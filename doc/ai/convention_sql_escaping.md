---
name: convention_sql_escaping
description: "Cast/escape any externally-sourced value before interpolating it into a SQL query string"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-07-13-sql-escaping
---

Any value that traces back to external input (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, or anything else not fully controlled by the application) must not be interpolated raw into a `$qtxt`/SQL string. Cast it with `intval()`/`floatval()` when it's meant to be numeric, or run it through `db_escape_string()` and wrap it in quotes when it's a string.

**Why:** `includes/db_query.php`'s `injecttjek()` — called from both `db_modify()` and `db_select()` — is not a substitute for this. It only scans the finished query for an unescaped semicolon outside a quoted string (guarding against stacked-query injection like `'; DROP TABLE ...`), an attack shape that `mysqli_query()`/`pg_query()` mostly can't even execute since they run a single statement. It does nothing for `OR 1=1`, `UNION SELECT`, or other injection that never needs a semicolon.

Concrete example fixed in this codebase: `kreditor/betalinger.php` used to build `... and kladde_id = $_GET[kladde_id] order by forfaldsdate` with the raw GET value. A request like `?kladde_id=0 OR 1=1` produces a query with no semicolon at all — `injecttjek()` never triggers — and silently returns every row instead of just the intended `kladde_id`'s. The fix was `intval($_GET["kladde_id"])` before interpolating it.

**How to apply:** Prospective-only, plus the enclosing-block scope from [Whitespace and indentation](feedback_whitespace_and_indentation.md) / [Include paths](convention_include_paths.md): when writing a new query or editing a function that builds one, check every interpolated variable that isn't a fixed literal — numeric values get `intval()`/`floatval()`, string values get `db_escape_string()` plus quotes. Don't skip this because `injecttjek()` is already called; it catches a different, narrower attack shape.
