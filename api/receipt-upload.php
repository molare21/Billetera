<?php

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/config.php';

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

if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'No se recibió una factura válida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['receipt'];

if ($file['size'] > 8 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['success'=>false,'message'=>'La factura debe pesar menos de 8 MB.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if (!isset($allowed[$mime])) {
    http_response_code(415);
    echo json_encode(['success'=>false,'message'=>'Usa una imagen JPG, PNG o WEBP.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS receipts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NULL,
            mime_type VARCHAR(64) NOT NULL,
            storage_path VARCHAR(500) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_receipts_user (user_id),
            INDEX idx_receipts_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $base = dirname($_SERVER['DOCUMENT_ROOT']) . '/private/receipts/' . $userId;
    if (!is_dir($base) && !mkdir($base, 0700, true) && !is_dir($base)) {
        throw new RuntimeException('Storage error');
    }

    $filename = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
    $destination = $base . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Upload error');
    }

    @chmod($destination, 0600);

    $stmt = $pdo->prepare(
        "INSERT INTO receipts (user_id, original_name, mime_type, storage_path)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([
        $userId,
        substr((string)$file['name'], 0, 255),
        $mime,
        $destination
    ]);

    echo json_encode([
        'success'=>true,
        'receipt'=>[
            'id'=>(int)$pdo->lastInsertId(),
            'original_name'=>substr((string)$file['name'], 0, 255)
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'No se pudo guardar la factura.'], JSON_UNESCAPED_UNICODE);
}
