<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.html");
    exit();
}

$userId  = (int)$_SESSION['UserID'];
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Schema fixed in Supabase &mdash; no column sniffing needed
if ($orderId <= 0) {
    $latest = db_fetch_one($pdo,
        'SELECT "OrderID" FROM orders WHERE "UserID" = ? ORDER BY "OrderID" DESC LIMIT 1',
        [$userId]
    );
    if ($latest) $orderId = (int)$latest['OrderID'];
}

$order = null;
if ($orderId > 0) {
    $order = db_fetch_one($pdo,
        'SELECT o."OrderID", o."OrderType", o."PickupTime", o."TotalAmount", o."Status",
                NULL AS ReservationMode, NULL AS SeatCount,
                o."CancelReason", o."Quantity",
                mf."FoodName", v."ShopName"
         FROM orders o
         INNER JOIN menu_food mf ON mf."FoodID" = o."FoodID"
         LEFT JOIN vendor v ON v."VendorID" = mf."VendorID"
         WHERE o."OrderID" = ? AND o."UserID" = ?
         LIMIT 1',
        [$orderId, $userId]
    );
}

$status      = $order ? strtolower($order['Status']) : 'unknown';
$stepIndex   = 1;
$isCancelled = false;

if ($status === 'cancelled') {
    $isCancelled = true;
} elseif (in_array($status, ['completed', 'finished', 'ready'])) {
    $stepIndex = 3;
} elseif (in_array($status, ['processing', 'preparing', 'accepted', 'cooking'])) {
    $stepIndex = 2;
} else {
    $stepIndex = 1;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Tracking - CraveFood</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <meta http-equiv="refresh" content="20">
    
    <style>
        /* â”€â”€ Reset & base â”€â”€ */
        *, body { font-family: 'Inter', 'Segoe UI', sans-serif; box-sizing: border-box; }
        body { background: #ffffff; margin: 0; padding: 0; color: #1e1e1e; }

        .tracking-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 60px); /* Fill space under navbar */
            padding: 40px 20px;
        }

        /* â”€â”€ Main Order Card â”€â”€ */
        .tracking-card {
            background: #fff;
            width: 100%;
            max-width: 580px;
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(193,18,31,0.06), 0 4px 12px rgba(0,0,0,0.04);
            padding: 32px;
            border: 1px solid #e0e0e0;
            position: relative;
            overflow: hidden;
        }
        
        /* Optional top accent border */
        .tracking-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff2a44, #ff6b6b);
        }

        /* â”€â”€ Header â”€â”€ */
        .tracking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            gap: 16px;
            flex-wrap: wrap;
        }
        .header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .vendor-name {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 800;
            color: #2d2d2d;
            letter-spacing: -0.3px;
        }
        .order-id {
            font-size: 0.95rem;
            font-weight: 600;
            color: #888;
        }

        .header-right {
            text-align: right;
        }
        .eta-box {
            background: #f9fafb;
            border: 1.5px solid #e0e0e0;
            padding: 8px 16px;
            border-radius: 12px;
            display: inline-flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .eta-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #ff2a44;
        }
        .eta-time {
            font-size: 1.25rem;
            font-weight: 800;
            color: #ff2a44;
            margin-top: 2px;
        }

        /* â”€â”€ Cancelled State â”€â”€ */
        .cancelled-banner {
            background: #f8d7da;
            color: #721c24;
            padding: 16px;
            border-radius: 12px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 32px;
            border: 1px solid #f5c6cb;
        }

        /* â”€â”€ Progress Tracker â”€â”€ */
        .progress-tracker {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 40px;
            padding: 0 10px;
        }
        
        /* The background line */
        .progress-line-bg {
            position: absolute;
            top: 20px; /* Center of the 40px circles */
            left: 30px;
            right: 30px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            z-index: 1;
        }
        /* The active line fill */
        .progress-line-fill {
            position: absolute;
            top: 20px;
            left: 30px;
            height: 4px;
            background: #ff2a44;
            border-radius: 2px;
            z-index: 2;
            transition: width 0.5s ease;
        }
        
        /* The Steps */
        .step {
            position: relative;
            z-index: 3;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            width: 80px;
        }
        .step-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #fff;
            border: 4px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .step-circle svg {
            width: 20px;
            height: 20px;
            fill: #aaa;
        }
        .step-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #aaa;
            text-align: center;
            transition: color 0.3s;
        }

        /* Active/Completed Step Styles */
        .step.active .step-circle,
        .step.completed .step-circle {
            border-color: #ff2a44;
            background: #ff2a44;
        }
        .step.active .step-circle svg,
        .step.completed .step-circle svg {
            fill: #fff;
        }
        .step.active .step-label {
            color: #ff2a44;
            font-weight: 700;
        }
        .step.completed .step-label {
            color: #2d2d2d;
        }

        /* â”€â”€ Order Summary â”€â”€ */
        .summary-box {
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 16px;
        }
        .summary-row:last-of-type {
            margin-bottom: 0;
        }
        .item-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2d2d2d;
            line-height: 1.4;
        }
        .item-price {
            font-size: 0.95rem;
            font-weight: 600;
            color: #666;
            white-space: nowrap;
        }
        
        .summary-divider {
            border: none;
            border-top: 1px dashed #ddd;
            margin: 16px 0;
        }
        
        .summary-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-type-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .total-price {
            font-size: 1.25rem;
            font-weight: 800;
            color: #2d2d2d;
        }

        /* Extra Details */
        .extra-details {
            margin-top: 12px;
            font-size: 0.85rem;
            color: #666;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        /* â”€â”€ Action Footer â”€â”€ */
        .action-footer {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn-chat {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            background: #ff2a44;
            color: #fff;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(193,18,31,0.2);
        }
        .btn-chat:hover {
            background: #a00f1a;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(193,18,31,0.25);
        }
        .btn-chat svg {
            width: 22px;
            height: 22px;
            fill: currentColor;
        }
        
        .live-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #888;
            margin: 0;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }

        /* Responsive */
        @media (max-width: 500px) {
            .tracking-card { padding: 24px; border-radius: 16px; border: none; border-top: 4px solid #ff2a44; }
            .tracking-card::before { display: none; }
            .header-right { width: 100%; text-align: left; }
            .eta-box { width: 100%; align-items: flex-start; }
            .step-label { font-size: 0.75rem; }
            .step-circle { width: 36px; height: 36px; }
            .step-circle svg { width: 16px; height: 16px; }
            .progress-line-bg, .progress-line-fill { top: 16px; }
        }
    </style>
</head>
<body>
    <?php include('header.php'); ?>

    <div class="tracking-wrap">
        <?php if ($order): ?>
            <div class="tracking-card">
                
                <!-- Header -->
                <div class="tracking-header">
                    <div class="header-left">
                        <h2 class="vendor-name"><?php echo htmlspecialchars($order['ShopName'] ?? 'Vendor'); ?></h2>
                        <span class="order-id">Order #<?php echo (int)$order['OrderID']; ?></span>
                    </div>
                    <?php if (!$isCancelled && $stepIndex < 3): ?>
                        <div class="header-right">
                            <div class="eta-box">
                                <span class="eta-label">Estimated Time</span>
                                <span class="eta-time">15 mins</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Progress Tracker -->
                <?php if ($isCancelled): ?>
                    <div class="cancelled-banner">
                        Order Cancelled: <?php echo htmlspecialchars($order['CancelReason'] ?? 'Vendor rejected the order.'); ?>
                    </div>
                <?php else: ?>
                    <div class="progress-tracker">
                        <!-- Calculate fill width based on step: 1=0%, 2=50%, 3=100% -->
                        <?php 
                            $fillWidth = 0;
                            if ($stepIndex === 2) $fillWidth = 50;
                            if ($stepIndex === 3) $fillWidth = 100;
                        ?>
                        <div class="progress-line-bg"></div>
                        <div class="progress-line-fill" style="width: <?php echo $fillWidth; ?>%;"></div>
                        
                        <!-- Step 1: Pending -->
                        <div class="step <?php echo $stepIndex >= 1 ? ($stepIndex == 1 ? 'active' : 'completed') : ''; ?>">
                            <div class="step-circle">
                                <!-- Document icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                </svg>
                            </div>
                            <span class="step-label">Pending</span>
                        </div>

                        <!-- Step 2: Preparing -->
                        <div class="step <?php echo $stepIndex >= 2 ? ($stepIndex == 2 ? 'active' : 'completed') : ''; ?>">
                            <div class="step-circle">
                                <!-- Fire/Cooking icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M19.36 10.04C18.67 6.59 15.64 4 12 4 8.13 4 5 7.13 5 11c0 3.87 3.13 7 7 7s7-3.13 7-7c0-.28-.02-.55-.06-.82l.42-7.14zM12 16c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm1-9.97C13 4.9 13.9 4 15 4s2 .9 2 2.03V10h-4V6.03z"/>
                                </svg>
                            </div>
                            <span class="step-label">Preparing</span>
                        </div>

                        <!-- Step 3: Ready -->
                        <div class="step <?php echo $stepIndex >= 3 ? 'completed active' : ''; ?>">
                            <div class="step-circle">
                                <!-- Checkmark icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                            </div>
                            <span class="step-label">Ready</span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Order Summary -->
                <div class="summary-box">
                    <div class="summary-row">
                        <span class="item-name"><?php echo (int)($order['Quantity'] ?? 1); ?>x <?php echo htmlspecialchars($order['FoodName']); ?></span>
                        <span class="item-price">RM <?php echo number_format((float)$order['TotalAmount'], 2); ?></span>
                    </div>
                    
                    <?php if (!empty($order['ReservationMode']) || !empty($order['SeatCount']) || !empty($order['PickupTime'])): ?>
                        <div class="extra-details">
                            <?php if (!empty($order['ReservationMode'])): ?>
                                <span>Reservation: <?php echo htmlspecialchars($order['ReservationMode']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($order['SeatCount'])): ?>
                                <span>Seats: <?php echo (int)$order['SeatCount']; ?></span>
                            <?php endif; ?>
                            <?php if (!empty($order['PickupTime'])): ?>
                                <span>Time: <?php echo htmlspecialchars($order['PickupTime']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <hr class="summary-divider">
                    
                    <div class="summary-footer-row">
                        <span class="order-type-label"><?php echo htmlspecialchars($order['OrderType']); ?></span>
                        <span class="total-price">RM <?php echo number_format((float)$order['TotalAmount'], 2); ?></span>
                    </div>
                </div>

                <!-- Action Footer -->
                <?php if (!in_array($order['Status'], ['Finished', 'Completed', 'Cancelled'])): ?>
                    <div class="action-footer">
                        <a href="Chat.php?order_id=<?php echo (int)$order['OrderID']; ?>" class="btn-chat">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/>
                            </svg>
                            Chat with Vendor
                        </a>
                        <p class="live-note">
                            <span class="live-dot"></span>
                            Live updates active.
                        </p>
                    </div>
                <?php endif; ?>
                
            </div>
        <?php else: ?>
            <div class="tracking-card" style="text-align:center;">
                <h2 style="color:#2d2d2d; margin-top:0;">No Active Order</h2>
                <p style="color:#888;">We couldn't find an order for this account.</p>
                <a href="Homepage.php" style="display:inline-block; padding:12px 24px; background:#ff2a44; color:#fff; text-decoration:none; border-radius:10px; font-weight:700; margin-top:16px;">Browse Food</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Intentionally removed the global bottom notification pill to prevent duplication on this specific page -->

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




