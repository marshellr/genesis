# Deployment Flow

## Normal release path

1. A push to main triggers GitHub Actions. Compose, shell, PHP and regression checks run before publication.
2. CI builds each PHP image once for linux/amd64, with provenance and SBOM attestations disabled. It embeds the release commit in the application image before the build.
3. Trivy writes complete vulnerability reports. A separate gate blocks relevant, fixable CRITICAL findings; HIGH findings remain visible for prioritisation. Reviewed package exceptions have explicit expiry dates, and invalid or expired reviews fail the gate. Reports are retained even when the gate fails.
4. The scanned images are published to GHCR. The release manifest records each immutable registry digest, configuration digest, filesystem layers, commit and report checksum. The release bundle and its checksum remain separate from the images.
5. The host polls GitHub with a read-only credential and conditional requests. It verifies the release, creates database dumps and consistent SQLite copies under the shared backup lock, and backs up the current release files.
6. Before changing live files, the host pulls both registry digests and checks their identity. It gives them the existing local Compose tags without rebuilding them. A pull or identity failure never triggers an automatic host build.
7. Compose updates the services. Verification compares the running images with the scanned manifest, tests and reloads Nginx, waits for origin readiness, and checks routing, security headers, the DMA boundary and authenticated Grafana nonce handling.
8. Only successful host verification updates the public deployment receipt. GitHub waits up to 15 minutes for that receipt. Failure preserves the previous receipt and records the failed release; rollback restores the previous application state and deployment markers.

The host initiates downloads. GitHub verifies the public receipt over HTTPS and needs no SSH access to the host. Deployment does not install host cron jobs, timers or certificate tooling.

## Explicit emergency host build

GENESIS_EMERGENCY_HOST_BUILD=1 selects the former host-build path. It is disabled by default and bypasses the normal CI-image provenance check. GENESIS_INSTALL_HOST_INTEGRATION must remain 0.

An operator-invoked emergency deployment still requires the established full backup, retained previous images, service verification and reconciliation of deployment state using the private operations record. Record the resulting image identities as an emergency build; do not describe them as the scanned CI images. Return to a verified registry-backed release through the pull deployer for normal operation.

## Evidence and limits

On 27 November 2026, the registry-backed release bbd5594 passed the complete host and public verification. The running application images matched the scanned manifest despite different Docker image stores on the runner and host. Earlier rejected deployments retained the previous confirmation and failure evidence.

The security gate was also tested with an intentionally fixable critical finding: publication was skipped and the report artifact remained available. Passing the gate does not erase unfixed findings; those retain their review dates.

## Documentation publishing

The static documentation is checked for local links, assets and fragments before publication to the separate GitHub Pages repository. External HTTPS checks compare the published pages with their source bytes. Public documentation contains architectural results; inventories, credentials and raw operational evidence remain private.
