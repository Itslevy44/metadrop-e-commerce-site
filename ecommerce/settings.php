<?php 
session_start(); 
include 'config.php'; 

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    // For development purposes, we'll continue without redirecting
    // In production, uncomment the following:
    // header("Location: admin_login.php");
    // exit();
}

$admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 1; // Default to ID 1 for development
$success_message = '';
$error_message = '';

// Fetch current settings
$settings = [];
$settings_query = "SELECT * FROM settings WHERE id = 1"; // Assuming a single row for global settings

try {
    $settings_result = mysqli_query($conn, $settings_query);
    if($settings_result && mysqli_num_rows($settings_result) > 0) {
        $settings = mysqli_fetch_assoc($settings_result);
    } else {
        // Create default settings if none exist
        $create_settings = "INSERT INTO settings (site_name, site_description, contact_email, currency, 
                            maintenance_mode) VALUES ('MetaDrop', 'Digital Product Marketplace', 
                            'admin@metadrop.com', 'USD', 0)";
        mysqli_query($conn, $create_settings);
        
        // Fetch the newly created settings
        $settings_result = mysqli_query($conn, $settings_query);
        if($settings_result) {
            $settings = mysqli_fetch_assoc($settings_result);
        }
    }
} catch (Exception $e) {
    // If table doesn't exist or other error
    $error_message = "Settings table not found. Default values will be used.";
    
    // Create some default settings for display
    $settings = [
        'site_name' => 'MetaDrop',
        'site_description' => 'Digital Product Marketplace',
        'contact_email' => 'admin@metadrop.com',
        'currency' => 'USD',
        'maintenance_mode' => 0
    ];
}

// Fetch admin details
$admin_data = [];
$admin_query = "SELECT * FROM admins WHERE id = $admin_id";

try {
    $admin_result = mysqli_query($conn, $admin_query);
    if($admin_result && mysqli_num_rows($admin_result) > 0) {
        $admin_data = mysqli_fetch_assoc($admin_result);
    } else {
        // Try users table as fallback
        $user_query = "SELECT * FROM users WHERE id = $admin_id";
        $user_result = mysqli_query($conn, $user_query);
        if($user_result && mysqli_num_rows($user_result) > 0) {
            $admin_data = mysqli_fetch_assoc($user_result);
        }
    }
} catch (Exception $e) {
    $error_message = "Could not fetch admin profile information.";
    
    // Create some default admin data for display
    $admin_data = [
        'username' => isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin',
        'email' => 'admin@metadrop.com'
    ];
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Site Settings Form
    if(isset($_POST['update_site_settings'])) {
        $site_name = mysqli_real_escape_string($conn, $_POST['site_name']);
        $site_description = mysqli_real_escape_string($conn, $_POST['site_description']);
        $contact_email = mysqli_real_escape_string($conn, $_POST['contact_email']);
        $currency = mysqli_real_escape_string($conn, $_POST['currency']);
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        
        try {
            $update_query = "UPDATE settings SET 
                            site_name = '$site_name',
                            site_description = '$site_description',
                            contact_email = '$contact_email',
                            currency = '$currency',
                            maintenance_mode = $maintenance_mode
                            WHERE id = 1";
            
            if(mysqli_query($conn, $update_query)) {
                $success_message = "Site settings updated successfully!";
                
                // Update the local settings array
                $settings['site_name'] = $site_name;
                $settings['site_description'] = $site_description;
                $settings['contact_email'] = $contact_email;
                $settings['currency'] = $currency;
                $settings['maintenance_mode'] = $maintenance_mode;
            } else {
                $error_message = "Error updating site settings: " . mysqli_error($conn);
            }
        } catch (Exception $e) {
            $error_message = "Error updating site settings. Database may not be properly configured.";
        }
    }
    
    // Admin Profile Form
    if(isset($_POST['update_profile'])) {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        try {
            // Determine the correct table
            $table = 'admins';
            $check_admin = "SHOW TABLES LIKE 'admins'";
            $admin_table_exists = mysqli_query($conn, $check_admin);
            
            if(!$admin_table_exists || mysqli_num_rows($admin_table_exists) == 0) {
                $table = 'users';
            }
            
            // Update username and email
            $update_query = "UPDATE $table SET 
                            username = '$username',
                            email = '$email'
                            WHERE id = $admin_id";
                            
            $update_result = mysqli_query($conn, $update_query);
            
            // Check if password change is requested
            if(!empty($current_password) && !empty($new_password)) {
                if($new_password != $confirm_password) {
                    $error_message = "New passwords do not match!";
                } else {
                    // Fetch current password hash
                    $pass_query = "SELECT password FROM $table WHERE id = $admin_id";
                    $pass_result = mysqli_query($conn, $pass_query);
                    
                    if($pass_result && mysqli_num_rows($pass_result) > 0) {
                        $user = mysqli_fetch_assoc($pass_result);
                        
                        // Verify current password (either as hash or plaintext for development)
                        if(password_verify($current_password, $user['password']) || $current_password == $user['password']) {
                            // Hash the new password
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            
                            // Update password
                            $password_query = "UPDATE $table SET password = '$hashed_password' WHERE id = $admin_id";
                            $password_result = mysqli_query($conn, $password_query);
                            
                            if($password_result) {
                                $success_message = "Profile and password updated successfully!";
                                
                                // Update the local admin_data array
                                $admin_data['username'] = $username;
                                $admin_data['email'] = $email;
                            } else {
                                $error_message = "Error updating password.";
                            }
                        } else {
                            $error_message = "Current password is incorrect!";
                        }
                    } else {
                        $error_message = "Error fetching user data.";
                    }
                }
            } else if($update_result) {
                $success_message = "Profile updated successfully!";
                
                // Update the local admin_data array
                $admin_data['username'] = $username;
                $admin_data['email'] = $email;
            } else {
                $error_message = "Error updating profile information.";
            }
        } catch (Exception $e) {
            $error_message = "Error updating profile. Database may not be properly configured.";
        }
    }
    
    // SMTP Settings Form
    if(isset($_POST['update_smtp'])) {
        $smtp_host = mysqli_real_escape_string($conn, $_POST['smtp_host']);
        $smtp_port = mysqli_real_escape_string($conn, $_POST['smtp_port']);
        $smtp_username = mysqli_real_escape_string($conn, $_POST['smtp_username']);
        $smtp_password = mysqli_real_escape_string($conn, $_POST['smtp_password']);
        $smtp_encryption = mysqli_real_escape_string($conn, $_POST['smtp_encryption']);
        
        try {
            // Check if SMTP settings exist in the table
            $check_smtp = "SELECT * FROM settings WHERE id = 1 AND smtp_host IS NOT NULL";
            $smtp_result = mysqli_query($conn, $check_smtp);
            
            if($smtp_result && mysqli_num_rows($smtp_result) > 0) {
                // Update existing SMTP settings
                $update_query = "UPDATE settings SET 
                                smtp_host = '$smtp_host',
                                smtp_port = '$smtp_port',
                                smtp_username = '$smtp_username',
                                smtp_encryption = '$smtp_encryption'";
                
                // Only update password if not empty
                if(!empty($smtp_password)) {
                    $update_query .= ", smtp_password = '$smtp_password'";
                }
                
                $update_query .= " WHERE id = 1";
            } else {
                // Add SMTP columns if they don't exist
                $alter_query = "ALTER TABLE settings ADD COLUMN smtp_host VARCHAR(255), 
                              ADD COLUMN smtp_port INT, 
                              ADD COLUMN smtp_username VARCHAR(255),
                              ADD COLUMN smtp_password VARCHAR(255),
                              ADD COLUMN smtp_encryption VARCHAR(10)";
                
                mysqli_query($conn, $alter_query);
                
                // Insert SMTP settings
                $update_query = "UPDATE settings SET 
                                smtp_host = '$smtp_host',
                                smtp_port = '$smtp_port',
                                smtp_username = '$smtp_username',
                                smtp_password = '$smtp_password',
                                smtp_encryption = '$smtp_encryption'
                                WHERE id = 1";
            }
            
            if(mysqli_query($conn, $update_query)) {
                $success_message = "SMTP settings updated successfully!";
                
                // Update the local settings array
                $settings['smtp_host'] = $smtp_host;
                $settings['smtp_port'] = $smtp_port;
                $settings['smtp_username'] = $smtp_username;
                $settings['smtp_encryption'] = $smtp_encryption;
                if(!empty($smtp_password)) {
                    $settings['smtp_password'] = $smtp_password;
                }
            } else {
                $error_message = "Error updating SMTP settings: " . mysqli_error($conn);
            }
        } catch (Exception $e) {
            $error_message = "Error updating SMTP settings. Database may not be properly configured.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="logo.jpg" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - MetaDrop Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(45deg, #f5f7fa, #e4e8f0);
            min-height: 100vh;
            padding: 20px;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 20px auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(45deg, #4361ee, #3a0ca3);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .content {
            padding: 30px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-name {
            font-weight: bold;
        }
        
        .back-btn, .logout-btn {
            background-color: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        
        .back-btn:hover, .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .settings-nav {
            display: flex;
            background-color: #f5f7fa;
            border-radius: 10px;
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .nav-item {
            padding: 15px 20px;
            cursor: pointer;
            font-weight: bold;
            color: #555;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
            border-bottom: 3px solid transparent;
        }
        
        .nav-item.active {
            background-color: white;
            color: #4361ee;
            border-bottom: 3px solid #4361ee;
        }
        
        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.5);
            color: #4361ee;
        }
        
        .settings-section {
            display: none;
        }
        
        .settings-section.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group input[type="password"]:focus,
        .form-group input[type="number"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #4361ee;
            outline: none;
        }
        
        .form-group input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn {
            background-color: #4361ee;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #3a0ca3;
        }
        
        .success-message {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background-color: rgba(244, 67, 54, 0.1);
            color: #f44336;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .section-title {
            margin-bottom: 20px;
            color: #333;
            font-size: 1.5rem;
        }
        
        .description {
            color: #666;
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .user-info {
                margin-top: 15px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .settings-nav {
                flex-direction: column;
            }
            
            .nav-item {
                border-bottom: 1px solid #eaeaea;
            }
            
            .nav-item.active {
                border-bottom: 1px solid #eaeaea;
                border-left: 3px solid #4361ee;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header">
            <h1>Settings</h1>
            <div class="user-info">
                <div class="avatar"><?php echo substr(isset($admin_data['username']) ? $admin_data['username'] : 'A', 0, 1); ?></div>
                <span class="user-name"><?php echo isset($admin_data['username']) ? $admin_data['username'] : 'Admin'; ?></span>
                <a href="admin.php" class="back-btn">Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <?php if(!empty($success_message)): ?>
                <div class="success-message">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="settings-nav">
                <div class="nav-item active" data-target="site-settings">Site Settings</div>
                <div class="nav-item" data-target="admin-profile">Admin Profile</div>
                <div class="nav-item" data-target="email-settings">Email Settings</div>
                <div class="nav-item" data-target="payment-settings">Payment Settings</div>
            </div>
            
            <!-- Site Settings Section -->
            <div class="settings-section active" id="site-settings">
                <h2 class="section-title">Site Settings</h2>
                <p class="description">Configure general settings for your MetaDrop store.</p>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="site_name">Site Name</label>
                            <input type="text" id="site_name" name="site_name" value="<?php echo isset($settings['site_name']) ? $settings['site_name'] : 'MetaDrop'; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="currency">Currency</label>
                            <select id="currency" name="currency">
                                <option value="USD" <?php echo (isset($settings['currency']) && $settings['currency'] == 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                                <option value="EUR" <?php echo (isset($settings['currency']) && $settings['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR (€)</option>
                                <option value="GBP" <?php echo (isset($settings['currency']) && $settings['currency'] == 'GBP') ? 'selected' : ''; ?>>GBP (£)</option>
                                <option value="CAD" <?php echo (isset($settings['currency']) && $settings['currency'] == 'CAD') ? 'selected' : ''; ?>>CAD ($)</option>
                                <option value="AUD" <?php echo (isset($settings['currency']) && $settings['currency'] == 'AUD') ? 'selected' : ''; ?>>AUD ($)</option>
                                <option value="KSH" <?php echo (isset($settings['currency']) && $settings['currency'] == 'KSH') ? 'selected' : ''; ?>>KSH ($)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_description">Site Description</label>
                        <textarea id="site_description" name="site_description" rows="3"><?php echo isset($settings['site_description']) ? $settings['site_description'] : 'Digital Product Marketplace'; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" value="<?php echo isset($settings['contact_email']) ? $settings['contact_email'] : 'admin@metadrop.com'; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="maintenance_mode">
                            <input type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php echo (isset($settings['maintenance_mode']) && $settings['maintenance_mode'] == 1) ? 'checked' : ''; ?>>
                            Enable Maintenance Mode
                        </label>
                    </div>
                    
                    <button type="submit" name="update_site_settings" class="btn">Save Settings</button>
                </form>
            </div>
            
            <!-- Admin Profile Section -->
            <div class="settings-section" id="admin-profile">
                <h2 class="section-title">Admin Profile</h2>
                <p class="description">Update your personal information and password.</p>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo isset($admin_data['username']) ? $admin_data['username'] : 'Admin'; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo isset($admin_data['email']) ? $admin_data['email'] : 'admin@metadrop.com'; ?>" required>
                        </div>
                    </div>
                    
                    <h3 style="margin: 30px 0 20px; color: #333;">Change Password</h3>
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
            </div>
            
            <!-- Email Settings Section -->
            <div class="settings-section" id="email-settings">
                <h2 class="section-title">Email Settings</h2>
                <p class="description">Configure SMTP settings for sending notification emails.</p>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_host">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="<?php echo isset($settings['smtp_host']) ? $settings['smtp_host'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_port">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" value="<?php echo isset($settings['smtp_port']) ? $settings['smtp_port'] : '587'; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_username">SMTP Username</label>
                            <input type="text" id="smtp_username" name="smtp_username" value="<?php echo isset($settings['smtp_username']) ? $settings['smtp_username'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_password">SMTP Password</label>
                            <input type="password" id="smtp_password" name="smtp_password" placeholder="<?php echo isset($settings['smtp_password']) && !empty($settings['smtp_password']) ? '••••••••' : 'Enter password'; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_encryption">Encryption</label>
                        <select id="smtp_encryption" name="smtp_encryption">
                            <option value="tls" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'tls') ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'none') ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="update_smtp" class="btn">Save SMTP Settings</button>
                </form>
            </div>
            
            <!-- Payment Settings Section -->
            <div class="settings-section" id="payment-settings">
                <h2 class="section-title">Payment Settings</h2>
                <p class="description">Configure payment gateways and options for your store.</p>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="paypal_email">PayPal Email</label>
                        <input type="email" id="paypal_email" name="paypal_email" value="<?php echo isset($settings['paypal_email']) ? $settings['paypal_email'] : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="stripe_public_key">Stripe Public Key</label>
                        <input type="text" id="stripe_public_key" name="stripe_public_key" value="<?php echo isset($settings['stripe_public_key']) ? $settings['stripe_public_key'] : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="stripe_secret_key">Stripe Secret Key</label>
                        <input type="password" id="stripe_secret_key" name="stripe_secret_key" placeholder="<?php echo isset($settings['stripe_secret_key']) && !empty($settings['stripe_secret_key']) ? '••••••••' : 'Enter Stripe secret key'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_methods">Enabled Payment Methods</label>
                        <div style="margin-top: 10px;">
                            <label style="font-weight: normal; display: inline-block; margin-right: 20px;">
                                <input type="checkbox" name="payment_methods[]" value="paypal" <?php echo (isset($settings['payment_methods']) && strpos($settings['payment_methods'], 'paypal') !== false) ? 'checked' : ''; ?>>
                                PayPal
                            </label>
                            <label style="font-weight: normal; display: inline-block; margin-right: 20px;">
                                <input type="checkbox" name="payment_methods[]" value="stripe" <?php echo (isset($settings['payment_methods']) && strpos($settings['payment_methods'], 'stripe') !== false) ? 'checked' : ''; ?>>
                                Stripe
                            </label>
                            <label style="font-weight: normal; display: inline-block;">
                                <input type="checkbox" name="payment_methods[]" value="bank" <?php echo (isset($settings['payment_methods']) && strpos($settings['payment_methods'], 'bank') !== false) ? 'checked' : ''; ?>>
                                Bank Transfer
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_payment" class="btn">Save Payment Settings</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Tab navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const navItems = document.querySelectorAll('.nav-item');
            
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove active class from all tabs and sections
                    document.querySelectorAll('.nav-item').forEach(tab => tab.classList.remove('active'));
                    document.querySelectorAll('.settings-section').forEach(section => section.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show corresponding section
                    const targetId = this.getAttribute('data-target');
                    document.getElementById(targetId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>