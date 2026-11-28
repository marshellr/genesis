# Logging

Grafana Alloy forwards container logs to filesystem-backed Loki; Grafana provides the internal log view. Loki retains logs for 72 hours. Application Docker logs use 10 MiB files with three-file rotation. This bounds local log growth without creating a long-term archive.

The ingress access-log format stores shortened IPv4 and IPv6 addresses for the peer and forwarded-address fields. Logs support operational diagnosis; no website analytics service is enabled. Raw monitoring and log-store data is excluded from offsite application backups.

Correlate a specific failure with current service and resource evidence. Inspect log timestamps and source completeness before drawing an availability conclusion. Credentials and unfiltered operational logs are not published in documentation.
