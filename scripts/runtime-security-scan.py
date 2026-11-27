#!/usr/bin/env python3
"""Bounded weekly scans of actual Compose images; only aggregate status is exported."""
import argparse
import fcntl
import hashlib
import importlib.util
import json
import os
import resource
import subprocess
import tempfile
from datetime import datetime, timedelta, timezone
from pathlib import Path
from time import monotonic


def command(args, timeout=30):
    result = subprocess.run(args, capture_output=True, text=True, timeout=timeout)
    if result.returncode:
        raise ValueError('runtime_inventory_unavailable')
    return result.stdout


def active_inventory():
    ids = command(['docker', 'ps', '-q']).split()
    if not ids or len(ids) > 64:
        raise ValueError('unexpected_runtime_inventory')
    containers = json.loads(command(['docker', 'inspect', *ids]))
    models, images = {}, {}
    for container in containers:
        labels = container['Config'].get('Labels') or {}
        project = labels.get('com.docker.compose.project')
        files = labels.get('com.docker.compose.project.config_files')
        work = labels.get('com.docker.compose.project.working_dir')
        service = labels.get('com.docker.compose.service')
        if not all([project, files, work, service]):
            raise ValueError('container_without_compose_source')
        key = (project, files, work, labels.get('com.docker.compose.project.environment_file', ''))
        if key not in models:
            args = ['docker', 'compose', '--project-directory', work, '-p', project]
            env_files = key[-1].split(',') if key[-1] else []
            candidate = Path(files.split(',')[0]).parent / '.env'
            if not env_files and candidate.is_file():
                env_files = [str(candidate)]
            for env_file in env_files:
                args += ['--env-file', env_file]
            for file in files.split(','):
                args += ['-f', file]
            models[key] = json.loads(command(args + ['--profile', '*', 'config', '--format', 'json']))
        expected = models[key]['services'][service]['image']
        image = json.loads(command(['docker', 'image', 'inspect', expected]))[0]
        if container['Config']['Image'] != expected or container['Image'] != image['Id']:
            raise ValueError('runtime_image_differs_from_active_compose')
        images[container['Image']] = {'reference': expected, 'image_id': image['Id'], 'repo_digests': image.get('RepoDigests', [])}
    return images


def report_matches(report, image):
    metadata = report.get('Metadata', {})
    return metadata.get('ImageID') == image['image_id'] or bool(set(metadata.get('RepoDigests', [])) & set(image['repo_digests']))


def recent(value, now, days=7):
    try:
        when = datetime.fromisoformat(value)
        return when.tzinfo is not None and timedelta(0) <= now - when < timedelta(days=days)
    except (ValueError, TypeError):
        return False


def atomic(path, value, mode=0o600, group=None):
    fd, temporary = tempfile.mkstemp(prefix='.status-', dir=path.parent)
    try:
        os.fchmod(fd, mode)
        if group is not None:
            os.fchown(fd, 0, group)
        with os.fdopen(fd, 'w') as stream:
            json.dump(value, stream)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, path)
    finally:
        if Path(temporary).exists():
            Path(temporary).unlink()


def limits():
    os.nice(10)
    resource.setrlimit(resource.RLIMIT_FSIZE, (16 * 1024**2, 16 * 1024**2))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, default=Path('/projects/genesis'))
    parser.add_argument('--if-due', action='store_true')
    args = parser.parse_args()
    root = args.root.resolve()
    config = json.loads((root / 'infra/local/security-scanner.json').read_text())
    binary = Path(config['binary']).resolve()
    if not binary.is_relative_to(root / 'infra/runtime') or hashlib.sha256(binary.read_bytes()).hexdigest() != config['sha256']:
        raise ValueError('scanner_integrity_failed')
    state = root / 'infra/runtime/security'
    private, public = state / 'private', state / 'public'
    private.mkdir(mode=0o700, parents=True, exist_ok=True)
    public.mkdir(mode=0o750, exist_ok=True)
    os.chown(public, 0, 101)
    lock = (private / 'lock').open('a')
    try:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        return
    if os.statvfs(state).f_bavail * os.statvfs(state).f_frsize < 3 * 1024**3:
        raise ValueError('insufficient_scanner_workspace')
    now = datetime.now(timezone.utc)
    previous = json.loads((public / 'security-status.json').read_text()) if (public / 'security-status.json').exists() else {}
    status = {'schema': 1, 'checked_at': now.isoformat(), 'last_success_at': previous.get('last_success_at'), 'image_count': 0, 'scanned_count': 0, 'critical': 0, 'high': 0, 'blocking': 0, 'inventory_matches': False, 'error': 'scan_incomplete'}
    try:
        images = active_inventory()
        status.update(image_count=len(images), inventory_matches=True)
        spec = importlib.util.spec_from_file_location('policy', root / 'scripts/check-vulnerability-policy.py')
        policy = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(policy)
        exceptions = policy.load_policy(root / 'infra/security/trivy-ignore.yaml', root / 'infra/security/cve-reviews.json')
        deadline, updated_db, scan_times = monotonic() + 240, False, []
        for image_id, image in sorted(images.items()):
            key = image_id.removeprefix('sha256:')
            report_path, stamp = private / (key + '.json'), private / (key + '.stamp.json')
            cached = json.loads(stamp.read_text()) if stamp.exists() else {}
            if not (args.if_due and report_path.exists() and recent(cached.get('scanned_at'), now)):
                if monotonic() >= deadline:
                    continue
                fd, temporary = tempfile.mkstemp(prefix='.scan-', dir=private)
                try:
                    os.fchmod(fd, 0o600)
                    flags = ['--skip-db-update'] if updated_db else []
                    with os.fdopen(fd, 'wb') as output:
                        result = subprocess.run([str(binary), '--cache-dir', str(private / 'cache'), 'image', '--image-src', 'docker', '--scanners', 'vuln', '--ignorefile', '', '--disable-telemetry', '--parallel', '1', '--timeout', '3m', *flags, '--format', 'json', '--exit-code', '0', image_id], stdout=output, stderr=subprocess.DEVNULL, timeout=min(180, max(1, deadline-monotonic())), env={**os.environ, 'GOMAXPROCS': '2'}, preexec_fn=limits)
                    if result.returncode:
                        continue
                    report = json.loads(Path(temporary).read_text())
                    if not report_matches(report, image):
                        continue
                    policy.evaluate(report, exceptions)
                    os.replace(temporary, report_path)
                    atomic(stamp, {'scanned_at': now.isoformat()})
                    updated_db = True
                except (subprocess.TimeoutExpired, ValueError, OSError):
                    continue
                finally:
                    if Path(temporary).exists():
                        Path(temporary).unlink()
            if not report_path.exists() or not stamp.exists() or not recent(json.loads(stamp.read_text()).get('scanned_at'), now):
                continue
            report = json.loads(report_path.read_text())
            if not report_matches(report, image):
                continue
            summary = policy.evaluate(report, exceptions)
            scan_times.append(datetime.fromisoformat(json.loads(stamp.read_text())['scanned_at']))
            status['scanned_count'] += 1
            for key in ('critical', 'high', 'blocking'):
                status[key] += summary[key]
        if set(active_inventory()) != set(images):
            raise ValueError('runtime_inventory_changed_during_scan')
        if status['scanned_count'] == status['image_count']:
            status.update(last_success_at=min(scan_times).isoformat(), error=None)
        atomic(private / 'inventory.json', images)
    except (ValueError, KeyError, TypeError, OSError, subprocess.SubprocessError):
        status['error'] = 'runtime_scan_unavailable'
    atomic(public / 'security-status.json', status, 0o640, 101)
    print(json.dumps(status))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, KeyError):
        raise SystemExit('Runtime scan configuration/integrity unavailable') from None
