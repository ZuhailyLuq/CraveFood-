<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.html");
    exit();
}

$userId = (int)$_SESSION['UserID'];

// Schema is fixed &mdash; OrderDate column exists in Supabase as "OrderDate"
$orders = db_fetch_all($pdo,
    'SELECT o."OrderID", o."OrderType", o."PickupTime", o."TotalAmount", o."Status",
            o."CancelReason", o."OrderDate" AS CreatedDate, o."Quantity",
            NULL AS ReservationMode, NULL AS SeatCount,
            mf."FoodName", mf."VendorID", v."ShopName"
     FROM orders o
     INNER JOIN menu_food mf ON mf."FoodID" = o."FoodID"
     LEFT JOIN vendor v ON v."VendorID" = mf."VendorID"
     WHERE o."UserID" = ?
     ORDER BY o."OrderID" DESC',
    [$userId]
);

// Enrich orders with chat count and display date
foreach ($orders as &$row) {
    $chatRow = db_fetch_one($pdo,
        'SELECT COUNT(*) AS cnt FROM chat_messages WHERE "OrderID" = ?',
        [(int)$row['OrderID']]
    );
    $row['ChatCount'] = (int)($chatRow['cnt'] ?? 0);

    $displayDate = '';
    if (!empty($row['CreatedDate'])) {
        $displayDate = date('d M Y, h:i A', strtotime($row['CreatedDate']));
    } elseif (!empty($row['PickupTime'])) {
        $displayDate = date('d M Y, h:i A', strtotime($row['PickupTime']));
    }
    $row['DisplayDate'] = $displayDate;
}
unset($row);

$activeOrder = db_fetch_one($pdo,
    'SELECT "OrderID", "Status" FROM orders WHERE "UserID" = ? AND "Status" NOT IN (\'Finished\', \'Completed\', \'Cancelled\') ORDER BY "OrderID" DESC LIMIT 1',
    [$userId]
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - CraveFood</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <style>
        /* â”€â”€ Reset & base â”€â”€ */
        *, body { font-family: 'Inter', 'Segoe UI', sans-serif; box-sizing: border-box; }
        body { background: #ffffff; margin: 0; padding: 0; color: #1e1e1e; }

        .history-wrap {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px 100px; /* bottom pad for pending pill */
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 40px;
        }
        .page-title-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .page-title-wrap svg {
            width: 32px;
            height: 32px;
            fill: #ff2a44;
        }
        .page-title {
            color: #ff2a44;
            font-size: 2.2rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .page-subtitle {
            color: #666;
            font-size: 1rem;
            margin: 0;
        }

        /* â”€â”€ Accordion List â”€â”€ */
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .history-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            overflow: hidden;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .history-card:hover {
            box-shadow: 0 8px 24px rgba(193,18,31,0.08);
            border-color: #ffd8de;
        }
        
        /* Always-visible Header Row */
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            cursor: pointer;
            user-select: none;
            gap: 16px;
            transition: background 0.2s;
        }
        .card-header:hover {
            background: #fcfcfc;
        }
        .card-header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }
        .order-summary {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .order-id {
            color: #ff2a44;
            font-weight: 800;
            font-size: 1.1rem;
        }
        .order-name {
            font-weight: 600;
            color: #2d2d2d;
            font-size: 1.05rem;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .order-date {
            color: #888;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .card-header-right {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
        }
        .order-price {
            font-weight: 800;
            color: #2d2d2d;
            font-size: 1.1rem;
        }
        
        /* Status Pills */
        .status-pill {
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        /* Pending (Warm Yellow/Orange) */
        .status-pill.pending { background: #fff3cd; color: #d39e00; border: 1px solid #ffeeba; }
        /* Completed (Forest Green) */
        .status-pill.completed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        /* Cancelled (Muted Grey) */
        .status-pill.cancelled { background: #e2e3e5; color: #6c757d; border: 1px solid #d6d8db; }

        .chevron {
            color: #ff2a44;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .history-card.open .chevron {
            transform: rotate(180deg);
        }

        /* Expanded Details */
        .card-details {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .history-card.open .card-details {
            grid-template-rows: 1fr;
        }
        .details-inner {
            overflow: hidden;
            padding: 0 24px;
        }
        .history-card.open .details-inner {
            padding-bottom: 24px; /* padding added when open to allow clean animation */
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            padding-top: 16px;
            border-top: 1px solid #e0e0e0;
            margin-bottom: 24px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .detail-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #aaa;
        }
        .detail-value {
            font-size: 0.95rem;
            color: #2d2d2d;
            font-weight: 500;
        }
        .detail-value.red {
            color: #ff2a44;
            font-weight: 600;
        }

        /* Action Buttons */
        .action-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }
        /* Primary Deep Red */
        .btn-primary {
            background: #ff2a44;
            color: #fff;
            border: 1.5px solid #ff2a44;
        }
        .btn-primary:hover {
            background: #a00f1a;
            border-color: #a00f1a;
            box-shadow: 0 4px 12px rgba(193,18,31,0.2);
            transform: translateY(-1px);
        }
        /* Secondary Ghost Button */
        .btn-ghost {
            background: #fff;
            color: #ff2a44;
            border: 1.5px solid #ff2a44;
        }
        .btn-ghost:hover {
            background: #fff5f6;
            box-shadow: 0 4px 12px rgba(193,18,31,0.1);
            transform: translateY(-1px);
        }
        /* Chat Button */
        .btn-chat {
            background: #f8f9fa;
            color: #495057;
            border: 1.5px solid #dee2e6;
        }
        .btn-chat:hover {
            background: #e9ecef;
            border-color: #ced4da;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e0e0e0;
        }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           PENDING ORDER PILL
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .pending-pill {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            background: #ffffff;
            border: 1.5px solid #e63946;
            border-radius: 999px;
            padding: 11px 20px 11px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 32px rgba(193,18,31,0.2), 0 2px 8px rgba(0,0,0,0.08);
            text-decoration: none;
            white-space: nowrap;
            transition: transform 0.2s, box-shadow 0.2s;
            animation: pill-float 3s ease-in-out infinite;
        }
        .pending-pill:hover {
            transform: translateX(-50%) translateY(-3px);
            box-shadow: 0 12px 40px rgba(193,18,31,0.28), 0 4px 12px rgba(0,0,0,0.1);
        }
        .pending-pill-icon  { color: #ff2a44; display: flex; align-items: center; }
        .pending-pill-text  { font-size: 0.9rem; font-weight: 600; color: #1e1e1e; }
        .pending-pill-chevron { color: #ff2a44; display: flex; align-items: center; }
        @keyframes pill-float {
            0%,100% { box-shadow: 0 8px 32px rgba(193,18,31,0.2), 0 2px 8px rgba(0,0,0,0.08); }
            50%      { box-shadow: 0 12px 40px rgba(193,18,31,0.26), 0 4px 14px rgba(0,0,0,0.1); }
        }

        /* Responsive adjustments */
        @media (max-width: 600px) {
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .card-header-right {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <?php include('header.php'); ?>

    <div class="history-wrap">
        
        <div class="header-section">
            <div class="page-title-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
                </svg>
                <h1 class="page-title">Order History</h1>
            </div>
            <p class="page-subtitle">View your past orders, reorder favorites, and manage live deliveries.</p>
        </div>

        <?php if (count($orders) === 0): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="#f0cfd3" style="margin-bottom:12px;">
                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                </svg>
                <h2 style="margin:0 0 8px;color:#333;">No orders yet</h2>
                <p style="margin:0 0 20px;">Your order history will appear here once you place an order.</p>
                <a href="Homepage.php" class="btn-action btn-primary">Browse Food</a>
            </div>
        <?php else: ?>
            <div class="history-list">
                <?php foreach ($orders as $order): ?>
                    <?php
                        // Determine status styles
                        $statusClass = 'pending'; // Default warm yellow/orange
                        $isCompleted = in_array($order['Status'], ['Completed', 'Finished']);
                        $isCancelled = ($order['Status'] === 'Cancelled');
                        
                        if ($isCompleted) {
                            $statusClass = 'completed'; // Forest green
                        } elseif ($isCancelled) {
                            $statusClass = 'cancelled'; // Muted grey
                        }
                    ?>
                    <div class="history-card" id="card-<?php echo $order['OrderID']; ?>">
                        
                        <!-- Header Row (Always Visible) -->
                        <div class="card-header" onclick="toggleCard(<?php echo $order['OrderID']; ?>)">
                            <div class="card-header-left">
                                <div class="order-summary">
                                    <span class="order-id">#<?php echo (int)$order['OrderID']; ?></span>
                                    <span class="order-name"><?php echo (int)($order['Quantity'] ?? 1); ?>x <?php echo htmlspecialchars($order['FoodName']); ?></span>
                                </div>
                                <?php if (!empty($order['DisplayDate'])): ?>
                                    <div class="order-date"><?php echo htmlspecialchars($order['DisplayDate']); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-header-right">
                                <span class="order-price">RM <?php echo number_format((float)$order['TotalAmount'], 2); ?></span>
                                <span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order['Status']); ?></span>
                                <div class="chevron">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Expanded Details -->
                        <div class="card-details">
                            <div class="details-inner">
                                <div class="details-grid">
                                    <?php if (!empty($order['ShopName'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Vendor</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($order['ShopName']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Order Type</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($order['OrderType']); ?></span>
                                    </div>

                                    <?php if (!empty($order['ReservationMode'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Reservation</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($order['ReservationMode']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($order['SeatCount'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Seat Count</span>
                                            <span class="detail-value"><?php echo (int)$order['SeatCount']; ?> seats</span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($isCancelled && !empty($order['CancelReason'])): ?>
                                        <div class="detail-item" style="grid-column: 1 / -1;">
                                            <span class="detail-label">Cancel Reason</span>
                                            <span class="detail-value red"><?php echo htmlspecialchars($order['CancelReason']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="action-group">
                                    <?php if (!$isCompleted && !$isCancelled): ?>
                                        <!-- Smart Action: Pending -> View Live Status -->
                                        <a href="OrderStatus.php?order_id=<?php echo (int)$order['OrderID']; ?>" class="btn-action btn-primary">
                                            View Live Status
                                        </a>
                                    <?php elseif ($isCompleted): ?>
                                        <!-- Smart Action: Completed -> Reorder This -->
                                        <?php if (!empty($order['VendorID'])): ?>
                                            <a href="VendorInfo.php?vendor_id=<?php echo (int)$order['VendorID']; ?>" class="btn-action btn-ghost">
                                                Reorder This
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($order['ChatCount'] > 0): ?>
                                        <a href="Chat.php?order_id=<?php echo (int)$order['OrderID']; ?>" class="btn-action btn-chat">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                                            </svg>
                                            View Chat (<?php echo $order['ChatCount']; ?>)
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- â•â•â•â•â•â• PENDING ORDER PILL â•â•â•â•â•â• -->
    <?php if ($activeOrder): ?>
    <a href="OrderStatus.php?order_id=<?php echo (int)$activeOrder['OrderID']; ?>" class="pending-pill" aria-label="View pending order">
        <span class="pending-pill-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#ff2a44">
                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
            </svg>
        </span>
        <span class="pending-pill-text">Order #<?php echo (int)$activeOrder['OrderID']; ?> is <?php echo htmlspecialchars($activeOrder['Status']); ?></span>
        <span class="pending-pill-chevron">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#ff2a44">
                <path d="M9.29 6.71a.996.996 0 0 0 0 1.41L13.17 12l-3.88 3.88a.996.996 0 1 0 1.41 1.41l4.59-4.59a.996.996 0 0 0 0-1.41L10.7 6.7c-.38-.38-1.02-.38-1.41.01z"/>
            </svg>
        </span>
    </a>
    <?php endif; ?>

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

function toggleCard(orderId) {
    var card = document.getElementById('card-' + orderId);
    if (card) {
        card.classList.toggle('open');
    }
}
</script>
</body>
</html>



