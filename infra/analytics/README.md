# AWStats Reference Workflow

This directory contains a reviewable AWStats setup for a small self-hosted platform.
It is not enabled in the current production runtime because Nginx access logs are not mounted as a host-level reporting input.

Before activation, choose a deliberate log source, set the variables in the cron environment, and validate that the source does not expose sensitive request data beyond the required retention period.

Files:

- `awstats.shellr.conf.example`: sanitised AWStats site configuration.
- `generate-report.sh`: cron-ready static report generator.
