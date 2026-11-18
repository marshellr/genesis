# Container hardening

Application images copy source as root-owned files. Directories are mode 0755 and files are mode 0644. The PHP services use a read-only root filesystem. Apache runtime directories, PHP sessions and DMA caches use dedicated temporary filesystems. The application source has no writable mount.

Both PHP services drop all capabilities and add only SETUID, SETGID and NET_BIND_SERVICE for Apache startup and worker privileges. The existing no-new-privileges setting remains enabled.

Base and platform images use version tags with immutable digests. Dependabot checks the PHP Dockerfiles weekly. To update a platform image, select its supported release, inspect the registry digest, update the version and digest together, and validate Compose and service health. Database and monitoring upgrades require a backup and a tested rollback path.

CI scans the complete Git history with Gitleaks and scans both built PHP images with Trivy. Critical findings are counted in the job summary and retained as short-lived vulnerability reports. Review the affected package, distribution advisory and fixed version before changing the image. A scanner finding may refer to code that the distribution does not ship. Keep that assessment explicit in the private audit record.

The portfolio generates asset query parameters from each file's SHA-256. Content changes produce a new URL without editing Nginx configuration.
