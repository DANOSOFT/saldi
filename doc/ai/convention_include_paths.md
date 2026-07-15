---
name: convention_include_paths
description: "Use __DIR__ for include/require paths so they resolve the same regardless of caller"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-07-13-include-paths
---

When writing a new `include`/`require`/`include_once`/`require_once` statement, use `__DIR__ . '/relative/path/to/file.php'` instead of a bare relative path like `"../includes/file.php"`.

**Why:** A bare relative include path resolves against the calling process's working-directory context, not necessarily the including file's own directory — the same include can silently break depending on which script pulled it in. `includes/opdat_4.3.php:40` already does this correctly (`include_once(__DIR__ . "/opdat_func/opdat_func.php")`), while older files like `includes/oioublfunk.php:47` still use a bare relative path that only works because of where it happens to be called from.

**How to apply:** Prospective-only — don't do a repo-wide sweep converting existing bare relative includes. New include/require statements always use `__DIR__`. The one exception: if you're already editing the enclosing function/block that contains an existing bare relative include (see [Whitespace and indentation](feedback_whitespace_and_indentation.md) for the same enclosing-block scope rule), upgrade that include to use `__DIR__` too. Otherwise leave legacy includes untouched.
