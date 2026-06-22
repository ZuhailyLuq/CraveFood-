<?php
$files = [
    'api/VendorDashboard.php',
    'api/VendorOrders.php',
    'api/VendorFoodCreate.php',
    'api/VendorFoodEdit.php',
    'api/VendorProfileEdit.php'
];

$oldNavbarRegex = '/<div class="navbar">.*?<div class="nav-links">.*?<\/div>\s*<\/div>/is';

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Replace old navbar robustly
    $content = preg_replace($oldNavbarRegex, "<?php include('vendor_header.php'); ?>", $content);
    
    if ($file === 'api/VendorDashboard.php') {
        $content = str_replace('<div class="dashboard-box">', '<div class="home-hero-wrap" style="margin-top:20px; align-items:flex-start; flex-direction:column;"><div class="home-hero-content" style="width:100%;">', $content);
        $content = str_replace('<h2>Vendor Dashboard</h2>', '<h1 class="hero-title" style="font-size:2rem;">Vendor <span>Dashboard</span></h1>', $content);
        $content = str_replace('<p class="settings-note">', '<p class="hero-subtitle" style="margin-bottom:24px;">', $content);
        
        $content = str_replace('<table class="vendor-table">', '<table class="vendor-status-table" style="width:100%; background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.04); overflow:hidden;">', $content);
        $content = str_replace('<td class="vendor-actions">', '<td style="display:flex; gap:10px; align-items:center;">', $content);
        $content = str_replace('<a class="btn-secondary"', '<a class="btn-advance-ghost" style="padding:6px 12px; font-size:0.8rem;"', $content);
        $content = str_replace('<button type="submit" name="Action_Delete" value="Delete" class="btn-danger">', '<button type="submit" name="Action_Delete" value="Delete" class="btn-advance-ghost" style="padding:6px 12px; font-size:0.8rem; border-color:#c1121f; color:#c1121f;">', $content);
    }
    
    if ($file === 'api/VendorOrders.php') {
        $content = str_replace('<div class="dashboard-box">', '<div class="home-hero-wrap" style="margin-top:20px; align-items:flex-start; flex-direction:column;"><div class="home-hero-content" style="width:100%;">', $content);
        $content = str_replace('<h2>Store Orders</h2>', '<h1 class="hero-title" style="font-size:2rem;">Store <span>Orders</span></h1>', $content);
        $content = str_replace('<p class="settings-note">', '<p class="hero-subtitle" style="margin-bottom:24px;">', $content);
        $content = str_replace('<div class="order-card">', '<div class="fi-card" style="flex-direction:column; align-items:stretch; width:100%;">', $content);
    }

    if ($file === 'api/VendorFoodCreate.php' || $file === 'api/VendorFoodEdit.php' || $file === 'api/VendorProfileEdit.php') {
        $content = str_replace('<div class="dashboard-box" style="max-width: 600px;">', '<div class="auth-container" style="max-width:600px; margin:40px auto;"><div class="auth-box">', $content);
        $content = str_replace('<h2>', '<h1 class="hero-title" style="text-align:center;">', $content);
        $content = str_replace('</h2>', '</h1>', $content);
        $content = str_replace('<form method="POST"', '<form class="auth-form" method="POST"', $content);
        
        // Add closing div for auth-box if not present
        if (strpos($content, '</div></div>') === false) {
            $content = str_replace('</form>', '</form></div>', $content);
        }
    }
    
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
?>
