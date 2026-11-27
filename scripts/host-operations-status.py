#!/usr/bin/env python3
"""Export completed backup timestamps only, from an operator-private host mapping."""
import argparse
import json
import os
import re
import stat
import subprocess
import tempfile
from datetime import datetime, timezone
from pathlib import Path


def iso(epoch, now):
    if not isinstance(epoch, (int, float)) or isinstance(epoch, bool) or not 0 < epoch <= now.timestamp():
        raise ValueError('invalid_success_time')
    return datetime.fromtimestamp(epoch, timezone.utc).isoformat()


def completed_unit(unit, now):
    if unit not in ('backup-extern.service', 'photos-backup.service'):
        raise ValueError('unexpected_backup_unit')
    result = subprocess.run(['systemctl', 'show', unit, '--property=Result,ExecMainExitTimestamp'], capture_output=True, text=True, timeout=3)
    fields = dict(line.split('=', 1) for line in result.stdout.splitlines() if '=' in line)
    if result.returncode or fields.get('Result') != 'success' or not fields.get('ExecMainExitTimestamp'):
        raise ValueError('backup_unit_has_no_completed_success')
    parsed = subprocess.run(['date', '--date', fields['ExecMainExitTimestamp'], '+%s'], capture_output=True, text=True, timeout=3)
    if parsed.returncode:
        raise ValueError('backup_unit_time_invalid')
    return iso(int(parsed.stdout), now)


def backup(spec, previous, now):
    if (not re.fullmatch(r'[a-z0-9-]{1,64}', spec['id']) or not 1 <= len(spec['label']) <= 80
            or spec['kind'] not in ('local', 'offsite')):
        raise ValueError('invalid_backup_mapping')
    value = {key: spec[key] for key in ('id', 'label', 'kind')}
    value.update(last_success_at=previous.get('last_success_at'), error=None)
    try:
        source = spec['source']
        if source['type'] == 'none':
            value.update(last_success_at=None, error='not_configured')
        elif source['type'] == 'unit':
            value['last_success_at'] = completed_unit(source['unit'], now)
        else:
            path = Path(source['path'])
            if not path.is_absolute() or not any(path.is_relative_to(prefix) for prefix in [Path('/projects'), Path('/srv')]):
                raise ValueError('unexpected_marker_location')
            info = path.stat()
            if not stat.S_ISREG(info.st_mode) or info.st_size > 65536:
                raise ValueError('invalid_marker_file')
            if source['type'] == 'file':
                if info.st_size == 0:
                    raise ValueError('empty_completed_dump')
                value['last_success_at'] = iso(info.st_mtime, now)
            elif source['type'] == 'marker':
                value['last_success_at'] = iso(int(path.read_text().strip()), now)
            elif source['type'] == 'json':
                when = datetime.fromisoformat(json.loads(path.read_text())[source['field']].replace('Z', '+00:00'))
                if when.tzinfo is None:
                    raise ValueError('naive_marker_time')
                value['last_success_at'] = iso(when.timestamp(), now)
            else:
                raise ValueError('unknown_backup_evidence')
    except (ValueError, KeyError, TypeError, OSError, subprocess.SubprocessError):
        value['error'] = 'evidence_unavailable'
    return value


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', type=Path, required=True)
    args = parser.parse_args()
    info = args.config.stat()
    if os.geteuid() != 0 or info.st_uid != 0 or stat.S_IMODE(info.st_mode) != 0o600 or info.st_size > 32768:
        raise ValueError('private_mapping_permissions_invalid')
    config = json.loads(args.config.read_text())
    output = Path(config['output'])
    if not output.is_absolute() or not any(output.is_relative_to(prefix) for prefix in [Path('/projects'), Path('/srv')]):
        raise ValueError('unexpected_status_output')
    if not 1 <= len(config['backups']) <= 16:
        raise ValueError('invalid_backup_mapping_count')
    previous = json.loads(output.read_text()) if output.exists() else {}
    old = {item['id']: item for item in previous.get('backups', [])}
    now = datetime.now(timezone.utc)
    value = {'schema': 1, 'checked_at': now.isoformat(), 'backups': [backup(spec, old.get(spec['id'], {}), now) for spec in config['backups']]}
    if len({item['id'] for item in value['backups']}) != len(value['backups']):
        raise ValueError('duplicate_backup_stream')
    fd, temporary = tempfile.mkstemp(prefix='.operations-', dir=output.parent)
    try:
        os.fchmod(fd, 0o640)
        os.fchown(fd, 0, int(config['read_group']))
        with os.fdopen(fd, 'w') as stream:
            json.dump(value, stream)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, output)
    finally:
        Path(temporary).unlink(missing_ok=True)


if __name__ == '__main__':
    try:
        main()
    except (ValueError, KeyError, TypeError, OSError, subprocess.SubprocessError):
        raise SystemExit('Host backup status refresh unavailable; prior evidence retained') from None
