<?php
/**
 * AdvanceSearchAjax.php
 * Returns JSON array of matching vendors for the Advanced Search live filter.
 * Called by AdvanceSearch.php via fetch() whenever a filter changes.
 */
session_start();
include('db.php');

if (!isset($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

function menuColExistsAjax($conn, $col) {
    $c = mysqli_real_escape_string($conn, $col);
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `MENU_FOOD` LIKE '$c'");
    return $r && mysqli_num_rows($r) > 0;
}
function vendorColExistsAjax($conn, $col) {
    $c = mysqli_real_escape_string($conn, $col);
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `vendor` LIKE '$c'");
    return $r && mysqli_num_rows($r) > 0;
}

$hasRating   = menuColExistsAjax($conn, 'Rating');
$hasVendorId = menuColExistsAjax($conn, 'VendorID');
$hasLat      = vendorColExistsAjax($conn, 'Latitude');
$hasLng      = vendorColExistsAjax($conn, 'Longitude');
$hasLoc      = vendorColExistsAjax($conn, 'Location');

$keyword   = trim($_GET['keyword']   ?? '');
$dietary   = trim($_GET['dietary']   ?? '');
$category  = trim($_GET['category']  ?? '');
$minPrice  = trim($_GET['min_price'] ?? '');
$maxPrice  = trim($_GET['max_price'] ?? '');
$minRating = trim($_GET['min_rating'] ?? '');
$sort      = trim($_GET['sort'] ?? 'relevance');

$conditions = ["mf.Status = 'Available'"];

if ($keyword !== '') {
    $kw = mysqli_real_escape_string($conn, $keyword);
    $conditions[] = "(mf.FoodName LIKE '%$kw%' OR mf.Description LIKE '%$kw%' OR v.ShopName LIKE '%$kw%')";
}
if ($dietary !== '') {
    $d = mysqli_real_escape_string($conn, $dietary);
    $conditions[] = "mf.DietaryTag = '$d'";
}
if ($category !== '') {
    $c2 = mysqli_real_escape_string($conn, $category);
    $conditions[] = "mf.Category = '$c2'";
}
if ($minPrice !== '' && is_numeric($minPrice)) $conditions[] = "mf.Price >= " . (float)$minPrice;
if ($maxPrice !== '' && is_numeric($maxPrice)) $conditions[] = "mf.Price <= " . (float)$maxPrice;
if ($hasRating && $minRating !== '' && is_numeric($minRating) && (float)$minRating > 0)
    $conditions[] = "mf.Rating >= " . (float)$minRating;

$result = [];

$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'vendor'");
if ($tableCheck && mysqli_num_rows($tableCheck) > 0 && $hasVendorId) {
    $latSel = $hasLat ? "v.Latitude" : "NULL AS Latitude";
    $lngSel = $hasLng ? "v.Longitude" : "NULL AS Longitude";
    $locSel = $hasLoc ? "v.Location"  : "NULL AS Location";
    $latGrp = $hasLat ? ", v.Latitude" : "";
    $lngGrp = $hasLng ? ", v.Longitude" : "";
    $locGrp = $hasLoc ? ", v.Location"  : "";

    $orderClause = "MatchedItems DESC";
    if ($sort === 'price_asc')  $orderClause = "MIN(mf.Price) ASC";
    if ($sort === 'price_desc') $orderClause = "MIN(mf.Price) DESC";

    $sql = "SELECT v.VendorID, v.ShopName, $locSel, $latSel, $lngSel,
                   COUNT(mf.FoodID) AS MatchedItems
            FROM vendor v
            INNER JOIN MENU_FOOD mf ON mf.VendorID = v.VendorID
            WHERE " . implode(" AND ", $conditions) . "
            GROUP BY v.VendorID, v.ShopName$locGrp$latGrp$lngGrp
            ORDER BY $orderClause";

    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $result[] = [
                'vendorId'    => (int)$row['VendorID'],
                'shopName'    => $row['ShopName'] ?? ('Vendor #' . $row['VendorID']),
                'location'    => $row['Location'] ?? '',
                'latitude'    => ($row['Latitude'] !== null && $row['Latitude'] !== '') ? (float)$row['Latitude'] : null,
                'longitude'   => ($row['Longitude'] !== null && $row['Longitude'] !== '') ? (float)$row['Longitude'] : null,
                'matchedItems'=> (int)$row['MatchedItems']
            ];
        }
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
