<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Completa correo y contraseña.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'El correo electrónico no es válido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, email, password_hash, display_name, provider
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Correo o contraseña incorrectos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($user['provider'] !== 'local') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Esta cuenta usa inicio de sesión externo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Correo o contraseña incorrectos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $update = $pdo->prepare(
        "UPDATE users SET last_login_at = NOW() WHERE id = ?"
    );
    $update->execute([$user['id']]);

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['display_name'];

    echo json_encode([
        'success' => true,
        'message' => 'Inicio de sesión correcto',
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['display_name'],
            'email' => $user['email']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo iniciar sesión.'
    ], JSON_UNESCAPED_UNICODE);
}
