<?php
declare(strict_types=1);

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Auth;

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

function firebase_configured(): bool
{
    return class_exists(Factory::class)
        && (string) getenv('FIREBASE_CREDENTIALS') !== ''
        && (string) getenv('FIREBASE_WEB_API_KEY') !== '';
}

function firebase_credentials_path(): string
{
    $path = trim((string) getenv('FIREBASE_CREDENTIALS'));

    if ($path === '') {
        throw new RuntimeException('FIREBASE_CREDENTIALS is not configured.');
    }

    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Firebase service-account credentials could not be read.');
    }

    return $path;
}

function firebase_auth(): Auth
{
    static $auth = null;

    if ($auth instanceof Auth) {
        return $auth;
    }

    $factory = (new Factory)->withServiceAccount(firebase_credentials_path());
    $auth = $factory->createAuth();

    return $auth;
}

function firebase_auth_email(string $username): string
{
    $domain = trim((string) getenv('FIREBASE_AUTH_EMAIL_DOMAIN'));

    if ($domain === '') {
        $projectId = trim((string) getenv('FIREBASE_PROJECT_ID'));
        $domain = $projectId !== '' ? $projectId . '.firebaseapp.com' : 'firebase.local';
    }

    return strtolower($username . '@' . $domain);
}

/**
 * Authenticate a Firebase Email/Password account without exposing the
 * Firebase Web API key to PHP source code.
 *
 * Returns the Firebase ID token and decoded response on success.
 */
function firebase_sign_in(string $email, string $password): array
{
    $apiKey = trim((string) getenv('FIREBASE_WEB_API_KEY'));

    if ($apiKey === '') {
        throw new RuntimeException('FIREBASE_WEB_API_KEY is not configured.');
    }

    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . rawurlencode($apiKey);
    $payload = json_encode([
        'email' => $email,
        'password' => $password,
        'returnSecureToken' => true,
    ], JSON_THROW_ON_ERROR);

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the Firebase authentication request.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('Firebase authentication request failed: ' . $curlError);
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Firebase returned an invalid authentication response.');
    }

    if ($status < 200 || $status >= 300 || empty($decoded['idToken'])) {
        throw new RuntimeException('Firebase authentication failed.');
    }

    return $decoded;
}

function firebase_create_user(string $email, string $password, string $displayName, bool $disabled = false): string
{
    $record = firebase_auth()->createUser([
        'email' => $email,
        'password' => $password,
        'displayName' => $displayName,
        'disabled' => $disabled,
    ]);

    return (string) $record->uid;
}

function firebase_update_user(string $uid, string $email, string $displayName, ?string $password, bool $disabled): void
{
    $properties = [
        'email' => $email,
        'displayName' => $displayName,
        'disabled' => $disabled,
    ];

    if ($password !== null && $password !== '') {
        $properties['password'] = $password;
    }

    firebase_auth()->updateUser($uid, $properties);
}

function firebase_verify_id_token(string $idToken): string
{
    $verified = firebase_auth()->verifyIdToken($idToken);
    $uid = (string) $verified->claims()->get('sub');

    if ($uid === '') {
        throw new RuntimeException('Firebase ID token did not contain a user ID.');
    }

    return $uid;
}
