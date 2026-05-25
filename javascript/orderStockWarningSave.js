
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
			var qtyEl =
				f.elements['dkan' + idx] ||
				f.elements['antal[' + idx + ']'] ||
				f.elements['dkantal[' + idx + ']'];
			var qtyVal = qtyEl ? (qtyEl.value || '0').replace(',', '.') : '1';
			var qty = parseFloat(qtyVal);
			if (!qty || qty <= 0) continue;
			if (seen[v]) continue;
			seen[v] = true;
			items.push({ varenr: v, idx: idx });
		}
		return items;
	}

	function alreadyApproved(f, varenr) {
		var el = f.elements['stock_warning_note[' + varenr + ']'];
		return el && (el.value || '').trim() !== '';
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
			// Fallback chain: a real submit button on this form.
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
		if (!idMatch) return; // no order id in URL -> nothing to log against
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
			} catch (e) { /* fall through to XHR fallback */ }
		}
	
		try {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', endpoint, false); // synchronous
			xhr.send(fd);
		} catch (e) {
			console && console.warn && console.warn('direct log write failed', e);
		}
	}

	function onSubmit(ev) {
		var f = ev.target;
		if (!f || f.tagName !== 'FORM') return;
		if (f.dataset.swApproved === '1') {
		
			f.dataset.swApproved = '';
			return;
		}

		var items = collectLineItems(f);
		if (!items.length) return;

		var unapproved = items.filter(function (it) { return !alreadyApproved(f, it.varenr); });
		if (!unapproved.length) return;
		currentSubmitter = ev.submitter ||
		                   (document.activeElement && document.activeElement.form === f ? document.activeElement : null);

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
				function onCancel() {
					f.dataset.swApproved = '';
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
			// Popup helper not loaded yet — retry shortly.
			setTimeout(init, 100);
			return;
		}
		// Bind every <form name="ordre"> currently in the DOM.
		var forms = document.getElementsByName('ordre');
		for (var i = 0; i < forms.length; i++) attachToForm(forms[i]);
		// In case the form is injected later, observe.
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
