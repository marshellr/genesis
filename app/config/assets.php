<?php
declare(strict_types=1);

function asset_url(string $path): string
{
    $digest = hash_file('sha256', __DIR__ . '/../public' . $path);
    if ($digest === false) {
        throw new RuntimeException('Asset is missing');
    }
    return htmlspecialchars($path . '?v=' . substr($digest, 0, 16), ENT_QUOTES, 'UTF-8');
}
