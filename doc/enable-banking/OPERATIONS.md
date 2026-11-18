# Enable Banking - operations guide

How to configure, schedule, deploy, monitor and roll back the Enable Banking bank feed.
Design and data model: `doc/enable-banking/DESIGN.md`.

Pull model (2026-11-16): the cron worker only fetches. Lines go into a journal only when a user
opens the journal, clicks "Bankforbindelse" and picks them ("Læg valgte i kladde N"). See DESIGN.md
"Pull model".

## 1. What runs where

| Piece | Location | Runs as |
|---|---|---|
| Connection, mapping, feed and settings pages | `bank_integration/*.php`, `systemdata/diverseIncludes/bank_integration.php` | web server (browser request) |
| Provider callback (registered redirect URL) | `bank_integration/callback.php` | web server |
| Background fetch (stores lines, never writes a journal) | `bank_integration/worker.php` (CLI) | cron, as the web server user |
| Schema | `bank_integration/includes/ebSchema.php`, `ebSchemaReady()`; the tables are created only by `includes/opdat_func/ebSchemaInstall.php`, called from `includes/betweenUpdates.php` at the company's next login | - |

All bank data lives in each company database (tables `bank_*`). Nothing is stored in the master
database. Provider session ids are encrypted with `bank_integration/includes/crypt.php`
(AES-256-GCM, key file `bank_integration/.ht_oauth_key.bin`).

## 2. Configuration

One Enable Banking application per server (Saldi-hosted model). Values come from environment
variables first, then from globals in the server's gitignored `includes/connect.php`:

| Environment variable | connect.php global | Example / meaning |
|---|---|---|
| `SALDI_ENABLEBANKING_APP_ID` | `$enableBankingAppId` | application id (JWT `kid`) from the Enable Banking control panel |
| `SALDI_ENABLEBANKING_KEY_PATH` | `$enableBankingKeyPath` | absolute path to the application's private key (PEM). MUST be outside the Saldi directory |
| `SALDI_ENABLEBANKING_REDIRECT_URL` | `$enableBankingRedirectUrl` | `https://<host>/<installation>/bank_integration/callback.php`; must be registered on the application exactly |
| `SALDI_ENABLEBANKING_ENV` | `$enableBankingEnvironment` | `SANDBOX` or `PRODUCTION` (informational; the environment is a property of the application id) |

Example `includes/connect.php` lines (placeholders):

```php
$enableBankingAppId = "00000000-0000-0000-0000-000000000000";
$enableBankingKeyPath = "/etc/saldi/enablebanking/00000000-0000-0000-0000-000000000000.pem";
$enableBankingRedirectUrl = "https://ssl12.saldi.dk/ntr/bank_integration/callback.php";
$enableBankingEnvironment = "PRODUCTION";
```

Key file: `install -d -m 750 -o root -g www-data /etc/saldi/enablebanking`, copy the PEM there,
`chown root:www-data`, `chmod 640`. Never commit it, never place it under the web root, never paste
it into tickets or chat. The settings page (Indstillinger » Diverse » Bankforbindelse) shows whether
the configuration is complete and names the first missing piece without revealing values.

Encryption key: `bank_integration/.ht_oauth_key.bin` (32 random bytes) is created by `index/install.php`.
On an existing server create it once if missing:
`sudo -u www-data php -r "file_put_contents('bank_integration/.ht_oauth_key.bin', random_bytes(32));"`
then `chmod 640`. Back it up with the database backups: without it, stored provider sessions
cannot be decrypted and every company must reconnect (accounting data is unaffected). Apache must deny
`.ht*` files (the Debian default does; verify `curl -I https://<host>/<inst>/bank_integration/.ht_oauth_key.bin` returns 403).

Per company: a user with the Finans settings right enables "Aktiver bankforbindelse" under
Indstillinger » Diverse » Bankforbindelse. Turning it off stops fetching for that company and
keeps all history.

### PHP settings

- `max_input_vars`: the kassekladde form posts about 18 fields per line. With PHP's default 1000,
  journals longer than about 50 lines silently lose trailing fields (footer buttons stop working).
  Picking many bank lines into one journal makes long journals more likely, so a server value of at
  least 5000 is recommended (`max_input_vars = 5000` in php.ini).
- Outbound HTTPS to `api.enablebanking.com:443` must be allowed from the web server and the worker.

## 3. Scheduler

Run the worker every 15 minutes; it only fetches accounts whose `next_fetch_at` is due, which limits
background access to at most four fetches per account per day (bank/PSD2 limit for unattended access).

```cron
# /etc/cron.d/saldi-bankfeed
*/15 * * * * www-data cd /var/www/html/<installation> && php bank_integration/worker.php >> /var/log/saldi/bankfeed.log 2>&1
```

- Must run as the web server user (`www-data`); running as root leaves root-owned files in `temp/`
  that break logins.
- Overlapping runs are prevented with a lock file (`temp/.ht_bank_worker.lock`); a second run exits 0.
- Heartbeat: after taking the lock the parent writes the current unix time to
  `temp/.ht_bank_worker.heartbeat`. The status icon turns red ("Den automatiske hentning kører ikke.
  Kontakt Saldi-support.") when an active account is more than 1 hour overdue and the heartbeat is
  missing or older than 1 hour. A missing heartbeat after deploy means the cron entry is missing or runs
  as the wrong user.
- Companies with the feed switched off are skipped before any schema access and print nothing.
- Each company is processed in its own child process; one failing company does not stop the others.
- Exit code 0 = every account ok/skipped/rate limited; 2 = at least one account failed.
- Useful manual commands:
  - `php bank_integration/worker.php --tenant=<db>` one company, due accounts only
  - `php bank_integration/worker.php --tenant=<db> --account=<id> --force` fetch one account now
  - `php bank_integration/worker.php --tenant=<db> --dry-run` list what would run

## 4. Deployment checklist

1. Snapshot/backup the server and company databases (policy for the Saldi VPS: snapshot first).
2. Deploy the code. No manual SQL: the `bank_*` tables are created on the next login of each company
   (`includes/betweenUpdates.php`). The worker does not create them; until that login it prints
   `schema_missing` for the company and skips it.
3. Place the private key and set the four configuration values (section 2). Register the production
   redirect URL on the production application in the Enable Banking control panel. Production
   applications accept only `https` redirect URLs (`http://localhost` is rejected with "uses unsupported
   scheme"), and every installation path needs its own registered URL, since the callback lives under
   the installation (`https://<host>/<installation>/bank_integration/callback.php`).
4. Ensure `bank_integration/.ht_oauth_key.bin` exists and is web-denied.
5. Install the cron entry (section 3).
6. Smoke test on a pilot company: settings page shows "Konfigureret"; connect a whitelisted account
   (restricted production) and map it; the first fetch appears under "Nye" on the feed page and
   nothing is put in a journal; open a journal, click Bankforbindelse, pick a few lines and choose
   "Læg valgte i kladde N"; the lines appear in that journal with continuing voucher numbers; worker log
   line shows `ok`; `temp/.ht_bank_worker.heartbeat` is updated.

## 5. Monitoring

- Worker log: one line per fetched account per run: tenant, account id, status, fetched/new/review.
  Alert when exit code 2 repeats or when `consent_expired`
  appears (customer must reconnect).
- Heartbeat: `temp/.ht_bank_worker.heartbeat` should never be older than the cron interval.
- In the database (per company): `bank_sync_runs` (status, counts, sanitized error),
  `bank_accounts.last_error_code`/`last_error`/`next_fetch_at`, `bank_connections.valid_until`.
- In the UI: the status icon in the finance top line (green/yellow/red, for users who switched on
  "Vis bankstatus i kassekladden"), the feed page panel per account (last successful fetch, last
  journal import, new lines, review, consent days left, last error). The icon tooltip lists new lines waiting to be picked (yellow when the oldest is
  more than 14 days old), "Ingen hentning i N timer" (yellow after 13 hours, red after 30 hours for an
  active account), paused accounts, and the worker heartbeat problem above.
- Logs and the database never contain JWTs, provider session ids (only encrypted), account uids in
  error messages, continuation keys or full IBANs (masked to the last four digits).

## 6. Failure handling (what the worker does)

| Situation | Behaviour |
|---|---|
| Consent expired/revoked | connection marked expired/revoked, no more scheduling, UI shows "Forny adgang" |
| Start date earlier than the bank allows (WRONG_TRANSACTIONS_PERIOD / WRONG_DATE_INTERVAL) | run failed, no automatic retries; UI asks for a later start date; changing the start date clears the error and schedules a fetch |
| Account paused (Pause on the feed page) | not fetched; lines already fetched can still be picked; Genoptag schedules a fetch at once |
| Bank rate limit (429 / ASPSP_RATE_LIMIT_EXCEEDED) | next attempt in 6 hours, no retry loop |
| Transient error (timeout, 5xx) | back-off 30 min, 1 h, 2 h ... capped at 6 h |
| Page 2 of N fails | already stored transactions stay, checkpoint not advanced, next run repeats the window without duplicates |
| Journal open by another user (less than an hour) or posted | the pick is refused with a message, nothing is written, the lines stay under "Nye" |
| Picked line outside the user's fiscal year / closed VAT period / ledger account closed / base currency not DKK | the line stays under "Nye" with the reason; the other picked lines are imported |
| Non-DKK account or no booking date | review item, never a guessed journal line |

## 7. Rollback

- Disable per company: untick "Aktiver bankforbindelse" (stops fetching and picking immediately).
- Disable globally: remove the cron entry and unset `$enableBankingAppId`; the pages show
  "ikke konfigureret" and the journal button disappears.
- Code rollback: redeploy the previous release. The `bank_*` tables are additive and can stay; no
  existing table is altered. The rollback also removes the journal-header lock this release added to
  `bogfor()` in `finans/bogfor.php`; that is safe because the old code has no bank import that could
  race with posting. Journal lines already created are ordinary unposted kassekladde lines and stay valid.
- Revoke provider access: "Afbryd" on the feed page or per connection on the bank accounts page
  (DELETE session at the provider), or revoke the application in the Enable Banking control panel.
  Saldi always disconnects locally. When the bank does not confirm the revoke, the connection keeps
  `last_error` "Banken bekræftede ikke afbrydelsen. Adgangen udløber senest <dato>." and the pages show
  it until the access would have expired. Lines already fetched can still be picked after disconnecting.

## 8. Data handling

Stored per transaction: booking/value/transaction date, signed amount, currency, a built description
(counterparty + remittance text), counterparty name, bank reference, entry reference, hashes for
deduplication, review state. Not stored: raw provider responses, full IBANs of the account or
counterparties, balances. Retention follows the company's accounting data (the transactions are the
source of journal lines); disconnecting keeps them. Access: feed data is visible to users with the
Kassekladde right (picking and skipping need write); connection management requires the Indstillinger right without
read-only (position 1 of `brugere.rettigheder`); actions are audited in
`bank_audit_log`.

## 9. External steps (not code)

- Commercial agreement, KYB and production activation with Enable Banking.
- Production application registration with Saldi's terms, privacy information and data-protection contact.
- Restricted-production test with the team's own whitelisted accounts (MitID/bank consent by an account owner).
  Restricted mode is free and needs no contract. Linking happens in the control panel ("Activate by
  linking accounts"; business accounts ask for the company CVR first) and is separate from Saldi's own
  consent: the same account must then be authorised again through Saldi's connect flow. Unlinked
  accounts are stripped from the provider's responses.
- Customer pilot only after full activation.
