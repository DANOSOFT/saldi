<?php
// 20261117 CDX/NTR Preserve database encodings when displaying and saving standard order text.
// 20261117 CDX/NTR Limit lookup formatting to attribute-free bold and italic tags.
// 20261118 CDX/NTR Bind saved text and preserve comparisons and LATIN9 glyphs in the lookup.
// 20261118 CDX/NTR Reject lossy text saves and preserve incomplete markup as lookup text.
// 20261118 CDX/NTR Report missing templates separately and accept unchanged MySQL saves.

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
 * @return 'unsupported_characters'|'not_found'|'save_failed'|null Error code, or null after a successful save.
 */
function saveStandardOrderText(int $textId, string $text, string $dbEncoding, string $dbType): ?string
{
    if (!mb_check_encoding($text, 'UTF-8')) {
        return 'unsupported_characters';
    }
    $text = str_replace("\0", '', $text);
    if ($dbEncoding !== 'UTF8') {
        $encoded = mb_convert_encoding($text, 'ISO-8859-15', 'UTF-8');
        if (mb_convert_encoding($encoded, 'UTF-8', 'ISO-8859-15') !== $text) {
            return 'unsupported_characters';
        }
        $text = $encoded;
    }
    $mysql = $dbType === 'mysql' || $dbType === 'mysqli';
    $sql = $mysql
        ? 'update ordretekster set tekst = ? where id = ?'
        : 'update ordretekster set tekst = $1 where id = $2';
    $affectedRows = db_modify($sql, __FILE__ . " linje " . __LINE__, false, [$text, $textId], true);
    if (!is_int($affectedRows) || $affectedRows < 0) {
        return 'save_failed';
    }
    if ($affectedRows > 0) {
        return null;
    }
    // MySQL reports changed rows, so zero can also mean that an existing template already had this text.
    if ($mysql) {
        $result = db_select("select id from ordretekster where id = '$textId'", __FILE__ . " linje " . __LINE__);
        if ($result === false) {
            return 'save_failed';
        }
        if (db_fetch_array($result)) {
            return null;
        }
    }
    return 'not_found';
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
    // Recognize only complete markup; quoted attributes may contain >, but an unquoted < starts another token.
    // Incomplete fragments such as Load<max remain text, even when a complete tag follows them.
    $markup = <<<'REGEX'
~(
    <!-- .*? -->
    | <[!?] (?: [^<>"'] | "[^"]*" | '[^']*' )* >
    | </? [a-zA-Z][a-zA-Z0-9:-]* (?=[\s/>])
      (?: [^<>"'] | "[^"]*" | '[^']*' )* >
)~sx
REGEX;
    $parts = preg_split($markup, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    foreach ($parts as $index => $part) {
        if ($index % 2 === 0) {
            $html .= htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        // Match only b/i tag names, followed by whitespace, / or >, at the start of a complete token.
        } elseif (preg_match('~^<(/?)([bi])(?=[\\s/>])~i', $part, $tag)) {
            // Rebuild allowed tags instead of preserving any stored attributes.
            $html .= '<' . $tag[1] . strtolower($tag[2]) . '>';
        }
    }
    return $dbEncoding === 'UTF8' ? $html : mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
}
