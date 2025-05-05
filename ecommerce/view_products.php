<?php
include 'config.php';

// Handle product status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];
    $status = $_POST['status'] == 'sold_out' ? 0 : 1;  // Flip logic for is_active

    $sql = "UPDATE products SET is_active = ? WHERE product_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $status, $product_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch products
$sql = "SELECT * FROM products";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <title>Product Inventory - MetaDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="icon" href="logo.jpg" type="image/png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary: #6366f1;
            --secondary: #4f46e5;
        }

        body {
            font-family: 'Inter', sans-serif;
            @apply bg-gray-50 dark:bg-gray-900;
        }

        .gradient-bg {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .status-badge {
            @apply px-3 py-1 rounded-full text-sm font-medium;
        }

        .status-available {
            @apply bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100;
        }

        .status-soldout {
            @apply bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100;
        }

        .action-btn {
            @apply px-4 py-2 rounded-lg font-medium transition-all duration-200 transform hover:scale-105;
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body x-data="{ darkMode: false }" :class="{ 'dark': darkMode }" class="min-h-screen">
    <!-- Header -->
    <header class="gradient-bg text-white shadow-xl">
        <div class="max-w-7xl mx-auto px-4 py-6 flex justify-between items-center">
            <h1 class="text-2xl font-bold">Product Inventory</h1>
            <div class="flex items-center space-x-4">
                <button @click="darkMode = !darkMode" class="p-2 rounded-full hover:bg-white/10 transition-colors">
                    <i x-text="darkMode ? '🌞' : '🌙'" class="text-xl"></i>
                </button>
                <a href="admin.php" class="bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden animate-fade-in">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Image</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Product</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Description</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Price</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Stock</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Status</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <img src="<?= htmlspecialchars($row['image']) ?>" 
                                             class="w-16 h-16 object-cover rounded-lg border dark:border-gray-600"
                                             alt="<?= htmlspecialchars($row['name']) ?>">
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                                        <?= htmlspecialchars($row['name']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400 max-w-xs">
                                        <?= htmlspecialchars($row['description']) ?>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                                        $<?= number_format($row['price'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center text-gray-600 dark:text-gray-400">
                                        <?= htmlspecialchars($row['quantity']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="status-badge <?= $row['is_active'] ? 'status-available' : 'status-soldout' ?>">
                                            <?= $row['is_active'] ? 'Available' : 'Sold Out' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <form method="POST" 
                                              class="inline-block"
                                              onsubmit="return confirm('Are you sure you want to change the status?')">
                                            <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">
                                            <input type="hidden" name="status" 
                                                   value="<?= $row['is_active'] ? 'sold_out' : 'available' ?>">
                                            <button type="submit" 
                                                    class="action-btn <?= $row['is_active'] ? 
                                                        'bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900 dark:hover:bg-red-800' : 
                                                        'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900 dark:hover:bg-green-800' ?>">
                                                <i class="fas <?= $row['is_active'] ? 'fa-times' : 'fa-check' ?> mr-2"></i>
                                                <?= $row['is_active'] ? 'Mark Sold Out' : 'Mark Available' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center space-y-4">
                                        <i class="fas fa-box-open text-4xl"></i>
                                        <p class="text-lg">No products found in inventory</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Notification Toast -->
    <div x-data="{ show: false, message: '' }" 
         x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg"
         x-cloak>
        <i class="fas fa-check-circle mr-2"></i>
        <span x-text="message"></span>
    </div>

    <script>
        // Handle status update notifications
        <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
            document.addEventListener('alpine:init', () => {
                Alpine.store('toast', {
                    show: true,
                    message: 'Status updated successfully!'
                })
            });
        <?php endif; ?>
    </script>
</body>
</html>