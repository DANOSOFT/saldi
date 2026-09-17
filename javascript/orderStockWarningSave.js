
(function () {
	if (window.SaldiOrderStockWarning) return;
	var VARE_RE = /^vare_?(\d+)$/;

	// Cumulative model: a "sale" is the TOTAL quantity of an item across the whole
	// order, so we sum every non-deleted line (saved + the unsaved entry row) per
	// varenr and check (current stock - total ordered) against the minimum. This
	// is independent of how any single line got its quantity (e.g. the lookup
	// modal defaults a new line to qty 1, which the user then edits). For each
	// item we remember the unsaved entry-row line (linje_id 0) so that declining
	// the sale ("No") can clear the line being added.
	function collectLineItems(f) {
		var items = [];
		var seen = {};
		var els = f.elements;
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (!el.name) continue;
			if (el.type === 'hidden') continue;
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
			var qty = getQuantity(f, idx);
			// A saved line whose quantity was just edited UP (current > the saved value
			// kept in antal[idx]) is treated as "in scope" too, so increasing an existing
			// line into an oversell still warns — not only adding a brand-new line.
			var savedQty = getSavedQuantity(f, idx);
			var increased = (lid > 0 && savedQty !== null && qty > savedQty + 1e-9);
			if (seen[v]) {
				seen[v].qty += qty;
				seen[v].lines.push({ idx: idx, lid: lid, savedQty: savedQty });
				if (lid === 0 && seen[v].entryIdx === null) seen[v].entryIdx = idx;
				if (increased) seen[v].changed = true;
				continue;
			}
			seen[v] = { varenr: v, idx: idx, qty: qty, entryIdx: (lid === 0 ? idx : null), changed: increased, lines: [{ idx: idx, lid: lid, savedQty: savedQty }] };
			items.push(seen[v]);
		}
		return items;
	}

	function getQuantity(f, idx) {
		var el = f.elements['dkan' + idx];
		var raw = el ? ((el.value || el.placeholder || '').trim()) : '';
		if (!raw) return 1;
		var n = parseFloat(raw.replace(/\./g, '').replace(',', '.'));
		if (!isFinite(n)) return 1;
		return n;
	}

	// The saved (pre-edit) quantity of a line, from the hidden antal[idx] field. This
	// is the raw DB value (US decimal); tolerate a comma decimal just in case. Returns
	// null when there is no saved value (e.g. the unsaved entry row has no antal[0]).
	function getSavedQuantity(f, idx) {
		var el = f.elements['antal[' + idx + ']'];
		if (!el) return null;
		var raw = (el.value || '').trim();
		if (raw === '') return null;
		if (raw.indexOf('.') === -1 && raw.indexOf(',') !== -1) raw = raw.replace(',', '.');
		var n = parseFloat(raw);
		return isFinite(n) ? n : null;
	}

	function alreadyApproved(f, item) {
		// Suppressed if this item's oversell has already been approved on the order:
		// either a note was just attached in the current popup sequence, or the
		// server marked the varenr as pre-approved (an approval was logged earlier).
		// One warning per item per order — adding more of an approved item is silent.
		var note = f.elements['stock_warning_note[' + item.varenr + ']'];
		if (note && (note.value || '').trim() !== '') return true;
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

	// Clear the input fields of one unsaved entry line (the line being added).
	function clearEntryLine(f, idx) {
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
		return focusEl;
	}

	// Declining the sale ("No") cancels the add: clear ONLY the unsaved entry line(s)
	// that the user is currently adding, and refocus Item no. for a new lookup.
	// Already-saved lines are never touched here — that is what previously caused
	// approved items and samlesæt components to be deleted. To remove a line that is
	// already on the order, the user uses the per-line delete (✗) button.
	//
	// Matching is by `origin` — the exact form-line string the server tied this
	// warning to. For a standalone item that is its own varenr; for a samlesæt
	// sub-item it is the SET's varenr, so "No" clears the set line being added and
	// never a standalone item that happens to share the sub-item's varenr.
	function clearRejectedLine(f, formItems, rejected) {
		var origin = (rejected && rejected.origin != null ? String(rejected.origin) : '').toLowerCase();
		var rej = (rejected && rejected.varenr != null ? String(rejected.varenr) : '').toLowerCase();
		var target = null;
		if (origin) {
			for (var oi = 0; oi < formItems.length; oi++) {
				if ((formItems[oi].varenr || '').toLowerCase() === origin) { target = formItems[oi]; break; }
			}
		}
		if (!target) {
			for (var i = 0; i < formItems.length; i++) {
				if ((formItems[i].varenr || '').toLowerCase() === rej) { target = formItems[i]; break; }
			}
		}
		if (!target && formItems.length === 1) target = formItems[0];
		if (!target) return;
		// Clear only the unsaved entry line(s) of this item; leave saved lines alone.
		var lines = target.lines || [];
		var focusEl = null;
		var clearedEntry = false;
		for (var li = 0; li < lines.length; li++) {
			if (lines[li].lid === 0) {
				var fe = clearEntryLine(f, lines[li].idx);
				if (fe && !focusEl) focusEl = fe;
				clearedEntry = true;
			}
		}
		if (clearedEntry) {
			if (focusEl) { try { focusEl.focus(); } catch (err) {} }
			return;
		}
		// No unsaved entry line: the warning came from increasing an existing line's
		// quantity. Declining reverts that line's quantity field to its saved value (the
		// submit is cancelled, so nothing is persisted); the line itself stays in place.
		for (var lj = 0; lj < lines.length; lj++) {
			if (lines[lj].lid > 0) {
				var dk = f.elements['dkan' + lines[lj].idx];
				var saved = f.elements['antal[' + lines[lj].idx + ']'];
				if (dk && saved && !dk.readOnly && !dk.disabled) dk.value = saved.value;
			}
		}
	}

	// One dialog per group (a group is everything sharing an origin form line — a
	// standalone item is its own group; a samlesæt is one group covering all its
	// out-of-stock components). The user gives ONE reason per group, which is then
	// attached and logged for every item in it.
	function promptGroups(groups, onAllDone, onCancel) {
		var gi = 0;
		var cancelled = false;
		function next() {
			if (cancelled) return;
			if (gi >= groups.length) { onAllDone(); return; }
			var g = groups[gi++];
			window.SaldiStockWarning.show({
				items: g.items.map(function (it) { return { varenr: it.varenr, beskrivelse: it.beskrivelse }; }),
				onCancel: function () { cancelled = true; onCancel(g); },
				onConfirm: function (note) {
					for (var k = 0; k < g.items.length; k++) {
						attachNote(currentForm, g.items[k].varenr, note);
						logApprovalDirect(g.items[k].varenr, note);
					}
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

		// Evaluate only the items the user is actually changing in this submit: a new
		// entry-row line, OR an existing line whose quantity was just edited up. Items
		// sitting unchanged on the order are not re-checked, so an in-stock add (or any
		// plain save) does not re-pop unrelated out-of-stock lines. The threshold still
		// uses each item's cumulative total (all its lines), so an oversell is caught
		// whether it comes from a new line or from increasing an existing one.
		var adding = items.filter(function (it) {
			var inScope = (it.entryIdx !== null && it.entryIdx !== undefined) || it.changed;
			return inScope && !alreadyApproved(f, it);
		});
		if (!adding.length) return;
		currentSubmitter = earlySubmitter;

		ev.preventDefault();
		ev.stopPropagation();
		currentForm = f;
		var endpoint = 'orderIncludes/stockCheckBatch.php';
		var varenrCsv = adding.map(function (it) { return it.varenr; }).join(',');
		var qtyCsv = adding.map(function (it) { return it.qty; }).join(',');

		postJSON(endpoint, { items: varenrCsv, quantities: qtyCsv }, function (err, resp) {
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
			// Build one group per origin (the form line that triggered the warning).
			// Filter each flagged item by approval on its OWN varenr — this keeps an
			// already-approved samlesæt sub-item from re-popping, since the master line
			// is not approved under the sub-item's varenr. Dedupe by varenr so an item
			// is never prompted twice in one pass.
			var groupsMap = {};
			var groupOrder = [];
			var seenVr = {};
			for (var oi = 0; oi < resp.out_of_stock.length; oi++) {
				var oos = resp.out_of_stock[oi];
				var vr = (oos.varenr || '').toLowerCase();
				if (seenVr[vr]) continue;
				seenVr[vr] = true;
				if (alreadyApproved(f, { varenr: oos.varenr })) continue;
				var key = String(oos.origin || oos.varenr).toLowerCase();
				if (!groupsMap[key]) {
					groupsMap[key] = { origin: oos.origin || oos.varenr, items: [] };
					groupOrder.push(key);
				}
				groupsMap[key].items.push(oos);
			}
			var groups = groupOrder.map(function (k) { return groupsMap[k]; });
			if (!groups.length) {
				f.dataset.swApproved = '1';
				submitFormNative(f);
				return;
			}
			promptGroups(
				groups,
				function onAllDone() {
					f.dataset.swApproved = '1';
					submitFormNative(f);
				},
				function onCancel(group) {
					f.dataset.swApproved = '';
					clearRejectedLine(f, adding, { origin: group.origin });
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
