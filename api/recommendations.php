<?php

function initFoodActivitySession() {
    if (!isset($_SESSION['food_activity'])) {
        $_SESSION['food_activity'] = null;
    }
}

function saveFoodActivity($source, $category = '', $dietaryTag = '') {
    initFoodActivitySession();
    $category  = trim((string)$category);
    $dietaryTag = trim((string)$dietaryTag);

    if ($category === '' && $dietaryTag === '') {
        return;
    }

    $_SESSION['food_activity'] = [
        'source'      => $source,
        'category'    => $category,
        'dietary_tag' => $dietaryTag,
        'timestamp'   => time()
    ];
}

function dominantValueFromFoodRows($rows, $field) {
    $counts = [];
    foreach ($rows as $row) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value === '') continue;
        $counts[$value] = ($counts[$value] ?? 0) + 1;
    }
    if ($counts === []) return '';
    arsort($counts);
    return (string)array_key_first($counts);
}

function recordSearchActivity($foods, $dietaryFilter = '', $categoryFilter = '') {
    $dietaryTag = trim((string)$dietaryFilter);
    $category   = trim((string)$categoryFilter);

    if ($dietaryTag === '' && !empty($foods)) {
        $dietaryTag = dominantValueFromFoodRows($foods, 'DietaryTag');
    }
    if ($category === '' && !empty($foods)) {
        $category = dominantValueFromFoodRows($foods, 'Category');
    }

    saveFoodActivity('search', $category, $dietaryTag);
}

function recordCartActivity($pdo, $foodId) {
    recordFoodActivityFromId($pdo, $foodId, 'cart');
}

function recordOrderActivity($pdo, $foodId) {
    recordFoodActivityFromId($pdo, $foodId, 'order');
}

function recordFoodActivityFromId($pdo, $foodId, $source) {
    $foodId = (int)$foodId;
    if ($foodId <= 0) return;

    $stmt = $pdo->prepare('SELECT "Category", "DietaryTag" FROM menu_food WHERE "FoodID" = ? LIMIT 1');
    $stmt->execute([$foodId]);
    $food = $stmt->fetch();

    if (!$food) return;

    saveFoodActivity(
        $source,
        trim((string)($food['Category'] ?? '')),
        trim((string)($food['DietaryTag'] ?? ''))
    );
}

function getRecentOrderActivity($pdo, $userId) {
    $stmt = $pdo->prepare(
        'SELECT mf."Category", mf."DietaryTag"
         FROM orders o
         INNER JOIN menu_food mf ON mf."FoodID" = o."FoodID"
         WHERE o."UserID" = ?
         ORDER BY o."OrderID" DESC
         LIMIT 1'
    );
    $stmt->execute([(int)$userId]);
    $row = $stmt->fetch();

    if (!$row) return null;

    return [
        'source'      => 'order',
        'category'    => trim((string)($row['Category'] ?? '')),
        'dietary_tag' => trim((string)($row['DietaryTag'] ?? '')),
        'timestamp'   => 0
    ];
}

function buildRecommendationProfile($pdo, $userId, $userDietPreference = '') {
    initFoodActivitySession();

    $activity = $_SESSION['food_activity'];
    if (is_array($activity) && ($activity['category'] !== '' || $activity['dietary_tag'] !== '')) {
        return $activity;
    }

    $recentOrder = getRecentOrderActivity($pdo, $userId);
    if ($recentOrder && ($recentOrder['category'] !== '' || $recentOrder['dietary_tag'] !== '')) {
        return $recentOrder;
    }

    $dietaryTag = trim((string)$userDietPreference);
    if ($dietaryTag !== '') {
        return ['source' => 'profile', 'category' => '', 'dietary_tag' => $dietaryTag, 'timestamp' => 0];
    }

    return ['source' => '', 'category' => '', 'dietary_tag' => '', 'timestamp' => 0];
}

function getRecommendationNote($profile) {
    $parts = [];
    if (!empty($profile['category'])) $parts[] = $profile['category'];
    if (!empty($profile['dietary_tag'])) $parts[] = $profile['dietary_tag'];
    if ($parts === []) return '';

    $label = implode(' Â· ', $parts);
    $sourceMap = [
        'search'  => 'Based on your recent search: <strong>' . htmlspecialchars($label) . '</strong>',
        'cart'    => 'Based on what you added to cart: <strong>' . htmlspecialchars($label) . '</strong>',
        'order'   => 'Based on your recent order: <strong>' . htmlspecialchars($label) . '</strong>',
    ];
    return $sourceMap[$profile['source']] ?? 'Matched to your preference: <strong>' . htmlspecialchars($label) . '</strong>';
}

function fetchRecommendedFoods($pdo, $profile, $limit = 8) {
    $limit      = max(1, (int)$limit);
    $category   = trim((string)($profile['category'] ?? ''));
    $dietaryTag = trim((string)($profile['dietary_tag'] ?? ''));

    $conditions = ["mf.\"Status\" = 'Available'"];
    $params     = [];

    if ($category !== '') {
        $conditions[] = 'mf."Category" = ?';
        $params[] = $category;
    }
    if ($dietaryTag !== '') {
        $conditions[] = 'mf."DietaryTag" = ?';
        $params[] = $dietaryTag;
    }

    $foods = [];

    if ($category !== '' || $dietaryTag !== '') {
        $params[] = $limit;
        $sql = 'SELECT mf."FoodID", mf."FoodName", mf."Price", mf."Description", mf."DietaryTag", mf."Category", mf."VendorID", v."ShopName", mf."Image"
                FROM menu_food mf
                LEFT JOIN vendor v ON mf."VendorID" = v."VendorID"
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY RANDOM()
                LIMIT ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $foods = $stmt->fetchAll();
    }

    if (count($foods) === 0) {
        $stmt = $pdo->prepare(
            'SELECT mf."FoodID", mf."FoodName", mf."Price", mf."Description", mf."DietaryTag", mf."Category", mf."VendorID", v."ShopName", mf."Image"
             FROM menu_food mf
             LEFT JOIN vendor v ON mf."VendorID" = v."VendorID"
             WHERE mf."Status" = \'Available\'
             ORDER BY RANDOM()
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        $foods = $stmt->fetchAll();
    }

    return $foods;
}
?>
