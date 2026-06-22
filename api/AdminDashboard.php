<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if (!isset($_SESSION['AdminID'])) {
    header("Location: Login.html");
    exit();
}

$adminId   = (int)$_SESSION['AdminID'];
$adminName = htmlspecialchars($_SESSION['AdminUsername'] ?? 'Admin');

/* â”€â”€ Stat counts â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$userRow   = db_fetch_one($pdo, 'SELECT COUNT(*) AS c FROM "user"');
$vendorRow = db_fetch_one($pdo, 'SELECT COUNT(*) AS c FROM vendor');
$orderRow  = db_fetch_one($pdo, 'SELECT COUNT(*) AS c FROM orders');

$userCount   = (int)($userRow['c']   ?? 0);
$vendorCount = (int)($vendorRow['c'] ?? 0);
$orderCount  = (int)($orderRow['c']  ?? 0);

/* â”€â”€ Weekly trends â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function weekCountPDO(PDO $pdo, string $table, string $col, int $weeksAgo): int {
    $start = date('Y-m-d', strtotime("-" . ($weeksAgo + 1) . " week"));
    $end   = date('Y-m-d', strtotime("-{$weeksAgo} week"));
    $row   = db_fetch_one($pdo,
        "SELECT COUNT(*) AS c FROM \"$table\" WHERE \"$col\" >= ? AND \"$col\" < ?",
        [$start, $end]
    );
    return (int)($row['c'] ?? 0);
}

// User trend &mdash; use "CreatedAt" column
$userTrend = null;
try {
    $u1 = weekCountPDO($pdo, 'user', 'CreatedAt', 0);
    $u2 = weekCountPDO($pdo, 'user', 'CreatedAt', 1);
    $userTrend = ($u2 > 0) ? round((($u1 - $u2) / $u2) * 100) : ($u1 > 0 ? 100 : 0);
} catch (Exception $e) { $userTrend = null; }

// Order trend
$orderTrend = null;
try {
    $o1 = weekCountPDO($pdo, 'orders', 'OrderDate', 0);
    $o2 = weekCountPDO($pdo, 'orders', 'OrderDate', 1);
    $orderTrend = ($o2 > 0) ? round((($o1 - $o2) / $o2) * 100) : ($o1 > 0 ? 100 : 0);
} catch (Exception $e) { $orderTrend = null; }

/* â”€â”€ Vendor update status â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$thresholdDays = 30;
$vendorRows    = db_fetch_all($pdo, 'SELECT "VendorID", "ShopName", "LastUpdate" FROM vendor ORDER BY "LastUpdate" ASC');
$vendors       = [];
$outdatedCount = 0;

foreach ($vendorRows as $v) {
    $lastUpdate = $v['LastUpdate'];
    $daysSince  = null;
    $isOutdated = false;
    if (!empty($lastUpdate)) {
        $diff      = (new DateTime())->diff(new DateTime($lastUpdate));
        $daysSince = $diff->days;
        $isOutdated = $daysSince >= $thresholdDays;
    } else {
        $isOutdated = true;
    }
    if ($isOutdated) $outdatedCount++;
    $vendors[] = [
        'VendorID'   => (int)$v['VendorID'],
        'ShopName'   => $v['ShopName'],
        'LastUpdate' => $lastUpdate,
        'DaysSince'  => $daysSince,
        'IsOutdated' => $isOutdated,
    ];
}

/* â”€â”€ Helper: natural-language "days since" â”€â”€â”€â”€â”€ */
function humanDays($days) {
    if ($days === null) return '&mdash;';
    if ($days === 0)    return 'Today';
    if ($days === 1)    return 'Yesterday';
    return $days . ' days ago';
}

/* â”€â”€ Helper: trend chip HTML â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function trendChip($pct) {
    if ($pct === null) return '<div class="stat-trend neutral">&mdash; no trend data</div>';
    if ($pct > 0) return '<div class="stat-trend up">&#9650; +' . $pct . '% this week</div>';
    if ($pct < 0) return '<div class="stat-trend down">&#9660; ' . $pct . '% this week</div>';
    return '<div class="stat-trend neutral">â†’ unchanged this week</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CraveFood</title>
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <style>
        /* â”€â”€ Layout â”€â”€ */
        .admin-wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 36px 24px 60px;
        }

        /* â”€â”€ Welcome header â”€â”€ */
        .welcome-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 36px;
            flex-wrap: wrap;
        }
        .welcome-header .welcome-text h1 {
            color: #c1121f;
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 4px;
        }
        .welcome-header .welcome-text p {
            color: #888;
            margin: 0;
            font-size: 0.95rem;
        }
        .welcome-header .welcome-date {
            font-size: 0.85rem;
            color: #bbb;
            white-space: nowrap;
            margin-top: 6px;
        }

        /* â”€â”€ Section divider â”€â”€ */
        .section-gap { margin-bottom: 40px; }
        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #bbb;
            margin-bottom: 14px;
        }

        /* â”€â”€ Stat Cards â”€â”€ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 16px;
            padding: 26px 24px 22px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.08);
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon svg { width: 26px; height: 26px; }
        .stat-icon.users  { background: #fff0f1; }
        .stat-icon.users svg  { fill: #c1121f; }
        .stat-icon.vendors { background: #fff5e8; }
        .stat-icon.vendors svg { fill: #e07b00; }
        .stat-icon.orders { background: #eef4ff; }
        .stat-icon.orders svg { fill: #1a73e8; }
        .stat-body { flex: 1; }
        .stat-number {
            font-size: 2.4rem;
            font-weight: 900;
            line-height: 1;
            color: #1a1a1a;
            letter-spacing: -1px;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #888;
            margin-top: 5px;
            font-weight: 500;
        }
        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-top: 10px;
            padding: 3px 9px;
            border-radius: 20px;
        }
        .stat-trend.up      { background: #e8f5e9; color: #1e8e3e; }
        .stat-trend.down    { background: #fce8e6; color: #c5221f; }
        .stat-trend.neutral { background: #f5f5f5; color: #999; }

        /* â”€â”€ Vendor Section Panel â”€â”€ */
        .vendor-panel {
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .vendor-panel-header {
            padding: 22px 24px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .vendor-panel-header h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1a1a1a;
            margin: 0 0 4px;
        }
        .vendor-panel-header p {
            font-size: 0.85rem;
            color: #888;
            margin: 0 0 20px;
        }

        /* â”€â”€ Action Toolbar â”€â”€ */
        .action-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 16px 24px;
            background: #fafafa;
            border-bottom: 1px solid #f0f0f0;
        }
        .toolbar-left  { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

        .outdated-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fce8e6;
            color: #c1121f;
            font-weight: 700;
            padding: 7px 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        .btn-notify-all {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #ff2a44;
            color: #fff;
            border: none;
            padding: 9px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 700;
            transition: background 0.2s, transform 0.15s;
            white-space: nowrap;
        }
        .btn-notify-all:hover    { background: #cc001b; transform: translateY(-1px); }
        .btn-notify-all:disabled { background: #ddd; color: #aaa; cursor: not-allowed; transform: none; }

        /* Search & Filter */
        .toolbar-search {
            position: relative;
        }
        .toolbar-search svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            height: 15px;
            fill: #aaa;
            pointer-events: none;
        }
        .toolbar-search input {
            padding: 8px 12px 8px 32px;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            font-size: 0.85rem;
            outline: none;
            width: 200px;
            background: #fff;
            transition: border-color 0.2s;
        }
        .toolbar-search input:focus { border-color: #c1121f; }
        .toolbar-filter select {
            padding: 8px 12px;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            font-size: 0.85rem;
            outline: none;
            background: #fff;
            cursor: pointer;
            transition: border-color 0.2s;
            color: #444;
        }
        .toolbar-filter select:focus { border-color: #c1121f; }

        /* â”€â”€ Vendor Table â”€â”€ */
        .vendor-status-table {
            width: 100%;
            border-collapse: collapse;
            display: none; /* replaced by premium-table */
        }
        .vendor-name { font-weight: 700; font-size: 0.95rem; color: #1a1a1a; }
        .vendor-id   { font-size: 0.78rem; color: #bbb; margin-top: 2px; }
        .last-update-date { font-size: 0.88rem; color: #555; }
        .last-update-never { font-size: 0.88rem; color: #ccc; font-style: italic; }
        .days-since { font-size: 0.88rem; color: #666; }

        /* Clean up unused old classes */
        .no-action { font-size: 0.82rem; color: #ccc; }

        /* Empty state */
        .empty-state {
            padding: 48px 24px;
            text-align: center;
            color: #bbb;
        }
        .empty-state svg { width: 48px; height: 48px; fill: #e8e8e8; margin-bottom: 14px; }
        .empty-state p { margin: 0; font-size: 0.9rem; }

        /* â”€â”€ Toast â”€â”€ */
        .toast-admin {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1e8e3e;
            color: #fff;
            padding: 13px 22px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 6px 24px rgba(0,0,0,0.15);
            z-index: 9999;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s, transform 0.3s;
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .toast-admin.show { opacity: 1; transform: translateY(0); }
        .toast-admin.toast-error { background: #c5221f; }

        /* ── No-results row ── */
        #no-results-row { display: none; }
        #no-results-row td { text-align: center; padding: 36px; color: #bbb; font-size: 0.9rem; }

        @media (max-width: 860px) {
            .action-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .toolbar-left, .toolbar-right {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include('admin_header.php'); ?>

    <div class="home-hero-wrap" style="align-items:flex-start; flex-direction:column; padding-bottom:60px;"><div class="home-hero-content" style="width:100%;">

        <!-- â”€â”€ Welcome Header â”€â”€ -->
        <div class="welcome-header" style="margin-bottom:24px;">
            <div class="welcome-text">
                <h1 class="hero-title" style="font-size:2rem; font-weight:800; color:#1a1a1a; margin:0 0 8px; letter-spacing:-0.5px;">&#128075; Welcome, <span style="color:#ff2a44;"><?php echo $adminName; ?></span></h1>
                <p class="hero-subtitle" style="color:#666; font-size:0.95rem; margin:0;">Admin Dashboard &mdash; here's your system overview for today.</p>
            </div>
            <div class="welcome-date" style="color:#888; font-weight:500; font-size:0.9rem;"><?php echo date('l, d F Y'); ?></div>
        </div>

        <!-- â”€â”€ Stat Cards â”€â”€ -->
        <div class="section-gap">
            <div class="section-label">Key Metrics</div>
            <div class="stats-grid">

                <!-- Users -->
                <div class="stat-card">
                    <div class="stat-icon users">
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-number"><?php echo number_format($userCount); ?></div>
                        <div class="stat-label">Registered Users</div>
                        <?php echo trendChip($userTrend); ?>
                    </div>
                </div>

                <!-- Vendors -->
                <div class="stat-card">
                    <div class="stat-icon vendors">
                        <svg viewBox="0 0 24 24"><path d="M20 4H4v2l8 5 8-5V4zm0 4.5-8 5-8-5V20h16V8.5z"/><path d="M2 4h20v2H2zM4 6h16v14H4z" opacity="0"/><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-8 9L4 7h16l-8 6z"/></svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-number"><?php echo number_format($vendorCount); ?></div>
                        <div class="stat-label">Registered Vendors</div>
                        <div class="stat-trend neutral">
                            <?php echo $outdatedCount; ?> outdated of <?php echo $vendorCount; ?>
                        </div>
                    </div>
                </div>

                <!-- Orders -->
                <div class="stat-card">
                    <div class="stat-icon orders">
                        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z"/></svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-number"><?php echo number_format($orderCount); ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                </div>
            </div> <!-- End metrics grid -->

            <!-- Action Toolbar & Search Bar -->
            <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 20px;">
                <!-- Notification block -->
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <div class="outdated-badge" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #fdf2f2; color: #c1121f; border-radius: 20px; font-size: 0.85rem; font-weight: 700; max-width: max-content;">
                        <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:#c1121f;flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                        <?php echo $outdatedCount; ?> outdated vendor<?php echo $outdatedCount !== 1 ? 's' : ''; ?>
                    </div>
                    <?php if ($outdatedCount > 0): ?>
                    <form method="POST" action="patch_admin_metrics.php" style="display:inline;">
                        <button type="submit" name="Action_NotifyAllOutdated" class="btn-primary" style="padding: 8px 16px; font-size: 0.85rem; border-radius: 8px; width: auto; margin-top: 0;">
                            <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:#fff;margin-right:6px;"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                            Notify All Outdated
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- Search Input -->
                <div class="quick-search-shell" style="margin: 0; max-width: 600px;">

                    <!-- Search Input -->
                    <div class="quick-segment">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <div class="quick-segment-inner">
                            <label>Vendor Name</label>
                            <input type="text" id="vendorSearch" placeholder="Search vendors..." oninput="filterTable()" class="quick-search-input">
                        </div>
                    </div>

                    <!-- Filter Select -->
                    <div class="quick-segment" style="border-right: none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                        <div class="quick-segment-inner">
                            <label>Filter Status</label>
                            <select id="vendorStatusFilter" onchange="filterTable()">
                                <option value="all">All Statuses</option>
                                <option value="outdated">Outdated</option>
                                <option value="ok">Up to Date</option>
                                <option value="never">Never Updated</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div> <!-- End Action Toolbar flexbox -->

            <!-- Table -->
            <?php if (count($vendors) > 0): ?>
            <div class="table-responsive">
                    <table class="premium-table" id="vendorTable">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>Last Updated</th>
                                <th>Time Since</th>
                                <th>Status</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="vendorTableBody">
                        <?php foreach ($vendors as $v):
                            if ($v['LastUpdate'] === null)  $statusKey = 'never';
                            elseif ($v['IsOutdated'])       $statusKey = 'outdated';
                            else                            $statusKey = 'ok';
                        ?>
                            <tr data-vendor-name="<?php echo strtolower(htmlspecialchars($v['ShopName'])); ?>"
                                data-status="<?php echo $statusKey; ?>">
                                <td>
                                    <div class="vendor-name"><?php echo htmlspecialchars($v['ShopName']); ?></div>
                                    <div class="vendor-id">ID #<?php echo $v['VendorID']; ?></div>
                                </td>
                                <td>
                                    <?php if ($v['LastUpdate']): ?>
                                        <div class="last-update-date"><?php echo date('d M Y', strtotime($v['LastUpdate'])); ?></div>
                                        <div style="font-size:0.78rem;color:#bbb;"><?php echo date('h:i A', strtotime($v['LastUpdate'])); ?></div>
                                    <?php else: ?>
                                        <span class="last-update-never">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="days-since"><?php echo humanDays($v['DaysSince']); ?></div>
                                </td>
                                <td>
                                    <?php if ($statusKey === 'never'): ?>
                                        <span class="badge-pill badge-neutral">Never Updated</span>
                                    <?php elseif ($statusKey === 'outdated'): ?>
                                        <span class="badge-pill badge-danger">Outdated</span>
                                    <?php else: ?>
                                        <span class="badge-pill badge-success">Up to Date</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($v['IsOutdated']): ?>
                                        <button type="button"
                                                class="btn-outline btn-outline-danger"
                                                style="padding:6px 12px; font-size:0.8rem;"
                                                id="btn-notify-<?php echo $v['VendorID']; ?>"
                                                onclick="notifyVendor(<?php echo $v['VendorID']; ?>, '<?php echo htmlspecialchars(addslashes($v['ShopName']), ENT_QUOTES); ?>')">
                                            <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                                            Notify
                                        </button>
                                    <?php else: ?>
                                        <span class="no-action">No action needed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tbody>
                            <tr id="no-results-row">
                                <td colspan="5">No vendors match your search or filter.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/></svg>
                    <p>No vendors registered yet.</p>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </div><!-- /admin-wrap -->

    <!-- Toast notification -->
    <div class="toast-admin" id="adminToast"></div>

    <script>
    /* â”€â”€ Navbar active link â”€â”€ */
    document.addEventListener('DOMContentLoaded', function () {
        var page  = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
        if (page === '' || page === 'index.php') page = 'homepage.php';
        document.querySelectorAll('.nav-links a').forEach(function (link) {
            var href = (link.getAttribute('href') || '').toLowerCase();
            if (href === page) link.classList.add('active');
        });
    });

    /* â”€â”€ Toast helper â”€â”€ */
    function showToast(msg, isError) {
        var t = document.getElementById('adminToast');
        t.textContent = msg;
        t.className   = 'toast-admin' + (isError ? ' toast-error' : '') + ' show';
        setTimeout(function () { t.className = 'toast-admin' + (isError ? ' toast-error' : ''); }, 3500);
    }

    /* â”€â”€ Client-side search + filter â”€â”€ */
    function filterTable() {
        var search = document.getElementById('vendorSearch').value.toLowerCase().trim();
        var status = document.getElementById('statusFilter').value;
        var rows   = document.querySelectorAll('#vendorTableBody tr');
        var visible = 0;
        rows.forEach(function (row) {
            var name  = row.getAttribute('data-vendor-name') || '';
            var st    = row.getAttribute('data-status') || '';
            var matchSearch = name.includes(search);
            var matchStatus = (status === 'all') || (st === status);
            if (matchSearch && matchStatus) { row.style.display = ''; visible++; }
            else { row.style.display = 'none'; }
        });
        var noResults = document.getElementById('no-results-row');
        if (noResults) noResults.style.display = visible === 0 ? '' : 'none';
    }

    /* â”€â”€ Individual notify â”€â”€ */
    function notifyVendor(vendorId, shopName) {
        var btn = document.getElementById('btn-notify-' + vendorId);
        if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }

        var fd = new FormData();
        fd.append('action', 'notify');
        fd.append('vendor_id', vendorId);

        fetch('AdminNotifyVendor.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showToast('&#10003; Notification sent to ' + shopName);
                    if (btn) { btn.textContent = '&#10003; Sent'; btn.disabled = true; }
                } else {
                    showToast('&#10007; ' + (data.message || 'Failed to notify.'), true);
                    if (btn) { btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg> Notify'; }
                }
            })
            .catch(function () {
                showToast('&#10007; Network error.', true);
                if (btn) { btn.disabled = false; btn.textContent = 'Notify'; }
            });
    }

    /* â”€â”€ Notify All â”€â”€ */
    function notifyAllOutdated() {
        var btn = document.getElementById('btnNotifyAll');
        if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }

        var fd = new FormData();
        fd.append('action', 'notify_all_outdated');
        fd.append('days', '<?php echo $thresholdDays; ?>');

        fetch('AdminNotifyVendor.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showToast('&#10003; ' + data.message);
                    /* disable all individual notify buttons too */
                    document.querySelectorAll('.btn-notify').forEach(function (b) {
                        b.disabled = true; b.textContent = '&#10003; Sent';
                    });
                    if (btn) { btn.textContent = '&#10003; All Notified'; }
                } else {
                    showToast('&#10007; ' + (data.message || 'Failed.'), true);
                    if (btn) { btn.disabled = false; btn.textContent = '&#128276; Notify All Outdated'; }
                }
            })
            .catch(function () {
                showToast('&#10007; Network error.', true);
                if (btn) { btn.disabled = false; btn.textContent = '&#128276; Notify All Outdated'; }
            });
    }
    </script>
</div></div></body>
</html>
