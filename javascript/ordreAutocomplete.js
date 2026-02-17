

(function () {
    'use strict';
    console.log('ordreAutocomplete.js loaded - version with logging');

    const CONFIG = {
        minSearchLength: 1,
        debounceDelay: 200,
        maxResults: 50
    };

    let activeDropdown = null;
    let activeInput = null;
    let debounceTimer = null;
    let dropdownContainer = null;
    let selectionMade = false;

    function getDropdownContainer() {
        if (!dropdownContainer) {
            dropdownContainer = document.createElement('div');
            dropdownContainer.id = 'ordre-autocomplete-container';
            document.body.appendChild(dropdownContainer);
        }
        return dropdownContainer;
    }

    function initOrdreAutocomplete() {
        // Item fields
        document.querySelectorAll('input[name="vare0"], textarea[name="beskrivelse0"]').forEach(input => {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'item');
                input.autocompleteInitialized = true;
            }
        });

        // Customer account fields
        document.querySelectorAll('input[name="kontonr"], input[name="newAccountNo"]').forEach(input => {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'customer');
                input.autocompleteInitialized = true;
            }
        });

        // Currency fields
        document.querySelectorAll('input[name="ny_valuta"], input[name="valuta"]').forEach(input => {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'currency');
                input.autocompleteInitialized = true;
            }
        });

        // Employee fields
        document.querySelectorAll('input[name="ref"]').forEach(input => {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'employee');
                input.autocompleteInitialized = true;
            }
        });

        // Project fields
        document.querySelectorAll('input[name^="projekt"]').forEach(input => {
            if (!input.autocompleteInitialized) {
                setupAutocomplete(input, 'project');
                input.autocompleteInitialized = true;
            }
        });

        document.addEventListener('mousedown', function (e) {
            if (activeDropdown) {
                if (activeDropdown.contains(e.target)) return;
                if (activeInput && (e.target === activeInput || activeInput.contains(e.target))) return;
                closeDropdown();
            }
        });
    }

    function setupAutocomplete(input, type) {
        console.log('setupAutocomplete called for:', input.name, type);
        const dropdown = document.createElement('div');
        dropdown.className = 'ordre-autocomplete-dropdown';
        dropdown.style.display = 'none';
        getDropdownContainer().appendChild(dropdown);

        input.autocompleteType = type;
        input.autocompleteDropdown = dropdown;

        input.addEventListener('input', function () {
            if (selectionMade) return;
            handleInput(this);
        });

        input.addEventListener('focus', function () {
            if (selectionMade) return;
            if (this.value.length >= CONFIG.minSearchLength || type !== 'item') {
                handleInput(this);
            }
        });

        input.addEventListener('keydown', function (e) {
            if (!activeDropdown || activeInput !== this) return;

            const items = activeDropdown.querySelectorAll('.autocomplete-item');
            const selected = activeDropdown.querySelector('.autocomplete-item.selected');
            let selectedIndex = Array.from(items).indexOf(selected);

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
                        handleSelection(this, selected);
                    }
                    break;
                case 'Escape':
                    e.preventDefault();
                    closeDropdown();
                    break;
            }
        });
    }

    function handleInput(input) {
        clearTimeout(debounceTimer);
        const value = input.value.trim();

        if (input.autocompleteType === 'item' && value.length < CONFIG.minSearchLength) {
            closeDropdown();
            return;
        }

        debounceTimer = setTimeout(function () {
            performSearch(input, value);
        }, CONFIG.debounceDelay);
    }

    function performSearch(input, value) {
        let url = '';
        const type = input.autocompleteType;
        const basePath = '';
        const kassePath = '../finans/kassekladde_includes/';

        switch (type) {
            case 'item':
                url = basePath + 'itemSearch.php?search=' + encodeURIComponent(value);
                // Add VAT parameters from the form - look for hidden inputs
                const inclMomsInput = document.querySelector('input[name="incl_moms"]');
                const momssatsInput = document.querySelector('input[name="momssats"]');
                if (inclMomsInput && inclMomsInput.value) {
                    url += '&incl_moms=' + encodeURIComponent(inclMomsInput.value);
                }
                if (momssatsInput && momssatsInput.value) {
                    url += '&momssats=' + encodeURIComponent(momssatsInput.value);
                }
                break;
            case 'customer':
                url = kassePath + 'accountSearch.php?type=debitor&search=' + encodeURIComponent(value);
                break;
            case 'currency':
                url = kassePath + 'currencySearch.php?search=' + encodeURIComponent(value);
                break;
            case 'employee':
                url = kassePath + 'employeeSearch.php?search=' + encodeURIComponent(value);
                break;
            case 'project':
                url = basePath + 'projectSearch.php?search=' + encodeURIComponent(value);
                break;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                renderDropdown(input, data.results);
            })
            .catch(error => {
                console.error('Search error:', error);
                closeDropdown();
            });
    }

    function renderDropdown(input, results) {
        const dropdown = input.autocompleteDropdown;
        const type = input.autocompleteType;

        let title = 'Select ';
        switch (type) {
            case 'item': title += 'Item'; break;
            case 'customer': title += 'Account'; break;
            case 'currency': title += 'Currency'; break;
            case 'employee': title += 'Employee'; break;
            case 'project': title += 'Project'; break;
        }

        let html = `
            <div class="ordre-autocomplete-header">
                <span class="ordre-autocomplete-header-title">${title}</span>
                <button type="button" class="ordre-autocomplete-close-btn">Close &times;</button>
            </div>
            <div class="ordre-autocomplete-results">
        `;

        if (!results || results.length === 0) {
            html += '<div class="ordre-autocomplete-no-results">Ingen resultater fundet</div>';
        } else {
            html += '<table class="ordre-autocomplete-table"><thead><tr>';

            if (type === 'item') {
                html += '<th style="width: 100px;">Varenr.</th>';
                html += '<th>Beskrivelse</th>';
                html += '<th style="width: 80px; text-align: right;">Pris</th>';
            } else if (type === 'customer') {
                html += '<th style="width: 100px;">Kontonr.</th>';
                html += '<th>Navn</th>';
            } else {
                html += '<th style="width: 100px;">Kode</th>';
                html += '<th>Beskrivelse</th>';
            }

            html += '</tr></thead><tbody>';

            results.forEach(item => {
                const id = item.id || item.kontonr || item.code;
                const val = item.varenr || item.kontonr || item.code || item.initials;

                html += `<tr class="autocomplete-item" data-id="${id}" data-value="${val}">`;

                if (type === 'item') {
                    html += `<td class="code-cell">${escapeHtml(item.varenr)}</td>`;
                    html += `<td>${escapeHtml(item.beskrivelse)}</td>`;
                    html += `<td style="text-align: right;">${item.salgspris.toFixed(2)}</td>`;
                } else if (type === 'customer') {
                    html += `<td class="code-cell">${escapeHtml(item.kontonr)}</td>`;
                    html += `<td>${escapeHtml(item.beskrivelse)}</td>`;
                } else {
                    html += `<td class="code-cell">${escapeHtml(item.code || item.initials)}</td>`;
                    html += `<td>${escapeHtml(item.description || item.name)}</td>`;
                }

                html += '</tr>';
            });
            html += '</tbody></table>';
        }

        html += `</div>
            <div class="ordre-autocomplete-footer">
                <span class="ordre-autocomplete-info">Viser ${results ? results.length : 0} resultater</span>
            </div>
        `;

        dropdown.innerHTML = html;

        positionDropdown(input, dropdown);
        dropdown.style.display = 'flex';
        activeDropdown = dropdown;
        activeInput = input;

        // Event listeners for results
        dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
            item.addEventListener('mouseenter', function () {
                dropdown.querySelectorAll('.autocomplete-item').forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');
            });
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                handleSelection(input, this);
            });
        });

        // Close button
        dropdown.querySelector('.ordre-autocomplete-close-btn').addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeDropdown();
        });
    }

    function positionDropdown(input, dropdown) {
        const rect = input.getBoundingClientRect();
        const dropdownHeight = 450;
        const windowHeight = window.innerHeight;

        let top = rect.bottom + window.scrollY;

        // If dropdown would go off screen, show it above the input
        if (rect.bottom + dropdownHeight > windowHeight) {
            top = rect.top + window.scrollY - dropdownHeight;
        }

        dropdown.style.top = top + 'px';
        dropdown.style.left = (rect.left + window.scrollX) + 'px';
    }

    function selectItemByIndex(items, index) {
        items.forEach(item => item.classList.remove('selected'));
        if (items[index]) {
            items[index].classList.add('selected');
            items[index].scrollIntoView({ block: 'nearest' });
        }
    }

    function handleSelection(input, selected) {
        selectionMade = true;
        const type = input.autocompleteType;
        const value = selected.dataset.value;
        const id = selected.dataset.id;

        console.log('=== ordreAutocomplete handleSelection ===');
        console.log('type:', type);
        console.log('selected value:', value);
        console.log('selected id:', id);
        console.log('input.name:', input.name);
        console.log('window.location.href:', window.location.href);
        console.log('window.location.search:', window.location.search);

        if (type === 'item') {
            const urlParams = new URLSearchParams(window.location.search);
            let orderId = urlParams.get('id');
            let kontoId = urlParams.get('konto_id');
            console.log('orderId from URL:', orderId);
            console.log('kontoId from URL:', kontoId);

            // Fallback: read from form hidden inputs (page is often loaded via POST, so URL may not have these)
            // Fallback: read from form hidden inputs
            if (!orderId) {
                // First try the form that the input belongs to (most reliable)
                if (input.form) {
                    const formIdInput = input.form.querySelector('input[name="id"]');
                    console.log('Searching for ID in input.form:', input.form);
                    console.log('Found in form?', formIdInput);
                    if (formIdInput) orderId = formIdInput.value;
                }

                // If not found in form (or no form), try global scope
                if (!orderId) {
                    const idInput = document.querySelector('input[name="id"]');
                    console.log('Searching for ID globally, found:', idInput);
                    if (idInput) orderId = idInput.value;
                }
            }
            if (!kontoId) {
                const kontoInput = document.querySelector('input[name="konto_id"]');
                console.log('kontoInput element:', kontoInput);
                console.log('kontoInput value:', kontoInput ? kontoInput.value : 'NOT FOUND');
                if (kontoInput) kontoId = kontoInput.value;
            }

            // Also log ALL hidden inputs with name="id" on the page
            const allIdInputs = document.querySelectorAll('input[name="id"]');
            console.log('All input[name="id"] on page:', allIdInputs.length);
            allIdInputs.forEach((el, i) => {
                console.log(`  [${i}] type=${el.type} value=${el.value} form=${el.form ? el.form.id || el.form.name || 'unnamed' : 'none'}`);
            });

            let redirectUrl = 'ordre.php?';
            if (orderId) {
                redirectUrl += `id=${orderId}&`;
            }
            if (kontoId) {
                redirectUrl += `konto_id=${kontoId}&`;
            }
            redirectUrl += `vare_id=${id}`;

            console.log('FINAL redirectUrl:', redirectUrl);
            window.location.href = redirectUrl;
        } else if (type === 'customer') {
            const urlParams = new URLSearchParams(window.location.search);
            const orderId = urlParams.get('id');
            if (input.name === 'newAccountNo') {
                window.location.href = `ordre.php?id=${orderId}&swap_account=swap&newAccountNo=${value}`;
            } else {
                window.location.href = `ordre.php?konto_id=${id}`;
            }
        } else {
            input.value = value;
            closeDropdown();
            const event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
        }

        setTimeout(() => { selectionMade = false; }, 100);
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOrdreAutocomplete);
    } else {
        initOrdreAutocomplete();
    }

    window.initOrdreAutocomplete = initOrdreAutocomplete;

})();
