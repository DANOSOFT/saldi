document.addEventListener('DOMContentLoaded', function () {
    console.log('Invoice drag-and-drop script loading...');

    // Check if this is an invoice page (status >= 3)
    const statusInput = document.querySelector('input[name="status"]');
    const status = statusInput ? parseInt(statusInput.value) : 0;
    
    console.log('Page status:', status);
    
    if (status < 3) {
        console.log('This is an ORDER (status < 3) - invoice_dragdrop.js will NOT initialize');
        return;
    }

    console.log('This is an INVOICE (status >= 3) - invoice_dragdrop.js will initialize');

    let invoiceTableBody = null;

    document.querySelectorAll('table').forEach(table => {
        const candidate = table.querySelector('tbody');
        if (candidate && candidate.querySelector('tr.ordrelinje')) {
            invoiceTableBody = candidate;
            console.log('Found INVOICE table body');
        }
    });

    if (!invoiceTableBody) {
        console.error('No invoice table found');
        return;
    }

    const ordreIdInput = document.querySelector('input[name="id"]');
    const ordreId = ordreIdInput ? ordreIdInput.value : null;
    console.log('Current ordre_id detected:', ordreId);

    const sortable = new Sortable(invoiceTableBody, {
        handle: '.drag-handle',
        draggable: '.ordrelinje',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        animation: 150,

        onStart(evt) {
            evt.item.classList.add('dragging');
            console.log('=== INVOICE DRAG STARTED ===');
        },

        onEnd(evt) {
            evt.item.classList.remove('dragging');
            console.log('=== INVOICE DRAG ENDED ===');
            updateInvoicePositions();
            const success = submitInvoiceForm();
            if (success) {
                showMessage('Invoice updated and saved.', 'success');
            } else {
                showMessage('Drag updated, but form save failed.', 'error');
            }

            if (typeof docChange !== 'undefined') {
                docChange = true;
            }
        }
    });

    function updateInvoicePositions() {
        const rows = invoiceTableBody.querySelectorAll('tr.ordrelinje');
        console.log('=== UPDATING INVOICE POSITION NUMBERS for ordre_id:', ordreId, '===');

        let step = 100;
        if (rows.length > 1) {
            const firstVal = parseInt(rows[0].querySelector('input[name^="posn"]:not([name="posn0"])')?.value || '0', 10);
            const secondVal = parseInt(rows[1].querySelector('input[name^="posn"]:not([name="posn0"])')?.value || '0', 10);
            const detectedStep = secondVal - firstVal;
            if (detectedStep > 0) step = detectedStep;
        }

        console.log('ℹ Step size used:', step);

        rows.forEach((row, index) => {
            const posInput = row.querySelector('input[name^="posn"]:not([name="posn0"])');
            if (posInput) {
                const newPos = (index + 1) * step;
                posInput.value = newPos;
                console.log(`INVOICE Row ${index + 1} → posnr=${newPos}`);
            } else {
                console.warn(`INVOICE Row ${index + 1} missing pos input`);
            }
        });
    }

 function submitInvoiceForm() {
    const form = document.querySelector('form[name="ordre"]');
    if (!form) {
        console.error('Could not find form[name="ordre"]');
        return false;
    }


let invoiceFlag = form.querySelector('input[name="invoice_dragdrop"]');
if (invoiceFlag) {
    invoiceFlag.remove();
}
invoiceFlag = document.createElement('input');
invoiceFlag.type = 'hidden';
invoiceFlag.name = 'invoice_dragdrop';
invoiceFlag.value = '1';
form.appendChild(invoiceFlag);

    const saveBtn = form.querySelector('input[type="submit"][name="save"]') ||
                   form.querySelector('input[type="submit"][value*="Gem"]') ||
                   form.querySelector('input[type="submit"][id="submit"]') ||
                   form.querySelector('input[type="submit"]');
    
    if (saveBtn) {
        console.log('Clicking save button for invoice:', saveBtn);
        saveBtn.click();
        return true;
    } else {
        console.warn('No save button found, submitting form directly');
        try {
       
            form.submit();
            return true;
        } catch (err) {
            console.error('Form submission failed:', err);
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
                setTimeout(() => div.remove(), 3000);
            }
        }
    }
});