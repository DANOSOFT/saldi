---
name: convention_ifset
description: "Use ifset($arrayOrVar, $key, $default) for array/object key lookups; use $var ?? $default for a plain-variable check (if_isset/ifset warn on an undefined variable); if_array() for an array-shaped default; migrate opportunistically, never as a sweep"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-09-09-ifset-convention
---

`ifset($arrayOrVar, $key = null, $default = null)` in `includes/std_func.php` is the canonical null-safe lookup. `if_isset($arrayOrVar, $default = null, $key = null)` is the legacy name with `$default` and `$key` in the wrong order; it now delegates to `ifset()` and stays as a thin wrapper so existing callers keep working - and stays only for legacy callers (new plain-variable checks use `??`, see below). `if_array($arrayOrVar, $key = null)` is a third wrapper for when the value should always come back as an array.

Call forms:

```php
ifset($var)                      // $var if set, else null
ifset($array, 'key')             // $array['key'] if the key exists, else null
ifset($array, 'key', $default)   // $array['key'] if the key exists, else $default
ifset($var, null, $default)      // $var if set, else $default (no key lookup)
ifset($array, ['a', 'b'], $d)    // nested $array['a']['b'], else $d
ifset($array, 'key', fn() => expensive())  // $default may be a zero-arg Closure, called lazily
                                            // only when the value is actually missing (20260923)

$var ?? $default                 // $var if set and not null, else $default - the form for plain-variable checks

if_array($array, 'key')          // $array['key'] if it exists, else array() - same lookup rules as
                                  // ifset(), incl. the nested-key array form, but never returns null
```

Integer keys work the same as string keys (`ifset($parts, 3, 0)`). Key lookups use `array_key_exists`, so `0`, `''` and `false` are returned as-is; only a missing key or an unset variable yields the default.

**Plain-variable checks use `??`, not `ifset()`/`if_isset()`:** both functions take `$arrayOrVar` by value, so PHP evaluates the argument *before* the call. If the variable was never defined, `if_isset($id, 0)` and `ifset($id, null, 0)` both still emit "Warning: Undefined variable $id" (verified on PHP 8) - the function cannot suppress it, it only supplies the default. `??` is a language construct that checks without reading, so it does not warn:

```php
$id = $id ?? 0;              // preferred: plain-variable check, no warning if $id is undefined
$id = if_isset($id, 0);      // legacy: warns if $id is undefined
$id = ifset($id, null, 0);   // same problem, plus the pointless null key argument
```

The same applies to array elements and properties: `$arr['k'] ?? $d` is warning-free, whereas `if_isset($arr['k'], $d)` warns on a missing key. `??` and `isset()` semantics match what `ifset()` does (a `null` value counts as missing; `0`, `''`, `false` are returned as-is).

`ifset()` remains the right tool where `??` cannot express the lookup: a key lookup on an array/object *parameter that is known to exist* when you want the nested-key array form, an object-property lookup by dynamic name, or a lazily-built (Closure) default. For a simple `$arr['key']` with a default, `$arr['key'] ?? $default` is equally fine and is not worth converting either way.

Migration table for existing `if_isset()` calls being converted to `ifset()`/`if_array()`:

| Legacy | Replacement |
|---|---|
| `if_isset($x)` | `ifset($x)` |
| `if_isset($x, $d)` | `$x ?? $d` (only when already editing the block; legacy form still works) |
| `if_isset($arr, $d, $k)` | `ifset($arr, $k, $d)` |
| `if_isset($arr['k'], $d)` | `ifset($arr, 'k', $d)` or `$arr['k'] ?? $d` |
| `if_isset($arr, array(), $k)` | `if_array($arr, $k)` |

The third row matters most: passing `$arr['k']` by value emits "Undefined array key" under PHP 8 when the key is missing, and the wrapper cannot prevent that. Moving the key into the argument list is the fix.

**`if_array()`** exists for the common "I only ever want an array back" case, so callers don't have to spell out `ifset($arr, $key, fn() => array())` or tack on `?? array()` afterwards. It always builds a fresh `array()` (never shared by reference across calls) when the value is missing, and returns the value as-is (even if it isn't actually an array) when it is set - it does not cast or validate, only supplies the default.

**Why:** The argument order of `if_isset()` was a mistake in the original function, but with roughly 4,700 call sites across 300+ files it cannot be corrected in one change without breaking the codebase. A new name with the right order lets the code migrate gradually and lets a reviewer tell at a glance which order a call uses. The two names are *not* interchangeable by rename: `if_isset($a, $b)` is a variable check with default `$b`, while `ifset($a, $b)` is a key lookup for `$b`. A mechanical search-and-replace would silently change behaviour. Plain-variable checks go to `??` (changed 20261006) because neither function can avoid the undefined-variable warning, and `??` is shorter than both. `if_array()` was added (20260923) after a report page crashed on `count(null)` when a POSTed array field was entirely absent (`debitor/rapport.php`, no accounts shown, so no `konto_id[]` fields were submitted) - `if_array($_POST, 'konto_id')` reads more clearly at the call site than a defensive `is_array(...) ? ... : array()` ternary and generalizes the fix.

**How to apply:** Prospective plus opportunistic, same scope rule as [Include paths](convention_include_paths.md) and [Whitespace and indentation](feedback_whitespace_and_indentation.md):

- New code uses `$var ?? $default` for a plain-variable check, `ifset()` for array/object key lookups that need it (nested keys, no default, Closure default, object property), and `if_array()` when the result should always be an array. Do not write a new `ifset($var, null, $default)` or `if_isset($var, $default)` call for a plain variable. Never write a new `if_isset($arr, $default, $key)` three-argument key-lookup call - that argument order is the one easy to get backwards.
- When already editing a function or block that contains `if_isset()` calls in the three-argument key-lookup form, convert those calls to `ifset()` (or `if_array()` when the default is `array()`) using the table above, swapping the arguments, and mention the conversion in the file's history line. Plain-variable `if_isset($x, $d)` calls in that block may become `$x ?? $d`. Leave `if_isset()` calls elsewhere in the file untouched.
- Do not run a repo-wide sweep, and do not remove or change the signature of `if_isset()`.
- Tests that exercise code calling either function should `require_once(__DIR__ . '/../includes/std_func.php')` (it has no side effects) instead of stubbing the function; a stub is easy to get wrong in exactly the argument-order way described above.
- `includes/topmenu/std_func.php` is an unused copy that only defines `if_isset()`; do not add `ifset()` or `if_array()` there or rely on it.
