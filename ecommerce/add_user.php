<?php
session_start();
include 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Initialize variables
$message = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = $_POST['role'];
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill all required fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check if username or email already exists
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM admins WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        
        if ($check_result['count'] > 0) {
            $error = "Username or email already exists.";
        } else {
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $insert_stmt = $conn->prepare("INSERT INTO admins (username, email, password, full_name, role, priority, status, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $insert_stmt->bind_param("sssssss", $username, $email, $hashed_password, $full_name, $role, $priority, $status);
                $insert_stmt->execute();
                
                // Log the action
                $admin_id = $conn->insert_id;
                $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, 'add_user', ?)");
                $details = "Added new user: " . $username;
                $log_stmt->bind_param("is", $_SESSION['admin_id'], $details);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $message = "User successfully created!";
                
                // Clear form data
                $username = $email = $full_name = $password = $confirm_password = '';
                $role = 'staff';
                $priority = 'low';
                $status = 'pending';
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error creating user: " . $e->getMessage();
                error_log("User creation error: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MetaDrop - Add User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" href="logo.jpg" type="image/png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background-color: #f7f9fc;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e6ed;
        }
        
        .admin-title {
            color: #4361ee;
            font-size: 24px;
            font-weight: bold;
        }
        
        .admin-actions {
            display: flex;
            gap: 15px;
        }
        
        .admin-btn {
            padding: 8px 16px;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .admin-btn:hover {
            background-color: #3a0ca3;
        }
        
        .admin-btn.secondary {
            background-color: #e0e6ed;
            color: #4361ee;
        }
        
        .admin-btn.secondary:hover {
            background-color: #d1d9e6;
        }
        
        .form-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
        }
        
        .form-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e0e6ed;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #4361ee;
        }
        
        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e0e6ed;
            border-radius: 5px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-col {
            flex: 1;
        }
        
        .form-helper {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
        }
        
        .form-button {
            padding: 12px 20px;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .form-button:hover {
            background-color: #3a0ca3;
        }
        
        .form-button.secondary {
            background-color: #e0e6ed;
            color: #4361ee;
        }
        
        .form-button.secondary:hover {
            background-color: #d1d9e6;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;border: 1px solid #fecaca;
        }
        
        .required-field::after {
            content: "*";
            color: #e63946;
            margin-left: 3px;
        }
        
        .form-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e6ed;
        }
        
        .form-title {
            font-size: 18px;
            color: #4361ee;
            margin-bottom: 5px;
        }
        
        .form-subtitle {
            font-size: 14px;
            color: #718096;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1 class="admin-title">Add New User</h1>
            <div class="admin-actions">
                <a href="manage_users.php" class="admin-btn secondary">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">User Information</h2>
                <p class="form-subtitle">Create a new admin user with appropriate role and permissions.</p>
            </div>
            
            <form action="" method="POST">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="username" class="form-label required-field">Username</label>
                            <input type="text" id="username" name="username" class="form-input" required value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="email" class="form-label required-field">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-input" value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="password" class="form-label required-field">Password</label>
                            <input type="password" id="password" name="password" class="form-input" required>
                            <p class="form-helper">Password must be at least 8 characters long.</p>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="confirm_password" class="form-label required-field">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="role" class="form-label required-field">Role</label>
                            <select id="role" name="role" class="form-select" required>
                                <option value="super-admin" <?php echo (isset($role) && $role == 'super-admin') ? 'selected' : ''; ?>>Super Admin</option>
                                <option value="admin" <?php echo (isset($role) && $role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="editor" <?php echo (isset($role) && $role == 'editor') ? 'selected' : ''; ?>>Editor</option>
                                <option value="manager" <?php echo (isset($role) && $role == 'manager') ? 'selected' : ''; ?>>Manager</option>
                                <option value="staff" <?php echo (!isset($role) || $role == 'staff') ? 'selected' : ''; ?>>Staff</option>
                            </select>
                            <p class="form-helper">Defines the user's access level and permissions.</p>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="priority" class="form-label required-field">Priority</label>
                            <select id="priority" name="priority" class="form-select" required>
                                <option value="high" <?php echo (isset($priority) && $priority == 'high') ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo (isset($priority) && $priority == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo (!isset($priority) || $priority == 'low') ? 'selected' : ''; ?>>Low</option>
                            </select>
                            <p class="form-helper">Determines the user's priority level for notifications and tasks.</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status" class="form-label required-field">Status</label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="active" <?php echo (isset($status) && $status == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($status) && $status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="pending" <?php echo (!isset($status) || $status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    </select>
                    <p class="form-helper">Sets the user's account status.</p>
                </div>
                
                <div class="form-actions">
                    <button type="reset" class="form-button secondary">Reset</button>
                    <button type="submit" class="form-button">Create User</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>