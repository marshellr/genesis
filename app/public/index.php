<?php
declare(strict_types=1);

$site = require __DIR__ . '/../config/site.php';
require_once __DIR__ . '/../config/assets.php';
$githubUrl = $site['github_url'] !== '' ? $site['github_url'] : 'https://github.com/marshellr/genesis';

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
  <title>shellr | DevOps / System Engineer</title>
  <meta name="description" content="Self-hosted platform demonstrating real-world system operation with deployment, monitoring, logging, and recovery built into the operating model.">
  <link rel="canonical" href="https://shellr.net/">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="shellr">
  <meta property="og:title" content="shellr | DevOps / System Engineer">
  <meta property="og:description" content="Self-hosted platform demonstrating deployment, monitoring, logging, and recovery in a live operating model.">
  <meta property="og:url" content="https://shellr.net/">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="shellr | DevOps / System Engineer">
  <meta name="twitter:description" content="Self-hosted platform demonstrating deployment, monitoring, logging, and recovery in a live operating model.">
  <link rel="icon" type="image/png" href="<?= asset_url('/assets/favi.png') ?>">
  <link rel="apple-touch-icon" href="<?= asset_url('/assets/favi.png') ?>">
  <link rel="stylesheet" href="<?= asset_url('/assets/fonts.css') ?>">
  <link rel="stylesheet" href="<?= asset_url('/assets/styles.css') ?>">
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Person",
        "name": "Marlin Scheler",
        "url": "https://shellr.net/",
        "jobTitle": "Junior DevOps / System Engineer",
        "sameAs": ["https://github.com/marshellr"]
      },
      {
        "@type": "WebSite",
        "name": "shellr",
        "url": "https://shellr.net/"
      }
    ]
  }
  </script>
</head>
<body>
  <a class="skip-link" href="#main-content">Skip to content</a>
  <div class="site-noise" aria-hidden="true"></div>
  <div class="site-orb site-orb-a" aria-hidden="true"></div>
  <div class="site-orb site-orb-b" aria-hidden="true"></div>

  <header class="site-header">
    <a class="brand" href="/" aria-label="shellr home">
      <span class="brand-lockup">
        <img src="<?= asset_url('/assets/logo-small.png') ?>" srcset="<?= asset_url('/assets/logo-small.png') ?> 160w, <?= asset_url('/assets/logo.png') ?> 320w" sizes="(max-width: 720px) 120px, 160px" alt="shellr" width="160" height="155" decoding="async">
      </span>
      <span class="brand-caption">platform frontdoor</span>
    </a>
    <nav class="site-nav" aria-label="Primary">
      <a href="#projects">Projects</a>
      <a href="#platform-facts">Platform facts</a>
      <a href="#documentation">Documentation and contact</a>
    </nav>
  </header>

  <main id="main-content">
    <section class="hero hero-portfolio" id="top">
      <div class="hero-copy" data-reveal>
        <p class="eyebrow">Marlin Scheler &middot; Junior DevOps / System Engineer</p>
        <h1>I operate a self-hosted platform on my own hardware.</h1>
        <p class="hero-summary">I use it to test deployments, service isolation and recovery under real operating conditions.</p>
        <p class="hero-summary">My work covers Linux administration, Docker Compose, reverse-proxy configuration, monitoring and backup verification. The repository and documentation show the implementation and the checks used to operate it.</p>
        <div class="hero-actions">
          <a class="button primary" href="#projects">View projects</a>
          <a class="button secondary" href="<?= e($site['docs_url'] . '/architecture.html') ?>">Read the architecture</a>
          <a class="button secondary" href="<?= e($githubUrl) ?>">Open GitHub</a>
        </div>
      </div>
    </section>

    <section class="section" id="projects">
      <div class="section-heading" data-reveal><h2>Projects</h2></div>
      <div class="project-grid">
        <article class="project-card" data-reveal>
          <div class="project-card-top">
            <span class="project-tag">Running in production</span>
            <h3>Genesis</h3>
          </div>
          <div class="project-detail">
            <h4>Problem</h4>
            <p>Move containerized services from a cloud VM to an operator-owned homelab while preserving data and keeping the public and internal interfaces separate.</p>
          </div>
          <div class="project-detail">
            <h4>Implementation</h4>
            <p>The platform runs on Proxmox VE with Docker Compose. Public traffic reaches Nginx through Cloudflare Tunnel. Internal interfaces use local DNS. Separate Docker networks carry frontend, database and monitoring traffic.</p>
            <p>A push to main produces a versioned release. The server downloads it, verifies its checksum and creates a database backup before deployment. Nginx configuration, origin readiness and application responses are checked before the server confirms the deployed commit. A failed verification restores the previous release.</p>
          </div>
          <div class="project-detail">
            <h4>Verification and result</h4>
            <p>Production checks cover the portfolio, the DMA showcase and restricted operational interfaces. Recovery tests import logical database dumps into an isolated instance and check the restored SQLite databases. The repository contains the deployment and verification scripts.</p>
          </div>
          <a class="text-link" href="<?= e($site['docs_url'] . '/case-studies.html') ?>">Read case studies</a>
        </article>
        <article class="project-card" data-reveal>
          <div class="project-card-top">
            <span class="project-tag">Read-only public showcase</span>
            <h3>DMA</h3>
          </div>
          <div class="project-detail">
            <h4>Problem</h4>
            <p>Present a PHP and MariaDB match-statistics project while keeping its administrative functions behind a separate access boundary.</p>
          </div>
          <div class="project-detail">
            <h4>Implementation</h4>
            <p>The public hostname serves static project context. The PHP service has no published host port. The PHP logic is kept outside the served web root. Database checks and cache prefetch run through CLI entry points.</p>
          </div>
          <div class="project-detail">
            <h4>Verification and result</h4>
            <p>Deployment checks verify that PHP endpoints return HTTP 404 at the origin and through the public route. The application remains available as a read-only architecture showcase.</p>
          </div>
          <a class="text-link" href="<?= e($site['dma_url']) ?>">Open project showcase</a>
        </article>
        <article class="project-card" data-reveal>
          <div class="project-card-top">
            <span class="project-tag">Conceptual overview</span>
            <h3>EVC — ENISA Vulnerability Check</h3>
          </div>
          <div class="project-detail">
            <h4>Problem</h4>
            <p>Security advisories only become actionable once they are matched to the systems actually in use.</p>
          </div>
          <div class="project-detail">
            <h4>Implementation</h4>
            <p>The workflow retrieves vulnerability records from ENISA EUVD, normalises product and version information, and compares it with an asset inventory. Relevant matches are enriched with CVSS severity, EPSS exploitation probability and evidence of known exploitation.</p>
            <p>A structured report preserves the matching evidence and prioritisation criteria for human review. The approach combines API integration, version matching, vulnerability enrichment and repeatable report generation.</p>
          </div>
          <div class="project-detail">
            <h4>Verification and result</h4>
            <p>A traceable review process connects an advisory to a potentially affected system and makes the reasons for its priority visible. Product and version matches remain subject to validation.</p>
          </div>
        </article>
      </div>
    </section>

    <section class="section" id="platform-facts">
      <div class="section-heading" data-reveal><h2>Platform facts</h2></div>
      <div class="work-panel" data-reveal>
        <ul>
          <li>Three Docker networks separate frontend, backend and monitoring traffic.</li>
          <li>The nightly backup creates logical database dumps and consistent SQLite copies before encrypted offsite transfer.</li>
          <li>Release confirmation is written after host verification. A failed deploy retains the previous confirmation.</li>
          <li>Monitoring combines metrics, logs and availability checks on internal interfaces.</li>
        </ul>
      </div>
    </section>

    <section class="section cta-section" id="documentation">
      <div class="cta-panel" data-reveal>
        <h2>Documentation and contact</h2>
        <p>Architecture, deployment and recovery procedures are documented alongside the source. The documentation links to the implementation and records operational limits.</p>
        <div class="hero-actions">
          <a class="button primary" href="<?= e($site['docs_url']) ?>">Read documentation</a>
          <a class="button secondary" href="https://github.com/marshellr/genesis">Open repository</a>
          <a class="button secondary" href="mailto:<?= e($site['contact_email']) ?>">Contact by email</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="site-footer-row">
      <p>Marlin Scheler &middot; Junior DevOps / System Engineer &middot; Linux, containers, monitoring, recovery</p>
      <nav class="site-footer-nav" aria-label="Legal">
        <a href="/imprint.php">Imprint</a>
        <a href="/privacy.php">Privacy Policy</a>
        <a href="mailto:<?= e($site['contact_email']) ?>"><?= e($site['contact_email']) ?></a>
      </nav>
    </div>
  </footer>

  <script src="<?= asset_url('/assets/app.js') ?>"></script>
</body>
</html>
