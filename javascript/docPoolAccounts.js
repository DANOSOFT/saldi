// --- javascript/docPoolAccounts.js --- ver 5.0.0 --- 2026-10-05 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-714 Created: Debet/Kredit in the pool's journal lines.
//                The lookup panel itself is accountAutocomplete.js, which binds to the debe/kred/d_ty/k_ty names the rows carry.
//                This file adds the type prefix ("K1234" sets type K and account 1234), the account's name and VAT code under the field, and the "sidste 5 posteringer" the other field's panel offers.
//                Needs window.saldiPoolAccounts.
// 20261004 CL/SZ SD-725 The VAT code select per side replaces the VAT badge under the name; it keeps the account's own code for "u/m".
//                "u/m" empties both codes and gives the accounts' codes back when unticked; choosing a code unticks "u/m", as in the journal.
// 20261005 CL/SZ SD-714 poolAccountValue() keeps search text that isn't an account as typed, so opening another document no longer turns "tele" into "Ftele".
// 20261006 CL/SZ SD-714 (CodeRabbit) A failed lookup also clears the other field's "sidste 5 posteringer" panel, so it no longer keeps showing the previous account's suggestions.
// 20261006 CL/SZ SD-714 (CodeRabbit) A failed lookup for a value that has been changed since clears nothing: the newer value's lookup decides.
(function () {
    'use strict';

    const TYPES = ['F', 'D', 'K'];
    const PREFIXED = /^([DKFdkf])(\d+)$/;
    const timers = {};

    /** The account and type inputs of one side of a pool line. */
    function sideFields(accountInput) {
        const prefix = accountInput.id.slice(0, -accountInput.dataset.side.length);
        return {
            prefix: prefix,
            side: accountInput.dataset.side,
            account: accountInput,
            type: document.getElementById(prefix + accountInput.dataset.side + 'Type'),
            name: document.getElementById(prefix + accountInput.dataset.side + 'Name'),
            vat: document.getElementById(prefix + accountInput.dataset.side + 'Vat'),
            other: document.getElementById(prefix + (accountInput.dataset.side === 'Debet' ? 'Kredit' : 'Debet'))
        };
    }

    function isEditable(input) {
        return input && !input.readOnly && !input.disabled;
    }

    /**
     * A value typed with a type prefix ("K1234") sets the type field and keeps only the number.
     * @param {HTMLInputElement} accountInput  A .pool-account-no field.
     */
    function splitPrefix(accountInput) {
        const match = accountInput.value.trim().match(PREFIXED);
        if (!match) return;
        const fields = sideFields(accountInput);
        accountInput.value = match[2];
        if (fields.type) fields.type.value = match[1].toUpperCase();
    }

    /** Shows the account's name and VAT code under the field, and gives the other field its suggestions. */
    function refreshSide(accountInput) {
        const fields = sideFields(accountInput);
        const cfg = window.saldiPoolAccounts || {};
        const kontonr = accountInput.value.trim();
        const type = (fields.type && fields.type.value) || 'F';
        clearTimeout(timers[accountInput.id]);
        if (!/^\d+$/.test(kontonr) || !cfg.lookupUrl) {
            if (fields.vat) fields.vat.dataset.accountVat = '';
            showName(fields, '', '');
            setLastPostings(fields.other, null);
            return;
        }
        timers[accountInput.id] = setTimeout(function () {
            const url = cfg.lookupUrl + '?art=' + encodeURIComponent(type) + '&kontonr=' + encodeURIComponent(kontonr) +
                '&dk=' + (fields.side === 'Debet' ? 'K' : 'D') + '&kladde_id=' + encodeURIComponent(cfg.kladdeId || 0);
            // A newer value may have been typed while this one was looked up: its own answer, or failure, decides then
            const stillCurrent = function () {
                return accountInput.value.trim() === kontonr && ((fields.type && fields.type.value) || 'F') === type;
            };
            fetch(url, { credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : {}; })
                .then(function (data) {
                    if (!stillCurrent()) return;
                    showName(fields, data.name || '', data.moms || '');
                    // The lookup panel sets the code itself when an account is chosen (dvat/kvat); "u/m" needs the account's own
                    if (fields.vat) fields.vat.dataset.accountVat = type === 'F' ? (data.moms || '') : '';
                    setLastPostings(fields.other, data.lastPostings);
                })
                .catch(function () {
                    if (!stillCurrent()) return;
                    showName(fields, '', '');
                    setLastPostings(fields.other, null);
                });
        }, 150);
    }

    function showName(fields, name, vat) {
        if (!fields.name) return;
        fields.name.textContent = name;
        fields.name.title = name;
        // A row with a VAT code field shows the code there (SD-725)
        if (vat && !fields.vat) {
            const badge = document.createElement('span');
            badge.className = 'pool-account-vat';
            badge.textContent = vat;
            // In front of the name, so a long name can't push it out of sight
            fields.name.insertBefore(badge, fields.name.firstChild);
        }
    }

    function setLastPostings(input, lastPostings) {
        if (!isEditable(input)) return;
        if (lastPostings && Array.isArray(lastPostings.rows) && lastPostings.rows.length) {
            input.dataset.lastPostings = JSON.stringify(lastPostings);
        } else {
            delete input.dataset.lastPostings;
        }
    }

    /**
     * The value the pool saves for one side: type + number ("K1234"), which insertDoc.php stores as
     * d_type/k_type and debet/kredit. Empty when there is no account.
     * @param {string} prefix  Row id prefix, e.g. 'row_141_'.
     * @param {string} side    'Debet' or 'Kredit'.
     * @returns {string}
     */
    window.poolAccountValue = function (prefix, side) {
        const accountInput = document.getElementById(prefix + side);
        const typeInput = document.getElementById(prefix + side + 'Type');
        const kontonr = accountInput ? accountInput.value.trim() : '';
        if (kontonr === '') return '';
        if (PREFIXED.test(kontonr)) return kontonr.toUpperCase();
        // Search text that was never turned into an account stays as typed, without a type in front ("tele", not "Ftele")
        if (!/^\d+$/.test(kontonr)) return kontonr;
        let type = typeInput ? typeInput.value.trim().toUpperCase() : '';
        if (TYPES.indexOf(type) === -1) type = 'F';
        return type + kontonr;
    };

    // Capture phase: the prefix is split before the lookup panel's own input handler searches
    document.addEventListener('input', function (e) {
        const el = e.target;
        if (!isEditable(el)) return;
        if (el.classList.contains('pool-account-no')) {
            splitPrefix(el);
        } else if (el.classList.contains('pool-account-type')) {
            const type = el.value.trim().toUpperCase();
            el.value = TYPES.indexOf(type) === -1 ? '' : type;
        }
    }, true);

    /** "u/m" and the VAT code fields of one row, as the journal's handleVatExempt() and handleVatChange() do it. */
    function vatFieldsOf(prefix) {
        return [document.getElementById(prefix + 'DebetVat'), document.getElementById(prefix + 'KreditVat')].filter(Boolean);
    }

    document.addEventListener('change', function (e) {
        const el = e.target;
        if (el && el.id && /_Momsfri$/.test(el.id) && !el.disabled) {
            vatFieldsOf(el.id.replace(/Momsfri$/, '')).forEach(function (select) {
                select.value = el.checked ? '' : (select.dataset.accountVat || '');
            });
            return;
        }
        if (el && el.classList && el.classList.contains('pool-account-vatcode') && el.value !== '') {
            const exempt = document.getElementById(el.id.replace(/(Debet|Kredit)Vat$/, 'Momsfri'));
            if (exempt) exempt.checked = false;
            return;
        }
    }, true);

    document.addEventListener('change', function (e) {
        const el = e.target;
        if (!isEditable(el)) return;
        if (el.classList.contains('pool-account-no')) {
            splitPrefix(el);
            refreshSide(el);
        } else if (el.classList.contains('pool-account-type')) {
            if (el.value === '') el.value = 'F';
            const accountInput = document.getElementById(el.id.replace(/Type$/, ''));
            if (accountInput) refreshSide(accountInput);
        }
    }, true);
})();
