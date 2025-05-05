<?php
session_start();
require_once 'config.php';

// Security checks
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];

// Calculate subtotal and shipping
$subtotal = 0;
$shipping = 0;

// Remove product from cart
if (isset($_POST['remove_product'])) {
    $product_id = $_POST['product_id'];
    unset($_SESSION['cart'][$product_id]);
    header("Location: checkout.php");
    exit();
}

// Recalculate subtotal
foreach ($_SESSION['cart'] as $product_id => $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

// Apply shipping logic
// Free shipping for orders above 10,000 KSH, otherwise 300 KSH
$shipping = ($subtotal >= 10000) ? 0 : 300;
$total = $subtotal + $shipping;

// Process Mpesa Payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proceed_payment'])) {
    $phone_number = $_POST['phone_number'] ?? '';
    
    // Remove non-digit characters
    $clean_number = preg_replace('/\D/', '', $phone_number);
    
    // Validate phone number
    if (empty($clean_number)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^(254|0)\d{9}$/', $clean_number)) {
        $errors[] = "Invalid phone number. Use format 254XXXXXXXXX or 0XXXXXXXXX";
    }
    
    if (empty($errors)) {
        // Redirect to order confirmation or further processing
        $_SESSION['mpesa_phone'] = $clean_number;
        header("Location: order_confirmation.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - MetaDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="icon" href="logo.jpg" type="image/png">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Checkout</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="grid md:grid-cols-2 gap-8">
            <!-- Cart Details -->
            <div>
                <h2 class="text-2xl font-semibold mb-4">Order Summary</h2>
                <?php if (empty($_SESSION['cart'])): ?>
                    <p class="text-gray-600">Your cart is empty</p>
                <?php else: ?>
                    <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                        <div class="flex justify-between items-center border-b py-4">
                            <div class="flex items-center">
                                <img 
                                    src="<?= htmlspecialchars($item['image']) ?>" 
                                    alt="<?= htmlspecialchars($item['name']) ?>" 
                                    class="w-20 h-20 object-cover mr-4"
                                >
                                <div>
                                    <h3 class="font-bold"><?= htmlspecialchars($item['name']) ?></h3>
                                    <p class="text-gray-600">
                                        KSh <?= number_format($item['price'], 2) ?> x <?= $item['quantity'] ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <span class="font-bold mr-4">
                                    KSh <?= number_format($item['price'] * $item['quantity'], 2) ?>
                                </span>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                                    <button 
                                        type="submit" 
                                        name="remove_product" 
                                        class="text-red-500 hover:text-red-700"
                                    >
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="mt-6 text-right">
                        <p class="text-lg">Subtotal: KSh <?= number_format($subtotal, 2) ?></p>
                        <p class="text-lg">
                            Shipping: 
                            <?php if ($shipping > 0): ?>
                                KSh <?= number_format($shipping, 2) ?>
                            <?php else: ?>
                                <span class="text-green-600 font-medium">FREE</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-gray-500 text-sm italic mb-2">
                            <?php if ($subtotal >= 10000): ?>
                                Free shipping on orders above KSh 10,000
                            <?php else: ?>
                                Free shipping on orders above KSh 10,000 (KSh <?= number_format(10000 - $subtotal, 2) ?> away)
                            <?php endif; ?>
                        </p>
                        <p class="text-2xl font-bold">Total: KSh <?= number_format($total, 2) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Payment Form -->
            <div>
                <h2 class="text-2xl font-semibold mb-4">Mpesa Payment</h2>
                <form method="POST" class="bg-white p-6 rounded-lg shadow-md">
                    <div class="mb-4">
                        <label class="block mb-2">Phone Number</label>
                        <input 
                            type="tel" 
                            name="phone_number"
                            class="w-full border rounded px-3 py-2" 
                            placeholder="254XXXXXXXXX or 0XXXXXXXXX" 
                            required
                            pattern="(254|0)\d{9}"
                            maxlength="12"
                        >
                        <p class="text-sm text-gray-600 mt-1">
                            Enter the M-Pesa registered phone number
                        </p>
                    </div>
                    <button 
                        type="submit" 
                        name="proceed_payment"
                        class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700"
                    >
                        Proceed to Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>