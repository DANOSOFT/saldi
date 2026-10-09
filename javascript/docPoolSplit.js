// --- javascript/docPoolSplit.js --- ver 5.0.0 --- 2026-10-03 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-720 Created: "Fordeling" in the document pool, one invoice spread over several lines of the same bilag.
//                "Fordeling" adds a row with the first row's bilag, date, invoice number, text and Kredit side, an empty Debet and the remaining amount.
//                The last added row keeps following the remaining amount until its amount is typed in.
//                "Bilagsbalance" under the rows: the document total minus the rows, green at 0,00 and red otherwise.
//                The total is the document's captured amount, or the first row's amount when nothing was captured.
//                A balance other than 0,00 warns on the first Enter (focus on the last row's amount) and saves on the second, as SD-715's warning does.
//                docPoolSaveNext.js saves the rows in the order shown and attaches the document to every row.
//                Needs window.saldiPoolSplit (docPool.php) and the row template #poolSplitTemplate.
(function () {
    'use strict';

    var snapshot = null;  // the first row's amount when "Fordeling" was pressed on a document without a captured amount
    var warnedFor = null; // the balance the warning was shown for; Enter again with the same balance saves

    function cfg() {
        return window.saldiPoolSplit || null;
    }

    function parse(text) {
        var value = typeof window.parseAmountToFloat === 'function' ? window.parseAmountToFloat(text) : parseFloat(String(text).replace(/\./g, '').replace(',', '.'));
        return isNaN(value) ? 0 : value;
    }

    function format(value) {
        return value.toLocaleString('da-DK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function container() {
        return document.getElementById('bilagRowsContainer');
    }

    function rows() {
        var box = container();
        return box ? Array.prototype.slice.call(box.querySelectorAll('.kassebilag-entry')) : [];
    }

    function rowId(entry) {
        return entry.id.replace('bilagEntry_', '');
    }

    function amountField(entry) {
        return document.getElementById('row_' + rowId(entry) + '_Amount');
    }

    function amountOf(entry) {
        var field = amountField(entry);
        return field ? parse(field.value) : 0;
    }

    /** The amount the rows must add up to; null when there is nothing to compare with. */
    function total() {
        var c = cfg();
        var captured = c ? parse(c.docAmount) : 0;
        if (captured) return captured;
        return snapshot;
    }

    function splitRows() {
        return rows().filter(function (entry) { return entry.dataset.split === '1'; });
    }

    /** Shown once "Fordeling" was used, or when a bilag with a captured total has more than one row. */
    function balanceShown() {
        return total() !== null && (splitRows().length > 0 || rows().length > 1);
    }

    function balance() {
        var sum = rows().reduce(function (acc, entry) { return acc + amountOf(entry); }, 0);
        return Math.round((total() - sum) * 100) / 100;
    }

    function clearWarning() {
        warnedFor = null;
        var box = document.getElementById('poolSplitBalance');
        var warning = box && box.querySelector('.pool-split-warning');
        if (warning) warning.textContent = '';
    }

    /** The last added row follows the remaining amount until the user types in it; then the balance is shown. */
    function refresh() {
        var all = rows();
        var last = all[all.length - 1];
        if (last && last.dataset.splitAuto === '1' && total() !== null) {
            var others = all.slice(0, -1).reduce(function (acc, entry) { return acc + amountOf(entry); }, 0);
            var field = amountField(last);
            if (field) field.value = format(Math.round((total() - others) * 100) / 100);
        }
        var box = document.getElementById('poolSplitBalance');
        if (!box) return;
        if (!balanceShown()) {
            box.style.display = 'none';
            clearWarning();
            return;
        }
        var rest = balance();
        box.style.display = '';
        box.classList.toggle('pool-split-ok', Math.abs(rest) < 0.005);
        box.classList.toggle('pool-split-off', Math.abs(rest) >= 0.005);
        box.querySelector('.pool-split-value').textContent = format(rest);
        if (warnedFor !== null && warnedFor !== format(rest)) clearWarning();
    }

    /** The template's ids (row_tpl_*) and lookup names (debe0 ...) made unique for the new row. */
    function prepareRow(node, id) {
        node.querySelectorAll('[id]').forEach(function (el) {
            el.id = el.id.replace(/^row_tpl_/, 'row_' + id + '_').replace(/^bilagEntry_tpl$/, 'bilagEntry_' + id);
        });
        var highest = 0;
        document.querySelectorAll('input[name^="debe"], input[name^="kred"]').forEach(function (input) {
            var match = input.name.match(/(\d+)$/);
            if (match) highest = Math.max(highest, parseInt(match[1], 10));
        });
        var number = highest + 1;
        node.querySelectorAll('input[name]').forEach(function (input) {
            input.name = input.name.replace(/^(d_ty|debe|k_ty|kred)\d+$/, '$1' + number);
        });
        // "Duplikér" saves a copy at once; a split row is not saved yet
        node.querySelectorAll('a[onclick^="duplicateRow"]').forEach(function (link) {
            if (link.parentNode) link.parentNode.remove();
        });
    }

    function copyValue(from, to, field) {
        var source = document.getElementById('row_' + rowId(from) + '_' + field);
        var target = document.getElementById('row_' + to + '_' + field);
        if (source && target) target.value = source.value;
    }

    function addRemoveButton(entry, wrapper) {
        var c = cfg();
        var cell = document.createElement('div');
        cell.className = 'topbar-field';
        var button = document.createElement('a');
        button.href = '#';
        button.className = 'pool-split-remove';
        button.title = c ? c.texts.remove : '';
        button.textContent = '×';
        button.addEventListener('click', function (e) {
            e.preventDefault();
            wrapper.remove();
            refresh();
        });
        cell.appendChild(button);
        var fields = entry.querySelector('.topbar-fields-row');
        (fields || entry).appendChild(cell);
    }

    /** "Fordeling": one more row of the same bilag with the remaining amount. */
    function addRow() {
        var template = document.getElementById('poolSplitTemplate');
        var box = container();
        var all = rows();
        if (!template || !box || !all.length) return;
        var first = all[0];
        if (total() === null) snapshot = amountOf(first);

        var n = 2;
        while (document.getElementById('bilagEntry_new' + n)) n++;
        var id = 'new' + n;
        var wrapper = template.content.firstElementChild.cloneNode(true);
        prepareRow(wrapper, id);
        // Every row shows, also those the collapsed view hid
        box.querySelectorAll('.bilag-row-wrapper').forEach(function (row) { row.style.display = ''; });
        box.appendChild(wrapper);

        var entry = document.getElementById('bilagEntry_' + id);
        entry.dataset.split = '1';
        ['Bilag', 'Dato', 'Faktura', 'Beskrivelse', 'KreditType', 'Kredit', 'Valuta'].forEach(function (field) { copyValue(first, id, field); });
        var firstName = document.getElementById('row_' + rowId(first) + '_KreditName');
        var name = document.getElementById('row_' + id + '_KreditName');
        if (firstName && name) {
            name.innerHTML = firstName.innerHTML;
            name.title = firstName.title;
        }
        var debetType = document.getElementById('row_' + id + '_DebetType');
        if (debetType) debetType.value = 'F';
        var box2 = entry.querySelector('.targetLineCheckbox');
        if (box2) box2.checked = true;
        addRemoveButton(entry, wrapper);

        // The row added before keeps its amount; this one follows the rest
        all.forEach(function (row) { delete row.dataset.splitAuto; });
        entry.dataset.splitAuto = '1';
        refresh();

        if (typeof window.initAccountAutocomplete === 'function') window.initAccountAutocomplete();
        var debet = document.getElementById('row_' + id + '_Debet');
        if (debet) debet.focus();
    }

    /**
     * Called before "Gem og næste" saves. A balance other than 0,00 warns once: the cursor goes to the last row's amount
     * and the rest is shown. Saving again with the same balance goes ahead (warn, do not block).
     * @returns {boolean} true when saving may go ahead.
     */
    function beforeSave() {
        if (!balanceShown()) return true;
        var rest = balance();
        if (Math.abs(rest) < 0.005) {
            clearWarning();
            return true;
        }
        if (warnedFor === format(rest)) return true;
        warnedFor = format(rest);
        var box = document.getElementById('poolSplitBalance');
        var warning = box && box.querySelector('.pool-split-warning');
        var c = cfg();
        if (warning) warning.textContent = (c ? c.texts.notBalanced : '') + ' ' + format(rest);
        var all = rows();
        var field = all.length ? amountField(all[all.length - 1]) : null;
        if (field) {
            field.focus();
            field.select();
        }
        return false;
    }

    /** Whether the bilag is split, so every row gets the document. */
    function active() {
        return splitRows().length > 0;
    }

    window.poolSplit = { addRow: addRow, beforeSave: beforeSave, active: active, refresh: refresh };

    // Any amount typed: the last added row follows, unless it is the one being typed in
    document.addEventListener('input', function (e) {
        var target = e.target;
        if (!(target instanceof Element) || !/_Amount$/.test(target.id || '') || !target.closest('#bilagRowsContainer')) return;
        var entry = target.closest('.kassebilag-entry');
        if (entry && entry.dataset.splitAuto === '1') delete entry.dataset.splitAuto;
        refresh();
    });

    // Another document opened in place (SD-719): its rows and total start fresh
    document.addEventListener('poolswitch', function () {
        snapshot = null;
        warnedFor = null;
        refresh();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refresh);
    } else {
        refresh();
    }
})();
