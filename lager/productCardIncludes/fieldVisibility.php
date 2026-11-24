<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/productCardIncludes/fieldVisibility.php --- ver 5.1.1 --- 2026-11-14 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Display-only show/hide panel for the product card sections.
// The choice is stored in the browser (localStorage) per company db and user,
// so nothing changes in the database and the fields still post on save.
// Sections are matched by id or class pcSec* set in varekort.php,
// notesEtc.php and showExpirySettings.php.
// Texts 3380-3384 live in importfiler/tekster.csv (da/en/no).
// 20260902 Sawaneh Created this file.
// 20260902 Sawaneh Numeric findtekst ids (csv holds the texts) and a
//                  dismissable first-time hint bubble pointing at the gear.
// 20260902 Sawaneh Storage keys now include brugernavn as well so the choice
//                  never leaks between users sharing a browser.
// 20260902 Sawaneh CodeRabbit (PR #545): Units label via findtekst(1259) and
//                  'Show all' is a real button for keyboard accessibility.
// 20260903 CL/NTR  Texts renumbered 5130-5134 -> 3380-3384 (free rows in tekster.csv).
// 20260921 Sawaneh The Udløbsdato row is left out when varekort.php does not render that box
//                  (batchExpiryEnabled off, or the item's group has no batch control).
// 20261114 CL/ASR Colli, Enheder, Mængderabatter, Diverse, Kategorier and Varianter are hidden until the user chooses in the panel.
// 20261114 CL/ASR Vis alle stores an empty list, so it still shows every section.
// 20261114 CL/ASR In menu S the gear is the button next to Ny in the top line (varekort.php sets $pcVisDocked).
// 20261114 CL/ASR The panel and the hint are placed from the gear button, and the hint's button says Ok (text 8795).
// 20261114 CL/ASR The Diverse section is called Minimum/maximum (items.card.minmax), as its box on the card.
// 20261114 CL/ASR The panel's foot links to Lagerstyring & kostpris in the settings (items.stock) instead of Vis alle.
// 20261114 CL/ASR Minimum/maximum (pcSecMisc) is three sections, Beholdning, Lokation and Salg, and a stored choice that hid it hides all three.
// 20261114 CL/ASR The rows follow the order of the boxes on the card, so Grupper comes after Tilbud.
// 20261114 CL/ASR On an item that is not stock-managed, Beholdning and Lokation stay in the panel greyed out with the note Kun for lagerførte varer.
// 20261114 CL/ASR The first-time hint bubble at the gear is gone (owner's wish); texts 3383 and 8795 are no longer used here.
// 20261114 CL/ASR Instead a small amber dot on the gear says there is something new, until the user first opens the panel.
// 20261114 CL/ASR The dot uses the bubble's old storage key, so a user who dismissed the bubble does not get it.

/**
 * @var string $db         company database name (includes/connect.php)
 * @var int    $bruger_id  logged-in user id (includes/online.php)
 * @var string $brugernavn logged-in user name (includes/online.php)
 * @var int    $sprog_id   language id (includes/online.php)
 */
$pcVisSections = array(
	'pcSecPrices'       => findtekst(2017, $sprog_id),
	'pcSecOffer'        => findtekst(812, $sprog_id),
	'pcSecGroups'       => findtekst(2037, $sprog_id),
	'pcSecColli'        => 'Colli', // heading in showColli.php is 'Colli' in all languages
	'pcSecUnits'        => findtekst(1259, $sprog_id),
	'pcSecQtyDiscounts' => findtekst(2041, $sprog_id),
	'pcSecStock'        => !empty($operation) ? findtekst('items.card.operation|Montage', $sprog_id) : findtekst('980|Beholdning', $sprog_id),
	'pcSecLocation'     => findtekst('2045|Lokation', $sprog_id),
	'pcSecSales'        => findtekst('5544|Salg', $sprog_id),
	'pcSecCategories'   => findtekst(388, $sprog_id),
	'pcSecVariants'     => findtekst(472, $sprog_id),
	'pcSecExpiry'       => findtekst('5001|Udl&oslash;bsdato', $sprog_id),
	'pcSecNotes'        => findtekst(391, $sprog_id),
);
// varekort.php only renders the expiry box when batch/expiry is enabled for the item's group
if (empty($batchExpiryEnabled) || trim((string) ($batchItem ?? '')) != 'on') {
	unset($pcVisSections['pcSecExpiry']);
}
$pcVisStoreKey = 'saldiPcHidden_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $db . '_' . $bruger_id . '_' . $brugernavn);
$pcVisTitle    = findtekst('3380|Tilpas visning', $sprog_id);
// Small dot on the gear until the panel has been opened once (key of the former first-time bubble)
$pcVisNewKey   = 'saldiPcHint_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $db . '_' . $bruger_id . '_' . $brugernavn);
$pcVisNewTip   = findtekst('view.gear_new|Tilpas visning: vælg hvad der vises', $sprog_id);
$pcVisHelp     = findtekst('3381|Vælg hvilke felter der vises ...', $sprog_id);
// The foot links to the stock settings (same section as the gear after the breadcrumbs), for users who may read them
$pcVisMoreUrl  = '';
if (!function_exists('perm_can') || perm_can('settings.items', 'read')) {
	$pcVisMoreUrl = '../systemdata/settingsSection.php?s=items.stock&back=' . rawurlencode('/lager/varekort.php?id=' . (int) $id)
		. '&back_label=' . rawurlencode(findtekst('566|Varekort', $sprog_id));
}
$pcVisMore     = findtekst('items.card.more_settings|Se flere lagerindstillinger', $sprog_id);
$pcVisLeave    = findtekst('items.card.leave_unsaved|Varekortet har ændringer, der ikke er gemt. Vil du gå videre uden at gemme?', $sprog_id);
// Sections hidden for a user who has not chosen yet
// Boxes that only stock-managed items have (Lagerført on the item group): shown greyed out with a note when missing
$pcVisStockOnly = array('pcSecStock', 'pcSecLocation');
$pcVisStockNote = findtekst('items.card.stock_only|Kun for lagerførte varer', $sprog_id);
$pcVisStockTip  = findtekst('items.card.stock_only_tip|Sæt flueben i Lagerført på varens varegruppe. Det gøres under Varegrupper i Indstillinger.', $sprog_id);
$pcVisDefaultHidden = array('pcSecColli', 'pcSecUnits', 'pcSecQtyDiscounts', 'pcSecStock', 'pcSecLocation', 'pcSecSales', 'pcSecCategories', 'pcSecVariants');
?>
<style>
.pcVisHidden { display: none !important; }
#pcVisToggle.pcVisFab {
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
#pcVisToggle.pcVisFab:hover { background: #1d5cb4; transform: rotate(60deg); }
#pcVisToggle svg { transition: transform .25s ease; }
#pcVisToggle:not(.pcVisFab) { position: relative; }
.pcVisNewDot { position: absolute; top: 3px; right: 4px; width: 7px; height: 7px; border-radius: 50%; background: #E0913A; pointer-events: none; }
#pcVisToggle:hover svg { transform: rotate(60deg); }
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
.pcVisRow.pcVisNA { cursor: default; color: #99a; text-align: left; }
.pcVisRow.pcVisNA:hover { background: none; }
.pcVisRow.pcVisNA input { cursor: default; }
.pcVisNote { display: none; font-size: 10px; }
.pcVisRow.pcVisNA .pcVisNote { display: block; }
.pcVisFoot {
	border-top: 1px solid #dde5f0;
	padding: 7px 10px;
	text-align: right;
}
.pcVisFoot a {
	color: #15488f;
	cursor: pointer;
	text-decoration: underline;
}
</style>
<?php if (empty($pcVisDocked)) { ?>
<button type="button" id="pcVisToggle" class="pcVisFab" title="<?php print $pcVisTitle; ?>">&#9881;</button>
<?php } ?>
<div id="pcVisPanel">
	<div class="pcVisHead"><?php print $pcVisTitle; ?></div>
	<div class="pcVisIntro"><?php print $pcVisHelp; ?></div>
	<div class="pcVisList">
<?php
foreach ($pcVisSections as $pcVisKey => $pcVisLabel) {
	$pcVisStock = in_array($pcVisKey, $pcVisStockOnly, true);
	print "\t\t<label class='pcVisRow' data-row='$pcVisKey'" . ($pcVisStock ? " data-stock-only='1' title=\"" . str_replace('"', '&quot;', $pcVisStockTip) . "\"" : '') . ">";
	print "<input type='checkbox' data-section='$pcVisKey' checked>";
	print "<span>$pcVisLabel" . ($pcVisStock ? "<span class='pcVisNote'>$pcVisStockNote</span>" : '') . "</span></label>\n";
}
?>
	</div>
<?php if ($pcVisMoreUrl !== '') { ?>
	<div class="pcVisFoot"><a href="<?php print htmlspecialchars($pcVisMoreUrl, ENT_QUOTES); ?>" id="pcVisMore" data-leave="<?php print str_replace(array('&', '"', '<'), array('&amp;', '&quot;', '&lt;'), $pcVisLeave); ?>"><?php print $pcVisMore; ?></a></div>
<?php } ?>
</div>
<script>
(function () {
	var storeKey = '<?php print $pcVisStoreKey; ?>';
	var defaultHidden = <?php print json_encode($pcVisDefaultHidden); ?>;
	var panel = document.getElementById('pcVisPanel');
	var toggleBtn = document.getElementById('pcVisToggle');
	toggleBtn.setAttribute('data-new-tip', <?php print "'" . str_replace(array('\\', "'", '<'), array('\\\\', "\\'", '\\x3c'), $pcVisNewTip) . "'"; ?>);
	var boxes = panel.querySelectorAll('input[data-section]');

	function readHidden() {
		try {
			var stored = localStorage.getItem(storeKey);
			if (stored === null) { return defaultHidden.slice(); }
			var list = JSON.parse(stored) || [];
			// pcSecMisc (Diverse) became three sections
			if (list.indexOf('pcSecMisc') !== -1) {
				list = list.filter(function (k) { return k !== 'pcSecMisc'; }).concat(['pcSecStock', 'pcSecLocation', 'pcSecSales']);
			}
			return list;
		}
		catch (e) { return defaultHidden.slice(); }
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
				var label = box.closest('label');
				if (label.hasAttribute('data-stock-only')) {
					// not a stock-managed item: the row stays, greyed out, so the user sees why the box is missing
					label.classList.add('pcVisNA');
					box.checked = false;
					box.disabled = true;
				} else {
					label.classList.add('pcVisHidden');
				}
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
	var moreLink = document.getElementById('pcVisMore');
	if (moreLink) {
		// leaves the card the way Tilbage and Ny do: unsaved changes are confirmed first
		moreLink.addEventListener('click', function (e) {
			if (typeof confirmClose !== 'function') { return; }
			e.preventDefault();
			confirmClose(moreLink.href, moreLink.getAttribute('data-leave'));
		});
	}
	var newKey = '<?php print $pcVisNewKey; ?>';
	var newDot = null, oldTitle = toggleBtn.title;
	try {
		if (!localStorage.getItem(newKey)) {
			newDot = document.createElement('span');
			newDot.className = 'pcVisNewDot';
			toggleBtn.appendChild(newDot);
			toggleBtn.title = toggleBtn.getAttribute('data-new-tip') || oldTitle;
		}
	} catch (e) {}
	toggleBtn.addEventListener('click', function () {
		if (newDot) {
			newDot.remove();
			newDot = null;
			toggleBtn.title = oldTitle;
			try { localStorage.setItem(newKey, '1'); } catch (e) {}
		}
		var r = toggleBtn.getBoundingClientRect();
		panel.style.top = (r.bottom + 6) + 'px';
		panel.classList.toggle('pcVisOpen');
	});
	document.addEventListener('click', function (e) {
		if (panel.classList.contains('pcVisOpen')
			&& !panel.contains(e.target) && !toggleBtn.contains(e.target)) {
			panel.classList.remove('pcVisOpen');
		}
	});
	applyAll();
})();
</script>
