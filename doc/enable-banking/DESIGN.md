# Enable Banking integration - design

Status: 2026-11-16, pull model. Companion: `doc/enable-banking/OPERATIONS.md` (configuration,
scheduler, deployment, monitoring). Code: `bank_integration/`, tests:
`tests/characterization/bank_integration/`.

## Pull model (2026-11-16)

Lines reach a journal only when a user picks them, into the journal the user is working in.

- Fetching is automatic: the worker fetches each active account at most four times a day (every 6
  hours), plus "Hent nu" on the feed page (once per 5 minutes per account). A fetch only stores lines
  in `bank_transactions`; it never writes a journal. There are no bank-owned journals, no automatic
  import and no journal line cap.
- The kassekladde button "Bankforbindelse" (open journals, company enabled) saves the journal like
  the CSV "Importér" button, with the same save replay guards, and opens
  `bank_integration/feed.php?kladde_id=<id>`.
- Feed page with `kladde_id` (validated: exists, `bogfort = '-'`): the "Nye" tab (review_state
  `pending`) is a pick list (checkbox per row, select all on the page, date filter on the accounting
  date `COALESCE(date_override, booking_date)`, overlap hints that never preselect a row) with
  "Læg valgte i kladde N" and "Spring valgte over". Without `kladde_id` there is no import button,
  only a hint to open the journal and click Bankforbindelse, with a link to the journal list; skip
  and restore still work.
- `ebImportIntoJournal(int $kladdeId, int $accountId, array $ids, string $username): array{imported:int,
  skipped:list<array{id:int, reason:string}>}` in `ebJournal.php`:
  - takes the journal's save lock (`kkJournalLock()`, the lock a kassekladde save holds from staging
    to cleanup), then one transaction with `SELECT ... FROM kladdeliste WHERE id = N FOR UPDATE`;
  - refuses, writing nothing, when the journal does not exist, is posted or simulated
    (`bogfort <> '-'`), or is open by another user (`hvem` not empty and not this user, and the lock
    in `tidspkt` younger than 3600 s, the rule kassekladde uses); the user's own lock and an expired
    lock are accepted;
  - ids: 1..200 integers that must all belong to the account (otherwise nothing is written). Amounts,
    dates and texts are read from the database, never from the request;
  - a line no longer pending (already imported, skipped) is reported as `not_pending`, so a repeated
    request imports nothing;
  - per line, in accounting-date order: base currency not DKK -> `base_currency`; accounting date
    outside the user's active fiscal year (session `$regnaar`, `grupper art='RA'`) ->
    `outside_fiscal_year`; closed VAT month (`moms_periode_luk`) -> `closed_period`; ledger account
    missing, closed or not D/S in that year -> `no_ledger_account`. Such lines stay `pending` with the
    reason in `review_reason`, which the "Nye" tab shows; the rest are imported;
  - an imported line is an unposted kassekladde row: voucher number from `bilagNextNumberForJournal()`
    (journal max + 1, else fiscal-year max + 1), `pos` = journal max + 1, the account's ledger account
    on the debet side for money in and the kredit side for money out, other side empty, no VAT,
    `valuta` 0, `beskrivelse` = the bank text. A `bank_import_links` row (disposition active), the
    transaction set `imported` with `link_id`, `last_import_at` and one audit row (`import_selected`).
- After an import with at least one line the user is sent back to the journal
  (`kassekladde.php?tjek=N&kladde_id=N`), which shows once how many lines went in and how many stayed
  under Nye (`ebJournalNotice()`, `$_SESSION['eb_journal_notice']`). When nothing was imported the
  user stays on the feed with the message, and the reasons are on the rows.
- Rows under "Til gennemsyn" are moved to "Nye" by an explicit action (possible duplicate / legacy
  after_review: "Flyt til Nye"; missing booking date: "Brug valørdato"); a line whose journal line was
  deleted can be moved to "Nye" again. "Sprunget over" rows can be restored.
- Posting code (`finans/bogfor.php`) is untouched: the import takes the header lock itself and only
  writes into an open journal nobody else holds.
- Account states: `new` (needs ledger account and start date), `active`, `paused` (not fetched; lines
  can still be picked), `unsupported` (not DKK), `disconnected` (lines can still be picked). Pause /
  Genoptag, disconnect, reconnect and mapping need the settings right; picking and skipping need write
  on Kassekladde.
- Rights (master's roles model, `perm_can()` in `includes/permissions.php`): `ebCanManage()` =
  write on `settings.finance` (the settings area the Bankforbindelse section belongs to; connect,
  accounts and callback declare the same `$permission_key`), `ebCanViewFeed()` = read on
  `finans.kassekladde`, `ebCanActOnFeed()` = write on `finans.kassekladde`.
- Status icon (`auth_check_icon.php`, kassekladde and finans top lines): shown to users who switched
  on "Vis bankstatus i kassekladden" (Personlige indstillinger, setting `bank_integration/show_status`).
  It lists "<konto>: N nye bankposteringer (ældste <d.m.Y>)" (yellow after 14 days), review rows,
  expiring or expired access, "Ingen hentning i N timer" for an overdue active account and a red
  "Den automatiske hentning kører ikke" when the worker heartbeat is missing or older than an hour.
- The notification center item `bank:expired` (texts 5632/5633) is raised while a fetching account's
  access has expired or been revoked, and links to the feed.
- Texts: every user-facing text goes through `findtekst('bank.<area>.<name>|Dansk', ...)`, rows in
  `importfiler/tekster_keyed.tsv`. The bank pages are UTF-8 (`ebT()` converts findtekst output);
  texts printed inside tenant-charset pages (settings section, status icon, CSV import notice) use the
  tenant's charset (`ebPageH()`).
- Out of scope: FIK parsing, balance check, automatic matching, master-DB tenant registry, e-mails.

## Scope and decisions

- Replaces the unused Aiia integration (its routes, OAuth credential rows and settings are removed).
- DKK only. Non-DKK accounts can be linked but are shown as unsupported; their transactions are stored
  as review items and never reach a journal.
- Saldi-hosted only: one provider application per server, configured outside the repository (env vars
  or `includes/connect.php` globals). No key-upload UI.
- Account matching: identification hashes that occur for more than one account in the same session are
  ignored; the primary hash is matched first, a secondary hash only when it is unique, the currency is
  equal and the local account is not already claimed in the same session. Hashes proven ambiguous are
  recorded permanently in `bank_ambiguous_hashes`.
- Text from banks can contain literal HTML entities (`&amp;`); normalization decodes them before hashing.
- Closed ledger accounts are excluded from mapping and import.
- Account reset (`systemdata/resetAccount.php`) marks active and posted links deleted.

## 2. Configuration (`bank_integration/includes/ebConfig.php`)

```php
/** @return array{app_id: string, key_path: string, redirect_url: string, environment: string, configured: bool, problem: string} */
function ebConfig(): array
```
Sources, first non-empty wins per field: `getenv('SALDI_ENABLEBANKING_APP_ID')`,
`getenv('SALDI_ENABLEBANKING_KEY_PATH')`, `getenv('SALDI_ENABLEBANKING_REDIRECT_URL')`,
`getenv('SALDI_ENABLEBANKING_ENV')`; then globals from `includes/connect.php`:
`$enableBankingAppId`, `$enableBankingKeyPath`, `$enableBankingRedirectUrl`, `$enableBankingEnvironment`.
`environment` is 'SANDBOX' or 'PRODUCTION' (default 'SANDBOX'). `configured` is true only when app id,
redirect URL and a readable key file exist and the key file is OUTSIDE the Saldi installation
directory (realpath not under `dirname(__DIR__, 2)`); otherwise `problem` names the first missing
piece (never the key contents). `ebFeatureAvailable(): bool` = configured.

## 3. Schema (tenant DB) - `bank_integration/includes/ebSchema.php`

`ebEnsureSchema(): void` creates missing tables/indexes idempotently, portable PostgreSQL/MySQL
(branch on `$db_type`: `SERIAL` vs `INT AUTO_INCREMENT`, `NUMERIC` vs `DECIMAL`, `TIMESTAMP` vs
`DATETIME`, `TEXT`). Uses `tbl_exists()` per table and catalog checks per index (pg_indexes /
information_schema.statistics). Never runs inside an import transaction. Called from
the end of `includes/betweenUpdates.php`, from the worker before processing a tenant, from the settings
save and from tests. Also exposes `ebSchemaReady(): bool`.

Timestamps are stored as `TIMESTAMP`/`DATETIME` in server local time, written as
`date('Y-m-d H:i:s')`. Dates as `DATE`. Amounts `NUMERIC(15,3)`/`DECIMAL(15,3)`.

### bank_connections
| column | type | notes |
|---|---|---|
| id | serial PK | |
| provider | varchar(20) | 'enablebanking' |
| environment | varchar(12) | SANDBOX / PRODUCTION at creation |
| aspsp_name | varchar(100) | |
| aspsp_country | varchar(2) | |
| psu_type | varchar(10) | business / personal |
| session_enc | text | `oauthEncrypt(session_id)` |
| session_hash | varchar(64) | sha256(session_id), unique, for lookup without decrypt |
| status | varchar(20) | active, expired, revoked, disconnected, replaced, error |
| valid_until | timestamp null | consent expiry from provider |
| created_by | varchar(100) | username |
| created_at, updated_at | timestamp | |
| disconnected_at | timestamp null | |
| disconnected_by | varchar(100) null | |
| last_error | text null | sanitized |

### bank_accounts (stable local account identity)
| column | type | notes |
|---|---|---|
| id | serial PK | stable local account id, survives reconnects |
| connection_id | int null | current connection |
| identification_hash | varchar(255) | primary hash from provider |
| display_name | varchar(200) | account `name`/`details`/product, user-editable later |
| display_identity | varchar(64) | masked IBAN/other: keep country+check digits and last 4, e.g. `DK50 **** **** 1234` |
| currency | varchar(3) | |
| cash_account_type | varchar(4) | |
| state | varchar(20) | new (needs mapping), active, paused, unsupported (non-DKK), disconnected |
| ledger_account | int null | kontoplan.kontonr of the bank account in the chart (the bank side) |
| import_from | date null | cutover: transactions booked before this are kept as `pre_cutover` |
| fetched_through | date null | checkpoint: last date_to whose whole window was fully persisted |
| last_fetch_at | timestamp null | last fully successful fetch |
| last_import_at | timestamp null | last time >=1 picked line went into a journal |
| next_fetch_at | timestamp null | scheduler due time; null = not scheduled |
| fail_count | int default 0 | consecutive transient failures |
| last_error_code | varchar(60) null | provider ErrorCode or internal code |
| last_error | text null | sanitized human text |
| lease_owner | varchar(64) null | |
| lease_until | timestamp null | |
| last_manual_at | timestamp null | throttle user-triggered refresh |
| created_at, updated_at | timestamp | |
Unique: (identification_hash).

### bank_account_aliases
id serial PK, account_id int, identification_hash varchar(255) UNIQUE. Every hash from
`identification_hashes` (including the primary) of every session the account appeared in.

### bank_account_sessions
id serial PK, account_id int, connection_id int, account_uid varchar(64), active smallint,
created_at. Unique (connection_id, account_uid). On (re)connect the previous active row for the
account is set active=0.

### bank_transactions
| column | type | notes |
|---|---|---|
| id | serial PK | the "local transaction id" the browser may submit |
| account_id | int | |
| dedup_key | varchar(160) | see section 5; NOT NULL |
| entry_reference | varchar(255) null | |
| fingerprint | varchar(64) | sha256 of normalized content (section 5) |
| payload_hash | varchar(64) | sha256 of the normalized accounting fields, for change detection |
| booking_date | date null | |
| value_date | date null | |
| transaction_date | date null | |
| amount | numeric(15,3) | SIGNED: positive = money into the account (CRDT), negative = out (DBIT) |
| currency | varchar(3) | |
| description | varchar(255) | built text (section 5) |
| counterparty | varchar(140) null | creditor/debtor name of the other party |
| bank_reference | varchar(140) null | reference_number |
| status | varchar(4) | provider status, only BOOK rows are stored |
| review_state | varchar(20) | pending ("Nye"), imported, review, ignored, pre_cutover |
| review_reason | varchar(40) null | review rows: missing_date, unsupported_currency, bad_amount, possible_duplicate, changed_after_import (after_review only on old rows). Pending rows a pick could not import: outside_fiscal_year, closed_period, no_ledger_account, base_currency |
| identical_group | smallint default 0 | 1 when another stored row of the same account shares fingerprint (shown as a hint) |
| changed_payload | text null | JSON of the newer provider values when changed after import |
| link_id | int null | current bank_import_links.id |
| first_seen_at, last_seen_at | timestamp | |
| first_run_id | int null | bank_sync_runs.id |
Unique: (account_id, dedup_key). Index: (account_id, review_state), (account_id, booking_date).

### bank_import_links
| column | type | notes |
|---|---|---|
| id | serial PK | |
| transaction_id | int | bank_transactions.id |
| account_id | int | |
| kladde_id | int | journal |
| kassekladde_id | int | generated line id; UNIQUE (a journal line has at most one source transaction) |
| bilag | int | voucher allocated |
| disposition | varchar(20) | active, posted, deleted, replaced |
| created_at | timestamp | |
| created_by | varchar(100) | the username that picked the line |
| disposition_at | timestamp null | |

### bank_sync_runs
id serial PK, account_id int, trigger varchar(12) (schedule, manual), started_at,
finished_at null, status varchar(20) (running, ok, partial, failed, rate_limited, consent_expired,
skipped), window_from date null, window_to date null, pages int, fetched int,
new_count int, changed_count int, imported_count int (always 0 since the pull model), review_count int, error_code varchar(60) null,
error_message text null (sanitized, no tokens/session ids/IBANs).

### bank_audit_log
id serial PK, at timestamp, actor varchar(100) ('worker' or username), action varchar(40)
(connect_started, connected, reconnected, mapped, refresh, disconnected, enabled, disabled, ignored,
restored, reimport, import_review, import_selected, paused, resumed), account_id int null,
connection_id int null, detail varchar(255) null (sanitized).

### Company setting
`settings` row `var_grp='enable_banking'`, `var_name='enabled'`, `user_id=0`, value 'on' or ''.
Read/write with explicit `user_id = 0` filter (do NOT use get_settings_value() with a null user).
Helpers `ebCompanyEnabled(): bool`, `ebSetCompanyEnabled(bool $on, string $actor): void`.

## 4. Provider client - `bank_integration/includes/ebClient.php`

```php
interface EbTransport {
    /** @return array{status: int, body: string, error: string} status 0 = transport failure */
    public function send(string $method, string $url, array $headers, ?string $body): array;
}
final class EbCurlTransport implements EbTransport { /* 10 s connect, 30 s total, TLS verify on */ }
final class EbApiException extends RuntimeException {
    public function __construct(public readonly int $httpStatus, public readonly string $errorCode, string $message);
    public function kind(): string; // 'consent' | 'action_required' | 'rate_limit' | 'transient' | 'continuation' | 'permanent'
}
final class EnableBankingClient {
    public function __construct(array $config, ?EbTransport $transport = null, ?callable $clock = null);
    public function jwt(): string;                                  // RS256, kid=app_id, iss enablebanking.com, aud api.enablebanking.com, exp now+3600, cached until 5 min before exp
    public function application(): array;                           // GET /application
    public function aspsps(string $country = 'DK', ?string $psuType = null): array; // returns list
    public function startAuth(string $aspspName, string $country, string $psuType, string $state, string $validUntilIso, string $language = 'da'): array; // {url, authorization_id}
    public function createSession(string $code): array;             // POST /sessions
    public function getSession(string $sessionId): array;
    public function deleteSession(string $sessionId): void;
    /** @return array{transactions: array<int, array>, continuation_key: ?string} */
    public function transactions(string $accountUid, string $dateFrom, string $dateTo, ?string $continuationKey, array $psuHeaders = []): array;
}
```
- Base URL `https://api.enablebanking.com`. JSON request/response; reject non-JSON or wrong shape as
  `EbApiException(status, 'INVALID_RESPONSE', ...)` kind transient.
- Error classification from `.codex-run/eb-api-notes.md` (consent: EXPIRED_SESSION, REVOKED_SESSION,
  CLOSED_SESSION, SESSION_DOES_NOT_EXIST, ACCOUNT_DOES_NOT_EXIST, ASPSP_ACCOUNT_NOT_ACCESSIBLE,
  UNAUTHORIZED_ACCESS, WRONG_SESSION_STATUS; action_required: ASPSP_PSU_ACTION_REQUIRED; rate_limit:
  HTTP 429 or ASPSP_RATE_LIMIT_EXCEEDED; continuation: WRONG_CONTINUATION_KEY; transient: status 0,
  408, 5xx, ASPSP_ERROR, ASPSP_TIMEOUT, INVALID_RESPONSE; everything else permanent).
- Messages are sanitized: never include the JWT, Authorization header, session id, account uid,
  continuation key, IBAN or response bodies; keep the provider error code and a short message.
- `ebPsuHeadersFromRequest(): array` builds Psu-Ip-Address / Psu-User-Agent / Psu-Referer /
  Psu-Accept / Psu-Accept-Charset / Psu-Accept-Encoding / Psu-Accept-language from `$_SERVER` for
  user-present calls. Scheduled calls pass `[]`. If the bank's `required_psu_headers` are not all
  available, send none.
- Tests use a scripted `EbFixtureTransport` (in tests/), never the network. The private key for the JWT
  test is generated in the test with `openssl_pkey_new()`.

## 5. Normalization and deduplication - `ebNormalize.php`, `ebStore.php`

`ebNormalizeTransaction(array $raw): array` (pure) returns:
`{status, entry_reference|null, booking_date|null, value_date|null, transaction_date|null,
amount (signed decimal string with up to 3 decimals, '-' for DBIT), currency, description,
counterparty|null, bank_reference|null, valid_amount: bool}`.
- Amount: `transaction_amount.amount` must match `^\d+(\.\d{1,3})?$` (after trimming a leading '+');
  never use floats for arithmetic; sign from `credit_debit_indicator` (DBIT negative; a negative
  provider amount string is accepted and its sign trusted only if indicator missing). Invalid ->
  valid_amount=false (stored with review_reason bad_amount and amount 0).
- Counterparty: for CRDT the debtor name, for DBIT the creditor name.
- Description: counterparty + ' ' + remittance_information joined by ' ' (fallback: note,
  bank_transaction_code.description), whitespace collapsed, trimmed, cut to 255 chars on a UTF-8
  boundary (mb_substr). Do not invent text when everything is empty: use '' and the UI shows the date.
- `ebFingerprint(array $n): string` = sha256 of
  `booking_date|amount|currency|lowercased collapsed description|lowercased counterparty|value_date`.
- `ebPayloadHash(array $n)` = sha256 of `booking_date|value_date|amount|currency|description`.

Dedup key (per local account):
1. `entry_reference` present and non-empty: `dedup_key = 'r:' . sha256(entry_reference)`.
   - New key -> insert. Existing key: update last_seen_at. If payload_hash differs: when
     review_state is imported -> set review_state 'review', review_reason 'changed_after_import',
     store the new values in changed_payload (JSON), never touch the journal line; when pending or
     review -> update the stored fields (and recompute derived review_reason); when ignored or
     pre_cutover -> update fields, keep state.
2. No entry_reference: occurrence counting over the WHOLE fetch window (not per page). Buffer these
   rows until every page of the window has been fetched. Group by fingerprint; let n = occurrences in
   this window, m = stored rows of this account whose dedup_key starts with `'f:' . fingerprint . ':'`.
   Insert keys `'f:' . fingerprint . ':' . k` for k = m+1..n. Rows created when m = 0 are normal
   (two genuinely identical payments in one window both import, both get identical_group=1). Rows
   created when m > 0 (a new identical occurrence appeared for a date already fetched) get
   review_state 'review', review_reason 'possible_duplicate'. If n < m nothing is deleted.
   Because windows are whole days and the fingerprint contains booking_date, every date in a window
   is complete, so counts are comparable across overlapping windows.
3. Rows with status other than BOOK are not stored (counted in the run as skipped). Pending (PDNG)
   transactions therefore never reach a journal; the later booked version is a new row.

Initial review_state for a new row, in order: bad amount -> review/bad_amount; currency != 'DKK' ->
review/unsupported_currency; booking_date null -> review/missing_date; account.import_from set and
booking_date < import_from -> pre_cutover; otherwise pending. (outside_fiscal_year, closed_period,
no_ledger_account and base_currency are decided when a user picks the line, see "Pull model".)

`ebPersistTransactions(int $accountId, array $normalizedWithRef, array $normalizedWithoutRef, int $runId): array{new:int, changed:int, review:int, skipped:int}`
runs in one DB transaction (`transaktion('begin'|'commit'|'rollback')`), relies on the unique
(account_id, dedup_key) index as the last line of defense (a concurrent duplicate insert must roll
back that statement's transaction and be retried once by re-reading, never create a second row).
Escape every string with `db_escape_string`, cast every number.

## 7. Sync orchestration - `ebSync.php`

`ebSyncAccount(int $accountId, string $trigger, array $psuHeaders = [], ?EnableBankingClient $client = null): array{status:string, run_id:int, message:string}`
1. Lease: in a transaction `SELECT ... FROM bank_accounts WHERE id=? FOR UPDATE`; if lease_until >
   now and lease_owner differs -> return status 'skipped' (no run row). Else set lease_owner (random
   hex) and lease_until = now + 15 min; commit. Always release in finally.
2. Preconditions: company enabled, account state active, ledger_account and
   import_from set, connection status active, valid_until > now (else mark connection expired, set
   account last_error_code 'consent_expired', next_fetch_at NULL, status 'consent_expired').
   Current account_uid = active bank_account_sessions row for the connection.
3. Window: date_to = today; date_from = import_from when fetched_through is null, else
   max(import_from, fetched_through - 10 days). Never request a date_from in the future.
4. Pages: call client->transactions(uid, from, to, key) until continuation_key is null/empty, also
   when a page has zero transactions. Hard cap 1000 pages -> run 'partial' with error 'page_cap'.
   After each page persist the rows WITH entry_reference immediately (ebPersistTransactions with an
   empty no-ref list); buffer the no-ref rows; after the last page persist the buffered no-ref rows.
   WRONG_CONTINUATION_KEY: restart the window once from the first page; second time -> failed.
5. Only after all pages are persisted: fetched_through = date_to, last_fetch_at = now, fail_count 0,
   last_error NULL. If a page failed: fetched_through unchanged (rows already persisted stay;
   reprocessing is idempotent).
6. `ebReconcileLinks` marks links whose journal line was deleted or posted. Nothing is put into a
   journal: a fetch only stores lines.
7. Scheduling (4 background cycles per day max): success -> next_fetch_at = now + 6 h.
   rate_limit -> next_fetch_at = now + 6 h, status 'rate_limited'. transient -> fail_count + 1,
   next_fetch_at = now + min(6 h, 30 min * 2^(fail_count-1)). consent / action_required ->
   connection status expired/revoked (consent) or keep (action_required), next_fetch_at NULL until
   reconnect, account last_error set. period (WRONG_TRANSACTIONS_PERIOD, WRONG_DATE_INTERVAL) ->
   failed, next_fetch_at NULL until the start date is changed. permanent -> status failed, next_fetch_at = now + 6 h.
   Manual trigger: allowed at most once per 5 minutes per account (last_manual_at), sends PSU
   headers, does not change next_fetch_at unless it fails with consent errors.
8. Every run writes a bank_sync_runs row with counts and sanitized error.

`ebDueAccounts(): int[]` ids where state = active AND next_fetch_at <= now (or null
and never fetched) AND connection active. `ebScheduleAfterMapping(int $accountId)` sets
next_fetch_at = now.

## 8. Connections, consent, callback - `ebConnections.php`

Authorization state is stored in the TENANT DB table `bank_auth_states`:
id serial, state_hash varchar(64) unique, user_id int, username varchar(100), environment
varchar(12), purpose varchar(12) ('connect'|'reconnect'), connection_id int null, aspsp_name
varchar(100), aspsp_country varchar(2), psu_type varchar(10), created_at timestamp, expires_at
timestamp (created + 30 min), used_at timestamp null. (Add it to ebEnsureSchema.)

- `ebStartAuthorization(EnableBankingClient $c, string $aspspName, string $country, string $psuType, string $purpose, ?int $connectionId, int $userId, string $username): string`
  (returns provider URL). state = bin2hex(random_bytes(32)); only its sha256 is stored. valid_until =
  now + min(aspsp.maximum_consent_validity, 180 days) - 1 hour, RFC3339 with offset.
- `ebConsumeAuthorization(string $state, int $userId): array` returns the state row or throws
  `EbUserError` with a Danish message for: unknown state (also when the user switched company: the
  lookup only searches the current session's tenant DB), already used (replay), expired, different
  user. Marks used_at inside a transaction with `SELECT ... FOR UPDATE`, so a replay racing the first
  callback cannot both succeed.
- `ebCompleteAuthorization(EnableBankingClient $c, array $stateRow, string $code, string $username): int`
  POST /sessions; store bank_connections (encrypted session id, valid_until, aspsp, psu_type, status
  active). For each returned account: if `uid` empty -> skip (blocked/closed). Match an existing local
  account when ANY of its identification_hashes (plus identification_hash) is in
  bank_account_aliases. Matched: update connection_id, display fields, currency; insert all new
  aliases; deactivate previous bank_account_sessions rows; insert the new session row; state
  disconnected/unsupported -> active when ledger_account and import_from are set (keep mapping and
  checkpoint) and set next_fetch_at = now; a reconnect therefore resolves to the same local account
  before any import. Not matched: create bank_accounts with state 'new' ('unsupported' when currency
  != 'DKK'). For purpose 'reconnect' the previous connection gets status 'replaced' and the old
  provider session is deleted best-effort (errors ignored, logged sanitized). Audit rows. Returns the
  new connection id.
- `ebDisconnect(int $connectionId, string $username, ?EnableBankingClient $c)`: DELETE
  /sessions/{id} best-effort, connection status disconnected, its accounts state disconnected,
  next_fetch_at NULL, lease cleared. Accounting records, transactions and links stay.
- `ebMapAccount(int $accountId, int $ledgerAccount, string $importFrom, string $username)`: validate
  ledger account exists in kontoplan (current fiscal year, kontotype 'D' or 'S'), import_from valid
  Y-m-d not in the future; account currency DKK; state new -> active, next_fetch_at = now.
- `ebSetPaused(int $accountId, bool $paused, string $username)` (Indstillinger right): paused accounts
  are not fetched; resuming sets `next_fetch_at` = now unless access or a period error stops fetching.
  `ebWaitingCountForConnection(int $connectionId)` counts unlinked pending and review rows, for the
  disconnect confirm. Disconnect is always local; when the bank did not confirm the revoke,
  `bank_connections.last_error` says so and the feed and accounts pages show it.
- `ebOverlapHints(int $accountId, array $txIds): array<int, string>` for the given (visible, max 200)
  pending/review rows: existing kassekladde lines (any journal) or transaktioner on ledger_account with
  the same date and absolute amount on the matching side -> hint text. Shown in the pick list; never
  preselects a row.
- `ebAccountStatus(int $accountId): array` for the UI: state, consent valid_until and days left,
  last_fetch_at, last_import_at, counts per review_state, queued (pending) count, last run status,
  last_error, next_fetch_at, waiting (unlinked pending rows), waiting_oldest and stale_hours.

## 9. Worker - `bank_integration/worker.php` (CLI only)

```
php bank_integration/worker.php                 # all tenants, due accounts
php bank_integration/worker.php --tenant=test_34 [--account=5] [--force] [--dry-run]
```
- Refuses non-CLI (`PHP_SAPI !== 'cli'` -> exit 1). chdir(__DIR__), sets REQUEST_URI
  '/saldi/bank_integration/worker.php' and REMOTE_ADDR 'cli', `$webservice = true`.
- Global run lock: `flock` on `<installation>/temp/.ht_bank_worker.lock` (LOCK_EX|LOCK_NB); a second
  overlapping cron run exits 0 with a message.
- Parent mode enumerates master `regnskab` rows with non-empty db and lukket != 'on', and runs one
  child process per tenant (`proc_open([PHP_BINARY, __FILE__, '--tenant=<db>'])`, sequential, 10 min
  timeout per tenant) so a legacy exit or fatal in one tenant cannot affect others.
- Child mode: connect to the tenant, set `$db`, run
  `ebEnsureSchema()` only if tables are missing, skip when !ebCompanyEnabled() or !ebFeatureAvailable(),
  then `ebSyncAccount($id, 'schedule')` for each due account (or the forced one). Prints one summary
  line per account: tenant, account id, status, fetched/new/review counts. Exit code 0 when
  every account ended ok/skipped/rate_limited, 2 when any failed (cron mail signal).
- The parent writes `temp/.ht_bank_worker.heartbeat` after locking and after every tenant
  (`ebWorkerHeartbeatAge()`); a disabled company is skipped before any schema access.
- Must run as the web server user (www-data) so temp/ files keep the right owner.

## 10. Pages

Controller first (POST handling, CSRF check, permission check, DB writes, 303 redirect), view after;
shared header `bank_integration/includes/page_header.php`. CSRF: `ebCsrfField()` / `ebCsrfCheck()`
(token bound to the tenant).

- `connect.php`: choose a Danish bank and business/personal, or renew access (purpose reconnect).
- `callback.php`: the registered redirect URL; consumes the single-use state and completes the
  authorization, then shows the accounts.
- `accounts.php`: map each DKK account to a ledger account and a start date; disconnect a connection.
- `feed.php`: status panel, Hent nu, Forny adgang, pause, disconnect, tabs and the pick list (see
  "Pull model").
- `systemdata/diverseIncludes/bank_integration.php` (Indstillinger » Bogføring & bank »
  Bankforbindelse, `diverse.php?sektion=bank_integration`): company toggle, server configuration
  status, connections and accounts. Offered when the server is configured (`bankIntegrationAvailable()`).
- `finans/bankimport.php`: when the chosen ledger account is fed by the bank feed, a notice with the
  covered period warns that overlapping CSV lines may duplicate fetched lines.

## 11. Tests

`tests/characterization/bank_integration/EnableBankingBackendTest.test.php` (characterization suite in
`phpunit.xml`) with a fixture transport and synthetic fixtures under `tests/fixtures/enablebanking/`;
no test contacts Enable Banking. `tests/test_account_reset.php` and
`tests/integration/journalSaveReplay.php` cover the reset and the Bankforbindelse save.
