---
name: convention_database_constants
description: "Name fixed database codes, flags and encoded positions with documented constant classes in includes/constants/"
metadata:
  node_type: memory
  type: project
---

When abstracting fixed values that represent database concepts, define them as
public constants on a domain-specific final class in `includes/constants/`.
This includes status codes, type codes, flags, and positions within encoded
database values such as a stored permissions string. Name the file after the
class and use `UPPER_SNAKE_CASE` for its constants.

Use plain integer or string constants so existing PHP and SQL code can consume
the stored representation directly. Preserve the stored values and their
meaning when replacing literals with named constants.

Document the mapping with PHPDoc:

- On the class, identify the source table and column, the encoding, and where
  the values are configured when applicable.
- On each constant, explain its meaning and include its Danish and English
  permission or domain label on one line: `Dansk: … | English: …`.
- For positional values, state both the zero-based offset and the corresponding
  character or bit position. Record any existing differences between the label
  and the checks that use it.

For example, [SagerPermission](../../includes/constants/SagerPermission.php)
names the positions in `grupper.box2`, and callers use
`SagerPermission::APPROVE_PIECEWORK` directly as the `substr()` offset.

Apply this to new abstractions and to relevant values in code being changed;
keep adoption scoped to the feature being worked on.

Keep purpose and usage examples beside the classes in
[`includes/constants/README.md`](../../includes/constants/README.md), linking
back to this convention for the definition rules.
