// --- javascript/kassekladde.js --- ver 5.0.0 --- 2026-10-21 ---
// Copyright (c) 2026 Danosoft ApS
// 20261021 ASR Cash journal redesign (Requirements_kassekladde_redesign_EN.md v2.2): density by the journal's width,
//                  the gear panel, the footer in the order of the work with "Flere" (shortcuts kept), the save status,
//                  Ctrl+S, leaving saves first (window.saldiNavigate + kkAfterSave=exit:), the invoice-number warning
//                  placed so the scrolling grid cannot clip it. Works on the existing markup; no field is renamed.
(function () {
  'use strict';
  var root = document.documentElement;
  var da = (document.documentElement.lang || 'da').indexOf('en') !== 0;
  function t(d, e) { return da ? d : e; }
  function $(s, c) { return (c || document).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); }
  var form = document.getElementById('kassekladde');
  var save = $('input[name="save"]');

  /* ---------- density (spec §6.1): wide -> compact -> narrow (horizontal scroll, paper-clip and Bilag frozen) ---------- */
  var box = $('.kassekladde-scroll-container');
  var table = box && $('table.formnavi', box);
  function fitDensity() {
    if (!box || !table) return;
    var order = ['wide', 'compact', 'narrow'];
    for (var i = 0; i < order.length; i++) {
      root.setAttribute('data-kk-density', order[i]);
      if (order[i] === 'narrow' || table.scrollWidth <= box.clientWidth + 1) break;
    }
    fitFooter();
  }

  /* ---------- footer: note, "Flere", overflow and shortcuts (spec §6.3) ---------- */
  var footRow = $('.kk-footer #buttonTable > tbody > tr');
  var moreTd = null, moreMenu = null;
  var OVERFLOW = ['kk-f-offset', 'kk-f-import', 'kk-f-match']; // first to move into "Flere"
  function buttonOf(td) { return td && $('input[type="submit"],input[type="button"],button', td); }
  function buildFooter() {
    if (!footRow || !$('.kk-f-post, .kk-f-save', footRow)) return;
    var note = document.createElement('td');
    note.className = 'kk-f-note';
    note.textContent = t('Træk i linjen for at flytte den · træk klipsen over på en anden linjes klips for at dele bilaget',
      'Drag a line to move it · drag the paper-clip onto another line to share the document');
    footRow.appendChild(note);
    moreTd = document.createElement('td');
    moreTd.className = 'kk-f-more';
    moreTd.innerHTML = '<button type="button" class="kk-more-btn" aria-haspopup="true" aria-expanded="false">' + t('Flere', 'More') + ' &#9662;</button><div class="kk-more-menu" role="menu"></div>';
    footRow.appendChild(moreTd);
    moreMenu = $('.kk-more-menu', moreTd);
    var btn = $('.kk-more-btn', moreTd);
    btn.addEventListener('click', function (e) { e.stopPropagation(); var o = !moreMenu.classList.contains('open'); moreMenu.classList.toggle('open', o); btn.setAttribute('aria-expanded', String(o)); });
    document.addEventListener('click', function (e) { if (!moreTd.contains(e.target)) { moreMenu.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') moreMenu.classList.remove('open'); });
  }
  function fillMenu() {
    if (!moreMenu) return;
    moreMenu.innerHTML = '';
    var tds = $$('td.kk-f-menu, td.kk-moved', footRow);
    tds.forEach(function (td) {
      var b = buttonOf(td); if (!b) return;
      var item = document.createElement('button');
      item.type = 'button';
      var key = b.getAttribute('accesskey');
      item.innerHTML = '<span></span>' + (key ? '<small>Alt+' + key.toUpperCase() + '</small>' : '');
      item.firstChild.textContent = b.value || b.textContent;
      // the original stays in the DOM, so a submit button still sends its name
      item.addEventListener('click', function () { moreMenu.classList.remove('open'); b.click(); });
      moreMenu.appendChild(item);
    });
    moreTd.hidden = !tds.length;
  }
  function fitFooter() {
    if (!footRow || !moreTd) { return; }
    $$('td.kk-moved', footRow).forEach(function (td) { td.classList.remove('kk-moved'); });
    var note = $('.kk-f-note', footRow);
    if (note) note.hidden = false;
    var over = function () { return footRow.scrollWidth > footRow.clientWidth + 1; };
    if (over() && note) note.hidden = true;
    for (var i = 0; i < OVERFLOW.length && over(); i++) {
      var td = $('td.' + OVERFLOW[i], footRow);
      if (td) td.classList.add('kk-moved');
    }
    fillMenu();
  }
  // A hidden element's accesskey does not fire: trigger the footer buttons that are in "Flere".
  document.addEventListener('keydown', function (e) {
    if (!e.altKey || e.ctrlKey || e.metaKey || !footRow) return;
    var k = (e.key || '').toLowerCase();
    var b = $$('input[accesskey],button[accesskey]', footRow).filter(function (x) { return x.getAttribute('accesskey').toLowerCase() === k; })[0];
    if (b && b.offsetParent === null) { e.preventDefault(); b.click(); }
  });

  /* ---------- save status, Ctrl+S (spec §6.5) ---------- */
  var state = document.getElementById('kkSaveState');
  var dirty = false;
  function setState() {
    if (!state) return;
    var d = dirty || !!window.docChange;
    state.classList.toggle('dirty', d);
    state.textContent = d ? state.getAttribute('data-dirty') : state.getAttribute('data-saved');
  }
  if (form) {
    form.addEventListener('input', function (e) { if (e.target.matches && e.target.matches('#kassekladde-tbody input, #kassekladde-tbody select, .kk-toolbar input')) { dirty = true; setState(); } });
    form.addEventListener('change', function () { setTimeout(setState, 0); });
  }
  setState();
  function submitSave() {
    if (!save || !save.form) return false;
    window.docChange = false;
    if (typeof save.form.requestSubmit === 'function') save.form.requestSubmit(save); else save.click();
    return true;
  }
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 's' || e.key === 'S')) { if (save) { e.preventDefault(); submitSave(); } }
  });

  /* ---------- leaving saves first: breadcrumb, Alt+L (via the shell) and Ny ---------- */
  function isDirty() { return dirty || !!window.docChange; }
  function samePath(href) {
    try { var u = new URL(href, window.location.href); if (u.origin !== window.location.origin) return null; return u.pathname + u.search; } catch (x) { return null; }
  }
  function lukHref(target) {
    // the hidden Luk link holds the luk.php address (releases the lock, SST-755); the target becomes its returside
    var c = $('a.kk-close'), raw = c ? c.getAttribute('href') || '' : '';
    var m = /^javascript:confirmClose\(\s*'([^']*)'/i.exec(raw);
    if (!m) return target;
    try { var u = new URL(m[1].replace(/&amp;/g, '&'), window.location.href); u.searchParams.set('returside', samePath(target) || target); return u.href; } catch (x) { return target; }
  }
  function leave(target) {
    var path = samePath(target);
    if (!path) { window.location.href = target; return; }
    if (isDirty() && save && save.form) {
      var after = save.form.querySelector('input[name="kkAfterSave"]');
      if (!after) { after = document.createElement('input'); after.type = 'hidden'; after.name = 'kkAfterSave'; save.form.appendChild(after); }
      after.value = 'exit:' + path;
      submitSave(); // a failed validation keeps the user here with the normal error
      return;
    }
    window.docChange = false;
    window.location.href = lukHref(target);
  }
  if (save) {
    window.saldiNavigate = leave; // pageChrome.js calls this for the shell's breadcrumb and "came from" chip
    var ny = document.getElementById('create-new');
    if (ny) ny.addEventListener('click', function (e) { e.preventDefault(); leave('../finans/kassekladde.php'); });
  }

  /* ---------- gear panel (spec §7) ---------- */
  var gear = document.getElementById('kkVisToggle');
  var panel = document.getElementById('kkVisPanel');
  var hint = document.getElementById('kkVisHint');
  function applyToggle(cb) {
    var col = cb.getAttribute('data-col'), show = cb.checked;
    if (col === 'ac_forslag' || col === 'ac_opslag') {
      window.saldiAutocompleteOptions = window.saldiAutocompleteOptions || {};
      if (col === 'ac_forslag') window.saldiAutocompleteOptions.showLastPostings = show;
      else window.saldiAutocompleteOptions.showAccountLookup = show;
      return;
    }
    $$('.kk-col-' + col).forEach(function (el) { el.style.display = show ? '' : 'none'; });
    fitDensity();
  }
  function saveCols() {
    var tokens = [];
    $$('#kkVisPanel .kk-col-toggle').forEach(function (cb) {
      var col = cb.getAttribute('data-col');
      if (cb.getAttribute('data-kind') === 'opt') { if (cb.checked && !cb.disabled) tokens.push(col + '_vis'); }
      else if (!cb.checked) tokens.push(col);
    });
    var fd = new FormData(); fd.append('save_kk_cols', tokens.join(','));
    // keepalive: a reload right after toggling must not cancel the save request
    fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true }).catch(function () {});
  }
  function placePanel() {
    if (!gear || !panel) return;
    var r = gear.getBoundingClientRect();
    panel.style.top = (r.bottom + 8) + 'px';
    panel.style.right = Math.max(8, window.innerWidth - r.right) + 'px';
  }
  if (panel) {
    document.addEventListener('change', function (e) {
      if (!e.target.classList || !e.target.classList.contains('kk-col-toggle')) return;
      applyToggle(e.target); saveCols();
    });
    var all = document.getElementById('kkVisShowAll');
    if (all) all.addEventListener('click', function () {
      // apply every switch first, then persist once (N saves could finish out of order)
      $$('#kkVisPanel .kk-col-toggle').forEach(function (cb) { if (!cb.checked && !cb.disabled) { cb.checked = true; applyToggle(cb); } });
      saveCols();
    });
  }
  function dismissHint() { if (hint) { hint.classList.remove('kkVisHintShow'); try { localStorage.setItem(hint.getAttribute('data-key'), '1'); } catch (x) {} } }
  if (gear && panel) {
    gear.addEventListener('click', function (e) {
      e.stopPropagation(); dismissHint(); placePanel();
      var o = !panel.classList.contains('kkVisOpen'); panel.classList.toggle('kkVisOpen', o); gear.setAttribute('aria-expanded', String(o));
    });
    document.addEventListener('click', function (e) { if (panel.classList.contains('kkVisOpen') && !panel.contains(e.target) && !gear.contains(e.target)) { panel.classList.remove('kkVisOpen'); gear.setAttribute('aria-expanded', 'false'); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('kkVisOpen')) { panel.classList.remove('kkVisOpen'); gear.focus(); } });
    window.addEventListener('resize', function () { if (panel.classList.contains('kkVisOpen')) placePanel(); });
    try {
      if (hint && !localStorage.getItem(hint.getAttribute('data-key'))) {
        var hr = gear.getBoundingClientRect();
        hint.style.top = (hr.bottom + 10) + 'px';
        hint.style.right = Math.max(8, window.innerWidth - hr.right) + 'px';
        hint.classList.add('kkVisHintShow');
      }
    } catch (x) {}
    var ok = document.getElementById('kkVisHintOk');
    if (ok) ok.addEventListener('click', dismissHint);
  }

  /* ---------- invoice-number warning (SD-715): fixed, next to its flag ---------- */
  function placePop(flag) {
    var pop = flag && $('.invoice-reuse-pop', flag); if (!pop) return;
    var r = flag.getBoundingClientRect(), w = 320;
    pop.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    pop.style.top = (r.bottom + 6) + 'px';
  }
  document.addEventListener('mouseover', function (e) { var f = e.target.closest && e.target.closest('.invoice-reuse-flag'); if (f) placePop(f); });
  document.addEventListener('focusin', function (e) { var f = e.target.closest && e.target.closest('.invoice-reuse-flag'); if (f) placePop(f); });
  if (box) box.addEventListener('scroll', function () {
    var f = $('.invoice-reuse-flag:focus, .invoice-reuse-flag:hover'); if (f) placePop(f);
  }, { passive: true });

  /* ---------- the Assist menu: tour (#tutorial-help) and the shortcut list ---------- */
  var c = window.saldiPageChrome;
  if (c && window.parent && window.parent !== window) {
    c.help = Object.assign({}, c.help || {}, { tour: '#tutorial-help', shortcuts: 'https://saldi.dk/dok/ledgerGuide.pdf' });
    window.parent.postMessage(c, window.location.origin);
  }

  buildFooter();
  fitDensity();
  if (window.ResizeObserver && box) new ResizeObserver(function () { fitDensity(); }).observe(box);
  else window.addEventListener('resize', fitDensity);
})();
