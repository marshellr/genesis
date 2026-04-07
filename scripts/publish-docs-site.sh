#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

PROJECT_ROOT="${PROJECT_ROOT:-/projects/genesis}"
SOURCE_DIR="$PROJECT_ROOT/docs/site"
PUBLISH_ROOT="$PROJECT_ROOT/docs/publish"
TARGET_REPO_DIR="${TARGET_REPO_DIR:-$PUBLISH_ROOT/marshellr.github.io}"
TARGET_REPO_SSH="${TARGET_REPO_SSH:-git@github.com:marshellr/marshellr.github.io.git}"
TARGET_BRANCH="${TARGET_BRANCH:-main}"

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "Fehlendes Kommando: $1" >&2
    exit 1
  }
}

require_cmd git
require_cmd rsync

if [[ ! -d "$SOURCE_DIR" ]]; then
  echo "Source-Verzeichnis fehlt: $SOURCE_DIR" >&2
  exit 1
fi

mkdir -p "$PUBLISH_ROOT"

if [[ ! -d "$TARGET_REPO_DIR/.git" ]]; then
  git clone "$TARGET_REPO_SSH" "$TARGET_REPO_DIR"
fi

git -C "$TARGET_REPO_DIR" fetch origin "$TARGET_BRANCH"
git -C "$TARGET_REPO_DIR" checkout "$TARGET_BRANCH"
git -C "$TARGET_REPO_DIR" pull --ff-only origin "$TARGET_BRANCH"

rsync -av --delete \
  --exclude ".git" \
  "$SOURCE_DIR"/ \
  "$TARGET_REPO_DIR"/

if git -C "$TARGET_REPO_DIR" diff --quiet && git -C "$TARGET_REPO_DIR" diff --cached --quiet; then
  echo "Keine Aenderungen fuer docs.shellr.net"
  exit 0
fi

git -C "$TARGET_REPO_DIR" add .
git -C "$TARGET_REPO_DIR" commit -m "Publish docs site"
git -C "$TARGET_REPO_DIR" push origin "$TARGET_BRANCH"

echo "Docs-Site wurde nach $TARGET_REPO_SSH gepusht"
