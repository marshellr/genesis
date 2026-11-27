#!/usr/bin/env python3
"""Publish scanned OCI manifests, prepare immutable local tags, verify runtime identity."""
import argparse
import hashlib
import json
import re
import subprocess
from pathlib import Path

DIGEST = re.compile(r'sha256:[a-f0-9]{64}')
SOURCE = 'https://github.com/marshellr/genesis'


def command(args):
    result = subprocess.run(args, capture_output=True, text=True, timeout=180)
    if result.returncode:
        raise ValueError('image_registry_or_docker_operation_failed')
    return result.stdout


def docker(*args):
    return command(['docker', *args])


def validate(value, commit):
    if (not re.fullmatch(r'[a-f0-9]{40}', commit) or set(value) != {'schema', 'commit', 'platform', 'images'}
            or value['schema'] != 1 or value['commit'] != commit or value['platform'] != 'linux/amd64'
            or set(value['images']) != {'app', 'dma'}):
        raise ValueError('invalid_release_image_manifest')
    for component, item in value['images'].items():
        if set(item) != {'reference', 'scan_image_id', 'config_digest', 'rootfs', 'scan_report_sha256'}:
            raise ValueError('invalid_release_image_fields')
        prefix = 'ghcr.io/marshellr/genesis-' + component + '@'
        if (not item['reference'].startswith(prefix) or not DIGEST.fullmatch(item['reference'][len(prefix):])
                or not DIGEST.fullmatch(item['config_digest']) or item['scan_image_id'] != item['config_digest']
                or not re.fullmatch(r'[a-f0-9]{64}', item['scan_report_sha256'])
                or not isinstance(item['rootfs'], list) or not 1 <= len(item['rootfs']) <= 64
                or any(not DIGEST.fullmatch(layer) for layer in item['rootfs'])):
            raise ValueError('mutable_or_unscanned_release_image')
    return value


def image_matches(image, item, commit):
    labels = image.get('Config', {}).get('Labels') or {}
    return (item['reference'] in image.get('RepoDigests', [])
            and image.get('Id') in {item['config_digest'], item['reference'].split('@')[1]}
            and image.get('Os') == 'linux' and image.get('Architecture') == 'amd64'
            and image.get('RootFS', {}).get('Layers') == item['rootfs']
            and labels.get('org.opencontainers.image.revision') == commit
            and labels.get('org.opencontainers.image.source') == SOURCE)


def immutable_reference(repository, digest):
    # No attestation or multi-platform index is admitted into this deployment path.
    raw = docker('buildx', 'imagetools', 'inspect', '--raw', repository + '@' + digest)
    manifest = json.loads(raw)
    if ('manifests' in manifest or manifest.get('schemaVersion') != 2
            or not DIGEST.fullmatch(manifest.get('config', {}).get('digest', ''))):
        raise ValueError('expected_single_platform_registry_manifest')
    if 'sha256:' + hashlib.sha256(raw.encode()).hexdigest() != digest:
        # buildx can append one newline to the otherwise byte-identical response.
        if 'sha256:' + hashlib.sha256(raw.rstrip('\n').encode()).hexdigest() != digest:
            raise ValueError('registry_manifest_digest_changed')
    return manifest['config']['digest']


def publish(commit, reports):
    value = {'schema': 1, 'commit': commit, 'platform': 'linux/amd64', 'images': {}}
    for component in ['app', 'dma']:
        local = 'genesis-' + component + ':' + commit
        image = json.loads(docker('image', 'inspect', local))[0]
        report_path = reports / ('genesis-' + component + '.json')
        report = json.loads(report_path.read_bytes())
        if report.get('Metadata', {}).get('ImageID') != image['Id']:
            raise ValueError('scan_does_not_match_published_image')
        repository = 'ghcr.io/marshellr/genesis-' + component
        docker('tag', local, repository + ':' + commit)
        docker('push', repository + ':' + commit)
        pushed = json.loads(docker('image', 'inspect', repository + ':' + commit))[0]
        refs = [ref for ref in pushed.get('RepoDigests', []) if ref.startswith(repository + '@')]
        if len(refs) != 1 or pushed['Id'] != image['Id']:
            raise ValueError('pushed_image_identity_changed')
        digest = refs[0].split('@')[1]
        config_digest = immutable_reference(repository, digest)
        if config_digest != image['Id']:
            raise ValueError('registry_config_is_not_the_scanned_image')
        value['images'][component] = {'reference': refs[0], 'scan_image_id': image['Id'],
                                      'config_digest': config_digest, 'rootfs': image['RootFS']['Layers'],
                                      'scan_report_sha256': hashlib.sha256(report_path.read_bytes()).hexdigest()}
    return validate(value, commit)


def prepare(value, commit):
    # Pull and verify both before exposing either local deployment tag.
    for item in value['images'].values():
        docker('pull', '--platform', 'linux/amd64', item['reference'])
        image = json.loads(docker('image', 'inspect', item['reference']))[0]
        if not image_matches(image, item, commit):
            raise ValueError('pulled_image_does_not_match_scanned_manifest')
    for component, item in value['images'].items():
        docker('tag', item['reference'], 'genesis-' + component + ':' + commit)


def verify(value, commit):
    for component, item in value['images'].items():
        image = json.loads(docker('image', 'inspect', item['reference']))[0]
        container = json.loads(docker('inspect', 'genesis-' + component))[0]
        if (not image_matches(image, item, commit) or container['Image'] != image['Id']
                or container['Config']['Image'] != 'genesis-' + component + ':' + commit):
            raise ValueError('running_image_does_not_match_scanned_registry_digest')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=['publish', 'prepare', 'verify'])
    parser.add_argument('manifest', type=Path)
    parser.add_argument('commit')
    parser.add_argument('--reports', type=Path, default=Path('.scan'))
    args = parser.parse_args()
    if not re.fullmatch(r'[a-f0-9]{40}', args.commit):
        raise ValueError('invalid_release_commit')
    if args.operation == 'publish':
        value = publish(args.commit, args.reports)
        args.manifest.write_text(json.dumps(value, indent=2) + '\n')
    else:
        if args.manifest.stat().st_size > 32768:
            raise ValueError('release_image_manifest_too_large')
        value = validate(json.loads(args.manifest.read_text()), args.commit)
        (prepare if args.operation == 'prepare' else verify)(value, args.commit)
    print('Scanned image identity verified: ' + args.operation)


if __name__ == '__main__':
    try:
        main()
    except (ValueError, KeyError, TypeError, OSError, subprocess.SubprocessError):
        raise SystemExit('Release image operation failed; no automatic host-build fallback') from None
