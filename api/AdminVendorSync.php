<?php
/**
 * AdminVendorSync.php
 * Admin-only page: sync OSM vendor data via Overpass API.
 * Access: Admin session required.
 */
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';
require_once __DIR__ . '/external/OverpassFetcher.php';

if (!isset($_SESSION['AdminID'])) {
    header("Location: Login.html");
    exit();
}

$message    = '';
$msgType    = '';
$syncResult = null;
$regions    = OverpassFetcher::getMalaysiaRegions();

// ── Handle sync POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Approve / hide a single vendor
    if ($_POST['action'] === 'toggle_vendor' && isset($_POST['vendor_id'])) {
        $vid       = (int)$_POST['vendor_id'];
        $newStatus = $_POST['new_status'] ?? 'Available';
        db_execute($pdo, 'UPDATE vendor SET "Status" = ? WHERE "VendorID" = ? AND "VendorType" = \'imported\'', [$newStatus, $vid]);
        $message = "Vendor status updated.";
        $msgType = 'success';

    // Run OSM sync for a chosen region
    } elseif ($_POST['action'] === 'sync_region' && isset($_POST['region'])) {
        $regionName = $_POST['region'];
        if (isset($regions[$regionName])) {
            $r = $regions[$regionName];
            try {
                $syncResult = OverpassFetcher::fetchByBbox(
                    $pdo,
                    $r['south'], $r['west'], $r['north'], $r['east'],
                    $regionName
                );
                $message = "Sync complete for <strong>{$regionName}</strong>.";
                $msgType = 'success';
            } catch (Exception $e) {
                $message = "Sync failed: " . htmlspecialchars($e->getMessage());
                $msgType = 'error';
            }
        } else {
            $message = "Unknown region.";
            $msgType = 'error';
        }

    // Approve a claim request
    } elseif ($_POST['action'] === 'approve_claim' && isset($_POST['claim_id'])) {
        $cid = (int)$_POST['claim_id'];
        $claim = db_fetch_one($pdo, 'SELECT "VendorID","Email" FROM vendor_claim_requests WHERE "ClaimID" = ?', [$cid]);
        if ($claim) {
            db_execute($pdo, 'UPDATE vendor_claim_requests SET "Status" = \'approved\' WHERE "ClaimID" = ?', [$cid]);
            // Flip vendor to registered + set a temp password they can reset
            $tempPass = password_hash('CraveTemp' . rand(1000,9999), PASSWORD_DEFAULT);
            db_execute($pdo,
                'UPDATE vendor SET "VendorType" = \'registered\', "IsVerified" = TRUE, "VendorPassword" = ? WHERE "VendorID" = ?',
                [$tempPass, $claim['VendorID']]
            );
            $message = "Claim approved. Vendor ID {$claim['VendorID']} is now registered.";
            $msgType = 'success';
        }

    // Reject a claim
    } elseif ($_POST['action'] === 'reject_claim' && isset($_POST['claim_id'])) {
        $cid = (int)$_POST['claim_id'];
        db_execute($pdo, 'UPDATE vendor_claim_requests SET "Status" = \'rejected\' WHERE "ClaimID" = ?', [$cid]);
        $message = "Claim rejected.";
        $msgType = 'info';
    }
}

// ── Data for display ─────────────────────────────────────────────────
$importedVendors = db_fetch_all($pdo,
    'SELECT "VendorID","ShopName","Location","Amenity","Categories","DietaryInfo","OpenHours",
            "ExternalURL","LastSynced","Status","IsVerified"
     FROM vendor WHERE "VendorType" = \'imported\'
     ORDER BY "LastSynced" DESC NULLS LAST, "ShopName" ASC
     LIMIT 200'
);

$pendingClaims = db_fetch_all($pdo,
    'SELECT cr."ClaimID", cr."Email", cr."Message", cr."CreatedAt",
            v."ShopName", v."VendorID"
     FROM vendor_claim_requests cr
     JOIN vendor v ON v."VendorID" = cr."VendorID"
     WHERE cr."Status" = \'pending\'
     ORDER BY cr."CreatedAt" ASC'
);

$importedCount = db_fetch_one($pdo, 'SELECT COUNT(*) AS c FROM vendor WHERE "VendorType" = \'imported\'');
$lastSync      = db_fetch_one($pdo, 'SELECT MAX("LastSynced") AS t FROM vendor WHERE "VendorType" = \'imported\'');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OSM Vendor Sync – CraveFood Admin</title>
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <?php include 'admin_header.php'; ?>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f7f7f9; color: #333; }
        .sync-wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        h2 { color: #ff2a44; margin-bottom: 4px; }
        .sub { color: #888; font-size: 0.9rem; margin-bottom: 24px; }
        .stats-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-card { background:#fff; border-radius:12px; padding:18px 24px;
                     box-shadow:0 2px 8px rgba(0,0,0,.06); min-width:150px; flex:1; }
        .stat-card .num { font-size:2rem; font-weight:700; color:#ff2a44; }
        .stat-card .lbl { font-size:.8rem; color:#888; text-transform:uppercase; letter-spacing:.5px; }
        .panel { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.06);
                 padding:20px 24px; margin-bottom:24px; }
        .panel h3 { margin-top:0; font-size:1rem; color:#444; border-bottom:1px solid #f0f0f0; padding-bottom:10px; }
        .sync-form { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
        .sync-form select, .sync-form button { padding:10px 16px; border-radius:8px;
                     border:1.5px solid #eaeaea; font-size:.9rem; }
        .sync-form button { background:#ff2a44; color:#fff; border-color:#ff2a44; cursor:pointer; font-weight:600; }
        .sync-form button:hover { background:#cc1f35; }
        .msg { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:.9rem; }
        .msg.success { background:#e8f5e9; color:#1e8e3e; border-left:4px solid #1e8e3e; }
        .msg.error   { background:#fce8e6; color:#c5221f; border-left:4px solid #c5221f; }
        .msg.info    { background:#eef4ff; color:#1a73e8; border-left:4px solid #1a73e8; }
        .result-box  { background:#f8f9ff; border:1px solid #dde3ff; border-radius:8px;
                       padding:14px 18px; font-size:.9rem; display:flex; gap:24px; flex-wrap:wrap; }
        .result-box span strong { color:#ff2a44; }
        table.vt { width:100%; border-collapse:collapse; font-size:.85rem; }
        table.vt th { background:#fafafa; color:#666; font-size:.75rem; text-transform:uppercase;
                      letter-spacing:.5px; padding:10px 14px; border-bottom:2px solid #f0f0f0; text-align:left; }
        table.vt td { padding:10px 14px; border-bottom:1px solid #f7f7f7; vertical-align:middle; }
        table.vt tr:last-child td { border-bottom:none; }
        .badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px;
                 font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
        .badge-osm      { background:#e8f5e9; color:#2e7d32; }
        .badge-halal    { background:#fff3cd; color:#856404; }
        .badge-hidden   { background:#fce8e6; color:#c5221f; }
        .badge-visible  { background:#e8f5e9; color:#1e8e3e; }
        .btn-sm { padding:5px 12px; border-radius:6px; border:1.5px solid #eaeaea; font-size:.78rem;
                  font-weight:600; cursor:pointer; background:#fff; color:#444; text-decoration:none; }
        .btn-sm.danger  { border-color:rgba(193,18,31,.4); color:#c1121f; }
        .btn-sm.danger:hover { background:#fce8e6; }
        .btn-sm.primary { border-color:rgba(255,42,68,.4); color:#ff2a44; }
        .btn-sm.primary:hover { background:#fff0f2; }
        .osm-link { color:#3b80d0; text-decoration:none; font-size:.78rem; }
        .osm-link:hover { text-decoration:underline; }
        .empty { text-align:center; color:#aaa; padding:30px; }
        .credit { font-size:.75rem; color:#aaa; margin-top:8px; }
    </style>
</head>
<body>

<div class="sync-wrap">
    <h2>🗺 OSM Vendor Sync</h2>
    <p class="sub">Pull live restaurant data from OpenStreetMap via Overpass API — free, no API key needed.</p>

    <?php if ($message): ?>
        <div class="msg <?= $msgType ?>"><?= $message ?></div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="num"><?= (int)($importedCount['c'] ?? 0) ?></div>
            <div class="lbl">Imported Vendors</div>
        </div>
        <div class="stat-card">
            <div class="num"><?= count($pendingClaims) ?></div>
            <div class="lbl">Pending Claims</div>
        </div>
        <div class="stat-card">
            <div class="num" style="font-size:1rem; padding-top:6px;">
                <?= $lastSync['t'] ? date('d M Y', strtotime($lastSync['t'])) : '—' ?>
            </div>
            <div class="lbl">Last Sync</div>
        </div>
    </div>

    <!-- Sync trigger panel -->
    <div class="panel">
        <h3>⚡ Trigger Sync</h3>
        <form method="POST">
            <input type="hidden" name="action" value="sync_region">
            <div class="sync-form">
                <div>
                    <label style="font-size:.8rem;color:#888;display:block;margin-bottom:4px;">Region</label>
                    <select name="region">
                        <?php foreach ($regions as $name => $coords): ?>
                            <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">🔄 Sync Now</button>
            </div>
        </form>
        <?php if ($syncResult): ?>
            <div class="result-box" style="margin-top:14px;">
                <span>🔎 Elements found: <strong><?= $syncResult['total_elements'] ?></strong></span>
                <span>✅ Imported: <strong><?= $syncResult['imported'] ?></strong></span>
                <span>🔄 Updated: <strong><?= $syncResult['updated'] ?></strong></span>
                <span>⏭ Skipped: <strong><?= $syncResult['skipped'] ?></strong></span>
            </div>
        <?php endif; ?>
        <p class="credit">Data © <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap contributors</a>, ODbL</p>
    </div>

    <!-- Pending claim requests -->
    <?php if ($pendingClaims): ?>
    <div class="panel">
        <h3>📋 Pending Claim Requests (<?= count($pendingClaims) ?>)</h3>
        <table class="vt">
            <thead>
                <tr>
                    <th>Restaurant</th><th>Claimant Email</th><th>Message</th><th>Date</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingClaims as $claim): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($claim['ShopName']) ?></strong><br>
                        <small style="color:#888">ID <?= $claim['VendorID'] ?></small></td>
                    <td><?= htmlspecialchars($claim['Email']) ?></td>
                    <td style="max-width:260px"><?= htmlspecialchars($claim['Message'] ?? '—') ?></td>
                    <td><?= date('d M Y', strtotime($claim['CreatedAt'])) ?></td>
                    <td style="white-space:nowrap">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="approve_claim">
                            <input type="hidden" name="claim_id" value="<?= $claim['ClaimID'] ?>">
                            <button class="btn-sm primary" type="submit">✅ Approve</button>
                        </form>
                        <form method="POST" style="display:inline;margin-left:6px">
                            <input type="hidden" name="action" value="reject_claim">
                            <input type="hidden" name="claim_id" value="<?= $claim['ClaimID'] ?>">
                            <button class="btn-sm danger" type="submit">✗ Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Imported vendors table -->
    <div class="panel">
        <h3>🌍 Imported Vendors (<?= count($importedVendors) ?>)</h3>
        <?php if (!$importedVendors): ?>
            <p class="empty">No imported vendors yet. Run a sync above to get started.</p>
        <?php else: ?>
        <table class="vt">
            <thead>
                <tr>
                    <th>Name</th><th>Type</th><th>Tags</th><th>Location</th>
                    <th>Hours</th><th>Status</th><th>Last Synced</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($importedVendors as $v): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($v['ShopName']) ?></strong><br>
                        <a href="<?= htmlspecialchars($v['ExternalURL'] ?? '#') ?>" target="_blank" class="osm-link">View on OSM ↗</a>
                    </td>
                    <td><?= htmlspecialchars($v['Amenity'] ?? '—') ?></td>
                    <td>
                        <?php if ($v['DietaryInfo']): ?>
                            <span class="badge badge-halal"><?= htmlspecialchars($v['DietaryInfo']) ?></span>
                        <?php endif; ?>
                        <?php if ($v['Categories']): ?>
                            <br><small style="color:#888"><?= htmlspecialchars($v['Categories']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:160px;font-size:.8rem"><?= htmlspecialchars($v['Location'] ?: '—') ?></td>
                    <td style="font-size:.8rem"><?= htmlspecialchars($v['OpenHours'] ?: '—') ?></td>
                    <td>
                        <?php $s = $v['Status'] ?? 'Available'; ?>
                        <span class="badge <?= $s === 'Available' ? 'badge-visible' : 'badge-hidden' ?>">
                            <?= htmlspecialchars($s) ?>
                        </span>
                    </td>
                    <td style="font-size:.78rem;color:#888">
                        <?= $v['LastSynced'] ? date('d M H:i', strtotime($v['LastSynced'])) : '—' ?>
                    </td>
                    <td style="white-space:nowrap">
                        <?php $newStatus = ($v['Status'] === 'Available') ? 'Hidden' : 'Available'; ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle_vendor">
                            <input type="hidden" name="vendor_id" value="<?= $v['VendorID'] ?>">
                            <input type="hidden" name="new_status" value="<?= $newStatus ?>">
                            <button class="btn-sm <?= $newStatus === 'Hidden' ? 'danger' : 'primary' ?>" type="submit">
                                <?= $newStatus === 'Hidden' ? '🙈 Hide' : '👁 Show' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <p class="credit" style="text-align:center;">
        Restaurant data © <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap contributors</a>, Open Database Licence (ODbL)
    </p>
</div>

</body>
</html>

