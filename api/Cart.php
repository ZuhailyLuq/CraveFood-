<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.html");
    exit();
}

$userId = (int)$_SESSION['UserID'];

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['vendor_id' => null, 'vendor_name' => '', 'items' => []];
}

$cart       = $_SESSION['cart'];
$cartItems  = $cart['items'];
$vendorName = $cart['vendor_name'];
$vendorId   = $cart['vendor_id'];

$totalAmount = 0;
$totalItems  = 0;
foreach ($cartItems as $ci) {
    $totalAmount += $ci['Price'] * $ci['Quantity'];
    $totalItems  += $ci['Quantity'];
}

$activeOrder = db_fetch_one($pdo,
    'SELECT "OrderID", "Status" FROM orders WHERE "UserID" = ? AND "Status" NOT IN (\'Finished\', \'Completed\', \'Cancelled\') ORDER BY "OrderID" DESC LIMIT 1',
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - CraveFood</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <style>
        /* â”€â”€ Reset & base â”€â”€ */
        *, body { font-family: 'Inter', 'Segoe UI', sans-serif; box-sizing: border-box; }
        body { background: #ffffff; margin: 0; padding: 0; color: #1e1e1e; }

        .cart-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px 100px; /* bottom pad for pending pill */
        }

        /* â”€â”€ Header â”€â”€ */
        .cart-header {
            margin-bottom: 24px;
            text-align: center;
        }
        .header-title-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .header-title-wrap svg {
            width: 36px;
            height: 36px;
            fill: #ff2a44;
        }
        .header-title {
            color: #ff2a44;
            font-size: 2.2rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .cart-vendor-subtitle {
            font-size: 1.05rem;
            color: #666;
            font-weight: 500;
        }
        .cart-vendor-subtitle strong {
            color: #2d2d2d;
            font-weight: 700;
        }

        /* â”€â”€ Empty State â”€â”€ */
        .cart-empty {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
            padding: 60px 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }
        .cart-empty svg {
            width: 80px;
            height: 80px;
            fill: #f0cfd3;
            margin-bottom: 16px;
        }
        .cart-empty h2 {
            color: #333;
            margin: 0 0 10px;
        }
        .cart-empty p {
            color: #888;
            margin: 0 0 20px;
        }

        /* â”€â”€ Unified List Card â”€â”€ */
        .cart-items-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .cart-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px 24px;
            transition: background 0.2s;
        }
        .cart-item:hover {
            background: #fdfafb;
        }
        .item-divider {
            height: 1px;
            background: #e0e0e0;
            margin: 0 24px;
        }
        
        .item-left {
            flex-shrink: 0;
        }
        .item-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
        }
        .item-img-placeholder {
            width: 80px;
            height: 80px;
            background: #fff5f6;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ff2a44;
            font-size: 0.8em;
            font-weight: 700;
            border: 1px solid #ffe2e6;
        }

        .item-middle {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .item-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #2d2d2d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .item-dietary {
            display: inline-block;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            align-self: flex-start;
        }
        .item-unit-price {
            color: #888;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .item-right {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-shrink: 0;
        }
        .qty-subtotal-group {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }
        .item-subtotal {
            font-weight: 800;
            color: #2d2d2d;
            font-size: 1.1rem;
        }

        /* Quantity Stepper */
        .cart-qty-stepper {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .cart-qty-btn {
            width: 32px;
            height: 32px;
            background: #fafafa;
            border: none;
            color: #2d2d2d;
            font-weight: 700;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-qty-btn:hover { background: #e0e0e0; color: #ff2a44; }
        .cart-qty-input {
            width: 40px;
            height: 32px;
            text-align: center;
            border: none;
            border-left: 1.5px solid #e0e0e0;
            border-right: 1.5px solid #e0e0e0;
            font-size: 14px;
            font-weight: 700;
            color: #2d2d2d;
            padding: 0;
        }
        .cart-qty-input:focus { outline: none; }

        /* Trash Button */
        .btn-trash {
            background: none;
            border: none;
            color: #ff2a44;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, color 0.2s;
        }
        .btn-trash:hover {
            background: #ffe2e6;
        }
        .btn-trash svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* â”€â”€ Summary Card â”€â”€ */
        .summary-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }
        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .summary-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #2d2d2d;
        }
        .btn-empty-cart {
            background: none;
            border: none;
            color: #888;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
            transition: color 0.2s;
        }
        .btn-empty-cart:hover { color: #ff2a44; }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 0.95rem;
            color: #666;
            font-weight: 500;
        }
        .summary-row.total {
            border-top: 1px dashed #ddd;
            margin-top: 12px;
            padding-top: 16px;
            font-weight: 800;
            font-size: 1.3rem;
            color: #ff2a44;
        }

        .summary-actions {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
        }
        .btn-proceed {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ff2a44;
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px 32px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(193,18,31,0.2);
        }
        .btn-proceed:hover {
            background: #a00f1a;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(193,18,31,0.25);
        }

        /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           PENDING ORDER PILL
        â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
        .pending-pill {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            background: #ffffff;
            border: 1.5px solid #e63946;
            border-radius: 999px;
            padding: 11px 20px 11px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 32px rgba(193,18,31,0.2), 0 2px 8px rgba(0,0,0,0.08);
            text-decoration: none;
            white-space: nowrap;
            transition: transform 0.2s, box-shadow 0.2s;
            animation: pill-float 3s ease-in-out infinite;
        }
        .pending-pill:hover {
            transform: translateX(-50%) translateY(-3px);
            box-shadow: 0 12px 40px rgba(193,18,31,0.28), 0 4px 12px rgba(0,0,0,0.1);
        }
        .pending-pill-icon  { color: #ff2a44; display: flex; align-items: center; }
        .pending-pill-text  { font-size: 0.9rem; font-weight: 600; color: #1e1e1e; }
        .pending-pill-chevron { color: #ff2a44; display: flex; align-items: center; }
        @keyframes pill-float {
            0%,100% { box-shadow: 0 8px 32px rgba(193,18,31,0.2), 0 2px 8px rgba(0,0,0,0.08); }
            50%      { box-shadow: 0 12px 40px rgba(193,18,31,0.26), 0 4px 14px rgba(0,0,0,0.1); }
        }

        /* â”€â”€ Responsive â”€â”€ */
        @media (max-width: 600px) {
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                position: relative;
            }
            .item-right {
                width: 100%;
                justify-content: space-between;
                flex-direction: row-reverse; /* Put trash on left, qty/subtotal on right on mobile */
            }
            .btn-trash {
                position: absolute;
                top: 20px;
                right: 20px;
            }
            .qty-subtotal-group {
                align-items: flex-start;
            }
            .summary-actions {
                justify-content: center;
            }
            .btn-proceed {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include('header.php'); ?>

    <div class="cart-wrapper">
        <div class="cart-header">
            <div class="header-title-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1.003 1.003 0 0020.25 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
                </svg>
                <h1 class="header-title">My Cart</h1>
            </div>
            <?php if ($vendorName): ?>
                <div class="cart-vendor-subtitle">Ordering from: <strong><?php echo htmlspecialchars($vendorName); ?></strong></div>
            <?php endif; ?>
        </div>

        <?php if (count($cartItems) === 0): ?>
            <div class="cart-empty">
                <svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1.003 1.003 0 0020.25 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                <h2>Your cart is empty</h2>
                <p>Browse food and add items to your cart to get started.</p>
                <a href="Homepage.php" class="btn-proceed" style="display: inline-flex;">Browse Food</a>
            </div>
        <?php else: ?>
            
            <!-- Unified List Card -->
            <div class="cart-items-card">
                <?php 
                $count = count($cartItems);
                $i = 0;
                foreach ($cartItems as $index => $item): 
                    $i++;
                ?>
                    <div class="cart-item" id="cart-item-<?php echo $item['FoodID']; ?>">
                        
                        <div class="item-left">
                            <?php if (!empty($item['Image'])): ?>
                                <img src="<?php echo htmlspecialchars($item['Image']); ?>" alt="Food" class="item-img">
                            <?php else: ?>
                                <div class="item-img-placeholder">No Image</div>
                            <?php endif; ?>
                        </div>

                        <div class="item-middle">
                            <div class="item-name"><?php echo htmlspecialchars($item['FoodName']); ?></div>
                            <?php if (!empty($item['DietaryTag'])): ?>
                                <span class="item-dietary"><?php echo htmlspecialchars($item['DietaryTag']); ?></span>
                            <?php endif; ?>
                            <div class="item-unit-price">RM <?php echo number_format($item['Price'], 2); ?> each</div>
                        </div>

                        <div class="item-right">
                            <div class="qty-subtotal-group">
                                <div class="cart-qty-stepper">
                                    <button type="button" class="cart-qty-btn" onclick="updateCartQty(<?php echo $item['FoodID']; ?>, -1)">&minus;</button>
                                    <input type="number" class="cart-qty-input" id="qty-<?php echo $item['FoodID']; ?>" value="<?php echo $item['Quantity']; ?>" min="1" onchange="setCartQty(<?php echo $item['FoodID']; ?>, this.value)">
                                    <button type="button" class="cart-qty-btn" onclick="updateCartQty(<?php echo $item['FoodID']; ?>, 1)">+</button>
                                </div>
                                <div class="item-subtotal">RM <?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></div>
                            </div>
                            <button type="button" class="btn-trash" aria-label="Remove item" onclick="removeCartItem(<?php echo $item['FoodID']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <?php if ($i < $count): ?>
                        <div class="item-divider"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Summary Card -->
            <div class="summary-card">
                <div class="summary-header">
                    <span class="summary-title">Order Summary</span>
                    <button type="button" class="btn-empty-cart" onclick="clearCart()">Empty Cart</button>
                </div>
                
                <div class="summary-row">
                    <span>Items Count</span>
                    <span id="summaryItemCount"><?php echo $totalItems; ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total Amount</span>
                    <span id="summaryTotal">RM <?php echo number_format($totalAmount, 2); ?></span>
                </div>

                <div class="summary-actions">
                    <a href="OrderOption.php?from_cart=1" class="btn-proceed">Proceed to Order â†’</a>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <!-- â•â•â•â•â•â• PENDING ORDER PILL â•â•â•â•â•â• -->
    <?php if ($activeOrder): ?>
    <a href="OrderStatus.php?order_id=<?php echo (int)$activeOrder['OrderID']; ?>" class="pending-pill" aria-label="View pending order">
        <span class="pending-pill-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#ff2a44">
                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
            </svg>
        </span>
        <span class="pending-pill-text">Order #<?php echo (int)$activeOrder['OrderID']; ?> is <?php echo htmlspecialchars($activeOrder['Status']); ?></span>
        <span class="pending-pill-chevron">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#ff2a44">
                <path d="M9.29 6.71a.996.996 0 0 0 0 1.41L13.17 12l-3.88 3.88a.996.996 0 1 0 1.41 1.41l4.59-4.59a.996.996 0 0 0 0-1.41L10.7 6.7c-.38-.38-1.02-.38-1.41.01z"/>
            </svg>
        </span>
    </a>
    <?php endif; ?>

    <script>
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
});

function updateCartQty(foodId, change) {
    var input = document.getElementById('qty-' + foodId);
    if (!input) return;
    var currentQty = parseInt(input.value) || 1;
    var newQty = Math.max(1, currentQty + change);
    if (newQty === currentQty) return;
    setCartQty(foodId, newQty);
}

function setCartQty(foodId, qty) {
    qty = parseInt(qty);
    if (isNaN(qty) || qty < 1) qty = 1;
    
    var formData = new FormData();
    formData.append('action', 'update');
    formData.append('food_id', foodId);
    formData.append('quantity', qty);

    fetch('CartActions.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to update cart.');
            }
        })
        .catch(function() { alert('Network error.'); });
}

function removeCartItem(foodId) {
    if (!confirm('Remove this item from your cart?')) return;
    
    var formData = new FormData();
    formData.append('action', 'remove');
    formData.append('food_id', foodId);

    fetch('CartActions.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to remove item.');
            }
        })
        .catch(function() { alert('Network error.'); });
}

function clearCart() {
    if (!confirm('Are you sure you want to empty your cart?')) return;

    var formData = new FormData();
    formData.append('action', 'clear');

    fetch('CartActions.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to empty cart.');
            }
        })
        .catch(function() { alert('Network error.'); });
}
</script>
</body>
</html>



