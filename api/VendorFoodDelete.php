<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if (!isset($_SESSION['VendorID'])) {
    header("Location: Login.html");
    exit();
}

$vendorId = (int)$_SESSION['VendorID'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['Action_Delete'])) {
    $foodId = (int)($_POST['FoodID'] ?? 0);
    if ($foodId <= 0) {
        header("Location: VendorDashboard.php?type=error&msg=" . urlencode("Invalid food item."));
        exit();
    }

    $rows = db_execute($pdo,
        'DELETE FROM menu_food WHERE "FoodID" = ? AND "VendorID" = ?',
        [$foodId, $vendorId]
    );

    if ($rows > 0) {
        header("Location: VendorDashboard.php?type=success&msg=" . urlencode("Food item deleted successfully."));
    } else {
        header("Location: VendorDashboard.php?type=error&msg=" . urlencode("Failed to delete food item."));
    }
    exit();
}

header("Location: VendorDashboard.php?type=error&msg=" . urlencode("Invalid request."));
exit();
?>
