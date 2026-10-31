<?php
// ---- includes/labelTemplates.php --- lap 5.0.0 --- 2026.10.10 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261010 Sawaneh Settings G5.8: loadLabelText(), saveLabelText(), parseLabelTemplate(), generateLabelTemplate(),
//                  labelTemplateEditableVisually() and saveVisualLabelEdit() moved here unchanged from
//                  systemdata/sys_div_func.php (their history is kept there), plus the list/template helpers the
//                  Mærkater page (systemdata/labels.php) needs. lager/labelprint.php prints what is saved here.

if (!function_exists('loadLabelText')) {

/**
 * The shipped templates a new label can start from: file in importfiler/ => its text.
 *
 * @return array<string, string>
 */
function label_templates(): array
{
	return array(
		'A4Label38x21_ens.txt'    => '1310|A4 38,1 x 21,2 mm, ens labels',
		'A4Label38x21.txt'        => '1311|A4 38,1 x 21,2 mm, mit salg',
		'BrotherLabel22606.txt'   => '1312|Brother 22606',
		'BrotherLabel22606MS.txt' => '1313|Brother 22606 mit salg',
		'DymoLabelArt11354.txt'   => '7074|Dymo 11354',
		'DymoLabelArt11354MS.txt' => '1314|Dymo 11354 mit salg',
	);
}

/**
 * Item labels as lager/labelprint.php offers them: Standard first, then the stored ones by name => type.
 *
 * @return array<string, string>
 */
function label_names(): array
{
	$out = array('Standard' => 'sheet');
	$q = db_select("select labelname, labeltype from labels where (account_id = '0' or account_id is null) order by labelname", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$name = (string) $r['labelname'];
		if ($name !== '') {
			$out[$name] = ((string) $r['labeltype'] === 'label') ? 'label' : 'sheet';
		}
	}
	return $out;
}

/**
 * The name rule of the create form (B-L1): the name ends up in file-like SQL and on screen.
 */
function label_name_valid(string $name): bool
{
	return (bool) preg_match('/^[a-zA-Z0-9+.-]{1,40}$/', $name);
}

/**
 * Reads the stored text for one label.
 *
 * Item labels ($valg='box1') live in the labels table since 4.0; grupper.box1 is only the
 * pre-4.0 fallback. lager/labelprint.php resolves them in the same order, so the editor has to
 * as well - otherwise an edit is written somewhere the print never looks. Address labels
 * ($valg='box2') only ever live in grupper.
 *
 * @param string $valg      'box1' for item labels, 'box2' for address labels
 * @param string $labelName Name of the label to read
 * @return array{labeltext: string, labeltype: string}
 */
function loadLabelText($valg, $labelName) {
    $label = array('labeltext' => '', 'labeltype' => 'sheet');
    if ($valg == 'box1') {
        // account_id is null on labels created before the insert below started setting it; they are
        // still editable here and a save heals them to 0, which is all lager/labelprint.php reads.
        $qtxt = "select labeltext, labeltype from labels where labelname = '" . db_escape_string($labelName) . "'";
        $qtxt.= " and (account_id = '0' or account_id is null)";
        $qtxt.= " order by account_id is null"; // if account_id is not null it'll be false, which is ordered before true.
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $label['labeltext'] = $r['labeltext'];
            if ($r['labeltype']) $label['labeltype'] = $r['labeltype'];
            return $label;
        }
        if ($labelName != 'Standard') return $label;
    }
    $qtxt = "select $valg from grupper where art = 'LABEL'";
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $label['labeltext'] = $r[$valg];
    return $label;
}

/**
 * Writes one label back to storage.
 *
 * Item labels go to the labels table, which is what lager/labelprint.php reads, and are mirrored
 * into grupper.box1 so the pre-4.0 fallback cannot serve a stale template. Address labels only go
 * to grupper. Rows left with a NULL account_id by older inserts are healed to 0 on update, since
 * labelprint.php only ever selects account_id 0.
 *
 * @param string $valg      'box1' for item labels, 'box2' for address labels
 * @param string $labelName Name of the label to write
 * @param string $labelText Full label template
 * @param string $labelType 'sheet' or 'label'
 * @return void
 */
function saveLabelText($valg, $labelName, $labelText, $labelType) {
    $labelText = db_escape_string($labelText);
    $labelType = db_escape_string($labelType);
    $labelName = db_escape_string($labelName);
    if ($valg != 'box1' || $labelName == 'Standard') {
        $qtxt = "select id from grupper where art = 'LABEL'";
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $qtxt = "update grupper set $valg = '$labelText' where id = '$r[id]'";
        } else {
            $qtxt = "insert into grupper (art, $valg) values ('LABEL', '$labelText')";
        }
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
    }
    if ($valg != 'box1') return;
    $qtxt = "select id from labels where labelname = '$labelName'";
    $qtxt.= " and (account_id = '0' or account_id is null)";
    $qtxt.= " order by account_id is null";
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        $qtxt = "update labels set labeltext = '$labelText', labeltype = '$labelType', account_id = '0' where id = '$r[id]'";
    } else {
        $qtxt = "insert into labels (account_id, labelname, labeltype, labeltext) values ('0', '$labelName', '$labelType', '$labelText')";
    }
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

function parseLabelTemplate($labelText) {
    $parsed = array(
        'cols'                  => 1,
        'rows'                  => 1,
        'txtlen'                => 50,
        'width'                 => '38.1',
        'height'                => '21.2',
        'font_size'             => '12',
        'margin_top'            => '7',
        'margin_left'           => '3',
        'show_varenr'           => false,
        'show_varemrk'          => false,
        'show_beskrivelse'      => false,
        'show_pris'             => false,
        'show_barcode'          => false,
        'varenr_font_size'      => '12',
        'varemrk_font_size'     => '12',
        'beskrivelse_font_size' => '12',
        'pris_font_size'        => '12'
    );
    
    if (empty($labelText)) return $parsed;
    
    // Parse $cols, $rows, $txtlen from first lines
    if (preg_match('/\$cols=(\d+);/', $labelText, $matches)) {
        $parsed['cols'] = $matches[1];
    }
    if (preg_match('/\$rows=(\d+);/', $labelText, $matches)) {
        $parsed['rows'] = $matches[1];
    }
    if (preg_match('/\$txtlen=(\d+);/', $labelText, $matches)) {
        $parsed['txtlen'] = $matches[1];
    }
    
    // Parse CSS dimensions. Accepts a comma decimal separator too (and normalizes it to a dot) in
    // case a template written before the generateLabelTemplate() comma-normalization fix already
    // has one stored - generateLabelTemplate() itself never writes a comma, so this is a read-side
    // safety net, not the primary fix.
    if (preg_match('/width:\s*([0-9.,]+)mm/', $labelText, $matches)) {
        $parsed['width'] = str_replace(',', '.', $matches[1]);
    }
    if (preg_match('/height:\s*([0-9.,]+)mm/', $labelText, $matches)) {
        $parsed['height'] = str_replace(',', '.', $matches[1]);
    }
    if (preg_match('/font-size:\s*([0-9.,]+)px/', $labelText, $matches)) {
        $parsed['font_size'] = str_replace(',', '.', $matches[1]);
        // Set default font sizes for all elements
        $parsed['varenr_font_size'] = $parsed['font_size'];
        $parsed['varemrk_font_size'] = $parsed['font_size'];
        $parsed['beskrivelse_font_size'] = $parsed['font_size'];
        $parsed['pris_font_size'] = $parsed['font_size'];
    }
    if (preg_match('/margin-top:\s*([0-9.,]+)mm/', $labelText, $matches)) {
        $parsed['margin_top'] = str_replace(',', '.', $matches[1]);
    }
    if (preg_match('/margin-left:\s*([0-9.,]+)mm/', $labelText, $matches)) {
        $parsed['margin_left'] = str_replace(',', '.', $matches[1]);
    }
    
    // Check what fields are shown
    $parsed['show_varenr']      = strpos($labelText,  '$varenr')      !== false;
    $parsed['show_varemrk']     = strpos($labelText,  '$varemrk')     !== false;
    $parsed['show_beskrivelse'] = (strpos($labelText, '$beskrivelse') !== false || strpos($labelText, '$minbeskrivelse') !== false);
    $parsed['show_pris']        = (strpos($labelText, '$pris')        !== false || strpos($labelText, '$minpris')        !== false);
    $parsed['show_barcode']     = strpos($labelText,  '$img')         !== false;
    
    // Parse individual font sizes for each element
    if (preg_match('/<p>(.*?)<\/p>/s', $labelText, $matches)) {
        $content = $matches[1];
        $lines   = explode('<br>', $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check for varenr with specific font size. [^<]*? (not .*?) keeps the match inside
            // a single <span>...</span> - $varenr and $varemrk can share one line
            // ("<span ...>$varenr</span> / <span ...>$varemrk</span>"), and .*? doesn't stop at
            // a tag boundary, so it would happily match across into the *other* span and report
            // its font-size instead (MB-18 review: locks out a label the editor itself just wrote).
            if (preg_match('/<span[^>]*font-size:\s*([0-9.]+)px[^>]*>[^<]*?\$varenr[^<]*?<\/span>/i', $line, $fontMatches)) {
                $parsed['varenr_font_size'] = $fontMatches[1];
            }

            // Check for varemrk with specific font size
            if (preg_match('/<span[^>]*font-size:\s*([0-9.]+)px[^>]*>[^<]*?\$varemrk[^<]*?<\/span>/i', $line, $fontMatches)) {
                $parsed['varemrk_font_size'] = $fontMatches[1];
            }

            // Check for beskrivelse with specific font size
            if (preg_match('/<span[^>]*font-size:\s*([0-9.]+)px[^>]*>[^<]*?\$(min)?beskrivelse[^<]*?<\/span>/i', $line, $fontMatches)) {
                $parsed['beskrivelse_font_size'] = $fontMatches[1];
            }

            // Check for pris with specific font size
            if (preg_match('/<span[^>]*font-size:\s*([0-9.]+)px[^>]*>[^<]*?[Pp]ris[^<]*?\$(min)?pris[^<]*?<\/span>/i', $line, $fontMatches)) {
                $parsed['pris_font_size'] = $fontMatches[1];
            }
        }
    }

    // Extract custom text with individual font sizes
    if (preg_match('/<p>(.*?)<\/p>/s', $labelText, $matches)) {
        $content         = $matches[1];
        $lines           = explode('<br>', $content);
        $customLineCount = 1;
        foreach ($lines as $line) {
            $line = trim($line);
            // A line is one of the fixed generated lines (and NOT custom text) only if it
            // actually contains one of the template variables/markup generateLabelTemplate()
            // emits - not merely the words "pris"/"$"/"img" as plain text. The old check
            // excluded any custom line containing those words at all (e.g. "Pris pr. stk",
            // "Kun $99"), silently dropping legitimate custom text the editor's own UI accepts
            // (MB-18 review).
            $isFixedLine = preg_match('/\$varenr|\$varemrk|\$minbeskrivelse|\$beskrivelse|\$minpris|\$pris|<img/i', $line);
            if (!empty($line) && !$isFixedLine) {

                // Check if this line has a specific font-size
                if (preg_match('/<span[^>]*font-size:\s*([0-9.]+)px[^>]*>([^<]*?)<\/span>/i', $line, $spanMatches)) {
                    $parsed["custom_text_$customLineCount"]        = trim(strip_tags($spanMatches[2]));
                    $parsed["custom_text_{$customLineCount}_size"] = $spanMatches[1];
                } else {
                    $parsed["custom_text_$customLineCount"]        = trim(strip_tags($line));
                    $parsed["custom_text_{$customLineCount}_size"] = $parsed['font_size'];
                }
                $customLineCount++;
                if ($customLineCount > 5) break;
            }
        }
    }
    
    return $parsed;
} # endfunc parseLabelTemplate

function generateLabelTemplate($data) {
    // $minbeskrivelse/$minpris are the default: they show the customer's own text and price from
    // Mit salg, and lager/labelprint_includes/newlabel.php falls back to the item's own when
    // printing without an account, so they work in both places where $beskrivelse/$pris only work
    // from the item card.

    // width/height/font_size/margin_top/margin_left are free-text form fields (not type=number),
    // so a Danish "3,5" reaches here as-is; a comma there isn't valid CSS to begin with, and
    // parseLabelTemplate()'s [0-9.]+ regexes can't read it back either, locking the label out of
    // the visual editor on the very next load (MB-18 review) - normalize before it's ever written.
    foreach (array('width', 'height', 'font_size', 'margin_top', 'margin_left') as $dimensionField) {
        if (isset($data[$dimensionField])) $data[$dimensionField] = str_replace(',', '.', $data[$dimensionField]);
    }

    $template = "\$cols={$data['cols']};\n";
    $template.= "\$rows={$data['rows']};\n";
    $template.= "\$txtlen={$data['txtlen']};\n";
    $template.= "<top>\n<style>\n";
    $template.= "#main {\n";
    $template.= "width: 100%;\n";
    $template.= "overflow:hidden;\n";
    $template.= "margin-top: {$data['margin_top']}mm;\n";
    $template.= "margin-bottom: 0mm;\n";
    $template.= "margin-right: 0mm;\n";
    $template.= "margin-left: {$data['margin_left']}mm;}\n\n";
    
    $template.= "p {\n";
    $template.= "width: {$data['width']}mm;\n";
    $template.= "display: inline-block;\n";
    $template.= "height: {$data['height']}mm;\n";
    $template.= "padding-bottom:0px;\n";
    $template.= "margin-top: 0mm;\n";
    $template.= "margin-bottom: 0mm;\n";
    $template.= "margin-right: 0mm;\n";
    $template.= "margin-left: 1mm;\n";
    $template.= "font-size: {$data['font_size']}px}\n\n";
    
    if ($data['show_barcode']) {
        $template.= "img {\n";
        $template.= "width: 90%;\n";
        $template.= "height: 5mm;\n";
        $template.= "margin-left:-4px}\n";
    }
    
    $template.= "</style>\t\n";
    $template.= "<div id=\"main\">\n";
    $template.= "</top>\n\n";
    
    $template.= "<p>\n";
    
    // Add content based on selections with individual font sizes
    if ($data['show_varenr'] && $data['show_varemrk']) {
        $varenrSize  = $data['varenr_font_size'];
        $varemrkSize = $data['varemrk_font_size'];
        if ($varenrSize == $varemrkSize && $varenrSize == $data['font_size']) {
            $template .= "\$varenr / \$varemrk<br>\n";
        } else {
            $template .= "<span style='font-size: {$varenrSize}px'>\$varenr</span> / <span style='font-size: {$varemrkSize}px'>\$varemrk</span><br>\n";
        }
    } elseif ($data['show_varenr']) {
        $fontSize = $data['varenr_font_size'];
        if ($fontSize != $data['font_size']) {
            $template .= "<span style='font-size: {$fontSize}px'>\$varenr</span><br>\n";
        } else {
            $template .= "\$varenr<br>\n";
        }
    } elseif ($data['show_varemrk']) {
        $fontSize = $data['varemrk_font_size'];
        if ($fontSize != $data['font_size']) {
            $template .= "<span style='font-size: {$fontSize}px'>\$varemrk</span><br>\n";
        } else {
            $template .= "\$varemrk<br>\n";
        }
    }
    
    if ($data['show_beskrivelse']) {
        $fontSize = $data['beskrivelse_font_size'];
        if ($fontSize != $data['font_size']) {
            $template .= "<span style='font-size: {$fontSize}px'>\$minbeskrivelse</span><br>\n";
        } else {
            $template .= "\$minbeskrivelse<br>\n";
        }
    }
    
    // Add custom text lines with individual font sizes
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($data["custom_text_$i"])) {
            $fontSize = isset($data["custom_text_{$i}_size"]) ? $data["custom_text_{$i}_size"] : $data['font_size'];
            if ($fontSize != $data['font_size']) {
                $template .= "<span style='font-size: {$fontSize}px'>{$data["custom_text_$i"]}</span><br>\n";
            } else {
                $template .= "{$data["custom_text_$i"]}<br>\n";
            }
        }
    }
    
    if ($data['show_pris']) {
        $fontSize = $data['pris_font_size'];
        if ($fontSize != $data['font_size']) {
            $template .= "<span style='font-size: {$fontSize}px'>Pris \$minpris</span><br>\n";
        } else {
            $template .= "Pris \$minpris<br>\n";
        }
    }
    
    if ($data['show_barcode']) {
        $template .= "<img src='\$img'><br>\n";
    }
    
    $template .= "</p>\n\n";
    $template .= "<bottom>\n";
    $template .= "</div>\n";
    $template .= "/bottom;";

    return $template;
} # endfunc generateLabelTemplate

/**
 * True if regenerating $labelText through the visual editor's own field model
 * (parseLabelTemplate() -> generateLabelTemplate()) reproduces it exactly (modulo
 * whitespace and the legacy $beskrivelse/$pris variable names, see below).
 * parseLabelTemplate() only understands a fixed, narrow set of CSS properties and
 * content lines - anything else in the template (custom CSS like transform/rotate,
 * margin shorthand, extra markup, more than 5 content lines, a hand-written raw-HTML
 * structure, an imported Brother/Dymo template, ...) is invisible to it, so
 * generateLabelTemplate() silently drops it on the very next visual-editor save.
 * Saving via the visual editor must be refused whenever this returns false, or
 * "changing one setting" ends up discarding the customer's whole template (MB-18).
 *
 * generateLabelTemplate() only ever writes $minbeskrivelse/$minpris, never the older
 * $beskrivelse/$pris (deliberate, see the 20260824 history entry) - so a template using
 * only the old names would otherwise never round-trip even though nothing about it is
 * actually unrepresentable. That's exactly what opdat_4.0.php's upgrade migration wrote
 * into every existing install's Standard label, so canonicalize the old names to the
 * new ones before comparing.
 *
 * @param string $labelText Full label template text, as stored in labels.labeltext /
 *                          grupper.box1.
 * @return bool True if the template is safe to edit visually (an empty template counts
 *              as safe - nothing to lose); false if it has content the visual editor's
 *              model can't reproduce and must stay in raw-HTML mode.
 */
function labelTemplateEditableVisually($labelText) {
    if (empty($labelText)) return true; // nothing to lose on a brand new label
    $canonical   = str_replace(array('$beskrivelse', '$pris'), array('$minbeskrivelse', '$minpris'), $labelText);
    $regenerated = generateLabelTemplate(parseLabelTemplate($labelText));
    $normalize   = function ($s) { return preg_replace('/\s+/', ' ', trim($s)); };
    return $normalize($regenerated) === $normalize($canonical);
} # endfunc labelTemplateEditableVisually

/**
 * Applies a visual-editor save for one label, refusing when the label's CURRENT stored
 * template has content the visual editor's field model can't reproduce (MB-18) - this is
 * the authoritative guard a form submit cannot bypass; systemdata/diverse.php's $saveLabel
 * POST handler is a thin wrapper around this function, not a reimplementation of it, so
 * tests exercising this function exercise the real save path.
 *
 * @param string $valg      'box1' for item labels, 'box2' for address labels
 * @param string $labelName Name of the label being saved
 * @param array  $postData  Raw $_POST-shaped visual-editor form fields (cols, rows, txtlen,
 *                          width, height, font_size, margin_top, margin_left, show_*,
 *                          *_font_size, custom_text_N, custom_text_N_size, labelType)
 * @return bool True if the save was applied; false if refused - nothing is written to
 *              storage in that case.
 */
function saveVisualLabelEdit($valg, $labelName, $postData) {
    $existingLabel = loadLabelText($valg, $labelName);
    if (!labelTemplateEditableVisually($existingLabel['labeltext'])) return false;

    // The safe 3-arg if_isset($array, $default, $key) form is required throughout - the
    // show_* checkboxes are genuinely absent from $_POST whenever unchecked (that's how HTML
    // checkboxes work), and the eager-dereference 1/2-arg form (if_isset($postData['key'], ...))
    // evaluates $postData['key'] before if_isset() ever runs, warning on the very undefined key
    // it's supposed to guard against.
    $formData = array(
        'cols'                  => if_isset($postData, 1, 'cols'),
        'rows'                  => if_isset($postData, 1, 'rows'),
        'txtlen'                => if_isset($postData, 50, 'txtlen'),
        'width'                 => if_isset($postData, '38.1', 'width'),
        'height'                => if_isset($postData, '21.2', 'height'),
        'font_size'             => if_isset($postData, '12', 'font_size'),
        'margin_top'            => if_isset($postData, '7', 'margin_top'),
        'margin_left'           => if_isset($postData, '3', 'margin_left'),
        'show_varenr'           => if_isset($postData, null, 'show_varenr')      == 'on',
        'show_varemrk'          => if_isset($postData, null, 'show_varemrk')     == 'on',
        'show_beskrivelse'      => if_isset($postData, null, 'show_beskrivelse') == 'on',
        'show_pris'             => if_isset($postData, null, 'show_pris')        == 'on',
        'show_barcode'          => if_isset($postData, null, 'show_barcode')     == 'on',
        // Individual font sizes for each element
        'varenr_font_size'      => if_isset($postData, if_isset($postData, '12', 'font_size'), 'varenr_font_size'),
        'varemrk_font_size'     => if_isset($postData, if_isset($postData, '12', 'font_size'), 'varemrk_font_size'),
        'beskrivelse_font_size' => if_isset($postData, if_isset($postData, '12', 'font_size'), 'beskrivelse_font_size'),
        'pris_font_size'        => if_isset($postData, if_isset($postData, '12', 'font_size'), 'pris_font_size')
    );

    // Add custom text lines with individual font sizes
    for ($i = 1; $i <= 5; $i++) {
        $formData["custom_text_$i"] = if_isset($postData, '', "custom_text_$i");
        $formData["custom_text_{$i}_size"] = if_isset($postData, $formData['font_size'], "custom_text_{$i}_size");
    }

    $generatedTemplate = generateLabelTemplate($formData);
    $labelType         = if_isset($postData, 'sheet', 'labelType');
    saveLabelText($valg, $labelName, $generatedTemplate, $labelType);
    return true;
} # endfunc saveVisualLabelEdit

}
