#!/usr/bin/env python3
"""Check Grafana-owned headers and inline nonces without credentials or login."""
import re
import subprocess
from email.parser import Parser
from html.parser import HTMLParser


class Scripts(HTMLParser):
    def __init__(self):
        super().__init__()
        self.inline = []

    def handle_starttag(self, tag, attributes):
        attrs = dict(attributes)
        if tag == "script" and not attrs.get("src") and attrs.get("type", "") in (
            "", "text/javascript", "application/javascript", "module",
        ):
            self.inline.append(attrs.get("nonce"))


def fetch(url, resolve=None):
    command = ["curl", "--noproxy", "*", "--silent", "--show-error",
               "--http1.1", "--connect-timeout", "4", "--max-time", "15",
               "--max-filesize", "8388608", "--include"]
    if resolve:
        command += ["--resolve", resolve]
    result = subprocess.run(command + [url], capture_output=True)
    if result.returncode:
        raise ValueError("Grafana probe transport failed")
    header, separator, body = result.stdout.partition(b"\r\n\r\n")
    if not separator:
        raise ValueError("Grafana probe response missing headers")
    status, _, fields = header.decode("iso-8859-1").partition("\r\n")
    return int(status.split()[1]), Parser().parsestr(fields), body.decode("utf-8")


def validate_front(headers):
    for name in ("content-security-policy", "x-frame-options", "x-content-type-options"):
        if headers.get_all(name, []):
            raise ValueError("Proxy must leave Grafana-owned headers to Grafana: " + name)
    challenges = headers.get_all("www-authenticate", [])
    if len(challenges) != 1 or not challenges[0].startswith("Basic "):
        raise ValueError("Grafana Basic Auth challenge changed")
    for name in ("referrer-policy", "permissions-policy", "strict-transport-security"):
        if len(headers.get_all(name, [])) != 1:
            raise ValueError("Missing or duplicate Grafana proxy header: " + name)


def validate_upstream(headers, body):
    policies = headers.get_all("content-security-policy", [])
    if len(policies) != 1:
        raise ValueError("Grafana must emit exactly one CSP")
    policy = policies[0]
    script = next((part.strip() for part in policy.split(";") if part.strip().startswith("script-src ")), "")
    nonces = re.findall(r"'nonce-([A-Za-z0-9+/_=-]{8,})'", script)
    if len(nonces) != 1 or "'strict-dynamic'" not in script or "object-src 'none'" not in policy:
        raise ValueError("Grafana nonce CSP missing or unexpected")
    for name, expected in (("x-frame-options", "deny"), ("x-content-type-options", "nosniff")):
        if [v.lower() for v in headers.get_all(name, [])] != [expected]:
            raise ValueError("Missing or duplicate Grafana-owned header: " + name)
    scripts = Scripts()
    scripts.feed(body)
    if "window.grafanaBootData" not in body or not scripts.inline or any(nonce != nonces[0] for nonce in scripts.inline):
        raise ValueError("Grafana bootstrap scripts must match the response nonce")
    return nonces[0]


def main():
    status, headers, _ = fetch("https://grafana.shellr.net/login", "grafana.shellr.net:443:127.0.0.1")
    if status != 401:
        raise ValueError("Grafana front door must remain HTTP 401 without credentials")
    validate_front(headers)
    nonces = []
    for _ in range(2):
        status, headers, body = fetch("http://127.0.0.1:3000/login")
        if status != 200:
            raise ValueError("Grafana upstream login unavailable")
        nonces.append(validate_upstream(headers, body))
    if nonces[0] == nonces[1]:
        raise ValueError("Grafana nonce must change between responses")
    print("Grafana headers verified: Basic Auth 401, one upstream CSP, matching fresh bootstrap nonces, no duplicate headers")


if __name__ == "__main__":
    try:
        main()
    except ValueError as error:
        raise SystemExit(str(error)) from None
