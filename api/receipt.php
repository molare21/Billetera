<?php

session_start();

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT mime_type, storage_path, original_name
     FROM receipts
     WHERE id = ? AND user_id = ?
     LIMIT 1"
);
$stmt->execute([$id, (int)$_SESSION['user_id']]);
$row = $stmt->fetch();

if (!$row || !is_file($row['storage_path'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $row['mime_type']);
header('Content-Length: ' . filesize($row['storage_path']));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($row['storage_path']);
