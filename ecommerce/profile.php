<?php
// Database configuration - directly included to avoid dependency issues
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); 
define('DB_PASS', '');
define('DB_NAME', 'metadrop');

// Start or resume session
session_start();

// Check if user is logged in (adjust this based on how your session variables are set in index.php)
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to login page or show error
    echo "User not logged in!";
    exit();
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

try {
    // Create database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Fetch user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Get user data
        $user = $result->fetch_assoc();
    } else {
        echo "User not found!";
        exit();
    }
    
    // Close statement and connection
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" href="logo.jpg" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .profile-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .profile-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .profile-table tr:last-child td {
            border-bottom: none;
        }
        .actions {
            margin-top: 20px;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #4361ee;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 0 5px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        footer {
            text-align: center;
            margin-top: 20px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>User Profile</h1>
        
        <div class="profile-info">
            <div class="profile-section">
                <h2>Personal Information</h2>
                <table class="profile-table">
                    <tr>
                        <td><strong>Username:</strong></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Full Name:</strong></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Phone Number:</strong></td>
                        <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Address:</strong></td>
                        <td><?php echo htmlspecialchars($user['address']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Registration Date:</strong></td>
                        <td><?php echo htmlspecialchars($user['registration_date']); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="actions">
                <a href="edit_profile.php" class="btn">Edit Profile</a>
                <a href="change_password.php" class="btn">Change Password</a>
                <a href="index.php" class="btn">Back to Home</a>
            </div>
        </div>
    </div>
    
    <footer>
        <p>&copy; <?php echo date("Y"); ?> MetaDrop. All rights reserved.</p>
    </footer>

    <!-- Debug information - remove in production -->
    <div style="margin-top: 20px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd;">
        <h3>Session Debug Info:</h3>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>
</body>
</html>