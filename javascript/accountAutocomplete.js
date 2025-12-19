
(function() {
    'use strict';

    const CONFIG = {
        minSearchLength: 0,
        debounceDelay: 200,
        maxResults: 50
    };

    function getTrans() {
        return window.saldiTranslations || {};
    }

    let activeDropdown = null;
    let activeInput = null;
    let debounceTimer = null;
    let dropdownContainer = null;
    let selectionMade = false; 
    let currentPage = 1; 
    let currentSearchValue = ''; 
    
    
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
        
        debeFields.forEach(function(input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'debet');
                input.autocompleteInitialized = true;
            }
        });

        const kredFields = document.querySelectorAll('input[name^="kred"]');
        
        kredFields.forEach(function(input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'kredit');
                input.autocompleteInitialized = true;
            }
        });

        const faktFields = document.querySelectorAll('input[name^="fakt"]');
        faktFields.forEach(function(input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'faktura');
                input.autocompleteInitialized = true;
            }
        });

        const afdFields = document.querySelectorAll('input[name^="afd_"]');
        afdFields.forEach(function(input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'department');
                input.autocompleteInitialized = true;
            }
        });

        const medaFields = document.querySelectorAll('input[name^="meda"]');
        medaFields.forEach(function(input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'employee');
                input.autocompleteInitialized = true;
            }
        });

        const valuFields = document.querySelectorAll('input[name^="valu"]');
        valuFields.forEach(function(input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'currency');
                input.autocompleteInitialized = true;
            }
        });

        const beloFields = document.querySelectorAll('input[name^="belo"]');
        beloFields.forEach(function(input) {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'amount');
                input.autocompleteInitialized = true;
            }
        });

        document.addEventListener('mousedown', function(e) {
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

        document.addEventListener('keydown', function(e) {
            if (activeDropdown) {
                handleKeyboardNavigation(e);
            }
        });

    }

  
    function setupAutocomplete(input, fieldType) {
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
        
        dropdown.addEventListener('mousedown', function(e) {
            if (e.target.closest('.account-autocomplete-close-btn')) {
                e.preventDefault();
                e.stopPropagation();
                selectionMade = true;
                closeDropdown();
                if (input) {
                    input.focus();
                }
                setTimeout(function() {
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
            
            const item = e.target.closest('.account-autocomplete-item');
            if (item) {
                e.preventDefault();
                e.stopPropagation();
                const kontonr = item.dataset.kontonr;
                if (kontonr) {
                    if (input.fieldType === 'faktura' || input.fieldType === 'amount') {
                        selectInvoiceOrAmount(input, item);
                    } else {
                        selectAccount(input, kontonr);
                    }
                }
                return;
            }
            
            e.preventDefault();
        });

        input.autocompleteDropdown = dropdown;
        input.fieldType = fieldType;

        const rowMatch = input.name.match(/\d+$/);
        input.rowNumber = rowMatch ? rowMatch[0] : '';

        input.addEventListener('input', function(e) {
            if (selectionMade) {
                return;
            }
            handleInputWithValue(this, this.value);
        });

        input.addEventListener('focus', function(e) {
            if (selectionMade) {
                return;
            }
            // Show dropdown with current input value as search
            handleInputWithValue(this, this.value);
        });

        input.addEventListener('blur', function(e) {
            // console.log('blur', e)
         
        });
        
        // Handle keyboard navigation while typing in the input field
        input.addEventListener('keydown', function(e) {
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
                    if (selected) {
                        e.preventDefault();
                        if (this.fieldType === 'faktura' || this.fieldType === 'amount') {
                            selectInvoiceOrAmount(this, selected);
                        } else {
                            selectAccount(this, selected.dataset.kontonr);
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
        debounceTimer = setTimeout(function() {
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
        debounceTimer = setTimeout(function() {
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

        let basePath = '';
        if (window.location.pathname.includes('/finans/')) {
            basePath = 'kassekladde_includes/accountSearch.php';
        } else {
            basePath = 'finans/kassekladde_includes/accountSearch.php';
        }
        const url = basePath + '?search=' + 
                    encodeURIComponent(searchValue) + 
                    '&type=' + searchType +
                    '&page=' + page;

        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text();
            })
            .then(function(text) {
                try {
                    const data = JSON.parse(text);
                    const results = data.results || data;
                    const pagination = data.pagination || { page: 1, total: results.length, hasMore: false };
                    renderDropdown(input, results, searchType, searchValue, pagination);
                } catch (e) {
                    console.error('JSON parse error:', e);
                }
            })
            .catch(function(error) {
                console.error('Account search error:', error);
                closeDropdown();
            });
    }

  
    function performInvoiceSearch(input, searchValue, page) {
        searchValue = searchValue.trim();
        page = page || 1;
        const dropdown = input.autocompleteDropdown;
        const rowNum = input.rowNumber;
        
        let accountNr = '';
        let accountType = '';
        
        const dTypeField = document.querySelector('input[name="d_ty' + rowNum + '"]');
        const debeField = document.querySelector('input[name="debe' + rowNum + '"]');
        if (dTypeField && debeField) {
            const dType = dTypeField.value.toUpperCase().trim();
            if (dType === 'D' || dType === 'K') {
                accountType = dType;
                accountNr = debeField.value.trim();
            }
        }
        
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

        let basePath = '';
        if (window.location.pathname.includes('/finans/')) {
            basePath = 'kassekladde_includes/invoiceSearch.php';
        } else {
            basePath = 'finans/kassekladde_includes/invoiceSearch.php';
        }
        
        const url = basePath + '?search=' + 
                    encodeURIComponent(searchValue) + 
                    '&account=' + encodeURIComponent(accountNr) +
                    '&accountType=' + encodeURIComponent(accountType) +
                    '&page=' + page;

        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text();
            })
            .then(function(text) {
                try {
                    const data = JSON.parse(text);
                    const results = data.results || data;
                    const pagination = data.pagination || { page: 1, total: results.length, hasMore: false };
                    renderInvoiceDropdown(input, results, searchValue, pagination);
                } catch (e) {
                    console.error('JSON parse error:', e);
                }
            })
            .catch(function(error) {
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
        } else {
            basePath = 'finans/kassekladde_includes/departmentSearch.php';
        }
        
        const url = basePath + '?search=' + encodeURIComponent(searchValue);

        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                const results = data.results || [];
                renderSimpleDropdown(input, results, 'department', searchValue);
            })
            .catch(function(error) {
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
        } else {
            basePath = 'finans/kassekladde_includes/employeeSearch.php';
        }
        
        const url = basePath + '?search=' + encodeURIComponent(searchValue);

        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                const results = data.results || [];
                renderSimpleDropdown(input, results, 'employee', searchValue);
            })
            .catch(function(error) {
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
        } else {
            basePath = 'finans/kassekladde_includes/currencySearch.php';
        }
        
        const url = basePath + '?search=' + encodeURIComponent(searchValue);

        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                const results = data.results || [];
                renderSimpleDropdown(input, results, 'currency', searchValue);
            })
            .catch(function(error) {
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
        } else {
            basePath = 'finans/kassekladde_includes/amountSearch.php';
        }
        
        const url = basePath + '?search=' + encodeURIComponent(searchValue) +
                    '&account=' + encodeURIComponent(accountNr) +
                    '&accountType=' + encodeURIComponent(accountType) +
                    '&invoice=' + encodeURIComponent(invoiceNr);

        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                const results = data.results || [];
                renderAmountDropdown(input, results, searchValue);
            })
            .catch(function(error) {
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
            
            results.forEach(function(item) {
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
        dropdown.style.display = 'flex';
        activeDropdown = dropdown;
        activeInput = input;
        
        // Keep focus on the original input field
        input.focus();
    }

  
    function renderDropdown(input, results, searchType, currentSearchValueParam, pagination) {
        const dropdown = input.autocompleteDropdown;
        const trans = getTrans();
        
        currentSearchValueParam = currentSearchValueParam || '';
        
        pagination = pagination || { page: 1, total: 0, hasMore: false };
        
        if (!results || results.length === 0) {
            if (currentSearchValueParam !== '') {
                dropdown.innerHTML = '<div class="account-autocomplete-header">' +
                    '<span class="account-autocomplete-header-title">' + trans.selectAccount + '</span>' +
                    '<button type="button" class="account-autocomplete-close-btn" data-action="close">' + trans.close + ' ✕</button>' +
                    '</div>' +
                    '<div class="account-autocomplete-no-results">' + trans.noResults + '</div>';
                positionDropdown(input, dropdown);
                dropdown.style.display = 'flex';
                activeDropdown = dropdown;
                activeInput = input;
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
        
        const titleText = searchType === 'finance' ? trans.selectAccount : 
                         (searchType === 'debitor' ? trans.selectDebtor : trans.selectCreditor);
        html += '<div class="account-autocomplete-header">';
        html += '<span class="account-autocomplete-header-title">' + titleText + '</span>';
        html += '<button type="button" class="account-autocomplete-close-btn" data-action="close">' + trans.close + ' ✕</button>';
        html += '</div>';
        
        html += '<div class="account-autocomplete-results">';
        html += '<table class="account-autocomplete-table">';
        
        if (searchType === 'finance') {
            html += '<thead><tr>' +
                    '<th style="width:70px;">' + trans.accountNo + '</th>' +
                    '<th>' + trans.description + '</th>' +
                    '<th style="width:45px;">' + trans.vat + '</th>' +
                    '<th style="width:50px;">' + trans.shortcut + '</th>' +
                    '<th style="width:90px;text-align:right;">' + trans.balance + '</th>' +
                    '</tr></thead>';
        } else {
            html += '<thead><tr>' +
                    '<th style="width:80px;">' + trans.accountNo + '</th>' +
                    '<th>' + trans.companyName + '</th>' +
                    '</tr></thead>';
        }
        
        html += '<tbody>';
        
        let itemIndex = 0;
        results.forEach(function(item) {
            if (item.kontotype === 'H') {
                html += '<tr class="account-autocomplete-category">' +
                        '<td colspan="5"><strong>' + escapeHtml(item.beskrivelse) + '</strong></td>' +
                        '</tr>';
            } else {
                html += '<tr class="account-autocomplete-item" data-kontonr="' + escapeHtml(item.kontonr) + '" data-index="' + itemIndex + '">';
                
                if (searchType === 'finance') {
                    html += '<td>' + escapeHtml(item.kontonr) + '</td>' +
                            '<td title="' + escapeHtml(item.beskrivelse) + '">' + escapeHtml(item.beskrivelse) + '</td>' +
                            '<td>' + escapeHtml(item.moms || '') + '</td>' +
                            '<td>' + escapeHtml(item.genvej || '') + '</td>' +
                            '<td style="text-align:right;">' + formatNumber(item.saldo) + '</td>';
                } else {
                    html += '<td>' + escapeHtml(item.kontonr) + '</td>' +
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
        
        dropdown.style.display = 'flex';
        activeDropdown = dropdown;
        activeInput = input;
        
        // Keep focus on the original input field
        input.focus();
    }

 
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
                dropdown.style.display = 'flex';
                activeDropdown = dropdown;
                activeInput = input;
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
        
        let itemIndex = 0;
        results.forEach(function(item) {
            const description = (item.firmanavn || '') + (item.faktnr ? ' - ' + item.faktnr : '');
            
            html += '<tr class="account-autocomplete-item"' +
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
            
            html += '<td>' + escapeHtml(item.kontonr) + '</td>' +
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
        
        dropdown.style.display = 'flex';
        activeDropdown = dropdown;
        activeInput = input;
        
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
            dropdown.style.display = 'flex';
            activeDropdown = dropdown;
            activeInput = input;
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
        results.forEach(function(item) {
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
        
        dropdown.style.display = 'flex';
        activeDropdown = dropdown;
        activeInput = input;
        
        // Keep focus on the original input field
        input.focus();
    }

  
    function selectAccount(input, kontonr) {
        clearTimeout(debounceTimer);
        
        selectionMade = true;
        
        closeDropdown();
        
        input.value = kontonr;
        
        // Dispatch both input and change events to ensure all handlers are triggered
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        
        // Ensure docChange is set (for unsaved changes warning)
        if (typeof window.docChange !== 'undefined') {
            window.docChange = true;
        }
        
        // Debug logging
        console.log('selectAccount:', input.name, '=', kontonr, 'form:', input.form ? input.form.id : 'no form');
        if (!input.form) {
            console.error('Input is not connected to form!', input.name);
        }

        const form = input.form;
        if (form) {
            const inputs = Array.from(form.querySelectorAll('input, select, textarea'));
            const currentIndex = inputs.indexOf(input);
            if (currentIndex > -1 && currentIndex < inputs.length - 1) {
                inputs[currentIndex + 1].focus();
            }
        }
        
        setTimeout(function() {
            selectionMade = false;
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
        
        // Debug logging
        console.log('setFieldValue:', field.name, '=', value, 'form:', field.form ? field.form.id : 'no form');
        if (!field.form) {
            console.error('Field is not connected to form!', field.name);
        }
    }

    function selectInvoiceOrAmount(input, item) {
        clearTimeout(debounceTimer);
        
        selectionMade = true;
        
        closeDropdown();
        
        const rowNum = input.rowNumber;
        
       
        const faktnr = item.dataset.faktnr || '';
        const amount = item.dataset.amount || '';
        const accountNr = item.dataset.accountnr || '';
        const accountType = item.dataset.accounttype || ''; 
        const description = item.dataset.description || '';
        const currency = item.dataset.currency || '';
        const offsetAccount = item.dataset.offsetaccount || ''; 
        
      
        console.log('selectInvoiceOrAmount:', {
            faktnr, amount, accountNr, accountType, description, currency, offsetAccount,
            rowNum, fieldType: input.fieldType
        });
        
        const faktField = document.querySelector('input[name="fakt' + rowNum + '"]');
        const beloField = document.querySelector('input[name="belo' + rowNum + '"]');
        const beskField = document.querySelector('input[name="besk' + rowNum + '"]');
        
        const dTypeField = document.querySelector('input[name="d_ty' + rowNum + '"]');
        const debeField = document.querySelector('input[name="debe' + rowNum + '"]');
        const kTypeField = document.querySelector('input[name="k_ty' + rowNum + '"]');
        const kredField = document.querySelector('input[name="kred' + rowNum + '"]');
        
        const amountValue = parseFloat(amount) || 0;
        
        if (input.fieldType === 'faktura') {
            setFieldValue(input, faktnr);
        } else if (input.fieldType === 'amount') {
            setFieldValue(input, formatNumberForInput(Math.abs(amountValue)));
        }
        
        // Fill invoice number
        if (faktField && faktnr && input.fieldType !== 'faktura') {
            setFieldValue(faktField, faktnr);
        }
        
        // Fill amount (always positive in the field)
        if (beloField && amount && input.fieldType !== 'amount') {
            setFieldValue(beloField, formatNumberForInput(Math.abs(amountValue)));
        }
        
        // Fill description (attachment text) - "Company name - Invoice number"
        if (beskField && description) {
            setFieldValue(beskField, description);
        }
        
        // Fill currency field
        const valuField = document.querySelector('input[name="valu' + rowNum + '"]');
        if (valuField && currency) {
            setFieldValue(valuField, currency);
        }
        
      
        const existingDebet = debeField ? debeField.value.trim() : '';
        const existingKredit = kredField ? kredField.value.trim() : '';
        const existingDType = dTypeField ? dTypeField.value.trim() : '';
        const existingKType = kTypeField ? kTypeField.value.trim() : '';
        
        if (accountNr && accountType) {
            if (amountValue < 0) {
                // Negative amount (e.g., credit note or payment received):
                // - Put the debtor/creditor account in DEBIT side (d_type=D/K, debet=accountNo)
                // - Put the offset account (bank) in CREDIT side (if not already filled)
                if (dTypeField && debeField) {
                    setFieldValue(dTypeField, accountType);
                    setFieldValue(debeField, accountNr);
                }
                // Fill offset account on CREDIT side only if not already filled
                if (kredField && !existingKredit && offsetAccount) {
                    setFieldValue(kredField, offsetAccount);
                }
                // Keep existing k_type, don't overwrite with 'F'
            } else {
                // Positive amount (e.g., invoice being paid):
                // - Put the debtor/creditor account in CREDIT side (k_type=D/K, kredit=accountNo)
                // - Put the offset account (bank) in DEBIT side (if not already filled)
                if (kTypeField && kredField) {
                    setFieldValue(kTypeField, accountType);
                    setFieldValue(kredField, accountNr);
                }
                // Fill offset account on DEBIT side only if not already filled
                if (debeField && !existingDebet && offsetAccount) {
                    setFieldValue(debeField, offsetAccount);
                }
                // Keep existing d_type, don't overwrite with 'F'
            }
        }
        
        // Debug logging after filling
        console.log('After fill:', {
            amountValue,
            branch: amountValue < 0 ? 'negative' : 'positive',
            dTypeField: dTypeField ? dTypeField.value : 'null',
            debeField: debeField ? debeField.value : 'null',
            kTypeField: kTypeField ? kTypeField.value : 'null',
            kredField: kredField ? kredField.value : 'null',
            existingDebet,
            existingKredit,
            offsetAccount
        });
        
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
        
        setTimeout(function() {
            selectionMade = false;
        }, 100);
    }

    function formatNumberForInput(value) {
        if (value === '' || value === null || value === undefined) return '';
        const num = parseFloat(value);
        if (isNaN(num)) return value;
        return num.toFixed(2).replace('.', ',');
    }

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
                if (selected && activeInput) {
                    e.preventDefault();
                    if (activeInput.fieldType === 'faktura' || activeInput.fieldType === 'amount') {
                        selectInvoiceOrAmount(activeInput, selected);
                    } else {
                        selectAccount(activeInput, selected.dataset.kontonr);
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
        items.forEach(function(item, i) {
            if (i === index) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }

  
    function closeDropdown() {
        if (activeDropdown) {
            activeDropdown.style.display = 'none';
            activeDropdown = null;
            activeInput = null;
        }
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

    window.addEventListener('load', function() {
        if (document.querySelectorAll('.account-autocomplete-wrapper').length === 0) {
            initAccountAutocomplete();
        }
    });

    window.initAccountAutocomplete = initAccountAutocomplete;

})();
