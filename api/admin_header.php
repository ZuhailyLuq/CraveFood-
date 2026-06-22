<div class="navbar">
    <div class="logo" onclick="window.location.href='AdminDashboard.php'">
        <h2><span>crave</span>food</h2>
    </div>
    <div class="nav-links">
        <a href="AdminDashboard.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Dashboard
        </a>
        <a href="AdminAchievements.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.45 1-1 1H4v2h16v-2h-5c-.55 0-1-.45-1-1v-2.34"></path><path d="M12 2a5 5 0 0 1 5 5v5a5 5 0 0 1-10 0V7a5 5 0 0 1 5-5z"></path></svg>
            Achievements
        </a>
        <a href="Logout.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            Logout
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var path = window.location.pathname.split('/').pop().toLowerCase() || 'admindashboard.php';
    if (path === '' || path === 'index.php') path = 'admindashboard.php';
    
    document.querySelectorAll('.nav-links a').forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === path || (path.startsWith('adminmanagevendors') && href === 'admindashboard.php')) {
            link.classList.add('active');
        }
    });
});
</script>
