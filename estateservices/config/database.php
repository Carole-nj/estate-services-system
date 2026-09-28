<?php
/** Database connection. Defaults match a fresh XAMPP install. */
define('DB_HOST', 'localhost');
define('DB_NAME', 'estate_serve');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()) .
        '<br>Did you create the "' . DB_NAME . '" database (import database/schema.sql) and run database/seed.php?');
}

// Auto-detect the app's base URL so links work under any htdocs folder name
if (!defined('BASE_URL')) {
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $base = substr($projectRoot, strlen($docRoot));
    define('BASE_URL', $base === false ? '' : $base);
}

define('SERVICE_CATEGORIES', [
    'Laundry', 'Car Wash', 'Grocery', 'House Cleaning',
    'Plumbing & Repairs', 'Food Delivery', 'Salon & Barber',
]);
