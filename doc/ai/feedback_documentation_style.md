---
name: feedback_documentation_style
description: "User's preferred documentation style for PHP functions and API responses"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 00580750-bc1b-4bc6-a248-4fd15935958e
---

Use PHPDoc blocks with `@return` and nested array shape notation for documenting functions and API response structures. Not plain `//` line comments.

**Why:** User explicitly prefers the structured PHPDoc `@return array{ ... }` style with inline type + description on each field over flat `// type  field  description` line comments.

**How to apply:** When documenting any PHP function or API response shape, use the format:
```php
/**
 * Short description.
 *
 * @return array{
 *   field: type,   Description of field.
 *   nested: array{
 *     subfield: type,  Description.
 *   },
 *   nullable: type|null,  Description, or null if X.
 * }
 */
```
