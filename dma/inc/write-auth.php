<?php
declare(strict_types=1);

/**
 * Only the configured operator may change stored DMA data.
 * HTTP credentials are checked here, independently of proxy headers.
 */
function dma_write_authorized(array $server, array $settings): bool
{
    if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }
    $expectedUser = (string) ($settings['DMA_WRITE_USERNAME'] ?? '');
    $hashFile = (string) ($settings['DMA_WRITE_PASSWORD_HASH_FILE'] ?? '');
    $user = $server['PHP_AUTH_USER'] ?? null;
    $password = $server['PHP_AUTH_PW'] ?? null;
    if ($expectedUser === '' || $hashFile === '' || !is_readable($hashFile)
        || !is_string($user) || !is_string($password)) {
        return false;
    }
    $hash = trim((string) file_get_contents($hashFile));
    $passwordValid = password_verify($password, $hash);
    return $passwordValid && hash_equals($expectedUser, $user);
}

function dma_require_writer(): void
{
    if (dma_write_authorized($_SERVER, app_config())) {
        return;
    }
    http_response_code(401);
    header('WWW-Authenticate: Basic realm="DMA operator", charset="UTF-8"');
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => false, 'error' => 'Operator authentication required.']);
    exit;
}
