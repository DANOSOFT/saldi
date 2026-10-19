---
name: convention_controller_view_separation
description: "Separate request handling (GET/POST + DB writes) from rendering; migrate legacy page scripts toward it opportunistically"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-07-20-controller-view-separation
---

A page script has three concerns that should stay separated: the **controller** (read the request, decide what to do, mutate the DB), the **view** (produce HTML), and the DB/model calls in between. Do all request processing and DB mutation *first*, then render. For a POST that changes state, redirect before rendering (**Post/Redirect/Get**) so a browser refresh cannot re-submit it. This is the standard controller-vs-view split of MVC applied to our flat PHP pages.

Large legacy scripts — `debitor/ordre.php` is the worst case, but `kreditor/`, `finans/`, and `produktion/ordre.php` share the shape — currently braid these together: `db_modify` calls interleaved among hundreds of `print` statements, request data read via `$_POST`/`$_GET` deep inside the file, and shared state passed through `global` rather than parameters.

**Why:** Interleaved logic and output can't be tested without rendering, and mutation that happens after output has already started can't be cleanly rolled back or redirected — which is also how refresh-resubmits duplicate invoices/credit notes. Reading the request in one place (instead of scattered superglobal access) and passing state as parameters (instead of `global`) is what makes a block extractable into a named, testable function.

**How to apply:** Prospective and opportunistic — the *Boy Scout rule* ("leave it cleaner than you found it"), not a big-bang rewrite. Scope each change to the block/function you are already editing (same enclosing-scope rule as [Include paths](convention_include_paths.md) and [Whitespace and indentation](feedback_whitespace_and_indentation.md)); do not sweep a whole file. When you touch such code:

1. **Read request data once, near the top**, into a variable/array. New code reads from that — not from `$_POST`/`$_GET`/`$_REQUEST` directly further down.
2. **Put new logic in a named function** that takes its inputs as parameters. Do not add new `global` declarations to reach shared state.
3. **No new `db_modify`/insert/update/delete after the first `print`/`echo`.** Keep all mutation in the controller phase, above any output; prefer redirecting after a state-changing POST.
4. **When you edit an existing action branch, extract it** into a named handler (`saveOrder()`, `creditOrder()`, `doInvoice()`, …) rather than growing it in place.

Pair with a characterization test for the page before relying on any extraction being behaviour-preserving — the code books real money, so an unverified change is a regression risk. See also [SQL escaping](convention_sql_escaping.md) for handling the request values you marshal in rule 1.
