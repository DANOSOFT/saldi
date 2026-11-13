# Characterization tests

Pin the CURRENT behavior of legacy code so refactors (SD-599/SD-600 and the
E-epic decompositions) can prove they changed nothing. Two kinds live here:

1. **Pure-function suites** (e.g. `includes/stdFunc/`) — no dependencies,
   run everywhere.
2. **DB-backed suites** (SD-601: `finans/` kassekladde posting and
   `includes/ordrefunc/` order→invoice) — exercise the real posting engine
   against a real PostgreSQL tenant inside the repo's docker-compose stack.
   On a machine without the stack they **skip cleanly**, so `composer test`
   stays green on a bare checkout.

## Running the DB-backed suites

Use an installed test master and a PostgreSQL role with `CREATEDB` access:

```bash
docker compose exec -T -w /var/www/html/saldi web php vendor/bin/phpunit --testsuite characterization
```

Set `SALDI_CHAR_PGHOST/PGUSER/PGPASS/MASTER_DB` for a host installation. No
`SALDI_CHAR_TEMPLATE_DB`, fixed test database, or prepared SQL fixture is needed.
See [database ownership](../README.md#database-ownership) for retention and
extension requirements.

`CharacterizationEnv::bootstrapTenant(static::class)` installs a fresh tenant
through the shared `TestDatabase` helper. Its UUID-derived session reaches the
classic pages, and class teardown removes only that class's database and exact
routing rows. `SALDI_TEST_KEEP_DATABASES=1` retains every group, with the UUID
and database name printed before creation. Tenant contents and settings never
come from an existing account. SQL-only suites explicitly share one empty
helper-owned database through private schemas or temporary tables.

Child runners still exercise the production posting, delivery and invoice
functions; tests assert on their database state.

## What is pinned (SD-601)

- Balanced two-account voucher: simulated posting writes balanced
  `simulering` rows, marks the kladde `S`, leaves `transaktioner` untouched.
- VAT voucher: gross 1250 on an S-coded account splits into net 1000 +
  250 VAT on the VAT account from the `SM` group.
- Real posting: balanced `transaktioner` rows, kladde marked `V`,
  `kontoplan.saldo` moves by the posted amount on both accounts.
- Unbalanced voucher: nothing reaches `simulering`/`transaktioner`.
- Order→invoice: `levering`+`bogfor` return OK, a sequential `fakturanr` is
  assigned, status ends at 4 (the re-invoice guard only requires >2),
  order sum/VAT unchanged, pending delivery quantity consumed.
- Re-invoicing an invoiced order is refused ("invoice allready created…",
  wording pinned) and the invoice number does not change.

Verified 2026-07-23: all suites green in the container; deliberately breaking
the simulate dispatch (posting for real) fails 2 tests.

<!-- 20260723 CL/LH SD-601: created. -->
<!-- 20261022 CL/NTR Restored after the master merge emptied it; pure-suite example now includes/stdFunc/ (includes/usdecimal/ was removed). -->

<!-- 20261111 CDX/NTR Document fresh per-group databases and UUID retention. -->
