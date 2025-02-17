<?php
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
    
    // Create users table
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'blocked') DEFAULT 'active',
            is_admin BOOLEAN DEFAULT FALSE
        )
    ");
    
    $conn->close();
} catch (Exception $e) {
    die("Database setup failed: " . $e->getMessage());
}
?>