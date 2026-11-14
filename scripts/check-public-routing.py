#!/usr/bin/env python3
"""Check the actual public landing page, its identity and required assets."""
import argparse
import json
import subprocess
import sys
import time


GATEWAY_ERRORS = {502, 503, 504}
TRANSPORT_ERRORS = {6, 7, 28, 35, 52, 55, 56}


def fetch(path, local=False, wait_for_gateway=None, timeout=8):
    args = ['curl', '--silent', '--show-error', '--fail', '--connect-timeout', '3', '--max-time', str(timeout)]
    if local:
        args += ['--resolve', 'shellr.net:443:127.0.0.1']
    args += ['--write-out', '\n%{http_code}\n%header{cf-ray}', 'https://shellr.net' + path]
    while True:
        result = subprocess.run(args, capture_output=True)
        body, separator, cf_ray = result.stdout.rpartition(b'\n')
        body, _, status = body.rpartition(b'\n')
        http = int(status) if status.isdigit() else 0
        if http in GATEWAY_ERRORS or (http in (0, 200) and result.returncode in TRANSPORT_ERRORS):
            if wait_for_gateway and (timeout := wait_for_gateway()):
                args[args.index('--max-time') + 1] = str(timeout)
                continue
        if result.returncode or http != 200:
            code = f'HTTP {status.decode()}' if status.isdigit() and status != b'000' else f'curl exit {result.returncode}'
            raise RuntimeError(f'{path}: request failed ({code})')
        if not separator or (not local and not cf_ray.strip()):
            raise RuntimeError(f'{path}: missing Cloudflare response header')
        return body.decode('utf-8')


def check_health(body):
    health = json.loads(body)
    if health.get('status') != 'ok' or health.get('application') != 'shellr-portfolio':
        raise RuntimeError('/health: wrong application answered')


def wait_for_origin():
    deadline = time.monotonic() + 60

    def next_timeout():
        remaining = deadline - time.monotonic()
        if remaining <= 2:
            return 0
        time.sleep(2)
        return max(0, min(2, deadline - time.monotonic()))

    check_health(fetch('/health', local=True, wait_for_gateway=next_timeout, timeout=2))


def check(local=False,rounds=3,retries=0):
    def wait_for_gateway():
        nonlocal retries
        if not retries:
            return False
        retries -= 1
        time.sleep(5)
        return 8

    for _ in range(rounds):
        check_health(fetch('/health',local,wait_for_gateway))
        page=fetch('/',local,wait_for_gateway)
        if '<link rel="canonical" href="https://shellr.net/">' not in page:
            raise RuntimeError('/: missing shellr canonical identity')
        for path in ['/assets/fonts.css','/assets/styles.css','/assets/app.js']:
            if path not in page:raise RuntimeError(f'/: missing reference to {path}')
            body=fetch(path,local,wait_for_gateway)
            if not body.strip() or '<html' in body.lower() or '<!doctype' in body.lower():
                raise RuntimeError(f'{path}: invalid asset response')
    return {'status':'ok','rounds':rounds,'checks':rounds*5,'application':'shellr-portfolio'}


if __name__=='__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('--local',action='store_true',help='Use loopback with correct Host, SNI and certificate validation')
    parser.add_argument('--rounds',type=int,default=3,choices=range(1,31))
    parser.add_argument("--gateway-retries", type=int, default=0, choices=range(13), metavar="0..12",
                        help="Total retries for transient HTTP and transport failures")
    parser.add_argument("--wait-for-origin", action="store_true",
                        help="Wait up to 60 seconds for valid health at the local origin")
    args=parser.parse_args()
    try:
        if args.wait_for_origin:
            wait_for_origin()
        else:
            print(json.dumps(check(args.local,args.rounds,args.gateway_retries)))
    except Exception as error:
        print(f'Public routing check failed: {error}', file=sys.stderr)
        sys.exit(1)
