/**
 * ConnectHub - Notifications Page JavaScript
 * Handles fetching, displaying, and managing notifications.
 */
document.addEventListener('DOMContentLoaded', function() {
    const notificationListUL = document.getElementById('notification-list-ul');
    const markAllReadBtn = document.getElementById('mark-all-read-btn');
    const csrfTokenInput = document.getElementById('csrf_token'); // Assuming you have a hidden input with the token on notifications.php
    const csrfToken = csrfTokenInput ? csrfTokenInput.value : null;

    let currentPage = 0;
    const notificationsPerPage = 20; // Should match the limit in notifications.php initial load
    let isLoading = false;
    let noMoreNotifications = false;

    /**
     * Renders a single notification item.
     * @param {object} notification - The notification object.
     * @returns {string} - HTML string for the notification item.
     */
    function renderNotificationItem(notification) {
        const avatar = notification.from_user_avatar || 'assets/default-avatar.png'; // Default avatar
        const defaultIcon = '<i class="fas fa-bell" style="font-size: 24px; width: 40px; text-align: center;"></i>';
        const avatarHtml = notification.from_username ? `<img src="${avatar}" alt="${notification.from_username}">` : defaultIcon;

        return `
            <li class="notification-item ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}">
                <a href="${notification.link || '#'}" class="notification-link">
                    <div class="notification-avatar">
                        ${avatarHtml}
                    </div>
                    <div class="notification-content">
                        <p class="notification-message">${notification.message}</p>
                        <span class="notification-time">${notification.time_ago}</span>
                    </div>
                </a>
            </li>
        `;
    }

    /**
     * Loads notifications from the server.
     * @param {boolean} append - Whether to append to existing list or replace.
     */
    function loadNotifications(append = false) {
        if (isLoading || noMoreNotifications) return;
        isLoading = true;

        const offset = append ? notificationListUL.children.length : 0;
        if (!append) {
            notificationListUL.innerHTML = ''; // Clear for initial load or refresh
            noMoreNotifications = false; // Reset for non-append loads
        }

        // Add a loading indicator if appending
        if (append && notificationListUL) {
            const loadingLi = document.createElement('li');
            loadingLi.className = 'loading-indicator';
            loadingLi.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading more...';
            notificationListUL.appendChild(loadingLi);
        } else if (!append && notificationListUL) {
            // Show main loading for initial load
            notificationListUL.innerHTML = '<li class="empty-notifications"><i class="fas fa-spinner fa-spin"></i> Loading notifications...</li>';
        }


        fetchWithCSRF(`ajax/get_notifications.php?limit=${notificationsPerPage}&offset=${offset}`, {
            // GET requests usually don't send CSRF in body, fetchWithCSRF handles header
        })
            .then(data => {
                if (append) {
                    const loadingIndicator = notificationListUL.querySelector('.loading-indicator');
                    if (loadingIndicator) loadingIndicator.remove();
                }

                if (data.success) {
                    if (!append && data.notifications.length === 0) {
                        notificationListUL.innerHTML = `
                            <li class="empty-notifications">
                                <i class="fas fa-bell-slash"></i>
                                You have no notifications.
                            </li>`;
                        noMoreNotifications = true;
                    } else if (data.notifications.length > 0) {
                        if (!append) notificationListUL.innerHTML = ''; // Clear "Loading..." if we got results
                        data.notifications.forEach(notification => {
                            notificationListUL.insertAdjacentHTML('beforeend', renderNotificationItem(notification));
                        });
                        if (data.notifications.length < notificationsPerPage) {
                            noMoreNotifications = true;
                        }
                    } else if (append) { // No more notifications to append
                        noMoreNotifications = true;
                    }
                    updateNavbarNotificationCount(data.total_unread);
                } else {
                    if (!append) notificationListUL.innerHTML = '<li class="empty-notifications">Could not load notifications.</li>';
                    showNotification(data.message || 'Failed to load notifications.', 'error');
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                if (append) {
                    const loadingIndicator = notificationListUL.querySelector('.loading-indicator');
                    if (loadingIndicator) loadingIndicator.remove();
                }
                if (!append) notificationListUL.innerHTML = '<li class="empty-notifications">Error loading notifications.</li>';
                showNotification('An error occurred while fetching notifications.', 'error');
            })
            .finally(() => {
                isLoading = false;
            });
    }

    /**
     * Updates the notification count badge in the navbar.
     * @param {number} count - The number of unread notifications.
     */
    function updateNavbarNotificationCount(count) {
        const navBadge = document.getElementById('nav-notification-badge'); // Ensure navbar.php adds this ID
        if (navBadge) {
            if (count > 0) {
                navBadge.textContent = count;
                navBadge.style.display = 'inline-flex'; // Or your default display style for the badge
            } else {
                navBadge.style.display = 'none';
            }
        }
    }

    // Event listener for "Mark all as read" button
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking...';

            fetchWithCSRF('ajax/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_all_read&csrf_token=${encodeURIComponent(csrfToken)}`
            })
                .then(response => {
                    if (response.success) {
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                        markAllReadBtn.style.display = 'none'; // Hide button
                        updateNavbarNotificationCount(0);
                        showNotification('All notifications marked as read.', 'success');
                    } else {
                        showNotification(response.message || 'Failed to mark notifications as read.', 'error');
                    }
                })
                .catch(error => {
                    showNotification('An error occurred.', 'error');
                    console.error("Error marking all as read:", error);
                })
                .finally(() => {
                    markAllReadBtn.disabled = false;
                    markAllReadBtn.innerHTML = 'Mark all as read';
                });
        });
    }

    // Event delegation for clicking on individual notification links
    if (notificationListUL) {
        notificationListUL.addEventListener('click', function(e) {
            const notificationLink = e.target.closest('.notification-link');
            if (!notificationLink) return;

            const notificationItem = notificationLink.closest('.notification-item');
            if (notificationItem && notificationItem.classList.contains('unread')) {
                const notificationId = notificationItem.dataset.id;

                fetchWithCSRF('ajax/mark_notification_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=mark_one_read&notification_id=${encodeURIComponent(notificationId)}&csrf_token=${encodeURIComponent(csrfToken)}`
                })
                    .then(response => {
                        if (response.success) {
                            notificationItem.classList.remove('unread');
                            // Optionally decrement navbar count immediately
                            const navBadge = document.getElementById('nav-notification-badge');
                            if (navBadge) {
                                const currentCount = parseInt(navBadge.textContent) || 0;
                                updateNavbarNotificationCount(Math.max(0, currentCount - 1));
                            }
                        } else {
                            // Log error but allow navigation
                            console.warn("Failed to mark notification as read on click:", response.message);
                        }
                    })
                    .catch(error => console.error("Error marking one as read:", error));
                // Do not prevent default navigation; let the user go to the link.
                // The read status will be fully updated on the next page load/notification fetch.
            }
        });
    }

    // Infinite scroll or "Load More" button logic
    // For simplicity, let's use a "Load More" button for now.
    const loadMoreContainer = document.createElement('div');
    loadMoreContainer.style.textAlign = 'center';
    loadMoreContainer.style.padding = '1rem';
    const loadMoreBtn = document.createElement('button');
    loadMoreBtn.id = 'load-more-notifications-btn';
    loadMoreBtn.className = 'btn btn-secondary';
    loadMoreBtn.textContent = 'Load More Notifications';
    loadMoreContainer.appendChild(loadMoreBtn);

    if (notificationListUL && notificationListUL.parentNode) { // Ensure parent exists
        notificationListUL.parentNode.appendChild(loadMoreContainer);

        loadMoreBtn.addEventListener('click', function() {
            if (!noMoreNotifications) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                loadNotifications(true); // Append new notifications
            }
        });

        // After notifications are loaded (in loadNotifications success callback), re-enable button
        // This is handled implicitly by `isLoading = false` and the check in `loadNotifications`.
        // If `noMoreNotifications` is true, the button won't try to load more.
        // We should update the button text if no more notifications.
        const observer = new MutationObserver(() => {
            if (noMoreNotifications) {
                loadMoreBtn.textContent = 'No more notifications';
                loadMoreBtn.disabled = true;
            } else if (!isLoading) {
                loadMoreBtn.textContent = 'Load More Notifications';
                loadMoreBtn.disabled = false;
            }
        });
        observer.observe(notificationListUL, { childList: true, subtree: true }); // Observe changes to list
    }


    // Initial load of notifications
    if (notificationListUL) { // Only load if the list element exists on the page
        loadNotifications();
    } else {
        console.warn("Notification list container (notification-list-ul) not found. Cannot load notifications.");
    }
});