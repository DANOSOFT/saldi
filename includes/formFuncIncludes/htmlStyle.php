<?php
// 20260929 CDX/PHR Match HTML form typography and rule widths to the PostScript form settings.
// 20260929 CDX/PHR Preserve repeated spaces in both HTML layouts while allowing text wrapping.

/**
 * @return string CSS declarations using the form's font size in PostScript points.
 */
function formHtmlTextStyle($font, $size, $bold, $italic, $version = 2) {
    if ($version === 1) {
        $pixels = (float)$size * 1.2;
        return "font-family:Arial, Helvetica, sans-serif;font-size:{$pixels}px;white-space:pre-wrap;";
    }
    $families = array(
        'Helvetica' => 'Helvetica, Arial, sans-serif',
        'Times' => 'Times, Times New Roman, serif',
        'Courier' => 'Courier, Courier New, monospace',
        'Bookman' => 'Bookman, URW Bookman, serif',
        'Palatino' => 'Palatino, P052, serif',
        'NewCenturySchlbk' => 'New Century Schoolbook, Century Schoolbook, serif',
        'Ocrbb12' => 'Ocrbb12, OCR B, monospace',
    );
    $family = $families[$font] ?? $families['Helvetica'];
    $size = max(0, (float)$size);
    $weight = $bold ? 'bold' : 'normal';
    $style = $italic ? 'italic' : 'normal';
    return "font-family:$family;font-size:{$size}pt;font-weight:$weight;font-style:$style;white-space:pre-wrap;";
}

/**
 * Render axis-aligned rules using the existing HTML coordinate conversion.
 *
 * @param array{xa: mixed, ya: mixed, xb: mixed, yb: mixed, str: mixed, color: mixed} $line
 * @return string HTML rule, or an empty string for a diagonal/zero-length line.
 */
function formHtmlLine($line, $version = 2) {
    if ($version === 1) {
        // Preserve the original hr element, both borders and browser margins exactly.
        $top = (297 - $line['ya']) * 1.01 . 'mm';
        $left = $line['xa'] . 'mm';
        $html = '';
        if ($line['ya'] == $line['yb']) {
            $length = ($line['xb'] - $line['xa']) . 'mm';
            $html .= "<hr style=\"position:absolute;top:$top;left:$left;border:0.2px solid black; width:$length;\">\n";
        }
        if ($line['xa'] == $line['xb']) {
            $length = ($line['ya'] - $line['yb']) * 1.01 . 'mm';
            $html .= "<hr style=\"position:absolute;top:$top;left:$left;border:0.2px solid black; width:1; height:$length\">\n";
        }
        return $html;
    }
    $xa = (float)$line['xa'];
    $ya = (float)$line['ya'];
    $xb = (float)$line['xb'];
    $yb = (float)$line['yb'];
    if (($xa !== $xb && $ya !== $yb) || ($xa === $xb && $ya === $yb)) {
        return '';
    }
    // PostScript width zero is a device hairline; CSS needs a nonzero width to remain visible.
    $width = (float)$line['str'] > 0 ? (float)$line['str'] : 0.2;
    $encodedColor = str_pad((string)(int)$line['color'], 9, '0', STR_PAD_LEFT);
    $rgb = array();
    for ($i = 0; $i < 9; $i += 3) {
        $rgb[] = max(0, min(100, (int)substr($encodedColor, $i, 3))) . '%';
    }
    $color = 'rgb(' . implode(',', $rgb) . ')';
    $left = min($xa, $xb);
    $top = (297 - max($ya, $yb)) * 1.01;
    $style = "position:absolute;left:{$left}mm;top:{$top}mm;margin:0;padding:0;";
    if ($ya === $yb) {
        $length = abs($xb - $xa);
        $style .= "width:{$length}mm;height:0;border-top:{$width}pt solid $color;transform:translateY(-50%);";
    } else {
        $length = abs($yb - $ya) * 1.01;
        $style .= "width:0;height:{$length}mm;border-left:{$width}pt solid $color;transform:translateX(-50%);";
    }
    return '<div style="' . $style . '"></div>' . "\n";
}
