#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="/projects/genesis"
CERTBOT_WWW="$ROOT_DIR/infra/nginx/certbot/www"
CERTBOT_CONF="$ROOT_DIR/infra/nginx/certbot/conf"
COMPOSE_FILE="$ROOT_DIR/infra/compose/docker-compose.yml"
ENV_FILE="$ROOT_DIR/infra/compose/.env"

docker run --rm \
  -v "$CERTBOT_WWW:/var/www/certbot" \
  -v "$CERTBOT_CONF:/etc/letsencrypt" \
  certbot/certbot@sha256:f70ad0adbb7e117f0fe42a63c553f28ea451edabc0148757b6efcd9735acaa20 renew \
  --webroot \
  -w /var/www/certbot \
  --non-interactive

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T nginx nginx -t
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T nginx nginx -s reload
