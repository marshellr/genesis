<?php
declare(strict_types=1);

$site = require __DIR__ . '/../config/site.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Imprint | shellr</title>
  <meta name="description" content="Imprint for shellr.net.">
  <link rel="canonical" href="https://shellr.net/imprint.php">
  <link rel="icon" type="image/png" href="/assets/favi.png">
  <link rel="apple-touch-icon" href="/assets/favi.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
  <a class="skip-link" href="#main-content">Skip to content</a>
  <div class="site-noise" aria-hidden="true"></div>
  <div class="site-orb site-orb-a" aria-hidden="true"></div>
  <div class="site-orb site-orb-b" aria-hidden="true"></div>

  <header class="legal-header legal-shell">
    <a class="brand" href="/" aria-label="shellr home">
      <span class="brand-lockup">
        <img src="/assets/logo.png" alt="shellr" width="188" height="72">
      </span>
      <span class="brand-caption">legal</span>
    </a>
    <nav class="site-nav" aria-label="Primary">
      <a href="/">Home</a>
      <a href="/privacy.php">Privacy Policy</a>
    </nav>
  </header>

  <main class="legal-shell legal-main" id="main-content">
    <section class="legal-hero">
      <p class="eyebrow">Imprint</p>
      <h1>Provider information for shellr.net.</h1>
      <p class="legal-summary">
        This page contains the legal provider information for the public website and platform surfaces operated under the shellr.net domain.
      </p>
      <div class="legal-meta">
        <span>Website owner: Marlin Scheler</span>
        <span>Primary domain: shellr.net</span>
        <span>Contact: <?= e($site['contact_email']) ?></span>
      </div>
    </section>

    <div class="legal-stack">
      <section class="legal-panel">
        <p class="eyebrow">Provider</p>
        <h2>Information according to Section 5 DDG</h2>
        <address>
          Marlin Scheler<br>
          Baumschulenweg 17<br>
          96450 Coburg<br>
          Germany
        </address>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">Contact</p>
        <h2>Contact details</h2>
        <address>
          Email:
          <a class="inline-link" href="mailto:<?= e($site['contact_email']) ?>"><?= e($site['contact_email']) ?></a>
        </address>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">Editorial responsibility</p>
        <h2>Responsible for content</h2>
        <address>
          Marlin Scheler<br>
          Baumschulenweg 17<br>
          96450 Coburg<br>
          Germany
        </address>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">Notice</p>
        <h2>Liability for content and links</h2>
        <p>
          The contents of this website were created with care. However, no guarantee is given for the completeness,
          accuracy, or timeliness of the information provided. External links are checked at the time of linking.
          Permanent monitoring of linked content is not reasonable without concrete indications of a legal violation.
        </p>
      </section>
    </div>
  </main>

  <footer class="site-footer legal-shell">
    <div class="site-footer-row">
      <p>Marlin Scheler &middot; shellr.net</p>
      <nav class="site-footer-nav" aria-label="Legal">
        <a href="/imprint.php">Imprint</a>
        <a href="/privacy.php">Privacy Policy</a>
        <a href="mailto:<?= e($site['contact_email']) ?>"><?= e($site['contact_email']) ?></a>
      </nav>
    </div>
  </footer>
</body>
</html>
