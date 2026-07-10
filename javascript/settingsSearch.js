
(function () {
    'use strict';

    const CONFIG = {
        debounceDelay: 200,
        minSearchLength: 0
    };

    function getTrans() {
        return window.saldiTranslations || {};
    }

    let dropdownContainer = null;
    let activeDropdown = null;
    let activeInput = null;
    let debounceTimer = null;

    function getDropdownContainer() {
        if (!dropdownContainer) {
            dropdownContainer = document.createElement('div');
            dropdownContainer.id = 'settings-search-container';
            dropdownContainer.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 99999;';
            document.body.appendChild(dropdownContainer);
        }
        return dropdownContainer;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function positionDropdown(input, dropdown) {
        const rect = input.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;

        dropdown.style.visibility = 'hidden';
        dropdown.style.display = 'block';
        const dropdownRect = dropdown.getBoundingClientRect();
        const dropdownHeight = dropdownRect.height || 300;
        const dropdownWidth = dropdownRect.width || 320;
        dropdown.style.visibility = 'visible';

        let top = rect.bottom + 2;
        let left = rect.left;

        if (top + dropdownHeight > viewportHeight) {
            top = rect.top - dropdownHeight - 2;
            if (top < 0) top = 10;
        }

        if (left + dropdownWidth > viewportWidth) {
            left = viewportWidth - dropdownWidth - 10;
        }
        if (left < 0) left = 10;

        dropdown.style.top = top + 'px';
        dropdown.style.left = left + 'px';
    }

    function closeDropdown() {
        if (activeDropdown) {
            activeDropdown.remove();
        }
        activeDropdown = null;
        activeInput = null;
    }

    function renderResults(input, results) {
        closeDropdown();

        const dropdown = document.createElement('div');
        dropdown.className = 'settings-search-dropdown';

        if (results.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'settings-search-no-results';
            empty.textContent = getTrans().settingsNoResults || 'Ingen resultater';
            dropdown.appendChild(empty);
        } else {
            const list = document.createElement('ul');
            list.className = 'settings-search-list';
            results.forEach(function (result) {
                const item = document.createElement('li');
                item.className = 'settings-search-item';
                let html = '<span class="settings-search-main">';
                html += '<span class="settings-search-label">' + escapeHtml(result.label) + '</span>';
                if (result.matchType === 'keyword' && result.matchedTerm) {
                    html += '<span class="settings-search-hint">' + escapeHtml(getTrans().settingsMatchHint || 'Match') + ': ' + escapeHtml(result.matchedTerm) + '</span>';
                }
                html += '</span>';
                html += '<span class="settings-search-category">' + escapeHtml(result.category) + '</span>';
                item.innerHTML = html;
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    window.location.href = result.url;
                });
                list.appendChild(item);
            });
            dropdown.appendChild(list);
        }

        getDropdownContainer().appendChild(dropdown);
        positionDropdown(input, dropdown);
        activeDropdown = dropdown;
        activeInput = input;
    }

    function performSearch(input) {
        const search = input.value.trim();
        if (search.length < CONFIG.minSearchLength) {
            closeDropdown();
            return;
        }

        fetch('settingsSearch.php?search=' + encodeURIComponent(search))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                renderResults(input, data.results || []);
            })
            .catch(function () {
                closeDropdown();
            });
    }

    function handleInput(input) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            performSearch(input);
        }, CONFIG.debounceDelay);
    }

    function handleKeyboardNavigation(e) {
        if (!activeDropdown) return;

        const items = activeDropdown.querySelectorAll('.settings-search-item');
        if (items.length === 0) return;

        const selected = activeDropdown.querySelector('.settings-search-item.selected');
        let selectedIndex = -1;
        if (selected) {
            for (let i = 0; i < items.length; i++) {
                if (items[i] === selected) { selectedIndex = i; break; }
            }
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (selectedIndex < items.length - 1) {
                    selectItemByIndex(items, selectedIndex + 1);
                } else if (selectedIndex === -1) {
                    selectItemByIndex(items, 0);
                }
                break;
            case 'ArrowUp':
                e.preventDefault();
                if (selectedIndex > 0) selectItemByIndex(items, selectedIndex - 1);
                break;
            case 'Enter':
                if (selected) {
                    e.preventDefault();
                    selected.dispatchEvent(new Event('mousedown'));
                }
                break;
            case 'Escape':
                e.preventDefault();
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

    function initSettingsSearch() {
        const inputs = document.querySelectorAll('.settings-search-input');
        if (inputs.length === 0) return;

        inputs.forEach(function (input) {
            if (input.settingsSearchInitialized) return;
            input.settingsSearchInitialized = true;

            input.addEventListener('input', function () { handleInput(input); });
            input.addEventListener('focus', function () {
                if (input.value.trim() !== '') handleInput(input);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (activeDropdown) handleKeyboardNavigation(e);
        });

        document.addEventListener('mousedown', function (e) {
            if (activeDropdown && !activeDropdown.contains(e.target) && !(e.target.classList && e.target.classList.contains('settings-search-input'))) {
                closeDropdown();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSettingsSearch);
    } else {
        initSettingsSearch();
    }
})();
