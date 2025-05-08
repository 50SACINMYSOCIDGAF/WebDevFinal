<?php
/**
 * Profile Preview Page
 * Used in the customize page for live preview
 */
session_start();
require_once 'functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Get current customization
$customization = getUserCustomization($user_id);

// If this is a POST request, use the posted values for preview
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Override customization with form values
    $customization['theme_color'] = $_POST['theme_color'] ?? $customization['theme_color'];
    $customization['font_preference'] = $_POST['font_preference'] ?? $customization['font_preference'];
    $customization['layout_preference'] = $_POST['layout_preference'] ?? $customization['layout_preference'];
    $customization['background_color'] = $_POST['background_color'] ?? $customization['background_color'];
    $customization['text_color'] = $_POST['text_color'] ?? $customization['text_color'];
    $customization['link_color'] = $_POST['link_color'] ?? $customization['link_color'];
    $customization['custom_css'] = $_POST['custom_css'] ?? $customization['custom_css'];
    $customization['music_url'] = $_POST['music_url'] ?? $customization['music_url'];
    
    // Handle background image preview (if file uploaded)
    if (isset($_FILES['background_image']) && $_FILES['background_image']['size'] > 0) {
        $tmp_path = $_FILES['background_image']['tmp_name'];
        if (file_exists($tmp_path)) {
            $customization['background_image'] = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($tmp_path));
        }
    }
    
    // Handle background removal checkbox
    if (isset($_POST['remove_background']) && $_POST['remove_background'] === 'on') {
        $customization['background_image'] = '';
    }
}

// Generate CSS based on customization
$customCSS = applyUserCustomization($customization);

// Add extra CSS for preview layout/sizing
$extraCSS = '
    html, body {
        height: 100%;
        overflow: hidden;
    }
    
    .profile-container {
        max-width: 100%;
        padding: 0;
    }
    
    .profile-cover-container {
        height: 150px;
    }
    
    .profile-header {
        padding: 0 1rem;
    }
    
    .profile-stats, .profile-tabs {
        padding: 0.5rem 1rem;
    }
    
    .profile-content {
        padding: 0.5rem;
    }
';

// Add custom font loading
$customFont = '';
if ($customization['font_preference'] !== 'System Default') {
    $fontName = str_replace(' ', '+', $customization['font_preference']);
    $customFont = '<link href="https://fonts.googleapis.com/css2?family=' . $fontName . ':wght@400;500;600;700&display=swap" rel="stylesheet">';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Preview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <?php echo $customFont; ?>
    <style>
        <?php echo $customCSS; ?>
        <?php echo $extraCSS; ?>
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Profile Cover -->
        <div class="profile-cover-container">
            <?php if (!empty($customization['background_image'])): ?>
                <img src="<?php echo htmlspecialchars($customization['background_image']); ?>" alt="Cover" class="profile-cover">
            <?php else: ?>
                <div class="profile-cover-default">
                    <i class="fas fa-image fa-3x"></i>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-picture-container">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile" class="profile-picture">
                <?php else: ?>
                    <div class="profile-picture-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-header-content">
                <div class="profile-info">
                    <h1 class="profile-name">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </h1>
                    
                    <div class="profile-bio">
                        <?php echo !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : 'Your bio will appear here...'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Profile Stats -->
        <div class="profile-stats">
            <div class="profile-stat">
                <div class="profile-stat-number">0</div>
                <div class="profile-stat-label">Posts</div>
            </div>
            <div class="profile-stat">
                <div class="profile-stat-number">0</div>
                <div class="profile-stat-label">Friends</div>
            </div>
            <div class="profile-stat">
                <div class="profile-stat-number">0</div>
                <div class="profile-stat-label">Likes</div>
            </div>
        </div>
        
        <!-- Profile Tabs -->
        <div class="profile-tabs">
            <div class="profile-tab active">
                <i class="fas fa-th"></i>
                <span>Posts</span>
            </div>
            <div class="profile-tab">
                <i class="fas fa-image"></i>
                <span>Photos</span>
            </div>
            <div class="profile-tab">
                <i class="fas fa-user-friends"></i>
                <span>Friends</span>
            </div>
        </div>
        
        <!-- Profile Content -->
        <div class="profile-content">
            <div class="preview-post">
                <div class="post-card">
                    <div class="post-header">
                        <img src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'assets/default-avatar.png'; ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-avatar">
                        <div class="post-info">
                            <a href="#" class="post-author"><?php echo htmlspecialchars($user['username']); ?></a>
                            <div class="post-meta">
                                <span class="post-time">Just now</span>
                                <span class="post-privacy">
                                    <i class="fas fa-globe-americas"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="post-content">
                        This is a sample post to preview your custom profile styles.
                    </div>
                    
                    <div class="post-stats">
                        <div class="post-stat-group">
                            <button class="stat-button like-button">
                                <i class="fas fa-heart"></i>
                                <span class="stat-count">10</span>
                            </button>
                            <button class="stat-button comment-button">
                                <i class="fas fa-comment"></i>
                                <span class="stat-count">5</span>
                            </button>
                        </div>
                        <button class="stat-button share-button">
                            <i class="fas fa-share"></i>
                            <span>Share</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>