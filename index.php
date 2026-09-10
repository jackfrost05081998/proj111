<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_login();

$user = current_user();

$products = db()->query(
    "SELECT
        id,
        name,
        base_price,
        hot_iced,
        iced_extra,
        size_16_extra
     FROM products
     WHERE is_active = 1
     ORDER BY name ASC"
)->fetchAll();

$today = db()->query(
    "SELECT
        COUNT(*) AS transactions,
        COALESCE(SUM(total), 0) AS sales
     FROM orders
     WHERE DATE(created_at) = CURDATE()"
)->fetch();

$lineCount = (int) db()->query(
    "SELECT COALESCE(SUM(oi.quantity), 0)
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE DATE(o.created_at) = CURDATE()"
)->fetchColumn();

$productsJson = json_encode(
    $products,
    JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG
);

if ($productsJson === false) {
    $productsJson = '[]';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS | Beanson Brew Cafe</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="kiosk-page">
<header class="topbar kiosk-topbar">
    <div>
        <a class="logo" href="index.php">
            Beanson Brew Cafe <span>— POS</span>
        </a>
        <small>
            <?= e($user['full_name']) ?> · <?= e(ucfirst($user['role'])) ?>
        </small>
    </div>

    <div class="top-actions">


        <?php if (($user['role'] ?? '') === 'cashier'): ?>
            <a class="button kiosk-header-button" href="cashier/report.php">
                My Sales
            </a>
        <?php endif; ?>

        <?php if (($user['role'] ?? '') === 'admin'): ?>
            <a class="button kiosk-header-button" href="admin/products.php">
                Administration
            </a>
        <?php endif; ?>

        <a class="button kiosk-header-button" href="logout.php">Log out</a>
    </div>
</header>

<main
    id="posApp"
    class="pos-layout kiosk-layout"
    data-products="<?= e($productsJson) ?>"
    data-csrf="<?= e(csrf_token()) ?>"
>
    <section class="panel menu-panel kiosk-menu-panel">
        <div class="panel-title kiosk-panel-title">
            <div>
                <h1>Menu</h1>
            </div>
        </div>

        <div class="filters kiosk-filters kiosk-search-filter">
            <label>
                Search drinks
                <input
                    id="searchFilter"
                    type="search"
                    placeholder="Search drinks"
                    autocomplete="off"
                >
            </label>
        </div>

        <div
            id="productGrid"
            class="product-grid kiosk-product-grid"
            aria-live="polite"
            aria-label="Available menu items"
        ></div>

        <div class="selection-box kiosk-selection-box">
            <div class="selected-product-heading">


                <p id="priceExplanation" class="price-explanation"></p>
            </div>

            <div class="options-row kiosk-options-row">
                <label>
                    Temperature
                    <select id="temperature">
                        <option value="hot">Hot</option>
                        <option value="iced">Iced</option>
                    </select>
                </label>

                <label>
                    Size
                    <select id="drinkSize">
                        <option value="12 Oz">12 Oz</option>
                        <option value="16 Oz">16 Oz</option>
                    </select>
                </label>

                <div class="quantity-picker">
                    <span class="quantity-label">Quantity</span>

                    <div class="quantity-control kiosk-quantity-control">
                        <button
                            id="decreaseQty"
                            type="button"
                            aria-label="Decrease selected quantity"
                        >−</button>

                        <span id="quantity">1</span>

                        <button
                            id="increaseQty"
                            type="button"
                            aria-label="Increase selected quantity"
                        >+</button>
                    </div>
                </div>

                <button
                    id="addToOrder"
                    class="button primary kiosk-add-button"
                    type="button"
                    disabled
                >
                    Add to Order
                </button>
            </div>
        </div>
    </section>

    <section class="panel cart-panel kiosk-cart-panel">
        <div class="panel-title kiosk-panel-title">
            <div>
                <h1>Current Order</h1>
            </div>

        </div>

        <div class="cart-table-wrap kiosk-cart-table-wrap">
            <table class="kiosk-cart-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="cart-qty-column">Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th class="cart-action-column"></th>
                    </tr>
                </thead>
                <tbody id="cartBody"></tbody>
            </table>

            <p id="emptyCart" class="empty-cart">
                Your order is empty.<br>
                Choose drinks from the menu.
            </p>
        </div>

        <div class="cart-footer kiosk-cart-footer">
            <div class="total-row kiosk-total-row">
                <span>Total Due</span>
                <strong id="cartTotal">₱0.00</strong>
            </div>

            <div class="cart-actions kiosk-cart-actions">
                <button
                    id="clearCart"
                    class="button kiosk-clear-button"
                    type="button"
                    disabled
                >
                    Clear Order
                </button>

                <button
                    id="checkoutButton"
                    class="button primary kiosk-checkout-button"
                    type="button"
                    disabled
                >
                    Continue to Payment
                </button>
            </div>
        </div>
    </section>
</main>

<dialog id="checkoutDialog" class="checkout-dialog">
    <form id="checkoutForm" method="dialog">
        <div class="checkout-dialog-header">
            <div>
                <p class="eyebrow">Payment</p>
                <h2>Complete Order</h2>
            </div>

            <button
                id="closeCheckout"
                class="icon-button"
                type="button"
                aria-label="Close checkout"
            >×</button>
        </div>

        <div class="checkout-total-box">
            <span>Total amount due</span>
            <strong id="checkoutTotal">₱0.00</strong>
        </div>

        <fieldset class="payment-methods">
            <legend>Payment method</legend>

            <div class="payment-method-grid">
                <button
                    class="payment-method is-selected"
                    type="button"
                    data-payment-method="cash"
                    aria-pressed="true"
                >
                    <strong>Cash</strong>
                    <small>Enter cash received</small>
                </button>

                <button
                    class="payment-method"
                    type="button"
                    data-payment-method="gcash"
                    aria-pressed="false"
                >
                    <strong>GCash</strong>
                    <small>Exact payment · no change</small>
                </button>

                <button
                    class="payment-method"
                    type="button"
                    data-payment-method="maya"
                    aria-pressed="false"
                >
                    <strong>Maya</strong>
                    <small>Exact payment · no change</small>
                </button>
            </div>
        </fieldset>

        <input id="paymentMethod" type="hidden" value="cash">

        <div id="cashPaymentFields">
            <label>
                Cash received
                <input
                    id="cashReceived"
                    type="number"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                    placeholder="0.00"
                    required
                >
            </label>

            <p id="changePreview" class="payment-preview"></p>
        </div>

        <div id="digitalPaymentMessage" class="digital-payment-message" hidden>
            <strong id="digitalPaymentTitle">GCash Payment</strong>
            <span>
                The total will be recorded as paid in full. Change: ₱0.00.
            </span>
        </div>

        <p id="checkoutError" class="alert error hidden"></p>

        <button
            id="completePaymentButton"
            class="button primary kiosk-pay-button"
            type="submit"
        >
            Complete Cash Payment
        </button>
    </form>
</dialog>

<script src="assets/js/pos.js"></script>
</body>
</html>