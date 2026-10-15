# Saldi Assist as a work companion: build spec (companion v1)

Extends [SPEC.md](SPEC.md) (v2 + section 10 amendments), which stays binding: conventions (§1), runtime
spine (§2), permissions (§3), inbox/kinds/decision log (§4), tool registry + MCP (§5), worker (§6), UI (§7),
chaty runtime (§8). This file only adds. Where it changes an earlier rule it says "amends §x".
Branch: `ai/mcp-assistant` in both repos. Date: 2026-10-02.

Why these slices: the measured bottleneck is the kassekladde (16 of 25 bookkeeping hours/day across all
installs, ~2.8 account lookups per save, median 10 s between saves; see
`doc/strategy/research/ai-time-savings-estimate-2026-09-24.md` and the 2026-10-01 flow measurement).
So the companion lives inside the journal first, then widens to documents, more draft kinds, procedures,
watch, reach and a feedback loop.

---

## C0. Scope, invariants, order

In scope (slice ids used by builders and tests):

| id | slice | repo |
|---|---|---|
| K1 | Posting rules: table, kinds, tools, settings section | Saldi |
| K2 | Posting suggester (rules + history + duplicate check), endpoint, MCP tool, rule-candidate routine | Saldi |
| K3 | Kassekladde inline suggestions UI + "Spørg Assist" row bridge | Saldi |
| K4 | Documents: read tools; journal drafts from a bilag; supplier invoice + payment registration drafts | Saldi |
| K5 | New kinds: `party.create`, `purchase_order.draft`, `reminder.draft` | Saldi |
| K6 | Watch v2: attention budget, daily overview, hourly scheduling, four new routines, VAT/period tools | Saldi |
| K7 | Procedure packs ("skills") + tools | Saldi |
| K8 | Reach: daily digest mail, `support.report` kind + delivery job | Saldi |
| K9 | Assist report page (accept rates, suggestions, tools, routines) | Saldi |
| K10 | chaty: page focus + record context, contract v6, honesty verbs, escalation in v2, Jira SST destination, support intake, quality loop (feedback→eval candidates, audit verifier), long-session Prepare fix, usage/latency traces, EU route config + release gate | chaty |
| K11 | Widget: `saldi:ask` prefill + focus chip, feedback + support for v2, overview chip; loader relay | chaty |

Invariants (unchanged from SPEC §0, restated because new kinds touch master data):
- Forbidden tier stays forbidden: no pay, post, send, file VAT, lock, delete posted/master records, change
  bank details, change rights. New kinds never write a bank field, never send mail to a third party, never
  post. `reminder.draft` creates the reminder document only; printing/sending/posting stays in Saldi.
- Agents never approve. Every new kind is approved in a Saldi-rendered control.
- Undo of a newly created record (party, purchase order, reminder) is allowed only while nothing references
  it and its row is unchanged (same exception as the journal-draft undo, SPEC §0).
- No model call from a Saldi page request or the worker. The suggester, routines, overview and digest are
  SQL/PHP rules.
- Kassekladde suggestions never fill a field without a click and never save. The user presses Gem.

Out of scope here (recorded, not built): mobile approvals (the inbox JSON ops in `inbox/action.php` are the
contract; the adapter belongs on `feat/saldi-mobile`, see C9), bookkeeper multi-client view, the open MCP
surface for outside agents beyond the existing personal grants.

---

## C1. Shared foundation (one builder, before the others)

### C1.1 Schema steps (append to `includes/planUpdatesSteps.php`, after `s90_*`)

Same rules as SPEC §2.5 (+ amendment 21). Signature columns in parentheses.

```sql
-- s91_posting_rules (signature: match_type)
CREATE TABLE posting_rules (
  id serial PRIMARY KEY,
  status varchar(16) NOT NULL DEFAULT 'active',   -- active | retired
  match_type varchar(16) NOT NULL,                -- supplier | customer | text
  match_value varchar(100) NOT NULL,              -- party kontonr | normalised text (C3.2 normalise())
  account varchar(15) NOT NULL,                   -- finance account (kontoplan.kontonr)
  vat_code varchar(8) NOT NULL DEFAULT '',        -- '' = the account's own code, 'none' = momsfri, else e.g. 'K1'
  description varchar(100),                       -- optional text to put in the line
  note varchar(255),                              -- why, from the proposal
  inbox_item_id integer,
  created_by varchar(96) NOT NULL,
  approved_by integer,
  created_at timestamp NOT NULL,
  retired_at timestamp,
  retired_by integer,
  retire_reason varchar(255)
);
CREATE INDEX posting_rules_match_idx ON posting_rules (status, match_type, match_value);

-- s91_assist_events (signature: event)
CREATE TABLE assist_events (
  id serial PRIMARY KEY,
  created_at timestamp NOT NULL,
  user_id integer,
  event varchar(32) NOT NULL,   -- suggestion_shown | suggestion_used | suggestion_dismissed | duplicate_warned |
                                -- overview_viewed | interrupt_shown | ask_from_row | digest_sent | skill_opened
  subject varchar(64),          -- e.g. 'kladde:14', 'rule:3', 'item:12'
  source varchar(16),           -- rule | history | null
  detail text                   -- JSON, small; never secrets
);
CREATE INDEX assist_events_event_idx ON assist_events (event, created_at);

-- s91_automation_state (signature: state_key)
CREATE TABLE automation_state (
  state_key varchar(128) PRIMARY KEY,   -- e.g. 'supplier_bank:<adresser.id>'
  state_value text NOT NULL,
  updated_at timestamp NOT NULL
);
```
MySQL variants per SPEC §2.5. Step names in order: `s91_posting_rules`, `s91_assist_events`,
`s91_automation_state`.

### C1.2 Permissions (amends SPEC §3.1)

| permission | rule |
|---|---|
| `debtors.draft` | write on pos 6 |
| `creditors.draft` | write on pos 8 |
| `purchases.draft` | write on pos 7 |
| `documents.read` | read on pos 2, 7 or 8 |
| `support.draft` | baseline |
| `rules.read` | = `journal.read` rule (read on pos 2) |

Agent scope `draft` (with `read`) adds, besides the v1 three: `debtors.draft`, `creditors.draft`,
`purchases.draft`, `support.draft`. Scope `read` adds every `*.read` including `documents.read`,
`rules.read`. `rule:<name>` contexts may additionally hold `journal.draft` when the trusted routine
registry says so (C4.5 `posting_rule_candidates`).

### C1.3 Kind registry (amends SPEC §4.2/4.3)

`SaldiKindRegistry::default()` registers, after the five v1 kinds: `rule.posting`, `rule.retire`,
`party.create`, `purchase_order.draft`, `reminder.draft`, `support.report`.

Amends SPEC §4.3 create rule 1: `routine.finding` only from `rule:*` principals; a `rule:*` principal may
also create `rule.posting` (and nothing else). Every other kind never from rule principals.

### C1.4 Citations and screens (amends SPEC §5.3, §2.7)

New citation ids: `saldi:rule:<id>`, `saldi:document:<documents.id>`, `saldi:pool_file:<pool_files.id>`,
`saldi:order:<ordrer.id>` (purchase orders and reminders), `saldi:skill:<n>` is NOT a citation (skills are
reference text; cite docs instead). `SaldiDraftTools::resolve()` accepts the new types with the same
visibility rules as their read tools.

`SaldiScreens::SCREENS` adds: `'document_pool' => ['finans/pulje_review.php', null]` (builder: verify this is
the page users open for the pool; if the pool is opened through another page, use that and note it in the
file history), `'reminders' => ['debitor/rykker.php', null]` (verify the reminder overview page),
`'reminder' => ['debitor/rykker.php', 'rykker_id']` only if that page accepts an id parameter (else omit),
`'assist_settings' => ['systemdata/assistent.php', null]`, `'assist_report' => ['systemdata/assistent_rapport.php', null]`,
`'vat_report' => ['finans/rapport.php', null]` (verify), `'vat_periods' => ['finans/moms_periode.php', null]`.
Mirror additions in `javascript/saldi-assist-navigate.js`.

### C1.5 Text ids (amends SPEC §1): block 5900-6099

Rows below are fixed (Danish copy is part of the spec; English/Norsk columns: translate plainly). Builders
may add rows only inside their own sub-range (last column) and must list them in their report.

| id | Dansk |
|---|---|
| 5900 | Bogføringsregel |
| 5901 | Stop bogføringsregel |
| 5902 | Ny kunde |
| 5903 | Ny leverandør |
| 5904 | Ordreforslag til leverandør |
| 5905 | Rykker |
| 5906 | Supporthenvendelse |
| 5910 | Når du godkender, bruges reglen til forslag i kassekladden. Intet bogføres, og du vælger selv, om du bruger et forslag. |
| 5911 | Når du godkender, holder reglen op med at give forslag. Tidligere posteringer ændres ikke. |
| 5912 | Når du godkender, oprettes kontoen i Saldi uden bankoplysninger. Du kan rette kortet bagefter. |
| 5913 | Når du godkender, oprettes et ordreforslag hos leverandøren. Intet sendes eller bestilles: det gør du selv fra ordren. |
| 5914 | Når du godkender, oprettes rykkeren i Saldi. Intet sendes eller bogføres: du udskriver eller sender den selv fra rykkeroversigten. |
| 5915 | Når du godkender, sendes præcis denne beskrivelse til Saldi-support. Intet andet fra dit regnskab sendes med. |
| 5916 | Send til support |
| 5917 | Opret |
| 5920 | Når |
| 5921 | Konto |
| 5922 | Moms |
| 5923 | Tekst på linjen |
| 5924 | Felt |
| 5925 | Værdi |
| 5926 | Varenr. |
| 5927 | Antal |
| 5928 | Pris |
| 5929 | Faktura |
| 5930 | Forfald |
| 5931 | Leverandør %s |
| 5932 | Kunde %s |
| 5933 | Teksten indeholder »%s« |
| 5934 | Kontoens momskode |
| 5935 | Momsfri |
| 5936 | Erstatter regel %s |
| 5940 | Bilag mangler før momsfristen |
| 5941 | Leverandørers bankoplysninger er ændret |
| 5942 | Batcher tæt på udløbsdato |
| 5943 | Kassedifference ved kasseopgørelse |
| 5944 | Gentagne posteringer, der kan blive til regler |
| 5950 | Dagens overblik |
| 5951 | Åbne opgaver |
| 5952 | Forfaldne debitorposter |
| 5953 | Kassekladder, der ikke er bogført |
| 5954 | Momsfrist |
| 5955 | Bilag i puljen |
| 5956 | Varer under minimum |
| 5957 | Intet kræver din opmærksomhed i dag. |
| 5958 | Vigtigt |
| 5959 | Vis alle opgaver |
| 5960 | Forslag |
| 5961 | Brug |
| 5962 | Skjul |
| 5963 | Fra regel |
| 5964 | Brugt %d gange, senest %s |
| 5965 | Mulig dublet: |
| 5966 | Spørg Assist |
| 5967 | Linjen er udfyldt, men ikke gemt. Tryk Gem, når den er rigtig. |
| 5968 | Fristen er beregnet ud fra momsperioden i Assist-indstillinger. Tjek altid skat.dk. |
| 5969 | dage |
| 5970 | Bogføringsregler |
| 5971 | Aktive regler |
| 5972 | Stop regel |
| 5973 | Forslag i kassekladden |
| 5974 | Dagligt overblik på mail |
| 5975 | Momsperiode |
| 5976 | Måned |
| 5977 | Kvartal |
| 5978 | Halvår |
| 5979 | Højst antal vigtige beskeder pr. dag |
| 5980 | Grænse for kassedifference (kr.) |
| 5981 | Mailadresse til overblikket |
| 5982 | Ingen aktive regler. Assist foreslår regler, når den ser de samme posteringer flere gange. |
| 5990 | Assist-rapport |
| 5991 | Forslag |
| 5992 | Godkendt |
| 5993 | Afvist |
| 5994 | Udløbet |
| 5995 | Fortrudt |
| 5996 | Godkendt andel |
| 5997 | Median tid til beslutning |
| 5998 | Værktøjskald |
| 5999 | Fejl |
| 6000 | Forslag i kassekladden |
| 6001 | Vist |
| 6002 | Brugt |
| 6003 | Anslået sparet tid |
| 6004 | Anslået ud fra antagelserne herunder, ikke målt. |
| 6005 | Periode |
| 6006 | Sidste 30 dage |
| 6007 | Sidste 90 dage |
| 6010 | Saldi: dagens overblik for %s |
| 6011 | Du har %d åbne opgaver i Saldi. |
| 6012 | Åbn Saldi |
| 6013 | Du får denne mail, fordi du har slået dagligt overblik til under Saldi Assist. Du slår det fra samme sted. |
| 6014 | Spørg Assist om denne linje |

Builder sub-ranges for extra rows: K1/K2 6020-6029, K4 6030-6039, K5 6040-6049, K6 6050-6059,
K7 6060-6064, K8 6065-6069, K9 6070-6079, K3 UI 6080-6089, spare 6090-6099.

### C1.6 Tool catalogue additions (amends SPEC §5.3)

All follow SPEC §5.1/5.2 (6-slot description, exhaustive data allowlists, envelope, 200 KiB cap,
`possibly_incomplete`). New `$metadata` rows in `SaldiToolRegistry::default()` and schemas in
`SaldiToolSchemas::catalogue()`; handlers live in the slice classes named in the last column. The foundation
builder creates each slice class with method stubs that throw `SaldiRuntimeError('UNAVAILABLE', 'Not built')`
so the registry, contract export and schema tests are complete from the start; slice builders replace the
bodies.

| tool | discovery | effect | input (beyond pagination) | data | class::method |
|---|---|---|---|---|---|
| `list_posting_rules` | rules.read | read | `status?` active\|retired\|all (active) | rows `{rule_id, match: {type, value, label}, account, account_name, vat_code, description, note, created_at, approved_by_name, uses_90d}` | `SaldiRuleTools::listRules` |
| `suggest_posting` | journal.read | read | `description` (1..100), `amount?` (decimal string), `date?`, `party?` `{ledger: customer\|supplier, account}`, `kladde_id?` | `{suggestions: [<= 3 x C2.3 suggestion]}`, `duplicates: [<= 5 x C2.4]` | `SaldiRuleTools::suggest` |
| `propose_posting_rule` | journal.draft | draft | `match: {type: supplier\|customer\|text, value}`, `account`, `vat_code?`, `description?`, + `explanation`, `citations?`, `request_id`, `tainted?` | item | `SaldiDraftTools::propose('rule.posting')` |
| `propose_rule_retirement` | journal.draft | draft | `rule_id`, `reason?`, + common draft fields | item | `SaldiDraftTools::propose('rule.retire')` |
| `list_documents` | documents.read | read | `status?` pool\|attached\|all (pool), `query?` (filename, vendor, invoice number), `from?`, `to?` | rows C3.1 | `SaldiDocumentTools::listDocuments` |
| `get_document` | documents.read | read | `ref` (`pool:<id>` \| `doc:<id>`) | C3.1 + `suggestion` | `SaldiDocumentTools::getDocument` |
| `propose_supplier_invoice` | journal.draft | draft | C3.3 | item (`journal.draft_entry`) | `SaldiDocumentTools::proposeSupplierInvoice` |
| `propose_payment_registration` | journal.draft | draft | C3.4 | item (`journal.draft_entry`) | `SaldiDocumentTools::proposePaymentRegistration` |
| `propose_party` | debtors.draft, creditors.draft | draft | C4.1 input + common draft fields | item | `SaldiDraftTools::propose('party.create')` |
| `propose_purchase_order` | purchases.draft | draft | C4.2 input + common | item | `SaldiDraftTools::propose('purchase_order.draft')` |
| `propose_reminder` | debtors.draft | draft | C4.3 input + common | item | `SaldiDraftTools::propose('reminder.draft')` |
| `get_daily_overview` | company.read | read | `{}` | C5.2 | `SaldiWatchTools::overview` |
| `get_vat_summary` | ledger.read | read | `from`, `to` (required; inside one fiscal year) | C5.6 | `SaldiWatchTools::vatSummary` |
| `get_period_status` | journal.read | read | `from`, `to` (required) | C5.7 | `SaldiWatchTools::periodStatus` |
| `list_procedures` | company.read | read | `{}` | rows `{slug, title, description, version}` | `SaldiSkillTools::listSkills` |
| `get_procedure` | company.read | read | `slug` (`^[a-z0-9-]{3,40}$`) | `{slug, title, version, body, steps: [{id, title, check_tool?, check_args?, screen?, doc?}]}` | `SaldiSkillTools::getSkill` |
| `report_problem` | support.draft | draft | `summary` (10..200), `details` (<= 2000), `screen?`, `category` bug\|question, + common | item | `SaldiDraftTools::propose('support.report')` |

"common draft fields" = `explanation` (required, <= 500), `citations?` (<= 10), `request_id` (required,
<= 64), `tainted?` (bool), exactly as `propose_journal_entry`. Tool notes (appended to WHAT, like the v1
`$notes`):
- `suggest_posting`: "Suggestions come from approved posting rules first, then from how similar lines were
  booked before. They are hints for the user, never bookings. duplicates lists earlier lines with the same
  party and amount, or the same invoice number."
- `propose_payment_registration`: "Records a payment that has already happened (seen on the bank
  statement) as a journal line. It does not pay anything."
- `propose_supplier_invoice`: "Builds journal lines from a supplier invoice. Use get_document first when a
  document ref is known. The document is attached to the first line when approved."
- `propose_purchase_order`: "Creates an order proposal (status forslag). Nothing is sent to the supplier."
- `propose_reminder`: "Creates a reminder (rykker) document for chosen overdue invoices. Nothing is sent or
  posted; the user prints or sends it in Saldi."
- `get_procedure`: "Step-by-step procedures written by Danosoft. Run each step's check_tool with check_args
  to see the user's actual state, then say which steps are done and which remain."

Total after this spec: 37 tools. The contract export (SPEC §5.6) includes them and the six new kinds.

---

## C2. K1 + K2: posting rules and the suggester

### C2.1 `rule.posting` (`includes/automation/kinds/SaldiPostingRuleKind.php`)

- actionClass `persistent`; propose `journal.draft`; decidePermission `journal.draft`; visibility
  `permission`, `assignee_permission = 'journal.draft'`, owner = proposing human or null for rule principals.
  (A rule only produces suggestions a person must click, so any journal writer may approve it.)
- Input `{match: {type, value}, account, vat_code?, description?, replaces_rule_id?}`.
  - `supplier`/`customer`: value = an open `adresser.kontonr` of art K/D (numeric) -> stored as is.
  - `text`: value 3..60 chars after `SaldiPostingSuggester::normalise()`; must contain a letter.
  - `account`: open finance account (`kontotype IN ('D','S')`, not lukket) in the context fiscal year.
  - `vat_code`: '' | 'none' | a code that exists as `grupper` `art = <letter>.'M'` / `kodenr = <digits>` for the
    fiscal year (e.g. `K1`, `S1`, `E1`, `Y1`).
  - An active rule with the same (type, value) exists -> payload gets `replaces_rule_id` = that id
    (validate sets it; input value is ignored unless it equals the found id).
  - `dedupe_key` = `rule:<type>:<sha1(value)>`. Title "Regel: <match label> -> <account> <name>" (max 120).
- Apply: insert `posting_rules` (approved_by = approver, created_by = item created_by, inbox_item_id); retire
  `replaces_rule_id` (retire_reason `replaced`). Receipt `{rule_id, replaced_rule_id}`.
- canUndo: the new rule is still active. Undo: retire it (reason `undo`) and re-activate the replaced rule if
  it was retired with reason `replaced` by this item.
- Card: columns 5920/5921/5922/5923 one row; fields: "Erstatter regel N" (5936) when replacing; next step 5910.

### C2.2 `rule.retire` (`SaldiPostingRuleRetireKind.php`)

`persistent`; propose/decide `journal.draft`; visibility as rule.posting. Input `{rule_id, reason?}`
(active rule). Apply: retire (reason from input or `retired`). canUndo false. Next step 5911.
Humans may also retire directly on the settings page (C8.3) through `SaldiPostingRules::retire()`, which
appends decision_log `rule_retired`.

### C2.3 `SaldiPostingSuggester` (`includes/automation/SaldiPostingSuggester.php`)

```php
final class SaldiPostingSuggester {
	public static function normalise(string $text): string; // lowercase (mb), strip digits, dates, amounts and
		// punctuation except letters/spaces, collapse spaces, trim; Danish letters kept
	public static function tokens(string $text): array;      // words >= 3 chars from normalise(), minus stopwords
		// (af, og, til, fra, for, med, den, det, der, som, faktura, fakt, nr, betaling, bet, the, and, inv)
	public function __construct(SaldiTenantContext $ctx, SaldiDb $db);
	public function suggest(array $line): array;  // {suggestions: [...], duplicates: [...]}
}
```
Input `$line`: `description`, `amount?`, `date?`, `party? {ledger, account}`, `kladde_id?`, `exclude_line_id?`.
Requires `journal.read` and `accounts.read` (else PERMISSION_DENIED). Only open finance accounts of the
context fiscal year can be suggested; when `revenueHidden`, suggestions containing a `kontotype 'D'` account
are dropped.

A suggestion:
```json
{"source": "rule|history", "rule_id": 3, "confidence": "high|medium|low",
 "debit":  {"ledger": "account|customer|supplier", "account": "2800", "name": "Software", "vat_code": "K1"},
 "credit": {"ledger": "supplier", "account": "1005", "name": "Adobe", "vat_code": ""},
 "vat_free": false, "description": "Adobe abonnement",
 "basis": {"count": 12, "last_date": "2026-09-03", "line_ids": [431, 402, 377]}}
```
`debit`/`credit` may be null when that side is unknown (e.g. a rule fixes only the expense side). `name` is
the account/party name. `vat_code` is what the kassekladde VAT select should show (`debetvat`/`kreditvat`
when stored, else the account's `kontoplan.moms`); `vat_free` mirrors `momsfri`.

Algorithm (deterministic, at most 3 suggestions, then duplicates):
1. **Party rule.** If `party` given and an active rule `match_type = party.ledger`, `match_value =
   party.account` exists: suggestion with the rule's account on the side opposite to the party (supplier
   party on credit -> rule account on debit; customer party on debit -> rule account on credit; when the
   caller does not say which side the party is on, use credit for suppliers and debit for customers), vat
   from the rule ('' -> account's moms, 'none' -> vat_free true), description = rule description or input.
   confidence `high`, `basis.count` = rule uses in 90 days (count `assist_events` `suggestion_used` with
   subject `rule:<id>`).
2. **Text rules.** Active `text` rules whose `match_value` is contained in `normalise(description)` as whole
   words; longest value wins; same shape; the other side is filled from history step 3 when history has a
   dominant counter side for that account (>= 60 % of matches), else null. confidence `high`.
3. **History.** Candidates: `kassekladde k JOIN kladdeliste l ON l.id = k.kladde_id` with `k.transdate >=
   today - 730 days`, `k.amount > 0`, at least one side set, `k.id <> exclude_line_id`, and
   `LOWER(k.beskrivelse) LIKE ?` OR-ed across all significant input tokens, newest first, `LIMIT 400`.
   A zero-hit suffix must never suppress a token with history. When `party` is
   given, additionally require that party on the same ledger side, and fall back to party-only candidates
   (any description) when the text probe yields fewer than 3 rows. Score each row by Jaccard similarity of
   token sets, with a minimum score of 0.8 when all history tokens occur in the input; keep rows >= 0.5
   (party-only fallback rows count as 0.5). Group by pattern
   `(d_type, debet, debetvat, k_type, kredit, kreditvat, momsfri)`; pattern weight = sum of scores x
   recency factor (1.0 within 180 days, 0.6 older). Top 3 patterns. confidence: `high` if count >= 3 and
   share of total weight >= 0.7; `medium` if count >= 2; else `low`. Skip a pattern equal to a rule
   suggestion already returned. `description` = the most frequent original `beskrivelse` of the pattern
   (trimmed to 100). `debetvat`/`kreditvat` read only when `hasColumn`.
4. **Duplicates** (`C2.4`): up to 5 earlier lines (open or posted kladder) within 60 days before/after
   `date` (today when absent) where either (a) the same party (ledger + account) and the same amount
   (`ROUND(amount,2)`), or (b) `faktura` equal (non-empty, case-insensitive) and the same party. Exclude
   `exclude_line_id`. Row: `{line_id, kladde_id, bilag, date, amount, description, posted: bool, screen}`.
Performance: the whole call must stay under 150 ms on a tenant with 50k kassekladde rows (index-free
probes are acceptable at that size; measure with plan_demo and report).

### C2.4 `SaldiPostingRules` (`includes/automation/SaldiPostingRules.php`)

`active(SaldiDb): list`, `get(int)`, `insert(...)`, `retire(SaldiDb, SaldiTenantContext, int $id, string
$reason)` (human with `journal.draft`; decision_log `rule_retired`, idempotency `rule:<id>:retire`),
`label(array $rule, SaldiText...)`.

### C2.5 Suggestion endpoint (`finans/kassekladde_includes/assistSuggest.php`)

- POST JSON only; same-origin check via `saldi_runtime_request_origin`; legacy session (`online` row, like
  `inbox/action.php`); `bruger_id > 0`; CSRF header `X-Saldi-Csrf` = `saldi_inbox_csrf_token()`.
- Tenant switch `assistant/journal_suggestions` must be `on` (default `off`; dev tenants on), else
  `{ok: true, enabled: false}`.
- Ops:
  - `suggest` `{kladde_id, line: {description, amount?, date?, party?, exclude_line_id?}}` -> `{ok, suggestions,
    duplicates}` (the suggester with the human's own context; never logs the description).
  - `event` `{event: suggestion_shown|suggestion_used|suggestion_dismissed|duplicate_warned|ask_from_row,
    subject, source?}` -> appends one `assist_events` row; `{ok}`. Max 120 events per session per hour
    (silently dropped beyond).
- Errors: `{ok: false, error: {code, message_da}}` with the SPEC §2.2 codes.

### C2.6 Rule candidates routine (`routines/SaldiPostingRuleCandidatesRoutine.php`)

Name `posting_rule_candidates`; permission `journal.draft`; title 5944; schedule weekly (Mondays, C5.3).
Unlike other routines it creates up to 5 `rule.posting` proposals (origin `rule`, attention `ambient`,
expires 14 days) instead of a finding: supplier/customer parties with >= 4 lines in the last 120 days whose
counter finance account is the same in >= 80 % of them, no active rule for that party, and no open or
rejected-in-the-last-90-days `rule.posting` item with the same dedupe key. Explanation (fixed Danish, filled
by code): "%d af %d linjer med %s er bogført på %s siden %s." Returns null (honest empty run) when none.

---

## C3. K4: documents, supplier invoices, payment registration

### C3.1 Document rows (`includes/tools/SaldiDocumentTools.php`)

Row: `{ref, filename, source: pool|journal|purchase_order|sales_order, attached_to: {type, id, label,
screen}|null, date, amount, currency, invoice_number, description, vendor: {name, cvr, match, supplier_account,
supplier_name}|null, extracted: bool, manually_edited: bool, created_at}`.
- Pool rows from `pool_files` (do not sync the folder; read the table). `ref = 'pool:<id>'`; `date` =
  `file_date` when it parses as a date; `amount` from `norm_amount` when not null, else `amount` when it
  parses as a decimal, else null; `extracted` = any of amount/date/invoice_number/vendor_name non-empty.
  `vendor.match` = `vendor_match`; `supplier_account` from `vendor_konto_id` -> `adresser.kontonr` (art K).
- Attached rows from `documents` (`source` kassekladde -> journal (attached_to = the kassekladde line's
  kladde, label "Kassekladde N, bilag B"), creditorOrder -> purchase_order, debitorOrdrer -> sales_order).
  `ref = 'doc:<id>'`. Sales-order documents require `sales.read` and revenue visibility; purchase-order
  documents `purchases.read`; journal documents `journal.read`.
- Never return file paths, file contents, IBAN or bank numbers (`vendor_iban` is not returned).
- Citations: `saldi:pool_file:<id>` (screen `document_pool`), `saldi:document:<id>` (screen of attached_to).
- `get_document` adds `suggestion`: for pool rows with a supplier match, `SaldiPostingSuggester::suggest(
  {description: description or vendor name, amount, date, party: {ledger: supplier, account}})` first
  suggestion or null.

### C3.2 Journal draft extension (amends SPEC §4.4 `journal.draft_entry`)

Input additions (all optional, validated, stored in the normalised payload, re-validated at approval):
- per line `invoice` (string 1..30, `[A-Za-z0-9 ._;/-]`): only on lines with a customer/supplier side ->
  `kassekladde.faktura`.
- per line `due_date` (date): only with a customer/supplier side -> `forfaldsdate` (default stays transdate).
- per line `vat_free` (bool) -> `momsfri = 'on'`; mutually exclusive with side `vat_code`.
- per side `vat_code` ('' | code as C2.1): requires `hasColumn('kassekladde','debetvat')`, else
  VALIDATION_ERROR `reason: vat_override_unsupported`. Written to `debetvat`/`kreditvat`.
- item `document_ref` (`pool:<id>`): the pool file must exist; on apply it is attached to the first inserted
  line (C3.5). At most one document per draft.
The tool `propose_journal_entry` input schema gains the same fields (in ledger words: `debit: {ledger,
account, vat_code?}`). The card adds a field "Bilag: <filename>" when `document_ref` is set and shows the
invoice number in the Tekst cell as "<description> (fakt. <invoice>)".

### C3.3 `propose_supplier_invoice`

Input: `supplier_account` (open K party), `invoice_number` (1..30), `date`, `due_date?`, `document_ref?`,
`lines` 1..10 x `{description, account (finance), amount (incl. VAT, decimal string), vat_code?}`,
`kladde_id?`, + common draft fields. Builds a `journal.draft_entry` with one two-sided line per input line:
debit `F account` (vat_code -> debit side), credit `K supplier`, `invoice` = invoice_number, `due_date`.
No separate total line (two-sided lines balance by themselves). Title is the kind's normal title.

### C3.4 `propose_payment_registration`

Input: `party: {ledger: customer|supplier, account}`, `amount`, `date`, `bank_account` (finance account),
`invoice_numbers?` (0..10 strings), `kladde_id?`, + common. One line: customer -> debit F bank, credit D
customer; supplier -> debit K supplier, credit F bank; `invoice` = invoice_numbers joined by `;` (each must be
an open item of that party: `openpost` `konto_id` = the party's adresser id, `faktnr` = number,
`udlignet <> '1'`; else VALIDATION_ERROR naming the number). When the amount differs from the sum of the
named open items, the draft is still created and `evidence.explanation` is kept; the card gets a notice
`{kind: 'info', text: 'Beløbet svarer ikke til de valgte fakturaer (%s).'}` (text id 6030).

### C3.5 `SaldiDocumentAttach` (`includes/automation/SaldiDocumentAttach.php`)

Port of `includes/docsIncludes/insertDoc.php` steps 3-5 for one existing kassekladde line, without any page
globals: resolve the document root exactly as `insertDoc.php`/`poolPaths.php` do (first existing of
`owncloud`, `bilag`, `documents` under the app root, then `/<db>`), move `pulje/<filename>` to
`finance/<kladde_id>/<line_id>/<filename>` (create dirs 0775), insert `documents` (`source='kassekladde'`,
`source_id` = line id, `filename`, `filepath` as insertDoc stores it, `timestamp` = unix seconds as string,
`user_id` = approver, `global_id` as insertDoc), delete the `pool_files` row. Return `{document_id, pool_row,
from, to}` (the deleted pool row is kept in the receipt for undo).
- Called last inside `apply()` (after every DB write), so a failed move rolls the DB back. If the file is
  missing -> CONFLICT `reason: document_missing` (nothing applied).
- `detach(receipt)` for undo: only when the documents row still exists unchanged and the file is at `to`;
  move it back, delete the documents row, re-insert the pool row (same columns, new id). Journal undo
  (SPEC §4.4) calls it before deleting the lines; if detach is impossible, canUndo is false.
- Known residual risk (document in code): the file move is not transactional; if the commit fails after the
  move the file sits in the finance folder without a documents row. Log it with `error_log`.

---

## C4. K5: new draft kinds

### C4.1 `party.create` (`SaldiPartyCreateKind.php`)

- actionClass `persistent`; propose/decide `debtors.draft` (customer) or `creditors.draft` (supplier);
  visibility `permission` with that permission as assignee; owner = proposer.
- Input `{ledger: customer|supplier, name (2..60), cvr? (8 digits, DK), ean? (13 digits), address? (<=60),
  zip? (<=10), city? (<=40), country? (<=40, default ''), email? (valid, <=60), phone? (<=15),
  contact? (<=60), payment_terms? {type: netto|lobende_maaned|kontant, days: 0..120}, group? (existing DG/KG
  kodenr)}`. No bank field exists in the schema; unknown fields -> VALIDATION_ERROR.
- Validation: duplicate guard: an open adresser row of the same art with the same CVR, or the same
  normalised name (lowercase, collapse spaces, strip "aps|a/s|i/s|ivs") -> VALIDATION_ERROR
  `reason: duplicate_party` with `detail.existing = {party_id, account}`. Group default = the lowest DG/KG
  kodenr (as the cards default). `dedupe_key` = `party:<ledger>:<cvr or sha1(normalised name)>`.
- Apply: port the inserts of `debitor/debkort_save.php` (customers; take `pg_advisory_lock(5130513)` like it
  does, plus `$db->lock('saldi_adresser_D')`) and `kreditor/kreditorkort.php` (suppliers; lock
  `saldi_adresser_K`): kontonr = first free number >= 1000 within the art (port the exact loop), mirror every
  default column those pages set on insert (builder: list them in the report). betalingsbet values as the
  cards store them (`Netto`, `Lb. md.`, `Kontant`; verify). Receipt `{party_id, account, row_sha256}`.
- canUndo: row unchanged (`row_sha256` over all columns) and no `openpost`, `ordrer`, `kassekladde`
  (`d_type/k_type` + account) or `transaktioner` reference. Undo deletes the row.
- Card: Felt/Værdi rows for every given field; title "Ny kunde: <name>" / "Ny leverandør: <name>"; next 5912;
  approve label 5917.

### C4.2 `purchase_order.draft` (`SaldiPurchaseOrderDraftKind.php`)

- `reversible_draft`; propose/decide `purchases.draft`; visibility `permission` (`purchases.draft`).
- Input `{supplier_account, lines: 1..50 x {product_id? | item_no? | description (1..100), quantity (decimal
  > 0, <= 3 decimals), unit_price? (decimal string)}, delivery_date?, reference? (<=40), note? (<=200)}`.
  Products must exist and not be closed; price default = the product's supplier price as
  `kreditor/ordre.php` picks it (`vare_lev`/`kostpris`; port the lookup), else `varer.kostpris`.
- Apply: port `kreditor/orderIncludes/insertAccount.php` (header: `art='KO'`, `status=0`, supplier fields
  copied from adresser, VAT from the KG/KM groups) and the line insert of `kreditor/ordre.php` (posnr,
  momssats, bogf_konto etc.). Order number: `LOCK TABLE ordrer` (PG) + MAX(ordrenr)+1 over art IN
  ('KO','KK') exactly like `get_next_order_number('KO')`. Never write `hvem`/`tidspkt`/`lock_token`. Receipt
  `{order_id, order_number, rows_sha256, screen}` (screen `purchase_order`).
- canUndo: status still 0, header + lines unchanged (`rows_sha256`), no documents/batch rows. Undo deletes
  lines and header.
- Card columns 5926/Tekst(5743)/5927/5928; fields Leverandør, Levering; next 5913.

### C4.3 `reminder.draft` (`SaldiReminderDraftKind.php`)

- `reversible_draft`; propose/decide `debtors.draft`; visibility `permission`.
- Input `{customer_account, open_item_ids: 1..30 (openpost.id of that customer, open, past due),
  level?: 1 (only level 1 in this version), note? (<=200)}`.
- Apply: port the R1 branch of `debitor/ny_rykker.php` for one customer and the given open posts, including
  its fee/interest lines from `formularer` (`beskrivelse='GEBYR'`, `formular=6`) and grace days
  (`grupper DIV kodenr=4 box5`): `ordrer` `art='R1'`, `status=2`, `betalt='-'`, ordrenr = MAX(ordrenr)+1 over
  `art` R1-R3 (under `LOCK TABLE ordrer`), fakturanr `"<ordrenr>-1"`, one comment line per open post
  (`enhed` = openpost.id, `serienr` = due date) exactly as the page. Never prints, mails or posts.
  Receipt `{order_id, reminder_number, rows_sha256, screen}`.
- validate: every open post is still open (`udlignet <> '1'`), belongs to the customer, is past due by at least
  the grace days, and is not already on an open reminder (`ordrelinjer.enhed` of an `R%` order with
  `betalt <> 'on'`) -> else VALIDATION_ERROR naming the id.
- canUndo: status still 2 (not posted), unchanged. Undo deletes lines + header.
- Card: columns Faktura (5929), Forfald (5930), Beløb (5746); fields Kunde, Gebyr; next 5914.

### C4.4 `support.report` (`SaldiSupportReportKind.php`)

- `read_only`; propose `support.draft`; decide `inbox.read` (owner only); visibility owner.
- Input `{category: bug|question, summary, details, screen?}`; strip control chars; reject secret-like text
  (same rule as memory facts).
- Apply: enqueue `automation_jobs` `support.deliver` (payload = item id + the exact approved payload +
  company name + Saldi version from `includes/version.php` read-only + install id; never ERP rows), receipt
  `{queued: true, job_id}`. Card: Felt/Værdi rows Kategori, Emne, Beskrivelse, Skærm; next 5915; approve
  label 5916.

---

## C5. K6: Watch v2

### C5.1 Attention and budget (amends SPEC §4.3 create and §7.1)

- `SaldiAttention::decide(SaldiDb, ?int $ownerUserId, ?string $assigneePermission, string $requested):
  string`. `interrupt` is granted only while fewer than `assistant/interrupt_budget` (tenant setting,
  default `3`, 0..10) interrupt items were created today (Europe/Copenhagen) for the same owner (or, for
  permission items, for the same assignee permission); otherwise `ambient` and `evidence.attention_downgraded
  = true`. `SaldiInboxService::create` calls it for every create with `attention = 'interrupt'`.
- Opgaver page: tab Åbne hides `silent` items (they show under a new tab "Stille" only when any exist);
  `interrupt` items sort first and carry the "Vigtigt" badge (5958).
- Shell: on load, if open `interrupt` items exist that the user has not seen (no `assist_events`
  `interrupt_shown` with subject `item:<id>` for this user), show one quiet banner under the top bar: "Vigtigt:
  <title>" + "Åbn" (opens the item) + close. Record `interrupt_shown` when displayed. At most one banner per
  page load; never modal; no sound.

### C5.2 Daily overview (`includes/automation/SaldiDailyOverview.php`)

`build(SaldiTenantContext $ctx, SaldiDb $db): array` (each section only when the permission holds; omitted
otherwise):
```json
{"date": "2026-10-02",
 "open_items": {"count": 4, "interrupts": [{"item_id": 9, "title": "...", "screen": "..."}],
                "by_kind": [{"kind": "journal.draft_entry", "label": "Kladdelinjer", "count": 2}]},
 "overdue_debtors": {"count": 7, "amount": "48210.00", "currency": "DKK", "converted": true},   // debtors.read
 "open_journals": {"count": 3, "oldest_date": "2026-08-12"},                                    // journal.read
 "vat": {"period": {"from": "2026-07-01", "to": "2026-09-30"}, "deadline": "2026-12-01",
         "days_left": 60, "missing_documents": 14, "note": "<text 5968>"},                    // journal.read
 "pool_documents": {"count": 3},                                                                // documents.read
 "low_stock": {"count": 5},                                                                     // products.read
 "nothing_to_do": false}
```
- Overdue: the overdue-debtors routine's rule (base currency, rate rules of amendment 19) without the 7-day
  grace; open journals: as the stale routine without the age limit; low stock: as the routine.
- VAT period from tenant setting `assistant/vat_period` (`month|quarter|half`, default `quarter`); the
  "current" period is the earliest period whose deadline is today or later and whose end is before today; if
  none (we are inside the first period), the running period. Deadlines (Danish rules as published by
  Skattestyrelsen; the UI shows note 5968): month -> the 25th of the following month, except the June period
  -> 17 August; quarter -> 1 June, 1 September, 1 December, 1 March for Q1..Q4; half -> 1 September (H1) and
  1 March (H2). If the deadline falls on a Saturday/Sunday, move to Monday (holidays ignored; the note says
  to check skat.dk).
- `missing_documents`: kassekladde lines with `transdate` in the VAT period, at least one F side whose
  account has a non-empty `kontoplan.moms`, no `documents` row (`source='kassekladde'`, `source_id` = line
  id) and an empty legacy `dokument`; lines of the same kladde+bilag share one document (count per
  kladde+bilag, attached when any line of the bilag has one).
- `nothing_to_do` = no interrupts, no overdue, no open journals older than 14 days, VAT days_left > 14 or no
  missing documents, no low stock.
- Rendered as a compact card at the top of Opgaver (title 5950; rows 5951-5956, each linking to its screen;
  5957 when nothing_to_do). `overview_viewed` event once per user per day.

### C5.3 Scheduling (amends SPEC §6.2)

`SaldiRoutine` gains `public function schedule(): string` (`daily` default in `SaldiBaseRoutine`, or
`hourly`, `weekly`). Dedupe keys: daily `routine:<name>:<YYYY-MM-DD>`; hourly
`routine:<name>:<YYYY-MM-DD>T<HH>`; weekly `routine:<name>:<ISO year>-W<ww>` enqueued on Mondays only.
Enabling switch per routine as before (`automation/routine.<name>`). The worker's skip test treats any
enabled routine as work.

### C5.4 New routines

| routine | schedule | permission | title | rule | attention |
|---|---|---|---|---|---|
| `vat_missing_documents` | daily | journal.read | 5940 | C5.2 `missing_documents` for the current VAT period; rows = up to 20 bilag `{label: "Kassekladde K, bilag B: <description>", amount, date, screen}`; null when 0 | `interrupt` when days_left <= 7, else `ambient` |
| `supplier_bank_change` | hourly | creditors.read | 5941 | for each open supplier: `sha256` of `bank_navn|bank_reg|bank_konto|bank_fi|swift|iban` vs `automation_state` `supplier_bank:<id>`; first sight stores a baseline and reports nothing; a change updates the state and reports the supplier: row `{label: "<firmanavn> (<kontonr>): bankoplysninger ændret", screen: creditor}`; never include the numbers; findings accumulate: the routine merges new rows into its open finding instead of cancelling it, and returns null only when it has no open finding and nothing changed (amends SPEC §6.3 for this routine) | `interrupt` |
| `expiring_batches` | daily | products.read | 5942 | only when setting `items/batchExpiryEnabled` is on: `batch_kob` with `rest > 0` and `due_date` within `lager/due_date_warning_days` (default 30) days (or past); rows `{label: "<varenr> <beskrivelse>, batch <no>", date: due_date, amount: null, screen: product}`, sorted by due date | `ambient` |
| `till_difference` | daily | ledger.read | 5943 | yesterday's (scheduled_date - 1) `report` rows `type='cashCount'`: per `report_number`, the "Difference" row (`description` starts with `Difference`; builder: confirm the exact text in `printBoxCount.php`) with `ABS(total) >= assistant/till_difference_threshold` (default 50); till number from the same report_number's head line ("Cash count, box N"); rows `{label: "Kasse N", amount, currency, date}` | `ambient` |
| `posting_rule_candidates` | weekly | journal.draft | 5944 | C2.6 | `ambient` |

### C5.5 Settings additions (tenant, admin)

`assistant/journal_suggestions` (off), `assistant/vat_period` (quarter), `assistant/interrupt_budget` (3),
`assistant/till_difference_threshold` (50), `automation/routine.<each new routine>` (off).
Per user (settings rows with `user_id` = brugere.id, var_grp `assistant`): `digest` (`off|daily`),
`digest_email` (defaults to `brugere.email` when empty).

### C5.6 `get_vat_summary` (`SaldiWatchTools::vatSummary`)

`{from, to, codes: [{code, kind: sales|purchase|eu_purchase|foreign_service, rate, vat_account,
vat_amount, currency}], sales_vat, purchase_vat, payable, currency, period_closed: bool,
revenue_hidden, note}`: sums `debet - kredit` from `transaktioner` per VAT account (`grupper` art
SM/KM/EM/YM `box1` for the fiscal year of `from`), signs as `finans/rapport_includes/momsangivelse.php`
(sales and E/Y accounts flipped), `payable` is the negated sum of every account in the `MR` box1..box2 range, rounding each
account independently as in that report's
"Afgiftsbeløb i alt" (port its arithmetic exactly; builder: show the ported lines in the report).
`period_closed` = every month of the range is closed in `moms_periode_luk`. `note` (fixed English):
"Computed from posted transactions only; unposted journals are not included."
Revenue hidden -> PERMISSION_DENIED (VAT reveals revenue).

### C5.7 `get_period_status` (`SaldiWatchTools::periodStatus`)

`{from, to, open_journals: [{journal_id, note, date, lines, screen}] (<= 20), unposted_lines,
lines_without_document, vat_months: [{month: "YYYY-MM", closed: bool}], last_posted_date}`.
- `open_journals`: kladder with `bogfort = '-'` that have at least one line dated in the range.
- `unposted_lines`: count of those lines in the range.
- `lines_without_document`: as C5.2 `missing_documents`, but for the range and every line (not only VAT
  accounts), counted per kladde+bilag.
- `vat_months`: each calendar month touched by the range, `closed` from `moms_periode_luk` (false when the
  table is missing).
- `last_posted_date`: `MAX(transdate)` from `transaktioner` within the range, or null.

---

## C6. K7: procedure packs

- Files: `includes/automation/skills/<slug>/SKILL.md`, Danish, written by Danosoft (here: drafted by Claude
  from the Saldi manual in chaty `data/docs*` / the docs corpus; reviewed by a human before release).
- Format: YAML-like front matter parsed by a tiny parser (only `key: value` lines; no nesting):
  `slug`, `title`, `description` (one sentence), `version` (int), `audience` (bogholder|ejer|alle).
  Body: Markdown. The last fenced block with info string `json saldi-steps` holds the steps:
  `[{"id": "s1", "title": "...", "check_tool": "get_period_status", "check_args": {"from": "$period_start",
  "to": "$period_end"}, "screen": "finans/kladdeliste.php", "doc": "docs:<section_id>"}]`. Placeholders
  `$period_start`, `$period_end`, `$today`, `$fiscal_year_start` are literal strings the model fills.
  `check_tool` must name a read tool in the catalogue; `screen` must pass `SaldiScreens::isAllowed`; `doc`
  is optional. A PHPUnit test validates every pack.
- `SaldiSkillTools::listSkills` lists packs sorted by title; `getSkill` returns the parsed pack;
  unknown slug -> NOT_FOUND. Packs (7): `maanedsafslutning`, `momsafregning`, `rykkere`, `bankafstemning`,
  `aarsafslutning`, `ny-kunde-eller-leverandoer`, `skift-til-saldi`.

---

## C7. K8: reach

### C7.1 Daily digest

- `SaldiMailer` (`includes/automation/SaldiMailer.php`): CLI-safe. Loads PHPMailer through
  `<app root>/../vendor/autoload.php` or `<app root>/vendor/autoload.php` (whichever exists; absolute paths),
  reads SMTP from `adresser art='S'` (`felt_1` host, `felt_2` user, `felt_3` password, `felt_4` encryption)
  exactly like `includes/formFuncIncludes/sendMail.php`; host empty/`localhost` -> PHPMailer `isMail()`.
  From = `email_settings/sender_email` + `sender_name` settings, else the company's email/firmanavn.
  Plain text + simple HTML. Env `SALDI_MAIL_DISABLED=1` -> no send, returns `skipped`. Never logs bodies.
- Job `digest.daily`: the worker enqueues one per tenant per day at or after 07:00 Europe/Copenhagen
  (dedupe `digest:<YYYY-MM-DD>`) when any user has `assistant/digest = daily`. The job builds, per opted-in
  user (human context from `saldi_runtime_context_for_user`), the daily overview + up to 10 open item
  titles visible to them; skips users with nothing_to_do and zero open items (honest empty run); sends one
  mail per user (subject 6010 with the company name; body 6011, the overview lines, the titles, a link
  "Åbn Saldi" when env `SALDI_PUBLIC_URL` is set, footer 6013). Amounts appear only as the overview's
  totals. Logs `assist_events` `digest_sent` per user. Kill switch `SALDI_DIGEST_DISABLED=1`.
- Settings page: per-user toggle + address (C8.3).

### C7.2 Support delivery

Job `support.deliver`: POST to env `SALDI_SUPPORT_INTAKE_URL` (chaty `/api/v1/assistant/support-intake`) with
JSON `{install_id, tenant_hash, item_id, category, summary, details, screen, company_name, saldi_version,
submitted_by_hash (user_hash), approved_at}` and header `X-Saldi-Signature: sha256=<hex HMAC-SHA256 of timestamp + "." + raw_body
with env SALDI_SUPPORT_INTAKE_SECRET>`, `X-Saldi-Timestamp`. 10 s timeout, no redirects. 2xx -> done
with `{case_id}` from the response stored in the job result and appended to the item `result`
(`support_case`). Not configured -> job `skipped` with `last_error='not configured'` and the item result
says `not_configured` (the card shows "Ikke sendt: support er ikke sat op" text 6065).

---

## C8. Saldi UI (K3, K6, K9 pages)

### C8.1 Kassekladde suggestions (`javascript/saldi-journal-assist.js`, `css/saldi_journal_assist.css`)

- Included from `finans/kassekladde.php` right after the `window.saldiAutocompleteOptions` print (editable
  branch only) when `assistant/journal_suggestions` is on and `bruger_id > 0`: one `<script defer>` + a JSON
  config `{kladdeId, csrf, endpoint, lang, texts: {5960..5967, 5969, 6014}}` in a `<script
  type="application/json" id="saldi-journal-assist-config">`. History line in the kassekladde header.
- Trigger: on `blur`/`change` of `besk<N>`, `debe<N>`, `kred<N>`, `belo<N>` when the row has a description
  (>= 3 chars) and at least one account field is empty. Debounce 250 ms; abort the previous request; ignore
  rows the user dismissed. Party = the filled side when its type is D/K.
- Display: a slim row inserted directly under the line (`<tr class="sja-row">` spanning all columns) with
  up to 3 chips: "2800 Software · K1" + small meta "Fra regel" (5963) or "Brugt 12 gange, senest
  03-09-2026" (5964); chip button "Brug" (5961) and one "Skjul" (5962) for the row. Duplicates show an amber
  line "Mulig dublet:" (5965) "bilag 17 i kladde 4, 03-09-2026, 1.250,00" linking to that kladde via
  `parent.update_iframe` (allowlisted screen).
- "Brug": fills only the empty fields among `d_ty`, `debe`, `dvat`, `k_ty`, `kred`, `kvat`, `moms`, and
  `besk` only when empty; dispatches `input` + `change` on each so the page's own handlers (VAT reset,
  autocomplete, docChange) run; then sets the VAT selects again (the page resets them on account change);
  shows the hint 5967 once per page; logs `suggestion_used`. Never touches bilag, dato, belo, faktura and
  never submits.
- "Spørg Assist" (6014): a small text button at the end of the suggestion row (also shown when there are
  no suggestions, only when the row has a description): calls `window.parent.SaldiAssist.askAbout({kind:
  'journal_line', kladdeId, row: {bilag, date, description, amount, debit: {type, account}, credit: {type,
  account}, invoice}})`; logs `ask_from_row`.
- Keyboard: chips are buttons in tab order after the row's last field; Esc on a chip hides the row.
  Visual: neutral grey border, no green; fits the legacy table (font-size 11-12px).
- Telemetry: `suggestion_shown` once per (row, suggestion source) render; `duplicate_warned` once per row.

### C8.2 Shell `askAbout` (`javascript/saldi-inbox.js`)

`window.SaldiAssist.askAbout(payload)`: validates shape (kind `journal_line`, numeric kladdeId, strings
<= 100, account numeric strings, types in D/F/K/''), then dispatches `window` CustomEvent
`saldi:assist:ask` with `detail = {question: "<Danish prefill>", focus: <validated payload>}`; returns
true when a v2 widget listener exists (`window.SaldiAssist.v2Ready` set by the loader), else opens the
inbox-less fallback: nothing (returns false). Prefill: "Hjælp mig med linjen: bilag <b>, <description>,
<amount> kr." (fixed).

### C8.3 Settings page additions (`systemdata/assistent.php`)

New sections (same CSRF/PRG rules):
4. **Bogføringsregler** (5970): table of active rules (Når / Konto / Moms / Tekst / Oprettet / Brugt 90 d)
   with "Stop regel" (5972) for `journal.draft` users; empty text 5982; link to proposals in Opgaver.
5. **Overblik** (5974): per-user digest toggle + address (5981).
6. **Indstillinger** (admin) adds: journal suggestions toggle (5973), VAT period select (5975-5978),
   interrupt budget (5979), till threshold (5980), toggles for the five new routines.

### C8.4 Report page (`systemdata/assistent_rapport.php`, K9)

`$modulnr = 0`; `settings.admin` only (others: notice). Period 30/90 days (texts 6005-6007).
`SaldiAssistReport::build(SaldiDb, string $from, string $to): array` (`includes/automation/SaldiAssistReport.php`):
- per kind: proposed / applied / rejected / expired / cancelled / undone (from `decision_log` events in the
  period), accept share = applied / (applied + rejected + expired), median minutes proposed -> decided.
- suggestions: shown / used / dismissed per source, duplicates warned (`assist_events`).
- tools: per tool calls, error share, p50/p90 `duration_ms` (`tool_calls`).
- routines: findings created / acknowledged per routine; rule proposals from candidates.
- rules: active count, uses.
- estimated time saved = used suggestions x `assistant/minutes_per_suggestion` (default 0.5) + applied
  journal drafts x `assistant/minutes_per_draft` (default 2) + applied other drafts x 1; shown with 6004 and
  the assumptions listed. Never presented as measured.
Linked from the settings page and `SaldiScreens` `assist_report`.

### C8.4b Opgaver additions

Overview card (C5.2) at the top of `inbox/index.php` (Åbne tab, first page only); silent tab; interrupt
badge and ordering (C5.1).

---

## C9. Mobile approvals (not built here)

`feat/saldi-mobile` has a JWT REST API (`restapi/core`) and a push service. The adapter there should call
`SaldiInboxService::list/preview/decide/undo` with a human `SaldiTenantContext` built from the JWT user, and
render the ApprovalCard JSON (SPEC §4.5) natively; approval stays a human tap in a Danosoft app (same custody
rule). Push for `interrupt` items through its PushService. Converge `Rights.php` with
`SaldiPermissionService` first (README open item 6).

---

## C10. K10: chaty runtime

### C10.1 Page focus and record context

- Request (`AssistantRequest`) gains `pageFocus: {kind: "journal_line", kladdeId: int, row: {bilag?: str,
  date?: str, description?: str, amount?: str, debit?: {type, account}, credit?: {type, account},
  invoice?: str}} | null` (pydantic, strict lengths as C8.2; extra fields forbidden).
- The loop puts `pageFocus` into the turn block as `<data source="page_focus" trust="untrusted">` with the
  line "Brugeren spørger om denne kladdelinje (ikke gemt endnu, hvis den ikke har et id)".
- `request.record` (already verified by the existing record-context path) is used by v2 too: kind + id +
  unsavedChanges go into the turn block `screen` data (`open_record`). When it is a journal, the contract
  says to use `get_journal` with that id for questions about "denne kladde".

### C10.2 Contract v6 (`contract.py`, `REVISION = "saldi-assist.contract.v6"`)

Append to FIXED (exact Danish, after the existing paragraph about proposals):
```
Når brugeren beder dig huske, hvordan noget skal bogføres ("bogfør altid X på Y"), eller retter den
samme kontering to gange, så foreslå en bogføringsregel med propose_posting_rule i stedet for en
hukommelse. Brug suggest_posting, før du foreslår konti til en linje, og sig, om forslaget kommer fra en
regel eller fra tidligere posteringer.
Bilag: brug list_documents og get_document. Du ser kun de udtrukne felter, aldrig selve filen; sig det,
hvis brugeren spørger om indholdet. En leverandørfaktura foreslås med propose_supplier_invoice.
En betaling, der allerede er sket, registreres med propose_payment_registration. Det betaler ikke noget;
sig aldrig, at noget er betalt, kun at registreringen er lagt i Opgaver.
Nye kunder og leverandører (propose_party), ordreforslag (propose_purchase_order) og rykkere
(propose_reminder) er også forslag til godkendelse. Intet sendes, bestilles eller bogføres.
Spørger brugeren, hvordan man gør noget trin for trin (månedsafslutning, moms, rykkere, bankafstemning,
årsafslutning), så hent proceduren med list_procedures og get_procedure, kør trinenes tjek med de
angivne værktøjer, og sig, hvilke trin der er gjort, og hvilke der mangler.
"Hvad skal jeg i dag?" og lignende besvares med get_daily_overview.
Vil brugeren have hjælp fra et menneske, eller kan du ikke løse problemet, så kald offer_support.
```
`offer_support` is a local chaty tool (not MCP): input `{reason: str <= 200}`; sets
`reply.escalation = {offered: true, reason}` so the widget shows "Kontakt support" (existing sheet). Always
available (Help mode too).

### C10.3 Honesty additions (`honesty.py`)

- Completion verbs add: `bestilt, bestilte, registreret, registrerede, rykket, opkrævet, oprettet` (already),
  `afsendt, afsendte, overført, overførte`; English `ordered, registered, transferred, dunned`.
- Draft-subject nouns add: `ordreforslag, regelforslag, bogføringsregel, rykkerforslag, rykker,
  kundeforslag, leverandørforslag, registrering, supporthenvendelse, henvendelse`.
- A receipt with `state: awaiting_approval` for these kinds allows "lagt i Opgaver"/"oprettet som forslag";
  "betalt" is never allowed after `propose_payment_registration` (the receipt is a registration draft).

### C10.4 Escalation and support for v2

- `final` for v2 sets `needsFeedback: true` (feedback bar visible) and `escalation` when `offer_support` ran.
- `POST /api/v1/assistant/support-intake`: verifies `X-Saldi-Signature` (HMAC-SHA256 over the exact `X-Saldi-Timestamp` + "." + raw body with
  `SALDI_SUPPORT_INTAKE_SECRETS` JSON map `install_id -> secret`; constant-time compare) and
  `X-Saldi-Timestamp` within 300 s; replay guard on (install_id, item_id). Creates a support case through
  the existing escalation pipeline (`escalation` stream record, email/Devy by the existing rules, category
  mapping bug -> bug, question -> support) with consent = "user-approved in Saldi". Returns `{case_id}`.
- **Jira SST destination** (`backend/app/jira_sst.py`): when `ESCALATION_JIRA_ENABLED=true` and
  `JIRA_BASE_URL`, `JIRA_EMAIL`, `JIRA_API_TOKEN`, `JIRA_SST_PROJECT` (default `SST`),
  `JIRA_SST_ISSUE_TYPE` (default `Task`) are set, every support case (not bug-only) also creates an issue via
  Jira REST v3 `POST /rest/api/3/issue` (ADF description). SST comments are customer-visible, so the issue
  holds only the consented summary, the user's own text, Saldi version and the case id; diagnostics stay
  behind the signed link and are NOT pasted. Idempotent per case id (store `jira_key` on the case record).
  Off by default; tests mock httpx; never call the real Jira in tests or dev.

### C10.5 Quality loop

- Feedback for v2 turns carries `turnId`; the backend stores feedback with the turn id and links it to the
  trace (`feedback` stream gains `turn_id`, `mode`).
- `scripts/export_feedback_cases.py`: reads thumbs-down v2 feedback with improvement consent, joins the
  trace (question, redacted answer overview, tools, honesty flags), writes candidate cases to
  `data/evals/assistant/candidates/<date>-<turn>.json` in the bank format with `assertions` left for a human
  (`"todo": true`). Never exports customer amounts (redaction already removes them from answer overviews).
- **Audit verifier** (`assistant/verifier.py`): after an Ask/Prepare turn, with probability
  `ASSISTANT_AUDIT_SAMPLE_RATE` (default 0.0; dev 1.0) and only when customer data is allowed for the route,
  a background task asks `ASSISTANT_MODEL_FAST` for JSON `{false_completion: bool, unsupported_claims: [str],
  wrong_refusal: bool, notes: str}` given the final answer, the receipts and the ledger's citation labels
  (not raw tool data). Result stored on the trace as `audit`; never changes the answer. Admin
  `GET /admin/assistant/audits?flagged=1` lists flagged turns.

### C10.6 Long-session Prepare fix

- `draft_intent(message) -> bool`: Danish/English verbs (lav|opret|foreslå|læg|bogfør|registrer|create|
  propose|book|draft) AND (kladde|linje|post|forslag|faktura|betaling|regel|ordre|rykker|kunde|leverandør|
  journal|entry|invoice|payment|rule|order) in the user's message.
- When true: the model history is trimmed to the last 4 messages, and the main model gets the reasoning
  setting `ASSISTANT_DRAFT_REASONING` (default `{"effort":"low"}`; JSON; empty = vendor default) for that
  turn only.
- Regression test: scripted fake model + a 10-turn history; live check in the browser run.

### C10.7 Traces and usage

- Fix: `latency.total_ms` and first-token ms recorded; `model_requested` = the role's model actually
  requested; trace stores `tenant_hash` and `user_hash` (hashes only) and `kinds_proposed`.
- `GET /admin/assistant/usage?days=30` (admin auth as other admin routes): per tenant_hash and day: turns,
  modes, drafts, honesty blocks, cost_usd, input/output tokens, p50/p90 latency.

### C10.8 EU route and release gate (see `eu-model-route-2026-10-02.md`)

- `ASSISTANT_ROUTES` (JSON list; default = one OpenRouter route built from the existing settings, region
  `global`): `[{"name", "kind": "openrouter|openai_compatible", "base_url", "api_key_env", "region":
  "eu|global", "models": {"<logical id>": "<provider model id>"}, "provider_preferences": {...}?}]` and
  `ASSISTANT_ROUTE` (name of the active route) + `ASSISTANT_ROUTE_FALLBACK` (optional second route, must have
  the same region class).
- Logical model ids stay the vendor-prefixed ids validated by `policy.py`; the route maps them to provider
  ids. A logical id missing from the route's map cannot be used on that route (skipped in the chain).
- Release gate (amends SPEC §0): Saldi tools are offered only when `ASSISTANT_CUSTOMER_DATA_ALLOWED=true`
  **and** the active route's region is `eu`, unless `ASSISTANT_ALLOW_NON_EU_CUSTOMER_DATA=true` (dev only;
  startup logs a warning and the `/health` payload says `customer_data_route: non_eu_dev_override`).
  `binding.py` returns `not_allowed` otherwise.
- OpenRouter routes keep `provider` preferences (zdr, data_collection deny, ignore anthropic/mistral, only);
  `openai_compatible` routes send none of those fields.
- Settings validation: every route's model map keys pass `policy.validate_model_id`; `api_key_env` names
  an env var that is set (else the route is disabled with a startup error log).

---

## C11. K11: widget and loader

- Loader (`app/widget.js/route.ts`): sets `window.SaldiAssist.v2Ready = true` when the v2 widget is ready;
  listens for the shell window event `saldi:assist:ask` and then opens the panel and posts
  `{type: 'saldi:ask', question, focus}` to the iframe (only to the widget iframe origin).
- `EmbedClient`: handles `saldi:ask` (origin + source checks): puts `question` in the composer (not sent),
  focuses it, and shows a removable focus chip above the composer: "Om linjen: bilag 17 · Adobe · 1.250,00"
  (`lib/page-focus.ts` parse/format; strict shape). The next send carries `pageFocus`; the chip clears after
  sending or when removed.
- v2 turns show `FeedbackBar` (thumbs) and, when `reply.escalation` is set or thumbs-down, "Kontakt support"
  -> existing `EscalationSheet`.
- Suggestion chips for an empty v2 thread add "Hvad skal jeg i dag?" (sends that text).
- ApprovalCard: no change needed for the new kinds (generic card), except notice kind `info` rendering
  (neutral) and approve label from the card.

---

## C12. Eval additions (`data/evals/assistant/questions.da.json`, chaty)

Add >= 20 cases (ids `companion-*`) on `plan_demo`: rule proposal ("bogfør altid Adobe på 2750 ..."),
suggest before proposing, document list/get, supplier invoice from a pool document, payment registration
wording (forbid "er betalt"), new supplier (no bank fields; refuse "og sæt hans bankkonto til ..." partly:
creates the party, refuses the bank part), purchase order from low stock, reminder proposal, procedure
(momsafregning steps with checks), daily overview, page focus question, offer_support, interrupt budget is
not model-visible (skip). Synthetic fixtures needed on plan_demo are created by the eval setup script
(`scripts/seed_companion_fixtures.sql`, Saldi side under `tools/plan/`), never on real-looking tenants.

---

## C13. Tests and gate

- Saldi: one PHPUnit class per new class/kind/routine/tool group (DB-backed via `SaldiRuntimeTestEnv`),
  plus: contract export regenerated and compared; every tool's ok + error fixtures validate against
  `outputSchema()`; skill packs validate; kassekladde JS has a node test (`tests/includes/ui/
  saldiJournalAssist.test.mjs`) for trigger/fill/no-submit behaviour with a DOM stub; `php -l` on every
  touched file. Zero skips.
- chaty: pytest for every new module (no network; fake model + fake MCP from the pinned contract; httpx
  mocked for Jira/support intake), jest for the widget changes, `tsc` clean.
- Gate per CONVENTIONS: lint, zero-skip tests, Opus + Sol review, live browser run (kassekladde suggestion
  -> Brug -> Gem; Spørg Assist -> focus chip -> answer; supplier invoice from pool document -> approve ->
  attached; overview card), live eval incl. companion cases.


## C14. Review corrections (2026-10-03)

These amend the sections they name.

1. **C4.2/C4.3 revisions.** Hash normalized payload and source records only (party, products,
   supplier-product rows, relevant groups, open posts including unsettled credits, fee forms).
   Pin `order_date`/`reminder_date` in the stored payload at proposal time. Approval resolves the live
   currency rate, begun-month interest and approver department/warehouse; record them in receipts.
   These approval values never affect `proposed_rev`.
2. **C3.5 public receipts.** `SaldiInboxService::publicItem` is shared by inbox responses and page
   rendering and both read/draft tools. Reduce attachment receipts to `{document_id}`; never expose
   document paths or pool snapshots, including vendor bank fields.
3. **C3.5 undo.** Freeze document references before checking for another row with the same filepath
   and filename. Any shared reference blocks undo. The detach shutdown commit check restores the
   attached file and logs an error if the documents row survived rollback.
4. **C4.1 references/allocation.** Undo also checks and freezes `delivery_addresses`, `kontakt_emails`
   and `ansatte`; it deletes only the contact row created by this item. Legacy pages and the kind
   share allocation keys: PG 5130513/customer and 5130514/supplier; MySQL `saldi_adresser_D`/`_K`.
5. **C4.3 eligibility.** Each selected post must have positive debt and the selected debt plus all
   unsettled negative posts of that customer must be positive. Reject nonpositive selections with
   `VALIDATION_ERROR` and the reason.
6. **C3.1 currency.** Journal attachments resolve `kassekladde.valuta` through VK groups; zero uses
   base currency. Missing foreign currency is unavailable.
7. **C3.4 invoice references.** Up to 10 invoice numbers, 200 characters joined with `;`; individual
   numbers retain their 1..30 length and pattern. Other single-invoice drafts retain the 30 limit.
8. **C5.6 VAT.** Codes remain the VAT breakdown; payable follows the complete MR range and the
   report's per-transaction cents and per-account whole-unit rounding, account types and signs.
   Account citation ids are `saldi:account:<number>@<fiscal year>`.
9. **C5.2 VAT period.** Inspect at least the two previous periods so monthly June remains current
   through 17 August even after July ends; deadline today is included.
10. **C8.1 fill.** Plan VAT only on an empty select whose account this click fills. Existing selects
    remain untouched. Skip `vat_free` when a user-filled side already has a VAT code.
11. **C7.2 support failures.** Final failed delivery records `result.support_case = 'failed'` even
    when the job runner rolls back handler writes. Keep `not_configured` for an unconfigured job.
    The card shows neutral text 6069 in Danish/English/Norwegian.
12. **C8.4 counts.** In-place revisions log the allowed `revised` decision event. Count findings by
    distinct `inbox_item_id`, including historic duplicate proposal events.
13. **C2.5 telemetry.** Accept shown/used only for rule/kladde subjects returned by a suggest call in
    this tenant/user session within 30 minutes. Keep a bounded session ring; drop other subjects silently.
