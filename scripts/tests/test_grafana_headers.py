import importlib.util
import unittest
import subprocess
from unittest.mock import patch
from email.parser import Parser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("grafana_headers", ROOT / "scripts/check-grafana-headers.py")
policy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(policy)


class GrafanaHeaderTests(unittest.TestCase):
    def test_credentials_use_curl_stdin_only_and_never_follow_redirects(self):
        response = subprocess.CompletedProcess([], 0, b'HTTP/1.1 200 OK\r\n\r\nsynthetic')
        with patch.object(policy.subprocess, 'run', return_value=response) as execute:
            policy.fetch('https://grafana.shellr.net/login', 'grafana.shellr.net:443:127.0.0.1', b'synthetic-base64')
        args, kwargs = execute.call_args
        self.assertNotIn('synthetic-base64', ' '.join(args[0]))
        self.assertIn(b'Authorization: Basic synthetic-base64', kwargs['input'])
        self.assertNotIn('--location', args[0])
        with self.assertRaises(ValueError):
            policy.fetch('https://other.invalid/login', None, b'synthetic-base64')

    def test_authenticated_path_checks_nonce_and_fails_closed(self):
        headers = self.headers()
        for name in ('referrer-policy', 'permissions-policy', 'strict-transport-security'):
            headers[name] = 'synthetic'
        second = self.headers()
        second.replace_header('content-security-policy', headers['content-security-policy'].replace('synthetic123', 'synthetic456'))
        for name in ('referrer-policy', 'permissions-policy', 'strict-transport-security'):
            second[name] = 'synthetic'
        with patch.object(policy, 'credentials', return_value=b'synthetic'), patch.object(policy, 'fetch', side_effect=[(200, headers, self.body()), (200, second, self.body('synthetic456'))]):
            policy.authenticated()
        with patch.object(policy, 'credentials', return_value=b'synthetic'), patch.object(policy, 'fetch', return_value=(401, headers, '')):
            with self.assertRaises(ValueError): policy.authenticated()
        with patch.object(policy, 'credentials', return_value=b'synthetic'), patch.object(policy, 'fetch', return_value=(200, headers, self.body())):
            with self.assertRaises(ValueError): policy.authenticated()

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
