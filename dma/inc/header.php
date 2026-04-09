<?php
$pageTitle = $pageTitle ?? 'Prime League Stats';
$bodyAttributes = isset($bodyAttributes) ? trim((string)$bodyAttributes) : '';
$bodyAttrString = $bodyAttributes !== '' ? ' '.$bodyAttributes : '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=safe($pageTitle)?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" href="assets/favicon.png" type="image/png">
</head>
<body<?=$bodyAttrString?>>
<a class="skip-link" href="#main-content">Skip to content</a>

<header>
  <div class="header-content">
    <div class="brand">
      <a href="index.php" class="brand-link">
        <img src="assets/logo.png" alt="Prime League Stats Logo" class="site-logo">
        <span class="brand-title">DMA Stat Sheet</span>
      </a>
    </div>
    <nav class="main-nav">
      <a href="index.php" class="nav-btn <?= ($activePage === 'dashboard') ? 'active' : '' ?>">Dashboard</a>
      <a href="champion-pool.php" class="nav-btn <?= ($activePage === 'champion-pool') ? 'active' : '' ?>">Champion Pool</a>
      <a href="overall-stats.php" class="nav-btn <?= ($activePage === 'overall-stats') ? 'active' : '' ?>">Alltime Stats</a>
    </nav>
  </div>
</header>
