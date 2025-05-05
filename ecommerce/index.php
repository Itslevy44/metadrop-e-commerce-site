<?php session_start(); 
require_once 'config.php';

// Redirect if not logged in 
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// User Information
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

// Fetch all active products directly from products table
$products_stmt = $conn->prepare("SELECT * FROM products WHERE is_active = 1");
$products_stmt->execute();
$all_products = $products_stmt->get_result();

// Fetch product categories from products table
$categories_stmt = $conn->prepare("SELECT DISTINCT category FROM products WHERE is_active = 1");
$categories_stmt->execute();
$categories_result = $categories_stmt->get_result();
$categories = [];
while ($category_row = $categories_result->fetch_assoc()) {
    if (!empty($category_row['category'])) {
        $categories[] = $category_row['category'];
    }
}

// Cart functionality
if (isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    if ($product && $quantity <= $product['quantity']) {
        // If product already in cart, update quantity
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'image' => $product['image'] // Using the image field directly from products table
            ];
        }
        // Add success message
        $_SESSION['cart_message'] = "Added " . $product['name'] . " to your cart!";
    }
}

// Update cart quantity
if (isset($_POST['update_quantity'])) {
    $product_id = $_POST['product_id'];
    $new_quantity = $_POST['new_quantity'];
    
    if ($new_quantity <= 0) {
        unset($_SESSION['cart'][$product_id]);
    } else {
        $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
    }
}

// Remove from cart
if (isset($_POST['remove_from_cart'])) {
    $product_id = $_POST['product_id'];
    unset($_SESSION['cart'][$product_id]);
    // Add success message
    $_SESSION['cart_message'] = "Item removed from cart";
}

// Calculate cart total
$cart_total = 0;
$cart_items_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_total += $item['price'] * $item['quantity'];
        $cart_items_count += $item['quantity'];
    }
}

// Filter by category if requested
$selected_category = isset($_GET['category']) ? $_GET['category'] : '';

// If filtering by category, modify the product query
if (!empty($selected_category)) {
    $products_stmt = $conn->prepare("SELECT * FROM products WHERE category = ? AND is_active = 1");
    $products_stmt->bind_param("s", $selected_category);
    $products_stmt->execute();
    $all_products = $products_stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MetaDrop - Modern E-Commerce Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="icon" href="logo.jpg" type="image/png">
    <style>
        .dark-mode {
            @apply bg-gray-900 text-gray-100;
        }
        .dark-mode .bg-white {
            @apply bg-gray-800;
        }
        .dark-mode .text-gray-600, .dark-mode .text-gray-700, .dark-mode .text-gray-800 {
            @apply text-gray-300;
        }
        .dark-mode .shadow-md {
            @apply shadow-gray-700;
        }
        
        /* Custom animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .slide-in {
            animation: slideIn 0.5s ease-in-out;
        }
        
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #6366F1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #4F46E5;
        }
        
        /* Tooltip */
        .tooltip {
            position: relative;
        }
        
        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        .tooltip-text {
            visibility: hidden;
            width: 120px;
            background-color: #6366F1;
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
        }
    </style>
</head>
<body 
    x-data="{ 
        cartOpen: false, 
        mobileMenuOpen: false, 
        searchTerm: '',
        darkMode: localStorage.getItem('darkMode') === 'true',
        selectedCategory: '<?= $selected_category ?>',
        isLoading: false,
        notification: '<?= isset($_SESSION['cart_message']) ? $_SESSION['cart_message'] : '' ?>',
        showWishlist: false,
        wishlist: JSON.parse(localStorage.getItem('wishlist') || '[]'),
        addToWishlist(productId, productName) {
            if (!this.wishlist.find(item => item.id === productId)) {
                this.wishlist.push({id: productId, name: productName});
                localStorage.setItem('wishlist', JSON.stringify(this.wishlist));
                this.notification = productName + ' added to wishlist';
                setTimeout(() => this.notification = '', 3000);
            }
        },
        removeFromWishlist(productId) {
            this.wishlist = this.wishlist.filter(item => item.id !== productId);
            localStorage.setItem('wishlist', JSON.stringify(this.wishlist));
        },
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode);
        }
    }"
    :class="{ 'dark-mode': darkMode }"
    class="min-h-screen flex flex-col bg-gray-50"
    @keydown.escape="cartOpen = false; mobileMenuOpen = false; showWishlist = false"
>
    <!-- Notification Toast -->
    <div
        x-show="notification"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform scale-90"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-90"
        @click="notification = ''"
        class="fixed top-20 right-4 bg-indigo-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 cursor-pointer"
        x-init="setTimeout(() => notification = '', 3000)"
    >
        <span x-text="notification"></span>
    </div>

    <!-- Navigation -->
    <nav class="bg-white shadow-md fixed w-full z-40 transition-all duration-300" :class="{'bg-opacity-95': !mobileMenuOpen}">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <img src="logo.jpg" alt="MetaDrop Logo" class="h-10 w-10 mr-2 rounded-full">
                        <h1 class="text-2xl font-bold text-indigo-600">MetaDrop</h1>
                    </div>
                    
                    <!-- Desktop Navigation Links -->
                    <div class="hidden md:ml-6 md:flex md:items-center md:space-x-4">
                        <a href="index.php" class="text-gray-600 hover:text-indigo-600 px-3 py-2 rounded-md transition-colors">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                        <a href="#featured" class="text-gray-600 hover:text-indigo-600 px-3 py-2 rounded-md transition-colors">
                            <i class="fas fa-fire mr-1"></i> Featured
                        </a>
                        <a href="about.php" class="text-gray-600 hover:text-indigo-600 px-3 py-2 rounded-md transition-colors">
                            <i class="fas fa-info-circle mr-1"></i> About
                        </a>
                        <a href="contact.php" class="text-gray-600 hover:text-indigo-600 px-3 py-2 rounded-md transition-colors">
                            <i class="fas fa-envelope mr-1"></i> Contact
                        </a>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="text-gray-600 hover:text-indigo-600 px-3 py-2 rounded-md transition-colors">
                                <i class="fas fa-tag mr-1"></i> Categories <i class="fas fa-chevron-down text-xs ml-1"></i>
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute mt-2 w-48 bg-white rounded-md shadow-lg z-50">
                                <a href="index.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-100">All Products</a>
                                <?php foreach ($categories as $category): ?>
                                <a href="index.php?category=<?= urlencode($category) ?>" class="block px-4 py-2 text-gray-700 hover:bg-indigo-100">
                                    <?= htmlspecialchars($category) ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Right Navigation Items -->
               
                    
                    <div class="flex items-center space-x-4">
                        <button 
                            @click="showWishlist = !showWishlist" 
                            class="text-gray-600 hover:text-indigo-600 relative tooltip"
                        >
                            <i class="fas fa-heart text-xl"></i>
                            <span x-show="wishlist.length > 0" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full px-2 py-1 text-xs">
                                <span x-text="wishlist.length"></span>
                            </span>
                            <span class="tooltip-text">Wishlist</span>
                        </button>
                        
                        <a href="profile.php" class="text-gray-600 hover:text-indigo-600 tooltip">
                            <i class="fas fa-user text-xl"></i>
                            <span class="tooltip-text">Profile</span>
                        </a>
                        
                        <button 
                            @click="cartOpen = !cartOpen" 
                            class="text-gray-600 hover:text-indigo-600 relative tooltip"
                        >
                            <i class="fas fa-shopping-cart text-xl"></i>
                            <span x-show="<?= $cart_items_count ?> > 0" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full px-2 py-1 text-xs">
                                <?= $cart_items_count ?>
                            </span>
                            <span class="tooltip-text">Cart</span>
                        </button>
                        
                        <a href="logout.php" class="text-gray-600 hover:text-indigo-600 tooltip">
                            <i class="fas fa-sign-out-alt text-xl"></i>
                            <span class="tooltip-text">Logout</span>
                        </a>
                        
                        <button 
                            @click="toggleDarkMode()" 
                            class="text-gray-600 hover:text-indigo-600 tooltip"
                        >
                            <i class="fas" :class="darkMode ? 'fa-sun' : 'fa-moon'"></i>
                            <span class="tooltip-text" x-text="darkMode ? 'Light Mode' : 'Dark Mode'"></span>
                        </button>
                    </div>
                </div>
                
                <!-- Mobile menu button -->
                <div class="flex items-center md:hidden">
                    <button 
                        @click="cartOpen = !cartOpen" 
                        class="text-gray-600 hover:text-indigo-600 px-2 relative mr-2"
                    >
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <span x-show="<?= $cart_items_count ?> > 0" class="absolute -top-2 -right-1 bg-red-500 text-white rounded-full px-2 py-1 text-xs">
                            <?= $cart_items_count ?>
                        </span>
                    </button>
                    
                    <button 
                        @click="mobileMenuOpen = !mobileMenuOpen" 
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100"
                    >
                        <i class="fas" :class="mobileMenuOpen ? 'fa-times' : 'fa-bars'"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div 
                x-show="mobileMenuOpen" 
                class="md:hidden bg-white shadow-md"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
            >
                <div class="py-2">
                    <div class="px-4 py-2">
                        <input 
                            x-model="searchTerm" 
                            type="text" 
                            placeholder="Search products..." 
                            class="border border-gray-300 rounded-md px-3 py-2 w-full"
                        >
                    </div>
                    
                    <a href="index.php" class="block px-4 py-2 text-base font-medium text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                    <a href="#featured" class="block px-4 py-2 text-base font-medium text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-fire mr-2"></i> Featured
                    </a>
                    <a href="about.php" class="block px-4 py-2 text-base font-medium text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-info-circle mr-2"></i> About Us
                    </a>
                    <a href="contact.php" class="block px-4 py-2 text-base font-medium text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-envelope mr-2"></i> Contact Us
                    </a>
                    
                    <div class="px-4 py-2" x-data="{ categoriesOpen: false }">
                        <button 
                            @click="categoriesOpen = !categoriesOpen" 
                            class="flex justify-between items-center w-full text-left text-base font-medium text-gray-700 hover:bg-gray-100 py-2"
                        >
                            <span><i class="fas fa-tag mr-2"></i> Categories</span>
                            <i class="fas" :class="categoriesOpen ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                        </button>
                        <div x-show="categoriesOpen" class="pl-4 space-y-1 mt-2">
                            <a href="index.php" class="block py-2 text-base font-medium text-gray-700 hover:text-indigo-600">
                                All Products
                            </a>
                            <?php foreach ($categories as $category): ?>
                            <a href="index.php?category=<?= urlencode($category) ?>" class="block py-2 text-base font-medium text-gray-700 hover:text-indigo-600">
                                <?= htmlspecialchars($category) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4 pb-3">
                        <div class="px-4 flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-circle text-2xl text-gray-500"></i>
                            </div>
                            <div class="ml-3">
                                <div class="text-base font-medium text-gray-800"><?= htmlspecialchars($user['username']) ?></div>
                                <div class="text-sm font-medium text-gray-500"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                        </div>
                        <div class="mt-3 space-y-1 px-2">
                            <a href="profile.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i> Profile
                            </a>
                            <button 
                                @click="showWishlist = !showWishlist; mobileMenuOpen = false" 
                                class="w-full text-left block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100"
                            >
                                <i class="fas fa-heart mr-2"></i> Wishlist
                            </button>
                            <button 
                                @click="toggleDarkMode()" 
                                class="w-full text-left block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100"
                            >
                                <i class="fas mr-2" :class="darkMode ? 'fa-sun' : 'fa-moon'"></i>
                                <span x-text="darkMode ? 'Light Mode' : 'Dark Mode'"></span>
                            </button>
                            <a href="admin_login.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-shield-alt mr-2"></i> Admin Panel
                            </a>
                            <a href="logout.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i> Log Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Cart Sidebar -->
    <div 
        x-show="cartOpen" 
        class="fixed inset-0 overflow-hidden z-50"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.away="cartOpen = false"
    >
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex">
                <div 
                    class="w-screen max-w-md"
                    x-transition:enter="transform transition ease-in-out duration-500"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-500"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                >
                    <div class="h-full flex flex-col bg-white shadow-xl overflow-y-auto">
                        <div class="p-6 bg-indigo-600 text-white">
                            <div class="flex items-center justify-between">
                                <h2 class="text-2xl font-bold">Your Cart</h2>
                                <button @click="cartOpen = false" class="text-white hover:text-gray-200">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-sm text-indigo-100"><?= $cart_items_count ?> items</p>
                        </div>

                        <div class="flex-1 overflow-y-auto p-6">
                            <?php if (empty($_SESSION['cart'])): ?>
                                <div class="flex flex-col items-center justify-center h-full">
                                    <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                                    <p class="text-center text-xl text-gray-500">Your cart is empty</p>
                                    <button 
                                        @click="cartOpen = false" 
                                        class="mt-4 bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition-colors"
                                    >
                                        Continue Shopping
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="space-y-6">
                                    <?php 
                                    $total = 0;
                                    foreach ($_SESSION['cart'] as $product_id => $item): 
                                        $subtotal = $item['price'] * $item['quantity'];
                                        $total += $subtotal;
                                    ?>
                                    <div class="flex space-x-4 border-b pb-4">
                                        <img src="<?= htmlspecialchars($item['image']) ?>" class="w-20 h-20 object-cover rounded">
                                        <div class="flex-1">
                                            <h3 class="font-bold text-lg"><?= htmlspecialchars($item['name']) ?></h3>
                                            <p class="text-gray-600">KSH<?= number_format($item['price'], 2) ?></p>
                                            <div class="flex items-center mt-2">
                                                <form method="POST" class="flex items-center">
                                                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                                                    <button 
                                                        type="submit" 
                                                        name="update_quantity" 
                                                        value="decrease"
                                                        onclick="this.form.elements.new_quantity.value = Math.max(1, parseInt(this.form.elements.new_quantity.value) - 1)"
                                                        class="bg-gray-200 text-gray-700 rounded-l-md px-2 py-1 hover:bg-gray-300"
                                                    >
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <input 
                                                        type="number" 
                                                        name="new_quantity" 
                                                        value="<?= $item['quantity'] ?>" 
                                                        min="1" 
                                                        class="w-12 text-center border-t border-b border-gray-200"
                                                        onchange="this.form.submit()"
                                                    >
                                                    <button 
                                                        type="submit" 
                                                        name="update_quantity" 
                                                        value="increase"
                                                        onclick="this.form.elements.new_quantity.value = parseInt(this.form.elements.new_quantity.value) + 1"
                                                        class="bg-gray-200 text-gray-700 rounded-r-md px-2 py-1 hover:bg-gray-300"
                                                    >
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </form>
                                                <span class="ml-auto font-semibold">KSH<?= number_format($subtotal, 2) ?></span>
                                                <form method="POST" class="ml-2">
                                                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                                                    <button 
                                                        type="submit" 
                                                        name="remove_from_cart" 
                                                        class="text-red-500 hover:text-red-700 transition-colors"
                                                        title="Remove item"
                                                    >
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($_SESSION['cart'])): ?>
                        <div class="border-t border-gray-200 p-6 space-y-4">
                            <div class="flex justify-between text-base font-medium text-gray-900">
                                <p>Subtotal</p>
                                <p>KSH<?= number_format($total, 2) ?></p>
                            </div>
                            <div class="flex justify-between text-sm text-gray-500">
                                <p>Shipping</p>
                                <p>KSH<?= number_format($total > 10000 ? 0 : 300, 2) ?></p>

                            </div>
                            <div class="border-t pt-4 flex justify-between text-lg font-bold">
                                <p>Total</p>
                                <p>KSH<?= number_format($total > 10000 ? $total : $total + 300, 2) ?></p>
                            </div>
                            <p class="text-sm text-gray-500">
                                <?= $total > 10,000 ? 'Free shipping applied!' : 'Free shipping on orders over KSH 10,000' ?>
                            </p>
                            <div class="mt-6">
                                <form action="checkout.php" method="POST">
                                    <input type="hidden" name="total" value="<?= $total > 50 ? $total : $total + 5.99 ?>">
                                    <button 
                                        type="submit" 
                                        class="w-full flex justify-center items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors"
                                    >
                                        <i class="fas fa-credit-card mr-2"></i> Checkout
                                    </button>
                                </form>
                                <div class="mt-2 flex justify-center text-sm text-gray-500">
                                    <p>
                                        or <button type="button" @click="cartOpen = false" class="text-indigo-600 font-medium hover:text-indigo-500">
                                            Continue Shopping<span aria-hidden="true"> &rarr;</span>
                                        </button>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-lock text-green-500 mr-2"></i>
                                <p class="text-xs text-gray-500">Secure checkout. We accept all major credit cards, PayPal, and more.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Wishlist Sidebar -->
    <div 
        x-show="showWishlist" 
        class="fixed inset-0 overflow-hidden z-50"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.away="showWishlist = false"
    >
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex">
                <div 
                    class="w-screen max-w-md"
                    x-transition:enter="transform transition ease-in-out duration-500"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-500"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                >
                    <div class="h-full flex flex-col bg-white shadow-xl overflow-y-auto">
                        <div class="p-6 bg-pink-600 text-white">
                            <div class="flex items-center justify-between">
                                <h2 class="text-2xl font-bold">Your Wishlist</h2>
                                <button @click="showWishlist = false" class="text-white hover:text-gray-200">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-sm text-pink-100" x-text="wishlist.length + ' items'"></p>
                        </div>

                        <div class="flex-1 overflow-y-auto p-6">
                            <template x-if="wishlist.length === 0">
                                <div class="flex flex-col items-center justify-center h-full">
                                    <i class="fas fa-heart text-6xl text-gray-300 mb-4"></i>
                                    <p class="text-center text-xl text-gray-500">Your wishlist is empty</p>
                                    <button 
                                        @click="showWishlist = false" 
                                        class="mt-4 bg-pink-600 text-white px-4 py-2 rounded hover:bg-pink-700 transition-colors"
                                    >
                                        Browse Products
                                    </button>
                                </div>
                            </template>
                            
                            <div class="space-y-6">
                                <template x-for="item in wishlist" :key="item.id">
                                    <div class="flex space-x-4 border-b pb-4">
                                        <div class="w-20 h-20 bg-gray-200 rounded flex items-center justify-center">
                                            <i class="fas fa-image text-gray-400 text-2xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-bold text-lg" x-text="item.name"></h3>
                                            <div class="flex mt-4">
                                                <form method="POST" class="mr-2">
                                                    <input type="hidden" name="product_id" :value="item.id">
                                                    <input type="hidden" name="quantity" value="1">
                                                    <button 
                                                        type="submit" 
                                                        name="add_to_cart" 
                                                        class="bg-indigo-600 text-white px-3 py-1 rounded text-sm hover:bg-indigo-700"
                                                        @click="showWishlist = false"
                                                    >
                                                        Add to Cart
                                                    </button>
                                                </form>
                                                <button 
                                                    @click="removeFromWishlist(item.id)" 
                                                    class="text-red-500 hover:text-red-700 transition-colors text-sm"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="flex-grow pt-16">
        <!-- Hero Section -->
        <header class="relative pt-16 pb-16 flex content-center items-center justify-center" style="min-height: 75vh;">
            <div class="absolute top-0 w-full h-full bg-center bg-cover" style="background-image: url('https://source.unsplash.com/random/1600x900?technology');">
                <span class="w-full h-full absolute opacity-75 bg-black"></span>
            </div>
            <div class="container relative mx-auto">
                <div class="items-center flex flex-wrap">
                    <div class="w-full lg:w-6/12 px-4 ml-auto mr-auto text-center">
                        <div class="pr-12 slide-in">
                            <h1 class="text-white font-bold text-5xl mb-6">
                                Welcome to <span class="text-indigo-400">MetaDrop</span>, <?= htmlspecialchars($user['username']) ?>!
                            </h1>
                            <p class="mt-4 text-xl text-gray-200">
                                Discover the future with our curated collection of cutting-edge products.
                            </p>
                            <div class="mt-8 flex justify-center">
                                <a href="#products" class="bg-indigo-600 text-white font-bold px-6 py-3 rounded-lg mr-4 hover:bg-indigo-700 transition-colors">
                                    Shop Now
                                </a>
                                <a href="#featured" class="bg-transparent border-2 border-white text-white font-bold px-6 py-3 rounded-lg hover:bg-white hover:text-indigo-600 transition-colors">
                                    Featured Products
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="absolute bottom-0 w-full">
                <svg class="w-full h-12 text-white fill-current" viewBox="0 0 1200 120" preserveAspectRatio="none">
                    <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z"></path>
                </svg>
            </div>
        </header>

        <!-- Features Section -->
        <section class="py-12 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                        Why Choose MetaDrop?
                    </h2>
                    <p class="mt-4 text-xl text-gray-600">
                        The ultimate shopping experience with benefits that set us apart.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="bg-indigo-50 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                        <div class="text-indigo-600 mb-4">
                            <i class="fas fa-shipping-fast text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Free & Fast Shipping</h3>
                        <p class="text-gray-600">
                            Enjoy free shipping on all orders over ksh 10,000, with delivery within 2-3 business days.
                        </p>
                    </div>
                    
                    <div class="bg-indigo-50 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                        <div class="text-indigo-600 mb-4">
                            <i class="fas fa-exchange-alt text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">30-Day Returns</h3>
                        <p class="text-gray-600">
                            Not satisfied? Return any product within 30 days for a full refund, no questions asked.
                        </p>
                    </div>
                    
                    <div class="bg-indigo-50 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                        <div class="text-indigo-600 mb-4">
                            <i class="fas fa-shield-alt text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Secure Payment</h3>
                        <p class="text-gray-600">
                            Shop with confidence knowing your payment information is secure with our encrypted system.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Products Section -->
        <section id="featured" class="py-16 bg-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                        Featured Products
                    </h2>
                    <p class="mt-4 text-xl text-gray-600">
                        Hand-picked selections from our latest collections.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <?php 
                    // Reset the internal pointer of the result set
                    $all_products->data_seek(0);
                    $featured_count = 0;
                    while($product = $all_products->fetch_assoc()): 
                        if ($featured_count >= 3) break; // Only show 3 featured products
                        if (isset($product['is_featured']) && $product['is_featured']):
                            $featured_count++;
                    ?>
                    <div class="bg-white rounded-xl shadow-md overflow-hidden transform transition duration-300 hover:shadow-xl hover:-translate-y-2">
                        <div class="relative">
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" class="w-full h-64 object-cover">
                            <div class="absolute top-2 left-2 bg-indigo-600 text-white px-3 py-1 rounded-full text-sm font-semibold">
                                Featured
                            </div>
                            <button 
                                @click="addToWishlist(<?= $product['product_id'] ?>, '<?= htmlspecialchars($product['name']) ?>')"
                                class="absolute top-2 right-2 bg-white p-2 rounded-full text-gray-500 hover:text-red-500 transition-colors"
                            >
                                <i class="fas fa-heart" :class="wishlist.find(item => item.id === <?= $product['product_id'] ?>) ? 'text-red-500' : ''"></i>
                            </button>
                        </div>
                        <div class="p-6">
                            <h3 class="font-bold text-xl mb-2"><?= htmlspecialchars($product['name']) ?></h3>
                            <div class="flex items-center mb-2">
                                <div class="flex text-yellow-400">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <i class="fas fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-gray-500 text-sm ml-2">(<?= rand(10, 500) ?> reviews)</span>
                            </div>
                            <p class="text-gray-600 mb-4"><?= htmlspecialchars(substr($product['description'], 0, 100)) ?>...</p>
                            <div class="flex justify-between items-center">
                                <span class="text-2xl font-bold text-indigo-600">
                                    KSH<?= number_format($product['price'], 2) ?>
                                </span>
                                <form method="POST" class="flex items-center">
                                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button 
                                        type="submit" 
                                        name="add_to_cart" 
                                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
                                    >
                                        <i class="fas fa-shopping-cart mr-2"></i> Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php 
                        endif;
                    endwhile; 
                    ?>
                </div>
                
                <div class="mt-12 text-center">
                    <a href="#products" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 transition-colors">
                        View All Products <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- Category Filter Section -->
        <section class="py-8 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Browse Categories</h2>
                    <div class="flex items-center mt-4 md:mt-0">
                        <span class="mr-2 text-gray-600">Sort by:</span>
                        <select class="border rounded-md px-3 py-1">
                            <option>Newest</option>
                            <option>Price: Low to High</option>
                            <option>Price: High to Low</option>
                            <option>Most Popular</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-2 mb-8">
                    <a href="index.php" class="px-4 py-2 rounded-full text-sm font-medium <?= empty($selected_category) ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> transition-colors">
                        All Products
                    </a>
                    <?php foreach ($categories as $category): ?>
                    <a 
                        href="index.php?category=<?= urlencode($category) ?>" 
                        class="px-4 py-2 rounded-full text-sm font-medium <?= $selected_category === $category ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> transition-colors"
                    >
                        <?= htmlspecialchars($category) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Product Grid with Enhanced Search -->
<section id="products" class="py-12 bg-gray-50" x-data="{ searchTerm: '' }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-8 text-center">Our Products</h2>
        
        <!-- Search form with button -->
        <div class="mb-6 max-w-md mx-auto">
            <form @submit.prevent="executeSearch()" class="flex items-center">
                <div class="relative flex-grow">
                    <input 
                        type="text" 
                        x-model="searchTerm" 
                        placeholder="Search products..." 
                        class="w-full px-4 py-2 border border-gray-300 rounded-l-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    >
                    <button 
                        type="button"
                        x-show="searchTerm" 
                        @click="searchTerm = ''" 
                        class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <button 
                    type="submit"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-r-md hover:bg-indigo-700 transition-colors"
                >
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        
        <!-- No results message -->
        <div 
            x-show="searchTerm && !Array.from(document.querySelectorAll('[data-product-container]')).some(el => !el.classList.contains('hidden'))"
            class="my-16 text-center"
        >
            <i class="fas fa-search text-5xl text-gray-300 mb-4"></i>
            <h3 class="text-2xl font-medium text-gray-600">No products found matching "<span x-text="searchTerm"></span>"</h3>
            <p class="mt-2 text-gray-500">Try adjusting your search or filter to find what you're looking for.</p>
            <button 
                @click="searchTerm = ''" 
                class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                Clear Search
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php 
            // Reset the internal pointer of the result set
            $all_products->data_seek(0);
            while($product = $all_products->fetch_assoc()): 
                // Skip if filtering by category and doesn't match
                if (!empty($selected_category) && $product['category'] !== $selected_category) continue;
                
                // Store product data in lowercase for search comparison
                $product_name_lower = strtolower($product['name']);
                $product_description_lower = strtolower($product['description']);
            ?>
            <div 
                x-show="!searchTerm || '<?= $product_name_lower ?>'.includes(searchTerm.toLowerCase()) || '<?= $product_description_lower ?>'.includes(searchTerm.toLowerCase())"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                class="bg-white rounded-xl shadow-sm overflow-hidden transform transition duration-300 hover:shadow-lg hover:-translate-y-1"
                data-product-container
            >
                <div class="relative">
                    <img src="<?= htmlspecialchars($product['image_url']) ?>" class="w-full h-56 object-cover">
                    <?php if ($product['quantity'] <= 5 && $product['quantity'] > 0): ?>
                        <div class="absolute top-2 left-2 bg-amber-500 text-white px-3 py-1 rounded-full text-xs font-semibold">
                            Only <?= $product['quantity'] ?> left!
                        </div>
                    <?php elseif ($product['quantity'] == 0): ?>
                        <div class="absolute top-2 left-2 bg-red-500 text-white px-3 py-1 rounded-full text-xs font-semibold">
                            Out of Stock
                        </div>
                    <?php endif; ?>
                    <button 
                        @click="addToWishlist(<?= $product['product_id'] ?>, '<?= htmlspecialchars($product['name']) ?>')"
                        class="absolute top-2 right-2 bg-white p-2 rounded-full text-gray-500 hover:text-red-500 transition-colors"
                    >
                        <i class="fas fa-heart" :class="wishlist.find(item => item.id === <?= $product['product_id'] ?>) ? 'text-red-500' : ''"></i>
                    </button>
                </div>
                <div class="p-4">
                    <div class="flex justify-between items-start">
                        <h3 class="font-bold text-lg mb-1"><?= htmlspecialchars($product['name']) ?></h3>
                        <span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded">
                            <?= htmlspecialchars($product['category']) ?>
                        </span>
                    </div>
                    <div class="flex items-center mb-2">
                        <div class="flex text-yellow-400">
                            <?php 
                            $rating = rand(3, 5); // Random rating between 3-5
                            for ($i = 1; $i <= 5; $i++): 
                                if ($i <= $rating):
                            ?>
                                <i class="fas fa-star"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php 
                                endif;
                            endfor; 
                            ?>
                        </div>
                        <span class="text-gray-500 text-xs ml-1">(<?= rand(5, 150) ?>)</span>
                    </div>
                    <p class="text-gray-600 text-sm mb-4"><?= htmlspecialchars(substr($product['description'], 0, 80)) ?>...</p>
                    <div class="flex justify-between items-center">
                        <span class="text-xl font-bold text-indigo-600">
                            KSH<?= number_format($product['price'], 2) ?>
                        </span>
                        <form method="POST" class="flex items-center">
                            <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                            <input 
                                type="number" 
                                name="quantity" 
                                min="1" 
                                max="<?= $product['quantity'] ?>" 
                                value="1" 
                                class="w-14 mr-2 border rounded px-2 py-1 text-center"
                                <?= $product['quantity'] == 0 ? 'disabled' : '' ?>
                            >
                            <button 
                                type="submit" 
                                name="add_to_cart" 
                                class="bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700 transition-colors flex items-center text-sm <?= $product['quantity'] == 0 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                <?= $product['quantity'] == 0 ? 'disabled' : '' ?>
                            >
                                <i class="fas fa-shopping-cart mr-1"></i> Add
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>



        <!-- Newsletter Section -->
        <section class="py-16 bg-indigo-600">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="lg:flex lg:items-center lg:justify-between">
                    <div class="lg:w-1/2">
                        <h2 class="text-3xl font-extrabold text-white sm:text-4xl">
                            Subscribe to our newsletter
                        </h2>
                        <p class="mt-3 max-w-3xl text-lg text-indigo-200">
                            Stay updated with our latest products, promotions, and tech tips.
                        </p>
                    </div>
                    <div class="mt-8 lg:mt-0 lg:w-1/2">
                        <form class="sm:flex">
                            <label for="email-address" class="sr-only">Email address</label>
                            <input id="email-address" name="email-address" type="email" autocomplete="email" required class="w-full px-5 py-3 border-white focus:ring-white focus:border-white rounded-md" placeholder="Enter your email">
                            <div class="mt-3 rounded-md sm:mt-0 sm:ml-3 sm:flex-shrink-0">
                                <button type="submit" class="w-full bg-white border border-transparent rounded-md py-3 px-5 flex items-center justify-center text-base font-medium text-indigo-600 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-indigo-700 focus:ring-white">
                                    Subscribe
                                </button>
                            </div>
                        </form>
                        <p class="mt-3 text-sm text-indigo-200">
                            We care about your data. Read our 
                            <a href="#" class="font-medium text-white underline">
                                Privacy Policy
                            </a>.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                        What Our Customers Say
                    </h2>
                    <p class="mt-4 text-xl text-gray-600">
                        Don't just take our word for it, hear from our satisfied customers.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="bg-gray-50 p-6 rounded-xl shadow-sm">
                        <div class="flex text-yellow-400 mb-4">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="text-gray-600 mb-6 italic">
                            "MetaDrop has completely transformed my tech shopping experience. Their selection is incredible and shipping is lightning fast!"
                        </p>
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-indigo-200 flex items-center justify-center text-indigo-500 font-bold">
                                JS
                            </div>
                            <div class="ml-3">
                                <h4 class="font-bold text-gray-900">Jamie Smith</h4>
                                <p class="text-sm text-gray-500">Tech Enthusiast</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-6 rounded-xl shadow-sm">
                        <div class="flex text-yellow-400 mb-4">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="text-gray-600 mb-6 italic">
                            "The customer service is outstanding. When I had a question about my order, they responded immediately and resolved my issue."
                        </p>
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-indigo-200 flex items-center justify-center text-indigo-500 font-bold">
                                TR
                            </div>
                            <div class="ml-3">
                                <h4 class="font-bold text-gray-900">Taylor Rodriguez</h4>
                                <p class="text-sm text-gray-500">Software Developer</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-6 rounded-xl shadow-sm">
                        <div class="flex text-yellow-400 mb-4">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <p class="text-gray-600 mb-6 italic">
                            "The quality of products I've purchased from MetaDrop is consistently exceptional. Their curated selection saves me so much time."
                        </p>
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-indigo-200 flex items-center justify-center text-indigo-500 font-bold">
                                AK
                            </div>
                            <div class="ml-3">
                                <h4 class="font-bold text-gray-900">Alex Kim</h4>
                                <p class="text-sm text-gray-500">Product Designer</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <img src="logo.jpg" alt="MetaDrop Logo" class="h-10 w-10 mr-2 rounded-full">
                        <h2 class="text-2xl font-bold text-indigo-400">MetaDrop</h2>
                    </div>
                    <p class="text-gray-400 mb-4">
                        The ultimate destination for cutting-edge tech products and innovative gadgets.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-indigo-400 transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-indigo-400 transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-indigo-400 transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-indigo-400 transition-colors">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-white transition-colors">Home</a></li>
                        <li><a href="#products" class="text-gray-400 hover:text-white transition-colors">Products</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white transition-colors">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-white transition-colors">Contact</a></li>
                        <li><a href="faq.php" class="text-gray-400 hover:text-white transition-colors">FAQ</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Customer Service</h3>
                    <ul class="space-y-2">
                        <li><a href="shipping.php" class="text-gray-400 hover:text-white transition-colors">Shipping Policy</a></li>
                        <li><a href="returns.php" class="text-gray-400 hover:text-white transition-colors">Returns & Refunds</a></li>
                        <li><a href="privacy.php" class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="terms.php" class="text-gray-400 hover:text-white transition-colors">Terms of Service</a></li>
                        <li><a href="track-order.php" class="text-gray-400 hover:text-white transition-colors">Track Order</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact Us</h3>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-2 text-indigo-400"></i>
                            <span class="text-gray-400">123 Tech Street, Silicon Valley, CA 94043</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone mr-2 text-indigo-400"></i>
                            <span class="text-gray-400">(123) 456-7890</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-2 text-indigo-400"></i>
                            <span class="text-gray-400">support@metadrop.com</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-clock mr-2 text-indigo-400"></i>
                            <span class="text-gray-400">Mon-Fri: 9AM - 6PM</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm">
                    &copy; <?= date('Y') ?> MetaDrop. All rights reserved.
                </p>
                <div class="mt-4 md:mt-0 flex space-x-4">
                    <img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/visa.svg" alt="Visa" class="h-8 w-auto bg-white p-1 rounded">
                    <img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/mastercard.svg" alt="Mastercard" class="h-8 w-auto bg-white p-1 rounded">
                    <img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/paypal.svg" alt="PayPal" class="h-8 w-auto bg-white p-1 rounded">
                    <img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/applepay.svg" alt="Apple Pay" class="h-8 w-auto bg-white p-1 rounded">
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Clear cart message after display
        <?php if(isset($_SESSION['cart_message'])): ?>
            setTimeout(() => {
                <?php unset($_SESSION['cart_message']); ?>
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>