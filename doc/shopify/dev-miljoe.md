# Shopify integration: development environment

How to run and test the Shopify parts of Saldi locally. Use synthetic data on a tenant named `saldi_shopify_test_<initials>` only; never pos_50 or test_12.

## Webhook endpoint (SD-748)

`POST api/shopify/webhook.php?shop=<shop_key>` stores each Shopify delivery once in `shopify_events` and does nothing else. The worker later turns stored events into jobs (`shopify_promote_events()`).

### The tenant

The endpoint never takes the tenant from the request; a `db` parameter gets 400. It serves the one tenant named by:

1. `SALDI_SHOPIFY_DB`, e.g. in `.env` for docker compose (see `.env.example`), or
2. `includes/shopify/.ht_shopify_db`, one line with the database name (git-ignored, and Apache never serves `.ht*` files).

The tenant must be registered and open in master `regnskab`. Without one, every delivery gets 503 and Shopify retries.

### The shop and its webhook secret

The shop needs a `shopify_shops` row, and its webhook secret is stored as `webhook_secret` in the settings group `shopify:<shop_key>`. For a Shopify app that is the app's client secret, which signs every webhook. Save it through the module, never with `db_modify()` (that would put it in the modify log). An empty secret deletes the setting, so the snippet stops when `HML_WEBHOOK_SECRET` isn't set. From the repo root:

```sh
cd tools && php -r '
include "../includes/connect.php";
require "../includes/shopify/bootstrap.php";
require "../includes/shopify/config.php";
$secret = getenv("HML_WEBHOOK_SECRET");
if ($secret === false || $secret === "") { fwrite(STDERR, "HML_WEBHOOK_SECRET is not set\n"); exit(1); }
shopify_bootstrap("saldi_shopify_test_xx");
shopify_db_transaction(function () use ($secret) {
    shopify_save_shop(["shop_key" => "hml", "domain" => "hml-test.myshopify.com", "is_primary" => true]);
    shopify_save_secret("hml", "webhook_secret", $secret);
});'
```

Each flow stays off until it is switched on (`orders_enabled`, `customers_enabled`, ...). Events of a flow that is off are stored but wait in state `received` until it is switched on.

### Send a test delivery

```sh
BODY='{"id":5,"admin_graphql_api_id":"gid://shopify/Order/5"}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$HML_WEBHOOK_SECRET" -binary | base64)
curl -i -X POST 'http://localhost:5000/saldi/api/shopify/webhook.php?shop=hml' \
  -H 'X-Shopify-Topic: orders/paid' -H "X-Shopify-Hmac-Sha256: $SIG" \
  -H 'X-Shopify-Shop-Domain: hml-test.myshopify.com' -H 'X-Shopify-Event-Id: dev-1' \
  -H 'X-Shopify-API-Version: 2026-07' --data-binary "$BODY"
```

The answer is JSON, `{"status": ...}`:

| HTTP | status | Meaning |
|---|---|---|
| 200 | `stored` | New event, now in the inbox |
| 200 | `duplicate` | Same shop, topic and event id with the same body: nothing new stored |
| 200 | `anomaly` | Same key with another body, or no usable topic or event id: stored as `anomaly` and alarmed (by the worker's next run if the alarm can't be raised at once) |
| 401 | `bad_signature`, `shop_mismatch` | Wrong HMAC, or `X-Shopify-Shop-Domain` isn't this shop's; logged, nothing stored |
| 400 / 404 / 405 | `bad_request`, `unknown_shop`, `method_not_allowed` | Not a delivery for this install |
| 413 | `payload_too_large` | Body over 10 MB (far above any Shopify webhook); not read further, nothing stored |
| 503 / 500 | `unavailable`, `not_configured`, `database_failed`, `failed` | Saldi can't take it now; Shopify retries |

Every HMAC-valid delivery gets 200, so Shopify never retries one that was received (it deletes a subscription that keeps failing). The deduplication key is `(shop_id, topic, event_id)`, never `X-Shopify-Webhook-Id`, which changes on every retry. Rejected deliveries are logged to the PHP error log as `shopify_webhook <outcome> {...}`, without the body, the signature or the secret.

The worker turns `received` events into jobs, `ORDERS_PAID` first. An event it can't route, or whose job can't be queued, gets state `error` and an alarm; after fixing the cause, set it back to `received` to replay it.

Look at the inbox with `SELECT id, topic, event_id, state, job_id FROM shopify_events ORDER BY id DESC LIMIT 20;`.

Real deliveries from a Shopify store need a public HTTPS URL (ssl12 staging or a tunnel) and registered subscriptions; registration comes with SD-749.

## Tests

The `shopify` PHPUnit suite needs no database for its pure tests. The DB-backed ones run on a throwaway clone of a template tenant and skip when the environment isn't set:

```sh
export SALDI_SHOPIFY_PGHOST=localhost SALDI_SHOPIFY_PGUSER=postgres SALDI_SHOPIFY_PGPASS=... \
  SALDI_SHOPIFY_MASTER_DB=saldi SALDI_SHOPIFY_TEMPLATE_DB=saldi_shopify_test_xx SALDI_SHOPIFY_TEST_DB=saldi_shopify_test_c
vendor/bin/phpunit --testsuite shopify
```

`WebhookEndpointTest` also needs the install's `includes/connect.php` on the same master database; it serves the endpoint with `php -S`.

The sandbox check for K03 runs on the server against the deployed endpoint and cleans up after itself:

```sh
php tests/shopify/sandbox/K03.php <tenant_db> <shop_key> https://<host>/<install>/api/shopify/webhook.php
```
