<?php
declare(strict_types=1);

//ini_set('display_errors', '1'); // Deaktiviert für Produktion
//error_reporting(E_ALL);

require_once __DIR__.'/inc/error-handler.php';
require_once __DIR__.'/inc/functions.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$sessionCookiePath = getenv('DMA_COOKIE_PATH') ?: '/dma';

session_name('DMASESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $sessionCookiePath,
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__.'/inc/db.php';
require_once __DIR__.'/inc/riot-api.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Elo-Auswahl
$selectedElo = $_GET['elo'] ?? 'emerald_plus';
$validElos = ['emerald_plus', 'diamond_plus', 'master_plus'];
if (!in_array($selectedElo, $validElos)) {
    $selectedElo = 'emerald_plus';
}

//------------------------------------------------
// Champions synchronisieren (einmalig beim ersten Aufruf)
//------------------------------------------------
$championsInDb = db_get_all_champions();
if (empty($championsInDb)) {
    $riotChampions = riot_get_all_champions();
    if (!empty($riotChampions)) {
        db_sync_champions($riotChampions);
        $championsInDb = db_get_all_champions();
    }
}

//------------------------------------------------
// AJAX Handler
//------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // CSRF Token Validierung
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_champion') {
        $role = $_POST['role'] ?? '';
        $championId = (int)($_POST['champion_id'] ?? 0);
        
        if (!in_array($role, ['Top', 'Jungle', 'Mid', 'ADC', 'Supp'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid role'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($championId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Champion required'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        try {
            $newId = db_add_champion_to_pool($championId, $role);
            echo json_encode(['success' => true, 'new_id' => $newId], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    if ($action === 'delete_champion') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            db_delete_champion_pool($id);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    if ($action === 'sync_champions') {
        try {
            $riotChampions = riot_get_all_champions();
            db_sync_champions($riotChampions);
            echo json_encode(['success' => true, 'count' => count($riotChampions)], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}

//------------------------------------------------
// Daten laden
//------------------------------------------------
$championPools = db_get_champion_pools();
$championStats = metasrc_get_champion_stats($selectedElo);
if (empty($championStats) && !empty($championPools)) {
    error_log("Champion Pool: MetaSrc returned no stats, switching to LeagueOfGraphs fallback.");
    $championStats = leagueofgraphs_get_champion_stats_for_pools($championPools, $selectedElo);
}
$allChampions = db_get_all_champions();

// Gruppiere nach Role
$poolsByRole = [
    'Top' => [],
    'Jungle' => [],
    'Mid' => [],
    'ADC' => [],
    'Supp' => []
];

foreach ($championPools as $entry) {
    $role = $entry['role'];
    if (isset($poolsByRole[$role])) {
        $poolsByRole[$role][] = $entry;
    }
}

$bodyAttributes = 'data-csrf-token="'.htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8').'"';

function get_winrate_color(float $wr): string {
    if ($wr >= 52) return '#22c55e';
    if ($wr >= 50) return '#f59e0b';
    return '#ef4444';
}

function get_tier_color(string $tier): string {
    $normalized = strtoupper(rtrim($tier, '+'));
    return match($normalized) {
        'S' => '#a855f7',
        'A' => '#3b82f6',
        'B' => '#22c55e',
        'C' => '#f59e0b',
        'D' => '#ef4444',
        default => '#64748b'
    };
}

$eloLabels = [
    'emerald_plus' => 'Emerald+',
    'diamond_plus' => 'Diamond+',
    'master_plus' => 'Master+'
];

$activePage = 'champion-pool';
$pageTitle = 'Champion Pool Manager';
require_once __DIR__.'/inc/header.php';
?>
<div class="page-controls-bar">
    <div class="controls-container">
        <label class="filter-label">
            <span>Elo:</span>
            <select id="eloSelect" onchange="changeElo(this.value)">
            <?php foreach($eloLabels as $value => $label): ?>
                <option value="<?=$value?>" <?=$selectedElo===$value?'selected':''?>><?=$label?></option>
            <?php endforeach; ?>
            </select>
        </label>
        <label class="filter-label">
            <span>Sortierung:</span>
            <select id="sortBy">
            <option value="order">Eingabe-Reihenfolge</option>
            <option value="winrate">Winrate</option>
            <option value="tier">Tier</option>
            </select>
        </label>
    </div>
</div>

<main class="container" id="main-content">
  <div class="pool-grid">
    <?php foreach(['Top', 'Jungle', 'Mid', 'ADC', 'Supp'] as $role): ?>
    <div class="role-column" data-role="<?=$role?>">
      <div class="role-header">
        <h2><?=$role?></h2>
        <button class="add-btn" onclick="openAddModal('<?=$role?>')">+</button>
      </div>
      
      <div class="champion-list" data-role="<?=$role?>">
        <?php 
        $champions = $poolsByRole[$role];
        if (empty($champions)): ?>
        <div class="empty-role">Noch keine Champions gespeichert.</div>
        <?php else:
        foreach ($champions as $entry):
          $champId = (int)$entry['champion_id'];
          $allRoleStatsForChamp = $championStats[$champId] ?? null;
          $stats = null;

          if ($allRoleStatsForChamp) {
              // 1. Versuche, die exakte Rolle zu finden
              if (isset($allRoleStatsForChamp[$role])) {
                  $stats = $allRoleStatsForChamp[$role];
              } elseif (!empty($allRoleStatsForChamp)) {
                  // 2. Fallback: Finde die Rolle mit dem höchsten "role_percent" oder nimm die erste verfügbare.
                  $mainRoleStats = null;
                  foreach ($allRoleStatsForChamp as $rData) {
                      if (!$mainRoleStats || ($rData['role_percent'] ?? 0) > ($mainRoleStats['role_percent'] ?? 0)) {
                          $mainRoleStats = $rData;
                      }
                  }
                  $stats = $mainRoleStats;
              }
          }
          $winrate = $stats['winrate'] ?? null; // Use the selected stats
          $tier = $stats['tier'] ?? '?'; // Use the selected stats
        ?>
        <div class="champion-card" data-id="<?=$entry['id']?>" data-winrate="<?=$winrate?>" data-tier="<?=$tier?>">
          <div class="champion-info">
            <img src="<?=safe($entry['icon_url'])?>" alt="<?=safe($entry['name'])?>" class="champ-icon">
            <span class="champion-name"><?=safe($entry['name'])?></span>
            <button class="delete-btn" onclick="deleteChampion(<?=$entry['id']?>)">×</button>
          </div>
          <div class="champion-stats">
            <?php if ($winrate !== null): ?>
              <span class="stat-item">
                <span class="stat-label">WR:</span>
                <span class="stat-value" style="color: <?=get_winrate_color($winrate)?>"><?=$winrate?>%</span>
              </span>
              <span class="stat-item">
                <span class="stat-label">Tier:</span>
                <span class="tier-badge" style="background: <?=get_tier_color($tier)?>"><?=$tier?></span>
              </span>
            <?php else: ?>
              <span class="stat-item no-data">Keine Daten</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>

<!-- Add Champion Modal -->
<div id="addModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Champion hinzufügen</h3>
      <button class="close-btn" onclick="closeAddModal()">×</button>
    </div>
    <form id="addForm" onsubmit="addChampion(event)">
      <input type="hidden" id="modalRole" name="role">
      <input type="hidden" name="csrf_token" value="<?= safe($_SESSION['csrf_token']) ?>">
      <div class="form-group">
        <label for="championSearch">Champion suchen:</label>
        <div class="autocomplete-wrapper">
          <input type="text" id="championSearch" autocomplete="off" 
                 placeholder="Tippe um zu suchen..." oninput="filterChampions(this.value)">
          <div id="championDropdown" class="champion-dropdown"></div>
        </div>
        <input type="hidden" id="championId" name="champion_id">
      </div>
      <button type="submit" class="submit-btn" id="submitBtn" disabled>Hinzufügen</button>
    </form>
  </div>
</div>

<script>
const allChampions = <?=json_encode($allChampions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
const championStats = <?=json_encode($championStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?> || {};
const csrfToken = <?=json_encode($csrfToken, JSON_UNESCAPED_UNICODE)?>;
let selectedChampionId = null;

const tierColorMap = {'S':'#a855f7','A':'#3b82f6','B':'#22c55e','C':'#f59e0b','D':'#ef4444'};

function winrateColor(value) {
  if (value >= 52) return '#22c55e';
  if (value >= 50) return '#f59e0b';
  return '#ef4444';
}

function tierColor(value) {
  if (!value) return '#64748b';
  const normalized = String(value).trim().toUpperCase().replace(/\+$/, '');
  return tierColorMap[normalized] ?? '#64748b';
}

function calculateTier(value) {
  if (value >= 53) return 'S';
  if (value >= 51.5) return 'A';
  if (value >= 50) return 'B';
  if (value >= 48) return 'C';
  return 'D';
}

function resolveChampionStats(championId, role) {
  const statsForChamp = championStats?.[championId] ?? championStats?.[String(championId)];
  if (!statsForChamp) return null;

  if (statsForChamp[role]) return statsForChamp[role];

  let fallback = null;
  Object.values(statsForChamp).forEach(candidate => {
    if (!candidate || typeof candidate !== 'object') return;
    if (!fallback || (parseFloat(candidate.role_percent ?? 0) > parseFloat(fallback.role_percent ?? 0))) {
      fallback = candidate;
    }
  });
  return fallback;
}

function sortChampionLists(sortBy) {
  document.querySelectorAll('.champion-list').forEach(list => {
    const cards = Array.from(list.querySelectorAll('.champion-card'));
    if (cards.length === 0) return;

    cards.sort((a, b) => {
      if (sortBy === 'winrate') {
        const wrA = parseFloat(a.dataset.winrate) || 0;
        const wrB = parseFloat(b.dataset.winrate) || 0;
        return wrB - wrA;
      }
      if (sortBy === 'tier') {
        const tierOrder = {'S': 5, 'A': 4, 'B': 3, 'C': 2, 'D': 1, '?': 0};
        const tierA = (a.dataset.tier || '?').toUpperCase().replace(/\+$/, '');
        const tierB = (b.dataset.tier || '?').toUpperCase().replace(/\+$/, '');
        return (tierOrder[tierB] || 0) - (tierOrder[tierA] || 0);
      }
      return 0;
    });

    cards.forEach(card => list.appendChild(card));
  });
}

// Elo ändern
function changeElo(elo) {
  window.location.href = '?elo=' + elo;
}

// Modal Handling
function openAddModal(role) {
  document.getElementById('modalRole').value = role;
  document.getElementById('addModal').style.display = 'flex';
  document.getElementById('championSearch').focus();
  selectedChampionId = null;
  document.getElementById('championId').value = '';
  document.getElementById('submitBtn').disabled = true;
}

function closeAddModal() {
  document.getElementById('addModal').style.display = 'none';
  document.getElementById('addForm').reset();
  document.getElementById('championDropdown').innerHTML = '';
}

// Champion Autocomplete
function filterChampions(query) {
  const dropdown = document.getElementById('championDropdown');
  
  if (query.length < 1) {
    dropdown.innerHTML = '';
    return;
  }
  
  const filtered = allChampions.filter(champ => 
    champ.name.toLowerCase().includes(query.toLowerCase())
  ).slice(0, 10);
  
  if (filtered.length === 0) {
    dropdown.innerHTML = '<div class="dropdown-item no-results">Keine Champions gefunden</div>';
    return;
  }
  
  dropdown.innerHTML = filtered.map(champ => `
    <div class="dropdown-item" onclick="selectChampion(${champ.id}, '${champ.name.replace(/'/g, "\\'")}', '${champ.icon_url}')">
      <img src="${champ.icon_url}" alt="${champ.name}" class="dropdown-icon">
      <span>${champ.name}</span>
    </div>
  `).join('');
}

function selectChampion(id, name, icon) {
  selectedChampionId = id;
  document.getElementById('championId').value = id;
  document.getElementById('championSearch').value = name;
  document.getElementById('championDropdown').innerHTML = '';
  document.getElementById('submitBtn').disabled = false;
}

// Add Champion
async function addChampion(e) {
  e.preventDefault();
  
  if (!selectedChampionId) {
    alert('Bitte wähle einen Champion aus');
    return;
  }
  
  const formData = new FormData(e.target);
  formData.append('action', 'add_champion');
  // CSRF-Token ist bereits im Formular
  
  const response = await fetch(window.location.href, {
    method: 'POST',
    body: formData
  });
  
  const result = await response.json();
  if (result.success) {
    // Die alte Methode, die Seite neu zu laden, um die Karte zu bekommen, ist fehleranfällig.
    // Wir erstellen die Karte stattdessen direkt im Frontend.
    const role = document.getElementById('modalRole').value;
    const championList = document.querySelector(`.champion-list[data-role="${role}"]`);
    const newChampInfo = allChampions.find(c => c.id == selectedChampionId);

    if (championList && newChampInfo) {
      const emptyState = championList.querySelector('.empty-role');
      if (emptyState) {
        emptyState.remove();
      }

      const statsForRole = resolveChampionStats(selectedChampionId, role);
      let winrate = null;
      let tier = '?';
      if (statsForRole) {
        if (statsForRole.winrate !== undefined && statsForRole.winrate !== null) {
          const parsedWinrate = Number.parseFloat(statsForRole.winrate);
          if (Number.isFinite(parsedWinrate)) {
            winrate = parsedWinrate;
          }
        }
        tier = statsForRole.tier || (winrate !== null ? calculateTier(winrate) : '?');
      }

      const winrateDisplay = winrate !== null ? winrate.toFixed(2) : null;

      const newCard = document.createElement('div');
      newCard.className = 'champion-card';
      newCard.dataset.id = result.new_id;
      newCard.dataset.winrate = winrate !== null ? String(winrate) : '';
      newCard.dataset.tier = tier ?? '?';

      newCard.innerHTML = `
        <div class="champion-info">
          <img src="${newChampInfo.icon_url}" alt="${newChampInfo.name}" class="champ-icon">
          <span class="champion-name">${newChampInfo.name}</span>
          <button class="delete-btn" onclick="deleteChampion(${result.new_id})">×</button>
        </div>
        <div class="champion-stats">
          ${
            winrate !== null
              ? `
                <span class="stat-item">
                  <span class="stat-label">WR:</span>
                  <span class="stat-value" style="color: ${winrateColor(winrate)}">${winrateDisplay}%</span>
                </span>
                <span class="stat-item">
                  <span class="stat-label">Tier:</span>
                  <span class="tier-badge" style="background: ${tierColor(tier)}">${tier}</span>
                </span>
              `
              : `<span class="stat-item no-data">Keine Daten</span>`
          }
        </div>
      `;
      
      championList.appendChild(newCard);
      sortChampionLists(document.getElementById('sortBy').value);
    } else {
      // Fallback, falls etwas schiefgeht
      location.reload();
    }

    closeAddModal();
  } else {
    alert('Fehler: ' + result.error);
  }
}

// Delete Champion
async function deleteChampion(id) {
  const formData = new FormData();
  formData.append('action', 'delete_champion');
  formData.append('id', id);
  formData.append('csrf_token', csrfToken);
  
  const response = await fetch(window.location.href, {
    method: 'POST',
    body: formData
  });
  
  const result = await response.json();
  if (result.success) {
    // Dynamically remove the card
    const card = document.querySelector(`.champion-card[data-id="${id}"]`);
    if (card) {
      const list = card.parentElement;
      card.remove();
      if (list && !list.querySelector('.champion-card')) {
        list.insertAdjacentHTML('beforeend', '<div class="empty-role">Noch keine Champions gespeichert.</div>');
      }
    } else {
      location.reload(); // Fallback
    }
  } else {
    alert('Fehler: ' + result.error);
  }
}

// Sorting
document.getElementById('sortBy').addEventListener('change', function() {
  sortChampionLists(this.value);
});

// Close modal/dropdown on outside click
window.onclick = function(event) {
  const modal = document.getElementById('addModal');
  if (event.target === modal) {
    closeAddModal();
  }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('.autocomplete-wrapper')) {
    document.getElementById('championDropdown').innerHTML = '';
  }
});
</script>

<?php require_once __DIR__.'/inc/footer.php';
