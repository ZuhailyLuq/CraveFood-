<style>
/* FORCE SEARCH SVGs TO SHRINK GLOBALLY */
.quick-search-shell { display: flex; align-items: stretch; background: #ffffff; border-radius: 6px; border: 1px solid #e0e0e0; box-shadow: 0 4px 16px rgba(0,0,0,0.06); overflow: hidden; width: 100%; max-width: 900px; margin-bottom: 20px; }
.quick-segment { display: flex; flex-direction: row; align-items: center; padding: 12px 18px; border-right: 1px solid #efefef; background: #ffffff; flex: 1; gap: 10px; }
.quick-segment svg { width: 18px !important; height: 18px !important; fill: #888; stroke: #888; stroke-width: 0; flex-shrink: 0; }
.quick-segment svg[stroke="currentColor"] { fill: none; stroke-width: 2px; }
.quick-segment-inner { display: flex; flex-direction: column; width: 100%; text-align: left; }
.quick-segment-inner label { font-size: 10px; color: #888; margin-bottom: 2px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
.quick-segment select, .quick-segment input { border: none; outline: none; width: 100%; font-size: 14px; color: #333; background: transparent; padding: 2px 0; font-weight: 600; cursor: pointer; }
.quick-search-input { flex: 1.5; }
.quick-search-btn { width: 60px; height: auto; align-self: stretch; border: none; background: #ff2a44; color: #ffffff; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.quick-search-btn svg { width: 20px !important; height: 20px !important; fill: none; stroke: #ffffff; stroke-width: 2.5px; }

/* Premium Table */
.premium-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-top: 10px;
}
.premium-table th {
    background: #fdfdfd;
    color: #555;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    padding: 14px 20px;
    border-bottom: 2px solid #f0f0f0;
    text-align: left;
}
.premium-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f5f5f5;
    vertical-align: middle;
    color: #333;
}
.premium-table tr:last-child td {
    border-bottom: none;
}
.premium-table tr:hover td {
    background: #fafafa;
}

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
@media (max-width: 860px) {
    .premium-table th, .premium-table td {
        padding: 12px 10px;
        font-size: 0.85rem;
    }
    .quick-search-shell {
        flex-direction: column;
    }
    .quick-segment {
        border-right: none;
        border-bottom: 1px solid #efefef;
    }
    .quick-search-btn {
        width: 100%;
        padding: 12px 0;
    }
}
</style>
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
