# Declaring table columns

Load `includes/database/ensureTable.php` from an install/update path or a feature
builder under `includes/structure/`. Declare each group with a `DB_ENSURE`
object and one of its type constants:

```php
require_once __DIR__ . '/../database/ensureTable.php';

db_ensure_table('loen_enheder', array(
    new DB_ENSURE(DB_ENSURE::FLOAT3, array(
        'procent', 'op_30', 'ned_30', 'op_70', 'ned_70',
        'op_100', 'ned_100', 'op_160', 'ned_160'
    )),
));
```

The second argument is **one array of declarations**. Several types can be
declared together:

```php
db_ensure_table('example', array(
    new DB_ENSURE(DB_ENSURE::INTEGER, array('employee_id', 'quantity')),
    new DB_ENSURE(DB_ENSURE::BIGINT, array('external_id')),
    new DB_ENSURE(DB_ENSURE::FLOAT3, array('amount', 'percentage')),
    new DB_ENSURE(DB_ENSURE::TEXT, array('description')),
    new DB_ENSURE(DB_ENSURE::BOOLEAN, array('enabled')),
));
```

| Constant | PostgreSQL | MySQL / MariaDB |
| --- | --- | --- |
| `DB_ENSURE::INTEGER` | signed `integer` (32 bits) | signed `integer` (32 bits) |
| `DB_ENSURE::BIGINT` | signed `bigint` (64 bits) | signed `bigint` (64 bits) |
| `DB_ENSURE::FLOAT3` | `numeric(15,3)` | `numeric(15,3)` / `decimal(15,3)` |
| `DB_ENSURE::TEXT` | `text` | `longtext` |
| `DB_ENSURE::BOOLEAN` | `boolean` | `boolean` / `tinyint(1)` |

`FLOAT3` is an exact decimal with twelve integer digits and three decimal places;
it is not a binary floating-point type. MySQL uses `longtext` to avoid imposing
the 64 KiB limit of its `text` type on existing strings. These are the initial
supported types; raw SQL definitions are rejected.

## Behavior

- A missing table is created with exactly the declared columns. There is no
  implicit `id`, primary key, index, default or sequence.
- Missing columns are nullable and have no non-null default.
- Existing declared columns are changed when their type differs. Decimal
  precision and scale are checked too; `numeric(12,2)` does not match `FLOAT3`.
- Unspecified columns and stored rows remain. Existing defaults and nullability
  are retained; MySQL column comments, text collation and compatible integer
  auto-increment attributes are carried into `MODIFY COLUMN`.
- A repeat call returns `false` without DDL or locks when all types match. A call
  that changes the schema returns `true`. Failed queries/conversions throw;
  callers must not mark a migration complete after an exception.

Identifiers are unqualified names of at most 63 ASCII letters, digits and
underscores, starting with a letter or underscore. Reserved names are quoted.
The active tenant's schema/database is used; there is no master-connection
argument. Duplicate names, unknown types and empty declarations are rejected
before writes.

## Type conversions and operations

Run this helper **outside application transactions**. PostgreSQL repairs use a
private transaction and table lock. MySQL DDL implicitly commits, so the helper
uses a table write lock, runs any conversion callbacks, and issues one `ALTER TABLE`.
A per-table advisory lock serializes cooperating migrations, with a metadata
recheck after acquiring the lock. Session SQL mode and locks are restored on
success and exception. MySQL needs `LOCK TABLES` as well as the normal schema
privileges.

Without a callback, the database performs its normal conversion. For example,
changing `numeric(18,4)` to `FLOAT3` rounds `0.0005` to `0.001`. Invalid numeric
strings and overflow fail under PostgreSQL and the helper's temporary MySQL
strict mode. Conversion details differ by database; normalize special data in a
callback when a migration needs consistent results. Generated columns and uncommon
MySQL column attributes require an explicit migration when their type changes.
Constraints that cannot survive the requested type change cause the database
operation to fail; the helper does not drop them to force a conversion.

### Conversion callbacks

The optional third constructor argument runs **once per existing column whose
type differs**, before the helper issues ALTER. It receives the unquoted table
name, column name, old SQL type and target SQL type, in that order. It is not
called for missing columns, new tables or a repeat whose types already match.
Its return value is ignored. Throw an exception to stop the migration.

```php
$beforeAlter = function (string $table, string $column, string $oldType, string $newType): void {
    if ($column === 'procent') {
        // Fixed identifiers: this migration handles only loen_enheder.procent.
        db_modify("UPDATE loen_enheder SET procent = REPLACE(procent, ',', '.')", __FILE__ . ' linje ' . __LINE__);
    }
};

db_ensure_table('loen_enheder', array(
    new DB_ENSURE(DB_ENSURE::FLOAT3, array('procent'), $beforeAlter),
));
```

Callbacks use the active connection and run while the table is locked. Use them
for data normalization or validation, not schema changes, connection switching or
transaction control. PostgreSQL rolls callback writes back if the migration
fails. MySQL callback writes can persist even when the later ALTER fails, because
DDL implicitly commits; make callbacks safe to retry. The callback is not a
per-row transformer and does not return replacement SQL.

Large tables may be scanned and locked while checking/converting values. Review
the conversion and run it on disposable data before shipping a new declaration.
New migrations still belong at the **end of `includes/betweenUpdates.php`**;
feature-specific declarations live in `includes/structure/`, called from there.
Do not call this helper from ordinary page requests. The migrations guardrail
checks both `db_ensure_table()` and `DB_ENSURE::ensure()` calls.

The existing `ensureTableAndColumns()` helper remains available for legacy
callers; adoption of this helper is explicit, one migration at a time.

## Verification

```bash
php vendor/bin/phpunit --filter DatabaseEnsureTest
node --test tools/guardrails/tests/migrations.test.mjs
```

PostgreSQL tests use the standard owned SQL fixture and `SALDI_CHAR_*` settings.
MySQL/MariaDB cases also run when `SALDI_SCHEMA_MYSQL_DSN`,
`SALDI_SCHEMA_MYSQL_USER` and `SALDI_SCHEMA_MYSQL_PASSWORD` are set. Use a disposable
test server with permission to create databases; each case creates and removes
its own random database. No credentials are stored in the repository.

SQL behavior references: [PostgreSQL ALTER TABLE](https://www.postgresql.org/docs/18/sql-altertable.html),
[MySQL ALTER TABLE](https://dev.mysql.com/doc/refman/8.4/en/alter-table.html),
[MySQL table locks](https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html).
