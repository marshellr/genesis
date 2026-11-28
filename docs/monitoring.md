# Monitoring

## Current roles

- Prometheus collects host, container and service metrics from Node Exporter, cAdvisor and configured endpoints.
- Grafana provides authenticated metrics and log views. Grafana Alloy forwards logs to Loki, with a 72-hour retention period.
- The internal dashboard shows service checks, resource utilisation, backup evidence, runtime image-scan counts and operator-visible warnings.
- Uptime Kuma remains in service during the comparison period. Retirement requires the operator's review of that comparison; it is not treated as already completed.

No push, email or other alert delivery is configured. No external notifying availability monitor is configured. Host, power or Internet failure can also make the internal dashboard unavailable. A failure may remain unnoticed until the next operator check; this is an accepted operating trade-off.

## Visible state and thresholds

Local and offsite backup success are evaluated separately. A recent local dump does not establish offsite success, and application offsite backups do not establish hypervisor archive coverage. Missing evidence remains explicitly unknown.

The backup freshness limit is 25 hours. Resource defaults are 90 percent host RAM, 80 percent thinpool utilisation, and 85 percent hard-disk or guest-filesystem utilisation. Guest usage at 95 percent is critical. Guest filesystem data is read using the existing hypervisor audit access; unavailable measurements remain unknown.

Most warnings need two consecutive samples to enter or recover, with a one-minute sampling interval. The API-outage threshold is five minutes. Stale-sampler warnings remain time-dependent so that a stopped sampler cannot hide its own failure. Container-process checks are labelled separately from complete application-function checks.

Actual running images are checked against active Compose definitions and scanned at least weekly. Raw reports remain private; the dashboard receives aggregate counts, completeness and timestamps. Fixable critical findings take priority for remediation. Fixable HIGH findings are prioritised ahead of routine unfixed HIGH review. Accepted unfixed package risks retain a dated review, rather than suppressing future fixes.

## Grafana policy and password state

Grafana owns its nonce-based CSP and bootstrap scripts. Nginx retains Basic Auth and the remaining proxy headers without adding a second CSP, X-Frame-Options or X-Content-Type-Options. Deployment checks exercise both the unauthenticated challenge and the authenticated origin response, including fresh matching nonces and duplicate-header rejection. The probe reads a protected host credential and never publishes it.

The Grafana administrator-password setting initialises an empty database; it does not reset existing users. Password changes remain database state. The separate nginx password file is operator-local state and is preserved during deployments and rollbacks.

## Incident review

Compare public requests with origin responses, check service and resource evidence, and correlate relevant short-retention logs. Check the age and completeness of evidence before treating a previous success as current. Recovery is confirmed by fresh successful checks; a stored historical success is not a current availability guarantee.
