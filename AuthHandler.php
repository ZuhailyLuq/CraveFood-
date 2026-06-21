<?php
session_start();
include('db.php');

// Auto-create admin table if it doesn't exist
$createAdmin = "CREATE TABLE IF NOT EXISTS `admin` (
    `AdminID` INT AUTO_INCREMENT PRIMARY KEY,
    `AdminUsername` VARCHAR(50) NOT NULL UNIQUE,
    `Email` VARCHAR(255),
    `Password` VARCHAR(255) NOT NULL,
    `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $createAdmin);

function authRedirect($type, $msg, $tab = 'login') {
    header("Location: Login.html?type={$type}&msg=" . urlencode($msg) . "&tab={$tab}");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: Login.html");
    exit();
}

$authAction = trim($_POST['auth_action'] ?? '');
$role = trim($_POST['role'] ?? '');

if (!in_array($role, ['user', 'vendor', 'admin'])) {
    authRedirect('error', 'Please select a valid role.');
}

// =====================
// REGISTRATION
// =====================
if ($authAction === 'register') {
    $password = $_POST['reg_password'] ?? '';
    if ($password === '') {
        authRedirect('error', 'Password is required.', 'register');
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // --- USER REGISTER ---
    if ($role === 'user') {
        $username = trim($_POST['reg_username'] ?? '');
        $email = trim($_POST['reg_email'] ?? '');
        $phone = trim($_POST['reg_phone'] ?? '');

        if ($username === '' || $email === '' || $phone === '') {
            authRedirect('error', 'All fields are required.', 'register');
        }

        $checkSql = "SELECT UserID FROM `user` WHERE Username = ? OR Email = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "ss", $username, $email);
        mysqli_stmt_execute($checkStmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt))) {
            authRedirect('error', 'Username or Email already exists.', 'register');
        }

        $sql = "INSERT INTO `user` (Username, Email, Phone, Password) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $phone, $hashed);

        if (mysqli_stmt_execute($stmt)) {
            authRedirect('success', 'Registration successful! Please login.');
        }
        authRedirect('error', 'Registration failed. Please try again.', 'register');
    }

    // --- VENDOR REGISTER ---
    if ($role === 'vendor') {
        $shopName = trim($_POST['reg_shopname'] ?? '');

        if ($shopName === '') {
            authRedirect('error', 'Shop name is required.', 'register');
        }

        $checkSql = "SELECT VendorID FROM vendor WHERE ShopName = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "s", $shopName);
        mysqli_stmt_execute($checkStmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt))) {
            authRedirect('error', 'Shop name already exists.', 'register');
        }

        $sql = "INSERT INTO vendor (ShopName, VendorPassword) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $shopName, $hashed);

        if (mysqli_stmt_execute($stmt)) {
            authRedirect('success', 'Vendor registration successful! Please login.');
        }
        authRedirect('error', 'Registration failed. Please try again.', 'register');
    }

    // --- ADMIN REGISTER ---
    if ($role === 'admin') {
        $adminName = trim($_POST['reg_admin_name'] ?? '');
        $adminEmail = trim($_POST['reg_admin_email'] ?? '');

        if ($adminName === '' || $adminEmail === '') {
            authRedirect('error', 'Admin username and email are required.', 'register');
        }

        $checkSql = "SELECT AdminID FROM `admin` WHERE AdminUsername = ? OR Email = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "ss", $adminName, $adminEmail);
        mysqli_stmt_execute($checkStmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt))) {
            authRedirect('error', 'Admin username or email already exists.', 'register');
        }

        $sql = "INSERT INTO `admin` (AdminUsername, Email, Password) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $adminName, $adminEmail, $hashed);

        if (mysqli_stmt_execute($stmt)) {
            authRedirect('success', 'Admin registration successful! Please login.');
        }
        authRedirect('error', 'Registration failed. Please try again.', 'register');
    }
}

// =====================
// LOGIN
// =====================
if ($authAction === 'login') {
    $password = $_POST['login_password'] ?? '';
    if ($password === '') {
        authRedirect('error', 'Password is required.');
    }

    // --- USER LOGIN ---
    if ($role === 'user') {
        $username = trim($_POST['login_username'] ?? '');
        if ($username === '') {
            authRedirect('error', 'Username is required.');
        }

        $sql = "SELECT UserID, Password FROM `user` WHERE Username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $row['Password'])) {
                $_SESSION['UserID'] = $row['UserID'];
                header("Location: Homepage.php");
                exit();
            }
            authRedirect('error', 'Incorrect password.');
        }
        authRedirect('error', 'User not found.');
    }

    // --- VENDOR LOGIN ---
    if ($role === 'vendor') {
        $shopName = trim($_POST['login_shopname'] ?? '');
        if ($shopName === '') {
            authRedirect('error', 'Shop name is required.');
        }

        $sql = "SELECT VendorID, ShopName, VendorPassword FROM vendor WHERE ShopName = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $shopName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            $stored = (string)($row['VendorPassword'] ?? '');
            $ok = false;
            if ($stored !== '' && isset($stored[0]) && $stored[0] === '$') {
                $ok = password_verify($password, $stored);
            } else {
                $ok = hash_equals($stored, $password);
            }
            if ($ok) {
                $_SESSION['VendorID'] = (int)$row['VendorID'];
                $_SESSION['ShopName'] = (string)($row['ShopName'] ?? $shopName);
                header("Location: VendorDashboard.php?type=success&msg=" . urlencode('Login successful.'));
                exit();
            }
        }
        authRedirect('error', 'Invalid shop name or password.');
    }

    // --- ADMIN LOGIN ---
    if ($role === 'admin') {
        $adminName = trim($_POST['login_admin_name'] ?? '');
        if ($adminName === '') {
            authRedirect('error', 'Admin username is required.');
        }

        $sql = "SELECT AdminID, Password FROM `admin` WHERE AdminUsername = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $adminName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $row['Password'])) {
                $_SESSION['AdminID'] = (int)$row['AdminID'];
                $_SESSION['AdminUsername'] = $adminName;
                header("Location: AdminDashboard.php");
                exit();
            }
            authRedirect('error', 'Incorrect admin password.');
        }
        authRedirect('error', 'Admin not found.');
    }
}

authRedirect('error', 'Invalid request.');
?>
