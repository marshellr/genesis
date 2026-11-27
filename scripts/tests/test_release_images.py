import copy
import importlib.util
import json
import unittest
from pathlib import Path
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('release_images', Path(__file__).resolve().parents[1] / 'release-images.py')
images = importlib.util.module_from_spec(spec)
spec.loader.exec_module(images)
COMMIT = 'a' * 40
CONFIG = 'sha256:' + 'b' * 64
DIGEST = 'sha256:' + 'c' * 64


def manifest():
    return {'schema': 1, 'commit': COMMIT, 'platform': 'linux/amd64', 'images': {
        component: {'reference': 'ghcr.io/marshellr/genesis-' + component + '@' + DIGEST,
                    'config_digest': CONFIG, 'scan_image_id': CONFIG, 'rootfs': [CONFIG],
                    'scan_report_sha256': 'd'*64} for component in ['app', 'dma']}}


def image(item):
    return {'Id': CONFIG, 'RepoDigests': [item['reference']], 'Os': 'linux', 'Architecture': 'amd64',
            'RootFS': {'Layers': [CONFIG]}, 'Config': {'Labels': {'org.opencontainers.image.source': images.SOURCE,
                                                               'org.opencontainers.image.revision': COMMIT}}}


class ReleaseImagesTests(unittest.TestCase):
    def test_host_docker_uses_sudo_as_an_executable_not_a_shell_function(self):
        with patch.object(images, 'DOCKER', ['sudo', '-n', 'docker']), patch.object(images, 'command', return_value='{}') as execute:
            images.docker('image', 'inspect', 'synthetic')
            execute.assert_called_once_with(['sudo', '-n', 'docker', 'image', 'inspect', 'synthetic'])

    def test_mutable_wrong_commit_platform_registry_and_scan_are_rejected(self):
        value = manifest()
        self.assertEqual(images.validate(value, COMMIT), value)
        edits = [('platform', 'linux/arm64'), ('commit', 'b'*40)]
        for key, replacement in edits:
            changed = copy.deepcopy(value)
            changed[key] = replacement
            with self.assertRaises(ValueError): images.validate(changed, COMMIT)
        for key, replacement in [('reference', 'ghcr.io/marshellr/genesis-app:latest'),
                                 ('reference', 'registry.invalid/app@' + DIGEST),
                                 ('scan_image_id', DIGEST), ('rootfs', [])]:
            changed = copy.deepcopy(value)
            changed['images']['app'][key] = replacement
            with self.assertRaises(ValueError): images.validate(changed, COMMIT)

    def test_registry_and_containerd_id_formats_still_require_exact_config_layers_and_revision(self):
        item = manifest()['images']['app']
        value = image(item)
        self.assertTrue(images.image_matches(value, item, COMMIT))
        value['Id'] = DIGEST
        self.assertTrue(images.image_matches(value, item, COMMIT))
        for key, replacement in [('Id', 'sha256:'+'e'*64), ('RepoDigests', []), ('RootFS', {'Layers': [DIGEST]})]:
            changed = copy.deepcopy(value)
            changed[key] = replacement
            self.assertFalse(images.image_matches(changed, item, COMMIT))

    def test_failed_pull_does_not_build_or_publish_any_deployment_tag(self):
        calls = []
        def execute(*args):
            calls.append(args)
            raise ValueError('synthetic_pull_failure')
        with patch.object(images, 'docker', side_effect=execute):
            with self.assertRaises(ValueError): images.prepare(manifest(), COMMIT)
        self.assertEqual(len(calls), 1)
        self.assertEqual(calls[0][0], 'pull')

    def test_running_digest_mismatch_fails_deployment_verification(self):
        item = manifest()['images']['app']
        container = {'Image': DIGEST, 'Config': {'Image': 'genesis-app:' + COMMIT}}
        with patch.object(images, 'docker', side_effect=[json.dumps([image(item)]), json.dumps([container])]):
            with self.assertRaises(ValueError): images.verify(manifest(), COMMIT)
