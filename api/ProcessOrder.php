<?php
session_start();
include('db.php');
include('db_helpers.php');
include('recommendations.php');
include('achievement_helpers.php');

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['Action_Order'])) {
    $UserID          = $_SESSION['UserID'];
    $OrderType       = $_POST['OrderType'] ?? 'Dine-In';
    $PickupTime      = ($OrderType === 'Book') ? ($_POST['PickupTime'] ?? null) : null;
    $ReservationMode = $_POST['ReservationMode'] ?? 'Dine-In';
    $SeatCount       = max(1, (int)($_POST['SeatCount'] ?? 1));
    $DirectSeatCount = max(1, (int)($_POST['DirectSeatCount'] ?? 1));
    $Status          = "Pending";
    $fromCart        = isset($_POST['from_cart']) && $_POST['from_cart'] == '1';

    if (!in_array($OrderType, ['Dine-In', 'Pickup', 'Book'], true)) {
        header("Location: Homepage.php?type=error&msg=" . urlencode("Invalid order type.")); exit();
    }
    if ($OrderType === 'Book' && empty($PickupTime)) {
        header("Location: Homepage.php?type=error&msg=" . urlencode("Pickup date/time is required for Book orders.")); exit();
    }

    // Determine items to order
    $orderItems = [];
    if ($fromCart) {
        if (!isset($_SESSION['cart']) || count($_SESSION['cart']['items']) === 0) {
            header("Location: Homepage.php?type=error&msg=" . urlencode("Your cart is empty.")); exit();
        }
        foreach ($_SESSION['cart']['items'] as $ci) {
            $orderItems[] = ['FoodID' => (int)$ci['FoodID'], 'Quantity' => max(1, (int)$ci['Quantity'])];
        }
    } else {
        $FoodID   = (int)($_POST['FoodID'] ?? 0);
        $Quantity = max(1, (int)($_POST['Quantity'] ?? 1));
        if ($FoodID <= 0) {
            header("Location: Homepage.php?type=error&msg=" . urlencode("Invalid food item.")); exit();
        }
        $orderItems[] = ['FoodID' => $FoodID, 'Quantity' => $Quantity];
    }

    // Price each item
    $pricedItems = [];
    foreach ($orderItems as $item) {
        $foodRow = db_fetch_one($pdo,
            'SELECT "Price" FROM menu_food WHERE "FoodID" = ? AND "Status" = \'Available\'',
            [$item['FoodID']]
        );
        if (!$foodRow) continue;
        $pricedItems[] = [
            'FoodID'   => (int)$item['FoodID'],
            'Quantity' => max(1, (int)$item['Quantity']),
            'Price'    => (float)$foodRow['Price'],
        ];
    }

    if (count($pricedItems) === 0) {
        header("Location: Homepage.php?type=error&msg=" . urlencode("No available items to order.")); exit();
    }

    $subtotal = array_sum(array_map(fn($i) => $i['Price'] * $i['Quantity'], $pricedItems));

    // Discount / claim
    $claimId        = (int)($_POST['claim_id'] ?? 0);
    $claim          = null;
    $discountAmount = 0.0;
    if ($claimId > 0) {
        $claim = getValidClaimForUser($pdo, (int)$UserID, $claimId);
        if (!$claim) {
            header("Location: OrderOption.php?from_cart=" . ($fromCart ? '1' : '0') . "&type=error&msg=" . urlencode("Invalid or already used discount code."));
            exit();
        }
        $discountAmount = calculateDiscountAmount($subtotal, $claim);
    }

    $adjustedItems = distributeDiscountedTotals($pricedItems, $discountAmount);

    $lastOrderId = null;
    $allSuccess  = true;

    foreach ($adjustedItems as $item) {
        $rows = db_execute($pdo,
            'INSERT INTO orders ("UserID", "FoodID", "OrderType", "PickupTime", "TotalAmount", "Status") VALUES (?, ?, ?, ?, ?, ?)',
            [$UserID, $item['FoodID'], $OrderType, $PickupTime, $item['TotalAmount'], $Status]
        );
        if ($rows > 0) {
            $lastOrderId = db_last_id($pdo, 'orders_OrderID_seq');
        } else {
            $allSuccess = false;
        }
    }

    if ($fromCart && $allSuccess && $lastOrderId) {
        $_SESSION['cart'] = ['vendor_id' => null, 'vendor_name' => '', 'items' => []];
    }

    if ($lastOrderId) {
        if ($claim) {
            markClaimUsed($pdo, (int)$claim['ClaimID'], (int)$UserID);
        }
        $lastOrderedFoodId = (int)$orderItems[count($orderItems) - 1]['FoodID'];
        recordOrderActivity($pdo, $lastOrderedFoodId);

        $itemCount = count($adjustedItems);
        $msg = $itemCount > 1 ? "Order placed successfully ($itemCount items)." : "Order placed successfully.";
        if ($discountAmount > 0) {
            $msg .= " Discount applied: RM " . number_format($discountAmount, 2) . ".";
        }
        header("Location: OrderStatus.php?order_id=" . $lastOrderId . "&type=success&msg=" . urlencode($msg));
        exit();
    } else {
        header("Location: Homepage.php?type=error&msg=" . urlencode("Error placing order. Please try again."));
        exit();
    }
}
?>