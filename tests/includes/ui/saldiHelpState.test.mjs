// --- tests/includes/ui/saldiHelpState.test.mjs --- ver 5.1.0 --- 2026.11.09
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The HelpState builder (javascript/saldi-help-state.js, doc/hjaelp_saldi_ai.md §19): the shape of contract §5, the
// problem card, the top three, the search list and the size limit, on the finance pages with their real texts from
// importfiler/tekster_keyed.tsv. Run on the host: node --test tests/includes/ui/*.test.mjs (not run in CI, §4.4).
//
// 20261108 CL/ASR Created.
// 20261108 CL/ASR Merge review: every state within the chat parser's bounds (B.caps), the bounds kept when content breaks
//                 them, an unreadable frame with route '', and the additive fields of spec §19.9.
// 20261108 CL/ASR Oversigt (spec §20): a new company, the roles card in English and a hidden overview in Norwegian.
// 20261108 CL/ASR Oversigt review: the flag noegletal; hidden gives FS10, FS7, FS9.
// 20261108 CL/ASR The Debitor and Kreditor module files load after saldi-help-finans.js: every page of them within the caps.
// 20261109 CL/ASR The Lager and Indstillinger module files load after saldi-help-kreditor.js; every page and every variant (spec §24,
//                 allPages()) within the caps, and resolve(): a settings section's own page, else the route's page.
// 20261109 CL/ASR Page questions (spec §25): questions on a page with and without its own order and without questions, kept
//                 through the size cut and the cut to 120 items, a bad id dropped; the labels questions, askMore and related.
// 20261109 CL/ASR Oversigt content review (spec §25): a new VAT-registered company's questions are Q1, Q7 (the VAT figure) and Q4.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const root = fileURLToPath(new URL('../../../', import.meta.url))
const FILES = ['javascript/saldi-help-content.js', 'javascript/saldi-help-finans.js', 'javascript/saldi-help-debitor.js', 'javascript/saldi-help-kreditor.js',
  'javascript/saldi-help-lager.js', 'javascript/saldi-help-settings.js', 'javascript/saldi-help-state.js']
const sources = FILES.map((f) => readFileSync(root + f, 'utf8'))
const tsv = new Map(readFileSync(root + 'importfiler/tekster_keyed.tsv', 'utf8').split('\n').filter((l) => l !== '').map((l) => { const f = l.split('\t'); return [f[0], f] }))

function load () {
  const window = {}
  const context = vm.createContext({ window, URL })
  for (const source of sources) vm.runInContext(source, context)
  return window
}
const W = load()
const C = W.SaldiHelpContent
const B = W.SaldiHelpState
const KK = C.pages['finans/kassekladde.php']
const KL = C.pages['finans/kladdeliste.php']
const KB = C.pages['includes/documents.php']

/** Plain data out of the vm realm. */
function data (v) { return JSON.parse(JSON.stringify(v)) }

const COL = { da: 1, en: 2, no: 3 }
/** T() as the shell resolves it: the user's language column, %s filled in order, [key] when the row is missing. */
function textFor (lang) {
  return (key, ...args) => {
    const row = tsv.get(key)
    if (!row) return '[' + key + ']'
    let i = 0
    return String(row[COL[lang]]).replace(/%s/g, () => (i < args.length ? String(args[i++]) : ''))
  }
}

const EL = { nodeType: 1, tagName: 'INPUT', isConnected: true, getClientRects: () => [{ width: 10, height: 10 }] }
/** The shell's resolver: here a set of selectors that exist on the page. Function targets find nothing. */
function resolver (present) { return (show) => (typeof show.target === 'string' && present.has(show.target) ? EL : null) }

const LABELS = {
  helpButton: 'Hjælp til siden', problem: 'Se her først', field: 'Feltet du stod i: %s', learn: 'Lær siden at kende', about: 'Om siden',
  tasks: 'Sådan gør du', showMe: 'Vis mig', showMeFor: 'Vis mig: %s', tour: 'Rundvisning af siden (%s trin)',
  tourDesc: 'Vis mig rundt, ét trin ad gangen', shortcuts: 'Tastaturgenveje', fromHelp: 'Til denne side'
}
const SHORTCUTS = {
  global: [{ text: 'Åbn hjælpen (uden for felter)', keys: '?' }, { text: 'Åbn hjælpen, også i et felt', keys: 'F1' }, { text: 'Søg i Saldi', keys: 'Ctrl+K' }],
  titles: { keys: 'Taster', codes: 'Koder i felterne', global: 'Overalt i Saldi' }
}
function ctxFor (route, lang = 'da') {
  return { doc: { querySelectorAll: () => [], querySelector: () => null }, win: {}, url: new URL('https://x/asr_hj/' + route), route, lang, t: () => '', field: null }
}
function build (page, route, st, o = {}) {
  const lang = o.lang || 'da'
  const warnings = []
  const state = B.build({
    page, ctx: ctxFor(route, lang), st: { flags: {}, data: {}, ...st }, lang, text: textFor(lang), title: o.title || '',
    field: o.field || null, tourCount: o.tourCount || 0, learnOpen: o.learnOpen !== false, shortcuts: SHORTCUTS, labels: LABELS,
    stopWords: C.stopWords[lang], resolveShow: o.resolveShow || resolver(new Set(o.present || [])),
    fields: o.fields, fieldTarget: o.fieldTarget,
    warn: (what, err) => warnings.push(what + ': ' + (err && err.message))
  })
  return { state: data(state), warnings }
}

const HELP_TASK = ['id', 'title', 'why', 'steps', 'canShow', 'showLabel']
const SEARCH_ITEM = ['kind', 'id', 'title', 'intro', 'steps', 'words', 'canShow', 'showLabel']
const STATE_KEYS = ['version', 'route', 'lang', 'title', 'hasContent', 'problem', 'field', 'about', 'tasks', 'learnOpen', 'tour', 'shortcuts', 'search', 'questions',
  'labels', 'support', 'contactWords', 'more', 'disclosure']
// spec §25: the labels of the page's questions, as topbar_help_host() hands them (texts 8825-8827)
const Q_LABELS = { questions: 'Spørgsmål om %s', askMore: 'Spørg Saldi AI om mere', related: 'Flere spørgsmål om siden' }
const CAPS = data(B.caps)
const ID = /^[A-Za-z0-9_]{1,24}$/
/** A string as the chat's parser reads it (support-sally lib/page-help.ts text()): one line, within max, maybe required. */
function chatLine (x, max, what, required = false) {
  assert.equal(typeof x, 'string', what)
  // eslint-disable-next-line no-control-regex
  const clean = x.replace(/[\u0000-\u001f\u007f-\u009f\u2028\u2029]/g, ' ').replace(/\s+/g, ' ').trim()
  assert.ok(clean.length <= max, what + ' over ' + max + ': ' + clean.length)
  if (required) assert.ok(clean !== '', what + ' is empty')
}

/** Every rule of contract §5 that does not depend on the page, and every bound the chat's parser refuses a state for. */
function assertShape (s, msg = '') {
  assertBounds(s, msg)
  assert.deepEqual(Object.keys(s).sort(), [...STATE_KEYS].sort(), msg + ' state keys')
  assert.equal(s.version, 1)
  assert.equal(typeof s.route, 'string')
  assert.ok(['da', 'en', 'no'].includes(s.lang))
  assert.equal(typeof s.title, 'string')
  assert.equal(typeof s.hasContent, 'boolean')
  assert.equal(typeof s.learnOpen, 'boolean')
  const plainLine = (x, what) => { assert.equal(typeof x, 'string', msg + what); assert.ok(!x.includes('**'), msg + what + ' has no **: ' + x) }
  const noKey = (x, what) => assert.ok(!/\[help\./.test(x), msg + what + ' is a text, not a missing key: ' + x)
  const task = (t, what) => {
    assert.deepEqual(Object.keys(t).sort(), [...HELP_TASK].sort(), msg + what)
    plainLine(t.title, what + ' title'); plainLine(t.why, what + ' why'); plainLine(t.showLabel, what + ' showLabel')
    assert.ok(t.title !== '', msg + what + ' has a title')
    assert.ok(Array.isArray(t.steps) && t.steps.length >= 1 && t.steps.length <= 4, msg + what + ' has 1-4 steps')
    t.steps.forEach((x) => { assert.equal(typeof x, 'string'); noKey(x, what + ' step') })
    noKey(t.title, what); noKey(t.why, what)
    assert.equal(typeof t.canShow, 'boolean')
    if (!t.canShow) assert.equal(t.showLabel, '', msg + what + ': no label without Vis mig')
  }
  if (s.problem !== null) task(s.problem, ' problem')
  assert.ok(Array.isArray(s.tasks) && s.tasks.length <= 3, msg + ' at most three tasks')
  s.tasks.forEach((t) => task(t, ' task ' + t.id))
  if (s.problem) assert.ok(!s.tasks.some((t) => t.id === s.problem.id), msg + ' the problem task is not repeated')
  assert.equal(new Set(s.tasks.map((t) => t.id)).size, s.tasks.length, msg + ' unique tasks')
  if (s.field !== null) { assert.deepEqual(Object.keys(s.field).sort(), ['ask', 'canShow', 'label', 'text']); plainLine(s.field.label, ' field label') }
  assert.ok(Array.isArray(s.about) && s.about.length <= 2, msg + ' 0-2 paragraphs: ' + s.about.length)
  s.about.forEach((p) => { assert.equal(typeof p, 'string'); assert.ok(p !== ''); noKey(p, ' about') })
  if (s.tour !== null) { assert.deepEqual(Object.keys(s.tour), ['steps']); assert.ok(Number.isInteger(s.tour.steps) && s.tour.steps > 0) }
  if (s.shortcuts !== null) {
    assert.deepEqual(Object.keys(s.shortcuts).sort(), ['note', 'sections'])
    assert.equal(typeof s.shortcuts.note, 'string')
    s.shortcuts.sections.forEach((sec) => {
      assert.deepEqual(Object.keys(sec).sort(), ['items', 'title'])
      assert.ok(sec.items.length > 0)
      sec.items.forEach((it) => { assert.deepEqual(Object.keys(it).sort(), ['keys', 'text']); assert.ok(it.keys !== '' && it.text !== ''); noKey(it.text, ' shortcut') })
    })
  }
  s.search.forEach((it) => {
    assert.deepEqual(Object.keys(it).sort(), [...SEARCH_ITEM].sort(), msg + ' search item ' + it.id)
    assert.ok(['task', 'q', 'field'].includes(it.kind))
    if (it.kind === 'field') assert.deepEqual([it.steps, it.words], [[], []], msg + ' a field item is its label and text')
    plainLine(it.title, ' search title'); plainLine(it.showLabel, ' search showLabel')
    assert.equal(typeof it.intro, 'string')
    it.words.forEach((w) => assert.equal(w, w.toLowerCase(), msg + ' words are lower case'))
    if (!it.canShow) assert.equal(it.showLabel, '')
  })
  assert.equal(new Set(s.search.map((it) => it.kind + ':' + it.id)).size, s.search.length, msg + ' each search item once')
  // spec §25: the start screen's questions are q items of the search this state sends, each once
  assert.ok(Array.isArray(s.questions), msg + ' questions')
  assert.equal(new Set(s.questions).size, s.questions.length, msg + ' each question once')
  s.questions.forEach((id) => assert.ok(s.search.some((it) => it.kind === 'q' && it.id === id), msg + ' question ' + id + ' is a q item of the search'))
  for (const k of Object.keys(LABELS)) assert.ok(Object.prototype.hasOwnProperty.call(s.labels, k), msg + ' label ' + k)
  assert.ok(Object.keys(s.labels).every((k) => k in LABELS || data(B.moreLabels).includes(k)), msg + ' only known labels')
  Object.values(s.labels).forEach((x) => assert.equal(typeof x, 'string'))
  // spec §19.9, additive
  if (s.support !== null) assert.deepEqual(Object.keys(s.support).sort(), ['hours', 'mail', 'open', 'phone', 'status', 'tel'])
  assert.ok(Array.isArray(s.contactWords))
  s.more.forEach((m) => { assert.deepEqual(Object.keys(m).sort(), ['kind', 'title']); assert.ok(data(B.moreKinds).includes(m.kind)) })
  if (s.disclosure !== null) { assert.deepEqual(Object.keys(s.disclosure).sort(), ['lines', 'title']); assert.ok(s.disclosure.lines.length > 0) }
  assert.ok(B.bytes(JSON.stringify(s)) <= B.maxBytes, msg + ' within the loader limit')
}
/** The caps of the chat's parser (B.caps mirrors them): one breach there refuses the whole state. */
function assertBounds (s, msg = '') {
  const c = CAPS
  chatLine(s.route, c.route, msg + ' route')
  chatLine(s.title, c.title, msg + ' title')
  const task = (t, what) => {
    assert.match(t.id, ID, msg + what + ' id')
    chatLine(t.title, c.title, msg + what + ' title', true)
    chatLine(t.why, c.why, msg + what + ' why')
    assert.ok(t.steps.length >= 1 && t.steps.length <= c.taskSteps, msg + what + ' steps ' + t.steps.length)
    t.steps.forEach((x) => chatLine(x, c.step, msg + what + ' step', true))
    if (t.canShow) chatLine(t.showLabel, c.label, msg + what + ' showLabel')
  }
  if (s.problem) task(s.problem, ' problem')
  assert.ok(s.tasks.length <= c.tasks, msg + ' tasks')
  s.tasks.forEach((t) => task(t, ' task ' + t.id))
  if (s.field) { chatLine(s.field.label, c.label, msg + ' field label', true); chatLine(s.field.text, c.fieldText, msg + ' field text') }
  assert.ok(s.about.length <= c.abouts, msg + ' about')
  s.about.forEach((x) => chatLine(x, c.about, msg + ' about', true))
  if (s.tour) assert.ok(Number.isInteger(s.tour.steps) && s.tour.steps >= 1 && s.tour.steps <= c.tourSteps, msg + ' tour')
  if (s.shortcuts) {
    assert.ok(s.shortcuts.sections.length <= c.sections, msg + ' sections')
    s.shortcuts.sections.forEach((sec) => {
      chatLine(sec.title, c.title, msg + ' section title')
      assert.ok(sec.items.length <= c.sectionItems, msg + ' section items')
      sec.items.forEach((it) => { chatLine(it.text, c.shortcutText, msg + ' shortcut', true); chatLine(it.keys, c.keys, msg + ' keys') })
    })
    chatLine(s.shortcuts.note, c.note, msg + ' note')
  }
  assert.ok(s.search.length <= c.searchItems, msg + ' search items ' + s.search.length)
  assert.ok(s.questions.length <= c.questions && c.questions === 3, msg + ' at most three questions')
  s.questions.forEach((id) => assert.match(id, ID, msg + ' question id'))
  s.search.forEach((it) => {
    const w = msg + ' search ' + it.kind + ':' + it.id
    assert.ok(it.kind === 'task' || it.kind === 'q' || it.kind === 'field', w + ' kind')
    assert.match(it.id, ID, w + ' id')
    chatLine(it.title, c.title, w + ' title', true)
    chatLine(it.intro, c.intro, w + ' intro')
    assert.ok(it.steps.length <= c.searchSteps, w + ' steps')
    it.steps.forEach((x) => chatLine(x, c.step, w + ' step', true))
    assert.ok(it.words.length <= c.words, w + ' words')
    it.words.forEach((x) => chatLine(x, c.word, w + ' word'))
    if (it.canShow) chatLine(it.showLabel, c.label, w + ' showLabel')
  })
  for (const k of Object.keys(LABELS)) chatLine(s.labels[k], c.label, msg + ' label ' + k, true)
}

test('the builder exposes what the shell reads', () => {
  assert.equal(B.version, 1)
  assert.equal(B.maxBytes, 96 * 1024)
  assert.equal(typeof B.build, 'function')
  assert.deepEqual(data(B.labels).sort(), Object.keys(LABELS).sort())
  assert.equal(B.plain(' **Bogfør**  kladden '), 'Bogfør kladden')
  assert.equal(B.bytes('aæ€😀'), 1 + 2 + 3 + 4)
  assert.equal(typeof B.resolve, 'function')
  assert.equal(typeof B.allPages, 'function')
})

test('variants (spec §24): resolve() gives the page a settings section registered, else the route\'s own page', () => {
  const SP = C.pages['systemdata/settingsSection.php']
  const route = 'systemdata/settingsSection.php'
  const at = (q) => ({ ...ctxFor(route + q), route })
  // a page of the test's own for a key no section has (zz_test.*), registered as addSection() does, removed at the end
  const own = { ...KL, group: 'zztest' }
  SP.variants['zz_test.section'] = own
  try {
    const r = B.resolve(SP, at('?s=zz_test.section'))
    assert.ok(r.page === own, 'the section\'s own page')
    assert.equal(r.key, 'zz_test.section')
    const other = B.resolve(SP, at('?s=zz_test.other'))
    assert.ok(other.page === SP, 'a section without a page of its own: the generic page')
    assert.equal(other.key, '')
    assert.ok(B.resolve(SP, at('')).page === SP)
    const listed = B.allPages(C.pages).filter((p) => p.route === route)
    assert.equal(listed[0].key, '', 'allPages(): the route\'s page first')
    assert.ok(listed[0].page === SP)
    assert.ok(listed.some((p) => p.key === 'zz_test.section' && p.page === own), 'allPages(): then its variants')
    assert.equal(listed.length, Object.keys(SP.variants).length + 1)
    // a variant that is not a page, and a variant() that throws: the route's page, the throw is one warning
    SP.variants['zz_test.section'] = { title: 'no group' }
    assert.ok(B.resolve(SP, at('?s=zz_test.section')).page === SP)
    const warned = []
    const boom = { ...KL, variants: { a: own }, variant () { throw new Error('boom') } }
    assert.ok(B.resolve(boom, at(''), (what, e) => warned.push(what + ': ' + e.message)).page === boom)
    assert.deepEqual(warned, ['variant(): boom'])
  } finally {
    delete SP.variants['zz_test.section']
  }
  // a page without variants serves itself; no page, no answer
  assert.ok(B.resolve(KK, ctxFor('finans/kassekladde.php')).page === KK)
  assert.equal(B.resolve(KK, ctxFor('finans/kassekladde.php')).key, '')
  assert.equal(B.resolve(null, ctxFor('x/y.php')), null)
  assert.equal(B.resolve({ name: 'no group' }, ctxFor('x/y.php')), null)
})

test('a frame Saldi cannot read: route \'\', no page help, the keys that work everywhere (contract §5)', () => {
  const s = data(B.build({ page: KK, ctx: null, st: null, lang: 'en', text: textFor('en'), title: 'Ignored', field: { label: 'X', text: 'Y', canShow: true },
    tourCount: 4, learnOpen: true, shortcuts: SHORTCUTS, labels: LABELS, more: [{ kind: 'guide', title: 'Guide' }] }))
  assertShape(s, 'unreadable')
  assert.equal(s.route, '')
  assert.equal(s.lang, 'en')
  assert.equal(s.title, 'Ignored', 'the caller decides the generic title')
  assert.equal(s.hasContent, false)
  assert.equal(s.problem, null)
  assert.equal(s.field, null)
  assert.deepEqual(s.tasks, [])
  assert.deepEqual(s.search, [])
  assert.deepEqual(s.questions, [], 'no page, no questions')
  assert.equal(s.tour, null, 'no page, no tour')
  assert.deepEqual(s.more, [], 'no page, no page rows')
  assert.deepEqual(s.shortcuts.sections.map((x) => x.title), ['Overalt i Saldi'])
  assert.equal(B.build(null), null)
})

test('kassekladde, a refused save: KK1 first with its steps, then the rest of the order', () => {
  const { state: s, warnings } = build(KK, 'finans/kassekladde.php', { state: 'afvist', data: { kladdeId: '12' } }, { present: ['input[name="save"]', '#kkRegnaar'], tourCount: 10 })
  assertShape(s, 'afvist')
  assert.deepEqual(warnings, [])
  assert.equal(s.route, 'finans/kassekladde.php')
  assert.equal(s.hasContent, true)
  assert.equal(s.title, textFor('da')('help.kk.name', '12').replace(/\*\*/g, ''))
  assert.equal(s.problem.id, 'KK1')
  assert.equal(s.problem.title, B.plain(tsv.get('help.kk.task.kk1.title')[1]))
  assert.deepEqual(s.problem.steps, [1, 2, 3].map((n) => tsv.get('help.kk.task.kk1.step' + n)[1]))
  assert.equal(s.problem.canShow, true)
  assert.equal(s.problem.showLabel, B.plain(tsv.get('help.kk.task.kk1.show')[1]))
  // KK.order('afvist') is KK1, KK28, KK26: the problem is not repeated below
  assert.deepEqual(s.tasks.map((t) => t.id), ['KK28', 'KK26'])
  assert.equal(s.tasks[0].canShow, true, '#kkRegnaar is on the page')
  assert.equal(s.tasks[1].canShow, false, 'a function target finds nothing on the stub')
  assert.equal(s.tasks[1].showLabel, '')
  assert.deepEqual(s.tour, { steps: 10 })
  assert.deepEqual(s.about, data(KK.about(ctxFor('finans/kassekladde.php'), { state: 'afvist', flags: {}, data: {} })).map((k) => tsv.get(k)[1]))
  // a line without an account as well: the top three follow
  const m = build(KK, 'finans/kassekladde.php', { state: 'afvist', flags: { manglerKonto: true } }).state
  assert.equal(m.problem.id, 'KK1')
  assert.deepEqual(m.tasks.map((t) => t.id), ['KK2', 'KK28', 'KK26'])
  assert.equal(m.problem.canShow, false, 'no target on the page: no Vis mig')
  assert.equal(m.problem.showLabel, '')
})

test('kladdeliste, empty for a new customer: no problem card, the first day in the top three', () => {
  const { state: s } = build(KL, 'finans/kladdeliste.php', { state: 'tom', flags: { tom: true } }, { present: ['#create-new'] })
  assertShape(s, 'kl tom')
  assert.equal(s.problem, null)
  assert.deepEqual(s.tasks.map((t) => t.id), ['K11', 'K10', 'K13'])
  assert.equal(s.tasks[0].canShow, true)
  assert.equal(s.tasks[0].showLabel, B.plain(tsv.get('help.kl.task.k2.show')[1]), 'K11 points at Ny kladde with K2\'s label')
  // every task and question once, the page's order for its state first
  const taskIds = s.search.filter((it) => it.kind === 'task').map((it) => it.id)
  const qIds = s.search.filter((it) => it.kind === 'q').map((it) => it.id)
  assert.deepEqual([...taskIds].sort(), Object.keys(KL.tasks).sort())
  assert.deepEqual([...qIds].sort(), Object.keys(KL.questions).sort())
  assert.deepEqual(taskIds.slice(0, 4), ['K11', 'K10', 'K13', 'K2'])
  assert.ok(s.search.findIndex((it) => it.kind === 'q') === taskIds.length, 'tasks first, then the questions')
  const qo = data(KL.questionOrder(ctxFor('finans/kladdeliste.php'), { state: 'tom', flags: { tom: true }, data: {} }))
  assert.deepEqual(qIds.slice(0, qo.length), qo)
  // a question carries its own words in the user's language; a task the words of its why line, without stop words
  const q = s.search.find((it) => it.kind === 'q')
  assert.deepEqual(q.words, data(KL.questions[q.id].words.da).map((w) => w.toLowerCase()))
  const k11 = s.search.find((it) => it.id === 'K11')
  assert.equal(k11.intro, '')
  assert.ok(k11.words.length > 0)
  assert.ok(!k11.words.some((w) => C.stopWords.da.includes(w)))
  assert.deepEqual(k11.steps, s.tasks[0].steps)
})

test('kassebilag pool: a refused Gem og næste puts KB13 first', () => {
  const { state: s } = build(KB, 'includes/documents.php', { state: 'pulje', flags: { nyLinje: true, obligatorisk: true } })
  assertShape(s, 'kb pulje')
  assert.equal(s.problem.id, 'KB13')
  assert.ok(!s.tasks.some((t) => t.id === 'KB13'))
  assert.ok(s.tasks.length > 0)
  const plain = build(KB, 'includes/documents.php', { state: 'pulje', flags: { nyLinje: true } }).state
  assert.equal(plain.problem, null)
})

test('English and Norwegian: every string in the user\'s language', () => {
  for (const lang of ['en', 'no']) {
    const { state: s } = build(KK, 'finans/kassekladde.php', { state: 'afvist' }, { lang })
    assertShape(s, lang)
    assert.equal(s.lang, lang)
    assert.equal(s.problem.title, B.plain(tsv.get('help.kk.task.kk1.title')[COL[lang]]))
    const q = s.search.find((it) => it.kind === 'q')
    assert.deepEqual(q.words, data(KK.questions[q.id].words[lang]).map((w) => w.toLowerCase()))
  }
})

test('the field the user stood in, the fold state and the shortcuts', () => {
  const field = { label: '**Debet**', text: 'Kontoen, der får beløbet. **F2** slår op.', canShow: true }
  const { state: s } = build(KK, 'finans/kassekladde.php', { state: 'aaben', flags: { hasSaved: true } }, { field, learnOpen: false })
  assertShape(s, 'field')
  assert.deepEqual(s.field, { label: 'Debet', text: field.text, canShow: true, ask: '' })
  assert.equal(s.learnOpen, false)
  const titles = s.shortcuts.sections.map((sec) => sec.title)
  assert.deepEqual(titles, ['Taster', 'Koder i felterne', 'Overalt i Saldi'])
  assert.deepEqual(s.shortcuts.sections[2].items, SHORTCUTS.global)
  assert.equal(s.shortcuts.note, tsv.get(KK.shortcutsNote)[1])
  assert.equal(s.shortcuts.sections[0].items.length, data(KK.shortcuts).filter((x) => (x.group || 'keys') === 'keys').length)
  assert.equal(s.labels.helpButton, 'Hjælp til siden')
})

test('a page without help content: no page-help sections, the tour and the keys stay', () => {
  const s = data(B.build({
    page: null, ctx: ctxFor('index/dashboard.php'), st: null, lang: 'da', text: textFor('da'), title: 'Oversigt',
    field: { label: 'X', text: 'Y', canShow: true }, tourCount: 4, learnOpen: true, shortcuts: SHORTCUTS, labels: LABELS
  }))
  assertShape(s, 'generic')
  assert.equal(s.hasContent, false)
  assert.equal(s.title, 'Oversigt')
  assert.equal(s.problem, null)
  assert.equal(s.field, null)
  assert.deepEqual(s.about, [])
  assert.deepEqual(s.tasks, [])
  assert.deepEqual(s.search, [])
  assert.deepEqual(s.questions, [])
  assert.deepEqual(s.tour, { steps: 4 })
  assert.deepEqual(s.shortcuts.sections.map((x) => x.title), ['Overalt i Saldi'])
  assert.equal(s.route, 'index/dashboard.php')
})

test('the default resolver finds a visible string target, and a failing target is only a warning', () => {
  const doc = { querySelectorAll: (sel) => (sel === 'input[name="save"]' ? [{ nodeType: 1, getClientRects: () => [] }, EL] : []), querySelector: () => null }
  const warnings = []
  const s = data(B.build({
    page: KK, ctx: { ...ctxFor('finans/kassekladde.php'), doc }, st: { state: 'afvist', flags: {}, data: {} }, lang: 'da', text: textFor('da'),
    shortcuts: SHORTCUTS, labels: LABELS, warn: (w) => warnings.push(w)
  }))
  assert.equal(s.problem.canShow, true)
  const page = { ...KK, problem: () => { throw new Error('boom') } }
  const t = build(page, 'finans/kassekladde.php', { state: 'afvist' })
  assert.equal(t.state.problem, null)
  assert.deepEqual(t.warnings, ['problem(): boom'])
})

test('every page in every state fits the loader limit, with every task and question in the search list', () => {
  const all = { resolveShow: () => EL }   // every Vis mig shown: the largest state
  for (const { route, key, page } of B.allPages(C.pages)) {
    for (const lang of ['da', 'en', 'no']) {
      for (const state of [...page.states, 'unknown']) {
        for (const flags of [{}, Object.fromEntries(page.flags.map((f) => [f, true]))]) {
          const { state: s } = build(page, route, { state, flags, data: { kladdeId: '12', linjer: 3 } }, { ...all, lang })
          const msg = route + (key ? '?' + key : '') + ' ' + lang + ' ' + state + ' ' + JSON.stringify(flags)
          assertShape(s, msg)
          const tq = Object.keys(page.tasks).length + Object.keys(page.questions).length
          assert.equal(s.search.length, tq, msg + ' search is complete')
          // spec §25: the state's own top three, questionOrder() first, then the declared order
          const declared = Object.keys(page.questions)
          const qo = data(page.questionOrder(ctxFor(route, lang), { state, flags, data: { kladdeId: '12', linjer: 3 } })).map(String)
          assert.deepEqual(s.questions, [...new Set([...qo, ...declared])].filter((id) => declared.includes(id)).slice(0, 3), msg + ' questions')
          assert.equal(s.questions.length, Math.min(3, declared.length), msg + ' three questions when the page has them')
          // a v1.1 chat also gets the fields, after every task and question: only fields can fall beyond the chat's 120
          const v11 = build(page, route, { state, flags, data: { kladdeId: '12', linjer: 3 } }, { ...all, lang, fields: true, fieldTarget: () => EL }).state
          assertShape(v11, msg + ' v1.1')
          const nf = Object.keys(page.fields || {}).length
          assert.equal(v11.search.length, Math.min(CAPS.searchItems, tq + nf), msg + ' v1.1 search')
          assert.deepEqual(v11.search.slice(0, tq), s.search, msg + ' the tasks and questions come first, unchanged')
          assert.deepEqual(v11.questions, s.questions, msg + ' the fields never change the questions')
        }
      }
    }
  }
})

test('a state over the limit keeps the page and cuts the search list from its end', () => {
  const long = 'x '.repeat(590)
  const warnings = []
  const s = data(B.build({
    page: KK, ctx: ctxFor('finans/kassekladde.php'), st: { state: 'afvist', flags: {}, data: {} }, lang: 'da', text: () => long,
    shortcuts: SHORTCUTS, labels: LABELS, warn: (w) => warnings.push(w)
  }))
  assert.ok(B.bytes(JSON.stringify(s)) <= B.maxBytes)
  assert.equal(s.problem.id, 'KK1')
  assert.equal(s.tasks.length, 2)
  assert.ok(s.search.length > 0 && s.search.length < Object.keys(KK.tasks).length)
  assert.equal(s.search[0].id, 'KK1', 'the page\'s own order is kept')
  assertBounds(s, 'long')
  assert.deepEqual(warnings, ['bounds', 'size'])
  // spec §25: the questions' items are never cut, though every other question and the last tasks are
  const qo = data(KK.questionOrder(ctxFor('finans/kassekladde.php'), { state: 'afvist', flags: {}, data: {} }))
  assert.deepEqual(s.questions, qo.slice(0, 3))
  assert.deepEqual(s.search.filter((it) => it.kind === 'q').map((it) => it.id), s.questions, 'only the questions\' items are left of the questions')
  assert.equal(s.search.findIndex((it) => it.kind === 'q'), s.search.length - 3, 'after the tasks that fit, in the page\'s order')
})

// ------------------------------------------------------------------ spec §25: the page's questions on the chat's start screen

/** A small page of the test's own: four questions, one task, its own question order per state (none: the declared order). */
function qPage (questionOrder, extra = {}) {
  const q = (id) => ({ q: 'Spørgsmål ' + id + '?', intro: 'Svar ' + id + '.', steps: ['Trin ' + id], words: { da: [id.toLowerCase()] } })
  return {
    group: 'zztest', name: () => 'Testside', about: () => [], order: () => ['T1'], states: ['x', 'y'], flags: [],
    tasks: { T1: { title: 'Opgave', why: '', steps: ['Gør det'] } },
    questions: { A: q('A'), B: q('B'), C: q('C'), D: q('D') }, fields: {}, shortcuts: [],
    ...(questionOrder ? { questionOrder } : {}), ...extra
  }
}
function qBuild (page, st, o = {}) {
  const warnings = []
  const state = data(B.build({
    page, ctx: ctxFor('zz/test.php'), st: { flags: {}, data: {}, ...st }, lang: 'da', text: o.text || ((k) => k), shortcuts: SHORTCUTS,
    labels: { ...LABELS, ...Q_LABELS }, resolveShow: () => null, warn: (w, e) => warnings.push(w + ': ' + e.message)
  }))
  return { state, warnings }
}

test('page questions (spec §25): the state\'s own top three, questionOrder() first, then the declared order', () => {
  // a page of the test's own: its order for state x names D, an unknown id and B; state y has no order of its own
  const page = qPage((ctx, st) => (st.state === 'x' ? ['D', 'nope', 'B'] : []))
  const x = qBuild(page, { state: 'x' })
  assertShape(x.state, 'q x')
  assert.deepEqual(x.warnings, [])
  assert.deepEqual(x.state.questions, ['D', 'B', 'A'], 'its order, then the declared order fills in')
  assert.deepEqual(x.state.search.filter((it) => it.kind === 'q').map((it) => it.id), ['D', 'B', 'A', 'C'], 'the search\'s own order for q')
  assert.deepEqual(qBuild(page, { state: 'y' }).state.questions, ['A', 'B', 'C'])
  assert.deepEqual(x.state.labels.questions, 'Spørgsmål om %s')
  // the answer is the item's intro and steps, already in the search
  const d = x.state.search.find((it) => it.id === 'D')
  assert.deepEqual([d.title, d.intro, d.steps], ['Spørgsmål D?', 'Svar D.', ['Trin D']])
  // the real pages: Kladdeliste empty and with journals, Kassekladde refused and open
  const kl = (state, flags) => build(KL, 'finans/kladdeliste.php', { state, flags }).state.questions
  assert.deepEqual(kl('tom', { tom: true }), ['Q4', 'Q1', 'Q7'])
  assert.deepEqual(kl('liste', {}), ['Q1', 'Q2', 'Q3'])
  const kk = (state) => build(KK, 'finans/kassekladde.php', { state }).state
  assert.deepEqual(kk('afvist').questions, ['Q14', 'Q6', 'Q4'])
  assert.deepEqual(kk('aaben').questions, ['Q4', 'Q3', 'Q11'])
  // in English and Norwegian the same ids, the item's own language
  const en = build(KK, 'finans/kassekladde.php', { state: 'afvist' }, { lang: 'en' }).state
  assert.deepEqual(en.questions, ['Q14', 'Q6', 'Q4'])
  assert.equal(en.search.find((it) => it.kind === 'q' && it.id === 'Q14').title, B.plain(textFor('en')(KK.questions.Q14.q)))
})

test('page questions: a page without questionOrder() takes the declared order, a page without questions gives []', () => {
  const declared = qBuild(qPage(null), { state: 'x' }).state
  assertShape(declared, 'declared')
  assert.deepEqual(declared.questions, ['A', 'B', 'C'])
  // an order that throws is one warning, and the declared order serves
  const boom = qBuild(qPage(() => { throw new Error('boom') }), { state: 'x' })
  assert.deepEqual(boom.state.questions, ['A', 'B', 'C'])
  assert.deepEqual(boom.warnings, ['questionOrder(): boom'])
  // fewer than three: all of them
  assert.deepEqual(qBuild(qPage(null, { questions: { B: { q: 'Kun B?', steps: ['Trin'] } } }), { state: 'x' }).state.questions, ['B'])
  // none: [] (the chat falls back to the module's starter questions)
  const none = qBuild(qPage(() => ['A'], { questions: {} }), { state: 'x' }).state
  assertShape(none, 'none')
  assert.deepEqual(none.questions, [])
  assert.ok(none.search.length === 1 && none.search[0].kind === 'task', 'the task stays in the search')
  const varefoto = C.pages['lager/varefoto.php']
  assert.deepEqual(Object.keys(varefoto.questions), [], 'a real page without prepared questions')
  assert.deepEqual(build(varefoto, 'lager/varefoto.php', { state: varefoto.states[0] }).state.questions, [])
})

test('page questions: the sanitiser drops an id whose item it dropped, and the next question fills in', () => {
  const page = qPage(() => ['bad-id', 'E', 'D', 'C'], {
    questions: {
      'bad-id': { q: 'Et dårligt id?', steps: ['Trin'] },   // a bad id: out of the search, so out of the questions
      E: { q: '   ', steps: ['Trin'] },                    // no title: out of the search, so out of the questions
      D: { q: 'D?', steps: ['Trin D'] },
      C: { q: 'C?', intro: 'Svar C.', steps: [] },
      A: { q: 'A?', steps: ['Trin A'] }
    }
  })
  const { state: s, warnings } = qBuild(page, { state: 'x' })
  assertShape(s, 'sanitiser')
  assert.deepEqual(s.questions, ['D', 'C', 'A'])
  assert.ok(!s.search.some((it) => it.id === 'bad-id' || it.id === 'E'))
  assert.equal(warnings.length, 1, 'one warning: ' + warnings.join(' | '))
  assert.match(warnings[0], /^bounds: .*question without a search item dropped/)
})

test('page questions: the cut to the chat\'s 120 items and the size cut keep the items the questions name', () => {
  // 125 tasks ahead of the questions: the cut to 120 takes the last tasks, never the questions' items
  const tasks = Object.fromEntries(Array.from({ length: 125 }, (_, i) => ['T' + i, { title: 'Opgave ' + i, why: '', steps: ['Gør det'] }]))
  const many = qBuild(qPage(() => ['C', 'A', 'D'], { tasks, order: () => Object.keys(tasks) }), { state: 'x' })
  assertShape(many.state, '120')
  assert.equal(many.state.search.length, CAPS.searchItems)
  assert.deepEqual(many.state.questions, ['C', 'A', 'D'])
  assert.deepEqual(many.state.search.filter((it) => it.kind === 'q').map((it) => it.id), ['C', 'A', 'D'], 'B is cut, the three are kept')
  assert.equal(many.state.search.filter((it) => it.kind === 'task').length, CAPS.searchItems - 3)
  assert.equal(many.state.search[0].id, 'T0', 'the page\'s order is kept')
  // a state whose three questions alone break the limit (every string at its cap in three-byte characters): the last
  // questions go with their ids, and what is sent stays consistent and within the limit
  const wide = '漢'.repeat(1600)
  const big = (id) => ({ q: id, intro: id, steps: [id, id, id, id, id, id], words: { da: Array.from({ length: 40 }, (_, j) => '漢'.repeat(38) + String(j).padStart(2, '0')) } })
  const heavy = qPage(() => ['A', 'B', 'C'], {
    about: () => ['p1', 'p2'], problem: () => 'T0', order: () => ['T1', 'T2', 'T3'],
    tasks: Object.fromEntries(['T0', 'T1', 'T2', 'T3'].map((id) => [id, { title: id, why: id, steps: [id, id, id, id] }])),
    questions: { A: big('A'), B: big('B'), C: big('C'), D: big('D') }
  })
  const h = qBuild(heavy, { state: 'x' }, { text: () => wide })
  assert.ok(B.bytes(JSON.stringify(h.state)) <= B.maxBytes, 'within the loader limit')
  assert.ok(h.state.questions.length >= 1 && h.state.questions.length < 3, 'the last questions went: ' + h.state.questions.join())
  assert.deepEqual(h.state.questions, ['A', 'B', 'C'].slice(0, h.state.questions.length))
  h.state.questions.forEach((id) => assert.ok(h.state.search.some((it) => it.kind === 'q' && it.id === id), id + ' is still in the search'))
  assertBounds(h.state, 'heavy')
  assert.ok(h.warnings.some((w) => /^size: /.test(w)))
})

test('content beyond the chat\'s bounds is clipped, cut or dropped, never refused as a whole', () => {
  const page = {
    group: 'kk',
    name: () => 'N'.repeat(300),
    about: () => ['a', 'b', 'c', '   '],
    order: () => ['T1', 'T2', 'T3', 'T4', 'T5', 'bad-id', 'EMPTY'],
    problem: () => 'T0',
    tasks: {
      T0: { title: 'Problem', why: 'w', steps: ['1', '2', '3', '4', '5'] },
      T1: { title: 'One', why: 'y'.repeat(500), steps: ['s'.repeat(700)] },
      T2: { title: '   ', why: '', steps: ['x'] },
      T3: { title: 'Three', why: '', steps: [] },
      T4: { title: 'Four', why: '', steps: ['\u0007', 'ok'] },
      T5: { title: 'Five', why: '', steps: ['ok'] },
      'bad-id': { title: 'Bad', why: '', steps: ['ok'] },
      EMPTY: { title: 'Empty', why: '', steps: [''] }
    },
    questions: Object.fromEntries(Array.from({ length: 130 }, (_, i) => ['Q' + i, {
      q: 'Q' + i, steps: ['1', '2', '3', '4', '5', '6', '7'], words: { da: ['w'.repeat(41), ...Array.from({ length: 45 }, (_, j) => 'v' + j)] }
    }])),
    shortcuts: [{ group: 'keys', text: 't'.repeat(400), keys: 'k'.repeat(70) }, { group: 'keys', text: '  ', keys: 'x' }]
  }
  const warnings = []
  const s = data(B.build({
    page, ctx: ctxFor('finans/kassekladde.php'), st: { state: 'x', flags: {}, data: {} }, lang: 'da', text: (k) => k,
    field: { label: '  ', text: 'text without a label', canShow: true }, tourCount: 150, learnOpen: true, shortcuts: SHORTCUTS,
    labels: LABELS, warn: (w, e) => warnings.push(w + ': ' + e.message)
  }))
  assertShape(s, 'bounds')
  assert.equal(s.title.length, CAPS.title)
  assert.ok(s.title.endsWith('\u2026'))
  assert.deepEqual(s.problem.steps, ['1', '2', '3', '4'], 'a fifth step is cut')
  assert.deepEqual(s.about, ['a', 'b'], 'at most two paragraphs')
  // T2 has no title, T3 no steps: dropped, and the next ones of the order fill the top three; T4 keeps its readable step
  assert.deepEqual(s.tasks.map((t) => t.id), ['T1', 'T4', 'T5'])
  assert.equal(s.tasks[0].why.length, CAPS.why)
  assert.equal(s.tasks[0].steps[0].length, CAPS.step)
  assert.deepEqual(s.tasks[1].steps, ['ok'])
  assert.equal(s.field, null, 'a field card without a label')
  assert.deepEqual(s.tour, { steps: CAPS.tourSteps })
  assert.equal(s.search.length, CAPS.searchItems)
  assert.ok(!s.search.some((it) => it.id === 'bad-id' || it.id === 'T2'), 'a bad id or no title: out of the search too')
  assert.deepEqual(s.search.find((it) => it.id === 'EMPTY').steps, [], 'a search item may have no steps')
  const q = s.search.find((it) => it.kind === 'q')
  assert.equal(q.steps.length, CAPS.searchSteps)
  assert.equal(q.words.length, CAPS.words)
  assert.ok(!q.words.includes('w'.repeat(41)))
  assert.equal(s.shortcuts.sections[0].items.length, 1)
  assert.equal(s.shortcuts.sections[0].items[0].keys.length, CAPS.keys)
  assert.equal(warnings.length, 1, 'one warning: ' + warnings.join(' | '))
  assert.match(warnings[0], /^bounds: /)
})

test('the additive fields of spec §19.9: support, contact words, the page\'s own rows, the disclosure, the field question', () => {
  const field = { label: '**Debet**', text: 'Kontoen.', canShow: true, ask: 'Forklar mere om: Debet' }
  const labels = { ...LABELS, back: 'Tilbage til samtalen', support: 'Kontakt support', call: 'Ring %s', write: 'Skriv til os', askField: 'Spørg Saldi AI om feltet', askThis: 'Spørg Saldi AI om dette', ...Q_LABELS }
  const s = data(B.build({
    page: KK, ctx: ctxFor('finans/kassekladde.php'), st: { state: 'aaben', flags: {}, data: {} }, lang: 'da', text: textFor('da'), field,
    shortcuts: SHORTCUTS, labels, stopWords: C.stopWords.da,
    support: { phone: '46 90 22 08', tel: '+4546902208', hours: 'hverdage 9–16', open: true, status: 'Åbent nu, til kl. 16', mail: 'support@saldi.dk' },
    contactWords: ['Ring', 'support', 'ring', ''],
    more: [{ kind: 'guide', title: '**Vejledning**' }, { kind: 'navigate', title: 'x' }, { kind: 'shortcuts', title: '' }],
    disclosure: { title: 'Hvad får Saldi AI at vide?', lines: ['Spørgsmålet.', ''] }
  }))
  assertShape(s, 'extras')
  assert.equal(s.field.ask, 'Forklar mere om: Debet')
  assert.deepEqual(Object.keys(s.labels).sort(), [...Object.keys(LABELS), ...data(B.moreLabels)].sort())
  assert.deepEqual([s.labels.questions, s.labels.askMore, s.labels.related], Object.values(Q_LABELS), 'the %s is the chat\'s to fill')
  assert.deepEqual(s.support, { phone: '46 90 22 08', tel: '+4546902208', hours: 'hverdage 9–16', open: true, status: 'Åbent nu, til kl. 16', mail: 'support@saldi.dk' })
  assert.deepEqual(s.contactWords, ['ring', 'support'])
  assert.deepEqual(s.more, [{ kind: 'guide', title: 'Vejledning' }], 'known kinds with a title only')
  assert.deepEqual(s.disclosure, { title: 'Hvad får Saldi AI at vide?', lines: ['Spørgsmålet.'] })
  // without them: null and empty lists, so a v1 chat sees nothing new
  const plain = build(KK, 'finans/kassekladde.php', { state: 'aaben' }).state
  assert.equal(plain.support, null)
  assert.deepEqual(plain.contactWords, [])
  assert.deepEqual(plain.more, [])
  assert.equal(plain.disclosure, null)
  assert.ok(!['questions', 'askMore', 'related'].some((k) => k in plain.labels), 'a label not handed is left out: the chat uses its own words')
})

test('v1.1: the page\'s fields go into the search with ids the shell maps back, the field the user stood in first', () => {
  assert.deepEqual(data(B.fieldIds({ 'search:kladdenote': {}, debe: {}, 'a-b': {}, a_b: {}, ['x'.repeat(30)]: {} })), [
    { id: 'search_kladdenote', key: 'search:kladdenote' }, { id: 'debe', key: 'debe' }, { id: 'a_b', key: 'a-b' }, { id: 'f3', key: 'a_b' },
    { id: 'x'.repeat(24), key: 'x'.repeat(30) }
  ])
  assert.deepEqual(data(B.fieldIds(null)), [])
  const T = textFor('da')
  const rung = []
  const target = (key) => { rung.push(key); return key === 'search:kladdenote' ? EL : null }
  const field = { key: 'search:kladdenote', label: T(KL.fields['search:kladdenote'].label), text: T(KL.fields['search:kladdenote'].text), canShow: true }
  // the shell's resolveShow() calls a function target, as for a task's own
  const resolveShow = (show) => (typeof show.target === 'function' ? show.target() : null)
  const { state: s } = build(KL, 'finans/kladdeliste.php', { state: 'tom' }, { fields: true, fieldTarget: target, field, resolveShow })
  assertShape(s, 'fields')
  const items = s.search.filter((it) => it.kind === 'field')
  assert.equal(items.length, Object.keys(KL.fields).length)
  assert.equal(items[0].id, 'search_kladdenote', 'the field the user stood in leads the fields')
  assert.equal(s.search.findIndex((it) => it.kind === 'field'), Object.keys(KL.tasks).length + Object.keys(KL.questions).length, 'after the tasks and questions')
  assert.deepEqual(items.map((it) => it.id).sort(), data(B.fieldIds(KL.fields)).map((x) => x.id).sort())
  assert.equal(items[0].title, B.plain(field.label))
  assert.equal(items[0].intro, field.text)
  assert.deepEqual([items[0].canShow, items[0].showLabel], [true, B.plain(field.label)])
  assert.ok(items.slice(1).every((it) => it.canShow === false && it.showLabel === ''), 'only a field on the page has Vis mig')
  assert.deepEqual(rung.sort(), Object.keys(KL.fields).sort(), 'each field resolved once')
  // a v1 chat refuses a whole state with them: without fields: true there are none
  assert.ok(!build(KL, 'finans/kladdeliste.php', { state: 'tom' }, { fieldTarget: target, field }).state.search.some((it) => it.kind === 'field'))
})

test('oversigt, the front page: a new company, the roles card in English, and a hidden overview', () => {
  const FSP = C.pages['index/dashboard.php']
  const flags = { noegletal: true, ingenOmsaetning: true, ingenUbetalte: true, moms: true }
  const { state: s, warnings } = build(FSP, 'index/dashboard.php', { state: 'tom', flags }, { present: ['.db-kpis'] })
  assertShape(s, 'fs tom')
  assert.deepEqual(warnings, [])
  assert.equal(s.route, 'index/dashboard.php')
  assert.equal(s.hasContent, true)
  assert.equal(s.title, 'Oversigt')
  assert.equal(s.problem, null, 'nothing on the front page stops the work')
  assert.deepEqual(s.tasks.map((t) => t.id), ['FS2', 'FS1', 'FS7'])
  assert.equal(s.tasks[0].canShow, false, 'Debitor and Finans are in the shell: no Vis mig')
  assert.equal(s.tasks[1].canShow, true, 'the key figures are on the page')
  assert.equal(s.tasks[1].showLabel, 'Nøgletal')
  assert.deepEqual(s.about, data(FSP.about(ctxFor('index/dashboard.php'), { state: 'tom', flags, data: {} })).map((k) => tsv.get(k)[1]))
  assert.equal(s.search.length, Object.keys(FSP.tasks).length + Object.keys(FSP.questions).length)
  assert.deepEqual(s.search.filter((it) => it.kind === 'q').slice(0, 3).map((it) => it.id), ['Q1', 'Q7', 'Q4'])
  assert.deepEqual(s.questions, ['Q1', 'Q7', 'Q4'], 'the chat\'s start screen: why 0 kr, the VAT figure, where the messages are')
  assert.deepEqual(s.shortcuts.sections.map((x) => x.title), ['Taster', 'Overalt i Saldi'])
  assert.equal(s.shortcuts.sections[0].items.length, 4)
  // an administrator with the roles card and overdue invoices, in English
  const en = build(FSP, 'index/dashboard.php', { state: 'oversigt', flags: { roller: true, noegletal: true, ubetalte: true, forfaldne: true } },
    { lang: 'en', present: ['.db-notice a[href*="usersRoles.php"]'] }).state
  assertShape(en, 'fs en')
  assert.equal(en.title, 'Dashboard')
  assert.deepEqual(en.tasks.map((t) => t.id), ['FS11', 'FS3', 'FS1'])
  assert.equal(en.tasks[0].title, tsv.get('help.fs.task.fs11.title')[2])
  assert.equal(en.tasks[0].showLabel, 'Review')
  assert.equal(en.tasks[1].canShow, false, 'a function target finds nothing on the stub')
  // hidden by the user, in Norwegian
  const no = build(FSP, 'index/dashboard.php', { state: 'skjult' }, { lang: 'no', present: ['#showDash'] }).state
  assertShape(no, 'fs no')
  assert.deepEqual(no.tasks.map((t) => t.id), ['FS10', 'FS7', 'FS9'])
  assert.equal(no.tasks[0].showLabel, 'Vis oversikt')
  assert.deepEqual(no.about, [tsv.get('help.fs.about.skjult')[3]])
})

test('the builder only reads: no DOM writes, focus, clicks, network, timers or storage', () => {
  const src = readFileSync(root + 'javascript/saldi-help-state.js', 'utf8').replace(/^\s*\/\/.*$/gm, '').replace(/\/\*\*[\s\S]*?\*\//g, '')
  for (const bad of ['innerHTML', 'outerHTML', 'insertAdjacent', 'appendChild', 'setAttribute', 'createElement', '.focus(', '.click(', 'fetch(', 'XMLHttpRequest',
    'setTimeout', 'setInterval', 'requestAnimationFrame', 'localStorage', 'sessionStorage', 'dispatchEvent', 'postMessage', 'document.']) {
    assert.ok(!src.includes(bad), 'saldi-help-state.js uses ' + bad)
  }
})
