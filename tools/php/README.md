# tools/php - PHP toolchain bootstrap

This clone's PHP dev tooling is composer-managed. Only ONE binary is
committed here: `composer.phar` (Composer 2.10.2), so a machine with plain
PHP and no global Composer can bootstrap everything else in one command.
PHPUnit and PHP_CodeSniffer are NOT committed - they are declared in
`composer.json` (require-dev) and installed into the gitignored `vendor/`
tree.

## Bootstrap (one command)

```powershell
php -c tools/php/php.ini tools/php/composer.phar install
```

This produces `vendor/bin/phpunit` (PHPUnit 11.x) and `vendor/bin/phpcs`
(PHP_CodeSniffer 4.x), pinned by the committed `composer.lock`.

## Environment quirk: no system php.ini on this host

The winget PHP 8.3 build loads NO php.ini by default (`php --ini` reports
"(none)"), so mbstring/openssl/zip/curl are unavailable until an ini is
passed explicitly. `tools/php/php.ini` enables them, but note its
`extension_dir` is hard-coded to this machine's winget package path - a PHP
reinstall/upgrade/move requires updating that line.

Consequently every phpunit/composer invocation must pass `-c`:

```powershell
# Run the characterization suite (verified: OK, 26 tests, 33 assertions)
php -c tools/php/php.ini vendor/bin/phpunit tests/characterization/includes/usdecimal/UsdecimalCharacterizationTest.test.php

# What the factory gates use (scripts/gates/run-gates.mjs + acceptance.mjs)
$env:PHPUNIT_CMD = 'php -c "C:/Users/miyam/dev/saldi/tools/php/php.ini" "C:/Users/miyam/dev/saldi/vendor/bin/phpunit"'
```

`vendor/bin/phpcs.bat` works directly (phpcs does not need mbstring), which
is what Gate 1 and the phpcs capability check invoke.

## History note

Standalone `phpunit.phar`/`phpcs.phar` builds were used to bootstrap this
setup before Composer was vendored; they were deliberately dropped from the
committed tree (a phar text-diffs as ~120k lines and bloats history) and may
exist locally as untracked files. The committed path is composer-only.
`.gitattributes` marks `*.phar binary` so any future phar never text-diffs.
