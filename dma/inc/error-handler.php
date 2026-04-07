<?php
declare(strict_types=1);

// --- Zentrales Fehler- und Exception-Handling ---

// In einer Produktionsumgebung sollten Fehler nicht direkt ausgegeben werden.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/lolranked.log');

error_reporting(E_ALL);

// Wandelt PHP-Fehler (Warnings, Notices) in Exceptions um, damit sie vom Exception-Handler gefangen werden.
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // Dieser Fehler-Code ist nicht in error_reporting() enthalten.
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Fängt alle nicht abgefangenen Exceptions.
set_exception_handler(function (Throwable $e) {
    // Logge die vollständige Fehlermeldung für die Fehlersuche.
    $logMessage = sprintf(
        "[%s] Uncaught exception: '%s' in %s:%d\nStack trace:\n%s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($logMessage);

    // Gib eine nutzerfreundliche, allgemeine Fehlermeldung aus.
    // Stelle sicher, dass vor dieser Ausgabe kein anderer Output gesendet wurde.
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    // Lade eine saubere Fehlerseite. Dies ist besser als hier direkt HTML auszugeben.
    // Wir nutzen das bestehende Layout, wenn verfügbar.
    $headerPath = __DIR__ . '/header.php';
    $footerPath = __DIR__ . '/footer.php';
    $functionsPath = __DIR__ . '/functions.php';

    if (!function_exists('safe') && is_readable($functionsPath)) {
        require_once $functionsPath;
    }

    $pageTitle = 'Unerwarteter Fehler';
    $activePage = ''; // Kein Nav-Punkt ist aktiv

    if (is_readable($headerPath)) {
        require $headerPath;
    }

    echo '<main class="container">';
    echo '<h1>Ein unerwarteter Fehler ist aufgetreten</h1>';
    echo '<p>Wir wurden über das Problem informiert und arbeiten daran. Bitte versuchen Sie es später erneut.</p>';
    echo '</main>';

    if (is_readable($footerPath)) {
        require $footerPath;
    }

    // Beende die Skriptausführung, um weitere Fehler zu vermeiden.
    exit;
});
