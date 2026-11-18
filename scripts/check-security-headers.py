#!/usr/bin/env python3
"""Verify the security policy on public HTML, assets and the deployment receipt."""
import argparse
import ipaddress
import re
import subprocess
from email.parser import Parser
from urllib.parse import urlsplit


TARGETS = (
    "https://shellr.net/",
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
        "--header", "Cache-Control: no-cache", "--include",
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
    return result.stdout.split((blocks[-1] + "\r\n\r\n").encode("iso-8859-1"), 1)[1]


def published_asset(page):
    match = re.search(r'''<script\b[^>]*\bsrc=["'](/assets/app\.js\?v=[a-f0-9]{16})["']''', page)
    if match is None:
        raise ValueError("The landing page does not reference a versioned application script")
    return "https://shellr.net" + match[1]


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--origin-ip", type=ipaddress.ip_address)
    args = parser.parse_args()
    for target in TARGETS:
        try:
            body = check(target, args.origin_ip)
            print(f"Security headers verified for {target}")
            if target == TARGETS[0]:
                target = published_asset(body.decode("utf-8"))
                check(target, args.origin_ip)
                print(f"Security headers verified for {target}")
        except ValueError as error:
            raise SystemExit(f"{target}: {error}") from None
