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
?>

<script src='../javascript/popupManager.js'></script>
<script type='text/javascript'>
    // Alignment/Precision images
    const alignments = Array.from([
        "",
        "../ikoner/paper.png",
        "../ikoner/checkmrk.png"        
    ]);
    const checkboxImg = Array.from([
        "../ikoner/circle_no_check_filled.png",
        "../ikoner/circle_check_filled.png"
    ])

    const columns = ColumnInfo.fromPositionalArray([
        ["ka. id - hidden", "kasse_id", "style='display: none;'", "style='display:none;'", 'kasse_id'],
        ["pf. id - hidden", "pf_id", "style='display: none;'", "style='display:none;'", 'pf_id'],
        ["kl. id", "kladde_id", "style='display: none;'", "style='display:none;'", 'kladde_id'],
        ["Match", (row) => `<img src='${checkboxImg[row.beloeb_match]}' title='amount'/><img src='${checkboxImg[row.date_match]}' title='date'/>`, "class='bilags-alignment'", "class='bilags-alignment'"],
        ["Kl. Date.", "file_date", "class='bilags-kladde-date'"],
        ["Bi. Date.", "pool_date", "class='bilags-date'"],
        ["Subject", "subject", "class='bilags-subject'"],
        ["Description", "description", "class='bilags-description'"],
        ["Attachment", "filename", "class='bilags-attach'", "class='attachment'", "filename"],
        ["kl. $", "amount", "class='kladde-amount' title='in the currency, '"],
        ["Bilag $.", "amount", "class='bilags-amount'"],
        ["Reciever", "invoice_number", "class='bilags-rec'"],
        ["valuta", "currency", "class='bilags-valuta'"],
    ]);

    const popupStyle = {}; // Defaults match system

    /**
     * Attaches All Invoices to the register
     */
    async function AttachAll(resultRows){
        const groups = new Map();
        for (const element of resultRows) {
            if (!groups.has(element.filename)) groups.set(element.filename, []);
            groups.get(element.filename).push(element);
        }

        for (const [filename, elements] of groups) {
            const primary = elements[0];
            const otherIds = elements.slice(1).map(e => e.kasse_id).join(',');
            const body = new URLSearchParams({
                source:    'kassekladde',
                kladde_id: primary.kladde_id,
                sourceId:  primary.kasse_id,
                openPool:  '1',
                insertFile:'1',
                poolFiles: filename,
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
    
    const popuper = new PopupManager(columns, popupStyle, AttachAll, "Attach selected");
    popuper.onResult.push(function(container){
        container.querySelectorAll('.autocomplete-item').forEach(element => {
            element.addEventListener('mouseover', function(e) { showPreview(element, e); });
            element.addEventListener('mousedown', function(e) { hidePreview(); });
        });
        document.querySelector("#background-dimmer").addEventListener('mousedown', function(e) { hidePreview(); });
    });
    function openPopup(){
        let params = new URLSearchParams(document.location.search);
        let kladde_id = params.get("kladde_id");

        fetch(`./kassekladde_includes/fetchbilagsmatch.php?kladde_id=${kladde_id}`)
            .then(res => res.json())
            .then(data => {
                popuper.popup(data, "Select Attachments");
            });
    }
</script>
<style>
    #popup-results tbody tr:nth-child(even) {
        background: #ededed;
    }
    #popup-results tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    #popup-results tbody tr {
        overflow: hidden;
    }

    #popup-results thead tr th {
        text-align: center;
    }

    .saldi-button {
        <?php echo $buttonStyle ?>
    }

    #popup-header {
        display: inline-block;
        * {
            padding: 2px;
        }
    }

    .popup-no-results{
        width: 50vw;
    }

    #popup-header-title {
        float: left;
    }
    #popupcontainer-calls{
        float: right;
    }
    .bilags-alignment img{
        width: 20px;
    }
    .popup-checkmark th{
        width: 30px;
    }
    .bilags-alignment th{
        width: 40px;
    }
    .bilags-kladde-date th{
        width: 70px;
    }
    .bilags-date th{
        width: 70px;
    }
    .bilags-subject th{
        width: 120px;
    }
    .bilags-description th{
        width: 75px;
    }
    .bilags-attach th{
        width: 200px;
    }
    .kladde-amount th{
        width: 50px;
    }
    .bilags-amount th{
        width: 50px;
    }
    .bilags-rec th{
        width: 50px;
    }
    .bilags-valuta th{
        width: 30px;
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
		var filename = element.querySelectorAll('td')[9].innerHTML;
		
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
				filepath = "<?php echo $puljeFolder; ?>" + filename;
				title.innerHTML = filename;
				
				// Check file extension
				var ext = filepath.split('.').pop().toLowerCase();
				
				if (ext === 'pdf') {
					content.innerHTML = '<embed src=\"' + filepath + '\" type=\"application/pdf\" style=\"width:480px;height:550px;\">';
				} else if (['jpg', 'jpeg', 'png', 'gif'].indexOf(ext) !== -1) {
					content.innerHTML = '<img src=\"' + filepath + '\" style=\"max-width:480px;max-height:550px;display:block;margin:0 auto;\">';
				} else {
					content.innerHTML = '<div class=\"preview-loading\">Forhåndsvisning ikke tilgængelig</div>';
				}
			}
			
			popup.classList.add('active');
			movePreview(event);
		}, 300);
	}
	
	function hidePreview() {
        console.log("tried hiding");
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
    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
    .header { background: $buttonColor; color: $buttonTxtColor; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
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