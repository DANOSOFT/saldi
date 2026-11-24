// ---- javascript/saldi-help-kreditor.js --- ver 5.1.0 --- 2026.11.09 ---
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The help panel's page knowledge for the Kreditor module (window.SaldiHelpContent.pages, doc/hjaelp_saldi_ai.md §3.2,
// §4 and §16.1): the Kreditor sidebar (purchase orders, supplier invoices, suppliers, payments, reports) and every page
// reached from it in the frame.
// Loads after javascript/saldi-help-finans.js and builds its pages with the helpers of javascript/saldi-help-content.js
// (SaldiHelpContent.lib). Data and pure functions only. Every text the panel shows is a key in
// importfiler/tekster_keyed.tsv (help.<group>.*), fetched per language from index/helpTexts.php. Detectors and show
// targets only read the frame document: no writes, focus, clicks, network or timers. Frame elements come from another
// realm, so type checks use nodeType/tagName.
//
// 20261108 CL/ASR Created: the module file for the Kreditor pages, loaded like saldi-help-finans.js.
// 20261108 CL/ASR The Kreditor pages (spec §22): purchase orders, OIOUBL import, suppliers, reports, payment lists, merge, Top 100.
// 20261108 CL/ASR Review: Vis mig on the purchase order and the card's Tilbage ring only where their task applies (spec §19.3).
// 20261108 CL/ASR Live check on ssl12 (spec §22.9): Kontonr., Opdel and Gem on the purchase order by state; Kontokort, Fakturaliste, Slet and merge only on a saved Kreditorkort.
// 20261109 CL/ASR Page questions in Saldi Assist (spec §25): each state's top three; correcting a posted invoice on the Faktura tab, paying a posted
//                 invoice, the supplier number on a new card.
(function () {
  'use strict';

  const C = window.SaldiHelpContent;
  if (!C || !C.lib || !C.pages) return;
  const task = C.lib.task, showKey = C.lib.showKey, fieldKeys = C.lib.fieldKeys, fieldLabel = C.lib.fieldLabel,
    stepKeys = C.lib.stepKeys, unique = C.lib.unique, attr = C.lib.attr, byName = C.lib.byName, rowOf = C.lib.rowOf;
  const PAGES = C.pages;

  // ==== Purchasing: the purchase order list, the purchase order and the OIOUBL import (author K1; helpers k1)
  // ------------------------------------------------------------------ K1: the purchase flow (Kreditor)
  // Shared read-only helpers of the K1 pages. Frame elements come from another realm: nodeType/tagName, never instanceof.

  function k1q(root, sel) {
    try {
      const el = root ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function k1qa(root, sel) {
    try {
      return root ? Array.prototype.slice.call(root.querySelectorAll(sel)) : [];
    } catch (e) {
      return [];
    }
  }

  /** The trimmed value of a field, '' for anything else. */
  function k1val(el) {
    try {
      return el && el.nodeType === 1 && el.value !== undefined && el.value !== null ? String(el.value).trim() : '';
    } catch (e) {
      return '';
    }
  }

  function k1txt(el) {
    try {
      return el && el.nodeType === 1 ? String(el.textContent || '').trim() : '';
    } catch (e) {
      return '';
    }
  }

  /** A read-only field (the property in the browser, the attribute on any markup). */
  function k1ro(el) {
    try {
      return !!el && el.nodeType === 1 && (el.readOnly === true || (el.getAttribute && el.getAttribute('readonly') !== null));
    } catch (e) {
      return false;
    }
  }

  /** A Danish date as the pages print and accept it (dd-mm-yyyy, also dd-mm-yy, '.' or '/'), as yyyymmdd; 0 when unreadable. */
  function k1date(s) {
    const m = /^(\d{1,2})[-./](\d{1,2})[-./](\d{2}|\d{4})$/.exec(String(s || '').trim());
    if (!m) return 0;
    let y = parseInt(m[3], 10);
    if (y < 100) y += 2000;
    const mo = parseInt(m[2], 10);
    const d = parseInt(m[1], 10);
    if (mo < 1 || mo > 12 || d < 1 || d > 31) return 0;
    return y * 10000 + mo * 100 + d;
  }

  /** A Danish number (1.234,5 or 4) as a number; NaN when unreadable. */
  function k1num(s) {
    const t = String(s || '').replace(/[()\s]/g, '').replace(/\./g, '').replace(',', '.');
    return t === '' ? NaN : parseFloat(t);
  }

  function k1closest(el, sel) {
    try {
      const c = el && el.closest ? el.closest(sel) : null;
      return c && c.nodeType === 1 ? c : null;
    } catch (e) {
      return null;
    }
  }

  /** Search vocabulary, never shown. */
  function k1words(da, en, no) {
    return { da: da, en: en, no: no };
  }

  // ---- Leverandørordrer (kreditor/ordreliste.php)
  // Evidence, kreditor/ordreliste.php unless named: the sidebar Kreditorer » Ordre (index/main.php:325). The tab is the
  // grid id kredorliste_<valg> (:105-127 the tab from the grid's GET or the last used one, :694, :726;
  // includes/kreditorOrderFuncIncludes/creditor_orderlist_grid.php:1240-1245): forslag is status 0, ordrer status 1-2 (also
  // 0 with quick invoicing, DIV 3 box4, :168-171), faktura status 3 and up (:249-257). The tabs, Tilbage (only with
  // konto_id in the URL, from a supplier card) and Ny #ny are in includes/kreditorOrderFuncIncludes/topLine.php:5, 37-56, 112-114;
  // the Forslag tab only without quick invoicing (:59-71). A row links ordre.php?tjek= (:286-290); the red fa-user marks
  // an order a colleague opened within the hour (:283-286; ordre.php:167-184 refuses it with Ordren er i brug af); the
  // print and mail icons (:635-680). The grid: search fields search[kredorliste_<valg>][field] in the thead (grid
  // :1312-1361), the menu Søg, Ryd søgning, Eksportér til CSV/PDF, Redigér » Kolonner/Filtre (:1365-1420), rowcount and
  // offset in the tfoot (:1506, :1513), one filler row (:1268); Redigér » Kolonner replaces the list with a form without
  // thead, saved with Gem and left with Luk (:1580-1625).

  const K1_TABS = ['forslag', 'ordrer', 'faktura'];

  function k1olTable(doc) {
    return k1q(doc, 'table[id^="datatable-kredorliste_"]');
  }

  /** The tab: the grid id, else the URL, else Ordrer (the page's own fallback, :125-127). */
  function k1olTab(ctx, table) {
    const m = /^datatable-kredorliste_([a-z]+)$/.exec(attr(table, 'id'));
    if (m && K1_TABS.indexOf(m[1]) >= 0) return m[1];
    let v = '';
    try {
      v = String(ctx.url.searchParams.get('valg') || '');
    } catch (e) {
      v = '';
    }
    return K1_TABS.indexOf(v) >= 0 ? v : 'ordrer';
  }

  /** The order rows, or null outside the list view (Redigér » Kolonner has no thead). */
  function k1olRows(table) {
    if (!table || !k1q(table, 'thead')) return null;
    return k1qa(table, 'tbody tr:not(.filler-row)');
  }

  /** The first order row, only on the tabs the task is about, so a search hit on another tab rings nothing. */
  function k1olFirstRow(tabs) {
    return function (ctx) {
      const table = k1olTable(ctx.doc);
      const tab = k1olTab(ctx, table);
      let ok = tabs.indexOf(tab) >= 0;
      // Godkend: with quick invoicing the proposals are on the Ordrer tab
      if (!ok && tabs.indexOf('forslag') >= 0 && tab === 'ordrer' && !k1q(ctx.doc, 'a[href*="valg=forslag"]')) ok = true;
      const rows = ok ? k1olRows(table) : null;
      return rows && rows.length ? rows[0] : null;
    };
  }

  /** The search field under Firmanavn, else the first search field of the list. */
  function k1olSearch(ctx) {
    const doc = ctx.doc;
    return k1q(doc, 'input[name^="search[kredorliste_"][name$="[firmanavn]"]') || k1q(doc, 'input[name^="search[kredorliste_"]');
  }

  const K1_KOL = 'kol';
  const K1_OL_MENU = 'div[id^="datatable-wrapper-kredorliste_"] .dropdown-trigger';

  const K1_OL_TASKS = {
    // [1] #ny (topLine.php:112-114); [2] Kontonr. with Gem, or a name and the list (ordre.php:653-699, 716-719;
    // javascript/kreditorOrdreAutocomplete.js:71, 386-389); [3] the supplier's price, else the item's cost price
    // (ordre.php:989-1011); [4] Godkend makes status 1 (ordre.php:648-651, orderIncludes/openOrderData.php:247)
    KOL1: task(K1_KOL, 'KOL1', 4, { target: '#ny', label: showKey(K1_KOL, 'KOL1'), kind: 'button' }),
    // The whole flow; Modtag needs status 1 (orderIncludes/openOrderLines.php:348-361), Bogfør every line received (:62,
    // :348) and Fakturanr. (ordre.php:1188); kreditor/bogfor.php posts on levdate (:122-147, :299) and the order goes to
    // Faktura (status 3+)
    KOL2: task(K1_KOL, 'KOL2', 4, { target: k1olFirstRow(['ordrer']), label: showKey(K1_KOL, 'KOL2'), kind: 'row' }),
    // Importer OIOUBL faktura on a new order (orderIncludes/openOrderData.php:88-93) → kreditor/ublimport.php
    KOL3: task(K1_KOL, 'KOL3', 3, { target: '#ny', label: showKey(K1_KOL, 'KOL1'), kind: 'button' }),
    KOL4: task(K1_KOL, 'KOL4', 2, { target: k1olSearch, label: showKey(K1_KOL, 'KOL4'), kind: 'field' }),
    KOL5: task(K1_KOL, 'KOL5', 2, { target: K1_OL_MENU, label: showKey(K1_KOL, 'KOL5'), kind: 'button' }),
    KOL6: task(K1_KOL, 'KOL6', 3, { target: k1olFirstRow(['forslag']), label: showKey(K1_KOL, 'KOL2'), kind: 'row' }),
    // Modtag (orderIncludes/openOrderLines.php:357-361) refused without Forventet lev. dato, before Ordredato or outside
    // the year (modtag.php:108-127); stock items go on stock (:231-257)
    KOL7: task(K1_KOL, 'KOL7', 3, { target: k1olFirstRow(['ordrer']), label: showKey(K1_KOL, 'KOL2'), kind: 'row' }),
    KOL8: task(K1_KOL, 'KOL8', 2, { target: 'a[href*="valg=faktura"]', label: showKey(K1_KOL, 'KOL8'), kind: 'button' }),
    // Kreditér (orderIncludes/closedOrder.php:248-250) makes a KK order, status 0 (ordre.php:634-646); then as an order
    KOL9: task(K1_KOL, 'KOL9', 4, { target: k1olFirstRow(['faktura']), label: showKey(K1_KOL, 'KOL9'), kind: 'row' }),
    KOL10: task(K1_KOL, 'KOL10', 2, { target: 'table[id^="datatable-kredorliste_"] span.fa-user', label: showKey(K1_KOL, 'KOL10'), kind: 'status' }),
    KOL11: task(K1_KOL, 'KOL11', 2, { target: 'a[href^="formularprint.php"]', label: showKey(K1_KOL, 'KOL11'), kind: 'button' }),
    KOL12: task(K1_KOL, 'KOL12', 2, { target: K1_OL_MENU, label: showKey(K1_KOL, 'KOL5'), kind: 'button' }),
    // Luk in Redigér » Kolonner (grid :1617)
    KOL13: task(K1_KOL, 'KOL13', 2, { target: 'button[onclick*="updateQueryParameter"]', label: showKey(K1_KOL, 'KOL13'), kind: 'button' }),
    // A line without Varenr. is text only, price 0 (ordre.php:725, 1067-1070): bills without items go through the
    // kassekladde (as kk KK33). No Vis mig: the sidebar is in the shell.
    KOL14: task(K1_KOL, 'KOL14', 4)
  };

  const K1_OL_FIELDS = {};
  ['search:ordrenr', 'search:ordredate', 'search:tilbudsdato', 'search:levdate', 'search:modtagelse', 'search:fakturanr',
    'search:fakturadate', 'search:kontonr', 'search:firmanavn', 'search:lev_navn', 'search:ref', 'search:ordresum',
    'search:forslagssum', 'search:fakturasum', 'rowcount']
    .forEach(function (k) { K1_OL_FIELDS[k] = fieldKeys(K1_KOL, k); });

  const k1Ordreliste = {
    group: K1_KOL,
    states: ['forslag', 'ordrer', 'faktura', 'opsaetning'],
    flags: ['tom', 'soegning', 'laast', 'leverandoer', 'udenForslag', 'mange'],

    name: function () {
      return 'help.kol.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const table = k1olTable(doc);
      const tab = k1olTab(ctx, table);
      const rows = k1olRows(table);
      const n = rows ? rows.length : 0;
      let offset = 0;
      try {
        offset = parseInt(k1val(k1q(doc, 'input[name^="offset[kredorliste_"]')), 10) || 0;
      } catch (e) {
        offset = 0;
      }
      const search = k1qa(doc, 'input[name^="search[kredorliste_"], select[name^="search[kredorliste_"]');
      const flags = {
        tom: !!rows && n === 0,
        soegning: search.some(function (i) { return k1val(i) !== ''; }),
        laast: !!k1q(doc, 'table[id^="datatable-kredorliste_"] span.fa-user'),
        // Tilbage to the card is printed only with konto_id in the URL (topLine.php:5, 37-56)
        leverandoer: !!k1q(doc, 'a[href^="kreditorkort.php?id="]'),
        udenForslag: tab !== 'forslag' && !k1q(doc, 'a[href*="valg=forslag"]'),
        mange: n > 15 || offset > 0
      };
      return { state: table && !rows ? 'opsaetning' : tab, flags: flags, data: { rows: n } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'opsaetning') return ['help.kol.about.opsaetning'];
      if (K1_TABS.indexOf(state) < 0) return [];
      // From a supplier card the kontonr search is filled in again on every submit (:693-713, :906-931)
      const second = f.leverandoer ? 'help.kol.about.leverandoer' : '';
      if (f.tom) {
        if (f.soegning && !f.leverandoer) return ['help.kol.about.soegning_tom'];
        if (state === 'faktura') return ['help.kol.about.faktura_tom', second || 'help.kol.about.uden_varer'];
        return ['help.kol.about.hvad', second || 'help.kol.about.uden_varer'];
      }
      const first = { forslag: 'help.kol.about.forslag', ordrer: 'help.kol.about.ordrer', faktura: 'help.kol.about.faktura' }[state];
      if (second) return [first, second];
      if (f.soegning) return [first, 'help.kol.about.soegning'];
      if (state === 'ordrer' && f.udenForslag) return [first, 'help.kol.about.uden_forslag'];
      return [first];
    },

    tasks: K1_OL_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'opsaetning') return ['KOL13', 'KOL12'];
      if (K1_TABS.indexOf(state) < 0) return [];
      const ids = [];
      // A search the user typed; the supplier filter from a card is put back by the page itself, so no Ryd søgning first
      const soeg = f.soegning && !f.leverandoer;
      if (f.tom) {
        if (soeg) ids.push('KOL5', 'KOL4');
        if (state === 'faktura') ids.push('KOL2', 'KOL1', 'KOL14');
        else ids.push('KOL1', 'KOL2', 'KOL3', 'KOL14');
        return unique(ids);
      }
      if (soeg) ids.push('KOL5');
      if (f.laast) ids.push('KOL10');
      if (state === 'forslag') ids.push('KOL6', 'KOL1', 'KOL11');
      else if (state === 'ordrer') {
        ids.push('KOL7', 'KOL2');
        if (f.udenForslag) ids.push('KOL6');
        ids.push('KOL1', 'KOL11');
      } else ids.push('KOL4', 'KOL9', 'KOL11');
      if (f.mange && !soeg) ids.push('KOL4');
      if (state !== 'faktura') ids.push('KOL8');
      ids.push('KOL3', 'KOL4', 'KOL14', 'KOL12');
      return unique(ids);
    },

    fields: K1_OL_FIELDS,

    // search[kredorliste_<valg>][field]: the date column is Tilbudsdato on Forslag and the sum has a name per tab (:296, :421)
    fieldKey: function (el) {
      const n = attr(el, 'name');
      const m = /^search\[kredorliste_([a-z]+)\]\[([a-z_]+)\]$/.exec(n);
      if (m) {
        if (m[2] === 'ordredate' && m[1] === 'forslag') return 'search:tilbudsdato';
        if (m[2] === 'sum') return 'search:' + (m[1] === 'faktura' ? 'fakturasum' : (m[1] === 'forslag' ? 'forslagssum' : 'ordresum'));
        return 'search:' + m[2];
      }
      if (/^rowcount\[/.test(n)) return 'rowcount';
      return null;
    },

    // Enter in a search field submits the grid form (grid :1241, :1360); no access keys in the S menu's top line.
    shortcuts: [
      { keys: 'Enter', text: 'help.kol.key.01', group: 'keys' }
    ],

    questions: {
      Q1: {
        q: 'help.kol.q.q1.q',
        words: k1words(['forsvundet', 'væk', 'kan ikke finde', 'hvor er', 'fane', 'forslag', 'ordrer', 'faktura', 'bogført', 'mangler'],
          ['gone', 'disappeared', 'cannot find', 'where is', 'tab', 'suggestions', 'orders', 'invoice', 'posted', 'missing'],
          ['forsvunnet', 'borte', 'finner ikke', 'hvor er', 'fane', 'forslag', 'ordrer', 'faktura', 'bokført', 'mangler']),
        intro: 'help.kol.q.q1.intro',
        steps: ['help.kol.q.q1.step1', 'help.kol.q.q1.step2', 'help.kol.q.q1.step3']
      },
      Q2: {
        q: 'help.kol.q.q2.q',
        words: k1words(['regning', 'udgift', 'husleje', 'telefon', 'el', 'uden varer', 'uden varenummer', 'faktura', 'kassekladde', 'kreditor'],
          ['bill', 'expense', 'rent', 'phone', 'electricity', 'without items', 'no item number', 'invoice', 'cash journal', 'supplier'],
          ['regning', 'utgift', 'husleie', 'telefon', 'strøm', 'uten varer', 'uten varenummer', 'faktura', 'kassekladd', 'leverandør']),
        intro: 'help.kol.q.q2.intro',
        steps: stepKeys(K1_KOL, 'KOL14', 1, 4)
      },
      Q3: {
        q: 'help.kol.q.q3.q',
        words: k1words(['send', 'sende', 'mail', 'e-mail', 'bestil', 'bestilling', 'leverandør', 'pdf', 'udskriv'],
          ['send', 'mail', 'email', 'order', 'supplier', 'pdf', 'print'],
          ['send', 'sende', 'e-post', 'bestill', 'bestilling', 'leverandør', 'pdf', 'skriv ut']),
        steps: ['help.kol.q.q3.step1', 'help.kol.q.q3.step2', 'help.kol.q.q3.step3'],
        // [1] opens the order: the first order row of Forslag or Ordrer
        show: { target: k1olFirstRow(['forslag', 'ordrer']), label: showKey(K1_KOL, 'KOL2'), kind: 'row' }
      },
      // The question for the Faktura tab, answered as KOL9: Kreditér on the posted invoice, then Godkend, Returnér and Bogfør
      Q4: {
        q: 'help.kol.q.q4.q',
        words: k1words(['ret', 'rette', 'fejl', 'forkert', 'bogført', 'kreditnota', 'kreditér', 'retur', 'returnere'],
          ['correct', 'error', 'wrong', 'posted', 'credit note', 'credit', 'return'],
          ['rett', 'rette', 'feil', 'bokført', 'kreditnota', 'krediter', 'retur', 'returnere']),
        intro: 'help.kol.task.kol9.why',
        steps: stepKeys(K1_KOL, 'KOL9', 1, 4),
        show: K1_OL_TASKS.KOL9.show
      }
    },

    // An empty list leads with the bill without items; Faktura with correcting a posted invoice, never with sending
    // an order to the supplier, which belongs to Forslag and Ordrer
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (f.tom) return state === 'faktura' ? ['Q2', 'Q1', 'Q4'] : ['Q2', 'Q1', 'Q3'];
      if (state === 'faktura') return ['Q4', 'Q1', 'Q2'];
      return ['Q1', 'Q3', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['kreditor/ordreliste.php'] = k1Ordreliste;

  // ---- Leverandørordre (kreditor/ordre.php)
  // Evidence, kreditor/ordre.php unless named; orderIncludes/ = kreditor/orderIncludes/. Reached by a row or Ny of the list,
  // the supplier card, ublimport (Importer → ordre.php?id=, ublimport.php:363) and posting (kreditor/bogfor.php:311). One
  // form name="ordre" (:1304) with the hidden ordrenr, fakturanr, status, id, art, konto_id, lager, omlev (:1435-1445);
  // then a closed order (status ≥ 3, orderIncludes/closedOrder.php), the split view (submit split,
  // orderIncludes/splitOrder.php) or the open order (orderIncludes/openOrderData.php, openOrderLines.php) (:1447-1456).
  // - Head: Kontonr. read-only once there is a supplier (openOrderData.php:65-71); without an id the link Importer OIOUBL
  //   faktura (:88-93); Ordredato and Forventet lev. dato 8407 (:109-112); the clip of the order's documents, paper.png
  //   when there are some (:185-191; closedOrder.php:80-86); Godkend at status 0, else Fakturanr. (:247-258).
  // - Lines: posn/vare/lev_varenr/anta/beskrivelse/pris/raba + x (openOrderLines.php:86-98); from status 1 the column Modtag
  //   (Returnér on a credit note) leve<x>, read-only when all of the line is received, and the received quantity in
  //   parentheses after it (:168-187); Udløbsdato/Batchnr. batch_due_date[x]/batch_batch_no[x] (:205-216, 249-260); the
  //   red × sets Pos. to - and saves (:286-292); the empty new line posn0/vare0 (read-only on a credit note, :296-305).
  // - Buttons (:341-386): Gem g, Opslag o; Bogfør b (postNow) only with status > 1 and every line received (:62, :348-351);
  //   else from status 1 Modtag m (receive) or, on a credit note, Returnér m (return); Slet (delete) without lines;
  //   Udskriv/Send (print) and Opdel (split) on a KO order with lines; CSV. Closed: Kopiér, Kreditér, Print, none on a
  //   closed credit note (closedOrder.php:243-255).
  // - Modtag (:1265-1270) goes to kreditor/modtag.php, which refuses an empty, earlier-than-Ordredato or out-of-year
  //   levdate, missing serial numbers and missing batch data (:108-127, :160-181) and returns to the order; the status is
  //   set from the lines on the next Gem (ordre.php:822, 902). Bogfør: Fakturanummer mangler (:1188), then
  //   kreditor/bogfor.php: the same date checks, Fakturanr. required (:122-147), status 3, fakturadate = levdate (:299),
  //   posted at once only with Omgående bogføring af købsordrer (DIV 3 box5, :303-307; systemdata/settingsDefinitions.php:839-841).
  // - The supplier lookup (Opslag in a new order's Kontonr./Firmanavn, or an unknown one on Gem, :690-698, 1218-1240) is
  //   includes/kreditorOrderFuncIncludes/accountLookup.php: grid kreditor_lookup, rows with td onclick selectAccount
  //   (:250-257), and the overlay Opret ny leverandør #createCreditorForm, shown when the search finds none (:298-312,
  //   :353-415; includes/orderFuncIncludes/grid_account_lookup.php:1930-1942). The contact lookup ansatopslag() (:1261, :1479).

  const K1_KORD = 'kord';

  /** The first element with this name that is not a hidden input. */
  function k1visible(doc, name) {
    return k1q(doc, 'input[name="' + name + '"]:not([type="hidden"])') || k1q(doc, 'select[name="' + name + '"]');
  }

  /** The Modtag/Returnér fields of the saved lines (leve1, leve2, … not lev_varenr, levdato). */
  function k1leve(doc) {
    return k1qa(doc, 'input[name^="leve"]').filter(function (i) { return /^leve\d+$/.test(attr(i, 'name')); });
  }

  /** The quantity in parentheses after a Modtag field: what is already received (openOrderLines.php:162). */
  function k1received(input) {
    const td = k1closest(input, 'td');
    const tr = td ? k1closest(td, 'tr') : null;
    if (!tr) return NaN;
    let cells = [];
    try {
      cells = Array.prototype.slice.call(tr.children || []);
    } catch (e) {
      cells = [];
    }
    const i = cells.indexOf(td);
    const next = i >= 0 ? cells[i + 1] : null;
    const t = k1txt(next);
    return /^\(.*\)$/.test(t) ? k1num(t) : NaN;
  }

  /** An empty Udløbsdato or Batchnr. on a line that is to be received (modtag.php:170-176). */
  function k1batchEmpty(doc) {
    const fields = k1qa(doc, 'input[name^="batch_due_date["], input[name^="batch_batch_no["]');
    for (let i = 0; i < fields.length; i++) {
      if (k1val(fields[i]) !== '') continue;
      const tr = k1closest(fields[i], 'tr');
      const leve = tr ? k1leve(tr)[0] : null;
      if (leve && !k1ro(leve) && k1num(k1val(leve)) > 0) return fields[i];
    }
    return null;
  }

  /** Is the overlay Opret ny leverandør shown? Its display comes from a style block or toggleCreateForm(). */
  function k1createShown(ctx) {
    const el = k1q(ctx.doc, '#createCreditorForm');
    if (!el) return false;
    let shown = null;
    try {
      const inline = el.style ? String(el.style.display || '') : '';
      if (inline) shown = inline !== 'none';
      else if (ctx.win && typeof ctx.win.getComputedStyle === 'function') {
        const d = ctx.win.getComputedStyle(el).display;
        if (d) shown = d !== 'none';
      }
    } catch (e) {
      shown = null;
    }
    if (shown !== null) return shown;
    // No style to read: the page shows it when a search finds no supplier
    return k1lookupRows(ctx.doc).length === 0 && k1qa(ctx.doc, 'input[name^="search[kreditor_lookup]"]').some(function (i) { return k1val(i) !== ''; });
  }

  /** The supplier rows of the lookup (td onclick selectAccount, or the row's own onclick after a live search). */
  function k1lookupRows(doc) {
    return k1qa(doc, 'table[id="datatable-kreditor_lookup"] tbody tr:not(.filler-row)').filter(function (tr) {
      return attr(tr, 'onclick') !== '' || !!k1q(tr, 'td[onclick]');
    });
  }

  function k1firstLookupRow(ctx) {
    return k1lookupRows(ctx.doc)[0] || null;
  }

  /** The row of the first saved line. */
  function k1firstLine(ctx) {
    return k1closest(k1q(ctx.doc, 'form[name="ordre"] input[name="posn1"]') || k1q(ctx.doc, 'input[name="posn1"]'), 'tr');
  }

  /** The link in the head to the credited order or to the credit note (ordre.php:1428-1430). */
  const K1_RELATED = 'a[href^="ordre.php?id="]';

  /** A show target that only counts in the given states of the purchase order: the chat's search offers every task in
   *  every state (spec §19.3), and some controls exist in other states with another meaning (the head link is the credit
   *  note on an invoice and the invoice on a credit note; Bogfør posts an invoice or a credit note; posn1 is hidden on a
   *  closed order). Without a state (a caller that passes none) the state is detected from the page. */
  function k1only(states, target) {
    return function (ctx, st) {
      let s = st && st.state ? st.state : '';
      if (!s) {
        try {
          s = k1Ordre.detect(ctx).state;
        } catch (e) {
          s = '';
        }
      }
      if (states.indexOf(s) < 0) return null;
      return typeof target === 'function' ? target(ctx, st) : k1q(ctx.doc, target);
    };
  }

  /** As k1only, and only with the flag set in the given state (other states listed in plain ring without it). */
  function k1onlyFlag(states, flagState, flag, target) {
    return function (ctx, st) {
      let d = st && st.state ? st : null;
      if (!d) {
        try {
          d = k1Ordre.detect(ctx);
        } catch (e) {
          d = null;
        }
      }
      if (!d || states.indexOf(d.state) < 0) return null;
      if (d.state === flagState && !(d.flags && d.flags[flag])) return null;
      return k1q(ctx.doc, target);
    };
  }

  const K1_ORD_TASKS = {
    // ny
    // Only on a new order: in the supplier lookup the Opret ny leverandør overlay has a Kontonr. of its own (live check)
    KO1: task(K1_KORD, 'KO1', 4, { target: k1only(['ny'], 'input[name="kontonr"]:not([readonly]):not([type="hidden"])'), label: fieldLabel(K1_KORD, 'kontonr'), kind: 'field' }),
    // [1] Opslag on Firmanavn opens the lookup with the name (ordre.php:1218-1240); [2] the overlay when none matches
    // (accountLookup.php:298-312), [3] the group KG (box1 VAT, box2 collective account, insertAccount.php:114-128;
    // kreditor/bogfor.php:363-367), [4] create_creditor → ordre.php?konto_id= creates the order (ordre.php:215-229, 233-284)
    // Opslag looks up suppliers only on a new order; on a saved one it looks up items or contacts (ordre.php:1227-1261)
    KO2: task(K1_KORD, 'KO2', 4, { target: k1only(['ny'], 'input[name="lookup"]'), label: showKey(K1_KORD, 'KO2'), kind: 'button' }),
    KO3: task(K1_KORD, 'KO3', 3, { target: 'a[href$="ublimport.php"]', label: showKey(K1_KORD, 'KO3'), kind: 'button' }),
    // [3] Opslag with the focus in Varenr. opens kreditor/productLookup.php (ordre.php:1240-1256)
    KO4: task(K1_KORD, 'KO4', 4, { target: 'input[name="vare0"]', label: fieldLabel(K1_KORD, 'vare'), kind: 'field' }),
    // forslag
    KO5: task(K1_KORD, 'KO5', 3, { target: 'input[name="godkend"]', label: fieldLabel(K1_KORD, 'godkend'), kind: 'field' }),
    // Udskriv til E-mail and E-mail make the button Send (openOrderLines.php:366-378, text 2311; ordre.php:1207-1215)
    // Only on an open purchase order: a closed one prints Print, a PDF only (closedOrder.php:250-253)
    KO6: task(K1_KORD, 'KO6', 3, { target: k1only(['forslag', 'ordre', 'klar'], 'input[name="print"]'), label: showKey(K1_KORD, 'KO6'), kind: 'button' }),
    // Slet only without lines (openOrderLines.php:362-365); received goods block it (ordre.php:550-566)
    KO7: task(K1_KORD, 'KO7', 3, { target: 'input[name="delete"]', label: showKey(K1_KORD, 'KO7'), kind: 'button' }),
    // Varenr. is read-only on a saved line (openOrderLines.php:87, text 1513); the red × (:286-292). A closed order has
    // posn1 as a hidden field (closedOrder.php:187), so only the open states.
    KO8: task(K1_KORD, 'KO8', 3, { target: k1only(['forslag', 'ordre', 'klar', 'kreditnota'], k1firstLine), label: showKey(K1_KORD, 'KO8'), kind: 'row' }),
    // ordre
    KO9: task(K1_KORD, 'KO9', 4, { target: 'input[name="receive"]', label: showKey(K1_KORD, 'KO9'), kind: 'button' }),
    KO10: task(K1_KORD, 'KO10', 3, { target: 'input[name="fakturanr"]:not([type="hidden"])', label: fieldLabel(K1_KORD, 'fakturanr'), kind: 'field' }),
    // Opdel: splitOrder.php (2010, 2011, Flyt), orderIncludes/moveOrderLines.php keeps the order number (JOB-128)
    // Opdel is printed on every open purchase order; it is a part delivery only after a part receipt (flag delvis)
    KO11: task(K1_KORD, 'KO11', 4, { target: k1onlyFlag(['ordre'], 'ordre', 'delvis', 'input[name="split"]'), label: showKey(K1_KORD, 'KO11'), kind: 'button' }),
    // Gem makes the order ready only when all is received (status 1 to 2, ordre.php:822, 902; live check), not after a part
    KO12: task(K1_KORD, 'KO12', 2, { target: k1onlyFlag(['ordre', 'klar'], 'ordre', 'alleModtaget', 'input[name="save"]'), label: showKey(K1_KORD, 'KO12'), kind: 'button' }),
    KO13: task(K1_KORD, 'KO13', 3, { target: 'a[href*="source=creditorOrder"]', label: showKey(K1_KORD, 'KO13'), kind: 'button' }),
    // klar; on a credit note the same Bogfør is KO16
    KO14: task(K1_KORD, 'KO14', 4, { target: k1only(['klar'], 'input[name="postNow"]'), label: showKey(K1_KORD, 'KO14'), kind: 'button' }),
    // kreditnota
    KO15: task(K1_KORD, 'KO15', 4, { target: 'input[name="return"]', label: showKey(K1_KORD, 'KO15'), kind: 'button' }),
    KO16: task(K1_KORD, 'KO16', 3, { target: k1only(['kreditnota'], 'input[name="postNow"]'), label: showKey(K1_KORD, 'KO14'), kind: 'button' }),
    // The head link is the credited invoice only on a credit note; on a credited invoice it is the credit note (KO22)
    KO17: task(K1_KORD, 'KO17', 2, { target: k1only(['kreditnota', 'bogfoertKreditnota'], K1_RELATED), label: showKey(K1_KORD, 'KO17'), kind: 'button' }),
    // bogfoert: Kreditér/Kopiér make a new order, status 0, with today's Ordredato and the old levdate (ordre.php:634-652;
    // insertAccount.php:141-146); the credited quantity is what is left of the purchase (ordre.php:1153-1176)
    KO18: task(K1_KORD, 'KO18', 3, { target: 'input[name="credit"]', label: showKey(K1_KORD, 'KO18'), kind: 'button' }, stepKeys(K1_KORD, 'KO15', 4, 4)),
    KO19: task(K1_KORD, 'KO19', 2, { target: 'input[name="copy"]', label: showKey(K1_KORD, 'KO19'), kind: 'button' }, stepKeys(K1_KORD, 'KO5', 2, 2)),
    // Print of a closed invoice; an open order's print button is KO6
    KO20: task(K1_KORD, 'KO20', 2, { target: k1only(['bogfoert'], 'input[name="print"]'), label: showKey(K1_KORD, 'KO20'), kind: 'button' }),
    // The setting is in Indstillinger (includes/settings/hub.php: Køb & lager, Kreditorer, Købsordrer); no Vis mig
    KO21: task(K1_KORD, 'KO21', 3),
    KO22: task(K1_KORD, 'KO22', 2, { target: k1only(['bogfoert'], K1_RELATED), label: showKey(K1_KORD, 'KO22'), kind: 'button' }),
    // problems
    KO23: task(K1_KORD, 'KO23', 3, { target: 'input[name="levdato"]:not([type="hidden"])', label: fieldLabel(K1_KORD, 'levdato'), kind: 'field' }),
    KO24: task(K1_KORD, 'KO24', 3, { target: function (ctx) { return k1batchEmpty(ctx.doc); }, label: showKey(K1_KORD, 'KO24'), kind: 'field' }),
    // opslag
    KO25: task(K1_KORD, 'KO25', 2, { target: '#createCreditorForm input[name="create_creditor"]', label: showKey(K1_KORD, 'KO25'), kind: 'button' },
      stepKeys(K1_KORD, 'KO2', 3, 4)),
    KO26: task(K1_KORD, 'KO26', 2, { target: k1firstLookupRow, label: showKey(K1_KORD, 'KO26'), kind: 'row' }),
    // opdel
    KO27: task(K1_KORD, 'KO27', 4, { target: 'input[name="moveOrderLines"]', label: showKey(K1_KORD, 'KO27'), kind: 'button' }),
    // kontaktopslag
    KO29: task(K1_KORD, 'KO29', 2, { target: 'a[href*="kontakt="]', label: showKey(K1_KORD, 'KO29'), kind: 'row' })
  };

  const K1_ORD_FIELDS = {};
  ['kontonr', 'firmanavn', 'ordredato', 'levdato', 'fakturanr', 'godkend', 'momssats', 'betalingsbet', 'betalingsdage',
    'udskriv_til', 'email', 'valuta', 'ref', 'kundeordnr', 'lager', 'posn', 'vare', 'lev_varenr', 'anta', 'beskrivelse',
    'pris', 'raba', 'leve', 'batch_due_date', 'batch_batch_no', 'omvbet', 'flyt_til', 'flyt_antal', 'flyt_modtaget',
    'ny_kontonr', 'ny_firmanavn', 'ny_grp', 'search:firmanavn']
    .forEach(function (k) { K1_ORD_FIELDS[k] = fieldKeys(K1_KORD, k); });

  const K1_ORD_OPEN = ['forslag', 'ordre', 'klar', 'kreditnota'];
  const K1_ORD_FLAGS = ['ingenVarer', 'delvis', 'alleModtaget', 'manglerLevdato', 'levdatoFoer', 'manglerFakturanr', 'batchMangler',
    'ikkeGodkendt', 'kanBogfoeres', 'harBilag', 'omvendt', 'ikkeBogfoert', 'krediteret', 'opret'];

  const k1Ordre = {
    group: K1_KORD,
    states: ['ny', 'forslag', 'ordre', 'klar', 'kreditnota', 'bogfoert', 'bogfoertKreditnota', 'opdel', 'opslag', 'kontaktopslag'],
    flags: K1_ORD_FLAGS,

    name: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const nr = String((st && st.data && st.data.ordrenr) || '');
      if (state === 'ny') return 'help.kord.name_new';
      if (!nr || state === 'opslag' || state === 'kontaktopslag' || state === 'unknown') return 'help.kord.name_plain';
      if (state === 'kreditnota' || state === 'bogfoertKreditnota') return ['help.kord.name_kreditnota', nr];
      return ['help.kord.name', nr];
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = {};
      K1_ORD_FLAGS.forEach(function (f) { flags[f] = false; });
      const form = k1q(doc, 'form[name="ordre"]');
      if (!form) {
        if (k1q(doc, 'table[id="datatable-kreditor_lookup"], #createCreditorForm')) {
          flags.opret = k1createShown(ctx);
          return { state: 'opslag', flags: flags, data: {} };
        }
        if (k1q(doc, 'a[href*="kontakt="]')) return { state: 'kontaktopslag', flags: flags, data: {} };
        return { state: 'unknown', flags: {}, data: {} };
      }
      const status = parseInt(k1val(byName(doc, 'status')), 10) || 0;
      const kk = k1val(byName(doc, 'art')).toUpperCase() === 'KK';
      const id = k1val(byName(doc, 'id'));
      let state;
      if (k1q(doc, 'select[name="MoveItemsTo"]')) state = 'opdel';
      else if (status >= 3) state = kk ? 'bogfoertKreditnota' : 'bogfoert';
      else if (kk) state = 'kreditnota';
      else if (!id || id === '0') state = 'ny';
      else if (k1q(doc, 'input[name="godkend"]')) state = 'forslag';
      else if (k1q(doc, 'input[name="postNow"]')) state = 'klar';
      else state = 'ordre';

      const items = k1qa(doc, 'input[name^="vare"]').filter(function (i) { return /^vare[1-9]\d*$/.test(attr(i, 'name')) && k1val(i) !== ''; });
      const leve = k1leve(doc);
      const done = leve.filter(k1ro);
      const lev = k1visible(doc, 'levdato');
      const ord = k1visible(doc, 'ordredato');
      const fak = k1q(doc, 'input[name="fakturanr"]:not([type="hidden"])');
      const levDate = k1date(k1val(lev));
      const ordDate = k1date(k1val(ord));
      const open = K1_ORD_OPEN.indexOf(state) >= 0;
      flags.ingenVarer = state !== 'opdel' && items.length === 0;
      flags.alleModtaget = leve.length > 0 && done.length === leve.length && !k1q(doc, 'input[name="postNow"]');
      // With Bogfør on the page (status 2) everything is received (live check), so it is no part receipt
      flags.delvis = !flags.alleModtaget && !k1q(doc, 'input[name="postNow"]') && leve.some(function (i) { return k1ro(i) || k1received(i) > 0; });
      flags.manglerLevdato = open && !!lev && k1val(lev) === '';
      flags.levdatoFoer = open && levDate > 0 && ordDate > 0 && levDate < ordDate;
      flags.manglerFakturanr = !!fak && (k1val(fak) === '' || k1val(fak) === '0');
      // The batch check of a receipt is for purchases, not returns (modtag.php:178)
      flags.batchMangler = state === 'ordre' && !!k1batchEmpty(doc);
      flags.ikkeGodkendt = !!k1q(doc, 'input[name="godkend"]');
      flags.kanBogfoeres = !!k1q(doc, 'input[name="postNow"]');
      flags.harBilag = !!k1q(doc, 'a[href*="source=creditorOrder"] img[src*="paper"]');
      flags.omvendt = k1val(byName(doc, 'omlev')) !== '';
      flags.ikkeBogfoert = status === 3;
      flags.krediteret = state === 'bogfoert' && !!k1q(doc, K1_RELATED);
      return { state: state, flags: flags, data: { ordrenr: k1val(byName(doc, 'ordrenr')), status: status } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'ny') return ['help.kord.about.ny', 'help.kord.about.varer'];
      if (state === 'forslag') {
        if (f.ingenVarer) return ['help.kord.about.forslag', 'help.kord.about.varer'];
        return f.levdatoFoer ? ['help.kord.about.forslag', 'help.kord.about.dato_foer'] : ['help.kord.about.forslag'];
      }
      if (state === 'ordre') {
        if (f.levdatoFoer) return ['help.kord.about.ordre', 'help.kord.about.dato_foer'];
        if (f.alleModtaget) return ['help.kord.about.ordre', 'help.kord.about.alle_modtaget'];
        if (f.delvis) return ['help.kord.about.ordre', 'help.kord.about.delvis'];
        if (f.manglerLevdato) return ['help.kord.about.ordre', 'help.kord.about.dato'];
        return ['help.kord.about.ordre'];
      }
      if (state === 'klar') return ['help.kord.about.klar', f.levdatoFoer ? 'help.kord.about.dato_foer' : 'help.kord.about.konti'];
      if (state === 'kreditnota') {
        if (f.ikkeGodkendt) return ['help.kord.about.kreditnota', 'help.kord.about.kreditnota_godkend'];
        if (f.levdatoFoer) return ['help.kord.about.kreditnota', 'help.kord.about.dato_foer'];
        if (f.kanBogfoeres) return ['help.kord.about.kreditnota', 'help.kord.about.kreditnota_klar'];
        return ['help.kord.about.kreditnota', 'help.kord.about.kreditnota_retur'];
      }
      if (state === 'bogfoert') {
        if (f.ikkeBogfoert) return ['help.kord.about.bogfoert', 'help.kord.about.ikke_bogfoert'];
        return f.krediteret ? ['help.kord.about.bogfoert', 'help.kord.about.krediteret'] : ['help.kord.about.bogfoert'];
      }
      if (state === 'bogfoertKreditnota') return ['help.kord.about.bogfoert_kreditnota'];
      if (state === 'opdel') return ['help.kord.about.opdel'];
      if (state === 'opslag') return f.opret ? ['help.kord.about.opslag', 'help.kord.about.opret'] : ['help.kord.about.opslag'];
      if (state === 'kontaktopslag') return ['help.kord.about.kontaktopslag'];
      return [];
    },

    tasks: K1_ORD_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      const pid = k1ordProblem(state, f);
      if (pid) ids.push(pid);
      if (state === 'ny') ids.push('KO1', 'KO2', 'KO3', 'KO4');
      else if (state === 'forslag') {
        // Without items: add them first; Godkend before that would approve an empty order
        if (f.ingenVarer) ids.push('KO4', 'KO5', 'KO7');
        ids.push('KO5', 'KO4', 'KO6', 'KO8', 'KO13', 'KO7', 'KO23');
      } else if (state === 'ordre') {
        if (f.ingenVarer) ids.push('KO4');
        if (f.alleModtaget) ids.push('KO12', 'KO10');
        else {
          ids.push('KO9');
          if (f.delvis) ids.push('KO11');
          ids.push('KO10');
        }
        ids.push('KO13', 'KO6', 'KO23', f.delvis ? 'KO11' : '', 'KO8', 'KO4');
      } else if (state === 'klar') {
        // Fakturanr. is filled in here unless it is the problem; a price that differs from the invoice is corrected on the line
        ids.push('KO14', 'KO13', 'KO8', 'KO23', 'KO10');
      }
      else if (state === 'kreditnota') {
        if (f.ikkeGodkendt) ids.push('KO5', 'KO8', 'KO17');
        else if (f.kanBogfoeres) ids.push('KO16', 'KO10', 'KO17', 'KO23');
        else ids.push('KO15', 'KO10', 'KO17', 'KO23', 'KO8');
      } else if (state === 'bogfoert') {
        if (f.ikkeBogfoert) ids.push('KO21');
        if (f.krediteret) ids.push('KO22');
        ids.push('KO18', 'KO19', 'KO13', 'KO20');
      } else if (state === 'bogfoertKreditnota') ids.push('KO17', 'KO13');
      else if (state === 'opdel') ids.push('KO27');
      else if (state === 'opslag') ids.push(f.opret ? 'KO25' : 'KO26', 'KO25', 'KO26');
      else if (state === 'kontaktopslag') ids.push('KO29');
      return unique(ids.filter(function (id) { return !!id; }));
    },

    problem: function (ctx, st) {
      return k1ordProblem(st ? st.state : '', (st && st.flags) || {});
    },

    fields: K1_ORD_FIELDS,

    // The default rule (vare3 → vare, addr1 → addr) plus the names it cannot map: arrays, the split view, the create overlay
    // and the lookup grid's search fields.
    fieldKey: function (el) {
      const n = attr(el, 'name');
      let m;
      if (k1closest(el, 'form[name="create_creditor"]')) return n ? 'ny_' + n.replace(/_?\d+$/, '') : null;
      if ((m = /^search\[[^\]]+\]\[([a-z_]+)\]$/.exec(n))) return 'search:' + m[1];
      if (/^rowcount\[/.test(n)) return 'rowcount';
      if ((m = /^(projekt|batch_due_date|batch_batch_no|omvbet)\[\d+\]$/.exec(n))) return m[1];
      if (n === 'MoveItemsTo') return 'flyt_til';
      if (/^mQt\[/.test(n)) return 'flyt_antal';
      if (/^mSQt\[/.test(n)) return 'flyt_modtaget';
      if (n) {
        const k = n.replace(/_?\d+$/, '');
        return /^[a-z_]+$/.test(k) ? k : null;
      }
      return null;
    },

    // Access keys g, o, b, m (openOrderLines.php:343-359) and N, the new order in the top line
    // (includes/kreditorOrderFuncIncludes/topLine_S.php:82-88); Enter in a field submits the form with its first submit
    // button, Gem.
    shortcuts: [
      { keys: 'Enter', text: 'help.kord.key.01', group: 'keys' },
      { keys: 'Alt+G', text: 'help.kord.key.02', group: 'keys' },
      { keys: 'Alt+O', text: 'help.kord.key.03', group: 'keys' },
      { keys: 'Alt+M', text: 'help.kord.key.04', group: 'keys' },
      { keys: 'Alt+B', text: 'help.kord.key.05', group: 'keys' },
      { keys: 'Alt+N', text: 'help.kord.key.06', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.kord.q.q1.q',
        words: k1words(['bogfør', 'bogføre', 'kan ikke bogføre', 'mangler', 'knap', 'vises ikke', 'modtaget', 'modtag'],
          ['post', 'cannot post', 'missing', 'button', 'not shown', 'received', 'receive'],
          ['bokfør', 'bokføre', 'kan ikke bokføre', 'mangler', 'knapp', 'vises ikke', 'mottatt', 'motta']),
        intro: 'help.kord.q.q1.intro',
        steps: ['help.kord.q.q1.step1', 'help.kord.q.q1.step2', 'help.kord.q.q1.step3']
      },
      Q2: {
        q: 'help.kord.q.q2.q',
        words: k1words(['leveringsdato', 'ikke udfyldt', 'før ordredato', 'udenfor regnskabsår', 'uden for regnskabsår', 'afvist',
          'serienumre', 'batchoplysninger', 'fejl', 'besked'],
          ['delivery date', 'not filled', 'before order date', 'outside financial year', 'rejected', 'serial numbers', 'batch', 'error', 'message'],
          ['leveringsdato', 'ikke utfylt', 'før ordredato', 'utenfor regnskapsår', 'avvist', 'serienumre', 'batch', 'feil', 'melding']),
        intro: 'help.kord.q.q2.intro',
        steps: ['help.kord.q.q2.step1', 'help.kord.q.q2.step2', 'help.kord.q.q2.step3', 'help.kord.q.q2.step4'],
        show: K1_ORD_TASKS.KO23.show
      },
      Q3: {
        q: 'help.kord.q.q3.q',
        words: k1words(['ret', 'rette', 'fejl', 'bogført', 'kreditnota', 'kreditere', 'returnere', 'retur', 'fortryd', 'annuller'],
          ['correct', 'error', 'posted', 'credit note', 'credit', 'return', 'undo', 'cancel'],
          ['rette', 'feil', 'bokført', 'kreditnota', 'kreditere', 'returnere', 'retur', 'angre', 'annuller']),
        intro: 'help.kord.q.q3.intro',
        steps: K1_ORD_TASKS.KO18.steps,
        show: K1_ORD_TASKS.KO18.show
      },
      Q4: {
        q: 'help.kord.q.q4.q',
        words: k1words(['konto', 'konti', 'varekøb', 'købsmoms', 'moms', 'samlekonto', 'kreditorgruppe', 'varegruppe', 'postering'],
          ['account', 'accounts', 'purchases', 'input vat', 'vat', 'control account', 'creditor group', 'product group', 'entry'],
          ['konto', 'kontoer', 'varekjøp', 'inngående mva', 'mva', 'samlekonto', 'kreditorgruppe', 'varegruppe', 'postering']),
        intro: 'help.kord.q.q4.intro',
        steps: ['help.kord.q.q4.step1', 'help.kord.q.q4.step2', 'help.kord.q.q4.step3', 'help.kord.q.q4.step4']
      },
      // The refusals of Bogfør: ordre.php:1186-1188, kreditor/bogfor.php:106, 113, 122-147, 217
      Q5: {
        q: 'help.kord.q.q5.q',
        words: k1words(['afviser', 'kan ikke bogføre', 'fakturanummer mangler', 'kontonummer for varekøb', 'varegruppe', 'valutakurs',
          'leveringsdato', 'fejl', 'besked'],
          ['refused', 'cannot post', 'invoice number missing', 'purchase account', 'product group', 'exchange rate', 'delivery date', 'error', 'message'],
          ['avviser', 'kan ikke bokføre', 'fakturanummer mangler', 'konto for varekjøp', 'varegruppe', 'valutakurs', 'leveringsdato', 'feil', 'melding']),
        intro: 'help.kord.q.q5.intro',
        steps: ['help.kord.q.q5.step1', 'help.kord.q.q5.step2', 'help.kord.q.q5.step3', 'help.kord.q.q5.step4'],
        // Bogfør of an invoice or a credit note: the refusals are the same
        show: { target: k1only(['klar', 'kreditnota'], 'input[name="postNow"]'), label: showKey(K1_KORD, 'KO14'), kind: 'button' }
      },
      // A posted invoice is an open item on the supplier until a payment settles it: the kassekladde with the supplier in
      // Debet and K in D/K settles the open item whose faktnr is the invoice number when the amounts net to zero
      // (finans/bogfor.php:1172-1215, art K as D), as AP4 on Kreditorrapporter. The invoice view has no paid mark to ring.
      Q6: {
        q: 'help.kord.q.q6.q',
        words: k1words(['betalt', 'betaling', 'betale', 'registrere', 'markere', 'udlign', 'udligne', 'regningen er betalt', 'kassekladde'],
          ['paid', 'payment', 'pay', 'record payment', 'mark as paid', 'settle', 'bill paid', 'cash journal'],
          ['betalt', 'betaling', 'betale', 'registrere', 'markere', 'utlign', 'utligne', 'regningen er betalt', 'kassekladd']),
        steps: ['help.kord.q.q6.step1', 'help.kord.q.q6.step2', 'help.kord.q.q6.step3']
      }
    },

    // The next step's refusal first: Bogfør missing before receipt, the receipt refused on an order (first when the
    // problem card names what will refuse it, k1ordProblem()), the posting refused when it is ready; a posted invoice is
    // corrected and paid. A new or open order no longer offers correcting a posted invoice.
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const p = k1ordProblem(state, f);
      if (state === 'ordre' || state === 'kreditnota') return p === 'KO23' || p === 'KO24' ? ['Q2', 'Q1', 'Q4'] : ['Q1', 'Q2', 'Q4'];
      if (state === 'klar') return ['Q5', 'Q4', 'Q3'];
      if (state === 'bogfoert') return ['Q3', 'Q6', 'Q4'];
      if (state === 'bogfoertKreditnota') return ['Q3', 'Q4', 'Q6'];
      return ['Q1', 'Q2', 'Q4'];
    },

    signal: function () {
      return false;
    }
  };

  /** The task to meet the user with first: something on the order that will make Modtag, Returnér or Bogfør refuse. */
  function k1ordProblem(state, f) {
    if (state === 'ordre' && f.batchMangler) return 'KO24';
    if (K1_ORD_OPEN.indexOf(state) >= 0 && state !== 'forslag' && f.levdatoFoer) return 'KO23';
    const posting = state === 'klar' || (state === 'kreditnota' && f.kanBogfoeres);
    if (posting && f.manglerLevdato) return 'KO23';
    if (posting && f.manglerFakturanr) return 'KO10';
    return '';
  }

  PAGES['kreditor/ordre.php'] = k1Ordre;

  // ---- OIOUBL import (kreditor/ublimport.php)
  // Evidence, kreditor/ublimport.php unless named: the link Importer OIOUBL faktura on a new purchase order
  // (kreditor/orderIncludes/openOrderData.php:88-93), in the frame. Luk (2172, accesskey L) back to the order (:69-77).
  // upload(): Vælg datafil uploadedfile and Hent (:144-152). importer(): vis_oioubl() shows the invoice (:373, :549-569);
  // no supplier with the file's CVR (cvrnr, or without a DK prefix, :192-196): form opret_kreditor, the group select
  // opret_lev only with more than one creditor group, Opret (:228-243); an item number the supplier does not have: form
  // opret_vare with Varegruppe: (only with more than one item group), Varenr:, Salgspris:, Opret and Tilknyt (:300-330);
  // else form importer with Importer (:365-368), which makes a KO order with status 1, levdate = ordredate from the file
  // and the invoice number, and opens it (:337-363). No check for an invoice imported before. A failed upload prints only
  // a message (:105).

  const K1_KUBL = 'kubl';

  const K1_UBL_TASKS = {
    KU1: task(K1_KUBL, 'KU1', 3, { target: 'input[name="uploadedfile"]', label: fieldLabel(K1_KUBL, 'uploadedfile'), kind: 'field' }),
    KU2: task(K1_KUBL, 'KU2', 3, { target: 'form[name="opret_kreditor"] input[name="opret"]', label: showKey(K1_KUBL, 'KU2'), kind: 'button' }),
    // One creditor group: no opret_lev to post, so the form returns (:107, :167, :233); the supplier card is the way.
    KU3: task(K1_KUBL, 'KU3', 3),
    KU4: task(K1_KUBL, 'KU4', 4, { target: 'input[name="opret_varenr"]', label: fieldLabel(K1_KUBL, 'opret_varenr'), kind: 'field' }),
    KU5: task(K1_KUBL, 'KU5', 4, { target: 'input[name="importer"]', label: showKey(K1_KUBL, 'KU5'), kind: 'button' }),
    KU6: task(K1_KUBL, 'KU6', 2, { target: 'a[accesskey="L"]', label: showKey(K1_KUBL, 'KU6'), kind: 'button' })
  };

  const K1_UBL_FIELDS = {};
  ['uploadedfile', 'opret_lev', 'opret_vare', 'opret_varenr', 'salgspris']
    .forEach(function (k) { K1_UBL_FIELDS[k] = fieldKeys(K1_KUBL, k); });

  const k1Ublimport = {
    group: K1_KUBL,
    states: ['upload', 'opretLeverandoer', 'opretVare', 'klar', 'fejl'],
    flags: ['vaelgGruppe', 'varenrFindes'],

    name: function () {
      return 'help.kubl.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = { vaelgGruppe: false, varenrFindes: false };
      let state = 'fejl';
      if (k1q(doc, 'input[name="uploadedfile"]')) state = 'upload';
      else if (k1q(doc, 'form[name="opret_kreditor"]')) {
        state = 'opretLeverandoer';
        flags.vaelgGruppe = !!k1q(doc, 'select[name="opret_lev"]');
      } else if (k1q(doc, 'form[name="opret_vare"]')) {
        state = 'opretVare';
        // The hard-coded line when the number is another item's own number (:304-306)
        flags.varenrFindes = k1txt(k1q(doc, 'form[name="opret_vare"]')).indexOf('eksisterer i systemet') >= 0;
      } else if (k1q(doc, 'input[name="importer"]')) state = 'klar';
      else if (k1q(doc, 'form[name="gennemse"]')) state = 'unknown';   // the old pool view, not linked (:118-121, :602-662)
      return { state: state, flags: flags, data: {} };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      if (state === 'upload') return ['help.kubl.about.upload'];
      if (state === 'opretLeverandoer') return ['help.kubl.about.leverandoer'];
      if (state === 'opretVare') return ['help.kubl.about.vare'];
      if (state === 'klar') return ['help.kubl.about.klar'];
      if (state === 'fejl') return ['help.kubl.about.fejl'];
      return [];
    },

    tasks: K1_UBL_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'upload') return ['KU1'];
      if (state === 'opretLeverandoer') return f.vaelgGruppe ? ['KU2', 'KU3'] : ['KU3'];
      if (state === 'opretVare') return ['KU4'];
      if (state === 'klar') return ['KU5'];
      if (state === 'fejl') return ['KU6'];
      return [];
    },

    problem: function (ctx, st) {
      return st && st.state === 'fejl' ? 'KU6' : '';
    },

    fields: K1_UBL_FIELDS,

    // Luk is Alt+L, which works everywhere; nothing of the page's own
    shortcuts: [],

    questions: {
      Q1: {
        q: 'help.kubl.q.q1.q',
        words: k1words(['leverandør', 'findes ikke', 'cvr', 'cvr-nummer', 'ingen leverandører', 'opret', 'kreditor'],
          ['supplier', 'not found', 'vat number', 'cvr', 'no suppliers', 'create', 'creditor'],
          ['leverandør', 'finnes ikke', 'org.nr', 'cvr', 'ingen leverandører', 'opprett', 'kreditor']),
        intro: 'help.kubl.q.q1.intro',
        steps: stepKeys(K1_KUBL, 'KU3', 1, 3)
      },
      Q2: {
        q: 'help.kubl.q.q2.q',
        words: k1words(['oioubl', 'xml', 'e-faktura', 'elektronisk faktura', 'nemhandel', 'fil', 'pdf'],
          ['oioubl', 'xml', 'e-invoice', 'electronic invoice', 'file', 'pdf'],
          ['oioubl', 'xml', 'e-faktura', 'elektronisk faktura', 'fil', 'pdf']),
        intro: 'help.kubl.q.q2.intro',
        steps: ['help.kubl.q.q2.step1', 'help.kubl.q.q2.step2']
      }
    },

    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      return state === 'opretLeverandoer' ? ['Q1', 'Q2'] : ['Q2', 'Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['kreditor/ublimport.php'] = k1Ublimport;

  // ==== Suppliers, payments and reports: the list, the card, reports, payment lists, merge, Top 100 (author K2; helpers k2)
  // ==================================================================== K2: Kreditorer (konti), Kreditorkort, Kreditorrapporter,
  // Betalingslister, Fusioner kreditorer and Top 100. Every selector is read from the page's own output in the shell's
  // sidebar layout ($menu 'S', the default a user gets, includes/online.php:333); the evidence is in the comment above each
  // selector. Plain functions on top of the shared helpers; detectors and show targets only read the frame document.

  /** querySelector that never throws; null when nothing matches. */
  function k2q(root, sel) {
    try {
      const el = root && root.querySelector ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  /** querySelectorAll as an array of elements; [] on an odd root or selector. */
  function k2qa(root, sel) {
    try {
      return root && root.querySelectorAll ? Array.prototype.slice.call(root.querySelectorAll(sel)).filter(function (el) {
        return el && el.nodeType === 1;
      }) : [];
    } catch (e) {
      return [];
    }
  }

  function k2text(el) {
    return el && el.nodeType === 1 ? String(el.textContent || '') : '';
  }

  /** The current value of a control, trimmed; '' for anything else. */
  function k2val(el) {
    if (!el || el.nodeType !== 1) return '';
    try {
      const v = el.value;
      return String(v === undefined || v === null ? (el.getAttribute('value') || '') : v).trim();
    } catch (e) {
      return '';
    }
  }

  /** A URL parameter of the frame, '' when it is missing. */
  function k2param(ctx, name) {
    try {
      return ctx && ctx.url && ctx.url.searchParams ? String(ctx.url.searchParams.get(name) || '') : '';
    } catch (e) {
      return '';
    }
  }

  /** The button inside a link (the shell layout prints <a><button>), else the link itself. */
  function k2btn(a) {
    if (!a) return null;
    return k2q(a, 'button') || a;
  }

  /** The row of an element (closest tr), or the element itself. */
  function k2row(el) {
    try {
      return el && el.closest ? (el.closest('tr') || el) : el;
    } catch (e) {
      return el;
    }
  }

  /** The first element any of the selectors finds, in the order given. */
  function k2first(doc, sels) {
    for (let i = 0; i < sels.length; i++) {
      const el = k2q(doc, sels[i]);
      if (el) return el;
    }
    return null;
  }

  /** True when a grid's search row has text (includes/grid.php:1396; the grid stores the search per user, :426-429). */
  function k2searching(doc, gridId) {
    const inputs = k2qa(doc, 'input[name^="search[' + gridId + ']"]');
    for (let i = 0; i < inputs.length; i++) {
      if (k2val(inputs[i]) !== '') return true;
    }
    return false;
  }

  /** Every flag name set to false, so the generic tests and the digest see the same shape in every state. */
  function k2flags(names) {
    const f = {};
    names.forEach(function (n) { f[n] = false; });
    return f;
  }

  /** A show target that only counts in the given states: the chat's search offers every task in every state (spec §19.3),
   *  so a generic selector must ring nothing on a page where it means something else. */
  function k2only(states, target) {
    return function (ctx, st) {
      if (!st || states.indexOf(st.state) === -1) return null;
      if (typeof target === 'function') return target(ctx, st);
      return k2q(ctx.doc, target);
    };
  }

  /** The first task of a page's own order, '' when there is none (the problem card, spec §18.2). */
  function k2firstTask(page, ctx, st) {
    const o = page.order(ctx, st);
    return Array.isArray(o) && o.length ? String(o[0]) : '';
  }

  // ---- Kreditorer (kreditor/kreditor.php)
  // Sidebar Kreditorer » Konti (index/main.php:327-329), loaded into the frame with update_iframe(). Evidence, kreditor.php
  // unless named: the shell layout's head with Ny, a link to kreditorkort.php?returside=kreditor.php around a button
  // (:119-152, :135-137; the classic head has the same link, :160); the grid 'kreditor_list' (:237-394): search fields
  // search[kreditor_list][<field>] (grid.php:1396), the filter Status with Aktive (on by default) and Lukkede (:360-379),
  // closed rows with opacity 0.6 (:380-386), a hidden td[data-kreditor-id] per row (:387-390); a click on a row opens the
  // card (:397-418). The grid's menu .dropdown-trigger (grid.php:1415-1460: Søg, Ryd søgning, Eksportér til CSV/PDF,
  // Redigér » Kolonner/Filtre), the filter setup #filter-wrapper in a POST form (grid.php:2030-2063) and the column setup
  // in a POST form (grid.php:1636-1662, rows[<grid>][n][pos] with move and delete buttons, :1777-1790). CSV and PDF export
  // the rows on the page (grid.php:2524-2560).

  const K2_KRED_ROWS = '#datatable-kreditor_list td[data-kreditor-id]';

  function k2KredFirstRow(ctx) {
    return k2row(k2q(ctx.doc, K2_KRED_ROWS));
  }

  function k2KredNew(ctx) {
    return k2btn(k2q(ctx.doc, 'a[href="kreditorkort.php?returside=kreditor.php"]'));
  }

  function k2KredSearchField(ctx) {
    const inputs = k2qa(ctx.doc, 'input[name^="search[kreditor_list]"]');
    for (let i = 0; i < inputs.length; i++) {
      if (k2val(inputs[i]) !== '') return inputs[i];
    }
    return null;
  }

  const K2_KRED_MENU = { target: k2only(['liste', 'tom'], '#datatable-wrapper-kreditor_list .dropdown-trigger'), label: showKey('kred', 'KR6'), kind: 'button' };
  const K2_KRED_ROW = { target: k2only(['liste'], k2KredFirstRow), label: showKey('kred', 'KR3'), kind: 'row' };

  const K2_KRED_TASKS = {
    KR1: task('kred', 'KR1', 4, { target: k2KredNew, label: showKey('kred', 'KR1'), kind: 'button' }),
    KR2: task('kred', 'KR2', 3, { target: 'input[name="search[kreditor_list][firmanavn]"]', label: fieldLabel('kred', 'search:firmanavn'), kind: 'field' }),
    KR3: task('kred', 'KR3', 2, K2_KRED_ROW),
    KR4: task('kred', 'KR4', 2),
    KR5: task('kred', 'KR5', 2, { target: k2KredSearchField, label: showKey('kred', 'KR5'), kind: 'field' }),
    KR6: task('kred', 'KR6', 3, K2_KRED_MENU),
    KR7: task('kred', 'KR7', 3, K2_KRED_MENU),
    KR8: task('kred', 'KR8', 3),
    KR9: task('kred', 'KR9', 2, K2_KRED_ROW),
    // the column setup: the first Pos field (grid.php:1777)
    KR10: task('kred', 'KR10', 3, { target: k2only(['kolonner'], 'input[name^="rows[kreditor_list]"]'), label: showKey('kred', 'KR10'), kind: 'field' }),
    // Luk in either setup form (grid.php:2045, :1660)
    KR11: task('kred', 'KR11', 1, {
      target: k2only(['filtre', 'kolonner'], '#datatable-wrapper-kreditor_list button[onclick*="updateQueryParameter"]'),
      label: showKey('kred', 'KR11'),
      kind: 'button'
    }),
    // the filter setup's Lukkede checkbox (grid.php:2094; filterName Status, option name Lukkede, kreditor.php:362-376)
    KR12: task('kred', 'KR12', 2, {
      target: k2only(['filtre'], 'input[type="checkbox"][name="filter[kreditor_list][Status][Lukkede]"]'),
      label: showKey('kred', 'KR12'),
      kind: 'field'
    })
  };

  const K2_KRED_FIELDS = {};
  ['search:kontonr', 'search:firmanavn', 'search:adresse', 'search:postnr', 'search:bynavn', 'search:kontakt', 'search:tlf',
    'search:email', 'rowcount'].forEach(function (k) { K2_KRED_FIELDS[k] = fieldKeys('kred', k); });

  const k2Kreditorer = {
    group: 'kred',
    states: ['tom', 'liste', 'filtre', 'kolonner'],
    flags: ['soegning', 'lukkede'],

    name: function () {
      return 'help.kred.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = k2flags(k2Kreditorer.flags);
      if (!k2q(doc, '#datatable-wrapper-kreditor_list')) return { state: 'unknown', flags: flags, data: {} };
      if (k2q(doc, '#datatable-wrapper-kreditor_list #filter-wrapper')) return { state: 'filtre', flags: flags, data: {} };
      if (k2q(doc, '#datatable-wrapper-kreditor_list form[method="POST"]')) return { state: 'kolonner', flags: flags, data: {} };
      const rows = k2qa(doc, K2_KRED_ROWS);
      flags.soegning = k2searching(doc, 'kreditor_list');
      flags.lukkede = !!k2q(doc, '#datatable-kreditor_list tr[style*="opacity"]');
      return { state: rows.length ? 'liste' : 'tom', flags: flags, data: { antal: rows.length } };
    },

    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'tom') return [f.soegning ? 'help.kred.about.soegning_tom' : 'help.kred.about.tom'];
      if (s === 'filtre') return ['help.kred.about.filtre'];
      if (s === 'kolonner') return ['help.kred.about.kolonner'];
      if (s !== 'liste') return [];
      const out = ['help.kred.about.liste'];
      if (f.soegning) out.push('help.kred.about.soegning');
      else if (f.lukkede) out.push('help.kred.about.lukkede');
      return out;
    },

    tasks: K2_KRED_TASKS,

    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (s === 'filtre') return ['KR12', 'KR11'];
      if (s === 'kolonner') return ['KR10', 'KR11'];
      if (f.soegning) ids.push('KR5');
      if (s === 'tom') ids.push('KR1', 'KR8', 'KR4');
      else if (s === 'liste') ids.push('KR2', 'KR1', 'KR4', 'KR3', 'KR9', 'KR6', 'KR7', 'KR8');
      return unique(ids);
    },

    fields: K2_KRED_FIELDS,

    // search[kreditor_list][addr1] ends in a digit inside the brackets; the default rule would give 'search:addr1'.
    fieldKey: function (el) {
      return /^search\[kreditor_list\]\[addr1\]$/.test(attr(el, 'name')) ? 'search:adresse' : null;
    },

    // The search fields sit in a GET form, so Enter searches (grid.php:1265, :1396). The shell layout's head has no access keys.
    shortcuts: [
      { keys: 'Enter', text: 'help.kred.key.01', group: 'keys' }
    ],

    questions: {
      Q1: {
        q: 'help.kred.q.q1.q',
        words: {
          da: ['opret', 'oprette', 'ny leverandør', 'ny kreditor', 'tilføj', 'leverandør', 'kreditor'],
          en: ['create', 'new supplier', 'new creditor', 'add', 'supplier', 'vendor', 'creditor'],
          no: ['opprett', 'opprette', 'ny leverandør', 'ny kreditor', 'legg til', 'leverandør', 'kreditor']
        },
        steps: stepKeys('kred', 'KR1', 1, 4),
        show: K2_KRED_TASKS.KR1.show
      },
      Q2: {
        q: 'help.kred.q.q2.q',
        words: {
          da: ['slet', 'slette', 'fjern', 'fjerne', 'luk', 'lukke', 'lukket'],
          en: ['delete', 'remove', 'close', 'closed'],
          no: ['slett', 'slette', 'fjern', 'fjerne', 'lukk', 'lukke', 'lukket']
        },
        intro: 'help.kred.q.q2.intro',
        steps: ['help.kred.task.kr3.step1', 'help.kred.q.q2.step2', 'help.kred.q.q2.step3'],
        show: K2_KRED_ROW
      },
      Q3: {
        q: 'help.kred.q.q3.q',
        words: {
          da: ['skylder', 'gæld', 'ubetalt', 'ubetalte', 'åbne poster', 'saldo', 'regninger'],
          en: ['owe', 'debt', 'unpaid', 'open items', 'balance', 'bills'],
          no: ['skylder', 'gjeld', 'ubetalt', 'ubetalte', 'åpne poster', 'saldo', 'regninger']
        },
        steps: stepKeys('kred', 'KR4', 1, 2)
      }
    },

    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'tom') return ['Q1', 'Q3'];
      return ['Q3', 'Q1', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['kreditor/kreditor.php'] = k2Kreditorer;

  // ---- Kreditorkort (kreditor/kreditorkort.php)
  // Reached from a row of Kreditorer (kreditor.php:60, :397-418) and Ny (kreditor.php:135), from Åbne poster's Firmanavn
  // (reportFunc/showOpenPosts.php:1231), from a purchase order's Ny (kreditorOrderFuncIncludes/topLine_S.php:96-105,
  // returside ../kreditor/ordre.php and ordre_id) and from an account link in the cash journal
  // (finans/kassekladde_includes/accountCard.php:38-46). Evidence, kreditorkort.php unless named: the head with Tilbage
  // (a[accesskey=L] with data-back) and Ny (a[accesskey='N']) (:268-281; classic head :306-308); the form kreditorkort
  // with hidden id, kontonr, ordre_id, returside (:374-379; the parser closes the form at once, so its fields are read by
  // name, :526-530); the fields :383-500 (Leverandørnr. ny_kontonr :383, Betalingsbetingelse :401, Kreditorgruppe :422,
  // CVR-nr. :433, Reg. nr. :441, ERH kode :494); Bekræft for unconfirmed bank details #kredBankUnconfirmed (:446-459);
  // Lukket :468; the line of an automatic creation, an <i> in a td colspan=2 (:469-478); Kontaktpersoner with Ny
  // (a[href^="ansatte.php?ordre_id="], :523) and the rows in tbody#kontaktpersoner (:531-544); Gem/opdatér accesskey g,
  // and Slet accesskey s only without open posts, orders and contacts (:549-570); the footer .cred-footer-bar with
  // Kontokort and, with right 5, Fakturaliste (:581-590). The CVR lookup (javascript/cvrapiopslag.js:269-334): a CVR-nr.
  // wrapped in *, + or / in Leverandørnr. or CVR-nr.; F2 (:66-73). A leading = in Leverandørnr. sends the save to
  // kontofusion.php (:140-143); a blank number is assigned (:159-164).

  function k2KkortField(ctx, name) {
    return byName(ctx.doc, name);
  }

  /** Tilbage, only when it leads where the task's title says (spec §19.3): back to the purchase order (KO12, flag fraOrdre)
   *  or to the cash journal (KO13, flag fraKladde); otherwise Tilbage goes to the supplier list. Without a state the flags
   *  are detected from the page. */
  function k2KkortBack(flag) {
    return {
      target: function (ctx, st) {
        let f = st && st.flags ? st.flags : null;
        if (!f) {
          try {
            f = k2Kreditorkort.detect(ctx).flags;
          } catch (e) {
            f = {};
          }
        }
        return f && f[flag] ? k2btn(k2q(ctx.doc, 'a[accesskey="L"][data-back]')) : null;
      },
      label: showKey('kkort', 'KO12'),
      kind: 'button'
    };
  }

  const K2_KKORT_TASKS = {
    KO1: task('kkort', 'KO1', 3, { target: 'input[name="cvrnr"]', label: fieldLabel('kkort', 'cvrnr'), kind: 'field' }),
    KO2: task('kkort', 'KO2', 3, { target: 'input[type="submit"][accesskey="g"]', label: showKey('kkort', 'KO2'), kind: 'button' }),
    KO3: task('kkort', 'KO3', 3, { target: 'select[name="betalingsbet"]', label: fieldLabel('kkort', 'betalingsbet'), kind: 'field' }),
    KO4: task('kkort', 'KO4', 4, { target: 'input[name="bank_reg"]', label: fieldLabel('kkort', 'bank_reg'), kind: 'field' }),
    KO5: task('kkort', 'KO5', 3, { target: '#kredBankUnconfirmed button', label: showKey('kkort', 'KO5'), kind: 'button' }),
    // A new card prints Kontokort and Fakturaliste too, without a supplier behind them (live check), so only on a saved card
    KO6: task('kkort', 'KO6', 2, { target: k2only(['kort'], function (ctx) { return k2btn(k2q(ctx.doc, 'a[href*="rapportart=kontokort"]')); }), label: showKey('kkort', 'KO6'), kind: 'button' }),
    KO7: task('kkort', 'KO7', 2, { target: k2only(['kort'], function (ctx) { return k2btn(k2q(ctx.doc, 'a[href*="valg=faktura"]')); }), label: showKey('kkort', 'KO7'), kind: 'button' }),
    KO8: task('kkort', 'KO8', 3, { target: function (ctx) { return k2btn(k2q(ctx.doc, 'a[href^="ansatte.php?ordre_id="]')); }, label: showKey('kkort', 'KO8'), kind: 'button' }),
    KO9: task('kkort', 'KO9', 2, { target: 'input[name="lukket"]', label: fieldLabel('kkort', 'lukket'), kind: 'field' }),
    // Nothing to delete or merge on a new card (the merge needs the card's id, kreditorkort.php:140-143)
    KO10: task('kkort', 'KO10', 2, { target: k2only(['kort'], 'input[type="submit"][accesskey="s"]'), label: showKey('kkort', 'KO10'), kind: 'button' }),
    KO11: task('kkort', 'KO11', 3, { target: k2only(['kort'], 'input[name="ny_kontonr"]'), label: fieldLabel('kkort', 'ny_kontonr'), kind: 'field' }),
    KO12: task('kkort', 'KO12', 2, k2KkortBack('fraOrdre')),
    KO13: task('kkort', 'KO13', 0, k2KkortBack('fraKladde'), stepKeys('kkort', 'KO12', 1, 2)),
    KO14: task('kkort', 'KO14', 2, { target: 'select[name="gruppe"]', label: fieldLabel('kkort', 'gruppe'), kind: 'field' }),
    KO15: task('kkort', 'KO15', 1, { target: 'input[name="lukket"]', label: fieldLabel('kkort', 'lukket'), kind: 'field' })
  };

  const K2_KKORT_FIELDS = {};
  ['ny_kontonr', 'firmanavn', 'addr', 'land', 'email', 'web', 'betalingsbet', 'betalingsdage', 'gruppe', 'cvrnr', 'tlf',
    'mobile', 'bank_navn', 'bank_reg', 'bank_konto', 'swift', 'bank_fi', 'kreditmax', 'lukket', 'felt', 'erh', 'notes', 'posnr']
    .forEach(function (k) { K2_KKORT_FIELDS[k] = fieldKeys('kkort', k); });
  // Postnr. and By share one row and one label, Postnr./By (kreditorkort.php:384-386)
  K2_KKORT_FIELDS.postnr = fieldKeys('kkort', 'postnr');
  K2_KKORT_FIELDS.bynavn = fieldKeys('kkort', 'postnr');

  const k2Kreditorkort = {
    group: 'kkort',
    states: ['ny', 'kort'],
    flags: ['ubekraeftet', 'autoOprettet', 'lukket', 'kanSlettes', 'manglerBank', 'fraOrdre', 'fraKladde', 'harKontakter'],

    name: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const nr = String((st && st.data && st.data.kontonr) || '');
      if (s === 'ny') return 'help.kkort.name_new';
      if (s === 'kort' && nr) return ['help.kkort.name', nr];
      return 'help.kkort.name_plain';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = k2flags(k2Kreditorkort.flags);
      const nr = k2KkortField(ctx, 'ny_kontonr');
      if (!nr) return { state: 'unknown', flags: flags, data: {} };
      const id = k2val(k2KkortField(ctx, 'id'));
      const lukket = k2KkortField(ctx, 'lukket');
      const returside = k2param(ctx, 'returside');
      flags.ubekraeftet = !!k2q(doc, '#kredBankUnconfirmed');
      flags.autoOprettet = !!k2q(doc, 'td[colspan="2"] > i');
      flags.lukket = !!lukket && (lukket.defaultChecked === true || lukket.getAttribute('checked') !== null);
      flags.kanSlettes = !!k2q(doc, 'input[type="submit"][accesskey="s"]');
      flags.manglerBank = k2val(k2KkortField(ctx, 'bank_konto')) === '' && k2val(k2KkortField(ctx, 'bank_fi')) === '';
      flags.fraOrdre = /ordre\.php/.test(returside);
      flags.fraKladde = /kassekladde\.php/.test(returside);
      flags.harKontakter = !!k2q(doc, '#kontaktpersoner tr');
      return { state: id === '' || id === '0' ? 'ny' : 'kort', flags: flags, data: { kontonr: k2val(nr) } };
    },

    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s !== 'ny' && s !== 'kort') return [];
      const out = [s === 'ny' ? 'help.kkort.about.ny' : 'help.kkort.about.kort'];
      if (f.ubekraeftet) out.push('help.kkort.about.ubekraeftet');
      else if (f.fraOrdre) out.push('help.kkort.about.fra_ordre');
      else if (f.fraKladde) out.push('help.kkort.about.fra_kladde');
      else if (s === 'kort' && f.lukket) out.push('help.kkort.about.lukket');
      else if (s === 'kort' && f.autoOprettet) out.push('help.kkort.about.auto');
      return out;
    },

    tasks: K2_KKORT_TASKS,

    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (f.ubekraeftet) ids.push('KO5');
      if (s === 'ny') {
        ids.push('KO1', 'KO2', 'KO3', 'KO14', 'KO4');
        if (f.fraOrdre) ids.push('KO12');
        else if (f.fraKladde) ids.push('KO13');
      } else if (s === 'kort') {
        if (f.fraOrdre) ids.push('KO12');
        else if (f.fraKladde) ids.push('KO13');
        if (f.lukket) ids.push('KO15');
        if (f.autoOprettet) ids.push('KO14', 'KO3');
        ids.push('KO6');
        if (f.manglerBank) ids.push('KO4');
        ids.push('KO3', 'KO4', 'KO14', 'KO8', 'KO7');
        if (!f.lukket) ids.push('KO9');
        if (f.kanSlettes) ids.push('KO10');
        ids.push('KO11', 'KO1');
      }
      return unique(ids);
    },

    problem: function (ctx, st) {
      const f = (st && st.flags) || {};
      return f.ubekraeftet ? k2firstTask(k2Kreditorkort, ctx, st) : '';
    },

    fields: K2_KKORT_FIELDS,

    // posnr[1], posnr[2] … are the contact persons' positions (kreditorkort.php:537).
    fieldKey: function (el) {
      return /^posnr\[\d+\]$/.test(attr(el, 'name')) ? 'posnr' : null;
    },

    shortcuts: [
      { keys: 'Alt+G', text: 'help.kkort.key.01', group: 'keys' },
      { keys: 'Alt+N', text: 'help.kkort.key.02', group: 'keys' },
      { keys: 'Alt+S', text: 'help.kkort.key.03', group: 'keys' },
      { keys: 'Alt+L', text: 'help.kkort.key.04', group: 'keys' },
      { keys: 'F2', text: 'help.kkort.key.05', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.kkort.q.q1.q',
        words: {
          da: ['slet', 'slette', 'fjern', 'fjerne', 'kan ikke slette', 'luk', 'lukke'],
          en: ['delete', 'remove', 'cannot delete', 'close'],
          no: ['slett', 'slette', 'fjern', 'fjerne', 'kan ikke slette', 'lukk', 'lukke']
        },
        intro: 'help.kkort.q.q1.intro',
        steps: ['help.kkort.q.q1.step1', 'help.kkort.q.q1.step2', 'help.kkort.task.ko10.step2'],
        show: K2_KKORT_TASKS.KO9.show
      },
      Q2: {
        q: 'help.kkort.q.q2.q',
        words: {
          da: ['kreditorgruppe', 'gruppe', 'samlekonto', 'købsmoms', 'valuta', 'omvendt betalingspligt', 'udland', 'eu'],
          en: ['creditor group', 'group', 'control account', 'purchase vat', 'currency', 'reverse charge', 'abroad', 'eu'],
          no: ['kreditorgruppe', 'gruppe', 'samlekonto', 'inngående mva', 'valuta', 'omvendt avgiftsplikt', 'utland', 'eu']
        },
        intro: 'help.kkort.q.q2.intro',
        steps: ['help.kkort.q.q2.step1', 'help.kkort.q.q2.step2', 'help.kkort.q.q2.step3'],
        show: K2_KKORT_TASKS.KO14.show
      },
      Q3: {
        q: 'help.kkort.q.q3.q',
        words: {
          da: ['cvr', 'cvr-nummer', 'cvr-registeret', 'hent', 'slå op', 'opslag', 'adresse'],
          en: ['cvr', 'company register', 'look up', 'lookup', 'fetch', 'address'],
          no: ['cvr', 'cvr-nummer', 'hent', 'slå opp', 'oppslag', 'adresse']
        },
        steps: stepKeys('kkort', 'KO1', 1, 3),
        show: K2_KKORT_TASKS.KO1.show
      },
      // A blank Leverandørnr. gets the first free number from 1000 and an alert with it (kreditorkort.php:162-166,
      // includes/kreditorCreate.php:35-44); a character that is no digit gives an alert and is read as 0 (:146-158); a
      // number that exists already is refused, and the card is not made (:173-180)
      Q4: {
        q: 'help.kkort.q.q4.q',
        words: {
          da: ['leverandørnummer', 'leverandørnr', 'kontonummer', 'nummer', 'tomt', 'automatisk', 'næste nummer'],
          en: ['supplier number', 'supplier no', 'account number', 'number', 'empty', 'automatic', 'next number'],
          no: ['leverandørnummer', 'leverandørnr', 'kontonummer', 'nummer', 'tomt', 'automatisk', 'neste nummer']
        },
        steps: ['help.kkort.q.q4.step1', 'help.kkort.q.q4.step2'],
        show: { target: k2only(['ny'], 'input[name="ny_kontonr"]'), label: fieldLabel('kkort', 'ny_kontonr'), kind: 'field' }
      }
    },

    // A new card: the CVR lookup and the number it gets, then the group; deleting is for a saved card only
    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'ny') return ['Q3', 'Q4', 'Q2'];
      return ['Q1', 'Q2', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['kreditor/kreditorkort.php'] = k2Kreditorkort;

  // ---- Kreditorrapporter (kreditor/rapport.php)
  // Sidebar Kreditorer » Rapporter (index/main.php:330-332). One route for the selection form and every report: a GET
  // without rapportart, or a POST, prints the form forside() (kreditor/rapport.php:253-261, includes/rapportfunc.php:816-958)
  // with the fields dato, konto, husk and the submit buttons openpost, kontosaldo, kontokort, plus Top 100 and, when
  // Brug betalingslister is Begge or Kreditorer, Betalingslister (reportFunc/frontPageOldMenu.php:52-67, :110-133). The
  // reports are told apart by their markup, because a POST from the form carries no rapportart in the URL:
  // - Åbne poster, openpost(): Tilbage a[accesskey=l], the select aabenpostmode with one option per other mode
  //   (rapportfunc.php:300-327), #visAabnePosterTable with the account search kontonr, Eksporter CSV, the aging links
  //   a.op-filter and the rows: Kontonr. to the account card (rapportart=accountChart), Firmanavn a.op-name to the card, a
  //   total that links udlign=<id> when the account's open posts net to 0 (showOpenPosts.php:785-1010, :1200-1260); amounts
  //   of a creditor are shown as booked, a debt in credit with a minus, red when overdue (:419-431);
  // - Kontosaldo, kontosaldo(): #ksHeaderTitleTable and #ksGridTable, rows with a balance other than 0 and the total
  //   (rapportfunc.php:2110-2300);
  // - Kontokort, kontokort(): #kkGridTable with tr.kk-account-row per supplier and tr.kk-data-row, open amounts linking
  //   ../includes/udlign_openpost.php, settled ones linking unAlign=, Udskriv (showLangModalKontoprint), Tilbage
  //   a[accesskey=L] (rapportfunc.php:1240-1290, :1716-1760, :1985-2080);
  // - the supplier's Kontokort from the card (rapportart=kontokort&layout=grid, one account): kreditor/generalLedger.php,
  //   .creditor-ledger-page, open links a.creditor-ledger-open-link, settled a.creditor-ledger-balanced-link, the currency
  //   difference link ../includes/ret_valutadiff.php, Luk, Udskriv and E-mail (generalLedgerTopLine.php:12-28,
  //   generalLedger.php:345-470);
  // - the account card from Åbne poster (rapportart=accountChart): reportFunc/accountChart.php, the select typeSelect
  //   (Vis åbne poster / Vis alle poster), Tilbage, Udskriv and, for one account, E-mail (accountChart.php:330-410).

  const K2_RAP_STATES = ['forside', 'aabnePoster', 'kontosaldo', 'kontokort', 'kontokortLev', 'kontoudtog'];
  const K2_RAP_KORT = ['kontokort', 'kontokortLev', 'kontoudtog'];
  const K2_RAP_MODES = ['vis_aabenpost', 'vis_alle_poster', 'kun_debet', 'kun_kredit', 'skjul_aabenpost'];

  /** Tilbage (or Luk) of a report: the shell layout prints <a accesskey=L><button> (rapportfunc.php:313, :1243, :2193,
   *  generalLedgerTopLine.php:13, accountChart.php:330, :348). */
  function k2RapBack(ctx) {
    return k2btn(k2first(ctx.doc, ['a[accesskey="L"]', 'a[accesskey="l"]']));
  }

  /** The current mode of Åbne poster: the select lists every other mode as an option URL (rapportfunc.php:317-327). */
  function k2RapMode(doc) {
    const sel = k2q(doc, 'select[name="aabenpostmode"]');
    if (!sel) return '';
    const values = k2qa(sel, 'option').map(function (o) { return attr(o, 'value'); }).join(' ');
    const missing = K2_RAP_MODES.filter(function (m) { return values.indexOf('&' + m + '=on') === -1; });
    return missing.length === 1 ? missing[0] : 'vis_aabenpost';
  }

  function k2RapPrint(ctx) {
    return k2first(ctx.doc, ['button[onclick*="showLangModalKontoprint"]', 'td[onclick*="kontoprint.php"] button']);
  }

  function k2RapMail(ctx) {
    return k2first(ctx.doc, ['button[onclick*="mail_kontoudtog.php"]', 'td[onclick*="mail_kontoudtog.php"] button']);
  }

  function k2RapOpen(ctx) {
    return k2first(ctx.doc, ['a.creditor-ledger-open-link[href*="udlign_openpost.php"]', 'a[href*="udlign_openpost.php"]']);
  }

  function k2RapSettled(ctx) {
    return k2first(ctx.doc, ['a.creditor-ledger-balanced-link', 'a[href*="unAlign="]']);
  }

  function k2RapVoucher(ctx) {
    return k2first(ctx.doc, ['a[href*="kassekladde.php?kladde_id="]', 'a[onclick*="kassekladde.php?kladde_id="]']);
  }

  /** The first aging column link of Åbne poster (showOpenPosts.php:886-889). */
  function k2RapAging(ctx) {
    return k2q(ctx.doc, '#visAabnePosterTable a.op-filter[href*="aging_bucket="]') || k2q(ctx.doc, '#visAabnePosterTable .op-group');
  }

  const K2_RAP_BACK = { target: k2RapBack, label: showKey('krap', 'KR8'), kind: 'button' };

  const K2_RAP_TASKS = {
    // The selection form (frontPageOldMenu.php:52-67)
    RP1: task('krap', 'RP1', 3, { target: k2only(['forside'], 'input[name="openpost"]'), label: showKey('krap', 'RP1'), kind: 'button' }),
    RP2: task('krap', 'RP2', 3, { target: k2only(['forside'], 'input[name="kontosaldo"]'), label: showKey('krap', 'RP2'), kind: 'button' }),
    RP3: task('krap', 'RP3', 3, { target: k2only(['forside'], 'input[name="kontokort"]'), label: showKey('krap', 'RP3'), kind: 'button' }),
    RP4: task('krap', 'RP4', 3, { target: k2only(['forside'], 'input[name="konto"]'), label: fieldLabel('krap', 'konto'), kind: 'field' }),
    RP5: task('krap', 'RP5', 2, { target: k2only(['forside'], 'input[name="husk"]'), label: fieldLabel('krap', 'husk'), kind: 'field' }),
    RP6: task('krap', 'RP6', 2, { target: k2only(['forside'], 'span[onclick*="top100.php"] input'), label: showKey('krap', 'RP6'), kind: 'button' }),
    RP7: task('krap', 'RP7', 3, { target: k2only(['forside'], 'span[onclick*="betalingsliste.php"] input'), label: showKey('krap', 'RP7'), kind: 'button' }),
    RP8: task('krap', 'RP8', 3),
    // Åbne poster
    AP1: task('krap', 'AP1', 3, { target: k2only(['aabnePoster'], k2RapAging), label: showKey('krap', 'AP1'), kind: 'status' }),
    AP2: task('krap', 'AP2', 3, { target: k2only(['aabnePoster'], '#visAabnePosterTable a[href*="rapportart=accountChart"]'), label: showKey('krap', 'AP2'), kind: 'button' }),
    AP3: task('krap', 'AP3', 3, { target: k2only(['aabnePoster'], '#visAabnePosterTable a[href*="udlign="]'), label: showKey('krap', 'AP3'), kind: 'button' }),
    AP4: task('krap', 'AP4', 3),
    AP5: task('krap', 'AP5', 2, { target: k2only(['aabnePoster'], 'select[name="aabenpostmode"]'), label: fieldLabel('krap', 'aabenpostmode'), kind: 'field' }),
    AP6: task('krap', 'AP6', 2, { target: k2only(['aabnePoster'], 'input[name="kontonr"]'), label: fieldLabel('krap', 'kontonr'), kind: 'field' }),
    AP7: task('krap', 'AP7', 2, { target: k2only(['aabnePoster'], 'input[type="button"][onclick*="openpost_csv"]'), label: showKey('krap', 'AP7'), kind: 'button' }),
    AP8: task('krap', 'AP8', 1, { target: k2only(['aabnePoster'], '.op-summary a.op-link'), label: showKey('krap', 'AP8'), kind: 'button' }),
    AP9: task('krap', 'AP9', 2, { target: k2only(['aabnePoster'], '#visAabnePosterTable a.op-name'), label: showKey('krap', 'AP9'), kind: 'button' }),
    APS: task('krap', 'APS', 1, { target: k2only(['aabnePoster'], 'select[name="aabenpostmode"]'), label: fieldLabel('krap', 'aabenpostmode'), kind: 'field' }),
    APT: task('krap', 'APT', 3, { target: k2only(['aabnePoster', 'kontosaldo', 'kontokort'], k2RapBack), label: showKey('krap', 'KR8'), kind: 'button' }),
    // Kontosaldo: the total row (rapportfunc.php:2290)
    KS1: task('krap', 'KS1', 3, { target: k2only(['kontosaldo'], '#ksGridTable tfoot tr'), label: showKey('krap', 'KS1'), kind: 'row' }),
    KS2: task('krap', 'KS2', 3, { target: k2only(['kontosaldo'], k2RapBack), label: showKey('krap', 'KR8'), kind: 'button' }),
    // The ledger views
    KK1: task('krap', 'KK1', 3, { target: k2only(K2_RAP_KORT, k2RapOpen), label: showKey('krap', 'KK1'), kind: 'button' }),
    KK2: task('krap', 'KK2', 2, { target: k2only(K2_RAP_KORT, k2RapSettled), label: showKey('krap', 'KK2'), kind: 'button' }),
    KK3: task('krap', 'KK3', 2, { target: k2only(K2_RAP_KORT, k2RapPrint), label: showKey('krap', 'KK3'), kind: 'button' }),
    KK4: task('krap', 'KK4', 2, { target: k2only(['kontokortLev', 'kontoudtog'], k2RapMail), label: showKey('krap', 'KK4'), kind: 'button' }),
    KK5: task('krap', 'KK5', 2, { target: k2only(K2_RAP_KORT, k2RapVoucher), label: showKey('krap', 'KK5'), kind: 'button' }),
    KK6: task('krap', 'KK6', 2, { target: k2only(K2_RAP_KORT, 'a[href*="ret_valutadiff.php"]'), label: showKey('krap', 'KK6'), kind: 'button' }),
    KK7: task('krap', 'KK7', 2, { target: k2only(['kontoudtog'], 'select[name="typeSelect"]'), label: fieldLabel('krap', 'typeselect'), kind: 'field' }),
    KR8: task('krap', 'KR8', 1, K2_RAP_BACK)
  };

  const K2_RAP_FIELDS = {
    dato: fieldKeys('krap', 'dato'),
    konto: fieldKeys('krap', 'konto'),
    husk: fieldKeys('krap', 'husk'),
    kontonr: fieldKeys('krap', 'kontonr'),
    aabenpostmode: fieldKeys('krap', 'aabenpostmode'),
    typeselect: fieldKeys('krap', 'typeselect')
  };

  const k2Rapport = {
    group: 'krap',
    states: K2_RAP_STATES,
    flags: ['husket', 'betalingslister', 'tom', 'skjult', 'kunDebet', 'kunKredit', 'alle', 'filter', 'udlign', 'soegning',
      'aabne', 'udlignede', 'valutadiff', 'flere'],

    name: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'aabnePoster') return 'help.krap.name_aabne';
      if (s === 'kontosaldo') return 'help.krap.name_kontosaldo';
      if (K2_RAP_KORT.indexOf(s) !== -1) return 'help.krap.name_kontokort';
      return 'help.krap.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = k2flags(k2Rapport.flags);
      let state = 'unknown';
      if (k2q(doc, 'input[name="openpost"]') && k2q(doc, 'input[name="dato"]')) {
        state = 'forside';
        const husk = k2q(doc, 'input[name="husk"]');
        flags.husket = !!husk && (husk.defaultChecked === true || husk.getAttribute('checked') !== null);
        flags.betalingslister = !!k2q(doc, 'span[onclick*="betalingsliste.php"]');
      } else if (k2q(doc, 'select[name="aabenpostmode"]')) {
        state = 'aabnePoster';
        const mode = k2RapMode(doc);
        flags.skjult = mode === 'skjul_aabenpost';
        flags.kunDebet = mode === 'kun_debet';
        flags.kunKredit = mode === 'kun_kredit';
        flags.alle = mode === 'vis_alle_poster';
        flags.tom = !flags.skjult && !k2q(doc, '#visAabnePosterTable a.op-name');
        flags.filter = k2qa(doc, 'input[name="aging_bucket"]').some(function (el) { return k2val(el) !== ''; });
        flags.udlign = !!k2q(doc, '#visAabnePosterTable a[href*="udlign="]');
        const search = k2val(k2q(doc, 'input[name="kontonr"]'));
        flags.soegning = search !== '' && search !== '*';
      } else if (k2q(doc, '#ksGridTable') || k2q(doc, '#ksHeaderTitleTable')) {
        state = 'kontosaldo';
        flags.tom = !k2q(doc, '#ksGridTable tr[bgcolor]');
      } else if (k2q(doc, '.creditor-ledger-page')) {
        state = 'kontokortLev';
        flags.tom = !!k2q(doc, '.creditor-ledger-empty-row');
      } else if (k2q(doc, 'select[name="typeSelect"]') || k2param(ctx, 'rapportart') === 'accountChart') {
        state = 'kontoudtog';
        flags.alle = !k2q(doc, 'select[name="typeSelect"] option[value*="kilde=show_all"]');
      } else if (k2q(doc, '#kkGridTable')) {
        state = 'kontokort';
        flags.tom = !k2q(doc, '#kkGridTable tr.kk-data-row');
        flags.flere = k2qa(doc, '#kkGridTable tr.kk-account-row').length > 1;
      }
      if (K2_RAP_KORT.indexOf(state) !== -1) {
        flags.aabne = !!k2RapOpen(ctx);
        flags.udlignede = !!k2RapSettled(ctx);
        flags.valutadiff = !!k2q(doc, 'a[href*="ret_valutadiff.php"]');
      }
      return { state: state, flags: flags, data: {} };
    },

    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const out = [];
      if (s === 'forside') {
        out.push('help.krap.about.forside');
        if (f.husket) out.push('help.krap.about.husket');
      } else if (s === 'aabnePoster') {
        if (f.skjult) return ['help.krap.about.skjult'];
        out.push('help.krap.about.aabne');
        if (f.tom) out.push('help.krap.about.aabne_tom');
        else if (f.filter) out.push('help.krap.about.filter');
        else if (f.kunDebet) out.push('help.krap.about.kun_debet');
        else if (f.alle) out.push('help.krap.about.alle');
        else out.push('help.krap.about.fortegn');
      } else if (s === 'kontosaldo') {
        out.push('help.krap.about.kontosaldo');
        out.push(f.tom ? 'help.krap.about.kontosaldo_tom' : 'help.krap.about.fortegn_saldo');
      } else if (K2_RAP_KORT.indexOf(s) !== -1) {
        out.push(s === 'kontoudtog' ? 'help.krap.about.kontoudtog' : 'help.krap.about.kontokort');
        if (f.tom) out.push('help.krap.about.kontokort_tom');
        else if (f.aabne || f.udlignede) out.push('help.krap.about.udlign');
      }
      return out;
    },

    tasks: K2_RAP_TASKS,

    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (s === 'forside') {
        ids.push('RP1', 'RP3', 'RP2', 'RP4');
        ids.push(f.betalingslister ? 'RP7' : 'RP8');
        ids.push('RP6', 'RP5');
      } else if (s === 'aabnePoster') {
        if (f.skjult) return ['APS', 'KR8'];
        if (f.tom) ids.push('APT', 'AP5');
        if (f.filter) ids.push('AP8');
        if (f.udlign) ids.push('AP3');
        ids.push('AP1', 'AP2', 'AP4', 'AP9', 'AP5', 'AP6', 'AP7', 'KR8');
      } else if (s === 'kontosaldo') {
        if (f.tom) ids.push('APT');
        ids.push('KS1', 'KS2', 'KR8');
      } else if (K2_RAP_KORT.indexOf(s) !== -1) {
        if (f.tom) ids.push(s === 'kontokort' ? 'APT' : 'KR8');
        if (s === 'kontoudtog') ids.push('KK7');
        // Open items: settle them, and post the next payments so they settle by themselves.
        if (f.aabne) ids.push('KK1', 'AP4');
        ids.push('KK3');
        if (s !== 'kontokort') ids.push('KK4');
        ids.push('KK5');
        if (f.udlignede) ids.push('KK2');
        if (f.valutadiff) ids.push('KK6');
        ids.push('AP4', 'KR8');
      }
      return unique(ids);
    },

    fields: K2_RAP_FIELDS,

    // typeSelect has an upper-case letter, which a field key may not have (accountChart.php:355).
    fieldKey: function (el) {
      return attr(el, 'name') === 'typeSelect' ? 'typeselect' : null;
    },

    // Every report's Tilbage (or Luk) is an accesskey L link; the selection form has none in the shell layout.
    shortcuts: [
      { keys: 'Alt+L', text: 'help.krap.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.krap.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.krap.q.q1.q',
        words: {
          da: ['minus', 'negativ', 'negativt', 'fortegn', 'kredit', 'debet', 'rød', 'røde'],
          en: ['minus', 'negative', 'sign', 'credit', 'debit', 'red'],
          no: ['minus', 'negativ', 'negativt', 'fortegn', 'kreditt', 'debet', 'rød', 'røde']
        },
        intro: 'help.krap.q.q1.intro',
        steps: ['help.krap.q.q1.step1', 'help.krap.q.q1.step2', 'help.krap.q.q1.step3']
      },
      Q2: {
        q: 'help.krap.q.q2.q',
        words: {
          da: ['betalt', 'stadig åben', 'åben', 'udlign', 'udligne', 'udligning', 'betaling'],
          en: ['paid', 'still open', 'open', 'settle', 'settlement', 'payment', 'match'],
          no: ['betalt', 'fortsatt åpen', 'åpen', 'utlign', 'utligne', 'utligning', 'betaling']
        },
        steps: stepKeys('krap', 'AP4', 1, 3)
      },
      Q3: {
        q: 'help.krap.q.q3.q',
        words: {
          da: ['mangler', 'kan ikke se', 'ikke med', 'faktura', 'regning', 'ubogført'],
          en: ['missing', 'not shown', 'not included', 'invoice', 'bill', 'unposted'],
          no: ['mangler', 'kan ikke se', 'ikke med', 'faktura', 'regning', 'ikke bokført']
        },
        intro: 'help.krap.q.q3.intro',
        steps: ['help.krap.q.q3.step1', 'help.krap.q.q3.step2', 'help.krap.q.q3.step3']
      },
      Q4: {
        q: 'help.krap.q.q4.q',
        words: {
          da: ['afstem', 'afstemme', 'afstemning', 'samlekonto', 'kreditorer', 'balance', 'saldo'],
          en: ['reconcile', 'reconciliation', 'control account', 'creditors', 'payables', 'balance'],
          no: ['avstem', 'avstemme', 'avstemming', 'samlekonto', 'kreditorer', 'balanse', 'saldo']
        },
        steps: stepKeys('krap', 'RP2', 1, 3),
        show: K2_RAP_TASKS.RP2.show
      }
    },

    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'aabnePoster') return ['Q1', 'Q2', 'Q3'];
      if (s === 'kontosaldo') return ['Q4', 'Q1', 'Q3'];
      if (K2_RAP_KORT.indexOf(s) !== -1) return ['Q2', 'Q1', 'Q3'];
      // The form: the paid bill that stays open is asked most, as on the Debitor reports
      return ['Q2', 'Q3', 'Q4'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['kreditor/rapport.php'] = k2Rapport;

  // ---- Betalingslister (kreditor/betalingsliste.php)
  // Reached with Betalingslister on the Kreditorrapporter form, shown when Brug betalingslister is Begge or Kreditorer
  // (reportFunc/frontPageOldMenu.php:125-130; systemdata/settingsDefinitions.php:901-904), and with Luk on a list. In the
  // shell layout the head is the classic one with hard-coded Danish texts: Luk (accesskey L, rapport.php), Indstillinger,
  // Ny (accesskey N, betalinger.php?id=0), and Vis alle / Vis egne (betalingsliste.php:86-102). Open lists link
  // betalinger.php?tjek=<id> (:147-149), lists sent to the bank (bogfort V) betalinger.php?liste_id=<id> with the date
  // under Lukket (:200-214); every row has a delete icon that asks first, span[onclick*=deleteList] (:158-160, :230-232,
  // the question :48-64). Indstillinger links betalinger_settings.php, which only exists in debitor/: no task names it.

  const K2_BL_TASKS = {
    BL1: task('kbet', 'BL1', 3, { target: 'a[href="betalinger.php?id=0"]', label: showKey('kbet', 'BL1'), kind: 'button' }),
    BL2: task('kbet', 'BL2', 2, { target: 'a[href^="betalinger.php?tjek="]', label: showKey('kbet', 'BL2'), kind: 'button' }),
    BL3: task('kbet', 'BL3', 1, { target: 'a[href^="betalinger.php?liste_id="]', label: showKey('kbet', 'BL2'), kind: 'button' },
      stepKeys('kbet', 'BT8', 2, 3)),
    BL4: task('kbet', 'BL4', 2, { target: 'span[onclick*="deleteList="]', label: showKey('kbet', 'BL4'), kind: 'button' }),
    BL5: task('kbet', 'BL5', 1, { target: 'a[href*="vis=alle"]', label: showKey('kbet', 'BL5'), kind: 'button' }),
    BL6: task('kbet', 'BL6', 2, { target: 'span[onclick*="confirmDelete=yes"] button', label: showKey('kbet', 'BL6'), kind: 'button' })
  };

  /** The question both payment pages share: a payment list posts nothing. */
  const K2_BET_QB1 = {
    q: 'help.kbet.q.qb1.q',
    words: {
      da: ['bogført', 'bogføre', 'bogfør', 'udlign', 'udligne', 'kassekladde', 'betalt'],
      en: ['posted', 'post', 'settle', 'journal', 'cash journal', 'paid'],
      no: ['bokført', 'bokføre', 'bokfør', 'utlign', 'utligne', 'kassekladd', 'betalt']
    },
    intro: 'help.kbet.q.qb1.intro',
    steps: stepKeys('kbet', 'BT7', 1, 3)
  };

  const k2Betalingsliste = {
    group: 'kbet',
    states: ['tom', 'liste', 'sletBekraeft'],
    flags: ['aabne', 'sendte', 'visAlle'],

    name: function () {
      return 'help.kbet.name_liste';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = k2flags(k2Betalingsliste.flags);
      if (k2q(doc, 'span[onclick*="confirmDelete=yes"]')) return { state: 'sletBekraeft', flags: flags, data: {} };
      if (!k2q(doc, 'a[href="betalinger.php?id=0"]')) return { state: 'unknown', flags: flags, data: {} };
      flags.aabne = !!k2q(doc, 'a[href^="betalinger.php?tjek="]');
      flags.sendte = !!k2q(doc, 'a[href^="betalinger.php?liste_id="]');
      flags.visAlle = k2param(ctx, 'vis') === 'alle';
      return { state: flags.aabne || flags.sendte ? 'liste' : 'tom', flags: flags, data: {} };
    },

    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'sletBekraeft') return ['help.kbet.about.slet'];
      if (s === 'tom') return ['help.kbet.about.liste_tom', 'help.kbet.about.bogfoeres_ikke'];
      if (s !== 'liste') return [];
      return ['help.kbet.about.liste', f.visAlle ? 'help.kbet.about.vis_alle' : 'help.kbet.about.bogfoeres_ikke'];
    },

    tasks: K2_BL_TASKS,

    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'sletBekraeft') return ['BL6'];
      if (s === 'tom') return ['BL1', 'BL5'];
      if (s !== 'liste') return [];
      const ids = [];
      if (f.aabne) ids.push('BL2');
      ids.push('BL1');
      if (f.sendte) ids.push('BL3');
      ids.push('BL4', 'BL5');
      return unique(ids);
    },

    fields: {},

    shortcuts: [
      { keys: 'Alt+N', text: 'help.kbet.key.01', group: 'keys' },
      { keys: 'Alt+L', text: 'help.kbet.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      QB1: K2_BET_QB1
    },

    questionOrder: function () {
      return ['QB1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['kreditor/betalingsliste.php'] = k2Betalingsliste;

  // ---- Betalinger til bank (kreditor/betalinger.php)
  // Reached from Ny and the list numbers of Betalingslister (betalingsliste.php:92, :148, :204). Ny creates the list at
  // once and reloads with liste_id (betalinger.php:194-204). In the shell layout the head has Luk (accesskey L, :175) and,
  // on a list that is not sent, the select find with Kontosum (saldo), Ubetalte (unpaid) and kladdeliste (cashRegister)
  // (:173-187): Kontosum adds one line per supplier with an unsettled credit balance not on the list yet (:226-272);
  // kladdeliste lists the 30 newest journals linking betalinger.php?kladde_id= (:273-301); Ubetalte adds a line per
  // unsettled supplier invoice and does not check for invoices already on a list (:302-398, the checks are comments at
  // :346-347). Lines: erh[n], fra_kto[n], egen_ref[n], til_kto[n], kort_ref[n], modt_navn[n], belob[n], valuta[n],
  // betalingsdato[n], slet[n] (:500-590); betalingskontrol() marks invalid cells red, td[bgcolor="#FF0000"], with the
  // reason as title (:502-524, includes/payListFunc.php:29-80); unconfirmed bank details show Bekræft (:545-553). The foot:
  // Gem (accesskey g), the format select and Udskriv og luk (accesskey u) without errors, Slet røde (accesskey u) with
  // errors; a sent list has the format and Udskriv (:632-664); an empty list says so (:628-631). Udskriv writes the file
  // and prints Se / gem betalingsfil, a link into ../temp/ (:414-421, payListFunc.php:154, :194, :364, :403, :428, :671).
  // Nothing here posts or settles anything (kreditor/ has no alignAccounts() call, unlike debitor/betalinger.php:211).

  const K2_BT_TASKS = {
    BT1: task('kbet', 'BT1', 4, { target: 'select[name="find"]', label: fieldLabel('kbet', 'find'), kind: 'field' }),
    BT2: task('kbet', 'BT2', 3, { target: 'input[name^="belob["]', label: fieldLabel('kbet', 'belob'), kind: 'field' }),
    BT3: task('kbet', 'BT3', 2, { target: 'input[name^="slet["]', label: fieldLabel('kbet', 'slet'), kind: 'field' }),
    BT4: task('kbet', 'BT4', 4, { target: 'td[bgcolor="#FF0000"]', label: showKey('kbet', 'BT4'), kind: 'field' }),
    BT5: task('kbet', 'BT5', 3, { target: 'select[name="format"]', label: fieldLabel('kbet', 'format'), kind: 'field' }),
    BT6: task('kbet', 'BT6', 3, { target: 'button[onclick*="bekraeft_bank"]', label: showKey('kbet', 'BT6'), kind: 'button' }),
    BT7: task('kbet', 'BT7', 3),
    BT8: task('kbet', 'BT8', 3, { target: 'input[name="udskriv"]', label: showKey('kbet', 'BT8'), kind: 'button' }),
    BT9: task('kbet', 'BT9', 3, { target: 'a[href*="../temp/"]', label: showKey('kbet', 'BT9'), kind: 'button' }),
    BT10: task('kbet', 'BT10', 2, { target: 'a[href*="betalinger.php?kladde_id="]', label: showKey('kbet', 'BT10'), kind: 'button' }),
    BT11: task('kbet', 'BT11', 1, { target: 'a[href="../kreditor/betalingsliste.php"]', label: showKey('kbet', 'BT11'), kind: 'button' })
  };

  const K2_BT_FIELDS = {};
  ['find', 'listenote', 'erh', 'fra_kto', 'egen_ref', 'til_kto', 'kort_ref', 'modt_navn', 'belob', 'valuta', 'betalingsdato',
    'slet', 'format'].forEach(function (k) { K2_BT_FIELDS[k] = fieldKeys('kbet', k); });

  const k2Betalinger = {
    group: 'kbet',
    states: ['tom', 'aaben', 'fejl', 'sendt', 'fil', 'kladdevalg'],
    flags: ['ubekraeftet', 'ingenFund'],

    name: function (ctx, st) {
      const nr = String((st && st.data && st.data.liste) || '');
      return nr ? ['help.kbet.name_nr', nr] : 'help.kbet.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = k2flags(k2Betalinger.flags);
      const data = { liste: k2param(ctx, 'liste_id').replace(/[^0-9]/g, '') };
      if (data.liste === '0') data.liste = '';
      flags.ubekraeftet = !!k2q(doc, 'button[onclick*="bekraeft_bank"]');
      flags.ingenFund = k2qa(doc, 'p').some(function (p) { return k2text(p).indexOf('Der blev ikke fundet nogen posteringer') !== -1; });
      let state = 'unknown';
      if (k2q(doc, 'a[href*="../temp/"]')) state = 'fil';
      else if (k2q(doc, 'a[href*="betalinger.php?kladde_id="]')) state = 'kladdevalg';
      else if (k2q(doc, 'input[name="slet_ugyldige"]')) state = 'fejl';
      else if (k2q(doc, 'input[name="gem"]')) state = 'aaben';
      else if (k2q(doc, 'select[name="format"]')) state = 'sendt';
      else if (k2q(doc, 'select[name="find"]')) state = 'tom';
      return { state: state, flags: flags, data: data };
    },

    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const map = {
        tom: 'help.kbet.about.tom', aaben: 'help.kbet.about.aaben', fejl: 'help.kbet.about.fejl', sendt: 'help.kbet.about.sendt',
        fil: 'help.kbet.about.fil', kladdevalg: 'help.kbet.about.kladdevalg'
      };
      if (!Object.prototype.hasOwnProperty.call(map, s)) return [];
      const out = [map[s]];
      if (f.ubekraeftet) out.push('help.kbet.about.ubekraeftet');
      else if (f.ingenFund && (s === 'tom' || s === 'aaben')) out.push('help.kbet.about.ingen_fund');
      else if (s === 'aaben' || s === 'fil' || s === 'sendt') out.push('help.kbet.about.bogfoeres_ikke');
      return out;
    },

    tasks: K2_BT_TASKS,

    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (f.ubekraeftet && (s === 'aaben' || s === 'fejl')) ids.push('BT6');
      // Ubetalte adds every unpaid bill again (betalinger.php:294-370), so on a list with lines it is offered last.
      if (s === 'tom') ids.push('BT1', 'BT7', 'BT11');
      else if (s === 'kladdevalg') ids.push('BT10', 'BT1');
      else if (s === 'fejl') ids.push('BT4', 'BT2', 'BT3', 'BT7', 'BT1');
      else if (s === 'aaben') ids.push('BT5', 'BT2', 'BT3', 'BT7', 'BT1');
      else if (s === 'sendt') ids.push('BT8', 'BT7', 'BT11');
      else if (s === 'fil') ids.push('BT9', 'BT7', 'BT11');
      return unique(ids);
    },

    problem: function (ctx, st) {
      const s = st ? st.state : '';
      return s === 'fejl' ? k2firstTask(k2Betalinger, ctx, st) : '';
    },

    fields: K2_BT_FIELDS,

    // The line fields are erh[1], belob[1] … (betalinger.php:470-560).
    fieldKey: function (el) {
      const m = /^([a-z_]+)\[\d+\]$/.exec(attr(el, 'name'));
      return m ? m[1] : null;
    },

    shortcuts: [
      { keys: 'Alt+G', text: 'help.kbet.key.03', group: 'keys' },
      { keys: 'Alt+U', text: 'help.kbet.key.04', group: 'keys' },
      { keys: 'Alt+L', text: 'help.kbet.key.05', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      QB1: K2_BET_QB1,
      QB2: {
        q: 'help.kbet.q.qb2.q',
        words: {
          da: ['rød', 'røde', 'fejl', 'ugyldig', 'kan ikke udskrive', 'mangler konto'],
          en: ['red', 'error', 'invalid', 'cannot print', 'missing account'],
          no: ['rød', 'røde', 'feil', 'ugyldig', 'kan ikke skrive ut', 'mangler konto']
        },
        steps: stepKeys('kbet', 'BT4', 1, 4),
        show: K2_BT_TASKS.BT4.show
      },
      QB3: {
        q: 'help.kbet.q.qb3.q',
        words: {
          da: ['dobbelt', 'to gange', 'samme regning', 'allerede betalt', 'gentaget'],
          en: ['twice', 'double', 'duplicate', 'same invoice', 'already paid'],
          no: ['dobbelt', 'to ganger', 'samme regning', 'allerede betalt']
        },
        intro: 'help.kbet.q.qb3.intro',
        steps: ['help.kbet.task.bt3.step1', 'help.kbet.task.bt3.step2']
      }
    },

    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'fejl') return ['QB2', 'QB3', 'QB1'];
      if (s === 'aaben') return ['QB3', 'QB1', 'QB2'];
      return ['QB1', 'QB3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['kreditor/betalinger.php'] = k2Betalinger;

  // ---- Fusioner kreditorer (kreditor/kontofusion.php)
  // Reached only from the card: = and another supplier's number in Leverandørnr., then Gem/opdatér
  // (kreditorkort.php:140-143). Without another open supplier with that number it alerts and goes back to the card
  // (kontofusion.php:56-63). Otherwise the form kontofusion shows the supplier that goes, til, the one that stays, and the
  // hard-coded Danish buttons Fortryd and OK (:93-113). OK moves history, contacts, orders, open posts, job cards, BS
  // customers and shop addresses to the other supplier and deletes this one (:65-76); the items' supplier links (vare_lev)
  // are not moved.

  const K2_FUS_TASKS = {
    FU1: task('kfus', 'FU1', 3, { target: 'input[name="fusion"][value="OK"]', label: showKey('kfus', 'FU1'), kind: 'button' }),
    FU2: task('kfus', 'FU2', 2, { target: 'input[name="fusion"][value="Fortryd"]', label: showKey('kfus', 'FU2'), kind: 'button' })
  };

  const k2Fusion = {
    group: 'kfus',
    states: ['bekraeft'],
    flags: [],

    name: function () {
      return 'help.kfus.name';
    },

    detect: function (ctx) {
      return { state: k2q(ctx.doc, 'form[name="kontofusion"]') ? 'bekraeft' : 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      return st && st.state === 'bekraeft' ? ['help.kfus.about.bekraeft'] : [];
    },

    tasks: K2_FUS_TASKS,

    order: function (ctx, st) {
      return st && st.state === 'bekraeft' ? ['FU1', 'FU2'] : [];
    },

    fields: {},

    shortcuts: [],

    questions: {
      Q1: {
        q: 'help.kfus.q.q1.q',
        words: {
          da: ['sammenlæg', 'sammenlægge', 'fusion', 'fusionere', 'dublet', 'to gange', 'flyt'],
          en: ['merge', 'combine', 'duplicate', 'twice', 'move'],
          no: ['slå sammen', 'sammenslåing', 'fusjon', 'duplikat', 'to ganger', 'flytt']
        },
        intro: 'help.kfus.q.q1.intro',
        steps: ['help.kfus.task.fu1.step3', 'help.kfus.q.q1.step2']
      }
    },

    questionOrder: function () {
      return ['Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['kreditor/kontofusion.php'] = k2Fusion;

  // ---- Top 100 (kreditor/top100.php)
  // Reached with Top 100 on the Kreditorrapporter form (reportFunc/frontPageOldMenu.php:114-123, window.location.href).
  // In the shell layout: Tilbage (accesskey L, ../kreditor/rapport.php), the period in the title and Periode (accesskey P,
  // top100.php?periode=..&ret=on) (top100.php:83-99). With ret the form omsaetning asks for periode (ddmmåå:ddmmåå, one date
  // runs to today, :52-58) with OK (accesskey O) (:107-113); else rows tr[bgcolor] with Nr., Kontonr., Firmanavn and
  // Omsætning DKK: the sum of purchase invoices and credit notes from purchase orders (art KO/KK, status >= 3) in DKK
  // (:114-130). Bills posted only in the cash journal are not orders, so they are not in it.

  const K2_STAT_TASKS = {
    ST1: task('kstat', 'ST1', 2, { target: function (ctx) { return k2btn(k2q(ctx.doc, 'a[accesskey="P"]')); }, label: showKey('kstat', 'ST1'), kind: 'button' }),
    ST2: task('kstat', 'ST2', 3, { target: k2only(['liste'], 'tr[bgcolor]'), label: showKey('kstat', 'ST2'), kind: 'row' }),
    ST3: task('kstat', 'ST3', 1, { target: function (ctx) { return k2btn(k2q(ctx.doc, 'a[accesskey="L"]')); }, label: showKey('kstat', 'ST3'), kind: 'button' }),
    ST4: task('kstat', 'ST4', 0, { target: 'input[name="periode"]', label: fieldLabel('kstat', 'periode'), kind: 'field' },
      ['help.kstat.task.st1.step2'])
  };

  const k2Top100 = {
    group: 'kstat',
    states: ['liste', 'tom', 'periode'],
    flags: [],

    name: function () {
      return 'help.kstat.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      if (k2q(doc, 'input[name="periode"]')) return { state: 'periode', flags: {}, data: {} };
      if (!k2q(doc, 'a[accesskey="P"]')) return { state: 'unknown', flags: {}, data: {} };
      return { state: k2q(doc, 'tr[bgcolor]') ? 'liste' : 'tom', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'liste') return ['help.kstat.about.liste', 'help.kstat.about.kun_ordrer'];
      if (s === 'tom') return ['help.kstat.about.tom', 'help.kstat.about.kun_ordrer'];
      if (s === 'periode') return ['help.kstat.about.periode'];
      return [];
    },

    tasks: K2_STAT_TASKS,

    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'periode') return ['ST4'];
      if (s === 'tom') return ['ST1', 'ST2', 'ST3'];
      if (s === 'liste') return ['ST2', 'ST1', 'ST3'];
      return [];
    },

    fields: {
      periode: fieldKeys('kstat', 'periode')
    },

    shortcuts: [
      { keys: 'Alt+P', text: 'help.kstat.key.01', group: 'keys' },
      { keys: 'Alt+O', text: 'help.kstat.key.02', group: 'keys' },
      { keys: 'Alt+L', text: 'help.kstat.key.03', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.kstat.q.q1.q',
        words: {
          da: ['mangler', 'ikke med', 'omsætning', 'forkert', 'kassekladde', 'køb'],
          en: ['missing', 'not included', 'turnover', 'revenue', 'wrong', 'journal', 'purchases'],
          no: ['mangler', 'ikke med', 'omsetning', 'feil', 'kassekladd', 'kjøp']
        },
        steps: stepKeys('kstat', 'ST2', 1, 3)
      }
    },

    questionOrder: function () {
      return ['Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['kreditor/top100.php'] = k2Top100;
})();
