#!/usr/bin/env python3
"""Emit bounded patch/reboot/image metadata to the local journal; never install updates."""
import argparse
import json
import os
import re
import stat
import subprocess
import tempfile
from datetime import datetime, timedelta, timezone
from pathlib import Path


def command(args):
    r = subprocess.run(args, capture_output=True, text=True, timeout=30)
    if r.returncode:
        raise ValueError('metadata_command_failed')
    return r.stdout


def atomic(path, value):
    fd, name = tempfile.mkstemp(prefix='.maintenance-', dir=path.parent)
    try:
        os.fchmod(fd, 0o600)
        with os.fdopen(fd, 'w') as f:
            json.dump(value, f)
            f.flush()
            os.fsync(f.fileno())
        os.replace(name, path)
    finally:
        Path(name).unlink(missing_ok=True)


def image_status(images, scans, now):
    """A report about a replaced ID must never certify its replacement."""
    if not images:
        return None, 0, None
    ages = []
    for item in images:
        # Docker may use nanoseconds; Python accepts the fractional timestamp.
        when = datetime.fromisoformat(item['Created'].replace('Z', '+00:00'))
        if when.tzinfo is None or when > now:
            raise ValueError('invalid_image_creation_time')
        ages.append(int((now - when).total_seconds() / 86400))
    try:
        when = datetime.fromisoformat(scans['checked_at'].replace('Z', '+00:00'))
        if when.tzinfo is None or not timedelta(0) <= now - when <= timedelta(days=8):
            raise ValueError('stale_image_evidence')
        counts = scans['images']
        values = [counts[item['Id']] for item in images]
        if any(type(value) is not int or not 0 <= value <= 100000 for value in values):
            raise ValueError('invalid_image_count')
        return max(ages), sum(values), None
    except (ValueError, KeyError, TypeError):
        return max(ages), None, 'image_evidence_unavailable'


def update_since(count, previous, now):
    if not count:
        return None
    try:
        old = datetime.fromisoformat(previous.get('updates_since', ''))
        if old.tzinfo is not None and old <= now:
            return old.isoformat()
    except (TypeError, ValueError):
        pass
    return now.isoformat()


def report_counts(directory, images, now):
    values = {}
    for image in images:
        image_id = image['Id']
        if not re.fullmatch(r'sha256:[a-f0-9]{64}', image_id):
            raise ValueError('invalid_image_id')
        path = directory / (image_id.removeprefix('sha256:') + '.json')
        info = path.stat()
        if info.st_size > 16 * 1024**2 or not timedelta(0) <= now - datetime.fromtimestamp(info.st_mtime, timezone.utc) <= timedelta(days=8):
            raise ValueError('stale_image_report')
        report = json.loads(path.read_text())
        meta = report.get('Metadata', {})
        if meta.get('ImageID') != image_id and not set(meta.get('RepoDigests', [])) & set(image.get('RepoDigests', [])):
            raise ValueError('image_report_mismatch')
        values[image_id] = sum(v.get('Severity') == 'CRITICAL' and bool(v.get('FixedVersion')) for result in report['Results'] for v in result.get('Vulnerabilities', []))
    return {'checked_at': now.isoformat(), 'images': values}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', type=Path, required=True)
    args = parser.parse_args()
    meta = args.config.stat()
    if os.geteuid() != 0 or meta.st_uid != 0 or stat.S_IMODE(meta.st_mode) != 0o600 or meta.st_size > 4096:
        raise ValueError('invalid_private_configuration')
    config = json.loads(args.config.read_text())
    ident = config['host_id']
    if not re.fullmatch(r'[a-z0-9-]{1,64}', ident):
        raise ValueError('invalid_host_id')
    area = args.config.parent
    state = area / 'previous.json'
    previous = json.loads(state.read_text()) if state.exists() else {}
    now = datetime.now(timezone.utc)
    value = {'schema': 1, 'host_id': ident, 'checked_at': now.isoformat(),
             'reboot_required': Path('/var/run/reboot-required').exists(),
             'updates_count': None, 'updates_since': previous.get('updates_since'),
             'updates_error': None, 'oldest_image_days': None,
             'fixable_critical': None, 'image_error': None}
    try:
        lines = command(['apt', 'list', '--upgradable']).splitlines()
        count = sum('/' in line and not line.startswith('Listing') for line in lines)
        value.update(updates_count=count, updates_since=update_since(count, previous, now))
    except (ValueError, OSError, subprocess.SubprocessError):
        value['updates_error'] = 'updates_unavailable'
    try:
        if config.get('docker', False):
            ids = command(['docker', 'ps', '-q']).split()
            containers = json.loads(command(['docker', 'inspect', *ids])) if ids else []
            images = [json.loads(command(['docker', 'image', 'inspect', image]))[0] for image in sorted({c['Image'] for c in containers})]
            scans = report_counts(Path(config['reports_dir']), images, now)
            oldest, critical, error = image_status(images, scans, now)
            value.update(oldest_image_days=oldest, fixable_critical=critical, image_error=error)
    except (ValueError, KeyError, TypeError, OSError, subprocess.SubprocessError):
        value['image_error'] = 'image_evidence_unavailable'
    atomic(state, value)
    # stdout is consumed by a fixed systemd unit with its own journal identifier.
    print(json.dumps(value, separators=(',', ':')))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, KeyError, TypeError, OSError, subprocess.SubprocessError):
        raise SystemExit('Maintenance metadata unavailable; no updates were installed') from None
