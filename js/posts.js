/**
 * ConnectHub - Posts JavaScript
 * Handles post creation, interaction, and comments
 */

document.addEventListener('DOMContentLoaded', function() {
    // Post creation elements
    const postInputTrigger = document.getElementById('post-input-trigger');
    const createPostModal = document.getElementById('create-post-modal');
    const postForm = document.getElementById('post-form');
    const postContent = document.getElementById('post-content');
    const postSubmitBtn = document.getElementById('post-submit');
    const imageInput = document.getElementById('post-image-input'); // This is the actual file input
    const imagePreview = document.getElementById('post-image-preview');
    const imagePreviewImg = document.getElementById('image-preview');
    const removeImageBtn = document.getElementById('remove-image');
    const postPrivacyBtn = document.getElementById('post-privacy-btn');
    const privacyDropdown = document.getElementById('privacy-dropdown');
    const privacyOptions = document.querySelectorAll('.privacy-option');
    const postPrivacyValue = document.getElementById('post-privacy-value');
    const privacyText = document.getElementById('privacy-text');

    // Location elements
    const locationBtn = document.getElementById('location-btn');
    const locationModal = document.getElementById('location-modal');
    const locationSearchInput = document.getElementById('location-search-input');
    const locationSearchResults = document.getElementById('location-search-results');
    const locationConfirmBtn = document.getElementById('location-confirm');
    const locationCancelBtn = document.getElementById('location-cancel');
    const locationContainer = document.getElementById('post-location-container');
    const locationText = document.getElementById('location-text');
    const removeLocationBtn = document.getElementById('remove-location');

    // Post menu & actions
    const postMenus = document.querySelectorAll('.post-menu');
    const reportButtons = document.querySelectorAll('.post-report');
    const reportModal = document.getElementById('report-modal');
    const reportPostIdInput = document.getElementById('report-post-id');
    const reportForm = document.getElementById('report-form');
    const reportSubmitBtn = document.getElementById('report-submit');
    const reportCancelBtn = document.getElementById('report-cancel');

    // Comments
    const commentButtons = document.querySelectorAll('.comment-button');
    const commentInputs = document.querySelectorAll('.comment-input');
    const commentSubmitButtons = document.querySelectorAll('.comment-submit');

    // Like buttons
    const likeButtons = document.querySelectorAll('.like-button');

    // Get the image upload button inside the modal
    const postImageUploadButton = document.getElementById('post-image-upload-button'); // New ID for the button

    /**
     * Post creation functionality
     */
    if (postInputTrigger) {
        // Open post creation modal when clicking the input
        postInputTrigger.addEventListener('click', function() {
            openModal('create-post-modal');
            postContent.focus();
        });

        // Handle post content validation
        postContent.addEventListener('input', function() {
            postSubmitBtn.disabled = this.value.trim() === '';
        });

        // Image upload preview
        if (imageInput) {
            // Add event listener to the specific button that triggers the file input
            if (postImageUploadButton) {
                postImageUploadButton.addEventListener('click', function() {
                    imageInput.click(); // Programmatically click the hidden file input
                });
            }

            imageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];

                    // Validate file type
                    if (!file.type.match('image.*')) {
                        showNotification('Please select an image file (JPEG, PNG, GIF).', 'error');
                        this.value = '';
                        return;
                    }

                    // Validate file size (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        showNotification('Image size must be less than 5MB.', 'error');
                        this.value = '';
                        return;
                    }

                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreviewImg.src = e.target.result;
                        imagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Remove image
            if (removeImageBtn) {
                removeImageBtn.addEventListener('click', function() {
                    imageInput.value = '';
                    imagePreview.style.display = 'none';
                });
            }
        }

        // Privacy options
        if (postPrivacyBtn && privacyDropdown) {
            postPrivacyBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                privacyDropdown.classList.toggle('show');
            });

            // Close privacy dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!postPrivacyBtn.contains(e.target) && !privacyDropdown.contains(e.target)) {
                    privacyDropdown.classList.remove('show');
                }
            });

            // Handle privacy option selection
            privacyOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const privacy = this.getAttribute('data-privacy');
                    const privacyIcon = this.querySelector('i').className;
                    const privacyLabel = this.querySelector('span').textContent;

                    // Update selected option
                    privacyOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');

                    // Update button text and hidden input
                    postPrivacyBtn.innerHTML = `<i class="fas ${privacyIcon.split('fa-')[1]}"></i> <span>${privacyLabel}</span> <i class="fas fa-caret-down"></i>`;
                    postPrivacyValue.value = privacy;
                    privacyText.textContent = privacyLabel;

                    // Close dropdown
                    privacyDropdown.classList.remove('show');
                });
            });
        }

        // Location picker
        if (locationBtn && locationModal) {
            let map, autocomplete, marker, selectedPlace;

            // Initialize map when location modal opens
            locationBtn.addEventListener('click', function() {
                openModal('location-modal');

                // Initialize Google Maps if not already done
                if (!map) {
                    // Create map centered on a default location (can be user's location if available)
                    const mapOptions = {
                        center: { lat: 40.7128, lng: -74.0060 }, // New York as default
                        zoom: 12,
                        mapTypeControl: false,
                        streetViewControl: false
                    };

                    setTimeout(() => {
                        const mapElement = document.getElementById('location-map');
                        if (mapElement && window.google && window.google.maps) {
                            map = new google.maps.Map(mapElement, mapOptions);

                            // Add autocomplete to search input
                            autocomplete = new google.maps.places.Autocomplete(locationSearchInput);
                            autocomplete.bindTo('bounds', map);

                            // Listen for place selection
                            autocomplete.addListener('place_changed', function() {
                                const place = autocomplete.getPlace();

                                if (!place.geometry) {
                                    showNotification('No location details available for this place.', 'error');
                                    return;
                                }

                                // Update map
                                if (place.geometry.viewport) {
                                    map.fitBounds(place.geometry.viewport);
                                } else {
                                    map.setCenter(place.geometry.location);
                                    map.setZoom(17);
                                }

                                // Add marker
                                if (marker) {
                                    marker.setMap(null);
                                }

                                marker = new google.maps.Marker({
                                    map: map,
                                    position: place.geometry.location,
                                    animation: google.maps.Animation.DROP
                                });

                                // Store selected place
                                selectedPlace = {
                                    name: place.name || place.formatted_address,
                                    lat: place.geometry.location.lat(),
                                    lng: place.geometry.location.lng()
                                };
                            });

                            // Allow clicking on map to set location
                            map.addListener('click', function(e) {
                                if (marker) {
                                    marker.setMap(null);
                                }

                                marker = new google.maps.Marker({
                                    position: e.latLng,
                                    map: map,
                                    animation: google.maps.Animation.DROP
                                });

                                // Get address from coordinates using reverse geocoding
                                const geocoder = new google.maps.Geocoder();
                                geocoder.geocode({ location: e.latLng }, function(results, status) {
                                    if (status === 'OK' && results[0]) {
                                        selectedPlace = {
                                            name: results[0].formatted_address,
                                            lat: e.latLng.lat(),
                                            lng: e.latLng.lng()
                                        };
                                    } else {
                                        selectedPlace = {
                                            name: `Location (${e.latLng.lat().toFixed(5)}, ${e.latLng.lng().toFixed(5)})`,
                                            lat: e.latLng.lat(),
                                            lng: e.latLng.lng()
                                        };
                                    }
                                });
                            });
                        } else {
                            showNotification('Could not load Google Maps. Please try again later.', 'error');
                        }
                    }, 500);
                }
            });

            // Confirm location selection
            locationConfirmBtn.addEventListener('click', function() {
                if (selectedPlace) {
                    // Set location values in form
                    document.getElementById('location-lat').value = selectedPlace.lat;
                    document.getElementById('location-lng').value = selectedPlace.lng;
                    document.getElementById('location-name').value = selectedPlace.name;

                    // Show location display
                    locationText.textContent = selectedPlace.name;
                    locationContainer.style.display = 'flex';

                    closeModal(locationModal);
                } else {
                    showNotification('Please select a location first.', 'warning');
                }
            });

            // Cancel location selection
            locationCancelBtn.addEventListener('click', function() {
                closeModal(locationModal);
            });

            // Remove location
            removeLocationBtn.addEventListener('click', function() {
                document.getElementById('location-lat').value = '';
                document.getElementById('location-lng').value = '';
                document.getElementById('location-name').value = '';
                locationContainer.style.display = 'none';
            });
        }

        // Submit post form
        if (postSubmitBtn && postForm) {
            postSubmitBtn.addEventListener('click', function() {
                // Validate form
                if (postContent.value.trim() === '') {
                    showNotification('Please enter some content for your post.', 'warning');
                    return;
                }

                // Submit form via AJAX
                submitFormAjax(postForm)
                    .then(data => {
                        if (data.success) {
                            showNotification('Post created successfully!', 'success');

                            // Reset form
                            postForm.reset();
                            imagePreview.style.display = 'none';
                            locationContainer.style.display = 'none';
                            postSubmitBtn.disabled = true;

                            // Close modal
                            closeModal(createPostModal);

                            // Reload page to show new post
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            showNotification(data.message || 'Failed to create post. Please try again.', 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('An error occurred. Please try again.', 'error');
                    });
            });
        }
    }

    /**
     * Post menu functionality
     */
    postMenus.forEach(menu => {
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
            const postId = this.getAttribute('data-post-id');
            const dropdown = document.getElementById(`post-dropdown-${postId}`);

            // Close all other dropdowns
            document.querySelectorAll('.post-dropdown').forEach(drop => {
                if (drop !== dropdown) {
                    drop.classList.remove('show');
                }
            });

            // Toggle this dropdown
            dropdown.classList.toggle('show');
        });
    });

    // Close post dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.post-dropdown').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    });

    /**
     * Report post functionality
     */
    reportButtons.forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            reportPostIdInput.value = postId;

            openModal('report-modal');
        });
    });

    if (reportSubmitBtn && reportForm) {
        reportSubmitBtn.addEventListener('click', function() {
            const reason = document.getElementById('report-reason').value;

            if (!reason) {
                showNotification('Please select a reason for your report.', 'warning');
                return;
            }

            // Submit report via AJAX
            const formData = new FormData(reportForm);

            fetchWithCSRF('ajax/report_post.php', {
                method: 'POST',
                body: formData
            })
                .then(data => {
                    if (data.success) {
                        showNotification('Thank you for your report. Our team will review it shortly.', 'success');

                        // Reset form and close modal
                        reportForm.reset();
                        closeModal(reportModal);
                    } else {
                        showNotification(data.message || 'Failed to submit report. Please try again.', 'error');
                    }
                })
                .catch(error => {
                    showNotification('An error occurred. Please try again.', 'error');
                });
        });
    }

    if (reportCancelBtn) {
        reportCancelBtn.addEventListener('click', function() {
            closeModal(reportModal);
        });
    }

    /**
     * Comment functionality
     */
    // Show/hide comments
    commentButtons.forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            const commentsSection = document.getElementById(`comments-${postId}`);
            const commentsList = document.getElementById(`comments-list-${postId}`);

            if (commentsSection.style.display === 'none') {
                commentsSection.style.display = 'block';

                // Load comments via AJAX if not already loaded
                if (commentsList.querySelector('.comments-loading')) {
                    fetchWithCSRF(`ajax/get_comments.php?post_id=${postId}`)
                        .then(data => {
                            if (data.success) {
                                if (data.comments.length > 0) {
                                    let commentsHtml = '';
                                    data.comments.forEach(comment => {
                                        commentsHtml += `
                                            <div class="comment">
                                                <img src="${comment.user_avatar}" alt="${comment.username}" class="user-avatar-small">
                                                <div class="comment-content">
                                                    <a href="profile.php?id=${comment.user_id}" class="comment-author">${comment.username}</a>
                                                    <p class="comment-text">${comment.content}</p>
                                                    <div class="comment-actions">
                                                        <span class="comment-time">${comment.time_ago}</span>
                                                        <span class="comment-action comment-like" data-comment-id="${comment.id}">Like</span>
                                                        <span class="comment-action comment-reply" data-comment-id="${comment.id}">Reply</span>
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                    });
                                    commentsList.innerHTML = commentsHtml;
                                } else {
                                    commentsList.innerHTML = '<div class="no-comments">No comments yet. Be the first to comment!</div>';
                                }
                            } else {
                                commentsList.innerHTML = '<div class="comments-error">Failed to load comments. Please try again.</div>';
                            }
                        })
                        .catch(error => {
                            commentsList.innerHTML = '<div class="comments-error">An error occurred while loading comments.</div>';
                        });
                }
            } else {
                commentsSection.style.display = 'none';
            }
        });
    });

    // Submit comment
    commentInputs.forEach(input => {
        input.addEventListener('input', function() {
            const submitButton = this.nextElementSibling;
            submitButton.disabled = this.value.trim() === '';
        });

        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey && this.value.trim() !== '') {
                e.preventDefault();
                const postId = this.getAttribute('data-post-id');
                const content = this.value;

                // Submit comment via AJAX
                fetchWithCSRF('ajax/add_comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `post_id=${postId}&content=${encodeURIComponent(content)}&csrf_token=${encodeURIComponent(document.querySelector('input[name="csrf_token"]').value)}`
                })
                    .then(data => {
                        if (data.success) {
                            // Add comment to list
                            const commentsList = document.getElementById(`comments-list-${postId}`);
                            const noComments = commentsList.querySelector('.no-comments');

                            if (noComments) {
                                commentsList.innerHTML = '';
                            }

                            const commentHtml = `
                            <div class="comment">
                                <img src="${data.comment.user_avatar}" alt="${data.comment.username}" class="user-avatar-small">
                                <div class="comment-content">
                                    <a href="profile.php?id=${data.comment.user_id}" class="comment-author">${data.comment.username}</a>
                                    <p class="comment-text">${data.comment.content}</p>
                                    <div class="comment-actions">
                                        <span class="comment-time">Just now</span>
                                        <span class="comment-action comment-like" data-comment-id="${data.comment.id}">Like</span>
                                        <span class="comment-action comment-reply" data-comment-id="${data.comment.id}">Reply</span>
                                    </div>
                                </div>
                            </div>
                        `;

                            commentsList.insertAdjacentHTML('afterbegin', commentHtml);

                            // Update comment count
                            const commentCountElement = document.querySelector(`.comment-button[data-post-id="${postId}"] .stat-count`);
                            const currentCount = parseInt(commentCountElement.textContent);
                            commentCountElement.textContent = currentCount + 1;

                            // Clear input
                            this.value = '';
                            this.nextElementSibling.disabled = true;
                        } else {
                            showNotification(data.message || 'Failed to add comment. Please try again.', 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('An error occurred. Please try again.', 'error');
                    });
            }
        });
    });

    /**
     * Like post functionality
     */
    likeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            const isLiked = this.classList.contains('active');
            const likeCountElement = this.querySelector('.stat-count');
            const currentCount = parseInt(likeCountElement.textContent);

            // Optimistic UI update
            if (isLiked) {
                this.classList.remove('active');
                likeCountElement.textContent = Math.max(0, currentCount - 1);
            } else {
                this.classList.add('active');
                likeCountElement.textContent = currentCount + 1;
            }

            // Send like/unlike request
            fetchWithCSRF('ajax/like_post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `post_id=${postId}&action=${isLiked ? 'unlike' : 'like'}&csrf_token=${encodeURIComponent(document.querySelector('input[name="csrf_token"]').value)}`
            })
                .then(data => {
                    if (!data.success) {
                        // Revert UI change if request failed
                        if (isLiked) {
                            this.classList.add('active');
                            likeCountElement.textContent = currentCount;
                        } else {
                            this.classList.remove('active');
                            likeCountElement.textContent = Math.max(0, currentCount);
                        }

                        showNotification(data.message || 'Failed to update like status. Please try again.', 'error');
                    }
                })
                .catch(error => {
                    // Revert UI change on error
                    if (isLiked) {
                        this.classList.add('active');
                        likeCountElement.textContent = currentCount;
                    } else {
                        this.classList.remove('active');
                        likeCountElement.textContent = Math.max(0, currentCount);
                    }

                    showNotification('An error occurred. Please try again.', 'error');
                });
        });
    });
});