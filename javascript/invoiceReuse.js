// --- javascript/invoiceReuse.js --- ver 5.0.0 --- 2026-10-03 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-715 Created: the "invoice number already used on this kreditor" warning.
//                Journal and pool: the first Enter on a line with a warning moves the focus to the warning instead of saving, so it can't be missed.
//                Enter on the warning then saves the way Enter in the field would.
//                Pool (window.saldiInvoiceReuse set): checks each line on load and when Kredit or Faktura changes.
// 20261006 CL/SZ SD-715 Journal (window.saldiInvoiceReuseJournal set): a line is checked when its Kredit, Kredit type or Fakturanr. changes, so the warning shows
//                while the line is typed, not only after "Gem" (the page's own check only sees saved lines).
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

    /* ---------- Journal ---------- */

    /** The journal row's field with this name prefix (fakt, kred, k_ty, bila), e.g. fakt7 on row 7. */
    function journalField(form, name, row) {
        return form.elements.namedItem(name + row);
    }

    function checkJournalLine(form, row) {
        const cfg = window.saldiInvoiceReuseJournal;
        const field = journalField(form, 'fakt', row);
        if (!cfg || !(field instanceof HTMLInputElement) || field.readOnly || field.disabled) return;
        const kType = journalField(form, 'k_ty', row);
        const kredit = journalField(form, 'kred', row);
        const kontonr = kType && kType.value.trim().toUpperCase() === 'K' && kredit ? kredit.value.trim() : '';
        const lineId = form.elements.namedItem('id[' + row + ']');
        const kladde = form.elements.namedItem('kladde_id');
        const bilag = journalField(form, 'bila', row);
        const query = 'kontonr=' + encodeURIComponent(kontonr) + '&faktura=' + encodeURIComponent(field.value.trim()) +
            '&line=' + encodeURIComponent(lineId && /^\d+$/.test(lineId.value) ? lineId.value : 0) +
            '&kladde_id=' + encodeURIComponent(kladde ? kladde.value : 0) + '&bilag=' + encodeURIComponent(bilag ? bilag.value.trim() : '');
        field.invoiceReuseQuery = query;
        if (!kontonr || !field.value.trim()) {
            showJournalWarning(field, '');
            return;
        }
        fetch(cfg.url + '?' + query, { credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : {}; })
            .then(function (data) {
                // A newer check for this line has started meanwhile
                if (field.invoiceReuseQuery !== query) return;
                showJournalWarning(field, data.html || '');
            })
            .catch(function () {});
    }

    /** The same ⚠ and pop-up kassekladde.php prints for a saved line, or none. */
    function showJournalWarning(field, html) {
        const cell = field.closest('td');
        if (!cell) return;
        let flag = cell.querySelector(WARNING);
        if (!html) {
            if (flag) flag.remove();
            field.classList.remove('invoice-reuse-field');
            cell.classList.remove('invoice-reuse-cell');
            return;
        }
        if (flag && flag.dataset.html === html) return;
        if (flag) flag.remove();
        // The text comes escaped from invoice_reuse_html()
        flag = document.createElement('span');
        flag.className = 'invoice-reuse-flag';
        flag.tabIndex = 0;
        flag.setAttribute('role', 'note');
        flag.dataset.invoiceReuse = '1';
        flag.dataset.html = html;
        flag.innerHTML = '&#9888;<span class="invoice-reuse-pop">' + html + '</span>';
        cell.appendChild(flag);
        cell.classList.add('invoice-reuse-cell');
        field.classList.add('invoice-reuse-field');
    }

    document.addEventListener('change', function (e) {
        if (!window.saldiInvoiceReuseJournal) return;
        const el = e.target;
        if (!(el instanceof HTMLInputElement) || !el.form) return;
        const match = /^(fakt|kred|k_ty)(\d+)$/.exec(el.name || '');
        if (match) checkJournalLine(el.form, match[2]);
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
