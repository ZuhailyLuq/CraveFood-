<?php

function initFoodActivitySession() {
    if (!isset($_SESSION['food_activity'])) {
        $_SESSION['food_activity'] = null;
    }
}

function saveFoodActivity($source, $category = '', $dietaryTag = '') {
    initFoodActivitySession();
    $category = trim((string)$category);
    $dietaryTag = trim((string)$dietaryTag);

    if ($category === '' && $dietaryTag === '') {
        return;
    }

    $_SESSION['food_activity'] = [
        'source' => $source,
        'category' => $category,
        'dietary_tag' => $dietaryTag,
        'timestamp' => time()
    ];
}

function dominantValueFromFoodRows($rows, $field) {
    $counts = [];
    foreach ($rows as $row) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value === '') {
            continue;
        }
        if (!isset($counts[$value])) {
            $counts[$value] = 0;
        }
        $counts[$value]++;
    }

    if ($counts === []) {
        return '';
    }

    arsort($counts);
    return (string)array_key_first($counts);
}

function recordSearchActivity($foods, $dietaryFilter = '', $categoryFilter = '') {
    $dietaryTag = trim((string)$dietaryFilter);
    $category = trim((string)$categoryFilter);

    if ($dietaryTag === '' && !empty($foods)) {
        $dietaryTag = dominantValueFromFoodRows($foods, 'DietaryTag');
    }
    if ($category === '' && !empty($foods)) {
        $category = dominantValueFromFoodRows($foods, 'Category');
    }

    saveFoodActivity('search', $category, $dietaryTag);
}

function recordCartActivity($conn, $foodId) {
    recordFoodActivityFromId($conn, $foodId, 'cart');
}

function recordOrderActivity($conn, $foodId) {
    recordFoodActivityFromId($conn, $foodId, 'order');
}

function recordFoodActivityFromId($conn, $foodId, $source) {
    $foodId = (int)$foodId;
    if ($foodId <= 0) {
        return;
    }

    $sql = "SELECT Category, DietaryTag FROM MENU_FOOD WHERE FoodID = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $foodId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $food = mysqli_fetch_assoc($result);

    if (!$food) {
        return;
    }

    saveFoodActivity(
        $source,
        trim((string)($food['Category'] ?? '')),
        trim((string)($food['DietaryTag'] ?? ''))
    );
}

function getRecentOrderActivity($conn, $userId) {
    $sql = "SELECT mf.Category, mf.DietaryTag
            FROM orders o
            INNER JOIN MENU_FOOD mf ON mf.FoodID = o.FoodID
            WHERE o.UserID = ?
            ORDER BY o.OrderID DESC
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if (!$row) {
        return null;
    }

    return [
        'source' => 'order',
        'category' => trim((string)($row['Category'] ?? '')),
        'dietary_tag' => trim((string)($row['DietaryTag'] ?? '')),
        'timestamp' => 0
    ];
}

function buildRecommendationProfile($conn, $userId, $userDietPreference = '') {
    initFoodActivitySession();

    $activity = $_SESSION['food_activity'];
    if (is_array($activity) && ($activity['category'] !== '' || $activity['dietary_tag'] !== '')) {
        return $activity;
    }

    $recentOrder = getRecentOrderActivity($conn, $userId);
    if ($recentOrder && ($recentOrder['category'] !== '' || $recentOrder['dietary_tag'] !== '')) {
        return $recentOrder;
    }

    $dietaryTag = trim((string)$userDietPreference);
    if ($dietaryTag !== '') {
        return [
            'source' => 'profile',
            'category' => '',
            'dietary_tag' => $dietaryTag,
            'timestamp' => 0
        ];
    }

    return [
        'source' => '',
        'category' => '',
        'dietary_tag' => '',
        'timestamp' => 0
    ];
}

function getRecommendationNote($profile) {
    $parts = [];
    if (!empty($profile['category'])) {
        $parts[] = $profile['category'];
    }
    if (!empty($profile['dietary_tag'])) {
        $parts[] = $profile['dietary_tag'];
    }

    if ($parts === []) {
        return '';
    }

    $label = implode(' · ', $parts);

    if ($profile['source'] === 'search') {
        return 'Based on your recent search: <strong>' . htmlspecialchars($label) . '</strong>';
    }
    if ($profile['source'] === 'cart') {
        return 'Based on what you added to cart: <strong>' . htmlspecialchars($label) . '</strong>';
    }
    if ($profile['source'] === 'order') {
        return 'Based on your recent order: <strong>' . htmlspecialchars($label) . '</strong>';
    }

    return 'Matched to your preference: <strong>' . htmlspecialchars($label) . '</strong>';
}

function fetchRecommendedFoods($conn, $profile, $limit = 8) {
    $limit = max(1, (int)$limit);
    $category = trim((string)($profile['category'] ?? ''));
    $dietaryTag = trim((string)($profile['dietary_tag'] ?? ''));

    $conditions = ["mf.Status = 'Available'"];
    $types = '';
    $params = [];

    if ($category !== '') {
        $conditions[] = 'mf.Category = ?';
        $types .= 's';
        $params[] = $category;
    }
    if ($dietaryTag !== '') {
        $conditions[] = 'mf.DietaryTag = ?';
        $types .= 's';
        $params[] = $dietaryTag;
    }

    $foods = [];

    if ($category !== '' || $dietaryTag !== '') {
        $sql = "SELECT mf.FoodID, mf.FoodName, mf.Price, mf.Description, mf.DietaryTag, mf.Category, mf.VendorID, v.ShopName, mf.Image
                FROM MENU_FOOD mf
                LEFT JOIN vendor v ON mf.VendorID = v.VendorID
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY RAND()
                LIMIT ?";
        $types .= 'i';
        $params[] = $limit;

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $foods[] = $row;
            }
        }
    }

    if (count($foods) === 0) {
        $fallbackSql = "SELECT mf.FoodID, mf.FoodName, mf.Price, mf.Description, mf.DietaryTag, mf.Category, mf.VendorID, v.ShopName, mf.Image
                        FROM MENU_FOOD mf
                        LEFT JOIN vendor v ON mf.VendorID = v.VendorID
                        WHERE mf.Status = 'Available'
                        ORDER BY RAND()
                        LIMIT ?";
        $fallbackStmt = mysqli_prepare($conn, $fallbackSql);
        mysqli_stmt_bind_param($fallbackStmt, "i", $limit);
        mysqli_stmt_execute($fallbackStmt);
        $fallbackResult = mysqli_stmt_get_result($fallbackStmt);
        while ($row = mysqli_fetch_assoc($fallbackResult)) {
            $foods[] = $row;
        }
    }

    return $foods;
}

?>
