<?php
/**
 * Centrale Konfiguration für API-Keys und Datenbankzugriff.
 *
 * Produktion: Werte als Umgebungsvariablen bereitstellen (z.B. mit
 * systemd unit, Apache/Nginx SetEnv oder Docker Secrets).
 *
 * Entwicklung: Optional eine inc/env.local.php anlegen, die ein Array mit
 * den benötigten Schlüsseln zurückliefert. Diese Datei sollte git-ignored sein.
 */

$localConfig = [];
$localOverride = __DIR__ . '/env.local.php';
if (is_readable($localOverride)) {
    $localConfig = require $localOverride;
    if (!is_array($localConfig)) {
        throw new RuntimeException('env.local.php muss ein Array zurückgeben.');
    }
}

return [
    // Riot API
    'RIOT_API_KEY'      => getenv('RIOT_API_KEY')      ?: ($localConfig['RIOT_API_KEY']      ?? ''),
    'GAME_NAME'         => getenv('GAME_NAME')         ?: ($localConfig['GAME_NAME']         ?? ''),
    'TAG_LINE'          => getenv('TAG_LINE')          ?: ($localConfig['TAG_LINE']          ?? ''),
    'REGION_ROUTING'    => getenv('REGION_ROUTING')    ?: ($localConfig['REGION_ROUTING']    ?? 'europe'),
    'PLATFORM'          => getenv('PLATFORM')          ?: ($localConfig['PLATFORM']          ?? 'euw1'),

    // Primebot API
    'PRIMEBOT_TEAM_ID'  => (int)(getenv('PRIMEBOT_TEAM_ID') ?: ($localConfig['PRIMEBOT_TEAM_ID'] ?? 0)),
    'PRIMEBOT_TOKEN'    => getenv('PRIMEBOT_TOKEN')    ?: ($localConfig['PRIMEBOT_TOKEN']    ?? ''),

    // Datenbank
    'DB_HOST'           => getenv('DB_HOST')           ?: ($localConfig['DB_HOST']           ?? 'localhost'),
    'DB_USER'           => getenv('DB_USER')           ?: ($localConfig['DB_USER']           ?? 'user'),
    'DB_PASS'           => getenv('DB_PASS')           ?: ($localConfig['DB_PASS']           ?? 'user'),
    'DB_NAME'           => getenv('DB_NAME')           ?: ($localConfig['DB_NAME']           ?? 'lolstats'),
    // Anwendungs-Konstanten
    'MATCH_TIME_TOLERANCE' => (int)(getenv('MATCH_TIME_TOLERANCE') ?: ($localConfig['MATCH_TIME_TOLERANCE'] ?? 10800)),
    'MIN_MATCH_DURATION'   => (int)(getenv('MIN_MATCH_DURATION')   ?: ($localConfig['MIN_MATCH_DURATION']   ?? 15)),
    'RIOT_SYNC_COOLDOWN'   => (int)(getenv('RIOT_SYNC_COOLDOWN')   ?: ($localConfig['RIOT_SYNC_COOLDOWN']   ?? 300)),
    'RIOT_MATCH_COUNT'     => (int)(getenv('RIOT_MATCH_COUNT')     ?: ($localConfig['RIOT_MATCH_COUNT']     ?? 10)),
];
