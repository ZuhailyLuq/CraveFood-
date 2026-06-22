<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';
include('recommendations.php');

$userId = isset($_SESSION['UserID']) ? (int)$_SESSION['UserID'] : 0;

// Initialize variables
$searchQuery = "";
$dietaryFilters = []; // multi-select array
$dietaryFilter  = "";  // legacy single value (kept for homepage dropdown)
$orderType = "Dine-In";
$pickupTime = "";
$userDietPreference = "";
$recommendProfile = [];
$recommendNote = "";
$recommendedFoods = [];

// Check if a search was submitted
$isSearch = isset($_GET['search_submitted']) && $_GET['search_submitted'] == '1';

// Base SQL query
$sql = 'SELECT mf."FoodID", mf."FoodName", mf."Price", mf."Description", mf."DietaryTag", mf."Category", mf."VendorID", v."ShopName", v."Image" AS "VendorImage", mf."Image" FROM menu_food mf LEFT JOIN vendor v ON mf."VendorID" = v."VendorID" WHERE mf."Status" = \'Available\'';
$params = [];

if ($isSearch) {
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $searchQuery = trim($_GET['search']);
        $sql .= ' AND (mf."FoodName" ILIKE ? OR mf."Description" ILIKE ? OR v."ShopName" ILIKE ?)';
        $params[] = '%' . $searchQuery . '%';
        $params[] = '%' . $searchQuery . '%';
        $params[] = '%' . $searchQuery . '%';
    }
    // Support both legacy single 'dietary' and new multi-select 'dietary_cb[]'
    if (!empty($_GET['dietary_cb']) && is_array($_GET['dietary_cb'])) {
        $dietaryFilters = $_GET['dietary_cb'];
        $placeholders = implode(',', array_fill(0, count($dietaryFilters), '?'));
        $sql .= ' AND mf."DietaryTag" IN (' . $placeholders . ')';
        foreach ($dietaryFilters as $tag) {
            $params[] = $tag;
        }
    } elseif (isset($_GET['dietary']) && !empty($_GET['dietary'])) {
        $dietaryFilter = trim($_GET['dietary']);
        $dietaryFilters = [$dietaryFilter];
        $sql .= ' AND mf."DietaryTag" = ?';
        $params[] = $dietaryFilter;
    }
    if (isset($_GET['order_type'])) {
        $orderType = trim($_GET['order_type']);
    }
    if (isset($_GET['pickup_time'])) {
        $pickupTime = trim($_GET['pickup_time']);
    }
}

// Pull user dietary preference
if ($userId > 0) {
    $prefRow = db_fetch_one($pdo, 'SELECT "DietaryPreference" FROM "user" WHERE "UserId" = ?', [$userId]);
    if ($prefRow) {
        $userDietPreference = trim((string)$prefRow['DietaryPreference']);
    }
}

if (!$isSearch) {
    $recommendProfile = buildRecommendationProfile($pdo, $userId, $userDietPreference);
    $recommendNote = getRecommendationNote($recommendProfile);
    $recommendedFoods = fetchRecommendedFoods($pdo, $recommendProfile);
}

$foodRows = db_fetch_all($pdo, $sql, $params);

// Process results for search (group by vendor)
$foodResults = [];
$vendorGroups = [];
if ($isSearch) {
    foreach ($foodRows as $row) {
        $foodResults[] = $row;
        $vid = $row['VendorID'];
        if (!isset($vendorGroups[$vid])) {
            $vendorGroups[$vid] = [
                'VendorID' => $vid,
                'ShopName' => $row['ShopName'],
                'VendorImage' => $row['VendorImage'],
                'FoodCount' => 0
            ];
        }
        $vendorGroups[$vid]['FoodCount']++;
    }

    recordSearchActivity($foodResults, $dietaryFilter);
}

$activeOrder = null;
if ($userId > 0) {
    $activeOrder = db_fetch_one($pdo,
        'SELECT "OrderID", "Status" FROM orders WHERE "UserID" = ? AND "Status" NOT IN (\'Finished\', \'Completed\', \'Cancelled\') ORDER BY "OrderID" DESC LIMIT 1',
        [$userId]
    );
}

// Cart count for floating badge
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['vendor_id' => null, 'vendor_name' => '', 'items' => []];
}
$cartCount = 0;
foreach ($_SESSION['cart']['items'] as $ci) {
    $cartCount += $ci['Quantity'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraveFood - Campus Food Discovery &amp; Exploration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <meta name="description" content="CraveFood - Discover nearby campus cafeterias and stalls, explore menus, and find dishes that fit your health and dietary preferences.">
    <style>
        /* â”€â”€ Inter font override â”€â”€ */
        *, body { box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: #ffffff; color: #2c3e50; line-height: 1.5; padding-bottom: 80px; margin: 0; }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           COLORS & UTILITIES (Eatigo DNA)
           Accents: `#ff2a44` (Coral Red)
         â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        :root {
            --primary: #ff2a44;
        }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           HOMEPAGE HERO & MASCOT
         â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .home-hero-wrap {
            max-width: 1120px;
            margin: 32px auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
        }
        .home-hero-content {
            flex: 1;
            text-align: left;
        }
        .hero-title {
            font-size: 2.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0 0 10px 0;
            letter-spacing: -0.5px;
            line-height: 1.25;
        }
        .hero-title span {
            color: var(--primary);
        }
        .hero-subtitle {
            font-size: 1.1rem;
            color: var(--text-muted);
            margin: 0 0 32px 0;
            font-weight: 400;
        }

        /* Cute SVG Mascot container */
        .hero-mascot {
            width: 240px;
            height: 160px;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        @media (max-width: 768px) {
            .home-hero-wrap { flex-direction: column; text-align: center; gap: 20px; margin: 20px auto; }
            .home-hero-content { text-align: center; }
            .hero-title { font-size: 1.8rem; }
            .hero-mascot { display: none; }
        }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           SEARCH BAR (Eatigo Pill Style)
         â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .quick-search-shell {
            display: flex;
            align-items: stretch;
            background: #ffffff;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            margin-bottom: 20px;
        }
        .quick-segment {
            display: flex;
            flex-direction: row;
            align-items: center;
            padding: 12px 18px;
            border-right: 1px solid #efefef;
            background: #ffffff;
            flex: 1;
            gap: 10px;
        }
        .quick-segment svg {
            width: 18px;
            height: 18px;
            fill: var(--text-muted);
            stroke: var(--text-muted);
            stroke-width: 0;
            flex-shrink: 0;
        }
        .quick-segment svg[stroke="currentColor"] {
            fill: none;
            stroke-width: 2px;
        }
        .quick-segment-inner {
            display: flex;
            flex-direction: column;
            width: 100%;
            text-align: left;
        }
        .quick-segment-inner label {
            font-size: 10px;
            color: var(--text-muted);
            margin-bottom: 2px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .quick-segment select,
        .quick-segment input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 14px;
            color: var(--text-dark);
            background: transparent;
            padding: 2px 0;
            font-weight: 600;
            cursor: pointer;
        }
        .quick-search-input {
            flex: 1.5;
        }
        .quick-search-btn {
            width: 60px;
            height: auto;
            align-self: stretch;
            border: none;
            background: var(--primary);
            color: #ffffff;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .quick-search-btn svg {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: #ffffff;
            stroke-width: 2.5px;
        }
        .quick-search-btn:hover {
            background: var(--primary-hover);
        }
        @media (max-width: 768px) {
            .quick-search-shell { flex-direction: column; }
            .quick-segment { border-right: none; border-bottom: 1px solid #efefef; }
            .quick-search-btn { width: 100%; padding: 14px 0; }
        }
        .btn-advance-ghost {
            display: inline-block;
            background: #ffffff;
            color: var(--primary);
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            padding: 10px 20px;
            border: 2px solid var(--primary);
            border-radius: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(255,42,68,0.1);
        }
        .btn-advance-ghost:hover {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(255,42,68,0.2);
            transform: translateY(-1px);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0 0 16px 0;
            text-align: left;
        }



        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           POPULAR / RECOMMENDED FOODS
         â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .recommendations-section {
            max-width: 1120px;
            margin: 0 auto 36px;
            padding: 0 24px;
        }
        .section-header-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 18px;
        }
        .section-title-v2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0;
        }
        .section-context-note {
            font-size: 0.84rem;
            color: var(--text-muted);
            margin: 0;
        }
        .rec-food-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .rec-food-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            position: relative;
            display: flex;
            flex-direction: column;
            transition: transform 0.22s, box-shadow 0.22s;
        }
        .rec-food-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        .card-img-wrap {
            position: relative;
            width: 100%;
            height: 150px;
            background: #f1f2f6;
        }
        .rec-card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .rec-card-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }
        .hot-badge {
            position: absolute;
            top: 10px; left: 10px;
            background: var(--primary);
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 3px;
            text-transform: uppercase;
        }

        /* Card Content */
        .rec-card-body {
            padding: 14px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .rec-card-shop {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 600;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .rec-card-name {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0 0 6px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }


        /* Price & Actions Row */
        .price-action-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid #f5f5f5;
        }
        .rec-card-price {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }
        .stepper-add-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Steppers */
        .qty-stepper {
            display: flex;
            align-items: center;
            background: #fcfcfc;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
        }
        .qty-stepper .qty-btn {
            width: 24px;
            height: 24px;
            border: none;
            background: transparent;
            color: var(--primary);
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qty-stepper .qty-btn:hover { background: #ffeef1; }
        .qty-stepper .qty-input-stepper {
            width: 28px;
            border: none;
            background: transparent;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-dark);
            outline: none;
            padding: 0;
        }
        
        .rec-add-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }
        .rec-add-btn:hover {
            background: var(--primary-hover);
        }
        .rec-add-btn svg {
            width: 16px;
            height: 16px;
            fill: #ffffff;
        }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           SEARCH RESULTS LAYOUT
         â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .breadcrumb {
            max-width: 1120px;
            margin: 16px auto 0;
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .breadcrumb a { color: var(--text-muted); text-decoration: none; transition: color 0.15s; }
        .breadcrumb a:hover { color: var(--primary); }
        .breadcrumb-sep { color: #ccc; }
        .breadcrumb-current { color: var(--text-dark); }

        .search-results-bar-wrap {
            max-width: 1120px;
            margin: 16px auto;
            padding: 0 24px;
        }

        .sr-layout {
            max-width: 1120px;
            margin: 0 auto;
            padding: 0 24px 40px;
            display: flex;
            gap: 24px;
            align-items: flex-start;
        }

        /* Sidebar Filter Card */
        .sr-sidebar {
            width: 240px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            position: sticky;
            top: 80px;
            flex-shrink: 0;
        }
        .sr-sidebar-title {
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-dark);
            margin: 0 0 16px 0;
        }
        .sr-filter-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-dark);
            transition: background 0.15s;
            margin-bottom: 4px;
            user-select: none;
        }
        .sr-filter-label:hover { background: #fff0f2; color: var(--primary); }
        .sr-filter-label input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            border: 2px solid #ccc;
            border-radius: 4px;
            cursor: pointer;
            flex-shrink: 0;
            position: relative;
            transition: border-color 0.15s, background 0.15s;
        }
        .sr-filter-label input[type="checkbox"]:checked {
            background: var(--primary);
            border-color: var(--primary);
        }
        .sr-filter-label input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            top: 1px; left: 4px;
            width: 4px; height: 8px;
            border: 2px solid #ffffff;
            border-top: none;
            border-left: none;
            transform: rotate(45deg);
        }
        .sr-filter-label.checked { background: #fff2f3; color: var(--primary); }
        
        .sr-clear-link {
            display: block;
            text-align: center;
            margin-top: 14px;
            font-size: 0.8rem;
            color: var(--text-muted);
            text-decoration: underline;
        }
        .sr-clear-link:hover { color: var(--primary); }
        .sr-divider { border: none; border-top: 1px solid #f0f0f0; margin: 16px 0; }

        .sr-main { flex: 1; min-width: 0; }
        .sr-result-meta { margin-bottom: 16px; }
        .sr-count { font-size: 0.9rem; color: var(--text-muted); margin: 0; font-weight: 500; }
        .sr-count strong { color: var(--text-dark); }

        /* Horizontal Card in Search Results */
        .fi-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            display: flex;
            gap: 20px;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .fi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.06); }
        .fi-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 6px;
            flex-shrink: 0;
        }
        .fi-img-placeholder {
            width: 100px; height: 100px;
            background: #f5f6f8;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); flex-shrink: 0;
        }
        .fi-info { flex: 1; min-width: 0; }
        .fi-name { font-size: 1.05rem; font-weight: 800; color: var(--text-dark); margin: 0 0 4px 0; }
        .fi-vendor { font-size: 0.8rem; color: var(--text-muted); margin: 0 0 8px 0; }
        .fi-vendor a { color: var(--primary); font-weight: 700; text-decoration: none; }
        .fi-vendor a:hover { text-decoration: underline; }
        .fi-tag {
            display: inline-block;
            background: #fff2f3;
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .fi-desc {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin: 0 0 8px 0;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .fi-price { font-size: 1.1rem; font-weight: 800; color: var(--primary); margin: 0; }
        
        .fi-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 12px;
            flex-shrink: 0;
        }
        .fi-stepper {
            display: flex;
            align-items: center;
            background: #fafafa;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
        }
        .fi-stepper .qty-btn {
            width: 28px; height: 28px;
            border: none;
            background: transparent;
            color: var(--primary);
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .fi-stepper .qty-btn:hover { background: #ffeef1; }
        .fi-stepper .qty-input-stepper {
            width: 32px;
            border: none;
            background: transparent;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            outline: none;
            padding: 0;
        }
        .fi-add-btn {
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 10px 18px;
            font-size: 0.85rem;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.15s;
        }
        .fi-add-btn:hover { background: var(--primary-hover); }

        .sr-empty { text-align: center; padding: 64px 20px; color: var(--text-muted); }
        .sr-empty svg { width: 48px; height: 48px; fill: var(--border-color); margin-bottom: 12px; }
        .sr-empty p { font-size: 1rem; font-weight: 700; color: var(--text-dark); margin: 0 0 4px 0; }
        .sr-empty span { font-size: 0.82rem; }



        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           TOAST NOTIFICATION
         â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .cart-toast {
            position: fixed;
            top: 24px;
            right: 24px;
            background: #27ae60;
            color: #ffffff;
            padding: 14px 20px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.88rem;
            box-shadow: 0 4px 16px rgba(39,174,96,0.3);
            z-index: 10001;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s, transform 0.3s;
            pointer-events: none;
        }
        .cart-toast.show { opacity: 1; transform: translateY(0); pointer-events: auto; }
        .cart-toast.toast-error { background: #c0392b; box-shadow: 0 4px 16px rgba(192,57,43,0.3); }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           PENDING ORDER PILL
         â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .pending-pill {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            background: #ffffff;
            border: 1.5px solid var(--primary);
            border-radius: 999px;
            padding: 10px 18px 10px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(255,42,68,0.22);
            text-decoration: none;
            white-space: nowrap;
            transition: transform 0.2s;
        }
        .pending-pill:hover { transform: translateX(-50%) translateY(-2px); }
        .pending-pill-icon { color: var(--primary); display: flex; align-items: center; }
        .pending-pill-text { font-size: 0.88rem; font-weight: 700; color: var(--text-dark); }
        .pending-pill-chevron { color: var(--primary); display: flex; align-items: center; }



        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           RESPONSIVE
         â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        @media (max-width: 820px) {
            .sr-layout { flex-direction: column; padding: 0 16px 40px; }
            .sr-sidebar { width: 100%; position: static; box-shadow: none; margin-bottom: 20px; }
            .search-results-bar-wrap { padding: 0 16px; }
            .breadcrumb { padding: 0 16px; }
            .fi-card { flex-wrap: wrap; }
            .fi-actions { flex-direction: row; width: 100%; justify-content: flex-end; }
            .quick-search-shell { flex-direction: column; }
            .quick-segment { border-right: none; border-bottom: 1px solid #efefef; width: 100%; }
            .quick-search-btn { width: 100%; height: 50px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <?php
        $noticeMsg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
        $noticeType = isset($_GET['type']) ? $_GET['type'] : 'error';
    ?>
    <?php if ($noticeMsg !== ''): ?>
        <div class="notice show <?php echo ($noticeType === 'success') ? 'notice-success' : 'notice-error'; ?>">
            <?php echo htmlspecialchars($noticeMsg); ?>
        </div>
    <?php endif; ?>

    <?php include('header.php'); ?>

    <?php if (!$isSearch): ?>
        <!-- Hero section with mascot -->
        <div class="home-hero-wrap">
            <div class="home-hero-content">
                <h1 class="hero-title">CraveFood: <span>Discover &amp; Explore Campus Food!</span></h1>
                <p class="hero-subtitle">Find stalls, view menus, and discover meals matching your health and dietary preferences.</p>
                <form method="GET" action="Homepage.php" id="homeSearchForm">
                    <input type="hidden" name="search_submitted" value="1">
                    <div class="quick-search-shell">
                        <div class="quick-segment">
                            <!-- Dietary Tag Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                            <div class="quick-segment-inner">
                                <label for="homeDietary">Dietary</label>
                                <select name="dietary" id="homeDietary">
                                    <option value="">All Dietary</option>
                                    <option value="Halal" <?php if($dietaryFilter === 'Halal') echo 'selected'; ?>>Halal</option>
                                    <option value="Vegetarian" <?php if($dietaryFilter === 'Vegetarian') echo 'selected'; ?>>Vegetarian</option>
                                    <option value="Low Lactose" <?php if($dietaryFilter === 'Low Lactose') echo 'selected'; ?>>Low Lactose</option>
                                    <option value="Protein" <?php if($dietaryFilter === 'Protein') echo 'selected'; ?>>Protein</option>
                                    <option value="Fiber" <?php if($dietaryFilter === 'Fiber') echo 'selected'; ?>>Fiber</option>
                                </select>
                            </div>
                        </div>
                        <div class="quick-segment quick-search-input">
                            <!-- Search Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <div class="quick-segment-inner">
                                <label for="homeSearchText">Search</label>
                                <input type="text" id="homeSearchText" name="search" placeholder="Search cafeterias, stalls or dishes...">
                            </div>
                        </div>
                        <button type="submit" class="quick-search-btn" aria-label="Search">
                            <!-- Magnifying Glass Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </button>
                    </div>
                </form>
                <div class="primary-action-wrap" style="text-align: left; margin: 0;">
                    <a href="AdvanceSearch.php" class="btn-advance-ghost" id="advanceSearchBtn">Go to Advance Search</a>
                </div>
            </div>
            
            <!-- Mascot Character SVG -->
            <div class="hero-mascot">
                <svg viewBox="0 0 150 120" width="100%" height="100%">
                    <!-- Hearts floating above -->
                    <path d="M70 18 C68 13, 62 13, 62 18 C62 23, 70 28, 70 28 C70 28, 78 23, 78 18 C78 13, 72 13, 70 18" fill="#ff2a44" />
                    <path d="M85 24 C84 20, 79 20, 79 24 C79 28, 85 32, 85 32 C85 32, 91 28, 91 24 C91 20, 86 20, 85 24" fill="#ff2a44" transform="scale(0.8) translate(15, 3)" />
                    <!-- Orange body -->
                    <ellipse cx="75" cy="65" rx="45" ry="38" fill="#ff7f50" />
                    <ellipse cx="75" cy="65" rx="42" ry="35" fill="#ff9f43" />
                    <!-- Eyes -->
                    <circle cx="60" cy="55" r="7" fill="#ffffff" />
                    <circle cx="60" cy="55" r="3.5" fill="#000000" />
                    <circle cx="90" cy="55" r="7" fill="#ffffff" />
                    <circle cx="90" cy="55" r="3.5" fill="#000000" />
                    <!-- Mouth -->
                    <path d="M70 75 Q75 80 80 75" stroke="#000000" stroke-width="3" fill="none" stroke-linecap="round" />
                    <!-- Fork & spoon in hands -->
                    <!-- Left Hand & Fork -->
                    <path d="M30 65 Q20 50 25 40" stroke="#ff9f43" stroke-width="6" fill="none" stroke-linecap="round" />
                    <path d="M23 40 L27 40 L25 45 Z" fill="#7f8c8d" />
                    <line x1="21" y1="36" x2="21" y2="42" stroke="#7f8c8d" stroke-width="1.5" />
                    <line x1="25" y1="35" x2="25" y2="42" stroke="#7f8c8d" stroke-width="1.5" />
                    <line x1="29" y1="36" x2="29" y2="42" stroke="#7f8c8d" stroke-width="1.5" />
                    <!-- Right Hand & Spoon -->
                    <path d="M120 65 Q130 50 125 40" stroke="#ff9f43" stroke-width="6" fill="none" stroke-linecap="round" />
                    <ellipse cx="125" cy="38" rx="4" ry="6" fill="#7f8c8d" />
                    <line x1="125" y1="44" x2="125" y2="47" stroke="#7f8c8d" stroke-width="2" />
                    <!-- Feet -->
                    <line x1="60" y1="100" x2="60" y2="110" stroke="#000000" stroke-width="4" stroke-linecap="round" />
                    <line x1="90" y1="100" x2="90" y2="110" stroke="#000000" stroke-width="4" stroke-linecap="round" />
                </svg>
            </div>
        </div>




        <!-- Food Recommendations Section -->
        <div class="recommendations-section">
            <div class="section-header-row">
                <h2 class="section-title-v2">Explore Popular &amp; Recommendations</h2>
                <?php if ($recommendNote !== ""): ?>
                    <p class="section-context-note"><?php echo $recommendNote; ?></p>
                <?php endif; ?>
            </div>

            <div class="rec-food-grid">
                <?php 
                if (count($recommendedFoods) > 0) {
                    foreach($recommendedFoods as $row) {
                ?>
                    <div class="rec-food-card">
                        <div class="card-img-wrap">
                            <?php if(!empty($row['Image'])): ?>
                                <img src="<?php echo htmlspecialchars($row['Image']); ?>" alt="<?php echo htmlspecialchars($row['FoodName']); ?>" class="rec-card-img">
                            <?php else: ?>
                                <div class="rec-card-img-placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="#ff2a44" opacity="0.4"><path d="M18.06 22.99h1.66c.84 0 1.53-.64 1.63-1.46L23 5.05h-5V1h-1.97v4.05h-4.97l.3 2.34c1.71.47 3.31 1.32 4.27 2.26 1.44 1.42 2.43 2.89 2.43 5.29v8.05zM1 21.99V21h15.03v.99c0 .55-.45 1-1.01 1H2.01c-.56 0-1.01-.45-1.01-1zm15.03-7c0-2.32-2.32-4-5.46-4-3.16 0-5.57 1.68-5.57 4v1H16.03v-1z"/></svg>
                                </div>
                            <?php endif; ?>
                            <span class="hot-badge">Hot</span>
                        </div>
                        <div class="rec-card-body">
                            <?php if(!empty($row['ShopName'])): ?>
                                <p class="rec-card-shop">By <?php echo htmlspecialchars($row['ShopName']); ?></p>
                            <?php endif; ?>
                            <h3 class="rec-card-name"><?php echo htmlspecialchars($row['FoodName']); ?></h3>

                            
                            <div class="price-action-row">
                                <p class="rec-card-price">RM <?php echo number_format($row['Price'], 2); ?></p>
                                <div class="stepper-add-wrap">
                                    <div class="qty-stepper" data-qty-stepper>
                                        <button type="button" class="qty-btn" data-qty-action="decrease" aria-label="Decrease">&minus;</button>
                                        <input type="number" min="1" value="1" class="qty-input-stepper" data-qty-input id="rq-<?php echo $row['FoodID']; ?>" aria-label="Quantity">
                                        <button type="button" class="qty-btn" data-qty-action="increase" aria-label="Increase">+</button>
                                    </div>
                                    <button type="button" class="rec-add-btn" title="Add to cart" onclick="addToCart(<?php echo $row['FoodID']; ?>, document.getElementById('rq-<?php echo $row['FoodID']; ?>').value)" aria-label="Add <?php echo htmlspecialchars($row['FoodName']); ?> to cart">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php }} ?>
            </div>
        </div>



    <?php else: ?>

        <!-- â”€â”€ Breadcrumb â”€â”€ -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="Homepage.php">Home</a>
            <span class="breadcrumb-sep">&rsaquo;</span>
            <span class="breadcrumb-current">Search Results</span>
        </nav>

        <!-- â”€â”€ Compact unified search bar â”€â”€ -->
        <div class="search-results-bar-wrap">
            <form method="GET" action="Homepage.php" id="searchFilterForm">
                <input type="hidden" name="search_submitted" value="1">
                <!-- hidden fields for checked dietary filters are injected by JS -->
                <div class="quick-search-shell">
                    <div class="quick-segment">
                        <!-- Dietary Tag Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        <div class="quick-segment-inner">
                            <label for="searchDietary">Dietary</label>
                            <select name="dietary" id="searchDietary" onchange="document.getElementById('searchFilterForm').submit()">
                                <option value="">All Dietary</option>
                                <option value="Halal" <?php if($dietaryFilter === 'Halal') echo 'selected'; ?>>Halal</option>
                                <option value="Vegetarian" <?php if($dietaryFilter === 'Vegetarian') echo 'selected'; ?>>Vegetarian</option>
                                <option value="Low Lactose" <?php if($dietaryFilter === 'Low Lactose') echo 'selected'; ?>>Low Lactose</option>
                                <option value="Protein" <?php if($dietaryFilter === 'Protein') echo 'selected'; ?>>Protein</option>
                                <option value="Fiber" <?php if($dietaryFilter === 'Fiber') echo 'selected'; ?>>Fiber</option>
                            </select>
                        </div>
                    </div>
                    <div class="quick-segment quick-search-input">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <div class="quick-segment-inner">
                            <label for="searchText">Search</label>
                            <input type="text" id="searchText" name="search"
                                   placeholder="Search cafeterias, stalls or dishes..."
                                   value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                    </div>
                    <button type="submit" class="quick-search-btn" id="searchSubmitBtn" aria-label="Search">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </button>
                </div>
            </form>
        </div>

        <!-- â”€â”€ Two-column layout â”€â”€ -->
        <div class="sr-layout">

            <!-- SIDEBAR -->
            <aside class="sr-sidebar">
                <p class="sr-sidebar-title">Dietary Filters</p>
                <?php
                    $allTags = ['Halal','Vegetarian','Low Lactose','Protein','Fiber'];
                ?>
                <?php foreach($allTags as $tag): ?>
                    <?php $isChecked = in_array($tag, $dietaryFilters); ?>
                    <label class="sr-filter-label <?php echo $isChecked ? 'checked' : ''; ?>"
                           id="label-<?php echo htmlspecialchars(str_replace(' ','-',$tag)); ?>">
                        <input type="checkbox"
                               class="dietary-cb"
                               value="<?php echo htmlspecialchars($tag); ?>"
                               <?php echo $isChecked ? 'checked' : ''; ?>
                               onchange="applyDietaryFilters()">
                        <?php echo htmlspecialchars($tag); ?>
                    </label>
                <?php endforeach; ?>
                <hr class="sr-divider">
                <a href="?search_submitted=1&search=<?php echo urlencode($searchQuery); ?>&order_type=<?php echo urlencode($orderType); ?>" class="sr-clear-link">Clear filters</a>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="sr-main">
                <?php $count = count($foodResults); ?>
                <div class="sr-result-meta">
                    <p class="sr-count">Showing <strong><?php echo $count; ?></strong> result<?php echo $count !== 1 ? 's' : ''; ?>
                        <?php if (!empty($searchQuery)): ?> for &ldquo;<strong><?php echo htmlspecialchars($searchQuery); ?></strong>&rdquo;<?php endif; ?>
                    </p>
                </div>

                <?php if ($count > 0): ?>

                <!-- Restaurants Section -->
                <?php if (count($vendorGroups) > 0): ?>
                <div class="restaurant-section">
                    <h3 class="section-title" style="font-size: 1.15rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <!-- Store / shop icon -->
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px; fill: var(--primary);">
                            <path d="M20 4H4v2l16-.01V4zm1 5v-2l-1-2H4L3 7v2h1c0 1.1.9 2 2 2s2-.9 2-2h2c0 1.1.9 2 2 2s2-.9 2-2h2c0 1.1.9 2 2 2s2-.9 2-2h1zm-9 11H8v-4h4v4zm7 0h-5v-5H7v5H4v-7c-.58-.34-1-.97-1-1.71V9h18v2.29c0 .74-.42 1.37-1 1.71V20z"/>
                        </svg>
                        Cafeterias &amp; Stalls
                    </h3>
                    <div class="rec-food-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); margin-bottom: 24px;">
                        <?php foreach($vendorGroups as $vg): ?>
                        <a href="VendorInfo.php?vendor_id=<?php echo (int)$vg['VendorID']; ?>" class="rec-food-card" style="text-decoration: none; color: inherit;">
                            <div class="card-img-wrap" style="height: 100px;">
                                <?php if(!empty($vg['VendorImage'])): ?>
                                    <img src="<?php echo htmlspecialchars($vg['VendorImage']); ?>"
                                         alt="<?php echo htmlspecialchars($vg['ShopName']); ?>"
                                         class="rec-card-img">
                                <?php else: ?>
                                    <div class="rec-card-img-placeholder">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#ff2a44" opacity="0.4"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="rec-card-body" style="padding: 10px 12px;">
                                <div class="rec-card-name" style="font-size: 0.9rem; font-weight: 700; margin: 0 0 2px 0;"><?php echo htmlspecialchars($vg['ShopName']); ?></div>
                                <div class="rec-card-shop" style="font-size: 0.75rem; font-weight: 500; color: var(--text-muted); text-transform: none; letter-spacing: 0;"><?php echo $vg['FoodCount']; ?> matching item<?php echo $vg['FoodCount'] > 1 ? 's' : ''; ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <hr class="sr-divider" style="margin: 24px 0;">

                <!-- Food Items Section -->
                <h3 class="section-title" style="font-size: 1.15rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <!-- Fork & knife icon -->
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px; fill: var(--primary);">
                        <path d="M18.06 22.99h1.66c.84 0 1.53-.64 1.63-1.46L23 5.05h-5V1h-1.97v4.05h-4.97l.3 2.34c1.71.47 3.31 1.32 4.27 2.26 1.44 1.42 2.43 2.89 2.43 5.29v8.05zM1 21.99V21h15.03v.99c0 .55-.45 1-1.01 1H2.01c-.56 0-1.01-.45-1.01-1zm15.03-7c0-2.32-2.32-4-5.46-4-3.16 0-5.57 1.68-5.57 4v1H16.03v-1z"/>
                    </svg>
                    Explore All Menu Items
                </h3>

                <?php foreach($foodResults as $row): ?>
                <div class="fi-card">
                    <!-- Image -->
                    <?php if(!empty($row['Image'])): ?>
                        <img src="<?php echo htmlspecialchars($row['Image']); ?>"
                             alt="<?php echo htmlspecialchars($row['FoodName']); ?>"
                             class="fi-img">
                    <?php else: ?>
                        <div class="fi-img-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="#ff2a44" opacity="0.35"><path d="M18.06 22.99h1.66c.84 0 1.53-.64 1.63-1.46L23 5.05h-5V1h-1.97v4.05h-4.97l.3 2.34c1.71.47 3.31 1.32 4.27 2.26 1.44 1.42 2.43 2.89 2.43 5.29v8.05zM1 21.99V21h15.03v.99c0 .55-.45 1-1.01 1H2.01c-.56 0-1.01-.45-1.01-1zm15.03-7c0-2.32-2.32-4-5.46-4-3.16 0-5.57 1.68-5.57 4v1H16.03v-1z"/></svg>
                        </div>
                    <?php endif; ?>

                    <!-- Info -->
                    <div class="fi-info">
                        <h3 class="fi-name"><?php echo htmlspecialchars($row['FoodName']); ?></h3>
                        <?php if(!empty($row['ShopName'])): ?>
                            <p class="fi-vendor">by
                                <a href="VendorInfo.php?vendor_id=<?php echo (int)$row['VendorID']; ?>">
                                    <?php echo htmlspecialchars($row['ShopName']); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        <?php if(!empty($row['DietaryTag'])): ?>
                            <span class="fi-tag"><?php echo htmlspecialchars($row['DietaryTag']); ?></span>
                        <?php endif; ?>
                        <p class="fi-desc"><?php echo htmlspecialchars($row['Description']); ?></p>
                        <p class="fi-price">RM <?php echo number_format($row['Price'], 2); ?></p>
                    </div>

                    <!-- Actions -->
                    <div class="fi-actions">
                        <div class="fi-stepper" data-qty-stepper>
                            <button type="button" class="qty-btn" data-qty-action="decrease" aria-label="Decrease">&minus;</button>
                            <input type="number" min="1" value="1"
                                   class="qty-input-stepper" data-qty-input
                                   id="sq-<?php echo $row['FoodID']; ?>" aria-label="Quantity">
                            <button type="button" class="qty-btn" data-qty-action="increase" aria-label="Increase">+</button>
                        </div>
                        <button type="button" class="fi-add-btn"
                                onclick="addToCart(<?php echo $row['FoodID']; ?>, document.getElementById('sq-<?php echo $row['FoodID']; ?>').value)"
                                id="atc-<?php echo $row['FoodID']; ?>">
                            Add to Cart
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php else: ?>
                <div class="sr-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <p>No results found</p>
                    <span>Try a different keyword or remove some filters</span>
                </div>
                <?php endif; ?>
            </main>

        </div><!-- /.sr-layout -->

    <?php endif; ?>



    <!-- Floating pending order pill -->
    <?php if ($activeOrder): ?>
        <a href="OrderStatus.php?order_id=<?php echo (int)$activeOrder['OrderID']; ?>" class="pending-pill">
            <span class="pending-pill-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </span>
            <span class="pending-pill-text">Active Order #<?php echo $activeOrder['OrderID']; ?> (<?php echo htmlspecialchars($activeOrder['Status']); ?>)</span>
            <span class="pending-pill-chevron">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </span>
        </a>
    <?php endif; ?>

    <script>
document.addEventListener('DOMContentLoaded', function() {

    /* â”€â”€ Navbar active link â”€â”€ */
    var page = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
    if (page === '' || page === 'index.php') page = 'homepage.php';
    document.querySelectorAll('.nav-links a').forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === page || (page.startsWith('advancesearch') && href === 'homepage.php')) {
            link.classList.add('active');
        }
    });

    /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       QTY STEPPERS
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    document.querySelectorAll('[data-qty-stepper]').forEach(function(stepper) {
        var input = stepper.querySelector('[data-qty-input]');
        if (!input) return;
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
       TOAST HELPER
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    var toastTimer = null;
    function showToast(msg, isError) {
        var toast = document.getElementById('homeCartToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'homeCartToast';
            toast.className = 'cart-toast';
            document.body.appendChild(toast);
        }
        clearTimeout(toastTimer);
        toast.classList.remove('show', 'toast-error');
        toast.textContent = msg;
        if (isError) {
            toast.classList.add('toast-error');
        }
        void toast.offsetWidth; // force reflow
        toast.classList.add('show');
        toastTimer = setTimeout(function() { toast.classList.remove('show'); }, 3000);
    }

    /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       ADD TO CART (AJAX)
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    window.addToCart = function(foodId, qty) {
        qty = parseInt(qty) || 1;

        var formData = new FormData();
        formData.append('action', 'add');
        formData.append('food_id', foodId);
        formData.append('quantity', qty);

        fetch('CartActions.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast(data.message || 'Added to cart!', false);
                    /* â”€â”€ Update navbar cart badge live â”€â”€ */
                    var badge = document.getElementById('navCartBadge');
                    if (badge) {
                        var count = data.cart_count || 0;
                        badge.textContent = count;
                        if (count > 0) {
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                } else if (data.needs_login) {
                    alert('Please log in first to use the cart features.');
                    window.location.href = 'Login.html';
                } else if (data.vendor_conflict) {
                    if (confirm(data.message + '\n\nClear cart and add this item?')) {
                        var fd2 = new FormData();
                        fd2.append('action', 'clear');
                        fetch('CartActions.php', { method: 'POST', body: fd2 })
                            .then(function() { window.addToCart(foodId, qty); });
                    }
                } else {
                    showToast(data.message || 'Could not add to cart.', true);
                }
            })
            .catch(function() { showToast('Network error. Please try again.', true); });
    };

    /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       DATE & TIME VISIBILITY
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    window.toggleTime = function(prefix) {
        var select = document.getElementById(prefix + 'OrderType');
        var wrap = document.getElementById(prefix + 'PickupWrap');
        if (select && wrap) {
            if (select.value === 'Pickup' || select.value === 'Book') {
                wrap.classList.remove('hidden');
            } else {
                wrap.classList.add('hidden');
            }
        }
    };

    // Initialize pickup time fields visibility
    toggleTime('home');
    var searchSelect = document.getElementById('searchOrderType');
    if (searchSelect) {
        toggleTime('search');
    }

    /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       SIDEBAR DIETARY FILTERS (SUBMIT)
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    window.applyDietaryFilters = function() {
        var form = document.getElementById('searchFilterForm');
        if (!form) return;

        // Remove existing hidden dietary elements
        form.querySelectorAll('input[name="dietary_cb[]"]').forEach(function(el) {
            el.remove();
        });

        // Append checked checkboxes
        document.querySelectorAll('.dietary-cb:checked').forEach(function(cb) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'dietary_cb[]';
            hidden.value = cb.value;
            form.appendChild(hidden);
        });

        form.submit();
    };


});
</script>
</body>
</html>
