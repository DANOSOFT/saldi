<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/productCardIncludes/fieldVisibility.php --- lap 5.0.0 --- 2026-09-02 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// Display-only show/hide panel for the product card sections.
// The choice is stored in the browser (localStorage) per company db and user,
// so nothing changes in the database and the fields still post on save.
// Sections are matched by id or class pcSec* set in varekort.php,
// notesEtc.php and showExpirySettings.php.
// Texts 5130-5134 live in importfiler/tekster.csv (da/en/no).
// 20260902 Sawaneh Created this file.
// 20260902 Sawaneh Numeric findtekst ids (csv holds the texts) and a
//                  dismissable first-time hint bubble pointing at the gear.
// 20260902 Sawaneh Storage keys now include brugernavn as well so the choice
//                  never leaks between users sharing a browser.

/**
 * @var string $db         company database name (includes/connect.php)
 * @var int    $bruger_id  logged-in user id (includes/online.php)
 * @var string $brugernavn logged-in user name (includes/online.php)
 * @var int    $sprog_id   language id (includes/online.php)
 */
$pcVisSections = array(
	'pcSecPrices'       => findtekst(2017, $sprog_id),
	'pcSecOffer'        => findtekst(812, $sprog_id),
	'pcSecColli'        => 'Colli',
	'pcSecUnits'        => 'Enheder',
	'pcSecGroups'       => findtekst(2037, $sprog_id),
	'pcSecQtyDiscounts' => findtekst(2041, $sprog_id),
	'pcSecMisc'         => findtekst(782, $sprog_id),
	'pcSecCategories'   => findtekst(388, $sprog_id),
	'pcSecVariants'     => findtekst(472, $sprog_id),
	'pcSecExpiry'       => findtekst('5001|Udl&oslash;bsdato', $sprog_id),
	'pcSecNotes'        => findtekst(391, $sprog_id),
);
$pcVisStoreKey = 'saldiPcHidden_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $db . '_' . $bruger_id . '_' . $brugernavn);
$pcVisHintKey  = 'saldiPcHint_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $db . '_' . $bruger_id . '_' . $brugernavn);
$pcVisTitle    = findtekst(5130, $sprog_id); // Tilpas visning
$pcVisHelp     = findtekst(5131, $sprog_id); // Vælg hvilke felter der vises ...
$pcVisShowAll  = findtekst(5132, $sprog_id); // Vis alle
$pcVisHint     = findtekst(5133, $sprog_id); // Nyt! Klik på tandhjulet ...
$pcVisGotIt    = findtekst(5134, $sprog_id); // Forstået
?>
<style>
.pcVisHidden { display: none !important; }
#pcVisToggle {
	position: fixed;
	right: 14px;
	top: 110px;
	z-index: 9998;
	width: 38px;
	height: 38px;
	border: none;
	border-radius: 50%;
	background: #15488f;
	color: #fff;
	font-size: 19px;
	line-height: 38px;
	text-align: center;
	cursor: pointer;
	box-shadow: 0 2px 6px rgba(0,0,0,.35);
	transition: transform .25s ease, background .2s ease;
	padding: 0;
}
#pcVisToggle:hover { background: #1d5cb4; transform: rotate(60deg); }
#pcVisPanel {
	position: fixed;
	right: 14px;
	top: 156px;
	z-index: 9999;
	width: 235px;
	background: #fff;
	border: 1px solid #c6d2e4;
	border-radius: 8px;
	box-shadow: 0 6px 18px rgba(21,72,143,.25);
	font-family: Verdana, Arial, sans-serif;
	font-size: 11px;
	color: #333;
	opacity: 0;
	visibility: hidden;
	transform: translateX(12px);
	transition: opacity .2s ease, transform .2s ease, visibility .2s;
}
#pcVisPanel.pcVisOpen { opacity: 1; visibility: visible; transform: translateX(0); }
.pcVisHead {
	background: #15488f;
	color: #fff;
	font-weight: bold;
	font-size: 12px;
	padding: 8px 10px;
	border-radius: 7px 7px 0 0;
}
.pcVisIntro { padding: 7px 10px 4px 10px; color: #667; line-height: 1.4; }
.pcVisList { max-height: 320px; overflow-y: auto; padding: 4px 6px 6px 6px; }
.pcVisRow {
	display: flex;
	align-items: center;
	gap: 7px;
	padding: 5px 6px;
	border-radius: 5px;
	cursor: pointer;
	user-select: none;
}
.pcVisRow:hover { background: #eef3fa; }
.pcVisRow input { margin: 0; cursor: pointer; }
.pcVisRow .pcVisOff { color: #99a; text-decoration: line-through; }
.pcVisFoot {
	border-top: 1px solid #dde5f0;
	padding: 7px 10px;
	text-align: right;
}
.pcVisFoot a { color: #15488f; cursor: pointer; text-decoration: underline; }
#pcVisHint {
	position: fixed;
	right: 60px;
	top: 104px;
	z-index: 9997;
	width: 215px;
	background: #15488f;
	color: #fff;
	border-radius: 8px;
	box-shadow: 0 6px 18px rgba(21,72,143,.35);
	font-family: Verdana, Arial, sans-serif;
	font-size: 11px;
	line-height: 1.45;
	padding: 10px 12px;
	display: none;
}
#pcVisHint.pcVisHintShow { display: block; animation: pcVisHintIn .35s ease; }
#pcVisHint:after {
	content: '';
	position: absolute;
	right: -6px;
	top: 18px;
	width: 12px;
	height: 12px;
	background: #15488f;
	transform: rotate(45deg);
}
#pcVisHintOk {
	display: inline-block;
	margin-top: 8px;
	background: #fff;
	color: #15488f;
	border: none;
	border-radius: 4px;
	padding: 4px 12px;
	font-size: 11px;
	font-weight: bold;
	cursor: pointer;
}
#pcVisHintOk:hover { background: #dce7f7; }
@keyframes pcVisHintIn {
	from { opacity: 0; transform: translateX(10px); }
	to   { opacity: 1; transform: translateX(0); }
}
</style>
<button type="button" id="pcVisToggle" title="<?php print $pcVisTitle; ?>">&#9881;</button>
<div id="pcVisPanel">
	<div class="pcVisHead"><?php print $pcVisTitle; ?></div>
	<div class="pcVisIntro"><?php print $pcVisHelp; ?></div>
	<div class="pcVisList">
<?php
foreach ($pcVisSections as $pcVisKey => $pcVisLabel) {
	print "\t\t<label class='pcVisRow' data-row='$pcVisKey'>";
	print "<input type='checkbox' data-section='$pcVisKey' checked>";
	print "<span>$pcVisLabel</span></label>\n";
}
?>
	</div>
	<div class="pcVisFoot"><a id="pcVisShowAll"><?php print $pcVisShowAll; ?></a></div>
</div>
<div id="pcVisHint">
	<?php print $pcVisHint; ?><br>
	<button type="button" id="pcVisHintOk"><?php print $pcVisGotIt; ?></button>
</div>
<script>
(function () {
	var storeKey = '<?php print $pcVisStoreKey; ?>';
	var panel = document.getElementById('pcVisPanel');
	var toggleBtn = document.getElementById('pcVisToggle');
	var boxes = panel.querySelectorAll('input[data-section]');

	function readHidden() {
		try { return JSON.parse(localStorage.getItem(storeKey)) || []; }
		catch (e) { return []; }
	}
	function writeHidden(list) {
		try { localStorage.setItem(storeKey, JSON.stringify(list)); }
		catch (e) {}
	}
	function targets(key) {
		return document.querySelectorAll('#' + key + ', .' + key);
	}
	function applySection(key, show) {
		targets(key).forEach(function (el) {
			el.classList.toggle('pcVisHidden', !show);
		});
		var row = panel.querySelector("label[data-row='" + key + "'] span");
		if (row) { row.classList.toggle('pcVisOff', !show); }
	}
	function applyAll() {
		var hidden = readHidden();
		boxes.forEach(function (box) {
			var key = box.getAttribute('data-section');
			if (!targets(key).length) {
				box.closest('label').classList.add('pcVisHidden');
				return;
			}
			box.checked = hidden.indexOf(key) === -1;
			applySection(key, box.checked);
		});
	}

	boxes.forEach(function (box) {
		box.addEventListener('change', function () {
			var key = box.getAttribute('data-section');
			var hidden = readHidden().filter(function (k) { return k !== key; });
			if (!box.checked) { hidden.push(key); }
			writeHidden(hidden);
			applySection(key, box.checked);
		});
	});
	document.getElementById('pcVisShowAll').addEventListener('click', function () {
		writeHidden([]);
		applyAll();
	});
	var hint = document.getElementById('pcVisHint');
	var hintKey = '<?php print $pcVisHintKey; ?>';
	function dismissHint() {
		hint.classList.remove('pcVisHintShow');
		try { localStorage.setItem(hintKey, '1'); } catch (e) {}
	}
	try {
		if (!localStorage.getItem(hintKey)) { hint.classList.add('pcVisHintShow'); }
	} catch (e) {}
	document.getElementById('pcVisHintOk').addEventListener('click', dismissHint);

	toggleBtn.addEventListener('click', function () {
		dismissHint();
		panel.classList.toggle('pcVisOpen');
	});
	document.addEventListener('click', function (e) {
		if (panel.classList.contains('pcVisOpen')
			&& !panel.contains(e.target) && e.target !== toggleBtn) {
			panel.classList.remove('pcVisOpen');
		}
	});
	applyAll();
})();
</script>
