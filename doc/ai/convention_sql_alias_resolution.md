---
name: convention_sql_alias_resolution
description: "ORDER BY/GROUP BY alias resolution differs between mysqli and Postgres when an alias name clashes with a real column; repeat the expression instead of relying on the bare alias"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-07-17-findboxsale-alias
---

`includes/db_query.php` runs against either mysqli or Postgres depending on `$db_type`, and the two engines don't agree on how to resolve an `ORDER BY`/`GROUP BY` name that's ambiguous — i.e. it matches both a `SELECT`-list alias and a real column from a joined table.

- `WHERE` — aliases never work, on either engine (filtering happens before the `SELECT` list is computed).
- `ORDER BY` — both engines resolve an ambiguous name to the **alias**.
- `GROUP BY` — MySQL also prefers the **alias**, but Postgres does the opposite and prefers the **input column** (documented Postgres behavior, kept that way for SQL-standard compatibility). Same bare name, different grouping result depending on backend.
- `HAVING` — MySQL documents alias support as an extension; don't assume Postgres extends the same courtesy without testing it directly.

Concrete shape that triggers this: `debitor/pos_ordre_includes/boxCountMethods/findBoxSale.php` selects `COALESCE(pos_betalinger.betalingstype, ordrer.felt_1) as betalingstype` from a `left join`, then needs to order by that coalesced value rather than the raw (possibly-NULL) `pos_betalinger.betalingstype`. `order by betalingstype` is correct and portable here. The same pattern reused in a `group by` would silently diverge across backends.

**Why:** this only surfaces when the alias name collides with an actual column in one of the joined tables — easy to miss in review since the query looks identical and works fine under whichever backend is being tested, while quietly grouping wrong on the other.

**How to apply:** for `ORDER BY`, a bare alias is fine and portable. For `GROUP BY`, don't rely on the bare alias when it could collide with a real input column — repeat the full expression instead (`group by COALESCE(pos_betalinger.betalingstype, ordrer.felt_1)`), which is unambiguous and behaves identically on both engines. Don't use ordinal position (`group by 2`) as the fix — it sidesteps the ambiguity but is fragile against later column reordering in the `SELECT` list.
