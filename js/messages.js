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
    const newMessageBtn = document.getElementById('new-message-btn');
    const newMessageModal = document.getElementById('new-message-modal');
    const recipientSearch = document.getElementById('recipient-search');
    const recipientResults = document.getElementById('recipient-results');
    const conversationSearch = document.getElementById('conversation-search');
    
    // Variables
    let conversations = [];
    let lastMessageId = 0;
    let isLoadingMore = false;
    let messagePollingInterval = null;
    
    /**
     * Load all conversations for the current user
     */
    function loadConversations() {
        fetchWithCSRF('ajax/get_conversations.php')
            .then(data => {
                if (data.success) {
                    conversations = data.conversations;
                    renderConversations(conversations);
                    
                    // If in a conversation, highlight it
                    if (partnerId) {
                        const conversationItem = document.querySelector(`.conversation-item[data-user-id="${partnerId}"]`);
                        if (conversationItem) {
                            conversationItem.classList.add('active');
                        }
                    }
                } else {
                    conversationsList.innerHTML = '<div class="error-message">Failed to load conversations</div>';
                }
            })
            .catch(error => {
                conversationsList.innerHTML = '<div class="error-message">Error loading conversations</div>';
                console.error('Error loading conversations:', error);
            });
    }
    
    /**
     * Render the conversations list
     * @param {Array} conversationsData - List of conversation data
     */
    function renderConversations(conversationsData) {
        if (!conversationsList) return;
        
        if (conversationsData.length === 0) {
            conversationsList.innerHTML = `
                <div class="empty-state-small">
                    <p>No conversations yet</p>
                    <button id="start-conversation-btn" class="btn btn-sm">Start a conversation</button>
                </div>
            `;
            
            // Add event listener to the new button
            const startConversationBtn = document.getElementById('start-conversation-btn');
            if (startConversationBtn) {
                startConversationBtn.addEventListener('click', function() {
                    openModal('new-message-modal');
                });
            }
            return;
        }
        
        let html = '';
        conversationsData.forEach(conversation => {
            const unreadBadge = conversation.unread_count > 0 
                ? `<span class="unread-badge">${conversation.unread_count}</span>` 
                : '';
                
            html += `
                <div class="conversation-item${partnerId == conversation.user_id ? ' active' : ''}" data-user-id="${conversation.user_id}">
                    <div class="conversation-avatar">
                        <img src="${conversation.avatar}" alt="${conversation.username}">
                    </div>
                    <div class="conversation-info">
                        <div class="conversation-header">
                            <h4 class="conversation-name">${conversation.username}</h4>
                            <span class="conversation-time">${conversation.time_ago}</span>
                        </div>
                        <div class="conversation-preview">
                            <p>${truncateText(conversation.last_message, 40)}</p>
                            ${unreadBadge}
                        </div>
                    </div>
                </div>
            `;
        });
        
        conversationsList.innerHTML = html;
        
        // Add event listeners to conversation items
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
        
        const url = `ajax/get_messages.php?user_id=${partnerId}`;
        
        fetchWithCSRF(url)
            .then(data => {
                if (data.success) {
                    renderMessages(data.messages, scrollToBottom);
                    
                    // Update last message ID for pagination
                    if (data.messages.length > 0) {
                        const firstMessage = data.messages[0];
                        lastMessageId = firstMessage.id;
                    }
                    
                    // Start message polling for real-time updates
                    startMessagePolling();
                } else {
                    messagesThread.innerHTML = '<div class="error-message">Failed to load messages</div>';
                }
            })
            .catch(error => {
                messagesThread.innerHTML = '<div class="error-message">Error loading messages</div>';
                console.error('Error loading messages:', error);
            });
    }
    
    /**
     * Load more messages (pagination)
     */
    function loadMoreMessages() {
        if (!partnerId || isLoadingMore || lastMessageId <= 0) return;
        
        isLoadingMore = true;
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'loading-indicator';
        loadingIndicator.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
        messagesThread.prepend(loadingIndicator);
        
        const url = `ajax/get_messages.php?user_id=${partnerId}&before_id=${lastMessageId}`;
        
        fetchWithCSRF(url)
            .then(data => {
                // Remove loading indicator
                loadingIndicator.remove();
                
                if (data.success) {
                    // Get current scroll height before adding new messages
                    const scrollHeightBefore = messagesThread.scrollHeight;
                    
                    if (data.messages.length > 0) {
                        // Prepend older messages
                        renderMessages(data.messages, false, true);
                        
                        // Update last message ID for pagination
                        const firstMessage = data.messages[0];
                        lastMessageId = firstMessage.id;
                        
                        // Maintain scroll position
                        const scrollHeightAfter = messagesThread.scrollHeight;
                        messagesThread.scrollTop = messagesThread.scrollTop + (scrollHeightAfter - scrollHeightBefore);
                    } else {
                        // No more messages
                        lastMessageId = 0;
                    }
                }
                
                isLoadingMore = false;
            })
            .catch(error => {
                loadingIndicator.remove();
                isLoadingMore = false;
                console.error('Error loading more messages:', error);
            });
    }
    
    /**
     * Render messages in the thread
     * @param {Array} messages - List of message data
     * @param {boolean} scrollToBottom - Whether to scroll to bottom after rendering
     * @param {boolean} prepend - Whether to prepend messages (for pagination)
     */
    function renderMessages(messages, scrollToBottom = true, prepend = false) {
        if (!messagesThread) return;
        
        if (messages.length === 0 && !prepend) {
            messagesThread.innerHTML = `
                <div class="empty-messages">
                    <div class="empty-state-small">
                        <i class="far fa-paper-plane"></i>
                        <p>No messages yet</p>
                        <p class="text-muted">Start the conversation by sending a message below.</p>
                    </div>
                </div>
            `;
            return;
        }
        
        let html = '';
        let currentDate = '';
        
        messages.forEach(message => {
            const isFromMe = message.sender_id == currentUserId;
            const messageDate = new Date(message.timestamp).toLocaleDateString();
            
            // Add date separator if needed
            if (messageDate !== currentDate) {
                html += `
                    <div class="message-date-separator">
                        <span>${formatMessageDate(message.timestamp)}</span>
                    </div>
                `;
                currentDate = messageDate;
            }
            
            html += `
                <div class="message ${isFromMe ? 'message-sent' : 'message-received'}">
                    ${!isFromMe ? `<img src="${message.sender_avatar}" alt="${message.sender_name}" class="message-avatar">` : ''}
                    <div class="message-bubble">
                        <div class="message-text">${message.content}</div>
                        <div class="message-meta">
                            <span class="message-time">${formatMessageTime(message.timestamp)}</span>
                            ${isFromMe ? `<span class="message-status">${message.is_read ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>'}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        if (prepend) {
            // Insert at the beginning for pagination
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            while (tempDiv.firstChild) {
                messagesThread.insertBefore(tempDiv.firstChild, messagesThread.firstChild);
            }
        } else {
            // Replace all content
            messagesThread.innerHTML = html;
            
            // Scroll to bottom if requested
            if (scrollToBottom) {
                messagesThread.scrollTop = messagesThread.scrollHeight;
            }
        }
    }
    
    /**
     * Send a message to the current conversation partner
     */
    function sendMessage() {
        if (!messageInput || !partnerId) return;
        
        const content = messageInput.value.trim();
        if (content === '') return;
        
        const formData = new FormData();
        formData.append('receiver_id', partnerId);
        formData.append('content', content);
        
        // Optimistic UI update
        const timestamp = new Date().toISOString();
        const optimisticMessage = {
            id: 'temp-' + Date.now(),
            sender_id: currentUserId,
            receiver_id: partnerId,
            content: content,
            timestamp: timestamp,
            time_ago: 'just now',
            is_read: false,
            is_from_me: true
        };
        
        // Add to UI immediately
        appendMessage(optimisticMessage);
        
        // Clear input
        messageInput.value = '';
        messageInput.focus();
        sendMessageBtn.disabled = true;
        
        // Send to server
        fetchWithCSRF('ajax/send_message.php', {
            method: 'POST',
            body: formData
        })
        .then(data => {
            if (!data.success) {
                showNotification(data.message || 'Failed to send message', 'error');
                // Remove optimistic message on failure
                const tempMessage = document.querySelector(`[data-message-id="temp-${optimisticMessage.id}"]`);
                if (tempMessage) {
                    tempMessage.remove();
                }
            }
        })
        .catch(error => {
            showNotification('Error sending message. Please try again.', 'error');
            console.error('Error sending message:', error);
        });
    }
    
    /**
     * Append a single message to the thread
     * @param {Object} message - Message data
     */
    function appendMessage(message) {
        if (!messagesThread) return;
        
        const isFromMe = message.sender_id == currentUserId;
        const messageElement = document.createElement('div');
        messageElement.className = `message ${isFromMe ? 'message-sent' : 'message-received'}`;
        messageElement.setAttribute('data-message-id', message.id);
        
        messageElement.innerHTML = `
            ${!isFromMe ? `<img src="${message.sender_avatar || 'https://via.placeholder.com/50'}" alt="${message.sender_name || 'User'}" class="message-avatar">` : ''}
            <div class="message-bubble">
                <div class="message-text">${message.content}</div>
                <div class="message-meta">
                    <span class="message-time">${formatMessageTime(message.timestamp)}</span>
                    ${isFromMe ? `<span class="message-status">${message.is_read ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>'}</span>` : ''}
                </div>
            </div>
        `;
        
        messagesThread.appendChild(messageElement);
        messagesThread.scrollTop = messagesThread.scrollHeight;
    }
    
    /**
     * Start polling for new messages
     */
    function startMessagePolling() {
        if (messagePollingInterval) {
            clearInterval(messagePollingInterval);
        }
        
        // Poll every 5 seconds for new messages
        messagePollingInterval = setInterval(() => {
            if (!partnerId) return;
            
            fetchWithCSRF(`ajax/get_messages.php?user_id=${partnerId}&after_id=${lastMessageId}`)
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        const newMessages = data.messages.filter(msg => 
                            !document.querySelector(`[data-message-id="${msg.id}"]`));
                        
                        // Only append new messages
                        newMessages.forEach(message => {
                            appendMessage(message);
                        });
                        
                        // Update conversations list to show latest message
                        loadConversations();
                    }
                })
                .catch(error => {
                    console.error('Error polling messages:', error);
                });
        }, 5000);
    }
    
    /**
     * Search for users to message
     * @param {string} query - Search query
     */
    function searchUsers(query) {
        if (query.trim() === '') {
            recipientResults.innerHTML = '';
            return;
        }
        
        recipientResults.innerHTML = '<div class="loading-spinner small"><i class="fas fa-circle-notch fa-spin"></i></div>';
        
        fetchWithCSRF(`ajax/search.php?q=${encodeURIComponent(query)}&type=users`)
            .then(data => {
                if (data.users && data.users.length > 0) {
                    let html = '';
                    data.users.forEach(user => {
                        html += `
                            <div class="recipient-item" data-user-id="${user.id}">
                                <img src="${user.avatar}" alt="${user.username}" class="user-avatar-small">
                                <div class="recipient-info">
                                    <div class="recipient-name">${user.username}</div>
                                    <div class="recipient-meta">${user.full_name || ''}</div>
                                </div>
                            </div>
                        `;
                    });
                    recipientResults.innerHTML = html;
                    
                    // Add click event listeners
                    document.querySelectorAll('.recipient-item').forEach(item => {
                        item.addEventListener('click', function() {
                            const userId = this.getAttribute('data-user-id');
                            window.location.href = `messages.php?user_id=${userId}`;
                        });
                    });
                } else {
                    recipientResults.innerHTML = '<div class="no-results">No users found</div>';
                }
            })
            .catch(error => {
                recipientResults.innerHTML = '<div class="error-message">Error searching users</div>';
                console.error('Error searching users:', error);
            });
    }
    
    /**
     * Search conversations
     * @param {string} query - Search query
     */
    function searchConversations(query) {
        if (!conversations) return;
        
        if (query.trim() === '') {
            renderConversations(conversations);
            return;
        }
        
        const queryLower = query.toLowerCase();
        const filtered = conversations.filter(conv => {
            return conv.username.toLowerCase().includes(queryLower) ||
                   conv.last_message.toLowerCase().includes(queryLower);
        });
        
        renderConversations(filtered);
    }
    
    // Event listeners
    
    // Send message button
    if (sendMessageBtn) {
        sendMessageBtn.addEventListener('click', sendMessage);
    }
    
    // Message input changes
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            sendMessageBtn.disabled = this.value.trim() === '';
        });
        
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() !== '') {
                    sendMessage();
                }
            }
        });
    }
    
    // Scroll to load more messages
    if (messagesThread) {
        messagesThread.addEventListener('scroll', function() {
            if (this.scrollTop === 0 && !isLoadingMore && lastMessageId > 0) {
                loadMoreMessages();
            }
        });
    }
    
    // New message button
    if (newMessageBtn) {
        newMessageBtn.addEventListener('click', function() {
            openModal('new-message-modal');
        });
    }
    
    // Recipient search
    if (recipientSearch) {
        let searchTimeout;
        recipientSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchUsers(this.value);
            }, 300);
        });
    }
    
    // Conversation search
    if (conversationSearch) {
        let searchTimeout;
        conversationSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchConversations(this.value);
            }, 300);
        });
    }
    
    // Load data on page load
    loadConversations();
    if (partnerId) {
        loadMessages();
    }
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (messagePollingInterval) {
            clearInterval(messagePollingInterval);
        }
    });
    
    // Helper functions
    
    /**
     * Format message date for display
     * @param {string} timestamp - ISO timestamp
     * @return {string} Formatted date
     */
    function formatMessageDate(timestamp) {
        const date = new Date(timestamp);
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        // Check if date is today
        if (date.toDateString() === today.toDateString()) {
            return 'Today';
        }
        
        // Check if date is yesterday
        if (date.toDateString() === yesterday.toDateString()) {
            return 'Yesterday';
        }
        
        // Check if date is within the last 7 days
        const daysDiff = Math.floor((today - date) / (1000 * 60 * 60 * 24));
        if (daysDiff < 7) {
            return date.toLocaleDateString('en-US', { weekday: 'long' });
        }
        
        // Format date for older messages
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }
    
    /**
     * Format message time for display
     * @param {string} timestamp - ISO timestamp
     * @return {string} Formatted time
     */
    function formatMessageTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    
    /**
     * Truncate text to specified length
     * @param {string} text - Text to truncate
     * @param {number} length - Maximum length
     * @return {string} Truncated text
     */
    function truncateText(text, length) {
        if (text.length <= length) return text;
        return text.substring(0, length) + '...';
    }
});