<?php
declare(strict_types=1);

require_once __DIR__.'/inc/error-handler.php';
require_once __DIR__.'/inc/functions.php';
require_once __DIR__.'/inc/db.php';

$API_KEY    = (string)config('RIOT_API_KEY', '');
$GAME_NAME  = (string)config('GAME_NAME', '');
$TAG_LINE   = (string)config('TAG_LINE', '');
$REGION     = (string)config('REGION_ROUTING', 'europe');
$TEAM_ID    = (int)config('PRIMEBOT_TEAM_ID', 0);
$TOKEN      = (string)config('PRIMEBOT_TOKEN', '');

$MATCH_TIME_TOLERANCE = (int)config('MATCH_TIME_TOLERANCE', 10800); // 3 Stunden
$MIN_MATCH_DURATION   = (int)config('MIN_MATCH_DURATION', 15);       // Minuten
$RIOT_SYNC_COOLDOWN   = (int)config('RIOT_SYNC_COOLDOWN', 300);
$RIOT_MATCH_COUNT     = (int)config('RIOT_MATCH_COUNT', 10);
$hasRiotSyncConfig    = $API_KEY !== '' && $GAME_NAME !== '' && $TAG_LINE !== '';
$hasPrimeSyncConfig   = $TEAM_ID > 0 && $TOKEN !== '';

// [Alle API-Funktionen bleiben gleich - hier ausgelassen für Kürze]
function api_riot_get_json(string $url, string $apiKey): array {
    // Der API-Key wird korrekt über den X-Riot-Token Header gesendet.
    // Das Hinzufügen als URL-Parameter ist hier redundant und wird nicht verwendet.
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FAILONERROR => true, // Fails on HTTP status >= 400
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Riot-Token: ' . $apiKey, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($resp === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL Error: $curlError (HTTP $status)");
    }

    if ($status < 200 || $status >= 300) {
        curl_close($ch);
        throw new RuntimeException("HTTP $status");
    }

    curl_close($ch);

    return is_array($data = json_decode($resp, true)) ? $data : [];
}

function api_riot_get_puuid(string $region, string $name, string $tag, string $key): string {
    $url = "https://$region.api.riotgames.com/riot/account/v1/accounts/by-riot-id/" .
           rawurlencode($name) . '/' . rawurlencode($tag);
    $res = api_riot_get_json($url, $key);
    return $res['puuid'] ?? throw new RuntimeException('PUUID nicht gefunden');
}

function api_riot_get_match_ids(string $region, string $puuid, string $key, int $count = 5): array {
    $url = "https://$region.api.riotgames.com/lol/match/v5/matches/by-puuid/$puuid/ids?type=tourney&start=0&count=$count";
    return api_riot_get_json($url, $key);
}

function api_riot_get_team_stats(string $region, string $matchId, string $puuid, string $key): array {
    $url = "https://{$region}.api.riotgames.com/lol/match/v5/matches/$matchId";
    $match = api_riot_get_json($url, $key);
    $info = $match['info'] ?? [];
    $players = $info['participants'] ?? [];
    $self = null;
    foreach ($players as $p) if ($p['puuid']===$puuid) $self=$p;
    if (!$self) throw new RuntimeException("Eigener Spieler nicht gefunden");

    $teamId = $self['teamId'];
    $team = array_filter($players, fn($p)=>$p['teamId']===$teamId);
    $roleMap = ['TOP'=>'Top','JUNGLE'=>'Jungle','MIDDLE'=>'Mid','BOTTOM'=>'ADC','UTILITY'=>'Supp'];

    $stats=[
        'matchId'=>$matchId,
        'timestamp'=>$info['gameStartTimestamp']??0,
        'duration'=>round(($info['gameDuration']??0)/60,2),
        'won'=>false,
        'roles'=>[],
        'total'=>['kills'=>0,'deaths'=>0,'assists'=>0,'cs'=>0,'vision'=>0,'pinks'=>0],
    ];

    foreach($info['teams']??[] as $t) {
        if($t['teamId']===$teamId) {
            $stats['won'] = $t['win'] ?? false;
        }
    }

    foreach($team as $p){
        $r=$roleMap[$p['teamPosition']]??'UNK';
        $k=(int)$p['kills']; $d=(int)$p['deaths']; $a=(int)$p['assists'];
        $cs=(int)$p['totalMinionsKilled']+(int)$p['neutralMinionsKilled'];
        $vs=(int)$p['visionScore']; $pk=(int)($p['visionWardsBoughtInGame']??0);
        $stats['roles'][$r]=['champ'=>$p['championName'],'kills'=>$k,'deaths'=>$d,'assists'=>$a,
            'cs'=>$cs,'vision'=>$vs,'pinks'=>$pk];
        $stats['total']['kills']+=$k; $stats['total']['deaths']+=$d;
        $stats['total']['assists']+=$a; $stats['total']['cs']+=$cs;
        $stats['total']['vision']+=$vs; $stats['total']['pinks']+=$pk;
    }

    foreach($stats['roles'] as &$x){
        $x['kda']=$x['deaths']>0?round(($x['kills']+$x['assists'])/$x['deaths'],2):$x['kills']+$x['assists'];
        $x['kp']=$stats['total']['kills']>0?round((($x['kills']+$x['assists'])/$stats['total']['kills'])*100,2):0;
        $x['csmin']=$stats['duration']>0?round($x['cs']/$stats['duration'],2):0;
    } unset($x);

    $stats['total']['kda']=$stats['total']['deaths']>0?round(($stats['total']['kills']+$stats['total']['assists'])/$stats['total']['deaths'],2):0;
    $stats['total']['csmin']=$stats['duration']>0?round($stats['total']['cs']/$stats['duration'],2):0;

    $teamObjectives = ['dragons' => 0, 'barons' => 0, 'heralds' => 0];
    $totalObjectives = ['dragons' => 0, 'barons' => 0, 'heralds' => 0];

    foreach($info['teams'] ?? [] as $t) {
        $dragonKills = $t['objectives']['dragon']['kills'] ?? 0;
        $baronKills = $t['objectives']['baron']['kills'] ?? 0;
        $heraldKills = $t['objectives']['riftHerald']['kills'] ?? 0;

        $totalObjectives['dragons'] += $dragonKills;
        $totalObjectives['barons'] += $baronKills;
        $totalObjectives['heralds'] += $heraldKills;

        if ($t['teamId'] === $teamId) {
            $teamObjectives = ['dragons' => $dragonKills, 'barons' => $baronKills, 'heralds' => $heraldKills];
        }
    }
    $stats['objectives'] = ['team' => $teamObjectives, 'total' => $totalObjectives];
    
    return $stats;
}

function api_primebot_get_team_matches(int $teamId, string $token): array {
    $url="https://primebot.me/api/v1/teams/$teamId/";
    $ch=curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>20,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token]
    ]);
    $resp=curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) throw new RuntimeException("Primebot API Error: HTTP $httpCode");
    
    $data=json_decode($resp,true);
    if(!is_array($data)||empty($data['matches'])) return [];
    
    $matches=[];
    foreach($data['matches'] as $m){
        $matches[]=[
            'id'=>(int)$m['id'],
            'team1'=>$data['name']??'Unknown',
            'team2'=>$m['enemy_team']['name']??'Unknown',
            'result'=>$m['result']??'',
            'match_day'=>(int)($m['match_day']??0),
            'status'=>$m['match_type']??'league',
            'begin'=>$m['begin']??null,
            'riot_match_ids'=>[]
        ];
    }
    return $matches;
}

function can_sync_riot(int $cooldown): array {
    $lastSync = db_get_last_riot_sync();
    $now = time();
    $diff = $now - $lastSync;
    
    if ($diff < $cooldown) {
        $remaining = $cooldown - $diff;
        return [false, "Bitte warte noch " . ceil($remaining / 60) . " Minute(n)"];
    }
    return [true, ""];
}

//------------------------------------------------
// Logiksteuerung
//------------------------------------------------
$messages=[];
$primeMatches=db_get_prime_matches();

if (!$hasPrimeSyncConfig) {
    $messages[]="<span class='warn'>Prime Sync ist deaktiviert, bis gueltige DMA-Umgebungsvariablen gesetzt sind.</span>";
}

if (!$hasRiotSyncConfig) {
    $messages[]="<span class='warn'>Riot Sync ist deaktiviert, bis gueltige DMA-Umgebungsvariablen gesetzt sind.</span>";
}

if(isset($_GET['prime'])){
    if (!$hasPrimeSyncConfig) {
        $messages[]="<span class='warn'>Prime Sync ist aktuell nicht konfiguriert.</span>";
    } else {
        try{
            $primeMatches=api_primebot_get_team_matches($TEAM_ID,$TOKEN);
            db_save_prime_matches($primeMatches);
            $matches=db_get_matches();
            db_link_riot_to_prime($primeMatches, $matches, $MIN_MATCH_DURATION, $MATCH_TIME_TOLERANCE);
            $primeMatches=db_get_prime_matches();
            $messages[]="<span class='ok'>✓ Prime-League-Daten aktualisiert.</span>";
        }catch(Throwable $e){
            $messages[]="<span class='error'>✗ ".htmlspecialchars($e->getMessage())."</span>";
        }
    }
}

if(isset($_GET['riot'])){
    if (!$hasRiotSyncConfig) {
        $messages[]="<span class='warn'>Riot Sync ist aktuell nicht konfiguriert.</span>";
    } else {
        list($canSync, $cooldownMsg) = can_sync_riot($RIOT_SYNC_COOLDOWN);
        if (!$canSync) {
            $messages[]="<span class='warn'>⏱ $cooldownMsg</span>";
        } else {
            try{
                $puuid=api_riot_get_puuid($REGION,$GAME_NAME,$TAG_LINE,$API_KEY);
                $ids=api_riot_get_match_ids($REGION,$puuid,$API_KEY,$RIOT_MATCH_COUNT);
                $inserted=0; $skipped=0;
                foreach($ids as $id){
                    $m=api_riot_get_team_stats($REGION,$id,$puuid,$API_KEY);
                    if($m['duration'] < $MIN_MATCH_DURATION) { $skipped++; continue; }
                    db_save_match($m);
                    $inserted++;
                }
                db_set_last_riot_sync(time());
                $matches=db_get_matches();
                db_link_riot_to_prime($primeMatches, $matches, $MIN_MATCH_DURATION, $MATCH_TIME_TOLERANCE);
                $primeMatches=db_get_prime_matches();
                $msg = "✓ $inserted Matches gespeichert.";
                if ($skipped > 0) $msg .= " ($skipped übersprungen)";
                $messages[]="<span class='ok'>$msg</span>";
            }catch(Throwable $e){
                $messages[]="<span class='error'>✗ ".htmlspecialchars($e->getMessage())."</span>";
            }
        }
    }
}

function safe($s){return htmlspecialchars((string)($s??''),ENT_QUOTES,'UTF-8');}

$activePage = 'dashboard';
$pageTitle = 'DMA Stat Sheet';
require_once __DIR__.'/inc/header.php';
?>

<?php if(!empty($messages)): ?>
<div class="messages">
  <?php foreach($messages as $msg): ?><p><?=$msg?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<main class="container" id="main-content">
  <section class="context-panel">
    <div class="context-copy">
      <p class="context-eyebrow">Public Context</p>
      <h1>DMA is a live statistics surface for a Prime League team environment.</h1>
      <p>
        This application aggregates match and role-based performance views across recorded games.
        It is both a usable stats tool and a platform case: a legacy PHP workload isolated behind its
        own subdomain, runtime boundary, health model, and database scope.
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
        <button name="prime" value="1" class="action-btn" <?= $hasPrimeSyncConfig ? '' : 'disabled title="Prime Sync ist nicht konfiguriert"' ?>>🔄 Prime Sync</button>
      </form>
      <form method="get" style="display:inline;">
        <button name="riot" value="1" class="action-btn" <?= $hasRiotSyncConfig ? '' : 'disabled title="Riot Sync ist nicht konfiguriert"' ?>>🎮 Riot Sync</button>
      </form>
  </div>

  <?php if (!empty($primeMatches)): ?>
  <section class="collapsible-section">
    <button class="collapsible-toggle" onclick="this.parentElement.classList.toggle('open')">
      <span class="collapsible-title">Prime League Spiele</span>
      <span class="toggle-icon"></span>
    </button>
    <div class="collapsible-content">
      <div class="prime-match-list">
        <?php foreach($primeMatches as $pm): ?>
        <div class="prime-match-card">
          <span class="match-day">Spieltag <?=safe($pm['match_day'])?></span>
          <span class="opponent-name">vs <?=safe($pm['team2'])?></span>
          <span class="match-time"><?=safe(human_date($pm['begin'], 'd.m.Y H:i'))?></span>
          <span class="result-badge small"><?=safe($pm['result'])?:'—'?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

<?php foreach($primeMatches as $pm): 
  if(empty($pm['riot_match_ids'])) continue;
  $matchDay = $pm['match_day'];
  $opponent = $pm['team2'];
  $result = $pm['result'];
?>

<section class="match-section">
  <div class="match-header">
    <p class="match-title">Spieltag <?=$matchDay?> - vs <?=safe($opponent)?></p>
    <span class="result-badge"><?=safe($result)?:'—'?></span>
  </div>

  <?php 
  $matchEntries = [];
  foreach ($pm['riot_match_ids'] as $rid) {
    $matchData = db_get_match_by_id($rid);
    if (!$matchData) {
      continue;
    }
    $players = db_get_match_players($rid);
    $matchEntries[] = [
      'id' => $rid,
      'data' => $matchData,
      'players' => $players,
    ];
  }

  usort($matchEntries, static function ($a, $b) {
    $ta = (int)($a['data']['timestamp'] ?? 0);
    $tb = (int)($b['data']['timestamp'] ?? 0);
    if ($ta === $tb) {
      return strcmp((string)$a['id'], (string)$b['id']);
    }
    return $ta <=> $tb;
  });

  $gameNum = 1;
  foreach($matchEntries as $entry):
    $matchData = $entry['data'];
    $players = $entry['players'];
    $rid = $entry['id'];
    $won = (bool)$matchData['won'];
    
    // Gruppiere Spieler nach Rolle
    $roleOrder = ['Top','Jungle','Mid','ADC','Supp'];
    $playersByRole = [];
    foreach($players as $p) {
      $playersByRole[$p['role']] = $p;
    }
  ?>
  
  <div class="game-block">
    <div class="game-header">
      <p class="game-title">Game <?=$gameNum?></p>
      <span class="game-result <?=$won?'win':'loss'?>"><?=$won?'Win':'Loss'?></span>
    </div>

    <div class="stats-table">
      <table>
        <thead>
          <tr>
            <th>Stat</th>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
            ?>
            <th><?=safe($role)?></th>
            <?php endforeach; ?>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="stat-label">Champions</td>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
              $p = $playersByRole[$role];
            ?>
            <td class="champion-cell">
              <span class="champion-pill"><?=safe($p['champ'])?></span>
            </td>
            <?php endforeach; ?>
            <td class="total-placeholder">—</td>
          </tr>
          <tr>
            <td class="stat-label">Kills</td>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
              $p = $playersByRole[$role];
            ?>
            <td><?=$p['kills']?></td>
            <?php endforeach; ?>
            <td class="total-cell"><?=$matchData['total_kills']?></td>
          </tr>
          
          <tr>
            <td class="stat-label">Deaths</td>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
              $p = $playersByRole[$role];
            ?>
            <td><?=$p['deaths']?></td>
            <?php endforeach; ?>
            <td class="total-cell"><?=$matchData['total_deaths']?></td>
          </tr>
          
          <tr>
            <td class="stat-label">Assists</td>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
              $p = $playersByRole[$role];
            ?>
            <td><?=$p['assists']?></td>
            <?php endforeach; ?>
            <td class="total-cell"><?=$matchData['total_assists']?></td>
          </tr>
          
          <tr>
            <td class="stat-label">KDA</td>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
              $p = $playersByRole[$role];
            ?>
            <td class="kda"><?=$p['kda']?></td>
            <?php endforeach; ?>
            <td>—</td>
          </tr>
          
          <tr>
            <td class="stat-label">KP%</td>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
              $p = $playersByRole[$role];
            ?>
            <td><?=$p['kp']?>%</td>
            <?php endforeach; ?>
            <td>—</td>
          </tr>
          
          <tr>
            <td class="stat-label">CS</td>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
              $p = $playersByRole[$role];
            ?>
            <td><?=$p['cs']?></td>
            <?php endforeach; ?>
            <td class="total-cell"><?=$matchData['total_cs']?></td>
          </tr>
          
          <tr>
            <td class="stat-label">CS/Min</td>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
              $p = $playersByRole[$role];
            ?>
            <td><?=$p['csmin']?></td>
            <?php endforeach; ?>
            <td>—</td>
          </tr>
          
          <tr>
            <td class="stat-label">Vision</td>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
              $p = $playersByRole[$role];
            ?>
            <td><?=$p['vision']?></td>
            <?php endforeach; ?>
            <td class="total-cell"><?=$matchData['total_vision']?></td>
          </tr>
          
          <tr>
            <td class="stat-label">Pinks</td>
            <?php foreach($roleOrder as $role): 
              if(!isset($playersByRole[$role])) continue;
              $p = $playersByRole[$role];
            ?>
            <td><?=$p['pinks']?></td>
            <?php endforeach; ?>
            <td class="total-cell"><?=$matchData['total_pinks']?></td>
          </tr>
        </tbody>
      </table>

      <?php
        $team_dragons = $matchData['dragons'];
        $total_dragons = $matchData['total_dragons'];
        $dragon_perc = $total_dragons > 0 ? round(($team_dragons / $total_dragons) * 100) : 0;

        $team_barons = $matchData['barons'];
        $total_barons = $matchData['total_barons'];
        $baron_perc = $total_barons > 0 ? round(($team_barons / $total_barons) * 100) : 0;

        $team_heralds = $matchData['heralds'];
        $total_heralds = $matchData['total_heralds'];
        $herald_perc = $total_heralds > 0 ? round(($team_heralds / $total_heralds) * 100) : 0;
      ?>
       <div class="objectives">
         <div class="obj-item">
           <span>Dragons: <?=$team_dragons?> / <?=$total_dragons?> (<?=$dragon_perc?>%)</span>
         </div>
         <div class="obj-item">
           <span>Barons: <?=$team_barons?> / <?=$total_barons?> (<?=$baron_perc?>%)</span>
         </div>
         <div class="obj-item">
           <span>Heralds: <?=$team_heralds?> / <?=$total_heralds?> (<?=$herald_perc?>%)</span>
         </div>
       </div>
    </div>
  </div>

  <?php 
    $gameNum++;
  endforeach; ?>
</section>

<?php endforeach; ?>
</main>
