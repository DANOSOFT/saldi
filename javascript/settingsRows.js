// ---- javascript/settingsRows.js --- lap 5.0.0 --- 2026.10.06 ---
// 20261006 Sawaneh A row action can ask for one value in its dialog (data-input), e.g. the new control account.
// 20261005 Sawaneh 4d: row action buttons (set active, delete a year with data...) post through the dialog form;
//                  a danger-zone action asks for the user's password there.
// 20261005 Sawaneh Settings redesign phase 4c (spec §8.2): the row editor. Enter moves down the column (and adds a
//                  row at the bottom), Tab moves right, Esc reverts the cell, Ctrl/Cmd+S saves; a spreadsheet paste
//                  into a new row fills it and the rows below. Trash asks first - and refuses with the usage count
//                  when the row is referenced, offering "Markér inaktiv" where the table supports it.
(function () {
	'use strict';
	var cfg = window.SALDI_SETTINGS || {};
	var form = document.getElementById('st-form');
	if (!form) { return; }
	var bar = document.getElementById('st-savebar'), status = document.getElementById('st-status'), undo = document.getElementById('st-undo'), dot = document.getElementById('st-dot');
	var dialog = document.getElementById('st-dialog'), backdrop = document.getElementById('st-backdrop'), dform = document.getElementById('st-dialog-form');
	var snack = document.getElementById('st-snack');
	var counter = 0, returnFocus = null;

	function say(text) {
		if (!snack) { return; }
		snack.textContent = text;
		snack.hidden = false;
		window.clearTimeout(say.t);
		say.t = window.setTimeout(function () { snack.hidden = true; }, 2500);
	}
	function fmt(text, value) { return String(text || '').replace('%s', value); }
	function controls() { return Array.prototype.slice.call(form.querySelectorAll('.st-rin, .st-rcheck, .st-field [data-control]')); }
	function valueOf(c) { return c.type === 'checkbox' ? (c.checked ? '1' : '') : String(c.value).trim(); }
	function origOf(c) { return c.dataset.orig !== undefined ? String(c.dataset.orig).trim() : (c.closest('.st-field') ? (function (f) { var o = f.querySelector('input[name^="o["]'); return o ? String(o.value).trim() : ''; })(c.closest('.st-field')) : ''); }
	function isNewRow(c) { var tr = c.closest('tr'); return tr && tr.classList.contains('st-new'); }

	function refresh() {
		var changed = 0;
		controls().forEach(function (c) {
			var ch = isNewRow(c) ? (valueOf(c) !== '' && c.type !== 'checkbox') : (valueOf(c) !== origOf(c));
			var cell = c.closest('.st-rc') || c.closest('.st-field');
			if (cell) { cell.classList.toggle('st-changed', ch); }
			if (ch) { changed++; }
		});
		var dirty = changed > 0;
		window.docChange = dirty;
		if (undo) { undo.disabled = !dirty; }
		if (dot) { dot.hidden = !dirty; }
		if (bar) {
			bar.hidden = !dirty;
			if (status) { status.textContent = changed === 1 ? (cfg.unsaved1 || '') : fmt(cfg.unsavedN, changed); }
		}
	}

	function addRow(card, focusCol) {
		var tpl = card.querySelector('template[data-row-template]');
		if (!tpl) { return null; }
		counter++;
		var html = tpl.innerHTML.replace(/__N__/g, 'n' + counter);
		var tbody = card.querySelector('tbody');
		var table = card.querySelector('table');
		tbody.insertAdjacentHTML('beforeend', html);
		if (table.hidden) { table.hidden = false; }
		var empty = card.querySelector('.st-rempty');
		if (empty) { empty.hidden = true; }
		var tr = tbody.lastElementChild;
		var inputs = tr.querySelectorAll('.st-rin, .st-rcheck');
		var target = inputs[Math.min(focusCol || 0, inputs.length - 1)];
		if (target) { target.focus(); }
		refresh();
		return tr;
	}

	// §8.13: the standard set goes in as new rows to edit and save, like rows added by hand.
	function seedRows(card, json) {
		var rows;
		try { rows = JSON.parse(json); } catch (e) { return; }
		rows.forEach(function (values) {
			var tr = addRow(card, 0);
			if (!tr) { return; }
			Object.keys(values).forEach(function (col) {
				var el = tr.querySelector('[name$="[' + col + ']"]:not([type=hidden])') || tr.querySelector('[name$="[' + col + ']"]');
				if (!el) { return; }
				if (el.type === 'checkbox') { el.checked = values[col] !== '' && values[col] !== '0'; }
				else { el.value = values[col]; }
				el.dispatchEvent(new Event('input', { bubbles: true }));
				el.dispatchEvent(new Event('change', { bubbles: true }));
			});
		});
		var hint = card.querySelector('.st-rhint');
		if (hint && cfg.seeded) { hint.textContent = cfg.seeded; }
		refresh();
	}

	function cellIndex(c) {
		var tr = c.closest('tr');
		return Array.prototype.indexOf.call(tr.querySelectorAll('.st-rin, .st-rcheck'), c);
	}
	function moveDown(c) {
		var tr = c.closest('tr'), idx = cellIndex(c), next = tr.nextElementSibling;
		while (next && next.hidden) { next = next.nextElementSibling; }
		if (next) {
			var inputs = next.querySelectorAll('.st-rin, .st-rcheck');
			if (inputs[idx]) { inputs[idx].focus(); if (inputs[idx].select) { inputs[idx].select(); } }
			return;
		}
		addRow(c.closest('.st-rows'), idx);
	}

	form.addEventListener('keydown', function (e) {
		var c = e.target;
		if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
			e.preventDefault();
			if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
			return;
		}
		if (!c.classList || !(c.classList.contains('st-rin') || c.classList.contains('st-rcheck'))) { return; }
		if (e.key === 'Enter') {
			e.preventDefault();
			moveDown(c);
		} else if (e.key === 'Escape') {
			if (c.type === 'checkbox') { c.checked = origOf(c) !== ''; } else { c.value = c.dataset.orig || ''; }
			refresh();
		}
	});

	// A spreadsheet paste into a new row: tab-separated cells across the row, further lines into new rows.
	form.addEventListener('paste', function (e) {
		var c = e.target;
		if (!c.classList || !c.classList.contains('st-rin') || !isNewRow(c)) { return; }
		var text = (e.clipboardData || window.clipboardData).getData('text');
		if (!text || (text.indexOf('\t') < 0 && text.indexOf('\n') < 0)) { return; }
		e.preventDefault();
		var card = c.closest('.st-rows');
		var lines = text.replace(/\r/g, '').split('\n').filter(function (l) { return l.trim() !== ''; });
		var tr = c.closest('tr');
		var start = cellIndex(c);
		lines.forEach(function (line, li) {
			if (li > 0) { tr = addRow(card, 0); if (!tr) { return; } }
			var inputs = tr.querySelectorAll('.st-rin, .st-rcheck');
			line.split('\t').forEach(function (v, i) {
				var input = inputs[start + i];
				if (!input) { return; }
				if (input.type === 'checkbox') { input.checked = /^(1|on|ja|x|yes)$/i.test(v.trim()); }
				else if (input.tagName === 'SELECT') { input.value = v.trim(); }
				else { input.value = v.trim(); }
			});
		});
		refresh();
	});

	function openDialog(title, body, verb, values, danger, password, inputLabel) {
		returnFocus = document.activeElement;
		document.getElementById('st-dialog-title').textContent = title;
		document.getElementById('st-dialog-body').textContent = body;
		var ok = document.getElementById('st-dialog-ok');
		ok.textContent = verb || '';
		ok.hidden = !verb;
		ok.classList.toggle('st-btn-danger-solid', !!danger);
		dform.elements.action.value = values.action || '';
		dform.elements.table.value = values.table || '';
		dform.elements.id.value = values.id || '';
		dform.elements.value.value = values.value || '';
		var pw = document.getElementById('st-dialog-pw');
		if (pw) { pw.hidden = !password; pw.querySelector('input').value = ''; pw.querySelector('input').required = !!password; }
		dialog.hidden = false;
		backdrop.hidden = false;
		var arg = document.getElementById('st-dialog-arg');
		if (arg) { arg.hidden = !inputLabel; arg.querySelector('span').textContent = inputLabel || ''; arg.querySelector('input').value = ''; arg.querySelector('input').required = !!inputLabel; }
		if (inputLabel && arg) { arg.querySelector('input').focus(); } else if (password && pw) { pw.querySelector('input').focus(); } else { document.getElementById('st-dialog-cancel').focus(); }
	}
	function closeDialog() {
		dialog.hidden = true;
		backdrop.hidden = true;
		if (returnFocus && returnFocus.focus) { returnFocus.focus(); }
	}
	document.getElementById('st-dialog-cancel').addEventListener('click', closeDialog);
	backdrop.addEventListener('click', closeDialog);
	dform.addEventListener('submit', function () { window.docChange = false; });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !dialog.hidden) { closeDialog(); } });

	form.addEventListener('click', function (e) {
		var add = e.target.closest('[data-add]');
		if (add) { addRow(add.closest('.st-rows'), 0); return; }
		var seed = e.target.closest('[data-seed]');
		if (seed) { seedRows(seed.closest('.st-rows'), seed.getAttribute('data-seed')); return; }
		var del = e.target.closest('[data-del]');
		if (del) {
			var tr = del.closest('tr'), card = del.closest('.st-rows');
			if (tr.classList.contains('st-new')) {
				tr.remove();
				var tbody = card.querySelector('tbody');
				if (!tbody.children.length) { card.querySelector('table').hidden = true; var em = card.querySelector('.st-rempty'); if (em) { em.hidden = false; } }
				refresh();
				return;
			}
			var code = tr.dataset.code || '';
			if (tr.dataset.use) {
				var canInactive = card.dataset.inactive === '1' && !tr.classList.contains('st-row-inactive');
				openDialog(fmt(cfg.cannotTitle, code), fmt(cfg.usedBody, tr.dataset.use), canInactive ? cfg.inactiveVerb : '', { action: 'row_inactive', table: card.dataset.table, id: tr.dataset.row, value: '1' }, false);
				document.getElementById('st-dialog-cancel').textContent = cfg.close || '';
				return;
			}
			document.getElementById('st-dialog-cancel').textContent = cfg.cancel || '';
			openDialog(fmt(cfg.deleteTitle, code), cfg.deleteBody || '', cfg.deleteVerb, { action: 'row_delete', table: card.dataset.table, id: tr.dataset.row }, true);
			return;
		}
		var act = e.target.closest('[data-row-action]');
		if (act) {
			var tr3 = act.closest('tr'), card3 = act.closest('.st-rows');
			var vals = { action: 'row_action', table: card3.dataset.table, id: tr3.dataset.row, value: act.dataset.rowAction };
			if (act.dataset.title) {
				document.getElementById('st-dialog-cancel').textContent = cfg.cancel || '';
				openDialog(act.dataset.title, act.dataset.body || '', act.dataset.verb || '', vals, act.dataset.danger === '1', act.dataset.password === '1', act.dataset.input || '');
				return;
			}
			dform.elements.action.value = vals.action;
			dform.elements.table.value = vals.table;
			dform.elements.id.value = vals.id;
			dform.elements.value.value = vals.value;
			window.docChange = false;
			dform.submit();
			return;
		}
		var ina = e.target.closest('[data-inactive]');
		if (ina) {
			var tr2 = ina.closest('tr'), card2 = ina.closest('.st-rows');
			dform.elements.action.value = 'row_inactive';
			dform.elements.table.value = card2.dataset.table;
			dform.elements.id.value = tr2.dataset.row;
			dform.elements.value.value = ina.dataset.inactive;
			window.docChange = false;
			dform.submit();
		}
	});
	form.addEventListener('change', function (e) {
		var t = e.target.closest('[data-show-inactive]');
		if (t) { t.closest('.st-rows').classList.toggle('st-show-inactive', t.checked); }
	});

	form.addEventListener('input', refresh);
	form.addEventListener('change', refresh);
	form.addEventListener('submit', function () { window.docChange = false; });
	if (undo) {
		undo.addEventListener('click', function () {
			controls().forEach(function (c) {
				if (isNewRow(c)) { return; }
				if (c.type === 'checkbox') { c.checked = origOf(c) !== ''; } else { c.value = c.dataset.orig !== undefined ? c.dataset.orig : origOf(c); }
			});
			form.querySelectorAll('tr.st-new').forEach(function (tr) {
				var card = tr.closest('.st-rows');
				tr.remove();
				if (!card.querySelector('tbody').children.length) { card.querySelector('table').hidden = true; var em = card.querySelector('.st-rempty'); if (em) { em.hidden = false; } }
			});
			refresh();
		});
	}
	window.addEventListener('beforeunload', function (e) {
		if (window.docChange) { e.preventDefault(); e.returnValue = ''; }
	});
	document.querySelectorAll('[data-dismiss]').forEach(function (b) { b.addEventListener('click', function () { b.parentNode.remove(); }); });

	var firstError = form.querySelector('[aria-invalid="true"]');
	if (firstError) { firstError.scrollIntoView({ block: 'center' }); try { firstError.focus({ preventScroll: true }); } catch (err) {} }
	refresh();
	if (window.history && window.history.replaceState) {
		var url = window.location.pathname + window.location.search.replace(/([?&])(saved|moved|err)=[^&]*/g, '$1').replace(/[?&]+$/, '').replace(/([?&])&+/g, '$1');
		window.history.replaceState(null, '', url);
	}
})();
