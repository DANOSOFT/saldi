<?php
// 20261117 CDX/NTR Preserve database encodings when displaying and saving standard order text.
// 20261117 CDX/NTR Limit lookup formatting to attribute-free bold and italic tags.
// 20261118 CDX/NTR Bind saved text and preserve comparisons and LATIN9 glyphs in the lookup.

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
 * @param string $dbType Database driver, used to select its parameter placeholders.
 * @return void
 */
function saveStandardOrderText(int $textId, string $text, string $dbEncoding, string $dbType): void
{
    $text = str_replace("\0", '', $text);
    if ($dbEncoding !== 'UTF8') {
        $text = mb_convert_encoding($text, 'ISO-8859-15', 'UTF-8');
    }
    $sql = ($dbType === 'mysql' || $dbType === 'mysqli')
        ? 'update ordretekster set tekst = ? where id = ?'
        : 'update ordretekster set tekst = $1 where id = $2';
    db_modify($sql, __FILE__ . " linje " . __LINE__, false, [$text, $textId]);
}

/**
 * Filter stored lookup text, preserving only attribute-free bold and italic tags.
 * @param string $text Stored text, including existing rows that may contain unsafe markup.
 * @param string $dbEncoding UTF8 or LATIN9; LATIN9 uses entities for the ISO-8859-1 page.
 * @return string Safe HTML without newline conversion.
 */
function orderTextLookupHtml(string $text, string $dbEncoding): string
{
    if ($dbEncoding !== 'UTF8') {
        $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-15');
    }
    // A comparison such as <5 or <=5 cannot start an HTML tag; protect it from strip_tags().
    $text = preg_replace('/<(?!\/?[a-zA-Z]|[!?])/', '&lt;', $text);
    $text = strip_tags($text, '<b><i>');
    $text = preg_replace('/<(\/?)(b|i)\b[^>]*>/i', '<$1$2>', $text);
    $parts = preg_split('/(<\/?[bi]>)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    foreach ($parts as $part) {
        if (preg_match('/^<\/?[bi]>$/i', $part)) {
            $html .= strtolower($part);
        } else {
            $html .= htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        }
    }
    return $dbEncoding === 'UTF8' ? $html : mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
}
