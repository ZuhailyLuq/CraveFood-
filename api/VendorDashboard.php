<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if (!isset($_SESSION['VendorID'])) {
    header("Location: Login.html");
    exit();
}

$vendorId = (int)$_SESSION['VendorID'];
$shopName = (string)($_SESSION['ShopName'] ?? '');

// Schema is fixed in Supabase &mdash; all columns exist
$hasStatus = $hasDietaryTag = $hasCategory = $hasDescription = $hasPrice = true;

$items = db_fetch_all($pdo,
    'SELECT "FoodID", "FoodName", "Price", "Description", "DietaryTag", "Category", "Status" FROM menu_food WHERE "VendorID" = ? ORDER BY "FoodID" DESC',
    [$vendorId]
);

// Fetch unread admin notifications
$adminNotifs = db_fetch_all($pdo,
    'SELECT "NotificationID", "Message", "CreatedAt" FROM admin_notifications WHERE "VendorID" = ? AND "IsRead" = FALSE ORDER BY "CreatedAt" DESC',
    [$vendorId]
);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Vendor Dashboard - CraveFood</title>
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <style>
        .admin-notif-banner {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #fd7e14;
            border-radius: 8px;
            padding: 14px 18px;
            margin: 12px 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .admin-notif-banner .notif-icon {
            font-size: 1.4rem;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .admin-notif-banner .notif-body {
            flex: 1;
        }
        .admin-notif-banner .notif-msg {
            font-size: 0.93rem;
            color: #856404;
            font-weight: 500;
            line-height: 1.4;
        }
        .admin-notif-banner .notif-time {
            font-size: 0.78rem;
            color: #a07800;
            margin-top: 4px;
        }
        .admin-notif-banner .notif-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        .admin-notif-banner .btn-update-profile {
            background: #fd7e14;
            color: #fff;
            border: none;
            padding: 5px 14px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .admin-notif-banner .btn-update-profile:hover {
            background: #e8690b;
        }
        .admin-notif-banner .btn-dismiss {
            background: transparent;
            border: 1px solid #c9a800;
            color: #856404;
            padding: 5px 14px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
        }
        .admin-notif-banner .btn-dismiss:hover {
            background: #ffeeba;
        }
    </style>
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

    <?php if (count($adminNotifs) > 0): ?>
        <?php foreach ($adminNotifs as $notif): ?>
            <div class="admin-notif-banner" id="notif-<?php echo (int)$notif['NotificationID']; ?>">
                <span class="notif-icon">&#128276;</span>
                <div class="notif-body">
                    <div class="notif-msg"><?php echo htmlspecialchars($notif['Message']); ?></div>
                    <div class="notif-time">Received: <?php echo date('d M Y, h:i A', strtotime($notif['CreatedAt'])); ?></div>
                    <div class="notif-actions">
                        <a href="VendorProfileEdit.php" class="btn-update-profile">Update Store Profile</a>
                        <button type="button" class="btn-dismiss" onclick="dismissNotif(<?php echo (int)$notif['NotificationID']; ?>)">Dismiss</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="home-hero-wrap" style="margin-top:20px; align-items:flex-start; flex-direction:column;"><div class="home-hero-content" style="width:100%;">
        <h1 class="hero-title" style="font-size:2rem;">Vendor <span>Dashboard</span></h1>
        <p class="hero-subtitle" style="margin-bottom:24px;">
            Manage your food menu.
            <?php if ($shopName !== '') echo 'Shop: <strong>' . htmlspecialchars($shopName) . '</strong>'; ?>
        </p>

        <?php if (count($items) === 0): ?>
            <p class="hero-subtitle" style="margin-bottom:24px;">You don't have any food items yet.</p>
            <a href="VendorFoodCreate.php" class="btn-advance-centered">Add your first food</a>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="vendor-status-table" style="width:100%; background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.04); overflow:hidden;">
                    <thead>
                        <tr>
                            <th>Food</th>
                            <th>Price</th>
                            <?php if ($hasDietaryTag): ?><th>Dietary Tag</th><?php endif; ?>
                            <?php if ($hasCategory): ?><th>Category</th><?php endif; ?>
                            <?php if ($hasStatus): ?><th>Status</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars((string)$item['FoodName']); ?>
                                <?php if ($hasDescription && !empty($item['Description'])): ?>
                                    <div class="vendor-desc"><?php echo htmlspecialchars((string)$item['Description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasPrice): ?>
                                    <?php echo 'RM ' . number_format((float)$item['Price'], 2); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <?php if ($hasDietaryTag): ?>
                                <td><?php echo htmlspecialchars((string)($item['DietaryTag'] ?? '')); ?></td>
                            <?php endif; ?>
                            <?php if ($hasCategory): ?>
                                <td><?php echo htmlspecialchars((string)($item['Category'] ?? '')); ?></td>
                            <?php endif; ?>
                            <?php if ($hasStatus): ?>
                                <?php
                                    $st = (string)($item['Status'] ?? '');
                                    $bClass = 'badge-neutral';
                                    if (strtolower($st) === 'available') $bClass = 'badge-success';
                                    elseif (strtolower($st) === 'unavailable') $bClass = 'badge-warning';
                                ?>
                                <td><span class="badge-pill <?php echo $bClass; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                            <?php endif; ?>
                            <td style="display:flex; gap:10px; align-items:center;">
                                <a class="btn-advance-ghost" style="padding:6px 12px; font-size:0.8rem;" href="VendorFoodEdit.php?food_id=<?php echo (int)$item['FoodID']; ?>">Edit</a>
                                <form action="VendorFoodDelete.php" method="POST" class="inline-delete-form" onsubmit="return confirm('Delete this food item?');">
                                    <input type="hidden" name="FoodID" value="<?php echo (int)$item['FoodID']; ?>">
                                    <button type="submit" name="Action_Delete" value="Delete" class="btn-outline btn-outline-danger">Delete</button>
                                </form>
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





