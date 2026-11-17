<?php
// 20261117 CDX/NTR Preserve database encodings when displaying and saving standard order text.
// 20261117 CDX/NTR Limit lookup formatting to attribute-free bold and italic tags.

/**
 * Escape database text for the UTF-8 order-text editor.
 * @param string $text Text in the account's database connection encoding.
 * @param string $dbEncoding UTF8 or LATIN9.
 * @return string UTF-8 text safe in HTML text and quoted attributes.
 */
function orderTextEditorHtml(string $text, string $dbEncoding): string
{
    if ($dbEncoding !== 'UTF8') {
        $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-15');
    }
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Save UTF-8 form text using the account's database connection encoding.
 * @param int $textId Standard order-text ID.
 * @param string $text UTF-8 submitted text.
 * @param string $dbEncoding UTF8 or LATIN9.
 * @return void
 */
function saveStandardOrderText(int $textId, string $text, string $dbEncoding): void
{
    $escaped = db_escape_string($text);
    if ($dbEncoding !== 'UTF8') {
        // Convert after escaping: db_escape_string() would recode LATIN9 bytes back to UTF-8.
        // LATIN9 is single-byte; conversion preserves the ASCII SQL quote/backslash escapes.
        $escaped = mb_convert_encoding($escaped, 'ISO-8859-15', 'UTF-8');
    }
    db_modify("update ordretekster set tekst='$escaped' where id = '$textId'", __FILE__ . " linje " . __LINE__);
}

/**
 * Filter stored lookup text, preserving only attribute-free bold and italic tags.
 * @param string $text Stored text, including existing rows that may contain unsafe markup.
 * @param string $dbEncoding UTF8 or LATIN9; keep the lookup's existing output encoding.
 * @return string Safe HTML without newline conversion.
 */
function orderTextLookupHtml(string $text, string $dbEncoding): string
{
    $text = strip_tags($text, '<b><i>');
    $text = preg_replace('/<(\/?)(b|i)\b[^>]*>/i', '<$1$2>', $text);
    $charset = $dbEncoding === 'UTF8' ? 'UTF-8' : 'ISO-8859-15';
    $parts = preg_split('/(<\/?[bi]>)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    foreach ($parts as $part) {
        if (preg_match('/^<\/?[bi]>$/i', $part)) {
            $html .= strtolower($part);
        } else {
            $html .= htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, $charset, false);
        }
    }
    return $html;
}
