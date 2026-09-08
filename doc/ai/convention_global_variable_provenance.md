---
name: convention_global_variable_provenance
description: "Document include-injected variables with a top-of-file @var provenance docblock; use global only where it is actually required"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-07-20-global-variable-provenance
---

Page scripts rely on variables that are injected by an earlier `include` (e.g. `$db_type`, `$login`, `$font` from `includes/connect.php`) rather than defined locally. Intelephense analyses each file in isolation and cannot follow the include chain, so it reports these as "Undefined variable". For genuinely-injected variables this is a **pure static-analysis blind spot, not a runtime bug**: an `include` runs in the including scope, so at top level the variable is really defined by the time it is read, and no warning is emitted at runtime whatever `error_reporting` is set to. (`error_reporting`/`display_errors` only affect whether a *genuine* undefined access — a real bug — surfaces; those are per-install/developer settings, not a repo-wide guarantee, so don't reason about them here.)

When a file consumes variables it did not define, add a **provenance docblock** and, by convention, put it at the top of the file — right after the license/logo/revision-history header. Intelephense honours the block *anywhere* in the enclosing scope, so this position is a readability/provenance choice, not a tooling requirement (see **Placement** below). List the variables and name the source. Phrase the source line for whichever of the two directions applies:

**A — this file does the `include` itself** (it includes `connect.php` further down, then uses the variables). Name the include and say it is below:

```php
/**
 * Injected by ../includes/connect.php, included below:
 * @var string $db
 */
```

**B — this file is itself `include`d by an entry page** that already ran `connect.php`, so the variables arrive from the parent scope and there is no local `include` line. Name the ultimate source and how it reaches this file:

```php
/**
 * Injected by ../includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 * @var string $login
 * @var string $font
 */
```

Either way, keep the license/logo/history header at the very top and place the block immediately after it. The point is that the source line tells the reader *where the variable comes from*; keep that information truthful for the file you are annotating.

**Placement — what Intelephense actually requires.** Two things matter, and *position within the scope is not one of them*. **(1) The docblock must be well-formed:** put the `@var` tag on its own line (`* @var Type $x`), or as a bare one-liner `/** @var Type $x */`. Do **not** cram descriptive prose — especially text containing a `:` or a path — in front of the tag on the same line; Intelephense then fails to parse the tag and the variable stays flagged. This, not placement, is what made our first attempt fail: `/** Injected by ../includes/connect.php: @var string $db */` was silently ignored, and we initially (wrongly) blamed its position after the `include`. Keep the prose on separate ` *` lines above the tag, as in the examples. **(2) Scopes are walled off:** a `@var` block declares its variable for its *entire enclosing scope*, but only that scope — a file-scope block does **not** reach into a function body, and a function-body block covers that whole function and nothing outside it. Beyond those two rules a well-formed block is honoured *wherever it sits in the scope* — top of file, after the header, mid-code, glued to another statement, or immediately before the use all behave identically (verified 2026-07-22 across `debitor/pbs_import.php`, `kreditor/orderIncludes/moveOrderLines.php`, and the bench below). Practical upshot: for a file whose top-level code runs inside a function because it is `include`d there (rule 3), the block still goes in that *file* — Intelephense analyses it at file scope regardless of runtime reality.

A worked bench of every placement/scope case (file scope, mid-code, before-use, function-body, scope isolation, and the not-injected local counter) lives in [../examples/intelephense_var_provenance.php](../examples/intelephense_var_provenance.php) — open it in the IDE and the squiggles should match the `EXPECT` note on each scenario.

**Why:** This does two things a bare `global $x;` at top level does not: it records *where* the variable comes from (a `global` statement names no source), and it stays truthful — it documents an existing scope fact rather than adding a redundant, potentially misleading declaration to code that is already in global scope. It also quiets Intelephense without changing runtime behaviour, and it moves the same direction as [Controller/view separation](convention_controller_view_separation.md) (shrinking reliance on ambient globals) instead of entrenching it.

**How to apply:** Prospective and opportunistic — the Boy Scout rule, scoped to the file/block you are already editing (same enclosing-scope rule as [Include paths](convention_include_paths.md) and [Whitespace and indentation](feedback_whitespace_and_indentation.md)). No repo-wide sweep.

1. **Top-of-file `@var` provenance block** for variables injected from an include, naming the source file. This is the default *by style* — one place, next to the provenance note — not because other positions fail (they don't; see **Placement**).
2. **Keep the docblock well-formed** — the `@var` tag on its own line, with no prose or paths jammed in front of it on the same line (see **Placement**). If a variable is used only inside one function, annotate it *inside that function* (a file-scope block won't reach it); `/** @var Type $x */` immediately before the use works just as well as a block at the top of the function body — both are honoured, so choose by readability.
3. **`global` is only for where it is genuinely required** — inside a function body, and in files that are themselves `include`d *inside* a function (their top-level code then runs in that function's local scope, e.g. `orderIncludes/updateOrderCost.php` included within `ordreside()`). Do **not** add top-level `global` as decoration purely to silence the linter; use the `@var` block there. Conversely, do **not** silence an *in-function* undefined variable with a top-of-function-body `@var` block: mechanically it works (a function-body block is function-scoped), but a genuine function does not inherit its caller's locals at runtime, so the `@var` would mask a real undefined-variable bug rather than document a real scope fact. Use `global` there — it is both truthful and enough to clear the warning on its own (no `@var` needed unless you also want a type).
4. **Don't silence the diagnostic wholesale.** Leaving Intelephense's `undefinedVariables` check disabled makes the squiggles vanish but also hides genuine undefined-variable bugs — annotate provenance instead. (Keeping *runtime* errors visible while testing is a separate concern — see [Testing error visibility](convention_testing_error_visibility.md).)

Long-term, the cleanest fix is to stop leaking loose globals at all — e.g. have `connect.php` return a config object passed explicitly — but that is a large change; the `@var` provenance block is the low-cost step that works today.
