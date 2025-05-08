<?php
/**
 * Login and Registration Page
 * Handles user authentication and new account creation
 */
session_start();
require_once 'functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// Generate CSRF token for form security
$csrf_token = generateCSRFToken();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDbConnection();
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !isValidCSRFToken($_POST['csrf_token'])) {
        $error = "Security token validation failed. Please try again.";
    } else {
        if (isset($_POST['action'])) {
            // Handle registration form
            if ($_POST['action'] === 'register') {
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Validate username length
                if (strlen($username) < 3 || strlen($username) > 30) {
                    $error = "Username must be between 3 and 30 characters.";
                }
                // Validate email
                elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Please enter a valid email address.";
                }
                // Check if passwords match
                elseif ($password !== $confirm_password) {
                    $error = "Passwords do not match.";
                }
                // Validate password strength
                elseif (!isPasswordStrong($password)) {
                    $error = "Password must be at least 8 characters and contain uppercase, lowercase, numbers, and special characters.";
                } 
                else {
                    // Check if username or email already exists
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $check_stmt->bind_param("ss", $username, $email);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $error = "Username or email already exists.";
                    } else {
                        // Hash password and create new user
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                        $stmt->bind_param("sss", $username, $email, $hashedPassword);
                        
                        if ($stmt->execute()) {
                            $user_id = $stmt->insert_id;
                            
                            // Create default user customization record
                            $conn->query("INSERT INTO user_customization (user_id) VALUES ($user_id)");
                            
                            // Log the user in
                            $_SESSION['user_id'] = $user_id;
                            $_SESSION['username'] = $username;
                            
                            // Record login time
                            $login_time = date("Y-m-d H:i:s");
                            $update_stmt = $conn->prepare("UPDATE users SET last_login = ? WHERE id = ?");
                            $update_stmt->bind_param("si", $login_time, $user_id);
                            $update_stmt->execute();
                            
                            header("Location: index.php");
                            exit();
                        } else {
                            $error = "Registration failed: " . $conn->error;
                        }
                    }
                }
            } 
            // Handle login form
            elseif ($_POST['action'] === 'login') {
                $username = sanitize($_POST['username']);
                $password = $_POST['password'];
                $remember = isset($_POST['remember']) ? true : false;
                
                // Check if username exists and password is correct
                $stmt = $conn->prepare("SELECT id, username, password, status FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($user = $result->fetch_assoc()) {
                    if ($user['status'] === 'blocked' || $user['status'] === 'suspended') {
                        // Check if block has expired
                        $check_block = $conn->prepare("SELECT block_expiry FROM users WHERE id = ?");
                        $check_block->bind_param("i", $user['id']);
                        $check_block->execute();
                        $block_result = $check_block->get_result();
                        $block_data = $block_result->fetch_assoc();
                        
                        if ($block_data['block_expiry'] && strtotime($block_data['block_expiry']) < time()) {
                            // Unblock user if block period has expired
                            $unblock = $conn->prepare("UPDATE users SET status = 'active', block_reason = NULL, block_expiry = NULL WHERE id = ?");
                            $unblock->bind_param("i", $user['id']);
                            $unblock->execute();
                        } else {
                            $error = "Your account has been temporarily suspended. Please contact support for more information.";
                            $stmt->close();
                            $conn->close();
                            $loginFailed = true;
                        }
                    }
                    
                    if (!isset($loginFailed) && password_verify($password, $user['password'])) {
                        // Successful login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        
                        // Update last login time
                        $login_time = date("Y-m-d H:i:s");
                        $update_stmt = $conn->prepare("UPDATE users SET last_login = ? WHERE id = ?");
                        $update_stmt->bind_param("si", $login_time, $user['id']);
                        $update_stmt->execute();
                        
                        // Set remember-me cookie if requested
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $expires = time() + (30 * 24 * 60 * 60); // 30 days
                            
                            // Store token in database
                            $remember_stmt = $conn->prepare("
                                CREATE TABLE IF NOT EXISTS remember_tokens (
                                    id INT PRIMARY KEY AUTO_INCREMENT,
                                    user_id INT NOT NULL,
                                    token VARCHAR(255) NOT NULL,
                                    expires DATETIME NOT NULL,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                                )
                            ");
                            $remember_stmt->execute();
                            
                            $expiry_date = date('Y-m-d H:i:s', $expires);
                            $remember_stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)");
                            $remember_stmt->bind_param("iss", $user['id'], $token, $expiry_date);
                            $remember_stmt->execute();
                            
                            // Set cookie
                            setcookie('remember_token', $token, $expires, '/');
                        }
                        
                        header("Location: index.php");
                        exit();
                    } elseif (!isset($loginFailed)) {
                        $error = "Invalid username or password.";
                    }
                } else {
                    $error = "Invalid username or password.";
                }
                $stmt->close();
            }
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
    <title>ConnectHub - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --dark-blue: #0a1930;
            --light-blue: #1e3a8a;
            --lighter-blue: #3b82f6;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --accent-color: #4f46e5;
            --success-color: #10B981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow-x: hidden;
        }

        .background-circles {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
        }

        .circle-1 {
            width: 600px;
            height: 600px;
            background: var(--dark-blue);
            top: -200px;
            right: -100px;
        }

        .circle-2 {
            width: 500px;
            height: 500px;
            background: var(--light-blue);
            top: -150px;
            right: -50px;
            filter: blur(60px);
        }

        .circle-3 {
            width: 600px;
            height: 600px;
            background: var(--dark-blue);
            bottom: -200px;
            left: -100px;
        }

        .circle-4 {
            width: 500px;
            height: 500px;
            background: var(--light-blue);
            bottom: -150px;
            left: -50px;
            filter: blur(60px);
        }

        .container {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            width: 450px;
            z-index: 1;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: white;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(79, 70, 229, 0.5);
        }

        .logo p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1em;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group i {
            position: absolute;
            color: rgba(255, 255, 255, 0.5);
            left: 12px;
            top: 14px;
            font-size: 18px;
        }

        input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--accent-color);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25);
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 12px;
            color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            font-size: 18px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .checkbox-group input {
            width: auto;
            margin-right: 10px;
            accent-color: var(--accent-color);
        }

        .checkbox-group label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: var(--accent-color);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        button:hover {
            background: #403bc2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        button::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        button:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% { transform: scale(0, 0); opacity: 0.5; }
            100% { transform: scale(50, 50); opacity: 0; }
        }

        .toggle-form {
            color: var(--accent-color);
            text-align: center;
            margin-top: 20px;
            cursor: pointer;
            font-size: 15px;
            transition: color 0.3s;
        }

        .toggle-form:hover {
            color: white;
            text-decoration: underline;
        }

        .error, .success {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .error {
            background-color: rgba(239, 68, 68, 0.2);
            border-left: 3px solid var(--error-color);
            color: #fca5a5;
        }

        .success {
            background-color: rgba(16, 185, 129, 0.2);
            border-left: 3px solid var(--success-color);
            color: #6ee7b7;
        }

        .password-requirements {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
            padding: 10px;
            margin-top: 5px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .password-requirements ul {
            margin-left: 20px;
        }

        .password-requirements li {
            margin-bottom: 3px;
        }

        .password-requirements li.valid {
            color: var(--success-color);
        }

        .register-form {
            display: none;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
        }
        
        .divider::before {
            margin-right: 10px;
        }
        
        .divider::after {
            margin-left: 10px;
        }
        
        .social-login {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .social-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .social-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .social-btn i {
            margin-right: 8px;
            font-size: 18px;
        }
        
        .forgot-password {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .forgot-password a {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .forgot-password a:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="background-circles">
        <div class="circle circle-1"></div>
        <div class="circle circle-2"></div>
        <div class="circle circle-3"></div>
        <div class="circle circle-4"></div>
    </div>

    <div class="container">
        <div class="logo">
            <h1>ConnectHub</h1>
            <p>Connect with friends and the world around you</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <form class="login-form" method="POST" autocomplete="off">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Username" required>
            </div>
            
            <div class="form-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="login-password" placeholder="Password" required>
                <i class="fas fa-eye password-toggle" id="login-password-toggle"></i>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me for 30 days</label>
            </div>
            
            <div class="forgot-password">
                <a href="forgot_password.php">Forgot password?</a>
            </div>
            
            <button type="submit">Sign In</button>
            
            <div class="divider">or continue with</div>
            
            <div class="social-login">
                <div class="social-btn">
                    <i class="fab fa-google"></i> Google
                </div>
                <div class="social-btn">
                    <i class="fab fa-facebook-f"></i> Facebook
                </div>
            </div>
            
            <div class="toggle-form" onclick="toggleForms()">Don't have an account? Register</div>
        </form>

        <!-- Registration Form -->
        <form class="register-form" method="POST" autocomplete="off">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" id="register-username" placeholder="Username" required minlength="3" maxlength="30">
            </div>
            
            <div class="form-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" required>
            </div>
            
            <div class="form-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="register-password" placeholder="Password" required minlength="8">
                <i class="fas fa-eye password-toggle" id="register-password-toggle"></i>
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
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm_password" id="confirm-password" placeholder="Confirm Password" required>
                <i class="fas fa-eye password-toggle" id="confirm-password-toggle"></i>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="#" style="color: var(--accent-color);">Terms of Service</a> and <a href="#" style="color: var(--accent-color);">Privacy Policy</a></label>
            </div>
            
            <button type="submit">Create Account</button>
            
            <div class="divider">or register with</div>
            
            <div class="social-login">
                <div class="social-btn">
                    <i class="fab fa-google"></i> Google
                </div>
                <div class="social-btn">
                    <i class="fab fa-facebook-f"></i> Facebook
                </div>
            </div>
            
            <div class="toggle-form" onclick="toggleForms()">Already have an account? Login</div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle between login and registration forms
            window.toggleForms = function() {
                const loginForm = document.querySelector('.login-form');
                const registerForm = document.querySelector('.register-form');

                if (loginForm.style.display === 'none') {
                    loginForm.style.display = 'block';
                    registerForm.style.display = 'none';
                } else {
                    loginForm.style.display = 'none';
                    registerForm.style.display = 'block';
                }
            };

            // Toggle password visibility
            const passwordToggles = document.querySelectorAll('.password-toggle');
            passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            });
            
            // Password strength validation
            const passwordInput = document.getElementById('register-password');
            const confirmInput = document.getElementById('confirm-password');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    
                    // Check length
                    const lengthRequirement = document.getElementById('length');
                    if (password.length >= 8) {
                        lengthRequirement.classList.add('valid');
                        lengthRequirement.innerHTML = '✓ At least 8 characters';
                    } else {
                        lengthRequirement.classList.remove('valid');
                        lengthRequirement.innerHTML = 'At least 8 characters';
                    }
                    
                    // Check uppercase
                    const uppercaseRequirement = document.getElementById('uppercase');
                    if (/[A-Z]/.test(password)) {
                        uppercaseRequirement.classList.add('valid');
                        uppercaseRequirement.innerHTML = '✓ At least one uppercase letter';
                    } else {
                        uppercaseRequirement.classList.remove('valid');
                        uppercaseRequirement.innerHTML = 'At least one uppercase letter';
                    }
                    
                    // Check lowercase
                    const lowercaseRequirement = document.getElementById('lowercase');
                    if (/[a-z]/.test(password)) {
                        lowercaseRequirement.classList.add('valid');
                        lowercaseRequirement.innerHTML = '✓ At least one lowercase letter';
                    } else {
                        lowercaseRequirement.classList.remove('valid');
                        lowercaseRequirement.innerHTML = 'At least one lowercase letter';
                    }
                    
                    // Check number
                    const numberRequirement = document.getElementById('number');
                    if (/[0-9]/.test(password)) {
                        numberRequirement.classList.add('valid');
                        numberRequirement.innerHTML = '✓ At least one number';
                    } else {
                        numberRequirement.classList.remove('valid');
                        numberRequirement.innerHTML = 'At least one number';
                    }
                    
                    // Check special character
                    const specialRequirement = document.getElementById('special');
                    if (/[^A-Za-z0-9]/.test(password)) {
                        specialRequirement.classList.add('valid');
                        specialRequirement.innerHTML = '✓ At least one special character';
                    } else {
                        specialRequirement.classList.remove('valid');
                        specialRequirement.innerHTML = 'At least one special character';
                    }
                });
                
                // Check if passwords match
                confirmInput.addEventListener('input', function() {
                    if (this.value === passwordInput.value) {
                        this.style.borderColor = 'var(--success-color)';
                    } else {
                        this.style.borderColor = 'var(--error-color)';
                    }
                });
            }
        });
    </script>
</body>
</html>