<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_login();

function refundReturnUrl(string $date, string $source = 'admin'): string
{
    $query = http_build_query(['date' => $date]);

    // A refund can be launched from the admin report or from a cashier's own
    // sales report. Send the browser back to whichever page it came from.
    if ($source === 'cashier') {
        return '../cashier/report.php?' . $query;
    }

    return 'reports.php?' . $query;
}

function validRefundDate(string $value): string
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return date('Y-m-d');
    }

    return $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$returnDate = validRefundDate((string) ($_POST['return_date'] ?? ''));
$source = (($_POST['source'] ?? '') === 'cashier') ? 'cashier' : 'admin';
$receiptNo = strtoupper(trim((string) ($_POST['receipt_no'] ?? '')));
$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
$reasonCode = (string) ($_POST['reason_code'] ?? '');
$reasonNote = trim((string) ($_POST['reason_note'] ?? ''));
$adminUsername = trim((string) ($_POST['admin_username'] ?? ''));
$adminPassword = (string) ($_POST['admin_password'] ?? '');
$allowedReasons = [
    'customer_request',
    'wrong_item',
    'duplicate_charge',
    'cashier_error',
    'other',
];

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'This refund request is invalid or expired. Refresh the report and try again.');
    header('Location: ' . refundReturnUrl($returnDate, $source));
    exit;
}

if (!$orderId || $orderId < 1 || $receiptNo === '') {
    flash('error', 'The receipt selected for refund is invalid.');
    header('Location: ' . refundReturnUrl($returnDate, $source));
    exit;
}

if (!in_array($reasonCode, $allowedReasons, true) || strlen($reasonNote) < 5 || strlen($reasonNote) > 500) {
    flash('error', 'Select a refund reason and enter an explanation between 5 and 500 characters.');
    header('Location: ' . refundReturnUrl($returnDate, $source));
    exit;
}

if ($adminPassword === '') {
    flash('error', 'Enter your current administrator password to authorize the refund.');
    header('Location: ' . refundReturnUrl($returnDate, $source));
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // Resolve the administrator who is authorizing this refund.
    // - From the cashier's report an admin username is supplied, so a manager
    //   can approve the refund at the cashier's screen (admin override).
    // - From the admin report no username is sent and the logged-in admin
    //   authorizes with their own password.
    if ($adminUsername !== '') {
        $adminStatement = $pdo->prepare(
            "SELECT id, password_hash
             FROM users
             WHERE username = ? AND role = 'admin' AND is_active = 1
             FOR UPDATE"
        );
        $adminStatement->execute([$adminUsername]);
    } elseif ((current_user()['role'] ?? '') === 'admin') {
        $adminStatement = $pdo->prepare(
            "SELECT id, password_hash
             FROM users
             WHERE id = ? AND role = 'admin' AND is_active = 1
             FOR UPDATE"
        );
        $adminStatement->execute([(int) current_user()['id']]);
    } else {
        $pdo->rollBack();
        audit((int) current_user()['id'], 'Failed refund authorization', 'Receipt ' . $receiptNo . ' · no administrator supplied');
        flash('error', 'An administrator must authorize this refund. Enter an administrator username and password.');
        header('Location: ' . refundReturnUrl($returnDate, $source));
        exit;
    }

    $admin = $adminStatement->fetch();

    if (!$admin || !password_verify($adminPassword, $admin['password_hash'])) {
        $pdo->rollBack();
        audit((int) current_user()['id'], 'Failed refund authorization', 'Receipt ' . $receiptNo);
        flash('error', 'Administrator credentials could not be verified. No refund was processed.');
        header('Location: ' . refundReturnUrl($returnDate, $source));
        exit;
    }

    $orderStatement = $pdo->prepare(
        "SELECT id, receipt_no, total, payment_method, status, cashier_id
         FROM orders
         WHERE id = ? AND receipt_no = ?
         FOR UPDATE"
    );
    $orderStatement->execute([$orderId, $receiptNo]);
    $order = $orderStatement->fetch();

    if (!$order || $order['status'] !== 'completed' || (float) $order['total'] <= 0) {
        throw new RuntimeException('This receipt is not eligible for a refund.');
    }

    // A cashier authorizing from their own report may only refund their own
    // receipts, even though an administrator approves the action.
    if ((current_user()['role'] ?? '') !== 'admin'
        && (int) $order['cashier_id'] !== (int) current_user()['id']) {
        throw new RuntimeException('You can only refund your own receipts.');
    }

    $existingRefund = $pdo->prepare('SELECT id FROM refunds WHERE order_id = ? FOR UPDATE');
    $existingRefund->execute([(int) $order['id']]);

    if ($existingRefund->fetch()) {
        throw new RuntimeException('This receipt has already been refunded.');
    }

    $refundNo = 'RFD-' . date('Ymd-His') . '-' . random_int(1000, 9999);
    $refundStatement = $pdo->prepare(
        'INSERT INTO refunds
        (refund_no, order_id, refund_amount, original_payment_method, reason_code, reason_note, authorized_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $refundStatement->execute([
        $refundNo,
        (int) $order['id'],
        (float) $order['total'],
        $order['payment_method'],
        $reasonCode,
        $reasonNote,
        (int) $admin['id'],
    ]);

    $statusStatement = $pdo->prepare(
        "UPDATE orders
         SET status = 'refunded', refunded_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'completed'"
    );
    $statusStatement->execute([(int) $order['id']]);

    if ($statusStatement->rowCount() !== 1) {
        throw new RuntimeException('The receipt status changed before the refund could be processed.');
    }

    audit(
        (int) $admin['id'],
        'Authorized full refund',
        'Refund ' . $refundNo . ' · Receipt ' . $order['receipt_no'] . ' · ' . peso((float) $order['total']) . ' · ' . $reasonCode
    );

    $pdo->commit();
    flash('success', 'Refund ' . $refundNo . ' was recorded for receipt ' . $order['receipt_no'] . '.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('error', $exception instanceof PDOException
        ? 'The refund could not be recorded. Refresh the report and confirm the receipt status.'
        : $exception->getMessage());
}

header('Location: ' . refundReturnUrl($returnDate, $source));
exit;
