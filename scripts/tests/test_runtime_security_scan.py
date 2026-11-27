import importlib.util
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('runtime_security_scan', Path(__file__).resolve().parents[1] / 'runtime-security-scan.py')
scan = importlib.util.module_from_spec(spec)
spec.loader.exec_module(scan)


class RuntimeSecurityScanTests(unittest.TestCase):
    def test_cache_age_is_not_advanced_by_reading_it(self):
        now = datetime(2026, 11, 27, tzinfo=timezone.utc)
        self.assertTrue(scan.recent((now - timedelta(days=6)).isoformat(), now))
        for value in [(now - timedelta(days=7)).isoformat(), (now + timedelta(seconds=1)).isoformat(), 'invalid', None, '2026-11-27T00:00:00']:
            self.assertFalse(scan.recent(value, now))

    def test_scan_must_identify_the_actual_image_or_its_immutable_repo_digest(self):
        image = {'image_id': 'sha256:' + 'a'*64, 'repo_digests': ['registry.invalid/synthetic@sha256:' + 'b'*64]}
        self.assertTrue(scan.report_matches({'Metadata': {'ImageID': image['image_id']}}, image))
        self.assertTrue(scan.report_matches({'Metadata': {'ImageID': 'sha256:' + 'c'*64, 'RepoDigests': image['repo_digests']}}, image))
        self.assertFalse(scan.report_matches({'Metadata': {'ImageID': 'sha256:' + 'c'*64}}, image))
        self.assertFalse(scan.report_matches({}, image))

    def test_missing_compose_origin_is_a_gap_not_a_successful_empty_inventory(self):
        with patch.object(scan, 'command', side_effect=['synthetic-id', '[{"Config":{"Labels":{}}}]']):
            with self.assertRaises(ValueError): scan.active_inventory()
