---
name: feedback_pre_pr_test_report
description: "Proactively surface a regression-impact test report before a draft PR becomes a proper PR"
metadata:
  node_type: memory
  type: feedback
  originSessionId: 2026-07-13-pre-pr-test-report
---

When a change looks functionally complete — before suggesting a draft PR be turned into a proper/ready-for-review PR — proactively produce a "what to test" report, without waiting to be asked.

The report lists functions/files plausibly affected by the change: callers of the changed function(s), functions it calls that behave differently now, and anything sharing state with it (globals, DB tables/columns touched, shared includes). For each item, give a concrete manual test scenario or input to try — not just a name — so the report is directly actionable.

**Why:** The team wants better testing discipline before deploying PRs. Surfacing likely regression surface area before a PR is marked ready catches problems while context is still fresh, instead of after review or in production.

**How to apply:** Near the end of implementing a change — once the core diff looks stable, and before proposing to move a draft PR to ready — generate this impact report as part of wrapping up, even unprompted. Keep it scoped to things plausibly affected by the actual diff; don't pad it with unrelated code just to seem thorough.
