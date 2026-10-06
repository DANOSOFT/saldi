// 20260907 CDX/LH Saved-record bridge. Reads identifiers and dirty state, never form values.
(function () {
  'use strict';
  var endpoint = new URL('../includes/saldi_assist_record.php', document.currentScript.src).href;
  var dirtyDocuments = new WeakSet();
  var observedDocuments = new WeakSet();

  function active() {
    try {
      var frame = document.querySelector('iframe[name="iframe_a"]');
      var win = frame ? frame.contentWindow : window;
      var doc = win.document;
      var url = new URL(win.location.href);
      var kind = /\/finans\/kassekladde\.php$/.test(url.pathname) ? 'journal' :
        (/\/debitor\/ordre\.php$/.test(url.pathname) ? 'invoice' : null);
      if (!kind) return null;
      var input = doc.querySelector(kind === 'journal' ? 'input[name="kladde_id"]' : 'input[name="id"]');
      var rawId = input ? input.value : url.searchParams.get(kind === 'journal' ? 'kladde_id' : 'id');
      if (!/^[1-9][0-9]{0,9}$/.test(rawId || '')) return null;
      var id = Number(rawId);
      if (id > 2147483647) return null;
      if (!observedDocuments.has(doc)) {
        observedDocuments.add(doc);
        var mark = function () {
          if (!dirtyDocuments.has(doc)) {
            dirtyDocuments.add(doc);
            window.dispatchEvent(new Event('saldi:record-changed'));
          }
          doc.querySelectorAll('[data-saldi-assist-highlight]').forEach(function (row) {
            row.removeAttribute('data-saldi-assist-highlight');
          });
        };
        doc.addEventListener('input', mark, true);
        doc.addEventListener('change', mark, true);
      }
      return { kind: kind, recordId: id, doc: doc, unsavedChanges: dirtyDocuments.has(doc) || win.docChange === true };
    } catch (_) { return null; }
  }

  async function getRecordContext(sessionHash) {
    var before = active();
    if (!before || !/^[0-9a-f]{32}$/.test(sessionHash || '')) return null;
    var controller = new AbortController();
    var timeout = setTimeout(function () { controller.abort(); }, 4000);
    try {
      var response = await fetch(endpoint, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ operation: 'grant', kind: before.kind, record_id: before.recordId, embed_session: sessionHash })
      });
      if (!response.ok) return null;
      var record = await response.json();
      var after = active();
      if (!after || after.doc !== before.doc || after.kind !== before.kind || after.recordId !== before.recordId) return null;
      return Object.assign(record, { unsavedChanges: after.unsavedChanges || record.hasSavedDraft === true });
    } catch (_) { return null; }
    finally { clearTimeout(timeout); }
  }

  async function highlightRows(payload, sessionHash) {
    if (!payload || payload.kind !== 'journal' || !Array.isArray(payload.rowIds) || payload.rowIds.length > 100 ||
        payload.rowIds.some(function (id) { return !Number.isInteger(id) || id <= 0; })) return false;
    var record = await getRecordContext(sessionHash);
    var selected = active();
    if (!record || !selected || record.unsavedChanges || selected.unsavedChanges || record.kind !== payload.kind ||
        record.recordId !== payload.recordId || record.revision !== payload.revision) return false;
    var doc = selected.doc;
    var style = doc.getElementById('saldi-assist-highlight-style');
    if (!style) {
      style = doc.createElement('style');
      style.id = 'saldi-assist-highlight-style';
      style.textContent = '[data-saldi-assist-highlight] { outline: 3px solid #b45309; outline-offset: -3px; background-color: #fef3c7 !important; }';
      doc.head.appendChild(style);
    }
    doc.querySelectorAll('[data-saldi-assist-highlight]').forEach(function (row) { row.removeAttribute('data-saldi-assist-highlight'); });
    var first = null;
    doc.querySelectorAll('.delete-line-btn[data-id]').forEach(function (button) {
      if (payload.rowIds.indexOf(Number(button.getAttribute('data-id'))) === -1) return;
      var row = button.closest('tr');
      if (row) { row.setAttribute('data-saldi-assist-highlight', ''); first = first || row; }
    });
    if (first) first.scrollIntoView({ block: 'center', behavior: 'smooth' });
    return Boolean(first);
  }

  document.addEventListener('load', function (event) {
    if (event.target && event.target.name === 'iframe_a') {
      active();
      window.dispatchEvent(new Event('saldi:record-changed'));
    }
  }, true);
  active();
  window.SaldiAssistRecords = { getRecordContext: getRecordContext, highlightRows: highlightRows };
})();
