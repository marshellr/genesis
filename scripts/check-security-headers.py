#!/usr/bin/env python3
"""Verify the security policy on public HTML, assets and the deployment receipt."""
import argparse
import ipaddress
import subprocess
from email.parser import Parser
from urllib.parse import urlsplit


TARGETS = (
    "https://shellr.net/",
    "https://shellr.net/assets/app.js",
    "https://shellr.net/deployed.json",
    "https://dma.shellr.net/",
)
REQUIRED = {
    "strict-transport-security": "max-age=31536000",
    "x-content-type-options": "nosniff",
    "x-frame-options": "SAMEORIGIN",
    "referrer-policy": "strict-origin-when-cross-origin",
    "permissions-policy": "camera=(), microphone=(), geolocation=()",
}


def validate(headers):
    for field, expected in REQUIRED.items():
        values = headers.get_all(field, [])
        if len(values) != 1 or expected not in values[0]:
            raise ValueError(f"Missing or unexpected {field}")
    values = headers.get_all("content-security-policy", [])
    if len(values) != 1 or any(
        directive not in values[0]
        for directive in ("default-src 'self'", "object-src 'none'", "frame-ancestors 'none'")
    ):
        raise ValueError("Missing or unexpected content-security-policy")


def check(url, origin_ip=None):
    parsed = urlsplit(url)
    command = [
        "curl", "--noproxy", "*", "--silent", "--show-error", "--fail",
        "--connect-timeout", "4", "--max-time", "12",
        "--header", "Cache-Control: no-cache", "--dump-header", "-", "--output", "/dev/null",
    ]
    if origin_ip:
        command += ["--resolve", f"{parsed.hostname}:443:{origin_ip}"]
    result = subprocess.run(command + [url], capture_output=True)
    if result.returncode:
        raise ValueError(f"HTTP request failed, curl exit {result.returncode}")
    blocks = [
        block for block in result.stdout.decode("iso-8859-1").split("\r\n\r\n")
        if block.startswith("HTTP/")
    ]
    if not blocks or blocks[-1].splitlines()[0].split()[1] != "200":
        raise ValueError("Expected HTTP 200")
    headers = Parser().parsestr(blocks[-1].split("\r\n", 1)[1])
    validate(headers)


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--origin-ip", type=ipaddress.ip_address)
    args = parser.parse_args()
    for target in TARGETS:
        try:
            check(target, args.origin_ip)
        except ValueError as error:
            raise SystemExit(f"{target}: {error}") from None
        print(f"Security headers verified for {target}")
