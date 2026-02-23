document.addEventListener('DOMContentLoaded', function () {
    console.log('Order drag-and-drop script loading...');

    const statusInput = document.querySelector('input[name="status"]');
    const status = statusInput ? parseInt(statusInput.value) : 0;

    if (status >= 3) {
        console.log('This is an INVOICE (status >= 3) - ordre_dragdrop.js will NOT initialize');
        return;
    }

    console.log('This is an ORDER (status < 3) - ordre_dragdrop.js will initialize');

    let orderTableBody = null;
    document.querySelectorAll('table').forEach(table => {
        const candidate = table.querySelector('tbody');
        if (candidate && candidate.querySelector('tr.ordrelinje')) {
            orderTableBody = candidate;
        }
    });

    if (!orderTableBody) {
        console.log('No order lines found yet - this is normal for new orders');
        return;
    }

    console.log('Found order table body with existing order lines:', orderTableBody);

    const ordreIdInput = document.querySelector('input[name="id"]');
    const ordreId = ordreIdInput ? ordreIdInput.value : null;
    console.log('Current ordre_id detected:', ordreId);

    let saveTimeout = null;
    let isDragging = false;

    // === COLLECTION HELPER FUNCTIONS ===

    /**
     * Get the saet (collection group) value for a row
     * @param {HTMLElement} row - The table row element
     * @returns {string|null} The saet value or null if not in a collection
     */
    function getSaetValue(row) {
        const saetInput = row.querySelector('input[name^="saet["]');
        if (saetInput && saetInput.value && saetInput.value !== '0' && saetInput.value !== '') {
            return saetInput.value;
        }
        return null;
    }

    /**
     * Get the samlevare value for a row
     * @param {HTMLElement} row - The table row element
     * @returns {string|null} The samlevare value
     */
    function getSamlevareValue(row) {
        const samlevareInput = row.querySelector('input[name^="samlevare["]');
        return samlevareInput ? samlevareInput.value : null;
    }

    /**
     * Check if a row is the main item of a collection (samlevare='on')
     * @param {HTMLElement} row - The table row element
     * @returns {boolean}
     */
    function isMainCollectionItem(row) {
        return getSamlevareValue(row) === 'on';
    }

    /**
     * Check if a row is part of a collection (has a non-zero saet value)
     * @param {HTMLElement} row - The table row element
     * @returns {boolean}
     */
    function isInCollection(row) {
        return getSaetValue(row) !== null;
    }

    /**
     * Get all rows that belong to the same collection as the given row
     * @param {HTMLElement} row - The table row element
     * @returns {HTMLElement[]} Array of rows in the same collection
     */
    function getCollectionRows(row) {
        const saet = getSaetValue(row);
        if (!saet) return [row];

        const allRows = orderTableBody.querySelectorAll('tr.ordrelinje');
        const collectionRows = [];

        allRows.forEach(r => {
            if (getSaetValue(r) === saet) {
                collectionRows.push(r);
            }
        });

        return collectionRows;
    }

    /**
     * Move an array of rows to a specific position in the table body
     * @param {HTMLElement[]} rows - Rows to move (should be in desired order)
     * @param {HTMLElement|null} insertBeforeRow - Insert before this row, or null to append
     */
    function moveRowsTogether(rows, insertBeforeRow) {
        rows.forEach(row => {
            if (insertBeforeRow) {
                orderTableBody.insertBefore(row, insertBeforeRow);
            } else {
                orderTableBody.appendChild(row);
            }
        });
    }

    /**
     * Find the main item (samlevare='on') for a collection
     * @param {string} saet - The collection group ID
     * @returns {HTMLElement|null} The main row or null
     */
    function findMainItemForCollection(saet) {
        const allRows = orderTableBody.querySelectorAll('tr.ordrelinje');
        for (const row of allRows) {
            if (getSaetValue(row) === saet && isMainCollectionItem(row)) {
                return row;
            }
        }
        return null;
    }

    // === SORTABLE INSTANCE ===

    const sortable = new Sortable(orderTableBody, {
        handle: '.drag-handle',
        draggable: '.ordrelinje',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        animation: 150,
        forceFallback: true,

        onStart(evt) {
            isDragging = true;
            evt.item.classList.add('dragging');
            document.body.classList.add('is-dragging');
            console.log('=== DRAG STARTED ===');

            // Highlight all items in the same collection
            const saet = getSaetValue(evt.item);
            if (saet) {
                console.log('Dragging collection item with saet:', saet);
                const collectionRows = getCollectionRows(evt.item);
                collectionRows.forEach(row => {
                    row.classList.add('collection-dragging');
                    row.style.backgroundColor = '#fffacd'; // Light yellow highlight
                });
            }
        },

        onEnd(evt) {
            isDragging = false;
            evt.item.classList.remove('dragging');
            document.body.classList.remove('is-dragging');
            console.log('=== DRAG ENDED ===');

            // Remove collection highlighting
            orderTableBody.querySelectorAll('.collection-dragging').forEach(row => {
                row.classList.remove('collection-dragging');
                row.style.backgroundColor = '';
            });

            const saet = getSaetValue(evt.item);

            if (saet) {
                // This item is part of a collection - ensure all collection items stay together
                console.log('Reorganizing collection with saet:', saet);

                // Get all collection rows
                const collectionRows = getCollectionRows(evt.item);

                // Sort collection rows: Main Item LAST, then by ORIGINAL posnr
                collectionRows.sort((a, b) => {
                    const isMainA = isMainItem(a);
                    const isMainB = isMainItem(b);

                    if (isMainA && !isMainB) return 1;  // A (Main) comes AFTER B
                    if (!isMainA && isMainB) return -1; // B (Main) comes AFTER A

                    const posA = parseInt(a.querySelector('input[name^="posn"]')?.value || '0', 10);
                    const posB = parseInt(b.querySelector('input[name^="posn"]')?.value || '0', 10);
                    return posA - posB;
                });

                // Find the correct insertion point
                // We want to insert the whole group at the position of the dragged item
                // But we need to find a reference node that is NOT part of the collection
                let insertBefore = evt.item.nextElementSibling;
                while (insertBefore && isInCollection(insertBefore) && getSaetValue(insertBefore) === saet) {
                    insertBefore = insertBefore.nextElementSibling;
                }

                // Move all collection rows together to the new position
                moveRowsTogether(collectionRows, insertBefore);

                console.log('Collection reorganized: moved', collectionRows.length, 'rows together');
            }

            updatePositionNumbers();

            // Clear any existing timeout
            if (saveTimeout) {
                clearTimeout(saveTimeout);
            }

            // Delay the save to allow other button clicks to take priority
            saveTimeout = setTimeout(() => {
                if (!isDragging) {
                    const success = submitOrderForm();
                    if (success) {
                        showMessage('Order updated and saved.', 'success');
                    } else {
                        showMessage('Drag updated, but form save failed.', 'error');
                    }
                }
            }, 500); // Wait 500ms before auto-saving

            if (typeof docChange !== 'undefined') {
                docChange = true;
            }
        }
    });

    // Prevent auto-save when clicking other buttons (but not for programmatic clicks)
    const allButtons = document.querySelectorAll('input[type="submit"], button[type="submit"]');
    allButtons.forEach(button => {
        button.addEventListener('click', function () {
            // Skip if this is a programmatic click from our auto-save
            if (this.dataset.programmaticClick === 'true') {
                console.log('Programmatic save click - not cancelling');
                delete this.dataset.programmaticClick;
                return;
            }
            if (saveTimeout) {
                clearTimeout(saveTimeout);
                console.log('Auto-save cancelled - user clicked button:', this.name || this.value);
            }
        });
    });

    function updatePositionNumbers() {
        const orderRows = orderTableBody.querySelectorAll('tr.ordrelinje');
        console.log('=== UPDATING POSITION NUMBERS for ordre_id:', ordreId, '===');

        // Filter out row 0 (add new item row) and rows without position inputs
        const validRows = Array.from(orderRows).filter(row => {
            const lineId = row.getAttribute('data-line-id');
            // Skip row 0 (the add new item row)
            if (lineId === '0' || lineId === null) return false;
            // Skip rows without position inputs (summary rows, etc.)
            const hasPosInput = row.querySelector('input[name^="posn"]:not([name="posn0"])');
            return hasPosInput !== null;
        });

        let step = 100;
        if (validRows.length > 1) {
            const firstVal = parseInt(validRows[0].querySelector('input[name^="posn"]:not([name="posn0"])')?.value || '0', 10);
            const secondVal = parseInt(validRows[1].querySelector('input[name^="posn"]:not([name="posn0"])')?.value || '0', 10);
            const detectedStep = secondVal - firstVal;
            if (detectedStep > 0) step = detectedStep;
        }

        console.log('ℹ Step size used:', step, '- Valid rows:', validRows.length);

        validRows.forEach((row, index) => {
            // Update ALL posn inputs in the row (there may be multiple hidden inputs)
            const posInputs = row.querySelectorAll('input[name^="posn"]:not([name="posn0"])');
            const newPos = (index + 1) * step;

            posInputs.forEach(posInput => {
                posInput.value = newPos;
            });

            console.log(`Row ${index + 1} (line-id=${row.getAttribute('data-line-id')}) → posnr=${newPos}`);
        });
    }

    function submitOrderForm() {
        console.log('submitOrderForm() called - attempting to save...');
        const form = document.querySelector('form[name="ordre"]');
        if (!form) {
            console.error('Could not find form[name="ordre"]');
            return false;
        }

        const saveBtn = form.querySelector('input[type="submit"][id="submit"][name="save"]');
        if (saveBtn) {
            console.log('Found save button, clicking it programmatically...');
            // Set flag to prevent our click handler from canceling
            saveBtn.dataset.programmaticClick = 'true';
            saveBtn.click();
            return true;
        } else {
            console.warn('Save button not found, fallback to native submit');
            try {
                HTMLFormElement.prototype.submit.call(form);
                return true;
            } catch (err) {
                console.error('Submit failed:', err);
                return false;
            }
        }
    }

    function showMessage(msg, type) {
        document.querySelectorAll('.success-message,.error-message,.info-message').forEach(el => el.remove());

        const div = document.createElement('div');
        div.textContent = msg;
        div.className = type === 'success' ? 'success-message'
            : type === 'error' ? 'error-message'
                : 'info-message';
        div.style.padding = '8px';
        div.style.margin = '10px 0';
        div.style.borderRadius = '5px';
        div.style.color = '#fff';
        div.style.fontWeight = 'bold';
        div.style.backgroundColor =
            type === 'success' ? '#0066cc' :
                type === 'error' ? 'crimson' :
                    '#0066cc';

        const form = document.querySelector('form[name="ordre"]');
        if (form) {
            form.insertBefore(div, form.firstChild);
            if (type !== 'error') {
                setTimeout(() => div.remove(), 4000);
            }
        }
    }
    /**
     * Check if a row is a main collection item
     * @param {HTMLElement} row 
     * @returns {boolean}
     */
    function isMainItem(row) {
        const val = getSamlevareValue(row);
        return val === 'on' || val === '1' || val === 'true';
    }

    function hideRedundantHeaders() {
        console.log('Checking for redundant collection headers...');
        const rows = document.querySelectorAll('tr.ordrelinje');
        rows.forEach(row => {
            if (isMainItem(row)) {
                const saet = getSaetValue(row);
                const descInput = row.querySelector('textarea[name^="beskrivelse"]');
                const desc = descInput ? descInput.value.trim() : '';

                if (!desc) return;

                // Check other rows in same set
                const siblings = getCollectionRows(row);
                siblings.forEach(sib => {
                    if (sib !== row) {
                        const sibDescInput = sib.querySelector('textarea[name^="beskrivelse"]');
                        const sibDesc = sibDescInput ? sibDescInput.value.trim() : '';

                        // If sibling matches Main Item description, hide the SIBLING (Sub-item)
                        // This preserves the Main Item (which has the price)
                        if (sibDesc === desc) {
                            console.log('Hiding redundant sub-item row:', sib);
                            sib.style.display = 'none';
                        }
                    }
                });
            }
        });
    }

    // Run on load
    hideRedundantHeaders();
});