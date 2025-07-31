document.addEventListener('DOMContentLoaded', function () {

    let orderTableBody = null;
    document.querySelectorAll('table').forEach(table => {
        const candidate = table.querySelector('tbody');
        if (candidate && candidate.querySelector('tr.ordrelinje')) {
            orderTableBody = candidate;
        }
    });

    if (!orderTableBody) {
        console.error('No table with .ordrelinje rows found!');
        showMessage(' Table with order lines not found!', 'error');
        return;
    }

    console.log('Found order table body:', orderTableBody);

    const ordreIdInput = document.querySelector('input[name="id"]');
    const ordreId = ordreIdInput ? ordreIdInput.value : null;
    console.log('Current ordre_id detected:', ordreId);

    const sortable = new Sortable(orderTableBody, {
        handle: '.drag-handle',
        draggable: '.ordrelinje',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        animation: 150,

        onStart(evt) {
            evt.item.classList.add('dragging');
            console.log('=== DRAG STARTED ===');
        },

        onEnd(evt) {
            evt.item.classList.remove('dragging');
            console.log('=== DRAG ENDED ===');
            updatePositionNumbers();
            const success = submitOrderForm();
            if (success) {
                showMessage('Order updated and saved.', 'success');
            } else {
                showMessage('Drag updated, but form save failed.', 'error');
            }

            if (typeof docChange !== 'undefined') {
                docChange = true;
            }
        }
    });

    function updatePositionNumbers() {
        const orderRows = orderTableBody.querySelectorAll('tr.ordrelinje');
        console.log('=== UPDATING POSITION NUMBERS for ordre_id:', ordreId, '===');

        let step = 100;
        if (orderRows.length > 1) {
            const firstVal = parseInt(orderRows[0].querySelector('input[name^="posn"]:not([name="posn0"])')?.value || '0', 10);
            const secondVal = parseInt(orderRows[1].querySelector('input[name^="posn"]:not([name="posn0"])')?.value || '0', 10);
            const detectedStep = secondVal - firstVal;
            if (detectedStep > 0) step = detectedStep;
        }

        console.log('ℹ Step size used:', step);

        orderRows.forEach((row, index) => {
            const posInput = row.querySelector('input[name^="posn"]:not([name="posn0"])');
            if (posInput) {
                const newPos = (index + 1) * step;
                posInput.value = newPos;
                console.log(`Row ${index + 1} → posnr=${newPos}`);
            } else {
                console.warn(`Row ${index + 1} missing pos input`);
            }
        });
    }

    function submitOrderForm() {
        const form = document.querySelector('form[name="ordre"]');
        if (!form) {
            console.error('Could not find form[name="ordre"]');
            return false;
        }

        const saveBtn = form.querySelector('input[type="submit"][id="submit"][name="save"]');
        if (saveBtn) {
            saveBtn.click();
            return true;
        } else {
            console.warn(' Save button not found, fallback to native submit');
            try {
                HTMLFormElement.prototype.submit.call(form);
                return true;
            } catch (err) {
                console.error(' Submit failed:', err);
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
});