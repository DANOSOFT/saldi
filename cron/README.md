# Mobile scheduled jobs

Install this block once on each Saldi installation as the web service user. Adapt the absolute root path. Both commands read that installation's ignored `includes/connect.php` and enumerate its registry; no host list is required.

```cron
# Generic push delivery and receipt polling; tenant advisory locks prevent overlaps.
* * * * * /usr/bin/php /var/www/html/saldi/cron/push_dispatch.php
# Seven-day abandoned upload reservations become retained tombstones, daily.
10 2 * * * /usr/bin/flock -n /var/www/html/saldi/temp/mobile-staged-expiry.lock /usr/bin/php /var/www/html/saldi/restapi/models/attachment/expire-staged.php
# Tier 2: payment link recovery (poll pending attempts, settle captured payments), every minute.
* * * * * /usr/bin/php /var/www/html/saldi/cron/payment_links.php
# Tier 3: event outbox fan-out to notifications, and approval file-move reconciliation, every minute.
* * * * * /usr/bin/php /var/www/html/saldi/cron/mobile_events.php
# Tier 3: event scanners (invoice paid/overdue, webshop orders, low stock), every 15 minutes.
*/15 * * * * /usr/bin/php /var/www/html/saldi/cron/mobile_scanners.php
# Tier 3: bookkeeper summary cache warmer, every 5 minutes.
*/5 * * * * /usr/bin/flock -n /var/www/html/saldi/temp/mobile-bookkeeper-summary.lock /usr/bin/php /var/www/html/saldi/cron/bookkeeper_summary.php
```

Each script also accepts `--tenant-id=<registry id>` for a diagnostic run against one open tenant and is CLI only. The payment, event and scanner jobs use tenant advisory locks against overlapping runs; the summary warmer has none, so `flock` serializes it.

The service user needs database access and write access to the document store and `temp/`. Supply any `SALDI_EXPO_ACCESS_TOKEN` through the service environment or protected local configuration. Leave `SALDI_EXPO_PUSH_URL` and `SALDI_EXPO_RECEIPTS_URL` unset for the standard Expo relay; tests override both with a local stub.

Push runs one bounded batch per open tenant, rejects ambiguous registration identities before sending, polls tickets and applies 12-month inbox retention. `--tenant-id=<positive registry id>` restricts a diagnostic push run. JSON output contains counts and tenant ids; exit 1 means at least one tenant failed, exit 0 means the run succeeded. Inspect job output without logging request bodies or tokens.

Expiry visits all locally registered tenant databases, expires staged rows older than seven days in batches of 100, skips locked uploads, retains surviving PDFs and identities, and leaves unprocessed/attached rows alone. It reports `{expired:int,failed:bool}`, with exit 1 if a tenant failed. Its CLI-only script is HTTP 404; push is HTTP 403. `flock` serializes expiry invocations; push uses database advisory locks.

These are deployment instructions. No cron entry is installed by the build.

<!-- 20261001 CDX/LH Created. -->
<!-- 20261007 CL/LH List the Tier 2-3 jobs (payment links, events, scanners, bookkeeper summary). -->
