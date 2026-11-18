<?php
declare(strict_types=1);

if (!function_exists('safe')) {
    function safe(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('app_config')) {
    /**
     * Returns the merged application configuration (env plus local overrides).
     *
     * @return array<string, mixed>
     */
    function app_config(): array
    {
        static $config;
        if ($config === null) {
            $configFile = __DIR__ . '/env.php';
            $loaded = require $configFile;
            if (!is_array($loaded)) {
                throw new RuntimeException('env.php must return an array.');
            }
            $config = $loaded;
        }
        return $config;
    }
}

if (!function_exists('config')) {
    /**
     * Convenience shortcut for configuration values.
     *
     * @template T
     * @param string $key
     * @param T $default
     * @return mixed|T
     */
    function config(string $key, mixed $default = null): mixed
    {
        $cfg = app_config();
        return $cfg[$key] ?? $default;
    }
}

if (!function_exists('human_date')) {
    /**
     * Formats a date safely and returns a fallback marker when parsing fails.
     */
    function human_date(?string $value, string $format = 'd.m.Y'): string
    {
        if ($value === null || $value === '') {
            return '--';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '--';
        }

        return date($format, $timestamp);
    }
}
