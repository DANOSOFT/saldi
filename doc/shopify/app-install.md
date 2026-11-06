# Shopify app install

Each shop gets its own app in Danosoft's Shopify Partner organization, with Custom
distribution. Saldi gets the shop's Admin API token through Shopify's authorization code
grant (`api/shopify/oauth.php`, SD-790). The token is offline and does not expire, so there
is no refresh step.

## 1. Create the app (Partner Dashboard)

1. Apps → Create app → Create app manually. Name it after the shop.
2. Configuration:
   - App URL: `https://<saldi host>/<install>/api/shopify/oauth.php`
   - Allowed redirection URL: the same URL.
   - Embedded in Shopify admin: off.
3. Distribution → Custom distribution → the shop's `*.myshopify.com` domain. Shopify then
   shows the install link. Keep it for step 3.
4. Note the client ID and the client secret (Overview → Client credentials).
5. API access → Protected customer data access: request access to protected customer data,
   and to the name, email, phone and address fields. The order and customer flows need them,
   and Shopify leaves them out of the API responses until this step is done.

The `<saldi host>/<install>` part is the install's base URL. It must be HTTPS. Until the
fixed host exists, use a tunnel to a dev install.

## 2. Configure Saldi

The shop must already exist in Saldi (Systemdata → Shopify) with its `*.myshopify.com`
domain. Then, on the server:

    php tools/shopify_worker.php set-app --db=<tenant> --shop=<shop_key> --client-id=<client id>

Paste the client secret on the next line and press Enter. It is stored as the shop's webhook
secret, because Shopify signs the app's webhooks with it. Then store the base URL once
per install, if it isn't stored yet:

    php tools/shopify_worker.php register-webhooks --db=<tenant> --base-url=https://<saldi host>/<install>

Before the app is installed this command reports each topic as failed (no token yet). That is
expected: the base URL is stored anyway.

## 3. Install it in the shop

The shop owner (or a staff member allowed to install apps) opens the install link from
step 1 and clicks **Install**. Shopify shows the access the app asks for and redirects back
to Saldi. Saldi checks the request's signature, stores the token and returns to the shop's
Apps page. The next worker run subscribes the shop's webhooks.

If the page shows `Saldi: not_configured`, step 2 is missing. `Saldi: bad_signature` means the
client secret in Saldi is not this app's.

## Uninstall

When the app is uninstalled in the shop, Shopify sends `app/uninstalled`. The worker then
deletes the token and switches all of the shop's flows off, and the error list shows
`shop.uninstalled`. To connect the shop again, open the install link again and switch the
flows back on.
