<?php
// --- includes/grid/text.php --- ver 5.1.1 --- 2026-11-25 ---
// LICENSE: GNU GPL v2 or later. Copyright (c) 2003-2026 Danosoft ApS
// 20261125 CDX/NTR Escape grid text once and add highlighting only around escaped text segments.

/**
 * Prepare plain text for an HTML cell. Render callbacks may wrap the result in trusted markup, but must not
 * escape it again. Use the original row for comparisons, SQL, URLs and HTML attributes.
 * Splitting before escaping preserves literal entities and never treats stored highlight spans as markup.
 *
 * @param string $value Raw text returned by a column's valueGetter.
 * @param string $term Literal search term, or an empty string for no highlighting.
 * @return string Escaped text with only grid-generated highlight spans.
 */
function grid_text_html(string $value, string $term = ''): string
{
    if ($term === '' || !mb_check_encoding($value, 'UTF-8') || !mb_check_encoding($term, 'UTF-8')) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    $parts = preg_split('/(' . preg_quote($term, '/') . ')/iu', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    foreach ($parts as $index => $part) {
        $part = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= $index % 2 ? '<span style="background-color:#FF0">' . $part . '</span>' : $part;
    }
    return $html;
}
