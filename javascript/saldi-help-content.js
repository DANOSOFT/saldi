// ---- javascript/saldi-help-content.js --- ver 5.1.0 --- 2026.11.09 ---
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The help panel's shared content (window.SaldiHelpContent, doc/hjaelp_saldi_ai.md §3.2 and §4): support data, search
// vocabulary, the global shortcuts and the helpers (SaldiHelpContent.lib) that the module files build their pages with.
// The pages themselves are in one file per module (javascript/saldi-help-finans.js), loaded after this one. Oversigt, the
// front page (index/dashboard.php), belongs to no module and is at the end of this file (spec §20).
// Data and pure functions only. Every text the panel shows is a key in importfiler/tekster_keyed.tsv
// (help.<group>.*, help.common.*), fetched per language from index/helpTexts.php and resolved by the shell.
// Detectors and show targets only read the frame document: no writes, focus, clicks, network or timers.
// Frame elements come from another realm, so type checks use nodeType/tagName, never instanceof.
//
// Key scheme (§8.2): help.<g>.task.<id>.title/.why/.step1-4/.show/.note, help.<g>.field.<field key>.label/.text
// (field key lower case, ':' becomes '_'), help.<g>.key.<nn>, help.<g>.q.<id>.q/.intro/.step<n>.
//
// 20261107 CL/ASR Created: help content for Kladdeliste and Kassekladde (Hjælp variant B).
// 20261107 CL/ASR Review: support.phoneIntl, the number with +45 for English and Norwegian users.
// 20261107 CL/ASR Review round 2: more English and Norwegian stop words (in, on, it, det, på, som …), so short common words no longer rank help hits.
// 20261108 CL/ASR Kladdeliste and Kassekladde moved to javascript/saldi-help-finans.js; pages starts empty and the helpers are shared as lib.
// 20261108 CL/ASR Oversigt, the front page (index/dashboard.php, spec §20): states for a new company, normal use, a role without key figures and a hidden overview; the roles card, unpaid and overdue invoices, orders to invoice and the revenue accounts as flags.
// 20261108 CL/ASR Oversigt review: a company whose charts show last year's revenue is not new; FS1 only with key figures on the page (flag noegletal); hidden: FS10, FS7, FS9 (no Rediger oversigt in the menu of a page loaded hidden).
// 20261108 CL/ASR Oversigt counts only the chart's net revenue accounts (asr/fix/dashboard-revenue-range): the revenue note follows.
// 20261109 CL/ASR Oversigt's questions on the chat's start screen (spec §25): the revenue question before the VAT figure, the unpaid card's
//                 question with unpaid invoices, Q4 asks for the news without the old front page, and the hidden overview leads with it.
(function () {
  'use strict';

  // ------------------------------------------------------------------ helpers

  /** The task's own keys: title, why, step1..stepN. */
  function task(g, id, nSteps, show, extra) {
    const p = 'help.' + g + '.task.' + id.toLowerCase() + '.';
    const t = { title: p + 'title', why: p + 'why', steps: [] };
    for (let i = 1; i <= nSteps; i++) t.steps.push(p + 'step' + i);
    if (extra) t.steps = t.steps.concat(extra);
    if (show) t.show = show;
    return t;
  }

  /** The Vis mig label key of a task: help.<g>.task.<id>.show. */
  function showKey(g, id) {
    return 'help.' + g + '.task.' + id.toLowerCase() + '.show';
  }

  /** help.<g>.field.<key>.label/.text; the key is lower case and ':' becomes '_'. */
  function fieldKeys(g, fieldKey) {
    const p = 'help.' + g + '.field.' + fieldKey.toLowerCase().replace(/:/g, '_') + '.';
    return { label: p + 'label', text: p + 'text' };
  }

  function fieldLabel(g, fieldKey) {
    return fieldKeys(g, fieldKey).label;
  }

  /** The step keys of a task, as a question or another task reuses them. */
  function stepKeys(g, id, from, to) {
    const out = [];
    for (let i = from; i <= to; i++) out.push('help.' + g + '.task.' + id.toLowerCase() + '.step' + i);
    return out;
  }

  /** Remove duplicates, keeping the first occurrence. */
  function unique(list) {
    const seen = {};
    return list.filter(function (x) {
      if (seen[x]) return false;
      seen[x] = true;
      return true;
    });
  }

  function attr(el, name) {
    return el && el.nodeType === 1 ? (el.getAttribute(name) || '') : '';
  }

  /** The first element of the frame document with this name, or null. */
  function byName(doc, name) {
    try {
      const el = doc.querySelector('[name="' + name + '"]');
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  /** The line number of a journal field (debe12 -> '12', afd_3 -> '3'), or ''. */
  function rowOf(el) {
    const m = /(\d+)$/.exec(attr(el, 'name'));
    return m ? m[1] : '';
  }

  // ------------------------------------------------------------------ shared data

  window.SaldiHelpContent = {
    version: 1,
    guideLinks: false, // Forslag decision 10: no links to the saldi.dk web guides yet
    support: {
      // phone is shown to Danish users, phoneIntl to everyone else (the Danish form reads as a domestic number abroad)
      phone: '46 90 22 08', phoneIntl: '+45 46 90 22 08', tel: '+4546902208', mail: 'support@saldi.dk',
      openHour: 9, closeHour: 16, tz: 'Europe/Copenhagen'
    },
    // Search vocabulary, never shown. A contact word (whole word) shows the contact card first (§1.11).
    contactWords: {
      da: ['menneske', 'mennesker', 'mennesket', 'support', 'supporten', 'ring', 'ringe', 'kontakt', 'kontakte', 'telefon', 'telefonen', 'telefonisk'],
      en: ['human', 'person', 'support', 'call', 'phone', 'contact'],
      no: ['menneske', 'mennesker', 'mennesket', 'support', 'ring', 'ringe', 'kontakt', 'kontakte', 'telefon', 'telefonen']
    },
    stopWords: {
      da: ['jeg', 'hvordan', 'hvad', 'hvor', 'kan', 'der', 'det', 'den', 'de', 'en', 'et', 'og', 'på', 'til', 'af', 'med', 'om', 'er', 'min', 'mit', 'mine',
        'hvorfor', 'skal', 'ikke', 'saldi', 'får', 'gør', 'man', 'mig', 'du', 'dig', 'har', 'have', 'eller', 'som', 'fra', 'for', 'nu', 'så', 'lige', 'vil', 'gerne',
        'laver', 'lave', 'gøre', 'finde', 'finder', 'ser', 'se', 'står', 'stå', 'sker', 'ske', 'muligt', 'nogen', 'noget', 'nogle', 'hvornår', 'hvilken', 'hvilke', 'hvilket',
        'bare', 'også', 'altid', 'måske', 'virker', 'hjælp', 'hjælpe', 'ved', 'vide', 'være', 'blive', 'bliver', 'denne', 'dette', 'disse', 'jer', 'os', 'vi', 'hej', 'tak'],
      en: ['i', 'how', 'what', 'where', 'can', 'the', 'a', 'an', 'and', 'to', 'of', 'with', 'about', 'is', 'my', 'why', 'do', 'does', 'not', 'saldi', 'me', 'you',
        'have', 'or', 'for', 'from', 'now', 'want', 'would', 'like', 'help', 'please', 'in', 'on', 'at', 'it', 'by', 'be', 'if', 'as', 'are',
        'there', 'this', 'that', 'which', 'when', 'should', 'need', 'goes'],
      no: ['jeg', 'hvordan', 'hva', 'hvor', 'kan', 'en', 'et', 'ei', 'og', 'å', 'til', 'av', 'med', 'om', 'er', 'min', 'mitt', 'mine', 'hvorfor', 'gjør', 'ikke',
        'saldi', 'meg', 'du', 'har', 'eller', 'for', 'fra', 'nå', 'vil', 'ville', 'gjerne', 'hjelp', 'det', 'den', 'de', 'på', 'som', 'skal', 'så', 'der',
        'hvilken', 'hvilke']
    },
    // The global keys use the chrome ids the generic panel uses too (data-txt, §4.1).
    globalShortcuts: [
      { keys: '?', id: '8749' },
      { keys: 'F1', id: '8750' },
      { keys: 'Esc', id: '8751' },
      { keys: 'Ctrl+K', id: '8752' },
      { keys: 'Alt+L', id: '8753' }
    ],
    // Filled by the module files (javascript/saldi-help-finans.js, …), which load after this file (§1.3).
    pages: {},
    // The helpers the module files build their pages with.
    lib: { task: task, showKey: showKey, fieldKeys: fieldKeys, fieldLabel: fieldLabel, stepKeys: stepKeys, unique: unique,
      attr: attr, byName: byName, rowOf: rowOf }
  };

  // ------------------------------------------------------------------ Oversigt, the front page (index/dashboard.php)
  // The frame's first page after login (index/main.php:1214-1225). It belongs to no module, so its page is here, next to
  // the shared data (spec §20). Evidence, index/dashboard.php unless named:
  // - the blocks in #grid in the user's order (:439-448, dash_order() :120-138); a block or card switched off in Rediger
  //   oversigt carries the class 'gone' (:265-267, javascript/dashboard.js setWidget());
  // - the key figures .db-kpis, one .db-cell[data-widget] per figure, the value in the first span of .db-val (:274-303);
  //   revenue is credit minus debit on the accounts from kontomin to kontomaks, both included (dashData.php
  //   dash_account_sql()); an empty Fra or Til kontonr. is automatic, as is the old dashboard's 0/2000 or 0/12000: the
  //   chart's net revenue sum account counts, from its fra_kto to its own number (1010-1390 in the standard chart;
  //   dash_revenue_accounts(), dash_chart_revenue_range()), else 0-2000; the VAT cell only when VAT
  //   registered and the user may open Finansrapport (:194-201);
  // - no revenue: .db-ph Ingen omsætning endnu instead of #monthChart / #dayChart (:280-281, :312, :342, :356);
  // - Ubetalte salgsfakturaer: #unpGrp (due groups late/warn/ok with Antal), #unpTbl (rows with data-g), the foot .db-unp-f
  //   with Vis alle and, for unapplied payments, a bold amount and Udlign (:376-421, dashboardIncludes/unpaidInvoices.php);
  //   .db-ok Ingen ubetalte salgsfakturaer (:382-384);
  // - Ufakturerede ordrer with Gå til ordrer (:424-437, dashData.php:298-311); POS-muligheder .db-pos (:450-461);
  // - a role without any block: .db-empty with links to its modules (:462-479);
  // - hidden by the user: #grid.all-hidden, #dashHidden with #showDash (:441, :481-482, dashboard.js setHidden());
  // - Rediger oversigt is #drawer, open with the class 'open' (:487-494, dashboard.js drawerOpen()), the list .blist and
  //   the revenue accounts .acct with two unnamed inputs, Fra kontonr. and Til kontonr. (dashboard.js buildDrawer());
  // - Gennemgå roller for administrators after the move to roles: .db-notice (dashboardIncludes/roleReview.php:48-61).
  // The bell, the user menu (Rediger oversigt, Skjul oversigt, Regnskabsår, Sagsstyring) and the Kassesystem icon are in
  // the shell's topbar (index/mainIncludes/topbar.php:438-525) and the modules in its sidebar, outside the frame: no
  // Vis mig for them. The 'Intet aktivt regnskabsår' branch (:235-243) needs $regnaar === null, which online.php never
  // leaves (:166, :273-280), so it has no state.

  const FS = 'fs';

  function fsQ(root, sel) {
    try {
      const el = root ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function fsQa(root, sel) {
    try {
      return root ? Array.prototype.slice.call(root.querySelectorAll(sel)) : [];
    } catch (e) {
      return [];
    }
  }

  function fsText(el) {
    return el && el.nodeType === 1 ? String(el.textContent || '') : '';
  }

  function fsHas(el, cls) {
    return (' ' + attr(el, 'class') + ' ').indexOf(' ' + cls + ' ') >= 0;
  }

  /** On the page and not switched off in Rediger oversigt (no 'gone' on it or its block). */
  function fsShown(el) {
    try {
      return !!el && !(el.closest && el.closest('.gone'));
    } catch (e) {
      return false;
    }
  }

  /** A figure with a digit other than 0 ('0 kr', '0' and '0,00 kr' are none). */
  function fsNonZero(el) {
    return /[1-9]/.test(fsText(el));
  }

  /** No revenue yet. The charts are drawn when this or last fiscal year has revenue (:280), so a company in the first
   *  days of a new year (the year's figure 0, last year's months full) is not new. Without a chart the year's figure
   *  decides, else the charts' placeholder; unknown (false) without any of them. */
  function fsNoRevenue(doc) {
    if (fsQ(doc, '#monthChart, #dayChart')) return false;
    const year = fsQ(doc, '.db-cell[data-widget="revyear"] .db-val span');
    if (year) return !fsNonZero(year);
    return !!fsQ(doc, '[data-block="revgraph"] .db-ph, [data-block="revweek"] .db-ph');
  }

  /** Nøgletal on the page with at least one figure switched on (a role may lack the block, a user may switch all off). */
  function fsKpisShown(doc) {
    return fsQa(doc, '.db-kpis .db-cell').some(function (c) { return !fsHas(c, 'gone'); });
  }

  /** The blocks the user sees: not switched off, and Nøgletal / Små tal only with a card that is on. */
  function fsVisibleBlocks(doc) {
    return fsQa(doc, '#grid > .blk').filter(function (b) {
      if (fsHas(b, 'gone')) return false;
      if (fsHas(b, 'db-kpis') || fsHas(b, 'db-small')) {
        return fsQa(b, '.db-cell').some(function (c) { return !fsHas(c, 'gone'); });
      }
      return true;
    }).length;
  }

  /** The due group with the most urgent invoices: over 15 days, then 1-15 days, then not due (dashboard.php:386-396). */
  function fsDueRow(ctx) {
    const groups = ['late', 'warn', 'ok'];
    for (let i = 0; i < groups.length; i++) {
      const tr = fsQ(ctx.doc, '#unpGrp tbody tr[data-g="' + groups[i] + '"]');
      if (tr && fsNonZero(fsQ(tr, 'td.dt-num'))) return tr;   // the first number is Antal
    }
    return fsQ(ctx.doc, '#unpGrp');
  }

  /** Udlign under the list: the last link of the foot, after Vis alle; only there with unapplied payments (:414-419). */
  function fsUdlign(ctx) {
    const links = fsQa(ctx.doc, '.db-unp-f a');
    return links.length > 1 ? links[links.length - 1] : null;
  }

  const FS_TASKS = {
    FS1: task(FS, 'FS1', 4, { target: '.db-kpis', label: showKey(FS, 'FS1'), kind: 'status' }),
    FS2: task(FS, 'FS2', 3),              // Debitor, Finans and the user menu are in the shell
    FS3: task(FS, 'FS3', 4, { target: fsDueRow, label: showKey(FS, 'FS3'), kind: 'row' }),
    FS4: task(FS, 'FS4', 3, { target: '.db-cell[data-widget="ordercount"] .db-acts a', label: showKey(FS, 'FS4'), kind: 'button' }),
    // Rediger oversigt opens from the user menu (shell); with the drawer open, its list and its account fields
    FS5: task(FS, 'FS5', 4, { target: '#drawer.open .blist', label: showKey(FS, 'FS5'), kind: 'status' }),
    FS6: task(FS, 'FS6', 4, { target: '#drawer.open .acct input', label: showKey(FS, 'FS6'), kind: 'field' }),
    FS7: task(FS, 'FS7', 4),              // the sidebar and the search are in the shell
    FS8: task(FS, 'FS8', 4),
    FS9: task(FS, 'FS9', 4),              // the bell and the user menu are in the shell's topbar
    FS10: task(FS, 'FS10', 2, { target: '#showDash', label: showKey(FS, 'FS10'), kind: 'button' }),
    FS11: task(FS, 'FS11', 3, { target: '.db-notice a[href*="usersRoles.php"]', label: showKey(FS, 'FS11'), kind: 'button' }),
    FS12: task(FS, 'FS12', 3, { target: '.db-empty .db-links', label: showKey(FS, 'FS12'), kind: 'status' })
  };

  /** Words a user would type; never shown. */
  function fsWords(da, en, no) {
    return { da: da, en: en, no: no };
  }

  const forside = {
    group: FS,
    states: ['tom', 'oversigt', 'begraenset', 'skjult'],
    flags: ['roller', 'noegletal', 'ubetalte', 'forfaldne', 'ikkeUdlignet', 'ingenUbetalte', 'ordrer', 'moms', 'pos', 'ingenOmsaetning', 'ingenBlokke'],

    name: function () {
      return 'help.fs.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const grid = fsQ(doc, '#grid');
      const unp = fsQ(doc, '#unpTbl');
      const ubetalte = fsShown(unp);
      const orders = fsQ(doc, '.db-cell[data-widget="ordercount"] .db-val span');
      const flags = {
        roller: !!fsQ(doc, '.db-notice a[href*="usersRoles.php"]'),
        noegletal: fsKpisShown(doc),
        ubetalte: ubetalte,
        forfaldne: ubetalte && !!fsQ(doc, '#unpTbl tbody tr[data-g="late"], #unpTbl tbody tr[data-g="warn"]'),
        ikkeUdlignet: ubetalte && !!fsQ(doc, '.db-unp-f span b'),
        ingenUbetalte: fsShown(fsQ(doc, '[data-block="unpaid_invoices"] .db-ok')),
        ordrer: fsShown(orders) && fsNonZero(orders),
        moms: fsShown(fsQ(doc, '.db-cell[data-widget="vatcount"]')),
        pos: !!fsQ(doc, '.db-pos'),
        ingenOmsaetning: fsNoRevenue(doc),
        ingenBlokke: false
      };
      let state = 'unknown';
      if (grid && fsHas(grid, 'all-hidden')) state = 'skjult';
      else if (grid && fsQ(grid, '.db-empty')) state = 'begraenset';
      else if (grid) {
        flags.ingenBlokke = fsVisibleBlocks(doc) === 0;
        state = flags.ingenOmsaetning && !flags.ubetalte && !flags.ordrer ? 'tom' : 'oversigt';
      }
      return { state: state, flags: flags, data: {} };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      if (state === 'tom') return ['help.fs.about.forside', 'help.fs.about.tom'];
      if (state === 'oversigt') return ['help.fs.about.forside', 'help.fs.about.kilde'];
      if (state === 'begraenset') return ['help.fs.about.begraenset'];
      if (state === 'skjult') return ['help.fs.about.skjult'];
      return [];
    },

    tasks: FS_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      // Hidden: no Rediger oversigt in the user menu when the page loaded hidden (topbar.php:488-490), so no FS5
      if (state === 'skjult') return ['FS10', 'FS7', 'FS9'];
      if (state === 'begraenset') return ['FS12', 'FS7', 'FS9'];
      if (state !== 'tom' && state !== 'oversigt') return [];
      const ids = [];
      if (f.roller) ids.push('FS11');            // the card waits at the top until every role is reviewed
      if (f.ingenBlokke) ids.push('FS5');        // every block switched off: nothing to read until one is on again
      const kpis = f.noegletal ? 'FS1' : '';     // FS1 only with key figures to read
      if (state === 'tom') {
        // A new company: how figures get here, what they will mean, where the work is
        ids.push('FS2', kpis, 'FS7', 'FS5', 'FS6', 'FS9', 'FS8');
      } else {
        if (f.forfaldne || f.ikkeUdlignet) ids.push('FS3');
        if (f.ordrer) ids.push('FS4');
        ids.push(kpis, 'FS8');
        if (f.ubetalte) ids.push('FS3');
        ids.push('FS7', 'FS5', 'FS6', 'FS9');
      }
      return unique(ids.filter(function (id) { return id !== ''; }));
    },

    fields: {
      sammenlign: fieldKeys(FS, 'sammenlign'),   // #cmpSel, Omsætning pr. måned (:309)
      uge: fieldKeys(FS, 'uge'),                 // #weekSel, Omsætning pr. dag (:334-338)
      periode: fieldKeys(FS, 'periode'),         // #heatSel, Kunder pr. time (:319-323)
      kontomin: fieldKeys(FS, 'kontomin'),       // Rediger oversigt, Omsætningskonti: Fra kontonr.
      kontomaks: fieldKeys(FS, 'kontomaks')      // and Til kontonr. (dashboard.js buildDrawer())
    },

    // The selects have ids, the two account fields neither a name nor an id: their place in .acct tells them apart.
    fieldKey: function (el) {
      const id = attr(el, 'id');
      if (id === 'cmpSel') return 'sammenlign';
      if (id === 'weekSel') return 'uge';
      if (id === 'heatSel') return 'periode';
      let acct = null;
      try { acct = el && el.closest ? el.closest('.acct') : null; } catch (e) { acct = null; }
      if (!acct) return null;
      const i = fsQa(acct, 'input').indexOf(el);
      return i === 0 ? 'kontomin' : (i === 1 ? 'kontomaks' : null);
    },

    // The bars take Tab (dashboard.js barChart(), tabindex 0) and show their tooltip on focus; Enter or space on a due
    // group filters the list, Enter on an invoice opens it (dashboard.js:201-204); Alt+Up/Down on a block's grip moves it
    // and Esc closes Rediger oversigt (dashboard.js:295, :344). No access keys, so no shortcutsNote.
    shortcuts: [
      { keys: 'Tab', text: 'help.fs.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.fs.key.02', group: 'keys' },
      { keys: 'Alt+↑ / Alt+↓', text: 'help.fs.key.03', group: 'keys' },
      { keys: 'Esc', text: 'help.fs.key.04', group: 'keys' }
    ],

    questions: {
      Q1: {
        q: 'help.fs.q.q1.q',
        words: fsWords(['nul', 'ingen omsætning', 'tom', 'tomme', 'mangler', 'tal', 'nøgletal', 'vises ikke', 'omsætning', 'kr'],
          ['zero', 'no revenue', 'empty', 'missing', 'figures', 'key figures', 'not shown', 'revenue'],
          ['null', 'ingen omsetning', 'tom', 'tomme', 'mangler', 'tall', 'nøkkeltall', 'vises ikke', 'omsetning', 'kr']),
        steps: ['help.fs.q.q1.step1'].concat(stepKeys(FS, 'FS2', 3, 3), ['help.fs.q.q1.step3']),
        show: FS_TASKS.FS1.show
      },
      Q2: {
        q: 'help.fs.q.q2.q',
        words: fsWords(['passer ikke', 'stemmer ikke', 'forkert', 'negativ', 'minus', 'for lav', 'for høj', 'omsætningskonti', 'konti', 'varekøb', 'regnskab'],
          ['does not match', 'wrong', 'negative', 'minus', 'too low', 'too high', 'revenue accounts', 'accounts', 'purchases'],
          ['stemmer ikke', 'passer ikke', 'feil', 'negativ', 'minus', 'for lav', 'for høy', 'omsetningskontoer', 'kontoer', 'varekjøp', 'regnskap']),
        steps: stepKeys(FS, 'FS6', 2, 3).concat(['help.fs.q.q2.step3', 'help.fs.q.q2.step4']),
        show: FS_TASKS.FS6.show
      },
      Q3: {
        q: 'help.fs.q.q3.q',
        words: fsWords(['betalt', 'ubetalt', 'står stadig', 'udlign', 'udligne', 'udligning', 'indbetaling', 'kreditnota', 'åbne poster'],
          ['paid', 'unpaid', 'still', 'equalize', 'match', 'matching', 'payment', 'credit note', 'open items'],
          ['betalt', 'ubetalt', 'står fortsatt', 'utlign', 'utligne', 'utligning', 'innbetaling', 'kreditnota', 'åpne poster']),
        steps: ['help.fs.q.q3.step1', 'help.fs.q.q3.step2', 'help.fs.q.q3.step3', 'help.fs.q.q3.step4'],
        show: { target: fsUdlign, label: 'help.fs.q.q3.show', kind: 'button' }
      },
      Q4: {
        q: 'help.fs.q.q4.q',
        words: fsWords(['nyheder', 'nyhed', 'banner', 'advarsel', 'advarsler', 'udløb', 'udløbsdato', 'batch', 'besked', 'beskeder', 'notifikation', 'notifikationer', 'klokke', 'klokken', 'gamle forside'],
          ['news', 'banner', 'warning', 'warnings', 'expiry', 'expiry date', 'batch', 'message', 'messages', 'notification', 'notifications', 'bell', 'old front page'],
          ['nyheter', 'nyhet', 'banner', 'advarsel', 'advarsler', 'utløp', 'utløpsdato', 'batch', 'melding', 'meldinger', 'varsel', 'varsler', 'bjelle', 'bjella', 'gamle forsiden']),
        steps: stepKeys(FS, 'FS9', 1, 1).concat(['help.fs.q.q4.step2'], stepKeys(FS, 'FS9', 4, 4))
      },
      Q5: {
        q: 'help.fs.q.q5.q',
        words: fsWords(['kasse', 'kassesystem', 'kassen', 'pos', 'butik', 'ekspedition', 'sagsstyring', 'sager', 'sag'],
          ['till', 'point of sale', 'pos', 'shop', 'cash register', 'job management', 'jobs', 'cases'],
          ['kasse', 'kassesystem', 'kassen', 'pos', 'butikk', 'saksstyring', 'saker', 'sak']),
        steps: ['help.fs.q.q5.step1', 'help.fs.q.q5.step2', 'help.fs.q.q5.step3'],
        show: { target: '.db-pos', label: 'help.fs.q.q5.show', kind: 'status' }
      },
      Q6: {
        q: 'help.fs.q.q6.q',
        words: fsWords(['graf', 'grafer', 'søjle', 'søjler', 'diagram', 'tabel', 'uge', 'måned', 'sammenlign', 'sidste år', 'bedste'],
          ['chart', 'charts', 'graph', 'bar', 'bars', 'table', 'week', 'month', 'compare', 'last year', 'best'],
          ['graf', 'grafer', 'søyle', 'søyler', 'diagram', 'tabell', 'uke', 'måned', 'sammenlign', 'i fjor', 'beste']),
        steps: ['help.fs.q.q6.step1', 'help.fs.q.q6.step2', 'help.fs.q.q6.step3', 'help.fs.q.q6.step4'],
        show: { target: '#tblToggle', label: 'help.fs.q.q6.show', kind: 'button' }
      },
      Q7: {
        q: 'help.fs.q.q7.q',
        words: fsWords(['moms', 'momsangivelse', 'momsperiode', 'momstal', 'frist', 'angivelse', 'skat', 'skattestyrelsen', 'indberet', 'indberetning'],
          ['vat', 'vat return', 'vat period', 'deadline', 'return', 'tax', 'file', 'filing'],
          ['mva', 'mva-melding', 'mva-periode', 'frist', 'melding', 'skatt', 'skatteetaten', 'innrapporter', 'innrapportering']),
        steps: ['help.fs.q.q7.step1', 'help.fs.q.q7.step2', 'help.fs.q.q7.step3', 'help.fs.q.q7.step4'],
        show: { target: '.db-cell[data-widget="vatcount"] a[href*="momsangivelse"]', label: 'help.fs.q.q7.show', kind: 'button' }
      },
      Q8: {
        q: 'help.fs.q.q8.q',
        words: fsWords(['mangler', 'blok', 'blokke', 'kort', 'væk', 'forsvundet', 'kan ikke se', 'rolle', 'adgang', 'rettighed', 'rettigheder'],
          ['missing', 'block', 'blocks', 'card', 'gone', 'disappeared', 'cannot see', 'role', 'access', 'permission', 'permissions'],
          ['mangler', 'blokk', 'blokker', 'kort', 'borte', 'forsvunnet', 'kan ikke se', 'rolle', 'tilgang', 'rettighet', 'rettigheter']),
        steps: ['help.fs.q.q8.step1', 'help.fs.q.q8.step2', 'help.fs.q.q8.step3', 'help.fs.q.q8.step4']
      }
    },

    // The chat's start screen shows the first three (spec §25). A new company: why 0 kr, then the till, the VAT figure and
    // where the messages went. Normal use: an unapplied payment, the till, then the revenue question support gets most,
    // the VAT figure and the unpaid card. Hidden: nothing on the page has figures, so the messages and the blocks.
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      let ids = [];
      if (state === 'tom') ids = ['Q1', f.pos ? 'Q5' : '', f.moms ? 'Q7' : '', 'Q4', 'Q8'];
      else if (state === 'begraenset') ids = ['Q8', 'Q5', 'Q4'];
      else if (state === 'skjult') ids = ['Q4', 'Q8', 'Q5'];
      else if (state === 'oversigt') {
        ids = [f.ikkeUdlignet ? 'Q3' : '', f.pos ? 'Q5' : '', 'Q2', f.moms ? 'Q7' : '', f.ubetalte ? 'Q3' : '', 'Q4', 'Q8', 'Q6', 'Q1'];
      }
      return unique(ids.filter(function (id) { return id !== ''; })).slice(0, 3);
    },

    signal: function () {
      return false;
    }
  };

  window.SaldiHelpContent.pages['index/dashboard.php'] = forside;
})();
