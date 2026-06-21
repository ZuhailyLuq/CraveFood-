<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

$userId = isset($_SESSION['UserID']) ? (int)$_SESSION['UserID'] : 0;

if (!isset($_GET['vendor_id'])) {
    header("Location: Homepage.php");
    exit();
}

$vendorId = (int)$_GET['vendor_id'];

$vendor = db_fetch_one($pdo,
    'SELECT "ShopName", "Location", "OpenHours", "FoodType", "Description", "Image", "Latitude", "Longitude" FROM vendor WHERE "VendorID" = ?',
    [$vendorId]
);

if (!$vendor) {
    header("Location: Homepage.php?type=error&msg=" . urlencode("Vendor not found."));
    exit();
}

$foodItems = db_fetch_all($pdo,
    'SELECT "FoodID", "FoodName", "Price", "Description", "DietaryTag", "Category", "Image" FROM menu_food WHERE "VendorID" = ? AND "Status" = \'Available\'',
    [$vendorId]
);

$activeOrder = null;
if ($userId > 0) {
    $activeOrder = db_fetch_one($pdo,
        'SELECT "OrderID", "Status" FROM orders WHERE "UserID" = ? AND "Status" NOT IN (\'Finished\', \'Completed\', \'Cancelled\') ORDER BY "OrderID" DESC LIMIT 1',
        [$userId]
    );
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['vendor_id' => null, 'vendor_name' => '', 'items' => []];
}
$cartCount = 0;
foreach ($_SESSION['cart']['items'] as $ci) {
    $cartCount += $ci['Quantity'];
}

$hasMap   = !empty($vendor['Latitude']) && !empty($vendor['Longitude']);
$shopName = htmlspecialchars((string)$vendor['ShopName']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $shopName; ?> &ndash; CraveFood</title>
    <meta name="description" content="View the menu and details for <?php echo $shopName; ?> on CraveFood.">

    <!-- Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Global styles -->
    <link rel="stylesheet" href="../style.css?v=20260621-7">

    <?php if ($hasMap): ?>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <?php endif; ?>

    <style>
        /* â”€â”€ Base â”€â”€ */
        *, body { font-family: 'Inter', 'Segoe UI', sans-serif; }

        .vi-page-wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 100px;
        }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           BREADCRUMB (desktop/tablet)
           BACK LINK (mobile)
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .vi-breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 18px 0 10px;
            font-size: 0.8rem;
            color: #bbb;
        }
        .vi-breadcrumb a {
            color: #aaa;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.15s;
        }
        .vi-breadcrumb a:hover { color: #ff2a44; }
        .vi-breadcrumb-sep { color: #d5d5d5; font-size: 0.72rem; }
        .vi-breadcrumb-current { color: #555; font-weight: 600; }

        /* Mobile back link &mdash; hidden on desktop */
        .vi-back-link {
            display: none;
            align-items: center;
            gap: 5px;
            padding: 14px 0 6px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #ff2a44;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .vi-back-link:hover { opacity: 0.75; }
        .vi-back-link svg { width: 18px; height: 18px; fill: #ff2a44; flex-shrink: 0; }

        @media (max-width: 640px) {
            .vi-breadcrumb { display: none; }
            .vi-back-link  { display: flex; }
        }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           PROFILE CARD &mdash; Desktop: 50/50 split
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .vi-profile-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 8px 32px rgba(193,18,31,0.08), 0 1px 4px rgba(0,0,0,0.04);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-bottom: 36px;
        }

        /* Left column */
        .vi-left-col {
            display: flex;
            flex-direction: column;
            border-right: 1px solid #f5eaec;
        }

        .vi-cover-img {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
            display: block;
        }
        .vi-cover-placeholder {
            width: 100%;
            aspect-ratio: 16/9;
            background: linear-gradient(135deg, #f9fafb 0%, #ffd0d8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .vi-cover-placeholder svg { width: 56px; height: 56px; fill: #ff2a44; opacity: 0.25; }

        .vi-info-body {
            padding: 24px 28px 28px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .vi-shop-name {
            font-size: clamp(1.4rem, 2.5vw, 2rem);
            font-weight: 800;
            color: #1e1e1e;
            margin: 0;
            line-height: 1.2;
        }

        .vi-meta-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 0;
        }
        .vi-meta-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.9rem;
            color: #555;
        }
        .vi-meta-icon {
            width: 18px;
            height: 18px;
            fill: #ff2a44;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .vi-meta-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #aaa;
            display: block;
            margin-bottom: 1px;
        }
        .vi-meta-value { color: #333; font-weight: 500; }

        .vi-desc {
            font-size: 0.88rem;
            color: #777;
            line-height: 1.6;
            margin: 0;
            flex: 1;
        }

        .vi-navigate-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ff2a44;
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 12px 22px;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            width: fit-content;
            margin-top: auto;
            box-shadow: 0 4px 14px rgba(193,18,31,0.28);
            transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
        }
        .vi-navigate-btn:hover {
            background: #e01e38;
            box-shadow: 0 6px 20px rgba(193,18,31,0.38);
            transform: translateY(-1px);
            color: #fff;
        }
        .vi-navigate-btn svg { width: 18px; height: 18px; fill: #fff; flex-shrink: 0; }
        .vi-navigate-btn:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
        }
        /* Button variant &ndash; open in maps (blue tint) */
        .vi-navigate-btn.btn-maps-app {
            background: #1a73e8;
            box-shadow: 0 4px 14px rgba(26,115,232,0.30);
        }
        .vi-navigate-btn.btn-maps-app:hover {
            background: #1557b0;
            box-shadow: 0 6px 20px rgba(26,115,232,0.40);
            color: #fff;
        }

        /* Spinner inside button */
        @keyframes vi-spin { to { transform: rotate(360deg); } }
        .vi-btn-spinner {
            width: 16px; height: 16px;
            border: 2.5px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: vi-spin 0.7s linear infinite;
            flex-shrink: 0;
        }

        /* Route info block (above button) */
        .vi-route-info {
            display: none;
            background: #f8f9fb;
            border: 1px solid #e8eaed;
            border-radius: 12px;
            padding: 12px 16px;
            gap: 20px;
            margin-top: auto;
            animation: vi-fadeup 0.35s ease;
        }
        .vi-route-info.visible {
            display: flex;
        }
        .vi-route-info-error {
            display: none;
            background: #fff5f6;
            border: 1px solid #ffd0d8;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.82rem;
            color: #9e1020;
            font-weight: 500;
            margin-top: auto;
            animation: vi-fadeup 0.3s ease;
        }
        .vi-route-info-error.visible { display: block; }
        @keyframes vi-fadeup {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .vi-route-stat {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .vi-route-stat-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #aaa;
        }
        .vi-route-stat-value {
            font-size: 1.05rem;
            font-weight: 800;
            color: #1e1e1e;
        }
        /* Route polyline colour used in JS */
        .vi-route-line-ref { stroke: #1a73e8; }

        /* Right column &ndash; map */
        .vi-right-col {
            position: relative;
            min-height: 380px;
        }
        #vendorInfoMap {
            width: 100%;
            height: 100%;
            min-height: 380px;
        }
        /* Floating "Center on My Location" inside map */
        .map-locate-btn {
            position: absolute;
            bottom: 16px;
            right: 16px;
            z-index: 800;
            background: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 14px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #ff2a44;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.14);
            transition: box-shadow 0.2s, transform 0.15s;
        }
        .map-locate-btn:hover { box-shadow: 0 6px 22px rgba(0,0,0,0.2); transform: translateY(-1px); }
        .map-locate-btn svg { width: 16px; height: 16px; fill: #ff2a44; flex-shrink: 0; }

        /* No-map right col: render info placeholder */
        .vi-no-map-right {
            background: linear-gradient(135deg, #fff5f6 0%, #f9fafb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }
        .vi-no-map-right p { color: #ff2a44; opacity: 0.5; font-weight: 600; font-size: 0.88rem; text-align: center; }

        /* â”€â”€ Responsive: tablet/mobile stacked â”€â”€ */
        @media (max-width: 860px) {
            .vi-profile-card {
                grid-template-columns: 1fr;
            }
            .vi-left-col { border-right: none; border-bottom: 1px solid #f5eaec; }
            .vi-right-col { min-height: 260px; }
            #vendorInfoMap { min-height: 260px; height: 260px; }
        }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           MENU SECTION
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .vi-menu-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 22px;
        }
        .vi-menu-header-line {
            flex: 1;
            height: 1.5px;
            background: linear-gradient(to right, #e0e0e0, transparent);
        }
        .vi-menu-header-line.right {
            background: linear-gradient(to left, #e0e0e0, transparent);
        }
        .vi-menu-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #1e1e1e;
            margin: 0;
            white-space: nowrap;
        }

        /* Responsive food grid */
        .vi-food-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }
        @media (max-width: 1100px) {
            .vi-food-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 760px) {
            .vi-food-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .vi-food-grid { grid-template-columns: 1fr; }
        }

        /* Food card */
        .vi-food-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 3px 14px rgba(193,18,31,0.07);
            display: flex;
            flex-direction: column;
            transition: transform 0.22s, box-shadow 0.22s;
        }
        .vi-food-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 28px rgba(193,18,31,0.13);
        }
        .vi-food-img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            display: block;
        }
        .vi-food-img-placeholder {
            width: 100%;
            height: 160px;
            background: linear-gradient(135deg, #f9fafb 0%, #ffd0d8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .vi-food-img-placeholder svg { width: 36px; height: 36px; fill: #ff2a44; opacity: 0.3; }

        .vi-food-body {
            padding: 14px 14px 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
            gap: 6px;
        }
        .vi-food-name {
            font-size: 0.97rem;
            font-weight: 700;
            color: #1e1e1e;
            margin: 0;
            line-height: 1.3;
        }
        .vi-food-price {
            font-size: 1.05rem;
            font-weight: 800;
            color: #ff2a44;
            margin: 0;
        }
        .vi-food-tag {
            display: inline-block;
            background: #f9fafb;
            color: #9f0f1c;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            width: fit-content;
        }
        .vi-food-desc {
            font-size: 0.8rem;
            color: #999;
            margin: 0;
            line-height: 1.45;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }
        .vi-food-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 10px;
        }
        .vi-qty-stepper {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #fdf5f6;
            border: 1px solid #f0e0e3;
            border-radius: 10px;
            padding: 4px 10px;
            width: fit-content;
        }
        .vi-qty-stepper .qty-btn {
            width: 28px; height: 28px;
            border: none;
            background: transparent;
            color: #ff2a44;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            border-radius: 6px;
            line-height: 1;
            transition: background 0.15s;
        }
        .vi-qty-stepper .qty-btn:hover { background: #ffe0e5; }
        .vi-qty-stepper input {
            width: 36px;
            text-align: center;
            border: none;
            background: transparent;
            font-size: 14px;
            font-weight: 700;
            color: #1e1e1e;
            outline: none;
            padding: 0;
            margin: 0;
        }
        .vi-add-btn {
            background: #ff2a44;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 10px 0;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 3px 10px rgba(193,18,31,0.22);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .vi-add-btn:hover { background: #e01e38; box-shadow: 0 5px 16px rgba(193,18,31,0.32); }

        /* Empty menu state */
        .vi-empty-menu {
            text-align: center;
            padding: 60px 20px;
            color: #ccc;
        }
        .vi-empty-menu svg { width: 52px; height: 52px; fill: #e8c5ca; margin-bottom: 14px; }
        .vi-empty-menu p { font-size: 1rem; font-weight: 600; color: #bbb; margin: 0; }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           TOAST &mdash; top-right
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .cart-toast {
            position: fixed;
            top: 20px;
            right: 24px;
            background: #1a5c35;
            color: #fff;
            padding: 13px 18px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.88rem;
            box-shadow: 0 6px 24px rgba(26,92,53,0.28);
            z-index: 10001;
            /* Hidden by default &mdash; display:none prevents phantom height */
            display: none;
            align-items: center;
            gap: 12px;
            max-width: 320px;
            width: max-content;
            white-space: nowrap;
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity 0.28s ease, transform 0.28s ease;
            pointer-events: none;
        }
        /* Only show as flex when .show is added via JS */
        .cart-toast.show {
            display: flex;
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .cart-toast.toast-error { background: #9e1020; box-shadow: 0 6px 24px rgba(158,16,32,0.28); }
        .toast-undo {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.75);
            text-decoration: underline;
            cursor: pointer;
            white-space: nowrap;
            background: none;
            border: none;
            padding: 0;
            font-family: inherit;
            transition: color 0.15s;
        }
        .toast-undo:hover { color: #fff; }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           PENDING ORDER PILL &mdash; fixed bottom-center
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .pending-pill {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            background: #ffffff;
            border: 1.5px solid #e63946;
            border-radius: 999px;
            padding: 11px 20px 11px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 32px rgba(193,18,31,0.2), 0 2px 8px rgba(0,0,0,0.08);
            text-decoration: none;
            white-space: nowrap;
            transition: transform 0.2s, box-shadow 0.2s;
            animation: pill-float 3s ease-in-out infinite;
        }
        .pending-pill:hover {
            transform: translateX(-50%) translateY(-3px);
            box-shadow: 0 12px 40px rgba(193,18,31,0.28), 0 4px 12px rgba(0,0,0,0.1);
        }
        .pending-pill-icon { color: #ff2a44; display: flex; align-items: center; }
        .pending-pill-text { font-size: 0.9rem; font-weight: 600; color: #1e1e1e; }
        .pending-pill-chevron { color: #ff2a44; display: flex; align-items: center; }
        @keyframes pill-float {
            0%,100% { box-shadow: 0 8px 32px rgba(193,18,31,0.2), 0 2px 8px rgba(0,0,0,0.08); }
            50%      { box-shadow: 0 12px 40px rgba(193,18,31,0.26), 0 4px 14px rgba(0,0,0,0.1); }
        }

        /* â”€â”€ Misc overrides â”€â”€ */
        .navbar h2 { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>

    <!-- â•â•â•â•â•â• NAVBAR â•â•â•â•â•â• -->
    <?php include('header.php'); ?>

    <div class="vi-page-wrap">

        <!-- â•â•â•â•â•â• BREADCRUMB (desktop/tablet) â•â•â•â•â•â• -->
        <nav class="vi-breadcrumb" aria-label="Breadcrumb">
            <a href="Homepage.php">Home</a>
            <span class="vi-breadcrumb-sep">&rsaquo;</span>
            <a href="Homepage.php?search_submitted=1&search=">Restaurants</a>
            <span class="vi-breadcrumb-sep">&rsaquo;</span>
            <span class="vi-breadcrumb-current"><?php echo $shopName; ?></span>
        </nav>

        <!-- â•â•â•â•â•â• BACK LINK (mobile only) â•â•â•â•â•â• -->
        <a href="javascript:history.back()" class="vi-back-link" aria-label="Go back">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
            </svg>
            Back
        </a>

        <!-- â•â•â•â•â•â• PROFILE CARD â•â•â•â•â•â• -->
        <div class="vi-profile-card">

            <!-- LEFT: Info column -->
            <div class="vi-left-col">
                <!-- Cover image -->
                <?php if (!empty($vendor['Image'])): ?>
                    <img src="<?php echo htmlspecialchars($vendor['Image']); ?>"
                         alt="<?php echo $shopName; ?>"
                         class="vi-cover-img">
                <?php else: ?>
                    <div class="vi-cover-placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M20 4H4v2l16-.01V4zm1 5v-2l-1-2H4L3 7v2h1c0 1.1.9 2 2 2s2-.9 2-2h2c0 1.1.9 2 2 2s2-.9 2-2h2c0 1.1.9 2 2 2s2-.9 2-2h1zm-9 11H8v-4h4v4zm7 0h-5v-5H7v5H4v-7c-.58-.34-1-.97-1-1.71V9h18v2.29c0 .74-.42 1.37-1 1.71V20z"/>
                        </svg>
                    </div>
                <?php endif; ?>

                <!-- Info body -->
                <div class="vi-info-body">
                    <h1 class="vi-shop-name"><?php echo $shopName; ?></h1>

                    <div class="vi-meta-list">
                        <?php if (!empty($vendor['Location'])): ?>
                        <div class="vi-meta-row">
                            <!-- Pin icon -->
                            <svg class="vi-meta-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            <div>
                                <span class="vi-meta-label">Location</span>
                                <span class="vi-meta-value"><?php echo htmlspecialchars((string)$vendor['Location']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($vendor['OpenHours'])): ?>
                        <div class="vi-meta-row">
                            <!-- Clock icon -->
                            <svg class="vi-meta-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
                            </svg>
                            <div>
                                <span class="vi-meta-label">Open Hours</span>
                                <span class="vi-meta-value"><?php echo htmlspecialchars((string)$vendor['OpenHours']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($vendor['FoodType'])): ?>
                        <div class="vi-meta-row">
                            <!-- Fork/knife icon -->
                            <svg class="vi-meta-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M18.06 22.99h1.66c.84 0 1.53-.64 1.63-1.46L23 5.05h-5V1h-1.97v4.05h-4.97l.3 2.34c1.71.47 3.31 1.32 4.27 2.26 1.44 1.42 2.43 2.89 2.43 5.29v8.05zM1 21.99V21h15.03v.99c0 .55-.45 1-1.01 1H2.01c-.56 0-1.01-.45-1.01-1zm15.03-7c0-2.32-2.32-4-5.46-4-3.16 0-5.57 1.68-5.57 4v1H16.03v-1z"/>
                            </svg>
                            <div>
                                <span class="vi-meta-label">Cuisine</span>
                                <span class="vi-meta-value"><?php echo htmlspecialchars((string)$vendor['FoodType']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($vendor['Description'])): ?>
                    <p class="vi-desc"><?php echo nl2br(htmlspecialchars((string)$vendor['Description'])); ?></p>
                    <?php endif; ?>

                    <?php if ($hasMap): ?>
                    <!-- Route info block (hidden until route is fetched) -->
                    <div class="vi-route-info" id="routeInfoBlock" aria-live="polite">
                        <div class="vi-route-stat">
                            <span class="vi-route-stat-label">Distance</span>
                            <span class="vi-route-stat-value" id="routeDistance">&ndash;</span>
                        </div>
                        <div class="vi-route-stat">
                            <span class="vi-route-stat-label">ETA</span>
                            <span class="vi-route-stat-value" id="routeETA">&ndash;</span>
                        </div>
                    </div>
                    <div class="vi-route-info-error" id="routeErrorBlock"></div>

                    <!-- Show Route / Open in Maps App button -->
                    <button type="button" class="vi-navigate-btn" id="showRouteBtn"
                            onclick="handleRouteBtn()"
                            data-dest-lat="<?php echo (float)$vendor['Latitude']; ?>"
                            data-dest-lng="<?php echo (float)$vendor['Longitude']; ?>">
                        <!-- Route icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" id="routeBtnIcon">
                            <path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 2-8 2S9 5 8 8c0 0 3 1 4 6 0 0 2-3 4-3 0 0 1 4-2 5 0 0 3-1 4-4 1-3 0-8-3-8z"/>
                        </svg>
                        <span id="routeBtnLabel">Show Route</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: Map column -->
            <div class="vi-right-col">
                <?php if ($hasMap): ?>
                    <div id="vendorInfoMap"></div>
                    <button class="map-locate-btn" id="btnLocateMeMap" type="button" aria-label="Center on my location">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                        </svg>
                        My Location
                    </button>
                <?php else: ?>
                    <div class="vi-no-map-right">
                        <p>&#128205; Location map not available</p>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /.vi-profile-card -->

        <!-- â•â•â•â•â•â• MENU SECTION â•â•â•â•â•â• -->
        <div class="vi-menu-header">
            <div class="vi-menu-header-line"></div>
            <h2 class="vi-menu-title">Menu</h2>
            <div class="vi-menu-header-line right"></div>
        </div>


        <?php if (count($foodItems) > 0): ?>
        <div class="vi-food-grid">
            <?php foreach ($foodItems as $row): ?>
            <div class="vi-food-card">
                <!-- Image -->
                <?php if (!empty($row['Image'])): ?>
                    <img src="<?php echo htmlspecialchars($row['Image']); ?>"
                         alt="<?php echo htmlspecialchars($row['FoodName']); ?>"
                         class="vi-food-img">
                <?php else: ?>
                    <div class="vi-food-img-placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M18.06 22.99h1.66c.84 0 1.53-.64 1.63-1.46L23 5.05h-5V1h-1.97v4.05h-4.97l.3 2.34c1.71.47 3.31 1.32 4.27 2.26 1.44 1.42 2.43 2.89 2.43 5.29v8.05zM1 21.99V21h15.03v.99c0 .55-.45 1-1.01 1H2.01c-.56 0-1.01-.45-1.01-1zm15.03-7c0-2.32-2.32-4-5.46-4-3.16 0-5.57 1.68-5.57 4v1H16.03v-1z"/>
                        </svg>
                    </div>
                <?php endif; ?>

                <!-- Body -->
                <div class="vi-food-body">
                    <h3 class="vi-food-name"><?php echo htmlspecialchars($row['FoodName']); ?></h3>
                    <p class="vi-food-price">RM <?php echo number_format($row['Price'], 2); ?></p>
                    <?php if (!empty($row['DietaryTag'])): ?>
                        <span class="vi-food-tag"><?php echo htmlspecialchars($row['DietaryTag']); ?></span>
                    <?php endif; ?>
                    <p class="vi-food-desc"><?php echo htmlspecialchars($row['Description']); ?></p>

                    <div class="vi-food-actions">
                        <div class="vi-qty-stepper" data-qty-stepper>
                            <button type="button" class="qty-btn" data-qty-action="decrease" aria-label="Decrease">âˆ’</button>
                            <input type="number" min="1" value="1" data-qty-input
                                   id="vq-<?php echo $row['FoodID']; ?>" aria-label="Quantity">
                            <button type="button" class="qty-btn" data-qty-action="increase" aria-label="Increase">+</button>
                        </div>
                        <button type="button" class="vi-add-btn"
                                onclick="addToCart(<?php echo $row['FoodID']; ?>, document.getElementById('vq-<?php echo $row['FoodID']; ?>').value)"
                                id="atc-<?php echo $row['FoodID']; ?>">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="vi-empty-menu">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M18.06 22.99h1.66c.84 0 1.53-.64 1.63-1.46L23 5.05h-5V1h-1.97v4.05h-4.97l.3 2.34c1.71.47 3.31 1.32 4.27 2.26 1.44 1.42 2.43 2.89 2.43 5.29v8.05zM1 21.99V21h15.03v.99c0 .55-.45 1-1.01 1H2.01c-.56 0-1.01-.45-1.01-1zm15.03-7c0-2.32-2.32-4-5.46-4-3.16 0-5.57 1.68-5.57 4v1H16.03v-1z"/>
            </svg>
            <p>No menu items available right now.</p>
        </div>
        <?php endif; ?>

    </div><!-- /.vi-page-wrap -->

    <!-- â•â•â•â•â•â• QTY STEPPERS â•â•â•â•â•â• -->
    <?php if ($hasMap): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <?php endif; ?>

    <script>
document.addEventListener('DOMContentLoaded', function() {

    /* â”€â”€ Navbar active link â”€â”€ */
    var page = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
    if (page === '' || page === 'index.php') page = 'homepage.php';
    document.querySelectorAll('.nav-links a').forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === page) link.classList.add('active');
    });

    /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       QTY STEPPERS
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    document.querySelectorAll('[data-qty-stepper]').forEach(function(stepper) {
        var input = stepper.querySelector('[data-qty-input]');
        stepper.querySelectorAll('.qty-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var val = parseInt(input.value) || 1;
                if (btn.getAttribute('data-qty-action') === 'increase') {
                    input.value = val + 1;
                } else {
                    input.value = Math.max(1, val - 1);
                }
            });
        });
    });

    /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       ADD TO CART (AJAX)
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    window.addToCart = function(foodId, qty) {
        qty = parseInt(qty) || 1;
        var vendorId = <?php echo $vendorId; ?>;
        var orderType = 'Dine-In'; // default

        var formData = new FormData();
        formData.append('action', 'add');
        formData.append('food_id', foodId);
        formData.append('quantity', qty);
        formData.append('vendor_id', vendorId);
        formData.append('order_type', orderType);

        fetch('CartActions.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast(data.message || 'Added to cart!');
                    // â”€â”€ Update navbar cart badge live â”€â”€
                    var badge = document.getElementById('navCartBadge');
                    if (badge) {
                        var count = data.cart_count || 0;
                        badge.textContent = count;
                        if (count > 0) {
                            badge.classList.remove('hidden');
                            // Re-trigger pop animation
                            badge.style.animation = 'none';
                            badge.offsetWidth; // force reflow
                            badge.style.animation = '';
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                } else {
                    showToast(data.message || 'Failed to add to cart.', true);
                }
            })
            .catch(function() {
                showToast('Network error.', true);
            });
    };

    /* â”€â”€ Toast â”€â”€ */
    var toastTimer = null;
    function showToast(msg, isError) {
        var toast = document.querySelector('.cart-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'cart-toast';
            document.body.appendChild(toast);
        }
        clearTimeout(toastTimer);
        toast.classList.remove('show', 'toast-error');
        toast.textContent = msg;
        if (isError) toast.classList.add('toast-error');
        // Force reflow
        void toast.offsetWidth;
        toast.classList.add('show');
        toastTimer = setTimeout(function() {
            toast.classList.remove('show');
        }, 3000);
    }

    /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       VENDOR INFO MAP
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    var mapEl = document.getElementById('vendorInfoMap');
    if (!mapEl) return; // no map on page &mdash; map code below requires Leaflet

    var vendorLat = <?php echo $hasMap ? (float)$vendor['Latitude'] : 0; ?>;
    var vendorLng = <?php echo $hasMap ? (float)$vendor['Longitude'] : 0; ?>;
    var shopName  = <?php echo json_encode($vendor['ShopName'] ?? ''); ?>;

    var map = L.map('vendorInfoMap', { zoomControl: true }).setView([vendorLat, vendorLng], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    setTimeout(function() { map.invalidateSize(); }, 200);
    setTimeout(function() { map.invalidateSize(); }, 600);

    /* â”€â”€ Vendor marker â”€â”€ */
    var vendorIcon = L.divIcon({
        className: '',
        html: '<div style="width:30px;height:30px;background:#ff2a44;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 10px rgba(193,18,31,0.4);"></div>',
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -18]
    });

    var vendorMarker = L.marker([vendorLat, vendorLng], { icon: vendorIcon }).addTo(map);
    vendorMarker.bindPopup('<strong style="font-family:Inter,sans-serif;">' + escapeHtml(shopName) + '</strong>').openPopup();

    /* â”€â”€ User location â”€â”€ */
    var userIcon = L.divIcon({
        className: '',
        html: '<div style="width:16px;height:16px;background:#3b82f6;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(59,130,246,0.5);"></div>',
        iconSize: [16, 16],
        iconAnchor: [8, 8]
    });

    var userMarker = null;
    var userLat = null, userLng = null;
    var routeLine = null;

    function locateMe() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(function(pos) {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            if (userMarker) {
                userMarker.setLatLng([userLat, userLng]);
            } else {
                userMarker = L.marker([userLat, userLng], { icon: userIcon }).addTo(map);
                userMarker.bindPopup('<strong style="font-family:Inter,sans-serif;font-size:0.85rem;">&#128205; You</strong>');
            }
            var bounds = L.latLngBounds([
                [vendorLat, vendorLng],
                [userLat, userLng]
            ]);
            map.fitBounds(bounds.pad(0.2));
        }, function() {}, { enableHighAccuracy: true, timeout: 8000 });
    }

    var locateBtn = document.getElementById('btnLocateMeMap');
    if (locateBtn) locateBtn.addEventListener('click', locateMe);

    /* Auto-locate on load */
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            if (!userMarker) {
                userMarker = L.marker([userLat, userLng], { icon: userIcon }).addTo(map);
                userMarker.bindPopup('<strong style="font-family:Inter,sans-serif;font-size:0.85rem;">&#128205; You</strong>');
            }
        }, function() {}, { enableHighAccuracy: true, timeout: 8000 });
    }

    /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       SHOW ROUTE (OSRM free routing)
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    var routeShown = false;

    window.handleRouteBtn = function() {
        var btn = document.getElementById('showRouteBtn');
        var label = document.getElementById('routeBtnLabel');
        var icon = document.getElementById('routeBtnIcon');

        if (routeShown) {
            // Open in Google Maps
            var url = 'https://www.google.com/maps/dir/?api=1&destination=' + vendorLat + ',' + vendorLng;
            if (userLat !== null) url += '&origin=' + userLat + ',' + userLng;
            window.open(url, '_blank');
            return;
        }

        if (userLat === null || userLng === null) {
            // Try to get location first
            label.textContent = 'Locating...';
            btn.disabled = true;
            navigator.geolocation.getCurrentPosition(function(pos) {
                userLat = pos.coords.latitude;
                userLng = pos.coords.longitude;
                if (!userMarker) {
                    userMarker = L.marker([userLat, userLng], { icon: userIcon }).addTo(map);
                }
                fetchRoute();
            }, function() {
                showRouteError('Could not get your location. Please enable location access.');
                label.textContent = 'Show Route';
                btn.disabled = false;
            }, { enableHighAccuracy: true, timeout: 8000 });
            return;
        }

        fetchRoute();
    };

    function fetchRoute() {
        var btn = document.getElementById('showRouteBtn');
        var label = document.getElementById('routeBtnLabel');
        btn.disabled = true;
        label.textContent = 'Loading...';

        var url = 'https://router.project-osrm.org/route/v1/driving/' +
                  userLng + ',' + userLat + ';' + vendorLng + ',' + vendorLat +
                  '?overview=full&geometries=geojson';

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.code !== 'Ok' || !data.routes || !data.routes.length) {
                    showRouteError('Could not calculate route.');
                    label.textContent = 'Show Route';
                    btn.disabled = false;
                    return;
                }

                var route = data.routes[0];
                var distKm = (route.distance / 1000).toFixed(1);
                var durMin = Math.round(route.duration / 60);

                // Draw route line
                var coords = route.geometry.coordinates.map(function(c) { return [c[1], c[0]]; });
                if (routeLine) map.removeLayer(routeLine);
                routeLine = L.polyline(coords, {
                    color: '#1a73e8',
                    weight: 5,
                    opacity: 0.75
                }).addTo(map);
                map.fitBounds(routeLine.getBounds().pad(0.15));

                // Show info
                document.getElementById('routeDistance').textContent = distKm + ' km';
                document.getElementById('routeETA').textContent = durMin + ' min';
                document.getElementById('routeInfoBlock').classList.add('visible');
                document.getElementById('routeErrorBlock').classList.remove('visible');

                // Switch button to "Open in Maps"
                routeShown = true;
                label.textContent = 'Open in Maps';
                btn.classList.add('btn-maps-app');
                btn.disabled = false;
            })
            .catch(function() {
                showRouteError('Network error while fetching route.');
                label.textContent = 'Show Route';
                btn.disabled = false;
            });
    }

    function showRouteError(msg) {
        var el = document.getElementById('routeErrorBlock');
        el.textContent = msg;
        el.classList.add('visible');
        document.getElementById('routeInfoBlock').classList.remove('visible');
    }

    /* â”€â”€ Helper â”€â”€ */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }


});
</script>
</body>
</html>



