<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($data['email'] ?? ''));
$token = trim($data['token'] ?? '');
$password = $data['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'El enlace no es válido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'La contraseña debe tener mínimo 8 caracteres.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.provider, prt.token_hash, prt.expires_at
         FROM users u
         JOIN password_reset_tokens prt ON prt.user_id = u.id
         WHERE u.email = ?
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (
        !$row ||
        $row['provider'] !== 'local' ||
        strtotime($row['expires_at']) < time() ||
        !hash_equals($row['token_hash'], hash('sha256', $token))
    ) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Este enlace venció o ya no es válido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->beginTransaction();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hash, (int)$row['id']]);

    $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
    $stmt->execute([(int)$row['id']]);

    $pdo->commit();

    echo json_encode([
        'success'=>true,
        'message'=>'Tu contraseña fue actualizada correctamente.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'No se pudo cambiar la contraseña.'], JSON_UNESCAPED_UNICODE);
}
