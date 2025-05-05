<?php
session_start();
include 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// If already logged in, redirect to admin dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit();
}

$error = '';
$username = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['admin_login'])) {
    try {
        // Get and sanitize input
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $password = $_POST['password']; // Don't sanitize password as it might affect verification

        // Validate input
        if (empty($username) || empty($password)) {
            throw new Exception("Please enter both username and password");
        }

        // Query the database
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Check if user exists
        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            
            // For debugging
            error_log("Admin found: " . json_encode($admin));
            
            // Try both password verification methods
            // Method 1: Hash verification (if password is stored as hash)
            $password_correct = false;
            
            // First try direct comparison (plain text)
            if ($password === $admin['password']) {
                $password_correct = true;
            } 
            // Then try password_verify (if it's hashed with password_hash)
            else if (password_hash($password, PASSWORD_DEFAULT) && password_verify($password, $admin['password'])) {
                $password_correct = true;
            }
            
            if ($password_correct) {
                // Set session variables
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Log the successful login if table exists
                $table_check = $conn->query("SHOW TABLES LIKE 'admin_login_logs'");
                if ($table_check->num_rows > 0) {
                    $log_stmt = $conn->prepare("INSERT INTO admin_login_logs (admin_id, status, ip_address) VALUES (?, 'success', ?)");
                    if ($log_stmt) {
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("is", $admin['admin_id'], $ip);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                }
                
                // Close statement and redirect
                $stmt->close();
                header("Location: admin.php");
                exit();
            } else {
                // Log failed attempt if table exists
                $table_check = $conn->query("SHOW TABLES LIKE 'admin_login_logs'");
                if ($table_check->num_rows > 0) {
                    $log_stmt = $conn->prepare("INSERT INTO admin_login_logs (admin_id, status, ip_address) VALUES (?, 'failed', ?)");
                    if ($log_stmt) {
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("is", $admin['admin_id'], $ip);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                }
                
                throw new Exception("Invalid username or password");
            }
        } else {
            throw new Exception("Invalid username or password");
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Login Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="logo.jpg" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MetaDrop - Admin Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            display: flex;
            min-height: 100vh;
            background: linear-gradient(45deg, #4361ee, #3a0ca3);
            overflow: hidden;
        }
        
        .container {
            display: flex;
            width: 100%;
            height: 100vh;
        }
        
        .left-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        
        .circle-bg-1 {
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: 20%;
            left: -100px;
        }
        
        .circle-bg-2 {
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            bottom: -200px;
            right: -150px;
        }
        
        .brand {
            font-size: 3rem;
            font-weight: bold;
            color: white;
            margin-bottom: 10px;
            z-index: 1;
        }
        
        .tagline {
            font-size: 1.2rem;
            color: white;
            opacity: 0.9;
            z-index: 1;
        }
        
        .right-panel {
            flex: 1;
            background-color: white;
            border-radius: 30px 0 0 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.1);
        }
        
        .form-container {
            width: 100%;
            max-width: 450px;
        }
        
        .form-title {
            font-size: 2.5rem;
            color: #4361ee;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .form-subtitle {
            color: #777;
            margin-bottom: 30px;
            font-size: 1rem;
        }
        
        .input-field {
            width: 100%;
            padding: 15px;
            margin-bottom: 20px;
            border: none;
            border-radius: 8px;
            background-color: #f5f5f5;
            font-size: 1rem;
        }
        
        .input-field::placeholder {
            color: #aaa;
        }
        
        .input-field:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.3);
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-btn:hover {
            background-color: #3a0ca3;
        }
        
        .bottom-text {
            text-align: center;
            margin-top: 20px;
            color: #777;
        }
        
        .bottom-text a {
            color: #4361ee;
            text-decoration: none;
            font-weight: bold;
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .right-panel {
                border-radius: 30px 30px 0 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="circle-bg-1"></div>
            <div class="circle-bg-2"></div>
            <h1 class="brand">MetaDrop</h1>
            <p class="tagline">Admin Control Panel</p>
        </div>
        
        <div class="right-panel">
            <div class="form-container">
                <h2 class="form-title">Admin Login</h2>
                <p class="form-subtitle">Enter your credentials to access the admin dashboard</p>
                
                <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="loginForm">
                    <input type="text" 
                        name="username" 
                        id="username"
                        class="input-field" 
                        placeholder="Username" 
                        required 
                        autocomplete="username"
                        value="<?php echo htmlspecialchars($username); ?>">
                    
                    <input type="password" 
                        name="password" 
                        id="password"
                        class="input-field" 
                        placeholder="Password" 
                        required 
                        autocomplete="current-password">
                    
                    <button type="submit" name="admin_login" class="login-btn">
                        Login
                    </button>
                </form>
                
                <div class="bottom-text">
                    <a href="login.php">Return to user login</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password');
            }
        });
    </script>
</body>
</html>