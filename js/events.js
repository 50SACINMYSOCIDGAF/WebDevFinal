/**
 * ConnectHub - Events JavaScript
 * Handles event creation, display, and attendance actions.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Elements for Create Event Modal
    const createEventBtn = document.getElementById('create-event-btn');
    const createEventModal = document.getElementById('create-event-modal');
    const createEventModalClose = document.getElementById('create-event-modal-close');
    const createEventCancel = document.getElementById('create-event-cancel');
    const createEventForm = document.getElementById('create-event-form');
    const createEventSubmit = document.getElementById('create-event-submit');

    // Event Image Upload
    const eventImageInput = document.getElementById('event-image-input');
    const eventImageUploadBtn = document.getElementById('event-image-upload-btn');
    const eventImagePreviewContainer = document.querySelector('.image-preview-container');
    const eventImagePreviewImg = document.getElementById('event-image-preview-img');
    const eventImageEmptyPreview = document.getElementById('event-image-empty-preview');
    const removeEventImageBtn = document.getElementById('remove-event-image-btn');

    // Event Location Map
    const eventMapSearchInput = document.getElementById('event-map-search-input');
    const eventLocationMap = document.getElementById('event-location-map');
    const eventLocationLat = document.getElementById('event-location-lat');
    const eventLocationLng = document.getElementById('event-location-lng');
    const eventLocationName = document.getElementById('event-location-name');

    let map, autocomplete, marker, selectedPlace;

    // Event Join/Interested Buttons
    const joinEventButtons = document.querySelectorAll('.join-event-btn');
    const interestedEventButtons = document.querySelectorAll('.interested-event-btn');

    /**
     * Handles opening the Create Event Modal.
     */
    if (createEventBtn) {
        createEventBtn.addEventListener('click', function() {
            openModal('create-event-modal');
            // Initialize map when modal opens for the first time
            if (!map) {
                setTimeout(initMapForEventCreation, 500); // Delay to ensure modal is visible
            }
        });
    }

    /**
     * Handles closing the Create Event Modal.
     */
    if (createEventModalClose) {
        createEventModalClose.addEventListener('click', function() {
            closeModal('create-event-modal');
            resetCreateEventForm();
        });
    }
    if (createEventCancel) {
        createEventCancel.addEventListener('click', function() {
            closeModal('create-event-modal');
            resetCreateEventForm();
        });
    }

    /**
     * Resets the Create Event form and UI elements.
     */
    function resetCreateEventForm() {
        createEventForm.reset();
        eventImagePreviewImg.style.display = 'none';
        eventImagePreviewImg.src = '#';
        eventImageEmptyPreview.style.display = 'flex';
        removeEventImageBtn.style.display = 'none';
        createEventSubmit.disabled = false; // Re-enable submit button

        // Reset map marker if it exists
        if (marker) {
            marker.setMap(null);
            marker = null;
        }
        selectedPlace = null;
        eventMapSearchInput.value = '';
        eventLocationLat.value = '';
        eventLocationLng.value = '';
        eventLocationName.value = '';
    }

    /**
     * Initializes Google Map for event location selection.
     */
    function initMapForEventCreation() {
        if (!eventLocationMap || !window.google || !window.google.maps) {
            showNotification('Google Maps could not be loaded. Location selection might be limited.', 'error');
            return;
        }

        const mapOptions = {
            center: { lat: 0, lng: 0 }, // Default to world center
            zoom: 2,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false
        };

        map = new google.maps.Map(eventLocationMap, mapOptions);

        // Try to get user's current location
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const userLatLng = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                map.setCenter(userLatLng);
                map.setZoom(12);
            }, function() {
                // Geolocation failed, use default center
                map.setCenter({ lat: 40.7128, lng: -74.0060 }); // New York
                map.setZoom(12);
            });
        } else {
            map.setCenter({ lat: 40.7128, lng: -74.0060 }); // New York
            map.setZoom(12);
        }

        // Autocomplete for search input
        autocomplete = new google.maps.places.Autocomplete(eventMapSearchInput);
        autocomplete.bindTo('bounds', map);

        autocomplete.addListener('place_changed', function() {
            const place = autocomplete.getPlace();
            if (!place.geometry) {
                showNotification('No location details available for this place.', 'warning');
                return;
            }

            // If the place has a geometry, then present it on a map.
            if (place.geometry.viewport) {
                map.fitBounds(place.geometry.viewport);
            } else {
                map.setCenter(place.geometry.location);
                map.setZoom(17);
            }

            if (marker) {
                marker.setMap(null);
            }
            marker = new google.maps.Marker({
                map: map,
                position: place.geometry.location,
                animation: google.maps.Animation.DROP
            });

            selectedPlace = {
                name: place.name || place.formatted_address,
                lat: place.geometry.location.lat(),
                lng: place.geometry.location.lng()
            };
            eventLocationLat.value = selectedPlace.lat;
            eventLocationLng.value = selectedPlace.lng;
            eventLocationName.value = selectedPlace.name;
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
                eventLocationLat.value = selectedPlace.lat;
                eventLocationLng.value = selectedPlace.lng;
                eventLocationName.value = selectedPlace.name;
                eventMapSearchInput.value = selectedPlace.name; // Update search input with selected name
            });
        });
    }

    /**
     * Handles event image preview and removal.
     */
    if (eventImageInput && eventImageUploadBtn && removeEventImageBtn) {
        eventImageUploadBtn.addEventListener('click', function() {
            eventImageInput.click(); // Trigger file input
        });

        eventImageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                if (!file.type.match('image.*')) {
                    showNotification('Please select an image file (JPEG, PNG, GIF).', 'error');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    eventImagePreviewImg.src = e.target.result;
                    eventImagePreviewImg.style.display = 'block';
                    eventImageEmptyPreview.style.display = 'none';
                    removeEventImageBtn.style.display = 'inline-block';
                };
                reader.readAsDataURL(file);
            }
        });

        removeEventImageBtn.addEventListener('click', function() {
            eventImageInput.value = '';
            eventImagePreviewImg.style.display = 'none';
            eventImagePreviewImg.src = '#';
            eventImageEmptyPreview.style.display = 'flex';
            removeEventImageBtn.style.display = 'none';
        });
    }

    /**
     * Handles submission of the Create Event form.
     */
    if (createEventSubmit && createEventForm) {
        createEventSubmit.addEventListener('click', function() {
            const title = document.getElementById('event-title').value.trim();
            const eventDate = document.getElementById('event-date').value.trim();

            if (!title || !eventDate) {
                showNotification('Please fill in required fields (Title, Date).', 'warning');
                return;
            }

            createEventSubmit.disabled = true; // Disable button to prevent multiple submissions

            const formData = new FormData(createEventForm);

            // Ensure location data is consistent if map was used
            if (selectedPlace && eventLocationLat.value && eventLocationLng.value) {
                formData.set('location_name', selectedPlace.name);
                formData.set('location_lat', selectedPlace.lat);
                formData.set('location_lng', selectedPlace.lng);
            } else if (eventLocationName.value.trim() === '') {
                // If location name is empty, ensure lat/lng are also null
                formData.set('location_lat', '');
                formData.set('location_lng', '');
            }

            fetchWithCSRF('ajax/create_event.php', {
                method: 'POST',
                body: formData
            })
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        closeModal('create-event-modal');
                        resetCreateEventForm();
                        // Reload page or dynamically add new event to the list
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showNotification(data.message || 'Failed to create event.', 'error');
                        createEventSubmit.disabled = false; // Re-enable button on failure
                    }
                })
                .catch(error => {
                    showNotification('An error occurred. Please try again.', 'error');
                    console.error('Error creating event:', error);
                    createEventSubmit.disabled = false; // Re-enable button on error
                });
        });
    }

    /**
     * Handles joining/interested/leaving an event.
     */
    joinEventButtons.forEach(button => {
        button.addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            const action = this.getAttribute('data-action'); // 'going', 'interested', 'leave'
            const originalHtml = this.innerHTML;
            const originalClasses = this.className;

            // Optimistic UI update
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled = true;

            fetchWithCSRF('ajax/join_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `event_id=${eventId}&action=${action}&csrf_token=${encodeURIComponent(document.querySelector('input[name="csrf_token"]').value)}`
            })
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        // Update UI based on new status
                        // Reload page to reflect attendee counts and button states accurately
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showNotification(data.message || 'Failed to update event status.', 'error');
                        this.innerHTML = originalHtml; // Revert UI
                        this.disabled = false;
                        this.className = originalClasses;
                    }
                })
                .catch(error => {
                    showNotification('An error occurred. Please try again.', 'error');
                    console.error('Error joining/leaving event:', error);
                    this.innerHTML = originalHtml; // Revert UI
                    this.disabled = false;
                    this.className = originalClasses;
                });
        });
    });

    interestedEventButtons.forEach(button => {
        button.addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            const action = this.getAttribute('data-action'); // Should be 'interested'
            const originalHtml = this.innerHTML;
            const originalClasses = this.className;

            // Optimistic UI update
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled = true;

            fetchWithCSRF('ajax/join_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `event_id=${eventId}&action=${action}&csrf_token=${encodeURIComponent(document.querySelector('input[name="csrf_token"]').value)}`
            })
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showNotification(data.message || 'Failed to update event status.', 'error');
                        this.innerHTML = originalHtml; // Revert UI
                        this.disabled = false;
                        this.className = originalClasses;
                    }
                })
                .catch(error => {
                    showNotification('An error occurred. Please try again.', 'error');
                    console.error('Error setting interested status:', error);
                    this.innerHTML = originalHtml; // Revert UI
                    this.disabled = false;
                    this.className = originalClasses;
                });
        });
    });
});
