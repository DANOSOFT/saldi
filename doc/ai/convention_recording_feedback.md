---
name: convention_recording_feedback
description: "Keep shared conventions and feedback under doc/ai/, with MEMORY.md as the index"
metadata:
  node_type: memory
  type: project
---

`AGENTS.md` is the entry point for repository instructions. Durable project
conventions and feedback belong in the topic files under `doc/ai/`, linked from
`doc/ai/MEMORY.md`. Update the existing topic when it already covers the rule;
otherwise add a focused topic file and a short entry in the index.

When corrected, fix the mistake and record the durable rule in its owning topic.
If the rule is already recorded and only review enforces it, the correction is a
repeat: enforce it in the same change. Prefer making the mistake impossible to
write through one owner, one implementation, or removal of a misleading copy.
Otherwise add a check in `tools/guardrails/checks/` and prove it fails on the commit
that made the mistake.

Keep enforcement details with the convention or in the
[rules and enforcement catalog](../../tools/guardrails/README.md#rules-and-enforcement).
Do not copy individual rules into `AGENTS.md`. Remove obsolete catalog entries
once their mistake can no longer happen; function-specific guarantees belong
with the implementation and its regression tests.
