---
name: convention_curly_braces
description: "Require curly braces for if/elseif/else/for/foreach/while/do-while whenever the body isn't on the same line"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-07-13-curly-braces
---

For `if`/`elseif`/`else`, `for`, `foreach`, `while`, and `do-while`: curly braces are required whenever the body is not on the same line as the control statement. A body on the same line may omit braces.

Acceptable:
```php
if ($i == 1) echo "XX";

if ($i == 1) {
	echo "XX";
}
```

Not acceptable:
```php
if ($i == 1)
	echo "XX";
```

**Why:** A brace-less body on its own line is a common source of bugs — a later edit that adds a second statement assuming it's still inside the block silently falls outside it instead. Requiring braces whenever the body isn't inline removes that trap.

**How to apply:** Prospective-only, plus the same enclosing-block scope as [Whitespace and indentation](feedback_whitespace_and_indentation.md) / [Include paths](convention_include_paths.md): apply to new code, and to any brace-less multi-line control structure inside a block you're already editing. Don't sweep the rest of the file just to add braces.
