<?php
/**
 * Profile Customization Page
 * Allows users to customize their profile appearance
 */
session_start();
require_once 'functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$customization = getUserCustomization($user_id);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && isValidCSRFToken($_POST['csrf_token'])) {
    $conn = getDbConnection();
    
    // Check if user customization record exists
    $checkStmt = $conn->prepare("SELECT user_id FROM user_customization WHERE user_id = ?");
    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $exists = $result->num_rows > 0;
    
    // Sanitize inputs
    $theme_color = sanitize($_POST['theme_color'] ?? $customization['theme_color']);
    $font_preference = sanitize($_POST['font_preference'] ?? $customization['font_preference']);
    $layout_preference = sanitize($_POST['layout_preference'] ?? $customization['layout_preference']);
    $background_color = sanitize($_POST['background_color'] ?? $customization['background_color']);
    $text_color = sanitize($_POST['text_color'] ?? $customization['text_color']);
    $link_color = sanitize($_POST['link_color'] ?? $customization['link_color']);
    $custom_css = sanitize($_POST['custom_css'] ?? $customization['custom_css']);
    $music_url = sanitize($_POST['music_url'] ?? $customization['music_url']);
    
    // Save background image if uploaded
    $background_image = $customization['background_image'];
    if (isset($_FILES['background_image']) && $_FILES['background_image']['size'] > 0) {
        $upload_dir = 'uploads/backgrounds/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['background_image']['name']);
        $target_file = $upload_dir . $file_name;
        
        // Check file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['background_image']['type'], $allowed_types)) {
            if (move_uploaded_file($_FILES['background_image']['tmp_name'], $target_file)) {
                $background_image = $target_file;
            }
        }
    }
    
    // Update database
    if ($exists) {
        $stmt = $conn->prepare("
            UPDATE user_customization 
            SET background_image = ?, background_color = ?, text_color = ?, 
                link_color = ?, custom_css = ?, music_url = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param("ssssssi", $background_image, $background_color, $text_color, $link_color, $custom_css, $music_url, $user_id);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO user_customization 
            (user_id, background_image, background_color, text_color, link_color, custom_css, music_url) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssss", $user_id, $background_image, $background_color, $text_color, $link_color, $custom_css, $music_url);
    }
    
    // Update user preference in users table
    $userStmt = $conn->prepare("
        UPDATE users
        SET theme_color = ?, font_preference = ?, layout_preference = ?
        WHERE id = ?
    ");
    $userStmt->bind_param("sssi", $theme_color, $font_preference, $layout_preference, $user_id);
    
    if ($stmt->execute() && $userStmt->execute()) {
        $message = 'Profile customization saved successfully!';
        $messageType = 'success';
        
        // Update local variable with new values
        $customization = getUserCustomization($user_id);
    } else {
        $message = 'Failed to save customization. Please try again.';
        $messageType = 'error';
    }
    
    $conn->close();
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Generate preview CSS
$previewCSS = applyUserCustomization($customization);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize Your Profile - ConnectHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <!-- Add Google Fonts for font previews -->
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&family=Roboto:wght@400;500;700&family=Lato:wght@400;700&family=Montserrat:wght@400;500;600&family=Poppins:wght@400;500;600&family=Comfortaa:wght@400;600&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    
    <!-- Color picker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.js"></script>
</head>
<body>
    <!-- Include navbar -->
    <?php include 'components/navbar.php'; ?>
    
    <div class="container">
        <div class="customize-wrapper">
            <div class="customize-header">
                <h1>Customize Your Profile</h1>
                <p>Make your profile unique by personalizing its appearance</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $messageType; ?>-alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="customize-container">
                <!-- Customization Form -->
                <div class="customize-form">
                    <form action="customize.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <!-- Theme Section -->
                        <div class="customize-section">
                            <h2 class="section-title">Theme Settings</h2>
                            
                            <div class="form-group">
                                <label for="theme_color">Theme Color</label>
                                <input type="text" id="theme_color" name="theme_color" class="color-picker" value="<?php echo htmlspecialchars($customization['theme_color']); ?>">
                                <div class="form-hint">This color will be used for buttons, links, and other UI elements</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="font_preference">Font Style</label>
                                <select id="font_preference" name="font_preference" class="form-control">
                                    <option value="System Default" <?php echo $customization['font_preference'] === 'System Default' ? 'selected' : ''; ?>>System Default</option>
                                    <option value="Roboto" <?php echo $customization['font_preference'] === 'Roboto' ? 'selected' : ''; ?> style="font-family: 'Roboto', sans-serif;">Roboto</option>
                                    <option value="Lato" <?php echo $customization['font_preference'] === 'Lato' ? 'selected' : ''; ?> style="font-family: 'Lato', sans-serif;">Lato</option>
                                    <option value="Montserrat" <?php echo $customization['font_preference'] === 'Montserrat' ? 'selected' : ''; ?> style="font-family: 'Montserrat', sans-serif;">Montserrat</option>
                                    <option value="Poppins" <?php echo $customization['font_preference'] === 'Poppins' ? 'selected' : ''; ?> style="font-family: 'Poppins', sans-serif;">Poppins</option>
                                    <option value="Quicksand" <?php echo $customization['font_preference'] === 'Quicksand' ? 'selected' : ''; ?> style="font-family: 'Quicksand', sans-serif;">Quicksand</option>
                                    <option value="Comfortaa" <?php echo $customization['font_preference'] === 'Comfortaa' ? 'selected' : ''; ?> style="font-family: 'Comfortaa', cursive;">Comfortaa</option>
                                    <option value="Playfair Display" <?php echo $customization['font_preference'] === 'Playfair Display' ? 'selected' : ''; ?> style="font-family: 'Playfair Display', serif;">Playfair Display</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="layout_preference">Layout Style</label>
                                <select id="layout_preference" name="layout_preference" class="form-control">
                                    <option value="standard" <?php echo $customization['layout_preference'] === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                    <option value="compact" <?php echo $customization['layout_preference'] === 'compact' ? 'selected' : ''; ?>>Compact</option>
                                    <option value="centered" <?php echo $customization['layout_preference'] === 'centered' ? 'selected' : ''; ?>>Centered</option>
                                    <option value="retro" <?php echo $customization['layout_preference'] === 'retro' ? 'selected' : ''; ?>>Retro</option>
                                    <option value="minimal" <?php echo $customization['layout_preference'] === 'minimal' ? 'selected' : ''; ?>>Minimal</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Profile Background Section -->
                        <div class="customize-section">
                            <h2 class="section-title">Profile Background</h2>
                            
                            <div class="form-group">
                                <label>Background Image</label>
                                <div class="background-preview">
                                    <?php if (!empty($customization['background_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($customization['background_image']); ?>" alt="Background preview" id="background-preview-img">
                                    <?php else: ?>
                                        <div class="empty-preview" id="background-preview-empty">
                                            <i class="fas fa-image"></i>
                                            <span>No background image</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="file-input-container">
                                    <input type="file" name="background_image" id="background-image-input" class="file-input" accept="image/*">
                                    <label for="background-image-input" class="upload-button">
                                        <i class="fas fa-upload"></i>
                                        <span>Choose Background Image</span>
                                    </label>
                                </div>
                                
                                <?php if (!empty($customization['background_image'])): ?>
                                    <div class="remove-background">
                                        <label>
                                            <input type="checkbox" name="remove_background" id="remove-background">
                                            Remove background image
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="background_color">Background Color</label>
                                <input type="text" id="background_color" name="background_color" class="color-picker" value="<?php echo htmlspecialchars($customization['background_color']); ?>">
                                <div class="form-hint">Will be used if no background image is set</div>
                            </div>
                        </div>
                        
                        <!-- Text and Links Section -->
                        <div class="customize-section">
                            <h2 class="section-title">Text & Links</h2>
                            
                            <div class="form-group">
                                <label for="text_color">Text Color</label>
                                <input type="text" id="text_color" name="text_color" class="color-picker" value="<?php echo htmlspecialchars($customization['text_color']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="link_color">Link Color</label>
                                <input type="text" id="link_color" name="link_color" class="color-picker" value="<?php echo htmlspecialchars($customization['link_color']); ?>">
                            </div>
                        </div>
                        
                        <!-- Profile Music Section -->
                        <div class="customize-section">
                            <h2 class="section-title">Profile Music</h2>
                            
                            <div class="form-group">
                                <label for="music_url">Music URL</label>
                                <input type="url" id="music_url" name="music_url" class="form-control" value="<?php echo htmlspecialchars($customization['music_url']); ?>" placeholder="https://example.com/music.mp3">
                                <div class="form-hint">Link to an MP3 file that will play when visitors view your profile</div>
                            </div>
                            
                            <div class="music-preview">
                                <button type="button" id="test-music-btn" class="btn-secondary" <?php echo empty($customization['music_url']) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-play"></i> Test Music
                                </button>
                                <audio id="music-preview" src="<?php echo htmlspecialchars($customization['music_url']); ?>" preload="none"></audio>
                            </div>
                        </div>
                        
                        <!-- Advanced Section -->
                        <div class="customize-section">
                            <h2 class="section-title">Advanced Customization</h2>
                            
                            <div class="form-group">
                                <label for="custom_css">Custom CSS</label>
                                <textarea id="custom_css" name="custom_css" class="form-control code-input" rows="6" placeholder="/* Add your custom CSS here */"><?php echo htmlspecialchars($customization['custom_css']); ?></textarea>
                                <div class="form-hint">For advanced users. Add custom CSS to further customize your profile.</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="profile.php" class="btn-secondary">Cancel</a>
                            <button type="submit" class="btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Preview Section -->
                <div class="customize-preview">
                    <div class="preview-header">
                        <h2>Preview</h2>
                        <div class="preview-controls">
                            <button id="refresh-preview" class="btn-icon" title="Refresh Preview">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="preview-container">
                        <iframe id="preview-frame" src="profile_preview.php" frameborder="0"></iframe>
                    </div>
                    
                    <div class="preview-help">
                        <p><i class="fas fa-info-circle"></i> This is a preview of how your profile will look with the selected customization options.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize color pickers
            $('.color-picker').spectrum({
                type: "component",
                showInput: true,
                showInitial: true,
                allowEmpty: true,
                preferredFormat: "hex"
            });
            
            // Background image preview
            const backgroundInput = document.getElementById('background-image-input');
            const backgroundPreviewImg = document.getElementById('background-preview-img');
            const backgroundPreviewEmpty = document.getElementById('background-preview-empty');
            
            if (backgroundInput) {
                backgroundInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (!backgroundPreviewImg) {
                                // Create image if it doesn't exist
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.id = 'background-preview-img';
                                img.alt = 'Background preview';
                                
                                const previewContainer = document.querySelector('.background-preview');
                                previewContainer.innerHTML = '';
                                previewContainer.appendChild(img);
                            } else {
                                backgroundPreviewImg.src = e.target.result;
                                backgroundPreviewImg.style.display = 'block';
                                if (backgroundPreviewEmpty) {
                                    backgroundPreviewEmpty.style.display = 'none';
                                }
                            }
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
            
            // Remove background checkbox
            const removeBackgroundCheck = document.getElementById('remove-background');
            if (removeBackgroundCheck) {
                removeBackgroundCheck.addEventListener('change', function() {
                    if (this.checked && backgroundPreviewImg) {
                        backgroundPreviewImg.style.display = 'none';
                        if (backgroundPreviewEmpty) {
                            backgroundPreviewEmpty.style.display = 'flex';
                        } else {
                            // Create empty preview
                            const emptyPreview = document.createElement('div');
                            emptyPreview.className = 'empty-preview';
                            emptyPreview.id = 'background-preview-empty';
                            emptyPreview.innerHTML = '<i class="fas fa-image"></i><span>No background image</span>';
                            
                            const previewContainer = document.querySelector('.background-preview');
                            previewContainer.appendChild(emptyPreview);
                        }
                    } else if (backgroundPreviewImg) {
                        backgroundPreviewImg.style.display = 'block';
                        if (backgroundPreviewEmpty) {
                            backgroundPreviewEmpty.style.display = 'none';
                        }
                    }
                });
            }
            
            // Test music playback
            const testMusicBtn = document.getElementById('test-music-btn');
            const musicPreview = document.getElementById('music-preview');
            const musicUrlInput = document.getElementById('music_url');
            
            if (testMusicBtn && musicPreview) {
                testMusicBtn.addEventListener('click', function() {
                    if (musicPreview.paused) {
                        musicPreview.src = musicUrlInput.value; // Update source with current value
                        musicPreview.play();
                        this.innerHTML = '<i class="fas fa-pause"></i> Stop Music';
                    } else {
                        musicPreview.pause();
                        musicPreview.currentTime = 0;
                        this.innerHTML = '<i class="fas fa-play"></i> Test Music';
                    }
                });
                
                // Enable/disable test button based on URL input
                if (musicUrlInput) {
                    musicUrlInput.addEventListener('input', function() {
                        testMusicBtn.disabled = this.value.trim() === '';
                        
                        if (musicPreview && !musicPreview.paused) {
                            musicPreview.pause();
                            musicPreview.currentTime = 0;
                            testMusicBtn.innerHTML = '<i class="fas fa-play"></i> Test Music';
                        }
                    });
                }
            }
            
            // Refresh preview iframe
            const refreshPreviewBtn = document.getElementById('refresh-preview');
            if (refreshPreviewBtn) {
                refreshPreviewBtn.addEventListener('click', function() {
                    const previewFrame = document.getElementById('preview-frame');
                    if (previewFrame) {
                        // Get current form values
                        const formData = new FormData(document.querySelector('.customize-form form'));
                        
                        // Send values to preview page
                        fetch('profile_preview.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.text())
                        .then(html => {
                            // Update iframe content
                            previewFrame.contentWindow.document.open();
                            previewFrame.contentWindow.document.write(html);
                            previewFrame.contentWindow.document.close();
                        })
                        .catch(error => {
                            console.error('Error updating preview:', error);
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>