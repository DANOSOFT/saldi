


class PopupManager {
    activeDropdown = null;
    activeInput = null;
    debounceTimer = null;
    popupContainer = null;
    selectionMade = false;
    constructor(){
        console.log('bilagsmatch.js loaded - version with logging');
    }
    init(){
        const CONFIG = {
            minSearchLength: 1,
            debounceDelay: 200,
            maxResults: 50
        };

        function getPopupContainer() {
            if (!Boolean(popupContainer)) {
                popupContainer = document.createElement('div');
                popupContainer.id = 'bilagsmatch-container';
                document.body.appendChild(popupContainer);
            }
            return popupContainer;
        }

        function popup(input) {
            clearTimeout(debounceTimer);
            
            

            debounceTimer = setTimeout(function () {
                performSearch(input, value);
            }, CONFIG.debounceDelay);
        }

        function renderDropdown(results) {
            const dropdown = input.autocompleteDropdown;
            const type = input.autocompleteType;

            let title = 'Select Attachments'; //TODO Language

            let html = `
                <div class="ordre-autocomplete-header">
                    <span class="ordre-autocomplete-header-title">${title}</span>
                    <button type="button" class="ordre-autocomplete-attach-btn">Attach &times;</button>
                    <button type="button" class="ordre-autocomplete-close-btn">Close &times;</button>
                </div>
                <div class="ordre-autocomplete-results">
            `; //TODO Language


            // Alignment/Precision images
            const alignments = Array.from([
                "../ikoner/paper.png",
                "../ikoner/checkmrk.png"
            ]);

            if (!results || results.length === 0) {
                html += '<div class="ordre-autocomplete-no-results">Ingen resultater fundet</div>';
            } else {
                html += '<table class="ordre-autocomplete-table"><thead><tr>';

                html += '<th class="bilags-checkmark">Add.</th>'; //TODO Language
                html += '<th class="bilags-date">Date.</th>'; //TODO Language
                html += '<th class="bilags-subject">Subject.</th>'; //TODO Language
                html += '<th class="bilags-attach">attachment</th>'; //TODO Language
                html += '<th class="bilags-amount">Amount</th>'; //TODO Language
                html += '<th class="bilags-acc">Account</th>'; //TODO Language
                html += '<th class="bilags-rec">Reciever</th>'; //TODO Language
                html += '<th class="bilags-valuta">valuta</th>'; //TODO Language
                html += '<th class="bilags-alignment">Alignment</th>'; //TODO Language

                html += '</tr></thead><tbody>';

                results.forEach(item => {
                    html += `<tr class="autocomplete-item" data-id="${id}">`;

                    html += `<td><input class='active-checkbox' type='checkbox' checked/></td>`;
                    html += `<td>${item.file_date}</td>`;
                    html += `<td>${item.subject}</td>`;
                    html += `<td>${item.filename}</td>`;
                    html += `<td>${item.amount}</td>`;
                    html += `<td>${item.account ?? ''}</td>`;
                    html += `<td>${item.invoice_number ?? ''}</td>`;
                    html += `<td>${item.currency ?? ''}</td>`;
                    html += `<td><img src='${alignments[item.aligns]}'/></td>`;

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
            dropdown.style.display = 'flex';
            activeDropdown = dropdown;

            // Event listeners for results
            dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                item.addEventListener('mouseenter', function () {
                    dropdown.querySelectorAll('.autocomplete-item').forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                });
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    item.querySelector('input', (box) => box.checked = !box.checked);
                });
            });

            dropdown.querySelector('.ordre-autocomplete-attach-btn').addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                attachAttachments();
            });

            // Close button
            dropdown.querySelector('.ordre-autocomplete-close-btn').addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                closeDropdown();
            });
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

            // Set value of the imput to selected value - moved from individual statements in type if statement
            input.value = value;

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
                const vsc = selected.dataset.vsc;
                if (vsc) redirectUrl += `&vsc=${encodeURIComponent(vsc)}`;

                console.log('FINAL redirectUrl:', redirectUrl);

                var submitButton = document.getElementById("submit");
                submitButton.url = redirectUrl;
                
                submitButton.click();
                //window.location.href = redirectUrl;
            } else if (type === 'customer') {
                const urlParams = new URLSearchParams(window.location.search);
                const orderId = urlParams.get('id');

                var submitButton = document.getElementById("submit");
                if (input.name === 'newAccountNo') {
                    submitButton.url = `ordre.php?id=${orderId}&swap_account=swap&newAccountNo=${value}`;
                    //window.location.href = `ordre.php?id=${orderId}&swap_account=swap&newAccountNo=${value}`;
                } else {
                    submitButton.url = `ordre.php?konto_id=${id}`;
                    //window.location.href = `ordre.php?konto_id=${id}`;
                }
                submitButton.click();
            } else {
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

    }
}
popup.init();
