<?php
declare(strict_types=1);

$site = require __DIR__ . '/../config/site.php';

$heroSignals = [
    '4 vCPU / 8 GB Hetzner VM',
    'Docker Compose runtime',
    'Nginx + Let\'s Encrypt',
    'Prometheus / Grafana / Loki',
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
        'lead' => 'A small platform with real operational logic on a single VM.',
        'summary' => 'Genesis is the platform project behind shellr.net. The goal is not a large stack for its own sake, but a clean system with routing, HTTPS, deployment, monitoring, logging, and backup on limited resources. The important part is not only that services start, but that they can be operated, reviewed, and documented properly.',
        'stack' => ['Linux', 'Docker Compose', 'Nginx', 'GitHub Actions'],
        'href' => $site['docs_url'],
        'label' => 'Open platform docs',
    ],
    [
        'title' => 'DMA Statistics Module',
        'tag' => 'live project',
        'lead' => 'An existing web application moved into a controlled platform context.',
        'summary' => 'DMA shows how a legacy PHP application can be moved into a cleaner runtime model. That includes its own container, its own subdomain, clearer database boundaries, defined healthchecks, and a reverse-proxy setup that remains maintainable after the first successful deployment.',
        'stack' => ['PHP', 'MariaDB', 'Docker', 'Subdomain routing'],
        'href' => $site['dma_url'],
        'label' => 'Open DMA live',
    ],
    [
        'title' => 'AWStats Reporting / Automation',
        'tag' => 'reporting',
        'lead' => 'Reporting that turns logs into usable operational visibility.',
        'summary' => 'In reporting work, the point is not only to collect data, but to turn it into something operationally useful. AWStats and related automation represent the part of my work where analysis, preparation, and repeatable workflows come together without overloading the system.',
        'stack' => ['AWStats', 'Shell scripts', 'Cron', 'Reporting'],
        'href' => $site['docs_url'] . '/projects.html',
        'label' => 'Open project overview',
    ],
    [
        'title' => 'Web Platform Migration & Hardening',
        'tag' => 'migration',
        'lead' => 'Taking over existing systems and bringing them onto a safer operational footing.',
        'summary' => 'Real infrastructure work rarely starts on a blank machine. This area covers host hardening, SSH security, firewalling, reverse-proxy migration, TLS, cleaner deployment paths, and the controlled cleanup of technical debt that would otherwise stay invisible until something breaks.',
        'stack' => ['Ubuntu', 'SSH', 'UFW', 'Fail2ban'],
        'href' => $site['docs_url'] . '/architecture.html',
        'label' => 'View architecture',
    ],
    [
        'title' => 'Inventory Tracking Application',
        'tag' => 'application',
        'lead' => 'A lean CRUD workload used as an operationally useful application layer.',
        'summary' => 'The inventory application is intentionally simple, but that is exactly what makes it useful in a platform context. It represents a typical web workload with database access, CRUD behaviour, and healthchecks, which makes deployment, monitoring, and recovery decisions visible under realistic conditions.',
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
        'body' => 'Prometheus, Grafana, Uptime Kuma, Loki, and Promtail are sized for the host instead of competing against it.',
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
        'label' => 'Documentation',
        'href' => $site['docs_url'],
        'meta' => 'Technical documentation, project overview, architecture, monitoring, logging, and backup.',
    ],
    [
        'label' => 'DMA',
        'href' => $site['dma_url'],
        'meta' => 'A live application running as its own platform surface.',
    ],
    [
        'label' => 'Status',
        'href' => $site['status_url'],
        'meta' => 'Public availability view for the main platform surfaces.',
    ],
    [
        'label' => 'Contact',
        'href' => 'mailto:' . $site['contact_email'],
        'meta' => $site['contact_email'],
    ],
];

if ($site['github_url'] !== '') {
    $liveLinks[] = [
        'label' => 'GitHub',
        'href' => $site['github_url'],
        'meta' => 'Code, changes, and technical source material.',
    ];
}

if ($site['grafana_url'] !== '') {
    $liveLinks[] = [
        'label' => 'Grafana',
        'href' => $site['grafana_url'],
        'meta' => 'Protected operational access to the monitoring surface.',
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
  <meta name="description" content="Personal engineer website of Marlin Scheler with a focus on Linux, Docker, deployment, monitoring, infrastructure, and production-like single-VM platforms.">
  <link rel="icon" type="image/png" href="/assets/favi.png">
  <link rel="apple-touch-icon" href="/assets/favi.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
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

  <main>
    <section class="hero" id="top">
      <div class="hero-copy" data-reveal>
        <p class="eyebrow">Marlin Scheler &middot; Junior DevOps / System Engineer</p>
        <h1>Build systems that can actually be operated.</h1>
        <p class="hero-summary">
          I am a systems integration apprentice focused on Linux-based platforms around Docker,
          deployment, monitoring, reverse proxies, and web operations. shellr.net is not a placeholder
          site. It is the frontdoor to a running platform with real services, explicit runtime
          boundaries, and technical documentation.
        </p>

        <div class="hero-signal-row">
          <?php foreach ($heroSignals as $signal): ?>
          <span><?= e($signal) ?></span>
          <?php endforeach; ?>
        </div>

        <div class="hero-actions">
          <a class="button primary" href="<?= e($site['docs_url']) ?>">Open documentation</a>
          <a class="button secondary" href="#projects">Project overview</a>
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
            <p>Personal landing page and technical starting point for the platform.</p>
          </article>
          <article>
            <span>application</span>
            <strong>dma.shellr.net</strong>
            <p>Concrete live project with its own runtime and routing boundary.</p>
          </article>
          <article>
            <span>monitoring</span>
            <strong>status.shellr.net</strong>
            <p>Public status view for the main platform surfaces.</p>
          </article>
          <article>
            <span>documentation</span>
            <strong>docs.shellr.net</strong>
            <p>Technical docs, project overview, and operational notes on GitHub Pages.</p>
          </article>
        </div>
      </aside>
    </section>

    <section class="section profile-section" id="profile">
      <div class="section-copy" data-reveal>
        <p class="eyebrow">Profile</p>
        <h2>Focused on infrastructure, operations, and technical clarity.</h2>
        <p>
          I position myself as a junior DevOps / system engineer with a focus on Linux, container
          runtimes, deployment, monitoring, and the stable operation of web platforms. What matters to
          me is not only how applications are built, but how they are delivered, observed, secured, and
          documented.
        </p>
        <p>
          This platform is intentionally small. On a single VM, technical decisions become visible:
          routing has to stay clean, logs have to stay bounded, backups have to stay readable, and
          additional complexity has to justify itself.
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
        <h2>Projects shaped by real operational constraints, not isolated demo logic.</h2>
      </div>

      <div class="project-grid">
        <?php foreach ($featuredProjects as $project): ?>
        <article class="project-card" data-reveal>
          <div class="project-card-top">
            <span class="project-tag"><?= e($project['tag']) ?></span>
            <h3><?= e($project['title']) ?></h3>
          </div>
          <p class="project-lead"><?= e($project['lead']) ?></p>
          <p><?= e($project['summary']) ?></p>
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
  |-- status.shellr.net  -> Uptime Kuma
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
            <li>Deployment with GitHub Actions, SSH, and health-gated release logic</li>
            <li>Prometheus, Grafana, Uptime Kuma, Loki, and Promtail for platform visibility</li>
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
        <h2>Direct entry points into the platform, live projects, and operational views.</h2>
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
        <h2>The best starting point is the technical view of the system.</h2>
        <p>
          If you want to see how I structure and operate systems, docs.shellr.net is the right place
          to start. It contains the technical view of architecture, deployment, monitoring, logging,
          backup, and lessons learned. For live workloads, the direct path is dma.shellr.net. For
          availability, it is status.shellr.net. For code and change history, it is GitHub.
        </p>
        <div class="hero-actions">
          <a class="button primary" href="<?= e($site['docs_url']) ?>">Go to docs.shellr.net</a>
          <a class="button secondary" href="<?= e($site['status_url']) ?>">Check status</a>
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
