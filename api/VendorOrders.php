<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if (!isset($_SESSION['VendorID'])) {
    header("Location: Login.html");
    exit();
}

$vendorId = (int)$_SESSION['VendorID'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['Action_UpdateStatus'])) {
    $orderIdToUpdate = (int)$_POST['OrderID'];
    $newStatus       = trim($_POST['NewStatus'] ?? '');

    $check = db_fetch_one($pdo,
        'SELECT o."OrderID" FROM orders o INNER JOIN menu_food mf ON o."FoodID" = mf."FoodID" WHERE o."OrderID" = ? AND mf."VendorID" = ?',
        [$orderIdToUpdate, $vendorId]
    );

    if ($check && $newStatus !== '') {
        $cancelReason = ($newStatus === 'Cancelled') ? trim($_POST['CancelReason'] ?? '') : null;
        $rows = db_execute($pdo,
            'UPDATE orders SET "Status" = ?, "CancelReason" = ? WHERE "OrderID" = ?',
            [$newStatus, $cancelReason, $orderIdToUpdate]
        );
        if ($rows > 0) {
            header("Location: VendorOrders.php?type=success&msg=" . urlencode("Order #$orderIdToUpdate status updated to $newStatus.")); exit();
        }
        header("Location: VendorOrders.php?type=error&msg=" . urlencode("Failed to update status.")); exit();
    }
    header("Location: VendorOrders.php?type=error&msg=" . urlencode("Invalid order.")); exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['Action_Cancel'])) {
    $orderIdToCancel = (int)$_POST['OrderID'];
    $cancelReason    = trim($_POST['CancelReason'] ?? '');

    $check = db_fetch_one($pdo,
        'SELECT o."OrderID" FROM orders o INNER JOIN menu_food mf ON o."FoodID" = mf."FoodID" WHERE o."OrderID" = ? AND mf."VendorID" = ?',
        [$orderIdToCancel, $vendorId]
    );

    if ($check && $cancelReason !== '') {
        $rows = db_execute($pdo,
            'UPDATE orders SET "Status" = \'Cancelled\', "CancelReason" = ? WHERE "OrderID" = ?',
            [$cancelReason, $orderIdToCancel]
        );
        if ($rows > 0) {
            header("Location: VendorOrders.php?type=success&msg=" . urlencode("Order #$orderIdToCancel cancelled.")); exit();
        }
    }
    header("Location: VendorOrders.php?type=error&msg=" . urlencode("Failed to cancel order.")); exit();
}

$orders = db_fetch_all($pdo,
    'SELECT o."OrderID", o."OrderType", o."Status", o."TotalAmount", o."PickupTime", o."CancelReason", mf."FoodName"
     FROM orders o
     INNER JOIN menu_food mf ON o."FoodID" = mf."FoodID"
     WHERE mf."VendorID" = ?
     ORDER BY o."OrderID" DESC',
    [$vendorId]
);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vendor Orders - CraveFood</title>
    <link rel="stylesheet" href="style.css?v=20260622-1">
    <meta http-equiv="refresh" content="30">
</head>
<body>
    <?php include('vendor_header.php'); ?>

    <?php
        $noticeMsg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
        $noticeType = isset($_GET['type']) ? $_GET['type'] : 'error';
    ?>

    <?php if ($noticeMsg !== ''): ?>
        <div class="notice show <?php echo ($noticeType === 'success') ? 'notice-success' : 'notice-error'; ?>">
            <?php echo htmlspecialchars($noticeMsg); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-box" style="max-width: 1000px;">
        <h2>Vendor Orders</h2>
        <p class="hero-subtitle" style="margin-bottom:24px;">Manage incoming orders. This page refreshes every 30 seconds.</p>

        <?php if (count($orders) === 0): ?>
            <p class="hero-subtitle" style="margin-bottom:24px;">No orders yet.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="vendor-status-table" style="width:100%; background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.04); overflow:hidden;">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Food</th>
                            <th>Order Type</th>
                            <th>Pickup Time</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo (int)$order['OrderID']; ?></td>
                            <td><?php echo htmlspecialchars((string)$order['FoodName']); ?></td>
                            <td><?php echo htmlspecialchars((string)$order['OrderType']); ?></td>
                            <td><?php echo !empty($order['PickupTime']) ? htmlspecialchars((string)$order['PickupTime']) : '-'; ?></td>
                            <td>RM <?php echo number_format((float)$order['TotalAmount'], 2); ?></td>
                            <td>
                                <?php
                                    $st = (string)$order['Status'];
                                    $bClass = 'badge-neutral';
                                    if ($st === 'Completed') $bClass = 'badge-success';
                                    elseif ($st === 'Pending') $bClass = 'badge-warning';
                                    elseif ($st === 'Preparing' || $st === 'Cooking' || $st === 'Ready') $bClass = 'badge-info';
                                    elseif ($st === 'Cancelled') $bClass = 'badge-danger';
                                ?>
                                <span class="badge-pill <?php echo $bClass; ?>"><?php echo htmlspecialchars($st); ?></span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <form action="VendorOrders.php" method="POST" style="margin: 0; display: flex; gap: 6px;">
                                        <input type="hidden" name="OrderID" value="<?php echo (int)$order['OrderID']; ?>">
                                        <select name="NewStatus" style="margin-bottom: 0; padding: 6px; width: 110px; font-size: 14px; border: 1px solid #e9ecef; border-radius: 9px; outline:none; background:#fff; color:#444;">
                                            <option value="Pending" <?php if($order['Status']==='Pending') echo 'selected'; ?>>Pending</option>
                                            <option value="Preparing" <?php if($order['Status']==='Preparing') echo 'selected'; ?>>Preparing</option>
                                            <option value="Cooking" <?php if($order['Status']==='Cooking') echo 'selected'; ?>>Cooking</option>
                                            <option value="Ready" <?php if($order['Status']==='Ready') echo 'selected'; ?>>Ready</option>
                                            <option value="Completed" <?php if($order['Status']==='Completed') echo 'selected'; ?>>Completed</option>
                                            <?php if($order['Status']==='Cancelled'): ?>
                                                <option value="Cancelled" selected>Cancelled</option>
                                            <?php endif; ?>
                                        </select>
                                        <button type="submit" name="Action_UpdateStatus" class="btn-outline btn-outline-primary" style="padding: 6px 12px; font-size:0.8rem;">Update</button>
                                    </form>
                                    <?php if ($order['Status'] !== 'Cancelled' && $order['Status'] !== 'Completed'): ?>
                                        <button type="button" class="btn-outline btn-outline-danger" style="padding: 6px 12px; margin: 0; font-size:0.8rem;" onclick="cancelOrder(<?php echo (int)$order['OrderID']; ?>)">Cancel</button>
                                        <a href="VendorChat.php?order_id=<?php echo (int)$order['OrderID']; ?>" class="btn-text-primary" style="text-decoration: none;">&#128172; Chat</a>
                                    <?php endif; ?>
                                </div>
                                <?php if ($order['Status'] === 'Cancelled' && !empty($order['CancelReason'])): ?>
                                    <div style="font-size: 12px; color: #c1121f; margin-top: 6px; max-width: 200px;">Reason: <?php echo htmlspecialchars($order['CancelReason']); ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
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




