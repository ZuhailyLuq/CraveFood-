<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

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
        $email    = trim($_POST['reg_email'] ?? '');
        $phone    = trim($_POST['reg_phone'] ?? '');

        if ($username === '' || $email === '' || $phone === '') {
            authRedirect('error', 'All fields are required.', 'register');
        }

        $existing = db_fetch_one($pdo,
            'SELECT "UserId" FROM "user" WHERE "Username" = ? OR "Email" = ?',
            [$username, $email]
        );
        if ($existing) {
            authRedirect('error', 'Username or Email already exists.', 'register');
        }

        $rows = db_execute($pdo,
            'INSERT INTO "user" ("Username", "Email", "Phone", "Password") VALUES (?, ?, ?, ?)',
            [$username, $email, $phone, $hashed]
        );
        if ($rows > 0) {
            authRedirect('success', 'Registration successful! Please login.');
        }
        authRedirect('error', 'Registration failed. Please try again.', 'register');
    }

    // --- VENDOR REGISTER ---
    if ($role === 'vendor') {
        $shopName = trim($_POST['reg_shopname'] ?? '');
        $vendorEmail = trim($_POST['reg_vendor_email'] ?? '');
        
        if ($shopName === '' || $vendorEmail === '') {
            authRedirect('error', 'Shop name and email are required.', 'register');
        }

        $existing = db_fetch_one($pdo,
            'SELECT "VendorID" FROM vendor WHERE "ShopName" = ? OR "Email" = ?',
            [$shopName, $vendorEmail]
        );
        if ($existing) {
            authRedirect('error', 'Shop name or email already exists.', 'register');
        }

        $rows = db_execute($pdo,
            'INSERT INTO vendor ("ShopName", "Email", "VendorPassword") VALUES (?, ?, ?)',
            [$shopName, $vendorEmail, $hashed]
        );
        if ($rows > 0) {
            authRedirect('success', 'Vendor registration successful! Please login.');
        }
        authRedirect('error', 'Registration failed. Please try again.', 'register');
    }
    }

}

// =====================
// RESET PASSWORD
// =====================
if ($authAction === 'reset_password') {
    $role = $_POST['role'] ?? 'user';
    $email = trim($_POST['reset_email'] ?? '');
    $newPass = $_POST['reset_password'] ?? '';
    $rePass = $_POST['reset_password_confirm'] ?? '';

    if ($email === '' || $newPass === '' || $rePass === '') {
        header("Location: ResetPassword.html?type=error&msg=" . urlencode('All fields are required.')); exit();
    }
    
    if ($newPass !== $rePass) {
        header("Location: ResetPassword.html?type=error&msg=" . urlencode('Passwords do not match.')); exit();
    }
    
    if (strlen($newPass) < 6) {
        header("Location: ResetPassword.html?type=error&msg=" . urlencode('Password must be at least 6 characters.')); exit();
    }
    
    $hashed = password_hash($newPass, PASSWORD_DEFAULT);
    
    if ($role === 'user') {
        $existing = db_fetch_one($pdo, 'SELECT "UserId" FROM "user" WHERE "Email" = ?', [$email]);
        if (!$existing) { header("Location: ResetPassword.html?type=error&msg=" . urlencode('Email not found in our system.')); exit(); }
        
        db_execute($pdo, 'UPDATE "user" SET "Password" = ? WHERE "Email" = ?', [$hashed, $email]);
    } 
    elseif ($role === 'vendor') {
        $existing = db_fetch_one($pdo, 'SELECT "VendorID" FROM vendor WHERE "Email" = ?', [$email]);
        if (!$existing) { header("Location: ResetPassword.html?type=error&msg=" . urlencode('Email not found in our system.')); exit(); }
        
        db_execute($pdo, 'UPDATE vendor SET "VendorPassword" = ? WHERE "Email" = ?', [$hashed, $email]);
    }
    
    authRedirect('success', 'Password reset successful! You can now login.', 'login');
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

        $row = db_fetch_one($pdo,
            'SELECT "UserId", "Password" FROM "user" WHERE "Username" = ?',
            [$username]
        );
        if ($row && password_verify($password, $row['Password'])) {
            $_SESSION['UserID'] = $row['UserId'];
            header("Location: Homepage.php");
            exit();
        }
        authRedirect('error', $row ? 'Incorrect password.' : 'User not found.');
    }

    // --- VENDOR LOGIN ---
    if ($role === 'vendor') {
        $shopName = trim($_POST['login_shopname'] ?? '');
        if ($shopName === '') {
            authRedirect('error', 'Shop name is required.');
        }

        $row = db_fetch_one($pdo,
            'SELECT "VendorID", "ShopName", "VendorPassword" FROM vendor WHERE "ShopName" = ? LIMIT 1',
            [$shopName]
        );
        if ($row) {
            $stored = (string)($row['VendorPassword'] ?? '');
            $ok = ($stored !== '' && $stored[0] === '$')
                ? password_verify($password, $stored)
                : hash_equals($stored, $password);
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

        $row = db_fetch_one($pdo,
            'SELECT "AdminID", "Password" FROM admin WHERE "AdminUsername" = ? LIMIT 1',
            [$adminName]
        );
        if ($row && password_verify($password, $row['Password'])) {
            $_SESSION['AdminID']       = (int)$row['AdminID'];
            $_SESSION['AdminUsername'] = $adminName;
            header("Location: AdminDashboard.php");
            exit();
        }
        authRedirect('error', $row ? 'Incorrect admin password.' : 'Admin not found.');
    }
}

authRedirect('error', 'Invalid request.');
?>
