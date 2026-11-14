#!/usr/bin/env python3
"""Check the actual public landing page, its identity and required assets."""
import argparse
import json
import subprocess
import sys
import time


def fetch(path, local=False, wait_for_gateway=None):
    args = ['curl', '--silent', '--show-error', '--fail', '--connect-timeout', '3', '--max-time', '8']
    if local:
        args += ['--resolve', 'shellr.net:443:127.0.0.1']
    args += ['--write-out', '\n%{http_code}\n%header{cf-ray}', 'https://shellr.net' + path]
    while True:
        result = subprocess.run(args, capture_output=True)
        body, separator, cf_ray = result.stdout.rpartition(b'\n')
        body, _, status = body.rpartition(b'\n')
        if status in (b'502', b'503', b'504') and wait_for_gateway and wait_for_gateway():
            continue
        if result.returncode:
            code = f'HTTP {status.decode()}' if status.isdigit() and status != b'000' else f'curl exit {result.returncode}'
            raise RuntimeError(f'{path}: request failed ({code})')
        if not separator or (not local and not cf_ray.strip()):
            raise RuntimeError(f'{path}: missing Cloudflare response header')
        return body.decode('utf-8')


def check(local=False,rounds=3,retries=0):
    def wait_for_gateway():
        nonlocal retries
        if not retries:
            return False
        retries -= 1
        time.sleep(5)
        return True

    for _ in range(rounds):
        health=json.loads(fetch('/health',local,wait_for_gateway))
        if health.get('status')!='ok' or health.get('application')!='shellr-portfolio':
            raise RuntimeError('/health: wrong application answered')
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
                        help="Total retries for HTTP 502, 503 or 504 across all checks")
    args=parser.parse_args()
    try:print(json.dumps(check(args.local,args.rounds,args.gateway_retries)))
    except Exception as error:print(f'Public routing check failed: {error}',file=sys.stderr);sys.exit(1)
