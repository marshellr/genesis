# Projects

## Genesis

Status: Running in production.

The public portfolio runs on a Docker Compose platform hosted on operator-owned hardware. Cloudflare Tunnel provides public ingress, with separate frontend, database and monitoring networks. CI-scanned registry images, host verification, rollback and encrypted offsite restore are part of the operating model.

## DMA

Status: Read-only public showcase.

The public hostname serves static project context. Interactive PHP sources stay outside the served web root and have no published host port. Database checks and bounded cache prefetch use CLI entry points.

## EVC — ENISA Vulnerability Check

Status: Conceptual overview.

### Problem

Security advisories only become actionable once they are matched to the systems actually in use.

### Implementation

The workflow retrieves vulnerability records from ENISA EUVD, normalises product and version information, and compares it with an asset inventory. Relevant matches are enriched with CVSS severity, EPSS exploitation probability and evidence of known exploitation.

A structured report preserves the matching evidence and prioritisation criteria for human review. The approach combines API integration, version matching, vulnerability enrichment and repeatable report generation.

### Verification and result

A traceable review process connects an advisory to a potentially affected system and makes the reasons for its priority visible. Product and version matches remain subject to validation.
