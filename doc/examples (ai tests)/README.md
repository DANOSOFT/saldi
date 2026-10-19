# doc/examples

Worked examples that back the conventions in [`doc/ai/`](../ai/). Where the
convention files explain a rule in prose, the files here are **real code you
open in the IDE** to see the tool behaviour first-hand and to catch it if the
tooling ever changes.

## `intelephense_var_provenance.php`

Bench for the [`@var` provenance convention](../ai/convention_global_variable_provenance.md).
Open it in VS Code and compare Intelephense's *"Undefined variable"* squiggles
against the `EXPECT: clean|squiggle` note on each scenario.

It covers, each with its own variable name so annotations don't bleed between
scenarios:

- **F1–F4** — file scope: top-of-file block (clean), no annotation (squiggle),
  mid-code placement (clean — position doesn't matter), immediately-before-use
  (clean).
- **MA–MD** — mid-code matrix proving position/what-follows/single-vs-multi-line
  are all irrelevant (all clean).
- **MP** — the *malformed* one-liner (prose + a `:`-path crammed before `@var`)
  that Intelephense can't parse (squiggle). This — not placement — was the real
  cause of the original "after-include" failure.
- **G1–G4** — function scope: top-of-body (clean), before-use (clean), control
  (squiggle), and scope isolation — a file-scope block failing to reach into a
  function body (squiggle).
- **L1–L2** — the not-injected local counter (the `pbs_import.php` `$komma`
  trap): a `$x++` used before init is a *real* bug to fix with `$x = 0`, not a
  blind spot to annotate.

**Invariant:** the file should show **exactly 5** "Undefined variable" errors
(F2, G3, G4, L1, MP). If that count changes, Intelephense's behaviour has
changed — re-check the convention's *Placement* section against the bench.

**What the bench established (2026-07-22):** placement within a scope is free; a
well-formed `@var` block is honoured wherever it sits. Only two things matter — a
well-formed docblock (MP) and scope boundaries (G4).

### Notes for maintainers

- The file is **not wired into the app** and is never `include`d; it only ever
  runs under static analysis.
- Its functions are namespaced (`Doc\Examples\VarProvenance`) so they don't
  pollute workspace symbol search (`Ctrl+T`).
- **Do not** add this folder to `intelephense.files.exclude`. That was tried
  (2026-07-21): exclusion suppresses diagnostics *even when the file is open*,
  which defeats the bench. It isn't needed anyway — Intelephense only reports
  "Undefined variable" diagnostics for open files, so the bench never clutters
  the workspace Problems panel.
