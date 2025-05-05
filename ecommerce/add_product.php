<?php
require_once 'config.php';
session_start();

// Initialize variables
$success_message = $error_message = '';

// Fetch available categories for the dropdown
try {
    $categories_query = $conn->query("SELECT DISTINCT category_name FROM vw_product_details");
    $categories = [];
    while ($row = $categories_query->fetch_assoc()) {
        $categories[] = $row['category_name'];
    }
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = ['Electronics', 'Clothing']; // Fallback categories from your screenshot
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validate input
        $name = filter_input(INPUT_POST, 'product_name', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'product_description', FILTER_SANITIZE_STRING);
        $price = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
        $quantity = filter_input(INPUT_POST, 'product_quantity', FILTER_VALIDATE_INT);
        $category = filter_input(INPUT_POST, 'product_category', FILTER_SANITIZE_STRING);

        if (!$name || !$description || $price === false || $quantity === false || !$category) {
            throw new Exception("Invalid input data");
        }

        // Validate and process image upload
        if (!isset($_FILES["product_image"]) || $_FILES["product_image"]["error"] !== UPLOAD_ERR_OK) {
            throw new Exception("Error uploading file");
        }

        $file_info = getimagesize($_FILES["product_image"]["tmp_name"]);
        if ($file_info === false) {
            throw new Exception("Invalid image file");
        }

        // Define allowed file types
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file_info['mime'], $allowed_types)) {
            throw new Exception("Invalid file type. Only JPG, PNG and GIF are allowed");
        }

        // Generate unique filename
        $file_extension = pathinfo($_FILES["product_image"]["name"], PATHINFO_EXTENSION);
        $unique_filename = uniqid() . '.' . $file_extension;
        $target_dir = "uploads/";
        $target_file = $target_dir . $unique_filename;

        // Create upload directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
            throw new Exception("Error moving uploaded file");
        }

        // Check if we need to use category_id or category_name based on database schema
        // First, get the category_id if needed
        $category_id = null;
        if (in_array('category_id', array_keys($conn->query("SHOW COLUMNS FROM products")->fetch_all(MYSQLI_ASSOC)))) {
            // If the products table uses category_id
            $cat_query = $conn->prepare("SELECT category_id FROM categories WHERE category_name = ?");
            $cat_query->bind_param("s", $category);
            $cat_query->execute();
            $cat_result = $cat_query->get_result();
            
            if ($cat_row = $cat_result->fetch_assoc()) {
                $category_id = $cat_row['category_id'];
            } else {
                // Category doesn't exist, create it
                $insert_cat = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
                $insert_cat->bind_param("s", $category);
                $insert_cat->execute();
                $category_id = $conn->insert_id;
            }
            
            // Insert product into database using category_id
            $sql = "INSERT INTO products (name, description, price, quantity, image_url, category_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Database prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("ssdisi", $name, $description, $price, $quantity, $target_file, $category_id);
        } else {
            // If the products table directly uses category name
            $sql = "INSERT INTO products (name, description, price, quantity, image_url, category) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Database prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("ssdiss", $name, $description, $price, $quantity, $target_file, $category);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Error adding product: " . $stmt->error);
        }

        $success_message = "Product added successfully!";
        $stmt->close();

    } catch (Exception $e) {
        $error_message = $e->getMessage();
        // Log error for admin
        error_log("Product addition error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - MetaDrop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" href="logo.jpg" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4361ee;
            --primary-hover: #3a0ca3;
            --secondary-color: #f8f9fa;
            --text-color: #2d3748;
            --border-color: #e2e8f0;
            --error-color: #e53e3e;
            --success-color: #38a169;
            --box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(120deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            padding: 40px 20px;
            color: var(--text-color);
            line-height: 1.6;
        }

        .admin-container {
            max-width: 850px;
            margin: 0 auto;
            background-color: white;
            border-radius: 15px;
            padding: 35px;
            box-shadow: var(--box-shadow);
        }

        .header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #edf2f7;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .header i {
            margin-right: 12px;
            font-size: 24px;
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            padding: 10px;
            border-radius: 10px;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }

        .message i {
            margin-right: 10px;
            font-size: 18px;
        }

        .success {
            background-color: rgba(56, 161, 105, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .error {
            background-color: rgba(229, 62, 62, 0.1);
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 15px;
            transition: var(--transition);
            background-color: white;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .file-input-container {
            position: relative;
            margin-top: 8px;
        }

        .file-input-button {
            display: block;
            background-color: #f7fafc;
            border: 1px dashed #cbd5e0;
            border-radius: 8px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-input-button:hover {
            background-color: #edf2f7;
        }

        .file-input-button i {
            font-size: 24px;
            margin-bottom: 10px;
            color: #a0aec0;
        }

        .file-input-button p {
            margin: 0;
            color: #718096;
            font-size: 14px;
        }

        .file-input-button p.main-text {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 6px;
        }

        input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .preview-container {
            text-align: center;
            margin-top: 20px;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 15px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: var(--transition);
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn i {
            margin-right: 8px;
        }

        .back-link {
            color: #718096;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--primary-color);
        }

        .back-link i {
            margin-right: 6px;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .admin-container {
                padding: 25px;
            }
            
            .buttons {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn-primary {
                width: 100%;
            }
            
            .back-link {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header">
            <i class="fas fa-plus-circle"></i>
            <h1>Add New Product</h1>
        </div>
        
        <?php if ($success_message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" id="productForm">
            <div class="form-grid">
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" required
                           maxlength="255" pattern="[A-Za-z0-9\s\-_]+" 
                           title="Only letters, numbers, spaces, hyphens and underscores are allowed"
                           placeholder="Enter product name">
                </div>

                <div class="form-group">
                    <label for="product_category">Category</label>
                    <select id="product_category" name="product_category" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                        <option value="new_category">+ Add New Category</option>
                    </select>
                </div>
                
                <div class="form-group" id="new_category_group" style="display: none;">
                    <label for="new_category_name">New Category Name</label>
                    <input type="text" id="new_category_name" name="new_category_name" 
                           maxlength="100" pattern="[A-Za-z0-9\s\-_]+"
                           placeholder="Enter new category name"
                           title="Only letters, numbers, spaces, hyphens and underscores are allowed">
                </div>

                <div class="form-group">
                    <label for="product_price">Price (Ksh)</label>
                    <input type="number" id="product_price" name="product_price" required
                           min="0" step="0.01" max="999999.99"
                           placeholder="0.00">
                </div>

                <div class="form-group">
                    <label for="product_quantity">Quantity</label>
                    <input type="number" id="product_quantity" name="product_quantity" required
                           min="0" max="999999"
                           placeholder="0">
                </div>

                <div class="form-group full-width">
                    <label for="product_description">Description</label>
                    <textarea id="product_description" name="product_description" required
                              rows="4" maxlength="1000"
                              placeholder="Enter product description"></textarea>
                </div>

                <div class="form-group full-width">
                    <label for="product_image">Product Image</label>
                    <div class="file-input-container">
                        <label class="file-input-button">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p class="main-text">Upload Product Image</p>
                            <p>JPG, PNG or GIF (Max. 5MB)</p>
                            <input type="file" id="product_image" name="product_image" 
                                   accept="image/jpeg,image/png,image/gif" required>
                        </label>
                    </div>
                    <div class="preview-container">
                        <img id="imagePreview" class="preview-image" alt="Image preview">
                    </div>
                </div>
            </div>

            <div class="buttons">
                <a href="admin.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Product
                </button>
            </div>
        </form>
    </div>

    <script>
        // Image preview functionality
        document.getElementById('product_image').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Category dropdown functionality
        document.getElementById('product_category').addEventListener('change', function() {
            const newCategoryGroup = document.getElementById('new_category_group');
            const newCategoryInput = document.getElementById('new_category_name');
            
            if (this.value === 'new_category') {
                newCategoryGroup.style.display = 'block';
                newCategoryInput.required = true;
            } else {
                newCategoryGroup.style.display = 'none';
                newCategoryInput.required = false;
            }
        });

        // Form validation
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const price = document.getElementById('product_price').value;
            const quantity = document.getElementById('product_quantity').value;
            const category = document.getElementById('product_category').value;
            
            if (parseFloat(price) < 0) {
                e.preventDefault();
                alert('Price cannot be negative');
            }

            if (parseInt(quantity) < 0) {
                e.preventDefault();
                alert('Quantity cannot be negative');
            }
            
            // Handle new category submission
            if (category === 'new_category') {
                const newCategoryName = document.getElementById('new_category_name').value.trim();
                if (!newCategoryName) {
                    e.preventDefault();
                    alert('Please enter a new category name');
                } else {
                    // Create a hidden field to pass the actual category name
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'product_category';
                    hiddenInput.value = newCategoryName;
                    this.appendChild(hiddenInput);
                }
            }
        });
    </script>
</body>
</html>