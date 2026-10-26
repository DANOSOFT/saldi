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
// 20261006 CL/SZ SD-722 The report button asks the server on load whether this user reported the document already, so after a reload or on the
//                journal line it reads "Du har allerede rapporteret dette bilag" too, not only right after sending.
// 20261026 CL/LH The Debet suggestion looks like Kredit's "Fundet via": the account's name under Debet, then "3 af 5 bilag · 1 anden ▾ ×",
//                instead of a box with a sentence and a button. Filled in by default; not filled in, it is Debet's dashed placeholder,
//                taken with Enter, Tab or a click on the account's name. "1 anden ▾" / Alt+↓ lists the other accounts of the last bilag with their counts,
//                and "Søg anden konto" hands over to the lookup. A suggestion from a single bilag is never filled in.
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
            '.pool-capture-badge.suggested.weak{background:#fff;color:#4a90d9;box-shadow:inset 0 0 0 1px #4a90d9}' +
            'input.pool-suggest-ghost{border:1px dashed #4a90d9 !important}' +
            'input.pool-suggest-ghost::placeholder{color:#4a90d9;font-style:italic;opacity:1}' +
            '.pool-account-name.pool-suggest-ghostname{color:#1d5fa3;cursor:pointer}' +
            '.pool-account-name.pool-suggest-ghostname:hover{text-decoration:underline}' +
            '.pool-capture-reason.pool-suggest{white-space:nowrap;max-width:none}' +
            '.pool-suggest button.pool-suggest-more{border:none;border-bottom:1px dotted #1d5fa3;background:none;padding:0;font:inherit;color:#1d5fa3;cursor:pointer}' +
            '#poolSuggestList{position:fixed;z-index:10040;background:#fff;border:1px solid #9cc4ea;border-radius:5px;box-shadow:0 6px 18px rgba(20,40,80,.18);min-width:290px;max-width:400px;padding:4px 0;font-size:12px}' +
            '#poolSuggestList .hd{padding:3px 10px 5px;color:#666;font-size:11px;border-bottom:1px solid #eef2f7}' +
            '#poolSuggestList .opt{display:grid;grid-template-columns:12px 52px 1fr 30px 44px;gap:6px;align-items:center;padding:5px 10px;cursor:pointer}' +
            '#poolSuggestList .opt.hl{background:#e6f3ff}' +
            '#poolSuggestList .chk{color:#1d5fa3;font-weight:700}' +
            '#poolSuggestList .acc{font-weight:600}' +
            '#poolSuggestList .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
            '#poolSuggestList .moms{color:#777;font-size:11px}' +
            '#poolSuggestList .cnt{text-align:right;color:#555;font-size:11px;white-space:nowrap}' +
            '#poolSuggestList .bar{display:inline-block;height:5px;border-radius:2px;background:#4a90d9;vertical-align:middle;margin-right:4px}' +
            '#poolSuggestList .ft{border-top:1px solid #eef2f7;margin-top:3px;padding:4px 10px 2px}' +
            '#poolSuggestList .ft button{border:none;background:none;padding:0;font:inherit;font-size:11px;color:#1d5fa3;text-decoration:underline;cursor:pointer}' +
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
    //
    // Under Debet, the way Kredit shows how it was found: the account's name, then "3 af 5 bilag · 1 anden ▾ ×".
    // Filled in (the default), Debet holds the account and "Forslag". Not filled in (switched off, or a single bilag),
    // the account is Debet's dashed placeholder ("2100 ↵"); Enter, Tab or a click on the name under it takes it.
    // The line is the same in both, and no wider than Debet, so Kredit doesn't move.
    // "1 anden ▾" (or Alt+↓ in Debet) lists the other accounts the last bilag went to.

    var list = null; // The open list of accounts: {box, entry, index}

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

    function fill(text, values) {
        return String(text || '').replace(/\$(antal|total)/g, function (all, key) { return values[key] !== undefined ? values[key] : all; });
    }

    function setGhost(f, item) {
        var debet = f.debet;
        if (!('ghostPlaceholder' in debet.dataset)) debet.dataset.ghostPlaceholder = debet.getAttribute('placeholder') || '';
        debet.setAttribute('placeholder', item.account + ' ↵');
        debet.classList.add('pool-suggest-ghost');
        if (f.debetName && debet.value.trim() === '') {
            f.debetName.textContent = item.name;
            f.debetName.title = texts().use;
            f.debetName.classList.add('pool-suggest-ghostname');
        }
    }

    function clearGhost(f) {
        var debet = f.debet;
        if ('ghostPlaceholder' in debet.dataset) {
            debet.setAttribute('placeholder', debet.dataset.ghostPlaceholder);
            delete debet.dataset.ghostPlaceholder;
        }
        debet.classList.remove('pool-suggest-ghost');
        if (f.debetName && f.debetName.classList.contains('pool-suggest-ghostname')) {
            // The name was the placeholder's; a typed account gets its own from docPoolAccounts.js on change
            f.debetName.classList.remove('pool-suggest-ghostname');
            f.debetName.textContent = '';
            f.debetName.title = '';
        }
    }

    function removeSuggestion(entry) {
        if (!entry) return;
        if (list && list.entry === entry) closeList();
        var line = entry.querySelector('.pool-suggest');
        if (line) line.remove();
        var f = rowFields(entry);
        if (f.debet && f.debet.classList.contains('pool-suggest-ghost')) {
            clearGhost(f);
            // The placeholder's "Forslag"; a filled Debet keeps its own until unmark()
            var box = fieldBox(f.debet);
            var b = box && !f.debet.classList.contains('pool-suggested') ? box.querySelector('.pool-capture-badge') : null;
            if (b) b.remove();
        }
        delete entry.poolSuggestion;
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

    function showSuggestion(entry, kreditor, suggestion, auto) {
        var state = {
            kreditor: kreditor,
            all: [suggestion].concat(suggestion.alternatives || []),
            total: suggestion.total || suggestion.count,
            reason: suggestion.reason
        };
        // One bilag is too little to write the account in without asking
        renderSuggestion(entry, state, 0, auto && suggestion.count > 1);
    }

    /**
     * Shows account state.all[index] under Debet: written into Debet when filled, else as Debet's placeholder.
     * @param {HTMLElement} entry
     * @param {{kreditor: string, all: Array, total: number, reason: string}} state
     * @param {number} index
     * @param {boolean} filled
     */
    function renderSuggestion(entry, state, index, filled) {
        var f = rowFields(entry);
        var item = state.all[index];
        removeSuggestion(entry);
        // Both kinds need the styles: without them the line under Debet stretches the row (a document without captured values)
        addStyles();
        state.index = index;
        state.filledWith = filled ? String(item.account) : '';
        entry.poolSuggestion = state;
        var line = el('div', 'pool-capture-reason pool-suggest');
        line.dataset.kreditor = state.kreditor;
        line.title = item.reason || '';
        line.appendChild(document.createTextNode(fill(texts().ofTotal, { antal: item.count, total: state.total })));
        var others = state.all.length - 1;
        if (others > 0) {
            line.appendChild(document.createTextNode(' · '));
            var more = el('button', 'pool-suggest-more', (others === 1 ? texts().oneOther : fill(texts().others, { antal: others })) + ' ▾');
            more.type = 'button';
            more.tabIndex = -1;
            more.setAttribute('aria-haspopup', 'listbox');
            more.setAttribute('aria-expanded', 'false');
            more.addEventListener('mousedown', function (e) {
                // Debet keeps the cursor; a second click closes the list
                e.preventDefault();
                if (list && list.entry === entry) closeList();
                else openList(entry);
            });
            line.appendChild(more);
        }
        line.appendChild(rejectButton(function () { rejectSuggestion(entry); }));
        if (filled) {
            fillDebet(f, item);
            f.debet.classList.add('pool-suggested');
        } else {
            setGhost(f, item);
        }
        // Hollow while it rests on a single bilag and nobody chose it
        badge(f.debet, texts().suggested, 'suggested' + (filled || item.count > 1 ? '' : ' weak'));
        var field = f.debet.closest('.pool-account-field') || fieldBox(f.debet);
        if (field) field.appendChild(line);
        // The lookup panel opened on Debet would cover the line; with the cursor in Debet it is kept shut, so Enter takes
        // the placeholder or, filled in, saves.
        if (document.activeElement === f.debet) {
            closePanel();
            keepPanelShut(f.debet);
        }
    }

    function takeSuggestion(entry, index) {
        var state = entry.poolSuggestion;
        // A line another document replaced (SD-719) keeps its state but is no longer on the page
        if (!state || !document.body.contains(entry)) return;
        renderSuggestion(entry, state, index, true);
        // Back in Debet, where the next Enter saves
        rowFields(entry).debet.focus();
    }

    function rejectSuggestion(entry) {
        var state = entry.poolSuggestion;
        if (!state) return;
        var f = rowFields(entry);
        var item = state.all[state.index];
        reject('debet', 'F' + item.account, item.reason || state.reason);
        dropSuggestion(entry);
        f.debet.focus();
    }

    /** Ends the suggestion on this line without asking again for this kreditor; a Debet it wrote is emptied. */
    function dropSuggestion(entry) {
        var state = entry.poolSuggestion;
        var f = rowFields(entry);
        entry.dataset.suggestRejected = state.kreditor;
        var filledWith = state.filledWith;
        removeSuggestion(entry);
        if (f.debet.classList.contains('pool-suggested')) {
            unmark(f.debet);
            if (filledWith && f.debet.value.trim() === filledWith) clearAccount(f.debet);
        }
    }

    function closePanel() {
        if (typeof window.closeAccountAutocomplete === 'function') window.closeAccountAutocomplete();
    }

    /**
     * The panel opens on focus after a delay and its own search, so it can appear after the suggestion is shown.
     * For a moment it is shut again while Debet still shows the untouched suggestion; typing in Debet ends this.
     */
    function keepPanelShut(debet) {
        var container = document.getElementById('account-autocomplete-container');
        if (!container || typeof MutationObserver !== 'function') return;
        var observer = new MutationObserver(function () {
            var entry = entryOf(debet);
            var state = entry && entry.poolSuggestion;
            var untouched = state && (debet.classList.contains('pool-suggest-ghost')
                ? debet.value.trim() === ''
                : debet.classList.contains('pool-suggested') && debet.value.trim() === state.filledWith);
            if (document.activeElement === debet && untouched) closePanel();
            else observer.disconnect();
        });
        observer.observe(container, { attributes: true, attributeFilter: ['style'], subtree: true, childList: true });
        setTimeout(function () { observer.disconnect(); }, 2000);
    }

    // ---------------------------------------------------------------- The list of the kreditor's other accounts

    function openList(entry) {
        var state = entry.poolSuggestion;
        var line = entry.querySelector('.pool-suggest');
        if (!state || !line || state.all.length < 2) return;
        closeList();
        closePanel();
        var box = el('div');
        box.id = 'poolSuggestList';
        box.setAttribute('role', 'listbox');
        box.appendChild(el('div', 'hd', fill(texts().listHead, { antal: state.total })));
        var max = state.all.reduce(function (m, item) { return Math.max(m, item.count); }, 1);
        state.all.forEach(function (item, i) {
            var opt = el('div', 'opt');
            opt.setAttribute('role', 'option');
            opt.setAttribute('aria-selected', i === state.index ? 'true' : 'false');
            opt.title = item.account + ' ' + item.name;
            opt.appendChild(el('span', 'chk', i === state.index ? '✓' : ''));
            opt.appendChild(el('span', 'acc', item.account));
            opt.appendChild(el('span', 'nm', item.name));
            opt.appendChild(el('span', 'moms', item.moms || ''));
            var count = el('span', 'cnt');
            var bar = el('span', 'bar');
            bar.style.width = Math.max(4, Math.round(24 * item.count / max)) + 'px';
            count.appendChild(bar);
            count.appendChild(document.createTextNode(String(item.count)));
            opt.appendChild(count);
            opt.addEventListener('mousedown', function (e) {
                e.preventDefault();
                takeSuggestion(entry, i);
            });
            opt.addEventListener('mousemove', function () { highlight(i); });
            box.appendChild(opt);
        });
        var foot = el('div', 'ft');
        var search = el('button', '', texts().searchOther + '…');
        search.type = 'button';
        search.tabIndex = -1;
        search.addEventListener('mousedown', function (e) {
            e.preventDefault();
            searchOther(entry);
        });
        foot.appendChild(search);
        box.appendChild(foot);
        document.body.appendChild(box);
        // Fixed on the page, so a scrolling line container can't cut it off
        var r = line.getBoundingClientRect();
        var h = box.offsetHeight;
        box.style.left = Math.max(4, Math.min(r.left, window.innerWidth - box.offsetWidth - 4)) + 'px';
        box.style.top = (r.bottom + 4 + h > window.innerHeight && r.top - 4 - h > 0 ? r.top - 4 - h : r.bottom + 4) + 'px';
        list = { box: box, entry: entry, index: state.index };
        highlight(state.index);
        var more = line.querySelector('.pool-suggest-more');
        if (more) {
            more.setAttribute('aria-expanded', 'true');
            more.textContent = more.textContent.replace('▾', '▴');
        }
    }

    function closeList() {
        if (!list) return;
        var line = list.entry.querySelector('.pool-suggest');
        var more = line && line.querySelector('.pool-suggest-more');
        if (more) {
            more.setAttribute('aria-expanded', 'false');
            more.textContent = more.textContent.replace('▴', '▾');
        }
        list.box.remove();
        list = null;
    }

    function highlight(i) {
        if (!list) return;
        var opts = list.box.querySelectorAll('.opt');
        list.index = (i + opts.length) % opts.length;
        opts.forEach(function (opt, n) { opt.classList.toggle('hl', n === list.index); });
    }

    /** "Søg anden konto": the suggestion steps aside and Debet's own lookup takes over. */
    function searchOther(entry) {
        var f = rowFields(entry);
        closeList();
        dropSuggestion(entry);
        // The panel opens on focus; with the cursor already in Debet (Alt+↓) a click opens it, as in accountAutocomplete.js
        if (document.activeElement === f.debet) f.debet.click();
        else f.debet.focus();
    }

    // Keys: Enter or Tab takes the placeholder, Alt+↓ opens the list; with the list open ↑ ↓ Enter Esc work in it.
    // On window, capturing, so it runs before the journal's own Enter handling (fieldNavigation.js).
    window.addEventListener('keydown', function (e) {
        if (list) {
            if (/^(Shift|Control|Alt|Meta)$/.test(e.key)) return;
            // Ctrl/Cmd + key (fieldNavigation.js moves on Ctrl+arrows), or the line is gone: the list closes and the key goes on
            if (e.ctrlKey || e.metaKey || !document.body.contains(list.entry)) {
                closeList();
                return;
            }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                highlight(list.index + (e.key === 'ArrowDown' ? 1 : -1));
            } else if (e.key === 'Enter') {
                takeSuggestion(list.entry, list.index);
            } else if (e.key === 'Escape') {
                closeList();
            } else {
                closeList();
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            return;
        }
        var input = e.target;
        if (!(input instanceof HTMLInputElement) || !/_Debet$/.test(input.id)) return;
        var entry = entryOf(input);
        var state = entry && entry.poolSuggestion;
        if (!state) return;
        if (e.key === 'ArrowDown' && e.altKey && state.all.length > 1) {
            e.preventDefault();
            e.stopImmediatePropagation();
            openList(entry);
        } else if ((e.key === 'Enter' || e.key === 'Tab') && !e.shiftKey && !e.ctrlKey && !e.altKey && !e.metaKey
            && input.classList.contains('pool-suggest-ghost') && input.value.trim() === '' && !panelHasSelection()) {
            takeSuggestion(entry, state.index);
            // Enter stays in Debet, where the next Enter saves; Tab goes on to the next field as usual
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        }
    }, true);

    // A row highlighted in the lookup panel takes Enter, as in docPoolSaveNext.js
    function panelHasSelection() {
        return Array.prototype.some.call(document.querySelectorAll('.account-autocomplete-dropdown'), function (dd) {
            return dd.style.display !== 'none' && dd.getClientRects().length > 0 && !!dd.querySelector('.account-autocomplete-item.selected');
        });
    }

    // Not filled in, the account's name under Debet takes it ("Brug forslag"), as Enter in Debet does
    document.addEventListener('mousedown', function (e) {
        var name = e.target.closest ? e.target.closest('.pool-suggest-ghostname') : null;
        var entry = name && entryOf(name);
        if (!entry || !entry.poolSuggestion) return;
        e.preventDefault();
        takeSuggestion(entry, entry.poolSuggestion.index);
    });

    document.addEventListener('mousedown', function (e) {
        // "1 anden ▾" toggles the list itself
        if (list && !list.box.contains(e.target) && !(e.target.closest && e.target.closest('.pool-suggest-more'))) closeList();
    }, true);
    window.addEventListener('resize', closeList);
    document.addEventListener('scroll', function (e) {
        if (list && !list.box.contains(e.target)) closeList();
    }, true);

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
        // Rejected with "×" (or put aside with "Søg anden konto"): not offered again for this kreditor on this line
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
            // Two change events can ask at the same time; the second answer must not replace the line (and its focus)
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
                    if (entry) {
                        entry.querySelectorAll('input.pool-captured, input.pool-suggested').forEach(unmark);
                        removeSuggestion(entry);
                    }
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

    /** A document this user reported before (also from another page, or as a renamed or attached copy) gets the "already reported" button. */
    function showReportedState() {
        var ref = documentRef();
        var key = ref.poolFile || (ref.filename + '|' + ref.sourceId);
        if (reportedFiles[key]) {
            markReported(key);
            return;
        }
        post(Object.assign({ action: 'info' }, ref)).then(function (info) {
            // The page may have switched to another document meanwhile (SD-719)
            var now = documentRef();
            if (info && info.reported && (now.poolFile || (now.filename + '|' + now.sourceId)) === key) markReported(key);
        }).catch(function () {});
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
            showReportedState();
            return;
        }
        var panel = document.getElementById('rightPanel');
        if (!panel || !c.poolFile) return;
        var bar = el('div');
        bar.id = 'poolCaptureBar';
        bar.appendChild(reportButton());
        panel.insertBefore(bar, panel.firstChild);
        showReportedState();
    }

    function init() {
        hookSave();
        placeReportButton();
        considerAll();
    }

    window.poolCapture = { mark: mark, consider: considerRow, openReport: openReport };

    // A document opened in place (SD-719) brings its own config
    document.addEventListener('poolswitch', function () {
        closeList();
        suggestions = {};
        init();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
    } else {
        setTimeout(init, 0);
    }
})();
