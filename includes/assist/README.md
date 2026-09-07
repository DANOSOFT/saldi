# Saved-record assistant service

Opt-in JSON service for the saved journal or sales document selected in SALDI.
The bot uses HTTP; no MCP server or accounting schema migration is required.

| Operation | Result |
| --- | --- |
| `get_journal_context` | Saved rows, journal state, draft warning and content revision |
| `validate_journal` | Preliminary findings with row IDs and domestic voucher differences |
| `get_invoice_status` | Saved sales-document state, known blockers and explicit unknown checks |

The service never posts, saves, simulates, sends invoices or runs page controllers.
Journal checks do not reproduce VAT calculations, FX conversion/tolerances,
customer/supplier posting, dimensions or open-item matching. `coverage.can_post`
is always null. Invoice delivery batches, terminal/card choices and other unsaved
form gates remain unknown; `actions[].enabled` is null unless a definite blocker
is established. SALDI remains responsible for final validation.

## Installation

Requires PHP 8.0+, PDO PostgreSQL and mbstring. Master and enabled tenant
databases must be on the same PostgreSQL host. MySQL needs a connection adapter.
Deploy with a chatbot release supporting these operations and `X-Saldi-Record`.
The existing signed context-token integration must be configured on both sides.

Provision a separate PostgreSQL login with CONNECT/USAGE and SELECT only:

- Master: `online`, `regnskab`, and `settings` when present.
- Enabled tenants: `brugere`, `kladdeliste`, `kassekladde`, `tmpkassekl`,
  `kontoplan`, `grupper`, `adresser`, `valuta`, `ordrer`, `ordrelinjer`, plus
  `moms_periode_luk` and `settings` when present.

Do not give this login write permissions or grant access to other companies.
Supply credentials from the installation's secret store, never tracked files.

| SALDI environment | Value |
| --- | --- |
| `SALDI_ASSIST_RECORDS_ENABLED` | `1` after both sides are installed; default off |
| `SALDI_ASSIST_DB_HOST`, `SALDI_ASSIST_DB_PORT` | PostgreSQL host/port; port defaults to 5432 |
| `SALDI_ASSIST_DB_MASTER` | Master database name |
| `SALDI_ASSIST_DB_USER`, `SALDI_ASSIST_DB_PASSWORD` | Dedicated read-only login |
| `SALDI_ASSIST_CONTEXT_SECRET`, `SALDI_ASSIST_KID` | Existing context signing key (at least 32 characters) and its ID |
| `SALDI_ASSIST_HOST_ORIGIN` | Exact public SALDI origin, including a nonstandard port; set explicitly behind a proxy |

Configure the chatbot's `SALDI_RECORD_API_URL` to the fixed HTTPS URL ending in
`/includes/saldi_assist_record.php`, match `CONTEXT_TOKEN_KEYS` to SALDI's key/ID,
and enable `SALDI_RECORDS_ENABLED=true`. Local HTTP requires the chatbot's
explicit `SALDI_RECORD_ALLOW_HTTP=true`; leave it false in production.

## Access and data flow

The browser reads only record ID and dirty state, never unsaved form values.
A same-origin JSON POST using the live login obtains a five-minute `r1` grant
bound to company, user, widget session and record. The grant contains no raw
login cookie or database name. The bot verifies it alongside the existing `v1`
context token; each PHP tool call rechecks the live session, company and tenant
module rights. Unknown/blank rights and users missing from `brugere` fail closed.
Special auditor/superuser arrangements need installation-specific verification.

Calls use independent READ ONLY, REPEATABLE READ transactions with timeouts,
prepared statements and bounded snapshots. Revisions cover saved data and
relevant configuration. Stale reads return 409. Highlighting rechecks the
revision and refuses dirty/different records. `tmpkassekl` triggers a saved-draft
warning; journals exceeding 2,000 saved rows fail explicitly.

Saved descriptions, amounts and findings can enter model context and retained
chat answers. The widget discloses this saved-data flow. Include it in the
installation's existing model-provider and retention configuration.

## Verification and rollback

See [tests and manual checks](../../tests/assist/README.md). Disable both feature
flags to return to documentation-only chat. Shared predicates preserve the
existing balance/payment conditions and can stay installed. No migration needs
reversing.
