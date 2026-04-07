# Monitoring

## Components

- Prometheus
- Grafana
- Node Exporter
- cAdvisor
- Uptime Kuma

## Goals

- host visibility
- container and service visibility
- deployment verification
- basic uptime checks

## Design choices

- one Grafana instance for metrics and logs
- cAdvisor for container-level runtime visibility
- no additional exporters unless they solve a concrete problem
- retention sized for a small single-VM host
