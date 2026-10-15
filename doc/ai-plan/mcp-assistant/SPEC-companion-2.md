# Saldi Assist companion, part 2: build spec

Extends [SPEC.md](SPEC.md) and [SPEC-companion.md](SPEC-companion.md) (both binding). Same branch, same
conventions (SPEC §1), same custody rules: agents read and propose; a human approves in Saldi; nothing
posts, pays, sends, files, locks or deletes posted/master records. Date 2026-10-04. Code facts below come
from the 2026-10-04 code maps (bank import, settlement, posting, purchasing, sales, POS, stock, reports).

| id | capability | slice |
|---|---|---|
| D1 | Bank statement -> kassekladde draft (CSV + camt.053, staging, matching) | B6 |
| D2 | "Tjek kladden" pre-posting check | B7 |
| D3 | Settlement (udligning) proposals for posted open items | B7 |
| D4 | Supplier invoice <-> purchase order matching, staged for receiving | B8 |
| D5 | Reorder proposals from sales velocity | B8 |
| D6 | Sales offer/order drafts | B9 |
| D7 | Credit note drafts | B9 |
| D8 | Customer statement (for message texts the user sends) | B9 |
| D9 | Period comparison by account and account group | B10 |
| D10 | Accountant year-end pack (tool + printable page) | B10 |
| D11 | Daily till summary | B10 |
| D12 | Stock count differences + FEFO | B10 |
| D13 | Master-data hygiene (tool + weekly routine) | B10 |

Safety lines decided from the code maps:
- D1 never inserts into kassekladde directly: it produces a `journal.draft_entry` (existing kind, undo exists).
  Customer/supplier payment lines carry the invoice number in `faktura`, so Saldi's own posting settles them.
- D3 settles only exact pairs/sets (|sum| < 0.005, same currency, same party); never writes `transaktioner`
  (no øre/currency difference postings); undo restores exactly what it changed.
- D4 never receives goods (Saldi's receive is irreversible: stock, other orders' batch rows, webshop sync) and
  never posts. It stages the supplier invoice number, invoice date (`levdate`), quantities to receive
  (`leveres`) and line prices on an open KO order; the user presses Modtag and Bogfør.
- D6/D7 stop at status 0 (tilbud / credit-note draft); invoicing posts and stays human.
- D11/D12 are read-only; `cashCountHistoryLoadAndRepair()` writes and must never be called.

---

## E1. Foundation (one builder first)

### E1.1 Schema step `s92_bank_import_lines` (signature `line_hash`)

```sql
CREATE TABLE bank_import_lines (
  id serial PRIMARY KEY,
  batch_id varchar(32) NOT NULL,        -- 32 hex, one per upload/paste
  line_no integer NOT NULL,
  bank_account varchar(15) NOT NULL,    -- finance account the statement belongs to
  transdate date NOT NULL,
  description varchar(255) NOT NULL,
  amount numeric(15,3) NOT NULL,        -- signed: + money in, - money out
  currency varchar(3) NOT NULL,
  balance numeric(15,3),
  reference varchar(100),               -- end-to-end id / FIK / payment id when the file has one
  line_hash char(64) NOT NULL,          -- sha256(bank_account|date|amount|normalised text|reference)
  status varchar(16) NOT NULL DEFAULT 'new', -- new | proposed | duplicate | ignored
  proposal text,                        -- JSON of the chosen match; null until matched
  inbox_item_id integer,
  created_by integer NOT NULL,
  created_at timestamp NOT NULL
);
CREATE INDEX bank_import_lines_batch_idx ON bank_import_lines (batch_id, line_no);
CREATE INDEX bank_import_lines_hash_idx ON bank_import_lines (line_hash);
```

### E1.2 Permissions (amends SPEC §3.1)
`sales.draft` = write on pos 5. Agent draft scope adds `sales.draft`. Existing: `journal.draft`,
`debtors.draft`, `creditors.draft`, `purchases.draft`.

### E1.3 Kinds (stub classes first, slices fill them)
`settlement.draft` (`SaldiSettlementKind`, B7), `purchase_order.invoice_stage` (`SaldiPurchaseInvoiceStageKind`,
B8), `sales_order.draft` (`SaldiSalesOrderDraftKind`, B9), `credit_note.draft` (`SaldiCreditNoteDraftKind`, B9).

### E1.4 Tools (registry + schemas + stub classes; same rules as SPEC-companion C1.6)

| tool | discovery | effect | class::method |
|---|---|---|---|
| `import_bank_statement` | journal.draft | read* | `SaldiBankTools::import` |
| `list_bank_lines` | journal.read | read | `SaldiBankTools::listLines` |
| `propose_bank_entries` | journal.draft | draft | `SaldiBankTools::propose` |
| `check_journal` | journal.read | read | `SaldiJournalCheckTools::check` |
| `suggest_settlements` | debtors.read, creditors.read | read | `SaldiSettlementTools::suggest` |
| `propose_settlement` | debtors.draft, creditors.draft | draft | `SaldiDraftTools::propose('settlement.draft')` |
| `match_purchase_order` | purchases.read | read | `SaldiPurchaseTools::match` |
| `propose_purchase_invoice_stage` | purchases.draft | draft | `SaldiDraftTools::propose('purchase_order.invoice_stage')` |
| `suggest_reorders` | products.read | read | `SaldiPurchaseTools::reorders` |
| `propose_sales_order` | sales.draft | draft | `SaldiDraftTools::propose('sales_order.draft')` |
| `propose_credit_note` | sales.draft | draft | `SaldiDraftTools::propose('credit_note.draft')` |
| `get_customer_statement` | debtors.read | read | `SaldiSalesTools::statement` |
| `compare_periods` | ledger.read | read | `SaldiInsightTools::comparePeriods` |
| `get_year_end_pack` | ledger.read | read | `SaldiInsightTools::yearEndPack` |
| `get_till_summary` | sales.read | read | `SaldiInsightTools::tillSummary` |
| `get_stock_count` | products.read | read | `SaldiInsightTools::stockCount` |
| `check_master_data` | company.read | read | `SaldiInsightTools::masterData` |

\* `import_bank_statement` writes only to the staging table `bank_import_lines` (never to the books); its
effect in the contract is `read` with the note "stages lines for matching; changes nothing in the books".
It requires `journal.draft` at execution. Total tools after this spec: 54.

Citations: `saldi:bank_line:<id>`, `saldi:order:<id>` (exists; now also DO/DK/PO with sales visibility).
Screens: `sales_offer` -> `debitor/ordre.php?id=`, `bank_import` -> `finans/assist_bankfil.php`,
`year_end_pack` -> `systemdata/revisorpakke.php`, `stock_count` -> `lager/optalling.php` (verify each).

### E1.5 Routines (stubs; slices fill them)
`reorder_suggestions` (weekly, products.read, B8), `master_data_hygiene` (weekly, company.read, B10).

### E1.6 Text ids 6100-6299 (Danish fixed here; builders may add rows in their sub-range)

| id | Dansk |
|---|---|
| 6100 | Udligning |
| 6101 | Faktura klar til modtagelse |
| 6102 | Tilbud |
| 6103 | Ordre |
| 6104 | Kreditnota |
| 6110 | Når du godkender, markeres posterne som udlignet. Intet bogføres, og du kan fortryde, så længe posterne er uændrede. |
| 6111 | Når du godkender, skrives fakturanummer, fakturadato, antal og priser på ordren. Varerne modtages og fakturaen bogføres først, når du selv trykker Modtag og Bogfør på ordren. |
| 6112 | Når du godkender, oprettes tilbuddet i Saldi. Intet sendes eller faktureres. |
| 6113 | Når du godkender, oprettes ordren i Saldi. Intet sendes eller faktureres. |
| 6114 | Når du godkender, oprettes kreditnotaen som kladde på ordren. Den bogføres og sendes først, når du selv gør det. |
| 6120 | Bankfil til kassekladde |
| 6121 | Vælg bankkonto |
| 6122 | Vælg fil (CSV eller camt.053) |
| 6123 | Læs filen |
| 6124 | Lad Assist foreslå konteringer |
| 6125 | Linjer i filen |
| 6126 | Allerede i en kassekladde |
| 6127 | Ingen sikker modpost |
| 6128 | Forslaget ligger i Opgaver. Intet er bogført. |
| 6129 | Kunde/leverandør betaling, faktura %s |
| 6140 | Tjek kladden |
| 6141 | Ingen fund. Kladden ser klar ud. |
| 6142 | Fejl, der stopper bogføringen |
| 6143 | Bør tjekkes |
| 6144 | Bilaget stemmer ikke: forskel %s |
| 6145 | Dato uden for regnskabsåret |
| 6146 | Momsperioden er lukket |
| 6147 | Kontoen findes ikke eller er lukket |
| 6148 | Momskoden findes ikke; der beregnes ingen moms |
| 6149 | Momskoden afviger fra kontoens (%s) |
| 6150 | Momskoden afviger fra bogføringsreglen (%s) |
| 6151 | Usædvanligt beløb for kontoen (normalt %s) |
| 6152 | Mulig dublet af bilag %s i kladde %s |
| 6153 | Bilag mangler |
| 6154 | Linjen mangler konto på den ene side |
| 6160 | Revisorpakke |
| 6161 | Saldobalance |
| 6162 | Åbne poster |
| 6163 | Moms |
| 6164 | Ikke-bogførte kassekladder |
| 6165 | Noter til revisor |
| 6166 | Udskriv |
| 6167 | Hent CSV |
| 6170 | Genbestilling ud fra salg |
| 6171 | Stamdata, der bør ryddes op |

Sub-ranges: B6 6180-6199, B7 6200-6219, B8 6220-6239, B9 6240-6259, B10 6260-6279, UI 6280-6299; review corrections 6300-6309. IDs 6291-6295 remain exclusive to journal checking.

---

## E2. D1 Bank statement -> kassekladde (B6)

### E2.1 Parser `SaldiBankStatementParser` (`includes/automation/SaldiBankStatementParser.php`)
- `parse(string $content, ?string $filename): array{format, rows: list<{date, description, amount, currency,
  balance?, reference?, statement_account?}>, warnings, statement_accounts?, statement_count?}`. Max 2 MB / 5000 rows.
- camt.053 (ISO 20022 `BkToCstmrStmt`): `Ntry` -> `BookgDt/Dt` (else `ValDt`), `Amt` (+`@Ccy`),
  `CdtDbtInd` (DBIT = negative), text from `NtryDtls/TxDtls/RmtInf/Ustrd` joined, else `AddtlNtryInf`;
  reference from `Refs/EndToEndId` (not `NOTPROVIDED`) or `AcctSvcrRef`. Preserve each Stmt account
  (IBAN, else BBAN/Othr Id) and reject an explicit Ntry/Sts other than BOOK. XML parsed with external entities off.
- CSV: encoding = UTF-8 if valid, else Windows-1252 converted; delimiter by the most consistent of `;` `,` TAB
  over the first 20 lines; header row detected by Danish/English names (dato|bogført|date; tekst|beskrivelse|
  text|posteringstekst; beløb|amount; saldo|balance; valuta|currency; reference|ref). Without a header: date =
  first column parsing as a date, amount = the column whose values all parse as amounts and that is not the
  last running balance (if two candidates, the one whose running sum matches the balance column).
  Reject unresolved numeric columns with a Danish warning asking for a header row. A dot-separated
  thousands amount without decimals uses the Danish convention when other amounts use comma decimals.
- Dates: `dd-mm-yyyy`, `dd.mm.yyyy`, `dd/mm/yyyy`, `yyyy-mm-dd`, `dd-mm-yy`. Amounts: Danish `1.234,56`,
  `-1.234,56`, `1234,56`, plain `1234.56`, and `1,234.56` (US, detected when the last separator is `.` and a
  `,` precedes it). Never cast with (float) before classifying (the legacy bug).
- Known layouts tested with fixtures: Danske Bank, Nordea, Jyske Bank, Sydbank, Spar Nord, Lunar, Arbejdernes
  Landsbank netbank CSV exports (builder writes realistic synthetic fixtures per layout; no real data).

### E2.2 Staging `SaldiBankImport` (`includes/automation/SaldiBankImport.php`)
- `stage(ctx, db, bankAccount, parsed): {batch_id, lines, duplicates}`: bank account must be an open balance
  account (kontotype S, not lukket) in the fiscal year; rows outside the active fiscal year are kept with
  status `ignored` and a warning; `line_hash` duplicates of earlier **proposed** lines -> status `duplicate`;
  supersede older unproposed previews instead of blocking re-upload. Consume each prior proposal/journal
  movement once per batch, including a proposal and its applied journal row as one movement. A row
  whose (date, amount, bank account) already exists as a kassekladde line on that bank account (either side)
  within ±2 days -> status `duplicate` with the line id in `proposal`.
- For multi-account camt, select only the Stmt matching the ledger's known IBAN/BBAN. Read an optional
  `kontoplan.iban` column or an explicit tenant setting `assistant/bank_iban.<ledger account>`; never infer
  a ledger association from the company bank account. Without a mapping reject distinct accounts with a
  Danish warning listing them; multiple unidentified Stmt accounts also require selection. Single-account files remain supported. No schema migration is required.
- Truncate descriptions to 255 characters and references to 100 with warnings, rather than rejecting a file.
- Cache permission-bound preview matches in `proposal` at staging. Reuse one matcher/suggester and its account,
  party, rule and event lookups per request. Proposal and approval still validate live context.
- Batches older than 30 days are deleted lazily on the next stage call (staging only).

### E2.3 Matcher `SaldiBankMatcher`
Per non-duplicate line, first hit wins, each with `confidence` high|medium:
1. Invoice reference: an invoice number (`ordrer.fakturanr`, `openpost.faktnr`) or payment id (`betalings_id`,
   FIK `+71<...>` / `IK71`, `ha_` ids, Nordea "Indbetalingskort, nr.") found in description/reference ->
   the open item's party; customer with positive open amount for money in, supplier with negative open amount
   for money out; `invoice` = that number. Never match the opposite sign by absolute value. high if the
   amount equals the open amount (±0.005), else medium.
2. Exact open-item amount for exactly one party whose name (normalised, >= 4 chars) occurs in the text -> high.
3. Posting rule / history via `SaldiPostingSuggester::suggest(['description' => text, 'amount' => abs])`
   accept a history pattern only when one side is the selected bank; take the opposite side as the counter.
   A one-sided approved rule may supply an explicit counter account. Do not reinterpret invoice registrations
   as bank payments. Preserve `vat_code` and `vat_free`; rule VAT `none` means exemption. Show exemption or
   a VAT override on the approval card when it differs from the account default.
4. No match -> status stays `new`, reported as "Ingen sikker modpost" (6127).
Money in: debit bank, credit counter. Money out: credit bank, debit counter.

### E2.4 Tools
- `import_bank_statement` `{bank_account, content (<= 200000 chars, pasted CSV or camt.053 XML), filename?}`
  -> `{batch_id, lines: [{line_id, date, description, amount, currency, status}], duplicates, warnings}`.
- `list_bank_lines` `{batch_id, status?}` -> rows + each row's `match` (party/account, invoice, confidence).
- `propose_bank_entries` `{batch_id, line_ids? (default all lines with confidence high), kladde_id?,
  explanation, request_id, ...common}` -> one `journal.draft_entry` with one two-sided line per bank line.
  Lines with confidence medium are included only when listed explicitly in `line_ids`. Marks the staged lines
  `proposed` with the item id (and back to `new` if the item is rejected, expired or undone).
  Amends SPEC §4.4 for drafts created by this tool (payload flag `bank_batch_id`): each line keeps its own
  `date` (inside the fiscal year) and gets its own consecutive voucher number (`bilag`), because every bank
  line is its own voucher; balance is per line (two-sided lines always balance). Max 200 lines per draft.

### E2.5 Page `finans/assist_bankfil.php` (UI slice U5)
Upload (or paste) -> choose bank account (remembered per user in settings `assistant/bank_account`) -> preview
table with per-line match, status and confidence -> "Lad Assist foreslå konteringer" creates the proposal and
links to it in Opgaver. Linked from the kassekladde list and the Finans menu. No model call (the matcher is
deterministic).

---

## E3. D2 Kladde check (B7 service, U4 UI)

`SaldiJournalCheck::run(ctx, db, kladdeId): {journal_id, status, ready: bool, errors: [...], warnings: [...]}`
(read-only; never loads `bogfor.php`). Finding: `{code, severity: error|warning, line_ids, bilag, message_da,
detail}`.
Errors (block posting in Saldi): kladde not open (`bogfort` <> '-'); kladde total does not balance
(|diff| >= 0.01, unless |diff| < number of foreign-currency rows / 100). Use the historical exchange rate
on the row date, ignoring its stored rate, and round converted amounts to cents as bogfor.php does; date outside the active fiscal year (RA box1-box4 + box5);
date in a closed VAT month (`moms_periode_luk`); F account missing/closed/H/Z in the fiscal year; D/K account
missing or closed; line with an amount but no account on either side; currency without a rate on the date
(warning in Saldi, error here? -> warning).
Warnings: individual bilag imbalance (bogfor.php displays it but does not block); missing currency rate
(leaves the raw amount like valutaopslag); blank VAT overrides inherit the account code unless the opposite
side is explicit. VAT code unknown (Saldi silently posts no VAT) (6148); VAT code differs from the account's code (6149)
or from an active posting rule for the party/text (6150); amount outlier: |amount| > 5 x the median of the
account's last 50 lines and > 1000 (6151); duplicate (same party on the same debit/credit side and amount within 60 days, or same invoice for that
party on that side), emitting each unordered line pair once (6152); missing bilag per kladde+bilag group (documents or legacy
`dokument`) (6153); one-sided line (6154).
Tool `check_journal {journal_id}`. Kassekladde UI: a "Tjek kladden" button next to Gem (U4) calls
`assistSuggest.php` op `check` and shows the findings in a panel above the table; each finding links to its
rows (highlight like `highlightRows`).

## E4. D3 Settlement (B7)

- `SaldiSettlementTools::suggest {party_type, party_account?, limit?}`: per party, unsettled `openpost` rows
  (`udlignet <> '1'`), same currency; find (a) pairs with |a + b| < 0.005, (b) one payment covering 2-3
  invoices exactly (subset sum in øre, max 12 candidates per party), (c) invoice-number matches (`faktnr`
  equal, opposite sign, exact). Returns `{groups: [{party, item_ids, amounts, total: "0.00", reason}]}`.
- `settlement.draft` kind: actionClass `persistent`; propose/decide `debtors.draft` or `creditors.draft` by
  party type; visibility permission. Input `{party_type, party_account, item_ids (2..10)}`. validate: all
  rows exist, same `konto_id`, unsettled, same currency (`valuta`), sum of `amount` in øre = 0 exactly (else
  VALIDATION_ERROR `reason: not_balanced` with the difference; øre differences need Saldi's manual
  udligning), every `transdate` in or before the active fiscal year. Apply: `udlign_id` = under
  `lock('saldi_udlign_id')` `COALESCE(MAX(udlign_id),0)+1`; `UPDATE openpost SET udlignet='1', udlign_id=?,
  udlign_date=? WHERE id IN (...) AND COALESCE(udlignet,'') <> '1'` (row count must equal the set, else
  CONFLICT); udlign_date = the latest `transdate` of the set. Receipt `{udlign_id, item_ids, before: [{id,
  udlignet, udlign_id, udlign_date}]}`. canUndo: all rows still carry this udlign_id and udlignet '1'. Undo:
  restore the `before` values exactly. Card: Faktura/Dato/Beløb rows, total 0,00; next 6110.

## E5. D4 + D5 Purchasing (B8)

- `match_purchase_order {supplier_account? , document_ref?, invoice_number?, order_number?, lines?: [{item_no?,
  description?, quantity?, unit_price?}]}`: candidate open KO orders (status 1-2, same supplier; order number /
  `kundeordnr` from the reference first) with per-line comparison: ordered, received (Σ`batch_kob.antal` per
  line), remaining, order price vs invoice price, quantity difference; `{orders: [{order_id, order_number,
  score, lines: [...], differences: [...]}]}`. From a pool document it uses the extracted fields (supplier,
  invoice number, amount); without lines it compares totals.
- `purchase_order.invoice_stage` kind: `reversible_draft`; propose/decide `purchases.draft`. Input `{order_id,
  invoice_number (1..30), invoice_date, lines: [{line_id, receive_quantity?, unit_price?}]}`. validate: order
  art KO, status 1 or 2, not locked by another user (`hvem`/`tidspkt` < 1 h like journal), invoice_date in the
  fiscal year and >= ordredate; `receive_quantity` <= remaining; `unit_price` changes only on lines with no
  `batch_kob` rows. Apply (snapshot first): `ordrer.fakturanr`, `levdate = invoice_date`;
  `ordrelinjer.leveres`, `pris`. Never calls receive or posting code, never writes stock, batch or cost price.
  Receipt `{order_id, before: {...}, after_sha256}`; canUndo while header+lines equal `after_sha256` and status
  unchanged; undo restores `before`. Hash business fields and receipt identities/quantities only, excluding
  order lock fields and batch remaining stock (`rest`); check other users' locks live at apply/undo. Card: Linje/Antal at modtage/Pris før/Pris nu; next 6111.
- `suggest_reorders {days? (30..365, default 90), lead_days? (default setting assistant/lead_days or 14)}`:
  per active stock item (VG box8 'on'): sold per day = Σ signed `batch_salg.antal` (`ordre_id <> 0`, order
  status >= 3) over `days` / days; on hand = `beholdning`; on order = open KO lines minus received; cover days =
  (on hand + on order) / per day; reorder when cover < lead_days + 7 or on hand <= min_lager; quantity =
  max(min_lager, per day x (lead_days + 30)) - on hand - on order, rounded up to `colli` when > 0;
  return only positive suggestions so zero-demand stock cannot fill the routine queue. Supplier from
  `vare_lev` (posnr 1). Rows `{product_id, item_no, text, per_day, on_hand, on_order, cover_days, suggest_qty,
  supplier: {account, name}}`. Routine `reorder_suggestions` (weekly) posts a finding with the top 20.

## E6. D6 + D7 + D8 Sales (B9)

- `sales_order.draft` kind: `reversible_draft`; propose/decide `sales.draft`. Input `{customer_account,
  type: offer|order, lines: 1..50 x {product_id?|item_no?, description?, quantity, unit_price?, discount_pct?},
  reference?, note?, delivery_date?}`. Port of `debitor/ordre.php:836-863` header insert (not `opret_ordre()`)
  and the price/VAT logic of `opret_ordrelinje()` (price precedence: given price, tier price for b2b, special
  price in window, `salgspris`; discount groups; line VAT from VG box4 -> kontoplan.moms -> SM; VAT-free VG
  box7). Text lines inserted directly (`ordre_id, posnr, beskrivelse`; never the broken free-text branch).
  Number via the DO/DK lock like `get_next_order_number`. status 0 = offer, 1 = order (no delivery, no invoice).
  Pin the resolved unit price and discount in the internal payload at proposal, including request retries.
  An explicit zero discount overrides a campaign. Approval may resolve permitted live header values, but
  inserts exactly the reviewed price/discount. Promotion expiry alone does not make a draft stale; changes
  to its source product/offer still do. Hash pricing/account configuration without `varer.beholdning` or
  ledger balances. Insert sum/moms/kostpris with legacy line/header rounding (including VAT-inclusive
  customer settings and surcharges). Undo hashes include totals and written business fields, excluding
  `hvem`, `tidspkt`, `lock_token` and other lock state; guard status/delivery/posted references live.
- `credit_note.draft` kind: `reversible_draft`; propose/decide `sales.draft`. Input `{invoice_order_id, lines?:
  [{line_id, quantity}] (default all), reason (<= 200)}`. validate: source art DO, status >= 3 (invoiced), no
  existing DK with `kred_ord_id` = source (else CONFLICT naming it), quantities <= original.
  Revenue-hidden users cannot propose, read, render or act on these cards, including older inbox items. Apply: port of
  `debitor/ordre.php:2580-2711` (header copied, `art='DK'`, `kred_ord_id`, status 0, lines with negative
  `antal`, `kred_linje_id`, source price/discount kept, VAT rules as listed). Preserve charged service/text
  lines (vare_id=0 with price/account) as priced lines with their accounting and VAT fields. Undo while status 0 and unchanged.
- `get_customer_statement {party_id|account, from?, to?}`: open items (as of `to`) + payments and invoices in
  the range (from `openpost`), opening/closing balance, overdue sum; classify using linked documents and
  journal debit/credit origins, including manual invoices with invoice numbers. A payment can share its
  invoice's order reference; the sign must agree with the document. Statement rows expose optional
  `classification_uncertain: bool` where only conventional sign/reference grouping is available; for message texts the user sends himself.

## E7. D9-D13 Insight (B10)

- `compare_periods {a_from, a_to, b_from, b_to, level: account|group (default group), only_changed?,
  limit?}`: the SQL pattern from the code map (Z groups: accounts between `fra_kto` and the Z account number;
  amounts debit - credit, revenue negative; primo never included); rows `{key, name, a, b, change,
  change_pct}` sorted by |change|; revenue-hidden -> P&L rows omitted.
- `get_year_end_pack {fiscal_year?}`: trial balance totals, open items as of year end (`transdate<=D AND
  (udlignet<>'1' OR udlign_date>D)`), VAT summary over every calendar quarter intersecting the fiscal year (including partial quarters and
  18-month years, with sequential quarter indices), unposted kladder, periods closed, notes
  (company memory facts tagged for the accountant are out of scope), links. Page `systemdata/revisorpakke.php`
  (U5): printable HTML of the same + CSV downloads (trial balance, open items, VAT), settings.admin or
  ledger.read. The page/CSV trial balance and open items are unlimited; only the MCP envelope may cap
  them at 200 rows with `possibly_incomplete`. Totals always include all eligible rows.
- `get_till_summary {date, till?}`: per till: receipts, turnover (net and gross), refunds (`sum<0` or
  `kred_ord_id`), payments by method (pos_betalinger with the change rule; convert every foreign tender to base currency
  using its stored rate / 100 before computing change, retaining payment totals in their tender currency), expected/counted/difference from
  the `report` cashCount rows matched by the header's report_number (positional parsing like
  `cashCountHistoryPrepare`; never `...LoadAndRepair`), cash difference postings. Revenue hidden -> denied.
- `get_stock_count {date?}`: latest count session (`regulering` rows by `tidspkt` date): per item and warehouse/variant (`warehouse`, `variant_id`) expected,
  counted, difference, value difference (x `kostpris`), posted or not; plus FEFO: batches expiring within the
  warning days (`fefo.php` rules).
- `check_master_data {}` + routine `master_data_hygiene` (weekly): duplicate parties (same CVR, or normalised
  name; grouped normalised keys with HAVING COUNT > 1, avoiding a correlated self-join), debtor customers
  missing CVR (B2B groups only: DG box8 'on'), customers/suppliers missing e-mail, parties with an invalid
  group, closed parties still referenced by open kladder/orders, products without cost price, products with
  salgspris < kostpris, closed products on open orders. Rows `{kind, label, screen}`.

---

## E8. chaty (C-slice, after the contract export)

Contract v9 additions (exact Danish):
```
Bankudtog: brugeren kan indsætte et kontoudtog (CSV eller camt.053) i chatten eller uploade det under Finans
-> Bankfil til kassekladde. Brug import_bank_statement, list_bank_lines og propose_bank_entries. Forslag med
usikker modpost tager du kun med, hvis brugeren beder om det.
"Tjek kladden" og lignende: kald check_journal og forklar fejl før advarsler.
Udligning: suggest_settlements og propose_settlement; kun præcise sæt, der giver 0,00.
Leverandørfakturaer mod ordrer: match_purchase_order, derefter propose_purchase_invoice_stage. Du modtager
aldrig varer og bogfører aldrig; brugeren trykker selv Modtag og Bogfør.
Tilbud og ordrer: propose_sales_order. Kreditnotaer: propose_credit_note. Intet faktureres eller sendes.
Beskeder til kunder (rykkertekst, afdragsordning, kontoudtog): hent get_customer_statement og skriv teksten
på dansk, klar til at kopiere; du sender den aldrig.
Analyse: compare_periods, get_year_end_pack, get_till_summary, get_stock_count, check_master_data.
```
Honesty: draft nouns `tilbud, tilbuddet, ordren, kreditnota, kreditnotaen, udligning, udligningen,
modtagelse`; completion verbs add `udlignet, faktureret, krediteret, modtaget`; receipt kinds for each new kind
(a settlement receipt never licenses "betalt"). Eval: >= 15 cases `companion2-*`.

## E9. Tests and gate
As SPEC-companion C13. Parser fixtures per bank layout + camt.053; matcher cases; settlement apply/undo exactness;
invoice-stage snapshot/undo; sales/credit-note inserts vs legacy columns; insight tools against seeded data;
node test for the kassekladde check panel. Zero skips; Opus + Sol review; live eval + browser (bank file ->
proposal -> approve -> kladde lines; Tjek kladden panel; settlement approve/undo).
