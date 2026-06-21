<?php
session_start();
include('db.php');
include('db_helpers.php');
include('recommendations.php');

header('Content-Type: application/json');

if (!isset($_SESSION['UserID'])) {
    echo json_encode(['success' => false, 'needs_login' => true, 'message' => 'Please log in to add items to your cart.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['vendor_id' => null, 'vendor_name' => '', 'items' => []];
}

switch ($action) {

    case 'add':
        $foodId   = (int)($_POST['food_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if ($foodId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid food item.']);
            exit();
        }

        $food = db_fetch_one($pdo,
            'SELECT mf."FoodID", mf."FoodName", mf."Price", mf."Description", mf."DietaryTag", mf."VendorID", mf."Image", v."ShopName"
             FROM menu_food mf
             LEFT JOIN vendor v ON mf."VendorID" = v."VendorID"
             WHERE mf."FoodID" = ? AND mf."Status" = \'Available\'',
            [$foodId]
        );

        if (!$food) {
            echo json_encode(['success' => false, 'message' => 'Food item not found or unavailable.']);
            exit();
        }

        $vendorId = (int)$food['VendorID'];

        // Single-vendor restriction
        if ($_SESSION['cart']['vendor_id'] !== null && $_SESSION['cart']['vendor_id'] !== $vendorId) {
            echo json_encode([
                'success'         => false,
                'vendor_conflict' => true,
                'message'         => 'Your cart has items from "' . $_SESSION['cart']['vendor_name'] . '". You can only order from one restaurant at a time. Clear your cart first to add items from "' . $food['ShopName'] . '".'
            ]);
            exit();
        }

        if ($_SESSION['cart']['vendor_id'] === null) {
            $_SESSION['cart']['vendor_id']   = $vendorId;
            $_SESSION['cart']['vendor_name'] = $food['ShopName'];
        }

        $found = false;
        foreach ($_SESSION['cart']['items'] as &$item) {
            if ($item['FoodID'] === $foodId) {
                $item['Quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            $_SESSION['cart']['items'][] = [
                'FoodID'     => $foodId,
                'FoodName'   => $food['FoodName'],
                'Price'      => (float)$food['Price'],
                'Quantity'   => $quantity,
                'Image'      => $food['Image'],
                'DietaryTag' => $food['DietaryTag']
            ];
        }

        recordCartActivity($pdo, $foodId);

        $totalItems = array_sum(array_column($_SESSION['cart']['items'], 'Quantity'));
        echo json_encode(['success' => true, 'message' => $food['FoodName'] . ' added to cart.', 'cart_count' => $totalItems]);
        break;

    case 'update':
        $foodId   = (int)($_POST['food_id'] ?? 0);
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));

        foreach ($_SESSION['cart']['items'] as $key => &$item) {
            if ($item['FoodID'] === $foodId) {
                if ($quantity <= 0) {
                    array_splice($_SESSION['cart']['items'], $key, 1);
                } else {
                    $item['Quantity'] = $quantity;
                }
                break;
            }
        }
        unset($item);

        if (count($_SESSION['cart']['items']) === 0) {
            $_SESSION['cart']['vendor_id']   = null;
            $_SESSION['cart']['vendor_name'] = '';
        }

        $totalItems = array_sum(array_column($_SESSION['cart']['items'], 'Quantity'));
        echo json_encode(['success' => true, 'message' => 'Cart updated.', 'cart_count' => $totalItems]);
        break;

    case 'remove':
        $foodId = (int)($_POST['food_id'] ?? 0);
        $_SESSION['cart']['items'] = array_values(array_filter(
            $_SESSION['cart']['items'],
            fn($item) => $item['FoodID'] !== $foodId
        ));

        if (count($_SESSION['cart']['items']) === 0) {
            $_SESSION['cart']['vendor_id']   = null;
            $_SESSION['cart']['vendor_name'] = '';
        }

        $totalItems = array_sum(array_column($_SESSION['cart']['items'], 'Quantity'));
        echo json_encode(['success' => true, 'message' => 'Item removed from cart.', 'cart_count' => $totalItems]);
        break;

    case 'clear':
        $_SESSION['cart'] = ['vendor_id' => null, 'vendor_name' => '', 'items' => []];
        echo json_encode(['success' => true, 'message' => 'Cart cleared.', 'cart_count' => 0]);
        break;

    case 'get':
        $totalItems  = array_sum(array_column($_SESSION['cart']['items'], 'Quantity'));
        $totalAmount = array_sum(array_map(fn($ci) => $ci['Price'] * $ci['Quantity'], $_SESSION['cart']['items']));
        echo json_encode([
            'success'      => true,
            'vendor_id'    => $_SESSION['cart']['vendor_id'],
            'vendor_name'  => $_SESSION['cart']['vendor_name'],
            'items'        => $_SESSION['cart']['items'],
            'cart_count'   => $totalItems,
            'total_amount' => round($totalAmount, 2)
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
?>
