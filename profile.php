<?php
/**
 * User Profile Page
 * Displays user profile and allows customization
 */
session_start();
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get profile user ID (either from URL or default to logged-in user)
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];

// Get profile user data
$profile_user = getUserById($profile_id);

if (!$profile_user) {
    header("Location: index.php");
    exit();
}

// Check if it's the user's own profile
$isOwnProfile = $_SESSION['user_id'] === $profile_id;

// Get friendship status if not own profile
$friendshipStatus = false;
if (!$isOwnProfile) {
    $friendshipStatus = getFriendshipStatus($_SESSION['user_id'], $profile_id);
}

// Get customization settings
$customization = getUserCustomization($profile_id);

// Generate custom CSS based on user preferences
$customCSS = applyUserCustomization($customization);

// Get user profile data
$conn = getDbConnection();

// Get user's posts (with privacy check)
$posts_query = "";
if ($isOwnProfile) {
    // If viewing own profile, show all posts
    $posts_query = "
        SELECT p.*, COUNT(l.id) as like_count, COUNT(c.id) as comment_count 
        FROM posts p 
        LEFT JOIN likes l ON p.id = l.post_id 
        LEFT JOIN comments c ON p.id = c.post_id 
        WHERE p.user_id = ? 
        GROUP BY p.id 
        ORDER BY p.created_at DESC 
        LIMIT 10
    ";
} else {
    // If viewing someone else's profile, check privacy settings
    $posts_query = "
        SELECT p.*, COUNT(l.id) as like_count, COUNT(c.id) as comment_count 
        FROM posts p 
        LEFT JOIN likes l ON p.id = l.post_id 
        LEFT JOIN comments c ON p.id = c.post_id 
        WHERE p.user_id = ? AND (
            p.privacy = 'public' 
            OR (p.privacy = 'friends' AND EXISTS (
                SELECT 1 FROM friends 
                WHERE (user_id = ? AND friend_id = ? AND status = 'accepted')
                OR (user_id = ? AND friend_id = ? AND status = 'accepted')
            ))
        )
        GROUP BY p.id 
        ORDER BY p.created_at DESC 
        LIMIT 10
    ";
}

$stmt = $conn->prepare($posts_query);

if ($isOwnProfile) {
    $stmt->bind_param("i", $profile_id);
} else {
    $stmt->bind_param("iiiii", $profile_id, $_SESSION['user_id'], $profile_id, $profile_id, $_SESSION['user_id']);
}

$stmt->execute();
$posts_result = $stmt->get_result();

// Get user's friends
$friends_query = "
    SELECT u.id, u.username, u.profile_picture 
    FROM users u
    JOIN friends f ON (u.id = f.friend_id OR u.id = f.user_id)
    WHERE (f.user_id = ? OR f.friend_id = ?)
    AND f.status = 'accepted'
    AND u.id != ?
    LIMIT 6
";

$friends_stmt = $conn->prepare($friends_query);
$friends_stmt->bind_param("iii", $profile_id, $profile_id, $profile_id);
$friends_stmt->execute();
$friends_result = $friends_stmt->get_result();

// Get user stats
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM posts WHERE user_id = ?) as post_count,
        (SELECT COUNT(*) FROM friends WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted') as friend_count,
        (SELECT COUNT(*) FROM likes WHERE user_id = ?) as like_count
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("iiii", $profile_id, $profile_id, $profile_id, $profile_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Generate CSRF token
$csrf_token = generateCSRFToken();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile_user['username']); ?>'s Profile - ConnectHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <!-- Add Google Fonts for custom fonts support -->
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&family=Comfortaa:wght@400;600&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <!-- User's custom CSS -->
    <style>
        <?php echo $customCSS; ?>
    </style>
</head>
<body>
    <!-- Include navbar -->
    <?php include 'components/navbar.php'; ?>
    
    <div class="page-container">
        <!-- Profile Cover and Header -->
        <div class="profile-container">
            <div class="profile-cover-container">
                <?php if (!empty($profile_user['cover_photo'])): ?>
                    <img src="<?php echo htmlspecialchars($profile_user['cover_photo']); ?>" alt="Cover" class="profile-cover">
                <?php else: ?>
                    <div class="profile-cover-default">
                        <i class="fas fa-image fa-3x"></i>
                    </div>
                <?php endif; ?>
                
                <?php if ($isOwnProfile): ?>
                    <div class="cover-edit-overlay">
                        <button class="edit-button" id="edit-cover-btn">
                            <i class="fas fa-camera"></i>
                            <span>Change Cover</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-header">
                <div class="profile-picture-container">
                    <?php if (!empty($profile_user['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($profile_user['profile_picture']); ?>" alt="Profile" class="profile-picture">
                    <?php else: ?>
                        <div class="profile-picture-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($isOwnProfile): ?>
                        <div class="edit-overlay" id="edit-profile-picture">
                            <button class="edit-button">
                                <i class="fas fa-camera"></i>
                                <span>Change</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-header-content">
                    <div class="profile-info">
                        <h1 class="profile-name">
                            <?php echo htmlspecialchars($profile_user['username']); ?>
                            <?php if (isset($profile_user['is_verified']) && $profile_user['is_verified']): ?>
                                <i class="fas fa-check-circle profile-verified" title="Verified Account"></i>
                            <?php endif; ?>
                        </h1>
                        
                        <?php if (isset($profile_user['tagline'])): ?>
                            <div class="profile-username">@<?php echo htmlspecialchars($profile_user['tagline']); ?></div>
                        <?php endif; ?>
                        
                        <div class="profile-bio">
                            <?php echo !empty($profile_user['bio']) ? nl2br(htmlspecialchars($profile_user['bio'])) : 'No bio yet...'; ?>
                        </div>
                        
                        <div class="profile-meta">
                            <?php if (!empty($profile_user['location'])): ?>
                                <div class="profile-meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($profile_user['location']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($profile_user['website'])): ?>
                                <div class="profile-meta-item">
                                    <i class="fas fa-link"></i>
                                    <a href="<?php echo htmlspecialchars($profile_user['website']); ?>" target="_blank"><?php echo htmlspecialchars($profile_user['website']); ?></a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="profile-meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Joined <?php echo date('F Y', strtotime($profile_user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-actions">
                        <?php if ($isOwnProfile): ?>
                            <a href="customize.php" class="profile-action-button profile-action-secondary">
                                <i class="fas fa-palette"></i>
                                <span>Customize Profile</span>
                            </a>
                            <a href="settings.php" class="profile-action-button profile-action-secondary">
                                <i class="fas fa-cog"></i>
                                <span>Edit Profile</span>
                            </a>
                        <?php else: ?>
                            <?php if ($friendshipStatus === 'accepted'): ?>
                                <button class="profile-action-button profile-action-secondary friend-action" data-action="unfriend" data-user-id="<?php echo $profile_id; ?>">
                                    <i class="fas fa-user-check"></i>
                                    <span>Friends</span>
                                </button>
                            <?php elseif ($friendshipStatus === 'pending' && $friendshipStatus['user_id'] == $_SESSION['user_id']): ?>
                                <button class="profile-action-button profile-action-secondary friend-action" data-action="cancel" data-user-id="<?php echo $profile_id; ?>">
                                    <i class="fas fa-user-clock"></i>
                                    <span>Requested</span>
                                </button>
                            <?php elseif ($friendshipStatus === 'pending' && $friendshipStatus['friend_id'] == $_SESSION['user_id']): ?>
                                <button class="profile-action-button profile-action-primary friend-action" data-action="accept" data-user-id="<?php echo $profile_id; ?>">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Accept Request</span>
                                </button>
                            <?php elseif ($friendshipStatus === 'blocked'): ?>
                                <button class="profile-action-button profile-action-secondary friend-action" data-action="unblock" data-user-id="<?php echo $profile_id; ?>">
                                    <i class="fas fa-user-slash"></i>
                                    <span>Unblock</span>
                                </button>
                            <?php else: ?>
                                <button class="profile-action-button profile-action-primary friend-action" data-action="add" data-user-id="<?php echo $profile_id; ?>">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Add Friend</span>
                                </button>
                            <?php endif; ?>
                            
                            <button class="profile-action-button profile-action-secondary message-action" data-user-id="<?php echo $profile_id; ?>">
                                <i class="fas fa-comment"></i>
                                <span>Message</span>
                            </button>
                            
                            <div class="profile-action-more">
                                <button class="profile-action-button profile-action-secondary" id="profile-more-btn">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="profile-more-dropdown" id="profile-more-dropdown">
                                    <?php if ($friendshipStatus !== 'blocked'): ?>
                                        <div class="profile-dropdown-item block-action" data-user-id="<?php echo $profile_id; ?>">
                                            <i class="fas fa-ban"></i>
                                            <span>Block</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="profile-dropdown-item report-action" data-user-id="<?php echo $profile_id; ?>">
                                        <i class="fas fa-flag"></i>
                                        <span>Report</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Profile Stats -->
            <div class="profile-stats">
                <div class="profile-stat">
                    <div class="profile-stat-number"><?php echo number_format($stats['post_count']); ?></div>
                    <div class="profile-stat-label">Posts</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-number"><?php echo number_format($stats['friend_count']); ?></div>
                    <div class="profile-stat-label">Friends</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-number"><?php echo number_format($stats['like_count']); ?></div>
                    <div class="profile-stat-label">Likes</div>
                </div>
            </div>
            
            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <div class="profile-tab active" data-tab="posts">
                    <i class="fas fa-th"></i>
                    <span>Posts</span>
                </div>
                <div class="profile-tab" data-tab="photos">
                    <i class="fas fa-image"></i>
                    <span>Photos</span>
                </div>
                <div class="profile-tab" data-tab="friends">
                    <i class="fas fa-user-friends"></i>
                    <span>Friends</span>
                </div>
                <?php if ($isOwnProfile): ?>
                    <div class="profile-tab" data-tab="saved">
                        <i class="fas fa-bookmark"></i>
                        <span>Saved</span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($customization['music_url'])): ?>
                    <div class="profile-tab" data-tab="music">
                        <i class="fas fa-music"></i>
                        <span>Music</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Profile Content -->
            <div class="profile-content">
                <!-- Main Content Area -->
                <div class="profile-main">
                    <!-- Tab Content: Posts -->
                    <div class="profile-tab-content" id="tab-posts" style="display: block;">
                        <?php if ($isOwnProfile): ?>
                            <!-- Create Post Box (only for own profile) -->
                            <div class="post-creator">
                                <div class="post-input-container">
                                    <img src="<?php echo !empty($profile_user['profile_picture']) ? htmlspecialchars($profile_user['profile_picture']) : 'assets/default-avatar.png'; ?>" alt="Your profile" class="user-avatar">
                                    <input type="text" placeholder="What's on your mind?" class="post-input" id="post-input-trigger">
                                </div>
                                <div class="post-actions">
                                    <div class="post-actions-left">
                                        <button class="action-button" id="post-photo-btn">
                                            <i class="fas fa-image"></i>
                                            <span>Photo/Video</span>
                                        </button>
                                        <button class="action-button" id="post-location-btn">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span>Location</span>
                                        </button>
                                    </div>
                                    <button class="post-submit" id="post-submit-btn" disabled>Post</button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Posts -->
                        <?php if ($posts_result->num_rows === 0): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                                <h3 class="empty-state-title">No posts yet</h3>
                                <?php if ($isOwnProfile): ?>
                                    <p class="empty-state-message">Create your first post to start sharing with your friends.</p>
                                <?php else: ?>
                                    <p class="empty-state-message"><?php echo htmlspecialchars($profile_user['username']); ?> hasn't posted anything yet.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php while ($post = $posts_result->fetch_assoc()): ?>
                                <div class="post-card">
                                    <div class="post-header">
                                        <img src="<?php echo !empty($profile_user['profile_picture']) ? htmlspecialchars($profile_user['profile_picture']) : 'assets/default-avatar.png'; ?>" alt="<?php echo htmlspecialchars($profile_user['username']); ?>" class="user-avatar">
                                        <div class="post-info">
                                            <a href="profile.php?id=<?php echo $profile_id; ?>" class="post-author"><?php echo htmlspecialchars($profile_user['username']); ?></a>
                                            <div class="post-meta">
                                                <span class="post-time"><?php echo formatTimeAgo($post['created_at']); ?></span>
                                                <span class="post-privacy">
                                                    <i class="fas <?php 
                                                        echo $post['privacy'] === 'public' ? 'fa-globe-americas' : 
                                                            ($post['privacy'] === 'friends' ? 'fa-user-friends' : 'fa-lock'); 
                                                    ?>"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <button class="post-menu" data-post-id="<?php echo $post['id']; ?>">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="post-dropdown" id="post-dropdown-<?php echo $post['id']; ?>">
                                            <?php if ($isOwnProfile): ?>
                                                <div class="post-dropdown-item post-edit" data-post-id="<?php echo $post['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                    <span>Edit Post</span>
                                                </div>
                                                <div class="post-dropdown-item post-delete" data-post-id="<?php echo $post['id']; ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                    <span>Delete Post</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="post-dropdown-item post-save" data-post-id="<?php echo $post['id']; ?>">
                                                    <i class="fas fa-bookmark"></i>
                                                    <span>Save Post</span>
                                                </div>
                                                <div class="post-dropdown-item post-report" data-post-id="<?php echo $post['id']; ?>">
                                                    <i class="fas fa-flag"></i>
                                                    <span>Report Post</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="post-content">
                                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                    </div>
                                    
                                    <?php if (!empty($post['image'])): ?>
                                        <div class="post-media">
                                            <div class="post-image-container">
                                                <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Post image" class="post-image">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($post['location_name'])): ?>
                                        <div class="post-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <div class="post-location-text"><?php echo htmlspecialchars($post['location_name']); ?></div>
                                            <a href="https://maps.google.com/?q=<?php echo $post['location_lat']; ?>,<?php echo $post['location_lng']; ?>" target="_blank" class="post-location-view">View Map</a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="post-stats">
                                        <div class="post-stat-group">
                                            <?php
                                            // Check if user liked this post
                                            $conn = getDbConnection();
                                            $like_check = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
                                            $like_check->bind_param("ii", $post['id'], $_SESSION['user_id']);
                                            $like_check->execute();
                                            $like_result = $like_check->get_result();
                                            $user_liked = $like_result->num_rows > 0;
                                            $conn->close();
                                            ?>
                                            <button class="stat-button like-button <?php echo $user_liked ? 'active' : ''; ?>" data-post-id="<?php echo $post['id']; ?>">
                                                <i class="fas fa-heart"></i>
                                                <span class="stat-count"><?php echo $post['like_count']; ?></span>
                                            </button>
                                            <button class="stat-button comment-button" data-post-id="<?php echo $post['id']; ?>">
                                                <i class="fas fa-comment"></i>
                                                <span class="stat-count"><?php echo $post['comment_count']; ?></span>
                                            </button>
                                        </div>
                                        <button class="stat-button share-button" data-post-id="<?php echo $post['id']; ?>">
                                            <i class="fas fa-share"></i>
                                            <span>Share</span>
                                        </button>
                                    </div>
                                    
                                    <!-- Comment section (hidden by default) -->
                                    <div class="post-comments" id="comments-<?php echo $post['id']; ?>" style="display: none;">
                                        <div class="comments-header">
                                            <h4 class="comments-title">Comments</h4>
                                            <div class="comments-sort">
                                                <span>Latest</span>
                                                <i class="fas fa-caret-down"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="comments-list" id="comments-list-<?php echo $post['id']; ?>">
                                            <div class="comments-loading">
                                                <i class="fas fa-spinner fa-spin"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="comment-form">
                                            <img src="<?php echo getUserAvatar($_SESSION['user_id']); ?>" alt="Your profile" class="user-avatar-small">
                                            <input type="text" class="comment-input" placeholder="Write a comment..." data-post-id="<?php echo $post['id']; ?>">
                                            <button class="comment-submit" disabled>
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Content: Photos -->
                    <div class="profile-tab-content" id="tab-photos" style="display: none;">
                        <!-- Photos grid here -->
                        <div class="photos-grid">
                            <?php
                            // Get user's photos from posts
                            $conn = getDbConnection();
                            $photos_query = "
                                SELECT image FROM posts 
                                WHERE user_id = ? AND image IS NOT NULL AND image != ''
                                ORDER BY created_at DESC
                            ";
                            $photos_stmt = $conn->prepare($photos_query);
                            $photos_stmt->bind_param("i", $profile_id);
                            $photos_stmt->execute();
                            $photos_result = $photos_stmt->get_result();
                            $conn->close();
                            
                            if ($photos_result->num_rows === 0): 
                            ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-images"></i>
                                    </div>
                                    <h3 class="empty-state-title">No photos yet</h3>
                                    <p class="empty-state-message">Photos from posts will appear here.</p>
                                </div>
                            <?php else: ?>
                                <div class="profile-photos">
                                    <?php while ($photo = $photos_result->fetch_assoc()): ?>
                                        <div class="profile-photo">
                                            <img src="<?php echo htmlspecialchars($photo['image']); ?>" alt="Photo" class="profile-photo-img">
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Tab Content: Friends -->
                    <div class="profile-tab-content" id="tab-friends" style="display: none;">
                        <?php if ($friends_result->num_rows === 0): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                                <h3 class="empty-state-title">No friends yet</h3>
                                <?php if ($isOwnProfile): ?>
                                    <p class="empty-state-message">Start connecting with others by sending friend requests.</p>
                                <?php else: ?>
                                    <p class="empty-state-message"><?php echo htmlspecialchars($profile_user['username']); ?> hasn't added any friends yet.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="profile-friends-grid">
                                <?php while ($friend = $friends_result->fetch_assoc()): ?>
                                    <div class="friend-card">
                                        <img src="<?php echo !empty($friend['profile_picture']) ? htmlspecialchars($friend['profile_picture']) : 'assets/default-avatar.png'; ?>" alt="<?php echo htmlspecialchars($friend['username']); ?>" class="friend-avatar">
                                        <a href="profile.php?id=<?php echo $friend['id']; ?>" class="friend-card-name"><?php echo htmlspecialchars($friend['username']); ?></a>
                                        <div class="friend-card-info">
                                            <?php
                                            // Get mutual friends count
                                            $conn = getDbConnection();
                                            $mutual_query = "
                                                SELECT COUNT(*) as count FROM (
                                                    SELECT DISTINCT f1.friend_id 
                                                    FROM friends f1 
                                                    WHERE f1.user_id = ? AND f1.status = 'accepted'
                                                    AND f1.friend_id IN (
                                                        SELECT f2.friend_id FROM friends f2 
                                                        WHERE f2.user_id = ? AND f2.status = 'accepted'
                                                    )
                                                ) as mutual
                                            ";
                                            $mutual_stmt = $conn->prepare($mutual_query);
                                            $mutual_stmt->bind_param("ii", $_SESSION['user_id'], $friend['id']);
                                            $mutual_stmt->execute();
                                            $mutual_result = $mutual_stmt->get_result();
                                            $mutual_count = $mutual_result->fetch_assoc()['count'];
                                            $conn->close();
                                            
                                            echo $mutual_count > 0 ? $mutual_count . ' mutual ' . ($mutual_count == 1 ? 'friend' : 'friends') : 'Friends since ' . date('M Y');
                                            ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Content: Saved (Only for own profile) -->
                    <?php if ($isOwnProfile): ?>
                        <div class="profile-tab-content" id="tab-saved" style="display: none;">
                            <?php
                            // Get saved posts
                            $conn = getDbConnection();
                            $saved_query = "
                                SELECT p.*, u.username, u.profile_picture 
                                FROM posts p 
                                JOIN users u ON p.user_id = u.id
                                JOIN saved_posts s ON p.id = s.post_id
                                WHERE s.user_id = ?
                                ORDER BY s.saved_at DESC
                            ";
                            $saved_stmt = $conn->prepare($saved_query);
                            $saved_stmt->bind_param("i", $_SESSION['user_id']);
                            $saved_stmt->execute();
                            $saved_result = $saved_stmt->get_result();
                            $conn->close();
                            
                            if ($saved_result->num_rows === 0):
                            ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-bookmark"></i>
                                    </div>
                                    <h3 class="empty-state-title">No saved posts</h3>
                                    <p class="empty-state-message">Posts you save will appear here.</p>
                                </div>
                            <?php else: ?>
                                <div class="saved-posts">
                                    <?php while ($saved_post = $saved_result->fetch_assoc()): ?>
                                        <!-- Saved post card HTML here (similar to regular post card) -->
                                        <div class="post-card">
                                            <!-- Post content -->
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Tab Content: Music (if available) -->
                    <?php if (!empty($customization['music_url'])): ?>
                        <div class="profile-tab-content" id="tab-music" style="display: none;">
                            <div class="profile-music">
                                <div class="profile-music-player">
                                    <img src="assets/music-cover.jpg" alt="Music" class="music-cover">
                                    <div class="music-info">
                                        <div class="music-title">Profile Song</div>
                                        <div class="music-artist"><?php echo htmlspecialchars($profile_user['username']); ?>'s selection</div>
                                        <div class="music-controls">
                                            <button class="music-button">
                                                <i class="fas fa-step-backward"></i>
                                            </button>
                                            <button class="music-button play-pause">
                                                <i class="fas fa-play"></i>
                                            </button>
                                            <button class="music-button">
                                                <i class="fas fa-step-forward"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="music-progress">
                                    <div class="music-progress-bar"></div>
                                </div>
                                <audio id="profile-music" src="<?php echo htmlspecialchars($customization['music_url']); ?>" preload="metadata"></audio>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Profile Sidebar -->
                <div class="profile-sidebar">
                    <!-- About Section -->
                    <div class="sidebar-card">
                        <div class="sidebar-header">
                            <div class="sidebar-title">About</div>
                        </div>
                        <div class="sidebar-content">
                            <ul class="about-list">
                                <?php if (!empty($profile_user['location'])): ?>
                                    <li class="about-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>Lives in <strong><?php echo htmlspecialchars($profile_user['location']); ?></strong></span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile_user['birthdate'])): ?>
                                    <li class="about-item">
                                        <i class="fas fa-birthday-cake"></i>
                                        <span>Born on <strong><?php echo date('F j, Y', strtotime($profile_user['birthdate'])); ?></strong></span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile_user['job_title']) && !empty($profile_user['workplace'])): ?>
                                    <li class="about-item">
                                        <i class="fas fa-briefcase"></i>
                                        <span><strong><?php echo htmlspecialchars($profile_user['job_title']); ?></strong> at <strong><?php echo htmlspecialchars($profile_user['workplace']); ?></strong></span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile_user['education'])): ?>
                                    <li class="about-item">
                                        <i class="fas fa-graduation-cap"></i>
                                        <span>Studied at <strong><?php echo htmlspecialchars($profile_user['education']); ?></strong></span>
                                    </li>
                                <?php endif; ?>
                                
                                <li class="about-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Joined <strong><?php echo date('F Y', strtotime($profile_user['created_at'])); ?></strong></span>
                                </li>
                            </ul>
                            
                            <?php if ($isOwnProfile): ?>
                                <button class="edit-about-btn">
                                    <i class="fas fa-pencil-alt"></i>
                                    <span>Edit Details</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Friends Preview -->
                    <div class="sidebar-card">
                        <div class="sidebar-header">
                            <div class="sidebar-title">Friends</div>
                            <a href="#" class="view-all" onclick="document.querySelector('[data-tab=friends]').click(); return false;">See All</a>
                        </div>
                        <div class="sidebar-content">
                            <?php if ($friends_result->num_rows === 0): ?>
                                <p class="no-friends">No friends to show</p>
                            <?php else: ?>
                                <div class="friends-preview">
                                    <?php
                                    // Reset result pointer
                                    $friends_result->data_seek(0);
                                    while ($friend = $friends_result->fetch_assoc()): 
                                    ?>
                                        <a href="profile.php?id=<?php echo $friend['id']; ?>" class="friend-preview">
                                            <img src="<?php echo !empty($friend['profile_picture']) ? htmlspecialchars($friend['profile_picture']) : 'assets/default-avatar.png'; ?>" alt="<?php echo htmlspecialchars($friend['username']); ?>" class="friend-preview-avatar">
                                            <span class="friend-preview-name"><?php echo htmlspecialchars($friend['username']); ?></span>
                                        </a>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Photos Preview -->
                    <div class="sidebar-card">
                        <div class="sidebar-header">
                            <div class="sidebar-title">Photos</div>
                            <a href="#" class="view-all" onclick="document.querySelector('[data-tab=photos]').click(); return false;">See All</a>
                        </div>
                        <div class="sidebar-content">
                            <?php
                            $conn = getDbConnection();
                            $photos_query = "
                                SELECT image FROM posts 
                                WHERE user_id = ? AND image IS NOT NULL AND image != ''
                                ORDER BY created_at DESC
                                LIMIT 9
                            ";
                            $photos_stmt = $conn->prepare($photos_query);
                            $photos_stmt->bind_param("i", $profile_id);
                            $photos_stmt->execute();
                            $photos_preview = $photos_stmt->get_result();
                            $conn->close();
                            
                            if ($photos_preview->num_rows === 0):
                            ?>
                                <p class="no-photos">No photos to show</p>
                            <?php else: ?>
                                <div class="photos-preview">
                                    <?php while ($photo = $photos_preview->fetch_assoc()): ?>
                                        <div class="photo-preview">
                                            <img src="<?php echo htmlspecialchars($photo['image']); ?>" alt="Photo" class="photo-preview-img">
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Picture Modal -->
    <div class="modal-overlay" id="profile-picture-modal">
        <div class="modal modal-small">
            <div class="modal-header">
                <h3 class="modal-title">Update Profile Picture</h3>
                <button class="modal-close" id="profile-picture-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="profile-picture-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_profile_picture">
                    
                    <div class="file-upload-preview">
                        <div id="profile-preview-container">
                            <img id="profile-preview" src="<?php echo !empty($profile_user['profile_picture']) ? htmlspecialchars($profile_user['profile_picture']) : 'assets/default-avatar.png'; ?>" alt="Preview">
                        </div>
                    </div>
                    
                    <div class="file-input-container">
                        <input type="file" name="profile_picture" id="profile-picture-input" class="file-input" accept="image/*">
                        <button type="button" class="upload-button">
                            <i class="fas fa-upload"></i>
                            <span>Choose Image</span>
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" id="profile-picture-cancel" class="btn-secondary">Cancel</button>
                <button type="button" id="profile-picture-submit" class="btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Cover Photo Modal -->
    <div class="modal-overlay" id="cover-photo-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Update Cover Photo</h3>
                <button class="modal-close" id="cover-photo-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="cover-photo-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_cover_photo">
                    
                    <div class="file-upload-preview">
                        <div id="cover-preview-container">
                            <?php if (!empty($profile_user['cover_photo'])): ?>
                                <img id="cover-preview" src="<?php echo htmlspecialchars($profile_user['cover_photo']); ?>" alt="Preview">
                            <?php else: ?>
                                <div class="empty-upload">
                                    <i class="fas fa-image"></i>
                                    <span>No cover photo selected</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="file-input-container">
                        <input type="file" name="cover_photo" id="cover-photo-input" class="file-input" accept="image/*">
                        <button type="button" class="upload-button">
                            <i class="fas fa-upload"></i>
                            <span>Choose Image</span>
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" id="cover-photo-cancel" class="btn-secondary">Cancel</button>
                <button type="button" id="cover-photo-submit" class="btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Bio Modal -->
    <div class="modal-overlay" id="bio-modal">
        <div class="modal modal-small">
            <div class="modal-header">
                <h3 class="modal-title">Update Bio</h3>
                <button class="modal-close" id="bio-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="bio-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_bio">
                    
                    <div class="form-group">
                        <label for="bio-text">Bio</label>
                        <textarea name="bio" id="bio-text" rows="5" class="form-control" placeholder="Write something about yourself..."><?php echo htmlspecialchars($profile_user['bio'] ?? ''); ?></textarea>
                        <div class="form-hint">Maximum 500 characters</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" id="bio-cancel" class="btn-secondary">Cancel</button>
                <button type="button" id="bio-submit" class="btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
    
    <!-- Report User Modal -->
    <div class="modal-overlay" id="report-user-modal">
        <div class="modal modal-small">
            <div class="modal-header">
                <h3 class="modal-title">Report User</h3>
                <button class="modal-close" id="report-user-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="report-user-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $profile_id; ?>">
                    
                    <div class="form-group">
                        <label for="report-reason">Why are you reporting this user?</label>
                        <select name="reason" id="report-reason" class="form-control">
                            <option value="">Select a reason</option>
                            <option value="inappropriate_content">Inappropriate content</option>
                            <option value="fake_account">Fake account</option>
                            <option value="harassment">Harassment</option>
                            <option value="spam">Spam</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="report-details">Additional details</label>
                        <textarea name="details" id="report-details" rows="4" class="form-control" placeholder="Please provide any additional information..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" id="report-user-cancel" class="btn-secondary">Cancel</button>
                <button type="button" id="report-user-submit" class="btn-primary">Submit Report</button>
            </div>
        </div>
    </div>
    
    <!-- Notification container for toast notifications -->
    <div class="notification-container" id="notification-container"></div>

    <!-- Include scripts -->
    <script src="js/main.js"></script>
    <script src="js/posts.js"></script>
    <script src="js/profile.js"></script>
</body>
</html>
