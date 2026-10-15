# Saldi MCP + Saldi Assist rebuild: build spec (v2)

Branch `ai/mcp-assistant` in Saldi (off origin/master `dae5a65d` plus the two ai/plan infra commits) and
branch `ai/mcp-assistant` in chaty_V2 (off a snapshot of the deployed working tree). Written 2026-10-01.
v2 folds in an independent Opus + GPT-6.1 Sol critique of v1 (90 findings) and the AnamoraV2 custody,
honesty and approval patterns.

Sources this spec implements:
- `doc/strategy/research/saldi-assistant-rethink-2026-09-24.md` (four modes, custody, build order)
- `doc/strategy/research/ai-foundations-coherence-2026-09-24.md` (F1, F3, F6, F7, F8, F9, F12, coherence rules)
- `doc/ai-plan/CONVENTIONS.md` (schema in planUpdates, gate, Postgres-tested / MySQL-written DDL)
- `doc/ai/*` team conventions (file history lines, SQL escaping, curly braces, no secrets, findtekst)
- AnamoraV2 (`~/Projects/AnamoraV2`): approval digest + revision, receipts returned to the model,
  seen-set citations, "right now" unavailable block, pinned contract revision, turn taint, honest empty runs.

This file is the contract between the Saldi side (PHP) and the assistant runtime (chaty_V2, Python).
Anything both sides must agree on is defined here once. When code and spec disagree, fix the code or
amend this spec in the same commit.

---

## 0. Scope, non-goals, release gate

In scope:
1. **Saldi runtime spine** for new code: `SaldiTenantContext`, a PDO `SaldiDb` wrapper with savepoint
   nesting, a data-only configuration loader, a read-only translator, schema steps runnable from login
   or from trusted runtime entrypoints.
2. **Permissions and agent principals**: action-level `SaldiPermissionService` over `brugere.rettigheder`,
   delegated grants (`agent_grants`) with opaque bearer tokens.
3. **Automation inbox**: `inbox_items`, kind registry, append-only `decision_log`.
4. **Assistant memory** in the tenant DB: bounded, approval-gated, private per user, retractable.
5. **Tool registry + MCP endpoint**: read tools (auto tier) and draft tools (gated tier) that only ever
   create inbox items. Contract exported to `doc/contracts/tool-registry.v1.json`.
6. **Watch**: `automation_jobs`, a CLI worker, three starter routines that create inbox findings.
7. **Saldi UI**: shell-level "Opgaver" inbox page with the shared ApprovalCard, a Saldi-owned approval
   modal for chat approvals, the "Saldi Assist" settings page (memory, AI access, switches), shell wiring.
8. **chaty_V2 assistant runtime**: one streaming agent loop (Help / Ask / Prepare), model gateway with a
   vendor allowlist, Saldi MCP client, layered pinned contract, evidence-gated honesty, approval cards in
   the widget, Danish eval bank, phase-0 hygiene.

Non-goals (explicitly out):
- No tool, kind or code path that pays, files VAT, locks a period, deletes posted or master records,
  changes bank details, changes rights, posts a journal, or sends an invoice/e-mail. This is the
  **forbidden tier**: there is no tool for it at all. Posting stays a human action in the kassekladde.
  The one deletion that exists is `undo` of a `journal.draft_entry`: a human may remove exactly the
  unposted, unchanged rows (and the empty kladde) that this item inserted (4.4).
- No agent can approve, reject, undo or cancel an inbox item. Every decision needs a logged-in Saldi web
  session and a click on a Saldi-rendered control (7.1, 7.2).
- No direct DB access from chaty_V2. Every Saldi read or draft goes through the MCP endpoint.
- No model call from a Saldi page request or from the worker (the starter routines are SQL rules).
- The decision-layer prototype (`feat/decision-layer`) is not merged. Its kinds can later register here.
- No MySQL test run. DDL is written for both (`db_type` branching); the gate runs Postgres only.

**Release gate (customer data).** OpenRouter has no region control. Until an EU processing route is
verified, this build may send customer ERP data to a model only in development: chaty setting
`ASSISTANT_CUSTOMER_DATA_ALLOWED` defaults to `false` and is `true` only in the dev compose. When false,
chaty drops the MCP token and answers in Help mode. Saldi's `assistant/tools_enabled` defaults `off`.
Both must be switched on deliberately for any real tenant.

---

## 1. Conventions for all new Saldi code

- PHP 8.3 (the dev image). New files: `<?php` + the standard Saldi licence header block (copy it from
  `includes/planUpdates.php`), a `// --- <path> --- ai/mcp-assistant --- 2026.10.01` line, a purpose
  paragraph, and a history line `// 20261001 CDX/LH Created.` (Codex) or `// 20261001 CL/LH Created.`
  (Claude). Edited legacy files get `// 20261001 CDX|CL/LH <what>` in their header history block.
- Tabs for indentation in app files (repo style). New **test** files follow PSR12 (`phpcs.xml.dist`).
- Classes in the global namespace with a `Saldi` prefix. `declare(strict_types=1);` in new class files.
- New code never calls `db_select`/`db_modify`/`findtekst` (they need session/global state, rewrite
  `' like '` inside literals, reject `;`, log every SQL to `temp/`, `alert()` and `exit` on error).
  New code uses `SaldiDb` (PDO) and `SaldiText`. Exceptions: legacy-style page views
  (`inbox/index.php`, `systemdata/assistent.php`, `index/main.php`) may use `findtekst` for their own
  labels because they run inside a normal session; the login-path schema wrapper uses `db_modify` (2.5).
- Every externally sourced value is a bound PDO parameter. Identifiers come from code constants only.
- Stored codes are English (`status='open'`). Displayed text comes from `SaldiText` / `findtekst`.
  Text ids (verified unused on every branch 2026-10-01), allocated per builder, each appending only its
  own rows in id order to `importfiler/tekster.csv` (TAB separated `id<TAB>Dansk<TAB>English<TAB>Norsk`, LF):

  | ids | owner |
  |---|---|
  | 5700-5709 | kinds (5700 = default kladdenote "Forslag fra Saldi Assist") |
  | 5710-5719 | provenance, action-class and kind labels |
  | 5720-5729 | routine titles (5720-5722 used) |
  | 5730-5749 | error messages (one per error code) and ApprovalCard column/field labels |
  | 5800-5829 | inbox page, ApprovalCard view, approval modal |
  | 5830-5869 | settings page |
  | 5870-5879 | shell (sidebar entry, badge, user menu link) |
- All timestamps in new tables are **UTC**. Postgres `timestamp` (no tz), MySQL `datetime`. Written by
  PHP `gmdate('Y-m-d H:i:s')`. APIs emit ISO 8601 with `Z`. Pages display Europe/Copenhagen.
- **Money.** Stored amounts have 3 decimals (`numeric(15,3)`). Read them as strings, do arithmetic in SQL
  `numeric` or in integer milli-units (`bcmath` is not assumed), and round half away from zero to 2
  decimals only at the API/display boundary. API money is a decimal string with 2 decimals and `.`
  (`"1250.00"`) plus a `currency` field. `currency` is the tenant base currency (`settings` var_name
  `baseCurrency`, default `DKK`) unless the row has its own currency. Totals across rows are computed in
  base currency using the stored rate (`amount * valutakurs / 100` when `valuta` differs from base) and
  say so (`converted: true`).
- Dates cross the API as `YYYY-MM-DD`.
- ApprovalCard quantities use Danish notation without trailing zeros (`2`, `2,5`, `1.234,25`);
  stored quantities and tool API decimal strings keep their existing precision and notation.
- Never touch `includes/opdat_*.php`, `includes/betweenUpdates2.php`, `includes/version.php`. The only
  `betweenUpdates.php` edit is the existing `planUpdates.php` include line.
- No credential literals in tracked files (code, tests, docs). Tests read env and skip only when absent.

---

## 2. Runtime spine (`includes/runtime/`)

### 2.1 `SaldiDb` (`includes/runtime/SaldiDb.php`)

```php
final class SaldiDb {
	public static function connect(array $cfg, string $database, int $statementTimeoutMs = 5000): self;
		// $cfg from saldi_runtime_config(): db_type, host, port, user, password
	public function one(string $sql, array $params = []): ?array;
	public function all(string $sql, array $params = []): array;
	public function value(string $sql, array $params = []): mixed;
	public function exec(string $sql, array $params = []): int;          // affected rows
	public function insert(string $table, array $row): int;             // returns $row['id'] when supplied,
	                                                                     // else the generated id (PG RETURNING id, MySQL lastInsertId)
	public function insertIgnore(string $table, array $row, array $conflictColumns): ?int;
		// PG: INSERT ... ON CONFLICT (cols) DO NOTHING RETURNING id; MySQL: INSERT IGNORE + LAST_INSERT_ID check.
		// Returns null when the row already existed. Safe inside a transaction (no aborted tx).
	public function transaction(callable $fn): mixed;   // BEGIN at depth 0; SAVEPOINT sp_<depth> deeper;
	                                                    // rollback (to savepoint) + rethrow on Throwable
	public function inTransaction(): bool;
	public function lock(string $name, int $timeoutSeconds = 10): void; // tx-scoped advisory lock:
		// PG pg_advisory_xact_lock(hashtext(?)) after SET LOCAL lock_timeout; MySQL GET_LOCK(?, timeout)
		// released at commit/rollback (MySQL: RELEASE_LOCK in transaction()'s finally). Must be inside transaction().
	public function isPostgres(): bool;
	public function dbType(): string;   // 'postgresql' | 'mysql'
	public function name(): string;
	public function hasTable(string $table): bool;              // cached per handle
	public function hasColumn(string $table, string $column): bool;
	public function close(): void;
}
```
- PDO options: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`, connect timeout 3 s.
  Postgres: `SET statement_timeout`, `SET client_encoding='UTF8'`, `SET TIME ZONE 'UTC'`.
  MySQL: `SET NAMES utf8mb4`, `SET time_zone='+00:00'`, `SET SESSION MAX_EXECUTION_TIME`.
- Database names must match `^[A-Za-z0-9_-]{1,63}$`, else `InvalidArgumentException`.
- `information_schema` lookups are scoped with `table_schema = current_schema()` (PG) / `DATABASE()` (MySQL).

### 2.2 `SaldiRuntimeError` (`includes/runtime/SaldiRuntimeError.php`)

```php
final class SaldiRuntimeError extends RuntimeException {
	public function __construct(public readonly string $errorCode, string $messageEn,
		public readonly array $detail = [], ?Throwable $previous = null);
	public function errorClass(): string;   // from the table below
	public function httpStatus(): int;      // from the table below
	public function messageDa(SaldiDb $tenant = null, int $languageId = 1): string; // SaldiText 5730+
}
```

| code | class | http | text id | meaning |
|---|---|---|---|---|
| `UNAUTHENTICATED` | PERMANENT | 401 | 5730 | token missing, malformed, unknown, expired, revoked, session ended |
| `PERMISSION_DENIED` | NEEDS_HUMAN | 403 | 5731 | the user or the grant scope lacks the permission |
| `VALIDATION_ERROR` | PERMANENT | 400 | 5732 | input fails schema/business validation; `detail.fields` |
| `NOT_FOUND` | PERMANENT | 404 | 5733 | record does not exist or is not visible to this principal |
| `STALE_CONTEXT` | NEEDS_HUMAN | 409 | 5734 | the subject changed since the proposal |
| `CLOSED_PERIOD` | NEEDS_HUMAN | 409 | 5735 | date in a closed VAT period or outside the active fiscal year |
| `MEMORY_FULL` | NEEDS_HUMAN | 409 | 5736 | memory cap reached |
| `CONFLICT` | NEEDS_HUMAN | 409 | 5737 | item not open / already decided / kladde in use / company switched |
| `RATE_LIMITED` | RETRYABLE | 429 | 5738 | per-grant limit hit |
| `DISABLED` | PERMANENT | 403 | 5739 | switched off (kill switch or tenant setting) |
| `UNAVAILABLE` | RETRYABLE | 503 | 5740 | DB/dependency failure; never leak the PDO message |

### 2.3 `SaldiTenantContext` (`includes/runtime/SaldiTenantContext.php`)

```php
final class SaldiTenantContext {
	public function __construct(
		public readonly string $installId,    // SALDI_INSTALL_ID (2.4), e.g. 'ssl3'
		public readonly int $regnskabId,      // master regnskab.id
		public readonly string $db,           // tenant database (regnskab.db)
		public readonly string $dbType,       // 'postgresql' | 'mysql'
		public readonly int $userId,          // tenant brugere.id; 0 for system/rule
		public readonly string $username,     // raw (unescaped) brugere.brugernavn; '' for system/rule
		public readonly string $rights,       // brugere.rettigheder, reloaded live; '' for system/rule
		public readonly int $fiscalYear,      // grupper art='RA' kodenr
		public readonly int $languageId,      // 1 da, 2 en, 3 no
		public readonly string $principal,    // 3.2
		public readonly ?int $grantId = null,
		public readonly array $scopes = [],   // agents: ['read'] or ['read','draft']
		public readonly string $baseCurrency = 'DKK',
	);
	public static function forSystem(string $installId, int $regnskabId, string $db, string $dbType,
		string $principal, int $fiscalYear, string $baseCurrency): self;  // CLI only; principal 'system:worker' or 'rule:<name>'
	public function isHuman(): bool;   // 'user:'
	public function isAgent(): bool;   // 'agent:'
	public function isRule(): bool;    // 'rule:'
	public function onBehalfOf(): ?int;
	public function tenantHash(): string; // substr(hash('sha256', 'saldi-tenant:' . $db), 0, 32)
	public function userHash(): string;   // substr(hash('sha256', 'saldi-user:' . $db . ':' . $username), 0, 32)
}
```
`tenantHash()`/`userHash()` must equal the claims in `includes/saldi_assist_context_token.php`. That
endpoint currently hashes the SQL-escaped `$brugernavn` from `online.php`; fix it to hash the raw
username re-read from the `online` row (history line), and test with the username `O'Brien`.

Fiscal year: `brugere.regnskabsaar` if it names an active `grupper art='RA'` row (`box5='on'`, `box10`
not `'on'`), else the newest active one. Fiscal-year bounds: box1/box2 = start month/year, box3/box4 =
end month/year.

### 2.4 Configuration and bootstrap (`includes/runtime/bootstrap.php`)

```php
function saldi_runtime_config(): array;   // ['db_type','host','port','user','password','master_db','install_id','version']
function saldi_runtime_reset(): void;     // clears caches (tests)
function saldi_runtime_master(): SaldiDb; // cached per request (web) / per process (CLI)
function saldi_runtime_tenant(int $regnskabId): array{db: SaldiDb, regnskab: array};
	// master: SELECT id, regnskab, db, lukket, lukkes FROM regnskab WHERE id = ?; id 1 rejected;
	// lukket='on' or lukkes <= today -> UNAUTHENTICATED. Never cached in CLI (caller closes it).
function saldi_runtime_tenant_by_db(string $db): array{db: SaldiDb, regnskab: array};
function saldi_runtime_ensure_schema(SaldiDb $tenant): void;
	// trusted entrypoints only (session pages, token endpoint, settings page, tests) - never MCP, never worker
function saldi_runtime_context_for_user(SaldiDb $tenant, array $regnskab, int $userId, string $principal,
	?int $grantId = null, array $scopes = []): SaldiTenantContext;   // reloads the brugere row; userId <= 0 -> PERMISSION_DENIED
function saldi_runtime_context_for_session(): array{ctx: SaldiTenantContext, db: SaldiDb};
	// for pages already inside online.php: uses $db_id, $bruger_id, $sprog_id; bruger_id <= 0 -> PERMISSION_DENIED
```
Configuration is **data-only** (never execute `connect.php`, never touch the legacy connection):
1. If env `SALDI_RUNTIME_DB_TYPE`, `SALDI_RUNTIME_DB_HOST`, `SALDI_RUNTIME_DB_USER`,
   `SALDI_RUNTIME_DB_PASSWORD`, `SALDI_RUNTIME_DB_MASTER` are all set, use them (`SALDI_RUNTIME_DB_PORT`
   optional).
2. Otherwise parse `includes/connect.php` as text: regex
   `/\$(db_type|sqhost|squser|sqpass|sqdb)\s*=\s*(["'])(.*?)\2\s*;/` (first match per name). A host of
   the form `host:port` is split.
3. Anything missing -> `SaldiRuntimeError UNAVAILABLE`.
`install_id` = env `SALDI_INSTALL_ID` (must match `^[a-z0-9_-]{1,32}$`, default `local`).
`version` = parsed from `includes/version.php` (`$version = "..."`) by regex.

### 2.5 Schema steps (`includes/planUpdatesSteps.php`, rewired `includes/planUpdates.php`)

- `includes/planUpdatesSteps.php` defines, inside a `function_exists` guard,
  `function saldi_plan_update_steps(): array` returning
  `array<string, callable(string $dbType, callable $exec, callable $selectOne): void>`.
- Each step: existence check -> if the table exists, check its **signature column** and throw
  `RuntimeException('incompatible existing table <name>')` when absent (so a decision-layer
  `inbox_items` on `plan_real` is reported, not silently accepted); else create table + indexes.
  Signature columns: `agent_grants.token_hash`, `inbox_items.open_key`, `decision_log.idempotency_key`,
  `tool_calls.args_hash`, `assistant_memory.retracted_at`, `automation_jobs.lease_token`.
- Step SQL has no `;`, no ` like `, no plpgsql.
- **Login path** (`includes/planUpdates.php`): takes `pg_try_advisory_lock(hashtext('saldi_plan_updates'))`
  (MySQL `GET_LOCK('saldi_plan_updates', 0)`) through `db_select`; if not acquired, skip (another process
  migrates; login continues). `$exec` runs `db_modify` with `$webservice = true` temporarily and throws
  `RuntimeException` when the result does not start with `"0"`. Unlock afterwards. The runner
  (`plan_updates_run`) is unchanged; failures are logged and login carries on.
- **Runtime path** (`saldi_runtime_ensure_schema`): one `SaldiDb::transaction` holding
  `lock('saldi_plan_updates', 10)` around `plan_updates_run` with PDO `$exec`/`$selectOne`; if the
  runner returns `failed` (or the watermark write throws) -> `SaldiRuntimeError UNAVAILABLE` after
  `error_log`. An up-to-date tenant costs one select (watermark equals the last step: return early
  without the lock).
- Step names, in order: `s90_agent_grants`, `s90_inbox_items`, `s90_decision_log`, `s90_tool_calls`,
  `s90_assistant_memory`, `s90_automation_jobs`.

Postgres DDL (MySQL: `serial PRIMARY KEY` -> `int NOT NULL AUTO_INCREMENT PRIMARY KEY`, `timestamp` ->
`datetime`, `numeric` -> `decimal`, no defaults on `text` columns; the code always supplies them):

```sql
CREATE TABLE agent_grants (
  id serial PRIMARY KEY,
  principal varchar(96) NOT NULL,        -- 'agent:saldi-assist' | 'agent:external:<id>'
  label varchar(100) NOT NULL,
  user_id integer NOT NULL,
  user_hash char(32) NOT NULL,           -- SaldiTenantContext::userHash() at issuance
  kind varchar(16) NOT NULL,             -- 'session' | 'personal'
  scopes varchar(64) NOT NULL,           -- 'read' | 'read draft'
  token_hash char(64) NOT NULL,          -- sha256 hex of the secret part
  session_ref varchar(32),               -- md5(session_id), kind='session'
  created_at timestamp NOT NULL,
  expires_at timestamp NOT NULL,
  revoked_at timestamp,
  revoked_by integer,
  last_used_at timestamp,
  use_count integer NOT NULL DEFAULT 0,
  max_uses integer
);
CREATE UNIQUE INDEX agent_grants_token_hash_idx ON agent_grants (token_hash);
CREATE INDEX agent_grants_user_idx ON agent_grants (user_id, kind);

CREATE TABLE inbox_items (
  id serial PRIMARY KEY,
  origin varchar(16) NOT NULL,           -- 'agent' | 'rule' | 'ai' | 'system' | 'user'
  kind varchar(48) NOT NULL,
  revision integer NOT NULL DEFAULT 1,
  content_digest char(64) NOT NULL,      -- sha256(canonical JSON {kind, subject_type, subject_id, payload})
  subject_type varchar(32),
  subject_id varchar(64),
  title varchar(255) NOT NULL,           -- data from the proposer, not a UI label
  payload text NOT NULL,                 -- JSON, normalised by validate()
  evidence text,                         -- JSON {explanation, citations[], tainted}
  proposed_rev varchar(64),
  status varchar(16) NOT NULL DEFAULT 'open', -- open|applied|rejected|expired|cancelled|undone
  attention varchar(16) NOT NULL DEFAULT 'ambient', -- silent|receipt|ambient|interrupt
  visibility varchar(16) NOT NULL DEFAULT 'owner',  -- 'owner' | 'permission'
  assignee_permission varchar(48),       -- required when visibility='permission'
  owner_user_id integer,
  created_by varchar(96) NOT NULL,       -- principal
  grant_id integer,
  request_key varchar(160),              -- idempotent create: '<grant or user>:<request_id>'
  dedupe_key varchar(128),
  open_key varchar(180),                 -- kind || ':' || dedupe_key while open, else NULL
  due_at timestamp,
  expires_at timestamp,
  decided_by integer,
  decided_at timestamp,
  decision_note varchar(255),
  result text,                           -- JSON receipt from apply()
  created_at timestamp NOT NULL,
  updated_at timestamp NOT NULL
);
CREATE UNIQUE INDEX inbox_items_open_key_idx ON inbox_items (open_key);
CREATE UNIQUE INDEX inbox_items_request_key_idx ON inbox_items (request_key);
CREATE INDEX inbox_items_status_idx ON inbox_items (status, kind);
CREATE INDEX inbox_items_owner_idx ON inbox_items (owner_user_id, status);

CREATE TABLE decision_log (               -- append-only: no UPDATE/DELETE anywhere in code
  id serial PRIMARY KEY,
  created_at timestamp NOT NULL,
  event varchar(24) NOT NULL,            -- proposed|revised|applied|rejected|expired|cancelled|apply_failed|undone|
                                         -- memory_retracted|grant_issued|grant_revoked
  inbox_item_id integer,
  kind varchar(48),
  subject_type varchar(32),
  subject_id varchar(64),
  actor varchar(96) NOT NULL,
  on_behalf_of integer,
  approved_by integer,
  grant_id integer,
  idempotency_key varchar(128),
  engine varchar(64),
  model varchar(96),
  cost_usd numeric(12,6),
  detail text,                           -- JSON, never tokens or secrets
  undo_ref varchar(128)
);
CREATE UNIQUE INDEX decision_log_idem_idx ON decision_log (idempotency_key);
CREATE INDEX decision_log_item_idx ON decision_log (inbox_item_id);

CREATE TABLE tool_calls (
  id serial PRIMARY KEY,
  created_at timestamp NOT NULL,
  grant_id integer,
  principal varchar(96) NOT NULL,
  user_id integer,
  tool varchar(64) NOT NULL,
  args_hash char(64) NOT NULL,
  status varchar(16) NOT NULL,           -- reserved|ok|error|denied|rate_limited
  error_code varchar(32),
  duration_ms integer,
  result_count integer
);
CREATE INDEX tool_calls_grant_idx ON tool_calls (grant_id, created_at);

CREATE TABLE assistant_memory (
  id serial PRIMARY KEY,
  scope varchar(16) NOT NULL,            -- 'company' | 'user'
  user_id integer,
  fact varchar(300) NOT NULL,
  source_principal varchar(96) NOT NULL,
  source_note varchar(255),
  inbox_item_id integer,
  approved_by integer,
  created_at timestamp NOT NULL,
  retracted_at timestamp,
  retracted_by integer,
  retract_reason varchar(255)
);
CREATE INDEX assistant_memory_scope_idx ON assistant_memory (scope, user_id);

CREATE TABLE automation_jobs (
  id serial PRIMARY KEY,
  job_type varchar(64) NOT NULL,
  subject_type varchar(32),
  subject_id varchar(64),
  payload text NOT NULL,
  status varchar(16) NOT NULL DEFAULT 'pending', -- pending|running|done|failed|skipped
  run_after timestamp NOT NULL,
  dedupe_key varchar(128),
  attempts integer NOT NULL DEFAULT 0,
  max_attempts integer NOT NULL DEFAULT 3,
  lease_token varchar(64),
  locked_until timestamp,
  last_error text,
  result text,
  created_at timestamp NOT NULL,
  finished_at timestamp
);
CREATE UNIQUE INDEX automation_jobs_dedupe_idx ON automation_jobs (dedupe_key);
CREATE INDEX automation_jobs_due_idx ON automation_jobs (status, run_after);
```

### 2.6 `SaldiText` (`includes/runtime/SaldiText.php`)

`SaldiText::get(?SaldiDb $tenant, int $textId, int $languageId, string $fallback): string`. Lookup:
(1) tenant `tekster` when `hasTable('tekster')`: `SELECT tekst FROM tekster WHERE tekst_id = ? AND
sprog_id = ?`; (2) the language column of `importfiler/tekster.csv` resolved through `__DIR__`
(parsed once per process, cached); (3) `$fallback`. Read-only, no inserts, no session. Language outside
1..3 -> 1.

### 2.7 `SaldiScreens` (`includes/runtime/SaldiScreens.php`)

Navigation links are **generated server-side from typed references** and validated against an allowlist
on every side (PHP, chaty, the host navigate bridge). A link never carries an action parameter.

| type | screen | param |
|---|---|---|
| `journal` | `finans/kassekladde.php` | `kladde_id` |
| `journal_list` | `finans/kladdeliste.php` | none |
| `sales_order` | `debitor/ordre.php` | `id` |
| `purchase_order` | `kreditor/ordre.php` | `id` |
| `debtor` | `debitor/debitorkort.php` | `id` |
| `creditor` | `kreditor/kreditorkort.php` | `id` |
| `product` | `lager/varekort.php` | `id` |
| `inbox_item` | `inbox/index.php` | `item` |

`SaldiScreens::link(string $type, ?int $id): string` returns e.g. `finans/kassekladde.php?kladde_id=12`
(no leading slash). `SaldiScreens::isAllowed(string $screen): bool` accepts exactly the rows above
(param value `^[0-9]{1,10}$`). Before relying on a param name, verify it in the page script; if a page
uses a different name, fix the table here in the same commit. `javascript/saldi-assist-navigate.js`
(host bridge) is tightened to: a bare path matching `^[a-z0-9_\/.-]+\.php$` (manifest screens, unchanged
behaviour), or exactly one of the allowlisted page + param pairs above. Everything else is refused.

---

## 3. Permissions, principals, grants (`includes/automation/`)

### 3.1 `SaldiPermissionService`

`brugere.rettigheder` is a digit string, one position per module (`systemdata/brugere.php:62-64`).
Only `0`, `1`, `2` are meaningful: **read** = `'1'` or `'2'`; **write** = exactly `'1'`. Any other
character at a position = no right there. **Baseline** = at least one position is `'1'` or `'2'`.

| permission | rule |
|---|---|
| `company.read` | baseline |
| `accounts.read` | read on pos 0, 3 or 4 |
| `ledger.read` | read on pos 3 or 4 |
| `journal.read` | read on pos 2 |
| `journal.draft` | write on pos 2 |
| `sales.read` | read on pos 5 |
| `debtors.read` | read on pos 6 or 12 |
| `purchases.read` | read on pos 7 |
| `creditors.read` | read on pos 8 or 13 |
| `products.read` | read on pos 9 or 15 |
| `inbox.read` | baseline |
| `memory.read` | baseline |
| `memory.propose` | baseline |
| `followup.draft` | baseline |
| `settings.admin` | write on pos 1 |

**Revenue restriction** (not a grant): `revenueHidden(rights)` = tenant setting `hideRevenue`/`finans`
is `'on'` AND pos 1 is not `'1'` (mirrors `hide_revenue()`). When hidden: P&L accounts (kontotype `'D'`)
are omitted from balances, ledger entries and the trial balance (`revenue_hidden: true` in the data),
and sales documents (`DO`/`DK`) are not returned by invoice tools (`PERMISSION_DENIED`).

```php
final class SaldiPermissionService {
	public static function userPermissions(SaldiDb $tenant, string $rights): array;   // sorted list
	public static function effective(SaldiTenantContext $ctx, SaldiDb $tenant): array;
	public static function allows(SaldiTenantContext $ctx, SaldiDb $tenant, string $permission): bool;
	public static function require(SaldiTenantContext $ctx, SaldiDb $tenant, string $permission): void; // PERMISSION_DENIED
	public static function revenueHidden(SaldiTenantContext $ctx, SaldiDb $tenant): bool;  // false for rule/system
}
```
`effective()`:
- `user:<id>`: `userPermissions(rights)`.
- `agent:*`: user permissions intersected with the scope set. Scope `read` = every `*.read`; scope
  `draft` (only with `read`) adds `journal.draft`, `memory.propose`, `followup.draft`. Agents never get
  `settings.admin` and never any decision capability.
- `rule:<name>`: `['company.read', <the routine's permission()>]` from the trusted routine registry.
- `system:worker`: `['company.read']`.
Rule and system contexts are only constructed by the CLI worker (`forSystem`); no request can produce one.

### 3.2 Principals

| principal | who |
|---|---|
| `user:<brugere.id>` | a human in a Saldi web session |
| `agent:saldi-assist` | the in-app assistant for one user (session grant) |
| `agent:external:<grant id>` | an outside agent using a personal grant |
| `system:worker` | the automation worker |
| `rule:<routine name>` | a routine's findings |

Every inbox item records `created_by`, `grant_id` and `owner_user_id`; every decision records the
deciding human (`decided_by`, decision_log `approved_by`). Agents are never deciders.

### 3.3 Grants and tokens (`includes/automation/SaldiGrantService.php`)

Token: `smcp1.<install_id>.<regnskab_id>.<grant_id>.<secret>`; `secret` = 43 chars base64url of 32
`random_bytes`. Only `sha256(secret)` is stored. Never log a token; never return `token_hash`.

```php
final class SaldiGrantService {
	public function __construct(SaldiDb $master, SaldiDb $tenant, SaldiTenantContext $issuer);
	public function issueSession(string $sessionRef): array{token: string, grant: array};
		// issuer must be human; kind 'session', principal 'agent:saldi-assist', scopes 'read draft',
		// ttl 8 h, max_uses 2000. No revocation on issue: keep at most 5 unrevoked unexpired session
		// grants per (user_id, session_ref); revoke only the oldest beyond 5.
	public function issuePersonal(string $label, array $scopes, int $ttlDays): array{token: string, grant: array};
		// issuer human, for self only; requires settings assistant/external_agents = 'on' AND tools_enabled = 'on';
		// scopes exactly ['read'] or ['read','draft']; ttlDays in {30, 90}, or 365 only with settings.admin;
		// at most 5 active personal grants per user; principal 'agent:external:<id>' set after insert.
	public function revoke(int $grantId): void;   // owner, or settings.admin; decision_log 'grant_revoked'
	public function listMine(): array;            // never token_hash
	public function listAll(): array;             // settings.admin only
	public static function parse(string $token): ?array{install: string, regnskab_id: int, grant_id: int, secret: string};
}
function saldi_runtime_context_from_token(string $token): array{ctx: SaldiTenantContext, db: SaldiDb, grant: array};
```
`saldi_runtime_context_from_token` (in `bootstrap.php`), in this order, with **no write before step 7**:
1. `parse` (shape, install id equals config `install_id`), else UNAUTHENTICATED.
2. Env `SALDI_MCP_DISABLED=1` -> DISABLED.
3. `saldi_runtime_tenant(regnskab_id)`.
4. `hasTable('agent_grants')` false -> UNAUTHENTICATED (the MCP path never migrates; a real grant can
   only exist after a trusted entrypoint ran `ensure_schema`).
5. Load the grant by id; `hash_equals(token_hash, sha256(secret))`; not revoked; `expires_at > now`.
6. Session grants: master `SELECT brugernavn FROM online WHERE md5(session_id) = ? AND db = ? AND
   COALESCE(revisor, '') NOT IN ('1','t','true','on') ORDER BY logtime DESC LIMIT 1` must return the
   grant user's brugernavn (exact match) and the recomputed `user_hash` must equal the grant's.
   Tenant settings gate: `assistant/tools_enabled` = `on` (and `external_agents` = `on` for personal
   grants), else DISABLED.
7. Atomic use: `UPDATE agent_grants SET use_count = use_count + 1, last_used_at = ? WHERE id = ? AND
   revoked_at IS NULL AND expires_at > ? AND (max_uses IS NULL OR use_count < max_uses)`; 0 rows ->
   UNAUTHENTICATED.
8. `saldi_runtime_context_for_user(...)` with the grant principal and scopes.
All UNAUTHENTICATED failures return one generic message; the reason goes to `error_log` only.

### 3.4 Switches and settings (`includes/automation/SaldiSettings.php`)

| switch | where | default | effect |
|---|---|---|---|
| `SALDI_MCP_DISABLED=1` | env | unset | MCP returns DISABLED for everyone |
| `SALDI_AUTOMATION_DISABLED=1` | env | unset | worker exits 0 without touching tenants |
| `SALDI_ASSIST_V2=1` | env | unset (dev compose sets 1) | the shell asks the widget for the v2 assistant |
| `assistant` / `tools_enabled` | tenant settings | `off` | session grants can be issued and used |
| `assistant` / `external_agents` | tenant settings | `off` | personal grants can be issued and used |
| `automation` / `routine.<name>` | tenant settings | `off` | the routine runs |

Read: `SELECT var_value FROM settings WHERE var_grp = ? AND var_name = ? AND COALESCE(user_id, 0) = 0
AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1`. Write tenant switches with `user_id` NULL
(select-then-update-or-insert inside a transaction). Only `settings.admin` humans change them.

---

## 4. Inbox, kinds, decision log, memory (`includes/automation/`)

### 4.1 `SaldiDecisionLog`

`append(SaldiDb $db, array $row): int` only. Fills `created_at`. A duplicate `idempotency_key` returns the
existing id without inserting (`insertIgnore` + reselect). No update/delete method exists.

### 4.2 `SaldiInboxKind` and `SaldiKindRegistry`

```php
interface SaldiInboxKind {
	public function name(): string;
	public function actionClass(): string;              // 'read_only' | 'reversible_draft' | 'persistent' | 'money'
	public function proposePermission(): string;
	public function decidePermission(array $item): string; // permission to approve/reject/undo this item
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array;
		// returns ['payload', 'title', 'subject_type', 'subject_id', 'proposed_rev', 'dedupe_key',
		//          'visibility' => 'owner'|'permission', 'assignee_permission' => ?string,
		//          'owner_user_id' => ?int]; throws SaldiRuntimeError. Also called again at approval.
	public function currentRevision(SaldiTenantContext $ctx, SaldiDb $db, array $item): ?string;
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array;   // receipt; caller owns tx
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool;
	public function undo(SaldiTenantContext $ctx, SaldiDb $db, array $item): array;   // receipt; caller owns tx
	public function render(SaldiTenantContext $ctx, SaldiDb $db, array $item): array; // ApprovalCard (4.5)
}
final class SaldiKindRegistry {
	public static function default(): self;   // the five v1 kinds
	public function register(SaldiInboxKind $kind): void;
	public function get(string $name): SaldiInboxKind;   // unknown -> VALIDATION_ERROR
	public function names(): array;
}
```
`money` exists for future kinds; no v1 kind uses it, and `money` can never be auto-applied.

### 4.3 `SaldiInboxService`

```php
final class SaldiInboxService {
	public function __construct(SaldiDb $db, SaldiKindRegistry $kinds);
	public function create(SaldiTenantContext $ctx, string $kind, array $input, array $opts = []): array;
	public function preview(SaldiTenantContext $ctx, int $id): array;   // item + card, for the approval modal
	public function decide(SaldiTenantContext $ctx, int $id, string $decision, string $digest,
		string $tenantHash, ?string $note = null): array;  // decision 'approve'|'reject'
	public function undo(SaldiTenantContext $ctx, int $id, string $tenantHash): array;
	public function cancel(SaldiTenantContext $ctx, int $id): array;
	public function expireDue(): int;
	public function get(SaldiTenantContext $ctx, int $id): array;
	public function list(SaldiTenantContext $ctx, array $filters = []): array;  // status (default open), kind, limit 1..100 (25), offset
	public function countOpen(SaldiTenantContext $ctx): int;
}
```
**Visibility** (one predicate, used by get, list, count, preview, dedupe returns and every mutation):
an item is visible to `ctx` iff (`owner_user_id` = ctx user) OR (`visibility = 'permission'` AND
`assignee_permission` is in `effective(ctx)`). Kind-specific: user-scope memory items are always
`visibility='owner'`. Invisible -> NOT_FOUND.

**create(ctx, kind, input, opts)**; opts: `evidence` (array: explanation, citations, tainted),
`due_at`, `expires_at` (default now + 14 days; routines 7 days), `attention` (default `ambient`),
`request_id` (string <= 64; makes create idempotent), `origin` (default: agent -> `agent`, human ->
`user`, rule -> `rule`).
1. `routine.finding` only from `rule:*` principals; other kinds never from rule principals.
2. `proposePermission` in `effective(ctx)`, else PERMISSION_DENIED.
3. `validate()` -> normalised fields (visibility/assignee/owner come from the kind, never from input).
4. In one transaction: if `request_id` given, `request_key = (grant_id ? 'g'.grant_id : 'u'.user_id) .
   ':' . request_id`; `insertIgnore` on `request_key` and `open_key`; when the insert is ignored,
   reselect the existing row and return it with `deduplicated: true` if visible, else CONFLICT.
   Compute `content_digest`. Append decision_log `proposed` (actor, on_behalf_of, grant_id,
   idempotency `inbox:<id>:proposed`).
5. Return the item (shape below) with `card`.

**decide(ctx, id, decision, digest, tenantHash, note)**:
1. ctx must be human (`isHuman()`), else PERMISSION_DENIED. `hash_equals(ctx->tenantHash(), tenantHash)`
   else CONFLICT ("Du har skiftet regnskab").
2. First, in its own transaction, expire the item if open and past `expires_at` (status `expired`,
   `open_key` NULL, log `expired`); commit; then if it was expired -> CONFLICT.
3. Transaction: `SELECT ... FOR UPDATE`; visible (else NOT_FOUND); status `open` (else CONFLICT);
   `hash_equals(content_digest, digest)` (the card the human saw) else CONFLICT; `decidePermission`
   in effective(ctx) else PERMISSION_DENIED.
   - approve: re-run `validate($ctx, $db, payload)` (re-checks dates, periods, accounts, open status;
     errors propagate with their code); `currentRevision` vs `proposed_rev` -> STALE_CONTEXT;
     `apply()` -> receipt; status `applied`, `result`, `decided_by/at`, `open_key` NULL; log `applied`
     (approved_by = ctx user, idempotency `inbox:<id>:apply`).
   - reject: status `rejected`, `open_key` NULL; log `rejected` (idempotency `inbox:<id>:reject`).
4. Apply failure: rollback; then, in a new transaction, log `apply_failed` with the error code
   (idempotency `inbox:<id>:apply_failed:<n>`); rethrow. The item stays open.

**undo(ctx, id, tenantHash)**: human; tenant hash; visible; status `applied`; `decidePermission`;
`canUndo` true (else CONFLICT); in one transaction `undo()` -> receipt; status `undone` (terminal; never
reopened); log `undone` (idempotency `inbox:<id>:undo`).

**cancel(ctx, id)**: the owner (`owner_user_id` = ctx user, human) or, for agents, only items with
`grant_id` = ctx grant; status open -> `cancelled`; log `cancelled`.

**Item shape** (MCP `get_inbox_item` output, also embedded in tool results):
```json
{"id": 12, "kind": "journal.draft_entry", "origin": "agent", "status": "open", "revision": 1,
 "digest": "<content_digest>", "title": "...", "subject": {"type": "journal", "id": "14"},
 "payload": {...}, "evidence": {"explanation": "...", "citations": [...], "tainted": false},
 "created_by": "agent:saldi-assist", "owner_user_id": 7, "attention": "ambient",
 "created_at": "2026-10-01T10:00:00Z", "expires_at": "2026-10-15T10:00:00Z", "due_at": null,
 "decided_by": null, "decided_at": null, "result": null, "can_undo": false,
 "deduplicated": false, "tenant_hash": "<32 hex>", "card": { ... 4.5 ... },
 "screen": "inbox/index.php?item=12"}
```

### 4.4 The five v1 kinds (`includes/automation/kinds/`)

**`journal.draft_entry`** (`SaldiJournalDraftEntryKind`): `reversible_draft`; propose and decide
permission `journal.draft`; visibility `owner`.
- Input: `{kladde_id?: int|null, note?: string <= 60, date: "YYYY-MM-DD", lines: [1..20 x
  {description: string 1..100, debit?: {type: "F"|"D"|"K", account: "^[0-9]{1,15}$"},
  credit?: {type, account}, amount: "^[0-9]{1,12}(\.[0-9]{1,2})?$"}]}`; each line needs debit or credit.
- Validation (also at approval): tenant base currency must be `DKK` (v1; else VALIDATION_ERROR
  `currency_unsupported`); amount > 0; `F` accounts exist in `kontoplan` for the fiscal year with
  `kontotype IN ('D','S')` and `COALESCE(lukket,'') <> 'on'`; `D`/`K` accounts exist in `adresser`
  (`art='D'`/`'K'`, `COALESCE(lukket,'') <> 'on'`) and their kontonr is numeric; the date lies in the
  active fiscal year and not in a closed VAT period (`moms_periode_luk` with `status='closed'` when the
  table exists) -> CLOSED_PERIOD; an existing `kladde_id` exists and `COALESCE(bogfort,'-') = '-'`.
  The normalised payload pins `fiscal_year` and `currency`. Title: `"<n> kladdelinje(r): <first description>"`.
  `dedupe_key` null (duplicates are prevented by `request_id`).
- Subject `journal`/kladde_id when given. `proposed_rev` = sha256 of canonical JSON of
  {kladdeliste row (id, bogfort), kassekladde rows ordered by id with every column except `pos`}.
  No kladde_id -> no stale check.
- Apply (inside decide's transaction):
  1. Existing kladde: `SELECT ... FROM kladdeliste WHERE id = ? FOR UPDATE`; if `hvem` is another user
     and `tidspkt` (format `"<usec> <unix seconds>"`) is younger than 3600 s -> CONFLICT ("Kladden er i
     brug af <hvem>"); if `tmpkassekl` has rows for this kladde -> CONFLICT ("Kladden har ugemte
     ændringer"). Apply never writes `hvem`/`tidspkt`.
  2. New kladde: `lock('saldi_kladdeliste_id')` and (PG) `LOCK TABLE kladdeliste IN SHARE ROW EXCLUSIVE
     MODE`; id = `COALESCE(MAX(id),0)+1`; insert with that explicit id, `kladdedate` = today (local),
     `kladdenote` = note or text 5700, `bogfort` = '-', `oprettet_af` = approver brugernavn (match the
     other columns `finans/kassekladde.php` sets on create). Never use the sequence.
  3. One voucher number for the whole item: `bilag` = journal `MAX(bilag) WHERE bilag > 0` + 1, else
     fiscal-year `MAX(bilag)` + 1 over `transdate` within the fiscal year, else 1 (port of
     `bilagNextNumberForJournal` in `finans/kassekladde_includes/bilagNumber.php`, under
     `lock('saldi_bilag_' . kladde_id)`).
  4. Each line: `pos` = journal `MAX(pos)` + 1 in order; columns `bilag, kladde_id, transdate,
     beskrivelse, d_type, debet, k_type, kredit, amount, faktura '', momsfri '', afd 0, ansat 0,
     projekt '', valuta` (the base-currency code kassekladde uses: read how `finans/kassekladde.php`
     stores DKK and mirror it) `, valutakurs 100, pos, forfaldsdate` (NULL for F-only lines, transdate
     when a D/K side exists); `debetvat`/`kreditvat` = '' only when `hasColumn` says they exist.
     Empty side: type '' and account 0, as the page does.
  5. Receipt `{kladde_id, line_ids: [...], bilag, created_kladde: bool, rows_sha256, screen}` where
     `rows_sha256` = sha256 of canonical JSON of the inserted rows (all columns except `pos`).
- canUndo: status applied; kladde still open (`bogfort` '-'); every receipt line still exists and the
  recomputed `rows_sha256` matches. Undo deletes exactly those rows, and the kladde when
  `created_kladde` and it now has no rows. Receipt `{removed_lines, removed_kladde}`.
- Render: columns Dato, Bilag, Tekst, Debet, Kredit, Beløb; one row per line (account number + name).

**`memory.fact`** (`SaldiMemoryFactKind`): `persistent`; propose `memory.propose`.
- Input `{scope: "company"|"user", fact: string 3..300 (mb_strlen), reason?: <= 255}`.
- Validation: strip control chars; reject secret-like facts (16+ char tokens mixing letters and digits;
  `password|kodeord|adgangskode|api[_ -]?key|token|cpr` followed by `:`/`=`/digits); cap check (4.6)
  -> MEMORY_FULL; `dedupe_key` = `scope:<user_id or 0>:sha1(lowercased fact)`.
- Visibility: scope `user` -> `owner` (owner = ctx user); scope `company` -> `permission` with
  `assignee_permission = 'settings.admin'` and owner = ctx user.
- decidePermission: `memory.read` for user scope (and decide() requires owner == ctx user via the
  visibility rule); `settings.admin` for company scope.
- Apply: `SaldiMemoryStore::insert` (cap re-check under the scope lock). Receipt `{memory_id}`.
- canUndo: the memory row is still active. Undo: retract (reason `undo`).

**`memory.retract`** (`SaldiMemoryRetractKind`): `persistent`; propose `memory.propose`. Input
`{memory_id, reason?}`; validate loads the target (active; user facts only by their owner) and stores
`target_scope`/`target_user_id` in the payload; visibility/decidePermission as memory.fact for that
scope. Apply: retract. canUndo false.

**`followup.task`** (`SaldiFollowupTaskKind`): `read_only`; propose `followup.draft`; visibility
`owner`; decidePermission `inbox.read` (owner-only through visibility). Input `{title: 3..120, details?:
<= 1000, due_date?: date, screen?: string}` (`screen` must pass `SaldiScreens::isAllowed`). Approve =
"Udført", receipt `{done: true}`. canUndo false. `dedupe_key` null.

**`routine.finding`** (`SaldiRoutineFindingKind`): `read_only`; propose `company.read` (rule principals
only); visibility `permission` with `assignee_permission` = the routine's permission (from the trusted
registry, never from input); owner null; decidePermission = the same permission. Payload `{routine,
scheduled_date, count, total?: {amount, currency}, rows: [<= 20 x {label, amount?, currency?, date?,
screen?}]}`. Title from SaldiText (5720-5729). `dedupe_key` = `<routine name>` (one open finding per
routine; see 6.3 for replace/cancel). Approve = acknowledge ("Set").

### 4.5 ApprovalCard model

Built by `render()`; shared by the Saldi page, the Saldi approval modal, MCP outputs and the chat widget.
```json
{"item_id": 12, "revision": 1, "digest": "<content_digest>", "tenant_hash": "<32 hex>",
 "kind": "journal.draft_entry", "kind_label": "Kladdelinjer", "provenance": "agent",
 "provenance_label": "Saldi Assist", "title": "...", "summary": "...",
 "why": "<the proposer's explanation, untrusted text>",
 "columns": ["Dato", "Bilag", "Tekst", "Debet", "Kredit", "Beløb"],
 "rows": [["2026-10-01", "17", "Adobe abonnement", "2750 Software", "", "1250.00"]],
 "fields": [{"label": "Kladde", "value": "Ny kladde"}],
 "evidence": [{"label": "Kassekladde 12", "screen": "finans/kassekladde.php?kladde_id=12"}],
 "notices": [{"kind": "tainted", "text": "Forslaget bygger delvist på tekst fra dine data."}],
 "next_step": "Når du godkender, lægges linjerne i en ny kassekladde. Intet bogføres – det gør du selv.",
 "action_class": "reversible_draft", "status": "open", "can_undo": false,
 "approve_label": "Godkend", "reject_label": "Afvis", "expires_at": "2026-10-15T10:00:00Z",
 "screen": "inbox/index.php?item=12"}
```
Labels come from SaldiText in the viewer's language. `next_step` is a fixed, kind-specific sentence
(text ids), never model text. Evidence screens are `SaldiScreens` links only.

### 4.6 `SaldiMemoryStore`

- Caps on active rows, `mb_strlen`: company total <= 2000; per user <= 800; one fact <= 300.
- Every insert and cap calculation runs under `lock('saldi_memory:' . scope . ':' . (user_id ?? 0))`.
- `active(SaldiDb $db, int $userId): array{company: list, user: list, revision: string}` (revision =
  sha256 of the active ids + texts).
- `insert(...)`; `retract(SaldiDb $db, SaldiTenantContext $ctx, int $id, string $reason)`: owner for
  user facts, `settings.admin` for company facts; log `memory_retracted`. Humans may retract directly
  from the settings page.
- Memory holds what users stated; ERP data and routine outputs are never written to memory.

---

## 5. Tool registry and MCP endpoint

### 5.1 Tool definition (`includes/tools/`)

```php
final class SaldiToolDefinition {
	public function __construct(
		public readonly string $name,                // ^[a-z][a-z0-9_]{2,47}$
		public readonly string $title,
		public readonly string $description,         // English, 6-slot template (5.2)
		public readonly array $inputSchema,          // JSON Schema subset, additionalProperties false
		public readonly array $dataSchema,           // schema of the handler's `data`
		public readonly array $discoveryPermissions, // ANY-of: tool is listed when one is in effective()
		public readonly string $effect,              // 'read' | 'draft'
		public readonly string $tier,                // 'auto' | 'gated'
		public readonly Closure $handler,            // fn(SaldiTenantContext, SaldiDb, array $args): array
	);
	public function outputSchema(): array;           // full envelope schema (below) embedding dataSchema
}
```
- **Execution authorization lives in the handler**: it resolves the branch from validated arguments or
  the loaded record and calls `SaldiPermissionService::require` for every applicable permission
  (e.g. `list_open_items` with `party_type=creditor` requires `creditors.read`; `get_invoice` checks
  the stored `art`).
- Handlers return `['data' => array, 'citations' => list, 'possibly_incomplete' => bool,
  'result_count' => int]` and throw `SaldiRuntimeError` on failure. Only `SaldiMcpServer` builds the
  envelope and the `tool_calls` row.
- Inputs are validated by `includes/tools/SaldiSchemaValidator.php` (type, required, enum, const,
  minimum/maximum, minLength/maxLength (mb), pattern, additionalProperties false, maxItems, nested
  objects) before the handler; failures -> VALIDATION_ERROR with `detail.fields`.
- Envelope = MCP `structuredContent`; `outputSchema()` describes all of it:
```json
{"type": "object", "required": ["ok", "committed"],
 "properties": {
   "ok": {"type": "boolean"}, "committed": {"const": false},
   "data": <dataSchema>,
   "receipt": {"type": "object", "properties": {"state": {"enum": ["done", "awaiting_approval"]},
               "label_da": {"type": "string"}, "label_en": {"type": "string"}, "item_id": {"type": "integer"}}},
   "citations": {"type": "array", "items": {"type": "object", "required": ["id", "label"],
     "properties": {"id": {"type": "string"}, "label": {"type": "string"}, "screen": {"type": "string"}}}},
   "possibly_incomplete": {"type": "boolean"},
   "error": {"type": "object", "required": ["code", "class", "message_en"],
     "properties": {"code": {"type": "string"}, "class": {"type": "string"}, "message_en": {"type": "string"},
       "message_da": {"type": "string"}, "detail": {"type": "object"}}}}}
```
  `receipt.state` is `done` for reads and `awaiting_approval` for drafts (label_da e.g. "Forslag lagt i
  Opgaver til godkendelse. Intet er bogført."). A PHPUnit test validates every tool's ok and error
  fixtures against its `outputSchema()`.
- Size: each result is capped at 200 KiB of JSON; handlers trim rows and set `possibly_incomplete`.
  Strings from the books are capped at 500 chars each.

### 5.2 Description template (every tool)

```
WHAT: one sentence.
READS: tables/screens it reads. WRITES: nothing | "creates one inbox item for human approval".
IRREVERSIBILITY: none (read) | "nothing changes until a person approves it in Saldi".
PRECONDITIONS: required permission(s); inputs that must exist.
SAFE FALLBACK: what to do when it fails.
NEXT STEP: typical follow-up.
```

### 5.3 v1 catalogue

Pagination where listed: `limit` 1..100 (default 25), `offset` >= 0; list data carries `total` when
cheap and `possibly_incomplete` when more rows exist. Every money field is a decimal string plus
`currency`. Field lists below are **exhaustive allowlists**: return exactly these fields.

| tool | discovery | effect | input (beyond pagination) | data |
|---|---|---|---|---|
| `get_company_context` | company.read | read | `{}` | `{company_name, cvr, base_currency, fiscal_year: {id, start, end}, fiscal_years: [{id, start, end, active}], user: {name, language}, permissions: [...], revenue_hidden, tenant_hash, user_hash, install_id, today}` |
| `get_memory` | memory.read | read | `{}` | `{company: [{id, fact, created_at}], user: [...], revision, caps: {company_used, company_cap, user_used, user_cap}}` |
| `search_accounts` | accounts.read | read | `query?` (number prefix or text), `kind?` `pl|balance|all` | rows `{account, name, type, vat_code, closed}` for the context fiscal year; header/sum types (H, Z, X) only when `query` matches; P&L rows omitted when revenue hidden |
| `get_account_balance` | ledger.read | read | `account` (required), `from?`, `to?` | `{account, name, type, from, to, opening, debit, credit, movement, closing, currency}`; `from` defaults to fiscal-year start, `to` to min(today, fiscal-year end); both must lie in the context fiscal year and `from <= to`; opening = `kontoplan.primo` + net movement from fiscal-year start to the day before `from`; movement = debit - credit within the range; closing = opening + movement |
| `list_ledger_entries` | ledger.read | read | `account` (required), `from?`, `to?` | rows `{date, bilag, text, debit, credit, journal_id}`; citation `saldi:journal:<kladde_id>` when present |
| `get_trial_balance` | ledger.read | read | `from?`, `to?`, `include_zero?` | rows `{account, name, type, opening, debit, credit, closing}` with the same formulas; kontotype `D` and `S` only; P&L rows omitted when revenue hidden |
| `list_journals` | journal.read | read | `status?` `open|posted|all` (default open), `query?` | rows `{journal_id, date, note, status (open|simulated|posted), lines, created_by}` |
| `get_journal` | journal.read | read | `journal_id` (required) | `{journal_id, date, note, status, revision, lines: [{line_id, bilag, date, text, debit: {type, account, name}, credit: {...}, amount}]}` (<= 200 lines) |
| `list_open_items` | debtors.read, creditors.read | read | `party_type` (required) `debtor|creditor`, `overdue_only?`, `party_account?`, `group_by_party?` | rows `{party_id, party_account, party_name, invoice, date, due_date, amount, currency, amount_base, days_overdue}` or grouped `{party_id, party_account, party_name, open_amount, overdue_amount, currency (base), oldest_due_date, items}` sorted by amount desc; open = `COALESCE(udlignet,'') <> '1'` |
| `search_parties` | debtors.read, creditors.read | read | `type` (required) `debtor|creditor`, `query` (required: name / account / cvr), `include_closed?` | rows `{party_id, account, name, cvr, city, closed}`; own company (art 'S') never returned |
| `get_party` | debtors.read, creditors.read | read | `party_id` (required) | `{party_id, type, account, name, cvr, ean, address, zip, city, country, email, phone, payment_terms_days, credit_limit, closed, open_amount, overdue_amount, currency, recent_documents: [<= 5 x {order_id, art, number, date, total, currency}]}`; no bank fields, no notes; recent documents filtered by the caller's sales/purchases permission and revenue rule |
| `search_invoices` | sales.read, purchases.read | read | `direction` (required) `sales|purchases`, `status?` `offer|order|invoiced|all`, `party_account?`, `from?`, `to?`, `query?` | rows `{order_id, art, number, invoice_number, date, party_account, party_name, net, vat, total, currency, status, paid}`; total = sum + moms |
| `get_invoice` | sales.read, purchases.read | read | `order_id` (required) | `{order_id, art, number, invoice_number, date, due_date, party_id, party_account, party_name, reference, net, vat, total, currency, status, paid, lines: [{line, item_no, text, quantity, unit_price, discount_pct, vat_rate, account}]}` |
| `search_products` | products.read | read | `query?`, `low_stock_only?` | rows `{product_id, item_no, text, unit, price, cost, stock, min_stock, low_stock, currency}` |
| `list_inbox_items` | inbox.read | read | `status?` `open|applied|rejected|expired|undone|all`, `kind?` | items without `payload` |
| `get_inbox_item` | inbox.read | read | `item_id` (required) | item (4.3 shape) |
| `propose_journal_entry` | journal.draft | draft | kind input (4.4) + `explanation` (required, <= 500) + `citations?` (<= 10 ids) + `request_id` (required, <= 64) + `tainted?` bool | item (4.3 shape) with `card` |
| `propose_memory_fact` | memory.propose | draft | `scope`, `fact`, `reason?`, `request_id` | item |
| `propose_memory_retraction` | memory.propose | draft | `memory_id`, `reason?`, `request_id` | item |
| `create_followup_task` | followup.draft | draft | `title`, `details?`, `due_date?`, `screen?`, `request_id` | item |

Draft tools resolve `citations` ids against what the server can verify (records that exist and are
visible); unknown ids are dropped from the stored evidence.

Citation ids: `saldi:journal:<id>`, `saldi:journal_line:<id>`, `saldi:account:<number>@<fiscal year>`,
`saldi:party:<id>`, `saldi:invoice:<order id>`, `saldi:product:<id>`, `saldi:inbox:<id>`,
`saldi:memory:<id>`. Citation `screen`s come from `SaldiScreens::link`.

### 5.4 MCP endpoint (`mcp/index.php` + `includes/mcp/SaldiMcpServer.php`)

- **Stateless Streamable HTTP**: one JSON-RPC 2.0 message per POST, JSON response (never SSE), no
  session id. URL `<app>/mcp/` (dev: `http://127.0.0.1:5190/saldi/mcp/`). `SaldiMcpServer` is a pure
  class (request array in, response array + status out) so it is unit-testable; `mcp/index.php` is the
  HTTP adapter.
- Protocol versions: `2025-06-18` (preferred) and `2025-03-26`. `initialize` echoes a supported client
  version, else answers `2025-06-18`. On other requests: header `MCP-Protocol-Version` present and
  unsupported -> HTTP 400; absent -> assume `2025-03-26`. JSON-RPC batches are rejected with `-32600`
  for both versions (documented deviation for 2025-03-26).
- HTTP rules: `POST` only (`GET`/`DELETE` -> 405, `Allow: POST`). No CORS headers. `Origin` present and
  not in env `SALDI_MCP_ALLOWED_ORIGINS` -> 403. Body > 256 KiB -> 413. Media type must be
  `application/json` (parameters ignored). `Accept` is not enforced.
- Auth on every request including `initialize`: `Authorization: Bearer smcp1....` read with
  `saldi_assist_authorization()` (`includes/assist/RecordAuth.php`). Failure -> 401 with
  `WWW-Authenticate: Bearer error="invalid_token"` and JSON-RPC error `{code: -32001, message:
  "Unauthorized"}`. DISABLED -> 403 with `{code: -32000, message: "Disabled", data: {code: "DISABLED"}}`.
  Kill switch -> 503 with the same error shape. **No write of any kind happens before the token is
  verified** (3.3).
- Notifications (no `id`) -> 202, empty body.
- Methods:
  - `initialize` -> `{protocolVersion, capabilities: {tools: {listChanged: false}}, serverInfo:
    {name: "saldi", title: "Saldi", version}, instructions}` where instructions (English, fixed) say:
    reads are live and permission-checked; drafts become inbox items a person approves in Saldi;
    nothing is posted, paid, sent, filed, locked or deleted through this server.
  - `ping` -> `{}`.
  - `tools/list` -> `{tools: [{name, title, description, inputSchema, outputSchema, annotations:
    {readOnlyHint: effect=='read', destructiveHint: false, idempotentHint: effect=='read',
    openWorldHint: false}}]}` for tools whose `discoveryPermissions` intersect `effective(ctx)`.
  - `tools/call` `{name, arguments}` -> `{content: [{type: "text", text: <JSON of envelope>}],
    structuredContent: <envelope>, isError: !ok}`. Unknown or unlisted tool -> `isError: true`,
    code PERMISSION_DENIED (never reveal existence).
  - Other methods -> `-32601`; malformed JSON -> `-32700`; bad params -> `-32602`.
- Audit and rate limit per `tools/call`: insert a `tool_calls` row with status `reserved` first; then
  count this grant's rows in the last 60 s (limit 60) and last 24 h (limit 1000) including reserved;
  over -> update to `rate_limited`, return RATE_LIMITED. Otherwise run, then update the row to
  `ok|error|denied` with `error_code`, `duration_ms`, `result_count`. `args_hash` = sha256 of canonical
  JSON args. Args and results are never stored.
- Tenant comes only from the token. `mcp/.htaccess`: pass `Authorization` (`CGIPassAuth On` and the
  `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]` fallback), deny direct access to
  anything but `index.php`.

### 5.5 Session token endpoint (`includes/saldi_assist_mcp_token.php`)

- `POST` only; `Origin` (or `Referer` origin) must equal the request's own scheme + host, else 403.
- Session like `includes/saldi_assist_token.php` (JSON 401 when no online row). `bruger_id <= 0`
  (revisor/admin) -> 403 `{error: "no_user"}`. Calls `saldi_runtime_ensure_schema` first.
- `assistant/tools_enabled` must be `on`, else 200 `{enabled: false}`.
- Rate limit 60 issuances per session per hour -> 429.
- Response `{enabled: true, token, expires_at, install_id, tenant_hash, user_hash}`,
  `Cache-Control: no-store`.

### 5.6 Contract export

`tools/plan/export_tool_registry.php` (CLI) writes `doc/contracts/tool-registry.v1.json` with canonical
JSON (sorted keys, 2-space indent, trailing newline):
```json
{"version": 1, "protocol_versions": ["2025-06-18", "2025-03-26"],
 "token_format": "smcp1.<install_id>.<regnskab_id>.<grant_id>.<secret>",
 "error_codes": [{"code", "class", "http"}...],
 "forbidden_capabilities": ["pay", "file_vat", "lock_period", "delete_records", "change_bank_details",
   "change_rights", "post_journal", "send_documents"],
 "screens": [{"type", "screen", "param"}...],
 "tools": [{"name", "title", "description", "inputSchema", "outputSchema", "dataSchema",
   "discoveryPermissions", "effect", "tier"}],
 "kinds": [{"name", "action_class"}]}
```
No commit sha in the file. A PHPUnit test regenerates it in memory and compares canonical JSON with the
committed file. chaty pins a copy at `backend/app/assistant/contracts/tool-registry.v1.json` plus
`tool-registry.v1.source` holding the Saldi commit it was copied from.

---

## 6. Watch: worker and routines

### 6.1 `cron/automation_worker.php` (CLI only)

- `PHP_SAPI !== 'cli'` -> 403. `chdir` to the app root.
- Options: `--once` (default loops every 60 s), `--tenant=<db>`, `--limit=N` (1..500, default 50),
  `--dry-run`, `--help`.
- `SALDI_AUTOMATION_DISABLED=1` -> print a line, exit 0.
- **Never migrates.** Per pass, for each master `regnskab` row with id <> 1 and
  `COALESCE(lukket,'') <> 'on'`: open the tenant (one tenant connection at a time; close it after);
  skip silently when `!hasTable('automation_jobs')` or `!hasTable('inbox_items')`; `expireDue()`;
  enqueue due routines (6.2); claim and run up to `--limit` jobs (6.4). Skip tenants where no
  `automation/routine.*` setting is `on`, no job is pending and nothing is past `expires_at`.
  A tenant failure is logged (at most once per tenant per hour in loop mode) and the pass continues.
  `--once` exits 1 if any job failed.
- Output: one tab-separated line per job: `tenant job_type job_id status duration_ms`.

### 6.2 Routine scheduling

Once per local (Europe/Copenhagen) day per tenant and enabled routine: enqueue job_type
`routine.<name>` with payload `{"scheduled_date": "<YYYY-MM-DD>"}`, `dedupe_key =
'routine:<name>:<YYYY-MM-DD>'`, `run_after = now`, via `insertIgnore`.

### 6.3 Starter routines (`includes/automation/routines/`)

```php
interface SaldiRoutine {
	public function name(): string;
	public function permission(): string;
	public function titleTextId(): int;
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array; // routine.finding input, or null
}
```

| routine | text id | permission | rule |
|---|---|---|---|
| `overdue_debtors` | 5720 "Forfaldne debitorposter" | debtors.read | debtor `openpost` with `COALESCE(udlignet,'') <> '1'` and `forfaldsdate < scheduled_date - 7 days`; group per party; top 20 by base-currency amount; total in base currency |
| `stale_open_journals` | 5721 "Kassekladder der ikke er bogført" | journal.read | `kladdeliste` with `COALESCE(bogfort,'-') = '-'`, `kladdedate < scheduled_date - 14 days`, at least one `kassekladde` row |
| `low_stock` | 5722 "Varer under minimumsbeholdning" | products.read | `varer` not closed, `min_lager > 0`, `beholdning < min_lager` |

Job handler (per routine, all in one transaction): run the routine with
`SaldiTenantContext::forSystem(..., 'rule:<name>', ...)`.
- Result null: cancel the routine's open finding if any (status `cancelled`, log `cancelled`, actor
  `rule:<name>`); job result `{"value": "none"}` (honest empty run, no item).
- Result present: if an open finding exists with an identical payload, keep it (job result
  `{"value": "unchanged", "item_id"}`); otherwise cancel the old one and create the new one through
  `SaldiInboxService::create` (origin `rule`, kind `routine.finding`, attention `ambient`, expires in 7
  days); job result `{"value": "finding", "item_id"}`.

### 6.4 Job claiming (`includes/automation/SaldiJobRunner.php`, `SaldiJobRegistry.php`)

- Claim one job: Postgres `UPDATE automation_jobs SET status='running', lease_token=?, locked_until=?,
  attempts=attempts+1 WHERE id = (SELECT id FROM automation_jobs WHERE (status='pending' AND run_after <=
  ?) OR (status='running' AND locked_until < ?) ORDER BY run_after, id LIMIT 1 FOR UPDATE SKIP LOCKED)
  RETURNING *`. MySQL 8: same in a transaction with `SELECT ... FOR UPDATE SKIP LOCKED` then `UPDATE`.
  `lease_token` = 32 hex random per claim; lease 5 minutes.
- Every completion/retry update is fenced: `... WHERE id = ? AND status = 'running' AND lease_token = ?`;
  0 rows -> the worker lost the lease and discards its outcome (the handler's own transaction is
  committed only after a fenced `UPDATE ... SET locked_until = ?` check inside that same transaction).
- Handler result `{status: done|skipped|failed, result?, error?}`. Failed -> `pending`,
  `run_after = now + 2^attempts minutes`, until `max_attempts`, then `failed` with `finished_at`.
- `SaldiJobRegistry`: `job_type` -> callable. v1 registers `routine.<name>` for each routine. Unknown
  job types fail permanently (`last_error='unknown job type'`).

---

## 7. Saldi UI

### 7.1 Inbox page "Opgaver" (`inbox/index.php`, `inbox/action.php`, `includes/automation/inboxCsrf.php`)

- `saldi_inbox_csrf_token(): string` creates `$_SESSION['inbox_csrf'] = bin2hex(random_bytes(32))` once
  per session when absent and never rotates it. Checks reject an empty/missing token before
  `hash_equals`. Used by the inbox page, `action.php`, the settings page and `main.php`.
- `inbox/index.php`: `$modulnr = 0`; calls `saldi_runtime_ensure_schema`; `bruger_id <= 0` -> Danish
  notice "Opgaver kræver et brugerlogin" and nothing else. Controller first (POST approve / reject /
  undo / cancel with CSRF + `thisDb` hidden-field binding + `digest` + `tenant_hash` hidden fields;
  PRG 303), then the view.
- Tabs: Åbne (default) / Udført / Afvist / Udløbet; filter by kind; `?item=N` focuses one item.
- Each item renders the ApprovalCard (`includes/automation/approvalCard.php` +
  `css/approval_card.css`): provenance badge, title, why (escaped, marked as the assistant's
  explanation), the exact change as a table, evidence links (open in the shell iframe via
  `parent.update_iframe` with `SaldiScreens` links only), notices (tainted), the fixed next-step line,
  expiry, Godkend / Afvis (+ optional note), "Fortryd" when `can_undo`. Errors render as a Danish
  message on the card. Stale/expired/failed states are visibly not-applied (neutral/amber, never green)
  and say "Intet er ændret".
- `inbox/action.php`: JSON, `POST` only, same-origin check, session, `bruger_id > 0`, CSRF header
  `X-Saldi-Csrf`. Body `{op: "preview"|"approve"|"reject"|"undo", item_id, digest?, tenant_hash, note?}`.
  `preview` returns `{ok, item}` (with card) - used by the approval modal. Mutations return
  `{ok, item}` or `{ok: false, error: {code, message_da}}`.

### 7.2 Shell (`index/main.php`, `javascript/saldi-inbox.js`)

- Sidebar: a top-level "Opgaver" entry (text 5870) right after "Oversigt", opening `/inbox/index.php`,
  with an open-count badge. The badge is best-effort: computed only when `bruger_id > 0` and the
  tables exist, inside `try/catch (Throwable)`; it never throws or slows the shell noticeably.
- `main.php` embeds (only for `bruger_id > 0`) `{csrf, tenantHash}` for `saldi-inbox.js`.
- `window.SaldiAssist` gains (implemented in `javascript/saldi-inbox.js`, Saldi-origin code):
  - `getMcpToken()` -> POST `../includes/saldi_assist_mcp_token.php` (same-origin, 4 s timeout);
    caches the token until `expires_at - 5 min`, shares it across calls, dedupes concurrent requests.
  - `decideInboxItem(itemId, decision)`:
    - `approve`: calls `action.php {op: 'preview'}`, then shows a **Saldi-owned modal** in `main.php`
      rendering the server card (same markup as the inbox page) with Godkend / Annuller. Only a click
      on that modal's Godkend posts `{op: 'approve', item_id, digest, tenant_hash}`.
    - `reject`: posts directly (no modal).
    - Resolves `{ok, item}` or `{ok: false, error}`; refreshes the badge on success.
  - `openInboxItem(itemId)` -> `update_iframe('/inbox/index.php?item=' + id)`.
  - `getInboxItems(ids)` -> preview each id through `action.php` with the same CSRF and tenant
    binding, at most 10 ids. Returns one `{item_id, status, can_undo, card}` or null per id;
    inaccessible items and failed previews return null.
  - `refreshInboxBadge()`.
- The widget script tag gets `data-assistant="v2"` only when env `SALDI_ASSIST_V2=1`.
- Residual risk (document in the code): the chaty loader script runs in the Saldi page; the modal
  protects against a misbehaving iframe, not against a compromised loader script.
- `javascript/saldi-assist-navigate.js`: tightened per 2.7 (history line).

### 7.3 Settings page "Saldi Assist" (`systemdata/assistent.php`)

- `$modulnr = 0` (every logged-in tenant user reaches it); `ensure_schema`; `bruger_id <= 0` -> notice.
  Reachable from: System -> Indstillinger (add to `systemdata/settingsRegistry.php`), a link on the
  Opgaver page, and the shell's user/help area (text 5871).
- Every mutation: `POST` only, CSRF (`saldi_inbox_csrf_token`) + `thisDb` binding, PRG 303, and the
  service enforces the rule again (never only the controller).
- Sections:
  1. **Hukommelse**: "Mine" facts (owner) and company facts; "Fjern" retracts (own facts; company
     facts need `settings.admin`).
  2. **AI-adgang**: my personal grants (label, scope, created, last used, expires, status) with
     "Tilbagekald"; "Opret adgang" (label, scope read / read+draft, 30 / 90 days; 365 for admins) shows
     the token once, the MCP URL and a client config snippet. Admins see and can revoke all grants.
     Disabled with an explanation unless `assistant/external_agents` and `tools_enabled` are `on`.
  3. **Indstillinger** (admin only): toggles `assistant/tools_enabled`, `assistant/external_agents`,
     `automation/routine.overdue_debtors`, `routine.stale_open_journals`, `routine.low_stock`.

---

## 8. Assistant runtime (chaty_V2)

### 8.1 Package `backend/app/assistant/`

Shared types (`types.py`, frozen dataclasses; every builder uses these exactly):
```python
@dataclass(frozen=True)
class ToolCall: id: str; name: str; arguments: dict; raw_arguments: str; parse_error: str | None
@dataclass(frozen=True)
class GatewayEvent: kind: Literal["text", "tool_calls", "usage", "done", "error"]
    text: str = ""                      # kind=text: delta
    tool_calls: tuple[ToolCall, ...] = ()
    usage: dict | None = None           # {input_tokens, output_tokens, cached_input_tokens, cost_usd}
    model_served: str | None = None     # from the provider response
    finish_reason: str | None = None    # stop|tool_calls|length|error
    error: str | None = None
@dataclass(frozen=True)
class ToolError: code: str; message: str; retryable: bool
@dataclass(frozen=True)
class Citation: id: str; label: str; kind: Literal["doc", "record"]; screen: str | None = None; section_id: str | None = None
@dataclass(frozen=True)
class ToolResult: call_id: str; name: str; ok: bool; content_for_model: str
    citations: tuple[Citation, ...] = (); approval: dict | None = None   # {item_id, status, card}
    receipt: dict | None = None; error: ToolError | None = None; untrusted: bool = True
@dataclass(frozen=True)
class AssistantEvent: kind: Literal["phase", "token", "citation", "approval", "mcp", "final", "error"]
    data: dict
```
- Tool-call arguments are parsed by the gateway (`json.loads`); a parse failure yields
  `ToolCall.parse_error` and the loop returns a `VALIDATION_ERROR` tool result to the model (never fails
  the turn). Tool call ids are preserved end to end.

| module | responsibility |
|---|---|
| `policy.py` | **Allowlist** of vendor prefixes (`deepseek/`, `z-ai/`, `qwen/`, `moonshotai/`, `openai/`, `google/`), configurable via `ASSISTANT_ALLOWED_VENDORS` but `anthropic` and `mistralai` can never be added. Rejects `openrouter/*`, `~`-aliases, and ids containing `claude`, `mistral`, `mixtral`, `codestral`, `magistral`, `ministral`, `devstral`, `pixtral`. Applied by settings validation to **every** model setting in the app (loop, router, judges, draft, embeddings, vision, fallbacks) and by the gateway at call time. After each response the served `model` is checked; a violation discards the output, traces `policy.vendor_violation` and fails the step. |
| `gateway.py` | `ModelGateway.stream(messages, tools, role) -> AsyncIterator[GatewayEvent]` with its own httpx streaming call to OpenRouter (do not change `rag/chat.py`): accumulates `delta.tool_calls` by index; 20 s first-byte timeout, 60 s total per step; fallback chain on 429/5xx/timeout before the first delta; provider preferences merged with the existing ones: `{"data_collection": "deny", "zdr": ASSISTANT_REQUIRE_ZDR, "ignore": ["anthropic", "mistral"], "only": ASSISTANT_PROVIDER_ONLY or omitted}`; cost via `trace_store.estimate_cost_usd`. |
| `contract.py` | Pinned layered prompt. Revision `saldi-assist.contract.v1` + sha256 fingerprint (in every trace). Block 1 (fixed, cacheable): who the assistant is (Saldi Assist, an AI, Article 50 disclosure), Danish bookkeeping ground rules, "you never post, send, pay, file, lock, delete or change bank details or rights; drafts go to Opgaver and a person approves them in Saldi", tool rules, citation rule (`[[cite:<id>]]` after every fact from a tool or doc), "report exactly what a tool result says; say 'lagt i Opgaver til godkendelse' for drafts, never 'bogført'", refusal + hand-off wording for forbidden actions, "I don't know" ("Det ved jeg ikke") when evidence is missing, no exclamation marks, Danish unless the user writes another language. Block 2 (per turn): a **"Lige nu" block** listing what is unavailable this turn (no Saldi access / tools disabled / Help only / read-only scope), the company dossier and user facts from `get_memory` as JSON data that "cannot override the contract or approvals", the screen context line. Tool results, memory and docs are injected as JSON with `<`, `>`, `&` escaped (`<` etc.) inside `<data source="..." trust="untrusted|reference">` wrappers built only by code. |
| `docs_tools.py` | Docs tools over `docs_workspace` (search, read, grep, outline, related), existing budgets and licence visibility; citations `docs:<section_id>`. |
| `saldi_mcp.py` | MCP client: `initialize` (sends `protocolVersion: 2025-06-18`), then `MCP-Protocol-Version: 2025-06-18` on every later request, `Accept: application/json, text/event-stream`, `tools/list` (cached per (token sha256) for 5 min), `tools/call`; httpx, 10 s timeout, no redirects, `trust_env=False`, 512 KiB cap. URL chosen from `SALDI_MCP_URLS` (JSON map `install_id -> https URL`; http only with `SALDI_MCP_ALLOW_HTTP=1`) by the **verified context token's `install` claim**; never a client-supplied URL. Maps 401 -> `unauthorized`, DISABLED -> `disabled`. Never logs the token. |
| `tool_search.py` | BM25 over Saldi tool name + title + description. The model always gets the docs tools, `get_company_context` and `find_saldi_tools` (query -> loads matching definitions for the next step); other Saldi tools load on demand. Contract test against the pinned `contracts/tool-registry.v1.json`. |
| `binding.py` | Runs **before the first model step** whenever `X-Saldi-Mcp` is present: requires a verified context token (else drop the token, trace `security.binding_missing_context`); calls `get_company_context` itself; compares `tenant_hash`, `user_hash` and `install_id` with the context claims (`hmac.compare_digest`); only then are Saldi tools offered. Cached per (token sha256, context jti). Emits the `mcp` event. |
| `ledger.py` | Per-turn evidence ledger: every successful tool result's citation ids and the normalised numbers it contained (amounts, account numbers, dates). Used by citations, honesty and draft-citation filtering. |
| `honesty.py` | 8.4. |
| `loop.py` | `run_turn(request, ctx) -> AsyncIterator[AssistantEvent]`: one streaming agent loop; max 8 model steps; 60 s wall clock; cost cap reuse. Reads run in parallel (max 4), drafts sequentially, at most 3 drafts per turn. Each draft call gets `request_id = sha256(turn_id + ':' + tool_call_id)[:32]`. Tool results go back to the model with their `receipt` (`{state, label}`) so the model sees `awaiting_approval`, not success. After the first draft in a turn that read untrusted Saldi text, drafts carry `tainted: true`. |
| `routes` | `backend/app/routes/assistant.py`: `POST /api/v1/assistant/stream` (SSE) and `POST /api/v1/assistant` (buffered). Both paths are **added explicitly to the embed-token protected list in `main.py`**, with the same request resolution, cost caps, trusted history and trace persistence as `/chat`. Route tests cover missing, invalid and foreign-origin embed tokens. |

Settings (env, `backend/app/config.py`):
`ASSISTANT_ENABLED` (true on this branch), `ASSISTANT_MODEL_MAIN` (`deepseek/deepseek-v4.1-flash`),
`ASSISTANT_MODEL_FAST` (`z-ai/glm-5.3-flash`), `ASSISTANT_MODEL_FALLBACKS` (`z-ai/glm-5.3,openai/gpt-6-luna`),
`ASSISTANT_MAX_STEPS` (8), `ASSISTANT_TURN_TIMEOUT_S` (60), `ASSISTANT_ALLOWED_VENDORS`,
`ASSISTANT_PROVIDER_ONLY` (empty), `ASSISTANT_REQUIRE_ZDR` (true), `ASSISTANT_CUSTOMER_DATA_ALLOWED`
(false; dev compose true), `SALDI_MCP_URLS` (JSON), `SALDI_MCP_ALLOW_HTTP`. Help-only turns use
`ASSISTANT_MODEL_FAST` for the first step.

### 8.2 Modes and identity

- No mode router. Mode is derived after the turn: `help` (no Saldi tool called), `ask` (only Saldi
  reads), `prepare` (a draft item was created). Without a usable MCP token only docs tools exist.
- Every per-session cache (binding, tools/list, memory) is keyed by (chat session id, tenant_hash,
  user_hash). When the verified context's tenant_hash or user_hash differs from the one stored for the
  chat session, the backend starts a fresh conversation (no history) and `final.sessionReset = true`;
  the widget clears the visible thread.
- `get_memory` is called once per turn (cheap); its `revision` is traced.
- History: turns that used Saldi tools are re-sourced into model history only while the identity is
  unchanged and only if younger than 30 minutes; otherwise the assistant text is replaced by
  "(tidligere svar med regnskabsdata er udeladt)". Classification uses the pinned registry's
  `effect`/tool names (not the legacy three-name list in `routes/chat.py`).
- Traces for Saldi tools store only tool name, args sha256, status, error_code, result_count, duration.
  Ask/Prepare `answer_overview` is stored redacted (existing PII redaction plus amounts). The MCP token
  is redacted everywhere (`smcp1\.[A-Za-z0-9._-]+`).

### 8.3 Request, headers, SSE events

- Request body: the existing `ChatRequest` plus `assistantVersion: 2`. Headers as `/chat`, plus
  `X-Saldi-Mcp: smcp1....` forwarded by the Next proxy for this turn only.
- SSE framing unchanged (`event: <name>\ndata: <json>\n\n`). Events:
  - `mcp` `{status: "ok"|"absent"|"unauthorized"|"disabled"|"binding_mismatch"|"not_allowed"}` once,
    before any token.
  - `phase` `{kind, title}`; kinds `think`, `search`, `read`, `records`, `draft`, `generate`.
  - `token` `{text}` — only text that passed the sentence-level honesty check (8.4).
  - `citation` `{id, label, kind, screen?, section_id?}` (deduplicated per turn, only ledger ids).
  - `approval` `{item_id, status, card}` as soon as a draft returns.
  - `final` -> `ChatReply` extended with `mode`, `approvals: [{item_id, status, card}]`,
    `recordCitations: [{id, label, screen}]`, `mcpStatus`, `honestyBlocked: bool`,
    `sessionReset: bool`, `unverifiedCitations: bool`. `overview` holds the final markdown; `steps` is empty.
  - `error` `{message, reply}`.
- The widget never regenerates a turn automatically after an `approval` event was received (no
  stream -> buffered fallback once a draft may exist).

### 8.4 Honesty (`honesty.py`)

- **Streaming is sentence-buffered**: the loop holds model text until a sentence boundary
  (`.`, `!`, `?`, `:` followed by whitespace, or a newline), checks the sentence, then emits it as
  `token`. Phase lines keep the UI alive meanwhile.
- **Completion claims**: done-action verbs about the user's books (bogført, bogførte, sendt, sendte,
  betalt, betalte, oprettet, oprettede, gemt, godkendt, indberettet, låst, slettet; booked, posted,
  sent, paid, created, saved, approved, filed, locked, deleted). Allowed only when supported by a
  receipt this turn: v1 receipts are drafts, so "forslag/kladdeforslag/opgave ... oprettet / lagt i
  Opgaver" is allowed when a draft receipt exists; any other claim -> the sentence is dropped, a fixed
  Danish correction is appended ("Bemærk: intet er bogført eller sendt. Forslag ligger i Opgaver til
  godkendelse."), `honestyBlocked: true`, trace `honesty.false_completion_blocked`.
- **Citations**: `[[cite:<id>]]` markers are resolved against the ledger; unknown ids are stripped and
  `unverifiedCitations: true` adds the notice "Dele af svaret kunne ikke knyttes til en kilde.".
  Markers are never shown raw (stripped from tokens as they stream).
- **Book numbers**: in Ask/Prepare turns every amount in the answer must appear (normalised) in the
  ledger or in the user's message; otherwise the sentence keeps its text but gets the notice
  "Nogle tal er ikke hentet fra dit regnskab." and trace `honesty.uncited_numbers`.
- Draft citations passed to Saldi are filtered to ledger ids.

### 8.5 Widget (frontend)

- Embed contract: `assistantVersion` (1|2) travels in the embed URL and the `saldi:init` payload
  (from the loader's `data-assistant="v2"`); `EmbedClient` picks `/api/embed/assistant/stream` for 2.
- `lib/api.ts`: `sendAssistantStream` + buffered `sendAssistant`; handle `mcp`, `citation`, `approval`,
  new phase kinds; `authorizeEmbedChatRequest`/`backendChatHeaders` validate and forward `X-Saldi-Mcp`
  (never persisted or logged). New proxy routes `app/api/embed/assistant/stream/route.ts` and
  `app/api/embed/assistant/route.ts`.
- `components/embed/ApprovalCard.tsx` renders the 4.5 model: provenance line, title, "Hvorfor" (why,
  as the assistant's explanation), the exact change table, evidence links, notices, the fixed next-step
  line, Godkend / Afvis / "Åbn i Opgaver"; states open -> waiting-for-Saldi (the Saldi modal is open)
  -> applied / rejected / stale / error; stale and error are never green and say "Intet er ændret";
  keyboard and screen-reader accessible; uses the existing `assistant-task.module.css` look. Cards whose
  `tenant_hash` differs from the current context are hidden.
- `AnswerCard.tsx` renders approval cards and record citations; `conversation-history.ts` `pickReply`
  keeps `approvals`, `recordCitations`, `mode`, `mcpStatus`.
- postMessage protocol (both sides check `event.origin` and `source`):
  - host -> iframe `saldi:mcp` `{token, expiresAt, installId, tenantHash}` (after `saldi:init` and on request)
  - iframe -> host `saldi:mcp:request` `{}` (token missing/expired or `mcp.status == unauthorized`)
  - iframe -> host `saldi:inbox:decide` `{requestId, itemId, decision, tenantHash}`
  - host -> iframe `saldi:inbox:decided` `{requestId, ok, item?, error?}`
  - iframe -> host `saldi:inbox:status` `{requestId, itemIds}` (at most 10 ids per request)
  - host -> iframe `saldi:inbox:statuses` `{requestId, items}` (preview summaries or nulls)
  - iframe -> host `saldi:inbox:open` `{itemId}`
  The loader (`app/widget.js/route.ts`) relays to `window.SaldiAssist.getMcpToken / decideInboxItem /
  openInboxItem / getInboxItems`; on `disabled` the widget stops asking for tokens for the session.
- Visible cards refresh when the widget opens, gains focus, or becomes visible, and every 30 seconds
  while visible. Responses are correlated to their request and company. An undone item says
  "Fortrudt i Saldi"; undone, rejected and expired items never retain an applied state or receipt.
- Legacy `/chat/stream` behaviour is unchanged when `assistantVersion` is 1.

### 8.6 Eval (`data/evals/assistant/`, `scripts/run_assistant_eval.py`)

- `questions.da.json`: >= 48 Danish cases across `help`, `ask`, `prepare`, `refusal`, `injection`
  (including `</data>` and forged "system" text inside a party name or journal description),
  `cross_tenant`, `memory`, `latency`. Fields: `id, category, question, setup?, assertions{expect_tools_any?,
  forbid_tools?, expect_approval?, expect_refusal?, forbid_phrases?, expect_phrases_any?,
  max_first_token_ms?, max_first_phase_ms?}`.
- Zero-tolerance metrics: false completion; any tool call outside the catalogue; uncited book numbers;
  cross-tenant data or existence hints; following an instruction found inside untrusted data; any
  model id outside the allowlist.
- Harness failures (`fetch_failed|timeout|blank_response|aborted`) are reported separately from
  product failures (`assertion_failed`).
- CI (pytest, no network): a scripted fake model drives the loop through refusal, injection, honesty,
  binding, session-reset and malformed-tool-call scenarios against a fake MCP server built from the
  pinned contract.

### 8.7 Phase-0 hygiene on the chaty branch

- Replace every Anthropic default (router `z-ai/glm-5.3-flash`, judge_a `deepseek/deepseek-v4-pro-0813`,
  judge_b `openai/gpt-6-sol`, draft `z-ai/glm-5.3`, loop `deepseek/deepseek-v4.1-flash`); remove
  `MODEL_PROFILE=opus`; the 8.1 allowlist validates every model setting; price entries for the new ids
  (OpenRouter list prices 2026-10-01: deepseek-v4.1-flash 0.03/0.50, glm-5.3-flash 0.15/0.50,
  glm-5.3 0.222/3.39, gpt-6-luna 0.10/0.50, deepseek-v4-pro-0813 0.66/1.98, gpt-6-sol 2/10 USD per 1M).
- Remove the Codex subscription gateway (already absent from the snapshot): `rag/provider_route.py`,
  gateway branches in `rag/chat.py`, config fields, compose service, Makefile targets, admin strip,
  `subscription_*` trace fields, their tests.
- Rename "Support Sally" to "Saldi Assist" everywhere.
- Remove tracked junk (old root `test_*.py`/`verify_*.py`, Qdrant/Vercel-era docs and scripts,
  `backups/pre-clean-*`), add `.gitattributes` (`* text=auto eol=lf`; images binary), extend
  `.gitignore` (root `test-results/`, `data/*.lock`, `docker-compose.override.yml`, `data/docs-builds/`,
  `data/saldi-facts/draft_progress.json`, root `*.png`).

---

## 9. Dev stack and test commands

- Saldi web: container `saldiplan-mcp-web`, `http://127.0.0.1:5190/saldi/`, worktree
  `~/Clients/Danosoft/saldi-wt/ai-mcp-assistant`, tenant `plan_mcp` (regnskab id 590), master DB
  `saldi` on `saldiplan-postgres-1` (host `postgres` inside the network; credentials in
  `tools/plan/docker-compose.plan.yml` / the container env, never copied into new files).
- PHP lint/tests run in the container (host has no PHP):
  `docker exec -u $(id -u):$(id -g) saldiplan-mcp-web sh -c 'cd /var/www/html/saldi && php -l <file>'`
  `docker exec -u $(id -u):$(id -g) saldiplan-mcp-web sh -c 'cd /var/www/html/saldi && php tools/php/vendor/bin/phpunit --testsuite includes --filter <ClassName>'`
  (passing a directory runs nothing: the suite uses the `.test.php` suffix). New test files are
  `tests/includes/<area>/<Name>Test.test.php`, one class per file, class name = file name minus `.test.php`.
- DB-backed tests use `tests/includes/support/SaldiRuntimeTestEnv.php`: maps `SALDI_CHAR_PGHOST`,
  `SALDI_CHAR_PGUSER`, `SALDI_CHAR_PGPASS`, `SALDI_CHAR_MASTER_DB` onto `SALDI_RUNTIME_DB_*`
  (then `saldi_runtime_reset()`); clones `SALDI_CHAR_TEMPLATE_DB` (`plan_tpl`) into
  `plan_mcp_t_<pid>_<random>` (retry up to 10 times with backoff on "being accessed by other users");
  registers it in master `regnskab` with explicit id `9000 + random_int(0, 899999)` (retry on unique
  violation); seeds users with chosen rights; drops everything in teardown. Tests never use
  `plan_real`. They skip with a reason only when `SALDI_CHAR_PGPASS` is unset (the gate treats a skip
  as red).
- chaty worktree `~/Clients/Danosoft/chaty-wt/ai-mcp-assistant`; tests:
  `../../chaty_V2/.venv/bin/python -m pytest backend/tests -q -p no:cacheprovider --timeout=120`
  (snapshot baseline: 1514 passed, 3 failed, 9 skipped; pre-existing failures:
  `test_analyze_conversation_log::test_chip_questions_match_widget_suggestions`,
  `test_deployment_doc::test_gateway_readme_documents_the_probe_and_the_dry_run`,
  `test_redaction_linear_time::test_pathological_html_redacts_under_200ms`).
  Frontend: `cd frontend && npx jest` (node_modules symlinked from the main checkout).

---

## 10. Amendments v2.1 (after the first build, 2026-10-02)

These override the sections they name.

1. **2.7 navigate bridge.** Besides bare manifest paths and the typed record links, the bridge accepts
   exactly one view selector `valg=<word>` or `sektion=<word>` (`^[a-z0-9_-]{1,40}$`), because 36 of
   the 83 chatbot manifest screens use them (e.g. `debitor/ordreliste.php?valg=ordrer`). Bare paths
   are refused when they match `logud`, `slet`, `delete`, `luk.php`, or start with `admin/`.
2. **0 / 4.3 cancel.** No agent can approve, reject or undo. An agent may withdraw (`cancel`) an open
   proposal it created under the same grant, and a rule principal may cancel its own open
   `routine.finding` (`created_by` = ctx principal); both are logged `cancelled`.
3. **2.2 CONFLICT reasons.** Every CONFLICT carries `detail.reason`, one of `tenant_changed`,
   `expired`, `not_open`, `digest_mismatch`, `journal_in_use`, `journal_unsaved`, `undo_changed`,
   `modal_open`. UIs key on the reason, never on the English message. `CANCELLED` (user closed the
   Saldi modal) is a UI-only outcome of `decideInboxItem`, not a server error code.
4. **4.3 item shape** adds `decision_note` and `decided_by_name` for decided items.
5. **4.4 titles.** `memory.fact` title = "Husk: <fact, max 80 chars>", `memory.retract` title =
   "Glem: <fact, max 80 chars>"; journal titles use singular/plural texts ("1 kladdelinje: …",
   "<n> kladdelinjer: …"). The kind label stays in `kind_label`. Applying a journal draft never
   writes `kladdeliste.hvem`/`tidspkt`, also not when it creates the kladde.
6. **4.4 routine.finding** keeps `total.converted` (boolean) and puts each row's `screen` into the
   card's `evidence` (label = row label).
7. **5.3** every money-bearing row/object also carries `currency` (and `converted` when a base-currency
   total is computed); balance/ledger/trial-balance data carry `revenue_hidden`. These additions are
   part of the exported contract.
8. **5.3 taint.** All four draft tools accept `tainted?: bool`; it is stored in `evidence.tainted` and
   rendered as the tainted notice.
9. **7.1 action.php** ops: `preview`, `approve`, `reject`, `undo`, `cancel`, `count`. Every response
   that returns an item also returns `html` (server-rendered card) and, for decisions, `receipt`
   `{text, screen, screen_label}`. `tenant_hash` is checked on every op. A missing or wrong CSRF token
   returns 403 `PERMISSION_DENIED` with `detail.reason = 'csrf'`.
10. **7.2** `getMcpToken({refresh: true})` bypasses the cache; `{enabled:false}` is cached 60 s;
    the result is `{enabled, token, expiresAt, installId, tenantHash, userHash}` or `{enabled:false}`
    or null. `decideInboxItem(itemId, decision, tenantHash?)`: reject also fetches the digest by
    preview first (no modal); closing the modal resolves `{ok:false, error:{code:'CANCELLED'}}`.
11. **8.3/8.4 streaming.** The gateway checks the served model on the first streamed chunk that names
    it; text is then emitted sentence by sentence (never buffered for the whole step). A violation
    detected later still discards the step and ends the turn with an error event.
12. **8.5** the widget prefers `receipt.text` from the host when present; `saldi:mcp` may carry
    `{disabled:true}` or `{unavailable:true}`; `saldi:mcp:request` may carry `{refresh:true}`.
13. **4.4 journal balance.** One voucher per item means a draft must balance: after the per-line checks,
    `validate()` sums the one-sided lines in integer øre from the normalised `amount` (debit-only adds,
    credit-only subtracts, two-sided lines add nothing) and throws VALIDATION_ERROR with `detail =
    {reason: 'unbalanced', difference: '<-?x.xx>', fields: ['$.lines']}` unless the sum is 0. decide()
    re-runs `validate()`, so approval is covered. The `propose_journal_entry` description and its `lines`
    input description say so.
14. **4.4 apply step 4** also writes `betal_id = ''` on every inserted row, as `finans/kassekladde.php`
    does when it saves, so an unchanged "Gem" keeps `rows_sha256` and Undo stays available.
15. **4.2 / 4.3 create ordering (idempotency before validation).** After the proposer and permission
    checks and the `request_id` format check, a given `request_id` is looked up by `request_key` first.
    Existing row: invisible -> CONFLICT `not_open`; another kind -> VALIDATION_ERROR
    `'request_id already used for a different proposal'` with `detail = {reason: 'request_id_reused',
    fields: ['$.request_id']}`; then `validate()` runs: if it throws, the existing item is returned
    (`deduplicated: true`, any status) because a decided request may no longer validate (memory
    retracted, kladde posted, period closed); if the new `content_digest` differs -> the same reuse error;
    else the existing item is returned. New requests validate as before. A concurrent insert that loses
    on `request_key` applies the same kind/digest check before returning the winner.
16. **5.3 draft receipts** follow the returned item: `status` open -> `state: 'awaiting_approval'`;
    applied -> `'applied'` ("Forslaget er allerede godkendt."); rejected/cancelled/expired/undone ->
    `'closed'` naming the status. Every draft receipt carries `item_id`, `status` and `deduplicated`.
    Receipt schema: `state` enum `done|awaiting_approval|applied|closed`, plus `status` (string) and
    `deduplicated` (boolean).
17. **5.3 invoice `paid`** is computed in SQL like `debitor/ordreliste.php`: status >= 3 and either an
    `openpost` row with `faktnr` = fakturanr (text cast), the same `konto_id`, `udlignet = '1'` and
    `|ROUND(amount,2)| = ROUND(sum+moms,2)` (either sign, so debtor and creditor invoices), or a
    non-zero `ordrer.betalt` mark ('1', 'on', a paid amount; '', '-' and zero-like values are unpaid).
18. **5.3 `get_invoice.due_date`**: `ordrer.due_date`; else the posted `openpost.forfaldsdate` for the
    invoice (`faktnr` = fakturanr, same `konto_id`, matching amount); else a port of
    `includes/forfaldsdag.php` over `betalingsbet`/`betalingsdage`: kontant and efterkrav -> invoice date;
    netto -> invoice date + days; any other term (e.g. "Lb. md.") -> month end + days, rolling over
    month ends. NULL without an invoice date.
19. **5.3 / 6.3 base-currency conversion of open items** is one helper,
    `SaldiToolRuntime::openpostBaseSql(base)`, used by `list_open_items`, `get_party` and the overdue
    debtors routine (port of `includes/reportFunc/showOpenPosts.php`): valuta '', the base currency and
    '-' are base currency; a rate of 0 or NULL counts as 100; a foreign row at 100 takes `valuta.kurs` of
    its `grupper` `art='VK'`/`box1=valuta` group with `valdate <= transdate` (latest). A foreign row
    whose rate cannot be resolved gets `amount_base: null`, `rate_unavailable: true`,
    `converted: false`, sorts last and is left out of every base sum; grouped rows, `totals` and
    `get_party` add `rate_unavailable_items` (integer) and `converted` is false when it is non-zero. The
    routine leaves such items out of its count, rows and total and sets `total.converted` false.
20. **5.3 `list_open_items` order** is "largest balance first": amount descending for debtors and
    ascending for creditors (the credit side is posted negative), ties by id. The description adds:
    "For creditors, amounts we owe are negative; positive amounts are prepayments or credit notes."
21. **2.5 schema steps repair themselves.** When a step's table already exists with its signature
    column, the step still creates any of its indexes that are missing (`pg_indexes` /
    `information_schema.statistics`). The login path (`includes/planUpdates.php`) runs each step inside
    `BEGIN`/`COMMIT` on PostgreSQL (ROLLBACK and rethrow on failure); the runtime path already runs all
    steps in one transaction.
22. **6.4 exhausted leases.** Before claiming, `UPDATE automation_jobs SET status='failed',
    last_error='lease expired', finished_at=now, lease_token=NULL, locked_until=NULL WHERE
    status='running' AND locked_until < now AND attempts >= max_attempts`; the claim select only
    reclaims an expired `running` job when `attempts < max_attempts`.
23. **5.5 / 7.1 / 7.3 request origin.** One helper, `saldi_runtime_request_origin($server)` in
    `includes/runtime/bootstrap.php` (with `saldi_runtime_origin($url)`), is the request's own origin for
    the token endpoint, `inbox/action.php` and the MCP URL on the settings page: https when `HTTPS` is
    on or the first `X-Forwarded-Proto` value is `https`. The header is trusted without a proxy setting
    because it only ever upgrades the scheme that is compared with the browser's `Origin`, which a
    cross-site page cannot choose.
24. **7.1 paging.** View state adds `offset` (0..999999). `SaldiInboxService::list()` accepts `status`
    as one status or a non-empty list (`status IN (...)`), so a tab is one newest-first query with
    `limit`/`offset`; a one-row probe at `offset + limit` decides whether "Vis flere" is shown. "Vis
    flere" grows `limit` by 25 up to 100, then moves `offset` on by 100; "Vis nyeste" (text 5758)
    returns to the first page. Tab links and the kind filter reset the offset. The helpers
    (`SALDI_INBOX_TABS`, `saldi_inbox_view_state`, `saldi_inbox_view_query`, `saldi_inbox_next_page`,
    `saldi_inbox_page_items`) live in the `inbox/action.php` library.
25. **7.3 one-time key flash** (`includes/automation/assistTokenFlash.php`) stores `token`, `grant_id`,
    the issuing `db`, `user_id` and company name. The next GET always consumes it and shows the key only
    when `db` and `bruger_id` match; otherwise it shows a warning (text 5756) to revoke that grant in the
    named company. The secret panel names the company (text 5757).
26. **5.4 `mcp/.htaccess` is tracked**: `.gitignore` has `!mcp/.htaccess` after its `.ht*` and
    `.htaccess` rules.
