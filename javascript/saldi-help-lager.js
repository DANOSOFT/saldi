// ---- javascript/saldi-help-lager.js --- ver 5.1.0 --- 2026.11.09 ---
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The help panel's page knowledge for the Lager module (window.SaldiHelpContent.pages, doc/hjaelp_saldi_ai.md §3.2,
// §4, §16.1 and §24): the Lager sidebar (items, the item card, goods receipt, stock movements, counts, batches, bills of
// materials and production, stock reports) and every page reached from it in the frame.
// Loads after javascript/saldi-help-kreditor.js and builds its pages with the helpers of javascript/saldi-help-content.js
// (SaldiHelpContent.lib). Data and pure functions only. Every text the panel shows is a key in
// importfiler/tekster_keyed.tsv (help.<group>.*), fetched per language from index/helpTexts.php. Detectors and show
// targets only read the frame document: no writes, focus, clicks, network or timers. Frame elements come from another
// realm, so type checks use nodeType/tagName.
//
// 20261109 CL/ASR Created: the module file for the Lager pages, loaded like saldi-help-kreditor.js.
// 20261109 CL/ASR L1: Vareliste and the goods tabs (lager/lister/*), the item card and its stock log, Ret varenummer and
//                 alias, Varefoto, Emballage and HappyHour (groups lvar, lkort, lvx).
// 20261109 CL/ASR L2: goods receipt, stock move, stock count, Batch oversigt and Batchoplysninger, Stykliste and
//                 Vareproduktion (groups lmod, lflyt, lopt, lbat, lprod).
// 20261109 CL/ASR L3: Varerapporter, Lagerstatus, Varespor, Salg pr. postnummer, Beholdningsliste, the stock change log,
//                 Beholdningsrapport, Udløbsrapport and Prisliste (groups lrap, lstat, ludl, lpris); spec §24.5.
// 20261109 CL/ASR Review: VK40 produce a bill of materials from the card, VK4 first on a fresh card, VK6 and VK39 with
//                 the cost-price facts, LS10 post the change in stock; key texts name the confirmations' keys (spec §24.13).
// 20261109 CL/ASR Live check on ssl12 (spec §24.15): Indkøb's route is lager/lister/indk%C3%B8b.php; with FIFO the card's
//                 Aktuel is locked, so VK41 (a stock count) replaces VK39 and VK8; VK8, VK39 and VK38 ring nothing on a bill
//                 of materials; VK23 needs internal document storage; VL7 in Filtre, VL19 on Vareliste, VM3 on a line being
//                 changed, Flyt vare with one warehouse.
// 20261109 CL/ASR Page questions in the chat (spec §25): questionOrder per state for the start screen; new questions on the
//                 item card (sales price VAT, a stock change, the description, the number), Indkøb (Bestil), Flyt vare and
//                 Vareproduktion (batch details), Lageroptælling (uncounted items); Q2 on Batchoplysninger with its own steps.
// 20261115 CL/ASR Integration: master's MB-62 gives a read-only user no Gem on the item card and no form on Flyt vare, so a
//                 saved card without Gem and a move page with its heading but no Antal or Opdater are read-only (kunLaes).
(function () {
  'use strict';

  const C = window.SaldiHelpContent;
  if (!C || !C.lib || !C.pages) return;
  const task = C.lib.task, showKey = C.lib.showKey, fieldKeys = C.lib.fieldKeys, fieldLabel = C.lib.fieldLabel,
    stepKeys = C.lib.stepKeys, unique = C.lib.unique, attr = C.lib.attr, byName = C.lib.byName, rowOf = C.lib.rowOf;
  const PAGES = C.pages;

  // ==== L1: the item lists, the item card and the small pages the card opens (author L1; helpers l1)
  // The Lager sidebar's Varer opens lager/varer.php, which in the shell (USET box3 'S', includes/online.php:333-337)
  // redirects at once to the goods tab the user last used under lager/lister/ (varer.php:121-133, topLineVarer.php:6-11):
  // Vareliste, Ordrevisning, Indkøb, Serienumre, Styklister. Those five grid pages, the item card lager/varekort.php, its
  // stock log lager/productCardIncludes/stockLog.php and the pages the card links to (Ret varenummer, alias, Varefoto,
  // Emballage, HappyHour) are the routes of this section. Detectors and show targets only read the frame document.

  /** querySelector / querySelectorAll that never throw (an odd document or selector gives null / []). */
  function l1Q(root, sel) {
    try {
      const el = root && root.querySelector ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function l1Qa(root, sel) {
    try {
      return root && root.querySelectorAll ? Array.prototype.slice.call(root.querySelectorAll(sel) || []) : [];
    } catch (e) {
      return [];
    }
  }

  function l1Text(el) {
    return el && el.nodeType === 1 ? String(el.textContent || '').replace(/\s+/g, ' ').trim() : '';
  }

  function l1Value(el) {
    return el && el.nodeType === 1 ? String(el.value || '').trim() : '';
  }

  /** An attribute the page printed (readonly, disabled, checked), whatever its value. */
  function l1Has(el, name) {
    return !!(el && el.nodeType === 1 && el.getAttribute(name) !== null);
  }

  /** The saved state of a checkbox: the checked attribute the page printed, not what the user ticked since. */
  function l1Saved(el) {
    if (!el || el.nodeType !== 1) return false;
    try {
      return el.defaultChecked === true || el.getAttribute('checked') !== null;
    } catch (e) {
      return false;
    }
  }

  function l1Param(ctx, name) {
    try {
      return String(ctx.url.searchParams.get(name) || '');
    } catch (e) {
      return '';
    }
  }

  /** A Danish decimal as the cards print it ('1.234,50', '5', '-2'), else 0. */
  function l1Num(s) {
    const t = String(s || '').replace(/\./g, '').replace(',', '.').trim();
    const n = parseFloat(t);
    return isFinite(n) ? n : 0;
  }

  function l1Words(da, en, no) {
    return { da: da, en: en, no: no };
  }

  function l1State(st) {
    return st && st.state ? st.state : 'unknown';
  }

  function l1Flags(st) {
    return (st && st.flags) || {};
  }

  /** A show target that rings only in the listed states of its page, and only with the flag when one is named (spec
   *  §19.3: the chat's search offers every task in every state). Without a state the page is detected first. */
  function l1Only(page, states, flag, target) {
    return function (ctx, st) {
      let s = st && st.state ? st : null;
      if (!s) {
        try {
          s = page().detect(ctx);
        } catch (e) {
          s = null;
        }
      }
      if (!s || states.indexOf(s.state) < 0) return null;
      if (flag && !(s.flags && s.flags[flag])) return null;
      return typeof target === 'function' ? target(ctx, s) : l1Q(ctx.doc, target);
    };
  }

  // ------------------------------------------------------------------ the goods tabs (lager/lister/*.php, includes/grid.php)
  // Evidence, includes/grid.php unless named: the wrapper #datatable-wrapper-<id> in every mode (:1264 list, :1636 Kolonner,
  // :2030 Filtre); the table #datatable-<id> with one tr.filler-row after the rows (:1307); search inputs
  // search[<id>][<field>] in the second header row, whose th carry the field as class (:1385-1397); the actions arrow
  // .dropdown-trigger with Søg, Ryd søgning, Eksportér til CSV/PDF, Redigér > Kolonner/Filtre, Vis SQL (:1408-1463);
  // the footer's rowcount[<id>] with Alle (:1497, :1556); Kolonner rows[<id>][n][field] whose options are the field names
  // (:1745, :1889); Filtre filter[<id>][<group>][<option>] (:2094). A number column takes 10 or 1:10 (:128-139), a text
  // column searches for the term anywhere (:121-127). The tabs (topLineVarer.php): Vareliste #back-btn (always a link,
  // :47-60), Ordrevisning #ordrevisning (right 5, :62-78), Indkøb #indkob (right 7, :80-96), Serienumre #serial (:98-112),
  // Styklister #styklister (:114-128); the current tab has no link. Ny #create-new with accesskey L on Vareliste only
  // (:144-151). Grid ids: varelst1 or varelst (vareliste.php:637, the item-card VAT setting as suffix), ordrestat
  // (ordrestatus.php:764), indkøb (indkøb.php:804), serienr (serialnumber.php:401), styklister (styklister.php:376).

  /** The grid id on the page whose wrapper id starts with prefix, or ''. */
  function l1GridId(doc, prefix) {
    const w = l1Q(doc, '[id^="datatable-wrapper-' + prefix + '"]');
    return w ? attr(w, 'id').slice('datatable-wrapper-'.length) : '';
  }

  /** The grid's data rows (child combinators: the Ordrevisning and Indkøb cells hold hover tables of their own). */
  function l1GridRows(doc, id) {
    return id ? l1Qa(doc, '[id="datatable-' + id + '"] > tbody > tr:not(.filler-row)') : [];
  }

  function l1SearchesWithText(doc, id) {
    return id ? l1Qa(doc, 'input[name^="search[' + id + ']"]').filter(function (el) { return l1Value(el) !== ''; }) : [];
  }

  /** list, tom, kolonner or filtre of a grid page, with the search flags; unknown without the grid. */
  function l1GridDetect(ctx, prefix) {
    const doc = ctx.doc;
    const id = l1GridId(doc, prefix);
    if (!id) return { state: 'unknown', flags: {}, data: {} };
    if (l1Q(doc, '[name^="rows[' + id + ']"]')) return { state: 'kolonner', flags: {}, data: { grid: id } };
    if (l1Q(doc, '[name^="filter[' + id + ']"]')) return { state: 'filtre', flags: {}, data: { grid: id } };
    const rows = l1GridRows(doc, id).length;
    const soegning = l1SearchesWithText(doc, id).length > 0;
    const flags = { soegning: soegning, ingenRaekker: rows === 0 };
    return { state: rows || soegning ? 'liste' : 'tom', flags: flags, data: { grid: id, rows: rows } };
  }

  /** The first grid on the page of the goods tabs, or ''. */
  function l1AnyGrid(doc) {
    const prefixes = ['varelst', 'ordrestat', 'indkøb', 'serienr', 'styklister'];
    for (let i = 0; i < prefixes.length; i++) {
      const id = l1GridId(doc, prefixes[i]);
      if (id) return id;
    }
    return '';
  }

  /** A show target in the grid's list mode only (the Kolonner and Filtre forms keep the wrapper id). */
  function l1InList(target) {
    return function (ctx) {
      const id = l1AnyGrid(ctx.doc);
      if (!id || l1Q(ctx.doc, '[name^="rows[' + id + ']"], [name^="filter[' + id + ']"]')) return null;
      return target(ctx, id);
    };
  }

  const l1FirstRow = l1InList(function (ctx, id) {
    const rows = l1GridRows(ctx.doc, id);
    return rows.length ? rows[0] : null;
  });

  const l1Trigger = l1InList(function (ctx, id) {
    return l1Q(ctx.doc, '[id="datatable-wrapper-' + id + '"] .dropdown-trigger');
  });

  const l1FirstSearchWithText = l1InList(function (ctx, id) {
    const list = l1SearchesWithText(ctx.doc, id);
    return list.length ? list[0] : null;
  });

  /** The search field of one column of the grid on the page. */
  function l1SearchOf(field) {
    return l1InList(function (ctx, id) {
      return l1Q(ctx.doc, 'input[name="search[' + id + '][' + field + ']"]');
    });
  }

  /** The header cell of one column (the first th with the field as class, grid.php:1357-1364). */
  function l1HeadOf(field) {
    return l1InList(function (ctx, id) {
      return l1Q(ctx.doc, '[id="datatable-' + id + '"] th.' + field);
    });
  }

  const l1ColumnField = function (ctx) {
    return l1Q(ctx.doc, 'select[name^="rows["][name$="[field]"]');
  };

  const l1FilterBox = function (ctx) {
    return l1Q(ctx.doc, 'input[type="checkbox"][name^="filter["]');
  };

  /** A goods tab's link, or null on the tab itself (the current tab is printed without a link). */
  function l1Tab(cellId) {
    return function (ctx) {
      return l1Q(ctx.doc, '#' + cellId + ' a');
    };
  }

  /** The grid's field keys: the warehouse columns lager1, lager2 … share one, Leverandør loses its ø. */
  function l1GridFieldKey(el) {
    const n = attr(el, 'name');
    const m = /^search\[[^\]]+\]\[([^\]]+)\]$/.exec(n);
    if (!m) return null;
    if (/^lager\d+$/.test(m[1])) return 'search:lager';
    if (m[1] === 'leverandør') return 'search:leverandor';
    return null;
  }

  function l1GridAbout(prefix, ctx, st) {
    const state = l1State(st);
    const f = l1Flags(st);
    if (state === 'kolonner') return ['help.lvar.about.kolonner'];
    if (state === 'filtre') return ['help.lvar.about.filtre'];
    if (state === 'tom') return ['help.lvar.about.' + prefix + '_tom'];
    if (state !== 'liste') return [];
    if (f.soegning) return f.ingenRaekker ? ['help.lvar.about.soegning_tom'] : ['help.lvar.about.' + prefix, 'help.lvar.about.soegning'];
    return ['help.lvar.about.' + prefix];
  }

  const L1_G = 'lvar';

  const L1_SHOW_TRIGGER = { target: l1Trigger, label: showKey(L1_G, 'VL6'), kind: 'button' };

  // Tasks shared by the five tabs (same keys, same group).
  const L1_GRID_TASKS = {
    // VL9: Ryd søgning in the arrow's menu (grid.php:1430-1433), or empty fields and Enter (the GET form, :1265)
    VL9: task(L1_G, 'VL9', 2, { target: l1FirstSearchWithText, label: showKey(L1_G, 'VL9'), kind: 'field' }),
    // VL16: the empty line at the bottom under Felt (grid.php:1883-1893), then VL6's Gem and Luk
    VL16: task(L1_G, 'VL16', 2, { target: l1ColumnField, label: showKey(L1_G, 'VL16'), kind: 'field' }, stepKeys(L1_G, 'VL6', 3, 3)),
    VL17: task(L1_G, 'VL17', 1, { target: l1FilterBox, label: showKey(L1_G, 'VL17'), kind: 'field' }, stepKeys(L1_G, 'VL6', 3, 3)),
    // VL18: the default text search on Varenr. and Navn (no generateSearch on ordrestatus.php:64-86, indkøb.php:174-196)
    VL18: task(L1_G, 'VL18', 1, { target: l1SearchOf('varenr'), label: fieldLabel(L1_G, 'search:varenr'), kind: 'field' }, stepKeys(L1_G, 'VL2', 3, 3)),
    // VL19: an empty tab other than Vareliste: Ny is on the Vareliste tab only (topLineVarer.php:144-151)
    VL19: task(L1_G, 'VL19', 1, { target: l1Tab('back-btn'), label: showKey(L1_G, 'VL19'), kind: 'button' }, stepKeys(L1_G, 'VL1', 1, 3))
  };

  const L1_GRID_ORDER_TAIL = function (state) {
    if (state === 'kolonner') return ['VL16'];
    if (state === 'filtre') return ['VL17'];
    return [];
  };

  // ---- Vareliste (lager/lister/vareliste.php)
  // Evidence, lager/lister/vareliste.php: the columns (:86-433) Varenr. (search in varenr, alias and barcode, every word
  // must match, :96-108), Navn (and its alias, :148-160), Varemærke, Varegruppe, Stregkode, Leverandør (links to the
  // kreditorkort, :226-243), Lev. varenr., Enhed, Kategorier, one column per warehouse (grupper LG) with a link to
  // lagerflyt.php when the stock is not 0 (:296-337), I alt (empty for a bill of materials or an item without a stock
  // row, :340-360), Købt and Solgt (hidden, :362-381), Salgspris with (ekskl. moms) or (inkl. moms) by the setting
  // (:384-405), Kostpris, DG (:408-433); hidden by default: alias columns, Momssats, Bemærkning, Intern note, Købt, Solgt.
  // The filters keep their Danish names (:437-438): Varegrupper, Leverandøre, Misc > Vis udgået (checked by default,
  // :496-511). A closed row is red only when lukket is '1' (:605-612). Webshop: Opdater webshopbeholdning is added to the
  // arrow's menu with the webshop right (:639-652). Every cell of Varenr. and Navn opens the card (:90-93, :142-145).

  function l1VlGrid(ctx) {
    return l1GridId(ctx.doc, 'varelst');
  }

  const L1_VL_TASKS = Object.assign({}, L1_GRID_TASKS, {
    VL1: task(L1_G, 'VL1', 3, { target: '#create-new a', label: showKey(L1_G, 'VL1'), kind: 'button' }),
    VL2: task(L1_G, 'VL2', 3, { target: l1SearchOf('varenr'), label: fieldLabel(L1_G, 'search:varenr'), kind: 'field' }),
    VL3: task(L1_G, 'VL3', 2, { target: l1FirstRow, label: showKey(L1_G, 'VL3'), kind: 'row' }),
    VL4: task(L1_G, 'VL4', 3, { target: l1HeadOf('lager_total'), label: showKey(L1_G, 'VL4'), kind: 'status' }),
    // VL5: a warehouse column's figure links to lagerflyt.php (vareliste.php:329-332; lagerflyt.php:216-233)
    // with one warehouse there is nowhere to move to (lagerflyt.php:219-224), so VL5 rings only with several
    VL5: task(L1_G, 'VL5', 2, {
      target: l1Only(function () { return l1Vareliste; }, ['liste'], 'flereLagre', function (ctx) { return l1Q(ctx.doc, 'a[href*="lagerflyt.php"]'); }),
      label: showKey(L1_G, 'VL5'),
      kind: 'button'
    }),
    VL6: task(L1_G, 'VL6', 3, L1_SHOW_TRIGGER),
    VL7: task(L1_G, 'VL7', 2, L1_SHOW_TRIGGER, stepKeys(L1_G, 'VL6', 3, 3)),
    VL8: task(L1_G, 'VL8', 3, L1_SHOW_TRIGGER),
    // VL10: Indstillinger > Finans > Data og sikkerhedskopi > Import og eksport > Importér varer (settingsDefinitions.php:1056,
    // systemdata/importer_varer.php:191-193 Vælg datafil/Hent, :343 Vis, :364 Importer once a column is the item number)
    VL10: task(L1_G, 'VL10', 4),
    VL11: task(L1_G, 'VL11', 3, { target: l1Tab('indkob'), label: showKey(L1_G, 'VL11'), kind: 'button' }),
    VL12: task(L1_G, 'VL12', 2, { target: l1Tab('ordrevisning'), label: showKey(L1_G, 'VL12'), kind: 'button' }),
    VL13: task(L1_G, 'VL13', 2, { target: l1Tab('serial'), label: showKey(L1_G, 'VL13'), kind: 'button' }),
    // VL14: Lager > Rapporter > Lagerstatus (lager/rapport.php:453-455) with Dato and Samlet lagerværdi pr. (lagerstatus.php:432, :851)
    VL14: task(L1_G, 'VL14', 3),
    // VL15: Indstillinger > Køb og lager > Varer > Varegrupper (includes/settings/hub.php:204-206; settingsDefinitions.php:430-455)
    VL15: task(L1_G, 'VL15', 3),
    // VL19 is for the other tabs; on Vareliste its Vareliste tab is the page itself, and Ny is VL1's (live check 24.15)
    VL19: task(L1_G, 'VL19', 1, null, stepKeys(L1_G, 'VL1', 1, 3)),
    // VL7: the arrow in the list, the Vis udgået box (filter[<id>][misc][show_discontinued], vareliste.php:496-511) in Filtre
    VL7: task(L1_G, 'VL7', 2, {
      target: function (ctx) {
        const id = l1AnyGrid(ctx.doc);
        const box = id ? l1Q(ctx.doc, 'input[name="filter[' + id + '][misc][show_discontinued]"]:not([type="hidden"])') : null;
        return box || l1Trigger(ctx);
      },
      label: showKey(L1_G, 'VL6'),
      kind: 'button'
    }, stepKeys(L1_G, 'VL6', 3, 3))
  });

  const L1_VL_FIELDS = {};
  ['search:varenr', 'search:beskrivelse', 'search:varegruppe', 'search:stregkode', 'search:leverandor', 'search:kategorier',
    'search:lager', 'search:lager_total', 'search:dg', 'rowcount'].forEach(function (k) { L1_VL_FIELDS[k] = fieldKeys(L1_G, k); });

  const l1Vareliste = {
    group: L1_G,
    states: ['liste', 'tom', 'kolonner', 'filtre'],
    flags: ['soegning', 'ingenRaekker', 'flereLagre'],

    name: function () {
      return 'help.lvar.name';
    },

    detect: function (ctx) {
      const st = l1GridDetect(ctx, 'varelst');
      if (st.state === 'liste' || st.state === 'tom') {
        // one header cell per warehouse column (lager1, lager2, …; vareliste.php:310-337)
        const id = st.data.grid;
        const heads = l1Qa(ctx.doc, '[id="datatable-' + id + '"] th[class^="lager"]').filter(function (th) {
          return /^lager\d+(\s|$)/.test(attr(th, 'class')) && !l1Q(th, 'input');
        });
        st.flags.flereLagre = heads.length > 1;
      }
      return st;
    },

    about: function (ctx, st) {
      return l1GridAbout('vareliste', ctx, st);
    },

    tasks: L1_VL_TASKS,

    order: function (ctx, st) {
      const state = l1State(st);
      const f = l1Flags(st);
      let ids = [];
      if (state === 'tom') ids = ['VL15', 'VL1', 'VL10'];
      else if (state === 'liste') {
        ids = [f.soegning && f.ingenRaekker ? 'VL9' : '', 'VL2', f.ingenRaekker ? '' : 'VL3', 'VL1', f.soegning ? 'VL9' : '', 'VL4', 'VL11',
          f.flereLagre ? 'VL5' : '', 'VL6', 'VL7', 'VL8', 'VL14', 'VL12', 'VL13', 'VL10', 'VL15'];
      } else ids = L1_GRID_ORDER_TAIL(state).concat(state === 'filtre' ? ['VL7'] : []);
      return unique(ids.filter(function (id) { return id !== ''; }));
    },

    fields: L1_VL_FIELDS,

    fieldKey: l1GridFieldKey,

    // Enter in a search field submits the grid's GET form (grid.php:1265); Ny carries accesskey L on this tab
    // (topLineVarer.php:146), so Alt+L opens a new item card here.
    shortcuts: [
      { keys: 'Enter', text: 'help.lvar.key.01', group: 'keys' },
      { keys: 'Alt+L', text: 'help.lvar.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.lvar.q.q1.q',
        words: l1Words(['kan ikke se', 'mangler', 'væk', 'forsvundet', 'skjult', 'udgået', 'lukket', 'filter', 'søgning', 'finde vare'],
          ['cannot see', 'missing', 'gone', 'disappeared', 'hidden', 'discontinued', 'closed', 'filter', 'search', 'find item'],
          ['kan ikke se', 'mangler', 'borte', 'forsvunnet', 'skjult', 'utgått', 'lukket', 'filter', 'søk', 'finne vare']),
        steps: ['help.lvar.q.q1.step1', 'help.lvar.q.q1.step2', 'help.lvar.q.q1.step3'],
        show: L1_VL_TASKS.VL2.show
      },
      Q2: {
        q: 'help.lvar.q.q2.q',
        words: l1Words(['beholdning', 'lager', 'tom', 'blank', 'nul', 'antal', 'lagerført', 'ydelse'],
          ['stock', 'inventory', 'empty', 'blank', 'zero', 'quantity', 'stock item', 'service'],
          ['beholdning', 'lager', 'tom', 'blank', 'null', 'antall', 'lagerført', 'tjeneste']),
        steps: ['help.lvar.q.q2.step1', 'help.lvar.q.q2.step2'],
        show: L1_VL_TASKS.VL4.show
      },
      Q3: {
        q: 'help.lvar.q.q3.q',
        words: l1Words(['moms', 'pris', 'salgspris', 'inkl moms', 'ekskl moms', 'uden moms', 'med moms'],
          ['vat', 'price', 'sales price', 'incl vat', 'excl vat', 'without vat', 'with vat'],
          ['mva', 'pris', 'salgspris', 'inkl mva', 'ekskl mva', 'uten mva', 'med mva']),
        steps: ['help.lvar.q.q3.step1', 'help.lvar.q.q3.step2'],
        show: { target: l1HeadOf('salgspris'), label: 'help.lvar.q.q3.show', kind: 'status' }
      }
    },

    // Q1 first also on an empty list: a filter on Varegrupper or Leverandøre can hide every item (vareliste.php:446-492).
    // In Kolonner the price columns salgspris and momspris are chosen (:384-405), so Q3 leads there.
    questionOrder: function (ctx, st) {
      return l1State(st) === 'kolonner' ? ['Q3', 'Q1', 'Q2'] : ['Q1', 'Q2', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/lister/vareliste.php'] = l1Vareliste;

  // ---- Ordrevisning (lager/lister/ordrestatus.php)
  // Evidence, lager/lister/ordrestatus.php: Tilbud, Ordre, Indkøbsforslag, Indkøbsordre per item with a hover list of the
  // orders (:152-228; the purchase order's date is the expected delivery date, :216-226), the warehouse columns and I alt,
  // Min., Maks., Vol. (the packing quantity), Gen. (sorted first, :353-381: when stock minus sales orders plus purchase
  // suggestions and purchase orders is below Min., the quantity up to Maks., rounded up to Vol.), 6md., 3md., 1md. (sales,
  // :383-410), Salgspris u.m., Salgspris, DG, Kostpris. Only the varenr cell links to the card (:64-75).

  const L1_OV_TASKS = Object.assign({}, L1_GRID_TASKS, {
    OV1: task(L1_G, 'OV1', 2, { target: l1HeadOf('in_sales_order'), label: showKey(L1_G, 'OV1'), kind: 'status' }),
    OV2: task(L1_G, 'OV2', 3, { target: l1HeadOf('genbestil'), label: showKey(L1_G, 'OV2'), kind: 'status' }),
    OV3: task(L1_G, 'OV3', 2, { target: l1HeadOf('sales_last_6_months'), label: showKey(L1_G, 'OV3'), kind: 'status' }),
    OV4: task(L1_G, 'OV4', 0, { target: l1Tab('indkob'), label: showKey(L1_G, 'VL11'), kind: 'button' }, stepKeys(L1_G, 'VL11', 1, 2)),
    VL3: task(L1_G, 'VL3', 2, { target: l1FirstRow, label: showKey(L1_G, 'VL3'), kind: 'row' })
  });

  const L1_OV_FIELDS = {};
  ['search:varenr', 'search:beskrivelse', 'search:genbestil', 'rowcount'].forEach(function (k) { L1_OV_FIELDS[k] = fieldKeys(L1_G, k); });

  const l1Ordrestatus = {
    group: L1_G,
    states: ['liste', 'tom', 'kolonner', 'filtre'],
    flags: ['soegning', 'ingenRaekker'],

    name: function () {
      return 'help.lvar.name_ordre';
    },

    detect: function (ctx) {
      return l1GridDetect(ctx, 'ordrestat');
    },

    about: function (ctx, st) {
      return l1GridAbout('ordre', ctx, st);
    },

    tasks: L1_OV_TASKS,

    order: function (ctx, st) {
      const state = l1State(st);
      const f = l1Flags(st);
      if (state === 'liste') {
        return unique([f.soegning && f.ingenRaekker ? 'VL9' : '', 'OV1', 'OV2', 'OV4', 'VL18', 'OV3', f.soegning ? 'VL9' : '', 'VL3']
          .filter(function (id) { return id !== ''; }));
      }
      if (state === 'tom') return ['VL19'];
      return L1_GRID_ORDER_TAIL(state);
    },

    fields: L1_OV_FIELDS,

    fieldKey: l1GridFieldKey,

    shortcuts: [
      { keys: 'Enter', text: 'help.lvar.key.01', group: 'keys' }
    ],

    questions: {
      Q4: {
        q: 'help.lvar.q.q4.q',
        words: l1Words(['gen', 'genbestil', 'genbestilling', 'forslag', 'bestille', 'min', 'maks', 'minimum', 'maksimum'],
          ['reorder', 'suggestion', 'order', 'min', 'max', 'minimum', 'maximum'],
          ['gjen', 'gjenbestill', 'forslag', 'bestille', 'min', 'maks', 'minimum', 'maksimum']),
        intro: 'help.lvar.task.ov2.why',
        steps: stepKeys(L1_G, 'OV2', 1, 2),
        show: L1_OV_TASKS.OV2.show
      }
    },

    questionOrder: function (ctx, st) {
      return l1State(st) === 'liste' || l1State(st) === 'tom' ? ['Q4'] : [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/lister/ordrestatus.php'] = l1Ordrestatus;

  // ---- Indkøb (lager/lister/indkøb.php; the frame's path is percent-encoded, varer.php:129 rawurlencode('indkøb'))
  // Evidence, lager/lister/indkøb.php: Autoudfyld #autoudfyldBtn fills each line's field with Gen. (:69-72, :82-93), Bestil
  // #bestilBtn posts each filled line to lager/orderapi.php (:74-77, :94-166), which puts the item on today's open purchase
  // suggestion to the item's first supplier or creates one with status 0 (orderapi.php:47-89, :125-132, :284-309). The
  // list holds only items with a supplier, not bills of materials, in a stock-managed item group (:762). The quantity
  // field per line .genbestil-inp id genb-<id> (:788-797). Kreditorer > Ordre, tab Forslag, Godkend (help.kol.task.kol6).

  const L1_IK_TASKS = Object.assign({}, L1_GRID_TASKS, {
    IK1: task(L1_G, 'IK1', 3, { target: '#bestilBtn', label: showKey(L1_G, 'IK1'), kind: 'button' }),
    IK2: task(L1_G, 'IK2', 2, { target: '#autoudfyldBtn', label: showKey(L1_G, 'IK2'), kind: 'button' }),
    IK3: task(L1_G, 'IK3', 3, { target: l1FirstRow, label: showKey(L1_G, 'VL3'), kind: 'row' }),
    IK4: task(L1_G, 'IK4', 3)
  });

  const L1_IK_FIELDS = {};
  ['search:varenr', 'search:beskrivelse', 'search:genbestil', 'bestil_antal', 'rowcount'].forEach(function (k) { L1_IK_FIELDS[k] = fieldKeys(L1_G, k); });

  const l1Indkob = {
    group: L1_G,
    states: ['liste', 'tom', 'kolonner', 'filtre'],
    flags: ['soegning', 'ingenRaekker'],

    name: function () {
      return 'help.lvar.name_indkob';
    },

    detect: function (ctx) {
      return l1GridDetect(ctx, 'indkøb');
    },

    about: function (ctx, st) {
      return l1GridAbout('indkob', ctx, st);
    },

    tasks: L1_IK_TASKS,

    order: function (ctx, st) {
      const state = l1State(st);
      const f = l1Flags(st);
      if (state === 'liste') {
        return unique([f.soegning && f.ingenRaekker ? 'VL9' : '', 'IK1', 'IK2', 'IK3', 'VL18', f.soegning ? 'VL9' : '', 'IK4']
          .filter(function (id) { return id !== ''; }));
      }
      if (state === 'tom') return ['IK4'];
      return L1_GRID_ORDER_TAIL(state);
    },

    fields: L1_IK_FIELDS,

    // The quantity field of a line has no name, only id genb-<item id> (indkøb.php:789-797).
    fieldKey: function (el) {
      if (/^genb-\d+$/.test(attr(el, 'id'))) return 'bestil_antal';
      return l1GridFieldKey(el);
    },

    shortcuts: [
      { keys: 'Enter', text: 'help.lvar.key.01', group: 'keys' }
    ],

    questions: {
      Q5: {
        q: 'help.lvar.q.q5.q',
        words: l1Words(['mangler', 'ikke med', 'kan ikke se', 'vare', 'indkøb', 'leverandør', 'lagerført', 'stykliste'],
          ['missing', 'not listed', 'cannot see', 'item', 'purchasing', 'supplier', 'stock item', 'bill of materials'],
          ['mangler', 'ikke med', 'kan ikke se', 'vare', 'innkjøp', 'leverandør', 'lagerført', 'stykkliste']),
        steps: stepKeys(L1_G, 'IK4', 1, 3)
      },
      Q4: {
        q: 'help.lvar.q.q4.q',
        words: l1Words(['gen', 'genbestil', 'forslag', 'hvor mange', 'min', 'maks'],
          ['reorder', 'suggestion', 'how many', 'min', 'max'],
          ['gjen', 'gjenbestill', 'forslag', 'hvor mange', 'min', 'maks']),
        intro: 'help.lvar.task.ov2.why',
        steps: stepKeys(L1_G, 'OV2', 1, 2),
        show: { target: l1HeadOf('genbestil'), label: showKey(L1_G, 'OV2'), kind: 'status' }
      },
      // Q8: Bestil only writes a purchase suggestion (status 0) to the supplier with the lowest posnr, today's or a new one
      // (orderapi.php:76-77, :83-89, :125-132, :284-309); nothing in orderapi.php mails or sends it.
      Q8: {
        q: 'help.lvar.q.q8.q',
        words: l1Words(['bestil', 'sendt', 'sende', 'sendes', 'leverandør', 'indkøbsforslag', 'forslag', 'godkend', 'købsordre'],
          ['order', 'sent', 'send', 'supplier', 'purchase suggestion', 'suggestion', 'approve', 'purchase order'],
          ['bestill', 'sendt', 'sende', 'sendes', 'leverandør', 'innkjøpsforslag', 'forslag', 'godkjenn', 'innkjøpsordre']),
        intro: 'help.lvar.q.q8.intro',
        steps: stepKeys(L1_G, 'IK1', 3, 3),
        show: L1_IK_TASKS.IK1.show
      }
    },

    // Q5 is what support hears most on the list; Q8 is the worry before Bestil; Q4 explains Gen.
    questionOrder: function (ctx, st) {
      const state = l1State(st);
      if (state === 'tom') return ['Q5', 'Q4', 'Q8'];
      return state === 'liste' ? ['Q5', 'Q8', 'Q4'] : [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/lister/indk%C3%B8b.php'] = l1Indkob;

  // ---- Serienumre (lager/lister/serialnumber.php)
  // Evidence, lager/lister/serialnumber.php: Varenr. (links to the card), Varenavn, Stregkode, Serienummer, Leverandør and
  // Ordre (the purchase), Køber and Ordre (the sale) (:103-232); an unsold serial number is grey (:327-333); with the right
  // (rettigheder position 1) an unsold one has a pencil (prompt Omdøb) and a bin (confirm Slet) in its last cell (:336-396);
  // the filter Misc keeps its Danish options Vis tomme serienr værdier and Vis kun serienumre der ikke er solgt (:260-283).
  // The item card's Serienr. box is printed for a stock-managed item only (productCardIncludes/notesEtc.php:41-44).

  const L1_SN_TASKS = Object.assign({}, L1_GRID_TASKS, {
    SN1: task(L1_G, 'SN1', 2, { target: l1SearchOf('serienr'), label: fieldLabel(L1_G, 'search:serienr'), kind: 'field' }),
    SN2: task(L1_G, 'SN2', 0, L1_SHOW_TRIGGER, stepKeys(L1_G, 'VL7', 1, 1).concat(['help.lvar.task.sn2.step2'], stepKeys(L1_G, 'VL6', 3, 3))),
    SN3: task(L1_G, 'SN3', 2, {
      target: l1InList(function (ctx) { return l1Q(ctx.doc, 'svg[onclick*="rename"]'); }),
      label: showKey(L1_G, 'SN3'),
      kind: 'button'
    }),
    SN4: task(L1_G, 'SN4', 3)
  });

  const L1_SN_FIELDS = {};
  ['search:serienr', 'search:varenr', 'rowcount'].forEach(function (k) { L1_SN_FIELDS[k] = fieldKeys(L1_G, k); });

  const l1Serienr = {
    group: L1_G,
    states: ['liste', 'tom', 'kolonner', 'filtre'],
    flags: ['soegning', 'ingenRaekker'],

    name: function () {
      return 'help.lvar.name_serienr';
    },

    detect: function (ctx) {
      return l1GridDetect(ctx, 'serienr');
    },

    about: function (ctx, st) {
      return l1GridAbout('serienr', ctx, st);
    },

    tasks: L1_SN_TASKS,

    order: function (ctx, st) {
      const state = l1State(st);
      const f = l1Flags(st);
      if (state === 'liste') {
        return unique([f.soegning && f.ingenRaekker ? 'VL9' : '', 'SN1', 'SN2', 'SN3', f.soegning ? 'VL9' : '', 'SN4']
          .filter(function (id) { return id !== ''; }));
      }
      if (state === 'tom') return ['SN4'];
      return L1_GRID_ORDER_TAIL(state).concat(state === 'filtre' ? ['SN2'] : []);
    },

    fields: L1_SN_FIELDS,

    fieldKey: l1GridFieldKey,

    shortcuts: [
      { keys: 'Enter', text: 'help.lvar.key.01', group: 'keys' }
    ],

    questions: {
      Q6: {
        q: 'help.lvar.q.q6.q',
        words: l1Words(['grå', 'gråt', 'farve', 'ikke solgt', 'usolgt', 'solgt'],
          ['grey', 'gray', 'colour', 'not sold', 'unsold', 'sold'],
          ['grå', 'farge', 'ikke solgt', 'usolgt', 'solgt']),
        steps: ['help.lvar.q.q6.step1']
      }
    },

    questionOrder: function (ctx, st) {
      return l1State(st) === 'liste' ? ['Q6'] : [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/lister/serialnumber.php'] = l1Serienr;

  // ---- Styklister (lager/lister/styklister.php)
  // Evidence, lager/lister/styklister.php: the item list filtered to bills of materials (samlevare 'on', :359), with the
  // Danish headers Vare Nr., Navn, Varemærke … printed as text (:56-240); every Vare Nr. and Navn cell opens the card
  // (:59-62, :107-110). A bill of materials is made on the item card (Samlevare, Vareopslag; varekort.php:2010-2035,
  // :2090-2092, notesEtc.php:45-52).

  const L1_ST_TASKS = Object.assign({}, L1_GRID_TASKS, {
    ST1: task(L1_G, 'ST1', 2, { target: l1FirstRow, label: showKey(L1_G, 'ST1'), kind: 'row' }),
    ST2: task(L1_G, 'ST2', 3, { target: l1Tab('back-btn'), label: showKey(L1_G, 'ST2'), kind: 'button' }),
    ST3: task(L1_G, 'ST3', 1, { target: l1SearchOf('varenr'), label: showKey(L1_G, 'ST3'), kind: 'field' })
  });

  const l1Styklister = {
    group: L1_G,
    states: ['liste', 'tom', 'kolonner', 'filtre'],
    flags: ['soegning', 'ingenRaekker'],

    name: function () {
      return 'help.lvar.name_stykliste';
    },

    detect: function (ctx) {
      return l1GridDetect(ctx, 'styklister');
    },

    about: function (ctx, st) {
      return l1GridAbout('stykliste', ctx, st);
    },

    tasks: L1_ST_TASKS,

    order: function (ctx, st) {
      const state = l1State(st);
      const f = l1Flags(st);
      if (state === 'liste') {
        return unique([f.soegning && f.ingenRaekker ? 'VL9' : '', 'ST1', 'ST3', 'ST2', f.soegning ? 'VL9' : '']
          .filter(function (id) { return id !== ''; }));
      }
      if (state === 'tom') return ['ST2'];
      return L1_GRID_ORDER_TAIL(state);
    },

    fields: { rowcount: fieldKeys(L1_G, 'rowcount') },

    shortcuts: [
      { keys: 'Enter', text: 'help.lvar.key.01', group: 'keys' }
    ],

    questions: {
      Q7: {
        q: 'help.lvar.q.q7.q',
        words: l1Words(['kostpris', 'pris', 'stykliste', 'samlevare', 'beregnet', 'dele'],
          ['cost price', 'price', 'bill of materials', 'assembled item', 'calculated', 'parts'],
          ['kostpris', 'pris', 'stykkliste', 'samlevare', 'beregnet', 'deler']),
        steps: ['help.lvar.q.q7.step1']
      }
    },

    questionOrder: function (ctx, st) {
      return l1State(st) === 'liste' ? ['Q7'] : [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/lister/styklister.php'] = l1Styklister;

  // ---- Varekort (lager/varekort.php)
  // Evidence, lager/varekort.php unless named. Reached from a list row (vareliste.php:90-93), Ny (topLineVarer.php:144-151,
  // varekort.php:1352-1366), a purchase order's Ret (kreditor/ordre.php:1679, :1707) and the order item lookups.
  // Head in the shell: Tilbage a[accesskey=L] (or Luk with opener varer.php, :1318-1346), POS menuer accesskey B with a
  // POS (:1356-1360), Ny accesskey N on a saved card (:1362-1366). Write access: rettigheder[9] '2' ($noEdit, :231) leaves
  // out Gem and the writing buttons (MB-62, :2193-2232), and every write is refused for it (:273-283, :305-311, :404-410).
  // - New card (id 0, no varenr): only Varenr. input[name=varenr] (pattern a-z A-Z 0-9 . + - _), Gem (:1752-1760). Gem
  //   with a number in use opens that item (:911-915); without a number: alert (:939). A new item gets no group, so the
  //   card shows group 1 (showGroups.php:5-6) and saves it on the next Gem.
  // - Saved card (:1761-2061): Varenr. link to ret_varenr.php and the alias link (Varenr alias / Tilføj varenr alias,
  //   ret_varenralias.php) with the purchase right, and Bestil (field #bestil-antal, button onclick bestil(), orderapi.php)
  //   when the item has a supplier, else both disabled (:1695-1745). showInfo.php: Beskrivelse beskrivelse0 (:3-8),
  //   Tilknyt billede / the photo to varefoto.php with internal document storage (:10-23), Beskrivelse Alias, languages
  //   beskrivelse[n], Varemærke trademark. Stregkode stregkode, or the variants' table (:1770-1780). Sections #pcSec*:
  //   Priser (showPrices.php: Salgspris salgspris, readonly with a price group, (incl Moms) beside it when the item card
  //   shows VAT (varekort.php:1539-1559, the save takes the VAT out again :762-771); B2B Salgspris tier_price, used for a
  //   debtor group with B2B (includes/ordrefunc.php:4009-4012, :4104-4105); Vejl.pris retail_price; Kostpris kostpris[0]
  //   #costPrice, readonly with a price group, for a bill of materials and with FIFO and stock (:62-65), then % dg (:89-93);
  //   Vises på prisliste; Automatisk prisberegning), Tilbud (showDiscounts.php: special_price, Startdato, Slutdato, Tid
  //   start, Tid slut, Avanceret to happyhour.php), Enheder (showUnits.php), Grupper (showGroups.php: Varegrupper
  //   ny_gruppe with the saved group first; no other choice with stock and batch control, only stock groups with stock,
  //   only other groups for a bill of materials, :14-25; Prisgruppe, Tilbudsgruppe, Rabatgruppe), Mængderabatter,
  //   Diverse (showLocations.php: Lokation, Følgevare; for a stock-managed item Beholdning Min min_lager / Max max_lager,
  //   Pakningsmængde volume_lager, Aktuel ny_lagerbeh[n] per warehouse or ny_beholdning, readonly with FIFO or variants,
  //   the Log link, Købs ordrer and Salgs ordrer; hidden stockItem :105), Kategorier, Varianter (useVariants.php:
  //   var_type[n] per variant from Indstillinger, one barcode var_type_stregk), Udløbsdato (showExpirySettings.php, only
  //   with batch and expiry handling on and batch control on the group, varekort.php:1862-1871: has_due_date,
  //   default_shelf_life_days, Batch oversigt), notes (notesEtc.php: Bemærkning notes and its order-line box, Intern note
  //   notesInternal, Serienr. for a stock item, Samlevare locked with stock, suppliers or variants, Udgået lukket).
  //   Suppliers (Pos. lev_pos, '-' removes; Varenr. lev_varenr; Indkøbspris kostpris[n], :1884-1942). Bill of materials
  //   (samlevare on): the Stykliste link, rows be_af_pos / be_af_ant ('-' or 0 removes, :1140-1153), Kostpris i alt
  //   (:2008-2035); Indgår i for a part (delvare, :2038-2059).
  // - Buttons (:2085-2113): Gem saveItem accesskey g; Kopier copy accesskey k (the copy is the number with _copy, :1062-1080);
  //   for a bill of materials Vareopslag (name submit, disabled with stock); else Leverandøropslag supplierLookUp accesskey
  //   l and Lagerhistorik stockHistory accesskey o (to productCardIncludes/stockLog.php); Slet deleteItem only without order
  //   lines, suppliers or bill-of-materials rows (:2101-2108); Emballage with the packaging module (:2109-2113). The gear
  //   #pcVisToggle hides sections per user and browser (class pcVisHidden, productCardIncludes/fieldVisibility.php:195-283).
  // - A stock change and Gem: lagerreguler() at the cost price, quantities only, no finance posting (includes/std_func.php:
  //   1666-1773); with Bekræft ved ændring af beholdning the page form confirmStockChange asks Årsag til ændring (reason)
  //   and Initialer (initials), OK acceptStockChange accesskey o, Afbryd cancelStockChange accesskey a, and writes stocklog
  //   (varekort.php:1238-1240, :323-351, confirmStockChange.php:18-69). With Bekræft ændring af beskrivelse a changed
  //   description asks Ja / Nej (ChangeDescription, accesskey j / n; changeDescription.php:3-21, varekort.php:1283-1289).
  // - Leverandøropslag shows the suppliers with Kontonr links (konto_id=, which adds the supplier, :286-288) and Ny
  //   (kreditorkort.php in a new window) (:2296-2378); Vareopslag lists items with links vare_id= that add a part
  //   (includes/vareopslag.php:41-103, varekort.php:289-295).

  const L1_K = 'lkort';

  function l1CardPage() {
    return l1Card;
  }

  /** The Aktuel stock field that can be typed in: per warehouse, else the single one, never the readonly copy. */
  function l1StockField(ctx) {
    const list = l1Qa(ctx.doc, 'input[name^="ny_lagerbeh["], input[name="ny_beholdning"]').filter(function (el) {
      return attr(el, 'type') !== 'hidden' && !l1Has(el, 'readonly');
    });
    return list.length ? list[0] : null;
  }

  /** The Aktuel field the card prints readonly: with FIFO for an item that is not a bill of materials, or with variants
   *  (showLocations.php:190-195, :233-236). Only when no field can be typed in. */
  function l1LockedStockField(ctx) {
    if (l1StockField(ctx)) return null;
    const list = l1Qa(ctx.doc, 'input[name="ny_beholdning"]').filter(function (el) {
      return attr(el, 'type') !== 'hidden' && l1Has(el, 'readonly');
    });
    return list.length ? list[0] : null;
  }

  /** The first option of the item group select: the saved group, printed with its name (showGroups.php:15). */
  function l1GroupUnknown(doc) {
    const o = l1Q(doc, 'select[name="ny_gruppe"] option');
    return !!o && /^\s*\d*\s*$/.test(l1Text(o));
  }

  /** The typed Aktuel of an item that is not a bill of materials (the bill's own Aktuel is VK40's). */
  function l1PlainStockField(ctx, s) {
    return s && s.flags && s.flags.stykliste ? null : l1StockField(ctx);
  }

  const L1_K_GROUP = { target: 'select[name="ny_gruppe"]', label: fieldLabel(L1_K, 'ny_gruppe'), kind: 'field' };
  const L1_K_GEAR = { target: '#pcVisToggle', label: showKey(L1_K, 'VK24'), kind: 'button' };

  const L1_K_TASKS = {
    VK1: task(L1_K, 'VK1', 3, { target: l1Only(l1CardPage, ['ny'], '', 'input[name="varenr"]:not([type="hidden"])'), label: fieldLabel(L1_K, 'varenr'), kind: 'field' }),
    VK2: task(L1_K, 'VK2', 3),
    VK3: task(L1_K, 'VK3', 2, { target: 'input[name="beskrivelse0"]', label: fieldLabel(L1_K, 'beskrivelse'), kind: 'field' }),
    VK4: task(L1_K, 'VK4', 3, L1_K_GROUP),
    VK5: task(L1_K, 'VK5', 3, { target: 'input[name="salgspris"]', label: fieldLabel(L1_K, 'salgspris'), kind: 'field' }),
    // VK6 step 3: Aut. regulering af kostpriser (DIV/5 box6) rewrites the card's cost price when a purchase order is posted
    // (kreditor/bogfor.php:86-87, :254-262), without locking the field.
    VK6: task(L1_K, 'VK6', 3, { target: '#costPrice', label: fieldLabel(L1_K, 'kostpris'), kind: 'field' }),
    VK7: task(L1_K, 'VK7', 3, { target: 'input[name="supplierLookUp"]', label: showKey(L1_K, 'VK7'), kind: 'button' }),
    // VK8, VK39: not on a bill of materials, where a changed Aktuel produces the item (VK40, live check 24.15)
    VK8: task(L1_K, 'VK8', 3, { target: l1Only(l1CardPage, ['kort'], 'lagerfoert', l1PlainStockField), label: fieldLabel(L1_K, 'ny_lagerbeh'), kind: 'field' }),
    // VK39: the new stock is valued at the card's cost price (lagerreguler() batch_kob pris, includes/std_func.php:1720-1724).
    // With the stock confirmation the save that carried the cost price ends before its commit (confirmStockChange.php:69,
    // varekort.php:1238-1240 against :1267) and OK values the stock at the saved kostpris (:329-331), so step 1 saves the
    // cost price first. Step 4: nothing is posted (lagerreguler() writes batches only).
    VK39: task(L1_K, 'VK39', 4, { target: l1Only(l1CardPage, ['kort'], 'lagerfoert', l1PlainStockField), label: fieldLabel(L1_K, 'ny_lagerbeh'), kind: 'field' }),
    // VK41: with FIFO (Anvend FIFO på lagervarer, DIV/3 box6) Aktuel is readonly for every item but a bill of materials
    // (showLocations.php:190, :233), so the stock is set by a stock count: Lageroptælling (rapport.php:463) adds batches at
    // the card's kostpris (optalling.php:732-766, :850), which can be typed while the stock is 0 (showPrices.php:76). Vis
    // mig rings the locked field the title names.
    VK41: task(L1_K, 'VK41', 3, { target: l1Only(l1CardPage, ['kort'], 'aktuelLaast', l1LockedStockField), label: fieldLabel(L1_K, 'ny_lagerbeh'), kind: 'field' }),
    // VK40: a bill of materials in a stock-kept group: a changed Aktuel redirects to vareproduktion.php (varekort.php:1245-1248),
    // which takes the parts from the same warehouse and adds the item at its cost price without a posting
    // (vareproduktion.php:80-158, :162-183 never runs). The field is editable for a bill of materials also with FIFO
    // (showLocations.php:190, :233); without a stock-kept group there is no Aktuel, so the target is null.
    VK40: task(L1_K, 'VK40', 3, { target: l1Only(l1CardPage, ['kort'], 'stykliste', l1StockField), label: fieldLabel(L1_K, 'ny_lagerbeh'), kind: 'field' }),
    VK9: task(L1_K, 'VK9', 2, { target: 'input[name="stockHistory"]', label: showKey(L1_K, 'VK9'), kind: 'button' }),
    VK10: task(L1_K, 'VK10', 3, { target: 'input[name="min_lager"]', label: fieldLabel(L1_K, 'min_lager'), kind: 'field' }),
    VK11: task(L1_K, 'VK11', 3, { target: 'button[onclick^="bestil("]:not([disabled])', label: showKey(L1_K, 'VK11'), kind: 'button' }),
    VK12: task(L1_K, 'VK12', 3, { target: 'input[name="samlevare"][type="checkbox"]', label: fieldLabel(L1_K, 'samlevare'), kind: 'field' }),
    VK13: task(L1_K, 'VK13', 3, { target: 'input[name="submit"][value="Vareopslag"]', label: showKey(L1_K, 'VK13'), kind: 'button' }),
    VK15: task(L1_K, 'VK15', 3, { target: 'select[name^="var_type["]', label: fieldLabel(L1_K, 'var_type'), kind: 'field' }),
    VK16: task(L1_K, 'VK16', 2, { target: 'input[name^="variant_varer_beholdning["]', label: showKey(L1_K, 'VK16'), kind: 'field' }),
    // Udgået: the saved tick decides which of the two the card offers (notesEtc.php:53-54)
    VK17: task(L1_K, 'VK17', 2, { target: l1Only(l1CardPage, ['kort'], '', function (ctx, s) {
      return s.flags && s.flags.udgaaet ? null : l1Q(ctx.doc, 'input[name="lukket"]');
    }), label: fieldLabel(L1_K, 'lukket'), kind: 'field' }),
    VK18: task(L1_K, 'VK18', 1, { target: l1Only(l1CardPage, ['kort'], 'udgaaet', 'input[name="lukket"]'), label: fieldLabel(L1_K, 'lukket'), kind: 'field' }),
    VK19: task(L1_K, 'VK19', 2, { target: 'input[name="deleteItem"]', label: showKey(L1_K, 'VK19'), kind: 'button' }),
    VK20: task(L1_K, 'VK20', 3, { target: 'input[name="copy"]', label: showKey(L1_K, 'VK20'), kind: 'button' }),
    VK21: task(L1_K, 'VK21', 2, { target: 'a[href^="ret_varenr.php"]', label: showKey(L1_K, 'VK21'), kind: 'button' }),
    VK22: task(L1_K, 'VK22', 3, { target: 'input[name="special_price"]', label: showKey(L1_K, 'VK22'), kind: 'field' }),
    // VK23: Tilknyt billede is printed only with internal document storage (grupper bilag box6 'on', showInfo.php:12-21;
    // includes/settings/virtualStorage.php:56-64), step 3 (live check 24.15).
    VK23: task(L1_K, 'VK23', 3, { target: 'a[href^="varefoto.php"]', label: showKey(L1_K, 'VK23'), kind: 'button' }),
    VK24: task(L1_K, 'VK24', 2, L1_K_GEAR),
    VK25: task(L1_K, 'VK25', 2, { target: 'input[name="has_due_date"]', label: fieldLabel(L1_K, 'has_due_date'), kind: 'field' }),
    VK26: task(L1_K, 'VK26', 1, { target: 'a[href^="batch_oversigt.php"]', label: showKey(L1_K, 'VK26'), kind: 'button' }),
    VK27: task(L1_K, 'VK27', 2, { target: 'input[name="serienr"]', label: fieldLabel(L1_K, 'serienr'), kind: 'field' }),
    // Tilbage leads to the order only on a card opened from one (returside, :1336-1340)
    VK28: task(L1_K, 'VK28', 2, { target: l1Only(l1CardPage, ['kort', 'ny'], 'fraOrdre', 'a[accesskey="L"]'), label: showKey(L1_K, 'VK28'), kind: 'button' }),
    VK29: task(L1_K, 'VK29', 2, { target: 'a[href^="ret_varenralias.php"]', label: showKey(L1_K, 'VK29'), kind: 'button' }),
    VK30: task(L1_K, 'VK30', 3, { target: 'input[name="acceptStockChange"]', label: showKey(L1_K, 'VK30'), kind: 'button' }),
    VK31: task(L1_K, 'VK31', 1, { target: 'input[name="cancelStockChange"]', label: showKey(L1_K, 'VK31'), kind: 'button' }),
    VK32: task(L1_K, 'VK32', 2, { target: 'input[name="ChangeDescription"][value="Ja"]', label: showKey(L1_K, 'VK32'), kind: 'button' }),
    VK33: task(L1_K, 'VK33', 2, { target: 'a[href*="konto_id="]', label: showKey(L1_K, 'VK33'), kind: 'row' }),
    VK34: task(L1_K, 'VK34', 2, {
      target: l1Only(l1CardPage, ['leverandoropslag'], '', function (ctx) {
        const td = l1Q(ctx.doc, 'td[onclick*="kreditorkort.php"]');
        return td ? (l1Q(td, 'button') || td) : null;
      }),
      label: showKey(L1_K, 'VK34'),
      kind: 'button'
    }),
    VK35: task(L1_K, 'VK35', 2, { target: l1Only(l1CardPage, ['vareopslag'], '', 'a[href*="vare_id="]'), label: showKey(L1_K, 'VK35'), kind: 'row' }),
    VK36: task(L1_K, 'VK36', 3, { target: l1Only(l1CardPage, ['kort'], 'gruppeUkendt', L1_K_GROUP.target), label: L1_K_GROUP.label, kind: 'field' }),
    VK37: task(L1_K, 'VK37', 2, L1_K_GEAR),
    // VK38: only on a part (delvare); a bill of materials prints the same link on each of its parts (live check 24.15)
    VK38: task(L1_K, 'VK38', 1, { target: l1Only(l1CardPage, ['kort'], 'delvare', 'a[href*="vis_samlevarer=on"]'), label: showKey(L1_K, 'VK38'), kind: 'button' })
  };

  const L1_K_FIELDS = {};
  ['varenr', 'beskrivelse', 'beskrivelse_alias', 'stregkode', 'trademark', 'ny_gruppe', 'prisgruppe', 'salgspris', 'tier_price',
    'retail_price', 'kostpris', 'special_price', 'enhed', 'lagerlok', 'folgevarenr', 'min_lager', 'max_lager', 'volume_lager',
    'ny_lagerbeh', 'notes', 'note_on_orderline', 'notes_internal', 'serienr', 'samlevare', 'lukket', 'has_due_date',
    'default_shelf_life_days', 'lev_pos', 'lev_varenr', 'lev_kostpris', 'be_af_ant', 'var_type', 'var_type_stregk',
    'bestil_antal', 'reason', 'initials'].forEach(function (k) { L1_K_FIELDS[k] = fieldKeys(L1_K, k); });

  const l1Card = {
    group: L1_K,
    states: ['ny', 'kort', 'bekraeftLager', 'bekraeftBeskrivelse', 'leverandoropslag', 'vareopslag'],
    flags: ['lagerfoert', 'beholdning', 'udenBeskrivelse', 'udenLeverandor', 'stykliste', 'delvare', 'varianter', 'variantTyper',
      'udloeb', 'serienr', 'udgaaet', 'kunLaes', 'kostLaast', 'kopi', 'fraOrdre', 'gruppeUkendt', 'skjulteDele', 'aktuelLaast'],

    name: function (ctx, st) {
      const state = l1State(st);
      const nr = String((st && st.data && st.data.varenr) || '');
      if (state === 'ny') return 'help.lkort.name_new';
      if (nr) return ['help.lkort.name', nr];
      return 'help.lkort.name_plain';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const varenr = l1Value(l1Q(doc, 'input[name="varenr"]'));
      const data = { varenr: varenr };
      if (l1Q(doc, 'form[name="confirmStockChange"], input[name="acceptStockChange"]')) return { state: 'bekraeftLager', flags: {}, data: data };
      if (l1Q(doc, 'input[name="ChangeDescription"]')) return { state: 'bekraeftBeskrivelse', flags: {}, data: data };
      if (l1Q(doc, 'a[href*="funktion=kontoOpslag"]')) return { state: 'leverandoropslag', flags: {}, data: {} };
      if (l1Q(doc, 'a[href*="funktion=vareopslag"]')) return { state: 'vareopslag', flags: {}, data: {} };
      const retur = l1Value(l1Q(doc, 'input[name="returside"]')) || l1Param(ctx, 'returside');
      const fraOrdre = /ordre\.php/.test(retur);
      if (l1Q(doc, 'input[name="varenr"]:not([type="hidden"])')) return { state: 'ny', flags: { fraOrdre: fraOrdre }, data: data };
      if (!l1Q(doc, 'input[name="beskrivelse0"]')) return { state: 'unknown', flags: {}, data: {} };
      const lager = l1Q(doc, 'input[name="stockItem"]');
      const lagerfoert = l1Value(lager) === 'on';
      const save = l1Q(doc, 'input[name="saveItem"]');
      const cost = l1Q(doc, '#costPrice');
      const stykliste = !!l1Q(doc, '#stykliste-tbody');
      const flags = {
        lagerfoert: lagerfoert,
        beholdning: lagerfoert && l1Num(l1Value(l1Q(doc, 'input[name="beholdning"]'))) !== 0,
        udenBeskrivelse: l1Value(l1Q(doc, 'input[name="beskrivelse0"]')) === '',
        udenLeverandor: !l1Q(doc, 'input[name^="lev_varenr["]') && !stykliste,
        stykliste: stykliste,
        delvare: l1Value(l1Q(doc, 'input[name="delvare"]')) === 'on',
        varianter: !!l1Q(doc, 'input[name^="variant_vare_id["]'),
        variantTyper: !!l1Q(doc, 'select[name^="var_type["]'),
        udloeb: !!l1Q(doc, 'input[name="has_due_date"]'),
        serienr: l1Saved(l1Q(doc, 'input[name="serienr"]')),
        udgaaet: l1Saved(l1Q(doc, 'input[name="lukket"]')),
        // MB-62: a read-only right gets no Gem at all (varekort.php:2193-2198); before it, a disabled Gem
        kunLaes: !save || l1Has(save, 'disabled'),
        kostLaast: !!cost && l1Has(cost, 'readonly'),
        kopi: /^kopi_af_|_copy$/.test(varenr),
        fraOrdre: fraOrdre,
        gruppeUkendt: l1GroupUnknown(doc),
        skjulteDele: !!l1Q(doc, '[id^="pcSec"].pcVisHidden'),
        // Aktuel printed readonly without variants and without a bill of materials: FIFO (showLocations.php:190, :233)
        aktuelLaast: false
      };
      flags.aktuelLaast = lagerfoert && !stykliste && !flags.varianter && !!l1LockedStockField(ctx);
      return { state: 'kort', flags: flags, data: data };
    },

    about: function (ctx, st) {
      const state = l1State(st);
      const f = l1Flags(st);
      if (state === 'ny') return f.fraOrdre ? ['help.lkort.about.ny', 'help.lkort.about.fra_ordre'] : ['help.lkort.about.ny'];
      if (state === 'bekraeftLager') return ['help.lkort.about.bekraeft_lager'];
      if (state === 'bekraeftBeskrivelse') return ['help.lkort.about.bekraeft_beskrivelse'];
      if (state === 'leverandoropslag') return ['help.lkort.about.leverandoropslag'];
      if (state === 'vareopslag') return ['help.lkort.about.vareopslag'];
      if (state !== 'kort') return [];
      let first = 'help.lkort.about.kort';
      if (f.stykliste) first = 'help.lkort.about.stykliste';
      else if (f.varianter) first = 'help.lkort.about.varianter';
      else if (f.lagerfoert) first = 'help.lkort.about.lagerfoert';
      const out = [first];
      if (f.kunLaes) out.push('help.lkort.about.kun_laes');
      else if (f.gruppeUkendt) out.push('help.lkort.about.gruppe_ukendt');
      else if (f.kopi) out.push('help.lkort.about.kopi');
      else if (f.udenBeskrivelse) out.push('help.lkort.about.uden_beskrivelse');
      else if (f.udgaaet) out.push('help.lkort.about.udgaaet');
      else if (f.aktuelLaast) out.push('help.lkort.about.aktuel_laast');
      else if (f.skjulteDele) out.push('help.lkort.about.skjulte_dele');
      else if (f.fraOrdre) out.push('help.lkort.about.fra_ordre');
      return out;
    },

    tasks: L1_K_TASKS,

    order: function (ctx, st) {
      const state = l1State(st);
      const f = l1Flags(st);
      let ids = [];
      if (state === 'ny') ids = [f.fraOrdre ? 'VK28' : '', 'VK1', 'VK2'];
      else if (state === 'bekraeftLager') ids = ['VK30', 'VK31'];
      else if (state === 'bekraeftBeskrivelse') ids = ['VK32'];
      else if (state === 'leverandoropslag') ids = ['VK33', 'VK34'];
      else if (state === 'vareopslag') ids = ['VK35'];
      else if (state === 'kort' && f.kunLaes) ids = ['VK9'];
      else if (state === 'kort') {
        const pre = [f.gruppeUkendt ? 'VK36' : '', f.skjulteDele ? 'VK37' : '', f.kopi ? 'VK21' : '', f.fraOrdre ? 'VK28' : '',
          f.udgaaet ? 'VK18' : ''];
        let main;
        // A fresh item: the group first, as the first Gem saves group 1 when none is chosen (showGroups.php:5, :15;
        // varekort.php:1033).
        if (f.udenBeskrivelse) main = ['VK4', 'VK3', 'VK5', 'VK6', f.stykliste ? 'VK13' : 'VK7', f.lagerfoert ? (f.aktuelLaast ? 'VK41' : 'VK39') : '', f.lagerfoert ? 'VK10' : ''];
        else if (f.stykliste && f.lagerfoert && f.beholdning) main = ['VK40', 'VK5', 'VK13', 'VK22', 'VK20', 'VK4'];
        else if (f.stykliste) main = ['VK13', f.lagerfoert ? 'VK40' : '', 'VK5', 'VK22', 'VK20', 'VK4'];
        else if (f.varianter) main = [f.lagerfoert ? 'VK16' : '', 'VK15', 'VK9', 'VK5', 'VK6', f.udenLeverandor ? 'VK7' : 'VK11'];
        // With FIFO Aktuel is locked: the stock count (VK41) takes the place of VK8 and VK39 (live check 24.15).
        else if (f.lagerfoert && f.beholdning) main = ['VK9', f.aktuelLaast ? 'VK41' : 'VK8', 'VK5', 'VK6', f.udenLeverandor ? 'VK7' : 'VK11', 'VK10'];
        else if (f.lagerfoert) main = [f.aktuelLaast ? 'VK41' : 'VK39', 'VK10', f.udenLeverandor ? 'VK7' : 'VK11', 'VK5', 'VK6', 'VK9'];
        else main = ['VK5', 'VK6', 'VK4', f.udenLeverandor ? 'VK7' : 'VK11'];
        const rest = [f.udloeb ? 'VK25' : '', f.udloeb ? 'VK26' : '', f.lagerfoert && !f.varianter ? 'VK27' : '',
          f.variantTyper && !f.varianter && !f.stykliste ? 'VK15' : '', 'VK22', 'VK20', 'VK21', 'VK29', 'VK4',
          f.udenLeverandor || f.stykliste ? '' : 'VK7', f.stykliste || f.varianter ? '' : 'VK12', f.delvare ? 'VK38' : '', 'VK23',
          f.skjulteDele ? '' : 'VK24', f.udgaaet ? '' : 'VK17', 'VK19'];
        ids = pre.concat(main, rest);
      }
      return unique(ids.filter(function (id) { return id !== ''; }));
    },

    // Group 1 as the saved group without a name: the group does not exist in the fiscal year (showGroups.php:5-15), so
    // the item has no sales, purchase or stock accounts to post to.
    problem: function (ctx, st) {
      const f = l1Flags(st);
      return l1State(st) === 'kort' && f.gruppeUkendt && !f.kunLaes ? 'VK36' : '';
    },

    fields: L1_K_FIELDS,

    // Names with an index or capitals (varekort.php, showPrices.php:63, showLocations.php:62, :145-151, notesEtc.php:20,
    // showInfo.php:26, useVariants.php:21, :43; the confirm form, confirmStockChange.php:29-34).
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (n === 'kostpris[0]') return 'kostpris';
      if (/^kostpris2?\[\d+\]$/.test(n)) return 'lev_kostpris';
      if (/^lev_varenr\[\d+\]$/.test(n)) return 'lev_varenr';
      if (/^lev_pos\[\d+\]$/.test(n)) return 'lev_pos';
      if (/^ny_lagerbeh\[\d+\]$/.test(n) || n === 'ny_beholdning') return 'ny_lagerbeh';
      if (/^lagerlok\[\d+\]$/.test(n) || n === 'location') return 'lagerlok';
      if (/^be_af_(ant|pos)\[\d+\]$/.test(n)) return 'be_af_ant';
      if (/^var_type\[\d+\]$/.test(n)) return 'var_type';
      if (n === 'beskrivelseAlias') return 'beskrivelse_alias';
      if (n === 'notesInternal') return 'notes_internal';
      if (attr(el, 'id') === 'bestil-antal') return 'bestil_antal';
      return null;
    },

    // accesskey g Gem, k Kopier, o Lagerhistorik (varekort.php:2085, :2097, :2099), N Ny in the head of a saved card
    // (:1363). Alt+L is left out: Tilbage (L) and Leverandøropslag (l) share it (:1337, :2098). The list is one for every
    // state, and the confirmations reuse two keys: o is OK of the stock confirmation (confirmStockChange.php:61) and n is
    // Nej of the description confirmation (changeDescription.php:43), so texts 03 and 04 name them.
    shortcuts: [
      { keys: 'Alt+G', text: 'help.lkort.key.01', group: 'keys' },
      { keys: 'Alt+K', text: 'help.lkort.key.02', group: 'keys' },
      { keys: 'Alt+O', text: 'help.lkort.key.03', group: 'keys' },
      { keys: 'Alt+N', text: 'help.lkort.key.04', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.lkort.q.q1.q',
        words: l1Words(['moms', 'momsfri', 'uden moms', 'momssats', 'momskode', 'ingen moms'],
          ['vat', 'vat free', 'without vat', 'vat rate', 'vat code', 'no vat'],
          ['mva', 'mva-fri', 'uten mva', 'mva-sats', 'mva-kode', 'ingen mva']),
        steps: ['help.lkort.q.q1.step1', 'help.lkort.q.q1.step2'],
        show: L1_K_GROUP
      },
      Q2: {
        q: 'help.lkort.q.q2.q',
        words: l1Words(['kostpris', 'låst', 'grå', 'kan ikke rette', 'kan ikke ændre', 'fifo', 'prisgruppe'],
          ['cost price', 'locked', 'greyed out', 'cannot change', 'cannot edit', 'fifo', 'price group'],
          ['kostpris', 'låst', 'grå', 'kan ikke endre', 'fifo', 'prisgruppe']),
        steps: ['help.lkort.q.q2.step1', 'help.lkort.q.q2.step2'],
        show: { target: '#costPrice', label: fieldLabel(L1_K, 'kostpris'), kind: 'field' }
      },
      Q3: {
        q: 'help.lkort.q.q3.q',
        words: l1Words(['varegruppe', 'skifte gruppe', 'ændre gruppe', 'kan ikke vælge', 'gruppe', 'lagerført'],
          ['product group', 'item group', 'change group', 'cannot choose', 'group', 'stock item'],
          ['varegruppe', 'bytte gruppe', 'endre gruppe', 'kan ikke velge', 'gruppe', 'lagerført']),
        steps: ['help.lkort.q.q3.step1', 'help.lkort.q.q3.step2'],
        show: L1_K_GROUP
      },
      Q4: {
        q: 'help.lkort.q.q4.q',
        words: l1Words(['væk', 'forsvundet', 'mangler', 'kan ikke se', 'skjult', 'felter', 'tandhjul'],
          ['gone', 'disappeared', 'missing', 'cannot see', 'hidden', 'fields', 'gear'],
          ['borte', 'forsvunnet', 'mangler', 'kan ikke se', 'skjult', 'felt', 'tannhjul']),
        steps: stepKeys(L1_K, 'VK37', 1, 2),
        show: L1_K_GEAR
      },
      Q5: {
        q: 'help.lkort.q.q5.q',
        words: l1Words(['slet', 'slette', 'fjern', 'fjerne', 'udgået', 'ikke mere', 'stoppe'],
          ['delete', 'remove', 'discontinued', 'no longer', 'stop'],
          ['slett', 'slette', 'fjern', 'fjerne', 'utgått', 'ikke mer', 'stoppe']),
        steps: stepKeys(L1_K, 'VK19', 1, 2),
        show: { target: 'input[name="lukket"]', label: fieldLabel(L1_K, 'lukket'), kind: 'field' }
      },
      Q6: {
        q: 'help.lkort.q.q6.q',
        words: l1Words(['samlevare', 'stykliste', 'låst', 'grå', 'kan ikke sætte flueben'],
          ['assembled item', 'bill of materials', 'locked', 'greyed out', 'cannot tick'],
          ['samlevare', 'stykkliste', 'låst', 'grå', 'kan ikke krysse av']),
        steps: ['help.lkort.q.q6.step1'],
        show: L1_K_TASKS.VK12.show
      },
      // Q8: with Vis priser med moms på varekort (items.stock.vat_on_item_card, settingsDefinitions.php:877-878) the card
      // shows Salgspris with the group's VAT and (incl Moms) beside it (varekort.php:1539-1557, showPrices.php:18-19, text
      // 2018); a momsfri group gives 0 (productCardIncludes/itemVat.php:3-17). Gem takes the VAT out again (:762-771).
      Q8: {
        q: 'help.lkort.q.q8.q',
        words: l1Words(['moms', 'inkl moms', 'incl moms', 'ekskl moms', 'uden moms', 'med moms', 'salgspris', 'pris'],
          ['vat', 'incl vat', 'excl vat', 'without vat', 'with vat', 'sales price', 'price'],
          ['mva', 'inkl mva', 'ekskl mva', 'uten mva', 'med mva', 'salgspris', 'pris']),
        intro: 'help.lkort.q.q8.intro',
        steps: ['help.lkort.q.q8.step1', 'help.lkort.q.q8.step2'],
        show: L1_K_TASKS.VK5.show
      },
      // Q9: a changed Aktuel runs lagerreguler() (varekort.php:1241-1254, after a confirmation :323-351), which writes
      // lagerstatus, batch_kob or batch_salg and varer only (includes/std_func.php:1666-1773); nothing posts the stock value
      // (aut_lager, includes/ordrefunc.php:1985-1986). Every movement, also an unconfirmed one, is in Lagerhistorik
      // (productCardIncludes/stockLog.php:62-130).
      Q9: {
        q: 'help.lkort.q.q9.q',
        words: l1Words(['bogført', 'bogføres', 'regnskab', 'postering', 'lagerværdi', 'beholdning', 'aktuel', 'regulering'],
          ['posted', 'accounts', 'entry', 'stock value', 'stock', 'aktuel', 'adjustment'],
          ['bokført', 'bokføres', 'regnskap', 'postering', 'lagerverdi', 'beholdning', 'aktuel', 'regulering']),
        intro: 'help.lkort.q.q9.intro',
        steps: ['help.lkort.q.q9.step1', 'help.lkort.q.q9.step2'],
        show: L1_K_TASKS.VK9.show
      },
      // Q10: Ja only updates varer.beskrivelse (varekort.php:1228-1231); order lines keep the text they were given
      // (includes/ordrefunc.php:1153, :1271, opret_ordrelinje :3894), and nothing updates them.
      Q10: {
        q: 'help.lkort.q.q10.q',
        words: l1Words(['beskrivelse', 'gamle fakturaer', 'ordrer', 'fakturaer', 'ændret tekst', 'varenavn'],
          ['description', 'old invoices', 'orders', 'invoices', 'changed text', 'item name'],
          ['beskrivelse', 'gamle fakturaer', 'ordrer', 'fakturaer', 'endret tekst', 'varenavn']),
        intro: 'help.lkort.q.q10.intro',
        steps: ['help.lkort.q.q10.step1'],
        show: L1_K_TASKS.VK32.show
      },
      // Q11: the number is changed on ret_varenr.php from the saved card's Varenr. link (varekort.php:1689-1697,
      // ret_varenr.php:113-123): VK21's steps.
      Q11: {
        q: 'help.lkort.q.q11.q',
        words: l1Words(['varenummer', 'ændre nummer', 'rette nummer', 'senere', 'skifte nummer', 'forkert nummer'],
          ['item number', 'change number', 'later', 'wrong number', 'rename'],
          ['varenummer', 'endre nummer', 'rette nummer', 'senere', 'bytte nummer', 'feil nummer']),
        intro: 'help.lkort.q.q11.intro',
        steps: stepKeys(L1_K, 'VK21', 1, 2),
        show: L1_K_TASKS.VK21.show
      }
    },

    // The problem (VK36) and the flags that change what the card asks lead; a new card has only Varenr. (Q11), a fresh one
    // gets its prices next (Q8); the confirmations and the lookups get their own question or the card's.
    questionOrder: function (ctx, st) {
      const state = l1State(st);
      const f = l1Flags(st);
      if (state === 'ny') return ['Q11', 'Q8', 'Q5'];
      if (state === 'bekraeftLager') return ['Q9', 'Q1', 'Q8'];
      if (state === 'bekraeftBeskrivelse') return ['Q10', 'Q1', 'Q8'];
      if (state === 'vareopslag') return ['Q2', 'Q1', 'Q8'];
      if (state !== 'kort') return ['Q1', 'Q8', 'Q5'];
      if (f.gruppeUkendt) return ['Q3', 'Q1', 'Q8'];
      if (f.skjulteDele) return ['Q4', 'Q1', 'Q8'];
      if (f.kostLaast) return ['Q2', 'Q1', 'Q8'];
      if (f.udenBeskrivelse) return ['Q8', 'Q1', 'Q11'];
      if (f.udgaaet) return ['Q5', 'Q1', 'Q8'];
      if (f.lagerfoert) return ['Q1', 'Q9', 'Q8'];
      return ['Q1', 'Q8', 'Q5'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/varekort.php'] = l1Card;

  // ---- Lagerlog (lager/productCardIncludes/stockLog.php)
  // Evidence, lager/productCardIncludes/stockLog.php: opened by Lagerhistorik on the card (varekort.php:2099). Luk
  // input[value*=Luk] back to the card (:251-254); the title Lagerlog with the item (:256); the columns Tidspunkt, Type,
  // Lager, Reference, Bruger, Initialer, Antal, Beholdning (:264-273), all Danish in the page; types Manuel (stocklog: a
  // confirmed change on the card with its reason, :70-80), Indkøb and Salg (batch_kob, batch_salg; an order link Ordre #nr
  // in a new tab, read only, :93-160, :303-309), Optælling (a stock count, grey italic with (ikke bogført) until posted,
  // :195-211, :291-313); none: Ingen transaktioner fundet for denne vare (:334-337).

  const L1_LOG_TASKS = {
    LG1: task(L1_K, 'LG1', 3, { target: 'table thead th', label: showKey(L1_K, 'LG1'), kind: 'status' }),
    LG2: task(L1_K, 'LG2', 1, { target: 'a[href*="ordre.php"][target="_blank"]', label: showKey(L1_K, 'LG2'), kind: 'row' }),
    LG3: task(L1_K, 'LG3', 3),
    LG4: task(L1_K, 'LG4', 1, { target: 'input[type="button"][value*="Luk"]', label: showKey(L1_K, 'LG4'), kind: 'button' })
  };

  const l1StockLog = {
    group: L1_K,
    states: ['liste', 'tom'],
    flags: ['ikkeBogfoert'],

    name: function () {
      return 'help.lkort.name_log';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      if (!l1Q(doc, 'input[type="button"][value*="Luk"]')) return { state: 'unknown', flags: {}, data: {} };
      const rows = l1Qa(doc, 'tr.table-row').length;
      const ikkeBogfoert = l1Qa(doc, 'tr.table-row em').length > 0;
      return { state: rows ? 'liste' : 'tom', flags: { ikkeBogfoert: ikkeBogfoert }, data: { rows: rows } };
    },

    about: function (ctx, st) {
      const state = l1State(st);
      if (state === 'tom') return ['help.lkort.about.log_tom'];
      if (state !== 'liste') return [];
      return l1Flags(st).ikkeBogfoert ? ['help.lkort.about.log', 'help.lkort.about.log_ikke_bogfoert'] : ['help.lkort.about.log'];
    },

    tasks: L1_LOG_TASKS,

    order: function (ctx, st) {
      const state = l1State(st);
      if (state === 'liste') return ['LG1', 'LG3', 'LG2', 'LG4'];
      if (state === 'tom') return ['LG4'];
      return [];
    },

    fields: {},

    shortcuts: [],

    questions: {
      Q7: {
        q: 'help.lkort.q.q7.q',
        words: l1Words(['ikke bogført', 'grå', 'kursiv', 'optælling', 'bogføre optælling'],
          ['not posted', 'grey', 'italic', 'stock count', 'post count'],
          ['ikke bokført', 'grå', 'kursiv', 'opptelling', 'bokføre opptelling']),
        steps: ['help.lkort.q.q7.step1']
      }
    },

    questionOrder: function (ctx, st) {
      return l1State(st) === 'liste' ? ['Q7'] : [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/productCardIncludes/stockLog.php'] = l1StockLog;

  // ------------------------------------------------------------------ the small pages the card opens (group lvx)
  const L1_X = 'lvx';

  /** Luk / Tilbage of the small pages: the link with accesskey L, else Tilbage of the packaging page. */
  const L1_X_BACK = { target: 'a[accesskey="L"]', label: showKey(L1_X, 'RV3'), kind: 'button' };

  // ---- Ret varenummer (lager/ret_varenr.php)
  // Evidence, lager/ret_varenr.php: the card's Varenr. link (varekort.php:1689, :1697, without write access no link); Luk
  // accesskey L (:47); the Danish explanation (:139-150); the field nyt_varenr and Ret (:150-156). A number, barcode or
  // alias in use is refused (:54-56); otherwise the number changes on the item and on the lines of orders not closed
  // (status < 3, :113-123). =<nr> merges this item into that one: history, stock, suppliers and shop links move, this
  // item is deleted; not for a part of a bill of materials (:57-104, flet() :160-281).

  const L1_RV_TASKS = {
    RV1: task(L1_X, 'RV1', 3, { target: 'input[name="nyt_varenr"]', label: fieldLabel(L1_X, 'nyt_varenr'), kind: 'field' }),
    RV2: task(L1_X, 'RV2', 3, { target: 'input[name="nyt_varenr"]', label: fieldLabel(L1_X, 'nyt_varenr'), kind: 'field' }),
    RV3: task(L1_X, 'RV3', 1, L1_X_BACK)
  };

  const l1RetVnr = {
    group: L1_X,
    states: ['form'],
    flags: [],

    name: function () {
      return 'help.lvx.name_retvnr';
    },

    detect: function (ctx) {
      return { state: l1Q(ctx.doc, 'input[name="nyt_varenr"]') ? 'form' : 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      return l1State(st) === 'form' ? ['help.lvx.about.retvnr'] : [];
    },

    tasks: L1_RV_TASKS,

    order: function (ctx, st) {
      return l1State(st) === 'form' ? ['RV1', 'RV2', 'RV3'] : [];
    },

    fields: { nyt_varenr: fieldKeys(L1_X, 'nyt_varenr') },

    // One form with one submit button (:137-156): Enter in the field sends Ret.
    shortcuts: [
      { keys: 'Enter', text: 'help.lvx.key.01', group: 'keys' }
    ],

    questions: {
      Q1: {
        q: 'help.lvx.q.q1.q',
        words: l1Words(['gamle fakturaer', 'bogførte', 'historik', 'statistik', 'gammelt nummer', 'afsluttede ordrer'],
          ['old invoices', 'posted', 'history', 'statistics', 'old number', 'closed orders'],
          ['gamle fakturaer', 'bokførte', 'historikk', 'statistikk', 'gammelt nummer', 'avsluttede ordrer']),
        steps: ['help.lvx.q.q1.step1']
      }
    },

    questionOrder: function (ctx, st) {
      return l1State(st) === 'form' ? ['Q1'] : [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/ret_varenr.php'] = l1RetVnr;

  // ---- Ret varenummer alias (lager/ret_varenralias.php)
  // Evidence, lager/ret_varenralias.php: the card's Varenr alias / Tilføj varenr alias link (varekort.php:1698-1702); Luk
  // accesskey L (:46); the field nyt_varenr_alias and Ret alias (:131-138). An alias that is a number, a barcode or an
  // alias in use is refused (:53-55); an empty field changes nothing (:53). =<nr> merges as on Ret varenummer (:56-92).
  // The item list's Varenr. search also looks in the alias (vareliste.php:104).

  const l1RetAlias = {
    group: L1_X,
    states: ['form'],
    flags: [],

    name: function () {
      return 'help.lvx.name_alias';
    },

    detect: function (ctx) {
      return { state: l1Q(ctx.doc, 'input[name="nyt_varenr_alias"]') ? 'form' : 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      return l1State(st) === 'form' ? ['help.lvx.about.alias'] : [];
    },

    tasks: {
      RA1: task(L1_X, 'RA1', 3, { target: 'input[name="nyt_varenr_alias"]', label: fieldLabel(L1_X, 'nyt_varenr_alias'), kind: 'field' }),
      RV3: task(L1_X, 'RV3', 1, L1_X_BACK)
    },

    order: function (ctx, st) {
      return l1State(st) === 'form' ? ['RA1', 'RV3'] : [];
    },

    fields: { nyt_varenr_alias: fieldKeys(L1_X, 'nyt_varenr_alias') },

    shortcuts: [
      { keys: 'Enter', text: 'help.lvx.key.02', group: 'keys' }
    ],

    questions: {
      Q2: {
        q: 'help.lvx.q.q2.q',
        words: l1Words(['alias', 'ekstra nummer', 'andet nummer', 'leverandørens nummer', 'gammelt nummer'],
          ['alias', 'extra number', 'other number', 'supplier number', 'old number'],
          ['alias', 'ekstra nummer', 'annet nummer', 'leverandørens nummer', 'gammelt nummer']),
        steps: ['help.lvx.q.q2.step1']
      }
    },

    questionOrder: function (ctx, st) {
      return l1State(st) === 'form' ? ['Q2'] : [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/ret_varenralias.php'] = l1RetAlias;

  // ---- Varefoto (lager/varefoto.php)
  // Evidence, lager/varefoto.php: Tilknyt billede or the photo on the card, only with internal document storage
  // (productCardIncludes/showInfo.php:10-23); Luk accesskey L (:41); without a photo the upload form Vælg datafil
  // uploadedfile and Hent, at most about 2,9 MB (:84-96); with a photo the picture and Slet foto (sletfoto=1, deletes at
  // once, :57-66).

  const l1Foto = {
    group: L1_X,
    states: ['upload', 'foto'],
    flags: [],

    name: function () {
      return 'help.lvx.name_foto';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      if (l1Q(doc, 'a[href*="sletfoto=1"]')) return { state: 'foto', flags: {}, data: {} };
      if (l1Q(doc, 'input[name="uploadedfile"]')) return { state: 'upload', flags: {}, data: {} };
      return { state: 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      const state = l1State(st);
      return state === 'upload' || state === 'foto' ? ['help.lvx.about.foto_' + state] : [];
    },

    tasks: {
      VF1: task(L1_X, 'VF1', 2, { target: 'input[name="uploadedfile"]', label: fieldLabel(L1_X, 'uploadedfile'), kind: 'field' }),
      VF2: task(L1_X, 'VF2', 2, { target: 'a[href*="sletfoto=1"]', label: showKey(L1_X, 'VF2'), kind: 'button' }),
      RV3: task(L1_X, 'RV3', 1, L1_X_BACK)
    },

    order: function (ctx, st) {
      const state = l1State(st);
      if (state === 'upload') return ['VF1', 'RV3'];
      if (state === 'foto') return ['VF2', 'RV3'];
      return [];
    },

    fields: { uploadedfile: fieldKeys(L1_X, 'uploadedfile') },

    shortcuts: [],

    questions: {},

    questionOrder: function () {
      return [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/varefoto.php'] = l1Foto;

  // ---- Emballage (lager/emballage.php)
  // Evidence, lager/emballage.php: the card's Emballage button with the packaging module on (varekort.php:2109-2113), so
  // the module-off note (:27-31) is not reached from the card and gets no state; Tilbage accesskey L (:121-124); headers Kategori, Type, Affaldssortering, Niveau,
  // Slutbruger, Vægt (kg), Beskrivelse (English for English users, Danish otherwise, :148-152); per line category[n],
  // type[n], waste_sorting[n], niveau[n], end_user[n], weight[n], line_text[n] and Slet on a saved line (:153-184); two
  // empty lines always (:191-194); Gem name=save (:200-203); Gemt. after a save (.emb-saved, :140). The data feed the
  // finance report Emballage (finans/rapport_includes/forside.php:342-345).

  const l1Emb = {
    group: L1_X,
    states: ['form'],
    flags: ['gemt'],

    name: function () {
      return 'help.lvx.name_emb';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      if (l1Q(doc, 'table.emb')) return { state: 'form', flags: { gemt: !!l1Q(doc, '.emb-saved') }, data: {} };
      return { state: 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      if (l1State(st) !== 'form') return [];
      return l1Flags(st).gemt ? ['help.lvx.about.emb', 'help.lvx.about.emb_gemt'] : ['help.lvx.about.emb'];
    },

    tasks: {
      EM1: task(L1_X, 'EM1', 3, { target: 'select[name^="category["]', label: fieldLabel(L1_X, 'category'), kind: 'field' }),
      EM2: task(L1_X, 'EM2', 1, { target: 'button[onclick*="embDelete"]', label: showKey(L1_X, 'EM2'), kind: 'button' }),
      EM3: task(L1_X, 'EM3', 2)
    },

    order: function (ctx, st) {
      return l1State(st) === 'form' ? ['EM1', 'EM2', 'EM3'] : [];
    },

    fields: (function () {
      const out = {};
      ['category', 'type', 'waste_sorting', 'niveau', 'end_user', 'weight', 'line_text'].forEach(function (k) { out[k] = fieldKeys(L1_X, k); });
      return out;
    })(),

    // The line fields carry the line's index (emballage.php:172-178).
    fieldKey: function (el) {
      const m = /^(category|type|waste_sorting|niveau|end_user|weight|line_text)\[\d+\]$/.exec(attr(el, 'name'));
      return m ? m[1] : null;
    },

    shortcuts: [],

    questions: {},

    questionOrder: function () {
      return [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/emballage.php'] = l1Emb;

  // ---- HappyHour (lager/happyhour.php)
  // Evidence, lager/happyhour.php: the card's Avanceret beside Tilbud (productCardIncludes/showDiscounts.php:2); Luk
  // accesskey L (:97); the item's Salgspris and Kostpris (:103-104); one row per price with Aktiv fra, Aktiv til, Fra kl,
  // Til kl., Tilbudspris (Incl. moms), Kostpris (Ex. moms), Ugedag, Slet (:106-130), one empty row for a new price
  // (:131-154), Opdater (:156). A new row is saved only with dates, a weekday and a price (:79-86); empty times give the
  // whole day (:56-65); Slet with Opdater deletes the row (:46-48). The price applies on an order line added on that
  // weekday in the period and time (includes/ordrefunc.php:4203-4210).

  const l1Happy = {
    group: L1_X,
    states: ['form'],
    flags: ['harPriser'],

    name: function () {
      return 'help.lvx.name_happy';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      if (!l1Q(doc, 'form[name="happyhour"], input[name="opdater"]')) return { state: 'unknown', flags: {}, data: {} };
      return { state: 'form', flags: { harPriser: !!l1Q(doc, 'input[name^="slet["]') }, data: {} };
    },

    about: function (ctx, st) {
      return l1State(st) === 'form' ? ['help.lvx.about.happy'] : [];
    },

    tasks: {
      HH1: task(L1_X, 'HH1', 3, {
        target: function (ctx) {
          const list = l1Qa(ctx.doc, 'input[name^="startdato["]');
          return list.length ? list[list.length - 1] : null;
        },
        label: fieldLabel(L1_X, 'startdato'),
        kind: 'field'
      }),
      HH2: task(L1_X, 'HH2', 1, { target: 'input[name^="slet["]', label: showKey(L1_X, 'HH2'), kind: 'field' }),
      RV3: task(L1_X, 'RV3', 1, L1_X_BACK)
    },

    order: function (ctx, st) {
      if (l1State(st) !== 'form') return [];
      return l1Flags(st).harPriser ? ['HH1', 'HH2', 'RV3'] : ['HH1', 'RV3'];
    },

    fields: (function () {
      const out = {};
      ['startdato', 'slutdato', 'starttid', 'sluttid', 'salgspris', 'kostpris', 'ugedag'].forEach(function (k) { out[k] = fieldKeys(L1_X, k); });
      return out;
    })(),

    fieldKey: function (el) {
      const m = /^(startdato|slutdato|starttid|sluttid|salgspris|kostpris|ugedag)\[\d+\]$/.exec(attr(el, 'name'));
      return m ? m[1] : null;
    },

    shortcuts: [],

    questions: {},

    questionOrder: function () {
      return [];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/happyhour.php'] = l1Happy;

  // ==== Stock movements: goods receipt, stock moves, counts, batches, bills of materials and production (author L2; helpers l2)
  // Shared read-only helpers of the L2 pages. Frame elements come from another realm: nodeType/tagName, never instanceof.

  function l2q(root, sel) {
    try {
      const el = root ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function l2qa(root, sel) {
    try {
      return root ? Array.prototype.slice.call(root.querySelectorAll(sel)) : [];
    } catch (e) {
      return [];
    }
  }

  /** The trimmed value of a field, '' for anything else. */
  function l2val(el) {
    try {
      return el && el.nodeType === 1 && el.value !== undefined && el.value !== null ? String(el.value).trim() : '';
    } catch (e) {
      return '';
    }
  }

  function l2txt(el) {
    try {
      return el && el.nodeType === 1 ? String(el.textContent || '').replace(/\s+/g, ' ').trim() : '';
    } catch (e) {
      return '';
    }
  }

  /** A query parameter of the frame's address, '' when absent. */
  function l2param(ctx, name) {
    try {
      return String(ctx.url.searchParams.get(name) || '');
    } catch (e) {
      return '';
    }
  }

  /** A disabled control (the property in the browser, the attribute on any markup). */
  function l2disabled(el) {
    try {
      return !!el && el.nodeType === 1 && (el.disabled === true || el.getAttribute('disabled') !== null);
    } catch (e) {
      return false;
    }
  }

  /** The direct children of an element that are of one tag (HTMLCollection in the browser, a plain list elsewhere). */
  function l2kids(el, tag) {
    try {
      const out = [];
      const kids = el && el.children ? el.children : [];
      for (let i = 0; i < kids.length; i++) if (kids[i] && kids[i].nodeType === 1 && kids[i].tagName === tag) out.push(kids[i]);
      return out;
    } catch (e) {
      return [];
    }
  }

  /** The messages a classic page shows after a post: <BODY onLoad="javascript:alert(...)">, which the parser adds to the
   *  body (includes/online.php:579 prints the first body), and std_func.php alert(), a script element (:1956-1967). */
  function l2alerts(doc) {
    let s = attr(l2q(doc, 'body[onload]'), 'onload');
    if (!/alert\(/.test(s)) s = '';
    l2qa(doc, 'script').forEach(function (el) {
      const t = l2txt(el);
      if (/alert\(/.test(t) && t.length < 600) s += '\n' + t;
    });
    return s;
  }

  /** The state of a page for a show target: the one the shell hands over, else the page's own detect(). */
  function l2stateOf(detect, ctx, st) {
    if (st && st.state) return st;
    try {
      return detect(ctx);
    } catch (e) {
      return null;
    }
  }

  /** A show target that rings only in the states its task is listed in (spec §19.3: the chat's search offers every task). */
  function l2only(detect, states, target, flag) {
    return function (ctx, st) {
      const d = l2stateOf(detect, ctx, st);
      if (!d || states.indexOf(d.state) < 0) return null;
      if (flag && !(d.flags && d.flags[flag])) return null;
      return typeof target === 'function' ? target(ctx, d) : l2q(ctx.doc, target);
    };
  }

  /** Search vocabulary, never shown. */
  function l2words(da, en, no) {
    return { da: da, en: en, no: no };
  }

  // ---- Modtageliste (lager/modtageliste.php) and Varemodtagelse (lager/modtagelse.php)
  // Evidence, lager/modtageliste.php unless named: the sidebar Lager » Varemodtagelse (index/main.php:364-366, right 10) and
  // the front page's tile (index/dashboard.php:495). Tilbage accesskey L (:69-70, :85). Ny accesskey N: in the shell's
  // layout (menu S) a button with onClick window.open('modtagelse.php?returside=modtageliste.php&tjek=-1') inside
  // <a href=modtageliste.php?... accesskey=N> (:74-75), in the plain layout the td's onClick (:87): a new receipt opens in a
  // window of its own. Vis alle (636) / Vis egne (641) in the first td colspan=6 (:94-95); by default only the user's own
  // lists (:114-115). Open lists (modtaget '-') first, each Id a td whose onClick opens modtagelse.php?tjek=<id> in a new
  // window (:117-134); received lists ('V') last, the Id a link modtagelse.php?liste_id=<id> in the frame (:156-173; a
  // window only with the popup preference, :163). TIP 1 (961) and TIP 2 (962, colleagues have lists) when the user has
  // no lists (:174-180). Columns Id, Dato (635), Oprettet af (958), Modtaget af (959), Modtagelsesdato (960).
  // The receipt, lager/modtagelse.php: the form name=modtagelse posts to modtagelse.php?liste_id=<n>&id=<line> (:246);
  // columns Varenr. (917), Antal (916), Beskrivelse (914), Leveres (1190), Lager (608) (:249); a saved line has ret
  // (hard-coded, :255-256) while the list is open; the input row varenr / antal_ny / OK (:260-263) and modtag (hard-coded,
  // :264-265) once a line is saved; a received list has no input row. A new item number gets the open quantity of the
  // item's approved purchase orders (status 1-2, art KO) as its quantity (:105-181); Leveres is what open sales orders
  // wait for, Lager the rest (:142-148, :163-177). modtag() spreads each line over those purchase order lines, oldest
  // order first, into the line's warehouse and variant, with the line's batch number and expiry date, in one transaction
  // (:285-442); it posts nothing to the accounts and the order is posted from Kreditor (kreditor/ordre.php, the purchase
  // order counts received lines from batch_kob, kreditor/orderIncludes/openOrderLines.php:126-131). Refusals are
  // <BODY onLoad=alert()> (:100-101, :125-130, :183, :439). Header Tilbage accesskey L, Ny accesskey N with a list (:214-222,
  // :232-235). Serial numbers are not asked for here (modtag() writes batch_kob only).

  const L2_LMOD = 'lmod';

  function l2mlDetect(ctx) {
    const doc = ctx.doc;
    const open = l2qa(doc, 'td[onclick*="modtagelse.php?tjek="]');
    const recv = l2qa(doc, 'a[href^="modtagelse.php?liste_id="], td[onclick*="modtagelse.php?liste_id="]');
    const toggle = l2q(doc, 'td[colspan="6"] > a');
    const flags = {
      alle: l2param(ctx, 'vis') === 'alle' || (!!toggle && attr(toggle, 'href').indexOf('vis=alle') < 0),
      aabne: open.length > 0,
      modtagne: recv.length > 0,
      // TIP 2 (:177-178): the user has no lists of their own, colleagues have
      kolleger: l2qa(doc, 'td[colspan="3"]').some(function (td) { return /TIP 2/.test(l2txt(td)); })
    };
    return { state: open.length + recv.length ? 'liste' : 'tom', flags: flags, data: { aabne: open.length, modtagne: recv.length } };
  }

  /** Ny: the button of the shell's layout, else the access-key link (both layouts, :74-75, :87). */
  function l2mlNew(ctx) {
    return l2q(ctx.doc, 'button[onclick*="tjek=-1"]') || l2q(ctx.doc, 'a[accesskey="N"]');
  }

  const L2_ML_TASKS = {
    // [1] Ny opens modtagelse.php?tjek=-1 in a window (:75, :87); [2] OK with a new item number (modtagelse.php:105-181);
    // [3] OK again saves the quantity (:129-153); [4] modtag appears with a saved line (:264-265) and books the list (:285-442)
    ML1: task(L2_LMOD, 'ML1', 4, { target: l2mlNew, label: showKey(L2_LMOD, 'ML1'), kind: 'button' }),
    ML2: task(L2_LMOD, 'ML2', 2, { target: l2only(l2mlDetect, ['liste'], 'td[onclick*="modtagelse.php?tjek="]'), label: showKey(L2_LMOD, 'ML2'), kind: 'row' }),
    ML3: task(L2_LMOD, 'ML3', 2, { target: l2only(l2mlDetect, ['liste'], 'a[href^="modtagelse.php?liste_id="]'), label: showKey(L2_LMOD, 'ML3'), kind: 'row' }),
    ML4: task(L2_LMOD, 'ML4', 1, { target: function (ctx) {
      const a = l2q(ctx.doc, 'td[colspan="6"] > a');
      return a && attr(a, 'href').indexOf('vis=alle') >= 0 ? a : null;
    }, label: showKey(L2_LMOD, 'ML4'), kind: 'button' }),
    ML5: task(L2_LMOD, 'ML5', 1, { target: function (ctx) {
      const a = l2q(ctx.doc, 'td[colspan="6"] > a');
      return a && attr(a, 'href').indexOf('vis=alle') < 0 ? a : null;
    }, label: showKey(L2_LMOD, 'ML5'), kind: 'button' }),
    // The supplier's bill is posted on the purchase order: Fakturanr., Forventet lev. dato, Gem, Bogfør (kord KO10, KO12,
    // KO14; kreditor/orderIncludes/openOrderLines.php:340-345, the order with every line received). No Vis mig: Kreditor.
    ML6: task(L2_LMOD, 'ML6', 3),
    // Only approved purchase orders can be received (modtagelse.php:110, :326): kol KOL1. No Vis mig: the sidebar.
    ML7: task(L2_LMOD, 'ML7', 3)
  };

  /** The questions of both goods receipt pages (one group). */
  const L2_MOD_QUESTIONS = {
    Q1: {
      q: 'help.lmod.q.q1.q',
      words: l2words(['kan ikke modtage', 'ingen åbne', 'indkøbsordre', 'indkøbsordrer', 'købsordre', 'eksisterer ikke', 'findes ikke', 'varenummer', 'fejl'],
        ['cannot receive', 'no open', 'purchase order', 'purchase orders', 'does not exist', 'not found', 'item number', 'error'],
        ['kan ikke motta', 'ingen åpne', 'innkjøpsordre', 'innkjøpsordrer', 'kjøpsordre', 'finnes ikke', 'varenummer', 'feil']),
      intro: 'help.lmod.q.q1.intro',
      steps: stepKeys(L2_LMOD, 'ML7', 1, 3)
    },
    Q2: {
      q: 'help.lmod.q.q2.q',
      words: l2words(['forskel', 'modtag', 'købsordre', 'serienumre', 'serienummer', 'batch', 'flere ordrer', 'scanner'],
        ['difference', 'receive', 'purchase order', 'serial numbers', 'serial number', 'batch', 'several orders', 'scanner'],
        ['forskjell', 'motta', 'kjøpsordre', 'serienumre', 'serienummer', 'batch', 'flere ordrer', 'skanner']),
      intro: 'help.lmod.q.q2.intro',
      steps: ['help.lmod.q.q2.step1', 'help.lmod.q.q2.step2']
    },
    Q3: {
      q: 'help.lmod.q.q3.q',
      words: l2words(['bogført', 'bogføres', 'regnskab', 'varekøb', 'regning', 'faktura', 'postering', 'moms'],
        ['posted', 'accounts', 'purchases', 'bill', 'invoice', 'entry', 'vat'],
        ['bokført', 'bokføres', 'regnskap', 'varekjøp', 'regning', 'faktura', 'postering', 'mva']),
      intro: 'help.lmod.q.q3.intro',
      steps: stepKeys(L2_LMOD, 'ML6', 1, 3)
    }
  };

  const l2Modtageliste = {
    group: L2_LMOD,
    states: ['tom', 'liste'],
    flags: ['alle', 'aabne', 'modtagne', 'kolleger'],

    name: function () {
      return 'help.lmod.name';
    },

    detect: l2mlDetect,

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'tom') {
        if (f.alle) return ['help.lmod.about.tom_alle', 'help.lmod.about.hvad'];
        return f.kolleger ? ['help.lmod.about.tom', 'help.lmod.about.kolleger'] : ['help.lmod.about.tom', 'help.lmod.about.hvad'];
      }
      if (state !== 'liste') return [];
      return f.alle ? ['help.lmod.about.liste', 'help.lmod.about.alle'] : ['help.lmod.about.liste', 'help.lmod.about.egne'];
    },

    tasks: L2_ML_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state === 'tom') {
        ids.push('ML1', 'ML7');
        if (f.kolleger && !f.alle) ids.push('ML4');
        return unique(ids);
      }
      if (state !== 'liste') return [];
      if (f.aabne) ids.push('ML2');
      ids.push('ML1');
      if (f.modtagne) ids.push('ML6', 'ML3');
      ids.push(f.alle ? 'ML5' : 'ML4', 'ML7');
      return unique(ids);
    },

    fields: {},

    // Tilbage accesskey L (:69, :85). Ny has accesskey N on its link, but in the shell's layout the new window is opened by the
    // button inside the link (:74-75), which the access key does not click: Alt+N only reloads the list, so it is not listed.
    shortcuts: [
      { keys: 'Alt+L', text: 'help.lmod.key.01', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: L2_MOD_QUESTIONS,

    // Without receipts a newcomer asks what the page is for next to the purchase order (Q2)
    questionOrder: function (ctx, st) {
      const f = (st && st.flags) || {};
      if (st && st.state === 'tom') return ['Q2', 'Q1', 'Q3'];
      return f.modtagne ? ['Q3', 'Q2', 'Q1'] : ['Q1', 'Q2', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/modtageliste.php'] = l2Modtageliste;

  function l2vmDetect(ctx) {
    const doc = ctx.doc;
    const form = l2q(doc, 'form[name="modtagelse"]');
    const action = attr(form, 'action');
    const lm = /liste_id=(\d+)/.exec(action);
    const im = /[?&]id=(\d+)/.exec(action);
    const listeId = lm ? lm[1] : (l2param(ctx, 'liste_id') || '0');
    const input = l2q(doc, 'input[name="varenr"]');
    const modtag = l2q(doc, 'input[name="modtag"]');
    const ret = l2qa(doc, 'a[href^="modtagelse.php?liste_id="]').filter(function (a) { return /[?&]id=\d+/.test(attr(a, 'href')); });
    const flags = {
      fejl: l2alerts(doc) !== '',
      kanModtage: !!modtag,
      linjer: ret.length > 0 || !!modtag
    };
    let state = 'unknown';
    if (form || input) {
      if (!input) state = 'modtaget';
      else if (im && im[1] !== '0' && l2val(input) !== '') state = 'ret';
      else if (listeId === '0' && !flags.linjer) state = 'ny';
      else state = 'aaben';
    }
    return { state: state, flags: flags, data: { listeId: listeId !== '0' ? listeId : '' } };
  }

  /** The ret link of the first saved line (:255-256). */
  function l2vmRet(ctx) {
    const a = l2qa(ctx.doc, 'a[href^="modtagelse.php?liste_id="]').filter(function (x) { return /[?&]id=\d+/.test(attr(x, 'href')); });
    return a.length ? a[0] : null;
  }

  const L2_VM_TASKS = {
    // [1]-[3] the input row and OK (:260-263, :105-181); Leveres/Lager (:142-148)
    VM1: task(L2_LMOD, 'VM1', 3, { target: l2only(l2vmDetect, ['ny', 'aaben'], 'input[name="varenr"]'), label: showKey(L2_LMOD, 'VM1'), kind: 'field' }),
    // modtag (:264-265, :188-190), modtag() (:285-442); a received list has no input row (:260)
    VM2: task(L2_LMOD, 'VM2', 3, { target: l2only(l2vmDetect, ['aaben', 'ret'], 'input[name="modtag"]'), label: showKey(L2_LMOD, 'VM2'), kind: 'button' }),
    // ret (:255-256) puts the line in the input row (:251, :261-263); OK saves it (:263, :129-153); 0 or empty deletes it
    // (:150-151, modtagelseAntal() :474-477). Vis mig: ret on an open list, the line's Antal (antal_ny) while it is changed.
    VM3: task(L2_LMOD, 'VM3', 2, { target: function (ctx, st) {
      const d = l2stateOf(l2vmDetect, ctx, st);
      if (!d) return null;
      if (d.state === 'ret') return l2q(ctx.doc, 'input[name="antal_ny"]');
      return d.state === 'aaben' ? l2vmRet(ctx) : null;
    }, label: showKey(L2_LMOD, 'VM3'), kind: 'button' }),
    // The refusals (:99-102, :121-131, :182-184) and the aborted receipt (5461-5467, :425-441). No Vis mig: an alert.
    VM4: task(L2_LMOD, 'VM4', 4),
    // Ny in the header with a list (:221-222, :234-235)
    VM5: task(L2_LMOD, 'VM5', 2, { target: l2only(l2vmDetect, ['modtaget', 'aaben'], 'a[href="modtagelse.php"]'), label: showKey(L2_LMOD, 'ML1'), kind: 'button' }),
    ML6: L2_ML_TASKS.ML6,
    ML7: L2_ML_TASKS.ML7
  };

  const l2Modtagelse = {
    group: L2_LMOD,
    states: ['ny', 'aaben', 'ret', 'modtaget'],
    flags: ['fejl', 'kanModtage', 'linjer'],

    name: function (ctx, st) {
      const id = st && st.data && st.data.listeId ? String(st.data.listeId) : '';
      if (st && st.state === 'ny') return 'help.lmod.name_ny';
      return id ? ['help.lmod.name_modtagelse', id] : 'help.lmod.name_plain';
    },

    detect: l2vmDetect,

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const first = { ny: 'help.lmod.about.ny', aaben: 'help.lmod.about.aaben', ret: 'help.lmod.about.ret', modtaget: 'help.lmod.about.modtaget' }[state];
      if (!first) return [];
      if (f.fejl && state !== 'modtaget') return ['help.lmod.about.fejl', first];
      if (state === 'modtaget') return [first, 'help.lmod.about.bogfoering'];
      if (state === 'aaben' && f.kanModtage) return [first, 'help.lmod.about.leveres'];
      return [first];
    },

    tasks: L2_VM_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (f.fejl && state !== 'modtaget' && state !== 'unknown') ids.push('VM4');
      if (state === 'ny') ids.push('VM1', 'ML7');
      else if (state === 'aaben') {
        if (f.kanModtage) ids.push('VM2');
        ids.push('VM1');
        if (f.linjer) ids.push('VM3');
        ids.push('ML7');
      } else if (state === 'ret') {
        ids.push('VM3');
        if (f.kanModtage) ids.push('VM2');
      } else if (state === 'modtaget') ids.push('ML6', 'VM5');
      return unique(ids);
    },

    problem: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      return st && st.flags && st.flags.fejl && ['ny', 'aaben', 'ret'].indexOf(state) >= 0 ? 'VM4' : '';
    },

    fields: {
      varenr: fieldKeys(L2_LMOD, 'varenr'),
      antal_ny: fieldKeys(L2_LMOD, 'antal_ny')
    },

    // Tilbage accesskey L and, with a list, Ny accesskey N (:214-222, :232-235); Enter in a field is OK
    shortcuts: [
      { keys: 'Enter', text: 'help.lmod.key.02', group: 'keys' },
      { keys: 'Alt+N', text: 'help.lmod.key.03', group: 'keys' },
      { keys: 'Alt+L', text: 'help.lmod.key.04', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: L2_MOD_QUESTIONS,

    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      if (state === 'modtaget') return ['Q3', 'Q2'];
      return ['Q1', 'Q2', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/modtagelse.php'] = l2Modtagelse;

  // ---- Flyt vare (lager/lagerflyt.php)
  // Evidence, lager/lagerflyt.php unless named: the stock figure under a warehouse in the item list is a link
  // lagerflyt.php?lager=<n>&vare_id=<id> with the title Flyt til andet lager (lager/varer.php:951,
  // lager/varerIncludes/itemPrint.php:222, lager/lister/vareliste.php:329-331). The page: Flyt vare <varenr> fra lager <n>
  // (hard-coded, :216); Beh., Antal, Til lager (hard-coded, :218); antal (value 0), the select nyt_lager with every other
  // warehouse (:221-228); hidden max_antal = the stock of the warehouse (:212-214, :231); Opdater name=opdater and Luk, a link
  // to varer.php (:233). A read-only right gets the heading and Beh. only, with no form (MB-62, :225-226), and its POST is
  // refused (:55-64). Opdater with antal >= 1 (:70) and at most
  // max_antal (:82) takes the quantity from the warehouse's batches, oldest first (:88-122), and puts one new batch on the
  // new warehouse at their average cost price (:123, :162-167), without batch number or expiry date; the stock rows follow
  // (:169-184). Nothing is posted to the accounts. Messages through alert(), a script (std_func.php:1956-1967): moved
  // (:191), too many (:189).

  const L2_LFLYT = 'lflyt';

  function l2flDetect(ctx) {
    const doc = ctx.doc;
    const antal = l2q(doc, 'input[name="antal"]');
    const opdater = l2q(doc, 'input[name="opdater"]');
    const select = l2q(doc, 'select[name="nyt_lager"]');
    const max = parseFloat(l2val(l2q(doc, 'input[name="max_antal"]')));
    const msg = l2alerts(doc);
    const flags = {
      ingenAndre: !!select && l2qa(select, 'option').length === 0,
      tom: !(max > 0),
      flyttet: msg.indexOf('flyttet fra lager') >= 0,
      forMange: msg.indexOf('Du kan maks flytte') >= 0
    };
    const m = /^Flyt vare (.+) fra lager (\d+)$/.exec(l2txt(l2q(doc, 'td[colspan="4"] b')));
    let state = 'unknown';
    if (antal || opdater) state = l2disabled(antal) || l2disabled(opdater) ? 'kunLaes' : 'flyt';
    // MB-62: a read-only right gets the heading and Beh. without Antal or Opdater (lagerflyt.php:225-226)
    else if (m) state = 'kunLaes';
    return { state: state, flags: flags, data: { varenr: m ? m[1] : '' } };
  }

  const L2_FL_TASKS = {
    // [1] antal, at least 1 and at most Beh. (:70, :82); [2] Til lager (:222-228); [3] Opdater (:233, :191)
    // FL1, FL3: with one warehouse (ingenAndre, FL5) there is nowhere to move to, so they ring nothing (live check 24.15)
    FL1: task(L2_LFLYT, 'FL1', 3, { target: l2only(l2flDetect, ['flyt'], function (ctx, d) { return d.flags && d.flags.ingenAndre ? null : l2q(ctx.doc, 'input[name="opdater"]'); }), label: showKey(L2_LFLYT, 'FL1'), kind: 'button' }),
    // The new batch has no batch_no/due_date (:163-166); lager/batch_oversigt.php, batch_udfyld.php. No Vis mig: another page.
    FL2: task(L2_LFLYT, 'FL2', 3),
    // Du kan maks flytte (:188-189)
    FL3: task(L2_LFLYT, 'FL3', 2, { target: l2only(l2flDetect, ['flyt'], function (ctx, d) { return d.flags && d.flags.ingenAndre ? null : l2q(ctx.doc, 'input[name="antal"]'); }), label: showKey(L2_LFLYT, 'FL3'), kind: 'field' }),
    // Beh. 0: nothing to move from this warehouse; Luk to the item list (:233)
    FL4: task(L2_LFLYT, 'FL4', 2, { target: 'a[href="varer.php"]', label: showKey(L2_LFLYT, 'FL4'), kind: 'button' }),
    // Only one warehouse: the select is empty (:223-227); Lagre: includes/settings/hub.php:205. No Vis mig: Indstillinger.
    FL5: task(L2_LFLYT, 'FL5', 2),
    // After the move the page shows the new Beh. (:212-214, :220)
    FL6: task(L2_LFLYT, 'FL6', 2, { target: 'a[href="varer.php"]', label: showKey(L2_LFLYT, 'FL4'), kind: 'button' }),
    // Read-only (:55-67, :225-226); Brugere & roller (includes/settings/hub.php:76-84). No Vis mig.
    FL7: task(L2_LFLYT, 'FL7', 2)
  };

  const l2Lagerflyt = {
    group: L2_LFLYT,
    states: ['flyt', 'kunLaes'],
    flags: ['ingenAndre', 'tom', 'flyttet', 'forMange'],

    name: function (ctx, st) {
      const v = st && st.data && st.data.varenr ? String(st.data.varenr) : '';
      return v ? ['help.lflyt.name', v] : 'help.lflyt.name_plain';
    },

    detect: l2flDetect,

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'kunLaes') return ['help.lflyt.about.kun_laes'];
      if (state !== 'flyt') return [];
      if (f.flyttet) return ['help.lflyt.about.flyttet', 'help.lflyt.about.bogfoering'];
      if (f.ingenAndre) return ['help.lflyt.about.ingen_andre'];
      if (f.tom) return ['help.lflyt.about.tom'];
      return ['help.lflyt.about.flyt', 'help.lflyt.about.bogfoering'];
    },

    tasks: L2_FL_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'kunLaes') return ['FL7'];
      if (state !== 'flyt') return [];
      const ids = [];
      if (f.forMange) ids.push('FL3');
      if (f.flyttet) ids.push('FL6', 'FL2');
      else if (f.tom) ids.push('FL4');
      if (f.ingenAndre) ids.push('FL5');
      if (!f.tom && !f.ingenAndre) ids.push('FL1', 'FL2');
      return unique(ids);
    },

    problem: function (ctx, st) {
      const f = (st && st.flags) || {};
      if (!st || st.state !== 'flyt') return '';
      if (f.forMange) return 'FL3';
      if (f.ingenAndre) return 'FL5';
      if (f.tom && !f.flyttet) return 'FL4';
      return '';
    },

    fields: {
      antal: fieldKeys(L2_LFLYT, 'antal'),
      nyt_lager: fieldKeys(L2_LFLYT, 'nyt_lager')
    },

    // No access keys on the page; Enter in Antal is Opdater (the form's only submit, :233)
    shortcuts: [
      { keys: 'Enter', text: 'help.lflyt.key.01', group: 'keys' }
    ],

    questions: {
      Q1: {
        q: 'help.lflyt.q.q1.q',
        words: l2words(['bogført', 'bogføres', 'regnskab', 'lagerværdi', 'kostpris', 'postering'],
          ['posted', 'accounts', 'stock value', 'cost price', 'entry'],
          ['bokført', 'bokføres', 'regnskap', 'lagerverdi', 'kostpris', 'postering']),
        intro: 'help.lflyt.q.q1.intro',
        steps: ['help.lflyt.q.q1.step1', 'help.lflyt.q.q1.step2']
      },
      Q2: {
        q: 'help.lflyt.q.q2.q',
        words: l2words(['flyt', 'flytte', 'andet lager', 'lagre', 'overfør', 'overføre', 'lager'],
          ['move', 'transfer', 'another warehouse', 'warehouses', 'warehouse'],
          ['flytt', 'flytte', 'annet lager', 'lagre', 'overfør', 'overføre', 'lager']),
        intro: 'help.lflyt.q.q2.intro',
        steps: stepKeys(L2_LFLYT, 'FL1', 1, 3),
        show: L2_FL_TASKS.FL1.show
      },
      // Q3: the moved quantity gets a new batch_kob row without batch_no or due_date (:160-166); FL2's steps fill them in
      Q3: {
        q: 'help.lflyt.q.q3.q',
        words: l2words(['batch', 'batchnummer', 'udløbsdato', 'udløb', 'følger med', 'mangler', 'holdbarhed'],
          ['batch', 'batch number', 'expiry date', 'expiry', 'follow', 'missing', 'shelf life'],
          ['batch', 'batchnummer', 'utløpsdato', 'utløp', 'følger med', 'mangler', 'holdbarhet']),
        intro: 'help.lflyt.q.q3.intro',
        steps: stepKeys(L2_LFLYT, 'FL2', 2, 3)
      }
    },

    // Q2 repeats FL1, the first task under Lær siden at kende, so it comes last
    questionOrder: function () {
      return ['Q1', 'Q3', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/lagerflyt.php'] = l2Lagerflyt;

  // ---- Lageroptælling (lager/optalling.php)
  // Evidence, lager/optalling.php unless named: Lager » Rapporter (lager/rapport.php) » Lageroptælling, a submit
  // name=inventoryCount (:463) that redirects to optalling.php in the frame (:206). Header (menu S): Luk accesskey L to
  // rapport.php (:203-204), Importér (1356) to optalling.php?importer=1, Afbryd (81) while importing (:209-210); plain
  // layout the same as links (:217-221). The top form: Dato (438), Lager (608, a select only with warehouses, :363-378),
  // Varenummer / Stregkode (2193) name=varenr, OK (:360-386); varenr is matched without case, with % and _ (:119-137,
  // title 2194-2196); an item with variants is refused (alert, :122-126), an unknown one too (:133). A found item: Varenr.,
  // Beskrivelse, Beholdning (980) at the date (:283, :295-324), Ny beholdning (2243) name=optalt, OK and Fortryd (159)
  // (:352-358, :387-388). OK writes an unposted count (regulering, :244-259). The list (vis_optalling, :427-571): each count
  // with a red X that deletes the item's unposted counts (:509, :535, :111-113), Klik her (2197, 2157) for the items not
  // counted (:559), Klik her to empty the list (:563-564, :115-116). With counts the form to post: Dato for optælling,
  // the box nulstil_ej_optalt and Bogfør (hard-coded, :402-409). Bogfør (bogfor=1) shows what is regulated, red rows not
  // counted and set to 0, green by the count (:632-636, :713-829), and the link bogfor=2 (:995-996); items whose stock
  // changed between two counts must be counted again, or accepted with godkend_regdif=1 (:691-712). bogfor=2 changes the
  // stock (:830-905, :983-993) and shows Lagerregulering udført (:999-1000). The count posts nothing to the accounts:
  // $aut_lager is always on (:630), so the entries of :910-948 are never written. Several counts of one item add up
  // (:679-681). Ikke-optalte varer (vis_ej_optalt=1, :573-621): each item a link optalling.php?varenr=...&dato=... without
  // the warehouse (:612). Import (importer=1, :1207-1425): Dato for optælling (2206), Vælg datafil (1364), Hent (1078); two
  // columns, item number or barcode (also a variant's barcode, :1345-1351) and quantity (:1404-1405); each import adds
  // counts (:1369-1371); afterwards vis_ej_exist=1 links Ikke oprettede varer (hard-coded, :225-226, :1396-1397).

  const L2_LOPT = 'lopt';

  function l2loDetect(ctx) {
    const doc = ctx.doc;
    const msg = l2alerts(doc);
    const lagerSel = l2q(doc, 'select[name="lager"]');
    const flags = {
      harOptalt: !!l2q(doc, 'input[name="nulstil_ej_optalt"]'),
      flereLagre: !!lagerSel && l2qa(lagerSel, 'option').length > 1,
      ikkeOprettet: !!l2q(doc, 'a[href*="optael_ej_exist"]'),
      varianter: msg.indexOf('indeholder varianter') >= 0,
      ikkeFundet: /varenr: [^\n]*ikke fundet/.test(msg)
    };
    let state = 'start';
    if (l2q(doc, 'input[name="uploadfile"]')) state = 'import';
    else if (l2param(ctx, 'bogfor') === '2' || msg.indexOf('Lagerregulering udf') >= 0) state = 'udfoert';
    else if (l2q(doc, 'a[href*="bogfor=2"]')) state = 'kontrol';
    else if (l2q(doc, 'a[href*="godkend_regdif=1"]')) state = 'gentael';
    else if (l2q(doc, 'input[name="optalt"]')) state = 'vare';
    else if (l2param(ctx, 'vis_ej_optalt') === '1' || l2q(doc, 'a[href^="optalling.php?varenr="]')) state = 'ikkeOptalt';
    else if (flags.harOptalt) state = 'liste';
    else if (!l2q(doc, 'input[name="varenr"]')) state = 'unknown';
    return { state: state, flags: flags, data: {} };
  }

  /** The Bogfør button of the count (hard-coded value, :408). */
  const L2_LO_POST = 'input[type="submit"][value="Bogfør"]';

  const L2_LO_TASKS = {
    // [1] Dato and Lager (:361-378, :295-317); [2] Varenummer / Stregkode and OK (:383-386); [3] Ny beholdning (:353)
    LO1: task(L2_LOPT, 'LO1', 3, { target: l2only(l2loDetect, ['start', 'liste', 'ikkeOptalt'], 'input[name="varenr"]'), label: showKey(L2_LOPT, 'LO1'), kind: 'field' }),
    // Beholdning at the date (:283, :347-353), Ny beholdning and OK (:244-259), Fortryd (:387-388)
    LO2: task(L2_LOPT, 'LO2', 3, { target: l2only(l2loDetect, ['vare'], 'input[name="optalt"]'), label: showKey(L2_LOPT, 'LO2'), kind: 'field' }),
    // The X (:509, :535), Dato for optælling and the box (:406-407), Bogfør (:408), the final link (:995-996)
    LO3: task(L2_LOPT, 'LO3', 4, { target: l2only(l2loDetect, ['liste', 'vare', 'ikkeOptalt'], L2_LO_POST), label: showKey(L2_LOPT, 'LO3'), kind: 'button' }),
    // The preview (:713-829) and the link to bogfor=2 (:995-996); the backup advice the page prints (:633)
    LO4: task(L2_LOPT, 'LO4', 4, { target: l2only(l2loDetect, ['kontrol'], 'a[href*="bogfor=2"]'), label: showKey(L2_LOPT, 'LO4'), kind: 'button' }),
    // Klik her (2197, 2157) for liste over ikke-optalte varer (:559)
    LO5: task(L2_LOPT, 'LO5', 2, { target: l2only(l2loDetect, ['start', 'liste'], 'a[href*="vis_ej_optalt=1"]'), label: showKey(L2_LOPT, 'LO5'), kind: 'button' }),
    // The item links of Ikke-optalte varer drop the warehouse (:612; $lager falls back to 1, :77-81)
    LO6: task(L2_LOPT, 'LO6', 3, { target: l2only(l2loDetect, ['ikkeOptalt'], 'a[href^="optalling.php?varenr="]'), label: showKey(L2_LOPT, 'LO6'), kind: 'row' }),
    // Importér (:209-210, :220), the form (:1401-1420), each import adds counts (:1369-1371, :679-681)
    LO7: task(L2_LOPT, 'LO7', 3, { target: l2only(l2loDetect, ['start', 'liste', 'vare', 'ikkeOptalt'], 'a[href*="importer=1"]'), label: showKey(L2_LOPT, 'LO7'), kind: 'button' }),
    // The import form: Dato for optælling (2206), Vælg datafil (1364), Hent (1078), its only submit (:1406-1419); the result
    // alert (:1396)
    LO15: task(L2_LOPT, 'LO15', 3, { target: l2only(l2loDetect, ['import'], 'input[type="submit"]'), label: showKey(L2_LOPT, 'LO15'), kind: 'button' }),
    // Ikke oprettede varer opens a text file in a new tab (:225-226)
    LO8: task(L2_LOPT, 'LO8', 2, { target: 'a[href*="optael_ej_exist"]', label: showKey(L2_LOPT, 'LO8'), kind: 'button' }),
    // nulstil=1 with a confirm (:563-564, :115-116)
    LO9: task(L2_LOPT, 'LO9', 2, { target: l2only(l2loDetect, ['liste'], 'a[href*="nulstil=1"]'), label: showKey(L2_LOPT, 'LO9'), kind: 'button' }),
    // The red X with a confirm (:509, :535, :111-113)
    LO10: task(L2_LOPT, 'LO10', 2, { target: l2only(l2loDetect, ['liste', 'vare'], 'a[href*="slet=y"]'), label: showKey(L2_LOPT, 'LO10'), kind: 'button' }),
    // The recount (:691-712): the X of the item, or Klik her with godkend_regdif=1 (:707)
    LO11: task(L2_LOPT, 'LO11', 3, { target: l2only(l2loDetect, ['gentael'], 'a[href*="slet=y"]'), label: showKey(L2_LOPT, 'LO10'), kind: 'button' }),
    // No entries in the accounts (:630, :910-948); Lagerstatus in lager/rapport.php (:453) with Dato and Lager
    // (lager/lagerstatus.php:414, :432). No Vis mig: another page.
    LO12: task(L2_LOPT, 'LO12', 3),
    // An item with variants is refused here (:122-126); the import takes a variant's barcode (:1345-1351, :1375-1384)
    LO13: task(L2_LOPT, 'LO13', 2, { target: l2only(l2loDetect, ['start', 'liste'], 'a[href*="importer=1"]'), label: showKey(L2_LOPT, 'LO7'), kind: 'button' }),
    // varenr: ... ikke fundet (:128-135): case-insensitive, % and _ (:122, :128-129, 2194-2196)
    LO14: task(L2_LOPT, 'LO14', 3, { target: l2only(l2loDetect, ['start', 'liste'], 'input[name="varenr"]'), label: showKey(L2_LOPT, 'LO1'), kind: 'field' })
  };

  const l2Optalling = {
    group: L2_LOPT,
    states: ['start', 'vare', 'liste', 'ikkeOptalt', 'import', 'kontrol', 'gentael', 'udfoert'],
    flags: ['harOptalt', 'flereLagre', 'ikkeOprettet', 'varianter', 'ikkeFundet'],

    name: function () {
      return 'help.lopt.name';
    },

    detect: l2loDetect,

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'start') {
        if (f.ikkeOprettet) return ['help.lopt.about.ikke_oprettet', 'help.lopt.about.start'];
        return f.flereLagre ? ['help.lopt.about.start', 'help.lopt.about.lager'] : ['help.lopt.about.start'];
      }
      if (state === 'liste') {
        if (f.ikkeOprettet) return ['help.lopt.about.ikke_oprettet', 'help.lopt.about.liste'];
        return f.flereLagre ? ['help.lopt.about.liste', 'help.lopt.about.lager'] : ['help.lopt.about.liste'];
      }
      if (state === 'vare') return ['help.lopt.about.vare'];
      if (state === 'ikkeOptalt') return f.flereLagre ? ['help.lopt.about.ikke_optalt', 'help.lopt.about.ikke_optalt_lager'] : ['help.lopt.about.ikke_optalt'];
      if (state === 'import') return ['help.lopt.about.import'];
      if (state === 'kontrol') return ['help.lopt.about.kontrol', 'help.lopt.about.finans'];
      if (state === 'gentael') return ['help.lopt.about.gentael'];
      if (state === 'udfoert') return ['help.lopt.about.udfoert', 'help.lopt.about.finans'];
      return [];
    },

    tasks: L2_LO_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (['start', 'liste'].indexOf(state) >= 0) {
        if (f.varianter) ids.push('LO13');
        if (f.ikkeFundet) ids.push('LO14');
        if (f.ikkeOprettet) ids.push('LO8');
      }
      if (state === 'start') ids.push('LO1', 'LO7', 'LO5');
      else if (state === 'liste') ids.push('LO1', 'LO5', 'LO3', 'LO10', 'LO7', 'LO9', 'LO12');
      else if (state === 'vare') {
        ids.push('LO2');
        if (f.harOptalt) ids.push('LO10', 'LO3');
      } else if (state === 'ikkeOptalt') {
        ids.push('LO6', 'LO1');
        if (f.harOptalt) ids.push('LO3');
      } else if (state === 'import') ids.push('LO15');
      else if (state === 'kontrol') ids.push('LO4', 'LO12');
      else if (state === 'gentael') ids.push('LO11');
      else if (state === 'udfoert') ids.push('LO12');
      return unique(ids);
    },

    problem: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'gentael') return 'LO11';
      if (state === 'start' || state === 'liste') {
        if (f.varianter) return 'LO13';
        if (f.ikkeFundet) return 'LO14';
      }
      return '';
    },

    fields: {
      varenr: fieldKeys(L2_LOPT, 'varenr'),
      optalt: fieldKeys(L2_LOPT, 'optalt'),
      dato: fieldKeys(L2_LOPT, 'dato'),
      lager: fieldKeys(L2_LOPT, 'lager'),
      nulstil_ej_optalt: fieldKeys(L2_LOPT, 'nulstil_ej_optalt'),
      uploadfile: fieldKeys(L2_LOPT, 'uploadfile')
    },

    // Luk accesskey L (:203, :217); Enter in a field is OK
    shortcuts: [
      { keys: 'Enter', text: 'help.lopt.key.01', group: 'keys' },
      { keys: 'Alt+L', text: 'help.lopt.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.lopt.q.q1.q',
        words: l2words(['bogført', 'bogføres', 'regnskab', 'lagerværdi', 'lagerkonto', 'postering', 'svind', 'regulering', 'finans'],
          ['posted', 'accounts', 'stock value', 'stock account', 'entry', 'shrinkage', 'adjustment', 'ledger'],
          ['bokført', 'bokføres', 'regnskap', 'lagerverdi', 'lagerkonto', 'postering', 'svinn', 'regulering', 'finans']),
        intro: 'help.lopt.q.q1.intro',
        steps: stepKeys(L2_LOPT, 'LO12', 2, 3)
      },
      Q2: {
        q: 'help.lopt.q.q2.q',
        words: l2words(['flere steder', 'to steder', 'samme vare', 'lægges sammen', 'dobbelt', 'to gange', 'import'],
          ['several places', 'two places', 'same item', 'added up', 'twice', 'double', 'import'],
          ['flere steder', 'to steder', 'samme vare', 'legges sammen', 'dobbelt', 'to ganger', 'import']),
        intro: 'help.lopt.q.q2.intro',
        steps: ['help.lopt.q.q2.step1', 'help.lopt.q.q2.step2']
      },
      Q3: {
        q: 'help.lopt.q.q3.q',
        words: l2words(['varianter', 'variant', 'størrelse', 'farve', 'stregkode', 'indeholder varianter'],
          ['variants', 'variant', 'size', 'colour', 'color', 'barcode'],
          ['varianter', 'variant', 'størrelse', 'farge', 'strekkode']),
        intro: 'help.lopt.q.q3.intro',
        steps: stepKeys(L2_LOPT, 'LO13', 1, 2)
      },
      // Q4: Bogfør regulates only the counted items (reg_vare_id) unless the box Sæt beholdning til 0 for alle ikke optalte
      // varer på lager <n> is ticked (:407, :742); then every uncounted item with stock on that warehouse goes to 0 and the
      // preview shows it as a red line (:713, :807-821). Nothing changes before the final link (:995-996).
      Q4: {
        q: 'help.lopt.q.q4.q',
        words: l2words(['ikke talt', 'ikke optalt', 'ikke optalte', 'mangler', 'glemt', 'nul', 'sæt til 0', 'røde linjer', 'resten'],
          ['not counted', 'uncounted', 'missing', 'forgot', 'zero', 'set to 0', 'red lines', 'the rest'],
          ['ikke telt', 'ikke opptalt', 'ikke-opptalte', 'mangler', 'glemt', 'null', 'sett til 0', 'røde linjer', 'resten']),
        intro: 'help.lopt.q.q4.intro',
        steps: ['help.lopt.q.q4.step1', 'help.lopt.q.q4.step2'],
        show: { target: 'input[name="nulstil_ej_optalt"]', label: fieldLabel(L2_LOPT, 'nulstil_ej_optalt'), kind: 'field' }
      }
    },

    // The list and the uncounted items lead with Q4 (the box on the posting form); the preview and the result with Q1
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (f.varianter) return ['Q3', 'Q2', 'Q1'];
      if (state === 'import') return ['Q2', 'Q3', 'Q1'];
      if (state === 'liste' || state === 'ikkeOptalt') return ['Q4', 'Q1', 'Q2'];
      if (state === 'kontrol' || state === 'udfoert') return ['Q1', 'Q4', 'Q2'];
      return ['Q2', 'Q4', 'Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/optalling.php'] = l2Optalling;

  // ---- Batch oversigt (lager/batch_oversigt.php) and Batchoplysninger (lager/batch_udfyld.php)
  // Evidence, lager/batch_oversigt.php unless named: the item card's Batch oversigt (5004) button in the Udløbsdato box,
  // for a saved stock item with the batch reports right (lager/productCardIncludes/showExpirySettings.php:59-69; the box
  // needs Anvend batch- og udløbsdatostyring and Batchkontrol on the item group, lager/varekort.php:1863-1871), and the
  // expiry report's Batch oversigt per item (lager/udlobsrapport.php:231-233). Tilbage accesskey L (:65, :75, :83). The
  // item <varenr> : <beskrivelse> (:90); the count of batches missing details in a span coloured #b45309 (:94-96,
  // includes/stdFunc/fefo.php:234-243) and Ret batchoplysninger (batch_txt edit) to batch_udfyld.php for a user with write
  // on Varer (:93, :97, fefo.php:213-222). Columns Batch ID, Batchnr. (5005), Udløbsdato (5001), Dage til udløb (5007),
  // Restlager (5008), Købsdato (5009), Lager (5010), Købsordre (5011) (:102-111); one row per batch with stock, FEFO order:
  // expiry date first, none last (:123, fefo.php:46-48); red #ffcccc expired, yellow #ffffcc within the user's warning days,
  // green #ccffcc (:134-138, fefo.php:113-133; Advar om udløb (dage før), systemdata/personalSettings.php:631-636);
  // Total restlager (5013) (:163). Købsordre is empty for a batch without an order: a count (lager/optalling.php:848), a move
  // (lager/lagerflyt.php:163), a stock change on the item card (includes/std_func.php:1724-1727), a production
  // (lager/vareproduktion.php:115-117).
  // lager/batch_udfyld.php: Batchoplysninger (batch_txt title); Tilbage accesskey L (:229, :238, :246). Without an item (the
  // expiry report's Udfyld nu, lager/udlobsrapport.php:110-114) the intro, Vis alle batches på lager / Vis kun batches, der
  // mangler oplysninger in div.bu-info and the search sog with Søg (913) (:308-320); the list #bu-table: Varenr, Beskrivelse,
  // Lager, Købsdato, Restlager (with (n res.) reserved), Batchnr. batch_no[id], Udløbsdato due_date[id], Opdel
  // (:341-379); Foreslå udløbsdato ud fra købsdato og standardholdbarhed when a batch has a shelf life (:329-339, :399-403);
  // Opdel adds a line Ny batch with Antal, batch number, date and Fjern (:404-421); Gem (3) (:391); 100 per page (:382-390).
  // Gem checks everything first and saves all or nothing (:120-209): messages div.bu-err (:269), saved div.bu-ok (:270);
  // a split can take at most the rest less what open orders reserve (:169-176); only batch number, expiry date and the split
  // change, and a line goes to the stock log (:25-29, :185-202). Nothing to fill in: a paragraph (:322-327).

  const L2_LBAT = 'lbat';

  /** The batch rows of Batch oversigt: eight cells, the first the Batch ID (:150-159). */
  function l2boRows(doc) {
    return l2qa(doc, 'tr').filter(function (tr) {
      const tds = l2kids(tr, 'TD');
      return tds.length === 8 && /^\d+$/.test(l2txt(tds[0]));
    });
  }

  function l2boDetect(ctx) {
    const doc = ctx.doc;
    const rows = l2boRows(doc);
    const head = /^(.+?) : /.exec(l2txt(l2q(doc, 'td[colspan="8"] > b')));
    const flags = {
      udloebet: !!l2q(doc, 'tr[style*="ffcccc"]'),
      snart: !!l2q(doc, 'tr[style*="ffffcc"]'),
      mangler: !!l2q(doc, 'span[style*="b45309"]'),
      kanRette: !!l2q(doc, 'a[href^="batch_udfyld.php"]')
    };
    return { state: rows.length ? 'liste' : 'tom', flags: flags, data: { varenr: head ? head[1] : '', batches: rows.length } };
  }

  const L2_BO_TASKS = {
    // FEFO (:123, fefo.php:46-48), Restlager and Total restlager (:155, :163)
    BO1: task(L2_LBAT, 'BO1', 2, { target: function (ctx) { const r = l2boRows(ctx.doc); return r.length ? r[0] : null; }, label: showKey(L2_LBAT, 'BO1'), kind: 'row' }),
    // The colours and Dage til udløb (:129-138, :154); Advar om udløb (dage før) in Personlige indstillinger
    BO2: task(L2_LBAT, 'BO2', 3, { target: 'tr[style*="ffcccc"]', label: showKey(L2_LBAT, 'BO2'), kind: 'row' }),
    // Ret batchoplysninger (:93, :97)
    BO3: task(L2_LBAT, 'BO3', 2, { target: 'a[href^="batch_udfyld.php"]', label: showKey(L2_LBAT, 'BO3'), kind: 'button' }),
    // Only with write on Varer (fefo.php:213-222). No Vis mig: nothing on the page.
    BO4: task(L2_LBAT, 'BO4', 2),
    BO5: task(L2_LBAT, 'BO5', 2, { target: 'a[href^="batch_udfyld.php"]', label: showKey(L2_LBAT, 'BO3'), kind: 'button' }),
    // Købsordre and Købsdato (:140-145, :156-158). No Vis mig: a column.
    BO6: task(L2_LBAT, 'BO6', 2),
    // Lager » Udløbsrapport (index/main.php:369-374) and Udløber inden (lager/udlobsrapport.php:117-124). No Vis mig: the sidebar.
    BO7: task(L2_LBAT, 'BO7', 2)
  };

  /** The questions of both batch pages. */
  const L2_BAT_QUESTIONS = {
    Q1: {
      q: 'help.lbat.q.q1.q',
      words: l2words(['rød', 'røde', 'gul', 'gule', 'grøn', 'farve', 'udløbet', 'udløber', 'advarsel', 'dage'],
        ['red', 'yellow', 'green', 'colour', 'color', 'expired', 'expires', 'warning', 'days'],
        ['rød', 'røde', 'gul', 'gule', 'grønn', 'farge', 'utløpt', 'utløper', 'advarsel', 'dager']),
      steps: stepKeys(L2_LBAT, 'BO2', 1, 3)
    },
    Q2: {
      q: 'help.lbat.q.q2.q',
      words: l2words(['mangler', 'udløbsdato', 'batchnummer', 'batchnr', 'tom', 'ingen dato', 'gammelt lager'],
        ['missing', 'expiry date', 'batch number', 'batch no', 'empty', 'no date', 'old stock'],
        ['mangler', 'utløpsdato', 'batchnummer', 'batchnr', 'tom', 'ingen dato', 'gammelt lager']),
      intro: 'help.lbat.q.q2.intro',
      steps: stepKeys(L2_LBAT, 'BO3', 1, 2)
    },
    Q3: {
      q: 'help.lbat.q.q3.q',
      words: l2words(['rækkefølge', 'sælges først', 'fefo', 'fifo', 'først udløb', 'hvilket batch'],
        ['order', 'sold first', 'fefo', 'fifo', 'first expiry', 'which batch'],
        ['rekkefølge', 'selges først', 'fefo', 'fifo', 'først utløp', 'hvilken batch']),
      steps: stepKeys(L2_LBAT, 'BO1', 1, 2)
    }
  };

  const l2BatchOversigt = {
    group: L2_LBAT,
    states: ['liste', 'tom'],
    flags: ['udloebet', 'snart', 'mangler', 'kanRette'],

    name: function (ctx, st) {
      const v = st && st.data && st.data.varenr ? String(st.data.varenr) : '';
      return v ? ['help.lbat.name', v] : 'help.lbat.name_plain';
    },

    detect: l2boDetect,

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'tom') return ['help.lbat.about.tom'];
      if (state !== 'liste') return [];
      if (f.udloebet || f.snart) return ['help.lbat.about.liste', 'help.lbat.about.farver'];
      if (f.mangler) return ['help.lbat.about.liste', 'help.lbat.about.mangler'];
      return ['help.lbat.about.liste'];
    },

    tasks: L2_BO_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state === 'tom') return ['BO7'];
      if (state !== 'liste') return [];
      if (f.udloebet) ids.push('BO2');
      if (f.mangler) ids.push(f.kanRette ? 'BO3' : 'BO4');
      ids.push('BO1');
      if (f.snart && !f.udloebet) ids.push('BO2');
      ids.push('BO6');
      if (f.kanRette && !f.mangler) ids.push('BO5');
      ids.push('BO7');
      return unique(ids);
    },

    problem: function (ctx, st) {
      return st && st.state === 'liste' && st.flags && st.flags.udloebet ? 'BO2' : '';
    },

    fields: {},

    // Tilbage accesskey L (:65, :75, :83)
    shortcuts: [
      { keys: 'Alt+L', text: 'help.lbat.key.01', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: L2_BAT_QUESTIONS,

    // Without batches there are no coloured lines to ask about: the order of sale leads
    questionOrder: function (ctx, st) {
      const f = (st && st.flags) || {};
      if (st && st.state === 'tom') return ['Q3', 'Q2', 'Q1'];
      if (f.mangler) return ['Q2', 'Q1', 'Q3'];
      return ['Q1', 'Q3', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/batch_oversigt.php'] = l2BatchOversigt;

  function l2buDetect(ctx) {
    const doc = ctx.doc;
    const table = l2q(doc, '#bu-table');
    const info = l2q(doc, 'div.bu-info');
    const flags = {
      vare: (parseInt(l2param(ctx, 'vare_id'), 10) || 0) > 0 || (!info && (!!table || !!l2q(doc, 'p'))),
      alle: !!l2q(doc, 'div.bu-info a[href*="alle=0"]'),
      soeg: l2val(l2q(doc, 'input[name="sog"]')) !== '',
      forslag: !!l2q(doc, 'button[onclick*="buSuggest"]'),
      gemt: !!l2q(doc, 'div.bu-ok'),
      fejl: !!l2q(doc, 'div.bu-err'),
      flereSider: !!l2q(doc, 'a[href*="&side="]')
    };
    return { state: table ? 'liste' : 'tom', flags: flags, data: {} };
  }

  const L2_BU_TASKS = {
    // batch_no[id], due_date[id] and Gem (:373-374, :391); all or nothing (:120-209); no quantities or prices (:25-29)
    BU1: task(L2_LBAT, 'BU1', 3, { target: l2only(l2buDetect, ['liste'], 'input[name^="batch_no["]'), label: showKey(L2_LBAT, 'BU1'), kind: 'field' }),
    // The suggest button and its note (:336-339, :399-403)
    BU2: task(L2_LBAT, 'BU2', 2, { target: 'button[onclick*="buSuggest"]', label: showKey(L2_LBAT, 'BU2'), kind: 'button' }),
    // Opdel (:376), the new line (:404-421), the limit (:169-176, :362)
    BU3: task(L2_LBAT, 'BU3', 3, { target: l2only(l2buDetect, ['liste'], 'button[onclick*="buSplit("]'), label: showKey(L2_LBAT, 'BU3'), kind: 'button' }),
    // Vis alle batches på lager / Vis kun batches, der mangler oplysninger (:311-313)
    BU4: task(L2_LBAT, 'BU4', 1, { target: 'div.bu-info a[href*="alle=1"]', label: showKey(L2_LBAT, 'BU4'), kind: 'button' }),
    BU5: task(L2_LBAT, 'BU5', 1, { target: 'div.bu-info a[href*="alle=0"]', label: showKey(L2_LBAT, 'BU5'), kind: 'button' }),
    // The search sog and Søg (:314-319, :288-292)
    BU6: task(L2_LBAT, 'BU6', 1, { target: 'input[name="sog"]', label: showKey(L2_LBAT, 'BU6'), kind: 'field' }),
    // div.bu-err (:269); what was typed is shown again (:207-208, :363-364, :423-435)
    BU7: task(L2_LBAT, 'BU7', 3, { target: 'div.bu-err', label: showKey(L2_LBAT, 'BU7'), kind: 'status' }),
    // Anvend batch- og udløbsdatostyring (settingsDefinitions.php:884-886, hub.php:203), Batchkontrol (6492, :447),
    // Varen har udløbsdato and Standard holdbarhed (dage) (showExpirySettings.php:44-55). No Vis mig: other pages.
    BU8: task(L2_LBAT, 'BU8', 3)
  };

  const l2BatchUdfyld = {
    group: L2_LBAT,
    states: ['liste', 'tom'],
    flags: ['vare', 'alle', 'soeg', 'forslag', 'gemt', 'fejl', 'flereSider'],

    name: function () {
      return 'help.lbat.name_udfyld';
    },

    detect: l2buDetect,

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'tom') {
        if (f.soeg) return ['help.lbat.about.tom_soeg'];
        return f.vare ? ['help.lbat.about.tom_vare'] : ['help.lbat.about.tom_udfyld'];
      }
      if (state !== 'liste') return [];
      let first = 'help.lbat.about.udfyld';
      if (f.vare) first = 'help.lbat.about.udfyld_vare';
      else if (f.alle) first = 'help.lbat.about.udfyld_alle';
      if (f.fejl) return ['help.lbat.about.fejl', first];
      if (f.gemt) return [first, 'help.lbat.about.gemt'];
      return [first, 'help.lbat.about.virkning'];
    },

    tasks: L2_BU_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      const ids = [];
      if (state === 'tom') {
        if (f.soeg) ids.push('BU6');
        if (!f.vare) ids.push('BU4');
        ids.push('BU8');
        return unique(ids);
      }
      if (state !== 'liste') return [];
      if (f.fejl) ids.push('BU7');
      if (f.forslag) ids.push('BU2');
      ids.push('BU1', 'BU3');
      if (!f.vare) ids.push('BU6', f.alle ? 'BU5' : 'BU4');
      return unique(ids);
    },

    problem: function (ctx, st) {
      return st && st.state === 'liste' && st.flags && st.flags.fejl ? 'BU7' : '';
    },

    fields: {
      batch_no: fieldKeys(L2_LBAT, 'batch_no'),
      due_date: fieldKeys(L2_LBAT, 'due_date'),
      split_antal: fieldKeys(L2_LBAT, 'split_antal'),
      sog: fieldKeys(L2_LBAT, 'sog')
    },

    // batch_no[12], due_date[12] (:373-374) and split[12][1][antal|batch_no|due_date] (:410-414)
    fieldKey: function (el) {
      const n = attr(el, 'name');
      let m = /^(batch_no|due_date)\[\d+\]$/.exec(n);
      if (m) return m[1];
      m = /^split\[\d+\]\[\d+\]\[(antal|batch_no|due_date)\]$/.exec(n);
      if (m) return m[1] === 'antal' ? 'split_antal' : m[1];
      return null;
    },

    // Tilbage accesskey L (:229, :238, :246); Enter in the search field is Søg
    shortcuts: [
      { keys: 'Alt+L', text: 'help.lbat.key.01', group: 'keys' },
      { keys: 'Enter', text: 'help.lbat.key.02', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    // Q2's answer on this page: the fields of this list (BU1), not Ret batchoplysninger, which leads here
    questions: {
      Q2: Object.assign({}, L2_BAT_QUESTIONS.Q2, { steps: stepKeys(L2_LBAT, 'BU1', 1, 2), show: L2_BU_TASKS.BU1.show }),
      Q4: {
        q: 'help.lbat.q.q4.q',
        words: l2words(['beholdning', 'kostpris', 'bogføring', 'bogført', 'regnskab', 'lagerværdi', 'ændrer'],
          ['stock', 'cost price', 'posting', 'posted', 'accounts', 'stock value', 'change'],
          ['beholdning', 'kostpris', 'bokføring', 'bokført', 'regnskap', 'lagerverdi', 'endrer']),
        intro: 'help.lbat.q.q4.intro',
        steps: ['help.lbat.q.q4.step1']
      },
      Q5: {
        q: 'help.lbat.q.q5.q',
        words: l2words(['opdel', 'dele', 'reserveret', 'res', 'højst', 'kan ikke dele', 'parti'],
          ['split', 'reserved', 'res', 'at most', 'cannot split', 'lot'],
          ['del opp', 'dele', 'reservert', 'res', 'maksimalt', 'kan ikke dele', 'parti']),
        intro: 'help.lbat.q.q5.intro',
        steps: ['help.lbat.q.q5.step1']
      }
    },

    // The list shows what is missing, so why it is missing leads (Q2). Of the refusals (:120-209), a split above the free
    // rest has a question of its own (max_split, :169-176), so Q5 leads with the error. An empty list has nothing missing.
    questionOrder: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      const f = (st && st.flags) || {};
      if (state === 'tom') return ['Q4', 'Q2', 'Q5'];
      return f.fejl ? ['Q5', 'Q2', 'Q4'] : ['Q2', 'Q4', 'Q5'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/batch_udfyld.php'] = l2BatchUdfyld;

  // ---- Stykliste (lager/stykliste.php) and Vareproduktion (lager/vareproduktion.php)
  // Evidence: the item card of an item with a bill of materials (samlevare) links Stykliste (hard-coded) to
  // stykliste.php?id=<id> (lager/varekort.php:2008-2011); its quantities are read-only while the item has stock (:2010) and
  // saving the card sets the item's cost price to the bill's total (:1157-1189). lager/stykliste.php prints stykliste(id,
  // 'udskriv') (:37-39, includes/stykliste.php:31-73): Stykliste for <a href=varekort.php?id=<id>>varenr</a>, the columns
  // Varenr:, Beskrivelse, Kostpris, Antal, Sum, Beholdning and I alt (hard-coded, :50-51, :65, :69); each part links its
  // card ../lager/varekort.php?id=<id> (:65). lager/fuld_stykliste.php opens only with window.open (includes/online.php:477-479,
  // finans/ordre.php:1707), so it has no page here.
  // lager/vareproduktion.php: saving the card of a stock item with a bill of materials with a changed stock figure under
  // Aktuel (lager/productCardIncludes/showLocations.php:191-200) redirects here (lager/varekort.php:1241-1249). The page
  // asks Ret lagerbeholdning til <n>? with OK name=OK and Afbryd name=cancel (hard-coded, :189-205). OK changes the item's
  // stock on that warehouse and takes the parts times the change from the same warehouse (:80-158; a lower figure puts parts
  // back), for items in a Lagerført group (box8, :111, :139); the new batch has the item's cost price and no batch number or
  // expiry date (:115-118); without a voucher number nothing is posted (:162-183; the field is not on the page, :195-199);
  // then Vareproduktion gennemført and back to the card (:186-187). Afbryd goes back to the card unchanged (:60-64).

  const L2_LPROD = 'lprod';

  function l2slDetect(ctx) {
    const doc = ctx.doc;
    const head = l2q(doc, 'a[href^="varekort.php?id="]');
    const parts = l2qa(doc, 'a[href^="../lager/varekort.php?id="]');
    let state = 'unknown';
    if (head) state = parts.length ? 'liste' : 'tom';
    return { state: state, flags: {}, data: { varenr: l2txt(head) } };
  }

  const L2_SL_TASKS = {
    // Sum, Kostpris, Antal, I alt (includes/stykliste.php:57-69); the card's cost price (lager/varekort.php:1188-1189). No Vis mig: a sum.
    SL1: task(L2_LPROD, 'SL1', 2),
    // A part's link (:65)
    SL2: task(L2_LPROD, 'SL2', 1, { target: l2only(l2slDetect, ['liste'], 'a[href^="../lager/varekort.php?id="]'), label: showKey(L2_LPROD, 'SL2'), kind: 'row' }),
    // The heading's link to the card (:50); the card's bill, read-only with stock (lager/varekort.php:2008-2031)
    SL3: task(L2_LPROD, 'SL3', 3, { target: 'a[href^="varekort.php?id="]', label: showKey(L2_LPROD, 'SL3'), kind: 'button' }),
    // Beholdning per part (:65). No Vis mig: a column.
    SL4: task(L2_LPROD, 'SL4', 2),
    // The card's stock under Aktuel and Gem (lager/varekort.php:1241-1249, :2086), then vareproduktion.php OK (:203)
    SL5: task(L2_LPROD, 'SL5', 3, { target: 'a[href^="varekort.php?id="]', label: showKey(L2_LPROD, 'SL3'), kind: 'button' })
  };

  /** The questions of both bill of materials pages. */
  const L2_PROD_QUESTIONS = {
    Q1: {
      q: 'help.lprod.q.q1.q',
      words: l2words(['producere', 'produktion', 'samle', 'samlevare', 'stykliste', 'færdig vare', 'dele'],
        ['produce', 'production', 'assemble', 'assembly', 'bill of materials', 'finished item', 'parts'],
        ['produsere', 'produksjon', 'sette sammen', 'stykkliste', 'ferdig vare', 'deler']),
      steps: stepKeys(L2_LPROD, 'SL5', 1, 3)
    },
    Q2: {
      q: 'help.lprod.q.q2.q',
      words: l2words(['bogført', 'bogføres', 'regnskab', 'postering', 'lagerværdi', 'kostpris'],
        ['posted', 'accounts', 'entry', 'stock value', 'cost price'],
        ['bokført', 'bokføres', 'regnskap', 'postering', 'lagerverdi', 'kostpris']),
      intro: 'help.lprod.q.q2.intro',
      steps: ['help.lprod.q.q2.step1', 'help.lprod.q.q2.step2']
    },
    // Q3: the Aktuel field of one warehouse redirects with lager=<n>, and the parts are taken from the same one
    // (varekort.php:1241-1249, vareproduktion.php:80-158)
    Q3: {
      q: 'help.lprod.q.q3.q',
      words: l2words(['lager', 'hvilket lager', 'lagre', 'dele', 'trækkes'],
        ['warehouse', 'which warehouse', 'warehouses', 'parts', 'taken'],
        ['lager', 'hvilket lager', 'lagre', 'deler', 'trekkes']),
      intro: 'help.lprod.q.q3.intro',
      steps: ['help.lprod.q.q3.step1']
    }
  };

  const l2Stykliste = {
    group: L2_LPROD,
    states: ['liste', 'tom'],
    flags: [],

    name: function (ctx, st) {
      const v = st && st.data && st.data.varenr ? String(st.data.varenr) : '';
      return v ? ['help.lprod.name', v] : 'help.lprod.name_plain';
    },

    detect: l2slDetect,

    about: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      if (state === 'liste') return ['help.lprod.about.liste', 'help.lprod.about.kostpris'];
      if (state === 'tom') return ['help.lprod.about.tom'];
      return [];
    },

    tasks: L2_SL_TASKS,

    order: function (ctx, st) {
      const state = st ? st.state : 'unknown';
      if (state === 'liste') return ['SL1', 'SL5', 'SL4', 'SL2', 'SL3'];
      if (state === 'tom') return ['SL3'];
      return [];
    },

    fields: {},

    // No access keys or key handlers on the page
    shortcuts: [],

    questions: L2_PROD_QUESTIONS,

    questionOrder: function () {
      return ['Q1', 'Q2', 'Q3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/stykliste.php'] = l2Stykliste;

  function l2vpDetect(ctx) {
    const doc = ctx.doc;
    const ok = l2q(doc, 'input[name="OK"]');
    const cancel = l2q(doc, 'input[name="cancel"]');
    return { state: ok && cancel ? 'bekraeft' : 'unknown', flags: {}, data: {} };
  }

  const L2_VP_TASKS = {
    // OK (:203, :66-187)
    VP1: task(L2_LPROD, 'VP1', 3, { target: l2only(l2vpDetect, ['bekraeft'], 'input[name="OK"]'), label: showKey(L2_LPROD, 'VP1'), kind: 'button' }),
    // Afbryd (:204, :60-64)
    VP2: task(L2_LPROD, 'VP2', 1, { target: l2only(l2vpDetect, ['bekraeft'], 'input[name="cancel"]'), label: showKey(L2_LPROD, 'VP2'), kind: 'button' }),
    // The produced batch has no batch number or expiry date (:115-118); Batch oversigt, Ret batchoplysninger. No Vis mig.
    VP3: task(L2_LPROD, 'VP3', 3)
  };

  const l2Vareproduktion = {
    group: L2_LPROD,
    states: ['bekraeft'],
    flags: [],

    name: function () {
      return 'help.lprod.name_produktion';
    },

    detect: l2vpDetect,

    about: function (ctx, st) {
      return st && st.state === 'bekraeft' ? ['help.lprod.about.bekraeft', 'help.lprod.about.ingen_postering'] : [];
    },

    tasks: L2_VP_TASKS,

    order: function (ctx, st) {
      return st && st.state === 'bekraeft' ? ['VP1', 'VP2', 'VP3'] : [];
    },

    fields: {},

    // No access keys or key handlers on the page
    shortcuts: [],

    questions: {
      Q2: L2_PROD_QUESTIONS.Q2,
      Q3: L2_PROD_QUESTIONS.Q3,
      // Q4: the produced quantity gets a batch_kob row with the item's cost price and no batch_no or due_date (:115-118)
      Q4: {
        q: 'help.lprod.q.q4.q',
        words: l2words(['batch', 'batchnummer', 'udløbsdato', 'udløb', 'holdbarhed', 'mangler'],
          ['batch', 'batch number', 'expiry date', 'expiry', 'shelf life', 'missing'],
          ['batch', 'batchnummer', 'utløpsdato', 'utløp', 'holdbarhet', 'mangler']),
        intro: 'help.lprod.q.q4.intro',
        steps: stepKeys(L2_LPROD, 'VP3', 2, 3)
      }
    },

    questionOrder: function () {
      return ['Q2', 'Q3', 'Q4'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/vareproduktion.php'] = l2Vareproduktion;

  // ==== Stock reports and lists (author L3; helpers l3)
  // ================================================================== L3: Lager reports
  // Varerapporter and the item sales report, Lagerstatus, Varespor, Salg pr. postnummer, Beholdningsliste, Lagerhistorik,
  // Beholdningsrapport, Udløbsrapport and Prisliste. Every page loads as the frame's own document; the classic layout is
  // the sidebar one ($menu 'S', the default of new users, includes/online.php:332-337), so the evidence below cites that
  // branch. Reached from the Lager menu (index/main.php:361-374): Rapporter opens lager/rapport.php, whose buttons open the
  // other reports by a meta refresh in the same frame (lager/rapport.php:200-206); Udløbsrapport has its own menu entry
  // when batch and expiry dates are in use (index/main.php:371-374, includes/stdFunc/fefo.php:161-185).
  // Not here, and why (details in the L3 notes): lager/csv_set_import.php and lager/vvsimport.php are linked from nowhere
  // (only their own forms post to them), so the group limp stays empty; the CSV and print views (csv=1, autoprint=1) open
  // in a new window or the shell's off-screen print frame, never as the frame's page.

  // ------------------------------------------------------------------ L3 helpers (read only)

  function l3Q(root, sel) {
    try {
      const el = root ? root.querySelector(sel) : null;
      return el && el.nodeType === 1 ? el : null;
    } catch (e) {
      return null;
    }
  }

  function l3All(root, sel) {
    try {
      return root ? Array.prototype.slice.call(root.querySelectorAll(sel)).filter(function (el) { return el && el.nodeType === 1; }) : [];
    } catch (e) {
      return [];
    }
  }

  function l3Text(el) {
    try {
      return el && el.nodeType === 1 ? String(el.textContent || '') : '';
    } catch (e) {
      return '';
    }
  }

  function l3Val(el) {
    try {
      return el && el.nodeType === 1 ? String(el.value || '') : '';
    } catch (e) {
      return '';
    }
  }

  /** A checkbox's state: the property in a browser, the attribute as printed otherwise. */
  function l3Checked(el) {
    if (!el || el.nodeType !== 1) return false;
    try {
      if (typeof el.checked === 'boolean') return el.checked;
    } catch (e) {
      return false;
    }
    return el.getAttribute('checked') !== null;
  }

  function l3Flags(names) {
    const f = {};
    names.forEach(function (n) { f[n] = false; });
    return f;
  }

  function l3State(st) {
    return st ? st.state : 'unknown';
  }

  function l3Fl(st) {
    return (st && st.flags) || {};
  }

  function l3Ids(list) {
    return unique(list.filter(function (id) { return !!id; }));
  }

  function l3Words(da, en, no) {
    return { da: da, en: en, no: no };
  }

  function l3Param(ctx, name) {
    try {
      return ctx && ctx.url && ctx.url.searchParams ? String(ctx.url.searchParams.get(name) || '') : '';
    } catch (e) {
      return '';
    }
  }

  /** A link that wraps a button (<a accesskey=L href=...><button>): the button, else the link. */
  function l3Inner(a) {
    if (!a) return null;
    return l3Q(a, 'button') || l3Q(a, 'input') || a;
  }

  /** A show target that only counts in the given states (one page object serves several screens, and the chat's search
   *  offers every task in every state, spec §19.3 and §21). */
  function l3Only(states, target) {
    return function (ctx, st) {
      if (!st || states.indexOf(st.state) === -1) return null;
      if (typeof target === 'function') return target(ctx, st);
      return l3Q(ctx.doc, target);
    };
  }

  /** The footer's "0 af 0" of the Grid Framework reports (rapport.php:1845, lagerstatus.php:894, pricelist.php:455). */
  function l3Zero(doc, sel) {
    const el = l3Q(doc, sel);
    return !!el && /^\s*0\s/.test(l3Text(el));
  }

  /** Any search box of a grid's search row with a value. */
  function l3Filled(doc, sel) {
    return l3All(doc, sel).some(function (el) { return l3Val(el).trim() !== ''; });
  }

  /** The Tilbage link (accesskey L) of a report: its button, else the link. */
  function l3Back(ctx) {
    return l3Inner(l3Q(ctx.doc, 'a[accesskey="L"]'));
  }

  // ---- Varerapporter and Varesalg (lager/rapport.php)
  // Sidebar Lager » Rapporter (index/main.php:367-369). One route for the selection form and the item sales report.
  // Evidence, lager/rapport.php unless named:
  // - the form forside() :236-478 under the title Varerapporter (includes/S_topLine.php, no Tilbage in the sidebar
  //   layout): form[name=rapport] (:377) with Varegrupper select[name=varegruppe] (:381), Leverandør select[name=lev] only
  //   when items have suppliers (:391-401), Afdeling select[name=afd] only with departments (:402-412), Sælger
  //   select[name=ref] (:413-425), Periode dato_fra and dato_til (:429-430, default 01-01 of this year to today, :244-245),
  //   Varenr. and Varenavn (:434, :437, substring matches, :641-663), the checkboxes Detaljeret, Kun salg / DB and Beregn
  //   lagerbeh./værdi (:440-446), OK input.green[name=submit] (:448). Under it the buttons Lagerstatus (input.blue, :453),
  //   Beholdningsrapport only in one tenant (:456-459), Lageroptælling input[name=inventoryCount] (input.gray, :461-465),
  //   Prisliste (input.blue, :468) and Salg pr. postnummer (input.blue, :473); the blue ones are name=submit and open
  //   their page by value (:200-206). The form is printed in a table, so its later inputs need not be descendants of it.
  // - the report varegruppe() :482-1905 (POST with OK, or GET with submit=ok from the paging, :200): #vrPageFlex with
  //   Tilbage a[accesskey=L][href^="rapport.php?"] that carries the form's choices (detaljer=, kun_salg=, lagertal=; :522,
  //   :597-601) and CSV a[href*="salgsrapport.csv"] (:606); the summary grid #vrGridTable with the search row
  //   tr.vr-search-row (Varenr. name=varenr, Beskrivelse name=varenavn, the computed columns vrs_<column>, :886-925,
  //   :961-972; vrs_behold and vrs_vaerdi only when Beholdning and Værdi are shown, i.e. Beregn lagerbeh./værdi with a
  //   stock-kept item group, :951-956), Summeret and the totals row last in the table (:1700-1810); the Detaljeret view
  //   prints per item the purchases (Købsdato ... Ordrer, Se opens the purchase order in a new window, :1243-1272), the
  //   sales (Salgsdato ... Ordre, td.hover-preview opens the order, :1404-1449) and Lagerreguleret with Samlet til-/afgang
  //   i perioden (:1450-1482), no search row; the footer #vrPageStatus, #vrPageSize, #vrNavButtons (:1844-1880).
  // - the figures: Bestilt counts open purchase order lines (ordrer.art KO, status 1-2, :738-760), Købt/Købspris the
  //   purchases and Reguleret the stock adjustments in the period (:1117-1128), Solgt/Salgspris the sales by invoice date
  //   without VAT and DB = Salgspris - the order lines' kostpris (:1130-1152, :1279-1297), DG = DB / Salgspris (:1400-1401);
  //   Beholdning and Værdi = stock on the period's last day times kostpris (:1086-1107, :1543-1552). batch_salg.pris is in
  //   kroner after discount (includes/ordrefunc.php:1895-1919). The CSV file holds every item of the report, not only those
  //   a search box shows (:801-805, written while the report is computed).

  const L3R = 'lrap';
  const L3R_STATES = ['forside', 'varesalg', 'detaljeret', 'unknown'];
  const L3R_FLAGS = ['afdelinger', 'leverandoerer', 'kunSalg', 'lagertal', 'ingenLagergruppe', 'tom', 'flereSider', 'soegning'];

  /** The choices the report was made with, from its Tilbage link (rapport.php:522), else the search form's hidden inputs. */
  function l3rOpts(doc) {
    const href = attr(l3Q(doc, 'a[accesskey="L"][href^="rapport.php?"]'), 'href');
    const get = function (n) {
      const m = new RegExp('[?&]' + n + '=([^&]*)').exec(href);
      if (m) return m[1] !== '';
      return l3Val(l3Q(doc, 'input[type="hidden"][name="' + n + '"]')) !== '';
    };
    return { detaljer: get('detaljer'), kunSalg: get('kun_salg'), lagertal: get('lagertal') };
  }

  /** The blue buttons under the form in print order: Lagerstatus, Prisliste, Salg pr. postnummer (rapport.php:453-473). */
  function l3rBlue(i) {
    return l3Only(['forside'], function (ctx) {
      return l3All(ctx.doc, 'input.blue[name="submit"]')[i] || null;
    });
  }

  /** The report's totals row: the last row of #vrGridTable (rapport.php:1743-1806). */
  function l3rTotals(ctx) {
    const rows = l3All(ctx.doc, '#vrGridTable tbody tr');
    return rows.length ? rows[rows.length - 1] : null;
  }

  const L3R_TASKS = {
    // The form
    RA1: task(L3R, 'RA1', 3, { target: l3Only(['forside'], 'input.green[name="submit"]'), label: showKey(L3R, 'RA1'), kind: 'button' }),
    RA2: task(L3R, 'RA2', 3, { target: l3Only(['forside'], 'input.green[name="submit"]'), label: showKey(L3R, 'RA1'), kind: 'button' }),
    RA3: task(L3R, 'RA3', 3, { target: l3rBlue(0), label: showKey(L3R, 'RA3'), kind: 'button' }),
    RA4: task(L3R, 'RA4', 2, { target: l3Only(['forside'], 'input[name="inventoryCount"]'), label: showKey(L3R, 'RA4'), kind: 'button' }),
    RA5: task(L3R, 'RA5', 2, { target: l3rBlue(1), label: showKey(L3R, 'RA5'), kind: 'button' }),
    RA6: task(L3R, 'RA6', 2, { target: l3rBlue(2), label: showKey(L3R, 'RA6'), kind: 'button' }),
    RA7: task(L3R, 'RA7', 2, { target: 'select[name="varegruppe"]', label: fieldLabel(L3R, 'varegruppe'), kind: 'field' }, stepKeys(L3R, 'RA1', 3, 3)),
    RA8: task(L3R, 'RA8', 3, { target: 'input[type="checkbox"][name="detaljer"]', label: fieldLabel(L3R, 'detaljer'), kind: 'field' }),
    RA9: task(L3R, 'RA9', 3, { target: 'input[type="checkbox"][name="lagertal"]', label: fieldLabel(L3R, 'lagertal'), kind: 'field' }),
    // The report
    RV1: task(L3R, 'RV1', 3, { target: 'input[name="vrs_dg"]', label: showKey(L3R, 'RV1'), kind: 'field' }),
    RV2: task(L3R, 'RV2', 2, {
      target: l3Only(['varesalg', 'detaljeret'], function (ctx) { return l3Inner(l3Q(ctx.doc, 'a[href*="salgsrapport.csv"]')); }),
      label: showKey(L3R, 'RV2'),
      kind: 'button'
    }),
    RV3: task(L3R, 'RV3', 2, { target: 'input[name="vrs_bestilt"]', label: showKey(L3R, 'RV3'), kind: 'field' }),
    RV4: task(L3R, 'RV4', 3, { target: l3Only(['varesalg'], l3rTotals), label: showKey(L3R, 'RV4'), kind: 'row' }),
    RV5: task(L3R, 'RV5', 3, { target: 'tr.vr-search-row input[name="varenr"]', label: fieldLabel(L3R, 'varenr'), kind: 'field' }),
    RV6: task(L3R, 'RV6', 2, { target: l3Only(['varesalg', 'detaljeret'], l3Back), label: showKey(L3R, 'RV6'), kind: 'button' }, stepKeys(L3R, 'RA1', 3, 3)),
    // Tilbage, one item in Varenr., then Detaljeret and OK on the form (RV6, RA8)
    RV7: {
      title: 'help.lrap.task.rv7.title',
      why: 'help.lrap.task.rv7.why',
      steps: stepKeys(L3R, 'RV6', 1, 1).concat(stepKeys(L3R, 'RV7', 1, 1), stepKeys(L3R, 'RA8', 2, 2)),
      show: { target: l3Only(['varesalg'], l3Back), label: showKey(L3R, 'RV6'), kind: 'button' }
    },
    RV8: task(L3R, 'RV8', 1, { target: '#vrPageSize', label: fieldLabel(L3R, 'linjer'), kind: 'field' }),
    RV9: task(L3R, 'RV9', 3),
    RV10: task(L3R, 'RV10', 3, { target: l3Only(['varesalg'], function (ctx, st) { return l3Fl(st).lagertal ? l3rTotals(ctx) : null; }), label: showKey(L3R, 'RV10'), kind: 'row' }),
    // The Detaljeret view
    RD1: task(L3R, 'RD1', 2, { target: 'td.hover-preview[data-preview-url*="debitor/ordre.php"]', label: showKey(L3R, 'RD1'), kind: 'row' }),
    RD2: task(L3R, 'RD2', 2, { target: 'td[onclick*="kreditor/ordre.php"]', label: showKey(L3R, 'RD1'), kind: 'row' }),
    RD3: task(L3R, 'RD3', 2)
  };

  const l3RapportPage = {
    group: L3R,
    states: L3R_STATES,
    flags: L3R_FLAGS,

    name: function (ctx, st) {
      const s = l3State(st);
      if (s === 'varesalg') return 'help.lrap.name_varesalg';
      if (s === 'detaljeret') return 'help.lrap.name_detaljeret';
      return 'help.lrap.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const f = l3Flags(L3R_FLAGS);
      let state = 'unknown';
      if (l3Q(doc, 'input[name="dato_fra"]') && l3Q(doc, 'input[name="inventoryCount"]')) {
        state = 'forside';
        f.afdelinger = !!l3Q(doc, 'select[name="afd"]');
        f.leverandoerer = !!l3Q(doc, 'select[name="lev"]');
      } else if (l3Q(doc, '#vrGridTable') || l3Q(doc, '#vrPageFlex')) {
        const o = l3rOpts(doc);
        state = l3Q(doc, 'tr.vr-search-row') || !o.detaljer ? 'varesalg' : 'detaljeret';
        f.kunSalg = o.kunSalg;
        f.lagertal = state === 'varesalg' && o.lagertal && !o.kunSalg;
        // Beholdning and Værdi need an item group with Lagerført (rapport.php:951-956, :586-603)
        f.ingenLagergruppe = f.lagertal && !!l3Q(doc, 'tr.vr-search-row') && !l3Q(doc, 'input[name="vrs_behold"]');
        f.tom = l3Zero(doc, '#vrPageStatus');
        f.flereSider = !!l3Q(doc, '#vrNavButtons a.navbutton');
        f.soegning = l3Filled(doc, 'tr.vr-search-row input');
      }
      return { state: state, flags: f, data: {} };
    },

    about: function (ctx, st) {
      const s = l3State(st);
      const f = l3Fl(st);
      if (s === 'forside') return ['help.lrap.about.forside'];
      if (s === 'varesalg') {
        const out = [f.kunSalg ? 'help.lrap.about.kun_salg' : 'help.lrap.about.varesalg'];
        if (f.ingenLagergruppe) out.push('help.lrap.about.ingen_lagergruppe');
        else if (f.tom) out.push(f.soegning ? 'help.lrap.about.tom_soegning' : 'help.lrap.about.tom');
        else if (f.lagertal) out.push('help.lrap.about.lagertal');
        else if (f.soegning) out.push('help.lrap.about.soegning');
        else out.push('help.lrap.about.summeret');
        return out;
      }
      if (s === 'detaljeret') return ['help.lrap.about.detaljeret', f.tom ? 'help.lrap.about.tom' : 'help.lrap.about.detaljeret_se'];
      return [];
    },

    tasks: L3R_TASKS,

    order: function (ctx, st) {
      const s = l3State(st);
      const f = l3Fl(st);
      if (s === 'forside') return ['RA1', 'RA3', 'RA2', 'RA7', 'RA8', 'RA9', 'RA4', 'RA5', 'RA6'];
      if (s === 'varesalg') {
        if (f.ingenLagergruppe) return ['RV9', 'RV6', 'RV2', 'RV5'];
        if (f.tom) return f.soegning ? ['RV5', 'RV6'] : ['RV6'];
        if (f.lagertal) return l3Ids(['RV10', 'RV1', 'RV2', 'RV5', 'RV4', 'RV3', 'RV6', 'RV7', f.flereSider ? 'RV8' : '']);
        if (f.kunSalg) return l3Ids(['RV1', 'RV5', 'RV2', 'RV6', 'RV7', f.flereSider ? 'RV8' : '']);
        return l3Ids(['RV1', 'RV5', 'RV2', 'RV4', 'RV3', 'RV6', 'RV7', f.flereSider ? 'RV8' : '']);
      }
      if (s === 'detaljeret') return f.tom ? ['RV6'] : l3Ids(['RD1', 'RD2', 'RD3', 'RV2', 'RV6', f.flereSider ? 'RV8' : '']);
      return [];
    },

    // The report cannot add Beholdning and Værdi when no item group is stock-kept: the user asked for them.
    problem: function (ctx, st) {
      return l3State(st) === 'varesalg' && l3Fl(st).ingenLagergruppe ? 'RV9' : '';
    },

    fields: {
      varegruppe: fieldKeys(L3R, 'varegruppe'),
      lev: fieldKeys(L3R, 'lev'),
      afd: fieldKeys(L3R, 'afd'),
      ref: fieldKeys(L3R, 'ref'),
      periode: fieldKeys(L3R, 'periode'),
      varenr: fieldKeys(L3R, 'varenr'),
      varenavn: fieldKeys(L3R, 'varenavn'),
      detaljer: fieldKeys(L3R, 'detaljer'),
      kun_salg: fieldKeys(L3R, 'kun_salg'),
      lagertal: fieldKeys(L3R, 'lagertal'),
      vrs: fieldKeys(L3R, 'vrs'),
      vrs_tekst: fieldKeys(L3R, 'vrs_tekst'),
      linjer: fieldKeys(L3R, 'linjer')
    },

    // dato_fra and dato_til are the two boxes of Periode (rapport.php:429-430); the report's search row names its computed
    // columns vrs_<column> (:210), the text ones are a substring search (:220-226); the page size select has only an id.
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (n === 'dato_fra' || n === 'dato_til') return 'periode';
      if (/^vrs_(enhed|varenr_alias|beskrivelse_alias)$/.test(n)) return 'vrs_tekst';
      if (/^vrs_/.test(n)) return 'vrs';
      if (attr(el, 'id') === 'vrPageSize') return 'linjer';
      return null;
    },

    // Tilbage on the report (accesskey L, rapport.php:522); the form has none in the sidebar layout (S_topLine.php).
    shortcuts: [
      { keys: 'Alt+L', text: 'help.lrap.key.01', group: 'keys' }
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.lrap.q.q1.q',
        words: l3Words(['db', 'dg', 'dækningsbidrag', 'dækningsgrad', 'avance', 'fortjeneste', 'tjent', 'kostpris', 'beregnet'],
          ['db', 'dg', 'contribution margin', 'margin', 'profit', 'earned', 'cost price', 'calculated'],
          ['db', 'dg', 'dekningsbidrag', 'dekningsgrad', 'avanse', 'fortjeneste', 'tjent', 'kostpris', 'beregnet']),
        steps: ['help.lrap.q.q1.step1', 'help.lrap.q.q1.step2', 'help.lrap.q.q1.step3']
      },
      Q2: {
        q: 'help.lrap.q.q2.q',
        words: l3Words(['mangler', 'ikke med', 'vare mangler', 'kan ikke finde', 'tom', 'ingen varer', 'rapport'],
          ['missing', 'not included', 'item missing', 'cannot find', 'empty', 'no items', 'report'],
          ['mangler', 'ikke med', 'vare mangler', 'finner ikke', 'tom', 'ingen varer', 'rapport']),
        steps: ['help.lrap.q.q2.step1', 'help.lrap.q.q2.step2', 'help.lrap.q.q2.step3']
      },
      Q3: {
        q: 'help.lrap.q.q3.q',
        words: l3Words(['lagerværdi', 'varelager', 'årsregnskab', 'årsafslutning', 'statusdag', 'status', 'værdi', 'revisor'],
          ['stock value', 'inventory value', 'annual accounts', 'year end', 'balance date', 'value', 'auditor'],
          ['lagerverdi', 'varelager', 'årsregnskap', 'årsavslutning', 'statusdag', 'status', 'verdi', 'revisor']),
        // step 1 from the sidebar: Q3 also leads on the item sales report, which has no Lagerstatus button (rapport.php:453-455)
        steps: ['help.lrap.q.q3.step1'].concat(stepKeys(L3R, 'RA3', 2, 2), ['help.lrap.q.q3.step3']),
        show: L3R_TASKS.RA3.show
      },
      Q4: {
        q: 'help.lrap.q.q4.q',
        words: l3Words(['bestilt', 'reguleret', 'lagerreguleret', 'købspris', 'kolonner', 'betyder'],
          ['ordered', 'regulated', 'adjusted', 'purchase price', 'columns', 'meaning'],
          ['bestilt', 'regulert', 'lagerregulert', 'kjøpesum', 'kolonner', 'betyr']),
        steps: ['help.lrap.q.q4.step1', 'help.lrap.q.q4.step2', 'help.lrap.q.q4.step3']
      }
    },

    questionOrder: function (ctx, st) {
      const s = l3State(st);
      const f = l3Fl(st);
      if (s === 'varesalg') {
        if (f.lagertal || f.ingenLagergruppe) return ['Q3', 'Q2', 'Q1'];
        return f.tom ? ['Q2', 'Q1', 'Q4'] : ['Q1', 'Q2', 'Q4'];
      }
      if (s === 'detaljeret') return ['Q4', 'Q1', 'Q2'];
      return ['Q1', 'Q3', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/rapport.php'] = l3RapportPage;

  // ---- Lagerstatus (lager/lagerstatus.php)
  // Reached from Lagerstatus on Varerapporter (lager/rapport.php:202, :453), same frame. Evidence, lager/lagerstatus.php:
  // #lsPageFlex with Tilbage a[accesskey=L] to rapport.php (:387) and CSV a[href*="csv=1"] (:370, :390); the filter form
  // (:410-446): Lager select[name=lagervalg] (:413-421, always "0 : Alle" and at least one more), Varegruppe
  // select[name=varegruppe] (:423-430), Dato input[type=text][name=dato] (:432, today by default), the second Dato
  // select[name=dateType] Leveringsdato/Fakturadato (Danish in the markup, :433-440; it picks the date a movement counts by
  // when Dato is not today, :600-608), 0 lager input[name=zStock] (:442, forced on for an earlier date, :197) and Udgåede
  // input[name=showClosed] (:443), OK (:445). The grid #lsGridTable (:551) with the search row tr.ls-search-row: varenr,
  // enhed, varenavn and lss_<column> for the computed columns (:536-553; a text search also finds items with 0 stock and
  // closed items, :291-306); columns Varenr., Enhed, Beskrivelse, Tilgang, Afgang, Antal, Købspris, Kostpris, Salgspris
  // (:542-550); the item number links to varespor.php only for users who may open it (:726-728); today, without a
  // warehouse, a stock figure that differs from the item card is a red link a[href*="opdater="] that asks and updates the
  // item card's figure (:740-768), and Ret skæve lagertal a[href*="ret_behold=2"] fixes them all (:855, write on Varer,
  // :117-122). Samlet lagerværdi pr. sums every matching item, not only the page (:811-834, :851-854); Købspris values the
  // remaining stock at the latest purchases (lagerstatusValue.php, :687-695). Footer #lsPageStatus, #lsPageSize,
  // #lsNavButtons (:893-930). The shell's Print side prints the page's own print view (window.saldiPrintAutoprint, :529;
  // index/main.php:1155-1200): the rows of the page and page size on screen.

  const L3S = 'lstat';

  const L3LS_TASKS = {
    LS1: task(L3S, 'LS1', 3, { target: 'input[type="text"][name="dato"]', label: fieldLabel(L3S, 'ls_dato'), kind: 'field' }),
    LS2: task(L3S, 'LS2', 3, {
      target: function (ctx) { return l3Q(ctx.doc, 'a[href*="opdater="]') || l3Q(ctx.doc, 'a[href*="ret_behold=2"]'); },
      label: showKey(L3S, 'LS2'),
      kind: 'status'
    }),
    LS3: task(L3S, 'LS3', 2, { target: function (ctx) { return l3Inner(l3Q(ctx.doc, 'a[href^="lagerstatus.php?"][href*="csv=1"]')); }, label: showKey(L3S, 'LS3'), kind: 'button' }),
    LS4: task(L3S, 'LS4', 2, { target: '#lsPageSize', label: fieldLabel(L3S, 'linjer'), kind: 'field' }),
    LS5: task(L3S, 'LS5', 2, { target: 'select[name="lagervalg"]', label: fieldLabel(L3S, 'ls_lager'), kind: 'field' }),
    LS6: task(L3S, 'LS6', 2, { target: 'input[type="checkbox"][name="zStock"]', label: fieldLabel(L3S, 'ls_nul'), kind: 'field' }),
    LS7: task(L3S, 'LS7', 2, { target: 'a[href^="varespor.php?vare_id="]', label: showKey(L3S, 'LS7'), kind: 'row' }),
    LS8: task(L3S, 'LS8', 2, { target: 'tr.ls-search-row input[name="varenr"]', label: fieldLabel(L3S, 'ls_soeg'), kind: 'field' }),
    LS9: task(L3S, 'LS9', 3, { target: 'select[name="dateType"]', label: fieldLabel(L3S, 'ls_datotype'), kind: 'field' }),
    // LS10: nothing posts the stock value (aut_lager, includes/ordrefunc.php:1985-1986; optalling.php:630), so the change
    // is posted by hand from the value on the balance date. Købspris is the FIFO value of the purchase batches up to the
    // date (lagerstatusValue.php:15-35); Kostpris is today's card price times the quantity for any date (:344, :773).
    // Vis mig: the date of step 1, as LS1.
    LS10: task(L3S, 'LS10', 3, { target: 'input[type="text"][name="dato"]', label: fieldLabel(L3S, 'ls_dato'), kind: 'field' })
  };

  const l3LagerstatusPage = {
    group: L3S,
    states: ['liste', 'unknown'],
    flags: ['skaev', 'tom', 'flereSider', 'soegning', 'lagervalgt', 'flereLagre', 'fakturadato'],

    name: function () {
      return 'help.lstat.name_lagerstatus';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const f = { skaev: false, tom: false, flereSider: false, soegning: false, lagervalgt: false, flereLagre: false, fakturadato: false };
      if (!(l3Q(doc, '#lsGridTable') || l3Q(doc, 'select[name="dateType"]'))) return { state: 'unknown', flags: f, data: {} };
      f.skaev = !!(l3Q(doc, 'a[href*="opdater="]') || l3Q(doc, 'a[href*="ret_behold=2"]'));
      f.tom = l3Zero(doc, '#lsPageStatus');
      f.flereSider = !!l3Q(doc, '#lsNavButtons a.navbutton');
      f.soegning = l3Filled(doc, 'tr.ls-search-row input');
      const lager = l3Q(doc, 'select[name="lagervalg"]');
      f.lagervalgt = ['', '0'].indexOf(l3Val(lager)) === -1;
      f.flereLagre = l3All(lager, 'option').length > 2;
      f.fakturadato = l3Val(l3Q(doc, 'select[name="dateType"]')) === 'fakturadate';
      return { state: 'liste', flags: f, data: {} };
    },

    about: function (ctx, st) {
      if (l3State(st) !== 'liste') return [];
      const f = l3Fl(st);
      let second = 'help.lstat.about.ls_vaerdi';
      if (f.skaev) second = 'help.lstat.about.ls_skaev';
      else if (f.tom) second = f.soegning ? 'help.lstat.about.ls_tom_soeg' : 'help.lstat.about.ls_tom';
      else if (f.lagervalgt) second = 'help.lstat.about.ls_lager';
      else if (f.fakturadato) second = 'help.lstat.about.ls_faktura';
      return ['help.lstat.about.ls_liste', second];
    },

    tasks: L3LS_TASKS,

    order: function (ctx, st) {
      if (l3State(st) !== 'liste') return [];
      const f = l3Fl(st);
      const ids = [];
      if (f.skaev) ids.push('LS2');
      if (f.tom) return l3Ids(ids.concat(f.soegning ? ['LS8', 'LS6', 'LS1'] : ['LS6', 'LS1', 'LS8']));
      return l3Ids(ids.concat(['LS1', 'LS10', 'LS8', 'LS3', 'LS4', 'LS9', 'LS7', f.flereLagre ? 'LS5' : '', 'LS6']));
    },

    // A red stock figure: the item card's stock does not match what was bought and sold (lagerstatus.php:740-768).
    problem: function (ctx, st) {
      return l3State(st) === 'liste' && l3Fl(st).skaev ? 'LS2' : '';
    },

    fields: {
      dato: fieldKeys(L3S, 'ls_dato'),
      datotype: fieldKeys(L3S, 'ls_datotype'),
      lagervalg: fieldKeys(L3S, 'ls_lager'),
      varegruppe: fieldKeys(L3S, 'ls_gruppe'),
      nul_lager: fieldKeys(L3S, 'ls_nul'),
      udgaaede: fieldKeys(L3S, 'ls_udgaaede'),
      varenr: fieldKeys(L3S, 'ls_soeg'),
      varenavn: fieldKeys(L3S, 'ls_soeg'),
      enhed: fieldKeys(L3S, 'ls_soeg'),
      lss: fieldKeys(L3S, 'ls_tal'),
      linjer: fieldKeys(L3S, 'linjer')
    },

    // dateType, zStock and showClosed are camel case and the computed columns' boxes are lss_<column> (lagerstatus.php:84,
    // :433-443, :544); the page size select has only an id (:895).
    fieldKey: function (el) {
      const n = attr(el, 'name');
      if (n === 'dateType') return 'datotype';
      if (n === 'zStock') return 'nul_lager';
      if (n === 'showClosed') return 'udgaaede';
      if (/^lss_/.test(n)) return 'lss';
      if (attr(el, 'id') === 'lsPageSize') return 'linjer';
      return null;
    },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.lstat.key.01', group: 'keys' }     // Tilbage (:387)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      LSQ1: {
        q: 'help.lstat.q.lsq1.q',
        words: l3Words(['regnskabet', 'stemmer ikke', 'balance', 'lagerværdi', 'bogført', 'lagerkonto', 'årsafslutning', 'statusregulering'],
          ['accounts', 'does not match', 'balance sheet', 'stock value', 'posted', 'stock account', 'year end'],
          ['regnskapet', 'stemmer ikke', 'balanse', 'lagerverdi', 'bokført', 'lagerkonto', 'årsavslutning']),
        intro: 'help.lstat.q.lsq1.intro',
        steps: ['help.lstat.q.lsq1.step1', 'help.lstat.q.lsq1.step2']
      },
      LSQ2: {
        q: 'help.lstat.q.lsq2.q',
        words: l3Words(['købspris', 'kostpris', 'salgspris', 'forskel', 'værdi', 'fifo'],
          ['purchase price', 'cost price', 'sales price', 'difference', 'value', 'fifo'],
          ['kjøpesum', 'kostpris', 'salgspris', 'forskjell', 'verdi', 'fifo']),
        steps: ['help.lstat.q.lsq2.step1', 'help.lstat.q.lsq2.step2', 'help.lstat.q.lsq2.step3']
      },
      LSQ3: {
        q: 'help.lstat.q.lsq3.q',
        words: l3Words(['mangler', 'ikke med', 'kan ikke finde', 'vare mangler', 'udgået', 'nul', 'tom'],
          ['missing', 'not included', 'cannot find', 'item missing', 'discontinued', 'zero', 'empty'],
          ['mangler', 'ikke med', 'finner ikke', 'vare mangler', 'utgått', 'null', 'tom']),
        steps: ['help.lstat.q.lsq3.step1', 'help.lstat.q.lsq3.step2', 'help.lstat.q.lsq3.step3']
      }
    },

    questionOrder: function (ctx, st) {
      const f = l3Fl(st);
      if (f.tom) return ['LSQ3', 'LSQ2', 'LSQ1'];
      return ['LSQ1', 'LSQ2', 'LSQ3'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/lagerstatus.php'] = l3LagerstatusPage;

  // ---- Varespor (lager/varespor.php)
  // Reached from an item number on Lagerstatus (lager/lagerstatus.php:728), same frame. Evidence, lager/varespor.php:
  // Tilbage a[accesskey=L] to lagerstatus.php (:93-95), the item's number, unit and name (:108), then the blocks
  // === TILGANG === (purchases with an order: Dato, Antal, Lager with several warehouses, Firmanavn, Købsordre, Købspris;
  // Købt i alt, :113-160), === BESTILT === (open purchase orders, status 1-2, :164-196), === AFGANG === (sales with Firmanavn
  // and Faktura, Solgt i alt, :203-245), === ORDREBEHOLDNING === (open sales orders, :252-287) and === LAGERREGULERINGER ===
  // (lines without an order, signed, with I alt, :291-318). The names and numbers open the order in a new window
  // (td[onclick*="ordre.php"], :141-142, :186-187, :227-228, :275-276).

  const L3VS_TASKS = {
    VS1: task(L3S, 'VS1', 2, { target: 'td[onclick*="ordre.php"]', label: showKey(L3S, 'VS1'), kind: 'row' }),
    VS2: task(L3S, 'VS2', 2),
    VS3: task(L3S, 'VS3', 2),
    VS4: task(L3S, 'VS4', 1, { target: function (ctx) { return l3Back(ctx); }, label: showKey(L3S, 'VS4'), kind: 'button' })
  };

  const l3VaresporPage = {
    group: L3S,
    states: ['spor', 'unknown'],
    flags: ['ordrer'],

    name: function () {
      return 'help.lstat.name_varespor';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const spor = !!l3Q(doc, 'a[accesskey="L"][href*="lagerstatus.php"]') || (l3Param(ctx, 'vare_id') !== '' && l3Param(ctx, 'vare_id') !== '0');
      return { state: spor ? 'spor' : 'unknown', flags: { ordrer: spor && !!l3Q(doc, 'td[onclick*="ordre.php"]') }, data: {} };
    },

    about: function (ctx, st) {
      return l3State(st) === 'spor' ? ['help.lstat.about.vs', 'help.lstat.about.vs_antal'] : [];
    },

    tasks: L3VS_TASKS,

    order: function (ctx, st) {
      if (l3State(st) !== 'spor') return [];
      return l3Fl(st).ordrer ? ['VS1', 'VS2', 'VS3', 'VS4'] : ['VS3', 'VS2', 'VS4'];
    },

    fields: {},

    shortcuts: [
      { keys: 'Alt+L', text: 'help.lstat.key.02', group: 'keys' }     // Tilbage (:93)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      VSQ1: {
        q: 'help.lstat.q.vsq1.q',
        words: l3Words(['passer ikke', 'stemmer ikke', 'antal', 'beholdning', 'varekort', 'forkert'],
          ['does not match', 'quantity', 'stock', 'item card', 'wrong'],
          ['passer ikke', 'stemmer ikke', 'antall', 'beholdning', 'varekort', 'feil']),
        steps: ['help.lstat.q.vsq1.step1', 'help.lstat.q.vsq1.step2']
      }
    },

    questionOrder: function () {
      return ['VSQ1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/varespor.php'] = l3VaresporPage;

  // ---- Salg pr. postnummer (lager/salg_rapport.php)
  // Reached from Salg pr. postnummer on Varerapporter (lager/rapport.php:204, :473), same frame; it reads none of the
  // form's filters, only its own search row (:72-98). Evidence, lager/salg_rapport.php: #srPageFlex with Tilbage
  // a[accesskey=L][href*="rapport.php"] (:156-160); the grid #datatable-wrapper-salg_rapport (includes/grid.php:1264) of
  // invoiced orders and credit notes by item, department, postcode, town and invoice date (:99-138) with the columns
  // Varenr, Beskrivelse, Afdeling, Postnr, Antal, Beløb (DKK), By, Fakturadato (Danish in the definitions, :186-322); the
  // search row (grid.php:1386-1398) search[salg_rapport][varenr|beskrivelse|bynavn], turned by the page's script into the
  // department list select[name="search[salg_rapport][afd]"], #postnr_range_input (fra:til) and the date range
  // #dato_range_picker (:426-610); sortable headers th.<field> (grid.php:1361-1368); the grid menu .dropdown-trigger with
  // Søg, Ryd søgning, Eksportér til CSV (grid.php:1405-1430).

  const L3SR_TASKS = {
    SR1: task(L3S, 'SR1', 3, { target: '#dato_range_picker', label: fieldLabel(L3S, 'dato_range_picker'), kind: 'field' }),
    SR2: task(L3S, 'SR2', 2, { target: '#postnr_range_input', label: fieldLabel(L3S, 'postnr_range_input'), kind: 'field' }),
    SR3: task(L3S, 'SR3', 2, { target: 'select[name="search[salg_rapport][afd]"]', label: fieldLabel(L3S, 'search:afd'), kind: 'field' }),
    SR4: task(L3S, 'SR4', 2, { target: 'input[name="search[salg_rapport][varenr]"]', label: fieldLabel(L3S, 'search:varenr'), kind: 'field' }),
    SR5: task(L3S, 'SR5', 2, { target: '#datatable-wrapper-salg_rapport th.total_dkk', label: showKey(L3S, 'SR5'), kind: 'button' }),
    SR6: task(L3S, 'SR6', 2, { target: '#datatable-wrapper-salg_rapport .dropdown-trigger', label: showKey(L3S, 'SR6'), kind: 'button' }),
    SR7: task(L3S, 'SR7', 0, {
      target: function (ctx) { return l3Inner(l3Q(ctx.doc, 'a[accesskey="L"][href*="rapport.php"]')); },
      label: showKey(L3S, 'VS4'),
      kind: 'button'
    }, stepKeys(L3S, 'VS4', 1, 1))
  };

  const l3SalgRapportPage = {
    group: L3S,
    states: ['liste', 'unknown'],
    flags: ['filter'],

    name: function () {
      return 'help.lstat.name_postnr';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      if (!l3Q(doc, '#datatable-wrapper-salg_rapport')) return { state: 'unknown', flags: { filter: false }, data: {} };
      // The date range starts as 2000-01-01 to 2099-12-31, which is no filter (salg_rapport.php:72-82)
      const range = l3Val(l3Q(doc, 'input[name="search[salg_rapport][fakturadate]"]')).replace(/\s/g, '');
      const filter = l3All(doc, '[name^="search[salg_rapport]"]').some(function (el) {
        return attr(el, 'name') !== 'search[salg_rapport][fakturadate]' && l3Val(el).replace(/[\s:]/g, '') !== '';
      }) || (range !== '' && range !== '2000-01-01:2099-12-31');
      return { state: 'liste', flags: { filter: filter }, data: {} };
    },

    about: function (ctx, st) {
      if (l3State(st) !== 'liste') return [];
      return ['help.lstat.about.sr', l3Fl(st).filter ? 'help.lstat.about.sr_filter' : 'help.lstat.about.sr_alt'];
    },

    tasks: L3SR_TASKS,

    order: function (ctx, st) {
      return l3State(st) === 'liste' ? ['SR1', 'SR2', 'SR5', 'SR3', 'SR4', 'SR6', 'SR7'] : [];
    },

    fields: {
      dato_range_picker: fieldKeys(L3S, 'dato_range_picker'),
      postnr_range_input: fieldKeys(L3S, 'postnr_range_input'),
      'search:afd': fieldKeys(L3S, 'search:afd'),
      'search:varenr': fieldKeys(L3S, 'search:varenr'),
      'search:beskrivelse': fieldKeys(L3S, 'search:beskrivelse'),
      'search:bynavn': fieldKeys(L3S, 'search:bynavn'),
      rowcount: fieldKeys(L3S, 'rowcount')
    },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.lstat.key.01', group: 'keys' }     // Tilbage (:156)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      SRQ1: {
        q: 'help.lstat.q.srq1.q',
        words: l3Words(['hvilke salg', 'moms', 'uden moms', 'kreditnota', 'kreditnotaer', 'ordrer', 'tilbud', 'fakturadato'],
          ['which sales', 'vat', 'without vat', 'credit note', 'credit notes', 'orders', 'quotes', 'invoice date'],
          ['hvilke salg', 'mva', 'uten mva', 'kreditnota', 'kreditnotaer', 'ordrer', 'tilbud', 'fakturadato']),
        steps: ['help.lstat.q.srq1.step1', 'help.lstat.q.srq1.step2']
      }
    },

    questionOrder: function () {
      return ['SRQ1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/salg_rapport.php'] = l3SalgRapportPage;

  // ---- Beholdningsliste (lager/beholdningsliste.php)
  // Not in the sidebar; the chat's Gå dertil opens it in the frame (javascript/saldi-assist-navigate.js:57), as the old
  // menu did (index/menu.php:279-280). Evidence, lager/beholdningsliste.php: without a warehouse of its own (none from the
  // address, none from the employee's department) the page lists the warehouses as links a[href^="beholdningsliste.php?lager="]
  // under Beholdning (:76-95); the list Beholdningsliste - <warehouse> (:99) with Vis kostpriser a[href*="vis_kost=on"] or
  // Skjul Kostpris (:112-117), sortable headings Varenr., Beskrivelse, Salgspris, Beholdning a[href*="sort="] (:118-124),
  // every open item (lukket, :138) with its stock in the warehouse and Reserveret in the title of the number (:158-185);
  // Kostpris is the first supplier's price on the item card (:150-155).

  const L3BH_TASKS = {
    BH1: task(L3S, 'BH1', 1, { target: 'a[href^="beholdningsliste.php?lager="]', label: showKey(L3S, 'BH1'), kind: 'row' }),
    BH2: task(L3S, 'BH2', 2, { target: 'a[href*="beholdningsliste.php?sort=beholdning"]', label: showKey(L3S, 'BH2'), kind: 'button' }),
    BH3: task(L3S, 'BH3', 2, {
      target: function (ctx, st) {
        return l3Fl(st).kostpris ? l3Q(ctx.doc, 'a[href^="beholdningsliste.php?sort="]:not([href*="vis_kost"])') : l3Q(ctx.doc, 'a[href*="vis_kost=on"]');
      },
      label: showKey(L3S, 'BH3'),
      kind: 'button'
    }),
    BH4: task(L3S, 'BH4', 1)
  };

  const l3BeholdningPage = {
    group: L3S,
    states: ['vaelgLager', 'liste', 'unknown'],
    flags: ['kostpris'],

    name: function () {
      return 'help.lstat.name_beholdning';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      let state = 'unknown';
      if (l3Q(doc, 'a[href*="beholdningsliste.php?sort="]')) state = 'liste';
      else if (l3Q(doc, 'a[href^="beholdningsliste.php?lager="]')) state = 'vaelgLager';
      return { state: state, flags: { kostpris: state === 'liste' && l3Param(ctx, 'vis_kost') === 'on' }, data: {} };
    },

    about: function (ctx, st) {
      const s = l3State(st);
      if (s === 'vaelgLager') return ['help.lstat.about.bh_vaelg'];
      if (s === 'liste') return ['help.lstat.about.bh', l3Fl(st).kostpris ? 'help.lstat.about.bh_kost' : 'help.lstat.about.bh_reserveret'];
      return [];
    },

    tasks: L3BH_TASKS,

    order: function (ctx, st) {
      const s = l3State(st);
      if (s === 'vaelgLager') return ['BH1'];
      return s === 'liste' ? ['BH2', 'BH3', 'BH4'] : [];
    },

    fields: {},

    shortcuts: [],

    questions: {
      BHQ1: {
        q: 'help.lstat.q.bhq1.q',
        words: l3Words(['mangler', 'ikke med', 'lukket', 'udgået', 'andet lager', 'lager'],
          ['missing', 'not included', 'closed', 'discontinued', 'other warehouse', 'warehouse'],
          ['mangler', 'ikke med', 'lukket', 'utgått', 'annet lager', 'lager']),
        steps: ['help.lstat.q.bhq1.step1', 'help.lstat.q.bhq1.step2']
      }
    },

    questionOrder: function () {
      return ['BHQ1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/beholdningsliste.php'] = l3BeholdningPage;

  // ---- Lagerhistorik (lager/stockLog.php)
  // Reached from Log in the item card's stock table, shown when the item has log lines
  // (lager/productCardIncludes/showLocations.php:214-226, included at lager/varekort.php:1843); the card's own
  // Lagerhistorik button opens lager/productCardIncludes/stockLog.php instead (varekort.php:2099, author L1). Evidence,
  // lager/stockLog.php: one table of the item's stock log, newest first (:39-49), headed Bruger, Initialer, Årsag, Antal,
  // Tidspkt (Danish in the markup, :53); the lines come from a stock change on the item card with initials and a reason
  // (lager/varekort.php:323-338), a receipt from a purchase order (kreditor/modtag.php:275-286) and batch details
  // (lager/batch_udfyld.php:202); Luk a[href^="varekort.php?id="] with a button back to the item card (:62).

  const L3SL_TASKS = {
    SL1: task(L3S, 'SL1', 2),
    SL2: task(L3S, 'SL2', 1, { target: function (ctx) { return l3Inner(l3Q(ctx.doc, 'a[href^="varekort.php?id="]')); }, label: showKey(L3S, 'SL2'), kind: 'button' })
  };

  const l3StockLogPage = {
    group: L3S,
    states: ['log', 'unknown'],
    flags: ['tom'],

    name: function () {
      return 'help.lstat.name_lagerhistorik';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      if (!l3Q(doc, 'a[href^="varekort.php?id="]')) return { state: 'unknown', flags: { tom: false }, data: {} };
      // a log line has Antal and Tidspkt right-aligned; the heading's Tidspkt is centred (stockLog.php:53-57)
      return { state: 'log', flags: { tom: !l3Q(doc, 'td[align="right"]') }, data: {} };
    },

    about: function (ctx, st) {
      if (l3State(st) !== 'log') return [];
      return [l3Fl(st).tom ? 'help.lstat.about.sl_tom' : 'help.lstat.about.sl'];
    },

    tasks: L3SL_TASKS,

    order: function (ctx, st) {
      if (l3State(st) !== 'log') return [];
      return l3Fl(st).tom ? ['SL2'] : ['SL1', 'SL2'];
    },

    fields: {},

    shortcuts: [],

    questions: {
      SLQ1: {
        q: 'help.lstat.q.slq1.q',
        words: l3Words(['lagerhistorik', 'hvem', 'ændret', 'rettet', 'årsag', 'log', 'beholdning'],
          ['inventory history', 'who', 'changed', 'corrected', 'reason', 'log', 'stock'],
          ['lagerhistorikk', 'hvem', 'endret', 'rettet', 'årsak', 'logg', 'beholdning']),
        steps: ['help.lstat.q.slq1.step1', 'help.lstat.q.slq1.step2']
      }
    },

    questionOrder: function () {
      return ['SLQ1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/stockLog.php'] = l3StockLogPage;

  // ---- Beholdningsrapport (lager/minmaxstock.php)
  // Reached from Beholdningsrapport on Varerapporter, printed for one tenant only (lager/rapport.php:148, :456-459), same
  // frame. Evidence, lager/minmaxstock.php: Luk a[accesskey=L] back to the form with its choices (:52-56, :72) and csv
  // a[href*="minmax"] (:74); one line per item, warehouse and variant whose stock is at or below its Min, for items with a
  // Min and a Max in stock-kept groups (:98-119, :148-170): Afd, Varenr, Beskrivelse, Beholdning, Min, Max and Køb = Max
  // minus Beholdning (Danish in the markup, :142-144, :165).

  const L3MM_TASKS = {
    MM1: task(L3S, 'MM1', 2),
    MM2: task(L3S, 'MM2', 1, { target: 'a[href*="minmax"]', label: showKey(L3S, 'MM2'), kind: 'button' }),
    MM3: task(L3S, 'MM3', 1, { target: 'a[accesskey="L"]', label: showKey(L3S, 'MM3'), kind: 'button' })
  };

  const l3MinmaxPage = {
    group: L3S,
    states: ['liste', 'unknown'],
    flags: [],

    name: function () {
      return 'help.lstat.name_minmax';
    },

    detect: function (ctx) {
      return { state: l3Q(ctx.doc, 'a[href*="minmax"]') ? 'liste' : 'unknown', flags: {}, data: {} };
    },

    about: function (ctx, st) {
      return l3State(st) === 'liste' ? ['help.lstat.about.mm'] : [];
    },

    tasks: L3MM_TASKS,

    order: function (ctx, st) {
      return l3State(st) === 'liste' ? ['MM1', 'MM2', 'MM3'] : [];
    },

    fields: {},

    shortcuts: [
      { keys: 'Alt+L', text: 'help.lstat.key.01', group: 'keys' }     // Luk (:72)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      MMQ1: {
        q: 'help.lstat.q.mmq1.q',
        words: l3Words(['min', 'max', 'minimum', 'maksimum', 'genbestil', 'indkøb', 'mangler'],
          ['min', 'max', 'minimum', 'maximum', 'reorder', 'purchase', 'missing'],
          ['min', 'maks', 'minimum', 'maksimum', 'bestill på nytt', 'innkjøp', 'mangler']),
        steps: ['help.lstat.q.mmq1.step1', 'help.lstat.q.mmq1.step2']
      }
    },

    questionOrder: function () {
      return ['MMQ1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/minmaxstock.php'] = l3MinmaxPage;

  // ---- Udløbsrapport (lager/udlobsrapport.php)
  // Sidebar Lager » Udløbsrapport, only when batch and expiry dates are in use and the user has Varerapport
  // (index/main.php:371-374); also from the notification Varer med udløb (includes/notifications.php:292). Evidence,
  // lager/udlobsrapport.php: Tilbage a[accesskey=L] to the page in returside (:72-75, default index/menu.php); a yellow box
  // div[style*="fff7e6"] with the number of batches in stock without an expiry date or batch number and, for users who may
  // change items, Udfyld nu a[href^="batch_udfyld.php"] (:86-92, includes/stdFunc/fefo.php:213-252); Udløber inden, a
  // select without a name that reloads with dage= (7, 14, 30, 60, 90 dage or Alle; Danish in the markup, :95-104), Vis kun
  // udløbne a[href*="udloebet=1"] and Eksporter CSV a[href*="csv=1"] (:105-107); the table of batches with an expiry date
  // and stock left, the first to expire at the top (:111-133, :175-213): Varenr a[href^="varekort.php?id="] with Batch
  // oversigt a[href^="batch_oversigt.php"] under it, Beskrivelse, Batchnr., Udløbsdato, Dage til udløb, Restlager,
  // Lagerværdi (rest times the item's kostpris), Lager; red rows are expired, yellow within the user's warning days, green
  // later (:190-195, fefo.php:113-139; the days are Advar om udløb (dage før) in Personlige indstillinger,
  // systemdata/personalSettings.php:631-636). Sales take the batch that expires first (fefo.php:31-46,
  // includes/ordrefunc.php:1211-1213).

  const L3U = 'ludl';
  const L3U_FLAGS = ['mangler', 'kanUdfylde', 'kunUdloebne', 'udloebne', 'tom'];

  const L3U_TASKS = {
    UD1: task(L3U, 'UD1', 3, { target: 'a[href^="batch_udfyld.php"]', label: showKey(L3U, 'UD1'), kind: 'button' }),
    UD2: task(L3U, 'UD2', 2, { target: 'select[onchange*="udlobsrapport.php?dage="]', label: showKey(L3U, 'UD2'), kind: 'field' }),
    UD3: task(L3U, 'UD3', 2, { target: 'a[href*="udloebet=1"]', label: showKey(L3U, 'UD3'), kind: 'button' }),
    UD4: task(L3U, 'UD4', 2, { target: 'a[href*="csv=1"]', label: showKey(L3U, 'UD4'), kind: 'button' }),
    UD5: task(L3U, 'UD5', 2, { target: 'a[href^="batch_oversigt.php"]', label: showKey(L3U, 'UD5'), kind: 'button' }),
    UD6: task(L3U, 'UD6', 1, { target: 'a[href^="varekort.php?id="]', label: showKey(L3U, 'UD6'), kind: 'row' }),
    UD7: task(L3U, 'UD7', 3)
  };

  const l3UdlobPage = {
    group: L3U,
    states: ['liste', 'unknown'],
    flags: L3U_FLAGS,

    name: function () {
      return 'help.ludl.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const f = l3Flags(L3U_FLAGS);
      if (!l3Q(doc, 'select[onchange*="udlobsrapport.php"]')) return { state: 'unknown', flags: f, data: {} };
      f.kanUdfylde = !!l3Q(doc, 'a[href^="batch_udfyld.php"]');
      f.mangler = f.kanUdfylde || !!l3Q(doc, 'div[style*="fff7e6"]');
      f.kunUdloebne = l3Param(ctx, 'udloebet') !== '';
      f.udloebne = !!l3Q(doc, 'tr[style*="ffcccc"]');
      f.tom = !l3Q(doc, 'a[href^="batch_oversigt.php"]');
      return { state: 'liste', flags: f, data: {} };
    },

    about: function (ctx, st) {
      if (l3State(st) !== 'liste') return [];
      const f = l3Fl(st);
      let second = 'help.ludl.about.vaerdi';
      if (f.mangler) second = 'help.ludl.about.mangler';
      else if (f.tom) second = f.kunUdloebne ? 'help.ludl.about.tom_udloebne' : 'help.ludl.about.tom';
      else if (f.kunUdloebne) second = 'help.ludl.about.kun_udloebne';
      return ['help.ludl.about.liste', second];
    },

    tasks: L3U_TASKS,

    order: function (ctx, st) {
      if (l3State(st) !== 'liste') return [];
      const f = l3Fl(st);
      const ids = f.mangler ? ['UD1'] : [];
      if (f.tom) return l3Ids(ids.concat(f.kunUdloebne ? ['UD2'] : ['UD2', 'UD7']));
      if (f.kunUdloebne) return l3Ids(ids.concat(['UD5', 'UD6', 'UD4', 'UD2']));
      if (f.udloebne) return l3Ids(ids.concat(['UD3', 'UD5', 'UD4', 'UD2', 'UD6', 'UD7']));
      return l3Ids(ids.concat(['UD2', 'UD5', 'UD4', 'UD3', 'UD6', 'UD7']));
    },

    // Batches without an expiry date or batch number are left out of the report (udlobsrapport.php:86-92).
    problem: function (ctx, st) {
      return l3State(st) === 'liste' && l3Fl(st).mangler ? 'UD1' : '';
    },

    fields: {},

    shortcuts: [
      { keys: 'Alt+L', text: 'help.ludl.key.01', group: 'keys' }      // Tilbage (:73)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.ludl.q.q1.q',
        words: l3Words(['mangler', 'ikke med', 'kan ikke se', 'udløbsdato', 'batchnummer', 'tom'],
          ['missing', 'not included', 'cannot see', 'expiry date', 'batch number', 'empty'],
          ['mangler', 'ikke med', 'kan ikke se', 'utløpsdato', 'batchnummer', 'tom']),
        steps: ['help.ludl.q.q1.step1', 'help.ludl.q.q1.step2', 'help.ludl.q.q1.step3']
      },
      Q2: {
        q: 'help.ludl.q.q2.q',
        words: l3Words(['først', 'fefo', 'sælger', 'salg', 'hvilken batch', 'rækkefølge'],
          ['first', 'fefo', 'sells', 'sale', 'which batch', 'order'],
          ['først', 'fefo', 'selger', 'salg', 'hvilken batch', 'rekkefølge']),
        steps: ['help.ludl.q.q2.step1', 'help.ludl.q.q2.step2']
      },
      Q3: {
        q: 'help.ludl.q.q3.q',
        words: l3Words(['farver', 'rød', 'gul', 'grøn', 'advarsel', 'dage før'],
          ['colours', 'colors', 'red', 'yellow', 'green', 'warning', 'days before'],
          ['farger', 'rød', 'gul', 'grønn', 'advarsel', 'dager før']),
        steps: ['help.ludl.q.q3.step1'].concat(stepKeys(L3U, 'UD7', 1, 2))
      }
    },

    questionOrder: function (ctx, st) {
      const f = l3Fl(st);
      if (f.mangler || f.tom) return ['Q1', 'Q3', 'Q2'];
      return ['Q3', 'Q2', 'Q1'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/udlobsrapport.php'] = l3UdlobPage;

  // ---- Prisliste (lager/pricelist.php)
  // Reached from Prisliste on Varerapporter (lager/rapport.php:203, :468), same frame. Evidence, lager/pricelist.php:
  // #plPageFlex with Tilbage a[accesskey=L] to rapport.php (:197) and CSV a[href^="pricelist.php?csv=1"] (:202); the form
  // (:237-296): Lager select[name=lagervalg] (:241-249), Varegruppe select[name=varegruppe] (:251-258), Fri tekst
  // input[name=custom_text] (:261, printed over the list and on the print view, :183-187, :206-207), the checkboxes 0 på
  // lager zero_stock, Vis skjulte produkter show_all_products, Vis antal, Vis enhed, Vis B2B salgspris, Vis salgspris,
  // Vis vejl.pris (:263-285; the three prices are on by default, :47-55), Vis input[name=submit] (:287) and Printvenlig
  // a[href*="autoprint=1"] in a new tab (:289, a plain list without paging, :182-188); the list #plGridTable (:316) has only
  // items of stock-kept groups (:161-162), on the price list unless Vis skjulte produkter (:127-157; Vises på prisliste on the
  // item card, lager/productCardIncludes/showPrices.php:100-108), with stock unless 0 på lager, and with a purchase, sale
  // or stock (:375); footer #plPageStatus, #plPageSize (:454-460).

  const L3P = 'lpris';
  const L3P_FLAGS = ['tom', 'flereSider', 'skjulte', 'fritekst'];

  const L3P_TASKS = {
    PL1: task(L3P, 'PL1', 3, { target: 'input[name="show_salgspris"]', label: fieldLabel(L3P, 'show_salgspris'), kind: 'field' }),
    PL2: task(L3P, 'PL2', 1, { target: 'select[name="varegruppe"]', label: fieldLabel(L3P, 'varegruppe'), kind: 'field' }, stepKeys(L3P, 'PL1', 3, 3)),
    PL3: task(L3P, 'PL3', 1, { target: 'input[name="custom_text"]', label: fieldLabel(L3P, 'custom_text'), kind: 'field' }, stepKeys(L3P, 'PL1', 3, 3)),
    PL4: task(L3P, 'PL4', 2, { target: 'a[href*="autoprint=1"]', label: showKey(L3P, 'PL4'), kind: 'button' }),
    PL5: task(L3P, 'PL5', 2, { target: function (ctx) { return l3Inner(l3Q(ctx.doc, 'a[href^="pricelist.php?csv=1"]')); }, label: showKey(L3P, 'PL5'), kind: 'button' }),
    PL6: task(L3P, 'PL6', 3),
    PL7: task(L3P, 'PL7', 1, { target: 'input[name="zero_stock"]', label: fieldLabel(L3P, 'zero_stock'), kind: 'field' }, stepKeys(L3P, 'PL1', 3, 3))
  };

  const l3PrislistePage = {
    group: L3P,
    states: ['liste', 'unknown'],
    flags: L3P_FLAGS,

    name: function () {
      return 'help.lpris.name';
    },

    detect: function (ctx) {
      const doc = ctx.doc;
      const f = l3Flags(L3P_FLAGS);
      if (!l3Q(doc, 'input[name="custom_text"]') && !l3Q(doc, '#plGridTable')) return { state: 'unknown', flags: f, data: {} };
      f.tom = l3Zero(doc, '#plPageStatus');
      f.flereSider = !!l3Q(doc, '#plNavButtons a.navbutton');
      f.skjulte = l3Checked(l3Q(doc, 'input[name="show_all_products"]'));
      f.fritekst = l3Val(l3Q(doc, 'input[name="custom_text"]')).trim() !== '';
      return { state: 'liste', flags: f, data: {} };
    },

    about: function (ctx, st) {
      if (l3State(st) !== 'liste') return [];
      const f = l3Fl(st);
      let second = 'help.lpris.about.udvalg';
      if (f.tom) second = 'help.lpris.about.tom';
      else if (f.skjulte) second = 'help.lpris.about.skjulte';
      return ['help.lpris.about.liste', second];
    },

    tasks: L3P_TASKS,

    order: function (ctx, st) {
      if (l3State(st) !== 'liste') return [];
      const f = l3Fl(st);
      if (f.tom) return ['PL7', 'PL6', 'PL2', 'PL1'];
      return l3Ids(['PL1', 'PL4', 'PL5', 'PL2', f.fritekst ? '' : 'PL3', 'PL6', 'PL7', 'PL3']);
    },

    fields: {
      varegruppe: fieldKeys(L3P, 'varegruppe'),
      custom_text: fieldKeys(L3P, 'custom_text'),
      zero_stock: fieldKeys(L3P, 'zero_stock'),
      show_all_products: fieldKeys(L3P, 'show_all_products'),
      show_antal: fieldKeys(L3P, 'show_antal'),
      show_enhed: fieldKeys(L3P, 'show_enhed'),
      show_tier_price: fieldKeys(L3P, 'show_tier_price'),
      show_salgspris: fieldKeys(L3P, 'show_salgspris'),
      show_retail_price: fieldKeys(L3P, 'show_retail_price'),
      linjer: fieldKeys(L3P, 'linjer')
    },

    // The page size select has only an id (pricelist.php:456).
    fieldKey: function (el) {
      return attr(el, 'id') === 'plPageSize' ? 'linjer' : null;
    },

    shortcuts: [
      { keys: 'Alt+L', text: 'help.lpris.key.01', group: 'keys' }     // Tilbage (:197)
    ],

    shortcutsNote: 'help.common.shortcuts_note',

    questions: {
      Q1: {
        q: 'help.lpris.q.q1.q',
        words: l3Words(['mangler', 'ikke med', 'kan ikke finde', 'vare mangler', 'skjult', 'tom'],
          ['missing', 'not included', 'cannot find', 'item missing', 'hidden', 'empty'],
          ['mangler', 'ikke med', 'finner ikke', 'vare mangler', 'skjult', 'tom']),
        // step 3: 0 på lager adds items without stock, and only those bought or sold (pricelist.php:136-140, :375)
        steps: ['help.lpris.q.q1.step1', 'help.lpris.q.q1.step2', 'help.lpris.q.q1.step3']
      },
      Q2: {
        q: 'help.lpris.q.q2.q',
        words: l3Words(['b2b', 'vejledende', 'vejl.pris', 'priser', 'forhandler', 'engros'],
          ['b2b', 'retail price', 'recommended price', 'prices', 'dealer', 'wholesale'],
          ['b2b', 'veiledende', 'vejl.pris', 'priser', 'forhandler', 'engros']),
        steps: ['help.lpris.q.q2.step1', 'help.lpris.q.q2.step2']
      }
    },

    // A missing item is what support hears most on a price list (Q1); Q2 explains the columns
    questionOrder: function () {
      return ['Q1', 'Q2'];
    },

    signal: function () {
      return false;
    }
  };

  PAGES['lager/pricelist.php'] = l3PrislistePage;
})();
