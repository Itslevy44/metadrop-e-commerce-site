<?php
// Start the session
session_start();



// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "metadrop";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle order status updates
if (isset($_POST['update_order'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['new_status'];
    
    // Validate status
    $allowed_statuses = ['approved', 'declined', 'pending'];
    if (in_array($new_status, $allowed_statuses)) {
        $update_sql = "UPDATE orders SET status = ? WHERE order_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_status, $order_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get orders query - removed updated_at column
$sql = "SELECT 
            o.order_id,
            o.user_id,
            o.order_date,
            o.total_amount,
            o.status,
            o.shipping_address,
            o.payment_status,
            o.phone_number,
            o.full_name,
            o.items,
            u.username
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        ORDER BY o.order_date DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<link rel="icon" href="logo.jpg" type="image/png">
    <link rel="icon" href="logo.jpg" type="image/png">
    <title>Order Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h2 {
            color: #333;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .back-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .back-button:hover {
            background-color: #0056b3;
        }

        .action-button {
            padding: 6px 12px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-right: 5px;
        }

        .approve-button {
            background-color: #28a745;
        }

        .approve-button:hover {
            background-color: #218838;
        }

        .decline-button {
            background-color: #dc3545;
        }

        .decline-button:hover {
            background-color: #c82333;
        }

        .status-approved {
            color: #28a745;
            font-weight: bold;
        }

        .status-declined {
            color: #dc3545;
            font-weight: bold;
        }

        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }

        .no-records {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .amount {
            font-family: monospace;
            text-align: right;
        }

        .address-info {
            max-width: 200px;
            word-wrap: break-word;
        }

        .items-container {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 5px;
            background-color: #f9f9f9;
        }

        .item {
            padding: 5px;
            border-bottom: 1px solid #eee;
        }

        .item:last-child {
            border-bottom: none;
        }

        details summary {
            cursor: pointer;
            color: #007bff;
            font-weight: bold;
        }

        details summary:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Order Management</h2>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Full Name</th>
                    <th>Phone Number</th>
                    <th>Shipping Address</th>
                    <th>Order Items</th>
                    <th>Total Amount</th>
                    <th>Order Date</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result && $result->num_rows > 0): 
                    while($row = $result->fetch_assoc()): 
                        // Parse the JSON items
                        $items = json_decode($row['items'], true);
                ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['order_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                            <td class="address-info"><?php echo htmlspecialchars($row['shipping_address']); ?></td>
                            <td>
                                <details>
                                    <summary>View Items (<?php echo count($items); ?>)</summary>
                                    <div class="items-container">
                                        <?php foreach ($items as $item): ?>
                                            <div class="item">
                                                <?php echo htmlspecialchars($item['name']); ?> - 
                                                $<?php echo number_format($item['price'], 2); ?> × 
                                                <?php echo $item['quantity']; ?> = 
                                                $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            </td>
                            <td class="amount">$<?php echo number_format($row['total_amount'], 2); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($row['order_date'])); ?></td>
                            <td>
                                <span class="status-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst(htmlspecialchars($row['payment_status'])); ?></td>
                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                        <input type="hidden" name="new_status" value="approved">
                                        <button type="submit" name="update_order" class="action-button approve-button">
                                            Approve
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                        <input type="hidden" name="new_status" value="declined">
                                        <button type="submit" name="update_order" class="action-button decline-button">
                                            Decline
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                        <input type="hidden" name="new_status" value="pending">
                                        <button type="submit" name="update_order" class="action-button">
                                            Reset to Pending
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                    <tr>
                        <td colspan="11" class="no-records">No order records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="admin.php" class="back-button">Back to Dashboard</a>
    </div>

    <?php
    // Close the database connection
    if (isset($conn)) {
        $conn->close();
    }
    ?>
</body>
</html>