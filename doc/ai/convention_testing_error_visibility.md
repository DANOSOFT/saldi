---
name: convention_testing_error_visibility
description: "When testing/debugging, don't suppress PHP errors to make a problem vanish; fix or surface the warnings your change introduces"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-07-20-testing-error-visibility
---

When testing or debugging, the goal is to *see* what your change does — including warnings and notices, not just fatals. Do not silence a PHP error to make a problem disappear. A warning your change introduces is a latent bug: many PHP 8 warnings were notices in older versions and are on track to become fatal, so suppressing one only defers a crash to a future upgrade.

**Scope — this is about the warnings *your change* adds, not the legacy floor.** The codebase already emits many warnings/notices (undefined array keys, deprecations, etc.); you are not expected to clean all of those up, and running full `E_ALL` against the whole app is impractical because that noise drowns the signal. Judge against what your diff introduces into the code paths you touch.

**Why:** Suppression turns a visible, cheap-to-fix warning into an invisible bug that resurfaces as a fatal after a PHP upgrade or in a slightly different code path. Keeping errors visible while you work is also faster than reconstructing failures from log files that churn constantly (which is exactly why some developers enable `display_errors` locally).

**How to apply:**

1. **Don't hide warnings in committed code.** No `@` error-suppression operator, and no `error_reporting()`-lowering added just to quiet a warning your change triggers. Fix the cause instead.
2. **Local error-visibility tweaks are a personal debugging aid, not a fix.** Enabling `display_errors` (or narrowing `error_reporting`) in your own `connect.php` is fine — `connect.php` is per-install and not committed — but it must not be how a warning gets "resolved" in a review; the warning itself still needs fixing.
3. **Exercise the path with warnings visible.** When you touch a code path, run it once with warnings on and clear any new ones your change caused before opening the PR.
4. **CI enforcement is a future goal, currently off on purpose.** `phpunit.xml` sets `failOnWarning="false"` deliberately, because full `E_ALL` would flood on pre-existing legacy warnings. Do **not** flip it globally as a quick win; when it is eventually turned on, scope it to new/changed code so it catches regressions without drowning in legacy noise.

Related: [Global variable provenance](convention_global_variable_provenance.md) (the Intelephense-editor side of the same topic) and [Controller/view separation](convention_controller_view_separation.md).
