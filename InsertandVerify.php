<?php
session_start();
include('db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST"){

    // --- REGISTRATION LOGIC ---
    if(isset($_POST['Action_Insert'])){
        $Username = trim($_POST['RName'] ?? '');
        $Email = trim($_POST['REmail'] ?? '');
        $Phone = trim($_POST['RTel'] ?? '');
        $Password = $_POST['RPass'] ?? '';

        if ($Username === '' || $Email === '' || $Phone === '' || $Password === '') {
            header("Location: Register.html?type=error&msg=" . urlencode("All fields are required."));
            exit();
        }

        $hashed_password = password_hash($Password, PASSWORD_DEFAULT);

        $checkSql = "SELECT UserID FROM `user` WHERE Username = ? OR Email = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "ss", $Username, $Email);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);

        if (mysqli_fetch_assoc($checkResult)) {
            header("Location: Register.html?type=error&msg=" . urlencode("Username or Email already exists."));
            exit();
        }

        $sql = "INSERT INTO `user` (Username, Email, Phone, Password) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        // FIXED: Bound $hashed_password instead of plain text $Password
        mysqli_stmt_bind_param($stmt, "ssss", $Username, $Email, $Phone, $hashed_password);

        if(mysqli_stmt_execute($stmt)){
            header("Location: Login.html?type=success&msg=" . urlencode("Registration successful. Please login."));
            exit();
        } else {
            header("Location: Register.html?type=error&msg=" . urlencode("Registration failed. Please try again."));
            exit();
        }

    // --- LOGIN LOGIC ---
    } elseif(isset($_POST['Action_Verify'])){
        $Username = trim($_POST['LName'] ?? '');
        $Password = $_POST['LPass'] ?? '';

        if ($Username === '' || $Password === '') {
            header("Location: Login.html?type=error&msg=" . urlencode("Username and password are required."));
            exit();
        }

        // FIXED: Select UserID instead of Username so we can match what Homepage.php expects
        $sql = "SELECT UserID, Password FROM `user` WHERE Username = ?";
        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param($stmt, "s", $Username);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if($row = mysqli_fetch_assoc($result)){
            if(password_verify($Password, $row['Password'])){
                // FIXED: Save UserID to match the security check in Homepage.php
                $_SESSION['UserID'] = $row['UserID'];

                header("Location: Homepage.php");
                exit();
            } else {
                header("Location: Login.html?type=error&msg=" . urlencode("Incorrect password."));
                exit();
            }
        } else {
            header("Location: Login.html?type=error&msg=" . urlencode("User not found."));
            exit();
        }
    }
}
?>