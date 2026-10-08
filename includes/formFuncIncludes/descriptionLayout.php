<?php
// 20260930 CDX/PHR Wrap order descriptions using font metrics and actual neighbouring values.

/**
 * Widths in points for the built-in PostScript fonts (ISO Latin-9 encoding).
 * Unknown fonts/characters use a conservative fallback. Metrics are loaded once,
 * without invoking a PDF converter or querying the database for each order line.
 *
 * @return float
 */
function formDescriptionTextWidth($text, $font, $size, $bold = false, $italic = false, $legacyHtml = false) {
    static $metrics;
    if ($metrics === null) {
        $metrics = json_decode(file_get_contents(__DIR__ . '/metrics/latin9.json'), true);
    }
    $style = $bold ? ($italic ? '-BoldItalic' : '-Bold') : ($italic ? '-Italic' : '');
    $widths = ifset($metrics, $font . $style, array());
    $htmlWidths = ifset($metrics, 'Helvetica' . $style, array());
    $width = $htmlWidth = 0;
    foreach (preg_split('//u', (string)$text, -1, PREG_SPLIT_NO_EMPTY) as $character) {
        $encoded = mb_convert_encoding($character, 'ISO-8859-15', 'UTF-8');
        $known = mb_convert_encoding($encoded, 'UTF-8', 'ISO-8859-15') === $character;
        $code = ord($encoded);
        $width += $known ? ifset($widths, $code, 1200) : 1200;
        $htmlWidth += $known ? ifset($htmlWidths, $code, 1200) : 1200;
    }
    // Both outputs share line breaks. Legacy HTML uses Arial at 1.2px per point.
    return max($width, $legacyHtml ? $htmlWidth * 0.9 : 0) * max(0, (float)$size) / 1000;
}

/**
 * Reserve the actual advance width of right/centre-aligned neighbouring fields.
 * Coordinates are template millimetres; use the smaller HTML point conversion
 * (PostScript uses 2.86), with a 2mm gap to allow for font substitution/overhang.
 *
 * @param array{x: float, align: string} $description
 * @param array<array{x: float, align: string, text: string, font: string, size: float, bold: bool, italic: bool}> $columns
 * @return float Available points, or INF when there is no right-hand boundary.
 */
function formDescriptionAvailableWidth($description, $columns, $legacyHtml = false) {
    $available = INF;
    foreach ($columns as $column) {
        if ($column['x'] <= $description['x'] || $column['size'] <= 0 || trim((string)$column['text']) === '') {
            continue;
        }
        $width = formDescriptionTextWidth($column['text'], $column['font'], $column['size'],
            $column['bold'], $column['italic'], $legacyHtml);
        // skriv() currently renders centre-aligned HTML fields right-aligned.
        // Reserve the full width so shared wrapping fits both print engines.
        $reserve = in_array($column['align'], array('H', 'C'), true) ? $width : 0;
        $available = min($available, ($column['x'] - $description['x'] - 2) * 72 / 25.4 - $reserve);
    }
    if ($description['align'] === 'C') {
        $available *= 2;
    } elseif ($description['align'] === 'H') {
        // Right-aligned descriptions grow to the left of their anchor.
        $available = INF;
    }
    return max(0, $available);
}

/**
 * Honour the configured character maximum and the measured physical width.
 * Preserve UTF-8, explicit newlines, internal spaces and the renderer's style tags.
 * Zero character limit means only the physical width limits the line.
 *
 * @return string Prewrapped text shared by page preflight and ombryd().
 */
function formWrapDescription($text, $maxCharacters, $maxWidth, $font, $size, $bold = false, $italic = false, $legacyHtml = false) {
    $text = str_replace(array("\r\n", "\r"), "\n", (string)$text);
    $result = array();
    $tagBold = $tagItalic = $big = false;
    foreach (explode("\n", $text) as $paragraph) {
        preg_match_all('/<\/?(?:b|i|big|small)>|./u', $paragraph, $matches);
        $tokens = $matches[0];
        $widths = $counts = array();
        foreach ($tokens as $index => $token) {
            if (preg_match('/^<(\/?)(b|i|big|small)>$/', $token, $tag)) {
                $enabled = $tag[1] !== '/';
                if ($tag[2] === 'b') { $tagBold = $enabled; }
                if ($tag[2] === 'i') { $tagItalic = $enabled; }
                if ($tag[2] === 'big') { $big = $enabled; }
                $widths[$index] = $counts[$index] = 0;
            } else {
                // PS keeps the base size; HTML honours small/big. Fit both outputs.
                $widths[$index] = formDescriptionTextWidth($token, $font, $size + ($big ? 2 : 0),
                    $bold || $tagBold, $italic || $tagItalic, $legacyHtml);
                $counts[$index] = 1;
            }
        }
        $start = 0;
        $length = count($tokens);
        while ($start < $length) {
            $width = $characters = 0;
            $break = null;
            for ($end = $start; $end < $length; $end++) {
                if ($tokens[$end] === ' ' && $characters > 0) {
                    $break = $end;
                }
                if ($counts[$end] && $characters > 0 &&
                    (($maxCharacters > 0 && $characters + 1 > $maxCharacters) || $width + $widths[$end] > $maxWidth)) {
                    break;
                }
                $width += $widths[$end];
                $characters += $counts[$end];
            }
            if ($end < $length && $break !== null && $break > $start) {
                $end = $break;
            }
            $result[] = implode('', array_slice($tokens, $start, $end - $start));
            $start = $end;
            while ($start < $length && $tokens[$start] === ' ') {
                $start++;
            }
        }
        if ($length === 0) {
            $result[] = '';
        }
    }
    return implode("\n", $result);
}
