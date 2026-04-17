<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/error-handler.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/db.php';

$API_KEY = (string) config('RIOT_API_KEY', '');
$GAME_NAME = (string) config('GAME_NAME', '');
$TAG_LINE = (string) config('TAG_LINE', '');
$REGION = (string) config('REGION_ROUTING', 'europe');
$TEAM_ID = (int) config('PRIMEBOT_TEAM_ID', 0);
$TOKEN = (string) config('PRIMEBOT_TOKEN', '');

$MATCH_TIME_TOLERANCE = (int) config('MATCH_TIME_TOLERANCE', 10800);
$MIN_MATCH_DURATION = (int) config('MIN_MATCH_DURATION', 15);
$RIOT_SYNC_COOLDOWN = (int) config('RIOT_SYNC_COOLDOWN', 300);
$RIOT_MATCH_COUNT = (int) config('RIOT_MATCH_COUNT', 10);
$hasRiotSyncConfig = $API_KEY !== '' && $GAME_NAME !== '' && $TAG_LINE !== '';
$hasPrimeSyncConfig = $TEAM_ID > 0 && $TOKEN !== '';

function api_riot_get_json(string $url, string $apiKey): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FAILONERROR => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Riot-Token: ' . $apiKey, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($response === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL error: $curlError (HTTP $status)");
    }

    if ($status < 200 || $status >= 300) {
        curl_close($ch);
        throw new RuntimeException("HTTP $status");
    }

    curl_close($ch);

    return is_array($data = json_decode($response, true)) ? $data : [];
}

function api_riot_get_puuid(string $region, string $name, string $tag, string $key): string
{
    $url = "https://$region.api.riotgames.com/riot/account/v1/accounts/by-riot-id/"
        . rawurlencode($name) . '/' . rawurlencode($tag);
    $result = api_riot_get_json($url, $key);
    return $result['puuid'] ?? throw new RuntimeException('PUUID not found');
}

function api_riot_get_match_ids(string $region, string $puuid, string $key, int $count = 5): array
{
    $url = "https://$region.api.riotgames.com/lol/match/v5/matches/by-puuid/$puuid/ids?type=tourney&start=0&count=$count";
    return api_riot_get_json($url, $key);
}

function api_riot_get_team_stats(string $region, string $matchId, string $puuid, string $key): array
{
    $url = "https://{$region}.api.riotgames.com/lol/match/v5/matches/$matchId";
    $match = api_riot_get_json($url, $key);
    $info = $match['info'] ?? [];
    $players = $info['participants'] ?? [];
    $self = null;
    foreach ($players as $player) {
        if ($player['puuid'] === $puuid) {
            $self = $player;
        }
    }

    if (!$self) {
        throw new RuntimeException('Own player could not be found in the match data');
    }

    $teamId = $self['teamId'];
    $team = array_filter($players, fn($player) => $player['teamId'] === $teamId);
    $roleMap = ['TOP' => 'Top', 'JUNGLE' => 'Jungle', 'MIDDLE' => 'Mid', 'BOTTOM' => 'ADC', 'UTILITY' => 'Supp'];

    $stats = [
        'matchId' => $matchId,
        'timestamp' => $info['gameStartTimestamp'] ?? 0,
        'duration' => round(($info['gameDuration'] ?? 0) / 60, 2),
        'won' => false,
        'roles' => [],
        'total' => ['kills' => 0, 'deaths' => 0, 'assists' => 0, 'cs' => 0, 'vision' => 0, 'pinks' => 0],
    ];

    foreach ($info['teams'] ?? [] as $teamEntry) {
        if ($teamEntry['teamId'] === $teamId) {
            $stats['won'] = $teamEntry['win'] ?? false;
        }
    }

    foreach ($team as $player) {
        $role = $roleMap[$player['teamPosition']] ?? 'UNK';
        $kills = (int) $player['kills'];
        $deaths = (int) $player['deaths'];
        $assists = (int) $player['assists'];
        $cs = (int) $player['totalMinionsKilled'] + (int) $player['neutralMinionsKilled'];
        $vision = (int) $player['visionScore'];
        $pinks = (int) ($player['visionWardsBoughtInGame'] ?? 0);

        $stats['roles'][$role] = [
            'champ' => $player['championName'],
            'kills' => $kills,
            'deaths' => $deaths,
            'assists' => $assists,
            'cs' => $cs,
            'vision' => $vision,
            'pinks' => $pinks,
        ];
        $stats['total']['kills'] += $kills;
        $stats['total']['deaths'] += $deaths;
        $stats['total']['assists'] += $assists;
        $stats['total']['cs'] += $cs;
        $stats['total']['vision'] += $vision;
        $stats['total']['pinks'] += $pinks;
    }

    foreach ($stats['roles'] as &$roleStats) {
        $roleStats['kda'] = $roleStats['deaths'] > 0
            ? round(($roleStats['kills'] + $roleStats['assists']) / $roleStats['deaths'], 2)
            : $roleStats['kills'] + $roleStats['assists'];
        $roleStats['kp'] = $stats['total']['kills'] > 0
            ? round((($roleStats['kills'] + $roleStats['assists']) / $stats['total']['kills']) * 100, 2)
            : 0;
        $roleStats['csmin'] = $stats['duration'] > 0 ? round($roleStats['cs'] / $stats['duration'], 2) : 0;
    }
    unset($roleStats);

    $stats['total']['kda'] = $stats['total']['deaths'] > 0
        ? round(($stats['total']['kills'] + $stats['total']['assists']) / $stats['total']['deaths'], 2)
        : 0;
    $stats['total']['csmin'] = $stats['duration'] > 0 ? round($stats['total']['cs'] / $stats['duration'], 2) : 0;

    $teamObjectives = ['dragons' => 0, 'barons' => 0, 'heralds' => 0];
    $totalObjectives = ['dragons' => 0, 'barons' => 0, 'heralds' => 0];

    foreach ($info['teams'] ?? [] as $teamEntry) {
        $dragonKills = $teamEntry['objectives']['dragon']['kills'] ?? 0;
        $baronKills = $teamEntry['objectives']['baron']['kills'] ?? 0;
        $heraldKills = $teamEntry['objectives']['riftHerald']['kills'] ?? 0;

        $totalObjectives['dragons'] += $dragonKills;
        $totalObjectives['barons'] += $baronKills;
        $totalObjectives['heralds'] += $heraldKills;

        if ($teamEntry['teamId'] === $teamId) {
            $teamObjectives = ['dragons' => $dragonKills, 'barons' => $baronKills, 'heralds' => $heraldKills];
        }
    }
    $stats['objectives'] = ['team' => $teamObjectives, 'total' => $totalObjectives];

    return $stats;
}

function api_primebot_get_team_matches(int $teamId, string $token): array
{
    $url = "https://primebot.me/api/v1/teams/$teamId/";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("Primebot API error: HTTP $httpCode");
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['matches'])) {
        return [];
    }

    $matches = [];
    foreach ($data['matches'] as $match) {
        $matches[] = [
            'id' => (int) $match['id'],
            'team1' => $data['name'] ?? 'Unknown',
            'team2' => $match['enemy_team']['name'] ?? 'Unknown',
            'result' => $match['result'] ?? '',
            'match_day' => (int) ($match['match_day'] ?? 0),
            'status' => $match['match_type'] ?? 'league',
            'begin' => $match['begin'] ?? null,
            'riot_match_ids' => [],
        ];
    }
    return $matches;
}

function can_sync_riot(int $cooldown): array
{
    $lastSync = db_get_last_riot_sync();
    $now = time();
    $diff = $now - $lastSync;

    if ($diff < $cooldown) {
        $remaining = $cooldown - $diff;
        return [false, 'Please wait ' . ceil($remaining / 60) . ' more minute(s).'];
    }
    return [true, ''];
}

$messages = [];
$primeMatches = db_get_prime_matches();

if (!$hasPrimeSyncConfig) {
    $messages[] = "<span class='warn'>Prime sync is disabled until valid DMA environment variables are configured.</span>";
}

if (!$hasRiotSyncConfig) {
    $messages[] = "<span class='warn'>Riot sync is disabled until valid DMA environment variables are configured.</span>";
}

if (isset($_GET['prime'])) {
    if (!$hasPrimeSyncConfig) {
        $messages[] = "<span class='warn'>Prime sync is not configured right now.</span>";
    } else {
        try {
            $primeMatches = api_primebot_get_team_matches($TEAM_ID, $TOKEN);
            db_save_prime_matches($primeMatches);
            $matches = db_get_matches();
            db_link_riot_to_prime($primeMatches, $matches, $MIN_MATCH_DURATION, $MATCH_TIME_TOLERANCE);
            $primeMatches = db_get_prime_matches();
            $messages[] = "<span class='ok'>Prime League data updated.</span>";
        } catch (Throwable $e) {
            $messages[] = "<span class='error'>" . htmlspecialchars($e->getMessage()) . "</span>";
        }
    }
}

if (isset($_GET['riot'])) {
    if (!$hasRiotSyncConfig) {
        $messages[] = "<span class='warn'>Riot sync is not configured right now.</span>";
    } else {
        [$canSync, $cooldownMessage] = can_sync_riot($RIOT_SYNC_COOLDOWN);
        if (!$canSync) {
            $messages[] = "<span class='warn'>$cooldownMessage</span>";
        } else {
            try {
                $puuid = api_riot_get_puuid($REGION, $GAME_NAME, $TAG_LINE, $API_KEY);
                $ids = api_riot_get_match_ids($REGION, $puuid, $API_KEY, $RIOT_MATCH_COUNT);
                $inserted = 0;
                $skipped = 0;
                foreach ($ids as $id) {
                    $match = api_riot_get_team_stats($REGION, $id, $puuid, $API_KEY);
                    if ($match['duration'] < $MIN_MATCH_DURATION) {
                        $skipped++;
                        continue;
                    }
                    db_save_match($match);
                    $inserted++;
                }
                db_set_last_riot_sync(time());
                $matches = db_get_matches();
                db_link_riot_to_prime($primeMatches, $matches, $MIN_MATCH_DURATION, $MATCH_TIME_TOLERANCE);
                $primeMatches = db_get_prime_matches();
                $message = "$inserted matches stored.";
                if ($skipped > 0) {
                    $message .= " ($skipped skipped)";
                }
                $messages[] = "<span class='ok'>$message</span>";
            } catch (Throwable $e) {
                $messages[] = "<span class='error'>" . htmlspecialchars($e->getMessage()) . "</span>";
            }
        }
    }
}

$activePage = 'dashboard';
$pageTitle = 'DMA Stat Sheet';
$pageDescription = 'Live team statistics surface for DMA with Prime League and Riot-based match views.';
require_once __DIR__ . '/inc/header.php';
?>

<?php if (!empty($messages)): ?>
<div class="messages">
  <?php foreach ($messages as $message): ?><p><?= $message ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<main class="container" id="main-content">
  <section class="context-panel">
    <div class="context-copy">
      <p class="context-eyebrow">Public Context</p>
      <h1>DMA is a live statistics surface for a Prime League roster.</h1>
      <p>
        This application aggregates match and role-based performance views across recorded games.
        It is both a usable stats tool and a platform case: a legacy PHP workload running on its
        own subdomain, in its own service container, with monitored health checks and isolated database scope.
      </p>
    </div>
    <div class="context-grid">
      <article>
        <p class="context-card-title">Who it is for</p>
        <p>Players, staff, and reviewers who need role-based match summaries instead of raw match history.</p>
      </article>
      <article>
        <p class="context-card-title">Data sources</p>
        <p>Prime League scheduling data and Riot match data when sync credentials are configured.</p>
      </article>
      <article>
        <p class="context-card-title">Key metrics</p>
        <p>KDA, kill participation, CS/min, vision score, and objective control by role and match.</p>
      </article>
      <article>
        <p class="context-card-title">Update model</p>
        <p>Live syncs remain manual by design, while champion-pool stat caches are refreshed on a schedule for faster reads.</p>
      </article>
      <article>
        <p class="context-card-title">Operational limit</p>
        <p>This is a focused team stats surface, not a public esports portal or a generalized data warehouse.</p>
      </article>
    </div>
  </section>

  <div class="sync-area">
    <form method="get" style="display:inline;">
      <button name="prime" value="1" class="action-btn" <?= $hasPrimeSyncConfig ? '' : 'disabled title="Prime sync is not configured"' ?>>Sync Prime data</button>
    </form>
    <form method="get" style="display:inline;">
      <button name="riot" value="1" class="action-btn" <?= $hasRiotSyncConfig ? '' : 'disabled title="Riot sync is not configured"' ?>>Sync Riot data</button>
    </form>
  </div>

  <?php if (!empty($primeMatches)): ?>
  <section class="collapsible-section">
    <button class="collapsible-toggle" onclick="this.parentElement.classList.toggle('open')">
      <span class="collapsible-title">Prime League matches</span>
      <span class="toggle-icon"></span>
    </button>
    <div class="collapsible-content">
      <div class="prime-match-list">
        <?php foreach ($primeMatches as $primeMatch): ?>
        <div class="prime-match-card">
          <span class="match-day">Matchday <?= safe((string) $primeMatch['match_day']) ?></span>
          <span class="opponent-name">vs <?= safe($primeMatch['team2']) ?></span>
          <span class="match-time"><?= safe(human_date($primeMatch['begin'], 'd.m.Y H:i')) ?></span>
          <span class="result-badge small"><?= safe($primeMatch['result']) ?: '--' ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php foreach ($primeMatches as $primeMatch):
    if (empty($primeMatch['riot_match_ids'])) {
        continue;
    }
    $matchDay = $primeMatch['match_day'];
    $opponent = $primeMatch['team2'];
    $result = $primeMatch['result'];
  ?>
  <section class="match-section">
    <div class="match-header">
      <p class="match-title">Matchday <?= $matchDay ?> - vs <?= safe($opponent) ?></p>
      <span class="result-badge"><?= safe($result) ?: '--' ?></span>
    </div>

    <?php
    $matchEntries = [];
    foreach ($primeMatch['riot_match_ids'] as $riotMatchId) {
        $matchData = db_get_match_by_id($riotMatchId);
        if (!$matchData) {
            continue;
        }
        $players = db_get_match_players($riotMatchId);
        $matchEntries[] = [
            'id' => $riotMatchId,
            'data' => $matchData,
            'players' => $players,
        ];
    }

    usort($matchEntries, static function ($a, $b) {
        $ta = (int) ($a['data']['timestamp'] ?? 0);
        $tb = (int) ($b['data']['timestamp'] ?? 0);
        if ($ta === $tb) {
            return strcmp((string) $a['id'], (string) $b['id']);
        }
        return $ta <=> $tb;
    });

    $gameNumber = 1;
    foreach ($matchEntries as $entry):
        $matchData = $entry['data'];
        $players = $entry['players'];
        $won = (bool) $matchData['won'];

        $roleOrder = ['Top', 'Jungle', 'Mid', 'ADC', 'Supp'];
        $playersByRole = [];
        foreach ($players as $player) {
            $playersByRole[$player['role']] = $player;
        }
    ?>
    <div class="game-block">
      <div class="game-header">
        <p class="game-title">Game <?= $gameNumber ?></p>
        <span class="game-result <?= $won ? 'win' : 'loss' ?>"><?= $won ? 'Win' : 'Loss' ?></span>
      </div>

      <div class="stats-table">
        <table>
          <thead>
            <tr>
              <th>Stat</th>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
              ?>
              <th><?= safe($role) ?></th>
              <?php endforeach; ?>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="stat-label">Champions</td>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
                $player = $playersByRole[$role];
              ?>
              <td class="champion-cell">
                <span class="champion-pill"><?= safe($player['champ']) ?></span>
              </td>
              <?php endforeach; ?>
              <td class="total-placeholder">--</td>
            </tr>
            <tr>
              <td class="stat-label">Kills</td>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
                $player = $playersByRole[$role];
              ?>
              <td><?= $player['kills'] ?></td>
              <?php endforeach; ?>
              <td class="total-cell"><?= $matchData['total_kills'] ?></td>
            </tr>
            <tr>
              <td class="stat-label">Deaths</td>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
                $player = $playersByRole[$role];
              ?>
              <td><?= $player['deaths'] ?></td>
              <?php endforeach; ?>
              <td class="total-cell"><?= $matchData['total_deaths'] ?></td>
            </tr>
            <tr>
              <td class="stat-label">Assists</td>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
                $player = $playersByRole[$role];
              ?>
              <td><?= $player['assists'] ?></td>
              <?php endforeach; ?>
              <td class="total-cell"><?= $matchData['total_assists'] ?></td>
            </tr>
            <tr>
              <td class="stat-label">KDA</td>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
                $player = $playersByRole[$role];
              ?>
              <td class="kda"><?= $player['kda'] ?></td>
              <?php endforeach; ?>
              <td>--</td>
            </tr>
            <tr>
              <td class="stat-label">KP%</td>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
                $player = $playersByRole[$role];
              ?>
              <td><?= $player['kp'] ?>%</td>
              <?php endforeach; ?>
              <td>--</td>
            </tr>
            <tr>
              <td class="stat-label">CS</td>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
                $player = $playersByRole[$role];
              ?>
              <td><?= $player['cs'] ?></td>
              <?php endforeach; ?>
              <td class="total-cell"><?= $matchData['total_cs'] ?></td>
            </tr>
            <tr>
              <td class="stat-label">CS/Min</td>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
                $player = $playersByRole[$role];
              ?>
              <td><?= $player['csmin'] ?></td>
              <?php endforeach; ?>
              <td>--</td>
            </tr>
            <tr>
              <td class="stat-label">Vision</td>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
                $player = $playersByRole[$role];
              ?>
              <td><?= $player['vision'] ?></td>
              <?php endforeach; ?>
              <td class="total-cell"><?= $matchData['total_vision'] ?></td>
            </tr>
            <tr>
              <td class="stat-label">Pinks</td>
              <?php foreach ($roleOrder as $role):
                if (!isset($playersByRole[$role])) {
                    continue;
                }
                $player = $playersByRole[$role];
              ?>
              <td><?= $player['pinks'] ?></td>
              <?php endforeach; ?>
              <td class="total-cell"><?= $matchData['total_pinks'] ?></td>
            </tr>
          </tbody>
        </table>

        <?php
        $teamDragons = $matchData['dragons'];
        $totalDragons = $matchData['total_dragons'];
        $dragonPercentage = $totalDragons > 0 ? round(($teamDragons / $totalDragons) * 100) : 0;

        $teamBarons = $matchData['barons'];
        $totalBarons = $matchData['total_barons'];
        $baronPercentage = $totalBarons > 0 ? round(($teamBarons / $totalBarons) * 100) : 0;

        $teamHeralds = $matchData['heralds'];
        $totalHeralds = $matchData['total_heralds'];
        $heraldPercentage = $totalHeralds > 0 ? round(($teamHeralds / $totalHeralds) * 100) : 0;
        ?>
        <div class="objectives">
          <div class="obj-item">
            <span>Dragons: <?= $teamDragons ?> / <?= $totalDragons ?> (<?= $dragonPercentage ?>%)</span>
          </div>
          <div class="obj-item">
            <span>Barons: <?= $teamBarons ?> / <?= $totalBarons ?> (<?= $baronPercentage ?>%)</span>
          </div>
          <div class="obj-item">
            <span>Heralds: <?= $teamHeralds ?> / <?= $totalHeralds ?> (<?= $heraldPercentage ?>%)</span>
          </div>
        </div>
      </div>
    </div>
    <?php
      $gameNumber++;
    endforeach;
    ?>
  </section>
  <?php endforeach; ?>
</main>

<?php require_once __DIR__ . '/inc/footer.php';
