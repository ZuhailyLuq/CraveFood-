<?php
session_start();
include('db.php');

header('Content-Type: application/json');

$action = trim($_POST['action'] ?? '');

// Vendor dismiss handler - must be before admin check so vendors can access it
if ($action === 'dismiss' && isset($_SESSION['VendorID'])) {
    $notifId = (int)($_POST['notification_id'] ?? 0);
    $vendorId = (int)$_SESSION['VendorID'];

    if ($notifId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification.']);
        exit();
    }

    $stmt = mysqli_prepare($conn, "UPDATE admin_notifications SET IsRead = 1 WHERE NotificationID = ? AND VendorID = ?");
    mysqli_stmt_bind_param($stmt, "ii", $notifId, $vendorId);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to dismiss.']);
    }
    exit();
}

// All other actions require admin
if (!isset($_SESSION['AdminID'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit();
}

// Auto-create admin_notifications table if it doesn't exist
$createTable = "CREATE TABLE IF NOT EXISTS `admin_notifications` (
    `NotificationID` INT AUTO_INCREMENT PRIMARY KEY,
    `VendorID` INT NOT NULL,
    `Message` TEXT NOT NULL,
    `IsRead` TINYINT(1) DEFAULT 0,
    `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(`VendorID`),
    INDEX(`IsRead`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $createTable);



if ($action === 'notify') {
    $vendorId = (int)($_POST['vendor_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($vendorId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid vendor.']);
        exit();
    }

    if ($message === '') {
        $message = 'Your store information has not been updated recently. Please review and update your store profile to keep your information current.';
    }

    // Check vendor exists
    $checkStmt = mysqli_prepare($conn, "SELECT VendorID FROM vendor WHERE VendorID = ?");
    mysqli_stmt_bind_param($checkStmt, "i", $vendorId);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);

    if (mysqli_num_rows($checkRes) === 0) {
        echo json_encode(['success' => false, 'message' => 'Vendor not found.']);
        exit();
    }

    // Insert notification
    $insertStmt = mysqli_prepare($conn, "INSERT INTO admin_notifications (VendorID, Message) VALUES (?, ?)");
    mysqli_stmt_bind_param($insertStmt, "is", $vendorId, $message);

    if (mysqli_stmt_execute($insertStmt)) {
        echo json_encode(['success' => true, 'message' => 'Notification sent successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send notification.']);
    }
    exit();
}

if ($action === 'notify_all_outdated') {
    $days = (int)($_POST['days'] ?? 30);
    if ($days <= 0) $days = 30;

    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        $message = 'Your store information has not been updated in over ' . $days . ' days. Please review and update your store profile to keep your information current.';
    }

    // Find vendors whose LastUpdate is older than X days or is NULL
    $sql = "SELECT VendorID FROM vendor WHERE LastUpdate IS NULL OR LastUpdate < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $count = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $vid = (int)$row['VendorID'];
        $insertStmt = mysqli_prepare($conn, "INSERT INTO admin_notifications (VendorID, Message) VALUES (?, ?)");
        mysqli_stmt_bind_param($insertStmt, "is", $vid, $message);
        if (mysqli_stmt_execute($insertStmt)) {
            $count++;
        }
    }

    echo json_encode(['success' => true, 'message' => "Notification sent to $count vendor(s).", 'count' => $count]);
    exit();
}



echo json_encode(['success' => false, 'message' => 'Unknown action.']);
?>
