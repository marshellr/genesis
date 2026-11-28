# Runtime Operations

## Release review

Compare the deployment receipt, release commit and scanned image manifest with running image identities. Read current container health, public routing and origin readiness before calling a release successful. Keep failed-deployment and rollback evidence.

## Resource and evidence review

Review thinpool and hard-disk utilisation separately from provisioned disk promises. Guest filesystem usage is measured independently. Inspect the timestamp and source status of each local and external backup stream; missing evidence is unknown.

Retain the previous recoverable release and avoid unreviewed image cleanup during an incident. Restore to private staging and isolated databases before changing production state. Keep credentials and full inventories in the private operations record.

## Accepted operating limit

There is no alert delivery and no external notifying monitor. Host, power or Internet failure can also remove access to the internal dashboard. Detection depends on the next operator check.
