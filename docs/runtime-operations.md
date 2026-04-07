# Runtime Operations Notes

## Pruefung

```bash
docker --version
docker compose version
docker info
sudo systemctl status docker --no-pager
sudo systemctl status containerd --no-pager
```

## Netzwerke

```bash
docker network ls
docker network inspect genesis_frontend
docker network inspect genesis_backend
docker network inspect genesis_monitoring
```

## Disk Management

```bash
docker system df
df -h
sudo du -sh /var/lib/docker
sudo journalctl --disk-usage
```

## Sichere Routine

- Nur benoetigte Images behalten
- Alte Images regelmaessig entfernen
- Keine ungebundenen Host-Port-Mappings fuer interne Services
- Monitoring- und Log-Retention spaeter klein konfigurieren
- Backups nicht dauerhaft lokal ansammeln lassen

## Vorsicht

Bei Docker koennen veroeffentlichte Container-Ports UFW umgehen. Spaeter nur oeffentlich gewollte Ports an den Host binden.
