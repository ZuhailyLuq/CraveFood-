<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';
include('recommendations.php');

$sessionUserId = isset($_SESSION['UserID']) ? (int)$_SESSION['UserID'] : 0;

$hasRating = true;
$keyword = trim($_GET['keyword'] ?? '');
$dietary = trim($_GET['dietary'] ?? '');
$category = trim($_GET['category'] ?? '');
$minPrice = trim($_GET['min_price'] ?? '');
$maxPrice = trim($_GET['max_price'] ?? '');
$minRating = trim($_GET['min_rating'] ?? '');
$radiusKm = trim($_GET['radius_km'] ?? '3');
$orderType = trim($_GET['order_type'] ?? 'Dine-In');
$pickupTime = trim($_GET['pickup_time'] ?? '');
$sort = trim($_GET['sort'] ?? 'relevance');

$conditions = ['mf."Status" = \'Available\''];
$params = [];

if ($keyword !== '') {
    $conditions[] = '(mf."FoodName" ILIKE ? OR mf."Description" ILIKE ? OR v."ShopName" ILIKE ?)';
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
if ($dietary !== '') {
    $conditions[] = 'mf."DietaryTag" = ?';
    $params[] = $dietary;
}
if ($category !== '') {
    $conditions[] = 'mf."Category" = ?';
    $params[] = $category;
}
if ($minPrice !== '' && is_numeric($minPrice)) {
    $conditions[] = 'mf."Price" >= ?';
    $params[] = (float)$minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $conditions[] = 'mf."Price" <= ?';
    $params[] = (float)$maxPrice;
}
if ($hasRating && $minRating !== '' && is_numeric($minRating)) {
    $conditions[] = 'mf."Rating" >= ?';
    $params[] = (float)$minRating;
}

$sql = 'SELECT mf."FoodID", mf."FoodName", mf."Price", mf."Description", mf."DietaryTag", mf."Category", mf."VendorID", v."ShopName", v."Latitude", v."Longitude", mf."Image"' . ($hasRating ? ', mf."Rating"' : '') . '
        FROM menu_food mf
        LEFT JOIN vendor v ON mf."VendorID" = v."VendorID"
        WHERE ' . implode(" AND ", $conditions);

if ($sort === 'price_asc') {
    $sql .= ' ORDER BY mf."Price" ASC';
} elseif ($sort === 'price_desc') {
    $sql .= ' ORDER BY mf."Price" DESC';
} else {
    $sql .= ' ORDER BY mf."FoodID" DESC';
}

$foodResults = db_fetch_all($pdo, $sql, $params);

// Process results for map
$mapVendors = [];
foreach ($foodResults as $row) {
    $vid = $row['VendorID'];
    if (!isset($mapVendors[$vid])) {
        $mapVendors[$vid] = [
            'VendorID' => $vid,
            'ShopName' => $row['ShopName'],
            'Latitude' => $row['Latitude'],
            'Longitude' => $row['Longitude'],
            'Foods' => []
        ];
    }
    $mapVendors[$vid]['Foods'][] = [
        'FoodName' => $row['FoodName'],
        'Price' => $row['Price']
    ];
}

recordSearchActivity($foodResults, $dietary, $category);

$activeOrder = null;
if ($sessionUserId > 0) {
    $activeOrder = db_fetch_one($pdo, "SELECT \"OrderID\", \"Status\" FROM orders WHERE \"UserID\" = ? AND \"Status\" NOT IN ('Finished', 'Completed', 'Cancelled') ORDER BY \"OrderID\" DESC LIMIT 1", [$sessionUserId]);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Advance Search Results - CraveFood</title>
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <style>
        #resultsMap { height: 75vh; width: 100%; border-radius: var(--border-radius-lg); margin-bottom: 20px; box-shadow: var(--shadow-sm); z-index: 1;}
        .leaflet-popup-content { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .popup-btn-group { display: flex; gap: 10px; margin-top: 10px; }
        .popup-btn { padding: 5px 10px; text-decoration: none; border-radius: 4px; font-weight: 500; font-size: 0.9em; text-align: center; flex: 1; color: white;}
        .btn-navigate { background-color: #28a745; }
        .btn-order { background-color: var(--primary-color); }
    </style>
</head>
<body>
    <?php include('header.php'); ?>

    <a href="AdvanceSearch.php" class="btn-return">Return to Advance Search</a>

    <form method="GET" action="AdvanceSearchResults.php" class="search-top-bar" id="advSearchResultForm">
        <select name="order_type" id="advResultOrderType" onchange="toggleAdvanceResultPickup()">
            <option value="Dine-In" <?php if($orderType === 'Dine-In') echo 'selected'; ?>>Dine-In</option>
            <option value="Pickup" <?php if($orderType === 'Pickup') echo 'selected'; ?>>Pickup</option>
            <option value="Book" <?php if($orderType === 'Book') echo 'selected'; ?>>Reservations (Book)</option>
        </select>

        <input type="datetime-local" name="pickup_time" id="advResultPickupTime" value="<?php echo htmlspecialchars($pickupTime); ?>" class="<?php echo ($orderType === 'Book') ? '' : 'hidden'; ?>">
        <input type="text" name="keyword" placeholder="Search keyword..." value="<?php echo htmlspecialchars($keyword); ?>">

        <input type="hidden" name="dietary" id="hiddenDietary" value="<?php echo htmlspecialchars($dietary); ?>">
        <input type="hidden" name="category" id="hiddenCategory" value="<?php echo htmlspecialchars($category); ?>">
        <input type="hidden" name="min_price" value="<?php echo htmlspecialchars($minPrice); ?>">
        <input type="hidden" name="max_price" value="<?php echo htmlspecialchars($maxPrice); ?>">
        <input type="hidden" name="min_rating" value="<?php echo htmlspecialchars($minRating); ?>">
        <input type="hidden" name="radius_km" value="<?php echo htmlspecialchars($radiusKm); ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

        <button type="submit" class="btn-primary">Update Search</button>
    </form>

    <div class="search-layout">
        <div class="sidebar">
            <h3>Dietary</h3>
            <label><input type="radio" name="dietary_radio" value="" onchange="updateDietary(this)" <?php if($dietary === '') echo 'checked'; ?>> All</label>
            <label><input type="radio" name="dietary_radio" value="Halal" onchange="updateDietary(this)" <?php if($dietary === 'Halal') echo 'checked'; ?>> Halal</label>
            <label><input type="radio" name="dietary_radio" value="Vegetarian" onchange="updateDietary(this)" <?php if($dietary === 'Vegetarian') echo 'checked'; ?>> Vegetarian</label>
            <label><input type="radio" name="dietary_radio" value="Low Lactose" onchange="updateDietary(this)" <?php if($dietary === 'Low Lactose') echo 'checked'; ?>> Low Lactose</label>
            <label><input type="radio" name="dietary_radio" value="Protein" onchange="updateDietary(this)" <?php if($dietary === 'Protein') echo 'checked'; ?>> Protein</label>
            <label><input type="radio" name="dietary_radio" value="Fiber" onchange="updateDietary(this)" <?php if($dietary === 'Fiber') echo 'checked'; ?>> Fiber</label>

            <h3>Category</h3>
            <label><input type="radio" name="category_radio" value="" onchange="updateCategory(this)" <?php if($category === '') echo 'checked'; ?>> All</label>
            <label><input type="radio" name="category_radio" value="Main" onchange="updateCategory(this)" <?php if($category === 'Main') echo 'checked'; ?>> Main</label>
            <label><input type="radio" name="category_radio" value="Drink" onchange="updateCategory(this)" <?php if($category === 'Drink') echo 'checked'; ?>> Drink</label>
            <label><input type="radio" name="category_radio" value="Snack" onchange="updateCategory(this)" <?php if($category === 'Snack') echo 'checked'; ?>> Snack</label>
        </div>

        <div class="main-content">
            <?php
            $count = count($foodResults);
            echo "<p class='result-count'>Showing $count results</p>";
            ?>
            
            <div id="resultsMap"></div>

            <?php
            if ($count === 0) {
                echo "<p>No food matches your advance search filters.</p>";
            }
            ?>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

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
       RESULTS MAP
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    var mapEl = document.getElementById('resultsMap');
    if (!mapEl) return;

    var defaultLat = 3.0738, defaultLng = 101.5183;
    var map = L.map('resultsMap', { zoomControl: true }).setView([defaultLat, defaultLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    setTimeout(function() { map.invalidateSize(); }, 200);
    setTimeout(function() { map.invalidateSize(); }, 600);

    /* â”€â”€ Vendor data from PHP â”€â”€ */
    var mapVendors = <?php echo json_encode(array_values($mapVendors), JSON_UNESCAPED_UNICODE); ?>;

    /* â”€â”€ Custom marker icon â”€â”€ */
    var vendorIcon = L.divIcon({
        className: '',
        html: '<div style="width:28px;height:28px;background:#c1121f;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(193,18,31,0.35);"></div>',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -16]
    });

    var userIcon = L.divIcon({
        className: '',
        html: '<div style="width:16px;height:16px;background:#3b82f6;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(59,130,246,0.5);"></div>',
        iconSize: [16, 16],
        iconAnchor: [8, 8]
    });

    /* â”€â”€ Place vendor markers â”€â”€ */
    var markers = [];

    mapVendors.forEach(function(v) {
        if (v.Latitude && v.Longitude) {
            var lat = parseFloat(v.Latitude);
            var lng = parseFloat(v.Longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            var foodList = '';
            if (v.Foods && v.Foods.length) {
                foodList = '<div style="margin-top:6px;font-size:0.78rem;">';
                v.Foods.slice(0, 5).forEach(function(f) {
                    foodList += '<div style="display:flex;justify-content:space-between;gap:12px;">' +
                        '<span>' + escapeHtml(f.FoodName) + '</span>' +
                        '<span style="color:#c1121f;font-weight:600;">RM ' + parseFloat(f.Price).toFixed(2) + '</span>' +
                        '</div>';
                });
                if (v.Foods.length > 5) {
                    foodList += '<div style="color:#aaa;font-style:italic;">+' + (v.Foods.length - 5) + ' more...</div>';
                }
                foodList += '</div>';
            }

            var popupHtml = '<div style="font-family:Inter,sans-serif;min-width:160px;">' +
                '<strong style="font-size:0.9rem;">' + escapeHtml(v.ShopName || 'Vendor') + '</strong>' +
                foodList +
                '<div class="popup-btn-group">' +
                '<a href="https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng + '" target="_blank" class="popup-btn btn-navigate">Navigate</a>' +
                '<a href="VendorInfo.php?vendor_id=' + v.VendorID + '" class="popup-btn btn-order">Order</a>' +
                '</div></div>';

            var marker = L.marker([lat, lng], { icon: vendorIcon }).addTo(map);
            marker.bindPopup(popupHtml);
            markers.push(marker);
        }
    });

    /* Fit map to markers */
    if (markers.length > 0) {
        var group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.15));
    }

    /* â”€â”€ User location â”€â”€ */
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            var uMarker = L.marker([pos.coords.latitude, pos.coords.longitude], { icon: userIcon }).addTo(map);
            uMarker.bindPopup('<strong style="font-family:Inter,sans-serif;font-size:0.85rem;">&#128205; You</strong>');
            if (markers.length === 0) {
                map.setView([pos.coords.latitude, pos.coords.longitude], 14);
            }
        }, function() {}, { enableHighAccuracy: true, timeout: 8000 });
    }

    /* â”€â”€ Helper â”€â”€ */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
});

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   SIDEBAR FILTER HELPERS
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
function updateDietary(radio) {
    document.getElementById('hiddenDietary').value = radio.value;
    document.getElementById('advSearchResultForm').submit();
}
function updateCategory(radio) {
    document.getElementById('hiddenCategory').value = radio.value;
    document.getElementById('advSearchResultForm').submit();
}
function toggleAdvanceResultPickup() {
    var sel = document.getElementById('advResultOrderType');
    var pt = document.getElementById('advResultPickupTime');
    if (sel.value === 'Book') {
        pt.classList.remove('hidden');
    } else {
        pt.classList.add('hidden');
    }
}
</script>
</body>
</html>




