#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="/projects/genesis"
COMPOSE_ENV="$PROJECT_ROOT/infra/compose/.env"
DUMP_FILE="$PROJECT_ROOT/dma/backup_db.sql"

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

if [[ ! -f "$COMPOSE_ENV" ]]; then
  echo "Fehlende Compose-Env: $COMPOSE_ENV" >&2
  exit 1
fi

if [[ ! -f "$DUMP_FILE" ]]; then
  echo "Fehlender DMA-Dump: $DUMP_FILE" >&2
  exit 1
fi

COMPOSE_PROJECT_NAME="$(read_env_value COMPOSE_PROJECT_NAME)"
DB_ROOT_PASSWORD="$(read_env_value DB_ROOT_PASSWORD)"
DMA_DB_NAME="$(read_env_value DMA_DB_NAME)"
DMA_DB_USER="$(read_env_value DMA_DB_USER)"
DMA_DB_PASSWORD="$(read_env_value DMA_DB_PASSWORD)"
DB_CONTAINER="${COMPOSE_PROJECT_NAME}-db"

docker exec -i -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_CONTAINER" mariadb -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DMA_DB_NAME}\`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DMA_DB_USER}'@'%' IDENTIFIED BY '${DMA_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DMA_DB_NAME}\`.* TO '${DMA_DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

docker exec -i -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_CONTAINER" mariadb -uroot "$DMA_DB_NAME" < "$DUMP_FILE"
