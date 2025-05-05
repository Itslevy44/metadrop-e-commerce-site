<?php
session_start();
require_once 'config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $inquiry_type = trim($_POST['inquiry_type'] ?? 'general');

    // Validation
    if (empty($name)) {
        $errors['name'] = 'Name is required';
    }
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    if (empty($message)) {
        $errors['message'] = 'Message is required';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO contacts (name, email, subject, message, inquiry_type) 
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message, $inquiry_type]);
            $success = true;
            
            // Clear form fields
            $name = $email = $subject = $message = '';
        } catch (PDOException $e) {
            $errors[] = 'There was an error submitting your message. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact MetaDrop - Modern Tech Marketplace</title>
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
            Let's Connect
        </h1>
        <p class="text-xl text-indigo-100 max-w-2xl mx-auto mb-8">
            Have questions or suggestions? We're here to help!
        </p>
        <!-- Home Button -->
        <a href="index.php" class="inline-flex items-center justify-center px-6 py-3 bg-white text-indigo-600 font-medium rounded-lg shadow-md hover:bg-indigo-50 transition duration-300">
            <i class="fas fa-home mr-2"></i>
            Back to Homepage
        </a>
    </div>
</header>

    <!-- Contact Content -->
    <section class="py-16 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-12">
                <!-- Contact Info -->
                <div class="space-y-8">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">
                        Get in Touch
                    </h2>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-map-marker-alt text-indigo-600 dark:text-indigo-400"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Visit Us</h3>
                                <p class="text-gray-600 dark:text-gray-300">123 Tech Street<br>Silicon Valley, CA 94043</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-phone text-indigo-600 dark:text-indigo-400"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Call Us</h3>
                                <p class="text-gray-600 dark:text-gray-300">(123) 456-7890</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-envelope text-indigo-600 dark:text-indigo-400"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Email Us</h3>
                                <p class="text-gray-600 dark:text-gray-300">support@metadrop.com</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="bg-gray-50 dark:bg-gray-800 p-8 rounded-xl shadow-lg">
                    <?php if ($success): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            Thank you! Your message has been sent successfully.
                        </div>
                    <?php elseif (!empty($errors)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php foreach ($errors as $error): ?>
                                <p><?= $error ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form action="contact.php" method="POST" class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($name ?? '') ?>" 
                                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <?php if (isset($errors['name'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?= $errors['name'] ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" 
                                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <?php if (isset($errors['email'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?= $errors['email'] ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="inquiry_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Inquiry Type</label>
                            <select id="inquiry_type" name="inquiry_type" 
                                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="general">General Inquiry</option>
                                <option value="support">Technical Support</option>
                                <option value="sales">Sales Question</option>
                                <option value="feedback">Product Feedback</option>
                            </select>
                        </div>

                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject</label>
                            <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($subject ?? '') ?>" 
                                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message</label>
                            <textarea id="message" name="message" rows="5" 
                                      class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"><?= htmlspecialchars($message ?? '') ?></textarea>
                            <?php if (isset($errors['message'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?= $errors['message'] ?></p>
                            <?php endif; ?>
                        </div>

                        <button type="submit" 
                                class="w-full bg-indigo-600 text-white py-2 px-6 rounded-lg hover:bg-indigo-700 transition-colors">
                            Send Message
                        </button>
                    </form>
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