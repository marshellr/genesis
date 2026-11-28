# Docker Platform Baseline

The platform uses Docker Engine, the Compose plugin and containerd. Frontend, database and monitoring networks separate service responsibilities. Stateful data has dedicated storage and is not mixed with application source.

Application images are built in CI for linux/amd64 and are deployed by immutable registry digest. The current host uses Docker's containerd image store; CI publishing uses the classic store. Verification accounts for configuration and manifest identities and confirms the actual running image rather than assuming both stores report the same identifier.

Container logging is size-limited and rotated. The standard application setting is 10 MiB per file with three files; Loki retains collected logs for 72 hours. Exact private-project overrides remain in the private operations record.

Docker-published ports can bypass assumptions based only on host firewall tools. Expose only required ingress, keep internal backends private, and verify router, host, hypervisor and external reachability separately.
