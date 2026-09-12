<?php
declare(strict_types=1);

/*
 * Database configuration.
 *
 * Environment variables are preferred for production deployment. The local
 * defaults preserve the existing XAMPP development setup.
 */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'beanson_pos';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = trim((string) getenv('DB_HOST')) ?: DB_HOST;
    $port = trim((string) getenv('DB_PORT'));
    $name = trim((string) getenv('DB_NAME')) ?: DB_NAME;
    $user = (string) getenv('DB_USER');
    $pass = (string) getenv('DB_PASS');
    $socket = trim((string) getenv('DB_SOCKET'));

    if ($user === '') {
        $user = DB_USER;
    }

    if ($pass === '' && getenv('DB_PASS') === false) {
        $pass = DB_PASS;
    }

    if ($socket !== '') {
        $dsn = 'mysql:unix_socket=' . $socket . ';dbname=' . $name . ';charset=utf8mb4';
    } else {
        $dsn = 'mysql:host=' . $host;
        if ($port !== '') {
            $dsn .= ';port=' . $port;
        }
        $dsn .= ';dbname=' . $name . ';charset=utf8mb4';
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+08:00'");
    return $pdo;
}
