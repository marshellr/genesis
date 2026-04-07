<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$publicPath = __DIR__ . '/public' . $uri;

if ($uri !== '/' && is_file($publicPath)) {
    return false;
}

require __DIR__ . '/public/index.php';
