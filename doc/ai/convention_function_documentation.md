---
name: convention_function_documentation
description: "Document a function once with a PHPDoc at its definition; at a call site add only a short provenance note when it arrives indirectly through an include"
metadata:
  node_type: memory
  type: project
---

Page scripts call helpers that arrive through an include chain (e.g. `jsString()` in `includes/stdFunc/jsString.php`, loaded by `includes/std_func.php`). Where the function is documented, and what a call site should say about it, follows one rule.

**Definition site:** every shared function gets its PHPDoc where it is defined (short description, `@param`, `@return`; array shapes as in [Documentation style](feedback_documentation_style.md)). That block is the single source of truth. Do **not** copy it, or a restatement of the parameters and return value, into the pages that call the function - the copy goes stale and the IDE already resolves the definition's PHPDoc through the include.

**Call site:** add a short note only when the function arrives indirectly and its origin is not obvious from reading the file, for example a helper that comes in via `std_func.php` rather than a local `include`. Keep it to the source and one line on how it is used here, and point at the definition with `@see`:

```php
/**
 * jsString() comes from includes/stdFunc/jsString.php, which std_func.php includes.
 *
 * @see jsString()
 */
```

Skip the note when the function is defined in the same file, when the include is visible in the file, or when the call is self-explanatory.

**Why:** It is the function counterpart of [Global variable provenance](convention_global_variable_provenance.md): a reader should be able to tell where something came from, but the description of what it does lives in one place.

**How to apply:** Prospective and opportunistic, like the other conventions - apply it to the file you are already editing, no repo-wide sweep.
