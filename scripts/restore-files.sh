#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

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

mkdir -p "$RESTORE_ROOT"

if [[ "$MODE" == "--in-place" ]]; then
  TARGET_DIR="$PROJECT_ROOT"
  echo "Stelle Archiv direkt nach $PROJECT_ROOT wieder her"
  sudo tar -xzf "$ARCHIVE_FILE" -C "$TARGET_DIR"
  echo "Restore abgeschlossen"
  exit 0
fi

BASENAME="$(basename "$ARCHIVE_FILE" .tar.gz)"
TARGET_DIR="$RESTORE_ROOT/$BASENAME"

rm -rf "$TARGET_DIR"
mkdir -p "$TARGET_DIR"

echo "Extrahiere Archiv nach $TARGET_DIR"
tar -xzf "$ARCHIVE_FILE" -C "$TARGET_DIR"
echo "Pruefpfad: $TARGET_DIR"
echo "Fuer ein In-Place-Restore nutze optional: $0 $ARCHIVE_FILE --in-place"
