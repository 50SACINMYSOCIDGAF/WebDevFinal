<?php
/**
 * User Settings Page
 * Allows users to change username, password, etc.
 */
session_start();
require_once 'functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get current user data
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

if (!$user) {
    // Should not happen if logged in, but good practice
    header("Location: logout.php");
    exit();
}

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();

// Variables for feedback messages
$usernameMessage = '';
$usernameMessageType = '';
$passwordMessage = '';
$passwordMessageType = '';

// Handle form submissions (We'll point forms to a separate handler or process here)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && isValidCSRFToken($_POST['csrf_token'])) {
    $action = $_POST['action'] ?? '';
    $conn = getDbConnection();

    if ($action === 'change_username') {
        $newUsername = sanitize($_POST['new_username']);
        $currentPassword = $_POST['current_password_username'];

        // --- Backend Logic for Username Change (See point 2 below) ---
        // 1. Verify current password
        if (password_verify($currentPassword, $user['password'])) {
            // 2. Check if new username is different
            if ($newUsername !== $user['username']) {
                // 3. Check if new username is available
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $checkStmt->bind_param("s", $newUsername);
                $checkStmt->execute();
                $result = $checkStmt->get_result();

                if ($result->num_rows === 0) {
                    // 4. Validate username format/length (optional, add if needed)
                    if (strlen($newUsername) >= 3 && strlen($newUsername) <= 50) {
                       // 5. Update username in DB
                        $updateStmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                        $updateStmt->bind_param("si", $newUsername, $user_id);
                        if ($updateStmt->execute()) {
                            // 6. Update session
                            $_SESSION['username'] = $newUsername;
                            $usernameMessage = 'Username updated successfully!';
                            $usernameMessageType = 'success';
                            $user['username'] = $newUsername; // Update local variable for display
                        } else {
                            $usernameMessage = 'Failed to update username. Please try again.';
                            $usernameMessageType = 'error';
                        }
                    } else {
                         $usernameMessage = 'Username must be between 3 and 50 characters.';
                         $usernameMessageType = 'error';
                    }
                } else {
                    $usernameMessage = 'Username already taken. Please choose another.';
                    $usernameMessageType = 'error';
                }
            } else {
                 $usernameMessage = 'New username cannot be the same as the current one.';
                 $usernameMessageType = 'error';
            }
        } else {
            $usernameMessage = 'Incorrect current password.';
            $usernameMessageType = 'error';
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // --- Backend Logic for Password Change (See point 2 below) ---
        // 1. Verify current password
        if (password_verify($currentPassword, $user['password'])) {
            // 2. Check if new passwords match
            if ($newPassword === $confirmPassword) {
                // 3. Check password strength
                if (isPasswordStrong($newPassword)) {
                    // 4. Hash new password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    // 5. Update password in DB
                    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $hashedPassword, $user_id);
                    if ($updateStmt->execute()) {
                        $passwordMessage = 'Password updated successfully!';
                        $passwordMessageType = 'success';
                         // Consider logging out other sessions or enhancing security here
                    } else {
                        $passwordMessage = 'Failed to update password. Please try again.';
                        $passwordMessageType = 'error';
                    }
                } else {
                    $passwordMessage = 'New password does not meet strength requirements.';
                    $passwordMessageType = 'error';
                }
            } else {
                $passwordMessage = 'New passwords do not match.';
                $passwordMessageType = 'error';
            }
        } else {
            $passwordMessage = 'Incorrect current password.';
            $passwordMessageType = 'error';
        }
    }
    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - ConnectHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Add some specific styles for settings page if needed */
        .settings-container {
            max-width: 800px;
            margin: 2rem auto;
        }
        .settings-section {
            margin-bottom: 2rem;
        }
        .settings-section h2 {
            margin-bottom: 1rem;
            font-size: 1.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem;
        }
        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="email"] {
            margin-bottom: 1rem; /* Add consistent spacing */
        }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            border: 1px solid transparent;
        }
        .alert.success {
            background-color: rgba(16, 185, 129, 0.1);
            border-color: #10b981;
            color: #065f46;
        }
        .alert.error {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #b91c1c;
        }
        .password-requirements { /* Reuse style from login */
            background: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
            padding: 10px;
            margin-top: -10px; /* Adjust spacing */
            margin-bottom: 15px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            border: 1px solid var(--border);
        }
        .password-requirements ul { list-style: none; padding: 0; }
        .password-requirements li { margin-bottom: 3px; }
        .password-requirements li.valid { color: var(--success); }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="page-container">
        <div class="settings-container">
            <h1>Account Settings</h1>

            <div class="settings-section card">
                <div class="card-header">
                    <h2 class="card-title">Change Username</h2>
                </div>
                <div class="card-body">
                    <?php if ($usernameMessage): ?>
                        <div class="alert <?php echo $usernameMessageType; ?>">
                            <?php echo $usernameMessage; ?>
                        </div>
                    <?php endif; ?>
                    <form action="settings.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="change_username">

                        <div class="form-group">
                            <label>Current Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" class="form-control" disabled>
                        </div>

                        <div class="form-group">
                            <label for="new_username">New Username</label>
                            <input type="text" id="new_username" name="new_username" class="form-control" required minlength="3" maxlength="50">
                        </div>

                        <div class="form-group">
                            <label for="current_password_username">Current Password</label>
                            <input type="password" id="current_password_username" name="current_password_username" class="form-control" required>
                            <small>Enter your current password to confirm changes.</small>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Username</button>
                    </form>
                </div>
            </div>

            <div class="settings-section card">
                 <div class="card-header">
                    <h2 class="card-title">Change Password</h2>
                </div>
                <div class="card-body">
                     <?php if ($passwordMessage): ?>
                        <div class="alert <?php echo $passwordMessageType; ?>">
                            <?php echo $passwordMessage; ?>
                        </div>
                    <?php endif; ?>
                    <form action="settings.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            </div>
                         <div class="password-requirements">
                            <p>Password must contain:</p>
                            <ul>
                                <li id="length">At least 8 characters</li>
                                <li id="uppercase">At least one uppercase letter</li>
                                <li id="lowercase">At least one lowercase letter</li>
                                <li id="number">At least one number</li>
                                <li id="special">At least one special character</li>
                            </ul>
                        </div>


                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>

            <div class="settings-section card">
                 <div class="card-header">
                    <h2 class="card-title">Profile & Appearance</h2>
                </div>
                 <div class="card-body">
                    <p>Customize how your profile looks to others.</p>
                    <a href="customize.php" class="btn btn-secondary">Customize Profile</a>
                    <p class="mt-2">Edit your bio, location, and other details.</p>
                     <a href="profile.php" class="btn btn-secondary">Edit Profile Details</a> </div>
            </div>

        </div>
    </div>

    <div class="notification-container" id="notification-container"></div>

    <script src="js/main.js"></script>
    <script>
        // Optional: Add JS for password strength indicator matching login page
        const passwordInput = document.getElementById('new_password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const requirements = {
                    length: document.getElementById('length'),
                    uppercase: document.getElementById('uppercase'),
                    lowercase: document.getElementById('lowercase'),
                    number: document.getElementById('number'),
                    special: document.getElementById('special')
                };

                // Check length
                if (password.length >= 8) {
                    requirements.length.classList.add('valid');
                    requirements.length.innerHTML = '✓ At least 8 characters';
                } else {
                    requirements.length.classList.remove('valid');
                    requirements.length.innerHTML = 'At least 8 characters';
                }
                // Check uppercase
                if (/[A-Z]/.test(password)) {
                    requirements.uppercase.classList.add('valid');
                    requirements.uppercase.innerHTML = '✓ At least one uppercase letter';
                } else {
                    requirements.uppercase.classList.remove('valid');
                    requirements.uppercase.innerHTML = 'At least one uppercase letter';
                }
                // Check lowercase
                if (/[a-z]/.test(password)) {
                    requirements.lowercase.classList.add('valid');
                    requirements.lowercase.innerHTML = '✓ At least one lowercase letter';
                } else {
                    requirements.lowercase.classList.remove('valid');
                    requirements.lowercase.innerHTML = 'At least one lowercase letter';
                }
                // Check number
                if (/[0-9]/.test(password)) {
                    requirements.number.classList.add('valid');
                    requirements.number.innerHTML = '✓ At least one number';
                } else {
                    requirements.number.classList.remove('valid');
                    requirements.number.innerHTML = 'At least one number';
                }
                // Check special character
                if (/[^A-Za-z0-9]/.test(password)) {
                    requirements.special.classList.add('valid');
                    requirements.special.innerHTML = '✓ At least one special character';
                } else {
                    requirements.special.classList.remove('valid');
                    requirements.special.innerHTML = 'At least one special character';
                }
            });
        }
    </script>
</body>
</html>