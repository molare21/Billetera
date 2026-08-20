<?php

require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');
$redirect = 'https://aldiabilletera.com/?verified=0';

if ($token === '') {
    header('Location: ' . $redirect);
    exit;
}

try {
    $hash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        "SELECT user_id, expires_at
         FROM email_verification_tokens
         WHERE token_hash = ?
         LIMIT 1"
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (!$row || strtotime($row['expires_at']) < time()) {
        header('Location: ' . $redirect);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
    $stmt->execute([(int)$row['user_id']]);

    $stmt = $pdo->prepare("DELETE FROM email_verification_tokens WHERE user_id = ?");
    $stmt->execute([(int)$row['user_id']]);

    $pdo->commit();

    header('Location: https://aldiabilletera.com/?verified=1');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: ' . $redirect);
    exit;
}
