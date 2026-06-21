<?php
session_start();
include('db.php');
include('db_helpers.php');

function redirectWithMessage(string $page, string $type, string $msg): void {
    header("Location: {$page}?type={$type}&msg=" . urlencode($msg));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // --- VENDOR REGISTRATION ---
    if (isset($_POST['Action_Insert'])) {
        $shopName       = trim($_POST['ShopName'] ?? '');
        $vendorPassword = $_POST['VendorPassword'] ?? '';

        if ($shopName === '' || $vendorPassword === '') {
            redirectWithMessage('VendorRegister.html', 'error', 'Shop name and password are required.');
        }

        $hashed = password_hash($vendorPassword, PASSWORD_DEFAULT);

        $existing = db_fetch_one($pdo, 'SELECT "VendorID" FROM vendor WHERE "ShopName" = ?', [$shopName]);
        if ($existing) {
            redirectWithMessage('VendorRegister.html', 'error', 'Shop name already exists.');
        }

        $rows = db_execute($pdo, 'INSERT INTO vendor ("ShopName", "VendorPassword") VALUES (?, ?)', [$shopName, $hashed]);
        if ($rows > 0) {
            redirectWithMessage('VendorLogin.html', 'success', 'Registration successful. Please login.');
        }
        redirectWithMessage('VendorRegister.html', 'error', 'Registration failed. Please try again.');
    }

    // --- VENDOR LOGIN ---
    elseif (isset($_POST['Action_Verify'])) {
        $shopName       = trim($_POST['ShopName'] ?? '');
        $vendorPassword = $_POST['VendorPassword'] ?? '';

        if ($shopName === '' || $vendorPassword === '') {
            redirectWithMessage('VendorLogin.html', 'error', 'Shop name and password are required.');
        }

        $row = db_fetch_one($pdo,
            'SELECT "VendorID", "ShopName", "VendorPassword" FROM vendor WHERE "ShopName" = ? LIMIT 1',
            [$shopName]
        );

        if ($row) {
            $stored = (string)($row['VendorPassword'] ?? '');
            $ok = ($stored !== '' && $stored[0] === '$')
                ? password_verify($vendorPassword, $stored)
                : hash_equals($stored, $vendorPassword);

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
