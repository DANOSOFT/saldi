---
name: convention_database_changes
description: "Where database structure/content changes go, and the version-cut exception"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-07-13-database-changes-routing
---

New database structure and content changes (schema changes, data migrations, one-off update queries) go into `includes/betweenUpdates.php`, not directly into an `includes/opdat_*.php` file.

**Exception — cutting a new version:** when a new version is released, the accumulated changes in `includes/betweenUpdates.php` are transferred (moved, not left duplicated) into `includes/opdat_<major>.<minor>.php` matching the new version (e.g. version `4.3.0` → `includes/opdat_4.3.php`), and `$version` in `includes/version.php` is bumped to the new version string. `includes/betweenUpdates.php` is then left empty again (header/footer intact) to accumulate changes for the next release.

Only the **body** — the actual statements between the header's closing separator and the trailing `#####`/`?>` — moves. Do NOT copy `betweenUpdates.php`'s ASCII banner, versioning line, LICENSE, or copyright block into the opdat file: the target `opdat_<major>.<minor>.php` already has its own header and its own wrapping function (e.g. `opdat_4_3()`). The `#####` markers inside opdat files are internal step separators, not file footers, so don't carry `betweenUpdates.php`'s trailing `#####`/`?>` over literally either.

The moved statements must be placed inside the `opdat_to(...)` call within that function's body — not appended as loose statements. `opdat_to()` and `opdat_version_string()` live in `includes/opdat_func/opdat_func.php`; see `includes/opdat_4.3.php` for the exact pattern:

```php
if (!function_exists('opdat_4_3')) {
function opdat_4_3($majorNo, $subNo, $fixNo){
	global $version;
	...
	include_once(__DIR__ . "/opdat_func/opdat_func.php");

	$current_version = opdat_version_string($majorNo, $subNo, $fixNo);
	$nextver = '4.3.0';
	opdat_to($current_version, $nextver, function () {
		// the moved betweenUpdates.php statements go here
	});
}}
```

**Why:** `betweenUpdates.php` is the working/staging area for changes not yet tied to a released version. `opdat_<major>.<minor>.php` files are the permanent, per-version record of what a given release's updater applies — see [Protected update files](feedback_protected_update_files.md) for handling rules on these files.

**How to apply:** For a normal DB structure/content change, edit `includes/betweenUpdates.php`. Only touch `includes/opdat_*.php` or `includes/version.php` when the user explicitly asks to cut/release a new version — do not do this transfer speculatively.
