import importlib.util
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path

spec = importlib.util.spec_from_file_location('maintenance', Path(__file__).resolve().parents[1] / 'host-maintenance-status.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class MaintenanceTests(unittest.TestCase):
    def test_replaced_images_cannot_inherit_a_good_scan(self):
        now = datetime.now(timezone.utc)
        images = [{'Id': 'sha256:replacement', 'Created': (now - timedelta(days=70)).isoformat()}]
        scans = {'checked_at': now.isoformat(), 'images': {'sha256:previous': 0}}
        self.assertEqual(module.image_status(images, scans, now), (70, None, 'image_evidence_unavailable'))
        scans['images']['sha256:replacement'] = 2
        self.assertEqual(module.image_status(images, scans, now), (70, 2, None))

    def test_stale_report_is_unknown_and_update_age_is_not_reset(self):
        now = datetime.now(timezone.utc)
        old = (now - timedelta(days=10)).isoformat()
        self.assertEqual(module.update_since(3, {'updates_since': old}, now), old)
        self.assertIsNone(module.update_since(0, {'updates_since': old}, now))
        images = [{'Id': 'image', 'Created': old}]
        self.assertEqual(module.image_status(images, {'checked_at': old, 'images': {'image': 0}}, now)[1:], (None, 'image_evidence_unavailable'))
