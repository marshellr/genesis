<?php
declare(strict_types=1);

$site = require __DIR__ . '/../config/site.php';

$platformSnapshot = [
    ['title' => 'Host', 'body' => 'Single Hetzner VM with 4 vCPU, 8 GB RAM, and bounded disk usage.'],
    ['title' => 'Runtime', 'body' => 'Service separation through Docker Compose instead of process sprawl on the host.'],
    ['title' => 'Ingress', 'body' => 'Nginx handles hostname routing, HTTPS, redirects, and public surface boundaries.'],
    ['title' => 'Delivery', 'body' => 'GitHub Actions builds staged releases and deploys over SSH with health-gated checks.'],
    ['title' => 'Monitoring', 'body' => 'Prometheus, Grafana, Node Exporter, cAdvisor, and Uptime Kuma cover host and service visibility.'],
    ['title' => 'Logging', 'body' => 'Loki and Promtail provide short-retention centralized logs without ELK overhead.'],
    ['title' => 'Recovery', 'body' => 'Database dumps, project archives, and explicit restore scripts are part of the operating model.'],
    ['title' => 'Docs', 'body' => 'Technical documentation is published through GitHub Pages, not served from the VM.'],
];

$outcomes = [
    'Deployable through staged SSH releases and health-gated checks.',
    'Observable through metrics, logs, and a public uptime surface.',
    'Recoverable through backups, restore scripts, and rollback-aware changes.',
];

$startHere = [
    [
        'title' => 'Open Architecture',
        'body' => 'Start with the public surfaces, trust boundaries, and container responsibilities on the single host.',
        'href' => $site['docs_url'] . '/architecture.html',
        'label' => 'View architecture',
    ],
    [
        'title' => 'Review Deployment Flow',
        'body' => 'See how releases are built, transferred, switched, and verified before a deployment is considered done.',
        'href' => $site['docs_url'] . '/deployment-flow.html',
        'label' => 'Open deployment flow',
    ],
    [
        'title' => 'Check Live Status',
        'body' => 'Use the public status surface to verify service health without needing internal access.',
        'href' => $site['status_url'],
        'label' => 'Open status',
    ],
    [
        'title' => 'Browse Source and Notes',
        'body' => 'Inspect repository structure, live documentation, and the decisions behind the current platform shape.',
        'href' => $site['github_url'] !== '' ? $site['github_url'] : 'https://github.com/marshellr/genesis',
        'label' => 'Open GitHub',
    ],
];

$proofLinks = [
    [
        'title' => 'Architecture map',
        'body' => 'Review hostnames, container boundaries, and public versus private surfaces first.',
        'href' => $site['docs_url'] . '/architecture.html',
        'label' => 'Open architecture',
    ],
    [
        'title' => 'Deployment runbook',
        'body' => 'See the real release path: staged files, health-gated checks, and rollback expectations.',
        'href' => $site['docs_url'] . '/deployment-flow.html',
        'label' => 'Open deployment flow',
    ],
    [
        'title' => 'Monitoring and alerts',
        'body' => 'Metrics, status checks, container visibility, alert rules, and responder-first dashboards.',
        'href' => $site['docs_url'] . '/monitoring.html',
        'label' => 'Open monitoring',
    ],
    [
        'title' => 'Backup and restore',
        'body' => 'Daily dumps, runtime snapshots, optional offsite sync, and explicit restore steps.',
        'href' => $site['docs_url'] . '/backup.html',
        'label' => 'Open backup strategy',
    ],
    [
        'title' => 'Case studies',
        'body' => 'Concrete platform trade-offs with constraints, failure modes, and operating outcomes.',
        'href' => $site['docs_url'] . '/case-studies.html',
        'label' => 'Read case studies',
    ],
    [
        'title' => 'Live source of truth',
        'body' => 'Repository structure, runtime files, and the same source tree that is deployed on the VM.',
        'href' => $site['github_url'] !== '' ? $site['github_url'] : 'https://github.com/marshellr/genesis',
        'label' => 'Open repository',
    ],
];

$focusAreas = [
    'Linux, containers, and web platforms with explicit operational boundaries',
    'Deployment, healthchecks, and recovery instead of build-only thinking',
    'Monitoring, logging, and documentation as part of the platform itself',
    'Technical decisions that stay explainable on a single VM',
];

$featuredProjects = [
    [
        'title' => 'Genesis / DevOps Platform',
        'tag' => 'platform',
        'lead' => 'A single-VM platform built around operational clarity.',
        'points' => [
            'Runs the public entrypoints, application workloads, monitoring, logging, and backups on one host.',
            'Uses explicit subdomains, Compose-managed services, and documented rollback and restore paths.',
            'Prioritizes bounded retention and maintainability over adding more tooling than the host needs.',
        ],
        'stack' => ['Linux', 'Docker Compose', 'Nginx', 'GitHub Actions'],
        'href' => $site['docs_url'],
        'label' => 'Open platform docs',
    ],
    [
        'title' => 'DMA Statistics Module',
        'tag' => 'live project',
        'lead' => 'A legacy PHP workload integrated as its own runtime surface.',
        'points' => [
            'Runs under its own subdomain and container instead of being buried inside the main site.',
            'Uses separate database scope, healthchecks, and reverse-proxy routing that stay reviewable.',
            'Turns an existing application into something that can be operated cleanly on the platform.',
        ],
        'stack' => ['PHP', 'MariaDB', 'Docker', 'Subdomain routing'],
        'href' => $site['dma_url'],
        'label' => 'Open DMA live',
    ],
    [
        'title' => 'AWStats Reporting / Automation',
        'tag' => 'reporting',
        'lead' => 'Reporting focused on visibility instead of raw log volume.',
        'points' => [
            'Collects and summarizes access and usage patterns into readable reporting outputs.',
            'Uses repeatable shell-based automation instead of manual report generation.',
            'Keeps reporting lightweight enough for a small host and a small platform team.',
        ],
        'stack' => ['AWStats', 'Shell scripts', 'Cron', 'Reporting'],
        'href' => $site['docs_url'] . '/projects.html',
        'label' => 'Open project overview',
    ],
    [
        'title' => 'Web Platform Migration & Hardening',
        'tag' => 'migration',
        'lead' => 'Taking over existing systems and making them safer to run.',
        'points' => [
            'Covers SSH hardening, firewalling, TLS cleanup, and reverse-proxy restructuring.',
            'Focuses on controlled migration steps instead of risky one-shot rewrites.',
            'Treats operational debt as something to be reduced deliberately, not ignored.',
        ],
        'stack' => ['Ubuntu', 'SSH', 'UFW', 'Fail2ban'],
        'href' => $site['docs_url'] . '/architecture.html',
        'label' => 'Open architecture',
    ],
    [
        'title' => 'Inventory Tracking Application',
        'tag' => 'application',
        'lead' => 'A compact CRUD workload used as an operational proving ground.',
        'points' => [
            'Represents a realistic PHP and MariaDB application with healthchecks and persistent state.',
            'Makes deployment, backup, and monitoring choices visible under simple but real workload conditions.',
            'Works as a clean example of app-level behavior inside the wider platform model.',
        ],
        'stack' => ['PHP', 'MariaDB', 'CRUD', 'Healthchecks'],
        'href' => $site['docs_url'] . '/projects.html',
        'label' => 'Read project notes',
    ],
];

$platformSlices = [
    [
        'title' => 'Ingress',
        'body' => 'A central Nginx entrypoint handles host routing, HTTPS, redirects, and the separation of public surfaces.',
    ],
    [
        'title' => 'Runtime',
        'body' => 'The landing page, DMA, database, monitoring, and logging run in containers with clear responsibilities instead of becoming an unstructured host mix.',
    ],
    [
        'title' => 'Observability',
        'body' => 'Prometheus, Grafana, Uptime Kuma, Loki, Promtail, and cAdvisor provide host, service, and container visibility with bounded overhead.',
    ],
    [
        'title' => 'Recovery',
        'body' => 'Backups, restore scripts, and rollback-aware deployments are treated as part of the system, not as optional extras.',
    ],
];

$workStyle = [
    'Tools only matter when they improve the way a system is actually operated.',
    'I prefer explicit boundaries and readable structures over hidden magic.',
    'Monitoring, logging, and backup belong in the platform design, not as afterthoughts.',
    'Operational decisions should still be understandable weeks later.',
];

$liveLinks = [
    [
        'label' => 'Explore Platform',
        'href' => $site['docs_url'],
        'meta' => 'Architecture, deployment flow, monitoring, logging, and backup design.',
    ],
    [
        'label' => 'View Live Systems',
        'href' => $site['dma_url'],
        'meta' => 'DMA as a live workload running inside the platform.',
    ],
    [
        'label' => 'Check System Status',
        'href' => $site['status_url'],
        'meta' => 'Public uptime surface for the main services and health checks.',
    ],
    [
        'label' => 'GitHub',
        'href' => $site['github_url'] !== '' ? $site['github_url'] : 'https://github.com/marshellr/genesis',
        'meta' => 'Repository, technical source material, and project structure.',
    ],
];

if ($site['grafana_url'] !== '') {
    $liveLinks[] = [
        'label' => 'Grafana',
        'href' => $site['grafana_url'],
        'meta' => 'Protected operational metrics and logging access for the platform.',
    ];
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function host_label(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) && $host !== '' ? $host : $url;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>shellr | DevOps / System Engineer</title>
  <meta name="description" content="Marlin Scheler builds and operates small Linux-based web platforms with Docker, monitoring, deployment, backup, and documented recovery paths.">
  <link rel="canonical" href="https://shellr.net/">
  <link rel="icon" type="image/png" href="/assets/favi.png">
  <link rel="apple-touch-icon" href="/assets/favi.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/styles.css">
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Person",
        "name": "Marlin Scheler",
        "jobTitle": "Junior DevOps Engineer / System Engineer",
        "url": "https://shellr.net/",
        "sameAs": [
          "<?= e($site['github_url'] !== '' ? $site['github_url'] : 'https://github.com/marshellr/genesis') ?>"
        ]
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
        <img src="/assets/logo.png" alt="shellr" width="188" height="72">
      </span>
      <span class="brand-caption">platform frontdoor</span>
    </a>
    <nav class="site-nav" aria-label="Primary">
      <a href="#profile">Profile</a>
      <a href="#projects">Projects</a>
      <a href="#platform">Platform</a>
      <a href="#links">Live Links</a>
    </nav>
  </header>

  <main id="main-content">
    <section class="hero" id="top">
      <div class="hero-copy" data-reveal>
        <p class="eyebrow">Marlin Scheler &middot; Junior DevOps / System Engineer</p>
        <h1>Build systems that can actually be operated.</h1>
        <p class="hero-summary">
          I build and operate small Linux-based web platforms with deployment, monitoring, backup,
          and recovery designed in from day one. shellr.net is a live operating environment with
          public services, internal observability, and documented operational constraints.
        </p>

        <ul class="outcome-list">
          <?php foreach ($outcomes as $outcome): ?>
          <li><?= e($outcome) ?></li>
          <?php endforeach; ?>
        </ul>

        <div class="hero-actions">
          <a class="button primary" href="#start-here">Explore Platform</a>
          <a class="button secondary" href="#links">View Live Systems</a>
        </div>
      </div>

      <aside class="hero-panel" data-reveal>
        <div class="logo-panel">
          <div class="logo-card">
            <img src="/assets/logo.png" alt="shellr wordmark" width="260" height="102">
          </div>
          <div class="logo-copy">
            <span>identity</span>
            <strong>clear form, clear operational boundaries</strong>
            <p>
              The visual system follows the same idea as the platform itself: a dark base layer,
              clean structure, one signal color, and as little unnecessary noise as possible.
            </p>
          </div>
        </div>

        <div class="hero-panel-header">
          <span>live surfaces</span>
          <strong>platform entry points</strong>
        </div>

        <div class="surface-list">
          <article>
            <span>frontdoor</span>
            <strong>shellr.net</strong>
            <p>Landing page, role positioning, project access, and platform overview.</p>
          </article>
          <article>
            <span>application</span>
            <strong>dma.shellr.net</strong>
            <p>Separate PHP runtime with its own boundary, database scope, and health behavior.</p>
          </article>
          <article>
            <span>status</span>
            <strong>status.shellr.net</strong>
            <p>Public uptime surface backed by the same monitoring logic used operationally.</p>
          </article>
          <article>
            <span>documentation</span>
            <strong>docs.shellr.net</strong>
            <p>Architecture, deployment, monitoring, logging, and recovery documentation on GitHub Pages.</p>
          </article>
        </div>
      </aside>
    </section>

    <section class="section snapshot-section" id="snapshot">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Platform Snapshot</p>
        <h2>Operational proof at a glance.</h2>
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

    <section class="section start-section" id="start-here">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Start Here</p>
        <h2>Follow the same path a technical reviewer would.</h2>
      </div>

      <div class="start-grid">
        <?php foreach ($startHere as $entry): ?>
        <article class="start-card" data-reveal>
          <h3><?= e($entry['title']) ?></h3>
          <p><?= e($entry['body']) ?></p>
          <a class="inline-link" href="<?= e($entry['href']) ?>"><?= e($entry['label']) ?></a>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section" id="proof-pack">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Proof Pack</p>
        <h2>Follow the operational trail, not just the landing page.</h2>
      </div>

      <div class="link-grid">
        <?php foreach ($proofLinks as $entry): ?>
        <article class="link-card" data-reveal>
          <strong><?= e($entry['title']) ?></strong>
          <p><?= e($entry['body']) ?></p>
          <a class="inline-link" href="<?= e($entry['href']) ?>"><?= e($entry['label']) ?></a>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section profile-section" id="profile">
      <div class="section-copy" data-reveal>
        <p class="eyebrow">Profile</p>
        <h2>Focused on infrastructure, delivery, and operational clarity.</h2>
        <p>
          I am training as a systems integration specialist and moving toward a junior DevOps or systems role.
          The work I care about most starts where deployment, routing, monitoring, and failure handling overlap.
          My goal is to build systems that remain understandable, supportable, and reviewable after they go live.
        </p>
      </div>

      <div class="focus-grid" data-reveal>
        <?php foreach ($focusAreas as $focus): ?>
        <article class="focus-card">
          <span></span>
          <p><?= e($focus) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section" id="projects">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Featured Projects</p>
        <h2>Projects that show technical choices under real operating constraints.</h2>
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

    <section class="section" id="platform">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Platform / Infrastructure</p>
        <h2>A running platform, not a static list of projects.</h2>
      </div>

      <div class="platform-grid">
        <div class="platform-diagram" data-reveal>
          <div class="platform-diagram-header">
            <span>runtime topology</span>
            <strong>explicit separation instead of unnecessary platform breadth</strong>
          </div>
          <pre>Internet
  |
Nginx + TLS
  |-- shellr.net         -> Portfolio frontdoor
  |-- dma.shellr.net     -> DMA
  |-- grafana.shellr.net -> Grafana (protected)
  |-- status.shellr.net  -> Public status page
  |
Docker networks
  |-- frontend
  |-- backend
  |-- monitoring</pre>
        </div>

        <div class="slice-grid">
          <?php foreach ($platformSlices as $slice): ?>
          <article class="slice-card" data-reveal>
            <h3><?= e($slice['title']) ?></h3>
            <p><?= e($slice['body']) ?></p>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="working-style">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Technologies & Working Style</p>
        <h2>Technology only matters when it improves operations.</h2>
      </div>

      <div class="work-grid">
        <article class="work-panel" data-reveal>
          <h3>Technical Focus</h3>
          <ul>
            <li>Linux administration, host hardening, and service operations</li>
            <li>Docker Engine and Compose for reproducible runtime models</li>
            <li>Nginx, HTTPS, and subdomain routing for clean platform boundaries</li>
            <li>GitHub Actions, SSH delivery, and health-gated deployment logic</li>
            <li>Prometheus, Grafana, Uptime Kuma, Loki, Promtail, and cAdvisor for visibility</li>
          </ul>
        </article>

        <article class="work-panel" data-reveal>
          <h3>Working Style</h3>
          <ul>
            <?php foreach ($workStyle as $item): ?>
            <li><?= e($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </article>
      </div>
    </section>

    <section class="section" id="links">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Live Links / System Access</p>
        <h2>Direct entry points into the platform, live workloads, and operational views.</h2>
      </div>

      <div class="link-grid">
        <?php foreach ($liveLinks as $link): ?>
        <a class="link-card" href="<?= e($link['href']) ?>" data-reveal>
          <span><?= e($link['label']) ?></span>
          <strong><?= e(host_label($link['href'])) ?></strong>
          <p><?= e($link['meta']) ?></p>
        </a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section cta-section">
      <div class="cta-panel" data-reveal>
        <p class="eyebrow">Contact / GitHub / Documentation</p>
        <h2>The technical path is the clearest path.</h2>
        <p>
          For architecture and operational decisions, start with docs.shellr.net. For the public health
          view, use status.shellr.net. For repository structure and implementation details, use GitHub.
          If you want to get in touch, write to <a class="inline-link" href="mailto:<?= e($site['contact_email']) ?>"><?= e($site['contact_email']) ?></a>.
        </p>
        <div class="hero-actions">
          <a class="button primary" href="<?= e($site['docs_url'] . '/architecture.html') ?>">Open Architecture</a>
          <a class="button secondary" href="<?= e($site['status_url']) ?>">Check System Status</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <p>Marlin Scheler &middot; Junior DevOps / System Engineer &middot; Linux, containers, platform operations, observability</p>
  </footer>

  <script src="/assets/app.js"></script>
</body>
</html>
