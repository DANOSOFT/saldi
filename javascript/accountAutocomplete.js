
(function() {
    'use strict';

    const CONFIG = {
        minSearchLength: 0,
        debounceDelay: 200,
        maxResults: 50
    };

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
        if (input.parentNode && input.parentNode.classList && 
            input.parentNode.classList.contains('account-autocomplete-wrapper')) {
            return;
        }
        
        let parentElement = input.parentNode;
        
        const wrapper = document.createElement('div');
        wrapper.className = 'account-autocomplete-wrapper';
        wrapper.style.position = 'relative';
        wrapper.style.display = 'inline-block';
        
        parentElement.insertBefore(wrapper, input);
        wrapper.appendChild(input);

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
                    performSearchWithValue(input, currentSearchValue, page);
                }
                return;
            }
            
            const item = e.target.closest('.account-autocomplete-item');
            if (item) {
                e.preventDefault();
                e.stopPropagation();
                const kontonr = item.dataset.kontonr;
                if (kontonr) {
                    selectAccount(input, kontonr);
                }
                return;
            }
            
            if (e.target.classList.contains('account-autocomplete-search-input')) {
                return;
            }
            
            e.preventDefault();
        });
        
        dropdown.addEventListener('input', function(e) {
            if (e.target.classList.contains('account-autocomplete-search-input')) {
                if (selectionMade) {
                    return;
                }
                
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    performSearchWithValue(input, e.target.value, 1);
                }, CONFIG.debounceDelay);
            }
        });
        
        dropdown.addEventListener('keydown', function(e) {
            if (e.target.classList.contains('account-autocomplete-search-input')) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeDropdown();
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const items = dropdown.querySelectorAll('.account-autocomplete-item');
                    if (items.length > 0) {
                        selectItemByIndex(items, 0);
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const selected = dropdown.querySelector('.account-autocomplete-item.selected');
                    if (selected) {
                        selectAccount(input, selected.dataset.kontonr);
                    } else {
                        // Select first item if none selected
                        const firstItem = dropdown.querySelector('.account-autocomplete-item');
                        if (firstItem) {
                            selectAccount(input, firstItem.dataset.kontonr);
                        }
                    }
                }
            }
        });

        input.autocompleteDropdown = dropdown;
        input.autocompleteWrapper = wrapper;
        input.fieldType = fieldType;

        const rowMatch = input.name.match(/\d+$/);
        input.rowNumber = rowMatch ? rowMatch[0] : '';

        input.addEventListener('input', function(e) {
            handleInput(this);
        });

        input.addEventListener('focus', function(e) {
            handleInput(this);
        });

        input.addEventListener('blur', function(e) {
            // console.log('blur', e)
         
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

  
    function renderDropdown(input, results, searchType, currentSearchValueParam, pagination) {
        const dropdown = input.autocompleteDropdown;
        
        currentSearchValueParam = currentSearchValueParam || '';
        
        pagination = pagination || { page: 1, total: 0, hasMore: false };
        
        if (!results || results.length === 0) {
            if (currentSearchValueParam !== '') {
                dropdown.innerHTML = '<div class="account-autocomplete-header">' +
                    '<span class="account-autocomplete-header-title">Select Account</span>' +
                    '<button type="button" class="account-autocomplete-close-btn" data-action="close">Close ✕</button>' +
                    '</div>' +
                    '<div class="account-autocomplete-search-box">' +
                    '<input type="text" class="account-autocomplete-search-input" placeholder="Search by account no. or description..." value="' + escapeHtml(currentSearchValueParam) + '">' +
                    '</div>' +
                    '<div class="account-autocomplete-no-results">No accounts found</div>';
                positionDropdown(input, dropdown);
                dropdown.style.display = 'flex';
                activeDropdown = dropdown;
                activeInput = input;
                const searchInput = dropdown.querySelector('.account-autocomplete-search-input');
                if (searchInput) {
                    searchInput.focus();
                }
                return;
            }
            dropdown.style.display = 'none';
            activeDropdown = null;
            activeInput = null;
            return;
        }

        let html = '';
        
        const titleText = searchType === 'finance' ? 'Select Account' : 
                         (searchType === 'debitor' ? 'Select Debtor' : 'Select Creditor');
        html += '<div class="account-autocomplete-header">';
        html += '<span class="account-autocomplete-header-title">' + titleText + '</span>';
        html += '<button type="button" class="account-autocomplete-close-btn" data-action="close">Close ✕</button>';
        html += '</div>';
        
        html += '<div class="account-autocomplete-search-box">';
        html += '<input type="text" class="account-autocomplete-search-input" placeholder="Search by account no. or description..." value="' + escapeHtml(currentSearchValueParam) + '">';
        html += '</div>';
        
        html += '<div class="account-autocomplete-results">';
        html += '<table class="account-autocomplete-table">';
        
        if (searchType === 'finance') {
            html += '<thead><tr>' +
                    '<th style="width:70px;">Account</th>' +
                    '<th>Description</th>' +
                    '<th style="width:45px;">VAT</th>' +
                    '<th style="width:50px;">Shortcut</th>' +
                    '<th style="width:90px;text-align:right;">Balance</th>' +
                    '</tr></thead>';
        } else {
            html += '<thead><tr>' +
                    '<th style="width:80px;">Account</th>' +
                    '<th>Company Name</th>' +
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
            html += '<span class="account-autocomplete-pagination-info">Showing ' + startItem + '-' + endItem + ' of ' + pagination.total + '</span>';
            
            html += '<div class="account-autocomplete-pagination-buttons">';
            if (pagination.page > 1) {
                html += '<button type="button" class="account-autocomplete-page-btn" data-page="' + (pagination.page - 1) + '">← Previous</button>';
            }
            if (pagination.hasMore) {
                html += '<button type="button" class="account-autocomplete-page-btn" data-page="' + (pagination.page + 1) + '">Next →</button>';
            }
            html += '</div>';
            html += '</div>';
        }
        
        const existingSearchInput = dropdown.querySelector('.account-autocomplete-search-input');
        const searchValueToRestore = existingSearchInput ? existingSearchInput.value : currentSearchValueParam;
        const cursorPos = existingSearchInput ? existingSearchInput.selectionStart : searchValueToRestore.length;
        
        dropdown.innerHTML = html;
        
        // Position the dropdown below the input using fixed positioning
        positionDropdown(input, dropdown);
        
        dropdown.style.display = 'flex';
        activeDropdown = dropdown;
        activeInput = input;
        
        const searchInput = dropdown.querySelector('.account-autocomplete-search-input');
        if (searchInput) {
            searchInput.value = searchValueToRestore;
            searchInput.focus();
            try {
                searchInput.setSelectionRange(cursorPos, cursorPos);
            } catch (e) {
               console.log('error in autocomplte', e)

            }
        }
    }

  
    function selectAccount(input, kontonr) {
        clearTimeout(debounceTimer);
        
        selectionMade = true;
        
        closeDropdown();
        
        input.value = kontonr;
        
        const event = new Event('change', { bubbles: true });
        input.dispatchEvent(event);
        
        if (typeof docChange !== 'undefined') {
            docChange = true;
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

    function handleKeyboardNavigation(e) {
        if (!activeDropdown) return;
        
        if (e.target.classList && e.target.classList.contains('account-autocomplete-search-input')) {
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
                if (selected && activeInput) {
                    e.preventDefault();
                    selectAccount(activeInput, selected.dataset.kontonr);
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
