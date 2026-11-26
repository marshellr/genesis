import importlib.util
import unittest
from email.parser import Parser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("grafana_headers", ROOT / "scripts/check-grafana-headers.py")
policy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(policy)


class GrafanaHeaderTests(unittest.TestCase):
    def headers(self):
        return Parser().parsestr(
            "Content-Security-Policy: script-src 'self' 'strict-dynamic' 'nonce-synthetic123';object-src 'none';\r\n"
            "X-Frame-Options: deny\r\nX-Content-Type-Options: nosniff\r\n"
        )

    def body(self, nonce="synthetic123"):
        return f'<script nonce="{nonce}">window.grafanaBootData = {{}};</script>'

    def test_valid_bootstrap_nonce(self):
        self.assertEqual(policy.validate_upstream(self.headers(), self.body()), "synthetic123")

    def test_missing_wrong_and_duplicate_policies_fail(self):
        for change in ("missing", "static", "duplicate", "duplicate_frame", "duplicate_nosniff"):
            with self.subTest(change=change):
                headers = self.headers()
                if change == "missing": del headers["content-security-policy"]
                elif change == "static":
                    headers.replace_header("content-security-policy", "default-src 'self'")
                elif change == "duplicate": headers["content-security-policy"] = "default-src 'self'"
                elif change == "duplicate_frame": headers["x-frame-options"] = "SAMEORIGIN"
                else: headers["x-content-type-options"] = "nosniff"
                with self.assertRaises(ValueError):
                    policy.validate_upstream(headers, self.body())

    def test_missing_or_mismatched_inline_nonce_fails(self):
        for body in (self.body("other_nonce"), '<script>window.grafanaBootData = {};</script>', self.body()+'<script>extra();</script>'):
            with self.subTest(body=body), self.assertRaises(ValueError):
                policy.validate_upstream(self.headers(), body)

    def test_proxy_rejects_global_header_regression(self):
        headers = Parser().parsestr('WWW-Authenticate: Basic realm="Restricted Grafana"\r\nReferrer-Policy: strict-origin-when-cross-origin\r\nPermissions-Policy: camera=()\r\nStrict-Transport-Security: max-age=31536000\r\n')
        policy.validate_front(headers)
        for name in ("content-security-policy", "x-frame-options", "x-content-type-options"):
            with self.subTest(header=name):
                headers[name] = "unexpected"
                with self.assertRaises(ValueError): policy.validate_front(headers)
                del headers[name]

    def test_repository_keeps_grafana_policy_separate(self):
        config = (ROOT / "infra/nginx/conf.d/30-grafana.shellr.net.conf").read_text()
        self.assertNotIn("snippets/security_headers.conf", config)
        for name in ("Content-Security-Policy", "X-Frame-Options", "X-Content-Type-Options"):
            self.assertNotIn("add_header " + name, config)
        self.assertNotIn("proxy_hide_header", config)
        self.assertEqual(config.count("include /etc/nginx/snippets/grafana_access.conf;"), 2)
        compose = (ROOT / "infra/monitoring/docker-compose.monitoring.yml").read_text()
        self.assertIn('GF_SECURITY_CONTENT_SECURITY_POLICY: "true"', compose)
        self.assertNotIn("GF_SECURITY_CONTENT_SECURITY_POLICY_TEMPLATE", compose)
        self.assertIn('"$ROOT_DIR/scripts/check-grafana-headers.py"', (ROOT / "scripts/deploy.sh").read_text())
