#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

AWSTATS_CONFIG="${AWSTATS_CONFIG:-/etc/awstats/awstats.shellr.conf}"
OUTPUT_DIR="${AWSTATS_OUTPUT_DIR:-/var/www/awstats/shellr}"

command -v awstats.pl >/dev/null 2>&1 || {
  printf '%s\n' 'awstats.pl is not installed.' >&2
  exit 1
}

install -d -m 0750 "$OUTPUT_DIR"
awstats.pl -config="$(basename "$AWSTATS_CONFIG" .conf | sed 's/^awstats\.//')" -update
awstats.pl -config="$(basename "$AWSTATS_CONFIG" .conf | sed 's/^awstats\.//')" -output -staticlinks > "$OUTPUT_DIR/index.html"
