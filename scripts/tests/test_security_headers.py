import importlib.util
import unittest
from email.parser import Parser
from pathlib import Path


spec = importlib.util.spec_from_file_location(
    "security_headers", Path(__file__).resolve().parents[1] / "check-security-headers.py"
)
policy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(policy)


class SecurityHeaderTests(unittest.TestCase):
    def test_checks_the_asset_version_served_to_browsers(self):
        page = '<script src="/assets/app.js?v=0123456789abcdef"></script>'
        self.assertEqual(policy.published_asset(page), "https://shellr.net/assets/app.js?v=0123456789abcdef")
        with self.assertRaises(ValueError):
            policy.published_asset('<script src="https://example.invalid/app.js"></script>')

    def headers(self):
        values = {**policy.REQUIRED, "content-security-policy":
                  "default-src 'self'; object-src 'none'; frame-ancestors 'none'"}
        return Parser().parsestr("".join(f"{key}: {value}\r\n" for key, value in values.items()))

    def test_complete_policy(self):
        policy.validate(self.headers())

    def test_missing_header_fails(self):
        for field in (*policy.REQUIRED, "content-security-policy"):
            with self.subTest(header=field):
                headers = self.headers()
                del headers[field]
                with self.assertRaises(ValueError):
                    policy.validate(headers)

    def test_duplicate_policy_fails(self):
        headers = self.headers()
        headers["content-security-policy"] = "default-src *"
        with self.assertRaises(ValueError):
            policy.validate(headers)
