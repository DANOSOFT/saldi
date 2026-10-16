// javascript/settingsList.js
// 20261002 Sawaneh Settings redesign phase 4b batch 2 (hand-over 2 Oct, mock-ups 07/08): a list section - every
//                  integration is a row that opens a drawer with its own form. Dirty tracking and dependency rules per
//                  drawer, write-only secrets (Skift reveals an empty field), confirmation dialog for actions, the two
//                  small provider forms sent through our own server, deep links ?item= and ?field=, Esc and Ctrl+S.
// 20261008 Sawaneh Mini forms send the page's CSRF token (X-CSRF-Token), as master's Flatpay ID save (SST-844) requires.
(function () {
	'use strict';
	var cfg = window.SALDI_SETTINGS || {};
	var page = document.querySelector('.st-page-list');
	if (!page) { return; }

	var scrim = document.getElementById('st-scrim');
	var snack = document.getElementById('st-snack');
	var dialog = document.getElementById('st-dialog');
	var backdrop = document.getElementById('st-backdrop');
	var dform = document.getElementById('st-dialog-form');
	var drawers = {};
	Array.prototype.forEach.call(page.querySelectorAll('.st-drawer'), function (d) { drawers[d.dataset.item] = d; });
	var current = null;
	var returnFocus = null;

	function control(field) { return field.querySelector('[data-control]'); }
	function valueOf(field) { var c = control(field); return c ? String(c.value).trim() : ''; }
	function fieldsOf(drawer) { return Array.prototype.slice.call(drawer.querySelectorAll('.st-field')); }
	function byKey(drawer) {
		var map = {};
		fieldsOf(drawer).forEach(function (f) { map[f.dataset.key] = f; });
		return map;
	}

	// The stored value, not what the field holds now: after a refused save the fields still hold
	// what the user typed, and that must count as unsaved. A secret is unsaved as soon as it is typed.
	var initial = {};
	Object.keys(drawers).forEach(function (item) {
		fieldsOf(drawers[item]).forEach(function (f) {
			var o = f.querySelector('input[name^="o["]');
			initial[f.dataset.key] = o ? String(o.value).trim() : '';
		});
	});

	function setValue(field, value) {
		var c = control(field);
		if (!c) { return; }
		c.value = value;
		var sw = field.querySelector('.st-switch');
		if (sw) { sw.setAttribute('aria-checked', value === '1' ? 'true' : 'false'); }
	}

	// ------------------------------------------------------------ dependencies (P7)
	function ruleHolds(rule, map) {
		var parent = map[rule[1]];
		if (!parent) { return true; }
		var v = valueOf(parent);
		if (rule[0] === 'setting') { return (v === '1') === !!rule[2]; }
		if (rule[0] === 'setting_in') { return rule[2].indexOf(v) !== -1; }
		if (rule[0] === 'setting_set') { return v !== '' || parent.classList.contains('st-type-secret') && !!parent.querySelector('.st-secret-set'); }
		return true;
	}
	function applyRules(drawer) {
		var map = byKey(drawer);
		Array.prototype.forEach.call(drawer.querySelectorAll('[data-visible-if]'), function (el) {
			var rule;
			try { rule = JSON.parse(el.dataset.visibleIf); } catch (e) { return; }
			el.hidden = !ruleHolds(rule, map);
		});
	}

	// ------------------------------------------------------------ unsaved changes per drawer
	function changedIn(drawer) {
		var n = 0;
		fieldsOf(drawer).forEach(function (f) {
			var ch = valueOf(f) !== initial[f.dataset.key];
			f.classList.toggle('st-changed', ch);
			if (ch) { n++; }
			var reset = f.querySelector('[data-reset]');
			if (reset) { reset.hidden = (valueOf(f) === f.dataset.default); }
		});
		return n;
	}
	function refresh(drawer) {
		if (!drawer) { return; }
		var n = changedIn(drawer);
		var save = drawer.querySelector('[data-save]');
		var status = drawer.querySelector('[data-status]');
		if (save) { save.disabled = (n === 0); }
		if (status) {
			status.hidden = (n === 0);
			status.querySelector('span').textContent = n === 1 ? cfg.unsaved1 : String(cfg.unsavedN).replace('%s', n);
		}
		window.docChange = (n > 0);
		applyRules(drawer);
	}
	function isDirty(drawer) { return !!drawer && changedIn(drawer) > 0; }

	// ------------------------------------------------------------ open and close
	function open(item, focusKey) {
		var drawer = drawers[item];
		if (!drawer) { return; }
		if (current && current !== drawer) {
			if (isDirty(current)) {
				openDialog(cfg.discardTitle, cfg.discardBody, cfg.discardVerb, { discard: true, then: function () { open(item, focusKey); } });
				return;
			}
			hide(current);
		}
		returnFocus = document.activeElement;
		current = drawer;
		drawer.hidden = false;
		scrim.hidden = false;
		page.classList.add('st-drawer-open');
		Array.prototype.forEach.call(page.querySelectorAll('[data-open]'), function (b) { b.setAttribute('aria-expanded', b.dataset.open === item ? 'true' : 'false'); });
		refresh(drawer);
		var target = focusKey ? drawer.querySelector('[data-key="' + focusKey + '"]') : null;
		if (target) {
			target.hidden = false;
			target.classList.add('st-highlight');
			window.setTimeout(function () { target.classList.remove('st-highlight'); }, 3000);
			target.scrollIntoView({ block: 'center' });
		}
		var first = (target && target.querySelector('.st-switch, [data-control]:not([hidden]), .st-btn, .st-tl'))
			|| drawer.querySelector('.st-dbody .st-switch:not(:disabled), .st-dbody [data-control]:not([hidden]):not([readonly]):not([type="hidden"])')
			|| drawer.querySelector('.st-dbody .st-btn, .st-dbody .st-tl')
			|| drawer.querySelector('[data-close]');
		if (first) { try { first.focus({ preventScroll: !target }); } catch (e) {} }
		if (window.history && window.history.replaceState) {
			var url = new URL(window.location.href);
			url.searchParams.set('item', item);
			window.history.replaceState(null, '', url.pathname + url.search);
		}
	}
	function hide(drawer) {
		drawer.hidden = true;
		fieldsOf(drawer).forEach(function (f) {
			setValue(f, initial[f.dataset.key]);
			var secret = f.querySelector('[data-secret]');
			if (secret && secret.classList.contains('st-secret-set')) {
				var input = secret.querySelector('[data-control]');
				var btn = secret.querySelector('[data-secret-change]');
				if (input) { input.value = ''; input.hidden = true; }
				if (btn) { btn.hidden = false; }
			}
		});
		window.docChange = false;
	}
	function close(force) {
		if (!current) { return; }
		if (!force && isDirty(current)) {
			openDialog(cfg.discardTitle, cfg.discardBody, cfg.discardVerb, { discard: true, then: function () { close(true); } });
			return;
		}
		hide(current);
		current = null;
		scrim.hidden = true;
		page.classList.remove('st-drawer-open');
		Array.prototype.forEach.call(page.querySelectorAll('[data-open]'), function (b) { b.setAttribute('aria-expanded', 'false'); });
		if (returnFocus && returnFocus.focus) { returnFocus.focus(); }
		if (window.history && window.history.replaceState) {
			var url = new URL(window.location.href);
			url.searchParams.delete('item');
			window.history.replaceState(null, '', url.pathname + (url.search || ''));
		}
	}
	scrim.addEventListener('click', function () { close(false); });

	// ------------------------------------------------------------ clicks
	page.addEventListener('click', function (e) {
		var opener = e.target.closest('[data-open]');
		if (opener) { open(opener.dataset.open); return; }
		var closer = e.target.closest('[data-close]');
		if (closer) { close(false); return; }
		var drawer = e.target.closest('.st-drawer');
		var sw = e.target.closest('.st-switch');
		if (sw && !sw.disabled) {
			setValue(sw.closest('.st-field'), sw.getAttribute('aria-checked') === 'true' ? '0' : '1');
			refresh(drawer);
			return;
		}
		var reset = e.target.closest('[data-reset]');
		if (reset) {
			var field = reset.closest('.st-field');
			setValue(field, field.dataset.default);
			refresh(drawer);
			return;
		}
		var change = e.target.closest('[data-secret-change]');
		if (change) {
			var input = document.getElementById(change.getAttribute('aria-controls'));
			change.hidden = true;
			if (input) { input.hidden = false; input.focus(); }
			return;
		}
		var copy = e.target.closest('[data-copy]');
		if (copy) {
			var url = fieldLink(copy.closest('.st-field').dataset.key, drawer ? drawer.dataset.item : '');
			if (navigator.clipboard) { navigator.clipboard.writeText(url).then(function () { say(cfg.copied); }); }
			return;
		}
		var run = e.target.closest('[data-run]');
		if (run) {
			openDialog(run.dataset.title, run.dataset.body, run.dataset.verb, { action: 'run', key: run.dataset.run, item: drawer ? drawer.dataset.item : '', newTab: !!run.dataset.blank }, run.classList.contains('st-btn-danger'), !!run.dataset.password);
			return;
		}
		var restore = e.target.closest('[data-restore]');
		if (restore) {
			openDialog(cfg.restoreTitle, cfg.restoreBody + ' ' + restore.dataset.value, cfg.restoreVerb, { action: 'revert', entry: restore.dataset.restore, item: drawer ? drawer.dataset.item : '' });
			return;
		}
		var send = e.target.closest('[data-mini-send]');
		if (send) { sendMini(send.closest('[data-mini]'), send, drawer); return; }
		var dismiss = e.target.closest('[data-dismiss]');
		if (dismiss) { dismiss.parentNode.hidden = true; }
	});
	page.addEventListener('input', function (e) { refresh(e.target.closest('.st-drawer')); });
	page.addEventListener('change', function (e) { refresh(e.target.closest('.st-drawer')); });
	page.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('st-switch')) { e.preventDefault(); }
	});
	Array.prototype.forEach.call(page.querySelectorAll('.st-dform'), function (form) {
		form.addEventListener('submit', function (e) {
			var save = form.querySelector('[data-save]');
			if (!save || save.disabled) { e.preventDefault(); return; }
			window.docChange = false;
			save.disabled = true;
		});
	});
	window.addEventListener('beforeunload', function (e) {
		if (!window.docChange) { return; }
		e.preventDefault();
		e.returnValue = '';
	});

	// ------------------------------------------------------------ the small provider forms (Flatpay, Vibrant)
	function sendMini(box, button, drawer) {
		if (!box || button.disabled) { return; }
		var data = {};
		var missing = false;
		Array.prototype.forEach.call(box.querySelectorAll('[data-mini-field]'), function (i) {
			data[i.dataset.miniField] = i.value;
			if (String(i.value).trim() === '') { missing = true; i.focus(); }
		});
		if (missing) { return; }
		var label = button.textContent;
		button.disabled = true;
		button.textContent = cfg.wait;
		var token = document.querySelector('input[name="csrf_token"]');
		fetch(box.dataset.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token ? token.value : '' }, body: JSON.stringify(data) })
			.then(function (r) {
				return r.text().then(function (t) {
					var msg = '';
					try { var j = JSON.parse(t); msg = j.message || ''; } catch (err) { msg = ''; }
					return { ok: r.ok, message: msg };
				});
			})
			.then(function (res) {
				Array.prototype.forEach.call(box.querySelectorAll('[type="password"]'), function (i) { i.value = ''; });
				if (res.ok) {
					window.docChange = false;
					var url = new URL(window.location.href);
					url.searchParams.set('item', drawer ? drawer.dataset.item : '');
					url.searchParams.set('done', box.dataset.mini);
					window.location.replace(url.pathname + url.search);
					return;
				}
				button.disabled = false;
				button.textContent = label;
				say(String(box.dataset.fail).replace('%s', res.message));
			})
			.catch(function () {
				button.disabled = false;
				button.textContent = label;
				say(String(box.dataset.fail).replace('%s', ''));
			});
	}

	// ------------------------------------------------------------ dialog (§8.3)
	var pending = null;
	function openDialog(title, body, verb, values, danger, password) {
		pending = values;
		var pw = document.getElementById('st-dialog-pw');
		if (pw) { pw.hidden = !password; pw.querySelector('input').value = ''; pw.querySelector('input').required = !!password; }
		document.getElementById('st-dialog-title').textContent = title;
		document.getElementById('st-dialog-body').textContent = body;
		document.getElementById('st-dialog-ok').textContent = verb;
		document.getElementById('st-dialog-ok').classList.toggle('st-btn-danger-solid', !!danger);
		dform.elements.action.value = values.action || '';
		dform.elements.key.value = values.key || '';
		dform.elements.entry.value = values.entry || '';
		dform.elements.item.value = values.item || '';
		dform.target = values.newTab ? '_blank' : '';
		dialog.hidden = false;
		backdrop.hidden = false;
		document.getElementById('st-dialog-cancel').focus();
	}
	function closeDialog() {
		dialog.hidden = true;
		backdrop.hidden = true;
		pending = null;
		var f = current ? current.querySelector('[data-close]') : null;
		if (f) { f.focus(); }
	}
	document.getElementById('st-dialog-cancel').addEventListener('click', closeDialog);
	backdrop.addEventListener('click', closeDialog);
	dform.addEventListener('submit', function (e) {
		if (pending && pending.discard) {
			e.preventDefault();
			var then = pending.then;
			closeDialog();
			if (then) { then(); }
			return;
		}
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

	// ------------------------------------------------------------ keyboard (§8.14)
	document.addEventListener('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
			e.preventDefault();
			var save = current ? current.querySelector('[data-save]') : null;
			if (save && !save.disabled) { var form = current.querySelector('form'); form.requestSubmit ? form.requestSubmit() : form.submit(); }
		}
		if (e.key === 'Escape') {
			if (!dialog.hidden) { closeDialog(); }
			else if (current) { close(false); }
		}
		if (e.key === 'Tab' && current && dialog.hidden) {
			var f = current.querySelectorAll('button:not([disabled]):not([hidden]), input:not([type="hidden"]):not([hidden]):not([readonly]), select:not([disabled]), a[href], summary');
			var list = Array.prototype.filter.call(f, function (el) { return el.offsetParent !== null; });
			if (!list.length) { return; }
			var first = list[0], last = list[list.length - 1];
			if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
		}
	});

	// ------------------------------------------------------------ left column follows the scroll
	var links = page.querySelectorAll('.st-tabs a[data-sub]');
	if (links.length && 'IntersectionObserver' in window) {
		var seen = {};
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) { seen[en.target.id] = en.isIntersecting; });
			var firstVisible = Array.prototype.find.call(page.querySelectorAll('.st-list .st-sect'), function (s) { return seen[s.id]; });
			if (!firstVisible) { return; }
			Array.prototype.forEach.call(links, function (a) { a.classList.toggle('on', a.getAttribute('href') === '#' + firstVisible.id); });
		}, { rootMargin: '-10% 0px -70% 0px' });
		Array.prototype.forEach.call(page.querySelectorAll('.st-list .st-sect'), function (s) { io.observe(s); });
	}

	// ------------------------------------------------------------ deep links (§8.11) and one-off notices
	function say(text) {
		snack.textContent = text;
		snack.hidden = false;
		window.setTimeout(function () { snack.hidden = true; }, 2600);
	}
	function fieldLink(key, item) {
		var root = window.location.pathname.replace(/\/systemdata\/[^\/]*$/, '');
		var section = new URLSearchParams(window.location.search).get('s') || '';
		return window.location.origin + root + '/index/main.php#/systemdata/settingsSection.php?s=' + encodeURIComponent(section) + '&item=' + encodeURIComponent(item) + '&field=' + encodeURIComponent(key);
	}
	function cleanUrl() {
		if (!window.history || !window.history.replaceState) { return; }
		var url = new URL(window.location.href);
		['saved', 'moved', 'reverted', 'err', 'field', 'newkey', 'qr', 'webhook', 'done', 'converted'].forEach(function (k) { url.searchParams.delete(k); });
		window.history.replaceState(null, '', url.pathname + (url.search || ''));
	}
	var params = new URLSearchParams(window.location.search);
	var field = params.get('field') || '';
	var startItem = (field && cfg.fieldItem && cfg.fieldItem[field]) || cfg.openItem || '';
	if (params.get('done') === 'flatpay') { say(cfg.doneFlatpay || ''); }
	if (params.get('done') === 'vibrant') { say(cfg.doneVibrant || ''); }
	cleanUrl();
	if (startItem) { open(startItem, field); }
})();
