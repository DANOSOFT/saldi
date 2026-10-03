// --- javascript/docPoolAccounts.js --- ver 5.0.0 --- 2026-10-03 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-714 Created: Debet/Kredit in the pool's journal lines.
//                The lookup panel itself is accountAutocomplete.js, which binds to the debe/kred/d_ty/k_ty names the rows carry.
//                This file adds the type prefix ("K1234" sets type K and account 1234), the account's name and VAT code under the field, and the "sidste 5 posteringer" the other field's panel offers.
//                Needs window.saldiPoolAccounts.
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
            showName(fields, '', '');
            setLastPostings(fields.other, null);
            return;
        }
        timers[accountInput.id] = setTimeout(function () {
            const url = cfg.lookupUrl + '?art=' + encodeURIComponent(type) + '&kontonr=' + encodeURIComponent(kontonr) +
                '&dk=' + (fields.side === 'Debet' ? 'K' : 'D') + '&kladde_id=' + encodeURIComponent(cfg.kladdeId || 0);
            fetch(url, { credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : {}; })
                .then(function (data) {
                    // A newer value may have been typed while this one was looked up
                    if (accountInput.value.trim() !== kontonr || ((fields.type && fields.type.value) || 'F') !== type) return;
                    showName(fields, data.name || '', data.moms || '');
                    setLastPostings(fields.other, data.lastPostings);
                })
                .catch(function () { showName(fields, '', ''); });
        }, 150);
    }

    function showName(fields, name, vat) {
        if (!fields.name) return;
        fields.name.textContent = name;
        fields.name.title = name;
        if (vat) {
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
