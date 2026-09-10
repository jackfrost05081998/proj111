<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_login();

if ((current_user()['role'] ?? '') !== 'cashier') {
    http_response_code(403);
    exit('This page is only available to cashier accounts.');
}

$date = $_GET['date'] ?? date('Y-m-d');

$dateObject = DateTime::createFromFormat('Y-m-d', $date);

if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
    $date = date('Y-m-d');
}

$cashierId = (int) current_user()['id'];

$flash = consume_flash();

$summaryStatement = db()->prepare(
    'SELECT
        COUNT(*) AS transaction_count,
        COALESCE(SUM(total), 0) AS total_sales
     FROM orders
     WHERE cashier_id = ?
       AND DATE(created_at) = ?'
);
$summaryStatement->execute([$cashierId, $date]);
$summary = $summaryStatement->fetch();

$refundSummaryStatement = db()->prepare(
    'SELECT
        COUNT(*) AS refund_count,
        COALESCE(SUM(r.refund_amount), 0) AS refund_total
     FROM refunds r
     INNER JOIN orders o ON o.id = r.order_id
     WHERE o.cashier_id = ?
       AND DATE(o.created_at) = ?'
);
$refundSummaryStatement->execute([$cashierId, $date]);
$refundSummary = $refundSummaryStatement->fetch();

$grossSales = (float) $summary['total_sales'];
$refundTotal = (float) $refundSummary['refund_total'];
$netSales = $grossSales - $refundTotal;

$transactionStatement = db()->prepare(
    'SELECT id, receipt_no, total, cash_received, change_amount, status, created_at
     FROM orders
     WHERE cashier_id = ?
       AND DATE(created_at) = ?
     ORDER BY created_at DESC'
);
$transactionStatement->execute([$cashierId, $date]);
$transactions = $transactionStatement->fetchAll();

$itemStatement = db()->prepare(
    'SELECT product_name, quantity
     FROM order_items
     WHERE order_id = ?
     ORDER BY id ASC'
);
foreach ($transactions as &$transaction) {
    $itemStatement->execute([(int) $transaction['id']]);
    $transaction['items'] = $itemStatement->fetchAll();
}
unset($transaction);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Sales Report | Beanson Brew Cafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div>
        <a class="logo" href="../index.php">
            Beanson Brew Cafe <span>— My Sales Report</span>
        </a>
        <small><?= e(current_user()['full_name']) ?></small>
    </div>

    <div class="top-actions">
        <a class="button" href="../index.php">Back to POS</a>
        <a class="button" href="../logout.php">Log out</a>
    </div>
</header>

<main class="report-page">
    <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <section class="panel">
        <div class="report-toolbar">
            <form method="get">
                <label>
                    Report date
                    <input type="date" name="date" value="<?= e($date) ?>">
                </label>

                <button class="button primary" type="submit">View My Report</button>
            </form>
        </div>

        <div class="stat-grid report-stat-grid">
            <article class="stat">
                <span>My Total Sales</span>
                <strong><?= peso($grossSales) ?></strong>
            </article>

            <article class="stat">
                <span>Refunds</span>
                <strong><?= peso($refundTotal) ?></strong>
            </article>

            <article class="stat">
                <span>Net Sales</span>
                <strong><?= peso($netSales) ?></strong>
            </article>

            <article class="stat">
                <span>My Transactions</span>
                <strong><?= (int) $summary['transaction_count'] ?></strong>
            </article>

            <article class="stat">
                <span>Refunded</span>
                <strong><?= (int) $refundSummary['refund_count'] ?></strong>
            </article>
        </div>
    </section>

    <section class="panel">
        <div class="panel-title">
            <h1>My Transaction Log</h1>
        </div>

        <div class="table-scroll">
            <table class="transaction-log-table">
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Products</th>
                        <th>Qty</th>
                        <th>Date and Time</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$transactions): ?>
                    <tr>
                        <td colspan="7" class="empty-cell">No transactions recorded for this date.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?= e($transaction['receipt_no']) ?></td>
                        <td>
                            <div class="transaction-products">
                                <?php foreach ($transaction['items'] as $item): ?>
                                    <span><?= e($item['product_name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <div class="transaction-quantities">
                                <?php foreach ($transaction['items'] as $item): ?>
                                    <span><?= (int) $item['quantity'] ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <?= e(date(
                                'M d, Y · h:i:s A',
                                strtotime($transaction['created_at'])
                            )) ?>
                        </td>
                        <td><?= peso((float) $transaction['total']) ?></td>
                        <td>
                            <span class="receipt-status <?= $transaction['status'] === 'refunded' ? 'is-refunded' : 'is-completed' ?>">
                                <?= $transaction['status'] === 'refunded' ? 'Refunded' : 'Completed' ?>
                            </span>
                        </td>
                        <td class="transaction-actions">
                            <?php if ($transaction['status'] === 'completed'): ?>
                                <button
                                    class="button small delete-button"
                                    type="button"
                                    data-open-refund
                                    data-order-id="<?= (int) $transaction['id'] ?>"
                                    data-receipt-no="<?= e($transaction['receipt_no']) ?>"
                                    data-refund-total="<?= e(peso((float) $transaction['total'])) ?>"
                                >Refund</button>
                            <?php endif; ?>
                            <a
                                class="button small"
                                href="../receipt.php?id=<?= (int) $transaction['id'] ?>"
                            >View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<dialog id="refundDialog" class="refund-dialog">
    <form method="post" action="../admin/refund.php" id="refundForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="order_id" id="refundOrderId">
        <input type="hidden" name="receipt_no" id="refundReceiptNo">
        <input type="hidden" name="return_date" value="<?= e($date) ?>">
        <input type="hidden" name="source" value="cashier">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">Irreversible financial action</p>
                <h2 id="refundDialogTitle">Refund Receipt</h2>
            </div>
            <button class="icon-button" type="button" data-close-refund aria-label="Close refund form">×</button>
        </div>
        <p class="refund-warning">This records a full refund of <strong id="refundDialogTotal"></strong> and must be authorized by an administrator. It does not delete the original receipt or send a gateway reversal for digital payments.</p>
        <label>Refund reason
            <select name="reason_code" required>
                <option value="customer_request">Customer request</option>
                <option value="wrong_item">Wrong item</option>
                <option value="duplicate_charge">Duplicate charge</option>
                <option value="cashier_error">Cashier error</option>
                <option value="other">Other</option>
            </select>
        </label>
        <label>Explanation
            <textarea name="reason_note" minlength="5" maxlength="500" required placeholder="Explain why this receipt is being refunded."></textarea>
        </label>
        <label>Administrator username
            <input type="text" name="admin_username" autocomplete="username" autocapitalize="none" spellcheck="false" required placeholder="Manager account">
        </label>
        <label>Administrator password
            <input type="password" name="admin_password" autocomplete="current-password" required>
        </label>
        <div class="dialog-actions">
            <button class="button" type="button" data-close-refund>Cancel</button>
            <button class="button delete-button" type="submit">Authorize Full Refund</button>
        </div>
    </form>
</dialog>
<script src="../assets/js/reports.js"></script>
</body>
</html>