<?php
include 'config.php';

// Signup Processing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $phone_number = $_POST['phone_number'];
    $address = $_POST['address'];

    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $error = "Email already exists";
    } else {
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone_number, address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $email, $password, $full_name, $phone_number, $address);
        
        if ($stmt->execute()) {
            header("Location: login.php");
            exit();
        } else {
            $error = "Registration failed: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MetaDrop - Sign Up</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="logo.jpg" type="image/png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #6366F1, #3B82F6, #2563EB);
            font-family: 'Poppins', sans-serif;
            color: #fff;
            padding: 20px;
        }
        
        .container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            height: 650px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }
        
        .left-panel {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .brand {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .brand h1 {
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .brand p {
            font-size: 1rem;
            opacity: 0.8;
            margin-top: 10px;
        }
        
        .left-panel::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            left: -100px;
        }
        
        .left-panel::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            bottom: -50px;
            right: -50px;
        }
        
        .right-panel {
            flex: 1.2;
            background: #fff;
            padding: 50px;
            border-radius: 20px 0 0 20px;
            margin-left: -20px;
            z-index: 1;
            color: #333;
        }
        
        .signup-header {
            margin-bottom: 40px;
        }
        
        .signup-header h2 {
            font-size: 2rem;
            color: #2563EB;
            font-weight: 600;
        }
        
        .signup-header p {
            color: #6B7280;
            margin-top: 8px;
            font-weight: 300;
        }
        
        .form-container {
            max-width: 450px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7280;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            background: #F3F4F6;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            color: #1F2937;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: #fff;
        }
        
        .form-group input::placeholder {
            color: #9CA3AF;
        }
        
        .row {
            display: flex;
            gap: 15px;
        }
        
        .row .form-group {
            flex: 1;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(to right, #3B82F6, #2563EB);
            border: none;
            border-radius: 10px;
            color: white;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(to right, #2563EB, #1D4ED8);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #6B7280;
            font-size: 0.95rem;
        }
        
        .login-link a {
            color: #3B82F6;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .login-link a:hover {
            color: #1D4ED8;
        }
        
        .error {
            background-color: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .error i {
            margin-right: 10px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .container {
                flex-direction: column;
                height: auto;
            }
            
            .left-panel {
                padding: 40px 20px;
            }
            
            .right-panel {
                margin-left: 0;
                border-radius: 0 0 20px 20px;
                padding: 40px 20px;
            }
        }
        
        @media (max-width: 576px) {
            .row {
                flex-direction: column;
                gap: 0;
            }
            
            .signup-header h2 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="brand">
                <h1>MetaDrop</h1>
                <p>Your digital marketplace for the future</p>
            </div>
        </div>
        
        <div class="right-panel">
            <div class="signup-header">
                <h2>Create an Account</h2>
                <p>Fill in your details to get started with MetaDrop</p>
            </div>
            
            <?php 
            if (isset($error)) {
                echo "<div class='error'><i class='fas fa-exclamation-circle'></i> $error</div>";
            }
            ?>
            
            <div class="form-container">
                <form method="POST">
                    <div class="row">
                        <div class="form-group">
                            <i class="fas fa-user icon"></i>
                            <input type="text" name="username" placeholder="Username" autocomplete="off" required>
                        </div>
                        
                        <div class="form-group">
                            <i class="fas fa-user-circle icon"></i>
                            <input type="text" name="full_name" placeholder="Full Name" autocomplete="off" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-envelope icon"></i>
                        <input type="email" name="email" placeholder="Email Address" autocomplete="off" required>
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-lock icon"></i>
                        <input type="password" name="password" placeholder="Password" autocomplete="off" required>
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-phone icon"></i>
                        <input type="tel" name="phone_number" placeholder="Phone Number" autocomplete="off" required>
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-map-marker-alt icon"></i>
                        <input type="text" name="address" placeholder="Address" autocomplete="off" required>
                    </div>
                    
                    <button type="submit" name="signup" class="btn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div class="login-link">
                    Already have an account? <a href="login.php">Log in</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>