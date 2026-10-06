// --- javascript/poolCapture.js --- ver 5.0.0 --- 2026-10-03 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-722 Created: data capture in the document pool.
//                "Aflæst" on a field "Overfør data" filled with what the extraction read, until the user changes it or saves.
//                Kredit filled from the vendor match says how the kreditor was found ("Fundet via CVR-nr.").
//                With a kreditor in Kredit and Debet empty, the contra account from the kreditor's history is shown under Debet with its reason and "Brug forslag".
//                With "Udfyld modkonto automatisk" on (the journal's gear box) it is written into Debet as "Forslag" instead.
//                "×" rejects a suggestion; the rejection is stored with the correction records.
//                "Rapportér fejl i aflæsning" (when switched on for the company): captured and current values, a comment, "Send".
//                Needs window.saldiPoolCapture (docPool.php, or includes/documents.php for a document attached to a journal line).
// 20261006 CL/SZ SD-722 The suggestion box under Debet is styled also when it isn't filled in automatically: unstyled, it stretched the line and moved Debet and Kredit.
(function () {
    'use strict';

    var suggestions = {};   // kreditor => promise of the endpoint's answer, for this page
    var reportedFiles = {}; // file => true once this user reported it here

    function cfg() {
        return window.saldiPoolCapture || null;
    }

    function texts() {
        return (cfg() && cfg().texts) || {};
    }

    function post(data) {
        var form = new FormData();
        Object.keys(data).forEach(function (key) {
            var value = data[key];
            if (value && typeof value === 'object') {
                Object.keys(value).forEach(function (sub) { form.append(key + '[' + sub + ']', value[sub]); });
            } else if (value !== undefined && value !== null) {
                form.append(key, value);
            }
        });
        return fetch(cfg().endpoint, { method: 'POST', body: form, credentials: 'same-origin' })
            .then(function (response) {
                return response.json().catch(function () { return { error: 'http ' + response.status }; });
            });
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function addStyles() {
        if (document.getElementById('poolCaptureStyles')) return;
        var style = document.createElement('style');
        style.id = 'poolCaptureStyles';
        style.textContent =
            '.pool-capture-field{position:relative}' +
            // A taller Debet or Kredit (reason, suggestion) must not move the other fields of the row
            '#kassebilagTopBar .topbar-fields-row:has(.pool-suggest),#kassebilagTopBar .topbar-fields-row:has(.pool-capture-reason){align-items:flex-start}' +
            'input.pool-captured{background-color:#fff7d6 !important}' +
            'input.pool-suggested{background-color:#e6f3ff !important}' +
            '.pool-capture-badge{display:inline-block;margin-left:4px;font-size:9px;line-height:12px;padding:0 4px;border-radius:6px;background:#f0c040;color:#3d3000;font-weight:600;vertical-align:1px;white-space:nowrap}' +
            '.pool-capture-badge.suggested{background:#4a90d9;color:#fff}' +
            '.pool-capture-x{border:none;background:none;color:#888;cursor:pointer;font-size:13px;line-height:1;padding:0 2px;margin-left:2px}' +
            '.pool-capture-x:hover,.pool-capture-x:focus{color:#c00}' +
            '.pool-capture-reason{font-size:11px;color:#555;margin-top:2px;max-width:260px;white-space:normal;line-height:1.3}' +
            '.pool-suggest{margin-top:3px;padding:3px 6px;border:1px solid #9cc4ea;border-radius:4px;background:#f2f8fe;font-size:11px;max-width:260px;white-space:normal;line-height:1.35}' +
            '.pool-suggest b{font-weight:600}' +
            '.pool-suggest button.pool-suggest-use{margin:3px 4px 0 0;padding:1px 8px;font-size:11px;border:1px solid #4a90d9;border-radius:3px;background:#4a90d9;color:#fff;cursor:pointer}' +
            '.pool-suggest button.pool-suggest-use:focus{outline:2px solid #1d5fa3;outline-offset:1px}' +
            '#poolCaptureBar{display:flex;justify-content:flex-end;padding:4px 6px;flex-shrink:0}' +
            '.pool-capture-report-btn{padding:4px 10px;font-size:12px;border:1px solid #bbb;border-radius:4px;background:#fff;color:#333;cursor:pointer}' +
            '.pool-capture-report-btn[disabled]{opacity:.6;cursor:default}' +
            '#poolCaptureDialog{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10050;display:flex;align-items:center;justify-content:center}' +
            '#poolCaptureDialog .box{background:#fff;border-radius:8px;padding:16px 18px;width:min(560px,calc(100vw - 32px));max-height:calc(100vh - 40px);overflow:auto;box-shadow:0 8px 30px rgba(0,0,0,.25);font-size:13px}' +
            '#poolCaptureDialog h3{margin:0 0 10px;font-size:15px}' +
            '#poolCaptureDialog table{width:100%;border-collapse:collapse;margin-bottom:10px}' +
            '#poolCaptureDialog th,#poolCaptureDialog td{border-bottom:1px solid #eee;padding:4px 6px;text-align:left;vertical-align:top;word-break:break-word}' +
            '#poolCaptureDialog td.diff{color:#b00020;font-weight:600}' +
            '#poolCaptureDialog textarea{width:100%;box-sizing:border-box;min-height:60px;font:inherit;margin-top:3px}' +
            '#poolCaptureDialog .note{color:#555;margin:8px 0}' +
            '#poolCaptureDialog .buttons{display:flex;justify-content:flex-end;gap:8px;margin-top:10px}' +
            '#poolCaptureDialog .buttons button{padding:6px 14px;border-radius:4px;border:1px solid #bbb;background:#fff;cursor:pointer}' +
            '#poolCaptureDialog .buttons button.primary{background:#114691;color:#fff;border-color:#114691}' +
            '.pool-capture-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#2e7d32;color:#fff;padding:8px 16px;border-radius:6px;z-index:10060;font-size:13px}';
        document.head.appendChild(style);
    }

    // ---------------------------------------------------------------- "Aflæst" and the kreditor's reason

    function fieldBox(input) {
        return input.closest('.topbar-field');
    }

    /** The small label after the field's own label, so it covers nothing. */
    function badge(input, text, kind) {
        var box = fieldBox(input);
        if (!box) return;
        box.classList.add('pool-capture-field');
        var old = box.querySelector('.pool-capture-badge');
        if (old) old.remove();
        var b = el('span', 'pool-capture-badge' + (kind ? ' ' + kind : ''), text);
        var label = box.querySelector('label');
        (label || box).appendChild(b);
    }

    function unmark(input) {
        input.classList.remove('pool-captured', 'pool-suggested');
        delete input.dataset.captureField;
        var box = fieldBox(input);
        if (!box) return;
        ['.pool-capture-badge', '.pool-capture-reason'].forEach(function (sel) {
            var node = box.querySelector(sel);
            if (node) node.remove();
        });
    }

    var vendorReasons = { cvr: 'viaCvr', bank: 'viaBank', name: 'viaName' };

    /**
     * Called by "Overfør data" for each field it fills. Marked only when the value is what the extraction read for the open document.
     * @param {HTMLInputElement} input
     * @param {string} field     date, amount, invoiceNumber or kreditor
     * @param {string} filename  The document the value came from.
     */
    function mark(input, field, filename) {
        var c = cfg();
        if (!c || c.readOnly || !input || filename !== c.poolFile || (c.fields || []).indexOf(field) === -1) return;
        addStyles();
        input.classList.remove('pool-suggested');
        input.classList.add('pool-captured');
        input.dataset.captureField = field;
        badge(input, texts().read);
        if (field === 'kreditor' && vendorReasons[c.vendorMatch]) {
            var box = fieldBox(input);
            var reasonText = texts()[vendorReasons[c.vendorMatch]];
            var reason = el('div', 'pool-capture-reason', reasonText);
            var x = rejectButton(function () {
                var value = input.value.trim();
                var type = document.getElementById(input.id + 'Type');
                reject('kreditor', (type && type.value ? type.value : 'K') + value, reasonText);
                clearAccount(input);
                unmark(input);
            });
            reason.appendChild(x);
            if (box) box.appendChild(reason);
        }
    }

    function rejectButton(onReject) {
        var x = el('button', 'pool-capture-x', '×');
        x.type = 'button';
        x.title = texts().reject;
        x.setAttribute('aria-label', texts().reject);
        x.addEventListener('click', function (e) {
            e.preventDefault();
            onReject();
        });
        return x;
    }

    function reject(field, value, reason) {
        var c = cfg();
        if (!c || !c.endpoint) return;
        post({ action: 'reject', poolFile: c.poolFile || '', field: field, value: value, reason: reason }).catch(function () {});
    }

    /** Empties an account field the way the user would, so the name and the lookup follow. */
    function clearAccount(input) {
        input.value = '';
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // The user changing a marked field ends the mark
    document.addEventListener('input', function (e) {
        var input = e.target;
        if (input instanceof HTMLInputElement && (input.classList.contains('pool-captured') || input.classList.contains('pool-suggested'))) unmark(input);
        if (input instanceof HTMLInputElement && /_Debet$/.test(input.id)) removeSuggestion(entryOf(input));
    });

    // ---------------------------------------------------------------- The contra account suggestion under Debet

    function entryOf(node) {
        return node && node.closest ? node.closest('.kassebilag-entry') : null;
    }

    function rowFields(entry) {
        var prefix = 'row_' + entry.id.replace('bilagEntry_', '') + '_';
        return {
            debet: document.getElementById(prefix + 'Debet'),
            debetType: document.getElementById(prefix + 'DebetType'),
            debetName: document.getElementById(prefix + 'DebetName'),
            kredit: document.getElementById(prefix + 'Kredit'),
            kreditType: document.getElementById(prefix + 'KreditType')
        };
    }

    function removeSuggestion(entry) {
        if (!entry) return;
        var box = entry.querySelector('.pool-suggest');
        if (box) box.remove();
    }

    function fetchSuggestion(kreditor) {
        var c = cfg();
        if (!suggestions[kreditor]) {
            var url = c.suggestUrl + '?kreditor=' + encodeURIComponent(kreditor) + '&kladde_id=' + encodeURIComponent(c.kladdeId || 0);
            suggestions[kreditor] = fetch(url, { credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : {}; })
                .catch(function () { return {}; });
        }
        return suggestions[kreditor];
    }

    /** Fills Debet with the suggested account, as choosing it in the lookup panel would. */
    function fillDebet(f, suggestion) {
        if (f.debetType) f.debetType.value = suggestion.type || 'F';
        f.debet.value = suggestion.account;
        f.debet.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function suggestionLine(suggestion) {
        var line = el('div');
        line.appendChild(el('b', '', texts().suggested + ': ' + suggestion.account + ' ' + suggestion.name));
        if (suggestion.moms) line.appendChild(document.createTextNode(' (' + suggestion.moms + ')'));
        return line;
    }

    function showSuggestion(entry, kreditor, suggestion, auto) {
        var f = rowFields(entry);
        removeSuggestion(entry);
        // Both kinds need the styles: without them the box under Debet stretches the line (a document without captured values)
        addStyles();
        var box = el('div', 'pool-suggest');
        box.dataset.kreditor = kreditor;
        var reason = el('div', 'pool-capture-reason', suggestion.reason);
        if (auto) {
            fillDebet(f, suggestion);
            f.debet.classList.add('pool-suggested');
            badge(f.debet, texts().suggested, 'suggested');
            reason.appendChild(rejectButton(function () {
                reject('debet', 'F' + suggestion.account, suggestion.reason);
                entry.dataset.suggestRejected = kreditor;
                removeSuggestion(entry);
                unmark(f.debet);
                if (f.debet.value.trim() === String(suggestion.account)) clearAccount(f.debet);
                f.debet.focus();
            }));
            box.appendChild(reason);
        } else {
            box.appendChild(suggestionLine(suggestion));
            box.appendChild(reason);
            var use = el('button', 'pool-suggest-use', texts().use);
            use.type = 'button';
            use.addEventListener('click', function () {
                fillDebet(f, suggestion);
                removeSuggestion(entry);
                // Back in Debet, where the next Enter saves (the panel the focus opens has no line highlighted)
                f.debet.focus();
            });
            box.appendChild(use);
            box.appendChild(rejectButton(function () {
                reject('debet', 'F' + suggestion.account, suggestion.reason);
                entry.dataset.suggestRejected = kreditor;
                removeSuggestion(entry);
                f.debet.focus();
            }));
        }
        var field = f.debet.closest('.pool-account-field') || fieldBox(f.debet);
        if (field) field.appendChild(box);
        // The lookup panel opened on the empty Debet would cover the suggestion and its reason. With the cursor in Debet the panel
        // is closed: the suggestion takes the focus, so Enter uses it ("Brug forslag"); filled automatically, Debet keeps it and Enter saves.
        if (document.activeElement === f.debet) {
            closePanel();
            var use = box.querySelector('.pool-suggest-use');
            if (use) use.focus();
            else keepPanelShut(f.debet, suggestion.account);
        }
    }

    function closePanel() {
        if (typeof window.closeAccountAutocomplete === 'function') window.closeAccountAutocomplete();
    }

    /**
     * The panel opens on focus after a delay and its own search, so it can appear after the automatic fill.
     * For a moment it is shut again while Debet still holds the untouched suggestion; typing in Debet ends this.
     */
    function keepPanelShut(debet, account) {
        var container = document.getElementById('account-autocomplete-container');
        if (!container || typeof MutationObserver !== 'function') return;
        var observer = new MutationObserver(function () {
            if (document.activeElement === debet && debet.classList.contains('pool-suggested') && debet.value.trim() === String(account)) closePanel();
            else observer.disconnect();
        });
        observer.observe(container, { attributes: true, attributeFilter: ['style'], subtree: true, childList: true });
        setTimeout(function () { observer.disconnect(); }, 2000);
    }

    /** Kredit holds a kreditor and Debet is empty: ask for the contra account. A typed Debet is never replaced. */
    function considerRow(entry) {
        var c = cfg();
        if (!c || c.readOnly || !c.suggestUrl || !entry) return;
        var f = rowFields(entry);
        if (!f.debet || !f.kredit || f.debet.readOnly || f.kredit.readOnly) return;
        var value = f.kredit.value.trim();
        var type = f.kreditType ? f.kreditType.value.trim().toUpperCase() : '';
        var prefixed = value.match(/^K(\d+)$/i);
        if (prefixed) { type = 'K'; value = prefixed[1]; }
        var current = entry.querySelector('.pool-suggest');
        // A Debet the suggestion wrote follows Kredit: another kreditor, or none, takes it away again
        if (f.debet.classList.contains('pool-suggested') && (!current || current.dataset.kreditor !== value || type !== 'K')) {
            unmark(f.debet);
            clearAccount(f.debet);
            removeSuggestion(entry);
            current = null;
        }
        if (type !== 'K' || !/^\d+$/.test(value)) {
            removeSuggestion(entry);
            return;
        }
        if (current && current.dataset.kreditor === value) return;
        // Rejected with "×": not offered again for this kreditor on this line
        if (entry.dataset.suggestRejected === value) return;
        if (f.debet.value.trim() !== '') {
            removeSuggestion(entry);
            return;
        }
        fetchSuggestion(value).then(function (data) {
            // The fields may have changed while the suggestion was looked up
            var now = rowFields(entry);
            if (!document.body.contains(entry) || now.debet.value.trim() !== '') return;
            var kNow = now.kredit.value.trim().replace(/^K/i, '');
            if (kNow !== value) return;
            // Two change events can ask at the same time; the second answer must not replace the box (and its focus)
            var shown = entry.querySelector('.pool-suggest');
            if (shown && shown.dataset.kreditor === value) return;
            if (!data || !data.suggestion) {
                removeSuggestion(entry);
                return;
            }
            showSuggestion(entry, value, data.suggestion, !!data.auto);
        });
    }

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!(input instanceof HTMLInputElement) || !/_(Kredit|KreditType|Debet)$/.test(input.id)) return;
        var entry = entryOf(input);
        if (!entry) return;
        // After docPoolAccounts.js has split a "K1234" into type and number
        setTimeout(function () { considerRow(entry); }, 0);
    });

    function considerAll() {
        document.querySelectorAll('#bilagRowsContainer .kassebilag-entry, .kassebilag-entry').forEach(considerRow);
    }

    // A save ends the marks of the saved row: the values are the user's now
    function hookSave() {
        if (typeof window._saveRowFetch !== 'function' || window._saveRowFetch.poolCapture) return;
        var original = window._saveRowFetch;
        var wrapped = function (rowId) {
            return original.apply(this, arguments).then(function (result) {
                if (result && result.success) {
                    var entry = document.getElementById('bilagEntry_' + rowId);
                    if (entry) entry.querySelectorAll('input.pool-captured, input.pool-suggested').forEach(unmark);
                }
                return result;
            });
        };
        wrapped.poolCapture = true;
        window._saveRowFetch = wrapped;
    }

    // ---------------------------------------------------------------- "Rapportér fejl i aflæsning"

    /** The values of the line being worked on, for "Nu": the first ticked line, else the new line. */
    function typedValues() {
        var entry = null;
        var ticked = document.querySelector('.targetLineCheckbox:checked');
        if (ticked) entry = entryOf(ticked);
        if (!entry) entry = document.getElementById('bilagEntry_new');
        if (!entry) return {};
        var prefix = 'row_' + entry.id.replace('bilagEntry_', '') + '_';
        var value = function (name) {
            var input = document.getElementById(prefix + name);
            return input ? input.value.trim() : '';
        };
        var out = { date: value('Dato'), amount: value('Amount'), invoiceNumber: value('Faktura'), currency: value('Valuta') };
        var kType = document.getElementById(prefix + 'KreditType');
        if ((kType && kType.value.toUpperCase() === 'K') || /^K\d+$/i.test(value('Kredit'))) out.kreditor = value('Kredit').replace(/^K/i, '');
        return out;
    }

    function documentRef() {
        var c = cfg();
        if (c.sourceId) return { filename: c.filename, sourceId: c.sourceId };
        return { poolFile: c.poolFile };
    }

    function toast(text) {
        var t = el('div', 'pool-capture-toast', text);
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 3500);
    }

    function closeDialog() {
        var d = document.getElementById('poolCaptureDialog');
        if (d) d.remove();
        document.removeEventListener('keydown', dialogKeys, true);
    }

    function dialogKeys(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeDialog();
        }
    }

    function markReported(key) {
        reportedFiles[key] = true;
        document.querySelectorAll('.pool-capture-report-btn').forEach(function (b) {
            b.disabled = true;
            b.textContent = texts().already;
        });
    }

    function openReport() {
        var c = cfg();
        if (!c || !c.reportEnabled) return;
        addStyles();
        var ref = documentRef();
        var key = ref.poolFile || (ref.filename + '|' + ref.sourceId);
        var current = c.sourceId ? {} : typedValues();
        post(Object.assign({ action: 'info', current: current }, ref)).then(function (info) {
            if (!info || !info.rows) {
                alert(texts().failed);
                return;
            }
            closeDialog();
            var overlay = el('div');
            overlay.id = 'poolCaptureDialog';
            var box = el('div', 'box');
            box.setAttribute('role', 'dialog');
            box.setAttribute('aria-modal', 'true');
            box.appendChild(el('h3', '', texts().report));
            if (!info.captured) box.appendChild(el('div', 'note', texts().noCapture));
            var table = el('table');
            var head = el('tr');
            [texts().field, texts().read, texts().now].forEach(function (h) { head.appendChild(el('th', '', h)); });
            table.appendChild(head);
            info.rows.forEach(function (row) {
                var tr = el('tr');
                tr.appendChild(el('td', '', (texts().labels || {})[row.field] || row.field));
                tr.appendChild(el('td', '', row.norm || '–'));
                tr.appendChild(el('td', row.changed ? 'diff' : '', row.final || '–'));
                table.appendChild(tr);
            });
            box.appendChild(table);
            var buttons = el('div', 'buttons');
            if (info.reported) {
                box.appendChild(el('div', 'note', texts().already));
                markReported(key);
                var ok = el('button', 'primary', 'OK');
                ok.type = 'button';
                ok.addEventListener('click', closeDialog);
                buttons.appendChild(ok);
            } else {
                var label = el('label', '', texts().comment);
                var comment = el('textarea');
                label.appendChild(comment);
                box.appendChild(label);
                box.appendChild(el('div', 'note', texts().notice));
                var cancel = el('button', '', texts().cancel);
                cancel.type = 'button';
                cancel.addEventListener('click', closeDialog);
                var send = el('button', 'primary', texts().send);
                send.type = 'button';
                send.addEventListener('click', function () {
                    send.disabled = true;
                    post(Object.assign({ action: 'report', current: current, comment: comment.value }, ref)).then(function (answer) {
                        if (!answer || !answer.ok) {
                            send.disabled = false;
                            alert(texts().failed);
                            return;
                        }
                        closeDialog();
                        markReported(key);
                        toast(texts().thanks);
                        // The mail goes out on its own request, so the bookkeeping never waits for it; a failure is retried later
                        if (answer.id) post({ action: 'deliver', id: answer.id }).catch(function () {});
                    }, function () {
                        send.disabled = false;
                        alert(texts().failed);
                    });
                });
                buttons.appendChild(cancel);
                buttons.appendChild(send);
            }
            box.appendChild(buttons);
            overlay.appendChild(box);
            overlay.addEventListener('click', function (e) { if (e.target === overlay) closeDialog(); });
            document.body.appendChild(overlay);
            document.addEventListener('keydown', dialogKeys, true);
            var focus = box.querySelector('textarea') || box.querySelector('button.primary');
            if (focus) focus.focus();
        }, function () { alert(texts().failed); });
    }

    function reportButton() {
        var b = el('button', 'pool-capture-report-btn', texts().report);
        b.type = 'button';
        b.addEventListener('click', openReport);
        return b;
    }

    /** The button above the document in the pool's viewer; a document attached to a line gets it from includes/documents.php's #poolCaptureReportSlot. */
    function placeReportButton() {
        var c = cfg();
        var old = document.getElementById('poolCaptureBar');
        if (old) old.remove();
        if (!c || !c.reportEnabled) return;
        addStyles();
        var slot = document.getElementById('poolCaptureReportSlot');
        if (slot) {
            slot.innerHTML = '';
            slot.appendChild(reportButton());
            return;
        }
        var panel = document.getElementById('rightPanel');
        if (!panel || !c.poolFile) return;
        var bar = el('div');
        bar.id = 'poolCaptureBar';
        var button = reportButton();
        if (reportedFiles[c.poolFile]) {
            button.disabled = true;
            button.textContent = texts().already;
        }
        bar.appendChild(button);
        panel.insertBefore(bar, panel.firstChild);
    }

    function init() {
        hookSave();
        placeReportButton();
        considerAll();
    }

    window.poolCapture = { mark: mark, consider: considerRow, openReport: openReport };

    // A document opened in place (SD-719) brings its own config
    document.addEventListener('poolswitch', function () {
        suggestions = {};
        init();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
    } else {
        setTimeout(init, 0);
    }
})();
