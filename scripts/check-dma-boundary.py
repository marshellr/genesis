#!/usr/bin/env python3
"""Verify that the public DMA surface exposes static content only."""
import argparse
import ipaddress
import subprocess


PHP_PATHS = (
    "/index.php", "/champion-pool.php", "/overall-stats.php", "/health.php",
    "/inc/env.php", "/inc/db.php", "/backend/index.php", "/backend/health.php",
)


def check(path, expected, origin_ip=None):
    args = ["curl", "--noproxy", "*", "--silent", "--show-error",
            "--connect-timeout", "4", "--max-time", "12",
            "--output", "/dev/null", "--write-out", "%{http_code}"]
    if origin_ip:
        args += ["--resolve", f"dma.shellr.net:443:{origin_ip}"]
    result = subprocess.run(args + ["https://dma.shellr.net" + path], capture_output=True)
    if result.returncode or result.stdout.strip() != str(expected).encode():
        raise ValueError(f"{path} must return HTTP {expected}, curl exit {result.returncode}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--origin-ip", type=ipaddress.ip_address)
    options = parser.parse_args()
    try:
        check("/", 200, options.origin_ip)
        check("/health.txt", 200, options.origin_ip)
        for path in PHP_PATHS:
            check(path, 404, options.origin_ip)
    except ValueError as error:
        raise SystemExit(str(error)) from None
    print("DMA showcase and static health respond; all checked PHP endpoints return 404")
