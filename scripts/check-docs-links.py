#!/usr/bin/env python3
"""Check local HTML links, assets and fragments without network requests."""
import argparse
import hashlib
import time
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlsplit
from urllib.request import Request, urlopen


class Page(HTMLParser):
    def __init__(self, content):
        super().__init__()
        self.ids = set()
        self.links = []
        self.feed(content)

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if values.get('id'):
            self.ids.add(values['id'])
        if tag == 'a' and values.get('name'):
            self.ids.add(values['name'])
        for key in ('href', 'src'):
            if values.get(key):
                self.links.append(values[key])


def check(root, hostname='docs.shellr.net'):
    root = Path(root).resolve()
    pages = {p: Page(p.read_text(encoding='utf-8')) for p in root.rglob('*.html') if not any(part.startswith('.') for part in p.relative_to(root).parts)}
    failures = []
    for path, page in pages.items():
        for value in page.links:
            link = urlsplit(value)
            if link.netloc and link.hostname != hostname:
                continue
            if link.scheme and link.scheme not in ('http', 'https'):
                continue
            target = ((root / unquote(link.path).lstrip('/')) if link.path.startswith('/') or link.netloc else (path.parent / unquote(link.path)) if link.path else path).resolve()
            if not target.is_relative_to(root):
                failures.append(f'{path.relative_to(root)}: link leaves site: {value}')
                continue
            if target.is_dir():
                target /= 'index.html'
            if not target.is_file():
                failures.append(f'{path.relative_to(root)}: missing target: {value}')
            elif link.fragment and target in pages and unquote(link.fragment) not in pages[target].ids:
                failures.append(f'{path.relative_to(root)}: missing fragment: {value}')
    if not pages:
        failures.append('No HTML pages found')
    return len(pages), failures


def verify_published(root, attempts=12, interval=10):
    """The runner verifies fresh HTTPS and exact deployed bytes from another network."""
    names = ['index.html', 'architecture.html', 'projects.html', 'case-studies.html', 'backup.html']
    expected = {name: hashlib.sha256((Path(root) / name).read_bytes()).digest() for name in names}
    for attempt in range(attempts):
        matched = 0
        for name in names:
            try:
                url = 'https://docs.shellr.net/' + ('' if name == 'index.html' else name)
                with urlopen(Request(url, headers={'Cache-Control': 'no-cache'}), timeout=15) as response:
                    content = response.read(2 * 1024 * 1024)
                    matched += response.status == 200 and response.url == url and hashlib.sha256(content).digest() == expected[name]
            except OSError:
                pass
        if matched == len(names):
            print('Published HTTPS documents verified: 5/5; exact bytes match this commit')
            return True
        if attempt + 1 < attempts:
            time.sleep(interval)
    print(f'Published HTTPS documents verified: {matched}/5')
    return False


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('root', nargs='?', default='docs/site')
    parser.add_argument('--verify-published', action='store_true')
    args = parser.parse_args()
    count, failures = check(args.root)
    for failure in failures:
        print(failure)
    print(f'Checked {count} HTML pages; {len(failures)} broken local links/assets/fragments')
    published = not args.verify_published or bool(failures) or verify_published(args.root)
    raise SystemExit(bool(failures) or not published)
