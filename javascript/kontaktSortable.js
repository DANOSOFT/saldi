// --- javascript/kontaktSortable.js --- ver 5.0.0 --- 2026-10-27 ---
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
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261027 CL/MMR SD-779 Drag-and-drop ordering of the contact persons on the debitor and kreditor card.
//                 Sortable (javascript/Sortable.min.js) is bound to <tbody id="kontaktpersoner">.
//                 After a drop the Pos. fields (posnr[x]) are renumbered 1..N in the new order, rows marked "-" keep their "-", and the hidden kontakt field gets the top name.
//                 Saving is the card's ordinary Gem/opdatér, so the existing posnr rules ("-" deletes, pos. 1 is the primary contact) are untouched.

(function () {
	function renumber(tbody) {
		var rows = tbody.querySelectorAll('tr');
		var n = 0;
		var firstName = null;
		for (var i = 0; i < rows.length; i++) {
			var input = rows[i].querySelector('input[name^="posnr["]');
			if (!input) {
				continue;
			}
			// A "-" marks the contact for deletion on save; it keeps its "-" and takes no number.
			if (input.value.trim() === '-') {
				continue;
			}
			n++;
			input.value = n;
			if (n === 1) {
				firstName = rows[i].querySelector('td:nth-child(2)');
			}
		}
		// The hidden "kontakt" field carries the primary contact's name to adresser.kontakt
		// (the debitor card also derives it from pos. 1 on save, the kreditor card does not).
		var kontakt = tbody.querySelector('input[name="kontakt"]');
		if (kontakt && firstName) {
			kontakt.value = firstName.textContent.trim();
		}
	}

	function init() {
		var tbody = document.getElementById('kontaktpersoner');
		if (!tbody || typeof Sortable === 'undefined') {
			return;
		}
		new Sortable(tbody, {
			handle: '.kontakt-drag-handle',
			animation: 150,
			onEnd: function (evt) {
				// Dropped back where it came from: nothing changed, so do not flag the card as edited.
				if (evt.oldIndex === evt.newIndex) {
					return;
				}
				renumber(tbody);
				if (typeof docChange !== 'undefined') {
					docChange = true;
				}
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
