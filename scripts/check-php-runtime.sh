#!/usr/bin/env sh
# Run inside a built image; no network, credentials or application data needed.
set -eu
case "${1:-}" in
  app) required='pdo_mysql' ;;
  dma) required='mysqli curl dom' ;;
  *) exit 2 ;;
esac
for module in $required sodium sqlite3 pdo_sqlite; do
  php -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$module"
done
php -r '$db=new SQLite3(":memory:");exit($db->querySingle("SELECT 1")===1?0:1);'
if dpkg-query -W -f='${binary:Package} ${db:Status-Status}\n' | awk '
  $2=="installed" && ($1 ~ /-dev(:[^ ]*)?$/ || $1 ~ /^(gcc|g\+\+|cpp|make|autoconf|automake|pkg-config|pkgconf|re2c|binutils)(-[0-9]+)?(:[^ ]*)?$/) {found=1;print $1} END {exit !found}'; then
  echo 'Build packages must not be present in the runtime image' >&2
  exit 1
fi
for binary in /usr/local/bin/php /usr/lib/apache2/modules/libphp.so /usr/local/lib/php/extensions/*/*.so; do
  if ldd "$binary" | grep -q 'not found'; then
    echo 'A required runtime library is missing' >&2
    exit 1
  fi
done
apache2ctl -t
test -x /usr/bin/htpasswd
test ! -e /usr/local/bin/phpize
echo 'PHP extensions, shared libraries, Apache configuration and runtime package boundary verified'
