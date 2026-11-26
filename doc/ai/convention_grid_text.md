# Grid text rendering

Protect text in the shared grid rendering path so every text column receives the same protection. Keep page renderers focused on their links, icons and layout instead of adding individual escaping value getters.

The cell-text contract is documented at `grid_text_html()` in `includes/grid/text.php`. Supply the active database encoding; legacy rows and search terms require normalization before Unicode escaping and highlighting. When editing a renderer, distinguish the prepared display value from the raw row used for queries, comparisons, URLs and attributes. Exercise the renderer through the grid so highlighting and escaping are tested together.

This applies to the general grid and the separate customer- and supplier-order grid implementations. `GridCellTextTest` covers all three.

Keep search input provenance explicit at page boundaries. A legacy page declaring
ISO-8859-1 submits browser fields as Windows-1252, while its database connection
may use ISO-8859-15. Decode browser fields separately from database values.
Lookup redirects carry the original input with an encoding marker; SQL-escaped
values must not become search text. `ProductLookupRenderingTest` exercises the
order redirects and both external-price-list search fields.
