<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Verify CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

header('Content-Type: application/json');

// Handle different update actions
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update_profile_picture':
        if (isset($_FILES['profile_picture'])) {
            $file = $_FILES['profile_picture'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type']);
                exit();
            }
            
            $upload_dir = 'uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = uniqid() . '_' . basename($file['name']);
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $stmt->bind_param("si", $target_path, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'url' => $target_path]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database update failed']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            }
        }
        break;
        
    case 'update_bio':
        $data = json_decode(file_get_contents('php://input'), true);
        $bio = $data['bio'] ?? '';
        
        $stmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
        $stmt->bind_param("si", $bio, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update bio']);
        }
        $stmt->close();
        break;
        
    case 'update_photo_section':
        if (isset($_FILES['photos']) && isset($_POST['section'])) {
            $section = intval($_POST['section']);
            if ($section !== 1 && $section !== 2) {
                echo json_encode(['success' => false, 'message' => 'Invalid section']);
                exit();
            }
            
            $upload_dir = 'uploads/photo_sections/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $uploaded_photos = [];
            $files = $_FILES['photos'];
            
            foreach ($files['tmp_name'] as $key => $tmp_name) {
                $file_type = $files['type'][$key];
                if (!in_array($file_type, ['image/jpeg', 'image/png', 'image/gif'])) {
                    continue;
                }
                
                $filename = uniqid() . '_' . basename($files['name'][$key]);
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($tmp_name, $target_path)) {
                    $uploaded_photos[] = $target_path;
                }
            }
            
            if (!empty($uploaded_photos)) {
                $column = "photo_section" . $section;
                $photos_json = json_encode($uploaded_photos);
                
                $stmt = $conn->prepare("UPDATE users SET $column = ? WHERE id = ?");
                $stmt->bind_param("si", $photos_json, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'photos' => $uploaded_photos]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update photos']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'No valid photos uploaded']);
            }
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
