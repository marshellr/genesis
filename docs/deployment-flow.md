# Deployment Flow

1. A push to main triggers GitHub Actions. It validates Compose and the application, builds the app, and publishes a versioned bundle with SHA-256 checksums.
2. The VM polls GitHub using a read-only credential and conditional requests. It checks the release identity and checksums, stages the files, and takes a local database backup.
3. Docker Compose builds and updates the services. Nginx validates and reloads its configuration before origin readiness, public routing, and container health are checked.
4. When the host checks pass, the VM writes a confirmation containing the deployed commit and verification time. GitHub Actions polls the public /deployed.json endpoint for up to 15 minutes.
5. If deployment verification fails, the host restores the previous release. Its confirmation stays in place, and the host records the failed release and waits for a newer one.

The VM initiates the download. GitHub needs HTTPS access to the confirmation endpoint.

## Documentation publishing

The Genesis docs workflow validates the static sources. The publish script syncs docs/site to the separate GitHub Pages repository, whose Pages workflow publishes the site.
