<script>
document.addEventListener('DOMContentLoaded', () => {
    let unsavedChanges = false;
    let pendingDragEvt = null;

    // Track changes in all inputs/selects/textareas in the tbody
    document.querySelectorAll('#kassekladde-tbody input, #kassekladde-tbody select, #kassekladde-tbody textarea').forEach(el => {
        el.addEventListener('input', () => { unsavedChanges = true; });
    });

    // Mark as saved when the form is submitted
    const form = document.getElementById('kassekladde');
    if (form) {
        form.addEventListener('submit', () => { unsavedChanges = false; });
    }

    // Modal logic
    const modal = document.getElementById('unsavedModal');
    const modalSaveBtn = document.getElementById('modalSaveBtn');
    const modalCancelBtn = document.getElementById('modalCancelBtn');

    const tbody = document.getElementById('kassekladde-tbody');
    if (tbody) {
        new Sortable(tbody, {
            handle: '.drag-handle',
            animation: 150,
            onStart: evt => {
                if (unsavedChanges) {
                    evt.preventDefault();
                    pendingDragEvt = evt;
                    if (modal) modal.style.display = 'flex';
                } else {
                    evt.item.classList.add('dragging');
                }
            },
            onEnd: evt => {
                evt.item.classList.remove('dragging');
                [...tbody.rows].forEach(row => row.classList.remove('drop-target'));

                [...tbody.rows].forEach((row, idx) => {
                    const posCell = row.querySelector('.drag-handle');
                    if (posCell) {
                        posCell.innerHTML = '&#x2630; ' + (idx + 1);
                    }
                });

                const ids = [...tbody.querySelectorAll('tr')]
                    .map(row => row.querySelector('input[name^="id"]'))
                    .filter(input => input)
                    .map(input => input.value);

                fetch('kassekladde.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'reorder',
                        kladde_id: <?php echo json_encode($kladde_id); ?>,
                        ids
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert('Kunne ikke gemme ny rækkefølge!');
                    }
                });
            },
            onMove: evt => {
                [...tbody.rows].forEach(row => row.classList.remove('drop-target'));
                if (evt.related) {
                    evt.related.classList.add('drop-target');
                }
                return true;
            }
        });
    }

    // Modal button handlers
    if (modalSaveBtn && modalCancelBtn) {
        modalSaveBtn.onclick = function() {
            modal.style.display = 'none';
            if (form) {
                unsavedChanges = false;
                form.submit();
            }
        };
        modalCancelBtn.onclick = function() {
            modal.style.display = 'none';
            pendingDragEvt = null;
        };
    }

    // Style for drag/drop
    const style = document.createElement('style');
    style.textContent = `
        tr.dragging {
            background: #ffe082 !important;
            opacity: 0.7;
        }
        tr.drop-target {
            outline: 2px dashed #1976d2;
            background: #e3f2fd !important;
        }
        #unsavedModal { display: none; align-items: center; justify-content: center; }
        #unsavedModal[style*="display: flex"] { display: flex !important; }
    `;
    document.head.appendChild(style);
});
</script>
<?php