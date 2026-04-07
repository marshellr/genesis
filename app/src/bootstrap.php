<?php

declare(strict_types=1);

require __DIR__ . '/env.php';
require __DIR__ . '/db.php';
require __DIR__ . '/InventoryRepository.php';

$fileConfig = loadEnv(dirname(__DIR__) . '/.env');
$defaults = [
    'APP_NAME' => 'Genesis Inventory',
    'APP_ENV' => 'development',
    'APP_DEBUG' => 'false',
    'APP_URL' => 'http://127.0.0.1:8080',
    'APP_TIMEZONE' => 'UTC',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'genesis_inventory',
    'DB_USERNAME' => 'genesis',
    'DB_PASSWORD' => '',
];

$envOverrides = [];
foreach (array_keys($defaults) as $key) {
    $value = getenv($key);
    if ($value !== false) {
        $envOverrides[$key] = $value;
    }
}

$config = array_merge($defaults, $fileConfig, $envOverrides);

date_default_timezone_set($config['APP_TIMEZONE']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function appConfig(string $key, mixed $default = null): mixed
{
    global $config;

    return $config[$key] ?? $default;
}

function appName(): string
{
    return (string) appConfig('APP_NAME', 'Genesis Inventory');
}

function isDebug(): bool
{
    return filter_var((string) appConfig('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectTo(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token): void
{
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function rememberInput(array $input): void
{
    $_SESSION['old_input'] = $input;
}

function oldInput(string $key, string $default = ''): string
{
    $input = $_SESSION['old_input'] ?? [];

    return (string) ($input[$key] ?? $default);
}

function clearOldInput(): void
{
    unset($_SESSION['old_input']);
}

function jsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function inventoryRepository(): InventoryRepository
{
    static $repository = null;

    if ($repository instanceof InventoryRepository) {
        return $repository;
    }

    $pdo = makePdo([
        'DB_HOST' => (string) appConfig('DB_HOST'),
        'DB_PORT' => (string) appConfig('DB_PORT'),
        'DB_DATABASE' => (string) appConfig('DB_DATABASE'),
        'DB_USERNAME' => (string) appConfig('DB_USERNAME'),
        'DB_PASSWORD' => (string) appConfig('DB_PASSWORD'),
    ]);

    $repository = new InventoryRepository($pdo);

    return $repository;
}

function normalizeInventoryInput(array $input): array
{
    return [
        'name' => trim((string) ($input['name'] ?? '')),
        'sku' => strtoupper(trim((string) ($input['sku'] ?? ''))),
        'quantity' => trim((string) ($input['quantity'] ?? '0')),
        'location' => trim((string) ($input['location'] ?? '')),
        'notes' => trim((string) ($input['notes'] ?? '')),
    ];
}

function validateInventoryInput(array $input): array
{
    $errors = [];

    if ($input['name'] === '' || mb_strlen($input['name']) > 120) {
        $errors[] = 'Name is required and must be at most 120 characters.';
    }

    if ($input['sku'] === '' || mb_strlen($input['sku']) > 64) {
        $errors[] = 'SKU is required and must be at most 64 characters.';
    }

    if ($input['quantity'] === '' || filter_var($input['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
        $errors[] = 'Quantity must be a non-negative integer.';
    }

    if (mb_strlen($input['location']) > 120) {
        $errors[] = 'Location must be at most 120 characters.';
    }

    if (mb_strlen($input['notes']) > 1000) {
        $errors[] = 'Notes must be at most 1000 characters.';
    }

    return $errors;
}
