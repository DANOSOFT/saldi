<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde.php --- ver 5.0.0 --- 2026-04-10 ---
// verifying fork target points to DANOSOFT/saldi
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260507 NTR - Added batch invoice matching (this)
// 20260513 PK - Changed padding from 20px to 8px
// 20260721 CL/SZ - Rewrote the popup per the new design: Type/Dato/Bilag/Tekst/Beløb/Konto/
//                   Modkonto/Valuta/Præcision/link-icon columns, a live "{n} valgt · {m}
//                   fundet" summary, high-confidence rows pre-selected, Annullér/OK footer,
//                   and a "0 fundet" empty state. All labels now go through findtekst()
//                   (fixes the "Reciever" typo by not carrying that column over). Removed a
//                   leftover console.log from hidePreview().
// 20260721 CL/SZ - AttachAll() fix: it was firing one parallel request per selected row,
//                   which races on insertDoc.php's rename() when one pool file matches
//                   several lines (e.g. an invoice split across journal lines). Restored
//                   filename-grouped requests with "targetSourceIds" for sibling lines -
//                   insertDoc.php already supports this (see includes/docsIncludes/insertDoc.php),
//                   it just wasn't being used correctly.
// 20260721 CL/SZ - Second pass on the popup to match the mockup exactly, not just its column
//                   set: header close button is now an icon-only "x" (popupManager.js was
//                   rendering the closeLabel text there instead), the "{n} valgt · {m} fundet"
//                   summary now sits directly under the header instead of in the footer, and
//                   the footer now shows both Annullér (outline) and OK (system button color
//                   via $buttonStyle) instead of OK only - was hardcoded red/green
//                   (#F44336/#4CAF50), matching neither the mockup nor this app's button
//                   convention.
?>

<?php
$bm_title          = findtekst('5043|Bilagsmatch', $sprog_id);
$bm_col_type       = findtekst('5040|Type', $sprog_id);
$bm_col_dato       = findtekst('5044|Dato', $sprog_id);
$bm_col_bilag      = findtekst('5045|Bilag', $sprog_id);
$bm_col_tekst      = findtekst('5046|Tekst', $sprog_id);
$bm_col_beloeb     = findtekst('5047|Beløb', $sprog_id);
$bm_col_konto      = findtekst('5032|Konto', $sprog_id);
$bm_col_modkonto   = findtekst('5033|Modkonto', $sprog_id);
$bm_col_valuta     = findtekst('5034|Valuta', $sprog_id);
$bm_col_praecision = findtekst('5035|Præcision', $sprog_id);
$bm_cancel         = findtekst('5036|Annullér', $sprog_id);
$bm_summary_tpl    = findtekst('5037|$n match valgt · $m fundet', $sprog_id);
$bm_empty_title    = findtekst('5038|0 fundet', $sprog_id);
$bm_empty_message  = findtekst('5039|Ingen forslag til match fundet for denne kladde.', $sprog_id);
$bm_link_tooltip   = findtekst('5041|Klik for at forhåndsvise/åbne dokumentet', $sprog_id);
$bm_lookup_tooltip = findtekst('5042|Slå konto op', $sprog_id);
?>

<script src='../javascript/popupManager.js'></script>
<script type='text/javascript'>
    // Præcision icons: green = high confidence, yellow = medium (no orange asset exists
    // in ikoner/ yet - swap this for a real amber icon if one gets added later).
    const precisionIcons = {
        high:   '../ikoner/circle_check_filled.png',
        medium: '../ikoner/circle_check_filled_yellow.png',
    };
    // paper.png/clip.png in ikoner/ both contain the same unrelated shield glyph, not a
    // document or link icon - inlined SVGs here instead of pointing Type/link at those.
    // encodeURIComponent leaves single quotes unescaped, so the SVG markup below uses
    // double quotes for its own attributes - single quotes here would prematurely close
    // the (single-quoted) src='...' attribute the <img> tags are rendered with.
    const typeIcon   = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#2b6cb0" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/></svg>`
    );
    // opslag.png is a document+magnifier combo icon that reads as clutter at 20px -
    // a plain magnifying glass (inline SVG, same reasoning as typeIcon/linkIcon above)
    // matches the mockup's lookup icon more clearly.
    const lookupIcon = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#5a6b8c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.5-4.5"/></svg>`
    );
    const linkIcon   = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#2b6cb0" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14a5 5 0 0 1 0-7l3-3a5 5 0 0 1 7 7l-1.5 1.5"/><path d="M14 10a5 5 0 0 1 0 7l-3 3a5 5 0 0 1-7-7l1.5 1.5"/></svg>`
    );

    function bmFormatAmount(row) {
        var n = parseFloat(row.amount);
        if (isNaN(n)) return '';
        return n.toLocaleString('da-DK', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    const columns = ColumnInfo.fromPositionalArray([
        ["ka. id - hidden", "kasse_id", "style='display:none;'", "style='display:none;'", 'kasse_id'],
        ["pf. id - hidden", "pf_id", "style='display:none;'", "style='display:none;'", 'pf_id'],
        ["kl. id - hidden", "kladde_id", "style='display:none;'", "style='display:none;'", 'kladde_id'],
        ["filename - hidden", "filename", "style='display:none;'", "class='bilags-filename' style='display:none;'", 'filename'],
        ["<?= $bm_col_type ?>", (row) => `<img src='${typeIcon}' title='${row.filename ?? ''}' class='bilags-type-icon'/>`, "class='bilags-type'", "class='bilags-type'", 'type'],
        ["<?= $bm_col_dato ?>", "file_date", "class='bilags-date'", null, 'dato'],
        ["<?= $bm_col_bilag ?>", "bilag", "class='bilags-bilag'", null, 'bilag'],
        ["<?= $bm_col_tekst ?>", "description", "class='bilags-description'", "class='bilags-description'", 'tekst'],
        ["<?= $bm_col_beloeb ?>", (row) => bmFormatAmount(row), "class='bilags-amount'", null, 'beloeb'],
        ["<?= $bm_col_konto ?>", "konto", "class='bilags-konto'", null, 'konto'],
        ["<?= $bm_col_modkonto ?>", (row) => `${row.modkonto ?? ''} <img src='${lookupIcon}' title='<?= $bm_lookup_tooltip ?>' class='bilags-lookup-icon'/>`, "class='bilags-modkonto'", null, 'modkonto'],
        ["<?= $bm_col_valuta ?>", "currency", "class='bilags-valuta'", null, 'valuta'],
        ["<?= $bm_col_praecision ?>", (row) => `<img src='${precisionIcons[row.precision] ?? precisionIcons.medium}' title='${row.score ?? ''}%'/>`, "class='bilags-precision'", "class='bilags-precision'", 'praecision'],
        ["", (row) => `<a href='#' class='bilags-link' title='<?= $bm_link_tooltip ?>' onclick='return false;'><img src='${linkIcon}'/></a>`, "class='bilags-linkcol'", "class='bilags-linkcol'", 'link'],
    ]);

    // PopupManager's shared default dialog style is a light-gray background (#eeeef0)
    // with thick beveled borders - override just for this popup to match the Bilagsmatch
    // mockup's clean white dialog, without touching that shared default for other popups.
    const popupStyle = {
        background: '#ffffff',
        border: 'none',
        borderRadius: '10px',
        boxShadow: '0 12px 40px rgba(0,0,0,0.25)',
        padding: '20px 24px',
        width: '960px',
        maxWidth: '95vw',
        // Default position:absolute anchors to the DOCUMENT (scrolls with the page) -
        // fixed anchors to the actual viewport instead, so this floats in view correctly
        // regardless of how far down the page you were scrolled when you opened it.
        position: 'fixed',
        // Horizontally centered (self-correcting for the actual rendered width via
        // translateX, not a fixed-pixel calc() that only centers at exactly 960px).
        // Floats near the top of the viewport rather than vertically centered.
        left: '50%',
        top: '40px',
        transform: 'translateX(-50%)',
        // Default is `overflow: auto` on the whole dialog, which scrolls the header and
        // summary bar along with the table. Make this a flex column instead so only
        // #popup-results (the table) scrolls, keeping header/summary/footer pinned.
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
    };

    /**
     * Attaches selected rows via includes/documents.php, grouping rows that share the
     * same pool file into a single request.
     *
     * insertDoc.php (included via documents.php -> docPool()) moves the pool file's
     * physical file on disk with a single rename() per file, then fans the resulting
     * documents-table row out to sibling lines via the "targetSourceIds" POST param -
     * firing one parallel request per row for a file matched to several lines (e.g. one
     * invoice split across multiple journal lines) races that same rename() and only the
     * first request's attach survives. Group by filename and pass the rest as
     * targetSourceIds instead, one request per distinct file.
     */
    async function AttachAll(resultRows){
        const groups = new Map();
        for (const row of resultRows) {
            if (!groups.has(row.filename)) groups.set(row.filename, []);
            groups.get(row.filename).push(row);
        }

        for (const [filename, rows] of groups) {
            const primary = rows[0];
            const otherIds = rows.slice(1).map(r => r.kasse_id).join(',');
            const body = new URLSearchParams({
                source:     'kassekladde',
                kladde_id:  primary.kladde_id,
                sourceId:   primary.kasse_id,
                openPool:   '1',
                insertFile: '1',
                poolFiles:  filename,
            });
            if (otherIds) body.set('targetSourceIds', otherIds);
            await fetch(`../includes/documents.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    body.toString(),
            });
        }
        location.replace(location.pathname + location.search);
    }

    const dimmerStyle = {
        // Default position:absolute anchors the dimmer to the document, not the
        // viewport - if the page is taller than one screen (it is here, via the sticky
        // footer) and you're scrolled down, the dimmer doesn't reach the bottom of what
        // you're actually looking at. Fixed always covers the full visible viewport.
        position: 'fixed',
    };

    const popuper = new PopupManager(columns, popupStyle, AttachAll, "OK", dimmerStyle, {
        closeLabel: <?= json_encode($bm_cancel) ?>,
        checkboxHeaderLabel: '',
        preSelectFn: (row) => row.precision === 'high',
        summaryFn: (selected, total) => <?= json_encode($bm_summary_tpl) ?>.replace('$n', selected).replace('$m', total),
        noResultsHtml: `<div class="popup-no-results">
            <div class="popup-no-results-title"><?= $bm_empty_title ?></div>
            <div class="popup-no-results-message"><?= $bm_empty_message ?></div>
        </div>`,
    });
    popuper.onResult.push(function(container){
        container.querySelectorAll('.autocomplete-item').forEach(element => {
            element.addEventListener('mouseover', function(e) { showPreview(element, e); });
            element.addEventListener('mousedown', function(e) { hidePreview(); });
            // Without this, moving the cursor off a row (to the header, summary bar, or
            // anywhere else in the popup) left the preview stuck open - nothing was
            // telling it to close except clicking a row or the background dimmer.
            element.addEventListener('mouseleave', function(e) { hidePreview(); });
        });
        // Click-outside-to-close: scoped to this popup only (not a PopupManager-wide
        // default, since other popups in the app share that component). Discards without
        // calling exitCall, same as the header x / footer Annullér.
        document.querySelector("#background-dimmer").addEventListener('mousedown', function(e) {
            hidePreview();
            popuper.closeDropdown();
        });
    });

    popuper.onClose.push(function(container){
        hidePreview();
    });

    function openPopup(){
        let params = new URLSearchParams(document.location.search);
        let kladde_id = params.get("kladde_id");

        fetch(`./kassekladde_includes/fetchbilagsmatch.php?kladde_id=${kladde_id}`)
            .then(res => res.json())
            .then(data => {
                popuper.popup(data, <?= json_encode($bm_title) ?>);
            });
    }
</script>
<style>
    #popup-results tbody tr:nth-child(even) {
        background: #dce6f5;
    }
    #popup-results tbody tr:nth-child(odd) {
        background: #e8f0fa;
    }
    #popup-results tbody tr {
        overflow: hidden;
    }

    /* Horizontal separation between rows only - never vertical lines between columns.
       (border-collapse so the per-cell border-bottom below renders as one clean line
       per row instead of doubled/gapped borders between adjacent cells.) */
    #popup-results .popup-table {
        border-collapse: collapse;
        width: 100%;
    }
    #popup-results .popup-table td, #popup-results .popup-table th {
        border-left: none;
        border-right: none;
    }
    #popup-results .popup-table tbody td {
        border-bottom: 1px solid #dbe3ee;
    }

    /* Mockup left-aligns text-column headers; only the icon-only columns (Type,
       Præcision, the trailing link column) are centered. */
    #popup-results thead tr th {
        text-align: left;
    }
    #popup-results thead tr th.bilags-type,
    #popup-results thead tr th.bilags-precision,
    #popup-results thead tr th.bilags-linkcol {
        text-align: center;
    }

    .saldi-button {
        <?= $buttonStyle ?>
        cursor: pointer;
        padding: 6px 20px;
        font-size: 14px;
    }

    /* Footer: Annullér (outline) + OK (system button color, via .saldi-button/$buttonStyle) */
    .popup-footer {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 10px;
        padding: 10px 4px 4px;
        flex: 0 0 auto;
    }

    .popup-btn-secondary {
        background-color: #ffffff;
        color: #333;
        border: 1px solid #b8bec8;
    }

    /* Header: title centered across the dialog, small square "x" close button pinned
       to the top-right corner (matches the mockup, not a flex space-between layout). */
    #popup-header {
        position: relative;
        padding: 4px 0 12px;
        margin-bottom: 8px;
        border-bottom: 1px solid #e0e0e0;
        flex: 0 0 auto;
    }

    #popup-header-title {
        display: block;
        text-align: center;
        font-size: 28px;
        font-weight: 700;
    }

    .popup-close-x {
        position: absolute;
        top: 0;
        right: 0;
        width: 28px;
        height: 28px;
        line-height: 1;
        padding: 0;
        border: 1px solid #b8bec8;
        border-radius: 4px;
        background: #ffffff;
        color: #333;
        font-size: 18px;
        cursor: pointer;
    }
    .popup-close-x:hover {
        background: #f0f0f0;
    }

    #popup-summary-bar {
        font-weight: bold;
        padding: 4px 2px 8px;
        flex: 0 0 auto;
    }

    /* Only this element scrolls now (the outer dialog is overflow:hidden) - header,
       summary bar and footer stay pinned in view. */
    #popup-results {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
    }

    .popup-no-results{
        width: 50vw;
        text-align: center;
        padding: 30px 10px;
    }
    .popup-no-results-title {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 8px;
    }
    .popup-no-results-message {
        color: #666;
    }

    .bilags-type-icon, .bilags-lookup-icon, .bilags-linkcol img, .bilags-precision img {
        width: 20px;
    }
    .bilags-linkcol a {
        cursor: pointer;
    }
    #popup-results .popup-table th, #popup-results .popup-table td {
        padding: 4px 12px;
        line-height: 1.3;
    }
    #popup-results .popup-table td {
        white-space: nowrap;
    }
    #popup-results .popup-table td.bilags-description {
        white-space: normal;
    }
    /* Headers like "Voucher no." / "Contra account" were wrapping to 2 lines at the
       widths below, doubling every row's height across 13 rows - keep them on one
       line and let the table's auto layout widen the column instead. */
    #popup-results .popup-table th {
        white-space: nowrap;
    }
    .popup-checkmark th{
        width: 40px;
    }
    .bilags-type th{
        width: 60px;
    }
    .bilags-date th{
        width: 90px;
    }
    .bilags-bilag th{
        width: 80px;
    }
    .bilags-description th{
        width: 200px;
    }
    .bilags-amount th{
        width: 110px;
    }
    .bilags-konto th, .bilags-modkonto th{
        width: 100px;
    }
    .bilags-valuta th{
        width: 70px;
    }
    .bilags-precision th{
        width: 70px;
    }
</style>

<!-- Copied from Includes/Documents.php -->
<script>
	var previewTimeout = null;
	var currentPreviewPath = null;

    <?php

    if (file_exists('../owncloud')) $docFolder = '../owncloud';
    elseif (file_exists('../bilag')) $docFolder = '../bilag';
    elseif (file_exists('../documents')) $docFolder = '../documents';
    else $docFolder = '../bilag'; // Default fallback

    $puljeFolder = "$docFolder/$db/pulje/";
    ?>

	function showPreview(element, event) {
		var filenameCell = element.querySelector('.bilags-filename');
		var filename = filenameCell ? filenameCell.textContent : '';

		if (!filename) return;

		// Clear any existing timeout
		if (previewTimeout) clearTimeout(previewTimeout);

		// Delay showing preview slightly to avoid flickering
		previewTimeout = setTimeout(function() {
			var popup = document.getElementById('previewPopup');
			var content = document.getElementById('previewContent');
			var title = document.getElementById('previewTitle');

			// Only reload if different file
			if (title.innerHTML !== filename) {
				var filepath = "<?php echo $puljeFolder; ?>" + filename;
				title.innerHTML = filename;

				// Check file extension
				var ext = filepath.split('.').pop().toLowerCase();

				content.innerHTML = '<div class=\"preview-loading\">Indlæser...</div>';

				// Confirm the file actually exists before embedding it - otherwise a
				// missing pool file (no physical upload behind the DB row) renders the
				// server's raw 404 HTML page inside the embed instead of a clean message.
				fetch(filepath, { method: 'HEAD' }).then(function(res) {
					if (title.innerHTML !== filename) return; // hovered elsewhere meanwhile

					if (!res.ok) {
						content.innerHTML = '<div class=\"preview-loading\">Forhåndsvisning ikke tilgængelig</div>';
					} else if (ext === 'pdf') {
						content.innerHTML = '<embed src=\"' + filepath + '\" type=\"application/pdf\" style=\"width:480px;height:550px;\">';
					} else if (['jpg', 'jpeg', 'png', 'gif'].indexOf(ext) !== -1) {
						content.innerHTML = '<img src=\"' + filepath + '\" style=\"max-width:480px;max-height:550px;display:block;margin:0 auto;\">';
					} else {
						content.innerHTML = '<div class=\"preview-loading\">Forhåndsvisning ikke tilgængelig</div>';
					}
				}).catch(function() {
					if (title.innerHTML === filename) {
						content.innerHTML = '<div class=\"preview-loading\">Forhåndsvisning ikke tilgængelig</div>';
					}
				});
			}

			popup.classList.add('active');
			movePreview(event);
		}, 300);
	}

	function hidePreview() {
		if (previewTimeout) {
			clearTimeout(previewTimeout);
			previewTimeout = null;
		}
        var popup = document.getElementById('previewPopup');
		popup.classList.remove('active');
	}

	function movePreview(event) {
		var popup = document.getElementById('previewPopup');
		if (!popup.classList.contains('active')) return;

		var x = event.clientX + 20;
		var y = event.clientY - 100;

		// Keep within viewport
		var rect = popup.getBoundingClientRect();
		var viewportWidth = window.innerWidth;
		var viewportHeight = window.innerHeight;

		// If would go off right edge, show on left side of cursor
		if (x + 520 > viewportWidth) {
			x = event.clientX - 520;
		}

		// If would go off bottom, adjust y
		if (y + 620 > viewportHeight) {
			y = viewportHeight - 630;
		}

		// Don't go above viewport
		if (y < 10) y = 10;

		popup.style.left = x + 'px';
		popup.style.top = y + 'px';
	}
</script>

<style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 8px; } /* #20260513 */
    .header { background: <?= $buttonColor ?>; color: <?= $buttonTxtColor ?>; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    .header h2 { margin: 0; }
    .doc-list { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .doc-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #eee; position: relative; cursor: pointer; }
    .doc-item:hover { background: #f0f7ff; }
    .doc-info { flex: 1; }
    .doc-name { font-weight: bold; color: #333; }
    .doc-meta { font-size: 12px; color: #666; margin-top: 4px; }
    .link-btn { background: $buttonColor; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; flex-shrink: 0; }
    .link-btn:hover { opacity: 0.9; }
    .empty-msg { padding: 40px; text-align: center; color: #666; }
    .search-box { margin-bottom: 15px; }
    .search-box input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }

    /* Preview popup styles */
    .preview-popup {
        display: none;
        position: fixed;
        z-index: 1000;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        padding: 10px;
        max-width: 500px;
        max-height: 600px;
        overflow: hidden;
    }
    .preview-popup.active { display: block; }
    .preview-popup iframe, .preview-popup embed {
        width: 480px;
        height: 550px;
        border: none;
        border-radius: 4px;
    }
    .preview-popup .preview-header {
        padding: 8px;
        background: <?php echo $buttonColor ?>;
        color: white;
        border-radius: 4px 4px 0 0;
        margin: -10px -10px 10px -10px;
        font-size: 12px;
        font-weight: bold;
    }
    .preview-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 480px;
        height: 550px;
        background: #f5f5f5;
        color: #666;
        font-size: 14px;
    }
</style>

<!-- Preview popup container -->
<div id='previewPopup' class='preview-popup'>
<div class='preview-header' id='previewTitle'>Forhåndsvisning</div>
<div id='previewContent'><div class='preview-loading'>Indlæser...</div></div>
</div>
