<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_login();

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) {
    http_response_code(404);
    exit('Receipt not found.');
}

$user = current_user();
$statement = db()->prepare(
    'SELECT
        o.*,
        u.full_name AS cashier_name,
        r.refund_no,
        r.refund_amount,
        r.reason_note,
        r.authorized_at,
        refund_admin.full_name AS refund_admin_name
     FROM orders o
     INNER JOIN users u ON u.id = o.cashier_id
     LEFT JOIN refunds r ON r.order_id = o.id
     LEFT JOIN users refund_admin ON refund_admin.id = r.authorized_by
     WHERE o.id = ?'
);
$statement->execute([$orderId]);
$order = $statement->fetch();

if (!$order || ($user['role'] !== 'admin' && (int) $order['cashier_id'] !== (int) $user['id'])) {
    http_response_code(403);
    exit('Receipt not available.');
}

$items = db()->prepare(
    'SELECT product_name, temperature, drink_size, quantity, unit_price, line_total
     FROM order_items WHERE order_id = ? ORDER BY id'
);
$items->execute([$orderId]);
$items = $items->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($order['receipt_no']) ?> | Receipt</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="receipt-page">
<main class="receipt">
    <header>
        <h1>Beanson Brew Cafe</h1>
        <p>Sales Receipt</p>
    </header>
    <?php if (($order['status'] ?? 'completed') === 'refunded'): ?>
        <p class="receipt-refund-status">REFUNDED IN FULL</p>
    <?php endif; ?>
    <hr>
    <p>Receipt: <?= e($order['receipt_no']) ?><br>
       Date: <?= e(date('M d, Y h:i A', strtotime($order['created_at']))) ?><br>
       Cashier: <?= e($order['cashier_name']) ?></p>
    <hr>
    <?php foreach ($items as $item): ?>
        <div class="receipt-line">
            <span><?= e($item['product_name']) ?><br><small><?= e(ucfirst($item['temperature'])) ?>, <?= e($item['drink_size']) ?> × <?= (int) $item['quantity'] ?></small></span>
            <strong><?= peso((float) $item['line_total']) ?></strong>
        </div>
    <?php endforeach; ?>
    <hr>
<?php
$paymentLabels = [
    'cash' => 'Cash',
    'gcash' => 'GCash',
    'maya' => 'Maya',
];
$paymentLabel = $paymentLabels[$order['payment_method'] ?? 'cash'] ?? 'Cash';
?>
<div class="receipt-line">
    <span>Total</span>
    <strong><?= peso((float) $order['total']) ?></strong>
</div>
<div class="receipt-line">
    <span>Payment Method</span>
    <span><?= e($paymentLabel) ?></span>
</div>
<div class="receipt-line">
    <span><?= $paymentLabel === 'Cash' ? 'Cash Received' : 'Amount Paid' ?></span>
    <span><?= peso((float) $order['cash_received']) ?></span>
</div>
<div class="receipt-line">
    <span>Change</span>
    <span><?= peso((float) $order['change_amount']) ?></span>
</div>
<?php if (($order['status'] ?? 'completed') === 'refunded'): ?>
    <div class="receipt-refund-status">
        Refund <?= e($order['refund_no']) ?> · <?= peso((float) $order['refund_amount']) ?><br>
        Recorded <?= e(date('M d, Y h:i A', strtotime($order['authorized_at']))) ?>.
        <?php if (($user['role'] ?? '') === 'admin'): ?>
            <br><small>Authorized by <?= e($order['refund_admin_name']) ?>: <?= e($order['reason_note']) ?></small>
        <?php endif; ?>
    </div>
<?php endif; ?>
    <hr>
    <p class="center">Thank you for visiting!</p>
    <p class="center no-print"><button class="button primary" onclick="window.print()">Print Receipt</button> <a class="button secondary" href="index.php">Back to POS</a></p>
</main>
</body>
</html>
