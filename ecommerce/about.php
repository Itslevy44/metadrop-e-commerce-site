<?php
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About MetaDrop - Modern Tech Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="icon" href="assets/logo.jpg" type="image/png">
    
</head>
<body class="bg-gray-50 font-sans" x-data="{ darkMode: false }" :class="{ 'dark': darkMode }">
    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-800 shadow-lg fixed w-full z-50">
        <!-- Include your existing navigation here -->
    </nav>

    <!-- Hero Section -->
<header class="pt-32 pb-20 bg-gradient-to-r from-indigo-500 to-purple-600 dark:from-gray-800 dark:to-gray-900">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-white mb-6 animate-fade-in">
            Revolutionizing Tech Commerce
        </h1>
        <p class="text-xl text-indigo-100 max-w-2xl mx-auto mb-8">
            Where innovation meets exceptional customer experience
        </p>
        <!-- Home Button -->
        <a href="index.php" class="inline-flex items-center justify-center px-6 py-3 bg-white text-indigo-600 font-medium rounded-lg shadow-md hover:bg-indigo-50 transition duration-300">
            <i class="fas fa-home mr-2"></i>
            Back to Homepage
        </a>
    </div>
</header>

    <!-- Testimonials Section -->
    <section class="py-16 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-4">
                    Don't just take our word for it
                </h2>
                <p class="text-gray-600 dark:text-gray-300">
                    Hear from our community of tech enthusiasts
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Featured Testimonial -->
                <div class="bg-indigo-50 dark:bg-gray-800 p-8 rounded-2xl shadow-lg">
                    <div class="text-indigo-600 dark:text-indigo-400 mb-4">
                        <?php for($i = 0; $i < 6; $i++): ?>
                            <i class="fas fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <blockquote class="text-gray-800 dark:text-white text-lg mb-6">
                        "MetaDrop has completely transformed my tech shopping experience. Their selection is incredible and shipping is lightning fast!"
                    </blockquote>
                    <div class="flex items-center">
                        <div class="ml-4">
                            <p class="font-bold text-gray-900 dark:text-white">Jamie Smith</p>
                            <p class="text-gray-600 dark:text-gray-300">Tech Enthusiast</p>
                        </div>
                    </div>
                </div>

                <!-- Other Testimonials -->
                <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-lg">
                    <div class="text-yellow-400 mb-4">
                        <?php for($i = 0; $i < 6; $i++): ?>
                            <i class="fas fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <blockquote class="text-gray-800 dark:text-white mb-6">
                        "The customer service is outstanding. When I had a question about my order, they responded immediately and resolved my issue."
                    </blockquote>
                    <div class="flex items-center">
                        <div class="ml-4">
                            <p class="font-bold text-gray-900 dark:text-white">Taylor Rodriguez</p>
                            <p class="text-gray-600 dark:text-gray-300">Software Developer</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-lg">
                    <div class="text-yellow-400 mb-4">
                        <?php for($i = 0; $i < 6; $i++): ?>
                            <i class="fas fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <blockquote class="text-gray-800 dark:text-white mb-6">
                        "The quality of products I've purchased from MetaDrop is consistently exceptional. Their curated selection saves me so much time."
                    </blockquote>
                    <div class="flex items-center">
                        <div class="ml-4">
                            <p class="font-bold text-gray-900 dark:text-white">Alex Kim</p>
                            <p class="text-gray-600 dark:text-gray-300">Product Designer</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Company Info Section -->
    <section class="py-16 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-12">
                <div class="space-y-6">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white">
                        The MetaDrop Difference
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300 text-lg">
                        As the ultimate destination for cutting-edge tech products and innovative gadgets, we're committed to:
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-center">
                            <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-bolt text-indigo-600 dark:text-indigo-400"></i>
                            </div>
                            <span class="text-gray-800 dark:text-white">Lightning-fast shipping</span>
                        </li>
                        <li class="flex items-center">
                            <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-star text-indigo-600 dark:text-indigo-400"></i>
                            </div>
                            <span class="text-gray-800 dark:text-white">Curated quality selection</span>
                        </li>
                        <li class="flex items-center">
                            <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-headset text-indigo-600 dark:text-indigo-400"></i>
                            </div>
                            <span class="text-gray-800 dark:text-white">24/7 customer support</span>
                        </li>
                    </ul>
                </div>

                <div class="grid grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Quick Links</h3>
                        <ul class="space-y-2">
                            <li><a href="/" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600">Home</a></li>
                            <li><a href="/products" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600">Products</a></li>
                            <li><a href="/about" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600">About Us</a></li>
                            <li><a href="/contact" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600">Contact</a></li>
                            <li><a href="/faq" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600">FAQ</a></li>
                        </ul>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Customer Service</h3>
                        <ul class="space-y-2">
                            <li><a href="/shipping" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600">Shipping Policy</a></li>
                            <li><a href="/returns" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600">Returns & Refunds</a></li>
                            <li><a href="/privacy" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600">Privacy Policy</a></li>
                            <li><a href="/terms" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600">Terms of Service</a></li>
                            <li><a href="/track" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600">Track Order</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-16 bg-white dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-800 dark:text-white">Get in Touch</h2>
                <p class="text-gray-600 dark:text-gray-300 mt-2">We're here to help with any questions</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 text-center">
                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-xl">
                    <i class="fas fa-map-marker-alt text-indigo-600 text-2xl mb-4"></i>
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Visit Us</h4>
                    <p class="text-gray-600 dark:text-gray-300">123 Tech Street<br>Silicon Valley, CA 94043</p>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-xl">
                    <i class="fas fa-phone text-indigo-600 text-2xl mb-4"></i>
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Call Us</h4>
                    <p class="text-gray-600 dark:text-gray-300">(123) 456-7890</p>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-xl">
                    <i class="fas fa-envelope text-indigo-600 text-2xl mb-4"></i>
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Email Us</h4>
                    <p class="text-gray-600 dark:text-gray-300">support@metadrop.com</p>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-xl">
                    <i class="fas fa-clock text-indigo-600 text-2xl mb-4"></i>
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Hours</h4>
                    <p class="text-gray-600 dark:text-gray-300">Mon-Fri: 9AM - 6PM PST</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 dark:bg-gray-900 text-gray-300 py-12">
        <!-- Include your existing footer here -->
    </footer>
</body>
</html>