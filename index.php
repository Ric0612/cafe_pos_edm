<?php
// Include the database connection
include('includes/db-conn.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the form data
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare the SQL query to fetch the user from the database
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username); 
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Function to check if password is hashed
        function is_password_hashed($password) {
            return (strlen($password) == 60 && strpos($password, '$2y$') === 0);
        }
        
        $is_authenticated = false;
        
        // Check if the password is hashed
        if (is_password_hashed($user['password'])) {
            // Verify against hashed password
            $is_authenticated = password_verify($password, $user['password']);
        } else {
            // Direct comparison for unhashed password
            if ($password === $user['password']) {
                // Password matches but needs to be hashed
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update the password in the database
                $update_sql = "UPDATE users SET password = ? WHERE user_ID = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $hashed_password, $user['user_ID']);
                $update_stmt->execute();
                
                $is_authenticated = true;
            }
        }
        
        if ($is_authenticated) {
            // Start the session and store user data
            session_start();
            $_SESSION['user_ID'] = $user['user_ID'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            // Log the successful login
            $ip = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $log_sql = "INSERT INTO login_audit_logs (user_ID, username, action, ip_address, user_agent) VALUES (?, ?, 'LOGIN', ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("isss", $user['user_ID'], $user['username'], $ip, $user_agent);
            $log_stmt->execute();

            // Redirect based on role
            switch ($_SESSION['role']) {
                case 'manager':
                    header('Location: dist/dashboard.php');
                    break;
                case 'cashier':
                    header('Location: dist/sales.php');
                    break;
                case 'kitchen':
                    header('Location: dist/orders.php');
                    break;
                default:
                    // Invalid role
                    session_destroy();
                    $error_message = "Invalid user role.";
                    break;
            }
            exit();
        } else {
            $error_message = "Invalid password.";
        }
    } else {
        $error_message = "No user found with that username.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café POS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <style>
        :root {
            --primary-color: #6c4f3d;
            --secondary-color: #f4e1c1;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f8f9fa;
            overflow: hidden;
        }

        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary-color);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .coffee-cup {
            width: 100px;
            height: 100px;
            position: relative;
            margin-bottom: 40px;
        }

        .cup {
            width: 100%;
            height: 100%;
            background: white;
            border-radius: 0 0 50% 50%;
            position: relative;
            box-shadow: 0 0 0 6px var(--secondary-color);
        }

        .liquid {
            width: 100%;
            height: 0%;
            background: var(--secondary-color);
            border-radius: 0 0 50% 50%;
            position: absolute;
            bottom: 0;
            transition: height 0.5s ease;
        }

        .handle {
            width: 30px;
            height: 50px;
            border: 6px solid var(--secondary-color);
            border-left: 0;
            border-radius: 0 25px 25px 0;
            position: absolute;
            right: -20px;
            top: 20px;
        }

        .steam {
            position: absolute;
            top: -20px;
            width: 8px;
            height: 20px;
            background: var(--secondary-color);
            border-radius: 10px;
            opacity: 0;
        }

        .steam:nth-child(1) { left: 20px; animation: steam 2s infinite; }
        .steam:nth-child(2) { left: 40px; animation: steam 2s infinite 0.3s; }
        .steam:nth-child(3) { left: 60px; animation: steam 2s infinite 0.6s; }

        @keyframes steam {
            0% { transform: translateY(0) scaleX(1); opacity: 0; }
            15% { opacity: 1; }
            50% { transform: translateY(-20px) scaleX(3); opacity: 0; }
            100% { transform: translateY(-40px) scaleX(4); opacity: 0; }
        }

        .loading-text {
            color: var(--secondary-color);
            font-size: 24px;
            margin-top: 20px;
            opacity: 0;
            animation: fadeInOut 2s infinite;
        }

        @keyframes fadeInOut {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 1; }
        }

        .login-container {
            display: none;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, #8b6b56 100%);
            padding: 20px;
        }

        .login-box {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            margin: auto;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.5s ease;
        }

        .login-box.show {
            transform: translateY(0);
            opacity: 1;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo i {
            font-size: 48px;
            color: var(--primary-color);
        }

        .form-floating {
            margin-bottom: 20px;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(108, 79, 61, 0.25);
        }

        .btn-login {
            background: var(--primary-color);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 10px;
            width: 100%;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: #8b6b56;
            transform: translateY(-2px);
        }

        .error-message {
            color: #dc3545;
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="coffee-cup">
            <div class="steam"></div>
            <div class="steam"></div>
            <div class="steam"></div>
            <div class="cup">
                <div class="liquid" id="liquid"></div>
                <div class="handle"></div>
            </div>
        </div>
        <div class="loading-text">Brewing your session...</div>
    </div>

    <!-- Login Form -->
    <div class="login-container d-flex align-items-center justify-content-center" id="loginContainer">
        <div class="login-box">
            <div class="login-logo">
                <i class="fas fa-mug-hot"></i>
                <h2 class="mt-3" style="color: var(--primary-color);">Café POS</h2>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                    <label for="username">Username</label>
                </div>
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password">Password</label>
                </div>
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loadingScreen = document.getElementById('loadingScreen');
            const loginContainer = document.getElementById('loginContainer');
            const loginBox = document.querySelector('.login-box');
            const liquid = document.getElementById('liquid');

            // Animate the liquid filling up
            setTimeout(() => {
                liquid.style.height = '100%';
            }, 500);

            // After 3 seconds, hide loading screen and show login form
            setTimeout(() => {
                loadingScreen.style.opacity = '0';
                loadingScreen.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    loadingScreen.style.display = 'none';
                    loginContainer.style.display = 'flex';
                    setTimeout(() => {
                        loginBox.classList.add('show');
                    }, 100);
                }, 500);
            }, 3000);
        });
    </script>
</body>
</html>
