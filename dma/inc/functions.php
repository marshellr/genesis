<?php
declare(strict_types=1);

if (!function_exists('safe')) {
    /**
     * Escapes a string for safe HTML output.
     *
     * @param string|null $s The string to escape.
     * @return string The escaped string.
     */
    function safe(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('app_config')) {
    /**
     * Liefert die zusammengeführte Anwendungskonfiguration (env + lokale Overrides).
     *
     * @return array<string, mixed>
     */
    function app_config(): array {
        static $config;
        if ($config === null) {
            $configFile = __DIR__ . '/env.php';
            $loaded = require $configFile;
            if (!is_array($loaded)) {
                throw new RuntimeException('env.php muss ein Array zurückgeben.');
            }
            $config = $loaded;
        }
        return $config;
    }
}

if (!function_exists('config')) {
    /**
     * Komfort-Shortcut für Konfigurationswerte.
     *
     * @template T
     * @param string $key
     * @param T $default
     * @return mixed|T
     */
    function config(string $key, mixed $default = null): mixed {
        $cfg = app_config();
        return $cfg[$key] ?? $default;
    }
}

if (!function_exists('human_date')) {
    /**
     * Formatiert ein Datum sicher (liefert \"—\" bei ungültigen Eingaben).
     */
    function human_date(?string $value, string $format = 'd.m.Y'): string {
        if ($value === null || $value === '') {
            return '—';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '—';
        }
        return date($format, $timestamp);
    }
}
