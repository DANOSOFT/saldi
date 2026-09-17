---
name: feedback_whitespace_and_indentation
description: "Don't introduce incidental trailing-whitespace diffs; do fix indentation in the enclosing block/function you're already editing"
metadata:
  node_type: memory
  type: feedback
  originSessionId: 2026-07-13-whitespace-indentation
---

Two separate rules that can look contradictory but aren't:

1. **Never touch trailing whitespace on lines you aren't otherwise changing.** Don't let an edit tool add or strip trailing spaces on unrelated lines — that shows up as noisy, unrelated diff churn on every PR.
2. **Do fix indentation problems in the function/block you're already editing**, even on lines not otherwise touched by that specific change — including comments that sit at the same indent as the statement they're attached to but are actually meant to describe/sit inside a nested block (common in this codebase, since indentation discipline is inconsistent).

**Why:** Developers here often introduce accidental trailing-whitespace noise and inconsistent indentation (including comments left at the outer indent level instead of the nested level they belong to). Fixing indentation while already in a block cleans it up incrementally without a dedicated reformatting pass; but touching whitespace on code outside the current change adds noise unrelated to the actual edit and makes diffs harder to review.

**How to apply:** When editing any line inside a function or block, it's in scope to also correct that whole function/block's indentation and comment placement. Do not extend the fix beyond the enclosing function/block — leave other functions/blocks in the same file untouched unless they're independently part of the change. Never let edits add/remove trailing whitespace on lines outside the scope of the current change.
