# Grid text rendering

Protect text in the shared grid rendering path so every text column receives the same protection. Keep page renderers focused on their links, icons and layout instead of adding individual escaping value getters.

The cell-text contract is documented at `grid_text_html()` in `includes/grid/text.php`. When editing a renderer, distinguish the prepared display value from the raw row used for queries, comparisons, URLs and attributes. Exercise the renderer through the grid so highlighting and escaping are tested together.

This applies to the general grid and the separate customer- and supplier-order grid implementations. `GridCellTextTest` covers all three.
