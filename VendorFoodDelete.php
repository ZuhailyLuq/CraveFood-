<?php
session_start();
include('db.php');

if (!isset($_SESSION['VendorID'])) {
    header("Location: Login.html");
    exit();
}

$vendorId = (int)$_SESSION['VendorID'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['Action_Delete'])) {
    $foodId = isset($_POST['FoodID']) ? (int)$_POST['FoodID'] : 0;
    if ($foodId <= 0) {
        header("Location: VendorDashboard.php?type=error&msg=" . urlencode("Invalid food item."));
        exit();
    }

    $sql = "DELETE FROM MENU_FOOD WHERE FoodID = ? AND VendorID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $foodId, $vendorId);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: VendorDashboard.php?type=success&msg=" . urlencode("Food item deleted successfully."));
        exit();
    }

    header("Location: VendorDashboard.php?type=error&msg=" . urlencode("Failed to delete food item."));
    exit();
}

header("Location: VendorDashboard.php?type=error&msg=" . urlencode("Invalid request."));
exit();
?>

