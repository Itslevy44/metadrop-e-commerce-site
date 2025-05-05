<?php 
session_start(); 
include 'config.php'; 

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    // For development purposes, we'll continue without redirecting
    // In production, uncomment the following:
    // header("Location: login.php");
    // exit();
}

// Fetch stats from database
$product_count = 0;
$user_count = 0;
$total_revenue = 0;
$new_orders_count = 0;

// Count total products
$product_query = "SELECT COUNT(*) as total FROM products";
$product_result = mysqli_query($conn, $product_query);
if($product_result) {
    $product_data = mysqli_fetch_assoc($product_result);
    $product_count = $product_data['total'];
}

// Count total users
$user_query = "SELECT COUNT(*) as total FROM users";
$user_result = mysqli_query($conn, $user_query);
if($user_result) {
    $user_data = mysqli_fetch_assoc($user_result);
    $user_count = $user_data['total'];
}

// Let's check the structure of orders table first
$check_orders_structure = "SHOW COLUMNS FROM orders";
$orders_columns_result = mysqli_query($conn, $check_orders_structure);
$price_column = 'price'; // Default column name to try

if($orders_columns_result) {
    while($column = mysqli_fetch_assoc($orders_columns_result)) {
        // Look for a column that might contain price information
        $column_name = $column['Field'];
        if(in_array($column_name, ['price', 'amount', 'total', 'cost', 'order_amount', 'total_amount'])) {
            $price_column = $column_name;
            break;
        }
    }
}

// Calculate total revenue
$revenue_query = "SELECT SUM($price_column) as total FROM orders WHERE status = 'Completed'";
try {
    $revenue_result = mysqli_query($conn, $revenue_query);
    if($revenue_result) {
        $revenue_data = mysqli_fetch_assoc($revenue_result);
        $total_revenue = $revenue_data['total'] ? $revenue_data['total'] : 0;
    }
} catch (Exception $e) {
    // If error occurs, set to 0 and continue
    $total_revenue = 0;
}

// Count new orders (pending/processing)
$new_orders_query = "SELECT COUNT(*) as total FROM orders WHERE status = 'Processing'";
$new_orders_result = mysqli_query($conn, $new_orders_query);
if($new_orders_result) {
    $new_orders_data = mysqli_fetch_assoc($new_orders_result);
    $new_orders_count = $new_orders_data['total'];
}

// Fetch recent orders - adjusting the query based on table structure
// First, let's check if the expected columns exist
$orders_columns = [];
$users_columns = [];
$products_columns = [];

$check_orders = "SHOW COLUMNS FROM orders";
$orders_result = mysqli_query($conn, $check_orders);
while($row = mysqli_fetch_assoc($orders_result)) {
    $orders_columns[] = $row['Field'];
}

$check_users = "SHOW COLUMNS FROM users";
$users_result = mysqli_query($conn, $check_users);
while($row = mysqli_fetch_assoc($users_result)) {
    $users_columns[] = $row['Field'];
}

$check_products = "SHOW COLUMNS FROM products";
$products_result = mysqli_query($conn, $check_products);
while($row = mysqli_fetch_assoc($products_result)) {
    $products_columns[] = $row['Field'];
}

// Determine the appropriate column names for user identification
$user_id_column = in_array('user_id', $orders_columns) ? 'user_id' : 'id';
$user_name_column = in_array('username', $users_columns) ? 'username' : 
                    (in_array('name', $users_columns) ? 'name' : 'id');

// Determine the appropriate column names for product identification
$product_id_column = in_array('product_id', $orders_columns) ? 'product_id' : 'id';
$product_name_column = in_array('name', $products_columns) ? 'name' : 
                       (in_array('title', $products_columns) ? 'title' : 'id');

// Determine date column
$date_column = in_array('order_date', $orders_columns) ? 'order_date' : 
               (in_array('date', $orders_columns) ? 'date' : 
               (in_array('created_at', $orders_columns) ? 'created_at' : 'id'));

// Build the recent orders query based on the available columns
$recent_orders_query = "SELECT o.id, ";

// Add user info if applicable
if(in_array($user_id_column, $orders_columns)) {
    $recent_orders_query .= "u.$user_name_column as username, ";
} else {
    $recent_orders_query .= "'Unknown' as username, ";
}

// Add product info if applicable
if(in_array($product_id_column, $orders_columns)) {
    $recent_orders_query .= "p.$product_name_column as product_name, ";
} else {
    $recent_orders_query .= "'Unknown Product' as product_name, ";
}

// Add date, amount and status
$recent_orders_query .= "o.$date_column as order_date, o.$price_column as amount, o.status 
                      FROM orders o ";

// Join with users table if possible
if(in_array($user_id_column, $orders_columns)) {
    $recent_orders_query .= "LEFT JOIN users u ON o.$user_id_column = u.id ";
}

// Join with products table if possible
if(in_array($product_id_column, $orders_columns)) {
    $recent_orders_query .= "LEFT JOIN products p ON o.$product_id_column = p.id ";
}

$recent_orders_query .= "ORDER BY o.$date_column DESC LIMIT 5";

try {
    $recent_orders_result = mysqli_query($conn, $recent_orders_query);
    $recent_orders = [];
    if($recent_orders_result) {
        while($row = mysqli_fetch_assoc($recent_orders_result)) {
            $recent_orders[] = $row;
        }
    }
} catch (Exception $e) {
    // If error occurs, create some dummy data for display
    $recent_orders = [
        [
            'id' => '1', 
            'username' => 'Database Error', 
            'product_name' => 'Could not fetch order data', 
            'order_date' => date('Y-m-d'),
            'amount' => 0,
            'status' => 'Error'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="logo.jpg" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MetaDrop Admin Panel</title>
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
        
        .admin-menu {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .menu-item {
            text-decoration: none;
            background-color: white;
            color: #4361ee;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border: 1px solid #eaeaea;
        }
        
        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.15);
            border-color: #4361ee;
        }
        
        .menu-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #4361ee;
        }
        
        .menu-text {
            font-weight: bold;
            color: #333;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border: 1px solid #eaeaea;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4361ee;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #777;
            font-size: 0.9rem;
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
        
        .logout-btn {
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
        }
        
        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        table th {
            background-color: #f5f7fa;
            color: #333;
            padding: 15px;
            text-align: left;
            font-weight: bold;
            border-bottom: 1px solid #eaeaea;
        }
        
        table td {
            padding: 15px;
            border-bottom: 1px solid #eaeaea;
            color: #555;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        table tr:hover {
            background-color: #f9fafc;
        }
        
        .error-message {
            background-color: rgba(244, 67, 54, 0.1);
            color: #f44336;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .user-info {
                margin-top: 15px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .admin-menu {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header">
            <h1>MetaDrop Admin Dashboard</h1>
            <div class="user-info">
                <div class="avatar">A</div>
                <span class="user-name"><?php echo isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin'; ?></span>
                <a href="logout.php" class="logout-btn" style="margin-left: 15px;">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $product_count; ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user_count; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $new_orders_count; ?></div>
                    <div class="stat-label">New Orders</div>
                </div>
            </div>
            
            <div class="admin-menu">
                <a href="add_product.php" class="menu-item">
                    <div class="menu-icon">+</div>
                    <div class="menu-text">Add New Product</div>
                </a>
                
                <a href="view_products.php" class="menu-item">
                    <div class="menu-icon">📋</div>
                    <div class="menu-text">View Products</div>
                </a>
                
                <a href="purchase_details.php" class="menu-item">
                    <div class="menu-icon">📊</div>
                    <div class="menu-text">Purchase Details</div>
                </a>
                
                <a href="manage_users.php" class="menu-item">
                    <div class="menu-icon">👥</div>
                    <div class="menu-text">Manage Users</div>
                </a>
                
                <a href="settings.php" class="menu-item">
                    <div class="menu-icon">⚙️</div>
                    <div class="menu-text">Settings</div>
                </a>
                
                <a href="reports.php" class="menu-item">
                    <div class="menu-icon">📈</div>
                    <div class="menu-text">Reports</div>
                </a>
            </div>
            
            <h2 style="margin: 30px 0 20px; color: #333;">Recent Orders</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($recent_orders)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No recent orders found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($recent_orders as $order): ?>
                            <tr>
                                <td>#ORD-<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($order['username']); ?></td>
                                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                <td>
                                    <?php 
                                    if(!empty($order['order_date'])) {
                                        try {
                                            echo date('M d, Y', strtotime($order['order_date'])); 
                                        } catch (Exception $e) {
                                            echo $order['order_date'];
                                        }
                                    } else {
                                        echo 'Unknown';
                                    }
                                    ?>
                                </td>
                                <td>$<?php echo number_format(floatval($order['amount']), 2); ?></td>
                                <td>
                                    <?php 
                                    $status = isset($order['status']) ? $order['status'] : 'Unknown';
                                    if($status == 'Completed'): ?>
                                        <span style="color: #4caf50; background-color: rgba(76, 175, 80, 0.1); padding: 5px 10px; border-radius: 20px; font-size: 0.8rem;">Completed</span>
                                    <?php elseif($status == 'Processing'): ?>
                                        <span style="color: #ff9800; background-color: rgba(255, 152, 0, 0.1); padding: 5px 10px; border-radius: 20px; font-size: 0.8rem;">Processing</span>
                                    <?php elseif($status == 'Error'): ?>
                                        <span style="color: #f44336; background-color: rgba(244, 67, 54, 0.1); padding: 5px 10px; border-radius: 20px; font-size: 0.8rem;">Error</span>
                                    <?php else: ?>
                                        <span style="color: #2196f3; background-color: rgba(33, 150, 243, 0.1); padding: 5px 10px; border-radius: 20px; font-size: 0.8rem;"><?php echo $status; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>