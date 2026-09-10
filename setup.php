<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$count = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count > 0) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUsername = trim((string) ($_POST['admin_username'] ?? ''));
    $adminName = trim((string) ($_POST['admin_name'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $cashierUsername = trim((string) ($_POST['cashier_username'] ?? ''));
    $cashierName = trim((string) ($_POST['cashier_name'] ?? ''));
    $cashierPassword = (string) ($_POST['cashier_password'] ?? '');

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session expired. Please try again.';
    } elseif (
        !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $adminUsername)
        || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $cashierUsername)
    ) {
        $error = 'Usernames must contain 3–50 letters, numbers, dots, underscores, or hyphens.';
    } elseif (strlen($adminName) < 2 || strlen($cashierName) < 2) {
        $error = 'Please enter both full names.';
    } elseif (strlen($adminPassword) < 8 || strlen($cashierPassword) < 8) {
        $error = 'Passwords must contain at least 8 characters.';
    } elseif ($adminUsername === $cashierUsername) {
        $error = 'Admin and cashier usernames must be different.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $insert = $pdo->prepare(
                'INSERT INTO users (username, full_name, password_hash, role)
                 VALUES (?, ?, ?, ?)'
            );

            $insert->execute([
                $adminUsername,
                $adminName,
                password_hash($adminPassword, PASSWORD_DEFAULT),
                'admin',
            ]);

            $insert->execute([
                $cashierUsername,
                $cashierName,
                password_hash($cashierPassword, PASSWORD_DEFAULT),
                'cashier',
            ]);

            $pdo->commit();
            flash('success', 'Accounts created. You can now log in.');
            header('Location: login.php');
            exit;
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $error = 'Could not create accounts. Ensure the database settings in config.php are correct.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Initial Setup | Beanson Brew Cafe</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
<main class="auth-card setup-card">
    <h1>Beanson Brew Cafe</h1>
    <p class="muted">Create the first secure accounts. This page only works while no accounts exist.</p>

    <?php if ($error): ?>
        <div class="alert error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <h2>Administrator Account</h2>
        <label>Full name
            <input name="admin_name" required maxlength="100" value="<?= e($_POST['admin_name'] ?? '') ?>">
        </label>
        <label>Username
            <input name="admin_username" required maxlength="50" value="<?= e($_POST['admin_username'] ?? 'admin') ?>">
        </label>
        <label>Password <span class="muted">(minimum 8 characters)</span>
            <input type="password" name="admin_password" required minlength="8">
        </label>

        <h2>Cashier Account</h2>
        <label>Full name
            <input name="cashier_name" required maxlength="100" value="<?= e($_POST['cashier_name'] ?? '') ?>">
        </label>
        <label>Username
            <input name="cashier_username" required maxlength="50" value="<?= e($_POST['cashier_username'] ?? 'cashier') ?>">
        </label>
        <label>Password <span class="muted">(minimum 8 characters)</span>
            <input type="password" name="cashier_password" required minlength="8">
        </label>

        <button class="button primary full" type="submit">Create Accounts</button>
    </form>
</main>
</body>
</html>
