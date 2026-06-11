
(function () {
	if (window.SaldiOrderStockWarning) return;
	var VARE_RE = /^vare_?(\d+)$/;

	function collectLineItems(f) {
		var items = [];
		var seen = {};
		var els = f.elements;
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (!el.name) continue;
			var m = el.name.match(VARE_RE);
			if (!m) continue;
			var v = (el.value || '').trim();
			if (!v) continue;
			var idx = m[1];
			// Per-line delete marks posn<idx> as '-'; saldi may render a mirror input, scan all.
			var posnEls = document.getElementsByName('posn' + idx);
			var isDeleted = false;
			for (var pi = 0; pi < posnEls.length; pi++) {
				var pv = (posnEls[pi].value || '').trim();
				if (pv === '-' || pv === '-1') { isDeleted = true; break; }
			}
			if (isDeleted) continue;
			var lidEl = f.elements['linje_id[' + idx + ']'];
			var lid = lidEl ? parseInt((lidEl.value || '0').trim(), 10) || 0 : 0;
			// Skip already-saved lines; only prompt for newly added ones.
			if (lid > 0) continue;
			if (seen[v]) continue;
			seen[v] = true;
			items.push({ varenr: v, idx: idx, linje_id: lid });
		}
		return items;
	}

	function alreadyApproved(f, item) {
		// Approved if a fresh note is attached, or the server marked this line/varenr as preapproved.
		var note = f.elements['stock_warning_note[' + item.varenr + ']'];
		if (note && (note.value || '').trim() !== '') return true;
		if (item.linje_id) {
			var preL = f.elements['stock_warning_preapproved_line[' + item.linje_id + ']'];
			if (preL) return true;
		}
		var preV = f.elements['stock_warning_preapproved_varenr[' + item.varenr + ']'];
		if (preV) return true;
		return false;
	}

	function attachNote(f, varenr, note) {
		var nm = 'stock_warning_note[' + varenr + ']';
		var el = f.elements[nm];
		if (!el) {
			el = document.createElement('input');
			el.type = 'hidden';
			el.name = nm;
			f.appendChild(el);
		}
		el.value = note;
	}

	function postJSON(url, params, cb) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', url, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4) {
				if (xhr.status >= 200 && xhr.status < 300) {
					try { cb(null, JSON.parse(xhr.responseText)); }
					catch (e) { cb(e); }
				} else cb(new Error('HTTP ' + xhr.status));
			}
		};
		var body = Object.keys(params).map(function (k) {
			return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
		}).join('&');
		xhr.send(body);
	}

	// Declining the sale ("No") must leave the line ready for a new lookup:
	// clear the rejected line's entry fields and refocus the Item no. field.
	// The rejected popup item is matched back to the form line by varenr; a
	// samlesæt sub-item (or a barcode lookup) carries its own varenr, so fall
	// back to the single pending line when no name matches.
	function clearRejectedLine(f, formItems, rejected) {
		var rej = (rejected && rejected.varenr != null ? String(rejected.varenr) : '').toLowerCase();
		var target = null;
		for (var i = 0; i < formItems.length; i++) {
			if ((formItems[i].varenr || '').toLowerCase() === rej) { target = formItems[i]; break; }
		}
		if (!target && formItems.length === 1) target = formItems[0];
		if (!target) return;
		var idx = target.idx;
		var vareNames = ['vare' + idx, 'vare_' + idx];
		var lineNames = vareNames.concat(['dkan' + idx, 'beskrivelse' + idx, 'pris' + idx, 'raba' + idx, 'lagr' + idx]);
		var focusEl = null;
		for (var n = 0; n < lineNames.length; n++) {
			var els = document.getElementsByName(lineNames[n]);
			for (var e = 0; e < els.length; e++) {
				var el = els[e];
				if (el.form !== f) continue;
				if (el.type === 'hidden' || el.readOnly || el.disabled) continue;
				el.value = '';
				// On the entry line the description and price are shown as placeholder
				// text (the looked-up item preview), not as a value — clear those too,
				// otherwise Description/Price stay visible after declining the sale.
				if ('placeholder' in el) el.placeholder = '';
				if (n < vareNames.length && !focusEl) focusEl = el;
			}
		}
		if (focusEl) {
			try { focusEl.focus(); } catch (err) {}
		}
	}

	function promptSequentially(items, onAllDone, onCancel) {
		var i = 0;
		var cancelled = false;
		function next() {
			if (cancelled) return;
			if (i >= items.length) { onAllDone(); return; }
			var it = items[i++];
			window.SaldiStockWarning.show({
				varenr: it.varenr,
				beskrivelse: it.beskrivelse,
				onCancel: function () { cancelled = true; onCancel(it); },
				onConfirm: function (note) {
					attachNote(currentForm, it.varenr, note);
					logApprovalDirect(it.varenr, note);
					next();
				}
			});
		}
		next();
	}

	var currentForm = null;
	var currentSubmitter = null;


	function isSubmitButton(el) {
		if (!el) return false;
		var tag = el.tagName;
		if (tag === 'BUTTON' && (el.type === 'submit' || !el.type)) return true;
		if (tag === 'INPUT' && (el.type === 'submit' || el.type === 'image')) return true;
		return false;
	}

	function submitFormNative(f) {
		f.dataset.swApproved = '1';
		var btn = currentSubmitter;
		if (!isSubmitButton(btn) || (btn && btn.form !== f)) {
			if (document.activeElement && document.activeElement.form === f && isSubmitButton(document.activeElement)) {
				btn = document.activeElement;
			} else {
				btn = f.querySelector('input[type="submit"][name="save"]') ||
				      f.querySelector('input[type="submit"]') ||
				      f.querySelector('button[type="submit"]');
			}
		}
		if (isSubmitButton(btn)) {
			btn.click();
			return;
		}
	
		if (!f.elements['save']) {
			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = 'save';
			hidden.value = 'Gem';
			f.appendChild(hidden);
		}
		try { HTMLFormElement.prototype.submit.call(f); } catch (e) {}
	}

	function logApprovalDirect(varenr, note) {
		var idMatch = (window.location.href || '').match(/[?&]id=(\d+)/);
		if (!idMatch) return;
		var ordre_id = idMatch[1];
		var endpoint = 'orderIncludes/stockWarningSave.php';
		var fd = new FormData();
		fd.append('ordre_id', ordre_id);
		fd.append('varenr', varenr);
		fd.append('note', note);
		if (navigator && typeof navigator.sendBeacon === 'function') {
			try {
				var ok = navigator.sendBeacon(endpoint, fd);
				if (ok) return;
			} catch (e) {}
		}
		try {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', endpoint, false);
			xhr.send(fd);
		} catch (e) {
			console && console.warn && console.warn('direct log write failed', e);
		}
	}

	// Submitters that must bypass the popup (delete/credit/copy/lookup/print).
	var BYPASS_SUBMITTERS = { 'delete': 1, 'credit': 1, 'copy': 1, 'lookUp': 1, 'print': 1 };

	function onSubmit(ev) {
		var f = ev.target;
		if (!f || f.tagName !== 'FORM') return;
		if (f.dataset.swApproved === '1') {
			f.dataset.swApproved = '';
			return;
		}
		// Per-line delete sets this flag right before submitting; skip the preflight entirely.
		if (f.dataset.swSkipPreflight === '1') {
			f.dataset.swSkipPreflight = '';
			return;
		}
		var earlySubmitter = ev.submitter ||
		                     (document.activeElement && document.activeElement.form === f ? document.activeElement : null);
		if (earlySubmitter && earlySubmitter.name && BYPASS_SUBMITTERS[earlySubmitter.name]) return;

		var items = collectLineItems(f);
		if (!items.length) return;

		var unapproved = items.filter(function (it) { return !alreadyApproved(f, it); });
		if (!unapproved.length) return;
		currentSubmitter = earlySubmitter;

		ev.preventDefault();
		ev.stopPropagation();
		currentForm = f;
		var endpoint = 'orderIncludes/stockCheckBatch.php';
		var varenrCsv = unapproved.map(function (it) { return it.varenr; }).join(',');

		postJSON(endpoint, { items: varenrCsv }, function (err, resp) {
			if (err || !resp) {
				console && console.warn && console.warn('stockCheckBatch failed; submitting without popup', err);
				f.dataset.swApproved = '1';
				submitFormNative(f);
				return;
			}
			if (!resp.enabled || !resp.out_of_stock || !resp.out_of_stock.length) {
				f.dataset.swApproved = '1';
				submitFormNative(f);
				return;
			}
			promptSequentially(
				resp.out_of_stock,
				function onAllDone() {
					f.dataset.swApproved = '1';
					submitFormNative(f);
				},
				function onCancel(rejected) {
					f.dataset.swApproved = '';
					clearRejectedLine(f, unapproved, rejected);
				}
			);
		});
	}

	function attachToForm(f) {
		if (!f || f.dataset.swBound === '1') return;
		f.dataset.swBound = '1';
		f.addEventListener('submit', onSubmit, true);
	}

	function init() {
		if (!window.SaldiStockWarning) {
			setTimeout(init, 100);
			return;
		}
		var forms = document.getElementsByName('ordre');
		for (var i = 0; i < forms.length; i++) attachToForm(forms[i]);
		if (window.MutationObserver) {
			new MutationObserver(function () {
				var fs = document.getElementsByName('ordre');
				for (var j = 0; j < fs.length; j++) attachToForm(fs[j]);
			}).observe(document.body || document.documentElement, { childList: true, subtree: true });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.SaldiOrderStockWarning = { init: init };
})();
