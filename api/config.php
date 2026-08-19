<?php

header_remove('X-Powered-By');

$privateConfigPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/private/aldia-db.php';

if (!is_file($privateConfigPath)) {
    http_response_code(500);
    exit('Server configuration missing');
}

$secrets = require $privateConfigPath;

$host = $secrets['host'] ?? 'localhost';
$db   = $secrets['database'] ?? '';
$user = $secrets['username'] ?? '';
$pass = $secrets['password'] ?? '';

if ($db === '' || $user === '' || $pass === '') {
    http_response_code(500);
    exit('Server configuration incomplete');
}

$dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed');
}
