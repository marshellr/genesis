<?php
declare(strict_types=1);

// --- Central error and exception handling ---

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/lolranked.log');

error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) {
    $logMessage = sprintf(
        "[%s] Uncaught exception: '%s' in %s:%d\nStack trace:\n%s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($logMessage);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $headerPath = __DIR__ . '/header.php';
    $footerPath = __DIR__ . '/footer.php';
    $functionsPath = __DIR__ . '/functions.php';

    if (!function_exists('safe') && is_readable($functionsPath)) {
        require_once $functionsPath;
    }

    $pageTitle = 'Unexpected Error';
    $pageDescription = 'DMA encountered an unexpected application error.';
    $activePage = '';

    if (is_readable($headerPath)) {
        require $headerPath;
    }

    echo '<main class="container">';
    echo '<section class="page-intro">';
    echo '<p class="context-eyebrow">Application Error</p>';
    echo '<h1>An unexpected error occurred.</h1>';
    echo '<p>The issue has been logged. Please try again later.</p>';
    echo '</section>';
    echo '</main>';

    if (is_readable($footerPath)) {
        require $footerPath;
    }

    exit;
});
