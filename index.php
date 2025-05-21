<?php
/**
 * ConnectHub - Home Page
 * Main feed page showing posts from friends and user's network
 */
session_start();
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$user = getUserById($_SESSION['user_id']);
$avatar_url = getUserAvatar($_SESSION['user_id']);

// Get posts for the feed
$conn = getDbConnection();

// Query to get posts from friends and public posts
$posts_query = "
    SELECT p.*, u.username, u.profile_picture 
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.privacy = 'public' 
        OR p.user_id = ? 
        OR p.user_id IN (
            SELECT friend_id FROM friends 
            WHERE user_id = ? AND status = 'accepted'
        )
    ORDER BY p.created_at DESC
    LIMIT 20
";

$stmt = $conn->prepare($posts_query);
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$posts_result = $stmt->get_result();

// Get suggested friends
$suggested_friends_query = "
    SELECT u.id, u.username, u.profile_picture, 
    (
        SELECT COUNT(*) FROM friends f1 
        JOIN friends f2 ON f1.friend_id = f2.user_id 
        WHERE f1.user_id = ? AND f2.friend_id = u.id AND f1.status = 'accepted' AND f2.status = 'accepted'
    ) as mutual_count
    FROM users u
    WHERE u.id != ? 
    AND u.id NOT IN (
        SELECT friend_id FROM friends WHERE user_id = ?
    )
    ORDER BY mutual_count DESC, RAND()
    LIMIT 5
";

$stmt = $conn->prepare($suggested_friends_query);
$stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$suggested_friends_result = $stmt->get_result();

// NEW: Fetch upcoming events for the sidebar
$upcoming_events_query = "
    SELECT e.id, e.title, e.event_date, e.event_time, e.location_name, e.user_id, u.username,
           (SELECT COUNT(*) FROM event_attendees ea WHERE ea.event_id = e.id AND ea.status = 'going') as going_count
    FROM events e
    JOIN users u ON e.user_id = u.id
    WHERE e.event_date >= CURDATE() AND (
        e.privacy = 'public'
        OR e.user_id = ?
        OR (e.privacy = 'friends' AND EXISTS (
            SELECT 1 FROM friends
            WHERE (user_id = e.user_id AND friend_id = ? AND status = 'accepted')
            OR (user_id = ? AND friend_id = e.user_id AND status = 'accepted')
        ))
    )
    ORDER BY e.event_date ASC, e.event_time ASC
    LIMIT 3
";
$stmt = $conn->prepare($upcoming_events_query);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$upcoming_events_result = $stmt->get_result();

$conn->close();

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectHub | Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&family=Comfortaa:wght@400;600&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* This ensures Font Awesome icons render correctly if the font isn't loaded by default */
        .fas {
            font-family: 'Font Awesome 6 Free';
            font-weight: 900; /* For solid icons */
        }
        .far {
            font-family: 'Font Awesome 6 Free';
            font-weight: 400; /* For regular icons */
        }
        /* Styling for events in index.php sidebar */
        .sidebar-card .event-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 8px;
            transition: background-color var(--transition-fast);
            text-decoration: none;
            color: var(--text-primary);
        }
        .sidebar-card .event-item:hover {
            background-color: var(--bg-tertiary);
        }
        .sidebar-card .event-date-box {
            background-color: var(--accent);
            color: white;
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            line-height: 1.2;
            min-width: 45px; /* Ensure consistent width */
        }
        .sidebar-card .event-date-box .month {
            display: block;
            font-size: 0.65rem;
            text-transform: uppercase;
        }
        .sidebar-card .event-date-box .day {
            display: block;
            font-size: 1.1rem;
            font-weight: 700;
        }
        .sidebar-card .event-details {
            flex: 1;
        }
        .sidebar-card .event-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 2px;
        }
        .sidebar-card .event-location,
        .sidebar-card .event-attendees {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="page-container">
        <div class="container">
            <aside class="sidebar left-sidebar">
                <div class="sidebar-card">
                    <div class="sidebar-content">
                        <a href="profile.php" class="sidebar-item">
                            <img src="<?php echo $avatar_url; ?>" alt="Profile" class="user-avatar-small">
                            <div class="sidebar-item-content">
                                <div class="sidebar-item-title"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div class="sidebar-item-subtitle">View your profile</div>
                            </div>
                        </a>

                        <a href="friends.php" class="sidebar-item">
                            <i class="fas fa-user-friends"></i>
                            <div class="sidebar-item-content">
                                <div class="sidebar-item-title">Friends</div>
                                <div class="sidebar-item-subtitle">See all friends</div>
                            </div>
                        </a>

                        <a href="events.php" class="sidebar-item">
                            <i class="fas fa-calendar-alt"></i>
                            <div class="sidebar-item-content">
                                <div class="sidebar-item-title">Events</div>
                                <div class="sidebar-item-subtitle">Upcoming events</div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <div class="sidebar-title">Trending Topics</div>
                    </div>
                    <div class="sidebar-content">
                        <a href="#" class="sidebar-item">
                            <i class="fas fa-fire"></i>
                            <div class="sidebar-item-content">
                                <div class="sidebar-item-title">#Technology</div>
                                <div class="sidebar-item-subtitle">3.2k posts today</div>
                            </div>
                        </a>
                        <a href="#" class="sidebar-item">
                            <i class="fas fa-fire"></i>
                            <div class="sidebar-item-content">
                                <div class="sidebar-item-title">#WebDevelopment</div>
                                <div class="sidebar-item-subtitle">1.8k posts today</div>
                            </div>
                        </a>
                        <a href="#" class="sidebar-item">
                            <i class="fas fa-fire"></i>
                            <div class="sidebar-item-content">
                                <div class="sidebar-item-title">#ArtificialIntelligence</div>
                                <div class="sidebar-item-subtitle">950 posts today</div>
                            </div>
                        </a>
                    </div>
                </div>
            </aside>

            <main class="main-content">
                <div class="post-creator">
                    <div class="post-input-container">
                        <img src="<?php echo $avatar_url; ?>" alt="Your profile" class="user-avatar">
                        <input type="text" placeholder="What's on your mind, <?php echo htmlspecialchars($user['username']); ?>?" class="post-input" id="post-input-trigger">
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
                            <div class="post-privacy-selector">
                                <button class="action-button" id="post-privacy-btn">
                                    <i class="fas fa-globe-americas"></i>
                                    <span>Public</span>
                                    <i class="fas fa-caret-down"></i>
                                </button>
                                <div class="privacy-dropdown" id="privacy-dropdown">
                                    <div class="privacy-option selected" data-privacy="public">
                                        <i class="fas fa-globe-americas"></i>
                                        <span>Public</span>
                                    </div>
                                    <div class="privacy-option" data-privacy="friends">
                                        <i class="fas fa-user-friends"></i>
                                        <span>Friends Only</span>
                                    </div>
                                    <div class="privacy-option" data-privacy="private">
                                        <i class="fas fa-lock"></i>
                                        <span>Only Me</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="post-submit" id="post-submit-btn" disabled>Post</button>
                    </div>
                </div>

                <div class="modal-overlay" id="create-post-modal">
                    <div class="modal">
                        <div class="modal-header">
                            <h3 class="modal-title">Create Post</h3>
                            <button class="modal-close" id="post-modal-close"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="modal-body">
                            <form id="post-form" method="post" action="ajax/create_post.php" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="privacy" id="post-privacy-value" value="public">

                                <div class="post-creator-header">
                                    <img src="<?php echo $avatar_url; ?>" alt="Your profile" class="user-avatar">
                                    <div>
                                        <div class="post-author"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <div class="post-privacy-display">
                                            <i class="fas fa-globe-americas"></i>
                                            <span id="privacy-text">Public</span>
                                        </div>
                                    </div>
                                </div>

                                <textarea name="content" id="post-content" placeholder="What's on your mind, <?php echo htmlspecialchars($user['username']); ?>?" rows="5" class="post-textarea"></textarea>

                                <div id="post-image-preview" class="post-image-preview" style="display: none;">
                                    <img id="image-preview" src="#" alt="Preview">
                                    <button type="button" id="remove-image" class="remove-image-btn">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>

                                <div id="post-location-container" class="post-location" style="display: none;">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span id="location-text" class="post-location-text">Add your location</span>
                                    <button type="button" id="remove-location" class="post-location-remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>

                                <input type="hidden" name="location_lat" id="location-lat">
                                <input type="hidden" name="location_lng" id="location-lng">
                                <input type="hidden" name="location_name" id="location-name">

                                <div class="post-form-actions">
                                    <div class="post-form-label">Add to your post</div>
                                    <div class="post-form-buttons">
                                        <div class="file-input-container">
                                            <input type="file" name="post_image" id="post-image-input" class="file-input" accept="image/*">
                                            <button type="button" class="action-button" id="post-image-upload-button">
                                                <i class="fas fa-image"></i>
                                            </button>
                                        </div>
                                        <button type="button" id="location-btn" class="action-button">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="post-submit" class="post-submit" disabled>Post</button>
                        </div>
                    </div>
                </div>

                <div class="modal-overlay" id="location-modal">
                    <div class="modal">
                        <div class="modal-header">
                            <h3 class="modal-title">Add Location</h3>
                            <button class="modal-close" id="location-modal-close"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="modal-body">
                            <div class="location-search">
                                <div class="form-group">
                                    <input type="text" id="location-search-input" placeholder="Search for a location..." class="location-search-input">
                                </div>
                                <div id="location-search-results" class="location-search-results"></div>
                            </div>
                            <div id="location-map" class="location-map"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="location-cancel" class="btn-secondary">Cancel</button>
                            <button type="button" id="location-confirm" class="btn-primary">Confirm Location</button>
                        </div>
                    </div>
                </div>

                <div class="posts-feed">
                    <?php if ($posts_result->num_rows === 0): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-newspaper"></i>
                            </div>
                            <h3 class="empty-state-title">No posts yet</h3>
                            <p class="empty-state-message">Start following people or create your first post to see content here.</p>
                        </div>
                    <?php else: ?>
                        <?php while ($post = $posts_result->fetch_assoc()): ?>
                            <div class="post-card">
                                <div class="post-header">
                                    <img src="<?php echo !empty($post['profile_picture']) ? htmlspecialchars($post['profile_picture']) : 'assets/default-avatar.png'; ?>" alt="<?php echo htmlspecialchars($post['username']); ?>" class="user-avatar">
                                    <div class="post-info">
                                        <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="post-author"><?php echo htmlspecialchars($post['username']); ?></a>
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
                                        <?php if ($post['user_id'] === $_SESSION['user_id']): ?>
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

                                <?php
                                // Get likes and comments count
                                $conn = getDbConnection();

                                $likes_stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
                                $likes_stmt->bind_param("i", $post['id']);
                                $likes_stmt->execute();
                                $likes_result = $likes_stmt->get_result();
                                $likes_count = $likes_result->fetch_assoc()['count'];

                                $comments_stmt = $conn->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
                                $comments_stmt->bind_param("i", $post['id']);
                                $comments_stmt->execute();
                                $comments_result = $comments_stmt->get_result();
                                $comments_count = $comments_result->fetch_assoc()['count'];

                                // Check if user liked this post
                                $user_liked_stmt = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
                                $user_liked_stmt->bind_param("ii", $post['id'], $_SESSION['user_id']);
                                $user_liked_stmt->execute();
                                $user_liked = $user_liked_stmt->get_result()->num_rows > 0;

                                $conn->close();
                                ?>

                                <div class="post-stats">
                                    <div class="post-stat-group">
                                        <button class="stat-button like-button <?php echo $user_liked ? 'active' : ''; ?>" data-post-id="<?php echo $post['id']; ?>">
                                            <i class="fas fa-heart"></i>
                                            <span class="stat-count"><?php echo $likes_count; ?></span>
                                        </button>
                                        <button class="stat-button comment-button" data-post-id="<?php echo $post['id']; ?>">
                                            <i class="fas fa-comment"></i>
                                            <span class="stat-count"><?php echo $comments_count; ?></span>
                                        </button>
                                    </div>
                                    <button class="stat-button share-button" data-post-id="<?php echo $post['id']; ?>">
                                        <i class="fas fa-share"></i>
                                        <span>Share</span>
                                    </button>
                                </div>

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
                                        <img src="<?php echo $avatar_url; ?>" alt="Your profile" class="user-avatar-small">
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
            </main>

            <aside class="sidebar right-sidebar">
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <div class="sidebar-title">People You May Know</div>
                    </div>
                    <div class="sidebar-content">
                        <div class="friend-suggestions">
                            <?php while ($friend = $suggested_friends_result->fetch_assoc()): ?>
                                <div class="friend-item">
                                    <div class="friend-info">
                                        <img src="<?php echo !empty($friend['profile_picture']) ? htmlspecialchars($friend['profile_picture']) : 'assets/default-avatar.png'; ?>" alt="User" class="user-avatar-small">
                                        <div>
                                            <a href="profile.php?id=<?php echo $friend['id']; ?>" class="friend-name"><?php echo htmlspecialchars($friend['username']); ?></a>
                                            <p class="mutual-friends"><?php echo $friend['mutual_count']; ?> mutual friends</p>
                                        </div>
                                    </div>
                                    <button class="friend-button add-friend" data-user-id="<?php echo $friend['id']; ?>">Add</button>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <div class="sidebar-title">Upcoming Events</div>
                        <a href="events.php" class="view-all">View All</a>
                    </div>
                    <div class="sidebar-content">
                        <?php if ($upcoming_events_result->num_rows === 0): ?>
                            <p class="no-events">No upcoming events.</p>
                        <?php else: ?>
                            <?php while ($event = $upcoming_events_result->fetch_assoc()): ?>
                                <a href="events.php?id=<?php echo $event['id']; ?>" class="event-item">
                                    <div class="event-date-box">
                                        <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                        <span class="day"><?php echo date('j', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="event-details">
                                        <div class="event-name"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <?php if (!empty($event['location_name'])): ?>
                                            <div class="event-location"><?php echo htmlspecialchars($event['location_name']); ?></div>
                                        <?php endif; ?>
                                        <div class="event-attendees"><?php echo $event['going_count']; ?> people going</div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <div class="sidebar-title">Online Friends</div>
                    </div>
                    <div class="sidebar-content">
                        <div class="online-friend">
                            <div class="online-friend-avatar">
                                <img src="assets/default-avatar.png" alt="Friend" class="user-avatar-small">
                                <span class="online-indicator"></span>
                            </div>
                            <div class="online-friend-name">Alex Johnson</div>
                            <button class="message-button">
                                <i class="fas fa-comment"></i>
                            </button>
                        </div>
                        <div class="online-friend">
                            <div class="online-friend-avatar">
                                <img src="assets/default-avatar.png" alt="Friend" class="user-avatar-small">
                                <span class="online-indicator"></span>
                            </div>
                            <div class="online-friend-name">Taylor Smith</div>
                            <button class="message-button">
                                <i class="fas fa-comment"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <div class="modal-overlay" id="report-modal">
        <div class="modal modal-small">
            <div class="modal-header">
                <h3 class="modal-title">Report Post</h3>
                <button class="modal-close" id="report-modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="report-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="post_id" id="report-post-id">

                    <div class="form-group">
                        <label for="report-reason">Why are you reporting this post?</label>
                        <select name="reason" id="report-reason" class="form-control">
                            <option value="">Select a reason</option>
                            <option value="inappropriate">Inappropriate content</option>
                            <option value="spam">Spam</option>
                            <option value="harassment">Harassment</option>
                            <option value="false_information">False information</option>
                            <option value="violence">Violence</option>
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
                <button type="button" id="report-cancel" class="btn-secondary">Cancel</button>
                <button type="button" id="report-submit" class="btn-primary">Submit Report</button>
            </div>
        </div>
    </div>

    <div class="notification-container" id="notification-container"></div>

    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBx8JE03m1lq_Qw6MYa68VMmXCYgjUheuU&libraries=places" async defer></script>

    <script src="js/main.js"></script>
    <script src="js/posts.js"></script>
</body>
</html>