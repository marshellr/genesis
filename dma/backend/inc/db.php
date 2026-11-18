<?php
declare(strict_types=1);

require_once __DIR__.'/functions.php';

/**
 * Zentrale DB-Verbindung (MariaDB)
 */
function db(): mysqli {
    static $db;
    if ($db instanceof mysqli) {
        return $db;
    }

    $cfg = app_config();
    $dbHost = (string)($cfg['DB_HOST'] ?? 'localhost');
    $dbUser = (string)($cfg['DB_USER'] ?? 'user');
    $dbPass = (string)($cfg['DB_PASS'] ?? 'user');
    $dbName = (string)($cfg['DB_NAME'] ?? 'lolstats');

    $db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($db->connect_errno) {
        throw new RuntimeException('DB-Verbindung fehlgeschlagen: ' . $db->connect_error);
    }
    if (!$db->set_charset('utf8mb4')) {
        throw new RuntimeException('Konnte Zeichensatz nicht setzen: ' . $db->error);
    }

    return $db;
}

/**
 * Riot-Match speichern (inkl. Team-Summaries)
 */
function db_save_match(array $m): void {
    $db = db();
    $sql = "REPLACE INTO matches 
        (match_id, timestamp, duration, total_kills, total_deaths, total_assists, 
         total_cs, total_vision, total_pinks, dragons, barons, heralds, won, 
         total_dragons, total_barons, total_heralds, json_data)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare fehlgeschlagen: " . $db->error);

    $id             = $m['matchId'];
    $timestamp      = (int)($m['timestamp'] ?? 0);
    $duration       = (float)($m['duration'] ?? 0);
    $kills          = (int)($m['total']['kills'] ?? 0);
    $deaths         = (int)($m['total']['deaths'] ?? 0);
    $assists        = (int)($m['total']['assists'] ?? 0);
    $cs             = (int)($m['total']['cs'] ?? 0);
    $vision         = (int)($m['total']['vision'] ?? 0);
    $pinks          = (int)($m['total']['pinks'] ?? 0);
    $dragons        = (int)($m['objectives']['team']['dragons'] ?? 0);
    $barons         = (int)($m['objectives']['team']['barons'] ?? 0);
    $heralds        = (int)($m['objectives']['team']['heralds'] ?? 0);
    $won            = (int)($m['won'] ?? false);
    $total_dragons  = (int)($m['objectives']['total']['dragons'] ?? 0);
    $total_barons   = (int)($m['objectives']['total']['barons'] ?? 0);
    $total_heralds  = (int)($m['objectives']['total']['heralds'] ?? 0);
    $json           = json_encode($m, JSON_UNESCAPED_UNICODE);

    $stmt->bind_param(
        'sidiiiiiiiiiiiiis',
        $id, $timestamp, $duration, $kills, $deaths, $assists, $cs, $vision, $pinks, 
        $dragons, $barons, $heralds, $won, 
        $total_dragons, $total_barons, $total_heralds, $json
    );
    
    if (!$stmt->execute()) {
        throw new RuntimeException("Match speichern fehlgeschlagen: " . $stmt->error);
    }

    // Spieler speichern
    if (!empty($m['roles'])) {
        db_save_match_players($id, $m['roles']);
    }
}

/**
 * Spieler-Stats pro Match speichern
 */
function db_save_match_players(string $matchId, array $roles): void {
    $db = db();
    // alte Einträge löschen, um Duplikate zu vermeiden
    $stmtDel = $db->prepare("DELETE FROM match_players WHERE match_id = ?");
    if (!$stmtDel) {
        throw new RuntimeException("Prepare fehlgeschlagen: " . $db->error);
    }
    $stmtDel->bind_param('s', $matchId);
    $stmtDel->execute();

    $sql = "INSERT INTO match_players 
        (match_id, role, champ, kills, deaths, assists, cs, vision, pinks, kda, kp, csmin)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare fehlgeschlagen: " . $db->error);

    foreach ($roles as $role => $r) {
        $champ  = $r['champ'] ?? '';
        $kills  = (int)($r['kills'] ?? 0);
        $deaths = (int)($r['deaths'] ?? 0);
        $assists= (int)($r['assists'] ?? 0);
        $cs     = (int)($r['cs'] ?? 0);
        $vision = (int)($r['vision'] ?? 0);
        $pinks  = (int)($r['pinks'] ?? 0);
        $kda    = (float)($r['kda'] ?? 0);
        $kp     = (float)($r['kp'] ?? 0);
        $csmin  = (float)($r['csmin'] ?? 0);

        $stmt->bind_param(
            'sssiiiiidddd',
            $matchId, $role, $champ, $kills, $deaths, $assists,
            $cs, $vision, $pinks, $kda, $kp, $csmin
        );
        
        if (!$stmt->execute()) {
            error_log("Spieler speichern fehlgeschlagen: " . $stmt->error);
        }
    }
}

/**
 * Alle gespeicherten Matches abrufen
 */
function db_get_matches(): array {
    $db = db();
    $sql = "
        SELECT match_id, timestamp, duration, total_kills, total_deaths, total_assists,
               total_cs, total_vision, total_pinks, dragons, barons, heralds, won,
               total_dragons, total_barons, total_heralds, json_data
        FROM matches ORDER BY timestamp DESC
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("DB Error in db_get_matches: " . $db->error);
        return [];
    }
    
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Ein einzelnes Match anhand der ID abrufen
 */
function db_get_match_by_id(string $matchId): ?array {
    $db = db();
    $stmt = $db->prepare("
        SELECT match_id, timestamp, duration, total_kills, total_deaths, total_assists,
               total_cs, total_vision, total_pinks, dragons, barons, heralds, won,
               total_dragons, total_barons, total_heralds, json_data
        FROM matches WHERE match_id = ?
    ");
    if (!$stmt) return null;
    
    $stmt->bind_param('s', $matchId);
    $stmt->execute();
    $res = $stmt->get_result();
    $match = $res->fetch_assoc();
    
    if ($match) {
        $match['won'] = (bool)$match['won'];
    }
    
    return $match ?: null;
}

/**
 * Mehrere Matches anhand ihrer IDs abrufen
 */
function db_get_matches_by_ids(array $matchIds): array {
    if (empty($matchIds)) return [];
    
    $db = db();
    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $types = str_repeat('s', count($matchIds));
    
    $sql = "SELECT * FROM matches WHERE match_id IN ($placeholders)";
    // Die Abfrage kann fehlschlagen, wenn $placeholders leer ist.
    // Wir bereiten die Abfrage nur vor, wenn es auch IDs gibt.
    $stmt = $db->prepare($sql); 

    if (!$stmt) return [];

    $stmt->bind_param($types, ...$matchIds);
    $stmt->execute();
    $res = $stmt->get_result();
    
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Spieler eines Matches abrufen
 */
function db_get_match_players(string $matchId): array {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM match_players WHERE match_id = ?");
    if (!$stmt) return [];
    
    $stmt->bind_param('s', $matchId);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Spieler mehrerer Matches abrufen
 */
function db_get_players_by_match_ids(array $matchIds): array {
    if (empty($matchIds)) return [];

    $db = db();
    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $types = str_repeat('s', count($matchIds));

    $sql = "SELECT * FROM match_players WHERE match_id IN ($placeholders)";
    $stmt = $db->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param($types, ...$matchIds);
    $stmt->execute();
    $res = $stmt->get_result();

    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Prime-League-Matches speichern (mit Riot-Verknüpfung)
 */
function db_save_prime_matches(array $matches): void {
    $db = db();
    $sql = "
        INSERT INTO prime_matches 
        (id, team1, team2, result, match_day, status, begin, riot_match_ids)
        VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?)
        ON DUPLICATE KEY UPDATE
            team1=VALUES(team1),
            team2=VALUES(team2),
            result=VALUES(result),
            match_day=VALUES(match_day),
            status=VALUES(status),
            begin=VALUES(begin),
            riot_match_ids=VALUES(riot_match_ids)";
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare fehlgeschlagen: " . $db->error);

    foreach ($matches as $m) {
        $id     = (int)($m['id'] ?? 0);
        $team1  = (string)($m['team1'] ?? '');
        $team2  = (string)($m['team2'] ?? '');
        $result = (string)($m['result'] ?? '');
        $day    = (int)($m['match_day'] ?? 0);
        $status = (string)($m['status'] ?? '');
        
        // ISO 8601 Datum konvertieren (z.B. "2025-11-02T15:00:00+01:00" -> "2025-11-02 15:00:00")
        $beginRaw = $m['begin'] ?? null;
        $begin = null;
        if ($beginRaw) {
            $timestamp = strtotime($beginRaw);
            if ($timestamp !== false) {
                $begin = date('Y-m-d H:i:s', $timestamp);
            }
        }
        
        try {
            $riot = json_encode($m['riot_match_ids'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Konnte Riot-Match-IDs nicht serialisieren: ' . $e->getMessage(), 0, $e);
        }

        $beginForQuery = $begin ?? '';

        $stmt->bind_param('isssisss', $id, $team1, $team2, $result, $day, $status, $beginForQuery, $riot);
        
        if (!$stmt->execute()) {
            error_log("Prime-Match speichern fehlgeschlagen: " . $stmt->error);
        }
    }
}

/**
 * Prime-League-Matches abrufen
 */
function db_get_prime_matches(): array {
    $db = db();
    $sql = "SELECT * FROM prime_matches ORDER BY match_day ASC";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("DB Error in db_get_prime_matches: " . $db->error);
        return [];
    }
    
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    
    foreach ($rows as &$r) {
        if (!empty($r['riot_match_ids'])) {
            $decoded = json_decode($r['riot_match_ids'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $r['riot_match_ids'] = $decoded;
            } else {
                $r['riot_match_ids'] = [];
            }
        } else {
            $r['riot_match_ids'] = [];
        }
    }
    unset($r);
    return $rows;
}

/**
 * Riot-Matches mit Prime-Matches verknüpfen (und DB aktualisieren)
 */
function db_link_riot_to_prime(array $primeMatches, array $riotMatches, int $minMatchDuration, int $matchTimeTolerance): void {
    foreach ($primeMatches as $pm) {
        if (empty($pm['begin'])) continue;
        $tMatch = strtotime($pm['begin']);
        if (!$tMatch) continue;
        
        $linked = [];

        foreach ($riotMatches as $rm) {
            // Nur Matches über 15 Minuten berücksichtigen
            $duration = isset($rm['duration']) ? (float)$rm['duration'] : 0;
            if ($duration < $minMatchDuration) continue;
            
            $riotTime = ($rm['timestamp'] ?? 0) / 1000;
            if (abs($riotTime - $tMatch) < $matchTimeTolerance) {
                $linked[] = $rm['match_id'] ?? '';
            }
        }

        if ($linked) {
            $linked = array_values(array_unique(array_filter(
                $linked,
                static fn($id) => is_string($id) && $id !== ''
            )));

            if (empty($linked)) {
                continue;
            }

            $db = db();
            $stmt = $db->prepare("UPDATE prime_matches SET riot_match_ids = ? WHERE id = ?");
            if (!$stmt) {
                error_log("Link-Update prepare failed: " . $db->error);
                continue;
            }
            
            try {
                $json = json_encode($linked, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('Link-Update JSON encode failed: ' . $e->getMessage());
                continue;
            }
            $id = (int)$pm['id'];
            $stmt->bind_param('si', $json, $id);
            
            if (!$stmt->execute()) {
                error_log("Link-Update execute failed: " . $stmt->error);
            }
        }
    }
}

/**
 * Letzten Riot-Sync-Zeitstempel abrufen
 */
function db_get_last_riot_sync(): int {
    $db = db();
    $stmt = $db->prepare("SELECT sync_value FROM sync_state WHERE sync_key = 'last_riot_sync'");
    if (!$stmt) return 0;
    
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    
    return $row ? (int)$row['sync_value'] : 0;
}

/**
 * Riot-Sync-Zeitstempel speichern
 */
function db_set_last_riot_sync(int $timestamp): void {
    $db = db();
    $sql = "
        INSERT INTO sync_state (sync_key, sync_value) 
        VALUES ('last_riot_sync', ?)
        ON DUPLICATE KEY UPDATE sync_value = VALUES(sync_value)
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Set sync timestamp failed: " . $db->error);
        return;
    }
    
    $stmt->bind_param('i', $timestamp);
    $stmt->execute();
}

/**
 * Champions synchronisieren (aus Riot API)
 */
function db_sync_champions(array $champions): void {
    $db = db();
    
    $sql = "
        INSERT INTO champions (id, name, champion_key, title, icon_url)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            champion_key = VALUES(champion_key),
            title = VALUES(title),
            icon_url = VALUES(icon_url)
    ";
    
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $db->error);
    
    foreach ($champions as $champ) {
        $id = $champ['id'];
        $name = $champ['name'];
        $key = $champ['key'];
        $title = $champ['title'];
        $icon = $champ['icon'];
        
        $stmt->bind_param('issss', $id, $name, $key, $title, $icon);
        $stmt->execute();
    }
}

/**
 * Alle Champions aus DB holen
 */
function db_get_all_champions(): array {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM champions ORDER BY name ASC");
    if (!$stmt) return [];
    
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Champion Pool - Champion zu Role hinzufügen
 */
function db_add_champion_to_pool(int $championId, string $role): int {
    $db = db();
    
    // Prüfe ob Champion existiert
    $stmt = $db->prepare("SELECT id FROM champions WHERE id = ?");
    $stmt->bind_param('i', $championId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new RuntimeException("Champion nicht gefunden");
    }
    
    // Ermittle höchste Position für diese Role
    $stmt = $db->prepare("SELECT MAX(position) as max_pos FROM champion_pool WHERE role = ?");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $nextPos = ($result['max_pos'] ?? 0) + 1;
    
    $sql = "INSERT INTO champion_pool (champion_id, role, position) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $db->error);
    
    $stmt->bind_param('isi', $championId, $role, $nextPos);
    if (!$stmt->execute()) {
        // Duplikat? (Champion bereits in dieser Role)
        if ($db->errno === 1062) {
            throw new RuntimeException("Champion bereits in dieser Role");
        }
        throw new RuntimeException("Execute failed: " . $stmt->error);
    }

    return $db->insert_id;
}

/**
 * Champion Pool - Champion löschen
 */
function db_delete_champion_pool(int $id): void {
    $db = db();
    $stmt = $db->prepare("DELETE FROM champion_pool WHERE id = ?");
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $db->error);
    
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new RuntimeException("Execute failed: " . $stmt->error);
    }
}

/**
 * Champion Pool - Alle Champions mit Details abrufen
 */
function db_get_champion_pools(): array {
    $db = db();
    $sql = "
        SELECT cp.*, c.name, c.champion_key, c.icon_url
        FROM champion_pool cp
        JOIN champions c ON cp.champion_id = c.id
        ORDER BY cp.role, cp.position ASC
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("DB Error in db_get_champion_pools: " . $db->error);
        return [];
    }
    
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
