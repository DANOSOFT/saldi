---
name: convention_ifset
description: "Use ifset($arrayOrVar, $key, $default) for new code; migrate if_isset() calls to ifset() opportunistically, never as a sweep"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-09-09-ifset-convention
---

`ifset($arrayOrVar, $key = null, $default = null)` in `includes/std_func.php` is the canonical null-safe lookup. `if_isset($arrayOrVar, $default = null, $key = null)` is the legacy name with `$default` and `$key` in the wrong order; it now delegates to `ifset()` and stays as a thin wrapper so existing callers keep working.

Call forms:

```php
ifset($var)                      // $var if set, else null
ifset($array, 'key')             // $array['key'] if the key exists, else null
ifset($array, 'key', $default)   // $array['key'] if the key exists, else $default
ifset($var, null, $default)      // $var if set, else $default (no key lookup)
ifset($array, ['a', 'b'], $d)    // nested $array['a']['b'], else $d
```

Integer keys work the same as string keys (`ifset($parts, 3, 0)`). Key lookups use `array_key_exists`, so `0`, `''` and `false` are returned as-is; only a missing key or an unset variable yields the default.

Migration table for existing calls:

| Legacy | Replacement |
|---|---|
| `if_isset($x)` | `ifset($x)` |
| `if_isset($x, $d)` | `ifset($x, null, $d)` |
| `if_isset($arr, $d, $k)` | `ifset($arr, $k, $d)` |
| `if_isset($arr['k'], $d)` | `ifset($arr, 'k', $d)` |

The last row matters most: passing `$arr['k']` by value emits "Undefined array key" under PHP 8 when the key is missing, and the wrapper cannot prevent that. Moving the key into the argument list is the fix.

**Why:** The argument order of `if_isset()` was a mistake in the original function, but with roughly 4,700 call sites across 300+ files it cannot be corrected in one change without breaking the codebase. A new name with the right order lets the code migrate gradually and lets a reviewer tell at a glance which order a call uses. The two names are *not* interchangeable by rename: `if_isset($a, $b)` is a variable check with default `$b`, while `ifset($a, $b)` is a key lookup for `$b`. A mechanical search-and-replace would silently change behaviour.

**How to apply:** Prospective plus opportunistic, same scope rule as [Include paths](convention_include_paths.md) and [Whitespace and indentation](feedback_whitespace_and_indentation.md):

- New code always uses `ifset()`. Never write a new `if_isset()` call.
- When already editing a function or block that contains `if_isset()` calls, convert those calls to `ifset()` using the table above, swapping the arguments, and mention the conversion in the file's history line. Leave `if_isset()` calls elsewhere in the file untouched.
- Do not run a repo-wide sweep, and do not remove or change the signature of `if_isset()`.
- Tests that exercise code calling either function should `require_once(__DIR__ . '/../includes/std_func.php')` (it has no side effects) instead of stubbing the function; a stub is easy to get wrong in exactly the argument-order way described above.
- `includes/topmenu/std_func.php` is an unused copy that only defines `if_isset()`; do not add `ifset()` there or rely on it.
