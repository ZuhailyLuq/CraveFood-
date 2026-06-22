<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';

if (!isset($_SESSION['VendorID'])) {
    header("Location: Login.html");
    exit();
}

$vendorId = (int)$_SESSION['VendorID'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['Action_Save'])) {
    $foodName    = trim($_POST['FoodName'] ?? '');
    $price       = $_POST['Price'] ?? '';
    $description = trim($_POST['Description'] ?? '');
    $category    = trim($_POST['Category'] ?? '');
    $dietaryTag  = trim($_POST['DietaryTag'] ?? '');
    $status      = trim($_POST['Status'] ?? 'Available');

    if ($foodName === '') {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Food name is required.")); exit();
    }
    if (!isset($_FILES['Image']) || $_FILES['Image']['error'] !== UPLOAD_ERR_OK) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Product image is required.")); exit();
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($_FILES['Image']['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Only JPG, PNG, GIF, and WEBP images are allowed.")); exit();
    }
    if ($_FILES['Image']['size'] > 5 * 1024 * 1024) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Image size must be under 5MB.")); exit();
    }
    if (!is_numeric($price) || (float)$price < 0) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Valid price is required.")); exit();
    }
    if ($category === '') {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Category is required.")); exit();
    }
    if ($status === '') $status = 'Available';

    $uploadDir = __DIR__ . '/uploads/food/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext      = pathinfo($_FILES['Image']['name'], PATHINFO_EXTENSION);
    $fileName = 'food_' . $vendorId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;

    if (!move_uploaded_file($_FILES['Image']['tmp_name'], $uploadDir . $fileName)) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Failed to upload image.")); exit();
    }

    $image = 'uploads/food/' . $fileName;
    $stmt  = $pdo->prepare(
        'INSERT INTO menu_food ("VendorID","FoodName","Price","Description","Category","DietaryTag","Status","Image") VALUES (?,?,?,?,?,?,?,?)'
    );
    if ($stmt->execute([$vendorId, $foodName, (float)$price, $description, $category, $dietaryTag, $status, $image])) {
        header("Location: VendorDashboard.php?type=success&msg=" . urlencode("Food item added successfully.")); exit();
    }
    header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Failed to add food item.")); exit();
}
?>
<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';

if (!isset($_SESSION['VendorID'])) {
    header("Location: Login.html");
    exit();
}

$vendorId = (int)$_SESSION['VendorID'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['Action_Save'])) {
    $foodName    = trim($_POST['FoodName'] ?? '');
    $price       = $_POST['Price'] ?? '';
    $description = trim($_POST['Description'] ?? '');
    $category    = trim($_POST['Category'] ?? '');
    $dietaryTag  = trim($_POST['DietaryTag'] ?? '');
    $status      = trim($_POST['Status'] ?? 'Available');

    if ($foodName === '') {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Food name is required.")); exit();
    }
    if (!isset($_FILES['Image']) || $_FILES['Image']['error'] !== UPLOAD_ERR_OK) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Product image is required.")); exit();
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($_FILES['Image']['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Only JPG, PNG, GIF, and WEBP images are allowed.")); exit();
    }
    if ($_FILES['Image']['size'] > 5 * 1024 * 1024) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Image size must be under 5MB.")); exit();
    }
    if (!is_numeric($price) || (float)$price < 0) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Valid price is required.")); exit();
    }
    if ($category === '') {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Category is required.")); exit();
    }
    if ($status === '') $status = 'Available';

    $uploadDir = __DIR__ . '/uploads/food/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext      = pathinfo($_FILES['Image']['name'], PATHINFO_EXTENSION);
    $fileName = 'food_' . $vendorId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;

    if (!move_uploaded_file($_FILES['Image']['tmp_name'], $uploadDir . $fileName)) {
        header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Failed to upload image.")); exit();
    }

    $image = 'uploads/food/' . $fileName;
    $stmt  = $pdo->prepare(
        'INSERT INTO menu_food ("VendorID","FoodName","Price","Description","Category","DietaryTag","Status","Image") VALUES (?,?,?,?,?,?,?,?)'
    );
    if ($stmt->execute([$vendorId, $foodName, (float)$price, $description, $category, $dietaryTag, $status, $image])) {
        header("Location: VendorDashboard.php?type=success&msg=" . urlencode("Food item added successfully.")); exit();
    }
    header("Location: VendorFoodCreate.php?type=error&msg=" . urlencode("Failed to add food item.")); exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Food - Vendor - CraveFood</title>
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <style>
        .image-preview-box { margin-top:10px; max-width:220px; border-radius:12px; overflow:hidden; border:2px dashed #e8e8e8; display:none; }
        .image-preview-box img { width:100%; display:block; }
        .file-upload-label { display:inline-block; padding:16px 20px; background:#fff; border:2px dashed #e8e8e8; border-radius:12px; cursor:pointer; text-align:center; width:100%; box-sizing:border-box; transition:border-color 0.2s,background 0.2s; }
        .file-upload-label:hover { border-color:#ff2a44; background:#fff0f2; }
        .file-upload-label .upload-icon { font-size:1.8rem; display:block; margin-bottom:8px; }
        .file-upload-label .upload-text { font-size:0.95rem; color:#444; font-weight:600; }
        .file-upload-label .upload-subtext { font-size:0.8rem; color:#888; }
        .file-input-hidden { display:none; }

        .premium-form-card {
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            padding: 32px;
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: #333; margin-bottom: 8px; font-size: 0.9rem; }
        .modern-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            font-size: 0.95rem;
            color: #333;
            background: #fdfdfd;
            transition: border-color 0.2s, background 0.2s;
            box-sizing: border-box;
        }
        .modern-input:focus {
            outline: none;
            border-color: #ff2a44;
            background: #fff;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ff2a44;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            width: 100%;
            margin-top: 10px;
        }
        .btn-primary:hover { background: #cc001b; transform: translateY(-2px); }
        .btn-return { display: block; text-align: center; margin-top: 20px; color: #666; font-weight: 600; text-decoration: none; transition: color 0.2s; }
        .btn-return:hover { color: #333; }
    </style>
</head>
<body>
    <?php include('vendor_header.php'); ?>

    <?php
        $noticeMsg  = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
        $noticeType = $_GET['type'] ?? 'error';
    ?>
    <?php if ($noticeMsg !== ''): ?>
        <div class="notice show <?php echo ($noticeType === 'success') ? 'notice-success' : 'notice-error'; ?>">
            <?php echo htmlspecialchars($noticeMsg); ?>
        </div>
    <?php endif; ?>

    <div style="padding: 40px 20px;">
        <div style="text-align:center; margin-bottom:30px;">
            <h1 class="hero-title" style="font-size:2.2rem; font-weight:800; color:#1a1a1a; margin:0 0 8px; letter-spacing:-0.5px;">Add Food Item</h1>
            <p style="color:#666; font-size:1rem; margin:0;">Create a new menu item for your vendor profile.</p>
        </div>

        <div class="premium-form-card">
        <form class="auth-form" method="POST" action="VendorFoodCreate.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="FoodName">Food Name</label>
                <input type="text" class="modern-input"  id="FoodName" name="FoodName" placeholder="e.g., Nasi Lemak" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="Price">Price (RM)</label>
                <input type="number" class="modern-input"  id="Price" name="Price" placeholder="e.g., 5.50" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="Description">Description / Ingredients</label>
                <textarea class="modern-input"  id="Description" name="Description" placeholder="Brief details..." rows="4"></textarea>
            </div>
            <div class="form-group">
                <label for="Category">Category</label>
                <select class="modern-input"  id="Category" name="Category" required>
                    <option value="Main">Main</option>
                    <option value="Drink">Drink</option>
                    <option value="Snack">Snack</option>
                </select>
            </div>
            <div class="form-group">
                <label for="DietaryTag">Dietary Tag</label>
                <input type="text" class="modern-input"  id="DietaryTag" name="DietaryTag" placeholder="e.g., Halal, Vegetarian, Protein" maxlength="100">
            </div>
            <div class="form-group">
                <label for="Status">Status</label>
                <select class="modern-input"  id="Status" name="Status">
                    <option value="Available" selected>Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>
            <div class="form-group">
                <label>Product Image</label>
                <label for="Image" class="file-upload-label" id="uploadLabel">
                    <span class="upload-icon">&#128247;</span>
                    <span class="upload-text">Click to choose an image</span>
                    <span class="upload-subtext" style="display:block;margin-top:4px;">Supported formats: JPG, PNG, GIF, WEBP</span>
                    <input type="file" id="Image" name="Image" accept="image/jpeg,image/png,image/gif,image/webp" class="file-input-hidden" onchange="previewImage(this)">
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) { img.src = e.target.result; preview.style.display = 'block'; };
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>
