<?php

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success'=>false,
        'authenticated'=>false,
        'message'=>'No hay una sesión activa.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, email, display_name, email_verified
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['success'=>false,'authenticated'=>false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['display_name'];

    echo json_encode([
        'success'=>true,
        'authenticated'=>true,
        'user'=>[
            'id'=>(int)$user['id'],
            'name'=>$user['display_name'],
            'email'=>$user['email'],
            'email_verified'=>(bool)$user['email_verified']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'authenticated'=>false,'message'=>'No se pudo consultar la sesión.'], JSON_UNESCAPED_UNICODE);
}
