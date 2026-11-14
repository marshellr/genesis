# Monitoring

The monitoring and logging interfaces are available on the internal network.

- Prometheus collects service and host metrics from Node Exporter, cAdvisor, and application endpoints. Alertmanager handles Prometheus alerts.
- Grafana provides authenticated access to metrics and logs.
- Grafana Alloy forwards logs to Loki, which retains them for 72 hours.
- Uptime Kuma checks service availability, including public routing through Cloudflare. The internal status snapshot shows the last successful local backup.

During an incident, compare the public request with an origin probe, inspect Prometheus targets and container health, and use Loki to correlate relevant logs.
