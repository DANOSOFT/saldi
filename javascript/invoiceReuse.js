// --- javascript/invoiceReuse.js --- ver 5.0.0 --- 2026-10-03 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ Doc pool task 3: Created: the "invoice number already used on this kreditor" warning.
//                  - Journal and pool: the first Enter on a line with a warning moves the focus to the warning
//                    instead of saving, so it can't be missed; Enter there saves the way Enter in the field would.
//                  - Pool (window.saldiInvoiceReuse set): checks each line on load and when Kredit or Faktura changes.
(function () {
    'use strict';

    const WARNING = '[data-invoice-reuse]';

    /** Line a field or warning belongs to: a journal table row or a pool line. */
    function lineOf(el) {
        return el.closest('tr, .kassebilag-entry');
    }

    /** What Enter in the field does without the warning: its own key handlers, else the form's default button. */
    function enterAsBefore(field) {
        field.focus();
        const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });
        if (!field.dispatchEvent(event) || !field.form) return;
        const defaultButton = field.form.querySelector('button:not([type]), button[type="submit"], input[type="submit"], input[type="image"]');
        if (defaultButton) defaultButton.click();
    }

    // Bubble phase, so the lookup panel's own Enter (selecting a line) has already taken the key
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.defaultPrevented || e.isComposing || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return;
        const target = e.target;
        if (!(target instanceof Element)) return;

        const warning = target.closest(WARNING);
        if (warning) {
            // Enter on the warning itself: go on and save
            if (e.target.closest('a')) return;
            e.preventDefault();
            const line = lineOf(warning);
            const origin = warning.invoiceReuseOrigin || (line && line.querySelector('input.invoice-reuse-field'));
            if (origin) enterAsBefore(origin);
            return;
        }

        if (!target.matches('input, select')) return;
        const line = lineOf(target);
        const unseen = line && line.querySelector(WARNING + ':not([data-seen])');
        if (!unseen) return;
        e.preventDefault();
        unseen.dataset.seen = '1';
        unseen.invoiceReuseOrigin = target;
        unseen.focus();
    });

    // A warning the user tabbed or clicked to has been seen
    document.addEventListener('focusin', function (e) {
        const warning = e.target instanceof Element && e.target.closest(WARNING);
        if (warning) warning.dataset.seen = '1';
    });

    /* ---------- Document pool ---------- */

    function poolKreditor(prefix) {
        if (typeof window.poolAccountValue !== 'function') return '';
        const match = window.poolAccountValue(prefix, 'Kredit').match(/^K(\d+)$/i);
        return match ? match[1] : '';
    }

    function value(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function checkPoolLine(entry) {
        const cfg = window.saldiInvoiceReuse;
        const rowId = entry.id.replace('bilagEntry_', '');
        const prefix = 'row_' + rowId + '_';
        const field = document.getElementById(prefix + 'Faktura');
        if (!cfg || !field || field.readOnly) return;
        const kontonr = poolKreditor(prefix);
        const faktura = value(prefix + 'Faktura');
        const query = 'kontonr=' + encodeURIComponent(kontonr) + '&faktura=' + encodeURIComponent(faktura) +
            '&line=' + encodeURIComponent(/^\d+$/.test(rowId) ? rowId : 0) + '&kladde_id=' + encodeURIComponent(cfg.kladdeId || 0) +
            '&bilag=' + encodeURIComponent(value(prefix + 'Bilag')) + '&pool=' + encodeURIComponent(cfg.poolFile || '');
        entry.invoiceReuseQuery = query;
        fetch(cfg.url + '?' + query, { credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : {}; })
            .then(function (data) {
                // A newer check for this line has started meanwhile
                if (entry.invoiceReuseQuery !== query) return;
                showPoolWarning(entry, field, data.html || '');
            })
            .catch(function () {});
    }

    function showPoolWarning(entry, field, html) {
        let note = entry.querySelector('.invoice-reuse-note');
        if (!html) {
            if (note) note.remove();
            field.classList.remove('invoice-reuse-field');
            return;
        }
        if (note && note.dataset.html === html) return;
        if (note) note.remove();
        // The text comes escaped from invoice_reuse_html()
        note = document.createElement('div');
        note.className = 'invoice-reuse-note';
        note.tabIndex = 0;
        note.setAttribute('role', 'note');
        note.dataset.invoiceReuse = '1';
        note.dataset.html = html;
        note.innerHTML = '&#9888; ' + html;
        entry.appendChild(note);
        field.classList.add('invoice-reuse-field');
    }

    function initPool() {
        if (!window.saldiInvoiceReuse) return;
        document.querySelectorAll('.kassebilag-entry').forEach(checkPoolLine);
        document.addEventListener('change', function (e) {
            const el = e.target;
            if (!(el instanceof Element) || !/^row_.+_(Faktura|Kredit|KreditType)$/.test(el.id || '')) return;
            const entry = el.closest('.kassebilag-entry');
            if (entry) checkPoolLine(entry);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPool);
    } else {
        initPool();
    }
})();
