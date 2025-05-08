<?php
/**
 * Navigation Bar Component
 * Site-wide navigation with user controls
 */
require_once __DIR__ . '/../functions.php';

// Get counts for notifications
$unread_messages = isLoggedIn() ? countUnreadMessages($_SESSION['user_id']) : 0;
$friend_requests = isLoggedIn() ? countPendingFriendRequests($_SESSION['user_id']) : 0;

// Get user avatar
$user_avatar = isLoggedIn() ? getUserAvatar($_SESSION['user_id']) : '';
?>

<nav class="navbar">
    <div class="nav-left">
        <a href="index.php" class="logo">ConnectHub</a>
        
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" placeholder="Search people, posts, and more..." class="search-input" id="search-input">
            <div class="search-results" id="search-results"></div>
        </div>
    </div>
    
    <div class="nav-center">
        <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span class="nav-text">Home</span>
        </a>
        <a href="friends.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'friends.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-friends"></i>
            <span class="nav-text">Friends</span>
            <?php if ($friend_requests > 0): ?>
                <span class="notification-badge"><?php echo $friend_requests; ?></span>
            <?php endif; ?>
        </a>
        <a href="messages.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>">
            <i class="fas fa-comment-alt"></i>
            <span class="nav-text">Messages</span>
            <?php if ($unread_messages > 0): ?>
                <span class="notification-badge"><?php echo $unread_messages; ?></span>
            <?php endif; ?>
        </a>
        <a href="notifications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <span class="nav-text">Notifications</span>
        </a>
    </div>
    
    <div class="nav-right">
        <div class="user-menu">
            <div class="user-menu-trigger">
                <img src="<?php echo $user_avatar; ?>" alt="Profile" class="user-avatar-small">
                <span class="user-name"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?></span>
                <i class="fas fa-chevron-down"></i>
            </div>
            
            <div class="dropdown-menu">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="customize.php" class="dropdown-item">
                    <i class="fas fa-palette"></i> Customize Profile
                </a>
                <a href="settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <?php if (isAdmin()): ?>
                <a href="admin/dashboard.php" class="dropdown-item">
                    <i class="fas fa-shield-alt"></i> Admin Dashboard
                </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
/**
 * Navbar functionality script
 * Handles dropdown menus, search, and navbar appearance
 */
document.addEventListener('DOMContentLoaded', function() {
    // Navbar shadow and hide/show on scroll
    const navbar = document.querySelector('.navbar');
    let lastScroll = 0;
    
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        // Add/remove shadow
        if (currentScroll > 0) {
            navbar.classList.add('navbar-shadow');
        } else {
            navbar.classList.remove('navbar-shadow');
        }
        
        // Auto-hide navbar on scroll down, show on scroll up
        if (currentScroll > lastScroll && currentScroll > 100) {
            navbar.classList.add('navbar-hidden');
        } else {
            navbar.classList.remove('navbar-hidden');
        }
        
        lastScroll = currentScroll;
    });
    
    // Toggle user dropdown menu
    const userMenuTrigger = document.querySelector('.user-menu-trigger');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    
    if (userMenuTrigger) {
        userMenuTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            dropdownMenu.classList.remove('show');
        });
    }
    
    // Live search functionality
    const searchInput = document.getElementById('search-input');
    const searchResults = document.getElementById('search-results');
    let searchTimeout;
    
    if (searchInput) {
        searchInput.addEventListener('focus', function() {
            searchResults.style.display = 'block';
        });
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = this.value.trim();
                
                if (query.length >= 2) {
                    // AJAX request for search results
                    fetch('ajax/search.php?q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                let resultsHtml = '';
                                data.forEach(item => {
                                    resultsHtml += `
                                        <a href="${item.link}" class="search-result-item">
                                            <img src="${item.image}" alt="${item.type}" class="search-result-image">
                                            <div class="search-result-info">
                                                <div class="search-result-name">${item.name}</div>
                                                <div class="search-result-meta">${item.meta}</div>
                                            </div>
                                        </a>
                                    `;
                                });
                                searchResults.innerHTML = resultsHtml;
                            } else {
                                searchResults.innerHTML = '<div class="no-results">No results found</div>';
                            }
                        })
                        .catch(error => {
                            console.error('Search error:', error);
                        });
                } else if (query.length === 0) {
                    searchResults.innerHTML = '';
                }
            }, 300);
        });
        
        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    }
});
</script>