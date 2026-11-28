// ---- javascript/saldi-help-finans.js --- ver 5.1.0 --- 2026.11.19 ---
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The help panel's page knowledge for the finance module (window.SaldiHelpContent.pages, doc/hjaelp_saldi_ai.md §3.2,
// §4 and §16): the Finans sidebar (Kassekladde, Regnskab, Rapporter, Kontoplan) and every page reached from it in the frame.
// Loads after javascript/saldi-help-content.js and builds its pages with that file's helpers (SaldiHelpContent.lib).
// Data and pure functions only. Every text the panel shows is a key in importfiler/tekster_keyed.tsv (help.<group>.*),
// fetched per language from index/helpTexts.php. Detectors and show targets only read the frame document: no writes,
// focus, clicks, network or timers. Frame elements come from another realm, so type checks use nodeType/tagName.
//
// 20261108 CL/ASR Created: Kladdeliste and Kassekladde moved here from saldi-help-content.js; help for Regnskab, Budget,
//                 Kontoplan, Kontokort, Kontospecifikation, Rapporter, Kontrolspor, Momsperioder, Listeangivelse, SAF-T,
//                 Bankafstemning, Regnskab Basis, the standalone account cards, Bogføring, Udlign, Dokumenter and the imports.
// 20261108 CL/ASR Round 2 for new customers (spec §17): Kladdeliste, Kassekladde and the posting overview start from the
//                 first day (what a kladde is, Gem versus Bogfør, the regnskabsår, worked entries, VAT, bilag, corrections);
//                 new page Kassebilag (includes/documents.php: the document pool, a line's documents and the link page).
// 20261108 CL/ASR problem(): the states in which a page shows an error or a blocker, for the panel's problem card (spec §18).
// 20261108 ASR Vis mig audit (spec §21): every target rings what its task is about. KK31 Bogfør (not the clip), KK17 and
//                 KK32 Debet, KK23 Bemærkning, Kladdeliste Q6 Ny and Q4 none, Kontokort Q1 Ny, Udlign AU1/AU2 the open
//                 items, Momsafstemning MS5 the period, Emballage EM1 Fra dato, Kladdens dokumenter Q2 the upload field,
//                 Kassebilag KB10/Q3 the mail address. KK13/Q3b never ring a kladde number of the reversal pick, and a
//                 report's own targets (rpOnly(): KM1, KU2, MS1, MS5, MR2, MR3, OS2, MT1, EM1) ring nothing on another report.
// 20261109 CL/ASR Content review of the questions on the chat's start screen (spec §25): questionOrder() per state on Kladdeliste,
//                 Kassekladde, Regnskab, Kontoplan, Kontokort, Rapporter, Bogføring, Udlign, Kassebilag and the two imports;
//                 Kontokort Q4 (the type) and Q5 (the number) for a new account; Rapporter Q4 and Udlign Q2 corrected.
// 20261119 CL/ASR Rapporter Q5 explains how Resultat and Balance are split, by the Sideskift account or else by account type,
//                 now that a chart without a Sideskift account no longer alerts; 'sideskift' stays among its words.
(function () {
  'use strict';

  const C = window.SaldiHelpContent;
  if (!C || !C.lib || !C.pages) return;
  const task = C.lib.task, showKey = C.lib.showKey, fieldKeys = C.lib.fieldKeys, fieldLabel = C.lib.fieldLabel,
    stepKeys = C.lib.stepKeys, unique = C.lib.unique, attr = C.lib.attr, byName = C.lib.byName, rowOf = C.lib.rowOf;
  const PAGES = C.pages;

  // ------------------------------------------------------------------ Kladdeliste (finans/kladdeliste.php)
  // Evidence: the ID cell carries data-bogfort and, for a journal a colleague touched within the hour, the lock title
  // (kladdeliste.php:250-298); Slet only on an empty open journal (:457-506); egne/alle (:509-556); the query lists open,
  // then simulated, then posted journals, newest first (:560-587); the section rows Simuleret Kladdeliste and Bogførte
  // kladder are added after load (:685-730); the Vis alle/Vis egne toggle sits in the footer (:862-975). The grid adds one
  // filler row (includes/grid.php:1305-1307), the search row (:1386-1400) and the menu (:1408-1466).

  /** The journal rows on the page: tbody rows that are neither the grid's filler row nor a section row, so the count
   *  also holds when the ID column (and with it data-bogfort) is hidden (U1 D3). null outside the list view: Redigér >
   *  Kolonner and Filtre replace the list with a form without thead (includes/grid.php:1636-1660, 2030-2040). */
  function klRows(doc) {
    try {
      if (!doc.querySelector('#datatable-kladdelst thead')) return null;
      return Array.prototype.slice.call(
        doc.querySelectorAll('#datatable-kladdelst tbody tr:not(.filler-row):not(.simulated-separator):not(.posted-separator)'));
    } catch (e) {
      return null;
    }
  }

  // The lock title only counts on a journal that is really locked: opening only stops at an open (-) or simulated (S)
  // journal a colleague touched within the hour (kassekladde.php:858-871, alert 1577). A colleague's posted journal, or a
  // '!' journal, shows the same red title for an hour (kladdeliste.php:276-291; bogfor.php:1117-1123), but opens freely.
  const KL_LOCKED = '#datatable-kladdelst td[data-bogfort="-"] > a[title], #datatable-kladdelst td[data-bogfort="S"] > a[title]';

  /** The heading row of the simulated journals, else of the posted ones (kladdeliste.php:699-727). */
  function klGroupHeading(ctx) {
    try {
      const el = ctx.doc.querySelector('tr.simulated-separator') || ctx.doc.querySelector('tr.posted-separator');
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  const KL_TASKS = {
    // PLAN §2.1; facts in kladdeliste.php and includes/grid.php, and on the pages the steps lead to
    K1: task('kl', 'K1', 2, { target: '#datatable-wrapper-kladdelst .dropdown-trigger', label: showKey('kl', 'K1'), kind: 'button' }),
    K2: task('kl', 'K2', 3, { target: '#create-new', label: showKey('kl', 'K2'), kind: 'button' }),
    // [3] Bogfør in the kladde opens the overview (kassekladde.php:4286, 1849-1851); its Bogfør posts and sends the user
    // to this list (bogfor.php:737, 294-296), where the journal sits under Bogførte kladder (kladdeliste.php:715-727).
    K3: task('kl', 'K3', 3, {
      target: function (ctx) {
        const td = ctx.doc.querySelector('#datatable-kladdelst td[data-bogfort="-"]') ||
          ctx.doc.querySelector('#datatable-kladdelst td[data-bogfort="!"]');
        return td && td.closest ? td.closest('tr') : null;
      },
      label: showKey('kl', 'K3'),
      kind: 'row'
    }),
    // Only the user's own journals (oprettet_af, kladdeliste.php:538-541) unless Vis alle; the choice is saved (:549-556).
    K4: task('kl', 'K4', 2, { target: '#filter-toggle-btn', label: showKey('kl', 'K4'), kind: 'button' }),
    K5: task('kl', 'K5', 2, { target: KL_LOCKED, label: showKey('kl', 'K5'), kind: 'status' }),
    // [2] the Simulering checkbox of Rapporter is hard-coded Danish (rapport_includes/forside.php:308); [3] Annuller
    // simulering deletes the simulation and opens the journal (kassekladde.php:1144-1147, 4267); [4] Bogfør (:4286).
    K6: task('kl', 'K6', 4, { target: 'tr.simulated-separator', label: showKey('kl', 'K6'), kind: 'status' }),
    K7: task('kl', 'K7', 3, { target: '#datatable-kladdelst button[onclick*="deleteKladde"]', label: showKey('kl', 'K7'), kind: 'button' }),
    // [1] without an open (-) journal the arrow only alerts 2597 (kassekladde.php:5110-5119), so [1] creates one first and
    // saves a line in it (the kladdeliste row is written on the first save, :1625-1633). [2] the arrow opens a list of the
    // open journals; [3] the reversal is written when a number in it is clicked, and again on every reload of that address
    // (GET ompost + ompost_til, :895, 910-911, 5107-5132): a neutral warning (PLAN §1.5), right after the step with the arrow.
    K8: task('kl', 'K8', 4, { target: 'tr.posted-separator', label: showKey('kl', 'K8'), kind: 'status' }),
    K9: task('kl', 'K9', 2, { target: 'input[name="search[kladdelst][kladdenote]"]', label: fieldLabel('kl', 'search:kladdenote'), kind: 'field' }),
    // Only dates in the active year with Bogføring tilladt are saved (includes/stdFunc/checkOpenFiscalYear.php:26-41).
    // [1] the settings hub lists Regnskabsår in the row Regnskab & moms (7711) under Finans (includes/settings/hub.php:
    // 109-115); every row starts folded, so the step clicks the row first (systemdata/settings.php:37, 296, 302); [2] the year list's
    // Bogføring tilladt column (systemdata/settingsDefinitions.php:182-203); [3] Opret nyt regnskabsår is the create card
    // under the list and makes the new year the active one (includes/settings/rowHooks.php:323-345, 795-803);
    // [4] the kassekladde's Regnskabsår select (finans/kassekladde_includes/fiscalYearSwitch.php:61-81).
    // No Vis mig: nothing on this page shows the year.
    K10: task('kl', 'K10', 4),
    // The newcomer's whole flow: Ny (topLineFinans.php:71), Enter saves the line (kassekladde.php:4282), the clip on a
    // saved line opens the document pool (:646-666, 3719-3742), where nothing is attached until Indsæt valgte (3292,
    // includes/docsIncludes/docPool.php:2665; insertDoc.php:650-668; Vælg fra enhed 3325, docPool.php:5241), Bogfør twice
    // (:4286; bogfor.php:737). A paid purchase: expense in Debet, bank in Kredit, the amount with VAT
    // (bogfor_includes/postingRules.php:132-143).
    K11: task('kl', 'K11', 4, { target: '#create-new', label: showKey('kl', 'K2'), kind: 'button' }),
    // [2] the posted kladde links each account number to kontospec or the customer/supplier card (kassekladde.php:2313-2315,
    // 2404-2406; kassekladde_includes/accountCard.php:16-49); [3] Regnskab shows the movements per account and month, and
    // each amount links to kontospec (finans/regnskab.php:535-536).
    K12: task('kl', 'K12', 3, { target: 'tr.posted-separator', label: showKey('kl', 'K8'), kind: 'status' }),
    // Practice, labelled as advice in the why. The Bemærkning of the kassekladde (kassekladde.php:3003) is listed and
    // searchable here (kladdeliste.php:370-379).
    K13: task('kl', 'K13', 4)
  };

  const KL_FIELDS = {};
  ['search:id', 'search:kladdedate', 'search:oprettet_af', 'search:kladdenote', 'search:bogfort', 'search:bogfort_af', 'rowcount']
    .forEach(function (k) { KL_FIELDS[k] = fieldKeys('kl', k); });

  const kladdeliste = {
    group: 'kl',
    states: ['tom', 'liste'],
    flags: ['tom', 'aabne', 'simuleret', 'bogfoert', 'laast', 'kanSlettes', 'soegning', 'kunEgne', 'mange', 'faa'],

    name: function () {
      return 'help.kl.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const rows = klRows(doc);
      const n = rows ? rows.length : 0;
      const cells = Array.prototype.slice.call(doc.querySelectorAll('#datatable-kladdelst td[data-bogfort]'));
      const v = cells.map(function (td) { return td.getAttribute('data-bogfort'); });
      const btn = doc.getElementById('filter-toggle-btn');
      // The server reads the URL's filter first (kladdeliste.php:520-521); without one it uses the saved
      // choice, which the toggle's text shows (636 Vis alle while only the user's own journals are listed).
      const f = ctx.url.searchParams.get('filter');
      const btnText = btn ? String(btn.textContent || '').trim() : '';
      const searchInputs = Array.prototype.slice.call(doc.querySelectorAll('input[name^="search[kladdelst]"]'));
      // The rows of one page only: on a later page (offset > 0, the grid's hidden field, includes/grid.php:1549) there
      // are more than 50 journals (smallest page size, :1491), however few rows this page has.
      let firstPage = true;
      try {
        const off = doc.querySelector('input[name="offset[kladdelst]"]');
        firstPage = !off || !(parseInt(String(off.value || ''), 10) > 0);
      } catch (e) {
        firstPage = true;
      }
      let laast = false;
      try {
        laast = !!doc.querySelector(KL_LOCKED);
      } catch (e) {
        laast = false;
      }
      const flags = {
        tom: !!rows && n === 0,
        aabne: v.some(function (x) { return x === '-' || x === '!'; }),
        simuleret: v.indexOf('S') !== -1,
        bogfoert: v.some(function (x) { return !!x && x !== '-' && x !== '!' && x !== 'S'; }),
        laast: laast,
        kanSlettes: !!doc.querySelector('#datatable-kladdelst button[onclick*="deleteKladde"]'),
        soegning: searchInputs.some(function (i) { return String(i.value || '').trim() !== ''; }),
        // No language list: ctx.t('636') is the same tenant row the page printed the toggle from.
        kunEgne: f === 'egne' || (f !== 'alle' && btnText !== '' && btnText === ctx.t('636')),
        mange: n > 15 || !firstPage,
        // A short list: most likely a new customer's first journals (PLAN §1.6), never on a later page.
        faa: firstPage && n >= 1 && n <= 5
      };
      return { state: flags.tom ? 'tom' : 'liste', flags: flags, data: { rows: n } };
    },

    // One or two paragraphs. The model (what a kassekladde is) only in the starter states: an empty list, or a short one.
    about: function (ctx, st) {
      const f = (st && st.flags) || {};
      if (f.tom || (st && st.state === 'tom')) {
        // Empty behind a search: there are journals, none of them match.
        if (f.soegning) return ['help.kl.about.soegning_tom'];
        return ['help.kl.about.hvad', f.kunEgne ? 'help.kl.about.tom_egne' : 'help.kl.about.tom'];
      }
      // An active search explains a short list better than the model (PLAN D-3).
      if (f.soegning) return ['help.kl.about.liste', 'help.kl.about.soegning'];
      if (f.faa) return ['help.kl.about.hvad', 'help.kl.about.liste'];
      if (f.kunEgne) return ['help.kl.about.liste', 'help.kl.about.kun_egne'];
      return ['help.kl.about.liste'];
    },

    tasks: KL_TASKS,

    order: function (ctx, st) {
      const f = (st && st.flags) || {};
      const ids = [];
      if (f.tom || (st && st.state === 'tom')) {
        if (f.soegning) {
          ids.push('K1');
          if (f.kunEgne) ids.push('K4');
          ids.push('K9', 'K2', 'K10');
          return unique(ids);
        }
        // A new customer: the whole flow, the year (the first stopper), how to organise (PLAN D-2).
        ids.push('K11', 'K10', 'K13');
        if (f.kunEgne) ids.push('K4');
        ids.push('K2');
        return unique(ids);
      }
      // A short list behind a search says nothing about a new customer (as in about()).
      const faa = f.faa && !f.soegning;
      if (f.soegning) ids.push('K1');
      if (f.aabne) ids.push('K3');
      if (f.simuleret) ids.push('K6');
      if (f.laast) ids.push('K5');
      if (faa && f.bogfoert && !f.aabne) ids.push('K12');
      if (faa && f.aabne) ids.push('K11');
      ids.push('K2');
      if (f.kanSlettes) ids.push('K7');
      if (f.bogfoert) ids.push('K8');
      if (f.kunEgne) ids.push('K4');
      if (f.bogfoert) ids.push('K12');
      if (f.mange && !f.soegning) ids.push('K9');
      ids.push('K13', 'K10');
      return unique(ids);
    },

    fields: KL_FIELDS,

    shortcuts: [
      { keys: 'Alt+N', text: 'help.kl.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.kl.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.kl.q.q1.q',
        words: {
          // No 'oprette'/'opprette': the search scores 'rette' as the end of that compound, so 'rette i kladden' found Q1.
          da: ['opret', 'ny kladde', 'ny kassekladde'],
          en: ['create', 'new journal', 'new cash journal', 'add'],
          no: ['opprett', 'ny kladd', 'ny kassekladd']
        },
        steps: ['help.kl.q.q1.step1', 'help.kl.q.q1.step2', 'help.kl.q.q1.step3', 'help.kl.q.q1.step4'],
        show: KL_TASKS.K2.show
      },
      Q2: {
        q: 'help.kl.q.q2.q',
        words: {
          da: ['kan ikke se', 'mangler', 'finde', 'kollega', 'alle kladder', 'væk', 'forsvundet', 'bogført', 'efter bogføring',
            'flyttet', 'hvor er', 'listen er tom', 'tom liste'],
          en: ['cannot see', "can't see", 'missing', 'find', 'colleague', 'all journals', 'gone', 'disappeared', 'posted',
            'after posting', 'moved', 'where is', 'list is empty', 'empty list'],
          no: ['kan ikke se', 'mangler', 'finne', 'kollega', 'alle kladder', 'borte', 'forsvunnet', 'bokført', 'etter bokføring',
            'flyttet', 'hvor er', 'listen er tom', 'tom liste']
        },
        intro: 'help.kl.q.q2.intro',
        steps: ['help.kl.q.q2.step1', 'help.kl.q.q2.step2', 'help.kl.q.q2.step3'],
        show: KL_TASKS.K4.show
      },
      Q3: {
        q: 'help.kl.q.q3.q',
        words: {
          da: ['slet', 'slette', 'fjern', 'tom kladde'],
          en: ['delete', 'remove', 'empty journal'],
          no: ['slett', 'slette', 'fjern', 'tom kladd']
        },
        intro: 'help.kl.q.q3.intro',
        steps: ['help.kl.q.q3.step1', 'help.kl.q.q3.step2', 'help.kl.q.q3.step3'],
        show: KL_TASKS.K7.show
      },
      // The model for a new customer (kassekladde.php:3250-3263 columns, 4742 Gem writes kassekladde only; bogfor.php:887-1131).
      Q4: {
        q: 'help.kl.q.q4.q',
        words: {
          da: ['hvad er', 'kassekladde', 'kladde', 'kladdeliste', 'forklar', 'betyder', 'arbejdsark', 'kom i gang', 'hvorfor flere',
            'start', 'starte', 'begynde', 'første gang', 'moms', 'med moms', 'uden moms'],
          en: ['what is', 'cash journal', 'journal', 'explain', 'get started', 'why several', 'start', 'first time', 'vat'],
          no: ['hva er', 'kassekladd', 'kladd', 'forklar', 'betyr', 'komme i gang', 'starte', 'begynne', 'første gang', 'mva']
        },
        intro: 'help.kl.q.q4.intro',
        // No Vis mig: the answer explains the model and names no control on this page (Vis mig audit, spec §21).
        steps: ['help.kl.q.q4.step1', 'help.kl.q.q4.step2', 'help.kl.q.q4.step3', 'help.kl.q.q4.step4']
      },
      // The three groups (kladdeliste.php:575-581, 685-730); Simulering in Rapporter (rapport_includes/forside.php:308).
      Q5: {
        q: 'help.kl.q.q5.q',
        words: {
          da: ['simuleret', 'simulering', 'bogført', 'åben', 'forskel', 'status', 'grupper', 'prøvebogføring'],
          en: ['simulated', 'posted', 'open', 'status', 'difference'],
          no: ['simulert', 'bokført', 'åpen', 'status', 'forskjell']
        },
        intro: 'help.kl.q.q5.intro',
        steps: ['help.kl.q.q5.step1', 'help.kl.q.q5.step2', 'help.kl.q.q5.step3', 'help.kl.q.q5.step4'],
        show: { target: klGroupHeading, label: 'help.kl.q.q5.show', kind: 'status' }
      },
      Q6: {
        q: 'help.kl.q.q6.q',
        words: {
          da: ['én kladde', 'flere kladder', 'hvor mange', 'pr. måned', 'god praksis', 'organisere', 'navngive'],
          en: ['one journal', 'several journals', 'how many', 'per month', 'good practice'],
          no: ['én kladd', 'flere kladder', 'hvor mange', 'per måned']
        },
        intro: 'help.kl.q.q6.intro',
        steps: stepKeys('kl', 'K13', 1, 4),
        // [1] creates a kladde per statement or month: Ny, not the list's search field (Vis mig audit, spec §21)
        show: KL_TASKS.K2.show
      },
      Q7: {
        q: 'help.kl.q.q7.q',
        words: {
          da: ['udenfor regnskabsår', 'uden for regnskabsår', 'nyt år', 'januar', 'sidste år', 'regnskabsår', 'afvist'],
          en: ['fiscal year', 'financial year', 'outside', 'new year', 'rejected'],
          no: ['regnskapsår', 'utenfor', 'nytt år', 'avvist']
        },
        intro: 'help.kl.task.k10.why',
        steps: stepKeys('kl', 'K10', 1, 4)
      },
      // Search only. A date in a closed VAT month: Bogfør on the overview alerts and still sends the user here, the journal
      // still open (bogfor.php:251-257, 294-296; includes/db_query.php:680-690, printed without æøå, hence bogfoering and
      // genaabne). No 'tilbage' in the texts: the search lets tilbagefør hit it. Q8 (opening balance) is HOLD.
      Q9: {
        q: 'help.kl.q.q9.q',
        words: {
          da: ['lukket', 'perioden er lukket', 'lukket for bogføring', 'bogfoering', 'momsperiode', 'ikke bogført', 'genåbne',
            'genaabne'],
          en: ['closed', 'period closed', 'closed for posting', 'vat period', 'not posted', 'reopen'],
          no: ['lukket', 'perioden er lukket', 'lukket for bokføring', 'mva-periode', 'ikke bokført', 'gjenåpne']
        },
        intro: 'help.kl.q.q9.intro',
        steps: ['help.kl.q.q9.step1', 'help.kl.q.q9.step2', 'help.kl.q.q9.step3']
      }
    },

    // The chat's start screen shows these (spec §25). The first journals: the three groups only once one has left the open
    // group, before that how to organise them. A search hides journals, which Q2 answers.
    questionOrder: function (ctx, st) {
      const f = (st && st.flags) || {};
      if (f.tom || (st && st.state === 'tom')) return f.soegning ? ['Q2', 'Q1', 'Q4'] : ['Q4', 'Q1', 'Q7'];
      if (f.soegning) return ['Q2', 'Q1', 'Q3'];
      if (f.faa) return ['Q4', f.simuleret || f.bogfoert ? 'Q5' : 'Q6', 'Q1'];
      return ['Q1', 'Q2', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['finans/kladdeliste.php'] = kladdeliste;

  // ------------------------------------------------------------------ Kassekladde (finans/kassekladde.php)
  // Evidence: the editable view is form#kassekladde (kassekladde.php:2993); a posted or simulated journal closes the form
  // before its grid (:3040). Saved lines print a clip cell td.clip-cell.clip-no-doc / .clip-has-doc (:3719-3742; the
  // posted grid :2177-2207), the hidden id[N] (:3893), + and x (:3880-3891). The blank new line prints the hidden
  // bilf<N> (:4035-4037); the spare empty lines after it have none (:4104-4150). Footer buttons :4243-4300.

  /** The empty account side of the first one-sided saved line, else Kredit on the line of the field the user stood in. */
  function contraField(ctx, st) {
    const row = st && st.data ? st.data.enSideRow : '';
    if (row) {
      const debe = byName(ctx.doc, 'debe' + row);
      const kred = byName(ctx.doc, 'kred' + row);
      if (debe && String(debe.value || '').trim() === '') return debe;
      if (kred && String(kred.value || '').trim() === '') return kred;
    }
    const n = ctx.field && ctx.field.el ? rowOf(ctx.field.el) : '';
    return n ? byName(ctx.doc, 'kred' + n) : null;
  }

  /** The row number of the blank new line: the last hidden bilf<N> (kassekladde.php:4035-4037; a refused line that is
   *  staged again can carry one too, :3776, so the last one counts). Never input[name^="bila"]:last, a spare line. */
  function kkNewRow(doc) {
    try {
      const list = doc.querySelectorAll('input[type="hidden"][name^="bilf"]');
      const last = list.length ? list[list.length - 1] : null;
      const m = last ? /^bilf(\d+)$/.exec(attr(last, 'name')) : null;
      return m ? m[1] : '';
    } catch (e) {
      return '';
    }
  }

  /** The field prefix<N> of the line the user stood in, else of the blank new line, else of line fallback. */
  function kkRowField(prefix, fallback) {
    return function (ctx, st) {
      const rows = [ctx.field && ctx.field.el ? rowOf(ctx.field.el) : '', st && st.data ? st.data.newRow : '', fallback || ''];
      for (let i = 0; i < rows.length; i++) {
        const el = rows[i] ? byName(ctx.doc, prefix + rows[i]) : null;
        if (el) return el;
      }
      return null;
    };
  }

  const KK_TASKS = {
    // §4.3 and the bookkeeping-flow round (8 Nov 2026), facts in kassekladde.php, bogfor.php, autoudlign.php,
    // bankimport.php, kassekladde_includes/, includes/documents.php and includes/docsIncludes/
    KK1: task('kk', 'KK1', 3, { target: 'input[name="save"]', label: showKey('kk', 'KK1'), kind: 'button' }),
    // [3] a line with a document cannot be deleted (kassekladde.php:4712-4724; x is disabled, :3882-3891)
    KK2: task('kk', 'KK2', 3, {
      target: function (ctx, st) {
        const row = st && st.data ? st.data.manglerKontoRow : '';
        return row ? byName(ctx.doc, 'debe' + row) : null;
      },
      label: fieldLabel('kk', 'debe'),
      kind: 'field'
    }),
    KK3: task('kk', 'KK3', 3, { target: '#kk-balance-status', label: showKey('kk', 'KK3'), kind: 'status', note: 'help.kk.task.kk3.note' }),
    // [4] the Fakturanr. pick fills customer and amount only when empty (accountAutocomplete.js:1859-1870, 2085-2128)
    KK4: task('kk', 'KK4', 4, { target: contraField, label: showKey('kk', 'KK4'), kind: 'field' }),
    // Udlign saves first (kassekladde.php:1742-1777, 1893-1895); Afregn only fills the line, the invoice closes on posting
    // (autoSettlement.php:152-200; bogfor.php:1180-1227); only open items with an invoice number (invoiceSearch.php:96)
    KK5: task('kk', 'KK5', 4, { target: 'input[name="offset"]', label: showKey('kk', 'KK5'), kind: 'button' }),
    // Bogfør saves, then the overview (kassekladde.php:1849-1851); the second Bogfør posts (bogfor.php:737, 294-296)
    KK6: task('kk', 'KK6', 3, { target: 'input[name="doPost"]', label: showKey('kk', 'KK6'), kind: 'button' }),
    // The clip of the first saved line without a document in the editable view (kassekladde.php:3719-3742); the clip
    // saves and opens the pool (:646-666); Indsæt valgte attaches and returns (insertDoc.php:646-668).
    KK7: task('kk', 'KK7', 4, { target: 'form#kassekladde td.clip-cell.clip-no-doc', label: showKey('kk', 'KK7'), kind: 'button' }),
    KK8: task('kk', 'KK8', 4, { target: 'input[name="import"]', label: showKey('kk', 'KK8'), kind: 'button' }),
    // The new line's Bilagstekst; the new line keeps the date of the line above (kassekladde.php:3954)
    KK9: task('kk', 'KK9', 4, {
      target: function (ctx, st) {
        const n = st && st.data ? st.data.newRow : '';
        return (n ? byName(ctx.doc, 'besk' + n) : null) || ctx.doc.querySelector('input[name^="besk"]');
      },
      label: fieldLabel('kk', 'besk'),
      kind: 'field'
    }),
    KK10: task('kk', 'KK10', 2, { target: 'input[name^="bila"][title]:not([title=""])', label: fieldLabel('kk', 'bila'), kind: 'field' }),
    KK11: task('kk', 'KK11', 2, { target: '.invoice-reuse-flag', label: fieldLabel('kk', 'fakt'), kind: 'status' }),
    KK12: task('kk', 'KK12', 3, { target: 'input[name="cancelSimulation"]', label: showKey('kk', 'KK12'), kind: 'button' }),
    // Every posted line repeats id='undo', so the link is found by its href (§4.3). The arrow only lists the open journals
    // (kassekladde.php:5107-5124); [4] the ompost_til pick inserts the reversal, in kroner, and so does every reload of that
    // URL (:910-911, 5125-5133; valutakode at :5129).
    // The pick page's kladde numbers carry ompost= too (ompost=..&ompost_til=.., kassekladde.php:5112): live, KK13 and Q3b
    // rang a number there, and a click on it writes the reversal. Only the arrow itself (Vis mig audit, spec §21).
    KK13: task('kk', 'KK13', 4, { target: 'a[href*="ompost="]:not([href*="ompost_til="])', label: showKey('kk', 'KK13'), kind: 'button' }),
    // The copy drops the lines' VAT codes (copy2new.php:38-66); number and date are prefilled (:78-95)
    KK14: task('kk', 'KK14', 4, { target: '#kopier-button', label: showKey('kk', 'KK14'), kind: 'button' }),
    KK14k: task('kk', 'KK14k', 0, { target: 'input[name="bilagsnr"]', label: showKey('kk', 'KK14k'), kind: 'field' }, stepKeys('kk', 'KK14', 2, 4)),
    KK15: task('kk', 'KK15', 2, { target: 'a[href*="ompost_til="]', label: showKey('kk', 'KK15'), kind: 'button' }),
    KK16: task('kk', 'KK16', 2, { target: 'input[name="kontrolkonto"]', label: fieldLabel('kk', 'kontrolkonto'), kind: 'field' }),
    // E1/Y1 add the VAT on top and post it both ways (postingRules.php:100-139; importfiler/grupper.txt:4-5). [1] chooses the
    // account in Debet, so Vis mig rings Debet; Q11 (the VAT code of a line) keeps the Moms field (Vis mig audit, spec §21)
    KK17: task('kk', 'KK17', 3, { target: kkRowField('debe', '1'), label: fieldLabel('kk', 'debe'), kind: 'field' }),
    // = takes the number of the line above (kassekladde.php:1273); the new line gets the next number (bilagNumber.php:70-102)
    KK18: task('kk', 'KK18', 4, {
      target: function (ctx, st) {
        const n = st && st.data ? st.data.newRow : '';
        return n ? byName(ctx.doc, 'bila' + n) : null;
      },
      label: fieldLabel('kk', 'bila'),
      kind: 'field'
    }),
    // - in Bilag deletes (kassekladde.php:1614-1620, 4712-4724); x asks and sets - (:5677-5689), disabled with a document
    KK19: task('kk', 'KK19', 3, { target: 'button.delete-line-btn:not([disabled])', label: showKey('kk', 'KK19'), kind: 'button' }),
    // Account numbers of a posted journal link to the account or the card (kassekladde.php:2315, 2406; accountCard.php:13-49)
    KK20: task('kk', 'KK20', 3, { target: 'a.kk-card-link', label: showKey('kk', 'KK20'), kind: 'row' }),
    // Dragging the paper icon onto another clip links the document after a confirm (kassekladde.php:5897-6072)
    KK22: task('kk', 'KK22', 4, { target: 'td.clip-cell.clip-has-doc', label: showKey('kk', 'KK22'), kind: 'button' }),
    // Opdatér saves the note, also on a posted journal (kassekladde.php:3014, 1715-1727). The title and [1] name Bemærkning
    // (ny_kladdenote, :3006), so Vis mig rings the field; Opdatér is [2] (Vis mig audit, spec §21)
    KK23: task('kk', 'KK23', 2, { target: 'input[name="ny_kladdenote"]', label: fieldLabel('kk', 'ny_kladdenote'), kind: 'field' }),
    // The lookup pages (financeLookup.php:36, 116-126; openpost_inc.php:94-113, 300-316); no target, several variants
    KK24: task('kk', 'KK24', 3),
    // The gear and its panel (topLineKassekladde.php:102-107; kassekladde.php:3110-3160, 793-798)
    KK25: task('kk', 'KK25', 4, { target: '#kkVisToggle', label: showKey('kk', 'KK25'), kind: 'button' }),
    // Search by part of the name (accountAutocomplete.js:651-684, 1284-1298); D/K for customers and suppliers
    KK26: task('kk', 'KK26', 4, { target: kkRowField('debe', '1'), label: fieldLabel('kk', 'debe'), kind: 'field' }),
    // Practice, labelled as advice; sales invoices post themselves on a new tenant (opretfunc.php:718; ordrefunc.php:1713-1753)
    KK27: task('kk', 'KK27', 4, { target: 'input[name="import"]', label: showKey('kk', 'KK27'), kind: 'button' }),
    // Only the selected year counts (checkOpenFiscalYear.php:26-41); the list holds the years with Bogføring tilladt only
    // (std_func.php:909-910); a new year becomes the active one (rowHooks.php:795-803)
    KK28: task('kk', 'KK28', 4, { target: '#kkRegnaar', label: fieldLabel('kk', 'kkRegnaar'), kind: 'field' }),
    // The paper icon opens the view-only tab saldiBilag (kassekladde.php:668-697; documents.php:152-217)
    KK29: task('kk', 'KK29', 3, { target: 'td.clip-cell.clip-has-doc', label: showKey('kk', 'KK29'), kind: 'button' }),
    // Administrér bilag brings the full page into the journal's frame (kassekladde.php:692-697); Flyt til pulje, Fjern link,
    // Slet (listDocs.php:176-199; moveDoc.php:74-125; deleteDoc.php:129-132). A posted journal's file older than a day prints
    // Låst instead (listDocs.php:85-95, 180, 198), so KK30 is not offered in bogfoert.
    KK30: task('kk', 'KK30', 4, { target: 'td.clip-cell.clip-has-doc', label: showKey('kk', 'KK30'), kind: 'button' }),
    // The check before posting: Vis mig rings Bogfør (kassekladde.php:4282), which the title names and [4] clicks, and its
    // note says to check first and that Bogfør shows the overview before anything is posted (:1849-1851; bogfor.php:737).
    // Not the clip of [1]: that is KK7's target, and the owner found it pointed at the wrong thing (Vis mig audit, spec §21).
    KK31: task('kk', 'KK31', 4, { target: 'input[name="doPost"]', label: showKey('kk', 'KK31'), kind: 'button', note: 'help.kk.task.kk31.note' }),
    // [1] puts the account the money goes into in Debet, so Vis mig rings Debet; Q18 (a credit note, [1] Debet) shares it
    // (Vis mig audit, spec §21)
    KK32: task('kk', 'KK32', 3, { target: kkRowField('debe', '1'), label: fieldLabel('kk', 'debe'), kind: 'field' }),
    // Forfald shows on a saved line with a supplier in Kredit (kassekladde.php:3829-3836); settles on posting (bogfor.php:1180-1227)
    KK33: task('kk', 'KK33', 4),
    // Bilags match (kassekladde.php:4281; bilagsmatch.php:203-209, 391-435, 618-621)
    KK34: task('kk', 'KK34', 4, { target: 'input[name="bilagsmatch"]', label: showKey('kk', 'KK34'), kind: 'button' }),
    // + reloads without saving and copies the documents, not the VAT codes (kassekladde.php:5612-5672; duplicate_line.php:84-170)
    KK35: task('kk', 'KK35', 4, { target: 'button.duplicate-line-btn', label: showKey('kk', 'KK35'), kind: 'button' })
  };

  const KK_FIELDS = {};
  ['bila', 'dato', 'besk', 'd_ty', 'k_ty', 'debe', 'kred', 'dvat', 'kvat', 'fakt', 'belo', 'afd', 'meda', 'proj', 'valu',
    'forf', 'b_id', 'moms', 'ny_kladdenote', 'kontrolkonto', 'kkRegnaar']
    .forEach(function (k) { KK_FIELDS[k] = fieldKeys('kk', k); });

  const KK_ABOUT = {
    afvist: 'help.kk.about.afvist',
    simuleret: 'help.kk.about.simuleret',
    bogfoert: 'help.kk.about.bogfoert',
    vaelgTilbagefoersel: 'help.kk.about.vaelg_tilbagefoersel',
    kopier: 'help.kk.about.kopier',
    opslag: 'help.kk.about.opslag'
  };

  /** An open journal without saved lines. The line flags imply saved lines, so they rule it out too (the order tests
   *  set them without hasSaved). */
  function kkStarter(f) {
    return !f.hasSaved && !f.difference && !f.balancerer && !f.manglerKonto && !f.enSide;
  }

  const KK_START = ['KK9', 'KK8', 'KK27', 'KK26', 'KK18'];

  const kassekladde = {
    group: 'kk',
    states: ['ny', 'aaben', 'afvist', 'simuleret', 'bogfoert', 'afbrudt', 'kopier', 'vaelgTilbagefoersel', 'opslag', 'udskrift'],
    flags: ['hasSaved', 'difference', 'balancerer', 'ikkeGemt', 'manglerKonto', 'enSide', 'dublet', 'fakturaBrugt', 'kontrolkonto',
      'udenBilag', 'harBilag', 'mange'],

    name: function (ctx, st) {
      if (st && st.state === 'ny') return 'help.kk.name_new';
      const id = st && st.data ? String(st.data.kladdeId || '') : '';
      return id ? ['help.kk.name', id] : 'help.kk.name_plain';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const q = function (s) { return doc.querySelector(s); };
      const form = q('form#kassekladde');
      const url = ctx.url;
      let state;
      if (url.searchParams.has('udskriv')) state = 'udskrift';
      else if (!form && q('input[name="vend_fortegn"]')) state = 'kopier';                 // copy2new dialog
      else if (!form && url.searchParams.has('ompost') && !url.searchParams.has('ompost_til')) state = 'vaelgTilbagefoersel';
      else if (!form) state = 'opslag';
      else if (q('input[name="cancelSimulation"]')) state = 'simuleret';
      else if (q('input[name="revert"]')) state = 'afbrudt';                                // before the ompost check: '!' has undo links
      else if (q('#kopier-button, a[href*="ompost="]')) state = 'bogfoert';
      else state = null;

      const els = form ? form.elements : null;
      // Value of one named control, '' when missing. form.elements is indexed once per document by the browser;
      // a repeated name (kladde_id is printed twice, kassekladde.php:2993 and :4177) gives a list: take the first.
      const val = function (name) {
        let el = els && els.namedItem ? els.namedItem(name) : null;
        if (el && el.nodeType !== 1) el = el[0];
        return el && el.nodeType === 1 ? String(el.value || '').trim() : '';
      };
      if (state === null) {
        const kid = val('kladde_id');
        if (kid === '' || kid === '0') state = 'ny';
        else if (!q('input[name="doPost"]')) state = 'afvist';
        else state = 'aaben';
      }
      // Saved lines: one pass over the hidden id[N] inputs (kassekladde.php:3893).
      const saved = [];
      if (form) {
        const ids = form.querySelectorAll('input[type="hidden"][name^="id["]');
        for (let i = 0; i < ids.length; i++) {
          const m = /^id\[(\d+)\]$/.exec(ids[i].getAttribute('name') || '');
          if (m && String(ids[i].value || '') !== '') saved.push(m[1]);
        }
      }
      const nonZero = function (s) { return s !== '' && !/^-?0*([.,]0*)?$/.test(s); };
      const manglerKontoRow = saved.find(function (n) {
        return !val('debe' + n) && !val('kred' + n) && (!!val('besk' + n) || !!val('fakt' + n) || nonZero(val('belo' + n)));
      }) || '';
      const enSideRow = saved.find(function (n) { return !!val('debe' + n) !== !!val('kred' + n); }) || '';
      const bal = q('#kk-balance-status span span');
      let color = '';
      if (bal) {
        try { color = ctx.win.getComputedStyle(bal).color; } catch (e) { color = ''; }
      }
      const flags = {
        hasSaved: saved.length > 0,
        difference: saved.length > 0 && color === 'rgb(204, 0, 0)',
        balancerer: saved.length > 0 && color === 'rgb(26, 122, 26)',
        ikkeGemt: !!ctx.win && ctx.win.docChange === true,
        manglerKonto: !!manglerKontoRow,
        enSide: !!enSideRow,
        dublet: !!q('input[name^="bila"][title]:not([title=""])'),
        fakturaBrugt: !!q('.invoice-reuse-flag'),
        kontrolkonto: !!val('kontrolkonto'),
        // A saved line of the editable view without a document; the posted grid is outside the form (kassekladde.php:3040)
        udenBilag: !!q('form#kassekladde td.clip-cell.clip-no-doc'),
        // A line with a document, in the editable view or the posted grid (kassekladde.php:2200, 3736)
        harBilag: !!q('td.clip-cell.clip-has-doc'),
        // A long journal, such as a bank import: the bookkeeper's tools move up (PLAN §1.6 item 4)
        mange: saved.length > 15
      };
      const data = {
        kladdeId: form ? val('kladde_id').replace(/^0$/, '') : (url.searchParams.get('kladde_id') || ''),
        manglerKontoRow: manglerKontoRow,
        enSideRow: enSideRow,
        newRow: form ? kkNewRow(doc) : ''
      };
      return { state: state, flags: flags, data: data };
    },

    // The model paragraph only in the starter states (a new journal, an open one without saved lines), so a bookkeeper
    // on a working journal reads one paragraph (PLAN §1.6 item 1).
    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const out = [];
      if (state === 'ny') {
        out.push('help.kk.about.ny');
        out.push(f.ikkeGemt ? 'help.kk.about.ikke_gemt' : 'help.kk.about.model');
        return out;
      }
      if (state === 'aaben') {
        // A line without an account stops posting although the journal balances (bogfor.php:133-141).
        const ready = !f.difference && f.balancerer && !f.manglerKonto;
        if (f.difference) out.push('help.kk.about.aaben_difference');
        else if (ready) out.push('help.kk.about.aaben_balancerer');
        else out.push('help.kk.about.aaben');
        if (f.ikkeGemt) out.push('help.kk.about.ikke_gemt');
        else if (kkStarter(f)) out.push('help.kk.about.model');
        // Ready to post while saved lines lack a document: say so before KK6 Bogfør leads (PLAN D-11 keeps that order)
        else if (ready && f.udenBilag) out.push('help.kk.about.uden_bilag');
        return out;
      }
      if (KK_ABOUT[state]) out.push(KK_ABOUT[state]);
      if (f.ikkeGemt) out.push('help.kk.about.ikke_gemt');
      return out;
    },

    tasks: KK_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      let ids = [];
      if (state === 'vaelgTilbagefoersel') ids = ['KK15'];
      else if (state === 'kopier') ids = ['KK14k'];
      else if (state === 'opslag') ids = ['KK24'];
      else if (state === 'simuleret') ids = ['KK12'];
      else if (state === 'afvist') ids = ['KK1'].concat(f.manglerKonto ? ['KK2'] : [], ['KK28', 'KK26']);
      else if (state === 'ny') ids = KK_START.slice();
      else if (state === 'bogfoert') ids = ['KK20', 'KK13', 'KK14'].concat(f.harBilag ? ['KK29'] : [], ['KK23']);
      else if (state === 'aaben') {
        if (kkStarter(f)) return KK_START.slice();
        const fieldKey = ctx && ctx.field ? ctx.field.key : '';
        if (f.manglerKonto) ids.push('KK2');
        if (f.dublet) ids.push('KK10');
        if (f.fakturaBrugt) ids.push('KK11');
        if (f.difference) {
          ids.push('KK3');
          if (f.enSide || fieldKey === 'debe' || fieldKey === 'kred') ids.push('KK4');
          ids.push(f.mange ? 'KK5' : 'KK18');
          if (f.enSide) ids.push('KK5');
        }
        if (f.balancerer) {
          ids.push('KK6');
          if (f.udenBilag) ids.push(f.mange ? 'KK34' : 'KK7');
          ids.push('KK31');
        }
        if (f.hasSaved && !f.mange) ids.push('KK18');
        ids.push('KK8');
        if (f.hasSaved && !f.kontrolkonto) ids.push('KK16');
        if (f.harBilag) ids.push('KK29', 'KK30');
        ids.push('KK7', 'KK26', 'KK33', 'KK32', 'KK17', 'KK19');
      }
      return unique(ids);
    },

    fields: KK_FIELDS,

    shortcuts: [
      { keys: 'Enter', text: 'help.kk.key.01', group: 'keys' },
      { keys: 'Alt+G', text: 'help.kk.key.02', group: 'keys' },
      { keys: 'Alt+O', text: 'help.kk.key.03', group: 'keys' },
      { keys: 'Alt+S', text: 'help.kk.key.04', group: 'keys' },
      { keys: 'Alt+B', text: 'help.kk.key.05', group: 'keys' },
      { keys: 'Alt+I', text: 'help.kk.key.06', group: 'keys' },
      { keys: 'Alt+U', text: 'help.kk.key.07', group: 'keys' },
      { keys: 'Alt+L', text: 'help.kk.key.08', group: 'keys' },
      { keys: 'Alt+N', text: 'help.kk.key.09', group: 'keys' },
      { keys: 'Ctrl+← / Ctrl+→', text: 'help.kk.key.10', group: 'keys' },
      { keys: 'Ctrl+↑ / Ctrl+↓', text: 'help.kk.key.11', group: 'keys' },
      { keys: '↑ ↓, Enter, Ctrl+Enter, Esc', text: 'help.kk.key.12', group: 'keys' },
      // Codes in Bilag (kassekladde.php:1262-1315) and in Fakturanr. (bogfor.php:1183-1230)
      { keys: '-', text: 'help.kk.key.13', group: 'codes' },
      { keys: '-*', text: 'help.kk.key.14', group: 'codes' },
      { keys: '-> ... <-', text: 'help.kk.key.20', group: 'codes' },
      { keys: '=', text: 'help.kk.key.15', group: 'codes' },
      { keys: '=*', text: 'help.kk.key.21', group: 'codes' },
      { keys: '+', text: 'help.kk.key.16', group: 'codes' },
      { keys: '12+3', text: 'help.kk.key.17', group: 'codes' },
      { keys: '12+=', text: 'help.kk.key.18', group: 'codes' },
      { keys: '=', text: 'help.kk.key.19', group: 'codes' },
      { keys: 'A;B', text: 'help.kk.key.22', group: 'codes' },
      { keys: '-', text: 'help.kk.key.23', group: 'codes' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.kk.q.q1.q',
        words: {
          da: ['difference', 'differencen', 'balancerer', 'balance', 'går ikke i nul', 'skæv'],
          en: ['difference', 'balance', 'balances', 'not zero', 'out of balance'],
          no: ['differanse', 'differansen', 'balanserer', 'balanse', 'går ikke i null', 'skjev']
        },
        intro: 'help.kk.q.q1.intro',
        steps: ['help.kk.q.q1.step1', 'help.kk.task.kk3.step2', 'help.kk.task.kk3.step3', 'help.kk.q.q1.step4'],
        show: KK_TASKS.KK3.show
      },
      Q2: {
        q: 'help.kk.q.q2.q',
        // Not 'simuler': Simulér is not a step in posting (§4.3).
        words: {
          da: ['bogfør', 'bogføre', 'bogfoer', 'bogføring', 'bogført', 'er det bogført', 'kontrolsum'],
          en: ['post', 'posting', 'book', 'is it posted', 'checksum'],
          no: ['bokfør', 'bokføre', 'bokføring', 'er det bokført', 'sjekksum']
        },
        intro: 'help.kk.q.q2.intro',
        steps: ['help.kk.q.q2.step1', 'help.kk.q.q2.step2', 'help.kk.q.q2.step3', 'help.kk.q.q2.step4'],
        show: KK_TASKS.KK6.show
      },
      Q3: {
        q: 'help.kk.q.q3.q',
        words: {
          da: ['debet', 'kredit', 'modkonto', 'modkontoen', 'konto', 'd/k', 'hvilken side', 'forkert side', 'køb', 'salg', 'regning',
            'kvittering', 'modpost', 'forstår ikke', 'kort', 'kortbetaling', 'betalt med kort', 'mobilepay', 'samlekonto'],
          en: ['debit', 'credit', 'contra account', 'account', 'd/k', 'which side', 'purchase', 'sale', 'receipt', 'card', 'card payment',
            'mobilepay'],
          no: ['debet', 'kredit', 'motkonto', 'motkontoen', 'konto', 'd/k', 'hvilken side', 'kjøp', 'salg', 'kvittering', 'kort', 'kortbetaling',
            'vipps']
        },
        intro: 'help.kk.q.q3.intro',
        steps: ['help.kk.q.q3.step1', 'help.kk.q.q3.step2', 'help.kk.q.q3.step3', 'help.kk.q.q3.step4'],
        show: {
          target: function (ctx) {
            const n = ctx.field && ctx.field.el ? rowOf(ctx.field.el) : '';
            return byName(ctx.doc, 'kred' + (n || '1'));
          },
          label: fieldLabel('kk', 'kred'),
          kind: 'field'
        }
      },
      Q3b: {
        q: 'help.kk.q.q3b.q',
        words: {
          da: ['ret', 'rette', 'fejl', 'tilbagefør', 'tilbageføre', 'bogført', 'forkert', 'modpostering', 'modpostere', 'ompostering',
            'fortryd', 'slet', 'slette', 'ændre'],
          en: ['correct', 'mistake', 'error', 'reverse', 'posted', 'wrong', 'reversing entry', 'undo', 'delete', 'change'],
          no: ['rett', 'rette', 'feil', 'tilbakefør', 'tilbakeføre', 'bokført', 'motpostering', 'angre', 'slette', 'endre']
        },
        // Four steps at most: q3b.step4 holds the message and KK13 [3]; KK13 [4] is the warning.
        steps: stepKeys('kk', 'KK13', 1, 2).concat(['help.kk.q.q3b.step4'], stepKeys('kk', 'KK13', 4, 4)),
        show: KK_TASKS.KK13.show
      },
      Q3c: {
        q: 'help.kk.q.q3c.q',
        words: {
          da: ['rette', 'ret', 'låst', 'simuleret', 'kan ikke'],
          en: ['edit', 'change', 'locked', 'simulated', 'cannot'],
          no: ['rette', 'rett', 'låst', 'simulert', 'kan ikke']
        },
        steps: stepKeys('kk', 'KK12', 1, 3),
        show: KK_TASKS.KK12.show
      },
      Q3d: {
        q: 'help.kk.q.q3d.q',
        words: {
          da: ['kunde', 'indbetaling', 'udlign', 'udligne', 'åben post'],
          en: ['customer', 'payment', 'equalize', 'settle', 'open item'],
          no: ['kunde', 'innbetaling', 'utjevner', 'utligne', 'åpen post']
        },
        steps: stepKeys('kk', 'KK5', 1, 4),
        show: KK_TASKS.KK5.show
      },
      // Gem writes kassekladde only (kassekladde.php:4742); only bogfor.php:887-1131 writes transaktioner
      Q4: {
        q: 'help.kk.q.q4.q',
        words: {
          da: ['gem', 'gemme', 'gemt', 'gemmer', 'forskel', 'er det bogført', 'bogført nu', 'enter', 'ikke bogført', 'kassekladde',
            'forsvundet', 'væk', 'mistet', 'simulér', 'simulere', 'simulering'],
          en: ['save', 'saved', 'difference', 'posted', 'cash journal', 'lost', 'disappeared', 'gone', 'simulate'],
          no: ['lagre', 'lagret', 'forskjell', 'bokført', 'kassekladd', 'forsvunnet', 'borte', 'mistet', 'simuler']
        },
        intro: 'help.kk.q.q4.intro',
        steps: ['help.kk.q.q4.step1', 'help.kk.q.q4.step2', 'help.kk.q.q4.step3', 'help.kk.q.q4.step4'],
        show: KK_TASKS.KK6.show
      },
      // The lock: another user within the hour (kassekladde.php:856-872); Tilbage releases it (includes/luk.php:98-107)
      Q5: {
        q: 'help.kk.q.q5.q',
        words: {
          da: ['i brug', 'låst', 'optaget', 'kan ikke åbne', 'anden bruger', 'rød'],
          en: ['in use', 'locked', 'another user'],
          no: ['i bruk', 'låst', 'annen bruker']
        },
        intro: 'help.kk.q.q5.intro',
        steps: ['help.kk.q.q5.step1', 'help.kk.q.q5.step2']
      },
      Q6: {
        q: 'help.kk.q.q6.q',
        words: {
          da: ['udenfor regnskabsår', 'uden for regnskabsår', 'regnskabsår', 'nyt år', 'januar', 'sidste år', 'forkert år', 'ikke gemt'],
          en: ['fiscal year', 'financial year', 'outside', 'new year', 'last year'],
          no: ['regnskapsår', 'utenfor', 'nytt år']
        },
        intro: 'help.kk.q.q6.intro',
        steps: stepKeys('kk', 'KK28', 1, 4),
        show: KK_TASKS.KK28.show
      },
      Q7: {
        q: 'help.kk.q.q7.q',
        words: {
          da: ['se', 'bogført', 'hvor', 'kontoen', 'saldo', 'resultat', 'forsvundet', 'hvor er', 'kontokort'],
          en: ['where', 'posted', 'account', 'balance'],
          no: ['hvor', 'bokført', 'konto', 'saldo']
        },
        intro: 'help.kk.q.q7.intro',
        steps: stepKeys('kk', 'KK20', 1, 3),
        show: KK_TASKS.KK20.show
      },
      Q8: {
        q: 'help.kk.q.q8.q',
        words: {
          da: ['slet', 'slette', 'fjern', 'linje', 'slet linje', 'minus'],
          en: ['delete line', 'remove line'],
          no: ['slett', 'slette linje']
        },
        steps: stepKeys('kk', 'KK19', 1, 3),
        show: KK_TASKS.KK19.show
      },
      Q9: {
        q: 'help.kk.q.q9.q',
        words: {
          da: ['leverandørfaktura', 'regning', 'betales senere', 'kreditor', 'åben post', 'skyldig'],
          en: ['supplier invoice', 'bill', 'pay later', 'open item'],
          no: ['leverandørfaktura', 'regning', 'betales senere', 'åpen post']
        },
        steps: stepKeys('kk', 'KK33', 1, 4)
      },
      Q10: {
        q: 'help.kk.q.q10.q',
        words: {
          da: ['samme bilag', 'flere linjer', 'linke', 'træk', 'dele bilag'],
          en: ['same document', 'several lines', 'link'],
          no: ['samme bilag', 'flere linjer', 'lenke']
        },
        steps: stepKeys('kk', 'KK22', 1, 4),
        show: KK_TASKS.KK22.show
      },
      // VAT from the account's code at posting (postingRules.php:94-143; grupper.txt:2-5; kassekladde.php:723-756)
      Q11: {
        q: 'help.kk.q.q11.q',
        words: {
          da: ['moms', 'momskode', 'momsen', 'købsmoms', 'salgsmoms', 'inkl moms', 'inklusive moms', 'med moms', 'uden moms', 'momsfradrag',
            'netto', 'brutto', 'omvendt betalingspligt', 'eu', 'udland', 's1', 'k1', 'e1', 'y1'],
          en: ['vat', 'vat code', 'including vat', 'excluding vat', 'reverse charge', 'eu'],
          no: ['mva', 'mva-kode', 'inkludert mva', 'uten mva', 'omvendt avgiftsplikt']
        },
        intro: 'help.kk.q.q11.intro',
        steps: ['help.kk.q.q11.step1', 'help.kk.q.q11.step2', 'help.kk.q.q11.step3', 'help.kk.q.q11.step4'],
        // The VAT code of the line: the Moms field by Debet (kassekladde.php:723-756)
        show: { target: kkRowField('dvat', '1'), label: fieldLabel('kk', 'dvat'), kind: 'field' }
      },
      Q12: {
        q: 'help.kk.q.q12.q',
        words: {
          da: ['hvilken konto', 'kontoplan', 'gebyr', 'rente', 'privat', 'udlæg', 'mellemregning', 'hævning', 'indskud', 'overførsel',
            'egen konto', 'kontorartikler', 'husleje', 'telefon'],
          en: ['which account', 'bank fee', 'interest', 'private', 'owner', 'transfer'],
          no: ['hvilken konto', 'gebyr', 'rente', 'privat', 'utlegg', 'overføring']
        },
        intro: 'help.kk.q.q12.intro',
        steps: ['help.kk.q.q12.step1', 'help.kk.q.q12.step2', 'help.kk.q.q12.step3', 'help.kk.q.q12.step4'],
        show: KK_TASKS.KK26.show
      },
      // VAT is split from the chart's codes whatever the registration (bogfor.php:408-420; kontokort.php:544-559). A saved line
      // keeps its own code (kassekladde.php:519-549, 4851), and posting uses it before the account's (postingRules.php:92-97),
      // so step4 (shown second) clears it on the lines already saved.
      Q13: {
        q: 'help.kk.q.q13.q',
        words: {
          da: ['ikke momsregistreret', 'momsfri', 'uden moms', 'ingen moms', 'ikke momspligtig', 'under grænsen'],
          en: ['not vat registered', 'no vat', 'vat exempt'],
          no: ['ikke mva-registrert', 'uten mva']
        },
        intro: 'help.kk.q.q13.intro',
        steps: ['help.kk.q.q13.step1', 'help.kk.q.q13.step4', 'help.kk.q.q13.step2', 'help.kk.q.q13.step3'],
        show: { target: 'input[name^="moms"][type="checkbox"]', label: fieldLabel('kk', 'moms'), kind: 'field' }
      },
      // The refusal alerts (kassekladde.php:4333-4343, 4508-4549, 4642-4655; texts 1589, 1593, 5089, 1595)
      Q14: {
        q: 'help.kk.q.q14.q',
        words: {
          da: ['ikke gemt', 'fejl', 'besked', 'kan ikke gemme', 'finanskonti', 'eksisterer ikke', 'ugyldigt beløb', 'udenfor regnskabsår'],
          en: ['not saved', 'error', 'message'],
          no: ['ikke lagret', 'feil', 'melding']
        },
        intro: 'help.kk.q.q14.intro',
        steps: ['help.kk.q.q14.step1', 'help.kk.q.q14.step2', 'help.kk.q.q14.step3', 'help.kk.q.q14.step4'],
        show: KK_TASKS.KK1.show
      },
      Q15: {
        q: 'help.kk.q.q15.q',
        words: {
          da: ['kontoudtog', 'bilag først', 'bank først', 'dobbelt', 'to gange', 'arbejdsgang', 'hvor starter jeg'],
          en: ['bank statement first', 'twice', 'double'],
          no: ['kontoutskrift', 'dobbelt', 'to ganger']
        },
        steps: stepKeys('kk', 'KK27', 1, 4),
        show: KK_TASKS.KK27.show
      },
      Q16: {
        q: 'help.kk.q.q16.q',
        words: {
          da: ['se bilag', 'papir', 'ikon', 'fane', 'vindue', 'hvor er bilaget', 'åbne bilag', 'mens', 'samtidig', 'anden skærm', 'vis bilag'],
          en: ['view document', 'tab', 'second screen', 'while', 'show document'],
          no: ['se bilag', 'fane', 'samtidig', 'annen skjerm', 'vis bilag']
        },
        steps: stepKeys('kk', 'KK29', 1, 3),
        show: KK_TASKS.KK29.show
      },
      // The second Bogfør refuses the whole journal when a line's month is closed (bogfor.php:250-257; the message names
      // MM-ÅÅÅÅ, includes/db_query.php:680-690); months are closed in finans/moms_periode.php
      Q17: {
        q: 'help.kk.q.q17.q',
        words: {
          da: ['lukket', 'perioden er lukket', 'lukket for bogføring', 'periode', 'momsperiode', 'genåbne', 'åbne igen'],
          en: ['period closed', 'closed period', 'closed', 'reopen'],
          no: ['lukket', 'periode', 'gjenåpne', 'åpne igjen']
        },
        intro: 'help.kk.q.q17.intro',
        steps: ['help.kk.q.q17.step1', 'help.kk.q.q17.step2']
      },
      // VAT follows the side of the account: a K1 account in Kredit credits the input VAT account (postingRules.php:115-143;
      // bogfor.php:419, 481). A credit note against an unpaid bill (settlement of part amounts) is left out on purpose.
      Q18: {
        q: 'help.kk.q.q18.q',
        words: {
          da: ['kreditnota', 'refusion', 'penge tilbage', 'retur', 'tilbagebetaling'],
          en: ['credit note', 'refund'],
          no: ['kreditnota', 'refusjon', 'penger tilbake']
        },
        intro: 'help.kk.q.q18.intro',
        steps: ['help.kk.q.q18.step1', 'help.kk.q.q18.step2'],
        show: KK_TASKS.KK32.show
      }
    },

    // The chat's start screen shows these (spec §25). A line without an account (problem card KK2): which account. A posted
    // journal: how to correct it before where it went, and the line's document when it has one. The reversal pick and the
    // copy dialog: Q3b; the lookup list: which account.
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'ny' || (state === 'aaben' && kkStarter(f))) return ['Q4', 'Q3', 'Q11'];
      if (state === 'afvist') return ['Q14', 'Q6', 'Q4'];
      if (state === 'bogfoert') return ['Q3b', 'Q7', f.harBilag ? 'Q16' : 'Q4'];
      if (state === 'simuleret') return ['Q3c', 'Q4', 'Q2'];
      if (state === 'vaelgTilbagefoersel' || state === 'kopier') return ['Q3b', 'Q2', 'Q4'];
      if (state === 'opslag') return ['Q12', 'Q3', 'Q11'];
      if (state === 'aaben' && f.manglerKonto) return f.difference ? ['Q12', 'Q1', 'Q2'] : ['Q12', 'Q2', 'Q4'];
      if (state === 'aaben' && f.enSide) return ['Q1', 'Q2', 'Q3d'];
      if (state === 'aaben' && f.difference) return ['Q1', 'Q2', 'Q3'];
      // Q4 before Q11: "er det bogført nu" otherwise ties to Q7 first (search breaks ties by this order)
      if (state === 'aaben') return ['Q2', 'Q4', 'Q11'];
      return ['Q2', 'Q4', 'Q3'];
    },

    // Read by the shell only when the host has data-signal="1", which phase 1 never prints (D11).
    signal: function (ctx, st) {
      return !!st && st.state === 'afvist';
    }
  };
  PAGES['finans/kassekladde.php'] = kassekladde;

  // ================================================================== a1: Regnskab, Budget, Kontoplan, Kontokort, Kontospecifikation
  // Plain functions on top of the shared helpers (task, showKey, fieldKeys, fieldLabel, stepKeys, unique, attr, byName, rowOf).
  // Detectors and show targets only read the frame document. Evidence per selector: a1.md.

  /** querySelector/querySelectorAll that never throw (an odd document or selector gives null / []). */
  function a1q(doc, sel) {
    try {
      const el = doc && doc.querySelector ? doc.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function a1qa(doc, sel) {
    try {
      return doc && doc.querySelectorAll ? (doc.querySelectorAll(sel) || []) : [];
    } catch (e) {
      return [];
    }
  }

  function a1text(el) {
    return el ? String(el.textContent || '') : '';
  }

  /** True when a grid's search row has any text (includes/grid.php:1396; the grid stores it per user, :426-429). */
  function a1searching(doc, gridId) {
    const inputs = a1qa(doc, 'input[name^="search[' + gridId + ']"]');
    for (let i = 0; i < inputs.length; i++) {
      if (String(inputs[i].value || '').trim() !== '') return true;
    }
    return false;
  }

  /** The total behind a grid's page status "1-100 af 250" (includes/grid.php:1551), or -1 when it is not there. */
  function a1gridTotal(doc, gridId) {
    const el = a1q(doc, '#datatable-wrapper-' + gridId + ' #page-status');
    const nums = el ? a1text(el).match(/\d+/g) : null;
    return nums && nums.length ? parseInt(nums[nums.length - 1], 10) : -1;
  }

  /** The value of the first option of a select: the saved value on the classic cards, which print it first (kontokort.php:480, :514, :547). */
  function a1firstOption(sel) {
    try {
      const o = sel && sel.querySelector ? sel.querySelector('option') : null;
      if (!o || o.nodeType !== 1) return '';
      const v = o.getAttribute('value');
      return String(v !== null ? v : (o.textContent || '')).trim();
    } catch (e) {
      return '';
    }
  }

  /** True when one of the cells matched by sel contains the text (cell by cell, so it works on any DOM). */
  function a1cellsContain(doc, sel, text) {
    const cells = a1qa(doc, sel);
    for (let i = 0; i < cells.length; i++) {
      if (a1text(cells[i]).indexOf(text) !== -1) return true;
    }
    return false;
  }

  // ---- Regnskab (finans/regnskab.php)
  // Evidence: main.php:224; regnskab.php:237 rows, :498 td[onclick^=click_row], :515 primo, :536 month links, :146-150 Budget, :583 csv, :564-577/:758-775 graph; year = session (online.php:166).

  /** The first month amount that is not 0,00, else the first month amount (regnskab.php:536). */
  function rgAmountLink(ctx) {
    const links = a1qa(ctx.doc, '#regnskab-wrapper tbody a[href^="kontospec.php"]');
    for (let i = 0; i < links.length; i++) {
      if (/[1-9]/.test(a1text(links[i]))) return links[i];
    }
    return links.length && links[0].nodeType === 1 ? links[0] : null;
  }

  /** The account number cell of the row with the first non-zero amount, else the first account number (regnskab.php:498). */
  function rgAccountCell(ctx) {
    const a = rgAmountLink(ctx);
    const tr = a && a.closest ? a.closest('tr') : null;
    const td = tr && tr.querySelector ? tr.querySelector('td[onclick^="click_row"]') : null;
    if (td && td.nodeType === 1) return td;
    return a1q(ctx.doc, '#regnskab-wrapper tbody td[onclick^="click_row"]');
  }

  const RG_TASKS = {
    RG1: task('rg', 'RG1', 3, { target: rgAmountLink, label: showKey('rg', 'RG1'), kind: 'button' }),
    RG2: task('rg', 'RG2', 3, { target: rgAccountCell, label: showKey('rg', 'RG2'), kind: 'button' }),
    RG3: task('rg', 'RG3', 3, { target: '#budget-link', label: showKey('rg', 'RG3'), kind: 'button' }),
    RG4: task('rg', 'RG4', 2, { target: '#csv-export', label: showKey('rg', 'RG4'), kind: 'button' }),
    RG5: task('rg', 'RG5', 3),            // the switch is in the shell's topbar, outside the frame: no Vis mig
    RG6: task('rg', 'RG6', 3),            // Finans > Rapporter in the sidebar
    RG7: task('rg', 'RG7', 3),            // Indstillinger > Regnskabsår > Åbningsbalance
    RG8: task('rg', 'RG8', 2),            // Finans > Kontoplan in the sidebar
    RG9: task('rg', 'RG9', 4)             // Finans > Kassekladde in the sidebar: posted kladder and invoices are what this page shows
  };

  const regnskab = {
    group: 'rg',
    states: ['tom', 'oversigt', 'graf'],
    flags: ['harBevaegelser', 'ingenPrimo'],

    name: function () {
      return 'help.rg.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const accounts = a1qa(doc, '#regnskab-wrapper tbody td[onclick^="click_row"]');
      const links = a1qa(doc, '#regnskab-wrapper tbody a[href^="kontospec.php"]');
      let harBevaegelser = false;
      for (let i = 0; i < links.length && !harBevaegelser; i++) harBevaegelser = /[1-9]/.test(a1text(links[i]));
      // Primo is the cell just before a row's first month amount (regnskab.php:515, :536); sum rows have no amounts and are skipped.
      let primoCells = 0;
      let harPrimo = false;
      for (let i = 0; i < accounts.length && !harPrimo; i++) {
        const tr = accounts[i].closest ? accounts[i].closest('tr') : null;
        const tds = tr && tr.querySelectorAll ? tr.querySelectorAll('td') : [];
        for (let j = 1; j < tds.length; j++) {
          if (tds[j].querySelector && tds[j].querySelector('a[href^="kontospec.php"]')) {
            primoCells++;
            harPrimo = /[1-9]/.test(a1text(tds[j - 1]));
            break;
          }
        }
      }
      let graf = false;
      try { graf = !!ctx.url.searchParams.get('graph_display'); } catch (e) { graf = false; }
      const flags = { harBevaegelser: harBevaegelser, ingenPrimo: primoCells > 0 && !harPrimo };
      const state = accounts.length === 0 ? 'tom' : (graf ? 'graf' : 'oversigt');
      return { state: state, flags: flags, data: { konti: accounts.length } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'tom') return ['help.rg.about.tom'];
      if (state !== 'oversigt' && state !== 'graf') return [];
      const out = ['help.rg.about.oversigt'];
      if (state === 'graf') out.push('help.rg.about.graf');
      else if (!f.harBevaegelser) out.push('help.rg.about.ingen_bevaegelser');
      else out.push('help.rg.about.sumkonti');
      return out;
    },

    tasks: RG_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state === 'tom') ids.push('RG8', 'RG5');
      else if (state === 'oversigt' || state === 'graf') {
        if (!f.harBevaegelser) {
          // Nothing posted yet: how amounts get here (RG9), the wrong-year case (RG5), the opening balance when primo is 0 too.
          ids.push('RG9', 'RG5');
          if (f.ingenPrimo) ids.push('RG7');
          ids.push('RG3', 'RG6', 'RG4');
        }
        else {
          if (f.ingenPrimo) ids.push('RG7');
          if (state === 'graf') ids.push('RG2');
          ids.push('RG1', 'RG2', 'RG6', 'RG4', 'RG3', 'RG5');
        }
      }
      return unique(ids);
    },

    fields: {},                           // the page has no input fields (regnskab.php:443-586)

    shortcuts: [],                        // no accesskey or key handler on the page (the old head with accesskeys is commented out, :97-132)

    questions: {
      Q1: {
        q: 'help.rg.q.q1.q',
        words: {
          da: ['stemmer ikke', 'passer ikke', 'forkert saldo', 'saldo', 'afstem', 'afstemning', 'bank', 'mangler'],
          en: ['does not match', "doesn't match", 'wrong balance', 'balance', 'reconcile', 'reconciliation', 'bank', 'missing'],
          no: ['stemmer ikke', 'passer ikke', 'feil saldo', 'saldo', 'avstem', 'avstemming', 'bank', 'mangler']
        },
        steps: ['help.rg.q.q1.step1', 'help.rg.q.q1.step2', 'help.rg.q.q1.step3'],
        show: RG_TASKS.RG1.show
      },
      Q2: {
        q: 'help.rg.q.q2.q',
        words: {
          da: ['ret', 'rette', 'fejl', 'forkert', 'tilbagefør', 'tilbageføre', 'fejlpostering'],
          en: ['correct', 'mistake', 'error', 'wrong', 'reverse'],
          no: ['rett', 'rette', 'feil', 'tilbakefør', 'tilbakeføre', 'feilføring']
        },
        steps: ['help.rg.q.q2.step1', 'help.rg.q.q2.step2', 'help.rg.q.q2.step3', 'help.rg.q.q2.step4'],
        show: RG_TASKS.RG1.show
      },
      Q3: {
        q: 'help.rg.q.q3.q',
        words: {
          da: ['primo', 'i alt', 'ultimo', 'kolonne', 'kolonner', 'minus', 'fortegn', 'negativ', 'betyder'],
          en: ['first', 'opening balance', 'total', 'column', 'columns', 'minus', 'sign', 'negative', 'mean'],
          no: ['først', 'inngående saldo', 'i alt', 'kolonne', 'kolonner', 'minus', 'fortegn', 'negativ', 'betyr']
        },
        steps: ['help.rg.q.q3.step1', 'help.rg.q.q3.step2', 'help.rg.q.q3.step3'],
        // The Primo heading is the first heading cell with a title (regnskab.php:454; the month headings after it have one too, finansfunk.php:79).
        show: { target: '#regnskab-wrapper thead td[title]', label: 'help.rg.q.q3.show', kind: 'status' }
      }
    },

    // Nothing posted yet (or no accounts): what the columns mean comes first; with movements, the saldo that does not match.
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      return state === 'tom' || ((state === 'oversigt' || state === 'graf') && !f.harBevaegelser) ? ['Q3', 'Q1', 'Q2'] : ['Q1', 'Q2', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['finans/regnskab.php'] = regnskab;

  // ---- Budget (finans/budget.php)
  // Evidence: regnskab.php:146-150 link; budget.php:55 modulnr 4, :389 amount[x][z], :391 id[x][z], :326-334 fill row, :323 Enter saves, :443 Gem, :445 CSV link.

  const BU_TASKS = {
    BU1: task('bu', 'BU1', 3, { target: 'input[name="udfyld"]', label: showKey('bu', 'BU1'), kind: 'button' }),
    BU2: task('bu', 'BU2', 3, { target: 'input[name^="amount["]', label: fieldLabel('bu', 'amount'), kind: 'field' }),
    BU3: task('bu', 'BU3', 2, { target: 'input[name="gem"]', label: showKey('bu', 'BU3'), kind: 'button' }),
    BU4: task('bu', 'BU4', 3),            // Finans > Rapporter > Resultat/budget, and the graph in Regnskab
    BU5: task('bu', 'BU5', 2, { target: 'a[href*="/budget_"]', label: showKey('bu', 'BU5'), kind: 'button' }),
    BU6: task('bu', 'BU6', 2)             // Finans > Kontoplan in the sidebar: the budget has one line per open account
  };

  const BU_FIELDS = {};
  ['amount', 'plusminus', 'procent'].forEach(function (k) { BU_FIELDS[k] = fieldKeys('bu', k); });

  const budget = {
    group: 'bu',
    states: ['tom', 'budget'],
    flags: ['harBudget', 'ikkeGemt'],

    name: function () {
      return 'help.bu.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const amounts = a1qa(doc, 'input[name^="amount["]');
      const idInputs = a1qa(doc, 'input[name^="id["]');
      const ids = {};
      let harBudget = false;
      for (let i = 0; i < idInputs.length; i++) {
        const v = String(idInputs[i].value || '').trim();
        ids[attr(idInputs[i], 'name')] = v;
        if (v !== '' && v !== '0') harBudget = true;
      }
      // Not saved: a typed value (value differs from the printed one), or a printed non-zero value without a budget row
      // (what "Udfyld med sidste års tal" prints, budget.php:181-232, :389-391).
      let ikkeGemt = false;
      for (let i = 0; i < amounts.length && !ikkeGemt; i++) {
        const el = amounts[i];
        const printed = attr(el, 'value').trim();
        if (String(el.value || '').trim() !== printed) {
          ikkeGemt = true;
          break;
        }
        const idv = ids[attr(el, 'name').replace(/^amount/, 'id')];
        if (printed !== '' && !/^-?0*([.,]0*)?$/.test(printed) && (idv === '0' || idv === '')) ikkeGemt = true;
      }
      return {
        state: amounts.length === 0 ? 'tom' : 'budget',
        flags: { harBudget: harBudget, ikkeGemt: ikkeGemt },
        data: { felter: amounts.length }
      };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'tom') return ['help.bu.about.tom'];
      if (state !== 'budget') return [];
      return ['help.bu.about.budget', f.ikkeGemt ? 'help.bu.about.ikke_gemt' : 'help.bu.about.fortegn'];
    },

    tasks: BU_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state === 'tom') ids.push('BU6');
      if (state === 'budget') {
        if (f.ikkeGemt) ids.push('BU3');
        if (f.harBudget) ids.push('BU2', 'BU4', 'BU1');
        else ids.push('BU1', 'BU2', 'BU4');
        ids.push('BU5');
      }
      return unique(ids);
    },

    fields: BU_FIELDS,

    /** amount[3][5] is one key, 'amount' (the default rule only strips trailing digits, saldi-help.js fieldKey). */
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (/^amount\[/.test(n)) return 'amount';
      if (n) return n.replace(/_?\d+$/, '');
      return attr(el, 'id') || null;
    },

    shortcuts: [
      { keys: 'Enter', text: 'help.bu.key.01', group: 'keys' },
      { keys: 'Alt+G', text: 'help.bu.key.02', group: 'keys' },
      { keys: '*', text: 'help.bu.key.03', group: 'codes' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.bu.q.q1.q',
        words: {
          da: ['minus', 'fortegn', 'negativ', 'indtægt', 'indtægter', 'omsætning', 'salg', 'plus'],
          en: ['minus', 'sign', 'negative', 'income', 'revenue', 'sales', 'plus'],
          no: ['minus', 'fortegn', 'negativ', 'inntekt', 'inntekter', 'omsetning', 'salg', 'pluss']
        },
        intro: 'help.bu.q.q1.intro',
        steps: ['help.bu.q.q1.step1', 'help.bu.q.q1.step2', 'help.bu.q.q1.step3'],
        show: BU_TASKS.BU2.show
      },
      Q2: {
        q: 'help.bu.q.q2.q',
        words: {
          da: ['samme beløb', 'alle måneder', 'hver måned', 'fordel', 'fordele', 'stjerne'],
          en: ['same amount', 'every month', 'all months', 'spread', 'asterisk'],
          no: ['samme beløp', 'alle måneder', 'hver måned', 'fordel', 'fordele', 'stjerne']
        },
        steps: stepKeys('bu', 'BU2', 2, 3),
        show: BU_TASKS.BU2.show
      }
    },

    questionOrder: function () {
      return ['Q1', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['finans/budget.php'] = budget;

  // ---- Kontoplan (systemdata/kontoplan.php)
  // Evidence: main.php:231; kontoplan.php:354-357 grid, :178 kontokort links, :190-192 Lukket, :414-417 menu hidden, :449-474 print, :60/:92-93/:105 Ny; grid.php:1396 search.

  /** The first search field with text (grid.php:1396). */
  function kpSearchField(ctx) {
    const inputs = a1qa(ctx.doc, 'input[name^="search[kontoplan]"]');
    for (let i = 0; i < inputs.length; i++) {
      if (String(inputs[i].value || '').trim() !== '') return inputs[i];
    }
    return null;
  }

  const KP_TASKS = {
    KP1: task('kp', 'KP1', 3, { target: 'input[name="search[kontoplan][beskrivelse]"]', label: fieldLabel('kp', 'search:beskrivelse'), kind: 'field' }),
    KP2: task('kp', 'KP2', 4, { target: 'a[accesskey="N"][href^="kontokort.php"]', label: showKey('kp', 'KP2'), kind: 'button' }),
    KP3: task('kp', 'KP3', 2, { target: '#datatable-kontoplan a[href^="kontokort.php?id="]', label: showKey('kp', 'KP3'), kind: 'button' }),
    KP4: task('kp', 'KP4', 3),
    KP5: task('kp', 'KP5', 2, { target: kpSearchField, label: showKey('kp', 'KP5'), kind: 'field' }),
    KP6: task('kp', 'KP6', 2, { target: '#datatable-kontoplan button.print-button', label: showKey('kp', 'KP6'), kind: 'button' }),
    KP7: task('kp', 'KP7', 4),            // Indstillinger > import og eksport > Importér kontoplan
    KP8: task('kp', 'KP8', 2)             // Finans > Regnskab month amount, or Rapporter > Kontokort (forside.php:312, :540-556)
  };
  KP_TASKS.KP4.show = KP_TASKS.KP2.show;  // a heading or sum account also starts with Ny

  const KP_FIELDS = {};
  ['search:kontonr', 'search:beskrivelse', 'search:kontotype', 'search:moms', 'search:saldo', 'search:genvej', 'rowcount']
    .forEach(function (k) { KP_FIELDS[k] = fieldKeys('kp', k); });

  const kontoplan = {
    group: 'kp',
    states: ['tom', 'liste'],
    flags: ['soegning', 'lukkede'],

    name: function () {
      return 'help.kp.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const links = a1qa(doc, '#datatable-kontoplan tbody a[href^="kontokort.php?id="]');
      const flags = {
        soegning: a1searching(doc, 'kontoplan'),
        // valueGetter prints "Lukket ! - <name>" for a closed account, in every language (kontoplan.php:190-192).
        lukkede: a1cellsContain(doc, '#datatable-kontoplan tbody td', 'Lukket ! - ')
      };
      return { state: links.length === 0 ? 'tom' : 'liste', flags: flags, data: { konti: links.length, total: a1gridTotal(doc, 'kontoplan') } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'tom') return [f.soegning ? 'help.kp.about.soegning_tom' : 'help.kp.about.tom'];
      if (state !== 'liste') return [];
      const out = ['help.kp.about.liste'];
      if (f.soegning) out.push('help.kp.about.soegning');
      else if (f.lukkede) out.push('help.kp.about.lukkede');
      return out;
    },

    tasks: KP_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (f.soegning) ids.push('KP5');
      if (state === 'tom') ids.push(f.soegning ? 'KP2' : 'KP7', 'KP2');
      else if (state === 'liste') ids.push('KP1', 'KP2', 'KP3', 'KP8', 'KP4', 'KP6', 'KP7');
      return unique(ids);
    },

    fields: KP_FIELDS,

    shortcuts: [
      { keys: 'Alt+N', text: 'help.kp.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.kp.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.kp.q.q1.q',
        words: {
          da: ['kontotype', 'kontotyper', 'type', 'drift', 'status', 'sum', 'sumkonto', 'overskrift', 'resultat', 'resultatkonto'],
          en: ['account type', 'account types', 'type', 'operation', 'status', 'sum', 'total account', 'heading', 'result'],
          no: ['kontotype', 'kontotyper', 'type', 'drift', 'status', 'sum', 'sumkonto', 'overskrift', 'resultat']
        },
        intro: 'help.kp.q.q1.intro',
        steps: ['help.kp.q.q1.step1', 'help.kp.q.q1.step2', 'help.kp.q.q1.step3', 'help.kp.q.q1.step4'],
        show: { target: 'input[name="search[kontoplan][kontotype]"]', label: fieldLabel('kp', 'search:kontotype'), kind: 'field' }
      },
      Q2: {
        q: 'help.kp.q.q2.q',
        words: {
          da: ['opret', 'oprette', 'ny konto', 'tilføj', 'konto'],
          en: ['create', 'new account', 'add', 'account'],
          no: ['opprett', 'opprette', 'ny konto', 'legg til', 'konto']
        },
        steps: stepKeys('kp', 'KP2', 1, 4),
        show: KP_TASKS.KP2.show
      },
      Q3: {
        q: 'help.kp.q.q3.q',
        words: {
          da: ['luk', 'lukke', 'lukket', 'slet', 'slette', 'fjern', 'skjul'],
          en: ['close', 'closed', 'delete', 'remove', 'hide'],
          no: ['lukk', 'lukke', 'lukket', 'slett', 'slette', 'fjern', 'skjul']
        },
        steps: ['help.kp.q.q3.step1', 'help.kp.q.q3.step2', 'help.kp.q.q3.step3'],
        show: KP_TASKS.KP3.show
      }
    },

    // Creating an account is what the page is opened for most; the types next, closing last.
    questionOrder: function () {
      return ['Q2', 'Q1', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['systemdata/kontoplan.php'] = kontoplan;

  // ---- Kontokort (systemdata/kontokort.php)
  // Evidence: kontoplan.php:178/:92-93, saft.php:853, regnskabbasis.php:436; kontokort.php:452 form (inside a tbody: use form.elements), :469-471, :476-493, :514, :544-585, :616-627.

  /** A named control of the card's form (form.elements, then the document), or null. */
  function ktControl(ctx, name) {
    const form = a1q(ctx.doc, 'form[name="kontokort"]');
    let el = null;
    try {
      el = form && form.elements && form.elements.namedItem ? form.elements.namedItem(name) : null;
      if (el && el.nodeType !== 1) el = el[0];
    } catch (e) {
      el = null;
    }
    if (!(el && el.nodeType === 1)) el = byName(ctx.doc, name);
    return el && el.nodeType === 1 ? el : null;
  }

  const KT_TASKS = {
    KT1: task('kt', 'KT1', 4, { target: 'select[name="moms"]', label: fieldLabel('kt', 'moms'), kind: 'field' }),
    KT2: task('kt', 'KT2', 3, { target: 'input[name="lukket"]', label: fieldLabel('kt', 'lukket'), kind: 'field' }),
    KT3: task('kt', 'KT3', 2, { target: 'input[name="slet"]', label: showKey('kt', 'KT3'), kind: 'button' }),
    KT4: task('kt', 'KT4', 3, { target: 'select[name="kontotype"]', label: fieldLabel('kt', 'kontotype'), kind: 'field' }),
    KT5: task('kt', 'KT5', 4, { target: 'input[name="kontonr"]:not([type="hidden"])', label: fieldLabel('kt', 'kontonr'), kind: 'field' }),
    KT6: task('kt', 'KT6', 2, { target: 'select[name="map_to"]', label: fieldLabel('kt', 'map_to'), kind: 'field' }),
    KT7: task('kt', 'KT7', 1, { target: 'input[name="lukket"]', label: fieldLabel('kt', 'lukket'), kind: 'field' }),
    KT8: task('kt', 'KT8', 2, { target: 'input[name="fra_kto"]', label: fieldLabel('kt', 'fra_kto'), kind: 'field' }),
    KT9: task('kt', 'KT9', 2, { target: 'select[name="genvej"]', label: fieldLabel('kt', 'genvej'), kind: 'field' })
  };

  const KT_FIELDS = {};
  ['kontonr', 'beskrivelse', 'kontotype', 'moms', 'map_to', 'fra_kto', 'genvej', 'ny_valuta', 'lukket']
    .forEach(function (k) { KT_FIELDS[k] = fieldKeys('kt', k); });

  const kontokort = {
    group: 'kt',
    states: ['ny', 'kort', 'ukendt'],
    flags: ['driftStatus', 'sum', 'harSaldo', 'kanSlettes', 'lukket', 'fraRapport'],

    name: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      if (state === 'ny') return 'help.kt.name_new';
      if (state === 'kort') return ['help.kt.name', String((st.data && st.data.kontonr) || '')];
      return 'help.kt.name_plain';
    },

    detect: function (ctx) {
      if (!a1q(ctx.doc, 'form[name="kontokort"]')) return { state: 'ukendt', flags: {}, data: {} };
      const val = function (name) {
        const el = ktControl(ctx, name);
        return el ? String(el.value || '').trim() : '';
      };
      const id = val('id');
      const kontonrEl = ktControl(ctx, 'kontonr');
      const type = a1firstOption(ktControl(ctx, 'kontotype'));
      const lukketEl = ktControl(ctx, 'lukket');
      let rapportart = '';
      try { rapportart = String(ctx.url.searchParams.get('rapportart') || ''); } catch (e) { rapportart = ''; }
      const flags = {
        driftStatus: type === 'D' || type === 'S',
        sum: type === 'Z',
        harSaldo: !!kontonrEl && attr(kontonrEl, 'type').toLowerCase() === 'hidden',
        kanSlettes: !!ktControl(ctx, 'slet'),
        lukket: !!lukketEl && (lukketEl.defaultChecked === true || lukketEl.getAttribute('checked') !== null),
        fraRapport: (rapportart || val('rapportart')) !== ''
      };
      return { state: id === '' || id === '0' ? 'ny' : 'kort', flags: flags, data: { kontonr: val('kontonr'), type: type } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'ny') return ['help.kt.about.ny'];
      if (state !== 'kort') return [];
      const out = ['help.kt.about.kort'];
      if (f.fraRapport) out.push('help.kt.about.fra_rapport');
      else if (f.lukket) out.push('help.kt.about.lukket');
      else if (f.harSaldo) out.push('help.kt.about.har_saldo');
      return out;
    },

    tasks: KT_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state === 'ny') ids.push('KT5', 'KT4');
      else if (state === 'kort') {
        // Map til comes first only when the user came from SAF-T or Regnskab Basis to map the account; else it is a bookkeeper's job further down.
        if (f.fraRapport && f.driftStatus) ids.push('KT6');
        if (f.lukket) ids.push('KT7');
        if (f.sum) ids.push('KT8');
        if (f.driftStatus) ids.push('KT1');
        if (!f.lukket) ids.push('KT2');
        // Slet is also printed when the saldo is only primo (kontokort.php:357-360, :626); deleting would drop that balance.
        if (f.kanSlettes && !f.harSaldo) ids.push('KT3');
        if (f.driftStatus) ids.push('KT6', 'KT9');
      }
      return unique(ids);
    },

    fields: KT_FIELDS,

    shortcuts: [
      { keys: 'Enter', text: 'help.kt.key.01', group: 'keys' },
      { keys: 'Alt+G', text: 'help.kt.key.02', group: 'keys' },
      { keys: 'Alt+N', text: 'help.kt.key.03', group: 'keys' },
      { keys: 'Alt+S', text: 'help.kt.key.04', group: 'keys' },
      { keys: 'Alt+R', text: 'help.kt.key.05', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.kt.q.q1.q',
        words: {
          da: ['kontonummer', 'nummer', 'ændre nummer', 'skifte nummer', 'omnummerere', 'låst'],
          en: ['account number', 'number', 'change number', 'renumber', 'locked'],
          no: ['kontonummer', 'nummer', 'endre nummer', 'bytte nummer', 'låst']
        },
        intro: 'help.kt.q.q1.intro',
        steps: ['help.kt.q.q1.step1', 'help.kt.q.q1.step2', 'help.kt.q.q1.step3'],
        // [1] creates the new account: Ny in the card's head (kontokort.php:389, 414, 435); Lukket is [3] (Vis mig audit, spec §21)
        show: { target: 'a[accesskey="N"][href^="kontokort.php"]', label: 'help.kt.q.q1.show', kind: 'button' }
      },
      Q2: {
        q: 'help.kt.q.q2.q',
        words: {
          da: ['moms', 'momssats', 'momskode', 'salgsmoms', 'købsmoms', 'uden moms'],
          en: ['vat', 'vat rate', 'vat code', 'sales vat', 'purchase vat', 'no vat'],
          no: ['mva', 'mva-sats', 'mva-kode', 'utgående mva', 'inngående mva', 'uten mva']
        },
        intro: 'help.kt.q.q2.intro',
        steps: ['help.kt.q.q2.step1', 'help.kt.q.q2.step2', 'help.kt.q.q2.step3', 'help.kt.q.q2.step4'],
        show: KT_TASKS.KT1.show
      },
      Q3: {
        q: 'help.kt.q.q3.q',
        words: {
          da: ['slet', 'slette', 'fjern', 'fjerne', 'luk', 'lukke'],
          en: ['delete', 'remove', 'close'],
          no: ['slett', 'slette', 'fjern', 'fjerne', 'lukk', 'lukke']
        },
        steps: ['help.kt.q.q3.step1', 'help.kt.q.q3.step2'],
        show: KT_TASKS.KT3.show
      },
      // A new account's type (kontokort.php:497-542: Overskrift, Drift, Status, Resultat, Sum, Sideskift; with a saldo only
      // Drift, Status and Resultat, :515-521). Momssats only for Drift and Status (:544-545); primo carried for Status.
      Q4: {
        q: 'help.kt.q.q4.q',
        words: {
          da: ['kontotype', 'hvilken type', 'type', 'drift', 'status', 'overskrift', 'sum', 'driftskonto', 'statuskonto'],
          en: ['account type', 'which type', 'type', 'operation', 'status', 'headline', 'heading', 'total'],
          no: ['kontotype', 'hvilken type', 'type', 'drift', 'status', 'overskrift', 'sum', 'driftskonto', 'statuskonto']
        },
        intro: 'help.kt.q.q4.intro',
        steps: ['help.kt.q.q4.step1', 'help.kt.q.q4.step2', 'help.kt.q.q4.step3', 'help.kt.q.q4.step4'],
        show: KT_TASKS.KT4.show
      },
      // A new account's number: a positive whole number not used in the year (kontokort.php:225-231), counted by a total
      // account from its Konto (fra) to its own number (:145-146, til_kto = kontonr), fixed once there is a saldo (:469-471).
      Q5: {
        q: 'help.kt.q.q5.q',
        words: {
          da: ['hvilket nummer', 'ledigt nummer', 'nyt nummer', 'kontonummer', 'nummerserie', 'placering', 'sumkonto'],
          en: ['which number', 'free number', 'new number', 'account number', 'placement', 'total account'],
          no: ['hvilket nummer', 'ledig nummer', 'nytt nummer', 'kontonummer', 'plassering', 'sumkonto']
        },
        intro: 'help.kt.task.kt5.why',
        steps: ['help.kt.q.q5.step1', 'help.kt.q.q5.step2', 'help.kt.q.q5.step3', 'help.kt.q.q5.step4'],
        show: KT_TASKS.KT5.show
      }
    },

    // A new account: its number, its type and its VAT rate. A saved one: the number only leads when it is locked by a saldo.
    questionOrder: function (ctx, st) {
      const f = (st && st.flags) || {};
      if (st && st.state === 'ny') return ['Q5', 'Q4', 'Q2'];
      return f.harSaldo ? ['Q1', 'Q2', 'Q3'] : ['Q2', 'Q3', 'Q4'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['systemdata/kontokort.php'] = kontokort;

  // ---- Kontospecifikation (finans/kontospec.php)
  // Evidence: regnskab.php:536, kassekladde.php:2313 (same frame); kontospec.php:64-68/:187-214 mode, :325 dummy row, :341/:412/:471/:486 links, :554 grid; grid.php:350-429 stored search/page.

  /** The page button "1" under the grid (grid.php:1519, :1526-1528). */
  function kspPageOne(ctx) {
    const btns = a1qa(ctx.doc, '#datatable-wrapper-kontospec #navbuttons button.navbutton');
    for (let i = 0; i < btns.length; i++) {
      if (a1text(btns[i]).trim() === '1') return btns[i];
    }
    return null;
  }

  const KSP_TASKS = {
    KSP1: task('ksp', 'KSP1', 3, { target: '#datatable-kontospec a[href^="kontospec.php?bilag="]', label: fieldLabel('ksp', 'search:bilag'), kind: 'button' }),
    KSP2: task('ksp', 'KSP2', 4, { target: '#datatable-kontospec a[href^="kassekladde.php?kladde_id="]', label: fieldLabel('ksp', 'search:kladde_id'), kind: 'button' }),
    KSP3: task('ksp', 'KSP3', 1, { target: '#datatable-kontospec a[href*="ordre.php?id="]', label: fieldLabel('ksp', 'search:faktura'), kind: 'button' }),
    KSP4: task('ksp', 'KSP4', 3, { target: 'input[name="search[kontospec][beskrivelse]"]', label: fieldLabel('ksp', 'search:beskrivelse'), kind: 'field' }),
    KSP5: task('ksp', 'KSP5', 2, { target: '#datatable-wrapper-kontospec .dropdown-trigger', label: showKey('ksp', 'KSP5'), kind: 'button' }),
    KSP6: task('ksp', 'KSP6', 2, { target: kspPageOne, label: showKey('ksp', 'KSP6'), kind: 'button' }),
    KSP7: task('ksp', 'KSP7', 2),
    KSP8: task('ksp', 'KSP8', 1, { target: '#datatable-kontospec a[href^="kontospec.php?kontonr="]', label: fieldLabel('ksp', 'search:kontonr'), kind: 'button' }),
    KSP9: task('ksp', 'KSP9', 3),         // Finans > Kassekladde in the sidebar
    KSP10: task('ksp', 'KSP10', 2)        // Rapporter > Kontokort (forside.php:312, :349, :540-555, :576)
  };
  KSP_TASKS.KSP7.show = KSP_TASKS.KSP5.show;  // Eksportér til CSV is in the same grid menu

  const KSP_FIELDS = {};
  ['search:bilag', 'search:transdate', 'search:beskrivelse', 'search:kontonr', 'search:debet', 'search:kredit', 'search:faktura',
    'search:kladde_id', 'search:afd', 'search:projekt', 'rowcount']
    .forEach(function (k) { KSP_FIELDS[k] = fieldKeys('ksp', k); });

  const kontospec = {
    group: 'ksp',
    states: ['konto', 'bilag', 'alle'],
    flags: ['tom', 'sideTom', 'soegning', 'lager', 'harKladde', 'harFaktura', 'harBilag', 'maaned'],

    name: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const d = (st && st.data) || {};
      if (state === 'konto') return ['help.ksp.name_konto', String(d.kontonr || '')];
      if (state === 'bilag') return ['help.ksp.name_bilag', String(d.bilag || '')];
      return 'help.ksp.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let kontonr = '';
      let bilag = '';
      let month = '';
      try {
        kontonr = String(ctx.url.searchParams.get('kontonr') || '').trim();
        bilag = String(ctx.url.searchParams.get('bilag') || '').trim();
        month = String(ctx.url.searchParams.get('month') || '').trim();
      } catch (e) { /* no URL parameters */ }
      const state = kontonr ? 'konto' : (bilag ? 'bilag' : 'alle');      // kontonr wins, as in kontospec.php:187-214
      const rows = a1qa(doc, '#datatable-kontospec tbody tr:not(.filler-row)');
      const dummy = rows.length === 1 && a1cellsContain(doc, '#datatable-kontospec tbody td', 'Ingen transaktioner fundet');
      const total = a1gridTotal(doc, 'kontospec');
      const flags = {
        tom: rows.length === 0 || dummy,
        sideTom: rows.length === 0 && total > 0,           // the stored offset is past the end (grid.php:403-415)
        soegning: a1searching(doc, 'kontospec'),
        lager: a1cellsContain(doc, '#datatable-kontospec tbody td', 'lagertransaktion'),
        harKladde: !!a1q(doc, '#datatable-kontospec a[href^="kassekladde.php?kladde_id="]'),
        harFaktura: !!a1q(doc, '#datatable-kontospec a[href*="ordre.php?id="]'),
        harBilag: !!a1q(doc, '#datatable-kontospec a[href^="kontospec.php?bilag="]'),
        maaned: state === 'konto' && month !== ''
      };
      return { state: state, flags: flags, data: { kontonr: kontonr, bilag: bilag, rows: rows.length, total: total } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const out = [];
      if (f.tom && !f.sideTom) out.push('help.ksp.about.tom');
      else if (state === 'konto') out.push(f.maaned ? 'help.ksp.about.konto_maaned' : 'help.ksp.about.konto_aar');
      else if (state === 'bilag') out.push('help.ksp.about.bilag');
      else if (state === 'alle') out.push('help.ksp.about.alle');
      if (f.sideTom) out.push('help.ksp.about.side_tom');
      else if (f.soegning) out.push('help.ksp.about.soegning');
      else if (f.lager && !f.tom) out.push('help.ksp.about.lager');
      return out;
    },

    tasks: KSP_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state !== 'konto' && state !== 'bilag' && state !== 'alle') return ids;
      if (f.sideTom) ids.push('KSP6');
      if (f.soegning) ids.push('KSP5');
      if (f.tom) ids.push('KSP9');
      if (state === 'bilag') {
        ids.push('KSP8');
        if (f.harKladde) ids.push('KSP2');
        if (f.harFaktura) ids.push('KSP3');
      } else {
        if (f.harBilag) ids.push('KSP1');
        if (f.harKladde) ids.push('KSP2');
        if (!f.tom) ids.push('KSP4');
        if (f.harFaktura) ids.push('KSP3');
      }
      if (!f.tom) ids.push('KSP4');
      if (state === 'konto' && !f.tom) ids.push('KSP10');
      if (!f.tom) ids.push('KSP7');
      ids.push('KSP9');
      return unique(ids);
    },

    fields: KSP_FIELDS,

    shortcuts: [
      { keys: 'Enter', text: 'help.ksp.key.01', group: 'keys' }
    ],

    questions: {
      Q1: {
        q: 'help.ksp.q.q1.q',
        words: {
          da: ['mangler', 'kan ikke se', 'væk', 'forsvundet', 'ikke med', 'tom', 'ingen posteringer'],
          en: ['missing', 'cannot see', "can't see", 'gone', 'not included', 'empty', 'no entries'],
          no: ['mangler', 'kan ikke se', 'borte', 'forsvunnet', 'ikke med', 'tom', 'ingen posteringer']
        },
        steps: ['help.ksp.q.q1.step1', 'help.ksp.q.q1.step2', 'help.ksp.q.q1.step3'],
        show: KSP_TASKS.KSP5.show
      },
      Q2: {
        q: 'help.ksp.q.q2.q',
        words: {
          da: ['modkonto', 'modkontoen', 'anden konto', 'hvor er den bogført', 'bilag', 'hele bilaget'],
          en: ['contra account', 'other account', 'where was it posted', 'voucher', 'whole voucher'],
          no: ['motkonto', 'motkontoen', 'annen konto', 'hvor er den bokført', 'bilag', 'hele bilaget']
        },
        steps: stepKeys('ksp', 'KSP1', 1, 3),
        show: KSP_TASKS.KSP1.show
      },
      Q3: {
        q: 'help.ksp.q.q3.q',
        words: {
          da: ['ret', 'rette', 'fejl', 'forkert', 'tilbagefør', 'tilbageføre'],
          en: ['correct', 'mistake', 'error', 'wrong', 'reverse'],
          no: ['rett', 'rette', 'feil', 'tilbakefør', 'tilbakeføre']
        },
        steps: stepKeys('ksp', 'KSP2', 1, 4),
        show: KSP_TASKS.KSP2.show
      }
    },

    questionOrder: function (ctx, st) {
      const f = (st && st.flags) || {};
      return f.tom ? ['Q1', 'Q2', 'Q3'] : ['Q2', 'Q3', 'Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['finans/kontospec.php'] = kontospec;

  // ---- Finansrapporter (finans/rapport.php)
  // Sidebar Finans » Rapporter (index/main.php:226-227) loads the route into iframe#iframe_a. One route, many reports:
  // a GET always renders the selection form, because $submit is read from POST only (rapport.php:93,278); a POST with
  // submit=OK includes rapport_includes/<report>.php (rapport.php:280-301,361). Every report prints a back link
  // rapport.php?rapportart=<art>&... (regnskab.php:308, kontokort.php:330, kontokort_moms.php:184, momsangivelse.php:177,
  // moms_afstemning.php:114, moms_rubrik.php:109, moms_oss.php:111, moms_transaktioner.php:113, emballage.php:61),
  // which is how detect() tells the reports apart.

  const RP_STATES = ['forside', 'ingenAar', 'regnskab', 'kontokort', 'kontokortUbogfort', 'kontokortMoms', 'momsangivelse',
    'momsAfstemning', 'momsRubrik', 'momsOss', 'momsTransaktioner', 'emballage', 'unknown'];
  const RP_FLAGS = ['ingenKonti', 'fraRapport', 'heleAaret', 'simulering', 'dimensioner', 'sidsteAar', 'emballageValg',
    'simuleret', 'filter', 'tom', 'ubogfortFejl', 'afvigelser', 'ingenAfvigelser', 'rubrikMangler', 'ossMangler',
    'typeMangler', 'zoneMangler', 'udenPeriode'];

  /** rapportart in a report's back link -> state. balance/resultat/budget/lastYear/regnskab all run regnskab() (rapport.php:281-300). */
  const RP_ART_STATE = {
    balance: 'regnskab', resultat: 'regnskab', regnskab: 'regnskab', budget: 'regnskab', lastYear: 'regnskab',
    kontokort: 'kontokort', kontokort_ubogfort: 'kontokortUbogfort', kontokort_moms: 'kontokortMoms',
    momsangivelse: 'momsangivelse', moms_afstemning: 'momsAfstemning', moms_rubrik: 'momsRubrik', moms_oss: 'momsOss',
    moms_transaktioner: 'momsTransaktioner', emballage: 'emballage'
  };
  const RP_STATE_NAME = {
    forside: 'help.rp.name', ingenAar: 'help.rp.name', unknown: 'help.rp.name',
    kontokort: 'help.rp.name_kontokort', kontokortUbogfort: 'help.rp.name_ubogfort', kontokortMoms: 'help.rp.name_kontokort_moms',
    momsangivelse: 'help.rp.name_momsangivelse', momsAfstemning: 'help.rp.name_moms_afstemning', momsRubrik: 'help.rp.name_moms_rubrik',
    momsOss: 'help.rp.name_moms_oss', momsTransaktioner: 'help.rp.name_moms_transaktioner', emballage: 'help.rp.name_emballage'
  };
  const RP_ART_NAME = {
    resultat: 'help.rp.name_resultat', balance: 'help.rp.name_balance', regnskab: 'help.rp.name_regnskab',
    budget: 'help.rp.name_budget', lastYear: 'help.rp.name_lastyear'
  };
  const RP_ART_ABOUT = {
    resultat: 'help.rp.about.resultat', balance: 'help.rp.about.balance', regnskab: 'help.rp.about.regnskab',
    budget: 'help.rp.about.budget', lastYear: 'help.rp.about.lastyear'
  };

  function rpHas(o, k) {
    return Object.prototype.hasOwnProperty.call(o, k);
  }

  function rpQ(doc, sel) {
    try {
      const el = doc.querySelector(sel);
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function rpAll(doc, sel) {
    try {
      return Array.prototype.slice.call(doc.querySelectorAll(sel)).filter(function (el) { return el && el.nodeType === 1; });
    } catch (e) {
      return [];
    }
  }

  /** The report family of a Rapporttype value (forside.php:310-381; Listeangivelse options carry no value, rapport.php:434-440). */
  function rpGroup(v) {
    v = String(v || '');
    if (['balance', 'resultat', 'regnskab', 'budget', 'lastYear'].indexOf(v) !== -1) return 'regn';
    if (['kontokort', 'kontokort_moms', 'kontokort_ubogfort'].indexOf(v) !== -1) return 'kort';
    if (['momsangivelse', 'moms_afstemning', 'moms_rubrik', 'moms_oss', 'moms_transaktioner'].indexOf(v) !== -1 ||
      v.indexOf('Listeangivelse') === 0) return 'moms';
    if (v === 'saft' || v === 'regnskabbasis') return 'saft';
    if (v === 'emballage') return 'emb';
    return '';
  }

  /** Both period selects on the whole financial year: option 0 is the current choice, 1..n the year's months (forside.php:480-523). */
  function rpWholeYear(doc) {
    try {
      const mf = rpQ(doc, 'select[name="maaned_fra"]');
      const mt = rpQ(doc, 'select[name="maaned_til"]');
      if (!mf || !mt || !mf.options || !mt.options || mf.options.length < 2 || mt.options.length < 2) return false;
      return String(mf.value) === String(mf.options[1].value) &&
        String(mt.value) === String(mt.options[mt.options.length - 1].value);
    } catch (e) {
      return false;
    }
  }

  function rpBodyText(doc) {
    try {
      return doc.body ? String(doc.body.textContent || '') : '';
    } catch (e) {
      return '';
    }
  }

  /** The report's Tilbage button: <a accesskey=L href="javascript:confirmClose('rapport.php?rapportart=...')"><button> (regnskab.php:310-312). */
  function rpBack(ctx) {
    const a = rpQ(ctx.doc, 'a[accesskey="L"][href*="rapportart="]');
    if (!a) return null;
    let b = null;
    try { b = a.querySelector('button'); } catch (e) { b = null; }
    return b && b.nodeType === 1 ? b : a;
  }

  function rpRowOf(el) {
    return el && el.closest ? (el.closest('tr') || el) : el;
  }

  /** The line "Afgiftsbeløb i alt" of the VAT return (momsangivelse.php:332). */
  function rpAfgiftRow(ctx) {
    const tds = rpAll(ctx.doc, 'td');
    for (let i = 0; i < tds.length; i++) {
      if (/^\s*Afgiftsbel/.test(String(tds[i].textContent || ''))) return rpRowOf(tds[i]);
    }
    return null;
  }

  /** A show target that only counts on its own report(s). One page object serves every report, and the search offers
   *  every task in every state, so a generic selector such as table.dataTable would ring another report's table under
   *  this task's label (live: MR2 rang the Regnskab table as Nettobeloeb, KM1 the Kontokort head as Moms). Vis mig audit,
   *  spec §21. */
  function rpOnly(states, target) {
    return function (ctx, st) {
      if (!st || states.indexOf(st.state) === -1) return null;
      if (typeof target === 'function') return target(ctx, st);
      const all = rpAll(ctx.doc, target);
      return all.length ? all[0] : null;
    };
  }

  /** The block with the report's period at the top of Momsafstemning: firm name, <br> and "Periode: ..." (hard-coded
   *  Danish, moms_afstemning.php:156-159), so the text reads "FirmaPeriode: ...". The innermost div with that text, not
   *  the page's wrappers. */
  function rpPeriodLine(ctx) {
    const divs = rpAll(ctx.doc, 'div');
    for (let i = 0; i < divs.length; i++) {
      if (/Periode:\s/.test(String(divs[i].textContent || '')) && !rpQ(divs[i], 'div')) return divs[i];
    }
    return null;
  }

  /** A task whose title and why are another task's (the same job on another report state). */
  function rpReuse(fromId, steps, show) {
    const p = 'help.rp.task.' + fromId.toLowerCase() + '.';
    const t = { title: p + 'title', why: p + 'why', steps: steps };
    if (show) t.show = show;
    return t;
  }

  const RP_BACK = { target: rpBack, label: showKey('rp', 'RG1'), kind: 'button' };
  const RP_BACK_MOMS = { target: rpBack, label: showKey('rp', 'MP'), kind: 'button' };       // moms_*: "Tilbage" in every language
  const RP_TYPE = { target: 'select[name="rapportart"]', label: fieldLabel('rp', 'rapportart'), kind: 'field' };
  const RP_CSV = { target: 'a[href*=".csv"]', label: showKey('rp', 'CSV'), kind: 'button' };
  const RP_CSVM = { target: 'a[href*=".csv"]', label: showKey('rp', 'CSVM'), kind: 'button' };

  const RP_TASKS = {
    // Selection form (rapport_includes/forside.php)
    RP1: task('rp', 'RP1', 3, RP_TYPE),
    RP2: task('rp', 'RP2', 3, { target: 'select[name="konto_fra"]', label: fieldLabel('rp', 'konto_fra'), kind: 'field' }),
    RP3: task('rp', 'RP3', 4, RP_TYPE),
    RPP: task('rp', 'RPP', 2, { target: 'select[name="maaned_fra"]', label: fieldLabel('rp', 'maaned_fra'), kind: 'field' },
      ['help.rp.task.rp1.step3']),
    RP4: task('rp', 'RP4', 3, RP_TYPE),
    RP5: task('rp', 'RP5', 3, { target: 'input[name="simulering"]', label: fieldLabel('rp', 'simulering'), kind: 'field' }),
    RP6: task('rp', 'RP6', 3, RP_TYPE),
    RP7: task('rp', 'RP7', 3, RP_TYPE),
    RP8: task('rp', 'RP8', 3, {
      target: function (ctx) {
        return rpQ(ctx.doc, 'select[name="afd"]') || rpQ(ctx.doc, 'select[name="projekt_fra"]') ||
          rpQ(ctx.doc, 'input[name^="delprojekt"]') || rpQ(ctx.doc, 'select[name="ansat_fra"]');
      },
      label: fieldLabel('rp', 'afd'),
      kind: 'field'
    }),
    RP9: task('rp', 'RP9', 3, { target: 'input[name="bankReconcile"]', label: showKey('rp', 'RP9'), kind: 'button' }),
    RP10: task('rp', 'RP10', 2, { target: 'input[name="kontrolspor"]', label: showKey('rp', 'RP10'), kind: 'button' }),
    RP11: task('rp', 'RP11', 2, RP_TYPE),
    RP12: task('rp', 'RP12', 2, RP_TYPE),
    RP14: task('rp', 'RP14', 2, { target: 'select[name="regnaar"]', label: fieldLabel('rp', 'regnaar'), kind: 'field' }),
    RP15: task('rp', 'RP15', 3, RP_TYPE),
    RP16: task('rp', 'RP16', 3, RP_TYPE),
    RP17: task('rp', 'RP17', 2, RP_TYPE),
    RP18: task('rp', 'RP18', 2, RP_TYPE),
    RP19: task('rp', 'RP19', 2, RP_TYPE),
    RPK: task('rp', 'RPK', 2, { target: 'select[name="regnaar"]', label: fieldLabel('rp', 'regnaar'), kind: 'field' }),
    // No financial year (forside.php:148-165)
    NA1: task('rp', 'NA1', 2),
    // Resultat, Balance, Regnskab, Resultat/budget, Resultat/Sidste år (rapport_includes/regnskab.php)
    RG1: task('rp', 'RG1', 3, RP_BACK),
    RG2: task('rp', 'RG2', 2),
    RG4: task('rp', 'RG4', 2, RP_BACK),
    RG5: task('rp', 'RG5', 2, RP_BACK),
    RG6: task('rp', 'RG6', 2, RP_BACK),
    RG7: task('rp', 'RG7', 2, null, ['help.rp.task.rp7.step3']),
    CSV: task('rp', 'CSV', 2, RP_CSV),
    CSVM: rpReuse('CSV', ['help.rp.task.csvm.step1', 'help.rp.task.csv.step2'], RP_CSVM),
    // Kontokort, Kontokort med u-bogført (rapport_includes/kontokort.php)
    KO1: task('rp', 'KO1', 2, { target: '#datapg td[onclick*="kassekladde.php"]', label: showKey('rp', 'KO1'), kind: 'button' }),
    KO2: task('rp', 'KO2', 2, { target: 'a[onclick*="kontokortprint"]', label: showKey('rp', 'KO2'), kind: 'button' }),
    KO3: task('rp', 'KO3', 3, {
      target: function (ctx) { return rpRowOf(rpQ(ctx.doc, '#datapg td.kontokort-dato')); },
      label: showKey('rp', 'KO3'),
      kind: 'row'
    }),
    KO5: task('rp', 'KO5', 2, RP_BACK),
    KU1: task('rp', 'KU1', 3, { target: 'tr.unpostedRow', label: showKey('rp', 'KU1'), kind: 'row' }),
    KU2: task('rp', 'KU2', 2, { target: rpOnly(['kontokortUbogfort'], 'div[style*="b00000"]'), label: showKey('rp', 'KU2'), kind: 'status' }),
    // Kontokort med moms (rapport_includes/kontokort_moms.php)
    KM1: task('rp', 'KM1', 3, { target: rpOnly(['kontokortMoms'], '#datapg thead tr'), label: showKey('rp', 'KM1'), kind: 'status' }),
    // Momsangivelse (rapport_includes/momsangivelse.php)
    MA1: task('rp', 'MA1', 4, { target: rpAfgiftRow, label: showKey('rp', 'MA1'), kind: 'row' }),
    MA2: task('rp', 'MA2', 4, RP_BACK),
    MA3: task('rp', 'MA3', 4),
    MA4: task('rp', 'MA4', 0, RP_BACK, stepKeys('rp', 'RG5', 1, 2)),
    MA5: rpReuse('RP15', ['help.rp.task.ma5.step1', 'help.rp.task.rp15.step2'], RP_BACK),
    // Momsafstemning (rapport_includes/moms_afstemning.php)
    MS1: task('rp', 'MS1', 3, { target: rpOnly(['momsAfstemning'], 'tr[style*="ffe0e0"]'), label: showKey('rp', 'MS1'), kind: 'row' }),
    MS4: task('rp', 'MS4', 1, RP_BACK_MOMS),
    // [1] checks the period at the top (moms_afstemning.php:156-159); no step clicks Tilbage (Vis mig audit, spec §21)
    MS5: task('rp', 'MS5', 2, { target: rpOnly(['momsAfstemning'], rpPeriodLine), label: showKey('rp', 'MS5'), kind: 'status' }),
    MP: task('rp', 'MP', 2, RP_BACK_MOMS),
    // Momsrubrikker (A/B/C) (rapport_includes/moms_rubrik.php)
    MR1: task('rp', 'MR1', 3, { target: 'a[href*="valg=debitor"]', label: showKey('rp', 'MR1'), kind: 'button' }),
    MR2: task('rp', 'MR2', 1, { target: rpOnly(['momsRubrik'], 'table.dataTable'), label: showKey('rp', 'MR2'), kind: 'status' },
      ['help.rp.task.rp15.step2', 'help.rp.task.mr2.step3', 'help.rp.task.mr2.step4']),
    MR3: task('rp', 'MR3', 1, { target: rpOnly(['momsRubrik'], 'form[action="rapport.php"] button[type="submit"]'), label: showKey('rp', 'MR3'), kind: 'button' }),
    MR5: rpReuse('RP12', ['help.rp.task.mr5.step1', 'help.rp.task.rp12.step1', 'help.rp.task.rp12.step2'], RP_BACK_MOMS),
    // OSS B2C EU-salg (rapport_includes/moms_oss.php)
    OS1: task('rp', 'OS1', 3, { target: 'a[href*="valg=moms"]', label: showKey('rp', 'OS1'), kind: 'button' }),
    OS2: task('rp', 'OS2', 4, {
      target: rpOnly(['momsOss'], function (ctx) {
        const t = rpAll(ctx.doc, 'table.dataTable');
        return t.length > 1 ? t[1] : null;      // the filing summary is the second table (moms_oss.php:466-468); a third, non-OSS one may follow (:582-585)
      }),
      label: showKey('rp', 'OS2'),
      kind: 'status'
    }),
    OS3: task('rp', 'OS3', 1, { target: 'a[href*="valg=debitor"]', label: showKey('rp', 'OS3'), kind: 'button' },
      ['help.rp.task.os1.step3']),
    OS4: task('rp', 'OS4', 1, { target: 'a[href*="valg=moms"]', label: showKey('rp', 'OS1'), kind: 'button' },
      ['help.rp.task.os1.step3']),
    OS5: task('rp', 'OS5', 1, { target: 'button[onclick*="window.print"]', label: showKey('rp', 'OS5'), kind: 'button' }),
    // Posteringer pr. momskode (rapport_includes/moms_transaktioner.php)
    MT1: task('rp', 'MT1', 3, { target: rpOnly(['momsTransaktioner'], 'table.dataTable thead tr'), label: showKey('rp', 'MT1'), kind: 'status' }),
    MT2: task('rp', 'MT2', 1, RP_BACK_MOMS),
    // Emballage (rapport_includes/emballage.php)
    // [1] Fra dato, the first of the filter's unnamed date fields (emballage.php:96-100); Visning is [2] (Vis mig audit, spec §21)
    EM1: task('rp', 'EM1', 3, { target: rpOnly(['emballage'], 'form[action="rapport.php"] input[type="date"]'), label: showKey('rp', 'EM1'), kind: 'field' }),
    EM2: rpReuse('CSV', ['help.rp.task.csv.step1', 'help.rp.task.em2.step2'], RP_CSV),
    EM3: task('rp', 'EM3', 3)
  };

  const RP_FIELDS = {
    regnaar: fieldKeys('rp', 'regnaar'),
    simulering: fieldKeys('rp', 'simulering'),
    rapportart: fieldKeys('rp', 'rapportart'),
    afd: fieldKeys('rp', 'afd'),
    projekt_fra: fieldKeys('rp', 'projekt_fra'),
    projekt_til: fieldKeys('rp', 'projekt_fra'),
    delprojekt: fieldKeys('rp', 'delprojekt'),
    ansat_fra: fieldKeys('rp', 'ansat_fra'),
    ansat_til: fieldKeys('rp', 'ansat_fra'),
    maaned_fra: fieldKeys('rp', 'maaned_fra'),
    dato_fra: fieldKeys('rp', 'maaned_fra'),
    maaned_til: fieldKeys('rp', 'maaned_til'),
    dato_til: fieldKeys('rp', 'maaned_til'),
    konto_fra: fieldKeys('rp', 'konto_fra'),
    konto_til: fieldKeys('rp', 'konto_til'),
    land: fieldKeys('rp', 'land'),
    view: fieldKeys('rp', 'view')
  };

  /** Order on the selection form: blockers, then the jobs of the chosen report type, then the rest for search. */
  function rpForsideOrder(f, d) {
    const v = String(d.valgt || '');
    const g = rpGroup(v);
    // Nothing chosen yet: Kontokort is the list's first option when no rapportart came in (forside.php:348-349).
    const start = !f.fraRapport && v === 'kontokort';
    const ids = [];
    if (f.ingenKonti) ids.push('RPK');
    if (start || g === '') {
      ids.push('RP1', 'RP2', 'RP3', 'RP4');
    } else if (g === 'regn') {
      if (v === 'budget' || v === 'lastYear') ids.push('RP7', 'RP1');
      else ids.push('RP1', 'RP7');
      if (f.simulering) ids.push('RP5');
      if (f.dimensioner) ids.push('RP8');
      ids.push('RP2');
    } else if (g === 'kort') {
      if (v === 'kontokort_ubogfort') ids.push('RP6', 'RP2');
      else ids.push('RP2', 'RP6');
      if (f.simulering && v !== 'kontokort_ubogfort') ids.push('RP5');
      if (f.dimensioner) ids.push('RP8');
      ids.push('RP10');
    } else if (g === 'moms') {
      if (f.heleAaret && v.indexOf('Listeangivelse') !== 0) ids.push('RPP');
      if (v === 'momsangivelse') ids.push('RP3', 'RP4', 'RP15');
      else if (v === 'moms_afstemning') ids.push('RP4', 'RP3');
      else if (v === 'moms_rubrik') ids.push('RP15', 'RP12', 'RP3');
      else if (v === 'moms_oss') ids.push('RP16', 'RP3');
      else if (v === 'moms_transaktioner') ids.push('RP17', 'RP4');
      else ids.push('RP12', 'RP15');
      ids.push('RP3', 'RP4');
    } else if (g === 'saft') {
      if (v === 'regnskabbasis') ids.push('RP18', 'RP11');
      else ids.push('RP11', 'RP18');
    } else if (g === 'emb') {
      ids.push('RP19');
    }
    ids.push('RP1', 'RP2', 'RP3', 'RP4');
    if (f.simulering) ids.push('RP5');
    ids.push('RP6', 'RP7');
    if (f.dimensioner) ids.push('RP8');
    ids.push('RP9', 'RP10', 'RP15', 'RP16', 'RP12', 'RP17', 'RP11', 'RP18');
    if (f.emballageValg) ids.push('RP19');
    ids.push('RP14');
    return unique(ids);
  }

  const finansRapport = {
    group: 'rp',
    states: RP_STATES,
    flags: RP_FLAGS,

    name: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const d = (st && st.data) || {};
      if (s === 'regnskab') return rpHas(RP_ART_NAME, d.art) ? RP_ART_NAME[d.art] : 'help.rp.name_regnskab';
      return rpHas(RP_STATE_NAME, s) ? RP_STATE_NAME[s] : 'help.rp.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const url = ctx.url;
      const flags = {};
      RP_FLAGS.forEach(function (k) { flags[k] = false; });
      const data = { valgt: '', art: '' };
      let state = 'unknown';
      const form = rpQ(doc, 'form[name="rapport"]');                 // forside.php:306
      const typeSel = rpQ(doc, 'select[name="rapportart"]');         // forside.php:310
      if (form && typeSel) {
        state = 'forside';
        data.valgt = String(typeSel.value || '');
        // An empty chart of accounts disables both account selects and OK (forside.php:226,540,555,582).
        flags.ingenKonti = !!(rpQ(doc, 'select[name="konto_fra"][disabled]') || rpQ(doc, 'input[name="submit"][disabled]'));
        // A report's back link carries rapportart and the period (regnskab.php:308, moms_afstemning.php:114-120); the
        // dashboard's VAT link carries rapportart only (index/dashboard.php:300) and is not a return from a report.
        flags.fraRapport = !!(url && url.searchParams && url.searchParams.has('rapportart') && url.searchParams.has('maaned_fra'));
        flags.heleAaret = rpWholeYear(doc);
        flags.simulering = !!rpQ(doc, 'input[name="simulering"]');   // only when a simulated journal exists (forside.php:307-309)
        flags.dimensioner = !!(rpQ(doc, 'select[name="afd"]') || rpQ(doc, 'select[name="projekt_fra"]') ||
          rpQ(doc, 'input[name^="delprojekt"]') || rpQ(doc, 'select[name="ansat_fra"]'));   // forside.php:391-478
        flags.sidsteAar = !!rpQ(doc, 'option[value="lastYear"]');    // not offered in financial year 1 (forside.php:362-363)
        flags.emballageValg = !!rpQ(doc, 'option[value="emballage"]');   // packaging module on (forside.php:378-379)
        return { state: state, flags: flags, data: data };
      }
      const back = rpQ(doc, 'a[href*="rapportart="]');
      const href = attr(back, 'href');
      const m = /rapportart=([A-Za-z_]+)/.exec(href);
      const art = m ? m[1] : '';
      if (art && rpHas(RP_ART_STATE, art)) {
        state = RP_ART_STATE[art];
        data.art = art;
        // The back link repeats the POSTed choices; the simulering checkbox posts "on" (rapport.php:260).
        const sim = /[?&]simulering=on/.test(href);
        const dim = /[?&](afd|projekt_fra|ansat_fra)=[^&']/.test(href);
        if (state === 'regnskab' || state === 'kontokort' || state === 'kontokortMoms') flags.simuleret = sim;
        if (['regnskab', 'kontokort', 'kontokortUbogfort', 'kontokortMoms', 'momsangivelse'].indexOf(state) !== -1) flags.filter = dim;
        if (state === 'kontokort' || state === 'kontokortUbogfort') {
          flags.tom = !rpQ(doc, '#datapg td.kontokort-dato');                // kontokort.php:1127
          flags.ubogfortFejl = !!rpQ(doc, 'div[style*="b00000"]');           // kontokort.php:549
        } else if (state === 'momsAfstemning') {
          flags.afvigelser = !!rpQ(doc, 'tr[style*="ffe0e0"]');              // moms_afstemning.php:277-284
          flags.ingenAfvigelser = !flags.afvigelser && !!rpQ(doc, 'div[style*="d4edda"]');   // :234-241
          flags.tom = !flags.afvigelser && !flags.ingenAfvigelser;            // no summary box: no rows (:233, :309-312)
        } else if (state === 'momsRubrik') {
          // The red box links to the debtor groups once more than the footnote does (moms_rubrik.php:281, :324).
          flags.rubrikMangler = rpAll(doc, 'a[href*="valg=debitor"]').length >= 2;
        } else if (state === 'momsOss') {
          // The footnote under the summary always links to Moms and Debitor (moms_oss.php:547-551), so a tip
          // shows as a second link: Type tip :201-207, EU-zone tip :279-285.
          const momsLinks = rpAll(doc, 'a[href*="valg=moms"]').length;
          const table = !!rpQ(doc, 'table.dataTable');
          flags.ossMangler = momsLinks > 0 && !table;                         // moms_oss.php:181-193
          flags.typeMangler = table && momsLinks >= 2;                        // :201-207 + :549
          flags.zoneMangler = table && rpAll(doc, 'a[href*="valg=debitor"]').length >= 2;   // :279-285 + :550
        } else if (state === 'emballage') {
          flags.udenPeriode = !rpQ(doc, 'h3');                                 // emballage.php: both views print an h3
        }
        return { state: state, flags: flags, data: data };
      }
      if (!rpQ(doc, 'select[name="regnaar"]') && rpBodyText(doc).indexOf('Der er ikke oprettet et regnskabs') !== -1) {
        state = 'ingenAar';                                                   // forside.php:148-165
      }
      return { state: state, flags: flags, data: data };
    },

    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const d = (st && st.data) || {};
      const out = [];
      const second = function (k) { if (k) out.push(k); };
      if (s === 'forside') {
        out.push('help.rp.about.forside');
        if (f.ingenKonti) second('help.rp.about.ingen_konti');
        else if (f.fraRapport) second('help.rp.about.fra_rapport');
        else if (rpGroup(d.valgt) === 'moms') second('help.rp.about.forside_moms');
      } else if (s === 'ingenAar') {
        out.push('help.rp.about.ingen_aar');
      } else if (s === 'regnskab') {
        out.push(rpHas(RP_ART_ABOUT, d.art) ? RP_ART_ABOUT[d.art] : 'help.rp.about.regnskab');
        second(f.filter ? 'help.rp.about.filter' : f.simuleret ? 'help.rp.about.simuleret' : null);
      } else if (s === 'kontokort') {
        out.push('help.rp.about.kontokort');
        second(f.filter ? 'help.rp.about.filter' : f.simuleret ? 'help.rp.about.simuleret' : null);
      } else if (s === 'kontokortUbogfort') {
        out.push('help.rp.about.ubogfort');
        second(f.ubogfortFejl ? 'help.rp.about.ubogfort_fejl' : f.filter ? 'help.rp.about.filter' : null);
      } else if (s === 'kontokortMoms') {
        out.push('help.rp.about.kontokort_moms');
        second(f.filter ? 'help.rp.about.filter' : f.simuleret ? 'help.rp.about.simuleret' : null);
      } else if (s === 'momsangivelse') {
        out.push('help.rp.about.momsangivelse', 'help.rp.about.moms_skat');
      } else if (s === 'momsAfstemning') {
        out.push('help.rp.about.moms_afstemning');
        second(f.afvigelser ? 'help.rp.about.afvigelser' : f.ingenAfvigelser ? 'help.rp.about.ingen_afvigelser' :
          f.tom ? 'help.rp.about.afstemning_tom' : null);
      } else if (s === 'momsRubrik') {
        out.push('help.rp.about.moms_rubrik');
        second(f.rubrikMangler ? 'help.rp.about.rubrik_mangler' : null);
      } else if (s === 'momsOss') {
        out.push('help.rp.about.moms_oss');
        second(f.ossMangler ? 'help.rp.about.oss_mangler' : null);
      } else if (s === 'momsTransaktioner') {
        out.push('help.rp.about.moms_transaktioner');
      } else if (s === 'emballage') {
        out.push('help.rp.about.emballage');
        second(f.udenPeriode ? 'help.rp.about.uden_periode' : null);
      }
      return out;
    },

    tasks: RP_TASKS,

    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const d = (st && st.data) || {};
      const ids = [];
      if (s === 'forside') return rpForsideOrder(f, d);
      if (s === 'ingenAar') {
        ids.push('NA1');
      } else if (s === 'regnskab') {
        if (f.filter) ids.push('RG5');
        if (f.simuleret) ids.push('RG6');
        if (d.art === 'budget') ids.push('RG7');
        ids.push('RG1', 'RG2', 'CSV', 'RG4');
      } else if (s === 'kontokort') {
        if (f.tom) ids.push('KO5');
        if (f.filter) ids.push('RG5');
        if (f.simuleret) ids.push('RG6');
        ids.push('KO1', 'KO3', 'KO2', 'CSV', 'RG4');
      } else if (s === 'kontokortUbogfort') {
        if (f.ubogfortFejl) ids.push('KU2');
        if (f.tom) ids.push('KO5');
        if (f.filter) ids.push('RG5');
        ids.push('KU1', 'KO3', 'KO2', 'CSV', 'RG4');
      } else if (s === 'kontokortMoms') {
        if (f.filter) ids.push('RG5');
        if (f.simuleret) ids.push('RG6');
        ids.push('KM1', 'KO1', 'CSV', 'RG4');
      } else if (s === 'momsangivelse') {
        if (f.filter) ids.push('MA4');
        ids.push('MA2', 'MA1', 'MA5', 'MA3', 'RG4');
      } else if (s === 'momsAfstemning') {
        if (f.tom) ids.push('MS5');
        if (f.afvigelser) ids.push('MS1');
        if (f.ingenAfvigelser) ids.push('MS4');
        ids.push('MP', 'CSVM');
      } else if (s === 'momsRubrik') {
        if (f.rubrikMangler) ids.push('MR1');
        ids.push('MR2', 'MR3', 'MR5', 'CSVM');
      } else if (s === 'momsOss') {
        if (f.ossMangler) ids.push('OS1');
        if (f.zoneMangler) ids.push('OS3');
        if (f.typeMangler) ids.push('OS4');
        ids.push('OS2', 'MP', 'OS5');
      } else if (s === 'momsTransaktioner') {
        ids.push('MT1', 'MT2', 'MP', 'CSVM');
      } else if (s === 'emballage') {
        ids.push('EM1', 'EM3', 'EM2');
      }
      return unique(ids);
    },

    fields: RP_FIELDS,

    // delprojekt[0], delprojekt[1] … are the parts of a structured project number (forside.php:413-425).
    fieldKey: function (el) {
      return /^delprojekt\[/.test(attr(el, 'name')) ? 'delprojekt' : null;
    },

    // Every report's Tilbage is an accesskey=L link (regnskab.php:310, kontokort.php:330, momsangivelse.php:183,
    // moms_afstemning.php:138, emballage.php:61). The selection form has no access keys.
    shortcuts: [
      { keys: 'Alt+L', text: 'help.rp.key.01', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.rp.q.q1.q',
        words: {
          da: ['moms', 'momsangivelse', 'indberet', 'indberette', 'indberetning', 'indberet moms', 'direkte', 'skat', 'skattestyrelsen', 'tastselv', 'kalender'],
          en: ['vat', 'vat return', 'file vat', 'report vat', 'directly', 'tax', 'calendar'],
          no: ['mva', 'mva-melding', 'moms', 'rapportere', 'innrapportering', 'direkte', 'skatt', 'skattestyrelsen', 'kalender']
        },
        intro: 'help.rp.q.q1.intro',
        steps: ['help.rp.q.q1.step1', 'help.rp.q.q1.step2', 'help.rp.task.ma1.step2', 'help.rp.task.ma1.step3'],
        show: RP_TYPE
      },
      Q2: {
        q: 'help.rp.q.q2.q',
        words: {
          da: ['rapportspecifikation', 'ikke defineret', 'momsrapport', 'momsopsætning', 'opsætning af moms'],
          en: ['report specification', 'not defined', 'vat report setup', 'vat setup'],
          no: ['rapportspesifikasjon', 'ikke definert', 'mva-rapport', 'mva-oppsett']
        },
        intro: 'help.rp.q.q2.intro',
        steps: ['help.rp.q.q2.step1', 'help.rp.q.q2.step2', 'help.rp.q.q2.step3', 'help.rp.q.q2.step4']
      },
      Q3: {
        q: 'help.rp.q.q3.q',
        words: {
          da: ['minus', 'negativ', 'negativt', 'negative', 'fortegn', 'overskud', 'underskud'],
          en: ['minus', 'negative', 'sign', 'profit', 'loss'],
          no: ['minus', 'negativ', 'negativt', 'fortegn', 'overskudd', 'underskudd']
        },
        steps: stepKeys('rp', 'RG2', 1, 2)
      },
      Q4: {
        q: 'help.rp.q.q4.q',
        words: {
          da: ['mangler', 'kan ikke se', 'ikke med', 'ubogført', 'ikke bogført', 'nyeste', 'kladde'],
          en: ['missing', 'not shown', 'unposted', 'not posted', 'latest', 'journal'],
          no: ['mangler', 'kan ikke se', 'ikke med', 'ikke bokført', 'nyeste', 'kladd']
        },
        intro: 'help.rp.q.q4.intro',
        steps: ['help.rp.q.q4.step1', 'help.rp.q.q4.step2', 'help.rp.q.q4.step3']
      },
      Q5: {
        q: 'help.rp.q.q5.q',
        words: {
          da: ['sideskift', 'sideskiftkonto', 'adskilles', 'adskille', 'balance og resultat', 'resultat og balance', 'kontotype',
            'resultatkonto', 'årets resultat', 'aktiver og passiver'],
          en: ['page change', 'separated', 'separate', 'balance and result', 'result and balance', 'account type',
            'result account', 'assets and liabilities'],
          no: ['sideskift', 'sideskiftkonto', 'skille', 'balanse og resultat', 'resultat og balanse', 'kontotype',
            'resultatkonto', 'årets resultat', 'eiendeler og gjeld']
        },
        intro: 'help.rp.q.q5.intro',
        steps: ['help.rp.q.q5.step1', 'help.rp.q.q5.step2', 'help.rp.q.q5.step3', 'help.rp.q.q5.step4']
      },
      Q6: {
        q: 'help.rp.q.q6.q',
        words: {
          da: ['posteringer bag', 'detaljer', 'specifikation', 'kontokort', 'hvad består', 'bilag'],
          en: ['entries behind', 'details', 'breakdown', 'general ledger', 'drill down', 'voucher'],
          no: ['posteringer bak', 'detaljer', 'spesifikasjon', 'kontokort', 'bilag']
        },
        steps: stepKeys('rp', 'RG1', 1, 3).concat(['help.rp.task.ko1.step1']),
        show: RP_BACK
      },
      Q7: {
        q: 'help.rp.q.q7.q',
        words: {
          da: ['afstem', 'afstemme', 'afstemning', 'momsafstemning', 'difference', 'kontrol'],
          en: ['reconcile', 'reconciliation', 'vat check', 'difference'],
          no: ['avstem', 'avstemme', 'avstemming', 'differanse', 'kontroll']
        },
        steps: stepKeys('rp', 'RP4', 1, 3).concat(['help.rp.task.ms1.step3']),
        show: RP_TYPE
      }
    },

    // The chat's start screen shows these (spec §25). Q2 answers an error that sends the user back to the form, so it
    // never leads on a report that was drawn, and Q5 (how Resultat and Balance are split) is found by its words. Q6 is the
    // way from a total to an account card, so on an account card it comes last; Kontokort med moms is the tool of Q7.
    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const d = (st && st.data) || {};
      if (s === 'forside') {
        const g = rpGroup(d.valgt);
        if (g === 'moms') return ['Q1', 'Q7', 'Q2'];
        if (g === 'regn') return ['Q3', 'Q6', 'Q4'];
        if (g === 'kort') return ['Q6', 'Q4', 'Q3'];
        return ['Q1', 'Q4', 'Q3'];
      }
      if (s === 'regnskab') return ['Q3', 'Q6', 'Q4'];
      if (s === 'kontokort' || s === 'kontokortUbogfort') return ['Q4', 'Q3', 'Q6'];
      if (s === 'kontokortMoms') return ['Q7', 'Q4', 'Q3'];
      if (s === 'momsangivelse') return ['Q1', 'Q7', 'Q4'];
      if (s === 'momsAfstemning') return ['Q7', 'Q1', 'Q4'];
      if (s === 'momsRubrik' || s === 'momsOss' || s === 'momsTransaktioner') return ['Q1', 'Q7', 'Q4'];
      if (s === 'emballage') return ['Q4'];
      return ['Q1', 'Q4', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['finans/rapport.php'] = finansRapport;

  // ==================================================================== Finance pages (a3)
  // Kontrolspor, Momsperioder, Listeangivelse, SAF-T, Bankafstemning, Regnskab Basis and the two standalone
  // account cards. Everything sits in one block, so the small helpers below cannot clash with other sections.
  // Uses task(), showKey(), fieldKeys(), fieldLabel(), stepKeys(), unique(), attr() and byName() from above.
  {
    /** querySelector on a document or element that never throws; null when nothing matches. */
    function finQ(root, sel) {
      try {
        const el = root && root.querySelector ? root.querySelector(sel) : null;
        return el && el.nodeType === 1 ? el : null;
      } catch (e) {
        return null;
      }
    }

    /** querySelectorAll as an array; [] on error. */
    function finQa(root, sel) {
      try {
        return root && root.querySelectorAll ? Array.prototype.slice.call(root.querySelectorAll(sel)) : [];
      } catch (e) {
        return [];
      }
    }

    function finText(el) {
      return el && el.nodeType === 1 ? String(el.textContent || '').replace(/\s+/g, ' ').trim() : '';
    }

    function finVal(el) {
      return el && el.nodeType === 1 ? String(el.value || '').trim() : '';
    }

    /** A URL parameter of the frame page, '' when missing. */
    function finParam(ctx, name) {
      try {
        return String(ctx.url.searchParams.get(name) || '');
      } catch (e) {
        return '';
      }
    }

    function finClosest(el, sel) {
      try {
        return el && el.closest ? el.closest(sel) : null;
      } catch (e) {
        return null;
      }
    }

    /** The submit button of the form that holds the first hidden action input with this value (moms_periode.php). */
    function finActionButton(doc, action) {
      const input = finQ(doc, 'input[type="hidden"][name="action"][value="' + action + '"]');
      const form = input ? input.form : null;
      return form ? finQ(form, 'button[type="submit"]') : null;
    }

    /** The default field-key rule (§1.6), for the pages below that override fieldKey(). */
    function finFieldKey(el) {
      const n = attr(el, 'name');
      let m;
      if ((m = /^search\[[^\]]+\]\[([^\]]+)\]$/.exec(n))) return 'search:' + m[1];
      if (/^rowcount\[/.test(n)) return 'rowcount';
      if (n) return n.replace(/_?\d+$/, '');
      return attr(el, 'id') || null;
    }

    function finFields(g, keys) {
      const out = {};
      keys.forEach(function (k) { out[k] = fieldKeys(g, k); });
      return out;
    }

    // ---- Kontrolspor (finans/kontrolspor.php)
    // Rapporter, button Kontrolspor (rapport_includes/forside.php:597) posts to rapport.php, which reloads the frame
    // with kontrolspor.php (rapport.php:85-87). Filter inputs :266-280 (one form, Enter submits), rows tr[bgcolor] :456,
    // paging arrows :221,:226, CSV link :489. Single numbers in Id/Bilag/Kladde/Konto/Kasse match as a prefix
    // (includes/udvaelg.php:111); amounts match to the øre (:67-72); Debet and Kredit together are OR (kontrolspor.php:322-327).

    const KS_FILTERS = ['idnumre', 'transdatoer', 'logdatoer', 'logtid', 'kladdenumre', 'bilagsnumre', 'kontonumre',
      'fakturanumre', 'debetbelob', 'kreditbelob', 'projektnumre', 'kassenumre', 'beskrivelse'];

    const KS_TASKS = {
      KS1: task('ks', 'KS1', 3, { target: 'input[name="bilagsnumre"]', label: fieldLabel('ks', 'bilagsnumre'), kind: 'field' }),
      KS2: task('ks', 'KS2', 3, { target: 'input[name="kontonumre"]', label: fieldLabel('ks', 'kontonumre'), kind: 'field' }),
      KS3: task('ks', 'KS3', 3, { target: 'input[name="debetbelob"]', label: fieldLabel('ks', 'debetbelob'), kind: 'field' }),
      KS4: task('ks', 'KS4', 2, { target: 'input[name="kladdenumre"]', label: fieldLabel('ks', 'kladdenumre'), kind: 'field' }),
      KS5: task('ks', 'KS5', 2, { target: 'input[name="logdatoer"]', label: fieldLabel('ks', 'logdatoer'), kind: 'field' }),
      KS6: task('ks', 'KS6', 2, { target: 'input[name="beskrivelse"]', label: fieldLabel('ks', 'beskrivelse'), kind: 'field' }),
      KS7: task('ks', 'KS7', 2, { target: 'input[type="submit"][name="submit"]', label: showKey('ks', 'KS7'), kind: 'button' }),
      KS8: task('ks', 'KS8', 2, { target: 'input[name="fakturanumre"]', label: fieldLabel('ks', 'fakturanumre'), kind: 'field' })
    };

    const kontrolspor = {
      group: 'ks',
      states: ['liste', 'tom', 'csv'],
      flags: ['soegning', 'mange', 'datoFilter'],

      name: function () {
        return 'help.ks.name';
      },

      detect: function (ctx) {
        const doc = ctx.doc;
        const filled = {};
        KS_FILTERS.forEach(function (n) { filled[n] = finVal(byName(doc, n)) !== ''; });
        // Rows are printed with a bgcolor (kontrolspor.php:456); the header and filter rows have none.
        const rows = finQa(doc, 'tr[bgcolor]').filter(function (tr) { return !!tr.cells && tr.cells.length >= 10; });
        const csv = finParam(ctx, 'csv');
        const flags = {
          // Dato is filled with the financial year whenever every field is empty (:125-128), so it alone is no search.
          soegning: KS_FILTERS.some(function (n) { return n !== 'transdatoer' && filled[n]; }),
          mange: !!finQ(doc, 'a[href*="start="] img[src*="right.png"]') || !!finQ(doc, 'a[href*="start="] img[src*="left.png"]'),
          datoFilter: filled.transdatoer
        };
        let state = rows.length ? 'liste' : 'tom';
        if (csv !== '' && csv !== '0') state = 'csv';        // the CSV pass prints no rows (:446)
        return { state: state, flags: flags, data: { rows: rows.length } };
      },

      about: function (ctx, st) {
        const state = st ? st.state : 'liste';
        const f = (st && st.flags) || {};
        const out = [state === 'tom' ? 'help.ks.about.tom' : state === 'csv' ? 'help.ks.about.csv' : 'help.ks.about.liste'];
        if (f.soegning) out.push('help.ks.about.soegning');
        return out;
      },

      tasks: KS_TASKS,

      order: function (ctx, st) {
        const state = st ? st.state : 'liste';
        const f = (st && st.flags) || {};
        const ids = [];
        if (f.soegning) ids.push('KS7');                      // the cookie brings the last search back (kontrolspor.php:117-121)
        else if (state === 'tom') ids.push('KS2');
        ids.push('KS1', 'KS3', 'KS2');
        ids.push('KS5', 'KS4', 'KS8', 'KS6');
        return unique(ids);
      },

      fields: finFields('ks', KS_FILTERS.concat(['linjeantal'])),

      shortcuts: [
        { keys: 'Enter', text: 'help.ks.key.01', group: 'keys' }
      ],

      questions: {
        Q1: {
          q: 'help.ks.q.q1.q',
          words: {
            da: ['kan ikke finde', 'finder ikke', 'mangler', 'væk', 'ikke med', 'ingen posteringer'],
            en: ["can't find", 'cannot find', 'missing', 'not shown', 'no entries'],
            no: ['finner ikke', 'kan ikke finne', 'mangler', 'borte', 'ikke med', 'ingen posteringer']
          },
          intro: 'help.ks.q.q1.intro',
          steps: ['help.ks.task.ks1.step3', 'help.ks.q.q1.step2', 'help.ks.task.ks7.step1'],
          show: { target: 'input[name="transdatoer"]', label: fieldLabel('ks', 'transdatoer'), kind: 'field' }
        },
        Q2: {
          q: 'help.ks.q.q2.q',
          words: {
            da: ['log dato', 'logdato', 'log. dato', 'bogført dato', 'tidspunkt', 'hvornår bogført'],
            en: ['log date', 'posted date', 'time', 'when posted'],
            no: ['logg dato', 'løk dato', 'bokført dato', 'tidspunkt', 'når bokført']
          },
          steps: ['help.ks.task.ks5.step2', 'help.ks.task.ks5.step1'],
          show: KS_TASKS.KS5.show
        },
        Q3: {
          q: 'help.ks.q.q3.q',
          words: {
            da: ['for mange', 'flere bilag', 'præcis', 'nøjagtig', 'interval', 'kolon'],
            en: ['too many', 'exact', 'exactly', 'range', 'colon'],
            no: ['for mange', 'nøyaktig', 'presis', 'intervall', 'kolon']
          },
          steps: ['help.ks.task.ks1.step2', 'help.ks.q.q3.step2'],
          show: KS_TASKS.KS1.show
        }
      },

      questionOrder: function (ctx, st) {
        return st && st.state === 'tom' ? ['Q1', 'Q3', 'Q2'] : ['Q1', 'Q2', 'Q3'];
      },

      signal: function () {
        return false;
      }
    };
    PAGES['finans/kontrolspor.php'] = kontrolspor;

    // ---- Momsperioder (finans/moms_periode.php)
    // No menu link in this branch (sidebar link removed, main.php:32; systemdata/regnskabsaar.php now only redirects to
    // Indstillinger, :45-51); reachable from Saldi AI's Gå dertil (saldi-assist-navigate.js:47) and recent pages. All UI text
    // is hard-coded Danish. Year links :200-202, bulk forms :207-232, rows :264-296 (closed #ffe0e0, open #f0fff0),
    // message ?msg= :137,:144,:182 (OBS: = unposted journals, :117). The lock is a trigger on transaktioner
    // (includes/std_func.php:3487-3517) plus checks in bogfor.php:254 and includes/ordrefunc.php:1321.

    const MP_TASKS = {
      MP1: task('mp', 'MP1', 3, { target: function (ctx) { return finActionButton(ctx.doc, 'luk'); }, label: showKey('mp', 'MP1'), kind: 'button' }),
      MP2: task('mp', 'MP2', 3, { target: function (ctx) { return finActionButton(ctx.doc, 'bulk_luk'); }, label: showKey('mp', 'MP2'), kind: 'button' }),
      MP3: task('mp', 'MP3', 3, { target: function (ctx) { return finActionButton(ctx.doc, 'aaben'); }, label: showKey('mp', 'MP3'), kind: 'button' }),
      MP4: task('mp', 'MP4', 2, { target: function (ctx) { return finActionButton(ctx.doc, 'bulk_aaben'); }, label: showKey('mp', 'MP4'), kind: 'button' },
        ['help.mp.task.mp3.step3']),
      MP5: task('mp', 'MP5', 2, null, ['help.mp.task.mp3.step3']),
      MP6: task('mp', 'MP6', 2, { target: 'a[href*="moms_periode.php?vis_aar="]', label: showKey('mp', 'MP6'), kind: 'button' }),
      MP7: task('mp', 'MP7', 2, { target: 'div[style*="fff3cd"]', label: showKey('mp', 'MP7'), kind: 'status' }),
      MP8: task('mp', 'MP8', 1, { target: 'div[style*="color:#c00"]', label: showKey('mp', 'MP8'), kind: 'status' })
    };

    const momsPerioder = {
      group: 'mp',
      states: ['oversigt', 'ikkeKlar'],
      flags: ['kanAendre', 'besked', 'kladdeAdvarsel', 'harLukket', 'harAaben'],

      name: function () {
        return 'help.mp.name';
      },

      detect: function (ctx) {
        const doc = ctx.doc;
        const actions = finQa(doc, 'input[type="hidden"][name="action"]').map(function (i) { return finVal(i); });
        const msg = finParam(ctx, 'msg');
        const closed = finQa(doc, 'tr[style*="ffe0e0"]').length;
        const open = finQa(doc, 'tr[style*="f0fff0"]').length;
        const flags = {
          kanAendre: actions.indexOf('bulk_luk') !== -1,
          besked: msg !== '',
          kladdeAdvarsel: msg.indexOf('OBS:') !== -1,
          harLukket: closed > 0,
          harAaben: open > 0
        };
        // The year links are printed only when the lock table exists (:191-203).
        const ready = !!finQ(doc, 'a[href*="moms_periode.php?vis_aar="]');
        return { state: ready ? 'oversigt' : 'ikkeKlar', flags: flags, data: { aar: finParam(ctx, 'vis_aar'), lukkede: closed } };
      },

      about: function (ctx, st) {
        const f = (st && st.flags) || {};
        if (st && st.state === 'ikkeKlar') return ['help.mp.about.ikke_klar'];
        const out = ['help.mp.about.oversigt'];
        if (f.kladdeAdvarsel) out.push('help.mp.about.kladde_advarsel');
        else if (!f.kanAendre) out.push('help.mp.about.kun_laes');
        return out;
      },

      tasks: MP_TASKS,

      order: function (ctx, st) {
        const f = (st && st.flags) || {};
        if (st && st.state === 'ikkeKlar') return ['MP7'];
        const ids = [];
        if (f.kladdeAdvarsel) ids.push('MP5');
        if (!f.kanAendre) ids.push('MP8');
        else {
          if (f.harAaben) ids.push('MP1', 'MP2');
          if (f.harLukket) ids.push('MP3', 'MP4');
        }
        ids.push('MP6');
        return unique(ids);
      },

      fields: finFields('mp', ['note', 'bulk_luk_til', 'bulk_fra_maaned', 'bulk_aaben_til']),

      // Both bulk forms name their last month select bulk_til_maaned (:211, :227); the form's action tells them apart.
      fieldKey: function (el) {
        if (attr(el, 'name') === 'bulk_til_maaned') {
          const a = el.form ? finQ(el.form, 'input[name="action"]') : null;
          return a && finVal(a) === 'bulk_aaben' ? 'bulk_aaben_til' : 'bulk_luk_til';
        }
        return finFieldKey(el);
      },

      shortcuts: [],

      questions: {
        Q1: {
          q: 'help.mp.q.q1.q',
          words: {
            da: ['luk', 'lukke', 'lås', 'låse', 'momsperiode', 'periode', 'periodelås'],
            en: ['close', 'lock', 'vat period', 'period'],
            no: ['lukk', 'lukke', 'lås', 'låse', 'mva-periode', 'periode']
          },
          steps: stepKeys('mp', 'MP1', 1, 3),
          show: MP_TASKS.MP1.show
        },
        Q2: {
          q: 'help.mp.q.q2.q',
          words: {
            da: ['kan ikke bogføre', 'lukket', 'låst', 'afvist', 'periode lukket', 'genåbn'],
            en: ["can't post", 'cannot post', 'closed', 'locked', 'rejected', 'reopen'],
            no: ['kan ikke bokføre', 'lukket', 'låst', 'avvist', 'gjenåpne']
          },
          intro: 'help.mp.q.q2.intro',
          steps: ['help.mp.q.q2.step1', 'help.mp.task.mp3.step1', 'help.mp.task.mp3.step3'],
          show: MP_TASKS.MP3.show
        }
      },

      questionOrder: function () {
        return ['Q1', 'Q2'];
      },

      signal: function () {
        return false;
      }
    };
    PAGES['finans/moms_periode.php'] = momsPerioder;

    // ---- Listeangivelse (finans/listeangivelse.php)
    // Rapporter, Rapporttype "Listeangivelse N. måned ÅÅÅÅ" (rapport.php:401-445) and OK; rapport.php:120-124 reloads the
    // frame with ?listeperiode=N.ÅÅÅÅ. No month: h1 :90. Data rows tr[style*=background] :184, file link :199, none :227.
    // Tilbage a[accesskey=L] :256. Saldi files nothing itself.

    const LA_TASKS = {
      LA1: task('la', 'LA1', 2, { target: 'a[accesskey="L"]', label: showKey('la', 'LA1'), kind: 'button' }),
      LA2: task('la', 'LA2', 4, {
        target: function (ctx) { return finQ(ctx.doc, '#laGridWrapper tr[style*="background"]'); },
        label: showKey('la', 'LA2'),
        kind: 'row'
      }),
      LA3: task('la', 'LA3', 3, {
        target: function (ctx) { return finQ(ctx.doc, '#laGridWrapper tr[style*="background"]'); },
        label: showKey('la', 'LA2'),
        kind: 'row'
      }),
      LA4: task('la', 'LA4', 3, null),
      LA5: task('la', 'LA5', 0, { target: 'a[accesskey="L"]', label: showKey('la', 'LA1'), kind: 'button' },
        ['help.la.task.la1.step1', 'help.la.task.la5.step2'])
    };

    const listeangivelse = {
      group: 'la',
      states: ['liste', 'ingenPoster', 'ingenPeriode'],
      flags: ['fil'],

      name: function () {
        return 'help.la.name';
      },

      detect: function (ctx) {
        const doc = ctx.doc;
        const rows = finQa(doc, '#laGridWrapper tr[style*="background"]');
        const fil = !!finQ(doc, 'a[href*="listeangivelse_"]');
        let state = rows.length || fil ? 'liste' : 'ingenPoster';
        if (finQ(doc, '#laGridWrapper h1')) state = 'ingenPeriode';    // only printed without a month (:90)
        return { state: state, flags: { fil: fil }, data: { rows: rows.length, periode: finParam(ctx, 'listeperiode') } };
      },

      about: function (ctx, st) {
        const state = st ? st.state : 'liste';
        if (state === 'ingenPeriode') return ['help.la.about.ingen_periode'];
        if (state === 'ingenPoster') return ['help.la.about.ingen_poster'];
        return ['help.la.about.liste', 'help.la.about.indberet'];
      },

      tasks: LA_TASKS,

      order: function (ctx, st) {
        const state = st ? st.state : 'liste';
        if (state === 'ingenPeriode') return ['LA1'];
        if (state === 'ingenPoster') return ['LA4', 'LA5'];
        return ['LA2', 'LA3', 'LA5'];
      },

      fields: {},

      shortcuts: [],

      questions: {
        Q1: {
          q: 'help.la.q.q1.q',
          words: {
            da: ['indberet', 'indberette', 'eu-salg', 'eu salg', 'listeangivelse', 'skattestyrelsen', 'skat'],
            en: ['report', 'file', 'eu sales', 'ec sales list', 'tax agency'],
            no: ['rapporter', 'rapportere', 'eu-salg', 'listeangivelse', 'skatt']
          },
          steps: stepKeys('la', 'LA3', 1, 3),
          show: LA_TASKS.LA3.show
        },
        Q2: {
          q: 'help.la.q.q2.q',
          words: {
            da: ['mangler', 'kunde mangler', 'ingen poster', 'tom', 'ikke med'],
            en: ['missing', 'customer missing', 'no entries', 'empty'],
            no: ['mangler', 'kunde mangler', 'ingen poster', 'tom']
          },
          steps: stepKeys('la', 'LA4', 1, 2)
        }
      },

      questionOrder: function (ctx, st) {
        return st && st.state === 'ingenPoster' ? ['Q2', 'Q1'] : ['Q1', 'Q2'];
      },

      signal: function () {
        return false;
      }
    };
    PAGES['finans/listeangivelse.php'] = listeangivelse;

    // ---- SAF-T (finans/saft.php)
    // Rapporter, Rapporttype SAF-T and OK; rapport.php:326-328 redirects the frame to saft.php. Unmapped accounts:
    // #showStandardKontoplan :871 and links to systemdata/kontokort.php :853; ready: form[action=saftCreator.php] :804,
    // Opret :823; file: #download :832, #showXML :836, #createMessage :777. saftCreator.php is only the action
    // (POST, writes the XML, location.replace back to saft.php, saftCreator.php:269-280): no page object.

    const SAFT_TASKS = {
      SAFT1: task('saft', 'SAFT1', 4, {
        target: function (ctx) { return finClosest(finQ(ctx.doc, 'a[href*="systemdata/kontokort.php?id="]'), 'tr'); },
        label: showKey('saft', 'SAFT1'),
        kind: 'row'
      }),
      SAFT2: task('saft', 'SAFT2', 3, { target: 'a[href*="diverse.php?sektion=div_io"]', label: showKey('saft', 'SAFT2'), kind: 'button' }),
      SAFT3: task('saft', 'SAFT3', 3, { target: 'form[action="saftCreator.php"] button[type="submit"]', label: showKey('saft', 'SAFT3'), kind: 'button' }),
      SAFT4: task('saft', 'SAFT4', 3, { target: '#download', label: showKey('saft', 'SAFT4'), kind: 'button' }),
      SAFT5: task('saft', 'SAFT5', 4, { target: '#showXML', label: showKey('saft', 'SAFT5'), kind: 'button' }),
      SAFT6: task('saft', 'SAFT6', 2, { target: 'a[accesskey="L"]', label: showKey('saft', 'SAFT6'), kind: 'button' })
    };

    const saft = {
      group: 'saft',
      states: ['mangler', 'klar'],
      flags: ['fil', 'oprettet'],

      name: function () {
        return 'help.saft.name';
      },

      detect: function (ctx) {
        const doc = ctx.doc;
        const flags = {
          fil: !!finQ(doc, '#download'),
          oprettet: finText(finQ(doc, '#createMessage')) !== ''
        };
        const state = finQ(doc, '#showStandardKontoplan') ? 'mangler' : 'klar';
        return { state: state, flags: flags, data: { konti: finQa(doc, 'a[href*="systemdata/kontokort.php?id="]').length } };
      },

      about: function (ctx, st) {
        const f = (st && st.flags) || {};
        if (st && st.state === 'mangler') return ['help.saft.about.mangler'];
        return f.fil ? ['help.saft.about.klar', 'help.saft.about.fil'] : ['help.saft.about.klar'];
      },

      tasks: SAFT_TASKS,

      order: function (ctx, st) {
        const f = (st && st.flags) || {};
        if (st && st.state === 'mangler') return ['SAFT1', 'SAFT2', 'SAFT5', 'SAFT6'];
        const ids = [];
        if (f.oprettet) ids.push('SAFT4', 'SAFT5');            // just created: fetch it, then check it before it is handed in
        ids.push('SAFT3');
        if (f.fil) ids.push('SAFT4');
        ids.push('SAFT5', 'SAFT6');
        return unique(ids);
      },

      fields: {},

      shortcuts: [],

      questions: {
        Q1: {
          q: 'help.saft.q.q1.q',
          words: {
            da: ['kan ikke oprette', 'opret', 'bemærk', 'map til', 'mapning', 'standardkonto', 'standardkontoplan'],
            en: ['cannot create', 'create', 'map to', 'mapping', 'standard account'],
            no: ['kan ikke opprette', 'opprett', 'map til', 'tilordning', 'standardkonto']
          },
          intro: 'help.saft.q.q1.intro',
          steps: stepKeys('saft', 'SAFT1', 1, 3),
          show: SAFT_TASKS.SAFT1.show
        },
        Q2: {
          q: 'help.saft.q.q2.q',
          words: {
            da: ['indhold', 'indeholder', 'posteringer', 'primo', 'saldo', 'hvad er med'],
            en: ['content', 'contains', 'entries', 'opening balance', 'balance'],
            no: ['innhold', 'inneholder', 'posteringer', 'primo', 'saldo']
          },
          steps: stepKeys('saft', 'SAFT5', 1, 3)
        },
        Q3: {
          q: 'help.saft.q.q3.q',
          words: {
            da: ['send', 'sende', 'indberet', 'skattestyrelsen', 'skat', 'revisor', 'aflever'],
            en: ['send', 'submit', 'tax', 'auditor', 'hand over'],
            no: ['send', 'sende', 'skatt', 'revisor', 'lever']
          },
          steps: ['help.saft.task.saft5.step4', 'help.saft.task.saft4.step1'],
          show: SAFT_TASKS.SAFT4.show
        }
      },

      questionOrder: function (ctx, st) {
        return st && st.state === 'mangler' ? ['Q1', 'Q2', 'Q3'] : ['Q2', 'Q3', 'Q1'];
      },

      signal: function () {
        return false;
      }
    };
    PAGES['finans/saft.php'] = saft;

    // ---- Bankafstemning (finans/bankReconcile.php)
    // Rapporter, button Afstem bank (rapport_includes/forside.php:592); rapport.php:332-334 redirects the frame here.
    // Upload :222-233 (MAX_FILE_SIZE 100000), columns and Posteringskonto :386-512 (Afstem only when all set, :444-456),
    // result :759-866 (bank left, Saldi right, red = no match, red (x) = balance difference, up/down arrows :804-822),
    // no financial year :709-714. finans/rapport_includes/bankReconcile.php is a stale copy of bankimport.php that
    // rapport.php never includes.

    /** Which side of the result a cell is on: the bank's four cells come first (:777-780); Saldi's follow, and the
     *  extra Saldi rows of a date start with one empty cell spanning the bank side (:841). */
    function baSide(td) {
      const tr = td ? td.parentNode : null;
      const cells = tr && tr.cells ? Array.prototype.slice.call(tr.cells) : [];
      if (cells.length && attr(cells[0], 'colspan') === '4') return 'saldi';
      return cells.indexOf(td) > -1 && cells.indexOf(td) < 4 ? 'bank' : 'saldi';
    }

    const BA_TASKS = {
      BA1: task('ba', 'BA1', 1, { target: 'a[accesskey="L"]', label: showKey('ba', 'BA1'), kind: 'button' }),
      BA2: task('ba', 'BA2', 3, { target: 'input[name="uploadedfile"]', label: fieldLabel('ba', 'uploadedfile'), kind: 'field' }),
      BA3: task('ba', 'BA3', 3, { target: 'select[name^="feltnavn["]', label: fieldLabel('ba', 'feltnavn'), kind: 'field' }),
      BA4: task('ba', 'BA4', 2, { target: 'input[name="kontonr"]', label: fieldLabel('ba', 'kontonr'), kind: 'field' }),
      BA5: task('ba', 'BA5', 3, { target: 'input[name="reconcile"]', label: showKey('ba', 'BA5'), kind: 'button' }),
      BA6: task('ba', 'BA6', 4, {
        // The first row with red text that is not a balance difference, bank side first.
        target: function (ctx) {
          const reds = finQa(ctx.doc, 'td[style*="color:red"]').filter(function (c) {
            return attr(c, 'colspan') !== '10' && !/^\(/.test(finText(c));
          });
          const td = reds.filter(function (c) { return baSide(c) === 'bank'; })[0] || reds[0];
          return td ? td.parentNode : null;
        },
        label: showKey('ba', 'BA6'),
        kind: 'row'
      }),
      BA7: task('ba', 'BA7', 4, {
        target: function (ctx) {
          return finQa(ctx.doc, 'td[style*="color:red"]').filter(function (c) { return /^\(/.test(finText(c)); })[0] || null;
        },
        label: showKey('ba', 'BA7'),
        kind: 'status'
      }),
      BA8: task('ba', 'BA8', 2, { target: 'a[href*="&up="], a[href*="&down="]', label: showKey('ba', 'BA8'), kind: 'button' }),
      BA9: task('ba', 'BA9', 2, { target: 'td[colspan="10"][style*="color:red"]', label: showKey('ba', 'BA9'), kind: 'status' }),
      BA10: task('ba', 'BA10', 2)       // Print side in the shell's topbar (topbar.php:498, main.php:1148-1158 prints the frame): no Vis mig
    };

    const bankAfstemning = {
      group: 'ba',
      states: ['start', 'upload', 'kolonner', 'afstemt', 'fejl'],
      flags: ['manglerKolonne', 'manglerKonto', 'klar', 'saldoValgt', 'bankRoed', 'saldiRoed', 'saldoForskel', 'ingenForskel'],

      name: function () {
        return 'help.ba.name';
      },

      detect: function (ctx) {
        const doc = ctx.doc;
        const selects = finQa(doc, 'select[name^="feltnavn["]');
        const cols = selects.map(function (s) { return finVal(s); });
        const kolonner = selects.length > 0;
        let header = false;
        finQa(doc, 'tr').forEach(function (tr) {
          const c = tr.cells;
          if (c && c.length >= 4 && finText(c[0]) === 'Dato' && finText(c[3]) === 'Saldo') header = true;   // :759, hard-coded
        });
        let bankRoed = false;
        let saldiRoed = false;
        let saldoForskel = false;
        finQa(doc, 'td[style*="color:red"]').forEach(function (td) {
          if (attr(td, 'colspan') === '10') return;                       // the no-financial-year message
          if (/^\(/.test(finText(td))) saldoForskel = true;
          else if (baSide(td) === 'bank') bankRoed = true;
          else saldiRoed = true;
        });
        let state = 'start';
        if (byName(doc, 'uploadedfile')) state = 'upload';
        else if (kolonner) state = 'kolonner';
        else if (finQ(doc, 'td[colspan="10"][style*="color:red"]')) state = 'fejl';
        else if (header) state = 'afstemt';
        const afstemt = state === 'afstemt';
        const flags = {
          manglerKolonne: kolonner && !(cols.indexOf('dato') !== -1 && cols.indexOf('beskrivelse') !== -1 && cols.indexOf('belob') !== -1),
          manglerKonto: kolonner && finVal(byName(doc, 'kontonr')) === '',
          klar: !!byName(doc, 'reconcile'),
          saldoValgt: cols.indexOf('saldo') !== -1,
          bankRoed: afstemt && bankRoed,
          saldiRoed: afstemt && saldiRoed,
          saldoForskel: afstemt && saldoForskel,
          ingenForskel: afstemt && !bankRoed && !saldiRoed && !saldoForskel
        };
        return { state: state, flags: flags, data: { kolonner: selects.length } };
      },

      about: function (ctx, st) {
        const state = st ? st.state : 'start';
        const f = (st && st.flags) || {};
        if (state === 'afstemt') return f.ingenForskel ? ['help.ba.about.afstemt', 'help.ba.about.ingen_forskel'] : ['help.ba.about.afstemt'];
        if (state === 'kolonner') return ['help.ba.about.kolonner'];
        if (state === 'fejl') return ['help.ba.about.fejl'];
        if (state === 'upload') return ['help.ba.about.upload'];
        return ['help.ba.about.start'];
      },

      tasks: BA_TASKS,

      order: function (ctx, st) {
        const state = st ? st.state : 'start';
        const f = (st && st.flags) || {};
        const ids = [];
        if (state === 'start') ids.push('BA1');
        else if (state === 'upload') ids.push('BA2');
        else if (state === 'fejl') ids.push('BA9', 'BA1');
        else if (state === 'kolonner') {
          if (f.manglerKolonne) ids.push('BA3');
          if (f.manglerKonto) ids.push('BA4');
          if (f.klar) ids.push('BA5');
          ids.push('BA3', 'BA4');
        } else if (state === 'afstemt') {
          if (f.bankRoed || f.saldiRoed) ids.push('BA6');
          if (f.saldoForskel) ids.push('BA7', 'BA8');
          ids.push('BA10', 'BA1');
        }
        return unique(ids);
      },

      fields: finFields('ba', ['uploadedfile', 'vend', 'splitter', 'kontonr', 'feltnavn']),

      // The column selects are named feltnavn[0], feltnavn[1] … (:484-490).
      fieldKey: function (el) {
        return /^feltnavn\[/.test(attr(el, 'name')) ? 'feltnavn' : finFieldKey(el);
      },

      shortcuts: [],

      questions: {
        Q1: {
          q: 'help.ba.q.q1.q',
          words: {
            da: ['afstem', 'afstemme', 'afstemning', 'bankafstemning', 'kontoudtog', 'bank'],
            en: ['reconcile', 'reconciliation', 'bank statement', 'bank'],
            no: ['avstem', 'avstemme', 'avstemming', 'kontoutdrag', 'bank']
          },
          steps: ['help.ba.task.ba2.step1', 'help.ba.task.ba2.step2', 'help.ba.task.ba3.step1', 'help.ba.task.ba5.step2'],
          show: BA_TASKS.BA2.show
        },
        Q2: {
          q: 'help.ba.q.q2.q',
          words: {
            da: ['rød', 'røde', 'forskel', 'difference', 'parentes', 'stemmer ikke'],
            en: ['red', 'difference', 'brackets', 'does not match'],
            no: ['rød', 'røde', 'forskjell', 'differanse', 'parentes', 'stemmer ikke']
          },
          steps: ['help.ba.task.ba6.step1', 'help.ba.task.ba6.step2', 'help.ba.task.ba7.step1'],
          show: BA_TASKS.BA6.show
        },
        Q3: {
          q: 'help.ba.q.q3.q',
          words: {
            da: ['afstem mangler', 'ingen afstem', 'kan ikke afstemme', 'knappen afstem'],
            en: ['reconcile button', 'cannot reconcile', 'no reconcile'],
            no: ['avstem mangler', 'kan ikke avstemme', 'knappen afstem']
          },
          steps: ['help.ba.task.ba3.step1', 'help.ba.task.ba4.step1', 'help.ba.task.ba4.step2'],
          show: BA_TASKS.BA3.show
        }
      },

      questionOrder: function (ctx, st) {
        const state = st ? st.state : 'start';
        if (state === 'afstemt') return ['Q2', 'Q1', 'Q3'];
        if (state === 'kolonner') return ['Q3', 'Q1', 'Q2'];
        return ['Q1', 'Q3', 'Q2'];
      },

      signal: function () {
        return false;
      }
    };
    PAGES['finans/bankReconcile.php'] = bankAfstemning;

    // ---- Regnskab Basis (finans/regnskabbasis.php)
    // Rapporter, Rapporttype Regnskab Basis and OK; rapport.php:329-331 redirects the frame here. Unmapped accounts:
    // #showStandardKontoplan :469 and kontokort links :436; the csv link :305 is printed only outside the default
    // layout (:387, not in the $menu=='S' bar :322-380). Values are kontoplan.saldo summed per map_to (:268-279).

    const RB_TASKS = {
      RB1: task('rb', 'RB1', 4, {
        target: function (ctx) { return finClosest(finQ(ctx.doc, 'a[href*="systemdata/kontokort.php?id="]'), 'tr'); },
        label: showKey('rb', 'RB1'),
        kind: 'row'
      }),
      RB2: task('rb', 'RB2', 3, { target: 'a[href*="diverse.php?sektion=div_io"]', label: showKey('rb', 'RB2'), kind: 'button' }),
      RB3: task('rb', 'RB3', 2, { target: 'a[href*="regnskabbasis.csv"]', label: showKey('rb', 'RB3'), kind: 'button' }),
      RB4: task('rb', 'RB4', 1, null),
      RB5: task('rb', 'RB5', 3, null),
      RB6: task('rb', 'RB6', 2, { target: 'a[accesskey="L"]', label: showKey('rb', 'RB6'), kind: 'button' })
    };

    const regnskabBasis = {
      group: 'rb',
      states: ['mangler', 'klar'],
      flags: ['csvLink'],

      name: function () {
        return 'help.rb.name';
      },

      detect: function (ctx) {
        const doc = ctx.doc;
        const state = finQ(doc, '#showStandardKontoplan') ? 'mangler' : 'klar';
        return {
          state: state,
          flags: { csvLink: !!finQ(doc, 'a[href*="regnskabbasis.csv"]') },
          data: { konti: finQa(doc, 'a[href*="systemdata/kontokort.php?id="]').length }
        };
      },

      about: function (ctx, st) {
        return st && st.state === 'mangler' ? ['help.rb.about.mangler'] : ['help.rb.about.klar'];
      },

      tasks: RB_TASKS,

      order: function (ctx, st) {
        const f = (st && st.flags) || {};
        if (st && st.state === 'mangler') return ['RB1', 'RB2', 'RB5', 'RB6'];
        return [f.csvLink ? 'RB3' : 'RB4', 'RB5', 'RB6'];
      },

      fields: {},

      shortcuts: [],

      questions: {
        Q1: {
          q: 'help.rb.q.q1.q',
          words: {
            da: ['map til', 'mapning', 'standardkonto', 'standardkontoplan', 'bemærk'],
            en: ['map to', 'mapping', 'standard account', 'standard chart'],
            no: ['map til', 'tilordning', 'standardkonto', 'standardkontoplan']
          },
          intro: 'help.rb.q.q1.intro',
          steps: stepKeys('rb', 'RB1', 1, 3),
          show: RB_TASKS.RB1.show
        },
        Q2: {
          q: 'help.rb.q.q2.q',
          words: {
            da: ['periode', 'hele året', 'regnskabsår', 'tal passer ikke', 'primo'],
            en: ['period', 'whole year', 'financial year', 'figures', 'opening balance'],
            no: ['periode', 'hele året', 'regnskapsår', 'tallene stemmer ikke', 'primo']
          },
          steps: stepKeys('rb', 'RB5', 1, 3)
        }
      },

      questionOrder: function (ctx, st) {
        return st && st.state === 'mangler' ? ['Q1', 'Q2'] : ['Q2', 'Q1'];
      },

      signal: function () {
        return false;
      }
    };
    PAGES['finans/regnskabbasis.php'] = regnskabBasis;

    // ---- Kontokort, standalone (finans/kontokort_standalone.php)
    // Rendered in the frame when the user pages or changes Linjer pr. side on the Kontokort report
    // (rapport_includes/kontokort.php:1232-1330, plain links in #kontokort-pagebar); the Udskriv button opens the same
    // page with udskriv=on in a popup (:267-290, window.open), which the panel does not see. Header :325-341 (Tilbage,
    // csv, Udskriv), bilag cells open the journal in a new window :1129-1130, italic unposted rows tr.unpostedRow :1126.

    const KKS_TASKS = {
      KKS1: task('kks', 'KKS1', 1, { target: '#kontokort-pagebar select', label: fieldLabel('kks', 'per_page'), kind: 'field' }),
      KKS2: task('kks', 'KKS2', 2, { target: 'a[onclick*="kontokortprint"]', label: showKey('kks', 'KKS2'), kind: 'button' }),
      KKS3: task('kks', 'KKS3', 2, { target: 'a[href$="rapport.csv"]', label: showKey('kks', 'KKS3'), kind: 'button' }),
      KKS4: task('kks', 'KKS4', 2, { target: 'td[title^="Kladde:"][onclick]', label: showKey('kks', 'KKS4'), kind: 'button' }),
      KKS5: task('kks', 'KKS5', 2, { target: 'tr.unpostedRow', label: showKey('kks', 'KKS5'), kind: 'row' }),
      KKS6: task('kks', 'KKS6', 2, { target: 'a[accesskey="L"]', label: showKey('kks', 'KKS6'), kind: 'button' })
    };

    function kksPerPageKey(el) {
      if (el && el.tagName === 'SELECT' && !attr(el, 'name') && finClosest(el, '#kontokort-pagebar, .ledger-pagination')) return 'per_page';
      return finFieldKey(el);
    }

    const kontokortStandalone = {
      group: 'kks',
      states: ['kontokort', 'ubogfoert', 'simuleret', 'udskrift'],
      flags: ['sider', 'kladdeLink', 'kursiv', 'mangler'],

      name: function (ctx, st) {
        const state = st ? st.state : 'kontokort';
        if (state === 'ubogfoert') return 'help.kks.name_ubogfoert';
        if (state === 'simuleret') return 'help.kks.name_simuleret';
        return 'help.kks.name';
      },

      detect: function (ctx) {
        const doc = ctx.doc;
        const sim = finParam(ctx, 'simulering');
        let state = 'kontokort';
        if (finParam(ctx, 'udskriv') !== '') state = 'udskrift';
        else if (finParam(ctx, 'rapportart') === 'kontokort_ubogfort') state = 'ubogfoert';
        else if (sim !== '' && sim !== '0') state = 'simuleret';
        const flags = {
          sider: (parseInt(finParam(ctx, 'per_page'), 10) || 0) > 0,       // 0 or missing = Alle (kontokort_standalone.php:72)
          kladdeLink: !!finQ(doc, 'td[title^="Kladde:"][onclick]'),
          kursiv: !!finQ(doc, 'tr.unpostedRow'),
          mangler: !!finQ(doc, 'div[style*="#b00000"]')
        };
        return { state: state, flags: flags, data: {} };
      },

      about: function (ctx, st) {
        const state = st ? st.state : 'kontokort';
        const f = (st && st.flags) || {};
        const out = ['help.kks.about.kontokort'];
        if (state === 'ubogfoert') out.push('help.kks.about.ubogfoert');
        else if (state === 'simuleret') out.push('help.kks.about.simuleret');
        else if (f.sider) out.push('help.kks.about.sider');
        return out;
      },

      tasks: KKS_TASKS,

      order: function (ctx, st) {
        const state = st ? st.state : 'kontokort';
        const f = (st && st.flags) || {};
        if (state === 'udskrift') return [];
        const ids = [];
        if (state === 'ubogfoert' && (f.kursiv || f.mangler)) ids.push('KKS5');
        ids.push('KKS4', 'KKS2', 'KKS3');
        if (f.sider) ids.push('KKS1');
        ids.push('KKS6');
        return unique(ids);
      },

      fields: finFields('kks', ['per_page']),

      fieldKey: kksPerPageKey,

      shortcuts: [],

      questions: {
        Q1: {
          q: 'help.kks.q.q1.q',
          words: {
            da: ['udskriv', 'print', 'printe', 'alle linjer', 'kun side 1'],
            en: ['print', 'all lines', 'only page 1'],
            no: ['skriv ut', 'utskrift', 'alle linjer', 'bare side 1']
          },
          steps: stepKeys('kks', 'KKS2', 1, 2),
          show: KKS_TASKS.KKS2.show
        },
        Q2: {
          q: 'help.kks.q.q2.q',
          words: {
            da: ['csv', 'regneark', 'excel', 'mangler linjer', 'eksport'],
            en: ['csv', 'spreadsheet', 'excel', 'missing lines', 'export'],
            no: ['csv', 'regneark', 'excel', 'mangler linjer', 'eksport']
          },
          steps: ['help.kks.task.kks3.step2', 'help.kks.task.kks1.step1'],
          show: KKS_TASKS.KKS1.show
        }
      },

      questionOrder: function () {
        return ['Q1', 'Q2'];
      },

      signal: function () {
        return false;
      }
    };
    PAGES['finans/kontokort_standalone.php'] = kontokortStandalone;

    // ---- Kontokort med moms, standalone (finans/kontokort_moms_standalone.php)
    // Rendered in the frame when the user pages or changes Linjer pr. side on Kontokort med moms
    // (rapport_includes/kontokort_moms.php:528-616, .ledger-pagination). Header :183-188 (Tilbage, csv, no Udskriv),
    // hard-coded column heads :342-347, bilag cells open the journal in a new window :476-479, sums per account :512-520.

    const KKSM_TASKS = {
      KKS1: task('kks', 'KKS1', 1, { target: '.ledger-pagination select', label: fieldLabel('kks', 'per_page'), kind: 'field' }),
      KKS3: task('kks', 'KKS3', 1, { target: 'a[href$="rapport.csv"]', label: showKey('kks', 'KKS3'), kind: 'button' }),
      KKS4: task('kks', 'KKS4', 2, { target: 'td[onclick*="kassekladde.php"]', label: showKey('kks', 'KKS4'), kind: 'button' }),
      KKS6: KKS_TASKS.KKS6,
      KKS7: task('kks', 'KKS7', 3, null)
    };

    const kontokortMomsStandalone = {
      group: 'kks',
      states: ['moms'],
      flags: ['sider', 'kladdeLink'],

      name: function () {
        return 'help.kks.name_moms';
      },

      detect: function (ctx) {
        return {
          state: 'moms',
          flags: {
            sider: (parseInt(finParam(ctx, 'per_page'), 10) || 0) > 0,
            kladdeLink: !!finQ(ctx.doc, 'td[onclick*="kassekladde.php"]')
          },
          data: {}
        };
      },

      about: function (ctx, st) {
        const f = (st && st.flags) || {};
        return f.sider ? ['help.kks.about.moms', 'help.kks.about.sider'] : ['help.kks.about.moms'];
      },

      tasks: KKSM_TASKS,

      order: function (ctx, st) {
        const f = (st && st.flags) || {};
        const ids = [];
        ids.push('KKS7', 'KKS4', 'KKS3');
        if (f.sider) ids.push('KKS1');
        ids.push('KKS6');
        return unique(ids);
      },

      fields: finFields('kks', ['per_page']),

      fieldKey: kksPerPageKey,

      shortcuts: [],

      questions: {
        Q3: {
          q: 'help.kks.q.q3.q',
          words: {
            da: ['moms', 'nul moms', '0,00', 'ingen moms', 'mangler moms'],
            en: ['vat', 'zero vat', '0.00', 'no vat', 'missing vat'],
            no: ['mva', 'null mva', '0,00', 'ingen mva', 'mangler mva']
          },
          steps: stepKeys('kks', 'KKS7', 1, 3)
        },
        Q4: {
          q: 'help.kks.q.q4.q',
          words: {
            da: ['kladde', 'bilag', 'åbn', 'se bilaget', 'hvor kommer'],
            en: ['journal', 'voucher', 'open', 'where from'],
            no: ['kladd', 'bilag', 'åpne', 'hvor kommer']
          },
          steps: stepKeys('kks', 'KKS4', 1, 2),
          show: KKSM_TASKS.KKS4.show
        }
      },

      questionOrder: function () {
        return ['Q3', 'Q4'];
      },

      signal: function () {
        return false;
      }
    };
    PAGES['finans/kontokort_moms_standalone.php'] = kontokortMomsStandalone;
  }

  // ================================================================== a4: bogføring, udligning, kladdens dokumenter
  // Pages reached from the kassekladde: finans/bogfor.php (bf), finans/autoudlign.php (au), finans/journalDocuments.php (jd).
  // Not written, see a4.md: hentordrer.php (ho: Hent is never printed on a standard regnskab, and its actions call split()),
  // upload.php (AJAX endpoint without links), pulje_review.php / pulje_upload.php (pu: only link each other),
  // ordre.php, simuler.php, genberegn.php, openpostdato.php (no link, or no UI).

  /** Trimmed text of an element, '' when missing. */
  function a4Text(el) {
    return el && el.nodeType === 1 ? String(el.textContent || '').replace(/\s+/g, ' ').trim() : '';
  }

  /** querySelector on the frame document that never throws. */
  function a4Q(doc, sel) {
    try {
      const el = doc.querySelector(sel);
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function a4All(root, sel) {
    try {
      return Array.prototype.slice.call(root.querySelectorAll(sel));
    } catch (e) {
      return [];
    }
  }

  // ---- Bogfør kassekladde (finans/bogfor.php)
  // Evidence: kassekladde.php:1842/1850 send Simulér/Bogfør here with a meta refresh in the same frame; the overview is
  // bogfor.php:485-742 (Finansbevægelser :499-525, Kontrolsum :563-565, differences :592-610, Debitor-/Kreditorbevægelser
  // :649-702, buttons :737/:739), posting is bogfor():745-1142 with openpost():1144-1239 and VAT in postingRules.php:80-174.
  // Nothing is posted until the second Bogfør (:737, :287-296); Simuleret Bogføring writes the simulation table and sets
  // the journal to S (:1116-1119), which locks it until Annuller simulering (kassekladde.php:1144-1147, 4247-4276).

  /** The Finansbevægelser table (bogfor.php:499). */
  function bfTable(doc) {
    return a4Q(doc, 'table.dataTableSmall');
  }

  /** A row of the Finansbevægelser table whose six cells pass the test, or null (own rows only, not the nested table). */
  function bfRow(doc, test) {
    const t = bfTable(doc);
    if (!t || !t.rows) return null;
    for (let i = 0; i < t.rows.length; i++) {
      const c = t.rows[i].cells;
      if (c && c.length === 6 && test(c)) return t.rows[i];
    }
    return null;
  }

  /** The Kontrolsum row: empty first and third cell, label in the second, sums in Debet and Kredit (bogfor.php:565). */
  function bfKontrolsumRow(ctx) {
    return bfRow(ctx.doc, function (c) {
      return a4Text(c[0]) === '' && a4Text(c[2]) === '' && a4Text(c[1]) !== '' && a4Text(c[3]) !== '';
    });
  }

  /** The row of an account that is not in the chart of accounts; the text is hard-coded Danish (bogfor.php:522, :559). */
  function bfMissingAccountRow(ctx) {
    return bfRow(ctx.doc, function (c) { return /FINDES IKKE/.test(a4Text(c[1])); });
  }

  /** The nested "Der er differencer på følgende bilag" table (bogfor.php:593-608). */
  function bfDiffTable(ctx) {
    const t = bfTable(ctx.doc);
    return t ? a4Q(t, 'td[colspan="6"] > table') : null;
  }

  /** The header cell of Debitorbevægelser or Kreditorbevægelser, hard-coded Danish (bogfor.php:650, :687). */
  function bfPartyHeader(ctx, re) {
    const t = bfTable(ctx.doc);
    if (!t) return null;
    const cells = a4All(t, 'td.tableHeader');
    for (let i = 0; i < cells.length; i++) {
      if (re.test(a4Text(cells[i]))) return cells[i];
    }
    return null;
  }

  function bfFirstPartyHeader(ctx) {
    return bfPartyHeader(ctx, /^Debitorbev/) || bfPartyHeader(ctx, /^Kreditorbev/);
  }

  const BF_TASKS = {
    // [2] which side an expense paid from the bank lands on; [3] the VAT split of an amount with VAT, 1.250,00 at 25 percent
    // is 1.000,00 net and 250,00 VAT (postingRules.php:132-143); [4] Kontrolsum and Ny saldo (bogfor.php:499-525, 563-565).
    BF1: task('bf', 'BF1', 4, { target: bfKontrolsumRow, label: showKey('bf', 'BF1'), kind: 'row' }),
    // [3] back on the list under Bogførte kladder (bogfor.php:294-296; kladdeliste.php:715-727), account links in the posted
    // kladde (kassekladde.php:2313-2315); [4] a closed VAT month: alert, nothing posted, still sent to the list (bogfor.php:
    // 226-233, 294-296; includes/db_query.php:680-692).
    BF2: task('bf', 'BF2', 4, { target: 'input[name="bogfor"]', label: showKey('bf', 'BF2'), kind: 'button' }),
    BF3: task('bf', 'BF3', 4),
    // [2] the undo arrow lists the open journals and writes when a number is clicked; without one it only alerts 2597
    // (kassekladde.php:5107-5132). [3] Kopier til ny opens a form whose second Kopier til ny copies (copy2new.php:96-107);
    // the copy leaves out the lines' own VAT codes (:66-70, unlike the arrow, kassekladde.php:5131), so posting falls back
    // to the account's code (postingRules.php:94-99): [4] says check Moms.
    BF4: task('bf', 'BF4', 4),
    BF5: task('bf', 'BF5', 3, { target: bfFirstPartyHeader, label: showKey('bf', 'BF5'), kind: 'status' }),
    BF6: task('bf', 'BF6', 3, { target: bfDiffTable, label: showKey('bf', 'BF6'), kind: 'status' }),
    // BF7 is the same table when the journal balances in total but single vouchers do not (bogfor.php:592-610): only a
    // total difference hides the buttons (:609), so such a journal can be posted.
    BF7: task('bf', 'BF7', 0, { target: bfDiffTable, label: showKey('bf', 'BF6'), kind: 'status' },
      stepKeys('bf', 'BF6', 1, 1).concat(['help.bf.task.bf7.step2', 'help.bf.task.bf7.step3'])),
    BF8: task('bf', 'BF8', 3, { target: bfMissingAccountRow, label: showKey('bf', 'BF8'), kind: 'row' }),
    BF9: task('bf', 'BF9', 4, { target: 'input[name="simuler"]', label: showKey('bf', 'BF9'), kind: 'button' }),
    BF10: task('bf', 'BF10', 2, { target: 'a[accesskey="L"]', label: showKey('bf', 'BF10'), kind: 'button' }),
    // [2] Manglende modpostering i bilag N (bogfor.php:1036-1039) and [4] Der er konstateret en afvigelse (:1133-1136) are
    // hard-coded Danish, so the steps quote them unbolded in every language.
    BF11: task('bf', 'BF11', 4, { target: 'a[accesskey="L"]', label: showKey('bf', 'BF10'), kind: 'button' })
  };

  const bogforPage = {
    group: 'bf',
    states: ['bekraeft', 'simuler', 'fejl', 'stoppet', 'videre'],
    flags: ['difference', 'bilagDiff', 'kontoMangler', 'debitorer', 'kreditorer'],

    name: function (ctx, st) {
      const d = (st && st.data) || {};
      const id = String(d.kladdeId || '');
      if (!id) return 'help.bf.name_plain';
      return [d.funktion === 'bogfor' ? 'help.bf.name' : 'help.bf.name_sim', id];
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const url = ctx.url;
      const funktion = url.searchParams.get('funktion') || '';
      const table = bfTable(doc);
      let state;
      // A meta refresh is printed when the page sends the user on: after Bogfør/Simuleret Bogføring/Tilbage
      // (bogfor.php:295-303), or before any overview (already posted :129-132, unfinished lines :138-141).
      if (a4Q(doc, 'meta[http-equiv="refresh" i]')) state = 'videre';
      else if (!table) state = 'stoppet';                         // bogfor() stopped with alert + exit (:1038-1040, :1102-1104, :1137-1139)
      else if (a4Q(doc, 'input[name="bogfor"]')) state = 'bekraeft';
      else if (a4Q(doc, 'input[name="simuler"]')) state = 'simuler';
      else state = 'fejl';                                        // $fejl hides the buttons (:592-622)
      const diffTable = table ? bfDiffTable(ctx) : null;
      const idInput = a4Q(doc, 'input[name="kladde_id"]');
      const flags = {
        difference: state === 'fejl' && !!diffTable,
        bilagDiff: (state === 'bekraeft' || state === 'simuler') && !!diffTable,
        kontoMangler: !!(table && bfMissingAccountRow(ctx)),
        debitorer: !!(table && bfPartyHeader(ctx, /^Debitorbev/)),
        kreditorer: !!(table && bfPartyHeader(ctx, /^Kreditorbev/))
      };
      const data = {
        funktion: funktion,
        kladdeId: url.searchParams.get('kladde_id') || (idInput ? String(idInput.value || '').trim() : '')
      };
      return { state: state, flags: flags, data: data };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const out = [];
      if (state === 'bekraeft' || state === 'simuler') {
        out.push(state === 'bekraeft' ? 'help.bf.about.bekraeft' : 'help.bf.about.simuler');
        if (f.kontoMangler) out.push('help.bf.about.konto_mangler');
        else if (f.bilagDiff) out.push('help.bf.about.bilag_diff');
      } else if (state === 'fejl') {
        if (f.difference) out.push('help.bf.about.fejl_difference');
        if (f.kontoMangler) out.push('help.bf.about.konto_mangler');
        if (!out.length) out.push('help.bf.about.fejl');
      } else if (state === 'stoppet') {
        out.push('help.bf.about.stoppet');
      } else if (state === 'videre') {
        out.push('help.bf.about.videre');
      }
      return out;
    },

    tasks: BF_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state === 'fejl') {
        if (f.difference) ids.push('BF6');
        if (f.kontoMangler) ids.push('BF8');
        if (!f.difference && !f.kontoMangler) ids.push('BF11');
      } else if (state === 'stoppet') {
        ids.push('BF11');
      } else if (state === 'bekraeft') {
        if (f.kontoMangler) ids.push('BF8');
        if (f.bilagDiff) ids.push('BF7');
        ids.push('BF1', 'BF2');
        if (f.debitorer || f.kreditorer) ids.push('BF5');
        ids.push('BF3', 'BF4');
      } else if (state === 'simuler') {
        // Bogfør kladden i stedet before Gem en simuleret bogføring: a user who meant to post goes back instead of
        // locking the journal with Simuleret Bogføring (PLAN D-29).
        if (f.kontoMangler) ids.push('BF8');
        if (f.bilagDiff) ids.push('BF7');
        ids.push('BF1', 'BF10', 'BF9');
        if (f.debitorer || f.kreditorer) ids.push('BF5');
      }
      return unique(ids);
    },

    // Bemærkning (bogfor.php:489), only on the Bogfør overview; Enter in it submits the form with Bogfør, the default button.
    fields: { kladdenote: fieldKeys('bf', 'kladdenote') },

    shortcuts: [
      { keys: 'Alt+B', text: 'help.bf.key.01', group: 'keys' },     // accesskey b on Bogfør / Simuleret Bogføring (:737, :739)
      { keys: 'Alt+L', text: 'help.bf.key.02', group: 'keys' },     // accesskey L/l on Tilbage (:146-150, :737, :739)
      { keys: 'Enter', text: 'help.bf.key.03', group: 'keys' }      // implicit submission from the Bemærkning field (:489)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.bf.q.q1.q',
        words: {
          // 'kan ikke bogføre': on the error overview (no Bogfør button) the answer is the Kontrolsum and the accounts.
          da: ['kontrolsum', 'kontrolsummen', 'sum', 'summen', 'debet', 'kredit', 'ens', 'kan ikke bogføre'],
          en: ['checksum', 'check sum', 'total', 'debit', 'credit', 'equal', 'cannot post', "can't post"],
          no: ['sjekksum', 'sjekksummen', 'sum', 'summen', 'debet', 'kredit', 'like', 'kan ikke bokføre']
        },
        intro: 'help.bf.q.q1.intro',
        steps: ['help.bf.q.q1.step1', 'help.bf.q.q1.step2'],
        show: BF_TASKS.BF1.show
      },
      Q2: {
        q: 'help.bf.q.q2.q',
        words: {
          // No 'modpostering' in da: the stop message Manglende modpostering found this question (BF11 answers it); the
          // intro still has the word, so a bookkeeper's search for it still finds Q2.
          da: ['fortryd', 'fortryde', 'slet', 'slette', 'tilbagefør', 'tilbageføre', 'rette', 'fejl'],
          en: ['undo', 'delete', 'reverse', 'reversal', 'correct', 'mistake'],
          no: ['angre', 'slett', 'slette', 'tilbakefør', 'tilbakeføre', 'rette', 'feil', 'motpostering']
        },
        intro: 'help.bf.q.q2.intro',
        steps: stepKeys('bf', 'BF4', 1, 4)
      },
      Q3: {
        q: 'help.bf.q.q3.q',
        words: {
          da: ['moms', 'momsen', 'momskonto', 'købsmoms', 'salgsmoms', 'netto', 'omvendt betalingspligt', 'tre linjer',
            'flere linjer', 'ekstra linje', 'hvorfor står', 'eu', 'udlandet', 'to gange', 'dobbelt', 'for meget moms'],
          en: ['vat', 'tax', 'input vat', 'output vat', 'net', 'reverse charge', 'three lines', 'extra line', 'eu', 'abroad',
            'twice', 'double', 'too much vat'],
          no: ['mva', 'mvaen', 'mva-konto', 'inngående', 'utgående', 'netto', 'omvendt avgiftsplikt', 'tre linjer', 'ekstra linje',
            'eu', 'utlandet', 'to ganger', 'dobbel', 'for mye mva']
        },
        // [4] VAT typed on a line of its own as well (PLAN §0 step 2): the amount on a line includes VAT (postingRules.php:132-143).
        steps: stepKeys('bf', 'BF3', 1, 2).concat(['help.bf.q.q3.step3', 'help.bf.q.q3.step4'])
      },
      // Nothing is posted yet (bogfor.php:146-150 Tilbage, :294-296 to the list after posting, :737 Bogfør, :739 the
      // simulation's own button).
      Q4: {
        q: 'help.bf.q.q4.q',
        words: {
          da: ['bogført nu', 'er det bogført', 'er kladden bogført', 'gemt', 'hvad sker der', 'færdig'],
          en: ['is it posted', 'posted now', 'saved'],
          no: ['bokført nå', 'er det bokført', 'lagret']
        },
        intro: 'help.bf.q.q4.intro',
        steps: ['help.bf.q.q4.step1', 'help.bf.q.q4.step2', 'help.bf.q.q4.step3'],
        show: BF_TASKS.BF2.show
      },
      // The accounts Saldi adds: VAT (postingRules.php:100-143), the customer or supplier group's control account
      // (gruppeopslag(), postingRules.php:185-205), the party tables at the bottom (bogfor.php:627-702).
      Q5: {
        q: 'help.bf.q.q5.q',
        words: {
          da: ['konto jeg ikke har skrevet', 'ekstra konto', 'samlekonto', 'momskonto', 'hvorfor står', 'tilgodehavender',
            'leverandørgæld'],
          en: ['extra account', 'control account'],
          no: ['ekstra konto', 'samlekonto']
        },
        intro: 'help.bf.q.q5.intro',
        steps: ['help.bf.q.q5.step1', 'help.bf.q.q5.step2', 'help.bf.q.q5.step3']
      }
    },

    // Nothing is posted on a refused or stopped overview, so Q2 (undo a posting) never leads there (spec §25).
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      if (state === 'fejl') return ['Q1', 'Q3', 'Q5'];
      if (state === 'stoppet') return ['Q1', 'Q2', 'Q3'];
      if (state === 'bekraeft' || state === 'simuler') return ['Q4', 'Q5', 'Q3'];
      return ['Q2', 'Q1', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['finans/bogfor.php'] = bogforPage;

  // ---- Udlign (finans/autoudlign.php)
  // Evidence: kassekladde.php:4300 Udlign (name offset) → :1894 meta refresh to autoudlign.php in the same frame; one line
  // per page (.entry-card :765-871), done card :730-757; Afregn only writes account and Fakturanr. on the line
  // (autoSettlement.php:152-200); the open item closes when the journal is posted (bogfor.php:858-863, :1183-1227).

  const AU_TASKS = {
    // AU1 finds and AU2 picks the open item: Vis mig rings the list of open items (.candidates-wrap, :823-838), which the
    // titles name, not Afregn, the step after the pick (Vis mig audit, spec §21)
    AU1: task('au', 'AU1', 4, { target: '.candidates-wrap', label: showKey('au', 'AU1'), kind: 'status' }),
    AU2: task('au', 'AU2', 0, { target: '.candidates-wrap', label: showKey('au', 'AU1'), kind: 'status' },
      ['help.au.task.au2.step1'].concat(stepKeys('au', 'AU1', 3, 3), ['help.au.task.au2.step3', 'help.au.task.au2.step4'])),
    AU3: task('au', 'AU3', 3, { target: '.entry-amount', label: showKey('au', 'AU3'), kind: 'status' }),
    AU4: task('au', 'AU4', 2, { target: '#skipBtn', label: showKey('au', 'AU4'), kind: 'button' }),
    AU5: task('au', 'AU5', 2, { target: '#accountSelect:not([disabled])', label: fieldLabel('au', 'konto'), kind: 'field' }),
    AU6: task('au', 'AU6', 2, { target: '.done-card a[href*="skipped=0"]', label: showKey('au', 'AU6'), kind: 'button' }),
    AU7: task('au', 'AU7', 3, { target: '.done-card a[href^="kassekladde.php"]', label: showKey('au', 'AU7'), kind: 'button' }),
    AU8: task('au', 'AU8', 0, { target: '.done-card a[href^="kassekladde.php"]', label: showKey('au', 'AU7'), kind: 'button' },
      ['help.au.task.au8.step1', 'help.au.task.au8.step2'].concat(stepKeys('au', 'AU7', 1, 1)))
  };

  const autoudlignPage = {
    group: 'au',
    states: ['linje', 'sprunget', 'faerdig', 'ingen', 'ukendt'],
    flags: ['kontoLaast', 'sprunget', 'kontotype'],

    name: function (ctx, st) {
      const id = st && st.data ? String(st.data.kladdeId || '') : '';
      return id ? ['help.au.name', id] : 'help.au.name_plain';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const url = ctx.url;
      const num = function (k) { const n = parseInt(url.searchParams.get(k) || '0', 10); return isNaN(n) ? 0 : n; };
      const select = a4Q(doc, '#accountSelect');
      let state;
      if (a4Q(doc, '.entry-card')) state = 'linje';
      else if (a4Q(doc, '.done-card a[href*="skipped=0"]')) state = 'sprunget';   // restart link only when lines were skipped (:736-745)
      else if (a4Q(doc, '.done-card')) state = num('settled') > 0 ? 'faerdig' : 'ingen';   // 5314 vs 5315 (:750-752)
      else state = 'ukendt';
      const kt = String(url.searchParams.get('kontotype') || '').toUpperCase();
      const flags = {
        // disabled when the line already names a customer or supplier (:794)
        kontoLaast: !!(select && (select.disabled === true || select.hasAttribute('disabled'))),
        sprunget: num('skipped') > 0,
        kontotype: kt === 'D' || kt === 'K'
      };
      return { state: state, flags: flags, data: { kladdeId: url.searchParams.get('kladde_id') || '' } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'linje') return ['help.au.about.linje', f.kontoLaast ? 'help.au.about.konto_laast' : 'help.au.about.afregn'];
      if (state === 'sprunget') return ['help.au.about.sprunget'];
      if (state === 'faerdig') return ['help.au.about.faerdig'];
      if (state === 'ingen') return ['help.au.about.ingen'];
      return [];
    },

    tasks: AU_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'linje') return f.kontoLaast ? ['AU2', 'AU3', 'AU4'] : ['AU1', 'AU3', 'AU4', 'AU5'];
      if (state === 'sprunget') return ['AU6', 'AU7'];
      if (state === 'faerdig') return ['AU7'];
      if (state === 'ingen') return ['AU8'];
      return [];
    },

    // Both controls have only an id (accountSelect :794, searchInput :816), so the default rule would give mixed-case keys.
    fieldKey: function (el) {
      const id = el && el.nodeType === 1 ? (el.id || '') : '';
      if (id === 'accountSelect') return 'konto';
      if (id === 'searchInput') return 'soeg';
      return null;
    },

    fields: { konto: fieldKeys('au', 'konto'), soeg: fieldKeys('au', 'soeg') },

    // The page's own key handler (:1275-1301) and the Tilbage link's accesskey (:693).
    shortcuts: [
      { keys: '↑ ↓', text: 'help.au.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.au.key.02', group: 'keys' },
      { keys: 'Tab', text: 'help.au.key.03', group: 'keys' },
      { keys: 'Esc', text: 'help.au.key.04', group: 'keys' },
      { keys: 'Alt+L', text: 'help.au.key.05', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.au.q.q1.q',
        words: {
          da: ['afregn', 'afregne', 'lukke', 'lukket', 'betalt', 'åben post', 'åbne poster'],
          en: ['settle', 'settled', 'close', 'closed', 'paid', 'open item'],
          no: ['avregn', 'avregne', 'lukke', 'lukket', 'betalt', 'åpen post', 'åpne poster']
        },
        intro: 'help.au.q.q1.intro',
        steps: stepKeys('au', 'AU3', 2, 3)
      },
      Q2: {
        q: 'help.au.q.q2.q',
        words: {
          da: ['finder ikke', 'kan ikke finde', 'mangler', 'ingen match', 'faktura', 'fakturaen'],
          en: ['cannot find', "can't find", 'missing', 'no match', 'invoice'],
          no: ['finner ikke', 'kan ikke finne', 'mangler', 'ingen treff', 'faktura', 'fakturaen']
        },
        intro: 'help.au.q.q2.intro',
        steps: ['help.au.q.q2.step1', 'help.au.q.q2.step2', 'help.au.q.q2.step3'],
        show: { target: '#searchInput', label: fieldLabel('au', 'soeg'), kind: 'field' }
      }
    },

    // On a line, after skipped lines and with nothing to settle, the invoice that is not in the list leads (spec §25).
    questionOrder: function (ctx, st) {
      return st && st.state === 'faerdig' ? ['Q1', 'Q2'] : ['Q2', 'Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['finans/autoudlign.php'] = autoudlignPage;

  // ---- Dokumenter til kladden (finans/journalDocuments.php)
  // Evidence: the clip next to Bemærkning (kassekladde.php:3006, journalDocumentsFunc.php:90-114) opens it with
  // confirmClose() → document.location in the same frame (confirmclose.js:2-9); list #journalDocsList, upload
  // #journalDocsUpload, viewer #journalDocsView (journalDocuments.php:142-171).

  const JD_TASKS = {
    JD1: task('jd', 'JD1', 3, { target: '#journalDocsUpload input[name="uploadedFile"]', label: fieldLabel('jd', 'fil'), kind: 'field' }),
    JD2: task('jd', 'JD2', 1, { target: '#journalDocsList a', label: showKey('jd', 'JD2'), kind: 'row' }),
    JD3: task('jd', 'JD3', 2, { target: '#journalDocsList input[type="submit"]', label: showKey('jd', 'JD3'), kind: 'button' }),
    JD4: task('jd', 'JD4', 2, { target: 'a[accesskey="L"]', label: showKey('jd', 'JD4'), kind: 'button' })
  };

  const journalDocumentsPage = {
    group: 'jd',
    states: ['tom', 'liste'],
    flags: ['kanSlettes', 'laast'],

    name: function (ctx, st) {
      const id = st && st.data ? String(st.data.kladdeId || '') : '';
      return id ? ['help.jd.name', id] : 'help.jd.name_plain';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const list = a4Q(doc, '#journalDocsList');
      const rows = list ? a4All(list, 'tr') : [];
      let withDelete = 0;
      for (let i = 0; i < rows.length; i++) {
        if (a4Q(rows[i], 'input[name="deleteDoc"]')) withDelete++;
      }
      const flags = {
        kanSlettes: withDelete > 0,
        // A row without Slet: the journal is posted and the document is older than 24 hours (journalDocuments.php:148-149)
        laast: rows.length > withDelete
      };
      return { state: rows.length ? 'liste' : 'tom', flags: flags, data: { kladdeId: ctx.url.searchParams.get('kladde_id') || '', rows: rows.length } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const out = ['help.jd.about.side'];
      if (state === 'tom') out.push('help.jd.about.tom');
      else if (f.laast) out.push('help.jd.about.laast');
      return out;
    },

    tasks: JD_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'tom') return ['JD1', 'JD4'];
      const ids = ['JD1', 'JD2'];
      if (f.kanSlettes || f.laast) ids.push('JD3');
      ids.push('JD4');
      return ids;
    },

    // The file input is name="uploadedFile" (journalDocuments.php:157); the default rule would keep the capital F.
    fieldKey: function (el) {
      return attr(el, 'name') === 'uploadedFile' ? 'fil' : null;
    },

    fields: { fil: fieldKeys('jd', 'fil') },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.jd.key.01', group: 'keys' }       // accesskey L on Tilbage (journalDocuments.php:122, :127, :132)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.jd.q.q1.q',
        words: {
          da: ['kontoudtog', 'kontoudtoget', 'bankudtog', 'vedhæft', 'vedhæfte', 'dokument', 'pdf'],
          en: ['bank statement', 'statement', 'attach', 'document', 'pdf'],
          no: ['kontoutskrift', 'kontoutskriften', 'bankutskrift', 'legg ved', 'dokument', 'pdf']
        },
        steps: stepKeys('jd', 'JD1', 1, 2),
        show: JD_TASKS.JD1.show
      },
      Q2: {
        q: 'help.jd.q.q2.q',
        words: {
          da: ['slet', 'slette', 'kan ikke slette', 'låst', 'fjern'],
          en: ['delete', 'cannot delete', "can't delete", 'locked', 'remove'],
          no: ['slett', 'slette', 'kan ikke slette', 'låst', 'fjern']
        },
        intro: 'help.jd.q.q2.intro',
        steps: ['help.jd.q.q2.step1'],
        // [1] attaches the right document instead; the document in question shows no Slet, so another document's Slet
        // would be the wrong thing to ring (Vis mig audit, spec §21)
        show: JD_TASKS.JD1.show
      }
    },

    questionOrder: function () {
      return ['Q1', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['finans/journalDocuments.php'] = journalDocumentsPage;

  // ---- Kassebilag (includes/documents.php)
  // Evidence: one route for every mode of the voucher page (title 1408 Kassebilag, topLineDocuments.php:61). The kassekladde's
  // clip on a line without a voucher saves the kladde and opens the pool in the frame (kassekladde.php:646-666, 3719-3742,
  // 4009-4021; documents.php:976-994 -> docPool()), the paper icon opens a separate view-only tab the panel cannot reach
  // (kassekladde.php:684-697; documents.php:152-217), and its Administrér bilag brings the line's viewer into the frame
  // (documents.php:663-972). Link page documents.php:421-655; order pools from debitor/ordre.php:4330 and kreditor orders.
  // The pool switches documents in place and fires "poolswitch" (docPoolSwitch.js:151); the panel reads the state again then
  // (saldi-help.js:1931). The list, the reading marks and the warnings arrive after the page by fetch (docPool.php:3316,
  // poolCapture.js, invoiceReuse.js:152-210), so Om siden never depends on them; only the order does.
  // Labels: the pool prints findtekst() names; the viewer's Dokumentpulje, Link bilag fra anden linje, Flyt til pulje,
  // Fjern link, Slet and Låst and the link page's Link are hard-coded Danish (documents.php:939-943, 494-551; listDocs.php:176-198).

  /** The voucher number at the top of the pool (docPool.php:2247), else the URL's bilag (kassekladde.php:3726). */
  function kbBilag(ctx) {
    const el = a4Q(ctx.doc, '#bilagRowsContainer input[id$="_Bilag"]');
    const v = el ? String(el.value || '').trim() : '';
    return v || String(ctx.url.searchParams.get('bilag') || '').trim();
  }

  /** The document shown in the pool's viewer: the last non-empty poolFile of the URL (docPoolSaveNext.js:52-58). */
  function kbCurrentFile(ctx) {
    const all = ctx.url.searchParams.getAll('poolFile');
    for (let i = all.length - 1; i >= 0; i--) {
      if (String(all[i] || '').trim() !== '') return String(all[i]);
    }
    return '';
  }

  /** The list row of the shown document (data-pool-file, docPool.php:3593-3599), or null. */
  function kbCurrentRow(ctx) {
    const file = kbCurrentFile(ctx);
    if (!file) return null;
    const rows = a4All(ctx.doc, '#fileListContainer tr[data-pool-file]');
    for (let i = 0; i < rows.length; i++) {
      if (attr(rows[i], 'data-pool-file') === file) return rows[i];
    }
    return null;
  }

  /** Mulig dublet on the shown document, else the first in the list (docPool.php:3557-3564). */
  function kbDuplicateBadge(ctx) {
    const row = kbCurrentRow(ctx);
    return (row ? a4Q(row, '.duplicate-badge') : null) || a4Q(ctx.doc, '#fileListContainer .duplicate-badge');
  }

  /** The first Aflæst mark on the line, else the Brug forslag button under Debet (poolCapture.js:95-104, 252-262). */
  function kbReadMark(ctx) {
    return a4Q(ctx.doc, '#kassebilagTopBar .pool-capture-badge') || a4Q(ctx.doc, '#kassebilagTopBar .pool-suggest-use');
  }

  /** The upload section is folded away: maxHeight 0, which the browser reads back as 0px (docPool.php:5455-5494). Its content
   *  keeps its size inside the clipped box, so the shell's visibility check would still pass. */
  function kbUploadFolded(ctx) {
    const content = a4Q(ctx.doc, '#uploadContent');
    return !!content && !!content.style && /^0(px)?$/.test(String(content.style.maxHeight || ''));
  }

  /** The drop area, or the header that opens it when the upload section is folded. */
  function kbUploadTarget(ctx) {
    return (kbUploadFolded(ctx) ? null : a4Q(ctx.doc, '#dropZone')) || a4Q(ctx.doc, '#uploadToggleHeader');
  }

  /** The pool's Link bilag fra anden linje sits in the upload section (docPool.php:5428-5437): the header while it is folded. */
  function kbLinkTarget(ctx) {
    const link = a4Q(ctx.doc, 'a[href*="linkBilag=1"]');
    if (!link) return null;
    return kbUploadFolded(ctx) && a4Q(ctx.doc, '#uploadContent a[href*="linkBilag=1"]') ? a4Q(ctx.doc, '#uploadToggleHeader') : link;
  }

  /** Tilbage til kassekladden after the last document (docPoolSaveNext.js:432-446), else Tilbage (topLineDocuments.php:52-55). */
  function kbBackLink(ctx) {
    return a4Q(ctx.doc, '#leftPanel .pool-done-note a') || a4Q(ctx.doc, '#topBarHeader a[accesskey="L"]');
  }

  /** The coloured match headings, a tr in the table view and a div in the card view; both call selectCombinationFiles() with the
   *  whole group (docPool.php:3620-3681, 3811-3841, 4457-4480). The rows carry data-*-match only in the table view. */
  const KB_MATCH_HEADING = '#fileListContainer [onclick^="selectCombinationFiles"]';

  const KB_TASKS = {
    // The pool from a saved line: view, tick, Indsæt valgte (in #bulkActionsContainer, shown once a box is ticked,
    // docPool.php:3690, 4483-4497); only the lines ticked at the top get the voucher and only the source line is ticked
    // (docPool.php:2242-2246, 4169-4181; insertDoc.php:573-596), then back to the kladde (insertDoc.php:646-668)
    KB1: task('kb', 'KB1', 3, { target: '#fileListContainer', label: showKey('kb', 'KB1'), kind: 'status' }),
    // Match groups from the line's amount and date (poolListQuery.php:90-177); the coloured heading unticks the rest and ticks the
    // whole group, a Dato match group too (docPool.php:4457-4480), so step 3 says to untick what does not belong
    KB2: task('kb', 'KB2', 3, { target: KB_MATCH_HEADING, label: showKey('kb', 'KB2'), kind: 'row' }),
    // Overfør data asks first, then overwrites amount, date, invoice number and text (docPool.php:6576-6832, onlyEmpty false :6806)
    KB3: task('kb', 'KB3', 4, { target: '#transferDataBtn', label: showKey('kb', 'KB3'), kind: 'button' }),
    // A click on a new line opens the document with its data (docPoolSaveNext.js:366-372, transfer :469-486); Gem og næste saves,
    // attaches and opens the next document on a new line (:268-320); Enter does the same (:396-404)
    KB4: task('kb', 'KB4', 4, { target: '#saveNextBtn', label: showKey('kb', 'KB4'), kind: 'button' }),
    // A new line's Kredit type is K (docPool.php:2254); a K side gets an open item on posting (bogfor.php:856-863)
    KB5: task('kb', 'KB5', 3, { target: '#row_new_KreditType', label: fieldLabel('kb', 'k_ty'), kind: 'field' }),
    // Aflæst, Forslag and Brug forslag (poolCapture.js:51-68, 228-262); the pencil marks the pool row Rettet / accepteret
    // (docPool.php:3588-3589, 4511-4582; poolMetadata.php:60-62)
    KB6: task('kb', 'KB6', 4, { target: kbReadMark, label: showKey('kb', 'KB6'), kind: 'status' }),
    // Fordeling copies bilag, date, invoice, text, Kredit and currency and follows the rest until its amount is typed;
    // without a captured amount the total is the first row's Beløb at the click, an empty one counts as 0 (docPoolSplit.js:66-71, 172);
    // a second Fordeling leaves the row before at the rest and starts at 0 (:95-103, 199-200);
    // an unbalanced split warns on the first Enter and saves on the second (docPoolSplit.js:65-116, 144-233)
    KB7: task('kb', 'KB7', 4, { target: '#splitRowBtn', label: showKey('kb', 'KB7'), kind: 'button' }),
    // Valuta is not transferred (docPool.php:6721-6741); Gem og næste saves the rows before the attach, so the pool's currency is
    // never used (docPoolSaveNext.js:295-307; docPool.php:1150 only for an unsaved line); an empty or unknown code is stored as 0
    // (insertDoc.php:274-286); the rate of the line's date is used on posting (bogfor.php:1241-1262)
    KB8: task('kb', 'KB8', 3, { target: '#kassebilagTopBar [id$="_Valuta"]', label: fieldLabel('kb', 'valuta'), kind: 'field' }),
    // Upload area (docPool.php:5221-5243), allowed types, images stored as PDF, a copy refused, renamed after the scan (poolUpload.php:342-410)
    KB9: task('kb', 'KB9', 3, { target: kbUploadTarget, label: showKey('kb', 'KB9'), kind: 'status' }),
    // LIVE L17: the mail address (docPool.php:5221-5228); a mailed file is read by Opdatér alle (docPool.php:2561)
    // The pool's mail address under Bilag kan sendes til, in the upload header (docPool.php:5221-5228); the header itself
    // also holds Vælg bilag fra enhed, so it is only the fallback (Vis mig audit, spec §21)
    KB10: task('kb', 'KB10', 3, {
      target: function (ctx) { return a4Q(ctx.doc, '#uploadToggleHeader a[href^="mailto:"]') || a4Q(ctx.doc, '#uploadToggleHeader'); },
      label: showKey('kb', 'KB10'),
      kind: 'status'
    }),
    // Arkivér, Arkivér valgte, Delete outside a field (docPoolArchive.js:39-50, 160-186, 211-220); purge after 12 months (poolArchive.php:166-172)
    KB11: task('kb', 'KB11', 4, { target: '#archiveSelectedBtn', label: showKey('kb', 'KB11'), kind: 'button' }),
    // In the archive the button reads Gendan valgte (docPoolArchive.js:170-180)
    KB12: task('kb', 'KB12', 2, { target: '#archiveSelectedBtn', label: showKey('kb', 'KB12'), kind: 'button' }),
    // Obligatorisk, the amount format, the account check and the out-of-year date (docPoolSaveNext.js:178-216, 334-348;
    // insertDoc.php:187-204; poolAccountInfo.php:258-270)
    KB13: task('kb', 'KB13', 4, { target: '#kassebilagTopBar .pool-mandatory', label: showKey('kb', 'KB13'), kind: 'field' }),
    // Same fakturanr, amount and date under two names (poolDuplicateMarker.php:24-60)
    KB14: task('kb', 'KB14', 3, { target: kbDuplicateBadge, label: showKey('kb', 'KB14'), kind: 'status' }),
    // The first Enter goes to the warning, the next saves (invoiceReuse.js:30-62, 152-210); the message names an open kladde line,
    // a posted invoice or another pool document (finans/kassekladde_includes/invoiceReuse.php:120-162, 178-200, texts 5287-5289)
    KB15: task('kb', 'KB15', 4, { target: '#kassebilagTopBar .invoice-reuse-note', label: showKey('kb', 'KB15'), kind: 'status' }),
    // The pool: no unsaved-changes guard (topLineDocuments.php:52-55; docPool.php:2384-2391; no beforeunload in docPool*.js)
    KB16: task('kb', 'KB16', 3, { target: kbBackLink, label: showKey('kb', 'KB16'), kind: 'button' }),
    // The viewer's list (listDocs.php:153-199)
    KB17: task('kb', 'KB17', 1, { target: 'tr.doc-list-row:not(.current-doc)', label: showKey('kb', 'KB17'), kind: 'row' }),
    KB18: task('kb', 'KB18', 2, { target: '#leftPanelContent a[href*="openPool=1"]', label: showKey('kb', 'KB18'), kind: 'button' }),
    // Flyt til pulje moves the file back with an empty pool row, no new reading (moveDoc.php:83-125, 139-140); viewer Slet deletes the file (deleteDoc.php:129-132)
    KB19: task('kb', 'KB19', 3, { target: 'tr.doc-list-row a[href*="moveDoc="]', label: showKey('kb', 'KB19'), kind: 'button' }),
    KB20: task('kb', 'KB20', 2, { target: 'tr.doc-list-row a[href*="unlinkDoc="]', label: showKey('kb', 'KB20'), kind: 'button' }),
    // The pool's link (text 3332, docPool.php:5428-5437); the link page (documents.php:494-555); drag in the kladde (kassekladde.php:5890-6110)
    KB21: task('kb', 'KB21', 4, { target: kbLinkTarget, label: showKey('kb', 'KB21'), kind: 'button' }),
    // LIVE L15 for step 4: the link page has no Tilbage ($backUrl :434 is never printed)
    KB22: task('kb', 'KB22', 4, { target: '#searchBilag', label: fieldLabel('kb', 'searchbilag'), kind: 'field' }),
    // LIVE L18: an order's pool attaches to the order and shows its viewer (insertDoc.php:655-657)
    KB23: task('kb', 'KB23', 2, { target: '#fileListContainer', label: showKey('kb', 'KB1'), kind: 'status' }),
    // The viewer's and the read-only pool's Tilbage goes to the kladde line (documents.php:691-708; topLineDocuments.php:33-34, 52-55);
    // in the kladde the paper icon opens the line's bilag in a tab, the clip the pool (kassekladde.php:646-694, 3719-3742)
    KB24: task('kb', 'KB24', 2, { target: '#topBarHeader a[accesskey="L"]', label: showKey('kb', 'KB24'), kind: 'button' }),
    // The viewer's own link button, hard-coded Danish (documents.php:942-943); the rest as KB21
    KB25: task('kb', 'KB25', 1, { target: '#leftPanelContent a[href*="linkBilag=1"]', label: showKey('kb', 'KB25'), kind: 'button' },
      stepKeys('kb', 'KB21', 2, 4))
  };

  // The pool's row fields have ids row_<row>_<Suffix> (docPool.php:2234-2295, split rows row_new2_… docPoolSplit.js:118-131);
  // the type and account inputs also carry d_ty0/debe0 names, the other fields only ids.
  const KB_ROW_FIELDS = {
    Bilag: 'bilag', Dato: 'dato', Faktura: 'faktura', Beskrivelse: 'beskrivelse', DebetType: 'd_ty', Debet: 'debe', DebetVat: 'dvat',
    KreditType: 'k_ty', Kredit: 'kred', KreditVat: 'kvat', Amount: 'amount', Afd: 'afd', Projekt: 'projekt', Valuta: 'valuta',
    Momsfri: 'momsfri', Forfald: 'forfald'
  };
  // Fields with only an id in camel case (docPool.php:2548, 2555; documents.php:504): the shell's default would keep the capitals
  // (saldi-help.js:314-326 returns n.id as it is).
  const KB_ID_FIELDS = { poolSearchBox: 'poolsearchbox', autoExtractToggle: 'autoextracttoggle', searchBilag: 'searchbilag' };

  const KB_FIELDS = {};
  ['bilag', 'dato', 'faktura', 'beskrivelse', 'debe', 'kred', 'd_ty', 'k_ty', 'dvat', 'kvat', 'amount', 'afd', 'projekt', 'valuta',
    'momsfri', 'forfald', 'poolsearchbox', 'autoextracttoggle', 'searchbilag']
    .forEach(function (k) { KB_FIELDS[k] = fieldKeys('kb', k); });

  const KB_ABOUT = {
    visning: 'help.kb.about.visning',
    ordrePulje: 'help.kb.about.ordre_pulje',
    klassisk: 'help.kb.about.klassisk',
    skifter: 'help.kb.about.skifter'
  };

  const kassebilagPage = {
    group: 'kb',
    states: ['pulje', 'ordrePulje', 'bilag', 'ordreBilag', 'link', 'visning', 'klassisk', 'skifter'],
    flags: ['nyLinje', 'fraLinje', 'laast', 'soegning', 'arkiv', 'tom', 'faerdig', 'match', 'dublet', 'fakturaBrugt', 'forslag',
      'skaev', 'obligatorisk', 'flere', 'linket', 'laastBilag', 'mangler', 'kanLinke', 'tomListe'],

    name: function (ctx, st) {
      const d = (st && st.data) || {};
      if (d.ordre) return 'help.kb.name_ordre';
      return d.bilag ? ['help.kb.name_bilag', String(d.bilag)] : 'help.kb.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const url = ctx.url;
      const source = String(url.searchParams.get('source') || '');
      const ordre = source === 'debitorOrdrer' || source === 'creditorOrder';
      const viewer = a4Q(doc, '#docViewerContainer');
      const rows = viewer ? a4All(viewer, 'tr.doc-list-row') : [];
      const shown = viewer ? a4Q(viewer, '#documentViewer') : null;
      let state;
      if (a4Q(doc, '#viewOnlyBar')) state = 'visning';                       // documents.php:170 (a separate tab)
      else if (a4Q(doc, '#bilagList')) state = 'link';                       // documents.php:507
      // The viewer prints its list and #documentViewer (documents.php:899-967); a move or delete prints only an alert and a redirect in it
      else if (viewer && (rows.length || shown)) state = source === 'kassekladde' ? 'bilag' : 'ordreBilag';
      else if (a4Q(doc, '#docPoolContainer')) state = a4Q(doc, '#kassebilagTopBar') ? 'pulje' : 'ordrePulje';   // docPool.php:2097, 2304
      else if (a4Q(doc, 'button#emailD')) state = 'klassisk';                // documents.php:1052
      else state = 'skifter';                                                // meta refresh after link/unlink (documents.php:416, 921-923)

      const top = a4Q(doc, '#kassebilagTopBar');
      const search = a4Q(doc, '#poolSearchBox');
      const archive = a4Q(doc, '#poolShowArchived');
      const soegning = !!search && String(search.value || '').trim() !== '';
      // A property: docPoolArchive.js:177-180 ticks it by script in the archive view
      const arkiv = !!archive && archive.checked === true;
      const nyLinje = !!a4Q(doc, '#bilagEntry_new');                         // docPool.php:2243, row 'new' when sourceId is 0 (:2412-2431)
      // Bilagsbalance shown and off (docPoolSplit.js:113-114), with a Fordeling row or after its warning (beforeSave :213-226).
      // Not the balance alone: a saved two-line bilag (bank and expense) shows it off by the document's amount (balanceShown() :78-80)
      const balance = a4Q(doc, '#poolSplitBalance.pool-split-off');
      const balanceShown = !!balance && !(balance.style && balance.style.display === 'none');
      const current = kbCurrentRow(ctx);
      const flags = {
        nyLinje: nyLinje,
        fraLinje: state === 'pulje' && !nyLinje,
        // Gem og næste and the other buttons are printed only for an open kladde; otherwise Bogført (docPool.php:1852-1856, 2387-2404)
        laast: !!top && !a4Q(top, '#saveNextBtn'),
        soegning: soegning,
        arkiv: arkiv,
        // <em>Ingen filer fundet.</em> (docPool.php:3334-3337); the archive has its own empty text
        tom: !!a4Q(doc, '#fileListContainer > em') && !soegning && !arkiv,
        faerdig: url.searchParams.get('poolDone') === '1' || !!a4Q(doc, '#leftPanel .pool-done-note'),
        // The heading, not the rows: the card view (a stored choice, docPool.php:2778) prints no data-*-match
        match: !!a4Q(doc, KB_MATCH_HEADING),
        // Only the shown document's own badge: a pair elsewhere in the pool must not take the first task on every line
        dublet: !!current && !!a4Q(current, '.duplicate-badge'),
        fakturaBrugt: !!a4Q(doc, '#kassebilagTopBar .invoice-reuse-note'),
        forslag: !!a4Q(doc, '#kassebilagTopBar .pool-capture-badge, #kassebilagTopBar .pool-suggest, #kassebilagTopBar input.pool-suggested'),
        skaev: balanceShown && (!!a4Q(doc, '#bilagRowsContainer .kassebilag-entry[data-split="1"]') || a4Text(a4Q(balance, '.pool-split-warning')) !== ''),
        obligatorisk: !!a4Q(doc, '#kassebilagTopBar .pool-mandatory'),
        // The viewer's rows (listDocs.php:176-199): Fjern link on a shared file, Låst without links on a posted kladde after 24 hours
        flere: rows.length > 1,
        linket: rows.some(function (r) { return !!a4Q(r, 'a[href*="unlinkDoc="]'); }),
        laastBilag: rows.length > 0 && !rows.some(function (r) { return !!a4Q(r, 'a[href*="deleteDoc="], a[href*="unlinkDoc="]'); }),
        // docFileMissingBox() prints a box instead of the iframe, image or XML (showDoc.php:46-47; docFileFunc.php:117-124)
        mangler: !!shown && !a4Q(shown, 'iframe, img, pre, embed'),
        kanLinke: !!a4Q(doc, 'a[href*="linkBilag=1"]'),
        tomListe: state === 'link' && !!a4Q(doc, '#bilagList .empty-msg')       // documents.php:555
      };
      return { state: state, flags: flags, data: { bilag: kbBilag(ctx), ordre: ordre, source: source } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'pulje') {
        // The second paragraph stays true between in-place switches: it follows the line and the URL, not the list
        let second = 'help.kb.about.fra_linje';
        if (f.faerdig) second = 'help.kb.about.faerdig';
        else if (f.laast) second = 'help.kb.about.laast';
        else if (f.arkiv) second = 'help.kb.about.arkiv';
        else if (f.tom) second = 'help.kb.about.tom';
        else if (f.nyLinje) second = 'help.kb.about.ny_linje';
        // An empty or finished pool has nothing to click in the list
        return [f.tom || f.faerdig ? 'help.kb.about.pulje_kort' : 'help.kb.about.pulje', second];
      }
      if (state === 'bilag' || state === 'ordreBilag') {
        const out = [state === 'bilag' ? 'help.kb.about.bilag' : 'help.kb.about.ordre_bilag'];
        if (f.mangler) out.push('help.kb.about.bilag_mangler');
        else if (state === 'bilag' && f.laastBilag) out.push('help.kb.about.bilag_laast');
        else if (state === 'bilag' && f.linket) out.push('help.kb.about.bilag_linket');
        return out;
      }
      if (state === 'link') return f.tomListe ? ['help.kb.about.link', 'help.kb.about.link_ingen'] : ['help.kb.about.link'];
      return KB_ABOUT[state] ? [KB_ABOUT[state]] : [];
    },

    tasks: KB_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state === 'link') ids.push('KB22');
      else if (state === 'ordrePulje') ids.push('KB23', 'KB9');
      else if (state === 'ordreBilag') {
        if (f.flere) ids.push('KB17');
      } else if (state === 'bilag') {
        // Reached through Administrér bilag, to manage the voucher: move it first (Flyt til pulje), or Fjern link on a shared file
        if (f.flere) ids.push('KB17');
        // A missing file: Flyt til pulje would drop the reference and move nothing (moveDoc.php:83-125, 214-216, 246)
        if (f.mangler) {
          ids.push('KB24');
          return unique(ids);
        }
        if (!f.laastBilag) {
          ids.push(f.linket ? 'KB20' : 'KB19', 'KB18');
          if (f.kanLinke) ids.push('KB25');
        }
        ids.push('KB24');
      } else if (state === 'pulje') {
        // What stops the save, and the two double-entry warnings, come first in every situation of the pool
        if (f.obligatorisk) ids.push('KB13');
        if (f.skaev) ids.push('KB7');
        if (f.dublet) ids.push('KB14');
        if (f.fakturaBrugt) ids.push('KB15');
        if (f.faerdig) ids.push('KB16', 'KB9');
        // Read-only: nothing to save, so the viewer's short way back (KB16 names Gem og næste, which is not printed)
        else if (f.laast) ids.push('KB24');
        else if (f.arkiv) ids.push('KB12', 'KB11', 'KB16');
        else if (f.tom) {
          ids.push('KB9', 'KB10', 'KB16');
          if (!f.nyLinje) ids.push('KB21');                                  // the link needs a saved line (documents.php:397)
        } else if (f.nyLinje) {
          ids.push('KB4', 'KB5', f.forslag ? 'KB6' : 'KB9', 'KB7', 'KB8', 'KB6', 'KB9', 'KB11', 'KB10', 'KB16');
        } else {
          if (f.match) ids.push('KB2');
          ids.push('KB1', 'KB9', 'KB21', 'KB3', 'KB8', 'KB11', 'KB16');
        }
      }
      return unique(ids);
    },

    fieldKey: function (el) {
      const id = el && el.nodeType === 1 ? String(attr(el, 'id')) : '';
      const m = /^row_[A-Za-z0-9]+_([A-Za-z]+)$/.exec(id);
      if (m) return Object.prototype.hasOwnProperty.call(KB_ROW_FIELDS, m[1]) ? KB_ROW_FIELDS[m[1]] : null;
      return Object.prototype.hasOwnProperty.call(KB_ID_FIELDS, id) ? KB_ID_FIELDS[id] : null;
    },

    fields: KB_FIELDS,

    shortcuts: [
      { keys: 'Enter', text: 'help.kb.key.01', group: 'keys' },                   // docPoolSaveNext.js:396-404
      { keys: 'Ctrl+← / Ctrl+→', text: 'help.kb.key.02', group: 'keys' },         // fieldNavigation.js:91-107 (data-field-nav, docPool.php:2411)
      { keys: 'Ctrl+↑ / Ctrl+↓', text: 'help.kb.key.03', group: 'keys' },         // fieldNavigation.js:76-79, 103-106; docPoolSaveNext.js:350-358
      { keys: '← →', text: 'help.kb.key.04', group: 'keys' },                     // docPoolSaveNext.js:406-429
      { keys: 'Delete', text: 'help.kb.key.05', group: 'keys' },                  // docPoolArchive.js:211-220
      { keys: 'Alt+L', text: 'help.kb.key.06', group: 'keys' }                    // accesskey L on Tilbage (topLineDocuments.php:52-55)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.kb.q.q1.q',
        words: {
          da: ['gem og næste', 'indsæt valgte', 'gem alle', 'forskel', 'hvilken knap', 'vedhæfte', 'vedhæft', 'sætte på', 'gemme', 'gemt', 'bogført'],
          en: ['save and next', 'insert selected', 'save all', 'difference', 'which button', 'attach', 'save', 'saved', 'posted'],
          no: ['lagre og neste', 'sett inn valgte', 'lagre alle', 'forskjell', 'hvilken knapp', 'legge ved', 'lagre', 'lagret', 'bokført']
        },
        intro: 'help.kb.q.q1.intro',
        steps: ['help.kb.q.q1.step1', 'help.kb.q.q1.step2', 'help.kb.q.q1.step3'],
        show: KB_TASKS.KB4.show
      },
      Q2: {
        q: 'help.kb.q.q2.q',
        words: {
          da: ['stadig i puljen', 'ikke vedhæftet', 'ikke sat på', 'forsvinder ikke', 'flueben', 'kvittering', 'klikket', 'klikkede', 'skete', 'intet sker'],
          en: ['still in the pool', 'not attached', 'tick', 'receipt', 'clicked', 'nothing happens'],
          no: ['fortsatt i puljen', 'ikke vedlagt', 'avkrysning', 'kvittering', 'klikket', 'skjer ingenting']
        },
        intro: 'help.kb.q.q2.intro',
        steps: ['help.kb.q.q2.step1', 'help.kb.q.q2.step2', 'help.kb.q.q2.step3'],
        show: KB_TASKS.KB1.show
      },
      Q3: {
        q: 'help.kb.q.q3.q',
        words: {
          da: ['mail', 'mailet', 'mailer', 'e-mail', 'sendt', 'mailadresse', 'scanner'],
          en: ['email', 'mail', 'mailed', 'email address', 'scanner'],
          no: ['e-post', 'sendt', 'e-postadresse', 'skanner']
        },
        intro: 'help.kb.q.q3.intro',
        steps: stepKeys('kb', 'KB10', 1, 3),
        show: KB_TASKS.KB10.show
      },
      Q4: {
        q: 'help.kb.q.q4.q',
        words: {
          da: ['forsvundet', 'væk', 'kan ikke finde', 'blev af', 'mangler', 'hvor er'],
          en: ['gone', 'missing', 'cannot find', 'disappeared'],
          no: ['borte', 'forsvunnet', 'finner ikke', 'mangler']
        },
        intro: 'help.kb.q.q4.intro',
        steps: ['help.kb.q.q4.step1', 'help.kb.q.q4.step2', 'help.kb.q.q4.step3'],
        show: { target: '#poolSearchBox', label: fieldLabel('kb', 'poolsearchbox'), kind: 'field' }
      },
      Q5: {
        q: 'help.kb.q.q5.q',
        words: {
          da: ['dublet', 'dobbelt', 'to gange', 'kopi'],
          en: ['duplicate', 'twice', 'copy'],
          no: ['duplikat', 'dobbel', 'to ganger', 'kopi']
        },
        intro: 'help.kb.task.kb14.why',
        steps: stepKeys('kb', 'KB14', 1, 3),
        show: KB_TASKS.KB14.show
      },
      Q6: {
        q: 'help.kb.q.q6.q',
        words: {
          da: ['forslag', 'rettet', 'accepteret', 'aflæst', 'aflæsning', 'ai scan', 'udtræk fakturadata', 'læse tallene', 'scanne'],
          en: ['suggested', 'corrected', 'accepted', 'read', 'ai scan', 'extract invoice data', 'scan'],
          no: ['forslag', 'rettet', 'akseptert', 'lest', 'avlesning', 'ai scan', 'uttrekk fakturadata', 'skanne']
        },
        intro: 'help.kb.q.q6.intro',
        steps: ['help.kb.q.q6.step1', 'help.kb.q.q6.step2', 'help.kb.q.q6.step3']
      },
      // Legal wording from Saldi-landingpage/content/viden/articles/opbevaring-af-bilag.json body[4], body[6] and body[7] (body fields only)
      Q7: {
        q: 'help.kb.q.q7.q',
        words: {
          da: ['hvor længe', 'gemme bilag', 'opbevare', 'opbevaring', '5 år', 'fem år', 'bogføringsloven', 'slette bilag'],
          en: ['how long', 'keep', 'retention', 'five years'],
          no: ['hvor lenge', 'oppbevare', 'oppbevaring', 'fem år']
        },
        intro: 'help.kb.q.q7.intro',
        steps: ['help.kb.q.q7.step1', 'help.kb.q.q7.step2', 'help.kb.q.q7.step3']
      },
      Q8: {
        q: 'help.kb.q.q8.q',
        words: {
          da: ['pulje', 'dokumentpulje', 'forskel', 'vedhæfte', 'kassebilag', 'forhåndsvisning', 'se bilaget'],
          en: ['pool', 'document pool', 'difference', 'attach', 'voucher', 'preview'],
          no: ['pulje', 'dokumentpulje', 'forskjell', 'legge ved', 'kassebilag', 'forhåndsvisning']
        },
        intro: 'help.kb.q.q8.intro',
        steps: ['help.kb.q.q8.step1', 'help.kb.q.q8.step2', 'help.kb.q.q8.step3']
      },
      // As the kk page's Q11 (no key is shared across pages): an ordinary code takes the VAT out of the amount, E/Y add it on top
      // (finans/bogfor_includes/postingRules.php:101-141); u/m is text 2589 in the pool (docPool.php:2294)
      Q10: {
        q: 'help.kb.q.q10.q',
        words: {
          da: ['moms', 'med moms', 'uden moms', 'inkl moms', 'ekskl moms', 'netto', 'brutto', 'momsen to gange', 'købsmoms', 'omvendt betalingspligt', 'udland'],
          en: ['vat', 'including vat', 'excluding vat', 'net', 'gross', 'reverse charge', 'abroad'],
          no: ['mva', 'med mva', 'uten mva', 'netto', 'brutto', 'omvendt avgiftsplikt', 'utland']
        },
        intro: 'help.kb.q.q10.intro',
        steps: ['help.kb.q.q10.step1', 'help.kb.q.q10.step2', 'help.kb.q.q10.step3']
      },
      // Own sales invoices are posted when invoicing (includes/opretfunc.php:718; includes/ordrefunc.php:1713-1753), as kk KK27 [3]
      Q11: {
        q: 'help.kb.q.q11.q',
        words: {
          da: ['salgsfaktura', 'salgsfakturaer', 'egne fakturaer', 'kundefaktura', 'faktura til kunde', 'salg'],
          en: ['sales invoice', 'own invoice', 'customer invoice', 'sale'],
          no: ['salgsfaktura', 'egne fakturaer', 'kundefaktura', 'salg']
        },
        intro: 'help.kb.q.q11.intro',
        steps: ['help.kb.q.q11.step1', 'help.kb.q.q11.step2']
      }
    },

    // The chat's start screen shows these (spec §25). Q1 and Q8 name the kassekladde's buttons and lines, so an order's
    // pool and bilag lead with the pool's own questions. A line's bilag: Q7 first only when it is locked (Låst).
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'pulje') {
        if (f.arkiv) return ['Q4', 'Q7', 'Q5'];
        // The pool is done: where the bilag went (the list is empty then, so before tom)
        if (f.faerdig) return ['Q4', 'Q8', 'Q7'];
        // Day one: mailed bilag, what the pool is, and own sales invoices kept out of it (PLAN §0 step 3)
        if (f.tom) return ['Q3', 'Q8', 'Q11'];
        if (f.nyLinje) return ['Q1', 'Q10', 'Q6'];
        return ['Q1', 'Q2', 'Q8'];
      }
      if (state === 'bilag') return f.laastBilag ? ['Q7', 'Q8', 'Q4'] : ['Q8', 'Q7', 'Q4'];
      if (state === 'link') return ['Q4', 'Q8', 'Q7'];
      if (state === 'ordrePulje') return ['Q2', 'Q3', 'Q4'];
      if (state === 'ordreBilag') return ['Q7', 'Q3', 'Q4'];
      if (state === 'visning') return ['Q8', 'Q4', 'Q7'];
      return ['Q8', 'Q1', 'Q4'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['includes/documents.php'] = kassebilagPage;

  // ================================================================== a5: imports into a cash journal
  // finans/importer.php (im), finans/bankimport.php (bi), finans/danlonimport.php + finans/datalonimport.php (lon),
  // finans/pbsm602import.php + finans/pbswebimport.php (pbs), finans/loppeafregning.php (lop).
  // Not registered: finans/docubizzimport.php (an action without UI: FTP fetch, alert, meta refresh back to the
  // kladde) and finans/pbsimport.php (nothing links to it). See a5.md.
  //
  // These pages print <form> inside <table>/<tbody>, so the parser leaves each form element empty and the
  // controls after it are only associated with it (form owner). Selectors therefore never use "form input";
  // a control of one form is found through its .form property (impFormControl).

  /** All elements matching sel, as an array; [] on a bad selector or a fake document. */
  function impAll(doc, sel) {
    try {
      return Array.prototype.slice.call(doc.querySelectorAll(sel)).filter(function (el) { return el && el.nodeType === 1; });
    } catch (e) {
      return [];
    }
  }

  /** The first element matching sel, or null. */
  function impOne(doc, sel) {
    try {
      const el = doc.querySelector(sel);
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  /** The trimmed value of a control, '' when missing. */
  function impVal(el) {
    return el && el.nodeType === 1 && el.value != null ? String(el.value).trim() : '';
  }

  /** The first control matching sel whose form owner posts to action (importer.php:198-303). */
  function impFormControl(doc, action, sel) {
    const list = impAll(doc, sel);
    for (let i = 0; i < list.length; i++) {
      const f = list[i].form;
      if (f && f.nodeType === 1 && (f.getAttribute('action') || '') === action) return list[i];
    }
    return null;
  }

  /** The page only redirects (meta refresh back to the kladde or to importer.php): no help to give. */
  function impRedirecting(doc) {
    return !!impOne(doc, 'meta[http-equiv="refresh"]');
  }

  function impText(doc) {
    try {
      return String((doc.body && doc.body.textContent) || '');
    } catch (e) {
      return '';
    }
  }

  /** The kladde the import belongs to: the hidden kladde_id the page printed, else the URL's. */
  function impKladdeId(ctx) {
    const ids = impAll(ctx.doc, 'input[name="kladde_id"]');
    for (let i = 0; i < ids.length; i++) {
      const v = impVal(ids[i]);
      if (v && v !== '0') return v;
    }
    return (ctx.url && ctx.url.searchParams.get('kladde_id')) || '';
  }

  /** Hidden kladde_id inputs exist, but none has a value (importer.php:68-73 after a redirect with &bilag=). */
  function impNoKladde(doc) {
    const ids = impAll(doc, 'input[name="kladde_id"]');
    return ids.length > 0 && ids.every(function (i) { const v = impVal(i); return v === '' || v === '0'; });
  }

  /** The column choices, select[name="feltnavn[N]"]; the current choice is the first option (bankimport.php:425, importer.php:498-503). */
  function impColumns(doc) {
    return impAll(doc, 'select[name^="feltnavn["]');
  }

  /** The column select with this value, else the first column select. */
  function impColumnSelect(doc, value) {
    const s = impColumns(doc);
    for (let i = 0; i < s.length; i++) {
      if (impVal(s[i]) === value) return s[i];
    }
    return s[0] || null;
  }

  /** Lines of the lon and pbs previews: black rows are imported, red ones are skipped
   *  (style="color:rgb(0,0,0);" / rgb(255,0,0): danlonimport.php:122-129, datalonimport.php:149-155,
   *  pbsm602import.php:284-290, pbswebimport.php:136-142). */
  function impColourRows(doc) {
    return { ok: impAll(doc, 'tr[style*="rgb(0,0,0)"]'), skip: impAll(doc, 'tr[style*="rgb(255,0,0)"]') };
  }

  /** Lines of the bank preview: the first cell holds the voucher number when the line is imported, and a grey
   *  "-" in a span when it is skipped (bankimport.php:527-547). Rows with controls are the form's own rows. */
  function impBankRows(doc) {
    const ok = [], skip = [];
    const trs = impAll(doc, 'tr');
    for (let i = 0; i < trs.length; i++) {
      const cells = trs[i].cells;
      if (!cells || cells.length < 2) continue;
      const c = cells[0];
      if (!c || typeof c.querySelector !== 'function' || c.querySelector('input, select, table, a, hr')) continue;
      const t = String(c.textContent || '').replace(/ /g, ' ').trim();
      if (t === '-' && c.querySelector('span')) skip.push(trs[i]);
      else if (/^\d+$/.test(t)) ok.push(trs[i]);
    }
    return { ok: ok, skip: skip };
  }

  /** Field keys of these pages: feltnavn[N] -> feltnavn, currencyCode -> currency, fileCharSet -> file,
   *  uploadedfile[] -> uploadedfile (a key must be a prefix of the name, so search can find the field again). */
  function impFieldKey(el) {
    const n = attr(el, 'name');
    if (/^feltnavn\[\d*\]$/.test(n)) return 'feltnavn';
    if (n === 'currencyCode') return 'currency';
    if (n === 'fileCharSet') return 'file';
    if (n === 'uploadedfile[]') return 'uploadedfile';
    if (n) return n.replace(/_?\d+$/, '');
    return (el && el.id) || null;
  }

  /** The Luk/Tilbage link back to the kladde (accesskey L), or its button in the S menu (importer.php:103). */
  function impBackLink(ctx) {
    const a = impOne(ctx.doc, 'a[href^="kassekladde.php"]');
    if (!a) return null;
    const b = typeof a.querySelector === 'function' ? a.querySelector('button') : null;
    return b && b.nodeType === 1 ? b : a;
  }

  // ---- Import til kassekladde (finans/importer.php)
  // Reached from the kladde's Importér: kassekladde.php:1890 redirects the frame to importer.php?kladde_id=&bilagsnr=;
  // upload() prints one form per import (importer.php:185-313); "Import af andre data" posts back here and
  // vis_data() shows the column choice (:315-590); Flyt inserts the lines and redirects to the kladde (:592-718).

  const IM_TASKS = {
    IM1: task('im', 'IM1', 3, {
      target: function (ctx) { return impFormControl(ctx.doc, 'bankimport.php', 'input[type="file"]'); },
      label: fieldLabel('im', 'uploadedfile'), kind: 'field'
    }),
    IM2: task('im', 'IM2', 3, {
      target: function (ctx) { return impFormControl(ctx.doc, 'danlonimport.php', 'input[type="file"]'); },
      label: fieldLabel('im', 'uploadedfile'), kind: 'field'
    }),
    IM3: task('im', 'IM3', 3, {
      target: function (ctx) { return impFormControl(ctx.doc, 'importer.php', 'input[type="file"]'); },
      label: fieldLabel('im', 'uploadedfile'), kind: 'field'
    }),
    IM4: task('im', 'IM4', 3, {
      target: function (ctx) { return impFormControl(ctx.doc, 'pbsm602import.php', 'input[type="file"]'); },
      label: fieldLabel('im', 'uploadedfile'), kind: 'field'
    }),
    // importer.php:310: <a href='loppeafregning.php?kladde_id=…'><button>Start</button></a>
    IM5: task('im', 'IM5', 2, {
      target: function (ctx) {
        const a = impOne(ctx.doc, 'a[href^="loppeafregning.php"]');
        const b = a && typeof a.querySelector === 'function' ? a.querySelector('button') : null;
        return b && b.nodeType === 1 ? b : a;
      },
      label: showKey('im', 'IM5'), kind: 'button'
    }),
    IM6: task('im', 'IM6', 2),
    // vis_data(): the column selects (:492-511), Datoformat (:465), Tegnsæt (:457-464), Flyt (:481-482)
    IM7: task('im', 'IM7', 3, { target: function (ctx) { return impColumnSelect(ctx.doc, ''); }, label: fieldLabel('im', 'feltnavn'), kind: 'field' }),
    IM8: task('im', 'IM8', 2, { target: function (ctx) { return impColumnSelect(ctx.doc, ''); }, label: fieldLabel('im', 'feltnavn'), kind: 'field' }),
    IM9: task('im', 'IM9', 3, { target: function (ctx) { return impColumnSelect(ctx.doc, ''); }, label: fieldLabel('im', 'feltnavn'), kind: 'field' }),
    IM10: task('im', 'IM10', 4, { target: 'input[name="submit"][value="Flyt"]', label: showKey('im', 'IM10'), kind: 'button' }),
    IM11: task('im', 'IM11', 2, { target: 'input[name="datoformat"]', label: fieldLabel('im', 'datoformat'), kind: 'field' }),
    IM12: task('im', 'IM12', 2, { target: 'select[name="fileCharSet"]', label: fieldLabel('im', 'file'), kind: 'field' })
  };

  const IM_FIELDS = {};
  ['uploadedfile', 'feltnavn', 'file', 'datoformat', 'splitter', 'bilag']
    .forEach(function (k) { IM_FIELDS[k] = fieldKeys('im', k); });

  const IM_AMOUNT = ['beløb', 'debet(beløb)', 'kredit(beløb)'];                   // importer.php:63, :512
  const IM_ACCOUNT = ['debet(konto)', 'kredit(konto)', 'debitor', 'kreditor'];    // importer.php:661-676

  const importer = {
    group: 'im',
    states: ['valg', 'kolonner', 'fejl', 'videre', 'unknown'],
    flags: ['ingenKladde', 'manglerBeloeb', 'manglerDato', 'manglerKonto', 'kanFlytte', 'datoMaanedFoerst'],

    name: function (ctx, st) {
      const id = st && st.data ? String(st.data.kladdeId || '') : '';
      return id ? ['help.im.name', id] : 'help.im.name_plain';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state;
      if (impRedirecting(doc)) state = 'videre';                                            // flyt_data :717
      else if (impOne(doc, 'select[name="fileCharSet"]')) state = 'kolonner';               // vis_data :457
      else if (impOne(doc, 'form[action="bankimport.php"]')) state = 'valg';                // upload :198
      else if (/hentningen/.test(impText(doc))) state = 'fejl';                             // :150, hard-coded Danish
      else state = 'unknown';
      const cols = state === 'kolonner' ? impColumns(doc).map(impVal) : [];
      const has = function (list) { return cols.some(function (c) { return list.indexOf(c) !== -1; }); };
      const fmt = impVal(impOne(doc, 'input[name="datoformat"]')).toLowerCase();
      const flags = {
        ingenKladde: state === 'valg' && impNoKladde(doc),
        manglerBeloeb: state === 'kolonner' && !has(IM_AMOUNT),
        manglerDato: state === 'kolonner' && cols.indexOf('dato') === -1,
        manglerKonto: state === 'kolonner' && !has(IM_ACCOUNT),
        kanFlytte: state === 'kolonner' && !!impOne(doc, 'input[name="submit"][value="Flyt"]'),
        datoMaanedFoerst: state === 'kolonner' && fmt.charAt(0) === 'm'                    // the default is mm-dd-yyyy (:331)
      };
      return { state: state, flags: flags, data: { kladdeId: impKladdeId(ctx) } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'valg') return f.ingenKladde ? ['help.im.about.valg', 'help.im.about.ingen_kladde'] : ['help.im.about.valg'];
      if (state === 'kolonner') return ['help.im.about.kolonner'];
      if (state === 'fejl') return ['help.im.about.fejl'];
      if (state === 'videre') return ['help.im.about.videre'];
      return [];
    },

    tasks: IM_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state === 'valg') {
        if (f.ingenKladde) ids.push('IM6');
        ids.push('IM1', 'IM2', 'IM3', 'IM4', 'IM5');
      } else if (state === 'kolonner') {
        if (f.manglerBeloeb) ids.push('IM7');
        if (f.manglerDato) ids.push('IM8');
        if (f.manglerKonto) ids.push('IM9');
        if (f.datoMaanedFoerst && !f.manglerDato) ids.push('IM11');
        if (f.kanFlytte) ids.push('IM10');
        if (!f.manglerDato) ids.push('IM11');
        ids.push('IM12');
      }
      return unique(ids);
    },

    fields: IM_FIELDS,
    fieldKey: impFieldKey,

    // importer.php:103 (S menu) and :117: the link back to the kladde has accesskey L
    shortcuts: [
      { keys: 'Alt+L', text: 'help.im.key.01', group: 'keys' }
    ],
    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.im.q.q1.q',
        words: {
          da: ['import', 'importere', 'fil', 'filtype', 'csv', 'vælge import', 'overskrift'],
          en: ['import', 'which import', 'file', 'file type', 'csv'],
          no: ['import', 'importere', 'fil', 'filtype', 'csv', 'velge import']
        },
        intro: 'help.im.q.q1.intro',
        steps: ['help.im.q.q1.step1', 'help.im.q.q1.step2', 'help.im.q.q1.step3', 'help.im.q.q1.step4'],
        show: IM_TASKS.IM1.show
      },
      Q2: {
        q: 'help.im.q.q2.q',
        words: {
          da: ['dato', 'datoer', 'datoen', 'forkert', 'dag', 'måned', 'byttet', 'datoformat'],
          en: ['date', 'dates', 'wrong', 'day', 'month', 'swapped', 'date format'],
          no: ['dato', 'datoer', 'datoen', 'feil', 'dag', 'måned', 'byttet', 'datoformat']
        },
        intro: 'help.im.q.q2.intro',
        steps: stepKeys('im', 'IM8', 1, 1).concat(stepKeys('im', 'IM11', 1, 2)),
        show: IM_TASKS.IM11.show
      },
      Q3: {
        q: 'help.im.q.q3.q',
        words: {
          da: ['fortryd', 'fortryde', 'slet', 'slette', 'forkert import', 'importeret forkert', 'to gange'],
          en: ['undo', 'delete', 'remove', 'wrong import', 'twice'],
          no: ['angre', 'slett', 'slette', 'feil import', 'importert feil', 'to ganger']
        },
        intro: 'help.im.q.q3.intro',
        steps: ['help.im.q.q3.step1', 'help.im.q.q3.step2', 'help.im.q.q3.step3']
      }
    },

    // After the import (videre) the lines are in the kladde: undoing them leads (spec §25).
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      if (state === 'videre') return ['Q3', 'Q2', 'Q1'];
      return state === 'kolonner' ? ['Q2', 'Q3', 'Q1'] : ['Q1', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };
  PAGES['finans/importer.php'] = importer;

  // ---- Bankimport (finans/bankimport.php)
  // Reached from importer.php:198 (Importer bankposteringer, Hent). vis_data() (bankimport.php:203-562) prints Vend,
  // Separatortegn, Afdeling, Valuta, Posteringskonto, Gebyrkonto, Vis and, once dato/beskrivelse/belob and the
  // account are set, Importér (:387-393); flyt_data() (:564-864) inserts the lines and redirects to the kladde.
  // In the S menu (the default, online.php:332) the page prints no header and no back link (:102-103).

  const BI_TASKS = {
    BI1: task('bi', 'BI1', 3, { target: function (ctx) { return impColumnSelect(ctx.doc, ''); }, label: fieldLabel('bi', 'feltnavn'), kind: 'field' }),
    BI2: task('bi', 'BI2', 3, { target: 'input[name="kontonr"]', label: fieldLabel('bi', 'kontonr'), kind: 'field' }),
    BI3: task('bi', 'BI3', 4, { target: 'input[name="import"]', label: showKey('bi', 'BI3'), kind: 'button' }),
    BI4: task('bi', 'BI4', 4, {
      target: function (ctx) { return impBankRows(ctx.doc).ok[0] || null; },
      label: showKey('bi', 'BI4'), kind: 'row'
    }),
    BI5: task('bi', 'BI5', 3, { target: function (ctx) { return impColumnSelect(ctx.doc, ''); }, label: fieldLabel('bi', 'feltnavn'), kind: 'field' }),
    BI6: task('bi', 'BI6', 3),
    BI8: task('bi', 'BI8', 2, { target: 'input[name="uploadedfile"]', label: fieldLabel('bi', 'uploadedfile'), kind: 'field' }),
    BI9: task('bi', 'BI9', 2),
    BI11: task('bi', 'BI11', 2, { target: function (ctx) { return impColumnSelect(ctx.doc, 'belob'); }, label: fieldLabel('bi', 'feltnavn'), kind: 'field' })
  };

  const BI_FIELDS = {};
  ['uploadedfile', 'vend', 'splitter', 'afd', 'currency', 'kontonr', 'gebyrkonto', 'bilag', 'feltnavn']
    .forEach(function (k) { BI_FIELDS[k] = fieldKeys('bi', k); });

  const bankimport = {
    group: 'bi',
    states: ['upload', 'kolonner', 'tom', 'videre', 'unknown'],
    flags: ['manglerDato', 'manglerTekst', 'manglerBeloeb', 'manglerKonto', 'kanImportere', 'ingenLinjer', 'saldo'],

    name: function () {
      return 'help.bi.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state;
      if (impRedirecting(doc)) state = 'videre';                                    // flyt_data :863
      else if (impOne(doc, 'select[name="splitter"]')) state = 'kolonner';          // vis_data :348
      else if (impOne(doc, 'input[name="uploadedfile"]')) state = 'upload';         // upload :195 (Hent without a file)
      else if (!impOne(doc, 'form')) state = 'tom';                                 // :139-160: a refused upload prints nothing
      else state = 'unknown';
      const cols = state === 'kolonner' ? impColumns(doc).map(impVal) : [];
      const konto = impVal(impOne(doc, 'input[name="kontonr"]'));
      const rows = state === 'kolonner' ? impBankRows(doc) : { ok: [], skip: [] };
      const flags = {
        manglerDato: state === 'kolonner' && cols.indexOf('dato') === -1,
        manglerTekst: state === 'kolonner' && cols.indexOf('beskrivelse') === -1,
        manglerBeloeb: state === 'kolonner' && cols.indexOf('belob') === -1,
        manglerKonto: state === 'kolonner' && (konto === '' || konto === '0'),
        kanImportere: state === 'kolonner' && !!impOne(doc, 'input[name="import"]'),
        ingenLinjer: false,
        saldo: cols.indexOf('saldo') !== -1
      };
      // An amount column is chosen, but every line is skipped (amount 0 or not a number, :486-500).
      flags.ingenLinjer = state === 'kolonner' && !flags.manglerBeloeb && rows.ok.length === 0 && rows.skip.length > 0;
      return { state: state, flags: flags, data: { linjer: rows.ok.length, sprunget: rows.skip.length } };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'kolonner') return f.kanImportere ? ['help.bi.about.kolonner'] : ['help.bi.about.kolonner', 'help.bi.about.mangler'];
      if (state === 'upload') return ['help.bi.about.upload'];
      if (state === 'tom') return ['help.bi.about.tom'];
      if (state === 'videre') return ['help.bi.about.videre'];
      return [];
    },

    tasks: BI_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state === 'upload') ids.push('BI8', 'BI4');
      else if (state === 'tom') ids.push('BI9');
      else if (state === 'kolonner') {
        if (f.manglerDato || f.manglerTekst || f.manglerBeloeb) ids.push('BI1');
        if (f.manglerKonto) ids.push('BI2');
        if (f.ingenLinjer) ids.push('BI11');
        if (f.kanImportere) ids.push('BI3');
        ids.push('BI4');
        if (!f.saldo) ids.push('BI5');
        ids.push('BI6');
      }
      return unique(ids);
    },

    fields: BI_FIELDS,
    fieldKey: impFieldKey,

    // No accesskey in the default S menu (bankimport.php:102-103 prints no header); the classic header's
    // Tilbage (:108) is not offered.
    shortcuts: [],

    questions: {
      Q1: {
        q: 'help.bi.q.q1.q',
        words: {
          da: ['fil', 'netbank', 'netbanken', 'format', 'csv', 'kontoudtog', 'eksport', 'eksportere', 'hente fil'],
          en: ['file', 'online bank', 'format', 'csv', 'statement', 'export', 'download'],
          no: ['fil', 'nettbank', 'nettbanken', 'format', 'csv', 'kontoutskrift', 'eksport', 'eksportere', 'laste ned']
        },
        intro: 'help.bi.q.q1.intro',
        steps: ['help.bi.q.q1.step1', 'help.bi.q.q1.step2', 'help.bi.q.q1.step3']
      },
      Q2: {
        q: 'help.bi.q.q2.q',
        words: {
          da: ['dobbelt', 'to gange', 'igen', 'dublet', 'dubletter', 'samme posteringer', 'importeret før'],
          en: ['twice', 'double', 'duplicate', 'duplicates', 'again', 'imported before'],
          no: ['dobbelt', 'to ganger', 'igjen', 'duplikat', 'samme posteringer', 'importert før']
        },
        steps: stepKeys('bi', 'BI4', 1, 4),
        show: BI_TASKS.BI4.show
      },
      Q3: {
        q: 'help.bi.q.q3.q',
        words: {
          da: ['modkonto', 'modkontoen', 'mangler', 'tom', 'ene side', 'difference', 'udlign'],
          en: ['contra account', 'missing', 'empty', 'one side', 'difference', 'equalize'],
          no: ['motkonto', 'motkontoen', 'mangler', 'tom', 'ene siden', 'differanse', 'utjevner']
        },
        intro: 'help.bi.q.q3.intro',
        steps: stepKeys('bi', 'BI6', 1, 3).concat(['help.bi.q.q3.step4'])
      }
    },

    // After the import (videre) the lines are in the kladde with the bank account only: the contra account leads (spec §25).
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      if (state === 'videre') return ['Q3', 'Q2', 'Q1'];
      return state === 'kolonner' ? ['Q2', 'Q3', 'Q1'] : ['Q1', 'Q2', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };
  PAGES['finans/bankimport.php'] = bankimport;

  // ---- Import fra Danløn / Import fra Dataløn (finans/danlonimport.php, finans/datalonimport.php)
  // Reached from importer.php:214 and :230 (Hent). vis_data() prints Vis, Flyt (only with a kladde id) and the
  // voucher field (danlonimport.php:107-119, datalonimport.php:131-145); Luk (accesskey L, :60 / :72) goes back to
  // the kladde. Flyt inserts one line per black row on the file's account, without a contra account
  // (danlonimport.php:171-175: + in Debet, - in Kredit; datalonimport.php:208-213: Debet with the amount's sign).

  const LON_TASKS = {
    L1: task('lon', 'L1', 2, { target: impBackLink, label: showKey('lon', 'L1'), kind: 'button' }),
    L2: task('lon', 'L2', 3, { target: 'input[name="submit"][value="Flyt"]', label: showKey('lon', 'L2'), kind: 'button' }),
    L3: task('lon', 'L3', 2),
    L4: task('lon', 'L4', 2)
  };

  const LON_FIELDS = { bilag: fieldKeys('lon', 'bilag') };

  /** One page object per payroll import; variant 'danlon' or 'datalon' only changes the name and one about line. */
  function lonPage(variant) {
    return {
      group: 'lon',
      states: ['vis', 'fejl', 'videre', 'unknown'],
      flags: ['ingenLinjer', 'ingenKladde'],

      name: function () {
        return 'help.lon.name_' + variant;
      },

      detect: function (ctx) {
        const doc = ctx.doc;
        let state;
        if (impRedirecting(doc)) state = 'videre';                                       // no file, GET, or after Flyt
        else if (impOne(doc, 'input[name="filnavn"]')) state = 'vis';                    // vis_data's hidden filnavn
        else if (/hentningen/.test(impText(doc))) state = 'fejl';                        // :70 / :82, hard-coded Danish
        else state = 'unknown';
        const rows = state === 'vis' ? impColourRows(doc) : { ok: [], skip: [] };
        const flags = {
          ingenLinjer: state === 'vis' && rows.ok.length === 0,
          ingenKladde: state === 'vis' && impNoKladde(doc)                                // Flyt needs the kladde id (:115 / :139)
        };
        return { state: state, flags: flags, data: { linjer: rows.ok.length, sprunget: rows.skip.length } };
      },

      about: function (ctx, st) {
        const state = st ? st.state : 'unknown';
        if (state === 'vis') return ['help.lon.about.vis', 'help.lon.about.' + variant];
        if (state === 'fejl') return ['help.lon.about.fejl'];
        return [];
      },

      tasks: LON_TASKS,

      order: function (ctx, st) {
        const f = (st && st.flags) || {};
        if (!st || st.state !== 'vis') return [];
        const ids = [];
        if (f.ingenKladde) ids.push('L4');
        if (f.ingenLinjer) ids.push('L1');
        if (!f.ingenKladde && !f.ingenLinjer) ids.push('L2');
        ids.push('L3');
        return unique(ids);
      },

      fields: LON_FIELDS,

      shortcuts: [
        { keys: 'Alt+L', text: 'help.lon.key.01', group: 'keys' }
      ],
      shortcutsNote: 'help.common.shortcuts_note',

      questions: {
        Q1: {
          q: 'help.lon.q.q1.q',
          words: {
            da: ['løn', 'lønnen', 'lønfil', 'lønkørsel', 'danløn', 'dataløn', 'importere løn'],
            en: ['payroll', 'salary', 'wages', 'payroll file', 'danløn', 'dataløn'],
            no: ['lønn', 'lønnen', 'lønnsfil', 'lønnskjøring', 'danløn', 'dataløn']
          },
          steps: ['help.lon.q.q1.step1', 'help.lon.q.q1.step2'].concat(stepKeys('lon', 'L2', 2, 3)),
          show: LON_TASKS.L2.show
        },
        Q2: {
          q: 'help.lon.q.q2.q',
          words: {
            da: ['difference', 'balancerer', 'balancerer ikke', 'går ikke i nul', 'skæv', 'lønbilag'],
            en: ['difference', 'balance', 'does not balance', 'not zero', 'payroll voucher'],
            no: ['differanse', 'balanserer', 'balanserer ikke', 'går ikke i null', 'lønnsbilag']
          },
          steps: stepKeys('lon', 'L3', 1, 2)
        }
      },

      questionOrder: function () {
        return ['Q1', 'Q2'];
      },

      signal: function () {
        return false;
      }
    };
  }

  const danlonimport = lonPage('danlon');
  PAGES['finans/danlonimport.php'] = danlonimport;
  const datalonimport = lonPage('datalon');
  PAGES['finans/datalonimport.php'] = datalonimport;

  // ---- BS-betalinger M602 / BS-betalinger fra web (finans/pbsm602import.php, finans/pbswebimport.php)
  // Reached from importer.php:246 (M602, several files) and :263 (web, udenopkrævningsarter.csv). Both print
  // Modkonto, Vis, Flyt (only with Modkonto and a kladde id) and the voucher field (pbsm602import.php:266-274,
  // pbswebimport.php:122-131). M602's preview already saves BS0603 mandate changes on the customers
  // (bs603UpdateAgreement, :198-206, :416-456); its Flyt credits each customer and debits Modkonto with the total
  // per day (:376-395). Web's Flyt makes one line per payment, Modkonto in Debet, the customer in Kredit (:175-179).

  const PBS_P2 = task('pbs', 'P2', 4, { target: 'input[name="submit"][value="Flyt"]', label: showKey('pbs', 'P2'), kind: 'button' });
  const PBS_TASKS = {
    P1: task('pbs', 'P1', 2, { target: 'input[name="modkonto"]', label: fieldLabel('pbs', 'modkonto'), kind: 'field' }),
    P2: PBS_P2,
    P3: task('pbs', 'P3', 3, {
      // A mandate row is red and has no amount in its last cell (pbsm602import.php:204, :290).
      target: function (ctx) {
        const red = impColourRows(ctx.doc).skip;
        for (let i = 0; i < red.length; i++) {
          const c = red[i].cells;
          if (c && c.length >= 5 && String(c[c.length - 1].textContent || '').trim() === '' &&
              String(c[2].textContent || '').trim() !== '') return red[i];
        }
        return null;
      },
      label: showKey('pbs', 'P3'), kind: 'row'
    }),
    P4: task('pbs', 'P4', 2, { target: impBackLink, label: showKey('pbs', 'P4'), kind: 'button' }),
    // The web import shares P2's title, why, first and last step.
    P5: {
      title: PBS_P2.title,
      why: PBS_P2.why,
      steps: [PBS_P2.steps[0], 'help.pbs.task.p5.step2', 'help.pbs.task.p5.step3', PBS_P2.steps[3]],
      show: PBS_P2.show
    },
    P6: task('pbs', 'P6', 2)
  };

  const PBS_FIELDS = { modkonto: fieldKeys('pbs', 'modkonto'), bilag: fieldKeys('pbs', 'bilag') };

  /** One page object per BS import; variant 'm602' or 'web'. */
  function pbsPage(variant) {
    const m602 = variant === 'm602';
    return {
      group: 'pbs',
      states: ['vis', 'fejl', 'videre', 'unknown'],
      flags: ['manglerModkonto', 'ingenKladde', 'ingenLinjer', 'aftaler'],

      name: function () {
        return 'help.pbs.name_' + variant;
      },

      detect: function (ctx) {
        const doc = ctx.doc;
        let state;
        if (impRedirecting(doc)) state = 'videre';
        else if (impOne(doc, 'input[name="modkonto"]')) state = 'vis';
        else if (/hentningen/.test(impText(doc))) state = 'fejl';                       // pbswebimport.php:75
        else state = 'unknown';
        const rows = state === 'vis' ? impColourRows(doc) : { ok: [], skip: [] };
        const aftaler = m602 && !!PBS_TASKS.P3.show.target(ctx);
        const flags = {
          manglerModkonto: state === 'vis' && impVal(impOne(doc, 'input[name="modkonto"]')) === '',
          ingenKladde: state === 'vis' && impNoKladde(doc),
          ingenLinjer: state === 'vis' && rows.ok.length === 0 && !aftaler,
          aftaler: state === 'vis' && aftaler
        };
        return { state: state, flags: flags, data: { linjer: rows.ok.length, sprunget: rows.skip.length } };
      },

      about: function (ctx, st) {
        const state = st ? st.state : 'unknown';
        const f = (st && st.flags) || {};
        if (state === 'vis') {
          if (!m602) return ['help.pbs.about.web'];
          return f.aftaler ? ['help.pbs.about.m602', 'help.pbs.about.aftaler'] : ['help.pbs.about.m602'];
        }
        if (state === 'fejl') return ['help.pbs.about.fejl'];
        return [];
      },

      tasks: PBS_TASKS,

      order: function (ctx, st) {
        const f = (st && st.flags) || {};
        const linjer = st && st.data ? st.data.linjer : 0;
        if (!st || st.state !== 'vis') return [];
        const ids = [];
        if (f.ingenKladde) ids.push('P6');
        if (f.manglerModkonto) ids.push('P1');
        if (f.ingenLinjer) ids.push('P4');
        if (f.aftaler) ids.push('P3');
        if (linjer > 0 && !f.ingenKladde) ids.push(m602 ? 'P2' : 'P5');               // Flyt needs the kladde id
        return unique(ids);
      },

      fields: PBS_FIELDS,

      // Luk has accesskey L (pbsm602import.php:71, pbswebimport.php:65)
      shortcuts: [
        { keys: 'Alt+L', text: 'help.pbs.key.01', group: 'keys' }
      ],
      shortcutsNote: 'help.common.shortcuts_note',

      questions: m602 ? {
        Q1: pbsQ1(),
        Q2: {
          q: 'help.pbs.q.q2.q',
          words: {
            da: ['aftale', 'aftaler', 'tilmeldt', 'afmeldt', 'ændret', 'mandat', 'bs-aftale'],
            en: ['agreement', 'agreements', 'mandate', 'signed up', 'cancelled', 'changed'],
            no: ['avtale', 'avtaler', 'påmeldt', 'avmeldt', 'endret', 'mandat']
          },
          steps: stepKeys('pbs', 'P3', 1, 3),
          show: PBS_TASKS.P3.show
        }
      } : { Q1: pbsQ1() },

      questionOrder: function () {
        return m602 ? ['Q1', 'Q2'] : ['Q1'];
      },

      signal: function () {
        return false;
      }
    };
  }

  function pbsQ1() {
    return {
      q: 'help.pbs.q.q1.q',
      words: {
        da: ['betalingsservice', 'bs', 'pbs', 'nets', 'leverance', '0602', 'm602', 'bs-betalinger'],
        en: ['betalingsservice', 'direct debit', 'bs', 'pbs', 'nets', 'delivery', '0602'],
        no: ['betalingsservice', 'bs', 'pbs', 'nets', 'leveranse', '0602', 'bs-betalinger']
      },
      intro: 'help.pbs.q.q1.intro',
      steps: stepKeys('pbs', 'P4', 1, 1).concat(['help.pbs.q.q1.step2'], stepKeys('pbs', 'P1', 1, 1), ['help.pbs.q.q1.step4'])
    };
  }

  const pbsm602import = pbsPage('m602');
  PAGES['finans/pbsm602import.php'] = pbsm602import;
  const pbswebimport = pbsPage('web');
  PAGES['finans/pbswebimport.php'] = pbswebimport;

  // ---- Afregning af kommissionssalg (finans/loppeafregning.php)
  // Reached from importer.php:310 (Start). The form (loppeafregning.php:147-160) is posted to itself; with
  // Vareprefix, Fra dato and Til dato set it inserts the lines at once (:64-143): one credit line per seller
  // (customer number = item number from the 6th character, :80) and a total line with no Debet account (:140,
  // $vg_modkonto is always 0, :59), then prints the count and a link back to the kladde (:144-145).

  const LOP_TASKS = {
    LP1: task('lop', 'LP1', 4, { target: 'input[name="loppeafregning"]', label: showKey('lop', 'LP1'), kind: 'button' }),
    LP2: task('lop', 'LP2', 2, { target: 'select[name="varegruppe"]', label: fieldLabel('lop', 'varegruppe'), kind: 'field' }),
    LP3: task('lop', 'LP3', 3, { target: 'a[href^="kassekladde.php"]', label: showKey('lop', 'LP3'), kind: 'button' }),
    LP4: task('lop', 'LP4', 2)
  };

  const LOP_FIELDS = {};
  ['vareprefix', 'varegruppe', 'provision', 'fra', 'til', 'bilag']
    .forEach(function (k) { LOP_FIELDS[k] = fieldKeys('lop', k); });

  const loppeafregning = {
    group: 'lop',
    states: ['form', 'faerdig', 'unknown'],
    flags: ['ingen'],

    name: function () {
      return 'help.lop.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state;
      if (impOne(doc, 'form[name="loppeafregning"]')) state = 'form';
      else if (impOne(doc, 'a[href^="kassekladde.php"]')) state = 'faerdig';
      else state = 'unknown';
      // ":144 count afregninger indsat …", hard-coded Danish: 0 means no sales were found.
      const ingen = state === 'faerdig' && /(^|[^0-9])0 afregninger/.test(impText(doc));
      return { state: state, flags: { ingen: ingen }, data: {} };
    },

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'form') return ['help.lop.about.form'];
      if (state === 'faerdig') return f.ingen ? ['help.lop.about.ingen'] : ['help.lop.about.faerdig'];
      return [];
    },

    tasks: LOP_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'form') return ['LP1', 'LP2'];
      if (state === 'faerdig') return f.ingen ? ['LP4'] : ['LP3'];
      return [];
    },

    fields: LOP_FIELDS,

    shortcuts: [],

    questions: {
      Q1: {
        q: 'help.lop.q.q1.q',
        words: {
          da: ['loppe', 'loppemarked', 'kommission', 'kommissionssalg', 'afregning', 'afregne', 'provision', 'sælger'],
          en: ['flea market', 'commission', 'consignment', 'settlement', 'settle', 'seller'],
          no: ['loppe', 'loppemarked', 'kommisjon', 'kommisjonssalg', 'avregning', 'avregne', 'provisjon', 'selger']
        },
        steps: stepKeys('lop', 'LP1', 1, 4),
        show: LOP_TASKS.LP1.show
      }
    },

    questionOrder: function () {
      return ['Q1'];
    },

    signal: function () {
      return false;
    }
  };
  PAGES['finans/loppeafregning.php'] = loppeafregning;

  // ------------------------------------------------------------------ problems the panel shows first (spec §18)
  // A state in which the page shows an error or something that stops the work: the panel puts the first task of the
  // page's own order in a card above the guidance, with its steps open, and leaves it out of "Sådan gør du".
  // Every other state returns '' (no card). States without a task (a file that could not be read) keep their Om siden.

  /** The first task of the page's own order for this state, '' when there is none. */
  function firstTask(page, ctx, st) {
    const o = page.order(ctx, st);
    return Array.isArray(o) && o.length ? String(o[0]) : '';
  }

  const PROBLEMS = {
    // a refused save; a line without an account, a possible double posting, a used invoice number, a difference
    'finans/kassekladde.php': function (s, f) { return s === 'afvist' || (s === 'aaben' && !!(f.manglerKonto || f.dublet || f.fakturaBrugt || f.difference)); },
    // posting refused or stopped; an account that does not exist or a bilag that does not net to zero on the overview
    'finans/bogfor.php': function (s, f) { return s === 'fejl' || s === 'stoppet' || ((s === 'bekraeft' || s === 'simuler') && !!(f.kontoMangler || f.bilagDiff)); },
    // Gem og næste refused, a split that does not balance, a possible duplicate, a used invoice number
    'includes/documents.php': function (s, f) { return s === 'pulje' && !!(f.obligatorisk || f.skaev || f.dublet || f.fakturaBrugt); },
    'finans/rapport.php': function (s) { return s === 'ingenAar'; },
    'finans/bankReconcile.php': function (s) { return s === 'fejl'; },
    'finans/bankimport.php': function (s) { return s === 'tom'; },
    'finans/importer.php': function (s) { return s === 'fejl'; },
    'finans/danlonimport.php': function (s) { return s === 'fejl'; },
    'finans/datalonimport.php': function (s) { return s === 'fejl'; },
    'finans/pbsm602import.php': function (s) { return s === 'fejl'; },
    'finans/pbswebimport.php': function (s) { return s === 'fejl'; }
  };

  Object.keys(PROBLEMS).forEach(function (route) {
    const page = PAGES[route];
    const is = PROBLEMS[route];
    if (!page) return;
    page.problem = function (ctx, st) {
      const state = st ? st.state : '';
      const f = (st && st.flags) || {};
      return is(state, f) ? firstTask(page, ctx, st) : '';
    };
  });
})();
