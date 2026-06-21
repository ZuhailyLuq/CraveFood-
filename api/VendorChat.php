<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if (!isset($_SESSION['VendorID'])) {
    header("Location: Login.html");
    exit();
}

$vendorId = (int)$_SESSION['VendorID'];
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    header("Location: VendorOrders.php");
    exit();
}

// Verify vendor owns this order's food
$orderSql = 'SELECT o."OrderID", o."Status", mf."FoodName", u."Username"
             FROM orders o
             INNER JOIN menu_food mf ON mf."FoodID" = o."FoodID"
             LEFT JOIN "user" u ON u."UserID" = o."UserID"
             WHERE o."OrderID" = ? AND mf."VendorID" = ?
             LIMIT 1';
$order = db_fetch_one($pdo, $orderSql, [$orderId, $vendorId]);

if (!$order) {
    header("Location: VendorOrders.php");
    exit();
}

$isActive = !in_array($order['Status'], ['Finished', 'Completed', 'Cancelled']);
$customerName = $order['Username'] ?? 'Customer';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chat - Order #<?php echo $orderId; ?> - CraveFood</title>
    <link rel="stylesheet" href="../style.css?v=20260621-7">
    <style>
        .chat-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 130px);
        }
        .chat-header {
            background: #fff;
            padding: 16px 20px;
            border-radius: 14px 14px 0 0;
            border: 1px solid #ffe2e6;
            border-bottom: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-header h2 {
            margin: 0;
            color: #c1121f;
            font-size: 1.2rem;
        }
        .chat-header .chat-order-info {
            font-size: 0.85rem;
            color: #888;
        }
        .chat-messages {
            flex: 1;
            background: #fff;
            border-left: 1px solid #ffe2e6;
            border-right: 1px solid #ffe2e6;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .chat-bubble {
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 0.95rem;
            line-height: 1.4;
            word-wrap: break-word;
        }
        .chat-bubble.vendor {
            align-self: flex-end;
            background: #c1121f;
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .chat-bubble.user {
            align-self: flex-start;
            background: #f5f5f5;
            color: #333;
            border-bottom-left-radius: 4px;
        }
        .chat-bubble .bubble-time {
            font-size: 0.72rem;
            margin-top: 4px;
            opacity: 0.7;
        }
        .chat-bubble.vendor .bubble-time {
            text-align: right;
            color: #ffcdd2;
        }
        .chat-bubble.user .bubble-time {
            color: #999;
        }
        .chat-input-area {
            display: flex;
            gap: 8px;
            background: #fff;
            padding: 14px 20px;
            border-radius: 0 0 14px 14px;
            border: 1px solid #ffe2e6;
            border-top: none;
        }
        .chat-input-area input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #f0c1c8;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }
        .chat-input-area input:focus {
            border-color: #c1121f;
        }
        .chat-input-area button {
            background: #c1121f;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s ease;
        }
        .chat-input-area button:hover {
            background: #a40f1b;
        }
        .chat-input-area button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .chat-ended {
            text-align: center;
            padding: 14px;
            background: #fff3cd;
            color: #856404;
            border-radius: 0 0 14px 14px;
            border: 1px solid #ffe2e6;
            border-top: none;
            font-weight: 600;
        }
        .chat-empty {
            text-align: center;
            color: #bbb;
            padding: 40px;
            font-size: 0.95rem;
        }
        .chat-status-badge {
            background: #ffe8ec;
            color: #9f0f1c;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo"><h2>CraveFood</h2></div>
        <div class="nav-links">
            <a href="VendorDashboard.php">Dashboard</a>
            <a href="VendorOrders.php">Orders</a>
            <a href="VendorLogout.php">Logout</a>
        </div>
    </div>

    <div class="chat-container">
        <a href="VendorOrders.php" class="btn-return" style="margin-bottom: 10px;">â† Back to Orders</a>

        <div class="chat-header">
            <div>
                <h2>ðŸ’¬ <?php echo htmlspecialchars($customerName); ?></h2>
                <span class="chat-order-info">Order #<?php echo $orderId; ?> â€” <?php echo htmlspecialchars($order['FoodName']); ?></span>
            </div>
            <span class="chat-status-badge"><?php echo htmlspecialchars($order['Status']); ?></span>
        </div>

        <div class="chat-messages" id="chatMessages">
            <div class="chat-empty" id="chatEmpty">No messages yet. Waiting for customer...</div>
        </div>

        <?php if ($isActive): ?>
            <div class="chat-input-area" id="chatInputArea">
                <input type="text" id="chatInput" placeholder="Type a message..." autocomplete="off" maxlength="500">
                <button type="button" id="chatSendBtn" onclick="sendMessage()">Send</button>
            </div>
        <?php else: ?>
            <div class="chat-ended">
                This order is <?php echo htmlspecialchars($order['Status']); ?>. Chat is now closed.
            </div>
        <?php endif; ?>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    var page = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
    if (page === '' || page === 'index.php') page = 'homepage.php';
    var links = document.querySelectorAll('.nav-links a');
    links.forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === page || (page.startsWith('advancesearch') && href === 'homepage.php')) {
            link.classList.add('active');
        }
    });
});
</script>
</body>
</html>



