//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- javascript/einvoiceCvrPopup.js --- ver 5.0.0 --- 2026-10-26 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261023 CL/MMR SD-774 Created. The order page (debitor/ordre.php) asks for the receiver's CVR when a digital invoice to an EAN would be rejected without it.
//                 The data and all texts (findtekst()) come from the JSON tag printed by debitor/orderIncludes/einvoiceCvr.php, and the checks mirror includes/stdFunc/einvoiceCvr.php.
//                 The file is kept ASCII, so it reads the same in a UTF-8 and an ISO-8859-1 page.
// 20261026 CL/MMR SD-774 cvrPresent() mirrors einvoice_cvr_present(): a CVR field holding a 13-digit EAN/GLN counts as empty.
// 20261026 CL/MMR SD-774 cvrPresent() also needs a digit other than 0, like einvoice_cvr_present().
//                 Choosing Digitalt on an invoiced order forgets an earlier Annuller, so the dialog opens again after the reload.

(function (global) {
	'use strict';

	if (global.SaldiEinvoiceCvr) {
		return;
	}

	var DATA_ID = 'saldi-einvoice-cvr-data';
	var FORM_SELECTOR = 'form[name="ordre"]';

	// The texts the page's JSON carries (einvoiceCvrPopupTexts() in PHP).
	var TEXT_KEYS = ['title', 'text', 'lookup', 'toCardNew', 'toCardReplace', 'invalid', 'missing', 'save', 'cancel', 'cvrLabel', 'eanLabel'];

	// ---- Checks, mirroring includes/stdFunc/einvoiceCvr.php ----

	// PCRE's \s without the u modifier, as einvoice_normalize_cvr() strips it.
	var PHP_SPACE = /[ \t\n\x0B\f\r]+/g;
	// The characters PHP's trim() removes.
	var PHP_TRIM = /^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g;

	function phpTrim(value) {
		return String(value == null ? '' : value).replace(PHP_TRIM, '');
	}

	// einvoice_normalize_cvr(): no whitespace, upper case (ASCII only, like strtoupper()), "DK" dropped before 8 digits.
	function normalizeCvr(raw) {
		var cvr = String(raw == null ? '' : raw).replace(PHP_SPACE, '');
		cvr = cvr.replace(/[a-z]+/g, function (s) {
			return s.toUpperCase();
		});
		var m = /^DK([0-9]{8})$/.exec(cvr);
		return m ? m[1] : cvr;
	}

	// einvoice_cvr_present(): a digit other than 0, and not a 13-digit EAN/GLN (with or without "DK") typed into the CVR field.
	function cvrPresent(raw) {
		var cvr = normalizeCvr(raw);
		return /[1-9]/.test(cvr) && !/^(DK)?[0-9]{13}$/.test(cvr);
	}

	// einvoice_cvr_mod11_ok(): weights 2,7,6,5,4,3,2,1, the sum a multiple of 11.
	function cvrMod11Ok(cvr) {
		if (!/^[0-9]{8}$/.test(cvr)) {
			return false;
		}
		var weights = [2, 7, 6, 5, 4, 3, 2, 1];
		var sum = 0;
		for (var i = 0; i < 8; i++) {
			sum += (cvr.charCodeAt(i) - 48) * weights[i];
		}
		return sum % 11 === 0;
	}

	// einvoice_cvr_input_problem(): '' when valid, 'missing' when empty, 'invalid' otherwise.
	function cvrInputProblem(raw) {
		var cvr = normalizeCvr(raw);
		if (cvr === '') {
			return 'missing';
		}
		if (/^[0-9]{8}$/.test(cvr)) {
			return cvrMod11Ok(cvr) ? '' : 'invalid';
		}
		if (/^[A-Z]{2}[A-Z0-9]{2,18}$/.test(cvr) && cvr.substring(0, 2) !== 'DK') {
			return '';
		}
		return 'invalid';
	}

	// PHP 8 is_numeric(): an integer or decimal number, optional sign and exponent, whitespace around it allowed.
	function phpIsNumeric(value) {
		return /^[ \t\n\r\x0B\f]*[+-]?([0-9]+(\.[0-9]*)?|\.[0-9]+)([eE][+-]?[0-9]+)?[ \t\n\r\x0B\f]*$/.test(value);
	}

	// einvoice_is_gln_endpoint(): a plain EAN, or "scheme:value" with a scheme that is not a number.
	function isGlnEndpoint(ean) {
		ean = phpTrim(ean);
		if (ean === '') {
			return false;
		}
		var colon = ean.indexOf(':');
		if (colon === -1) {
			return true;
		}
		return !phpIsNumeric(phpTrim(ean.substring(0, colon)));
	}

	// ---- Page data ----

	function readData() {
		var el = document.getElementById(DATA_ID);
		if (!el) {
			return null;
		}
		var data;
		try {
			data = JSON.parse(el.textContent || '{}');
		} catch (e) {
			return null;
		}
		if (!data || typeof data !== 'object' || !data.texts || typeof data.texts !== 'object') {
			return null;
		}
		var texts = {};
		for (var i = 0; i < TEXT_KEYS.length; i++) {
			var key = TEXT_KEYS[i];
			texts[key] = (typeof data.texts[key] === 'string') ? data.texts[key] : '';
		}
		data.texts = texts;
		return data;
	}

	function findForm() {
		return document.querySelector(FORM_SELECTOR);
	}

	// What the user typed or pasted, without any whitespace. JavaScript's \s also covers the
	// no-break and other Unicode spaces a copy from a web page can bring along, which PHP's
	// einvoice_normalize_cvr() keeps; the popup always submits this cleaned value.
	function cleanInput(value) {
		return String(value == null ? '' : value).replace(/\s+/g, '');
	}

	function fieldValue(form, name) {
		var el = form.querySelector('[name="' + name + '"]');
		return el ? el.value : '';
	}

	function setFieldValue(form, name, value) {
		var els = form.querySelectorAll('[name="' + name + '"]');
		for (var i = 0; i < els.length; i++) {
			els[i].value = value;
		}
	}

	function setHidden(form, name, value) {
		var el = form.querySelector('input[type="hidden"][name="' + name + '"]');
		if (!el) {
			el = document.createElement('input');
			el.type = 'hidden';
			el.name = name;
			form.appendChild(el);
		}
		el.value = value;
	}

	// The EAN the invoice would go to with the form as it is now: the order's field, else the card's.
	function currentEan(form, data) {
		var formEan = phpTrim(fieldValue(form, 'ean'));
		return formEan !== '' ? formEan : phpTrim(data.cardEan);
	}

	// Same rule as einvoice_cvr_missing() on the form's current values.
	function formNeedsCvr(form, data, ean) {
		if (!data.receiverDanish) {
			return false;
		}
		if (cvrPresent(fieldValue(form, 'cvrnr')) || cvrPresent(data.cardCvr)) {
			return false;
		}
		return isGlnEndpoint(ean);
	}

	// ordre.php's unlock beacon skips the unlock while isSubmitting is true. A programmatic
	// submit() fires no submit event, so the page's own listener cannot set it.
	function markSubmitting() {
		try {
			if (typeof isSubmitting !== 'undefined') {
				isSubmitting = true; // eslint-disable-line no-undef
			}
		} catch (e) {
			// Not on this page.
		}
	}

	// ---- Dialog ----

	var active = null;

	function injectStyles() {
		if (document.getElementById('saldi-ecvr-styles')) {
			return;
		}
		var css =
			'.saldi-ecvr-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:center;justify-content:center;font-family:Arial,Helvetica,sans-serif;}' +
			'.saldi-ecvr-modal{background:#fff;border-radius:6px;padding:24px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.3);text-align:left;}' +
			'.saldi-ecvr-modal:focus{outline:none;}' +
			'.saldi-ecvr-title{font-size:18px;font-weight:bold;color:#b00;margin:0 0 12px;}' +
			'.saldi-ecvr-text{font-size:14px;margin:0 0 12px;color:#222;}' +
			'.saldi-ecvr-ean{font-size:14px;margin:0 0 12px;color:#222;}' +
			'.saldi-ecvr-label{display:block;font-size:13px;font-weight:bold;color:#222;margin:0 0 4px;}' +
			'.saldi-ecvr-input{width:100%;padding:6px 8px;font-size:14px;box-sizing:border-box;border:1px solid #bbb;border-radius:4px;}' +
			'.saldi-ecvr-input[aria-invalid="true"]{border-color:#b00;}' +
			'.saldi-ecvr-error{color:#b00;font-size:12px;margin:4px 0 8px;min-height:14px;}' +
			'.saldi-ecvr-link{font-size:13px;margin:0 0 12px;}' +
			'.saldi-ecvr-check{display:block;font-size:13px;color:#222;margin:0 0 16px;cursor:pointer;}' +
			'.saldi-ecvr-actions{display:flex;justify-content:flex-end;gap:10px;}' +
			'.saldi-ecvr-btn{padding:8px 18px;border:0;border-radius:4px;font-size:14px;cursor:pointer;}' +
			'.saldi-ecvr-btn-save{background:#114691;color:#fff;}' +
			'.saldi-ecvr-btn-cancel{background:#ddd;color:#222;}';
		var style = document.createElement('style');
		style.id = 'saldi-ecvr-styles';
		style.appendChild(document.createTextNode(css));
		document.head.appendChild(style);
	}

	function escapeHtml(s) {
		if (s == null) {
			return '';
		}
		return String(s).replace(/[&<>"']/g, function (c) {
			return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
		});
	}

	function focusables(modal) {
		return Array.prototype.slice.call(modal.querySelectorAll('input:not([disabled]), a[href], button:not([disabled])'));
	}

	// While the dialog is open, Tab stays inside it, Esc closes it, and the order form behind
	// the overlay gets no keys (Enter there would submit it).
	function onKeydown(e) {
		if (!active) {
			return;
		}
		var modal = active.modal;
		if (e.key === 'Escape' || e.key === 'Esc') {
			e.preventDefault();
			e.stopPropagation();
			cancel();
			return;
		}
		if (e.key === 'Tab') {
			var els = focusables(modal);
			e.preventDefault();
			if (!els.length) {
				modal.focus();
				return;
			}
			var i = els.indexOf(document.activeElement);
			i = e.shiftKey ? (i <= 0 ? els.length - 1 : i - 1) : (i + 1) % els.length;
			els[i].focus();
			return;
		}
		if (!modal.contains(e.target)) {
			e.preventDefault();
			e.stopPropagation();
			active.input.focus();
		}
	}

	function onFocusin(e) {
		if (active && !active.modal.contains(e.target)) {
			active.input.focus();
		}
	}

	// The order the page shows, used to remember a dismissal for this browser session only.
	var dismissKey = '';

	// Annuller/Esc: close, and do not open again by itself on the next load of this order in this
	// session (the reloads after each new order line would otherwise reopen it every time). Choosing
	// Digitalt again still opens it, and sending stays blocked by debitor/api.php.
	function cancel() {
		if (dismissKey) {
			try {
				window.sessionStorage.setItem(dismissKey, '1');
			} catch (e) {
				// No storage (private mode): the dialog simply opens again next time.
			}
		}
		close();
	}

	// Choosing Digitalt is an explicit request, so it clears an earlier Annuller for this order.
	function forgetDismissal() {
		if (!dismissKey) {
			return;
		}
		try {
			window.sessionStorage.removeItem(dismissKey);
		} catch (e) {
			// No storage: nothing was remembered.
		}
	}

	function wasDismissed() {
		if (!dismissKey) {
			return false;
		}
		try {
			return window.sessionStorage.getItem(dismissKey) === '1';
		} catch (e) {
			return false;
		}
	}

	function close() {
		if (!active) {
			return;
		}
		var overlay = active.overlay;
		var returnFocus = active.returnFocus;
		active = null;
		document.removeEventListener('keydown', onKeydown, true);
		document.removeEventListener('focusin', onFocusin, true);
		if (overlay.parentNode) {
			overlay.parentNode.removeChild(overlay);
		}
		if (returnFocus && typeof returnFocus.focus === 'function') {
			try {
				returnFocus.focus();
			} catch (e) {
				// The element may be gone.
			}
		}
	}

	// Puts the CVR in the order form and submits it: through the Gem button on an open order, so the
	// order's own save runs (and stores the same CVR), or directly on an invoiced one.
	function submitCvr(data, cvr, toCard) {
		var form = findForm();
		if (!form) {
			return;
		}
		setHidden(form, 'einvoiceCvrSave', '1');
		setHidden(form, 'einvoiceCvr', cvr);
		setHidden(form, 'einvoiceCvrToCard', toCard ? '1' : '0');
		setHidden(form, 'einvoiceCvrCardWas', data.cardCvr || '');
		setFieldValue(form, 'cvrnr', cvr);
		if (data.open) {
			var saveButton = form.querySelector('input[name="save"]');
			if (saveButton && !saveButton.disabled) {
				saveButton.click();
				return;
			}
		}
		markSubmitting();
		// The Gem button (id="submit") shadows form.submit on an open order.
		HTMLFormElement.prototype.submit.call(form);
	}

	/**
	 * Opens the dialog.
	 *
	 * @param {Object} data The page data (readData()).
	 * @param {string} ean The EAN shown in the dialog.
	 * @param {Element} [returnFocus] Element to focus again when the dialog is cancelled.
	 */
	function show(data, ean, returnFocus) {
		if (active || !document.body) {
			return;
		}
		injectStyles();
		var t = data.texts;
		var cardHasCvr = cvrPresent(data.cardCvr);
		var lookupUrl = /^https:\/\//.test(String(data.lookupUrl || '')) ? String(data.lookupUrl) : '';
		var overlay = document.createElement('div');
		overlay.className = 'saldi-ecvr-overlay';
		overlay.innerHTML =
			'<div class="saldi-ecvr-modal" role="dialog" aria-modal="true" aria-labelledby="saldi-ecvr-title" aria-describedby="saldi-ecvr-text" tabindex="-1">' +
			'<h3 class="saldi-ecvr-title" id="saldi-ecvr-title"><span aria-hidden="true">&#9888;</span> ' + escapeHtml(t.title) + '</h3>' +
			'<p class="saldi-ecvr-text" id="saldi-ecvr-text">' + escapeHtml(t.text) + '</p>' +
			'<p class="saldi-ecvr-ean">' + escapeHtml(t.eanLabel) + ': <b>' + escapeHtml(ean) + '</b></p>' +
			'<label class="saldi-ecvr-label" for="saldi-ecvr-input">' + escapeHtml(t.cvrLabel) + '</label>' +
			'<input type="text" class="saldi-ecvr-input" id="saldi-ecvr-input" autocomplete="off" spellcheck="false" maxlength="40" aria-describedby="saldi-ecvr-error">' +
			'<p class="saldi-ecvr-error" id="saldi-ecvr-error" role="alert"></p>' +
			(lookupUrl ? '<p class="saldi-ecvr-link"><a href="' + escapeHtml(lookupUrl) + '" target="_blank" rel="noopener">' + escapeHtml(t.lookup) + ' <span aria-hidden="true">&#8599;</span></a></p>' : '') +
			'<label class="saldi-ecvr-check"><input type="checkbox" data-role="tocard"' + (cardHasCvr ? '' : ' checked') + '> ' +
			escapeHtml(cardHasCvr ? t.toCardReplace : t.toCardNew) + '</label>' +
			'<div class="saldi-ecvr-actions">' +
			'<button type="button" class="saldi-ecvr-btn saldi-ecvr-btn-save" data-action="save">' + escapeHtml(t.save) + '</button>' +
			'<button type="button" class="saldi-ecvr-btn saldi-ecvr-btn-cancel" data-action="cancel">' + escapeHtml(t.cancel) + '</button>' +
			'</div>' +
			'</div>';
		document.body.appendChild(overlay);

		var modal = overlay.querySelector('.saldi-ecvr-modal');
		var input = overlay.querySelector('#saldi-ecvr-input');
		var error = overlay.querySelector('#saldi-ecvr-error');
		var toCard = overlay.querySelector('[data-role="tocard"]');

		function showProblem(problem) {
			error.textContent = (problem === 'missing') ? t.missing : t.invalid;
			input.setAttribute('aria-invalid', 'true');
			input.focus();
		}

		function save() {
			var typed = cleanInput(input.value);
			var problem = cvrInputProblem(typed);
			if (problem !== '') {
				showProblem(problem);
				return;
			}
			var keepToCard = toCard.checked;
			if (active) {
				active.returnFocus = null;
			}
			close();
			submitCvr(data, normalizeCvr(typed), keepToCard);
		}

		input.value = String(data.value || '');
		if (data.error === 'missing' || data.error === 'invalid') {
			showProblem(data.error);
		}
		input.addEventListener('input', function () {
			if (input.getAttribute('aria-invalid') === 'true' && cvrInputProblem(cleanInput(input.value)) === '') {
				error.textContent = '';
				input.removeAttribute('aria-invalid');
			}
		});
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				save();
			}
		});
		// Keys typed in the dialog stop here, so the order page's own key handlers never see them.
		overlay.addEventListener('keydown', function (e) {
			e.stopPropagation();
		});
		overlay.querySelector('[data-action="cancel"]').addEventListener('click', cancel);
		overlay.querySelector('[data-action="save"]').addEventListener('click', save);

		active = {overlay: overlay, modal: modal, input: input, returnFocus: returnFocus || null};
		document.addEventListener('keydown', onKeydown, true);
		document.addEventListener('focusin', onFocusin, true);
		input.focus();
	}

	// On an open order "Udskriv til" has no auto-submit: open the dialog as soon as Digitalt is
	// chosen while the form's own values still lack the CVR. On an invoiced order the select submits
	// the form, and the reload opens the dialog by itself once an earlier Annuller is forgotten.
	function bindDigitalChange(form, data) {
		var select = form.querySelector('select[name="udskriv_til"]');
		if (!select) {
			return;
		}
		select.addEventListener('change', function () {
			if (select.value !== 'Digitalt') {
				return;
			}
			forgetDismissal();
			if (!data.open) {
				return;
			}
			var ean = currentEan(form, data);
			if (formNeedsCvr(form, data, ean)) {
				show(data, ean, select);
			}
		});
	}

	function init() {
		var data = readData();
		if (!data) {
			return;
		}
		var form = findForm();
		if (!form) {
			return;
		}
		dismissKey = data.orderId ? 'saldiEinvoiceCvrDismissed:' + data.orderId : '';
		bindDigitalChange(form, data);
		// A rejected input is always shown again; otherwise respect a dismissal in this session.
		if (data.autoOpen && (data.error || !wasDismissed())) {
			show(data, String(data.ean || ''));
		}
	}

	global.SaldiEinvoiceCvr = {
		show: show,
		cvrInputProblem: cvrInputProblem,
		normalizeCvr: normalizeCvr,
		isGlnEndpoint: isGlnEndpoint
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window);
