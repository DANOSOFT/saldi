/*
 * posScanBuffer.js - keeps barcode-scanner keystrokes from being lost in debitor/pos_ordre.php.
 *
 * Every scan in POS is a full page reload. While the POST for one scan is in flight, and
 * while the next page is still loading, there is no input field ready, so the scanner's
 * keystrokes for the next item used to land nowhere (or only partly, giving a truncated
 * item number and the item lookup page). This script holds those keystrokes and replays
 * them, one scan per page load, into the scan field once the page has placed focus.
 *
 * The buffer lives in sessionStorage (per browser tab) so it survives the reload. A scan is
 * a run of characters ended by Enter. Only keystrokes that arrive after a submit from a scan
 * field, or before the page has placed focus, are buffered; normal typing is untouched.
 *
 * 20261002 CL/SZ SST-812 Created.
 */
(function (global) {
	if (global.PosScanBuffer) return;

	var STORAGE_KEY = 'saldiPosScanBuffer';
	var SCAN_FIELDS = ['varenr_ny', 'antal_ny'];
	var MAX_AGE_MS = 20000;  // a buffer untouched this long is stale and dropped
	var BUSY_TIMEOUT_MS = 15000;  // give the keyboard back if a submit never navigates

	var ready = false;  // the page has placed focus; keys may go to the field directly
	var busy = false;   // a submit from a scan field is in flight; keys go to the buffer
	var busyTimer = null;
	var buffer = load();

	function load() {
		try {
			var raw = global.sessionStorage.getItem(STORAGE_KEY);
			if (!raw) return '';
			var data = JSON.parse(raw);
			if (!data || typeof data.keys !== 'string' || Date.now() - data.t > MAX_AGE_MS) {
				global.sessionStorage.removeItem(STORAGE_KEY);
				return '';
			}
			return data.keys;
		} catch (e) {
			return '';
		}
	}

	function save() {
		try {
			if (buffer) global.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ t: Date.now(), keys: buffer }));
			else global.sessionStorage.removeItem(STORAGE_KEY);
		} catch (e) {}
	}

	function isScanField(el) {
		return !!(el && el.form && el.form.name === 'pos_ordre' && SCAN_FIELDS.indexOf(el.name) !== -1);
	}

	function isUsable(el) {
		return !!(el && el.focus && el.type !== 'hidden' && !el.disabled && el.offsetParent !== null);
	}

	function field(form, name) {
		if (!form || !name) return null;
		var el = form.elements[name];
		if (el && !el.tagName && el.length) el = el[0];  // several fields with the same name
		return isUsable(el) ? el : null;
	}

	function firstTextField(form) {
		if (!form) return null;
		for (var i = 0; i < form.elements.length; i++) {
			var el = form.elements[i];
			if (el.tagName === 'INPUT' && (el.type === 'text' || el.type === '') && isUsable(el)) return el;
		}
		return null;
	}

	function refreshPending() {
		var metas = document.getElementsByTagName('meta');
		for (var i = 0; i < metas.length; i++) {
			if ((metas[i].getAttribute('http-equiv') || '').toLowerCase() === 'refresh') return true;
		}
		return false;
	}

	function setBusy() {
		busy = true;
		clearTimeout(busyTimer);
		busyTimer = setTimeout(function () {
			// The submit did not lead to a new page; hand the held keys to the field instead.
			busy = false;
			replay(document.activeElement);
		}, BUSY_TIMEOUT_MS);
	}

	// The button the browser "clicks" when Enter is pressed in a text field of the form.
	function defaultButton(form) {
		for (var i = 0; i < form.elements.length; i++) {
			var el = form.elements[i];
			var type = (el.getAttribute('type') || (el.tagName === 'BUTTON' ? 'submit' : '')).toLowerCase();
			if ((el.tagName === 'BUTTON' || el.tagName === 'INPUT') && (type === 'submit' || type === 'image')) return el;
		}
		return null;
	}

	function submitLikeEnter(el) {
		var form = el.form;
		var btn = defaultButton(form);
		if (btn) {
			if (!btn.disabled) btn.click();
		} else if (form.requestSubmit) {
			form.requestSubmit();
		} else {
			form.submit();
		}
	}

	// Deliver held keystrokes to el: at most one complete scan, which is then submitted.
	function replay(el) {
		if (!buffer) return;
		if (!isScanField(el) || document.getElementById('saldi-sw-pos-payload')) {
			// Not in scan mode (price/payment field, stock warning popup): never type a held
			// scan into some other field. Drop it, as the keys would have been lost before.
			buffer = '';
			save();
			return;
		}
		var end = buffer.indexOf('\n');
		if (end === -1) {
			// A scan still being typed; the rest of it goes straight into the field.
			el.value += buffer;
			buffer = '';
			save();
			return;
		}
		el.value += buffer.substring(0, end);
		buffer = buffer.substring(end + 1);
		save();
		setBusy();
		submitLikeEnter(el);
	}

	document.addEventListener('keydown', function (e) {
		if (ready && !busy) return;
		var ch = null;
		if (e.key === 'Enter') ch = '\n';
		else if (e.key && e.key.length === 1 && !e.metaKey && e.ctrlKey === e.altKey) ch = e.key;  // AltGr sets both
		if (ch === null) return;
		e.preventDefault();
		e.stopPropagation();
		buffer += ch;
		save();
	}, true);

	// The page is navigating away after a scan (Enter in a scan field). This fires however the
	// form got submitted, including handlers that call form.submit() and skip the submit event.
	global.addEventListener('beforeunload', function () {
		if (ready && isScanField(document.activeElement)) setBusy();
	});

	// Back/forward cache restores a page that was mid-submit; make it usable again.
	global.addEventListener('pageshow', function (e) {
		if (e.persisted) {
			busy = false;
			clearTimeout(busyTimer);
		}
	});

	// Pages that never call focus() (other POS screens) must not keep swallowing keys.
	document.addEventListener('DOMContentLoaded', function () {
		if (ready) return;
		ready = true;
		if (refreshPending()) {
			busy = true;  // this page is about to navigate on; hold keys for the next one
		} else {
			buffer = '';
			save();
		}
	});

	global.PosScanBuffer = {
		/**
		 * Puts focus in the named field of the pos_ordre form, or the scan field when that
		 * field is missing or not visible, then replays any held scan into it.
		 */
		focus: function (name) {
			var form = document.forms['pos_ordre'];
			var el = field(form, name) || field(form, 'varenr_ny') || firstTextField(form);
			if (el) {
				try { el.focus(); } catch (e) {}
			}
			ready = true;
			if (refreshPending()) {
				busy = true;
				return;
			}
			replay(el);
		}
	};
})(window);
