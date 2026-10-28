#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

if [[ $# -lt 1 || $# -gt 2 ]]; then
  echo "Usage: $0 /projects/genesis/infra/backup/files/<archive>.tar.gz [--in-place]" >&2
  exit 1
fi

ARCHIVE_FILE="$1"
MODE="${2:-}"
PROJECT_ROOT="/projects/genesis"
RESTORE_ROOT="$PROJECT_ROOT/infra/backup/restore"

if [[ ! -f "$ARCHIVE_FILE" ]]; then
  echo "Archiv nicht gefunden: $ARCHIVE_FILE" >&2
  exit 1
fi

if [[ -n "$MODE" && "$MODE" != "--in-place" ]]; then
  echo "Unbekannter Modus: $MODE" >&2
  exit 1
fi
mkdir -p "$RESTORE_ROOT"

if [[ "$MODE" == "--in-place" ]]; then
  echo "Direkter Restore gesperrt: zuerst ins Staging entpacken, Dienste gezielt stoppen und Daten kontrolliert uebernehmen (docs/backup-restore.md)." >&2
  exit 1

fi

TARGET_DIR="$(mktemp -d "$RESTORE_ROOT/restore-XXXXXXXX")"
echo "Extrahiere Archiv nach $TARGET_DIR"
sudo tar --same-owner -xzf "$ARCHIVE_FILE" -C "$TARGET_DIR"
echo "Pruefpfad: $TARGET_DIR"
echo "Weitere Projekte: $TARGET_DIR/external/"
