<?php
session_start();
include('db.php');
include('db_helpers.php');
include('recommendations.php');

$userId = isset($_SESSION['UserID']) ? (int)$_SESSION['UserID'] : 0;

$hasRating  = true;
$hasVendorId = true;
$hasLat     = true;
$hasLng     = true;
$hasLoc     = true;

// ── Current filter values ──
$keyword   = trim($_GET['keyword']   ?? '');
$dietary   = trim($_GET['dietary']   ?? '');
$category  = trim($_GET['category']  ?? '');
$minPrice  = trim($_GET['min_price'] ?? '');
$maxPrice  = trim($_GET['max_price'] ?? '');
$minRating = trim($_GET['min_rating'] ?? '');
$radiusKm  = is_numeric(trim($_GET['radius_km'] ?? '')) ? (float)trim($_GET['radius_km']) : 5;
$orderType = trim($_GET['order_type'] ?? 'Dine-In');
$pickupTime= trim($_GET['pickup_time'] ?? '');
$sort      = trim($_GET['sort'] ?? 'relevance');
$userLat   = (isset($_GET['user_lat']) && is_numeric($_GET['user_lat'])) ? (float)$_GET['user_lat'] : null;
$userLng   = (isset($_GET['user_lng']) && is_numeric($_GET['user_lng'])) ? (float)$_GET['user_lng'] : null;

// ── Build vendor query ──
$vendorConditions = ['mf."Status" = \'Available\''];
$params = [];

if ($keyword !== '') {
    $vendorConditions[] = '(mf."FoodName" ILIKE ? OR mf."Description" ILIKE ? OR v."ShopName" ILIKE ?)';
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
if ($dietary !== '') {
    $vendorConditions[] = 'mf."DietaryTag" = ?';
    $params[] = $dietary;
}
if ($category !== '') {
    $vendorConditions[] = 'mf."Category" = ?';
    $params[] = $category;
}
if ($minPrice !== '' && is_numeric($minPrice)) {
    $vendorConditions[] = 'mf."Price" >= ?';
    $params[] = (float)$minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $vendorConditions[] = 'mf."Price" <= ?';
    $params[] = (float)$maxPrice;
}
if ($hasRating && $minRating !== '' && is_numeric($minRating)) {
    $vendorConditions[] = 'mf."Rating" >= ?';
    $params[] = (float)$minRating;
}

$vendorMapData = [];
$latSel = 'v."Latitude"';
$lngSel = 'v."Longitude"';
$locSel = 'v."Location"';
$latGrp = ', v."Latitude"';
$lngGrp = ', v."Longitude"';
$locGrp = ', v."Location"';

// Haversine distance calculation if user location is available
$distanceSelect = '';
$distanceHaving = '';
if ($userLat !== null && $userLng !== null && $hasLat && $hasLng) {
    $uLat = (float)$userLat;
    $uLng = (float)$userLng;
    $distanceSelect = ", ( 6371 * acos( LEAST(1, cos(radians($uLat)) * cos(radians(v.\"Latitude\")) * cos(radians(v.\"Longitude\") - radians($uLng)) + sin(radians($uLat)) * sin(radians(v.\"Latitude\")) ) ) ) AS distance_km";
    // We duplicate the calculation in HAVING because some PostgreSQL versions don't allow using the alias in HAVING
    $distanceHaving = "HAVING ( 6371 * acos( LEAST(1, cos(radians($uLat)) * cos(radians(v.\"Latitude\")) * cos(radians(v.\"Longitude\") - radians($uLng)) + sin(radians($uLat)) * sin(radians(v.\"Latitude\")) ) ) ) <= " . (float)$radiusKm;
}

$vSql = "SELECT v.\"VendorID\", v.\"ShopName\", $locSel, $latSel, $lngSel,
                COUNT(mf.\"FoodID\") AS \"MatchedItems\" $distanceSelect
         FROM vendor v
         INNER JOIN menu_food mf ON mf.\"VendorID\" = v.\"VendorID\"
         WHERE " . implode(" AND ", $vendorConditions) . "
         GROUP BY v.\"VendorID\", v.\"ShopName\"$locGrp$latGrp$lngGrp
         $distanceHaving
         ORDER BY \"MatchedItems\" DESC";

$vRes = db_fetch_all($pdo, $vSql, $params);
foreach ($vRes as $v) {
    $vendorMapData[] = [
        'vendorId'    => (int)$v['VendorID'],
        'shopName'    => $v['ShopName'] ?? ('Vendor #' . $v['VendorID']),
        'location'    => $v['Location'] ?? '',
        'latitude'    => ($v['Latitude'] !== null && $v['Latitude'] !== '') ? (float)$v['Latitude'] : null,
        'longitude'   => ($v['Longitude'] !== null && $v['Longitude'] !== '') ? (float)$v['Longitude'] : null,
        'matchedItems'=> (int)$v['MatchedItems'],
        'distanceKm'  => isset($v['distance_km']) ? round((float)$v['distance_km'], 1) : null
    ];
}

// ── Active order (pending pill) ──
$activeOrder = null;
if ($userId > 0) {
    $activeOrder = db_fetch_one($pdo, "SELECT \"OrderID\", \"Status\" FROM orders WHERE \"UserID\" = ? AND \"Status\" NOT IN ('Finished','Completed','Cancelled') ORDER BY \"OrderID\" DESC LIMIT 1", [$userId]);
}

$vendorJson = json_encode($vendorMapData, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Search – CraveFood</title>
    <meta name="description" content="Advanced search to find food by budget, dietary tag, category, radius and order mode on CraveFood.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=20260621-7">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

    <style>
        /* ── Reset & base ── */
        *, body { font-family: 'Inter', 'Segoe UI', sans-serif; }

        /* Make html+body fill the full viewport with no gaps */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        body {
            display: flex;
            flex-direction: column;
        }
        /* Navbar stays natural height; shell takes all remaining space */
        .navbar { flex-shrink: 0; }

        /* ── SPLIT LAYOUT ── */
        .as-shell {
            flex: 1;          /* fill all space below navbar */
            min-height: 0;    /* allow flex children to shrink correctly */
            display: flex;
            overflow: hidden;
            background: #fdf5f6;
        }

        /* ════════════════════════════════
           LEFT SIDEBAR
        ════════════════════════════════ */
        .as-sidebar {
            width: 360px;
            min-width: 300px;
            max-width: 380px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-right: 1px solid #f0e4e7;
            overflow: hidden;
        }
        .as-sidebar-inner {
            overflow-y: auto;
            flex: 1;
            padding: 20px 20px 120px; /* bottom pad clears pending pill */
            scrollbar-width: thin;
            scrollbar-color: #f0cfd3 transparent;
        }
        .as-sidebar-inner::-webkit-scrollbar { width: 5px; }
        .as-sidebar-inner::-webkit-scrollbar-thumb { background: #f0cfd3; border-radius: 99px; }

        /* Sidebar header */
        .as-sidebar-header {
            padding: 18px 20px 14px;
            border-bottom: 1px solid #f5eaec;
            flex-shrink: 0;
        }
        .as-sidebar-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1e1e1e;
            margin: 0 0 2px;
        }
        .as-sidebar-sub {
            font-size: 0.78rem;
            color: #bbb;
            margin: 0;
        }

        /* ── Filter groups ── */
        .as-filter-group {
            margin-bottom: 18px;
        }
        .as-filter-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #aaa;
            display: block;
            margin-bottom: 6px;
        }

        /* Text / search input */
        .as-input {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #e8e0e2;
            border-radius: 10px;
            font-size: 0.87rem;
            font-family: 'Inter', sans-serif;
            color: #1e1e1e;
            background: #fff;
            outline: none;
            transition: border-color 0.18s;
            box-sizing: border-box;
        }
        .as-input:focus { border-color: #c1121f; }

        /* Select */
        .as-select {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #e8e0e2;
            border-radius: 10px;
            font-size: 0.87rem;
            font-family: 'Inter', sans-serif;
            color: #1e1e1e;
            background: #fff;
            outline: none;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='%23aaa'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            transition: border-color 0.18s;
            box-sizing: border-box;
        }
        .as-select:focus { border-color: #c1121f; }

        /* Price row */
        .as-price-row {
            display: flex;
            gap: 8px;
        }
        .as-price-wrap {
            flex: 1;
            position: relative;
        }
        .as-price-prefix {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.78rem;
            font-weight: 700;
            color: #c1121f;
            pointer-events: none;
        }
        .as-price-input {
            width: 100%;
            padding: 9px 10px 9px 30px;
            border: 1.5px solid #e8e0e2;
            border-radius: 10px;
            font-size: 0.87rem;
            font-family: 'Inter', sans-serif;
            color: #1e1e1e;
            background: #fff;
            outline: none;
            transition: border-color 0.18s;
            box-sizing: border-box;
        }
        .as-price-input:focus { border-color: #c1121f; }

        /* Radius slider */
        .as-slider-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .as-slider {
            flex: 1;
            -webkit-appearance: none;
            height: 4px;
            border-radius: 99px;
            background: #f0e4e7;
            outline: none;
            cursor: pointer;
        }
        .as-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: #c1121f;
            box-shadow: 0 2px 8px rgba(193,18,31,0.3);
            cursor: pointer;
            transition: transform 0.15s;
        }
        .as-slider::-webkit-slider-thumb:hover { transform: scale(1.18); }
        .as-slider::-moz-range-thumb {
            width: 18px; height: 18px;
            border-radius: 50%;
            background: #c1121f;
            border: none;
            cursor: pointer;
        }
        .as-slider-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: #c1121f;
            min-width: 48px;
            text-align: right;
        }

        /* Divider */
        .as-divider {
            height: 1px;
            background: #f5eaec;
            margin: 16px 0;
        }

        /* ── Result list in sidebar ── */
        .as-result-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .as-result-title {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #aaa;
        }
        .as-result-count-badge {
            font-size: 0.72rem;
            font-weight: 700;
            background: #ffe8ec;
            color: #c1121f;
            padding: 3px 9px;
            border-radius: 999px;
        }
        .as-vendor-card {
            background: #fff;
            border: 1px solid #f0e4e7;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: box-shadow 0.18s, border-color 0.18s;
        }
        .as-vendor-card:hover {
            border-color: #c1121f;
            box-shadow: 0 4px 16px rgba(193,18,31,0.1);
        }
        .as-vendor-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1e1e1e;
            margin: 0 0 2px;
        }
        .as-vendor-loc {
            font-size: 0.75rem;
            color: #999;
            margin: 0 0 5px;
        }
        .as-vendor-meta {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .as-vendor-badge {
            font-size: 0.67rem;
            font-weight: 600;
            background: #f5f5f5;
            color: #666;
            padding: 2px 8px;
            border-radius: 999px;
        }
        .as-vendor-badge.red { background: #ffe8ec; color: #9f0f1c; }
        .as-vendor-view-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #c1121f;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .as-vendor-view-btn:hover { opacity: 0.7; }

        .as-empty-state {
            text-align: center;
            padding: 32px 16px;
            color: #ccc;
        }
        .as-empty-state svg { width: 40px; height: 40px; fill: #f0cfd3; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
        .as-empty-state p { font-size: 0.85rem; font-weight: 600; color: #bbb; margin: 0; }

        /* ════════════════════════════════
           RIGHT MAP AREA
        ════════════════════════════════ */
        .as-map-area {
            flex: 1;
            position: relative;
            overflow: hidden;
        }
        #advancedMap {
            width: 100%;
            height: 100%;
        }

        /* Vendor count pill — top-center of map */
        .as-map-pill {
            position: absolute;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 800;
            background: rgba(255,255,255,0.96);
            border: 1px solid #f0e4e7;
            border-radius: 999px;
            padding: 7px 16px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #1e1e1e;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            white-space: nowrap;
            pointer-events: none;
            transition: opacity 0.2s;
        }
        .as-map-pill span { color: #c1121f; }

        /* Circular locate button — bottom-right of map */
        .as-locate-btn {
            position: absolute;
            bottom: 80px;
            right: 16px;
            z-index: 800;
            width: 42px; height: 42px;
            border-radius: 50%;
            background: #fff;
            border: none;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: box-shadow 0.2s, transform 0.15s;
        }
        .as-locate-btn:hover { box-shadow: 0 6px 22px rgba(0,0,0,0.22); transform: translateY(-1px); }
        .as-locate-btn svg { width: 20px; height: 20px; fill: #c1121f; }
        .as-locate-btn.locating svg { fill: #aaa; animation: as-spin 1s linear infinite; }
        @keyframes as-spin { to { transform: rotate(360deg); } }

        /* ════════════════════════════════
           PENDING ORDER PILL
        ════════════════════════════════ */
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
        .pending-pill-icon  { color: #c1121f; display: flex; align-items: center; }
        .pending-pill-text  { font-size: 0.9rem; font-weight: 600; color: #1e1e1e; }
        .pending-pill-chevron { color: #c1121f; display: flex; align-items: center; }
        @keyframes pill-float {
            0%,100% { box-shadow: 0 8px 32px rgba(193,18,31,0.2), 0 2px 8px rgba(0,0,0,0.08); }
            50%      { box-shadow: 0 12px 40px rgba(193,18,31,0.26), 0 4px 14px rgba(0,0,0,0.1); }
        }

        /* ════════════════════════════════
           RESPONSIVE — Mobile / Tablet
        ════════════════════════════════ */
        @media (max-width: 860px) {
            .as-shell {
                flex-direction: column;
                height: auto;
                overflow: visible;
            }
            .as-sidebar {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                order: 2;
                border-right: none;
                border-top: 1px solid #f0e4e7;
                overflow: visible;
                max-height: none;
            }
            .as-sidebar-inner { padding-bottom: 100px; }
            .as-map-area {
                order: 1;
                height: 50vh;
                min-height: 280px;
            }
            .as-locate-btn { bottom: 14px; }
        }

        /* Misc overrides */
        .navbar h2 { font-family: 'Inter', sans-serif; }
        .leaflet-control-zoom { border-radius: 10px !important; overflow: hidden; }
    </style>
</head>
<body>

<?php include('header.php'); ?>

<!-- ══════ SPLIT SHELL ══════ -->
<div class="as-shell">

    <!-- ══ LEFT SIDEBAR ══ -->
    <aside class="as-sidebar">
        <div class="as-sidebar-header">
            <h1 class="as-sidebar-title">Advanced Search</h1>
            <p class="as-sidebar-sub">Filters update the map instantly</p>
        </div>

        <div class="as-sidebar-inner">

            <!-- Keyword -->
            <div class="as-filter-group">
                <label class="as-filter-label" for="f-keyword">Keyword</label>
                <input class="as-input" type="text" id="f-keyword" placeholder="e.g. Nasi Lemak, Chicken…"
                       value="<?php echo htmlspecialchars($keyword); ?>">
            </div>

            <!-- Order Mode -->
            <div class="as-filter-group">
                <label class="as-filter-label" for="f-order-type">Order Mode</label>
                <select class="as-select" id="f-order-type" onchange="handleOrderTypeChange()">
                    <option value="Dine-In"  <?php if($orderType==='Dine-In')  echo 'selected';?>>Dine-In</option>
                    <option value="Pickup"   <?php if($orderType==='Pickup')   echo 'selected';?>>Pickup</option>
                    <option value="Book"     <?php if($orderType==='Book')     echo 'selected';?>>Book (Reservation)</option>
                </select>
            </div>

            <!-- Pickup time (shown only for Book) -->
            <div class="as-filter-group" id="pickup-group" style="display:<?php echo $orderType==='Book'?'block':'none'; ?>">
                <label class="as-filter-label" for="f-pickup-time">Date &amp; Time</label>
                <input class="as-input" type="datetime-local" id="f-pickup-time"
                       value="<?php echo htmlspecialchars($pickupTime); ?>">
            </div>

            <div class="as-divider"></div>

            <!-- Dietary -->
            <div class="as-filter-group">
                <label class="as-filter-label" for="f-dietary">Dietary</label>
                <select class="as-select" id="f-dietary">
                    <option value="">All Dietary</option>
                    <option value="Halal"       <?php if($dietary==='Halal')       echo 'selected';?>>Halal</option>
                    <option value="Vegetarian"  <?php if($dietary==='Vegetarian')  echo 'selected';?>>Vegetarian</option>
                    <option value="Low Lactose" <?php if($dietary==='Low Lactose') echo 'selected';?>>Low Lactose</option>
                    <option value="Protein"     <?php if($dietary==='Protein')     echo 'selected';?>>Protein</option>
                    <option value="Fiber"       <?php if($dietary==='Fiber')       echo 'selected';?>>Fiber</option>
                </select>
            </div>

            <!-- Category -->
            <div class="as-filter-group">
                <label class="as-filter-label" for="f-category">Category</label>
                <select class="as-select" id="f-category">
                    <option value="">All Categories</option>
                    <option value="Main"  <?php if($category==='Main')  echo 'selected';?>>Main</option>
                    <option value="Drink" <?php if($category==='Drink') echo 'selected';?>>Drink</option>
                    <option value="Snack" <?php if($category==='Snack') echo 'selected';?>>Snack</option>
                </select>
            </div>

            <!-- Sort -->
            <div class="as-filter-group">
                <label class="as-filter-label" for="f-sort">Sort By</label>
                <select class="as-select" id="f-sort">
                    <option value="relevance" <?php if($sort==='relevance') echo 'selected';?>>Newest</option>
                    <option value="price_asc" <?php if($sort==='price_asc') echo 'selected';?>>Price: Low → High</option>
                    <option value="price_desc"<?php if($sort==='price_desc') echo 'selected';?>>Price: High → Low</option>
                </select>
            </div>

            <div class="as-divider"></div>

            <!-- Price Range -->
            <div class="as-filter-group">
                <label class="as-filter-label">Price Range (RM)</label>
                <div class="as-price-row">
                    <div class="as-price-wrap">
                        <span class="as-price-prefix">RM</span>
                        <input class="as-price-input" type="number" id="f-min-price" placeholder="Min"
                               min="0" step="0.50" value="<?php echo htmlspecialchars($minPrice); ?>">
                    </div>
                    <div class="as-price-wrap">
                        <span class="as-price-prefix">RM</span>
                        <input class="as-price-input" type="number" id="f-max-price" placeholder="Max"
                               min="0" step="0.50" value="<?php echo htmlspecialchars($maxPrice); ?>">
                    </div>
                </div>
            </div>

            <!-- Radius Slider -->
            <div class="as-filter-group">
                <label class="as-filter-label">Radius (uses your location)</label>
                <div class="as-slider-row">
                    <input class="as-slider" type="range" id="f-radius"
                           min="1" max="20" step="0.5"
                           value="<?php echo htmlspecialchars($radiusKm); ?>">
                    <span class="as-slider-value" id="radiusLabel"><?php echo htmlspecialchars($radiusKm); ?> km</span>
                </div>
            </div>

            <?php if ($hasRating): ?>
            <!-- Min Rating -->
            <div class="as-filter-group">
                <label class="as-filter-label">Min Rating</label>
                <div class="as-slider-row">
                    <input class="as-slider" type="range" id="f-min-rating"
                           min="0" max="5" step="0.5"
                           value="<?php echo htmlspecialchars($minRating ?: '0'); ?>">
                    <span class="as-slider-value" id="ratingLabel"><?php echo htmlspecialchars($minRating ?: '0'); ?> ★</span>
                </div>
            </div>
            <?php endif; ?>

            <div class="as-divider"></div>

            <!-- Vendor results list -->
            <div class="as-result-header">
                <span class="as-result-title">Matching Vendors</span>
                <span class="as-result-count-badge" id="sidebarCount">
                    <?php echo count($vendorMapData); ?>
                </span>
            </div>
            <div id="vendorList">
                <?php if (count($vendorMapData) === 0): ?>
                <div class="as-empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    <p>No vendors match your filters</p>
                </div>
                <?php else: ?>
                <?php foreach ($vendorMapData as $v): ?>
                <div class="as-vendor-card"
                     onclick="focusVendorOnMap(<?php echo $v['vendorId']; ?>)"
                     data-vendor-id="<?php echo $v['vendorId']; ?>">
                    <p class="as-vendor-name"><?php echo htmlspecialchars($v['shopName']); ?></p>
                    <?php if (!empty($v['location'])): ?>
                    <p class="as-vendor-loc"><?php echo htmlspecialchars($v['location']); ?></p>
                    <?php endif; ?>
                    <div class="as-vendor-meta">
                        <span class="as-vendor-badge red"><?php echo $v['matchedItems']; ?> item<?php echo $v['matchedItems']!==1?'s':''; ?></span>
                        <?php if ($v['latitude'] && $v['longitude']): ?>
                        <span class="as-vendor-badge"><?php echo ($v['distanceKm'] !== null) ? '📍 ' . $v['distanceKm'] . ' km' : '📍 On map'; ?></span>
                        <?php endif; ?>
                    </div>
                    <a class="as-vendor-view-btn" href="VendorInfo.php?vendor_id=<?php echo $v['vendorId']; ?>">
                        View menu →
                    </a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div><!-- /.as-sidebar-inner -->
    </aside>

    <!-- ══ RIGHT MAP AREA ══ -->
    <div class="as-map-area">
        <!-- Vendor count pill -->
        <div class="as-map-pill" id="mapPill">
            Showing <span id="mapVendorCount"><?php echo count($vendorMapData); ?></span> vendor<?php echo count($vendorMapData)!==1?'s':''; ?>
        </div>

        <!-- Map -->
        <div id="advancedMap"></div>

        <!-- Circular locate button -->
        <button class="as-locate-btn" id="locateBtn" type="button" aria-label="Center on my location">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
            </svg>
        </button>
    </div>

</div><!-- /.as-shell -->

<!-- ══════ PENDING ORDER PILL ══════ -->
<?php if ($activeOrder): ?>
<a href="OrderStatus.php?order_id=<?php echo (int)$activeOrder['OrderID']; ?>"
   class="pending-pill" aria-label="View pending order">
    <span class="pending-pill-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#c1121f">
            <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
        </svg>
    </span>
    <span class="pending-pill-text">Order #<?php echo (int)$activeOrder['OrderID']; ?> is <?php echo htmlspecialchars($activeOrder['Status']); ?></span>
    <span class="pending-pill-chevron">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#c1121f">
            <path d="M9.29 6.71a.996.996 0 0 0 0 1.41L13.17 12l-3.88 3.88a.996.996 0 1 0 1.41 1.41l4.59-4.59a.996.996 0 0 0 0-1.41L10.7 6.7c-.38-.38-1.02-.38-1.41.01z"/>
        </svg>
    </span>
</a>
<?php endif; ?>

<!-- ══════ LEAFLET ══════ -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    /* ── Navbar active link ── */
    var page = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
    if (page === '' || page === 'index.php') page = 'homepage.php';
    document.querySelectorAll('.nav-links a').forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === page || (page.startsWith('advancesearch') && href === 'homepage.php')) {
            link.classList.add('active');
        }
    });

    /* ══════════════════════════════════
       MAP INITIALIZATION
    ══════════════════════════════════ */
    var defaultLat = 3.0738, defaultLng = 101.5183; // Sunway / Subang default
    var defaultZoom = 13;

    var map = L.map('advancedMap', {
        zoomControl: true
    }).setView([defaultLat, defaultLng], defaultZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    /* Force Leaflet to recalculate size after layout settles */
    setTimeout(function() { map.invalidateSize(); }, 200);
    setTimeout(function() { map.invalidateSize(); }, 600);

    /* ── Vendor data from PHP ── */
    var vendors = <?php echo $vendorJson; ?>;

    /* ── Custom marker icon ── */
    var vendorIcon = L.divIcon({
        className: '',
        html: '<div style="width:30px;height:30px;background:#c1121f;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>',
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -18]
    });

    var userIcon = L.divIcon({
        className: '',
        html: '<div style="width:18px;height:18px;background:#3b82f6;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(59,130,246,0.5);"></div>',
        iconSize: [18, 18],
        iconAnchor: [9, 9]
    });

    /* ── Place vendor markers ── */
    var markers = [];
    var markerMap = {}; // vendorId -> marker

    vendors.forEach(function(v) {
        if (v.latitude !== null && v.longitude !== null) {
            var marker = L.marker([v.latitude, v.longitude], { icon: vendorIcon }).addTo(map);
            marker.bindPopup(
                '<div style="font-family:Inter,sans-serif;min-width:140px;">' +
                '<strong style="font-size:0.9rem;">' + escapeHtml(v.shopName) + '</strong>' +
                (v.location ? '<br><span style="font-size:0.75rem;color:#888;">' + escapeHtml(v.location) + '</span>' : '') +
                '<br><span style="font-size:0.75rem;color:#c1121f;font-weight:600;">' + v.matchedItems + ' matched item' + (v.matchedItems !== 1 ? 's' : '') + '</span>' +
                '<br><a href="VendorInfo.php?vendor_id=' + v.vendorId + '" style="font-size:0.78rem;color:#c1121f;font-weight:600;text-decoration:none;">View menu &rarr;</a>' +
                '</div>'
            );
            markers.push(marker);
            markerMap[v.vendorId] = marker;
        }
    });

    /* Fit map to markers if any exist */
    if (markers.length > 0) {
        var group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.15));
    }

    /* ── User location & radius circle ── */
    var userMarker = null;
    var radiusCircle = null;
    var userLat = null, userLng = null;

    function drawRadiusCircle() {
        if (userLat === null || userLng === null) return;
        var radiusKm = parseFloat(document.getElementById('f-radius').value) || 5;
        var radiusM = radiusKm * 1000;

        if (radiusCircle) {
            radiusCircle.setLatLng([userLat, userLng]);
            radiusCircle.setRadius(radiusM);
        } else {
            radiusCircle = L.circle([userLat, userLng], {
                radius: radiusM,
                color: '#c1121f',
                fillColor: '#c1121f',
                fillOpacity: 0.06,
                weight: 2,
                dashArray: '6 4'
            }).addTo(map);
        }
    }

    function locateUser() {
        var btn = document.getElementById('locateBtn');
        btn.classList.add('locating');

        if (!navigator.geolocation) {
            btn.classList.remove('locating');
            return;
        }

        navigator.geolocation.getCurrentPosition(function(pos) {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            btn.classList.remove('locating');

            if (userMarker) {
                userMarker.setLatLng([userLat, userLng]);
            } else {
                userMarker = L.marker([userLat, userLng], { icon: userIcon }).addTo(map);
                userMarker.bindPopup('<strong style="font-family:Inter,sans-serif;font-size:0.85rem;">📍 You are here</strong>');
            }

            drawRadiusCircle();
            map.setView([userLat, userLng], 14);
        }, function() {
            btn.classList.remove('locating');
        }, { enableHighAccuracy: true, timeout: 8000 });
    }

    document.getElementById('locateBtn').addEventListener('click', locateUser);

    /* Try to get location on load */
    var urlParams = new URLSearchParams(window.location.search);
    var hadLocationInUrl = urlParams.has('user_lat') && urlParams.has('user_lng');

    /* If URL already has user coords, restore them into JS variables */
    if (hadLocationInUrl) {
        userLat = parseFloat(urlParams.get('user_lat'));
        userLng = parseFloat(urlParams.get('user_lng'));
        if (!isNaN(userLat) && !isNaN(userLng)) {
            userMarker = L.marker([userLat, userLng], { icon: userIcon }).addTo(map);
            userMarker.bindPopup('<strong style="font-family:Inter,sans-serif;font-size:0.85rem;">📍 You are here</strong>');
            drawRadiusCircle();
        }
    }

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;

            if (!userMarker) {
                userMarker = L.marker([userLat, userLng], { icon: userIcon }).addTo(map);
                userMarker.bindPopup('<strong style="font-family:Inter,sans-serif;font-size:0.85rem;">📍 You are here</strong>');
            } else {
                userMarker.setLatLng([userLat, userLng]);
            }
            drawRadiusCircle();

            /* If no vendor markers, center on user */
            if (markers.length === 0) {
                map.setView([userLat, userLng], 14);
            }

            /* If user location wasn't in URL yet, auto-apply filters
               so the server can do Haversine distance filtering */
            if (!hadLocationInUrl) {
                applyFilters();
            }
        }, function() {}, { enableHighAccuracy: true, timeout: 8000 });
    }

    /* ══════════════════════════════════
       RADIUS SLIDER — live update
    ══════════════════════════════════ */
    var radiusSlider = document.getElementById('f-radius');
    var radiusLabel  = document.getElementById('radiusLabel');

    radiusSlider.addEventListener('input', function() {
        radiusLabel.textContent = this.value + ' km';
        drawRadiusCircle();
    });

    /* Also trigger filter re-apply when slider is released */
    radiusSlider.addEventListener('change', function() {
        radiusLabel.textContent = this.value + ' km';
        drawRadiusCircle();
        applyFilters();
    });

    /* ══════════════════════════════════
       RATING SLIDER — live update
    ══════════════════════════════════ */
    var ratingSlider = document.getElementById('f-min-rating');
    var ratingLabel  = document.getElementById('ratingLabel');
    if (ratingSlider && ratingLabel) {
        ratingSlider.addEventListener('input', function() {
            ratingLabel.textContent = this.value + ' ★';
        });
        ratingSlider.addEventListener('change', function() {
            applyFilters();
        });
    }

    /* ══════════════════════════════════
       AUTO-APPLY FILTERS (debounced)
    ══════════════════════════════════ */
    var debounceTimer = null;

    function applyFilters() {
        var params = new URLSearchParams();
        var keyword   = document.getElementById('f-keyword').value.trim();
        var orderType = document.getElementById('f-order-type').value;
        var dietary   = document.getElementById('f-dietary').value;
        var category  = document.getElementById('f-category').value;
        var sort      = document.getElementById('f-sort').value;
        var minPrice  = document.getElementById('f-min-price').value.trim();
        var maxPrice  = document.getElementById('f-max-price').value.trim();
        var radius    = document.getElementById('f-radius').value;
        var pickupEl  = document.getElementById('f-pickup-time');
        var pickupTime= pickupEl ? pickupEl.value : '';

        if (keyword)    params.set('keyword',     keyword);
        if (orderType)  params.set('order_type',  orderType);
        if (dietary)    params.set('dietary',     dietary);
        if (category)   params.set('category',    category);
        if (sort)       params.set('sort',        sort);
        if (minPrice)   params.set('min_price',   minPrice);
        if (maxPrice)   params.set('max_price',   maxPrice);
        if (radius)     params.set('radius_km',   radius);
        if (pickupTime) params.set('pickup_time', pickupTime);

        /* Pass user location so PHP can filter by distance */
        if (userLat !== null && userLng !== null) {
            params.set('user_lat', userLat);
            params.set('user_lng', userLng);
        }

        if (ratingSlider) {
            var rating = ratingSlider.value;
            if (parseFloat(rating) > 0) params.set('min_rating', rating);
        }

        window.location.href = 'AdvanceSearch.php?' + params.toString();
    }

    function debouncedApply() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(applyFilters, 500);
    }

    /* Attach listeners */
    document.getElementById('f-keyword').addEventListener('input', debouncedApply);
    document.getElementById('f-dietary').addEventListener('change', applyFilters);
    document.getElementById('f-category').addEventListener('change', applyFilters);
    document.getElementById('f-sort').addEventListener('change', applyFilters);
    document.getElementById('f-min-price').addEventListener('change', applyFilters);
    document.getElementById('f-max-price').addEventListener('change', applyFilters);

    /* ══════════════════════════════════
       ORDER TYPE toggle (Book group)
    ══════════════════════════════════ */
    window.handleOrderTypeChange = function() {
        var ot = document.getElementById('f-order-type').value;
        var pg = document.getElementById('pickup-group');
        if (pg) pg.style.display = (ot === 'Book') ? 'block' : 'none';
        applyFilters();
    };

    /* ══════════════════════════════════
       FOCUS VENDOR ON MAP
    ══════════════════════════════════ */
    window.focusVendorOnMap = function(vendorId) {
        var m = markerMap[vendorId];
        if (m) {
            map.setView(m.getLatLng(), 16, { animate: true });
            m.openPopup();
        }
    };

    /* ── Helper ── */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

});
</script>
</body>
</html>



