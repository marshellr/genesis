# Projects

## Overview

The project set behind `shellr.net` is not just a list of code exercises. It combines live platform work, legacy workload integration, reporting automation, migration and hardening work, and planned security automation.

## Genesis / self-hosted platform

Current platform layer built on one Hetzner VM with explicit runtime boundaries, documented deployment, monitoring, logging, and recovery.

### Focus

- public entrypoints routed through Nginx
- Compose-managed service separation on one host
- bounded observability and backup strategy
- operational documentation and rollback paths

## DMA statistics module

API-based dashboard for match and performance data, implemented as a separate PHP runtime on `dma.shellr.net`.

### Focus

- aggregation of JSON-based external data
- isolated runtime, database scope, and health behavior
- live workload that can be monitored and operated on the wider platform

## Automated web analytics with AWStats

Static reporting workflow for access and usage patterns derived from webserver logs.

### Focus

- automated generation of reports instead of manual export
- structured analysis of log data over time
- lightweight reporting model that fits a single-VM environment

## Web platform migration and hardening

Migration of web workloads onto Linux-based infrastructure with stronger security and more predictable operations.

### Focus

- controlled migration instead of one-shot changes
- host hardening, access control, and stable reachability
- separation of public surfaces and runtime responsibilities

## Inventory tracking application

Web-based inventory application used as a practical CRUD workload inside the wider platform model.

### Focus

- PHP and relational database stack
- structured display and management of stock data
- useful proving ground for deployment, monitoring, and backup behavior

## Private web projects and hosting

Several self-hosted websites operated on privately managed Linux infrastructure before and alongside the current platform shape.

### Focus

- setup and maintenance of multiple web applications
- Apache and HTTPS configuration
- deployment and operational upkeep on Linux systems

## Planned final project: automated vulnerability management

Planned completion project centered on vulnerability ingestion and relevance matching.

### Focus

- ENISA EUVD API as upstream data source
- comparison with asset inventory data
- reduction of manual triage through automation
