#!/usr/bin/env python3
"""Small read-only regression checks of this project's public HTTP surfaces."""
import json
import re
import socket
import ssl
import subprocess
from email.parser import Parser

HOSTS = ('shellr.net', 'dma.shellr.net')
HIDDEN = ('/.env', '/.git/HEAD', '/.git/config', '/backup.sql', '/nonexistent-reaudit-check')


def response(url, method='GET'):
    result = subprocess.run(['curl', '--noproxy', '*', '--silent', '--show-error', '--http1.1',
                             '--connect-timeout', '4', '--max-time', '10', '--max-filesize', '65536',
                             '--include', '--request', method, url], capture_output=True, timeout=15)
    if result.returncode:
        raise ValueError('surface_probe_transport_failed')
    raw, separator, body = result.stdout.partition(b'\r\n\r\n')
    if not separator:
        raise ValueError('surface_probe_headers_missing')
    status, _, fields = raw.decode('iso-8859-1').partition('\r\n')
    return int(status.split()[1]), Parser().parsestr(fields), body


def validate_hidden(status, headers, body):
    if status not in (403, 404):
        raise ValueError('unexpected_public_file_or_error_response')
    if re.search(rb'(?i)(fatal error:|warning:.*\.php|traceback \(most recent call|/var/www/|stack trace:)', body):
        raise ValueError('public_error_discloses_runtime_details')
    if headers.get('x-powered-by') or re.search(r'(?i)(?:php/|apache/|nginx/\d)', headers.get('server', '')):
        raise ValueError('public_error_discloses_runtime_version')


def legacy_protocol_refused(result):
    # A local OpenSSL policy failure does not establish a server-side protocol limit.
    return result.returncode != 0 and b'alert protocol version' in (result.stdout + result.stderr).lower()


def check_tls(host):
    for version in [ssl.TLSVersion.TLSv1_2, ssl.TLSVersion.TLSv1_3]:
        context = ssl.create_default_context()
        context.minimum_version = context.maximum_version = version
        with socket.create_connection((host, 443), timeout=4) as stream:
            with context.wrap_socket(stream, server_hostname=host) as tls:
                if tls.version() not in ('TLSv1.2', 'TLSv1.3'):
                    raise ValueError('public_tls_version_or_certificate_invalid')
    for flag in ['-tls1', '-tls1_1']:
        result = subprocess.run(['openssl', 's_client', '-connect', host + ':443', '--servername', host,
                                 flag, '-cipher', 'ALL:@SECLEVEL=0', '-brief'], input=b'', capture_output=True, timeout=8)
        if not legacy_protocol_refused(result):
            raise ValueError('public_legacy_tls_rejection_not_proven')


def main():
    count = 0
    for host in HOSTS:
        check_tls(host)
        for path in HIDDEN:
            validate_hidden(*response('https://' + host + path))
            count += 1
        status, _, _ = response('https://' + host + '/', 'TRACE')
        if status not in (403, 405, 501):
            raise ValueError('unexpected_public_trace_method')
        count += 1
        status, headers, _ = response('http://' + host + '/')
        if status not in (301, 308) or headers.get('location') != 'https://' + host + '/':
            raise ValueError('public_https_redirect_changed')
        count += 1
    print(json.dumps({'public_tls_and_hostnames_valid': True, 'sensitive_and_missing_paths_rejected': True,
                      'trace_rejected': True, 'https_redirects_valid': True, 'http_requests': count}))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, subprocess.SubprocessError):
        raise SystemExit('Public surface verification failed; inspect the bounded checks') from None
