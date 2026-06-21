<?php
session_start();
include('db.php');
include('recommendations.php');
include('achievement_helpers.php');

if(!isset($_SESSION['UserID'])){
    header("Location: Login.html");
    exit();
}

function orderColumnExists($conn, $column) {
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `orders` LIKE '$columnEsc'");
    return $res && mysqli_num_rows($res) > 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['Action_Order'])) {
    $UserID = $_SESSION['UserID'];
    $OrderType = $_POST['OrderType'] ?? 'Dine-In';
    $PickupTime = ($OrderType === 'Book') ? ($_POST['PickupTime'] ?? NULL) : NULL;
    $ReservationMode = $_POST['ReservationMode'] ?? 'Dine-In';
    $SeatCount = max(1, (int)($_POST['SeatCount'] ?? 1));
    $DirectSeatCount = max(1, (int)($_POST['DirectSeatCount'] ?? 1));
    $Status = "Pending";
    $fromCart = isset($_POST['from_cart']) && $_POST['from_cart'] == '1';

    if (!in_array($OrderType, ['Dine-In', 'Pickup', 'Book'], true)) {
        header("Location: Homepage.php?type=error&msg=" . urlencode("Invalid order type."));
        exit();
    }

    if ($OrderType === 'Book' && empty($PickupTime)) {
        header("Location: Homepage.php?type=error&msg=" . urlencode("Pickup date/time is required for Book orders."));
        exit();
    }

    if ($OrderType === 'Book' && !in_array($ReservationMode, ['Dine-In', 'Pickup'], true)) {
        header("Location: Homepage.php?type=error&msg=" . urlencode("Invalid reservation type."));
        exit();
    }

    if ($OrderType === 'Book' && $ReservationMode === 'Dine-In' && $SeatCount < 1) {
        header("Location: Homepage.php?type=error&msg=" . urlencode("Seat count must be at least 1 for dine-in bookings."));
        exit();
    }

    if ($OrderType === 'Dine-In' && $DirectSeatCount < 1) {
        header("Location: Homepage.php?type=error&msg=" . urlencode("Seat count must be at least 1 for dine-in orders."));
        exit();
    }

    // Determine items to order
    $orderItems = [];

    if ($fromCart) {
        // Cart-based order
        if (!isset($_SESSION['cart']) || count($_SESSION['cart']['items']) === 0) {
            header("Location: Homepage.php?type=error&msg=" . urlencode("Your cart is empty."));
            exit();
        }
        foreach ($_SESSION['cart']['items'] as $ci) {
            $orderItems[] = [
                'FoodID' => (int)$ci['FoodID'],
                'Quantity' => max(1, (int)$ci['Quantity'])
            ];
        }
    } else {
        // Single item legacy
        $FoodID = (int)($_POST['FoodID'] ?? 0);
        $Quantity = max(1, (int)($_POST['Quantity'] ?? 1));
        if ($FoodID <= 0) {
            header("Location: Homepage.php?type=error&msg=" . urlencode("Invalid food item."));
            exit();
        }
        $orderItems[] = [
            'FoodID' => $FoodID,
            'Quantity' => $Quantity
        ];
    }

    $hasReservationModeColumn = orderColumnExists($conn, 'ReservationMode');
    $hasSeatCountColumn = orderColumnExists($conn, 'SeatCount');

    $pricedItems = [];
    foreach ($orderItems as $item) {
        $foodSql = "SELECT Price FROM MENU_FOOD WHERE FoodID = ? AND Status = 'Available'";
        $foodStmt = mysqli_prepare($conn, $foodSql);
        mysqli_stmt_bind_param($foodStmt, "i", $item['FoodID']);
        mysqli_stmt_execute($foodStmt);
        $foodResult = mysqli_stmt_get_result($foodStmt);
        $foodRow = mysqli_fetch_assoc($foodResult);

        if (!$foodRow) {
            continue;
        }

        $pricedItems[] = [
            'FoodID' => (int)$item['FoodID'],
            'Quantity' => max(1, (int)$item['Quantity']),
            'Price' => (float)$foodRow['Price'],
        ];
    }

    if (count($pricedItems) === 0) {
        header("Location: Homepage.php?type=error&msg=" . urlencode("No available items to order."));
        exit();
    }

    $subtotal = 0.0;
    foreach ($pricedItems as $item) {
        $subtotal += $item['Price'] * $item['Quantity'];
    }

    $claimId = (int)($_POST['claim_id'] ?? 0);
    $claim = null;
    $discountAmount = 0.0;
    if ($claimId > 0) {
        $claim = getValidClaimForUser($conn, (int)$UserID, $claimId);
        if (!$claim) {
            header("Location: OrderOption.php?from_cart=" . ($fromCart ? '1' : '0') . "&type=error&msg=" . urlencode("Invalid or already used discount code."));
            exit();
        }
        $discountAmount = calculateDiscountAmount($subtotal, $claim);
    }

    $adjustedItems = distributeDiscountedTotals($pricedItems, $discountAmount);

    $lastOrderId = null;
    $allSuccess = true;

    foreach ($adjustedItems as $item) {
        $FoodID = $item['FoodID'];
        $TotalAmount = $item['TotalAmount'];

        if ($hasReservationModeColumn && $hasSeatCountColumn) {
            $ReservationModeValue = ($OrderType === 'Book') ? $ReservationMode : NULL;
            $SeatCountValue = NULL;
            if ($OrderType === 'Book' && $ReservationMode === 'Dine-In') {
                $SeatCountValue = $SeatCount;
            } elseif ($OrderType === 'Dine-In') {
                $SeatCountValue = $DirectSeatCount;
            }
            $sql = "INSERT INTO `orders` (UserID, FoodID, OrderType, PickupTime, TotalAmount, Status, ReservationMode, SeatCount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iissdssi", $UserID, $FoodID, $OrderType, $PickupTime, $TotalAmount, $Status, $ReservationModeValue, $SeatCountValue);
        } elseif ($hasReservationModeColumn) {
            $ReservationModeValue = ($OrderType === 'Book') ? $ReservationMode : NULL;
            $sql = "INSERT INTO `orders` (UserID, FoodID, OrderType, PickupTime, TotalAmount, Status, ReservationMode) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iissdss", $UserID, $FoodID, $OrderType, $PickupTime, $TotalAmount, $Status, $ReservationModeValue);
        } elseif ($hasSeatCountColumn) {
            $SeatCountValue = ($OrderType === 'Book' && $ReservationMode === 'Dine-In') ? $SeatCount : NULL;
            $sql = "INSERT INTO `orders` (UserID, FoodID, OrderType, PickupTime, TotalAmount, Status, SeatCount) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iissdsi", $UserID, $FoodID, $OrderType, $PickupTime, $TotalAmount, $Status, $SeatCountValue);
        } else {
            $sql = "INSERT INTO `orders` (UserID, FoodID, OrderType, PickupTime, TotalAmount, Status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iissds", $UserID, $FoodID, $OrderType, $PickupTime, $TotalAmount, $Status);
        }

        if (mysqli_stmt_execute($stmt)) {
            $lastOrderId = mysqli_insert_id($conn);
        } else {
            $allSuccess = false;
        }
        mysqli_stmt_close($stmt);
    }

    // Clear cart after successful order
    if ($fromCart && $allSuccess && $lastOrderId) {
        $_SESSION['cart'] = [
            'vendor_id' => null,
            'vendor_name' => '',
            'items' => []
        ];
    }

    if ($lastOrderId) {
        if ($claim) {
            markClaimUsed($conn, (int)$claim['ClaimID'], (int)$UserID);
        }

        $lastOrderedFoodId = (int)$orderItems[count($orderItems) - 1]['FoodID'];
        recordOrderActivity($conn, $lastOrderedFoodId);

        $itemCount = count($adjustedItems);
        $msg = $itemCount > 1
            ? "Order placed successfully ($itemCount items)."
            : "Order placed successfully.";
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