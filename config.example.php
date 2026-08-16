<?php
declare(strict_types=1);

/* ---------- Database ---------- */
const DB_HOST = 'localhost';
const DB_NAME = 'messageboard';
const DB_USER = 'parlor';
const DB_PASS = '';  // Set your database password here

/* ---------- Board settings ---------- */
const MAX_HANDLE_LENGTH  = 32;
const MAX_MESSAGE_LENGTH = 1000;
const HISTORY_LIMIT      = 100;
const FLOOD_SECONDS      = 2;
const POLL_MS            = 2000;

const BEHIND_PROXY = false;

/* ---------- Connection ---------- */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}

/* ---------- Client IP ---------- */
function client_ip(): string
{
    if (BEHIND_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
