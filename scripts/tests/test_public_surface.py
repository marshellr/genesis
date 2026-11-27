import importlib.util
import unittest
import subprocess
from email.parser import Parser
from pathlib import Path

spec = importlib.util.spec_from_file_location('surface', Path(__file__).resolve().parents[1] / 'check-public-surface.py')
surface = importlib.util.module_from_spec(spec)
spec.loader.exec_module(surface)


class PublicSurfaceTests(unittest.TestCase):
    def test_only_a_remote_protocol_alert_proves_legacy_tls_is_disabled(self):
        for code, text, expected in [(1, b'tlsv1 alert protocol version', True), (0, b'Protocol version: TLSv1.1', False), (1, b'no protocols available', False), (1, b'connection refused', False)]:
            result = subprocess.CompletedProcess([], code, b'', text)
            self.assertEqual(surface.legacy_protocol_refused(result), expected)

    def test_hidden_files_and_generic_errors_are_rejected_without_disclosure(self):
        headers = Parser().parsestr('Server: cloudflare\r\n')
        surface.validate_hidden(404, headers, b'Not found')
        surface.validate_hidden(403, headers, b'Forbidden')
        for status, body in [(200, b'data'), (500, b'error'), (404, b'Fatal error: example.php'), (403, b'/var/www/example')]:
            with self.assertRaises(ValueError): surface.validate_hidden(status, headers, body)
        headers['X-Powered-By'] = 'PHP/8.3.synthetic'
        with self.assertRaises(ValueError): surface.validate_hidden(404, headers, b'Not found')
