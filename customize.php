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
    // --- Form processing logic (Keep your existing logic here) ---
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
    $custom_css = $_POST['custom_css'] ?? $customization['custom_css']; // Don't sanitize CSS heavily
    $music_url = sanitize($_POST['music_url'] ?? $customization['music_url']);
    $remove_background = isset($_POST['remove_background']) && $_POST['remove_background'] === 'on';

    // Save background image if uploaded
    $background_image = $customization['background_image'];
    if ($remove_background) {
        $background_image = ''; // Remove image if checkbox is checked
        // Optionally delete the old file from server here
    } elseif (isset($_FILES['background_image']) && $_FILES['background_image']['size'] > 0) {
        $upload_dir = 'uploads/backgrounds/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = time() . '_' . basename($_FILES['background_image']['name']);
        $target_file = $upload_dir . $file_name;
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['background_image']['type'], $allowed_types)) {
            if (move_uploaded_file($_FILES['background_image']['tmp_name'], $target_file)) {
                $background_image = $target_file;
                // Optionally delete the old file from server here
            }
        }
    }

    // Update database
    $conn->begin_transaction();
    try {
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
                (user_id, background_image, background_color, text_color, link_color, custom_css, music_url, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param("issssss", $user_id, $background_image, $background_color, $text_color, $link_color, $custom_css, $music_url);
        }
        $stmt->execute();

        // Update user preference in users table
        $userStmt = $conn->prepare("
            UPDATE users
            SET theme_color = ?, font_preference = ?, layout_preference = ?
            WHERE id = ?
        ");
        $userStmt->bind_param("sssi", $theme_color, $font_preference, $layout_preference, $user_id);
        $userStmt->execute();

        $conn->commit();
        $message = 'Profile customization saved successfully!';
        $messageType = 'success';
        // Update local variable with new values
        $customization = getUserCustomization($user_id);

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $message = 'Failed to save customization. Please try again.';
        $messageType = 'error';
    }

    $conn->close();
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize Your Profile - ConnectHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&family=Roboto:wght@400;500;700&family=Lato:wght@400;700&family=Montserrat:wght@400;500;600&family=Poppins:wght@400;500;600&family=Comfortaa:wght@400;600&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Add specific styles for customize page */
        .customize-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); /* Responsive grid */
            gap: 1.5rem; /* Spacing between cards */
            max-width: 1200px; /* Max width of the grid */
            margin: 2rem auto; /* Center the grid */
        }

        .form-group {
             margin-bottom: 1rem; /* Consistent spacing */
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-hint {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Style file inputs */
        .file-input-container {
            margin-top: 0.5rem;
        }
        .upload-button { /* Use the existing btn styles */
            display: inline-flex; /* Make it behave like a button */
            cursor: pointer;
        }
        .file-input { /* Hide the actual file input */
             width: 0.1px;
             height: 0.1px;
             opacity: 0;
             overflow: hidden;
             position: absolute;
             z-index: -1;
        }

        .background-preview {
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            min-height: 150px; /* Ensure it has some height */
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden; /* Ensure image fits */
             background-color: var(--bg-tertiary);
        }
        .background-preview img {
            max-width: 100%;
            max-height: 250px;
            object-fit: cover;
            display: block;
        }
        .empty-preview {
            color: var(--text-secondary);
            text-align: center;
        }
        .empty-preview i {
             font-size: 2rem;
             margin-bottom: 0.5rem;
             display: block;
        }

        .remove-background {
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
         .remove-background input {
             margin-right: 0.5rem;
             width: auto; /* Override default input width */
         }

        .music-preview {
            margin-top: 1rem;
        }

         .code-input { /* Style textarea for CSS */
            font-family: monospace;
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 6px;
            min-height: 150px;
         }

         .customize-preview-container {
             margin: 2rem auto; /* Center preview section */
             max-width: 1200px;
             padding: 1.5rem;
             background-color: var(--bg-secondary);
             border: 1px solid var(--border);
             border-radius: 12px;
         }
         .preview-header {
             display: flex;
             justify-content: space-between;
             align-items: center;
             margin-bottom: 1rem;
         }
         .preview-header h2 {
             margin: 0;
             font-size: 1.25rem;
         }
         .preview-frame {
             width: 100%;
             height: 500px; /* Adjust height as needed */
             border: 1px solid var(--border);
             border-radius: 8px;
             overflow: hidden;
         }
         .preview-help {
             margin-top: 1rem;
             font-size: 0.9rem;
             color: var(--text-secondary);
             text-align: center;
         }

         .form-actions {
             margin-top: 1.5rem;
             padding-top: 1.5rem;
             border-top: 1px solid var(--border);
             display: flex;
             justify-content: flex-end; /* Align buttons to the right */
             gap: 1rem;
         }

        .alert { /* Basic alert styling */
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }
        .alert.success {
            background-color: rgba(16, 185, 129, 0.1);
            border-color: #10b981;
            color: #065f46;
        }
        .alert.error {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #b91c1c;
        }

        /* Ensure color picker input looks like a button */
        .sp-replacer {
             padding: 0;
             border: none;
             background: transparent;
             display: inline-block; /* Or block */
             margin-right: 5px;
        }
        .sp-preview {
             width: 30px;
             height: 30px;
             border-radius: 4px;
             border: 1px solid var(--border);
             cursor: pointer;
        }
        .sp-dd { display: none; } /* Hide dropdown arrow */
        .sp-input { /* Style the hex input if shown */
            height: 30px;
            border-radius: 4px;
            border: 1px solid var(--border);
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 0 5px;
            width: 80px; /* Adjust width */
            margin-left: 5px;
            vertical-align: top;
        }
        /* Make label and picker align better */
        .form-group .sp-container {
             display: inline-block;
             vertical-align: middle;
             margin-left: 10px;
        }


    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="page-container">
        <div class="container-narrow"> <div class="customize-header text-center mb-4"> <h1>Customize Your Profile</h1>
                <p class="text-secondary">Make your profile unique by personalizing its appearance</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="customize.php" method="POST" enctype="multipart/form-data" id="customize-form">
                 <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                 <div class="customize-grid">

                    <div class="card customize-section">
                        <div class="card-header">
                             <h2 class="card-title">Theme Settings</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="theme_color">Theme Color</label>
                                <input type="text" id="theme_color" name="theme_color" class="color-picker" value="<?php echo htmlspecialchars($customization['theme_color'] ?? '#4f46e5'); ?>">
                                <div class="form-hint">Accent color for buttons, links, etc.</div>
                            </div>

                            <div class="form-group">
                                <label for="font_preference">Font Style</label>
                                <select id="font_preference" name="font_preference" class="form-control">
                                    <option value="System Default" <?php echo ($customization['font_preference'] ?? '') === 'System Default' ? 'selected' : ''; ?>>System Default</option>
                                    <option value="Roboto" <?php echo ($customization['font_preference'] ?? '') === 'Roboto' ? 'selected' : ''; ?> style="font-family: 'Roboto', sans-serif;">Roboto</option>
                                    <option value="Lato" <?php echo ($customization['font_preference'] ?? '') === 'Lato' ? 'selected' : ''; ?> style="font-family: 'Lato', sans-serif;">Lato</option>
                                    <option value="Montserrat" <?php echo ($customization['font_preference'] ?? '') === 'Montserrat' ? 'selected' : ''; ?> style="font-family: 'Montserrat', sans-serif;">Montserrat</option>
                                    <option value="Poppins" <?php echo ($customization['font_preference'] ?? '') === 'Poppins' ? 'selected' : ''; ?> style="font-family: 'Poppins', sans-serif;">Poppins</option>
                                    <option value="Quicksand" <?php echo ($customization['font_preference'] ?? '') === 'Quicksand' ? 'selected' : ''; ?> style="font-family: 'Quicksand', sans-serif;">Quicksand</option>
                                    <option value="Comfortaa" <?php echo ($customization['font_preference'] ?? '') === 'Comfortaa' ? 'selected' : ''; ?> style="font-family: 'Comfortaa', cursive;">Comfortaa</option>
                                    <option value="Playfair Display" <?php echo ($customization['font_preference'] ?? '') === 'Playfair Display' ? 'selected' : ''; ?> style="font-family: 'Playfair Display', serif;">Playfair Display</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="layout_preference">Layout Style</label>
                                <select id="layout_preference" name="layout_preference" class="form-control">
                                     <option value="standard" <?php echo ($customization['layout_preference'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                     </select>
                                <div class="form-hint">Affects profile page layout (future feature).</div>
                            </div>
                        </div>
                    </div>

                    <div class="card customize-section">
                         <div class="card-header">
                            <h2 class="card-title">Profile Background</h2>
                        </div>
                         <div class="card-body">
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
                                    <label for="background-image-input" class="btn btn-secondary btn-sm"> <i class="fas fa-upload"></i> Choose Image
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
                                <input type="text" id="background_color" name="background_color" class="color-picker" value="<?php echo htmlspecialchars($customization['background_color'] ?? ''); ?>">
                                <div class="form-hint">Used if no background image is set.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card customize-section">
                        <div class="card-header">
                            <h2 class="card-title">Text & Links</h2>
                        </div>
                        <div class="card-body">
                             <div class="form-group">
                                <label for="text_color">Text Color</label>
                                <input type="text" id="text_color" name="text_color" class="color-picker" value="<?php echo htmlspecialchars($customization['text_color'] ?? ''); ?>">
                                <div class="form-hint">Default text color on your profile.</div>
                            </div>

                            <div class="form-group">
                                <label for="link_color">Link Color</label>
                                <input type="text" id="link_color" name="link_color" class="color-picker" value="<?php echo htmlspecialchars($customization['link_color'] ?? ''); ?>">
                                <div class="form-hint">Color for links on your profile.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card customize-section">
                         <div class="card-header">
                            <h2 class="card-title">Profile Music</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="music_url">Music URL</label>
                                <input type="url" id="music_url" name="music_url" class="form-control" value="<?php echo htmlspecialchars($customization['music_url'] ?? ''); ?>" placeholder="https://example.com/music.mp3">
                                <div class="form-hint">Link to an MP3 file for your profile.</div>
                            </div>
                            <div class="music-preview">
                                <button type="button" id="test-music-btn" class="btn btn-secondary btn-sm" <?php echo empty($customization['music_url']) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-play"></i> Test Music
                                </button>
                                <audio id="music-player" src="<?php echo htmlspecialchars($customization['music_url'] ?? ''); ?>" preload="none"></audio>
                            </div>
                         </div>
                    </div>

                    <div class="card customize-section">
                        <div class="card-header">
                            <h2 class="card-title">Advanced Customization</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="custom_css">Custom CSS</label>
                                <textarea id="custom_css" name="custom_css" class="form-control code-input" rows="6" placeholder="/* Add custom CSS rules here */"><?php echo htmlspecialchars($customization['custom_css'] ?? ''); ?></textarea>
                                <div class="form-hint">Advanced users only. Use with caution.</div>
                            </div>
                        </div>
                    </div>

                 </div> <div class="form-actions">
                     <a href="profile.php" class="btn btn-secondary">Cancel</a>
                     <button type="submit" class="btn btn-primary">Save Changes</button>
                 </div>
            </form>

            <div class="customize-preview-container mt-5">
                <div class="preview-header">
                    <h2>Live Preview</h2>
                    <button id="refresh-preview" class="btn btn-sm btn-secondary" title="Refresh Preview">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="preview-frame-container">
                    <iframe id="preview-frame" src="profile_preview.php" frameborder="0" class="preview-frame"></iframe>
                </div>
                <div class="preview-help">
                    <p><i class="fas fa-info-circle"></i> This preview updates when you save changes or click refresh.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="notification-container" id="notification-container"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.js"></script>
    <script src="js/main.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize color pickers
            $(".color-picker").spectrum({
                type: "color", // Use 'color' type for hex input with picker
                showInput: true,
                showInitial: true,
                allowEmpty: true,
                preferredFormat: "hex",
                 showAlpha: false // Disable alpha for simple hex
            });

            // Background image preview logic
            const backgroundInput = document.getElementById('background-image-input');
            const backgroundPreviewContainer = document.querySelector('.background-preview'); // Target the container
            const removeBackgroundCheck = document.getElementById('remove-background');

             function updatePreviewImage(src) {
                let img = backgroundPreviewContainer.querySelector('img');
                let emptyView = backgroundPreviewContainer.querySelector('.empty-preview');

                if (src) {
                    if (!img) {
                        img = document.createElement('img');
                        img.alt = 'Background preview';
                        img.id = 'background-preview-img';
                        backgroundPreviewContainer.innerHTML = ''; // Clear container
                        backgroundPreviewContainer.appendChild(img);
                    }
                    img.src = src;
                    img.style.display = 'block';
                     if (emptyView) emptyView.style.display = 'none';
                 } else {
                     if (img) img.style.display = 'none';
                    if (!emptyView) {
                        emptyView = document.createElement('div');
                        emptyView.className = 'empty-preview';
                        emptyView.id = 'background-preview-empty';
                         emptyView.innerHTML = '<i class="fas fa-image"></i><span>No background image</span>';
                         backgroundPreviewContainer.appendChild(emptyView);
                     }
                     emptyView.style.display = 'flex'; // Use flex for centering
                }
             }

            if (backgroundInput) {
                backgroundInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                             updatePreviewImage(e.target.result);
                             // Uncheck remove box if user uploads new image
                            if (removeBackgroundCheck) removeBackgroundCheck.checked = false;
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }

            if (removeBackgroundCheck) {
                removeBackgroundCheck.addEventListener('change', function() {
                    if (this.checked) {
                         updatePreviewImage(null); // Show empty preview
                         backgroundInput.value = ''; // Clear file input
                     } else {
                         // Revert to original image if unchecked (or stay empty if no original)
                         const originalImageSrc = '<?php echo htmlspecialchars($customization['background_image'] ?? '', ENT_QUOTES); ?>';
                         updatePreviewImage(originalImageSrc);
                     }
                });
            }

            // Test music playback logic
            const testMusicBtn = document.getElementById('test-music-btn');
            const musicPlayer = document.getElementById('music-player');
            const musicUrlInput = document.getElementById('music_url');

            if (testMusicBtn && musicPlayer && musicUrlInput) {
                testMusicBtn.addEventListener('click', function() {
                    const currentUrl = musicUrlInput.value.trim();
                    if (!currentUrl) return; // Don't play if URL is empty

                    if (musicPlayer.paused || musicPlayer.src !== currentUrl) {
                        musicPlayer.src = currentUrl;
                         musicPlayer.play()
                             .then(() => {
                                 testMusicBtn.innerHTML = '<i class="fas fa-pause"></i> Stop Music';
                             })
                             .catch(error => {
                                 console.error("Audio playback failed:", error);
                                 showNotification('Could not play audio. Check the URL or file format.', 'error');
                                 testMusicBtn.innerHTML = '<i class="fas fa-play"></i> Test Music';
                             });
                    } else {
                        musicPlayer.pause();
                        musicPlayer.currentTime = 0;
                        testMusicBtn.innerHTML = '<i class="fas fa-play"></i> Test Music';
                    }
                });

                 // Reset button when player ends
                 musicPlayer.addEventListener('ended', function() {
                      testMusicBtn.innerHTML = '<i class="fas fa-play"></i> Test Music';
                 });

                // Enable/disable test button based on URL input
                musicUrlInput.addEventListener('input', function() {
                    testMusicBtn.disabled = this.value.trim() === '';
                    if (musicPlayer && !musicPlayer.paused) {
                        musicPlayer.pause();
                        musicPlayer.currentTime = 0;
                        testMusicBtn.innerHTML = '<i class="fas fa-play"></i> Test Music';
                    }
                });
            }

            // Refresh preview iframe (simplified - just reload)
            const refreshPreviewBtn = document.getElementById('refresh-preview');
             const previewFrame = document.getElementById('preview-frame');
            if (refreshPreviewBtn && previewFrame) {
                 // Initial load based on saved data
                 previewFrame.src = 'profile_preview.php';

                refreshPreviewBtn.addEventListener('click', function() {
                     // Simply reload the preview frame which uses saved settings
                     previewFrame.contentWindow.location.reload();
                     // For a true live preview without saving, you'd need to POST
                     // the current form data to profile_preview.php as done before,
                     // but reloading after save is often sufficient.
                });
            }
        });
    </script>
</body>
</html>