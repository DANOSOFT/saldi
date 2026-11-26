<?php
// --- includes/grid/text.php --- ver 5.1.1 --- 2026-11-26 ---
// LICENSE: GNU GPL v2 or later. Copyright (c) 2003-2026 Danosoft ApS
// 20261125 CDX/NTR Escape grid text once and add highlighting only around escaped text segments.
// 20261126 CDX/NTR Normalize LATIN9 text and terms before escaping, emitting entities for legacy page charsets.

/**
 * Prepare plain text for an HTML cell. Render callbacks may wrap the result in trusted markup, but must not
 * escape it again. Use the original row for comparisons, SQL, URLs and HTML attributes.
 * Splitting before escaping preserves literal entities and never treats stored highlight spans as markup.
 *
 * @param string $value Raw text returned by a column's valueGetter.
 * @param string $term Literal search term, or an empty string for no highlighting.
 * @param string $dbEncoding Connection encoding of both strings, UTF8 or LATIN9.
 * @return string Escaped text with grid-generated highlight spans; ASCII HTML on legacy pages.
 */
function grid_text_html(string $value, string $term = '', string $dbEncoding = 'UTF8'): string
{
    if ($dbEncoding !== 'UTF8') {
        $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-15');
        $term = mb_convert_encoding($term, 'UTF-8', 'ISO-8859-15');
    }
    if ($term === '' || !mb_check_encoding($value, 'UTF-8') || !mb_check_encoding($term, 'UTF-8')) {
        $html = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    } else {
        $parts = preg_split('/(' . preg_quote($term, '/') . ')/iu', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';
        foreach ($parts as $index => $part) {
            $part = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= $index % 2 ? '<span style="background-color:#FF0">' . $part . '</span>' : $part;
        }
    }
    return $dbEncoding === 'UTF8' ? $html : mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
}
