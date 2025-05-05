<?php
// Start the session at the very beginning
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$error_message = '';
$debug_info = [];
$success = false;
$total_ksh = 0;
$subtotal_ksh = 0;
$shipping_fee = 0;
$order_items = array();
$order_number = '';

// Function to generate a unique order number
function generateOrderNumber() {
    return 'MTD' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Function to calculate shipping fee
function calculateShippingFee($subtotal) {
    // Free shipping for orders above KSH 10000
    return ($subtotal >= 10000) ? 0 : 300;
}

try {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "metadrop";

    // Create connection with error handling
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
        // Validate delivery details
        if (empty($_POST['delivery_address']) || empty($_POST['phone_number']) || empty($_POST['full_name'])) {
            throw new Exception("Delivery address, phone number, and full name are required");
        }

        // Get user details
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("User not logged in");
        }
        
        $user_id = $_SESSION['user_id'];
        $shipping_address = $_POST['delivery_address'];
        $phone_number = $_POST['phone_number'];
        $full_name = $_POST['full_name'];
        $order_date = date('Y-m-d H:i:s');
        $status = 'pending';
        $payment_status = 'unpaid';
        $order_number = generateOrderNumber();
        
        // Calculate subtotal
        $subtotal_ksh = 0;
        foreach ($_SESSION['cart'] as $product_id => $item) {
            $item_subtotal = $item['price'] * $item['quantity'];
            $subtotal_ksh += $item_subtotal;
        }
        
        // Calculate shipping fee
        $shipping_fee = calculateShippingFee($subtotal_ksh);
        
        // Calculate total including shipping
        $total_ksh = $subtotal_ksh + $shipping_fee;
        
        // Prepare order items as JSON
        $items_json = json_encode($_SESSION['cart']);
        
        // Begin transaction
        $conn->begin_transaction();

        // Insert into orders table
        $insert_order = $conn->prepare("
            INSERT INTO orders (user_id, order_date, total_amount, subtotal_amount, shipping_fee, status, shipping_address, payment_status, items, phone_number, full_name, order_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insert_order->bind_param("isdddsssssss", 
        $user_id, 
        $order_date, 
        $total_ksh,
        $subtotal_ksh,
        $shipping_fee,
        $status, 
        $shipping_address, 
        $payment_status, 
        $items_json,
        $phone_number,
        $full_name,
        $order_number
    );
        
        if (!$insert_order->execute()) {
            throw new Exception("Failed to create order: " . $insert_order->error);
        }
        
        // Get the order ID for reference
        $order_id = $conn->insert_id;
        
        // Store items for display
        $order_items = $_SESSION['cart'];
        
        // Commit transaction
        $conn->commit();
        
        // Send SMS notification
        sendSmsConfirmation($phone_number, $order_number, $total_ksh);
        
        // Clear cart and set success flag
        $success = true;
        unset($_SESSION['cart']);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->connect_error === null) {
        $conn->rollback();
    }
    $error_message = $e->getMessage();
}

// Function to send SMS notification (you'll need to integrate with an SMS service provider)
function sendSmsConfirmation($phone, $order_number, $total_ksh) {
    // This is a placeholder function. You need to integrate with an SMS API like Twilio, Nexmo, etc.
    // Example with Twilio would look something like:
    /*
    require_once 'vendor/autoload.php'; // Include Twilio PHP library
    use Twilio\Rest\Client;
    
    $account_sid = 'YOUR_TWILIO_SID';
    $auth_token = 'YOUR_TWILIO_TOKEN';
    $twilio_number = 'YOUR_TWILIO_PHONE';
    
    $client = new Client($account_sid, $auth_token);
    $message = $client->messages->create(
        $phone,
        array(
            'from' => $twilio_number,
            'body' => "Thank you for your order! Your order #$order_number has been confirmed. Total: KSH " . number_format($total_ksh, 2) . ". Track your order at metadrop.com/track"
        )
    );
    */
    
    // For now we'll just log this action
    error_log("SMS would be sent to $phone: Order #$order_number confirmed. Total: KSH " . number_format($total_ksh, 2));
}

// Get user's existing information for pre-filling the form
$user_data = array('address' => '', 'phone_number' => '', 'full_name' => '');
if (isset($_SESSION['user_id'])) {
    $user_query = $conn->prepare("SELECT address, phone_number, full_name FROM users WHERE user_id = ?");
    $user_query->bind_param("i", $_SESSION['user_id']);
    $user_query->execute();
    $result = $user_query->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
}

// Calculate cart totals for display
$cart_subtotal_ksh = 0;
$cart_shipping_fee = 0;
$cart_total_ksh = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $item_subtotal = $item['price'] * $item['quantity'];
        $cart_subtotal_ksh += $item_subtotal;
    }
    $cart_shipping_fee = calculateShippingFee($cart_subtotal_ksh);
    $cart_total_ksh = $cart_subtotal_ksh + $cart_shipping_fee;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.jpg" type="image/png">
    <title>Order Confirmation | MetaDrop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #5D3FD3;
            --secondary-color: #7B68EE;
            --accent-color: #9370DB;
            --text-color: #333;
            --light-bg: #f8f9fa;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --border-radius: 8px;
            --box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 850px;
            margin: 30px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        h1, h2, h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        h1 {
            text-align: center;
            border-bottom: 2px solid var(--light-bg);
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input, 
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, 
        .form-group textarea:focus {
            border-color: var(--accent-color);
            outline: none;
        }
        
        .error {
            color: var(--danger-color);
            padding: 15px;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .success {
            color: var(--success-color);
            padding: 15px;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            background-color: rgba(40, 167, 69, 0.1);
        }
        
        .button {
            display: inline-block;
            padding: 12px 22px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
        }
        
        .button:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .button i {
            margin-right: 8px;
        }
        
        .cart-summary {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }
        
        .total {
            margin-top: 20px;
            text-align: right;
            padding: 15px 0;
        }
        
        .subtotal-row, .shipping-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .free-shipping-note {
            color: var(--success-color);
            font-size: 0.9em;
            text-align: right;
            margin-top: 5px;
        }
        
        /* Receipt styles */
        .receipt {
            background-color: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            position: relative;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .order-number {
            background-color: var(--light-bg);
            padding: 10px;
            border-radius: var(--border-radius);
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .receipt-section {
            margin-bottom: 25px;
        }
        
        .receipt-section h3 {
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .receipt-subtotal, .receipt-shipping {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-top: 1px dashed #ddd;
        }
        
        .receipt-total {
            font-size: 1.2em;
            font-weight: bold;
            text-align: right;
            padding: 15px 0;
            border-top: 2px dashed #ddd;
            display: flex;
            justify-content: space-between;
        }
        
        .receipt-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .secondary-button {
            background-color: #6c757d;
        }
        
        .secondary-button:hover {
            background-color: #5a6268;
        }
        
        .success-icon {
            font-size: 60px;
            color: var(--success-color);
            display: block;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-20 {
            margin-top: 20px;
        }
        
        /* For printing */
        @media print {
            body * {
                visibility: hidden;
            }
            .receipt, .receipt * {
                visibility: visible;
            }
            .receipt {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
            }
            .receipt-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error_message): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success text-center">
                <i class="fas fa-check-circle success-icon"></i>
                <h1>Order Confirmed!</h1>
                <p>Thank you for your purchase! We've sent a confirmation text to your phone.</p>
            </div>
            
            <div id="receipt" class="receipt">
                <div class="receipt-header">
                    <h2>METADROP</h2>
                    <div class="order-number">
                        <strong>Order #<?php echo htmlspecialchars($order_number); ?></strong>
                    </div>
                    <p><?php echo date('F j, Y g:i A', strtotime($order_date)); ?></p>
                </div>
                
                <div class="receipt-section">
                    <h3>Items Purchased</h3>
                    <?php foreach ($order_items as $item): ?>
                        <?php $item_total_ksh = $item['price'] * $item['quantity']; ?>
                        <div class="receipt-item">
                            <span><?php echo htmlspecialchars($item['quantity']); ?> × <?php echo htmlspecialchars($item['name']); ?></span>
                            <span>KSH <?php echo number_format($item_total_ksh, 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="receipt-subtotal">
                        <span>Subtotal:</span>
                        <span>KSH <?php echo number_format($subtotal_ksh, 2); ?></span>
                    </div>
                    
                    <div class="receipt-shipping">
                        <span>Shipping:</span>
                        <span>
                            <?php if ($shipping_fee > 0): ?>
                                KSH <?php echo number_format($shipping_fee, 2); ?>
                            <?php else: ?>
                                Free
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="receipt-total">
                        <span>Total:</span>
                        <span>KSH <?php echo number_format($total_ksh, 2); ?></span>
                    </div>
                </div>
                
                <div class="receipt-section">
                    <h3>Delivery Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($_POST['full_name']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($_POST['delivery_address']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($_POST['phone_number']); ?></p>
                </div>
                
                <div class="receipt-section">
                    <h3>Order Status</h3>
                    <p>Your order is currently <strong>pending</strong> and will be processed shortly.</p>
                    <p>Payment status: <strong>Awaiting payment</strong></p>
                </div>
            </div>
            
            <div class="receipt-actions">
                <button class="button" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button class="button secondary-button" onclick="downloadReceipt()">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <a href="index.php" class="button">
                    <i class="fas fa-shopping-cart"></i> Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <h1><i class="fas fa-shopping-bag"></i> Complete Your Order</h1>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="full_name"><i class="fas fa-user"></i> Full Name:</label>
                    <input type="text" name="full_name" id="full_name" 
                           value="<?php echo isset($user_data['full_name']) ? htmlspecialchars($user_data['full_name']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="delivery_address"><i class="fas fa-map-marker-alt"></i> Delivery Address:</label>
                    <textarea name="delivery_address" id="delivery_address" rows="3" required><?php echo isset($user_data['address']) ? htmlspecialchars($user_data['address']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="phone_number"><i class="fas fa-phone"></i> Phone Number:</label>
                    <input type="tel" name="phone_number" id="phone_number" 
                           value="<?php echo isset($user_data['phone_number']) ? htmlspecialchars($user_data['phone_number']) : ''; ?>" required>
                </div>
                
                <div class="cart-summary">
                    <h2><i class="fas fa-clipboard-list"></i> Order Summary</h2>
                    <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
                        <?php 
                        foreach ($_SESSION['cart'] as $item): 
                            $item_subtotal_ksh = $item['price'] * $item['quantity'];
                        ?>
                            <div class="item">
                                <span><?php echo htmlspecialchars($item['name']); ?> × <?php echo $item['quantity']; ?></span>
                                <span>KSH <?php echo number_format($item_subtotal_ksh, 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="total">
                            <div class="subtotal-row">
                                <span>Subtotal:</span>
                                <span>KSH <?php echo number_format($cart_subtotal_ksh, 2); ?></span>
                            </div>
                            <div class="shipping-row">
                                <span>Shipping:</span>
                                <span>
                                    <?php if ($cart_shipping_fee > 0): ?>
                                        KSH <?php echo number_format($cart_shipping_fee, 2); ?>
                                    <?php else: ?>
                                        Free
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($cart_shipping_fee == 0): ?>
                                <div class="free-shipping-note">
                                    <i class="fas fa-truck"></i> Free shipping on orders over KSH 10,000
                                </div>
                            <?php endif; ?>
                            <h3>Total: KSH <?php echo number_format($cart_total_ksh, 2); ?></h3>
                        </div>
                        
                        <button type="submit" name="submit_order" class="button">
                            <i class="fas fa-check-circle"></i> Confirm Order
                        </button>
                    <?php else: ?>
                        <p>Your cart is empty.</p>
                        <a href="index.php" class="button">
                            <i class="fas fa-arrow-left"></i> Return to Shop
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Scripts for receipt functionality -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function printReceipt() {
            window.print();
        }
        
        function downloadReceipt() {
            const element = document.getElementById('receipt');
            const opt = {
                margin: 1,
                filename: 'metadrop-receipt-<?php echo isset($order_number) ? $order_number : date('YmdHis'); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>

<?php
// Close the database connection
if (isset($conn)) {
    $conn->close();
}
?>