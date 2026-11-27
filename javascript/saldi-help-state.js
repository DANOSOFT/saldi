// ---- javascript/saldi-help-state.js --- ver 5.1.0 --- 2026.11.09 ---
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The page help as data for Saldi Assist (window.SaldiHelpState, doc/hjaelp_saldi_ai.md §19). build() turns a page
// object of window.SaldiHelpContent, the page context and its detected state into a HelpState v1: every string is
// final, translated text, and the only markup is **bold** in steps, paragraphs and field texts. javascript/saldi-help.js
// sends it to the chat as saldi:help:state, and the chat draws it in its own design.
// Pure: no DOM writes, focus, clicks, network, timers or storage. It calls the page's read-only functions (name,
// about, order, questionOrder, problem) and finds the Vis mig targets through the resolver the caller hands it.
//
// 20261108 CL/ASR Created: Hjælp inside Saldi Assist, the host side of the merge contract (spec §19).
// 20261108 CL/ASR Review: the state keeps the chat parser's bounds (CAPS: lists cut, strings clipped, an empty or
//                 malformed entry dropped, with one warning), because the chat refuses a whole state that breaks one.
//                 A frame Saldi cannot read gives a state with route '' and the keys that work everywhere, not null.
//                 Additive fields the v1 chat ignores (spec §19.9): support, contactWords, more, disclosure, field.ask
//                 and six more labels. For a v1.1 chat (input fields: true) the page's fields go into the search as
//                 kind 'field', with an id from fieldIds() that the shell maps back for Vis mig (field:<id>).
// 20261109 CL/ASR Variants (spec §24): resolve() gives the page object that serves a page context (a route's variant named by
//                 its variant(ctx), such as one settings section of settingsSection.php, else the route's own page);
//                 allPages() lists every route's page and its variants for the tests and tools.
// 20261109 CL/ASR Page questions in the chat (spec §25): questions, the ids of the state's top three prepared questions for
//                 the chat's start screen (the search's order: questionOrder() first, then the declared order), and the
//                 labels questions, askMore and related. bound() keeps the ids consistent with the search it sends, and
//                 neither its cut to 120 items nor the size cut ever drops an item questions names.
(function () {
  'use strict';

  const VERSION = 1;
  const MAX_BYTES = 96 * 1024;          // the loader refuses a larger state (JSON, contract §2)
  const LABELS = ['helpButton', 'problem', 'field', 'learn', 'about', 'tasks', 'showMe', 'showMeFor', 'tour', 'tourDesc', 'shortcuts', 'fromHelp'];
  // Labels for the additive fields (spec §19.9) and the page questions (spec §25: questions holds %s for the state's title);
  // the v1 chat reads only LABELS, and a label the caller does not hand is left out (the chat then uses its own words)
  const MORE_LABELS = ['back', 'support', 'call', 'write', 'askField', 'askThis', 'questions', 'askMore', 'related'];
  const MORE_KINDS = ['guide', 'tour', 'shortcuts'];
  // The bounds the chat's parser holds a state to (support-sally frontend/lib/page-help.ts, MAX and the list sizes). One
  // string or list beyond them makes the chat refuse the whole state and show no page help at all, so bound() keeps
  // them here. Lengths are UTF-16 units after control characters and runs of white space became one space, as there.
  // The last group bounds the additive fields of spec §19.9 (proposed for the contract, not checked by the v1 chat).
  const CAPS = {
    route: 200, title: 200, why: 400, step: 600, label: 160, fieldText: 1500, about: 1500, intro: 1000, word: 40,
    words: 40, searchItems: 120, searchSteps: 6, taskSteps: 4, tasks: 3, abouts: 2, tourSteps: 99, sections: 12,
    sectionItems: 40, shortcutText: 300, keys: 60, note: 600,
    phone: 40, mail: 160, contactWords: 60, more: 3, disclosure: 3, disclosureText: 600,
    questions: 3
  };
  const ID = /^[A-Za-z0-9_]{1,24}$/;
  const CONTROL = /[\u0000-\u001f\u007f-\u009f\u2028\u2029]/g;

  function str(v) { return v === null || v === undefined ? '' : String(v); }
  function list(v) { return Array.isArray(v) ? v : []; }
  function own(o, k) { return !!o && typeof o === 'object' && Object.prototype.hasOwnProperty.call(o, k); }
  /** A plain-text line (a title, a why line, a question, a label): the ** markers dropped, the spaces collapsed. */
  function plain(v) { return str(v).replace(/\*\*/g, '').replace(/\s+/g, ' ').trim(); }
  /** One line as the chat reads it: control characters and runs of white space become one space, the ends trimmed. */
  function oneLine(v) { return str(v).replace(CONTROL, ' ').replace(/\s+/g, ' ').trim(); }
  /** At most max UTF-16 units, the cut marked with an ellipsis and never inside a surrogate pair. */
  function clip(s, max) {
    if (s.length <= max) { return s; }
    let cut = max - 1;
    const c = s.charCodeAt(cut - 1);
    if (c >= 0xd800 && c <= 0xdbff) { cut -= 1; }
    return s.slice(0, cut).replace(/\s+$/, '') + '\u2026';
  }
  /**
   * The search id of each field help of a page (page.fields), in its declared order: the key itself when it is a valid id,
   * else the key with every other character as '_' and at most 24 long ('search:kladdenote' → 'search_kladdenote'), else
   * 'f<n>'; each id once. The shell maps a field:<id> target back to its key with the same function.
   */
  function fieldIds(fields) {
    const out = [];
    const taken = {};
    if (!fields || typeof fields !== 'object') { return out; }
    Object.keys(fields).forEach(function (key, n) {
      let id = ID.test(key) ? key : key.replace(/[^A-Za-z0-9_]/g, '_').slice(0, 24);
      if (!ID.test(id) || own(taken, id)) { id = 'f' + n; }
      while (own(taken, id)) { id = id + '_'; }
      taken[id] = true;
      out.push({ id: id, key: key });
    });
    return out;
  }
  /** The size of a string in UTF-8, as the loader's limit counts it at the most. */
  function bytes(s) {
    const t = str(s);
    let n = 0;
    for (let i = 0; i < t.length; i++) {
      const c = t.charCodeAt(i);
      if (c < 0x80) { n += 1; } else if (c < 0x800) { n += 2; } else if (c >= 0xd800 && c <= 0xdbff && i + 1 < t.length) { n += 4; i += 1; } else { n += 3; }
    }
    return n;
  }
  /** Search vocabulary from a text: lower case, without punctuation and stop words, each word once. */
  function vocab(texts, stop) {
    const out = [];
    texts.forEach(function (x) {
      plain(x).toLowerCase().replace(/[»«"'?!.,:;()/]/g, ' ').split(/\s+/).forEach(function (w) {
        if (w.length >= 2 && stop.indexOf(w) < 0 && out.indexOf(w) < 0) { out.push(w); }
      });
    });
    return out;
  }
  function visible(n) {
    try {
      if (typeof n.getClientRects !== 'function') { return n.isConnected !== false; }
      return Array.prototype.some.call(n.getClientRects(), function (r) { return r.width > 0 && r.height > 0; });
    } catch (e) { return false; }
  }
  /** The shell hands its own resolver (resolveShow() in saldi-help.js); this one is the same rule for other callers. */
  function defaultResolve(show, ctx, st, page) {
    let n = null;
    if (typeof show.target === 'function') {
      n = show.target.call(page, ctx, st);
    } else if (typeof show.target === 'string' && ctx.doc && typeof ctx.doc.querySelectorAll === 'function') {
      n = Array.prototype.find.call(ctx.doc.querySelectorAll(show.target), visible) || null;
    }
    return n && n.nodeType === 1 && visible(n) ? n : null;
  }

  /** A page object as the shell takes one: an object with a text group. */
  function isPage(p) { return !!p && typeof p === 'object' && typeof p.group === 'string'; }
  /**
   * The page object that serves the frame (spec §24): a page with variants (one route that shows several pages, such as
   * the settings sections of systemdata/settingsSection.php?s=<group>.<section>) names the variant for this page context
   * with variant(ctx); that variant, a page object with its own text group, is the page. Any other answer, a variant
   * that is not a page or a variant() that throws (one warning) gives the route's own page. One level only: a variant's
   * own variants are not looked at. { page, key } (key '' for the route's own page), or null when page is not a page.
   */
  function resolve(page, ctx, warn) {
    if (!isPage(page)) { return null; }
    const vs = page.variants;
    if (typeof page.variant !== 'function' || !vs || typeof vs !== 'object') { return { page: page, key: '' }; }
    let key = '';
    try { key = page.variant.call(page, ctx); } catch (e) { if (typeof warn === 'function') { warn('variant()', e); } key = ''; }
    if (typeof key !== 'string' || key === '' || !own(vs, key) || !isPage(vs[key])) { return { page: page, key: '' }; }
    return { page: vs[key], key: key };
  }
  /** Every page object of a pages map, each route's own page followed by its variants: [{ route, key, page }]. */
  function allPages(pages) {
    const out = [];
    if (!pages || typeof pages !== 'object') { return out; }
    Object.keys(pages).forEach(function (route) {
      const p = pages[route];
      if (!isPage(p)) { return; }
      out.push({ route: route, key: '', page: p });
      if (typeof p.variant === 'function' && p.variants && typeof p.variants === 'object') {
        Object.keys(p.variants).forEach(function (key) {
          if (isPage(p.variants[key])) { out.push({ route: route, key: key, page: p.variants[key] }); }
        });
      }
    });
    return out;
  }

  /**
   * The HelpState v1 of the page in the frame. Without a readable frame (ctx null) it has route '', no page content and
   * the keys that work everywhere (contract §5); null only without an input.
   *
   * input: {
   *   page,        a page object of SaldiHelpContent.pages whose texts are loaded, or null (no help content yet or at all)
   *   ctx, st,     the page context and detect() result the page functions take (spec §3.2)
   *   lang,        'da' | 'en' | 'no' (default ctx.lang)
   *   text,        (key, ...args) → string: a help text in the user's language (T() in saldi-help.js)
   *   title,       the page's name when page is null (the generic label)
   *   field,       { key, label, text, canShow } of "Feltet du stod i", or null (key: its field key, for the search order)
   *   tourCount,   visible steps of the page's Rundvisning (0: none)
   *   learnOpen,   the initial fold state of "Lær siden at kende"
   *   shortcuts,   { global: [{ text, keys }], titles: { keys, codes, global } }, all translated
   *   labels,      the twelve chrome labels of contract §5, translated
   *   stopWords,   words left out of a task's search vocabulary
   *   resolveShow, (show) → Element|null; showLabel, (show, el) → string; warn, (what, err) → void   (optional)
   *   and the additive fields of spec §19.9, all translated (optional):
   *   support,     { phone, tel, hours, open, status, mail } of the contact card, or null
   *   contactWords, words that ask for a person (the panel's contactWords[lang])
   *   more,        [{ kind: 'guide' | 'tour' | 'shortcuts', title }]: the page's registered help (page_help())
   *   disclosure,  { title, lines }: what Saldi AI gets to know
   *   field.ask,   the question "Forklar mere om: <label>" for the field card
   *   labels       may hold the MORE_LABELS as well (spec §19.9, §25)
   *   fields,      true: the chat takes field items in the search (contract v1.1); a v1 chat refuses a whole state with them
   *   fieldTarget, (key) → Element|null: the field on the page that Vis mig of a field item rings (fieldEl() in the shell)
   * }
   */
  function build(input) {
    if (!input || typeof input !== 'object') { return null; }
    const o = input;
    const ctx = o.ctx && typeof o.ctx === 'object' ? o.ctx : null;
    const page = ctx && o.page && typeof o.page === 'object' ? o.page : null;
    const st = o.st && typeof o.st === 'object' ? o.st : { state: 'unknown', flags: {}, data: {} };
    const text = typeof o.text === 'function' ? o.text : function (k) { return str(k); };
    const warn = typeof o.warn === 'function' ? o.warn : function () { /* quiet */ };
    const lang = /^(da|en|no)$/.test(str(o.lang)) ? o.lang : (ctx && /^(da|en|no)$/.test(str(ctx.lang)) ? ctx.lang : 'da');
    const stop = list(o.stopWords).map(function (w) { return str(w).toLowerCase(); });
    const resolve = typeof o.resolveShow === 'function' ? o.resolveShow : function (show) { return defaultResolve(show, ctx, st, page); };
    const labelOf = typeof o.showLabel === 'function' ? o.showLabel : function (show) { return M(show.label); };

    /** Msg = Key or [Key, ...args] (spec §3.2). */
    function M(msg) {
      try { return str(Array.isArray(msg) ? text.apply(null, msg) : text(msg)); } catch (e) { warn('text', e); return ''; }
    }
    function call(name, dflt) {
      const fn = page ? page[name] : null;
      if (typeof fn !== 'function') { return dflt; }
      try {
        const v = fn.call(page, ctx, st);
        return v === null || v === undefined ? dflt : v;
      } catch (e) { warn(name + '()', e); return dflt; }
    }
    function showOf(show) {
      if (!show || typeof show !== 'object') { return { canShow: false, showLabel: '' }; }
      let n = null;
      try { n = resolve(show); } catch (e) { warn('show', e); n = null; }
      if (!n) { return { canShow: false, showLabel: '' }; }
      let label = '';
      try { label = plain(labelOf(show, n)); } catch (e) { warn('showLabel', e); label = ''; }
      return { canShow: true, showLabel: label };
    }

    const tasks = page && page.tasks && typeof page.tasks === 'object' ? page.tasks : {};
    const questions = page && page.questions && typeof page.questions === 'object' ? page.questions : {};
    const shown = {};   // id → showOf(), each target resolved once per build
    function taskShow(id) { if (!own(shown, id)) { shown[id] = showOf(tasks[id].show); } return shown[id]; }
    function helpTask(id) {
      const d = tasks[id];
      const s = taskShow(id);
      return { id: id, title: plain(M(d.title)), why: plain(M(d.why)), steps: list(d.steps).map(M), canShow: s.canShow, showLabel: s.showLabel };
    }

    // Se her først: the task page.problem() names, when it exists (spec §18)
    const pid = page ? str(call('problem', '')) : '';
    const problemId = pid && own(tasks, pid) ? pid : '';

    // Sådan gør du: the first three existing ids of page.order(), never the problem task (D4). Every candidate goes to
    // bound(), which drops one the chat would refuse and keeps the first three after that.
    const order = list(call('order', []));
    const taskIds = [];
    const seen = {};
    if (problemId) { seen[problemId] = true; }
    order.forEach(function (raw) {
      const id = str(raw);
      if (!seen[id] && own(tasks, id)) { seen[id] = true; taskIds.push(id); }
    });

    // Every task and prepared question, in the page's own order for its state first, for the chat's local search
    const search = [];
    const ordered = function (first, all) {
      const out = [];
      list(first).concat(all).forEach(function (raw) { const id = str(raw); if (out.indexOf(id) < 0 && all.indexOf(id) >= 0) { out.push(id); } });
      return out;
    };
    ordered(order, Object.keys(tasks)).forEach(function (id) {
      const t = helpTask(id);
      const d = tasks[id];
      const showText = d.show && d.show.label ? M(d.show.label) : '';
      search.push({ kind: 'task', id: id, title: t.title, intro: '', steps: t.steps, words: vocab([M(d.why), showText], stop), canShow: t.canShow, showLabel: t.showLabel });
    });
    ordered(call('questionOrder', []), Object.keys(questions)).forEach(function (id) {
      const d = questions[id];
      const s = showOf(d.show);
      const words = d.words && Array.isArray(d.words[lang]) ? d.words[lang] : [];
      search.push({
        kind: 'q', id: id, title: plain(M(d.q)), intro: d.intro ? M(d.intro) : '', steps: list(d.steps).map(M),
        words: words.map(function (w) { return str(w).toLowerCase().trim(); }).filter(function (w, i, a) { return w !== '' && a.indexOf(w) === i; }),
        canShow: s.canShow, showLabel: s.showLabel
      });
    });

    // spec §25: the start screen's questions are the state's own top prepared questions, in the search's order for 'q'
    // (questionOrder() first, then the declared order). All of them go to bound(), which keeps the first three whose item
    // it keeps; the fields below never come into it.
    const questionIds = search.filter(function (it) { return it.kind === 'q'; }).map(function (it) { return it.id; });

    // v1.1: the page's fields, as the panel's search found them (label and text), after the tasks and questions as the
    // panel ranks them; the field the user stood in first among them. No steps and no words: label and text are searched.
    const f = o.field && typeof o.field === 'object' ? o.field : null;
    const fieldDefs = page && page.fields && typeof page.fields === 'object' ? page.fields : null;
    if (o.fields === true && fieldDefs) {
      const here = f ? str(f.key) : '';
      const target = typeof o.fieldTarget === 'function' ? o.fieldTarget : null;
      const ids = fieldIds(fieldDefs);
      ids.sort(function (a, b) { return (a.key === here ? 0 : 1) - (b.key === here ? 0 : 1); });   // stable: declared order
      ids.forEach(function (x) {
        const d = fieldDefs[x.key];
        if (!d || typeof d !== 'object') { return; }
        const label = plain(M(d.label));
        const s = target ? showOf({ target: function () { return target(x.key); }, label: d.label, kind: 'field' }) : { canShow: false, showLabel: '' };
        search.push({ kind: 'field', id: x.id, title: label, intro: M(d.text), steps: [], words: [], canShow: s.canShow, showLabel: s.canShow ? (s.showLabel || label) : '' });
      });
    }

    // Tastaturgenveje: the page's keys and codes, then the keys that work everywhere, then the page's note
    const sc = o.shortcuts && typeof o.shortcuts === 'object' ? o.shortcuts : {};
    const titles = sc.titles && typeof sc.titles === 'object' ? sc.titles : {};
    const sections = [];
    const mine = page ? list(page.shortcuts) : [];
    [['keys', 'keys'], ['codes', 'codes']].forEach(function (g) {
      const items = mine.filter(function (s) { return s && (s.group || 'keys') === g[0]; })
        .map(function (s) { return { text: M(s.text), keys: str(s.keys) }; });
      if (items.length) { sections.push({ title: plain(titles[g[1]]), items: items }); }
    });
    const everywhere = list(sc.global).map(function (s) { return { text: str(s && s.text), keys: str(s && s.keys) }; })
      .filter(function (s) { return s.text !== '' && s.keys !== ''; });
    if (everywhere.length) { sections.push({ title: plain(titles.global), items: everywhere }); }
    const note = page && page.shortcutsNote ? M(page.shortcutsNote) : '';

    const tour = Math.max(0, Math.floor(Number(o.tourCount) || 0));
    const lb = o.labels && typeof o.labels === 'object' ? o.labels : {};
    const labels = {};
    LABELS.forEach(function (k) { labels[k] = str(lb[k]); });
    MORE_LABELS.forEach(function (k) { if (own(lb, k)) { labels[k] = str(lb[k]); } });
    const name = page ? plain(M(call('name', ''))) : '';
    const sup = o.support && typeof o.support === 'object' ? o.support : null;
    const dis = o.disclosure && typeof o.disclosure === 'object' ? o.disclosure : null;

    const state = {
      version: VERSION,
      route: ctx ? str(ctx.route) : '',
      lang: lang,
      title: name || plain(o.title),
      hasContent: !!page,
      problem: problemId ? helpTask(problemId) : null,
      field: page && f ? { label: plain(f.label), text: str(f.text), canShow: !!f.canShow, ask: plain(f.ask) } : null,
      about: page ? list(call('about', [])).map(M).filter(function (p) { return p !== ''; }) : [],
      tasks: taskIds.map(helpTask),
      learnOpen: !!o.learnOpen,
      tour: ctx && tour > 0 ? { steps: tour } : null,
      shortcuts: sections.length || note ? { sections: sections, note: note } : null,
      search: search,
      questions: questionIds,   // spec §25, additive: a chat without it ignores it
      labels: labels,
      // spec §19.9: additive, a v1 chat drops them
      support: sup ? { phone: str(sup.phone), tel: str(sup.tel), hours: str(sup.hours), open: !!sup.open, status: str(sup.status), mail: str(sup.mail) } : null,
      contactWords: list(o.contactWords).map(function (w) { return str(w).toLowerCase(); }),
      more: ctx ? list(o.more).filter(function (m) { return m && MORE_KINDS.indexOf(m.kind) >= 0; }).map(function (m) { return { kind: m.kind, title: plain(m.title) }; }) : [],
      disclosure: dis ? { title: plain(dis.title), lines: list(dis.lines).map(str) } : null
    };
    return fit(bound(state, warn), MAX_BYTES, warn);
  }

  /**
   * The state within CAPS, so the chat never refuses it for one string or list (see CAPS). Strings are read as the chat
   * reads them (oneLine()) and clipped; an empty required string, a task without steps, a bad id or an unknown kind
   * drops its entry; lists are cut from their end. Whatever changed is told once through warn('bounds').
   */
  function bound(s, warn) {
    const notes = [];
    const note = function (what) { if (notes.indexOf(what) < 0) { notes.push(what); } };
    function line(v, max, what) {
      const x = oneLine(v);
      if (x.length > max) { note(what + ' clipped to ' + max); return clip(x, max); }
      return x;
    }
    /** A list of required lines: empty ones dropped, at most n. */
    function lines(v, n, max, what) {
      const out = list(v).map(function (x) { return line(x, max, what); }).filter(function (x) { if (x === '') { note('empty ' + what + ' dropped'); } return x !== ''; });
      if (out.length > n) { note(what + ' list cut to ' + n); }
      return out.slice(0, n);
    }
    function task(t, what) {
      const id = t && typeof t.id === 'string' ? t.id : '';
      if (!ID.test(id)) { note(what + ' with a bad id dropped'); return null; }
      const title = line(t.title, CAPS.title, 'title');
      const steps = lines(t.steps, CAPS.taskSteps, CAPS.step, 'step');
      if (!title || !steps.length) { note(what + ' ' + id + ' without a title or steps dropped'); return null; }
      const show = !!t.canShow;
      return { id: id, title: title, why: line(t.why, CAPS.why, 'why'), steps: steps, canShow: show, showLabel: show ? line(t.showLabel, CAPS.label, 'showLabel') : '' };
    }
    function item(it) {
      const id = it && typeof it.id === 'string' ? it.id : '';
      if (!it || (it.kind !== 'task' && it.kind !== 'q' && it.kind !== 'field') || !ID.test(id)) { note('search item with a bad kind or id dropped'); return null; }
      const title = line(it.title, CAPS.title, 'title');
      if (!title) { note('search item ' + id + ' without a title dropped'); return null; }
      const words = list(it.words).map(oneLine).filter(function (w) { if (w.length > CAPS.word) { note('word over ' + CAPS.word + ' dropped'); return false; } return w !== ''; });
      if (words.length > CAPS.words) { note('words cut to ' + CAPS.words); }
      const show = !!it.canShow;
      return {
        kind: it.kind, id: id, title: title, intro: line(it.intro, CAPS.intro, 'intro'), steps: lines(it.steps, CAPS.searchSteps, CAPS.step, 'step'),
        words: words.slice(0, CAPS.words), canShow: show, showLabel: show ? line(it.showLabel, CAPS.label, 'showLabel') : ''
      };
    }
    const kept = function (x) { return x !== null; };

    s.route = line(s.route, CAPS.route, 'route');
    s.title = line(s.title, CAPS.title, 'title');
    s.problem = s.problem ? task(s.problem, 'problem') : null;
    s.tasks = list(s.tasks).map(function (t) { return task(t, 'task'); }).filter(kept).slice(0, CAPS.tasks);   // the top three (D4)
    if (s.field) {
      const label = line(s.field.label, CAPS.label, 'field label');
      if (!label) { note('field without a label dropped'); }
      s.field = label ? { label: label, text: line(s.field.text, CAPS.fieldText, 'field text'), canShow: !!s.field.canShow, ask: line(s.field.ask, CAPS.title, 'field ask') } : null;
    }
    s.about = lines(s.about, CAPS.abouts, CAPS.about, 'paragraph');
    if (s.tour) { s.tour = { steps: Math.min(s.tour.steps, CAPS.tourSteps) }; }
    if (s.shortcuts) {
      const secs = list(s.shortcuts.sections).map(function (sec) {
        const items = list(sec.items).map(function (x) { return { text: line(x.text, CAPS.shortcutText, 'shortcut'), keys: line(x.keys, CAPS.keys, 'keys') }; })
          .filter(function (x) { if (x.text === '') { note('empty shortcut dropped'); } return x.text !== ''; });
        if (items.length > CAPS.sectionItems) { note('shortcuts cut to ' + CAPS.sectionItems); }
        return { title: line(sec.title, CAPS.title, 'section title'), items: items.slice(0, CAPS.sectionItems) };
      }).filter(function (sec) { return sec.items.length > 0; });
      if (secs.length > CAPS.sections) { note('sections cut to ' + CAPS.sections); }
      const nt = line(s.shortcuts.note, CAPS.note, 'note');
      s.shortcuts = secs.length || nt ? { sections: secs.slice(0, CAPS.sections), note: nt } : null;
    }
    const items = list(s.search).map(item).filter(kept);
    // spec §25: questions names q items of the search this state sends, each once, at most three; an id whose item was
    // dropped above (or that names none) is dropped, and the next one fills in
    const qs = [];
    list(s.questions).forEach(function (raw) {
      const id = typeof raw === 'string' ? raw : '';
      if (qs.indexOf(id) >= 0) { return; }
      if (!items.some(function (it) { return it.kind === 'q' && it.id === id; })) { note('question without a search item dropped'); return; }
      qs.push(id);
    });
    s.questions = qs.slice(0, CAPS.questions);
    // the cut to the chat's 120 items keeps the questions' items and takes the others in the page's order
    if (items.length > CAPS.searchItems) {
      note('search cut to ' + CAPS.searchItems);
      let room = CAPS.searchItems - items.filter(function (it) { return named(s, it); }).length;
      s.search = items.filter(function (it) { if (named(s, it)) { return true; } room -= 1; return room >= 0; });
    } else {
      s.search = items;
    }
    Object.keys(s.labels).forEach(function (k) {
      s.labels[k] = line(s.labels[k], CAPS.label, 'label');
      if (s.labels[k] === '') { note('label ' + k + ' is empty'); }   // nothing to put instead: the chat refuses the state
    });
    if (s.support) {
      const u = s.support;
      s.support = { phone: line(u.phone, CAPS.phone, 'phone'), tel: line(u.tel, CAPS.phone, 'tel'), hours: line(u.hours, CAPS.label, 'hours'), open: u.open, status: line(u.status, CAPS.label, 'status'), mail: line(u.mail, CAPS.mail, 'mail') };
    }
    const cw = [];
    s.contactWords.forEach(function (w) { const x = oneLine(w); if (x !== '' && x.length <= CAPS.word && cw.indexOf(x) < 0) { cw.push(x); } });
    s.contactWords = cw.slice(0, CAPS.contactWords);
    s.more = s.more.filter(function (m) { return m.title !== ''; }).map(function (m) { return { kind: m.kind, title: line(m.title, CAPS.label, 'more title') }; }).slice(0, CAPS.more);
    if (s.disclosure) {
      const dl = lines(s.disclosure.lines, CAPS.disclosure, CAPS.disclosureText, 'disclosure line');
      const dt = line(s.disclosure.title, CAPS.label, 'disclosure title');
      s.disclosure = dt && dl.length ? { title: dt, lines: dl } : null;
    }
    if (notes.length && typeof warn === 'function') { warn('bounds', new Error('help state bounds: ' + notes.join('; '))); }
    return s;
  }

  /** A search item that the state's questions name (spec §25). */
  function named(state, it) { return it.kind === 'q' && list(state.questions).indexOf(it.id) >= 0; }

  /**
   * Within the loader's limit: the search list is the bulk, so its last items go first (the page's own order is kept). The
   * items the questions name (spec §25) are never cut; only should they alone break the limit do the last of them go,
   * with their ids.
   */
  function fit(state, max, warn) {
    const total = bytes(JSON.stringify(state));
    if (total <= max) { return state; }
    const items = state.search;
    const sized = function (search) { return bytes(JSON.stringify(Object.assign({}, state, { search: search }))); };
    let keep = items.filter(function (it) { return named(state, it); });
    let size = sized(keep);
    while (size > max && state.questions.length) {
      state.questions = state.questions.slice(0, -1);
      keep = items.filter(function (it) { return named(state, it); });
      size = sized(keep);
    }
    items.some(function (it) {
      if (keep.indexOf(it) >= 0) { return false; }
      const n = bytes(JSON.stringify(it)) + (keep.length ? 1 : 0);
      if (size + n > max) { return true; }
      size += n;
      keep.push(it);
      return false;
    });
    state.search = items.filter(function (it) { return keep.indexOf(it) >= 0; });
    if (typeof warn === 'function') { warn('size', new Error('help state ' + total + ' bytes, search cut to ' + state.search.length + ' of ' + items.length)); }
    return state;
  }

  window.SaldiHelpState = {
    version: VERSION, maxBytes: MAX_BYTES, labels: LABELS.slice(), moreLabels: MORE_LABELS.slice(), moreKinds: MORE_KINDS.slice(),
    caps: Object.assign({}, CAPS), build: build, plain: plain, bytes: bytes, fieldIds: fieldIds, resolve: resolve, allPages: allPages
  };
})();
