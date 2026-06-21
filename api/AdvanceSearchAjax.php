<?php
/**
 * AdvanceSearchAjax.php
 * Returns JSON array of matching vendors for the Advanced Search live filter.
 * Called by AdvanceSearch.php via fetch() whenever a filter changes.
 */
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

// Allow guest access for exploration
header('Content-Type: application/json; charset=utf-8');

$keyword   = trim($_GET['keyword']   ?? '');
$dietary   = trim($_GET['dietary']   ?? '');
$category  = trim($_GET['category']  ?? '');
$minPrice  = trim($_GET['min_price'] ?? '');
$maxPrice  = trim($_GET['max_price'] ?? '');
$sort      = trim($_GET['sort'] ?? 'relevance');

$conditions = ["mf.\"Status\" = 'Available'"];
$params     = [];

if ($keyword !== '') {
    $conditions[] = "(mf.\"FoodName\" ILIKE ? OR mf.\"Description\" ILIKE ? OR v.\"ShopName\" ILIKE ?)";
    $kw = '%' . $keyword . '%';
    $params[] = $kw; $params[] = $kw; $params[] = $kw;
}
if ($dietary !== '') {
    $conditions[] = "mf.\"DietaryTag\" = ?";
    $params[] = $dietary;
}
if ($category !== '') {
    $conditions[] = "mf.\"Category\" = ?";
    $params[] = $category;
}
if ($minPrice !== '' && is_numeric($minPrice)) {
    $conditions[] = "mf.\"Price\" >= ?";
    $params[] = (float)$minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $conditions[] = "mf.\"Price\" <= ?";
    $params[] = (float)$maxPrice;
}

$orderClause = "COUNT(mf.\"FoodID\") DESC";
if ($sort === 'price_asc')  $orderClause = "MIN(mf.\"Price\") ASC";
if ($sort === 'price_desc') $orderClause = "MIN(mf.\"Price\") DESC";

$sql = "SELECT v.\"VendorID\", v.\"ShopName\", v.\"Location\", v.\"Latitude\", v.\"Longitude\",
               COUNT(mf.\"FoodID\") AS MatchedItems
        FROM vendor v
        INNER JOIN menu_food mf ON mf.\"VendorID\" = v.\"VendorID\"
        WHERE " . implode(" AND ", $conditions) . "
        GROUP BY v.\"VendorID\", v.\"ShopName\", v.\"Location\", v.\"Latitude\", v.\"Longitude\"
        ORDER BY $orderClause";

$rows   = db_fetch_all($pdo, $sql, $params);
$result = [];
foreach ($rows as $row) {
    $result[] = [
        'vendorId'     => (int)$row['VendorID'],
        'shopName'     => $row['ShopName'] ?? ('Vendor #' . $row['VendorID']),
        'location'     => $row['Location'] ?? '',
        'latitude'     => ($row['Latitude'] !== null && $row['Latitude'] !== '') ? (float)$row['Latitude'] : null,
        'longitude'    => ($row['Longitude'] !== null && $row['Longitude'] !== '') ? (float)$row['Longitude'] : null,
        'matchedItems' => (int)$row['MatchedItems']
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>
