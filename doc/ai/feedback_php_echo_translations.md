---
name: feedback_php_echo_translations
description: "Do not remove <?= 'string' ?> short echo tags — they are placeholders for future translation calls"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 028ca4b1-6bd4-4e2d-b690-7fb233c7c56f
---

Do not remove `<?= 'some string' ?>` (short echo tags containing just a plain string literal). These are intentional placeholders that will be wrapped in a translation function later in development.

**Why:** The user is building out translation support incrementally; bare string echoes mark spots that need to be translated.

**How to apply:** When reviewing, refactoring, or simplifying PHP files, leave these short-echo string literals untouched even if they look like they could be inlined or removed.
