<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

header('Content-Type: application/json');

// Determine sender
$senderType = null;
$senderId   = 0;

if (isset($_SESSION['UserID'])) {
    $senderType = 'user';
    $senderId   = (int)$_SESSION['UserID'];
} elseif (isset($_SESSION['VendorID'])) {
    $senderType = 'vendor';
    $senderId   = (int)$_SESSION['VendorID'];
} else {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

$action  = trim($_POST['action'] ?? $_GET['action'] ?? '');
$orderId = (int)($_POST['order_id'] ?? $_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order.']);
    exit();
}

// Verify access: user must own the order, vendor must own the food
if ($senderType === 'user') {
    $access = db_fetch_one($pdo,
        'SELECT "OrderID" FROM orders WHERE "OrderID" = ? AND "UserID" = ?',
        [$orderId, $senderId]
    );
} else {
    $access = db_fetch_one($pdo,
        'SELECT o."OrderID" FROM orders o INNER JOIN menu_food mf ON o."FoodID" = mf."FoodID" WHERE o."OrderID" = ? AND mf."VendorID" = ?',
        [$orderId, $senderId]
    );
}

if (!$access) {
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
    $statusRow = db_fetch_one($pdo, 'SELECT "Status" FROM orders WHERE "OrderID" = ?', [$orderId]);
    if (!$statusRow || in_array($statusRow['Status'], ['Finished', 'Completed', 'Cancelled'])) {
        echo json_encode(['success' => false, 'message' => 'Order is no longer active. Chat disabled.']);
        exit();
    }

    $rows = db_execute($pdo,
        'INSERT INTO chat_messages ("OrderID", "SenderType", "SenderID", "Message") VALUES (?, ?, ?, ?)',
        [$orderId, $senderType, $senderId, $message]
    );

    echo json_encode(['success' => $rows > 0]);
    exit();
}

if ($action === 'fetch') {
    $afterId  = (int)($_GET['after_id'] ?? 0);
    $rows     = db_fetch_all($pdo,
        'SELECT "MessageID", "SenderType", "Message", "SentAt" FROM chat_messages WHERE "OrderID" = ? AND "MessageID" > ? ORDER BY "MessageID" ASC',
        [$orderId, $afterId]
    );

    $messages = [];
    foreach ($rows as $row) {
        $messages[] = [
            'id'      => (int)$row['MessageID'],
            'sender'  => $row['SenderType'],
            'message' => $row['Message'],
            'time'    => date('h:i A', strtotime($row['SentAt']))
        ];
    }

    $statusRow   = db_fetch_one($pdo, 'SELECT "Status" FROM orders WHERE "OrderID" = ?', [$orderId]);
    $orderActive = $statusRow && !in_array($statusRow['Status'], ['Finished', 'Completed', 'Cancelled']);

    echo json_encode(['success' => true, 'messages' => $messages, 'order_active' => $orderActive]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
?>
