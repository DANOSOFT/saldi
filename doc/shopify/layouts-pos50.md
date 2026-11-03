# Shopify document layouts on pos_50

The customer documents of a Shopify sale (invoice, credit note, packing slip) are printed by
`formularprint()` from the `formularer` rows of the sale's brand: the layout language is the
order's `sprog`, which the web intake sets to the brand (WP11-4, SD-754). These changes are tenant
data on pos_50, not code. Make them in the configuration window, in the order below, after a backup.
Try them on a `saldi_shopify_test_*` copy first.

## 1. Back up the layouts

    pg_dump -h localhost -U <user> -t formularer --data-only pos_50 > formularer-pos50-$(date +%Y%m%d).sql
    psql -h localhost -U <user> pos_50 -c "\copy (SELECT * FROM grupper WHERE art = 'VSPR') TO 'vspr-pos50.csv' CSV HEADER"

Keep both files until HML has accepted the documents. To restore: `DELETE FROM formularer`, then
load the dump with `psql`, and delete any `VSPR` row that is not in the CSV.

## 2. One layout per brand (K36b)

Each shop's brand is a layout language (Indstillinger → Formularer → Sprog). Its name must be
exactly the `sprog` of the shop's `shopify_brands` row, case aside:

    SELECT b.sprog, s.shop_key FROM shopify_brands b JOIN shopify_shops s ON s.id = b.shop_id ORDER BY b.sprog;

The document link and the worker print through `render_document()`, which prints a sale only in
its own brand. A brand without an invoice or credit-note layout is refused (no document is made),
never printed in another brand's layout. A print from the order screen falls back to the Dansk
layout instead, so give every brand all its layouts. A
brand's letterhead is `logolib/<db_id>/<brand>_faktura_bg.pdf` (`<brand>_kreditnota_bg.pdf`,
`<brand>_bg.pdf`, lower-case brand, also per department under `logolib/<db_id>/<afd>/`). Without
one, the document has no letterhead.

## 3. Direkte Import (K36c)

1. Formularer → Sprog: create the language for the Direkte Import brand, with Havemøbelland's
   layout as the template (the VSPR copy). Use a name without spaces, e.g. `DirekteImport`,
   because it is also part of the letterhead's file name.
2. Set the DI shop's `shopify_brands.sprog` to the same name.
3. Until HML's artwork arrives, the copy keeps Havemøbelland's design with a placeholder
   letterhead: copy the HML brand's `<brand>_faktura_bg.pdf` and `<brand>_kreditnota_bg.pdf` to
   `direkteimport_faktura_bg.pdf` and `direkteimport_kreditnota_bg.pdf` (the brand name in lower case). When the artwork comes,
   replace the file and adjust the copied rows in the form editor. K36c is tested from then.

## 4. References (K54)

The customer's reference is the Shopify order name with prefix (HML1234, HMS1234, DI1234), which
the web intake writes to `ordrer.kundeordnr`. The invoice and credit note also show their own
number (`$ordre_fakturanr`). Saldi's internal order number (`$ordre_ordrenr`) is not shown on a
customer document. A store order has no Shopify order name, so its reference is the invoice
number. Its layout must not print the order number instead.

For every brand, on the invoice (formular 4) and credit note (formular 5), list the header rows
that print the order number or the customer's order number:

    SELECT id, formular, beskrivelse, xa, ya FROM formularer
    WHERE formular IN (4, 5) AND art = 2 AND lower(sprog) = lower('<brand>')
      AND (beskrivelse LIKE '%ordre_ordrenr%' OR beskrivelse LIKE '%ordre_kundeordnr%' OR beskrivelse ILIKE '%ordre nr%' OR beskrivelse ILIKE 'Deres ref%')
    ORDER BY formular, ya DESC, xa;

Then change the rows by id:

| Row (standard layout) | New text |
|---|---|
| `$ordre_ordrenr` | `if($ordre_kundeordnr)$ordre_kundeordnr` |
| its label, `Vores ordre nr` / `Deres ordre nr` | `if($ordre_kundeordnr)Ordrenr.` |
| `Deres ref:` and `$ordre_kundeordnr;` | delete both (the order name is now printed above) |

`if($ordre_kundeordnr)` prints the row only when the order has a Shopify order name. A store
order's invoice then shows only its number, without an empty label. Afterwards, the first query
returns no row with `ordre_ordrenr` for the brand. The same recipe runs in
`tests/characterization/includes/FormularprintLagerFieldsTest.test.php`.

Check it with `php tests/shopify/support/sandbox/K54.php <tenant> <web invoice id> <web credit note id> <store invoice id>`
on the server that hosts the tenant. The other customer documents (order receipt, order
confirmation, shipping mail) are Shopify's mails, made by DTAILS.

## 5. Lager per line (K17)

Formularer → Ordrelinjer, for the invoice (4), credit note (5) and packing slip (3) of every
brand: add the line field **Lagernavn** (the lager's name) or **Lager** (its number), at an x
position where the description leaves room. Each line prints the lager it is drawn from. On a
Shopify web order (`ref` = `shopify`), a line drawn from two lagre is two order lines and prints as
two rows, one per lager. Other orders still print one row per position and item number, as before.
Lager is shown on Saldi's documents only. Shopify has no line field for it (K17, v0.2).
