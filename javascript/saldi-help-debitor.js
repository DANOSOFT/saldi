// ---- javascript/saldi-help-debitor.js --- ver 5.1.0 --- 2026.11.13 ---
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The help panel's page knowledge for the Debitor module (window.SaldiHelpContent.pages, doc/hjaelp_saldi_ai.md §3.2,
// §4 and §16.1): the Debitor sidebar (quotes, orders, invoices, customers, reminders, payments, reports) and every page
// reached from it in the frame.
// Loads after javascript/saldi-help-finans.js and builds its pages with the helpers of javascript/saldi-help-content.js
// (SaldiHelpContent.lib). Data and pure functions only. Every text the panel shows is a key in
// importfiler/tekster_keyed.tsv (help.<group>.*), fetched per language from index/helpTexts.php. Detectors and show
// targets only read the frame document: no writes, focus, clicks, network or timers. Frame elements come from another
// realm, so type checks use nodeType/tagName.
//
// 20261108 CL/ASR Created: the module file for the Debitor pages, loaded like saldi-help-finans.js.
// 20261108 CL/ASR The Debitor pages (spec §22): orders, customers, history, job cards, reports, reminders, payments, recurring invoicing, statistics.
// 20261108 CL/ASR Review (spec §22.8): payment question on the order, Lav en ordre on the customer card, a reminder without a usable address is the problem card.
// 20261108 CL/ASR Live check on ssl12 (spec §22.9): new order with id 0, Gem before Fakturér, shell row links, hidden O column, Debitorkort and list targets by state, an empty reminder view.
// 20261109 CL/ASR Page questions in Saldi Assist (spec §25): each state's top three; new questions on the order list, the order, the card and the reminder.
// 20261112 CL/ASR The CRM replaced the customer history (spec §22.10): no pages for historikkort.php, historik.php and debitor_historik.php,
//                 which redirect to debitor/crm.php, and no Historik view on the list. DB18, DK20 and DB3's second step lead to the CRM.
//                 debitor/crm.php has its own page (group dcrm).
// 20261112 CL/ASR CRM review: the page is read again after a click or a key (inPlace), a Vis mig reads the live state, an open
//                 dialog rings only its own composer (flag dialog), and texts against crm.js (search, write access, note).
// 20261113 CL/ASR CRM: "mine" is one of the page's ownWords, so the help search finds the view Mine (it stays a stop word on
//                 every other page). CR2 and Q3 name til only for a company with employees; Sager covers the empty board.
(function () {
  'use strict';

  const C = window.SaldiHelpContent;
  if (!C || !C.lib || !C.pages) return;
  const task = C.lib.task, showKey = C.lib.showKey, fieldKeys = C.lib.fieldKeys, fieldLabel = C.lib.fieldLabel,
    stepKeys = C.lib.stepKeys, unique = C.lib.unique, attr = C.lib.attr, byName = C.lib.byName, rowOf = C.lib.rowOf;
  const PAGES = C.pages;

  // ==== Sales: the order list and the order (author D1; its helpers start with d1)
  // ------------------------------------------------------------------ shared by the D1 pages

  /** The first element of root matching sel, or null (a bad selector or a foreign node gives null). */
  function d1q(root, sel) {
    try {
      const el = root ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  /** Every element of root matching sel, [] on any error. */
  function d1qa(root, sel) {
    try {
      return root ? Array.prototype.slice.call(root.querySelectorAll(sel)) : [];
    } catch (e) {
      return [];
    }
  }

  /** The trimmed value of a form control, '' for anything else. */
  function d1val(el) {
    return el && el.nodeType === 1 ? String(el.value || '').trim() : '';
  }

  /** A Danish or plain number ('0,00', '25', '-1.250,50') as a number; NaN when there is none. */
  function d1num(s) {
    const t = String(s || '').replace(/\s/g, '').replace(/\.(?=\d{3}(\D|$))/g, '').replace(',', '.');
    return t === '' ? NaN : parseFloat(t);
  }

  /** The row (tr) around an element, or the element itself. */
  function d1row(el) {
    try {
      const tr = el && el.closest ? el.closest('tr') : null;
      return tr && tr.nodeType === 1 ? tr : el;
    } catch (e) {
      return el;
    }
  }

  /** Words a user would type; never shown. */
  function d1Words(da, en, no) {
    return { da: da, en: en, no: no };
  }

  // ---- Ordreliste, salg (debitor/ordreliste.php)
  // Evidence, debitor/ordreliste.php unless named: Debitor > Ordre in the sidebar (index/main.php:262) and Fakturaliste on
  // the customer card (debitorkort.php:2638, account_context=1). The tab (valg) comes from the URL or the user's last tab
  // (:378-421); with hurtigfakturering there is no Tilbud tab (:410-411; ordLstIncludes/topLine.php:51-60); BS only with
  // a PBS agreement (:431-433), and valg=pbs prints pbsliste.php and exits (topLine.php:94-108). The grid is ordrelst_<valg>
  // (:628), drawn by includes/orderFuncIncludes/grid_order.php render_datagrid() (:1393-1470): #datatable-wrapper-<id>,
  // the search inputs search[<id>][<field>] in thead (:1529-1551), .no-rows-row with text 2730 when nothing matches
  // (:1445-1452), one .filler-row (:1453); Kolonner and Filtre replace the table with a form (:1786-1810, :2173-2190).
  // Rows open the order (:768, :285 order-row-clickable.js.php); print icons per row (:1659-1683); an invoice that is not
  // settled has its sum in red with the title Ikke udlignet (:1153-1166). Ny is #ny (topLine.php:113-125). The bottom
  // bar (:2667-2785): Vis lagerstatus and Tilbage (:2675-2683), CSV import and Fakturér alt with an MFAKT setting
  // (:2685-2701), Hent fra shop (:2704-2706), Massehandlinger in #bulkActionForm (:2745-2785): Vælg alle, on Fakturaer
  // Udskriv, Følgeseddel, Genfakturer, Send mails, else Slet (and Ret on Ordrer, which opens ret_genfakt.php), and Ryd.

  /** The tab of the order list, from the grid's id ordrelst_<valg>; '' without a grid. */
  function d1Tab(doc) {
    const w = d1q(doc, '[id^="datatable-wrapper-ordrelst_"]');
    const m = w ? /^datatable-wrapper-ordrelst_([a-z]+)$/.exec(attr(w, 'id')) : null;
    return m ? m[1] : '';
  }

  /** The order rows of the grid; null outside the list view (Kolonner, Filtre have no thead). */
  function d1OlRows(doc, tab) {
    if (!tab || !d1q(doc, '#datatable-ordrelst_' + tab + ' thead')) return null;
    return d1qa(doc, '#datatable-ordrelst_' + tab + ' tbody tr:not(.filler-row):not(.no-rows-row)');
  }

  /** A search field of the grid with text or a choice (grid_order.php:1529-1551). */
  function d1OlSearching(doc) {
    return d1qa(doc, 'input[name^="search[ordrelst_"], select[name^="search[ordrelst_"]')
      .some(function (el) { return d1val(el) !== ''; });
  }

  /** A target only on the tabs listed (the chat offers every task in every state, spec §19.3). */
  function d1OnTab(tabs, find) {
    return function (ctx) {
      const tab = d1Tab(ctx.doc);
      return tabs.indexOf(tab) >= 0 ? find(ctx, tab) : null;
    };
  }

  /** The first order row's link, as the row. In the shell includes/order-row-clickable.js.php rewrites the row links
   *  to /<regnskab>/index/main.php#/debitor/ordre.php?..., so the link is matched anywhere in the href (live check). */
  function d1OlFirstRow(ctx, tab) {
    return d1row(d1q(ctx.doc, '#datatable-ordrelst_' + tab + ' tbody a[href*="ordre.php?"]'));
  }

  /** The search field under Firmanavn, else the first search field of the grid. */
  function d1OlSearchField(ctx) {
    const tab = d1Tab(ctx.doc);
    return d1q(ctx.doc, 'input[name="search[ordrelst_' + tab + '][firmanavn]"]') || d1q(ctx.doc, 'input[name^="search[ordrelst_"]');
  }

  const D1_OL = 'dol';

  const D1_OL_TASKS = {
    // Ny opens ordre.php without an id (topLine.php:113-125); the order is made when the customer is chosen (ordre.php:870-900)
    OL1: task(D1_OL, 'OL1', 3, { target: '#ny', label: showKey(D1_OL, 'OL1'), kind: 'button' }),
    // A new order is a tilbud (status 0) until Godkend and Gem (ordre.php:1722, :5349); its Udskriv makes the tilbud (:2761-2786)
    OL2: task(D1_OL, 'OL2', 3, { target: d1OnTab(['tilbud'], function (ctx) { return d1q(ctx.doc, '#ny'); }), label: showKey(D1_OL, 'OL1'), kind: 'button' }),
    // The thead search inputs; dates take ranges with ':' (dateRangeSearch.php:123-127); Enter submits the grid's form
    OL3: task(D1_OL, 'OL3', 3, { target: d1OlSearchField, label: fieldLabel(D1_OL, 'search:firmanavn'), kind: 'field' }),
    // A row opens the order (:768); Fakturér there (ordre.php:6440-6478)
    OL4: task(D1_OL, 'OL4', 3, { target: d1OnTab(['ordrer'], d1OlFirstRow), label: showKey(D1_OL, 'OL4'), kind: 'row' }),
    // Udskriv makes one PDF of the ticked invoices, Send mails mails them (:2009-2012); the boxes are cloned into the bulk form (:2610-2630)
    OL5: task(D1_OL, 'OL5', 3, {
      target: d1OnTab(['faktura'], function (ctx) { return d1q(ctx.doc, '#bulkActionForm input[name="submit"]'); }),
      label: showKey(D1_OL, 'OL5'),
      kind: 'button'
    }),
    // Red sum, title Ikke udlignet (:1153-1166); payments settle against the invoice number (bogfor.php:1180-1227)
    OL6: task(D1_OL, 'OL6', 4, { target: '#datatable-ordrelst_faktura tbody td[style*="#FF0000"]', label: showKey(D1_OL, 'OL6'), kind: 'status' }),
    // Kredit on the invoice makes a credit note order (ordre.php:4688-4692, :2622-2700)
    OL7: task(D1_OL, 'OL7', 3, { target: d1OnTab(['faktura'], d1OlFirstRow), label: showKey(D1_OL, 'OL7'), kind: 'row' }),
    // Genfakturer opens genfakturer.php with the ticked invoices (:1971-1995); its form and Ok (genfakturer.php:158-169)
    OL8: task(D1_OL, 'OL8', 3, { target: '#bulkActionForm input[value="Genfakturer"]', label: showKey(D1_OL, 'OL8'), kind: 'button' }),
    // Vis lagerstatus colours the rows and adds the stock popup (:2675-2683, :160-235, :800-830)
    OL9: task(D1_OL, 'OL9', 3, { target: '#left-controls button[onclick*="vis_lagerstatus=on"]', label: showKey(D1_OL, 'OL9'), kind: 'button' }),
    // The grid menu: Søg, Ryd søgning, CSV, PDF, Redigér > Kolonner/Filtre (grid_order.php:1560-1615)
    OL10: task(D1_OL, 'OL10', 3, { target: '[id^="datatable-wrapper-ordrelst_"] .dropdown-trigger', label: showKey(D1_OL, 'OL10'), kind: 'button' }),
    // Slet asks and calls slet_ordre() per ticked order (:2015-2019, :2769-2776); invoiced or delivered orders are refused (ordrefunc.php:5851-5875)
    OL11: task(D1_OL, 'OL11', 3, { target: '#bulkActionForm input[name="slet_valgte"]', label: showKey(D1_OL, 'OL11'), kind: 'button' }),
    // The print icons in Handlinger (:1659-1683): plukliste and ordrebekræftelse, følgeseddel and faktura, tilbud
    OL12: task(D1_OL, 'OL12', 2, { target: '[id^="datatable-ordrelst_"] tbody a[href^="formularprint.php?id="]', label: showKey(D1_OL, 'OL12'), kind: 'button' }),
    // Ryd empties the search and filters, not the columns (:117-140, :496-509)
    OL13: task(D1_OL, 'OL13', 2, { target: '#bulkActionForm input[name="clear"]', label: showKey(D1_OL, 'OL13'), kind: 'button' }),
    // Fakturér alt asks, then delivers and invoices every order that can be delivered (:2694-2700; massefakt.php:80-100)
    OL14: task(D1_OL, 'OL14', 3, { target: 'a[href^="massefakt.php"]', label: showKey(D1_OL, 'OL14'), kind: 'button' }),
    // Hent fra shop asks the shop API for new orders, at most every 20 minutes (:2028-2078, :2704-2706)
    OL15: task(D1_OL, 'OL15', 2, { target: 'a[href*="hent_nu=1"]', label: showKey(D1_OL, 'OL15'), kind: 'button' }),
    // Godkend and Gem on the tilbud (ordre.php:5349, :1722)
    OL16: task(D1_OL, 'OL16', 3, { target: d1OnTab(['tilbud'], d1OlFirstRow), label: showKey(D1_OL, 'OL16'), kind: 'row' }),
    // BS: the list number opens pbsfile.php with the list's invoices (pbsliste.php:42-62; pbsfile.php:297-305)
    OL17: task(D1_OL, 'OL17', 2, { target: 'a[href^="pbsfile.php?id="]:not([href*="afslut"])', label: showKey(D1_OL, 'OL17'), kind: 'button' }),
    // afslut closes the list and writes the file to save (pbsliste.php:48-52, :58-62; pbsfile.php:256-310)
    OL18: task(D1_OL, 'OL18', 3, { target: 'a[href*="afslut=ok"]', label: showKey(D1_OL, 'OL18'), kind: 'button' }),
    // Import PBS: an agreement file from Nets updates the customers' cards (topLine.php:94-105; pbs_import.php:58-162)
    OL19: task(D1_OL, 'OL19', 3, { target: 'a[href^="pbs_import.php"] button', label: showKey(D1_OL, 'OL19'), kind: 'button' }),
    // Kolonner and Filtre: Gem saves, Luk goes back (grid_order.php:1806-1811, :2186-2191)
    OL20: task(D1_OL, 'OL20', 3, { target: '[id^="datatable-wrapper-ordrelst_"] form button:not([type])', label: showKey(D1_OL, 'OL20'), kind: 'button' })
  };

  const D1_OL_FIELDS = {};
  ['search:ordrenr', 'search:firmanavn', 'search:kontonr', 'search:ordredate', 'search:levdate', 'search:fakturanr',
    'search:fakturadate', 'search:sum', 'rowcount']
    .forEach(function (k) { D1_OL_FIELDS[k] = fieldKeys(D1_OL, k); });

  const d1Ordreliste = {
    group: D1_OL,
    states: ['tilbud', 'ordrer', 'faktura', 'bs', 'opsaetning'],
    flags: ['tom', 'soegning', 'kunde', 'lagerstatus', 'hurtigfakt', 'ikkeUdlignet', 'massefakt', 'shop', 'skjulteKolonner'],

    name: function (ctx, st) {
      const state = st ? st.state : '';
      if (state === 'tilbud') return 'help.dol.name_tilbud';
      if (state === 'faktura') return 'help.dol.name_faktura';
      if (state === 'bs') return 'help.dol.name_bs';
      return 'help.dol.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const tab = d1Tab(doc);
      const rows = d1OlRows(doc, tab);
      let state = 'unknown';
      if (tab && rows === null) state = 'opsaetning';
      else if (tab === 'tilbud' || tab === 'ordrer' || tab === 'faktura') state = tab;
      else if (d1q(doc, 'a[href^="pbs_import.php"], a[href^="pbsfile.php"]')) state = 'bs';
      let lager = false;
      try { lager = ctx.url.searchParams.get('vis_lagerstatus') === 'on'; } catch (e) { lager = false; }
      const flags = {
        tom: rows !== null && rows.length === 0,
        soegning: d1OlSearching(doc),
        // Opened from a customer card: Tilbage to the card and the tabs carry account_context=1 (topLine.php:36-49)
        kunde: !!d1q(doc, 'a[href^="debitorkort.php?id="][accesskey="L"], a[href*="account_context=1"]'),
        lagerstatus: lager,
        // No Tilbud tab: hurtigfakturering (topLine.php:51-60; ordreliste.php:410-411)
        hurtigfakt: !!tab && tab !== 'tilbud' && !d1q(doc, 'a[href*="valg=tilbud"]'),
        ikkeUdlignet: tab === 'faktura' && !!d1q(doc, '#datatable-ordrelst_faktura tbody td[style*="#FF0000"]'),
        massefakt: !!d1q(doc, 'a[href^="massefakt.php"]'),
        shop: !!d1q(doc, 'a[href*="hent_nu=1"]'),
        // Rows without the O column (input.deliveryNoteSelect, ordreliste.php:1641-1656): a column setup saved before the
        // column existed hides it and Handlinger until the user saves Kolonner (grid_order.php merge_column_setup(),
        // live in test_838), so the mass actions and the print icons have nothing to work on
        skjulteKolonner: !!rows && rows.length > 0 && !d1q(doc, '#datatable-ordrelst_' + tab + ' input.deliveryNoteSelect')
      };
      return { state: state, flags: flags, data: { rows: rows ? rows.length : 0 } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'bs') return ['help.dol.about.bs'];
      if (state === 'opsaetning') return ['help.dol.about.opsaetning'];
      if (state !== 'tilbud' && state !== 'ordrer' && state !== 'faktura') return [];
      const out = [];
      if (f.tom) out.push(f.soegning && !f.kunde ? 'help.dol.about.soegning_tom' : 'help.dol.about.tom_' + state);
      else if (state === 'ordrer') out.push(f.hurtigfakt ? 'help.dol.about.ordrer_hurtig' : 'help.dol.about.ordrer');
      else out.push('help.dol.about.' + state);
      if (f.kunde) out.push('help.dol.about.kunde');
      else if (f.lagerstatus && state === 'ordrer') out.push('help.dol.about.lagerstatus');
      else if (f.ikkeUdlignet && state === 'faktura' && !f.tom) out.push('help.dol.about.ikke_udlignet');
      else if (f.skjulteKolonner) out.push('help.dol.about.skjulte_kolonner');
      return out;
    },

    tasks: D1_OL_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'bs') return ['OL17', 'OL18', 'OL19'];
      if (state === 'opsaetning') return ['OL20', 'OL10'];
      if (state !== 'tilbud' && state !== 'ordrer' && state !== 'faktura') return [];
      const ids = [];
      // A search that hides everything comes first; a customer's own list is not a search to clear
      if (f.soegning && !f.kunde && f.tom) ids.push('OL13', 'OL3');
      if (state === 'tilbud') {
        ids.push('OL2');
        if (!f.tom) ids.push('OL16', 'OL3', 'OL12', 'OL11');
      } else if (state === 'ordrer') {
        if (f.tom) ids.push('OL1');
        else {
          ids.push('OL4', 'OL1', 'OL3');
          if (f.massefakt) ids.push('OL14');
          if (f.shop) ids.push('OL15');
          ids.push('OL12', 'OL9', 'OL11');
        }
      } else {
        if (f.tom) ids.push('OL1');
        else {
          if (f.ikkeUdlignet) ids.push('OL6');
          ids.push('OL3', 'OL5', 'OL7', 'OL12', 'OL8');
        }
      }
      if (f.soegning && !f.kunde) ids.push('OL13');
      ids.push('OL3', 'OL10');
      return unique(ids);
    },

    fields: D1_OL_FIELDS,

    // Enter in a search field submits the grid's form (its first submit is Søg, grid_order.php:1574-1578). Tilbage has
    // Alt+L only when the list was opened from a customer card (topLine.php:44-49), so no access keys are listed.
    shortcuts: [
      { keys: 'Enter', text: 'help.dol.key.01', group: 'keys' }
    ],

    questions: {
      Q1: {
        q: 'help.dol.q.q1.q',
        words: d1Words(['tilbud', 'fane', 'mangler', 'hurtigfakturering', 'levér', 'levere', 'følgeseddel', 'kan ikke se'],
          ['offer', 'offers', 'quote', 'tab', 'missing', 'fast invoicing', 'deliver', 'delivery note'],
          ['tilbud', 'fane', 'mangler', 'hurtigfakturering', 'levere', 'følgeseddel', 'kan ikke se']),
        steps: ['help.dol.q.q1.step1', 'help.dol.q.q1.step2', 'help.dol.q.q1.step3']
      },
      Q2: {
        q: 'help.dol.q.q2.q',
        words: d1Words(['rød', 'røde', 'rødt', 'ubetalt', 'ubetalte', 'betalt', 'udlignet', 'ikke udlignet', 'skylder', 'åbne poster'],
          ['red', 'unpaid', 'paid', 'settled', 'not settled', 'owes', 'open items'],
          ['rød', 'røde', 'rødt', 'ubetalt', 'ubetalte', 'betalt', 'utlignet', 'ikke avgjort', 'skylder', 'åpne poster']),
        steps: stepKeys(D1_OL, 'OL6', 1, 4),
        show: D1_OL_TASKS.OL6.show
      },
      Q3: {
        q: 'help.dol.q.q3.q',
        words: d1Words(['kunde', 'kundens', 'find', 'søg', 'søge', 'faktura', 'fakturaer', 'ordrer', 'fakturaliste'],
          ['customer', 'find', 'search', 'invoice', 'invoices', 'orders', 'invoice list'],
          ['kunde', 'kundens', 'finn', 'søk', 'søke', 'faktura', 'fakturaer', 'ordrer', 'fakturaliste']),
        steps: ['help.dol.q.q3.step1', 'help.dol.q.q3.step2', 'help.dol.q.q3.step3'],
        show: D1_OL_TASKS.OL3.show
      },
      // The tab follows the status: Tilbud 0, Ordrer 1-2 (every status below 3 with hurtigfakturering), Fakturaer 3 and
      // up (:549-555, :1733-1741); no Tilbud tab with hurtigfakturering (:410-411); Ryd empties the search (:117-140,
      // :2783); from a debitorkort the list holds that customer's orders only (account_context, :1743-1746)
      Q4: {
        q: 'help.dol.q.q4.q',
        words: d1Words(['væk', 'forsvundet', 'hvor er', 'kan ikke finde', 'mangler', 'fane', 'flyttet', 'faktureret', 'tom liste'],
          ['gone', 'disappeared', 'where is', 'cannot find', 'missing', 'tab', 'moved', 'invoiced', 'empty list'],
          ['borte', 'forsvunnet', 'hvor er', 'finner ikke', 'mangler', 'fane', 'flyttet', 'fakturert', 'tom liste']),
        intro: 'help.dol.q.q4.intro',
        steps: ['help.dol.q.q4.step1', 'help.dol.q.q4.step2', 'help.dol.q.q4.step3', 'help.dol.q.q4.step4']
      },
      // Massehandlinger has Slet on Tilbud and Ordrer only (:2763-2779); slet_ordre() refuses an invoiced order and one
      // with delivered items (includes/ordrefunc.php:5851-5872). On the order Slet needs count($leveret) == 0, and
      // $leveret holds one entry per line (ordre.php:5853, :5986, :6526), so it shows only on an order without lines.
      Q5: {
        q: 'help.dol.q.q5.q',
        words: d1Words(['slet', 'slette', 'fjern', 'fjerne', 'kan ikke slette', 'annuller', 'annullere', 'tilbud', 'ordre'],
          ['delete', 'remove', 'cannot delete', 'cancel', 'offer', 'order'],
          ['slett', 'slette', 'fjern', 'fjerne', 'kan ikke slette', 'annuller', 'annullere', 'tilbud', 'ordre']),
        intro: 'help.dol.q.q5.intro',
        steps: ['help.dol.q.q5.step1', 'help.dol.q.q5.step2', 'help.dol.task.ol11.step3', 'help.dol.q.q5.step4'],
        show: D1_OL_TASKS.OL11.show
      },
      // As OL7, from the list: Kredit on the invoice (ordre.php:4688-4692); without hurtigfakturering Kreditér shows from
      // status 2, after Godkend and Modtag (ordre.php:6442, :5349, :6418-6421); Kopier on the invoice (ordre.php:4673-4693)
      Q6: {
        q: 'help.dol.q.q6.q',
        words: d1Words(['ret', 'rette', 'fejl', 'forkert', 'faktura', 'kreditnota', 'kreditér', 'kreditere', 'annuller', 'slet faktura'],
          ['correct', 'error', 'mistake', 'wrong', 'invoice', 'credit note', 'credit', 'cancel', 'delete invoice'],
          ['rett', 'rette', 'feil', 'faktura', 'kreditnota', 'krediter', 'kreditere', 'annuller', 'slett faktura']),
        intro: 'help.dol.task.ol7.why',
        steps: ['help.dol.q.q6.step1', 'help.dol.task.ol7.step3', 'help.dol.q.q6.step3', 'help.dol.q.q6.step4'],
        show: D1_OL_TASKS.OL7.show
      },
      // The skjulteKolonner flag: a column setup saved before O and Handlinger existed keeps them out until it is saved
      // again (grid_order.php:921-940); Kolonner lists them with Pos - and a number switches them on (:1888-1891, :1965-1967)
      Q7: {
        q: 'help.dol.q.q7.q',
        words: d1Words(['flueben', 'markere', 'vælg alle', 'massehandling', 'kolonne', 'skjult', 'handlinger', 'udskriv', 'mangler'],
          ['tick', 'checkbox', 'select', 'select all', 'mass action', 'column', 'hidden', 'actions', 'print', 'missing'],
          ['hake', 'avkrysning', 'markere', 'velg alle', 'massehandling', 'kolonne', 'skjult', 'handlinger', 'skriv ut', 'mangler']),
        intro: 'help.dol.q.q7.intro',
        steps: ['help.dol.task.ol10.step1', 'help.dol.q.q7.step2', 'help.dol.q.q7.step3'],
        show: D1_OL_TASKS.OL10.show
      }
    },

    // The tab's own questions first (spec §25): where an order went and why it cannot be deleted on Tilbud and Ordrer,
    // correcting an invoice on Fakturaer, red sums when there are some; hidden mass-action columns lead while they block.
    // Fakturaer opened from a debitorkort already holds one customer's invoices, so finding them (Q3) gives way.
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const list = state === 'tilbud' || state === 'ordrer' || state === 'faktura';
      let ids;
      if (f.skjulteKolonner && list) ids = state === 'faktura' ? ['Q7', 'Q6', 'Q2'] : ['Q7', 'Q4', 'Q5'];
      else if (state === 'opsaetning') ids = ['Q7', 'Q4', 'Q3'];
      else if (state === 'bs') ids = ['Q3', 'Q4', 'Q2'];
      else if (state === 'faktura') ids = f.ikkeUdlignet ? ['Q2', 'Q6', 'Q3'] : ['Q6', 'Q3', 'Q2'];
      else if (state === 'tilbud') ids = ['Q4', 'Q5', 'Q3'];
      else ids = f.hurtigfakt ? ['Q4', 'Q1', 'Q5'] : ['Q4', 'Q5', 'Q3'];
      if (f.kunde && state === 'faktura') ids = ids.map(function (id) { return id === 'Q3' ? 'Q4' : id; });
      return ids;
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/ordreliste.php'] = d1Ordreliste;

  // ---- Kundeordre (debitor/ordre.php)
  // Evidence, debitor/ordre.php unless named. One page for tilbud, ordre, faktura and kreditnota, form[name="ordre"]:
  // the invoiced view (status >= 3, :3938-4693) and the open view (:4720-6560). Hidden status, id and art come first in
  // the form (:3983-3986, :4796-4799); later status inputs carry adjusted values (:5817, :6402), so the first counts.
  // Without a customer the order has no id: choosing one makes it (:870-900). Gem or Enter in a customer field, or a
  // number that is no account, shows the customer lookup kontoopslag() on this route
  // (includes/orderFuncIncludes/accountLookup.php:110-306, grid accountLkUp_<nr>; the form Opret ny kunde :475-540,
  // shown when nothing matches :380-389; created with create_debtor, ordre.php:356-397). A new tenant has
  // hurtigfakturering and omgående bogføring of sales on (includes/opretfunc.php:718, DIV 3 box4 and box5): no tilbud,
  // no Levér, and Fakturér delivers and invoices (:3268-3312; levering.php; bogfor.php). Without it a tilbud has Godkend
  // (:5348-5349), an order Restordre (:5350-5355), Lev. dato (:5124-5131) and Levér (:6418-6421).
  // Buttons (:6398-6560): Gem g (:6409), Opslag o (:6413), Levér/Modtag l (:6418-6421), Fakturér f (status 2, or > 0
  // with hurtigfakturering, :6440-6478), Kreditér on a credit note (:6479-6493), Del ordre after a part delivery
  // (:6181-6182, :6494-6499), Udskriv/Send (:6500-6515), Kopier (:6517), Slet (:6527-6530). Invoiced (:4673-4693):
  // Kopier, Udskriv/Send, PBS, Kredit until credited. The title bar (includes/orderFuncIncludes/topLine.php:40-61) has
  // Tilbage (L) and Ny (N); the title links a credit note and its invoice (:3796-3803, :3874-3884).

  const D1_OR = 'dord';

  /** The order form (open :4764, invoiced :3948). */
  function d1Form(doc) {
    return d1q(doc, 'form[name="ordre"]');
  }

  /** The first value of a named control of the form, '' when missing. */
  function d1Named(form, name) {
    return d1val(form ? d1q(form, '[name="' + name + '"]') : null);
  }

  /** Kredit on an invoice (value 1001), Kreditér on an open credit note (hard-coded): a b_submit that is not OK or Del ordre. */
  function d1Kredit(doc) {
    return d1qa(doc, 'form[name="ordre"] input[name="b_submit"]').filter(function (el) {
      return /^(kred|cred)/i.test(attr(el, 'value'));
    })[0] || null;
  }

  /** A target only in the states listed. */
  function d1InState(states, find) {
    return function (ctx, st) {
      return st && states.indexOf(st.state) >= 0 ? find(ctx, st) : null;
    };
  }

  /** The first saved line (data-line-id 1); the new line takes the next number, so only when there are lines (:6941, :6248). */
  function d1FirstLine(ctx, st) {
    const n = st && st.data ? st.data.linjer : 0;
    return n > 0 ? d1q(ctx.doc, 'tr.ordrelinje[data-line-id="1"]') : null;
  }

  /** Udskriv or Send (open :6500-6515, invoiced :4679-4683). */
  function d1PrintSend(ctx) {
    return d1q(ctx.doc, 'form[name="ordre"] input[name="print"], form[name="ordre"] input[name="send"]');
  }

  /** Udskriv or Send in the states of the task only: a tilbud, an open order, a finished invoice or credit note (spec §19.3). */
  function d1PrintIn(states) {
    return { target: d1InState(states, d1PrintSend), label: showKey(D1_OR, 'OR7'), kind: 'button' };
  }

  const D1_OR_OPEN = ['tilbud', 'ordre', 'kreditnota'];
  const D1_OR_SAVED = ['tilbud', 'ordre', 'kreditnota', 'faktura', 'kreditnotaFaktureret'];

  const D1_OR_TASKS = {
    // A number that is an account or a phone sets the customer; a name in Firmanavn, or an empty Kontonr., opens the
    // lookup (:3122-3143; accountLookup.php:85-108); the order takes address, terms and VAT from the card (:790-845)
    OR1: task(D1_OR, 'OR1', 3, {
      target: 'form[name="ordre"] input[name="kontonr"]:not([readonly]):not([type="hidden"])',
      label: fieldLabel(D1_OR, 'kontonr'),
      kind: 'field'
    }),
    // Opret ny kunde opens by itself when nothing matches (accountLookup.php:380-389; grid_account_lookup.php:1930-1942);
    // Navn is required (:544-552); Opret makes the customer and puts it on the order (ordre.php:356-397)
    OR2: task(D1_OR, 'OR2', 3, { target: 'form[name="create_debtor"] input[name="create_debtor"]', label: showKey(D1_OR, 'OR2'), kind: 'button' }),
    // The lookup searches as you type (grid_account_lookup.php:2092-2104); a row selects (:1946-1952)
    OR3: task(D1_OR, 'OR3', 3, { target: '[id^="datatable-accountLkUp"] input[name$="[firmanavn]"]', label: fieldLabel(D1_OR, 'search:firmanavn'), kind: 'field' }),
    // The new line vare0/dkan0/beskrivelse0/pris0 (:6244-6283), only with a customer and not on a credit note; Opslag with
    // the focus in Varenr. opens productLookup.php (:3162-3170); a text-only line (:2443-2444)
    OR4: task(D1_OR, 'OR4', 4, { target: 'form[name="ordre"] input[name="vare0"]:not([readonly])', label: fieldLabel(D1_OR, 'vare'), kind: 'field' }),
    // Saved lines are editable (readonly '0', :6030); the red x asks and sets - in Pos. (:7206-7222); Pos. order (title 1477)
    OR5: task(D1_OR, 'OR5', 3, { target: d1InState(D1_OR_OPEN, d1FirstLine), label: showKey(D1_OR, 'OR5'), kind: 'row' }),
    // Fakturér: the date (:3268-3272; orderIncludes/defaultInvoiceDate.php), the confirm for e-mail (:6447-6451), then
    // levering.php and bogfor.php (:3283-3311); bogfor() numbers the invoice and posts it with Omgående bogføring
    // (includes/ordrefunc.php:1707-1753, :2459-3290); it prints or sends by Udskriv til (debitor/bogfor.php:65-130)
    OR6: task(D1_OR, 'OR6', 4, { target: 'form[name="ordre"] input[name="doInvoice"]', label: showKey(D1_OR, 'OR6'), kind: 'button' }),
    // Udskriv til is read when the page is drawn: Gem first, then the button is Send for e-mail (:1021, :6500-6515);
    // status 0 prints the tilbud (:2784-2788)
    OR7: task(D1_OR, 'OR7', 3, d1PrintIn(['tilbud'])),
    // Godkend + Gem: status 1, the quantities are set to deliver, Lev. dato today when empty (:1648-1652, :1722-1735)
    // A credit note without hurtigfakturering has Godkend too (:5349), so only on a tilbud
    OR8: task(D1_OR, 'OR8', 3, {
      target: d1InState(['tilbud'], function (ctx) { return d1q(ctx.doc, 'form[name="ordre"] input[name="godkend"]'); }),
      label: fieldLabel(D1_OR, 'godkend'),
      kind: 'field'
    }),
    // Levér with the quantities in the Levér column (:5803-5811, :7153-7190; titles 1484, 1495); Følgeseddel per
    // delivery (:4291-4306); stock moves in levering() (includes/ordrefunc.php)
    OR9: task(D1_OR, 'OR9', 4, { target: 'form[name="ordre"] input[name="deliver"]', label: showKey(D1_OR, 'OR9'), kind: 'button' }),
    // Del ordre: a new order with the same number takes what is not delivered (:3189-3266, alert 1845)
    OR10: task(D1_OR, 'OR10', 3, { target: 'form[name="ordre"] input[name="b_submit"][value="Del ordre"]', label: showKey(D1_OR, 'OR10'), kind: 'button' }),
    // Udskriv on an order: ordrebekræftelse, plukliste or følgeseddel by status (:2761-2786); Print plukliste (:6540-6545)
    OR11: task(D1_OR, 'OR11', 3, d1PrintIn(['ordre'])),
    // Kredit (value 1001) only on an invoice that is not credited (:4688-4692): a credit note order with the lines
    // (:2622-2700, :1737-1751); a full credit settles the invoice's open item (ordrefunc.php:2713-2722)
    OR12: task(D1_OR, 'OR12', 4, { target: d1InState(['faktura'], function (ctx) { return d1Kredit(ctx.doc); }), label: showKey(D1_OR, 'OR12'), kind: 'button' }),
    // Kreditér (hard-coded, :6492) goes the invoice way (:1291-1293); without hurtigfakturering Godkend and Modtag first
    OR13: task(D1_OR, 'OR13', 4, { target: d1InState(['kreditnota'], function (ctx) { return d1Kredit(ctx.doc); }), label: showKey(D1_OR, 'OR13'), kind: 'button' }),
    // Kopier: a new order for the same customer with the lines, opened at once (:2622-2758)
    // A new order without a customer has Kopier and Debitorkort too (live check), with nothing to copy or open yet
    OR14: task(D1_OR, 'OR14', 3, { target: d1InState(D1_OR_SAVED, function (ctx) { return d1q(ctx.doc, 'form[name="ordre"] input[name="copy"]'); }), label: showKey(D1_OR, 'OR14'), kind: 'button' }),
    // Slet needs count($leveret) == 0 and no payment (:6526-6528), and $leveret holds one entry per line (:5853, :5986),
    // so only an order without lines has it; slet_ordre() (:1677-1686; ordrefunc.php:5851-5875)
    OR15: task(D1_OR, 'OR15', 2, { target: 'form[name="ordre"] input[name="delete"]', label: showKey(D1_OR, 'OR15'), kind: 'button' }),
    // Skift opens newAccountNo (:4884-4902); a number, phone or name sets the customer or opens the lookup (:1066-1100)
    OR16: task(D1_OR, 'OR16', 3, { target: 'a[href*="swap_account=swap"]', label: showKey(D1_OR, 'OR16'), kind: 'button' }),
    // Momssats comes from the debitor group's VAT code (:811-825) and is saved with the order (:1230, :2477); the moms
    // is the net times it (:6316-6320)
    OR17: task(D1_OR, 'OR17', 3, { target: 'form[name="ordre"] input[name="momssats"]:not([type="hidden"])', label: fieldLabel(D1_OR, 'momssats'), kind: 'field' }),
    // Digitalt (:5056); the CVR popup for an EAN without CVR (orderIncludes/einvoiceCvr.php; :6593-6609); NemHandel
    // sign-up at the first digital invoice (debitor/bogfor.php:88-104)
    OR18: task(D1_OR, 'OR18', 3, { target: 'form[name="ordre"] select[name="udskriv_til"]', label: fieldLabel(D1_OR, 'udskriv_til'), kind: 'field' }),
    // Invoiced: Udskriv til and E-mail submit on change (:4081-4083, :4118); Udskriv or Send (:4679-4683)
    OR19: task(D1_OR, 'OR19', 3, d1PrintIn(['faktura', 'kreditnotaFaktureret'])),
    // Kreditloft overskrides med (:6561-6591), from Kreditmax on the card (debitorkort.php:1925-1926)
    OR20: task(D1_OR, 'OR20', 3, { target: 'big > span[style*="FF0000"]', label: showKey(D1_OR, 'OR20'), kind: 'status' }),
    // Red labels with the card's value as title; the arrows run sync_stamkort.php up or down (:4905-4990, :4977-4988)
    OR21: task(D1_OR, 'OR21', 3, { target: 'a[href*="sync_stamkort.php"][href*="retning=ned"]', label: showKey(D1_OR, 'OR21'), kind: 'button' }),
    // The title links the credit note and its invoice (:3796-3803, :3874-3884)
    OR22: task(D1_OR, 'OR22', 2, { target: 'a[href^="ordre.php?id="][style*="color:white"]', label: showKey(D1_OR, 'OR22'), kind: 'button' }),
    // Debitorkort (:3832-3835)
    OR23: task(D1_OR, 'OR23', 2, { target: d1InState(D1_OR_SAVED, function (ctx) { return d1q(ctx.doc, '#debitorkort'); }), label: showKey(D1_OR, 'OR23'), kind: 'button' })
  };

  const D1_OR_FIELDS = {};
  ['kontonr', 'firmanavn', 'kundeordnr', 'cvrnr', 'ean', 'email', 'udskriv_til', 'momssats', 'ordredato', 'levdato',
    'fakturadato', 'genfakt', 'betalingsbet', 'betalingsdage', 'ref', 'performed_by', 'ny_valuta', 'godkend', 'restordre',
    'posn', 'vare', 'dkan', 'beskrivelse', 'pris', 'raba', 'leve', 'mail_subj', 'mail_text', 'search:firmanavn', 'search:tlf']
    .forEach(function (k) { D1_OR_FIELDS[k] = fieldKeys(D1_OR, k); });

  const d1Ordre = {
    group: D1_OR,
    states: ['ny', 'kundeopslag', 'tilbud', 'ordre', 'kreditnota', 'faktura', 'kreditnotaFaktureret'],
    flags: ['hurtigfakt', 'ingenLinjer', 'kanFaktureres', 'kanLeveres', 'delOrdre', 'kanSlettes', 'email', 'digital', 'ean',
      'nulMoms', 'kreditloft', 'ikkeGemt', 'krediteret', 'ikkeBogfoert', 'afvigerFraKort', 'forbundet', 'opret', 'gemFoerst'],

    name: function (ctx, st) {
      const state = st ? st.state : '';
      const nr = st && st.data ? String(st.data.ordrenr || '') : '';
      if (state === 'ny') return 'help.dord.name_ny';
      if (state === 'kundeopslag') return 'help.dord.name_opslag';
      if (!nr) return 'help.dord.name_plain';
      if (state === 'tilbud') return ['help.dord.name_tilbud', nr];
      if (state === 'kreditnota' || state === 'kreditnotaFaktureret') return ['help.dord.name_kn', nr];
      if (state === 'faktura') {
        const fnr = st && st.data ? String(st.data.fakturanr || '') : '';
        return fnr ? ['help.dord.name_faktura', fnr] : ['help.dord.name', nr];
      }
      return ['help.dord.name', nr];
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const form = d1Form(doc);
      let state = 'unknown';
      const status = form ? (parseInt(d1Named(form, 'status'), 10) || 0) : 0;
      const art = d1Named(form, 'art');
      // A new order prints id 0 (live check: ordre.php?ordre_id=0 from Ny), a saved one its number
      const oid = d1Named(form, 'id');
      if (d1q(doc, '[id^="datatable-accountLkUp"], form[name="create_debtor"]')) state = 'kundeopslag';
      else if (form) {
        if (status >= 3) state = art === 'DK' ? 'kreditnotaFaktureret' : 'faktura';
        else if (!oid || oid === '0') state = 'ny';
        else if (art === 'DK') state = 'kreditnota';
        else if (d1q(form, 'input[name="godkend"]')) state = 'tilbud';
        else state = 'ordre';
      }
      const open = D1_OR_OPEN.indexOf(state) >= 0;
      const linjer = parseInt(d1Named(form, 'linjeantal'), 10) || 0;
      const ut = d1val(d1q(doc, 'form[name="ordre"] select[name="udskriv_til"]'));
      let opret = false;
      const cf = d1q(doc, '#createCustomerForm');
      if (cf) {
        try { opret = ctx.win.getComputedStyle(cf).display === 'block'; } catch (e) { opret = false; }
      }
      const flags = {
        // No Lev. dato input on the open order (:5124-5131)
        hurtigfakt: (open || state === 'ny') && !d1q(form, 'input[name="levdato"][type="text"]'),
        ingenLinjer: open && linjer === 0,
        kanFaktureres: open && (!!d1q(form, 'input[name="doInvoice"]:not([disabled])') || (state === 'kreditnota' && !!d1Kredit(doc))),
        kanLeveres: open && !!d1q(form, 'input[name="deliver"], input[name="receive"]'),
        delOrdre: open && !!d1q(form, 'input[name="b_submit"][value="Del ordre"]'),
        kanSlettes: open && !!d1q(form, 'input[name="delete"]'),
        email: ut === 'email',
        digital: ut === 'Digitalt' || ut === 'oioubl',
        ean: d1val(d1q(doc, 'form[name="ordre"] input[name="ean"]')) !== '',
        nulMoms: open && state !== 'kreditnota' && linjer > 0 && d1num(d1Named(form, 'momssats')) === 0,
        kreditloft: open && !!d1q(doc, 'big > span[style*="FF0000"]'),
        ikkeGemt: !!ctx.win && ctx.win.docChange === true,
        krediteret: state === 'faktura' && !d1Kredit(doc),
        ikkeBogfoert: status === 3,
        afvigerFraKort: open && !!d1q(doc, 'a[href*="sync_stamkort.php"]'),
        forbundet: !!d1q(doc, 'a[href^="ordre.php?id="][style*="color:white"]'),
        opret: state === 'kundeopslag' && opret,
        // Quick invoicing leaves a new order or credit note at status 0 when its lines are added; Fakturér or Kreditér
        // is printed from status 1, which the next Gem gives (ordre.php:1722, :6440-6493; live check in test_838)
        gemFoerst: (state === 'ordre' || state === 'kreditnota') && status === 0 && linjer > 0 &&
          !d1q(form, 'input[name="doInvoice"]') && !d1Kredit(doc) && !d1q(form, 'input[name="levdato"][type="text"]')
      };
      // ordrenr (:3983, :4796); fakturanr only on the invoiced view (:4021); linjeantal on the open view (:6179)
      return { state: state, flags: flags, data: { ordrenr: d1Named(form, 'ordrenr'), fakturanr: d1Named(form, 'fakturanr'), linjer: linjer, status: status } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const out = [];
      if (state === 'ny') out.push('help.dord.about.ny');
      else if (state === 'kundeopslag') out.push(f.opret ? 'help.dord.about.opret' : 'help.dord.about.opslag');
      else if (state === 'tilbud') out.push('help.dord.about.tilbud');
      else if (state === 'ordre') out.push(f.hurtigfakt ? 'help.dord.about.ordre_hurtig' : 'help.dord.about.ordre');
      else if (state === 'kreditnota') out.push('help.dord.about.kreditnota');
      else if (state === 'faktura') out.push('help.dord.about.faktura');
      else if (state === 'kreditnotaFaktureret') out.push('help.dord.about.kn_faktureret');
      else return out;
      // Gem exists only on the open view (:6409); a credit note takes no new items (vare0 read-only, :6254)
      const open = D1_OR_OPEN.indexOf(state) >= 0;
      if (f.ikkeGemt && (open || state === 'ny')) out.push('help.dord.about.ikke_gemt');
      else if (f.gemFoerst) out.push(state === 'kreditnota' ? 'help.dord.about.gem_kreditnota' : 'help.dord.about.gem_faktura');
      else if (f.ingenLinjer && open && state !== 'kreditnota') out.push('help.dord.about.ingen_linjer');
      else if (f.krediteret && state === 'faktura') out.push('help.dord.about.krediteret');
      else if (f.ikkeBogfoert && (state === 'faktura' || state === 'kreditnotaFaktureret')) out.push('help.dord.about.ikke_bogfoert');
      return out;
    },

    tasks: D1_OR_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'ny') return ['OR1', 'OR2'];
      if (state === 'kundeopslag') return f.opret ? ['OR2', 'OR3'] : ['OR3', 'OR2'];
      if (state === 'faktura') {
        const ids = ['OR19', f.krediteret ? 'OR22' : 'OR12', 'OR14'];
        if (f.ean && !f.digital) ids.push('OR18');
        ids.push('OR23');
        if (f.forbundet) ids.push('OR22');
        return unique(ids);
      }
      if (state === 'kreditnotaFaktureret') return ['OR19', 'OR22', 'OR23'];
      if (D1_OR_OPEN.indexOf(state) < 0) return [];
      const ids = [];
      if (f.kreditloft) ids.push('OR20');           // the warning on the page (problem card)
      if (state === 'kreditnota') {
        ids.push('OR13', 'OR5');
        if (f.forbundet) ids.push('OR22');
        if (f.kanSlettes) ids.push('OR15');
        ids.push('OR23');
        return unique(ids);
      }
      if (f.ingenLinjer) {
        ids.push('OR4', 'OR16', 'OR23');
        if (f.kanSlettes) ids.push('OR15');
        return unique(ids);
      }
      // Before Fakturér: a 0 % VAT rate, an EAN customer without Digitalt
      if (f.nulMoms) ids.push('OR17');
      if (f.ean && !f.digital) ids.push('OR18');
      if (state === 'tilbud') ids.push('OR7', 'OR8', 'OR4');
      else {
        if (f.delOrdre) ids.push('OR10');
        if (f.kanLeveres) ids.push('OR9');
        if (f.kanFaktureres || f.gemFoerst) ids.push('OR6');
        ids.push('OR4', 'OR11');
      }
      ids.push('OR5');
      if (f.afvigerFraKort) ids.push('OR21');
      ids.push('OR14', 'OR16', 'OR23');
      if (f.kanSlettes) ids.push('OR15');
      if (f.ean) ids.push('OR18');
      return unique(ids);
    },

    // A credit limit exceeded is printed in red on the open order (:6587-6589): the panel shows it first (spec §18)
    problem: function (ctx, st) {
      const state = st ? st.state : '';
      const f = (st && st.flags) || {};
      return D1_OR_OPEN.indexOf(state) >= 0 && f.kreditloft ? 'OR20' : '';
    },

    fields: D1_OR_FIELDS,

    // Skift shows newAccountNo instead of Kontonr. (:4886-4889)
    fieldKey: function (el) {
      return attr(el, 'name') === 'newAccountNo' ? 'kontonr' : null;
    },

    // Access keys: Gem g, Opslag o, Fakturér/Kreditér/Del ordre f (:6409-6499), Ny N in the title bar
    // (orderFuncIncludes/topLine.php:58-61). Alt+L is both Tilbage (topLine.php:40-48) and Levér/Modtag (:6418-6421),
    // so it is left out. Enter submits the form with its first submit, Gem (:6409). Ctrl+arrows move between the line
    // fields (javascript/tablenav.js); arrows, Enter and Esc in the suggestion list of Kontonr., Varenr. and Beskrivelse
    // (javascript/ordreAutocomplete.js:57-67, :128-160; setting ordreAutocomplete, on by default, :7426).
    shortcuts: [
      { keys: 'Enter', text: 'help.dord.key.01', group: 'keys' },
      { keys: 'Alt+G', text: 'help.dord.key.02', group: 'keys' },
      { keys: 'Alt+O', text: 'help.dord.key.03', group: 'keys' },
      { keys: 'Alt+F', text: 'help.dord.key.04', group: 'keys' },
      { keys: 'Alt+N', text: 'help.dord.key.05', group: 'keys' },
      { keys: 'Ctrl+← → ↑ ↓', text: 'help.dord.key.06', group: 'keys' },
      { keys: '↑ ↓, Enter, Esc', text: 'help.dord.key.07', group: 'keys' },
      // Codes in Pos. (title 1477; :7219) and Varenr. (title 1478)
      { keys: '-', text: 'help.dord.key.08', group: 'codes' },
      { keys: '1,5', text: 'help.dord.key.09', group: 'codes' },
      { keys: 't*', text: 'help.dord.key.10', group: 'codes' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.dord.q.q1.q',
        words: d1Words(['ret', 'rette', 'fejl', 'forkert', 'faktura', 'kreditnota', 'krediter', 'kreditere', 'annuller', 'slet faktura', 'tilbagefør'],
          ['correct', 'error', 'mistake', 'wrong', 'invoice', 'credit note', 'credit', 'cancel', 'delete invoice', 'reverse'],
          ['rett', 'rette', 'feil', 'faktura', 'kreditnota', 'krediter', 'kreditere', 'annuller', 'slett faktura', 'tilbakefør']),
        intro: 'help.dord.q.q1.intro',
        steps: stepKeys(D1_OR, 'OR12', 1, 4),
        show: D1_OR_TASKS.OR12.show
      },
      Q2: {
        q: 'help.dord.q.q2.q',
        words: d1Words(['genfakturer', 'genfakturering', 'abonnement', 'fast', 'hver måned', 'månedlig', 'kvartal', 'gentag', 'løbende'],
          ['re-invoice', 'recurring', 'subscription', 'every month', 'monthly', 'quarterly', 'repeat'],
          ['gjenfakturer', 'gjenfakturering', 'abonnement', 'fast', 'hver måned', 'månedlig', 'kvartal', 'gjenta', 'løpende']),
        steps: ['help.dord.q.q2.step1', 'help.dord.q.q2.step2', 'help.dord.q.q2.step3', 'help.dord.q.q2.step4'],
        show: { target: 'form[name="ordre"] input[name="genfakt"]:not([type="hidden"])', label: fieldLabel(D1_OR, 'genfakt'), kind: 'field' }
      },
      Q3: {
        q: 'help.dord.q.q3.q',
        words: d1Words(['fakturér', 'fakturer', 'fakturere', 'knap', 'mangler', 'kan ikke', 'grå', 'levér', 'godkend', 'del ordre'],
          ['invoice', 'button', 'missing', 'cannot', 'grey', 'deliver', 'approve', 'split order'],
          ['fakturer', 'fakturere', 'knapp', 'mangler', 'kan ikke', 'grå', 'levere', 'godkjenn', 'del ordre']),
        steps: ['help.dord.q.q3.step1', 'help.dord.q.q3.step2', 'help.dord.q.q3.step3', 'help.dord.q.q3.step4']
      },
      Q4: {
        q: 'help.dord.q.q4.q',
        words: d1Words(['mail', 'e-mail', 'email', 'send', 'sende', 'pdf', 'udskriv', 'udskriv til', 'emne'],
          ['mail', 'e-mail', 'email', 'send', 'pdf', 'print', 'print to', 'subject'],
          ['mail', 'e-post', 'epost', 'send', 'sende', 'pdf', 'skriv ut', 'emne']),
        steps: ['help.dord.q.q4.step1', 'help.dord.q.q4.step2', 'help.dord.q.q4.step3', 'help.dord.q.q4.step4'],
        show: { target: 'form[name="ordre"] input[name="email"]:not([type="hidden"])', label: fieldLabel(D1_OR, 'email'), kind: 'field' }
      },
      // A payment is posted in the kassekladde, never on the invoice: the customer in Kredit with D, the invoice number
      // in Fakturanr. (finans/kassekladde.php:3257, as OL6); posting settles the open item when the amount matches,
      // several invoices separated by ; settle when they and the payment net to zero (finans/bogfor.php:1172-1215).
      // The invoice view has no paid mark, so there is no element to ring here.
      Q5: {
        q: 'help.dord.q.q5.q',
        words: d1Words(['betalt', 'betaling', 'indbetaling', 'registrere', 'registrer', 'markere', 'bogfør betaling', 'udlign', 'udligne', 'kunden har betalt'],
          ['paid', 'payment', 'record payment', 'mark as paid', 'settle', 'customer paid'],
          ['betalt', 'betaling', 'innbetaling', 'registrere', 'markere', 'bokfør betaling', 'utlign', 'utligne', 'kunden har betalt']),
        steps: ['help.dord.q.q5.step1', 'help.dord.q.q5.step2', 'help.dord.q.q5.step3', 'help.dord.q.q5.step4']
      },
      // The lookup leaves out closed customers (accountLookup.php:163-165), and a closed customer's number in Kontonr.
      // gives the alert Konto <nr> er lukket and no customer (ordre.php:1760-1768); Lukket on the card (debitorkort.php:1942-1985)
      Q6: {
        q: 'help.dord.q.q6.q',
        words: d1Words(['finde kunde', 'kan ikke finde', 'findes ikke', 'kunde mangler', 'lukket', 'er lukket', 'søg kunde', 'opslag'],
          ['find customer', 'cannot find', 'not found', 'customer missing', 'closed', 'is closed', 'search customer', 'lookup'],
          ['finne kunde', 'finner ikke', 'finnes ikke', 'kunde mangler', 'lukket', 'er stengt', 'søk kunde', 'oppslag']),
        steps: ['help.dord.task.or1.step2', 'help.dord.q.q6.step2', 'help.dord.q.q6.step3', 'help.dord.task.or3.step3'],
        show: D1_OR_TASKS.OR3.show
      },
      // A paid invoice credited leaves the credit note as an open item (OR13); a payout with the customer in Debet and the
      // credit note's number in Fakturanr. settles it when the amounts net to zero (finans/bogfor.php:1172-1215, the
      // debit side of the payment rule in Q5); a full credit before payment settles the invoice (ordrefunc.php:2713-2722)
      Q7: {
        q: 'help.dord.q.q7.q',
        words: d1Words(['tilbagebetal', 'tilbagebetaling', 'betale tilbage', 'refusion', 'penge tilbage', 'udbetaling', 'til gode', 'kreditnota'],
          ['refund', 'pay back', 'repay', 'money back', 'payout', 'credit balance', 'credit note'],
          ['tilbakebetal', 'tilbakebetaling', 'betale tilbake', 'refusjon', 'penger tilbake', 'utbetaling', 'til gode', 'kreditnota']),
        intro: 'help.dord.q.q7.intro',
        steps: ['help.dord.q.q7.step1', 'help.dord.q.q7.step2', 'help.dord.task.or13.step4']
      }
    },

    // The state's own questions (spec §25): finding the customer before there is one, Fakturér on an open order, the
    // correction and the payment on an invoice, the refund on a credit note. On an invoice OR19 already resends it.
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      if (state === 'faktura') return ['Q1', 'Q5', 'Q2'];
      if (state === 'kreditnota') return ['Q1', 'Q3', 'Q7'];
      if (state === 'kreditnotaFaktureret') return ['Q7', 'Q4', 'Q1'];
      if (state === 'ny' || state === 'kundeopslag') return ['Q6', 'Q3', 'Q4'];
      return ['Q3', 'Q4', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/ordre.php'] = d1Ordre;

  // ==== Customers: the list, the card, the CRM, job cards and the merge (author D2; helpers d2)
  // ================================================================== D2: Debitor accounts
  // The customer list and its views (Debitorer, Kommission), the customer card, the CRM (which replaced the customer
  // history), the job cards and the account merge. Plain functions on top of the shared helpers; detectors and show
  // targets only read the frame document. Evidence per selector in the section comments (file:line in the worktree).

  /** querySelector / querySelectorAll that never throw (an odd document or selector gives null / []). */
  function d2Q(root, sel) {
    try {
      const el = root && root.querySelector ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function d2Qa(root, sel) {
    try {
      return root && root.querySelectorAll ? Array.prototype.slice.call(root.querySelectorAll(sel) || []) : [];
    } catch (e) {
      return [];
    }
  }

  function d2Text(el) {
    return el && el.nodeType === 1 ? String(el.textContent || '') : '';
  }

  function d2Value(el) {
    return el && el.nodeType === 1 ? String(el.value || '').trim() : '';
  }

  /** The saved state of a checkbox: the checked attribute the page printed, not what the user ticked since. */
  function d2Saved(el) {
    if (!el || el.nodeType !== 1) return false;
    try {
      return el.defaultChecked === true || el.getAttribute('checked') !== null;
    } catch (e) {
      return false;
    }
  }

  function d2Param(ctx, name) {
    try {
      return String(ctx.url.searchParams.get(name) || '');
    } catch (e) {
      return '';
    }
  }

  /** A named control of a form (form.elements, then the document): the classic cards close their form early, so
   *  most controls are form-associated without being its DOM children (debitorkort.php:2526-2529). */
  function d2Control(doc, formSel, name) {
    const form = d2Q(doc, formSel);
    let el = null;
    try {
      el = form && form.elements && form.elements.namedItem ? form.elements.namedItem(name) : null;
      if (el && el.nodeType !== 1) el = el[0];
    } catch (e) {
      el = null;
    }
    if (!(el && el.nodeType === 1)) el = byName(doc, name);
    return el && el.nodeType === 1 ? el : null;
  }

  /** The value of a select's first option: the classic cards print the saved value first (debitorkort.php:1516-1523). */
  function d2FirstOption(sel) {
    const o = d2Q(sel, 'option');
    if (!o) return '';
    const v = o.getAttribute('value');
    return String(v !== null ? v : d2Text(o)).trim();
  }

  function d2Words(da, en, no) {
    return { da: da, en: en, no: no };
  }

  // ---- Debitorer og Kommission (debitor/debitor.php, debitor/debitor_kommission.php)
  // Evidence: sidebar Debitor > Konti (index/main.php:279-281); the list's top line (debLstIncludes/topLine.php: #debitore
  // :44-53, Historik debitor_historik.php :57-67, #kommission when Mit salg is on :68-78, #tutorial-help :105-110, Ny
  // #opret-ny > a[href^=debitorkort.php] :129-134, Mailtekst instead of Ny on Kommission :112-117). The Historik tab is
  // printed for every user, but debitor_historik.php and debitor.php?valg=historik only redirect to the CRM's Aktivitet
  // tab, behind crm.access (debitor_historik.php:27-28, debitor.php:62-65, includes/crmLegacyRoute.php:21-47), so the
  // list has no Historik view any more: DB18 leads there. debitor.php?valg=kommission includes the same files
  // (debitor.php:107-114), so one page object serves both routes. Each view is a grid (includes/grid.php):
  // #datatable-wrapper-<id> in every mode (:1264, :1636, :2030), ids debitor_list (debitor.php:727), debitor_kommission
  // (debitor_kommission.php:623); search inputs search[<id>][<field>] (:1396), the actions menu .dropdown-trigger in the
  // search row with Søg, Ryd søgning, Eksportér til CSV/PDF, Redigér > Kolonner/Filtre (:1419-1460), the footer
  // #page-status and rowcount[<id>] (:1549-1559), one tr.filler-row (:1307). Kolonner: rows[<id>][n][field] (:1793, :1889),
  // Filtre: filter[<id>][group][option] (:2094). Rows: kontonr link to debitorkort.php, every cell onclick (debitor.php:
  // 594-613); Kommission rows open Mit salg in a new tab or the card (debitor_kommission.php:438-488), meta columns Aktiv
  // mySale[] and Inviter invite[] (disabled without e-mail, :600-610), OK name=kommission and Vælg alle name=chooseAll
  // (:634-636). The export takes the rows on the page (grid.php:2540-2600). Vis udgået is a filter (debitor.php:617-629).
  // Closed customers are not marked in the list: the red row style reads lukket, which the query never selects
  // (debitor.php:700-705, defect list).

  const D2_GRIDS = ['debitor_list', 'debitor_kommission'];

  /** The grid on the page, or ''. */
  function d2GridId(doc) {
    for (let i = 0; i < D2_GRIDS.length; i++) {
      if (d2Q(doc, '#datatable-wrapper-' + D2_GRIDS[i])) return D2_GRIDS[i];
    }
    return '';
  }

  function d2GridRows(doc, id) {
    return id ? d2Qa(doc, '#datatable-' + id + ' tbody tr:not(.filler-row)') : [];
  }

  /** The grid's search fields with text. */
  function d2SearchesWithText(doc, id) {
    return d2Qa(doc, 'input[name^="search[' + id + ']"]').filter(function (el) { return d2Value(el) !== ''; });
  }

  /** The name search field of the grid on the page (grid.php:1396). */
  function d2NameSearch(ctx) {
    const id = d2GridId(ctx.doc);
    return id ? d2Q(ctx.doc, 'input[name="search[' + id + '][firmanavn]"]') : null;
  }

  function d2FirstSearchWithText(ctx) {
    const list = d2SearchesWithText(ctx.doc, d2GridId(ctx.doc));
    return list.length ? list[0] : null;
  }

  /** The arrow that opens the grid's actions menu (grid.php:1419-1422). */
  function d2Trigger(ctx) {
    const id = d2GridId(ctx.doc);
    return id ? d2Q(ctx.doc, '#datatable-wrapper-' + id + ' .dropdown-trigger') : null;
  }

  /** The first row of one grid, else null: a show target that rings nothing in the other views, nor in the grid's
   *  Kolonner and Filtre forms, whose first row is a setting (live check). */
  function d2FirstRowOf(id) {
    return function (ctx) {
      if (d2Q(ctx.doc, '[name^="rows[' + id + ']"], [name^="filter[' + id + ']"]')) return null;
      const rows = d2GridRows(ctx.doc, id);
      return rows.length ? rows[0] : null;
    };
  }

  /** The first Inviter box that can be ticked, else the first one (debitor_kommission.php:603-607). */
  function d2InviteBox(ctx) {
    return d2Q(ctx.doc, 'input[name="invite[]"]:not([disabled])') || d2Q(ctx.doc, 'input[name="invite[]"]');
  }

  const D2_DEB = 'deb';
  const D2_DEB_TASKS = {
    DB1: task(D2_DEB, 'DB1', 3, { target: d2NameSearch, label: fieldLabel(D2_DEB, 'search:firmanavn'), kind: 'field' }),
    DB2: task(D2_DEB, 'DB2', 3, { target: '#opret-ny a[href^="debitorkort.php"]', label: showKey(D2_DEB, 'DB2'), kind: 'button' }),
    // The second step is help.deb.task.db3.step3: the old step2 named Historik for every user, and a reworded keyed text
    // gets a new key, because findtekst() keeps serving the row a tenant has cached (tools/guardrails/checks/tekster.mjs)
    DB3: task(D2_DEB, 'DB3', 1, { target: d2FirstRowOf('debitor_list'), label: showKey(D2_DEB, 'DB3'), kind: 'row' }, ['help.deb.task.db3.step3']),
    DB4: task(D2_DEB, 'DB4', 3, { target: d2Trigger, label: showKey(D2_DEB, 'DB4'), kind: 'button' }),
    DB5: task(D2_DEB, 'DB5', 2, { target: d2Trigger, label: showKey(D2_DEB, 'DB4'), kind: 'button' }, stepKeys(D2_DEB, 'DB4', 3, 3)),
    DB6: task(D2_DEB, 'DB6', 3, { target: d2Trigger, label: showKey(D2_DEB, 'DB4'), kind: 'button' }),
    DB7: task(D2_DEB, 'DB7', 2, { target: d2FirstSearchWithText, label: showKey(D2_DEB, 'DB7'), kind: 'field' }),
    DB8: task(D2_DEB, 'DB8', 4),          // Indstillinger > Finans > Data og sikkerhedskopi > Import og eksport (settingsDefinitions.php:1054)
    DB10: task(D2_DEB, 'DB10', 2, { target: 'input[name="mySale[]"]', label: showKey(D2_DEB, 'DB10'), kind: 'field' }),
    DB11: task(D2_DEB, 'DB11', 3, { target: d2InviteBox, label: showKey(D2_DEB, 'DB11'), kind: 'field' }),
    DB12: task(D2_DEB, 'DB12', 2, { target: d2FirstRowOf('debitor_kommission'), label: showKey(D2_DEB, 'DB3'), kind: 'row' }),
    // DB14's successor (new keys, as DB3's step): the Historik tab opens the CRM's Aktivitet tab, where Ny note picks the
    // customer (topLine.php:57-67, includes/crmLegacyRoute.php:23-47, javascript/crm.js NEW_FOR and newFollowUp())
    DB18: task(D2_DEB, 'DB18', 2, { target: 'a[href^="debitor_historik.php"]', label: showKey(D2_DEB, 'DB18'), kind: 'button' }),
    // DB15 starts with DB4's second step, the empty line under Felt (grid.php:1883-1893)
    DB15: task(D2_DEB, 'DB15', 0, { target: 'select[name^="rows["][name$="[field]"]', label: showKey(D2_DEB, 'DB15'), kind: 'field' },
      stepKeys(D2_DEB, 'DB4', 2, 2).concat(['help.deb.task.db15.step2'], stepKeys(D2_DEB, 'DB4', 3, 3))),
    DB17: task(D2_DEB, 'DB17', 2, { target: 'input[type="checkbox"][name^="filter["]', label: showKey(D2_DEB, 'DB17'), kind: 'field' },
      stepKeys(D2_DEB, 'DB4', 3, 3))
  };

  const D2_DEB_FIELDS = {};
  ['search:kontonr', 'search:firmanavn', 'search:postnr', 'search:bynavn', 'search:kontakt', 'search:tlf', 'search:cvrnr',
    'search:email', 'rowcount', 'aktiv', 'inviter'].forEach(function (k) { D2_DEB_FIELDS[k] = fieldKeys(D2_DEB, k); });

  const d2List = {
    group: D2_DEB,
    states: ['liste', 'tom', 'kommission', 'kolonner', 'filtre'],
    flags: ['soegning', 'ingenRaekker', 'udenMail'],

    name: function (ctx, st) {
      const view = (st && st.data && st.data.view) || '';
      const state = st ? st.state : '';
      if (state === 'kommission' || view === 'kommission') return 'help.deb.name_kommission';
      return 'help.deb.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const id = d2GridId(doc);
      if (!id) return { state: 'unknown', flags: {}, data: {} };   // the Booking view (valg=rental) has no grid
      const view = id === 'debitor_kommission' ? 'kommission' : 'debitorer';
      const rows = d2GridRows(doc, id).length;
      const flags = {
        soegning: d2SearchesWithText(doc, id).length > 0,
        ingenRaekker: rows === 0,
        udenMail: view === 'kommission' && !!d2Q(doc, 'input[name="invite[]"][disabled]')
      };
      let state;
      if (d2Q(doc, '[name^="rows[' + id + ']"]')) state = 'kolonner';
      else if (d2Q(doc, '[name^="filter[' + id + ']"]')) state = 'filtre';
      else if (view === 'debitorer') state = rows ? 'liste' : 'tom';
      else state = view;
      return { state: state, flags: flags, data: { view: view, rows: rows } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'tom') return [f.soegning ? 'help.deb.about.soegning_tom' : 'help.deb.about.tom'];
      if (state === 'liste') return f.soegning ? ['help.deb.about.liste', 'help.deb.about.soegning'] : ['help.deb.about.liste'];
      if (state === 'kommission') {
        const out = ['help.deb.about.kommission'];
        if (f.soegning) out.push(f.ingenRaekker ? 'help.deb.about.soegning_tom' : 'help.deb.about.soegning');
        else if (f.udenMail) out.push('help.deb.about.uden_mail');
        return out;
      }
      if (state === 'kolonner') return ['help.deb.about.kolonner'];
      if (state === 'filtre') return ['help.deb.about.filtre'];
      return [];
    },

    tasks: D2_DEB_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      const main = ['tom', 'liste', 'kommission'].indexOf(state) >= 0;
      if (main && f.soegning) ids.push('DB7');
      if (state === 'tom') ids.push('DB2', 'DB8');
      else if (state === 'liste') ids.push('DB1', 'DB3', 'DB2', 'DB18', 'DB4', 'DB5', 'DB6', 'DB8');
      else if (state === 'kommission') ids.push('DB10', 'DB11', 'DB12', 'DB1');
      else if (state === 'kolonner') ids.push('DB15');
      else if (state === 'filtre') ids.push('DB17', 'DB5');
      return unique(ids);
    },

    fields: D2_DEB_FIELDS,

    // The Kommission view's two checkbox columns have list names (debitor_kommission.php:606-607).
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (n === 'mySale[]') return 'aktiv';
      if (n === 'invite[]') return 'inviter';
      return null;
    },

    // Enter in a search field submits the grid's GET form (grid.php:1265, :2686-2699). The top line prints Luk with
    // accesskey L only outside the sidebar layout (topLine.php:34-41), so there is no Alt key here.
    shortcuts: [
      { keys: 'Enter', text: 'help.deb.key.01', group: 'keys' }
    ],

    questions: {
      Q1: {
        q: 'help.deb.q.q1.q',
        words: d2Words(['kan ikke se', 'mangler', 'væk', 'forsvundet', 'skjult', 'lukket', 'lukkede', 'udgået', 'filter', 'søgning', 'finde kunde'],
          ['cannot see', 'missing', 'gone', 'disappeared', 'hidden', 'closed', 'filter', 'search', 'find customer'],
          ['kan ikke se', 'mangler', 'borte', 'forsvunnet', 'skjult', 'lukket', 'lukkede', 'filter', 'søk', 'finne kunde']),
        steps: ['help.deb.q.q1.step1', 'help.deb.q.q1.step2', 'help.deb.q.q1.step3'],
        show: D2_DEB_TASKS.DB1.show
      },
      Q2: {
        q: 'help.deb.q.q2.q',
        words: d2Words(['skylder', 'saldo', 'gæld', 'tilgodehavende', 'ubetalt', 'ubetalte', 'åbne poster', 'kontokort', 'kontoudtog', 'restance'],
          ['owes', 'balance', 'debt', 'receivable', 'unpaid', 'open items', 'account statement', 'statement', 'arrears'],
          ['skylder', 'saldo', 'gjeld', 'utestående', 'ubetalt', 'ubetalte', 'åpne poster', 'kontokort', 'kontoutskrift']),
        steps: ['help.deb.q.q2.step1', 'help.deb.q.q2.step2'],
        show: D2_DEB_TASKS.DB3.show
      },
      Q3: {
        q: 'help.deb.q.q3.q',
        words: d2Words(['slet', 'slette', 'fjern', 'fjerne', 'anonymiser', 'anonymisere', 'gdpr', 'luk', 'lukke'],
          ['delete', 'remove', 'anonymise', 'anonymize', 'gdpr', 'close'],
          ['slett', 'slette', 'fjern', 'fjerne', 'anonymiser', 'anonymisere', 'gdpr', 'lukk', 'lukke']),
        steps: ['help.deb.q.q3.step1', 'help.deb.q.q3.step2', 'help.deb.q.q3.step3'],
        show: D2_DEB_TASKS.DB3.show
      },
      Q4: {
        q: 'help.deb.q.q4.q',
        words: d2Words(['inviter', 'invitere', 'invitation', 'mit salg', 'kommission', 'e-mail', 'mail', 'grå'],
          ['invite', 'invitation', 'my sales', 'commission', 'email', 'mail', 'greyed out'],
          ['inviter', 'invitere', 'invitasjon', 'mitt salg', 'kommisjon', 'e-post', 'grå']),
        steps: ['help.deb.q.q4.step1', 'help.deb.q.q4.step2'],
        show: D2_DEB_TASKS.DB11.show
      }
    },

    // A search or an empty list leads with the customer that is not shown; a full list with what a customer owes
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'kommission') return ['Q4', 'Q1', 'Q2'];
      if (state === 'kolonner' || state === 'filtre') return ['Q1'];
      if (state === 'liste' && !f.soegning) return ['Q2', 'Q1', 'Q3'];
      return ['Q1', 'Q2', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/debitor.php'] = d2List;
  PAGES['debitor/debitor_kommission.php'] = d2List;

  // ---- Debitorkort (debitor/debitorkort.php)
  // Evidence: opened from a list row (debitor.php:583-606), Ny on the list (topLine.php:129-134), an order's Debitorkort
  // button and its redirects (ordre.php:829, :1068, :1839, :3832). Header in the sidebar layout: Tilbage a[accesskey=L],
  // Hjælp #tutorial-help, Ny a[accesskey=N] (:1381-1422). form#debitorkortForm name=debitorkort (:1473), hidden id,
  // kontonr, ordre_id, returside (:1500-1503); a new card has id 0 (:1241-1302, kontotype and Betalingsbetingelse
  // default to the most used). SD-774 warning .einvoice-cvr-warning with the NemHandel link (:1514,
  // debitorkortIncludes/einvoiceCvrBanner.php:87-104). Kundetype select kontotype, saved value first (:1515-1524);
  // Kundenr. ny_kontonr (:1528); Firmanavn or Fornavn/Efternavn (:1552-1561); e-mail kontakt_email_val[1] and the
  // hard-coded brug mail box mailfakt (:1584-1591); Betalingsbetingelse betalingsbet + betalingsdage (:1840-1858);
  // Debitorgruppe gruppe (:1860-1876); Rabatgruppe when groups exist (:1886-1897); Auto-lookup CVR nr. (:1903); CVR-nr.
  // cvrnr (:1905); EAN-nr. ean and Institutionsnr. only for erhverv, else hidden (:1910-1921); Kreditmax (:1925-1926);
  // Kundeansvarlig, Status, Lukket, Ingen kortbetaling (:1942-1985); Ekstrafelter felt_1..5 (:2358-2369); Bemærkning notes
  // (:2475); Kontaktpersoner with Ny a[href^=ansatte.php] > button and Pos. posnr[n] (:2515-2550); Gem/opdatér
  // name=submit accesskey g, then Anonymisér (has orders, open items or contacts) or Slet accesskey s (:2558-2590).
  // The footer #debkort-page-footer (Historik, Kontokort, Fakturaliste, Opgaveliste, Print) is added by script once the
  // page is ready (:2619-2655, :3703-3722); Historik only with crm.access, and it opens the customer in the CRM
  // (debitorkort.php:2663-2671, historikkort.php redirects to crm.php?tab=cust&drawer=<id>, crmLegacyRoute.php:23-47);
  // Fakturaoversigt #ph_toggle_bar (:2747-2754). Save rules: blank Kundenr. gets the next free number from 1000
  // (:582-592); '=nr' goes to kontofusion.php (:354-358); a new card saved from an order returns to the order (:696-700);
  // a changed Kundetype with contacts is refused (:733, :1102-1106); Pos. '-' deletes a contact (:994-1011); postnr fills
  // the town (:561). CVR lookup only on a new card (:2712-2715, cvrapiopslag.js:270-353).

  const D2_DK = 'dkort';

  function d2CardCtl(ctx, name) {
    return d2Control(ctx.doc, 'form[name="debitorkort"]', name);
  }

  /** Ny in the Kontaktpersoner header: the ansatte.php link that holds a button (the names link without one). */
  function d2ContactNew(ctx) {
    const links = d2Qa(ctx.doc, 'a[href^="ansatte.php"]');
    for (let i = 0; i < links.length; i++) {
      if (d2Q(links[i], 'button')) return links[i];
    }
    return null;
  }

  /** Slet when the card can be deleted, else Anonymisér (debitorkort.php:2566-2590). */
  function d2DeleteOrAnonymize(ctx) {
    return d2Q(ctx.doc, 'input[name="submit"][accesskey="s"]') || d2Q(ctx.doc, 'input[name="anonymize"]');
  }

  const D2_DK_SAVE = { target: 'input[name="submit"][accesskey="g"]', label: showKey(D2_DK, 'DK2'), kind: 'button' };
  const D2_DK_CVR = { target: 'input[name="cvrnr"]', label: fieldLabel(D2_DK, 'cvrnr'), kind: 'field' };

  /** A show that rings only in the card's states listed, and with a flag when one is named (spec §19.3: the chat's search
   *  offers every task in every state). Without a state the card is detected from the page. */
  function d2CardIn(states, flag, show) {
    return {
      target: function (ctx, st) {
        let s = st && st.state ? st : null;
        if (!s) {
          try {
            s = d2Card.detect(ctx);
          } catch (e) {
            s = null;
          }
        }
        if (!s || states.indexOf(s.state) < 0 || (flag && !(s.flags && s.flags[flag]))) return null;
        return typeof show.target === 'function' ? show.target(ctx, s) : d2Q(ctx.doc, show.target);
      },
      label: show.label,
      kind: show.kind
    };
  }

  const D2_DK_TASKS = {
    // The CVR lookup is loaded only on a new card (debitorkort.php:2712-2715), so a saved card's CVR-nr. fetches nothing
    DK1: task(D2_DK, 'DK1', 3, d2CardIn(['ny'], '', D2_DK_CVR)),
    DK2: task(D2_DK, 'DK2', 4, D2_DK_SAVE),
    DK3: task(D2_DK, 'DK3', 3, { target: 'select[name="betalingsbet"]', label: fieldLabel(D2_DK, 'betalingsbet'), kind: 'field' }),
    DK4: task(D2_DK, 'DK4', 3, { target: 'select[name="gruppe"]', label: fieldLabel(D2_DK, 'gruppe'), kind: 'field' }),
    DK5: task(D2_DK, 'DK5', 3, { target: 'input[name="kontakt_email_val[1]"]', label: fieldLabel(D2_DK, 'email'), kind: 'field' }),
    DK6: task(D2_DK, 'DK6', 4, { target: 'input[name="ean"]:not([type="hidden"])', label: fieldLabel(D2_DK, 'ean'), kind: 'field' }),
    DK7: task(D2_DK, 'DK7', 3, { target: d2ContactNew, label: showKey(D2_DK, 'DK7'), kind: 'button' }),
    DK8: task(D2_DK, 'DK8', 3, { target: '#debkort-page-footer button[onclick*="rapportart=kontokort"]', label: showKey(D2_DK, 'DK8'), kind: 'button' }),
    // DK9's successor (new keys, as DB18): Historik opens the customer's drawer in the CRM, whose composer has the types,
    // the text, the follow-up date and Gem (javascript/crm.js composer()); the button needs crm.access (read), the composer
    // write access (DATA.canWrite, crm_can_write()); the step says both
    DK20: task(D2_DK, 'DK20', 2, { target: '#debkort-page-footer button[onclick*="historikkort.php"]', label: showKey(D2_DK, 'DK20'), kind: 'button' }),
    DK10: task(D2_DK, 'DK10', 2, { target: 'input[name="lukket"]', label: fieldLabel(D2_DK, 'lukket'), kind: 'field' }),
    // A new card has no customer to delete or merge yet (=nr needs the card's id, debitorkort.php:354-358)
    DK11: task(D2_DK, 'DK11', 3, d2CardIn(['kort'], '', { target: d2DeleteOrAnonymize, label: showKey(D2_DK, 'DK11'), kind: 'button' })),
    DK12: task(D2_DK, 'DK12', 4, d2CardIn(['kort'], '', { target: 'input[name="ny_kontonr"]', label: fieldLabel(D2_DK, 'ny_kontonr'), kind: 'field' })),
    DK13: task(D2_DK, 'DK13', 3, D2_DK_CVR),
    // Gem/opdatér goes back to the order only on a card opened from one (debitorkort.php:696-700)
    DK14: task(D2_DK, 'DK14', 3, d2CardIn(['kort'], 'fraOrdre', D2_DK_SAVE)),
    DK15: task(D2_DK, 'DK15', 3, d2CardIn(['ny'], 'fraOrdre', D2_DK_SAVE)),
    DK16: task(D2_DK, 'DK16', 1, { target: 'input[name="lukket"]', label: fieldLabel(D2_DK, 'lukket'), kind: 'field' }),
    DK17: task(D2_DK, 'DK17', 3, D2_DK_SAVE),
    DK18: task(D2_DK, 'DK18', 3, { target: '#ph_toggle_bar a', label: showKey(D2_DK, 'DK18'), kind: 'button' }),
    // Fakturaliste opens the customer's own order list (debitorkort.php:2637-2641, a disabled button without the orders
    // right); its Ny carries konto_id (ordLstIncludes/topLine.php:113-124), so the new order is made with the customer
    // at once (ordre.php:870-900). Only on a saved card: a new card's footer has no customer id yet.
    DK19: task(D2_DK, 'DK19', 3, {
      target: function (ctx, st) {
        return st && st.state === 'kort' ? d2Q(ctx.doc, '#debkort-page-footer button[onclick*="ordreliste.php?konto_id="]') : null;
      },
      label: showKey(D2_DK, 'DK19'),
      kind: 'button'
    })
  };

  const D2_DK_FIELDS = {};
  ['kontotype', 'ny_kontonr', 'firmanavn', 'fornavn', 'efternavn', 'postnr', 'email', 'mailfakt', 'betalingsbet', 'betalingsdage',
    'gruppe', 'rabatgruppe', 'cvrnr', 'auto_lookup_cvr', 'ean', 'kreditmax', 'kontoansvarlig', 'status', 'lukket', 'stripe_fravalg',
    'notes', 'posnr', 'felt'].forEach(function (k) { D2_DK_FIELDS[k] = fieldKeys(D2_DK, k); });

  const d2Card = {
    group: D2_DK,
    states: ['ny', 'kort'],
    flags: ['privat', 'cvrAdvarsel', 'fraOrdre', 'lukket'],

    name: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const nr = String((st && st.data && st.data.kontonr) || '');
      if (state === 'ny') return 'help.dkort.name_new';
      if (state === 'kort' && nr) return ['help.dkort.name', nr];
      return 'help.dkort.name_plain';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      if (!d2Q(doc, 'form[name="debitorkort"]')) return { state: 'unknown', flags: {}, data: {} };
      const id = d2Value(d2CardCtl(ctx, 'id'));
      const retur = d2Value(d2CardCtl(ctx, 'returside')) || d2Param(ctx, 'returside');
      const flags = {
        privat: d2FirstOption(d2CardCtl(ctx, 'kontotype')) === 'privat',
        cvrAdvarsel: !!d2Q(doc, '.einvoice-cvr-warning'),
        fraOrdre: retur.indexOf('ordre.php') >= 0,
        lukket: d2Saved(d2CardCtl(ctx, 'lukket'))
      };
      const state = id === '' || id === '0' ? 'ny' : 'kort';
      return { state: state, flags: flags, data: { kontonr: d2Value(d2CardCtl(ctx, 'kontonr')) } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'ny') return f.fraOrdre ? ['help.dkort.about.ny', 'help.dkort.about.fra_ordre_ny'] : ['help.dkort.about.ny'];
      if (state !== 'kort') return [];
      const out = ['help.dkort.about.kort'];
      if (f.cvrAdvarsel) out.push('help.dkort.about.cvr_advarsel');
      else if (f.fraOrdre) out.push('help.dkort.about.fra_ordre');
      else if (f.lukket) out.push('help.dkort.about.lukket');
      return out;
    },

    tasks: D2_DK_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const erhverv = !f.privat;
      let ids = [];
      if (state === 'ny') {
        if (f.fraOrdre) ids = ['DK15', erhverv ? 'DK1' : '', 'DK4', 'DK3', 'DK5', erhverv ? 'DK6' : ''];
        else if (erhverv) ids = ['DK1', 'DK2', 'DK4', 'DK3', 'DK5', 'DK6'];
        else ids = ['DK2', 'DK3', 'DK5', 'DK4'];
      } else if (state === 'kort') {
        ids = [f.cvrAdvarsel ? 'DK13' : '', f.fraOrdre ? 'DK14' : '', f.lukket ? 'DK16' : '',
          'DK17', 'DK8', f.lukket ? '' : 'DK19', 'DK20', erhverv ? 'DK7' : '', 'DK18', 'DK5', 'DK3', 'DK4', erhverv ? 'DK6' : '', 'DK12',
          f.lukket ? '' : 'DK10', 'DK11'];
      }
      return unique(ids.filter(function (id) { return id !== ''; }));
    },

    // The e-invoice warning stops e-invoices to the customer until the CVR is right (einvoiceCvrBanner.php:44-63).
    problem: function (ctx, st) {
      const f = (st && st.flags) || {};
      return st && st.state === 'kort' && f.cvrAdvarsel ? 'DK13' : '';
    },

    fields: D2_DK_FIELDS,

    // The primary e-mail and the contacts' positions have list names (debitorkort.php:1585, :2521).
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (n === 'kontakt_email_val[1]') return 'email';
      if (/^posnr\[\d+\]$/.test(n)) return 'posnr';
      return null;
    },

    // accesskey g Gem/opdatér, s Slet (:2569, :2581, :2585), L Tilbage and N Ny in the head (:1399-1416); Enter in a text
    // field submits the card with its first button, Gem/opdatér.
    shortcuts: [
      { keys: 'Enter', text: 'help.dkort.key.01', group: 'keys' },
      { keys: 'Alt+G', text: 'help.dkort.key.02', group: 'keys' },
      { keys: 'Alt+N', text: 'help.dkort.key.03', group: 'keys' },
      { keys: 'Alt+S', text: 'help.dkort.key.04', group: 'keys' },
      { keys: 'Alt+L', text: 'help.dkort.key.05', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.dkort.q.q1.q',
        words: d2Words(['ordre', 'vælge kunde', 'kan ikke vælge', 'lukket', 'debitorgruppe', 'gruppe', 'mangler gruppe'],
          ['order', 'choose customer', 'cannot choose', 'closed', 'debtor group', 'group', 'missing group'],
          ['ordre', 'velge kunde', 'kan ikke velge', 'lukket', 'debitorgruppe', 'gruppe', 'mangler gruppe']),
        steps: ['help.dkort.q.q1.step1', 'help.dkort.q.q1.step2'],
        show: D2_DK_TASKS.DK10.show
      },
      Q2: {
        q: 'help.dkort.q.q2.q',
        words: d2Words(['kundenummer', 'kontonummer', 'nummer', 'ændre nummer', 'skifte nummer', 'findes allerede'],
          ['customer number', 'account number', 'number', 'change number', 'already exists'],
          ['kundenummer', 'kontonummer', 'nummer', 'endre nummer', 'bytte nummer', 'finnes allerede']),
        steps: ['help.dkort.q.q2.step1', 'help.dkort.q.q2.step2', 'help.dkort.q.q2.step3'],
        // Kundenr. on a new card too, unlike the merge (DK12)
        show: { target: 'input[name="ny_kontonr"]', label: fieldLabel(D2_DK, 'ny_kontonr'), kind: 'field' }
      },
      Q3: {
        q: 'help.dkort.q.q3.q',
        words: d2Words(['slet', 'slette', 'fjern', 'anonymiser', 'anonymisere', 'gdpr', 'persondata'],
          ['delete', 'remove', 'anonymise', 'anonymize', 'gdpr', 'personal data'],
          ['slett', 'slette', 'fjern', 'anonymiser', 'anonymisere', 'gdpr', 'personopplysninger']),
        steps: stepKeys(D2_DK, 'DK11', 1, 3),
        show: D2_DK_TASKS.DK11.show
      },
      Q4: {
        q: 'help.dkort.q.q4.q',
        words: d2Words(['sammenlæg', 'sammenlægge', 'fusion', 'fusionere', 'dublet', 'to kort', 'findes ikke'],
          ['merge', 'duplicate', 'two cards', 'does not exist'],
          ['slå sammen', 'sammenslå', 'fusjon', 'duplikat', 'to kort', 'finnes ikke']),
        steps: ['help.dkort.q.q4.step1', 'help.dkort.q.q4.step2'],
        show: D2_DK_TASKS.DK12.show
      },
      // A blank Kundenr. gets the first free number from 1000 and an alert with it (debitorkort.php:576-592); a character
      // that is no digit gives an alert and is read as 0 (:518-532); a number that exists already is refused, and the card
      // is not made (:546-553, :603)
      Q5: {
        q: 'help.dkort.q.q5.q',
        words: d2Words(['kundenummer', 'kundenr', 'nummer', 'tomt', 'automatisk', 'nyt nummer', 'næste nummer'],
          ['customer number', 'customer no', 'number', 'empty', 'automatic', 'new number', 'next number'],
          ['kundenummer', 'kundenr', 'nummer', 'tomt', 'automatisk', 'nytt nummer', 'neste nummer']),
        steps: ['help.dkort.q.q5.step1', 'help.dkort.q.q5.step2'],
        show: d2CardIn(['ny'], '', { target: 'input[name="ny_kontonr"]', label: fieldLabel(D2_DK, 'ny_kontonr'), kind: 'field' })
      }
    },

    // A new card leads with the number it gets; a saved card with deleting or anonymising, and a closed card with why
    // an order cannot use it
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'ny') return ['Q5', 'Q2', 'Q1'];
      return f.lukket ? ['Q1', 'Q3', 'Q2'] : ['Q3', 'Q2', 'Q4'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/debitorkort.php'] = d2Card;

  // ---- CRM (debitor/crm.php)
  // The CRM replaced the customer history: historikkort.php, historik.php, debitor_historik.php and debitor.php?valg=historik
  // only redirect to crm.php?tab=cust&drawer=<id> or ?tab=act, behind crm.access as crm.php itself (crmLegacyRoute.php:21-47,
  // debitor.php:62-65, crm.php:51), so those routes have no page here. Evidence, debitor/crm.php and javascript/crm.js
  // unless named: Debitor > CRM in the sidebar only with crm.access (index/main.php:285-287). One root #crm (crm.php:68); the
  // tabs #tabs (:70) are buttons .sk-tab[data-tab] follow, pipe, cal, cust, act with aria-selected on the open one, the
  // count of overdue follow-ups .crm-tab-late on Opfølgning (renderTabs(), crm.js:312-323); the open view #v-<tab> (:99-103,
  // renderAll() crm.js:1327-1331). The search #q (:72) filters every tab: names, customer numbers, contacts and note texts,
  // on Kunder also town, e-mail and phone (match(), custMatch()); Enter opens a single hit, does nothing with several, and
  // with none searches the customers that are not closed on the server, also outside the CRM: name, customer number or
  // contact name, at most 10 (crm.js:1453-1459, crmFunctions.php crm_search() :1103-1111). The page holds at most the
  // CRM_MAX_CUSTOMERS most recently active customers and says so in #noteBar (crm.js:1342). #grpSel the
  // status group (:73), #scope Alle | Mine (:74-77, Mine disabled for a user without an employee, renderAll()); #newBtn
  // (:79) is Ny opfølgning, Ny sag, Nyt møde, Ny kunde (a lead) or Ny note by tab (NEW_FOR, crm.js:1436) and, like the gear,
  // hidden without write access (crm.js:1450, renderAll()); key N, Esc and Enter on a row (crm.js:1422-1426). Opfølgning:
  // the open follow-ups and meetings by date, Forsinket .. Senere, rows .body-row[data-open][data-h], the box [data-tick]
  // (aria-label Gjort), Udsæt [data-snooze] and Åbn on hover (renderFollow() crm.js:350, css/crm.css:116-117); every change
  // can be undone from the message (toggleDone() :1127, snoozeMenu() :1238). Kunder: the customers in the CRM's scope, a
  // status in a status group, an entry, an open sag, a next-contact date or a lead, never a closed one (renderCust() :489,
  // crm_customer_scope() :1087-1101). Aktivitet: the newest entries of every customer, the customer's name opens it
  // (renderAct() :545, itemHtml() :523). The drawer #drawer is shown only with the class open (css/crm.css:257): #dTitle,
  // #stSel, #ownSel, Book møde [data-addmeet], the tabs [data-dtab] Historik, Sager (og ordrer), Kontaktpersoner, the next
  // follow-up .crm-next with Udsæt and Gjort, and with write access the composer #ccomp: the types [data-type] Opkald, Mail,
  // Møde, Note, the contact #cto (med), the text #cNote, the follow-up chips #cchips, #cdate, til, Gem #csave, Ctrl+Enter
  // (renderDrawer() :617, composer() :594, wireComposer() :662); Gem needs a text or a date, and with a date and no employee
  // for the user (ME 0) someone at til (upd()). Ny opfølgning and Ny note pick the customer first and show the same
  // composer with the prefix n (newFollowUp() :821); Book møde opens the meeting dialog, Nyt møde picks the customer first
  // (meetingDialog() :866, :949): Emne, Dato, Fra, Varighed, Ansvarlig, Kontaktperson, Kolleger, the busy warning with a
  // free time and the invitation box. Dialogs are #dlg in #scrim.open, a fixed .sk-modal over the whole page (openDlg()
  // :716, css/saldi-kit.css:400). None of this loads the page or fires a change event, so the page is inPlace.

  const D2_CRM = 'dcrm';
  const D2_CRM_TAB_STATES = ['opfoelgning', 'sager', 'kalender', 'kunder', 'aktivitet'];
  const D2_CRM_STATES = D2_CRM_TAB_STATES.concat(['kunde']);
  const D2_CRM_TABS = { follow: 'opfoelgning', pipe: 'sager', cal: 'kalender', cust: 'kunder', act: 'aktivitet' };

  /** The customer's name in the drawer's title, without the ' (lukket)' after it (renderDrawer()). */
  function d2CrmName(h2) {
    const all = d2Text(h2);
    const sub = d2Text(d2Q(h2, 'span'));
    return (sub && all.slice(-sub.length) === sub ? all.slice(0, all.length - sub.length) : all).trim();
  }

  /** A show target per CRM state, { state: selector }: nothing in the other states (§19.3), so a list control is not
   *  ringed under the open drawer's scrim. An open dialog (#scrim.open, a fixed .sk-modal over the whole page) covers
   *  the tabs and the drawer, so then only map.dialog counts, looked up inside the dialog. The page redraws in place
   *  (inPlace), so the state is read live here and the cached one is only a fallback. */
  function d2CrmShow(map) {
    return function (ctx, st) {
      const dlg = d2Q(ctx.doc, '#scrim.open #dlg');
      if (dlg) return map.dialog ? d2Q(dlg, map.dialog) : null;
      let s = '';
      try {
        s = d2Crm.detect(ctx).state;
      } catch (e) {
        s = st && st.state ? st.state : '';
      }
      return Object.prototype.hasOwnProperty.call(map, s) ? d2Q(ctx.doc, map[s]) : null;
    };
  }

  /** The same selector in every tab state (not in the drawer). */
  function d2CrmTabs(sel) {
    const map = {};
    D2_CRM_TAB_STATES.forEach(function (s) { map[s] = sel; });
    return map;
  }

  const D2_CRM_TASKS = {
    // the drawer's composer (c), or the one in the Ny note / Ny opfølgning dialog (n) once the customer is picked
    CR1: task(D2_CRM, 'CR1', 4, { target: d2CrmShow({ kunde: '#cNote', dialog: '#nNote' }), label: fieldLabel(D2_CRM, 'notat'), kind: 'field' }),
    CR2: task(D2_CRM, 'CR2', 3, { target: d2CrmShow({ kunde: '#cchips', dialog: '#nchips' }), label: showKey(D2_CRM, 'CR2'), kind: 'button' }),
    CR3: task(D2_CRM, 'CR3', 2, { target: d2CrmShow(d2CrmTabs('#q')), label: fieldLabel(D2_CRM, 'soeg'), kind: 'field' }),
    // the first open follow-up's box (a done one in Vis gjort is ticked, .on), and Gjort at the next follow-up in the drawer
    CR4: task(D2_CRM, 'CR4', 3, {
      target: d2CrmShow({ opfoelgning: '#v-follow [data-tick]:not(.on)', kunde: '#drawer .crm-next [data-tick]' }),
      label: showKey(D2_CRM, 'CR4'),
      kind: 'button'
    }),
    CR5: task(D2_CRM, 'CR5', 4, { target: d2CrmShow({ kunde: '#drawer [data-addmeet]' }), label: showKey(D2_CRM, 'CR5'), kind: 'button' }),
    CR6: task(D2_CRM, 'CR6', 2, { target: d2CrmShow(d2CrmTabs('#scope [data-scope="mine"]')), label: showKey(D2_CRM, 'CR6'), kind: 'button' })
  };

  const D2_CRM_FIELDS = {};
  ['soeg', 'statusgruppe', 'notat', 'med', 'dato', 'status', 'ansvarlig'].forEach(function (k) { D2_CRM_FIELDS[k] = fieldKeys(D2_CRM, k); });

  // The controls have ids, no names; the composer's ids carry its prefix: c in the drawer, n in Ny opfølgning/Ny note
  const D2_CRM_FIELD_IDS = {
    q: 'soeg', grpSel: 'statusgruppe', cNote: 'notat', nNote: 'notat', cto: 'med', nto: 'med', cdate: 'dato', ndate: 'dato',
    stSel: 'status', ownSel: 'ansvarlig'
  };

  const d2Crm = {
    group: D2_CRM,
    // crm.js changes the tab, the drawer and the dialogs without a load or a change event: the shell reads the page again
    // after a click or the keys Esc, Enter and N (saldi-help.js bindFrame())
    inPlace: true,
    states: D2_CRM_STATES,
    flags: ['kunLaes', 'forsinket', 'soegning', 'dialog'],
    // Mine, the view of what the user is responsible for, is a stop word everywhere else ("mine fakturaer")
    ownWords: { da: ['mine'], en: ['mine'], no: ['mine'] },

    name: function (ctx, st) {
      const kunde = String((st && st.data && st.data.kunde) || '');
      return st && st.state === 'kunde' && kunde ? ['help.dcrm.name_kunde', kunde] : 'help.dcrm.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      if (!d2Q(doc, '#crm')) return { state: 'unknown', flags: {}, data: {} };   // the 503 text before a migration
      const tab = attr(d2Q(doc, '#tabs [data-tab][aria-selected="true"]'), 'data-tab');
      const title = d2Q(doc, '#drawer.open #dTitle');
      let state = Object.prototype.hasOwnProperty.call(D2_CRM_TABS, tab) ? D2_CRM_TABS[tab] : 'unknown';
      if (title) state = 'kunde';
      const nyKnap = d2Q(doc, '#newBtn');
      const flags = {
        kunLaes: !!nyKnap && nyKnap.getAttribute('hidden') !== null,
        forsinket: !!d2Q(doc, '#tabs .crm-tab-late'),
        soegning: d2Value(d2Q(doc, '#q')) !== '',
        dialog: !!d2Q(doc, '#scrim.open #dlg')
      };
      return { state: state, flags: flags, data: { tab: tab, kunde: title ? d2CrmName(title) : '' } };
    },

    // One paragraph per tab and the drawer, and one more: read-only access, a search, or overdue follow-ups
    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (D2_CRM_STATES.indexOf(state) < 0) return [];
      const out = ['help.dcrm.about.' + state];
      if (f.kunLaes) out.push('help.dcrm.about.kun_laes');
      else if (f.soegning && state !== 'kunde') out.push('help.dcrm.about.soegning');
      else if (f.forsinket && state === 'opfoelgning') out.push('help.dcrm.about.forsinket');
      return out;
    },

    tasks: D2_CRM_TASKS,

    // Read-only: no composer, no buttons to make anything, so only finding and filtering. Opfølgning leads with the overdue
    // ones; Aktivitet, where Historik on the list lands, and the drawer with the note.
    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (f.kunLaes) return D2_CRM_TAB_STATES.indexOf(state) >= 0 ? ['CR3', 'CR6'] : [];
      if (state === 'opfoelgning') return f.forsinket ? ['CR4', 'CR2', 'CR1', 'CR3', 'CR6', 'CR5'] : ['CR2', 'CR4', 'CR1', 'CR3', 'CR6', 'CR5'];
      if (state === 'kunder') return ['CR3', 'CR1', 'CR2', 'CR6', 'CR5'];
      if (state === 'aktivitet') return ['CR1', 'CR3', 'CR2', 'CR6'];
      if (state === 'sager') return ['CR3', 'CR2', 'CR6'];
      if (state === 'kalender') return ['CR5', 'CR2', 'CR3'];
      if (state === 'kunde') return ['CR1', 'CR2', 'CR4', 'CR5'];
      return [];
    },

    fields: D2_CRM_FIELDS,

    fieldKey: function (el) {
      const id = attr(el, 'id');
      return Object.prototype.hasOwnProperty.call(D2_CRM_FIELD_IDS, id) ? D2_CRM_FIELD_IDS[id] : null;
    },

    // N makes the tab's new thing outside a field, with write access; Esc closes a dialog, the gear, then the drawer;
    // Enter on a focused row opens the customer (crm.js:1422-1426); Ctrl+Enter saves the composer (wireComposer()).
    // No access keys, so no shortcutsNote.
    shortcuts: [
      { keys: 'N', text: 'help.dcrm.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.dcrm.key.02', group: 'keys' },
      { keys: 'Ctrl+Enter', text: 'help.dcrm.key.03', group: 'keys' },
      { keys: 'Esc', text: 'help.dcrm.key.04', group: 'keys' }
    ],

    questions: {
      // The history's notes are rows of the same table (historik); the drawer fetches all of a customer's (refreshFull())
      Q1: {
        q: 'help.dcrm.q.q1.q',
        words: d2Words(['historik', 'kundehistorik', 'historikkort', 'gamle', 'notater', 'notat', 'næste kontakt', 'væk', 'forsvundet', 'flyttet'],
          ['history', 'customer history', 'old', 'notes', 'note', 'next contact', 'gone', 'disappeared', 'moved'],
          ['historikk', 'historie', 'kundehistorikk', 'gamle', 'notater', 'notat', 'neste kontakt', 'borte', 'forsvunnet', 'flyttet']),
        steps: ['help.dcrm.q.q1.step1', 'help.dcrm.q.q1.step2'],
        show: { target: d2CrmShow(d2CrmTabs('#tabs [data-tab="act"]')), label: 'help.dcrm.q.q1.show', kind: 'button' }
      },
      Q2: {
        q: 'help.dcrm.q.q2.q',
        words: d2Words(['kan ikke se', 'mangler', 'væk', 'finde kunde', 'find kunde', 'ikke med', 'skjult', 'lukket', 'filter', 'mine'],
          ['cannot see', 'missing', 'gone', 'find customer', 'not included', 'hidden', 'closed', 'filter', 'mine'],
          ['kan ikke se', 'mangler', 'borte', 'finne kunde', 'ikke med', 'skjult', 'lukket', 'filter', 'mine']),
        steps: ['help.dcrm.q.q2.step1', 'help.dcrm.q.q2.step2', 'help.dcrm.q.q2.step3'],
        show: D2_CRM_TASKS.CR3.show
      },
      Q3: {
        q: 'help.dcrm.q.q3.q',
        words: d2Words(['kan ikke skrive', 'kan ikke gemme', 'kan ikke ændre', 'ingen knap', 'skriveadgang', 'adgang', 'rettighed', 'rettigheder', 'rolle', 'læseadgang'],
          ['cannot write', 'cannot save', 'cannot change', 'no button', 'write access', 'access', 'permission', 'permissions', 'role', 'read only'],
          ['kan ikke skrive', 'kan ikke lagre', 'kan ikke endre', 'ingen knapp', 'skrivetilgang', 'tilgang', 'rettighet', 'rettigheter', 'rolle', 'lesetilgang']),
        // the panel's search matches every question, so this one also names why Gem is grey with write access (upd())
        steps: ['help.dcrm.q.q3.step1', 'help.dcrm.q.q3.step2', 'help.dcrm.q.q3.step3']
      }
    },

    // Read-only leads with why; Aktivitet (where Historik on the list lands) and the drawer with where the history went
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (f.kunLaes) return ['Q3', 'Q1', 'Q2'];
      if (state === 'aktivitet' || state === 'kunde') return ['Q1', 'Q2'];
      return ['Q2', 'Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/crm.php'] = d2Crm;

  // ---- Opgaveliste (debitor/jobliste.php)
  // Evidence: only with Brug jobkort on (Indstillinger > Salg > Debitorer > Debitorkort, settingsDefinitions.php:727-729):
  // Opgaveliste on the list's top line (topLine.php:86-102) and the card's foot (debitorkort.php:2643-2648, konto_id=).
  // The top line with valg=jobkort: Opgaveliste down, no Hjælp cell, Ny #opret-ny > a[href^=jobkort.php] (topLine.php:
  // 95-102, :105, :118-128). Headings Jobnr., Kunde, Postnr., Telefon, Felt 1, Felt 3, Felt 7, Planlagt til uge as sort
  // links (jobliste.php:138-145); search inputs felt01..felt08 and OK (:147-171): Jobnr. and Planlagt til uge take a
  // number or an interval 23:44, the text fields match the start of the value (includes/udvaelg.php:74-111). Rows link
  // jobkort.php?tjek= unless another user opened the job within the hour (span title 'job er låst af ...', :221-241).

  const D2_DJ = 'djob';

  const D2_DJ_TASKS = {
    DJ1: task(D2_DJ, 'DJ1', 3, { target: '#opret-ny a[href^="jobkort.php"]', label: showKey(D2_DJ, 'DJ1'), kind: 'button' }),
    DJ2: task(D2_DJ, 'DJ2', 3, { target: 'input[name="felt02"]', label: fieldLabel(D2_DJ, 'kunde'), kind: 'field' }),
    DJ3: task(D2_DJ, 'DJ3', 2, { target: 'a[href^="jobkort.php?tjek="]', label: showKey(D2_DJ, 'DJ3'), kind: 'row' })
  };

  /** The job card's own tasks (jobkort.php). */
  const D2_DJ_CARD_TASKS = {
    DJ5: task(D2_DJ, 'DJ5', 2, { target: 'a[href^="jobkort.php?id="][href*="konto_id="]', label: showKey(D2_DJ, 'DJ5'), kind: 'row' }),
    DJ6: task(D2_DJ, 'DJ6', 3, { target: 'input[name="gem"]', label: showKey(D2_DJ, 'DJ6'), kind: 'button' }),
    DJ7: task(D2_DJ, 'DJ7', 1, { target: 'input[name="udskriv"]', label: showKey(D2_DJ, 'DJ7'), kind: 'button' }),
    DJ8: task(D2_DJ, 'DJ8', 2, { target: 'a[href*="opdat17=1"]', label: showKey(D2_DJ, 'DJ8'), kind: 'button' }),
    DJ9: task(D2_DJ, 'DJ9', 1, { target: 'input[name="slet"]', label: showKey(D2_DJ, 'DJ9'), kind: 'button' })
  };

  const D2_DJ_QUESTIONS = {
    Q1: {
      q: 'help.djob.q.q1.q',
      words: d2Words(['jobkort', 'arbejdsseddel', 'arbejdskort', 'opgave', 'opgaveliste', 'jobliste'],
        ['job card', 'work order', 'task', 'task list', 'job list'],
        ['jobbkort', 'arbeidsseddel', 'oppgave', 'oppgaveliste', 'jobbliste']),
      intro: 'help.djob.q.q1.intro',
      steps: ['help.djob.q.q1.step1', 'help.djob.q.q1.step2', 'help.djob.q.q1.step3']
    },
    Q2: {
      q: 'help.djob.q.q2.q',
      words: d2Words(['låst', 'kan ikke åbne', 'åbner ikke', 'anden bruger', 'optaget'],
        ['locked', 'cannot open', 'does not open', 'other user', 'in use'],
        ['låst', 'kan ikke åpne', 'åpner ikke', 'annen bruker', 'opptatt']),
      steps: stepKeys(D2_DJ, 'DJ3', 2, 2),
      show: D2_DJ_TASKS.DJ3.show
    }
  };

  const d2JobList = {
    group: D2_DJ,
    states: ['liste', 'tom'],
    flags: ['soegning', 'kunde'],

    name: function () {
      return 'help.djob.name_liste';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const rows = d2Qa(doc, 'a[href^="jobkort.php?tjek="]').length + d2Qa(doc, 'span[title^="job er l"]').length;
      let soegning = false;
      for (let i = 1; i <= 8; i++) {
        if (d2Value(byName(doc, 'felt0' + i)) !== '') soegning = true;
      }
      const flags = { soegning: soegning, kunde: Number(d2Param(ctx, 'konto_id')) > 0 };
      return { state: rows ? 'liste' : 'tom', flags: flags, data: { rows: rows } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state !== 'liste' && state !== 'tom') return [];
      const out = [state === 'liste' ? 'help.djob.about.liste' : (f.soegning ? 'help.djob.about.soegning_tom' : 'help.djob.about.tom')];
      if (f.kunde) out.push('help.djob.about.kunde');
      return out;
    },

    tasks: D2_DJ_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'liste') return ['DJ3', 'DJ2', 'DJ1'];
      if (state === 'tom') return f.soegning ? ['DJ2', 'DJ1'] : ['DJ1'];
      return [];
    },

    fields: (function () {
      const out = {};
      ['jobnr', 'kunde', 'uge'].forEach(function (k) { out[k] = fieldKeys(D2_DJ, k); });
      return out;
    })(),

    // The search fields felt01, felt02 and felt08 sit under Jobnr., Kunde and Planlagt til uge (jobliste.php:138-167).
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (n === 'felt01') return 'jobnr';
      if (n === 'felt02') return 'kunde';
      if (n === 'felt08') return 'uge';
      return null;
    },

    // Enter in a search field submits the form with OK (jobliste.php:147-171); the top line has no access keys in the
    // sidebar layout (topLine.php:34-41).
    shortcuts: [
      { keys: 'Enter', text: 'help.djob.key.01', group: 'keys' }
    ],

    questions: D2_DJ_QUESTIONS,

    questionOrder: function () {
      return ['Q1', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/jobliste.php'] = d2JobList;

  // ---- Jobkort (debitor/jobkort.php)
  // Evidence: rows of the Opgaveliste and its Ny (jobliste.php:233, topLine.php:118-128), the order's jobkort button
  // (ordre.php:3826-3830). Without a customer the page lists the customers to choose from, each a link
  // jobkort.php?id=&konto_id= (jobkort.php:179-182, :347-390); with a customer and no job a job card is created at once
  // (:68-75). Head (topLineJobkort.php): Tilbage jobkort.php?luk=luk accesskey L, Jobkort, Hjælp. form name=jobkort
  // action jobkort.php?id=<jobnr> (:219); Planlagt til uge felt_1 (:273), the customer's name and address from the card,
  // Felt 1-10, Bemærkning 1 (a link that fills in the order's lines when the job belongs to an order, :298-309),
  // Tabelfelt 1-6 with a new empty line and sums of columns 4-6 (:311-330), Bemærkning 2-4. The fixed foot: Gem (g),
  // Udskriv (u), Slet (s), hard-coded Danish; Slet deletes without asking (:149-153, :340-344).

  function d2JobNo(doc) {
    const m = /[?&]id=(\d+)/.exec(attr(d2Q(doc, 'form[name="jobkort"]'), 'action'));
    return m ? m[1] : '';
  }

  const d2JobCard = {
    group: D2_DJ,
    states: ['kort', 'vaelgKunde'],
    flags: ['fraOrdre'],

    name: function (ctx, st) {
      const nr = String((st && st.data && st.data.jobnr) || '');
      if (st && st.state === 'kort' && nr) return ['help.djob.name', nr];
      return 'help.djob.name_plain';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      if (d2Q(doc, 'form[name="jobkort"]')) {
        return { state: 'kort', flags: { fraOrdre: !!d2Q(doc, 'a[href*="opdat17=1"]') }, data: { jobnr: d2JobNo(doc) } };
      }
      if (d2Q(doc, 'a[href^="jobkort.php?id="][href*="konto_id="]')) return { state: 'vaelgKunde', flags: {}, data: {} };
      return { state: 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'vaelgKunde') return ['help.djob.about.vaelg_kunde'];
      if (state === 'kort') return f.fraOrdre ? ['help.djob.about.kort', 'help.djob.about.fra_ordre'] : ['help.djob.about.kort'];
      return [];
    },

    tasks: D2_DJ_CARD_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'vaelgKunde') return ['DJ5'];
      if (state === 'kort') return unique([f.fraOrdre ? 'DJ8' : 'DJ6', 'DJ6', 'DJ7', 'DJ9']);
      return [];
    },

    fields: (function () {
      const out = {};
      ['uge', 'felt_indhold'].forEach(function (k) { out[k] = fieldKeys(D2_DJ, k); });
      return out;
    })(),

    // Planlagt til uge is felt_1 (jobkort.php:273); the notes and the table are felt_indhold[x][y] (:307-338).
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (n === 'felt_1') return 'uge';
      if (/^felt_indhold\[/.test(n)) return 'felt_indhold';
      return null;
    },

    shortcuts: [
      { keys: 'Alt+G', text: 'help.djob.key.02', group: 'keys' },
      { keys: 'Alt+U', text: 'help.djob.key.03', group: 'keys' },
      { keys: 'Alt+S', text: 'help.djob.key.04', group: 'keys' },
      { keys: 'Alt+L', text: 'help.djob.key.05', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: { Q1: D2_DJ_QUESTIONS.Q1 },

    questionOrder: function () {
      return ['Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/jobkort.php'] = d2JobCard;

  // ---- Fusioner debitorer (debitor/kontofusion.php)
  // Evidence: reached by saving the card with '=' and another customer number in Kundenr. (debitorkort.php:354-358).
  // form name=kontofusion with the card being removed first (fra konto) and the card that stays after til, the buttons
  // Fortryd and OK name=fusion, hard-coded Danish (kontofusion.php:88-107). OK moves historik, ansatte, ordrer, openpost,
  // jobkort, pbs_kunder and shop_adresser and deletes the first card (:58-68); kontakt_emails, delivery_addresses and the
  // card's own fields (Bemærkning, categories) are not moved. No other open card with the number: an alert and back to
  // the card (:48-55). Fortryd returns to the unchanged card (:44-47).

  const D2_DF = 'dfus';

  const D2_DF_TASKS = {
    DF1: task(D2_DF, 'DF1', 3, { target: 'input[name="fusion"][value="OK"]', label: showKey(D2_DF, 'DF1'), kind: 'button' }),
    DF2: task(D2_DF, 'DF2', 1, { target: 'input[name="fusion"][value="Fortryd"]', label: showKey(D2_DF, 'DF2'), kind: 'button' })
  };

  const d2Fusion = {
    group: D2_DF,
    states: ['bekraeft'],
    flags: [],

    name: function () {
      return 'help.dfus.name';
    },

    detect: function (ctx) {
      return d2Q(ctx.doc, 'form[name="kontofusion"]') ? { state: 'bekraeft', flags: {}, data: {} } : { state: 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      return st && st.state === 'bekraeft' ? ['help.dfus.about.bekraeft'] : [];
    },

    tasks: D2_DF_TASKS,

    order: function (ctx, st) {
      return st && st.state === 'bekraeft' ? ['DF1', 'DF2'] : [];
    },

    fields: {},

    shortcuts: [],

    questions: {
      Q1: {
        q: 'help.dfus.q.q1.q',
        words: d2Words(['flyttes', 'flytte', 'hvad sker', 'ordrer', 'åbne poster', 'kontaktpersoner', 'historik', 'slettes', 'fortryde'],
          ['moved', 'move', 'what happens', 'orders', 'open items', 'contacts', 'history', 'deleted', 'undo'],
          ['flyttes', 'flytte', 'hva skjer', 'ordrer', 'åpne poster', 'kontaktpersoner', 'historikk', 'slettes', 'angre']),
        steps: stepKeys(D2_DF, 'DF1', 2, 3),
        show: D2_DF_TASKS.DF1.show
      }
    },

    questionOrder: function () {
      return ['Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/kontofusion.php'] = d2Fusion;

  // ==== Money and reports: reports, reminders, payments, recurring invoicing, statistics (author D3; helpers d3)
  // ================================================================== D3: Debitor money and reports
  // Debitorrapporter (Åbne poster, Kontokort, Kontosaldo and the kontokort of one customer), Rykkerbrev, Betalinger til
  // bank and its list, Import fra PBS, Genfakturer, Ret abonnementsordrer, Massefakturering, Top 100, Salg pr. postnummer
  // and Kassespor. Every page loads as the frame's own document; the classic layout is the sidebar one ($menu 'S', the
  // default of new users, includes/online.php:332), so the evidence below cites that branch.
  // Not here, and why (details in the D3 notes): debitor/ny_rykker.php, afslut_rykker.php and mail_kontoudtog.php open
  // in a popup (debitor/rapport.php:169-205); inkasso.php is unfinished and only redirects to rykkerprint.php;
  // rykkertjek.php is a login include that is switched off (index/login.php:980); betalinger_settings.php redirects to
  // Indstillinger; pbsliste.php is part of the order list's BS tab (debitor/ordLstIncludes/topLine.php:107);
  // debitor/salgsstat.php is not linked (Salgsstat opens includes/salgsstat.php, debitor/rapport.php:479-492);
  // easyUBL.php is a webhook; peppol.php opens in a new tab (debitor/bogfor.php:97-120).

  // ------------------------------------------------------------------ D3 helpers (read only)

  function d3Q(root, sel) {
    try {
      const el = root ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function d3All(root, sel) {
    try {
      return root ? Array.prototype.slice.call(root.querySelectorAll(sel)).filter(function (el) { return el && el.nodeType === 1; }) : [];
    } catch (e) {
      return [];
    }
  }

  function d3Text(el) {
    try {
      return el && el.nodeType === 1 ? String(el.textContent || '') : '';
    } catch (e) {
      return '';
    }
  }

  function d3Val(el) {
    try {
      return el && el.nodeType === 1 ? String(el.value || '') : '';
    } catch (e) {
      return '';
    }
  }

  /** A checkbox's state: the property in a browser, the attribute as printed otherwise. */
  function d3Checked(el) {
    if (!el || el.nodeType !== 1) return false;
    try {
      if (typeof el.checked === 'boolean') return el.checked;
    } catch (e) {
      return false;
    }
    return el.getAttribute('checked') !== null;
  }

  function d3Flags(names) {
    const f = {};
    names.forEach(function (n) { f[n] = false; });
    return f;
  }

  function d3State(st) {
    return st ? st.state : 'unknown';
  }

  function d3Fl(st) {
    return (st && st.flags) || {};
  }

  function d3Ids(list) {
    return unique(list.filter(function (id) { return !!id; }));
  }

  function d3Words(da, en, no) {
    return { da: da, en: en, no: no };
  }

  function d3Param(ctx, name) {
    try {
      return ctx && ctx.url && ctx.url.searchParams ? String(ctx.url.searchParams.get(name) || '') : '';
    } catch (e) {
      return '';
    }
  }

  /** The styled checkbox's visible box: the label round it (checkContainer*), else the input itself. */
  function d3Box(el) {
    if (!el) return null;
    try {
      const label = el.closest ? el.closest('label') : null;
      return label && label.nodeType === 1 ? label : el;
    } catch (e) {
      return el;
    }
  }

  /** A link that wraps a button (<a accesskey=L href=...><button>): the button, else the link. */
  function d3Inner(a) {
    if (!a) return null;
    const b = d3Q(a, 'button') || d3Q(a, 'input');
    return b || a;
  }

  /** A show target that only counts in the given states (one page object serves several screens, and the chat's search
   *  offers every task in every state, spec §19.3 and §21). */
  function d3Only(states, target) {
    return function (ctx, st) {
      if (!st || states.indexOf(st.state) === -1) return null;
      if (typeof target === 'function') return target(ctx, st);
      return d3Q(ctx.doc, target);
    };
  }

  // ---- Debitorrapporter (debitor/rapport.php)
  // Sidebar Debitor » Rapporter (index/main.php:268). One route for the selection form and four reports; in the shell's
  // frame every request is Sec-Fetch-Dest: iframe, so the async shell is skipped and the report renders directly
  // (debitor/rapport.php:614-615). Evidence, includes/rapportfunc.php unless named:
  // - the form: forside() :816-957 with includes/reportFunc/frontPageOldMenu.php: form[name=regnskabsaar] (:55), Dato,
  //   Konto, Husk (:56-58), the submits openpost, kontosaldo, kontokort (:61-67), Top 100 (:83), salgsstat (:84),
  //   Kassespor with a POS setup (:85-87), Betalingslister when DIV/2 box10 is B or D (:89-95), Salg pr. Postnr (:99,
  //   :105), the SAF-T kasserapport form with a POS setup (:138-154). No access key in the sidebar layout (:888-930).
  // - Åbne poster: openpost() :130-551. Tilbage a[href=rapport.php] (:311), the view select aabenpostmode (:315-326; the
  //   current view is the one option without a value), the reminder overview #opRykkere (:407) with the sections
  //   #rykkerOverviewTable1-4 (:426): Slet/Udskriv/Bogfør in Åbne rykkere, Afslut in Inkasso sager, Udskriv/Afslut/Ny
  //   rykker in Bogførte rykkere (:506-520). The grid: reportFunc/showOpenPosts.php vis_aabne_poster(): #visAabnePosterTable
  //   (:1006), the summary and search rows (:916, :930-940), the aging headers a.op-filter (:854-861), the account rows
  //   with konto_id[n], Kontonr. to the kontokort, the name to Debitorkort and the 0,00 udlign link (:1224-1252), Ingen
  //   (:1259), the hidden aging_bucket (:1293), the action bar Mail kontoudtog, Opret rykker, Udlign alle, Ryk alle
  //   (:1300-1348) with the paging. A blocked result window shows div[role=alert] with a link (debitor/rapport.php:175-190).
  // - Kontokort: kontokort() :967-2104: #kkPageFlex with Tilbage (accesskey L, :1243) and Udskriv (:1257-1264), #kkGridTable
  //   (:1282), tr.kk-data-row (:1751), an open amount links to includes/udlign_openpost.php, a settled one to unAlign=
  //   (:1760-1785), the footer #kkPageSize (:2026).
  // - Kontosaldo: kontosaldo() :2110-2330: #ksPageFlex with Tilbage (:2181-2192), #ksGridTable (:2241) and I alt (:2299).
  // - The kontokort of one customer from Åbne poster: includes/reportFunc/accountChart.php :343-382: Tilbage (accesskey
  //   L, back to Åbne poster), select[name=typeSelect] (:356-372), Udskriv and, for one account, E-mail (:376-382).

  const D3R = 'drap';
  const D3R_STATES = ['forside', 'openpost', 'kontokort', 'kontosaldo', 'kontodetalje', 'unknown'];
  const D3R_FLAGS = ['husk', 'soegning', 'betalingslister', 'pos', 'popupBlokeret', 'kunRykkere', 'allePoster', 'debetKredit',
    'tom', 'forfaldne', 'udlignKlar', 'rykkere', 'aabneRykkere', 'bogfoerteRykkere', 'filter', 'flereSider', 'aabnePoster',
    'udlignede'];
  const D3R_MODES = ['kun_debet', 'kun_kredit', 'vis_aabenpost', 'vis_alle_poster', 'skjul_aabenpost'];

  /** The current view of Åbne poster: the mode no option of the view select links to (rapportfunc.php:315-326). */
  function d3rMode(doc) {
    const sel = d3Q(doc, 'select[name="aabenpostmode"]');
    if (!sel) return 'vis_aabenpost';
    const values = d3All(sel, 'option').map(function (o) { return o.getAttribute('value') || ''; }).join(' ');
    for (let i = 0; i < D3R_MODES.length; i++) {
      if (values.indexOf(D3R_MODES[i] + '=on') === -1) return D3R_MODES[i];
    }
    return 'vis_aabenpost';
  }

  /** The open amount's link to Udlign (kontokort and the customer's kontokort, rapportfunc.php:1763, accountChart.php:490). */
  function d3rOpenAmount(ctx) {
    return d3Q(ctx.doc, 'a[href*="udlign_openpost.php"]');
  }

  /** Udlign alle in the action bar, else the first 0,00 that settles one account (showOpenPosts.php:1248, :1318). */
  function d3rUdlign(ctx) {
    return d3Q(ctx.doc, '#visAabnePosterTable input[value="Udlign alle"]') ||
      d3Q(ctx.doc, '#visAabnePosterTable a.op-link[href*="udlign="]');
  }

  /** The >90 column header that filters the list, else the group heading Dage over forfald (showOpenPosts.php:854-861, :945). */
  function d3rOverdue(ctx) {
    return d3Q(ctx.doc, '#visAabnePosterTable a.op-filter[href*="aging_bucket=over90"]') || d3Q(ctx.doc, '#visAabnePosterTable th.op-group');
  }

  /** Udskriv on the kontokort (rapportfunc.php:1264) or on the customer's kontokort (accountChart.php:376-377). */
  function d3rPrint(ctx) {
    return d3Q(ctx.doc, '#kkPageFlex button[onclick*="showLangModalKontoprint"]') || d3Inner(d3Q(ctx.doc, 'td[onclick*="kontoprint.php"]'));
  }

  /** Tilbage on a report: the accesskey L link's button (rapportfunc.php:1257, :2190; accountChart.php:348). */
  function d3rBack(ctx) {
    return d3Inner(d3Q(ctx.doc, 'a[accesskey="L"]'));
  }

  const D3R_TASKS = {
    // Selection form
    RF1: task(D3R, 'RF1', 3, { target: 'input[name="openpost"]', label: showKey(D3R, 'RF1'), kind: 'button' }),
    RF2: task(D3R, 'RF2', 3, { target: 'input[name="kontokort"]', label: showKey(D3R, 'RF2'), kind: 'button' }),
    RF3: task(D3R, 'RF3', 3, { target: 'input[name="kontosaldo"]', label: showKey(D3R, 'RF3'), kind: 'button' }),
    RF4: task(D3R, 'RF4', 3, { target: 'input[name="dato"]', label: fieldLabel(D3R, 'dato'), kind: 'field' }),
    RF5: task(D3R, 'RF5', 2, { target: function (ctx) { return d3Box(d3Q(ctx.doc, 'input[name="husk"]')); }, label: fieldLabel(D3R, 'husk'), kind: 'field' }),
    RF6: task(D3R, 'RF6', 2, { target: 'span[onclick*="top100.php"] input', label: showKey(D3R, 'RF6'), kind: 'button' }),
    RF7: task(D3R, 'RF7', 3, { target: 'input[name="salgsstat"]', label: showKey(D3R, 'RF7'), kind: 'button' }),
    RF8: task(D3R, 'RF8', 2, { target: 'a[href="kassespor.php"] input', label: showKey(D3R, 'RF8'), kind: 'button' }),
    RF9: task(D3R, 'RF9', 3, { target: 'span[onclick*="betalingsliste.php"] input', label: showKey(D3R, 'RF9'), kind: 'button' }),
    RF10: task(D3R, 'RF10', 2, { target: 'span[onclick*="salg_postnr.php"] input', label: showKey(D3R, 'RF10'), kind: 'button' }),
    RF11: task(D3R, 'RF11', 3, { target: 'input[name="saft"]', label: showKey(D3R, 'RF11'), kind: 'button' }),
    // Åbne poster
    OPP: task(D3R, 'OPP', 3, { target: 'div[role="alert"] a[target="_blank"]', label: showKey(D3R, 'OPP'), kind: 'button' }),
    OP1: task(D3R, 'OP1', 3, { target: d3rOverdue, label: showKey(D3R, 'OP1'), kind: 'status' }),
    OP2: task(D3R, 'OP2', 4, { target: '#visAabnePosterTable input[value="Opret rykker"]', label: showKey(D3R, 'OP2'), kind: 'button' }),
    OP3: task(D3R, 'OP3', 3, { target: '#visAabnePosterTable input[value="Mail kontoudtog"]', label: showKey(D3R, 'OP3'), kind: 'button' }),
    OP4: task(D3R, 'OP4', 3, { target: d3rUdlign, label: showKey(D3R, 'OP4'), kind: 'button' }),
    OP5: task(D3R, 'OP5', 2, { target: '#visAabnePosterTable a[href*="rapportart=accountChart"]', label: showKey(D3R, 'OP5'), kind: 'row' }),
    OP6: task(D3R, 'OP6', 2, { target: '#visAabnePosterTable a.op-name', label: showKey(D3R, 'OP6'), kind: 'row' }),
    OP7: task(D3R, 'OP7', 2, { target: '.op-search input[type="button"]', label: showKey(D3R, 'OP7'), kind: 'button' }),
    OP8: task(D3R, 'OP8', 2, { target: 'select[name="aabenpostmode"]', label: fieldLabel(D3R, 'aabenpostmode'), kind: 'field' }),
    OP9: task(D3R, 'OP9', 2, { target: 'input[name="kontonr"]', label: fieldLabel(D3R, 'kontonr'), kind: 'field' }),
    OP10: task(D3R, 'OP10', 3, { target: '#visAabnePosterTable input[value="Ryk alle"]', label: showKey(D3R, 'OP10'), kind: 'button' }),
    OP11: task(D3R, 'OP11', 2, { target: d3Only(['openpost'], function (ctx) { return d3Inner(d3Q(ctx.doc, 'a[href="rapport.php"]')); }), label: showKey(D3R, 'OP11'), kind: 'button' }),
    OP12: task(D3R, 'OP12', 2, { target: '.op-actionbar a.op-link[href*="openpost_page_size="]', label: showKey(D3R, 'OP12'), kind: 'button' }),
    // The reminder overview under the list
    RY1: task(D3R, 'RY1', 3, { target: '#rykkerOverviewTable1', label: showKey(D3R, 'RY1'), kind: 'status' }),
    RY2: task(D3R, 'RY2', 3, { target: '#rykkerOverviewTable1 input[onclick*="Bogf"]', label: showKey(D3R, 'RY2'), kind: 'button' }),
    RY3: task(D3R, 'RY3', 3, { target: '#rykkerOverviewTable3 tfoot input[type="submit"]:not([onclick])', label: showKey(D3R, 'RY3'), kind: 'button' }),
    RY4: task(D3R, 'RY4', 3, { target: '#rykkerOverviewTable3 input[onclick*="Afslut"]', label: showKey(D3R, 'RY4'), kind: 'button' }),
    RY5: task(D3R, 'RY5', 2, { target: '#rykkerOverviewTable1 input[onclick*="Slet"]', label: showKey(D3R, 'RY5'), kind: 'button' }),
    // Kontokort and the customer's kontokort
    KO1: task(D3R, 'KO1', 3, { target: d3rOpenAmount, label: showKey(D3R, 'KO1'), kind: 'row' }),
    KO2: task(D3R, 'KO2', 2, { target: 'a[href*="unAlign="]', label: showKey(D3R, 'KO2'), kind: 'row' }),
    KO3: task(D3R, 'KO3', 2, { target: d3rPrint, label: showKey(D3R, 'KO3'), kind: 'button' }),
    KO4: task(D3R, 'KO4', 2, { target: d3Only(['kontokort', 'kontosaldo'], d3rBack), label: showKey(D3R, 'KO4'), kind: 'button' }),
    KO5: task(D3R, 'KO5', 1, { target: '#kkPageSize', label: fieldLabel(D3R, 'linjer'), kind: 'field' }),
    KO6: task(D3R, 'KO6', 2, { target: '#kkGridTable a[onclick*="kassekladde.php"]', label: showKey(D3R, 'KO6'), kind: 'row' }),
    KD1: task(D3R, 'KD1', 2, { target: 'select[name="typeSelect"]', label: fieldLabel(D3R, 'visning'), kind: 'field' }),
    KD2: task(D3R, 'KD2', 2, { target: function (ctx) { return d3Inner(d3Q(ctx.doc, 'td[onclick*="mail_kontoudtog.php"]')); }, label: showKey(D3R, 'KD2'), kind: 'button' }),
    KD3: task(D3R, 'KD3', 1, { target: d3Only(['kontodetalje'], d3rBack), label: showKey(D3R, 'KD3'), kind: 'button' }),
    // Kontosaldo
    KS1: task(D3R, 'KS1', 3, { target: '#ksGridTable tfoot', label: showKey(D3R, 'KS1'), kind: 'row' }),
    KS2: task(D3R, 'KS2', 3, { target: '#ksGridTable', label: showKey(D3R, 'KS2'), kind: 'status' })
  };

  /** Order on the selection form: the three account reports, then what this company's form offers. */
  function d3rFormOrder(f) {
    return d3Ids(['RF1', 'RF2', 'RF3', 'RF4', f.husk ? '' : 'RF5', 'RF7', 'RF6', f.betalingslister ? 'RF9' : '',
      f.pos ? 'RF8' : '', 'RF10', f.pos ? 'RF11' : '', f.husk ? 'RF5' : '']);
  }

  /** Order on Åbne poster: a blocked window, the reminders waiting, overdue customers, settling, then the rest. */
  function d3rOpenOrder(f) {
    const ids = [];
    if (f.popupBlokeret) ids.push('OPP');
    if (f.kunRykkere) {
      // Without any reminder the page is empty (the overview needs one, rapportfunc.php:341-346; live check): change the view
      if (!f.rykkere) ids.push('OP8', 'OP11');
      else {
        // The sections that are open on the page say which reminders there are (posted ones only, live check)
        if (f.aabneRykkere) ids.push('RY1', 'RY2');
        if (f.bogfoerteRykkere) ids.push('RY3', 'RY4');
        ids.push('RY1', 'RY2', 'RY3', 'RY4', 'RY5', 'OP8', 'OP11');
      }
      return d3Ids(ids);
    }
    if (f.tom) {
      ids.push('OP8', 'OP9', 'OP11');
      if (f.rykkere) ids.push('RY1', 'RY3', 'RY4');
      return d3Ids(ids);
    }
    if (f.aabneRykkere) ids.push('RY1', 'RY2');
    if (f.forfaldne) ids.push('OP2');
    if (f.udlignKlar) ids.push('OP4');
    ids.push(f.forfaldne ? 'OP1' : '', 'OP5', 'OP3', 'OP9', 'OP8', 'OP7', f.flereSider ? 'OP12' : '', 'OP6');
    if (f.bogfoerteRykkere) ids.push('RY3', 'RY4');
    if (f.rykkere) ids.push('RY1', 'RY2', 'RY5', 'RY3', 'RY4');
    ids.push('OP10', 'OP11', 'OP1', 'OP2');
    return d3Ids(ids);
  }

  const d3RapportPage = {
    group: D3R,
    states: D3R_STATES,
    flags: D3R_FLAGS,

    name: function (ctx, st) {
      const s = d3State(st);
      if (s === 'openpost') return 'help.drap.name_openpost';
      if (s === 'kontokort' || s === 'kontodetalje') return 'help.drap.name_kontokort';
      if (s === 'kontosaldo') return 'help.drap.name_kontosaldo';
      return 'help.drap.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const f = d3Flags(D3R_FLAGS);
      const data = { mode: '' };
      let state = 'unknown';
      f.popupBlokeret = !!d3Q(doc, 'div[role="alert"] a[target="_blank"]');
      // The form is printed inside a table, so the browser closes it at once: its inputs are no descendants of it
      if (d3Q(doc, 'input[name="dato"]') && d3Q(doc, 'input[name="openpost"]')) {
        state = 'forside';
        f.husk = d3Checked(d3Q(doc, 'input[name="husk"]'));
        f.soegning = d3Val(d3Q(doc, 'input[name="dato"]')) !== '' || d3Val(d3Q(doc, 'input[name="konto"]')) !== '';
        f.betalingslister = !!d3Q(doc, 'span[onclick*="betalingsliste.php"]');
        f.pos = !!(d3Q(doc, 'a[href="kassespor.php"]') || d3Q(doc, 'input[name="saft"]'));
      } else if (d3Q(doc, 'select[name="aabenpostmode"]') || d3Q(doc, '#visAabnePosterTable') || d3Q(doc, '#opRykkere')) {
        state = 'openpost';
        data.mode = d3rMode(doc);
        f.kunRykkere = data.mode === 'skjul_aabenpost';
        f.allePoster = data.mode === 'vis_alle_poster';
        f.debetKredit = data.mode === 'kun_debet' || data.mode === 'kun_kredit';
        const grid = d3Q(doc, '#visAabnePosterTable');
        f.tom = !!grid && !d3Q(grid, 'input[name^="konto_id["]');
        // an overdue amount is red (showOpenPosts.php:447-448); the 'Ikke forfalden' column never is
        f.forfaldne = !!grid && !f.tom && !!d3Q(grid, 'tbody span[style*="255, 0, 0"]');
        f.udlignKlar = !!d3rUdlign(ctx);
        f.rykkere = !!d3Q(doc, '#opRykkere');
        f.aabneRykkere = !!d3Q(doc, '#rykkerOverviewTable1 a[href*="rykker.php?rykker_id="]');
        f.bogfoerteRykkere = !!d3Q(doc, '#rykkerOverviewTable3 a[href*="rykker.php?rykker_id="]');
        f.filter = d3Val(d3Q(doc, 'input[name="aging_bucket"]')) !== '' || d3Param(ctx, 'aging_bucket') !== '';
        f.flereSider = !!d3Q(doc, '.op-actionbar a.op-link[href*="openpost_page="]');
      } else if (d3Q(doc, '#kkPageFlex') || d3Q(doc, '#kkGridTable')) {
        state = 'kontokort';
        f.tom = !d3Q(doc, '#kkGridTable tr.kk-data-row');
        f.aabnePoster = !!d3rOpenAmount(ctx);
        f.udlignede = !!d3Q(doc, 'a[href*="unAlign="]');
        f.flereSider = !!d3Q(doc, '#kkNavButtons a.navbutton');
      } else if (d3Q(doc, '#ksPageFlex') || d3Q(doc, '#ksGridTable')) {
        state = 'kontosaldo';
        f.tom = d3All(doc, '#ksGridTable tbody tr').length < 1;
      } else if (d3Q(doc, 'select[name="typeSelect"]')) {
        state = 'kontodetalje';
        f.aabnePoster = !!d3rOpenAmount(ctx);
        f.udlignede = !!d3Q(doc, 'a[href*="unAlign="]');
        f.allePoster = d3rDetailAll(doc);
      }
      return { state: state, flags: f, data: data };
    },

    about: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      const out = [];
      if (s === 'forside') {
        out.push('help.drap.about.forside');
        if (f.husk) out.push('help.drap.about.husk');
      } else if (s === 'openpost') {
        if (f.kunRykkere) {
          out.push(f.rykkere ? 'help.drap.about.kun_rykkere' : 'help.drap.about.kun_rykkere_tom');
        } else {
          out.push(f.allePoster ? 'help.drap.about.alle_poster' : 'help.drap.about.openpost');
          if (f.popupBlokeret) out.push('help.drap.about.popup');
          else if (f.tom) out.push('help.drap.about.tom');
          else if (f.filter) out.push('help.drap.about.filter');
          else if (f.rykkere) out.push('help.drap.about.rykkere');
          else if (f.debetKredit) out.push('help.drap.about.debet_kredit');
        }
      } else if (s === 'kontokort') {
        out.push('help.drap.about.kontokort');
        out.push(f.tom ? 'help.drap.about.kontokort_tom' : 'help.drap.about.udlign');
      } else if (s === 'kontodetalje') {
        out.push('help.drap.about.kontodetalje', 'help.drap.about.udlign');
      } else if (s === 'kontosaldo') {
        out.push('help.drap.about.kontosaldo');
        if (f.tom) out.push('help.drap.about.kontosaldo_tom');
      }
      return out;
    },

    tasks: D3R_TASKS,

    order: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      if (s === 'forside') return d3Ids([f.popupBlokeret ? 'OPP' : ''].concat(d3rFormOrder(f)));
      if (s === 'openpost') return d3rOpenOrder(f);
      if (s === 'kontokort') {
        if (f.tom) return ['KO4'];
        return d3Ids([f.aabnePoster ? 'KO1' : '', 'KO3', f.udlignede ? 'KO2' : '', 'KO6', 'KO4', f.flereSider ? 'KO5' : '', 'KO1', 'KO2']);
      }
      if (s === 'kontodetalje') {
        return d3Ids([f.aabnePoster ? 'KO1' : '', 'KD1', 'KD2', 'KO3', f.udlignede ? 'KO2' : '', 'KD3', 'KO1', 'KO2']);
      }
      if (s === 'kontosaldo') return f.tom ? ['KO4'] : ['KS1', 'KS2', 'KO4'];
      return [];
    },

    // A blocked result window is the only thing that stops the work on these screens (debitor/rapport.php:175-190).
    problem: function (ctx, st) {
      const f = d3Fl(st);
      const s = d3State(st);
      return f.popupBlokeret && (s === 'forside' || s === 'openpost') ? 'OPP' : '';
    },

    fields: {
      dato: fieldKeys(D3R, 'dato'),
      konto: fieldKeys(D3R, 'konto'),
      husk: fieldKeys(D3R, 'husk'),
      kontonr: fieldKeys(D3R, 'kontonr'),
      aabenpostmode: fieldKeys(D3R, 'aabenpostmode'),
      kontoudtog: fieldKeys(D3R, 'kontoudtog'),
      rykkerbox: fieldKeys(D3R, 'rykkerbox'),
      visning: fieldKeys(D3R, 'visning'),
      linjer: fieldKeys(D3R, 'linjer')
    },

    // kontoudtog[3] and rykkerbox[2] are the row checkboxes (showOpenPosts.php:1251, rapportfunc.php:497); typeSelect and
    // the footer's page size (id kkPageSize) are camel case, which the field-key rule does not allow.
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (/^kontoudtog\[/.test(n)) return 'kontoudtog';
      if (/^rykkerbox\[/.test(n)) return 'rykkerbox';
      if (n === 'typeSelect') return 'visning';
      if (attr(el, 'id') === 'kkPageSize') return 'linjer';
      return null;
    },

    // Tilbage on Åbne poster (accesskey l, rapportfunc.php:311), Kontokort and Kontosaldo (:1243, :2131) and the customer's
    // kontokort (accountChart.php:330). The selection form has none in the sidebar layout.
    shortcuts: [
      { keys: 'Alt+L', text: 'help.drap.key.01', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.drap.q.q1.q',
        words: d3Words(['betalt', 'har betalt', 'stadig', 'står stadig', 'åben', 'åbne poster', 'udlign', 'udligne', 'udligning', 'indbetaling'],
          ['paid', 'has paid', 'still', 'open', 'open items', 'settle', 'settlement', 'payment', 'match'],
          ['betalt', 'har betalt', 'fortsatt', 'åpen', 'åpne poster', 'utlign', 'utligne', 'utligning', 'innbetaling']),
        intro: 'help.drap.q.q1.intro',
        steps: stepKeys(D3R, 'OP5', 1, 1).concat(stepKeys(D3R, 'KO1', 2, 3), ['help.drap.q.q1.step4']),
        show: D3R_TASKS.OP5.show
      },
      Q2: {
        q: 'help.drap.q.q2.q',
        words: d3Words(['minus', 'negativ', 'kredit', 'tilgode', 'til gode', 'for meget', 'betalt for meget', 'kreditnota', 'skylder kunden'],
          ['minus', 'negative', 'credit', 'overpaid', 'paid too much', 'credit note', 'owe the customer'],
          ['minus', 'negativ', 'kreditt', 'tilgode', 'til gode', 'for mye', 'betalt for mye', 'kreditnota']),
        intro: 'help.drap.q.q2.intro',
        steps: ['help.drap.q.q2.step1', 'help.drap.q.q2.step2']
      },
      Q3: {
        q: 'help.drap.q.q3.q',
        words: d3Words(['rykkergebyr', 'gebyr', 'rente', 'frist', 'frister', 'rykkerfrist', 'rykkertekst', 'inkasso', 'indstillinger'],
          ['reminder fee', 'fee', 'interest', 'deadline', 'deadlines', 'reminder text', 'debt collection', 'settings'],
          ['purregebyr', 'gebyr', 'rente', 'frist', 'frister', 'purrefrist', 'purretekst', 'inkasso', 'innstillinger']),
        steps: ['help.drap.q.q3.step1', 'help.drap.q.q3.step2', 'help.drap.q.q3.step3']
      },
      Q4: {
        q: 'help.drap.q.q4.q',
        words: d3Words(['ingen rykker', 'rykker', 'rykkere', 'virker ikke', 'sker ikke', 'ikke forfaldent', 'pop-up', 'popup', 'vindue', 'blokeret'],
          ['no reminder', 'reminder', 'reminders', 'not working', 'nothing happens', 'not due', 'pop-up', 'popup', 'window', 'blocked'],
          ['ingen påminnelse', 'påminnelse', 'purring', 'virker ikke', 'skjer ikke', 'ikke forfalt', 'popup', 'vindu', 'blokkert']),
        intro: 'help.drap.q.q4.intro',
        steps: ['help.drap.q.q4.step1', 'help.drap.q.q4.step2', 'help.drap.q.q4.step3'],
        show: D3R_TASKS.OP2.show
      },
      Q5: {
        q: 'help.drap.q.q5.q',
        words: d3Words(['dato', 'pr. dato', 'bestemt dato', 'tidligere', 'årsskifte', 'statusdag', 'skyldte', 'saldo', 'saldoliste'],
          ['date', 'as of', 'specific date', 'earlier', 'year end', 'balance', 'owed'],
          ['dato', 'per dato', 'bestemt dato', 'tidligere', 'årsskifte', 'skyldte', 'saldo', 'saldoliste']),
        steps: ['help.drap.q.q5.step1', 'help.drap.q.q5.step2', 'help.drap.q.q5.step3'],
        show: D3R_TASKS.RF4.show
      },
      Q6: {
        q: 'help.drap.q.q6.q',
        words: d3Words(['betalingslister', 'betalingsliste', 'kan ikke se', 'mangler', 'tilbagebetaling', 'udbetaling', 'bank'],
          ['payment lists', 'payment list', 'cannot see', 'missing', 'refund', 'payout', 'bank'],
          ['betalingslister', 'betalingsliste', 'kan ikke se', 'mangler', 'tilbakebetaling', 'utbetaling', 'bank']),
        steps: ['help.drap.q.q6.step1', 'help.drap.q.q6.step2'],
        show: D3R_TASKS.RF9.show
      }
    },

    // The paid invoice that stays open is what users ask most; a blocked window leads with the reminder it stopped
    questionOrder: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      if (s === 'forside') return f.popupBlokeret ? ['Q4', 'Q1', 'Q5'] : ['Q1', 'Q5', 'Q3'];
      if (s === 'openpost') {
        if (f.popupBlokeret) return ['Q4', 'Q1', 'Q3'];
        if (f.kunRykkere || f.rykkere) return ['Q4', 'Q3', 'Q1'];
        return ['Q1', 'Q4', 'Q2'];
      }
      if (s === 'kontokort' || s === 'kontodetalje') return ['Q1', 'Q2', 'Q5'];
      if (s === 'kontosaldo') return ['Q2', 'Q5', 'Q6'];
      return ['Q1', 'Q5', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  /** The customer's kontokort shows every post: only Vis åbne poster is offered as a link (accountChart.php:358-372). */
  function d3rDetailAll(doc) {
    const values = d3All(doc, 'select[name="typeSelect"] option').map(function (o) { return o.getAttribute('value') || ''; }).join(' ');
    return values.indexOf('kilde=openpost') !== -1 && values.indexOf('kilde=show_all') === -1;
  }

  PAGES['debitor/rapport.php'] = d3RapportPage;

  // ---- Rykkerbrev (debitor/rykker.php)
  // Reached from the Løbenr. of a reminder in Åbne poster (includes/rapportfunc.php:481), the front page's unpaid list
  // (index/dashboardIncludes/unpaidInvoices.php:83) and the print return (includes/udskriv.php:156); same frame. Evidence,
  // debitor/rykker.php, sidebar branch: Tilbage a[href=rapport.php?rapportart=openpost] accesskey L (:256), title
  // Rykkerbrev or Inkassosag (:257-258); form rykker (:276) with the hidden felt_5 (:281); Att., e-mail (:338-339), Send pr.
  // mail mail_fakt (:349), Valuta ny_valuta (:350), Sprog newFormLanguage with form languages (:358-365); the lines
  // (:424-441): an open reminder (status < 3) has beskrivelse[n], dkpris[n] (:428, never saved, :155) and the box
  // rykkerbox[n] (:436), plus ny_beskrivelse (:441); the buttons (:476-491): Opdatér, Send (with Send pr. mail) or Udskriv,
  // Slet valgte (open), Inkasso (posted, when the reminder form announces debt collection and 11 days have passed, :222-236).

  const D3Y = 'dryk';
  const D3Y_STATES = ['aaben', 'bogfoert', 'unknown'];
  const D3Y_FLAGS = ['mail', 'ingenEmail', 'inkasso', 'inkassoKlar'];

  /** The submits without a confirm: Opdatér, Udskriv (without Send pr. mail) and Slet valgte (open), in that order. */
  function d3yPlain(doc) {
    return d3All(doc, 'input[type="submit"]:not([onclick])');
  }

  function d3ySend(ctx) {
    return d3Q(ctx.doc, 'input[type="submit"][value="Send"]');
  }

  const D3Y_TASKS = {
    // Send shows only once Send pr. mail is saved (:479); before that there is no Send to ring
    RK1: task(D3Y, 'RK1', 4, { target: d3ySend, label: showKey(D3Y, 'RK1'), kind: 'button' }),
    RK2: task(D3Y, 'RK2', 2, {
      target: function (ctx) {
        if (d3ySend(ctx)) return null;               // Send replaces Udskriv (:479-483)
        const plain = d3yPlain(ctx.doc);
        return plain.length > 1 ? plain[1] : null;
      },
      label: showKey(D3Y, 'RK2'),
      kind: 'button'
    }),
    RK3: task(D3Y, 'RK3', 3, { target: 'input[name^="beskrivelse["]', label: fieldLabel(D3Y, 'beskrivelse'), kind: 'field' }),
    RK4: task(D3Y, 'RK4', 3, {
      target: function (ctx) {
        if (!d3Q(ctx.doc, 'input[name^="rykkerbox["]')) return null;   // Slet valgte only on an open reminder (:487)
        const plain = d3yPlain(ctx.doc);
        return plain.length > 1 ? plain[plain.length - 1] : null;
      },
      label: showKey(D3Y, 'RK4'),
      kind: 'button'
    }),
    RK5: task(D3Y, 'RK5', 3, { target: 'select[name="ny_valuta"]', label: fieldLabel(D3Y, 'ny_valuta'), kind: 'field' }),
    RK6: task(D3Y, 'RK6', 2, { target: 'input[type="submit"][value="Inkasso"]', label: showKey(D3Y, 'RK6'), kind: 'button' }),
    // [1] is Tilbage: posting, the next reminder and closing a reminder are done in the overview under Åbne poster
    // Only an open reminder is posted from Åbne poster; a posted one is not posted again (live check)
    RK7: task(D3Y, 'RK7', 3, { target: d3Only(['aaben'], 'a[href="rapport.php?rapportart=openpost"]'), label: showKey(D3Y, 'RK7'), kind: 'button' }),
    RK8: task(D3Y, 'RK8', 3, { target: 'input[name="email"]', label: fieldLabel(D3Y, 'email'), kind: 'field' }),
    RK9: task(D3Y, 'RK9', 3, { target: 'a[href="rapport.php?rapportart=openpost"]', label: showKey(D3Y, 'RK7'), kind: 'button' })
  };

  const d3RykkerPage = {
    group: D3Y,
    states: D3Y_STATES,
    flags: D3Y_FLAGS,

    name: function (ctx, st) {
      return d3Fl(st).inkasso ? 'help.dryk.name_inkasso' : 'help.dryk.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const f = d3Flags(D3Y_FLAGS);
      let state = 'unknown';
      if (d3Q(doc, 'input[name="rykker_id"]') || d3Q(doc, 'form[name="rykker"]')) {
        state = (d3Q(doc, 'input[name^="rykkerbox["]') || d3Q(doc, 'input[name="ny_beskrivelse"]')) ? 'aaben' : 'bogfoert';
        f.mail = d3Checked(d3Q(doc, 'input[name="mail_fakt"]'));
        f.ingenEmail = !!d3Q(doc, 'input[name="email"]') && d3Val(d3Q(doc, 'input[name="email"]')).indexOf('@') === -1;
        f.inkasso = d3Val(d3Q(doc, 'input[name="felt_5"]')) === 'inkasso';
        f.inkassoKlar = !!d3Q(doc, 'input[type="submit"][value="Inkasso"]');
      }
      return { state: state, flags: f, data: {} };
    },

    about: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      if (s === 'aaben') return ['help.dryk.about.aaben', f.mail ? 'help.dryk.about.mail' : 'help.dryk.about.udskriv'];
      if (s === 'bogfoert') return ['help.dryk.about.bogfoert', f.inkasso ? 'help.dryk.about.inkasso' : (f.inkassoKlar ? 'help.dryk.about.inkasso_klar' : 'help.dryk.about.naeste')];
      return [];
    },

    tasks: D3Y_TASKS,

    order: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      // With Send pr. mail there is no Udskriv (:479-483), so RK2 is left out
      // A customer without an e-mail address gets the letter printed first (Udskriv, RK2); mail needs an address
      const print1 = !f.mail && f.ingenEmail;
      if (s === 'aaben') {
        if (f.mail) return d3Ids([f.ingenEmail ? 'RK8' : '', 'RK1', 'RK3', 'RK7', 'RK4', 'RK5', 'RK8']);
        return d3Ids([print1 ? 'RK2' : '', 'RK1', 'RK2', 'RK3', 'RK7', 'RK4', 'RK5', 'RK8']);
      }
      if (s === 'bogfoert') {
        return d3Ids([f.mail && f.ingenEmail ? 'RK8' : '', f.inkassoKlar ? 'RK6' : '', 'RK9', print1 ? 'RK2' : '', 'RK1', f.mail ? '' : 'RK2', 'RK8']);
      }
      return [];
    },

    // Send pr. mail with no usable address: Send mails to nobody, and Opdatér refuses the address and clears the
    // flag (rykker.php:71-74, :453-482), so the address comes first (spec §18)
    problem: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      return (s === 'aaben' || s === 'bogfoert') && f.mail && f.ingenEmail ? 'RK8' : '';
    },

    fields: {
      kontakt: fieldKeys(D3Y, 'kontakt'),
      email: fieldKeys(D3Y, 'email'),
      mail_fakt: fieldKeys(D3Y, 'mail_fakt'),
      ny_valuta: fieldKeys(D3Y, 'ny_valuta'),
      sprog: fieldKeys(D3Y, 'sprog'),
      beskrivelse: fieldKeys(D3Y, 'beskrivelse'),
      pris: fieldKeys(D3Y, 'pris'),
      rykkerbox: fieldKeys(D3Y, 'rykkerbox'),
      ny_beskrivelse: fieldKeys(D3Y, 'ny_beskrivelse')
    },

    // The line fields are beskrivelse[n], dkpris[n] and rykkerbox[n] (:424-436); the language is newFormLanguage (:361).
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (/^beskrivelse\[/.test(n)) return 'beskrivelse';
      if (/^dkpris\[/.test(n)) return 'pris';
      if (/^rykkerbox\[/.test(n)) return 'rykkerbox';
      if (n === 'newFormLanguage') return 'sprog';
      return null;
    },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.dryk.key.01', group: 'keys' }        // Tilbage, accesskey L (:256)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.dryk.q.q1.q',
        words: d3Words(['kan ikke rette', 'låst', 'bogført', 'ændre', 'rette', 'slette rykker', 'felter væk'],
          ['cannot edit', 'locked', 'posted', 'change', 'edit', 'delete reminder'],
          ['kan ikke rette', 'låst', 'bokført', 'endre', 'rette', 'slette påminnelse']),
        intro: 'help.dryk.q.q1.intro',
        steps: ['help.dryk.q.q1.step1', 'help.dryk.q.q1.step2']
      },
      Q2: {
        q: 'help.dryk.q.q2.q',
        words: d3Words(['gebyr', 'rykkergebyr', 'rente', 'tekst', 'rykkertekst', 'layout', 'formular', 'logo'],
          ['fee', 'reminder fee', 'interest', 'text', 'reminder text', 'layout', 'form', 'logo'],
          ['gebyr', 'purregebyr', 'rente', 'tekst', 'purretekst', 'layout', 'skjema', 'logo']),
        steps: ['help.dryk.q.q2.step1', 'help.dryk.q.q2.step2', 'help.dryk.q.q2.step3']
      },
      Q3: {
        q: 'help.dryk.q.q3.q',
        words: d3Words(['mail', 'e-mail', 'send', 'sendt', 'kom ikke frem', 'modtager', 'adresse', 'send knap'],
          ['mail', 'email', 'send', 'sent', 'not received', 'recipient', 'address', 'send button'],
          ['e-post', 'epost', 'send', 'sendt', 'kom ikke frem', 'mottaker', 'adresse']),
        steps: stepKeys(D3Y, 'RK1', 1, 3).concat(['help.dryk.q.q3.step4']),
        show: D3Y_TASKS.RK1.show
      },
      // An open reminder is posted from Åbne rykkere under Åbne poster, which puts the fee on the customer (RK7)
      Q4: {
        q: 'help.dryk.q.q4.q',
        words: d3Words(['gebyr', 'rykkergebyr', 'bogfør', 'bogføre', 'kundens konto', 'saldo', 'hvornår'],
          ['fee', 'reminder fee', 'post', 'posting', 'customer account', 'balance', 'when'],
          ['gebyr', 'purregebyr', 'bokfør', 'bokføre', 'kundens konto', 'saldo', 'når']),
        intro: 'help.dryk.task.rk7.why',
        steps: stepKeys(D3Y, 'RK7', 1, 3),
        show: D3Y_TASKS.RK7.show
      }
    },

    // An open reminder: sending it, the fee and the text, then when the fee is posted; a posted one: why it is locked
    questionOrder: function (ctx, st) {
      const s = d3State(st);
      if (s === 'bogfoert') return ['Q1', 'Q3', 'Q2'];
      return ['Q3', 'Q2', 'Q4'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/rykker.php'] = d3RykkerPage;

  // ---- Betalinger til bank (debitor/betalinger.php)
  // Reached from Betalingsliste: Ny (betalinger.php?id=0, debitor/betalingsliste.php:94) and a list's Id (:142, :193),
  // same frame. Evidence, debitor/betalinger.php: Luk a[href=betalingsliste.php] accesskey L (:161); select[name=find]
  // Fra liste / Fra kontokort while the list is open (:168-175); Bemærkning listenote (:407-410); the line fields
  // erh[n], fra_kto[n], egen_ref[n], til_kto[n], kort_ref[n], modt_navn[n], belob[n], valuta[n], betalingsdato[n] and
  // the box slet[n] (:515-547), a field with an error on a red cell td[bgcolor=#FF0000] whose title says why
  // (:495-514, includes/payListFunc.php:29-80); without lines the hint 2752-2754 (:582-587); the buttons Gem (accesskey g),
  // the bank format select[name=format], Udskriv og luk or, with errors, Slet røde (accesskey u), Mail til modtagere
  // (accesskey m) (:589-617); a closed list keeps format and Udskriv (:605-614). Udskriv og luk writes the bank file and
  // marks the list as sent (:117-124); nothing is posted (includes/payListFunc.php).

  const D3B = 'dbet';
  const D3B_STATES = ['tom', 'aaben', 'lukket', 'unknown'];
  const D3B_FLAGS = ['fejl', 'mail'];

  /** The first field with an error: its cell is red (:495-514). */
  function d3bRed(ctx) {
    return d3Q(ctx.doc, 'td[bgcolor="#FF0000"] input');
  }

  const D3B_TASKS = {
    BT1: task(D3B, 'BT1', 3, { target: 'select[name="find"]', label: fieldLabel(D3B, 'find'), kind: 'field' }),
    BT2: task(D3B, 'BT2', 3, { target: d3bRed, label: showKey(D3B, 'BT2'), kind: 'field' }),
    BT3: task(D3B, 'BT3', 3, { target: 'input[name^="til_kto["]', label: fieldLabel(D3B, 'til_kto'), kind: 'field' }),
    BT4: task(D3B, 'BT4', 4, { target: d3Only(['aaben'], 'input[name="udskriv"]'), label: showKey(D3B, 'BT4'), kind: 'button' }),
    BT5: task(D3B, 'BT5', 2, { target: 'input[name="mail"]', label: showKey(D3B, 'BT5'), kind: 'button' }),
    BT6: task(D3B, 'BT6', 2, { target: 'input[name^="betalingsdato["]', label: fieldLabel(D3B, 'betalingsdato'), kind: 'field' }),
    BT7: task(D3B, 'BT7', 2, { target: function (ctx) { return d3Box(d3Q(ctx.doc, 'input[name^="slet["]')); }, label: fieldLabel(D3B, 'slet'), kind: 'field' }),
    BT8: task(D3B, 'BT8', 2, { target: d3Only(['lukket'], 'input[name="udskriv"]'), label: showKey(D3B, 'BT8'), kind: 'button' }),
    BT9: task(D3B, 'BT9', 2, { target: 'input[name="listenote"]', label: fieldLabel(D3B, 'listenote'), kind: 'field' })
  };

  const d3BetalingerPage = {
    group: D3B,
    states: D3B_STATES,
    flags: D3B_FLAGS,

    name: function () {
      return 'help.dbet.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const f = d3Flags(D3B_FLAGS);
      let state = 'unknown';
      const lines = !!d3Q(doc, 'input[name^="belob["]');
      if (d3Q(doc, 'select[name="find"]')) state = lines ? 'aaben' : 'tom';
      else if (d3Q(doc, 'select[name="format"]') || d3Q(doc, 'input[name="udskriv"]')) state = 'lukket';
      f.fejl = state === 'aaben' && !!(d3Q(doc, 'input[name="slet_ugyldige"]') || d3Q(doc, 'td[bgcolor="#FF0000"]'));
      f.mail = !!d3Q(doc, 'input[name="mail"]');
      return { state: state, flags: f, data: {} };
    },

    about: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      if (s === 'tom') return ['help.dbet.about.side', 'help.dbet.about.tom'];
      if (s === 'aaben') return ['help.dbet.about.side', f.fejl ? 'help.dbet.about.fejl' : 'help.dbet.about.aaben'];
      if (s === 'lukket') return ['help.dbet.about.lukket'];
      return [];
    },

    tasks: D3B_TASKS,

    order: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      if (s === 'tom') return ['BT1', 'BT9'];
      // With errors Udskriv og luk is replaced by Slet røde (:600-604), so BT4 waits until they are gone
      if (s === 'aaben') {
        if (f.fejl) return d3Ids(['BT2', 'BT3', 'BT7', 'BT6', 'BT1', 'BT9']);
        return d3Ids(['BT3', 'BT4', 'BT6', 'BT7', 'BT1', f.mail ? 'BT5' : '', 'BT9']);
      }
      if (s === 'lukket') return d3Ids(['BT8', f.mail ? 'BT5' : '']);
      return [];
    },

    problem: function (ctx, st) {
      return d3State(st) === 'aaben' && d3Fl(st).fejl ? 'BT2' : '';
    },

    fields: {
      find: fieldKeys(D3B, 'find'),
      listenote: fieldKeys(D3B, 'listenote'),
      erh: fieldKeys(D3B, 'erh'),
      fra_kto: fieldKeys(D3B, 'fra_kto'),
      egen_ref: fieldKeys(D3B, 'egen_ref'),
      til_kto: fieldKeys(D3B, 'til_kto'),
      kort_ref: fieldKeys(D3B, 'kort_ref'),
      modt_navn: fieldKeys(D3B, 'modt_navn'),
      belob: fieldKeys(D3B, 'belob'),
      valuta: fieldKeys(D3B, 'valuta'),
      betalingsdato: fieldKeys(D3B, 'betalingsdato'),
      slet: fieldKeys(D3B, 'slet'),
      format: fieldKeys(D3B, 'format')
    },

    // The line fields are named field[n] (:515-547), which the default rule keeps whole.
    fieldKey: function (el) {
      const m = /^([a-z_]+)\[\d+\]$/.exec(attr(el, 'name'));
      return m ? m[1] : null;
    },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.dbet.key.01', group: 'keys' },     // Luk (:161)
      { keys: 'Alt+G', text: 'help.dbet.key.02', group: 'keys' },     // Gem (:589)
      { keys: 'Alt+U', text: 'help.dbet.key.03', group: 'keys' },     // Udskriv og luk, Slet røde, Udskriv (:600-613)
      { keys: 'Alt+M', text: 'help.dbet.key.04', group: 'keys' }      // Mail til modtagere (:616)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.dbet.q.q1.q',
        words: d3Words(['rød', 'røde', 'fejl', 'ugyldig', 'modtagerkonto', 'betalingsdato', 'overskredet', 'cifre'],
          ['red', 'error', 'invalid', 'recipient account', 'payment date', 'passed', 'digits'],
          ['rød', 'røde', 'feil', 'ugyldig', 'mottakerkonto', 'betalingsdato', 'overskredet', 'sifre']),
        intro: 'help.dbet.q.q1.intro',
        steps: stepKeys(D3B, 'BT2', 1, 3),
        show: D3B_TASKS.BT2.show
      },
      Q2: {
        q: 'help.dbet.q.q2.q',
        words: d3Words(['bogført', 'bogføre', 'bogføres', 'postering', 'kassekladde', 'udbetaling', 'tilbagebetaling'],
          ['posted', 'post', 'entry', 'cash journal', 'payout', 'refund'],
          ['bokført', 'bokføre', 'postering', 'kassekladd', 'utbetaling', 'tilbakebetaling']),
        steps: ['help.dbet.q.q2.step1', 'help.dbet.q.q2.step2', 'help.dbet.q.q2.step3']
      }
    },

    questionOrder: function (ctx, st) {
      return d3Fl(st).fejl ? ['Q1', 'Q2'] : ['Q2', 'Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/betalinger.php'] = d3BetalingerPage;

  // ---- Betalingsliste (debitor/betalingsliste.php)
  // Reached from Betalingslister on the report form (frontPageOldMenu.php:94) and Luk on a list (betalinger.php:161).
  // Evidence, debitor/betalingsliste.php: the delete question with Nej/Ja (:47-63); the bar Luk a[href=rapport.php]
  // accesskey L, Indstillinger, Ny a[href=betalinger.php?id=0] accesskey N (:92-94); Vis alle / Vis egne (:98-103); open
  // lists link betalinger.php?tjek= (:142), closed ones betalinger.php?liste_id= (:193); the delete icon (:158-160, :209-211).

  const D3L = 'dbet';
  const D3L_TASKS = {
    BL1: task(D3L, 'BL1', 2, { target: 'a[href="betalinger.php?id=0"]', label: showKey(D3L, 'BL1'), kind: 'button' }),
    BL2: task(D3L, 'BL2', 2, { target: 'a[href*="betalinger.php?"][href*="liste_id="]', label: showKey(D3L, 'BL2'), kind: 'row' }),
    BL3: task(D3L, 'BL3', 2, { target: 'a[href*="betalingsliste.php?sort="][href*="vis=alle"]', label: showKey(D3L, 'BL3'), kind: 'button' }),
    BL4: task(D3L, 'BL4', 3, { target: 'span[onclick*="deleteList="]', label: showKey(D3L, 'BL4'), kind: 'button' }),
    BL5: task(D3L, 'BL5', 2, { target: 'a[href="betalinger_settings.php"]', label: showKey(D3L, 'BL5'), kind: 'button' }),
    BL6: task(D3L, 'BL6', 2, { target: 'span[onclick*="confirmDelete=yes"]', label: showKey(D3L, 'BL6'), kind: 'button' })
  };

  const d3BetalingslistePage = {
    group: D3L,
    states: ['liste', 'tom', 'slet', 'unknown'],
    flags: ['visAlle'],

    name: function () {
      return 'help.dbet.name_liste';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const f = { visAlle: d3Param(ctx, 'vis') === 'alle' };
      let state = 'unknown';
      if (d3Q(doc, 'span[onclick*="confirmDelete=yes"]')) state = 'slet';
      else if (d3Q(doc, 'a[href*="betalinger.php?"][href*="liste_id="]')) state = 'liste';
      else if (d3Q(doc, 'a[href="betalinger.php?id=0"]')) state = 'tom';
      return { state: state, flags: f, data: {} };
    },

    about: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      if (s === 'liste' || s === 'tom') return ['help.dbet.about.liste', s === 'tom' ? (f.visAlle ? 'help.dbet.about.liste_tom_alle' : 'help.dbet.about.liste_tom') : 'help.dbet.about.liste_lukket'];
      if (s === 'slet') return ['help.dbet.about.slet'];
      return [];
    },

    tasks: D3L_TASKS,

    order: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      if (s === 'slet') return ['BL6'];
      if (s === 'tom') return d3Ids(['BL1', f.visAlle ? '' : 'BL3', 'BL5']);
      if (s === 'liste') return d3Ids(['BL2', 'BL1', f.visAlle ? '' : 'BL3', 'BL4', 'BL5']);
      return [];
    },

    fields: {},

    shortcuts: [
      { keys: 'Alt+L', text: 'help.dbet.key.05', group: 'keys' },     // Luk (:92)
      { keys: 'Alt+N', text: 'help.dbet.key.06', group: 'keys' }      // Ny (:94)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q3: {
        q: 'help.dbet.q.q3.q',
        words: d3Words(['lukket', 'åben', 'åbne', 'afsendt', 'sendt', 'liste', 'lister', 'låst'],
          ['closed', 'open', 'sent', 'list', 'lists', 'locked'],
          ['lukket', 'åpen', 'åpne', 'sendt', 'liste', 'lister', 'låst']),
        steps: ['help.dbet.q.q3.step1', 'help.dbet.q.q3.step2']
      }
    },

    questionOrder: function () {
      return ['Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/betalingsliste.php'] = d3BetalingslistePage;

  // ---- Import fra PBS (debitor/pbs_import.php)
  // Reached from Import PBS on the order list's BS tab (debitor/ordLstIncludes/topLine.php:100-104), same frame; the tab
  // shows when the company has a PBS number (debitor/ordreliste.php:431-433). Evidence, debitor/pbs_import.php: Luk
  // a[href="ordreliste.php?"] accesskey L with a query string (:46-47); the warning PBS nr mangler i stamdata (:58); the
  // upload form input[name=uploadedfile] and Hent (:186-195); the result table Kundenr / Navn / PBS nr / Handling
  // (:69, :121-147). The import updates the customer cards and the open subscription orders right away (:76-79, :124-139).

  const D3P = 'dbet';
  const D3P_TASKS = {
    PI1: task(D3P, 'PI1', 3, { target: 'input[name="uploadedfile"]', label: fieldLabel(D3P, 'uploadedfile'), kind: 'field' }),
    PI2: task(D3P, 'PI2', 2, {
      target: d3Only(['resultat'], function (ctx) {
        const tds = d3All(ctx.doc, 'td');
        for (let i = 0; i < tds.length; i++) {
          if (/^\s*Handling\s*$/.test(d3Text(tds[i]))) return tds[i].closest ? tds[i].closest('table') : tds[i];
        }
        return null;
      }),
      label: showKey(D3P, 'PI2'),
      kind: 'status'
    }),
    PI3: task(D3P, 'PI3', 2),                  // the setting is in Indstillinger, outside the frame
    PI4: task(D3P, 'PI4', 1, { target: 'a[accesskey="L"][href^="ordreliste.php"]', label: showKey(D3P, 'PI4'), kind: 'button' })
  };

  const d3PbsImportPage = {
    group: D3P,
    states: ['upload', 'resultat', 'unknown'],
    flags: ['manglerPbsNr'],

    name: function () {
      return 'help.dbet.name_pbs';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const body = d3Text(doc.body || d3Q(doc, 'body'));
      const f = { manglerPbsNr: body.indexOf('PBS nr mangler') !== -1 };
      let state = 'unknown';
      if (d3Q(doc, 'input[name="uploadedfile"]')) state = 'upload';
      else if (/Handling/.test(body)) state = 'resultat';
      return { state: state, flags: f, data: {} };
    },

    about: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      const out = [];
      if (s === 'upload') out.push('help.dbet.about.pbs');
      else if (s === 'resultat') out.push('help.dbet.about.pbs_resultat');
      if (f.manglerPbsNr && (s === 'upload' || s === 'resultat')) out.push('help.dbet.about.pbs_nr');
      return out;
    },

    tasks: D3P_TASKS,

    order: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      if (s === 'upload') return d3Ids([f.manglerPbsNr ? 'PI3' : '', 'PI1', 'PI4']);
      if (s === 'resultat') return d3Ids([f.manglerPbsNr ? 'PI3' : '', 'PI2', 'PI4', 'PI1']);
      return [];
    },

    problem: function (ctx, st) {
      const s = d3State(st);
      return d3Fl(st).manglerPbsNr && (s === 'upload' || s === 'resultat') ? 'PI3' : '';
    },

    fields: { uploadedfile: fieldKeys(D3P, 'uploadedfile') },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.dbet.key.05', group: 'keys' }      // Luk (:47)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q4: {
        q: 'help.dbet.q.q4.q',
        words: d3Words(['betalingsservice', 'pbs', 'nets', 'aftale', 'aftaler', 'tilmelding', 'afmelding', 'fil', 'leverandørservice'],
          ['direct debit', 'betalingsservice', 'pbs', 'nets', 'agreement', 'agreements', 'sign up', 'cancel', 'file'],
          ['betalingsservice', 'pbs', 'nets', 'avtale', 'avtaler', 'påmelding', 'avmelding', 'fil']),
        steps: ['help.dbet.q.q4.step1', 'help.dbet.q.q4.step2']
      }
    },

    questionOrder: function () {
      return ['Q4'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/pbs_import.php'] = d3PbsImportPage;

  // ---- Genfakturer (debitor/genfakturer.php)
  // Reached from Genfakturer with ticked invoices on the order list's Fakturaer tab (debitor/ordreliste.php:1969-1990), a
  // refresh in the same frame. Evidence, debitor/genfakturer.php: the form genfakturer (:158) with the Ja/Nej radios
  // org_nr (Brug originalt ordrenummer), komplet (Gennemfør komplet fakturering), fakt_dato (Brug forvalgt fakturadato),
  // opdat_pris (Opdater priser.), opdat_text (Opdater varetekster.), slet_gfdato (Ryd genfaktdato fra gl.fakt) and Ok /
  // Afbryd (:160-169). Ok copies each invoice to a new order dated from Genfakt or today (:212-245); with komplet it is
  // delivered, posted and printed in a new window at once (:126-150), without it the orders open in Ret
  // abonnementsordrer (:154).

  const D3G = 'dgen';
  const D3G_TASKS = {
    GF1: task(D3G, 'GF1', 3, { target: 'input[name="komplet"]', label: fieldLabel(D3G, 'komplet'), kind: 'field' }),
    GF2: task(D3G, 'GF2', 2, { target: 'input[name="fakt_dato"]', label: fieldLabel(D3G, 'fakt_dato'), kind: 'field' }),
    GF3: task(D3G, 'GF3', 2, { target: 'input[name="opdat_pris"]', label: fieldLabel(D3G, 'opdat_pris'), kind: 'field' }),
    GF4: task(D3G, 'GF4', 2, { target: 'input[name="org_nr"]', label: fieldLabel(D3G, 'org_nr'), kind: 'field' }),
    GF5: task(D3G, 'GF5', 3, { target: 'input[name="Ok"]', label: showKey(D3G, 'GF5'), kind: 'button' }),
    GF6: task(D3G, 'GF6', 1, { target: 'input[name="Afbryd"]', label: showKey(D3G, 'GF6'), kind: 'button' })
  };

  const d3GenfakturerPage = {
    group: D3G,
    states: ['valg', 'unknown'],
    flags: [],

    name: function () {
      return 'help.dgen.name';
    },

    detect: function (ctx) {
      return { state: d3Q(ctx.doc, 'form[name="genfakturer"]') ? 'valg' : 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      return d3State(st) === 'valg' ? ['help.dgen.about.valg', 'help.dgen.about.komplet'] : [];
    },

    tasks: D3G_TASKS,

    order: function (ctx, st) {
      return d3State(st) === 'valg' ? ['GF1', 'GF2', 'GF5', 'GF3', 'GF4', 'GF6'] : [];
    },

    fields: {
      org_nr: fieldKeys(D3G, 'org_nr'),
      komplet: fieldKeys(D3G, 'komplet'),
      fakt_dato: fieldKeys(D3G, 'fakt_dato'),
      opdat_pris: fieldKeys(D3G, 'opdat_pris'),
      opdat_text: fieldKeys(D3G, 'opdat_text'),
      slet_gfdato: fieldKeys(D3G, 'slet_gfdato')
    },

    shortcuts: [],

    questions: {
      Q1: {
        q: 'help.dgen.q.q1.q',
        words: d3Words(['genfakturer', 'genfakturering', 'abonnement', 'abonnementer', 'fast faktura', 'hver måned', 'gentag', 'genfakt'],
          ['re-invoice', 'recurring', 'subscription', 'subscriptions', 'every month', 'repeat'],
          ['gjenfakturer', 'gjenfakturering', 'abonnement', 'abonnementer', 'hver måned', 'gjenta']),
        steps: ['help.dgen.q.q1.step1', 'help.dgen.q.q1.step2', 'help.dgen.q.q1.step3']
      }
    },

    questionOrder: function () {
      return ['Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/genfakturer.php'] = d3GenfakturerPage;

  // ---- Ret abonnementsordrer (debitor/ret_genfakt.php)
  // Reached from Ret with ticked invoices on the Fakturaer tab (debitor/ordreliste.php:1986-1987) and from Genfakturer
  // without komplet (debitor/genfakturer.php:154), same frame. Evidence, debitor/ret_genfakt.php: form ret_genfakt
  // (:452); per order the head fakturadato_n, genfakt_n, betalingsbet_n + betalingsdage_n, email_n, udskriv_til_n and,
  // when the order's customer data differ from the card, sync_stamdata_n / sync_email_n (:472-540); the lines posnr_n,
  // varenr_n, dkantal_n, beskrivelse_n, dkpris_n, dkrabat_n and the box kdo_n (Kun denne ordre) (:560-640); Gem
  // (accesskey g) and, unless a check failed, Fakturer (accesskey f) (:659-661). A failed check (invoice date outside
  // the financial year, Genfakt not after the invoice date, a PBS date in the wrong month, :240-257) shows an alert
  // and hides Fakturer. A date or payment term ending in * is copied to the orders with the same value (:184-215).
  // Fakturer delivers and posts every order on the page and prints them (:312-389).

  const D3E = 'dgen';
  const D3E_TASKS = {
    RG1: task(D3E, 'RG1', 3, { target: 'input[name^="fakturadato_"]', label: fieldLabel(D3E, 'fakturadato'), kind: 'field' }),
    RG2: task(D3E, 'RG2', 3, { target: 'input[name^="dkantal_"]', label: fieldLabel(D3E, 'dkantal'), kind: 'field' }),
    RG3: task(D3E, 'RG3', 2, { target: 'input[name^="sync_stamdata_"]', label: fieldLabel(D3E, 'sync_stamdata'), kind: 'field' }),
    RG4: task(D3E, 'RG4', 2, { target: 'select[name^="udskriv_til_"]', label: fieldLabel(D3E, 'udskriv_til'), kind: 'field' }),
    RG5: task(D3E, 'RG5', 3, { target: 'input[type="submit"][value="Fakturer"]', label: showKey(D3E, 'RG5'), kind: 'button' }),
    RG6: task(D3E, 'RG6', 2, { target: 'input[name^="kdo_"]', label: fieldLabel(D3E, 'kdo'), kind: 'field' }),
    RG7: task(D3E, 'RG7', 3, { target: 'input[type="submit"][value="Gem"]', label: showKey(D3E, 'RG7'), kind: 'button' }),
    RG8: task(D3E, 'RG8', 2, { target: 'input[name^="genfakt_"]', label: fieldLabel(D3E, 'genfakt'), kind: 'field' })
  };

  const d3RetGenfaktPage = {
    group: D3E,
    states: ['ret', 'fejl', 'unknown'],
    flags: ['forskel'],

    name: function () {
      return 'help.dgen.name_ret';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state = 'unknown';
      if (d3Q(doc, 'form[name="ret_genfakt"]')) state = d3Q(doc, 'input[type="submit"][value="Fakturer"]') ? 'ret' : 'fejl';
      return { state: state, flags: { forskel: !!d3Q(doc, 'input[name^="sync_stamdata_"], input[name^="sync_email_"]') }, data: {} };
    },

    about: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      if (s === 'ret') return ['help.dgen.about.ret', f.forskel ? 'help.dgen.about.forskel' : 'help.dgen.about.fakturer'];
      if (s === 'fejl') return ['help.dgen.about.fejl'];
      return [];
    },

    tasks: D3E_TASKS,

    order: function (ctx, st) {
      const s = d3State(st);
      const f = d3Fl(st);
      if (s === 'fejl') return d3Ids(['RG7', 'RG1', f.forskel ? 'RG3' : '', 'RG8', 'RG2']);
      if (s === 'ret') return d3Ids([f.forskel ? 'RG3' : '', 'RG1', 'RG2', 'RG5', 'RG4', 'RG6', 'RG8']);
      return [];
    },

    problem: function (ctx, st) {
      return d3State(st) === 'fejl' ? 'RG7' : '';
    },

    fields: {
      fakturadato: fieldKeys(D3E, 'fakturadato'),
      genfakt: fieldKeys(D3E, 'genfakt'),
      betalingsbet: fieldKeys(D3E, 'betalingsbet'),
      betalingsdage: fieldKeys(D3E, 'betalingsbet'),
      email: fieldKeys(D3E, 'email'),
      udskriv_til: fieldKeys(D3E, 'udskriv_til'),
      sync_stamdata: fieldKeys(D3E, 'sync_stamdata'),
      sync_email: fieldKeys(D3E, 'sync_stamdata'),
      dkantal: fieldKeys(D3E, 'dkantal'),
      dkpris: fieldKeys(D3E, 'dkpris'),
      dkrabat: fieldKeys(D3E, 'dkpris'),
      beskrivelse: fieldKeys(D3E, 'beskrivelse'),
      kdo: fieldKeys(D3E, 'kdo')
    },

    shortcuts: [
      { keys: 'Alt+G', text: 'help.dgen.key.01', group: 'keys' },     // Gem (:660)
      { keys: 'Alt+F', text: 'help.dgen.key.02', group: 'keys' }      // Fakturer (:661)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q2: {
        q: 'help.dgen.q.q2.q',
        words: d3Words(['fakturer', 'knap', 'væk', 'mangler', 'kan ikke fakturere', 'fejl', 'genfakturering'],
          ['invoice', 'button', 'gone', 'missing', 'cannot invoice', 'error'],
          ['fakturer', 'knapp', 'borte', 'mangler', 'kan ikke fakturere', 'feil']),
        steps: stepKeys(D3E, 'RG7', 1, 3),
        show: D3E_TASKS.RG7.show
      },
      Q3: {
        q: 'help.dgen.q.q3.q',
        words: d3Words(['kdo', 'kun denne ordre', 'engangs', 'én gang', 'linje', 'næste gang'],
          ['kdo', 'this order only', 'one-off', 'once', 'line', 'next time'],
          ['kdo', 'bare denne ordren', 'engangs', 'én gang', 'linje', 'neste gang']),
        steps: stepKeys(D3E, 'RG6', 1, 2),
        show: D3E_TASKS.RG6.show
      }
    },

    questionOrder: function (ctx, st) {
      return d3State(st) === 'fejl' ? ['Q2', 'Q3'] : ['Q3', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/ret_genfakt.php'] = d3RetGenfaktPage;

  // ---- Massefakturering (debitor/massefakt.php)
  // Reached from Fakturér alt under the Ordrer tab when the company uses mass invoicing (debitor/ordreliste.php:2690-2699).
  // Evidence, debitor/massefakt.php: Luk a[href*="ordreliste.php?valg="] accesskey L (:56); the run delivers and posts
  // every approved order whose stock lines can be delivered in full, ten at a time with the line Vent - n ordrer tilbage
  // (:84-112, find_next() :306-356) and prints them (:118); what is left shows Ingen ordrer til levering (:195). A date
  // outside the active financial year stops it with an alert before anything happens (:66-70).

  const D3M = 'dgen';
  // Both tasks start with Luk, back to the Ordrer tab: the run is over, and what is left is done on the orders
  const D3M_TASKS = {
    MF1: task(D3M, 'MF1', 3, { target: 'a[accesskey="L"][href*="ordreliste.php"]', label: showKey(D3M, 'MF1'), kind: 'button' }),
    MF2: task(D3M, 'MF2', 3, { target: 'a[accesskey="L"][href*="ordreliste.php"]', label: showKey(D3M, 'MF1'), kind: 'button' })
  };

  const d3MassefaktPage = {
    group: D3M,
    states: ['koerer', 'faerdig', 'unknown'],
    flags: [],

    name: function () {
      return 'help.dgen.name_masse';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const body = d3Text(doc.body || d3Q(doc, 'body'));
      let state = 'unknown';
      if (/Vent - \d+ ordrer tilbage/.test(body)) state = 'koerer';
      else if (d3Q(doc, 'a[accesskey="L"][href*="ordreliste.php"]')) state = 'faerdig';
      return { state: state, flags: {}, data: {} };
    },

    about: function (ctx, st) {
      const s = d3State(st);
      if (s === 'koerer') return ['help.dgen.about.masse_koerer'];
      if (s === 'faerdig') return ['help.dgen.about.masse', 'help.dgen.about.masse_faerdig'];
      return [];
    },

    tasks: D3M_TASKS,

    order: function (ctx, st) {
      const s = d3State(st);
      if (s === 'faerdig') return ['MF1', 'MF2'];
      return [];
    },

    fields: {},

    shortcuts: [
      { keys: 'Alt+L', text: 'help.dgen.key.03', group: 'keys' }      // Luk (:56)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q4: {
        q: 'help.dgen.q.q4.q',
        words: d3Words(['ikke faktureret', 'mangler', 'lager', 'beholdning', 'kan ikke leveres', 'fakturér alt', 'massefakturering'],
          ['not invoiced', 'missing', 'stock', 'cannot be delivered', 'invoice all', 'mass invoicing'],
          ['ikke fakturert', 'mangler', 'lager', 'beholdning', 'kan ikke leveres', 'fakturer alt', 'massefakturering']),
        steps: stepKeys(D3M, 'MF1', 1, 3),
        show: D3M_TASKS.MF1.show
      }
    },

    questionOrder: function () {
      return ['Q4'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/massefakt.php'] = d3MassefaktPage;

  // ---- Top 100 (debitor/top100.php)
  // Reached from Top 100 on the report form (frontPageOldMenu.php:83), same frame. Evidence, debitor/top100.php: Tilbage
  // a[href=../debitor/rapport.php] accesskey L, the title Top 100 i perioden and Periode a[href*="ret=on"] accesskey P
  // (:84-95); the list #t1GridTable with Nr., Kontonr., Firmanavn, Omsætning DKK (:96-117, :140-150), the sum of the
  // invoiced orders' amounts without VAT, in kroner (:142); with ret=on the period form: Vælg period, input
  // periode (ddmmyy:ddmmyy, default the last 12 months, :46-56) and OK accesskey O (:127-133).

  const D3T = 'dstat';
  const D3T_TASKS = {
    TP1: task(D3T, 'TP1', 3, { target: function (ctx) { return d3Q(ctx.doc, 'input[name="periode"]') || d3Inner(d3Q(ctx.doc, 'a[href*="top100.php?periode="][href*="ret=on"]')); }, label: showKey(D3T, 'TP1'), kind: 'button' }),
    TP2: task(D3T, 'TP2', 2, { target: '#t1GridTable', label: showKey(D3T, 'TP2'), kind: 'status' }),
    TP3: task(D3T, 'TP3', 1, { target: function (ctx) { return d3Inner(d3Q(ctx.doc, 'a[accesskey="L"][href*="rapport.php"]')); }, label: showKey(D3T, 'TP3'), kind: 'button' })
  };

  const d3Top100Page = {
    group: D3T,
    states: ['liste', 'periode', 'unknown'],
    flags: [],

    name: function () {
      return 'help.dstat.name_top';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state = 'unknown';
      if (d3Q(doc, 'input[name="periode"]')) state = 'periode';
      else if (d3Q(doc, '#t1GridTable') || d3Q(doc, 'a[href*="top100.php?periode="]')) state = 'liste';
      return { state: state, flags: {}, data: {} };
    },

    about: function (ctx, st) {
      const s = d3State(st);
      if (s === 'liste') return ['help.dstat.about.top'];
      if (s === 'periode') return ['help.dstat.about.top_periode'];
      return [];
    },

    tasks: D3T_TASKS,

    order: function (ctx, st) {
      const s = d3State(st);
      if (s === 'liste') return ['TP2', 'TP1', 'TP3'];
      if (s === 'periode') return ['TP1', 'TP3'];
      return [];
    },

    fields: { periode: fieldKeys(D3T, 'periode') },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.dstat.key.01', group: 'keys' },    // Tilbage (:86)
      { keys: 'Alt+P', text: 'help.dstat.key.02', group: 'keys' },    // Periode (:92)
      { keys: 'Alt+O', text: 'help.dstat.key.03', group: 'keys' }     // OK in the period form (:130)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.dstat.q.q1.q',
        words: d3Words(['moms', 'uden moms', 'med moms', 'omsætning', 'beløb', 'valuta', 'kroner'],
          ['vat', 'without vat', 'with vat', 'revenue', 'amount', 'currency'],
          ['mva', 'uten mva', 'med mva', 'omsetning', 'beløp', 'valuta', 'kroner']),
        steps: ['help.dstat.q.q1.step1', 'help.dstat.q.q1.step2']
      }
    },

    questionOrder: function () {
      return ['Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/top100.php'] = d3Top100Page;

  // ---- Salg pr. postnummer (debitor/salg_postnr.php)
  // Reached from Salg pr. Postnr on the report form (frontPageOldMenu.php:99, :105), same frame. Evidence,
  // debitor/salg_postnr.php: Tilbage a[href=../debitor/rapport.php] accesskey L (:119); the grid
  // #datatable-wrapper-salg_postnr (includes/grid.php:1264) of invoiced orders and credit notes grouped by postcode, town
  // and department (:85-101) with the columns Postnr, By, Afdeling, Antal ordrer, Beløb, Avg. order DKK, Fakturadato
  // (:145-260); the search row becomes #postnr_range_input (fra:til), the department list
  // select[name="search[salg_postnr][afd]"] and the date range #dato_range_picker (:385-460); sortable headers
  // th.<field>[onclick*=setSort] (includes/grid.php:1361-1368).

  const D3S = 'dstat';
  const D3S_TASKS = {
    SP1: task(D3S, 'SP1', 3, { target: '#dato_range_picker', label: fieldLabel(D3S, 'dato_range_picker'), kind: 'field' }),
    SP2: task(D3S, 'SP2', 2, { target: '#postnr_range_input', label: fieldLabel(D3S, 'postnr_range_input'), kind: 'field' }),
    SP3: task(D3S, 'SP3', 2, { target: 'select[name="search[salg_postnr][afd]"]', label: fieldLabel(D3S, 'search:afd'), kind: 'field' }),
    SP4: task(D3S, 'SP4', 2, { target: '#datatable-wrapper-salg_postnr th.belob', label: showKey(D3S, 'SP4'), kind: 'button' }),
    SP5: task(D3S, 'SP5', 1, { target: function (ctx) { return d3Inner(d3Q(ctx.doc, 'a[accesskey="L"][href*="rapport.php"]')); }, label: showKey(D3S, 'TP3'), kind: 'button' })
  };

  const d3SalgPostnrPage = {
    group: D3S,
    states: ['liste', 'unknown'],
    flags: ['filter'],

    name: function () {
      return 'help.dstat.name_postnr';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const state = d3Q(doc, '#datatable-wrapper-salg_postnr') ? 'liste' : 'unknown';
      const filter = ['seneste_ordre', 'postnr', 'afd', 'bynavn'].some(function (k) {
        return d3Param(ctx, 'search[salg_postnr][' + k + ']').replace(/:/g, '') !== '';
      });
      return { state: state, flags: { filter: filter }, data: {} };
    },

    about: function (ctx, st) {
      if (d3State(st) !== 'liste') return [];
      return ['help.dstat.about.postnr', d3Fl(st).filter ? 'help.dstat.about.postnr_filter' : 'help.dstat.about.postnr_alt'];
    },

    tasks: D3S_TASKS,

    order: function (ctx, st) {
      return d3State(st) === 'liste' ? ['SP1', 'SP2', 'SP4', 'SP3', 'SP5'] : [];
    },

    fields: {
      dato_range_picker: fieldKeys(D3S, 'dato_range_picker'),
      postnr_range_input: fieldKeys(D3S, 'postnr_range_input'),
      'search:afd': fieldKeys(D3S, 'search:afd'),
      'search:bynavn': fieldKeys(D3S, 'search:bynavn')
    },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.dstat.key.01', group: 'keys' }     // Tilbage (:119)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q2: {
        q: 'help.dstat.q.q2.q',
        words: d3Words(['moms', 'uden moms', 'kreditnota', 'kreditnotaer', 'fakturadato', 'seneste', 'hvilke salg', 'kasse'],
          ['vat', 'without vat', 'credit note', 'credit notes', 'invoice date', 'latest', 'which sales'],
          ['mva', 'uten mva', 'kreditnota', 'kreditnotaer', 'fakturadato', 'siste', 'hvilke salg']),
        steps: ['help.dstat.q.q2.step1', 'help.dstat.q.q2.step2']
      }
    },

    questionOrder: function () {
      return ['Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/salg_postnr.php'] = d3SalgPostnrPage;

  // ---- Kassespor (debitor/kassespor.php)
  // Reached from Kassespor on the report form when the company has a till (frontPageOldMenu.php:85-87), same frame.
  // Evidence, debitor/kassespor.php: Tilbage a[href=rapport.php] accesskey L (:234-236); the search form bonliste (:304)
  // with status (3 not posted, 4 posted), idnumre, fakturadatoer (Bondato), logtimes, fakturanumre (Bonnr), kasser,
  // borde, refs, summer, betalinger, modtagelser and OK (:444-495), linjeantal per page (:314); the list #ksGridTable
  // (:386) with the receipt id linking to (pos_)ordre.php (:686-689) and, on a receipt that is not posted (status 3),
  // the payment method as a link to change it (:722-725); that change shows select[name=ny_betal_type] with Fortryd and
  // Ret (:281-297).

  const D3K = 'dstat';
  const D3K_TASKS = {
    KP1: task(D3K, 'KP1', 3, { target: 'input[name="fakturadatoer"]', label: fieldLabel(D3K, 'fakturadatoer'), kind: 'field' }),
    KP2: task(D3K, 'KP2', 2, { target: '#ksGridTable a[href*="ordre.php?id="]', label: showKey(D3K, 'KP2'), kind: 'row' }),
    KP3: task(D3K, 'KP3', 3, {
      target: function (ctx) { return d3Q(ctx.doc, 'select[name="ny_betal_type"]') || d3Q(ctx.doc, '#ksGridTable a[href*="ret_bet_id="]'); },
      label: showKey(D3K, 'KP3'),
      kind: 'row'
    }),
    KP4: task(D3K, 'KP4', 2, { target: 'select[name="status"]', label: fieldLabel(D3K, 'status'), kind: 'field' }),
    KP5: task(D3K, 'KP5', 2, { target: 'input[name="linjeantal"]', label: fieldLabel(D3K, 'linjeantal'), kind: 'field' }),
    KP6: task(D3K, 'KP6', 2, { target: 'select[name="ny_betal_type"]', label: fieldLabel(D3K, 'ny_betal_type'), kind: 'field' })
  };

  const d3KassesporPage = {
    group: D3K,
    states: ['liste', 'retBetaling', 'unknown'],
    flags: ['ikkeBogfoert'],

    name: function () {
      return 'help.dstat.name_kassespor';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state = 'unknown';
      if (d3Q(doc, 'select[name="ny_betal_type"]')) state = 'retBetaling';
      else if (d3Q(doc, 'form[name="bonliste"]') || d3Q(doc, 'input[name="fakturadatoer"]')) state = 'liste';
      return { state: state, flags: { ikkeBogfoert: !!d3Q(doc, '#ksGridTable a[href*="ret_bet_id="]') }, data: {} };
    },

    about: function (ctx, st) {
      const s = d3State(st);
      if (s === 'liste') return ['help.dstat.about.kassespor', 'help.dstat.about.kassespor_status'];
      if (s === 'retBetaling') return ['help.dstat.about.ret_betaling'];
      return [];
    },

    tasks: D3K_TASKS,

    order: function (ctx, st) {
      const s = d3State(st);
      if (s === 'retBetaling') return ['KP6'];
      if (s === 'liste') return d3Ids(['KP1', 'KP2', d3Fl(st).ikkeBogfoert ? 'KP3' : '', 'KP4', 'KP5', 'KP3']);
      return [];
    },

    fields: {
      status: fieldKeys(D3K, 'status'),
      idnumre: fieldKeys(D3K, 'idnumre'),
      fakturadatoer: fieldKeys(D3K, 'fakturadatoer'),
      logtimes: fieldKeys(D3K, 'logtimes'),
      fakturanumre: fieldKeys(D3K, 'fakturanumre'),
      kasser: fieldKeys(D3K, 'kasser'),
      borde: fieldKeys(D3K, 'borde'),
      refs: fieldKeys(D3K, 'refs'),
      summer: fieldKeys(D3K, 'summer'),
      betalinger: fieldKeys(D3K, 'betalinger'),
      modtagelser: fieldKeys(D3K, 'summer'),
      linjeantal: fieldKeys(D3K, 'linjeantal'),
      ny_betal_type: fieldKeys(D3K, 'ny_betal_type')
    },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.dstat.key.01', group: 'keys' }     // Tilbage (:236)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q3: {
        q: 'help.dstat.q.q3.q',
        words: d3Words(['dagens bonner', 'i dag', 'bon', 'bonner', 'kvittering', 'dato', 'interval', 'tidspunkt'],
          ['today', 'receipt', 'receipts', 'date', 'range', 'time'],
          ['dagens', 'i dag', 'bong', 'kvittering', 'dato', 'intervall', 'tidspunkt']),
        steps: stepKeys(D3K, 'KP1', 1, 3),
        show: D3K_TASKS.KP1.show
      }
    },

    questionOrder: function () {
      return ['Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['debitor/kassespor.php'] = d3KassesporPage;

  // ---- Salgsstatistik (includes/salgsstat.php)
  // Reached from Salgsstat on the report form: debitor/rapport.php posts salgsstat and refreshes to
  // ../includes/salgsstat.php with the form's Dato and Konto and art=D (debitor/rapport.php:479-492), same frame
  // (debitor/salgsstat.php itself is linked from nowhere). Evidence, includes/salgsstat.php: #ssPageFlex with Tilbage
  // a[accesskey=L] to ../debitor/rapport.php and Søg a[accesskey=B] with ret=on (:120-133); a block per customer with
  // tr.ss-account-row and the lines tr.ss-data-row: Varenr., Beskrivelse, Antal, Pris, with Vis detaljer also Dato and
  // Rabat, and Sum (:275-327); only invoiced orders (status 3 and up) and lines with an item (:191-205), quantity times
  // price less discount, without VAT (prices are stored without VAT, debitor/ordre.php:1635-1637). With ret=on only the
  // search form begraens() (:491-520): Kontonr, Firmanavn, Adresse, Postnr., Bynavn, Varenr., Varetekst, Vis detaljer
  // (detaljer) and Søg / Fortryd.

  const D3SS_TASKS = {
    SS1: task(D3S, 'SS1', 3, {
      target: function (ctx) { return d3Q(ctx.doc, 'input[name="find"]') || d3Inner(d3Q(ctx.doc, 'a[accesskey="B"][href*="ret=on"]')); },
      label: showKey(D3S, 'SS1'),
      kind: 'button'
    }),
    // only on the search form: on the list the first step is Søg, which SS1 rings under its own label
    SS2: task(D3S, 'SS2', 2, { target: function (ctx) { return d3Box(d3Q(ctx.doc, 'input[name="detaljer"]')); }, label: fieldLabel(D3S, 'detaljer'), kind: 'field' }),
    SS3: task(D3S, 'SS3', 2, { target: '#ssGridTable', label: showKey(D3S, 'SS3'), kind: 'status' }),
    // the same job as Top 100's TP3, with its texts
    SS4: {
      title: 'help.dstat.task.tp3.title',
      why: 'help.dstat.task.tp3.why',
      steps: stepKeys(D3S, 'TP3', 1, 1),
      show: { target: function (ctx) { return d3Inner(d3Q(ctx.doc, 'a[accesskey="L"][href*="rapport.php"]')); }, label: showKey(D3S, 'TP3'), kind: 'button' }
    }
  };

  const d3SalgsstatPage = {
    group: D3S,
    states: ['liste', 'soeg', 'unknown'],
    flags: ['tom'],

    name: function () {
      return 'help.dstat.name_salgsstat';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state = 'unknown';
      const f = { tom: false };
      if (d3Q(doc, 'input[name="varetekst"]')) state = 'soeg';
      else if (d3Q(doc, '#ssPageFlex') || d3Q(doc, '#ssGridTable')) {
        state = 'liste';
        f.tom = !d3Q(doc, 'tr.ss-data-row');
      }
      return { state: state, flags: f, data: {} };
    },

    about: function (ctx, st) {
      const s = d3State(st);
      if (s === 'liste') return ['help.dstat.about.salgsstat', d3Fl(st).tom ? 'help.dstat.about.salgsstat_tom' : 'help.dstat.about.salgsstat_sum'];
      if (s === 'soeg') return ['help.dstat.about.salgsstat_soeg'];
      return [];
    },

    tasks: D3SS_TASKS,

    order: function (ctx, st) {
      const s = d3State(st);
      if (s === 'soeg') return ['SS1', 'SS2'];
      if (s === 'liste') return d3Fl(st).tom ? ['SS1', 'SS4'] : ['SS3', 'SS1', 'SS2', 'SS4'];
      return [];
    },

    fields: {
      kontonr: fieldKeys(D3S, 'kontonr'),
      firmanavn: fieldKeys(D3S, 'firmanavn'),
      postnr: fieldKeys(D3S, 'postnr'),
      bynavn: fieldKeys(D3S, 'postnr'),
      varenr: fieldKeys(D3S, 'varenr'),
      varetekst: fieldKeys(D3S, 'varenr'),
      detaljer: fieldKeys(D3S, 'detaljer')
    },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.dstat.key.01', group: 'keys' },    // Tilbage (:129)
      { keys: 'Alt+B', text: 'help.dstat.key.04', group: 'keys' }     // Søg (:132)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q4: {
        q: 'help.dstat.q.q4.q',
        words: d3Words(['mangler', 'ikke med', 'ordrer', 'tilbud', 'periode', 'salgsstatistik', 'statistik'],
          ['missing', 'not included', 'orders', 'quotes', 'period', 'sales statistics', 'statistics'],
          ['mangler', 'ikke med', 'ordrer', 'tilbud', 'periode', 'salgsstatistikk', 'statistikk']),
        steps: ['help.dstat.q.q4.step1', 'help.dstat.q.q4.step2']
      }
    },

    questionOrder: function () {
      return ['Q4'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['includes/salgsstat.php'] = d3SalgsstatPage;
})();
