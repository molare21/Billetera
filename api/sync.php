<?php

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No hay una sesión activa.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_state (
            user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            state_json LONGTEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_state_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare(
            "SELECT state_json, updated_at
             FROM user_state
             WHERE user_id = ?
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode([
                'success' => true,
                'state' => null,
                'updated_at' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $state = json_decode($row['state_json'], true);
        if (!is_array($state)) $state = null;

        echo json_encode([
            'success' => true,
            'state' => $state,
            'updated_at' => $row['updated_at']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');

        if (strlen($raw) > 8 * 1024 * 1024) {
            http_response_code(413);
            echo json_encode([
                'success' => false,
                'message' => 'Los datos son demasiado grandes.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $payload = json_decode($raw, true);
        $state = $payload['state'] ?? null;

        if (!is_array($state)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Estado inválido.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $allowed = [
            'entries','campaigns','fixedExpenses','fixedIncome',
            'expensePresets','incomePresets',
            'hiddenExpensePresets','hiddenIncomePresets',
            'budgets','categoryMemory','rate','rateUpdated'
        ];

        $clean = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $state)) {
                $clean[$key] = $state[$key];
            }
        }

        $json = json_encode(
            $clean,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No se pudieron serializar los datos.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO user_state (user_id, state_json)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                state_json = VALUES(state_json),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$userId, $json]);

        echo json_encode([
            'success' => true,
            'message' => 'Datos sincronizados.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudieron sincronizar los datos.'
    ], JSON_UNESCAPED_UNICODE);
}
