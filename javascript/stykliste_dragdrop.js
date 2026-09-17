// Restore scroll position after stykliste DnD reload
const _styklisteScroll = sessionStorage.getItem('stykliste_scroll');
if (_styklisteScroll !== null) {
    sessionStorage.removeItem('stykliste_scroll');
    window.scrollTo(0, parseInt(_styklisteScroll, 10));
}

document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('stykliste-tbody');
    if (!tbody) return;

    const rows = tbody.querySelectorAll('tr.stykliste-row');
    if (rows.length === 0) return;

    if (typeof Sortable === 'undefined') {
        console.error('[stykliste_dragdrop] Sortable is not loaded');
        return;
    }

    new Sortable(tbody, {
        handle: '.stykliste-drag-handle',
        draggable: '.stykliste-row',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        animation: 150,
        revertOnSpill: true,

        onStart(evt) {
            evt.item.classList.add('dragging');
            document.body.classList.add('is-dragging');
        },

        onEnd(evt) {
            evt.item.classList.remove('dragging');
            document.body.classList.remove('is-dragging');

            const currentRows = tbody.querySelectorAll('tr.stykliste-row');

            // Build positions payload from data attributes on each row
            const vare_id = currentRows[0].dataset.vareId;
            const positions = [];
            currentRows.forEach((row, index) => {
                const styklisteId = row.dataset.styklisteId;
                if (styklisteId) {
                    positions.push({ stykliste_id: styklisteId, posnr: index + 1 });
                }
            });

            if (!positions.length) {
                console.error('[stykliste_dragdrop] no positions to save');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_stykliste_positions');
            formData.append('vare_id', vare_id);
            formData.append('positions', JSON.stringify(positions));

            fetch('productCardIncludes/save_stykliste_positions.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            })
            .then(r => r.text())
            .then(text => JSON.parse(text.split('--- DATA BEYOND THIS LINE ---\n')[1]))
            .then(data => {
                if (data.success) {
                    sessionStorage.setItem('stykliste_scroll', window.scrollY);
                    const params = new URLSearchParams(location.search);
                    params.set('id', vare_id);
                    location.href = 'varekort.php?' + params.toString();
                } else {
                    showMessage('Fejl: ' + (data.error || 'Ukendt fejl'), 'error');
                    console.error('[stykliste_dragdrop] save failed:', data);
                }
            })
            .catch(err => {
                showMessage('Netværksfejl - rækkefølge ikke gemt.', 'error');
                console.error('[stykliste_dragdrop] fetch error:', err);
            });
        }
    });

    function showMessage(msg, type) {
        document.querySelectorAll('.stykliste-msg').forEach(el => el.remove());
        const div = document.createElement('div');
        div.className = 'stykliste-msg';
        div.textContent = msg;
        div.style.cssText = 'padding:6px 10px;margin:6px 0;border-radius:4px;font-weight:bold;color:#fff;background:' +
            (type === 'success' ? '#0066cc' : 'crimson');
        tbody.closest('table').insertAdjacentElement('beforebegin', div);
        if (type !== 'error') setTimeout(() => div.remove(), 3000);
    }
});
