/**
 * ConnectHub - Posts JavaScript
 * Handles post creation, interaction, and comments
 */

document.addEventListener('DOMContentLoaded', function() {
    // Post creation elements
    const postInputTrigger = document.getElementById('post-input-trigger');
    const createPostModal = document.getElementById('create-post-modal');
    const postModalTitle = createPostModal ? createPostModal.querySelector('.modal-title') : null;
    const postForm = document.getElementById('post-form');
    const postContentInput = document.getElementById('post-content'); // Renamed for clarity
    const postSubmitBtn = document.getElementById('post-submit'); // The one in the modal
    const mainPostSubmitBtn = document.getElementById('post-submit-btn'); // The one on the main page, under input trigger
    const imageInput = document.getElementById('post-image-input');
    const imagePreviewContainer = document.getElementById('post-image-preview'); // The container div
    const imagePreviewImg = document.getElementById('image-preview'); // The img tag
    const removeImageBtn = document.getElementById('remove-image');
    const postPrivacyBtn = document.getElementById('post-privacy-btn');
    const privacyDropdown = document.getElementById('privacy-dropdown');
    const privacyOptions = document.querySelectorAll('.privacy-option');
    const postPrivacyValueInput = document.getElementById('post-privacy-value'); // Renamed for clarity
    const privacyTextDisplay = document.getElementById('privacy-text'); // Renamed for clarity
    const postModalCloseBtn = document.getElementById('post-modal-close');

    // Location elements (assuming IDs from index.php)
    const locationBtn = document.getElementById('location-btn'); // The button in the modal's "Add to your post"
    const mainLocationBtn = document.getElementById('post-location-btn'); // The button on the main page
    const locationModal = document.getElementById('location-modal');
    const locationSearchInput = document.getElementById('location-search-input');
    // const locationSearchResults = document.getElementById('location-search-results'); // Not directly used here but exists
    const locationConfirmBtn = document.getElementById('location-confirm');
    const locationCancelBtn = document.getElementById('location-cancel'); // In location modal
    const postLocationContainer = document.getElementById('post-location-container'); // In create post modal
    const locationTextDisplay = document.getElementById('location-text'); // In create post modal
    const removeLocationBtn = document.getElementById('remove-location'); // In create post modal
    const locationLatInput = document.getElementById('location-lat');
    const locationLngInput = document.getElementById('location-lng');
    const locationNameInput = document.getElementById('location-name');


    // Post menu & actions
    const reportModal = document.getElementById('report-modal');
    const reportPostIdInput = document.getElementById('report-post-id');
    const reportForm = document.getElementById('report-form');
    const reportSubmitBtn = document.getElementById('report-submit');
    const reportCancelBtn = document.getElementById('report-cancel');

    // Get the image upload button inside the modal
    const postImageUploadButton = document.getElementById('post-image-upload-button');

    let currentEditingPostId = null; // To store post ID when editing

    function resetPostModal() {
        if (postForm) postForm.reset();
        if (imagePreviewContainer) imagePreviewContainer.style.display = 'none';
        if (imagePreviewImg) imagePreviewImg.src = '#';
        if (postLocationContainer) postLocationContainer.style.display = 'none';
        if (locationTextDisplay) locationTextDisplay.textContent = 'Add your location';
        if (postContentInput) postContentInput.value = '';
        if (postSubmitBtn) postSubmitBtn.disabled = true;
        if (mainPostSubmitBtn) mainPostSubmitBtn.disabled = true;

        // Reset privacy to public default
        if (postPrivacyValueInput) postPrivacyValueInput.value = 'public';
        if (privacyTextDisplay) privacyTextDisplay.textContent = 'Public';
        const publicIcon = privacyDropdown ? privacyDropdown.querySelector('.privacy-option[data-privacy="public"] i') : null;
        const publicText = privacyDropdown ? privacyDropdown.querySelector('.privacy-option[data-privacy="public"] span').textContent : "Public";
        if (postPrivacyBtn && publicIcon) {
            postPrivacyBtn.innerHTML = `<i class="fas ${publicIcon.className.split('fa-')[1]}"></i> <span>${publicText}</span> <i class="fas fa-caret-down"></i>`;
        }
        if (privacyOptions) {
            privacyOptions.forEach(opt => opt.classList.remove('selected'));
            const publicOption = privacyDropdown ? privacyDropdown.querySelector('.privacy-option[data-privacy="public"]') : null;
            if (publicOption) publicOption.classList.add('selected');
        }


        currentEditingPostId = null;
        if (postModalTitle) postModalTitle.textContent = 'Create Post';
        if (postSubmitBtn) postSubmitBtn.textContent = 'Post';
    }

    if (postModalCloseBtn && createPostModal) {
        postModalCloseBtn.addEventListener('click', () => {
            closeModal(createPostModal);
            resetPostModal();
        });
    }


    /**
     * Post creation functionality
     */
    if (postInputTrigger && createPostModal) {
        postInputTrigger.addEventListener('click', function() {
            resetPostModal(); // Ensure modal is reset for new post
            openModal('create-post-modal');
            if (postContentInput) postContentInput.focus();
        });

        if (postContentInput) {
            postContentInput.addEventListener('input', function() {
                if (postSubmitBtn) postSubmitBtn.disabled = this.value.trim() === '';
                if (mainPostSubmitBtn) mainPostSubmitBtn.disabled = this.value.trim() === '';
            });
        }

        // Also handle the main page's post input trigger related buttons.
        if (mainPostSubmitBtn && postInputTrigger) { // This is the button under the "What's on your mind" input
            mainPostSubmitBtn.addEventListener('click', function() {
                openModal('create-post-modal');
                if (postContentInput) {
                    postContentInput.value = document.getElementById('post-input-trigger').value; // Get value from trigger if any
                    postContentInput.focus();
                    if (postSubmitBtn) postSubmitBtn.disabled = postContentInput.value.trim() === '';
                }
            });
        }


        if (imageInput && postImageUploadButton) {
            postImageUploadButton.addEventListener('click', function() {
                imageInput.click();
            });

            imageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    if (!file.type.match('image.*')) {
                        showNotification('Please select an image file (JPEG, PNG, GIF).', 'error');
                        this.value = '';
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        showNotification('Image size must be less than 5MB.', 'error');
                        this.value = '';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (imagePreviewImg) imagePreviewImg.src = e.target.result;
                        if (imagePreviewContainer) imagePreviewContainer.style.display = 'block';
                        if (removeImageBtn) removeImageBtn.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });

            if (removeImageBtn) {
                removeImageBtn.addEventListener('click', function() {
                    imageInput.value = '';
                    if (imagePreviewContainer) imagePreviewContainer.style.display = 'none';
                    if (imagePreviewImg) imagePreviewImg.src = '#';
                    this.style.display = 'none';
                });
            }
        }

        if (postPrivacyBtn && privacyDropdown) {
            postPrivacyBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                privacyDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!postPrivacyBtn.contains(e.target) && !privacyDropdown.contains(e.target)) {
                    privacyDropdown.classList.remove('show');
                }
            });

            privacyOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const privacy = this.getAttribute('data-privacy');
                    const privacyIconEl = this.querySelector('i');
                    const privacyLabelEl = this.querySelector('span');

                    if (!privacyIconEl || !privacyLabelEl) return;

                    const privacyIcon = privacyIconEl.className;
                    const privacyLabel = privacyLabelEl.textContent;

                    privacyOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');

                    postPrivacyBtn.innerHTML = `<i class="fas ${privacyIcon.split('fa-')[1]}"></i> <span>${privacyLabel}</span> <i class="fas fa-caret-down"></i>`;
                    if (postPrivacyValueInput) postPrivacyValueInput.value = privacy;
                    if (privacyTextDisplay) privacyTextDisplay.textContent = privacyLabel; // For modal header display

                    privacyDropdown.classList.remove('show');
                });
            });
        }

        // Location Picker in Modal
        if (locationBtn && locationModal && typeof google !== 'undefined' && google.maps) {
            let map, autocompleteService, placesService, modalMarker;

            function initModalMap() {
                if (map) return; // Initialize only once or if modal is reopened
                const mapElement = document.getElementById('location-map');
                if (!mapElement) return;

                map = new google.maps.Map(mapElement, {
                    center: { lat: 40.7128, lng: -74.0060 }, // Default: New York
                    zoom: 12,
                    mapTypeControl: false,
                    streetViewControl: false
                });
                autocompleteService = new google.maps.places.AutocompleteService();
                placesService = new google.maps.places.PlacesService(map);

                // Handle search input for modal map
                if (locationSearchInput && locationSearchResults) { // locationSearchResults ID might be different or not used for direct rendering
                    locationSearchInput.addEventListener('input', function() {
                        if (this.value.length > 0) {
                            autocompleteService.getPlacePredictions({ input: this.value }, displaySuggestions);
                        } else {
                            locationSearchResults.innerHTML = '';
                            locationSearchResults.style.display = 'none';
                        }
                    });
                }

                map.addListener('click', function(e) {
                    placeMarkerAndPanTo(e.latLng, map);
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ location: e.latLng }, function(results, status) {
                        if (status === 'OK' && results[0]) {
                            locationNameInput.value = results[0].formatted_address;
                            locationLatInput.value = e.latLng.lat();
                            locationLngInput.value = e.latLng.lng();
                            if(locationSearchInput) locationSearchInput.value = results[0].formatted_address;
                        }
                    });
                });
            }

            function displaySuggestions(predictions, status) {
                if (status !== google.maps.places.PlacesServiceStatus.OK || !predictions) {
                    locationSearchResults.innerHTML = '';
                    locationSearchResults.style.display = 'none';
                    return;
                }
                let resultsHtml = predictions.map(p => `<div class="search-result-item location-suggestion" data-place-id="${p.place_id}">${p.description}</div>`).join('');
                locationSearchResults.innerHTML = resultsHtml;
                locationSearchResults.style.display = 'block';

                document.querySelectorAll('.location-suggestion').forEach(item => {
                    item.addEventListener('click', function() {
                        const placeId = this.getAttribute('data-place-id');
                        placesService.getDetails({ placeId: placeId }, (place, status) => {
                            if (status === google.maps.places.PlacesServiceStatus.OK && place.geometry) {
                                placeMarkerAndPanTo(place.geometry.location, map);
                                locationNameInput.value = place.formatted_address || place.name;
                                locationLatInput.value = place.geometry.location.lat();
                                locationLngInput.value = place.geometry.location.lng();
                                if(locationSearchInput) locationSearchInput.value = place.formatted_address || place.name;
                                locationSearchResults.style.display = 'none';
                            }
                        });
                    });
                });
            }

            function placeMarkerAndPanTo(latLng, mapInstance) {
                if (modalMarker) {
                    modalMarker.setMap(null);
                }
                modalMarker = new google.maps.Marker({
                    position: latLng,
                    map: mapInstance,
                    animation: google.maps.Animation.DROP
                });
                mapInstance.panTo(latLng);
            }


            locationBtn.addEventListener('click', function() { // This is the "Location" button in the create post modal
                openModal('location-modal');
                setTimeout(initModalMap, 100); // Ensure map div is visible
            });

            // Also handle the main page's location button if it exists
            if(mainLocationBtn) {
                mainLocationBtn.addEventListener('click', function() {
                    openModal('location-modal');
                    setTimeout(initModalMap, 100);
                });
            }


            if (locationConfirmBtn) {
                locationConfirmBtn.addEventListener('click', function() {
                    if (locationLatInput.value && locationLngInput.value && locationNameInput.value) {
                        if (locationTextDisplay) locationTextDisplay.textContent = locationNameInput.value;
                        if (postLocationContainer) postLocationContainer.style.display = 'flex';
                        closeModal(locationModal);
                    } else {
                        showNotification('Please select a location from the map or search.', 'warning');
                    }
                });
            }

            if (locationCancelBtn) {
                locationCancelBtn.addEventListener('click', function() {
                    closeModal(locationModal);
                });
            }

            if (removeLocationBtn) {
                removeLocationBtn.addEventListener('click', function() {
                    locationLatInput.value = '';
                    locationLngInput.value = '';
                    locationNameInput.value = '';
                    if (locationTextDisplay) locationTextDisplay.textContent = 'Add your location';
                    if (postLocationContainer) postLocationContainer.style.display = 'none';
                    if (modalMarker) modalMarker.setMap(null);
                    if(locationSearchInput) locationSearchInput.value = '';

                });
            }
        } else {
            // Hide location features if Google Maps API is not available
            if(locationBtn) locationBtn.style.display = 'none';
            if(mainLocationBtn) mainLocationBtn.style.display = 'none';
        }


        if (postSubmitBtn && postForm) {
            postSubmitBtn.addEventListener('click', function() {
                if (postContentInput.value.trim() === '' && !imageInput.files.length) { // Allow image-only posts
                    showNotification('Please enter some content or upload an image for your post.', 'warning');
                    return;
                }

                const formData = new FormData(postForm);
                let url = 'ajax/create_post.php';
                if (currentEditingPostId) {
                    formData.append('post_id', currentEditingPostId);
                    url = 'ajax/edit_post.php';
                }

                // Ensure CSRF token is appended if not already picked up by FormData from a hidden input in the form.
                // If your form doesn't have a CSRF token input, uncomment and adjust:
                // const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
                // if (csrfToken && !formData.has('csrf_token')) {
                //     formData.append('csrf_token', csrfToken);
                // }


                fetchWithCSRF(url, {
                    method: 'POST',
                    body: formData
                })
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            resetPostModal();
                            closeModal(createPostModal);

                            if (currentEditingPostId) {
                                // Update post in UI
                                const postCard = document.querySelector(`.post-card[data-post-id="${currentEditingPostId}"]`); // Assuming post cards will have data-post-id
                                if (postCard) {
                                    const postContentElement = postCard.querySelector('.post-content');
                                    if (postContentElement) postContentElement.innerHTML = data.new_content; // nl2br handled by PHP
                                    // Optionally update privacy icon
                                    const privacyIconElement = postCard.querySelector('.post-privacy i');
                                    if (privacyIconElement) {
                                        let newIconClass = 'fa-globe-americas'; // public
                                        if (data.new_privacy === 'friends') newIconClass = 'fa-user-friends';
                                        else if (data.new_privacy === 'private') newIconClass = 'fa-lock';
                                        privacyIconElement.className = `fas ${newIconClass}`;
                                    }
                                }
                            } else {
                                // For new posts, a page reload or dynamic prepending would be needed.
                                // For simplicity with current structure, we can reload.
                                setTimeout(() => { window.location.reload(); }, 1500);
                            }
                            currentEditingPostId = null;
                        } else {
                            showNotification(data.message || 'An error occurred.', 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('An error occurred. Please try again.', 'error');
                        console.error("Error submitting post:", error);
                    });
            });
        }
    }

    /**
     * Post menu functionality & Edit/Delete
     */
    document.querySelectorAll('.post-menu').forEach(menu => {
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
            const postId = this.getAttribute('data-post-id');
            const dropdown = document.getElementById(`post-dropdown-${postId}`);
            if (dropdown) {
                document.querySelectorAll('.post-dropdown.show').forEach(d => {
                    if (d !== dropdown) d.classList.remove('show');
                });
                dropdown.classList.toggle('show');
            }
        });
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('.post-dropdown.show').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    });

    // Edit Post
    document.querySelectorAll('.post-edit').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            const postCard = this.closest('.post-card');
            if (!postCard || !createPostModal || !postContentInput || !postModalTitle || !postSubmitBtn || !postPrivacyValueInput) return;

            const currentContent = postCard.querySelector('.post-content').textContent.trim(); // Get raw text
            const currentPrivacyIcon = postCard.querySelector('.post-privacy i');
            let currentPrivacy = 'public';
            if (currentPrivacyIcon.classList.contains('fa-user-friends')) currentPrivacy = 'friends';
            else if (currentPrivacyIcon.classList.contains('fa-lock')) currentPrivacy = 'private';

            // TODO: Fetch full post details if image/location also needs to be editable.
            // For now, only content and privacy.

            currentEditingPostId = postId;
            postModalTitle.textContent = 'Edit Post';
            postContentInput.value = currentContent;
            postPrivacyValueInput.value = currentPrivacy;

            // Update privacy button display
            privacyOptions.forEach(opt => {
                opt.classList.remove('selected');
                if (opt.getAttribute('data-privacy') === currentPrivacy) {
                    opt.classList.add('selected');
                    const icon = opt.querySelector('i').className;
                    const text = opt.querySelector('span').textContent;
                    if(postPrivacyBtn) postPrivacyBtn.innerHTML = `<i class="fas ${icon.split('fa-')[1]}"></i> <span>${text}</span> <i class="fas fa-caret-down"></i>`;
                    if(privacyTextDisplay) privacyTextDisplay.textContent = text;
                }
            });

            // Clear previous image/location from modal if not handling their edit yet.
            if(imagePreviewContainer) imagePreviewContainer.style.display = 'none';
            if(imageInput) imageInput.value = ''; // Clear file input for edit
            if(postLocationContainer) postLocationContainer.style.display = 'none';
            // Reset location hidden fields
            if(locationLatInput) locationLatInput.value = '';
            if(locationLngInput) locationLngInput.value = '';
            if(locationNameInput) locationNameInput.value = '';


            postSubmitBtn.textContent = 'Save Changes';
            postSubmitBtn.disabled = false;
            openModal('create-post-modal');
        });
    });

    // Delete Post
    document.querySelectorAll('.post-delete').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('post_id', postId);
                // const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
                // if (csrfToken) formData.append('csrf_token', csrfToken);


                fetchWithCSRF('ajax/delete_post.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            const postCard = this.closest('.post-card');
                            if (postCard) postCard.remove();
                        } else {
                            showNotification(data.message || 'Failed to delete post.', 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('An error occurred. Please try again.', 'error');
                        console.error("Error deleting post:", error);
                    });
            }
        });
    });


    /**
     * Report post functionality
     */
    document.querySelectorAll('.post-report').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            if (reportPostIdInput) reportPostIdInput.value = postId;
            if (reportModal) openModal('report-modal');
        });
    });

    if (reportSubmitBtn && reportForm) {
        reportSubmitBtn.addEventListener('click', function() {
            const reason = document.getElementById('report-reason').value;
            if (!reason) {
                showNotification('Please select a reason for your report.', 'warning');
                return;
            }
            const formData = new FormData(reportForm);
            fetchWithCSRF('ajax/report_post.php', {
                method: 'POST',
                body: formData
            })
                .then(data => {
                    if (data.success) {
                        showNotification('Thank you for your report. Our team will review it shortly.', 'success');
                        if (reportForm) reportForm.reset();
                        if (reportModal) closeModal(reportModal);
                    } else {
                        showNotification(data.message || 'Failed to submit report.', 'error');
                    }
                })
                .catch(error => {
                    showNotification('An error occurred. Please try again.', 'error');
                    console.error("Error reporting post:", error);
                });
        });
    }

    if (reportCancelBtn && reportModal) {
        reportCancelBtn.addEventListener('click', function() {
            closeModal(reportModal);
        });
        // Also add listener to modal's own close button if it has one
        const reportModalDirectClose = reportModal.querySelector('.modal-close');
        if (reportModalDirectClose) {
            reportModalDirectClose.addEventListener('click', () => closeModal(reportModal));
        }
    }


    /**
     * Comment functionality
     */
    document.querySelectorAll('.comment-button').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            const commentsSection = document.getElementById(`comments-${postId}`);
            const commentsList = document.getElementById(`comments-list-${postId}`);

            if (!commentsSection || !commentsList) return;

            if (commentsSection.style.display === 'none' || commentsSection.style.display === '') {
                commentsSection.style.display = 'block';
                if (commentsList.querySelector('.comments-loading')) {
                    fetchWithCSRF(`ajax/get_comments.php?post_id=${postId}`)
                        .then(data => {
                            if (data.success) {
                                let commentsHtml = '';
                                if (data.comments.length > 0) {
                                    data.comments.forEach(comment => {
                                        commentsHtml += `
                                            <div class="comment" data-comment-id="${comment.id}">
                                                <img src="${comment.user_avatar || 'assets/default-avatar.png'}" alt="${comment.username}" class="user-avatar-small">
                                                <div class="comment-content">
                                                    <a href="profile.php?id=${comment.user_id}" class="comment-author">${comment.username}</a>
                                                    <p class="comment-text">${comment.content.replace(/\n/g, '<br>')}</p>
                                                    <div class="comment-actions">
                                                        <span class="comment-time">${comment.time_ago}</span>
                                                        </div>
                                                </div>
                                            </div>
                                        `;
                                    });
                                } else {
                                    commentsHtml = '<div class="no-comments">No comments yet. Be the first to comment!</div>';
                                }
                                commentsList.innerHTML = commentsHtml;
                            } else {
                                commentsList.innerHTML = '<div class="comments-error">Failed to load comments.</div>';
                            }
                        })
                        .catch(error => {
                            commentsList.innerHTML = '<div class="comments-error">Error loading comments.</div>';
                            console.error("Error fetching comments:", error);
                        });
                }
            } else {
                commentsSection.style.display = 'none';
            }
        });
    });

    document.querySelectorAll('.comment-input').forEach(input => {
        const submitButton = input.nextElementSibling;
        input.addEventListener('input', function() {
            if (submitButton) submitButton.disabled = this.value.trim() === '';
        });

        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey && this.value.trim() !== '') {
                e.preventDefault();
                const postId = this.getAttribute('data-post-id');
                const content = this.value;
                const csrfTokenVal = document.querySelector('input[name="csrf_token"]')?.value;


                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('content', content);
                formData.append('csrf_token', csrfTokenVal);


                fetchWithCSRF('ajax/add_comment.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(data => {
                        if (data.success && data.comment) {
                            const commentsList = document.getElementById(`comments-list-${postId}`);
                            const noCommentsEl = commentsList ? commentsList.querySelector('.no-comments') : null;
                            if (noCommentsEl) noCommentsEl.remove();

                            const commentHtml = `
                            <div class="comment" data-comment-id="${data.comment.id}">
                                <img src="${data.comment.user_avatar || 'assets/default-avatar.png'}" alt="${data.comment.username}" class="user-avatar-small">
                                <div class="comment-content">
                                    <a href="profile.php?id=${data.comment.user_id}" class="comment-author">${data.comment.username}</a>
                                    <p class="comment-text">${data.comment.content.replace(/\n/g, '<br>')}</p>
                                    <div class="comment-actions">
                                        <span class="comment-time">${data.comment.time_ago}</span>
                                        </div>
                                </div>
                            </div>
                        `;
                            if (commentsList) commentsList.insertAdjacentHTML('afterbegin', commentHtml); // Add new comment to top

                            const commentCountElement = document.querySelector(`.comment-button[data-post-id="${postId}"] .stat-count`);
                            if (commentCountElement) {
                                const currentCount = parseInt(commentCountElement.textContent);
                                commentCountElement.textContent = currentCount + 1;
                            }

                            this.value = '';
                            if (submitButton) submitButton.disabled = true;
                        } else {
                            showNotification(data.message || 'Failed to add comment.', 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('An error occurred. Please try again.', 'error');
                        console.error("Error adding comment:", error);
                    });
            }
        });
    });

    // Handle click on comment submit buttons (if any exist that aren't just for Enter key)
    document.querySelectorAll('.comment-submit').forEach(button => {
        button.addEventListener('click', function() {
            const inputField = this.previousElementSibling; // Assuming input is right before button
            if (inputField && inputField.classList.contains('comment-input') && inputField.value.trim() !== '') {
                const postId = inputField.getAttribute('data-post-id');
                const content = inputField.value;
                const csrfTokenVal = document.querySelector('input[name="csrf_token"]')?.value;

                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('content', content);
                formData.append('csrf_token', csrfTokenVal);

                fetchWithCSRF('ajax/add_comment.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(data => {
                        // (Same success/error handling as in keypress event)
                        if (data.success && data.comment) {
                            const commentsList = document.getElementById(`comments-list-${postId}`);
                            const noCommentsEl = commentsList ? commentsList.querySelector('.no-comments') : null;
                            if (noCommentsEl) noCommentsEl.remove();

                            const commentHtml = `
                            <div class="comment" data-comment-id="${data.comment.id}">
                                <img src="${data.comment.user_avatar || 'assets/default-avatar.png'}" alt="${data.comment.username}" class="user-avatar-small">
                                <div class="comment-content">
                                    <a href="profile.php?id=${data.comment.user_id}" class="comment-author">${data.comment.username}</a>
                                    <p class="comment-text">${data.comment.content.replace(/\n/g, '<br>')}</p>
                                    <div class="comment-actions">
                                        <span class="comment-time">${data.comment.time_ago}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                            if (commentsList) commentsList.insertAdjacentHTML('afterbegin', commentHtml);

                            const commentCountElement = document.querySelector(`.comment-button[data-post-id="${postId}"] .stat-count`);
                            if (commentCountElement) {
                                const currentCount = parseInt(commentCountElement.textContent);
                                commentCountElement.textContent = currentCount + 1;
                            }

                            inputField.value = '';
                            this.disabled = true;
                        } else {
                            showNotification(data.message || 'Failed to add comment.', 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('An error occurred. Please try again.', 'error');
                        console.error("Error adding comment:", error);
                    });
            }
        });
    });


    /**
     * Like post functionality
     */
    document.querySelectorAll('.like-button').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            const isLiked = this.classList.contains('active');
            const likeCountElement = this.querySelector('.stat-count');
            if (!likeCountElement) return;
            let currentCount = parseInt(likeCountElement.textContent);
            const csrfTokenVal = document.querySelector('input[name="csrf_token"]')?.value;


            this.classList.toggle('active');
            if (isLiked) {
                likeCountElement.textContent = Math.max(0, currentCount - 1);
            } else {
                likeCountElement.textContent = currentCount + 1;
            }

            const formData = new FormData();
            formData.append('post_id', postId);
            formData.append('action', isLiked ? 'unlike' : 'like');
            formData.append('csrf_token', csrfTokenVal);


            fetchWithCSRF('ajax/like_post.php', {
                method: 'POST',
                body: formData
            })
                .then(data => {
                    if (!data.success) {
                        // Revert UI on failure
                        this.classList.toggle('active'); // Toggle back
                        likeCountElement.textContent = currentCount; // Restore original count
                        showNotification(data.message || 'Failed to update like.', 'error');
                    }
                    // On success, UI is already updated optimistically
                })
                .catch(error => {
                    this.classList.toggle('active');
                    likeCountElement.textContent = currentCount;
                    showNotification('An error occurred. Please try again.', 'error');
                    console.error("Error liking post:", error);
                });
        });
    });
});