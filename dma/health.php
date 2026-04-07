<?php
declare(strict_types=1);

require_once __DIR__.'/inc/error-handler.php';
require_once __DIR__.'/inc/db.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $db = db();
    $result = $db->query('SELECT 1');
    if ($result === false) {
        throw new RuntimeException('DB ping fehlgeschlagen');
    }

    echo json_encode([
        'status' => 'ok',
        'service' => 'dma',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'service' => 'dma',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
