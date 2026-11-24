---
name: feedback_whitespace_and_indentation
description: "Allow blank separators and local indentation fixes; reject incidental whitespace-only rewrites"
metadata:
  node_type: memory
  type: feedback
  originSessionId: 2026-07-13-whitespace-indentation
---

Apply these rules to the block being edited:

1. **Never touch trailing whitespace on nonblank lines you aren't otherwise changing.** Don't let an edit tool add or strip trailing spaces after code or comments on unrelated lines — that shows up as noisy, unrelated diff churn on every PR.
2. **Do fix indentation problems in the function/block you're already editing**, even on lines not otherwise touched by that specific change — including comments that sit at the same indent as the statement they're attached to but are actually meant to describe/sit inside a nested block (common in this codebase, since indentation discipline is inconsistent).
3. **Keep breathing room between sections of code.** New blank separator lines may be empty or contain spaces/tabs for indentation, and separators may be removed. Adjust existing blank-line indentation alongside content edits in the same diff hunk. Standalone rewrites of existing blank lines, such as file-wide trim-on-save changes, are incidental whitespace churn.

**Why:** Developers here often introduce accidental trailing-whitespace noise and inconsistent indentation (including comments left at the outer indent level instead of the nested level they belong to). Fixing indentation while already in a block cleans it up incrementally without a dedicated reformatting pass; but touching whitespace on code outside the current change adds noise unrelated to the actual edit and makes diffs harder to review.

**How to apply:** When editing any line inside a function or block, it's in scope to also correct that whole function/block's indentation and comment placement. Do not extend the fix beyond the enclosing function/block — leave other functions/blocks in the same file untouched unless they're independently part of the change. Never let edits add/remove trailing whitespace after content on lines outside the scope of the current change. Leave useful blank separators in place.

**Enforced by:** `tools/guardrails` check `whitespace` (incidental trailing-whitespace or line-ending-only rewrites, new trailing whitespace after content, file mode changes). Added/removed blank separators, including indentation, are allowed. Existing blank-line indentation may change when the same diff hunk changes content; hunks that only rewrite existing lines' trailing whitespace are rejected. Re-indenting the enclosing block stays a review call.
