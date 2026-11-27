import importlib.util
import unittest
from datetime import datetime, timezone
from pathlib import Path
from unittest.mock import patch
from unittest.mock import MagicMock
from types import SimpleNamespace
import stat

spec = importlib.util.spec_from_file_location('host_operations', Path(__file__).resolve().parents[1] / 'host-operations-status.py')
host = importlib.util.module_from_spec(spec)
spec.loader.exec_module(host)


class HostOperationsTests(unittest.TestCase):
    def test_large_dump_uses_only_metadata_and_is_not_read_as_a_small_marker(self):
        now = datetime(2026, 11, 27, tzinfo=timezone.utc)
        path = MagicMock()
        path.is_absolute.return_value = True
        path.is_relative_to.return_value = True
        path.stat.return_value = SimpleNamespace(st_mode=stat.S_IFREG | 0o600, st_size=20*1024**2, st_mtime=now.timestamp()-1)
        spec = {'id': 'synthetic', 'label': 'Synthetic', 'kind': 'local', 'source': {'type': 'file', 'path': '/srv/synthetic.dump'}}
        with patch.object(host, 'Path', return_value=path):
            result = host.backup(spec, {}, now)
        self.assertIsNone(result['error'])
        self.assertIsNotNone(result['last_success_at'])
        path.read_text.assert_not_called()

    def test_failed_backup_preserves_actual_previous_success_without_refreshing_it(self):
        now = datetime(2026, 11, 27, tzinfo=timezone.utc)
        spec = {'id': 'synthetic', 'label': 'Synthetic', 'kind': 'offsite', 'source': {'type': 'unit', 'unit': 'backup-extern.service'}}
        old = {'last_success_at': '2026-11-26T00:00:00+00:00'}
        with patch.object(host, 'completed_unit', side_effect=ValueError('synthetic_failure')):
            result = host.backup(spec, old, now)
        self.assertEqual(result['last_success_at'], old['last_success_at'])
        self.assertEqual(result['error'], 'evidence_unavailable')
        spec['source'] = {'type': 'none'}
        result = host.backup(spec, old, now)
        self.assertIsNone(result['last_success_at'])
        self.assertEqual(result['error'], 'not_configured')

    def test_future_invalid_and_naive_times_do_not_become_success(self):
        now = datetime(2026, 11, 27, tzinfo=timezone.utc)
        for value in [True, -1, 'not-a-time', now.timestamp()+1]:
            with self.assertRaises(ValueError): host.iso(value, now)
