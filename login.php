<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/firebase.php';

if (logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$flash = consume_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session expired. Please try again.';
    } else {
        $statement = db()->prepare(
            'SELECT id, username, full_name, password_hash, firebase_uid, role, permissions, is_active
             FROM users WHERE username = ? LIMIT 1'
        );
        $statement->execute([$username]);
        $user = $statement->fetch();

        if (!$user || !(bool) $user['is_active']) {
            $error = 'Invalid username or password.';
            if ($user) {
                audit((int) $user['id'], 'Failed login attempt');
            }
        } else {
            $authenticated = false;
            $firebaseUid = trim((string) ($user['firebase_uid'] ?? ''));

            /*
             * Firebase is the preferred authentication provider once it has
             * been configured. The existing username/password login remains
             * as a migration fallback so no current account is locked out.
             */
            if (firebase_configured()) {
                try {
                    $firebaseResponse = firebase_sign_in(
                        firebase_auth_email($username),
                        $password
                    );

                    $tokenUid = firebase_verify_id_token((string) $firebaseResponse['idToken']);

                    if ($firebaseUid === '' || !hash_equals($firebaseUid, $tokenUid)) {
                        $updateUid = db()->prepare(
                            'UPDATE users SET firebase_uid = ? WHERE id = ? LIMIT 1'
                        );
                        $updateUid->execute([$tokenUid, (int) $user['id']]);
                        $firebaseUid = $tokenUid;
                    }

                    $authenticated = true;
                } catch (Throwable $firebaseException) {
                    /* Migration fallback handled below. */
                }
            }

            if (!$authenticated && password_verify($password, (string) $user['password_hash'])) {
                $authenticated = true;

                if (firebase_configured()) {
                    try {
                        if ($firebaseUid !== '') {
                            firebase_update_user(
                                $firebaseUid,
                                firebase_auth_email($username),
                                (string) $user['full_name'],
                                $password,
                                false
                            );
                        } else {
                            $firebaseUid = firebase_create_user(
                                firebase_auth_email($username),
                                $password,
                                (string) $user['full_name'],
                                false
                            );

                            $updateUid = db()->prepare(
                                'UPDATE users SET firebase_uid = ? WHERE id = ? LIMIT 1'
                            );
                            $updateUid->execute([$firebaseUid, (int) $user['id']]);
                        }
                    } catch (Throwable $firebaseException) {
                        /* Keep the current login working if Firebase is unavailable. */
                    }
                }
            }

            if (!$authenticated) {
                $error = 'Invalid username or password.';
                audit((int) $user['id'], 'Failed login attempt');
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role'],
                    'permissions' => $user['permissions'],
                ];
                audit((int) $user['id'], 'Logged in');
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Beanson Brew Cafe POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="brand-mark">BB</div>
    <h1>Beanson Brew Cafe</h1>
    <p class="muted">Point-of-Sale System</p>

    <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Username
            <input name="username" required autofocus maxlength="50">
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button class="button primary full" type="submit">Log In</button>
    </form>
</main>
</body>
</html>
