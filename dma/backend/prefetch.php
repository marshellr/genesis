<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$elo = $argv[1] ?? '';
if (!in_array($elo, ['emerald_plus', 'diamond_plus', 'master_plus'], true)) {
    fwrite(STDERR, "A supported rank is required.\n");
    exit(2);
}

require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/riot-api.php';

try {
    $stats = metasrc_get_champion_stats($elo);
    if (empty($stats)) {
        $pools = db_get_champion_pools();
        if (!empty($pools)) {
            leagueofgraphs_get_champion_stats_for_pools($pools, $elo);
        }
    }
    echo "DMA cache prefetch completed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "DMA cache prefetch failed.\n");
    exit(1);
}
