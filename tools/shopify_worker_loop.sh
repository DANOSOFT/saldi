#!/bin/sh
# Shopify worker loop for development (WP02): runs one worker pass per minute for one tenant,
# like the systemd timer does in production. Usage: tools/shopify_worker_loop.sh <tenant> [pause-seconds]
# 20261026 Sawaneh SD-737: created (WP02-2).
set -u
db="${1:?usage: tools/shopify_worker_loop.sh <tenant> [pause-seconds]}"
pause="${2:-10}"
cd "$(dirname "$0")/.." || exit 1
while :; do
	php tools/shopify_worker.php run --db="$db" --max-seconds=50
	sleep "$pause"
done
