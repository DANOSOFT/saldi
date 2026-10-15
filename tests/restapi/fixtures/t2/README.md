# Tier 2 fixtures

20261001 CDX/LH — T2-B0.

Feature slices add non-secret JSON response fixtures here. X1 compares them with PHP responses; A1 validates the same files with zod. Money uses decimal strings with exactly two fractional digits, quantities up to three, dates YYYY-MM-DD and instants ISO 8601 UTC. Fixtures carry the complete `{success,message,data}` envelope unless explicitly named as a domain item.

Golden accounting rows belong in `golden/` (G1–G10, T2 section 8). Credentials come from the environment/ignored connect.php; seeded user passwords come from `TestCredentials`. Tests use disposable tenants and local stubs/Mailpit only.

`MobileT2FoundationTest.php` provisions its own fresh clone and registry schema, exercises append-only migrations, replay rollback and scope, legacy context restoration, permissions/capability fragments, twenty parallel settlement allocations and SQL instant comparisons across the 2026-10-25 DST change. The foundation does not extract or switch an accounting engine.
