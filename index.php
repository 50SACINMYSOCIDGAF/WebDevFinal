<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Simple function to get user avatar (you can expand this later)
function getUserAvatar($userId) {
    return "https://via.placeholder.com/40";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <h1 class="logo">ConnectHub</h1>
            <div id="welcome-message" class="welcome-message">
                <?php
                $welcome_messages = [
                    "Welcome back, " . htmlspecialchars($_SESSION['username']),
                    "Hi, " . htmlspecialchars($_SESSION['username']),
                    "Good to see you, " . htmlspecialchars($_SESSION['username']),
                    "Hello, " . htmlspecialchars($_SESSION['username'])
                ];
                echo $welcome_messages[array_rand($welcome_messages)];
                ?>
            </div>
            <div class="search-container">
                <input type="text" placeholder="Search..." class="search-input">
            </div>
        </div>
        <div class="nav-right">
            <button class="nav-button"><i class="fas fa-home"></i></button>
            <button class="nav-button"><i class="fas fa-user-friends"></i></button>
            <button class="nav-button"><i class="fas fa-bell"></i></button>
            <div class="user-menu">
                <button class="nav-button"><i class="fas fa-user-circle"></i></button>
                <div class="dropdown-menu">
                    <a href="profile.php">Profile</a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Left Sidebar -->
        <aside class="sidebar left-sidebar">
            <div class="sidebar-content">
                <div class="sidebar-item">
                    <i class="fas fa-user-circle"></i>
                    <span>Your Profile</span>
                </div>
                <div class="sidebar-item">
                    <i class="fas fa-users"></i>
                    <span>Friends</span>
                </div>
                <div class="sidebar-item">
                    <i class="fas fa-bookmark"></i>
                    <span>Saved</span>
                </div>
                <div class="sidebar-item">
                    <i class="fas fa-calendar"></i>
                    <span>Events</span>
                </div>
            </div>
        </aside>

        <!-- Main Feed -->
        <main class="main-content">
            <!-- Create Post -->
            <div class="post-card create-post">
                <div class="post-input-container">
                    <img src="https://via.placeholder.com/40" alt="User" class="user-avatar">
                    <input type="text" placeholder="What's on your mind?" class="post-input">
                </div>
                <div class="post-actions">
                    <button class="action-button">
                        <i class="fas fa-image"></i>
                        <span>Photo/Video</span>
                    </button>
                    <button class="action-button">
                        <i class="fas fa-smile"></i>
                        <span>Feeling/Activity</span>
                    </button>
                    <button class="action-button">
                        <i class="fas fa-video"></i>
                        <span>Live Video</span>
                    </button>
                </div>
            </div>

            <!-- Sample Posts -->
            <div class="post-card">
                <div class="post-header">
                    <img src="https://via.placeholder.com/40" alt="User" class="user-avatar">
                    <div class="post-info">
                        <h3 class="post-author">John Doe</h3>
                        <p class="post-time">2 hours ago</p>
                    </div>
                </div>
                <p class="post-content">Just finished working on an amazing project! Can't wait to share more details soon. 🚀 #coding #webdev</p>
                <img src="https://via.placeholder.com/600x400" alt="Post image" class="post-image">
                <div class="post-stats">
                    <button class="stat-button">
                        <i class="fas fa-heart"></i>
                        <span>2.5k</span>
                    </button>
                    <button class="stat-button">
                        <i class="fas fa-comment"></i>
                        <span>482</span>
                    </button>
                    <button class="stat-button">
                        <i class="fas fa-share"></i>
                        <span>128</span>
                    </button>
                </div>
            </div>

            <div class="post-card">
                <div class="post-header">
                    <img src="https://via.placeholder.com/40" alt="User" class="user-avatar">
                    <div class="post-info">
                        <h3 class="post-author">Jane Smith</h3>
                        <p class="post-time">5 hours ago</p>
                    </div>
                </div>
                <p class="post-content">Beautiful sunset at the beach today! 🌅 #nature #peace</p>
                <img src="https://via.placeholder.com/600x400" alt="Post image" class="post-image">
                <div class="post-stats">
                    <button class="stat-button">
                        <i class="fas fa-heart"></i>
                        <span>1.8k</span>
                    </button>
                    <button class="stat-button">
                        <i class="fas fa-comment"></i>
                        <span>324</span>
                    </button>
                    <button class="stat-button">
                        <i class="fas fa-share"></i>
                        <span>95</span>
                    </button>
                </div>
            </div>
        </main>

        <!-- Right Sidebar -->
        <aside class="sidebar right-sidebar">
            <div class="sidebar-content">
                <h3 class="sidebar-title">Suggested Friends</h3>
                <div class="friend-suggestions">
                    <div class="friend-item">
                        <div class="friend-info">
                            <img src="https://via.placeholder.com/40" alt="User" class="user-avatar">
                            <div>
                                <h4 class="friend-name">Mike Johnson</h4>
                                <p class="mutual-friends">12 mutual friends</p>
                            </div>
                        </div>
                        <button class="add-friend">Add</button>
                    </div>
                    <div class="friend-item">
                        <div class="friend-info">
                            <img src="https://via.placeholder.com/40" alt="User" class="user-avatar">
                            <div>
                                <h4 class="friend-name">Sarah Wilson</h4>
                                <p class="mutual-friends">8 mutual friends</p>
                            </div>
                        </div>
                        <button class="add-friend">Add</button>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>