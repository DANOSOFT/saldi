// --- javascript/docPoolArchive.js --- ver 5.0.0 --- 2026-10-04 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-717 Created: the archive of the document pool.
//                "Arkivér" per document and for the selection, Del when the focus is not in a field, "Vis arkiverede" and "Gendan".
//                The list shows either the normal documents or the archived ones; search works on the list shown.
//                Archiving the document in the viewer opens the next one, as "Spring over" does.
//                Needs window.saldiPoolArchive (docPool.php) and docPoolSaveNext.js's poolSkipDocument().
// 20261003 CL/SZ SD-719 Archived and restored documents are taken out of the loaded list instead of reloading the page.
// 20261004 CL/SZ SD-727 Under an archived document, the date it will be deleted follows the archive date: "Slettes 04-10-2027" (deleteNote()).
(function () {
    'use strict';

    var busy = false;
    var ICON_ARCHIVE = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>';
    var ICON_RESTORE = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>';

    function cfg() {
        return window.saldiPoolArchive || null;
    }

    function storageKey() {
        var c = cfg();
        return 'docPoolArchived_' + (c ? c.db : '');
    }

    /** Whether the list shows the archive ("Vis arkiverede" on). Per tab, like the pool's other list settings. */
    function archiveView() {
        try {
            return sessionStorage.getItem(storageKey()) === '1';
        } catch (e) {
            return false;
        }
    }

    function escapeAttr(text) {
        return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /** "Arkivér" button for a row of the normal list, "Gendan" for a row of the archive. size: 'row' or 'card'. */
    function button(filename, size) {
        var c = cfg();
        if (!c) return '';
        var restore = archiveView();
        var padding = size === 'card' ? '6px 10px' : '4px 8px';
        var fontSize = size === 'card' ? '12px' : '11px';
        return '<button type="button" class="pool-archive-btn" data-pool-archive="' + escapeAttr(filename) + '" data-pool-archive-action="' + (restore ? 'restore' : 'archive') + '"' +
            ' title="' + escapeAttr(restore ? c.texts.restore : c.texts.archive) + '"' +
            ' style="padding: ' + padding + '; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: ' + fontSize + '; font-weight: bold;">' +
            (restore ? ICON_RESTORE : ICON_ARCHIVE) + '</button>';
    }

    /** Y-m-d... as d-m-Y. */
    function danishDate(value) {
        var date = String(value).substring(0, 10).split('-');
        return date.length === 3 ? date[2] + '-' + date[1] + '-' + date[0] : String(value);
    }

    /** "Arkiveret <date>" under a document in the archive. */
    function archivedNote(row) {
        var c = cfg();
        if (!c || !row || !row.archived) return '';
        return c.texts.archived + ' ' + danishDate(row.archived);
    }

    /** "Slettes <date>" under a document in the archive: the day the periodic sync deletes it (SD-727). */
    function deleteNote(row) {
        var c = cfg();
        if (!c || !row || !row.archived || !row.deletes) return '';
        return c.texts.deletes + ' ' + danishDate(row.deletes);
    }

    function post(action, file) {
        var c = cfg();
        var form = new FormData();
        form.append('action', action);
        form.append('poolFile', file);
        return fetch(c.handlerUrl, { method: 'POST', body: form, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.success) throw new Error((data && data.error) || 'error');
                return data;
            });
    }

    function forget(file) {
        try {
            sessionStorage.removeItem('docPool_checked_' + file);
        } catch (e) { /* the list works without it */ }
    }

    function reloadList() {
        if (typeof window.savePoolListView === 'function') window.savePoolListView();
        window.location.reload();
    }

    function currentPoolFile() {
        var all = new URLSearchParams(window.location.search).getAll('poolFile');
        for (var i = all.length - 1; i >= 0; i--) {
            if (all[i] && all[i].trim() !== '') return all[i];
        }
        return '';
    }

    /**
     * Archives or restores the files, one request each, then shows the result.
     * When the document in the viewer was archived, the next document opens; otherwise the list reloads.
     */
    function run(action, files) {
        if (busy || !files.length) return;
        busy = true;
        var current = currentPoolFile();
        var failed = [];
        var done = [];
        var chain = Promise.resolve();
        files.forEach(function (file) {
            chain = chain.then(function () {
                return post(action, file).then(function () { forget(file); done.push(file); }, function (error) {
                    failed.push(file + ': ' + error.message);
                });
            });
        });
        chain.then(function () {
            busy = false;
            if (failed.length) alert(failed.join('\n'));
            var leftCurrent = action === 'archive' && done.indexOf(current) >= 0;
            if (leftCurrent && typeof window.poolSkipDocument === 'function') {
                // The next document is picked while the archived one is still in the list, then it is taken out
                window.poolSkipDocument();
                if (typeof window.poolRemoveFiles === 'function') {
                    document.addEventListener('poolswitch', function once() {
                        document.removeEventListener('poolswitch', once);
                        window.poolRemoveFiles(done);
                    });
                }
                return;
            }
            if (typeof window.poolRemoveFiles === 'function') {
                window.poolRemoveFiles(done);
                if (typeof window.updateBulkButton === 'function') window.updateBulkButton();
                return;
            }
            reloadList();
        });
    }

    function selectedFiles() {
        return Array.prototype.map.call(document.querySelectorAll('.file-checkbox:checked'), function (box) { return box.value; });
    }

    /** "Arkivér valgte" / "Gendan valgte". */
    function runSelected() {
        var files = selectedFiles();
        if (!files.length) {
            alert(cfg().texts.noneSelected);
            return;
        }
        run(archiveView() ? 'restore' : 'archive', files);
    }

    function toggleView(on) {
        try {
            if (on) sessionStorage.setItem(storageKey(), '1'); else sessionStorage.removeItem(storageKey());
        } catch (e) { /* stays in the normal list */ }
        reloadList();
    }

    window.poolArchive = {
        view: archiveView,
        button: button,
        archivedNote: archivedNote,
        deleteNote: deleteNote,
        runSelected: runSelected,
        toggleView: toggleView
    };

    /** The toolbar follows the list shown: the filter ticked, "Gendan valgte" instead of "Arkivér valgte", no "Opdatér alle". */
    function initToolbar() {
        if (!cfg() || !archiveView()) return;
        var filter = document.getElementById('poolShowArchived');
        if (filter) filter.checked = true;
        var selected = document.getElementById('archiveSelectedBtn');
        var label = selected && selected.querySelector('span');
        if (label && selected.getAttribute('data-restore-label')) label.textContent = selected.getAttribute('data-restore-label');
        var extractAll = document.getElementById('extractAllBtn');
        if (extractAll) extractAll.style.display = 'none';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initToolbar);
    } else {
        initToolbar();
    }

    // The row's buttons are rendered as HTML strings by docPool.php, so their clicks are handled here
    document.addEventListener('click', function (e) {
        var btn = e.target instanceof Element && e.target.closest('.pool-archive-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        run(btn.getAttribute('data-pool-archive-action'), [btn.getAttribute('data-pool-archive')]);
    }, true);

    /** A field Del edits text in. A checkbox is not one: Del right after ticking documents archives them. */
    function inTextField(el) {
        if (!(el instanceof Element)) return false;
        if (el.isContentEditable || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') return true;
        return el.tagName === 'INPUT' && !/^(checkbox|radio|button|submit|reset|image|file|hidden)$/i.test(el.type);
    }

    // Del outside a field archives the selection, or the document in the viewer when nothing is selected
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Delete' || e.defaultPrevented || e.ctrlKey || e.altKey || e.metaKey || e.shiftKey) return;
        var c = cfg();
        if (!c || busy || archiveView() || inTextField(e.target) || document.getElementById('transferConfirmPopup')) return;
        var files = selectedFiles();
        if (!files.length && currentPoolFile() !== '') files = [currentPoolFile()];
        if (!files.length) return;
        e.preventDefault();
        run('archive', files);
    });
})();
