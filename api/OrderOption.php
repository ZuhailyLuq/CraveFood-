<?php
session_start();
include('db.php');
include('db_helpers.php');
include('achievement_helpers.php');

if(!isset($_SESSION['UserID'])){
    header("Location: Homepage.php");
    exit();
}

$userId = (int)$_SESSION['UserID'];

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['vendor_id' => null, 'vendor_name' => '', 'items' => []];
}

// Determine mode: cart-based or single-item (legacy fallback)
$fromCart = isset($_GET['from_cart']) && $_GET['from_cart'] == '1';
$cartItems = [];
$vendorName = '';
$totalAmount = 0;
$totalItems = 0;

if ($fromCart) {
    // Cart-based order
    $cartItems = $_SESSION['cart']['items'];
    $vendorName = $_SESSION['cart']['vendor_name'];

    if (count($cartItems) === 0) {
        header("Location: Cart.php");
        exit();
    }

    foreach ($cartItems as $ci) {
        $totalAmount += $ci['Price'] * $ci['Quantity'];
        $totalItems += $ci['Quantity'];
    }

    $unusedClaims = getUserUnusedClaims($pdo, $userId);

    $PassedOrderType = 'Dine-In';
    $PassedPickupTime = date('Y-m-d\TH:i');
    $seatCount = 1;
    $reservationMode = 'Dine-In';
} else {
    // Single-item legacy mode (fallback)
    if (!isset($_POST['FoodID'])) {
        header("Location: Homepage.php");
        exit();
    }

    $FoodID = $_POST['FoodID'];
    $Quantity = isset($_POST['Quantity']) ? max(1, (int)$_POST['Quantity']) : 1;
    $PassedOrderType = $_POST['PassedOrderType'] ?? 'Dine-In';
    $PassedPickupTime = $_POST['PassedPickupTime'] ?? '';
    $reservationMode = $_POST['ReservationMode'] ?? 'Dine-In';
    $seatCount = isset($_POST['SeatCount']) ? max(1, (int)$_POST['SeatCount']) : 1;
    $defaultDateTime = date('Y-m-d\TH:i');
    if ($PassedPickupTime === '') {
        $PassedPickupTime = $defaultDateTime;
    }

    $food = db_fetch_one($pdo,
        'SELECT "FoodName", "Price", "Description", "DietaryTag" FROM menu_food WHERE "FoodID" = ?',
        [$FoodID]
    );

    if (!$food) {
        header("Location: Homepage.php?type=error&msg=" . urlencode("Selected food item does not exist."));
        exit();
    }

    $cartItems = [[
        'FoodID' => $FoodID,
        'FoodName' => $food['FoodName'],
        'Price' => (float)$food['Price'],
        'Quantity' => $Quantity,
        'DietaryTag' => $food['DietaryTag']
    ]];
    $totalAmount = $food['Price'] * $Quantity;
    $totalItems = $Quantity;
    $unusedClaims = getUserUnusedClaims($pdo, $userId);
}

if (!isset($unusedClaims)) {
    $unusedClaims = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Order - CraveFood</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=20260621-7">
    <style>
        /* ── Reset & base ── */
        *, body { font-family: 'Inter', 'Segoe UI', sans-serif; box-sizing: border-box; }
        body { background: #ffffff; margin: 0; padding: 0; color: #1e1e1e; }

        .checkout-wrapper {
            max-width: 680px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        /* ── Navigation ── */
        .nav-back {
            display: inline-block;
            color: #888;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 20px;
            transition: color 0.2s;
        }
        .nav-back:hover {
            color: #ff2a44;
        }

        /* ── Checkout Card ── */
        .checkout-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 8px 32px rgba(193,18,31,0.06), 0 2px 8px rgba(0,0,0,0.04);
            padding: 32px;
        }

        .checkout-header {
            margin-bottom: 24px;
        }
        .checkout-header h2 {
            margin: 0 0 10px;
            font-size: 1.6rem;
            font-weight: 800;
            color: #2d2d2d;
            letter-spacing: -0.3px;
        }
        .vendor-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f9fafb;
            color: #ff2a44;
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.85rem;
            border: 1px solid #e0e0e0;
        }

        /* ── Items List ── */
        .items-list {
            margin-bottom: 24px;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .item-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .item-name {
            font-weight: 700;
            color: #2d2d2d;
            font-size: 1.05rem;
        }
        .item-meta {
            color: #888;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .item-meta .tag {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .item-subtotal {
            font-weight: 700;
            color: #2d2d2d;
            font-size: 1.05rem;
        }

        /* ── Totals Breakdown ── */
        .totals-breakdown {
            background: #fdfafb;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 32px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #666;
        }
        .totals-row:last-child {
            margin-bottom: 0;
        }
        .totals-row.discount-row {
            color: #28a745;
        }
        .totals-divider {
            height: 1px;
            background: #e9ecef;
            margin: 16px 0;
        }
        .totals-row.final-total {
            font-size: 1.4rem;
            font-weight: 800;
            color: #ff2a44;
        }
        .final-total span:first-child {
            color: #2d2d2d;
        }

        /* ── Forms (Modernized) ── */
        .form-group {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group.hidden {
            display: none;
        }
        .form-group label {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2d2d2d;
        }
        
        .modern-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            color: #2d2d2d;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .modern-input:focus {
            border-color: #ff2a44;
            box-shadow: 0 0 0 3px rgba(193, 18, 31, 0.1);
        }

        .select-with-icon {
            position: relative;
        }
        .select-with-icon svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            fill: #888;
            pointer-events: none;
        }
        .select-with-icon select {
            padding-left: 40px;
        }

        /* ── Final Button ── */
        .btn-confirm {
            display: block;
            width: 100%;
            background: #ff2a44;
            color: #fff;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
            margin-top: 32px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(193,18,31,0.2);
        }
        .btn-confirm:hover {
            background: #a00f1a;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(193,18,31,0.25);
        }
        .btn-confirm:active {
            transform: scale(0.98);
        }

        @media (max-width: 600px) {
            .checkout-wrapper { padding: 24px 16px 60px; }
            .checkout-card { padding: 24px; border-radius: 16px; border: none; border-top: 4px solid #ff2a44; }
        }
    </style>
</head>
<body>
    <?php include('header.php'); ?>

    <div class="checkout-wrapper">
        <a href="<?php echo $fromCart ? 'Cart.php' : 'Homepage.php?search_submitted=1'; ?>" class="nav-back">
            &lt; Back to <?php echo $fromCart ? 'Cart' : 'Search'; ?>
        </a>

        <div class="checkout-card">
            
            <div class="checkout-header">
                <h2>Confirm Your Order</h2>
                <?php if (!empty($vendorName)): ?>
                    <span class="vendor-pill">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        <?php echo htmlspecialchars($vendorName); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="items-list">
                <?php foreach ($cartItems as $item): ?>
                    <div class="item-row">
                        <div class="item-left">
                            <span class="item-name"><?php echo htmlspecialchars($item['FoodName']); ?></span>
                            <span class="item-meta">
                                RM <?php echo number_format($item['Price'], 2); ?> × <?php echo $item['Quantity']; ?>
                                <?php if (!empty($item['DietaryTag'])): ?>
                                    &nbsp;·&nbsp;<span class="tag"><?php echo htmlspecialchars($item['DietaryTag']); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="item-subtotal">RM <?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Totals Breakdown -->
            <div class="totals-breakdown">
                <div class="totals-row">
                    <span>Subtotal (<?php echo $totalItems; ?> item<?php echo $totalItems > 1 ? 's' : ''; ?>)</span>
                    <span id="subtotalDisplay">RM <?php echo number_format($totalAmount, 2); ?></span>
                </div>
                <div class="totals-row discount-row" id="discountRow" style="display:none;">
                    <span>Discount (<span id="discountLabel"></span>)</span>
                    <span id="discountDisplay">- RM 0.00</span>
                </div>
                <div class="totals-divider"></div>
                <div class="totals-row final-total">
                    <span>Total</span>
                    <span id="finalTotalDisplay">RM <?php echo number_format($totalAmount, 2); ?></span>
                </div>
            </div>

            <!-- Checkout Form -->
            <form action="ProcessOrder.php" method="POST" id="orderConfirmForm">
                <input type="hidden" name="from_cart" value="<?php echo $fromCart ? '1' : '0'; ?>">
                <?php if (!$fromCart): ?>
                    <input type="hidden" name="FoodID" value="<?php echo $cartItems[0]['FoodID']; ?>">
                    <input type="hidden" name="Quantity" value="<?php echo $cartItems[0]['Quantity']; ?>">
                <?php endif; ?>

                <!-- Discount -->
                <?php if (count($unusedClaims) > 0): ?>
                    <div class="form-group">
                        <label for="claimSelect">Apply Achievement Discount</label>
                        <div class="select-with-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>
                            </svg>
                            <select id="claimSelect" name="claim_id" class="modern-input" onchange="updateDiscountPreview()">
                                <option value="">No discount</option>
                                <?php foreach ($unusedClaims as $claim): ?>
                                    <option value="<?php echo (int)$claim['ClaimID']; ?>"
                                            data-reward-type="<?php echo htmlspecialchars($claim['RewardType']); ?>"
                                            data-reward-value="<?php echo htmlspecialchars((string)$claim['RewardValue']); ?>">
                                        <?php echo htmlspecialchars($claim['DiscountCode']); ?> —
                                        <?php echo htmlspecialchars($claim['Title']); ?>
                                        (<?php echo htmlspecialchars(getRewardTypeLabel($claim['RewardType'], $claim['RewardValue'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Order Type -->
                <div class="form-group">
                    <label for="OrderType">Dining Preference</label>
                    <select name="OrderType" id="OrderType" class="modern-input" required onchange="toggleBookingFields()">
                        <option value="Dine-In" <?php if($PassedOrderType == 'Dine-In') echo 'selected'; ?>>Dine-In</option>
                        <option value="Pickup" <?php if($PassedOrderType == 'Pickup') echo 'selected'; ?>>Pickup</option>
                        <option value="Book" <?php if($PassedOrderType == 'Book') echo 'selected'; ?>>Reservations (Book)</option>
                    </select>
                </div>

                <!-- Direct Seat Count -->
                <div class="form-group <?php echo ($PassedOrderType == 'Dine-In') ? '' : 'hidden'; ?>" id="directSeatGroup">
                    <label for="DirectSeatCount">Number of Seats</label>
                    <input type="number" name="DirectSeatCount" id="DirectSeatCount" class="modern-input" min="1" value="<?php echo $seatCount; ?>">
                </div>

                <!-- Book Fields -->
                <div id="bookFields" class="<?php echo ($PassedOrderType == 'Book') ? '' : 'hidden'; ?>">
                    <div class="form-group">
                        <label for="ReservationMode">Occasion (Optional)</label>
                        <select name="ReservationMode" id="ReservationMode" class="modern-input" onchange="toggleOccasion()">
                            <option value="None">None</option>
                            <option value="Birthday">Birthday</option>
                            <option value="Farewell">Farewell</option>
                            <option value="Anniversary">Anniversary</option>
                            <option value="Celebration">Celebration</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" name="ReservationModeCustom" id="ReservationModeCustom" class="modern-input hidden" style="margin-top: 8px;" placeholder="e.g., Business Meeting, Graduation">
                    </div>

                    <div class="form-group" id="seatCountGroup">
                        <label for="SeatCount">Number of Seats</label>
                        <input type="number" name="SeatCount" id="SeatCount" class="modern-input" min="1" value="<?php echo $seatCount; ?>">
                    </div>

                    <div class="form-group" id="pickupDiv">
                        <label for="PickupTime">Date & Time</label>
                        <input type="datetime-local" name="PickupTime" id="PickupTime" class="modern-input" value="<?php echo htmlspecialchars($PassedPickupTime); ?>">
                    </div>
                </div>

                <button type="submit" name="Action_Order" id="btnConfirm" class="btn-confirm">
                    Confirm & Place Order &bull; RM <?php echo number_format($totalAmount, 2); ?>
                </button>
            </form>
        </div>
    </div>

<script>
    var baseSubtotal = <?php echo $totalAmount; ?>;

    function toggleBookingFields() {
        var orderType = document.getElementById('OrderType').value;
        var bookFields = document.getElementById('bookFields');
        var directSeatGroup = document.getElementById('directSeatGroup');

        if (orderType === 'Book') {
            bookFields.classList.remove('hidden');
            directSeatGroup.classList.add('hidden');
        } else if (orderType === 'Dine-In') {
            bookFields.classList.add('hidden');
            directSeatGroup.classList.remove('hidden');
        } else {
            // Pickup
            bookFields.classList.add('hidden');
            directSeatGroup.classList.add('hidden');
        }
    }

    function toggleOccasion() {
        var resMode = document.getElementById('ReservationMode').value;
        var custom = document.getElementById('ReservationModeCustom');
        if (resMode === 'Other') {
            custom.classList.remove('hidden');
            custom.required = true;
        } else {
            custom.classList.add('hidden');
            custom.required = false;
            custom.value = '';
        }
    }

    function updateDiscountPreview() {
        var select = document.getElementById('claimSelect');
        var discountRow = document.getElementById('discountRow');
        var labelDisplay = document.getElementById('discountLabel');
        var amountDisplay = document.getElementById('discountDisplay');
        var finalTotalDisplay = document.getElementById('finalTotalDisplay');
        var btnConfirm = document.getElementById('btnConfirm');

        if (!select || !select.value) {
            // No discount selected
            discountRow.style.display = 'none';
            finalTotalDisplay.innerText = 'RM ' + baseSubtotal.toFixed(2);
            btnConfirm.innerHTML = 'Confirm & Place Order &bull; RM ' + baseSubtotal.toFixed(2);
            return;
        }

        var opt = select.options[select.selectedIndex];
        var type = opt.getAttribute('data-reward-type');
        var val = parseFloat(opt.getAttribute('data-reward-value')) || 0;

        var discountAmt = 0;
        if (type === 'percent') {
            discountAmt = baseSubtotal * (val / 100);
        } else {
            discountAmt = val;
        }

        if (discountAmt > baseSubtotal) discountAmt = baseSubtotal;

        var finalTotal = baseSubtotal - discountAmt;

        discountRow.style.display = 'flex';
        
        if (type === 'percent') {
            labelDisplay.innerText = val + '% off';
        } else {
            labelDisplay.innerText = 'RM ' + val.toFixed(2) + ' off';
        }
        
        amountDisplay.innerText = '- RM ' + discountAmt.toFixed(2);
        finalTotalDisplay.innerText = 'RM ' + finalTotal.toFixed(2);
        btnConfirm.innerHTML = 'Confirm & Place Order &bull; RM ' + finalTotal.toFixed(2);
    }

document.addEventListener('DOMContentLoaded', function() {
    var page = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
    if (page === '' || page === 'index.php') page = 'homepage.php';
    var links = document.querySelectorAll('.nav-links a');
    links.forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === page || (page.startsWith('advancesearch') && href === 'homepage.php')) {
            link.classList.add('active');
        }
    });

    // Initialize toggle states
    toggleBookingFields();
    var resModeSelect = document.getElementById('ReservationMode');
    if(resModeSelect) toggleOccasion();
    var claimSelect = document.getElementById('claimSelect');
    if(claimSelect) updateDiscountPreview();
});
</script>
</body>
</html>



