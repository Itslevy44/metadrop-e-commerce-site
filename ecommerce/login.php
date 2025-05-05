<?php session_start(); include 'config.php'; 

// Login Processing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Prepare SQL to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify password (you should use password_hash() when storing passwords)
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "User not found";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="logo.jpg" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MetaDrop - Login</title>
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
            margin-bottom: 15px;
        }
        
        .login-btn:hover {
            background-color: #3a0ca3;
        }
        
        .admin-btn {
            width: 100%;
            padding: 12px;
            background-color: #f5f5f5;
            color: #555;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .admin-btn:hover {
            background-color: #e0e0e0;
            color: #333;
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
        
        .divider {
            display: flex;
            align-items: center;
            margin: 15px 0;
            color: #999;
        }
        
        .divider-line {
            flex: 1;
            height: 1px;
            background-color: #eee;
        }
        
        .divider-text {
            padding: 0 15px;
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
            <p class="tagline">Your digital marketplace for the future</p>
        </div>
        
        <div class="right-panel">
            <div class="form-container">
                <h2 class="form-title">Login</h2>
                <p class="form-subtitle">Fill in your credentials to continue with MetaDrop</p>
                
                <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="email" name="email" class="input-field" placeholder="Email Address" required>
                    <input type="password" name="password" class="input-field" placeholder="Password" required>
                    
                    <button type="submit" name="login" class="login-btn">
                        Login
                    </button>
                </form>
                
                <div class="divider">
                    <div class="divider-line"></div>
                    <div class="divider-text">OR</div>
                    <div class="divider-line"></div>
                </div>
                
                <a href="admin_login.php">
                    <button type="button" class="admin-btn">
                        Admin Login
                    </button>
                </a>
                
                <div class="bottom-text">
                    Don't have account? <a href="signup.php">Sign Up</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>