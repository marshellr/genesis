# Container Hardening

## Build and runtime boundary

Both PHP applications use multi-stage builds. Compilers, development packages and kernel headers stay in the build stage. The runtime contains Apache, PHP, required extensions and their linked libraries. Runtime checks verify extensions, shared-library resolution, Apache configuration, and the absence of build packages. SQLite and XML libraries remain where PHP actually requires them.

Source files are root-owned inside the images, with directories 0755 and files 0644. PHP services have a read-only root filesystem. Dedicated temporary filesystems hold writable runtime state. Both services drop all capabilities, adding only SETUID, SETGID and NET_BIND_SERVICE for Apache startup. No-new-privileges remains enabled.

## Image identity and security gates

Base and platform images are pinned by digest. CI builds and scans application images once, publishes immutable registry digests and records the scanned identity in the release manifest. The host pulls those digests and checks the running image, configuration identity, filesystem layers and commit before confirming deployment. A matching mutable tag alone is insufficient.

Gitleaks checks repository history. Trivy reports HIGH and CRITICAL findings independently of the release gate. Relevant fixable CRITICAL findings block publication. Exceptions are limited to reviewed package versions, have an owner and expiry, and fail closed when invalid or expired. Accepted unfixed findings remain visible for re-evaluation when a fix appears.

The bounded runtime scan derives its image set from active Compose models, verifies actual running identities and retains reports privately. Aggregate findings are visible in the operator dashboard without alert delivery. A scanner label is reviewed against the distribution advisory and the reachable application code before a package is removed or an exception is introduced.

## Isolation and updates

Frontend, database and monitoring traffic use separate Docker networks. Internal database and monitoring backends do not require generally published host ports. The ingress proxy serves tunnel and LAN traffic; router and firewall boundaries require their own evidence, including an external IPv6 test.

For an update, select a supported version and registry digest, back up affected state, verify Compose references and actual running identities, then check readiness and rollback. Stateful upgrades need a tested data rollback. Full inventories, firewall exceptions, host addresses and credentials belong in the private operations record.
