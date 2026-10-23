# findtekst() text keys

Texts fetched with `findtekst($textId, $languageID)` (and `findtekst_utf8()`) are
stored in the `tekster` table. Historically each text was identified by a numeric
`tekst_id`. A `tekst_key` column now holds a descriptive string key, and
`findtekst()` resolves either one.

**For new texts, prefer a string key over a numeric id.** Numeric ids are opaque
(`findtekst(917, …)` tells you nothing) and force you to hunt for a free integer.

## Key format

- Dotted, lowercase, describing the text's purpose: `invoice.open`, `debtor.create`.
- Shared/reused texts go under the `common.*` namespace. A generic "Accept"
  button is `common.accept`, a generic close/back is `common.close` — reuse these
  instead of minting a page-specific key for the same word.
- Pass a fallback after a pipe while a key is new: `findtekst('common.accept|Accept', $sprog_id)`.
  The left side is the lookup token (key or id), the right side is the text shown
  (and self-healed into the table) until a translator fills it in.
- When seeding keys into `importfiler/tekster.csv`, keep them in alphanumeric
  order by key so the file stays scannable.

## Behaviour to know

- Resolution order for a key: `tekst_key` row → legacy `tekst`-content match →
  CSV seed files → self-heal insert. A requested key missing everywhere is
  inserted so it shows up in `systemdata/tekster.php` for translation.
- A row whose `tekst` equals its `tekst_key` is a not-yet-translated placeholder;
  `findtekst()` never returns it as a finished translation.
- Existing numeric `tekst_id` calls keep working unchanged — do **not** mass-migrate
  the ~8,400 call sites. Convert to keys opportunistically when you touch a page.

## Future cleanup

The legacy `tekst`-content match (step 2 in the resolution order) only exists for
callers that pass literal text as the lookup token. Once no call site does that,
step 2 in `findTxt.php` can be dropped and a non-numeric argument can mean "a
`tekst_key`, full stop."

See `includes/stdFunc/findTxt.php` for the implementation.
