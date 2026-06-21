<?php
session_start();
include('db.php');

if (!isset($_SESSION['VendorID'])) {
    header("Location: Login.html");
    exit();
}

$vendorId = (int)$_SESSION['VendorID'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['Action_Save'])) {
    $foodName = trim($_POST['FoodName'] ?? '');
    $price = $_POST['Price'] ?? '';
    $description = trim($_POST['Description'] ?? '');
    $category = trim($_POST['Category'] ?? '');
    $dietaryTag = trim($_POST['DietaryTag'] ?? '');
    $status = trim($_POST['Status'] ?? 'Available');

    if ($foodName === '') {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Food name is required."));
        exit();
    }

    // Handle file upload
    if (!isset($_FILES['Image']) || $_FILES['Image']['error'] !== UPLOAD_ERR_OK) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Product image is required."));
        exit();
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($_FILES['Image']['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Only JPG, PNG, GIF, and WEBP images are allowed."));
        exit();
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['Image']['size'] > $maxSize) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Image size must be under 5MB."));
        exit();
    }

    if (!is_numeric($price) || (float)$price < 0) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Valid price is required."));
        exit();
    }

    if ($category === '') {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Category is required."));
        exit();
    }

    // Default fields from pdf table design
    if ($status === '') $status = 'Available';

    // Save uploaded image
    $uploadDir = 'uploads/food/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $ext = pathinfo($_FILES['Image']['name'], PATHINFO_EXTENSION);
    $fileName = 'food_' . $vendorId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['Image']['tmp_name'], $filePath)) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Failed to upload image."));
        exit();
    }

    $image = $filePath;

    $sql = "INSERT INTO MENU_FOOD (VendorID, FoodName, Price, Description, Category, DietaryTag, Status, Image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isdsssss", $vendorId, $foodName, $price, $description, $category, $dietaryTag, $status, $image);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: VendorDashboard.php?type=success&msg=" . urlencode("Food item added successfully."));
        exit();
    }

    header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Failed to add food item."));
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Food - Vendor - CraveFood</title>
    <link rel="stylesheet" href="style.css?v=20260621-7">
    <style>
        .image-preview-box {
            margin-top: 10px;
            max-width: 220px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px dashed #ccc;
            display: none;
        }
        .image-preview-box img {
            width: 100%;
            display: block;
        }
        .file-upload-label {
            display: inline-block;
            padding: 10px 20px;
            background: #f0f0f0;
            border: 2px dashed #aaa;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.2s, background 0.2s;
        }
        .file-upload-label:hover {
            border-color: #e8690b;
            background: #fff5ee;
        }
        .file-upload-label .upload-icon {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 4px;
        }
        .file-upload-label .upload-text {
            font-size: 0.9rem;
            color: #666;
        }
        .file-input-hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo"><h2>CraveFood</h2></div>
        <div class="nav-links">
            <a href="VendorDashboard.php">Dashboard</a>
            <a href="VendorOrders.php">Orders</a>
            <a href="VendorProfileEdit.php">Store Profile</a>
            <a href="VendorLogout.php">Logout</a>
        </div>
    </div>

    <?php
        $noticeMsg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
        $noticeType = isset($_GET['type']) ? $_GET['type'] : 'error';
    ?>
    <?php if ($noticeMsg !== ''): ?>
        <div class="notice show <?php echo ($noticeType === 'success') ? 'notice-success' : 'notice-error'; ?>">
            <?php echo htmlspecialchars($noticeMsg); ?>
        </div>
    <?php endif; ?>

    <div class="settings-box">
        <h2>Add Food Item</h2>
        <p class="settings-note">Create a new menu item for your vendor.</p>

        <form method="POST" action="VendorFoodCreate.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="FoodName">Food Name</label>
                <input type="text" id="FoodName" name="FoodName" placeholder="e.g., Nasi Lemak" required maxlength="100">
            </div>

            <div class="form-group">
                <label for="Price">Price (RM)</label>
                <input type="number" id="Price" name="Price" placeholder="e.g., 5.50" step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label for="Description">Description / Ingredients</label>
                <textarea id="Description" name="Description" placeholder="Brief details..." rows="4"></textarea>
            </div>

            <div class="form-group">
                <label for="Category">Category</label>
                <select id="Category" name="Category" required>
                    <option value="Main">Main</option>
                    <option value="Drink">Drink</option>
                    <option value="Snack">Snack</option>
                </select>
            </div>

            <div class="form-group">
                <label for="DietaryTag">Dietary Tag</label>
                <input type="text" id="DietaryTag" name="DietaryTag" placeholder="e.g., Halal, Vegetarian, Protein" maxlength="100">
            </div>

            <div class="form-group">
                <label for="Status">Status</label>
                <select id="Status" name="Status">
                    <option value="Available" selected>Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>

            <div class="form-group">
                <label>Product Image</label>
                <label for="Image" class="file-upload-label" id="uploadLabel">
                    <span class="upload-icon">📷</span>
                    <span class="upload-text">Click to choose an image from your device</span>
                </label>
                <input type="file" id="Image" name="Image" accept="image/jpeg,image/png,image/gif,image/webp" required class="file-input-hidden" onchange="previewImage(this)">
                <div class="image-preview-box" id="imagePreview">
                    <img id="previewImg" src="" alt="Preview">
                </div>
            </div>

            <button type="submit" name="Action_Save" value="Save" class="btn-primary btn-block">Save Food</button>
        </form>

        <a href="VendorDashboard.php" class="btn-return" style="margin-top: 14px; display: inline-block;">Back to Dashboard</a>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    var page = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
    if (page === '' || page === 'index.php') page = 'homepage.php';
    var links = document.querySelectorAll('.nav-links a');
    links.forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === page || (page.startsWith('advancesearch') && href === 'homepage.php')) {
            link.classList.add('active');
        }
    });
});
</script>
</body>
</html>




