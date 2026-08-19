<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'No hay una sesión activa.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

echo json_encode([
    'success' => true,
    'authenticated' => true,
    'user' => [
        'id' => (int)$_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? ''
    ]
], JSON_UNESCAPED_UNICODE);
