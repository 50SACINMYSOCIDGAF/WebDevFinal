<?php
session_start();
require_once 'config.php';

// Prevent SQL injection
function sanitize($input) {
    return htmlspecialchars(strip_tags($input));
}

// Password validation
function isPasswordStrong($password) {
    return (strlen($password) >= 8 &&
            preg_match('/[A-Z]/', $password) &&
            preg_match('/[a-z]/', $password) &&
            preg_match('/[0-9]/', $password) &&
            preg_match('/[^A-Za-z0-9]/', $password));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'register') {
            $username = sanitize($_POST['username']);
            $email = sanitize($_POST['email']);
            $password = $_POST['password'];

            if (!isPasswordStrong($password)) {
                $error = "Password must be at least 8 characters and contain uppercase, lowercase, numbers, and special characters.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $email, $hashedPassword);

                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $stmt->insert_id;
                    $_SESSION['username'] = $username;
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Registration failed. Username or email might already exist.";
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] === 'login') {
            $username = sanitize($_POST['username']);
            $password = $_POST['password'];

            $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: index.php");
                    exit();
                }
            }
            $error = "Invalid username or password.";
            $stmt->close();
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
    <style>
        :root {
            --dark-blue: #0a1930;
            --light-blue: #1e3a8a;
            --lighter-blue: #3b82f6;
            --glass-bg: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #000;
            font-family: 'Segoe UI', sans-serif;
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
            width: 400px;
            z-index: 1;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        input {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--lighter-blue);
            background: rgba(255, 255, 255, 0.15);
        }

        button {
            width: 100%;
            padding: 12px;
            background: var(--lighter-blue);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        button:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .toggle-form {
            color: var(--lighter-blue);
            text-align: center;
            margin-top: 20px;
            cursor: pointer;
        }

        .error {
            color: #ef4444;
            text-align: center;
            margin-bottom: 20px;
        }

        .register-form {
            display: none;
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
        <h1>ConnectHub</h1>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form class="login-form" method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit">Login</button>
            <div class="toggle-form" onclick="toggleForms()">Don't have an account? Register</div>
        </form>

        <form class="register-form" method="POST">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="email" name="email" placeholder="Email" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit">Register</button>
            <div class="toggle-form" onclick="toggleForms()">Already have an account? Login</div>
        </form>
    </div>

    <script>
        function toggleForms() {
            const loginForm = document.querySelector('.login-form');
            const registerForm = document.querySelector('.register-form');

            if (loginForm.style.display === 'none') {
                loginForm.style.display = 'block';
                registerForm.style.display = 'none';
            } else {
                loginForm.style.display = 'none';
                registerForm.style.display = 'block';
            }
        }
    </script>
</body>
</html>