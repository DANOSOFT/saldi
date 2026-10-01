# HTML form layout versions

The print engine remains selected by `grupper.box3` on `art='PV', kodenr='1'`.
The separate tenant setting `settings(var_grp='forms', var_name='htmlLayoutVersion')`
selects the HTML rendering behavior:

- `1`: preserve the original HTML output, including its fixed font family, smaller
  pixel-based text sizes, and fixed thin rules. Both versions preserve repeated
  spaces in text; this whitespace fix also applies to the legacy layout.
- `2`: use the form's font family, point sizes, weight/style, rule width and color.

The login updater in `includes/betweenUpdates.php` initializes this setting once.
Existing HTML users receive version 1; accounts using PostScript or without print
settings receive version 2 for future HTML use. Subsequent updates retain the
stored choice. Initialization is serialized per database to avoid duplicate rows
from concurrent logins. No rows in `formularer` are changed.

Under **Indstillinger → Diverse → Diverse valg**, **HTML/CSS-layout** offers
**Bevar hidtidigt udseende** and **Brug formularens skrifter og stregtykkelser**.
The selection can be changed back at any time. It only affects HTML-generated
PDFs; PostScript rendering and stored form definitions are unchanged. Check a
sample document, including page breaks, when changing the layout.

Rendering only reads the setting, cached per tenant for the request. If an active
session prints before the updater has initialized the setting, it safely uses
version 1. Older settings forms that do not submit a layout selection leave the
stored choice untouched. No server-wide tenant scan is required.

Tests:

- `php tests/integration/printHtmlStyle.php`
- `php tests/integration/printTextEscaping.php` (requires Ghostscript)
- `php tests/integration/printDescriptionLayout.php` (requires Ghostscript)
- `php tests/test_html_layout_version.php` with `SALDI_CHAR_DSN`,
  `SALDI_CHAR_PGUSER`, and `SALDI_CHAR_PGPASS` (PostgreSQL temporary tables;
  rolled back after the test).

## Description wrapping

Order-line descriptions use the configured maximum number of Unicode characters
and the available width up to neighbouring fields. The latter is measured using
font metrics, including the actual printed quantity/price instead of a fixed
eight-character allowance. A 2mm gap accommodates glyph overhang and font
substitution. Page preflight and rendering share the same wrapped text.
This correction applies to PostScript and both HTML layouts, so long descriptions
may occupy fewer lines and page breaks can change even in the legacy layout.

Bundled numeric advance widths cover Helvetica, Times, Courier, Palatino and
NewCenturySchlbk, regular/bold/italic/bold-italic, with the print engine's Latin-9
encoding. No font files are bundled. Other fonts and unrepresentable characters
use a conservative 1.2em fallback. These metrics describe the built-in fonts;
browser font substitutions can differ. Preview custom fonts before deployment.
Both renderers share wrapping; legacy HTML also checks its fixed Arial size.

Regenerate the metrics from the repository root using
`python3 tools/forms/generate-description-metrics.py` (requires Ghostscript).
Test the configured description maximum at 52 and 62, narrow/wide text, large
quantities, bold/italic text, explicit line breaks and an order line near the
bottom of a multipage confirmation, invoice and delivery note.
