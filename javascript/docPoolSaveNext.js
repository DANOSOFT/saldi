// --- javascript/docPoolSaveNext.js --- ver 5.0.0 --- 2026-10-05 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-716 Created: "Gem og næste" in the document pool.
//                One action saves every row of the bilag (one after another, so new rows don't race), attaches the shown document and opens the next document with its data transferred.
//                Enter in an entry field does the same, except when the lookup panel has a line highlighted (Enter picks it) or SD-715's invoice warning takes the first Enter.
//                Dato, Debet, Kredit and Beløb are mandatory: a missing one gets focus and is marked "Obligatorisk", and nothing is saved.
//                "Spring over" opens the next document without saving; the arrow keys outside a field open the previous / next document.
//                The next document is the one after the current one in the list as shown (sort and search kept).
// 20261004 CL/SZ SD-716 A new row that was saved keeps its line id (data-saved-line-id) until the document is left.
//                When the attach then fails (e.g. a dropped connection) and Enter is pressed again, that line is updated instead of saved a second time.
//                Needs window.saldiPoolSaveNext (docPool.php) and docPool.php's _saveRowFetch(), chooseMultipleBilag() and transferDataFromSelectedFile().
// 20261005 CL/SZ SD-716 Beløb must be a number other than zero ("abc" and "0,00" saved a 0,00 line before).
// 20261005 CL/SZ SD-716 A document clicked in the list opens on a new line with its data filled in when nothing was typed, as the arrow keys do.
// 20261006 CL/SZ SD-716 A new document puts the cursor in Debet without opening the lookup panel over the list (window.focusAccountQuietly()),
//                or on "Brug forslag" when Debet has a suggestion; window.poolFocusNewLine() does the same after "Overfør data".
(function () {
    'use strict';

    var busy = false;
    // Whether the user typed in the bilag's fields since the document opened; without that, the line holds only the document's transferred data
    var lineTyped = false;
    // The typed values of an unsaved line, which openPoolFile() carries in the URL
    var LINE_PARAMS = ['sourceId', 'bilag', 'dato', 'beskrivelse', 'debet', 'kredit', 'fakturanr', 'sum', 'afd', 'projekt', 'valuta', 'momsfri', 'forfald'];

    function cfg() {
        return window.saldiPoolSaveNext || null;
    }

    /** The document shown in the viewer: the last non-empty poolFile in the URL. */
    function currentPoolFile() {
        var all = new URLSearchParams(window.location.search).getAll('poolFile');
        for (var i = all.length - 1; i >= 0; i--) {
            if (all[i] && all[i].trim() !== '') return all[i];
        }
        return '';
    }

    /** File names in the list, in the order shown (table rows or cards, whichever view is visible). */
    function listedFiles() {
        var seen = {};
        var files = [];
        document.querySelectorAll('#leftPanel [data-pool-file]').forEach(function (el) {
            var name = el.getAttribute('data-pool-file');
            if (!name || seen[name] || !el.getClientRects().length) return;
            seen[name] = true;
            files.push(name);
        });
        return files;
    }

    /** The document after (step 1) or before (step -1) the current one; null when there is none. */
    function neighbour(step) {
        var files = listedFiles();
        var current = currentPoolFile();
        var at = files.indexOf(current);
        if (at < 0) return step > 0 ? (files[0] || null) : null;
        return files[at + step] || null;
    }

    /** Pool URL for a document. fresh: a new, empty line for it, with the journal's next voucher number and its data transferred. */
    function documentUrl(file, fresh) {
        var url = new URL(window.location.href);
        url.searchParams.delete('poolFile');
        url.searchParams.delete('poolFile[]');
        url.searchParams.delete('poolDone');
        url.searchParams.set('poolFile', file);
        if (fresh) {
            LINE_PARAMS.forEach(function (key) {
                url.searchParams.delete(key);
            });
            url.searchParams.set('sourceId', '0');
            url.searchParams.set('transfer', '1');
        }
        return url.href;
    }

    function leaveFor(href) {
        if (typeof window.savePoolListView === 'function') window.savePoolListView();
        window.location.href = href;
    }

    /** After the last document: the pool without a document, showing "Ingen flere bilag i puljen". */
    function doneUrl() {
        var url = new URL(window.location.href);
        ['poolFile', 'poolFile[]', 'transfer'].concat(LINE_PARAMS).forEach(function (key) { url.searchParams.delete(key); });
        url.searchParams.set('sourceId', '0');
        url.searchParams.set('poolDone', '1');
        return url.href;
    }

    function editableEntries() {
        return Array.prototype.filter.call(document.querySelectorAll('.kassebilag-entry'), function (entry) {
            var dato = entry.querySelector('[id$="_Dato"]');
            return dato && !dato.readOnly;
        });
    }

    function rowIdOf(entry) {
        return entry.id.replace('bilagEntry_', '');
    }

    function clearMandatory(field) {
        field.classList.remove('pool-mandatory');
        var mark = field.parentNode && field.parentNode.querySelector('.pool-mandatory-text');
        if (mark) mark.remove();
    }

    function markMandatory(field, text) {
        if (field.classList.contains('pool-mandatory')) return;
        field.classList.add('pool-mandatory');
        var mark = document.createElement('span');
        mark.className = 'pool-mandatory-text';
        mark.textContent = text;
        field.insertAdjacentElement('afterend', mark);
        field.addEventListener('input', function () { clearMandatory(field); }, { once: true });
    }

    /** True for an amount other than zero, as "1.234,56", "5,03", "-50" or "12.50"; letters and "0,00" are not. */
    function isAmount(value) {
        var number = value.replace(/\s/g, '').replace(/\.(?=\d{3}(?!\d))/g, '').replace(',', '.');
        return /^-?\d+(\.\d+)?$/.test(number) && parseFloat(number) !== 0;
    }

    /** Marks every missing mandatory field and returns the first one, or null when all are filled. */
    function firstMissing(entries) {
        var text = cfg().texts.mandatory;
        var first = null;
        entries.forEach(function (entry) {
            var prefix = 'row_' + rowIdOf(entry) + '_';
            [['Dato', null], ['Debet', 'Debet'], ['Kredit', 'Kredit'], ['Amount', null]].forEach(function (item) {
                var field = document.getElementById(prefix + item[0]);
                if (!field) return;
                var value = item[1] && typeof window.poolAccountValue === 'function'
                    ? window.poolAccountValue(prefix, item[1]).replace(/^[DKF]/i, '')
                    : field.value.trim();
                if (value === '' || value === '0' || (item[0] === 'Amount' && !isAmount(value))) {
                    markMandatory(field, text);
                    if (!first) first = field;
                } else {
                    clearMandatory(field);
                }
            });
        });
        return first;
    }

    function setBusy(on) {
        busy = on;
        var button = document.getElementById('saveNextBtn');
        if (!button) return;
        if (on) {
            button.dataset.label = button.innerHTML;
            button.textContent = cfg().texts.saving;
            button.style.opacity = '0.7';
            button.style.pointerEvents = 'none';
        } else if (button.dataset.label) {
            button.innerHTML = button.dataset.label;
            button.style.opacity = '';
            button.style.pointerEvents = '';
        }
    }

    /** Saves the rows one after another; resolves with the new row's id (or null) when all were saved. */
    function saveRowsInOrder(entries) {
        var c = cfg();
        var newId = null;
        return entries.reduce(function (chain, entry) {
            return chain.then(function () {
                var rowId = rowIdOf(entry);
                return window._saveRowFetch(rowId, c.kladdeId, c.bilag).then(function (data) {
                    // Saved once: a retry after a failed attach updates this line (docPool.php's _buildFormData()) instead of adding another
                    if (data && data.success && /^new/.test(rowId) && data.sourceId) entry.dataset.savedLineId = data.sourceId;
                    if (!data || !data.success) throw new Error((data && data.message) || 'save failed');
                    if (rowId === 'new' && data.sourceId) newId = data.sourceId;
                });
            });
        }, Promise.resolve()).then(function () { return newId; });
    }

    function saveAndNext() {
        var c = cfg();
        if (busy || !c || c.readOnly) return;
        var entries = editableEntries();
        if (!entries.length) return;
        var missing = firstMissing(entries);
        if (missing) {
            missing.focus();
            if (typeof missing.select === 'function') missing.select();
            return;
        }
        // Chosen before the save: the current document leaves the list when it is attached
        var file = currentPoolFile();
        var next = neighbour(1);
        setBusy(true);
        saveRowsInOrder(entries).then(function (newId) {
            if (newId) {
                // The new row's "attach here" box carried 0 until the row existed
                var box = document.querySelector('#bilagEntry_new .targetLineCheckbox');
                if (box) box.value = newId;
            }
            if (!file || typeof window.chooseMultipleBilag !== 'function') {
                leaveFor(next ? documentUrl(next, true) : doneUrl());
                return;
            }
            window.chooseMultipleBilag([file], function (error) {
                if (error) { setBusy(false); return; }
                leaveFor(next ? documentUrl(next, true) : doneUrl());
            });
        }).catch(function (error) {
            console.error(error);
            alert(error.message);
            setBusy(false);
        });
    }

    function skipDocument() {
        if (busy) return;
        var next = neighbour(1);
        leaveFor(next ? documentUrl(next, true) : doneUrl());
    }

    window.poolSaveAndNext = saveAndNext;
    window.poolSkipDocument = skipDocument;
    window.poolFocusNewLine = focusNewLine;

    /**
     * Pool URL for a document clicked in the list: a new line with its own data when nothing was typed in the new line, as
     * the arrow keys and "Gem og næste" open it (spec: "read values pre-filled"); null keeps the typed line (docPool.php's openPoolFile()).
     */
    window.poolFreshDocumentUrl = function (href) {
        var c = cfg();
        if (!c || c.readOnly || lineTyped || !document.getElementById('bilagEntry_new')) return null;
        if (editableEntries().length !== 1) return null;
        var file = new URL(href, window.location.href).searchParams.get('poolFile');
        return file ? documentUrl(file, true) : null;
    };

    /** The lookup panel is open with a line highlighted: Enter belongs to it (it picks that line). */
    function panelHasSelection() {
        return Array.prototype.some.call(document.querySelectorAll('.account-autocomplete-dropdown'), function (el) {
            return el.style.display !== 'none' && el.getClientRects().length > 0 && !!el.querySelector('.account-autocomplete-item.selected');
        });
    }

    function inField(el) {
        return el instanceof Element && (el.isContentEditable || /^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName));
    }

    function modalOpen() {
        return !!document.getElementById('transferConfirmPopup');
    }

    // Bubble phase and after invoiceReuse.js: the lookup panel and SD-715's first-Enter rule mark the keys they use
    document.addEventListener('keydown', function (e) {
        if (e.defaultPrevented || e.isComposing || e.altKey || e.metaKey) return;
        var c = cfg();
        if (!c || c.readOnly) return;
        var target = e.target;

        if (e.key === 'Enter' && !e.ctrlKey && !e.shiftKey) {
            if (!(target instanceof Element) || !target.matches('input, select') || !target.closest('.kassebilag-entry')) return;
            if (/^(button|submit|reset)$/i.test(target.type) || panelHasSelection()) return;
            e.preventDefault();
            // An open panel with nothing highlighted doesn't use Enter; as in the journal, Enter saves
            if (typeof window.closeAccountAutocomplete === 'function') window.closeAccountAutocomplete();
            saveAndNext();
            return;
        }

        if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && !e.ctrlKey && !e.shiftKey) {
            if (inField(target) || modalOpen() || busy) return;
            var other = neighbour(e.key === 'ArrowRight' ? 1 : -1);
            if (!other) return;
            e.preventDefault();
            var href = documentUrl(other, false);
            if (typeof window.openPoolFile === 'function') window.openPoolFile(href); else leaveFor(href);
        }
    });

    function showDone() {
        var c = cfg();
        var panel = document.getElementById('leftPanel');
        if (!c || !panel) return;
        var box = document.createElement('div');
        box.className = 'pool-done-note';
        var text = document.createElement('span');
        text.textContent = c.texts.noMore;
        var link = document.createElement('a');
        link.href = c.journalUrl;
        link.textContent = c.texts.toJournal;
        box.appendChild(text);
        box.appendChild(link);
        panel.insertBefore(box, panel.firstChild);
    }

    /** First empty mandatory field of the new line, for the keyboard to start in; Debet as the usual case. */
    function focusNewLine() {
        var prefix = 'row_new_';
        var order = ['Debet', 'Kredit', 'Amount', 'Dato'];
        for (var i = 0; i < order.length; i++) {
            var field = document.getElementById(prefix + order[i]);
            if (field && !field.readOnly && field.value.trim() === '') {
                // A suggestion for the empty Debet takes the cursor, as when it arrives (poolCapture.js): Enter uses it
                var use = order[i] === 'Debet' && field.closest('.kassebilag-entry') && field.closest('.kassebilag-entry').querySelector('.pool-suggest-use');
                if (use) {
                    use.focus();
                    return;
                }
                // Without the lookup panel: opened on its own it covered the list the user clicks the next document in
                if (typeof window.focusAccountQuietly === 'function' && field.classList.contains('pool-account-no')) window.focusAccountQuietly(field);
                else field.focus();
                return;
            }
        }
    }

    function init() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('poolDone') === '1') showDone();
        if (params.get('transfer') === '1') {
            // A reload must not transfer again over what the user changed
            var url = new URL(window.location.href);
            url.searchParams.delete('transfer');
            window.history.replaceState(null, '', url.href);
            whenListLoaded(function () {
                if (document.getElementById('bilagEntry_new') && typeof window.transferDataFromSelectedFile === 'function') {
                    window.transferDataFromSelectedFile({ auto: true });
                }
                focusNewLine();
            });
        }
    }

    /** The list's data (docData) arrives by fetch after the page has loaded; wait up to 5 s for it. */
    function whenListLoaded(callback) {
        var tries = 0;
        (function wait() {
            if ((window.docData && window.docData.length) || ++tries > 50) {
                callback();
                return;
            }
            setTimeout(wait, 100);
        })();
    }

    // Only the user's own typing counts (isTrusted): the transfer and the lookup panel fill the fields by script
    document.addEventListener('input', function (e) {
        if (e.isTrusted && e.target instanceof Element && e.target.closest('.kassebilag-entry')) lineTyped = true;
    }, true);

    // After docPool.php's own DOMContentLoaded handlers (docData is filled by then)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
    } else {
        setTimeout(init, 0);
    }
})();
