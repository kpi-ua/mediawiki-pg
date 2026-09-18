#!/bin/sh
# Entrypoint wrapper: decides which LocalSettings.php MediaWiki loads, then
# hands over to the base image's entrypoint.
#
# Order of preference:
#   1. MW_CONFIG_FILE already set by the operator — left untouched.
#   2. /var/www/html/LocalSettings.php present (mounted or baked into a derived
#      image) — the previous behaviour of this image, unchanged.
#   3. Database variables present — the bundled environment-driven
#      configuration at /etc/mediawiki/LocalSettings.php is selected.
#   4. Nothing set — MediaWiki shows the web installer, as before.
set -eu

BUNDLED_CONFIG=/etc/mediawiki/LocalSettings.php
DOCROOT_CONFIG=/var/www/html/LocalSettings.php

db_server="${MW_DB_SERVER:-${MW_DB_HOST:-${MEDIAWIKI_DB_HOST:-${MW_DB_SERVER_FILE:-${MW_DB_HOST_FILE:-}}}}}"

if [ -z "${MW_CONFIG_FILE:-}" ]; then
	if [ -f "$DOCROOT_CONFIG" ]; then
		echo "mediawiki-pg: using $DOCROOT_CONFIG"
	elif [ -n "$db_server" ]; then
		MW_CONFIG_FILE="$BUNDLED_CONFIG"
		export MW_CONFIG_FILE
		echo "mediawiki-pg: using environment-driven $BUNDLED_CONFIG"
	else
		echo "mediawiki-pg: no configuration file and no MW_DB_SERVER; MediaWiki will show the installer" >&2
	fi
fi

# The localisation cache directory is container-local and may sit on a tmpfs or
# an ephemeral volume that is empty at every start.
cache_dir="${MW_CACHE_DIR:-/tmp/mw-cache}"
if mkdir -p "$cache_dir" 2>/dev/null; then
	if [ "$(id -u)" = "0" ]; then
		chown www-data:www-data "$cache_dir" || true
	fi
else
	echo "mediawiki-pg: cannot create $cache_dir; set MW_CACHE_DIR to a writable path" >&2
fi

exec docker-php-entrypoint "$@"
