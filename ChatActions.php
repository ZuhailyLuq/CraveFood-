<?php
session_start();
include('db.php');

header('Content-Type: application/json');

// Auto-create chat_messages table if it doesn't exist
$createTable = "CREATE TABLE IF NOT EXISTS `chat_messages` (
    `MessageID` INT AUTO_INCREMENT PRIMARY KEY,
    `OrderID` INT NOT NULL,
    `SenderType` ENUM('user','vendor') NOT NULL,
    `SenderID` INT NOT NULL,
    `Message` TEXT NOT NULL,
    `SentAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(`OrderID`),
    INDEX(`SentAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $createTable);

// Determine sender
$senderType = null;
$senderId = 0;

if (isset($_SESSION['UserID'])) {
    $senderType = 'user';
    $senderId = (int)$_SESSION['UserID'];
} elseif (isset($_SESSION['VendorID'])) {
    $senderType = 'vendor';
    $senderId = (int)$_SESSION['VendorID'];
} else {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
$orderId = (int)($_POST['order_id'] ?? $_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order.']);
    exit();
}

// Verify access: user must own the order, vendor must own the food
if ($senderType === 'user') {
    $checkSql = "SELECT OrderID FROM orders WHERE OrderID = ? AND UserID = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "ii", $orderId, $senderId);
} else {
    $checkSql = "SELECT o.OrderID FROM orders o INNER JOIN MENU_FOOD mf ON o.FoodID = mf.FoodID WHERE o.OrderID = ? AND mf.VendorID = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "ii", $orderId, $senderId);
}
mysqli_stmt_execute($checkStmt);
$checkRes = mysqli_stmt_get_result($checkStmt);
if (mysqli_num_rows($checkRes) === 0) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

if ($action === 'send') {
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        echo json_encode(['success' => false, 'message' => 'Empty message.']);
        exit();
    }

    // Check order is still active
    $statusSql = "SELECT Status FROM orders WHERE OrderID = ?";
    $statusStmt = mysqli_prepare($conn, $statusSql);
    mysqli_stmt_bind_param($statusStmt, "i", $orderId);
    mysqli_stmt_execute($statusStmt);
    $statusRes = mysqli_stmt_get_result($statusStmt);
    $statusRow = mysqli_fetch_assoc($statusRes);
    if (!$statusRow || in_array($statusRow['Status'], ['Finished', 'Completed', 'Cancelled'])) {
        echo json_encode(['success' => false, 'message' => 'Order is no longer active. Chat disabled.']);
        exit();
    }

    $insertSql = "INSERT INTO chat_messages (OrderID, SenderType, SenderID, Message) VALUES (?, ?, ?, ?)";
    $insertStmt = mysqli_prepare($conn, $insertSql);
    mysqli_stmt_bind_param($insertStmt, "isis", $orderId, $senderType, $senderId, $message);
    
    if (mysqli_stmt_execute($insertStmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message.']);
    }
    exit();
}

if ($action === 'fetch') {
    $afterId = (int)($_GET['after_id'] ?? 0);
    
    $fetchSql = "SELECT MessageID, SenderType, Message, SentAt FROM chat_messages WHERE OrderID = ? AND MessageID > ? ORDER BY MessageID ASC";
    $fetchStmt = mysqli_prepare($conn, $fetchSql);
    mysqli_stmt_bind_param($fetchStmt, "ii", $orderId, $afterId);
    mysqli_stmt_execute($fetchStmt);
    $fetchRes = mysqli_stmt_get_result($fetchStmt);
    
    $messages = [];
    while ($row = mysqli_fetch_assoc($fetchRes)) {
        $messages[] = [
            'id' => (int)$row['MessageID'],
            'sender' => $row['SenderType'],
            'message' => $row['Message'],
            'time' => date('h:i A', strtotime($row['SentAt']))
        ];
    }
    
    // Also check order status
    $statusSql = "SELECT Status FROM orders WHERE OrderID = ?";
    $statusStmt = mysqli_prepare($conn, $statusSql);
    mysqli_stmt_bind_param($statusStmt, "i", $orderId);
    mysqli_stmt_execute($statusStmt);
    $statusRes = mysqli_stmt_get_result($statusStmt);
    $statusRow = mysqli_fetch_assoc($statusRes);
    $orderActive = $statusRow && !in_array($statusRow['Status'], ['Finished', 'Completed', 'Cancelled']);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'order_active' => $orderActive
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
?>
