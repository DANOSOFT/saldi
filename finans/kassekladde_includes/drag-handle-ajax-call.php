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
        // 20261021 ASR Redesign: the whole saved line is the handle (the Position column is gone). Fields, buttons, links
        // and the paper-clip are filtered out, so text can be selected and the clip keeps its own drag (sharing a document);
        // window.kkRowDrag tells the clip's document-level handlers that a line, not a document, is being dragged.
        new Sortable(tbody, {
            draggable: 'tr.kk-line',
            filter: 'input, select, textarea, button, a, label, img, .clip-cell',
            preventOnFilter: false,
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
                    window.kkRowDrag = true;
                    evt.item.classList.add('dragging');
                }
            },
            onEnd: evt => {
                window.kkRowDrag = false;
                evt.item.classList.remove('dragging');
                [...tbody.rows].forEach(row => row.classList.remove('drop-target'));

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
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Could not save new order');
                    }

                    // Do not navigate away if the user made changes while
                    // the reorder request was in flight.
                    if (unsavedChanges) {
                        return;
                    }

                    // Keep kladde_id, kkdir, tjek, etc.
                    // Only change kksort to "pos"
                    const url = new URL(window.location.href);
                    url.searchParams.set('kksort', 'pos');
                    url.searchParams.set('kkdir', 'asc');
                    window.location.href = url.toString();
                })
                .catch(error => {
                    console.error(error);
                    alert('Could not save new order!');
                      // The DOM was already reordered optimistically before this fetch;
                    // since the save failed, reload to rebuild the table from the
                    // actual persisted pos values instead of leaving an unsaved,
                    // out-of-sync order on screen.
                    if (!unsavedChanges) {
                        window.location.reload();
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

    if (document.documentElement.classList.contains('kk-redesign')) return; // css/kassekladde.css styles the drag
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
