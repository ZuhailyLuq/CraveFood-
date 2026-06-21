<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- REGISTRATION LOGIC ---
    if (isset($_POST['Action_Insert'])) {
        $Username = trim($_POST['RName'] ?? '');
        $Email    = trim($_POST['REmail'] ?? '');
        $Phone    = trim($_POST['RTel'] ?? '');
        $Password = $_POST['RPass'] ?? '';

        if ($Username === '' || $Email === '' || $Phone === '' || $Password === '') {
            header("Location: Login.html?type=error&msg=" . urlencode("All fields are required."));
            exit();
        }

        $hashed_password = password_hash($Password, PASSWORD_DEFAULT);

        $existing = db_fetch_one($pdo,
            'SELECT "UserId" FROM "user" WHERE "Username" = ? OR "Email" = ?',
            [$Username, $Email]
        );
        if ($existing) {
            header("Location: Login.html?type=error&msg=" . urlencode("Username or Email already exists."));
            exit();
        }

        $rows = db_execute($pdo,
            'INSERT INTO "user" ("Username", "Email", "Phone", "Password") VALUES (?, ?, ?, ?)',
            [$Username, $Email, $Phone, $hashed_password]
        );

        if ($rows > 0) {
            header("Location: Login.html?type=success&msg=" . urlencode("Registration successful. Please login."));
        } else {
            header("Location: Login.html?type=error&msg=" . urlencode("Registration failed. Please try again."));
        }
        exit();

    // --- LOGIN LOGIC ---
    } elseif (isset($_POST['Action_Verify'])) {
        $Username = trim($_POST['LName'] ?? '');
        $Password = $_POST['LPass'] ?? '';

        if ($Username === '' || $Password === '') {
            header("Location: Login.html?type=error&msg=" . urlencode("Username and password are required."));
            exit();
        }

        $row = db_fetch_one($pdo,
            'SELECT "UserId", "Password" FROM "user" WHERE "Username" = ?',
            [$Username]
        );

        if ($row && password_verify($Password, $row['Password'])) {
            $_SESSION['UserID'] = $row['UserId'];
            header("Location: Homepage.php");
            exit();
        }

        $msg = $row ? "Incorrect password." : "User not found.";
        header("Location: Login.html?type=error&msg=" . urlencode($msg));
        exit();
    }
}
?>