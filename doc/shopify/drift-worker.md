# Shopify worker: operations

The Shopify module does every Shopify effect as a job in `shopify_jobs`. One worker pass per
minute per tenant claims and runs them (`tools/shopify_worker.php`, WP02, SD-737).

## What a pass does

1. Returns jobs whose lease expired (a crashed pass) to the queue.
2. Inserts the due scheduled slots: price window and counterpart scan every 15 minutes, webhook
   health hourly, stock reconcile at 02:00, overview at 07:00 and purge at 07:15 Copenhagen time.
   Only for shops whose flow is enabled, and only for job types that have a handler.
3. Runs the producers: webhook event promotion (ORDERS_PAID first), then the stock-dirty drain.
4. Claims jobs by priority (lower first) until 50 seconds are spent.

A failed job is retried after 1, 5 and 15 minutes, then hourly, and is `dead` after 52
attempts (about 48 hours). A handler that runs out of time yields with a checkpoint and
continues on the next pass. Each pass writes `temp/<db>/.ht_shopify_worker.heartbeat`, which
the login watchdog reads.

## Running it

    php tools/shopify_worker.php run --db=<tenant> --max-seconds=50

Exit 0 also when another pass for the tenant is still running (the flock on
`temp/<db>/.ht_shopify_worker.lock` is taken). Exit 3: the lock file cannot be opened, nothing
ran. Exit 4: the Shopify schema is not installed on the tenant.

Development: `SALDI_SHOPIFY_DB=<test tenant> docker compose --profile shopify up shopify-worker`,
or `tools/shopify_worker_loop.sh <tenant>` on the host.

Production: the systemd templates in `doc/shopify/systemd/`, one timer per tenant:

    systemctl enable --now shopify-worker@<tenant>.timer

## Open facts for ssl3 (December)

PHP CLI path, the web user that owns `temp/`, and permission to install the timer. The
templates assume `/usr/bin/php`, `/var/www/html/saldi` and `www-data`.

## Looking at the queue

    SELECT state, type, count(*) FROM shopify_jobs GROUP BY 1, 2 ORDER BY 1, 2;
    SELECT id, type, dedupe_key, attempts, next_run_at, last_error FROM shopify_jobs WHERE state = 'dead' ORDER BY id DESC LIMIT 20;
