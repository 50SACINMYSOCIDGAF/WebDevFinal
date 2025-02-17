// Function to handle profile picture updates
function editProfilePicture() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    
    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('profile_picture', file);
        formData.append('action', 'update_profile_picture');

        try {
            const response = await fetch('update_profile.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    document.querySelector('.profile-picture').src = data.url;
                } else {
                    alert('Failed to update profile picture');
                }
            }
        } catch (error) {
            console.error('Error updating profile picture:', error);
            alert('Error updating profile picture');
        }
    };

    input.click();
}

// Function to handle bio updates
function editBio() {
    const bioSection = document.querySelector('#bio-section p');
    const currentBio = bioSection.textContent.trim();
    
    const textarea = document.createElement('textarea');
    textarea.value = currentBio;
    textarea.style.width = '100%';
    textarea.style.minHeight = '100px';
    textarea.style.marginBottom = '10px';
    
    const saveButton = document.createElement('button');
    saveButton.textContent = 'Save';
    saveButton.className = 'edit-button';
    
    const cancelButton = document.createElement('button');
    cancelButton.textContent = 'Cancel';
    cancelButton.className = 'edit-button';
    cancelButton.style.marginLeft = '10px';
    
    const buttonContainer = document.createElement('div');
    buttonContainer.appendChild(saveButton);
    buttonContainer.appendChild(cancelButton);
    
    bioSection.replaceWith(textarea);
    textarea.parentNode.appendChild(buttonContainer);
    
    saveButton.onclick = async () => {
        const newBio = textarea.value.trim();
        
        try {
            const response = await fetch('update_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_bio',
                    bio: newBio
                }),
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    bioSection.textContent = newBio;
                    textarea.replaceWith(bioSection);
                    buttonContainer.remove();
                } else {
                    alert('Failed to update bio');
                }
            }
        } catch (error) {
            console.error('Error updating bio:', error);
            alert('Error updating bio');
        }
    };
    
    cancelButton.onclick = () => {
        textarea.replaceWith(bioSection);
        buttonContainer.remove();
    };
}

// Function to handle photo section updates
function editPhotoSection(sectionNumber) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.multiple = true;
    
    input.onchange = async (e) => {
        const files = Array.from(e.target.files);
        if (files.length === 0) return;

        const formData = new FormData();
        files.forEach(file => {
            formData.append('photos[]', file);
        });
        formData.append('action', 'update_photo_section');
        formData.append('section', sectionNumber);

        try {
            const response = await fetch('update_profile.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    const photoSection = document.querySelector(`#photo-section-${sectionNumber}`);
                    photoSection.innerHTML = data.photos.map(photo => 
                        `<img src="${photo}" alt="Photo">`
                    ).join('');
                } else {
                    alert('Failed to update photos');
                }
            }
        } catch (error) {
            console.error('Error updating photos:', error);
            alert('Error updating photos');
        }
    };

    input.click();
}

// Initialize tooltips and other UI elements
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects for edit buttons
    const editButtons = document.querySelectorAll('.edit-button');
    editButtons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            button.style.transform = 'scale(1.05)';
        });
        
        button.addEventListener('mouseleave', () => {
            button.style.transform = 'scale(1)';
        });
    });
});
