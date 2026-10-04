// --- javascript/docPoolSaveNext.js --- ver 5.0.0 --- 2026-10-04 ---
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
// 20261003 CL/SZ SD-717 "Gem og næste" and "Spring over" never open an archived document; the arrow keys still browse the archive.
// 20261003 CL/SZ SD-719 The next document opens in place (docPoolSwitch.js); the attached document is taken out of the list.
//                When the open document is the last loaded row, the next page of the list is fetched first.
//                The right arrow on the last loaded row fetches the next page too, so it doesn't stop at row 50.
//                After a switch ("poolswitch") the transfer, the focus and the "no more documents" note run again.
// 20261003 CL/SZ SD-720 Before saving, "Fordeling"'s balance check (docPoolSplit.js) may take the first Enter.
//                Every new row (new, new2 ...) gets its line id, and a split bilag attaches the document to every row.
// 20261004 CL/SZ SD-726 With "Gem og gå til næste/forrige" (window.saldiShortcuts), Ctrl+↓ in an entry field does what Enter does.
//                Ctrl+↑ saves the same way and opens the previous document in the list; without one, the next, as Enter.
//                An unseen invoice-number warning takes the first Ctrl+↓ / Ctrl+↑, as it takes the first Enter.
(function () {
    'use strict';

    var busy = false;
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

    /** File names of archived documents in the loaded list (docData), as a lookup. */
    function archivedFiles() {
        var archived = {};
        (window.docData || []).forEach(function (row) {
            if (row && row.archived) archived[row.filename] = true;
        });
        return archived;
    }

    /** File names in the list, in the order shown (table rows or cards, whichever view is visible). withArchived: keep archived ones. */
    function listedFiles(withArchived) {
        var seen = {};
        var files = [];
        var archived = withArchived ? {} : archivedFiles();
        document.querySelectorAll('#leftPanel [data-pool-file]').forEach(function (el) {
            var name = el.getAttribute('data-pool-file');
            if (!name || seen[name] || archived[name] || !el.getClientRects().length) return;
            seen[name] = true;
            files.push(name);
        });
        return files;
    }

    /** The document after (step 1) or before (step -1) the current one; null when there is none. */
    function neighbour(step, withArchived) {
        var files = listedFiles(withArchived);
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

    /** Opens href in place when the pool can (SD-719), else loads it. remove: files that left the list. */
    function leaveFor(href, remove) {
        if (typeof window.savePoolListView === 'function') window.savePoolListView();
        if (typeof window.poolSwitch === 'function') {
            window.poolSwitch(href, { remove: remove || [] });
            return;
        }
        window.location.href = href;
    }

    /** Whether the open document is the last loaded row while the list has more pages. withArchived: as in listedFiles(). */
    function atLoadedEnd(withArchived) {
        var files = listedFiles(withArchived);
        var at = files.indexOf(currentPoolFile());
        return at >= 0 && at === files.length - 1 && typeof window.poolHasMore === 'function' && window.poolHasMore();
    }

    /** The next document; when the open one is the last loaded row, the list's next page is fetched first. */
    function nextDocument(withArchived) {
        if (atLoadedEnd(withArchived)) {
            return window.poolLoadMore().then(function () { return neighbour(1, withArchived); });
        }
        return Promise.resolve(neighbour(1, withArchived));
    }

    /** The document before the current one, among those not yet processed; the next one when there is none before it. */
    function previousDocument() {
        var before = neighbour(-1);
        return before ? Promise.resolve(before) : nextDocument();
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
                if (value === '' || value === '0') {
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

    /**
     * Saves the rows one after another in the order shown, so new lines get their pos in that order (SD-720).
     * Resolves with the line id of each new row (new, new2 ...) by row id.
     */
    function saveRowsInOrder(entries) {
        var c = cfg();
        var newIds = {};
        return entries.reduce(function (chain, entry) {
            return chain.then(function () {
                var rowId = rowIdOf(entry);
                return window._saveRowFetch(rowId, c.kladdeId, c.bilag).then(function (data) {
                    // Saved once: a retry after a failed attach updates this line (docPool.php's _buildFormData()) instead of adding another
                    if (data && data.success && /^new/.test(rowId) && data.sourceId) entry.dataset.savedLineId = data.sourceId;
                    if (!data || !data.success) throw new Error((data && data.message) || 'save failed');
                    if (/^new/.test(rowId) && data.sourceId) newIds[rowId] = data.sourceId;
                });
            });
        }, Promise.resolve()).then(function () { return newIds; });
    }

    /** The "attach here" boxes after saving: new rows get their line id; a split bilag ticks every row (SD-720). */
    function markTargets(entries, newIds) {
        var split = window.poolSplit && typeof window.poolSplit.active === 'function' && window.poolSplit.active();
        entries.forEach(function (entry) {
            var box = entry.querySelector('.targetLineCheckbox');
            if (!box) return;
            var id = newIds[rowIdOf(entry)];
            if (id) box.value = id;
            if (split && /^\d+$/.test(box.value) && box.value !== '0') box.checked = true;
        });
    }

    /** Saves the bilag, attaches the document and opens the next one (step 1, Enter) or the previous one (step -1, Ctrl+↑). */
    function saveAndNext(step) {
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
        // SD-720: a split that doesn't add up warns on the first Enter; the second saves
        if (window.poolSplit && typeof window.poolSplit.beforeSave === 'function' && !window.poolSplit.beforeSave()) return;
        // Chosen before the save: the current document leaves the list when it is attached
        var file = currentPoolFile();
        var next = null;
        setBusy(true);
        (step === -1 ? previousDocument() : nextDocument()).then(function (found) {
            next = found;
            return saveRowsInOrder(entries);
        }).then(function (newIds) {
            // The new rows' "attach here" boxes carried 0 until the rows existed
            markTargets(entries, newIds);
            if (!file || typeof window.chooseMultipleBilag !== 'function') {
                leaveFor(next ? documentUrl(next, true) : doneUrl());
                return;
            }
            window.chooseMultipleBilag([file], function (error) {
                if (error) { setBusy(false); return; }
                leaveFor(next ? documentUrl(next, true) : doneUrl(), [file]);
            });
        }).catch(function (error) {
            console.error(error);
            alert(error.message);
            setBusy(false);
        });
    }

    function skipDocument() {
        if (busy) return;
        busy = true;
        nextDocument().then(function (next) {
            busy = false;
            leaveFor(next ? documentUrl(next, true) : doneUrl());
        });
    }

    window.poolSaveAndNext = saveAndNext;

    // SD-726: "Gem og gå til næste/forrige" - fieldNavigation.js hands Ctrl+↓ / Ctrl+↑ from an entry field here
    window.fieldNavigationSave = function (field, target, step) {
        var c = cfg();
        if (!c || c.readOnly || !field.closest('.kassebilag-entry')) return false;
        // An unseen invoice-number warning takes the first key, as it takes the first Enter (invoiceReuse.js)
        if (typeof window.invoiceReuseStopsSave === 'function' && window.invoiceReuseStopsSave(field)) return true;
        saveAndNext(step > 0 ? 1 : -1);
        return true;
    };
    window.poolSkipDocument = skipDocument;

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
            var open = function (other) {
                if (!other) return;
                var href = documentUrl(other, false);
                if (typeof window.openPoolFile === 'function') window.openPoolFile(href); else leaveFor(href);
            };
            if (e.key === 'ArrowRight' && atLoadedEnd(true)) {
                e.preventDefault();
                nextDocument(true).then(open);
                return;
            }
            var other = neighbour(e.key === 'ArrowRight' ? 1 : -1, true);
            if (!other) return;
            e.preventDefault();
            open(other);
        }
    });

    function showDone() {
        var c = cfg();
        var panel = document.getElementById('leftPanel');
        if (!c || !panel || panel.querySelector('.pool-done-note')) return;
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
                field.focus();
                return;
            }
        }
    }

    function init() {
        var params = new URLSearchParams(window.location.search);
        busy = false;
        var note = document.querySelector('#leftPanel .pool-done-note');
        if (note && params.get('poolDone') !== '1') note.remove();
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

    // A document opened in place (SD-719) is a new start for the transfer, the focus and the note
    document.addEventListener('poolswitch', init);

    // After docPool.php's own DOMContentLoaded handlers (docData is filled by then)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
    } else {
        setTimeout(init, 0);
    }
})();
