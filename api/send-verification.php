<?php

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'No hay una sesión activa.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS email_verification_tokens (
            user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_verify_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = $pdo->prepare(
        "SELECT id, email, display_name, email_verified
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Usuario no encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$user['email_verified'] === 1) {
        echo json_encode(['success'=>true,'message'=>'Tu correo ya está verificado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        "INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
         ON DUPLICATE KEY UPDATE
           token_hash = VALUES(token_hash),
           expires_at = VALUES(expires_at),
           created_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$userId, $hash]);

    $config = aldia_mail_config();
    $appUrl = rtrim((string)($config['app_url'] ?? 'https://aldiabilletera.com'), '/');
    $link = $appUrl . '/api/verify-email.php?token=' . rawurlencode($token);

    $html = aldia_email_shell(
        'Verifica tu correo',
        '<p>Confirma que este correo pertenece a tu cuenta de AL DÍA.</p><p>El enlace estará disponible durante 24 horas.</p>',
        'Verificar mi correo',
        $link
    );

    $mail = aldia_send_email($user['email'], 'Verifica tu correo · AL DÍA', $html);

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
        'message'=>'Te enviamos un enlace de verificación. Revisa tu correo.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'No se pudo enviar la verificación.'], JSON_UNESCAPED_UNICODE);
}
