# Self-service signup (oprettelsesflow)

The flow that turns a form on saldi.dk into an activated, logged-in account. Specification and
designs: `docs/oprettelsesflow/` in the landing-page repository (plan.md, velkomstmail-preview.html,
aktiveringsside-preview.html).

```
form on saldi.dk  ->  signup/opret.php    pending row + welcome mail with a one-time link
                  ->  signup/aktiver.php  confirm account name, choose password
                  ->  account created (opret_build_tenant), trial starts, customer logged in
```

## Files

| File | Role |
|---|---|
| `signup/opret.php` | Endpoint for the website's form (JSON or form post). Validates on the server, looks the CVR number up, stores a pending signup and sends the welcome mail. GET shows a plain fallback form. |
| `signup/aktiver.php` | Activation page (`?t=<token>`): all states from the design, creates the account and posts the customer to `index/login.php`. |
| `signup/nytlink.php` | "Få et nyt link" / "Send mailen igen": new token, old one invalidated, same answer whether or not anything was sent. |
| `signup/tjeknavn.php` | Live account-name availability for the activation page (needs a valid token). |
| `signup/cvr.php` | Server-side CVR lookup for the website's form (cached, rate limited). |
| `signup/rydop.php` | Cleanup job (CLI/cron): deletes signups never activated after `cleanup_days`. |
| `includes/signupIncludes/signupFunc.php` | The library: settings, schema, validation, normalisation, tokens, name check, password rules (incl. Have I Been Pwned), activation, billing order. |
| `includes/signupIncludes/signupMail.php` | Mail templates (HTML + text) and transport (PHPMailer over SMTP, or sendmail). |
| `includes/signupIncludes/signupPage.php` | Page frame of the flow's own pages (card layout, CSP nonce). |
| `includes/opretfunc.php` (existing) | `opret_build_tenant()`: master's tenant database build, shared with `admin/opret.php` and `api/signup_api.php` (via `create_regnskab()`). The signup flow reserves the account name and the `regnskab` row itself (one locked transaction) and then calls only the tenant build. |
| `tests/includes/signupFunc.test.php` | PHPUnit tests for the pure functions. |

## Database (master)

Created by `includes/betweenUpdates.php` (master only, from any tenant's login, so the tables exist shortly after a
deploy). Until then every endpoint answers "not ready" (`signup_schema_ready()`); the flow itself runs no DDL, following
`doc/ai/convention_database_changes.md`:

- `signup_pending` - one row per submission. Holds only the SHA-256 of the activation token
  (`token_hash`), its expiry, the previous token's hash (so an old link can still ask for a new one)
  and the resend key's hash. `status`: pending, activating, activated, failed (the account build failed three
  times; the link then asks the customer to call). The `activated` timestamp is set when the account is reserved
  (status `activating`) and again when it is ready (status `activated`); a failed build clears it, so it no longer
  counts toward `max_activations_per_day`. At activation the resend key is cleared; the token hash is kept so the link can still say "already
  activated". `rydop.php` removes only signups that were never activated.
- `signup_events` - rate limiting (kind, ip, email, ts). `ip` is the client address (IPv6 per /64), `email` the
  mailbox (`signup_email_key()`: lower case, without `+tag`, Gmail without dots).
- `signup_cvr_cache` - CVR lookups, kept for `cvr_cache_days`.
- `regnskab` gets `trial_start`, `trial_end` and `pakke`. A new account also gets its package's
  `brugerantal`/`posteringer` (Finans 1/500, Professionel 1/5.000, Business 1/25.000; never 0, which would
  mean unlimited) and `lukkes` = end of trial + `trial_grace_days` (14), so it closes by itself at login
  (`includes/online.php`) unless sales extends or clears the date. `lukkes_kommentar` says so in the admin
  panel. Accounts with a `pakke` do not get the old "+1000 postings for kr. 30,-" offer in `finans/bogfor.php`.
- `settings` (`var_grp 'system'`, `signup_schema`) marks the schema as done, so `betweenUpdates.php` costs one
  query per login after that.

## Settings

Master `settings` table, `var_grp = 'signup'`, or environment `SALDI_SIGNUP_<KEY>` (upper case). The
mail transport also reads the docker stack's `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`,
`SMTP_FROM`, `SMTP_STARTTLS`/`SMTP_TLS`.

| Key | Default | Meaning |
|---|---|---|
| `base_url` | **required** | Absolute url of this installation, e.g. `https://ssl3.saldi.dk/finans`; the activation links are built from it. Never derived from the request (the Host header is the caller's choice). Without it every endpoint answers "not ready". |
| `assets_url` | `https://saldi.dk/assets` | Logo and fonts for mails and pages. |
| `landing_url` | `https://saldi.dk` | The website; `priser_url` and `guide_url` default to `/priser/` and `/vejledninger/` under it. |
| `login_url` | `<base_url>/index/index.php` | Login page. Every package is created on this installation. |
| `login_url_finans` | `login_url` | Only if Finans should log in somewhere else. |
| `from_email`, `from_name`, `reply_to`, `bcc` | `noreply@saldi.dk`, `Saldi`, `support_email`, - | Sender; the sender address is still an open decision (plan.md). |
| `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_secure` | -, 587, -, -, - | SMTP; without a host PHPMailer uses sendmail. `smtp_secure`: `tls` (STARTTLS) or `ssl`. Production uses Resend, see "Mail delivery" below. |
| `allowed_origins` | `https://saldi.dk,https://www.saldi.dk` | Origins allowed to call the endpoints from a browser (CORS), plus `base_url`'s own origin. A POST to `opret.php`/`nytlink.php` with any other `Origin` is refused (403), so other websites cannot make their visitors send signup mails. |
| `cvr_api_url`, `cvr_user_agent`, `cvr_cache_days` | `https://cvrapi.dk/api`, `Saldi oprettelse - <support_email>`, 7 | CVR provider. |
| `locator_url`, `locator_check` | `https://ssl3.saldi.dk/locator/locator.php`, `on` | Cross-server name check, the same locator as `index/login.php`. Asked before the `regnskab` table is locked. The locator only answers allow-listed servers; otherwise the check is skipped and logged once. |
| `hibp_check` | `on` | Reject passwords known from breaches (k-anonymity: 5 hash characters leave the server). |
| `billing_api_base` | - | REST api base (e.g. `https://ssl3.saldi.dk/finans/restapi/endpoints/v1`) of the account that bills; empty = no order. Uses the `internal_api` settings. `billing_sku_<pakke>` overrides the item numbers FINANS/PROFESSIONEL/BUSINESS. Off until the billing integration has been reviewed and tested end to end (it is not part of the launch); the api finds the customer by phone number, so without a phone number no order is created. |
| `trial_days`, `trial_grace_days` | 30, 14 | Trial length; the account closes (`lukkes`) this many days after the trial. |
| `brugere_<pakke>`, `posteringer_<pakke>` | from the package | Override the included users/postings (never 0 = unlimited). |
| `token_days`, `cleanup_days`, `max_mails` | 7, 14, 5 | Link validity, cleanup, activation mails per signup. |
| `max_mails_per_day` | 5 | Signup and new-link mails per mailbox per day. |
| `max_signups_per_hour`, `max_activations_per_day` | 60, 30 | Ceilings for the whole server (mail reputation; each account is a database). |
| `support_phone`, `support_email` | `46 90 22 08`, `info@saldi.dk` | Shown in mails and pages. |

## Mail delivery: set it up through Resend (TODO before go-live)

Decided 2026-10-21: the flow's mails (welcome mail, "new link") are sent through
[Resend](https://resend.com) over SMTP. The server's own mailer is not used:

- On the test servers (ssl12) postfix relays every outgoing mail to Mailpit, a mail catcher
  (http://ssl12.saldi.dk:8025). Nothing reaches a real inbox, which is fine for testing the flow
  but hides delivery problems.
- A production server sending as `@saldi.dk` from its own IP is not covered by saldi.dk's SPF
  record, so Google/Microsoft put the mail in spam or drop it.

To do on the production installation (ssl3):

1. In Resend, add and verify the sending domain (DNS records for SPF and DKIM). `danosoft.dk` is
   already verified in the account; to send as `noreply@saldi.dk`, `saldi.dk` must be added too.
   The sender address itself is still an open decision (plan.md, open point 1).
2. Create an API key with "sending access" only, for this installation.
3. Set these rows in the master `settings` table, `var_grp = 'signup'` (or the `SMTP_*`
   environment variables, which take precedence):

   | Key | Value |
   |---|---|
   | `smtp_host` | `smtp.resend.com` |
   | `smtp_port` | `587` |
   | `smtp_secure` | `tls` (STARTTLS) |
   | `smtp_user` | `resend` |
   | `smtp_pass` | the API key |
   | `from_email` | an address on a verified domain, e.g. `noreply@saldi.dk` |

   The key is a secret: only in the settings table or the environment, never in a tracked file
   (doc/ai/convention_no_hardcoded_secrets.md).
4. Send a signup to a real address and check that the welcome mail arrives (and is not in spam).
   Failures are logged with the prefix `signup:` in the web server's error log.

On ssl12, `/home/ps/signup_smtp_ssl12.php <key> <from> [test-recipient]` (outside the repo) sets
the same rows and sends a test mail.

## Contract with the website's form

`POST signup/opret.php` with `Content-Type: application/json` (or form fields) and
`Accept: application/json`:

```json
{ "name": "Mette Hansen", "cvr": "12345678", "company": "", "email": "mette@firma.dk",
  "phone": "+45 12 34 56 78", "pakke": "business", "source": "prov", "website": "" }
```

`pakke` is `finans`, `professionel` or `business`; `website` is the honeypot and must stay empty.
Answers: `200 {ok:true, resend, email}` (show "Tjek jeres mail"; post `resend` to `nytlink.php` for
"Send mailen igen"), `422 {ok:false, errors:{field: message}}`, `429` too many attempts, `403` a foreign
`Origin`, `503` not ready (schema or `base_url` missing). The CVR lookup for the form is
`GET signup/cvr.php?q=<number or name>`. Field limits: name 60, e-mail 60 (a longer address is refused, not cut,
and may not contain `' & " \ ; < >`, which `index/login.php` strips from usernames), company 90; no web addresses in
name or company. Forms must not put `maxlength="60"` on the e-mail field: the browser would cut the address, and a
cut address can be somebody else's.

## Operations

- Cron: `15 3 * * * cd <install>/signup && php rydop.php >> ../temp/signup-rydop.log 2>&1`
- Log lines are prefixed `signup:` in the web server's error log.
- Rate limits (claimed: recorded first, then counted, so parallel requests cannot all pass): 5 signups/hour
  per address and `max_signups_per_hour` for the server (only submissions that pass validation count),
  `max_mails_per_day` per mailbox, 10 new-link requests/hour per address and one per signup a minute, 40 CVR
  lookups/hour per address (the form's own lookups count too; when they are used up the form answers 429 rather
  than skip the check), 60 account-name checks/10 minutes per address (`tjeknavn.php`), 30 activation
  attempts/hour per address.
- `max_activations_per_day` (default 30) caps the accounts signups may reserve in 24 hours on the server. It is
  counted inside the activation's lock on `regnskab`, so parallel activations cannot all pass it (on PostgreSQL;
  MySQL takes no such lock). Above it the activation page asks the customer to call, and the log says why.

## Known limitations / open points

- Finans (Mini) accounts are created the same way as the others; plan.md's open point 5 (own
  system for Mini) is not resolved here.
- The locator and cvrapi.dk both restrict by server address (the locator allow-lists servers,
  cvrapi.dk's free quota is per address); on a server without access the name check is local only
  and the form falls back to a manually typed CVR number.
- Passwords are stored with `saldikrypt()` to stay compatible with `index/login.php`; the upgrade
  to `password_hash()` is a separate task.

<!-- 20261014 CL/PS created. -->
<!-- 20261021 CL/PS Mail delivery through Resend (decision and setup steps). -->
<!-- 20261102 CL/ASR Ported to master (opret_build_tenant, betweenUpdates.php); package limits, trial closing, billing payload, required base_url, origin check, claimed limits. -->
