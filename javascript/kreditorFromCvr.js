// --- javascript/kreditorFromCvr.js --- ver 5.0.0 --- 2026-10-03 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-721 Created: kreditor from the CVR register (spec Task 7), in the document pool and the journal.
//                Pool: when an opened document's supplier is not a kreditor, kreditor/kreditorFromCvr.php resolves it.
//                A kreditor with the CVR number fills Kredit; with "Opret kreditor automatisk" on, the kreditor is created and Kredit filled, with "Fortryd";
//                otherwise "Ukendt leverandør: Firma A/S (CVR …) — Opret kreditor" creates it with one click, and without usable CVR data "Ukendt leverandør — Opret kreditor" opens the dialog.
//                The dialog sits beside the document; its CVR field fetches on Tab with the kreditor card's lookup (cvrapiopslag.js, loaded when the dialog first opens).
//                window.kreditorFromCvr.openDialog() is also what the lookup panel's "Opret kreditor" calls when a kreditor search finds nothing.
//                Needs window.saldiKreditorCvr (url, proxy, cvrScript, texts), set by docPool.php and kassekladde.php.
// 20261004 CL/SZ SD-721 A CVR number that only a closed kreditor has: "Kreditor 1005 Firma A/S er lukket — Genåbn · Opret ny", in the pool and in the dialog.
//                "Genåbn" opens the kreditor again and fills Kredit; "Opret ny" opens the dialog, which then creates a second kreditor.
(function () {
    'use strict';

    var resolving = null;   // the document a resolve is running for
    var dialogOpen = false;

    function cfg() {
        return window.saldiKreditorCvr || null;
    }

    // The journal doesn't load docpool.css, so the notice and the dialog bring their own styles
    (function addStyles() {
        if (document.getElementById('kredCvrStyles')) return;
        var style = document.createElement('style');
        style.id = 'kredCvrStyles';
        style.textContent =
            '.kred-cvr-notice{margin:4px 0 6px;padding:6px 10px;border-radius:4px;font-size:12px;line-height:1.5;background:#eef4fb;border:1px solid #c9d9ee;color:#15324f;}' +
            '.kred-cvr-notice a{color:#15488f;font-weight:600;}' +
            '.kred-cvr-suggest{background:#fff8e6;border-color:#f0d48a;}' +
            '.kred-cvr-created{background:#eaf7ee;border-color:#a9dcb7;}' +
            '.kred-cvr-reason{color:#777;}' +
            '.kred-cvr-dialog{position:fixed;top:0;left:0;bottom:0;width:min(420px,100%);z-index:100000;background:rgba(0,0,0,.15);display:flex;align-items:flex-start;}' +
            '.kred-cvr-box{background:#fff;margin:60px 0 0 16px;padding:16px 18px;border-radius:8px;box-shadow:0 8px 28px rgba(0,0,0,.25);width:360px;max-width:calc(100% - 32px);font-size:12px;}' +
            '.kred-cvr-title{font-size:14px;font-weight:600;margin-bottom:10px;}' +
            '.kred-cvr-field{display:flex;align-items:center;gap:8px;margin:4px 0;min-width:0;}' +
            '.kred-cvr-field>span:first-child{width:110px;flex:none;color:#555;}' +
            '.kred-cvr-field input[type=text],.kred-cvr-field select{flex:1;width:0;min-width:0;padding:3px 5px;}' +
            '.kred-cvr-pair{display:flex;gap:8px;}.kred-cvr-pair .kred-cvr-field{flex:1;}.kred-cvr-pair .kred-cvr-field+.kred-cvr-field>span:first-child{width:auto;}' +
            '.kred-cvr-terms{flex:1;display:flex;align-items:center;gap:4px;}.kred-cvr-terms input{width:40px;flex:none !important;}' +
            '.kred-cvr-default{display:block;margin:8px 0 4px;}' +
            '.kred-cvr-error{color:#c00;min-height:16px;}' +
            '.kred-cvr-buttons{text-align:right;margin-top:6px;}.kred-cvr-buttons button{padding:5px 14px;}' +
            '.account-autocomplete-create-kreditor{margin-left:8px;padding:2px 8px;font-size:11px;cursor:pointer;}';
        (document.head || document.documentElement).appendChild(style);
    })();

    function t(key) {
        var c = cfg();
        return c && c.texts && c.texts[key] ? c.texts[key] : key;
    }

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function post(action, data) {
        var form = new FormData();
        form.append('action', action);
        Object.keys(data || {}).forEach(function (key) {
            if (data[key] !== undefined && data[key] !== null) form.append(key, data[key]);
        });
        return fetch(cfg().url, { method: 'POST', body: form, credentials: 'same-origin' })
            .then(function (response) {
                return response.json().catch(function () { return { error: 'http ' + response.status }; });
            });
    }

    /** The document shown in the viewer: the last non-empty poolFile in the URL. */
    function currentPoolFile() {
        var all = new URLSearchParams(window.location.search).getAll('poolFile');
        for (var i = all.length - 1; i >= 0; i--) {
            if (all[i] && all[i].trim() !== '') return all[i];
        }
        return '';
    }

    function poolRow(filename) {
        var rows = typeof window.poolAllRows === 'function' ? window.poolAllRows() : (window.docData || []);
        for (var i = 0; i < rows.length; i++) {
            if (rows[i] && rows[i].filename === filename) return rows[i];
        }
        return null;
    }

    // ---------------------------------------------------------------- Kredit on the new line

    function kreditField() {
        return document.getElementById('row_new_Kredit');
    }

    /** Fills Kredit of the new line with the kreditor, as choosing it in the lookup panel would. */
    function fillKredit(kreditor) {
        var field = kreditField();
        if (!field) return;
        var type = document.getElementById('row_new_KreditType');
        if (type) type.value = 'K';
        field.value = kreditor.kontonr;
        field.dispatchEvent(new Event('change', { bubbles: true }));
        var name = document.getElementById('row_new_KreditName');
        if (name) {
            name.textContent = kreditor.firmanavn;
            name.title = kreditor.firmanavn;
        }
    }

    function clearKredit(kreditor) {
        var field = kreditField();
        if (!field || field.value.trim() !== String(kreditor.kontonr)) return;
        field.value = '';
        field.dispatchEvent(new Event('change', { bubbles: true }));
        var name = document.getElementById('row_new_KreditName');
        if (name) {
            name.textContent = '';
            name.title = '';
        }
    }

    // ---------------------------------------------------------------- The notice above the rows

    function notice() {
        var box = document.getElementById('kredCvrNotice');
        if (box) return box;
        var rows = document.getElementById('bilagRowsContainer');
        if (!rows || !rows.parentNode) return null;
        box = document.createElement('div');
        box.id = 'kredCvrNotice';
        box.className = 'kred-cvr-notice';
        rows.parentNode.insertBefore(box, rows);
        return box;
    }

    function removeNotice() {
        var box = document.getElementById('kredCvrNotice');
        if (box) box.remove();
    }

    function showNotice(html, kind) {
        var box = notice();
        if (!box) return null;
        box.className = 'kred-cvr-notice' + (kind ? ' kred-cvr-' + kind : '');
        box.innerHTML = html;
        return box;
    }

    function kreditorLabel(kreditor) {
        return t('kreditor') + ' ' + esc(kreditor.kontonr) + ' ' + esc(kreditor.firmanavn);
    }

    /** "Kreditor 1234 Firma A/S oprettet automatisk fra CVR-registeret — Fortryd". */
    function showCreatedAuto(kreditor) {
        var box = showNotice(kreditorLabel(kreditor) + ' ' + esc(t('autoCreated')) +
            ' — <a href="#" class="kred-cvr-undo">' + esc(t('undo')) + '</a>', 'created');
        if (!box) return;
        box.dataset.kreditorId = kreditor.id;
        box.querySelector('.kred-cvr-undo').addEventListener('click', function (e) {
            e.preventDefault();
            post('undo', { id: kreditor.id }).then(function (answer) {
                if (answer && answer.ok) {
                    clearKredit(kreditor);
                    showNotice(esc(t('undone')), 'info');
                } else {
                    showNotice(kreditorLabel(kreditor) + ' — ' + esc(t('undoInUse')), 'info');
                }
            });
        });
    }

    function showCreated(kreditor, existed, how) {
        if (how === 'reopened') {
            showNotice(kreditorLabel(kreditor) + ' ' + esc(t('reopened')), 'created');
            return;
        }
        showNotice(existed ? esc(t('exists')) + ': ' + kreditorLabel(kreditor) : kreditorLabel(kreditor) + ' ' + esc(t('created')), 'created');
    }

    /** "Kreditor 1005 Firma A/S er lukket — Genåbn · Opret ny": only a closed kreditor has the document's CVR number. */
    function showClosed(answer, filename) {
        var kreditor = answer.kreditor;
        var box = showNotice(kreditorLabel(kreditor) + ' ' + esc(t('closed')) + ' — <a href="#" class="kred-cvr-reopen">' + esc(t('reopen')) + '</a>' +
            ' · <a href="#" class="kred-cvr-create">' + esc(t('createNew')) + '</a>', 'suggest');
        if (!box) return;
        box.querySelector('.kred-cvr-reopen').addEventListener('click', function (e) {
            e.preventDefault();
            post('reopen', { id: kreditor.id, poolFile: filename }).then(function (result) {
                if (result && result.ok) afterCreate(result.kreditor, true, 'reopened');
                else showNotice(esc(t('failed')), 'info');
            });
        });
        box.querySelector('.kred-cvr-create').addEventListener('click', function (e) {
            e.preventDefault();
            var captured = answer.captured || {};
            var company = answer.company || { firmanavn: captured.name || '', cvrnr: captured.cvr || '' };
            openDialog({ company: company, poolFile: filename, allowClosed: true, onCreated: afterCreate });
        });
    }

    /** "Ukendt leverandør: Firma A/S (CVR 12345678) — Opret kreditor". One click creates it, or asks for the group the first time. */
    function showSuggest(answer, filename) {
        var company = answer.company;
        var box = showNotice(esc(t('unknown')) + ': <b>' + esc(company.firmanavn) + '</b> (CVR ' + esc(company.cvrnr) + ') — ' +
            '<a href="#" class="kred-cvr-create">' + esc(t('create')) + '</a>', 'suggest');
        if (!box) return;
        box.querySelector('.kred-cvr-create').addEventListener('click', function (e) {
            e.preventDefault();
            if (answer.needsGroup) {
                openDialog({ company: company, poolFile: filename, onCreated: afterCreate });
                return;
            }
            post('form', {}).then(function (form) {
                var d = form.defaults;
                return post('create', {
                    poolFile: filename, mode: 'click', gruppe: d.gruppe, betalingsbet: d.betalingsbet, betalingsdage: d.betalingsdage,
                    cvrnr: company.cvrnr, firmanavn: company.firmanavn, addr1: company.addr1, addr2: company.addr2,
                    postnr: company.postnr, bynavn: company.bynavn, tlf: company.tlf, email: company.email
                });
            }).then(function (result) {
                if (result && result.status === 'closed') showClosed({ kreditor: result.kreditor, company: company }, filename);
                else if (result && result.kreditor) afterCreate(result.kreditor, result.status === 'match');
                else showNotice(esc(t('failed')), 'info');
            });
        });
    }

    function afterCreate(kreditor, existed, how) {
        fillKredit(kreditor);
        showCreated(kreditor, existed, how);
    }

    /** "Ukendt leverandør — Opret kreditor", opening the dialog with what was read from the document. */
    function showUnknown(answer, filename) {
        var reason = { lookup_failed: t('lookupFailed'), dissolved: t('dissolved'), not_found: t('notFound'), no_cvr: t('noCvr') }[answer.reason] || '';
        var box = showNotice(esc(t('unknown')) + (answer.captured && answer.captured.name ? ': <b>' + esc(answer.captured.name) + '</b>' : '') +
            ' — <a href="#" class="kred-cvr-create">' + esc(t('create')) + '</a>' +
            (reason ? ' <span class="kred-cvr-reason">(' + esc(reason) + ')</span>' : ''), 'suggest');
        if (!box) return;
        box.querySelector('.kred-cvr-create').addEventListener('click', function (e) {
            e.preventDefault();
            var captured = answer.captured || {};
            var company = answer.company || { firmanavn: captured.name || '', cvrnr: captured.cvr || '' };
            openDialog({ company: company, poolFile: filename, onCreated: afterCreate });
        });
    }

    /** Runs when a document is opened for entry: the new line is there, Kredit is empty and the vendor match found no kreditor. */
    function resolveCurrent() {
        removeNotice();
        var c = cfg();
        var filename = currentPoolFile();
        var field = kreditField();
        if (!c || !filename || !field || field.value.trim() !== '' || resolving === filename) return;
        var row = poolRow(filename);
        if (!row || !row.vendor || row.vendor.match !== 'none') return;
        resolving = filename;
        post('resolve', { poolFile: filename }).then(function (answer) {
            resolving = null;
            // Another document may have been opened meanwhile, or Kredit typed
            if (currentPoolFile() !== filename || !kreditField() || kreditField().value.trim() !== '' || !answer) return;
            row.vendor.match = answer.status === 'match' || answer.status === 'created' ? 'cvr' : row.vendor.match;
            if (answer.status === 'match') fillKredit(answer.kreditor);
            else if (answer.status === 'created') {
                fillKredit(answer.kreditor);
                showCreatedAuto(answer.kreditor);
            } else if (answer.status === 'suggest') showSuggest(answer, filename);
            else if (answer.status === 'closed') showClosed(answer, filename);
            else if (answer.status === 'unknown') showUnknown(answer, filename);
        }, function () {
            resolving = null;
        });
    }

    // ---------------------------------------------------------------- The dialog

    var cvrScript = null;

    /** Loads the kreditor card's CVR lookup once, set up for the dialog's CVR field. */
    function loadCvrLookup() {
        if (cvrScript) return cvrScript;
        var c = cfg();
        window.cvrLookupProxy = c.proxy;
        window.cvrAutoFelter = ['cvrnr'];
        cvrScript = new Promise(function (resolve) {
            if (!window.jQuery) {
                resolve(false);
                return;
            }
            var script = document.createElement('script');
            script.src = c.cvrScript;
            script.onload = function () { resolve(true); };
            script.onerror = function () { resolve(false); };
            document.head.appendChild(script);
        });
        return cvrScript;
    }

    function field(name, label, value) {
        return '<label class="kred-cvr-field"><span>' + esc(label) + '</span><input type="text" name="' + name + '" value="' + esc(value || '') + '" autocomplete="off"></label>';
    }

    /**
     * The "Opret kreditor" dialog beside the document.
     * @param {{company?: Object, poolFile?: string, allowClosed?: boolean, onCreated: function(Object, boolean, string=)}} options
     *   allowClosed: "Opret ny" was chosen for a CVR number a closed kreditor has. onCreated's third argument is 'reopened' after "Genåbn".
     */
    function openDialog(options) {
        if (dialogOpen || !cfg()) return;
        dialogOpen = true;
        var company = options.company || {};
        var overlay = document.createElement('div');
        overlay.id = 'kredCvrDialog';
        overlay.className = 'kred-cvr-dialog';
        overlay.innerHTML =
            '<form class="kred-cvr-box" autocomplete="off">' +
            '<div class="kred-cvr-title">' + esc(t('create')) + '</div>' +
            '<input type="checkbox" name="auto_lookup_cvr" checked hidden>' +
            field('cvrnr', t('cvr'), company.cvrnr) +
            field('firmanavn', t('firmanavn'), company.firmanavn) +
            field('addr1', t('adresse'), company.addr1) +
            field('addr2', '', company.addr2) +
            '<div class="kred-cvr-pair">' + field('postnr', t('postnr'), company.postnr) + field('bynavn', t('by'), company.bynavn) + '</div>' +
            field('tlf', t('tlf'), company.tlf) +
            field('email', t('email'), company.email) +
            '<label class="kred-cvr-field"><span>' + esc(t('gruppe')) + '</span><select name="gruppe"></select></label>' +
            '<label class="kred-cvr-field"><span>' + esc(t('betalingsbet')) + '</span><span class="kred-cvr-terms"><select name="betalingsbet"></select> + <input type="text" name="betalingsdage" size="3"> ' + esc(t('dage')) + '</span></label>' +
            '<label class="kred-cvr-default"><input type="checkbox" name="saveDefault" value="1"> ' + esc(t('saveDefault')) + '</label>' +
            '<div class="kred-cvr-error"></div>' +
            '<div class="kred-cvr-buttons"><button type="button" class="kred-cvr-cancel">' + esc(t('cancel')) + '</button> <button type="submit" class="kred-cvr-ok">' + esc(t('create')) + '</button></div>' +
            '</form>';
        document.body.appendChild(overlay);
        var form = overlay.querySelector('form');
        var error = overlay.querySelector('.kred-cvr-error');

        function close() {
            overlay.remove();
            dialogOpen = false;
            if (options.returnFocus) options.returnFocus.focus();
        }

        post('form', {}).then(function (data) {
            var d = data.defaults || {};
            form.gruppe.innerHTML = (data.groups || []).map(function (g) {
                return '<option value="' + g.kodenr + '"' + (g.kodenr === d.gruppe ? ' selected' : '') + '>' + g.kodenr + ': ' + esc(g.beskrivelse) + '</option>';
            }).join('');
            form.betalingsbet.innerHTML = (data.terms || []).map(function (term) {
                return '<option' + (term === d.betalingsbet ? ' selected' : '') + '>' + esc(term) + '</option>';
            }).join('');
            form.betalingsdage.value = d.betalingsdage != null ? d.betalingsdage : 8;
            // No default yet: this first creation stores it
            form.saveDefault.checked = d.gruppe === null;
        });

        loadCvrLookup().then(function (ok) {
            if (ok && typeof window.cvrKeyupOpslag === 'function') {
                // Loading the script binds the field itself; bound once either way
                window.jQuery(form.cvrnr).off('keyup blur').on('keyup', window.cvrKeyupOpslag).on('blur', window.cvrBlurOpslag);
            }
        });

        overlay.querySelector('.kred-cvr-cancel').addEventListener('click', close);
        overlay.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.stopPropagation();
                close();
            }
            // Enter in the dialog submits it, not the pool's "Gem og næste"
            if (e.key === 'Enter') e.stopPropagation();
        });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (form.firmanavn.value.trim() === '') {
                error.textContent = t('nameMissing');
                form.firmanavn.focus();
                return;
            }
            var data = { mode: 'dialog', poolFile: options.poolFile || '' };
            if (options.allowClosed) data.allowClosed = '1';
            ['cvrnr', 'firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'tlf', 'email', 'gruppe', 'betalingsbet', 'betalingsdage'].forEach(function (name) {
                data[name] = form[name].value;
            });
            if (form.saveDefault.checked) data.saveDefault = '1';
            form.querySelector('.kred-cvr-ok').disabled = true;
            post('create', data).then(function (result) {
                if (result && result.status === 'closed') {
                    // A closed kreditor has the CVR number typed: "Genåbn" or "Opret ny"
                    form.querySelector('.kred-cvr-ok').disabled = false;
                    error.innerHTML = kreditorLabel(result.kreditor) + ' ' + esc(t('closed')) + ' — <a href="#" class="kred-cvr-reopen">' + esc(t('reopen')) + '</a>' +
                        ' · <a href="#" class="kred-cvr-create">' + esc(t('createNew')) + '</a>';
                    error.querySelector('.kred-cvr-reopen').addEventListener('click', function (ev) {
                        ev.preventDefault();
                        post('reopen', { id: result.kreditor.id, poolFile: options.poolFile || '' }).then(function (answer) {
                            if (answer && answer.ok) {
                                close();
                                options.onCreated(answer.kreditor, true, 'reopened');
                            } else {
                                error.textContent = t('failed');
                            }
                        });
                    });
                    error.querySelector('.kred-cvr-create').addEventListener('click', function (ev) {
                        ev.preventDefault();
                        options.allowClosed = true;
                        form.requestSubmit();
                    });
                } else if (result && result.kreditor) {
                    close();
                    options.onCreated(result.kreditor, result.status === 'match');
                } else {
                    form.querySelector('.kred-cvr-ok').disabled = false;
                    error.textContent = t('failed');
                }
            });
        });
        // The CVR field first when it is empty (Tab fetches the company), otherwise the name to check
        (company.cvrnr ? form.firmanavn : form.cvrnr).focus();
    }

    window.kreditorFromCvr = { openDialog: openDialog, resolveCurrent: resolveCurrent };

    // ---------------------------------------------------------------- Pool wiring

    function isPool() {
        return !!document.getElementById('bilagRowsContainer');
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

    function start() {
        if (!isPool()) return;
        // After SD-716's transfer, which fills Kredit itself for a confident vendor match
        whenListLoaded(function () { setTimeout(resolveCurrent, 50); });
    }

    // Saving the new line ends "Fortryd": the line now refers to the kreditor
    document.addEventListener('click', function (e) {
        var box = document.getElementById('kredCvrNotice');
        if (!box || !box.dataset.kreditorId || !(e.target instanceof Element)) return;
        if (e.target.closest('#saveNextBtn, #gemAlleBtn')) removeNotice();
    }, true);

    document.addEventListener('poolswitch', start);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(start, 0); });
    } else {
        setTimeout(start, 0);
    }
})();
