// javascript/settingsSection.js
// 20260929 Sawaneh Settings redesign phase 4a (spec §8.1, §8.3, §8.11, §8.12, §8.14): behaviour of a
//                  generated settings section - toggles, dependencies, save bar, deep links,
//                  lookups, confirmation dialog and keyboard shortcuts.
// 20260930 Sawaneh Field links open inside the shell and use ?field= (spec §8.11).
// 20261002 Sawaneh Hand-over 2 Oct (8.0): the save bar shows only while something is unsaved and counts the changes;
//                  a changed field is marked; a sub-section whose fields are all hidden is hidden too.
// 20261004 Sawaneh Skift on a stored secret opens its empty input (it did nothing on form sections).
(function () {
	'use strict';
	var cfg = window.SALDI_SETTINGS || {};
	var form = document.getElementById('st-form');
	if (!form) { return; }

	var status = document.getElementById('st-status');
	var bar = document.getElementById('st-savebar');
	var undo = document.getElementById('st-undo');
	var dot = document.getElementById('st-dot');
	var snack = document.getElementById('st-snack');
	var fields = Array.prototype.slice.call(form.querySelectorAll('.st-field'));
	var actions = Array.prototype.slice.call(form.querySelectorAll('.st-action'));
	var byKey = {};
	fields.forEach(function (f) { byKey[f.dataset.key] = f; });

	function control(field) { return field.querySelector('[data-control]'); }
	function valueOf(field) { var c = control(field); return c ? String(c.value).trim() : ''; }

	// The stored value, not what the field holds now: after a refused save the fields still
	// hold what the user typed, and that must count as unsaved.
	var initial = {};
	fields.forEach(function (f) {
		var o = f.querySelector('input[name^="o["]');
		initial[f.dataset.key] = o ? String(o.value).trim() : valueOf(f);
	});

	function setValue(field, value) {
		var c = control(field);
		if (!c) { return; }
		c.value = value;
		var sw = field.querySelector('.st-switch');
		if (sw) { sw.setAttribute('aria-checked', value === '1' ? 'true' : 'false'); }
		var resolved = field.querySelector('.st-resolved');
		if (resolved && value === '') { resolved.textContent = ''; }
	}

	// ------------------------------------------------------------ dependencies (P7)
	function ruleHolds(rule) {
		var parent = byKey[rule[1]];
		if (!parent) { return true; }
		var v = valueOf(parent);
		if (rule[0] === 'setting') { return (v === '1') === !!rule[2]; }
		if (rule[0] === 'setting_in') { return rule[2].indexOf(v) !== -1; }
		return true;
	}
	function applyRules() {
		fields.concat(actions).forEach(function (el) {
			if (!el.dataset.visibleIf) { return; }
			var rule;
			try { rule = JSON.parse(el.dataset.visibleIf); } catch (e) { return; }
			el.hidden = !ruleHolds(rule);
		});
		// A sub-section is shown only while one of its fields is.
		Array.prototype.forEach.call(form.querySelectorAll('.st-sect'), function (sect) {
			var items = sect.querySelectorAll('.st-field, .st-action');
			if (!items.length) { return; }
			sect.hidden = !Array.prototype.some.call(items, function (el) { return !el.hidden; });
		});
	}

	// ------------------------------------------------------------ save bar
	// Shown only while something is unsaved (settings redesign 8.0); a changed field gets a small dot.
	function refresh() {
		var changed = 0;
		fields.forEach(function (f) {
			var ch = valueOf(f) !== initial[f.dataset.key];
			f.classList.toggle('st-changed', ch);
			if (ch) { changed++; }
			var reset = f.querySelector('[data-reset]');
			if (reset) { reset.hidden = (valueOf(f) === f.dataset.default); }
		});
		var dirty = changed > 0;
		window.docChange = dirty;
		if (undo) { undo.disabled = !dirty; }
		if (dot) { dot.hidden = !dirty; }
		if (bar) {
			bar.hidden = !dirty;
			if (status) { status.textContent = changed === 1 ? cfg.unsaved1 : String(cfg.unsavedN).replace('%s', changed); }
		}
		applyRules();
	}

	form.addEventListener('click', function (e) {
		var sw = e.target.closest('.st-switch');
		if (sw && !sw.disabled) {
			setValue(sw.closest('.st-field'), sw.getAttribute('aria-checked') === 'true' ? '0' : '1');
			refresh();
			return;
		}
		var reset = e.target.closest('[data-reset]');
		if (reset) {
			var field = reset.closest('.st-field');
			setValue(field, field.dataset.default);
			refresh();
			return;
		}
		var change = e.target.closest('[data-secret-change]');
		if (change) {
			// A stored secret is never sent to the page: Skift opens an empty field, and empty means unchanged.
			var input = document.getElementById(change.getAttribute('aria-controls'));
			change.hidden = true;
			if (input) { input.hidden = false; input.focus(); }
			return;
		}
		var copy = e.target.closest('[data-copy]');
		if (copy) {
			var url = fieldLink(copy.closest('.st-field').dataset.key);
			if (navigator.clipboard) { navigator.clipboard.writeText(url).then(function () { say(cfg.copied); }); }
			return;
		}
		var run = e.target.closest('[data-run]');
		if (run) {
			openDialog(run.dataset.title, run.dataset.body, run.dataset.verb, { action: 'run', key: run.dataset.run }, run.dataset.blank === '1', run.classList.contains('st-btn-danger'), !!run.dataset.password, run.dataset.options);
		}
	});
	// Enter on a focused toggle does nothing; Space toggles (button default).
	form.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('st-switch')) { e.preventDefault(); }
	});
	// A colour swatch writes into its #rrggbb text field, and follows it.
	form.addEventListener('input', function (e) {
		var sw = e.target.closest('[data-swatch-for]');
		if (sw) {
			var txt = document.getElementById(sw.getAttribute('data-swatch-for'));
			if (txt && !txt.readOnly) { txt.value = sw.value; }
			return;
		}
		if (e.target.matches('.st-color [data-control]') && /^#[0-9a-f]{6}$/i.test(e.target.value)) {
			var swatch = e.target.parentNode.querySelector('[data-swatch-for]');
			if (swatch) { swatch.value = e.target.value.toLowerCase(); }
		}
	});
	form.addEventListener('input', refresh);
	form.addEventListener('change', refresh);
	form.addEventListener('submit', function () {
		window.docChange = false;
		var b = document.getElementById('st-save');
		if (b) { b.disabled = true; }
	});
	if (undo) {
		undo.addEventListener('click', function () {
			fields.forEach(function (f) { setValue(f, initial[f.dataset.key]); });
			refresh();
		});
	}
	window.addEventListener('beforeunload', function (e) {
		if (!window.docChange) { return; }
		e.preventDefault();
		e.returnValue = '';
	});

	// ------------------------------------------------------------ history: restore
	document.addEventListener('click', function (e) {
		var restore = e.target.closest('[data-restore]');
		if (restore) {
			openDialog(cfg.restoreTitle, cfg.restoreBody + ' ' + restore.dataset.value, cfg.restoreVerb, { action: 'revert', entry: restore.dataset.restore }, false);
		}
		var dismiss = e.target.closest('[data-dismiss]');
		if (dismiss) { dismiss.parentNode.hidden = true; }
	});

	// ------------------------------------------------------------ dialog (§8.3)
	var dialog = document.getElementById('st-dialog');
	var backdrop = document.getElementById('st-backdrop');
	var dform = document.getElementById('st-dialog-form');
	var returnFocus = null;
	function openDialog(title, body, verb, values, newTab, danger, password, options) {
		returnFocus = document.activeElement;
		// Checkboxes an action asks for (G1.4 reset: keep customers & suppliers, keep items), posted as opt[name].
		var opts = document.getElementById('st-dialog-opts');
		if (opts) {
			opts.textContent = '';
			var list = [];
			try { list = options ? JSON.parse(options) : []; } catch (err) { list = []; }
			list.forEach(function (o) {
				var lab = document.createElement('label');
				var box = document.createElement('input');
				box.type = 'checkbox';
				box.name = 'opt[' + o.name + ']';
				box.value = '1';
				lab.appendChild(box);
				lab.appendChild(document.createTextNode(' ' + o.label));
				opts.appendChild(lab);
			});
			opts.hidden = list.length === 0;
		}
		// A danger-zone action asks for the user's password (spec §8.3).
		var pw = document.getElementById('st-dialog-pw');
		if (pw) { pw.hidden = !password; pw.querySelector('input').value = ''; pw.querySelector('input').required = !!password; }
		document.getElementById('st-dialog-title').textContent = title;
		document.getElementById('st-dialog-body').textContent = body;
		document.getElementById('st-dialog-ok').textContent = verb;
		// The destructive verb is red (spec §8.3); Annullér keeps the default focus.
		document.getElementById('st-dialog-ok').classList.toggle('st-btn-danger-solid', !!danger);
		dform.elements.action.value = values.action || '';
		dform.elements.key.value = values.key || '';
		dform.elements.entry.value = values.entry || '';
		dform.target = newTab ? '_blank' : '';
		dialog.hidden = false;
		backdrop.hidden = false;
		document.getElementById('st-dialog-cancel').focus();
	}
	function closeDialog() {
		dialog.hidden = true;
		backdrop.hidden = true;
		if (returnFocus && returnFocus.focus) { returnFocus.focus(); }
	}
	document.getElementById('st-dialog-cancel').addEventListener('click', closeDialog);
	backdrop.addEventListener('click', closeDialog);
	dform.addEventListener('submit', function () {
		window.docChange = false;
		if (dform.target === '_blank') { window.setTimeout(closeDialog, 50); }
	});
	dialog.addEventListener('keydown', function (e) {
		if (e.key !== 'Tab') { return; }
		var f = dialog.querySelectorAll('button');
		var first = f[0], last = f[f.length - 1];
		if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
	});

	// ------------------------------------------------------------ shortcuts (§8.14)
	document.addEventListener('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
			e.preventDefault();
			var b = document.getElementById('st-save');
			if (b && !b.disabled) { form.requestSubmit ? form.requestSubmit() : form.submit(); }
		}
		if (e.key === 'Escape' && !dialog.hidden) { closeDialog(); }
	});

	// ------------------------------------------------------------ lookups (§8.1)
	Array.prototype.forEach.call(form.querySelectorAll('.st-lookup'), function (box) {
		var input = box.querySelector('input');
		var list = box.querySelector('.st-lookup-list');
		var resolved = box.querySelector('.st-resolved');
		var timer = null, active = -1, items = [];
		if (input.readOnly) { return; }
		function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; }
		function pick(i) {
			if (!items[i]) { return; }
			input.value = items[i].value;
			resolved.textContent = '· ' + items[i].name;
			close();
			refresh();
		}
		function show(rows) {
			items = rows;
			list.innerHTML = '';
			if (!rows.length) {
				var li = document.createElement('li');
				li.className = 'st-lookup-none';
				li.textContent = cfg.notFound;
				list.appendChild(li);
			}
			rows.forEach(function (row, i) {
				var li = document.createElement('li');
				li.setAttribute('role', 'option');
				li.textContent = row.value + ' · ' + row.name;
				li.addEventListener('mousedown', function (ev) { ev.preventDefault(); pick(i); });
				list.appendChild(li);
			});
			list.hidden = false;
			input.setAttribute('aria-expanded', 'true');
		}
		input.addEventListener('input', function () {
			resolved.textContent = '';
			window.clearTimeout(timer);
			var q = input.value.trim();
			if (q === '') { close(); return; }
			timer = window.setTimeout(function () {
				fetch(cfg.lookupUrl + '?type=' + encodeURIComponent(box.dataset.lookup) + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (data) { if (input.value.trim() === q) { show(data.results || []); } })
					.catch(close);
			}, 180);
		});
		input.addEventListener('keydown', function (e) {
			if (list.hidden) { return; }
			var lis = list.querySelectorAll('[role="option"]');
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				active = (active + (e.key === 'ArrowDown' ? 1 : -1) + lis.length) % Math.max(lis.length, 1);
				Array.prototype.forEach.call(lis, function (li, i) { li.classList.toggle('on', i === active); });
			} else if (e.key === 'Enter' && active >= 0) {
				e.preventDefault();
				pick(active);
			} else if (e.key === 'Escape') {
				e.stopPropagation();
				close();
			}
		});
		input.addEventListener('blur', function () { window.setTimeout(close, 120); });
	});

	// ------------------------------------------------------------ deep links (§8.11)
	function say(text) {
		snack.textContent = text;
		snack.hidden = false;
		window.setTimeout(function () { snack.hidden = true; }, 2200);
	}
	// The link opens the page inside the shell (index/main.php#/systemdata/...), which is how
	// Guides, SALDI Assist and support send people to one field.
	function fieldLink(key) {
		var root = window.location.pathname.replace(/\/systemdata\/[^\/]*$/, '');
		var section = new URLSearchParams(window.location.search).get('s') || '';
		return window.location.origin + root + '/index/main.php#/systemdata/settingsSection.php?s=' + encodeURIComponent(section) + '&field=' + encodeURIComponent(key);
	}
	function highlight() {
		var params = new URLSearchParams(window.location.search);
		var id = params.get('field') || decodeURIComponent((window.location.hash || '').replace('#', ''));
		// A redirect from an old page without an anchor lights up the first card for a moment (spec §8.10).
		var el = id ? document.getElementById(id) : (params.get('moved') ? form.querySelector('.st-sect') : null);
		if (!el) { return; }
		if (!el) { return; }
		if (el.hidden) { el.hidden = false; }
		el.classList.add('st-highlight');
		el.scrollIntoView({ block: 'center' });
		var c = el.querySelector('.st-switch, [data-control]:not([type="hidden"])');
		if (c) { try { c.focus({ preventScroll: true }); } catch (e) {} }
		window.setTimeout(function () { el.classList.remove('st-highlight'); }, 3000);
	}
	// One-off notices (saved, restored, moved) and the field anchor are dropped from the address
	// once shown, so a reload does not repeat them.
	function cleanUrl() {
		if (!window.history || !window.history.replaceState) { return; }
		var url = window.location.pathname + window.location.search.replace(/([?&])(saved|moved|reverted|err|field)=[^&]*/g, '$1').replace(/[?&]+$/, '').replace(/([?&])&+/g, '$1');
		window.history.replaceState(null, '', url);
	}
	window.addEventListener('hashchange', highlight);

	refresh();
	highlight();
	var firstError = form.querySelector('.st-invalid');
	if (firstError && !window.location.hash && !new URLSearchParams(window.location.search).get('field')) { firstError.scrollIntoView({ block: 'center' }); }
	cleanUrl();
})();
