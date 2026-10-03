// --- javascript/fieldNavigation.js --- ver 5.0.0 --- 2026-10-03 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-716 Created: Ctrl + arrow keys between fields, shared by the journal and the document pool.
//                Replaces jquery.formnavigation.js in the journal, which stopped at the first cell without an <input> (the VAT select after the account field).
//                That plugin also listened on keyup, after the browser had already moved the cursor inside the field.
//                Ctrl+→ / Ctrl+← move to the next / previous editable field of the line, Ctrl+↓ / Ctrl+↑ to the same field in the line below / above.
//                The key is handled on keydown with the browser default suppressed, the field's content is selected, and lines added later are covered.
//
// Where it works: inside a table with class "formnavi" (the journal; a line is a <tr>), or inside an element with
// data-field-nav (the pool; a line is an element with data-field-nav-row).
// A field's column is its data-nav-col, else its name without the line number (debe12 -> debe), else its id
// without the row prefix (row_141_Amount -> Amount).
(function () {
    'use strict';

    var ARROWS = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -1, ArrowDown: 1 };
    var SKIP_TYPES = /^(hidden|button|submit|reset|image|file)$/i;

    function container(el) {
        return el.closest ? el.closest('table.formnavi, [data-field-nav]') : null;
    }

    function rowOf(el, box) {
        var row = box.hasAttribute('data-field-nav') ? el.closest('[data-field-nav-row]') : el.closest('tr');
        return row && box.contains(row) ? row : null;
    }

    function rowsOf(box) {
        return Array.prototype.slice.call(box.hasAttribute('data-field-nav')
            ? box.querySelectorAll('[data-field-nav-row]')
            : box.querySelectorAll('tr'));
    }

    function editable(el) {
        if (el.disabled || el.readOnly) return false;
        if (el.tagName === 'INPUT' && SKIP_TYPES.test(el.type)) return false;
        return el.getClientRects().length > 0; // not hidden, not in a collapsed line
    }

    function fieldsOf(row) {
        return Array.prototype.filter.call(row.querySelectorAll('input, select, textarea'), editable);
    }

    function column(el) {
        if (el.dataset && el.dataset.navCol) return el.dataset.navCol;
        if (el.name) return el.name.replace(/_?\d+$/, '');
        return (el.id || '').replace(/^row_[^_]+_/, '');
    }

    function sideways(field, row, step) {
        var fields = fieldsOf(row);
        var at = fields.indexOf(field);
        return at < 0 ? null : (fields[at + step] || null);
    }

    function vertical(field, row, box, step) {
        var rows = rowsOf(box);
        var col = column(field);
        var index = fieldsOf(row).indexOf(field);
        for (var i = rows.indexOf(row) + step; i >= 0 && i < rows.length; i += step) {
            var fields = fieldsOf(rows[i]);
            if (!fields.length) continue; // header, posted or collapsed line
            for (var k = 0; k < fields.length; k++) {
                if (column(fields[k]) === col) return fields[k];
            }
            return fields[Math.min(index, fields.length - 1)];
        }
        return null;
    }

    function moveTo(target) {
        // After the other keydown listeners have seen the key: the lookup panel and the date picker
        // use it to keep themselves closed when focus arrives by keyboard
        setTimeout(function () {
            target.focus();
            if (typeof target.select === 'function' && target.tagName !== 'SELECT' && !/^(checkbox|radio)$/i.test(target.type)) {
                target.select();
            }
        }, 0);
    }

    window.addEventListener('keydown', function (e) {
        if (!e.ctrlKey || e.altKey || e.shiftKey || e.metaKey || !(e.key in ARROWS)) return;
        var field = e.target;
        if (!field || !/^(INPUT|SELECT|TEXTAREA)$/.test(field.tagName)) return;
        var box = container(field);
        var row = box ? rowOf(field, box) : null;
        if (!row) return;

        e.preventDefault(); // no word jump inside the field, no option change in a select
        if (typeof window.closeAccountAutocomplete === 'function') window.closeAccountAutocomplete();
        var step = ARROWS[e.key];
        var target = (e.key === 'ArrowLeft' || e.key === 'ArrowRight')
            ? sideways(field, row, step)
            : vertical(field, row, box, step);
        if (target) moveTo(target);
    }, true);
})();
