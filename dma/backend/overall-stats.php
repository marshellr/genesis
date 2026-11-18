<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/error-handler.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/db.php';

$primeMatches = db_get_prime_matches();
$linkedMatchIds = [];
foreach ($primeMatches as $primeMatch) {
    if (!empty($primeMatch['riot_match_ids'])) {
        $linkedMatchIds = array_merge($linkedMatchIds, $primeMatch['riot_match_ids']);
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
$linkedMatches = !empty($linkedMatchIds) ? db_get_matches_by_ids($linkedMatchIds) : [];
$totalGames = count($linkedMatches);

foreach ($linkedMatches as $match) {
    if ($match['won'] ?? false) {
        $totalWins++;
    }
}

$totalLosses = max($totalGames - $totalWins, 0);

foreach ($allPlayers as $player) {
    $role = $player['role'];
    if (!isset($statsByRole[$role])) {
        continue;
    }

    $statsByRole[$role]['games']++;
    $statsByRole[$role]['kda'][] = (float) $player['kda'];
    $statsByRole[$role]['csmin'][] = (float) $player['csmin'];
    $statsByRole[$role]['vision'][] = (int) $player['vision'];
    $statsByRole[$role]['kp'][] = (float) $player['kp'];

    $championName = $player['champ'];
    if (!isset($statsByRole[$role]['champions'][$championName])) {
        $statsByRole[$role]['champions'][$championName] = 0;
    }
    $statsByRole[$role]['champions'][$championName]++;
}

foreach ($statsByRole as &$data) {
    if ($data['games'] <= 0) {
        continue;
    }

    $data['avg_kda'] = round(array_sum($data['kda']) / count($data['kda']), 2);
    $data['avg_csmin'] = round(array_sum($data['csmin']) / count($data['csmin']), 2);
    $data['avg_vision'] = round(array_sum($data['vision']) / count($data['vision']), 1);
    $data['avg_kp'] = round(array_sum($data['kp']) / count($data['kp']), 1);
    arsort($data['champions']);
}
unset($data);

$overallWinrate = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 2) : 0;

$activePage = 'overall-stats';
$pageTitle = 'All-Time Role Stats';
$pageDescription = 'Role-based all-time winrate, KDA, farming, vision, and champion usage across recorded DMA matches.';
require_once __DIR__ . '/inc/header.php';
?>

<main class="container" id="main-content">
  <div class="page-intro">
    <p class="context-eyebrow">All-Time Overview</p>
    <h1>Role-based match trends across recorded DMA games.</h1>
    <p>
      This view summarizes all recorded DMA matches by role. It is meant to give a fast
      operator-friendly overview of winrate, champion usage, and recurring performance patterns.
    </p>
  </div>

  <div class="overall-winrate">
    <h2>Overall winrate (<?= safe((string) $totalGames) ?> matches)</h2>
    <span class="stat-value"><?= safe((string) $overallWinrate) ?>%</span>
    <div class="winrate-meta">
      <span class="meta-pill">Wins: <?= safe((string) $totalWins) ?></span>
      <span class="meta-pill">Losses: <?= safe((string) $totalLosses) ?></span>
    </div>
  </div>

  <div class="stats-grid">
    <?php foreach ($statsByRole as $role => $data): ?>
    <div class="stat-card">
      <div class="stat-card-header">
        <h2><?= safe($role) ?></h2>
        <span class="game-count-pill"><?= safe((string) $data['games']) ?> matches</span>
      </div>
      <div class="stat-card-body">
        <?php if ($data['games'] > 0): ?>
        <div class="stat-row">
          <span class="stat-label">Avg. KDA</span>
          <span class="stat-value"><?= safe(isset($data['avg_kda']) ? (string) $data['avg_kda'] : 'N/A') ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-label">Avg. KP%</span>
          <span class="stat-value"><?= safe(isset($data['avg_kp']) ? (string) $data['avg_kp'] . '%' : 'N/A') ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-label">Avg. CS/Min</span>
          <span class="stat-value"><?= safe(isset($data['avg_csmin']) ? (string) $data['avg_csmin'] : 'N/A') ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-label">Avg. Vision Score</span>
          <span class="stat-value"><?= safe(isset($data['avg_vision']) ? (string) $data['avg_vision'] : 'N/A') ?></span>
        </div>
        <div class="stat-row top-champ-row">
          <span class="stat-label">Top Champions</span>
          <div class="stat-value chip-list">
            <?php
            $topChampions = array_slice($data['champions'], 0, 3, true);
            if (empty($topChampions)):
            ?>
              <span class="chip chip-empty">N/A</span>
            <?php else: ?>
              <?php foreach (array_keys($topChampions) as $championName): ?>
              <span class="chip"><?= safe($championName) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="no-data-placeholder">No recorded matches are available for this role yet.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>

<?php require_once __DIR__ . '/inc/footer.php';
