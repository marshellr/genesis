# System Maintenance

Ubuntu's existing unattended security-update service is active. Controlled maintenance and post-boot verification scripts remain available in the repository.

The repository's weekly patch-and-reboot timer is not installed or active on the current production host. Its example schedule is not an active operating commitment. Deployments keep host integration disabled and do not install or enable cron jobs, timers or certificate tooling.

An operator-triggered maintenance run needs a current verified backup, a free-space check, valid Compose configuration, service-health checks and a recovery path. A reboot is a separate controlled action. Restore and patch procedures do not replace the image and application verification performed by the release pipeline.

Inspect the actual host unit state and private operations record before scheduling maintenance. Public documentation records the operating policy rather than publishing a complete host-unit inventory.
