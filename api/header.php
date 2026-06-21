<?php
require_once __DIR__ . '/session.php';
$_cartBadgeCount = 0;
if (isset($_SESSION['cart']['items'])) {
    foreach ($_SESSION['cart']['items'] as $_bci) {
        $_cartBadgeCount += $_bci['Quantity'];
    }
}
?>
<div class="navbar">
    <div class="logo" onclick="window.location.href='Homepage.php'">
        <h2><span>crave</span>food</h2>
    </div>
    <div class="nav-links">
        <a href="Cart.php" class="nav-cart-link">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
            Cart<span class="nav-cart-badge<?= $_cartBadgeCount > 0 ? '' : ' hidden' ?>" id="navCartBadge"><?= $_cartBadgeCount ?></span>
        </a>
        <a href="OrderHistory.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            History
        </a>
        <a href="Achievements.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.45 1-1 1H4v2h16v-2h-5c-.55 0-1-.45-1-1v-2.34"></path><path d="M12 2a5 5 0 0 1 5 5v5a5 5 0 0 1-10 0V7a5 5 0 0 1 5-5z"></path></svg>
            Achievements
        </a>
        <?php if (isset($_SESSION['UserID'])): ?>
            <a href="Logout.php">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Logout
            </a>
        <?php else: ?>
            <a href="Login.html">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                Login
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    /* â”€â”€ Navbar active link highlighting â”€â”€ */
    var path = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
    if (path === '' || path === 'index.php') path = 'homepage.php';
    
    document.querySelectorAll('.nav-links a').forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === path || 
            (path.startsWith('advancesearch') && href === 'homepage.php') ||
            (path.startsWith('orderstatus') && href === 'orderhistory.php') ||
            (path.startsWith('orderoption') && href === 'cart.php') ||
            (path.startsWith('chat') && href === 'orderhistory.php')
        ) {
            link.classList.add('active');
        }
    });
});
</script>
