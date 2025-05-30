/**
 * ConnectHub - Main JavaScript
 * Core functionality for the social media platform
 */

document.addEventListener('DOMContentLoaded', function() {
    // Toast notification system
    const notificationContainer = document.getElementById('notification-container');

    // Retrieve CSRF token once the DOM is loaded and make it accessible globally within this script's scope
    // MODIFIED: Get token by ID
    const csrfToken = document.getElementById('csrf_token')?.value;

    /**
     * Shows a toast notification
     * @param {string} message - The notification message
     * @param {string} type - Notification type: success, error, warning, info
     * @param {number} duration - Duration in milliseconds before auto-close
     */
    window.showNotification = function(message, type = 'info', duration = 5000) {
        if (!notificationContainer) {
            console.warn("Notification container not found. Cannot display notification:", message);
            return;
        }
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;

        let icon = '';
        switch(type) {
            case 'success':
                icon = 'fa-check-circle';
                break;
            case 'error':
                icon = 'fa-exclamation-circle';
                break;
            case 'warning':
                icon = 'fa-exclamation-triangle';
                break;
            default:
                icon = 'fa-info-circle';
        }

        notification.innerHTML = `
            <div class="notification-header">
                <div class="notification-title">
                    <i class="fas ${icon}"></i>
                    <span>${type.charAt(0).toUpperCase() + type.slice(1)}</span>
                </div>
                <button class="notification-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="notification-message">${message}</div>
        `;

        notificationContainer.appendChild(notification);

        // Close button click event
        const closeButton = notification.querySelector('.notification-close');
        closeButton.addEventListener('click', function() {
            closeNotification(notification);
        });

        // Auto close after duration
        setTimeout(() => {
            closeNotification(notification);
        }, duration);
    };

    /**
     * Closes a notification with animation
     * @param {Element} notification - The notification element to close
     */
    function closeNotification(notification) {
        if (!notification) return;
        notification.classList.add('closing');

        // Remove from DOM after animation completes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    /**
     * Helper to make AJAX requests with proper CSRF handling
     * @param {string} url - The endpoint URL
     * @param {Object} options - Request options
     * @returns {Promise<Object>} - Fetch promise resolving with parsed JSON
     */
    window.fetchWithCSRF = function(url, options = {}) {
        // Ensure headers object exists
        options.headers = options.headers || {};

        // Add CSRF token to headers
        if (csrfToken) {
            options.headers['X-CSRF-Token'] = csrfToken;
        } else {
            console.warn("CSRF token not found on page. Request might fail."); // Warning updated
        }

        // Identify the request as AJAX
        options.headers['X-Requested-With'] = 'XMLHttpRequest';

        // Ensure credentials are sent if needed (e.g., for sessions)
        options.credentials = options.credentials || 'same-origin';

        console.log("fetchWithCSRF - Options being sent:", JSON.stringify(options, null, 2));

        return fetch(url, options)
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (!response.ok) {
                    // Attempt to get more info for non-OK responses
                    return response.text().then(text => {
                        console.error(`HTTP error! Status: ${response.status}, URL: ${url}, Response: ${text}`);
                        // Try to parse as JSON if it's an error response from our API
                        if (contentType && contentType.indexOf("application/json") !== -1) {
                            try {
                                const errorJson = JSON.parse(text);
                                throw { ...new Error(`HTTP error! Status: ${response.status}`), json: errorJson, status: response.status };
                            } catch (e) {
                                // If parsing fails, throw with text
                                throw { ...new Error(`HTTP error! Status: ${response.status}`), text: text, status: response.status };
                            }
                        }
                        throw { ...new Error(`HTTP error! Status: ${response.status}`), text: text, status: response.status };
                    });
                }
                // Check content type before parsing JSON
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        console.warn("Received non-JSON response from URL:", url, "Content:", text.substring(0,100)+"..."); // Log a snippet
                        // If expecting JSON but didn't get it, it's an issue.
                        // For now, let's return an object indicating it wasn't JSON, or throw an error.
                        // This depends on how forgiving the calling code should be.
                        // Throwing an error is generally safer if JSON is strictly expected.
                        throw new Error("Server did not return JSON as expected. URL: " + url);
                    });
                }
            })
            .catch(error => {
                // Log fetch errors (network, CORS) or processing errors
                console.error('Fetch error in fetchWithCSRF for URL:', url, error);
                // Re-throw the error so calling code's .catch block is triggered
                // Include more context if available (e.g., error.json or error.text)
                throw error;
            });
    };

    /**
     * Helper to submit forms via AJAX with proper CSRF handling
     * This function now correctly uses fetchWithCSRF.
     * @param {HTMLFormElement} form - The form element
     * @returns {Promise} - Fetch promise with JSON response
     */
    window.submitFormAjax = function(form) {
        const formData = new FormData(form);
        // If CSRF token input is not part of the form, fetchWithCSRF will add it from header.
        // If your form *does* include a csrf_token input, FormData will pick it up.
        // Ensure your backend checks header *or* body for the token.

        return window.fetchWithCSRF(form.action || window.location.pathname, { // Use current path if form.action is empty
            method: form.method || 'POST',
            body: formData
        })
            .catch(error => {
                console.error('Form submission error:', error);
                throw error; // Re-throw to be caught by caller
            });
    };

    /**
     * Modal system
     */
    const modalOverlays = document.querySelectorAll('.modal-overlay');

    modalOverlays.forEach(overlay => {
        // Close modal when clicking outside
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeModal(overlay);
            }
        });

        // Close button functionality
        const closeButtons = overlay.querySelectorAll('.modal-close');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const parentModal = this.closest('.modal-overlay');
                if (parentModal) {
                    closeModal(parentModal);
                }
            });
        });
    });

    /**
     * Opens a modal by ID
     * @param {string} modalId - The ID of the modal to open
     */
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.error("Modal with ID '" + modalId + "' not found.");
            return;
        }
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    };

    /**
     * Closes a modal
     * @param {Element|string} modalOrId - The modal element or its ID to close
     */
    window.closeModal = function(modalOrId) {
        const modal = (typeof modalOrId === 'string') ? document.getElementById(modalOrId) : modalOrId;
        if (!modal) {
            console.error("Modal not found for closing:", modalOrId);
            return;
        }
        modal.classList.remove('active');
        // Only reset body overflow if no other modals are active
        if (!document.querySelector('.modal-overlay.active')) {
            document.body.style.overflow = '';
        }
    };


    /**
     * Dropdown menu system
     */
    document.addEventListener('click', function(e) {
        // Close all dropdowns when clicking outside
        if (!e.target.closest('.dropdown-menu') && !e.target.closest('[data-toggle="dropdown"]')) {
            const dropdowns = document.querySelectorAll('.dropdown-menu.show, .post-dropdown.show, .profile-more-dropdown.visible'); // Added other dropdowns
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
                dropdown.classList.remove('visible'); // For profile more dropdown
            });
        }
    });

    // Initialize dropdown toggles
    const dropdownToggles = document.querySelectorAll('[data-toggle="dropdown"]');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const targetId = this.getAttribute('data-target') || this.dataset.target; // Prefer data-target
            const target = document.getElementById(targetId);

            if (target) {
                // Close other dropdowns first
                document.querySelectorAll('.dropdown-menu.show, .post-dropdown.show, .profile-more-dropdown.visible').forEach(d => {
                    if (d !== target) {
                        d.classList.remove('show');
                        d.classList.remove('visible');
                    }
                });
                target.classList.toggle('show');
            }
        });
    });

    /**
     * Friend request functionality (from index.php context, previously in main.js)
     * This should ideally be in a more specific JS file if it's only for one page,
     * or ensure the selectors are specific enough.
     */
    document.querySelectorAll('.add-friend').forEach(button => { // Make selector more specific if needed
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const originalText = this.textContent;
            const originalClasses = this.className;

            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled = true;

            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('action', 'add');
            // CSRF token will be added by fetchWithCSRF in headers

            fetchWithCSRF('ajax/friend_request.php', {
                method: 'POST',
                body: new URLSearchParams(formData) // Send as x-www-form-urlencoded
            })
                .then(data => {
                    if (data.success) {
                        this.textContent = 'Requested';
                        this.className = 'friend-button pending'; // Match profile.php button style
                        this.disabled = true; // Keep disabled after requested
                        showNotification(data.message, 'success');
                    } else {
                        this.textContent = originalText;
                        this.className = originalClasses;
                        this.disabled = false;
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    this.textContent = originalText;
                    this.className = originalClasses;
                    this.disabled = false;
                    showNotification('Something went wrong. Please try again.', 'error');
                    console.error("Error in add friend button:", error);
                });
        });
    });

    /**
     * Navigation handling
     */
    const currentPage = window.location.pathname.split('/').pop() || 'index.php'; // Default to index.php if path is empty
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        } else {
            link.classList.remove('active'); // Ensure others are not active
        }
    });
});