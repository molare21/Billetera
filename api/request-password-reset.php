<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($data['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'El correo electrónico no es válido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reset_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = $pdo->prepare(
        "SELECT id, email, provider, display_name
         FROM users
         WHERE email = ?
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Do not reveal whether an address exists.
    if (!$user || $user['provider'] !== 'local') {
        echo json_encode([
            'success'=>true,
            'message'=>'Si existe una cuenta compatible con ese correo, recibirás un enlace para recuperar tu contraseña.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
         ON DUPLICATE KEY UPDATE
           token_hash = VALUES(token_hash),
           expires_at = VALUES(expires_at),
           created_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([(int)$user['id'], $hash]);

    $config = aldia_mail_config();
    $appUrl = rtrim((string)($config['app_url'] ?? 'https://aldiabilletera.com'), '/');
    $link = $appUrl . '/?reset_token=' . rawurlencode($token) . '&email=' . rawurlencode($email);

    $html = aldia_email_shell(
        'Recupera tu contraseña',
        '<p>Recibimos una solicitud para cambiar la contraseña de tu cuenta de AL DÍA.</p><p>Este enlace vence en 30 minutos.</p>',
        'Crear nueva contraseña',
        $link
    );

    $mail = aldia_send_email($email, 'Recupera tu contraseña · AL DÍA', $html);

    if (!$mail['success']) {
        http_response_code(503);
        echo json_encode([
            'success'=>false,
            'message'=>$mail['configured']
              ? 'No pudimos enviar el correo en este momento.'
              : 'El correo de AL DÍA todavía no está configurado en el servidor.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success'=>true,
        'message'=>'Revisa tu correo. El enlace vence en 30 minutos.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'No se pudo procesar la recuperación.'], JSON_UNESCAPED_UNICODE);
}
