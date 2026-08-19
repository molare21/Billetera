<?php

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

$name = trim($data['name'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if ($name === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Completa nombre, correo y contraseña.'
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

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'La contraseña debe tener mínimo 8 caracteres.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe una cuenta con este correo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (email, password_hash, provider, display_name)
         VALUES (?, ?, 'local', ?)"
    );

    $stmt->execute([$email, $passwordHash, $name]);

    $userId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Cuenta creada correctamente',
        'user' => [
            'id' => (int)$userId,
            'name' => $name,
            'email' => $email
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo crear la cuenta.'
    ], JSON_UNESCAPED_UNICODE);
}
