<?php

function ensureAchievementTables(PDO $pdo): void {
    // Tables are pre-created in Supabase. This function is a no-op kept for API compatibility.
}

function getCriteriaTypeLabel(string $type): string {
    $labels = [
        'order_count'        => 'Completed orders',
        'total_spent'        => 'Total spent (RM)',
        'unique_vendors'     => 'Unique restaurants',
        'unique_categories'  => 'Unique food categories',
        'order_specific_food' => 'Order specific food',
    ];
    return $labels[$type] ?? $type;
}

function getRewardTypeLabel(string $type, $value): string {
    if ($type === 'percent') {
        return number_format((float)$value, 0) . '% off';
    }
    return 'RM ' . number_format((float)$value, 2) . ' off';
}

function getUserOrderStats(PDO $pdo, int $userId): array {
    $stats = [
        'order_count'      => 0,
        'total_spent'      => 0.0,
        'unique_vendors'   => 0,
        'unique_categories'=> 0,
        'dietary_counts'   => [],
    ];

    $row = $pdo->prepare(
        'SELECT COUNT(*) AS order_count,
                COALESCE(SUM(o."TotalAmount"), 0) AS total_spent,
                COUNT(DISTINCT mf."VendorID") AS unique_vendors,
                COUNT(DISTINCT mf."Category") AS unique_categories
         FROM orders o
         INNER JOIN menu_food mf ON mf."FoodID" = o."FoodID"
         WHERE o."UserID" = ? AND o."Status" IN (\'Completed\', \'Finished\')'
    );
    $row->execute([$userId]);
    $r = $row->fetch();
    if ($r) {
        $stats['order_count']       = (int)$r['order_count'];
        $stats['total_spent']       = (float)$r['total_spent'];
        $stats['unique_vendors']    = (int)$r['unique_vendors'];
        $stats['unique_categories'] = (int)$r['unique_categories'];
    }

    $tagStmt = $pdo->prepare(
        'SELECT mf."DietaryTag", COUNT(*) AS cnt
         FROM orders o
         INNER JOIN menu_food mf ON mf."FoodID" = o."FoodID"
         WHERE o."UserID" = ? AND o."Status" IN (\'Completed\', \'Finished\')
           AND mf."DietaryTag" IS NOT NULL AND mf."DietaryTag" != \'\'
         GROUP BY mf."DietaryTag"'
    );
    $tagStmt->execute([$userId]);
    while ($tRow = $tagStmt->fetch()) {
        $stats['dietary_counts'][$tRow['DietaryTag']] = (int)$tRow['cnt'];
    }

    return $stats;
}

function getAchievementProgress(array $stats, array $achievement): array {
    $type   = $achievement['CriteriaType'];
    $target = (float)$achievement['CriteriaValue'];

    switch ($type) {
        case 'order_count':       $current = (float)$stats['order_count'];       break;
        case 'total_spent':       $current = (float)$stats['total_spent'];        break;
        case 'unique_vendors':    $current = (float)$stats['unique_vendors'];     break;
        case 'unique_categories': $current = (float)$stats['unique_categories'];  break;
        case 'order_specific_food':
            $current = 0.0;
            $requiredTags = !empty($achievement['DietaryTags'])
                ? explode(',', $achievement['DietaryTags'])
                : [];
            foreach ($requiredTags as $tag) {
                $tag = trim($tag);
                if (isset($stats['dietary_counts'][$tag])) {
                    $current += $stats['dietary_counts'][$tag];
                }
            }
            break;
        default: $current = 0.0; break;
    }

    $percent = ($target > 0) ? min(100, ($current / $target) * 100) : 0;
    return [
        'current'     => $current,
        'target'      => $target,
        'percent'     => $percent,
        'is_complete' => $current >= $target,
    ];
}

function formatProgressCurrent(string $type, $value): string {
    return $type === 'total_spent' ? 'RM ' . number_format((float)$value, 2) : (string)(int)$value;
}

function formatProgressTarget(string $type, $value): string {
    return $type === 'total_spent' ? 'RM ' . number_format((float)$value, 2) : (string)(int)$value;
}

function getActiveAchievements(PDO $pdo): array {
    $stmt = $pdo->query('SELECT * FROM achievements WHERE "IsActive" = TRUE ORDER BY "AchievementID" ASC');
    return $stmt ? $stmt->fetchAll() : [];
}

function getAllAchievements(PDO $pdo): array {
    $stmt = $pdo->query('SELECT * FROM achievements ORDER BY "AchievementID" DESC');
    return $stmt ? $stmt->fetchAll() : [];
}

function getUserClaimsMap(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT * FROM user_achievement_claims WHERE "UserID" = ?');
    $stmt->execute([$userId]);
    $claims = [];
    while ($row = $stmt->fetch()) {
        $claims[(int)$row['AchievementID']] = $row;
    }
    return $claims;
}

function getAchievementsForUser(PDO $pdo, int $userId): array {
    ensureAchievementTables($pdo);
    $stats  = getUserOrderStats($pdo, $userId);
    $claims = getUserClaimsMap($pdo, $userId);
    $items  = [];

    foreach (getActiveAchievements($pdo) as $achievement) {
        $achievementId = (int)$achievement['AchievementID'];
        $progress      = getAchievementProgress($stats, $achievement);
        $claim         = $claims[$achievementId] ?? null;

        $items[] = [
            'achievement' => $achievement,
            'progress'    => $progress,
            'claim'       => $claim,
            'status'      => $claim
                ? ($claim['IsUsed'] ? 'used' : 'claimed')
                : ($progress['is_complete'] ? 'ready' : 'in_progress'),
        ];
    }
    return $items;
}

function generateDiscountCode(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = 'CRV-';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function claimAchievement(PDO $pdo, int $userId, int $achievementId): array {
    ensureAchievementTables($pdo);
    if ($achievementId <= 0) return ['success' => false, 'message' => 'Invalid achievement.'];

    $achievement = $pdo->prepare('SELECT * FROM achievements WHERE "AchievementID" = ? AND "IsActive" = TRUE LIMIT 1');
    $achievement->execute([$achievementId]);
    $achievement = $achievement->fetch();
    if (!$achievement) return ['success' => false, 'message' => 'Achievement not found or inactive.'];

    $existing = $pdo->prepare('SELECT "ClaimID" FROM user_achievement_claims WHERE "UserID" = ? AND "AchievementID" = ? LIMIT 1');
    $existing->execute([$userId, $achievementId]);
    if ($existing->fetch()) return ['success' => false, 'message' => 'You already claimed this reward.'];

    $stats    = getUserOrderStats($pdo, $userId);
    $progress = getAchievementProgress($stats, $achievement);
    if (!$progress['is_complete']) return ['success' => false, 'message' => 'Task not completed yet. Keep ordering to unlock this reward.'];

    $attempts    = 0;
    $ok          = false;
    $discountCode = '';
    do {
        $discountCode = generateDiscountCode();
        try {
            $insertStmt = $pdo->prepare(
                'INSERT INTO user_achievement_claims ("UserID", "AchievementID", "DiscountCode", "RewardType", "RewardValue") VALUES (?, ?, ?, ?, ?)'
            );
            $ok = $insertStmt->execute([
                $userId, $achievementId, $discountCode,
                $achievement['RewardType'], (float)$achievement['RewardValue']
            ]);
        } catch (PDOException $e) {
            $ok = false;
        }
        $attempts++;
    } while (!$ok && $attempts < 5);

    if (!$ok) return ['success' => false, 'message' => 'Could not generate discount code. Please try again.'];

    return [
        'success'      => true,
        'message'      => 'Reward claimed! Your discount code is ' . $discountCode . '.',
        'discount_code' => $discountCode,
        'reward_label' => getRewardTypeLabel($achievement['RewardType'], $achievement['RewardValue']),
    ];
}

function getUserUnusedClaims(PDO $pdo, int $userId): array {
    ensureAchievementTables($pdo);
    $stmt = $pdo->prepare(
        'SELECT c.*, a."Title"
         FROM user_achievement_claims c
         INNER JOIN achievements a ON a."AchievementID" = c."AchievementID"
         WHERE c."UserID" = ? AND c."IsUsed" = FALSE
         ORDER BY c."ClaimedAt" ASC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getValidClaimForUser(PDO $pdo, int $userId, int $claimId): ?array {
    if ($claimId <= 0) return null;
    $stmt = $pdo->prepare(
        'SELECT * FROM user_achievement_claims WHERE "ClaimID" = ? AND "UserID" = ? AND "IsUsed" = FALSE LIMIT 1'
    );
    $stmt->execute([$claimId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function calculateDiscountAmount($subtotal, ?array $claim): float {
    $subtotal = max(0, (float)$subtotal);
    if (!$claim || $subtotal <= 0) return 0.0;

    if ($claim['RewardType'] === 'percent') {
        $percent = max(0, min(100, (float)$claim['RewardValue']));
        return round($subtotal * ($percent / 100), 2);
    }
    return round(min((float)$claim['RewardValue'], $subtotal), 2);
}

function markClaimUsed(PDO $pdo, int $claimId, int $userId): bool {
    $stmt = $pdo->prepare(
        'UPDATE user_achievement_claims SET "IsUsed" = TRUE, "UsedAt" = NOW() WHERE "ClaimID" = ? AND "UserID" = ? AND "IsUsed" = FALSE'
    );
    return $stmt->execute([$claimId, $userId]) && $stmt->rowCount() > 0;
}

function distributeDiscountedTotals(array $orderItems, float $discountAmount): array {
    $subtotal = array_sum(array_map(fn($i) => (float)$i['Price'] * (int)$i['Quantity'], $orderItems));
    $discountAmount = min((float)$discountAmount, $subtotal);
    $finalTotal = max(0, $subtotal - $discountAmount);
    $adjusted   = [];
    $running    = 0.0;
    $count      = count($orderItems);

    foreach ($orderItems as $index => $item) {
        $lineSubtotal = (float)$item['Price'] * (int)$item['Quantity'];
        if ($subtotal <= 0) {
            $lineTotal = $lineSubtotal;
        } elseif ($index === $count - 1) {
            $lineTotal = round($finalTotal - $running, 2);
        } else {
            $lineTotal = round(($lineSubtotal / $subtotal) * $finalTotal, 2);
            $running  += $lineTotal;
        }

        $adjusted[] = [
            'FoodID'      => (int)$item['FoodID'],
            'Quantity'    => max(1, (int)$item['Quantity']),
            'TotalAmount' => max(0, $lineTotal),
        ];
    }
    return $adjusted;
}
?>
