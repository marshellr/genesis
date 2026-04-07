<?php
declare(strict_types=1);

/**
 * Riot Data Dragon API Helper
 * Holt aktuelle Patch-Version und Champion-Daten
 */

/**
 * Aktuelle Patch-Version holen
 */
function riot_get_current_patch(): string {
    static $patch = null;
    
    if ($patch !== null) {
        return $patch;
    }
    
    $url = "https://ddragon.leagueoflegends.com/api/versions.json";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        error_log("Riot API Error (versions): " . $error);
        return '14.20'; // Fallback
    }
    
    $versions = json_decode($response, true);
    if (!is_array($versions) || empty($versions)) {
        return '14.20'; // Fallback
    }
    
    $patch = $versions[0]; // Neueste Version
    return $patch;
}

/**
 * Alle Champions mit Details holen
 */
function riot_get_all_champions(): array {
    static $champions = null;
    
    if ($champions !== null) {
        return $champions;
    }
    
    $patch = riot_get_current_patch();
    $url = "https://ddragon.leagueoflegends.com/cdn/{$patch}/data/en_US/champion.json";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        error_log("Riot API Error (champions): " . $error);
        return [];
    }
    
    $data = json_decode($response, true);
    if (!isset($data['data'])) {
        return [];
    }
    
    $champions = [];
    foreach ($data['data'] as $champ) {
        $champions[] = [
            'id' => (int)$champ['key'],
            'name' => $champ['name'],
            'key' => $champ['id'], // API Key (z.B. "MonkeyKing" für Wukong)
            'title' => $champ['title'],
            'icon' => "https://ddragon.leagueoflegends.com/cdn/{$patch}/img/champion/{$champ['id']}.png"
        ];
    }
    
    // Sortiere alphabetisch
    usort($champions, fn($a, $b) => strcmp($a['name'], $b['name']));
    
    return $champions;
}

/**
 * Champion-ID aus Name holen
 */
function riot_get_champion_id_by_name(string $name): ?int {
    $champions = riot_get_all_champions();
    
    // Normalize name from scraper: remove all non-alphanumeric characters and convert to lowercase
    $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    
    foreach ($champions as $champ) {
        // Normalize champion name from Riot API
        $champNameNormalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $champ['name']));
        // Also normalize the champion key (e.g., "MonkeyKing" for Wukong)
        $champKeyNormalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $champ['key']));

        // Compare against both the name and the key
        if ($champNameNormalized === $normalized || $champKeyNormalized === $normalized) {
            return $champ['id'];
        }
    }
    
    return null;
}

/**
 * Champion-Details aus ID holen
 */
function riot_get_champion_by_id(int $id): ?array {
    $champions = riot_get_all_champions();
    
    foreach ($champions as $champ) {
        if ($champ['id'] === $id) {
            return $champ;
        }
    }
    
    return null;
}

/**
 * MetaSrc Champion Stats scrapen
 */
function metasrc_get_champion_stats(string $elo = 'emerald_plus'): array
{
    $patch = riot_get_current_patch();

    $rankGroups = [
        'emerald_plus' => ['emerald', 'diamond', 'master', 'grandmaster', 'challenger'],
        'diamond_plus' => ['diamond', 'master', 'grandmaster', 'challenger'],
        'master_plus' => ['master', 'grandmaster', 'challenger']
    ];

    $ranksForScraping = $rankGroups[$elo] ?? $rankGroups['emerald_plus'];
    $metaRanksParam = implode(',', $ranksForScraping);

    error_log("MetaSrc: Selected ELO: {$elo}, Ranks for scraping: {$metaRanksParam}");

    $singleRankForJsonApi = $ranksForScraping[0];

    if (!class_exists('DOMDocument')) {
        error_log('PHP-Erweiterung "php-xml" (DOM) ist nicht installiert oder aktiviert. Metasrc Scraping nicht möglich.');
        return metasrc_try_json_api($singleRankForJsonApi, $patch);
    }
    error_log("MetaSrc: DOMDocument is available.");

    $cacheKey = md5($metaRanksParam . '_' . $patch);
    $cacheFile = sys_get_temp_dir() . "/metasrc_stats_{$cacheKey}.json";

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        error_log("MetaSrc: Loading from cache: {$cacheFile}");
        $cachedContent = file_get_contents($cacheFile);
        $cachedData = json_decode($cachedContent, true);

        // Validierung: Prüfe, ob der Cache die neue, verschachtelte Struktur hat.
        $firstItem = $cachedData ? reset($cachedData) : null;
        if (is_array($firstItem) && is_array(reset($firstItem)) && isset(reset($firstItem)['winrate'])) {
            return $cachedData; // Cache ist valide
        }

        // Cache ist veraltet, ignoriere ihn.
        error_log("MetaSrc: Cache file has outdated format. Ignoring and re-fetching.");
    }

    $url = "https://www.metasrc.com/lol/stats?ranks={$metaRanksParam}&patch={$patch}";
    error_log("MetaSrc: Scraping URL: " . $url);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9'
        ]
    ]);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($html)) {
        error_log("MetaSrc scraping failed: HTTP $httpCode, HTML empty: " . (empty($html) ? 'true' : 'false') . ". Falling back to JSON API.");
        return metasrc_try_json_api($singleRankForJsonApi, $patch);
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $stats = [];
    $rows = $xpath->query("//table[contains(@class, 'stats-table')]//tbody/tr");
    error_log("MetaSrc: Found " . $rows->length . " champion rows for scraping.");

    foreach ($rows as $row) {
        $nameNode = $xpath->evaluate(".//td[1]//span[@hidden]", $row)->item(0); // Name is in a hidden span in the 1st column
        $roleNode = $xpath->evaluate(".//td[2]", $row)->item(0); // Role is in the 2nd column
        $tierNode = $xpath->evaluate(".//td[3]", $row)->item(0); // Tier is in the 3rd column
        $rolePercentNode = $xpath->evaluate(".//td[7]", $row)->item(0); // Role % is in the 7th column
        $winrateNode = $xpath->evaluate(".//td[6]", $row)->item(0); // Winrate is in the 6th column

        if (!$nameNode || !$winrateNode || !$roleNode || !$rolePercentNode) {
            error_log("MetaSrc: Skipping a row, not all required nodes found (name, role, winrate, role %).");
            continue;
        }

        $championName = trim($nameNode->nodeValue);
        $winrateText = trim($winrateNode->nodeValue);
        $winrate = (float)str_replace('%', '', $winrateText);

        // Map role text to the keys used in your application ('Top', 'Jungle', etc.)
        $roleText = strtoupper(trim($roleNode->nodeValue));
        $roleMap = ['TOP' => 'Top', 'JUNGLE' => 'Jungle', 'MID' => 'Mid', 'ADC' => 'ADC', 'SUPPORT' => 'Supp'];
        $role = $roleMap[$roleText] ?? null;

        $rolePercent = (float)str_replace('%', '', trim($rolePercentNode->nodeValue));

        // Skip if we couldn't map the role
        if (!$role) continue;

        $tier = '?';
        if ($tierNode) {
            $tierText = trim($tierNode->nodeValue);
            if (preg_match('/([SABCD]\+?)$/', $tierText, $matches)) {
                $tier = $matches[1];
            }
        }

        if ($championName && $winrate > 0) {
            $champId = riot_get_champion_id_by_name($championName);
            if ($champId) {
                // Store stats per role for each champion, using the numeric ID as key
                $stats[$champId][$role] = [ // This creates the nested structure
                    'winrate' => round($winrate, 2),
                    'tier' => $tier === '?' ? calculate_tier($winrate) : $tier,
                    'role_percent' => $rolePercent
                ];
            } else {
                error_log("MetaSrc: Champion ID not found for scraped name: '{$championName}'. This champion will not be added to stats.");
            }
        }
    }

    error_log("MetaSrc: Total unique champion stats extracted: " . count($stats));

    if (empty($stats)) {
        error_log("MetaSrc: Scraping resulted in empty stats. Trying JSON API fallback with ELO: {$singleRankForJsonApi} and Patch: {$patch}.");
        $stats = metasrc_try_json_api($singleRankForJsonApi, $patch);
    }

    if (!empty($stats)) {
        file_put_contents($cacheFile, json_encode($stats));
    }

    return $stats;
}

/**
 * MetaSrc JSON API als Fallback
 * (Manchmal haben sie eine interne API die man finden kann)
 */
function metasrc_try_json_api(string $elo, string $patch): array {
    error_log("MetaSrc JSON API: Attempting to fetch data for ELO: {$elo}, Patch: {$patch}");
    $url = "https://www.metasrc.com/api/lol/stats/champions?rank={$elo}&patch={$patch}";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true, // Follow redirects (like HTTP 301)
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) {
        error_log("MetaSrc JSON API: Failed to fetch data. HTTP $httpCode, Response empty: " . (empty($response) ? 'true' : 'false'));
        return [];
    }
    // error_log("MetaSrc JSON API: Successfully fetched JSON (first 500 chars): " . substr($response, 0, 500)); // Uncomment for verbose JSON debugging
    
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return [];
    }
    
    $stats = [];
    foreach ($data as $item) {
        $name = $item['championName'] ?? $item['name'] ?? '';
        $wr = $item['winRate'] ?? $item['win_rate'] ?? 0;
        $tier = $item['tier'] ?? '';
        
        if ($name && $wr > 0) {
            $champId = riot_get_champion_id_by_name($name);
            if ($champId) {
                error_log("MetaSrc JSON API: Found: {$name} (ID: {$champId}), WR: {$wr}%, Tier: {$tier}");
                // Create the same nested structure as the scraping function
                // to avoid a TypeError in the frontend.
                $stats[$champId]['main'] = [ // 'main' is a fallback role key
                    'winrate' => round($wr, 2),
                    'tier' => $tier ?: calculate_tier($wr),
                    'role_percent' => 100.0 // Da die API keine Rollen liefert, nehmen wir 100% an
                ];
            } else {
                error_log("MetaSrc JSON API: Champion ID not found for JSON API name: '{$name}'.");
            }
        }
    }
    error_log("MetaSrc JSON API: Total unique champion stats from JSON API: " . count($stats));
    
    return $stats;
}

/**
 * Tier aus Winrate berechnen
 */
function calculate_tier(float $winrate): string {
    if ($winrate >= 53) return 'S';
    if ($winrate >= 51.5) return 'A';
    if ($winrate >= 50) return 'B';
    if ($winrate >= 48) return 'C';
    return 'D';
}