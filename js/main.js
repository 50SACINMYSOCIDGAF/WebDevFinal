/**
 * ConnectHub - Main JavaScript
 * Core functionality for the social media platform
 */

document.addEventListener('DOMContentLoaded', function() {
    // Toast notification system
    const notificationContainer = document.getElementById('notification-container');
    
    /**
     * Shows a toast notification
     * @param {string} message - The notification message
     * @param {string} type - Notification type: success, error, warning, info
     * @param {number} duration - Duration in milliseconds before auto-close
     */
    window.showNotification = function(message, type = 'info', duration = 5000) {
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
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value; // Get token if needed

        // Default options
        options.headers = options.headers || {};
        options.headers['X-CSRF-Token'] = csrfToken;
        // *** ADD THIS LINE: Identify the request as AJAX ***
        options.headers['X-Requested-With'] = 'XMLHttpRequest';

        // Ensure credentials are sent if needed (e.g., for sessions)
        options.credentials = options.credentials || 'same-origin';

        return fetch(url, options)
            .then(response => {
                if (!response.ok) {
                    // Attempt to get more info for non-OK responses
                    return response.text().then(text => {
                        console.error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    });
                }
                // Check content type before parsing JSON
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json(); // Parse JSON only if header is correct
                } else {
                    // Handle cases where server didn't send JSON
                    return response.text().then(text => {
                        console.error("Received non-JSON response:", text);
                        throw new Error("Server did not return JSON.");
                    });
                }
            })
            .catch(error => {
                // Log fetch errors (network, CORS) or processing errors
                console.error('Fetch error in fetchWithCSRF:', error);
                // Re-throw the error so calling code's .catch block is triggered
                throw error;
            });
    };
    
    /**
     * Helper to submit forms via AJAX with proper CSRF handling
     * @param {HTMLFormElement} form - The form element
     * @returns {Promise} - Fetch promise with JSON response
     */
    window.submitFormAjax = function(form) {
        const formData = new FormData(form);
        
        return fetch(form.action, {
            method: form.method || 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': csrfToken
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('Form submission error:', error);
            throw error;
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
                closeModal(overlay);
            });
        });
    });
    
    /**
     * Opens a modal by ID
     * @param {string} modalId - The ID of the modal to open
     */
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    
    /**
     * Closes a modal
     * @param {Element} modal - The modal element to close
     */
    window.closeModal = function(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    };
    
    /**
     * Dropdown menu system
     */
    document.addEventListener('click', function(e) {
        // Close all dropdowns when clicking outside
        if (!e.target.closest('.dropdown-menu') && !e.target.closest('[data-toggle="dropdown"]')) {
            const dropdowns = document.querySelectorAll('.dropdown-menu.show');
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
    
    // Initialize dropdown toggles
    const dropdownToggles = document.querySelectorAll('[data-toggle="dropdown"]');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const targetId = this.getAttribute('data-target');
            const target = document.getElementById(targetId);
            
            if (target) {
                target.classList.toggle('show');
            }
        });
    });
    
    /**
     * Friend request functionality
     */
    const addFriendButtons = document.querySelectorAll('.add-friend');
    
    addFriendButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            
            fetchWithCSRF(`ajax/friend_request.php?user_id=${userId}`, {
                method: 'POST'
            })
            .then(data => {
                if (data.success) {
                    this.textContent = 'Requested';
                    this.classList.add('pending');
                    this.disabled = true;
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Something went wrong. Please try again.', 'error');
            });
        });
    });
    
    /**
     * Navigation handling
     */
    // Highlight active navbar link
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || (currentPage === '' && href === 'index.php')) {
            link.classList.add('active');
        }
    });
});