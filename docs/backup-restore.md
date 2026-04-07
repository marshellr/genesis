# Backup and Restore

## Scope

Backups are centered on `/projects/genesis`.

The current backup design captures:

- MariaDB data as a logical dump
- project files as a compressed archive
- staged SQLite backups for Grafana and Uptime Kuma inside the file archive
- timestamped artifacts with local rotation

## Why Logical Backups

The MariaDB data is restored from SQL dumps, not raw volume snapshots. That keeps the procedure easier to inspect and safer to restore on a small single-host setup.

## Backup Artifacts

- database dumps under `/projects/genesis/infra/backup/db`
- file archives under `/projects/genesis/infra/backup/files`
- backup logs under `/projects/genesis/infra/backup/logs`
- restore extraction targets under `/projects/genesis/infra/backup/restore`

## Explicit Exclusions

To keep disk usage predictable on a 40 GB VM, the file archive intentionally excludes:

- `/projects/genesis/infra/backup`
- `/projects/genesis/infra/docker/volumes` as a live tree
- selected runtime state is added back in from staged backups for Grafana and Uptime Kuma
- `/projects/genesis/docs/publish`
- `/projects/genesis/releases`

## Rotation

- retention is day-based
- old artifacts are removed by the backup script
- the policy is intentionally small to protect disk on a 40 GB VM

## Restore Model

- database restore uses a dedicated restore script
- file restore uses a dedicated extraction script and does not overwrite `/projects/genesis` by default
- recovery remains procedural and transparent, not hidden in backup tooling
