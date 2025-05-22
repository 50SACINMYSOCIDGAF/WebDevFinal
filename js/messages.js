/**
 * ConnectHub - Messages JavaScript
 * Handles real-time messaging functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const conversationsList = document.getElementById('conversations-list');
    const messagesThread = document.getElementById('messages-thread');
    const messageInput = document.getElementById('message-input');
    const sendMessageBtn = document.getElementById('send-message');
    const currentUserId = document.getElementById('current-user-id')?.value;
    const partnerId = document.getElementById('partner-id')?.value;
    const newMessageBtnTrigger = document.getElementById('new-message-btn'); // Button that opens modal
    const newMessageModal = document.getElementById('new-message-modal');
    const recipientSearchInput = document.getElementById('recipient-search'); // Input in modal
    const recipientResultsContainer = document.getElementById('recipient-results'); // Container in modal
    const conversationSearchInput = document.getElementById('conversation-search'); // Input in sidebar

    // Variables
    let conversations = [];
    let lastFetchedMessageId = 0; // Used for fetching messages *after* this ID
    let oldestMessageId = 0; // Used for fetching messages *before* this ID (pagination)
    let isLoadingMore = false;
    let messagePollingInterval = null;

    /**
     * Load all conversations for the current user
     */
    function loadConversations() {
        if (!conversationsList) return;
        conversationsList.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Loading conversations...</span></div>';

        fetchWithCSRF('ajax/get_conversations.php')
            .then(data => {
                if (data.success) {
                    conversations = data.conversations;
                    renderConversations(conversations);

                    if (partnerId) {
                        const activeConv = document.querySelector(`.conversation-item[data-user-id="${partnerId}"]`);
                        if (activeConv) activeConv.classList.add('active');
                    }
                } else {
                    conversationsList.innerHTML = `<div class="error-message">${data.message || 'Failed to load conversations'}</div>`;
                }
            })
            .catch(error => {
                conversationsList.innerHTML = '<div class="error-message">Error loading conversations. Please try again.</div>';
                console.error('Error loading conversations:', error);
            });
    }

    /**
     * Render the conversations list
     * @param {Array} conversationsData - List of conversation data
     */
    function renderConversations(conversationsData) {
        if (!conversationsList) return;

        if (!conversationsData || conversationsData.length === 0) {
            // CORRECTED: Removed JS comments from HTML string
            conversationsList.innerHTML = `
            <div class="empty-state conversations-empty-state">
                <div class="empty-state-icon" style="font-size: 2rem; margin-bottom: 0.75rem;">
                    <i class="far fa-comments"></i>
                </div>
                <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;">No Conversations Yet</h3>
                <p style="font-size: 0.9rem; margin-bottom: 1rem;">Start a new chat using the button below.</p>
                <button id="start-conversation-btn-empty" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> New Chat
                </button>
            </div>`;
            const startConvBtnEmpty = document.getElementById('start-conversation-btn-empty');
            if (startConvBtnEmpty) {
                startConvBtnEmpty.addEventListener('click', () => {
                    if(newMessageModal) openModal('new-message-modal');
                    if(recipientSearchInput) recipientSearchInput.focus();
                });
            }
            return;
        }

        let html = '';
        conversationsData.forEach(conversation => {
            const unreadBadge = conversation.unread_count > 0
                ? `<span class="unread-badge">${conversation.unread_count}</span>`
                : '';
            const avatarSrc = conversation.avatar || 'assets/default-avatar.png'; // Fallback avatar

            html += `
            <div class="conversation-item${partnerId == conversation.user_id ? ' active' : ''}" data-user-id="${conversation.user_id}">
                <div class="conversation-avatar">
                    <img src="${avatarSrc}" alt="${conversation.username}">
                </div>
                <div class="conversation-info">
                    <div class="conversation-info-header">
                        <h4 class="conversation-name">${conversation.username}</h4>
                        <span class="conversation-time">${conversation.time_ago || ''}</span>
                    </div>
                    <div class="conversation-preview">
                        <p>${truncateText(conversation.last_message, 30)}</p>
                        ${unreadBadge}
                    </div>
                </div>
            </div>`;
        });
        conversationsList.innerHTML = html;

        document.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                window.location.href = `messages.php?user_id=${userId}`;
            });
        });
    }

    /**
     * Load messages for current conversation
     * @param {boolean} scrollToBottom - Whether to scroll to bottom after loading
     */
    function loadMessages(scrollToBottom = true) {
        if (!partnerId || !messagesThread) return;
        messagesThread.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Loading messages...</span></div>';

        const url = `ajax/get_messages.php?user_id=${partnerId}`;

        fetchWithCSRF(url)
            .then(data => {
                if (data.success) {
                    renderMessages(data.messages, scrollToBottom);
                    if (data.messages.length > 0) {
                        oldestMessageId = data.messages[0].id; // For pagination: oldest is the first in initial load (desc order from server)
                        lastFetchedMessageId = data.messages[data.messages.length - 1].id; // For polling: newest
                    } else {
                        oldestMessageId = 0;
                        lastFetchedMessageId = 0;
                    }
                    startMessagePolling();
                } else {
                    messagesThread.innerHTML = `<div class="error-message">${data.message || 'Failed to load messages'}</div>`;
                }
            })
            .catch(error => {
                messagesThread.innerHTML = '<div class="error-message">Error loading messages. Please try again.</div>';
                console.error('Error loading messages:', error);
            });
    }

    /**
     * Load more messages (pagination)
     */
    function loadMoreMessages() {
        if (!partnerId || isLoadingMore || oldestMessageId <= 0) return;

        isLoadingMore = true;
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'loading-spinner small'; // Use small spinner
        loadingIndicator.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
        if (messagesThread) messagesThread.prepend(loadingIndicator);

        const url = `ajax/get_messages.php?user_id=${partnerId}&before_id=${oldestMessageId}`;

        fetchWithCSRF(url)
            .then(data => {
                loadingIndicator.remove();
                if (data.success) {
                    const scrollHeightBefore = messagesThread ? messagesThread.scrollHeight : 0;
                    if (data.messages.length > 0) {
                        renderMessages(data.messages, false, true); // Prepend
                        oldestMessageId = data.messages[0].id;
                        if (messagesThread) {
                            const scrollHeightAfter = messagesThread.scrollHeight;
                            messagesThread.scrollTop += (scrollHeightAfter - scrollHeightBefore); // Adjust scroll
                        }
                    } else {
                        oldestMessageId = 0; // No more older messages
                    }
                } else {
                    showNotification(data.message || "Could not load older messages.", "error");
                }
                isLoadingMore = false;
            })
            .catch(error => {
                loadingIndicator.remove();
                isLoadingMore = false;
                console.error('Error loading more messages:', error);
                showNotification("Error loading older messages.", "error");
            });
    }

    /**
     * Render messages in the thread
     * @param {Array} messagesData - List of message data
     * @param {boolean} scrollToBottom - Whether to scroll to bottom after rendering
     * @param {boolean} prepend - Whether to prepend messages (for pagination)
     */
    function renderMessages(messagesData, scrollToBottom = true, prepend = false) {
        if (!messagesThread) return;

        if (messagesData.length === 0 && !prepend) {
            messagesThread.innerHTML = `
                <div class="empty-state">
                     <div class="empty-state-icon"><i class="far fa-comments"></i></div>
                     <h3>Start of your conversation</h3>
                     <p>Send a message to get things started.</p>
                </div>`;
            return;
        }

        let htmlContent = '';
        let lastMessageDate = '';

        messagesData.forEach(message => {
            const messageTimestamp = new Date(message.timestamp);
            const messageDate = messageTimestamp.toLocaleDateString();

            if (messageDate !== lastMessageDate && !prepend) { // Add date separator only for initial load or new messages, not pagination
                htmlContent += `<div class="message-date-separator"><span>${formatMessageDate(message.timestamp)}</span></div>`;
                lastMessageDate = messageDate;
            }
            if (prepend && messageDate !== (messagesThread.dataset.lastRenderedDate || '')) { // For prepending, only if different from previously rendered top date
                htmlContent = `<div class="message-date-separator"><span>${formatMessageDate(message.timestamp)}</span></div>` + htmlContent;
                messagesThread.dataset.lastRenderedDate = messageDate; // Store it
            }


            const isFromMe = message.sender_id == currentUserId;
            const avatarSrc = (isFromMe ? (document.querySelector('.user-avatar-small')?.src) : message.sender_avatar) || 'assets/default-avatar.png';

            htmlContent += `
                <div class="message ${isFromMe ? 'message-sent' : 'message-received'}" data-message-id="${message.id}">
                    ${!isFromMe ? `<img src="${avatarSrc}" alt="${message.sender_name}" class="message-avatar">` : ''}
                    <div class="message-bubble">
                        <div class="message-text">${message.content.replace(/\n/g, '<br>')}</div>
                        <div class="message-meta">
                            <span class="message-time">${formatMessageTime(message.timestamp)}</span>
                            ${isFromMe ? `<span class="message-status">${message.is_read ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>'}</span>` : ''}
                        </div>
                    </div>
                </div>`;
        });

        if (prepend) {
            const firstChild = messagesThread.firstChild;
            messagesThread.insertAdjacentHTML('afterbegin', htmlContent);
            if(firstChild && firstChild.classList.contains('loading-spinner')) firstChild.remove(); // remove spinner if it was prepended
        } else {
            // If it was empty state, clear it first
            const emptyState = messagesThread.querySelector('.empty-state');
            if(emptyState) emptyState.remove();
            messagesThread.innerHTML = htmlContent;
        }

        if (scrollToBottom) {
            messagesThread.scrollTop = messagesThread.scrollHeight;
        }
    }

    /**
     * Send a message to the current conversation partner
     */
    function sendMessage() {
        if (!messageInput || !partnerId || !sendMessageBtn) return;

        const content = messageInput.value.trim();
        if (content === '') return;

        // Get the CSRF token from the hidden input field on the page
        const csrfTokenFromPage = document.getElementById('csrf_token')?.value; // Ensure this ID exists on messages.php

        if (!csrfTokenFromPage) {
            showNotification('Security token missing. Please refresh the page.', 'error');
            console.error('CSRF token input field not found or has no value.');
            return;
        }

        const formData = new FormData();
        formData.append('receiver_id', partnerId);
        formData.append('content', content);
        formData.append('csrf_token', csrfTokenFromPage); // Add CSRF token to the form data

        const tempId = 'temp-' + Date.now();
        const currentUserAvatar = document.querySelector('.user-avatar-small')?.src || // Try to get current user's avatar from navbar
            document.querySelector('.conversation-avatar img[alt="' + (document.querySelector('.user-name')?.textContent || '') + '"]')?.src || // Try from conversation list if active
            'assets/default-avatar.png'; // Fallback
        const currentUserName = document.querySelector('.user-name')?.textContent || 'Me';


        const optimisticMessage = {
            id: tempId,
            sender_id: currentUserId, // Ensure currentUserId is available in this scope
            content: content,
            timestamp: new Date().toISOString(),
            is_read: false,
            sender_avatar: currentUserAvatar,
            sender_name: currentUserName
        };
        appendMessage(optimisticMessage);

        messageInput.value = '';
        messageInput.style.height = 'auto';
        messageInput.focus();
        sendMessageBtn.disabled = true;

        // fetchWithCSRF will still try to add the header, which is fine,
        // but the server will now prioritize the token from the POST body.
        fetchWithCSRF('ajax/send_message.php', {
            method: 'POST',
            body: new URLSearchParams(formData) // Send as x-www-form-urlencoded if that's what PHP expects for $_POST easily.
            // Or just 'body: formData' if PHP handles multipart/form-data from fetch correctly.
            // For simplicity and compatibility, URLSearchParams is often easier for PHP's $_POST.
        })
            .then(data => {
                const tempMessageElement = document.querySelector(`.message[data-message-id="${tempId}"]`);
                if (data.success && data.message_data) {
                    if (tempMessageElement) {
                        tempMessageElement.dataset.messageId = data.message_data.id;
                        // Update other message details if needed from server response
                        const timeMeta = tempMessageElement.querySelector('.message-time');
                        if(timeMeta) timeMeta.textContent = formatMessageTime(data.message_data.timestamp); // Or data.message_data.time_ago
                    }
                    lastFetchedMessageId = data.message_data.id;
                    loadConversations(); // Refresh conversation list
                } else {
                    showNotification(data.message || 'Failed to send message', 'error');
                    if (tempMessageElement) tempMessageElement.remove();
                }
            })
            .catch(error => {
                let errorMessage = 'Error sending message. Please try again.';
                if (error && error.json && error.json.message) {
                    errorMessage = error.json.message;
                } else if (error && error.text) {
                    errorMessage = `Error: ${error.status || 'Network error'}. Could not send message. Response: ${error.text.substring(0,100)}`;
                } else if (error && error.message) {
                    errorMessage = error.message;
                }
                showNotification(errorMessage, 'error');
                console.error('Error sending message:', error);
                const tempMessageElement = document.querySelector(`.message[data-message-id="${tempId}"]`);
                if (tempMessageElement) tempMessageElement.remove();
            });
    }

    /**
     * Append a single message to the thread
     * @param {Object} messageData - Message data
     */
    function appendMessage(messageData) {
        if (!messagesThread) return;

        // Clear empty state if present
        const emptyState = messagesThread.querySelector('.empty-state');
        if(emptyState) emptyState.remove();

        const isFromMe = messageData.sender_id == currentUserId;
        const messageElement = document.createElement('div');
        messageElement.className = `message ${isFromMe ? 'message-sent' : 'message-received'}`;
        messageElement.dataset.messageId = messageData.id; // Use dataset for ID

        const avatarSrc = (isFromMe ? (document.querySelector('.user-avatar-small')?.src) : messageData.sender_avatar) || 'assets/default-avatar.png';

        messageElement.innerHTML = `
            ${!isFromMe ? `<img src="${avatarSrc}" alt="${messageData.sender_name}" class="message-avatar">` : ''}
            <div class="message-bubble">
                <div class="message-text">${messageData.content.replace(/\n/g, '<br>')}</div>
                <div class="message-meta">
                    <span class="message-time">${formatMessageTime(messageData.timestamp)}</span>
                    ${isFromMe ? `<span class="message-status">${messageData.is_read ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>'}</span>` : ''}
                </div>
            </div>`;

        messagesThread.appendChild(messageElement);
        messagesThread.scrollTop = messagesThread.scrollHeight;
    }

    /**
     * Start polling for new messages
     */
    function startMessagePolling() {
        if (messagePollingInterval) clearInterval(messagePollingInterval);

        messagePollingInterval = setInterval(() => {
            if (!partnerId || document.hidden) return; // Don't poll if page is hidden or no partner

            // Fetch messages after the last one we know about
            fetchWithCSRF(`ajax/get_messages.php?user_id=${partnerId}&after_id=${lastFetchedMessageId}`)
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            // Only append if message not already on page (handles potential race conditions)
                            if (!document.querySelector(`.message[data-message-id="${message.id}"]`)) {
                                appendMessage(message);
                            }
                        });
                        if(data.messages.length > 0) {
                            lastFetchedMessageId = data.messages[data.messages.length - 1].id;
                        }
                        loadConversations(); // Refresh conversation list for unread counts/last message
                    }
                })
                .catch(error => console.error('Error polling messages:', error));
        }, 5000); // Poll every 5 seconds
    }

    /**
     * Search for users to message
     * @param {string} query - Search query
     */
    function searchUsers(query) {
        if (!recipientResultsContainer || !recipientSearchInput) return;
        if (query.trim() === '') {
            recipientResultsContainer.innerHTML = '';
            recipientResultsContainer.style.display = 'none';
            return;
        }

        recipientResultsContainer.innerHTML = '<div class="loading-spinner small"><i class="fas fa-circle-notch fa-spin"></i></div>';
        recipientResultsContainer.style.display = 'block';

        fetchWithCSRF(`ajax/search.php?q=${encodeURIComponent(query)}&type=users`) // Assuming search.php can filter by type=users
            .then(data => { // search.php returns an array directly, not {success:..., users:...}
                if (Array.isArray(data) && data.length > 0) {
                    let html = '';
                    // Filter for users, as search.php might return other types too
                    const users = data.filter(item => item.type === 'user');
                    if (users.length > 0) {
                        users.forEach(user => {
                            // Exclude current user from results
                            if (user.id.toString() === currentUserId) return;

                            html += `
                                <div class="recipient-item" data-user-id="${user.id}">
                                    <img src="${user.image || 'assets/default-avatar.png'}" alt="${user.name}" class="user-avatar-small">
                                    <div class="recipient-info">
                                        <div class="recipient-name">${user.name}</div>
                                        <div class="recipient-meta">${truncateText(user.meta || '', 40)}</div>
                                    </div>
                                </div>`;
                        });
                        recipientResultsContainer.innerHTML = html;
                    } else {
                        recipientResultsContainer.innerHTML = '<div class="no-results">No users found</div>';
                    }

                    document.querySelectorAll('.recipient-item').forEach(item => {
                        item.addEventListener('click', function() {
                            const userId = this.getAttribute('data-user-id');
                            window.location.href = `messages.php?user_id=${userId}`;
                        });
                    });
                } else {
                    recipientResultsContainer.innerHTML = '<div class="no-results">No users found</div>';
                }
            })
            .catch(error => {
                recipientResultsContainer.innerHTML = '<div class="error-message">Error searching users</div>';
                console.error('Error searching users:', error);
            });
    }

    /**
     * Search conversations
     * @param {string} query - Search query
     */
    function searchConversations(query) {
        if (!conversations) return;
        const queryLower = query.toLowerCase();

        if (queryLower.trim() === '') {
            renderConversations(conversations); // Show all if search is empty
            return;
        }

        const filtered = conversations.filter(conv =>
            conv.username.toLowerCase().includes(queryLower) ||
            (conv.last_message && conv.last_message.toLowerCase().includes(queryLower))
        );
        renderConversations(filtered);
    }

    // Event listeners
    if (sendMessageBtn) sendMessageBtn.addEventListener('click', sendMessage);

    if (messageInput) {
        messageInput.addEventListener('input', function() {
            if (sendMessageBtn) sendMessageBtn.disabled = this.value.trim() === '';
            // Auto-resize textarea
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() !== '') sendMessage();
            }
        });
    }

    if (messagesThread) {
        messagesThread.addEventListener('scroll', function() {
            if (this.scrollTop === 0 && !isLoadingMore && oldestMessageId > 0) {
                loadMoreMessages();
            }
        });
    }

    if (newMessageBtnTrigger && newMessageModal) {
        newMessageBtnTrigger.addEventListener('click', () => {
            openModal('new-message-modal');
            if (recipientSearchInput) recipientSearchInput.focus();
        });
    }

    if (recipientSearchInput) {
        let searchTimeout;
        recipientSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => searchUsers(this.value), 300);
        });
    }

    if (conversationSearchInput) {
        let conversationSearchTimeout;
        conversationSearchInput.addEventListener('input', function() {
            clearTimeout(conversationSearchTimeout);
            conversationSearchTimeout = setTimeout(() => searchConversations(this.value), 300);
        });
    }

    // Initial data load
    loadConversations();
    if (partnerId) {
        loadMessages();
    } else {
        // If no partner selected, clear the message thread and show empty state
        if (messagesThread) {
            messagesThread.innerHTML = `
                <div class="no-conversation-selected">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="far fa-comments"></i></div>
                        <h2>Your Messages</h2>
                        <p>Select a conversation or start a new one.</p>
                        <button id="new-message-btn-main" class="btn btn-primary">
                            <i class="fas fa-edit"></i> New Message
                        </button>
                    </div>
                </div>`;
            const newMessageBtnMain = document.getElementById('new-message-btn-main');
            if (newMessageBtnMain) {
                newMessageBtnMain.addEventListener('click', () => {
                    if(newMessageModal) openModal('new-message-modal');
                    if(recipientSearchInput) recipientSearchInput.focus();
                });
            }
        }
    }

    window.addEventListener('beforeunload', () => {
        if (messagePollingInterval) clearInterval(messagePollingInterval);
    });

    // Helper functions
    function formatMessageDate(timestamp) {
        const date = new Date(timestamp);
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        if (date.toDateString() === today.toDateString()) return 'Today';
        if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: (date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined) });
    }

    function formatMessageTime(timestamp) {
        return new Date(timestamp).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    function truncateText(text, length) {
        if (!text) return '';
        const strippedText = text.replace(/<[^>]*>?/gm, ''); // Basic HTML stripping
        return strippedText.length > length ? strippedText.substring(0, length) + '...' : strippedText;
    }
});