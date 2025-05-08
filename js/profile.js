/**
 * ConnectHub - Profile Page JavaScript
 * Handles user interactions on profile pages
 */

document.addEventListener('DOMContentLoaded', function() {
    // Profile elements
    const profileTabs = document.querySelectorAll('.profile-tab');
    const profileTabContents = document.querySelectorAll('.profile-tab-content');
    const profileMoreBtn = document.getElementById('profile-more-btn');
    const profileMoreDropdown = document.getElementById('profile-more-dropdown');
    const editProfilePicture = document.getElementById('edit-profile-picture');
    const editCoverBtn = document.getElementById('edit-cover-btn');
    const editAboutBtn = document.querySelector('.edit-about-btn');
    
    // Modals
    const profilePictureModal = document.getElementById('profile-picture-modal');
    const coverPhotoModal = document.getElementById('cover-photo-modal');
    const bioModal = document.getElementById('bio-modal');
    const reportUserModal = document.getElementById('report-user-modal');
    
    // Friend action buttons
    const friendActionButtons = document.querySelectorAll('.friend-action');
    const messageActionButtons = document.querySelectorAll('.message-action');
    const blockActionButtons = document.querySelectorAll('.block-action');
    const reportActionButtons = document.querySelectorAll('.report-action');
    
    // Handle tab clicks
    if (profileTabs) {
        profileTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and hide all content
                profileTabs.forEach(t => t.classList.remove('active'));
                profileTabContents.forEach(c => c.style.display = 'none');
                
                // Add active class to clicked tab and show corresponding content
                this.classList.add('active');
                document.getElementById(`tab-${tabId}`).style.display = 'block';
            });
        });
    }
    
    // Handle profile more dropdown
    if (profileMoreBtn && profileMoreDropdown) {
        profileMoreBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileMoreDropdown.classList.toggle('visible');
        });
        
        document.addEventListener('click', function(e) {
            if (!profileMoreBtn.contains(e.target) && !profileMoreDropdown.contains(e.target)) {
                profileMoreDropdown.classList.remove('visible');
            }
        });
    }
    
    // Handle profile picture edit
    if (editProfilePicture && profilePictureModal) {
        editProfilePicture.addEventListener('click', function() {
            profilePictureModal.classList.add('active');
        });
        
        document.getElementById('profile-picture-close').addEventListener('click', function() {
            profilePictureModal.classList.remove('active');
        });
        
        document.getElementById('profile-picture-cancel').addEventListener('click', function() {
            profilePictureModal.classList.remove('active');
        });
        
        // Handle file input
        const profilePictureInput = document.getElementById('profile-picture-input');
        const profilePreview = document.getElementById('profile-preview');
        
        if (profilePictureInput && profilePreview) {
            profilePictureInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePreview.src = e.target.result;
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        
        // Handle upload button click (trigger file input)
        const uploadButton = profilePictureModal.querySelector('.upload-button');
        if (uploadButton) {
            uploadButton.addEventListener('click', function() {
                profilePictureInput.click();
            });
        }
        
        // Handle submit
        document.getElementById('profile-picture-submit').addEventListener('click', function() {
            if (profilePictureInput.files.length === 0) {
                showNotification('Please select an image file.', 'warning');
                return;
            }
            
            const formData = new FormData(document.getElementById('profile-picture-form'));
            
            fetchWithCSRF('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.success) {
                    showNotification('Profile picture updated successfully!', 'success');
                    profilePictureModal.classList.remove('active');
                    
                    // Update profile picture on page
                    const profilePictures = document.querySelectorAll('.profile-picture');
                    profilePictures.forEach(img => {
                        img.src = profilePreview.src;
                    });
                    
                    // Hide placeholder if any
                    const placeholders = document.querySelectorAll('.profile-picture-placeholder');
                    placeholders.forEach(placeholder => {
                        placeholder.style.display = 'none';
                    });
                    
                    // Reload page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification(response.message || 'Failed to update profile picture. Please try again.', 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred. Please try again.', 'error');
                console.error('Error updating profile picture:', error);
            });
        });
    }
    
    // Handle cover photo edit
    if (editCoverBtn && coverPhotoModal) {
        editCoverBtn.addEventListener('click', function() {
            coverPhotoModal.classList.add('active');
        });
        
        document.getElementById('cover-photo-close').addEventListener('click', function() {
            coverPhotoModal.classList.remove('active');
        });
        
        document.getElementById('cover-photo-cancel').addEventListener('click', function() {
            coverPhotoModal.classList.remove('active');
        });
        
        // Handle file input
        const coverPhotoInput = document.getElementById('cover-photo-input');
        const coverPreview = document.getElementById('cover-preview');
        const coverPreviewContainer = document.getElementById('cover-preview-container');
        
        if (coverPhotoInput && coverPreviewContainer) {
            coverPhotoInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (!coverPreview) {
                            // Create image if it doesn't exist
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.id = 'cover-preview';
                            img.alt = 'Preview';
                            
                            // Clear container and add image
                            coverPreviewContainer.innerHTML = '';
                            coverPreviewContainer.appendChild(img);
                        } else {
                            coverPreview.src = e.target.result;
                        }
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        
        // Handle upload button click (trigger file input)
        const uploadButton = coverPhotoModal.querySelector('.upload-button');
        if (uploadButton) {
            uploadButton.addEventListener('click', function() {
                coverPhotoInput.click();
            });
        }
        
        // Handle submit
        document.getElementById('cover-photo-submit').addEventListener('click', function() {
            if (coverPhotoInput.files.length === 0) {
                showNotification('Please select an image file.', 'warning');
                return;
            }
            
            const formData = new FormData(document.getElementById('cover-photo-form'));
            
            fetchWithCSRF('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.success) {
                    showNotification('Cover photo updated successfully!', 'success');
                    coverPhotoModal.classList.remove('active');
                    
                    // Reload page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification(response.message || 'Failed to update cover photo. Please try again.', 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred. Please try again.', 'error');
                console.error('Error updating cover photo:', error);
            });
        });
    }
    
    // Handle bio edit
    if (editAboutBtn && bioModal) {
        editAboutBtn.addEventListener('click', function() {
            bioModal.classList.add('active');
        });
        
        document.getElementById('bio-close').addEventListener('click', function() {
            bioModal.classList.remove('active');
        });
        
        document.getElementById('bio-cancel').addEventListener('click', function() {
            bioModal.classList.remove('active');
        });
        
        // Handle submit
        document.getElementById('bio-submit').addEventListener('click', function() {
            const bioText = document.getElementById('bio-text').value;
            
            const formData = new FormData(document.getElementById('bio-form'));
            
            fetchWithCSRF('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.success) {
                    showNotification('Bio updated successfully!', 'success');
                    bioModal.classList.remove('active');
                    
                    // Update bio on page
                    const bioElements = document.querySelectorAll('.profile-bio');
                    bioElements.forEach(element => {
                        element.innerHTML = bioText ? bioText.replace(/\n/g, '<br>') : 'No bio yet...';
                    });
                } else {
                    showNotification(response.message || 'Failed to update bio. Please try again.', 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred. Please try again.', 'error');
                console.error('Error updating bio:', error);
            });
        });
    }
    
    // Friend actions (Add friend, accept request, unfriend, etc.)
    if (friendActionButtons.length > 0) {
        friendActionButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const action = this.getAttribute('data-action');
                
                fetchWithCSRF('ajax/friend_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `user_id=${userId}&action=${action}&csrf_token=${encodeURIComponent(document.querySelector('input[name="csrf_token"]').value)}`
                })
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        
                        // Update UI based on action
                        switch (action) {
                            case 'add':
                                this.setAttribute('data-action', 'cancel');
                                this.innerHTML = '<i class="fas fa-user-clock"></i><span>Requested</span>';
                                this.classList.remove('profile-action-primary');
                                this.classList.add('profile-action-secondary');
                                break;
                            case 'accept':
                                this.setAttribute('data-action', 'unfriend');
                                this.innerHTML = '<i class="fas fa-user-check"></i><span>Friends</span>';
                                this.classList.remove('profile-action-primary');
                                this.classList.add('profile-action-secondary');
                                break;
                            case 'cancel':
                            case 'unfriend':
                                this.setAttribute('data-action', 'add');
                                this.innerHTML = '<i class="fas fa-user-plus"></i><span>Add Friend</span>';
                                this.classList.remove('profile-action-secondary');
                                this.classList.add('profile-action-primary');
                                break;
                        }
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Something went wrong. Please try again.', 'error');
                    console.error('Error processing friend action:', error);
                });
            });
        });
    }
    
    // Message action (Open messages)
    if (messageActionButtons.length > 0) {
        messageActionButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                window.location.href = `messages.php?user_id=${userId}`;
            });
        });
    }
    
    // Block user action
    if (blockActionButtons.length > 0) {
        blockActionButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const username = document.querySelector('.profile-name').textContent.trim();
                
                if (confirm(`Are you sure you want to block ${username}? They will not be able to contact you or see your content.`)) {
                    fetchWithCSRF('ajax/block_user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `user_id=${userId}&action=block&csrf_token=${encodeURIComponent(document.querySelector('input[name="csrf_token"]').value)}`
                    })
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            
                            // Update UI - reload page after short delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Something went wrong. Please try again.', 'error');
                        console.error('Error blocking user:', error);
                    });
                }
            });
        });
    }
    
    // Report user action
    if (reportActionButtons.length > 0) {
        reportActionButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                
                // Open report modal
                if (reportUserModal) {
                    // Set user ID
                    const userIdInput = reportUserModal.querySelector('input[name="user_id"]');
                    if (userIdInput) {
                        userIdInput.value = userId;
                    }
                    
                    reportUserModal.classList.add('active');
                }
            });
        });
        
        // Handle report modal close
        if (reportUserModal) {
            document.getElementById('report-user-close').addEventListener('click', function() {
                reportUserModal.classList.remove('active');
            });
            
            document.getElementById('report-user-cancel').addEventListener('click', function() {
                reportUserModal.classList.remove('active');
            });
            
            // Handle report form submission
            document.getElementById('report-user-submit').addEventListener('click', function() {
                const reportForm = document.getElementById('report-user-form');
                const reasonSelect = document.getElementById('report-reason');
                
                if (!reasonSelect.value) {
                    showNotification('Please select a reason for your report.', 'warning');
                    return;
                }
                
                const formData = new FormData(reportForm);
                
                fetchWithCSRF('ajax/report_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        reportUserModal.classList.remove('active');
                        reportForm.reset();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Something went wrong. Please try again.', 'error');
                    console.error('Error reporting user:', error);
                });
            });
        }
    }
    
    // Handle music tab
    const musicTab = document.querySelector('[data-tab="music"]');
    if (musicTab) {
        const profileMusic = document.getElementById('profile-music');
        const playPauseBtn = document.querySelector('.play-pause');
        
        if (profileMusic && playPauseBtn) {
            playPauseBtn.addEventListener('click', function() {
                if (profileMusic.paused) {
                    profileMusic.play();
                    this.innerHTML = '<i class="fas fa-pause"></i>';
                } else {
                    profileMusic.pause();
                    this.innerHTML = '<i class="fas fa-play"></i>';
                }
            });
            
            // Update progress bar
            profileMusic.addEventListener('timeupdate', function() {
                const progressBar = document.querySelector('.music-progress-bar');
                if (progressBar) {
                    const progress = (this.currentTime / this.duration) * 100;
                    progressBar.style.width = `${progress}%`;
                }
            });
            
            // Reset on end
            profileMusic.addEventListener('ended', function() {
                playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                const progressBar = document.querySelector('.music-progress-bar');
                if (progressBar) {
                    progressBar.style.width = '0%';
                }
            });
        }
    }
    
    // Close any modal when clicking outside
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            // Only close if clicking directly on the overlay
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
});