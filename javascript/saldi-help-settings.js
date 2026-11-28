// ---- javascript/saldi-help-settings.js --- ver 5.1.0 --- 2026.11.14 ---
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The help panel's page knowledge for Indstillinger (window.SaldiHelpContent.pages, doc/hjaelp_saldi_ai.md §3.2, §4,
// §16.1 and §24): the settings hub (systemdata/settings.php), its sections (systemdata/settingsSection.php?s=<group>.
// <section>), personal settings and every page a section leads to in the frame.
// Loads after javascript/saldi-help-lager.js and builds its pages with the helpers of javascript/saldi-help-content.js
// (SaldiHelpContent.lib). Data and pure functions only. Every text the panel shows is a key in
// importfiler/tekster_keyed.tsv (help.<group>.*), fetched per language from index/helpTexts.php. Detectors and show
// targets only read the frame document: no writes, focus, clicks, network or timers. Frame elements come from another
// realm, so type checks use nodeType/tagName.
//
// One route, many sections (spec §24): systemdata/settingsSection.php serves every section of getSettingsSections()
// (systemdata/settingsDefinitions.php:103) from one script, so its page below is a dispatcher. Each section gets its own
// page object with its own text group, registered with addSection('<group>.<section>', page); the dispatcher's variant()
// reads the section key from the frame's address, and SaldiHelpState.resolve() hands that page to the shell. A section
// without a page of its own gets the dispatcher's generic page (group sset).
//
// 20261109 CL/ASR Created: the module file for Indstillinger, loaded like saldi-help-kreditor.js, with the dispatcher of
//                 the settings sections and its generic page.
// 20261109 CL/ASR S1: the hub and the moved map, personal settings, the Virksomhed, Finans, Dokumenter, Afdelinger, Ansatte and
//                 Import & eksport sections, Åbningsbalance, Formularer, the form editor, Baggrunds- og bilagsfiler, Tekster,
//                 backup and restore, and the import and export pages.
// 20261109 CL/ASR S2: the Salg, Køb, Provision, Projekter and Kommissionsvarer sections.
// 20261109 CL/ASR S3: the Varer & lager, Integrationer and Kasse sections and the pages they lead to: POS-menuer with its
//                 import and export, Importér varianter, Mærkater and Vis QR-kode til app-login; spec §24.5.
// 20261109 CL/ASR Review (spec §24.14): the restore's RS4 rings the warning it is about; Lagerstyring puts the cost price
//                 method first; texts against the code (setup order, stock value, VAT report range, fiscal year deletion).
// 20261109 CL/ASR Live check on ssl12 (spec §24.15): a table's checkbox cell rings its visible box, not the hidden twin;
//                 Regnskabsår with one year; Lagerstyring with FIFO on; Formularer's selects outside their form; Tekster
//                 without the csv link (it has no file); Baggrunds- og bilagsfiler and Lagre only offer what the page has.
// 20261109 CL/ASR Integrationer: till payments come first only when a payment provider is set up (flag kasseIBrug); the till module alone is on for most installs.
// 20261109 CL/ASR Page questions in the chat (spec §25): each state's first three questions reviewed; new questions on Stamdata, Debitorkort, Købsordrer, Mit salg, Provision, Projekter, Kommissionsvarer and Valuta, so the read-only question no longer leads a section you can edit; orders per state for Regnskabsår, Moms, Valuta, Varegrupper, the debtor and creditor groups and personal settings; Landekonfiguration and the GDPR deletion answered as the code does.
// 20261112 CL/ASR E-mail: no ML3, and the customer mail fields say that the CRM, which replaced the history list, does not send that mail (spec §22.10).
// 20261114 CL/ASR Varegrupper: the Lagertilgang/Lagertræk columns are gone from the table, so their field help and the tasks' mention of them are too.
// 20261114 CL/ASR Brugere og roller (systemdata/usersRoles.php, group sbrugere): the people list, the user card, a new login, an
//                 employee without a login, the roles editor, a new role, the audit log and the refusal card, with tasks, field
//                 help and page questions; selectors accept both master's markup and the roles branch's.
(function () {
  'use strict';

  const C = window.SaldiHelpContent;
  if (!C || !C.lib || !C.pages) return;
  const task = C.lib.task, showKey = C.lib.showKey, fieldKeys = C.lib.fieldKeys, fieldLabel = C.lib.fieldLabel,
    stepKeys = C.lib.stepKeys, unique = C.lib.unique, attr = C.lib.attr, byName = C.lib.byName, rowOf = C.lib.rowOf;
  const PAGES = C.pages;

  // ==== The settings sections (systemdata/settingsSection.php?s=<group>.<section>): the dispatcher
  // Evidence, systemdata/settingsSection.php unless named: the section key is the address's s= (:103), and every save,
  // action and redirect keeps it, because the forms post to selfUrl, which starts with settingsSection.php?s=<key>
  // (:173, :644, includes/settings/rowsView.php:130, includes/settings/listView.php:371). An unknown key redirects to
  // settings.php (:104-108). Three views: a form (#st-form, the save bar #st-savebar with Fortryd #st-undo and Gem
  // ændringer #st-save, Ctrl+S, only while something is unsaved and only with write access, :747-757), a table of rows
  // (kind rows: the same form, bar and history, rowsView.php:130-224) and a list (kind list: rows .st-irow with a button
  // [data-open] that opens a drawer with its own Gem ændringer [data-save], listView.php:318-330, :371-450). Form and
  // rows show Seneste ændringer (.st-hist) with Gendan (button[data-restore]) where a value can be put back (:760-780);
  // Gendan asks first and then saves (javascript/settingsSection.js:163-166). Without write access the page says Du har
  // kun læseadgang til disse indstillinger (.st-flash-info, 5755, :564) and has no save bar. Above the title: Tilbage
  // (a[data-back], accesskey L, includes/settings/components.php:761-767); in the title the ? that opens the section's
  // own help (button[data-section-help], components.php:734-750), so the generic page does not repeat it.

  /** Section key → its page object, filled with addSection() by the authors' sections below. */
  const SECTIONS = {};
  /** What addSection() refused (a malformed key, a key registered twice, no page object); the content test wants none. */
  const SECTION_ERRORS = [];

  /**
   * Register the page object of one settings section: key '<group>.<section>' as in getSettingsSections()
   * (finance.vat, company.fiscal_years), page a full page object (§3.2) with its own text group. The first registration of
   * a key wins; anything else is recorded in SECTION_ERRORS instead of thrown, so one mistake cannot stop the other pages.
   */
  function addSection(key, page) {
    if (typeof key !== 'string' || !/^[a-z_]+\.[a-z_]+$/.test(key)) {
      SECTION_ERRORS.push('addSection(): not a section key: ' + String(key));
      return;
    }
    if (Object.prototype.hasOwnProperty.call(SECTIONS, key)) {
      SECTION_ERRORS.push('addSection(): ' + key + ' is registered twice');
      return;
    }
    if (!page || typeof page !== 'object' || typeof page.group !== 'string') {
      SECTION_ERRORS.push('addSection(): ' + key + ' has no page object with a group');
      return;
    }
    SECTIONS[key] = page;
  }

  function sectionQ(doc, sel) {
    try {
      const el = doc ? doc.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  /** The section key of the frame: the address's s=, else the s= in the action of the page's form; '' without one. */
  function sectionKeyOf(ctx) {
    let key = '';
    try {
      key = ctx && ctx.url && ctx.url.searchParams ? String(ctx.url.searchParams.get('s') || '') : '';
    } catch (e) {
      key = '';
    }
    if (!key && ctx) {
      const form = sectionQ(ctx.doc, 'form[action*="settingsSection.php?s="]');
      const m = /[?&]s=([^&#]*)/.exec(attr(form, 'action'));
      if (m) {
        try {
          key = decodeURIComponent(m[1]);
        } catch (e) {
          key = '';
        }
      }
    }
    return /^[a-z_]+\.[a-z_]+$/.test(key) ? key : '';
  }

  /** The section's own name: the text of its heading without the ? beside it (its help popup and script are inside h1). */
  function sectionHeading(doc) {
    const h = sectionQ(doc, 'h1');
    if (!h) return '';
    let s = '';
    try {
      const kids = h.childNodes;
      if (kids && typeof kids.length === 'number') {
        for (let i = 0; i < kids.length; i++) if (kids[i] && kids[i].nodeType === 3) s += String(kids[i].textContent || '');
      } else {
        s = String(h.textContent || '');
      }
    } catch (e) {
      s = '';
    }
    return s.replace(/\s+/g, ' ').trim().slice(0, 80);
  }

  const SET_TASKS = {
    SS1: task('sset', 'SS1', 3, { target: '#st-save', label: showKey('sset', 'SS1'), kind: 'button' }),
    SS2: task('sset', 'SS2', 1, { target: '#st-undo', label: showKey('sset', 'SS2'), kind: 'button' }),
    SS3: task('sset', 'SS3', 3, { target: 'button[data-restore]', label: showKey('sset', 'SS3'), kind: 'button' }),
    SS4: task('sset', 'SS4', 2, { target: '.st-irow button[data-open]', label: showKey('sset', 'SS4'), kind: 'button' }),
    SS5: task('sset', 'SS5', 2, { target: 'a[data-back]', label: showKey('sset', 'SS5'), kind: 'button' })
  };

  /** The generic page of a settings section, and the dispatcher of the sections that have a page of their own. */
  const settingsSectionPage = {
    group: 'sset',
    states: ['formular', 'liste', 'kunLaes'],
    flags: ['historik'],

    name: function (ctx) {
      const h = sectionHeading(ctx ? ctx.doc : null);
      return h ? ['help.sset.name.sektion', h] : 'help.sset.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = { historik: !!sectionQ(doc, 'button[data-restore]') };
      const data = { section: sectionKeyOf(ctx) };
      let state = 'unknown';
      if (sectionQ(doc, '.st-flash-info')) state = 'kunLaes';
      else if (sectionQ(doc, '#st-form')) state = 'formular';
      else if (sectionQ(doc, '.st-irow')) state = 'liste';
      return { state: state, flags: flags, data: data };
    },

    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'formular') return ['help.sset.about.formular'];
      if (s === 'liste') return ['help.sset.about.liste'];
      if (s === 'kunLaes') return ['help.sset.about.kunlaes'];
      return [];
    },

    tasks: SET_TASKS,

    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const hist = !!(st && st.flags && st.flags.historik);
      if (s === 'formular') return hist ? ['SS1', 'SS2', 'SS3', 'SS5'] : ['SS1', 'SS2', 'SS5'];
      if (s === 'liste') return ['SS4', 'SS5'];
      if (s === 'kunLaes') return ['SS5'];
      return [];
    },

    fields: {},

    shortcuts: [
      { keys: 'Ctrl+S', text: 'help.sset.key.01', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.sset.q.q1.q',
        words: {
          da: ['rette', 'ret', 'låst', 'læseadgang', 'adgang', 'grå', 'gem', 'kan ikke'],
          en: ['edit', 'change', 'locked', 'read-only', 'access', 'grey', 'save', 'cannot'],
          no: ['rette', 'endre', 'låst', 'lesetilgang', 'tilgang', 'grå', 'lagre', 'kan ikke']
        },
        intro: 'help.sset.q.q1.intro',
        steps: ['help.sset.q.q1.step1']
      }
    },

    questionOrder: function (ctx, st) {
      return st && st.state === 'kunLaes' ? ['Q1'] : [];
    },

    signal: function () {
      return false;
    },

    // Spec §24: the sections with a page of their own (SaldiHelpState.resolve()).
    variants: SECTIONS,
    variant: function (ctx) {
      return sectionKeyOf(ctx);
    },
    errors: SECTION_ERRORS
  };

  PAGES['systemdata/settingsSection.php'] = settingsSectionPage;

  // ==== S1: the hub, personal settings, Virksomhed, Finans, Dokumenter, Afdelinger og ansatte, Import og eksport
  // Evidence is systemdata/ unless named. Every section of S1 is a variant of systemdata/settingsSection.php (spec §24),
  // registered with addSection(); the hub, personal settings and the pages the sections lead to are ordinary routes.

  // ------------------------------------------------------------------ S1 helpers

  /** The first element of the frame document for a selector, or null (never throws). */
  function s1q(doc, sel) {
    try {
      const el = doc && doc.querySelector ? doc.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  /** Every element for a selector, as a plain array. */
  function s1all(doc, sel) {
    const out = [];
    try {
      const list = doc && doc.querySelectorAll ? doc.querySelectorAll(sel) : [];
      for (let i = 0; i < list.length; i++) if (list[i] && list[i].nodeType === 1) out.push(list[i]);
    } catch (e) {
      return [];
    }
    return out;
  }

  /** The current value of a field, '' for anything else. */
  function s1val(el) {
    try {
      return el && el.nodeType === 1 && typeof el.value === 'string' ? el.value : '';
    } catch (e) {
      return '';
    }
  }

  /** A checkbox's state: the live property in a browser, else the checked attribute. */
  function s1checked(el) {
    if (!el || el.nodeType !== 1) return false;
    try {
      if (typeof el.checked === 'boolean') return el.checked;
    } catch (e) {
      return false;
    }
    return el.getAttribute('checked') !== null;
  }

  /** A parameter of the frame's address, '' without one. */
  function s1param(ctx, name) {
    try {
      return ctx && ctx.url && ctx.url.searchParams ? String(ctx.url.searchParams.get(name) || '') : '';
    } catch (e) {
      return '';
    }
  }

  /** The first descendant of el for a selector, or null. */
  function s1in(el, sel) {
    try {
      const x = el && el.nodeType === 1 && el.querySelector ? el.querySelector(sel) : null;
      return x && x.nodeType === 1 ? x : null;
    } catch (e) {
      return null;
    }
  }

  /** The closest ancestor (or el itself) for a selector, or null. */
  function s1up(el, sel) {
    try {
      const x = el && el.nodeType === 1 && el.closest ? el.closest(sel) : null;
      return x && x.nodeType === 1 ? x : null;
    } catch (e) {
      return null;
    }
  }

  /**
   * A show target that rings only in the listed states: the state the shell passes, else the page's own detect().
   * target: a selector or a function (ctx, st) → Element|null.
   */
  function s1only(detect, states, target) {
    return function (ctx, st) {
      let d = st && st.state ? st : null;
      if (!d) {
        try {
          d = detect(ctx);
        } catch (e) {
          d = null;
        }
      }
      if (!d || states.indexOf(d.state) < 0) return null;
      return typeof target === 'function' ? target(ctx, d) : s1q(ctx ? ctx.doc : null, target);
    };
  }

  /**
   * The field key of a settings section's control (includes/settings/components.php:808, rowsView.php:290): a field is
   * f[<group>.<section>.<name>(.<n>)] (the n of a per-language or per-till copy, settingsDefinitions.php:1671-1680),
   * a table cell r[<table>][<row>][<column>]. map turns a name or 'table.column' into the help's field key; any other
   * control keeps the default rule.
   */
  function s1fieldKeyFn(map) {
    return function (el) {
      const n = attr(el, 'name');
      let m = /^f\[([a-z0-9_.]+)\]$/.exec(n);
      if (m) {
        const parts = m[1].split('.').filter(function (p) { return !/^\d+$/.test(p); });
        const k = parts.length ? parts[parts.length - 1] : '';
        if (Object.prototype.hasOwnProperty.call(map, k)) return map[k];
        return /^[a-z_]+$/.test(k) ? k : null;
      }
      m = /^r\[([a-z_]+)\]\[[a-z0-9_]+\]\[([a-z0-9_]+)\]$/.exec(n);
      if (m) {
        const k = m[1] + '.' + m[2];
        return Object.prototype.hasOwnProperty.call(map, k) ? map[k] : null;
      }
      return null;
    };
  }

  /** The parts every settings section page shows (systemdata/settingsSection.php:540-571, rowsView.php:118-160). */
  function s1base(doc) {
    return {
      kunLaes: !!s1q(doc, '.st-flash-info'),
      fejl: !!(s1q(doc, '.st-flash-err') || s1q(doc, '.st-errors') || s1q(doc, '.st-errorlist')),
      form: !!s1q(doc, '#st-form'),
      list: !!s1q(doc, '.st-irow')
    };
  }

  function s1fields(g, keys) {
    const out = {};
    keys.forEach(function (k) { out[k] = fieldKeys(g, k); });
    return out;
  }

  function s1words(da, en, no) {
    return { da: da, en: en, no: no };
  }

  /** Ctrl+S on a form section (javascript/settingsSection.js:233); Enter and Esc in a table cell as well (settingsRows.js:107-121). */
  function s1keys(g, rows) {
    const out = [{ keys: 'Ctrl+S', text: 'help.' + g + '.key.01', group: 'keys' }];
    if (rows) {
      out.push({ keys: 'Enter', text: 'help.' + g + '.key.02', group: 'keys' });
      out.push({ keys: 'Esc', text: 'help.' + g + '.key.03', group: 'keys' });
    }
    return out;
  }

  /** A section page: the settings layout's common states and keys around the section's own content. */
  function s1section(o) {
    const g = o.group;
    const page = {
      group: g,
      states: o.states,
      flags: o.flags,
      name: function () { return 'help.' + g + '.name'; },
      detect: function (ctx) {
        try {
          return o.detect(ctx);
        } catch (e) {
          return { state: 'unknown', flags: {}, data: {} };
        }
      },
      about: o.about,
      tasks: o.tasks,
      order: o.order,
      problem: function (ctx, st) {
        return o.problem ? o.problem(ctx, st) : '';
      },
      fields: o.fields || {},
      fieldKey: s1fieldKeyFn(o.fieldMap || {}),
      shortcuts: o.shortcuts || s1keys(g, !!o.rows),
      questions: o.questions || {},
      questionOrder: o.questionOrder || function () { return []; },
      signal: function () {
        return false;
      }
    };
    return page;
  }

  // ------------------------------------------------------------------ Indstillinger, the hub (systemdata/settings.php)
  // Reached from the sidebar's Indstillinger (index/main.php), the breadcrumb and Tilbage of every settings page; it opens in
  // the frame. ?g=virksomhed|finans|salg|koeb_lager only marks that group's panel for a moment (javascript/settingsHub.js:503-511).
  // settings.php:190-256 and includes/settings/hub.php:
  // - #sh-search, the search (:199) with / to focus it, Enter or Down to the first hit, Esc to clear (settingsHub.js:428-486);
  // - #sh-grid with a panel per group (:222-230); each sub-group is a button#sh-b-<sub> that folds open its pages (:296-325),
  //   ids from getSettingsHub() (hub.php:59-185): firma, brugere, formularer, integrationer, regnskab, bogfoering, afdelinger,
  //   data, debitorer, ordrer, kasse, booking, kreditorer, varer, lager, saerlige;
  // - a row's badge (.sh-row[data-row] .sk-status) and its attention line .sh-attn with a button (:310-314); the attention
  //   comes from settings_attention() (settingsRegistry.php:493-523: sender e-mail missing, MobilePay webhook, role review);
  //   Grundopsætning mangler (7096) on Debitorgrupper, Kreditorgrupper and Varegrupper without an active group in the year
  //   (hub.php:744-748, settingsRegistry.php:545-549);
  // - no access at all: .sk-note.sk-warn 5665 and no grid (:205-206); read only: div.sk-note.sk-info[role=status] 6031 (:207-208);
  //   the transition banner form.sh-notice (:211-218); Hvor er de gamle menupunkter? a[href="settingsMoved.php"] (:238).
  const S1_HUB = 'shub';

  function s1hubDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    const grid = s1q(doc, '#sh-grid');
    const warnRows = ['sales.debtor_groups', 'purchase.creditor_groups', 'items.item_groups'].filter(function (r) {
      return !!s1q(doc, '.sh-row[data-row="' + r + '"] .sk-status.sk-warn');
    });
    const flags = {
      opmaerksomhed: !!s1q(doc, '.sh-attn'),
      grundopsaetning: warnRows.length > 0,
      nyStruktur: !!s1q(doc, 'form.sh-notice')
    };
    let state = 'unknown';
    if (!grid && s1q(doc, '.sk-note.sk-warn')) state = 'ingenAdgang';
    else if (grid && s1q(doc, 'div.sk-note.sk-info[role="status"]')) state = 'kunLaes';
    else if (grid && s1val(s1q(doc, '#sh-search')).trim() !== '') state = 'soeg';
    else if (grid) state = 'oversigt';
    return { state: state, flags: flags, data: { gruppe: s1param(ctx, 'g'), mangler: warnRows } };
  }

  /** The sub-group row (button.sh-btn) that holds an element: its pages are folded away until it is opened. */
  function s1hubRowOf(el) {
    const item = s1up(el, '.sh-item');
    return item ? s1in(item, 'button.sh-btn') : null;
  }

  const S1_HUB_GROUP_ROW = { 'sales.debtor_groups': '#sh-b-debitorer', 'purchase.creditor_groups': '#sh-b-kreditorer', 'items.item_groups': '#sh-b-varer' };

  const S1_HUB_TASKS = {
    HB1: task(S1_HUB, 'HB1', 3, { target: '#sh-search', label: showKey(S1_HUB, 'HB1'), kind: 'field' }),
    // advice, in the order the hub's own groups have them (hub.php:59-150)
    HB2: task(S1_HUB, 'HB2', 4, { target: '#sh-b-firma', label: showKey(S1_HUB, 'HB2'), kind: 'button' }),
    HB3: task(S1_HUB, 'HB3', 3, { target: '#sh-b-firma', label: showKey(S1_HUB, 'HB3'), kind: 'button' }),
    HB4: task(S1_HUB, 'HB4', 2, { target: '#sh-b-regnskab', label: showKey(S1_HUB, 'HB4'), kind: 'button' }),
    HB5: task(S1_HUB, 'HB5', 2, { target: '#sh-b-regnskab', label: showKey(S1_HUB, 'HB5'), kind: 'button' }),
    HB6: task(S1_HUB, 'HB6', 3, { target: '#sh-b-formularer', label: showKey(S1_HUB, 'HB6'), kind: 'button' }),
    // the row that carries the attention line; its own button sits in the folded part (settings.php:286-314)
    HB7: task(S1_HUB, 'HB7', 3, {
      target: function (ctx) { return s1hubRowOf(s1q(ctx ? ctx.doc : null, '.sh-attn')); },
      label: showKey(S1_HUB, 'HB7'), kind: 'row'
    }),
    HB8: task(S1_HUB, 'HB8', 3, {
      target: function (ctx) {
        const doc = ctx ? ctx.doc : null;
        const rows = Object.keys(S1_HUB_GROUP_ROW);
        for (let i = 0; i < rows.length; i++) {
          if (s1q(doc, '.sh-row[data-row="' + rows[i] + '"] .sk-status.sk-warn')) return s1q(doc, S1_HUB_GROUP_ROW[rows[i]]);
        }
        return null;
      },
      label: showKey(S1_HUB, 'HB8'), kind: 'row'
    }),
    HB9: task(S1_HUB, 'HB9', 2, { target: 'a[href="settingsMoved.php"]', label: showKey(S1_HUB, 'HB9'), kind: 'button' })
  };

  const s1Hub = {
    group: S1_HUB,
    states: ['oversigt', 'soeg', 'kunLaes', 'ingenAdgang'],
    flags: ['opmaerksomhed', 'grundopsaetning', 'nyStruktur'],

    name: function () {
      return 'help.shub.name';
    },

    detect: function (ctx) {
      try {
        return s1hubDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },

    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'ingenAdgang') return ['help.shub.about.ingenadgang'];
      if (s === 'kunLaes') return ['help.shub.about.kunlaes'];
      if (s === 'soeg') return ['help.shub.about.soeg'];
      if (s === 'oversigt') return f.grundopsaetning ? ['help.shub.about.oversigt', 'help.shub.about.grundopsaetning'] : ['help.shub.about.oversigt'];
      return [];
    },

    tasks: S1_HUB_TASKS,

    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const pre = f.opmaerksomhed ? ['HB7'] : [];
      if (s === 'kunLaes') return ['HB1', 'HB9'];
      if (s === 'soeg') return unique(pre.concat(['HB1', 'HB9', 'HB2']));
      if (s === 'oversigt') {
        if (f.grundopsaetning) return unique(pre.concat(['HB2', 'HB8', 'HB1', 'HB3', 'HB4', 'HB5', 'HB6', 'HB9']));
        return unique(pre.concat(['HB1', 'HB4', 'HB5', 'HB3', 'HB6', 'HB2', 'HB9']));
      }
      return [];
    },

    problem: function (ctx, st) {
      const s = st ? st.state : '';
      return (s === 'oversigt' || s === 'soeg') && st.flags && st.flags.opmaerksomhed ? 'HB7' : '';
    },

    fields: { soeg: fieldKeys(S1_HUB, 'soeg') },

    // The search has no name, only an id (settings.php:199)
    fieldKey: function (el) {
      return attr(el, 'id') === 'sh-search' ? 'soeg' : null;
    },

    shortcuts: [
      { keys: '/', text: 'help.shub.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.shub.key.02', group: 'keys' },
      { keys: 'Esc', text: 'help.shub.key.03', group: 'keys' }
    ],

    questions: {
      Q1: {
        q: 'help.shub.q.q1.q',
        words: s1words(['personlige', 'sprog', 'adgangskode', 'tofaktor', 'mit', 'min', 'egen'],
          ['personal', 'language', 'password', 'two-factor', 'own', 'profile'],
          ['personlige', 'språk', 'passord', 'tofaktor', 'egen', 'profil']),
        steps: ['help.shub.q.q1.step1']
      },
      Q2: {
        q: 'help.shub.q.q2.q',
        words: s1words(['rette', 'ændre', 'læseadgang', 'adgang', 'administrator', 'låst'],
          ['change', 'edit', 'read', 'access', 'administrator', 'locked'],
          ['endre', 'rette', 'lesetilgang', 'tilgang', 'administrator', 'låst']),
        steps: ['help.shub.q.q2.step1']
      },
      Q3: {
        q: 'help.shub.q.q3.q',
        words: s1words(['først', 'nyt', 'regnskab', 'opsætning', 'starte', 'rækkefølge', 'begynde'],
          ['first', 'new', 'setup', 'start', 'order', 'begin'],
          ['først', 'nytt', 'regnskap', 'oppsett', 'starte', 'rekkefølge']),
        steps: stepKeys(S1_HUB, 'HB2', 1, 4)
      },
      Q4: {
        q: 'help.shub.q.q4.q',
        words: s1words(['gamle', 'menupunkt', 'diverse', 'valg', 'flyttet', 'hvor', 'systemdata'],
          ['old', 'menu', 'diverse', 'moved', 'where', 'item'],
          ['gamle', 'menypunkt', 'diverse', 'valg', 'flyttet', 'hvor']),
        steps: stepKeys(S1_HUB, 'HB9', 1, 2),
        show: { target: 'a[href="settingsMoved.php"]', label: showKey(S1_HUB, 'HB9'), kind: 'button' }
      }
    },

    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'kunLaes' || s === 'ingenAdgang') return ['Q2', 'Q1'];
      if (f.grundopsaetning) return ['Q3', 'Q4', 'Q1'];
      return ['Q4', 'Q1', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['systemdata/settings.php'] = s1Hub;

  // ---- Hvor er de gamle menupunkter? (systemdata/settingsMoved.php)
  // Linked from the hub's foot (settings.php:238) and from the notice of a moved address (settingsSection.php:584);
  // in the frame. The filter #sm-search hides rows that do not match (settingsMoved.php:66, 92-103); the column Nu (5727)
  // links the new place, tagged Personlig (5742) or Ikke på forsiden (7747) (:79-84); Tilbage a[data-back] (:62).
  const S1_MOVED_TASKS = {
    MV1: task(S1_HUB, 'MV1', 2, { target: '#sm-search', label: showKey(S1_HUB, 'MV1'), kind: 'field' }),
    MV2: task(S1_HUB, 'MV2', 1, { target: 'a[data-back]', label: showKey(S1_HUB, 'MV2'), kind: 'button' })
  };

  const s1Moved = {
    group: S1_HUB,
    states: ['liste'],
    flags: [],
    name: function () { return 'help.shub.name_moved'; },
    detect: function (ctx) {
      return { state: s1q(ctx ? ctx.doc : null, '#sm-table') ? 'liste' : 'unknown', flags: {}, data: {} };
    },
    about: function (ctx, st) { return st && st.state === 'liste' ? ['help.shub.about.moved'] : []; },
    tasks: S1_MOVED_TASKS,
    order: function (ctx, st) { return st && st.state === 'liste' ? ['MV1', 'MV2'] : []; },
    fields: { filter: fieldKeys(S1_HUB, 'filter') },
    fieldKey: function (el) { return attr(el, 'id') === 'sm-search' ? 'filter' : null; },
    shortcuts: [],
    questions: {},
    questionOrder: function () { return []; },
    signal: function () { return false; }
  };

  PAGES['systemdata/settingsMoved.php'] = s1Moved;

  // ------------------------------------------------------------------ Personlige indstillinger (systemdata/personalSettings.php)
  // Opened from the user's name in the top bar and from the hub's lead text (settings.php:194, 6020); in the frame.
  // personalSettings.php: tabs ?tab=profile|security|access|notifications (:85, :495-500; Sikkerhed and Notifikationer only
  // for the company's own users, not a revisor); the tab of a form is also its hidden input tab (:550, :654, :756).
  // Profil (:552-646): #ps-email, #ps-tlf, #ps-confirm (current password for contact changes), #ps-text-size, #ps-language,
  // the warnings order_autocomplete, bank_status, #ps-warn; Gem #ps-submit. Sikkerhed (:650-712): #ps-twofactor, #ps-glkode,
  // #ps-nykode1, #ps-nykode2, Aktive sessioner with Log andre enheder ud (form.ps-inline-form, :704-707, only with more than
  // one session). Min adgang (:714-750). Notifikationer (:752-784): types[] and digest. A refused save shows .ps-flash-err
  // (5625, 5520, 7804; :432-446); an outside revisor or administrator gets .ps-notice 5533 and nothing else (:490-493).
  const S1_PS = 'spers';

  function s1psDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    const flags = {
      afvist: !!s1q(doc, '.ps-flash-err'),
      revisor: !!s1q(doc, 'nav.ps-tabs') && !s1q(doc, 'a[href*="tab=security"]')
    };
    let state = 'unknown';
    if (s1q(doc, '.ps-notice') && !s1q(doc, 'nav.ps-tabs')) state = 'udefra';
    else if (s1q(doc, 'nav.ps-tabs')) {
      const tab = s1param(ctx, 'tab') || s1val(s1q(doc, '#ps-form input[name="tab"]'));
      state = { security: 'sikkerhed', access: 'adgang', notifications: 'notifikationer' }[tab] || 'profil';
    }
    return { state: state, flags: flags, data: {} };
  }

  /** On its own tab the control itself, on another tab the link to that tab (the first step). */
  function s1psTab(sel, tab) {
    return function (ctx) {
      const doc = ctx ? ctx.doc : null;
      return s1q(doc, sel) || s1q(doc, 'nav.ps-tabs a[href*="tab=' + tab + '"]');
    };
  }

  const S1_PS_TASKS = {
    PS1: task(S1_PS, 'PS1', 2, { target: '#ps-language', label: fieldLabel(S1_PS, 'language_id'), kind: 'field' }),
    PS2: task(S1_PS, 'PS2', 2, { target: '#ps-text-size', label: fieldLabel(S1_PS, 'text_size'), kind: 'field' }),
    PS3: task(S1_PS, 'PS3', 3, { target: '#ps-email', label: fieldLabel(S1_PS, 'email'), kind: 'field' }),
    PS4: task(S1_PS, 'PS4', 3, { target: s1psTab('#ps-twofactor', 'security'), label: showKey(S1_PS, 'PS4'), kind: 'field' }),
    PS5: task(S1_PS, 'PS5', 3, { target: s1psTab('#ps-nykode1', 'security'), label: showKey(S1_PS, 'PS5'), kind: 'field' }),
    PS6: task(S1_PS, 'PS6', 2, { target: 'form.ps-inline-form button[type="submit"]', label: showKey(S1_PS, 'PS6'), kind: 'button' }),
    PS7: task(S1_PS, 'PS7', 2, { target: 'nav.ps-tabs a[href*="tab=access"]', label: showKey(S1_PS, 'PS7'), kind: 'button' }),
    PS8: task(S1_PS, 'PS8', 3, { target: s1psTab('input[name="types[]"]', 'notifications'), label: showKey(S1_PS, 'PS8'), kind: 'field' }),
    // the field the refused change needs: the current password of the tab
    PS9: task(S1_PS, 'PS9', 3, {
      target: function (ctx) { return s1q(ctx ? ctx.doc : null, '#ps-confirm') || s1q(ctx ? ctx.doc : null, '#ps-glkode'); },
      label: showKey(S1_PS, 'PS9'), kind: 'field'
    })
  };

  const s1Personal = {
    group: S1_PS,
    states: ['profil', 'sikkerhed', 'adgang', 'notifikationer', 'udefra'],
    flags: ['afvist', 'revisor'],
    name: function () { return 'help.spers.name'; },
    detect: function (ctx) {
      try {
        return s1psDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'udefra') return ['help.spers.about.udefra'];
      if (s === 'profil') return f.revisor ? ['help.spers.about.profil', 'help.spers.about.revisor'] : ['help.spers.about.profil'];
      if (s === 'sikkerhed') return ['help.spers.about.sikkerhed'];
      if (s === 'adgang') return ['help.spers.about.adgang'];
      if (s === 'notifikationer') return ['help.spers.about.notifikationer'];
      return [];
    },
    tasks: S1_PS_TASKS,
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const pre = f.afvist && (s === 'profil' || s === 'sikkerhed') ? ['PS9'] : [];
      if (s === 'profil') return pre.concat(f.revisor ? ['PS1', 'PS2', 'PS7'] : ['PS1', 'PS3', 'PS4', 'PS5', 'PS2', 'PS7', 'PS8']);
      if (s === 'sikkerhed') return pre.concat(['PS4', 'PS5', 'PS6', 'PS7']);
      if (s === 'adgang') return f.revisor ? ['PS7', 'PS1'] : ['PS7', 'PS4', 'PS1'];
      if (s === 'notifikationer') return ['PS8', 'PS1'];
      return [];
    },
    problem: function (ctx, st) {
      const s = st ? st.state : '';
      return st && st.flags && st.flags.afvist && (s === 'profil' || s === 'sikkerhed') ? 'PS9' : '';
    },
    // the default rule maps nykode1 and nykode2 to nykode
    fields: s1fields(S1_PS, ['email', 'tlf', 'confirm_kode', 'text_size', 'language_id', 'order_autocomplete', 'bank_status',
      'due_date_warning_days', 'twofactor', 'glkode', 'nykode', 'digest']),
    shortcuts: [],
    questions: {
      Q1: {
        q: 'help.spers.q.q1.q',
        words: s1words(['gemt', 'adgangskode', 'email', 'telefon', 'forkert', 'mangler', 'tofaktor'],
          ['saved', 'password', 'email', 'phone', 'wrong', 'missing', 'two-factor'],
          ['lagret', 'passord', 'epost', 'telefon', 'feil', 'mangler', 'tofaktor']),
        steps: ['help.spers.q.q1.step1', 'help.spers.q.q1.step2']
      },
      Q2: {
        q: 'help.spers.q.q2.q',
        words: s1words(['rolle', 'adgang', 'rettigheder', 'administrator', 'må', 'ikke', 'se'],
          ['role', 'access', 'rights', 'permission', 'administrator'],
          ['rolle', 'tilgang', 'rettigheter', 'administrator']),
        steps: stepKeys(S1_PS, 'PS7', 1, 2),
        show: { target: 'nav.ps-tabs a[href*="tab=access"]', label: showKey(S1_PS, 'PS7'), kind: 'button' }
      }
    },
    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'udefra') return [];
      // Profil and Sikkerhed hold the e-mail and the password; the role question belongs to Min adgang
      if (st.flags && st.flags.afvist) return ['Q1', 'Q2'];
      return s === 'adgang' || s === 'notifikationer' ? ['Q2', 'Q1'] : ['Q1', 'Q2'];
    },
    signal: function () { return false; }
  };

  PAGES['systemdata/personalSettings.php'] = s1Personal;

  // ------------------------------------------------------------------ Brugere og roller (systemdata/usersRoles.php)
  // One route, three views by ?tab= (usersRoles.php:569-581): the people list (users), the roles editor (roles) and the
  // audit log (log); the page opens with read on any of settings.users.manage, settings.roles.manage, settings.audit.read
  // (:84-86), else require_permission() prints the refusal card div[role=alert] with Tilbage til Oversigt (7805,
  // includes/permissions.php perm_refuse()). Evidence, systemdata/usersRolesIncludes/view.php unless named. The markup
  // differs between master (#1033) and the roles branch in this base (a6cbca4c); selectors below accept either:
  // - list: #ur-users rows tr[data-search] (.ur-nologin for an employee without a login, includes/settings/people.php
  //   ur_people_row()), the tick boxes input[name="ids[]"] and #ur-check-all only with write (:246, :266), #ur-search,
  //   #ur-status-filter, #ur-empty shown when nothing matches, the review banner #ur-review-banner and tr[data-review="1"]
  //   (:119-130, :265), the flash .sk-note[role=status] with sk-bad for a refused action (:132-134); Tilføj person is
  //   button[data-padd] (people.php ur_people_add_button()).
  // - user card: aside#ur-drawer with form#ur-user-form, input[name=id] 0 for a new login, input[name=mode] invite or
  //   classic (:324-327); #ur-role disabled for the own card, the last administrator or without write (:381); the
  //   footer .sk-drawer-f only with write (:443), with #ur-close-btn, Genåbn/Send invitation igen/Nulstil adgangskode as
  //   button[form=ur-reopen-form|ur-resend-form|ur-reset-form] (:450-461); Behold nuværende button[form=ur-confirm-keep]
  //   (:350); the invitation link .ur-note-col input[readonly] (:330-335); #ur-tfa, #ur-tlf, #ur-ip, #ur-ansat, details.ur-pw;
  //   on the branch some sit in folded details.ur-card-section (Sikkerhed, Avanceret), so Vis mig rings the fold's summary
  //   while it is closed. Tabs #ur-tab-stamdata/#ur-tab-login and the drawer's data-fane (people.php ur_people_card_head(),
  //   switched in place by javascript/usersPeople.js setFane()), Se log a.ur-plog.
  // - employee without a login: .ur-give with .ur-give-acts (Inviter som bruger, Opret login med adgangskode) only with
  //   write and not for one who has left (people.php ur_people_card_nologin()).
  // - roles: .ur-roles with form#ur-role-form (input[name=id] 0 for a new role); Gem is #ur-role-save on the branch and
  //   the primary submit of the panel on master; Ny rolle a[href*="tab=roles&rolle=0"]; Kopiér a[href*="copy_role="] or
  //   button[form=ur-copy-form]; Nulstil til standard #ur-role-reset or button[form=ur-reset-role-form]; Slet
  //   #ur-role-delete or #ur-del-role; a standard role has #ur-role-standard-diff or .ur-lock; the levels are radios
  //   input.sk-radio[name^="level["]; group buttons [data-group-level] or [data-all-write]; the branch's enforcement
  //   note .ur-role-enforcement (sk-info in deny).
  // - log: table.ur-aud, the filter bar .ur-log-bar (q, type, bruger, objekt, fra, til), the CSV link a[href*="export=csv"]
  //   only with Import og eksport, and details#ur-enforce / details.ur-enforce (Håndhævelse) only with read on Roller,
  //   whose radios name=mode carry checked for the mode in use.
  // Every view redraws in place (tabs of the card, filters, dialogs), so the page is inPlace.
  const S1_UR = 'sbrugere';

  /** The element itself, or the summary of the outermost closed details around it (the branch folds parts of the card). */
  function s1urOpen(el) {
    let out = el;
    try {
      // details elements from the field outwards, by closest(); the parent step tolerates a plain element tree
      for (let d = s1up(el, 'details'); d; d = s1up(d.parentNode || d.parent || null, 'details')) {
        if (!(d.open === true || d.getAttribute('open') !== null)) {
          const sum = s1in(d, 'summary');
          if (sum && sum !== el) out = sum;
        }
      }
    } catch (e) {
      return el;
    }
    return out;
  }

  function s1urDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    const q = function (sel) { return s1q(doc, sel); };
    const flags = {
      kunLaes: false, gennemgang: false, ingenTraef: false, fejl: !!q('.sk-note.sk-bad[role="status"]'), lukket: false,
      inviteret: false, forslag: false, rolleLaast: false, linkVist: false, invitation: false, stamdata: false,
      fratraadt: false, standard: false, egen: false, overEgne: false, afvis: false
    };
    let state = 'unknown';
    const form = q('#ur-user-form');
    if (q('.ur-roles')) {
      const rf = q('#ur-role-form');
      state = rf && s1val(s1in(rf, 'input[name="id"]')) === '0' ? 'nyRolle' : 'roller';
      const save = q('#ur-role-save') || q('#ur-role-form button.sk-btn-primary[type="submit"]');
      const canNew = !!q('a[href*="tab=roles&rolle=0"]');
      flags.kunLaes = !!rf && !save && !canNew;
      flags.overEgne = !!rf && !save && canNew;
      flags.standard = !!(q('#ur-role-standard-diff') || q('#ur-role-form .ur-lock'));
      flags.egen = !!(q('#ur-role-delete') || q('#ur-del-role'));
      flags.afvis = !!q('.ur-role-enforcement.sk-info');
    } else if (q('table.ur-aud')) {
      state = 'log';
      flags.afvis = !!q('input[name="mode"][value="deny"][checked]');
    } else if (q('#ur-users')) {
      flags.gennemgang = !!(q('#ur-review-banner') || q('#ur-users tr[data-review="1"]'));
      flags.stamdata = !!q('#ur-drawer[data-fane="stamdata"]');
      if (form) {
        const isNew = s1val(s1in(form, 'input[name="id"]')) === '0';
        state = isNew ? 'nyBruger' : 'bruger';
        flags.kunLaes = !q('#ur-drawer .sk-drawer-f');
        flags.invitation = isNew && !!s1in(form, 'input[name="mode"][value="invite"]');
        flags.lukket = !!q('button[form="ur-reopen-form"]');
        flags.inviteret = !!q('button[form="ur-resend-form"]');
        flags.forslag = !!q('button[form="ur-confirm-keep"]');
        flags.rolleLaast = !isNew && !flags.kunLaes && !!q('#ur-role[disabled]');
        flags.linkVist = !!q('.ur-note-col input[readonly]');
      } else if (q('.ur-give')) {
        state = 'person';
        const acts = !!q('.ur-give .ur-give-acts');
        flags.fratraadt = !acts && !!q('.ur-give p.sk-muted');
        flags.kunLaes = !acts && !flags.fratraadt;
      } else {
        state = 'liste';
        flags.kunLaes = !q('#ur-check-all');
        flags.ingenTraef = !!q('#ur-empty:not([hidden])');
      }
    } else if (q('div[role="alert"] a[target="_top"]')) {
      state = 'ingenAdgang';
    }
    return { state: state, flags: flags, data: {} };
  }

  /** A target that rings only in the listed states; target is a selector list (first hit wins) or a function. */
  function s1urOnly(states, target) {
    return s1only(s1urDetect, states, function (ctx, d) {
      const doc = ctx ? ctx.doc : null;
      if (typeof target === 'function') return target(ctx, d);
      for (let i = 0; i < target.length; i++) {
        const el = s1q(doc, target[i]);
        if (el) return s1urOpen(el);
      }
      return null;
    });
  }

  const S1_UR_LIST_ROW = '#ur-users tbody tr[data-search]:not(.ur-nologin):not([data-self]) a.ur-name-a';
  const S1_UR_CARD = ['bruger'];
  const S1_UR_ROLES = ['roller', 'nyRolle'];
  function s1urTask(id, n, target, kind, extra) {
    return task(S1_UR, id, n, target ? { target: target, label: showKey(S1_UR, id), kind: kind || 'button' } : null, extra);
  }

  const S1_UR_TASKS = {
    BR1: s1urTask('BR1', 4, s1urOnly(['liste', 'person'], ['button[data-padd]'])),
    BR2: s1urTask('BR2', 3, s1urOnly(['liste', 'person'], ['button[data-padd]'])),
    BR3: s1urTask('BR3', 3, s1urOnly(['liste', 'bruger'], function (ctx, d) {
      const doc = ctx ? ctx.doc : null;
      if (d.state === 'liste') return s1q(doc, S1_UR_LIST_ROW);
      return d.flags && d.flags.stamdata ? s1q(doc, '#ur-tab-login') : s1urOpen(s1q(doc, '#ur-role'));
    }), 'field'),
    BR4: s1urTask('BR4', 3, s1urOnly(['liste'], ['#ur-users tbody input[name="ids[]"]']), 'field'),
    BR5: s1urTask('BR5', 2, s1urOnly(['liste'], ['#ur-search']), 'field'),
    BR6: s1urTask('BR6', 4, s1urOnly(['liste'], ['#ur-review-banner a', '#ur-users [data-suggest]', '#ur-review-filter'])),
    BR7: s1urTask('BR7', 4, s1urOnly(['liste', 'person'], ['button[data-padd]'])),
    BR8: s1urTask('BR8', 3, s1urOnly(['liste', 'bruger'], function (ctx, d) {
      const doc = ctx ? ctx.doc : null;
      return d.state === 'liste' ? s1q(doc, S1_UR_LIST_ROW) : s1q(doc, '#ur-close-btn');
    })),
    BR9: s1urTask('BR9', 2, s1urOnly(['liste', 'bruger', 'nyBruger'], ['a.st-menu-row[href*="tab=roles"]'])),
    BR10: s1urTask('BR10', 2, s1urOnly(['liste', 'bruger', 'roller', 'nyRolle'], ['a.st-menu-row[href*="tab=log"]'])),
    BR11: s1urTask('BR11', 3, s1urOnly(['liste'], ['#ur-status-filter']), 'field'),
    BK2: s1urTask('BK2', 3, s1urOnly(S1_UR_CARD, ['button[form="ur-reset-form"]'])),
    BK3: s1urTask('BK3', 2, s1urOnly(S1_UR_CARD, ['details.ur-pw summary'])),
    BK4: s1urTask('BK4', 3, s1urOnly(S1_UR_CARD, ['#ur-tfa']), 'field'),
    BK6: s1urTask('BK6', 2, s1urOnly(S1_UR_CARD, ['button[form="ur-reopen-form"]'])),
    BK7: s1urTask('BK7', 3, s1urOnly(S1_UR_CARD, ['button[form="ur-resend-form"]'])),
    BK8: s1urTask('BK8', 2, s1urOnly(S1_UR_CARD, ['button[form="ur-confirm-keep"]'])),
    BK9: s1urTask('BK9', 2, s1urOnly(S1_UR_CARD, ['#ur-ip']), 'field'),
    BK10: s1urTask('BK10', 2, s1urOnly(S1_UR_CARD, ['#ur-ansat']), 'field'),
    BK11: s1urTask('BK11', 2, s1urOnly(S1_UR_CARD, ['a.ur-plog'])),
    BK12: s1urTask('BK12', 2, s1urOnly(S1_UR_CARD, ['#ur-role']), 'field'),
    BK13: s1urTask('BK13', 3, s1urOnly(S1_UR_CARD, ['.ur-note-col input[readonly]']), 'field'),
    NB1: s1urTask('NB1', 3, s1urOnly(['nyBruger'], ['#ur-email']), 'field'),
    NB2: s1urTask('NB2', 3, s1urOnly(['nyBruger'], ['#ur-user-form input[name="kode"]']), 'field'),
    NB3: s1urTask('NB3', 3, s1urOnly(['nyBruger'], ['#ur-role']), 'field'),
    PE1: s1urTask('PE1', 3, s1urOnly(['person'], function (ctx, d) {
      const doc = ctx ? ctx.doc : null;
      return d.flags && d.flags.stamdata ? s1q(doc, '#ur-tab-login') : s1q(doc, '.ur-give-acts a.sk-btn-primary');
    })),
    PE2: s1urTask('PE2', 2, s1urOnly(['person', 'bruger'], ['#ur-tab-stamdata'])),
    RO1: s1urTask('RO1', 4, s1urOnly(S1_UR_ROLES, ['a[href*="tab=roles&rolle=0"]'])),
    RO2: s1urTask('RO2', 3, s1urOnly(S1_UR_ROLES, ['#ur-role-form input.sk-radio[name^="level["]']), 'field'),
    RO3: s1urTask('RO3', 2, s1urOnly(['roller'], ['a[href*="copy_role="]', 'button[form="ur-copy-form"]'])),
    RO4: s1urTask('RO4', 3, s1urOnly(['roller'], ['#ur-role-reset', 'button[form="ur-reset-role-form"]'])),
    RO5: s1urTask('RO5', 3, s1urOnly(['roller'], ['#ur-role-delete', '#ur-del-role'])),
    RO6: s1urTask('RO6', 1, s1urOnly(['roller'], ['.ur-role-impact a[href*="tab=users"]'])),
    RO7: s1urTask('RO7', 3, s1urOnly(S1_UR_ROLES, ['[data-group-level]', '[data-all-write]'])),
    RO8: s1urTask('RO8', 3, s1urOnly(S1_UR_ROLES, ['.ur-role-enforcement']), 'status'),
    LG1: s1urTask('LG1', 3, s1urOnly(['log'], ['.ur-log-bar select[name="bruger"]']), 'field'),
    LG2: s1urTask('LG2', 3, s1urOnly(['log'], ['.ur-log-bar select[name="type"]']), 'field'),
    LG3: s1urTask('LG3', 2, s1urOnly(['log'], ['a[href*="export=csv"]'])),
    LG4: s1urTask('LG4', 4, s1urOnly(['log'], ['details.ur-enforce summary'])),
    NA1: s1urTask('NA1', 2, s1urOnly(['ingenAdgang'], ['div[role="alert"] a[target="_top"]'])),
    FE: s1urTask('FE', 3, s1urOnly(['liste', 'bruger', 'nyBruger', 'person', 'roller', 'nyRolle', 'log'], ['.sk-note.sk-bad[role="status"]']), 'status')
  };

  function s1urQ(id, words, steps, show, intro) {
    const q = { q: 'help.' + S1_UR + '.q.' + id.toLowerCase() + '.q', words: words, steps: steps };
    if (intro) q.intro = 'help.' + S1_UR + '.q.' + id.toLowerCase() + '.intro';
    if (show) q.show = show;
    return q;
  }

  function s1urQs(id, from, to) {
    const out = [];
    for (let i = from; i <= to; i++) out.push('help.' + S1_UR + '.q.' + id.toLowerCase() + '.step' + i);
    return out;
  }

  const S1_UR_QUESTIONS = {
    Q1: s1urQ('Q1', s1words(['opret', 'oprette', 'ny', 'bruger', 'brugere', 'login', 'invitation', 'invitere', 'kollega', 'medarbejder'],
      ['create', 'new', 'user', 'login', 'invite', 'invitation', 'colleague', 'employee'],
      ['opprett', 'opprette', 'ny', 'bruker', 'innlogging', 'invitasjon', 'inviter', 'kollega', 'ansatt']),
    stepKeys(S1_UR, 'BR1', 1, 4), S1_UR_TASKS.BR1.show, true),
    Q2: s1urQ('Q2', s1words(['adgang', 'give', 'kollega', 'fakturering', 'faktura', 'kassekladde', 'bogføring', 'rolle', 'rettigheder', 'salg'],
      ['access', 'give', 'colleague', 'invoicing', 'invoice', 'journal', 'posting', 'role', 'permissions', 'sales'],
      ['tilgang', 'gi', 'kollega', 'fakturering', 'faktura', 'kassekladd', 'bokføring', 'rolle', 'rettigheter', 'salg']),
    s1urQs('Q2', 1, 4), S1_UR_TASKS.BR3.show, true),
    Q3: s1urQ('Q3', s1words(['ny', 'rolle', 'roller', 'lave', 'egen', 'opret', 'kopiér', 'rettigheder'],
      ['new', 'role', 'roles', 'make', 'custom', 'create', 'copy', 'permissions'],
      ['ny', 'rolle', 'roller', 'lage', 'egen', 'opprett', 'kopier', 'rettigheter']),
    stepKeys(S1_UR, 'RO1', 1, 4), {
      target: function (ctx, st) {
        return S1_UR_TASKS.RO1.show.target(ctx, st) || S1_UR_TASKS.BR9.show.target(ctx, st);
      },
      label: showKey(S1_UR, 'RO1'), kind: 'button'
    }, true),
    Q4: s1urQ('Q4', s1words(['menupunkt', 'menu', 'side', 'kollega', 'adgang', 'afvist', 'mangler', 'åbne', 'rolle'],
      ['menu', 'item', 'page', 'colleague', 'access', 'denied', 'missing', 'open', 'role'],
      ['menypunkt', 'meny', 'side', 'kollega', 'tilgang', 'avvist', 'mangler', 'åpne', 'rolle']),
    s1urQs('Q4', 1, 4), S1_UR_TASKS.BR3.show, true),
    Q5: s1urQ('Q5', s1words(['revisor', 'bogholder', 'revision', 'ekstern', 'adgang', 'udefra'],
      ['auditor', 'accountant', 'bookkeeper', 'external', 'access'],
      ['revisor', 'regnskapsfører', 'ekstern', 'tilgang', 'utenfra']),
    stepKeys(S1_UR, 'BR7', 1, 4), S1_UR_TASKS.BR7.show, true),
    Q6: s1urQ('Q6', s1words(['kun', 'visning', 'læseadgang', 'ændre', 'stadig', 'log', 'afvis', 'håndhævelse'],
      ['view', 'only', 'read', 'change', 'still', 'log', 'deny', 'enforcement'],
      ['kun', 'visning', 'lesetilgang', 'endre', 'fortsatt', 'logg', 'avvis', 'håndheving']),
    stepKeys(S1_UR, 'RO8', 1, 1).concat(s1urQs('Q6', 2, 2), stepKeys(S1_UR, 'LG4', 3, 3)), S1_UR_TASKS.LG4.show, true),
    Q7: s1urQ('Q7', s1words(['egen', 'rolle', 'låst', 'ændre', 'administrator', 'sidste'],
      ['own', 'role', 'locked', 'change', 'administrator', 'last'],
      ['egen', 'rolle', 'låst', 'endre', 'administrator', 'siste']),
    stepKeys(S1_UR, 'BK12', 1, 2), S1_UR_TASKS.BK12.show),
    Q8: s1urQ('Q8', s1words(['lukke', 'luk', 'slette', 'slet', 'bruger', 'stoppet', 'fratrådt'],
      ['close', 'delete', 'user', 'left', 'remove'],
      ['stenge', 'lukk', 'slette', 'slett', 'bruker', 'sluttet', 'fratrådt']),
    s1urQs('Q8', 1, 3), S1_UR_TASKS.BR8.show),
    Q9: s1urQ('Q9', s1words(['glemt', 'adgangskode', 'kodeord', 'nulstil', 'password', 'login'],
      ['forgotten', 'forgot', 'password', 'reset', 'login'],
      ['glemt', 'passord', 'nullstill', 'innlogging']),
    stepKeys(S1_UR, 'BK2', 1, 3), S1_UR_TASKS.BK2.show),
    Q10: s1urQ('Q10', s1words(['invitation', 'mail', 'email', 'kommet', 'modtaget', 'link', 'igen', 'udløbet'],
      ['invitation', 'mail', 'email', 'arrived', 'received', 'link', 'again', 'expired'],
      ['invitasjon', 'epost', 'kommet', 'mottatt', 'lenke', 'igjen', 'utløpt']),
    stepKeys(S1_UR, 'BK7', 1, 3), S1_UR_TASKS.BK7.show),
    Q11: s1urQ('Q11', s1words(['ikke', 'bekræftet', 'migreret', 'forslag', 'gennemgå', 'tilpasset'],
      ['not', 'confirmed', 'migrated', 'suggestion', 'review', 'custom'],
      ['ikke', 'bekreftet', 'migrert', 'forslag', 'gjennomgå', 'tilpasset']),
    stepKeys(S1_UR, 'BR6', 2, 4), S1_UR_TASKS.BR6.show, true),
    Q12: s1urQ('Q12', s1words(['adgang', 'side', 'afvist', 'ingen', 'brugere', 'roller'],
      ['access', 'page', 'denied', 'users', 'roles'],
      ['tilgang', 'side', 'avvist', 'brukere', 'roller']),
    stepKeys(S1_UR, 'NA1', 1, 2), S1_UR_TASKS.NA1.show),
    Q13: s1urQ('Q13', s1words(['ændre', 'låst', 'gemme', 'knap', 'læseadgang', 'grå'],
      ['change', 'locked', 'save', 'button', 'read', 'grey'],
      ['endre', 'låst', 'lagre', 'knapp', 'lesetilgang', 'grå']),
    s1urQs('Q13', 1, 3)),
    Q14: s1urQ('Q14', s1words(['hvem', 'ændret', 'log', 'historik', 'rolle', 'bruger'],
      ['who', 'changed', 'log', 'history', 'role', 'user'],
      ['hvem', 'endret', 'logg', 'historikk', 'rolle', 'bruker']),
    s1urQs('Q14', 1, 2), S1_UR_TASKS.LG2.show)
  };

  /** The usersRoles.php field keys: the radios level[<key>] are one field; the list and editor searches by id. */
  function s1urFieldKey(el) {
    const n = attr(el, 'name');
    if (/^level\[/.test(n)) return 'level';
    if (n === 'kode2') return 'kode';
    if (['email', 'brugernavn', 'kode', 'role_id', 'twofactor', 'tlf', 'ansat_id', 'ip_address', 'revisor', 'navn', 'beskrivelse',
      'p_login', 'p_emp', 'type', 'bruger', 'mode'].indexOf(n) >= 0) return n;
    const id = attr(el, 'id');
    return { 'ur-search': 'soeg', 'ur-role-search': 'rettighedsoeg', 'ur-role-template': 'skabelon' }[id] || null;
  }

  const s1UsersRoles = {
    group: S1_UR,
    states: ['liste', 'bruger', 'nyBruger', 'person', 'roller', 'nyRolle', 'log', 'ingenAdgang'],
    flags: ['kunLaes', 'gennemgang', 'ingenTraef', 'fejl', 'lukket', 'inviteret', 'forslag', 'rolleLaast', 'linkVist', 'invitation',
      'stamdata', 'fratraadt', 'standard', 'egen', 'overEgne', 'afvis'],
    inPlace: true,
    name: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'roller' || s === 'nyRolle') return 'help.sbrugere.name.roller';
      if (s === 'log') return 'help.sbrugere.name.log';
      if (s === 'liste' || s === 'bruger' || s === 'nyBruger' || s === 'person') return 'help.sbrugere.name.brugere';
      return 'help.sbrugere.name.adgang';
    },
    detect: function (ctx) {
      try {
        return s1urDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const mode = f.afvis ? 'help.sbrugere.about.afvis' : 'help.sbrugere.about.logkun';
      if (s === 'liste') {
        if (f.kunLaes) return ['help.sbrugere.about.liste', 'help.sbrugere.about.kunlaes'];
        return f.gennemgang ? ['help.sbrugere.about.liste', 'help.sbrugere.about.gennemgang'] : ['help.sbrugere.about.liste'];
      }
      if (s === 'bruger') {
        if (f.kunLaes) return ['help.sbrugere.about.bruger', 'help.sbrugere.about.kunlaes'];
        if (f.lukket) return ['help.sbrugere.about.bruger', 'help.sbrugere.about.lukket'];
        if (f.inviteret) return ['help.sbrugere.about.bruger', 'help.sbrugere.about.inviteret'];
        if (f.forslag) return ['help.sbrugere.about.bruger', 'help.sbrugere.about.gennemgang'];
        if (f.rolleLaast) return ['help.sbrugere.about.bruger', 'help.sbrugere.about.rollelaast'];
        return ['help.sbrugere.about.bruger'];
      }
      if (s === 'nyBruger') return [f.invitation ? 'help.sbrugere.about.nybruger_invite' : 'help.sbrugere.about.nybruger_kode'];
      if (s === 'person') return f.fratraadt ? ['help.sbrugere.about.person', 'help.sbrugere.about.fratraadt'] : ['help.sbrugere.about.person'];
      if (s === 'roller') return [f.kunLaes ? 'help.sbrugere.about.kunlaes' : 'help.sbrugere.about.roller', mode];
      if (s === 'nyRolle') return ['help.sbrugere.about.nyrolle', mode];
      if (s === 'log') return ['help.sbrugere.about.log', 'help.sbrugere.about.haandhaevelse'];
      if (s === 'ingenAdgang') return ['help.sbrugere.about.ingenadgang'];
      return [];
    },
    tasks: S1_UR_TASKS,
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const pre = f.fejl && s !== 'ingenAdgang' && s !== 'unknown' ? ['FE'] : [];
      let list = [];
      if (s === 'liste') {
        if (f.kunLaes) list = ['BR5', 'BR10'];
        else {
          list = ['BR1', 'BR3', 'BR5', 'BR7', 'BR4', 'BR11', 'BR9', 'BR10', 'BR2', 'BR8'];
          if (f.ingenTraef) list = ['BR5'].concat(list);
          if (f.gennemgang) list = ['BR6'].concat(list);
        }
      } else if (s === 'bruger') {
        if (f.kunLaes) list = ['BK11', 'BR10'];
        else if (f.lukket) list = ['BK6', 'BK11'];
        else {
          list = ['BR3', 'BK2', 'BK4', 'BK3', 'BK9', 'BK10', 'BK11', 'BR8', 'PE2'];
          if (f.rolleLaast) list = ['BK12', 'BK2', 'BK4', 'BK3', 'BK9', 'BK10', 'BK11', 'PE2'];
          if (f.inviteret) list = ['BK7', 'BR3', 'BR8', 'BK10'];
          if (f.forslag) list = ['BK8'].concat(list);
          if (f.linkVist) list = ['BK13'].concat(list);
        }
      } else if (s === 'nyBruger') {
        list = f.invitation ? ['NB1', 'NB3', 'BR9'] : ['NB2', 'NB3', 'BR9'];
      } else if (s === 'person') {
        list = f.fratraadt || f.kunLaes ? ['PE2'] : ['PE1', 'PE2'];
      } else if (s === 'roller') {
        if (f.kunLaes || f.overEgne) list = f.overEgne ? ['RO1', 'RO6', 'RO8'] : ['RO6', 'RO8'];
        else {
          list = ['RO1', 'RO2', 'RO3', 'RO6'];
          if (f.standard) list.push('RO4');
          if (f.egen) list.push('RO5');
          list = list.concat(['RO7', 'RO8', 'BR10']);
        }
      } else if (s === 'nyRolle') {
        list = ['RO1', 'RO2', 'RO7', 'RO8'];
      } else if (s === 'log') {
        list = ['LG1', 'LG2', 'LG3', 'LG4'];
      } else if (s === 'ingenAdgang') {
        list = ['NA1'];
      }
      return unique(pre.concat(list));
    },
    problem: function (ctx, st) {
      const s = st ? st.state : '';
      return st && st.flags && st.flags.fejl && s !== 'ingenAdgang' && s !== 'unknown' && s !== '' ? 'FE' : '';
    },
    fields: s1fields(S1_UR, ['email', 'brugernavn', 'kode', 'role_id', 'twofactor', 'tlf', 'ansat_id', 'ip_address', 'revisor', 'navn',
      'beskrivelse', 'level', 'soeg', 'rettighedsoeg', 'skabelon', 'p_login', 'p_emp', 'type', 'bruger', 'mode']),
    fieldKey: s1urFieldKey,
    shortcuts: [{ keys: 'Esc', text: 'help.sbrugere.key.01', group: 'keys' }],
    questions: S1_UR_QUESTIONS,
    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'liste') {
        if (f.kunLaes) return ['Q13', 'Q4', 'Q5'];
        return f.gennemgang ? ['Q11', 'Q1', 'Q2'] : ['Q1', 'Q2', 'Q4'];
      }
      if (s === 'bruger') {
        if (f.kunLaes) return ['Q13', 'Q4', 'Q2'];
        if (f.lukket) return ['Q8', 'Q2', 'Q4'];
        if (f.inviteret) return ['Q10', 'Q2', 'Q4'];
        if (f.forslag) return ['Q11', 'Q2', 'Q4'];
        if (f.rolleLaast) return ['Q7', 'Q2', 'Q4'];
        return ['Q2', 'Q4', 'Q9'];
      }
      if (s === 'nyBruger') return ['Q5', 'Q2', 'Q10'];
      if (s === 'person') return ['Q1', 'Q5', 'Q2'];
      if (s === 'roller') return f.kunLaes || f.overEgne ? ['Q13', 'Q3', 'Q6'] : ['Q3', 'Q2', 'Q6'];
      if (s === 'nyRolle') return ['Q3', 'Q2', 'Q6'];
      if (s === 'log') return ['Q14', 'Q4', 'Q6'];
      if (s === 'ingenAdgang') return ['Q12', 'Q13', 'Q6'];
      return [];
    },
    signal: function () { return false; }
  };

  PAGES['systemdata/usersRoles.php'] = s1UsersRoles;

  // ------------------------------------------------------------------ Regnskabskort, the opening balance (systemdata/regnskabskort.php)
  // Reached from Åbningsbalance in a fiscal year's row (settingsDefinitions.php:197, rowsView.php:342) and after Opret nyt
  // regnskabsår (includes/settings/rowHooks.php:803-804); without an id it redirects to the section (regnskabskort.php:61-64).
  // In the frame. Three cards (:296-307): the first year fiscalYearInc/year1.php (form year1: beskrivelse, startmd, startaar,
  // slutmd, slutaar, aaben = Bogføring tilladt, fakt, modt, no_faktbill, faktbill, modtbill, then debet[n]/kredit[n] per
  // balance account, Gem/opdatér, :25-116); a later year yearX.php (form yearX: start fixed, the Resultat row
  // overfor_til[0] and per balance account Saldo, Overfør til overfor_til[n] and Ny Primo, Gem/opdatér, :36-207); a year
  // after a deleted one yearX1.php (form yearX1: primo as it stands, 6579, :17-41). Gem/opdatér writes the transfer:
  // regnskabskort.php:207-258 (year X: primo reset, then each Saldo added to the Overfør til account).
  const S1_RK = 'sregnkort';

  /** A Danish amount as the card prints it (1.234,56; -12,00), 0 for anything else. */
  function s1amount(s) {
    const t = String(s || '').replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
    const n = parseFloat(t);
    return isFinite(n) ? n : 0;
  }

  function s1rkDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    let state = 'unknown';
    if (s1q(doc, 'form[name="year1"]')) state = 'aar1';
    else if (s1q(doc, 'form[name="yearX"]')) state = 'aarX';
    else if (s1q(doc, 'form[name="yearX1"]')) state = 'aarX1';
    const flags = { ubalance: false, resultat: !!s1q(doc, 'select[name="overfor_til[0]"]') };
    if (state === 'aar1') {
      let sum = 0;
      s1all(doc, 'input[name^="debet["]').forEach(function (el) { sum += s1amount(s1val(el)); });
      s1all(doc, 'input[name^="kredit["]').forEach(function (el) { sum -= s1amount(s1val(el)); });
      flags.ubalance = Math.abs(sum) > 0.009;
    }
    return { state: state, flags: flags, data: { aar: s1val(s1q(doc, 'input[name="kodenr"]')) } };
  }

  const S1_RK_SAVE = 'input[type="submit"][name="submit"]';
  const S1_RK_TASKS = {
    RK1: task(S1_RK, 'RK1', 3, { target: s1only(s1rkDetect, ['aar1'], 'input[name^="debet["]'), label: fieldLabel(S1_RK, 'debet'), kind: 'field' }),
    RK2: task(S1_RK, 'RK2', 3, { target: s1only(s1rkDetect, ['aar1'], 'input[name^="debet["]'), label: fieldLabel(S1_RK, 'debet'), kind: 'field' }),
    RK3: task(S1_RK, 'RK3', 2, { target: s1only(s1rkDetect, ['aar1'], 'input[name="fakt"]'), label: fieldLabel(S1_RK, 'fakt'), kind: 'field' }),
    RK4: task(S1_RK, 'RK4', 3, { target: s1only(s1rkDetect, ['aarX'], S1_RK_SAVE), label: showKey(S1_RK, 'RK4'), kind: 'button' }),
    RK5: task(S1_RK, 'RK5', 2, { target: s1only(s1rkDetect, ['aarX'], 'select[name="overfor_til[0]"]'), label: fieldLabel(S1_RK, 'overfor_til'), kind: 'field' }),
    RK6: task(S1_RK, 'RK6', 2, { target: 'input[name="aaben"]', label: fieldLabel(S1_RK, 'aaben'), kind: 'field' }),
    // the first year's start is editable until a later year exists ($laast, year1.php:8, :44-49)
    RK7: task(S1_RK, 'RK7', 2, { target: s1only(s1rkDetect, ['aar1'], 'input[name="startmd"]:not([readonly])'), label: fieldLabel(S1_RK, 'startmd'), kind: 'field' })
  };

  const s1Regnskabskort = {
    group: S1_RK,
    states: ['aar1', 'aarX', 'aarX1'],
    flags: ['ubalance', 'resultat'],
    name: function (ctx, st) {
      const n = st && st.data && /^\d+$/.test(String(st.data.aar || '')) ? String(st.data.aar) : '';
      return n ? ['help.sregnkort.name', n] : 'help.sregnkort.name_plain';
    },
    detect: function (ctx) {
      try {
        return s1rkDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'aar1') return f.ubalance ? ['help.sregnkort.about.aar1', 'help.sregnkort.about.ubalance'] : ['help.sregnkort.about.aar1'];
      if (s === 'aarX') return ['help.sregnkort.about.aarx'];
      if (s === 'aarX1') return ['help.sregnkort.about.aarx1'];
      return [];
    },
    tasks: S1_RK_TASKS,
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'aar1') return f.ubalance ? ['RK2', 'RK1', 'RK3', 'RK7', 'RK6'] : ['RK1', 'RK3', 'RK7', 'RK6'];
      if (s === 'aarX') return f.resultat ? ['RK4', 'RK5', 'RK6'] : ['RK4', 'RK6'];
      if (s === 'aarX1') return ['RK6'];
      return [];
    },
    problem: function (ctx, st) {
      return st && st.state === 'aar1' && st.flags && st.flags.ubalance ? 'RK2' : '';
    },
    fields: s1fields(S1_RK, ['beskrivelse', 'startmd', 'startaar', 'slutmd', 'slutaar', 'aaben', 'fakt', 'modt', 'no_faktbill', 'faktbill', 'modtbill', 'debet',
      'kredit', 'overfor_til']),
    // debet[3], kredit[3] and overfor_til[3] are one account's line (year1.php:99-100, yearX.php:186)
    fieldKey: function (el) {
      const m = /^(debet|kredit|overfor_til)\[\d+\]$/.exec(attr(el, 'name'));
      return m ? m[1] : null;
    },
    // Gem/opdatér has accesskey g (year1.php:110, yearX.php:201)
    shortcuts: [{ keys: 'Alt+G', text: 'help.sregnkort.key.01', group: 'keys' }],
    shortcutsNote: 'help.common.shortcuts_note',
    questions: {
      Q1: {
        q: 'help.sregnkort.q.q1.q',
        words: s1words(['balance', 'stemmer', 'ikke', 'primo', 'åbningsbalance', 'difference'],
          ['balance', 'opening', 'does', 'not', 'difference', 'primo'],
          ['balanse', 'stemmer', 'ikke', 'primo', 'åpningsbalanse', 'differanse']),
        steps: stepKeys(S1_RK, 'RK2', 1, 3)
      },
      Q2: {
        q: 'help.sregnkort.q.q2.q',
        words: s1words(['overfør', 'til', 'resultat', 'egenkapital', 'saldo', 'nyt', 'år'],
          ['transfer', 'result', 'equity', 'balance', 'new', 'year'],
          ['overfør', 'resultat', 'egenkapital', 'saldo', 'nytt', 'år']),
        steps: ['help.sregnkort.q.q2.step1', 'help.sregnkort.q.q2.step2']
      }
    },
    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'aar1') return ['Q1'];
      if (s === 'aarX') return ['Q2'];
      return [];
    },
    signal: function () { return false; }
  };

  PAGES['systemdata/regnskabskort.php'] = s1Regnskabskort;

  // ------------------------------------------------------------------ Formularer (systemdata/formularkort.php)
  // The hub row Formularer under Fakturaformularer og e-mail (includes/settings/hub.php:91-92); in the frame, in the settings
  // layout with the head buttons Visuel editor (formeditor.php), Bg.Navn and Baggrund (logoupload.php?upload=yes, accesskey u)
  // (formularkort.php:300-311, includes/settings/layout.php:392-405). The selector form formularvalg: form_nr, art (Tekster,
  // Ordrelinjer, Streger, Flyt center, Mail tekst), sprog, Vælg (accesskey v) (:340-380); with a form and a type chosen the
  // lines below and Opdatér input[name=streger] (accesskey v) (:539). The reminder forms link the fees to Indstillinger (:441).
  const S1_FK = 'sformular';

  function s1fkDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    let state = 'unknown';
    const art = s1val(s1q(doc, 'select[name="art"]'));
    if (s1q(doc, 'input[name="streger"]')) state = /^5:/.test(art) ? 'mailtekst' : 'redigering';
    else if (s1q(doc, 'select[name="form_nr"]')) state = 'valg';
    return { state: state, flags: {}, data: {} };
  }

  const S1_FK_TASKS = {
    // The form tag opens inside the table, so the parser leaves the selects outside it: by name (live check 24.15); the
    // other form (streger) carries form_nr and art as hidden inputs, never as selects.
    FK1: task(S1_FK, 'FK1', 3, { target: 'select[name="form_nr"]', label: fieldLabel(S1_FK, 'form_nr'), kind: 'field' }),
    FK2: task(S1_FK, 'FK2', 3, { target: 'select[name="art"]', label: fieldLabel(S1_FK, 'art'), kind: 'field' }),
    FK3: task(S1_FK, 'FK3', 2, { target: '.st-phead-acts a[href*="formeditor.php"]', label: showKey(S1_FK, 'FK3'), kind: 'button' }),
    FK4: task(S1_FK, 'FK4', 2, { target: '.st-phead-acts a[href*="logoupload.php"]', label: showKey(S1_FK, 'FK4'), kind: 'button' }),
    FK5: task(S1_FK, 'FK5', 2, { target: 'input[name="streger"]', label: showKey(S1_FK, 'FK5'), kind: 'button' })
  };

  const s1Formularkort = {
    group: S1_FK,
    states: ['valg', 'redigering', 'mailtekst'],
    flags: [],
    name: function () { return 'help.sformular.name'; },
    detect: function (ctx) {
      try {
        return s1fkDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'valg') return ['help.sformular.about.valg'];
      if (s === 'redigering') return ['help.sformular.about.redigering'];
      if (s === 'mailtekst') return ['help.sformular.about.mailtekst'];
      return [];
    },
    tasks: S1_FK_TASKS,
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'valg') return ['FK1', 'FK3', 'FK2', 'FK4'];
      if (s === 'redigering') return ['FK5', 'FK1', 'FK3', 'FK4', 'FK2'];
      if (s === 'mailtekst') return ['FK2', 'FK5', 'FK1'];
      return [];
    },
    fields: s1fields(S1_FK, ['form_nr', 'art', 'sprog']),
    shortcuts: [
      { keys: 'Alt+V', text: 'help.sformular.key.01', group: 'keys' },
      { keys: 'Alt+U', text: 'help.sformular.key.02', group: 'keys' }
    ],
    shortcutsNote: 'help.common.shortcuts_note',
    questions: {
      Q1: {
        q: 'help.sformular.q.q1.q',
        words: s1words(['mail', 'tekst', 'faktura', 'emne', 'besked', 'mailen'],
          ['mail', 'email', 'text', 'invoice', 'subject', 'message'],
          ['epost', 'tekst', 'faktura', 'emne', 'melding']),
        steps: stepKeys(S1_FK, 'FK2', 1, 3)
      },
      Q2: {
        q: 'help.sformular.q.q2.q',
        words: s1words(['logo', 'brevpapir', 'baggrund', 'pdf', 'billede'],
          ['logo', 'letterhead', 'background', 'pdf', 'image'],
          ['logo', 'brevpapir', 'bakgrunn', 'pdf', 'bilde']),
        steps: ['help.sformular.q.q2.step1', 'help.sformular.q.q2.step2']
      }
    },
    questionOrder: function (ctx, st) {
      return st && st.state !== 'unknown' ? (st.state === 'mailtekst' ? ['Q1', 'Q2'] : ['Q2', 'Q1']) : [];
    },
    signal: function () { return false; }
  };

  PAGES['systemdata/formularkort.php'] = s1Formularkort;

  // ---- Formulareditor, the visual editor (systemdata/formeditor.php)
  // Visuel editor in the head of Formularer (formularkort.php:295-301); in the frame. The UI is Danish for sprog_id 1 and
  // English otherwise (formeditor.php:641-642). The toolbar #fe-toolbar (:1121-1171): the form and background selects
  // (form_nr, sprog, submit on change), + Tekst #fe-add-text, + Streg #fe-add-line, #fe-bg-btn (logoupload.php), E-mailtekst
  // #fe-mail-btn, Fortryd/Gentag, Vis kladde #fe-preview, Gem kladde #fe-savedraft, the blue save-and-activate #fe-save,
  // Nulstil #fe-reset, ? #fe-help-btn. The page #fe-canvas-area (:1226). Keys (:2747-2775): Ctrl+Z, Ctrl+Y, Ctrl+C,
  // Ctrl+V, Ctrl+D, Delete and the arrows (Shift for bigger steps) on the selected element.
  function s1feDetect(ctx) {
    return { state: s1q(ctx ? ctx.doc : null, '#fe-canvas-area') ? 'editor' : 'unknown', flags: {}, data: {} };
  }

  const S1_FE_TASKS = {
    FE1: task(S1_FK, 'FE1', 3, { target: '#fe-canvas-area', label: showKey(S1_FK, 'FE1'), kind: 'status' }),
    FE2: task(S1_FK, 'FE2', 2, { target: '#fe-add-text', label: showKey(S1_FK, 'FE2'), kind: 'button' }),
    FE3: task(S1_FK, 'FE3', 2, { target: '#fe-preview', label: showKey(S1_FK, 'FE3'), kind: 'button' }),
    FE4: task(S1_FK, 'FE4', 2, { target: '#fe-save', label: showKey(S1_FK, 'FE4'), kind: 'button' }),
    FE5: task(S1_FK, 'FE5', 2, { target: '#fe-mail-btn', label: showKey(S1_FK, 'FE5'), kind: 'button' }),
    FE6: task(S1_FK, 'FE6', 2, { target: '#fe-reset', label: showKey(S1_FK, 'FE6'), kind: 'button' })
  };

  const s1Formeditor = {
    group: S1_FK,
    states: ['editor'],
    flags: [],
    name: function () { return 'help.sformular.name_editor'; },
    detect: function (ctx) {
      try {
        return s1feDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) { return st && st.state === 'editor' ? ['help.sformular.about.editor'] : []; },
    tasks: S1_FE_TASKS,
    order: function (ctx, st) { return st && st.state === 'editor' ? ['FE1', 'FE2', 'FE3', 'FE4', 'FE5', 'FE6'] : []; },
    fields: s1fields(S1_FK, ['form_nr', 'sprog']),
    shortcuts: [
      { keys: 'Ctrl+Z', text: 'help.sformular.key.03', group: 'keys' },
      { keys: 'Ctrl+Y', text: 'help.sformular.key.04', group: 'keys' },
      { keys: 'Ctrl+D', text: 'help.sformular.key.05', group: 'keys' },
      { keys: 'Delete', text: 'help.sformular.key.06', group: 'keys' },
      { keys: 'Piletaster', text: 'help.sformular.key.07', group: 'keys' }
    ],
    questions: {},
    questionOrder: function () { return []; },
    signal: function () { return false; }
  };

  PAGES['systemdata/formeditor.php'] = s1Formeditor;

  // ---- Baggrunds- og bilagsfiler (systemdata/logoupload.php)
  // Åbn under Logo og baggrunde (settingsDefinitions.php:1048), Baggrund in Formularer's head (formularkort.php:308) and the
  // editor's background button (formeditor.php:1156); in the frame. The filter form #filterForm (background sprog and
  // department, :586-630); the upload of a background: bg_valg (Alle formularer, Tilbud, Ordrer, Fakturaer), uploadedfile,
  // Indlæs input[name=bgfil] (:664-686); of a mail attachment: bilag_valg, Indlæs input[name=bilagfil] (:690-710); each file
  // has vis and Slet (logo_delete_form(), :231-239, :480-566). A user outside the department gets no upload (:712-718).
  function s1luDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    let state = 'unknown';
    if (s1q(doc, 'input[name="bgfil"]')) state = 'upload';
    else if (s1q(doc, '#filterForm')) state = 'ingenRet';
    return { state: state, flags: {}, data: {} };
  }

  const S1_LU_TASKS = {
    LU1: task(S1_FK, 'LU1', 3, { target: 'select[name="bg_valg"]', label: fieldLabel(S1_FK, 'bg_valg'), kind: 'field' }),
    LU2: task(S1_FK, 'LU2', 2, { target: 'select[name="bilag_valg"]', label: fieldLabel(S1_FK, 'bilag_valg'), kind: 'field' }),
    LU3: task(S1_FK, 'LU3', 2, { target: '#filterForm select', label: showKey(S1_FK, 'LU3'), kind: 'field' })
  };

  const s1Logoupload = {
    group: S1_FK,
    states: ['upload', 'ingenRet'],
    flags: [],
    name: function () { return 'help.sformular.name_filer'; },
    detect: function (ctx) {
      try {
        return s1luDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'upload') return ['help.sformular.about.upload'];
      if (s === 'ingenRet') return ['help.sformular.about.ingenret'];
      return [];
    },
    tasks: S1_LU_TASKS,
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      // The background and department selects are printed only when there is something to choose (:589-630)
      const choice = !!s1q(ctx ? ctx.doc : null, '#filterForm select');
      if (s === 'upload') return choice ? ['LU1', 'LU2', 'LU3'] : ['LU1', 'LU2'];
      if (s === 'ingenRet') return choice ? ['LU3'] : [];
      return [];
    },
    fields: s1fields(S1_FK, ['bg_valg', 'bilag_valg', 'department', 'sprog']),
    shortcuts: [],
    questions: {},
    questionOrder: function () { return []; },
    signal: function () { return false; }
  };

  PAGES['systemdata/logoupload.php'] = s1Logoupload;

  // ------------------------------------------------------------------ Tekster, the translations (systemdata/tekster.php)
  // Rediger oversættelser under Sprog og lokalisering (settingsDefinitions.php:1091); in the frame. One row per text: Id,
  // Nuværende tekst and Ny tekst input ny_tekst[n] (tekster.php:142-158), OK with accesskey o (:160).
  // The language is the one the user is logged in with (:9-10); a saved text clears its cached copy (:77-81, 122).
  const S1_TX = 'stekster';

  const s1Tekster = {
    group: S1_TX,
    states: ['liste'],
    flags: [],
    name: function () { return 'help.stekster.name'; },
    detect: function (ctx) {
      return { state: s1q(ctx ? ctx.doc : null, 'form[name="tekster"]') ? 'liste' : 'unknown', flags: {}, data: {} };
    },
    about: function (ctx, st) { return st && st.state === 'liste' ? ['help.stekster.about.liste'] : []; },
    // No task for the csv link: its href is empty ($csvfile is never set, tekster.php:172-173, :200), live check 24.15.
    tasks: {
      TX1: task(S1_TX, 'TX1', 3, { target: 'input[name^="ny_tekst["]', label: fieldLabel(S1_TX, 'ny_tekst'), kind: 'field' })
    },
    order: function (ctx, st) { return st && st.state === 'liste' ? ['TX1'] : []; },
    fields: { ny_tekst: fieldKeys(S1_TX, 'ny_tekst') },
    fieldKey: function (el) { return /^ny_tekst\[\d+\]$/.test(attr(el, 'name')) ? 'ny_tekst' : null; },
    shortcuts: [{ keys: 'Alt+O', text: 'help.stekster.key.01', group: 'keys' }],
    shortcutsNote: 'help.common.shortcuts_note',
    questions: {
      Q1: {
        q: 'help.stekster.q.q1.q',
        words: s1words(['alle', 'brugere', 'gælder', 'sprog', 'oversættelse', 'tekst'],
          ['all', 'users', 'applies', 'language', 'translation', 'text'],
          ['alle', 'brukere', 'gjelder', 'språk', 'oversettelse', 'tekst']),
        steps: ['help.stekster.q.q1.step1']
      }
    },
    questionOrder: function (ctx, st) { return st && st.state === 'liste' ? ['Q1'] : []; },
    signal: function () { return false; }
  };

  PAGES['systemdata/tekster.php'] = s1Tekster;

  // ------------------------------------------------------------------ Sikkerhedskopi: the backup and restore pages
  // admin/backup.php: Opret sikkerhedskopi a[href*="backup.php?backup=1"] and Indlæs sikkerhedskopi a[href*="restore.php"]
  // (:250-266); after a backup a link to backup_download.php (Gem + Backup, :218-226), Annullér input[name=cancel] that
  // deletes the kept copy (:146-168, :232-235) and Gå til indlæsning (form action restore.php, :237-241).
  // admin/restore.php: the upload (uploadedfile, Indlæs input[name=restore_upload], 7819, :293-305); the confirmation
  // (restore_render_confirm(), :829-871: Firma i sikkerhedskopien, Sikkerhedskopien er taget, .restore-other-company 7815,
  // .restore-no-info 7816, confirm_restore 6930, Gendan button[name=restore_confirm], Afbryd button[name=restore_cancel]).
  // Both are reached from the section import_export.backup (settingsDefinitions.php:1065-1066) and open in the frame.
  const S1_BK = 'sbackup';

  function s1bpDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    let state = 'unknown';
    if (s1q(doc, 'a[href*="backup_download.php"]')) state = 'klar';
    else if (s1q(doc, 'a[href*="backup.php?backup=1"]')) state = 'start';
    return { state: state, flags: {}, data: {} };
  }

  const s1Backup = {
    group: S1_BK,
    states: ['start', 'klar'],
    flags: [],
    name: function () { return 'help.sbackup.name_backup'; },
    detect: function (ctx) {
      try {
        return s1bpDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'start') return ['help.sbackup.about.start'];
      if (s === 'klar') return ['help.sbackup.about.klar'];
      return [];
    },
    tasks: {
      BP1: task(S1_BK, 'BP1', 2, { target: 'a[href*="backup.php?backup=1"]', label: showKey(S1_BK, 'BP1'), kind: 'button' }),
      BP2: task(S1_BK, 'BP2', 2, { target: 'a[href*="backup_download.php"]', label: showKey(S1_BK, 'BP2'), kind: 'button' }),
      BP3: task(S1_BK, 'BP3', 2, {
        target: function (ctx) { return s1q(ctx ? ctx.doc : null, 'a[href*="restore.php"]') || s1q(ctx ? ctx.doc : null, 'form[action="restore.php"] input[type="submit"]'); },
        label: showKey(S1_BK, 'BP3'), kind: 'button'
      }),
      BP4: task(S1_BK, 'BP4', 1, { target: 'input[name="cancel"]', label: showKey(S1_BK, 'BP4'), kind: 'button' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'start') return ['BP1', 'BP3'];
      if (s === 'klar') return ['BP2', 'BP4', 'BP3'];
      return [];
    },
    fields: {},
    shortcuts: [],
    questions: {},
    questionOrder: function () { return []; },
    signal: function () { return false; }
  };

  PAGES['admin/backup.php'] = s1Backup;

  function s1rsDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    let state = 'unknown';
    if (s1q(doc, 'button[name="restore_confirm"]')) state = 'bekraeft';
    else if (s1q(doc, 'input[name="restore_upload"]')) state = 'upload';
    const flags = { andetFirma: !!s1q(doc, '.restore-other-company'), udenInfo: !!s1q(doc, '.restore-no-info') };
    return { state: state, flags: flags, data: {} };
  }

  const s1Restore = {
    group: S1_BK,
    states: ['upload', 'bekraeft'],
    flags: ['andetFirma', 'udenInfo'],
    name: function () { return 'help.sbackup.name_restore'; },
    detect: function (ctx) {
      try {
        return s1rsDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'upload') return ['help.sbackup.about.upload'];
      if (s === 'bekraeft') return f.andetFirma || f.udenInfo ? ['help.sbackup.about.bekraeft', 'help.sbackup.about.tjek'] : ['help.sbackup.about.bekraeft'];
      return [];
    },
    tasks: {
      RS1: task(S1_BK, 'RS1', 3, { target: 'input[name="uploadedfile"]', label: showKey(S1_BK, 'RS1'), kind: 'field' }),
      RS2: task(S1_BK, 'RS2', 3, { target: 'button[name="restore_confirm"]', label: showKey(S1_BK, 'RS2'), kind: 'button' }),
      RS3: task(S1_BK, 'RS3', 1, { target: 'button[name="restore_cancel"]', label: showKey(S1_BK, 'RS3'), kind: 'button' }),
      // the warning the task is about (restore.php:843-847), not Afbryd of a side step (spec §21)
      RS4: task(S1_BK, 'RS4', 3, {
        target: function (ctx) {
          const doc = ctx ? ctx.doc : null;
          return s1q(doc, '.restore-other-company') || s1q(doc, '.restore-no-info');
        },
        label: showKey(S1_BK, 'RS4'), kind: 'status'
      })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'upload') return ['RS1'];
      if (s === 'bekraeft') return f.andetFirma || f.udenInfo ? ['RS4', 'RS2', 'RS3'] : ['RS2', 'RS3'];
      return [];
    },
    problem: function (ctx, st) {
      return st && st.state === 'bekraeft' && st.flags && (st.flags.andetFirma || st.flags.udenInfo) ? 'RS4' : '';
    },
    fields: s1fields(S1_BK, ['uploadedfile', 'confirm_restore']),
    shortcuts: [],
    questions: {},
    questionOrder: function () { return []; },
    signal: function () { return false; }
  };

  PAGES['admin/restore.php'] = s1Restore;

  // ------------------------------------------------------------------ Import og eksport: the import and export pages
  // The links of the section import_export.data (settingsDefinitions.php:1051-1063, :1612-1621); each opens in the frame in the
  // settings layout with Tilbage to the section. Importers: a file input uploadedfile (importer_kontoplan.php:143-152,
  // importer_adresser.php:110-123 with art and opdat, importer_varer.php:191-193, importer_variantvarer.php:108-110,
  // importer_varelokationer.php:145-147, importer_formular.php:78-84), then a preview with a column choice feltnavn[n],
  // Tegnsæt and Separatortegn, Vis and, once the needed columns are chosen, Importér (importer_kontoplan.php:251-307,
  // importer_adresser.php:191-224, importer_variantvarer.php:153-204). importer_kontoplan.php refuses a year with postings
  // (:124-136) and replaces the chart of the newest year (:324-372); importer_formular.php replaces every form
  // (includes/formularimport.php:32-33). Exporters print a link to the file (exporter_kontoplan.php:81-82,
  // exporter_varer.php:104, exporter_formular.php:75, exporter_variantvarer.php) or first a choice of art
  // (exporter_adresser.php:66-87). importAccountMap.php maps the chart to the public standard chart (:74-88).
  const S1_IE = 'simpexp';

  function s1ieDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    let state = 'unknown';
    if (s1q(doc, 'select[name^="feltnavn["]')) state = 'kolonner';
    else if (s1q(doc, 'input[name="uploadedfile"]')) state = 'upload';
    return { state: state, flags: {}, data: {} };
  }

  const S1_IE_ROUTE = {
    'systemdata/importer_kontoplan.php': 'kontoplan',
    'systemdata/importer_adresser.php': 'adresser',
    'systemdata/importer_varer.php': 'varer',
    'systemdata/importer_variantvarer.php': 'variantvarer',
    'systemdata/importer_varelokationer.php': 'lokationer',
    'systemdata/importer_formular.php': 'formularer'
  };

  const s1Importer = {
    group: S1_IE,
    states: ['upload', 'kolonner'],
    flags: [],
    name: function (ctx) {
      const k = S1_IE_ROUTE[ctx ? ctx.route : ''] || 'kontoplan';
      return 'help.simpexp.name.' + k;
    },
    detect: function (ctx) {
      try {
        return s1ieDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s !== 'upload' && s !== 'kolonner') return [];
      const k = S1_IE_ROUTE[ctx ? ctx.route : ''] || 'kontoplan';
      return ['help.simpexp.about.' + k, s === 'upload' ? 'help.simpexp.about.upload' : 'help.simpexp.about.kolonner'];
    },
    tasks: {
      IE1: task(S1_IE, 'IE1', 2, { target: 'input[name="uploadedfile"]', label: showKey(S1_IE, 'IE1'), kind: 'field' }),
      IE2: task(S1_IE, 'IE2', 3, { target: 'select[name^="feltnavn["]', label: fieldLabel(S1_IE, 'feltnavn'), kind: 'field' }),
      IE3: task(S1_IE, 'IE3', 2, { target: 'select[name^="feltnavn["]', label: fieldLabel(S1_IE, 'feltnavn'), kind: 'field' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'upload') return ['IE1'];
      if (s === 'kolonner') return ['IE2', 'IE3', 'IE1'];
      return [];
    },
    fields: s1fields(S1_IE, ['uploadedfile', 'feltnavn', 'splitter', 'tegnset', 'opdat', 'art']),
    // feltnavn[3] is the column choice of column 3; file_charset is the kontoplan import's Tegnsæt (importer_kontoplan.php:266)
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (/^feltnavn\[\d+\]$/.test(n)) return 'feltnavn';
      if (n === 'file_charset') return 'tegnset';
      return null;
    },
    shortcuts: [],
    questions: {
      Q1: {
        q: 'help.simpexp.q.q1.q',
        words: s1words(['format', 'csv', 'fil', 'regneark', 'excel', 'kolonner', 'semikolon'],
          ['format', 'csv', 'file', 'spreadsheet', 'excel', 'columns', 'semicolon'],
          ['format', 'csv', 'fil', 'regneark', 'excel', 'kolonner', 'semikolon']),
        steps: ['help.simpexp.q.q1.step1', 'help.simpexp.q.q1.step2']
      }
    },
    questionOrder: function (ctx, st) { return st && st.state !== 'unknown' ? ['Q1'] : []; },
    signal: function () { return false; }
  };

  PAGES['systemdata/importer_kontoplan.php'] = s1Importer;
  PAGES['systemdata/importer_adresser.php'] = s1Importer;
  PAGES['systemdata/importer_varer.php'] = s1Importer;
  PAGES['systemdata/importer_variantvarer.php'] = s1Importer;
  PAGES['systemdata/importer_varelokationer.php'] = s1Importer;
  PAGES['systemdata/importer_formular.php'] = s1Importer;

  const S1_EX_ROUTE = {
    'systemdata/exporter_kontoplan.php': 'kontoplan',
    'systemdata/exporter_adresser.php': 'adresser',
    'systemdata/exporter_varer.php': 'varer',
    'systemdata/exporter_variantvarer.php': 'variantvarer',
    'systemdata/exporter_formular.php': 'formularer'
  };

  function s1exDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    let state = 'unknown';
    if (s1q(doc, 'form[name="addr_export"]')) state = 'valg';
    else if (s1q(doc, 'a[href*="temp/"]') || s1q(doc, 'a[href$=".csv"]') || s1q(doc, 'a[href$=".txt"]')) state = 'fil';
    return { state: state, flags: {}, data: {} };
  }

  const s1Exporter = {
    group: S1_IE,
    states: ['valg', 'fil'],
    flags: [],
    name: function (ctx) {
      const k = S1_EX_ROUTE[ctx ? ctx.route : ''] || 'kontoplan';
      return 'help.simpexp.name.eks_' + k;
    },
    detect: function (ctx) {
      try {
        return s1exDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'valg') return ['help.simpexp.about.eks_valg'];
      if (s === 'fil') return ['help.simpexp.about.eks_fil'];
      return [];
    },
    tasks: {
      EX1: task(S1_IE, 'EX1', 2, { target: 'select[name="art"]', label: fieldLabel(S1_IE, 'art'), kind: 'field' }),
      EX2: task(S1_IE, 'EX2', 2, {
        target: function (ctx) {
          const doc = ctx ? ctx.doc : null;
          return s1q(doc, 'a[href*="temp/"]') || s1q(doc, 'a[href$=".csv"]') || s1q(doc, 'a[href$=".txt"]');
        },
        label: showKey(S1_IE, 'EX2'), kind: 'button'
      })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'valg') return ['EX1'];
      if (s === 'fil') return ['EX2'];
      return [];
    },
    fields: { art: fieldKeys(S1_IE, 'art') },
    shortcuts: [],
    questions: {},
    questionOrder: function () { return []; },
    signal: function () { return false; }
  };

  PAGES['systemdata/exporter_kontoplan.php'] = s1Exporter;
  PAGES['systemdata/exporter_adresser.php'] = s1Exporter;
  PAGES['systemdata/exporter_varer.php'] = s1Exporter;
  PAGES['systemdata/exporter_variantvarer.php'] = s1Exporter;
  PAGES['systemdata/exporter_formular.php'] = s1Exporter;

  // ---- Mapping til standardkontoplanen (systemdata/importAccountMap.php)
  // Importer mappingfil til offentlig standard kontoplan (settingsDefinitions.php:1052); in the frame. A link to the
  // standard file for Saldi's standard chart from before 2024, or an upload of a CSV with two columns (:74-88).
  const s1AccountMap = {
    group: S1_IE,
    states: ['upload', 'kolonner'],
    flags: [],
    name: function () { return 'help.simpexp.name.mapping'; },
    detect: function (ctx) {
      try {
        return s1ieDetect(ctx);
      } catch (e) {
        return { state: 'unknown', flags: {}, data: {} };
      }
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      return s === 'upload' || s === 'kolonner' ? ['help.simpexp.about.mapping'] : [];
    },
    tasks: {
      AM1: task(S1_IE, 'AM1', 2, { target: 'a[href*="stdAccountMap.csv"]', label: showKey(S1_IE, 'AM1'), kind: 'button' }),
      AM2: task(S1_IE, 'AM2', 3, { target: 'input[name="uploadedfile"]', label: showKey(S1_IE, 'IE1'), kind: 'field' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      return s === 'upload' || s === 'kolonner' ? ['AM1', 'AM2'] : [];
    },
    fields: { uploadedfile: fieldKeys(S1_IE, 'uploadedfile') },
    shortcuts: [],
    questions: {},
    questionOrder: function () { return []; },
    signal: function () { return false; }
  };

  PAGES['systemdata/importAccountMap.php'] = s1AccountMap;

  // ================================================================== the S1 settings sections (settingsSection.php?s=…)
  // A form section: #st-form with .st-field[data-key] fields, ids f-<key with - for .> (components.php:769-772, :814-842);
  // switches are button.st-switch[role=switch] with aria-checked (:841-842). Links are a.st-btn in their .st-field
  // (:937-948), actions button[data-run] (:979-995). A rows section: tables .st-rows[data-table] with tr[data-row] and cells
  // r[<table>][<row>][<column>] (rowsView.php:265-399); + Tilføj in .st-rfoot button[data-add], the empty card .st-rempty
  // with Opret dansk standardsæt [data-seed] (:375-394), Kopiér fra %s button[form^=st-copy-] in an empty fiscal table
  // (:387-397), the year scope #st-year (:98-112), refused rows .st-errors (:136-156), the confirmation .st-confirm (:158-175).

  // ---- Stamdata (company.data)
  // settingsDefinitions.php:146-152 (subsections Virksomhed, Kontakt, Bank og betaling, Persondata) and :1155-1180 (fields).
  const S1_SD = 'sstam';
  function s1sdDetect(ctx) {
    const b = s1base(ctx ? ctx.doc : null);
    return { state: b.kunLaes ? 'kunLaes' : (b.form ? 'formular' : 'unknown'), flags: { fejl: b.fejl }, data: {} };
  }
  const s1Stamdata = s1section({
    group: S1_SD,
    states: ['formular', 'kunLaes'],
    flags: ['fejl'],
    detect: s1sdDetect,
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'formular') return ['help.sstam.about.formular'];
      if (s === 'kunLaes') return ['help.sstam.about.kunlaes'];
      return [];
    },
    tasks: {
      SD1: task(S1_SD, 'SD1', 2, { target: '#f-company-data-name', label: fieldLabel(S1_SD, 'name'), kind: 'field' }),
      SD2: task(S1_SD, 'SD2', 3, { target: '#f-company-data-bank_reg', label: fieldLabel(S1_SD, 'bank_reg'), kind: 'field' }),
      SD3: task(S1_SD, 'SD3', 2, { target: '#f-company-data-email', label: fieldLabel(S1_SD, 'email'), kind: 'field' }),
      SD4: task(S1_SD, 'SD4', 2, { target: '#f-company-data-bs_number', label: fieldLabel(S1_SD, 'bs_number'), kind: 'field' }),
      SD5: task(S1_SD, 'SD5', 2, { target: '#f-company-data-gdpr_contact', label: fieldLabel(S1_SD, 'gdpr_contact'), kind: 'field' })
    },
    order: function (ctx, st) {
      return st && st.state === 'formular' ? ['SD1', 'SD2', 'SD3', 'SD4', 'SD5'] : [];
    },
    fields: s1fields(S1_SD, ['name', 'adresse', 'zip', 'city', 'cvr', 'country', 'phone', 'email', 'bank_reg', 'bank_account', 'iban',
      'swift', 'bs_number', 'gdpr_contact']),
    fieldMap: { address1: 'adresse', address2: 'adresse' },
    // Q1: Landekonfiguration carries the stricter permission settings.company.danger (settingsDefinitions.php:1166,
    // includes/permissionRegistry.php:117, 6053); without write it is shown read-only (includes/settings/components.php:
    // 610-621), so it is not only Saldi that can change it, whatever its own help text 6514 says.
    // Q2: Stamdata has no logo field (settingsDefinitions.php:1155-1180); the logo goes on as a background PDF from
    // Formularer's Baggrund (formularkort.php:308, logoupload.php). Q3: a printed form reads the company's details from
    // the address row art S at print time (includes/formfunk.php:316, :689), so a reprint shows today's Stamdata.
    questions: {
      Q1: {
        q: 'help.sstam.q.q1.q',
        words: s1words(['landekonfiguration', 'land', 'låst', 'ændre', 'grå'],
          ['country', 'configuration', 'locked', 'change', 'grey'],
          ['landkonfigurasjon', 'land', 'låst', 'endre', 'grå']),
        intro: 'help.sstam.q.q1.intro',
        steps: ['help.sstam.q.q1.step1'],
        show: { target: '#f-company-data-country', label: fieldLabel(S1_SD, 'country'), kind: 'field' }
      },
      Q2: {
        q: 'help.sstam.q.q2.q',
        words: s1words(['logo', 'brevpapir', 'faktura', 'billede', 'baggrund', 'pdf'],
          ['logo', 'letterhead', 'invoice', 'image', 'background', 'pdf'],
          ['logo', 'brevpapir', 'faktura', 'bilde', 'bakgrunn', 'pdf']),
        intro: 'help.sstam.q.q2.intro',
        steps: ['help.sstam.q.q2.step1', 'help.sstam.q.q2.step2']
      },
      Q3: {
        q: 'help.sstam.q.q3.q',
        words: s1words(['gamle fakturaer', 'adresse', 'flytning', 'ændring', 'udskrive igen', 'navn'],
          ['old invoices', 'address', 'move', 'change', 'reprint', 'name'],
          ['gamle fakturaer', 'adresse', 'flytting', 'endring', 'skrive ut igjen', 'navn']),
        intro: 'help.sstam.q.q3.intro',
        steps: ['help.sstam.q.q3.step1', 'help.sstam.q.q3.step2']
      }
    },
    // The logo is what owners look for here, then what a change does to old invoices; the locked country last
    questionOrder: function (ctx, st) { return st && st.state !== 'unknown' ? ['Q2', 'Q3', 'Q1'] : []; }
  });
  addSection('company.data', s1Stamdata);

  // ---- Sprog og lokalisering (company.localisation)
  // settingsDefinitions.php:153-158, :1080-1091 (Basisvaluta, Land, Tidszone, Talformat, Systemsprog; the link Rediger
  // oversættelser to tekster.php).
  const S1_SP = 'ssprog';
  const s1Sprog = s1section({
    group: S1_SP,
    states: ['formular', 'kunLaes'],
    flags: [],
    detect: function (ctx) {
      const b = s1base(ctx ? ctx.doc : null);
      return { state: b.kunLaes ? 'kunLaes' : (b.form ? 'formular' : 'unknown'), flags: {}, data: {} };
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'formular') return ['help.ssprog.about.formular'];
      if (s === 'kunLaes') return ['help.ssprog.about.kunlaes'];
      return [];
    },
    tasks: {
      SP1: task(S1_SP, 'SP1', 2, { target: '#f-company-localisation-system_language', label: fieldLabel(S1_SP, 'system_language'), kind: 'field' }),
      SP2: task(S1_SP, 'SP2', 2, { target: '[data-key="company.localisation.translations"] a.st-btn', label: showKey(S1_SP, 'SP2'), kind: 'button' }),
      SP3: task(S1_SP, 'SP3', 2, { target: '#f-company-localisation-number_format', label: fieldLabel(S1_SP, 'number_format'), kind: 'field' })
    },
    order: function (ctx, st) { return st && st.state === 'formular' ? ['SP1', 'SP3', 'SP2'] : []; },
    fields: s1fields(S1_SP, ['base_currency', 'base_country', 'timezone', 'number_format', 'system_language']),
    questions: {
      Q1: {
        q: 'help.ssprog.q.q1.q',
        words: s1words(['basisvaluta', 'valuta', 'euro', 'skifte', 'kroner'],
          ['base', 'currency', 'euro', 'change', 'kroner'],
          ['basisvaluta', 'valuta', 'euro', 'bytte', 'kroner']),
        steps: ['help.ssprog.q.q1.step1']
      }
    },
    questionOrder: function (ctx, st) { return st && st.state === 'formular' ? ['Q1'] : []; }
  });
  addSection('company.localisation', s1Sprog);

  // ---- Abonnement og konto (company.account), hosted installations only (settingsDefinitions.php:170-175, :1070-1077):
  // Regnskabets navn, Aktivitet and the danger zone Slet regnskab, with the password (settingsSection.php:263-268).
  // Nulstil regnskab (AC2) left the section on 14 Nov 2026: Saldi's staff reset a ledger from the admin panel.
  const S1_AC = 'skonto';
  const s1Konto = s1section({
    group: S1_AC,
    states: ['formular', 'kunLaes'],
    flags: ['fejl'],
    detect: function (ctx) {
      const b = s1base(ctx ? ctx.doc : null);
      return { state: b.kunLaes ? 'kunLaes' : (b.form ? 'formular' : 'unknown'), flags: { fejl: b.fejl }, data: {} };
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'formular') return ['help.skonto.about.formular'];
      if (s === 'kunLaes') return ['help.skonto.about.kunlaes'];
      return [];
    },
    tasks: {
      AC1: task(S1_AC, 'AC1', 3, { target: '#f-company-account-name', label: fieldLabel(S1_AC, 'name'), kind: 'field' }),
      AC3: task(S1_AC, 'AC3', 2, { target: 'button[data-run="company.account.delete"]', label: showKey(S1_AC, 'AC3'), kind: 'button' })
    },
    order: function (ctx, st) { return st && st.state === 'formular' ? ['AC1', 'AC3'] : []; },
    fields: { name: fieldKeys(S1_AC, 'name') }
  });
  addSection('company.account', s1Konto);

  // ---- Persondata (GDPR) (company.gdpr): the count and Slet inaktive kunder og leverandører with the password
  // (settingsDefinitions.php:176-181, :1094-1096).
  const S1_GD = 'sgdpr';
  const s1Gdpr = s1section({
    group: S1_GD,
    states: ['formular', 'kunLaes'],
    flags: [],
    detect: function (ctx) {
      const b = s1base(ctx ? ctx.doc : null);
      return { state: b.kunLaes ? 'kunLaes' : (b.form ? 'formular' : 'unknown'), flags: {}, data: {} };
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'formular') return ['help.sgdpr.about.formular'];
      if (s === 'kunLaes') return ['help.sgdpr.about.kunlaes'];
      return [];
    },
    tasks: {
      GD1: task(S1_GD, 'GD1', 3, { target: 'button[data-run="company.gdpr.delete_inactive"]', label: showKey(S1_GD, 'GD1'), kind: 'button' })
    },
    order: function (ctx, st) { return st && st.state === 'formular' ? ['GD1'] : []; },
    shortcuts: [],
    questions: {
      Q1: {
        q: 'help.sgdpr.q.q1.q',
        words: s1words(['slettes', 'inaktive', 'gdpr', 'persondata', 'kunder', 'leverandører', 'tre', 'år'],
          ['deleted', 'inactive', 'gdpr', 'personal', 'customers', 'suppliers', 'years'],
          ['slettes', 'inaktive', 'gdpr', 'persondata', 'kunder', 'leverandører', 'år']),
        steps: ['help.sgdpr.q.q1.step1', 'help.sgdpr.q.q1.step2']
      }
    },
    questionOrder: function (ctx, st) { return st && st.state !== 'unknown' ? ['Q1'] : []; }
  });
  addSection('company.gdpr', s1Gdpr);

  // ---- Regnskabsår (company.fiscal_years), kind rows
  // settingsDefinitions.php:182-205: the table years (Nr., Beskrivelse, Periode, Bogføring tilladt, Status; no + row), the row
  // actions Sæt aktivt (fy_activate: open for posting and not your active year), Sæt aktivt for alle (fy_activate_all: your
  // active year while others have another), Åbningsbalance (a link to regnskabskort.php?id=, not on a deleted year) and Slet
  // med data (fy_archive with the password) (includes/settings/rowHooks.php:689-704; rowsView.php:337-349); the trash
  // [data-del] on a row that is not locked, refused while the year is used (rowHooks.php:66-93, rows.php:701-711).
  // The create card #create with Beskrivelse, Start (a month and year select only for the first year), Slut, Bogføring
  // tilladt and Opret nyt regnskabsår button[form=st-create-years] (rowHooks.php:308-354); it copies the chart and the
  // year's groups, makes the year your active one and opens the opening balance (rowHooks.php:242-290, :783-805).
  const S1_FY = 'sregnaar';
  const S1_FY_ROWS = '.st-rows[data-table="years"] tbody tr[data-row]';

  /** The end date of a Periode cell (fy_period, rowHooks.php:540-542: j/n-Y – j/n-Y), or null. */
  function s1fyEnd(row) {
    const span = s1in(row, '.st-rc-col-period span');
    const m = /(\d{1,2})\/(\d{1,2})-(\d{4})\D+(\d{1,2})\/(\d{1,2})-(\d{4})/.exec(span ? String(span.textContent || '') : '');
    return m ? new Date(Number(m[6]), Number(m[5]) - 1, Number(m[4])) : null;
  }

  function s1fyDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    const b = s1base(doc);
    const rows = s1all(doc, S1_FY_ROWS);
    const open = rows.filter(function (r) { return s1checked(s1in(r, 'input[type="checkbox"][name$="[box5]"]')); });
    const end = rows.length ? s1fyEnd(rows[rows.length - 1]) : null;
    const flags = {
      foerste: !!s1q(doc, 'select[name="start_month"]'),
      slutPasseret: !!(end && end.getTime() < Date.now()),
      flereAabne: open.length > 1,
      aktivForAlle: !!s1q(doc, 'button[data-row-action="fy_activate_all"]'),
      kanSlettes: !!s1q(doc, 'button[data-row-action="fy_archive"]'),
      fejl: b.fejl
    };
    let state = 'unknown';
    if (b.kunLaes) state = 'kunLaes';
    else if (rows.length) state = 'liste';
    else if (b.form || s1q(doc, '#create')) state = 'tom';
    return { state: state, flags: flags, data: { aar: rows.length } };
  }

  /** The Bogføring tilladt box of the oldest year still open for posting, not the newest year. */
  function s1fyOldOpen(ctx) {
    const rows = s1all(ctx ? ctx.doc : null, S1_FY_ROWS);
    for (let i = 0; i < rows.length - 1; i++) {
      const box = s1in(rows[i], 'input[type="checkbox"][name$="[box5]"]');
      if (s1checked(box)) return box;
    }
    return null;
  }

  /** The newest year's Åbningsbalance link. */
  function s1fyOpening(ctx) {
    const rows = s1all(ctx ? ctx.doc : null, S1_FY_ROWS);
    return rows.length ? s1in(rows[rows.length - 1], 'a[href*="regnskabskort.php?id="]') : null;
  }

  /** The newest year's trash. */
  function s1fyTrash(ctx) {
    const rows = s1all(ctx ? ctx.doc : null, S1_FY_ROWS);
    return rows.length ? s1in(rows[rows.length - 1], 'button[data-del]') : null;
  }

  const s1Regnskabsaar = s1section({
    group: S1_FY,
    states: ['tom', 'liste', 'kunLaes'],
    flags: ['foerste', 'slutPasseret', 'flereAabne', 'aktivForAlle', 'kanSlettes', 'fejl'],
    rows: true,
    detect: s1fyDetect,
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'tom') return ['help.sregnaar.about.tom'];
      if (s === 'liste') {
        if (f.slutPasseret) return ['help.sregnaar.about.liste', 'help.sregnaar.about.slut'];
        if (f.flereAabne) return ['help.sregnaar.about.liste', 'help.sregnaar.about.flereaabne'];
        return ['help.sregnaar.about.liste'];
      }
      if (s === 'kunLaes') return ['help.sregnaar.about.kunlaes'];
      return [];
    },
    tasks: {
      FY1: task(S1_FY, 'FY1', 4, { target: s1only(s1fyDetect, ['liste'], 'button[form="st-create-years"]'), label: showKey(S1_FY, 'FY1'), kind: 'button' }),
      FY2: task(S1_FY, 'FY2', 4, { target: 'select[name="start_month"]', label: showKey(S1_FY, 'FY2'), kind: 'field' }),
      FY3: task(S1_FY, 'FY3', 2, { target: 'button[data-row-action="fy_activate"]', label: showKey(S1_FY, 'FY3'), kind: 'button' }),
      FY4: task(S1_FY, 'FY4', 2, { target: 'button[data-row-action="fy_activate_all"]', label: showKey(S1_FY, 'FY4'), kind: 'button' }),
      FY5: task(S1_FY, 'FY5', 3, { target: s1fyOldOpen, label: fieldLabel(S1_FY, 'bogfoering_tilladt'), kind: 'field' }),
      FY6: task(S1_FY, 'FY6', 3, { target: s1fyOpening, label: showKey(S1_FY, 'FY6'), kind: 'button' }),
      FY7: task(S1_FY, 'FY7', 3, { target: 'button[data-row-action="fy_archive"]', label: showKey(S1_FY, 'FY7'), kind: 'button' }),
      FY8: task(S1_FY, 'FY8', 2, { target: s1fyTrash, label: showKey(S1_FY, 'FY8'), kind: 'button' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'tom') return ['FY2'];
      if (s !== 'liste') return [];
      // One year: nothing to switch to, close or archive yet (live check 24.15, a new company's usual state)
      if (st.data && st.data.aar === 1) return ['FY1', 'FY6', 'FY8'];
      let o = f.slutPasseret ? ['FY1', 'FY6', 'FY3'] : ['FY1', 'FY3', 'FY6'];
      if (f.flereAabne) o.splice(1, 0, 'FY5');
      if (f.aktivForAlle) o.splice(2, 0, 'FY4');
      o = o.concat(['FY5', 'FY4', 'FY7', 'FY8']);
      return unique(o);
    },
    fields: s1fields(S1_FY, ['aarnavn', 'bogfoering_tilladt', 'beskrivelse', 'start_month', 'start_year', 'end_month', 'end_year', 'posting']),
    fieldMap: { 'years.beskrivelse': 'aarnavn', 'years.box5': 'bogfoering_tilladt' },
    questions: {
      Q1: {
        q: 'help.sregnaar.q.q1.q',
        words: s1words(['lukke', 'luk', 'år', 'årsafslutning', 'spærre', 'bogføring'],
          ['close', 'year', 'year-end', 'lock', 'posting'],
          ['lukke', 'år', 'årsavslutning', 'sperre', 'bokføring']),
        steps: stepKeys(S1_FY, 'FY5', 1, 3),
        show: { target: s1fyOldOpen, label: fieldLabel(S1_FY, 'bogfoering_tilladt'), kind: 'field' }
      },
      Q2: {
        q: 'help.sregnaar.q.q2.q',
        words: s1words(['slette', 'slet', 'regnskabsår', 'kan', 'ikke', 'fejl'],
          ['delete', 'fiscal', 'year', 'cannot', 'mistake'],
          ['slette', 'regnskapsår', 'kan', 'ikke', 'feil']),
        steps: ['help.sregnaar.q.q2.step1', 'help.sregnaar.q.q2.step2']
      },
      Q3: {
        q: 'help.sregnaar.q.q3.q',
        words: s1words(['dato', 'bogføre', 'afvist', 'udenfor', 'regnskabsåret', 'aktive'],
          ['date', 'post', 'refused', 'outside', 'fiscal', 'active'],
          ['dato', 'bokføre', 'avvist', 'utenfor', 'regnskapsåret', 'aktive']),
        steps: ['help.sregnaar.q.q3.step1', 'help.sregnaar.q.q3.step2']
      }
    },
    // Without a year closing or deleting one is beside the point: the refused date leads. Several years open for posting
    // put closing the old one first.
    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'liste') return st.flags && st.flags.flereAabne ? ['Q1', 'Q3', 'Q2'] : ['Q3', 'Q1', 'Q2'];
      if (s === 'tom' || s === 'kunLaes') return ['Q3'];
      return [];
    }
  });
  addSection('company.fiscal_years', s1Regnskabsaar);

  // ---- Moms (finance.vat), kind rows with a year scope
  // settingsDefinitions.php:330-383: the fields Momsregistreret (switch #f-vat-registered, 6895) and Momsperiode
  // (#f-vat-period, visible when registered) (:889-895); the tables sales (Salgsmoms), purchase (Købsmoms), services and goods
  // (Moms af ydelseskøb/varekøb i udlandet, with Modkonto) and report (Momsrapport: fra, til, Rubrik A1, A2), each per fiscal
  // year with Opret dansk standardsæt when empty. A code in use (an account's Moms or a debtor/creditor group, rows.php:235-241)
  // keeps its Nr. (rowsView.php:280-283) and cannot be deleted (rows.php:709-711); Markér inactive hides it (:351).
  const S1_MO = 'smoms';
  function s1moDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    const b = s1base(doc);
    const sw = s1q(doc, '#f-vat-registered');
    const flags = {
      standardsaet: !!s1q(doc, '.st-rows [data-seed]'),
      kopierFra: !!s1q(doc, 'button[form^="st-copy-"]'),
      ikkeMomsreg: !!sw && attr(sw, 'aria-checked') === 'false',
      ingenRapport: !!s1q(doc, '.st-rows[data-table="report"] .st-rempty'),
      fejl: !!s1q(doc, '.st-errors')
    };
    let state = 'unknown';
    if (b.kunLaes) state = 'kunLaes';
    else if (s1q(doc, '.st-rows[data-table="sales"] .st-rempty')) state = 'tom';
    else if (b.form) state = 'koder';
    return { state: state, flags: flags, data: {} };
  }
  const S1_MO_TASKS = {
    MO1: task(S1_MO, 'MO1', 3, { target: '.st-rows [data-seed]', label: showKey(S1_MO, 'MO1'), kind: 'button' }),
    MO2: task(S1_MO, 'MO2', 2, { target: 'button[form^="st-copy-"]', label: showKey(S1_MO, 'MO2'), kind: 'button' }),
    MO3: task(S1_MO, 'MO3', 4, { target: '.st-rows[data-table="sales"] .st-rfoot button[data-add]', label: showKey(S1_MO, 'MO3'), kind: 'button' }),
    MO4: task(S1_MO, 'MO4', 2, { target: '#f-vat-period', label: fieldLabel(S1_MO, 'momsperiode'), kind: 'field' }),
    MO5: task(S1_MO, 'MO5', 3, { target: '.st-rows[data-table="sales"] tbody tr[data-row] input[name$="[box2]"]', label: fieldLabel(S1_MO, 'sats'), kind: 'field' }),
    MO6: task(S1_MO, 'MO6', 3, {
      target: function (ctx) {
        const doc = ctx ? ctx.doc : null;
        return s1q(doc, '.st-rows[data-table="report"] tbody tr[data-row] input[name$="[box1]"]') || s1q(doc, '.st-rows[data-table="report"] .st-rfoot button[data-add]');
      },
      label: showKey(S1_MO, 'MO6'), kind: 'field'
    }),
    MO7: task(S1_MO, 'MO7', 3, {
      target: function (ctx) {
        const doc = ctx ? ctx.doc : null;
        return s1q(doc, '.st-rows[data-table="goods"] .st-rfoot button[data-add]') || s1q(doc, '.st-rows[data-table="goods"] [data-seed]');
      },
      label: showKey(S1_MO, 'MO7'), kind: 'button'
    }),
    MO8: task(S1_MO, 'MO8', 2, { target: '.st-rows[data-table="sales"] button[data-inactive="1"]', label: showKey(S1_MO, 'MO8'), kind: 'button' }),
    MO9: task(S1_MO, 'MO9', 2, { target: '#f-vat-registered', label: showKey(S1_MO, 'MO9'), kind: 'field' }),
    MO10: task(S1_MO, 'MO10', 2, { target: '.st-errors a', label: showKey(S1_MO, 'MO10'), kind: 'status' })
  };
  const s1Moms = s1section({
    group: S1_MO,
    states: ['tom', 'koder', 'kunLaes'],
    flags: ['standardsaet', 'kopierFra', 'ikkeMomsreg', 'ingenRapport', 'fejl'],
    rows: true,
    detect: s1moDetect,
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'tom') return f.kopierFra ? ['help.smoms.about.tom', 'help.smoms.about.kopier'] : ['help.smoms.about.tom'];
      if (s === 'koder') {
        if (f.ikkeMomsreg) return ['help.smoms.about.koder', 'help.smoms.about.ikkemomsreg'];
        if (f.ingenRapport) return ['help.smoms.about.koder', 'help.smoms.about.ingenrapport'];
        return ['help.smoms.about.koder'];
      }
      if (s === 'kunLaes') return ['help.smoms.about.kunlaes'];
      return [];
    },
    tasks: S1_MO_TASKS,
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const pre = f.fejl ? ['MO10'] : [];
      if (s === 'tom') return unique(pre.concat(f.kopierFra ? ['MO2', 'MO1', 'MO4', 'MO3'] : ['MO1', 'MO4', 'MO3']));
      if (s === 'koder') {
        let o = ['MO3', 'MO5', 'MO4', 'MO6', 'MO7', 'MO8'];
        if (f.ingenRapport) o = ['MO6'].concat(o);
        if (f.kopierFra) o = ['MO2'].concat(o);
        if (!f.ikkeMomsreg) o.push('MO9');
        return unique(pre.concat(o));
      }
      return [];
    },
    problem: function (ctx, st) {
      return st && st.flags && st.flags.fejl && (st.state === 'tom' || st.state === 'koder') ? 'MO10' : '';
    },
    fields: s1fields(S1_MO, ['momsperiode', 'nr', 'beskrivelse', 'konto', 'sats', 'land_oss', 'type', 'modkonto', 'fra_konto', 'til_konto',
      'rubrik_varer', 'rubrik_ydelser']),
    fieldMap: {
      period: 'momsperiode',
      'sales.kodenr': 'nr', 'purchase.kodenr': 'nr', 'services.kodenr': 'nr', 'goods.kodenr': 'nr', 'report.kodenr': 'nr',
      'sales.beskrivelse': 'beskrivelse', 'purchase.beskrivelse': 'beskrivelse', 'services.beskrivelse': 'beskrivelse',
      'goods.beskrivelse': 'beskrivelse', 'report.beskrivelse': 'beskrivelse',
      'sales.box1': 'konto', 'purchase.box1': 'konto', 'services.box1': 'konto', 'goods.box1': 'konto',
      'sales.box2': 'sats', 'purchase.box2': 'sats', 'services.box2': 'sats', 'goods.box2': 'sats',
      'sales.box6': 'land_oss', 'sales.box7': 'type', 'services.box3': 'modkonto', 'goods.box3': 'modkonto',
      'report.box1': 'fra_konto', 'report.box2': 'til_konto', 'report.box3': 'rubrik_varer', 'report.box4': 'rubrik_ydelser'
    },
    questions: {
      Q1: {
        q: 'help.smoms.q.q1.q',
        words: s1words(['nummer', 'nr', 'ændre', 'låst', 'grå', 'momskode', 'brug'],
          ['number', 'change', 'locked', 'grey', 'vat', 'code', 'used'],
          ['nummer', 'endre', 'låst', 'grå', 'mva-kode', 'bruk']),
        steps: ['help.smoms.q.q1.step1', 'help.smoms.q.q1.step2']
      },
      Q2: {
        q: 'help.smoms.q.q2.q',
        words: s1words(['konto', 'moms', 'kontoplan', 'momskode', 'automatisk', 'sætte'],
          ['account', 'vat', 'chart', 'code', 'automatic', 'set'],
          ['konto', 'mva', 'kontoplan', 'mva-kode', 'automatisk']),
        steps: ['help.smoms.q.q2.step1', 'help.smoms.q.q2.step2']
      },
      Q3: {
        q: 'help.smoms.q.q3.q',
        words: s1words(['momsangivelse', 'indberette', 'skat', 'momsregnskab', 'afregne', 'periode'],
          ['vat', 'return', 'report', 'tax', 'settle', 'period'],
          ['mva-melding', 'rapportere', 'skatteetaten', 'mva', 'periode']),
        steps: ['help.smoms.q.q3.step1', 'help.smoms.q.q3.step2']
      }
    },
    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'tom') return ['Q2', 'Q3'];
      // Many come here looking for the VAT return itself; a company without VAT registration does not file one
      if (s === 'koder') return st.flags && st.flags.ikkeMomsreg ? ['Q2', 'Q1', 'Q3'] : ['Q3', 'Q2', 'Q1'];
      if (s === 'kunLaes') return ['Q3'];
      return [];
    }
  });
  addSection('finance.vat', s1Moms);

  // ---- Valuta (finance.currencies), kind rows
  // settingsDefinitions.php:206-231: currencies (Valutakode, Beskrivelse, Kursdifferencekonto, Vis i kassen, Aktuel kurs) and
  // rates (Dato, Kurs) of the currency chosen in #st-f-rates (rowsView.php:258-269); saving a new rate first shows the
  // exchange differences it would post in .st-confirm with Bogfør og gem and Gem kurs uden bogføring (settingsSection.php:
  // 383-386, rowsView.php:158-175; rowHooks.php:412-507).
  const S1_VA = 'svaluta';
  function s1vaDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    const b = s1base(doc);
    let state = 'unknown';
    if (b.kunLaes) state = 'kunLaes';
    else if (s1q(doc, '.st-confirm')) state = 'bekraeft';
    else if (s1q(doc, '.st-rows[data-table="currencies"] .st-rempty')) state = 'tom';
    else if (b.form) state = 'liste';
    return { state: state, flags: { fejl: !!s1q(doc, '.st-errors') }, data: {} };
  }
  const s1Valuta = s1section({
    group: S1_VA,
    states: ['tom', 'liste', 'bekraeft', 'kunLaes'],
    flags: ['fejl'],
    rows: true,
    detect: s1vaDetect,
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'tom') return ['help.svaluta.about.tom'];
      if (s === 'liste') return ['help.svaluta.about.liste'];
      if (s === 'bekraeft') return ['help.svaluta.about.bekraeft'];
      if (s === 'kunLaes') return ['help.svaluta.about.kunlaes'];
      return [];
    },
    tasks: {
      VA1: task(S1_VA, 'VA1', 3, { target: '.st-rows[data-table="currencies"] .st-rfoot button[data-add]', label: showKey(S1_VA, 'VA1'), kind: 'button' }),
      VA2: task(S1_VA, 'VA2', 3, { target: '.st-rows[data-table="rates"] .st-rfoot button[data-add]', label: showKey(S1_VA, 'VA2'), kind: 'button' }),
      VA3: task(S1_VA, 'VA3', 3, { target: '.st-confirm button[name="confirmed"]', label: showKey(S1_VA, 'VA3'), kind: 'button' }),
      VA4: task(S1_VA, 'VA4', 2, { target: '.st-errors a', label: showKey(S1_VA, 'VA4'), kind: 'status' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const pre = st && st.flags && st.flags.fejl ? ['VA4'] : [];
      if (s === 'tom') return pre.concat(['VA1']);
      if (s === 'liste') return pre.concat(['VA2', 'VA1']);
      if (s === 'bekraeft') return ['VA3', 'VA2'];
      return [];
    },
    problem: function (ctx, st) {
      const s = st ? st.state : '';
      if (s === 'bekraeft') return 'VA3';
      return st && st.flags && st.flags.fejl && (s === 'tom' || s === 'liste') ? 'VA4' : '';
    },
    fields: s1fields(S1_VA, ['valutakode', 'beskrivelse', 'kursdiffkonto', 'vis_kasse', 'dato', 'kurs']),
    fieldMap: {
      'currencies.box1': 'valutakode', 'currencies.beskrivelse': 'beskrivelse', 'currencies.box3': 'kursdiffkonto',
      'currencies.box4': 'vis_kasse', 'rates.valdate': 'dato', 'rates.kurs': 'kurs'
    },
    questions: {
      Q1: {
        q: 'help.svaluta.q.q1.q',
        words: s1words(['kurs', 'beregnes', '100', 'enheder', 'euro', 'valutakurs'],
          ['rate', 'calculated', '100', 'units', 'euro', 'exchange'],
          ['kurs', 'beregnes', '100', 'enheter', 'euro', 'valutakurs']),
        steps: ['help.svaluta.q.q1.step1']
      },
      // An order takes its currency from the customer's debtor group (debitor/ordre.php:812, :820; the group's Valuta,
      // box3), so a currency is used on invoices through a group, not from here.
      Q2: {
        q: 'help.svaluta.q.q2.q',
        words: s1words(['euro', 'fremmed valuta', 'faktura', 'udenlandsk kunde', 'valuta på ordre', 'dollar'],
          ['euro', 'foreign currency', 'invoice', 'foreign customer', 'order currency', 'dollar'],
          ['euro', 'utenlandsk valuta', 'faktura', 'utenlandsk kunde', 'valuta på ordre', 'dollar']),
        intro: 'help.svaluta.q.q2.intro',
        steps: ['help.svaluta.q.q2.step1', 'help.svaluta.q.q2.step2']
      }
    },
    // Without a currency the newcomer's question is how to invoice in one; with currencies, how to write the rate
    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'tom') return ['Q2', 'Q1'];
      return s === 'liste' || s === 'bekraeft' || s === 'kunLaes' ? ['Q1', 'Q2'] : [];
    }
  });
  addSection('finance.currencies', s1Valuta);

  // ---- Kassekladde og betalinger (finance.cash_journal)
  // settingsDefinitions.php:137-144 and :898-913: different_dates_same_voucher (switch; finans/kassekladde.php:938-940),
  // payment_lists (Ingen, Begge, Debitorer, Kreditorer; the payment id field in the cash journal, kassekladde.php:940),
  // payment_days (debitor/betalinger.php:271-272, :339-340), rounding_max and rounding_account (includes/udlign_openpost.php:
  // 319-322: settling open items).
  const S1_KJ = 'skladde';
  const s1Kladde = s1section({
    group: S1_KJ,
    states: ['formular', 'kunLaes'],
    flags: ['fejl'],
    detect: function (ctx) {
      const b = s1base(ctx ? ctx.doc : null);
      return { state: b.kunLaes ? 'kunLaes' : (b.form ? 'formular' : 'unknown'), flags: { fejl: b.fejl }, data: {} };
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'formular') return ['help.skladde.about.formular'];
      if (s === 'kunLaes') return ['help.skladde.about.kunlaes'];
      return [];
    },
    tasks: {
      KJ1: task(S1_KJ, 'KJ1', 3, { target: '#f-finance-cash_journal-payment_lists', label: fieldLabel(S1_KJ, 'payment_lists'), kind: 'field' }),
      KJ2: task(S1_KJ, 'KJ2', 2, { target: '#f-finance-cash_journal-payment_days', label: fieldLabel(S1_KJ, 'payment_days'), kind: 'field' }),
      KJ3: task(S1_KJ, 'KJ3', 3, { target: '#f-finance-cash_journal-rounding_max', label: fieldLabel(S1_KJ, 'rounding_max'), kind: 'field' }),
      KJ4: task(S1_KJ, 'KJ4', 2, { target: '#f-finance-cash_journal-different_dates_same_voucher', label: showKey(S1_KJ, 'KJ4'), kind: 'field' })
    },
    order: function (ctx, st) { return st && st.state === 'formular' ? ['KJ1', 'KJ3', 'KJ2', 'KJ4'] : []; },
    fields: s1fields(S1_KJ, ['payment_lists', 'payment_days', 'rounding_max', 'rounding_account'])
  });
  addSection('finance.cash_journal', s1Kladde);

  // ---- Bilagsopbevaring (finance.document_storage)
  // settingsDefinitions.php:528-536, :1352-1386: Opbevaring af bilag (Intern opbevaring, Egen FTP server, Ingen opbevaring;
  // includes/settings/virtualStorage.php:55-65, :133-145: switching moves no files, Ingen clears the FTP details), the
  // address for mailed bilag (internal only), the FTP fields and Test forbindelsen (FTP only, data-visible-if), the result
  // as ?ftp=ok|err (settingsSection.php:519-521: 6212, 6213), Brug Google Docs viewer.
  const S1_BI = 'sbilag';
  function s1biDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    const b = s1base(doc);
    const type = s1val(s1q(doc, '#f-finance-document_storage-type'));
    const ftp = s1param(ctx, 'ftp');
    let state = 'unknown';
    if (b.kunLaes) state = 'kunLaes';
    else if (b.form) state = type === 'internFTP' ? 'intern' : (type === 'externFTP' ? 'ftp' : 'ingen');
    return { state: state, flags: { ftpOk: ftp === 'ok', ftpFejl: ftp !== '' && ftp !== 'ok' }, data: {} };
  }
  const s1Bilag = s1section({
    group: S1_BI,
    states: ['intern', 'ftp', 'ingen', 'kunLaes'],
    flags: ['ftpOk', 'ftpFejl'],
    detect: s1biDetect,
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'intern') return ['help.sbilag.about.intern'];
      if (s === 'ftp') return f.ftpOk ? ['help.sbilag.about.ftp', 'help.sbilag.about.ftpok'] : ['help.sbilag.about.ftp'];
      if (s === 'ingen') return ['help.sbilag.about.ingen'];
      if (s === 'kunLaes') return ['help.sbilag.about.kunlaes'];
      return [];
    },
    tasks: {
      BI1: task(S1_BI, 'BI1', 3, { target: '#f-finance-document_storage-type', label: fieldLabel(S1_BI, 'type'), kind: 'field' }),
      BI2: task(S1_BI, 'BI2', 3, { target: '[data-key="finance.document_storage.mail_address"]', label: showKey(S1_BI, 'BI2'), kind: 'status' }),
      BI3: task(S1_BI, 'BI3', 3, { target: '#f-finance-document_storage-ftp_server', label: fieldLabel(S1_BI, 'ftp_server'), kind: 'field' }),
      BI4: task(S1_BI, 'BI4', 3, { target: 'button[data-run="finance.document_storage.ftp_test"]', label: showKey(S1_BI, 'BI4'), kind: 'button' }),
      BI5: task(S1_BI, 'BI5', 3, { target: '#f-finance-document_storage-ftp_server', label: fieldLabel(S1_BI, 'ftp_server'), kind: 'field' }),
      BI6: task(S1_BI, 'BI6', 2, { target: '#f-finance-document_storage-google_docs', label: showKey(S1_BI, 'BI6'), kind: 'field' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'intern') return ['BI2', 'BI1', 'BI6'];
      if (s === 'ftp') return f.ftpFejl ? ['BI5', 'BI4', 'BI1'] : ['BI3', 'BI4', 'BI1', 'BI6'];
      if (s === 'ingen') return ['BI1'];
      return [];
    },
    problem: function (ctx, st) {
      return st && st.state === 'ftp' && st.flags && st.flags.ftpFejl ? 'BI5' : '';
    },
    fields: s1fields(S1_BI, ['type', 'ftp_server', 'ftp_user', 'ftp_password', 'ftp_folder_vouchers', 'ftp_folder_documents']),
    questions: {
      Q1: {
        q: 'help.sbilag.q.q1.q',
        words: s1words(['mailet', 'mail', 'bilag', 'finder', 'puljen', 'scannet', 'kvittering'],
          ['mailed', 'email', 'voucher', 'find', 'pool', 'scanned', 'receipt'],
          ['sendt', 'epost', 'bilag', 'finner', 'puljen', 'skannet', 'kvittering']),
        steps: ['help.sbilag.q.q1.step1', 'help.sbilag.q.q1.step2']
      },
      Q2: {
        q: 'help.sbilag.q.q2.q',
        words: s1words(['gemme', 'opbevare', 'år', 'hvor', 'længe', 'bogføringsloven'],
          ['keep', 'store', 'years', 'how', 'long', 'law'],
          ['lagre', 'oppbevare', 'år', 'hvor', 'lenge', 'bokføringsloven']),
        steps: ['help.sbilag.q.q2.step1']
      }
    },
    questionOrder: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'intern') return ['Q1', 'Q2'];
      if (s === 'ftp' || s === 'ingen' || s === 'kunLaes') return ['Q2'];
      return [];
    }
  });
  addSection('finance.document_storage', s1Bilag);

  // ---- E-mail (documents.email), permission settings.email
  // settingsDefinitions.php:662-669, :1427-1460: Udgående server (SMTP-server, Brugernavn, Adgangskode, Kryptering),
  // Afsender pr. sprog (sender_email.<n>, sender_name.<n>; n 0 = Standard, :1671-1680) and Mailtekster (the mail to customers
  // from the old history list; the Mit salg invitation; the commission settlement). On this line the history list only
  // redirects to the CRM (debitor_historik.php:27-28, debitor.php:62-65), and nothing sends the customer mail any more:
  // documents.email.customer_subject/customer_text are read only by this page (and debitor/mailTxt.php, which redirects
  // here); the CRM only mails meeting invitations (crmFunctions.php). So no task for it, and the two fields say so. The
  // invoice's own mail text belongs to the form (Formularer, Type Mail tekst). The hub's attention Afsender-e-mail mangler
  // opens this page with field=documents.email.sender_email.0 (settingsRegistry.php:505-510).
  const S1_ML = 'smail';
  function s1mlDetect(ctx) {
    const doc = ctx ? ctx.doc : null;
    const b = s1base(doc);
    const flags = {
      fraAdvarsel: /sender_email/.test(s1param(ctx, 'field')),
      egenServer: s1val(s1q(doc, '#f-documents-email-smtp_host')).trim() !== '',
      flereSprog: s1all(doc, '[data-key^="documents.email.sender_email."]').length > 1,
      fejl: b.fejl
    };
    return { state: b.kunLaes ? 'kunLaes' : (b.form ? 'formular' : 'unknown'), flags: flags, data: {} };
  }
  /** The fields in page order; the two customer mail texts were reworded under new keys (.text2), since findtekst() keeps
   *  serving the row a tenant has cached. */
  function s1mlFields() {
    const out = s1fields(S1_ML, ['smtp_host', 'smtp_user', 'smtp_password', 'smtp_encryption', 'sender_email', 'sender_name', 'customer_subject',
      'customer_text', 'mysale_subject', 'mysale_text', 'paylist_text']);
    out.customer_subject.text = 'help.smail.field.customer_subject.text2';
    out.customer_text.text = 'help.smail.field.customer_text.text2';
    return out;
  }
  const s1Mail = s1section({
    group: S1_ML,
    states: ['formular', 'kunLaes'],
    flags: ['fraAdvarsel', 'egenServer', 'flereSprog', 'fejl'],
    detect: s1mlDetect,
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'formular') {
        if (f.fraAdvarsel) return ['help.smail.about.formular', 'help.smail.about.afsender'];
        if (f.flereSprog) return ['help.smail.about.formular', 'help.smail.about.sprog'];
        return ['help.smail.about.formular'];
      }
      if (s === 'kunLaes') return ['help.smail.about.kunlaes'];
      return [];
    },
    tasks: {
      ML1: task(S1_ML, 'ML1', 3, { target: '#f-documents-email-sender_email-0', label: fieldLabel(S1_ML, 'sender_email'), kind: 'field' }),
      ML2: task(S1_ML, 'ML2', 3, { target: '#f-documents-email-smtp_host', label: fieldLabel(S1_ML, 'smtp_host'), kind: 'field' }),
      ML4: task(S1_ML, 'ML4', 3, { target: 'a.st-menu-row[href*="formularkort.php"]', label: showKey(S1_ML, 'ML4'), kind: 'button' }),
      ML5: task(S1_ML, 'ML5', 2, { target: '#f-documents-email-sender_email-0', label: fieldLabel(S1_ML, 'sender_email'), kind: 'field' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s !== 'formular') return [];
      const o = ['ML1', 'ML4', 'ML2'];
      if (f.flereSprog) o.splice(1, 0, 'ML5');
      return o;
    },
    problem: function (ctx, st) {
      return st && st.state === 'formular' && st.flags && st.flags.fraAdvarsel ? 'ML1' : '';
    },
    fields: s1mlFields(),
    questions: {
      Q1: {
        q: 'help.smail.q.q1.q',
        words: s1words(['tekst', 'mail', 'faktura', 'fakturamail', 'emne', 'besked', 'tilbud'],
          ['text', 'mail', 'invoice', 'email', 'subject', 'message', 'quote'],
          ['tekst', 'epost', 'faktura', 'emne', 'melding', 'tilbud']),
        steps: stepKeys(S1_ML, 'ML4', 1, 3),
        show: { target: 'a.st-menu-row[href*="formularkort.php"]', label: showKey(S1_ML, 'ML4'), kind: 'button' }
      },
      Q2: {
        q: 'help.smail.q.q2.q',
        words: s1words(['afsender', 'tom', 'hvem', 'fra', 'adresse', 'svar'],
          ['sender', 'empty', 'from', 'address', 'reply'],
          ['avsender', 'tom', 'fra', 'adresse', 'svar']),
        steps: ['help.smail.q.q2.step1']
      }
    },
    questionOrder: function (ctx, st) { return st && st.state !== 'unknown' ? ['Q1', 'Q2'] : []; }
  });
  addSection('documents.email', s1Mail);

  // ---- Udskrift (documents.print): Direkte print til lokal printer, Brug HTML/CSS til formulargenerering, HTML/CSS-layout
  // (only with HTML on), Kommando til PDF (settingsDefinitions.php:637-644, :970-983).
  const S1_PR = 'sprint';
  const s1Print = s1section({
    group: S1_PR,
    states: ['formular', 'kunLaes'],
    flags: [],
    detect: function (ctx) {
      const b = s1base(ctx ? ctx.doc : null);
      return { state: b.kunLaes ? 'kunLaes' : (b.form ? 'formular' : 'unknown'), flags: {}, data: {} };
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'formular') return ['help.sprint.about.formular'];
      if (s === 'kunLaes') return ['help.sprint.about.kunlaes'];
      return [];
    },
    tasks: {
      PR1: task(S1_PR, 'PR1', 3, { target: '#f-documents-print-html_forms', label: showKey(S1_PR, 'PR1'), kind: 'field' })
    },
    order: function (ctx, st) { return st && st.state === 'formular' ? ['PR1'] : []; },
    fields: { pdf_command: fieldKeys(S1_PR, 'pdf_command'), html_layout_version: fieldKeys(S1_PR, 'html_layout_version') }
  });
  addSection('documents.print', s1Print);

  // ---- Logo og baggrunde (documents.backgrounds), kind rows: the table backgrounds (Navn, create only; Kopiér fra, only for
  // a new row) and the link Baggrunds- og bilagsfiler to logoupload.php (settingsDefinitions.php:622-636, :1048).
  const S1_BG = 'sbaggrund';
  const s1Baggrund = s1section({
    group: S1_BG,
    states: ['liste', 'kunLaes'],
    flags: ['fejl'],
    rows: true,
    detect: function (ctx) {
      const doc = ctx ? ctx.doc : null;
      const b = s1base(doc);
      return { state: b.kunLaes ? 'kunLaes' : (b.form ? 'liste' : 'unknown'), flags: { fejl: !!s1q(doc, '.st-errors') }, data: {} };
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'liste') return ['help.sbaggrund.about.liste'];
      if (s === 'kunLaes') return ['help.sbaggrund.about.kunlaes'];
      return [];
    },
    tasks: {
      BG1: task(S1_BG, 'BG1', 3, { target: '.st-rows[data-table="backgrounds"] .st-rfoot button[data-add]', label: showKey(S1_BG, 'BG1'), kind: 'button' }),
      BG2: task(S1_BG, 'BG2', 2, { target: '[data-key="documents.backgrounds.files"] a.st-btn', label: showKey(S1_BG, 'BG2'), kind: 'button' }),
      BG3: task(S1_BG, 'BG3', 2, { target: '.st-errors a', label: showKey(S1_BG, 'BG3'), kind: 'status' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'liste') return (st.flags && st.flags.fejl ? ['BG3'] : []).concat(['BG2', 'BG1']);
      return [];
    },
    problem: function (ctx, st) {
      return st && st.state === 'liste' && st.flags && st.flags.fejl ? 'BG3' : '';
    },
    fields: s1fields(S1_BG, ['navn', 'kopier_fra']),
    fieldMap: { 'backgrounds.box1': 'navn', 'backgrounds.template': 'kopier_fra' }
  });
  addSection('documents.backgrounds', s1Baggrund);

  // ---- Afdelinger (organisation.departments), kind rows: Nr., Beskrivelse, Standardlager (derived), Formularnote
  // (settingsDefinitions.php:233-249); a used number is locked and a used row refuses delete (rowsView.php:280-283, rows.php:709).
  const S1_AF = 'safd';
  const s1Afdeling = s1section({
    group: S1_AF,
    states: ['tom', 'liste', 'kunLaes'],
    flags: ['fejl'],
    rows: true,
    detect: function (ctx) {
      const doc = ctx ? ctx.doc : null;
      const b = s1base(doc);
      let state = 'unknown';
      if (b.kunLaes) state = 'kunLaes';
      else if (s1q(doc, '.st-rows[data-table="departments"] .st-rempty')) state = 'tom';
      else if (b.form) state = 'liste';
      return { state: state, flags: { fejl: !!s1q(doc, '.st-errors') }, data: {} };
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'tom') return ['help.safd.about.tom'];
      if (s === 'liste') return ['help.safd.about.liste'];
      if (s === 'kunLaes') return ['help.safd.about.kunlaes'];
      return [];
    },
    tasks: {
      AF1: task(S1_AF, 'AF1', 3, { target: '.st-rows[data-table="departments"] .st-rfoot button[data-add]', label: showKey(S1_AF, 'AF1'), kind: 'button' }),
      AF2: task(S1_AF, 'AF2', 2, { target: '.st-rows[data-table="departments"] button[data-del]', label: showKey(S1_AF, 'AF2'), kind: 'button' }),
      AF3: task(S1_AF, 'AF3', 2, { target: '.st-errors a', label: showKey(S1_AF, 'AF3'), kind: 'status' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const pre = st && st.flags && st.flags.fejl ? ['AF3'] : [];
      if (s === 'tom') return pre.concat(['AF1']);
      if (s === 'liste') return pre.concat(['AF1', 'AF2']);
      return [];
    },
    problem: function (ctx, st) {
      const s = st ? st.state : '';
      return st && st.flags && st.flags.fejl && (s === 'tom' || s === 'liste') ? 'AF3' : '';
    },
    fields: s1fields(S1_AF, ['nr', 'beskrivelse', 'formularnote']),
    fieldMap: { 'departments.kodenr': 'nr', 'departments.beskrivelse': 'beskrivelse', 'departments.box2': 'formularnote' }
  });
  addSection('organisation.departments', s1Afdeling);

  // ---- Ansatte (organisation.employees), kind list. A plain visit goes on to usersRoles.php (settingsSection.php:196-207);
  // the frame shows the list only to a user without that page (hub.php:69-75 'fallback'): rows .st-irow[data-item=emp_<id>]
  // with a drawer each, Tilføj person in the head (listView.php:276-283). The person card's own tab embeds the section in a
  // dialog (embed=person&popup=1), never as the frame's page.
  const S1_AN = 'sansat';
  const s1Ansat = s1section({
    group: S1_AN,
    states: ['liste', 'tom', 'kunLaes'],
    flags: [],
    detect: function (ctx) {
      const doc = ctx ? ctx.doc : null;
      const b = s1base(doc);
      let state = 'unknown';
      if (b.kunLaes) state = 'kunLaes';
      else if (s1q(doc, '.st-irow[data-item^="emp_"]')) state = 'liste';
      else if (s1q(doc, '#st-list')) state = 'tom';
      return { state: state, flags: {}, data: {} };
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'liste' || s === 'tom') return ['help.sansat.about.liste'];
      if (s === 'kunLaes') return ['help.sansat.about.kunlaes'];
      return [];
    },
    tasks: {
      AN1: task(S1_AN, 'AN1', 2, { target: '.st-phead form button.st-btn-primary', label: showKey(S1_AN, 'AN1'), kind: 'button' }),
      AN2: task(S1_AN, 'AN2', 2, { target: '.st-irow[data-item^="emp_"] button[data-open]', label: showKey(S1_AN, 'AN2'), kind: 'button' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'liste') return ['AN2', 'AN1'];
      if (s === 'tom') return ['AN1'];
      return [];
    },
    shortcuts: []
  });
  addSection('organisation.employees', s1Ansat);

  // ---- Import og eksport (import_export.data): links only (settingsDefinitions.php:159-164, :1051-1063, :1612-1621);
  // import links are disabled spans without write (components.php:937-948).
  const S1_IM = 'simport';
  const S1_IM_LINK = function (key) { return '[data-key="import_export.data.' + key + '"] a.st-btn'; };
  const s1Import = s1section({
    group: S1_IM,
    states: ['formular', 'kunLaes'],
    flags: [],
    detect: function (ctx) {
      const b = s1base(ctx ? ctx.doc : null);
      return { state: b.kunLaes ? 'kunLaes' : (b.form ? 'formular' : 'unknown'), flags: {}, data: {} };
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'formular') return ['help.simport.about.formular'];
      if (s === 'kunLaes') return ['help.simport.about.kunlaes'];
      return [];
    },
    tasks: {
      IM1: task(S1_IM, 'IM1', 3, { target: S1_IM_LINK('accounts_import'), label: showKey(S1_IM, 'IM1'), kind: 'button' }),
      IM2: task(S1_IM, 'IM2', 3, { target: S1_IM_LINK('addresses_import'), label: showKey(S1_IM, 'IM2'), kind: 'button' }),
      IM3: task(S1_IM, 'IM3', 2, { target: S1_IM_LINK('items_import'), label: showKey(S1_IM, 'IM3'), kind: 'button' }),
      IM4: task(S1_IM, 'IM4', 2, { target: '[data-key^="import_export.data.accounts_export_"] a.st-btn', label: showKey(S1_IM, 'IM4'), kind: 'button' }),
      IM5: task(S1_IM, 'IM5', 2, { target: S1_IM_LINK('accounts_map'), label: showKey(S1_IM, 'IM5'), kind: 'button' }),
      IM6: task(S1_IM, 'IM6', 3, { target: S1_IM_LINK('forms_export'), label: showKey(S1_IM, 'IM6'), kind: 'button' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'formular') return ['IM1', 'IM2', 'IM3', 'IM4', 'IM5', 'IM6'];
      if (s === 'kunLaes') return ['IM4'];
      return [];
    },
    shortcuts: []
  });
  addSection('import_export.data', s1Import);

  // ---- Sikkerhedskopi (import_export.backup), permission settings.backup: Seneste sikkerhedskopi (with Hent when one is
  // kept, includes/settings/integrations.php:221-237), Opret sikkerhedskopi (../admin/backup.php?backup=1) and Indlæs
  // sikkerhedskopi (../admin/restore.php) (settingsDefinitions.php:165-169, :1064-1066).
  const s1BackupSection = s1section({
    group: S1_BK,
    states: ['formular', 'kunLaes'],
    flags: ['kopiFindes'],
    detect: function (ctx) {
      const doc = ctx ? ctx.doc : null;
      const b = s1base(doc);
      return { state: b.kunLaes ? 'kunLaes' : (b.form ? 'formular' : 'unknown'), flags: { kopiFindes: !!s1q(doc, 'a[href*="backup_download.php"]') }, data: {} };
    },
    about: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      if (s === 'formular') return ['help.sbackup.about.formular'];
      if (s === 'kunLaes') return ['help.sbackup.about.kunlaes'];
      return [];
    },
    tasks: {
      BK1: task(S1_BK, 'BK1', 3, { target: '[data-key="import_export.backup.take"] a.st-btn', label: showKey(S1_BK, 'BP1'), kind: 'button' }),
      BK2: task(S1_BK, 'BK2', 1, { target: 'a[href*="backup_download.php"]', label: showKey(S1_BK, 'BK2'), kind: 'button' }),
      BK3: task(S1_BK, 'BK3', 3, { target: '[data-key="import_export.backup.restore"] a.st-btn', label: showKey(S1_BK, 'BP3'), kind: 'button' })
    },
    order: function (ctx, st) {
      const s = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (s === 'formular') return f.kopiFindes ? ['BK1', 'BK2', 'BK3'] : ['BK1', 'BK3'];
      return [];
    },
    shortcuts: [],
    questions: {
      Q1: {
        q: 'help.sbackup.q.q1.q',
        words: s1words(['indlæse', 'gendanne', 'overskrive', 'sikkerhedskopi', 'backup', 'sker'],
          ['restore', 'load', 'overwrite', 'backup', 'happens'],
          ['lese', 'gjenopprette', 'overskrive', 'sikkerhetskopi', 'backup']),
        steps: ['help.sbackup.q.q1.step1', 'help.sbackup.q.q1.step2']
      }
    },
    questionOrder: function (ctx, st) { return st && st.state === 'formular' ? ['Q1'] : []; }
  });
  addSection('import_export.backup', s1BackupSection);

  // ==================================================================== S2: Salg, Køb, Provision, Projekter, Kommissionsvarer
  // Twelve settings sections (spec §24, help-ls/BRIEF_SETUP.md §3), each its own page object and text group, registered with
  // addSection(). Evidence, systemdata/settingsSection.php unless named:
  // - form view (sales.debtor_card, sales.orders, sales.reminders, sales.mysale, purchase.orders, organisation.commission,
  //   items.consignment): form#st-form (:644), a field row div.st-field[data-key="<key>"] with the control #f-<key, . as ->
  //   (includes/settings/components.php:769-772, :798); a switch is button.st-switch[aria-checked] plus a hidden
  //   [data-control] (:826-828); a locked field has .st-locked (:819-821); a field hidden by its switch has [hidden] (:798).
  //   Save bar #st-savebar with Fortryd #st-undo (159) and Gem ændringer #st-save (6045), only with write access (:747-757);
  //   Seneste ændringer .st-hist with Gendan button[data-restore] (5711, :760-780); actions button[data-run="<key>"]
  //   (components.php:991).
  // - rows view (sales.debtor_groups, purchase.creditor_groups, sales.discounts, organisation.projects): the same form and bar
  //   (includes/settings/rowsView.php:130-224), one card .st-rows[data-table="<table>"] per table (:361), rows
  //   tbody tr[data-row] with cells named r[<table>][<row>][<column>] (:291-293, :373-403), the add button .st-rfoot
  //   button[data-add] (:428-429), an empty table .st-rempty with Opret dansk standardsæt [data-seed] (6824) and Tilføj selv
  //   (:413-418), Kopiér fra %s button[form^="st-copy-"] (6440, :441-442), row actions button[data-row-action="<name>"]
  //   (:391), the eye button[data-inactive] (6433, :393), the trash button[data-del] (1099, :394), Vis inaktive
  //   input[data-show-inactive] (6432, :432); a per-year table has Gælder regnskabsåret select#st-year (6439, :102-110). The rows' history has no
  //   Gendan (:231-246).
  // - list view (purchase.pricelists): rows .st-irow[data-item="pl_<id>"] with Indstil button[data-open] (6122),
  //   drawers aside.st-drawer[data-item] (hidden until opened), each with its form .st-dform, its actions and Gem ændringer
  //   [data-save] (includes/settings/listView.php:319-330, :370-450); the head's Tilføj prisliste is a submit button in a
  //   form inside .st-phead (:270-282); no lists: .st-list-empty (6250, :331).
  // - always: Tilbage a[data-back] (5647, accesskey l, components.php:761-767); read-only .st-flash-info (5755, :567-569);
  //   a refused save .st-flash-err (5731) with the error list ul.st-errorlist (form, :650-655) or div.st-errors
  //   (rowsView.php:135-153); someone else saved first .st-flash-warn (5728/5744, :535-538); saved .st-flash-ok (5709).
  // Keyboard: Ctrl+S saves (javascript/settingsSection.js:233-236, settingsRows.js:107-112, settingsList.js:344-348);
  // in a table Enter moves down and adds a row at the bottom, Esc puts the cell back (settingsRows.js:115-121); Esc closes
  // a drawer (settingsList.js:349-352); Alt+L is Tilbage.

  function s2Q(root, sel) {
    try {
      const el = root && root.querySelector ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function s2Qa(root, sel) {
    const out = [];
    try {
      const list = root && root.querySelectorAll ? root.querySelectorAll(sel) : [];
      for (let i = 0; i < list.length; i++) if (list[i] && list[i].nodeType === 1) out.push(list[i]);
    } catch (e) {
      return [];
    }
    return out;
  }

  function s2Ids(list) {
    return unique(list.filter(function (id) { return !!id; }));
  }

  /** The row of one setting: div.st-field[data-key] (components.php:798). */
  function s2Row(key) {
    return '.st-field[data-key="' + key + '"]';
  }

  /** Whether the switch of a setting is on (button.st-switch[aria-checked], components.php:827). */
  function s2On(doc, key) {
    return attr(s2Q(doc, s2Row(key) + ' .st-switch'), 'aria-checked') === 'true';
  }

  /** The rows of a table (rowsView.php:369-405); the template row is not part of the document. */
  function s2TableRows(doc, table) {
    return s2Qa(doc, '.st-rows[data-table="' + table + '"] tbody tr[data-row]:not([data-row="__N__"])');
  }

  function s2Value(el) {
    try {
      return el && el.nodeType === 1 ? String(el.value === undefined || el.value === null ? '' : el.value).trim() : '';
    } catch (e) {
      return '';
    }
  }

  /** What every section page shows: read-only access, a refused save, the history's Gendan. */
  function s2Base(ctx) {
    const doc = ctx ? ctx.doc : null;
    return {
      doc: doc,
      ro: !!s2Q(doc, '.st-flash-info'),
      form: !!s2Q(doc, '#st-form'),
      list: !!s2Q(doc, '#st-list'),
      flags: {
        fejl: !!s2Q(doc, '.st-errorlist, .st-errors, .st-flash-err, .st-flash-warn'),
        historik: !!s2Q(doc, 'button[data-restore]')
      }
    };
  }

  /** The state of a section: kunLaes, or the view's own state; unknown on another page. */
  function s2State(b, own) {
    if (b.ro && (b.form || b.list)) return 'kunLaes';
    return own;
  }

  /** The open drawer of a list section (settingsList.js:115 removes its hidden), or null. */
  function s2OpenDrawer(doc) {
    return s2Q(doc, 'aside.st-drawer[data-item]:not([hidden])');
  }

  /**
   * Field keys of the section pages (spec §1.6): f[<group>.<section>.<name>(.<n>)] gives <name> without a trailing _<n>
   * (sales.reminders.fee_item_1.0 → fee_item), a table cell r[<table>][<row>][<column>] the name the section maps it to,
   * the year select #st-year aar, the row-action dialog's input (name arg) the section's name for it, the discount
   * matrix's cells (.st-mcell) matrix and their type (.st-mtype) type.
   */
  function s2FieldKey(cols) {
    return function (el) {
      const n = attr(el, 'name');
      let m = /^f\[[a-z_]+\.[a-z_]+\.([a-z0-9_]+)(?:\.\d+)?\]$/.exec(n);
      if (m) {
        const k = m[1].replace(/_\d+$/, '');
        return /^[a-z_]+$/.test(k) ? k : null;
      }
      m = /^r\[([a-z_]+)\]\[[^\]]*\]\[([a-z0-9_]+)\]$/.exec(n);
      if (m) return (cols && (cols[m[1] + '.' + m[2]] || cols[m[2]])) || null;
      if (n === 'arg') return (cols && cols.arg) || null;
      if (attr(el, 'id') === 'st-year') return 'aar';
      const cls = ' ' + attr(el, 'class') + ' ';
      if (cls.indexOf(' st-mcell ') >= 0) return 'matrix';
      if (cls.indexOf(' st-mtype ') >= 0) return 'type';
      return null;
    };
  }

  function s2Fields(g, keys) {
    const out = {};
    keys.forEach(function (k) { out[k] = fieldKeys(g, k); });
    return out;
  }

  function s2Words(da, en, no) {
    return { da: da, en: en, no: no };
  }

  /**
   * A section's page object (§3.2) from its own parts plus what every section has: the problem task FE (a refused save,
   * a refused action or someone else's save first), Gem ændringer (GM), Gendan on a form with history (GD), the
   * read-only question QL, the keys. def: group, view ('form' | 'rows' | 'list'), states (besides kunLaes), flags (its
   * own), detect(ctx, b) → { state, flags, data }, about(st) → the first key or keys, tasks, order(st), fields, cols,
   * questions, questionOrder(st).
   */
  function s2Page(def) {
    const g = def.group;
    const view = def.view;
    const tasks = Object.assign({}, def.tasks);
    tasks.FE = task(g, 'FE', 4, { target: '.st-errorlist, .st-errors, .st-flash-err, .st-flash-warn', label: showKey(g, 'FE'), kind: 'status' });
    if (view === 'list') {
      tasks.GM = task(g, 'GM', 3, {
        target: function (ctx) {
          const d = s2OpenDrawer(ctx.doc);
          return d ? s2Q(d, 'button[data-save]') : s2Q(ctx.doc, '.st-irow button[data-open]');
        },
        label: showKey(g, 'GM'),
        kind: 'button'
      });
    } else {
      tasks.GM = task(g, 'GM', 3, { target: '#st-save', label: showKey(g, 'GM'), kind: 'button' });
    }
    if (view === 'form') tasks.GD = task(g, 'GD', 3, { target: 'button[data-restore]', label: showKey(g, 'GD'), kind: 'button' });

    const questions = Object.assign({}, def.questions);
    questions.QL = {
      q: 'help.' + g + '.q.ql.q',
      words: s2Words(['rette', 'ret', 'låst', 'læseadgang', 'adgang', 'grå', 'gem', 'kan ikke'],
        ['edit', 'change', 'locked', 'read-only', 'access', 'grey', 'save', 'cannot'],
        ['rette', 'endre', 'låst', 'lesetilgang', 'tilgang', 'grå', 'lagre', 'kan ikke']),
      intro: 'help.' + g + '.q.ql.intro',
      steps: ['help.' + g + '.q.ql.step1']
    };

    const keys = [
      { keys: 'Ctrl+S', text: 'help.' + g + '.key.01', group: 'keys' },
      { keys: 'Alt+L', text: 'help.' + g + '.key.02', group: 'keys' }
    ];
    if (view === 'rows') {
      keys.push({ keys: 'Enter', text: 'help.' + g + '.key.03', group: 'keys' });
      keys.push({ keys: 'Esc', text: 'help.' + g + '.key.04', group: 'keys' });
    }
    if (view === 'list') keys.push({ keys: 'Esc', text: 'help.' + g + '.key.03', group: 'keys' });

    const page = {
      group: g,
      states: def.states.concat(['kunLaes']),
      flags: ['fejl', 'historik'].concat(def.flags || []),

      name: function () {
        return 'help.' + g + '.name';
      },

      detect: function (ctx) {
        const b = s2Base(ctx);
        let own = { state: 'unknown', flags: {}, data: {} };
        try {
          own = def.detect(ctx, b) || own;
        } catch (e) {
          own = { state: 'unknown', flags: {}, data: {} };
        }
        const flags = Object.assign({}, own.flags || {}, b.flags);
        if (view !== 'form') flags.historik = false;
        return { state: s2State(b, own.state), flags: flags, data: own.data || {} };
      },

      about: function (ctx, st) {
        const s = st ? st.state : 'unknown';
        if (s === 'unknown') return [];
        const first = def.about(st || { state: s, flags: {}, data: {} });
        const list = Array.isArray(first) ? first.slice(0, 2) : [first];
        if (s === 'kunLaes') return [list[0], 'help.' + g + '.about.kunlaes'];
        return list;
      },

      tasks: tasks,

      order: function (ctx, st) {
        const s = st ? st.state : 'unknown';
        const f = (st && st.flags) || {};
        if (s === 'unknown' || s === 'kunLaes') return [];
        // An empty list has nothing to open and save yet (listView.php:331).
        const save = view === 'list' && s === 'tom' ? '' : 'GM';
        return s2Ids([f.fejl ? 'FE' : ''].concat(def.order(st), [view === 'form' && f.historik ? 'GD' : '', save]));
      },

      problem: function (ctx, st) {
        const s = st ? st.state : 'unknown';
        return s !== 'unknown' && s !== 'kunLaes' && st.flags && st.flags.fejl ? 'FE' : '';
      },

      fields: s2Fields(g, def.fields || []),
      fieldKey: s2FieldKey(def.cols || {}),
      shortcuts: keys,
      shortcutsNote: 'help.common.shortcuts_note',
      questions: questions,

      questionOrder: function (ctx, st) {
        const s = st ? st.state : 'unknown';
        if (s === 'unknown') return [];
        if (s === 'kunLaes') return ['QL'];
        return def.questionOrder ? def.questionOrder(st).slice(0, 3) : [];
      },

      signal: function () {
        return false;
      }
    };
    return page;
  }

  /** The state of a form section: formular when the form is there. */
  function s2FormDetect(extra) {
    return function (ctx, b) {
      return { state: b.form ? 'formular' : 'unknown', flags: extra ? extra(b.doc) : {}, data: {} };
    };
  }

  /** The state of a rows section by one of its tables: tabel with rows, tom without (rowsView.php:369-419). */
  function s2RowsDetect(table, extra) {
    return function (ctx, b) {
      if (!b.form || !s2Q(b.doc, '.st-rows[data-table="' + table + '"]')) return { state: 'unknown', flags: {}, data: {} };
      const rows = s2TableRows(b.doc, table);
      const flags = extra ? extra(b.doc, rows) : {};
      return { state: rows.length ? 'tabel' : 'tom', flags: flags, data: { rows: rows.length } };
    };
  }

  // ---- Debitorkort (systemdata/settingsSection.php?s=sales.debtor_card)
  // Evidence: systemdata/settingsDefinitions.php:106-112 (the section), :725-736 (six switches, DIV/2 and settings ordre).
  // Debitorgruppe starts empty and Gem/opdatér warns Debitorgruppe ikke valgt with the switch on, else a new card has
  // group 1 (debitor/debitorkort.php:1861-1864, :571-575); Kundeansvarlig ikke valgt (:567-569); Opgaveliste is enabled in
  // the card's footer only with jobkort on (:2616, :2644-2648); the order reads showBothAddrExtra (settings ordre).
  const S2_DK = 'sdebkort';
  const s2DebtorCard = s2Page({
    group: S2_DK,
    view: 'form',
    states: ['formular'],
    flags: [],
    detect: s2FormDetect(null),
    about: function () { return 'help.sdebkort.about.formular'; },
    tasks: {
      DC1: task(S2_DK, 'DC1', 3, { target: s2Row('sales.debtor_card.mandatory_group'), label: showKey(S2_DK, 'DC1'), kind: 'field' }),
      DC2: task(S2_DK, 'DC2', 2, { target: s2Row('sales.debtor_card.job_cards'), label: showKey(S2_DK, 'DC2'), kind: 'field' }),
      DC3: task(S2_DK, 'DC3', 2, { target: s2Row('sales.debtor_card.show_both_addresses'), label: showKey(S2_DK, 'DC3'), kind: 'field' })
    },
    order: function () { return ['DC1', 'DC2', 'DC3']; },
    // Q1: on save the card asks for a customer manager with DIV/2 box2 and for a group whenever it has none
    // (debitor/debitorkort.php:563-575); a new card starts without a group only with box1 (:1859-1862). Q2: an order takes
    // the account number as its phone when the card has none and box5 is on (debitor/ordre.php:731-735, :1782-1789; 1060,
    // 1061). Q3: debitoripad puts an icon by E-mail on the order (ordre.php:5002-5004, 2540) that opens a choice of the
    // users named debitoripad* (debitoripad/choose.php:53-56); 2369, 2370.
    questions: {
      Q1: {
        q: 'help.sdebkort.q.q1.q',
        words: s2Words(['eksisterende kunder', 'gamle kunder', 'gælder', 'alle kunder', 'kundeansvarlig', 'debitorgruppe'],
          ['existing customers', 'old customers', 'apply', 'all customers', 'customer manager', 'debtor group'],
          ['eksisterende kunder', 'gamle kunder', 'gjelder', 'alle kunder', 'kundeansvarlig', 'debitorgruppe']),
        intro: 'help.sdebkort.q.q1.intro',
        steps: ['help.sdebkort.q.q1.step1']
      },
      Q2: {
        q: 'help.sdebkort.q.q2.q',
        words: s2Words(['telefonnummer', 'kontonummer', 'telefon', 'ordre', 'forkert nummer'],
          ['phone number', 'account number', 'phone', 'order', 'wrong number'],
          ['telefonnummer', 'kontonummer', 'telefon', 'ordre', 'feil nummer']),
        intro: 'help.sdebkort.q.q2.intro',
        steps: ['help.sdebkort.q.q2.step1', 'help.sdebkort.q.q2.step2'],
        show: { target: s2Row('sales.debtor_card.account_as_phone'), label: 'help.sdebkort.q.q2.show', kind: 'field' }
      },
      Q3: {
        q: 'help.sdebkort.q.q3.q',
        words: s2Words(['ipad', 'debitoripad', 'e-mail', 'kunden skriver', 'tablet'],
          ['ipad', 'debtor ipad', 'email', 'customer types', 'tablet'],
          ['ipad', 'debitoripad', 'e-post', 'kunden skriver', 'nettbrett']),
        intro: 'help.sdebkort.q.q3.intro',
        steps: ['help.sdebkort.q.q3.step1', 'help.sdebkort.q.q3.step2'],
        show: { target: s2Row('sales.debtor_card.debtor_ipad'), label: 'help.sdebkort.q.q3.show', kind: 'field' }
      }
    },
    questionOrder: function () { return ['Q1', 'Q2', 'Q3']; }
  });
  addSection('sales.debtor_card', s2DebtorCard);

  // ---- Debitorgrupper (systemdata/settingsSection.php?s=sales.debtor_groups) and Kreditorgrupper (purchase.creditor_groups)
  // Evidence: settingsDefinitions.php:384-407 and :408-429 (table groups, per fiscal year, columns, Flyt samlekonto,
  // inactive, the Danish standard set; box10 EU-zone goes to every year, includes/settings/rows.php:656-659). A new fiscal
  // year copies the groups of the year before (includes/settings/rowHooks.php:269-273). Flyt samlekonto shows on a group
  // with an account and customers or suppliers (rowHooks.php:707-708), asks for Ny samlekonto (6886) and posts the open
  // items to the new account today; the group gets the account's currency (rowHooks.php:994-1035). A used number and a
  // used control account are read-only (rowsView.php:296-302). The trash refuses a used group and offers Markér inaktiv
  // (javascript/settingsRows.js:195-214); an inactive group is not offered on the cards (debitor/debitorkort.php:1871,
  // kreditor/kreditorkort.php:425). Where the boxes act: an order takes VAT rate, currency, form language, discount, B2B and
  // reverse charge from the customer's group (debitor/ordre.php:811-831, :1816-1840: Debitorgrupper forkert opsat, a
  // group without VAT code is 0 %), the invoice posts to the group's control account in the year (includes/ordrefunc.php:
  // 2238-2245); a supplier's E or Y code gives no VAT on the order and EU VAT at posting (kreditor/ordre.php:514-530,
  // kreditor/bogfor.php:464-490), S.moms grp. is required with OB (settingsDefinitions.php:421); the EU zone splits the VAT
  // boxes A, B, C and OSS (finans/rapport_includes/moms_rubrik.php:202-264, moms_oss.php:233-270).
  const S2_GROUP_COLS = {
    'groups.kodenr': 'nr', 'groups.beskrivelse': 'beskrivelse', 'groups.box1': 'momsgrp', 'groups.box2': 'samlekonto',
    'groups.box3': 'valuta', 'groups.box4': 'sprog', 'groups.box5': 'modkonto', 'groups.box8': 'btob', 'groups.box9': 'ob',
    'groups.box10': 'eu_zone', arg: 'ny_samlekonto'
  };

  /** The flags of a group table: an empty year that can be copied or seeded, Flyt samlekonto, one group, no EU zone. */
  function s2GroupFlags(doc, rows) {
    let zones = 0;
    rows.forEach(function (tr) {
      if (s2Value(s2Q(tr, 'select[name$="[box10]"]')) !== '') zones++;
    });
    return {
      kopier: !!s2Q(doc, 'button[form^="st-copy-"]'),
      standard: !!s2Q(doc, '.st-rows[data-table="groups"] [data-seed]'),
      flyt: !!s2Q(doc, '.st-rows[data-table="groups"] button[data-row-action="move_control"]'),
      enGruppe: rows.length === 1,
      udenEuZone: rows.length > 1 && zones === 0
    };
  }

  /** The first row's cell of a column in the group table. */
  function s2GroupCell(col, tag) {
    return '.st-rows[data-table="groups"] tbody tr[data-row]:not([data-row="__N__"]) ' + (tag || 'input') + '[name$="[' + col + ']"]';
  }

  const S2_GROUP_SHOW = {
    add: '.st-rows[data-table="groups"] .st-rfoot button[data-add]',
    seed: '.st-rows[data-table="groups"] [data-seed]',
    copy: 'button[form^="st-copy-"]',
    move: '.st-rows[data-table="groups"] button[data-row-action="move_control"]',
    del: '.st-rows[data-table="groups"] tbody tr[data-row]:not([data-row="__N__"]) button[data-del]'
  };

  function s2GroupTasks(g, p) {
    const t = {};
    t[p + '1'] = task(g, p + '1', 3, { target: S2_GROUP_SHOW.add, label: showKey(g, p + '1'), kind: 'button' });
    t[p + '2'] = task(g, p + '2', p === 'DG' ? 4 : 3, { target: S2_GROUP_SHOW.add, label: showKey(g, p + '2'), kind: 'button' });
    t[p + '3'] = task(g, p + '3', 3, { target: s2GroupCell('box10', 'select'), label: showKey(g, p + '3'), kind: 'field' });
    t[p + '4'] = task(g, p + '4', 3, { target: S2_GROUP_SHOW.move, label: showKey(g, p + '4'), kind: 'button' });
    t[p + '5'] = task(g, p + '5', 3, { target: '#st-year', label: showKey(g, p + '5'), kind: 'field' });
    t[p + '6'] = task(g, p + '6', 3, { target: S2_GROUP_SHOW.del, label: showKey(g, p + '6'), kind: 'button' });
    t[p + '8'] = task(g, p + '8', 2, { target: S2_GROUP_SHOW.seed, label: showKey(g, p + '8'), kind: 'button' });
    t[p + '9'] = task(g, p + '9', 2, { target: S2_GROUP_SHOW.copy, label: showKey(g, p + '9'), kind: 'button' });
    return t;
  }

  function s2GroupOrder(p, special) {
    return function (st) {
      const f = st.flags || {};
      if (st.state === 'tom') return [f.kopier ? p + '9' : '', f.standard ? p + '8' : '', p + '1', p + '2', p + '5'];
      const main = f.udenEuZone ? [p + '3', p + '1', special] : (f.enGruppe ? [p + '2', p + '1', special] : [p + '1', special, p + '2']);
      return main.concat([p + '3', f.flyt ? p + '4' : '', p + '5', p + '6', f.flyt ? '' : p + '4']);
    };
  }

  function s2GroupQuestions(g, p, wordsQ3) {
    return {
      Q1: {
        q: 'help.' + g + '.q.q1.q',
        words: s2Words(['samlekonto', 'låst', 'grå', 'kan ikke rette', 'ændre samlekonto', 'skifte konto', 'flyt'],
          ['control account', 'locked', 'grey', 'cannot change', 'change control account', 'switch account', 'move'],
          ['samlekonto', 'låst', 'grå', 'kan ikke endre', 'endre samlekonto', 'bytte konto', 'flytt']),
        intro: 'help.' + g + '.q.q1.intro',
        steps: stepKeys(g, p + '4', 1, 3),
        show: { target: S2_GROUP_SHOW.move, label: showKey(g, p + '4'), kind: 'button' }
      },
      Q2: {
        q: 'help.' + g + '.q.q2.q',
        words: s2Words(['slet', 'slette', 'fjern', 'gruppe', 'i brug', 'inaktiv', 'kan ikke slette'],
          ['delete', 'remove', 'group', 'in use', 'inactive', 'cannot delete'],
          ['slett', 'slette', 'fjern', 'gruppe', 'i bruk', 'inaktiv', 'kan ikke slette']),
        steps: stepKeys(g, p + '6', 2, 3),
        show: { target: S2_GROUP_SHOW.del, label: showKey(g, p + '6'), kind: 'button' }
      },
      Q3: {
        q: 'help.' + g + '.q.q3.q',
        words: wordsQ3,
        steps: ['help.' + g + '.q.q3.step1', 'help.' + g + '.q.q3.step2', 'help.' + g + '.q.q3.step3'],
        show: { target: s2GroupCell('box1', 'select'), label: fieldLabel(g, 'momsgrp'), kind: 'field' }
      },
      Q4: {
        q: 'help.' + g + '.q.q4.q',
        words: p === 'DG'
          ? s2Words(['forkert opsat', 'debitorgrupper forkert opsat', 'momskode', 'ikke gyldigt', 'kontonummer', 'faktura fejler'],
            ['set up wrong', 'debtor groups', 'vat code', 'not valid', 'account number', 'invoice fails'],
            ['feil satt opp', 'debitorgrupper', 'mva-kode', 'ikke gyldig', 'kontonummer', 'faktura feiler'])
          : s2Words(['ingen moms', 'moms mangler', 'eu', 'erhvervelsesmoms', 'ydelsesmoms', 'købsordre', 'leverandør'],
            ['no vat', 'vat missing', 'eu', 'acquisition vat', 'services vat', 'purchase order', 'supplier'],
            ['ingen mva', 'mva mangler', 'eu', 'ervervsmva', 'tjenestemva', 'innkjøpsordre', 'leverandør']),
        intro: 'help.' + g + '.q.q4.intro',
        steps: ['help.' + g + '.q.q4.step1', 'help.' + g + '.q.q4.step2'],
        show: { target: s2GroupCell('box1', 'select'), label: fieldLabel(g, 'momsgrp'), kind: 'field' }
      }
    };
  }

  const S2_DG = 'sdebgrp';
  const S2_DG_TASKS = s2GroupTasks(S2_DG, 'DG');
  // B2B: a new order line takes the item's B2B price (includes/ordrefunc.php:4009-4012, :4104-4105); the discount on order
  // lines comes from the discount matrix, by debtor group when there are no customer discount groups (ordrefunc.php:
  // 4308-4327). Rabat % (box6) is read by the commission report only (finans/provisionsrapport.php:166-178); debitor/ordre.php
  // reads it (:814, :1669) but does not use it. A bool cell is a hidden input and the checkbox (rowsView.php:310-314).
  S2_DG_TASKS.DG7 = task(S2_DG, 'DG7', 3, { target: s2GroupCell('box8', 'input.st-rcheck'), label: showKey(S2_DG, 'DG7'), kind: 'field' });
  const s2DebtorGroups = s2Page({
    group: S2_DG,
    view: 'rows',
    states: ['tabel', 'tom'],
    flags: ['kopier', 'standard', 'flyt', 'enGruppe', 'udenEuZone'],
    detect: s2RowsDetect('groups', s2GroupFlags),
    about: function (st) {
      const f = st.flags || {};
      if (st.state === 'tom') return ['help.sdebgrp.about.tabel', 'help.sdebgrp.about.tom'];
      if (f.udenEuZone) return ['help.sdebgrp.about.tabel', 'help.sdebgrp.about.uden_eu_zone'];
      return ['help.sdebgrp.about.tabel', 'help.sdebgrp.about.aar'];
    },
    tasks: S2_DG_TASKS,
    order: s2GroupOrder('DG', 'DG7'),
    fields: ['nr', 'beskrivelse', 'momsgrp', 'samlekonto', 'valuta', 'sprog', 'modkonto', 'rabat', 'btob', 'ob', 'eu_zone', 'aar', 'ny_samlekonto'],
    cols: Object.assign({ 'groups.box6': 'rabat' }, S2_GROUP_COLS),
    questions: s2GroupQuestions(S2_DG, 'DG', s2Words(['eu', 'udland', 'udlandet', 'eksport', 'moms', 'uden moms', 'momsfri', 'b2b', 'oss'],
      ['eu', 'abroad', 'foreign', 'export', 'vat', 'without vat', 'vat free', 'b2b', 'oss'],
      ['eu', 'utland', 'utlandet', 'eksport', 'mva', 'uten mva', 'mva-fri', 'b2b', 'oss'])),
    // An empty year asks about the order that refuses, then the EU customer; groups without EU zone lead with the EU question
    questionOrder: function (st) {
      const f = st.flags || {};
      if (st.state === 'tom') return ['Q4', 'Q3'];
      if (f.enGruppe) return ['Q3', 'Q4', 'Q1'];
      return f.udenEuZone ? ['Q3', 'Q1', 'Q2'] : ['Q1', 'Q2', 'Q3'];
    }
  });
  addSection('sales.debtor_groups', s2DebtorGroups);

  const S2_KG = 'skredgrp';
  const S2_KG_TASKS = s2GroupTasks(S2_KG, 'KG');
  // OB and S.moms grp. of a creditor group (settingsDefinitions.php:419-421, kreditor/bogfor.php:464-468)
  S2_KG_TASKS.KG7 = task(S2_KG, 'KG7', 3, { target: s2GroupCell('box9', 'input.st-rcheck'), label: showKey(S2_KG, 'KG7'), kind: 'field' });
  const s2CreditorGroups = s2Page({
    group: S2_KG,
    view: 'rows',
    states: ['tabel', 'tom'],
    flags: ['kopier', 'standard', 'flyt', 'enGruppe', 'udenEuZone'],
    detect: s2RowsDetect('groups', s2GroupFlags),
    about: function (st) {
      const f = st.flags || {};
      if (st.state === 'tom') return ['help.skredgrp.about.tabel', 'help.skredgrp.about.tom'];
      if (f.udenEuZone) return ['help.skredgrp.about.tabel', 'help.skredgrp.about.uden_eu_zone'];
      return ['help.skredgrp.about.tabel', 'help.skredgrp.about.aar'];
    },
    tasks: S2_KG_TASKS,
    order: s2GroupOrder('KG', 'KG7'),
    fields: ['nr', 'beskrivelse', 'momsgrp', 'samlekonto', 'valuta', 'modkonto', 's_moms', 'ob', 'eu_zone', 'aar', 'ny_samlekonto'],
    cols: Object.assign({ 'groups.box6': 's_moms' }, S2_GROUP_COLS),
    questions: s2GroupQuestions(S2_KG, 'KG', s2Words(['eu', 'udland', 'udlandet', 'import', 'moms', 'erhvervelsesmoms', 'ydelser', 'omvendt betalingspligt'],
      ['eu', 'abroad', 'foreign', 'import', 'vat', 'acquisition', 'services', 'reverse charge'],
      ['eu', 'utland', 'utlandet', 'import', 'mva', 'ervervsmva', 'tjenester', 'omvendt avgiftsplikt'])),
    questionOrder: function (st) {
      const f = st.flags || {};
      if (st.state === 'tom') return ['Q3', 'Q4'];
      if (f.enGruppe) return ['Q3', 'Q4', 'Q1'];
      return f.udenEuZone ? ['Q3', 'Q1', 'Q4'] : ['Q1', 'Q4', 'Q2'];
    }
  });
  addSection('purchase.creditor_groups', s2CreditorGroups);

  // ---- Ordrer & fakturering (systemdata/settingsSection.php?s=sales.orders)
  // Evidence: settingsDefinitions.php:113-120, :740-836. Hurtigfakturering (DIV/3 box4) is locked on while an item group
  // uses batch control (locked_if batch_control, text 7290, components.php:819-821); a new company has quick invoicing and
  // immediate posting on (includes/opretfunc.php:718; a lone 'on' in box5 is both sales and purchase,
  // includes/settings/SettingsService.php:768-770). Immediate posting off: the invoice waits as status 3 (debitor help
  // dord about.ikke_bogfoert). Kontant and Kreditkort payment terms post to the cash and card accounts, else an open item
  // (includes/ordrefunc.php:2690-2712). Samlet pris needs Varenr. for rabat (validate requires, text 1875). Massefakturering:
  // Fakturér alt on the order list's Ordrer tab (debitor/ordreliste.php:2685-2699) delivers and invoices every order that
  // can be delivered (debitor/massefakt.php:85-104). Porto: the item goes on new orders (5697).
  const S2_OF = 'sordre';
  const s2Orders = s2Page({
    group: S2_OF,
    view: 'form',
    states: ['formular'],
    flags: ['hurtig', 'udenStraks', 'hurtigLaast'],
    detect: s2FormDetect(function (doc) {
      return {
        hurtig: s2On(doc, 'sales.orders.quick_invoice'),
        udenStraks: !!s2Q(doc, s2Row('sales.orders.post_sales_immediately')) && !s2On(doc, 'sales.orders.post_sales_immediately'),
        // the lock shows only while quick invoicing is off (components.php:673-682)
        hurtigLaast: !!s2Q(doc, s2Row('sales.orders.quick_invoice') + ' .st-locked')
      };
    }),
    about: function (st) {
      const f = st.flags || {};
      let second = f.hurtig ? 'help.sordre.about.hurtig' : 'help.sordre.about.uden_hurtig';
      if (f.hurtigLaast) second = 'help.sordre.about.hurtig_laast';
      if (f.udenStraks) second = 'help.sordre.about.uden_straks';
      return ['help.sordre.about.formular', second];
    },
    tasks: {
      OF1: task(S2_OF, 'OF1', 3, { target: s2Row('sales.orders.quick_invoice'), label: showKey(S2_OF, 'OF1'), kind: 'field' }),
      OF2: task(S2_OF, 'OF2', 3, { target: s2Row('sales.orders.quick_invoice'), label: showKey(S2_OF, 'OF2'), kind: 'field' }),
      OF3: task(S2_OF, 'OF3', 3, { target: s2Row('sales.orders.post_sales_immediately'), label: showKey(S2_OF, 'OF3'), kind: 'field' }),
      OF4: task(S2_OF, 'OF4', 3, { target: s2Row('sales.orders.vat_private'), label: showKey(S2_OF, 'OF4'), kind: 'field' }),
      OF5: task(S2_OF, 'OF5', 3, { target: s2Row('sales.orders.cash_account'), label: showKey(S2_OF, 'OF5'), kind: 'field' }),
      OF6: task(S2_OF, 'OF6', 3, { target: s2Row('sales.orders.postage_item'), label: showKey(S2_OF, 'OF6'), kind: 'field' }),
      OF7: task(S2_OF, 'OF7', 3, { target: s2Row('sales.orders.bundle_price'), label: showKey(S2_OF, 'OF7'), kind: 'field' }),
      OF8: task(S2_OF, 'OF8', 3, { target: s2Row('sales.orders.mass_invoicing'), label: showKey(S2_OF, 'OF8'), kind: 'field' }),
      OF9: task(S2_OF, 'OF9', 2, { target: s2Row('sales.orders.hide_margin'), label: showKey(S2_OF, 'OF9'), kind: 'field' }),
      OF10: task(S2_OF, 'OF10', 2, { target: s2Row('sales.orders.out_of_stock_warning'), label: showKey(S2_OF, 'OF10'), kind: 'field' })
    },
    order: function (st) {
      const f = st.flags || {};
      const quick = f.hurtig ? 'OF1' : (f.hurtigLaast ? '' : 'OF2');
      const rest = ['OF4', 'OF5', 'OF6', 'OF7', 'OF8', 'OF9', 'OF10', f.hurtigLaast && !f.hurtig ? 'OF2' : ''];
      return (f.udenStraks ? ['OF3', quick] : [quick, 'OF3']).concat(rest);
    },
    // Field help only where it adds to the field's own help line (components.php:807-809): the others say it already.
    fields: ['mass_delivery_deadline', 'discount_item', 'postage_item', 'cash_account', 'card_account'],
    questions: {
      Q1: {
        q: 'help.sordre.q.q1.q',
        words: s2Words(['tilbud', 'levér', 'følgeseddel', 'fanen tilbud', 'mangler', 'hurtigfakturering', 'levering'],
          ['offer', 'quote', 'deliver', 'packing slip', 'missing', 'fast invoicing', 'delivery'],
          ['tilbud', 'levere', 'følgeseddel', 'fanen tilbud', 'mangler', 'hurtigfakturering', 'levering']),
        intro: 'help.sordre.q.q1.intro',
        steps: stepKeys(S2_OF, 'OF1', 1, 3),
        show: { target: s2Row('sales.orders.quick_invoice'), label: showKey(S2_OF, 'OF1'), kind: 'field' }
      },
      Q2: {
        q: 'help.sordre.q.q2.q',
        words: s2Words(['ikke bogført', 'bogføres ikke', 'faktura', 'kontiene', 'omgående bogføring', 'straksbogføring', 'mangler i regnskabet'],
          ['not posted', 'invoice', 'accounts', 'immediate posting', 'missing in the accounts'],
          ['ikke bokført', 'bokføres ikke', 'faktura', 'kontoene', 'umiddelbar postering', 'mangler i regnskapet']),
        intro: 'help.sordre.q.q2.intro',
        steps: stepKeys(S2_OF, 'OF3', 1, 3),
        show: { target: s2Row('sales.orders.post_sales_immediately'), label: showKey(S2_OF, 'OF3'), kind: 'field' }
      },
      Q3: {
        q: 'help.sordre.q.q3.q',
        words: s2Words(['kontant', 'kontantsalg', 'kort', 'kortsalg', 'åben post', 'skylder', 'kreditkort'],
          ['cash', 'cash sale', 'card', 'card sale', 'open item', 'owes', 'credit card'],
          ['kontant', 'kontantsalg', 'kort', 'kortsalg', 'åpen post', 'skylder', 'kredittkort']),
        intro: 'help.sordre.q.q3.intro',
        steps: stepKeys(S2_OF, 'OF5', 1, 3),
        show: { target: s2Row('sales.orders.cash_account'), label: showKey(S2_OF, 'OF5'), kind: 'field' }
      }
    },
    questionOrder: function (st) {
      return (st.flags || {}).udenStraks ? ['Q2', 'Q1', 'Q3'] : ['Q1', 'Q2', 'Q3'];
    }
  });
  addSection('sales.orders', s2Orders);

  // ---- Rykkere & inkasso (systemdata/settingsSection.php?s=sales.reminders)
  // Evidence: settingsDefinitions.php:653-661, :1388-1424 (the fee and interest fields one set per form language under the
  // heading Rykker 1-3, settingsSection.php:686-693). Opret rykker on Åbne poster makes the reminders (debitor help drap
  // OP2); the reminder takes the fee item's sales price and the interest at the rate per started month from the due date
  // (debitor/ny_rykker.php:69-76, :224-274); the fee and the interest are posted without VAT on the item group's sales
  // account, and a stock item stops the posting (includes/rapportfunc.php:564, :605-631); Inkasso on the reminder prints
  // the letter to the lawyer chosen here (debitor/rykker.php:161-167). The deadlines and the reminder manager are read by
  // the automatic check only (ny_rykker.php:89-158, debitor/rykkertjek.php), which the login does not run in this branch
  // (index/login.php:980), so they get no task.
  const S2_RY = 'srykker';
  const s2Reminders = s2Page({
    group: S2_RY,
    view: 'form',
    states: ['formular'],
    flags: [],
    detect: s2FormDetect(null),
    about: function () { return 'help.srykker.about.formular'; },
    tasks: {
      RY1: task(S2_RY, 'RY1', 3, { target: s2Row('sales.reminders.fee_item_1.0'), label: showKey(S2_RY, 'RY1'), kind: 'field' }),
      RY2: task(S2_RY, 'RY2', 3, { target: s2Row('sales.reminders.interest_rate_1.0'), label: showKey(S2_RY, 'RY2'), kind: 'field' }),
      RY3: task(S2_RY, 'RY3', 3, { target: s2Row('sales.reminders.collection_lawyer'), label: showKey(S2_RY, 'RY3'), kind: 'field' }),
      RY4: task(S2_RY, 'RY4', 3)
    },
    order: function () { return ['RY1', 'RY2', 'RY4', 'RY3']; },
    fields: ['fee_item', 'interest_item', 'interest_rate', 'collection_lawyer'],
    questions: {
      Q1: {
        q: 'help.srykker.q.q1.q',
        words: s2Words(['automatisk', 'af sig selv', 'sendes', 'send rykker', 'opret rykker', 'rykkere', 'påmindelse'],
          ['automatic', 'by itself', 'sent', 'send reminder', 'create reminder', 'reminders', 'notification'],
          ['automatisk', 'av seg selv', 'sendes', 'send purring', 'opprett purring', 'purringer', 'påminnelse']),
        intro: 'help.srykker.q.q1.intro',
        steps: stepKeys(S2_RY, 'RY4', 1, 3)
      },
      Q2: {
        q: 'help.srykker.q.q2.q',
        words: s2Words(['moms', 'gebyr', 'rykkergebyr', 'rente', 'momsfri', 'uden moms'],
          ['vat', 'fee', 'reminder fee', 'interest', 'vat free', 'without vat'],
          ['mva', 'gebyr', 'purregebyr', 'rente', 'mva-fri', 'uten mva']),
        intro: 'help.srykker.q.q2.intro',
        steps: ['help.srykker.q.q2.step1']
      },
      Q3: {
        q: 'help.srykker.q.q3.q',
        words: s2Words(['kan ikke bogføres', 'bogfør rykker', 'lagerført', 'fejl', 'gebyrvare'],
          ['cannot be posted', 'post reminder', 'stock item', 'error', 'fee item'],
          ['kan ikke bokføres', 'bokfør purring', 'lagerført', 'feil', 'gebyrvare']),
        intro: 'help.srykker.q.q3.intro',
        steps: ['help.srykker.q.q3.step1'],
        show: { target: s2Row('sales.reminders.fee_item_1.0'), label: showKey(S2_RY, 'RY1'), kind: 'field' }
      }
    },
    questionOrder: function () { return ['Q1', 'Q2', 'Q3']; }
  });
  addSection('sales.reminders', s2Reminders);

  // ---- Rabatter & prisgrupper (systemdata/settingsSection.php?s=sales.discounts)
  // Evidence: settingsDefinitions.php:537-597 (tables prices VPG, campaigns VTG, quantity VRG, debtor_groups DRG per year,
  // item_groups DVRG, old_discounts only with rows; the matrix field :1057). Anvend på varer (group_apply) shows only for a
  // group with items (rowHooks.php:703-704) and writes the group's values on every item in it, empty fields left out
  // (rowHooks.php:895-958, flash 6873). The item card links the groups under Grupper: Prisgruppe, Tilbudsgruppe,
  // Rabatgruppe and Debitorrabatgrp. (lager/productCardIncludes/showGroups.php:28-77) and takes the price group's prices
  // when it is saved (lager/varekort.php:795-805); Rabatgruppe on the debtor card (debitor/debitorkort.php:1886-1897). The
  // matrix's rows are the customer discount groups, else the debtor groups, its columns the item discount groups, else the
  // item groups (6881-6883, components.php:883-905). Overfør moves an old discount into the matrix (rowHooks.php:727-730).
  const S2_RB = 'srabat';
  function s2TableAdd(table) {
    return '.st-rows[data-table="' + table + '"] .st-rfoot button[data-add]';
  }
  const s2Discounts = s2Page({
    group: S2_RB,
    view: 'rows',
    states: ['tabel'],
    flags: ['gamle'],
    detect: function (ctx, b) {
      if (!b.form || !s2Q(b.doc, '.st-rows[data-table="prices"]')) return { state: 'unknown', flags: {}, data: {} };
      return {
        state: 'tabel',
        flags: { gamle: !!s2Q(b.doc, '.st-rows[data-table="old_discounts"] button[data-row-action="discount_transfer"]') },
        data: {}
      };
    },
    about: function (st) {
      return (st.flags || {}).gamle ? ['help.srabat.about.tabel', 'help.srabat.about.gamle'] : ['help.srabat.about.tabel', 'help.srabat.about.matrix'];
    },
    tasks: {
      RB1: task(S2_RB, 'RB1', 3, { target: 'table[data-matrix]', label: showKey(S2_RB, 'RB1'), kind: 'field' }),
      RB2: task(S2_RB, 'RB2', 3, { target: s2TableAdd('quantity'), label: showKey(S2_RB, 'RB2'), kind: 'button' }),
      RB3: task(S2_RB, 'RB3', 3, { target: s2TableAdd('campaigns'), label: showKey(S2_RB, 'RB3'), kind: 'button' }),
      RB4: task(S2_RB, 'RB4', 3, { target: s2TableAdd('prices'), label: showKey(S2_RB, 'RB4'), kind: 'button' }),
      RB5: task(S2_RB, 'RB5', 3, { target: s2TableAdd('debtor_groups'), label: showKey(S2_RB, 'RB5'), kind: 'button' }),
      RB6: task(S2_RB, 'RB6', 2, { target: '.st-rows[data-table="old_discounts"] button[data-row-action="discount_transfer"]', label: showKey(S2_RB, 'RB6'), kind: 'button' })
    },
    order: function (st) {
      return [(st.flags || {}).gamle ? 'RB6' : '', 'RB1', 'RB2', 'RB3', 'RB4', 'RB5'];
    },
    fields: ['nr', 'beskrivelse', 'kostpris', 'salgspris', 'vejl_pris', 'btob_pris', 'kampagnepris', 'fra', 'til', 'type', 'stk_rabat', 'v_antal',
      'navn', 'matrix', 'aar'],
    cols: {
      kodenr: 'nr', beskrivelse: 'beskrivelse', 'prices.box1': 'kostpris', 'prices.box2': 'salgspris', 'prices.box3': 'vejl_pris',
      'prices.box4': 'btob_pris', 'campaigns.box1': 'kostpris', 'campaigns.box2': 'kampagnepris', 'campaigns.box3': 'fra',
      'campaigns.box4': 'til', 'quantity.box1': 'type', 'quantity.box2': 'stk_rabat', 'quantity.box3': 'v_antal',
      'debtor_groups.box1': 'navn', 'item_groups.box1': 'navn'
    },
    questions: {
      Q1: {
        q: 'help.srabat.q.q1.q',
        words: s2Words(['priser ændrede sig ikke', 'pris', 'priser', 'anvend', 'varer', 'opdater priser', 'gem'],
          ['prices did not change', 'price', 'prices', 'apply', 'items', 'update prices', 'save'],
          ['prisene endret seg ikke', 'pris', 'priser', 'bruk på varer', 'varer', 'oppdater priser', 'lagre']),
        intro: 'help.srabat.q.q1.intro',
        steps: ['help.srabat.q.q1.step1', 'help.srabat.q.q1.step2'],
        show: { target: 'button[data-row-action="group_apply"]', label: 'help.srabat.q.q1.show', kind: 'button' }
      },
      Q2: {
        q: 'help.srabat.q.q2.q',
        words: s2Words(['matrix', 'rabatmatrix', 'ny gruppe', 'kan ikke se', 'mangler', 'kolonne', 'række'],
          ['matrix', 'discount matrix', 'new group', 'cannot see', 'missing', 'column', 'row'],
          ['matrise', 'rabattmatrise', 'ny gruppe', 'kan ikke se', 'mangler', 'kolonne', 'rad']),
        intro: 'help.srabat.q.q2.intro',
        steps: ['help.srabat.q.q2.step1'],
        show: { target: 'table[data-matrix]', label: showKey(S2_RB, 'RB1'), kind: 'field' }
      }
    },
    questionOrder: function () { return ['Q1', 'Q2']; }
  });
  addSection('sales.discounts', s2Discounts);

  // ---- Mit salg (systemdata/settingsSection.php?s=sales.mysale)
  // Evidence: settingsDefinitions.php:598-605, :916-927 (the three dependants hidden until Aktivér 'Mit salg' is on). With it
  // on, the customer list gets Kommission (debitor/debLstIncludes/topLine.php:68-77), where Inviter sends the link (debitor
  // help deb DB11).
  const S2_MS = 'smitsalg';
  const s2MySale = s2Page({
    group: S2_MS,
    view: 'form',
    states: ['formular'],
    flags: ['aktiv'],
    detect: s2FormDetect(function (doc) {
      return { aktiv: s2On(doc, 'sales.mysale.enabled') };
    }),
    about: function () { return 'help.smitsalg.about.formular'; },
    tasks: {
      MS1: task(S2_MS, 'MS1', 3, { target: s2Row('sales.mysale.enabled'), label: showKey(S2_MS, 'MS1'), kind: 'field' }),
      MS2: task(S2_MS, 'MS2', 3, { target: s2Row('sales.mysale.disable_customer_labels'), label: showKey(S2_MS, 'MS2'), kind: 'field' }),
      MS3: task(S2_MS, 'MS3', 1, { target: s2Row('sales.mysale.show_times'), label: showKey(S2_MS, 'MS3'), kind: 'field' })
    },
    order: function (st) {
      return (st.flags || {}).aktiv ? ['MS2', 'MS1', 'MS3'] : ['MS1', 'MS2', 'MS3'];
    },
    fields: ['label_max_length'],
    // The invitation mails each ticked customer the link to Mit salg, to the e-mail of the card (debitor/debitor_kommission.php:
    // 78-129); the dependants show only with the switch on (settingsDefinitions.php:916-927).
    questions: {
      Q1: {
        q: 'help.smitsalg.q.q1.q',
        words: s2Words(['kan ikke se', 'link', 'invitation', 'inviter', 'kunde', 'mit salg'],
          ['cannot see', 'link', 'invitation', 'invite', 'customer', 'my sales'],
          ['kan ikke se', 'lenke', 'invitasjon', 'inviter', 'kunde', 'mitt salg']),
        intro: 'help.smitsalg.q.q1.intro',
        steps: ['help.smitsalg.q.q1.step1', 'help.smitsalg.q.q1.step2'],
        show: { target: s2Row('sales.mysale.enabled'), label: showKey(S2_MS, 'MS1'), kind: 'field' }
      }
    },
    questionOrder: function () { return ['Q1']; }
  });
  addSection('sales.mysale', s2MySale);

  // ---- Købsordrer (systemdata/settingsSection.php?s=purchase.orders)
  // Evidence: settingsDefinitions.php:121-127, :839-843; the purchase posting reads the second part of DIV/3 box5, a lone
  // value for both (kreditor/bogfor.php:304-306); Åbn Lagerstyring links to items.stock (a link row, components.php:948-962).
  const S2_KO = 'skoeb';
  const s2PurchaseOrders = s2Page({
    group: S2_KO,
    view: 'form',
    states: ['formular'],
    flags: ['udenStraks'],
    detect: s2FormDetect(function (doc) {
      return { udenStraks: !!s2Q(doc, s2Row('purchase.orders.post_immediately')) && !s2On(doc, 'purchase.orders.post_immediately') };
    }),
    about: function (st) {
      return (st.flags || {}).udenStraks ? ['help.skoeb.about.formular', 'help.skoeb.about.uden_straks'] : 'help.skoeb.about.formular';
    },
    tasks: {
      KB1: task(S2_KO, 'KB1', 3, { target: s2Row('purchase.orders.post_immediately'), label: showKey(S2_KO, 'KB1'), kind: 'field' }),
      KB2: task(S2_KO, 'KB2', 2, { target: s2Row('purchase.orders.confirm_stock_change') + ' a.st-btn', label: showKey(S2_KO, 'KB2'), kind: 'button' })
    },
    order: function () { return ['KB1', 'KB2']; },
    // Q1: the posting of the bill runs only with the second part of DIV/3 box5 (kreditor/bogfor.php:304-307). Q2: the
    // supplier's open item goes to the creditor group's control account box2 (:364-366), its VAT code box1 decides the
    // VAT (:464-476, kreditor/ordre.php:514-530), and every item line needs Varekøb (box3) on its item group or the
    // posting is refused with Der er ikke opsat kontonummer for varekøb på varegruppen (:211-221).
    questions: {
      Q1: {
        q: 'help.skoeb.q.q1.q',
        words: s2Words(['ikke bogført', 'kontiene', 'købsordre', 'regning', 'moms', 'mangler'],
          ['not posted', 'accounts', 'purchase order', 'bill', 'vat', 'missing'],
          ['ikke bokført', 'kontoene', 'innkjøpsordre', 'regning', 'mva', 'mangler']),
        intro: 'help.skoeb.q.q1.intro',
        steps: ['help.skoeb.q.q1.step1', 'help.skoeb.q.q1.step2'],
        show: { target: s2Row('purchase.orders.post_immediately'), label: showKey(S2_KO, 'KB1'), kind: 'field' }
      },
      Q2: {
        q: 'help.skoeb.q.q2.q',
        words: s2Words(['konti', 'samlekonto', 'varekøb', 'moms', 'kreditorgruppe', 'varegruppe', 'kontonummer for varekøb'],
          ['accounts', 'control account', 'purchases', 'vat', 'creditor group', 'item group', 'account for purchases'],
          ['kontoer', 'samlekonto', 'varekjøp', 'mva', 'kreditorgruppe', 'varegruppe', 'kontonummer for varekjøp']),
        intro: 'help.skoeb.q.q2.intro',
        steps: ['help.skoeb.q.q2.step1', 'help.skoeb.q.q2.step2']
      }
    },
    questionOrder: function (st) {
      return (st.flags || {}).udenStraks ? ['Q1', 'Q2'] : ['Q2', 'Q1'];
    }
  });
  addSection('purchase.orders', s2PurchaseOrders);

  // ---- Leverandørprislister (systemdata/settingsSection.php?s=purchase.pricelists)
  // Evidence: settingsDefinitions.php:519-527, :1326-1349 (one drawer per list pl_<id>, fields and actions per list,
  // :1765-1771); the actions (includes/settings/actions.php:191-218): Tilføj prisliste makes Ny prisliste and opens its
  // drawer, Brug denne prisliste makes it the one list in use (I brug, 6233), Test prisfilen fetches the file and reports
  // its lines and columns (actions.php:83-103, flash 6246/6247), Slet prislisten deletes it. The order's item lookup
  // fetches from the list in use (debitor/ordre.php:3159-3162, 6236).
  const S2_PL = 'sprisliste';
  /** An action button of the open drawer, else Indstil of the first list (the first step). */
  function s2ListAction(run) {
    return function (ctx) {
      const d = s2OpenDrawer(ctx.doc);
      if (d) return s2Q(d, 'button[data-run^="purchase.pricelists.' + run + '."]');
      return s2Q(ctx.doc, '.st-irow button[data-open]');
    };
  }
  const s2Pricelists = s2Page({
    group: S2_PL,
    view: 'list',
    states: ['liste', 'tom'],
    flags: ['aaben', 'iBrug'],
    detect: function (ctx, b) {
      if (!b.list) return { state: 'unknown', flags: {}, data: {} };
      const rows = s2Qa(b.doc, '.st-irow[data-item]');
      return {
        state: rows.length ? 'liste' : 'tom',
        flags: { aaben: !!s2OpenDrawer(b.doc), iBrug: !!s2Q(b.doc, '.st-irow.st-irow-ok') },
        data: { lists: rows.length }
      };
    },
    about: function (st) {
      if (st.state === 'tom') return ['help.sprisliste.about.liste', 'help.sprisliste.about.tom'];
      return (st.flags || {}).iBrug ? 'help.sprisliste.about.liste' : ['help.sprisliste.about.liste', 'help.sprisliste.about.ingen_i_brug'];
    },
    tasks: {
      PL1: task(S2_PL, 'PL1', 3, { target: '.st-phead form button[type="submit"]', label: showKey(S2_PL, 'PL1'), kind: 'button' }),
      PL2: task(S2_PL, 'PL2', 3, { target: s2ListAction('test'), label: showKey(S2_PL, 'PL2'), kind: 'button' }),
      PL3: task(S2_PL, 'PL3', 3, { target: s2ListAction('use'), label: showKey(S2_PL, 'PL3'), kind: 'button' }),
      PL4: task(S2_PL, 'PL4', 2, { target: s2ListAction('delete'), label: showKey(S2_PL, 'PL4'), kind: 'button' })
    },
    order: function (st) {
      const f = st.flags || {};
      if (st.state === 'tom') return ['PL1'];
      if (f.aaben) return ['PL2', 'PL3', 'PL1', 'PL4'];
      return f.iBrug ? ['PL2', 'PL1', 'PL3', 'PL4'] : ['PL3', 'PL2', 'PL1', 'PL4'];
    },
    fields: ['description', 'url', 'delimiter', 'encoding', 'item_group', 'supplier'],
    questions: {
      Q1: {
        q: 'help.sprisliste.q.q1.q',
        words: s2Words(['kan ikke hentes', 'prisfil', 'fejl', 'url', 'adresse', 'test', 'kolonner'],
          ['cannot be fetched', 'price file', 'error', 'url', 'address', 'test', 'columns'],
          ['kan ikke hentes', 'prisfil', 'feil', 'url', 'adresse', 'test', 'kolonner']),
        intro: 'help.sprisliste.q.q1.intro',
        steps: ['help.sprisliste.q.q1.step1', 'help.sprisliste.q.q1.step2'],
        show: { target: s2ListAction('test'), label: showKey(S2_PL, 'PL2'), kind: 'button' }
      }
    },
    questionOrder: function (st) { return st.state === 'tom' ? [] : ['Q1']; }
  });
  addSection('purchase.pricelists', s2Pricelists);

  // ---- Provision (systemdata/settingsSection.php?s=organisation.commission)
  // Evidence: settingsDefinitions.php:645-652, :986-1001; the commission is the employee's rate of the contribution margin,
  // sales price after discount minus cost price (finans/provisionsrapport.php:92-97, :262-263), the period runs from the
  // cut-off day of the month before (:105-106, 1724); Vis provision på varekort shows Provision beside Salgspris with the
  // default rate on an item without one (lager/productCardIncludes/percentageField.php:34-44, :62-83).
  const S2_PV = 'sprovision';
  const s2Commission = s2Page({
    group: S2_PV,
    view: 'form',
    states: ['formular'],
    flags: [],
    detect: s2FormDetect(null),
    about: function () { return 'help.sprovision.about.formular'; },
    tasks: {
      PV1: task(S2_PV, 'PV1', 3, { target: s2Row('organisation.commission.basis'), label: showKey(S2_PV, 'PV1'), kind: 'field' }),
      PV2: task(S2_PV, 'PV2', 2, { target: s2Row('organisation.commission.cutoff_day'), label: showKey(S2_PV, 'PV2'), kind: 'field' }),
      PV3: task(S2_PV, 'PV3', 2, { target: s2Row('organisation.commission.show_on_item_card'), label: showKey(S2_PV, 'PV3'), kind: 'field' })
    },
    order: function () { return ['PV1', 'PV2', 'PV3']; },
    fields: ['cutoff_day'],
    // The report counts an order for the customer manager, the order's reference or the manager first and the reference
    // only on a customer without one, and invoiced or paid orders (finans/provisionsrapport.php:92-98), in the period
    // from the cut-off day of the month before (:105-106).
    questions: {
      Q1: {
        q: 'help.sprovision.q.q1.q',
        words: s2Words(['ingen provision', 'sælger', 'kundeansvarlig', 'referenceperson', 'betalt', 'provision mangler'],
          ['no commission', 'salesperson', 'customer manager', 'reference person', 'paid', 'commission missing'],
          ['ingen provisjon', 'selger', 'kundeansvarlig', 'referanseperson', 'betalt', 'provisjon mangler']),
        intro: 'help.sprovision.q.q1.intro',
        steps: ['help.sprovision.q.q1.step1', 'help.sprovision.q.q1.step2'],
        show: { target: s2Row('organisation.commission.basis'), label: showKey(S2_PV, 'PV1'), kind: 'field' }
      }
    },
    questionOrder: function () { return ['Q1']; }
  });
  addSection('organisation.commission', s2Commission);

  // ---- Projekter (systemdata/settingsSection.php?s=organisation.projects)
  // Evidence: settingsDefinitions.php:257-271 (table projects without kodenr 0, a number of letters or digits, usage orders
  // and postings, no inactive), :1187-1189 (Projektopdeling, the kodenr 0 row). With a project the cash journal shows its
  // project column (finans/kassekladde.php:1159, :1910) and refuses a number that does not exist (:4666-4672), the order
  // lines get Proj. (debitor/ordre.php:4364-4367), the reports can be limited to projects (finans/rapport_includes/
  // forside.php:250-262).
  const S2_PR = 'sprojekt';
  const s2Projects = s2Page({
    group: S2_PR,
    view: 'rows',
    states: ['tabel', 'tom'],
    flags: [],
    detect: s2RowsDetect('projects', null),
    about: function (st) {
      return st.state === 'tom' ? ['help.sprojekt.about.tabel', 'help.sprojekt.about.tom'] : 'help.sprojekt.about.tabel';
    },
    tasks: {
      PR1: task(S2_PR, 'PR1', 3, { target: s2TableAdd('projects'), label: showKey(S2_PR, 'PR1'), kind: 'button' }),
      PR2: task(S2_PR, 'PR2', 2, { target: s2Row('organisation.projects.number_split'), label: showKey(S2_PR, 'PR2'), kind: 'field' }),
      PR3: task(S2_PR, 'PR3', 2, { target: '.st-rows[data-table="projects"] tbody tr[data-row]:not([data-row="__N__"]) button[data-del]', label: showKey(S2_PR, 'PR3'), kind: 'button' })
    },
    order: function (st) {
      return st.state === 'tom' ? ['PR1', 'PR2'] : ['PR1', 'PR3', 'PR2'];
    },
    fields: ['nr', 'beskrivelse', 'number_split'],
    cols: { 'projects.kodenr': 'nr', 'projects.beskrivelse': 'beskrivelse' },
    // Q1: the cash journal refuses a project that is not a PRJ row here, Projekt %s eksisterer ikke ... Kladden er IKKE
    // gemt (finans/kassekladde.php:4666-4674). Q2: the reports get Projekt: when projects exist (finans/rapport_includes/
    // forside.php:250-262, :409-430); order lines carry Proj. (debitor/ordre.php:4364-4367).
    questions: {
      Q1: {
        q: 'help.sprojekt.q.q1.q',
        words: s2Words(['eksisterer ikke', 'projektnummer', 'kassekladde', 'ikke gemt', 'projekt'],
          ['does not exist', 'project number', 'cash journal', 'not saved', 'project'],
          ['eksisterer ikke', 'prosjektnummer', 'kassekladd', 'ikke lagret', 'prosjekt']),
        intro: 'help.sprojekt.q.q1.intro',
        steps: ['help.sprojekt.q.q1.step1', 'help.sprojekt.q.q1.step2']
      },
      Q2: {
        q: 'help.sprojekt.q.q2.q',
        words: s2Words(['rapport', 'projektregnskab', 'tal', 'resultat', 'projekt'],
          ['report', 'project accounts', 'figures', 'result', 'project'],
          ['rapport', 'prosjektregnskap', 'tall', 'resultat', 'prosjekt']),
        intro: 'help.sprojekt.q.q2.intro',
        steps: ['help.sprojekt.q.q2.step1', 'help.sprojekt.q.q2.step2']
      }
    },
    // Without projects the newcomer asks what they give; with projects the refused number in the journal comes first
    questionOrder: function (st) { return st.state === 'tom' ? ['Q2', 'Q1'] : ['Q1', 'Q2']; }
  });
  addSection('organisation.projects', s2Projects);

  // ---- Kommissionsvarer (systemdata/settingsSection.php?s=items.consignment), for companies with the POS module only
  // Evidence: settingsDefinitions.php:606-613 ('module' => 'pos'; without it settings.php?err=module, settingsSection.php:
  // 109-113), :930-962 (everything but the switch hidden until it is on, the action Konverter eksisterende varer? with
  // its warning 6006). The cash count's settlement moves each customer's share from the settlement account to the
  // customer's account (debitor/pos_ordre_includes/settleCommission/moveToCustomerAccount.php:74-90, :187-237).
  const S2_KM = 'skommission';
  const s2Consignment = s2Page({
    group: S2_KM,
    view: 'form',
    states: ['formular'],
    flags: ['aktiv'],
    detect: s2FormDetect(function (doc) {
      return { aktiv: s2On(doc, 'items.consignment.enabled') };
    }),
    about: function () { return 'help.skommission.about.formular'; },
    tasks: {
      KM1: task(S2_KM, 'KM1', 3, { target: s2Row('items.consignment.enabled'), label: showKey(S2_KM, 'KM1'), kind: 'field' }),
      KM2: task(S2_KM, 'KM2', 3, { target: s2Row('items.consignment.income_account_new'), label: showKey(S2_KM, 'KM2'), kind: 'field' }),
      KM3: task(S2_KM, 'KM3', 2, { target: s2Row('items.consignment.settlement_from'), label: showKey(S2_KM, 'KM3'), kind: 'field' }),
      KM4: task(S2_KM, 'KM4', 3, { target: 'button[data-run="items.consignment.convert_existing"]', label: showKey(S2_KM, 'KM4'), kind: 'button' })
    },
    order: function (st) {
      return (st.flags || {}).aktiv ? ['KM2', 'KM3', 'KM1', 'KM4'] : ['KM1', 'KM2', 'KM3'];
    },
    fields: ['settlement_from'],
    // The cash count's settlement moves each customer's share from the settlement account to the customer's account, with
    // the accounts of Konti and from Startdato (debitor/pos_ordre_includes/settleCommission/moveToCustomerAccount.php:74-90,
    // :187-237).
    questions: {
      Q1: {
        q: 'help.skommission.q.q1.q',
        words: s2Words(['afregne', 'afregning', 'kommissionskunde', 'andel', 'kasseoptælling', 'udbetale'],
          ['settle', 'settlement', 'commission customer', 'share', 'cash count', 'pay out'],
          ['avregne', 'avregning', 'kommisjonskunde', 'andel', 'kasseopptelling', 'utbetale']),
        intro: 'help.skommission.q.q1.intro',
        steps: ['help.skommission.q.q1.step1'],
        show: { target: s2Row('items.consignment.settlement_from'), label: showKey(S2_KM, 'KM3'), kind: 'field' }
      }
    },
    questionOrder: function () { return ['Q1']; }
  });
  addSection('items.consignment', s2Consignment);

  // ==== S3: Varer & lager, Integrationer and Kasse (spec §24.3), the pages they lead to (POS-menuer, Importér varianter,
  // Vis QR-kode til app-login). Every section is a page of systemdata/settingsSection.php, registered with addSection().
  // The three section views (settingsSection.php:640-780, includes/settings/rowsView.php, includes/settings/listView.php):
  // form #st-form with the save bar #st-savebar (Fortryd #st-undo, Gem ændringer #st-save, only with write access) and fields
  // .st-field[data-key] whose control has the id f-<key with - for .> (includes/settings/components.php:769-772, :796);
  // a rows section: the same form, one card .st-rows[data-table] per table with saved rows tr[data-row], the add button
  // [data-add] in .st-rfoot, the trash [data-del], the empty card .st-rempty and the cells r[<table>][<row>][<column>]
  // (rowsView.php:300-420); a list section: rows .st-irow[data-item] with a button [data-open] that opens a drawer
  // aside.st-drawer[data-item] with its own form .st-dform and Gem ændringer [data-save] (listView.php:318-450). Read-only
  // access shows .st-flash-info (5755) and no save bar; a refused save shows .st-flash-err (5731) and the error list
  // (.st-errorlist in a form or a drawer, .st-errors in a rows section).

  function s3q(root, sel) {
    try {
      const el = root ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function s3all(root, sel) {
    const out = [];
    try {
      const list = root ? root.querySelectorAll(sel) : [];
      for (let i = 0; i < list.length; i++) if (list[i] && list[i].nodeType === 1) out.push(list[i]);
    } catch (e) {
      return [];
    }
    return out;
  }

  function s3param(ctx, name) {
    try {
      return ctx && ctx.url && ctx.url.searchParams ? String(ctx.url.searchParams.get(name) || '') : '';
    } catch (e) {
      return '';
    }
  }

  function s3state(st) {
    return st && st.state ? st.state : 'unknown';
  }

  function s3flag(st, name) {
    return !!(st && st.flags && st.flags[name]);
  }

  /** Read-only access: the info note 5755 (settingsSection.php:564, rowsView.php:111-116, listView.php:302-307). */
  function s3readOnly(doc) {
    return !!s3q(doc, '.st-flash-info');
  }

  /**
   * A refused save: the error list of a form or a drawer, or of a rows section (settingsSection.php:654-660, listView.php:389-395,
   * rowsView.php:131-150). Not every error flash: a refused delete or a failed action has no list to correct.
   */
  function s3refused(doc) {
    return !!(s3q(doc, '.st-errorlist') || s3q(doc, '.st-errors'));
  }

  /** The saved rows of a table in a rows section (rowsView.php:322-354); new rows carry .st-new. */
  function s3rows(doc, table) {
    return s3all(doc, '.st-rows[data-table="' + table + '"] tr[data-row]:not(.st-new)');
  }

  /** The empty card of a table without rows (rowsView.php:390-396). */
  function s3empty(doc, table) {
    return !!s3q(doc, '.st-rows[data-table="' + table + '"] .st-rempty');
  }

  /** The first saved row's cell of a column, as a Vis mig target. */
  function s3cell(doc, table, col) {
    // a checkbox cell prints a hidden twin with the same name first (rowsView.php); Vis mig rings the visible box
    return s3q(doc, '.st-rows[data-table="' + table + '"] tr[data-row]:not(.st-new) [name$="[' + col + ']"]:not([type="hidden"])');
  }

  /** The button of a list section's head form that runs an add action (listView.php:287-295). */
  function s3addButton(doc, key) {
    const inp = s3q(doc, 'input[name="key"][value="' + key + '"]');
    let form = null;
    try {
      form = inp && typeof inp.closest === 'function' ? inp.closest('form') : null;
    } catch (e) {
      form = null;
    }
    return form ? s3q(form, 'button[type="submit"]') : null;
  }

  /**
   * A field key from a section field or a table cell: map is { '<setting key>' | '<table>.<column>' | '#<id>': fieldKey }.
   * A field's control is named f[<key>] and has the id f-<key with - for .> (components.php:770-772, :796; per-item keys end
   * in .<n>); a cell is named r[<table>][<row>][<column>] (rowsView.php:280-283).
   */
  function s3fieldKey(map) {
    return function (el) {
      if (!el || el.nodeType !== 1) return null;
      const name = attr(el, 'name');
      const id = attr(el, 'id');
      let m = /^r\[([a-z_]+)\]\[[a-z0-9_]+\]\[([a-z0-9_]+)\]$/.exec(name);
      if (m) return map[m[1] + '.' + m[2]] || null;
      if (id && map['#' + id]) return map['#' + id];
      let s = '';
      if ((m = /^f\[([a-z0-9_.]+)\]$/.exec(name))) s = m[1].replace(/\.\d+$/, '');
      else if (/^f-[a-z0-9_-]+$/.test(id)) s = id.slice(2).replace(/-\d+$/, '');
      if (!s) return null;
      for (const k in map) {
        if (Object.prototype.hasOwnProperty.call(map, k) && k.charAt(0) !== '#' && (k === s || k.replace(/\./g, '-') === s)) return map[k];
      }
      return null;
    };
  }

  // ---- Varegrupper (systemdata/settingsSection.php?s=items.item_groups)
  // Evidence: settingsDefinitions.php:430-458 (kind rows, table groups = grupper VG per fiscal year; columns Nr., Beskrivelse,
  // Varekøb/Varesalg (box3/box4, required; Lagertilgang/Lagertræk box1/box2 are kept but no longer shown, 20261114), Varer/ydelser (box5,
  // kept on every year's copy, rows.php:656-659), Omvendt betalingspligt, Momsfri, Lagerført, Batchkontrol (box9 requires box8,
  // 6498), the EU and outside-EU accounts; the link to Salg » Rabatter & prisgrupper, :1183). rowsView.php:92-109 (the year select
  // #st-year), :131-150 (the error list .st-errors), :340 (the trash [data-del]), :390-396 (empty: Opret dansk standardsæt [data-seed]
  // and Tilføj selv), :405-422 (Tilføj varegruppe [data-add] in .st-rfoot; Kopiér fra <år> button[form="st-copy-groups"] for an empty
  // year after an earlier one). rows.php:73-104 (the standard set from importfiler/grupper.txt, the standard chart's numbers),
  // :248 (in use: the group's items), :396-410 (an account must be in the year's chart, 5734), :758 (copy inserts at once).
  // settingsRows.js:107-121 (Ctrl+S, Enter, Esc), :195-214 (delete asks, refuses a group in use). settingsSection.php:354-379
  // (delete and copy are posted at once). Posting: includes/ordrefunc.php:1938-1955 (Varesalg, or the EU / outside-EU sales account
  // by the customer's VAT number; the rate from that account's VAT code), :1991 (the account is stored on the posted line),
  // :4560-4570 (Momsfri: no VAT); actions.php:576-584 and ordrefunc.php:5801 (cost price and minimum stock only for Lagerført);
  // components.php:673-681 (batch control keeps Hurtigfakturering off).

  const S3_VG_TASKS = {
    VG1: task('svaregrp', 'VG1', 4, { target: '.st-rows[data-table="groups"] .st-rfoot button[data-add]', label: showKey('svaregrp', 'VG1'), kind: 'button' }),
    VG2: task('svaregrp', 'VG2', 3, { target: '.st-rows[data-table="groups"] button[data-seed]', label: showKey('svaregrp', 'VG2'), kind: 'button' }),
    VG3: task('svaregrp', 'VG3', 3, { target: 'button[form="st-copy-groups"]', label: showKey('svaregrp', 'VG3'), kind: 'button' }),
    VG4: task('svaregrp', 'VG4', 3, {
      target: function (ctx) { return s3cell(ctx.doc, 'groups', 'box4'); }, label: showKey('svaregrp', 'VG4'), kind: 'field'
    }),
    VG5: task('svaregrp', 'VG5', 4, {
      target: function (ctx) { return s3cell(ctx.doc, 'groups', 'box8'); }, label: showKey('svaregrp', 'VG5'), kind: 'field'
    }),
    VG6: task('svaregrp', 'VG6', 4, {
      target: function (ctx) { return s3cell(ctx.doc, 'groups', 'box7'); }, label: showKey('svaregrp', 'VG6'), kind: 'field'
    }),
    VG7: task('svaregrp', 'VG7', 4, {
      target: function (ctx) { return s3cell(ctx.doc, 'groups', 'box12'); }, label: showKey('svaregrp', 'VG7'), kind: 'field'
    }),
    VG8: task('svaregrp', 'VG8', 3, {
      target: '.st-rows[data-table="groups"] tr[data-row]:not(.st-new) button[data-del]', label: showKey('svaregrp', 'VG8'), kind: 'button'
    }),
    VG9: task('svaregrp', 'VG9', 2, { target: '#st-year', label: showKey('svaregrp', 'VG9'), kind: 'field' }),
    VG10: task('svaregrp', 'VG10', 4, { target: '.st-errors', label: showKey('svaregrp', 'VG10'), kind: 'status' })
  };

  const s3ItemGroups = {
    group: 'svaregrp',
    states: ['liste', 'tom', 'kunLaes'],
    flags: ['fejl', 'standard', 'kopiAar'],

    name: function () {
      return 'help.svaregrp.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = {
        fejl: s3refused(doc),
        standard: !!s3q(doc, '.st-rows[data-table="groups"] button[data-seed]'),
        kopiAar: !!s3q(doc, 'button[form="st-copy-groups"]')
      };
      const n = s3rows(doc, 'groups').length;
      let state = 'unknown';
      if (s3readOnly(doc)) state = 'kunLaes';
      else if (n > 0) state = 'liste';
      else if (s3empty(doc, 'groups') || s3q(doc, '.st-rows[data-table="groups"]')) state = 'tom';
      return { state: state, flags: flags, data: { grupper: n } };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'kunLaes') return ['help.svaregrp.about.kunlaes'];
      if (s3flag(st, 'fejl') && (s === 'liste' || s === 'tom')) return ['help.svaregrp.about.fejl'];
      if (s === 'tom') return ['help.svaregrp.about.tom'];
      if (s === 'liste') return ['help.svaregrp.about.liste'];
      return [];
    },

    tasks: S3_VG_TASKS,

    order: function (ctx, st) {
      const s = s3state(st);
      let out = [];
      if (s === 'liste') out = ['VG1', 'VG4', 'VG6', 'VG5', 'VG7', 'VG8', 'VG9'];
      else if (s === 'tom') {
        if (s3flag(st, 'kopiAar')) out = ['VG3', 'VG1', 'VG9'];
        else if (s3flag(st, 'standard')) out = ['VG2', 'VG1', 'VG9'];
        else out = ['VG1', 'VG9'];
      } else if (s === 'kunLaes') out = ['VG9'];
      if (s3flag(st, 'fejl') && (s === 'liste' || s === 'tom')) out = ['VG10'].concat(out);
      return out;
    },

    problem: function (ctx, st) {
      const s = s3state(st);
      if ((s === 'liste' || s === 'tom') && s3flag(st, 'fejl')) return 'VG10';
      if (s === 'tom') return s3flag(st, 'kopiAar') ? 'VG3' : (s3flag(st, 'standard') ? 'VG2' : 'VG1');
      return '';
    },

    fields: {
      nr: fieldKeys('svaregrp', 'nr'),
      beskrivelse: fieldKeys('svaregrp', 'beskrivelse'),
      varekoeb: fieldKeys('svaregrp', 'varekoeb'),
      varesalg: fieldKeys('svaregrp', 'varesalg'),
      lagerfoert: fieldKeys('svaregrp', 'lagerfoert'),
      batch: fieldKeys('svaregrp', 'batch'),
      momsfri: fieldKeys('svaregrp', 'momsfri'),
      eu_salg: fieldKeys('svaregrp', 'eu_salg'),
      regnskabsaar: fieldKeys('svaregrp', 'regnskabsaar')
    },

    fieldKey: s3fieldKey({
      'groups.kodenr': 'nr', 'groups.beskrivelse': 'beskrivelse',
      'groups.box3': 'varekoeb', 'groups.box4': 'varesalg', 'groups.box7': 'momsfri', 'groups.box8': 'lagerfoert',
      'groups.box9': 'batch', 'groups.box12': 'eu_salg', '#st-year': 'regnskabsaar'
    }),

    shortcuts: [
      { keys: 'Ctrl+S', text: 'help.svaregrp.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.svaregrp.key.02', group: 'keys' },
      { keys: 'Esc', text: 'help.svaregrp.key.03', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.svaregrp.q.q1.q',
        words: {
          da: ['slette', 'slet', 'varegruppe', 'kan ikke', 'i brug', 'fjerne'],
          en: ['delete', 'item group', 'product group', 'cannot', 'in use', 'remove'],
          no: ['slette', 'varegruppe', 'kan ikke', 'i bruk', 'fjerne']
        },
        intro: 'help.svaregrp.task.vg8.why',
        steps: stepKeys('svaregrp', 'VG8', 2, 3)
      },
      Q2: {
        q: 'help.svaregrp.q.q2.q',
        words: {
          da: ['moms', 'momsfri', 'momssats', 'uden moms', 'omvendt betalingspligt', 'momskode'],
          en: ['vat', 'vat-free', 'vat rate', 'without vat', 'reverse charge', 'vat code'],
          no: ['mva', 'mva-fri', 'mva-sats', 'uten mva', 'omvendt betalingsplikt', 'mva-kode']
        },
        intro: 'help.svaregrp.task.vg6.why',
        steps: stepKeys('svaregrp', 'VG6', 1, 3)
      },
      Q3: {
        q: 'help.svaregrp.q.q3.q',
        words: {
          da: ['prisgrupper', 'prisgruppe', 'rabatgrupper', 'tilbudsgrupper', 'kampagnepris', 'rabat'],
          en: ['price groups', 'price group', 'discount groups', 'offer groups', 'campaign price', 'discount'],
          no: ['prisgrupper', 'prisgruppe', 'rabattgrupper', 'tilbudsgrupper', 'kampanjepris', 'rabatt']
        },
        steps: ['help.svaregrp.q.q3.step1']
      },
      Q4: {
        q: 'help.svaregrp.q.q4.q',
        words: {
          da: ['nummer', 'gruppenummer', 'ændre nummer', 'låst', 'grå', 'nr'],
          en: ['number', 'group number', 'change number', 'locked', 'grey'],
          no: ['nummer', 'gruppenummer', 'endre nummer', 'låst', 'grå', 'nr']
        },
        intro: 'help.svaregrp.q.q4.intro',
        steps: ['help.svaregrp.q.q4.step1']
      }
    },

    questionOrder: function (ctx, st) {
      const s = s3state(st);
      if (s === 'liste') return ['Q2', 'Q1', 'Q3'];
      // A newcomer setting up the first groups asks where the VAT comes from; deleting and renumbering come later
      if (s === 'tom') return ['Q2', 'Q3'];
      if (s === 'kunLaes') return ['Q2', 'Q3'];
      return [];
    },

    signal: function () {
      return false;
    }
  };

  addSection('items.item_groups', s3ItemGroups);

  // ---- Lagerstyring & kostpris (systemdata/settingsSection.php?s=items.stock)
  // Evidence: settingsDefinitions.php:128-135 (the form section; sub-sections Lagerstyring, Kostpris, Lagerstatus-mail, Varekort),
  // :846-886 (the fields: FIFO, negative stock, low-stock warning, discontinue when negative, standard minimum stock; the cost method
  // select (Opdater ikke kostpris, Gennemsnitspris, Genanskaffelsespris) and the action Opdater kostpriser, shown only for the two
  // methods; the stock status mail; the item card switches; batch and expiry dates). settingsSection.php:676-728 (the action under
  // Handlinger with write access only, its row div.st-action[data-key][hidden]), components.php:979-995 (button[data-run] with the
  // confirmation), settingsSection.js:120-122 and :207 (the dialog; data-blank opens a new tab), actions.php:155-156 (it runs
  // includes/opdat_kostpriser.php with the stored method), :576-584 (the items it touches: Lagerført groups, not closed, not bills
  // of materials). includes/opdat_kostpriser.php:62-101 (Gennemsnitspris: the average of the latest purchases that cover the stock),
  // :102-127 (Genanskaffelsespris: the last purchase price). includes/ordrefunc.php:1993-2024 (FIFO: a sale's cost from the oldest
  // purchases with rest), :5790-5815 (the low-stock warning; the standard minimum for items without their own), debitor/ordre.php:
  // 1947-1960 (without negative stock a Lagerført item is delivered at most as many as are in stock). components.php:673-681 (batch
  // control keeps Hurtigfakturering off, 7290). settingsSection.js:233 (Ctrl+S).

  const S3_LS_TASKS = {
    LS1: task('slager', 'LS1', 4, { target: '#f-items-stock-low_stock_warning', label: showKey('slager', 'LS1'), kind: 'field' }),
    LS2: task('slager', 'LS2', 3, { target: '#f-items-stock-cost_method', label: showKey('slager', 'LS2'), kind: 'field' }),
    LS3: task('slager', 'LS3', 3, { target: 'button[data-run="items.stock.update_cost_prices"]', label: showKey('slager', 'LS3'), kind: 'button' }),
    LS4: task('slager', 'LS4', 3, { target: '#f-items-stock-batch_expiry', label: showKey('slager', 'LS4'), kind: 'field' }),
    LS5: task('slager', 'LS5', 4, { target: '#f-items-stock-status_mail', label: showKey('slager', 'LS5'), kind: 'field' }),
    // LS6 step 3: with FIFO the card's Aktuel is readonly for every item but a bill of materials (showLocations.php:190, :233)
    LS6: task('slager', 'LS6', 3, { target: '#f-items-stock-fifo', label: showKey('slager', 'LS6'), kind: 'field' }),
    LS7: task('slager', 'LS7', 3, { target: '#f-items-stock-vat_on_item_card', label: showKey('slager', 'LS7'), kind: 'field' }),
    LS8: task('slager', 'LS8', 2, { target: '.st-errorlist', label: showKey('slager', 'LS8'), kind: 'status' })
  };

  const s3Stock = {
    group: 'slager',
    states: ['formular', 'kunLaes'],
    flags: ['fejl', 'kostmetode', 'fifo'],

    name: function () {
      return 'help.slager.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = {
        fejl: s3refused(doc),
        kostmetode: !!s3q(doc, '.st-action[data-key="items.stock.update_cost_prices"]:not([hidden])'),
        // Anvend FIFO på lagervarer as the switch shows it (aria-checked, components.php)
        fifo: attr(s3q(doc, '#f-items-stock-fifo'), 'aria-checked') === 'true'
      };
      let state = 'unknown';
      if (s3readOnly(doc)) state = 'kunLaes';
      else if (s3q(doc, '#st-form')) state = 'formular';
      return { state: state, flags: flags, data: {} };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'kunLaes') return ['help.slager.about.kunlaes'];
      if (s === 'formular') {
        const first = s3flag(st, 'fejl') ? 'help.slager.about.fejl' : 'help.slager.about.formular';
        return s3flag(st, 'fifo') ? [first, 'help.slager.about.fifo'] : [first];
      }
      return [];
    },

    tasks: S3_LS_TASKS,

    order: function (ctx, st) {
      if (s3state(st) !== 'formular') return [];
      // the cost price method first: it sets the margin on every sale and the stock value; FIFO is the other valuation choice
      const out = ['LS2', 'LS1'];
      if (s3flag(st, 'kostmetode')) out.push('LS3');
      // FIFO already on (live check 24.15): turning it on is not a first task; the about says what it does on the card
      out.push.apply(out, s3flag(st, 'fifo') ? ['LS4', 'LS5', 'LS7', 'LS6'] : ['LS6', 'LS4', 'LS5', 'LS7']);
      return s3flag(st, 'fejl') ? ['LS8'].concat(out) : out;
    },

    problem: function (ctx, st) {
      return s3state(st) === 'formular' && s3flag(st, 'fejl') ? 'LS8' : '';
    },

    fields: {
      kostmetode: fieldKeys('slager', 'kostmetode'),
      minimum: fieldKeys('slager', 'minimum'),
      statusmail: fieldKeys('slager', 'statusmail'),
      hyppighed: fieldKeys('slager', 'hyppighed'),
      graense: fieldKeys('slager', 'graense')
    },

    fieldKey: s3fieldKey({
      'items.stock.cost_method': 'kostmetode', 'items.stock.default_minimum': 'minimum', 'items.stock.status_mail': 'statusmail',
      'items.stock.status_frequency': 'hyppighed', 'items.stock.status_threshold': 'graense'
    }),

    shortcuts: [
      { keys: 'Ctrl+S', text: 'help.slager.key.01', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.slager.q.q1.q',
        words: {
          da: ['gennemsnitspris', 'genanskaffelsespris', 'kostpris', 'forskel', 'kostprismetode'],
          en: ['average price', 'replacement price', 'cost price', 'difference', 'cost method'],
          no: ['gjennomsnittspris', 'erstatningspris', 'kostpris', 'forskjell', 'kostprismetode']
        },
        steps: ['help.slager.task.ls2.step2', 'help.slager.q.q1.step2']
      },
      Q2: {
        q: 'help.slager.q.q2.q',
        words: {
          da: ['levere', 'kan ikke levere', 'der kan højest leveres', 'negativ', 'beholdning', 'ikke på lager'],
          en: ['deliver', 'cannot deliver', 'at most', 'negative', 'stock', 'out of stock'],
          no: ['levere', 'kan ikke levere', 'høyst', 'negativ', 'beholdning', 'ikke på lager']
        },
        intro: 'help.slager.q.q2.intro',
        steps: ['help.slager.q.q2.step1', 'help.slager.q.q2.step2']
      }
    },

    questionOrder: function (ctx, st) {
      const s = s3state(st);
      return s === 'formular' || s === 'kunLaes' ? ['Q2', 'Q1'] : [];
    },

    signal: function () {
      return false;
    }
  };

  addSection('items.stock', s3Stock);

  // ---- Lagre (systemdata/settingsSection.php?s=items.warehouses)
  // Evidence: settingsDefinitions.php:272-287 (kind rows, table warehouses = grupper LG: Nr., Beskrivelse (required), Afd. (box1, a
  // department); the empty text 6402: every item is in warehouse 0), rows.php:217-226 (a warehouse with stock, orders, order lines,
  // stock movements or a department is in use), :679-690 (after a save each department's default warehouse follows the first
  // warehouse marked with it). rowsView.php as for Varegrupper (add, trash, empty card, errors). settingsRows.js:107-121.

  const S3_LG_TASKS = {
    LG1: task('slagre', 'LG1', 3, { target: '.st-rows[data-table="warehouses"] .st-rfoot button[data-add]', label: showKey('slagre', 'LG1'), kind: 'button' }),
    LG2: task('slagre', 'LG2', 2, {
      target: function (ctx) { return s3cell(ctx.doc, 'warehouses', 'box1'); }, label: showKey('slagre', 'LG2'), kind: 'field'
    }),
    LG3: task('slagre', 'LG3', 2, {
      target: '.st-rows[data-table="warehouses"] tr[data-row]:not(.st-new) button[data-del]', label: showKey('slagre', 'LG3'), kind: 'button'
    }),
    LG4: task('slagre', 'LG4', 2, { target: '.st-errors', label: showKey('slagre', 'LG4'), kind: 'status' })
  };

  const s3Warehouses = {
    group: 'slagre',
    states: ['liste', 'tom', 'kunLaes'],
    flags: ['fejl'],

    name: function () {
      return 'help.slagre.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const n = s3rows(doc, 'warehouses').length;
      let state = 'unknown';
      if (s3readOnly(doc)) state = 'kunLaes';
      else if (n > 0) state = 'liste';
      else if (s3q(doc, '.st-rows[data-table="warehouses"]')) state = 'tom';
      return { state: state, flags: { fejl: s3refused(doc) }, data: { lagre: n } };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'kunLaes') return ['help.slagre.about.kunlaes'];
      if (s === 'tom') return ['help.slagre.about.tom'];
      if (s === 'liste') return ['help.slagre.about.liste'];
      return [];
    },

    tasks: S3_LG_TASKS,

    order: function (ctx, st) {
      const s = s3state(st);
      let out = [];
      if (s === 'liste') out = ['LG1', 'LG2', 'LG3'];
      else if (s === 'tom') out = ['LG1'];
      if (s3flag(st, 'fejl') && (s === 'liste' || s === 'tom')) out = ['LG4'].concat(out);
      return out;
    },

    problem: function (ctx, st) {
      const s = s3state(st);
      return (s === 'liste' || s === 'tom') && s3flag(st, 'fejl') ? 'LG4' : '';
    },

    fields: {
      nr: fieldKeys('slagre', 'nr'),
      afd: fieldKeys('slagre', 'afd')
    },

    fieldKey: s3fieldKey({ 'warehouses.kodenr': 'nr', 'warehouses.box1': 'afd' }),

    shortcuts: [
      { keys: 'Ctrl+S', text: 'help.slagre.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.slagre.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.slagre.q.q1.q',
        words: {
          da: ['slette lager', 'slet lager', 'kan ikke slettes', 'lager i brug', 'fjerne lager'],
          en: ['delete warehouse', 'cannot be deleted', 'warehouse in use', 'remove warehouse'],
          no: ['slette lager', 'kan ikke slettes', 'lager i bruk', 'fjerne lager']
        },
        intro: 'help.slagre.task.lg3.why',
        steps: ['help.slagre.task.lg3.step2']
      }
    },

    questionOrder: function (ctx, st) {
      return s3state(st) === 'liste' ? ['Q1'] : [];
    },

    signal: function () {
      return false;
    }
  };

  addSection('items.warehouses', s3Warehouses);

  // ---- Enheder & materialer (systemdata/settingsSection.php?s=items.units)
  // Evidence: settingsDefinitions.php:288-308 (kind rows; units = table enheder (Enhed, Beskrivelse) with the standard set stk/styk;
  // materials = table materialer (Materiale, Densitet)), rows.php:232-234 (a unit in use: items with it as unit or alternative unit),
  // lager/productCardIncludes/showUnits.php:28-50 (the item card lists the units; Alternativ enh. only with two or more).
  // rowsView.php and settingsRows.js as for Varegrupper.

  const S3_EN_TASKS = {
    EN1: task('senhed', 'EN1', 3, { target: '.st-rows[data-table="units"] .st-rfoot button[data-add]', label: showKey('senhed', 'EN1'), kind: 'button' }),
    EN2: task('senhed', 'EN2', 2, { target: '.st-rows[data-table="units"] button[data-seed]', label: showKey('senhed', 'EN2'), kind: 'button' }),
    EN3: task('senhed', 'EN3', 2, { target: '.st-rows[data-table="materials"] .st-rfoot button[data-add]', label: showKey('senhed', 'EN3'), kind: 'button' }),
    EN4: task('senhed', 'EN4', 2, {
      target: '.st-rows[data-table="units"] tr[data-row]:not(.st-new) button[data-del]', label: showKey('senhed', 'EN4'), kind: 'button'
    }),
    EN5: task('senhed', 'EN5', 2, { target: '.st-errors', label: showKey('senhed', 'EN5'), kind: 'status' })
  };

  const s3Units = {
    group: 'senhed',
    states: ['liste', 'kunLaes'],
    flags: ['fejl', 'ingenEnheder'],

    name: function () {
      return 'help.senhed.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const flags = { fejl: s3refused(doc), ingenEnheder: s3rows(doc, 'units').length === 0 && s3empty(doc, 'units') };
      let state = 'unknown';
      if (s3readOnly(doc)) state = 'kunLaes';
      else if (s3q(doc, '.st-rows[data-table="units"]')) state = 'liste';
      return { state: state, flags: flags, data: {} };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'kunLaes') return ['help.senhed.about.kunlaes'];
      if (s === 'liste') return s3flag(st, 'ingenEnheder') ? ['help.senhed.about.tom'] : ['help.senhed.about.liste'];
      return [];
    },

    tasks: S3_EN_TASKS,

    order: function (ctx, st) {
      if (s3state(st) !== 'liste') return [];
      const out = s3flag(st, 'ingenEnheder') ? ['EN2', 'EN1', 'EN3'] : ['EN1', 'EN3', 'EN4'];
      return s3flag(st, 'fejl') ? ['EN5'].concat(out) : out;
    },

    problem: function (ctx, st) {
      return s3state(st) === 'liste' && s3flag(st, 'fejl') ? 'EN5' : '';
    },

    fields: {
      enhed: fieldKeys('senhed', 'enhed'),
      densitet: fieldKeys('senhed', 'densitet')
    },

    fieldKey: s3fieldKey({ 'units.betegnelse': 'enhed', 'materials.densitet': 'densitet' }),

    shortcuts: [
      { keys: 'Ctrl+S', text: 'help.senhed.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.senhed.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.senhed.q.q1.q',
        words: {
          da: ['enhed', 'stk', 'timer', 'kg', 'enhed på varen', 'alternativ enhed'],
          en: ['unit', 'pcs', 'hours', 'kg', 'unit on item', 'alternative unit'],
          no: ['enhet', 'stk', 'timer', 'kg', 'enhet på varen', 'alternativ enhet']
        },
        intro: 'help.senhed.q.q1.intro',
        steps: ['help.senhed.task.en1.step3']
      }
    },

    questionOrder: function (ctx, st) {
      return s3state(st) === 'liste' ? ['Q1'] : [];
    },

    signal: function () {
      return false;
    }
  };

  addSection('items.units', s3Units);

  // ---- Varianter (systemdata/settingsSection.php?s=items.variants)
  // Evidence: settingsDefinitions.php:309-329 (kind rows; types = table varianter (Variant, Værdier derived), deleting a type deletes
  // its values (6830, rowHooks.php:655-657); values = table variant_typer filtered by the type in the address (?type=, the select
  // #st-f-values, rowsView.php:258-268); an add row only when a type is chosen (rowsView.php:398); in use: variant items, rows.php:
  // 290-297), :1059 (the link Importér to importer_varianter.php, with write on Import & eksport). settingsSection.php:182-193 (the
  // type kept in the address). The values are chosen on the item card (6827).

  const S3_VA_TASKS = {
    VA1: task('svariant', 'VA1', 3, { target: '.st-rows[data-table="types"] .st-rfoot button[data-add]', label: showKey('svariant', 'VA1'), kind: 'button' }),
    VA2: task('svariant', 'VA2', 3, { target: '#st-f-values', label: showKey('svariant', 'VA2'), kind: 'field' }),
    VA3: task('svariant', 'VA3', 2, { target: 'a.st-btn[href="importer_varianter.php"]', label: showKey('svariant', 'VA3'), kind: 'button' }),
    VA4: task('svariant', 'VA4', 2, {
      target: '.st-rows[data-table="types"] tr[data-row]:not(.st-new) button[data-del]', label: showKey('svariant', 'VA4'), kind: 'button'
    }),
    VA5: task('svariant', 'VA5', 2, { target: '.st-errors', label: showKey('svariant', 'VA5'), kind: 'status' })
  };

  const s3Variants = {
    group: 'svariant',
    states: ['liste', 'tom', 'kunLaes'],
    flags: ['fejl'],

    name: function () {
      return 'help.svariant.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const n = s3rows(doc, 'types').length;
      let state = 'unknown';
      if (s3readOnly(doc)) state = 'kunLaes';
      else if (n > 0) state = 'liste';
      else if (s3q(doc, '.st-rows[data-table="types"]')) state = 'tom';
      return { state: state, flags: { fejl: s3refused(doc) }, data: { typer: n } };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'kunLaes') return ['help.svariant.about.kunlaes'];
      if (s === 'tom') return ['help.svariant.about.tom'];
      if (s === 'liste') return ['help.svariant.about.liste'];
      return [];
    },

    tasks: S3_VA_TASKS,

    order: function (ctx, st) {
      const s = s3state(st);
      let out = [];
      if (s === 'liste') out = ['VA2', 'VA1', 'VA3', 'VA4'];
      else if (s === 'tom') out = ['VA1', 'VA3'];
      if (s3flag(st, 'fejl') && (s === 'liste' || s === 'tom')) out = ['VA5'].concat(out);
      return out;
    },

    problem: function (ctx, st) {
      const s = s3state(st);
      return (s === 'liste' || s === 'tom') && s3flag(st, 'fejl') ? 'VA5' : '';
    },

    fields: {
      variant: fieldKeys('svariant', 'variant'),
      vaerdi: fieldKeys('svariant', 'vaerdi')
    },

    fieldKey: s3fieldKey({ 'types.beskrivelse': 'variant', 'values.beskrivelse': 'vaerdi' }),

    shortcuts: [
      { keys: 'Ctrl+S', text: 'help.svariant.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.svariant.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.svariant.q.q1.q',
        words: {
          da: ['variant', 'varianter', 'farve', 'størrelse', 'variantvare', 'varekort'],
          en: ['variant', 'variants', 'colour', 'color', 'size', 'variant item', 'item card'],
          no: ['variant', 'varianter', 'farge', 'størrelse', 'variantvare', 'varekort']
        },
        intro: 'help.svariant.q.q1.intro',
        steps: ['help.svariant.q.q1.step1']
      }
    },

    questionOrder: function (ctx, st) {
      const s = s3state(st);
      return s === 'liste' || s === 'tom' ? ['Q1'] : [];
    },

    signal: function () {
      return false;
    }
  };

  addSection('items.variants', s3Variants);

  // ---- Emballage (producentansvar) (systemdata/settingsSection.php?s=items.packaging)
  // Evidence: settingsDefinitions.php:614-621 and :965-967 (one switch, Anvend emballagemodul, settings items packagingModuleEnabled);
  // the switch is button[role=switch]#f-items-packaging-enabled with aria-checked (components.php:832-834). When it is on:
  // lager/varekort.php:2109-2113 (a saved item card gets the button Emballage, emballage.php?id=), finans/rapport_includes/
  // forside.php:341-345, :378-379 (the report type Emballage under Finans » Rapporter), debitor/debitorkort.php:1986 (the end-user
  // type on the customer card).

  const S3_EM_TASKS = {
    EM1: task('semballage', 'EM1', 2, { target: '#f-items-packaging-enabled', label: showKey('semballage', 'EM1'), kind: 'field' }),
    EM2: task('semballage', 'EM2', 3)
  };

  const s3Packaging = {
    group: 'semballage',
    states: ['formular', 'kunLaes'],
    flags: ['slaaetTil'],

    name: function () {
      return 'help.semballage.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const sw = s3q(doc, '#f-items-packaging-enabled');
      let state = 'unknown';
      if (s3readOnly(doc)) state = 'kunLaes';
      else if (s3q(doc, '#st-form')) state = 'formular';
      return { state: state, flags: { slaaetTil: attr(sw, 'aria-checked') === 'true' }, data: {} };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'kunLaes') return ['help.semballage.about.kunlaes'];
      if (s === 'formular') return s3flag(st, 'slaaetTil') ? ['help.semballage.about.til'] : ['help.semballage.about.fra'];
      return [];
    },

    tasks: S3_EM_TASKS,

    order: function (ctx, st) {
      if (s3state(st) !== 'formular') return [];
      return s3flag(st, 'slaaetTil') ? ['EM2', 'EM1'] : ['EM1', 'EM2'];
    },

    fields: {},

    shortcuts: [
      { keys: 'Ctrl+S', text: 'help.semballage.key.01', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {},

    questionOrder: function () {
      return [];
    },

    signal: function () {
      return false;
    }
  };

  addSection('items.packaging', s3Packaging);

  // ---- Integrationer (systemdata/settingsSection.php?s=integrations.connections)
  // Evidence: settingsDefinitions.php:671-690 (kind list; the rows REST API, Webshop-synkronisering, QuickPay, GLS, Danske Fragtmænd,
  // E-faktura (NemHandel), App-login (QR), MobilePay, Flatpay, Vibrant, Bankforbindelse (only with its feature); the sub-section
  // Betalingsudbydere only with the POS module, listView.php:226-229), :1463-1586 (each row's drawer: fields, actions and links).
  // listView.php:318-330 (row .st-irow.st-irow-<ok|off|err|soon>[data-item], the button [data-open]: Indstil 6122 when active,
  // Aktivér 6123 when not, Ret 6124 on an error, Vis 6143 without write), :371-450 (the drawer and its error list .st-errorlist,
  // Gem ændringer [data-save] disabled until a change), :298-301 (a new API key in .st-flash-key, shown once).
  // integrations.php:31-108 (the status: MobilePay with a client id but no webhook is err, Webhook ikke forbundet 6102, button Ret).
  // settingsSection.php:545-559 (flashes: the new key, ?webhook=ok|fail 6131/6132, ?qr=). actions.php:158-170 (Generér ny API-nøgle,
  // the shop sync actions, the webhook setup page). integrations.php:257 (NemHandel: registered, or 6376 at the first digital invoice),
  // :322-348 (Flatpay: Brugernavn, Adgangskode, Hent Flatpay-ID; Vibrant: Gem Vibrant API-nøglen først, Opret login).
  // settingsList.js:103-135 (a drawer opens, ?item= in the address), :343-352 (Ctrl+S saves the open drawer, Esc closes it).

  /** The open drawer of a list section (listView.php:371), else the one the address names (?item=, settingsList.js:131-134). */
  function s3drawer(ctx) {
    const open = s3q(ctx.doc, 'aside.st-drawer:not([hidden])');
    if (open) return open;
    const item = s3param(ctx, 'item');
    return /^[a-z0-9_]+$/.test(item) ? s3q(ctx.doc, 'aside.st-drawer[data-item="' + item + '"]') : null;
  }

  function s3rowButton(item) {
    return '.st-irow[data-item="' + item + '"] button[data-open]';
  }

  const S3_IN_TASKS = {
    IN1: task('sintegr', 'IN1', 4, { target: s3rowButton('webshop'), label: showKey('sintegr', 'IN1'), kind: 'button' }),
    IN2: task('sintegr', 'IN2', 4, { target: s3rowButton('rest_api'), label: showKey('sintegr', 'IN2'), kind: 'button' }),
    IN3: task('sintegr', 'IN3', 2, { target: '.st-flash-key', label: showKey('sintegr', 'IN3'), kind: 'status' }),
    IN4: task('sintegr', 'IN4', 3, { target: s3rowButton('easyubl'), label: showKey('sintegr', 'IN4'), kind: 'button' }),
    IN5: task('sintegr', 'IN5', 3, { target: s3rowButton('gls'), label: showKey('sintegr', 'IN5'), kind: 'button' }),
    IN6: task('sintegr', 'IN6', 4, { target: s3rowButton('dfm'), label: showKey('sintegr', 'IN6'), kind: 'button' }),
    IN7: task('sintegr', 'IN7', 3, { target: s3rowButton('app'), label: showKey('sintegr', 'IN7'), kind: 'button' }),
    IN8: task('sintegr', 'IN8', 4, { target: s3rowButton('mobilepay'), label: showKey('sintegr', 'IN8'), kind: 'button' }),
    IN9: task('sintegr', 'IN9', 3, { target: s3rowButton('mobilepay'), label: showKey('sintegr', 'IN9'), kind: 'button' }),
    IN10: task('sintegr', 'IN10', 2, {
      target: function (ctx) {
        const d = s3drawer(ctx);
        return (d ? s3q(d, '.st-errorlist') : null) || s3q(ctx.doc, '.st-errorlist');
      },
      label: showKey('sintegr', 'IN10'), kind: 'status'
    }),
    IN11: task('sintegr', 'IN11', 4, {
      target: function (ctx) { return s3q(ctx.doc, s3rowButton('flatpay')) || s3q(ctx.doc, s3rowButton('vibrant')); },
      label: showKey('sintegr', 'IN11'), kind: 'button'
    })
  };

  const s3Connections = {
    group: 'sintegr',
    states: ['liste', 'kunLaes'],
    flags: ['nyNoegle', 'webhookFejl', 'fejl', 'kasse', 'kasseIBrug', 'aktiv'],

    name: function () {
      return 'help.sintegr.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const webhookFail = s3param(ctx, 'webhook') === 'fail';
      const flags = {
        nyNoegle: !!s3q(doc, '.st-flash-key'),
        webhookFejl: webhookFail || !!s3q(doc, '.st-irow.st-irow-err[data-item="mobilepay"]'),
        fejl: !!s3q(doc, '.st-errorlist'),
        kasse: !!s3q(doc, '#sub-payments'),
        // a payment provider that is set up (ok) or set up wrong (err): the company takes payments in the till. The
        // sub-section alone is not enough: SettingsService::hasModule('pos') is true on any install without a pos_licence row.
        kasseIBrug: !!s3q(doc, ['mobilepay', 'flatpay', 'vibrant'].map(function (i) { return '.st-irow-ok[data-item="' + i + '"], .st-irow-err[data-item="' + i + '"]'; }).join(', ')),
        aktiv: !!s3q(doc, '.st-irow.st-irow-ok')
      };
      let state = 'unknown';
      if (s3readOnly(doc)) state = 'kunLaes';
      else if (s3q(doc, '.st-irow')) state = 'liste';
      return { state: state, flags: flags, data: { item: s3param(ctx, 'item') } };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'kunLaes') return ['help.sintegr.about.kunlaes'];
      if (s !== 'liste') return [];
      if (s3flag(st, 'webhookFejl')) return ['help.sintegr.about.webhook'];
      return s3flag(st, 'aktiv') ? ['help.sintegr.about.liste'] : ['help.sintegr.about.ny'];
    },

    tasks: S3_IN_TASKS,

    order: function (ctx, st) {
      if (s3state(st) !== 'liste') return [];
      // till payments first only for a company that uses them; with the module merely present they come after e-invoices and the webshop
      let out = s3flag(st, 'kasseIBrug') ? ['IN8', 'IN11', 'IN4', 'IN1', 'IN5', 'IN6', 'IN2', 'IN7']
        : (s3flag(st, 'kasse') ? ['IN4', 'IN1', 'IN8', 'IN11', 'IN5', 'IN6', 'IN2', 'IN7'] : ['IN4', 'IN1', 'IN5', 'IN6', 'IN2', 'IN7']);
      if (s3flag(st, 'nyNoegle')) out = ['IN3'].concat(out);
      if (s3flag(st, 'fejl')) out = ['IN10'].concat(out);
      if (s3flag(st, 'webhookFejl')) out = ['IN9'].concat(out);
      return unique(out);
    },

    problem: function (ctx, st) {
      if (s3state(st) !== 'liste') return '';
      if (s3flag(st, 'webhookFejl')) return 'IN9';
      if (s3flag(st, 'fejl')) return 'IN10';
      return '';
    },

    fields: {
      shop_url: fieldKeys('sintegr', 'shop_url'),
      ip_liste: fieldKeys('sintegr', 'ip_liste'),
      easyubl_noegle: fieldKeys('sintegr', 'easyubl_noegle'),
      mp_client_id: fieldKeys('sintegr', 'mp_client_id')
    },

    fieldKey: s3fieldKey({
      'integrations.webshop.url': 'shop_url', 'integrations.rest_api.ip_list': 'ip_liste', 'integrations.easyubl.api_key': 'easyubl_noegle',
      'integrations.mobilepay.client_id': 'mp_client_id'
    }),

    shortcuts: [
      { keys: 'Ctrl+S', text: 'help.sintegr.key.01', group: 'keys' },
      { keys: 'Esc', text: 'help.sintegr.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.sintegr.q.q1.q',
        words: {
          da: ['mobilepay', 'vipps', 'webhook', 'betaling', 'registreres ikke', 'betalinger mangler'],
          en: ['mobilepay', 'vipps', 'webhook', 'payment', 'not registered', 'payments missing'],
          no: ['mobilepay', 'vipps', 'webhook', 'betaling', 'registreres ikke', 'betalinger mangler']
        },
        intro: 'help.sintegr.q.q1.intro',
        steps: ['help.sintegr.task.in9.step2', 'help.sintegr.task.in9.step3']
      },
      Q2: {
        q: 'help.sintegr.q.q2.q',
        words: {
          da: ['api', 'api-nøgle', 'nøgle', 'rest', 'adgang', 'mistet nøgle'],
          en: ['api', 'api key', 'key', 'rest', 'access', 'lost key'],
          no: ['api', 'api-nøkkel', 'nøkkel', 'rest', 'tilgang', 'mistet nøkkel']
        },
        intro: 'help.sintegr.q.q2.intro',
        steps: stepKeys('sintegr', 'IN2', 2, 3)
      },
      Q3: {
        q: 'help.sintegr.q.q3.q',
        words: {
          da: ['e-faktura', 'ean', 'nemhandel', 'oioubl', 'offentlig kunde', 'easyubl'],
          en: ['e-invoice', 'ean', 'nemhandel', 'oioubl', 'public customer', 'easyubl'],
          no: ['e-faktura', 'ean', 'nemhandel', 'oioubl', 'offentlig kunde', 'easyubl']
        },
        intro: 'help.sintegr.task.in4.why',
        steps: stepKeys('sintegr', 'IN4', 1, 3)
      }
    },

    questionOrder: function (ctx, st) {
      const s = s3state(st);
      if (s === 'liste') return s3flag(st, 'kasseIBrug') || s3flag(st, 'webhookFejl') ? ['Q1', 'Q3', 'Q2'] : ['Q3', 'Q2'];
      if (s === 'kunLaes') return ['Q3'];
      return [];
    },

    signal: function () {
      return false;
    }
  };

  addSection('integrations.connections', s3Connections);

  // ---- Afhentningsadresser (systemdata/settingsSection.php?s=integrations.pickup)
  // Evidence: settingsDefinitions.php:459-466 (kind list, rows from the DFM_Pickup settings, the empty text 6386: the main address is
  // used), :1192-1231 (Tilføj afhentningsadresse; each address: Firmanavn, Eventuelt ekstra navn, Adresse, Postnummer, By, Knap navn,
  // then an optional own Danske Fragtmænd agreement; Slet afhentningsadresse under Farezone). actions.php:302-321 (add creates
  // Ny afhentningsadresse and opens its drawer; delete removes it with a flash). listView.php:287-295 (the add form in the head, write
  // access only), :318-330 (rows and Indstil), :340-342 (.st-list-empty).

  const S3_AF_TASKS = {
    AF1: task('safhent', 'AF1', 4, {
      target: function (ctx) { return s3addButton(ctx.doc, 'integrations.pickup.add'); }, label: showKey('safhent', 'AF1'), kind: 'button'
    }),
    AF2: task('safhent', 'AF2', 3, { target: '.st-irow[data-item^="pickup_"] button[data-open]', label: showKey('safhent', 'AF2'), kind: 'button' }),
    AF3: task('safhent', 'AF3', 3, { target: '.st-irow[data-item^="pickup_"] button[data-open]', label: showKey('safhent', 'AF3'), kind: 'button' })
  };

  const s3Pickup = {
    group: 'safhent',
    states: ['liste', 'tom', 'kunLaes'],
    flags: [],

    name: function () {
      return 'help.safhent.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state = 'unknown';
      if (s3readOnly(doc)) state = 'kunLaes';
      else if (s3q(doc, '.st-irow[data-item^="pickup_"]')) state = 'liste';
      else if (s3q(doc, '.st-list')) state = 'tom';
      return { state: state, flags: {}, data: {} };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'kunLaes') return ['help.safhent.about.kunlaes'];
      if (s === 'tom') return ['help.safhent.about.tom'];
      if (s === 'liste') return ['help.safhent.about.liste'];
      return [];
    },

    tasks: S3_AF_TASKS,

    order: function (ctx, st) {
      const s = s3state(st);
      if (s === 'liste') return ['AF1', 'AF2', 'AF3'];
      if (s === 'tom') return ['AF1'];
      return [];
    },

    fields: {
      knapnavn: fieldKeys('safhent', 'knapnavn')
    },

    fieldKey: s3fieldKey({ 'integrations.pickup.button': 'knapnavn' }),

    shortcuts: [
      { keys: 'Ctrl+S', text: 'help.safhent.key.01', group: 'keys' },
      { keys: 'Esc', text: 'help.safhent.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {},

    questionOrder: function () {
      return [];
    },

    signal: function () {
      return false;
    }
  };

  addSection('integrations.pickup', s3Pickup);

  // ---- Kasse (systemdata/settingsSection.php?s=pos.*): short pages, two tasks each (BRIEF.md "Not in scope")
  // Evidence: settingsDefinitions.php:468-518 (the seven sections, module pos: settingsSection.php:109-113 sends a company without
  // the POS licence back to the hub), :1004-1044 (Kasser: Tilføj kasse; per till Afdeling, Momsgruppe, Kontantkonto, Mellemkonto,
  // Differencekonto, Printer IP, Terminal type, Køkkenprinter-IP ...), :1233-1255 (Betalingskort: Fælles for alle kort with Andre
  // kort; per card Navn på betalingsmåde, Kontonr, Betalingskort, Aktivt, Gavekort), :1256-1322 (Borde, Kasseoptælling, Kvittering,
  // Køkken, Skærm). actions.php:173-190 (Tilføj kasse opens the new till's drawer), :329-333 and :420-455 (Tilføj betalingsmåde
  // opens the new card's drawer). listView.php:287-295 (the add form), :318-330 (rows, Indstil), :340-342 (.st-list-empty).
  // includes/settings/hub.php:948-1000 (the left menu: POS-menuer is the second page under Skærm & kassemenuer).

  /** A short POS form section: the form and read-only states, its tasks in a fixed order. */
  function s3PosForm(group, tasks, ids) {
    return {
      group: group,
      states: ['formular', 'kunLaes'],
      flags: [],
      name: function () {
        return 'help.' + group + '.name';
      },
      detect: function (ctx) {
        let state = 'unknown';
        if (s3readOnly(ctx.doc)) state = 'kunLaes';
        else if (s3q(ctx.doc, '#st-form')) state = 'formular';
        return { state: state, flags: {}, data: {} };
      },
      about: function (ctx, st) {
        const s = s3state(st);
        if (s === 'formular') return ['help.' + group + '.about.formular'];
        if (s === 'kunLaes') return ['help.' + group + '.about.kunlaes'];
        return [];
      },
      tasks: tasks,
      order: function (ctx, st) {
        return s3state(st) === 'formular' ? ids.slice() : [];
      },
      fields: {},
      shortcuts: [{ keys: 'Ctrl+S', text: 'help.' + group + '.key.01', group: 'keys' }],
      shortcutsNote: 'help.common.shortcuts_note',
      questions: {},
      questionOrder: function () {
        return [];
      },
      signal: function () {
        return false;
      }
    };
  }

  /** A short POS list section (tills, payment cards): rows, an empty list, read-only. */
  function s3PosList(group, tasks, rowSel, listIds, emptyIds) {
    return {
      group: group,
      states: ['liste', 'tom', 'kunLaes'],
      flags: [],
      name: function () {
        return 'help.' + group + '.name';
      },
      detect: function (ctx) {
        let state = 'unknown';
        if (s3readOnly(ctx.doc)) state = 'kunLaes';
        else if (s3q(ctx.doc, rowSel)) state = 'liste';
        else if (s3q(ctx.doc, '.st-list')) state = 'tom';
        return { state: state, flags: {}, data: {} };
      },
      about: function (ctx, st) {
        const s = s3state(st);
        if (s === 'liste') return ['help.' + group + '.about.liste'];
        if (s === 'tom') return ['help.' + group + '.about.tom'];
        if (s === 'kunLaes') return ['help.' + group + '.about.kunlaes'];
        return [];
      },
      tasks: tasks,
      order: function (ctx, st) {
        const s = s3state(st);
        if (s === 'liste') return listIds.slice();
        if (s === 'tom') return emptyIds.slice();
        return [];
      },
      fields: {},
      shortcuts: [
        { keys: 'Ctrl+S', text: 'help.' + group + '.key.01', group: 'keys' },
        { keys: 'Esc', text: 'help.' + group + '.key.02', group: 'keys' }
      ],
      shortcutsNote: 'help.common.shortcuts_note',
      questions: {},
      questionOrder: function () {
        return [];
      },
      signal: function () {
        return false;
      }
    };
  }

  const s3PosTills = s3PosList('skasse', {
    KA1: task('skasse', 'KA1', 2, {
      target: function (ctx) { return s3addButton(ctx.doc, 'pos.tills.add'); }, label: showKey('skasse', 'KA1'), kind: 'button'
    }),
    KA2: task('skasse', 'KA2', 4, { target: '.st-irow[data-item^="till_"] button[data-open]', label: showKey('skasse', 'KA2'), kind: 'button' })
  }, '.st-irow[data-item^="till_"]', ['KA2', 'KA1'], ['KA1']);
  addSection('pos.tills', s3PosTills);

  const s3PosCash = s3PosForm('skasseopt', {
    KO1: task('skasseopt', 'KO1', 3, { target: '#f-pos-cash-opening_float', label: showKey('skasseopt', 'KO1'), kind: 'field' }),
    KO2: task('skasseopt', 'KO2', 2, { target: '#f-pos-cash-discount_item', label: showKey('skasseopt', 'KO2'), kind: 'field' })
  }, ['KO1', 'KO2']);
  addSection('pos.cash', s3PosCash);

  const s3PosCards = s3PosList('skort', {
    KT1: task('skort', 'KT1', 3, {
      target: function (ctx) { return s3addButton(ctx.doc, 'pos.cards.add'); }, label: showKey('skort', 'KT1'), kind: 'button'
    }),
    KT2: task('skort', 'KT2', 2, { target: s3rowButton('general'), label: showKey('skort', 'KT2'), kind: 'button' })
  }, '.st-irow[data-item^="card_"]', ['KT1', 'KT2'], ['KT1', 'KT2']);
  addSection('pos.cards', s3PosCards);

  const s3PosReceipt = s3PosForm('sbon', {
    BO1: task('sbon', 'BO1', 2, { target: '#f-pos-receipt-print_receipt', label: showKey('sbon', 'BO1'), kind: 'field' }),
    BO2: task('sbon', 'BO2', 2, { target: '#f-pos-receipt-timeout', label: showKey('sbon', 'BO2'), kind: 'field' })
  }, ['BO1', 'BO2']);
  addSection('pos.receipt', s3PosReceipt);

  const s3PosScreen = s3PosForm('sskaerm', {
    SK1: task('sskaerm', 'SK1', 2, { target: '#f-pos-screen-cash_button', label: showKey('sskaerm', 'SK1'), kind: 'field' }),
    SK2: task('sskaerm', 'SK2', 2, { target: 'a.st-menu-row[href*="posmenuer.php"]', label: showKey('sskaerm', 'SK2'), kind: 'button' })
  }, ['SK1', 'SK2']);
  addSection('pos.screen', s3PosScreen);

  const s3PosTables = s3PosForm('sborde', {
    BD1: task('sborde', 'BD1', 2, { target: '#f-pos-tables-count', label: showKey('sborde', 'BD1'), kind: 'field' }),
    BD2: task('sborde', 'BD2', 2, { target: 'a.st-btn[href*="bordplaner/planner"]', label: showKey('sborde', 'BD2'), kind: 'button' })
  }, ['BD1', 'BD2']);
  addSection('pos.tables', s3PosTables);

  const s3PosKitchen = s3PosForm('skoekken', {
    KD1: task('skoekken', 'KD1', 2, { target: '#f-pos-kitchen-kds_active', label: showKey('skoekken', 'KD1'), kind: 'field' }),
    KD2: task('skoekken', 'KD2', 2, { target: '#f-pos-kitchen-print_active', label: showKey('skoekken', 'KD2'), kind: 'field' })
  }, ['KD1', 'KD2']);
  addSection('pos.kitchen', s3PosKitchen);

  // ---- POS-menuer (systemdata/posmenuer.php)
  // Reached from the left menu of Salg: the second page under Kasse » Skærm & kassemenuer (includes/settings/hub.php:169-170,
  // settingsRegistry.php:236), in the frame with the settings menu (posmenuer.php:384-386, systemdata/top.php). Three views:
  // no menu open (select menuvalg with Opret ny at the end, posmenuer.php:655-711), a menu open (Beskrivelse, Menutype and the
  // button grid, :713-800, :867-958: each button a link posmenuer.php?menu_id=&ret_row=&ret_col=), one button open (Skift menu,
  // Tekst, Farvekode, Funktion (hard-coded Danish options Varenr, Menu, Systemknap, Betalingsknap ...), OK, Anuller and Slet Knap,
  // :393-411, :604-653). Below: Importér POS-menuer and Eksportér POS-menuer (:840-847).

  const S3_PM_TASKS = {
    PM1: task('sposmenu', 'PM1', 2, { target: 'select[name="menuvalg"]', label: showKey('sposmenu', 'PM1'), kind: 'field' }),
    PM2: task('sposmenu', 'PM2', 2, { target: 'select[name="menuvalg"]', label: showKey('sposmenu', 'PM2'), kind: 'field' }),
    PM3: task('sposmenu', 'PM3', 3, { target: 'a[href*="ret_row="]', label: showKey('sposmenu', 'PM3'), kind: 'button' }),
    PM4: task('sposmenu', 'PM4', 2, {
      // OK of the open button only: without one, the OK beside Menu (ID) opens a menu (posmenuer.php:711)
      target: function (ctx) { return s3q(ctx.doc, 'input[name="SletKnap"]') ? s3q(ctx.doc, 'form[name="posmenuer"] input[name="OK"]') : null; },
      label: showKey('sposmenu', 'PM4'), kind: 'button'
    }),
    PM5: task('sposmenu', 'PM5', 2, { target: 'form[name="export_posmenu"] input[type="submit"]', label: showKey('sposmenu', 'PM5'), kind: 'button' })
  };

  const s3PosMenus = {
    group: 'sposmenu',
    states: ['vaelg', 'menu', 'knap'],
    flags: [],

    name: function () {
      return 'help.sposmenu.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state = 'unknown';
      if (s3q(doc, 'input[name="SletKnap"]')) state = 'knap';
      else if (s3q(doc, 'select[name="menutype"]')) state = 'menu';
      else if (s3q(doc, 'select[name="menuvalg"]')) state = 'vaelg';
      return { state: state, flags: {}, data: {} };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'vaelg') return ['help.sposmenu.about.vaelg'];
      if (s === 'menu') return ['help.sposmenu.about.menu'];
      if (s === 'knap') return ['help.sposmenu.about.knap'];
      return [];
    },

    tasks: S3_PM_TASKS,

    order: function (ctx, st) {
      const s = s3state(st);
      if (s === 'vaelg') return ['PM1', 'PM2', 'PM5'];
      if (s === 'menu') return ['PM3', 'PM2', 'PM5'];
      if (s === 'knap') return ['PM4'];
      return [];
    },

    fields: {},

    shortcuts: [],

    questions: {
      Q1: {
        q: 'help.sposmenu.q.q1.q',
        words: {
          da: ['kopiere menuer', 'andet regnskab', 'eksport', 'import', 'pos-menuer', 'flytte menuer'],
          en: ['copy menus', 'other company', 'export', 'import', 'pos menus', 'move menus'],
          no: ['kopiere menyer', 'annet regnskap', 'eksport', 'import', 'pos-menyer', 'flytte menyer']
        },
        steps: stepKeys('sposmenu', 'PM5', 1, 2)
      }
    },

    questionOrder: function (ctx, st) {
      return s3state(st) === 'unknown' ? [] : ['Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['systemdata/posmenuer.php'] = s3PosMenus;

  // ---- Importér POS-menuer (systemdata/importer_posmenu.php)
  // Reached from POS-menuer (posmenuer.php:841-843, a form posting here). In the standard settings layout (importer_posmenu.php:
  // 47-50). The upload form: Vælg POS-menuer-fil (1936) input uploadedfile, Hent (1078) (:76-85). includes/posmenu_import.php:
  // 25-35: the import first deletes every POS button and every POS menu of the company.

  const s3PosMenuImport = {
    group: 'sposmenu',
    states: ['upload'],
    flags: [],

    name: function () {
      return 'help.sposmenu.name_import';
    },

    detect: function (ctx) {
      return { state: s3q(ctx.doc, 'input[name="uploadedfile"]') ? 'upload' : 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      return s3state(st) === 'upload' ? ['help.sposmenu.about.import'] : [];
    },

    tasks: {
      PI1: task('sposmenu', 'PI1', 3, { target: 'input[name="uploadedfile"]', label: showKey('sposmenu', 'PI1'), kind: 'field' })
    },

    order: function (ctx, st) {
      return s3state(st) === 'upload' ? ['PI1'] : [];
    },

    fields: {},

    shortcuts: [],

    questions: {},

    questionOrder: function () {
      return [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['systemdata/importer_posmenu.php'] = s3PosMenuImport;

  // ---- Eksportér POS-menuer (systemdata/exporter_posmenu.php)
  // Reached from POS-menuer (posmenuer.php:844-846). The page writes the file at once and shows Højreklik her: POS menuer-kopi,
  // gem destination som (1362, 1933, 1363; exporter_posmenu.php:93-96), the link to the export file.

  const s3PosMenuExport = {
    group: 'sposmenu',
    states: ['fil'],
    flags: [],

    name: function () {
      return 'help.sposmenu.name_export';
    },

    detect: function (ctx) {
      return { state: s3q(ctx.doc, 'a[href*="saldi_posmenus"]') ? 'fil' : 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      return s3state(st) === 'fil' ? ['help.sposmenu.about.export'] : [];
    },

    tasks: {
      PE1: task('sposmenu', 'PE1', 2, { target: 'a[href*="saldi_posmenus"]', label: showKey('sposmenu', 'PE1'), kind: 'button' })
    },

    order: function (ctx, st) {
      return s3state(st) === 'fil' ? ['PE1'] : [];
    },

    fields: {},

    shortcuts: [],

    questions: {},

    questionOrder: function () {
      return [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['systemdata/exporter_posmenu.php'] = s3PosMenuExport;

  // ---- Importér varianter fra CSV (systemdata/importer_varianter.php)
  // Reached from Varianter: the link Importér under Importér varianter fra CSV (settingsDefinitions.php:1059, with write on
  // Import & eksport). In the standard settings layout (importer_varianter.php:56). Without write it prints only 5665 and stops
  // (:44-47). Two forms: Varianttyper (one per line, input variant_types_file) and Værdier (variant;værdi, the variant must exist,
  // input variant_values_file), each with Importér (:58-80). systemdata/diverse.php:343-379, :380-438: only new types and values are
  // added (a name that exists is skipped, a value of a variant that does not exist is not imported); the result shows on Varianter.

  const s3VariantImport = {
    group: 'svariant',
    states: ['upload', 'ingenAdgang'],
    flags: [],

    name: function () {
      return 'help.svariant.name_import';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state = 'unknown';
      if (s3q(doc, 'input[name="variant_types_file"]')) state = 'upload';
      else if (doc && s3q(doc, 'body') && !s3q(doc, 'form')) state = 'ingenAdgang';
      return { state: state, flags: {}, data: {} };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'upload') return ['help.svariant.about.import'];
      if (s === 'ingenAdgang') return ['help.svariant.about.ingen_adgang'];
      return [];
    },

    tasks: {
      VI1: task('svariant', 'VI1', 2, { target: 'input[name="variant_types_file"]', label: showKey('svariant', 'VI1'), kind: 'field' }),
      VI2: task('svariant', 'VI2', 3, { target: 'input[name="variant_values_file"]', label: showKey('svariant', 'VI2'), kind: 'field' })
    },

    order: function (ctx, st) {
      return s3state(st) === 'upload' ? ['VI1', 'VI2'] : [];
    },

    fields: {},

    shortcuts: [],

    questions: {},

    questionOrder: function () {
      return [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['systemdata/importer_varianter.php'] = s3VariantImport;

  // ---- Vis QR-kode til app-login (systemdata/barcodescan.php)
  // Reached from Integrationer » App-login (QR): Åbn beside Vis QR-kode til app-login (settingsDefinitions.php:1560-1561), and from
  // the hub's search (settingsRegistry.php:201). In the standard settings layout, Tilbage to the App panel (barcodescan.php:46-49).
  // The code is drawn in img#qrcode-img (:94-100, :112-135); without a company the page shows an error in #error-message (:40-44).
  // The page's own text (hard-coded English): the code gives access to the app until the user unchecks Remember me in the app.

  const s3AppQr = {
    group: 'sxlager',
    states: ['kode'],
    flags: [],

    name: function () {
      return 'help.sxlager.name_qr';
    },

    detect: function (ctx) {
      return { state: s3q(ctx.doc, '#qrcode-img') ? 'kode' : 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      return s3state(st) === 'kode' ? ['help.sxlager.about.qr'] : [];
    },

    tasks: {
      QR1: task('sxlager', 'QR1', 3, { target: '#qrcode-img', label: showKey('sxlager', 'QR1'), kind: 'status' })
    },

    order: function (ctx, st) {
      return s3state(st) === 'kode' ? ['QR1'] : [];
    },

    fields: {},

    shortcuts: [],

    questions: {},

    questionOrder: function () {
      return [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['systemdata/barcodescan.php'] = s3AppQr;

  // ---- Mærkater (systemdata/labels.php)
  // Reached from the hub: Køb & lager » Varer » Mærkater (includes/settings/hub.php:197-198, settingsRegistry.php:181, group items);
  // in the frame with the settings menu (labels.php:178-194). The list of labels .lb-names with Ny mærkat button[data-open="lb-new"]
  // (:197-207, write only); the editor form#lb-form: the visual editor (action save_visual: Mærkattype select labelType, Layout
  // with Kolonner, Rækker, Tekstlængde, Bredde, Højde ..., Indhold, Egne tekstlinjer) or Rå HTML (action save_raw, textarea rawHTML)
  // (:209-254); the link Rå HTML / Visuel editor, Slet button[data-open="lb-del"] (not on Standard) and Gem with accesskey g
  // (:255-270). Flashes: saved, created, deleted, refused 5079 (.st-flash-err), a template the visual editor cannot show 5078
  // (.st-flash-warn), read only 5755 (:127-138). Delete: the label can no longer be chosen when printing (7090, :114-118).

  const S3_LB_TASKS = {
    LB1: task('sxlager', 'LB1', 3, { target: 'button[data-open="lb-new"]', label: showKey('sxlager', 'LB1'), kind: 'button' }),
    LB2: task('sxlager', 'LB2', 4, { target: 'select[name="labelType"]', label: showKey('sxlager', 'LB2'), kind: 'field' }),
    LB3: task('sxlager', 'LB3', 3, {
      target: function (ctx) { return s3q(ctx.doc, 'textarea[name="rawHTML"]') || s3q(ctx.doc, '.lb-foot a.st-tl[href*="mode=raw"]'); },
      label: showKey('sxlager', 'LB3'), kind: 'field'
    }),
    LB4: task('sxlager', 'LB4', 2, { target: 'button[data-open="lb-del"]', label: showKey('sxlager', 'LB4'), kind: 'button' }),
    LB5: task('sxlager', 'LB5', 2, { target: '.st-flash-err', label: showKey('sxlager', 'LB5'), kind: 'status' })
  };

  const s3Labels = {
    group: 'sxlager',
    states: ['visuel', 'raa', 'kunLaes'],
    flags: ['afvist', 'navnFejl', 'kunRaa'],

    name: function () {
      return 'help.sxlager.name_labels';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      // labels.php:64-68, :127-133: the result is the address's msg= (refused: the template changed elsewhere; exists/invalid: the
      // new label's name, with the new-label box open again)
      const msg = s3param(ctx, 'msg');
      const flags = { afvist: msg === 'refused', navnFejl: msg === 'exists' || msg === 'invalid', kunRaa: !!s3q(doc, '.st-flash-warn') };
      let state = 'unknown';
      if (s3readOnly(doc)) state = 'kunLaes';
      else if (s3q(doc, '#lb-form textarea[name="rawHTML"]')) state = 'raa';
      else if (s3q(doc, '#lb-form')) state = 'visuel';
      return { state: state, flags: flags, data: {} };
    },

    about: function (ctx, st) {
      const s = s3state(st);
      if (s === 'kunLaes') return ['help.sxlager.about.labels_kunlaes'];
      if (s === 'raa' && s3flag(st, 'kunRaa')) return ['help.sxlager.about.labels_kunraa'];
      if (s === 'raa' || s === 'visuel') return ['help.sxlager.about.labels'];
      return [];
    },

    tasks: S3_LB_TASKS,

    order: function (ctx, st) {
      const s = s3state(st);
      let out = [];
      if (s === 'visuel') out = ['LB2', 'LB1', 'LB3', 'LB4'];
      else if (s === 'raa') out = ['LB3', 'LB1', 'LB4'];
      if (s3flag(st, 'navnFejl') && (s === 'visuel' || s === 'raa')) out = unique(['LB1'].concat(out));
      if (s3flag(st, 'afvist') && (s === 'visuel' || s === 'raa')) out = ['LB5'].concat(out);
      return out;
    },

    problem: function (ctx, st) {
      const s = s3state(st);
      if (s !== 'visuel' && s !== 'raa') return '';
      if (s3flag(st, 'afvist')) return 'LB5';
      return s3flag(st, 'navnFejl') ? 'LB1' : '';
    },

    fields: {},

    shortcuts: [
      { keys: 'Alt+G', text: 'help.sxlager.key.01', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {},

    questionOrder: function () {
      return [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['systemdata/labels.php'] = s3Labels;
})();
