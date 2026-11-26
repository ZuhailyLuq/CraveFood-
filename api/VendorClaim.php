<?php
/**
 * VendorClaim.php
 * Lets a real restaurant owner claim their OSM-imported vendor profile.
 * On submit → inserts into vendor_claim_requests.
 * Admin reviews in AdminVendorSync.php.
 */
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

$vendorId = (int)($_GET['vendor_id'] ?? 0);
if ($vendorId <= 0) {
    header("Location: Homepage.php");
    exit();
}

$vendor = db_fetch_one($pdo,
    'SELECT "VendorID","ShopName","Location","VendorType","IsVerified" FROM vendor WHERE "VendorID" = ?',
    [$vendorId]
);

if (!$vendor || $vendor['VendorType'] !== 'imported') {
    header("Location: Homepage.php?type=error&msg=" . urlencode("This vendor is not available for claiming."));
    exit();
}

// Already claimed / verified
if ($vendor['IsVerified']) {
    header("Location: VendorInfo.php?vendor_id={$vendorId}&type=info&msg=" . urlencode("This restaurant has already been claimed."));
    exit();
}

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email   = trim($_POST['email']   ?? '');
    $msgText = trim($_POST['message'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $msgType = 'error';
    } else {
        // Check for duplicate pending claim
        $existing = db_fetch_one($pdo,
            'SELECT "ClaimID" FROM vendor_claim_requests WHERE "VendorID" = ? AND "Status" = \'pending\'',
            [$vendorId]
        );
        if ($existing) {
            $message = 'A claim for this restaurant is already under review.';
            $msgType = 'info';
        } else {
            db_execute($pdo,
                'INSERT INTO vendor_claim_requests ("VendorID","Email","Message") VALUES (?,?,?)',
                [$vendorId, $email, $msgText]
            );
            $message = 'Your claim has been submitted! Our admin will review it and contact you at ' . htmlspecialchars($email) . '.';
            $msgType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Claim Restaurant – CraveFood</title>
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <style>
        .claim-wrap { max-width: 540px; margin: 60px auto; padding: 0 16px; }
        .claim-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.08);
                      padding: 36px 40px; }
        .claim-card h2 { color: #ff2a44; margin-top: 0; }
        .venue-name { font-size: 1.1rem; font-weight: 700; color: #333; margin-bottom: 4px; }
        .venue-loc  { color: #888; font-size: .9rem; margin-bottom: 24px; }
        label { display: block; font-size: .85rem; color: #555; font-weight: 600; margin-bottom: 4px; }
        input[type=email], textarea {
            width: 100%; padding: 10px 14px; border: 1.5px solid #eaeaea; border-radius: 8px;
            font-size: .95rem; margin-bottom: 16px; box-sizing: border-box; resize: vertical; }
        input[type=email]:focus, textarea:focus { outline: none; border-color: #ff2a44; }
        button[type=submit] { width: 100%; padding: 12px; background: #ff2a44; color: #fff;
                              border: none; border-radius: 8px; font-size: 1rem; font-weight: 700;
                              cursor: pointer; }
        button[type=submit]:hover { background: #cc1f35; }
        .msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: .9rem; }
        .msg.success { background: #e8f5e9; color: #1e8e3e; }
        .msg.error   { background: #fce8e6; color: #c5221f; }
        .msg.info    { background: #eef4ff; color: #1a73e8; }
        .note { font-size: .78rem; color: #aaa; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="claim-wrap">
    <div class="claim-card">
        <h2>🏷 Claim Your Restaurant</h2>
        <div class="venue-name"><?= htmlspecialchars($vendor['ShopName']) ?></div>
        <div class="venue-loc">📍 <?= htmlspecialchars($vendor['Location'] ?: 'Malaysia') ?></div>

        <?php if ($message): ?>
            <div class="msg <?= $msgType ?>"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($msgType !== 'success'): ?>
        <form method="POST">
            <label>Your Business Email *</label>
            <input type="email" name="email" required placeholder="owner@restaurant.com">

            <label>How can you prove ownership? <span style="color:#aaa;font-weight:400">(optional)</span></label>
            <textarea name="message" rows="4"
                placeholder="e.g. I am the owner — my phone is 012-xxxxxxx, or I can provide SSM registration."></textarea>

            <button type="submit">Submit Claim</button>
        </form>
        <?php endif; ?>

        <p class="note">
            Once approved, you can fully manage your menu, receive orders, and chat with customers on CraveFood.<br><br>
            Restaurant data sourced from <a href="https://www.openstreetmap.org/copyright" target="_blank">© OpenStreetMap contributors</a>, ODbL.
        </p>
    </div>
</div>

</body>
</html>

