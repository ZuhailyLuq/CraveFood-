<style>
/* Status Badges */
.badge-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    white-space: nowrap;
}
.badge-success { background: #e8f5e9; color: #1e8e3e; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-info    { background: #eef4ff; color: #1a73e8; }
.badge-danger  { background: #fce8e6; color: #c5221f; }
.badge-neutral { background: #f3f3f3; color: #666666; }

/* Outlined Minimalist Buttons */
.btn-outline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #fff;
    border: 1.5px solid #eaeaea;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #444;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}
.btn-outline:hover {
    border-color: #ccc;
    background: #fcfcfc;
}
.btn-outline-primary {
    border-color: rgba(255, 42, 68, 0.4);
    color: #ff2a44;
}
.btn-outline-primary:hover {
    background: #fff0f2;
    border-color: #ff2a44;
    transform: translateY(-1px);
}
.btn-outline-danger {
    border-color: rgba(193, 18, 31, 0.4);
    color: #c1121f;
}
.btn-outline-danger:hover {
    background: #fdf2f2;
    border-color: #c1121f;
    transform: translateY(-1px);
}
.btn-text-primary {
    background: transparent;
    border: none;
    color: #ff2a44;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    padding: 4px 8px;
    transition: color 0.2s;
}
.btn-text-primary:hover {
    color: #cc001b;
    text-decoration: underline;
}
</style>
<div class="navbar">
    <div class="logo" onclick="window.location.href='VendorDashboard.php'">
        <h2><span>crave</span>food</h2>
    </div>
    <div class="nav-links">
        <a href="VendorDashboard.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Dashboard
        </a>
        <a href="VendorOrders.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            Orders
        </a>
        <a href="VendorFoodCreate.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Food
        </a>
        <a href="VendorProfileEdit.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            Store Profile
        </a>
        <a href="Logout.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            Logout
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var path = window.location.pathname.split('/').pop().toLowerCase() || 'vendordashboard.php';
    if (path === '' || path === 'index.php') path = 'vendordashboard.php';
    
    document.querySelectorAll('.nav-links a').forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === path || (path.startsWith('vendorfoodedit') && href === 'vendordashboard.php')) {
            link.classList.add('active');
        }
    });
});
</script>
