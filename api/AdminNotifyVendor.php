<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

header('Content-Type: application/json');

$action = trim($_POST['action'] ?? '');

// Vendor dismiss handler â€” before admin check so vendors can access it
if ($action === 'dismiss' && isset($_SESSION['VendorID'])) {
    $notifId  = (int)($_POST['notification_id'] ?? 0);
    $vendorId = (int)$_SESSION['VendorID'];

    if ($notifId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification.']);
        exit();
    }

    $rows = db_execute($pdo,
        'UPDATE admin_notifications SET "IsRead" = TRUE WHERE "NotificationID" = ? AND "VendorID" = ?',
        [$notifId, $vendorId]
    );
    echo json_encode(['success' => $rows > 0]);
    exit();
}

// All other actions require admin
if (!isset($_SESSION['AdminID'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit();
}

if ($action === 'notify') {
    $vendorId = (int)($_POST['vendor_id'] ?? 0);
    $message  = trim($_POST['message'] ?? '');

    if ($vendorId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid vendor.']); exit(); }
    if ($message === '') {
        $message = 'Your store information has not been updated recently. Please review and update your store profile to keep your information current.';
    }

    $vendor = db_fetch_one($pdo, 'SELECT "VendorID" FROM vendor WHERE "VendorID" = ?', [$vendorId]);
    if (!$vendor) { echo json_encode(['success' => false, 'message' => 'Vendor not found.']); exit(); }

    $rows = db_execute($pdo,
        'INSERT INTO admin_notifications ("VendorID", "Message") VALUES (?, ?)',
        [$vendorId, $message]
    );
    echo json_encode(['success' => $rows > 0, 'message' => $rows > 0 ? 'Notification sent successfully.' : 'Failed to send notification.']);
    exit();
}

if ($action === 'notify_all_outdated') {
    $days    = max(1, (int)($_POST['days'] ?? 30));
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        $message = "Your store information has not been updated in over {$days} days. Please review and update your store profile to keep your information current.";
    }

    // PostgreSQL uses NOW() - INTERVAL '? days' with positional params
    $outdated = db_fetch_all($pdo,
        "SELECT \"VendorID\" FROM vendor WHERE \"LastUpdate\" IS NULL OR \"LastUpdate\" < NOW() - INTERVAL '{$days} days'"
    );

    $count = 0;
    foreach ($outdated as $row) {
        $vid  = (int)$row['VendorID'];
        $rows = db_execute($pdo,
            'INSERT INTO admin_notifications ("VendorID", "Message") VALUES (?, ?)',
            [$vid, $message]
        );
        if ($rows > 0) $count++;
    }

    echo json_encode(['success' => true, 'message' => "Notification sent to $count vendor(s).", 'count' => $count]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
?>
