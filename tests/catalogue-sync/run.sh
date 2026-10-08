#!/usr/bin/env bash
#
# Run the catalogue sync receiver tests against a disposable local site.
#
# Starts a throwaway PHP web server for the test images, runs
# test-catalogue-sync.php through WP-CLI, then stops the server. Pass the
# WP-CLI command for your site as the arguments:
#
#   tests/catalogue-sync/run.sh studio wp --path="$HOME/Studio/hkfs-sync-test"
#   tests/catalogue-sync/run.sh wp --path=/path/to/site
#
# The site needs this plugin active with the casket and urn types enabled.
# Never point it at a real site (see the notes in test-catalogue-sync.php).
#
# HKFS_TEST_IMAGE_PORT  port for the image server (default 8765)
# HKFS_TEST_KEEP=1      leave the test products in place afterwards

set -euo pipefail

if [ "$#" -lt 1 ]; then
	echo "Usage: $0 <wp-cli command…>   e.g. $0 wp --path=/path/to/site" >&2
	exit 2
fi

here="$(cd "$(dirname "$0")" && pwd)"
port="${HKFS_TEST_IMAGE_PORT:-8765}"
image_dir="$(mktemp -d "${TMPDIR:-/tmp}/hkfs-sync-images.XXXXXX")"

php -S "127.0.0.1:${port}" -t "$image_dir" >/dev/null 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true; rm -rf "$image_dir"' EXIT

# Wait for the server to accept connections.
for _ in 1 2 3 4 5 6 7 8 9 10; do
	if php -r "exit(@fsockopen('127.0.0.1', ${port}) ? 0 : 1);"; then
		break
	fi
	sleep 0.3
done

"$@" eval-file "$here/test-catalogue-sync.php" "$image_dir" "http://127.0.0.1:${port}"
