# Database constants

This folder holds named integer and string constants for fixed database codes,
flags, and positions within encoded values. Each class groups one domain's
definitions, with PHPDoc explaining their database mapping and meaning.

## Usage

Load the relevant class with an explicit `__DIR__` path, then pass its constants
directly to the code that consumes the stored representation.

For example, from a page in `sager/`:

```php
require_once(__DIR__ . '/../includes/constants/SagerPermission.php');

$canApprove = (bool) substr((string) $sag_rettigheder, SagerPermission::APPROVE_PIECEWORK, 1);
```

`APPROVE_PIECEWORK` is the integer offset `6`, meaning the seventh character of
the Sager permissions string. [SagerPermission.php](SagerPermission.php) documents
all eight positions, their Danish and English labels, and where they are configured.

## Adding definitions

Follow the [database constants convention](../../doc/ai/convention_database_constants.md)
for class naming, PHPDoc, bilingual labels, and preserving stored values.
