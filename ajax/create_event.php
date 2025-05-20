<?php
/**
 * AJAX endpoint for creating new events.
 * Handles event data submission, image upload, and database insertion.
 */
session_start();
require_once '../functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to create an event.']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !isValidCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit();
}

// Validate required event data
if (!isset($_POST['title']) || trim($_POST['title']) === '' ||
    !isset($_POST['event_date']) || trim($_POST['event_date']) === '') {
    echo json_encode(['success' => false, 'message' => 'Event title and date are required.']);
    exit();
}

// Get event data
$user_id = $_SESSION['user_id'];
$title = sanitize($_POST['title']);
$description = isset($_POST['description']) ? sanitize($_POST['description']) : null;
$event_date = sanitize($_POST['event_date']);
$event_time = isset($_POST['event_time']) && !empty($_POST['event_time']) ? sanitize($_POST['event_time']) : null;
$location_name = isset($_POST['location_name']) ? sanitize($_POST['location_name']) : null;
$location_lat = isset($_POST['location_lat']) && !empty($_POST['location_lat']) ? floatval($_POST['location_lat']) : null;
$location_lng = isset($_POST['location_lng']) && !empty($_POST['location_lng']) ? floatval($_POST['location_lng']) : null;
$privacy = isset($_POST['privacy']) && in_array($_POST['privacy'], ['public', 'friends', 'private'])
    ? $_POST['privacy']
    : 'public';

// Initialize image path
$image_path = null;

// Handle image upload if present
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    $file = $_FILES['image'];

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
    $upload_dir = '../uploads/events/';

    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = uniqid('event_') . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $upload_path = $upload_dir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $image_path = 'uploads/events/' . $filename;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload event image. Please try again.']);
        exit();
    }
}

// Insert event into database
$conn = getDbConnection();

$query = "INSERT INTO events (user_id, title, description, event_date, event_time, location_name, location_lat, location_lng, privacy, created_at, updated_at, image)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)";

$stmt = $conn->prepare($query);
$stmt->bind_param("isssssddss",
    $user_id,
    $title,
    $description,
    $event_date,
    $event_time,
    $location_name,
    $location_lat,
    $location_lng,
    $privacy,
    $image_path
);

if ($stmt->execute()) {
    $event_id = $stmt->insert_id;

    // Optionally, automatically mark the creator as "going"
    $attendee_stmt = $conn->prepare("INSERT INTO event_attendees (event_id, user_id, status, created_at) VALUES (?, ?, 'going', NOW())");
    $attendee_stmt->bind_param("ii", $event_id, $user_id);
    $attendee_stmt->execute();

    // Create notification for friends if event is public or friends-only
    if ($privacy !== 'private') {
        // Get friend IDs
        $friends_query = "SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted'";
        $friends_stmt = $conn->prepare($friends_query);
        $friends_stmt->bind_param("i", $user_id);
        $friends_stmt->execute();
        $friends_result = $friends_stmt->get_result();

        while ($friend = $friends_result->fetch_assoc()) {
            $message = $_SESSION['username'] . " created a new event: " . $title;
            createNotification($friend['friend_id'], 'new_event', $message, $user_id, $event_id);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Event created successfully!',
        'event_id' => $event_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create event: ' . $conn->error]);
}

$conn->close();
?>
