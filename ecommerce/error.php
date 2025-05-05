<?php
session_start();
$error_message = $_SESSION['order_error'] ?? "Unknown order processing error";
unset($_SESSION['order_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Error - MetaDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="icon" href="logo.jpg" type="image/png">
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md text-center max-w-md">
        <svg class="mx-auto mb-4 h-16 w-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        
        <h1 class="text-2xl font-bold text-red-600 mb-4">Order Processing Error</h1>
        
        <p class="text-gray-700 mb-6">
            <?= htmlspecialchars($error_message) ?>
        </p>
        
        <div class="flex justify-center space-x-4">
            <a href="cart.php" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                Return to Cart
            </a>
            <a href="index.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">
                Home
            </a>
        </div>
    </div>
</body>
</html>