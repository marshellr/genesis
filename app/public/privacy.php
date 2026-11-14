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
  <meta name="description" content="Privacy policy for websites operated under shellr.net.">
  <link rel="canonical" href="https://shellr.net/privacy.php">
  <link rel="icon" type="image/png" href="/assets/favi.png">
  <link rel="apple-touch-icon" href="/assets/favi.png">
  <link rel="stylesheet" href="/assets/fonts.css">
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
        This policy explains how I process personal data when you visit the websites covered by this policy.
      </p>
      <div class="legal-meta">
        <span>Controller: Marlin Scheler</span>
        <span>Contact: <?= e($site['contact_email']) ?></span>
        <span>Applies to: the websites I operate under shellr.net, except docs.shellr.net (hosted on GitHub Pages)</span>
        <span>Last updated: 13 November 2026</span>
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
        <p class="eyebrow">Hosting</p>
        <h2>Self-hosting and Cloudflare</h2>
        <p>
          I run the websites covered by this policy on my own hardware in Germany. Requests to these
          websites pass through Cloudflare, Inc. Cloudflare provides DNS, TLS, protection against
          attacks and delivery of the websites.
          It processes your IP address and request data, such as the requested URL, request time
          and browser information. The legal basis is Article 6(1)(f) GDPR. My legitimate interest
          is to deliver the website securely and reliably. Cloudflare acts as a data processor under its
          Data Processing Addendum.
        </p>
        <p>
          Cloudflare may process data in the United States. Cloudflare states that it is certified
          under the EU–US Data Privacy Framework. For transfers covered by that certification,
          the European Commission’s adequacy decision applies under Article 45 GDPR. Where that
          decision does not cover a transfer, Cloudflare provides for the EU Standard Contractual
          Clauses under Article 46(2)(c) GDPR in its
          <a class="inline-link" href="https://www.cloudflare.com/cloudflare-customer-dpa/">Data Processing Addendum</a>.
          See <a class="inline-link" href="https://www.cloudflare.com/privacypolicy/">Cloudflare’s Privacy Policy</a>
          for further information.
        </p>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">Server logs</p>
        <h2>Access logs and retention</h2>
        <p>
          The nginx access logs record the time, the site that handled the request,
          the response status, bytes sent and processing time. They store only shortened IP addresses
          for the connection and for addresses forwarded by Cloudflare. For IPv4, the last octet is set
          to zero. For IPv6, only the first 48 bits are retained. The legal basis is Article 6(1)(f) GDPR.
          My legitimate interest is to troubleshoot faults and protect the website.
        </p>
        <p>
          Docker rotates the access logs by size at 10 MiB per file and retains up to three files per container.
          Logs sent to Loki have a 72-hour retention period, with a configured two-hour delay before
          physical deletion of expired log data.
        </p>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">Website use</p>
        <h2>Cookies and analytics</h2>
        <p>
          The websites covered by this policy set no cookies and use no visitor analytics service.
        </p>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">Contact requests</p>
        <h2>Email communication</h2>
        <p>
          If you contact me by email, I use the information you provide to handle your request.
          The legal basis is Article 6(1)(f) GDPR. My legitimate interest is to answer enquiries.
          I retain the information for as long as needed to handle the request and meet any legal
          retention obligations. Email sent to the contact address is received and stored in Germany
          by IONOS SE, which acts as a data processor.
        </p>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">External services</p>
        <h2>Documentation and external links</h2>
        <p>
          The linked documentation at <code>docs.shellr.net</code> is hosted on GitHub Pages.
          When you follow a link to the documentation or another external site, the provider
          of that site processes your request under its own privacy policy.
        </p>
      </section>

      <section class="legal-panel">
        <p class="eyebrow">Your rights</p>
        <h2>Your data protection rights</h2>
        <p>
          Under the GDPR, you can request access to your personal data, rectification, erasure,
          restriction of processing and, where applicable, data portability. You can object
          to processing based on legitimate interests on grounds relating to your particular
          situation. You can also lodge a complaint with a data protection supervisory authority.
          The supervisory authority responsible for this website is the
          <a class="inline-link" href="https://www.lda.bayern.de/de/index.html">Bayerisches Landesamt für Datenschutzaufsicht (BayLDA)</a>
          in Ansbach, Germany.
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
