<script>
document.addEventListener('DOMContentLoaded', () => {
    let unsavedChanges = false;
    let pendingDragEvt = null;
    let retryDrag = false;

    document.querySelectorAll('#kassekladde-tbody input, #kassekladde-tbody select, #kassekladde-tbody textarea').forEach(el => {
        el.addEventListener('input', () => { unsavedChanges = true; });
    });

    const form = document.getElementById('kassekladde');
    const tbody = document.getElementById('kassekladde-tbody');

    if (form) {
        form.addEventListener('submit', () => { unsavedChanges = false; });
    }

    if (tbody) {
        new Sortable(tbody, {
            handle: '.drag-handle',
            animation: 150,
            onStart: evt => {
                if (unsavedChanges) {
                    evt.preventDefault();
                    pendingDragEvt = evt;
                    const formData = new FormData(form);
                    fetch(form.action, {
                        method: 'POST',
                        body: formData
                    }).then(response => {
                        if (response.ok) {
                            unsavedChanges = false;
                            retryDrag = true;
                            setTimeout(() => {
                                if (pendingDragEvt) {
                                    pendingDragEvt.item.dispatchEvent(new MouseEvent('mousedown', {bubbles:true}));
                                    pendingDragEvt = null;
                                    retryDrag = false;
                                }
                            }, 300); 
                        }
                    });
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
                        alert('Could not save new order!');
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
    `;
    document.head.appendChild(style);
});
</script>
<?php