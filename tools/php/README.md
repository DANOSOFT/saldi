# tools/php - PHP toolchain bootstrap

This clone's PHP dev tooling is composer-managed. Only ONE binary is
committed here: `composer.phar` (Composer 2.10.2), so a machine with plain
PHP and no global Composer can bootstrap everything else in one command.
PHPUnit and PHP_CodeSniffer are NOT committed - they are declared in
`composer.json` (require-dev) and installed into the gitignored `vendor/`
tree.

## Bootstrap (one command)

```powershell
php tools/php/phpw.php tools/php/composer.phar install
```

This produces `vendor/bin/phpunit` (PHPUnit 11.x) and `vendor/bin/phpcs`
(PHP_CodeSniffer 4.x), pinned by the committed `composer.lock`.

## Run the tests (portable)

```powershell
composer test
```

or, without a global Composer:

```powershell
php tools/php/phpw.php tools/php/composer.phar test
```

Both run PHPUnit with `phpunit.xml` discovery (the characterization suite
under `tests/characterization`). This is the command the CI gate (SD-591)
should invoke.

## How portability works: `phpw.php`

Some PHP builds load no php.ini at all (the winget PHP package on Windows
reports "Loaded Configuration File: (none)"), so the extensions PHPUnit and
Composer need (mbstring, openssl, zip, curl) are never enabled. Worse, the
compiled-in `extension_dir` default points at a directory that doesn't exist
(`C:\php\ext`).

`tools/php/phpw.php` fixes this without any machine-specific config: it runs
on bare PHP (needs no extensions itself), detects which required extensions
are missing, and re-invokes PHP on the given script with
`-d extension_dir=<dir-of-php-binary>/ext` and `-d extension=...` flags.
On a PHP that already loads a proper ini (typical Linux/macOS), it adds no
flags and just passes through.

`tools/php/php.ini` remains for manual `php -c` invocations but no longer
hard-codes any path; see the comments in that file.

Direct PHPUnit invocations also work through the wrapper:

```powershell
# Run a single test file
php tools/php/phpw.php vendor/bin/phpunit tests/characterization/includes/usdecimal/UsdecimalCharacterizationTest.test.php

# What the factory gates use (scripts/gates/run-gates.mjs + acceptance.mjs)
$env:PHPUNIT_CMD = 'php tools/php/phpw.php vendor/bin/phpunit'
```

`vendor/bin/phpcs.bat` works directly (phpcs does not need mbstring), which
is what Gate 1 and the phpcs capability check invoke.

## History note

Standalone `phpunit.phar`/`phpcs.phar` builds were used to bootstrap this
setup before Composer was vendored; they were deliberately dropped from the
committed tree (a phar text-diffs as ~120k lines and bloats history) and may
exist locally as untracked files. The committed path is composer-only.
`.gitattributes` marks `*.phar binary` so any future phar never text-diffs.

20260723 CL/LH SD-593: portable entry point (`composer test` + `phpw.php`);
de-hardcoded `extension_dir`.
