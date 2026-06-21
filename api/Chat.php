<?php
require_once __DIR__ . '/session.php';
include('db.php');
include('db_helpers.php');

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.html");
    exit();
}

$userId = (int)$_SESSION['UserID'];
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    header("Location: Homepage.php");
    exit();
}

// Verify user owns this order and get vendor info
$orderSql = 'SELECT o."OrderID", o."Status", mf."FoodName", mf."VendorID", v."ShopName"
             FROM orders o
             INNER JOIN menu_food mf ON mf."FoodID" = o."FoodID"
             LEFT JOIN vendor v ON v."VendorID" = mf."VendorID"
             WHERE o."OrderID" = ? AND o."UserID" = ?
             LIMIT 1';
$order = db_fetch_one($pdo, $orderSql, [$orderId, $userId]);

if (!$order) {
    header("Location: Homepage.php");
    exit();
}

$isActive = !in_array($order['Status'], ['Finished', 'Completed', 'Cancelled']);
$vendorName = $order['ShopName'] ?? ('Vendor #' . $order['VendorID']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Order #<?php echo $orderId; ?> - CraveFood</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=20260621-7">
    <style>
        /* â”€â”€ Reset & base â”€â”€ */
        *, body { font-family: 'Inter', 'Segoe UI', sans-serif; box-sizing: border-box; }
        body { background: #ffffff; margin: 0; padding: 0; color: #1e1e1e; }

        .chat-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px 20px 60px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 60px); /* 60px navbar */
        }

        .nav-back {
            color: #888;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 12px;
            align-self: flex-start;
            transition: color 0.2s;
        }
        .nav-back:hover {
            color: #2d2d2d;
        }

        /* â”€â”€ Chat Card â”€â”€ */
        .chat-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 8px 32px rgba(193,18,31,0.06), 0 2px 8px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            flex: 1; /* take up remaining height */
            overflow: hidden;
        }

        /* â”€â”€ Header â”€â”€ */
        .chat-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            flex-shrink: 0;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .vendor-avatar {
            width: 48px;
            height: 48px;
            background: #f9fafb;
            border-radius: 50%;
            border: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .vendor-avatar svg {
            width: 24px;
            height: 24px;
            fill: #ff2a44;
        }
        .header-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .vendor-name {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            color: #2d2d2d;
        }
        .context-text {
            font-size: 0.85rem;
            color: #888;
            font-weight: 500;
        }

        .header-right .status-pill {
            background: #f9fafb;
            color: #ff2a44;
            border: 1px solid #e0e0e0;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* â”€â”€ Message Area (Body) â”€â”€ */
        .chat-body {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #fdfdfd;
        }
        .empty-state {
            text-align: center;
            color: #aaa;
            font-size: 0.95rem;
            font-weight: 500;
            margin: auto 0; /* vertically center if alone */
        }

        /* Chat Bubbles */
        .chat-bubble {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 0.95rem;
            line-height: 1.4;
            word-wrap: break-word;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        /* User */
        .chat-bubble.user {
            align-self: flex-end;
            background: #ff2a44;
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        /* Vendor */
        .chat-bubble.vendor {
            align-self: flex-start;
            background: #f1f3f5;
            color: #2d2d2d;
            border-bottom-left-radius: 4px;
            border: 1px solid #e9ecef;
        }

        .bubble-time {
            font-size: 0.72rem;
            margin-top: 6px;
            font-weight: 600;
        }
        .chat-bubble.user .bubble-time {
            text-align: right;
            color: rgba(255, 255, 255, 0.7);
        }
        .chat-bubble.vendor .bubble-time {
            color: #888;
        }

        /* â”€â”€ Input Area (Footer) â”€â”€ */
        .chat-footer {
            padding: 20px 24px;
            border-top: 1px solid #e0e0e0;
            background: #fff;
            flex-shrink: 0;
        }
        .input-pill {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border: 1.5px solid #e9ecef;
            border-radius: 999px;
            padding: 6px 6px 6px 20px;
            transition: border-color 0.2s, background 0.2s;
        }
        .input-pill:focus-within {
            border-color: #f0c1c8;
            background: #fff;
        }
        .input-pill input {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            color: #2d2d2d;
            outline: none;
        }
        .input-pill input::placeholder {
            color: #adb5bd;
        }
        .btn-send {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ff2a44;
            color: #fff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            flex-shrink: 0;
        }
        .btn-send:hover {
            background: #e01e38;
        }
        .btn-send:active {
            transform: scale(0.95);
        }
        .btn-send svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
            margin-left: -2px; /* slight visual offset for paper airplane */
        }
        .btn-send:disabled {
            background: #ced4da;
            cursor: not-allowed;
            transform: none;
        }

        .chat-ended {
            text-align: center;
            padding: 20px;
            background: #fafafa;
            color: #888;
            border-top: 1px solid #e0e0e0;
            font-weight: 600;
            font-size: 0.95rem;
        }

        /* â”€â”€ Responsive â”€â”€ */
        @media (max-width: 600px) {
            .chat-wrapper { padding: 16px 12px 20px; height: calc(100vh - 60px); }
            .chat-header { padding: 16px; }
            .chat-body { padding: 16px; }
            .chat-footer { padding: 16px; }
            .chat-bubble { max-width: 85%; }
            .vendor-avatar { width: 40px; height: 40px; }
            .vendor-avatar svg { width: 20px; height: 20px; }
        }
    </style>
</head>
<body>
    <?php include('header.php'); ?>

    <div class="chat-wrapper">
        <a href="OrderStatus.php?order_id=<?php echo $orderId; ?>" class="nav-back">&lt; Back to Order Details</a>
        
        <div class="chat-card">
            
            <!-- Header -->
            <div class="chat-header">
                <div class="header-left">
                    <div class="vendor-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/>
                        </svg>
                    </div>
                    <div class="header-info">
                        <h2 class="vendor-name"><?php echo htmlspecialchars($vendorName); ?></h2>
                        <span class="context-text">Order #<?php echo $orderId; ?> â€” <?php echo htmlspecialchars($order['FoodName']); ?></span>
                    </div>
                </div>
                <div class="header-right">
                    <span class="status-pill"><?php echo htmlspecialchars($order['Status']); ?></span>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="chat-body" id="chatMessages">
                <div class="empty-state" id="chatEmpty">No messages yet. Start the conversation!</div>
            </div>

            <!-- Footer (Input) -->
            <?php if ($isActive): ?>
                <div class="chat-footer" id="chatInputArea">
                    <div class="input-pill">
                        <input type="text" id="chatInput" placeholder="Type a message..." autocomplete="off" maxlength="500">
                        <button type="button" id="chatSendBtn" class="btn-send" onclick="sendMessage()" aria-label="Send Message">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="chat-ended">
                    This order is <?php echo htmlspecialchars($order['Status']); ?>. Chat is now closed.
                </div>
            <?php endif; ?>

        </div>
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



