
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('kassekladde-tbody');
    if (tbody) {
        new Sortable(tbody, {
            handle: '.drag-handle',
            animation: 150,
            onStart: evt => {
                evt.item.classList.add('dragging');
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
});

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
</script>
<?php
