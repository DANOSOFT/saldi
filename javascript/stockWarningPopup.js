
(function (global) {
	if (global.SaldiStockWarning) return;

	function injectStyles() {
		if (document.getElementById('saldi-sw-styles')) return;
		var css =
			'.saldi-sw-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:center;justify-content:center;font-family:Arial,Helvetica,sans-serif;}' +
			'.saldi-sw-modal{background:#fff;border-radius:6px;padding:24px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.3);}' +
			'.saldi-sw-title{font-size:18px;font-weight:bold;color:#b00;margin:0 0 12px;}' +
			'.saldi-sw-text{font-size:14px;margin:0 0 16px;color:#222;}' +
			'.saldi-sw-item{font-size:13px;color:#555;margin:0 0 16px;padding:8px;background:#fbeaea;border-left:3px solid #b00;}' +
			'.saldi-sw-actions{display:flex;justify-content:flex-end;gap:10px;}' +
			'.saldi-sw-btn{padding:8px 18px;border:0;border-radius:4px;font-size:14px;cursor:pointer;}' +
			'.saldi-sw-btn-yes{background:#b00;color:#fff;}' +
			'.saldi-sw-btn-no{background:#ddd;color:#222;}' +
			'.saldi-sw-note{width:100%;min-height:80px;padding:8px;font-size:14px;box-sizing:border-box;border:1px solid #bbb;border-radius:4px;margin-bottom:8px;}' +
			'.saldi-sw-error{color:#b00;font-size:12px;margin:0 0 8px;min-height:14px;}' +
			'.saldi-sw-hint{font-size:12px;color:#666;margin:12px 0 0;}' +
			'.saldi-sw-modal:focus{outline:none;}' +
			'.saldi-sw-attn{box-shadow:0 0 0 4px #b00,0 8px 32px rgba(0,0,0,.3);}';
		var s = document.createElement('style');
		s.id = 'saldi-sw-styles';
		s.appendChild(document.createTextNode(css));
		document.head.appendChild(s);
	}

	// The modal currently on screen: { overlay, modal, onEscape }. While it is set,
	// keystrokes are kept inside the modal. Without this a barcode scanner kept
	// typing into the POS form hidden behind the overlay and its Enter resubmitted
	// that form, so every scan brought back the same popup and the cashier could
	// not get out of it without a mouse (SST-813).
	var active = null;
	var attnTimer = null;

	function focusables(modal) {
		return Array.prototype.slice.call(modal.querySelectorAll('button:not([disabled]), textarea'));
	}

	function attention() {
		if (!active) return;
		var modal = active.modal;
		modal.classList.add('saldi-sw-attn');
		clearTimeout(attnTimer);
		attnTimer = setTimeout(function () { modal.classList.remove('saldi-sw-attn'); }, 400);
	}

	function onKeydown(e) {
		if (!active) return;
		var modal = active.modal;
		var inside = modal.contains(e.target);
		if (e.key === 'Escape' || e.key === 'Esc') {
			e.preventDefault();
			e.stopPropagation();
			if (typeof active.onEscape === 'function') active.onEscape();
			return;
		}
		if (e.key === 'Tab') {
			var els = focusables(modal);
			e.preventDefault();
			if (!els.length) { modal.focus(); return; }
			var i = els.indexOf(document.activeElement);
			i = e.shiftKey ? (i <= 0 ? els.length - 1 : i - 1) : (i + 1) % els.length;
			els[i].focus();
			return;
		}
		// Buttons and the note field handle their own keys (Enter clicks a focused
		// button). Anything else - the modal itself or the page behind it - must not
		// receive scanner input.
		if (inside && e.target !== modal) return;
		if (e.ctrlKey || e.metaKey || e.altKey) return;
		e.preventDefault();
		e.stopPropagation();
		if (!inside) modal.focus();
		attention();
	}

	function onFocusin(e) {
		if (active && !active.modal.contains(e.target)) active.modal.focus();
	}

	function buildModal(html, onEscape) {
		removeModal();
		var overlay = document.createElement('div');
		overlay.className = 'saldi-sw-overlay';
		overlay.innerHTML = '<div class="saldi-sw-modal" role="dialog" aria-modal="true" tabindex="-1">' + html + '</div>';
		document.body.appendChild(overlay);
		var modal = overlay.querySelector('.saldi-sw-modal');
		active = { overlay: overlay, modal: modal, onEscape: onEscape };
		document.addEventListener('keydown', onKeydown, true);
		document.addEventListener('focusin', onFocusin, true);
		if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
		modal.focus();
		return overlay;
	}

	function removeModal() {
		if (!active) return;
		var overlay = active.overlay;
		active = null;
		document.removeEventListener('keydown', onKeydown, true);
		document.removeEventListener('focusin', onFocusin, true);
		if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
	}

	function findForm(formName) {
		if (formName && document.forms[formName]) return document.forms[formName];
		return document.forms[0] || null;
	}

	function resubmit(formName, note, extra, opts) {
		var form = findForm(formName);
		if (!form) {
			alert('Stock warning: could not locate form to resubmit.');
			return;
		}
		function setHidden(name, value) {
			var el = form.elements[name];
			if (!el) {
				el = document.createElement('input');
				el.type = 'hidden';
				el.name = name;
				form.appendChild(el);
			}
			el.value = value;
		}
		setHidden('stock_warning_confirmed', '1');
		setHidden('stock_warning_note', note);
		if (extra && typeof extra === 'object') {
			for (var k in extra) {
				if (Object.prototype.hasOwnProperty.call(extra, k)) setHidden(k, extra[k]);
			}
		}
		showPending(opts || {});
		form.submit();
	}

	// Keeps the screen covered while the confirmed sale is sent, so a scan cannot
	// submit the form a second time. If the server has not answered within
	// PENDING_TIMEOUT_MS the cashier gets a clear message and a way out instead of
	// a page that just sits there.
	var PENDING_TIMEOUT_MS = 15000;

	function showPending(opts) {
		var t = textsFrom(opts);
		// The server may still have saved the line, so reload to show the order as
		// it really is rather than letting the cashier scan the item a second time.
		var close = function () {
			if (window.stop) window.stop();
			removeModal();
			window.location.assign(window.location.href);
		};
		var pending = buildModal(
			'<h3 class="saldi-sw-title">' + escapeHtml(t.pending_title) + '</h3>' +
			'<p class="saldi-sw-text" data-role="status">' + escapeHtml(t.pending_text) + '</p>' +
			'<div class="saldi-sw-actions" data-role="actions" style="display:none">' +
			'<button type="button" class="saldi-sw-btn saldi-sw-btn-no" data-action="close">' + escapeHtml(t.btn_close) + '</button>' +
			'</div>'
		);
		pending.querySelector('[data-action="close"]').addEventListener('click', close);
		setTimeout(function () {
			if (!active || active.overlay !== pending) return;
			active.onEscape = close;
			var status = pending.querySelector('[data-role="status"]');
			status.className = 'saldi-sw-error';
			status.textContent = t.pending_timeout;
			pending.querySelector('[data-role="actions"]').style.display = '';
			pending.querySelector('[data-action="close"]').focus();
		}, PENDING_TIMEOUT_MS);
	}

	function escapeHtml(s) {
		if (s == null) return '';
		return String(s).replace(/[&<>"']/g, function (c) {
			return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
		});
	}

	var DEFAULT_TEXTS = {
		popup_title:       'Vare ikke på lager',
		popup_text:        'Denne vare er ikke på lager – ønsker du alligevel at fortsætte med salget?',
		btn_no:            'Nej',
		btn_yes:           'Ja, fortsæt',
		note_title:        'Begrundelse påkrævet',
		note_text:         'Angiv venligst en begrundelse for at sælge en udsolgt vare:',
		note_placeholder:  'Fx: Varen forventes hjem d. XX, eller kunden er informeret om forsinkelse',
		btn_cancel:        'Annullér',
		btn_confirm:       'Bekræft salg',
		error_required:    'Begrundelse er påkrævet.',
		hint_keys:         'Esc = Nej. Tab skifter knap.',
		pending_title:     'Gemmer salget…',
		pending_text:      'Vent venligst.',
		pending_timeout:   'Serveren svarer ikke. Tryk Luk for at genindlæse, og tjek om varen kom på bonen, før du scanner den igen.',
		btn_close:         'Luk'
	};

	function readJsonScript(id) {
		var el = document.getElementById(id);
		if (!el) return null;
		try { return JSON.parse(el.textContent || el.innerText || '{}'); }
		catch (e) { console && console.warn && console.warn('saldi-sw: bad JSON in #' + id, e); return null; }
	}

	function textsFrom(opts) {
		var t = {};
		var k;
		for (k in DEFAULT_TEXTS) t[k] = DEFAULT_TEXTS[k];
		var fromTag = readJsonScript('saldi-sw-texts');
		if (fromTag && typeof fromTag === 'object') {
			for (k in fromTag) if (fromTag[k]) t[k] = fromTag[k];
		}
		var pageWide = window.SaldiStockWarningTexts;
		if (pageWide && typeof pageWide === 'object') {
			for (k in pageWide) if (pageWide[k]) t[k] = pageWide[k];
		}
		if (opts && opts.texts && typeof opts.texts === 'object') {
			for (k in opts.texts) if (opts.texts[k]) t[k] = opts.texts[k];
		}
		return t;
	}

	function show(opts) {
		opts = opts || {};
		injectStyles();
		var t = textsFrom(opts);
		var itemTxt = '';
		if (opts.items && opts.items.length) {
			// Multiple lines in one dialog (e.g. all out-of-stock components of a
			// samlesæt) so the user confirms the whole set with a single reason.
			for (var ii = 0; ii < opts.items.length; ii++) {
				var it = opts.items[ii] || {};
				itemTxt += '<div class="saldi-sw-item"><b>' + escapeHtml(it.varenr || '') + '</b> ' + escapeHtml(it.beskrivelse || '') + '</div>';
			}
		} else if (opts.varenr || opts.beskrivelse) {
			itemTxt = '<div class="saldi-sw-item"><b>' + escapeHtml(opts.varenr || '') + '</b> ' + escapeHtml(opts.beskrivelse || '') + '</div>';
		}
		var cancel = function () {
			removeModal();
			if (typeof opts.onCancel === 'function') opts.onCancel();
		};
		// Focus starts on the dialog, not on a button, so a stray scanner Enter
		// cannot answer it either way.
		var step1 = buildModal(
			'<h3 class="saldi-sw-title">' + escapeHtml(t.popup_title) + '</h3>' +
			'<p class="saldi-sw-text">' + escapeHtml(t.popup_text) + '</p>' +
			itemTxt +
			'<div class="saldi-sw-actions">' +
			'<button type="button" class="saldi-sw-btn saldi-sw-btn-no" data-action="no">' + escapeHtml(t.btn_no) + '</button>' +
			'<button type="button" class="saldi-sw-btn saldi-sw-btn-yes" data-action="yes">' + escapeHtml(t.btn_yes) + '</button>' +
			'</div>' +
			'<p class="saldi-sw-hint">' + escapeHtml(t.hint_keys) + '</p>',
			cancel
		);
		step1.querySelector('[data-action="no"]').addEventListener('click', cancel);
		step1.querySelector('[data-action="yes"]').addEventListener('click', function () {
			openNote(opts, itemTxt, t);
		});
	}

	function openNote(opts, itemTxt, t) {
		var cancel = function () {
			removeModal();
			if (typeof opts.onCancel === 'function') opts.onCancel();
		};
		var step2 = buildModal(
			'<h3 class="saldi-sw-title">' + escapeHtml(t.note_title) + '</h3>' +
			'<p class="saldi-sw-text">' + escapeHtml(t.note_text) + '</p>' +
			itemTxt +
			'<textarea class="saldi-sw-note" placeholder="' + escapeHtml(t.note_placeholder) + '"></textarea>' +
			'<p class="saldi-sw-error" data-role="error"></p>' +
			'<div class="saldi-sw-actions">' +
			'<button type="button" class="saldi-sw-btn saldi-sw-btn-no" data-action="cancel">' + escapeHtml(t.btn_cancel) + '</button>' +
			'<button type="button" class="saldi-sw-btn saldi-sw-btn-yes" data-action="submit">' + escapeHtml(t.btn_confirm) + '</button>' +
			'</div>',
			cancel
		);
		var ta = step2.querySelector('textarea');
		var err = step2.querySelector('[data-role="error"]');
		ta.focus();
		step2.querySelector('[data-action="cancel"]').addEventListener('click', cancel);
		step2.querySelector('[data-action="submit"]').addEventListener('click', function () {
			var note = (ta.value || '').trim();
			if (!note) {
				err.textContent = t.error_required;
				ta.focus();
				return;
			}
			removeModal();
			if (typeof opts.onConfirm === 'function') {
				opts.onConfirm(note);
			} else {
				resubmit(opts.formName, note, opts.extra, opts);
			}
		});
	}

	global.SaldiStockWarning = { show: show, resubmit: resubmit };
})(window);
