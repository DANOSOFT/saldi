
// 20260907 CDX/LH Preserve D/K/F account types when selecting a historical counter-account.
//                  Handle each keyboard selection once, without bubbling into a second move.
// 20260929 CL/SZ SD-698: Card button on every account line (account lookup, recent postings, Åbne Poster) when the page
//                  sets window.saldiAccountCard (kassekladde): debtor -> debitorkort, creditor -> kreditorkort,
//                  finance -> kontospec. Unsaved journal edits ask for confirmation before leaving.
// 20261001 CL/SZ SD-698: Ctrl/Cmd+Enter on the selected popup line opens its card, while plain Enter still selects it.
//                  Added JSDoc to the functions this change touches.
// 20261002 MJ SST-814 Don't ask accountSearch.php for a debitor/kreditor term shorter than
//                  three characters: the endpoint now refuses it, because a trigram index
//                  cannot match below three characters. finance is not gated - kontoplan is
//                  small and its account numbers are legitimately short.
// 20261002 MJ SST-814 Review: drop a search response that comes back for a value the field
//                  no longer holds. The race predates this ticket, but the new minimum made
//                  it persistent - a value falling below it sends no further request, so
//                  nothing arrived afterwards to replace a stale dropdown.
// 20261003 CL/SZ SD-698: The card opens in a separate, reused tab (saldiKort) instead of leaving the journal, so unsaved
//                  journal edits are kept; the card's Tilbage closes the tab. A blocked popup falls back to the old ask-and-leave.
// 20261003 CL/SZ SD-716 window.closeAccountAutocomplete closes the panel, so Ctrl + arrow navigation (fieldNavigation.js) can move on from an open panel.
// 20261003 CL/SZ SD-720 A search answer that arrives after the user left the field no longer opens the panel and takes the focus back.
// 20261003 CL/SZ SD-721 A kreditor search that finds nothing offers "Opret kreditor", which opens kreditorFromCvr.js's dialog and puts the new kreditor in the field.
// 20261005 CL/SZ SD-714 A Tab or Ctrl + arrow only keeps the panel closed for the focus change it makes. Before, a Tab that landed on a field without
//                  the panel (Beløb, the type field) kept it closed for the next click into Debet or Kredit too.
// 20261006 CL/SZ SD-720 The same for the invoice-number (Åbne Poster) and amount searches: a late answer put the cursor back in Fakturanr.
//                while the user typed the amount, so the rest of the amount landed in the invoice number.
// 20261006 CL/SZ SD-720 The same for the invoice-number (Åbne Poster) and amount searches: a late answer put the cursor back in Fakturanr.
//                while the user typed the amount, so the rest of the amount landed in the invoice number.
// 20261006 CL/SZ SD-714 window.focusAccountQuietly(input) puts the cursor in a field without opening the panel (the pool's new document), and a
//                  click into a field that already has the cursor opens the panel, as the first click would.
// 20261005 CL/SZ SD-714 A click in a document viewer (the pool's PDF) closes the panel; the click never reached the page, so the panel stayed open.
// 20261015 CL/SZ SD-720 (CodeRabbit) A failed search for a field the user has left no longer closes the panel another field has opened.
// 20261019 CL/SZ SD-721 A kreditor made with "Opret kreditor" advances the search token like any other selection (SST-814).
// 20261015 CL/SZ SD-720 (CodeRabbit) A failed search for a field the user has left no longer closes the panel another field has opened.
// 20261023 CL/LH An Åbne Poster pick under Fakturanr. no longer overwrites what the line already holds. It writes only an empty field,
//                  or one an earlier pick wrote that nobody has changed since. A filled Beløb, Valuta or debitor/kreditor account that
//                  differs from the invoice is kept and marked, and a notice above the line offers "Brug fakturaens værdier"; a filled
//                  Beskrivelse (a bank import's text) is kept as it is. "Fortryd" in the notice puts the line back. The momskode is no
//                  longer recalculated on both sides after a pick, only on the side whose account the pick wrote (its change event).
//                  The invoice search ignores an account or amount an earlier pick wrote, so a second pick can find another debitor's invoice.
//                  updateVatFieldForRow() drops a finance VAT lookup still in flight when the field no longer holds a finance account.
(function () {
    'use strict'; 

    const CONFIG = {
        minSearchLength: 0,
        // SST-814 review: accountSearch.php refuses a one- or two-character
        // debitor/kreditor search, because a trigram index cannot match below three
        // characters and adresser is large. Only those two types are gated - a finance
        // search of kontoplan is small and its account numbers are legitimately short.
        minAccountSearchLength: 3,
        debounceDelay: 200,
        maxResults: 50
    };

    function getTrans() {
        return window.saldiTranslations || {};
    }

    // Per-page panel-section config (set by kassekladde.php). Pages that don't define
    // window.saldiAutocompleteOptions (bank import, document pool, order autocomplete)
    // keep the default behaviour: both sections shown.
    function getPanelOptions() {
        const opts = window.saldiAutocompleteOptions || {};
        return {
            showLastPostings: opts.showLastPostings !== false,
            showAccountLookup: opts.showAccountLookup !== false
        };
    }

    let activeDropdown = null;
    let activeInput = null;
    let debounceTimer = null;
    // Bumped on every keystroke, including one that the length gate refuses. A response
    // that comes back carrying an older number is for a value the field no longer holds,
    // so it is dropped rather than painted over the current state.
    let searchSeq = 0;
    let dropdownContainer = null;
    let selectionMade = false;
    let currentPage = 1;
    let currentSearchValue = '';
    let focusViaKeyboardNav = false;


    function getDropdownContainer() {
        if (!dropdownContainer) {
            dropdownContainer = document.createElement('div');
            dropdownContainer.id = 'account-autocomplete-container';
            dropdownContainer.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 99999;';
            document.body.appendChild(dropdownContainer);
        }
        return dropdownContainer;
    }


    function initAccountAutocomplete() {
        const debeFields = document.querySelectorAll('input[name^="debe"]');

        debeFields.forEach(function (input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'debet');
                input.autocompleteInitialized = true;
            }
        });

        const kredFields = document.querySelectorAll('input[name^="kred"]');

        kredFields.forEach(function (input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'kredit');
                input.autocompleteInitialized = true;
            }
        });

        const faktFields = document.querySelectorAll('input[name^="fakt"]');
        faktFields.forEach(function (input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'faktura');
                input.autocompleteInitialized = true;
            }
        });

        const afdFields = document.querySelectorAll('input[name^="afd_"]');
        afdFields.forEach(function (input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'department');
                input.autocompleteInitialized = true;
            }
        });

        const medaFields = document.querySelectorAll('input[name^="meda"]');
        medaFields.forEach(function (input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'employee');
                input.autocompleteInitialized = true;
            }
        });

        const valuFields = document.querySelectorAll('input[name^="valu"]');
        valuFields.forEach(function (input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'currency');
                input.autocompleteInitialized = true;
            }
        });

        initVatFieldSync();

        // const beloFields = document.querySelectorAll('input[name^="belo"]');
        // beloFields.forEach(function(input) {
        //     if (!input.autocompleteInitialized) {
        //         setupAutocomplete(input, 'amount');
        //         input.autocompleteInitialized = true;
        //     }
        // });

        document.addEventListener('mousedown', function (e) {
            // A click is never keyboard navigation: a Tab whose focus landed on a field without the panel must not stop this click from opening it
            focusViaKeyboardNav = false;
            if (activeDropdown) {
                if (activeDropdown.contains(e.target)) {
                    return;
                }
                if (activeInput && (e.target === activeInput || activeInput.contains(e.target))) {
                    return;
                }
                closeDropdown();
            }
        });

        // A click in a document viewer (the pool's PDF) never reaches the mousedown above; the focus moving into it closes the panel
        window.addEventListener('blur', function () {
            setTimeout(function () {
                const el = document.activeElement;
                if (activeDropdown && el && /^(IFRAME|EMBED|OBJECT)$/.test(el.tagName)) closeDropdown();
            }, 0);
        });

        document.addEventListener('keydown', function (e) {
            // Track keyboard navigation that moves focus (Tab, Shift+Tab, Ctrl+Arrow)
            // so the focus handler can suppress the dropdown opening
            if (e.key === 'Tab' || (e.ctrlKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown' || e.key === 'ArrowLeft' || e.key === 'ArrowRight'))) {
                focusViaKeyboardNav = true;
                // Only for the focus change this key makes: it happens before this timer, wherever the focus lands
                setTimeout(function () { focusViaKeyboardNav = false; }, 0);
            }

            if (activeDropdown && !e.defaultPrevented) {
                handleKeyboardNavigation(e);
            }
        });

    }


    /**
     * Attach the lookup popup to one journal field: its dropdown, the mouse handling on it
     * (close, paging, card button, line selection) and the field's keyboard navigation.
     * @param {HTMLInputElement} input      The journal field (debe/kred/fakt/... + line number).
     * @param {string}           fieldType  Which lookup the field uses, e.g. 'debet', 'kredit', 'faktura'.
     */
    function setupAutocomplete(input, fieldType) {
        if (fieldType === 'amount') return;
        // Skip if already initialized
        if (input.autocompleteInitialized) {
            return;
        }

        // Create dropdown in the global container (doesn't affect form structure)
        const dropdown = document.createElement('div');
        dropdown.className = 'account-autocomplete-dropdown';
        dropdown.style.display = 'none';
        dropdown.style.pointerEvents = 'auto';
        getDropdownContainer().appendChild(dropdown);

        dropdown.addEventListener('mousedown', function (e) {
            if (e.target.closest('.account-autocomplete-close-btn')) {
                e.preventDefault();
                e.stopPropagation();
                selectionMade = true;
                // Supersede any request already in flight: the dropdown is closing, and a
                // response that lands afterwards must not reopen it.
                searchSeq++;
                closeDropdown();
                if (input) {
                    input.focus();
                }
                setTimeout(function () {
                    selectionMade = false;
                }, 100);
                return;
            }

            const pageBtn = e.target.closest('.account-autocomplete-page-btn');
            if (pageBtn) {
                e.preventDefault();
                e.stopPropagation();
                const page = parseInt(pageBtn.dataset.page, 10);
                if (page > 0) {
                    // Use the current input value for pagination
                    performSearchWithValue(input, input.value, page);
                }
                return;
            }

            const createBtn = e.target.closest('.account-autocomplete-create-kreditor');
            if (createBtn) {
                // SD-721: the dialog; the new kreditor goes into the field the search came from
                e.preventDefault();
                e.stopPropagation();
                const search = createBtn.dataset.search || '';
                const digits = search.replace(/\D/g, '');
                closeDropdown();
                window.kreditorFromCvr.openDialog({
                    company: /^\d{8}$/.test(digits) && digits.length === search.replace(/\s/g, '').length ? { cvrnr: digits } : { firmanavn: search },
                    returnFocus: input,
                    onCreated: function (kreditor) {
                        selectionMade = true;
                        // Supersede any request already in flight, as a selection does (SST-814)
                        searchSeq++;
                        input.value = kreditor.kontonr;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        input.focus();
                        setTimeout(function () { selectionMade = false; }, 100);
                    }
                });
                return;
            }

            const cardBtn = e.target.closest('.account-autocomplete-card-btn');
            if (cardBtn) {
                // opens the card instead of selecting the line
                e.preventDefault();
                e.stopPropagation();
                openAccountCard(cardBtn.dataset.cardArt, cardBtn.dataset.cardKontonr);
                return;
            }

            const item = e.target.closest('.account-autocomplete-item');
            if (item) {
                e.preventDefault();
                e.stopPropagation();
                if (input.fieldType === 'faktura' || input.fieldType === 'amount') {
                    selectInvoiceOrAmount(input, item);
                } else if (item.dataset.kontonr) {
                    selectAccount(input, item);
                }
                return;
            }

            e.preventDefault();
        });

        input.autocompleteDropdown = dropdown;
        input.fieldType = fieldType;

        const rowMatch = input.name.match(/\d+$/);
        input.rowNumber = rowMatch ? rowMatch[0] : '';

        input.addEventListener('input', function (e) {
            if (selectionMade) {
                return;
            }
            handleInputWithValue(this, this.value);
        });

        input.addEventListener('focus', function (e) {
            if (selectionMade) {
                return;
            }
            // Don't open dropdown when focus is gained via Tab, Shift+Tab, or Ctrl+Arrow navigation
            if (focusViaKeyboardNav) {
                focusViaKeyboardNav = false;
                return;
            }
            // Show dropdown with current input value as search
            handleInputWithValue(this, this.value);
        });

        // The cursor already in the field (Escape, or a quiet focus): a click opens the panel the focus did not
        input.addEventListener('click', function () {
            if (selectionMade || (activeDropdown && activeInput === this)) return;
            handleInputWithValue(this, this.value);
        });

        input.addEventListener('blur', function (e) {
            // console.log('blur', e)

        });

        // Handle keyboard navigation while typing in the input field
        input.addEventListener('keydown', function (e) {
            if (!activeDropdown || activeInput !== this) {
                return;
            }

            const items = activeDropdown.querySelectorAll('.account-autocomplete-item');
            if (items.length === 0) return;

            const selected = activeDropdown.querySelector('.account-autocomplete-item.selected');
            let selectedIndex = -1;

            if (selected) {
                for (let i = 0; i < items.length; i++) {
                    if (items[i] === selected) {
                        selectedIndex = i;
                        break;
                    }
                }
            }

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (selectedIndex < items.length - 1) {
                        selectItemByIndex(items, selectedIndex + 1);
                    } else if (selectedIndex === -1 && items.length > 0) {
                        selectItemByIndex(items, 0);
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (selectedIndex > 0) {
                        selectItemByIndex(items, selectedIndex - 1);
                    }
                    break;

                case 'Enter':
                    if (selected && (e.ctrlKey || e.metaKey) && openSelectedAccountCard(selected)) {
                        // Ctrl/Cmd+Enter opens the selected line's card; plain Enter selects it
                        e.preventDefault();
                        break;
                    }
                    if (selected) {
                        e.preventDefault();
                        if (this.fieldType === 'faktura' || this.fieldType === 'amount') {
                            selectInvoiceOrAmount(this, selected);
                        } else {
                            selectAccount(this, selected);
                        }
                    }
                    break;

                case 'Escape':
                    e.preventDefault();
                    closeDropdown();
                    break;

                case 'Tab':
                    closeDropdown();
                    break;
            }
        });
    }


    function initVatFieldSync() {
        bindVatFieldListeners('debe', 'd_ty', 'dvat', 'debet');
        bindVatFieldListeners('kred', 'k_ty', 'kvat', 'kredit');
    }

    function bindVatFieldListeners(accountPrefix, typePrefix, vatPrefix, side) {
        const accountFields = document.querySelectorAll('input[name^="' + accountPrefix + '"]');
        accountFields.forEach(function (input) {
            if (input.vatSyncInitialized) {
                return;
            }

            const rowNum = getRowNumber(input.name);
            input.addEventListener('change', function () {
                updateVatFieldForRow(rowNum, side);
            });
            input.addEventListener('blur', function () {
                updateVatFieldForRow(rowNum, side);
            });
            input.vatSyncInitialized = true;
        });

        const typeFields = document.querySelectorAll('input[name^="' + typePrefix + '"]');
        typeFields.forEach(function (input) {
            if (input.vatTypeSyncInitialized) {
                return;
            }

            const rowNum = getRowNumber(input.name);
            input.addEventListener('change', function () {
                updateVatFieldForRow(rowNum, side);
            });
            input.vatTypeSyncInitialized = true;
        });

        const vatFields = document.querySelectorAll('input[name^="' + vatPrefix + '"]');
        vatFields.forEach(function (input) {
            if (!input.hasAttribute('readonly')) {
                input.setAttribute('readonly', 'readonly');
            }
        });
    }

    function getRowNumber(fieldName) {
        const match = fieldName ? fieldName.match(/\d+$/) : null;
        return match ? match[0] : '';
    }

    function getVatBasePath() {
        if (window.location.pathname.includes('/finans/')) {
            return 'kassekladde_includes/accountSearch.php';
        }
        if (window.location.pathname.includes('/includes/') || window.location.pathname.includes('/bank_integration/')) {
            return '../finans/kassekladde_includes/accountSearch.php';
        }
        return 'finans/kassekladde_includes/accountSearch.php';
    }

    function getVatField(rowNum, side) {
        const prefix = side === 'debet' ? 'dvat' : 'kvat';
        return document.querySelector('select[name="' + prefix + rowNum + '"], input[name="' + prefix + rowNum + '"]');
    }

    function setVatFieldValue(rowNum, side, value) {
        const vatField = getVatField(rowNum, side);
        if (!vatField) {
            return;
        }

        const normalizedValue = (value || '').toString().trim();

        if (vatField.tagName === 'SELECT') {
            // Try exact match first, then case-insensitive
            let matched = '';
            for (var i = 0; i < vatField.options.length; i++) {
                if (vatField.options[i].value === normalizedValue) {
                    matched = vatField.options[i].value;
                    break;
                }
                if (vatField.options[i].value.toUpperCase() === normalizedValue.toUpperCase()) {
                    matched = vatField.options[i].value;
                }
            }
            vatField.value = matched;
            return;
        }

        vatField.value = normalizedValue;
    }

    function updateVatFieldForRow(rowNum, side, options) {
        const accountPrefix = side === 'debet' ? 'debe' : 'kred';
        const typePrefix = side === 'debet' ? 'd_ty' : 'k_ty';
        const accountField = document.querySelector('input[name="' + accountPrefix + rowNum + '"]');
        const typeField = document.querySelector('input[name="' + typePrefix + rowNum + '"]');
        const vatField = getVatField(rowNum, side);

        if (!accountField || !vatField) {
            return Promise.resolve('');
        }

        const accountNo = accountField.value.trim();
        const accountType = typeField ? typeField.value.toUpperCase().trim() : '';
        const isFinanceAccount = !accountType || accountType === 'F';

        if (!accountNo || !isFinanceAccount) {
            // A lookup still on its way is for a value the field no longer holds
            vatField.dataset.lookupKey = '';
            setVatFieldValue(rowNum, side, '');
            return Promise.resolve('');
        }

        if (options && Object.prototype.hasOwnProperty.call(options, 'vatCode')) {
            vatField.dataset.lookupKey = '';
            setVatFieldValue(rowNum, side, options.vatCode);
            return Promise.resolve(options.vatCode || '');
        }

        const lookupKey = side + ':' + accountNo;
        vatField.dataset.lookupKey = lookupKey;

        return fetch(getVatBasePath() + '?type=finance&exact=1&search=' + encodeURIComponent(accountNo))
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                const results = data.results || [];
                const vatCode = results.length > 0 ? (results[0].moms || '') : '';

                if (vatField.dataset.lookupKey === lookupKey) {
                    setVatFieldValue(rowNum, side, vatCode);
                }

                return vatCode;
            })
            .catch(function () {
                if (vatField.dataset.lookupKey === lookupKey) {
                    setVatFieldValue(rowNum, side, '');
                }
                return '';
            });
    }


    function positionDropdown(input, dropdown) {
        const rect = input.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;

        dropdown.style.visibility = 'hidden';
        dropdown.style.display = 'block';
        const dropdownRect = dropdown.getBoundingClientRect();
        const dropdownHeight = dropdownRect.height || 400;
        const dropdownWidth = dropdownRect.width || 450;
        dropdown.style.visibility = 'visible';

        let top = rect.bottom + 2;
        let left = rect.left;

        if (top + dropdownHeight > viewportHeight) {
            top = rect.top - dropdownHeight - 2;
            if (top < 0) {
                top = 10;
            }
        }

        if (left + dropdownWidth > viewportWidth) {
            left = viewportWidth - dropdownWidth - 10;
        }

        if (left < 0) {
            left = 10;
        }

        dropdown.style.top = top + 'px';
        dropdown.style.left = left + 'px';
    }

    function handleInput(input) {
        if (selectionMade) {
            return;
        }

        clearTimeout(debounceTimer);
        searchSeq++;
        debounceTimer = setTimeout(function () {
            performSearch(input);
        }, CONFIG.debounceDelay);
    }

    /**
     * Handle input with a specific search value (from the input field itself)
     */
    function handleInputWithValue(input, searchValue) {
        if (selectionMade) {
            return;
        }

        clearTimeout(debounceTimer);
        searchSeq++;
        debounceTimer = setTimeout(function () {
            performSearchWithValue(input, searchValue || '', 1);
        }, CONFIG.debounceDelay);
    }


    function performSearch(input) {
        currentPage = 1;
        currentSearchValue = '';
        performSearchWithValue(input, '', 1);
    }


    function performSearchWithValue(input, searchValue, page) {
        searchValue = searchValue.trim();
        page = page || 1;
        currentSearchValue = searchValue;
        currentPage = page;
        const dropdown = input.autocompleteDropdown;

        let searchType = 'finance';
        const rowNum = input.rowNumber;

        if (input.fieldType === 'faktura') {
            performInvoiceSearch(input, searchValue, page);
            return;
        }

        if (input.fieldType === 'department') {
            performDepartmentSearch(input, searchValue, page);
            return;
        }

        if (input.fieldType === 'employee') {
            performEmployeeSearch(input, searchValue, page);
            return;
        }

        if (input.fieldType === 'currency') {
            performCurrencySearch(input, searchValue, page);
            return;
        }

        if (input.fieldType === 'amount') {
            performAmountSearch(input, searchValue, page);
            return;
        }

        if (input.fieldType === 'debet') {
            const dTypeField = document.querySelector('input[name="d_ty' + rowNum + '"]');
            if (dTypeField) {
                const dType = dTypeField.value.toUpperCase().trim();
                if (dType === 'D') searchType = 'debitor';
                else if (dType === 'K') searchType = 'kreditor';
            }
        } else if (input.fieldType === 'kredit') {
            const kTypeField = document.querySelector('input[name="k_ty' + rowNum + '"]');
            if (kTypeField) {
                const kType = kTypeField.value.toUpperCase().trim();
                if (kType === 'D') searchType = 'debitor';
                else if (kType === 'K') searchType = 'kreditor';
            }
        }

        const panelOptions = getPanelOptions();
        if (!panelOptions.showLastPostings && !panelOptions.showAccountLookup) {
            closeDropdown();
            return;
        }
        if (!panelOptions.showAccountLookup) {
            // Lookup section deselected: skip the server search and show suggestions only
            renderDropdown(input, [], searchType, searchValue, null);
            return;
        }

        // Matches the endpoint's own minimum, so no request is sent that it would refuse.
        if ((searchType === 'debitor' || searchType === 'kreditor')
                && searchValue.length > 0
                && searchValue.length < CONFIG.minAccountSearchLength) {
            renderDropdown(input, [], searchType, searchValue, null);
            return;
        }

        let basePath = '';
        if (window.location.pathname.includes('/finans/')) {
            basePath = 'kassekladde_includes/accountSearch.php';
        } else if (window.location.pathname.includes('/includes/') || window.location.pathname.includes('/bank_integration/')) {
            basePath = '../finans/kassekladde_includes/accountSearch.php';
        } else {
            basePath = 'finans/kassekladde_includes/accountSearch.php';
        }
        // SST-814 This dropdown shows "viser 1-50 af N" and page buttons, so it is the one
        // caller that needs the total. accountSearch.php only runs the extra COUNT(*) when
        // asked, because it costs as much again as the search itself.
        const url = basePath + '?search=' +
            encodeURIComponent(searchValue) +
            '&type=' + searchType +
            '&count=1' +
            '&page=' + page;

        const seq = searchSeq;
        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text();
            })
            .then(function (text) {
                if (seq !== searchSeq) return;
                try {
                    const data = JSON.parse(text);
                    const results = data.results || data;
                    const pagination = data.pagination || { page: 1, total: results.length, hasMore: false };
                    // A late answer for a field the user has left must not open the panel and take the focus back (SD-720)
                    if (document.activeElement !== input) return;
                    renderDropdown(input, results, searchType, searchValue, pagination);
                } catch (e) {
                    console.error('JSON parse error:', e);
                }
            })
            .catch(function (error) {
                if (seq !== searchSeq) return;
                if (document.activeElement !== input) return;
                console.error('Account search error:', error);
                closeDropdown();
            });
    }


    function getLastPostings(input) {
        if (!input || !input.dataset || !input.dataset.lastPostings) {
            return { heading: '', rows: [] };
        }

        try {
            const data = JSON.parse(input.dataset.lastPostings);
            if (!data || !Array.isArray(data.rows)) {
                return { heading: '', rows: [] };
            }
            return data;
        } catch (e) {
            return { heading: '', rows: [] };
        }
    }


    function getAccountSectionHeading(searchType) {
        if (searchType === 'debitor') {
            return 'Debitorer';
        }
        if (searchType === 'kreditor') {
            return 'Kreditorer';
        }
        return 'Kontoplan';
    }


    function getAccountDropdownTitle(searchType, trans) {
        if (searchType === 'debitor') {
            return trans.selectDebtor;
        }
        if (searchType === 'kreditor') {
            return trans.selectCreditor;
        }
        return trans.selectAccount;
    }


    function renderAccountTableHeader(searchType, trans) {
        if (searchType === 'finance') {
            return '<thead><tr>' +
                '<th style="width:70px;">' + trans.accountNo + '</th>' +
                '<th>' + trans.description + '</th>' +
                '<th style="width:45px;">' + trans.vat + '</th>' +
                '<th style="width:50px;">' + trans.shortcut + '</th>' +
                '<th style="width:90px;text-align:right;">' + trans.balance + '</th>' +
                '</tr></thead>';
        }

        return '<thead><tr>' +
            '<th style="width:80px;">' + trans.accountNo + '</th>' +
            '<th>' + trans.companyName + '</th>' +
            '</tr></thead>';
    }


    /**
     * Rows for the "recent postings" section at the top of the account popup.
     * @param {{heading: string, rows: Array<Object>}} lastPostings  Recent counter-accounts for the line.
     * @param {boolean} includeAccountHeading  Add the heading for the account list that follows.
     * @param {number}  columnCount            Columns in the popup table.
     * @param {string}  searchType             'finance', 'debitor' or 'kreditor'.
     * @returns {string}  Table row HTML, or '' when there are no recent postings.
     */
    function renderLastPostingsRows(lastPostings, includeAccountHeading, columnCount, searchType) {
        if (!lastPostings || !lastPostings.rows || lastPostings.rows.length === 0) {
            return '';
        }

        columnCount = columnCount || 5;

        let html = '<tr class="account-autocomplete-category account-autocomplete-last-postings-heading">' +
            '<td colspan="' + columnCount + '"><strong>' + escapeHtml(lastPostings.heading || 'Tidligere posteringer') + '</strong></td>' +
            '</tr>';

        lastPostings.rows.forEach(function (item, index) {
            const description = [
                item.dato ? item.dato : '',
                item.bilag ? 'Bilag ' + item.bilag : '',
                item.tekst ? item.tekst : ''
            ].filter(Boolean).join(' - ');

            html += '<tr class="account-autocomplete-item account-autocomplete-last-posting-item"' +
                ' data-kontonr="' + escapeHtml(item.kontonr || '') + '"' +
                ' data-account-type="' + escapeHtml(item.art || '') + '"' +
                ' data-index="last-' + index + '">' +
                '<td>' + escapeHtml((item.art ? item.art + ' ' : '') + (item.kontonr || '')) + renderCardButton(item.art, item.kontonr) + '</td>' +
                '<td title="' + escapeHtml(description) + '">' + escapeHtml(description) + '</td>';

            for (let i = 2; i < columnCount; i++) {
                html += '<td></td>';
            }

            html += '</tr>';
        });

        if (includeAccountHeading) {
            html += '<tr class="account-autocomplete-category account-autocomplete-account-heading">' +
                '<td colspan="' + columnCount + '"><strong>' + getAccountSectionHeading(searchType) + '</strong></td>' +
                '</tr>';
        }

        return html;
    }


   function performInvoiceSearch(input, searchValue, page) {
    searchValue = searchValue.trim();
    page = page || 1;
    const dropdown = input.autocompleteDropdown;
    const rowNum = input.rowNumber;
   
        let accountNr = '';
        let accountType = '';

        // An account or amount an earlier pick wrote is not the user's: it must not narrow the search for the right invoice
        const dTypeField = document.querySelector('input[name="d_ty' + rowNum + '"]');
        const debeField = document.querySelector('input[name="debe' + rowNum + '"]');
        if (dTypeField && debeField && !isAutofilled(debeField)) {
            const dType = dTypeField.value.toUpperCase().trim();
            if (dType === 'D' || dType === 'K') {
                accountType = dType;
                accountNr = debeField.value.trim();
            }
        }

        if (!accountNr) {
            const kTypeField = document.querySelector('input[name="k_ty' + rowNum + '"]');
            const kredField = document.querySelector('input[name="kred' + rowNum + '"]');
            if (kTypeField && kredField && !isAutofilled(kredField)) {
                const kType = kTypeField.value.toUpperCase().trim();
                if (kType === 'D' || kType === 'K') {
                    accountType = kType;
                    accountNr = kredField.value.trim();
                }
            }
        }

       let basePath = '';
        if (window.location.pathname.includes('/finans/')) {
            basePath = 'kassekladde_includes/invoiceSearch.php';
        } else if (window.location.pathname.includes('/includes/')) {
            basePath = '../finans/kassekladde_includes/invoiceSearch.php';
        } else {
            basePath = 'finans/kassekladde_includes/invoiceSearch.php';
        }

        // Read belo field and convert from Danish format (4.999,00 → 4999.00)
        const beloFieldForSearch = document.querySelector('input[name="belo' + rowNum + '"]');
        const rawAmount = beloFieldForSearch && !isAutofilled(beloFieldForSearch) ? beloFieldForSearch.value.trim() : '';
        const currentAmount = rawAmount.replace(/\./g, '').replace(',', '.');
       
        const url = basePath + '?search=' +
            encodeURIComponent(searchValue) +
            '&account=' + encodeURIComponent(accountNr) +
            '&accountType=' + encodeURIComponent(accountType) +
            '&currentAmount=' + encodeURIComponent(currentAmount) +
            '&page=' + page;

        const seq = searchSeq;
        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text();
            })
            .then(function (text) {
                if (seq !== searchSeq) return;
                try {
                    const data = JSON.parse(text);
                    const results = data.results || data;
                    const pagination = data.pagination || { page: 1, total: results.length, hasMore: false };
                    // The same rule as the account search (SD-720): an answer for a field the user has left takes nothing back
                    if (document.activeElement !== input) return;
                    renderInvoiceDropdown(input, results, searchValue, pagination);
                } catch (e) {
                    console.error('JSON parse error:', e);
                }
            })
            .catch(function (error) {
                if (seq !== searchSeq) return;
                if (document.activeElement !== input) return;
                console.error('Invoice search error:', error);
                closeDropdown();
            });
    }


    function performDepartmentSearch(input, searchValue, page) {
        searchValue = searchValue.trim();
        const dropdown = input.autocompleteDropdown;

        let basePath = '';
        if (window.location.pathname.includes('/finans/')) {
            basePath = 'kassekladde_includes/departmentSearch.php';
        } else if (window.location.pathname.includes('/includes/')) {
            basePath = '../finans/kassekladde_includes/departmentSearch.php';
        } else {
            basePath = 'finans/kassekladde_includes/departmentSearch.php';
        }

        const url = basePath + '?search=' + encodeURIComponent(searchValue);

        const seq = searchSeq;
        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (seq !== searchSeq) return;
                const results = data.results || [];
                renderSimpleDropdown(input, results, 'department', searchValue);
            })
            .catch(function (error) {
                if (seq !== searchSeq) return;
                console.error('Department search error:', error);
                closeDropdown();
            });
    }

    /**
     * Perform AJAX search for employees
     */
    function performEmployeeSearch(input, searchValue, page) {
        searchValue = searchValue.trim();
        const dropdown = input.autocompleteDropdown;

        let basePath = '';
        if (window.location.pathname.includes('/finans/')) {
            basePath = 'kassekladde_includes/employeeSearch.php';
        } else if (window.location.pathname.includes('/includes/')) {
            basePath = '../finans/kassekladde_includes/employeeSearch.php';
        } else {
            basePath = 'finans/kassekladde_includes/employeeSearch.php';
        }

        const url = basePath + '?search=' + encodeURIComponent(searchValue);

        const seq = searchSeq;
        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (seq !== searchSeq) return;
                const results = data.results || [];
                renderSimpleDropdown(input, results, 'employee', searchValue);
            })
            .catch(function (error) {
                if (seq !== searchSeq) return;
                console.error('Employee search error:', error);
                closeDropdown();
            });
    }


    function performCurrencySearch(input, searchValue, page) {
        searchValue = searchValue.trim();
        const dropdown = input.autocompleteDropdown;

        let basePath = '';
        if (window.location.pathname.includes('/finans/')) {
            basePath = 'kassekladde_includes/currencySearch.php';
        } else if (window.location.pathname.includes('/includes/')) {
            basePath = '../finans/kassekladde_includes/currencySearch.php';
        } else {
            basePath = 'finans/kassekladde_includes/currencySearch.php';
        }

        const url = basePath + '?search=' + encodeURIComponent(searchValue);

        const seq = searchSeq;
        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (seq !== searchSeq) return;
                const results = data.results || [];
                renderSimpleDropdown(input, results, 'currency', searchValue);
            })
            .catch(function (error) {
                if (seq !== searchSeq) return;
                console.error('Currency search error:', error);
                closeDropdown();
            });
    }

    function performAmountSearch(input, searchValue, page) {
        searchValue = searchValue.trim();
        const dropdown = input.autocompleteDropdown;
        const rowNum = input.rowNumber;

        let accountNr = '';
        let accountType = '';
        let invoiceNr = '';

        // Check debet field first
        const dTypeField = document.querySelector('input[name="d_ty' + rowNum + '"]');
        const debeField = document.querySelector('input[name="debe' + rowNum + '"]');
        if (dTypeField && debeField) {
            const dType = dTypeField.value.toUpperCase().trim();
            if (dType === 'D' || dType === 'K') {
                accountType = dType;
                accountNr = debeField.value.trim();
            }
        }

        // If not found in debet, check kredit
        if (!accountNr) {
            const kTypeField = document.querySelector('input[name="k_ty' + rowNum + '"]');
            const kredField = document.querySelector('input[name="kred' + rowNum + '"]');
            if (kTypeField && kredField) {
                const kType = kTypeField.value.toUpperCase().trim();
                if (kType === 'D' || kType === 'K') {
                    accountType = kType;
                    accountNr = kredField.value.trim();
                }
            }
        }

        const faktField = document.querySelector('input[name="fakt' + rowNum + '"]');
        if (faktField) {
            invoiceNr = faktField.value.trim();
        }

        let basePath = '';
        if (window.location.pathname.includes('/finans/')) {
            basePath = 'kassekladde_includes/amountSearch.php';
        } else if (window.location.pathname.includes('/includes/')) {
            basePath = '../finans/kassekladde_includes/amountSearch.php';
        } else {
            basePath = 'finans/kassekladde_includes/amountSearch.php';
        }

        // Read the current amount value from the belo field on the same row
        const beloFieldForSearch = document.querySelector('input[name="belo' + rowNum + '"]');
        // Danish format: 7.500,2 → remove thousands dot → replace decimal comma → 7500.2
        const rawAmount = beloFieldForSearch ? beloFieldForSearch.value.trim() : '';
        const currentAmount = rawAmount.replace(/\./g, '').replace(',', '.'); 
      

        const url = basePath + '?search=' +
            encodeURIComponent(searchValue) +
            '&account=' + encodeURIComponent(accountNr) +
            '&accountType=' + encodeURIComponent(accountType) +
            '&currentAmount=' + encodeURIComponent(currentAmount) +
            '&page=' + page;



        const seq = searchSeq;
        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (seq !== searchSeq) return;
                const results = data.results || [];
                if (document.activeElement !== input) return;
                renderAmountDropdown(input, results, searchValue);
            })
            .catch(function (error) {
                if (seq !== searchSeq) return;
                if (document.activeElement !== input) return;
                console.error('Amount search error:', error);
                closeDropdown();
            });
    }

    function renderSimpleDropdown(input, results, fieldType, searchValue) {
        const dropdown = input.autocompleteDropdown;
        const trans = getTrans();

        let title, col1Header, col2Header;
        switch (fieldType) {
            case 'department':
                title = trans.selectDepartment;
                col1Header = trans.code;
                col2Header = trans.description;
                break;
            case 'employee':
                title = trans.selectEmployee;
                col1Header = trans.initials;
                col2Header = trans.name;
                break;
            case 'currency':
                title = trans.selectCurrency;
                col1Header = trans.code;
                col2Header = trans.description;
                break;
            default:
                title = trans.selectAccount;
                col1Header = trans.code;
                col2Header = trans.description;
        }

        let html = '<div class="account-autocomplete-header">' +
            '<span class="account-autocomplete-header-title">' + title + '</span>' +
            '<button type="button" class="account-autocomplete-close-btn" data-action="close">' + trans.close + ' ✕</button>' +
            '</div>';

        if (!results || results.length === 0) {
            html += '<div class="account-autocomplete-no-results">' + trans.noResults + '</div>';
        } else {
            html += '<div class="account-autocomplete-results">';
            html += '<table class="account-autocomplete-table">';
            html += '<thead><tr>' +
                '<th style="width:80px;">' + col1Header + '</th>' +
                '<th>' + col2Header + '</th>' +
                '</tr></thead>';
            html += '<tbody>';

            results.forEach(function (item) {
                let code, description, displayValue;

                switch (fieldType) {
                    case 'department':
                        code = item.code || '';
                        description = item.description || '';
                        displayValue = code;
                        break;
                    case 'employee':
                        code = item.initials || '';
                        description = item.name || '';
                        displayValue = code;
                        break;
                    case 'currency':
                        code = item.code || '';
                        description = item.description || '';
                        displayValue = code;
                        break;
                    default:
                        code = item.code || '';
                        description = item.description || '';
                        displayValue = code;
                }

                html += '<tr class="account-autocomplete-item" data-kontonr="' + escapeHtml(displayValue) + '">' +
                    '<td>' + escapeHtml(code) + '</td>' +
                    '<td>' + escapeHtml(description) + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table>';
            html += '</div>';
        }

        dropdown.innerHTML = html;
        positionDropdown(input, dropdown);
        showDropdown(input, dropdown);

        // Keep focus on the original input field
        input.focus();
    }


    /**
     * "Opret kreditor" under "no results" when a kreditor search finds nothing (SD-721), on pages that load kreditorFromCvr.js.
     * @param {string} searchType  'finance', 'debitor' or 'kreditor'.
     * @param {string} searchValue The text that found nothing.
     * @returns {string} The button's HTML, or '' when it doesn't apply.
     */
    function createKreditorButton(searchType, searchValue) {
        if (searchType !== 'kreditor' || searchValue === '' || !window.kreditorFromCvr || !window.saldiKreditorCvr) return '';
        const label = (window.saldiKreditorCvr.texts && window.saldiKreditorCvr.texts.create) || 'Opret kreditor';
        return ' <button type="button" class="account-autocomplete-create-kreditor" data-search="' + escapeHtml(searchValue) + '">' + escapeHtml(label) + '</button>';
    }


    /**
     * Fill and show the account popup (Vælg Konto / Debitor / Kreditor) for a field.
     * @param {HTMLInputElement} input                    The journal field the popup belongs to.
     * @param {Array<Object>}    results                  Accounts from accountSearch.php.
     * @param {string}           searchType               'finance', 'debitor' or 'kreditor'.
     * @param {string}           currentSearchValueParam  The search text the results are for.
     * @param {{page: number, total: number, hasMore: boolean, limit: number}} pagination  Paging state.
     */
    function renderDropdown(input, results, searchType, currentSearchValueParam, pagination) {
        const dropdown = input.autocompleteDropdown;
        const trans = getTrans();
        const panelOptions = getPanelOptions();
        const lastPostings = panelOptions.showLastPostings ? getLastPostings(input) : { heading: '', rows: [] };
        const hasLastPostings = lastPostings.rows && lastPostings.rows.length > 0;
        const columnCount = searchType === 'finance' ? 5 : 2;

        currentSearchValueParam = currentSearchValueParam || '';

        pagination = pagination || { page: 1, total: 0, hasMore: false };

        if (!results || results.length === 0) {
            if ((currentSearchValueParam !== '' && panelOptions.showAccountLookup) || hasLastPostings) {
                let noResultHtml = '<div class="account-autocomplete-no-results">' + trans.noResults + createKreditorButton(searchType, currentSearchValueParam) + '</div>';
                if (hasLastPostings) {
                    noResultHtml = '<div class="account-autocomplete-results">' +
                        '<table class="account-autocomplete-table">' +
                        renderAccountTableHeader(searchType, trans) +
                        '<tbody>' + renderLastPostingsRows(lastPostings, false, columnCount, searchType) + '</tbody></table></div>';
                }

                dropdown.innerHTML = '<div class="account-autocomplete-header">' +
                    '<span class="account-autocomplete-header-title">' + getAccountDropdownTitle(searchType, trans) + '</span>' +
                    '<button type="button" class="account-autocomplete-close-btn" data-action="close">' + trans.close + ' ✕</button>' +
                    '</div>' + noResultHtml;
                positionDropdown(input, dropdown);
                showDropdown(input, dropdown);
                // Keep focus on the original input field
                input.focus();
                return;
            }
            dropdown.style.display = 'none';
            activeDropdown = null;
            activeInput = null;
            return;
        }

        let html = '';

        html += '<div class="account-autocomplete-header">';
        html += '<span class="account-autocomplete-header-title">' + getAccountDropdownTitle(searchType, trans) + '</span>';
        html += '<button type="button" class="account-autocomplete-close-btn" data-action="close">' + trans.close + ' ✕</button>';
        html += '</div>';

        html += '<div class="account-autocomplete-results">';
        html += '<table class="account-autocomplete-table">';

        html += renderAccountTableHeader(searchType, trans);

        html += '<tbody>';

        if (hasLastPostings) {
            html += renderLastPostingsRows(lastPostings, true, columnCount, searchType);
        }

        let itemIndex = 0;
        results.forEach(function (item) {
            if (item.kontotype === 'H') {
                html += '<tr class="account-autocomplete-category">' +
                    '<td colspan="5"><strong>' + escapeHtml(item.beskrivelse) + '</strong></td>' +
                    '</tr>';
            } else {
                html += '<tr class="account-autocomplete-item" data-kontonr="' + escapeHtml(item.kontonr) + '" data-index="' + itemIndex + '"';
                if (searchType === 'finance') {
                    html += ' data-moms="' + escapeHtml(item.moms || '') + '"';
                }
                html += '>';

                if (searchType === 'finance') {
                    html += '<td>' + escapeHtml(item.kontonr) + renderCardButton('F', item.kontonr) + '</td>' +
                        '<td title="' + escapeHtml(item.beskrivelse) + '">' + escapeHtml(item.beskrivelse) + '</td>' +
                        '<td>' + escapeHtml(item.moms || '') + '</td>' +
                        '<td>' + escapeHtml(item.genvej || '') + '</td>' +
                        '<td style="text-align:right;">' + formatNumber(item.saldo) + '</td>';
                } else {
                    html += '<td>' + escapeHtml(item.kontonr) + renderCardButton(searchType === 'kreditor' ? 'K' : 'D', item.kontonr) + '</td>' +
                        '<td title="' + escapeHtml(item.beskrivelse) + '">' + escapeHtml(item.beskrivelse) + '</td>';
                }

                html += '</tr>';
                itemIndex++;
            }
        });

        html += '</tbody></table>';
        html += '</div>';

        if (pagination.total > 0) {
            const startItem = ((pagination.page - 1) * pagination.limit) + 1;
            const endItem = Math.min(pagination.page * pagination.limit, pagination.total);

            html += '<div class="account-autocomplete-footer">';
            html += '<span class="account-autocomplete-pagination-info">' + trans.showing + ' ' + startItem + '-' + endItem + ' ' + trans.of + ' ' + pagination.total + '</span>';

            html += '<div class="account-autocomplete-pagination-buttons">';
            if (pagination.page > 1) {
                html += '<button type="button" class="account-autocomplete-page-btn" data-page="' + (pagination.page - 1) + '">← ' + trans.previous + '</button>';
            }
            if (pagination.hasMore) {
                html += '<button type="button" class="account-autocomplete-page-btn" data-page="' + (pagination.page + 1) + '">' + trans.next + ' →</button>';
            }
            html += '</div>';
            html += '</div>';
        }

        dropdown.innerHTML = html;

        positionDropdown(input, dropdown);

        showDropdown(input, dropdown);

        // Keep focus on the original input field
        input.focus();
    }


    /**
     * Fill and show the "Åbne Poster" popup (open items) for a Fakturanr. field.
     * @param {HTMLInputElement} input                    The journal field the popup belongs to.
     * @param {Array<Object>}    results                  Open items from invoiceSearch.php.
     * @param {string}           currentSearchValueParam  The search text the results are for.
     * @param {{page: number, total: number, hasMore: boolean, limit: number}} pagination  Paging state.
     */
    function renderInvoiceDropdown(input, results, currentSearchValueParam, pagination) {
        const dropdown = input.autocompleteDropdown;

        currentSearchValueParam = currentSearchValueParam || '';
        pagination = pagination || { page: 1, total: 0, hasMore: false };
        const trans = getTrans();

        if (!results || results.length === 0) {
            if (currentSearchValueParam !== '') {
                dropdown.innerHTML = '<div class="account-autocomplete-header">' +
                    '<span class="account-autocomplete-header-title">' + trans.openItems + '</span>' +
                    '<button type="button" class="account-autocomplete-close-btn" data-action="close">' + trans.close + ' ✕</button>' +
                    '</div>' +
                        '<div class="account-autocomplete-no-results">' + trans.noResults + '</div>';
                positionDropdown(input, dropdown);
                showDropdown(input, dropdown);
                // Keep focus on the original input field
                input.focus();
                return;
            }
            dropdown.style.display = 'none';
            activeDropdown = null;
            activeInput = null;
            return;
        }

        let html = '';

        html += '<div class="account-autocomplete-header">';
        html += '<span class="account-autocomplete-header-title">' + trans.openItems + '</span>';
        html += '<button type="button" class="account-autocomplete-close-btn" data-action="close">' + trans.close + ' ✕</button>';
        html += '</div>';

        html += '<div class="account-autocomplete-results">';
        html += '<table class="account-autocomplete-table">';

        html += '<thead><tr>' +
            '<th style="width:80px;">' + trans.accountNo + '</th>' +
            '<th>' + trans.name + '</th>' +
            '<th style="width:90px;">' + trans.invoiceNo + '</th>' +
            '<th style="width:80px;">' + trans.date + '</th>' +
            '<th style="width:90px;text-align:right;">' + trans.amount + '</th>' +
            '</tr></thead>';

        html += '<tbody>';

        // Sort: amount-matching rows first, then the rest
        const sortedResults = results.slice().sort(function (a, b) {
            if (a.amountMatch && !b.amountMatch) return -1;
            if (!a.amountMatch && b.amountMatch) return 1;
            return 0;
        });



        let itemIndex = 0;
        sortedResults.forEach(function (item) {
            const description = (item.firmanavn || '') + (item.faktnr ? ' - ' + item.faktnr : '');
            const matchStyle = item.amountMatch ? ' style="background-color: #fff9c4 !important; font-weight: bold;"' : '';
            const matchClass = item.amountMatch ? ' account-autocomplete-item--amount-match' : '';

            html += '<tr class="account-autocomplete-item' + matchClass + '"' + matchStyle +
                ' data-kontonr="' + escapeHtml(item.faktnr) + '"' +
                ' data-faktnr="' + escapeHtml(item.faktnr) + '"' +
                ' data-amount="' + item.amount + '"' +
                ' data-accountnr="' + escapeHtml(item.kontonr || '') + '"' +
                ' data-accounttype="' + escapeHtml(item.art || '') + '"' +
                ' data-companyname="' + escapeHtml(item.firmanavn || '') + '"' +
                ' data-description="' + escapeHtml(description) + '"' +
                ' data-currency="' + escapeHtml(item.valuta || '') + '"' +
                ' data-offsetaccount="' + escapeHtml(item.offsetAccount || '') + '"' +
                ' data-index="' + itemIndex + '">';

            html += '<td>' + escapeHtml(item.kontonr) + renderCardButton(item.art, item.kontonr) + '</td>' +
                '<td title="' + escapeHtml(item.firmanavn || '') + '">' + escapeHtml(item.firmanavn || '') + '</td>' +
                '<td>' + escapeHtml(item.faktnr || '') + '</td>' +
                '<td>' + formatDate(item.transdate) + '</td>' +
                '<td style="text-align:right;">' + formatNumber(item.amount) + '</td>';

            html += '</tr>';
            itemIndex++;
        });

        html += '</tbody></table>';
        html += '</div>';

        if (pagination.total > 0) {
            const startItem = ((pagination.page - 1) * pagination.limit) + 1;
            const endItem = Math.min(pagination.page * pagination.limit, pagination.total);

            html += '<div class="account-autocomplete-footer">';
            html += '<span class="account-autocomplete-pagination-info">' + trans.showing + ' ' + startItem + '-' + endItem + ' ' + trans.of + ' ' + pagination.total + '</span>';

            html += '<div class="account-autocomplete-pagination-buttons">';
            if (pagination.page > 1) {
                html += '<button type="button" class="account-autocomplete-page-btn" data-page="' + (pagination.page - 1) + '">← ' + trans.previous + '</button>';
            }
            if (pagination.hasMore) {
                html += '<button type="button" class="account-autocomplete-page-btn" data-page="' + (pagination.page + 1) + '">' + trans.next + ' →</button>';
            }
            html += '</div>';
            html += '</div>';
        }

        dropdown.innerHTML = html;

        positionDropdown(input, dropdown);

        showDropdown(input, dropdown);

        // Keep focus on the original input field
        input.focus();
    }


    function renderAmountDropdown(input, results, searchValue) {
        const dropdown = input.autocompleteDropdown;
        const trans = getTrans();

        searchValue = searchValue || '';

        const title = trans.selectAmount || 'Select Amount';

        if (!results || results.length === 0) {
            dropdown.innerHTML = '<div class="account-autocomplete-header">' +
                '<span class="account-autocomplete-header-title">' + title + '</span>' +
                '<button type="button" class="account-autocomplete-close-btn" data-action="close">' + trans.close + ' ✕</button>' +
                '</div>' +
                '<div class="account-autocomplete-no-results">' + trans.noResults + '</div>';
            positionDropdown(input, dropdown);
            showDropdown(input, dropdown);
            // Keep focus on the original input field
            input.focus();
            return;
        }

        let html = '';

        html += '<div class="account-autocomplete-header">';
        html += '<span class="account-autocomplete-header-title">' + title + '</span>';
        html += '<button type="button" class="account-autocomplete-close-btn" data-action="close">' + trans.close + ' ✕</button>';
        html += '</div>';

        html += '<div class="account-autocomplete-results">';
        html += '<table class="account-autocomplete-table">';

        html += '<thead><tr>' +
            '<th style="width:80px;">' + trans.accountNo + '</th>' +
            '<th>' + trans.name + '</th>' +
            '<th style="width:90px;">' + trans.invoiceNo + '</th>' +
            '<th style="width:80px;">' + trans.date + '</th>' +
            '<th style="width:100px;text-align:right;">' + trans.amount + '</th>' +
            '</tr></thead>';

        html += '<tbody>';

        let itemIndex = 0;
        results.forEach(function (item) {
            const description = (item.companyName || '') + (item.invoiceNr ? ' - ' + item.invoiceNr : '');

            html += '<tr class="account-autocomplete-item"' +
                ' data-kontonr="' + escapeHtml(item.amount.toString()) + '"' +
                ' data-faktnr="' + escapeHtml(item.invoiceNr || '') + '"' +
                ' data-amount="' + item.amount + '"' +
                ' data-accountnr="' + escapeHtml(item.accountNr || '') + '"' +
                ' data-accounttype="' + escapeHtml(item.accountType || '') + '"' +
                ' data-companyname="' + escapeHtml(item.companyName || '') + '"' +
                ' data-description="' + escapeHtml(description) + '"' +
                ' data-currency="' + escapeHtml(item.currency || '') + '"' +
                ' data-offsetaccount="' + escapeHtml(item.offsetAccount || '') + '"' +
                ' data-index="' + itemIndex + '">';

            html += '<td>' + escapeHtml(item.accountNr || '') + '</td>' +
                '<td title="' + escapeHtml(item.companyName || '') + '">' + escapeHtml(item.companyName || '') + '</td>' +
                '<td>' + escapeHtml(item.invoiceNr || '') + '</td>' +
                '<td>' + formatDate(item.date) + '</td>' +
                '<td style="text-align:right;font-weight:bold;">' + formatNumber(item.amount) + '</td>';

            html += '</tr>';
            itemIndex++;
        });

        html += '</tbody></table>';
        html += '</div>';

        dropdown.innerHTML = html;

        positionDropdown(input, dropdown);

        showDropdown(input, dropdown);

        // Keep focus on the original input field
        input.focus();
    }


    function selectAccount(input, selection) {
        clearTimeout(debounceTimer);

        selectionMade = true;
        // Supersede any request already in flight: the dropdown is closing, and a
        // response that lands afterwards must not reopen it.
        searchSeq++;

        closeDropdown();

        const selectedItem = selection && selection.dataset ? selection : null;
        const kontonr = selectedItem ? (selectedItem.dataset.kontonr || '') : selection;
        const rowNum = input.rowNumber || getRowNumber(input.name);
        const vatOptions = selectedItem && Object.prototype.hasOwnProperty.call(selectedItem.dataset, 'moms')
            ? { vatCode: selectedItem.dataset.moms || '' }
            : undefined;

        input.value = kontonr;

        if (input.fieldType === 'debet' || input.fieldType === 'kredit') {
            const accountType = selectedItem ? selectedItem.dataset.accountType : '';
            if (['D', 'K', 'F'].includes(accountType)) {
                const typeName = (input.fieldType === 'debet' ? 'd_ty' : 'k_ty') + rowNum;
                const typeField = (input.form || document).querySelector('input[name="' + typeName + '"]');
                if (typeField && typeField.value !== accountType) {
                    setFieldValue(typeField, accountType);
                }
            }
        }

        // Dispatch both input and change events to ensure all handlers are triggered
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));

        if (input.fieldType === 'debet' || input.fieldType === 'kredit') {
            updateVatFieldForRow(rowNum, input.fieldType, vatOptions);
        }

        // Ensure docChange is set (for unsaved changes warning)
        if (typeof window.docChange !== 'undefined') {
            window.docChange = true;
        }

        // Debug logging
        console.log('selectAccount:', input.name, '=', kontonr, 'form:', input.form ? input.form.id : 'no form');
        if (!input.form) {
            console.error('Input is not connected to form!', input.name);
        }

        let nextInput = null;
        // If input.form is null use document
        const scope = input.form || document;
        const inputs = Array.from(scope.querySelectorAll('input, select, textarea'));
        const currentIndex = inputs.indexOf(input);
        if (currentIndex > -1 && currentIndex < inputs.length - 1) {
            nextInput = inputs[currentIndex + 1];
            nextInput.focus();
        }

        setTimeout(function () {
            selectionMade = false;
            // Open autocomplete on the next field now that selectionMade is cleared
            if (nextInput && nextInput.autocompleteInitialized && document.activeElement === nextInput) {
                handleInputWithValue(nextInput, nextInput.value);
            }
        }, 100);
    }

    /**
     * Helper function to set field value and trigger proper change events
     */
    function setFieldValue(field, value) {
        if (!field) return;
        field.value = value;

        // Dispatch events that will trigger event listeners
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));

        // Ensure docChange is set for unsaved changes warning
        if (typeof window.docChange !== 'undefined') {
            window.docChange = true;
        }

    }

    /*
     * Invoice autofill: a pick in the Åbne Poster panel under Fakturanr. fills the rest of the line.
     * The pick only writes a field that is empty, or one an earlier pick wrote that still holds that value.
     * A value the user typed, or one the line was saved with (a bank import line), is never overwritten:
     * where it disagrees with the invoice the field is marked, and the notice above the line offers the
     * invoice's value ("Brug fakturaens værdier"). Every field a pick writes keeps the value it had before
     * (data-autofill-original) and the value the pick wrote (data-autofill-value), so "Fortryd" and the
     * next pick on the same line can put the line back to the user's own values.
     */
    // Put back in this order: a side's account before its type, so a type put back while the pick's
    // debitor is still in the account field never starts a finance VAT lookup for that number
    const AUTOFILL_FIELDS = ['besk', 'debe', 'd_ty', 'kred', 'k_ty', 'fakt', 'belo', 'valu'];
    // A side's type and account are one value
    const AUTOFILL_PAIRS = [['d_ty', 'debe'], ['k_ty', 'kred']];
    let autofillNotice = null;
    let autofillListenersBound = false;

    function autofillText(key, fallback) {
        const trans = getTrans();
        return trans[key] || fallback;
    }

    function rowField(prefix, rowNum) {
        return document.querySelector('input[name="' + prefix + rowNum + '"]');
    }

    /** A Danish amount as the user types it ("4.950,00", "4950") as a number, or null. */
    function parseDanishAmount(text) {
        const clean = String(text || '').replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
        if (clean === '') return null;
        const num = parseFloat(clean);
        return isNaN(num) ? null : num;
    }

    /** True while the field still holds the value a pick wrote into it (the user has not changed it since). */
    function isAutofilled(field) {
        return field.dataset.autofillValue !== undefined && field.value === field.dataset.autofillValue;
    }

    function forgetAutofill(field) {
        delete field.dataset.autofillValue;
        delete field.dataset.autofillOriginal;
    }

    /** Write a value as the pick's own, remembering what the field held before. */
    function autofillField(field, value) {
        if (!field || field.value === value) return;
        const original = field.dataset.autofillValue !== undefined ? field.dataset.autofillOriginal : field.value;
        setFieldValue(field, value);
        field.dataset.autofillOriginal = original;
        field.dataset.autofillValue = field.value;
    }

    /** Put back every field the line's picks wrote and nobody has changed since; forget the rest. */
    function revertAutofill(rowNum) {
        // When the user has changed the type or the account of a side, the whole side is theirs:
        // putting back only the other half would give a type that doesn't belong to the account
        AUTOFILL_PAIRS.forEach(function (pair) {
            const written = pair.map(function (prefix) {
                return rowField(prefix, rowNum);
            }).filter(function (field) {
                return field && field.dataset.autofillValue !== undefined;
            });
            if (written.some(function (field) { return !isAutofilled(field); })) {
                written.forEach(forgetAutofill);
            }
        });
        AUTOFILL_FIELDS.forEach(function (prefix) {
            const field = rowField(prefix, rowNum);
            if (!field || field.dataset.autofillValue === undefined) return;
            if (isAutofilled(field)) {
                setFieldValue(field, field.dataset.autofillOriginal);
            }
            forgetAutofill(field);
        });
    }

    /** Labels of the fields that still hold what a pick wrote, in the line's column order. */
    function autofilledLabels(rowNum) {
        const labels = {
            besk: autofillText('description', 'Beskrivelse'),
            debe: autofillText('debit', 'Debet'),
            kred: autofillText('credit', 'Kredit'),
            belo: autofillText('amount', 'Beløb'),
            valu: autofillText('currency', 'Valuta')
        };
        return Object.keys(labels).filter(function (prefix) {
            const field = rowField(prefix, rowNum);
            return field && isAutofilled(field);
        }).map(function (prefix) {
            return labels[prefix];
        });
    }

    /** Mark a field whose value disagrees with the invoice; the notice offers the invoice's value. */
    function markConflict(field, value, note, pairField) {
        if (field.dataset.autofillSuggest === undefined) {
            field.dataset.autofillTitle = field.getAttribute('title') || '';
        }
        field.dataset.autofillSuggest = value;
        field.dataset.autofillMarked = field.value;
        if (note) field.dataset.autofillNote = note;
        if (pairField) field.dataset.autofillPair = pairField.name;
        field.classList.add('autofill-conflict');
        field.title = autofillText('invoice', 'Faktura') + ': ' + value;
        if (!field.autofillConflictBound) {
            field.autofillConflictBound = true;
            // Any new value settles the mark - typed, or picked in the field's own lookup panel (synthetic events)
            const settle = function () {
                if (field.dataset.autofillSuggest === undefined || field.value === field.dataset.autofillMarked) return;
                clearConflict(field);
                const rowMatch = field.name.match(/\d+$/);
                if (rowMatch && autofillNotice && autofillNotice.rowNum === rowMatch[0]) showAutofillNotice(rowMatch[0]);
            };
            field.addEventListener('input', settle);
            field.addEventListener('change', settle);
            field.addEventListener('focus', function () {
                if (field.dataset.autofillSuggest !== undefined) {
                    const rowMatch = field.name.match(/\d+$/);
                    if (rowMatch) showAutofillNotice(rowMatch[0]);
                }
            });
        }
    }

    function clearConflict(field) {
        if (!field || field.dataset.autofillSuggest === undefined) return;
        const pairName = field.dataset.autofillPair;
        field.classList.remove('autofill-conflict');
        if (field.dataset.autofillTitle) field.title = field.dataset.autofillTitle;
        else field.removeAttribute('title');
        delete field.dataset.autofillSuggest;
        delete field.dataset.autofillMarked;
        delete field.dataset.autofillNote;
        delete field.dataset.autofillPair;
        delete field.dataset.autofillTitle;
        // The type and the account of one side are one value: settling one settles both
        if (pairName) clearConflict(document.querySelector('input[name="' + pairName + '"]'));
    }

    function clearConflicts(rowNum) {
        AUTOFILL_FIELDS.forEach(function (prefix) {
            clearConflict(rowField(prefix, rowNum));
        });
    }

    /** The marked fields in the order the type of a side comes before its account (the order a pick writes them). */
    function conflictFields(rowNum) {
        return ['besk', 'd_ty', 'debe', 'k_ty', 'kred', 'fakt', 'belo', 'valu'].map(function (prefix) {
            return rowField(prefix, rowNum);
        }).filter(function (field) {
            return field && field.dataset.autofillSuggest !== undefined;
        });
    }

    /** "Brug fakturaens værdier": the marked fields take the invoice's values, which "Fortryd" can take back. */
    function acceptConflicts(rowNum) {
        // Read every suggestion first: clearing a type's mark clears its account's mark too
        const accepted = conflictFields(rowNum).map(function (field) {
            return { field: field, value: field.dataset.autofillSuggest };
        });
        accepted.forEach(function (item) { clearConflict(item.field); });
        accepted.forEach(function (item) { autofillField(item.field, item.value); });
    }

    /**
     * Offer one value to one field: an empty field takes it, an equal one is left alone, a different one
     * is kept and marked (or kept silently when options.silent).
     * @returns {string} 'filled', 'same', 'kept' or 'conflict'; '' when there is nothing to offer.
     */
    function offerValue(field, value, options) {
        if (!field || value === '') return '';
        const same = options.same || function (a, b) { return a.trim() === b; };
        if (same(field.value, value)) return 'same';
        if (field.value.trim() === '') {
            autofillField(field, value);
            return 'filled';
        }
        if (options.silent) return 'kept';
        markConflict(field, value, options.note(field.value.trim()));
        return 'conflict';
    }

    /** Offer a debitor/kreditor (type + account) to one side of the line. */
    function offerAccount(typeField, accountField, type, account, label) {
        if (!accountField || !account) return '';
        const lineAccount = accountField.value.trim();
        const lineType = (typeField ? typeField.value.trim().toUpperCase() : '') || 'F';
        if (lineAccount === account && lineType === type) return 'same';
        if (lineAccount === '') {
            if (typeField) autofillField(typeField, type);
            autofillField(accountField, account);
            return 'filled';
        }
        const note = label + ' ' + type + ' ' + account + ' (' + autofillText('autofillLine', 'linjen') + ': ' + lineType + ' ' + lineAccount + ')';
        if (typeField) markConflict(typeField, type, '', accountField);
        markConflict(accountField, account, note, typeField);
        return 'conflict';
    }

    function bindAutofillNoticeListeners() {
        if (autofillListenersBound) return;
        autofillListenersBound = true;
        const reposition = function () {
            if (autofillNotice) positionAutofillNotice();
        };
        window.addEventListener('scroll', reposition, true);
        window.addEventListener('resize', reposition);
        // Moving on to another line puts the notice away; its marks stay on the fields
        document.addEventListener('focusin', function (e) {
            if (!autofillNotice || !e.target || !e.target.name) return;
            const rowMatch = e.target.name.match(/\d+$/);
            if (rowMatch && rowMatch[0] !== autofillNotice.rowNum) hideAutofillNotice();
        });
    }

    function hideAutofillNotice() {
        if (!autofillNotice) return;
        clearTimeout(autofillNotice.timer);
        autofillNotice.box.remove();
        autofillNotice = null;
    }

    function positionAutofillNotice() {
        const anchor = rowField('fakt', autofillNotice.rowNum);
        if (!anchor || !anchor.isConnected) {
            hideAutofillNotice();
            return;
        }
        const rect = anchor.getBoundingClientRect();
        const box = autofillNotice.box;
        const width = box.offsetWidth || 400;
        let left = rect.right - width;
        if (left + width > window.innerWidth - 10) left = window.innerWidth - width - 10;
        if (left < 10) left = 10;
        // Above the line: below it are the next line the user types in and the lookup panels
        let top = rect.top - box.offsetHeight - 4;
        if (top < 10) top = rect.bottom + 4;
        box.style.left = left + 'px';
        box.style.top = top + 'px';
    }

    function startAutofillNoticeTimer() {
        clearTimeout(autofillNotice.timer);
        autofillNotice.timer = setTimeout(hideAutofillNotice, 12000);
    }

    /** Run fn with selectionMade set, as a pick does, so the input events setFieldValue sends don't start lookups. */
    function withoutLookup(fn) {
        selectionMade = true;
        try {
            fn();
        } finally {
            setTimeout(function () {
                selectionMade = false;
            }, 100);
        }
    }

    function noticeAction(text, onClick) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'autofill-notice-action';
        button.textContent = text;
        // Keep the cursor where the user is typing; the click is handled on mousedown like the panel's lines
        button.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            onClick();
        });
        button.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                onClick();
            }
        });
        return button;
    }

    /**
     * The notice above the line after a pick: what the pick filled in ("Fortryd") and where the line
     * disagrees with the invoice ("Brug fakturaens værdier"). Built from the fields themselves, so it is
     * current when a marked field gets the cursor again or a mark is settled.
     * @param {string} rowNum  The line number in the field names.
     */
    function showAutofillNotice(rowNum) {
        bindAutofillNoticeListeners();
        hideAutofillNotice();
        const faktField = rowField('fakt', rowNum);
        const faktnr = faktField ? faktField.value.trim() : '';
        const filled = autofilledLabels(rowNum);
        const conflicts = conflictFields(rowNum).filter(function (field) {
            return field.dataset.autofillNote;
        });
        if (!filled.length && !conflicts.length) return;

        const box = document.createElement('div');
        box.className = 'autofill-notice' + (conflicts.length ? ' autofill-notice--conflict' : '');
        box.setAttribute('role', 'status');

        if (filled.length) {
            const line = document.createElement('div');
            line.appendChild(document.createTextNode(autofillText('autofillFilled', 'Udfyldt fra faktura') + ' ' + faktnr + ': ' + filled.join(', ') + ' · '));
            line.appendChild(noticeAction(autofillText('undo', 'Fortryd'), function () {
                withoutLookup(function () {
                    clearConflicts(rowNum);
                    revertAutofill(rowNum);
                });
                hideAutofillNotice();
                if (faktField) window.focusAccountQuietly(faktField);
            }));
            box.appendChild(line);
        }
        if (conflicts.length) {
            const line = document.createElement('div');
            line.className = 'autofill-notice-conflict';
            const notes = conflicts.map(function (field) { return field.dataset.autofillNote; });
            line.appendChild(document.createTextNode(autofillText('autofillDiffers', 'Afviger fra fakturaen') + ': ' + notes.join('; ') + ' · '));
            line.appendChild(noticeAction(autofillText('autofillUseInvoice', 'Brug fakturaens værdier'), function () {
                withoutLookup(function () {
                    acceptConflicts(rowNum);
                });
                showAutofillNotice(rowNum);
            }));
            box.appendChild(line);
        }

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'autofill-notice-close';
        close.setAttribute('aria-label', autofillText('close', 'Luk'));
        close.textContent = '✕';
        close.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            hideAutofillNotice();
        });
        box.appendChild(close);

        getDropdownContainer().appendChild(box);
        autofillNotice = { box: box, rowNum: rowNum, timer: null };
        box.addEventListener('mouseenter', function () { clearTimeout(autofillNotice && autofillNotice.timer); });
        box.addEventListener('mouseleave', function () { if (autofillNotice && autofillNotice.box === box) startAutofillNoticeTimer(); });
        positionAutofillNotice();
        startAutofillNoticeTimer();
    }

    function selectInvoiceOrAmount(input, item) {
        clearTimeout(debounceTimer);

        selectionMade = true;
        // Supersede any request already in flight: the dropdown is closing, and a
        // response that lands afterwards must not reopen it.
        searchSeq++;

        closeDropdown();

        const rowNum = input.rowNumber;

        const faktnr = item.dataset.faktnr || '';
        const amount = item.dataset.amount || '';
        const accountNr = item.dataset.accountnr || '';
        const accountType = (item.dataset.accounttype || '').toUpperCase();
        const description = item.dataset.description || '';
        const currency = (item.dataset.currency || '').toUpperCase();
        const offsetAccount = item.dataset.offsetaccount || '';

        const faktField = rowField('fakt', rowNum);
        const beloField = rowField('belo', rowNum);
        const beskField = rowField('besk', rowNum);
        const valuField = rowField('valu', rowNum);
        const dTypeField = rowField('d_ty', rowNum);
        const debeField = rowField('debe', rowNum);
        const kTypeField = rowField('k_ty', rowNum);
        const kredField = rowField('kred', rowNum);

        const amountValue = parseFloat(amount) || 0;
        const lineText = autofillText('autofillLine', 'linjen');

        // A second pick on the line starts from the user's own values, not from the first pick's
        clearConflicts(rowNum);
        revertAutofill(rowNum);

        // A negative amount typed on the line turns the line round (a save swaps its sides), so the pick follows it
        const lineAmount = parseDanishAmount(beloField ? beloField.value : '');
        const lineNegative = lineAmount !== null && lineAmount < 0;

        // The field the user picked from takes the pick
        if (input.fieldType === 'faktura') {
            autofillField(input, faktnr);
        } else if (input.fieldType === 'amount') {
            autofillField(input, formatNumberForInput(Math.abs(amountValue)));
        }

        if (input.fieldType !== 'faktura') {
            offerValue(faktField, faktnr, {
                note: function (line) { return autofillText('invoiceNo', 'Fakturanr.') + ' ' + faktnr + ' (' + lineText + ': ' + line + ')'; }
            });
        }

        // Amount (always positive in the field): a different amount on the line is a partial payment,
        // a fee or the wrong invoice - the user decides, the pick never changes it
        if (amount && input.fieldType !== 'amount') {
            offerValue(beloField, (lineNegative ? '-' : '') + formatNumberForInput(Math.abs(amountValue)), {
                same: function (line) {
                    const amountOnLine = parseDanishAmount(line);
                    return amountOnLine !== null && Math.abs(Math.abs(amountOnLine) - Math.abs(amountValue)) < 0.005;
                },
                note: function (line) { return autofillText('amount', 'Beløb') + ' ' + formatNumber(Math.abs(amountValue)) + ' (' + lineText + ': ' + line + ')'; }
            });
        }

        // Description - "Company name - Invoice number" - only on a line without its own text (a bank import line has the bank's text)
        offerValue(beskField, description, { silent: true });

        // Currency: a saved line shows the base currency, so a different currency is a question for the user, not a fill
        offerValue(valuField, currency, {
            same: function (line, value) { return line.trim().toUpperCase() === value; },
            note: function (line) { return autofillText('currency', 'Valuta') + ' ' + currency + ' (' + lineText + ': ' + line + ')'; }
        });

        if (accountNr && accountType) {
            // A negative open post (a credit note, a kreditor invoice) takes the debitor/kreditor on the debit
            // side and the offset account (bank) on the credit side; a positive one the other way round.
            const negative = (amountValue < 0) !== lineNegative;
            const accountSide = negative
                ? { type: dTypeField, account: debeField, label: autofillText('debit', 'Debet') }
                : { type: kTypeField, account: kredField, label: autofillText('credit', 'Kredit') };
            const offsetField = negative ? kredField : debeField;

            offerAccount(accountSide.type, accountSide.account, accountType, accountNr, accountSide.label);

            // The offset account only goes into an empty field; its type is left as it is
            if (offsetAccount && offsetField && offsetField.value.trim() === '') {
                autofillField(offsetField, offsetAccount);
            }
        }
        // The momskode follows by itself: setFieldValue's change event reaches the account fields' VAT
        // listener (bindVatFieldListeners) on the side a pick changed, and the other side keeps its code.

        // Ensure docChange is set
        if (typeof window.docChange !== 'undefined') {
            window.docChange = true;
        }

        // Move focus to the next field
        const form = input.form;
        if (form) {
            const inputs = Array.from(form.querySelectorAll('input, select, textarea'));
            const currentIndex = inputs.indexOf(input);
            if (currentIndex > -1 && currentIndex < inputs.length - 1) {
                inputs[currentIndex + 1].focus();
            }
        }

        showAutofillNotice(rowNum);

        setTimeout(function () {
            selectionMade = false;
        }, 100);
    }

    function formatNumberForInput(value) {
        if (value === '' || value === null || value === undefined) return '';
        const num = parseFloat(value);
        if (isNaN(num)) return value;
        return num.toFixed(2).replace('.', ',');
    }

    /**
     * Document-level keys for the open popup: arrows move the selection, Enter selects the line,
     * Ctrl/Cmd+Enter opens the selected line's card, Escape and Tab close the popup.
     * @param {KeyboardEvent} e  The keydown event.
     */
    function handleKeyboardNavigation(e) {
        if (!activeDropdown) return;

        const items = activeDropdown.querySelectorAll('.account-autocomplete-item');
        if (items.length === 0) return;

        const selected = activeDropdown.querySelector('.account-autocomplete-item.selected');
        let selectedIndex = -1;

        if (selected) {
            for (let i = 0; i < items.length; i++) {
                if (items[i] === selected) {
                    selectedIndex = i;
                    break;
                }
            }
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (selectedIndex < items.length - 1) {
                    selectItemByIndex(items, selectedIndex + 1);
                } else if (selectedIndex === -1 && items.length > 0) {
                    selectItemByIndex(items, 0);
                }
                break;

            case 'ArrowUp':
                e.preventDefault();
                if (selectedIndex > 0) {
                    selectItemByIndex(items, selectedIndex - 1);
                }
                break;

            case 'Enter':
                if (selected && (e.ctrlKey || e.metaKey) && openSelectedAccountCard(selected)) {
                    e.preventDefault();
                    break;
                }
                if (selected && activeInput) {
                    e.preventDefault();
                    if (activeInput.fieldType === 'faktura' || activeInput.fieldType === 'amount') {
                        selectInvoiceOrAmount(activeInput, selected);
                    } else {
                        selectAccount(activeInput, selected);
                    }
                }
                break;

            case 'Escape':
                e.preventDefault();
                closeDropdown();
                break;

            case 'Tab':
                closeDropdown();
                break;
        }
    }


    function selectItemByIndex(items, index) {
        items.forEach(function (item, i) {
            if (i === index) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }


    function closeDropdown() {
        // Hide ALL visible dropdowns in the container to prevent orphaned modals
        if (dropdownContainer) {
            const allDropdowns = dropdownContainer.querySelectorAll('.account-autocomplete-dropdown');
            allDropdowns.forEach(function (dd) {
                dd.style.display = 'none';
            });
        }
        if (activeDropdown) {
            activeDropdown.style.display = 'none';
        }
        activeDropdown = null;
        activeInput = null;
    }

    /**
     * Close any previous dropdown, then show the given dropdown for the given input.
     * This prevents orphaned/duplicate modals.
     */
    function showDropdown(input, dropdown) {
        // Close any previously open dropdown first
        closeDropdown();
        dropdown.style.display = 'flex';
        activeDropdown = dropdown;
        activeInput = input;
    }


    /**
     * Small button opening the card behind an account line (SD-698). Only pages that set
     * window.saldiAccountCard get it; art is F (finance), D (debtor) or K (creditor).
     * @param {string}        art      Account type of the line: 'F', 'D' or 'K'.
     * @param {string|number} kontonr  Account number of the line.
     * @returns {string}  Button HTML, or '' when the page has no card config or the line has no valid account.
     */
    function renderCardButton(art, kontonr) {
        const cfg = window.saldiAccountCard;
        art = String(art || 'F').toUpperCase();
        if (!cfg || !kontonr || !/^[0-9]+$/.test(String(kontonr)) || ['F', 'D', 'K'].indexOf(art) === -1) {
            return '';
        }
        const label = (cfg.titles && cfg.titles[art]) || '';
        const title = escapeHtml(label ? label + ' (Ctrl+Enter)' : '').replace(/"/g, '&quot;');
        return '<button type="button" class="account-autocomplete-card-btn" tabindex="-1"' +
            ' data-card-art="' + art + '" data-card-kontonr="' + escapeHtml(String(kontonr)) + '"' +
            ' title="' + title + '" aria-label="' + title + '">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
            ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/>' +
            '<circle cx="8" cy="11" r="2"/><path d="M5 16c.6-1.5 1.8-2.2 3-2.2s2.4.7 3 2.2M14 9h5M14 13h4"/></svg></button>';
    }


    /**
     * Whether the journal form holds edits that leaving the page would lose.
     * @returns {boolean}  True when docChange is set or a text field / select differs from its loaded value.
     */
    function journalHasUnsavedChanges() {
        if (typeof docChange !== 'undefined' && docChange) {
            return true;
        }
        const fields = document.querySelectorAll('form input[type="text"], form textarea, form select');
        for (let i = 0; i < fields.length; i++) {
            const f = fields[i];
            if (f.tagName === 'SELECT') {
                // with no option marked selected in the markup, the browser shows the first one
                let initial = 0;
                for (let j = 0; j < f.options.length; j++) {
                    if (f.options[j].defaultSelected) { initial = j; break; }
                }
                if (f.options.length && f.selectedIndex !== initial) return true;
            } else if (f.value !== f.defaultValue) {
                return true;
            }
        }
        return false;
    }


    /**
     * Keyboard route to the card button (Ctrl/Cmd+Enter on the selected popup line).
     * @param {HTMLElement} item  The selected .account-autocomplete-item row.
     * @returns {boolean}  True when the line has a card button and its card is being opened.
     */
    function openSelectedAccountCard(item) {
        const cardBtn = item && item.querySelector('.account-autocomplete-card-btn');
        if (!cardBtn) return false;
        openAccountCard(cardBtn.dataset.cardArt, cardBtn.dataset.cardKontonr);
        return true;
    }


    /**
     * Open the card behind an account (via openAccountCard.php) in a separate, reused tab, so the
     * journal and its unsaved edits stay open; the card's Tilbage closes that tab again. When the
     * browser blocks the tab, fall back to leaving the journal, after asking when there are unsaved edits.
     * @param {string}        art      Account type: 'F' (kontospec), 'D' (debitorkort) or 'K' (kreditorkort).
     * @param {string|number} kontonr  Account number.
     */
    function openAccountCard(art, kontonr) {
        const cfg = window.saldiAccountCard;
        if (!cfg || !kontonr) return;
        const url = cfg.url + '?art=' + encodeURIComponent(art) +
            '&kontonr=' + encodeURIComponent(kontonr) + '&kladde_id=' + encodeURIComponent(cfg.kladdeId || 0);
        const cardTab = window.open(url + '&tab=1', 'saldiKort');
        if (cardTab) {
            cardTab.focus();
            return;
        }
        if (journalHasUnsavedChanges() && !window.confirm(cfg.unsaved || '')) {
            return;
        }
        window.location.href = url;
    }


    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }


    function formatNumber(num) {
        if (num === null || num === undefined) return '';
        return parseFloat(num).toLocaleString('da-DK', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }


    function formatDate(dateStr) {
        if (!dateStr) return '';
        try {
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return day + '-' + month + '-' + year;
        } catch (e) {
            return dateStr;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAccountAutocomplete);
    } else {
        initAccountAutocomplete();
    }

    window.addEventListener('load', function () {
        if (document.querySelectorAll('.account-autocomplete-wrapper').length === 0) {
            initAccountAutocomplete();
        }
    });

    window.initAccountAutocomplete = initAccountAutocomplete;
    window.closeAccountAutocomplete = closeDropdown;
    /** Focuses a lookup field without opening its panel, as a Tab into it does; typing or a click opens it. */
    window.focusAccountQuietly = function (input) {
        if (!input) return;
        focusViaKeyboardNav = true;
        input.focus();
        focusViaKeyboardNav = false;
    };

})();
