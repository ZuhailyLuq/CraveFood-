<?php

function ensureAchievementTables($conn) {
    $createAchievements = "CREATE TABLE IF NOT EXISTS `achievements` (
        `AchievementID` INT AUTO_INCREMENT PRIMARY KEY,
        `Title` VARCHAR(100) NOT NULL,
        `Description` TEXT,
        `Icon` VARCHAR(20) DEFAULT '🏆',
        `CriteriaType` ENUM('order_count', 'total_spent', 'unique_vendors', 'unique_categories', 'order_specific_food') NOT NULL,
        `CriteriaValue` DECIMAL(10,2) NOT NULL DEFAULT 1,
        `RewardType` ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
        `RewardValue` DECIMAL(10,2) NOT NULL DEFAULT 10,
        `IsActive` TINYINT(1) NOT NULL DEFAULT 1,
        `DietaryTags` VARCHAR(255) DEFAULT NULL,
        `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $createAchievements);

    // Ensure DietaryTags column exists for existing tables
    $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM `achievements` LIKE 'DietaryTags'");
    if (mysqli_num_rows($checkCol) == 0) {
        mysqli_query($conn, "ALTER TABLE `achievements` ADD COLUMN `DietaryTags` VARCHAR(255) DEFAULT NULL");
        mysqli_query($conn, "ALTER TABLE `achievements` MODIFY COLUMN `CriteriaType` ENUM('order_count', 'total_spent', 'unique_vendors', 'unique_categories', 'order_specific_food') NOT NULL");
    }

    $createClaims = "CREATE TABLE IF NOT EXISTS `user_achievement_claims` (
        `ClaimID` INT AUTO_INCREMENT PRIMARY KEY,
        `UserID` INT NOT NULL,
        `AchievementID` INT NOT NULL,
        `DiscountCode` VARCHAR(20) NOT NULL,
        `RewardType` ENUM('percent', 'fixed') NOT NULL,
        `RewardValue` DECIMAL(10,2) NOT NULL,
        `IsUsed` TINYINT(1) NOT NULL DEFAULT 0,
        `UsedAt` DATETIME DEFAULT NULL,
        `ClaimedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_user_achievement` (`UserID`, `AchievementID`),
        UNIQUE KEY `uq_discount_code` (`DiscountCode`),
        INDEX (`UserID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $createClaims);
}

function getCriteriaTypeLabel($type) {
    $labels = [
        'order_count' => 'Completed orders',
        'total_spent' => 'Total spent (RM)',
        'unique_vendors' => 'Unique restaurants',
        'unique_categories' => 'Unique food categories',
        'order_specific_food' => 'Order specific food',
    ];
    return $labels[$type] ?? $type;
}

function getRewardTypeLabel($type, $value) {
    if ($type === 'percent') {
        return number_format((float)$value, 0) . '% off';
    }
    return 'RM ' . number_format((float)$value, 2) . ' off';
}

function getUserOrderStats($conn, $userId) {
    $stats = [
        'order_count' => 0,
        'total_spent' => 0.0,
        'unique_vendors' => 0,
        'unique_categories' => 0,
        'dietary_counts' => [],
    ];

    $sql = "SELECT COUNT(*) AS order_count,
                   COALESCE(SUM(o.TotalAmount), 0) AS total_spent,
                   COUNT(DISTINCT mf.VendorID) AS unique_vendors,
                   COUNT(DISTINCT mf.Category) AS unique_categories
            FROM orders o
            INNER JOIN MENU_FOOD mf ON mf.FoodID = o.FoodID
            WHERE o.UserID = ? AND o.Status IN ('Completed', 'Finished')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $stats['order_count'] = (int)$row['order_count'];
        $stats['total_spent'] = (float)$row['total_spent'];
        $stats['unique_vendors'] = (int)$row['unique_vendors'];
        $stats['unique_categories'] = (int)$row['unique_categories'];
    }

    $tagSql = "SELECT mf.DietaryTag, COUNT(*) AS cnt 
               FROM orders o 
               INNER JOIN MENU_FOOD mf ON mf.FoodID = o.FoodID 
               WHERE o.UserID = ? AND o.Status IN ('Completed', 'Finished') AND mf.DietaryTag IS NOT NULL AND mf.DietaryTag != ''
               GROUP BY mf.DietaryTag";
    $tagStmt = mysqli_prepare($conn, $tagSql);
    mysqli_stmt_bind_param($tagStmt, "i", $userId);
    mysqli_stmt_execute($tagStmt);
    $tagRes = mysqli_stmt_get_result($tagStmt);
    while ($tRow = mysqli_fetch_assoc($tagRes)) {
        $stats['dietary_counts'][$tRow['DietaryTag']] = (int)$tRow['cnt'];
    }

    return $stats;
}

function getAchievementProgress($stats, $achievement) {
    $type = $achievement['CriteriaType'];
    $target = (float)$achievement['CriteriaValue'];

    switch ($type) {
        case 'order_count':
            $current = (float)$stats['order_count'];
            break;
        case 'total_spent':
            $current = (float)$stats['total_spent'];
            break;
        case 'unique_vendors':
            $current = (float)$stats['unique_vendors'];
            break;
        case 'unique_categories':
            $current = (float)$stats['unique_categories'];
            break;
        case 'order_specific_food':
            $current = 0.0;
            $requiredTags = !empty($achievement['DietaryTags']) ? explode(',', $achievement['DietaryTags']) : [];
            foreach ($requiredTags as $tag) {
                $tag = trim($tag);
                if (isset($stats['dietary_counts'][$tag])) {
                    $current += $stats['dietary_counts'][$tag];
                }
            }
            break;
        default:
            $current = 0.0;
            break;
    }

    $percent = ($target > 0) ? min(100, ($current / $target) * 100) : 0;

    return [
        'current' => $current,
        'target' => $target,
        'percent' => $percent,
        'is_complete' => $current >= $target,
    ];
}

function formatProgressCurrent($type, $value) {
    if ($type === 'total_spent') {
        return 'RM ' . number_format((float)$value, 2);
    }
    return (string)(int)$value;
}

function formatProgressTarget($type, $value) {
    if ($type === 'total_spent') {
        return 'RM ' . number_format((float)$value, 2);
    }
    return (string)(int)$value;
}

function getActiveAchievements($conn) {
    $achievements = [];
    $sql = "SELECT * FROM achievements WHERE IsActive = 1 ORDER BY AchievementID ASC";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $achievements[] = $row;
        }
    }
    return $achievements;
}

function getAllAchievements($conn) {
    $achievements = [];
    $sql = "SELECT * FROM achievements ORDER BY AchievementID DESC";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $achievements[] = $row;
        }
    }
    return $achievements;
}

function getUserClaimsMap($conn, $userId) {
    $claims = [];
    $sql = "SELECT * FROM user_achievement_claims WHERE UserID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $claims[(int)$row['AchievementID']] = $row;
    }
    return $claims;
}

function getAchievementsForUser($conn, $userId) {
    ensureAchievementTables($conn);

    $stats = getUserOrderStats($conn, $userId);
    $claims = getUserClaimsMap($conn, $userId);
    $items = [];

    foreach (getActiveAchievements($conn) as $achievement) {
        $achievementId = (int)$achievement['AchievementID'];
        $progress = getAchievementProgress($stats, $achievement);
        $claim = $claims[$achievementId] ?? null;

        $items[] = [
            'achievement' => $achievement,
            'progress' => $progress,
            'claim' => $claim,
            'status' => $claim
                ? ($claim['IsUsed'] ? 'used' : 'claimed')
                : ($progress['is_complete'] ? 'ready' : 'in_progress'),
        ];
    }

    return $items;
}

function generateDiscountCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = 'CRV-';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function claimAchievement($conn, $userId, $achievementId) {
    ensureAchievementTables($conn);
    $achievementId = (int)$achievementId;
    $userId = (int)$userId;

    if ($achievementId <= 0) {
        return ['success' => false, 'message' => 'Invalid achievement.'];
    }

    $sql = "SELECT * FROM achievements WHERE AchievementID = ? AND IsActive = 1 LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $achievementId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $achievement = mysqli_fetch_assoc($result);

    if (!$achievement) {
        return ['success' => false, 'message' => 'Achievement not found or inactive.'];
    }

    $existingSql = "SELECT ClaimID FROM user_achievement_claims WHERE UserID = ? AND AchievementID = ? LIMIT 1";
    $existingStmt = mysqli_prepare($conn, $existingSql);
    mysqli_stmt_bind_param($existingStmt, "ii", $userId, $achievementId);
    mysqli_stmt_execute($existingStmt);
    $existingResult = mysqli_stmt_get_result($existingStmt);
    if (mysqli_fetch_assoc($existingResult)) {
        return ['success' => false, 'message' => 'You already claimed this reward.'];
    }

    $stats = getUserOrderStats($conn, $userId);
    $progress = getAchievementProgress($stats, $achievement);
    if (!$progress['is_complete']) {
        return ['success' => false, 'message' => 'Task not completed yet. Keep ordering to unlock this reward.'];
    }

    $attempts = 0;
    do {
        $discountCode = generateDiscountCode();
        $insertSql = "INSERT INTO user_achievement_claims
                      (UserID, AchievementID, DiscountCode, RewardType, RewardValue)
                      VALUES (?, ?, ?, ?, ?)";
        $insertStmt = mysqli_prepare($conn, $insertSql);
        $rewardType = $achievement['RewardType'];
        $rewardValue = (float)$achievement['RewardValue'];
        mysqli_stmt_bind_param(
            $insertStmt,
            "iissd",
            $userId,
            $achievementId,
            $discountCode,
            $rewardType,
            $rewardValue
        );
        $ok = mysqli_stmt_execute($insertStmt);
        $attempts++;
    } while (!$ok && $attempts < 5);

    if (!$ok) {
        return ['success' => false, 'message' => 'Could not generate discount code. Please try again.'];
    }

    return [
        'success' => true,
        'message' => 'Reward claimed! Your discount code is ' . $discountCode . '.',
        'discount_code' => $discountCode,
        'reward_label' => getRewardTypeLabel($rewardType, $rewardValue),
    ];
}

function getUserUnusedClaims($conn, $userId) {
    ensureAchievementTables($conn);
    $claims = [];
    $sql = "SELECT c.*, a.Title
            FROM user_achievement_claims c
            INNER JOIN achievements a ON a.AchievementID = c.AchievementID
            WHERE c.UserID = ? AND c.IsUsed = 0
            ORDER BY c.ClaimedAt ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $claims[] = $row;
    }
    return $claims;
}

function getValidClaimForUser($conn, $userId, $claimId) {
    $claimId = (int)$claimId;
    $userId = (int)$userId;
    if ($claimId <= 0) {
        return null;
    }

    $sql = "SELECT * FROM user_achievement_claims
            WHERE ClaimID = ? AND UserID = ? AND IsUsed = 0
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $claimId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result) ?: null;
}

function calculateDiscountAmount($subtotal, $claim) {
    $subtotal = max(0, (float)$subtotal);
    if (!$claim || $subtotal <= 0) {
        return 0.0;
    }

    if ($claim['RewardType'] === 'percent') {
        $percent = max(0, min(100, (float)$claim['RewardValue']));
        return round($subtotal * ($percent / 100), 2);
    }

    return round(min((float)$claim['RewardValue'], $subtotal), 2);
}

function markClaimUsed($conn, $claimId, $userId) {
    $sql = "UPDATE user_achievement_claims
            SET IsUsed = 1, UsedAt = NOW()
            WHERE ClaimID = ? AND UserID = ? AND IsUsed = 0";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $claimId, $userId);
    return mysqli_stmt_execute($stmt);
}

function distributeDiscountedTotals($orderItems, $discountAmount) {
    $subtotal = 0.0;
    foreach ($orderItems as $item) {
        $subtotal += (float)$item['Price'] * (int)$item['Quantity'];
    }

    $discountAmount = min((float)$discountAmount, $subtotal);
    $finalTotal = max(0, $subtotal - $discountAmount);
    $adjusted = [];
    $running = 0.0;
    $count = count($orderItems);

    foreach ($orderItems as $index => $item) {
        $lineSubtotal = (float)$item['Price'] * (int)$item['Quantity'];
        if ($subtotal <= 0) {
            $lineTotal = $lineSubtotal;
        } elseif ($index === $count - 1) {
            $lineTotal = round($finalTotal - $running, 2);
        } else {
            $share = ($lineSubtotal / $subtotal) * $finalTotal;
            $lineTotal = round($share, 2);
            $running += $lineTotal;
        }

        $adjusted[] = [
            'FoodID' => (int)$item['FoodID'],
            'Quantity' => max(1, (int)$item['Quantity']),
            'TotalAmount' => max(0, $lineTotal),
        ];
    }

    return $adjusted;
}

?>
