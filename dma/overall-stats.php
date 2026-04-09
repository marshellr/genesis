<?php
declare(strict_types=1);

require_once __DIR__.'/inc/error-handler.php';
require_once __DIR__.'/inc/functions.php';

//ini_set('display_errors', '1'); // Deaktiviert für Produktion
//error_reporting(E_ALL);

require_once __DIR__.'/inc/db.php';



//------------------------------------------------
// Daten laden und verarbeiten
//------------------------------------------------

$primeMatches = db_get_prime_matches();
$linkedMatchIds = [];
foreach ($primeMatches as $pm) {
    if (!empty($pm['riot_match_ids'])) {
        $linkedMatchIds = array_merge($linkedMatchIds, $pm['riot_match_ids']);
    }
}

$linkedMatchIds = array_values(array_unique(array_filter(
    $linkedMatchIds,
    static fn($id) => is_string($id) && $id !== ''
)));

$allPlayers = [];
if (!empty($linkedMatchIds)) {
    $allPlayers = db_get_players_by_match_ids($linkedMatchIds);
}


$statsByRole = [
    'Top' => ['games' => 0, 'kda' => [], 'csmin' => [], 'vision' => [], 'kp' => [], 'champions' => []],
    'Jungle' => ['games' => 0, 'kda' => [], 'csmin' => [], 'vision' => [], 'kp' => [], 'champions' => []],
    'Mid' => ['games' => 0, 'kda' => [], 'csmin' => [], 'vision' => [], 'kp' => [], 'champions' => []],
    'ADC' => ['games' => 0, 'kda' => [], 'csmin' => [], 'vision' => [], 'kp' => [], 'champions' => []],
    'Supp' => ['games' => 0, 'kda' => [], 'csmin' => [], 'vision' => [], 'kp' => [], 'champions' => []],
];

$totalWins = 0;

if (!empty($linkedMatchIds)) {
    $linkedMatches = db_get_matches_by_ids($linkedMatchIds);
} else {
    $linkedMatches = [];
}

$totalGames = count($linkedMatches);

foreach ($linkedMatches as $match) {
    if ($match['won'] ?? false) {
        $totalWins++;
    }
}

$totalLosses = max($totalGames - $totalWins, 0);

foreach ($allPlayers as $player) {
    $role = $player['role'];
    if (isset($statsByRole[$role])) {
        $statsByRole[$role]['games']++;
        $statsByRole[$role]['kda'][] = (float)$player['kda'];
        $statsByRole[$role]['csmin'][] = (float)$player['csmin'];
        $statsByRole[$role]['vision'][] = (int)$player['vision'];
        $statsByRole[$role]['kp'][] = (float)$player['kp'];
        
        $champName = $player['champ'];
        if (!isset($statsByRole[$role]['champions'][$champName])) {
            $statsByRole[$role]['champions'][$champName] = 0;
        }
        $statsByRole[$role]['champions'][$champName]++;
    }
}

// Berechne Durchschnitte und Top-Champions
foreach ($statsByRole as $role => &$data) {
    if ($data['games'] > 0) {
        $data['avg_kda'] = round(array_sum($data['kda']) / count($data['kda']), 2);
        $data['avg_csmin'] = round(array_sum($data['csmin']) / count($data['csmin']), 2);
        $data['avg_vision'] = round(array_sum($data['vision']) / count($data['vision']), 1);
        $data['avg_kp'] = round(array_sum($data['kp']) / count($data['kp']), 1);
        arsort($data['champions']); // Sortiere Champions nach Spielanzahl
    }
}
unset($data);

$overallWinrate = ($totalGames > 0) ? round(($totalWins / $totalGames) * 100, 2) : 0;

$activePage = 'overall-stats';
$pageTitle = 'Gesamtstatistiken';
require_once __DIR__.'/inc/header.php';
?>

<main class="container" id="main-content">
  <div class="overall-winrate">
    <h2>Gesamte Winrate (<?=safe((string)$totalGames)?> Spiele)</h2>
    <span class="stat-value"><?=safe((string)$overallWinrate)?>%</span>
    <div class="winrate-meta">
      <span class="meta-pill">Siege: <?=safe((string)$totalWins)?></span>
      <span class="meta-pill">Niederlagen: <?=safe((string)$totalLosses)?></span>
    </div>
  </div>

  <div class="stats-grid">
    <?php foreach ($statsByRole as $role => $data): ?>
    <div class="stat-card">
      <div class="stat-card-header">
        <h2><?=safe($role)?></h2>
        <span class="game-count-pill"><?=safe((string)$data['games'])?> Spiele</span>
      </div>
      <div class="stat-card-body">
        <?php if ($data['games'] > 0): ?>
        <div class="stat-row">
          <span class="stat-label">Avg. KDA</span>
          <span class="stat-value"><?=safe(isset($data['avg_kda']) ? (string)$data['avg_kda'] : 'N/A')?></span>
        </div>
        <div class="stat-row">
          <span class="stat-label">Avg. KP%</span>
          <span class="stat-value"><?=safe(isset($data['avg_kp']) ? (string)$data['avg_kp'].'%' : 'N/A')?></span>
        </div>
        <div class="stat-row">
          <span class="stat-label">Avg. CS/Min</span>
          <span class="stat-value"><?=safe(isset($data['avg_csmin']) ? (string)$data['avg_csmin'] : 'N/A')?></span>
        </div>
        <div class="stat-row">
          <span class="stat-label">Avg. Vision Score</span>
          <span class="stat-value"><?=safe(isset($data['avg_vision']) ? (string)$data['avg_vision'] : 'N/A')?></span>
        </div>
        <div class="stat-row top-champ-row">
          <span class="stat-label">Top Champions</span>
          <div class="stat-value chip-list">
            <?php 
              $topChamps = array_slice($data['champions'], 0, 3, true);
              if (empty($topChamps)): ?>
                <span class="chip chip-empty">N/A</span>
            <?php else: 
                foreach (array_keys($topChamps) as $champName): ?>
                <span class="chip"><?=safe($champName)?></span>
            <?php 
                endforeach;
              endif;
            ?>
          </div>
        </div>
        <?php else: ?>
          <div class="no-data-placeholder">Keine Spieldaten für diese Rolle vorhanden.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>
<?php require_once __DIR__.'/inc/footer.php';
