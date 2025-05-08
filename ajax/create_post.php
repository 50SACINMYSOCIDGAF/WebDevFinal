<?php
/**
 * Create Post AJAX Endpoint
 * Handles post creation with text, images, and location
 */
session_start();
require_once '../functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to create a post.']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !isValidCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit();
}

// Validate post content
if (!isset($_POST['content']) || trim($_POST['content']) === '') {
    echo json_encode(['success' => false, 'message' => 'Post content cannot be empty.']);
    exit();
}

// Get post data
$user_id = $_SESSION['user_id'];
$content = sanitize($_POST['content']);
$privacy = isset($_POST['privacy']) && in_array($_POST['privacy'], ['public', 'friends', 'private']) 
    ? $_POST['privacy'] 
    : 'public';

// Handle location data
$location_lat = isset($_POST['location_lat']) && !empty($_POST['location_lat']) ? floatval($_POST['location_lat']) : null;
$location_lng = isset($_POST['location_lng']) && !empty($_POST['location_lng']) ? floatval($_POST['location_lng']) : null;
$location_name = isset($_POST['location_name']) && !empty($_POST['location_name']) ? sanitize($_POST['location_name']) : null;

// Initialize image path
$image_path = null;

// Handle image upload if present
if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file = $_FILES['post_image'];
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload JPEG, PNG, or GIF images.']);
        exit();
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File size too large. Maximum size is 5MB.']);
        exit();
    }
    
    // Generate unique filename
    $upload_dir = '../uploads/posts/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = uniqid('post_') . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $upload_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $image_path = 'uploads/posts/' . $filename;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload image. Please try again.']);
        exit();
    }
}

// Insert post into database
$conn = getDbConnection();

$query = "INSERT INTO posts (user_id, content, image, location_lat, location_lng, location_name, privacy) 
          VALUES (?, ?, ?, ?, ?, ?, ?)";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("issddss", $user_id, $content, $image_path, $location_lat, $location_lng, $location_name, $privacy);

if ($stmt->execute()) {
    $post_id = $stmt->insert_id;
    
    // Create notification for friends if post is public or friends-only
    if ($privacy !== 'private') {
        // Get friend IDs
        $friends_query = "SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted'";
        $friends_stmt = $conn->prepare($friends_query);
        $friends_stmt->bind_param("i", $user_id);
        $friends_stmt->execute();
        $friends_result = $friends_stmt->get_result();
        
        while ($friend = $friends_result->fetch_assoc()) {
            $message = $_SESSION['username'] . " shared a new post";
            createNotification($friend['friend_id'], 'new_post', $message, $user_id, $post_id);
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Post created successfully', 
        'post_id' => $post_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create post: ' . $conn->error]);
}

$conn->close();
?>