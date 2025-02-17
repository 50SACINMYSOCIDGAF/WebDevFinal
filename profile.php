<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get profile user ID (either from URL or default to logged-in user)
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];

// Get user data
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT id, username, bio, profile_picture, photo_section1, photo_section2 FROM users WHERE id = ?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: index.php");
    exit();
}

// Get user's posts
$posts_stmt = $conn->prepare("SELECT * FROM s4402739_posts WHERE user_id = ? ORDER BY created_at DESC");
$posts_stmt->bind_param("i", $profile_id);
$posts_stmt->execute();
$posts = $posts_stmt->get_result();

$conn->close();

// Check if it's the user's own profile
$isOwnProfile = $_SESSION['user_id'] === $profile_id;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?>'s Profile - ConnectHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr 300px;
            gap: 2rem;
            padding: 2rem;
            margin-top: 4rem;
        }

        .profile-section {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: block;
            object-fit: cover;
            cursor: pointer;
        }

        .photo-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .photo-section img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
        }

        .edit-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .profile-section:hover .edit-overlay {
            opacity: 1;
        }

        .bio-section {
            white-space: pre-wrap;
            cursor: pointer;
        }

        .posts-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .edit-button {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .edit-button:hover {
            background: var(--accent-hover);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="profile-grid">
        <!-- Left Column: Profile Info -->
        <div class="left-column">
            <div class="profile-section" id="profile-picture-section">
                <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'assets/default-avatar.png'); ?>" 
                     alt="Profile Picture" 
                     class="profile-picture"
                     <?php if ($isOwnProfile) echo 'onclick="editProfilePicture()"'; ?>>
                <?php if ($isOwnProfile): ?>
                    <div class="edit-overlay">
                        <button class="edit-button">Change Picture</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-section bio-section" id="bio-section">
                <h2>Bio</h2>
                <p <?php if ($isOwnProfile) echo 'onclick="editBio()"'; ?>>
                    <?php echo htmlspecialchars($user['bio'] ?? 'No bio yet...'); ?>
                </p>
                <?php if ($isOwnProfile): ?>
                    <div class="edit-overlay">
                        <button class="edit-button">Edit Bio</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Middle Column: Photo Sections -->
        <div class="middle-column">
            <div class="profile-section">
                <h2>Photo Section 1</h2>
                <div class="photo-section" id="photo-section-1">
                    <?php 
                    $photos1 = json_decode($user['photo_section1'] ?? '[]', true);
                    foreach ($photos1 as $photo): ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="Photo">
                    <?php endforeach; ?>
                    <?php if ($isOwnProfile): ?>
                        <div class="edit-overlay">
                            <button class="edit-button" onclick="editPhotoSection(1)">Edit Photos</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-section">
                <h2>Photo Section 2</h2>
                <div class="photo-section" id="photo-section-2">
                    <?php 
                    $photos2 = json_decode($user['photo_section2'] ?? '[]', true);
                    foreach ($photos2 as $photo): ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="Photo">
                    <?php endforeach; ?>
                    <?php if ($isOwnProfile): ?>
                        <div class="edit-overlay">
                            <button class="edit-button" onclick="editPhotoSection(2)">Edit Photos</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Posts -->
        <div class="right-column">
            <div class="profile-section posts-section">
                <h2>Posts</h2>
                <?php while ($post = $posts->fetch_assoc()): ?>
                    <div class="post-card">
                        <div class="post-header">
                            <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'assets/default-avatar.png'); ?>" 
                                 alt="User" 
                                 class="user-avatar">
                            <div class="post-info">
                                <h3 class="post-author"><?php echo htmlspecialchars($user['username']); ?></h3>
                                <p class="post-time"><?php echo date('F j, Y', strtotime($post['created_at'])); ?></p>
                            </div>
                        </div>
                        <p class="post-content"><?php echo htmlspecialchars($post['content']); ?></p>
                        <?php if ($post['image']): ?>
                            <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Post image" class="post-image">
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <script src="profile.js"></script>
</body>
</html>
