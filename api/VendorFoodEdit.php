<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if (!isset($_SESSION['VendorID'])) {
    header("Location: Login.html");
    exit();
}

$vendorId = (int)$_SESSION['VendorID'];

$foodId = isset($_GET['food_id']) ? (int)$_GET['food_id'] : 0;
if ($foodId <= 0) {
    header("Location: VendorDashboard.php?type=error&msg=" . urlencode("Invalid food item."));
    exit();
}

$item = db_fetch_one($pdo,
    'SELECT "FoodID", "FoodName", "Price", "Description", "Category", "DietaryTag", "Status", "Image"
     FROM menu_food WHERE "FoodID" = ? AND "VendorID" = ? LIMIT 1',
    [$foodId, $vendorId]
);

if (!$item) {
    header("Location: VendorDashboard.php?type=error&msg=" . urlencode("Food item not found."));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['Action_Update'])) {
    $foodName = trim($_POST['FoodName'] ?? '');
    $price = $_POST['Price'] ?? '';
    $description = trim($_POST['Description'] ?? '');
    $category = trim($_POST['Category'] ?? '');
    $dietaryTag = trim($_POST['DietaryTag'] ?? '');
    $status = trim($_POST['Status'] ?? 'Available');

    // Use existing image by default
    $image = $item['Image'];

    if ($foodName === '') {
        header("Location: VendorFoodEdit.php?food_id={$foodId}&type=error&msg=" . urlencode("Food name is required."));
        exit();
    }

    // Handle new file upload (optional on edit)
    if (isset($_FILES['Image']) && $_FILES['Image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['Image']['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            header("Location: VendorFoodEdit.php?food_id={$foodId}&type=error&msg=" . urlencode("Only JPG, PNG, GIF, and WEBP images are allowed."));
            exit();
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($_FILES['Image']['size'] > $maxSize) {
            header("Location: VendorFoodEdit.php?food_id={$foodId}&type=error&msg=" . urlencode("Image size must be under 5MB."));
            exit();
        }

        $uploadDir = 'uploads/food/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = pathinfo($_FILES['Image']['name'], PATHINFO_EXTENSION);
        $fileName = 'food_' . $vendorId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['Image']['tmp_name'], $filePath)) {
            // Delete old uploaded image if it exists in uploads/
            if (!empty($image) && strpos($image, 'uploads/') === 0 && file_exists($image)) {
                unlink($image);
            }
            $image = $filePath;
        } else {
            header("Location: VendorFoodEdit.php?food_id={$foodId}&type=error&msg=" . urlencode("Failed to upload image."));
            exit();
        }
    }

    if (!is_numeric($price) || (float)$price < 0) {
        header("Location: VendorFoodEdit.php?food_id={$foodId}&type=error&msg=" . urlencode("Valid price is required."));
        exit();
    }

    if ($category === '') {
        header("Location: VendorFoodEdit.php?food_id={$foodId}&type=error&msg=" . urlencode("Category is required."));
        exit();
    }

    if ($status === '') $status = 'Available';

    $stmt = $pdo->prepare(
        'UPDATE menu_food SET "FoodName" = ?, "Price" = ?, "Description" = ?, "Category" = ?, "DietaryTag" = ?, "Status" = ?, "Image" = ? WHERE "FoodID" = ? AND "VendorID" = ?'
    );

    if ($stmt->execute([$foodName, (float)$price, $description, $category, $dietaryTag, $status, $image, $foodId, $vendorId])) {
        header("Location: VendorDashboard.php?type=success&msg=" . urlencode("Food item updated successfully."));
        exit();
    }

    header("Location: VendorFoodEdit.php?food_id={$foodId}&type=error&msg=" . urlencode("Failed to update food item."));
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Food - Vendor - CraveFood</title>
    <link rel="stylesheet" href="../style.css?v=20260621-7">
    <style>
        .image-preview-box {
            margin-top: 10px;
            max-width: 220px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px dashed #ccc;
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
        .current-image-note {
            font-size: 0.85rem;
            color: #888;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo"><h2>CraveFood</h2></div>
        <div class="nav-links">
            <a href="VendorDashboard.php">Dashboard</a>
            <a href="VendorOrders.php">Orders</a>
            <a href="VendorFoodCreate.php">Add Food</a>
            <a href="VendorLogout.php">Logout</a>
        </div>
    </div>

    <div class="settings-box">
        <h2>Edit Food Item</h2>
        <p class="settings-note">Only your vendor items can be edited.</p>

        <form method="POST" action="VendorFoodEdit.php?food_id=<?php echo (int)$foodId; ?>" enctype="multipart/form-data">
            <div class="form-group">
                <label for="FoodName">Food Name</label>
                <input type="text" id="FoodName" name="FoodName" required maxlength="100"
                       value="<?php echo htmlspecialchars((string)$item['FoodName'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="Price">Price (RM)</label>
                <input type="number" id="Price" name="Price" step="0.01" min="0" required
                       value="<?php echo htmlspecialchars((string)($item['Price'] ?? '0')); ?>">
            </div>

            <div class="form-group">
                <label for="Description">Description / Ingredients</label>
                <textarea id="Description" name="Description" rows="4"><?php echo htmlspecialchars((string)($item['Description'] ?? '')); ?></textarea>
            </div>

            <div class="form-group">
                <label for="Category">Category</label>
                <select id="Category" name="Category" required>
                    <option value="Main" <?php echo (($item['Category'] ?? '') === 'Main') ? 'selected' : ''; ?>>Main</option>
                    <option value="Drink" <?php echo (($item['Category'] ?? '') === 'Drink') ? 'selected' : ''; ?>>Drink</option>
                    <option value="Snack" <?php echo (($item['Category'] ?? '') === 'Snack') ? 'selected' : ''; ?>>Snack</option>
                </select>
            </div>

            <div class="form-group">
                <label for="DietaryTag">Dietary Tag</label>
                <input type="text" id="DietaryTag" name="DietaryTag" maxlength="100"
                       value="<?php echo htmlspecialchars((string)($item['DietaryTag'] ?? '')); ?>"
                       placeholder="e.g., Halal, Vegetarian, Protein">
            </div>

            <div class="form-group">
                <label for="Status">Status</label>
                <select id="Status" name="Status">
                    <option value="Available" <?php echo (($item['Status'] ?? 'Available') === 'Available') ? 'selected' : ''; ?>>Available</option>
                    <option value="Unavailable" <?php echo (($item['Status'] ?? '') === 'Unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                </select>
            </div>

            <div class="form-group">
                <label>Product Image</label>
                <?php if (!empty($item['Image'])): ?>
                    <div class="image-preview-box" id="imagePreview">
                        <img id="previewImg" src="<?php echo htmlspecialchars($item['Image']); ?>" alt="Current Image">
                    </div>
                    <p class="current-image-note">Current image shown above. Upload a new image to replace it.</p>
                <?php else: ?>
                    <div class="image-preview-box" id="imagePreview" style="display: none;">
                        <img id="previewImg" src="" alt="Preview">
                    </div>
                <?php endif; ?>
                <label for="Image" class="file-upload-label" id="uploadLabel" style="margin-top: 8px;">
                    <span class="upload-icon">&#128205;·</span>
                    <span class="upload-text">Click to choose a new image from your device</span>
                </label>
                <input type="file" id="Image" name="Image" accept="image/jpeg,image/png,image/gif,image/webp" class="file-input-hidden" onchange="previewImage(this)">
            </div>

            <button type="submit" name="Action_Update" value="Update" class="btn-primary btn-block">Update Food</button>
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




