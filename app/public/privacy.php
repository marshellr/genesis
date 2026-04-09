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
  <title>Privacy Policy | shellr</title>
  <meta name="description" content="Privacy policy for shellr.net.">
  <link rel="canonical" href="https://shellr.net/privacy.php">
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
        <img src="/assets/logo-small.png" srcset="/assets/logo-small.png 160w, /assets/logo.png 320w" sizes="(max-width: 720px) 120px, 160px" alt="shellr" width="160" height="155" decoding="async">
      </span>
      <span class="brand-caption">legal</span>
    </a>
    <nav class="site-nav" aria-label="Primary">
      <a href="/">Home</a>
      <a href="/imprint.php">Imprint</a>
    </nav>
  </header>

  <main class="legal-shell legal-main" id="main-content">
    <section class="legal-hero">
      <p class="eyebrow">Privacy Policy</p>
      <h1>Information about the processing of personal data.</h1>
      <p class="legal-summary">
        This privacy policy explains which data may be processed when visiting shellr.net and the associated public platform surfaces.
      </p>
      <div class="legal-meta">
        <span>Controller: Marlin Scheler</span>
        <span>Contact: <?= e($site['contact_email']) ?></span>
        <span>Applies to: shellr.net and related services</span>
      </div>
    </section>

    <div class="legal-stack">
      <section class="legal-panel">
        <p class="eyebrow">Controller</p>
        <h2>Responsible party</h2>
        <address>
          Marlin Scheler<br>
          Baumschulenweg 17<br>
          96450 Coburg<br>
          Germany<br><br>
          Email:
          <a class="inline-link" href="mailto:<?= e($site['contact_email']) ?>"><?= e($site['contact_email']) ?></a>
        </address>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">Server logs</p>
        <h2>Hosting and access data</h2>
        <p>
          When visiting this website, server log files may process technically necessary data such as IP address,
          date and time of access, requested resource, referrer, user agent, and response status. This processing is
          required to ensure the stable and secure operation of the website.
        </p>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">Contact requests</p>
        <h2>Email communication</h2>
        <p>
          If you contact me by email, the information you provide will be processed for the purpose of handling your request.
          The data will not be passed on to third parties unless required by law or necessary to answer your request.
        </p>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">External services</p>
        <h2>Embedded and linked services</h2>
        <ul>
          <li>Documentation is published via GitHub Pages under <code>docs.shellr.net</code>.</li>
          <li>Monitoring and status surfaces may technically process requests needed to display uptime information.</li>
          <li>External links may lead to services outside this website, each with their own privacy practices.</li>
        </ul>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">Your rights</p>
        <h2>Information, correction, deletion</h2>
        <p>
          Within the limits of the applicable legal framework, you have the right to request information about stored personal data,
          to request correction or deletion, and to object to processing where the legal requirements are met.
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
