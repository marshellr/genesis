# Projects

## Overview

The project set behind `shellr.net` is not just a list of code exercises. It combines live platform work, legacy workload integration, reporting automation, migration and hardening work, and current security automation.

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

Reference reporting workflow for access and usage patterns derived from webserver logs.

### Focus

- sanitised AWStats configuration and cron-ready generator
- explicit access-log source required before production activation
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


## Current security automation work: vulnerability relevance

Current automation work centered on vulnerability ingestion and asset-relevance matching.

### Focus

- ENISA EUVD API as upstream data source
- comparison with asset inventory data
- CVSS, KEV, and EPSS enrichment
- reduction of manual triage through repeatable automation
