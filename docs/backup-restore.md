# Backup and Restore

## Logical data and encrypted offsite snapshots

The pre-backup hook creates a logical MariaDB dump and consistent SQLite copies. The pre-deploy hook and nightly offsite backup share a lock. A failed pre-deploy hook prevents deployment.

The nightly Restic job encrypts application files, configuration and prepared logical database copies before offsite storage. Raw MariaDB backing data, duplicate local archive sets and short-lived Prometheus, Loki and Alloy stores are excluded. MariaDB recovery uses its logical dump.

Local archive tooling retains seven complete sets with checksums and manifests. Partial artifacts are not promoted. It reserves at least 2 GiB beyond its estimated working data. Pre-deployment dumps, local archives, hypervisor backups and offsite snapshots have distinct purposes and success evidence.

## Evidence freshness

The internal dashboard evaluates local and offsite success independently with a 25-hour limit. Failed or unavailable evidence preserves the last known success and marks its uncertainty. A missing external hypervisor-archive success is not inferred from successful application backups. These state checks add no backup schedule or notification channel.

## Restore exercise on 27 November 2026

The selected offsite snapshot was 19.66 hours old. Logical SQL and SQLite copies were recovered into private staging. The SQL was imported into a separate MariaDB instance, application tables were checked, and both SQLite copies passed integrity checks.

Both PHP applications were then started on an internal test network without published ports. The portfolio HTTP health response and its database adapter worked against restored data; DMA's health and database queries also succeeded. Only synthetic test credentials were created in the isolated database. Production data and credentials were not changed. Test containers, their data and the staging directory were removed afterwards.

Offsite retrieval, logical restore and the application checks took 19.6 seconds on an already available Docker host with the required images. This measurement does not include replacement hardware or VM provisioning, certificate recovery or public tunnel restoration.

## Recovery objectives

The confirmed recovery point objective is 25 hours. The recovery time objective is 2 hours for full service restoration to an available replacement VM, assuming working hardware, network and access to backup credentials and keys. The measured exercise covers logical data and application startup; it is not a complete replacement-infrastructure timing result.

Stage restores away from active databases. Verify artifact checksums and database integrity, restore configuration through the private operations procedure, then check image identity, application health and public routing before declaring recovery complete.
