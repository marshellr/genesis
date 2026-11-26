import importlib.util
import tempfile
import unittest
from unittest.mock import MagicMock, patch
from pathlib import Path

spec = importlib.util.spec_from_file_location('docs_links', Path(__file__).resolve().parents[1] / 'check-docs-links.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class DocsLinksTests(unittest.TestCase):
    def fixture(self, body):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        root = Path(temporary.name)
        (root / 'index.html').write_text(body)
        (root / 'guide.html').write_text('<h1 id="synthetic-section">Guide</h1>')
        (root / 'styles.css').write_text('body{}')
        return root

    def test_relative_absolute_fragment_and_external_links(self):
        root = self.fixture('<a href="guide.html?version=1#synthetic-section">G</a><a href="https://docs.shellr.net/guide.html">G</a><link href="/styles.css"><a href="https://example.invalid/">External</a>')
        self.assertEqual(module.check(root), (2, []))

    def test_missing_page_asset_and_fragment_fail(self):
        root = self.fixture('<a href="missing.html">G</a><img src="missing.png"><a href="guide.html#missing">G</a>')
        self.assertEqual(len(module.check(root)[1]), 3)

    def test_link_cannot_escape_root(self):
        root = self.fixture('<a href="../outside.html">G</a>')
        self.assertIn('leaves site', module.check(root)[1][0])

    def test_no_pages_fails(self):
        with tempfile.TemporaryDirectory() as directory:
            self.assertTrue(module.check(directory)[1])

    def test_published_bytes_must_match_and_can_retry(self):
        root = self.fixture('synthetic')
        names = ['index.html', 'architecture.html', 'projects.html', 'case-studies.html', 'backup.html']
        for name in names:
            (root / name).write_text('synthetic')
        def response(request, timeout):
            result = MagicMock()
            result.status, result.url = 200, request.full_url
            result.read.return_value = b'synthetic'
            result.__enter__.return_value = result
            return result
        with patch.object(module, 'urlopen', side_effect=[OSError('synthetic outage')]*5 + [response(type('Request', (), {'full_url': 'https://docs.shellr.net/' + ('' if name == 'index.html' else name)})(), 15) for name in names]) as fetch, patch.object(module.time, 'sleep') as sleep:
            self.assertTrue(module.verify_published(root, attempts=2))
            self.assertEqual(fetch.call_count, 10)
            sleep.assert_called_once()

    def test_published_error_cannot_pass(self):
        root = self.fixture('synthetic')
        for name in ['architecture.html', 'projects.html', 'case-studies.html', 'backup.html']:
            (root / name).write_text('synthetic')
        with patch.object(module, 'urlopen', side_effect=OSError('synthetic TLS failure')):
            self.assertFalse(module.verify_published(root, attempts=1))
