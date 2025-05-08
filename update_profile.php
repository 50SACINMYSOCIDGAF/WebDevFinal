<?php
/**
 * Profile Update Handler
 * Processes profile updates from profile page and customization page
 */
session_start();
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        // AJAX request
        echo json_encode(['success' => false, 'message' => 'You must be logged in to update your profile']);
        exit;
    } else {
        // Regular request
        header('Location: login.php');
        exit;
    }
}

// Validate CSRF token
$headers = getallheaders();
$token = isset($headers['X-CSRF-Token']) ? $headers['X-CSRF-Token'] : '';
$post_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if (!isValidCSRFToken($token) && !isValidCSRFToken($post_token)) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    } else {
        header('Location: profile.php');
        exit;
    }
}

// Get the action and user ID
$action = isset($_POST['action']) ? $_POST['action'] : '';
$user_id = $_SESSION['user_id'];
$conn = getDbConnection();

$response = ['success' => false, 'message' => 'Unknown action'];

switch ($action) {
    case 'update_profile_picture':
        // Handle profile picture update
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/profile/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_name = time() . '_' . basename($_FILES['profile_picture']['name']);
            $target_file = $upload_dir . $file_name;
            
            // Check file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['profile_picture']['type'], $allowed_types)) {
                // Upload file
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                    // Update database
                    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->bind_param("si", $target_file, $user_id);
                    
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Profile picture updated successfully', 'url' => $target_file];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to update profile picture in database'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Failed to upload profile picture'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid file type. Please upload a JPEG, PNG, or GIF image'];
            }
        } else {
            $response = ['success' => false, 'message' => 'No profile picture uploaded or upload error'];
        }
        break;
        
    case 'update_cover_photo':
        // Handle cover photo update
        if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/covers/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_name = time() . '_' . basename($_FILES['cover_photo']['name']);
            $target_file = $upload_dir . $file_name;
            
            // Check file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['cover_photo']['type'], $allowed_types)) {
                // Upload file
                if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $target_file)) {
                    // Update database
                    $stmt = $conn->prepare("UPDATE users SET cover_photo = ? WHERE id = ?");
                    $stmt->bind_param("si", $target_file, $user_id);
                    
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Cover photo updated successfully', 'url' => $target_file];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to update cover photo in database'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Failed to upload cover photo'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid file type. Please upload a JPEG, PNG, or GIF image'];
            }
        } else {
            $response = ['success' => false, 'message' => 'No cover photo uploaded or upload error'];
        }
        break;
        
    case 'update_bio':
        // Handle bio update
        if (isset($_POST['bio'])) {
            $bio = sanitize($_POST['bio']);
            
            // Update database
            $stmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
            $stmt->bind_param("si", $bio, $user_id);
            
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Bio updated successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update bio'];
            }
        } else {
            $response = ['success' => false, 'message' => 'No bio data provided'];
        }
        break;
        
    case 'update_profile_details':
        // Handle profile details update (name, location, etc.)
        $fields = [
            'username' => isset($_POST['username']) ? sanitize($_POST['username']) : null,
            'email' => isset($_POST['email']) ? sanitize($_POST['email']) : null,
            'location' => isset($_POST['location']) ? sanitize($_POST['location']) : null,
            'birthdate' => isset($_POST['birthdate']) ? sanitize($_POST['birthdate']) : null,
            'job_title' => isset($_POST['job_title']) ? sanitize($_POST['job_title']) : null,
            'workplace' => isset($_POST['workplace']) ? sanitize($_POST['workplace']) : null,
            'education' => isset($_POST['education']) ? sanitize($_POST['education']) : null,
            'website' => isset($_POST['website']) ? sanitize($_POST['website']) : null
        ];
        
        // Remove null values
        $fields = array_filter($fields, function($value) {
            return $value !== null;
        });
        
        if (empty($fields)) {
            $response = ['success' => false, 'message' => 'No profile data provided'];
            break;
        }
        
        // Build query
        $query = "UPDATE users SET ";
        $params = [];
        $types = "";
        
        foreach ($fields as $field => $value) {
            $query .= "$field = ?, ";
            $params[] = $value;
            $types .= "s";
        }
        
        // Remove trailing comma and space
        $query = rtrim($query, ", ");
        $query .= " WHERE id = ?";
        $params[] = $user_id;
        $types .= "i";
        
        // Update database
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Profile details updated successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Failed to update profile details'];
        }
        break;
        
    case 'update_photo_section':
        if (isset($_FILES['photos']) && isset($_POST['section'])) {
            $section = intval($_POST['section']);
            if ($section !== 1 && $section !== 2) {
                $response = ['success' => false, 'message' => 'Invalid section'];
                break;
            }
            
            $upload_dir = 'uploads/photo_sections/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
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
                $stmt->bind_param("si", $photos_json, $user_id);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Photos updated successfully', 'photos' => $uploaded_photos];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to update photos'];
                }
            } else {
                $response = ['success' => false, 'message' => 'No valid photos uploaded'];
            }
        }
        break;
}

// Return JSON response for AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    // Redirect for regular form submissions
    if ($response['success']) {
        header('Location: profile.php?success=' . urlencode($response['message']));
    } else {
        header('Location: profile.php?error=' . urlencode($response['message']));
    }
}

$conn->close();
?>