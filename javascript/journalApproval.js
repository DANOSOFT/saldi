// 20261122 CDX/ASR Order overlapping approval saves and keep confirmations aligned with stored selections.

/** Install the approval controls for one editable journal. @param {Object} T Translated labels and journal ID. */
function saldiJournalApproval(T) {
    var pending = 0, stale = false, queues = {}, revisions = {}, confirmed = {};
    /** @returns {HTMLInputElement[]} Stored line checkboxes. */
    function boxes() { return Array.prototype.slice.call(document.querySelectorAll('input.kk-godkendt')); }
    boxes().forEach(function (box) { confirmed[box.dataset.lineId] = box.checked; });
    /** Disable partial posting until all approvals have settled on the reviewed content. */
    function postBtn() {
        var button = document.getElementById('kkPostApproved');
        if (button) button.disabled = pending > 0 || stale;
    }
    /** Refresh the selection counter and header checkbox. */
    function count() {
        var all = boxes(), n = all.filter(function (box) { return box.checked; }).length;
        var el = document.getElementById('kk-godkendt-status');
        if (!el) {
            var balance = document.getElementById('kk-balance-status');
            if (!balance) return;
            el = document.createElement('span');
            el.id = 'kk-godkendt-status';
            el.style.cssText = 'display:inline-block;white-space:nowrap;background:#fff;border:1px solid #ddd;border-radius:4px;padding:2px 10px;font-size:12px;margin-left:4px;';
            balance.parentNode.insertBefore(el, balance.nextSibling);
        }
        el.textContent = T.label + ': ' + n + ' ' + T.of + ' ' + all.length;
        var master = document.getElementById('kkGodkendtAlle');
        if (master) {
            master.checked = all.length > 0 && n === all.length;
            master.indeterminate = n > 0 && n < all.length;
        }
    }
    /**
     * Queue a single-line or header action after all earlier actions on those lines.
     * Disjoint selections can save concurrently; old failures never undo a newer selection.
     * @param {HTMLInputElement[]} selected Selected stored lines.
     * @param {boolean} on Requested approval state.
     */
    function save(selected, on) {
        var versions = {}, snapshots = {}, ids = [];
        var predecessors = selected.map(function (box) {
            var id = box.dataset.lineId;
            ids.push(id);
            snapshots[id] = box.dataset.snapshot;
            versions[id] = revisions[id] = (revisions[id] || 0) + 1;
            return queues[id] || Promise.resolve();
        });
        pending++;
        postBtn();
        var task = Promise.all(predecessors).then(function () {
            var data = new FormData();
            data.append('kladde_id', T.kladde);
            data.append('ids', ids.join(','));
            data.append('godkendt', on ? '1' : '0');
            data.append('snapshots', JSON.stringify(snapshots));
            return fetch('kassekladde_includes/approveLines.php', { method: 'POST', body: data, credentials: 'same-origin' });
        }).then(function (response) { return response.json(); }).then(function (data) {
            if (!data || !data.ok) throw new Error(data && data.error ? data.error : 'db');
            ids.forEach(function (id) { confirmed[id] = on; });
        }).catch(function (error) {
            selected.forEach(function (box) {
                if (revisions[box.dataset.lineId] === versions[box.dataset.lineId]) box.checked = confirmed[box.dataset.lineId];
            });
            if (error.message === 'changed') {
                stale = true;
                boxes().forEach(function (box) { box.disabled = true; });
                var master = document.getElementById('kkGodkendtAlle');
                if (master) master.disabled = true;
            }
            count();
            alert(error.message === 'changed' ? T.changed : T.failed);
        }).then(function () { pending--; postBtn(); });
        ids.forEach(function (id) { queues[id] = task; });
    }
    document.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || stale) return;
        if (target.id === 'kkGodkendtAlle') {
            var selected = boxes(), on = target.checked;
            selected.forEach(function (box) { box.checked = on; });
            count();
            if (selected.length) save(selected, on);
        } else if (target.classList.contains('kk-godkendt')) {
            count();
            save([target], target.checked);
        }
    });
    /** @returns {boolean} Whether all saves are complete and the complete voucher groups may move. */
    window.kkConfirmPostApproved = function () {
        if (pending > 0 || stale) return false;
        var groups = {}, n = 0;
        boxes().forEach(function (box) {
            var number = parseInt(box.dataset.bilag, 10);
            var key = number >= 0 ? String(number) : 'l' + box.dataset.lineId;
            if (!groups[key]) groups[key] = { total: 0, approved: 0 };
            groups[key].total++;
            if (box.checked) { groups[key].approved++; n++; }
        });
        if (!n) { alert(T.none); return false; }
        var mixed = [], vouchers = 0, lines = 0;
        Object.keys(groups).forEach(function (key) {
            var group = groups[key];
            if (group.approved === group.total) { vouchers++; lines += group.total; }
            else if (group.approved > 0) mixed.push(key);
        });
        if (!vouchers) { alert(T.mixed + ' ' + mixed.join(', ')); return false; }
        var message = lines + ' ' + T.linesIn + ' ' + vouchers + ' ' + T.confirm;
        if (mixed.length) message += '\n\n' + T.mixed + ' ' + mixed.join(', ');
        if (!confirm(message)) return false;
        window.docChange = false;
        return true;
    };
    count();
    postBtn();
}
