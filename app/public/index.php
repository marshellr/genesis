<?php
declare(strict_types=1);

$site = require __DIR__ . '/../config/site.php';

$heroHighlights = [
    'Deployable through staged releases and health checks',
    'Observable through metrics, logs, and uptime tracking',
    'Recoverable through backups, restores, and rollback strategies',
];

$heroGuide = [
    'Explore the architecture',
    'View live system components',
    'Review operational decisions and trade-offs',
];

$liveComponents = [
    [
        'title' => 'shellr.net',
        'body' => 'Entry point and overview',
        'href' => 'https://shellr.net',
    ],
    [
        'title' => 'dma.shellr.net',
        'body' => 'Application runtime',
        'href' => $site['dma_url'],
    ],
    [
        'title' => 'status.shellr.net',
        'body' => 'Public uptime monitoring',
        'href' => $site['status_url'],
    ],
    [
        'title' => 'grafana.shellr.net',
        'body' => 'Metrics and logs (restricted)',
        'href' => '',
    ],
    [
        'title' => 'docs.shellr.net',
        'body' => 'Full technical documentation',
        'href' => $site['docs_url'],
    ],
];

$platformSnapshot = [
    ['title' => 'Host', 'body' => 'Single Hetzner VM with 4 vCPU, 8 GB RAM, and bounded disk usage.'],
    ['title' => 'Runtime', 'body' => 'Services run in separate containers instead of sharing one unmanaged host setup.'],
    ['title' => 'Ingress', 'body' => 'Nginx handles HTTPS, redirects, and hostname-based routing for public services.'],
    ['title' => 'Delivery', 'body' => 'GitHub Actions deploys staged releases over SSH and checks service health before completion.'],
    ['title' => 'Monitoring', 'body' => 'Prometheus, Grafana, Node Exporter, cAdvisor, and Uptime Kuma cover host and service visibility.'],
    ['title' => 'Recovery', 'body' => 'Database dumps, project archives, and restore scripts are part of normal operations.'],
];

$operationalMetrics = [
    ['title' => 'Public Surfaces', 'body' => '5 public hostnames routed through one ingress layer.'],
    ['title' => 'Network Segments', 'body' => '3 Docker networks separating frontend, backend, and monitoring traffic.'],
    ['title' => 'Backup Cadence', 'body' => 'Daily database and runtime backup jobs with defined retention.'],
    ['title' => 'Monitoring Coverage', 'body' => 'Host, container, and application checks are included in the stack.'],
];

$securityModel = [
    [
        'title' => 'Ingress Control',
        'points' => [
            'TLS termination via Nginx (HTTPS only)',
            'Redirect enforcement and hostname routing',
            'Basic request filtering and rate limiting',
        ],
    ],
    [
        'title' => 'Access Control',
        'points' => [
            'Grafana and internal tools behind authentication',
            'No direct public exposure of internal services',
        ],
    ],
    [
        'title' => 'Isolation',
        'points' => [
            'Service separation via Docker networks',
            'Distinct boundaries between frontend, app, and monitoring',
        ],
    ],
    [
        'title' => 'Recovery',
        'points' => [
            'Regular backups with defined retention',
            'Restore scripts tested against real workloads',
        ],
    ],
];

$operationalFocus = [
    'Incidents can be detected via metrics and uptime checks',
    'Logs provide traceability across services',
    'Deployments are gated by health checks',
    'Recovery paths are defined and tested',
];

$featuredProjects = [
    [
        'title' => 'Genesis / Self-Hosted Platform',
        'tag' => 'platform',
        'lead' => 'Platform used to validate operational decisions under real conditions instead of theoretical setups.',
        'points' => [
            'Focus: Deployability',
            'Focus: Observability',
            'Focus: Recoverability',
        ],
        'stack' => ['Linux', 'Docker Compose', 'Nginx', 'GitHub Actions'],
        'href' => $site['docs_url'] . '/case-studies.html',
        'label' => 'Read case studies',
    ],
    [
        'title' => 'DMA Statistics Module',
        'tag' => 'live application',
        'lead' => 'Simulates a stateful application to test deployment, monitoring, and recovery under realistic conditions.',
        'points' => [
            'Includes: Database persistence',
            'Includes: Application runtime isolation',
            'Includes: Health-based monitoring and alerting',
        ],
        'stack' => ['PHP', 'MariaDB', 'Health checks', 'Monitoring'],
        'href' => $site['dma_url'],
        'label' => 'Open live system',
    ],
    [
        'title' => 'Automated Web Analytics with AWStats',
        'tag' => 'reporting',
        'lead' => 'Generates lightweight reporting from webserver logs without adding a heavy analytics stack.',
        'points' => [
            'Turns raw access logs into readable reports',
            'Runs on a scheduled shell-based workflow',
            'Fits the resource limits of a small single-host platform',
        ],
        'stack' => ['AWStats', 'Shell scripts', 'Cron', 'Reporting'],
        'href' => $site['docs_url'] . '/projects.html',
        'label' => 'Read project overview',
    ],
    [
        'title' => 'Web Platform Migration and Hardening',
        'tag' => 'operations',
        'lead' => 'Moves existing web workloads into a safer operating model with cleaner routing and tighter access control.',
        'points' => [
            'Improves SSH, TLS, firewalling, and reverse proxy structure',
            'Reduces cross-impact between services',
            'Turns inherited setups into supportable systems',
        ],
        'stack' => ['Ubuntu', 'SSH', 'UFW', 'Nginx'],
        'href' => $site['docs_url'] . '/architecture.html',
        'label' => 'View architecture',
    ],
    [
        'title' => 'Inventory Tracking Application',
        'tag' => 'stateful application',
        'lead' => 'Simulates a stateful application to test deployment, monitoring, and recovery under realistic conditions.',
        'points' => [
            'Includes: Database persistence',
            'Includes: Application runtime isolation',
            'Includes: Health-based monitoring and alerting',
        ],
        'stack' => ['PHP', 'MariaDB', 'CRUD', 'Health checks'],
        'href' => $site['docs_url'] . '/projects.html',
        'label' => 'Read project notes',
    ],
];

$docsIncludes = [
    'Architecture',
    'Deployment flow',
    'Monitoring setup',
    'Backup and restore strategy',
];

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

  <header class="site-header">
    <a class="brand" href="/" aria-label="shellr home">
      <span class="brand-lockup">
        <img src="/assets/logo-small.png" srcset="/assets/logo-small.png 160w, /assets/logo.png 320w" sizes="(max-width: 720px) 120px, 160px" alt="shellr" width="160" height="155" decoding="async">
      </span>
      <span class="brand-caption">platform frontdoor</span>
    </a>
    <nav class="site-nav" aria-label="Primary">
      <a href="#live-components">Live Systems</a>
      <a href="#metrics">Metrics</a>
      <a href="#security-model">Security</a>
      <a href="#projects">Projects</a>
      <a href="#documentation">Docs</a>
    </nav>
  </header>

  <main id="main-content">
    <section class="hero" id="top">
      <div class="hero-copy" data-reveal>
        <p class="eyebrow">Marlin Scheler &middot; Junior DevOps / System Engineer</p>
        <h1>Self-hosted platform demonstrating real-world system operation.</h1>

        <ul class="outcome-list">
          <?php foreach ($heroHighlights as $item): ?>
          <li><?= e($item) ?></li>
          <?php endforeach; ?>
        </ul>

        <div class="hero-note">
          <p class="hero-note-title">What you can do here:</p>
          <ul class="hero-guide-list">
            <?php foreach ($heroGuide as $item): ?>
            <li><?= e($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="hero-actions">
          <a class="button primary" href="<?= e($site['docs_url'] . '/architecture.html') ?>">View Architecture</a>
          <a class="button secondary" href="<?= e($site['dma_url']) ?>">Open Live System</a>
          <a class="button secondary" href="<?= e($site['docs_url']) ?>">Read Documentation</a>
        </div>
      </div>

      <aside class="hero-panel" data-reveal>
        <div class="hero-panel-header">
          <span>platform scope</span>
          <strong>real services, measured responsibility</strong>
        </div>

        <div class="surface-list">
          <article>
            <span>runtime</span>
            <strong>Single-VM platform</strong>
            <p>One host, containerized services, documented recovery, and bounded infrastructure choices.</p>
          </article>
          <article>
            <span>operations</span>
            <strong>Monitoring and logging included</strong>
            <p>Host, service, and application visibility are built into the platform instead of added later.</p>
          </article>
          <article>
            <span>documentation</span>
            <strong>Reviewer-friendly path</strong>
            <p>Architecture, deployment, monitoring, and backup documentation are linked directly from the landing page.</p>
          </article>
        </div>
      </aside>
    </section>

    <section class="section" id="live-components">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Live System Components</p>
        <h2>Public entry points and restricted operations surfaces.</h2>
      </div>

      <div class="link-grid">
        <?php foreach ($liveComponents as $component): ?>
        <?php if ($component['href'] !== ''): ?>
        <a class="link-card" href="<?= e($component['href']) ?>" data-reveal>
          <span>live component</span>
          <strong><?= e($component['title']) ?></strong>
          <p><?= e($component['body']) ?></p>
        </a>
        <?php else: ?>
        <article class="link-card" data-reveal>
          <span>restricted</span>
          <strong><?= e($component['title']) ?></strong>
          <p><?= e($component['body']) ?></p>
        </article>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section snapshot-section" id="snapshot">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Platform Snapshot</p>
        <h2>Concrete operating characteristics of the platform.</h2>
      </div>

      <div class="snapshot-grid" data-reveal>
        <?php foreach ($platformSnapshot as $item): ?>
        <article class="snapshot-card">
          <strong><?= e($item['title']) ?></strong>
          <p><?= e($item['body']) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section" id="metrics">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Operational Metrics</p>
        <h2>Measured platform scope and operational coverage.</h2>
      </div>

      <div class="snapshot-grid" data-reveal>
        <?php foreach ($operationalMetrics as $item): ?>
        <article class="snapshot-card">
          <strong><?= e($item['title']) ?></strong>
          <p><?= e($item['body']) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section" id="security-model">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Security Model</p>
        <h2>Security is treated as part of system design, not a side note.</h2>
      </div>

      <div class="work-grid">
        <?php foreach ($securityModel as $group): ?>
        <article class="work-panel" data-reveal>
          <h3><?= e($group['title']) ?></h3>
          <ul>
            <?php foreach ($group['points'] as $point): ?>
            <li><?= e($point) ?></li>
            <?php endforeach; ?>
          </ul>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section" id="operations">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Operational Focus</p>
        <h2>This platform is designed to be operated, not just deployed.</h2>
      </div>

      <div class="platform-grid">
        <article class="work-panel" data-reveal>
          <ul>
            <?php foreach ($operationalFocus as $item): ?>
            <li><?= e($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </article>

        <div class="platform-diagram" data-reveal>
          <div class="platform-diagram-header">
            <span>service map</span>
            <strong>one ingress layer, containerized services, monitored operations</strong>
          </div>
          <pre>Internet
   |
Nginx (Ingress)
   |
Docker Services (App / Monitoring)
   |
Metrics + Logs (Prometheus / Grafana)
   |
Backup System</pre>
        </div>
      </div>
    </section>

    <section class="section" id="projects">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Projects</p>
        <h2>Projects framed by concrete system behavior and operational value.</h2>
      </div>

      <div class="project-grid">
        <?php foreach ($featuredProjects as $project): ?>
        <article class="project-card" data-reveal>
          <div class="project-card-top">
            <span class="project-tag"><?= e($project['tag']) ?></span>
            <h3><?= e($project['title']) ?></h3>
          </div>
          <p class="project-lead"><?= e($project['lead']) ?></p>
          <ul class="project-points">
            <?php foreach ($project['points'] as $point): ?>
            <li><?= e($point) ?></li>
            <?php endforeach; ?>
          </ul>
          <ul class="project-stack">
            <?php foreach ($project['stack'] as $item): ?>
            <li><?= e($item) ?></li>
            <?php endforeach; ?>
          </ul>
          <a class="text-link" href="<?= e($project['href']) ?>"><?= e($project['label']) ?></a>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section cta-section" id="documentation">
      <div class="cta-panel" data-reveal>
        <p class="eyebrow">Documentation</p>
        <h2>Full documentation available: docs.shellr.net</h2>
        <p>
          The documentation site is the full technical record for the platform. It connects the landing page
          with architecture, deployment, monitoring, and recovery details.
        </p>
        <div class="docs-note">
          <p class="hero-note-title">Includes:</p>
          <ul class="hero-guide-list">
            <?php foreach ($docsIncludes as $item): ?>
            <li><?= e($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="hero-actions">
          <a class="button primary" href="<?= e($site['docs_url']) ?>">Read Documentation</a>
          <a class="button secondary" href="<?= e($site['docs_url'] . '/architecture.html') ?>">View Architecture</a>
          <a class="button secondary" href="<?= e($site['status_url']) ?>">Open Status</a>
          <a class="button secondary" href="<?= e($githubUrl) ?>">Open Repository</a>
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

  <script src="/assets/app.js"></script>
</body>
</html>
