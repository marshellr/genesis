# Logging

## Components

- Loki
- Promtail
- Grafana

## Goals

- short-term incident visibility
- container log aggregation without ELK overhead
- bounded disk growth

## Design choices

- filesystem-backed Loki
- short retention
- Docker log rotation at the container level
- no attempt to build a long-term log archive on the VM
