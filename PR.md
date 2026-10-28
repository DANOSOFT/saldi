# Remove obsolete lager pages and fix min/max stock initialization

## Summary

This change removes obsolete `lager` pages that are no longer used or have been
replaced by newer implementations. It also updates decimal helper usage and
fixes uninitialized arrays in `lager/minmaxstock.php`.

## Removed files

The following files have been deleted:

- `lager/opdater_kostpriser.php`
  - No longer referenced anywhere.

- `lager/vareliste.php`
  - Replaced by the newer implementation:
    - `lister/vareliste.php`

- `lager/enheder.php`
  - Replaced by:
    - `systemdata/enheder.php`

- `lager/vareimport.php`
  - Replaced by:
    - `systemdata/importer_variantvarer.php`

- `includes/dkdecimal.php`
  - Removed and references updated to use:
    - `includes/stdFunc/dkDecimal.php`

## Retained files

`lager/fuld_stykliste.php` was reviewed but not removed because it is still
referenced by existing code paths, including:

- `online.php`
- `top_header_sager.php`

The current behaviour remains unchanged:
- When `vare_id` is supplied, normal functionality is used.
- When `vare_id` is missing, it redirects to `vareliste.php`.

## Bug fix

Updated `lager/minmaxstock.php`:

- Initialized previously uninitialized arrays.
- Set `group` to `null` when no group value is supplied.

## Testing

Tested on:

- `test_58`
- `ssl12`

### fuld_stykliste.php verification

Verified HTTP 200 response using an authenticated session:

```bash
curl -i \
  -b "PHPSESSID=<session_id>" \
  https://<server>/lager/fuld_stykliste.php
