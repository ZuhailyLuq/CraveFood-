<?php
$files = [
    'api/AdminDashboard.php',
    'api/AdminAchievements.php'
];

$oldNavbarRegex = '/<div class="navbar">.*?<div class="nav-links">.*?<\/div>\s*<\/div>/is';

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Replace old navbar robustly
    $content = preg_replace($oldNavbarRegex, "<?php include('admin_header.php'); ?>", $content);
    
    if ($file === 'api/AdminDashboard.php') {
        // Redesign layout wrapper
        $content = str_replace('<div class="admin-wrap">', '<div class="home-hero-wrap" style="align-items:flex-start; flex-direction:column; padding-bottom:60px;"><div class="home-hero-content" style="width:100%;">', $content);
        $content = str_replace('<div class="welcome-header">', '<div class="welcome-header" style="margin-bottom:24px;">', $content);
        $content = str_replace('<h1>&#128075; Welcome', '<h1 class="hero-title" style="font-size:2rem;">&#128075; Welcome', $content);
        $content = str_replace('<p>Admin Dashboard', '<p class="hero-subtitle">Admin Dashboard', $content);
        
        // Buttons and panels
        $content = str_replace('<div class="vendor-panel">', '<div class="vendor-panel" style="background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.04); overflow:hidden;">', $content);
        $content = str_replace('<button type="button" class="btn-notify', '<button type="button" class="btn-advance-ghost" style="padding:6px 12px; font-size:0.8rem;"', $content);
        $content = str_replace('<button type="button" id="btnNotifyAll" class="btn-notify-all"', '<button type="button" id="btnNotifyAll" class="btn-advance-centered"', $content);
        $content = str_replace('class="btn-notify-all"', 'class="btn-advance-centered"', $content); // In case it misses one
    }
    
    if ($file === 'api/AdminAchievements.php') {
        $content = str_replace('<div class="admin-wrap">', '<div class="home-hero-wrap" style="align-items:flex-start; flex-direction:column;"><div class="home-hero-content" style="width:100%;">', $content);
        $content = str_replace('<h1 class="admin-title">', '<h1 class="hero-title" style="font-size:2rem;">', $content);
        $content = str_replace('<p class="admin-subtitle">', '<p class="hero-subtitle" style="margin-bottom:24px;">', $content);
        
        $content = str_replace('<div class="panel">', '<div class="panel" style="background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.04); border:none;">', $content);
        $content = str_replace('<button type="submit" class="btn-primary">', '<button type="submit" class="btn-advance-centered">', $content);
        $content = str_replace('<button type="button" class="btn-secondary"', '<button type="button" class="btn-advance-ghost"', $content);
        $content = str_replace('<button type="submit" name="action" value="delete" class="btn-danger"', '<button type="submit" name="action" value="delete" class="btn-advance-ghost" style="border-color:#c1121f; color:#c1121f;"', $content);
    }
    
    // Close the hero content properly
    if (strpos($content, '<!-- ── Welcome Header ── -->') !== false || strpos($content, '<div class="admin-wrap">') !== false) {
        $content = str_replace('</body>', '</div></div></body>', $content); // Closes home-hero-wrap and home-hero-content
    } else {
        // Just for safety if it misses
        $content = str_replace('</body>', '</div></div></body>', $content);
    }
    
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
?>
