<?php
// VendorDashboard.php
$vd = file_get_contents('api/VendorDashboard.php');
$vd = str_replace('<link rel="stylesheet" href="../style.css?v=<?= time() ?>">', '<link rel="stylesheet" href="../style.css?v=<?= time() ?>">', $vd); // Safe measure
// Force ?v=<?= time() ?> if not present
$vd = preg_replace('/<link rel="stylesheet" href="\.\.\/style\.css\?[^>]+>/', '<link rel="stylesheet" href="../style.css?v=<?= time() ?>">', $vd);

// Ensure Edit button has proper classes
$vd = preg_replace('/<a class="btn-advance-ghost"/i', '<a class="btn-outline btn-outline-primary"', $vd);

file_put_contents('api/VendorDashboard.php', $vd);


// VendorOrders.php
$vo = file_get_contents('api/VendorOrders.php');
$vo = preg_replace('/<link rel="stylesheet" href="\.\.\/style\.css\?[^>]+>/', '<link rel="stylesheet" href="../style.css?v=<?= time() ?>">', $vo);
file_put_contents('api/VendorOrders.php', $vo);

echo "Cache patch complete.\\n";
?>
