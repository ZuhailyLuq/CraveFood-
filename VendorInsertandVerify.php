<?php
session_start();
include('db.php');

function redirectWithMessage(string $page, string $type, string $msg): void {
    header("Location: {$page}?type={$type}&msg=" . urlencode($msg));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // --- VENDOR REGISTRATION LOGIC ---
    if (isset($_POST['Action_Insert'])) {
        $shopName = trim($_POST['ShopName'] ?? '');
        $vendorPassword = $_POST['VendorPassword'] ?? '';

        if ($shopName === '' || $vendorPassword === '') {
            redirectWithMessage('VendorRegister.html', 'error', 'Shop name and password are required.');
        }

        $hashed_password = password_hash($vendorPassword, PASSWORD_DEFAULT);

        $checkSql = "SELECT VendorID FROM vendor WHERE ShopName = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "s", $shopName);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);

        if (mysqli_fetch_assoc($checkResult)) {
            redirectWithMessage('VendorRegister.html', 'error', 'Shop name already exists.');
        }

        $sql = "INSERT INTO vendor (ShopName, VendorPassword) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $shopName, $hashed_password);

        if (mysqli_stmt_execute($stmt)) {
            redirectWithMessage('VendorLogin.html', 'success', 'Registration successful. Please login.');
        } else {
            redirectWithMessage('VendorRegister.html', 'error', 'Registration failed. Please try again.');
        }
    }

    // --- VENDOR LOGIN LOGIC ---
    elseif (isset($_POST['Action_Verify'])) {
        $shopName = trim($_POST['ShopName'] ?? '');
        $vendorPassword = $_POST['VendorPassword'] ?? '';

        if ($shopName === '' || $vendorPassword === '') {
            redirectWithMessage('VendorLogin.html', 'error', 'Shop name and password are required.');
        }

        // Login by ShopName + VendorPassword
        $sql = "SELECT VendorID, ShopName, VendorPassword FROM vendor WHERE ShopName = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $shopName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            $stored = (string)($row['VendorPassword'] ?? '');
            $ok = false;

            // Support both hashed (password_hash) and plain-text passwords (for compatibility)
            if ($stored !== '' && isset($stored[0]) && $stored[0] === '$') {
                $ok = password_verify($vendorPassword, $stored);
            } else {
                $ok = hash_equals($stored, $vendorPassword);
            }

            if ($ok) {
                $_SESSION['VendorID'] = (int)$row['VendorID'];
                $_SESSION['ShopName'] = (string)($row['ShopName'] ?? $shopName);

                header("Location: VendorDashboard.php?type=success&msg=" . urlencode('Login successful.'));
                exit();
            }
        }

        redirectWithMessage('VendorLogin.html', 'error', 'Invalid shop name or password.');
    }
}

redirectWithMessage('VendorLogin.html', 'error', 'Invalid request.');
?>

