# Backup and Restore

## Scope

Backups are centered on `/projects/genesis`.

The current backup design captures:

- both application databases (`genesis_inventory` and `dma`) in one logical MariaDB dump
- project files as a compressed archive
- integrity-checked SQLite snapshots for Grafana and Uptime Kuma inside the file archive
- optional host-local project snapshots under `external/`
- timestamped, checksummed artifacts with manifests and count-based local retention

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
- selected runtime state is added back in from staged SQLite snapshots
- `/projects/genesis/docs/publish`
- `/projects/genesis/releases`

## Rotation

- the newest 7 complete local backup sets are retained
- incomplete and unreferenced artifacts are removed automatically
- restore work directories older than 7 days are removed
- the policy is intentionally small to protect disk on a 40 GB VM

Every run reserves at least 2 GiB of free space in addition to the estimated archive and staged runtime-state size. Outputs remain `*.partial` until the dump and file archive are complete.

## Restore Model

- database restore uses a dedicated restore script
- the combined SQL dump restores both application databases
- file restore uses a dedicated extraction script and does not overwrite `/projects/genesis` by default
- recovery remains procedural and transparent, not hidden in backup tooling

## Local policy (2026-10-28)

Offsite storage is deliberately disabled at the operator's request. Daily execution is at 03:17 (host timezone), retaining seven complete sets. Archives and dumps are mode 0600. SQLite is snapshotted through the online backup API; every staged SQLite database must pass `PRAGMA integrity_check` before publication. Rebuildable `.venv`, `node_modules`, Python caches and build outputs are excluded. The Genesis transient runtime and restic cache directories are excluded too.

## Staged recovery

```bash
/projects/genesis/scripts/restore-files.sh /projects/genesis/infra/backup/files/<archive>.tar.gz
```

The command creates a fresh private staging directory and preserves original numeric owners through sudo. `--in-place` is rejected to prevent overwriting active SQLite databases. Verify the manifest's SHA-256 lines with `sha256sum -c` before recovery. Inspect the staged databases with `sudo sqlite3 <staged-database> 'PRAGMA integrity_check;'`.

Host-local projects and their recovery instructions live under `infra/local/`. These files and `infra/nginx/conf.d/local/` are excluded from Git but included in local backups. Deployment and rollback preserve `local/` directories. Optional `backup.sh` and `health.sh` hooks extend backup and health checks without publishing private project configuration. Only trusted operator-maintained scripts belong there.
