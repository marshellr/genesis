#!/usr/bin/env python3
"""Create an online SQLite snapshot from a read-only source connection."""
import sqlite3
import sys
from pathlib import Path


def snapshot(source, target):
    source = Path(source).resolve(strict=True)
    target = Path(target).resolve()
    if source == target:
        raise ValueError("Source and destination must differ")
    with sqlite3.connect(source.as_uri() + "?mode=ro", uri=True, timeout=10) as original:
        with sqlite3.connect(target, timeout=10) as copy:
            # One backup step retains one snapshot while WAL writers continue.
            original.backup(copy, pages=-1)
            if copy.execute("PRAGMA integrity_check").fetchone() != ("ok",):
                raise ValueError("SQLite snapshot failed its integrity check")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit("Usage: sqlite-snapshot.py <source> <destination>")
    try:
        snapshot(*sys.argv[1:])
    except (OSError, sqlite3.Error, ValueError):
        raise SystemExit("SQLite snapshot failed") from None
