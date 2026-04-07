#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 /projects/genesis/infra/backup/db/<dump>.sql.gz" >&2
  exit 1
fi

DUMP_FILE="$1"
PROJECT_ROOT="/projects/genesis"
COMPOSE_ENV="$PROJECT_ROOT/infra/compose/.env"

if [[ ! -f "$DUMP_FILE" ]]; then
  echo "Dump nicht gefunden: $DUMP_FILE" >&2
  exit 1
fi

read_env_value() {
  local key="$1"
  local value
  value="$(grep -E "^${key}=" "$COMPOSE_ENV" | tail -n1 | cut -d= -f2- || true)"
  if [[ -z "$value" ]]; then
    echo "Fehlende Variable in $COMPOSE_ENV: $key" >&2
    exit 1
  fi
  printf '%s' "$value"
}

COMPOSE_PROJECT_NAME="$(read_env_value COMPOSE_PROJECT_NAME)"
DB_ROOT_PASSWORD="$(read_env_value DB_ROOT_PASSWORD)"
DB_CONTAINER="${COMPOSE_PROJECT_NAME}-db"

if [[ "$(docker inspect -f '{{.State.Running}}' "$DB_CONTAINER" 2>/dev/null || true)" != "true" ]]; then
  echo "DB-Container laeuft nicht: $DB_CONTAINER" >&2
  exit 1
fi

echo "Stelle Dump wieder her: $DUMP_FILE"
gunzip -c "$DUMP_FILE" | docker exec -i -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_CONTAINER" mariadb -uroot

echo "Restore abgeschlossen"
