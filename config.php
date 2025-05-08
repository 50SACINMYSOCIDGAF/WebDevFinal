<?php
/**
 * Database configuration and table initialization
 * Creates all necessary tables for the social media platform
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'noah');
define('DB_PASS', 'Repave7-Starting7-Deskbound2-Flail1-Viscous0');
define('DB_NAME', 's4402739_main');

// Create the users table if it doesn't exist
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    // Create database if it doesn't exist
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $conn->select_db(DB_NAME);
    
    // Create users table with enhanced profile customization
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            bio TEXT,
            profile_picture VARCHAR(255),
            cover_photo VARCHAR(255),
            theme_color VARCHAR(20) DEFAULT '#4f46e5',
            font_preference VARCHAR(50) DEFAULT 'System Default',
            layout_preference VARCHAR(20) DEFAULT 'standard',
            created_at DATETIME NULL,
            status ENUM('active', 'blocked', 'suspended') DEFAULT 'active',
            block_reason TEXT,
            block_expiry DATETIME,
            is_admin BOOLEAN DEFAULT FALSE,
            last_login DATETIME
        )
    ");
    
    // Create posts table with location support
    $conn->query("
        CREATE TABLE IF NOT EXISTS posts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            image VARCHAR(255),
            location_lat DECIMAL(10, 8),
            location_lng DECIMAL(11, 8),
            location_name VARCHAR(255),
            privacy ENUM('public', 'friends', 'private') DEFAULT 'public',
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Create comments table
    $conn->query("
        CREATE TABLE IF NOT EXISTS comments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME NULL,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Create likes table
    $conn->query("
        CREATE TABLE IF NOT EXISTS likes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            post_id INT,
            comment_id INT,
            user_id INT NOT NULL,
            created_at DATETIME NULL,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CHECK (post_id IS NOT NULL OR comment_id IS NOT NULL)
        )
    ");
    
    // Create messages table
    $conn->query("
        CREATE TABLE IF NOT EXISTS messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            content TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at DATETIME NULL,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Create friends table
    $conn->query("
        CREATE TABLE IF NOT EXISTS friends (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            friend_id INT NOT NULL,
            status ENUM('pending', 'accepted', 'rejected', 'blocked') NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user_id, friend_id)
        )
    ");
    
    // Create reports table
    $conn->query("
        CREATE TABLE IF NOT EXISTS reports (
            id INT PRIMARY KEY AUTO_INCREMENT,
            reporter_id INT NOT NULL,
            reported_user_id INT,
            post_id INT,
            comment_id INT,
            reason TEXT NOT NULL,
            status ENUM('pending', 'reviewed', 'actioned', 'dismissed') DEFAULT 'pending',
            admin_notes TEXT,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            CHECK (reported_user_id IS NOT NULL OR post_id IS NOT NULL OR comment_id IS NOT NULL)
        )
    ");
    
    // Create user_customization table for theme and layout preferences
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_customization (
            user_id INT PRIMARY KEY,
            background_image VARCHAR(255),
            background_color VARCHAR(20),
            text_color VARCHAR(20),
            link_color VARCHAR(20),
            custom_css TEXT,
            music_url VARCHAR(255),
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Create notifications table
    $conn->query("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            from_user_id INT,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            content_id INT,
            is_read BOOLEAN DEFAULT FALSE,
            created_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    // Create saved_posts table
    $conn->query("
        CREATE TABLE IF NOT EXISTS saved_posts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            post_id INT NOT NULL,
            saved_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            UNIQUE(user_id, post_id)
        )
    ");
    
    // We'll manually handle the timestamps in code instead of using triggers
    // to maintain compatibility with older MariaDB versions
    
    $conn->close();
} catch (Exception $e) {
    die("Database setup failed: " . $e->getMessage());
}
?>