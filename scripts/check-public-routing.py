#!/usr/bin/env python3
"""Check the actual public landing page, its identity and required assets."""
import argparse
import json
import subprocess
import sys


def fetch(path, local=False):
    args=['curl','--silent','--show-error','--fail','--connect-timeout','3','--max-time','8']
    if local:args+=['--resolve','shellr.net:443:127.0.0.1']
    result=subprocess.run([*args,'https://shellr.net'+path],capture_output=True)
    if result.returncode:raise RuntimeError(f'{path}: request failed ({result.returncode})')
    return result.stdout.decode('utf-8')


def check(local=False,rounds=3):
    for _ in range(rounds):
        health=json.loads(fetch('/health',local))
        if health.get('status')!='ok' or health.get('application')!='shellr-portfolio':
            raise RuntimeError('/health: wrong application answered')
        page=fetch('/',local)
        if '<link rel="canonical" href="https://shellr.net/">' not in page:
            raise RuntimeError('/: missing shellr canonical identity')
        for path in ['/assets/fonts.css','/assets/styles.css','/assets/app.js']:
            if path not in page:raise RuntimeError(f'/: missing reference to {path}')
            body=fetch(path,local)
            if not body.strip() or '<html' in body.lower() or '<!doctype' in body.lower():
                raise RuntimeError(f'{path}: invalid asset response')
    return {'status':'ok','rounds':rounds,'checks':rounds*5,'application':'shellr-portfolio'}


if __name__=='__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('--local',action='store_true',help='Use loopback with correct Host, SNI and certificate validation')
    parser.add_argument('--rounds',type=int,default=3,choices=range(1,31))
    args=parser.parse_args()
    try:print(json.dumps(check(args.local,args.rounds)))
    except Exception as error:print(f'Public routing check failed: {error}',file=sys.stderr);sys.exit(1)
