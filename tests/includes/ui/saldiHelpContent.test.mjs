// --- tests/includes/ui/saldiHelpContent.test.mjs --- ver 5.1.0 --- 2026.11.24
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The help panel's page content (javascript/saldi-help-content.js and the module files, doc/hjaelp_saldi_ai.md §4.4):
// schema, the help.* text keys against importfiler/tekster_keyed.tsv, and the state detectors on a small fake DOM.
// Run on the host: node --test tests/includes/ui/*.test.mjs (not run in CI, §4.4).
//
// 20261107 CL/ASR Created.
// 20261108 CL/ASR Every page of javascript/saldi-help-finans.js: the generic checks walk page.states and page.flags.
// 20261108 CL/ASR Round 2: the new states and flags of Kladdeliste and Kassekladde, and the Kassebilag page.
// 20261108 CL/ASR problem() names an existing task, and the kassekladde, Bogfør and Kassebilag problem states.
// 20261108 CL/ASR Oversigt (index/dashboard.php, spec §20): its states, flags, orders and show targets on a fake front page.
// 20261108 CL/ASR Oversigt review: last year's revenue in the charts is not a new company; the flag noegletal; hidden gives FS10, FS7, FS9.
// 20261108 ASR Vis mig audit (spec §21): the fixed targets ring the element their task is about.
// 20261108 CL/ASR The Debitor and Kreditor module files load after saldi-help-finans.js, so the generic checks walk their pages.
// 20261108 CL/ASR The Debitor and Kreditor pages (spec §22): states, flags, orders, problem() and targets on fake pages (D1-D3, K1-K2 blocks).
// 20261108 CL/ASR Debitor review (spec §22.8): the payment question on an invoice, Lav en ordre on the customer card, the reminder's mail problem card.
// 20261108 CL/ASR Kreditor review: the purchase order's Vis mig targets ring nothing in the states where the control means something else.
// 20261108 CL/ASR Live check on ssl12 (spec §22.9): a new order, status 0 before Fakturér, rewritten row links, hidden O column, card and purchase-order targets by state.
// 20261109 CL/ASR The Lager and Indstillinger module files load after saldi-help-kreditor.js; the generic checks also walk every variant of a
//                 page (spec §24: one page object per settings section), and the settings sections' dispatcher and generic page.
// 20261109 CL/ASR Routes may lie in a sub-directory (lager/lister/vareliste.php, lager/productCardIncludes/stockLog.php) and carry
//                 percent-encoding (lager/lister/indk%C3%B8b.php).
// 20261109 CL/ASR The Lager and Indstillinger pages (spec §24.5): states, flags, orders, problem() and targets on fake pages
//                 (L1-L3 and S1-S3 blocks).
// 20261109 CL/ASR Lager review (spec §24.13): the item card's fresh-card order and VK40, Lagerstatus' LS10.
// 20261109 CL/ASR Indstillinger review (spec §24.14): Lagerstyring's new order, the restore's RS4 rings the warning.
// 20261109 CL/ASR Live check on ssl12 (spec §24.15): the Indkøb route is indk%C3%B8b.php; the card with FIFO (VK41), VK8/VK39/VK38 on a
//                 bill of materials, VL7 in Filtre, VL19 on Vareliste, VM3 on a line being changed, Flyt vare with one warehouse;
//                 Regnskabsår with one year, Lagerstyring with FIFO on, a checkbox cell's visible box, Formularer, Tekster, Lagre.
// 20261109 CL/ASR Finance and Oversigt content review (spec §25): the questions on the chat's start screen per state, Kontokort Q4 and Q5.
// 20261109 CL/ASR Debitor and Kreditor page questions (spec §25): the question order per state in the D1-D3 and K1-K2 blocks.
// 20261109 CL/ASR Lager page questions (spec §25): the question order per state in the L1-L3 blocks and the new questions' steps.
// 20261112 CL/ASR The CRM replaced the customer history (spec §22.10): no Historik view or history pages, DB18 and DK20 lead to the
//                 CRM, and the D2 CRM block covers debitor/crm.php (tabs, the drawer, read-only access, targets, field keys).
// 20261112 CL/ASR CRM review: the D2 CRM block reads the live state and covers the dialogs (flag dialog, #nNote, #nchips);
//                 E-mail has no ML3 and the customer mail fields' new keys.
// 20261113 CL/ASR D2 CRM: ownWords keeps "mine" searchable on the CRM only; CR2, Q3 and the Sager text fit a company without
//                 employees (no til) and an empty board.
// 20261114 CL/ASR Brugere og roller (systemdata/usersRoles.php): states, flags, orders, questions, targets and field keys on fake pages
//                 in master's markup and the roles branch's.
// 20261115 CL/ASR Integration: master's MB-62 gives the read-only item card no Gem, Kopier or Leverandøropslag, and the
//                 read-only Flyt vare page its heading and Beh. but no form.
// 20261124 CL/ASR Fictive fixture names and numbers; no change to what the test checks.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const root = fileURLToPath(new URL('../../../', import.meta.url))
const sources = ['javascript/saldi-help-content.js', 'javascript/saldi-help-finans.js', 'javascript/saldi-help-debitor.js', 'javascript/saldi-help-kreditor.js',
  'javascript/saldi-help-lager.js', 'javascript/saldi-help-settings.js'].map((f) => readFileSync(root + f, 'utf8'))
const tsvRows = readFileSync(root + 'importfiler/tekster_keyed.tsv', 'utf8').split('\n').filter((l) => l !== '').map((l) => l.split('\t'))
const tsv = new Map(tsvRows.map((f) => [f[0], f]))

/** deepEqual across realms: arrays made inside the vm context have another Array prototype. */
function eq (actual, expected, message) {
  assert.deepEqual(JSON.parse(JSON.stringify(actual)), expected, message)
}

function load () {
  const window = {}
  const context = vm.createContext({ window, URL })
  for (const source of sources) vm.runInContext(source, context)
  return window.SaldiHelpContent
}
const C = load()
const KL = C.pages['finans/kladdeliste.php']
const KK = C.pages['finans/kassekladde.php']

// ------------------------------------------------------------------ a small fake DOM
// Enough of Element/Document for the detectors and show targets: querySelector(All) with tag, #id, .class,
// [attr], [attr="v"], [attr^="v"], [attr*="v"], [attr$="v"], :not(...), descendant and child combinators and comma lists.

function splitTop (s, isSep) {
  const out = []
  let cur = ''
  let depth = 0
  let quote = null
  for (const ch of s) {
    if (quote) { cur += ch; if (ch === quote) quote = null; continue }
    if (ch === '"' || ch === "'") { quote = ch; cur += ch; continue }
    if (ch === '[' || ch === '(') depth++
    if (ch === ']' || ch === ')') depth--
    if (depth === 0 && isSep(ch)) { out.push(cur); out.push(ch); cur = ''; continue }
    cur += ch
  }
  out.push(cur)
  return out
}

function parseCompound (s) {
  const c = { tag: null, id: null, classes: [], attrs: [], nots: [] }
  let rest = s.trim()
  let m
  while (rest) {
    if ((m = /^[a-zA-Z][\w-]*|^\*/.exec(rest))) c.tag = m[0] === '*' ? null : m[0].toUpperCase()
    else if ((m = /^#([\w-]+)/.exec(rest))) c.id = m[1]
    else if ((m = /^\.([\w-]+)/.exec(rest))) c.classes.push(m[1])
    else if ((m = /^\[\s*([\w-]+)\s*(?:([\^*$]?=)\s*"([^"]*)")?\s*\]/.exec(rest))) c.attrs.push({ name: m[1], op: m[2] || null, value: m[3] })
    else if ((m = /^:not\(([^()]*)\)/.exec(rest))) c.nots.push(parseCompound(m[1]))
    else throw new Error('fake DOM: unsupported selector part "' + rest + '"')
    rest = rest.slice(m[0].length)
  }
  return c
}

function parseSelector (sel) {
  return splitTop(sel, (ch) => ch === ',').filter((x) => x !== ',').map((part) => {
    const tokens = splitTop(part.trim().replace(/\s*>\s*/g, '>'), (ch) => ch === ' ' || ch === '>')
    const seq = [] // [{ compound, combinator to the left }]
    let comb = null
    for (const t of tokens) {
      if (t === ' ' || t === '>') { comb = t; continue }
      if (t === '') continue
      seq.push({ c: parseCompound(t), comb })
      comb = null
    }
    return seq
  })
}

function matchCompound (el, c) {
  if (!el || el.nodeType !== 1) return false
  if (c.tag && el.tagName !== c.tag) return false
  if (c.id && el.getAttribute('id') !== c.id) return false
  const cls = (el.getAttribute('class') || '').split(/\s+/)
  if (c.classes.some((x) => !cls.includes(x))) return false
  for (const a of c.attrs) {
    const v = el.getAttribute(a.name)
    if (v === null) return false
    if (a.op === '=' && v !== a.value) return false
    if (a.op === '^=' && !v.startsWith(a.value)) return false
    if (a.op === '*=' && !v.includes(a.value)) return false
    if (a.op === '$=' && !v.endsWith(a.value)) return false
  }
  return !c.nots.some((n) => matchCompound(el, n))
}

function matchSeq (el, seq, i = seq.length - 1) {
  if (!matchCompound(el, seq[i].c)) return false
  if (i === 0) return true
  if (seq[i].comb === '>') return matchSeq(el.parent, seq, i - 1)
  for (let p = el.parent; p; p = p.parent) if (matchSeq(p, seq, i - 1)) return true
  return false
}

class Node {
  constructor () { this.children = []; this.parent = null }
  append (...kids) { for (const k of kids) { k.parent = this; this.children.push(k) } return this }
  * descendants () { for (const k of this.children) { yield k; yield * k.descendants() } }
  querySelectorAll (sel) {
    const seqs = parseSelector(sel)
    return [...this.descendants()].filter((el) => seqs.some((seq) => matchSeq(el, seq)))
  }
  querySelector (sel) { return this.querySelectorAll(sel)[0] || null }
}

class El extends Node {
  constructor (tag, attrs = {}, extra = {}) {
    super()
    this.nodeType = 1
    this.tagName = tag.toUpperCase()
    this.attrs = { ...attrs }
    this.value = attrs.value !== undefined ? String(attrs.value) : ''
    this.textContent = extra.text || ''
    this.color = extra.color || ''
  }

  getAttribute (n) { return n in this.attrs ? String(this.attrs[n]) : null }
  get name () { return this.getAttribute('name') || '' }
  closest (sel) {
    const seqs = parseSelector(sel)
    for (let e = this; e && e.nodeType === 1; e = e.parent) if (seqs.some((seq) => matchSeq(e, seq))) return e
    return null
  }
}

class Doc extends Node {
  getElementById (id) { return this.querySelector('#' + id) }
}

/** form.elements as the browser gives it: namedItem() returns the element, or a list for a repeated name. */
function withElements (form) {
  form.elements = {
    namedItem (name) {
      const all = [...form.descendants()].filter((e) => e.getAttribute('name') === name)
      if (all.length === 0) return null
      if (all.length === 1) return all[0]
      const list = { length: all.length }
      all.forEach((e, i) => { list[i] = e })
      return list
    }
  }
  return form
}

const T = { 636: 'Vis alle', 641: 'Vis egne' }
function ctxFor (doc, url, extra = {}) {
  return {
    doc,
    win: { getComputedStyle: (el) => ({ color: el.color || '' }), docChange: !!extra.docChange },
    url: new URL(url),
    route: new URL(url).pathname.replace(/^\/asr_hj\//, ''),
    lang: 'da',
    t: (id) => T[id] || '[' + id + ']',
    field: extra.field || null
  }
}

// ------------------------------------------------------------------ fixtures

/** A kassekladde page. lines: [{ n, id, bila, debe, kred, besk, fakt, belo, bilaTitle }] */
function kkPage ({ kladdeId = '12', lines = [], footer = 'open', balance = null, kontrolkonto = '', reuse = false, clip = false } = {}) {
  const doc = new Doc()
  const body = new El('body')
  doc.append(body)
  const form = withElements(new El('form', { id: 'kassekladde', name: 'kassekladde' }))
  body.append(form)
  form.append(new El('input', { type: 'hidden', name: 'kladde_id', value: kladdeId }))
  const status = new El('span', { id: 'kk-balance-status' })
  if (balance) status.append(new El('span').append(new El('span', {}, { color: balance === 'diff' ? 'rgb(204, 0, 0)' : 'rgb(26, 122, 26)' })))
  form.append(status)
  form.append(new El('input', { type: 'text', name: 'ny_kladdenote', value: '' }))
  form.append(new El('input', { type: 'text', name: 'kontrolkonto', value: kontrolkonto }))
  const tbody = new El('tbody')
  form.append(new El('table').append(tbody))
  for (const l of lines) {
    const tr = new El('tr')
    if (clip && l.id) tr.append(new El('td', { class: 'clip-cell clip-no-doc' }))
    const bilaAttrs = { type: 'text', name: 'bila' + l.n, value: l.bila || '1' }
    if (l.bilaTitle) bilaAttrs.title = l.bilaTitle
    tr.append(new El('input', bilaAttrs))
    tr.append(new El('input', { type: 'text', name: 'besk' + l.n, value: l.besk || '' }))
    tr.append(new El('input', { type: 'text', name: 'debe' + l.n, value: l.debe || '' }))
    tr.append(new El('input', { type: 'text', name: 'kred' + l.n, value: l.kred || '' }))
    const fakt = new El('td').append(new El('input', { type: 'text', name: 'fakt' + l.n, value: l.fakt || '' }))
    if (reuse && l.id) fakt.append(new El('span', { class: 'invoice-reuse-flag' }))
    tr.append(fakt)
    tr.append(new El('input', { type: 'text', name: 'belo' + l.n, value: l.belo || '' }))
    tr.append(new El('input', { type: 'hidden', name: 'id[' + l.n + ']', value: l.id || '' }))
    tbody.append(tr)
  }
  // The second kladde_id (kassekladde.php:4177) makes namedItem('kladde_id') a list.
  form.append(new El('input', { type: 'hidden', name: 'kladde_id', value: kladdeId }))
  const foot = {
    open: ['bilagsmatch', 'save', 'lookup', 'simulate', 'doPost', 'import', 'offset'],
    refused: ['bilagsmatch', 'save', 'lookup'],
    simulated: ['cancelSimulation'],
    interrupted: ['revert'],
    posted: []
  }[footer]
  for (const name of foot) form.append(new El('input', { type: name === 'bilagsmatch' ? 'button' : 'submit', name }))
  if (footer === 'posted') form.append(new El('input', { type: 'submit', name: 'copy2new', id: 'kopier-button' }))
  if (footer === 'posted' || footer === 'interrupted') {
    body.append(new El('a', { href: '../finans/kassekladde.php?kladde_id=' + kladdeId + '&ompost=501', id: 'undo' }))
    body.append(new El('a', { href: '../finans/kassekladde.php?kladde_id=' + kladdeId + '&ompost=502', id: 'undo' }))
  }
  return doc
}

function copyDialog () {
  const doc = new Doc()
  const form = new El('form', { name: 'Form1' })
  doc.append(new El('body').append(form))
  form.append(new El('input', { name: 'bilagsnr', value: '1001' }), new El('input', { name: 'ny_dato', value: '07-11-26' }),
    new El('input', { type: 'checkbox', name: 'vend_fortegn' }), new El('input', { type: 'hidden', name: 'kladde_id', value: '12' }))
  return doc
}

function chooseReversal () {
  const doc = new Doc()
  const table = new El('table')
  doc.append(new El('body').append(table))
  table.append(new El('a', { href: 'kassekladde.php?kladde_id=12' }, { text: 'Fortryd' }))
  table.append(new El('a', { href: 'kassekladde.php?kladde_id=12&ompost=501&ompost_til=14' }, { text: '14' }))
  return doc
}

/** A kladdeliste page. rows: list of data-bogfort values; opts: locked row index, deletable, search text, toggle text. */
function klPage (rows = [], { locked = -1, deletable = false, search = '', toggle = null } = {}) {
  const doc = new Doc()
  const wrapper = new El('div', { id: 'datatable-wrapper-kladdelst', class: 'datatable-wrapper' })
  doc.append(new El('body').append(wrapper))
  wrapper.append(new El('button', { type: 'button', class: 'dropdown-trigger', 'aria-label': 'Handlinger' }))
  const table = new El('table', { id: 'datatable-kladdelst' })
  wrapper.append(table)
  const thead = new El('thead')
  for (const f of ['id', 'kladdedate', 'oprettet_af', 'kladdenote', 'bogfort', 'bogfort_af']) {
    thead.append(new El('input', { type: 'text', name: 'search[kladdelst][' + f + ']', value: f === 'kladdenote' ? search : '' }))
  }
  table.append(thead)
  const tbody = new El('tbody')
  table.append(tbody)
  rows.forEach((b, i) => {
    const td = new El('td', { 'data-bogfort': b })
    td.append(new El('a', i === locked ? { href: 'kassekladde.php?tjek=' + i, title: 'Kladde er låst af ole' } : { href: 'kassekladde.php?tjek=' + i }))
    const tr = new El('tr').append(td)
    if (deletable && b === '-') tr.append(new El('td').append(new El('button', { type: 'button', onclick: 'event.preventDefault(); deleteKladde(' + i + ');' })))
    tbody.append(tr)
  })
  if (toggle !== null) wrapper.append(new El('button', { type: 'button', id: 'filter-toggle-btn' }, { text: toggle }))
  return doc
}

const KK_URL = 'https://ssl12.saldi.dk/asr_hj/finans/kassekladde.php?kladde_id=12&tjek=12'
const KL_URL = 'https://ssl12.saldi.dk/asr_hj/finans/kladdeliste.php'

// ------------------------------------------------------------------ every state and flag combination the content reacts to

const KK_STATES = ['ny', 'aaben', 'afvist', 'simuleret', 'bogfoert', 'afbrudt', 'kopier', 'vaelgTilbagefoersel', 'opslag', 'udskrift', 'unknown']
const KK_FLAGS = ['hasSaved', 'difference', 'balancerer', 'ikkeGemt', 'manglerKonto', 'enSide', 'dublet', 'fakturaBrugt', 'kontrolkonto']
const KL_FLAGS = ['tom', 'aabne', 'simuleret', 'bogfoert', 'laast', 'kanSlettes', 'soegning', 'kunEgne', 'mange']

const DATA = { kladdeId: '12', enSideRow: '1', manglerKontoRow: '1', linjer: 3, kontonr: '1000', funktion: 'bogfor' }
function stCombos (states, flagNames) {
  const out = []
  for (const state of [...states, 'unknown']) {
    out.push({ state, flags: {}, data: {} })
    out.push({ state, flags: {}, data: DATA })
    out.push({ state, flags: {}, data: { ...DATA, funktion: 'simuler' } })
    for (const f of flagNames) out.push({ state, flags: { [f]: true }, data: DATA })
    out.push({ state, flags: Object.fromEntries(flagNames.map((f) => [f, true])), data: DATA })
  }
  return out
}
const KK_ST = stCombos(KK_STATES, KK_FLAGS)
const KL_ST = stCombos(['tom', 'liste'], KL_FLAGS)
// Every page object: each route's own page and, for a page with variants (spec §24: one route that shows several pages, such
// as the settings sections of systemdata/settingsSection.php), each variant, walked like a page of its own (where: route?key).
const variantsOf = (page) => (typeof page.variant === 'function' && page.variants && typeof page.variants === 'object' ? Object.entries(page.variants) : [])
const PAGES = Object.entries(C.pages)
  .flatMap(([route, page]) => [{ route, key: '', page }, ...variantsOf(page).map(([key, v]) => ({ route, key, page: v }))])
  .map((p) => ({ ...p, where: p.key ? p.route + '?' + p.key : p.route, states: stCombos(p.page.states, p.page.flags) }))
const GROUPS = new Set(PAGES.map((p) => p.page.group))
const stubCtx = (route, field = null) => ({ doc: new Doc(), win: {}, url: new URL('https://x/' + route), route, lang: 'da', t: (id) => T[id] || '', field })

// ------------------------------------------------------------------ the keys the content can produce

function msgKey (msg) { return Array.isArray(msg) ? msg[0] : msg }

function producedKeys (page, route) {
  const keys = new Map() // key -> number of %s arguments, or null when it is not a Msg with arguments
  const add = (k, args = 0) => { assert.equal(typeof k, 'string', 'a text key is a string: ' + String(k)); keys.set(k, args) }
  const addShow = (s) => { if (!s) return; add(s.label); if (s.note) add(s.note) }
  for (const t of Object.values(page.tasks)) { add(t.title); add(t.why); t.steps.forEach((k) => add(k)); addShow(t.show) }
  for (const f of Object.values(page.fields)) { add(f.label); add(f.text) }
  page.shortcuts.forEach((s) => add(s.text))
  if (page.shortcutsNote) add(page.shortcutsNote)
  for (const q of Object.values(page.questions)) { add(q.q); if (q.intro) add(q.intro); q.steps.forEach((k) => add(k)); addShow(q.show) }
  const states = stCombos(page.states, page.flags)
  for (const field of [null, { key: 'debe', el: null }, { key: 'kred', el: null }]) {
    const ctx = stubCtx(route, field)
    for (const st of states) {
      const name = page.name(ctx, st)
      add(msgKey(name), Array.isArray(name) ? name.length - 1 : 0)
      page.about(ctx, st).forEach((k) => add(k))
    }
  }
  return keys
}

const KEYS = PAGES.map((p) => ({ ...p, keys: producedKeys(p.page, p.route) }))

// ------------------------------------------------------------------ tests

test('the shared data has the shape the shell reads', () => {
  assert.equal(C.version, 1)
  assert.equal(C.guideLinks, false)
  for (const route of ['finans/kassekladde.php', 'finans/kladdeliste.php', 'finans/regnskab.php', 'finans/rapport.php', 'systemdata/kontoplan.php']) {
    assert.ok(C.pages[route], route)
  }
  for (const { route, key, where, page } of PAGES) {
    assert.match(route, /^[a-z]+(\/[A-Za-z0-9_%]+)+\.php$/, route)
    assert.match(page.group, /^[a-z]{2,12}$/, where)
    assert.notEqual(page.group, 'common', where)
    assert.ok(Array.isArray(page.states) && page.states.length > 0, where + ': states')
    assert.ok(Array.isArray(page.flags), where + ': flags')
    for (const fn of ['name', 'detect', 'about', 'order', 'questionOrder', 'signal']) assert.equal(typeof page[fn], 'function', where + ': ' + fn)
    if (key) assert.equal(variantsOf(page).length, 0, where + ': a variant has no variants of its own (one level, spec §24)')
  }
  assert.equal(C.support.phone, '46 90 22 08')
  assert.equal(C.support.phoneIntl, '+45 46 90 22 08')
  assert.equal(C.support.tel, '+4546902208')
  assert.equal(C.support.mail, 'support@saldi.dk')
  assert.equal(C.support.tz, 'Europe/Copenhagen')
  assert.ok(C.support.openHour < C.support.closeHour)
  for (const lang of ['da', 'en', 'no']) {
    assert.ok(C.contactWords[lang].length > 3, 'contactWords.' + lang)
    assert.ok(C.stopWords[lang].length > 10, 'stopWords.' + lang)
    for (const w of [...C.contactWords[lang], ...C.stopWords[lang]]) assert.equal(w, w.toLowerCase().trim(), lang + ': ' + w)
  }
  eq(C.globalShortcuts.map((s) => s.keys), ['?', 'F1', 'Esc', 'Ctrl+K', 'Alt+L'])
  eq(C.globalShortcuts.map((s) => s.id), ['8749', '8750', '8751', '8752', '8753'])
  assert.equal(KL.group, 'kl')
  assert.equal(KK.group, 'kk')
})

test('every key the content can produce is a row of the keyed TSV, with the right number of %s', () => {
  for (const { page: { group }, keys } of KEYS) {
    for (const [key, args] of keys) {
      assert.match(key, new RegExp('^help\\.(common|' + group + ')\\.[a-z0-9_.]+$'), key)
      assert.ok(tsv.has(key), 'missing in importfiler/tekster_keyed.tsv: ' + key)
      assert.equal(tsv.get(key)[1].split('%s').length - 1, args, '%s in ' + key)
    }
  }
})

test('every help row of the TSV is used, and its key follows the scheme', () => {
  // Produced for the stub states, or named as a literal in the content (keys picked by data, such as a report type)
  const literal = sources.join('\n').match(/'help\.[a-z0-9_.]+'(?!\s*\+)/g).map((k) => k.slice(1, -1))
  for (const key of literal) assert.ok(tsv.has(key), 'a key in the content is missing in the TSV: ' + key)
  const used = new Set([...KEYS.flatMap((p) => [...p.keys.keys()]), ...literal])
  const helpRows = tsvRows.filter((f) => f[0].startsWith('help.'))
  assert.ok(helpRows.length > 1500)
  for (const [key] of helpRows) {
    assert.match(key, /^help\.[a-z]+\.[a-z0-9_.]+$/, key)
    const g = key.split('.')[1]
    assert.ok(g === 'common' || GROUPS.has(g), key + ': no page has the group ' + g)
    assert.ok(used.has(key), 'dead row (no content uses it): ' + key)
  }
})

test('each help row is well formed in all three languages', () => {
  for (const f of tsvRows.filter((r) => r[0].startsWith('help.'))) {
    const key = f[0]
    assert.equal(f.length, 4, key + ' has ' + f.length + ' fields')
    for (const text of f.slice(1)) {
      assert.ok(text !== '' && text === text.trim(), key + ': empty field or outer whitespace')
      assert.doesNotMatch(text, /Assist|Sally|!/, key)
      assert.doesNotMatch(text, /["<>&]/, key + ': no quotes or markup, the shell shows text only')
      assert.equal(text.split('**').length % 2, 1, key + ': unbalanced **')
      // A title, a why line, a question and a field label are shown as plain text (saldi-help.js rowButton()), so no **
      if (/\.(why|title|q|label)$/.test(key)) assert.ok(!text.includes('**'), key + ': ** is shown as is in a plain-text line')
      // findtekst() stores texts in ISO-8859-1 for Latin-1 tenants: anything outside it would turn into '?'.
      assert.ok([...text].every((ch) => ch.charCodeAt(0) <= 0xff), key + ': a character outside ISO-8859-1')
    }
    for (const mark of ['%s', '**']) {
      const counts = f.slice(1).map((t) => t.split(mark).length - 1)
      assert.ok(counts.every((c) => c === counts[0]), key + ': different number of ' + mark + ' per language')
    }
  }
})

test('the keyed TSV stays sorted by key with unique keys', () => {
  const keys = tsvRows.map((f) => f[0])
  assert.equal(new Set(keys).size, keys.length)
  for (let i = 1; i < keys.length; i++) assert.ok(keys[i - 1] < keys[i], keys[i] + ' is out of order')
})

test('tasks, fields, shortcuts and questions follow the schema', () => {
  for (const { page } of PAGES) {
    for (const [id, t] of Object.entries(page.tasks)) {
      assert.ok(t.steps.length >= 1 && t.steps.length <= 4, id + ' has ' + t.steps.length + ' steps')
      if (t.show) {
        assert.ok(['button', 'field', 'status', 'row'].includes(t.show.kind), id)
        assert.ok(typeof t.show.target === 'string' || typeof t.show.target === 'function', id)
        if (typeof t.show.target === 'string') new Doc().querySelector(t.show.target) // parses
      }
    }
    for (const k of Object.keys(page.fields)) assert.match(k, /^[a-z_]+$|^search:[a-z_]+$|^rowcount$|^kkRegnaar$/, k)
    for (const s of page.shortcuts) {
      assert.ok(['keys', 'codes'].includes(s.group), s.keys)
      assert.ok(s.keys !== '')
    }
    for (const [id, q] of Object.entries(page.questions)) {
      for (const lang of ['da', 'en', 'no']) assert.ok(Array.isArray(q.words[lang]) && q.words[lang].length > 0, id + ' words.' + lang)
      assert.ok(q.steps.length >= 1 && q.steps.length <= 4, id)
    }
  }
})

test('order and questionOrder only name existing ids, at most once', () => {
  for (const { page, route, where, states } of PAGES) {
    for (const field of [null, { key: 'debe', el: null }]) {
      const ctx = stubCtx(route, field)
      for (const st of states) {
        const order = page.order(ctx, st)
        assert.equal(new Set(order).size, order.length, where + ' ' + JSON.stringify(st))
        for (const id of order) assert.ok(page.tasks[id], where + ': ' + id)
        if (page.problem !== undefined) {
          assert.equal(typeof page.problem, 'function', where + ': problem')
          const pid = page.problem(ctx, st)
          assert.equal(typeof pid, 'string', where + ': problem() gives a task id or \'\'')
          if (pid) assert.ok(page.tasks[pid], where + ': problem() names ' + pid)
        }
        const qs = page.questionOrder(ctx, st)
        assert.ok(qs.length <= 3, where)
        for (const id of qs) assert.ok(page.questions[id], where + ': ' + id)
        assert.equal(typeof page.signal(ctx, st), 'boolean')
      }
    }
  }
})

test('kassekladde: the order and the questions follow the state', () => {
  const ctx = stubCtx('finans/kassekladde.php')
  const order = (state, flags = {}) => KK.order(ctx, { state, flags, data: {} })
  const start = ['KK9', 'KK8', 'KK27', 'KK26', 'KK18']
  const tail = ['KK7', 'KK26', 'KK33', 'KK32', 'KK17', 'KK19']
  eq(order('ny'), start, 'a new journal: the first line, the bank route and the double-posting trap')
  eq(order('afvist'), ['KK1', 'KK28', 'KK26'])
  eq(order('afvist', { manglerKonto: true }), ['KK1', 'KK2', 'KK28', 'KK26'])
  eq(order('aaben'), start, 'an open journal without saved lines starts like a new one')
  eq(order('aaben', { difference: true, enSide: true }), ['KK3', 'KK4', 'KK18', 'KK5', 'KK8', ...tail], 'few lines: split a voucher')
  eq(order('aaben', { hasSaved: true, difference: true, enSide: true, mange: true }).slice(0, 3), ['KK3', 'KK4', 'KK5'], 'a bank import: Udlign')
  eq(order('aaben', { balancerer: true, kontrolkonto: true }), ['KK6', 'KK31', 'KK8', ...tail])
  eq(order('aaben', { hasSaved: true, balancerer: true, udenBilag: true }).slice(0, 3), ['KK6', 'KK7', 'KK31'])
  eq(order('aaben', { hasSaved: true, balancerer: true, harBilag: true, kontrolkonto: true }).slice(0, 3), ['KK6', 'KK31', 'KK18'])
  eq(order('aaben', { hasSaved: true, balancerer: true, udenBilag: true, mange: true }).slice(0, 3), ['KK6', 'KK34', 'KK31'])
  eq(order('aaben', { hasSaved: true, harBilag: true }), ['KK18', 'KK8', 'KK16', 'KK29', 'KK30', ...tail])
  eq(order('aaben', { manglerKonto: true, dublet: true, fakturaBrugt: true, difference: true }).slice(0, 4), ['KK2', 'KK10', 'KK11', 'KK3'])
  eq(KK.order({ ...ctx, field: { key: 'kred', el: null } }, { state: 'aaben', flags: { difference: true }, data: {} }).slice(0, 2), ['KK3', 'KK4'])
  eq(order('simuleret'), ['KK12'])
  eq(order('bogfoert'), ['KK20', 'KK13', 'KK14', 'KK23'])
  eq(order('bogfoert', { harBilag: true }), ['KK20', 'KK13', 'KK14', 'KK29', 'KK23'], 'no KK30: a posted bilag is Låst after a day')
  eq(order('kopier'), ['KK14k'])
  eq(order('vaelgTilbagefoersel'), ['KK15'])
  eq(order('opslag'), ['KK24'])
  for (const s of ['afbrudt', 'udskrift', 'unknown']) eq(order(s), [])
  const qo = (state, flags = {}) => KK.questionOrder(ctx, { state, flags, data: {} })
  eq(qo('ny'), ['Q4', 'Q3', 'Q11'])
  eq(qo('aaben'), ['Q4', 'Q3', 'Q11'])
  eq(qo('aaben', { hasSaved: true }), ['Q2', 'Q4', 'Q11'])
  eq(qo('aaben', { enSide: true }), ['Q1', 'Q2', 'Q3d'])
  eq(qo('aaben', { difference: true }), ['Q1', 'Q2', 'Q3'])
  eq(qo('aaben', { hasSaved: true, manglerKonto: true }), ['Q12', 'Q2', 'Q4'], 'a line without an account (problem card KK2): which account')
  eq(qo('aaben', { hasSaved: true, manglerKonto: true, difference: true, enSide: true }), ['Q12', 'Q1', 'Q2'])
  eq(qo('afvist'), ['Q14', 'Q6', 'Q4'])
  eq(qo('bogfoert'), ['Q3b', 'Q7', 'Q4'], 'a posted journal: how to correct it, then where it went')
  eq(qo('bogfoert', { harBilag: true }), ['Q3b', 'Q7', 'Q16'])
  eq(qo('simuleret'), ['Q3c', 'Q4', 'Q2'])
  eq(qo('kopier'), ['Q3b', 'Q2', 'Q4'])
  eq(qo('vaelgTilbagefoersel'), ['Q3b', 'Q2', 'Q4'], 'picking the kladde for a reversal is Q3b\'s second step')
  eq(qo('opslag'), ['Q12', 'Q3', 'Q11'])
  for (const s of ['afbrudt', 'udskrift', 'unknown']) eq(qo(s), ['Q2', 'Q4', 'Q3'], s + ': no difference to explain')
  eq(KK.tasks.KK14k.steps, ['help.kk.task.kk14.step2', 'help.kk.task.kk14.step3', 'help.kk.task.kk14.step4'])
  eq(KK.questions.Q3b.steps, ['help.kk.task.kk13.step1', 'help.kk.task.kk13.step2', 'help.kk.q.q3b.step4', 'help.kk.task.kk13.step4'])
  eq(KK.questions.Q13.steps, ['help.kk.q.q13.step1', 'help.kk.q.q13.step4', 'help.kk.q.q13.step2', 'help.kk.q.q13.step3'], 'saved lines keep their code')
  eq([!KK.questions.Q17.show, KK.questions.Q18.show === KK.tasks.KK32.show], [true, true], 'Q17 has no Vis mig; Q18 shows Debet, as KK32')
  assert.equal(KK.signal(ctx, { state: 'afvist', flags: {}, data: {} }), true)
  assert.equal(KK.signal(ctx, { state: 'aaben', flags: { difference: true }, data: {} }), false)
  assert.equal(KK.name(ctx, { state: 'ny', flags: {}, data: {} }), 'help.kk.name_new')
  eq(KK.name(ctx, { state: 'aaben', flags: {}, data: { kladdeId: '45' } }), ['help.kk.name', '45'])
  assert.equal(KK.name(ctx, { state: 'opslag', flags: {}, data: { kladdeId: '' } }), 'help.kk.name_plain')
  const about = (state, flags = {}) => KK.about(ctx, { state, flags, data: {} })
  eq(about('ny'), ['help.kk.about.ny', 'help.kk.about.model'], 'the model paragraph in a starter state')
  eq(about('ny', { ikkeGemt: true }), ['help.kk.about.ny', 'help.kk.about.ikke_gemt'])
  eq(about('aaben'), ['help.kk.about.aaben', 'help.kk.about.model'])
  eq(about('aaben', { hasSaved: true }), ['help.kk.about.aaben'], 'one paragraph on a working journal')
  eq(about('aaben', { hasSaved: true, balancerer: true }), ['help.kk.about.aaben_balancerer'])
  eq(about('aaben', { hasSaved: true, balancerer: true, udenBilag: true }), ['help.kk.about.aaben_balancerer', 'help.kk.about.uden_bilag'],
    'ready to post, lines without a document')
  eq(about('aaben', { hasSaved: true, balancerer: true, udenBilag: true, ikkeGemt: true }), ['help.kk.about.aaben_balancerer', 'help.kk.about.ikke_gemt'])
  eq(about('aaben', { hasSaved: true, difference: true, udenBilag: true }), ['help.kk.about.aaben_difference'])
  eq(about('aaben', { hasSaved: true, balancerer: true, manglerKonto: true, udenBilag: true }), ['help.kk.about.aaben'])
  eq(about('aaben', { balancerer: true, manglerKonto: true }), ['help.kk.about.aaben'])
  eq(about('aaben', { difference: true, ikkeGemt: true }), ['help.kk.about.aaben_difference', 'help.kk.about.ikke_gemt'])
  eq(about('bogfoert'), ['help.kk.about.bogfoert'])
  eq(about('afvist', { ikkeGemt: true }), ['help.kk.about.afvist', 'help.kk.about.ikke_gemt'])
  eq(about('afbrudt'), [])
})

test('kladdeliste: the order follows the flags', () => {
  const ctx = stubCtx('finans/kladdeliste.php')
  const order = (flags) => KL.order(ctx, { state: flags.tom ? 'tom' : 'liste', flags, data: {} })
  // a new customer: the whole flow, the year, how to organise; Vis alle 4th when only their own journals are listed
  eq(order({ tom: true }), ['K11', 'K10', 'K13', 'K2'])
  eq(order({ tom: true, kunEgne: true }), ['K11', 'K10', 'K13', 'K4', 'K2'])
  eq(KL.order(ctx, { state: 'tom', flags: {}, data: {} }), ['K11', 'K10', 'K13', 'K2'], 'the state alone, as the digest calls it')
  // empty behind a search: there are journals, none match
  eq(order({ tom: true, soegning: true }), ['K1', 'K9', 'K2', 'K10'])
  eq(order({ tom: true, soegning: true, kunEgne: true }).slice(0, 3), ['K1', 'K4', 'K9'])
  // the first journals
  eq(order({ faa: true, aabne: true }).slice(0, 3), ['K3', 'K11', 'K2'])
  eq(order({ faa: true, bogfoert: true }).slice(0, 3), ['K12', 'K2', 'K8'])
  eq(order({ faa: true, aabne: true, simuleret: true }).slice(0, 3), ['K3', 'K6', 'K11'])
  eq(order({ faa: true, aabne: true, bogfoert: true }).slice(0, 3), ['K3', 'K11', 'K2'])
  // a bookkeeper's long list
  eq(order({ aabne: true, bogfoert: true, kanSlettes: true, mange: true }).slice(0, 3), ['K3', 'K2', 'K7'])
  eq(order({ bogfoert: true, mange: true, kunEgne: true }).slice(0, 3), ['K2', 'K8', 'K4'])
  eq(order({ bogfoert: true, mange: true }), ['K2', 'K8', 'K12', 'K9', 'K13', 'K10'])
  eq(order({ aabne: true, laast: true }).slice(0, 3), ['K3', 'K5', 'K2'])
  eq(order({ aabne: true, simuleret: true, laast: true, kunEgne: true }).slice(0, 5), ['K3', 'K6', 'K5', 'K2', 'K4'])
  eq(order({ soegning: true, aabne: true }).slice(0, 3), ['K1', 'K3', 'K2'])
  eq(order({ soegning: true, aabne: true, faa: true }).slice(0, 3), ['K1', 'K3', 'K2'], 'a short list behind a search is not a new customer')
  eq(order({ mange: true, soegning: true }), ['K1', 'K2', 'K13', 'K10'], 'no K9 while a search is active')

  const about = (flags) => KL.about(ctx, { state: flags.tom ? 'tom' : 'liste', flags, data: {} })
  eq(about({ tom: true }), ['help.kl.about.hvad', 'help.kl.about.tom'])
  eq(about({ tom: true, kunEgne: true }), ['help.kl.about.hvad', 'help.kl.about.tom_egne'])
  eq(about({ tom: true, soegning: true, kunEgne: true }), ['help.kl.about.soegning_tom'])
  eq(about({ faa: true }), ['help.kl.about.hvad', 'help.kl.about.liste'])
  eq(about({ faa: true, soegning: true, kunEgne: true }), ['help.kl.about.liste', 'help.kl.about.soegning'], 'a search wins over the model')
  eq(about({ kunEgne: true }), ['help.kl.about.liste', 'help.kl.about.kun_egne'])
  eq(about({ kunEgne: true, soegning: true }), ['help.kl.about.liste', 'help.kl.about.soegning'])
  eq(about({ mange: true }), ['help.kl.about.liste'])
  for (const st of stCombos(KL.states, KL.flags)) {
    const n = KL.about(ctx, st).length
    assert.ok(n >= 1 && n <= 2, 'Om siden has 1-2 paragraphs: ' + JSON.stringify(st))
  }

  const qo = (flags) => KL.questionOrder(ctx, { state: flags.tom ? 'tom' : 'liste', flags, data: {} })
  eq(qo({ tom: true }), ['Q4', 'Q1', 'Q7'])
  eq(qo({ tom: true, soegning: true }), ['Q2', 'Q1', 'Q4'])
  eq(qo({ faa: true }), ['Q4', 'Q6', 'Q1'], 'only open journals: how to organise them, the three groups once one has moved')
  eq(qo({ faa: true, aabne: true, bogfoert: true }), ['Q4', 'Q5', 'Q1'])
  eq(qo({ faa: true, simuleret: true }), ['Q4', 'Q5', 'Q1'])
  eq(qo({ faa: true, soegning: true }), ['Q2', 'Q1', 'Q3'], 'a search hides journals: Q2 says how to clear it')
  eq(qo({ mange: true, soegning: true }), ['Q2', 'Q1', 'Q3'])
  eq(qo({ mange: true }), ['Q1', 'Q2', 'Q3'])
  eq(KL.questions.Q6.steps, ['help.kl.task.k13.step1', 'help.kl.task.k13.step2', 'help.kl.task.k13.step3', 'help.kl.task.k13.step4'])
  eq(KL.questions.Q7.steps, ['help.kl.task.k10.step1', 'help.kl.task.k10.step2', 'help.kl.task.k10.step3', 'help.kl.task.k10.step4'])
  assert.equal(KL.questions.Q7.intro, 'help.kl.task.k10.why')
  for (const [id, n] of [['K3', 3], ['K6', 4], ['K8', 4], ['K10', 4], ['K11', 4], ['K12', 3], ['K13', 4]]) {
    assert.equal(KL.tasks[id].steps.length, n, id + ' steps')
  }
  // Q9, search only: the closed VAT period sends the user to this list (bogfor.php:251-257, 294-296)
  eq(KL.questions.Q9.steps, ['help.kl.q.q9.step1', 'help.kl.q.q9.step2', 'help.kl.q.q9.step3'])
  assert.ok(KL.questions.Q9.words.da.includes('perioden er lukket'))
  for (const st of stCombos(KL.states, KL.flags)) assert.ok(!KL.questionOrder(ctx, st).includes('Q9'), 'Q9 is found by search only')
})

test('kassekladde detect() gives the state of each page', () => {
  const detect = (doc, url = KK_URL, extra) => KK.detect(ctxFor(doc, url, extra))
  const saved = [{ n: 1, id: '101', debe: '5800', kred: '', besk: 'Hjælp-test', belo: '100,00' }]

  let st = detect(kkPage({ kladdeId: '', lines: [{ n: 1 }] }), 'https://x/asr_hj/finans/kassekladde.php?returside=kladdeliste.php&tjek=-1')
  assert.equal(st.state, 'ny')
  assert.equal(st.data.kladdeId, '')
  assert.equal(st.flags.hasSaved, false)

  st = detect(kkPage({ kladdeId: '0', lines: [{ n: 1 }], balance: 'ok' }))
  assert.equal(st.state, 'ny', 'kladde_id 0 is a new journal')
  assert.equal(st.flags.balancerer, false, 'a new journal says "balancerer" without saved lines')

  st = detect(kkPage({ lines: saved, balance: 'diff' }))
  assert.equal(st.state, 'aaben')
  assert.equal(st.data.kladdeId, '12', 'kladde_id printed twice is read from the list')
  eq([st.flags.difference, st.flags.balancerer, st.flags.enSide, st.data.enSideRow], [true, false, true, '1'])
  assert.equal(st.flags.manglerKonto, false)

  st = detect(kkPage({ lines: [{ n: 1, id: '101', debe: '5800', kred: '1000', belo: '100,00' }], balance: 'ok', kontrolkonto: '5800' }))
  assert.equal(st.state, 'aaben')
  eq([st.flags.difference, st.flags.balancerer, st.flags.enSide, st.flags.kontrolkonto], [false, true, false, true])

  st = detect(kkPage({ lines: [{ n: 1, id: '101', besk: 'Gebyr', belo: '25,00' }, { n: 2, id: '102', belo: '0,00' }], balance: 'diff' }))
  assert.equal(st.flags.manglerKonto, true)
  assert.equal(st.data.manglerKontoRow, '1', 'a line with only a zero amount is not missing an account')

  st = detect(kkPage({ lines: [{ n: 1, id: '101', debe: '5800', kred: '1000', belo: '100,00', bilaTitle: 'En tilsvarende postering er også ført på kladde 9' }], balance: 'ok', reuse: true }))
  eq([st.flags.dublet, st.flags.fakturaBrugt], [true, true])

  st = detect(kkPage({ lines: saved, footer: 'refused', balance: 'diff' }))
  assert.equal(st.state, 'afvist')
  assert.equal(KK.signal(ctxFor(new Doc(), KK_URL), st), true)

  st = detect(kkPage({ lines: saved, footer: 'simulated' }))
  assert.equal(st.state, 'simuleret')

  st = detect(kkPage({ lines: saved, footer: 'posted' }))
  assert.equal(st.state, 'bogfoert')

  st = detect(kkPage({ lines: saved, footer: 'interrupted' }))
  assert.equal(st.state, 'afbrudt', 'an interrupted journal has undo links too, but is not posted')

  st = detect(copyDialog(), 'https://x/asr_hj/finans/kassekladde.php')
  assert.equal(st.state, 'kopier', 'the copy dialog has no form#kassekladde, but it is not a lookup')

  st = detect(chooseReversal(), 'https://x/asr_hj/finans/kassekladde.php?kladde_id=12&ompost=501')
  assert.equal(st.state, 'vaelgTilbagefoersel')
  assert.equal(st.data.kladdeId, '12')

  st = detect(new Doc(), 'https://x/asr_hj/finans/kassekladde.php?kladde_id=12&fokus=debe1')
  assert.equal(st.state, 'opslag')

  st = detect(kkPage({ lines: saved }), KK_URL + '&udskriv=1')
  assert.equal(st.state, 'udskrift')

  st = detect(kkPage({ lines: saved, balance: 'diff' }), KK_URL, { docChange: true })
  assert.equal(st.flags.ikkeGemt, true)
})

test('kassekladde show targets find the element they name', () => {
  const page = kkPage({ lines: [{ n: 1, id: '101', debe: '5800', kred: '' }, { n: 2, id: '102', besk: 'Gebyr', belo: '25,00' }], balance: 'diff', clip: true })
  const ctx = ctxFor(page, KK_URL)
  const st = KK.detect(ctx)
  const resolve = (show, c = ctx, s = st) => typeof show.target === 'function' ? show.target(c, s) : c.doc.querySelector(show.target)
  assert.equal(resolve(KK.tasks.KK2.show).name, 'debe2', 'the line without an account')
  assert.equal(resolve(KK.tasks.KK4.show).name, 'kred1', 'the empty side of the one-sided line')
  assert.equal(resolve(KK.tasks.KK3.show).getAttribute('id'), 'kk-balance-status')
  assert.equal(resolve(KK.tasks.KK5.show).name, 'offset')
  assert.equal(resolve(KK.tasks.KK6.show).name, 'doPost')
  assert.equal(resolve(KK.tasks.KK7.show).getAttribute('class'), 'clip-cell clip-no-doc')
  assert.equal(resolve(KK.tasks.KK8.show).name, 'import')
  assert.equal(resolve(KK.tasks.KK9.show).name, 'besk1', 'without a new line: the first Bilagstekst')
  assert.equal(resolve(KK.tasks.KK16.show).name, 'kontrolkonto')
  assert.equal(resolve(KK.tasks.KK27.show).name, 'import')
  assert.equal(resolve(KK.tasks.KK31.show).name, 'doPost', 'the check before posting rings Bogfør, not the clip')
  assert.equal(resolve(KK.tasks.KK34.show).name, 'bilagsmatch')
  assert.equal(resolve(KK.tasks.KK17.show).name, 'debe1', 'a purchase abroad starts in Debet')
  assert.equal(resolve(KK.questions.Q11.show), null, 'no Moms column in this page')
  const fieldCtx = { ...ctx, field: { key: 'besk', el: new El('input', { name: 'besk2' }) } }
  assert.equal(resolve(KK.questions.Q3.show, fieldCtx).name, 'kred2', "Kredit on the line of the user's field")
  assert.equal(resolve(KK.questions.Q3.show).name, 'kred1')
  assert.equal(resolve(KK.tasks.KK26.show, fieldCtx).name, 'debe2', "Debet on the line of the user's field")
  assert.equal(resolve(KK.tasks.KK26.show).name, 'debe1', 'else line 1')

  // The editable view's other controls, and the blank new line 3 (kassekladde.php:3880-3891, 4020-4046)
  const tbody = page.querySelector('tbody')
  const tr1 = tbody.children[0]
  tr1.append(new El('button', { type: 'button', class: 'duplicate-line-btn', 'data-row': '1' }), new El('button', { type: 'button', class: 'delete-line-btn', 'data-row': '1', disabled: '' }))
  tbody.children[1].append(new El('button', { type: 'button', class: 'delete-line-btn', 'data-row': '2' }), new El('input', { type: 'checkbox', name: 'moms2' }))
  tbody.append(new El('tr').append(new El('input', { type: 'text', name: 'bila3' }), new El('input', { type: 'hidden', name: 'bilf3', value: '3' }),
    new El('input', { type: 'text', name: 'besk3' }), new El('input', { type: 'text', name: 'debe3' }), new El('select', { name: 'dvat3' }),
    new El('input', { type: 'text', name: 'kred3' })))
  const body = page.querySelector('body')
  body.append(new El('button', { type: 'button', id: 'kkVisToggle' }), new El('select', { id: 'kkRegnaar' }), new El('span', { id: 'kkJournalDocs' }))
  const st2 = KK.detect(ctx)
  assert.equal(st2.data.newRow, '3')
  assert.equal(resolve(KK.tasks.KK9.show, ctx, st2).name, 'besk3', 'the new line')
  assert.equal(resolve(KK.tasks.KK18.show, ctx, st2).name, 'bila3')
  assert.equal(resolve(KK.tasks.KK17.show, ctx, st2).name, 'debe3')
  assert.equal(resolve(KK.questions.Q11.show, ctx, st2).name, 'dvat3')
  assert.equal(resolve(KK.tasks.KK26.show, ctx, st2).name, 'debe3')
  assert.equal(resolve(KK.tasks.KK32.show, ctx, st2).name, 'debe3', 'a sale without an invoice starts in Debet')
  assert.equal(resolve(KK.tasks.KK19.show).getAttribute('data-row'), '2', 'x of a line without a document')
  assert.equal(resolve(KK.tasks.KK35.show).getAttribute('data-row'), '1')
  assert.equal(resolve(KK.questions.Q13.show).name, 'moms2')
  assert.equal(resolve(KK.tasks.KK25.show).getAttribute('id'), 'kkVisToggle')
  assert.equal(resolve(KK.tasks.KK28.show).getAttribute('id'), 'kkRegnaar')
  assert.equal(resolve(KK.tasks.KK31.show, ctxFor(kkPage({ lines: [{ n: 1, id: '101' }], footer: 'refused' }), KK_URL), st2), null, 'no Bogfør on a refused journal')

  const posted = kkPage({ lines: [{ n: 1, id: '101', debe: '5800', kred: '1000' }], footer: 'posted' })
  const grid = new El('tr').append(new El('td', { class: 'clip-cell clip-has-doc' }), new El('td').append(new El('a', { class: 'kk-card-link', href: 'openAccountCard.php?k=5800' })))
  posted.querySelector('body').append(new El('table').append(grid), new El('input', { type: 'submit', name: 'updateNote' }))
  const pc = ctxFor(posted, KK_URL)
  assert.match(resolve(KK.tasks.KK13.show, pc).getAttribute('href'), /ompost=501$/, 'the first undo link, not #undo')
  assert.equal(resolve(KK.tasks.KK14.show, pc).name, 'copy2new')
  assert.equal(resolve(KK.tasks.KK20.show, pc).getAttribute('class'), 'kk-card-link')
  assert.equal(resolve(KK.tasks.KK23.show, pc).name, 'ny_kladdenote', 'the title and [1] name Bemærkning; Opdatér is [2]')
  for (const id of ['KK22', 'KK29', 'KK30']) assert.equal(resolve(KK.tasks[id].show, pc).getAttribute('class'), 'clip-cell clip-has-doc', id)
  assert.equal(resolve(KK.tasks.KK7.show, pc), null, 'the posted grid is outside the form')
  assert.equal(resolve(KK.tasks.KK14k.show, ctxFor(copyDialog(), KK_URL)).name, 'bilagsnr')
  assert.match(resolve(KK.tasks.KK15.show, ctxFor(chooseReversal(), KK_URL)).getAttribute('href'), /ompost_til=14/)
  for (const id of ['KK24', 'KK33']) assert.equal(KK.tasks[id].show, undefined, id + ' has no Vis mig')
})

test('kladdeliste detect(): rows, locks, search and the own-journals filter', () => {
  const detect = (doc, url = KL_URL) => KL.detect(ctxFor(doc, url))
  const tbodyOf = (doc) => doc.querySelector('#datatable-kladdelst tbody')
  let st = detect(klPage([]))
  assert.equal(st.state, 'tom')
  assert.equal(st.flags.tom, true)
  eq([st.flags.faa, st.flags.mange, st.data.rows], [false, false, 0])

  // the grid's filler row (includes/grid.php:1305-1307) is not a journal
  const empty = klPage([])
  tbodyOf(empty).append(new El('tr', { class: 'filler-row' }).append(new El('td', { colspan: '8' })))
  assert.equal(detect(empty).state, 'tom', 'only the filler row')

  st = detect(klPage(['-', '!', 'S', 'V', 'V'], { locked: 0, deletable: true, toggle: 'Vis alle' }))
  assert.equal(st.state, 'liste')
  eq([st.flags.aabne, st.flags.simuleret, st.flags.bogfoert, st.flags.laast, st.flags.kanSlettes, st.flags.mange, st.flags.faa],
    [true, true, true, true, true, false, true])
  assert.equal(st.data.rows, 5)

  // the section rows added after load (kladdeliste.php:699-727) and the filler row do not count
  const sections = klPage(['-', 'S', 'V', 'V', 'V', 'V'])
  const tb = tbodyOf(sections)
  tb.append(new El('tr', { class: 'simulated-separator' }).append(new El('td')))
  tb.append(new El('tr', { class: 'posted-separator' }).append(new El('td')))
  tb.append(new El('tr', { class: 'filler-row' }).append(new El('td')))
  st = detect(sections)
  eq([st.data.rows, st.flags.faa], [6, false], 'six journals, not nine rows')

  // the ID column hidden: no data-bogfort, but the journals are still rows (U1 D3)
  const hidden = klPage([])
  for (let i = 0; i < 2; i++) tbodyOf(hidden).append(new El('tr').append(new El('td', {}, { text: '01-10-2026' })))
  st = detect(hidden)
  eq([st.state, st.flags.tom, st.flags.faa, st.flags.aabne, st.data.rows], ['liste', false, true, false, 2])

  // Redigér > Kolonner replaces the list with a form without thead: not an empty list
  const setup = new Doc()
  const setupTable = new El('table', { id: 'datatable-kladdelst' })
  setup.append(new El('body').append(new El('div', { id: 'datatable-wrapper-kladdelst' }).append(setupTable)))
  setupTable.append(new El('tbody').append(new El('tr').append(new El('td', { colspan: '100' }))))
  st = detect(setup)
  eq([st.state, st.flags.tom, st.flags.faa], ['liste', false, false])

  // the lock: only on a journal that can still change; a colleague's journal posted within the hour is not locked
  assert.equal(detect(klPage(['V', '-'], { locked: 0 })).flags.laast, false, 'a posted journal is never locked')
  assert.equal(detect(klPage(['V', 'S'], { locked: 1 })).flags.laast, true, 'a simulated one is')
  assert.equal(detect(klPage(['V', '!'], { locked: 1 })).flags.laast, false, 'a ! journal opens without the in-use alert (kassekladde.php:861)')
  assert.equal(detect(klPage(['V', '-'], { locked: 1 })).flags.laast, true, 'an open one is')

  assert.equal(detect(klPage(['-'], { search: 'bank' })).flags.soegning, true)
  assert.equal(detect(klPage(new Array(16).fill('V'))).flags.mange, true)
  assert.equal(detect(klPage(new Array(6).fill('V'))).flags.faa, false)
  assert.equal(detect(klPage(['V'])).flags.faa, true)

  // a later page of a long list (the grid's hidden offset, includes/grid.php:1549) is not a new customer's short list
  const later = klPage(['V', 'V', 'V'])
  later.querySelector('#datatable-wrapper-kladdelst').append(new El('input', { type: 'hidden', name: 'offset[kladdelst]', value: '100' }))
  st = detect(later)
  eq([st.state, st.flags.faa, st.flags.mange, st.data.rows], ['liste', false, true, 3], 'page 3: few rows, many journals')
  const first = klPage(['V', 'V', 'V'])
  first.querySelector('#datatable-wrapper-kladdelst').append(new El('input', { type: 'hidden', name: 'offset[kladdelst]', value: '0' }))
  eq([detect(first).flags.faa, detect(first).flags.mange], [true, false], 'offset 0 is the first page')

  // kunEgne: the URL parameter first, then the toggle text compared with the tenant's own 636 row
  assert.equal(detect(klPage(['-'], { toggle: 'Vis egne' }), KL_URL + '?filter=egne').flags.kunEgne, true)
  assert.equal(detect(klPage(['-'], { toggle: 'Vis alle' }), KL_URL + '?filter=alle').flags.kunEgne, false)
  assert.equal(detect(klPage(['-'], { toggle: 'Vis alle' })).flags.kunEgne, true)
  assert.equal(detect(klPage(['-'], { toggle: 'Vis egne' })).flags.kunEgne, false)
  assert.equal(detect(klPage(['-'])).flags.kunEgne, false, 'before the toggle exists (300 ms after DOMContentLoaded)')

  const page = klPage(['V', '!', '-'], { locked: 2, deletable: true, toggle: 'Vis alle' })
  const ptb = tbodyOf(page)
  ptb.append(new El('tr', { class: 'posted-separator' }).append(new El('td')))
  const ctx = ctxFor(page, KL_URL)
  st = KL.detect(ctx)
  const resolve = (show) => typeof show.target === 'function' ? show.target(ctx, st) : page.querySelector(show.target)
  assert.equal(resolve(KL.tasks.K3.show).tagName, 'TR')
  assert.equal(resolve(KL.tasks.K3.show).children[0].getAttribute('data-bogfort'), '-', 'the first open (-) journal')
  assert.equal(resolve(KL.tasks.K1.show).getAttribute('aria-label'), 'Handlinger')
  assert.equal(resolve(KL.tasks.K4.show).getAttribute('id'), 'filter-toggle-btn')
  assert.equal(resolve(KL.tasks.K5.show).parent.getAttribute('data-bogfort'), '-', 'the locked open journal')
  assert.ok(resolve(KL.tasks.K7.show))
  assert.equal(resolve(KL.tasks.K9.show).name, 'search[kladdelst][kladdenote]')
  assert.equal(resolve(KL.tasks.K12.show).getAttribute('class'), 'posted-separator')
  assert.equal(resolve(KL.questions.Q5.show).getAttribute('class'), 'posted-separator', 'no simulated journals: the posted heading')
  const lockedPosted = klPage(['V', '-'], { locked: 0 })
  assert.equal(lockedPosted.querySelector(KL.tasks.K5.show.target), null, 'K5 does not ring a posted journal')
  const sim = klPage(['S', 'V'])
  tbodyOf(sim).append(new El('tr', { class: 'simulated-separator' }).append(new El('td')))
  tbodyOf(sim).append(new El('tr', { class: 'posted-separator' }).append(new El('td')))
  const simCtx = ctxFor(sim, KL_URL)
  assert.equal(KL.questions.Q5.show.target(simCtx, KL.detect(simCtx)).getAttribute('class'), 'simulated-separator')
})

test('the content only reads: no markup, no timers, no network, no focus or clicks', () => {
  const code = sources.join('\n').replace(/^\s*\/\/.*$/gm, '')
  assert.doesNotMatch(code, /innerHTML|outerHTML|insertAdjacentHTML|document\.write/)
  assert.doesNotMatch(code, /\binstanceof\b/)
  assert.doesNotMatch(code, /setTimeout|setInterval|requestAnimationFrame|fetch\(|XMLHttpRequest|\.focus\(|\.click\(|\.setAttribute\(|\.value\s*=[^=]/)
})
test('bogfor: the order and the questions follow the state', () => {
  const BF = C.pages['finans/bogfor.php']
  const ctx = stubCtx('finans/bogfor.php')
  const order = (state, flags = {}) => BF.order(ctx, { state, flags, data: {} })
  eq(order('bekraeft'), ['BF1', 'BF2', 'BF3', 'BF4'])
  eq(order('bekraeft', { debitorer: true }).slice(0, 3), ['BF1', 'BF2', 'BF5'])
  eq(order('bekraeft', { bilagDiff: true }).slice(0, 3), ['BF7', 'BF1', 'BF2'])
  eq(order('bekraeft', { kontoMangler: true }).slice(0, 2), ['BF8', 'BF1'])
  eq(order('simuler'), ['BF1', 'BF10', 'BF9'], 'Bogfør kladden i stedet before Gem en simuleret bogføring')
  eq(order('simuler', { kreditorer: true }), ['BF1', 'BF10', 'BF9', 'BF5'])
  eq(order('fejl', { difference: true }), ['BF6'])
  eq(order('fejl', { difference: true, kontoMangler: true }), ['BF6', 'BF8'])
  eq(order('fejl'), ['BF11'])
  eq(order('stoppet'), ['BF11'])
  eq(order('videre'), [])
  const qo = (state) => BF.questionOrder(ctx, { state, flags: {}, data: {} })
  eq(qo('bekraeft'), ['Q4', 'Q5', 'Q3'])
  eq(qo('simuler'), ['Q4', 'Q5', 'Q3'])
  eq(qo('fejl'), ['Q1', 'Q3', 'Q5'], 'nothing is posted: no Q2 (undo a posting)')
  eq(qo('stoppet'), ['Q1', 'Q2', 'Q3'])
  eq(qo('videre'), ['Q2', 'Q1', 'Q3'])
  eq(BF.about(ctx, { state: 'simuler', flags: {}, data: {} }), ['help.bf.about.simuler'])
  eq(BF.about(ctx, { state: 'bekraeft', flags: { bilagDiff: true }, data: {} }), ['help.bf.about.bekraeft', 'help.bf.about.bilag_diff'])
  eq(BF.tasks.BF7.steps, ['help.bf.task.bf6.step1', 'help.bf.task.bf7.step2', 'help.bf.task.bf7.step3'])
  eq(BF.questions.Q4.steps, ['help.bf.q.q4.step1', 'help.bf.q.q4.step2', 'help.bf.q.q4.step3'])
  eq(BF.tasks.BF11.steps, ['help.bf.task.bf11.step1', 'help.bf.task.bf11.step2', 'help.bf.task.bf11.step3', 'help.bf.task.bf11.step4'])
  eq(BF.questions.Q3.steps, ['help.bf.task.bf3.step1', 'help.bf.task.bf3.step2', 'help.bf.q.q3.step3', 'help.bf.q.q3.step4'])
  assert.ok(!BF.questions.Q2.words.da.includes('modpostering'), 'Manglende modpostering is answered by BF11, not Q2')
  assert.equal(BF.questions.Q4.show, BF.tasks.BF2.show)

  // detect() on a small overview: the buttons tell Bogfør from Simulér (bogfor.php:737, :739)
  const overview = (button, party) => {
    const doc = new Doc()
    const table = new El('table', { class: 'dataTableSmall' })
    doc.append(new El('body').append(table))
    if (party) table.append(new El('tr').append(new El('td', { class: 'tableHeader' }, { text: party })))
    if (button) table.append(new El('tr').append(new El('td').append(new El('input', { type: 'submit', name: button }))))
    return doc
  }
  const url = 'https://x/asr_hj/finans/bogfor.php?funktion=bogfor&kladde_id=12'
  let st = BF.detect(ctxFor(overview('bogfor', 'Debitorbevægelser'), url))
  eq([st.state, st.flags.debitorer, st.flags.kreditorer, st.data.kladdeId], ['bekraeft', true, false, '12'])
  st = BF.detect(ctxFor(overview('simuler', 'Kreditorbevægelser'), url.replace('bogfor&', 'simuler&')))
  eq([st.state, st.flags.kreditorer, st.data.funktion], ['simuler', true, 'simuler'])
  assert.equal(BF.detect(ctxFor(overview(null), url)).state, 'fejl', 'a total difference hides the buttons')
  assert.equal(BF.detect(ctxFor(new Doc(), url)).state, 'stoppet')
})
test('finance pages: the questions on the chat\'s start screen follow the state (spec §25)', () => {
  const qo = (route, state, flags = {}, data = {}) => C.pages[route].questionOrder(stubCtx(route), { state, flags, data })
  // Regnskab: nothing posted yet, what the columns mean first
  eq(qo('finans/regnskab.php', 'oversigt'), ['Q3', 'Q1', 'Q2'])
  eq(qo('finans/regnskab.php', 'oversigt', { harBevaegelser: true }), ['Q1', 'Q2', 'Q3'])
  eq(qo('finans/regnskab.php', 'tom'), ['Q3', 'Q1', 'Q2'])
  eq(qo('systemdata/kontoplan.php', 'liste'), ['Q2', 'Q1', 'Q3'])
  // Kontokort: a new account's number, type and VAT rate; the locked number only leads with a saldo
  const KT = C.pages['systemdata/kontokort.php']
  eq(qo('systemdata/kontokort.php', 'ny'), ['Q5', 'Q4', 'Q2'])
  eq(qo('systemdata/kontokort.php', 'kort'), ['Q2', 'Q3', 'Q4'])
  eq(qo('systemdata/kontokort.php', 'kort', { harSaldo: true }), ['Q1', 'Q2', 'Q3'])
  eq(KT.questions.Q4.steps, ['help.kt.q.q4.step1', 'help.kt.q.q4.step2', 'help.kt.q.q4.step3', 'help.kt.q.q4.step4'])
  assert.equal(KT.questions.Q4.show, KT.tasks.KT4.show, 'Q4 shows Kontotype')
  assert.equal(KT.questions.Q5.intro, 'help.kt.task.kt5.why')
  assert.equal(KT.questions.Q5.show, KT.tasks.KT5.show, 'Q5 shows Kontonr.')
  // Rapporter: an error question never leads a drawn report; Q6 (from a total to an account card) is last on a card
  const RP = 'finans/rapport.php'
  eq(qo(RP, 'regnskab'), ['Q3', 'Q6', 'Q4'])
  eq(qo(RP, 'kontokort'), ['Q4', 'Q3', 'Q6'])
  eq(qo(RP, 'kontokortUbogfort'), ['Q4', 'Q3', 'Q6'])
  eq(qo(RP, 'kontokortMoms'), ['Q7', 'Q4', 'Q3'])
  eq(qo(RP, 'momsangivelse'), ['Q1', 'Q7', 'Q4'])
  eq(qo(RP, 'momsAfstemning'), ['Q7', 'Q1', 'Q4'])
  eq(qo(RP, 'forside'), ['Q1', 'Q4', 'Q3'])
  // Udlign: the invoice that is not in the list leads until the journal is through
  for (const s of ['linje', 'sprunget', 'ingen', 'ukendt']) eq(qo('finans/autoudlign.php', s), ['Q2', 'Q1'], s)
  eq(qo('finans/autoudlign.php', 'faerdig'), ['Q1', 'Q2'])
  // The imports: after the import the lines are in the kladde
  eq(qo('finans/importer.php', 'videre'), ['Q3', 'Q2', 'Q1'])
  eq(qo('finans/importer.php', 'valg'), ['Q1', 'Q3'])
  eq(qo('finans/bankimport.php', 'videre'), ['Q3', 'Q2', 'Q1'])
  eq(qo('finans/bankimport.php', 'upload'), ['Q1', 'Q2', 'Q3'])
})

test('kassekladde detect(): documents, many lines and the new line', () => {
  const detect = (doc, url = KK_URL) => KK.detect(ctxFor(doc, url))
  const line = (n) => ({ n, id: String(100 + n), debe: '2450', kred: '6480', belo: '100,00' })
  /** The blank new line (kassekladde.php:4020-4037: a clip without a doc class, bila, the hidden bilf) and a spare line. */
  const addNewLine = (doc, n) => {
    const tbody = doc.querySelector('tbody')
    tbody.append(new El('tr').append(new El('td', { class: 'clip-cell', 'data-source-id': '0' }),
      new El('input', { type: 'text', name: 'bila' + n, value: String(n) }), new El('input', { type: 'hidden', name: 'bilf' + n, value: String(n) }),
      new El('input', { type: 'text', name: 'besk' + n }), new El('input', { type: 'text', name: 'debe' + n }),
      new El('select', { name: 'dvat' + n }), new El('input', { type: 'text', name: 'kred' + n })))
    tbody.append(new El('tr').append(new El('input', { type: 'text', name: 'bila' + (n + 1) }), new El('input', { type: 'text', name: 'besk' + (n + 1) })))
    return doc
  }

  let st = detect(addNewLine(kkPage({ lines: [line(1), line(2)], balance: 'ok', clip: true }), 3))
  assert.equal(st.state, 'aaben')
  eq([st.flags.balancerer, st.flags.udenBilag, st.flags.harBilag, st.flags.mange], [true, true, false, false])
  assert.equal(st.data.newRow, '3', 'the last hidden bilf, not the spare line')

  // Every line has a document: the paper icon (kassekladde.php:3736)
  const withDocs = kkPage({ lines: [line(1), line(2)], balance: 'ok' })
  for (const tr of withDocs.querySelectorAll('tr')) tr.append(new El('td', { class: 'clip-cell clip-has-doc' }))
  st = detect(withDocs)
  eq([st.flags.udenBilag, st.flags.harBilag, st.data.newRow], [false, true, ''])

  // More than 15 saved lines
  const many = (k) => kkPage({ lines: Array.from({ length: k }, (_, i) => line(i + 1)), balance: 'diff' })
  assert.equal(detect(many(16)).flags.mange, true)
  assert.equal(detect(many(15)).flags.mange, false)

  // A posted journal: the grid's clips are outside form#kassekladde (kassekladde.php:3040, 2177-2207)
  const posted = kkPage({ lines: [line(1)], footer: 'posted' })
  posted.querySelector('body').append(new El('table').append(new El('tr').append(
    new El('td', { class: 'clip-cell clip-no-doc' }), new El('td', { class: 'clip-cell clip-has-doc' }))))
  st = detect(posted)
  assert.equal(st.state, 'bogfoert')
  eq([st.flags.udenBilag, st.flags.harBilag], [false, true], 'a posted line without a document is not udenBilag')

  // A refused save prints no clips (kassekladde.php:3719, !$fejl)
  st = detect(kkPage({ lines: [line(1)], footer: 'refused', balance: 'ok' }))
  eq([st.state, st.flags.udenBilag, st.flags.harBilag], ['afvist', false, false])
})
test('kassebilag: the order, Om siden and the questions follow the state', () => {
  const KB = C.pages['includes/documents.php']
  assert.equal(KB.group, 'kb')
  const ctx = stubCtx('includes/documents.php')
  const order = (state, flags = {}) => KB.order(ctx, { state, flags, data: {} })
  const pool = (flags = {}) => order('pulje', flags)

  // From a saved line (the clip in the kassekladde): attach first, a match group before that
  eq(pool({ fraLinje: true }), ['KB1', 'KB9', 'KB21', 'KB3', 'KB8', 'KB11', 'KB16'])
  eq(pool({ fraLinje: true, match: true }).slice(0, 3), ['KB2', 'KB1', 'KB9'])
  eq(pool(), ['KB1', 'KB9', 'KB21', 'KB3', 'KB8', 'KB11', 'KB16'], 'the state alone, as the digest calls it')
  // A new line (from the top, or after Gem og næste): one line per document, paid or bill, then upload or the reading check
  eq(pool({ nyLinje: true }), ['KB4', 'KB5', 'KB9', 'KB7', 'KB8', 'KB6', 'KB11', 'KB10', 'KB16'])
  eq(pool({ nyLinje: true, forslag: true }).slice(0, 3), ['KB4', 'KB5', 'KB6'])
  // What stops the save and the double-entry warnings come first
  eq(pool({ nyLinje: true, obligatorisk: true }).slice(0, 3), ['KB13', 'KB4', 'KB5'])
  eq(pool({ nyLinje: true, skaev: true }).slice(0, 3), ['KB7', 'KB4', 'KB5'])
  eq(pool({ nyLinje: true, dublet: true }).slice(0, 3), ['KB14', 'KB4', 'KB5'])
  eq(pool({ fraLinje: true, fakturaBrugt: true }).slice(0, 3), ['KB15', 'KB1', 'KB9'])
  eq(pool({ nyLinje: true, obligatorisk: true, skaev: true, dublet: true, fakturaBrugt: true }).slice(0, 5), ['KB13', 'KB7', 'KB14', 'KB15', 'KB4'])
  // Empty pool, done, locked, archive
  eq(pool({ tom: true, fraLinje: true }), ['KB9', 'KB10', 'KB16', 'KB21'])
  eq(pool({ tom: true, nyLinje: true }), ['KB9', 'KB10', 'KB16'], 'no link from another line on an unsaved line')
  eq(pool({ faerdig: true, nyLinje: true, tom: true }), ['KB16', 'KB9'])
  eq(pool({ laast: true, fraLinje: true, match: true }), ['KB24'], 'read-only: back without the save advice')
  eq(pool({ arkiv: true, fraLinje: true }), ['KB12', 'KB11', 'KB16'])
  // The viewer (Administrér bilag): move or unlink first, then add, link, back
  eq(order('bilag'), ['KB19', 'KB18', 'KB24'])
  eq(order('bilag', { kanLinke: true }), ['KB19', 'KB18', 'KB25', 'KB24'])
  eq(order('bilag', { flere: true, linket: true, kanLinke: true }), ['KB17', 'KB20', 'KB18', 'KB25', 'KB24'])
  eq(order('bilag', { laastBilag: true, kanLinke: true }), ['KB24'])
  eq(order('bilag', { laastBilag: true, flere: true }), ['KB17', 'KB24'])
  eq(order('bilag', { mangler: true, kanLinke: true }), ['KB24'], 'a missing file: Flyt til pulje would move nothing')
  eq(order('bilag', { mangler: true, flere: true, linket: true }), ['KB17', 'KB24'])
  eq(order('link'), ['KB22'])
  eq(order('ordrePulje'), ['KB23', 'KB9'])
  eq(order('ordreBilag'), [])
  eq(order('ordreBilag', { flere: true }), ['KB17'])
  for (const s of ['visning', 'klassisk', 'skifter', 'unknown']) eq(order(s), [])

  const about = (state, flags = {}) => KB.about(ctx, { state, flags, data: {} })
  eq(about('pulje', { fraLinje: true }), ['help.kb.about.pulje', 'help.kb.about.fra_linje'])
  eq(about('pulje', { nyLinje: true }), ['help.kb.about.pulje', 'help.kb.about.ny_linje'])
  eq(about('pulje', { nyLinje: true, tom: true }), ['help.kb.about.pulje_kort', 'help.kb.about.tom'], 'nothing to click in an empty pool')
  eq(about('pulje', { nyLinje: true, tom: true, faerdig: true }), ['help.kb.about.pulje_kort', 'help.kb.about.faerdig'])
  eq(about('pulje', { nyLinje: true, faerdig: true }), ['help.kb.about.pulje_kort', 'help.kb.about.faerdig'])
  eq(about('pulje', { laast: true, tom: true }), ['help.kb.about.pulje_kort', 'help.kb.about.laast'])
  eq(about('pulje', { laast: true, fraLinje: true }), ['help.kb.about.pulje', 'help.kb.about.laast'])
  eq(about('pulje', { arkiv: true, fraLinje: true }), ['help.kb.about.pulje', 'help.kb.about.arkiv'])
  eq(about('pulje', { fraLinje: true, match: true, dublet: true, obligatorisk: true }), ['help.kb.about.pulje', 'help.kb.about.fra_linje'],
    'the volatile list flags never change Om siden')
  eq(about('bilag'), ['help.kb.about.bilag'])
  eq(about('bilag', { linket: true }), ['help.kb.about.bilag', 'help.kb.about.bilag_linket'])
  eq(about('bilag', { laastBilag: true, linket: true }), ['help.kb.about.bilag', 'help.kb.about.bilag_laast'])
  eq(about('bilag', { mangler: true, laastBilag: true }), ['help.kb.about.bilag', 'help.kb.about.bilag_mangler'])
  eq(about('ordreBilag', { laastBilag: true, linket: true }), ['help.kb.about.ordre_bilag'])
  eq(about('ordreBilag', { mangler: true }), ['help.kb.about.ordre_bilag', 'help.kb.about.bilag_mangler'])
  eq(about('link'), ['help.kb.about.link'])
  eq(about('link', { tomListe: true }), ['help.kb.about.link', 'help.kb.about.link_ingen'])
  eq(about('visning'), ['help.kb.about.visning'])
  eq(about('ordrePulje'), ['help.kb.about.ordre_pulje'])
  eq(about('klassisk'), ['help.kb.about.klassisk'])
  eq(about('skifter'), ['help.kb.about.skifter'])
  eq(about('unknown'), [])
  for (const st of stCombos(KB.states, KB.flags).filter((s) => s.state !== 'unknown')) {
    const n = KB.about(ctx, st).length
    assert.ok(n >= 1 && n <= 2, 'Om siden has 1-2 paragraphs: ' + JSON.stringify(st))
  }

  const qo = (state, flags = {}) => KB.questionOrder(ctx, { state, flags, data: {} })
  eq(qo('pulje', { fraLinje: true }), ['Q1', 'Q2', 'Q8'])
  eq(qo('pulje', { nyLinje: true }), ['Q1', 'Q10', 'Q6'])
  eq(qo('pulje', { tom: true, nyLinje: true }), ['Q3', 'Q8', 'Q11'])
  eq(qo('pulje', { arkiv: true }), ['Q4', 'Q7', 'Q5'])
  eq(qo('pulje', { faerdig: true, tom: true }), ['Q4', 'Q8', 'Q7'], 'the pool is done: where the bilag went, not the day-one questions')
  eq(qo('bilag'), ['Q8', 'Q7', 'Q4'])
  eq(qo('bilag', { laastBilag: true }), ['Q7', 'Q8', 'Q4'], 'Låst: why the bilag stays')
  eq(qo('link'), ['Q4', 'Q8', 'Q7'])
  eq(qo('ordrePulje'), ['Q2', 'Q3', 'Q4'], 'an order: not Q1 and Q8, which name the kassekladde')
  eq(qo('ordreBilag'), ['Q7', 'Q3', 'Q4'])
  eq(qo('visning'), ['Q8', 'Q4', 'Q7'])

  assert.equal(KB.name(ctx, { state: 'pulje', flags: {}, data: {} }), 'help.kb.name')
  eq(KB.name(ctx, { state: 'pulje', flags: {}, data: { bilag: '7' } }), ['help.kb.name_bilag', '7'])
  assert.equal(KB.name(ctx, { state: 'ordrePulje', flags: {}, data: { ordre: true, bilag: '3' } }), 'help.kb.name_ordre')
  assert.equal(KB.signal(ctx, { state: 'pulje', flags: { obligatorisk: true }, data: {} }), false)

  // Reused steps and step counts
  eq(KB.tasks.KB25.steps, ['help.kb.task.kb25.step1', 'help.kb.task.kb21.step2', 'help.kb.task.kb21.step3', 'help.kb.task.kb21.step4'])
  eq(KB.questions.Q3.steps, ['help.kb.task.kb10.step1', 'help.kb.task.kb10.step2', 'help.kb.task.kb10.step3'])
  eq(KB.questions.Q5.steps, ['help.kb.task.kb14.step1', 'help.kb.task.kb14.step2', 'help.kb.task.kb14.step3'])
  assert.equal(KB.questions.Q5.intro, 'help.kb.task.kb14.why')
  assert.equal(KB.tasks.KB23.show.label, 'help.kb.task.kb1.show')
  eq(KB.questions.Q6.steps, ['help.kb.q.q6.step1', 'help.kb.q.q6.step2', 'help.kb.q.q6.step3'])
  eq(KB.questions.Q10.steps, ['help.kb.q.q10.step1', 'help.kb.q.q10.step2', 'help.kb.q.q10.step3'])
  eq(KB.questions.Q11.steps, ['help.kb.q.q11.step1', 'help.kb.q.q11.step2'])
  for (const id of ['Q10', 'Q11']) assert.ok(!KB.questions[id].show, id + ' points at nothing')
  for (const [id, n] of [['KB1', 3], ['KB2', 3], ['KB3', 4], ['KB4', 4], ['KB5', 3], ['KB6', 4], ['KB7', 4], ['KB8', 3], ['KB9', 3],
    ['KB10', 3], ['KB11', 4], ['KB12', 2], ['KB13', 4], ['KB14', 3], ['KB15', 4], ['KB16', 3], ['KB17', 1], ['KB18', 2], ['KB19', 3],
    ['KB20', 2], ['KB21', 4], ['KB22', 4], ['KB23', 2], ['KB24', 2], ['KB25', 4]]) {
    assert.equal(KB.tasks[id].steps.length, n, id + ' steps')
  }
  // Every field key the page maps to has a field entry
  for (const f of Object.values(KB.fields)) assert.match(f.label, /^help\.kb\.field\.[a-z_]+\.label$/)
})

test('kassebilag detect() gives the mode and the flags of each page', () => {
  const KB = C.pages['includes/documents.php']
  const detect = (doc, url = kbUrl()) => KB.detect(ctxFor(doc, url))

  // The pool from a saved line, the shown document a.pdf; a match group in the list
  let st = detect(kbPool({ rows: [{ file: 'a.pdf' }, { file: 'b.pdf', match: 'perfect' }] }))
  assert.equal(st.state, 'pulje')
  eq([st.flags.fraLinje, st.flags.nyLinje, st.flags.match, st.flags.laast, st.flags.tom, st.flags.kanLinke], [true, false, true, false, false, true])
  eq([st.flags.dublet, st.flags.fakturaBrugt, st.flags.forslag, st.flags.skaev, st.flags.obligatorisk, st.flags.faerdig],
    [false, false, false, false, false, false])
  assert.equal(st.data.bilag, '7', 'the voucher number at the top')
  assert.equal(st.data.source, 'kassekladde')
  assert.equal(detect(kbPool({ rows: [{ file: 'a.pdf', match: 'combination' }] })).flags.match, true)
  // The card view: the heading is a div and the cards carry no data-*-match
  st = detect(kbPool({ view: 'card', rows: [{ file: 'a.pdf' }, { file: 'b.pdf', match: 'amount' }] }))
  eq([st.flags.match, st.flags.tom], [true, false])
  assert.equal(detect(kbPool({ view: 'card', rows: [{ file: 'a.pdf' }] })).flags.match, false)

  // A new line
  st = detect(kbPool({ line: 'new', rows: [{ file: 'a.pdf' }] }), kbUrl('sourceId=0&poolFile=a.pdf'))
  eq([st.state, st.flags.nyLinje, st.flags.fraLinje], ['pulje', true, false])

  // The list: empty, still loading, empty behind a search, the archive
  assert.equal(detect(kbPool({ list: 'empty' })).flags.tom, true)
  assert.equal(detect(kbPool({ list: 'loading' })).flags.tom, false, 'Indlæser filer... is not an empty pool')
  st = detect(kbPool({ list: 'empty', search: 'kontor' }))
  eq([st.flags.tom, st.flags.soegning], [false, true])
  st = detect(kbPool({ list: 'empty', archived: true }))
  eq([st.flags.tom, st.flags.arkiv], [false, true])

  // Done: the last document saved (poolDone=1 and the note)
  st = detect(kbPool({ line: 'new', list: 'empty', done: true }), kbUrl('sourceId=0&poolDone=1'))
  eq([st.flags.faerdig, st.flags.nyLinje], [true, true])
  assert.equal(detect(kbPool({ line: 'new', list: 'empty', done: true }), kbUrl('sourceId=0')).flags.faerdig, true, 'the note alone')

  // A simulated or posted kladde: no Gem og næste
  assert.equal(detect(kbPool({ readOnly: true, rows: [{ file: 'a.pdf' }] })).flags.laast, true)

  // Mulig dublet only on the shown document (the last non-empty poolFile of the URL)
  const pair = { rows: [{ file: 'a.pdf', duplicate: true }, { file: 'c.pdf', duplicate: true }, { file: 'b.pdf' }] }
  assert.equal(detect(kbPool(pair), kbUrl('poolFile=&poolFile=a.pdf')).flags.dublet, true)
  assert.equal(detect(kbPool(pair), kbUrl('poolFile=b.pdf')).flags.dublet, false, 'a pair elsewhere in the pool')

  // The line's own marks: invoice number used, read values, mandatory fields
  assert.equal(detect(kbPool({ reuse: true, rows: [{ file: 'a.pdf' }] })).flags.fakturaBrugt, true)
  assert.equal(detect(kbPool({ read: true, rows: [{ file: 'a.pdf' }] })).flags.forslag, true)
  assert.equal(detect(kbPool({ mandatory: true, rows: [{ file: 'a.pdf' }] })).flags.obligatorisk, true)

  // Fordeling: off and shown with a split row, or after its warning; not a saved two-line bilag, not a hidden or balanced box
  assert.equal(detect(kbPool({ balance: 'off', splitRow: true })).flags.skaev, true)
  assert.equal(detect(kbPool({ balance: 'off', warning: 'Bilaget går ikke op: rest 250,00' })).flags.skaev, true)
  assert.equal(detect(kbPool({ balance: 'off' })).flags.skaev, false, 'bank and expense lines of one bilag')
  assert.equal(detect(kbPool({ balance: 'ok', splitRow: true })).flags.skaev, false)
  assert.equal(detect(kbPool({ balance: 'hidden-off', splitRow: true })).flags.skaev, false)

  // An order's pool: no kassebilag top bar
  st = detect(kbPool({ source: 'creditorOrder', rows: [{ file: 'a.pdf' }] }), 'https://x/asr_hj/includes/documents.php?source=creditorOrder&sourceId=55&ny=ja')
  eq([st.state, st.data.ordre, st.flags.fraLinje, st.flags.laast], ['ordrePulje', true, false, false])
  assert.equal(KB.name(ctxFor(new Doc(), kbUrl()), st), 'help.kb.name_ordre')

  // The viewer of a line's documents
  const VIEW = 'https://x/asr_hj/includes/documents.php?source=kassekladde&sourceId=101&kladde_id=12&fokus='
  st = detect(kbViewer(), VIEW)
  assert.equal(st.state, 'bilag')
  eq([st.flags.flere, st.flags.linket, st.flags.laastBilag, st.flags.mangler, st.flags.kanLinke], [false, false, false, false, true])
  assert.equal(st.data.bilag, '', 'no voucher number in this URL')
  st = detect(kbViewer({ docs: [{ name: 'a.pdf', action: 'delete' }, { name: 'b.pdf', action: 'unlink' }] }), VIEW)
  eq([st.flags.flere, st.flags.linket, st.flags.laastBilag], [true, true, false])
  st = detect(kbViewer({ docs: [{ name: 'a.pdf', action: 'locked' }, { name: 'b.pdf', action: 'locked' }] }), VIEW)
  eq([st.flags.laastBilag, st.flags.linket], [true, false])
  assert.equal(detect(kbViewer({ docs: [{ name: 'a.pdf', action: 'locked' }, { name: 'b.pdf', action: 'delete' }] }), VIEW).flags.laastBilag, false,
    'a document added within 24 hours can still be moved')
  assert.equal(detect(kbViewer({ shown: 'missing' }), VIEW).flags.mangler, true)
  assert.equal(detect(kbViewer({ shown: 'pre' }), VIEW).flags.mangler, false, 'raw XML is a shown document')
  st = detect(kbViewer({ link: false }), 'https://x/asr_hj/includes/documents.php?source=debitorOrdrer&sourceId=55')
  eq([st.state, st.data.ordre, st.flags.kanLinke], ['ordreBilag', true, false])
  assert.equal(detect(kbViewer({ docs: [], shown: null }), VIEW).state, 'skifter', 'Flyt til pulje prints only an alert in the viewer')

  // The link page, the view-only tab, the classic layout, a page between two others
  st = detect(kbLinkPage(false), VIEW + '&linkBilag=1&bilag=7')
  eq([st.state, st.flags.tomListe, st.data.bilag], ['link', false, '7'])
  assert.equal(detect(kbLinkPage(true), VIEW + '&linkBilag=1').flags.tomListe, true)
  const tab = new Doc()
  tab.append(new El('body').append(new El('div', { id: 'viewOnlyBar' })))
  assert.equal(detect(tab, VIEW + '&viewOnly=1').state, 'visning')
  const classic = new Doc()
  classic.append(new El('body').append(new El('button', { id: 'emailD' })))
  assert.equal(detect(classic, VIEW).state, 'klassisk')
  assert.equal(detect(new Doc(), VIEW).state, 'skifter')
  for (const s of ['pulje', 'ordrePulje', 'bilag', 'ordreBilag', 'link', 'visning', 'klassisk', 'skifter']) assert.ok(KB.states.includes(s), s)
})

test('kassebilag show targets find the element they name, and the pool fields get their keys', () => {
  const KB = C.pages['includes/documents.php']
  const resolveIn = (doc, url, show) => {
    const ctx = ctxFor(doc, url)
    const st = KB.detect(ctx)
    return typeof show.target === 'function' ? show.target(ctx, st) : doc.querySelector(show.target)
  }
  const T = KB.tasks
  let page = kbPool({ rows: [{ file: 'a.pdf', duplicate: true }, { file: 'b.pdf', match: 'amount', duplicate: true }], read: true, reuse: true, mandatory: true })
  const at = (show, url = kbUrl('poolFile=b.pdf')) => resolveIn(page, url, show)
  assert.equal(at(T.KB1.show).getAttribute('id'), 'fileListContainer')
  assert.match(at(T.KB2.show).getAttribute('onclick'), /^selectCombinationFiles\(/, 'the coloured heading of a match group')
  assert.match(resolveIn(kbPool({ view: 'card', rows: [{ file: 'b.pdf', match: 'perfect' }] }), kbUrl(), T.KB2.show).getAttribute('onclick'),
    /^selectCombinationFiles\(/, 'the card view heading')
  assert.equal(at(T.KB3.show).getAttribute('id'), 'transferDataBtn')
  assert.equal(at(T.KB4.show).getAttribute('id'), 'saveNextBtn')
  assert.match(at(T.KB6.show).getAttribute('class'), /pool-capture-badge/)
  assert.equal(at(T.KB7.show).getAttribute('id'), 'splitRowBtn')
  assert.equal(at(T.KB8.show).getAttribute('id'), 'row_101_Valuta')
  assert.equal(at(T.KB9.show).getAttribute('id'), 'dropZone')
  assert.equal(at(T.KB10.show).getAttribute('id'), 'uploadToggleHeader')
  assert.equal(at(T.KB11.show).getAttribute('id'), 'archiveSelectedBtn')
  assert.match(at(T.KB13.show).getAttribute('class'), /pool-mandatory/)
  assert.equal(at(T.KB14.show).parent.parent.getAttribute('data-pool-file'), 'b.pdf', "the shown document's own badge")
  assert.equal(at(T.KB14.show, kbUrl('poolFile=x.pdf')).parent.parent.getAttribute('data-pool-file'), 'a.pdf', 'else the first in the list')
  assert.match(at(T.KB15.show).getAttribute('class'), /invoice-reuse-note/)
  assert.equal(at(T.KB16.show).getAttribute('accesskey'), 'L')
  assert.match(at(T.KB21.show).getAttribute('href'), /linkBilag=1/)
  assert.equal(at(KB.questions.Q4.show).getAttribute('id'), 'poolSearchBox')

  page = kbPool({ upload: 'folded', line: 'new', list: 'empty', done: true })
  assert.equal(at(T.KB9.show).getAttribute('id'), 'uploadToggleHeader', 'the header opens a folded upload area')
  assert.equal(at(T.KB21.show).getAttribute('id'), 'uploadToggleHeader', 'the link is inside the folded area')
  assert.match(at(T.KB16.show).getAttribute('href'), /kassekladde\.php/, 'Tilbage til kassekladden in the note')
  assert.ok(!at(T.KB16.show).getAttribute('accesskey'))
  assert.equal(at(T.KB5.show).getAttribute('id'), 'row_new_KreditType')

  const VIEW = 'https://x/asr_hj/includes/documents.php?source=kassekladde&sourceId=101&kladde_id=12'
  page = kbViewer({ docs: [{ name: 'a.pdf', action: 'delete' }, { name: 'b.pdf', action: 'unlink' }] })
  const v = (show) => resolveIn(page, VIEW, show)
  assert.match(v(T.KB17.show).getAttribute('class'), /^doc-list-row$/, 'a row that is not shown')
  assert.match(v(T.KB18.show).getAttribute('href'), /openPool=1/)
  assert.match(v(T.KB19.show).getAttribute('href'), /moveDoc=/)
  assert.match(v(T.KB20.show).getAttribute('href'), /unlinkDoc=/)
  assert.match(v(T.KB25.show).getAttribute('href'), /linkBilag=1/)
  assert.equal(v(T.KB24.show).getAttribute('accesskey'), 'L')
  assert.equal(resolveIn(kbLinkPage(false), VIEW + '&linkBilag=1', T.KB22.show).getAttribute('id'), 'searchBilag')

  // fieldKey(): the row ids by their suffix, three camel-case ids, nothing else
  const key = (attrs) => KB.fieldKey(new El('input', attrs))
  eq(['row_new_KreditType', 'row_141_Amount', 'row_new2_DebetVat', 'row_101_Dato', 'row_new_Momsfri', 'row_101_Forfald'].map((id) => key({ id })),
    ['k_ty', 'amount', 'dvat', 'dato', 'momsfri', 'forfald'])
  assert.equal(key({ id: 'row_new_DebetName' }), null)
  assert.equal(key({ id: 'poolSearchBox' }), 'poolsearchbox')
  assert.equal(key({ id: 'autoExtractToggle' }), 'autoextracttoggle')
  assert.equal(key({ id: 'searchBilag' }), 'searchbilag')
  assert.equal(key({ name: 'debe0' }), null, 'no id: the shell falls back to the name')
  assert.equal(KB.fieldKey(null), null)
  for (const id of ['Bilag', 'Dato', 'Faktura', 'Beskrivelse', 'DebetType', 'Debet', 'DebetVat', 'KreditType', 'Kredit', 'KreditVat',
    'Amount', 'Afd', 'Projekt', 'Valuta', 'Momsfri', 'Forfald']) {
    const k = key({ id: 'row_new_' + id })
    assert.ok(KB.fields[k], id + ' -> ' + k + ' has field help')
  }
})

/** The Kassebilag pool's URL from a saved journal line, with extra query text. */
function kbUrl (extra = 'poolFile=a.pdf') {
  return 'https://ssl12.saldi.dk/asr_hj/includes/documents.php?openPool=1&kladde_id=12&bilag=7&fokus=&sourceId=101&source=kassekladde&' + extra
}

/**
 * The document pool (docPool.php:2097-2600, 3540-3700, 5215-5440). source: kassekladde gets #kassebilagTopBar.
 * line 'saved' or 'new'; list 'rows', 'empty' (the em) or 'loading'; rows: [{ file, match: perfect|amount|date|combination, duplicate }];
 * view 'table' or 'card' (docPoolViewMode);
 * balance: 'hidden' | 'ok' | 'off' | 'hidden-off' for #poolSplitBalance, splitRow: a Fordeling row, warning: its text.
 */
function kbPool ({ source = 'kassekladde', line = 'saved', readOnly = false, list = 'rows', rows = [], search = '', archived = false, done = false,
  reuse = false, read = false, mandatory = false, balance = 'hidden', splitRow = false, warning = '', upload = 'open', view = 'table' } = {}) {
  const doc = new Doc()
  const body = new El('body')
  doc.append(body)
  const header = new El('table', { id: 'topBarHeader' })
  header.append(new El('a', { href: '../finans/kassekladde.php?kladde_id=12&id=101&fokus=', accesskey: 'L' }))
  body.append(header)
  const pool = new El('div', { id: 'docPoolContainer' })
  body.append(pool)
  if (source === 'kassekladde') {
    const top = new El('div', { id: 'kassebilagTopBar' })
    pool.append(top)
    const nav = new El('div', { class: 'topbar-nav' })
    top.append(nav)
    if (!readOnly) nav.append(new El('a', { id: 'splitRowBtn' }), new El('a', { id: 'saveNextBtn' }), new El('a', { id: 'transferDataBtn' }))
    const box = new El('div', { id: 'bilagRowsContainer' })
    top.append(box)
    const id = line === 'new' ? 'new' : '101'
    const entry = new El('div', { class: 'kassebilag-entry', id: 'bilagEntry_' + id })
    box.append(entry)
    const field = new El('div', { class: 'topbar-field' })
    if (read) field.append(new El('label').append(new El('span', { class: 'pool-capture-badge' }, { text: 'Aflæst' })))
    field.append(new El('input', { type: 'text', id: 'row_' + id + '_Amount', class: mandatory ? 'pool-mandatory' : '', value: '1.250,00' }))
    entry.append(new El('input', { type: 'text', id: 'row_' + id + '_Bilag', value: '7' }), field,
      new El('input', { type: 'text', id: 'row_' + id + '_KreditType', name: 'k_ty0', value: line === 'new' ? 'K' : 'F' }),
      new El('input', { type: 'text', id: 'row_' + id + '_Valuta', value: '' }))
    if (reuse) entry.append(new El('div', { class: 'invoice-reuse-note' }))
    if (splitRow) box.append(new El('div', { class: 'kassebilag-entry', id: 'bilagEntry_new2', 'data-split': '1' }))
    const cls = balance === 'off' || balance === 'hidden-off' ? ' pool-split-off' : balance === 'ok' ? ' pool-split-ok' : ''
    const split = new El('div', { id: 'poolSplitBalance', class: 'pool-split-balance' + cls })
    split.style = { display: balance === 'hidden' || balance === 'hidden-off' ? 'none' : '' }
    split.append(new El('span', { class: 'pool-split-warning' }, { text: warning }))
    top.append(split)
  }
  const left = new El('div', { id: 'leftPanel' })
  pool.append(left)
  if (done) left.append(new El('div', { class: 'pool-done-note' }).append(new El('a', { href: '../finans/kassekladde.php?kladde_id=12' })))
  left.append(new El('input', { type: 'text', id: 'poolSearchBox', value: search }))
  const archive = new El('input', { type: 'checkbox', id: 'poolShowArchived' })
  archive.checked = archived
  left.append(archive, new El('button', { type: 'button', id: 'archiveSelectedBtn' }))
  const listBox = new El('div', { id: 'fileListContainer' }, { text: list === 'loading' ? 'Indlæser filer...' : '' })
  left.append(listBox)
  if (list === 'empty') listBox.append(new El('em', {}, { text: 'Ingen filer fundet.' }))
  // A match group gets its coloured heading, which ticks the group (docPool.php:3620-3681 table, 3811-3841 cards)
  const group = rows.filter((r) => r.match).map((r) => r.file)
  const heading = 'selectCombinationFiles(' + JSON.stringify(group) + ')'
  if (list === 'rows' && view === 'card') {
    if (group.length) listBox.append(new El('div', { onclick: heading }))
    for (const r of rows) listBox.append(new El('div', { class: 'doc-card-item', 'data-pool-file': r.file }))
  } else if (list === 'rows') {
    const tbody = new El('tbody')
    listBox.append(new El('table').append(tbody))
    if (group.length) tbody.append(new El('tr', { onclick: heading }).append(new El('td', { colspan: '6' })))
    for (const r of rows) {
      const attrs = { 'data-pool-file': r.file }
      if (r.match) attrs['data-' + r.match + '-match'] = 'true'
      const td = new El('td')
      if (r.duplicate) td.append(new El('span', { class: 'duplicate-badge' }))
      tbody.append(new El('tr', attrs).append(td))
    }
  }
  const content = new El('div', { id: 'uploadContent' })
  content.style = { maxHeight: upload === 'folded' ? '0px' : 'none' }
  content.append(new El('div', { id: 'dropZone' }))
  if (source === 'kassekladde') content.append(new El('a', { href: '../includes/documents.php?linkBilag=1&kladde_id=12&sourceId=101&source=kassekladde' }))
  left.append(new El('div', { id: 'uploadToggleHeader' }), content)
  return doc
}

/** The viewer of a line's documents (documents.php:691-972; listDocs.php:153-199). docs: [{ name, action: delete|unlink|locked }]. */
function kbViewer ({ docs = [{ name: 'a.pdf', action: 'delete' }], shown = 'iframe', link = true } = {}) {
  const doc = new Doc()
  const body = new El('body')
  doc.append(body)
  const header = new El('table', { id: 'topBarHeader' })
  header.append(new El('a', { href: '../finans/kassekladde.php?kladde_id=12&id=101&fokus=', accesskey: 'L' }))
  body.append(header)
  const viewer = new El('div', { id: 'docViewerContainer' })
  body.append(viewer)
  const content = new El('div', { id: 'leftPanelContent' })
  viewer.append(new El('div', { id: 'leftPanel' }).append(content))
  if (docs.length || shown !== null) {
    const tbody = new El('tbody')
    content.append(new El('table').append(tbody))
    docs.forEach((d, i) => {
      const td = new El('td')
      if (d.action === 'delete') td.append(new El('a', { href: 'documents.php?source=kassekladde&deleteDoc=' + d.name }), new El('a', { href: 'documents.php?source=kassekladde&moveDoc=' + d.name }))
      else if (d.action === 'unlink') td.append(new El('a', { href: 'documents.php?source=kassekladde&unlinkDoc=5' }), new El('span', { title: 'Kan ikke flytte linket bilag' }))
      else td.append(new El('span', {}, { text: 'Låst' }))
      tbody.append(new El('tr', { class: i === 0 ? 'doc-list-row current-doc' : 'doc-list-row' }).append(new El('td'), td))
    })
    content.append(new El('a', { href: 'documents.php?openPool=1&kladde_id=12&sourceId=101&source=kassekladde' }))
    if (link) content.append(new El('a', { href: 'documents.php?linkBilag=1&kladde_id=12&sourceId=101&source=kassekladde' }))
  }
  if (shown !== null) {
    const view = new El('div', { id: 'documentViewer' })
    viewer.append(new El('div', { id: 'rightPanel' }).append(view))
    if (shown === 'iframe') view.append(new El('div').append(new El('iframe')))
    if (shown === 'pre') view.append(new El('div').append(new El('pre')))
    if (shown === 'missing') view.append(new El('div').append(new El('div', {}, { text: 'Bilaget findes ikke på serveren' })))
  }
  return doc
}

/** Link bilag fra anden linje (documents.php:421-555). */
function kbLinkPage (empty) {
  const doc = new Doc()
  const body = new El('body')
  doc.append(body)
  body.append(new El('input', { type: 'text', id: 'searchBilag' }))
  const list = new El('div', { class: 'doc-list', id: 'bilagList' })
  body.append(list)
  if (empty) list.append(new El('div', { class: 'empty-msg' }))
  else list.append(new El('div', { class: 'doc-item' }).append(new El('a', { href: 'documents.php?doLink=1&linkDocId=9', class: 'link-btn' })))
  return doc
}

test('problem(): the states in which the panel shows a problem card first', () => {
  const pr = (route, state, flags = {}) => C.pages[route].problem(stubCtx(route), { state, flags, data: {} })
  assert.equal(pr('finans/kassekladde.php', 'afvist'), 'KK1')
  assert.equal(pr('finans/kassekladde.php', 'aaben', { hasSaved: true, manglerKonto: true }), 'KK2')
  assert.equal(pr('finans/kassekladde.php', 'aaben', { hasSaved: true, difference: true }), 'KK3')
  assert.equal(pr('finans/kassekladde.php', 'aaben', { hasSaved: true, balancerer: true }), '')
  assert.equal(pr('finans/kassekladde.php', 'ny'), '')
  assert.equal(pr('finans/bogfor.php', 'fejl', { difference: true }), 'BF6')
  assert.equal(pr('finans/bogfor.php', 'bekraeft', { bilagDiff: true }), 'BF7')
  assert.equal(pr('finans/bogfor.php', 'bekraeft'), '')
  assert.equal(pr('includes/documents.php', 'pulje', { nyLinje: true, obligatorisk: true }), 'KB13')
  assert.equal(pr('includes/documents.php', 'pulje', { nyLinje: true }), '')
  assert.equal(pr('finans/importer.php', 'fejl'), '')     // no task: Om siden says what to do
  assert.equal(C.pages['finans/kladdeliste.php'].problem, undefined)
})

test('Vis mig audit: the fixed targets ring the element their task is about (spec §21)', () => {
  const da = (k) => (tsv.get(k) || [])[1]
  const ring = (page, show, doc, url) => {
    const ctx = ctxFor(doc, url)
    const st = typeof page.detect === 'function' ? page.detect(ctx) : { state: null, flags: {}, data: {} }
    return typeof show.target === 'function' ? show.target.call(page, ctx, st) : doc.querySelector(show.target)
  }
  const docWith = (...kids) => {
    const doc = new Doc()
    doc.append(new El('body').append(...kids))
    return doc
  }

  // Kassekladde KK31: Bogfør with a note, not the clip (the owner's find)
  const kk31 = KK.tasks.KK31.show
  assert.equal(ring(KK, kk31, kkPage({ lines: [{ n: 1, id: '101', debe: '2450', kred: '6480', belo: '125,00' }], balance: 'ok', clip: true }), KK_URL).name, 'doPost')
  eq([da(kk31.label), kk31.kind, kk31.note], ['Bogfør', 'button', 'help.kk.task.kk31.note'])
  assert.match(da(kk31.note), /\*\*Bogfør\*\*/)
  eq([KK.tasks.KK17.show.label, KK.tasks.KK32.show.label, KK.questions.Q11.show.label],
    ['help.kk.field.debe.label', 'help.kk.field.debe.label', 'help.kk.field.dvat.label'])
  eq([KK.tasks.KK23.show.label, KK.tasks.KK23.show.kind], ['help.kk.field.ny_kladdenote.label', 'field'])

  // Kladdeliste: Q4 explains the model and rings nothing; Q6 starts with a new kladde
  const KL_LIST = 'https://ssl12.saldi.dk/asr_hj/finans/kladdeliste.php'
  assert.equal(KL.questions.Q4.show, undefined)
  assert.ok(KL.questions.Q6.show === KL.tasks.K2.show)
  const kl = klPage(['-'])
  kl.querySelector('body').append(new El('td', { id: 'create-new' }))
  assert.equal(ring(KL, KL.questions.Q6.show, kl, KL_LIST).getAttribute('id'), 'create-new')

  // Kontokort Q1: [1] creates the new account with Ny, not Lukket ([3])
  const KT = C.pages['systemdata/kontokort.php']
  const kt = docWith(new El('a', { class: 'st-btn', href: 'kontokort.php?settings=1', accesskey: 'N' }), new El('input', { type: 'checkbox', name: 'lukket' }))
  assert.equal(ring(KT, KT.questions.Q1.show, kt, 'https://ssl12.saldi.dk/asr_hj/systemdata/kontokort.php?id=7').getAttribute('accesskey'), 'N')
  assert.equal(da(KT.questions.Q1.show.label), 'Ny')

  // Rapporter: MS5 the period at the top of Momsafstemning, EM1 Fra dato
  const RP = C.pages['finans/rapport.php']
  const RP_URL = 'https://ssl12.saldi.dk/asr_hj/finans/rapport.php'
  // the firm name and the period are split by a <br>, so the text has no space before Periode (moms_afstemning.php:156-158)
  const period = new El('div', {}, { text: 'Nystart HjaelpPeriode: 01-01-2026 - 31-03-2026' })
  const ms = docWith(new El('div').append(period), new El('div', {}, { text: 'Genveje: Q1 Q2' }), new El('a', { accesskey: 'L', href: 'rapport.php?rapportart=moms_afstemning' }))
  const ms5 = RP.tasks.MS5.show
  const on = (state) => ({ state, flags: {}, data: {} })
  assert.ok(ms5.target({ doc: ms }, on('momsAfstemning')) === period, 'the innermost block with the period')
  eq([da(ms5.label), ms5.kind], ['Periode', 'status'])
  const form = new El('form', { action: 'rapport.php' }).append(new El('input', { type: 'date', id: 'from' }), new El('input', { type: 'date', id: 'to' }), new El('select', { name: 'land' }), new El('select', { name: 'view' }))
  assert.equal(RP.tasks.EM1.show.target({ doc: docWith(form) }, on('emballage')).getAttribute('id'), 'from')
  assert.equal(da(RP.tasks.EM1.show.label), 'Fra dato')
  // One page object serves every report, and the search offers every task: a report's own targets ring nothing on
  // another report (live: MR2 rang the Regnskab table as Nettobeloeb, KM1 the Kontokort head as Moms)
  const table = new El('table', { class: 'dataTable' }).append(new El('thead').append(new El('tr')))
  const datapg = new El('table', { id: 'datapg' }).append(new El('thead').append(new El('tr')))
  const report = docWith(table, new El('table', { class: 'dataTable' }), datapg, new El('div', { style: 'color:#b00000' }), new El('div').append(period))
  const own = { KU2: 'kontokortUbogfort', KM1: 'kontokortMoms', MS1: null, MS5: 'momsAfstemning', MR2: 'momsRubrik', OS2: 'momsOss', MT1: 'momsTransaktioner', EM1: null }
  for (const [id, state] of Object.entries(own)) {
    for (const other of ['regnskab', 'kontokort', 'momsangivelse']) assert.equal(RP.tasks[id].show.target({ doc: report }, on(other)), null, id + ' on ' + other)
    if (state) assert.ok(RP.tasks[id].show.target({ doc: report }, on(state)), id + ' on its own report')
  }

  // Kassekladde KK13: the undo arrow only, never a kladde number of the reversal pick (a click there writes the reversal)
  assert.equal(ring(KK, KK.tasks.KK13.show, chooseReversal(), KK_URL), null)
  assert.ok(KK.questions.Q3b.show === KK.tasks.KK13.show)

  // Udlign: AU1 and AU2 ring the list of open items, not Afregn
  const AU = C.pages['finans/autoudlign.php']
  const au = docWith(new El('div', { class: 'entry-card' }).append(new El('div', { class: 'candidates-wrap' }), new El('button', { id: 'udlignBtn' })))
  for (const id of ['AU1', 'AU2']) assert.match(au.querySelector(AU.tasks[id].show.target).getAttribute('class'), /candidates-wrap/, id)
  eq([da(AU.tasks.AU1.show.label), AU.tasks.AU1.show.kind], ['Åbne poster', 'status'])

  // Kladdens dokumenter Q2: [1] attaches the right document, not another document's Slet
  const JD = C.pages['finans/journalDocuments.php']
  assert.ok(JD.questions.Q2.show === JD.tasks.JD1.show)
  const jd = docWith(new El('form', { id: 'journalDocsUpload' }).append(new El('input', { type: 'file', name: 'uploadedFile' })),
    new El('div', { id: 'journalDocsList' }).append(new El('input', { type: 'submit', name: 'slet' })))
  assert.equal(jd.querySelector(JD.questions.Q2.show.target).getAttribute('name'), 'uploadedFile')

  // Kassebilag KB10 and Q3: the pool's mail address, the header only as the fallback
  const KB = C.pages['includes/documents.php']
  assert.ok(KB.questions.Q3.show === KB.tasks.KB10.show)
  const mail = new El('a', { href: 'mailto:bilag_test_838@ssl12.saldi.dk' })
  const kb = docWith(new El('div', { id: 'uploadToggleHeader' }).append(new El('span', {}, { text: 'Bilag kan sendes til' }), mail))
  assert.ok(KB.tasks.KB10.show.target({ doc: kb }) === mail)
  assert.equal(KB.tasks.KB10.show.target({ doc: docWith(new El('div', { id: 'uploadToggleHeader' })) }).getAttribute('id'), 'uploadToggleHeader')
})

// ------------------------------------------------------------------ Oversigt, the front page (index/dashboard.php)

const FSP = C.pages['index/dashboard.php']
const FS_URL = 'https://ssl12.saldi.dk/asr_hj/index/dashboard.php?inframe=1'

/**
 * Oversigt as index/dashboard.php prints it. o.revenue: figures other than 0 and the month chart; o.lastYear: the year's
 * figures 0 but the month chart (revenue last year only, dashboard.php:280); o.unpaid: undefined (no
 * access), 'none' (Ingen ubetalte salgsfakturaer) or { late, warn, ok, unapplied }; o.orders: uninvoiced orders; o.vat,
 * o.roles, o.pos: the VAT figure, the roles card, POS-muligheder; o.noKpis: a role without Nøgletal; o.gone: what is
 * switched off in Rediger oversigt; o.hidden: the overview hidden; o.empty: a role without blocks; o.drawer: Rediger
 * oversigt open.
 */
function fsPage (o = {}) {
  const doc = new Doc()
  const body = new El('body')
  doc.append(body)
  const dash = new El('div', { class: 'dash' })
  body.append(dash)
  if (o.roles) dash.append(new El('div', { class: 'db-notice' }).append(new El('a', { class: 'btn btn-sm btn-primary', href: '../systemdata/usersRoles.php?inframe=1&tab=users&status=review' })))
  const grid = new El('div', { class: 'grid' + (o.hidden ? ' all-hidden' : ''), id: 'grid' })
  dash.append(grid)
  const gone = (id) => ((o.gone || []).includes(id) ? ' gone' : '')
  const cell = (w, text, ...kids) => new El('div', { class: 'db-cell' + gone(w), 'data-widget': w }).append(new El('div', { class: 'db-val' }).append(new El('span', {}, { text })), ...kids)
  if (o.empty) {
    grid.append(new El('section', { class: 'blk db-sec db-empty' }).append(new El('div', { class: 'db-b' }).append(new El('div', { class: 'db-links' }).append(new El('a', { class: 'btn btn-sm btn-secondary', href: '../finans/kladdeliste.php' })))))
  } else {
    if (!o.noKpis) {
      const kpis = new El('section', { class: 'blk db-sec db-kpis', 'data-block': 'kpis' })
      for (const w of ['weekkpi', 'revmonth', 'revyear']) kpis.append(cell(w, o.revenue ? '12.500 kr' : '0 kr'))
      if (o.vat) kpis.append(cell('vatcount', '−25 kr', new El('div', { class: 'db-note' }).append(new El('a', { href: '../finans/rapport.php?rapportart=momsangivelse' }))))
      grid.append(kpis)
    }
    if (o.unpaid !== undefined) {
      const sec = new El('section', { class: 'blk db-sec' + gone('unpaid_invoices'), 'data-widget': 'unpaid_invoices', 'data-block': 'unpaid_invoices' })
      if (o.unpaid === 'none') {
        sec.append(new El('div', { class: 'db-b' }).append(new El('div', { class: 'db-ph db-ok' })))
      } else {
        const groups = new El('tbody')
        const list = new El('tbody')
        for (const g of ['late', 'warn', 'ok']) {
          groups.append(new El('tr', { 'data-g': g, tabindex: '0' }).append(new El('td'), new El('td', { class: 'dt-num' }, { text: String(o.unpaid[g] || 0) }), new El('td', { class: 'dt-num' }, { text: '0,00' })))
          for (let i = 0; i < (o.unpaid[g] || 0); i++) list.append(new El('tr', { 'data-g': g, 'data-href': '../debitor/ordre.php?id=' + g + i }))
        }
        const foot = new El('div', { class: 'db-unp-f' }).append(new El('a', { class: 'db-link', href: '../debitor/rapport.php?rapportart=openpost' }), new El('span', { class: 'db-grow' }))
        if (o.unpaid.unapplied) foot.append(new El('span').append(new El('b', {}, { text: '−1.250,00 kr' })), new El('a', { class: 'db-link', href: '../debitor/rapport.php?rapportart=openpost' }))
        sec.append(new El('div', { class: 'db-unp' }).append(
          new El('div', { class: 'db-unp-l' }).append(new El('table', { class: 'dt db-grp', id: 'unpGrp' }).append(groups)),
          new El('div', { class: 'db-unp-r' }).append(new El('table', { class: 'dt dt-fixed db-inv', id: 'unpTbl' }).append(list), foot)))
      }
      grid.append(sec)
    }
    grid.append(new El('section', { class: 'blk db-sec db-small', 'data-block': 'small' }).append(cell('ordercount', String(o.orders || 0),
      new El('div', { class: 'db-acts' }).append(new El('a', { class: 'btn btn-sm btn-secondary', href: '../debitor/ordreliste.php' })))))
    const tools = new El('div', { class: 'db-tools' })
    const chart = o.revenue || o.lastYear
    if (chart) tools.append(new El('button', { type: 'button', class: 'db-link', id: 'tblToggle' }), new El('select', { class: 'db-fld', id: 'cmpSel' }))
    grid.append(new El('section', { class: 'blk db-sec' + gone('revgraph'), 'data-widget': 'revgraph', 'data-block': 'revgraph' }).append(
      new El('div', { class: 'db-h' }).append(tools),
      new El('div', { class: 'db-b' }).append(chart ? new El('div', { class: 'db-chartbox', id: 'monthChart' }) : new El('div', { class: 'db-ph' }))))
    if (o.pos) grid.append(new El('section', { class: 'blk db-sec db-pos' }).append(new El('div', { class: 'db-b db-links' }).append(new El('a', { class: 'btn btn-sm btn-secondary', href: '../lager/varer.php?returside=../index/dashboard.php' }))))
  }
  dash.append(new El('div', { class: 'db-sec dash-hidden', id: 'dashHidden' }).append(new El('div', { class: 'db-ph' }).append(new El('button', { type: 'button', class: 'btn btn-sm btn-secondary', id: 'showDash' }))))
  body.append(new El('aside', { class: 'drawer' + (o.drawer ? ' open' : ''), id: 'drawer' }).append(new El('div', { class: 'body', id: 'drawerBody' }).append(
    new El('div', { class: 'blist' }),
    new El('div', { class: 'acct' }).append(new El('label').append(new El('input', { value: '0' })), new El('label').append(new El('input', { value: '2000' }))))))
  return doc
}

test('oversigt: detect() gives the state and the flags of each front page', () => {
  const st = (o) => FSP.detect(ctxFor(fsPage(o), FS_URL))
  const pick = (s, names) => [s.state, ...names.map((n) => s.flags[n])]
  // a new company (Nystart Hjaelp on ssl12): 0 kr, no unpaid invoice, no order, the VAT figure
  eq(pick(st({ unpaid: 'none', vat: true }), ['ingenOmsaetning', 'ingenUbetalte', 'moms', 'ubetalte', 'ordrer', 'roller', 'pos', 'ingenBlokke']),
    ['tom', true, true, true, false, false, false, false, false])
  eq(Object.keys(st({}).flags).sort(), [...FSP.flags].sort(), 'detect() gives every flag the page lists')
  // normal use: overdue invoices, an unapplied payment, orders to invoice, POS
  eq(pick(st({ revenue: true, unpaid: { late: 2, warn: 1, ok: 3, unapplied: true }, orders: 4, pos: true }), ['ubetalte', 'forfaldne', 'ikkeUdlignet', 'ordrer', 'pos', 'ingenOmsaetning', 'ingenUbetalte']),
    ['oversigt', true, true, true, true, true, false, false])
  eq(pick(st({ revenue: true, unpaid: { ok: 2 } }), ['ubetalte', 'forfaldne', 'ikkeUdlignet']), ['oversigt', true, false, false], 'not due yet is not overdue')
  eq(pick(st({ unpaid: { ok: 1 } }), ['ingenOmsaetning', 'ubetalte']), ['oversigt', true, true], 'no revenue, but an invoice to follow up')
  eq(pick(st({ orders: 2 }), ['ingenOmsaetning', 'ordrer']), ['oversigt', true, true], 'no revenue, but orders to invoice')
  // a card switched off in Rediger oversigt gives no flag
  eq(pick(st({ revenue: true, unpaid: { late: 1 }, orders: 2, vat: true, gone: ['unpaid_invoices', 'ordercount', 'vatcount'] }), ['ubetalte', 'forfaldne', 'ordrer', 'moms']),
    ['oversigt', false, false, false, false])
  // every block switched off: Nøgletal and Små tal count as off when all their cards are
  eq(pick(st({ gone: ['weekkpi', 'revmonth', 'revyear', 'ordercount', 'revgraph'] }), ['ingenBlokke']), ['tom', true])
  eq(pick(st({ gone: ['weekkpi', 'revmonth', 'ordercount', 'revgraph'] }), ['ingenBlokke']), ['tom', false], 'one key figure is still on')
  // the first days of a new year: 0 kr this year, last year's months in the chart; not a new company
  eq(pick(st({ lastYear: true, unpaid: 'none' }), ['ingenOmsaetning']), ['oversigt', false])
  // Nøgletal with a figure switched on
  eq(pick(st({ revenue: true }), ['noegletal']), ['oversigt', true])
  eq(pick(st({ revenue: true, gone: ['weekkpi', 'revmonth', 'revyear'] }), ['noegletal']), ['oversigt', false], 'every key figure switched off')
  eq(pick(st({ revenue: true, noKpis: true }), ['noegletal']), ['oversigt', false], 'a role without Nøgletal')
  // without Nøgletal the month chart tells whether there is revenue
  eq(pick(st({ noKpis: true, revenue: true }), ['ingenOmsaetning']), ['oversigt', false])
  eq(pick(st({ noKpis: true }), ['ingenOmsaetning']), ['tom', true])
  // the roles card for an administrator
  eq(pick(st({ roles: true, revenue: true }), ['roller']), ['oversigt', true])
  // hidden by the user, a role without key figures, another page
  assert.equal(st({ hidden: true, revenue: true }).state, 'skjult')
  assert.equal(st({ empty: true }).state, 'begraenset')
  assert.equal(FSP.detect(ctxFor(new Doc(), FS_URL)).state, 'unknown')
})

test('oversigt: the order, Om siden and the questions follow the state', () => {
  const ctx = stubCtx('index/dashboard.php')
  const order = (state, flags = {}) => FSP.order(ctx, { state, flags, data: {} })
  const k = { noegletal: true }
  eq(order('tom', k).slice(0, 3), ['FS2', 'FS1', 'FS7'], 'a new company: how figures get here, what they mean, where the work is')
  eq(order('tom', { ...k, roller: true }).slice(0, 3), ['FS11', 'FS2', 'FS1'])
  eq(order('tom', { ingenBlokke: true }).slice(0, 3), ['FS5', 'FS2', 'FS7'], 'every block off: no key figures to read')
  eq(order('oversigt', k).slice(0, 3), ['FS1', 'FS8', 'FS7'])
  eq(order('oversigt', { ...k, ubetalte: true }), ['FS1', 'FS8', 'FS3', 'FS7', 'FS5', 'FS6', 'FS9'])
  eq(order('oversigt', { ...k, ubetalte: true, forfaldne: true, ordrer: true }).slice(0, 3), ['FS3', 'FS4', 'FS1'])
  eq(order('oversigt', { ...k, ubetalte: true, ikkeUdlignet: true }).slice(0, 3), ['FS3', 'FS1', 'FS8'])
  eq(order('oversigt', { ...k, roller: true, ubetalte: true, forfaldne: true }).slice(0, 3), ['FS11', 'FS3', 'FS1'])
  eq(order('oversigt', { ubetalte: true, ordrer: true }).slice(0, 3), ['FS4', 'FS8', 'FS3'], 'a role without Nøgletal gets no FS1')
  eq(order('skjult'), ['FS10', 'FS7', 'FS9'], 'no Rediger oversigt in the menu of a page loaded hidden')
  eq(order('begraenset'), ['FS12', 'FS7', 'FS9'])
  eq(order('unknown'), [])
  const everywhere = new Set(['tom', 'oversigt', 'begraenset', 'skjult'].flatMap((s) => order(s, { roller: true, noegletal: true, ubetalte: true, forfaldne: true, ordrer: true })))
  eq([...everywhere].sort(), Object.keys(FSP.tasks).sort(), 'every task is in some order')
  assert.equal(Object.keys(FSP.tasks).length, 12)
  assert.equal(Object.keys(FSP.questions).length, 8)
  const about = (state) => FSP.about(ctx, { state, flags: {}, data: {} })
  eq(about('tom'), ['help.fs.about.forside', 'help.fs.about.tom'])
  eq(about('oversigt'), ['help.fs.about.forside', 'help.fs.about.kilde'])
  eq(about('begraenset'), ['help.fs.about.begraenset'])
  eq(about('skjult'), ['help.fs.about.skjult'])
  eq(about('unknown'), [])
  const qo = (state, flags = {}) => FSP.questionOrder(ctx, { state, flags, data: {} })
  eq(qo('tom'), ['Q1', 'Q4', 'Q8'])
  eq(qo('tom', { pos: true }), ['Q1', 'Q5', 'Q4'])
  eq(qo('tom', { moms: true }), ['Q1', 'Q7', 'Q4'])
  eq(qo('oversigt'), ['Q2', 'Q4', 'Q8'])
  eq(qo('oversigt', { moms: true, ubetalte: true }), ['Q2', 'Q7', 'Q3'], 'the revenue question before the VAT figure, then the unpaid card')
  eq(qo('oversigt', { ikkeUdlignet: true, ubetalte: true, moms: true }), ['Q3', 'Q2', 'Q7'])
  eq(qo('oversigt', { pos: true }), ['Q5', 'Q2', 'Q4'])
  eq(qo('begraenset'), ['Q8', 'Q5', 'Q4'])
  eq(qo('skjult'), ['Q4', 'Q8', 'Q5'], 'a hidden overview: where the messages are, not why it says 0 kr')
  eq(qo('unknown'), [])
  eq(FSP.questions.Q1.steps, ['help.fs.q.q1.step1', 'help.fs.task.fs2.step3', 'help.fs.q.q1.step3'])
  eq(FSP.questions.Q2.steps, ['help.fs.task.fs6.step2', 'help.fs.task.fs6.step3', 'help.fs.q.q2.step3', 'help.fs.q.q2.step4'])
  eq(FSP.questions.Q4.steps, ['help.fs.task.fs9.step1', 'help.fs.q.q4.step2', 'help.fs.task.fs9.step4'])
  assert.ok(FSP.questions.Q1.show === FSP.tasks.FS1.show)
  assert.equal(FSP.name(ctx, { state: 'tom', flags: {}, data: {} }), 'help.fs.name')
  assert.equal(FSP.problem, undefined, 'nothing on the front page stops the work')
  assert.equal(FSP.shortcutsNote, undefined, 'no access keys on the page')
})

test('oversigt: show targets find the element they name, and the fields get their keys', () => {
  const page = fsPage({ revenue: true, unpaid: { warn: 1, ok: 2, unapplied: true }, orders: 3, vat: true, roles: true, pos: true })
  const ctx = ctxFor(page, FS_URL)
  const st = FSP.detect(ctx)
  const find = (show) => (typeof show.target === 'function' ? show.target(ctx, st) : page.querySelector(show.target))
  assert.equal(find(FSP.tasks.FS1.show).getAttribute('data-block'), 'kpis')
  assert.equal(find(FSP.tasks.FS3.show).getAttribute('data-g'), 'warn', 'no invoice over 15 days: the 1-15 days group')
  assert.equal(find(FSP.tasks.FS4.show).getAttribute('href'), '../debitor/ordreliste.php')
  assert.ok(find(FSP.tasks.FS11.show).getAttribute('href').includes('usersRoles.php'))
  assert.ok(find(FSP.questions.Q3.show) === page.querySelectorAll('.db-unp-f a')[1], 'Udlign, not Vis alle')
  assert.ok(find(FSP.questions.Q5.show))
  assert.equal(find(FSP.questions.Q6.show).getAttribute('id'), 'tblToggle')
  assert.ok(find(FSP.questions.Q7.show).getAttribute('href').includes('momsangivelse'))
  for (const id of ['FS2', 'FS7', 'FS8', 'FS9']) assert.equal(FSP.tasks[id].show, undefined, id + ': the sidebar, the bell and the user menu are in the shell')
  // Rediger oversigt: its list and its fields only while it is open
  assert.equal(find(FSP.tasks.FS5.show), null)
  assert.equal(find(FSP.tasks.FS6.show), null)
  const open = fsPage({ revenue: true, drawer: true })
  assert.ok(open.querySelector(FSP.tasks.FS5.show.target))
  const inputs = open.querySelectorAll('#drawer .acct input')
  assert.ok(open.querySelector(FSP.tasks.FS6.show.target) === inputs[0], 'Fra kontonr.')
  eq(inputs.map((el) => FSP.fieldKey(el)), ['kontomin', 'kontomaks'])
  eq(['cmpSel', 'weekSel', 'heatSel'].map((id) => FSP.fieldKey(new El('select', { id }))), ['sammenlign', 'uge', 'periode'])
  assert.equal(FSP.fieldKey(new El('input', { name: 'x' })), null)
  for (const k of ['sammenlign', 'uge', 'periode', 'kontomin', 'kontomaks']) assert.ok(FSP.fields[k], k)
  // the most urgent group first; Udlign only with unapplied payments; no unpaid card, no target
  const late = ctxFor(fsPage({ revenue: true, unpaid: { late: 1, ok: 1 } }), FS_URL)
  assert.equal(FSP.tasks.FS3.show.target(late).getAttribute('data-g'), 'late')
  assert.equal(FSP.questions.Q3.show.target(late), null)
  const none = ctxFor(fsPage({ unpaid: 'none' }), FS_URL)
  assert.equal(FSP.tasks.FS3.show.target(none), null)
  // hidden, and a role without key figures
  assert.ok(fsPage({ hidden: true }).querySelector(FSP.tasks.FS10.show.target))
  assert.ok(fsPage({ empty: true }).querySelector(FSP.tasks.FS12.show.target))
  assert.equal(fsPage({ revenue: true }).querySelector(FSP.tasks.FS12.show.target), null)
})

test('D1 ordreliste: detect() gives the tab, the list mode and the flags', () => {
  const OL = C.pages['debitor/ordreliste.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/debitor/ordreliste.php'
  /** An order list: the grid ordrelst_<tab> (grid_order.php:1393-1470), the tabs and the bottom bar (ordreliste.php:2667-2785). */
  function olPage (tab, { rows = 2, tilbudTab = true, search = '', red = false, kunde = false, kolonner = false, massefakt = false, shop = false, oCol = true } = {}) {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    if (tilbudTab && tab !== 'tilbud') body.append(new El('a', { href: 'ordreliste.php?valg=tilbud' }))
    if (kunde) body.append(new El('a', { href: 'debitorkort.php?id=7', accesskey: 'L' }))
    body.append(new El('button', { id: 'ny' }))
    const wrapper = new El('div', { id: 'datatable-wrapper-ordrelst_' + tab, class: 'datatable-wrapper' })
    body.append(wrapper)
    const form = new El('form')
    wrapper.append(form)
    const table = new El('table', { id: 'datatable-ordrelst_' + tab })
    form.append(table)
    if (kolonner) {
      form.append(new El('button', {}, { text: 'Gem' }))
    } else {
      const thead = new El('thead')
      thead.append(new El('input', { type: 'text', name: 'search[ordrelst_' + tab + '][firmanavn]', value: search }))
      thead.append(new El('button', { type: 'button', class: 'dropdown-trigger' }))
      table.append(thead)
      const tbody = new El('tbody')
      table.append(tbody)
      for (let i = 0; i < rows; i++) {
        const tr = new El('tr')
        tr.append(new El('td').append(new El('a', { href: 'ordre.php?tjek=' + i + '&id=' + i + '&valg=' + tab })))
        tr.append(new El('td', red && i === 0 ? { style: 'color: #FF0000;', title: 'Ikke udlignet' } : {}))
        if (oCol) tr.append(new El('td').append(new El('input', { type: 'checkbox', name: 'checked[' + i + ']', class: 'deliveryNoteSelect' })))
        tbody.append(tr)
      }
      if (rows === 0) tbody.append(new El('tr', { class: 'no-rows-row' }))
      tbody.append(new El('tr', { class: 'filler-row' }))
    }
    const left = new El('div', { id: 'left-controls' })
    body.append(left)
    if (tab === 'ordrer') left.append(new El('button', { type: 'button', onclick: "location.href='ordreliste.php?vis_lagerstatus=on&valg=ordrer'" }))
    if (massefakt) left.append(new El('a', { href: 'massefakt.php?valg=ordrer' }))
    if (shop) left.append(new El('a', { href: 'ordreliste.php?hent_nu=1&valg=ordrer' }))
    const bulk = new El('form', { id: 'bulkActionForm' })
    body.append(bulk)
    bulk.append(new El('input', { type: 'hidden', name: 'valg', value: tab }))
    if (tab === 'faktura') {
      for (const v of ['Udskriv', 'Følgeseddel', 'Genfakturer', 'Send mails']) bulk.append(new El('input', { type: 'submit', name: 'submit', value: v }))
    } else {
      bulk.append(new El('input', { type: 'submit', name: 'slet_valgte', value: 'Slet' }))
      if (tab === 'ordrer') bulk.append(new El('input', { type: 'submit', name: 'submit', value: 'Ret' }))
    }
    bulk.append(new El('input', { type: 'submit', name: 'clear', value: 'Ryd' }))
    return doc
  }
  const det = (doc, url = URL0) => OL.detect(ctxFor(doc, url))

  let st = det(olPage('ordrer'))
  assert.equal(st.state, 'ordrer')
  eq(st.flags, { tom: false, soegning: false, kunde: false, lagerstatus: false, hurtigfakt: false, ikkeUdlignet: false, massefakt: false, shop: false, skjulteKolonner: false })
  // Live (test_838): a column setup saved before the O column existed hides O and Handlinger (grid_order.php merge_column_setup())
  assert.equal(det(olPage('faktura', { oCol: false })).flags.skjulteKolonner, true)
  assert.equal(det(olPage('faktura', { rows: 0, oCol: false })).flags.skjulteKolonner, false)
  assert.equal(st.data.rows, 2)
  // No Tilbud tab: hurtigfakturering (topLine.php:51-60)
  assert.equal(det(olPage('ordrer', { tilbudTab: false })).flags.hurtigfakt, true)
  assert.equal(det(olPage('tilbud', { tilbudTab: false })).flags.hurtigfakt, false)
  // Empty, searching, from a customer card, stock status, mass invoicing and shop
  st = det(olPage('ordrer', { rows: 0, search: 'zz' }))
  assert.equal(st.flags.tom, true)
  assert.equal(st.flags.soegning, true)
  assert.equal(det(olPage('faktura', { kunde: true })).flags.kunde, true)
  assert.equal(det(olPage('ordrer'), URL0 + '?vis_lagerstatus=on&valg=ordrer').flags.lagerstatus, true)
  st = det(olPage('ordrer', { massefakt: true, shop: true }))
  assert.equal(st.flags.massefakt, true)
  assert.equal(st.flags.shop, true)
  // A red sum on Fakturaer (ordreliste.php:1153-1166)
  st = det(olPage('faktura', { red: true }))
  assert.equal(st.state, 'faktura')
  assert.equal(st.flags.ikkeUdlignet, true)
  assert.equal(det(olPage('faktura')).flags.ikkeUdlignet, false)
  // Kolonner/Filtre replace the table (grid_order.php:1786-1810)
  assert.equal(det(olPage('ordrer', { kolonner: true })).state, 'opsaetning')
  // The BS tab prints pbsliste.php instead of the grid (topLine.php:94-108)
  const bs = new Doc()
  bs.append(new El('body').append(new El('a', { href: 'pbs_import.php?returside=ordreliste.php' }).append(new El('button')), new El('a', { href: 'pbsfile.php?id=3' }), new El('a', { href: 'pbsfile.php?id=3&afslut=ok' })))
  assert.equal(det(bs, URL0 + '?valg=pbs').state, 'bs')
  assert.equal(det(new Doc()).state, 'unknown')
})

test('D1 ordreliste: order, Om siden and the targets follow the tab', () => {
  const OL = C.pages['debitor/ordreliste.php']
  const ctx = stubCtx('debitor/ordreliste.php')
  const o = (state, flags = {}) => OL.order(ctx, { state, flags, data: {} }).slice(0, 3)
  eq(o('ordrer', { hurtigfakt: true }), ['OL4', 'OL1', 'OL3'])
  eq(o('ordrer', { tom: true }), ['OL1', 'OL3', 'OL10'])
  eq(o('tilbud'), ['OL2', 'OL16', 'OL3'])
  eq(o('faktura'), ['OL3', 'OL5', 'OL7'])
  eq(o('faktura', { ikkeUdlignet: true }), ['OL6', 'OL3', 'OL5'])
  eq(o('faktura', { tom: true, soegning: true }), ['OL13', 'OL3', 'OL1'])
  // A customer's own list is not a search to clear
  assert.ok(!OL.order(ctx, { state: 'ordrer', flags: { kunde: true, soegning: true }, data: {} }).includes('OL13'))
  eq(o('bs'), ['OL17', 'OL18', 'OL19'])
  eq(o('opsaetning'), ['OL20', 'OL10'])
  eq(OL.about(ctx, { state: 'ordrer', flags: { hurtigfakt: true }, data: {} }), ['help.dol.about.ordrer_hurtig'])
  eq(OL.about(ctx, { state: 'faktura', flags: { ikkeUdlignet: true }, data: {} }), ['help.dol.about.faktura', 'help.dol.about.ikke_udlignet'])
  eq(OL.about(ctx, { state: 'tilbud', flags: { tom: true, soegning: true }, data: {} }), ['help.dol.about.soegning_tom'])
  eq(OL.about(ctx, { state: 'tilbud', flags: { tom: true, soegning: true, kunde: true }, data: {} }), ['help.dol.about.tom_tilbud', 'help.dol.about.kunde'])
  eq(OL.about(ctx, { state: 'faktura', flags: { skjulteKolonner: true }, data: {} }), ['help.dol.about.faktura', 'help.dol.about.skjulte_kolonner'])
  eq(OL.about(ctx, { state: 'faktura', flags: { ikkeUdlignet: true, skjulteKolonner: true }, data: {} }), ['help.dol.about.faktura', 'help.dol.about.ikke_udlignet'])
  // The start screen's questions (spec §25): the tab's own first, the missing tilbud tab only with hurtigfakturering,
  // red sums when there are some, the hidden mass-action columns while they block; a customer's own list is no search
  const q = (state, flags = {}) => OL.questionOrder(ctx, { state, flags, data: {} })
  eq(q('tilbud'), ['Q4', 'Q5', 'Q3'])
  eq(q('ordrer'), ['Q4', 'Q5', 'Q3'])
  eq(q('ordrer', { hurtigfakt: true }), ['Q4', 'Q1', 'Q5'])
  eq(q('faktura'), ['Q6', 'Q3', 'Q2'])
  eq(q('faktura', { ikkeUdlignet: true }), ['Q2', 'Q6', 'Q3'])
  eq(q('faktura', { kunde: true }), ['Q6', 'Q4', 'Q2'])
  eq(q('ordrer', { skjulteKolonner: true, hurtigfakt: true }), ['Q7', 'Q4', 'Q5'])
  eq(q('opsaetning'), ['Q7', 'Q4', 'Q3'])
  // Slet on the order needs an order without lines; the list's Slet and its refusal are the answer
  eq(OL.questions.Q5.steps, ['help.dol.q.q5.step1', 'help.dol.q.q5.step2', 'help.dol.task.ol11.step3', 'help.dol.q.q5.step4'])

  // Udskriv of the bulk form only on Fakturaer, where it is the first submit; a row only on its own tab
  function bulkPage (tab) {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    const w = new El('div', { id: 'datatable-wrapper-ordrelst_' + tab })
    const tbody = new El('tbody')
    w.append(new El('table', { id: 'datatable-ordrelst_' + tab }).append(new El('thead'), tbody))
    tbody.append(new El('tr', { id: 'row1' }).append(new El('td').append(new El('a', { href: 'ordre.php?tjek=1&id=1' }))))
    body.append(w)
    const bulk = new El('form', { id: 'bulkActionForm' })
    body.append(bulk)
    bulk.append(new El('input', { type: 'submit', name: 'submit', value: tab === 'faktura' ? 'Udskriv' : 'Ret' }))
    return doc
  }
  const fak = bulkPage('faktura')
  assert.equal(OL.tasks.OL5.show.target(ctxFor(fak, 'https://x/debitor/ordreliste.php')).getAttribute('value'), 'Udskriv')
  assert.equal(OL.tasks.OL5.show.target(ctxFor(bulkPage('ordrer'), 'https://x/debitor/ordreliste.php')), null)
  assert.equal(OL.tasks.OL7.show.target(ctxFor(fak, 'https://x/debitor/ordreliste.php')).getAttribute('id'), 'row1')
  assert.equal(OL.tasks.OL4.show.target(ctxFor(fak, 'https://x/debitor/ordreliste.php')), null)
  assert.equal(OL.tasks.OL16.show.target(ctxFor(fak, 'https://x/debitor/ordreliste.php')), null)
  // Live: in the shell the row links are rewritten to main.php#/debitor/ordre.php?... (includes/order-row-clickable.js.php)
  const shell = new Doc()
  const sb = new El('body')
  shell.append(sb)
  const stb = new El('tbody')
  sb.append(new El('div', { id: 'datatable-wrapper-ordrelst_ordrer' }).append(new El('table', { id: 'datatable-ordrelst_ordrer' }).append(new El('thead'), stb)))
  stb.append(new El('tr', { id: 'row5', class: 'order-row-link' }).append(new El('td').append(new El('a', { href: '/asr_hj/index/main.php#/debitor/ordre.php?tjek=5&id=5&valg=ordrer', class: 'order-row-cell-link' }))))
  assert.equal(OL.tasks.OL4.show.target(ctxFor(shell, 'https://x/debitor/ordreliste.php')).getAttribute('id'), 'row5')
})

test('D1 ordre: detect() tells a new order, the lookup, a quote, an order, a credit note and an invoice apart', () => {
  const OR = C.pages['debitor/ordre.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/debitor/ordre.php?id=5'
  /** An order page: form[name="ordre"] with its first hidden status, id and art (ordre.php:3983-3986, :4796-4799). */
  function orPage ({ id = '5', status = '1', art = 'DO', ordrenr = '12', fakturanr = '', hurtig = true, godkend = false, linjer = 2,
    buttons = ['save', 'lookUp', 'doInvoice', 'print', 'copy', 'delete'], kredit = '', delOrdre = false, udskriv = 'PDF', ean = '',
    momssats = '25,00', kreditloft = false, sync = false, titleLink = false } = {}) {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    if (titleLink) body.append(new El('a', { style: 'color:white;', href: 'ordre.php?id=4' }))
    const form = new El('form', { name: 'ordre' })
    body.append(form)
    form.append(new El('input', { type: 'hidden', name: 'ordrenr', value: ordrenr }))
    form.append(new El('input', { type: 'hidden', name: 'status', value: status }))
    form.append(new El('input', { type: 'hidden', name: 'id', value: id }))
    form.append(new El('input', { type: 'hidden', name: 'art', value: art }))
    if (fakturanr) form.append(new El('input', { type: 'hidden', name: 'fakturanr', value: fakturanr }))
    form.append(new El('input', { type: 'text', name: 'kontonr', value: id ? '1000' : '', ...(id ? { readonly: 'readonly' } : {}) }))
    form.append(new El('input', { type: 'text', name: 'ean', value: ean }))
    form.append(new El('input', { type: 'text', name: 'momssats', value: momssats }))
    const sel = new El('select', { name: 'udskriv_til', value: udskriv })
    form.append(sel)
    if (!hurtig) form.append(new El('input', { type: 'text', name: 'levdato', value: '' }))
    if (godkend) form.append(new El('input', { type: 'checkbox', name: 'godkend' }))
    if (sync) form.append(new El('a', { href: 'sync_stamkort.php?konto_id=7&ordre_id=5&retning=ned' }))
    if (Number(status) < 3 && id) form.append(new El('input', { type: 'hidden', name: 'linjeantal', value: String(linjer) }))
    for (let x = 1; x <= linjer; x++) form.append(new El('tr', { class: 'ordrelinje', 'data-line-id': String(x) }))
    for (const b of buttons) form.append(new El('input', { type: 'submit', name: b }))
    if (kredit) form.append(new El('input', { type: 'submit', name: 'b_submit', value: kredit }))
    if (delOrdre) form.append(new El('input', { type: 'submit', name: 'b_submit', value: 'Del ordre' }))
    if (Number(status) >= 3) form.append(new El('input', { type: 'submit', name: 'b_submit', value: 'OK' }))
    if (kreditloft) body.append(new El('big').append(new El('span', { style: "color:#FF0000';" })))
    return doc
  }
  const det = (doc, extra) => OR.detect(ctxFor(doc, URL0, extra))

  // No id: the customer is chosen first (ordre.php:870-900)
  let st = det(orPage({ id: '', status: '0', linjer: 0, buttons: ['save', 'lookUp', 'copy'] }))
  assert.equal(st.state, 'ny')
  assert.equal(OR.name(stubCtx('debitor/ordre.php'), st), 'help.dord.name_ny')
  // Live: Ny opens ordre.php?ordre_id=0, whose form prints id 0
  {
    const nyDoc = orPage({ id: '0', status: '0', linjer: 0, buttons: ['save', 'lookUp', 'copy'] })
    nyDoc.querySelector('form').append(new El('input', { type: 'button', id: 'debitorkort' }))
    const nyCtx = ctxFor(nyDoc, URL0)
    const nySt = OR.detect(nyCtx)
    assert.equal(nySt.state, 'ny')
    assert.equal(OR.tasks.OR14.show.target(nyCtx, nySt), null, 'nothing to copy on a new order')
    assert.equal(OR.tasks.OR23.show.target(nyCtx, nySt), null, 'no customer card yet')
    const oDoc = orPage({})
    oDoc.querySelector('form').append(new El('input', { type: 'button', id: 'debitorkort' }))
    const oCtx = ctxFor(oDoc, URL0)
    assert.equal(OR.tasks.OR14.show.target(oCtx, OR.detect(oCtx)).getAttribute('name'), 'copy')
    assert.equal(OR.tasks.OR23.show.target(oCtx, OR.detect(oCtx)).getAttribute('id'), 'debitorkort')
  }
  // Live: quick invoicing leaves the order at status 0 when its line is added; Fakturér comes with the next Gem
  st = det(orPage({ status: '0', linjer: 1, buttons: ['save', 'lookUp', 'print', 'copy'] }))
  assert.equal(st.state, 'ordre')
  assert.equal(st.flags.gemFoerst, true)
  assert.equal(st.flags.kanFaktureres, false)
  eq(OR.about(stubCtx('debitor/ordre.php'), st), ['help.dord.about.ordre_hurtig', 'help.dord.about.gem_faktura'])
  eq(OR.order(stubCtx('debitor/ordre.php'), st).slice(0, 3), ['OR6', 'OR4', 'OR11'])
  assert.equal(det(orPage({ status: '1', linjer: 1 })).flags.gemFoerst, false)
  assert.equal(det(orPage({ status: '0', linjer: 0, buttons: ['save', 'lookUp'] })).flags.gemFoerst, false)
  st = det(orPage({ art: 'DK', status: '0', linjer: 1, buttons: ['save', 'lookUp'] }))
  assert.equal(st.state, 'kreditnota')
  assert.equal(st.flags.gemFoerst, true)
  eq(OR.about(stubCtx('debitor/ordre.php'), st), ['help.dord.about.kreditnota', 'help.dord.about.gem_kreditnota'])
  // The lookup (accountLookup.php:110-306) with its create form (:475-540)
  const look = new Doc()
  look.append(new El('body').append(new El('table', { id: 'datatable-accountLkUp_0' }).append(new El('input', { type: 'text', name: 'search[accountLkUp_0][firmanavn]' })),
    new El('div', { id: 'createCustomerForm' }).append(new El('form', { name: 'create_debtor' }).append(new El('input', { type: 'submit', name: 'create_debtor' })))))
  assert.equal(det(look).state, 'kundeopslag')
  // A quote has Godkend (:5349); an order without it; the Lev. dato input is gone with hurtigfakturering (:5124-5131)
  st = det(orPage({ status: '0', godkend: true, hurtig: false, buttons: ['save', 'lookUp', 'print', 'copy', 'delete'] }))
  assert.equal(st.state, 'tilbud')
  assert.equal(st.flags.hurtigfakt, false)
  eq(OR.name(stubCtx('debitor/ordre.php'), st), ['help.dord.name_tilbud', '12'])
  st = det(orPage({}))
  assert.equal(st.state, 'ordre')
  assert.equal(st.flags.hurtigfakt, true)
  assert.equal(st.flags.kanFaktureres, true)
  assert.equal(st.flags.kanSlettes, true)
  assert.equal(st.flags.ingenLinjer, false)
  assert.equal(st.data.linjer, 2)
  st = det(orPage({ linjer: 0 }))
  assert.equal(st.flags.ingenLinjer, true)
  st = det(orPage({ hurtig: false, buttons: ['save', 'lookUp', 'deliver', 'print', 'copy'] }))
  assert.equal(st.flags.kanLeveres, true)
  assert.equal(st.flags.kanFaktureres, false)
  assert.equal(det(orPage({ hurtig: false, buttons: ['save', 'lookUp'], delOrdre: true })).flags.delOrdre, true)
  // E-mail, Digitalt and an EAN customer; a VAT rate of 0 with lines; the credit limit; the card arrows; unsaved changes
  assert.equal(det(orPage({ udskriv: 'email' })).flags.email, true)
  st = det(orPage({ ean: '5798000000000', udskriv: 'Digitalt' }))
  assert.equal(st.flags.ean, true)
  assert.equal(st.flags.digital, true)
  assert.equal(det(orPage({ momssats: '0,00' })).flags.nulMoms, true)
  assert.equal(det(orPage({ momssats: '0,00', linjer: 0 })).flags.nulMoms, false)
  assert.equal(det(orPage({ kreditloft: true })).flags.kreditloft, true)
  assert.equal(det(orPage({ sync: true })).flags.afvigerFraKort, true)
  assert.equal(det(orPage({}), { docChange: true }).flags.ikkeGemt, true)
  // An open credit note has Kreditér (hard-coded, :6492)
  st = det(orPage({ art: 'DK', status: '1', buttons: ['save', 'lookUp', 'print', 'delete'], kredit: 'Kreditér', titleLink: true }))
  assert.equal(st.state, 'kreditnota')
  assert.equal(st.flags.kanFaktureres, true)
  assert.equal(st.flags.forbundet, true)
  eq(OR.name(stubCtx('debitor/ordre.php'), st), ['help.dord.name_kn', '12'])
  // Invoiced: Kredit until credited (:4688-4692); status 3 is not posted yet (ordrefunc.php:1707, :3280)
  st = det(orPage({ status: '4', fakturanr: '1043', buttons: ['copy', 'print'], kredit: 'Kredit' }))
  assert.equal(st.state, 'faktura')
  assert.equal(st.flags.krediteret, false)
  assert.equal(st.flags.ikkeBogfoert, false)
  eq(OR.name(stubCtx('debitor/ordre.php'), st), ['help.dord.name_faktura', '1043'])
  st = det(orPage({ status: '3', buttons: ['copy', 'print'], titleLink: true }))
  assert.equal(st.flags.krediteret, true)
  assert.equal(st.flags.ikkeBogfoert, true)
  assert.equal(det(orPage({ status: '4', art: 'DK', buttons: ['print'] })).state, 'kreditnotaFaktureret')
  assert.equal(det(new Doc()).state, 'unknown')
})

test('D1 ordre: the next step comes first, a credit limit is the problem card, and the targets stay in their state', () => {
  const OR = C.pages['debitor/ordre.php']
  const ctx = stubCtx('debitor/ordre.php')
  const o = (state, flags = {}) => OR.order(ctx, { state, flags, data: {} }).slice(0, 3)
  eq(o('ny'), ['OR1', 'OR2'])
  eq(o('kundeopslag', { opret: true }), ['OR2', 'OR3'])
  eq(o('tilbud'), ['OR7', 'OR8', 'OR4'])
  eq(o('ordre', { hurtigfakt: true, kanFaktureres: true }), ['OR6', 'OR4', 'OR11'])
  eq(o('ordre', { hurtigfakt: true, ingenLinjer: true, kanFaktureres: true }), ['OR4', 'OR16', 'OR23'])
  eq(o('ordre', { kanLeveres: true }), ['OR9', 'OR4', 'OR11'])
  eq(o('ordre', { delOrdre: true }), ['OR10', 'OR4', 'OR11'])
  eq(o('ordre', { kanFaktureres: true, nulMoms: true }), ['OR17', 'OR6', 'OR4'])
  eq(o('ordre', { kanFaktureres: true, ean: true }), ['OR18', 'OR6', 'OR4'])
  eq(o('ordre', { kanFaktureres: true, ean: true, digital: true }), ['OR6', 'OR4', 'OR11'])
  eq(o('kreditnota'), ['OR13', 'OR5', 'OR23'])
  eq(o('faktura'), ['OR19', 'OR12', 'OR14'])
  eq(o('faktura', { krediteret: true, forbundet: true }), ['OR19', 'OR22', 'OR14'])
  eq(o('kreditnotaFaktureret'), ['OR19', 'OR22', 'OR23'])
  // The credit limit warning on the open order is shown first (spec §18), never on an invoice
  const pr = (state, flags) => OR.problem(ctx, { state, flags, data: {} })
  assert.equal(pr('ordre', { kreditloft: true }), 'OR20')
  assert.equal(OR.order(ctx, { state: 'ordre', flags: { kreditloft: true }, data: {} })[0], 'OR20')
  assert.equal(pr('ordre', {}), '')
  assert.equal(pr('faktura', { kreditloft: true }), '')
  // Om siden: Gem only on the open view, no new items on a credit note
  eq(OR.about(ctx, { state: 'faktura', flags: { ikkeGemt: true }, data: {} }), ['help.dord.about.faktura'])
  eq(OR.about(ctx, { state: 'kreditnota', flags: { ingenLinjer: true }, data: {} }), ['help.dord.about.kreditnota'])
  eq(OR.about(ctx, { state: 'ordre', flags: { hurtigfakt: true, ingenLinjer: true }, data: {} }), ['help.dord.about.ordre_hurtig', 'help.dord.about.ingen_linjer'])
  // On an invoice the payment question follows the correction; it is answered in the kassekladde, so it rings nothing
  eq(OR.questionOrder(ctx, { state: 'faktura', flags: {}, data: {} }).slice(0, 2), ['Q1', 'Q5'])
  assert.equal(OR.questions.Q5.show, undefined)
  eq(OR.questions.Q5.steps.length, 4)
  // Before there is a customer, finding it leads; a posted credit note leads with paying the money back, which is
  // answered in the kassekladde and rings nothing
  eq(OR.questionOrder(ctx, { state: 'kundeopslag', flags: {}, data: {} })[0], 'Q6')
  eq(OR.questionOrder(ctx, { state: 'ny', flags: {}, data: {} })[0], 'Q6')
  eq(OR.questionOrder(ctx, { state: 'kreditnotaFaktureret', flags: {}, data: {} }), ['Q7', 'Q4', 'Q1'])
  assert.equal(OR.questions.Q7.show, undefined)

  // Kredit rings only on an invoice, Kreditér only on an open credit note; line 1 only when the order has lines
  const doc = new Doc()
  const form = new El('form', { name: 'ordre' })
  doc.append(new El('body').append(form))
  form.append(new El('input', { type: 'submit', name: 'b_submit', value: 'OK' }), new El('input', { type: 'submit', name: 'b_submit', value: 'Credit' }))
  form.append(new El('tr', { class: 'ordrelinje', 'data-line-id': '1' }), new El('input', { type: 'submit', name: 'print' }))
  const c = ctxFor(doc, 'https://x/debitor/ordre.php?id=5')
  // Udskriv/Send: the tilbud task, the order task and the invoice task each ring it only in their own state
  assert.equal(OR.tasks.OR19.show.target(c, { state: 'faktura', flags: {}, data: {} }).getAttribute('name'), 'print')
  assert.equal(OR.tasks.OR7.show.target(c, { state: 'faktura', flags: {}, data: {} }), null)
  assert.equal(OR.tasks.OR11.show.target(c, { state: 'tilbud', flags: {}, data: {} }), null)
  assert.equal(OR.tasks.OR5.show.target(c, { state: 'faktura', flags: {}, data: { linjer: 1 } }), null)
  assert.equal(OR.tasks.OR12.show.target(c, { state: 'faktura', flags: {}, data: {} }).getAttribute('value'), 'Credit')
  assert.equal(OR.tasks.OR12.show.target(c, { state: 'kreditnota', flags: {}, data: {} }), null)
  assert.equal(OR.tasks.OR13.show.target(c, { state: 'faktura', flags: {}, data: {} }), null)
  assert.equal(OR.tasks.OR5.show.target(c, { state: 'ordre', flags: {}, data: { linjer: 0 } }), null)
  assert.equal(OR.tasks.OR5.show.target(c, { state: 'ordre', flags: {}, data: { linjer: 1 } }).getAttribute('data-line-id'), '1')
  // Skift shows newAccountNo, which keeps the Kontonr. help
  assert.equal(OR.fieldKey(new El('input', { name: 'newAccountNo' })), 'kontonr')
  assert.equal(OR.fieldKey(new El('input', { name: 'vare0' })), null)
})

test('D2 debitorer: detect() gives the view, the state and the flags of each grid', () => {
  const LIST = C.pages['debitor/debitor.php']
  assert.equal(C.pages['debitor/debitor_historik.php'], undefined, 'Historik redirects to the CRM (includes/crmLegacyRoute.php)')
  assert.equal(C.pages['debitor/debitor_kommission.php'], LIST, 'the two views share one page object')
  /** A grid page as includes/grid.php prints it: wrapper, table, search row with the actions arrow, rows, filler row. */
  const grid = (id, { rows = 1, search = '', mode = 'main', topNy = true, meta = null } = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    if (topNy) body.append(new El('td', { id: 'opret-ny' }).append(new El('a', { href: 'debitorkort.php?returside=debitor.php' })))
    body.append(new El('td').append(new El('a', { href: 'debitor_historik.php?returside=x' })))
    const wrap = new El('div', { id: 'datatable-wrapper-' + id, class: 'datatable-wrapper' })
    body.append(wrap)
    if (mode === 'kolonner') {
      wrap.append(new El('select', { name: 'rows[' + id + '][1][field]' }), new El('input', { name: 'rows[' + id + '][1][pos]', value: '1' }))
      return doc
    }
    if (mode === 'filtre') {
      wrap.append(new El('input', { type: 'checkbox', name: 'filter[' + id + '][Misc][Vis udgået]' }))
      return doc
    }
    const table = new El('table', { id: 'datatable-' + id, class: 'datatable' })
    wrap.append(table)
    const thead = new El('thead')
    const searchRow = new El('tr')
    searchRow.append(new El('th').append(new El('input', { name: 'search[' + id + '][firmanavn]', value: search })))
    searchRow.append(new El('th').append(new El('input', { name: 'search[' + id + '][kontonr]', value: '' })))
    searchRow.append(new El('th').append(new El('div', { class: 'dropdown' }).append(new El('button', { type: 'button', class: 'dropdown-trigger' }))))
    thead.append(searchRow)
    const tbody = new El('tbody')
    for (let i = 0; i < rows; i++) {
      const tr = new El('tr', { style: '' })
      tr.append(new El('td', { onclick: "window.location.href='debitorkort.php?id=" + i + "'" }))
      if (meta) {
        tr.append(new El('td').append(new El('input', { type: 'checkbox', name: 'mySale[]', value: String(i) })))
        tr.append(new El('td').append(new El('input', { type: 'checkbox', name: 'invite[]', value: String(i), ...(meta[i] ? {} : { disabled: '' }) })))
      }
      tbody.append(tr)
    }
    tbody.append(new El('tr', { class: 'filler-row' }))
    table.append(thead, tbody)
    return doc
  }
  const URL_D = 'https://ssl12.saldi.dk/asr_hj/debitor/debitor.php'
  const det = (doc, url = URL_D) => LIST.detect(ctxFor(doc, url))

  let st = det(grid('debitor_list', { rows: 3 }))
  eq([st.state, st.flags, st.data], ['liste', { soegning: false, ingenRaekker: false, udenMail: false }, { view: 'debitorer', rows: 3 }])
  eq(LIST.order(null, st), ['DB1', 'DB3', 'DB2', 'DB18', 'DB4', 'DB5', 'DB6', 'DB8'], 'DB18: Historik leads to the CRM')
  st = det(grid('debitor_list', { rows: 0 }))
  assert.equal(st.state, 'tom')
  eq(LIST.order(null, st), ['DB2', 'DB8'])
  eq(LIST.about(null, st), ['help.deb.about.tom'])
  st = det(grid('debitor_list', { rows: 0, search: 'xyz' }))
  eq([st.state, st.flags.soegning], ['tom', true])
  eq(LIST.order(null, st), ['DB7', 'DB2', 'DB8'])
  eq(LIST.about(null, st), ['help.deb.about.soegning_tom'])
  assert.equal(det(grid('debitor_historik', { rows: 2, topNy: false })).state, 'unknown', 'the old Historik grid is no view of the list')
  assert.ok(!LIST.states.includes('historik'))
  st = det(grid('debitor_kommission', { rows: 2, topNy: false, meta: [true, false] }), URL_D + '?valg=kommission')
  eq([st.state, st.flags.udenMail], ['kommission', true])
  eq(LIST.about(null, st), ['help.deb.about.kommission', 'help.deb.about.uden_mail'])
  eq(LIST.order(null, st).slice(0, 3), ['DB10', 'DB11', 'DB12'])
  eq(LIST.questionOrder(null, st)[0], 'Q4')
  st = det(grid('debitor_list', { mode: 'kolonner' }))
  eq([st.state, st.data.view], ['kolonner', 'debitorer'])
  eq(LIST.order(null, st), ['DB15'])
  st = det(grid('debitor_kommission', { mode: 'filtre' }))
  eq([st.state, st.data.view], ['filtre', 'kommission'])
  assert.equal(LIST.name(null, st), 'help.deb.name_kommission')
  eq(LIST.order(null, st), ['DB17', 'DB5'])
  eq(det(new Doc()).state, 'unknown', 'the Booking view (valg=rental) has no grid')
})

test('D2 debitorer: show targets ring only their own view, and the Kommission boxes get their field keys', () => {
  const LIST = C.pages['debitor/debitor.php']
  const T = LIST.tasks
  const page = (id, extra = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    if (id === 'debitor_list') body.append(new El('td', { id: 'opret-ny' }).append(new El('a', { href: 'debitorkort.php?returside=debitor.php' })))
    body.append(new El('a', { href: 'debitor_historik.php?returside=x' }))
    const wrap = new El('div', { id: 'datatable-wrapper-' + id })
    const table = new El('table', { id: 'datatable-' + id })
    const thead = new El('thead').append(new El('tr').append(
      new El('th').append(new El('input', { name: 'search[' + id + '][firmanavn]', value: extra.search || '' })),
      new El('th').append(new El('input', { name: 'search[' + id + '][postnr]', value: '' })),
      new El('th').append(new El('button', { class: 'dropdown-trigger' }))))
    const row = new El('tr', { style: '', class: 'first' })
    if (id === 'debitor_kommission') {
      row.append(new El('td').append(new El('input', { type: 'checkbox', name: 'mySale[]', value: '7' })))
      row.append(new El('td').append(new El('input', { type: 'checkbox', name: 'invite[]', value: '7', disabled: '' })))
    }
    const row2 = new El('tr', { style: '' })
    if (id === 'debitor_kommission') row2.append(new El('td').append(new El('input', { type: 'checkbox', name: 'invite[]', value: '8' })))
    const tbody = new El('tbody').append(row, row2, new El('tr', { class: 'filler-row' }))
    table.append(thead, tbody)
    wrap.append(table)
    body.append(wrap)
    return doc
  }
  const at = (doc, show) => {
    const ctx = ctxFor(doc, 'https://ssl12.saldi.dk/asr_hj/debitor/debitor.php')
    return typeof show.target === 'function' ? show.target(ctx, LIST.detect(ctx)) : doc.querySelector(show.target)
  }
  let doc = page('debitor_list')
  assert.equal(at(doc, T.DB1.show).getAttribute('name'), 'search[debitor_list][firmanavn]')
  assert.match(at(doc, T.DB2.show).getAttribute('href'), /^debitorkort\.php/)
  assert.equal(at(doc, T.DB3.show).getAttribute('class'), 'first', 'the first customer, not the filler row')
  assert.equal(at(doc, T.DB4.show).getAttribute('class'), 'dropdown-trigger')
  assert.equal(at(doc, T.DB7.show), null, 'no search with text')
  assert.equal(at(doc, T.DB12.show), null)
  // The Historik tab still links debitor_historik.php, which redirects to the CRM's Aktivitet tab
  assert.match(at(doc, T.DB18.show).getAttribute('href'), /^debitor_historik\.php/)
  assert.ok(!T.DB13 && !T.DB14, 'no tasks for the old Historik view')
  eq(T.DB3.steps, ['help.deb.task.db3.step1', 'help.deb.task.db3.step3'], 'a new key for the reworded second step')
  assert.equal(at(page('debitor_list', { search: 'Hansen' }), T.DB7.show).getAttribute('value'), 'Hansen')

  // Live: the Kolonner form keeps the grid's table, so its first row is a setting, not a customer
  doc = page('debitor_list')
  doc.querySelector('thead').append(new El('select', { name: 'rows[debitor_list][1][field]' }))
  assert.equal(at(doc, T.DB3.show), null, 'no customer row in Kolonner')
  assert.equal(at(doc, LIST.questions.Q2.show), null)

  doc = page('debitor_kommission')
  assert.equal(at(doc, T.DB10.show).getAttribute('name'), 'mySale[]')
  assert.equal(at(doc, T.DB11.show).getAttribute('value'), '8', 'the first Inviter box that can be ticked')
  assert.equal(at(doc, T.DB12.show).getAttribute('class'), 'first')
  assert.equal(at(doc, T.DB3.show), null)

  assert.equal(LIST.fieldKey(new El('input', { name: 'mySale[]' })), 'aktiv')
  assert.equal(LIST.fieldKey(new El('input', { name: 'invite[]' })), 'inviter')
  assert.equal(LIST.fieldKey(new El('input', { name: 'search[debitor_list][firmanavn]' })), null, 'the default rule gives search:firmanavn')
  for (const k of ['aktiv', 'inviter', 'search:firmanavn', 'search:kontonr', 'rowcount']) assert.ok(LIST.fields[k], k)
})

test('D2 debitorkort: detect(), problem() and the show targets', () => {
  const CARD = C.pages['debitor/debitorkort.php']
  /** The card as debitorkort.php prints it: the form closes early, its controls stand beside it. */
  const card = ({ id = '42', kontonr = '1005', type = 'erhverv', banner = false, returside = 'debitor.php', lukket = false, slet = false, contacts = 1, footer = true, crm = true } = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('a', { href: returside, accesskey: 'L' }))
    body.append(new El('form', { name: 'debitorkort', id: 'debitorkortForm' }))
    body.append(new El('input', { type: 'hidden', name: 'id', value: id }))
    body.append(new El('input', { type: 'hidden', name: 'kontonr', value: kontonr }))
    body.append(new El('input', { type: 'hidden', name: 'returside', value: returside }))
    if (banner) body.append(new El('div', { class: 'einvoice-cvr-warning', role: 'alert' }))
    const sel = new El('select', { name: 'kontotype' })
    const types = type === 'privat' ? ['privat', 'erhverv'] : ['erhverv', 'privat']
    for (const t of types) sel.append(new El('option', { value: t }, { text: t }))
    body.append(sel)
    body.append(new El('input', { type: 'text', name: 'ny_kontonr', value: kontonr }))
    body.append(new El('input', { type: 'text', name: 'kontakt_email_val[1]', value: '' }))
    body.append(new El('input', { type: 'text', name: 'cvrnr', value: '' }))
    body.append(type === 'erhverv' ? new El('input', { type: 'text', name: 'ean', value: '5798000000000' }) : new El('input', { type: 'hidden', name: 'ean', value: '' }))
    body.append(new El('input', { type: 'checkbox', name: 'lukket', ...(lukket ? { checked: '' } : {}) }))
    if (type === 'erhverv' && id !== '0') {
      body.append(new El('a', { href: 'ansatte.php?returside=debitor.php&ordre_id=&fokus=&konto_id=' + id }).append(new El('button', { type: 'button' })))
      const tb = new El('tbody', { id: 'kontaktpersoner' })
      for (let i = 1; i <= contacts; i++) {
        tb.append(new El('tr').append(new El('td').append(new El('input', { name: 'posnr[' + i + ']', value: String(i) })),
          new El('td').append(new El('a', { href: 'ansatte.php?returside=debitor.php&konto_id=' + id + '&id=' + (90 + i) }))))
      }
      body.append(tb)
    }
    body.append(new El('input', { type: 'submit', name: 'submit', accesskey: 'g', value: 'Gem/opdatér' }))
    body.append(slet ? new El('input', { type: 'submit', name: 'submit', accesskey: 's', value: 'Slet' }) : new El('input', { type: 'submit', name: 'anonymize', value: 'Anonymisér' }))
    if (footer) {
      // Historik only with crm.access (debitorkort.php:2664); it redirects to the CRM's drawer
      body.append(new El('div', { id: 'debkort-page-footer' }).append(new El('div', { class: 'sticky-custom-buttons' }).append(
        ...(crm ? [new El('button', { type: 'button', onclick: "window.location.href='historikkort.php?id=" + id + "'" })] : []),
        new El('button', { type: 'button', onclick: "window.location.href='rapport.php?rapportart=kontokort&amp;layout=grid'" }),
        new El('button', { type: 'button', onclick: "window.location.href='ordreliste.php?konto_id=" + id + "&amp;account_context=1&amp;valg=faktura'" }))))
    }
    return doc
  }
  const URL_K = 'https://ssl12.saldi.dk/asr_hj/debitor/debitorkort.php?tjek=42&id=42&returside=debitor.php'
  const det = (doc, url = URL_K) => CARD.detect(ctxFor(doc, url))
  const at = (doc, show) => {
    const ctx = ctxFor(doc, URL_K)
    return typeof show.target === 'function' ? show.target(ctx, CARD.detect(ctx)) : doc.querySelector(show.target)
  }

  let st = det(card())
  eq([st.state, st.flags, st.data], ['kort', { privat: false, cvrAdvarsel: false, fraOrdre: false, lukket: false }, { kontonr: '1005' }])
  eq(CARD.name(null, st), ['help.dkort.name', '1005'])
  eq(CARD.order(null, st).slice(0, 4), ['DK17', 'DK8', 'DK19', 'DK20'])
  assert.ok(!CARD.tasks.DK9, 'DK20 replaces DK9')
  assert.equal(CARD.problem(null, st), '')

  st = det(card({ banner: true }))
  assert.equal(st.flags.cvrAdvarsel, true)
  assert.equal(CARD.problem(null, st), 'DK13')
  assert.equal(CARD.order(null, st)[0], 'DK13')
  eq(CARD.about(null, st), ['help.dkort.about.kort', 'help.dkort.about.cvr_advarsel'])

  st = det(card({ id: '0', kontonr: '', type: 'privat', returside: '../debitor/ordre.php?ordre_id=7', contacts: 0 }))
  eq([st.state, st.flags.privat, st.flags.fraOrdre], ['ny', true, true])
  assert.equal(CARD.name(null, st), 'help.dkort.name_new')
  eq(CARD.order(null, st).slice(0, 3), ['DK15', 'DK4', 'DK3'])
  eq(CARD.about(null, st), ['help.dkort.about.ny', 'help.dkort.about.fra_ordre_ny'])
  assert.equal(CARD.problem(null, st), '', 'a new card has no saved values to warn about')
  st = det(card({ id: '0', kontonr: '' }))
  eq(CARD.order(null, st).slice(0, 3), ['DK1', 'DK2', 'DK4'])

  st = det(card({ lukket: true }))
  eq([st.flags.lukket, CARD.order(null, st)[0]], [true, 'DK16'])
  assert.ok(!CARD.order(null, st).includes('DK10'), 'no Luk for a closed customer')
  assert.ok(!CARD.order(null, st).includes('DK19'), 'no new order for a closed customer')
  assert.equal(det(card({ returside: 'ordreliste.php?konto_id=42' })).flags.fraOrdre, false, 'the order list is not an order')
  assert.equal(det(new Doc()).state, 'unknown')

  // targets
  let doc = card()
  assert.match(at(doc, CARD.tasks.DK7.show).getAttribute('href'), /konto_id=42$/, 'Ny holds a button, the names do not')
  assert.equal(at(doc, CARD.tasks.DK11.show).getAttribute('name'), 'anonymize', 'Anonymisér when Slet is not printed')
  assert.equal(at(card({ slet: true, contacts: 0 }), CARD.tasks.DK11.show).getAttribute('accesskey'), 's')
  assert.match(at(doc, CARD.tasks.DK8.show).getAttribute('onclick'), /rapportart=kontokort/)
  assert.match(at(doc, CARD.tasks.DK20.show).getAttribute('onclick'), /historikkort\.php/)
  assert.equal(at(card({ crm: false }), CARD.tasks.DK20.show), null, 'no Historik button without crm.access')
  assert.equal(at(doc, CARD.tasks.DK2.show).getAttribute('accesskey'), 'g')
  assert.equal(at(doc, CARD.tasks.DK13.show).getAttribute('name'), 'cvrnr')
  assert.equal(at(doc, CARD.tasks.DK6.show).getAttribute('name'), 'ean')
  assert.equal(at(card({ type: 'privat' }), CARD.tasks.DK6.show), null, 'a private card has EAN only as a hidden field')
  assert.equal(at(card({ type: 'privat' }), CARD.tasks.DK7.show), null, 'no contact persons on a private card')
  assert.equal(at(card({ footer: false }), CARD.tasks.DK8.show), null, 'the footer is added by script')
  assert.match(at(doc, CARD.tasks.DK19.show).getAttribute('onclick'), /ordreliste\.php\?konto_id=42/, 'Fakturaliste, whose Ny makes the order')
  {
    const nyDoc = card({ id: '0', kontonr: '' })
    const nyCtx = ctxFor(nyDoc, URL_K)
    assert.equal(CARD.tasks.DK19.show.target(nyCtx, CARD.detect(nyCtx)), null, 'a new card has no customer to order for')
    // Live: the CVR lookup only works on a new card; delete, merge and back to the order only where they apply (§19.3)
    assert.equal(at(nyDoc, CARD.tasks.DK1.show).getAttribute('name'), 'cvrnr')
    assert.equal(at(doc, CARD.tasks.DK1.show), null, 'no lookup on a saved card')
    assert.equal(at(nyDoc, CARD.tasks.DK11.show), null, 'nothing to delete on a new card')
    assert.equal(at(nyDoc, CARD.tasks.DK12.show), null, 'nothing to merge on a new card')
    assert.equal(at(nyDoc, CARD.questions.Q2.show).getAttribute('name'), 'ny_kontonr', 'Kundenr. on a new card')
    // The start screen (spec §25): a new card leads with the number it gets, which rings Kundenr. on a new card only;
    // a saved card with deleting, a closed one with why an order cannot use it
    assert.equal(at(nyDoc, CARD.questions.Q5.show).getAttribute('name'), 'ny_kontonr')
    assert.equal(at(doc, CARD.questions.Q5.show), null, 'the number a new card gets is no question on a saved card')
    eq(CARD.questionOrder(null, { state: 'ny', flags: {}, data: {} }), ['Q5', 'Q2', 'Q1'])
    eq(CARD.questionOrder(null, { state: 'kort', flags: {}, data: {} }), ['Q3', 'Q2', 'Q4'])
    eq(CARD.questionOrder(null, { state: 'kort', flags: { lukket: true }, data: {} }), ['Q1', 'Q3', 'Q2'])
    assert.equal(at(doc, CARD.tasks.DK12.show).getAttribute('name'), 'ny_kontonr')
    assert.equal(at(doc, CARD.tasks.DK14.show), null, 'Gem/opdatér does not go back to an order here')
    assert.equal(at(nyDoc, CARD.tasks.DK15.show), null)
    const fraOrdre = card({ returside: '../debitor/ordre.php?ordre_id=7' })
    assert.equal(at(fraOrdre, CARD.tasks.DK14.show).getAttribute('accesskey'), 'g')
    const nyFraOrdre = card({ id: '0', kontonr: '', returside: '../debitor/ordre.php?ordre_id=7' })
    assert.equal(at(nyFraOrdre, CARD.tasks.DK15.show).getAttribute('accesskey'), 'g')
    assert.equal(CARD.tasks.DK1.show.target(ctxFor(nyDoc, URL_K)).getAttribute('name'), 'cvrnr', 'the state is detected without one')
  }
  assert.equal(CARD.fieldKey(new El('input', { name: 'kontakt_email_val[1]' })), 'email')
  assert.equal(CARD.fieldKey(new El('input', { name: 'posnr[3]' })), 'posnr')
  assert.equal(CARD.fieldKey(new El('input', { name: 'cvrnr' })), null, 'the default rule')
  for (const k of ['email', 'posnr', 'cvrnr', 'ny_kontonr', 'kontotype', 'betalingsbet', 'gruppe', 'ean', 'felt']) assert.ok(CARD.fields[k], k)
})

test('D2 CRM: the tabs, the drawer, read-only access and the targets (debitor/crm.php replaced the customer history)', () => {
  const CRM = C.pages['debitor/crm.php']
  for (const r of ['debitor/historikkort.php', 'debitor/historik.php', 'debitor/debitor_historik.php']) {
    assert.equal(C.pages[r], undefined, r + ' only redirects to the CRM (includes/crmLegacyRoute.php)')
  }
  assert.equal(CRM.group, 'dcrm')
  /** The CRM as debitor/crm.php and javascript/crm.js draw it: tabs, the head's controls, the views, the drawer and the
   *  dialog. drawer: 'open', 'closed' (its content stays after closeDrawer()) or false. dialog: 'n' (Ny note or Ny
   *  opfølgning with the customer picked: the composer with the prefix n), 'meet' (Nyt møde / Book møde), 'closed' (#scrim
   *  without open; the last dialog's content stays) or false. */
  const crm = ({ tab = 'follow', drawer = false, write = true, late = false, q = '', name = 'Fjordlys ApS', closed = false, open = true, dialog = false } = {}) => {
    const doc = new Doc()
    const root = new El('div', { id: 'crm', class: 'sk sk-page crm' })
    doc.append(new El('body').append(root))
    const tabs = new El('div', { class: 'sk-tabs', role: 'tablist', id: 'tabs' })
    for (const id of ['follow', 'pipe', 'cal', 'cust', 'act']) {
      const b = new El('button', { type: 'button', class: 'sk-tab', role: 'tab', 'aria-selected': String(id === tab), 'data-tab': id })
      if (id === 'follow' && late) b.append(new El('span', { class: 'crm-tab-late' }))
      tabs.append(b)
    }
    root.append(tabs)
    root.append(new El('input', { class: 'sk-input', id: 'q', value: q }), new El('select', { class: 'sk-input', id: 'grpSel' }))
    root.append(new El('div', { class: 'sk-seg', id: 'scope' }).append(new El('button', { 'data-scope': 'all' }), new El('button', { 'data-scope': 'mine' })))
    root.append(new El('button', { class: 'sk-btn sk-btn-primary', id: 'newBtn', ...(write ? {} : { hidden: '' }) }))
    for (const id of ['follow', 'pipe', 'cal', 'cust', 'act']) {
      const v = new El('div', { class: 'crm-view', id: 'v-' + id, ...(id === tab ? {} : { hidden: '' }) })
      if (id === 'follow') {
        const tb = new El('tbody')
        if (open) tb.append(new El('tr', { class: 'row body-row', 'data-open': '4', 'data-h': '7' }).append(new El('td').append(new El('button', { class: 'tick', 'data-tick': '7' }))))
        tb.append(new El('tr', { class: 'row body-row done-row', 'data-open': '5', 'data-h': '8' }).append(new El('td').append(new El('button', { class: 'tick on', 'data-tick': '8' }))))
        v.append(new El('table').append(tb))
      }
      root.append(v)
    }
    const d = new El('aside', { class: 'sk-drawer drawer crm-drawer' + (drawer === 'open' ? ' open' : ''), id: 'drawer' })
    if (drawer) {
      const h2 = new El('h2', { class: 'sk-drawer-t', id: 'dTitle' }, { text: closed ? name + ' (lukket)' : name })
      if (closed) h2.append(new El('span', { class: 'sk-faint' }, { text: '(lukket)' }))
      d.append(h2, new El('select', { id: 'stSel' }), new El('select', { id: 'ownSel' }))
      d.append(new El('div', { class: 'crm-links' }).append(new El('a', { href: '#', 'data-addmeet': '', 'data-cid': '4' })))
      d.append(new El('div', { class: 'sk-note crm-next' }).append(new El('button', { 'data-snooze': '9' }), new El('button', { 'data-tick': '9' })))
      if (write) d.append(new El('div', { class: 'crm-comp', id: 'ccomp' }).append(new El('textarea', { id: 'cNote' }), new El('div', { id: 'cchips' }), new El('input', { type: 'date', id: 'cdate' })))
    }
    root.append(d)
    const dlg = new El('div', { class: 'sk-dialog crm-dlg', id: 'dlg', role: 'dialog' })
    if (dialog === 'n' || dialog === 'closed') dlg.append(new El('div', { class: 'crm-comp', id: 'ncomp' }).append(new El('textarea', { id: 'nNote' }), new El('div', { id: 'nchips' })))
    if (dialog === 'meet') dlg.append(new El('textarea', { id: 'mN' }), new El('input', { type: 'date', id: 'mD' }))
    root.append(new El('div', { class: 'sk-modal' + (dialog && dialog !== 'closed' ? ' open' : ''), id: 'scrim' }).append(dlg))
    return doc
  }
  const URL_C = 'https://ssl12.saldi.dk/asr/debitor/crm.php?tab=follow'
  const det = (doc) => CRM.detect(ctxFor(doc, URL_C))
  const at = (doc, show) => {
    const ctx = ctxFor(doc, URL_C)
    return typeof show.target === 'function' ? show.target(ctx, CRM.detect(ctx)) : doc.querySelector(show.target)
  }
  const T = CRM.tasks

  // the tabs
  let st = det(crm())
  eq([st.state, st.flags, st.data], ['opfoelgning', { kunLaes: false, forsinket: false, soegning: false, dialog: false }, { tab: 'follow', kunde: '' }])
  assert.equal(CRM.inPlace, true, 'crm.js redraws in place: the shell reads it again after a click or a key')
  assert.equal(CRM.name(null, st), 'help.dcrm.name')
  eq(CRM.about(null, st), ['help.dcrm.about.opfoelgning'])
  eq(CRM.order(null, st).slice(0, 3), ['CR2', 'CR4', 'CR1'])
  eq(CRM.questionOrder(null, st), ['Q2', 'Q1'])
  st = det(crm({ late: true }))
  eq([st.flags.forsinket, CRM.order(null, st)[0]], [true, 'CR4'], 'overdue follow-ups first')
  eq(CRM.about(null, st), ['help.dcrm.about.opfoelgning', 'help.dcrm.about.forsinket'])
  eq(['pipe', 'cal', 'cust', 'act'].map((tab) => det(crm({ tab })).state), ['sager', 'kalender', 'kunder', 'aktivitet'])
  st = det(crm({ tab: 'act' }))
  eq([CRM.order(null, st)[0], CRM.questionOrder(null, st)], ['CR1', ['Q1', 'Q2']], 'Historik on the list lands here')
  eq(CRM.about(null, st), ['help.dcrm.about.aktivitet'])
  st = det(crm({ tab: 'cust', q: 'Fjord' }))
  eq([st.state, st.flags.soegning], ['kunder', true])
  eq(CRM.about(null, st), ['help.dcrm.about.kunder', 'help.dcrm.about.soegning'])
  eq(CRM.order(null, det(crm({ tab: 'cal' }))).slice(0, 1), ['CR5'])

  // the drawer: a closed one keeps its content, so only the class open counts
  st = det(crm({ drawer: 'open' }))
  eq([st.state, st.data.kunde], ['kunde', 'Fjordlys ApS'])
  eq(CRM.name(null, st), ['help.dcrm.name_kunde', 'Fjordlys ApS'])
  eq(CRM.order(null, st), ['CR1', 'CR2', 'CR4', 'CR5'])
  eq(CRM.about(null, st), ['help.dcrm.about.kunde'])
  eq(CRM.name(null, det(crm({ drawer: 'open', closed: true }))), ['help.dcrm.name_kunde', 'Fjordlys ApS'], 'without (lukket)')
  assert.equal(det(crm({ drawer: 'closed' })).state, 'opfoelgning')

  // read-only: no new button, no composer
  st = det(crm({ write: false }))
  eq([st.state, st.flags.kunLaes], ['opfoelgning', true])
  eq(CRM.order(null, st), ['CR3', 'CR6'])
  eq(CRM.about(null, st), ['help.dcrm.about.opfoelgning', 'help.dcrm.about.kun_laes'])
  eq(CRM.questionOrder(null, st)[0], 'Q3')
  st = det(crm({ write: false, drawer: 'open' }))
  eq([st.state, CRM.order(null, st)], ['kunde', []])
  assert.equal(at(crm({ write: false, drawer: 'open' }), T.CR1.show), null, 'no composer to write in')
  eq(det(new Doc()).state, 'unknown', 'the update-required text has no #crm')

  // targets: the list controls in the tabs, the drawer's in the drawer
  let doc = crm()
  assert.equal(at(doc, T.CR3.show).getAttribute('id'), 'q')
  assert.equal(at(doc, T.CR6.show).getAttribute('data-scope'), 'mine')
  assert.equal(at(doc, T.CR4.show).getAttribute('data-tick'), '7', 'the first open follow-up, not a done one')
  assert.equal(at(crm({ open: false }), T.CR4.show), null, 'only done follow-ups: nothing to tick')
  assert.equal(at(doc, CRM.questions.Q1.show).getAttribute('data-tab'), 'act')
  assert.equal(at(doc, T.CR1.show), null)
  assert.equal(T.CR3.show.target(ctxFor(doc, URL_C)).getAttribute('id'), 'q', 'the state is detected without one')
  doc = crm({ drawer: 'open' })
  assert.equal(at(doc, T.CR1.show).getAttribute('id'), 'cNote')
  assert.equal(at(doc, T.CR2.show).getAttribute('id'), 'cchips')
  assert.equal(at(doc, T.CR4.show).getAttribute('data-tick'), '9', 'Gjort at the next follow-up')
  assert.equal(at(doc, T.CR5.show).getAttribute('data-cid'), '4')
  assert.equal(at(doc, T.CR3.show), null, 'the search is under the drawer\'s scrim')
  assert.equal(at(doc, T.CR6.show), null)
  assert.equal(at(doc, CRM.questions.Q1.show), null)
  assert.equal(at(crm({ drawer: 'closed' }), T.CR1.show), null)
  assert.ok(CRM.questions.Q2.show === T.CR3.show)

  // a Vis mig reads the live state, not the one cached at the page load (the page redraws in place)
  doc = crm({ drawer: 'open' })
  assert.equal(T.CR1.show.target(ctxFor(doc, URL_C), { state: 'opfoelgning', flags: {}, data: {} }).getAttribute('id'), 'cNote', 'a stale tab state')
  assert.equal(T.CR3.show.target(ctxFor(crm({ tab: 'cust' }), URL_C), { state: 'kunde', flags: {}, data: {} }).getAttribute('id'), 'q', 'a stale drawer state')

  // a dialog (#scrim.open) lies over the tabs and the drawer: only its own composer rings
  doc = crm({ dialog: 'n' })
  st = det(doc)
  eq([st.state, st.flags.dialog], ['opfoelgning', true])
  assert.equal(at(doc, T.CR1.show).getAttribute('id'), 'nNote')
  assert.equal(at(doc, T.CR2.show).getAttribute('id'), 'nchips')
  for (const id of ['CR3', 'CR4', 'CR5', 'CR6']) assert.equal(at(doc, T[id].show), null, id + ' is under the dialog')
  assert.equal(at(doc, CRM.questions.Q1.show), null)
  doc = crm({ drawer: 'open', dialog: 'meet' })
  st = det(doc)
  eq([st.state, st.flags.dialog], ['kunde', true])
  for (const id of ['CR1', 'CR2', 'CR3', 'CR4', 'CR5', 'CR6']) assert.equal(at(doc, T[id].show), null, id + ': Book møde lies over the drawer')
  doc = crm({ drawer: 'open', dialog: 'closed' })
  eq([det(doc).flags.dialog, at(doc, T.CR1.show).getAttribute('id')], [false, 'cNote'], 'a closed dialog keeps its content but counts for nothing')
  eq(CRM.questions.Q3.steps, ['help.dcrm.q.q3.step1', 'help.dcrm.q.q3.step2', 'help.dcrm.q.q3.step3'])

  // field keys: ids, the composer's with its prefix (c in the drawer, n in Ny opfølgning and Ny note)
  const fk = (id) => CRM.fieldKey(new El('input', { id }))
  eq(['q', 'grpSel', 'cNote', 'nNote', 'cto', 'nto', 'cdate', 'ndate', 'stSel', 'ownSel', 'mN'].map(fk),
    ['soeg', 'statusgruppe', 'notat', 'notat', 'med', 'med', 'dato', 'dato', 'status', 'ansvarlig', null])
  for (const k of ['soeg', 'statusgruppe', 'notat', 'med', 'dato', 'status', 'ansvarlig']) assert.ok(CRM.fields[k], k)

  // "Mine" is a stop word (saldi-help-content.js), but the name of a CRM view: the page's ownWords take it out there only
  for (const lang of ['da', 'en', 'no']) assert.ok(CRM.ownWords[lang].includes('mine'), lang)
  assert.ok(C.stopWords.da.includes('mine') && C.stopWords.no.includes('mine'), 'still a stop word for every other page')
  for (const p of PAGES) if (p.page !== CRM) assert.equal(p.page.ownWords, undefined, p.route + ' has no ownWords')

  // the field til exists only when the company has employees (crm.js composer(): DATA.emps.length), and the board may be empty
  const da = (k) => tsv.get(k)[1]
  for (const k of ['help.dcrm.task.cr2.step2', 'help.dcrm.q.q3.step3']) assert.match(da(k), /Har firmaet (ingen )?medarbejdere/, k)
  assert.match(da('help.dcrm.task.cr2.step2'), /^Har firmaet medarbejdere, kan du ved \*\*til\*\* vælge/)
  assert.match(da('help.dcrm.about.sager'), /Er tavlen tom, /)
  assert.match(tsv.get('help.dcrm.about.sager')[2], /If the board is empty, /)
})

test('D2 jobkort and kontofusion: the states and the targets', () => {
  const JL = C.pages['debitor/jobliste.php']
  const JK = C.pages['debitor/jobkort.php']
  const FU = C.pages['debitor/kontofusion.php']
  const list = ({ rows = 1, locked = 0, search = '' } = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('td', { id: 'opret-ny' }).append(new El('a', { href: 'jobkort.php?returside=jobkort.php&konto_id=0&ordre_id=0' })))
    for (let i = 1; i <= 8; i++) body.append(new El('input', { type: 'text', name: 'felt0' + i, value: i === 2 ? search : '' }))
    for (let i = 0; i < rows; i++) body.append(new El('a', { href: 'jobkort.php?tjek=' + i + '&id=' + i + '&returside=jobliste.php' }))
    for (let i = 0; i < locked; i++) body.append(new El('span', { title: 'job er l&aring;st af anne' }))
    return doc
  }
  const LURL = 'https://ssl12.saldi.dk/asr_hj/debitor/jobliste.php'
  let st = JL.detect(ctxFor(list({ rows: 2 }), LURL))
  eq([st.state, st.flags], ['liste', { soegning: false, kunde: false }])
  eq(JL.order(null, st), ['DJ3', 'DJ2', 'DJ1'])
  st = JL.detect(ctxFor(list({ rows: 0, search: 'Fjord' }), LURL + '?konto_id=5'))
  eq([st.state, st.flags], ['tom', { soegning: true, kunde: true }])
  eq(JL.about(null, st), ['help.djob.about.soegning_tom', 'help.djob.about.kunde'])
  assert.equal(JL.detect(ctxFor(list({ rows: 0, locked: 1 }), LURL)).state, 'liste', 'a locked job is still a row')
  eq(['felt01', 'felt02', 'felt08', 'felt03'].map((name) => JL.fieldKey(new El('input', { name }))), ['jobnr', 'kunde', 'uge', null])
  assert.match(list().querySelector(JL.tasks.DJ1.show.target).getAttribute('href'), /^jobkort\.php/)

  const card = (ordre) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('form', { name: 'jobkort', action: 'jobkort.php?id=17' }))
    body.append(new El('input', { type: 'text', name: 'felt_1', value: '12' }))
    if (ordre) body.append(new El('a', { href: 'jobkort.php?id=17&returside=ordre.php&17&konto_id=5&ordre_id=3&opdat17=1' }))
    body.append(new El('input', { type: 'submit', name: 'gem', accesskey: 'g', value: 'Gem' }))
    body.append(new El('input', { type: 'submit', name: 'udskriv', accesskey: 'u', value: 'Udskriv' }))
    body.append(new El('input', { type: 'submit', name: 'slet', accesskey: 's', value: 'Slet' }))
    return doc
  }
  const KURL = 'https://ssl12.saldi.dk/asr_hj/debitor/jobkort.php?id=17'
  st = JK.detect(ctxFor(card(false), KURL))
  eq([st.state, st.flags, st.data], ['kort', { fraOrdre: false }, { jobnr: '17' }])
  eq(JK.name(null, st), ['help.djob.name', '17'])
  eq(JK.order(null, st), ['DJ6', 'DJ7', 'DJ9'])
  st = JK.detect(ctxFor(card(true), KURL))
  eq(JK.order(null, st), ['DJ8', 'DJ6', 'DJ7', 'DJ9'])
  assert.match(card(true).querySelector(JK.tasks.DJ8.show.target).getAttribute('href'), /opdat17=1/)
  assert.equal(card(false).querySelector(JK.tasks.DJ9.show.target).getAttribute('name'), 'slet')
  assert.equal(JK.fieldKey(new El('input', { name: 'felt_1' })), 'uge')
  assert.equal(JK.fieldKey(new El('textarea', { name: 'felt_indhold[1][1]' })), 'felt_indhold')
  const pick = new Doc()
  pick.append(new El('a', { href: 'jobkort.php?id=&konto_id=5' }))
  st = JK.detect(ctxFor(pick, 'https://ssl12.saldi.dk/asr_hj/debitor/jobkort.php?returside=jobkort.php&konto_id=0'))
  eq([st.state, JK.order(null, st)], ['vaelgKunde', ['DJ5']])
  assert.ok(!JK.tasks.DJ1 && !JL.tasks.DJ6, 'each page offers only its own tasks')

  const fus = new Doc()
  fus.append(new El('form', { name: 'kontofusion' }).append(
    new El('input', { type: 'submit', name: 'fusion', value: 'Fortryd' }), new El('input', { type: 'submit', name: 'fusion', value: 'OK' })))
  st = FU.detect(ctxFor(fus, 'https://ssl12.saldi.dk/asr_hj/debitor/kontofusion.php?id=4&kontonr=1005'))
  eq([st.state, FU.order(null, st)], ['bekraeft', ['DF1', 'DF2']])
  assert.equal(fus.querySelector(FU.tasks.DF1.show.target).getAttribute('value'), 'OK')
  assert.equal(fus.querySelector(FU.tasks.DF2.show.target).getAttribute('value'), 'Fortryd')
  assert.equal(FU.detect(ctxFor(new Doc(), 'https://ssl12.saldi.dk/asr_hj/debitor/kontofusion.php')).state, 'unknown')
})

test('D3 debitorrapporter: detect() tells the form and the four reports apart, and the targets ring their element', () => {
  const RP = C.pages['debitor/rapport.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/debitor/rapport.php'
  const page = (...kids) => { const doc = new Doc(); doc.append(new El('body').append(...kids)); return doc }
  const st = (doc, url = URL0) => RP.detect(ctxFor(doc, url))
  const ring = (id, doc) => { const ctx = ctxFor(doc, URL0); const s = RP.detect(ctx); const t = RP.tasks[id].show.target; return typeof t === 'function' ? t(ctx, s) : doc.querySelector(t) }

  // The selection form (frontPageOldMenu.php:55-95): the form is closed by the table, so its inputs are no descendants
  const husk = new El('input', { type: 'checkbox', name: 'husk', checked: '' })
  const form = page(new El('form', { name: 'regnskabsaar' }), new El('input', { type: 'text', name: 'dato', value: '' }),
    new El('input', { type: 'text', name: 'konto', value: '' }), new El('label', { class: 'checkContainerVisning' }).append(husk),
    new El('input', { type: 'submit', name: 'openpost' }), new El('input', { type: 'submit', name: 'kontosaldo' }), new El('input', { type: 'submit', name: 'kontokort' }),
    new El('span', { onclick: "window.location.href='top100.php'" }).append(new El('input', { type: 'button', name: 'submit' })),
    new El('span', { onclick: "javascript:location.href='../debitor/betalingsliste.php'" }).append(new El('input', { type: 'button' })),
    new El('a', { href: 'kassespor.php' }).append(new El('input', { type: 'button' })))
  let s = st(form)
  eq([s.state, s.flags.husk, s.flags.soegning, s.flags.betalingslister, s.flags.pos], ['forside', true, false, true, true])
  eq(Object.keys(s.flags).sort(), [...RP.flags].sort(), 'detect() gives every flag the page lists')
  eq(RP.order(ctxFor(form, URL0), s).slice(0, 3), ['RF1', 'RF2', 'RF3'])
  eq(RP.about(ctxFor(form, URL0), s), ['help.drap.about.forside', 'help.drap.about.husk'])
  assert.equal(ring('RF5', form).getAttribute('class'), 'checkContainerVisning', 'Husk rings the styled box')
  assert.equal(ring('RF9', form).parent.getAttribute('onclick').includes('betalingsliste.php'), true)
  assert.equal(RP.problem(ctxFor(form, URL0), s), '')

  // Åbne poster with overdue amounts, a 0,00 to settle and open reminders (showOpenPosts.php, rapportfunc.php:130-551)
  const openpost = (current, extra = []) => {
    const sel = new El('select', { name: 'aabenpostmode' })
    sel.append(new El('option'))
    for (const m of ['vis_aabenpost', 'vis_alle_poster', 'kun_debet', 'kun_kredit', 'skjul_aabenpost']) {
      if (m !== current) sel.append(new El('option', { value: 'rapport.php?rapportart=openpost&submit=ok&' + m + '=on' }))
    }
    const table = new El('table', { id: 'visAabnePosterTable' })
    table.append(new El('thead').append(new El('tr').append(new El('th', { class: 'op-group' }),
      new El('th').append(new El('a', { class: 'op-filter', href: 'rapport.php?rapportart=openpost&aging_bucket=over90' })))))
    const row = new El('tr').append(new El('input', { type: 'hidden', name: 'konto_id[1]', value: '7' }),
      new El('td').append(new El('a', { href: 'rapport.php?rapportart=accountChart&kilde=openpost&konto_fra=1001' })),
      new El('td').append(new El('a', { class: 'op-name', href: 'debitorkort.php?id=7' })),
      new El('td').append(new El('span', { style: 'color: rgb(255, 0, 0);' })),
      new El('td').append(new El('a', { class: 'op-link', href: 'rapport.php?rapportart=openpost&udlign=7' })),
      new El('td').append(new El('label', { class: 'checkContainerOrdreliste' }).append(new El('input', { type: 'checkbox', name: 'kontoudtog[1]' }))))
    const bar = new El('tr', { class: 'op-actionbar' }).append(new El('td').append(new El('input', { type: 'submit', value: 'Mail kontoudtog', name: 'submit' }),
      new El('input', { type: 'submit', value: 'Opret rykker', name: 'submit' }), new El('input', { type: 'button', value: 'Udlign alle' }),
      new El('input', { type: 'submit', value: 'Ryk alle', name: 'submit' })))
    table.append(new El('tbody').append(row, bar))
    const back = new El('a', { accesskey: 'l', href: 'rapport.php' }).append(new El('button'))
    return page(back, sel, new El('div', { class: 'op-search' }).append(new El('input', { type: 'text', name: 'kontonr' }), new El('input', { type: 'button' })), table, ...extra)
  }
  const reminders = () => [new El('div', { id: 'opRykkere' }),
    new El('table', { id: 'rykkerOverviewTable1' }).append(new El('tbody').append(new El('tr').append(new El('td').append(new El('a', { href: 'rykker.php?rykker_id=12' })))),
      new El('tfoot').append(new El('tr').append(new El('td').append(new El('input', { type: 'submit', onclick: "return confirmSubmit('Slet valgte ?')" }),
        new El('input', { type: 'submit', onclick: "return confirmSubmit('Udskriv valgte ?')" }), new El('input', { type: 'submit', onclick: "return confirmSubmit('Bogfør valgte ?')" })))))]
  const op = openpost('vis_aabenpost', reminders())
  s = st(op)
  eq([s.state, s.data.mode, s.flags.forfaldne, s.flags.udlignKlar, s.flags.rykkere, s.flags.aabneRykkere, s.flags.bogfoerteRykkere, s.flags.tom],
    ['openpost', 'vis_aabenpost', true, true, true, true, false, false])
  eq(RP.order(ctxFor(op, URL0), s).slice(0, 4), ['RY1', 'RY2', 'OP2', 'OP4'])
  assert.equal(ring('OP2', op).getAttribute('value'), 'Opret rykker')
  assert.equal(ring('OP4', op).getAttribute('value'), 'Udlign alle')
  assert.equal(ring('OP1', op).getAttribute('href').includes('aging_bucket=over90'), true)
  assert.ok(ring('RY2', op).getAttribute('onclick').includes('Bogf'), 'Bogfør in Åbne rykkere')
  assert.equal(ring('RY5', op).getAttribute('onclick').includes('Slet'), true)
  assert.equal(ring('RY3', op), null, 'no Bogførte rykkere on the page')
  assert.equal(ring('OP11', op).tagName, 'BUTTON')
  assert.equal(ring('KO4', op), null, 'Tilbage of the kontokort rings nothing on Åbne poster')
  // the views: the current one is the option without a link
  eq([st(openpost('skjul_aabenpost')).flags.kunRykkere, st(openpost('vis_alle_poster')).flags.allePoster, st(openpost('kun_kredit')).flags.debetKredit], [true, true, true])
  eq(RP.order(ctxFor(op, URL0), st(openpost('skjul_aabenpost', reminders()))).slice(0, 3), ['RY1', 'RY2', 'RY3'])
  // Live (test_838): Vis kun rykkere without any reminder is an empty page, so the view comes first
  s = st(openpost('skjul_aabenpost'))
  eq(RP.order(ctxFor(op, URL0), s), ['OP8', 'OP11'])
  eq(RP.about(ctxFor(op, URL0), s), ['help.drap.about.kun_rykkere_tom'])
  eq(RP.about(ctxFor(op, URL0), st(openpost('skjul_aabenpost', reminders()))), ['help.drap.about.kun_rykkere'])
  // only posted reminders open on the page: Send næste rykker first
  const posted = [new El('div', { id: 'opRykkere' }), new El('table', { id: 'rykkerOverviewTable3' }).append(new El('tbody').append(new El('tr').append(new El('td').append(new El('a', { href: 'rykker.php?rykker_id=10' })))))]
  eq(RP.order(ctxFor(op, URL0), st(openpost('skjul_aabenpost', posted))).slice(0, 3), ['RY3', 'RY4', 'RY1'])
  // no account rows: Ingen
  const none = page(new El('select', { name: 'aabenpostmode' }).append(new El('option')), new El('table', { id: 'visAabnePosterTable' }).append(new El('tbody').append(new El('tr').append(new El('td')))))
  s = st(none)
  eq([s.state, s.flags.tom, s.flags.forfaldne], ['openpost', true, false])
  eq(RP.order(ctxFor(none, URL0), s), ['OP8', 'OP9', 'OP11'])
  // a blocked result window (debitor/rapport.php:175-190) is the problem
  const blocked = openpost('vis_aabenpost', [new El('div', { role: 'alert' }).append(new El('a', { href: 'ny_rykker.php?kontoliste=7;', target: '_blank', rel: 'noopener' }))])
  s = st(blocked)
  assert.equal(s.flags.popupBlokeret, true)
  assert.equal(RP.problem(ctxFor(blocked, URL0), s), 'OPP')
  assert.equal(ring('OPP', blocked).getAttribute('target'), '_blank')

  // Kontokort (rapportfunc.php:1236-2032)
  const kk = page(new El('div', { id: 'kkPageFlex' }).append(new El('a', { accesskey: 'L', href: '../index/menu.php' }).append(new El('button')),
    new El('button', { type: 'button', onclick: 'showLangModalKontoprint()' }),
    new El('table', { id: 'kkGridTable' }).append(new El('tbody').append(new El('tr', { class: 'kk-data-row' }).append(
      new El('td').append(new El('a', { onclick: "window.open('../finans/kassekladde.php?kladde_id=3&visipop=on')" })),
      new El('td').append(new El('a', { href: '../includes/udlign_openpost.php?post_id=9' })))))))
  s = st(kk)
  eq([s.state, s.flags.tom, s.flags.aabnePoster, s.flags.udlignede], ['kontokort', false, true, false])
  eq(RP.order(ctxFor(kk, URL0), s).slice(0, 3), ['KO1', 'KO3', 'KO6'])
  assert.equal(ring('KO1', kk).getAttribute('href').includes('udlign_openpost.php'), true)
  assert.equal(ring('KO3', kk).getAttribute('onclick'), 'showLangModalKontoprint()')
  assert.equal(ring('KO4', kk).tagName, 'BUTTON')
  assert.equal(ring('KD3', kk), null, 'the customer ledger\'s Tilbage only counts there')

  // Kontosaldo (rapportfunc.php:2181-2299)
  const ks = page(new El('div', { id: 'ksPageFlex' }), new El('table', { id: 'ksGridTable' }).append(new El('tbody').append(new El('tr')), new El('tfoot')))
  s = st(ks)
  eq([s.state, s.flags.tom], ['kontosaldo', false])
  eq(RP.order(ctxFor(ks, URL0), s), ['KS1', 'KS2', 'KO4'])

  // The customer's kontokort from Åbne poster (accountChart.php:343-382)
  const kd = (current) => page(new El('a', { accesskey: 'L', href: 'rapport.php?rapportart=openpost' }).append(new El('button')),
    new El('select', { name: 'typeSelect' }).append(new El('option'), new El('option', { value: 'rapport.php?rapportart=accountChart&kilde=' + (current === 'all' ? 'openpost' : 'show_all') })),
    new El('td', { onclick: "javascript:kontoprint=window.open('kontoprint.php?dato_fra=&kilde=openpost')" }).append(new El('button')),
    new El('td', { onclick: "javascript:kontoprint=window.open('mail_kontoudtog.php?kontoantal=1&kontoliste=7')" }).append(new El('button', { id: 'mail' })))
  s = st(kd('open'))
  eq([s.state, s.flags.allePoster], ['kontodetalje', false])
  assert.equal(st(kd('all')).flags.allePoster, true)
  assert.equal(ring('KD2', kd('open')).getAttribute('id'), 'mail')
  assert.equal(ring('KO3', kd('open')).parent.getAttribute('onclick').includes('kontoprint.php'), true)
  assert.equal(ring('KD3', kd('open')).tagName, 'BUTTON')

  // field keys (showOpenPosts.php:1195, accountChart.php:356, rapportfunc.php:2024)
  eq([RP.fieldKey(new El('input', { name: 'kontoudtog[3]' })), RP.fieldKey(new El('select', { name: 'typeSelect' })), RP.fieldKey(new El('select', { id: 'kkPageSize' })), RP.fieldKey(new El('input', { name: 'dato' }))],
    ['kontoudtog', 'visning', 'linjer', null])
})

test('D3 rykkerbrev: open and posted reminders, mail and the Inkasso button', () => {
  const RK = C.pages['debitor/rykker.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/debitor/rykker.php?rykker_id=12'
  const page = ({ open = true, mail = false, email = 'kunde@example.com', inkasso = '', ready = false } = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('a', { href: 'rapport.php?rapportart=openpost', accesskey: 'L' }), new El('form', { name: 'rykker' }),
      new El('input', { type: 'hidden', name: 'rykker_id', value: '12' }), new El('input', { type: 'hidden', name: 'felt_5', value: inkasso }),
      new El('input', { type: 'text', name: 'email', value: email }), new El('input', mail ? { type: 'checkbox', name: 'mail_fakt', checked: 'checked' } : { type: 'checkbox', name: 'mail_fakt' }),
      new El('select', { name: 'ny_valuta' }), new El('input', { type: 'text', name: 'beskrivelse[1]' }))
    if (open) body.append(new El('input', { type: 'checkbox', name: 'rykkerbox[1]' }), new El('input', { type: 'text', name: 'ny_beskrivelse' }))
    body.append(new El('input', { type: 'submit', name: 'submit', value: 'Opdatér', id: 'upd' }))
    body.append(mail ? new El('input', { type: 'submit', name: 'submit', value: 'Send', onclick: "return confirm('Send rykker som mail til x?')" })
      : new El('input', { type: 'submit', name: 'submit', value: 'Udskriv', id: 'print' }))
    if (open) body.append(new El('input', { type: 'submit', name: 'submit', value: 'Slet valgte', id: 'del' }))
    if (ready) body.append(new El('input', { type: 'submit', name: 'inkasso', value: 'Inkasso', onclick: "return confirm('Send sagen til inkasso?')" }))
    return doc
  }
  const ring = (id, doc) => { const ctx = ctxFor(doc, URL0); const s = RK.detect(ctx); const t = RK.tasks[id].show.target; return typeof t === 'function' ? t(ctx, s) : doc.querySelector(t) }
  let doc = page()
  let s = RK.detect(ctxFor(doc, URL0))
  eq([s.state, s.flags.mail, s.flags.ingenEmail, s.flags.inkasso, s.flags.inkassoKlar], ['aaben', false, false, false, false])
  eq(RK.order(ctxFor(doc, URL0), s).slice(0, 3), ['RK1', 'RK2', 'RK3'])
  // Live: a customer without an e-mail address gets Udskriv first
  eq(RK.order(null, RK.detect(ctxFor(page({ email: '' }), URL0))).slice(0, 3), ['RK2', 'RK1', 'RK3'])
  eq(RK.order(null, RK.detect(ctxFor(page({ open: false, email: '' }), URL0))).slice(0, 3), ['RK9', 'RK2', 'RK1'])
  assert.equal(ring('RK2', doc).getAttribute('id'), 'print')
  assert.equal(ring('RK4', doc).getAttribute('id'), 'del')
  assert.equal(ring('RK1', doc), null, 'no Send before Send pr. mail is saved')
  eq(RK.fieldKey(new El('input', { name: 'dkpris[2]' })), 'pris')
  eq(RK.fieldKey(new El('select', { name: 'newFormLanguage' })), 'sprog')
  // mail on, no valid address: the address first; no Udskriv on the page
  doc = page({ mail: true, email: '' })
  s = RK.detect(ctxFor(doc, URL0))
  eq([s.flags.mail, s.flags.ingenEmail], [true, true])
  eq(RK.order(ctxFor(doc, URL0), s).slice(0, 3), ['RK8', 'RK1', 'RK3'])
  assert.equal(RK.problem(ctxFor(doc, URL0), s), 'RK8', 'Send pr. mail without a usable address is the problem card')
  assert.equal(RK.problem(ctxFor(page(), URL0), RK.detect(ctxFor(page(), URL0))), '', 'a valid address, no problem')
  assert.equal(RK.problem(ctxFor(page({ email: '' }), URL0), RK.detect(ctxFor(page({ email: '' }), URL0))), '', 'no address is fine for Udskriv')
  s = RK.detect(ctxFor(page({ open: false, mail: true, email: '', ready: true }), URL0))
  eq([s.state, RK.problem(null, s), RK.order(null, s).slice(0, 2)], ['bogfoert', 'RK8', ['RK8', 'RK6']])
  assert.equal(ring('RK2', doc), null)
  assert.equal(ring('RK1', doc).getAttribute('value'), 'Send')
  assert.equal(ring('RK4', doc).getAttribute('id'), 'del', 'Slet valgte is the last plain submit with mail too')
  // posted, ready for debt collection
  doc = page({ open: false, mail: true, ready: true })
  s = RK.detect(ctxFor(doc, URL0))
  eq([s.state, s.flags.inkassoKlar], ['bogfoert', true])
  eq(RK.order(ctxFor(doc, URL0), s).slice(0, 3), ['RK6', 'RK9', 'RK1'])
  assert.equal(ring('RK4', doc), null, 'no Slet valgte on a posted reminder')
  assert.equal(ring('RK7', doc), null, 'a posted reminder is not posted again')
  assert.equal(ring('RK7', page()).getAttribute('href'), 'rapport.php?rapportart=openpost')
  assert.equal(RK.name(ctxFor(doc, URL0), RK.detect(ctxFor(page({ open: false, inkasso: 'inkasso' }), URL0))), 'help.dryk.name_inkasso')
})

test('D3 betalinger: the payment list, its overview and the PBS import', () => {
  const BT = C.pages['debitor/betalinger.php']
  const BL = C.pages['debitor/betalingsliste.php']
  const PI = C.pages['debitor/pbs_import.php']
  const page = (...kids) => { const doc = new Doc(); doc.append(new El('body').append(...kids)); return doc }
  const U = (p) => 'https://ssl12.saldi.dk/asr_hj/debitor/' + p
  const line = (red) => new El('tr').append(new El('td', red ? { bgcolor: '#FF0000' } : {}).append(new El('input', { type: 'text', name: 'til_kto[1]', id: 'kto' })),
    new El('td').append(new El('input', { type: 'text', name: 'belob[1]' })), new El('td').append(new El('input', { type: 'text', name: 'betalingsdato[1]' })),
    new El('td').append(new El('label', { class: 'checkContainerOrdreliste' }).append(new El('input', { type: 'checkbox', name: 'slet[1]' }))))
  const head = () => [new El('a', { href: 'betalingsliste.php', accesskey: 'L' }), new El('select', { name: 'find' }), new El('input', { type: 'text', name: 'listenote' })]
  // empty open list (betalinger.php:582-587)
  let doc = page(...head())
  let s = BT.detect(ctxFor(doc, U('betalinger.php?liste_id=4')))
  eq([s.state, s.flags.fejl], ['tom', false])
  eq(BT.order(ctxFor(doc, U('betalinger.php')), s), ['BT1', 'BT9'])
  // lines with an error: Slet røde instead of Udskriv og luk (:600-604)
  doc = page(...head(), new El('table').append(line(true)), new El('input', { type: 'submit', name: 'gem', value: 'Gem' }), new El('input', { type: 'submit', name: 'slet_ugyldige' }), new El('input', { type: 'submit', name: 'mail' }))
  let ctx = ctxFor(doc, U('betalinger.php?liste_id=4'))
  s = BT.detect(ctx)
  eq([s.state, s.flags.fejl, s.flags.mail], ['aaben', true, true])
  assert.equal(BT.problem(ctx, s), 'BT2')
  assert.ok(!BT.order(ctx, s).includes('BT4'), 'no file while there are errors')
  assert.equal(BT.tasks.BT2.show.target(ctx, s).getAttribute('id'), 'kto')
  assert.equal(BT.tasks.BT7.show.target(ctx, s).tagName, 'LABEL')
  eq(BT.fieldKey(new El('input', { name: 'til_kto[1]' })), 'til_kto')
  // no errors
  doc = page(...head(), new El('table').append(line(false)), new El('select', { name: 'format' }), new El('input', { type: 'submit', name: 'udskriv' }))
  ctx = ctxFor(doc, U('betalinger.php?liste_id=4'))
  s = BT.detect(ctx)
  eq([s.state, s.flags.fejl, BT.problem(ctx, s)], ['aaben', false, ''])
  eq(BT.order(ctx, s).slice(0, 3), ['BT3', 'BT4', 'BT6'])
  assert.equal(BT.tasks.BT4.show.target(ctx, s).getAttribute('name'), 'udskriv')
  assert.equal(BT.tasks.BT8.show.target(ctx, s), null, 'Udskriv of a closed list only there')
  // closed list (:605-614)
  doc = page(new El('a', { href: 'betalingsliste.php', accesskey: 'L' }), new El('select', { name: 'format' }), new El('input', { type: 'submit', name: 'udskriv' }))
  s = BT.detect(ctxFor(doc, U('betalinger.php?liste_id=4')))
  eq([s.state, BT.order(ctxFor(doc, U('betalinger.php')), s)], ['lukket', ['BT8']])

  // Betalingsliste (betalingsliste.php:47-211)
  doc = page(new El('a', { href: 'rapport.php', accesskey: 'L' }), new El('a', { href: 'betalinger_settings.php' }), new El('a', { href: 'betalinger.php?id=0', accesskey: 'N' }),
    new El('a', { href: 'betalingsliste.php?sort=id&rf=desc&vis=alle' }), new El('a', { href: 'betalinger.php?tjek=3&liste_id=3&returside=betalingsliste.php' }),
    new El('span', { onclick: "location.href = 'betalingsliste.php?sort=id&rf=desc&deleteList=3'" }))
  s = BL.detect(ctxFor(doc, U('betalingsliste.php')))
  eq([s.state, s.flags.visAlle], ['liste', false])
  eq(BL.order(ctxFor(doc, U('betalingsliste.php')), s).slice(0, 3), ['BL2', 'BL1', 'BL3'])
  doc = page(new El('span', { onclick: "location.href = 'betalingsliste.php?sort=id&rf=desc&vis=alle&deleteList=3&confirmDelete=yes'" }))
  eq(BL.detect(ctxFor(doc, U('betalingsliste.php?deleteList=3'))).state, 'slet')

  // Import fra PBS (pbs_import.php:46-195): a missing PBS number is the problem
  doc = new Doc()
  doc.append(new El('body', {}, { text: 'Luk Import fra PBS PBS nr mangler i stamdata (Indstillinger -> Stamdata) Import af PBS data' }).append(
    new El('a', { href: 'ordreliste.php?', accesskey: 'L' }), new El('input', { type: 'file', name: 'uploadedfile' })))
  ctx = ctxFor(doc, U('pbs_import.php?returside=ordreliste.php'))
  s = PI.detect(ctx)
  eq([s.state, s.flags.manglerPbsNr, PI.problem(ctx, s)], ['upload', true, 'PI3'])
  assert.equal(doc.querySelector(PI.tasks.PI4.show.target).getAttribute('href'), 'ordreliste.php?')
})

test('D3 genfakturering: Genfakturer, Ret abonnementsordrer and Massefakturering', () => {
  const GF = C.pages['debitor/genfakturer.php']
  const RG = C.pages['debitor/ret_genfakt.php']
  const MF = C.pages['debitor/massefakt.php']
  const U = (p) => 'https://ssl12.saldi.dk/asr_hj/debitor/' + p
  const page = (bodyText, ...kids) => { const doc = new Doc(); doc.append(new El('body', {}, { text: bodyText }).append(...kids)); return doc }
  let doc = page('', new El('form', { name: 'genfakturer' }).append(new El('input', { type: 'radio', name: 'komplet', value: '1' }), new El('input', { type: 'submit', name: 'Ok' }), new El('input', { type: 'submit', name: 'Afbryd' })))
  let s = GF.detect(ctxFor(doc, U('genfakturer.php?id=-1&ordre_antal=2&genfakt=4,5')))
  eq([s.state, GF.order(ctxFor(doc, U('genfakturer.php')), s).slice(0, 3)], ['valg', ['GF1', 'GF2', 'GF5']])
  assert.equal(doc.querySelector(GF.tasks.GF5.show.target).getAttribute('name'), 'Ok')
  // Ret abonnementsordrer: a failed check hides Fakturer (ret_genfakt.php:240-257, :659-661)
  const rg = (fakturer, sync) => page('', new El('form', { name: 'ret_genfakt' }), new El('input', { type: 'text', name: 'fakturadato_0' }), new El('input', { type: 'text', name: 'genfakt_0' }),
    ...(sync ? [new El('input', { type: 'checkbox', name: 'sync_stamdata_0' })] : []),
    new El('input', { type: 'submit', name: 'submit', value: 'Gem' }), ...(fakturer ? [new El('input', { type: 'submit', name: 'submit', value: 'Fakturer' })] : []))
  let ctx = ctxFor(rg(false, false), U('ret_genfakt.php'))
  s = RG.detect(ctx)
  eq([s.state, RG.problem(ctx, s)], ['fejl', 'RG7'])
  ctx = ctxFor(rg(true, true), U('ret_genfakt.php'))
  s = RG.detect(ctx)
  eq([s.state, s.flags.forskel, RG.problem(ctx, s), RG.order(ctx, s).slice(0, 3)], ['ret', true, '', ['RG3', 'RG1', 'RG2']])
  assert.equal(ctx.doc.querySelector(RG.tasks.RG5.show.target).getAttribute('value'), 'Fakturer')
  // Massefakturering: the run and its end (massefakt.php:56, :106-112, :195)
  s = MF.detect(ctxFor(page('Luk Massefakturering Vent - 12 ordrer tilbage'), U('massefakt.php?valg=ordrer')))
  assert.equal(s.state, 'koerer')
  doc = page('Luk Massefakturering Ingen ordrer til levering', new El('a', { href: '../debitor/ordreliste.php?valg=ordrer', accesskey: 'L' }))
  s = MF.detect(ctxFor(doc, U('massefakt.php?valg=ordrer')))
  eq([s.state, MF.order(ctxFor(doc, U('massefakt.php')), s)], ['faerdig', ['MF1', 'MF2']])
})

test('D3 statistik: Top 100, Salg pr. postnummer and Kassespor', () => {
  const TP = C.pages['debitor/top100.php']
  const SP = C.pages['debitor/salg_postnr.php']
  const KP = C.pages['debitor/kassespor.php']
  const U = (p) => 'https://ssl12.saldi.dk/asr_hj/debitor/' + p
  const page = (...kids) => { const doc = new Doc(); doc.append(new El('body').append(...kids)); return doc }
  // Top 100 (top100.php:84-150)
  let doc = page(new El('a', { href: '../debitor/rapport.php', accesskey: 'L' }).append(new El('button', { id: 'back' })),
    new El('a', { href: 'top100.php?periode=081125:081126&ret=on', accesskey: 'P' }).append(new El('button', { id: 'per' })), new El('table', { id: 't1GridTable' }))
  let ctx = ctxFor(doc, U('top100.php'))
  let s = TP.detect(ctx)
  eq([s.state, TP.order(ctx, s)], ['liste', ['TP2', 'TP1', 'TP3']])
  eq([TP.tasks.TP1.show.target(ctx, s).getAttribute('id'), TP.tasks.TP3.show.target(ctx, s).getAttribute('id')], ['per', 'back'])
  doc = page(new El('input', { type: 'text', name: 'periode', value: '081125:081126' }))
  eq(TP.detect(ctxFor(doc, U('top100.php?ret=on'))).state, 'periode')
  // Salg pr. postnummer (salg_postnr.php:119-460)
  doc = page(new El('div', { id: 'datatable-wrapper-salg_postnr' }).append(new El('table').append(new El('thead').append(new El('tr').append(
    new El('th', { class: 'belob sortable-td', onclick: "setSortsalg_postnr('belob', 'asc')" }))),
  new El('input', { type: 'text', id: 'dato_range_picker' }), new El('select', { name: 'search[salg_postnr][afd]' }))))
  ctx = ctxFor(doc, U('salg_postnr.php?search%5Bsalg_postnr%5D%5Bseneste_ordre%5D=2026-01-01%3A2026-06-30'))
  s = SP.detect(ctx)
  eq([s.state, s.flags.filter], ['liste', true])
  assert.equal(SP.detect(ctxFor(doc, U('salg_postnr.php?search%5Bsalg_postnr%5D%5Bpostnr%5D=%3A'))).flags.filter, false, 'an empty range is no filter')
  assert.equal(doc.querySelector(SP.tasks.SP4.show.target).getAttribute('onclick').includes('belob'), true)
  assert.ok(doc.querySelector(SP.tasks.SP3.show.target))
  // Kassespor (kassespor.php:234-725)
  doc = page(new El('form', { name: 'bonliste' }), new El('input', { type: 'text', name: 'fakturadatoer' }), new El('table', { id: 'ksGridTable' }).append(new El('tr').append(
    new El('td').append(new El('a', { href: 'pos_ordre.php?id=55&returside=kassespor.php' })),
    new El('td').append(new El('a', { href: 'kassespor.php?ret_bet_id=9&bon=101&sum=100&betal_type=Kontant' })))))
  ctx = ctxFor(doc, U('kassespor.php'))
  s = KP.detect(ctx)
  eq([s.state, s.flags.ikkeBogfoert, KP.order(ctx, s).slice(0, 3)], ['liste', true, ['KP1', 'KP2', 'KP3']])
  assert.equal(KP.tasks.KP3.show.target(ctx, s).getAttribute('href').includes('ret_bet_id='), true)
  doc = page(new El('select', { name: 'ny_betal_type' }))
  s = KP.detect(ctxFor(doc, U('kassespor.php?ret_bet_id=9')))
  eq([s.state, KP.order(ctxFor(doc, U('kassespor.php')), s)], ['retBetaling', ['KP6']])
  // Salgsstatistik (includes/salgsstat.php:120-327, :491-520)
  const SS = C.pages['includes/salgsstat.php']
  const SSU = 'https://ssl12.saldi.dk/asr_hj/includes/salgsstat.php?art=D'
  doc = page(new El('div', { id: 'ssPageFlex' }).append(new El('a', { href: '../debitor/rapport.php', accesskey: 'L' }).append(new El('button', { id: 'back' })),
    new El('a', { href: 'salgsstat.php?dato_fra=&art=D&ret=on', accesskey: 'B' }).append(new El('button', { id: 'soeg' }))),
  new El('table', { id: 'ssGridTable' }).append(new El('tbody').append(new El('tr', { class: 'ss-data-row' }))))
  ctx = ctxFor(doc, SSU)
  s = SS.detect(ctx)
  eq([s.state, s.flags.tom, SS.order(ctx, s)], ['liste', false, ['SS3', 'SS1', 'SS2', 'SS4']])
  eq([SS.tasks.SS1.show.target(ctx, s).getAttribute('id'), SS.tasks.SS2.show.target(ctx, s), SS.tasks.SS4.show.target(ctx, s).getAttribute('id')], ['soeg', null, 'back'])
  doc = page(new El('input', { type: 'text', name: 'varetekst' }), new El('label').append(new El('input', { type: 'checkbox', name: 'detaljer' })), new El('input', { type: 'submit', name: 'find' }))
  ctx = ctxFor(doc, SSU + '&ret=on')
  s = SS.detect(ctx)
  eq([s.state, SS.order(ctx, s)], ['soeg', ['SS1', 'SS2']])
  eq([SS.tasks.SS1.show.target(ctx, s).getAttribute('name'), SS.tasks.SS2.show.target(ctx, s).tagName], ['find', 'LABEL'])
})

test('K1 ordreliste: tabs, empty lists, search, locks and the supplier filter', () => {
  const P = C.pages['kreditor/ordreliste.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/kreditor/ordreliste.php'
  /** The supplier order list (kreditor/ordreliste.php, includes/kreditorOrderFuncIncludes/topLine.php and the grid). */
  function page ({ tab = 'ordrer', rows = 0, search = '', locked = false, fromCard = false, forslagTab = true, setup = false, offset = '0' } = {}) {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    if (fromCard) body.append(new El('a', { href: 'kreditorkort.php?id=7', accesskey: 'L' }).append(new El('button')))
    if (forslagTab && tab !== 'forslag') body.append(new El('a', { href: 'ordreliste.php?sort=&valg=forslag' }).append(new El('button')))
    if (tab !== 'ordrer') body.append(new El('a', { href: 'ordreliste.php?valg=ordrer' }).append(new El('button', { id: 'ordrer' })))
    if (tab !== 'faktura') body.append(new El('a', { href: 'ordreliste.php?valg=faktura' }).append(new El('button')))
    body.append(new El('a', { href: 'ordre.php?returside=ordreliste.php' }).append(new El('button', { id: 'ny' })))
    const id = 'kredorliste_' + tab
    const wrapper = new El('div', { class: 'datatable-wrapper', id: 'datatable-wrapper-' + id })
    body.append(wrapper)
    const table = new El('table', { class: 'datatable', id: 'datatable-' + id })
    wrapper.append(table)
    if (setup) {
      table.append(new El('tr').append(new El('td').append(new El('button', { type: 'button', onclick: 'updateQueryParameter("menu[' + id + ']", "menu[' + id + ']", "main");' }))))
      return doc
    }
    const thead = new El('thead')
    const sr = new El('tr')
    for (const f of ['ordrenr', 'ordredate', 'firmanavn', 'sum']) sr.append(new El('th').append(new El('input', { type: 'text', name: 'search[' + id + '][' + f + ']', value: f === 'firmanavn' ? search : '' })))
    sr.append(new El('th').append(new El('div', { class: 'dropdown' }).append(new El('button', { type: 'button', class: 'dropdown-trigger', 'aria-label': 'Handlinger' }))))
    thead.append(sr)
    table.append(thead)
    const tbody = new El('tbody')
    table.append(tbody)
    for (let i = 0; i < rows; i++) {
      const td = new El('td').append(new El('a', { href: 'ordre.php?tjek=' + (i + 1) + '&id=' + (i + 1) + '&returside=ordreliste.php' }))
      if (locked && i === 0) td.children[0].append(new El('span', { class: 'fa fa-user', title: 'I brug af: kim' }))
      tbody.append(new El('tr', { 'data-order-id': String(i + 1) }).append(td, new El('td').append(new El('a', { href: 'formularprint.php?id=' + (i + 1) + '&formular=13&udskriv_til=PDF' }))))
    }
    tbody.append(new El('tr', { class: 'filler-row' }).append(new El('td')))
    wrapper.append(new El('input', { type: 'hidden', name: 'offset[' + id + ']', value: offset }))
    return doc
  }
  const run = (doc, url = URL0) => { const ctx = ctxFor(doc, url); return { ctx, st: P.detect(ctx) } }

  let { st } = run(page({ tab: 'ordrer', rows: 0 }))
  eq([st.state, st.flags.tom, st.flags.soegning, st.flags.udenForslag], ['ordrer', true, false, false])
  eq(P.order(null, st).slice(0, 3), ['KOL1', 'KOL2', 'KOL3'], 'an empty list: create, the whole flow, import')
  eq(P.about(null, st), ['help.kol.about.hvad', 'help.kol.about.uden_varer'])
  eq(P.questionOrder(null, st)[0], 'Q2', 'bills without items first on an empty list')

  st = run(page({ tab: 'faktura', rows: 0 })).st
  eq([st.state, P.order(null, st).slice(0, 3)], ['faktura', ['KOL2', 'KOL1', 'KOL14']])
  eq(P.questionOrder(null, st), ['Q2', 'Q1', 'Q4'], 'an empty Faktura tab: no sending an order to the supplier')
  eq(P.questionOrder(null, { state: 'faktura', flags: {}, data: {} })[0], 'Q4', 'Faktura leads with correcting a posted invoice')
  eq(P.questions.Q4.steps, ['help.kol.task.kol9.step1', 'help.kol.task.kol9.step2', 'help.kol.task.kol9.step3', 'help.kol.task.kol9.step4'])
  eq(P.about(null, st), ['help.kol.about.faktura_tom', 'help.kol.about.uden_varer'])

  st = run(page({ tab: 'ordrer', rows: 3, search: 'gross' })).st
  eq([st.flags.tom, st.flags.soegning, P.order(null, st)[0]], [false, true, 'KOL5'])
  st = run(page({ tab: 'ordrer', rows: 0, search: 'gross' })).st
  eq([P.about(null, st), P.order(null, st).slice(0, 2)], [['help.kol.about.soegning_tom'], ['KOL5', 'KOL4']])

  // From a supplier card the kontonr search comes back on every submit: no Ryd søgning first, the card's paragraph
  st = run(page({ tab: 'faktura', rows: 2, search: '1001', fromCard: true }), URL0 + '?konto_id=7&valg=faktura').st
  eq([st.flags.leverandoer, P.order(null, st)[0], P.about(null, st)[1]], [true, 'KOL4', 'help.kol.about.leverandoer'])

  st = run(page({ tab: 'ordrer', rows: 2, locked: true })).st
  eq([st.flags.laast, P.order(null, st)[0]], [true, 'KOL10'])
  st = run(page({ tab: 'ordrer', rows: 2, forslagTab: false })).st
  assert.equal(st.flags.udenForslag, true, 'quick invoicing: no Forslag tab')
  assert.ok(P.order(null, st).indexOf('KOL6') >= 0 && P.order(null, st).indexOf('KOL6') < 3)
  st = run(page({ tab: 'forslag', rows: 1 })).st
  eq([st.state, st.flags.udenForslag, P.order(null, st)[0]], ['forslag', false, 'KOL6'])
  st = run(page({ tab: 'ordrer', rows: 3, offset: '100' })).st
  assert.equal(st.flags.mange, true, 'a later page')
  st = run(page({ tab: 'ordrer', setup: true })).st
  eq([st.state, P.order(null, st)], ['opsaetning', ['KOL13', 'KOL12']])

  // Targets: a row only on the tabs the task is about; the Faktura tab link, the menu, the first search field
  const resolve = (ctx, show) => typeof show.target === 'function' ? show.target(ctx, null) : ctx.doc.querySelector(show.target)
  let r = run(page({ tab: 'ordrer', rows: 2 }))
  assert.equal(resolve(r.ctx, P.tasks.KOL2.show).getAttribute('data-order-id'), '1')
  assert.equal(resolve(r.ctx, P.tasks.KOL7.show).getAttribute('data-order-id'), '1')
  assert.equal(resolve(r.ctx, P.tasks.KOL9.show), null, 'Krediter rings no open order')
  assert.equal(resolve(r.ctx, P.tasks.KOL6.show), null, 'with a Forslag tab, Godkend rings no approved order')
  assert.equal(resolve(r.ctx, P.tasks.KOL8.show).getAttribute('href'), 'ordreliste.php?valg=faktura')
  assert.equal(resolve(r.ctx, P.tasks.KOL5.show).getAttribute('aria-label'), 'Handlinger')
  assert.equal(resolve(r.ctx, P.tasks.KOL4.show).name, 'search[kredorliste_ordrer][firmanavn]')
  assert.equal(resolve(r.ctx, P.tasks.KOL1.show).getAttribute('id'), 'ny')
  r = run(page({ tab: 'faktura', rows: 2 }))
  assert.equal(resolve(r.ctx, P.tasks.KOL9.show).getAttribute('data-order-id'), '1')
  assert.equal(resolve(r.ctx, P.tasks.KOL2.show), null, 'Bogfør rings no posted invoice')
  assert.equal(resolve(r.ctx, P.questions.Q3.show), null)
  r = run(page({ tab: 'ordrer', rows: 2, forslagTab: false }))
  assert.equal(resolve(r.ctx, P.tasks.KOL6.show).getAttribute('data-order-id'), '1', 'quick invoicing: proposals are on Ordrer')
  r = run(page({ tab: 'ordrer', setup: true }))
  assert.ok(resolve(r.ctx, P.tasks.KOL13.show))

  // Field keys: Tilbudsdato on Forslag, one sum key per tab
  const fk = (name) => P.fieldKey(new El('input', { name }))
  eq([fk('search[kredorliste_forslag][ordredate]'), fk('search[kredorliste_ordrer][ordredate]'), fk('search[kredorliste_faktura][sum]'),
    fk('search[kredorliste_forslag][sum]'), fk('search[kredorliste_ordrer][sum]'), fk('rowcount[kredorliste_ordrer]')],
  ['search:tilbudsdato', 'search:ordredate', 'search:fakturasum', 'search:forslagssum', 'search:ordresum', 'rowcount'])
  for (const k of ['search:tilbudsdato', 'search:ordredate', 'search:fakturasum', 'search:forslagssum', 'search:ordresum', 'rowcount']) assert.ok(P.fields[k], k)
})

test('K1 ordre: the states of a purchase order and what stops Modtag and Bogfør', () => {
  const P = C.pages['kreditor/ordre.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/kreditor/ordre.php?id=5'
  /** A purchase order page (kreditor/ordre.php ordreside(), orderIncludes/openOrderData.php, openOrderLines.php, closedOrder.php). */
  function order ({ id = '5', status = 0, art = 'KO', kontonr = '1001', ordredato = '01-11-2026', levdato = '', fakturanr = '', lines = [], buttons = [], clip = 'clip', related = false, omlev = '', split = false } = {}) {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    const form = withElements(new El('form', { name: 'ordre', action: 'ordre.php', method: 'post' }))
    body.append(form)
    if (related) form.append(new El('td').append(new El('a', { href: 'ordre.php?id=9' })))
    for (const [n, v] of [['ordrenr', '17'], ['fakturanr', fakturanr], ['status', String(status)], ['id', id], ['art', art], ['konto_id', kontonr ? '3' : ''], ['lager', '0'], ['omlev', omlev]]) {
      form.append(new El('input', { type: 'hidden', name: n, value: v }))
    }
    if (split) {
      form.append(new El('select', { name: 'MoveItemsTo' }), new El('input', { type: 'submit', name: 'moveOrderLines', value: 'Flyt' }), new El('input', { type: 'submit', name: 'cancel', value: 'Fortryd' }))
      return doc
    }
    if (status < 3) {
      form.append(new El('input', kontonr ? { class: 'inputbox', readonly: 'readonly', name: 'kontonr', value: kontonr } : { class: 'inputbox', type: 'text', name: 'kontonr', value: '' }))
      form.append(new El('input', { type: 'text', name: 'firmanavn', value: kontonr ? 'Grossisten' : '' }))
      if (!id) form.append(new El('a', { href: 'ublimport.php' }))
      form.append(new El('input', { type: 'text', name: 'ordredato', value: ordredato }), new El('input', { type: 'text', name: 'levdato', value: levdato }))
      if (status === 0) form.append(new El('input', { class: 'inputbox', type: 'checkbox', name: 'godkend' }))
      else form.append(new El('input', { class: 'inputbox', type: 'text', name: 'fakturanr', value: fakturanr }))
    } else form.append(new El('input', { type: 'hidden', name: 'levdato', value: levdato }))
    if (id) form.append(new El('a', { href: '../includes/documents.php?source=creditorOrder&&ny=ja&sourceId=' + id }).append(new El('img', { src: '../ikoner/' + clip + '.png' })))
    const tbody = new El('tbody')
    form.append(new El('table').append(tbody))
    lines.forEach((l, i) => {
      const x = i + 1
      const tr = new El('tr')
      tr.append(new El('td').append(new El('input', { type: 'text', name: 'posn' + x, value: String(x) })))
      tr.append(new El('td').append(new El('input', { type: 'text', readonly: 'readonly', name: 'vare' + x, value: l.vare || 'V' + x })))
      tr.append(new El('td').append(new El('input', { type: 'text', name: 'anta' + x, value: String(l.antal || 1) })))
      if (status >= 1 && l.leve !== undefined) {
        tr.append(new El('td').append(new El('input', l.done ? { type: 'text', readonly: 'readonly', name: 'leve' + x, value: '0' } : { type: 'text', name: 'leve' + x, value: String(l.leve) })))
        tr.append(new El('td', {}, { text: '(' + (l.received || 0) + ')' }))
      }
      if (l.batch !== undefined) tr.append(new El('td').append(new El('input', { type: 'text', name: 'batch_batch_no[' + x + ']', value: l.batch })))
      tbody.append(tr)
    })
    const tr0 = new El('tr')
    tr0.append(new El('td').append(new El('input', { type: 'text', name: 'posn0', value: String(lines.length) })))
    if (art !== 'KK') tr0.append(new El('td').append(new El('input', { type: 'text', name: 'vare0' })))
    tbody.append(tr0)
    for (const b of buttons) form.append(new El('input', { type: 'submit', name: b, value: b }))
    return doc
  }
  const run = (doc, url = URL0) => { const ctx = ctxFor(doc, url); const st = P.detect(ctx); return { ctx, st, order: P.order(ctx, st), problem: P.problem(ctx, st) } }
  const resolve = (ctx, show) => typeof show.target === 'function' ? show.target(ctx, null) : ctx.doc.querySelector(show.target)

  // A new order: no id, an empty Kontonr.
  let r = run(order({ id: '', kontonr: '', buttons: ['save', 'lookup', 'csv'] }), 'https://ssl12.saldi.dk/asr_hj/kreditor/ordre.php?returside=ordreliste.php')
  eq([r.st.state, r.order.slice(0, 3), r.problem, P.name(r.ctx, r.st)], ['ny', ['KO1', 'KO2', 'KO3'], '', 'help.kord.name_new'])
  assert.equal(resolve(r.ctx, P.tasks.KO1.show).name, 'kontonr')
  assert.equal(resolve(r.ctx, P.tasks.KO3.show).getAttribute('href'), 'ublimport.php')
  assert.equal(resolve(r.ctx, P.tasks.KO2.show).name, 'lookup', 'Opslag finds the supplier on a new order')

  // A suggestion (status 0): Godkend; without items, add them first
  r = run(order({ status: 0, buttons: ['save', 'lookup', 'delete', 'csv'] }))
  eq([r.st.state, r.st.flags.ingenVarer, r.order.slice(0, 2)], ['forslag', true, ['KO4', 'KO5']])
  eq(P.name(r.ctx, r.st), ['help.kord.name', '17'])
  r = run(order({ status: 0, lines: [{}], buttons: ['save', 'lookup', 'print', 'split', 'csv'] }))
  eq([r.st.state, r.st.flags.ingenVarer, r.order[0]], ['forslag', false, 'KO5'])
  assert.equal(resolve(r.ctx, P.tasks.KO5.show).name, 'godkend')
  assert.equal(resolve(r.ctx, P.tasks.KO11.show), null, 'Opdel on a proposal is no part delivery')
  assert.equal(resolve(r.ctx, P.tasks.KO8.show).tagName, 'TR', 'the first saved line')
  assert.equal(resolve(r.ctx, P.tasks.KO1.show), null, 'Kontonr. is read-only on a saved order')
  // Review (spec §19.3): a control that means something else in this state rings nothing
  assert.equal(resolve(r.ctx, P.tasks.KO2.show), null, 'Opslag on a saved order looks up items, not suppliers')
  assert.equal(resolve(r.ctx, P.tasks.KO6.show).name, 'print')
  assert.equal(resolve(r.ctx, P.tasks.KO20.show), null, 'the print of an open order is not the closed invoice')

  // An approved order waiting for the goods
  r = run(order({ status: 1, levdato: '', lines: [{ leve: 2 }], buttons: ['save', 'lookup', 'receive', 'print', 'split', 'csv'] }))
  eq([r.st.state, r.st.flags.manglerLevdato, r.st.flags.delvis, r.order[0], r.problem], ['ordre', true, false, 'KO9', ''])
  eq(P.about(r.ctx, r.st), ['help.kord.about.ordre', 'help.kord.about.dato'])
  assert.equal(resolve(r.ctx, P.tasks.KO10.show).getAttribute('type'), 'text', 'the visible Fakturanr., not the hidden one')
  // part received: the number in brackets
  r = run(order({ status: 1, levdato: '05-11-2026', lines: [{ leve: 0, received: 4 }, { leve: 3 }], buttons: ['save', 'receive', 'split'] }))
  eq([r.st.flags.delvis, r.st.flags.alleModtaget, r.order.slice(0, 2)], [true, false, ['KO9', 'KO11']])
  // Live: Opdel is a part delivery only after a part receipt; Gem makes the order ready only when all is received
  assert.equal(resolve(r.ctx, P.tasks.KO11.show).name, 'split')
  assert.equal(resolve(r.ctx, P.tasks.KO12.show), null, 'Gem after a part receipt does not make the order ready')
  // everything received, but status 1 until the next Gem: KO12 first
  r = run(order({ status: 1, levdato: '05-11-2026', lines: [{ done: true, leve: 0, received: 2 }], buttons: ['save', 'receive', 'split'] }))
  eq([r.st.flags.alleModtaget, r.order[0]], [true, 'KO12'])
  assert.equal(resolve(r.ctx, P.tasks.KO12.show).name, 'save')
  assert.equal(resolve(r.ctx, P.tasks.KO11.show), null, 'nothing left to split off')
  assert.ok(!r.order.includes('KO11'))
  // a delivery date before the order date stops Modtag: the problem card
  r = run(order({ status: 1, ordredato: '08-11-2026', levdato: '01-10-2026', lines: [{ leve: 1 }], buttons: ['save', 'receive'] }))
  eq([r.st.flags.levdatoFoer, r.problem, r.order[0]], [true, 'KO23', 'KO23'])
  eq(P.about(r.ctx, r.st)[1], 'help.kord.about.dato_foer')
  // a batch line to receive without its batch number
  r = run(order({ status: 1, levdato: '05-11-2026', lines: [{ leve: 2, batch: '' }], buttons: ['save', 'receive'] }))
  eq([r.st.flags.batchMangler, r.problem], [true, 'KO24'])
  assert.equal(resolve(r.ctx, P.tasks.KO24.show).name, 'batch_batch_no[1]')
  r = run(order({ status: 1, levdato: '05-11-2026', lines: [{ leve: 2, batch: 'L7' }], buttons: ['save', 'receive'] }))
  assert.equal(r.st.flags.batchMangler, false)

  // Ready to post: Bogfør; without the invoice number the problem card says so
  r = run(order({ status: 2, levdato: '05-11-2026', fakturanr: '', lines: [{ done: true, leve: 0, received: 1 }], buttons: ['save', 'postNow', 'print'] }))
  eq([r.st.state, r.st.flags.manglerFakturanr, r.problem, r.order.slice(0, 2)], ['klar', true, 'KO10', ['KO10', 'KO14']])
  assert.equal(r.st.flags.delvis, false, 'with Bogfør everything is received (live check)')
  r = run(order({ status: 2, levdato: '05-11-2026', fakturanr: 'F-88', lines: [{ done: true, leve: 0 }], buttons: ['save', 'postNow'], clip: 'paper' }))
  eq([r.st.state, r.problem, r.order.slice(0, 3), r.st.flags.harBilag], ['klar', '', ['KO14', 'KO13', 'KO8'], true])
  assert.equal(resolve(r.ctx, P.tasks.KO14.show).name, 'postNow')
  assert.equal(resolve(r.ctx, P.tasks.KO16.show), null, 'Bogfør kreditnotaen rings nothing on an invoice')
  assert.equal(resolve(r.ctx, P.tasks.KO12.show).name, 'save')
  assert.equal(resolve(r.ctx, P.questions.Q5.show).name, 'postNow')

  // A credit note: approve, return, post; the copied delivery date is usually before today's order date
  r = run(order({ status: 0, art: 'KK', related: true, ordredato: '08-11-2026', levdato: '01-10-2026', lines: [{}], buttons: ['save', 'lookup'] }))
  eq([r.st.state, r.st.flags.ikkeGodkendt, r.problem, P.name(r.ctx, r.st)], ['kreditnota', true, 'KO23', ['help.kord.name_kreditnota', '17']])
  assert.equal(resolve(r.ctx, P.tasks.KO17.show).getAttribute('href'), 'ordre.php?id=9')
  r = run(order({ status: 1, art: 'KK', related: true, levdato: '08-11-2026', ordredato: '08-11-2026', lines: [{ leve: 1 }], buttons: ['save', 'return'] }))
  eq([r.st.state, r.problem, r.order[0]], ['kreditnota', '', 'KO15'])
  r = run(order({ status: 2, art: 'KK', related: true, levdato: '08-11-2026', ordredato: '08-11-2026', fakturanr: '', lines: [{ done: true, leve: 0 }], buttons: ['save', 'postNow'] }))
  eq([r.st.flags.kanBogfoeres, r.problem], [true, 'KO10'])
  assert.equal(resolve(r.ctx, P.tasks.KO16.show).name, 'postNow')
  assert.equal(resolve(r.ctx, P.tasks.KO14.show), null, 'Bogfør fakturaen rings nothing on a credit note')
  assert.equal(resolve(r.ctx, P.questions.Q5.show).name, 'postNow', 'the refusals are the same for a credit note')
  // The start screen's questions (spec §25): the receipt refusal leads when the problem card names it; a posted invoice
  // is corrected and paid, and an open order never offers correcting a posted invoice
  const kq = (state, flags = {}) => P.questionOrder(null, { state, flags, data: {} })
  eq(kq('ordre', { levdatoFoer: true }), ['Q2', 'Q1', 'Q4'])
  eq(kq('ordre'), ['Q1', 'Q2', 'Q4'])
  eq(kq('bogfoert'), ['Q3', 'Q6', 'Q4'])
  for (const s0 of ['ny', 'forslag', 'ordre', 'opdel', 'opslag']) assert.ok(!kq(s0).includes('Q3'), s0)
  assert.equal(resolve(r.ctx, P.tasks.KO17.show).getAttribute('href'), 'ordre.php?id=9')
  assert.equal(resolve(r.ctx, P.tasks.KO22.show), null, 'on a credit note the head link is the invoice')

  // Closed: posted, or closed without posting (status 3); a credited invoice links its credit note
  r = run(order({ status: 4, lines: [{}], buttons: ['copy', 'credit', 'print'] }))
  eq([r.st.state, r.st.flags.ikkeBogfoert, r.order.slice(0, 2), r.problem], ['bogfoert', false, ['KO18', 'KO19'], ''])
  r = run(order({ status: 3, lines: [{}], related: true, buttons: ['copy', 'credit', 'print'] }))
  eq([r.st.flags.ikkeBogfoert, r.st.flags.krediteret, r.order.slice(0, 2)], [true, true, ['KO21', 'KO22']])
  assert.equal(resolve(r.ctx, P.tasks.KO22.show).getAttribute('href'), 'ordre.php?id=9')
  assert.equal(resolve(r.ctx, P.tasks.KO17.show), null, 'on a credited invoice the head link is the credit note')
  assert.equal(resolve(r.ctx, P.tasks.KO20.show).name, 'print')
  assert.equal(resolve(r.ctx, P.tasks.KO6.show), null, 'a closed invoice is not sent to the supplier from here')
  assert.equal(resolve(r.ctx, P.tasks.KO8.show), null, 'the lines of a closed invoice cannot be changed')
  assert.equal(resolve(r.ctx, P.tasks.KO12.show), null)
  r = run(order({ status: 4, art: 'KK', related: true, lines: [{}] }))
  eq([r.st.state, r.order], ['bogfoertKreditnota', ['KO17', 'KO13']])

  // The split view
  r = run(order({ status: 1, split: true }))
  eq([r.st.state, r.order], ['opdel', ['KO27']])

  // The supplier lookup: a list, or the overlay when nothing matches
  const lookup = (rows, search) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    const table = new El('table', { class: 'datatable', id: 'datatable-kreditor_lookup' })
    table.append(new El('thead').append(new El('tr').append(new El('th').append(new El('input', { type: 'text', name: 'search[kreditor_lookup][firmanavn]', value: search })))))
    const tbody = new El('tbody')
    for (let i = 0; i < rows; i++) tbody.append(new El('tr').append(new El('td', { onclick: "selectAccount('kontonr', '" + (i + 1) + "')" })))
    tbody.append(new El('tr', { class: 'filler-row' }).append(new El('td')))
    table.append(tbody)
    body.append(table)
    const overlay = new El('div', { class: 'create-creditor-overlay', id: 'createCreditorForm' })
    const cf = new El('form', { name: 'create_creditor', action: 'ordre.php', method: 'post' })
    cf.append(new El('input', { type: 'text', name: 'firmanavn', value: search }), new El('select', { name: 'grp' }), new El('input', { type: 'submit', name: 'create_creditor', value: 'Opret' }))
    body.append(overlay.append(cf))
    return doc
  }
  r = run(lookup(2, 'gro'), 'https://ssl12.saldi.dk/asr_hj/kreditor/ordre.php')
  eq([r.st.state, r.st.flags.opret, r.order[0]], ['opslag', false, 'KO26'])
  assert.equal(resolve(r.ctx, P.tasks.KO26.show).tagName, 'TR')
  r = run(lookup(0, 'zzz'), 'https://ssl12.saldi.dk/asr_hj/kreditor/ordre.php')
  eq([r.st.state, r.st.flags.opret, r.order[0]], ['opslag', true, 'KO25'])
  const cfDoc = r.ctx.doc
  // Live: the overlay has a Kontonr. of its own (the new supplier's number), which is not the order's
  cfDoc.querySelector('form[name="create_creditor"]').append(new El('input', { type: 'text', name: 'kontonr', value: '1003' }))
  assert.equal(resolve(r.ctx, P.tasks.KO1.show), null)
  eq([P.fieldKey(cfDoc.querySelector('form[name="create_creditor"] input[name="firmanavn"]')), P.fieldKey(cfDoc.querySelector('select[name="grp"]')),
    P.fieldKey(cfDoc.querySelector('input[name="search[kreditor_lookup][firmanavn]"]'))], ['ny_firmanavn', 'ny_grp', 'search:firmanavn'])

  // The field keys of the order lines
  const fk = (name) => P.fieldKey(new El('input', { name }))
  eq([fk('vare0'), fk('anta12'), fk('leve3'), fk('lev_varenr0'), fk('batch_due_date[2]'), fk('omvbet[1]'), fk('mQt[1]'), fk('mSQt[1]'), fk('levdato'), fk('addr1')],
    ['vare', 'anta', 'leve', 'lev_varenr', 'batch_due_date', 'omvbet', 'flyt_antal', 'flyt_modtaget', 'levdato', 'addr'])
  assert.equal(P.fieldKey(new El('select', { name: 'MoveItemsTo' })), 'flyt_til')
})

test('K1 ublimport: upload, the missing supplier or item, and import', () => {
  const P = C.pages['kreditor/ublimport.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/kreditor/ublimport.php'
  const page = (kind, extra = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('a', { href: 'ordre.php', accesskey: 'L' }).append(new El('button', { type: 'button' })))
    if (kind === 'upload') body.append(new El('form', { enctype: 'multipart/form-data' }).append(new El('input', { name: 'uploadedfile', type: 'file' }), new El('input', { type: 'submit', name: 'hent' })))
    if (kind === 'lev') {
      const f = new El('form', { name: 'opret_kreditor' })
      if (extra.groups) f.append(new El('select', { name: 'opret_lev' }))
      f.append(new El('input', { type: 'submit', name: 'opret', value: 'Opret' }))
      body.append(f)
    }
    if (kind === 'vare') {
      body.append(new El('form', { name: 'opret_vare' }, { text: extra.exists ? 'Varenummer 12 (x) eksisterer i systemet med beskrivelsen y' : 'Der eksisterer ingen varer' })
        .append(new El('input', { type: 'tekst', name: 'opret_varenr', value: '12' }), new El('input', { type: 'text', name: 'salgspris' })))
    }
    if (kind === 'klar') body.append(new El('form', { name: 'importer' }).append(new El('input', { type: 'submit', name: 'importer', value: 'Importer' })))
    return doc
  }
  const run = (doc) => { const ctx = ctxFor(doc, URL0); const st = P.detect(ctx); return { ctx, st, order: P.order(ctx, st), problem: P.problem(ctx, st) } }
  let r = run(page('upload'))
  eq([r.st.state, r.order, r.problem], ['upload', ['KU1'], ''])
  r = run(page('lev', { groups: true }))
  eq([r.st.state, r.st.flags.vaelgGruppe, r.order], ['opretLeverandoer', true, ['KU2', 'KU3']])
  assert.equal(r.ctx.doc.querySelector(P.tasks.KU2.show.target).name, 'opret')
  r = run(page('lev'))
  eq([r.st.flags.vaelgGruppe, r.order], [false, ['KU3']], 'one creditor group: no group to post, the supplier card is the way')
  r = run(page('vare', { exists: true }))
  eq([r.st.state, r.st.flags.varenrFindes, r.order], ['opretVare', true, ['KU4']])
  r = run(page('klar'))
  eq([r.st.state, r.order], ['klar', ['KU5']])
  r = run(page('none'))
  eq([r.st.state, r.problem, r.order], ['fejl', 'KU6', ['KU6']])
  assert.equal(r.ctx.doc.querySelector(P.tasks.KU6.show.target).getAttribute('href'), 'ordre.php')
})

test('K2 kreditorer: detect() gives the list, the search, the closed rows and the grid setup forms', () => {
  const P = C.pages['kreditor/kreditor.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/kreditor/kreditor.php'
  const page = ({ rows = 0, search = '', closed = false, setup = null } = {}) => {
    const doc = new Doc()
    const wrap = new El('div', { id: 'datatable-wrapper-kreditor_list' })
    doc.append(new El('a', { href: 'kreditorkort.php?returside=kreditor.php' }).append(new El('button', {}, { text: 'Ny' })), wrap)
    if (setup === 'filtre') {
      wrap.append(new El('form', { method: 'POST' }).append(new El('div', { id: 'filter-wrapper' }).append(
        new El('input', { type: 'checkbox', name: 'filter[kreditor_list][Status][Lukkede]' })),
      new El('button', { type: 'button', onclick: 'updateQueryParameter("menu[kreditor_list]", "menu[kreditor_list]", "main");' })))
      return doc
    }
    if (setup === 'kolonner') {
      wrap.append(new El('form', { method: 'POST' }).append(new El('input', { type: 'text', name: 'rows[kreditor_list][1][pos]', value: '1' })))
      return doc
    }
    const tbody = new El('tbody')
    for (let i = 0; i < rows; i++) {
      tbody.append(new El('tr', closed && i === 0 ? { style: 'opacity: 0.6;' } : {}).append(new El('td', {}, { text: '100' + i }), new El('td', { 'data-kreditor-id': String(i + 1) })))
    }
    tbody.append(new El('tr', { class: 'filler-row' }))
    wrap.append(new El('form', { method: 'GET' }).append(new El('table', { id: 'datatable-kreditor_list' }).append(
      new El('thead').append(new El('tr').append(new El('th').append(new El('input', { name: 'search[kreditor_list][firmanavn]', value: search })),
        new El('th').append(new El('button', { type: 'button', class: 'dropdown-trigger' })))), tbody)))
    return doc
  }
  const det = (doc) => P.detect(ctxFor(doc, URL0))
  eq(det(page()).state, 'tom')
  eq(P.order(ctxFor(page(), URL0), det(page())).slice(0, 3), ['KR1', 'KR8', 'KR4'])
  const searched = det(page({ search: 'xyz' }))
  eq([searched.state, searched.flags.soegning], ['tom', true])
  eq(P.about(ctxFor(page(), URL0), searched), ['help.kred.about.soegning_tom'])
  const list = page({ rows: 3, closed: true })
  const st = det(list)
  eq([st.state, st.flags.lukkede, st.flags.soegning], ['liste', true, false])
  eq(P.order(ctxFor(list, URL0), st).slice(0, 3), ['KR2', 'KR1', 'KR4'])
  // Vis mig: Ny rings the button in the link, KR3 the first supplier row, KR6 the grid's menu
  const ctx = ctxFor(list, URL0)
  assert.equal(P.tasks.KR1.show.target(ctx, st).tagName, 'BUTTON')
  assert.equal(P.tasks.KR3.show.target(ctx, st).tagName, 'TR')
  assert.equal(P.tasks.KR6.show.target(ctx, st).getAttribute('class'), 'dropdown-trigger')
  // the setup forms: no list task rings anything there
  const filt = page({ setup: 'filtre' })
  const sf = det(filt)
  eq(sf.state, 'filtre')
  eq(P.order(ctxFor(filt, URL0), sf), ['KR12', 'KR11'])
  assert.equal(P.tasks.KR12.show.target(ctxFor(filt, URL0), sf).getAttribute('type'), 'checkbox')
  assert.equal(P.tasks.KR3.show.target(ctxFor(filt, URL0), sf), null)
  eq(det(page({ setup: 'kolonner' })).state, 'kolonner')
  // the address search field ends in a digit inside the brackets
  eq(P.fieldKey(new El('input', { name: 'search[kreditor_list][addr1]' })), 'search:adresse')
})

test('K2 kreditorkort: new and saved card, the flags, the problem card and the targets', () => {
  const P = C.pages['kreditor/kreditorkort.php']
  const card = ({ id = '0', nr = '', unconfirmed = false, closed = false, deletable = false, bank = '', contacts = false, auto = false } = {}) => {
    const doc = new Doc()
    doc.append(new El('a', { href: '#', 'data-back': 'kreditor.php', accesskey: 'L' }).append(new El('button', {}, { text: 'Tilbage' })))
    doc.append(new El('form', { name: 'kreditorkort', id: 'kreditorkortForm' }),
      new El('input', { type: 'hidden', name: 'id', value: id }),
      new El('input', { type: 'text', name: 'ny_kontonr', value: nr }),
      new El('input', { type: 'text', name: 'cvrnr', value: '' }),
      new El('select', { name: 'betalingsbet' }), new El('select', { name: 'gruppe' }),
      new El('input', { type: 'text', name: 'bank_reg', value: '' }),
      new El('input', { type: 'text', name: 'bank_konto', value: bank }),
      new El('input', { type: 'text', name: 'bank_fi', value: '' }),
      new El('input', closed ? { type: 'checkbox', name: 'lukket', checked: '' } : { type: 'checkbox', name: 'lukket' }),
      new El('input', { type: 'submit', accesskey: 'g', name: 'submit', value: 'Gem/opdatér' }))
    if (unconfirmed) doc.append(new El('tr', { id: 'kredBankUnconfirmed' }).append(new El('td').append(new El('button', { type: 'button', onclick: 'kredConfirmBank(5)' }))))
    if (auto) doc.append(new El('tr').append(new El('td', { colspan: '2' }).append(new El('i', {}, { text: 'Oprettet automatisk fra CVR-registeret' }))))
    if (deletable) doc.append(new El('input', { type: 'submit', accesskey: 's', name: 'submit', value: 'Slet' }))
    if (contacts) doc.append(new El('tbody', { id: 'kontaktpersoner' }).append(new El('tr')))
    doc.append(new El('div', { class: 'cred-footer-bar' }).append(
      new El('a', { href: 'rapport.php?rapportart=kontokort&layout=grid&konto_fra=1001&konto_til=1001' }).append(new El('button', { type: 'button' }))))
    return doc
  }
  const U = 'https://ssl12.saldi.dk/asr_hj/kreditor/kreditorkort.php'
  const nyDoc = card()
  const ny = P.detect(ctxFor(nyDoc, U))
  eq(ny.state, 'ny')
  eq(P.name(ctxFor(nyDoc, U), ny), 'help.kkort.name_new')
  eq(P.order(ctxFor(nyDoc, U), ny).slice(0, 3), ['KO1', 'KO2', 'KO3'])
  eq(P.problem(ctxFor(nyDoc, U), ny), '')
  const doc = card({ id: '5', nr: '1001', unconfirmed: true, deletable: true, bank: '1234567', contacts: true })
  const st = P.detect(ctxFor(doc, U + '?id=5&returside=..%2Fkreditor%2Fordre.php&ordre_id=12'))
  eq([st.state, st.flags.ubekraeftet, st.flags.kanSlettes, st.flags.manglerBank, st.flags.fraOrdre, st.flags.harKontakter, st.flags.lukket],
    ['kort', true, true, false, true, true, false])
  eq(P.name(ctxFor(doc, U), st), ['help.kkort.name', '1001'])
  eq(P.problem(ctxFor(doc, U), st), 'KO5')
  eq(P.order(ctxFor(doc, U), st).slice(0, 3), ['KO5', 'KO12', 'KO6'])
  eq(P.about(ctxFor(doc, U), st), ['help.kkort.about.kort', 'help.kkort.about.ubekraeftet'])
  const ctx = ctxFor(doc, U)
  const ring = (id) => { const t = P.tasks[id].show.target; return typeof t === 'function' ? t(ctx, st) : doc.querySelector(t) }
  assert.equal(ring('KO5').tagName, 'BUTTON')
  assert.equal(ring('KO6').tagName, 'BUTTON')
  assert.equal(ring('KO12').tagName, 'BUTTON')
  assert.equal(ring('KO13'), null, 'from a purchase order Tilbage does not lead to the cash journal')
  assert.equal(P.tasks.KO12.show.target(ctxFor(nyDoc, U), ny), null, 'Tilbage to the supplier list is not the purchase order')
  // Live: a new card prints Kontokort too; nothing to see, delete or merge before the card is saved
  assert.equal(P.tasks.KO6.show.target(ctxFor(nyDoc, U), ny), null)
  assert.equal(P.tasks.KO11.show.target(ctxFor(nyDoc, U), ny), null)
  // The start screen (spec §25): a new card asks for the CVR lookup and the number it gets, a saved one for deleting
  eq(P.questionOrder(ctxFor(nyDoc, U), ny), ['Q3', 'Q4', 'Q2'])
  eq(P.questionOrder(ctx, st)[0], 'Q1')
  assert.equal(P.questions.Q4.show.target(ctxFor(nyDoc, U), ny).getAttribute('name'), 'ny_kontonr')
  assert.equal(P.questions.Q4.show.target(ctx, st), null, 'no number question on a saved card')
  assert.equal(P.tasks.KO13.show.target(ctxFor(doc, U + '?id=5&returside=..%2Ffinans%2Fkassekladde.php'), null).tagName, 'BUTTON', 'from the cash journal')
  assert.equal(ring('KO10').getAttribute('accesskey'), 's')
  assert.equal(ring('KO1').getAttribute('name'), 'cvrnr')
  const closed = card({ id: '6', nr: '1002', closed: true, auto: true })
  const sc = P.detect(ctxFor(closed, U))
  eq([sc.flags.lukket, sc.flags.autoOprettet, sc.flags.manglerBank], [true, true, true])
  eq(P.order(ctxFor(closed, U), sc)[0], 'KO15')
  eq(P.fieldKey(new El('input', { name: 'posnr[2]' })), 'posnr')
})

test('K2 kreditorrapporter: the form and every report are told apart by their markup', () => {
  const P = C.pages['kreditor/rapport.php']
  const U = 'https://ssl12.saldi.dk/asr_hj/kreditor/rapport.php'
  const det = (doc, url = U) => P.detect(ctxFor(doc, url))
  // the selection form, with Betalingslister
  const form = new Doc()
  form.append(new El('form', { name: 'regnskabsaar' }).append(new El('input', { name: 'dato' }), new El('input', { name: 'konto' }),
    new El('input', { type: 'checkbox', name: 'husk', checked: '' }), new El('input', { type: 'submit', name: 'openpost' }),
    new El('input', { type: 'submit', name: 'kontosaldo' }), new El('input', { type: 'submit', name: 'kontokort' }),
    new El('span', { onclick: "window.location.href='top100.php'" }).append(new El('input', { type: 'button' })),
    new El('span', { onclick: "javascript:location.href='../kreditor/betalingsliste.php'" }).append(new El('input', { type: 'button' }))))
  const sf = det(form)
  eq([sf.state, sf.flags.husket, sf.flags.betalingslister], ['forside', true, true])
  eq(P.order(ctxFor(form, U), sf).slice(0, 3), ['RP1', 'RP3', 'RP2'])
  assert.ok(P.order(ctxFor(form, U), sf).includes('RP7'))
  assert.equal(P.tasks.RP1.show.target(ctxFor(form, U), sf).getAttribute('name'), 'openpost')
  // Åbne poster in the mode Kun konti i kredit: every other mode is an option URL
  const op = new Doc()
  const modes = ['vis_aabenpost', 'vis_alle_poster', 'kun_debet', 'skjul_aabenpost']
  op.append(new El('a', { accesskey: 'l', href: 'rapport.php' }).append(new El('button')),
    new El('select', { name: 'aabenpostmode' }).append(new El('option', {}, { text: 'Kun konti i kredit' }),
      ...modes.map((m) => new El('option', { value: 'rapport.php?rapportart=openpost&submit=ok&' + m + '=on' }))),
    new El('table', { id: 'visAabnePosterTable' }).append(
      new El('tr').append(new El('th').append(new El('a', { class: 'op-filter', href: 'rapport.php?x=1&aging_bucket=over90' }))),
      new El('tr').append(new El('td').append(new El('a', { href: 'rapport.php?rapportart=accountChart&konto_fra=1001' })),
        new El('td').append(new El('a', { class: 'op-name', href: 'kreditorkort.php?id=5' })),
        new El('td').append(new El('a', { class: 'op-link', href: 'rapport.php?x=1&udlign=5' })))),
    new El('input', { name: 'kontonr', value: '' }), new El('input', { type: 'hidden', name: 'aging_bucket', value: '' }))
  const so = det(op)
  eq([so.state, so.flags.kunKredit, so.flags.skjult, so.flags.tom, so.flags.udlign, so.flags.filter, so.flags.soegning],
    ['aabnePoster', true, false, false, true, false, false])
  eq(P.order(ctxFor(op, U), so).slice(0, 3), ['AP3', 'AP1', 'AP2'])
  const octx = ctxFor(op, U)
  assert.equal(P.tasks.AP3.show.target(octx, so).getAttribute('href'), 'rapport.php?x=1&udlign=5')
  assert.equal(P.tasks.AP1.show.target(octx, so).getAttribute('class'), 'op-filter')
  assert.equal(P.tasks.KR8.show.target(octx, so).tagName, 'BUTTON')
  // a form task rings nothing on a report (spec §19.3)
  assert.equal(P.tasks.RP1.show.target(octx, so), null)
  // Kontosaldo, empty
  const ks = new Doc()
  ks.append(new El('table', { id: 'ksHeaderTitleTable' }), new El('table', { id: 'ksGridTable' }).append(new El('tfoot').append(new El('tr'))))
  const sk = det(ks)
  eq([sk.state, sk.flags.tom], ['kontosaldo', true])
  eq(P.order(ctxFor(ks, U), sk)[0], 'APT')
  eq(P.about(ctxFor(ks, U), sk), ['help.krap.about.kontosaldo', 'help.krap.about.kontosaldo_tom'])
  // Kontokort with an open and a settled amount
  const kk = new Doc()
  kk.append(new El('table', { id: 'kkGridTable' }).append(new El('tr', { class: 'kk-account-row' }),
    new El('tr', { class: 'kk-data-row' }).append(new El('td').append(new El('a', { href: '../includes/udlign_openpost.php?post_id=7' })),
      new El('td').append(new El('a', { href: 'rapport.php?rapportart=kontokort&unAlign=3&oppId=8' })))),
  new El('button', { type: 'button', onclick: 'showLangModalKontoprint()' }))
  const skk = det(kk)
  eq([skk.state, skk.flags.aabne, skk.flags.udlignede, skk.flags.tom, skk.flags.flere], ['kontokort', true, true, false, false])
  eq(P.order(ctxFor(kk, U), skk).slice(0, 3), ['KK1', 'AP4', 'KK3'])
  assert.ok(P.tasks.KK1.show.target(ctxFor(kk, U), skk).getAttribute('href').includes('udlign_openpost.php'))
  assert.ok(P.tasks.KK2.show.target(ctxFor(kk, U), skk).getAttribute('href').includes('unAlign='))
  // the supplier's ledger from the card, and the account card from Åbne poster
  const gl = new Doc()
  gl.append(new El('div', { class: 'creditor-ledger-page' }).append(new El('tr', { class: 'creditor-ledger-empty-row' })))
  eq([det(gl).state, det(gl).flags.tom], ['kontokortLev', true])
  const ac = new Doc()
  ac.append(new El('select', { name: 'typeSelect' }).append(new El('option', {}, { text: 'Vis åbne poster' }),
    new El('option', { value: 'rapport.php?rapportart=accountChart&kilde=show_all' })))
  const sa = det(ac, U + '?rapportart=accountChart&kilde=openpost')
  eq([sa.state, sa.flags.alle], ['kontoudtog', false])
  eq(P.order(ctxFor(ac, U), sa)[0], 'KK7')
  eq(P.fieldKey(new El('select', { name: 'typeSelect' })), 'typeselect')
})

test('K2 betalinger: the payment list pages, the red lines as a problem and the line field keys', () => {
  const L = C.pages['kreditor/betalingsliste.php']
  const B = C.pages['kreditor/betalinger.php']
  const UL = 'https://ssl12.saldi.dk/asr_hj/kreditor/betalingsliste.php'
  const UB = 'https://ssl12.saldi.dk/asr_hj/kreditor/betalinger.php?liste_id=7'
  // the overview: an open and a sent list
  const list = new Doc()
  list.append(new El('a', { href: 'rapport.php', accesskey: 'L' }), new El('a', { href: 'betalinger.php?id=0', accesskey: 'N' }),
    new El('a', { href: 'betalingsliste.php?sort=id&rf=desc&vis=alle' }),
    new El('a', { href: 'betalinger.php?tjek=7&liste_id=7&returside=betalingsliste.php' }),
    new El('a', { href: "betalinger.php?liste_id=3&returside=betalingsliste.php'" }),
    new El('span', { onclick: "location.href = 'betalingsliste.php?sort=id&rf=desc&deleteList=7'" }))
  const sl = L.detect(ctxFor(list, UL))
  eq([sl.state, sl.flags.aabne, sl.flags.sendte], ['liste', true, true])
  eq(L.order(ctxFor(list, UL), sl).slice(0, 3), ['BL2', 'BL1', 'BL3'])
  assert.ok(L.tasks.BL2.show.target.startsWith('a['))
  const empty = new Doc()
  empty.append(new El('a', { href: 'betalinger.php?id=0', accesskey: 'N' }))
  eq(L.detect(ctxFor(empty, UL)).state, 'tom')
  const confirm = new Doc()
  confirm.append(new El('span', { onclick: "location.href = 'betalingsliste.php?sort=id&rf=desc&vis=alle&deleteList=7&confirmDelete=yes'" }).append(new El('button', {}, { text: 'Ja' })))
  eq(L.detect(ctxFor(confirm, UL + '?deleteList=7')).state, 'sletBekraeft')
  // a list with red lines and an unconfirmed account
  const lines = (foot) => {
    const doc = new Doc()
    doc.append(new El('a', { href: '../kreditor/betalingsliste.php', accesskey: 'L' }), new El('select', { name: 'find' }),
      new El('input', { name: 'listenote' }), new El('select', { name: 'erh[1]' }), new El('input', { name: 'belob[1]', value: '100,00' }),
      new El('td', { bgcolor: '#FF0000' }).append(new El('input', { name: 'til_kto[1]', value: '' })),
      new El('button', { type: 'button', onclick: "var f=this.form,v={gem:'1',bekraeft_bank:'5'};" }),
      new El('input', { type: 'checkbox', name: 'slet[1]' }), new El('input', { type: 'submit', name: 'gem', value: 'Gem' }))
    if (foot === 'fejl') doc.append(new El('input', { type: 'submit', name: 'slet_ugyldige', value: 'Slet røde' }))
    else doc.append(new El('select', { name: 'format' }), new El('input', { type: 'submit', name: 'udskriv', value: 'Udskriv og luk' }))
    return doc
  }
  const red = lines('fejl')
  const sr = B.detect(ctxFor(red, UB))
  eq([sr.state, sr.flags.ubekraeftet, sr.data.liste], ['fejl', true, '7'])
  eq(B.name(ctxFor(red, UB), sr), ['help.kbet.name_nr', '7'])
  eq(B.problem(ctxFor(red, UB), sr), 'BT6')
  eq(B.problem(ctxFor(red, UB), { state: 'fejl', flags: {}, data: {} }), 'BT4')
  assert.equal(B.tasks.BT4.show.target, 'td[bgcolor="#FF0000"]')
  const ok = lines('ok')
  const so = B.detect(ctxFor(ok, UB))
  eq(so.state, 'aaben')
  eq(B.problem(ctxFor(ok, UB), so), '')
  // a sent list, the file and the empty list
  const sent = new Doc()
  sent.append(new El('select', { name: 'format' }), new El('input', { type: 'submit', name: 'udskriv', value: 'Udskriv' }))
  eq(B.detect(ctxFor(sent, UB)).state, 'sendt')
  const file = new Doc()
  file.append(new El('a', { href: '../temp/test_8381.txt' }))
  eq(B.detect(ctxFor(file, UB)).state, 'fil')
  const none = new Doc()
  none.append(new El('select', { name: 'find' }))
  eq(B.detect(ctxFor(none, UB)).state, 'tom')
  eq(B.order(ctxFor(none, UB), B.detect(ctxFor(none, UB)))[0], 'BT1')
  eq(B.fieldKey(new El('input', { name: 'til_kto[12]' })), 'til_kto')
  eq(B.fieldKey(new El('input', { name: 'listenote' })), null)
})

test('K2 kontofusion and top100: detect() and the targets', () => {
  const F = C.pages['kreditor/kontofusion.php']
  const T = C.pages['kreditor/top100.php']
  const fdoc = new Doc()
  fdoc.append(new El('form', { name: 'kontofusion' }).append(new El('input', { type: 'submit', name: 'fusion', value: 'Fortryd' }),
    new El('input', { type: 'submit', name: 'fusion', value: 'OK' })))
  const fs = F.detect(ctxFor(fdoc, 'https://ssl12.saldi.dk/asr_hj/kreditor/kontofusion.php?id=5&kontonr=1005'))
  eq(fs.state, 'bekraeft')
  assert.equal(new Doc().querySelector(F.tasks.FU1.show.target), null)
  assert.equal(fdoc.querySelector(F.tasks.FU1.show.target).getAttribute('value'), 'OK')
  const U = 'https://ssl12.saldi.dk/asr_hj/kreditor/top100.php'
  const tdoc = new Doc()
  tdoc.append(new El('a', { href: '../kreditor/rapport.php', accesskey: 'L' }).append(new El('button')),
    new El('a', { href: 'top100.php?periode=081125:081126&ret=on', accesskey: 'P' }).append(new El('button')),
    new El('tr', { bgcolor: '#ffffff' }).append(new El('td', {}, { text: '1' })))
  const ts = T.detect(ctxFor(tdoc, U))
  eq(ts.state, 'liste')
  eq(T.order(ctxFor(tdoc, U), ts), ['ST2', 'ST1', 'ST3'])
  assert.equal(T.tasks.ST1.show.target(ctxFor(tdoc, U), ts).tagName, 'BUTTON')
  const pdoc = new Doc()
  pdoc.append(new El('form', { name: 'omsaetning' }).append(new El('input', { type: 'text', name: 'periode', value: '081125:081126' })))
  eq(T.detect(ctxFor(pdoc, U + '?ret=on')).state, 'periode')
})

test('settings sections: the dispatcher of settingsSection.php reads the section key, and every registered section is in the registry', () => {
  const SP = C.pages['systemdata/settingsSection.php']
  assert.equal(SP.group, 'sset')
  assert.equal(typeof SP.variant, 'function')
  assert.ok(SP.variants && typeof SP.variants === 'object')
  eq(SP.errors, [], 'addSection() refused nothing')
  // The sections of getSettingsSections() (systemdata/settingsDefinitions.php): the keys of its one array
  const defs = readFileSync(root + 'systemdata/settingsDefinitions.php', 'utf8')
  const start = defs.indexOf('function getSettingsSections')
  const known = new Set([...defs.slice(start, defs.indexOf('\n\t}\n', start)).matchAll(/^\t{3}'([a-z_]+\.[a-z_]+)' => array\(/gm)].map((m) => m[1]))
  assert.ok(known.size >= 40 && known.has('finance.vat') && known.has('company.fiscal_years') && known.has('pos.tills'), 'the registry parses: ' + known.size)
  for (const [key, page] of Object.entries(SP.variants)) {
    assert.ok(known.has(key), key + ' is not a section of getSettingsSections()')
    assert.notEqual(page.group, 'sset', key + ': a section has a text group of its own')
  }
  // variant(): the address's s= first (kept through every save and redirect), else the s= of the page's form; '' otherwise
  const U = 'https://x/asr_hj/systemdata/settingsSection.php'
  eq(SP.variant(ctxFor(new Doc(), U + '?s=finance.vat&saved=1210')), 'finance.vat')
  eq(SP.variant(ctxFor(new Doc(), U + '?s=company.fiscal_years&year=3')), 'company.fiscal_years')
  const posted = new Doc()
  posted.append(new El('form', { id: 'st-form', method: 'post', action: 'settingsSection.php?s=items.stock&back=%2Flager%2Fvarer.php' }))
  eq(SP.variant(ctxFor(posted, U)), 'items.stock')
  eq(SP.variant(ctxFor(new Doc(), U)), '')
  eq(SP.variant(ctxFor(new Doc(), U + '?s=../x')), '', 'only a key of the form group.section')
  eq(SP.variant(ctxFor(new Doc(), U + '?s=Finance.VAT')), '')
})

test('settings sections: the generic page of a section without its own page follows the view, the access and the history', () => {
  const SP = C.pages['systemdata/settingsSection.php']
  const U = 'https://x/asr_hj/systemdata/settingsSection.php?s=finance.vat'
  // a form section with write access and a value that can be put back (settingsSection.php:640-780)
  const form = new Doc()
  form.append(new El('a', { class: 'sk-btn sk-btn-sm sk-back', href: '../systemdata/settings.php', 'data-back': '', accesskey: 'l' }),
    new El('h1', {}, { text: 'Moms' }),
    new El('form', { class: 'st-form', id: 'st-form', method: 'post', action: 'settingsSection.php?s=finance.vat' }).append(
      new El('footer', { class: 'st-savebar', id: 'st-savebar', hidden: '' }).append(new El('button', { type: 'button', id: 'st-undo' }), new El('button', { type: 'submit', id: 'st-save' }))),
    new El('aside', { class: 'st-hist' }).append(new El('button', { type: 'button', class: 'st-tl', 'data-restore': '7', 'data-value': '25' })))
  const fc = ctxFor(form, U)
  const fs = SP.detect(fc)
  eq(fs, { state: 'formular', flags: { historik: true }, data: { section: 'finance.vat' } })
  eq(SP.name(fc, fs), ['help.sset.name.sektion', 'Moms'])
  eq(SP.order(fc, fs), ['SS1', 'SS2', 'SS3', 'SS5'])
  eq(SP.order(fc, { state: 'formular', flags: {}, data: {} }), ['SS1', 'SS2', 'SS5'], 'no Gendan without a change to put back')
  eq(SP.about(fc, fs), ['help.sset.about.formular'])
  eq(SP.questionOrder(fc, fs), [])
  for (const [id, sel] of [['SS1', '#st-save'], ['SS2', '#st-undo'], ['SS3', 'button[data-restore]'], ['SS5', 'a[data-back]']]) {
    assert.equal(SP.tasks[id].show.target, sel, id)
    assert.ok(form.querySelector(sel) !== null, id + ' rings an element of the form section')
  }
  assert.equal(form.querySelector(SP.tasks.SS4.show.target), null, 'SS4 rings nothing outside a list')
  // a list section (kind list: rows with a drawer each, listView.php:318-330)
  const list = new Doc()
  list.append(new El('h1', {}, { text: 'Integrationer' }),
    new El('div', { class: 'st-irow st-irow-ok', 'data-item': 'webshop' }).append(new El('button', { type: 'button', class: 'st-btn', 'data-open': 'webshop' })))
  const lc = ctxFor(list, 'https://x/asr_hj/systemdata/settingsSection.php?s=integrations.connections')
  const ls = SP.detect(lc)
  eq(ls.state, 'liste')
  eq(SP.order(lc, ls), ['SS4', 'SS5'])
  assert.equal(list.querySelector(SP.tasks.SS4.show.target).getAttribute('data-open'), 'webshop')
  for (const id of ['SS1', 'SS2', 'SS3']) assert.equal(list.querySelector(SP.tasks[id].show.target), null, id + ' rings nothing in a list')
  // read-only access (the info flash 5755, no save bar)
  const ro = new Doc()
  ro.append(new El('h1', {}, { text: 'Moms' }), new El('div', { class: 'st-flash st-flash-info', role: 'status' }), new El('form', { id: 'st-form', action: 'settingsSection.php?s=finance.vat' }))
  const rc = ctxFor(ro, U)
  const rs = SP.detect(rc)
  eq(rs.state, 'kunLaes')
  eq(SP.order(rc, rs), ['SS5'])
  eq(SP.questionOrder(rc, rs), ['Q1'])
  eq(SP.about(rc, rs), ['help.sset.about.kunlaes'])
  // another page: no state, and the plain name without a heading
  const none = ctxFor(new Doc(), U)
  eq(SP.detect(none).state, 'unknown')
  eq(SP.name(none, SP.detect(none)), 'help.sset.name')
})

test('L1 vareliste: detect() gives the state and flags of the goods tab, and the order follows them', () => {
  const P = C.pages['lager/lister/vareliste.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/lager/lister/vareliste.php'
  /** The item list as lager/lister/vareliste.php and includes/grid.php print it. */
  const page = ({ rows = 2, search = '', mode = 'main', lagre = 2 } = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('td', { id: 'back-btn' }).append(new El('a', { href: 'vareliste.php?returside=x' })))
    body.append(new El('td', { id: 'ordrevisning' }).append(new El('a', { href: 'ordrestatus.php?returside=x' })))
    body.append(new El('td', { id: 'indkob' }).append(new El('a', { href: 'indkøb.php?returside=x' })))
    body.append(new El('td', { id: 'create-new' }).append(new El('a', { href: '../varekort.php', accesskey: 'L' })))
    const wrap = new El('div', { id: 'datatable-wrapper-varelst1', class: 'datatable-wrapper' })
    body.append(wrap)
    if (mode === 'kolonner') {
      wrap.append(new El('select', { name: 'rows[varelst1][1][field]' }))
      return doc
    }
    if (mode === 'filtre') {
      wrap.append(new El('input', { type: 'checkbox', name: 'filter[varelst1][misc][show_discontinued]' }))
      return doc
    }
    const table = new El('table', { id: 'datatable-varelst1', class: 'datatable' })
    wrap.append(table)
    const head = new El('tr').append(new El('th', { class: 'varenr sortable-td' }))
    for (let i = 1; i <= lagre; i++) head.append(new El('th', { class: 'lager' + i + ' sortable-td' }))
    head.append(new El('th', { class: 'lager_total sortable-td' }), new El('th', { class: 'salgspris sortable-td' }))
    const searchRow = new El('tr').append(
      new El('th', { class: 'varenr' }).append(new El('input', { name: 'search[varelst1][varenr]', value: search })),
      new El('th', { class: 'lager1' }).append(new El('input', { name: 'search[varelst1][lager1]', value: '' })),
      new El('th').append(new El('div', { class: 'dropdown' }).append(new El('button', { type: 'button', class: 'dropdown-trigger' }))))
    table.append(new El('thead').append(head, searchRow))
    const tbody = new El('tbody')
    for (let i = 0; i < rows; i++) {
      const tr = new El('tr', { style: '' })
      tr.append(new El('td').append(new El('a', { href: '../lagerflyt.php?lager=1&vare_id=' + i })))
      tbody.append(tr)
    }
    tbody.append(new El('tr', { class: 'filler-row' }))
    table.append(tbody)
    return doc
  }

  let ctx = ctxFor(page(), URL0)
  let st = P.detect(ctx)
  assert.equal(st.state, 'liste')
  assert.equal(st.flags.soegning, false)
  assert.equal(st.flags.flereLagre, true)
  eq(P.order(ctx, st).slice(0, 3), ['VL2', 'VL3', 'VL1'])
  assert.ok(P.order(ctx, st).includes('VL5'), 'two warehouses: move stock')
  assert.ok(P.tasks.VL5.show.target(ctx, st) === ctx.doc.querySelector('a[href*="lagerflyt.php"]'))
  eq(P.about(ctx, st), ['help.lvar.about.vareliste'])
  assert.ok(P.tasks.VL3.show.target(ctx, st) === ctx.doc.querySelector('[id="datatable-varelst1"] > tbody > tr'))
  assert.ok(P.tasks.VL4.show.target(ctx, st) === ctx.doc.querySelector('th.lager_total'))
  assert.ok(P.tasks.VL1.show.target === '#create-new a')
  assert.ok(P.tasks.VL11.show.target(ctx, st) === ctx.doc.querySelector('#indkob a'))
  assert.ok(P.tasks.VL6.show.target(ctx, st) === ctx.doc.querySelector('.dropdown-trigger'))
  assert.equal(P.fieldKey(ctx.doc.querySelector('[name="search[varelst1][lager1]"]')), 'search:lager')

  ctx = ctxFor(page({ lagre: 1 }), URL0)
  st = P.detect(ctx)
  assert.equal(st.flags.flereLagre, false)
  assert.ok(!P.order(ctx, st).includes('VL5'))
  assert.equal(P.tasks.VL5.show.target(ctx, st), null, 'one warehouse: nowhere to move to')

  ctx = ctxFor(page({ rows: 0, search: 'zz' }), URL0)
  st = P.detect(ctx)
  assert.equal(st.state, 'liste')
  assert.equal(st.flags.ingenRaekker, true)
  assert.equal(P.order(ctx, st)[0], 'VL9')
  assert.ok(!P.order(ctx, st).includes('VL3'), 'no row to open (live check 24.15)')
  assert.equal(P.tasks.VL19.show, undefined, 'VL19 is for the other tabs; on Vareliste Ny is VL1')
  eq(P.about(ctx, st), ['help.lvar.about.soegning_tom'])
  assert.ok(P.tasks.VL9.show.target(ctx, st) === ctx.doc.querySelector('[name="search[varelst1][varenr]"]'))

  ctx = ctxFor(page({ rows: 0 }), URL0)
  st = P.detect(ctx)
  assert.equal(st.state, 'tom')
  eq(P.order(ctx, st), ['VL15', 'VL1', 'VL10'])
  eq(P.questionOrder(ctx, st), ['Q1', 'Q2', 'Q3'], 'an empty list: a filter can hide every item, so Q1 leads')

  ctx = ctxFor(page({ mode: 'kolonner' }), URL0)
  st = P.detect(ctx)
  assert.equal(st.state, 'kolonner')
  eq(P.order(ctx, st), ['VL16'])
  eq(P.questionOrder(ctx, st), ['Q3', 'Q1', 'Q2'], 'Kolonner: the price columns with and without VAT')
  assert.equal(P.tasks.VL3.show.target(ctx, st), null, 'no row to ring in the column setup')
  assert.equal(P.tasks.VL6.show.target(ctx, st), null)

  ctx = ctxFor(page({ mode: 'filtre' }), URL0)
  st = P.detect(ctx)
  assert.equal(st.state, 'filtre')
  eq(P.order(ctx, st), ['VL17', 'VL7'])
  assert.ok(P.tasks.VL7.show.target(ctx, st) === ctx.doc.querySelector('[name="filter[varelst1][misc][show_discontinued]"]'), 'Vis udgået in Filtre')
})

test('L1 the other goods tabs: each grid is its own page, and the tab links ring nothing on their own tab', () => {
  const grid = (id, { rows = 1, tabs = ['back-btn', 'ordrevisning', 'indkob', 'serial', 'styklister'] } = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    for (const t of tabs) body.append(new El('td', { id: t }).append(new El('a', { href: t + '.php' })))
    const wrap = new El('div', { id: 'datatable-wrapper-' + id })
    const table = new El('table', { id: 'datatable-' + id })
    const searchRow = new El('tr').append(
      new El('th').append(new El('input', { name: 'search[' + id + '][varenr]', value: '' })),
      new El('th').append(new El('input', { name: 'search[' + id + '][serienr]', value: '' })),
      new El('th').append(new El('button', { class: 'dropdown-trigger' })))
    const head = new El('tr').append(new El('th', { class: 'in_sales_order' }), new El('th', { class: 'genbestil' }), new El('th', { class: 'sales_last_6_months' }))
    table.append(new El('thead').append(head, searchRow))
    const tbody = new El('tbody')
    for (let i = 0; i < rows; i++) {
      const tr = new El('tr')
      // a hover box with a table of its own inside a cell (ordrestatus.php:152-180)
      tr.append(new El('td').append(new El('div', { class: 'hover-dropdown' }).append(new El('table').append(new El('tbody').append(new El('tr'))))))
      tr.append(new El('td', { class: 'filler-row' }).append(new El('div').append(new El('svg', { onclick: "const name = prompt('x'); document.getElementsByName('rename')[0].value=name;" }))))
      tbody.append(tr)
    }
    tbody.append(new El('tr', { class: 'filler-row' }))
    table.append(tbody)
    wrap.append(table)
    body.append(wrap)
    if (id === 'indkøb') {
      body.append(new El('button', { type: 'button', id: 'autoudfyldBtn' }), new El('button', { type: 'button', id: 'bestilBtn' }))
      body.append(new El('input', { type: 'text', id: 'genb-12', class: 'inputbox genbestil-inp' }))
    }
    return doc
  }
  const OV = C.pages['lager/lister/ordrestatus.php']
  let ctx = ctxFor(grid('ordrestat', { rows: 2, tabs: ['back-btn', 'indkob'] }), 'https://ssl12.saldi.dk/asr_hj/lager/lister/ordrestatus.php')
  let st = OV.detect(ctx)
  assert.equal(st.state, 'liste')
  assert.equal(st.data.rows, 2, 'only the grid rows count, not the rows of the hover tables')
  eq(OV.order(ctx, st).slice(0, 3), ['OV1', 'OV2', 'OV4'])
  assert.ok(OV.tasks.OV2.show.target(ctx, st) === ctx.doc.querySelector('th.genbestil'))
  assert.ok(OV.tasks.OV4.show.target(ctx, st) === ctx.doc.querySelector('#indkob a'))

  ctx = ctxFor(grid('ordrestat', { rows: 0 }), 'https://ssl12.saldi.dk/asr_hj/lager/lister/ordrestatus.php')
  st = OV.detect(ctx)
  assert.equal(st.state, 'tom')
  eq(OV.order(ctx, st), ['VL19'])
  assert.ok(OV.tasks.VL19.show.target(ctx, st) === ctx.doc.querySelector('#back-btn a'))

  const IK = C.pages['lager/lister/indk%C3%B8b.php']
  assert.ok(IK, 'the purchasing tab is registered under its percent-encoded path')
  ctx = ctxFor(grid('indkøb', { tabs: ['back-btn', 'ordrevisning'] }), 'https://ssl12.saldi.dk/asr_hj/lager/lister/indk%C3%B8b.php')
  st = IK.detect(ctx)
  assert.equal(st.state, 'liste')
  eq(IK.order(ctx, st).slice(0, 3), ['IK1', 'IK2', 'IK3'])
  assert.ok(ctx.doc.querySelector('#bestilBtn') !== null)
  assert.equal(IK.fieldKey(ctx.doc.querySelector('#genb-12')), 'bestil_antal')
  assert.equal(IK.tasks.VL11 === undefined, true, 'Indkøb does not offer the link to itself')
  eq(IK.questionOrder(ctx, st), ['Q5', 'Q8', 'Q4'])
  assert.ok(IK.questions.Q8.show.target === '#bestilBtn', 'Q8 rings Bestil')
  eq(IK.questions.Q8.steps, ['help.lvar.task.ik1.step3'])
  ctx = ctxFor(grid('indkøb', { rows: 0, tabs: ['back-btn'] }), 'https://ssl12.saldi.dk/asr_hj/lager/lister/indk%C3%B8b.php')
  eq(IK.questionOrder(ctx, IK.detect(ctx)), ['Q5', 'Q4', 'Q8'])
  assert.equal(OV.questions.Q4.intro, 'help.lvar.task.ov2.why', 'Gen. is explained before it is worked out')

  const SN = C.pages['lager/lister/serialnumber.php']
  ctx = ctxFor(grid('serienr'), 'https://ssl12.saldi.dk/asr_hj/lager/lister/serialnumber.php')
  st = SN.detect(ctx)
  assert.equal(st.state, 'liste')
  assert.ok(SN.tasks.SN3.show.target(ctx, st) === ctx.doc.querySelector('svg'))
  assert.ok(SN.tasks.SN1.show.target(ctx, st) === ctx.doc.querySelector('[name="search[serienr][serienr]"]'))
  ctx = ctxFor(grid('serienr', { rows: 0 }), 'https://ssl12.saldi.dk/asr_hj/lager/lister/serialnumber.php')
  eq(SN.order(ctx, SN.detect(ctx)), ['SN4'])

  const STK = C.pages['lager/lister/styklister.php']
  ctx = ctxFor(grid('styklister', { tabs: ['back-btn'] }), 'https://ssl12.saldi.dk/asr_hj/lager/lister/styklister.php')
  st = STK.detect(ctx)
  eq(STK.order(ctx, st), ['ST1', 'ST3', 'ST2'])
  // a grid of another tab is not this page's grid
  assert.equal(STK.detect(ctxFor(grid('serienr'), 'https://ssl12.saldi.dk/asr_hj/lager/lister/styklister.php')).state, 'unknown')
})

test('L1 varekort: the states and flags of the item card, the problem card and the order per situation', () => {
  const P = C.pages['lager/varekort.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/lager/varekort.php?id=3'
  /** A saved item card (lager/varekort.php:1675-2113 and the productCardIncludes). */
  const card = ({ group = '2 Handelsvarer', stock = 'on', qty = '5', supplier = false, readOnly = false, closed = false, hidden = false,
    varenr = 'K1', desc = 'Kaffe', costReadonly = false, bom = false, retur = 'lister/vareliste.php', fifo = false } = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('a', { href: 'javascript:confirmClose(x)', accesskey: 'L' }))
    body.append(new El('a', { href: 'ret_varenr.php?id=3' }))
    const form = withElements(new El('form', { name: 'varekort' }))
    body.append(form)
    form.append(new El('input', { type: 'hidden', name: 'returside', value: retur }))
    form.append(new El('input', { type: 'hidden', name: 'varenr', value: varenr }))
    form.append(new El('input', { type: 'text', name: 'beskrivelse0', value: desc }))
    form.append(new El('input', { type: 'text', name: 'beskrivelseAlias', value: '' }))
    form.append(new El('input', { name: 'salgspris', value: '100,00' }))
    form.append(new El('input', { name: 'kostpris[0]', id: 'costPrice', value: '50,00', ...(costReadonly ? { readonly: 'readonly' } : {}) }))
    const sel = new El('select', { name: 'ny_gruppe' })
    sel.append(new El('option', { value: '2' }, { text: group }))
    form.append(new El('input', { type: 'hidden', name: 'gruppe', value: '2' }), sel)
    form.append(new El('td', { id: 'pcSecOffer', ...(hidden ? { class: 'pcVisHidden' } : {}) }))
    form.append(new El('input', { type: 'hidden', name: 'stockItem', value: stock }))
    if (stock === 'on') {
      form.append(new El('input', { type: 'text', name: 'min_lager', value: '0' }))
      if (fifo && !bom) {
        // FIFO: Aktuel readonly with hidden copies (showLocations.php:190-195)
        form.append(new El('input', { class: 'inputbox', readonly: 'readonly', name: 'ny_beholdning', value: qty }))
        form.append(new El('input', { type: 'hidden', name: 'ny_beholdning', value: qty }), new El('input', { type: 'hidden', name: 'ny_lagerbeh[1]', value: qty }))
      } else form.append(new El('input', { type: 'text', name: 'ny_lagerbeh[1]', value: qty }))
      form.append(new El('input', { type: 'hidden', name: 'beholdning', value: qty }))
      form.append(new El('input', { type: 'checkbox', name: 'serienr' }))
    }
    if (supplier) {
      form.append(new El('input', { type: 'text', name: 'lev_pos[1]', value: '1' }), new El('input', { type: 'text', name: 'lev_varenr[1]', value: 'L-1' }))
      body.append(new El('button', { type: 'button', onclick: "bestil('3', document.getElementById('bestil-antal').value)" }))
    }
    if (bom) form.append(new El('tbody', { id: 'stykliste-tbody' }))
    form.append(new El('input', { type: 'checkbox', name: 'samlevare' }))
    form.append(new El('input', { type: 'checkbox', name: 'lukket', ...(closed ? { checked: '' } : {}) }))
    form.append(new El('input', { type: 'hidden', name: 'delvare', value: '' }))
    // MB-62 (varekort.php:2193-2216): a read-only right gets no Gem, Kopier or Leverandøropslag, not disabled ones
    if (!readOnly) {
      form.append(new El('input', { type: 'submit', name: 'saveItem', value: 'Gem' }))
      form.append(new El('input', { type: 'submit', name: 'copy', value: 'Kopier' }))
      form.append(new El('input', { type: 'submit', name: 'supplierLookUp', value: 'Leverandøropslag', accesskey: 'l' }))
    }
    form.append(new El('input', { type: 'button', name: 'stockHistory', value: 'Lagerhistorik' }))
    body.append(new El('button', { type: 'button', id: 'pcVisToggle' }))
    return doc
  }

  let ctx = ctxFor(card(), URL0)
  let st = P.detect(ctx)
  assert.equal(st.state, 'kort')
  assert.equal(st.flags.lagerfoert, true)
  assert.equal(st.flags.beholdning, true)
  assert.equal(st.flags.udenLeverandor, true)
  eq(P.name(ctx, st), ['help.lkort.name', 'K1'])
  eq(P.order(ctx, st).slice(0, 3), ['VK9', 'VK8', 'VK5'])
  assert.equal(P.problem(ctx, st), '')
  eq(P.about(ctx, st), ['help.lkort.about.lagerfoert'])
  assert.ok(P.tasks.VK8.show.target(ctx, st) === ctx.doc.querySelector('[name="ny_lagerbeh[1]"]'))
  assert.ok(P.tasks.VK7.show.target === 'input[name="supplierLookUp"]')
  assert.equal(P.tasks.VK33.show.target, 'a[href*="konto_id="]')
  assert.equal(P.tasks.VK35.show.target(ctx, st), null, 'the item lookup rings nothing on the card')
  assert.equal(P.tasks.VK36.show.target(ctx, st), null, 'a known group: VK36 rings nothing')
  eq(P.questionOrder(ctx, st), ['Q1', 'Q9', 'Q8'], 'a stock item: VAT, then whether a stock change is posted')

  // no stock yet: enter the stock (valued at the cost price) first
  ctx = ctxFor(card({ qty: '0' }), URL0)
  st = P.detect(ctx)
  assert.equal(st.flags.beholdning, false)
  assert.equal(P.order(ctx, st)[0], 'VK39')

  // a service: no stock fields
  ctx = ctxFor(card({ stock: '', group: '1 Ydelser', supplier: true }), URL0)
  st = P.detect(ctx)
  assert.equal(st.flags.lagerfoert, false)
  eq(P.order(ctx, st).slice(0, 4), ['VK5', 'VK6', 'VK4', 'VK11'])
  assert.equal(P.tasks.VK8.show.target(ctx, st), null)
  eq(P.questionOrder(ctx, st), ['Q1', 'Q8', 'Q5'])

  // the group does not exist in the fiscal year: the problem card
  ctx = ctxFor(card({ group: '1 ' }), URL0)
  st = P.detect(ctx)
  assert.equal(st.flags.gruppeUkendt, true)
  assert.equal(P.problem(ctx, st), 'VK36')
  assert.ok(P.tasks.VK36.show.target(ctx, st) === ctx.doc.querySelector('select[name="ny_gruppe"]'))
  eq(P.about(ctx, st), ['help.lkort.about.lagerfoert', 'help.lkort.about.gruppe_ukendt'])
  assert.equal(P.questionOrder(ctx, st)[0], 'Q3', 'the problem card\'s question leads: a missing group')

  // read only: no Gem (MB-62)
  ctx = ctxFor(card({ readOnly: true, group: '1 ' }), URL0)
  st = P.detect(ctx)
  assert.equal(st.flags.kunLaes, true)
  eq(P.order(ctx, st), ['VK9'])
  assert.equal(P.problem(ctx, st), '')

  // hidden sections, a copy, a closed item, a card opened from a purchase order, a fresh card
  assert.equal(P.order(ctx = ctxFor(card({ hidden: true }), URL0), P.detect(ctx))[0], 'VK37')
  assert.equal(P.order(ctx = ctxFor(card({ varenr: 'K1_copy' }), URL0), P.detect(ctx))[0], 'VK21')
  st = P.detect(ctx = ctxFor(card({ closed: true }), URL0))
  assert.equal(st.flags.udgaaet, true)
  assert.equal(P.order(ctx, st)[0], 'VK18')
  assert.ok(!P.order(ctx, st).includes('VK17'))
  assert.equal(P.tasks.VK17.show.target(ctx, st), null)
  st = P.detect(ctx = ctxFor(card({ retur: '../kreditor/ordre.php' }), URL0))
  assert.equal(st.flags.fraOrdre, true)
  assert.equal(P.order(ctx, st)[0], 'VK28')
  assert.ok(P.tasks.VK28.show.target(ctx, st) === ctx.doc.querySelector('a[accesskey="L"]'))
  // a fresh card: the group first, as the first Gem saves group 1 (review 9 Nov 2026)
  st = P.detect(ctx = ctxFor(card({ desc: '', stock: '' }), URL0))
  eq(P.order(ctx, st).slice(0, 3), ['VK4', 'VK3', 'VK5'])
  eq(P.questionOrder(ctx, st), ['Q8', 'Q1', 'Q11'], 'a fresh card: the price and its VAT next')
  st = P.detect(ctx = ctxFor(card({ closed: true }), URL0))
  assert.equal(P.questionOrder(ctx, st)[0], 'Q5')
  st = P.detect(ctx = ctxFor(card({ hidden: true }), URL0))
  assert.equal(P.questionOrder(ctx, st)[0], 'Q4')
  // a bill of materials in a stock-kept group produces from the card: VK40 rings Aktuel; without a stock group no VK40
  st = P.detect(ctx = ctxFor(card({ bom: true }), URL0))
  eq([st.flags.stykliste, st.flags.lagerfoert, st.flags.beholdning], [true, true, true])
  eq(P.order(ctx, st).slice(0, 3), ['VK40', 'VK5', 'VK13'])
  assert.ok(P.tasks.VK40.show.target(ctx, st) === ctx.doc.querySelector('input[name="ny_lagerbeh[1]"]'))
  st = P.detect(ctx = ctxFor(card({ bom: true, qty: '0' }), URL0))
  eq(P.order(ctx, st).slice(0, 2), ['VK13', 'VK40'])
  st = P.detect(ctx = ctxFor(card({ bom: true, stock: '' }), URL0))
  assert.equal(P.order(ctx, st)[0], 'VK13')
  assert.ok(!P.order(ctx, st).includes('VK40'))
  assert.ok(P.tasks.VK40.show.target(ctx, st) === null)
  st = P.detect(ctx = ctxFor(card(), URL0))
  assert.ok(P.tasks.VK40.show.target(ctx, st) === null, 'no ring on a card without a bill of materials')
  assert.equal(P.tasks.VK39.steps.length, 4)
  st = P.detect(ctx = ctxFor(card({ bom: true, stock: '', costReadonly: true }), URL0))
  assert.equal(st.flags.stykliste, true)
  assert.equal(st.flags.kostLaast, true)
  eq(P.questionOrder(ctx, st), ['Q2', 'Q1', 'Q8'])

  // live check 24.15: with FIFO Aktuel is readonly, so the stock count (VK41) takes the place of VK39 and VK8
  st = P.detect(ctx = ctxFor(card({ fifo: true, qty: '0' }), URL0))
  assert.equal(st.flags.aktuelLaast, true)
  eq(P.order(ctx, st).slice(0, 2), ['VK41', 'VK10'])
  assert.ok(!P.order(ctx, st).includes('VK39'))
  assert.ok(P.tasks.VK41.show.target(ctx, st) === ctx.doc.querySelector('input[name="ny_beholdning"]'), 'the locked Aktuel')
  assert.ok(P.tasks.VK39.show.target(ctx, st) === null && P.tasks.VK8.show.target(ctx, st) === null)
  eq(P.about(ctx, st), ['help.lkort.about.lagerfoert', 'help.lkort.about.aktuel_laast'])
  st = P.detect(ctx = ctxFor(card({ fifo: true }), URL0))
  eq(P.order(ctx, st).slice(0, 3), ['VK9', 'VK41', 'VK5'])
  assert.ok(!P.order(ctx, st).includes('VK8'))
  st = P.detect(ctx = ctxFor(card(), URL0))
  assert.equal(st.flags.aktuelLaast, false)
  assert.ok(P.tasks.VK41.show.target(ctx, st) === null, 'a typed Aktuel: no stock count first')
  // a bill of materials: VK8 and VK39 never ring its Aktuel (a change produces the item, VK40); VK38 only on a part
  st = P.detect(ctx = ctxFor(card({ bom: true, fifo: true }), URL0))
  assert.equal(st.flags.aktuelLaast, false, 'a bill of materials keeps Aktuel open with FIFO')
  assert.ok(P.tasks.VK8.show.target(ctx, st) === null && P.tasks.VK39.show.target(ctx, st) === null)
  assert.ok(P.tasks.VK40.show.target(ctx, st) === ctx.doc.querySelector('input[name="ny_lagerbeh[1]"]'))
  ctx.doc.querySelector('form').append(new El('a', { href: '?opener=&id=2&returside=varer.php&vis_samlevarer=on' }))
  assert.ok(P.tasks.VK38.show.target(ctx, P.detect(ctx)) === null, 'the part link of a bill of materials is not VK38')
  const part = card()
  const dv = part.querySelector('input[name="delvare"]')
  dv.value = 'on'
  dv.attrs.value = 'on'
  part.querySelector('form').append(new El('a', { href: '?id=3&vis_samlevarer=on' }))
  st = P.detect(ctx = ctxFor(part, URL0))
  assert.equal(st.flags.delvare, true)
  assert.ok(P.tasks.VK38.show.target(ctx, st) === part.querySelector('a[href*="vis_samlevarer=on"]'))
  assert.equal(P.tasks.VK23.steps.length, 3)

  // field keys of the indexed and camel-case names
  const el = (attrs) => new El('input', attrs)
  assert.equal(P.fieldKey(el({ name: 'kostpris[0]' })), 'kostpris')
  assert.equal(P.fieldKey(el({ name: 'kostpris[2]' })), 'lev_kostpris')
  assert.equal(P.fieldKey(el({ name: 'ny_lagerbeh[3]' })), 'ny_lagerbeh')
  assert.equal(P.fieldKey(el({ name: 'ny_beholdning' })), 'ny_lagerbeh')
  assert.equal(P.fieldKey(el({ name: 'beskrivelseAlias' })), 'beskrivelse_alias')
  assert.equal(P.fieldKey(el({ id: 'bestil-antal' })), 'bestil_antal')
  assert.equal(P.fieldKey(el({ name: 'min_lager' })), null, 'the default rule serves plain names')
})

test('L1 varekort: the new card, the confirmations and the two lookups', () => {
  const P = C.pages['lager/varekort.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/lager/varekort.php'
  const doc = (...els) => {
    const d = new Doc()
    const body = new El('body')
    d.append(body)
    body.append(new El('a', { href: 'javascript:confirmClose(x)', accesskey: 'L' }))
    body.append(...els)
    return d
  }
  let ctx = ctxFor(doc(new El('input', { type: 'hidden', name: 'returside', value: '' }), new El('input', { class: 'inputbox', type: 'text', name: 'varenr', value: '' })), URL0)
  let st = P.detect(ctx)
  assert.equal(st.state, 'ny')
  assert.equal(P.name(ctx, st), 'help.lkort.name_new')
  eq(P.order(ctx, st), ['VK1', 'VK2'])
  assert.ok(P.tasks.VK1.show.target(ctx, st) === ctx.doc.querySelector('input[name="varenr"]'))
  assert.equal(P.tasks.VK28.show.target(ctx, st), null, 'not opened from an order')
  eq(P.questionOrder(ctx, st), ['Q11', 'Q8', 'Q5'], 'a new card: the number first, no question about the group it does not show')

  ctx = ctxFor(doc(new El('form', { name: 'confirmStockChange' }), new El('input', { type: 'hidden', name: 'varenr', value: 'K1' }),
    new El('textarea', { name: 'reason' }), new El('input', { name: 'initials' }), new El('input', { type: 'submit', name: 'acceptStockChange', value: 'OK' }),
    new El('input', { type: 'submit', name: 'cancelStockChange', value: 'Afbryd' })), URL0)
  st = P.detect(ctx)
  assert.equal(st.state, 'bekraeftLager')
  eq(P.order(ctx, st), ['VK30', 'VK31'])
  assert.equal(P.tasks.VK8.show.target(ctx, st), null)
  assert.equal(P.questionOrder(ctx, st)[0], 'Q9', 'the stock confirmation: is the change posted')

  ctx = ctxFor(doc(new El('input', { type: 'submit', name: 'ChangeDescription', value: 'Ja' }), new El('input', { type: 'submit', name: 'ChangeDescription', value: 'Nej' })), URL0)
  st = P.detect(ctx)
  assert.equal(st.state, 'bekraeftBeskrivelse')
  eq(P.order(ctx, st), ['VK32'])
  assert.equal(P.questionOrder(ctx, st)[0], 'Q10', 'the description confirmation: old invoices keep their text')
  assert.ok(P.questions.Q10.show.target === 'input[name="ChangeDescription"][value="Ja"]')

  const supplierRow = new El('a', { href: 'varekort.php?id=3&konto_id=9&returside=x&vare_lev_id=9' })
  ctx = ctxFor(doc(new El('a', { href: 'varekort.php?opener=&sort=kontonr&funktion=kontoOpslag&id=3&vare_id=3' }),
    new El('td', { onclick: "JavaScript:window.open('../kreditor/kreditorkort.php?returside=../includes/luk.php')" }).append(new El('button')), supplierRow), URL0)
  st = P.detect(ctx)
  assert.equal(st.state, 'leverandoropslag')
  eq(P.order(ctx, st), ['VK33', 'VK34'])
  assert.ok(ctx.doc.querySelector(P.tasks.VK33.show.target) === supplierRow)
  assert.ok(P.tasks.VK34.show.target(ctx, st) === ctx.doc.querySelector('td button'))
  assert.equal(P.tasks.VK35.show.target(ctx, st), null, 'the header links carry vare_id too; VK35 rings only in the item lookup')

  const itemRow = new El('a', { href: 'varekort.php?vare_id=7&fokus=varenr&id=3' })
  ctx = ctxFor(doc(new El('a', { href: 'varekort.php?sort=varenr&funktion=vareopslag&x=&fokus=varenr&id=3&vis_kost=on' }), itemRow), URL0)
  st = P.detect(ctx)
  assert.equal(st.state, 'vareopslag')
  assert.ok(P.tasks.VK35.show.target(ctx, st) === itemRow)
  assert.equal(P.tasks.VK34.show.target(ctx, st), null)
})

test('L1 lagerlog and the small pages of the card: states and orders', () => {
  const LOG = C.pages['lager/productCardIncludes/stockLog.php']
  const page = (rows, unposted) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('a', { href: '../varekort.php?id=3' }).append(new El('input', { type: 'button', value: '← Luk' })))
    const table = new El('table').append(new El('thead').append(new El('tr').append(new El('th', {}, { text: 'Tidspunkt' }))))
    const tbody = new El('tbody')
    for (let i = 0; i < rows; i++) {
      const tr = new El('tr', { class: 'table-row' })
      if (unposted && i === 0) tr.append(new El('td').append(new El('em', {}, { text: '(ikke bogført)' })))
      tr.append(new El('td').append(new El('a', { href: '../../kreditor/ordre.php?id=1&ro=1', target: '_blank' })))
      tbody.append(tr)
    }
    table.append(tbody)
    body.append(table)
    return doc
  }
  let ctx = ctxFor(page(2, true), 'https://ssl12.saldi.dk/asr_hj/lager/productCardIncludes/stockLog.php?id=3')
  let st = LOG.detect(ctx)
  assert.equal(st.state, 'liste')
  assert.equal(st.flags.ikkeBogfoert, true)
  eq(LOG.order(ctx, st).slice(0, 3), ['LG1', 'LG3', 'LG2'])
  eq(LOG.about(ctx, st), ['help.lkort.about.log', 'help.lkort.about.log_ikke_bogfoert'])
  ctx = ctxFor(page(0, false), 'https://ssl12.saldi.dk/asr_hj/lager/productCardIncludes/stockLog.php?id=3')
  assert.equal(LOG.detect(ctx).state, 'tom')

  const one = (route, ...els) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('a', { href: 'varekort.php?id=3', accesskey: 'L' }), ...els)
    return [C.pages[route], ctxFor(doc, 'https://ssl12.saldi.dk/asr_hj/' + route + '?id=3')]
  }
  let [P, c] = one('lager/ret_varenr.php', new El('input', { type: 'text', name: 'nyt_varenr', value: 'K1' }))
  eq(P.order(c, P.detect(c)), ['RV1', 'RV2', 'RV3'])
  ;[P, c] = one('lager/ret_varenralias.php', new El('input', { type: 'text', name: 'nyt_varenr_alias', value: '' }))
  eq(P.order(c, P.detect(c)), ['RA1', 'RV3'])
  ;[P, c] = one('lager/varefoto.php', new El('input', { type: 'file', name: 'uploadedfile' }))
  eq(P.order(c, P.detect(c)), ['VF1', 'RV3'])
  ;[P, c] = one('lager/varefoto.php', new El('img'), new El('a', { href: 'varefoto.php?id=3&sletfoto=1' }))
  eq(P.order(c, P.detect(c)), ['VF2', 'RV3'])
  ;[P, c] = one('lager/emballage.php', new El('div', { class: 'emb-saved' }), new El('table', { class: 'emb' }).append(new El('select', { name: 'category[0]' })))
  st = P.detect(c)
  assert.equal(st.flags.gemt, true)
  eq(P.about(c, st), ['help.lvx.about.emb', 'help.lvx.about.emb_gemt'])
  assert.equal(P.fieldKey(c.doc.querySelector('[name="category[0]"]')), 'category')
  ;[P, c] = one('lager/happyhour.php', new El('form', { name: 'happyhour' }), new El('input', { name: 'startdato[0]' }), new El('input', { type: 'checkbox', name: 'slet[0]' }),
    new El('input', { name: 'startdato[1]' }))
  st = P.detect(c)
  eq(P.order(c, st), ['HH1', 'HH2', 'RV3'])
  assert.ok(P.tasks.HH1.show.target(c, st) === c.doc.querySelector('[name="startdato[1]"]'), 'the empty row is the last one')
})

test('L2 modtageliste and modtagelse: the list, a new, open, changed and received goods receipt, the refusal as the problem card', () => {
  const ML = C.pages['lager/modtageliste.php']
  const VM = C.pages['lager/modtagelse.php']
  const base = 'https://ssl12.saldi.dk/asr_hj/lager/'

  /** The receipt list (lager/modtageliste.php:69-180): Ny, the Vis alle/Vis egne row, open and received lists, the tips. */
  function listPage ({ open = 0, recv = 0, alle = false, tip2 = false } = {}) {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    const table = new El('table')
    body.append(table)
    table.append(new El('a', { href: 'modtageliste.php?sort=id&rf=desc&vis=', accesskey: 'N' }).append(
      new El('button', { onclick: "javascript:liste=window.open('modtagelse.php?returside=modtageliste.php&tjek=-1','liste','');liste.focus();" }, { text: 'Ny' })))
    table.append(new El('tr').append(new El('td', { colspan: '6', align: 'center' }).append(
      new El('a', { href: alle ? 'modtageliste.php?sort=id&rf=desc' : 'modtageliste.php?sort=id&rf=desc&vis=alle' }, { text: alle ? 'Vis egne' : 'Vis alle' }))))
    for (let i = 1; i <= open; i++) {
      table.append(new El('tr').append(new El('td', { onclick: "javascript:liste" + i + "=window.open('modtagelse.php?tjek=" + i + '&liste_id=' + i + "&returside=modtageliste.php','liste" + i + "','');" }, { text: String(i) })))
    }
    for (let i = 1; i <= recv; i++) {
      table.append(new El('tr').append(new El('td').append(new El('a', { href: 'modtagelse.php?liste_id=' + (10 + i) + '&returside=modtageliste.php' }, { text: String(10 + i) }))))
    }
    if (!open && !recv) {
      table.append(new El('tr').append(new El('td', { colspan: '3', align: 'right' }, { text: 'TIP 1:' })))
      if (tip2) table.append(new El('tr').append(new El('td', { colspan: '3', align: 'right' }, { text: 'TIP 2:' })))
    }
    return doc
  }

  let ctx = ctxFor(listPage(), base + 'modtageliste.php')
  let st = ML.detect(ctx)
  assert.equal(st.state, 'tom')
  eq(ML.order(ctx, st), ['ML1', 'ML7'])
  eq(ML.questionOrder(ctx, st), ['Q2', 'Q1', 'Q3'], 'no receipts yet: what the page is for next to the purchase order')
  ctx = ctxFor(listPage({ tip2: true }), base + 'modtageliste.php')
  st = ML.detect(ctx)
  assert.equal(st.flags.kolleger, true)
  eq(ML.order(ctx, st), ['ML1', 'ML7', 'ML4'])
  eq(ML.about(ctx, st), ['help.lmod.about.tom', 'help.lmod.about.kolleger'])
  assert.equal(ML.tasks.ML1.show.target(ctx, st).tagName, 'BUTTON', 'Ny: the button of the shell layout')
  assert.ok(ML.tasks.ML2.show.target(ctx, st) === null, 'no open list to ring on an empty list')

  const listDoc = listPage({ open: 2, recv: 1 })
  ctx = ctxFor(listDoc, base + 'modtageliste.php')
  st = ML.detect(ctx)
  assert.equal(st.state, 'liste')
  assert.equal(st.flags.aabne, true)
  assert.equal(st.flags.modtagne, true)
  assert.equal(st.flags.alle, false)
  eq(ML.order(ctx, st).slice(0, 3), ['ML2', 'ML1', 'ML6'])
  assert.ok(ML.tasks.ML2.show.target(ctx, st) === listDoc.querySelector('td[onclick*="modtagelse.php?tjek=1&"]'))
  assert.ok(ML.tasks.ML3.show.target(ctx, st) === listDoc.querySelector('a[href^="modtagelse.php?liste_id=11"]'))
  assert.ok(ML.tasks.ML4.show.target(ctx, st) !== null)
  assert.ok(ML.tasks.ML5.show.target(ctx, st) === null, 'Vis egne only when every list is shown')
  ctx = ctxFor(listPage({ recv: 1, alle: true }), base + 'modtageliste.php?vis=alle')
  st = ML.detect(ctx)
  assert.equal(st.flags.alle, true)
  assert.ok(ML.order(ctx, st).includes('ML5') && !ML.order(ctx, st).includes('ML4'))

  /** The goods receipt (lager/modtagelse.php:214-269): header, the form, saved lines with ret, the input row, modtag. */
  function receipt ({ listeId = '0', id = '0', lines = 0, inputRow = true, editVarenr = '', modtag = false, alert = '' } = {}) {
    const doc = new Doc()
    const body = new El('body', alert ? { bgcolor: '#eeeef0', onload: "javascript:alert('" + alert + "')" } : { bgcolor: '#eeeef0' })
    doc.append(body)
    body.append(new El('a', { href: 'modtageliste.php', accesskey: 'L' }, { text: 'Tilbage' }))
    if (listeId !== '0') body.append(new El('a', { href: 'modtagelse.php', accesskey: 'N' }, { text: 'Ny' }))
    const table = new El('table')
    body.append(table)
    table.append(new El('form', { name: 'modtagelse', action: 'modtagelse.php?liste_id=' + listeId + '&id=' + id, method: 'post' }))
    for (let i = 1; i <= lines; i++) {
      const tr = new El('tr').append(new El('td', {}, { text: 'V' + i }))
      if (inputRow) tr.append(new El('td').append(new El('a', { href: 'modtagelse.php?liste_id=' + listeId + '&id=' + (100 + i) }, { text: 'ret' })))
      table.append(tr)
    }
    if (inputRow) {
      table.append(new El('tr').append(new El('input', { type: 'text', name: 'varenr', value: editVarenr }),
        new El('input', { type: 'text', name: 'antal_ny', value: editVarenr ? ' 5.000 ' : '  ' }), new El('input', { type: 'submit', name: 'ok', value: 'OK' })))
      if (modtag) table.append(new El('tr').append(new El('input', { type: 'submit', name: 'modtag', value: 'modtag' })))
    }
    return doc
  }

  ctx = ctxFor(receipt(), base + 'modtagelse.php')
  st = VM.detect(ctx)
  assert.equal(st.state, 'ny')
  eq(VM.name(ctx, st), 'help.lmod.name_ny')
  eq(VM.order(ctx, st), ['VM1', 'ML7'])
  assert.equal(VM.problem(ctx, st), '')
  assert.equal(VM.tasks.VM1.show.target(ctx, st).name, 'varenr')

  ctx = ctxFor(receipt({ alert: 'Der er ikke nogle åbne indkøbsordrer på varenr ' }), base + 'modtagelse.php')
  st = VM.detect(ctx)
  assert.equal(st.state, 'ny')
  assert.equal(st.flags.fejl, true)
  assert.equal(VM.problem(ctx, st), 'VM4')
  eq(VM.about(ctx, st), ['help.lmod.about.fejl', 'help.lmod.about.ny'])

  const openDoc = receipt({ listeId: '7', lines: 2, modtag: true })
  ctx = ctxFor(openDoc, base + 'modtagelse.php?liste_id=7')
  st = VM.detect(ctx)
  assert.equal(st.state, 'aaben')
  eq(VM.name(ctx, st), ['help.lmod.name_modtagelse', '7'])
  eq(VM.order(ctx, st), ['VM2', 'VM1', 'VM3', 'ML7'])
  assert.equal(VM.tasks.VM2.show.target(ctx, st).name, 'modtag')
  assert.ok(VM.tasks.VM3.show.target(ctx, st) === openDoc.querySelector('a[href="modtagelse.php?liste_id=7&id=101"]'), 'ret of the first line')

  const editDoc = receipt({ listeId: '7', id: '102', lines: 1, editVarenr: 'V2', modtag: true })
  ctx = ctxFor(editDoc, base + 'modtagelse.php?liste_id=7&id=102')
  st = VM.detect(ctx)
  assert.equal(st.state, 'ret')
  eq(VM.order(ctx, st), ['VM3', 'VM2'])
  assert.ok(VM.tasks.VM3.show.target(ctx, st) === editDoc.querySelector('input[name="antal_ny"]'), 'the Antal of the line being changed (live check 24.15)')
  assert.ok(VM.tasks.VM1.show.target(ctx, st) === null, 'VM1 is not offered on a line being changed')

  ctx = ctxFor(receipt({ listeId: '9', lines: 2, inputRow: false }), base + 'modtagelse.php?liste_id=9&returside=modtageliste.php')
  st = VM.detect(ctx)
  assert.equal(st.state, 'modtaget')
  eq(VM.order(ctx, st), ['ML6', 'VM5'])
  eq(VM.about(ctx, st), ['help.lmod.about.modtaget', 'help.lmod.about.bogfoering'])
  assert.equal(VM.tasks.VM5.show.target(ctx, st).getAttribute('href'), 'modtagelse.php')
  assert.ok(VM.tasks.VM2.show.target(ctx, st) === null && VM.tasks.VM3.show.target(ctx, st) === null)
})

test('L2 lagerflyt: the move form, read-only, an empty warehouse, one warehouse, too many and a move done', () => {
  const FL = C.pages['lager/lagerflyt.php']
  const url = 'https://ssl12.saldi.dk/asr_hj/lager/lagerflyt.php?lager=1&vare_id=5'

  /** lager/lagerflyt.php:215-235 */
  function movePage ({ max = '12.000', others = 1, readOnly = false, script = '' } = {}) {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    if (script) body.append(new El('script', { type: 'text/javascript' }, { text: script }))
    const table = new El('table')
    body.append(table)
    table.append(new El('tr').append(new El('td', { colspan: '4', align: 'center' }).append(new El('b', {}, { text: 'Flyt vare A-100 fra lager 1' }))))
    // MB-62 (lagerflyt.php:225-226): a read-only right gets the heading and Beh. only, not a disabled form
    if (readOnly) {
      table.append(new El('tr').append(new El('td', { align: 'center' }).append(new El('b', {}, { text: 'Beh.' })), new El('td', {}, { text: '12,00' })))
      return doc
    }
    table.append(new El('input', { type: 'text', name: 'antal', value: '0' }))
    const select = new El('select', { name: 'nyt_lager' })
    for (let i = 0; i < others; i++) select.append(new El('option', { value: String(i + 2) }, { text: (i + 2) + ' : Lager ' + (i + 2) }))
    table.append(select)
    table.append(new El('input', { type: 'hidden', name: 'max_antal', value: max }))
    table.append(new El('input', { type: 'submit', name: 'opdater', value: 'Opdater' }))
    table.append(new El('a', { href: 'varer.php' }).append(new El('input', { type: 'button', value: 'Luk' })))
    return doc
  }

  let ctx = ctxFor(movePage(), url)
  let st = FL.detect(ctx)
  assert.equal(st.state, 'flyt')
  eq(FL.name(ctx, st), ['help.lflyt.name', 'A-100'])
  eq(FL.order(ctx, st), ['FL1', 'FL2'])
  assert.equal(FL.problem(ctx, st), '')
  assert.equal(FL.tasks.FL1.show.target(ctx, st).name, 'opdater')
  eq(FL.questionOrder(ctx, st), ['Q1', 'Q3', 'Q2'], 'Q2 repeats FL1 and comes last')
  eq(FL.questions.Q3.steps, ['help.lflyt.task.fl2.step2', 'help.lflyt.task.fl2.step3'])

  ctx = ctxFor(movePage({ readOnly: true }), url)
  st = FL.detect(ctx)
  assert.equal(st.state, 'kunLaes')
  eq(FL.name(ctx, st), ['help.lflyt.name', 'A-100'])
  eq(FL.order(ctx, st), ['FL7'])
  assert.ok(FL.tasks.FL1.show.target(ctx, st) === null, 'no Opdater on a read-only page, so none is rung')

  ctx = ctxFor(movePage({ max: '' }), url)
  st = FL.detect(ctx)
  assert.equal(st.flags.tom, true)
  assert.equal(FL.problem(ctx, st), 'FL4')
  eq(FL.order(ctx, st), ['FL4'])

  ctx = ctxFor(movePage({ others: 0 }), url)
  st = FL.detect(ctx)
  assert.equal(st.flags.ingenAndre, true)
  assert.equal(FL.problem(ctx, st), 'FL5')
  assert.ok(FL.tasks.FL1.show.target(ctx, st) === null && FL.tasks.FL3.show.target(ctx, st) === null, 'one warehouse: nowhere to move to (live check 24.15)')
  assert.ok(FL.questions.Q2.show.target(ctx, st) === null)

  ctx = ctxFor(movePage({ script: "alert('Du kan maks flytte 12,00');" }), url)
  st = FL.detect(ctx)
  assert.equal(st.flags.forMange, true)
  assert.equal(FL.problem(ctx, st), 'FL3')
  assert.equal(FL.tasks.FL3.show.target(ctx, st).name, 'antal')

  ctx = ctxFor(movePage({ max: '0.000', script: "alert('12 flyttet fra lager 1 til lager 2');" }), url)
  st = FL.detect(ctx)
  assert.equal(st.flags.flyttet, true)
  assert.equal(FL.problem(ctx, st), '', 'moving everything leaves Beh. 0, which is no problem')
  eq(FL.order(ctx, st), ['FL6', 'FL2'])
})

test('L2 optalling: the count form, an item, the list, uncounted items, import, the preview, a recount and the refusals', () => {
  const LO = C.pages['lager/optalling.php']
  const base = 'https://ssl12.saldi.dk/asr_hj/lager/optalling.php'

  /** lager/optalling.php:196-409 and the views of :573-1001, :1401-1420 */
  function countPage ({ view = 'start', counts = 0, lagre = 1, script = '', onload = '', ejExist = false } = {}) {
    const doc = new Doc()
    const body = new El('body', onload ? { onload: "javascript:alert('" + onload + "')" } : {})
    doc.append(body)
    if (script) body.append(new El('script', {}, { text: script }))
    body.append(new El('a', { href: 'rapport.php', accesskey: 'L' }, { text: 'Luk' }))
    body.append(new El('a', { href: view === 'import' ? 'optalling.php' : 'optalling.php?importer=1&lager=1&dato=09-11-2026' }, { text: view === 'import' ? 'Afbryd' : 'Importér' }))
    if (ejExist) body.append(new El('a', { href: '../temp/test/optael_ej_exist.txt', target: 'blank' }, { text: 'Ikke oprettede varer' }))
    if (view === 'import') {
      body.append(new El('form', { enctype: 'multipart/form-data', action: 'optalling.php?importer=1&lager=1&dato=09-11-2026', method: 'POST' }))
      body.append(new El('input', { type: 'text', name: 'dato', value: '09-11-2026' }), new El('input', { name: 'uploadfile', type: 'file' }),
        new El('input', { type: 'submit', value: 'Hent' }))
      return doc
    }
    const table = new El('table')
    body.append(table)
    if (view === 'vare') {
      table.append(new El('input', { type: 'hidden', name: 'dato', value: '09-11-2026' }), new El('input', { type: 'text', name: 'optalt' }),
        new El('input', { type: 'hidden', name: 'varenr', value: 'A-100' }), new El('input', { type: 'hidden', name: 'lager', value: '1' }))
    } else {
      table.append(new El('input', { type: 'text', name: 'dato', value: '09-11-2026' }))
      if (lagre) {
        const sel = new El('select', { name: 'lager' })
        for (let i = 1; i <= lagre; i++) sel.append(new El('option', { value: String(i) }))
        table.append(sel)
      }
      table.append(new El('input', { type: 'text', name: 'varenr' }))
    }
    table.append(new El('input', { type: 'submit', value: 'OK' }))
    for (let i = 1; i <= counts; i++) {
      table.append(new El('tr').append(new El('td').append(new El('a', { href: 'optalling.php?vare_id=' + i + '&varenr=V' + i + '&slet=y&lager=1&gentael=' }, { text: 'X' }))))
    }
    if (view === 'start' || view === 'liste') {
      table.append(new El('a', { href: 'optalling.php?vis_ej_optalt=1&dato=09-11-2026&lager=1' }, { text: 'her' }))
      if (counts) table.append(new El('a', { href: 'optalling.php?nulstil=1&lager=1&dato=09-11-2026' }, { text: 'her' }))
    }
    if (view === 'ikkeOptalt') table.append(new El('a', { href: 'optalling.php?varenr=B-200&dato=09-11-2026' }, { text: 'B-200' }))
    if (view === 'kontrol') table.append(new El('a', { href: 'optalling.php?bogfor=2&nulstil_ej_optalt=&dato=09-11-2026&lager=1&godkend_regdif=' }, { text: 'her' }))
    if (view === 'gentael') table.append(new El('a', { href: 'optalling.php?nulstil_ej_optalt=&dato=09-11-2026&bogfor=1&lager=1&godkend_regdif=1' }, { text: 'her' }))
    if (counts && ['start', 'liste', 'vare', 'ikkeOptalt'].includes(view)) {
      table.append(new El('form', { name: 'optalling', action: 'optalling.php?bogfor=1&lager=1', method: 'post' }))
      table.append(new El('input', { type: 'text', name: 'dato', value: '09-11-2026' }), new El('input', { type: 'checkbox', name: 'nulstil_ej_optalt' }),
        new El('input', { type: 'submit', value: 'Bogfør' }))
    }
    return doc
  }

  let ctx = ctxFor(countPage(), base)
  let st = LO.detect(ctx)
  assert.equal(st.state, 'start')
  eq(LO.order(ctx, st), ['LO1', 'LO7', 'LO5'])
  assert.equal(LO.tasks.LO1.show.target(ctx, st).name, 'varenr')
  assert.ok(LO.tasks.LO3.show.target(ctx, st) === null, 'no Bogfør before anything is counted')

  const listDoc = countPage({ view: 'liste', counts: 2, lagre: 2 })
  ctx = ctxFor(listDoc, base + '?gentael=&lager=1')
  st = LO.detect(ctx)
  assert.equal(st.state, 'liste')
  assert.equal(st.flags.harOptalt, true)
  assert.equal(st.flags.flereLagre, true)
  eq(LO.order(ctx, st).slice(0, 3), ['LO1', 'LO5', 'LO3'])
  assert.ok(LO.tasks.LO3.show.target(ctx, st) === listDoc.querySelector('input[value="Bogfør"]'))
  assert.ok(LO.tasks.LO10.show.target(ctx, st) === listDoc.querySelector('a[href*="slet=y"]'))
  assert.ok(LO.tasks.LO9.show.target(ctx, st) === listDoc.querySelector('a[href*="nulstil=1"]'))
  eq(LO.questionOrder(ctx, st), ['Q4', 'Q1', 'Q2'], 'the list with the posting form: what happens to uncounted items')
  assert.ok(ctx.doc.querySelector(LO.questions.Q4.show.target) === listDoc.querySelector('input[name="nulstil_ej_optalt"]'))

  ctx = ctxFor(countPage({ view: 'vare', counts: 1 }), base + '?gentael=&lager=1')
  st = LO.detect(ctx)
  assert.equal(st.state, 'vare')
  eq(LO.order(ctx, st), ['LO2', 'LO10', 'LO3'])
  assert.equal(LO.tasks.LO2.show.target(ctx, st).name, 'optalt')
  assert.ok(LO.tasks.LO1.show.target(ctx, st) === null, 'the hidden varenr of an item is not rung')

  ctx = ctxFor(countPage({ view: 'ikkeOptalt', lagre: 2 }), base + '?vis_ej_optalt=1&dato=09-11-2026&lager=2')
  st = LO.detect(ctx)
  assert.equal(st.state, 'ikkeOptalt')
  eq(LO.about(ctx, st), ['help.lopt.about.ikke_optalt', 'help.lopt.about.ikke_optalt_lager'])
  assert.equal(LO.tasks.LO6.show.target(ctx, st).getAttribute('href'), 'optalling.php?varenr=B-200&dato=09-11-2026')

  const importDoc = countPage({ view: 'import' })
  ctx = ctxFor(importDoc, base + '?importer=1&lager=1')
  st = LO.detect(ctx)
  assert.equal(st.state, 'import')
  eq(LO.order(ctx, st), ['LO15'])
  eq(LO.questionOrder(ctx, st), ['Q2', 'Q3', 'Q1'], 'an import adds to the counts; variants are counted by file')
  assert.ok(LO.tasks.LO15.show.target(ctx, st) === importDoc.querySelector('input[type="submit"]'))
  assert.ok(LO.tasks.LO7.show.target(ctx, st) === null, 'Afbryd is not Importér')

  ctx = ctxFor(countPage({ view: 'kontrol' }), base + '?bogfor=1&lager=1')
  st = LO.detect(ctx)
  assert.equal(st.state, 'kontrol')
  eq(LO.order(ctx, st), ['LO4', 'LO12'])
  eq(LO.questionOrder(ctx, st), ['Q1', 'Q4', 'Q2'])
  eq(LO.about(ctx, st), ['help.lopt.about.kontrol', 'help.lopt.about.finans'])
  assert.ok(LO.tasks.LO4.show.target(ctx, st).getAttribute('href').includes('bogfor=2'))

  ctx = ctxFor(countPage({ view: 'gentael', counts: 1 }), base + '?bogfor=1&lager=1')
  st = LO.detect(ctx)
  assert.equal(st.state, 'gentael')
  assert.equal(LO.problem(ctx, st), 'LO11')
  assert.ok(LO.tasks.LO11.show.target(ctx, st) !== null)

  ctx = ctxFor(countPage({ onload: 'Lagerregulering udført.' }), base + '?bogfor=2&lager=1')
  st = LO.detect(ctx)
  assert.equal(st.state, 'udfoert')
  eq(LO.order(ctx, st), ['LO12'])

  ctx = ctxFor(countPage({ script: "alert('varenr: t-1 indeholder varianter og kan kun reguleres på varekort eller gennem csv fil');" }), base)
  st = LO.detect(ctx)
  assert.equal(st.flags.varianter, true)
  assert.equal(st.flags.ikkeFundet, false)
  assert.equal(LO.problem(ctx, st), 'LO13')
  ctx = ctxFor(countPage({ script: "alert('varenr: zz ikke fundet');" }), base)
  st = LO.detect(ctx)
  assert.equal(st.flags.ikkeFundet, true)
  assert.equal(LO.problem(ctx, st), 'LO14')
  ctx = ctxFor(countPage({ ejExist: true }), base + '?vis_ej_exist=1&lager=1')
  st = LO.detect(ctx)
  assert.equal(st.flags.ikkeOprettet, true)
  assert.equal(LO.problem(ctx, st), '', 'the link is printed after every import, so it is no problem card')
  eq(LO.order(ctx, st)[0], 'LO8')
})

test('L2 batch pages: Batch oversigt and Batchoplysninger, their flags, problem cards, targets and field keys', () => {
  const BO = C.pages['lager/batch_oversigt.php']
  const BU = C.pages['lager/batch_udfyld.php']
  const base = 'https://ssl12.saldi.dk/asr_hj/lager/'

  /** lager/batch_oversigt.php:72-163; rows: [[id, colour]] */
  function overview ({ rows = [], missing = false, canEdit = true } = {}) {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    const table = new El('table')
    body.append(table)
    table.append(new El('tr').append(new El('td', { colspan: '8' }).append(new El('table').append(
      new El('td').append(new El('a', { href: 'varekort.php?id=5', accesskey: 'L' }).append(new El('button', {}, { text: 'Tilbage' }))),
      new El('td', {}, { text: 'Batch oversigt' }), new El('td')))))
    table.append(new El('tr').append(new El('td', { colspan: '8' }).append(new El('b', {}, { text: 'A-100 : Mælk 1 l' }))))
    const tools = new El('td', { colspan: '8' })
    if (missing) tools.append(new El('span', { style: 'color:#b45309' }, { text: '2 batches på lager mangler udløbsdato eller batchnummer' }))
    if (canEdit) tools.append(new El('a', { href: 'batch_udfyld.php?vare_id=5&returside=batch_oversigt.php%3Fvare_id%3D5' }).append(new El('button', { type: 'button' })))
    table.append(new El('tr').append(tools))
    const head = new El('tr')
    for (const t of ['Batch ID', 'Batchnr.', 'Udløbsdato', 'Dage til udløb', 'Restlager', 'Købsdato', 'Lager', 'Købsordre']) head.append(new El('td').append(new El('b', {}, { text: t })))
    table.append(head)
    for (const [id, colour] of rows) {
      const tr = new El('tr', colour ? { style: 'background-color:#' + colour + ';' } : {})
      tr.append(new El('td', {}, { text: String(id) }))
      for (let i = 0; i < 7; i++) tr.append(new El('td', {}, { text: '-' }))
      table.append(tr)
    }
    table.append(new El('tr').append(new El('td', { colspan: '4' }).append(new El('b', {}, { text: 'Total restlager' })), new El('td'), new El('td', { colspan: '3' })))
    return doc
  }

  let doc = overview({ rows: [[41, 'ffcccc'], [42, 'ccffcc'], [43, '']], missing: true })
  let ctx = ctxFor(doc, base + 'batch_oversigt.php?vare_id=5')
  let st = BO.detect(ctx)
  assert.equal(st.state, 'liste')
  assert.equal(st.data.batches, 3)
  assert.equal(st.flags.udloebet, true)
  assert.equal(st.flags.mangler, true)
  assert.equal(st.flags.kanRette, true)
  eq(BO.name(ctx, st), ['help.lbat.name', 'A-100'])
  assert.equal(BO.problem(ctx, st), 'BO2')
  eq(BO.order(ctx, st).slice(0, 3), ['BO2', 'BO3', 'BO1'])
  assert.ok(BO.tasks.BO1.show.target(ctx, st) === doc.querySelectorAll('tr')[4], 'the first batch row, not the header')
  assert.ok(BO.tasks.BO3.show.target === 'a[href^="batch_udfyld.php"]')
  ctx = ctxFor(overview({ rows: [[41, 'ccffcc']], missing: true, canEdit: false }), base + 'batch_oversigt.php?vare_id=5')
  st = BO.detect(ctx)
  eq(BO.order(ctx, st).slice(0, 2), ['BO4', 'BO1'])
  ctx = ctxFor(overview(), base + 'batch_oversigt.php?vare_id=5')
  st = BO.detect(ctx)
  assert.equal(st.state, 'tom')
  eq(BO.order(ctx, st), ['BO7'])
  eq(BO.questionOrder(ctx, st), ['Q3', 'Q2', 'Q1'], 'no batches: no coloured lines to ask about')

  /** lager/batch_udfyld.php:269-391 */
  function fill ({ vare = false, rows = 2, alle = false, suggest = false, err = false, ok = false, sog = '' } = {}) {
    const d = new Doc()
    const body = new El('body')
    d.append(body)
    if (err) body.append(new El('div', { class: 'bu-msg bu-err' }, { text: 'A-100: Ugyldig udløbsdato (31-02-2026)' }))
    if (ok) body.append(new El('div', { class: 'bu-msg bu-ok' }, { text: 'Batchoplysningerne er gemt.' }))
    if (!vare) {
      body.append(new El('div', { class: 'bu-msg bu-info' }).append(new El('a', { href: 'batch_udfyld.php?alle=' + (alle ? 0 : 1) + '&sog=&returside=udlobsrapport.php' })))
      body.append(new El('input', { class: 'inputbox', type: 'search', name: 'sog', value: sog }))
    }
    if (!rows) {
      body.append(new El('p', {}, { text: 'Der er ingen batches, der mangler batchnummer eller udløbsdato.' }))
      return d
    }
    if (suggest) body.append(new El('p').append(new El('button', { type: 'button', class: 'LightButton', onclick: 'buSuggest()' })))
    const table = new El('table', { id: 'bu-table' })
    body.append(table)
    for (let i = 1; i <= rows; i++) {
      table.append(new El('tr', { id: 'bu-row-' + i }).append(new El('input', { type: 'text', name: 'batch_no[' + i + ']' }),
        new El('input', { type: 'date', name: 'due_date[' + i + ']' }), new El('button', { type: 'button', onclick: 'buSplit(' + i + ')' })))
    }
    table.append(new El('input', { type: 'text', name: 'split[1][1][antal]' }))
    body.append(new El('input', { type: 'submit', name: 'gem', value: 'Gem' }))
    return d
  }

  doc = fill({ suggest: true })
  ctx = ctxFor(doc, base + 'batch_udfyld.php?returside=udlobsrapport.php')
  st = BU.detect(ctx)
  assert.equal(st.state, 'liste')
  assert.equal(st.flags.vare, false)
  assert.equal(st.flags.forslag, true)
  eq(BU.order(ctx, st), ['BU2', 'BU1', 'BU3', 'BU6', 'BU4'])
  assert.ok(BU.tasks.BU1.show.target(ctx, st) === doc.querySelector('input[name="batch_no[1]"]'))
  assert.ok(BU.tasks.BU3.show.target(ctx, st) === doc.querySelector('button[onclick="buSplit(1)"]'))
  assert.equal(BU.fieldKey(doc.querySelector('input[name="due_date[2]"]')), 'due_date')
  assert.equal(BU.fieldKey(doc.querySelector('input[name="split[1][1][antal]"]')), 'split_antal')
  assert.ok(BU.fieldKey(doc.querySelector('input[name="sog"]')) === null, 'sog keeps the default rule')
  eq(BU.questionOrder(ctx, st), ['Q2', 'Q4', 'Q5'])
  eq(BU.questions.Q2.steps, ['help.lbat.task.bu1.step1', 'help.lbat.task.bu1.step2'], 'filled in here, not through Ret batchoplysninger')
  eq(BO.questions.Q2.steps, ['help.lbat.task.bo3.step1', 'help.lbat.task.bo3.step2'])
  ctx = ctxFor(fill({ err: true }), base + 'batch_udfyld.php')
  st = BU.detect(ctx)
  assert.equal(BU.problem(ctx, st), 'BU7')
  eq(BU.questionOrder(ctx, st), ['Q5', 'Q2', 'Q4'])
  eq(BU.about(ctx, st)[0], 'help.lbat.about.fejl')
  ctx = ctxFor(fill({ vare: true, rows: 1 }), base + 'batch_udfyld.php?vare_id=5&returside=batch_oversigt.php')
  st = BU.detect(ctx)
  assert.equal(st.flags.vare, true)
  eq(BU.order(ctx, st), ['BU1', 'BU3'])
  ctx = ctxFor(fill({ rows: 0 }), base + 'batch_udfyld.php')
  st = BU.detect(ctx)
  assert.equal(st.state, 'tom')
  eq(BU.order(ctx, st), ['BU4', 'BU8'])
  ctx = ctxFor(fill({ rows: 0, sog: 'mælk' }), base + 'batch_udfyld.php?sog=m%C3%A6lk')
  st = BU.detect(ctx)
  eq(BU.order(ctx, st), ['BU6', 'BU4', 'BU8'])
  ctx = ctxFor(fill({ alle: true }), base + 'batch_udfyld.php?alle=1')
  st = BU.detect(ctx)
  assert.equal(st.flags.alle, true)
  assert.ok(BU.order(ctx, st).includes('BU5') && !BU.order(ctx, st).includes('BU4'))
})

test('L2 stykliste and vareproduktion: the bill of materials and the production confirmation', () => {
  const SL = C.pages['lager/stykliste.php']
  const VP = C.pages['lager/vareproduktion.php']

  /** includes/stykliste.php:48-71 */
  function bom (parts) {
    const doc = new Doc()
    const table = new El('table')
    doc.append(new El('body').append(table))
    table.append(new El('tr').append(new El('td').append(new El('big').append(new El('b', {}, { text: 'Stykliste for ' }).append(
      new El('a', { href: 'varekort.php?id=5' }, { text: 'KIT-1' }))))))
    for (let i = 1; i <= parts; i++) {
      table.append(new El('tr').append(new El('td').append(new El('a', { href: '../lager/varekort.php?id=' + (20 + i) + '&returside=%2Fasr_hj%2Flager%2Fstykliste.php%3Fid%3D5' }, { text: 'P' + i }))))
    }
    return doc
  }

  let doc = bom(2)
  let ctx = ctxFor(doc, 'https://ssl12.saldi.dk/asr_hj/lager/stykliste.php?id=5')
  let st = SL.detect(ctx)
  assert.equal(st.state, 'liste')
  eq(SL.name(ctx, st), ['help.lprod.name', 'KIT-1'])
  eq(SL.order(ctx, st).slice(0, 3), ['SL1', 'SL5', 'SL4'])
  eq(SL.questionOrder(ctx, st), ['Q1', 'Q2', 'Q3'])
  assert.ok(SL.questions.Q3 === VP.questions.Q3, 'one question about the warehouse on both pages')
  assert.ok(SL.tasks.SL2.show.target(ctx, st) === doc.querySelector('a[href^="../lager/varekort.php?id=21"]'))
  ctx = ctxFor(bom(0), 'https://ssl12.saldi.dk/asr_hj/lager/stykliste.php?id=5')
  st = SL.detect(ctx)
  assert.equal(st.state, 'tom')
  eq(SL.order(ctx, st), ['SL3'])
  assert.ok(SL.tasks.SL2.show.target(ctx, st) === null)

  /** lager/vareproduktion.php:189-205 */
  doc = new Doc()
  const table = new El('table')
  doc.append(new El('body').append(table))
  table.append(new El('form', { name: 'vareproduktion', action: 'vareproduktion.php?antal=1&samlevare=on&lager=1', method: 'post' }))
  table.append(new El('input', { type: 'hidden', name: 'id[0]', value: '5' }), new El('input', { type: 'submit', name: 'OK', value: 'OK' }),
    new El('input', { type: 'submit', name: 'cancel', value: 'Afbryd' }))
  ctx = ctxFor(doc, 'https://ssl12.saldi.dk/asr_hj/lager/vareproduktion.php?id=5&antal=1&lager=1&ny_beholdning=4&samlevare=on')
  st = VP.detect(ctx)
  assert.equal(st.state, 'bekraeft')
  eq(VP.order(ctx, st), ['VP1', 'VP2', 'VP3'])
  eq(VP.questionOrder(ctx, st), ['Q2', 'Q3', 'Q4'])
  eq(VP.questions.Q4.steps, ['help.lprod.task.vp3.step2', 'help.lprod.task.vp3.step3'])
  assert.equal(VP.tasks.VP1.show.target(ctx, st).name, 'OK')
  assert.equal(VP.tasks.VP2.show.target(ctx, st).name, 'cancel')
  ctx = ctxFor(new Doc(), 'https://ssl12.saldi.dk/asr_hj/lager/vareproduktion.php')
  st = VP.detect(ctx)
  assert.equal(st.state, 'unknown')
  assert.ok(VP.tasks.VP1.show.target(ctx, st) === null)
})

test('L3 varerapporter: the form, the item sales report and the detailed report, with their targets', () => {
  const RP = C.pages['lager/rapport.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/lager/rapport.php'
  const page = (...kids) => { const doc = new Doc(); doc.append(new El('body').append(...kids)); return doc }
  const ctx = (doc) => ctxFor(doc, URL0)
  const ring = (id, doc) => { const c = ctx(doc); const s = RP.detect(c); const t = RP.tasks[id].show.target; return typeof t === 'function' ? t(c, s) : doc.querySelector(t) }

  // The selection form (lager/rapport.php:377-478): the inputs are printed in a table, so they are no descendants of the form
  const blue = (value) => new El('input', { class: 'button blue medium', type: 'submit', name: 'submit', value })
  const form = page(new El('form', { name: 'rapport' }), new El('select', { name: 'varegruppe' }), new El('select', { name: 'afd' }),
    new El('select', { name: 'ref' }), new El('input', { type: 'text', name: 'dato_fra', value: '01-01-2026' }),
    new El('input', { type: 'text', name: 'dato_til', value: '09-11-2026' }), new El('input', { name: 'varenr' }),
    new El('input', { type: 'checkbox', name: 'detaljer' }), new El('input', { type: 'checkbox', name: 'kun_salg' }),
    new El('input', { type: 'checkbox', name: 'lagertal' }), new El('input', { class: 'button green medium', type: 'submit', name: 'submit', value: '  OK  ' }),
    blue('Lagerstatus'), new El('input', { class: 'button gray medium', type: 'submit', name: 'inventoryCount', value: 'Lageroptælling' }),
    blue('Prisliste'), blue('Salg pr. postnummer'))
  let s = RP.detect(ctx(form))
  eq([s.state, s.flags.afdelinger, s.flags.leverandoerer], ['forside', true, false])
  eq(Object.keys(s.flags).sort(), [...RP.flags].sort(), 'detect() gives every flag the page lists')
  eq(RP.order(ctx(form), s).slice(0, 3), ['RA1', 'RA3', 'RA2'])
  eq(RP.about(ctx(form), s), ['help.lrap.about.forside'])
  assert.equal(RP.problem(ctx(form), s), '')
  assert.equal(ring('RA1', form).getAttribute('value'), '  OK  ')
  assert.equal(ring('RA3', form).getAttribute('value'), 'Lagerstatus')
  assert.equal(ring('RA5', form).getAttribute('value'), 'Prisliste')
  assert.equal(ring('RA6', form).getAttribute('value'), 'Salg pr. postnummer')
  assert.equal(ring('RA4', form).getAttribute('name'), 'inventoryCount')
  assert.equal(ring('RA8', form).getAttribute('name'), 'detaljer')
  assert.ok(ring('RV6', form) === null, 'Tilbage of the report rings nothing on the form')
  assert.ok(ring('RV2', form) === null)
  assert.equal(RP.fieldKey(new El('input', { name: 'dato_til' })), 'periode')
  assert.equal(RP.fieldKey(new El('input', { name: 'vrs_dg' })), 'vrs')
  assert.equal(RP.fieldKey(new El('input', { name: 'vrs_enhed' })), 'vrs_tekst')
  assert.equal(RP.fieldKey(new El('select', { id: 'vrPageSize' })), 'linjer')
  assert.equal(RP.fieldKey(new El('input', { name: 'varenr' })), null, 'the default rule')

  // The summary report (:522, :586-606, :886-975, :1700-1880)
  const report = ({ lagertal = '', kunSalg = '', behold = false, status = '1-2 af 2', search = '' } = {}) => {
    const back = new El('a', { class: 'button red small', accesskey: 'L', href: 'rapport.php?varegruppe=0&afd=&lev=&ref=&date_from=2026-01-01&date_to=2026-11-09&varenr=&varenavn=&detaljer=&kun_salg=' + kunSalg + '&lagertal=' + lagertal })
    back.append(new El('button'))
    const csv = new El('a', { href: '../temp/test_838/salgsrapport.csv', target: '_blank' }).append(new El('button'))
    const searchRow = new El('tr', { class: 'vr-search-row' }).append(new El('th').append(new El('input', { type: 'text', name: 'varenr', value: search })),
      new El('th').append(new El('input', { type: 'text', name: 'vrs_bestilt' })), new El('th').append(new El('input', { type: 'text', name: 'vrs_dg' })))
    if (behold) searchRow.append(new El('th').append(new El('input', { type: 'text', name: 'vrs_behold' })), new El('th').append(new El('input', { type: 'text', name: 'vrs_vaerdi' })))
    const table = new El('table', { id: 'vrGridTable' }).append(new El('thead').append(searchRow),
      new El('tbody').append(new El('tr', { id: 'item1' }), new El('tr', { id: 'summeret' }), new El('tr', { id: 'totals' })))
    const nav = new El('span', { id: 'vrNavButtons' }).append(new El('span', { class: 'navbutton' }))
    return page(new El('div', { id: 'vrPageFlex' }).append(back, csv), table, new El('span', { id: 'vrPageStatus' }, { text: status }), new El('select', { id: 'vrPageSize' }), nav)
  }
  let doc = report()
  s = RP.detect(ctx(doc))
  eq([s.state, s.flags.kunSalg, s.flags.lagertal, s.flags.ingenLagergruppe, s.flags.tom, s.flags.flereSider, s.flags.soegning],
    ['varesalg', false, false, false, false, false, false])
  eq(RP.order(ctx(doc), s).slice(0, 3), ['RV1', 'RV5', 'RV2'])
  eq(RP.about(ctx(doc), s), ['help.lrap.about.varesalg', 'help.lrap.about.summeret'])
  assert.equal(ring('RV1', doc).getAttribute('name'), 'vrs_dg')
  assert.equal(ring('RV2', doc).tagName, 'BUTTON')
  assert.equal(ring('RV4', doc).getAttribute('id'), 'totals', 'the totals row is the last row')
  assert.equal(ring('RV6', doc).tagName, 'BUTTON')
  assert.ok(ring('RV10', doc) === null, 'no stock value without Beregn lagerbeh./værdi')
  assert.ok(ring('RA1', doc) === null && ring('RA7', doc) === null, 'the form targets ring nothing on the report')
  // Beregn lagerbeh./værdi without a stock-kept group: the columns are missing (:951-956), the problem card
  doc = report({ lagertal: 'on' })
  s = RP.detect(ctx(doc))
  eq([s.flags.lagertal, s.flags.ingenLagergruppe], [true, true])
  assert.equal(RP.problem(ctx(doc), s), 'RV9')
  eq(RP.about(ctx(doc), s), ['help.lrap.about.varesalg', 'help.lrap.about.ingen_lagergruppe'])
  assert.equal(RP.questionOrder(ctx(doc), s)[0], 'Q3')
  assert.equal(RP.questions.Q3.steps[0], 'help.lrap.q.q3.step1', 'from the report the way to Lagerstatus is the sidebar: the button is on the form only')
  doc = report({ lagertal: 'on', behold: true })
  s = RP.detect(ctx(doc))
  eq([s.flags.lagertal, s.flags.ingenLagergruppe, RP.problem(ctx(doc), s)], [true, false, ''])
  eq(RP.order(ctx(doc), s).slice(0, 2), ['RV10', 'RV1'])
  assert.equal(ring('RV10', doc).getAttribute('id'), 'totals')
  // Kun salg / DB and an empty search result
  s = RP.detect(ctx(report({ kunSalg: 'on', lagertal: 'on' })))
  eq([s.flags.kunSalg, s.flags.lagertal, s.flags.ingenLagergruppe], [true, false, false])
  eq(RP.about(ctx(doc), s)[0], 'help.lrap.about.kun_salg')
  doc = report({ status: '0 af 0', search: 'ZZ' })
  s = RP.detect(ctx(doc))
  eq([s.flags.tom, s.flags.soegning, RP.order(ctx(doc), s)], [true, true, ['RV5', 'RV6']])
  eq(RP.questionOrder(ctx(doc), s)[0], 'Q2')

  // The detailed report: no search row, the sales and purchase lines link to their orders (:1243-1272, :1404-1449)
  const back = new El('a', { accesskey: 'L', href: 'rapport.php?varegruppe=0&detaljer=on&kun_salg=&lagertal=' }).append(new El('button'))
  const detail = page(new El('div', { id: 'vrPageFlex' }).append(back), new El('table', { id: 'vrGridTable' }).append(new El('tbody').append(
    new El('tr', { class: 'vr-col-title-row' }),
    new El('tr').append(new El('td', { onclick: "javascript:k_ordre=window.open('../kreditor/ordre.php?id=4&returside=../includes/luk.php')" })),
    new El('tr').append(new El('td', { class: 'hover-preview', 'data-preview-url': '../debitor/ordre.php?id=9&returside=../includes/luk.php' })))),
  new El('span', { id: 'vrPageStatus' }, { text: '1-1 af 1' }))
  s = RP.detect(ctx(detail))
  eq([s.state, s.flags.tom], ['detaljeret', false])
  eq(RP.order(ctx(detail), s).slice(0, 3), ['RD1', 'RD2', 'RD3'])
  assert.ok(ring('RD1', detail).getAttribute('data-preview-url').includes('debitor/ordre.php'))
  assert.ok(ring('RD2', detail).getAttribute('onclick').includes('kreditor/ordre.php'))
  assert.ok(ring('RV4', detail) === null && ring('RV7', detail) === null, 'summary-only targets ring nothing in Detaljeret')
  assert.equal(ring('RV6', detail).tagName, 'BUTTON')
  assert.equal(RP.detect(ctx(new Doc())).state, 'unknown')
})

test('L3 lagerstatus: a red stock figure is the problem card, and the filters give the flags', () => {
  const LS = C.pages['lager/lagerstatus.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/lager/lagerstatus.php'
  const page = ({ red = false, status = '1-2 af 2', lager = '0', dateType = 'levdate', search = '' } = {}) => {
    const doc = new Doc()
    const lagervalg = new El('select', { name: 'lagervalg', value: lager }).append(new El('option', { value: '0' }), new El('option', { value: '1' }), new El('option', { value: '2' }))
    const head = new El('div', { id: 'lsPageFlex' }).append(new El('a', { href: 'rapport.php', accesskey: 'L' }).append(new El('button')),
      new El('a', { href: 'lagerstatus.php?dato=09-11-2026&varegruppe=0:Alle&csv=1&zStock=' }).append(new El('button')),
      lagervalg, new El('select', { name: 'varegruppe' }), new El('input', { class: 'inputbox', type: 'text', name: 'dato', value: '09-11-2026' }),
      new El('select', { name: 'dateType', value: dateType }), new El('input', { type: 'checkbox', name: 'zStock' }), new El('input', { type: 'checkbox', name: 'showClosed' }))
    const rows = new El('tbody').append(new El('tr').append(new El('td').append(new El('a', { href: 'varespor.php?vare_id=7' }))))
    if (red) rows.append(new El('tr').append(new El('td').append(new El('a', { href: '/asr_hj/lager/lagerstatus.php?opdater=7' }))))
    const table = new El('table', { id: 'lsGridTable' }).append(new El('thead').append(new El('tr', { class: 'ls-search-row' }).append(
      new El('th').append(new El('input', { type: 'text', name: 'varenr', value: search })), new El('th').append(new El('input', { type: 'text', name: 'lss_antal' })))), rows)
    doc.append(new El('body').append(head, new El('input', { type: 'hidden', name: 'dato', value: '09-11-2026' }), table,
      new El('span', { id: 'lsPageStatus' }, { text: status }), new El('select', { id: 'lsPageSize' })))
    return doc
  }
  let doc = page()
  let s = LS.detect(ctxFor(doc, URL0))
  eq([s.state, s.flags.skaev, s.flags.tom, s.flags.lagervalgt, s.flags.flereLagre, s.flags.fakturadato], ['liste', false, false, false, true, false])
  eq(Object.keys(s.flags).sort(), [...LS.flags].sort())
  eq(LS.order(ctxFor(doc, URL0), s).slice(0, 3), ['LS1', 'LS10', 'LS8'])
  eq(LS.about(ctxFor(doc, URL0), s), ['help.lstat.about.ls_liste', 'help.lstat.about.ls_vaerdi'])
  const ring = (id, d) => { const c = ctxFor(d, URL0); const st = LS.detect(c); const t = LS.tasks[id].show.target; return typeof t === 'function' ? t(c, st) : d.querySelector(t) }
  assert.equal(ring('LS1', doc).getAttribute('type'), 'text', 'the date field, not the search form\'s hidden copy')
  assert.ok(ring('LS10', doc) === ring('LS1', doc), 'LS10 rings the date of its first step')
  assert.equal(ring('LS3', doc).tagName, 'BUTTON')
  assert.equal(ring('LS7', doc).getAttribute('href'), 'varespor.php?vare_id=7')
  assert.equal(ring('LS8', doc).getAttribute('name'), 'varenr')
  assert.ok(ring('LS2', doc) === null)
  doc = page({ red: true, lager: '2', dateType: 'fakturadate' })
  s = LS.detect(ctxFor(doc, URL0))
  eq([s.flags.skaev, s.flags.lagervalgt, s.flags.fakturadato], [true, true, true])
  assert.equal(LS.problem(ctxFor(doc, URL0), s), 'LS2')
  eq(LS.order(ctxFor(doc, URL0), s)[0], 'LS2')
  assert.ok(ring('LS2', doc).getAttribute('href').includes('opdater=7'))
  doc = page({ status: '0 af 0', search: 'K70' })
  s = LS.detect(ctxFor(doc, URL0))
  eq([s.flags.tom, s.flags.soegning, LS.order(ctxFor(doc, URL0), s)], [true, true, ['LS8', 'LS6', 'LS1']])
  assert.equal(LS.fieldKey(new El('select', { name: 'dateType' })), 'datotype')
  assert.equal(LS.fieldKey(new El('input', { name: 'zStock' })), 'nul_lager')
  assert.equal(LS.fieldKey(new El('input', { name: 'showClosed' })), 'udgaaede')
  assert.equal(LS.fieldKey(new El('input', { name: 'lss_kobspris' })), 'lss')
  assert.equal(LS.detect(ctxFor(new Doc(), URL0)).state, 'unknown')
})

test('L3 udløbsrapport and prisliste: missing batch details are the problem card; an empty price list leads with 0 på lager', () => {
  const UD = C.pages['lager/udlobsrapport.php']
  const URL0 = 'https://ssl12.saldi.dk/asr_hj/lager/udlobsrapport.php'
  const page = ({ missing = false, link = false, rows = [] } = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('a', { href: '../index/menu.php', accesskey: 'L' }).append(new El('button')))
    if (missing) {
      const box = new El('div', { style: 'background:#fff7e6;border-left:4px solid #b45309;padding:.6em 1em;margin:.4em 0' })
      if (link) box.append(new El('a', { href: 'batch_udfyld.php?returside=udlobsrapport.php' }))
      body.append(box)
    }
    body.append(new El('select', { onchange: "window.location='udlobsrapport.php?dage='+this.value+''" }),
      new El('a', { href: 'udlobsrapport.php?udloebet=1' }), new El('a', { href: 'udlobsrapport.php?dage=30&csv=1' }))
    const tbody = new El('tbody')
    for (const color of rows) {
      tbody.append(new El('tr', { style: 'background-color:#' + color + ';' }).append(new El('td').append(
        new El('a', { href: 'varekort.php?id=3' }), new El('a', { href: 'batch_oversigt.php?vare_id=3&returside=udlobsrapport.php%3Fdage%3D30' }))))
    }
    body.append(new El('table').append(tbody))
    return doc
  }
  let doc = page({ missing: true, link: true, rows: ['ffcccc', 'ffffcc'] })
  let s = UD.detect(ctxFor(doc, URL0))
  eq([s.state, s.flags.mangler, s.flags.kanUdfylde, s.flags.udloebne, s.flags.tom, s.flags.kunUdloebne], ['liste', true, true, true, false, false])
  eq(Object.keys(s.flags).sort(), [...UD.flags].sort())
  assert.equal(UD.problem(ctxFor(doc, URL0), s), 'UD1')
  eq(UD.order(ctxFor(doc, URL0), s).slice(0, 4), ['UD1', 'UD3', 'UD5', 'UD4'])
  eq(UD.about(ctxFor(doc, URL0), s), ['help.ludl.about.liste', 'help.ludl.about.mangler'])
  const ring = (id, d, url = URL0) => { const c = ctxFor(d, url); const st = UD.detect(c); const t = UD.tasks[id].show.target; return typeof t === 'function' ? t(c, st) : d.querySelector(t) }
  assert.ok(ring('UD1', doc).getAttribute('href').startsWith('batch_udfyld.php'))
  assert.equal(ring('UD2', doc).tagName, 'SELECT')
  assert.ok(ring('UD3', doc).getAttribute('href').includes('udloebet=1'))
  assert.ok(ring('UD4', doc).getAttribute('href').includes('csv=1'))
  assert.ok(ring('UD5', doc).getAttribute('href').startsWith('batch_oversigt.php'))
  // Without the right to change items the box has no link: still the problem card, whose first step says so
  s = UD.detect(ctxFor(page({ missing: true }), URL0))
  eq([s.flags.mangler, s.flags.kanUdfylde, UD.problem(ctxFor(doc, URL0), s)], [true, false, 'UD1'])
  // Vis kun udløbne with nothing expired
  const url = URL0 + '?udloebet=1'
  s = UD.detect(ctxFor(page(), url))
  eq([s.flags.kunUdloebne, s.flags.tom, UD.order(ctxFor(page(), url), s), UD.problem(ctxFor(page(), url), s)], [true, true, ['UD2'], ''])
  eq(UD.about(ctxFor(page(), url), s), ['help.ludl.about.liste', 'help.ludl.about.tom_udloebne'])

  const PL = C.pages['lager/pricelist.php']
  const PURL = 'https://ssl12.saldi.dk/asr_hj/lager/pricelist.php'
  const plist = ({ status = '1-3 af 3', hidden = false, text = '' } = {}) => {
    const doc = new Doc()
    const all = new El('input', { type: 'checkbox', name: 'show_all_products' })
    all.checked = hidden
    doc.append(new El('body').append(new El('div', { id: 'plPageFlex' }).append(new El('a', { href: 'rapport.php', accesskey: 'L' }).append(new El('button')),
      new El('a', { href: 'pricelist.php?csv=1&varegruppe=0:Alle' }).append(new El('button'))),
    new El('select', { name: 'varegruppe' }), new El('input', { type: 'text', name: 'custom_text', value: text }), new El('input', { type: 'checkbox', name: 'zero_stock' }), all,
    new El('input', { type: 'checkbox', name: 'show_salgspris' }), new El('input', { type: 'submit', name: 'submit', value: 'Vis' }),
    new El('a', { href: 'pricelist.php?autoprint=1&varegruppe=0:Alle', target: '_blank' }), new El('table', { id: 'plGridTable' }),
    new El('span', { id: 'plPageStatus' }, { text: status })))
    return doc
  }
  doc = plist()
  s = PL.detect(ctxFor(doc, PURL))
  eq([s.state, s.flags.tom, s.flags.skjulte, s.flags.fritekst], ['liste', false, false, false])
  eq(PL.order(ctxFor(doc, PURL), s).slice(0, 3), ['PL1', 'PL4', 'PL5'])
  eq(PL.questionOrder(ctxFor(doc, PURL), s), ['Q1', 'Q2'], 'a missing item leads also on a full list')
  assert.equal(doc.querySelector(PL.tasks.PL4.show.target).getAttribute('target'), '_blank')
  assert.equal(PL.tasks.PL5.show.target(ctxFor(doc, PURL), s).tagName, 'BUTTON')
  doc = plist({ status: '0 af 0', hidden: true, text: 'Forår' })
  s = PL.detect(ctxFor(doc, PURL))
  eq([s.flags.tom, s.flags.skjulte, s.flags.fritekst, PL.order(ctxFor(doc, PURL), s)], [true, true, true, ['PL7', 'PL6', 'PL2', 'PL1']])
  assert.equal(PL.fieldKey(new El('select', { id: 'plPageSize' })), 'linjer')
})

test('L3 small Lager pages: Varespor, Salg pr. postnummer, Beholdningsliste, Lagerhistorik and Beholdningsrapport', () => {
  const page = (...kids) => { const doc = new Doc(); doc.append(new El('body').append(...kids)); return doc }
  const VS = C.pages['lager/varespor.php']
  const VURL = 'https://ssl12.saldi.dk/asr_hj/lager/varespor.php?vare_id=7'
  let doc = page(new El('a', { href: 'lagerstatus.php', accesskey: 'L' }).append(new El('button')),
    new El('td', { onclick: "javascript:k_ordre=window.open('../kreditor/ordre.php?id=4&returside=../includes/luk.php','k_ordre','')" }))
  let s = VS.detect(ctxFor(doc, VURL))
  eq([s.state, s.flags.ordrer, VS.order(ctxFor(doc, VURL), s)], ['spor', true, ['VS1', 'VS2', 'VS3', 'VS4']])
  assert.ok(doc.querySelector(VS.tasks.VS1.show.target).getAttribute('onclick').includes('ordre.php'))
  assert.equal(VS.tasks.VS4.show.target(ctxFor(doc, VURL), s).tagName, 'BUTTON')

  const SR = C.pages['lager/salg_rapport.php']
  const SURL = 'https://ssl12.saldi.dk/asr_hj/lager/salg_rapport.php'
  const grid = (postnr) => page(new El('div', { id: 'srPageFlex' }).append(new El('a', { href: '../lager/rapport.php', accesskey: 'L' }).append(new El('button'))),
    new El('div', { id: 'datatable-wrapper-salg_rapport' }).append(new El('table').append(new El('thead').append(
      new El('tr').append(new El('th', { class: 'total_dkk sortable-td' })),
      new El('tr').append(new El('th').append(new El('input', { type: 'text', name: 'search[salg_rapport][varenr]', value: '' })),
        new El('th').append(new El('input', { type: 'text', id: 'postnr_range_input' }), new El('input', { type: 'hidden', name: 'search[salg_rapport][postnr]', value: postnr })),
        new El('th').append(new El('input', { type: 'text', id: 'dato_range_picker' }), new El('input', { type: 'hidden', name: 'search[salg_rapport][fakturadate]', value: '2000-01-01:2099-12-31' })),
        new El('th').append(new El('button', { type: 'button', class: 'dropdown-trigger' })))))))
  doc = grid('')
  s = SR.detect(ctxFor(doc, SURL))
  eq([s.state, s.flags.filter], ['liste', false])
  eq(SR.about(ctxFor(doc, SURL), s), ['help.lstat.about.sr', 'help.lstat.about.sr_alt'])
  assert.equal(doc.querySelector(SR.tasks.SR5.show.target).getAttribute('class'), 'total_dkk sortable-td')
  assert.equal(doc.querySelector(SR.tasks.SR6.show.target).getAttribute('class'), 'dropdown-trigger')
  assert.equal(SR.tasks.SR7.show.target(ctxFor(doc, SURL), s).tagName, 'BUTTON')
  assert.equal(SR.detect(ctxFor(grid('1000:2999'), SURL)).flags.filter, true)

  const BH = C.pages['lager/beholdningsliste.php']
  const BURL = 'https://ssl12.saldi.dk/asr_hj/lager/beholdningsliste.php'
  doc = page(new El('a', { href: 'beholdningsliste.php?lager=1' }), new El('a', { href: 'beholdningsliste.php?lager=2' }))
  s = BH.detect(ctxFor(doc, BURL))
  eq([s.state, BH.order(ctxFor(doc, BURL), s)], ['vaelgLager', ['BH1']])
  const list = (kost) => page(kost ? new El('a', { href: 'beholdningsliste.php?sort=varenr&lager=1', id: 'skjul' }) : new El('a', { href: 'beholdningsliste.php?sort=varenr&lager=1&vis_kost=on', id: 'vis' }),
    new El('a', { href: 'beholdningsliste.php?sort=varenr&lager=1&vis_kost=' + (kost ? 'on' : ''), id: 'h1' }),
    new El('a', { href: 'beholdningsliste.php?sort=beholdning&lager=1&vis_kost=' + (kost ? 'on' : ''), id: 'h4' }))
  doc = list(false)
  s = BH.detect(ctxFor(doc, BURL + '?lager=1'))
  eq([s.state, s.flags.kostpris], ['liste', false])
  assert.equal(BH.tasks.BH3.show.target(ctxFor(doc, BURL), s).getAttribute('id'), 'vis')
  assert.equal(doc.querySelector(BH.tasks.BH2.show.target).getAttribute('id'), 'h4')
  doc = list(true)
  s = BH.detect(ctxFor(doc, BURL + '?sort=varenr&lager=1&vis_kost=on'))
  eq([s.state, s.flags.kostpris], ['liste', true])
  assert.equal(BH.tasks.BH3.show.target(ctxFor(doc, BURL), s).getAttribute('id'), 'skjul', 'Skjul Kostpris, not a heading that keeps vis_kost')

  const SL = C.pages['lager/stockLog.php']
  const LURL = 'https://ssl12.saldi.dk/asr_hj/lager/stockLog.php?id=5'
  doc = page(new El('table').append(new El('tr').append(new El('td', { align: 'center', width: '150px' })),
    new El('tr').append(new El('td', { align: 'right' })), new El('tr').append(new El('a', { href: 'varekort.php?id=5' }).append(new El('button')))))
  s = SL.detect(ctxFor(doc, LURL))
  eq([s.state, s.flags.tom, SL.order(ctxFor(doc, LURL), s)], ['log', false, ['SL1', 'SL2']])
  assert.equal(SL.tasks.SL2.show.target(ctxFor(doc, LURL), s).tagName, 'BUTTON')
  s = SL.detect(ctxFor(page(new El('a', { href: 'varekort.php?id=5' })), LURL))
  eq([s.flags.tom, SL.order(ctxFor(doc, LURL), s)], [true, ['SL2']])

  const MM = C.pages['lager/minmaxstock.php']
  const MURL = 'https://ssl12.saldi.dk/asr_hj/lager/minmaxstock.php?vgrp=0'
  doc = page(new El('a', { href: 'rapport.php?varenr=&afd=0&varegruppe=0&varenavn=', accesskey: 'L' }), new El('a', { href: '../temp/bizsys_49/minmax3.csv' }))
  s = MM.detect(ctxFor(doc, MURL))
  eq([s.state, MM.order(ctxFor(doc, MURL), s)], ['liste', ['MM1', 'MM2', 'MM3']])
  assert.ok(doc.querySelector(MM.tasks.MM2.show.target).getAttribute('href').includes('minmax'))
})

test('S1 indstillinger: the hub tells overview, search, read-only and no access apart, and rings the marked rows', () => {
  const HUB = C.pages['systemdata/settings.php']
  const U = 'https://ssl12.saldi.dk/asr_hj/systemdata/settings.php'
  const hub = ({ search = '', ro = false, attn = false, warn = false } = {}) => {
    const doc = new Doc()
    const body = new El('body')
    doc.append(body)
    body.append(new El('input', { type: 'search', id: 'sh-search', value: search }))
    if (ro) body.append(new El('div', { class: 'sk-note sk-info', role: 'status' }))
    const grid = new El('div', { class: 'sh-grid', id: 'sh-grid' })
    body.append(grid)
    for (const sub of ['firma', 'formularer', 'regnskab', 'debitorer']) {
      const item = new El('div', { class: 'sh-item', 'data-sub': sub })
      item.append(new El('button', { type: 'button', class: 'sk-list-row sh-btn', id: 'sh-b-' + sub }))
      const ul = new El('ul', { class: 'sh-links' })
      item.append(new El('div', { class: 'sh-body', id: 'sh-p-' + sub, hidden: '' }).append(ul))
      if (sub === 'formularer') {
        const li = new El('li', { class: 'sh-row', 'data-row': 'documents.email' })
        if (attn) li.append(new El('div', { class: 'sh-attn' }).append(new El('a', { class: 'sk-btn sk-btn-sm', href: 'settingsSection.php?s=documents.email' })))
        ul.append(li)
      }
      if (sub === 'debitorer') {
        const li = new El('li', { class: 'sh-row', 'data-row': 'sales.debtor_groups' })
        if (warn) li.append(new El('span', { class: 'sk-status sk-warn' }))
        ul.append(li)
      }
      grid.append(item)
    }
    body.append(new El('a', { href: 'settingsMoved.php' }))
    return doc
  }
  const plain = hub()
  const ps = HUB.detect(ctxFor(plain, U))
  eq(ps.state, 'oversigt')
  eq(HUB.order(ctxFor(plain, U), ps).slice(0, 3), ['HB1', 'HB4', 'HB5'])
  eq(HUB.problem(ctxFor(plain, U), ps), '')
  assert.ok(HUB.tasks.HB7.show.target(ctxFor(plain, U), ps) === null, 'HB7 rings nothing without a marked row')
  assert.ok(HUB.tasks.HB8.show.target(ctxFor(plain, U), ps) === null, 'HB8 rings nothing without missing groups')
  // a marked row and missing groups: the marked row first, then the setup
  const marked = hub({ attn: true, warn: true })
  const ms = HUB.detect(ctxFor(marked, U))
  eq(ms.flags, { opmaerksomhed: true, grundopsaetning: true, nyStruktur: false })
  eq(HUB.problem(ctxFor(marked, U), ms), 'HB7')
  eq(HUB.order(ctxFor(marked, U), ms).slice(0, 3), ['HB7', 'HB2', 'HB8'])
  assert.ok(HUB.tasks.HB7.show.target(ctxFor(marked, U), ms) === marked.querySelector('#sh-b-formularer'), 'HB7 rings the row that holds the attention line')
  assert.ok(HUB.tasks.HB8.show.target(ctxFor(marked, U), ms) === marked.querySelector('#sh-b-debitorer'), 'HB8 rings the row of the missing groups')
  eq(HUB.about(ctxFor(marked, U), ms), ['help.shub.about.oversigt', 'help.shub.about.grundopsaetning'])
  // search, read-only and no access
  eq(HUB.detect(ctxFor(hub({ search: 'moms' }), U)).state, 'soeg')
  const ro = HUB.detect(ctxFor(hub({ ro: true }), U))
  eq(ro.state, 'kunLaes')
  eq(HUB.order(ctxFor(hub({ ro: true }), U), ro), ['HB1', 'HB9'])
  const none = new Doc()
  none.append(new El('div', { class: 'sk-note sk-warn' }))
  eq(HUB.detect(ctxFor(none, U)).state, 'ingenAdgang')
  eq(HUB.fieldKey(new El('input', { id: 'sh-search' })), 'soeg')
})

test('S1 indstillinger: Regnskabsår reads the years, the open ones and the end of the newest year', () => {
  const FY = C.pages['systemdata/settingsSection.php'].variants['company.fiscal_years']
  const U = 'https://ssl12.saldi.dk/asr_hj/systemdata/settingsSection.php?s=company.fiscal_years'
  const doc = new Doc()
  const form = new El('form', { class: 'st-form st-rows-form', id: 'st-form', action: 'settingsSection.php?s=company.fiscal_years' })
  doc.append(new El('body').append(form))
  const tbody = new El('tbody')
  form.append(new El('div', { class: 'st-card st-rows', 'data-table': 'years' }).append(new El('table', { class: 'st-rt' }).append(tbody)))
  const rows = [['1', '1/1-2020 – 31/12-2020'], ['2', '1/1-2021 – 31/12-2021']]
  for (const [id, period] of rows) {
    const tr = new El('tr', { 'data-row': id })
    tr.append(new El('td').append(new El('input', { type: 'text', class: 'st-rin', name: 'r[years][' + id + '][beskrivelse]' })))
    tr.append(new El('td', { class: 'st-rc st-rc-derived st-rc-col-period' }).append(new El('span', {}, { text: period })))
    tr.append(new El('td').append(new El('input', { type: 'checkbox', class: 'st-rcheck', name: 'r[years][' + id + '][box5]', checked: '' })))
    tr.append(new El('td', { class: 'st-ra' }).append(new El('a', { class: 'st-ricon st-ricon-txt', href: 'regnskabskort.php?id=' + id }),
      new El('button', { type: 'button', class: 'st-ricon st-ricon-del', 'data-del': '' })))
    tbody.append(tr)
  }
  form.append(new El('button', { type: 'submit', class: 'st-btn st-btn-primary', form: 'st-create-years' }))
  const ctx = ctxFor(doc, U)
  const st = FY.detect(ctx)
  eq(st.state, 'liste')
  eq([st.flags.slutPasseret, st.flags.flereAabne, st.flags.foerste], [true, true, false])
  eq(FY.order(ctx, st).slice(0, 3), ['FY1', 'FY5', 'FY6'])
  assert.ok(FY.tasks.FY5.show.target(ctx, st) === doc.querySelector('input[name="r[years][1][box5]"]'), 'FY5 rings the old open year, not the newest')
  assert.ok(FY.tasks.FY6.show.target(ctx, st) === doc.querySelector('a[href="regnskabskort.php?id=2"]'), 'FY6 rings the newest year\'s opening balance')
  assert.ok(FY.tasks.FY1.show.target(ctx, st) === doc.querySelector('button[form="st-create-years"]'))
  assert.ok(FY.tasks.FY1.show.target(ctx, { state: 'tom', flags: {}, data: {} }) === null, 'FY1 rings nothing on an empty ledger')
  // one year, a new company's usual state (live check 24.15): nothing to switch to, close or archive
  const one = { state: 'liste', flags: {}, data: { aar: 1 } }
  eq(FY.order(ctx, one), ['FY1', 'FY6', 'FY8'])
  eq(FY.fieldKey(new El('input', { name: 'r[years][2][box5]' })), 'bogfoering_tilladt')
  eq(FY.fieldKey(new El('input', { name: 'end_month' })), null, 'the create card keeps the default rule')
  // read-only
  const ro = new Doc()
  ro.append(new El('div', { class: 'st-flash st-flash-info' }), new El('form', { id: 'st-form' }))
  eq(FY.detect(ctxFor(ro, U)).state, 'kunLaes')
})

test('S1 indstillinger: Moms, Valuta and E-mail follow their tables, the confirmation and the hub\'s warning', () => {
  const SP = C.pages['systemdata/settingsSection.php']
  const MO = SP.variants['finance.vat']
  const U = 'https://ssl12.saldi.dk/asr_hj/systemdata/settingsSection.php?s='
  const vat = new Doc()
  const form = new El('form', { id: 'st-form', action: 'settingsSection.php?s=finance.vat' })
  vat.append(new El('body').append(form))
  form.append(new El('button', { type: 'button', role: 'switch', class: 'st-switch', id: 'f-vat-registered', 'aria-checked': 'true' }))
  form.append(new El('div', { class: 'st-errors', role: 'alert' }).append(new El('a', { href: '#c-sales-n1-kodenr' })))
  const sales = new El('div', { class: 'st-card st-rows', 'data-table': 'sales' })
  sales.append(new El('div', { class: 'st-rempty' }).append(new El('button', { type: 'button', class: 'st-btn st-btn-primary', 'data-seed': '[]' })))
  sales.append(new El('div', { class: 'st-rfoot' }).append(new El('button', { type: 'button', class: 'st-btn', 'data-add': '' })))
  form.append(sales)
  const vc = ctxFor(vat, U + 'finance.vat')
  const vs = MO.detect(vc)
  eq(vs.state, 'tom')
  eq([vs.flags.standardsaet, vs.flags.fejl, vs.flags.ikkeMomsreg], [true, true, false])
  eq(MO.problem(vc, vs), 'MO10')
  eq(MO.order(vc, vs).slice(0, 3), ['MO10', 'MO1', 'MO4'])
  assert.ok(MO.tasks.MO3.show.target === '.st-rows[data-table="sales"] .st-rfoot button[data-add]')
  assert.ok(vat.querySelector(MO.tasks.MO3.show.target).getAttribute('data-add') === '', 'MO3 rings Tilføj momskode, not Tilføj selv')
  eq([MO.fieldKey(new El('input', { name: 'r[sales][3][box2]' })), MO.fieldKey(new El('select', { name: 'f[vat.period]' })),
    MO.fieldKey(new El('input', { name: 'r[goods][n1][box3]' }))], ['sats', 'momsperiode', 'modkonto'])
  // Valuta: a new rate waits for the confirmation of the exchange differences
  const VA = SP.variants['finance.currencies']
  const cur = new Doc()
  cur.append(new El('form', { id: 'st-form' }).append(new El('div', { class: 'st-confirm', role: 'alert' }).append(
    new El('button', { type: 'submit', class: 'st-btn st-btn-primary', name: 'confirmed', value: '1' }))))
  const cs = VA.detect(ctxFor(cur, U + 'finance.currencies'))
  eq(cs.state, 'bekraeft')
  eq(VA.problem(ctxFor(cur, U + 'finance.currencies'), cs), 'VA3')
  // E-mail: opened from the hub's Tilføj afsender
  const ML = SP.variants['documents.email']
  const mail = new Doc()
  mail.append(new El('form', { id: 'st-form' }).append(new El('input', { type: 'email', id: 'f-documents-email-sender_email-0', name: 'f[documents.email.sender_email.0]', value: '' })))
  const mc = ctxFor(mail, U + 'documents.email&field=documents.email.sender_email.0')
  const ms = ML.detect(mc)
  eq([ms.state, ms.flags.fraAdvarsel, ms.flags.egenServer], ['formular', true, false])
  eq(ML.problem(mc, ms), 'ML1')
  eq(ML.fieldKey(new El('input', { name: 'f[documents.email.sender_email.2]' })), 'sender_email')
  eq(ML.problem(ctxFor(mail, U + 'documents.email'), ML.detect(ctxFor(mail, U + 'documents.email'))), '')
  // the mail to customers from the old history list: the CRM replaced the list and does not send it (spec §22.10)
  assert.equal(ML.tasks.ML3, undefined)
  eq(ML.order(mc, ms), ['ML1', 'ML4', 'ML2'])
  eq([ML.fields.customer_subject.text, ML.fields.customer_text.text], ['help.smail.field.customer_subject.text2', 'help.smail.field.customer_text.text2'])
})

test('S1 indstillinger: the opening balance, the restore and personal settings put the blocking state first', () => {
  const RK = C.pages['systemdata/regnskabskort.php']
  const card = (kredit) => {
    const doc = new Doc()
    const form = new El('form', { name: 'year1', action: 'regnskabskort.php' })
    doc.append(new El('body').append(form))
    form.append(new El('input', { type: 'hidden', name: 'kodenr', value: '1' }), new El('input', { type: 'text', name: 'startmd', value: '1' }),
      new El('input', { type: 'text', name: 'debet[1]', value: '1.000,00' }), new El('input', { type: 'text', name: 'kredit[1]', value: '0,00' }),
      new El('input', { type: 'text', name: 'debet[2]', value: '0,00' }), new El('input', { type: 'text', name: 'kredit[2]', value: kredit }))
    return doc
  }
  const U = 'https://ssl12.saldi.dk/asr_hj/systemdata/regnskabskort.php?id=4'
  const off = RK.detect(ctxFor(card('900,00'), U))
  eq([off.state, off.flags.ubalance], ['aar1', true])
  eq(RK.problem(ctxFor(card('900,00'), U), off), 'RK2')
  eq(RK.name(ctxFor(card('900,00'), U), off), ['help.sregnkort.name', '1'])
  const even = RK.detect(ctxFor(card('1.000,00'), U))
  eq([even.flags.ubalance, RK.problem(ctxFor(card('1.000,00'), U), even)], [false, ''])
  eq(RK.fieldKey(new El('input', { name: 'kredit[7]' })), 'kredit')
  // restore: a copy of another ledger is checked first
  const RS = C.pages['admin/restore.php']
  const conf = new Doc()
  conf.append(new El('div', { class: 'restore-other-company', role: 'alert' }), new El('form').append(
    new El('input', { type: 'checkbox', name: 'confirm_restore', value: '1' }), new El('button', { type: 'submit', name: 'restore_confirm', value: '1' }),
    new El('button', { type: 'submit', name: 'restore_cancel', value: '1' })))
  const rc = ctxFor(conf, 'https://ssl12.saldi.dk/asr_hj/admin/restore.php')
  const rs = RS.detect(rc)
  eq([rs.state, rs.flags.andetFirma], ['bekraeft', true])
  eq(RS.problem(rc, rs), 'RS4')
  assert.ok(RS.tasks.RS4.show.target(rc, rs) === conf.querySelector('.restore-other-company'), 'RS4 rings the warning, not Afbryd')
  // personal settings: the tab from the address, a refused save first, two-factor through the tab link
  const PS = C.pages['systemdata/personalSettings.php']
  const ps = new Doc()
  const nav = new El('nav', { class: 'ps-tabs' })
  for (const t of ['profile', 'security', 'access', 'notifications']) nav.append(new El('a', { href: 'personalSettings.php?tab=' + t }))
  ps.append(new El('div', { class: 'ps-flash ps-flash-err' }), nav, new El('form', { id: 'ps-form' }).append(
    new El('input', { type: 'hidden', name: 'tab', value: 'profile' }), new El('input', { type: 'password', id: 'ps-confirm', name: 'confirm_kode' })))
  const pc = ctxFor(ps, 'https://ssl12.saldi.dk/asr_hj/systemdata/personalSettings.php')
  const pst = PS.detect(pc)
  eq([pst.state, pst.flags.afvist, pst.flags.revisor], ['profil', true, false])
  eq(PS.problem(pc, pst), 'PS9')
  assert.ok(PS.tasks.PS4.show.target(pc, pst) === ps.querySelector('a[href="personalSettings.php?tab=security"]'), 'PS4 rings the Sikkerhed tab on Profil')
  eq(PS.detect(ctxFor(ps, 'https://ssl12.saldi.dk/asr_hj/systemdata/personalSettings.php?tab=security')).state, 'sikkerhed')
})

test('S1 indstillinger: one page object serves every import page and names the route', () => {
  const IMP = C.pages['systemdata/importer_kontoplan.php']
  assert.ok(C.pages['systemdata/importer_varer.php'] === IMP)
  const up = new Doc()
  up.append(new El('form').append(new El('input', { name: 'uploadedfile', type: 'file' })))
  const uc = ctxFor(up, 'https://ssl12.saldi.dk/asr_hj/systemdata/importer_formular.php')
  const us = IMP.detect(uc)
  eq(us.state, 'upload')
  eq(IMP.name(uc, us), 'help.simpexp.name.formularer')
  eq(IMP.about(uc, us), ['help.simpexp.about.formularer', 'help.simpexp.about.upload'])
  const cols = new Doc()
  cols.append(new El('form').append(new El('select', { name: 'feltnavn[0]' }), new El('select', { name: 'feltnavn[1]' })))
  const cc = ctxFor(cols, 'https://ssl12.saldi.dk/asr_hj/systemdata/importer_kontoplan.php')
  eq(IMP.detect(cc).state, 'kolonner')
  eq(IMP.fieldKey(new El('select', { name: 'feltnavn[1]' })), 'feltnavn')
})

test('S1 and S3 after the live check (spec §24.15): Formularer, Tekster, Baggrunds- og bilagsfiler and Lagre', () => {
  const U = 'https://ssl12.saldi.dk/asr_hj/systemdata/'
  // Formularer: the form tag opens inside the table, so the selects are not its descendants in the browser
  const FK = C.pages['systemdata/formularkort.php']
  const fk = new Doc()
  const tr = new El('tr')
  fk.append(new El('body').append(new El('table').append(new El('tbody').append(new El('form', { name: 'formularvalg' }), tr))))
  tr.append(new El('td').append(new El('select', { name: 'form_nr' })), new El('td').append(new El('select', { name: 'art' })))
  let ctx = ctxFor(fk, U + 'formularkort.php?valg=formularer')
  let st = FK.detect(ctx)
  assert.equal(st.state, 'valg')
  assert.ok(FK.tasks.FK1.show.target === 'select[name="form_nr"]' && fk.querySelector(FK.tasks.FK1.show.target) !== null)
  assert.ok(fk.querySelector(FK.tasks.FK2.show.target) === fk.querySelector('select[name="art"]'))
  // Tekster: the csv link has no file, so there is no task for it
  const TX = C.pages['systemdata/tekster.php']
  const tx = new Doc()
  tx.append(new El('body').append(new El('form', { name: 'tekster' }).append(new El('input', { type: 'text', name: 'ny_tekst[1]' })), new El('a', { href: '' })))
  ctx = ctxFor(tx, U + 'tekster.php')
  eq(TX.order(ctx, TX.detect(ctx)), ['TX1'])
  assert.ok(!('TX2' in TX.tasks))
  // Baggrunds- og bilagsfiler: LU3 only when the filter form has a select
  const LU = C.pages['systemdata/logoupload.php']
  const lu = (select) => {
    const d = new Doc()
    const filter = new El('form', { id: 'filterForm' }).append(new El('input', { type: 'hidden', name: 'sprog', value: 'Dansk' }))
    if (select) filter.append(new El('select', { name: 'sprog' }))
    d.append(new El('body').append(filter, new El('select', { name: 'bg_valg' }), new El('input', { type: 'submit', name: 'bgfil' }), new El('select', { name: 'bilag_valg' })))
    return d
  }
  ctx = ctxFor(lu(false), U + 'logoupload.php')
  eq(LU.order(ctx, LU.detect(ctx)), ['LU1', 'LU2'])
  ctx = ctxFor(lu(true), U + 'logoupload.php')
  eq(LU.order(ctx, LU.detect(ctx)), ['LU1', 'LU2', 'LU3'])
  // Lagre without warehouses: only Opret et lager (nothing to link to a department yet)
  const LG = C.pages['systemdata/settingsSection.php'].variants['items.warehouses']
  eq(LG.order(ctxFor(new Doc(), U + 'settingsSection.php?s=items.warehouses'), { state: 'tom', flags: {}, data: {} }), ['LG1'])
})

test('S2 settings sections: Salg, Køb, Provision, Projekter and Kommissionsvarer have their own pages and groups', () => {
  const SP = C.pages['systemdata/settingsSection.php']
  const want = {
    'sales.debtor_card': 'sdebkort', 'sales.debtor_groups': 'sdebgrp', 'sales.orders': 'sordre', 'sales.reminders': 'srykker',
    'sales.discounts': 'srabat', 'sales.mysale': 'smitsalg', 'purchase.creditor_groups': 'skredgrp', 'purchase.orders': 'skoeb',
    'purchase.pricelists': 'sprisliste', 'organisation.commission': 'sprovision', 'organisation.projects': 'sprojekt', 'items.consignment': 'skommission'
  }
  for (const [key, group] of Object.entries(want)) {
    const page = SP.variants[key]
    assert.ok(page, key + ' has a page')
    assert.equal(page.group, group, key)
    assert.ok(SP.variant(ctxFor(new Doc(), 'https://x/asr_hj/systemdata/settingsSection.php?s=' + key)) === key, key + ': variant() names it')
    // another page in the frame: no state, no tasks
    const none = ctxFor(new Doc(), 'https://x/asr_hj/systemdata/settingsSection.php?s=' + key)
    const st = page.detect(none)
    eq(st.state, 'unknown', key)
    eq(page.order(none, st), [], key)
    eq(page.problem(none, st), '', key)
  }
})

test('S2 debitorgrupper and kreditorgrupper: rows, an empty year, Flyt samlekonto, the EU zone, read-only and a refused save', () => {
  const SP = C.pages['systemdata/settingsSection.php']
  const DG = SP.variants['sales.debtor_groups']
  const KG = SP.variants['purchase.creditor_groups']
  const U = 'https://x/asr_hj/systemdata/settingsSection.php?s=sales.debtor_groups'
  // rowsView.php:291-433: a table card with rows, row actions and the add button; #st-year above (:102-110)
  function groupsPage ({ rows = [], empty = false, seed = false, copy = false, err = false, ro = false } = {}) {
    const doc = new Doc()
    const page = new El('div', { class: 'sk st-page st-page-rows' })
    doc.append(page)
    page.append(new El('a', { class: 'sk-btn sk-back', href: 'settings.php', 'data-back': '', accesskey: 'l' }),
      new El('h1', {}, { text: 'Debitorgrupper' }), new El('select', { class: 'st-input', id: 'st-year' }))
    if (ro) page.append(new El('div', { class: 'st-flash st-flash-info', role: 'status' }))
    const form = new El('form', { class: 'st-form st-rows-form', id: 'st-form', method: 'post', action: 'settingsSection.php?s=sales.debtor_groups' })
    page.append(form)
    if (err) form.append(new El('div', { class: 'st-errors', role: 'alert' }).append(new El('ul').append(new El('li').append(new El('a', { href: '#c-groups-1-box2' })))))
    const card = new El('div', { class: 'st-card st-rows', 'data-table': 'groups', 'data-inactive': '1' })
    form.append(card)
    const tbody = new El('tbody')
    card.append(new El('table', { class: 'st-rt' }).append(tbody))
    for (const r of rows) {
      const tr = new El('tr', { 'data-row': String(r.id), 'data-code': String(r.id) })
      tr.append(new El('td').append(new El('input', { type: 'text', class: 'st-rin', id: 'c-groups-' + r.id + '-box2', name: 'r[groups][' + r.id + '][box2]', value: '6190' })),
        new El('td').append(new El('select', { class: 'st-rin', name: 'r[groups][' + r.id + '][box1]', value: 'S1' })),
        new El('td').append(new El('input', { type: 'text', class: 'st-rin', name: 'r[groups][' + r.id + '][box6]', value: '' })),
        new El('td').append(new El('input', { type: 'hidden', name: 'r[groups][' + r.id + '][box8]', value: '' }), new El('input', { type: 'checkbox', class: 'st-rcheck', name: 'r[groups][' + r.id + '][box8]', value: '1' })),
        new El('td').append(new El('select', { class: 'st-rin', name: 'r[groups][' + r.id + '][box10]', value: r.zone || '' })),
        new El('td', { class: 'st-ra' }).append(
          new El('button', { type: 'button', class: 'st-ricon st-ricon-txt', 'data-row-action': 'move_control' }),
          new El('button', { type: 'button', class: 'st-ricon', 'data-inactive': '1' }),
          new El('button', { type: 'button', class: 'st-ricon st-ricon-del', 'data-del': '' })))
      tbody.append(tr)
    }
    if (empty) {
      const box = new El('div', { class: 'st-rempty' })
      if (seed) box.append(new El('button', { type: 'button', class: 'st-btn st-btn-primary', 'data-seed': '[]' }), new El('button', { type: 'button', class: 'st-btn', 'data-add': '' }))
      card.append(box)
    }
    const foot = new El('div', { class: 'st-rfoot' })
    if (!ro) foot.append(new El('button', { type: 'button', class: 'st-btn', 'data-add': '' }))
    if (copy) foot.append(new El('button', { type: 'submit', class: 'st-btn st-btn-quiet', form: 'st-copy-groups' }))
    card.append(foot)
    if (!ro) form.append(new El('footer', { class: 'st-savebar', id: 'st-savebar', hidden: '' }).append(new El('button', { type: 'button', id: 'st-undo' }), new El('button', { type: 'submit', id: 'st-save' })))
    return doc
  }
  // one group (a new company's Danske Kunder): a group for customers abroad first
  const one = groupsPage({ rows: [{ id: 1 }] })
  const c1 = ctxFor(one, U)
  const s1 = DG.detect(c1)
  eq(s1.state, 'tabel')
  eq(s1.flags, { kopier: false, standard: false, flyt: true, enGruppe: true, udenEuZone: false, fejl: false, historik: false })
  eq(DG.order(c1, s1).slice(0, 3), ['DG2', 'DG1', 'DG7'])
  eq(DG.problem(c1, s1), '')
  eq(DG.about(c1, s1), ['help.sdebgrp.about.tabel', 'help.sdebgrp.about.aar'])
  assert.ok(DG.tasks.DG1.show.target && one.querySelector(DG.tasks.DG1.show.target) === one.querySelector('.st-rfoot button[data-add]'), 'DG1 rings Tilføj debitorgruppe')
  assert.ok(one.querySelector(DG.tasks.DG4.show.target) === one.querySelector('button[data-row-action="move_control"]'), 'DG4 rings Flyt samlekonto')
  assert.ok(one.querySelector(DG.tasks.DG3.show.target) === one.querySelector('select[name="r[groups][1][box10]"]'), 'DG3 rings EU-zone')
  assert.ok(one.querySelector(DG.tasks.DG7.show.target) === one.querySelector('input.st-rcheck[name="r[groups][1][box8]"]'), 'DG7 rings the B2B box, not its hidden twin')
  assert.ok(one.querySelector(DG.tasks.DG5.show.target) === one.querySelector('#st-year'), 'DG5 rings Gælder regnskabsåret')
  assert.equal(one.querySelector(DG.tasks.DG8.show.target), null, 'DG8 rings nothing in a table with rows')
  assert.equal(one.querySelector(DG.tasks.DG9.show.target), null, 'DG9 rings nothing in a table with rows')
  // two groups and no EU zone: the EU zone first
  const two = groupsPage({ rows: [{ id: 1 }, { id: 2 }] })
  const s2 = DG.detect(ctxFor(two, U))
  assert.ok(s2.flags.udenEuZone && !s2.flags.enGruppe)
  eq(DG.order(ctxFor(two, U), s2).slice(0, 3), ['DG3', 'DG1', 'DG7'])
  eq(DG.about(ctxFor(two, U), s2), ['help.sdebgrp.about.tabel', 'help.sdebgrp.about.uden_eu_zone'])
  const zoned = groupsPage({ rows: [{ id: 1 }, { id: 2, zone: 'B2B-EU' }] })
  assert.equal(DG.detect(ctxFor(zoned, U)).flags.udenEuZone, false)
  // an empty year with the standard set and a year to copy from
  const empty = groupsPage({ empty: true, seed: true, copy: true })
  const se = DG.detect(ctxFor(empty, U))
  eq(se.state, 'tom')
  assert.ok(se.flags.kopier && se.flags.standard)
  eq(DG.order(ctxFor(empty, U), se).slice(0, 3), ['DG9', 'DG8', 'DG1'])
  assert.ok(empty.querySelector(DG.tasks.DG8.show.target) === empty.querySelector('[data-seed]'))
  assert.ok(empty.querySelector(DG.tasks.DG9.show.target) === empty.querySelector('button[form="st-copy-groups"]'))
  // a refused save: the problem card
  const bad = groupsPage({ rows: [{ id: 1 }], err: true })
  const sb = DG.detect(ctxFor(bad, U))
  assert.ok(sb.flags.fejl)
  eq(DG.problem(ctxFor(bad, U), sb), 'FE')
  eq(DG.order(ctxFor(bad, U), sb)[0], 'FE')
  assert.ok(bad.querySelector(DG.tasks.FE.show.target) === bad.querySelector('.st-errors'))
  // read-only: no tasks, the access question
  const ro = groupsPage({ rows: [{ id: 1 }], ro: true })
  const sr = DG.detect(ctxFor(ro, U))
  eq(sr.state, 'kunLaes')
  eq(DG.order(ctxFor(ro, U), sr), [])
  eq(DG.questionOrder(ctxFor(ro, U), sr), ['QL'])
  eq(DG.about(ctxFor(ro, U), sr), ['help.sdebgrp.about.tabel', 'help.sdebgrp.about.kunlaes'])
  // field keys of the cells, the year and the Flyt samlekonto dialog
  eq(DG.fieldKey(new El('input', { name: 'r[groups][12][box2]' })), 'samlekonto')
  eq(DG.fieldKey(new El('select', { name: 'r[groups][n1][box10]' })), 'eu_zone')
  eq(DG.fieldKey(new El('input', { name: 'arg' })), 'ny_samlekonto')
  eq(DG.fieldKey(new El('select', { id: 'st-year' })), 'aar')
  eq(KG.fieldKey(new El('select', { name: 'r[groups][3][box6]' })), 's_moms')
  eq(DG.fieldKey(new El('select', { name: 'r[groups][3][box6]' })), 'rabat')
  for (const k of ['samlekonto', 'eu_zone', 'ny_samlekonto', 'aar']) assert.ok(DG.fields[k], 'sdebgrp field ' + k)
  assert.ok(KG.fields.s_moms)
  // the creditor groups read the same table
  const kd = KG.detect(ctxFor(one, 'https://x/asr_hj/systemdata/settingsSection.php?s=purchase.creditor_groups'))
  eq(kd.state, 'tabel')
  eq(KG.order(ctxFor(one, U), kd).slice(0, 3), ['KG2', 'KG1', 'KG7'])
})

test('S2 ordrer og fakturering: quick invoicing, immediate posting, the batch lock and the field keys', () => {
  const SP = C.pages['systemdata/settingsSection.php']
  const OF = SP.variants['sales.orders']
  const U = 'https://x/asr_hj/systemdata/settingsSection.php?s=sales.orders'
  // components.php:798-828: a field row with its switch
  function sw (key, on, locked) {
    const row = new El('div', { class: 'st-field st-type-bool', id: key, 'data-key': key })
    const tx = new El('div', { class: 'st-tx' })
    if (locked) tx.append(new El('span', { class: 'st-locked' }))
    row.append(tx, new El('div', { class: 'st-ctl' }).append(new El('button', { type: 'button', role: 'switch', class: 'st-switch', id: 'f-' + key.replace(/\./g, '-'), 'aria-checked': on ? 'true' : 'false' })))
    return row
  }
  function ordersPage ({ quick = true, post = true, locked = false, hist = false } = {}) {
    const doc = new Doc()
    const form = new El('form', { class: 'st-form', id: 'st-form', action: 'settingsSection.php?s=sales.orders' })
    doc.append(new El('h1', {}, { text: 'Ordrer & fakturering' }), form)
    form.append(sw('sales.orders.quick_invoice', quick, locked), sw('sales.orders.post_sales_immediately', post, false),
      new El('div', { class: 'st-field st-type-account', 'data-key': 'sales.orders.cash_account' }).append(new El('input', { type: 'text', id: 'f-sales-orders-cash_account', name: 'f[sales.orders.cash_account]' })),
      new El('footer', { class: 'st-savebar', id: 'st-savebar', hidden: '' }).append(new El('button', { type: 'submit', id: 'st-save' })))
    if (hist) doc.append(new El('aside', { class: 'st-hist' }).append(new El('button', { type: 'button', class: 'st-tl', 'data-restore': '4' })))
    return doc
  }
  // a new company: quick invoicing and immediate posting on
  const d1 = ordersPage()
  const s1 = OF.detect(ctxFor(d1, U))
  eq(s1, { state: 'formular', flags: { hurtig: true, udenStraks: false, hurtigLaast: false, fejl: false, historik: false }, data: {} })
  eq(OF.order(ctxFor(d1, U), s1).slice(0, 3), ['OF1', 'OF3', 'OF4'])
  eq(OF.about(ctxFor(d1, U), s1), ['help.sordre.about.formular', 'help.sordre.about.hurtig'])
  assert.ok(d1.querySelector(OF.tasks.OF1.show.target) === d1.querySelector('[data-key="sales.orders.quick_invoice"]'), 'OF1 rings the quick-invoicing row')
  assert.ok(d1.querySelector(OF.tasks.OF5.show.target) === d1.querySelector('[data-key="sales.orders.cash_account"]'), 'OF5 rings the cash account')
  // immediate posting off: that first, with its own paragraph and question
  const d2 = ordersPage({ post: false, hist: true })
  const s2 = OF.detect(ctxFor(d2, U))
  assert.ok(s2.flags.udenStraks && s2.flags.historik)
  eq(OF.order(ctxFor(d2, U), s2).slice(0, 2), ['OF3', 'OF1'])
  assert.ok(OF.order(ctxFor(d2, U), s2).includes('GD'), 'Gendan with a change to put back')
  eq(OF.about(ctxFor(d2, U), s2)[1], 'help.sordre.about.uden_straks')
  eq(OF.questionOrder(ctxFor(d2, U), s2)[0], 'Q2')
  // quick invoicing off and locked by batch control: no task to switch it on first
  const d3 = ordersPage({ quick: false, locked: true })
  const s3 = OF.detect(ctxFor(d3, U))
  assert.ok(s3.flags.hurtigLaast && !s3.flags.hurtig)
  const o3 = OF.order(ctxFor(d3, U), s3)
  eq(o3.slice(0, 2), ['OF3', 'OF4'])
  assert.ok(o3.indexOf('OF2') > 2, 'OF2 comes late while the lock is on')
  eq(OF.about(ctxFor(d3, U), s3)[1], 'help.sordre.about.hurtig_laast')
  // field keys: f[group.section.name(.n)] without the number
  eq(OF.fieldKey(new El('input', { name: 'f[sales.orders.cash_account]' })), 'cash_account')
  const RY = SP.variants['sales.reminders']
  eq(RY.fieldKey(new El('input', { name: 'f[sales.reminders.fee_item_2.0]' })), 'fee_item')
  eq(RY.fieldKey(new El('input', { name: 'f[sales.reminders.interest_rate_1.3]' })), 'interest_rate')
  assert.ok(RY.fields.fee_item && RY.fields.interest_rate)
  assert.equal(RY.tasks.RY4.show, undefined, 'RY4 has no Vis mig: the reminders are made on another page')
})

test('S2 prislister, rabatter, købsordrer and kommissionsvarer: list drawers, row actions and switches', () => {
  const SP = C.pages['systemdata/settingsSection.php']
  const PL = SP.variants['purchase.pricelists']
  const U = 'https://x/asr_hj/systemdata/settingsSection.php?s=purchase.pricelists'
  // listView.php:291-450: the head's add button, rows with Indstil, one drawer per list
  function listPage ({ lists = [], open = '' } = {}) {
    const doc = new Doc()
    doc.append(new El('section', { class: 'st-phead' }).append(new El('h1', {}, { text: 'Leverandørprislister' }),
      new El('form', { method: 'post' }).append(new El('button', { type: 'submit', class: 'st-btn st-btn-primary' }))))
    const list = new El('div', { class: 'st-list', id: 'st-list' })
    doc.append(list)
    const card = new El('div', { class: 'st-card' })
    list.append(card)
    if (!lists.length) card.append(new El('p', { class: 'st-h-empty st-list-empty' }))
    for (const l of lists) {
      card.append(new El('div', { class: 'st-irow st-irow-' + (l.used ? 'ok' : 'off'), 'data-item': 'pl_' + l.id }).append(new El('button', { type: 'button', class: 'st-btn', 'data-open': 'pl_' + l.id })))
      const attrs = { class: 'st-drawer', id: 'dw-pl_' + l.id, 'data-item': 'pl_' + l.id }
      if (open !== 'pl_' + l.id) attrs.hidden = ''
      doc.append(new El('aside', attrs).append(new El('form', { class: 'st-dform' }).append(
        new El('button', { type: 'button', class: 'st-btn', 'data-run': 'purchase.pricelists.test.' + l.id }),
        new El('button', { type: 'button', class: 'st-btn', 'data-run': 'purchase.pricelists.use.' + l.id }),
        new El('button', { type: 'submit', class: 'st-btn st-btn-primary', 'data-save': '' }))))
    }
    return doc
  }
  const none = listPage()
  const sn = PL.detect(ctxFor(none, U))
  eq(sn.state, 'tom')
  eq(PL.order(ctxFor(none, U), sn), ['PL1'])
  assert.ok(none.querySelector(PL.tasks.PL1.show.target) === none.querySelector('.st-phead button[type="submit"]'), 'PL1 rings Tilføj prisliste')
  const closed = listPage({ lists: [{ id: 3, used: true }, { id: 4 }] })
  const sc = PL.detect(ctxFor(closed, U))
  eq(sc.state, 'liste')
  assert.ok(sc.flags.iBrug && !sc.flags.aaben)
  eq(PL.order(ctxFor(closed, U), sc).slice(0, 3), ['PL2', 'PL1', 'PL3'])
  assert.ok(PL.tasks.PL2.show.target(ctxFor(closed, U), sc) === closed.querySelector('.st-irow button[data-open]'), 'closed: PL2 rings Indstil, the first step')
  const opened = listPage({ lists: [{ id: 3 }, { id: 4 }], open: 'pl_4' })
  const so = PL.detect(ctxFor(opened, U))
  assert.ok(so.flags.aaben && !so.flags.iBrug)
  assert.ok(PL.tasks.PL2.show.target(ctxFor(opened, U), so) === opened.querySelector('button[data-run="purchase.pricelists.test.4"]'), 'open: PL2 rings Test prisfilen of the open list')
  assert.ok(PL.tasks.PL3.show.target(ctxFor(opened, U), so) === opened.querySelector('button[data-run="purchase.pricelists.use.4"]'), 'open: PL3 rings Brug denne prisliste')
  assert.ok(PL.tasks.GM.show.target(ctxFor(opened, U), so) === opened.querySelector('#dw-pl_4 button[data-save]'), 'open: GM rings its Gem ændringer')
  eq(PL.fieldKey(new El('input', { name: 'f[purchase.pricelists.url.4]' })), 'url')
  // Rabatter & prisgrupper: old discounts to transfer come first
  const RB = SP.variants['sales.discounts']
  const rd = new Doc()
  rd.append(new El('form', { id: 'st-form' }).append(
    new El('div', { class: 'st-rows', 'data-table': 'prices' }).append(new El('div', { class: 'st-rfoot' }).append(new El('button', { type: 'button', 'data-add': '' }))),
    new El('table', { class: 'st-matrix', id: 'f-sales-discounts-matrix', 'data-matrix': '' }),
    new El('div', { class: 'st-rows', 'data-table': 'old_discounts' }).append(new El('button', { type: 'button', 'data-row-action': 'discount_transfer' }))))
  const rc = ctxFor(rd, 'https://x/asr_hj/systemdata/settingsSection.php?s=sales.discounts')
  const rs = RB.detect(rc)
  eq(rs.state, 'tabel')
  assert.ok(rs.flags.gamle)
  eq(RB.order(rc, rs).slice(0, 2), ['RB6', 'RB1'])
  assert.ok(rd.querySelector(RB.tasks.RB1.show.target) === rd.querySelector('table[data-matrix]'))
  assert.ok(rd.querySelector(RB.tasks.RB4.show.target) === rd.querySelector('[data-table="prices"] button[data-add]'))
  eq(RB.fieldKey(new El('input', { class: 'st-input st-mcell', 'data-col': '2' })), 'matrix')
  eq(RB.fieldKey(new El('input', { name: 'r[quantity][n2][box3]' })), 'v_antal')
  eq(RB.fieldKey(new El('input', { name: 'r[debtor_groups][5][kodenr]' })), 'nr')
  // Købsordrer and Kommissionsvarer read their switches
  const KO = SP.variants['purchase.orders']
  const kd = new Doc()
  kd.append(new El('form', { id: 'st-form' }).append(new El('div', { class: 'st-field', 'data-key': 'purchase.orders.post_immediately' }).append(new El('button', { type: 'button', class: 'st-switch', 'aria-checked': 'false' }))))
  const ks = KO.detect(ctxFor(kd, 'https://x/asr_hj/systemdata/settingsSection.php?s=purchase.orders'))
  assert.ok(ks.flags.udenStraks)
  eq(KO.about(ctxFor(kd, 'https://x/'), ks), ['help.skoeb.about.formular', 'help.skoeb.about.uden_straks'])
  const KM = SP.variants['items.consignment']
  const md = new Doc()
  md.append(new El('form', { id: 'st-form' }).append(new El('div', { class: 'st-field', 'data-key': 'items.consignment.enabled' }).append(new El('button', { type: 'button', class: 'st-switch', 'aria-checked': 'true' }))))
  const ms = KM.detect(ctxFor(md, 'https://x/asr_hj/systemdata/settingsSection.php?s=items.consignment'))
  assert.ok(ms.flags.aktiv)
  eq(KM.order(ctxFor(md, 'https://x/'), ms)[0], 'KM2')
})

test('S3 settings sections: the Varer & lager, Integrationer and Kasse sections are registered with their own groups', () => {
  const SP = C.pages['systemdata/settingsSection.php']
  const want = {
    'items.stock': 'slager', 'items.warehouses': 'slagre', 'items.units': 'senhed', 'items.variants': 'svariant',
    'items.item_groups': 'svaregrp', 'items.packaging': 'semballage', 'integrations.connections': 'sintegr', 'integrations.pickup': 'safhent',
    'pos.tills': 'skasse', 'pos.cash': 'skasseopt', 'pos.cards': 'skort', 'pos.receipt': 'sbon', 'pos.screen': 'sskaerm',
    'pos.tables': 'sborde', 'pos.kitchen': 'skoekken'
  }
  for (const [key, group] of Object.entries(want)) {
    assert.ok(SP.variants[key], key + ' has a page')
    assert.equal(SP.variants[key].group, group, key)
  }
  eq(SP.errors, [])
  eq(SP.variant(ctxFor(new Doc(), 'https://x/asr_hj/systemdata/settingsSection.php?s=items.item_groups&year=3')), 'items.item_groups')
  for (const route of ['systemdata/posmenuer.php', 'systemdata/importer_posmenu.php', 'systemdata/exporter_posmenu.php', 'systemdata/importer_varianter.php', 'systemdata/barcodescan.php']) {
    assert.ok(C.pages[route], route)
  }
  assert.equal(C.pages['systemdata/importer_varianter.php'].group, 'svariant', 'the import page shares the Varianter group')
})

/** A rows section (includes/settings/rowsView.php): a table card per table id with saved rows, the add footer, an empty card. */
function s3RowsPage ({ table = 'groups', rows = [], empty = false, seed = false, copy = false, errors = false, readOnly = false, year = true } = {}) {
  const doc = new Doc()
  const body = new El('body')
  doc.append(body)
  body.append(new El('a', { class: 'sk-btn sk-btn-sm sk-back', href: '../systemdata/settings.php', 'data-back': '', accesskey: 'l' }), new El('h1', {}, { text: 'Varegrupper' }))
  if (year) body.append(new El('select', { class: 'st-input st-input-short', id: 'st-year' }))
  if (readOnly) body.append(new El('div', { class: 'st-flash st-flash-info', role: 'status' }))
  if (errors) body.append(new El('div', { class: 'st-flash st-flash-err', role: 'alert' }))
  const form = new El('form', { class: 'st-form st-rows-form', id: 'st-form', method: 'post', action: 'settingsSection.php?s=items.item_groups' })
  body.append(form)
  if (errors) form.append(new El('div', { class: 'st-errors', role: 'alert' }))
  const card = new El('div', { class: 'st-card st-rows', 'data-table': table, 'data-inactive': '0' })
  form.append(card)
  const tbody = new El('tbody')
  card.append(new El('table', { class: 'st-rt' }).append(tbody))
  for (const id of rows) {
    const tr = new El('tr', { 'data-row': String(id), 'data-code': String(id) })
    for (const col of ['kodenr', 'beskrivelse', 'box1', 'box2', 'box3', 'box4', 'box7', 'box8', 'box12']) {
      const box = ['box7', 'box8'].includes(col)
      const td = new El('td')
      // a checkbox cell prints its hidden twin first, with the same name (live check 24.15)
      if (box) td.append(new El('input', { type: 'hidden', name: 'r[' + table + '][' + id + '][' + col + ']', value: '' }))
      tr.append(td.append(new El('input', { type: box ? 'checkbox' : 'text', class: 'st-rin', id: 'c-' + table + '-' + id + '-' + col, name: 'r[' + table + '][' + id + '][' + col + ']' })))
    }
    tr.append(new El('td', { class: 'st-ra' }).append(new El('button', { type: 'button', class: 'st-ricon st-ricon-del', 'data-del': '' })))
    tbody.append(tr)
  }
  if (empty) {
    const e = new El('div', { class: 'st-rempty' })
    if (seed) e.append(new El('button', { type: 'button', class: 'st-btn st-btn-primary', 'data-seed': '[]' }), new El('button', { type: 'button', class: 'st-btn', 'data-add': '' }))
    card.append(e)
  }
  if (!readOnly) {
    const foot = new El('div', { class: 'st-rfoot' }).append(new El('button', { type: 'button', class: 'st-btn', 'data-add': '' }))
    if (copy) foot.append(new El('button', { type: 'submit', class: 'st-btn st-btn-quiet', form: 'st-copy-' + table }))
    card.append(foot)
    form.append(new El('footer', { class: 'st-savebar', id: 'st-savebar', hidden: '' }).append(new El('button', { type: 'button', id: 'st-undo' }), new El('button', { type: 'submit', id: 'st-save' })))
  }
  return doc
}

test('S3 varegrupper: detect(), the problem card and the targets follow the table', () => {
  const P = C.pages['systemdata/settingsSection.php'].variants['items.item_groups']
  const U = 'https://x/asr_hj/systemdata/settingsSection.php?s=items.item_groups'
  const list = s3RowsPage({ rows: [1, 2] })
  const lc = ctxFor(list, U)
  const ls = P.detect(lc)
  eq([ls.state, ls.flags, ls.data.grupper], ['liste', { fejl: false, standard: false, kopiAar: false }, 2])
  eq(P.order(lc, ls), ['VG1', 'VG4', 'VG6', 'VG5', 'VG7', 'VG8', 'VG9'])
  assert.equal(P.problem(lc, ls), '')
  eq(P.about(lc, ls), ['help.svaregrp.about.liste'])
  // Vis mig rings the cell or button of its task, in the first saved row
  assert.ok(P.tasks.VG4.show.target(lc, ls) === list.querySelector('[name="r[groups][1][box4]"]'), 'VG4 rings Varesalg')
  assert.ok(P.tasks.VG5.show.target(lc, ls) === list.querySelector('[name="r[groups][1][box8]"][type="checkbox"]'), 'VG5 rings the Lagerført box, not its hidden twin')
  assert.ok(P.tasks.VG6.show.target(lc, ls) === list.querySelector('[name="r[groups][1][box7]"][type="checkbox"]'), 'VG6 rings the Momsfri box, not its hidden twin')
  assert.ok(P.tasks.VG7.show.target(lc, ls) === list.querySelector('[name="r[groups][1][box12]"]'), 'VG7 rings Salgskonto til EU')
  assert.ok(list.querySelector(P.tasks.VG1.show.target) !== null, 'VG1 rings Tilføj varegruppe')
  assert.ok(list.querySelector(P.tasks.VG8.show.target) !== null, 'VG8 rings a trash button')
  assert.ok(list.querySelector(P.tasks.VG9.show.target) !== null, 'VG9 rings the year select')
  for (const id of ['VG2', 'VG3', 'VG10']) assert.equal(list.querySelector(P.tasks[id].show.target), null, id + ' rings nothing in a saved list')
  // an empty year after an earlier one: copy first, as the problem card
  const copy = s3RowsPage({ rows: [], empty: true, copy: true })
  const cc = ctxFor(copy, U + '&year=4')
  const cs = P.detect(cc)
  eq([cs.state, cs.flags.kopiAar, cs.flags.standard], ['tom', true, false])
  assert.equal(P.problem(cc, cs), 'VG3')
  eq(P.order(cc, cs), ['VG3', 'VG1', 'VG9'])
  assert.ok(copy.querySelector(P.tasks.VG3.show.target) !== null)
  // an empty table with the Danish standard set on offer
  const seed = s3RowsPage({ rows: [], empty: true, seed: true })
  const sc = ctxFor(seed, U)
  const ss = P.detect(sc)
  eq([ss.state, ss.flags.standard], ['tom', true])
  assert.equal(P.problem(sc, ss), 'VG2')
  assert.ok(seed.querySelector(P.tasks.VG2.show.target) !== null)
  eq(P.about(sc, ss), ['help.svaregrp.about.tom'])
  // a refused save: the error list first
  const err = s3RowsPage({ rows: [1], errors: true })
  const ec = ctxFor(err, U)
  const es = P.detect(ec)
  assert.equal(es.flags.fejl, true)
  assert.equal(P.problem(ec, es), 'VG10')
  eq(P.order(ec, es).slice(0, 2), ['VG10', 'VG1'])
  eq(P.about(ec, es), ['help.svaregrp.about.fejl'])
  assert.ok(err.querySelector(P.tasks.VG10.show.target) !== null)
  // read only: no add, no problem
  const ro = s3RowsPage({ rows: [1], readOnly: true })
  const rc = ctxFor(ro, U)
  const rs = P.detect(rc)
  eq([rs.state, P.order(rc, rs), P.problem(rc, rs)], ['kunLaes', ['VG9'], ''])
  // field keys: the cells by column, the year select by id
  eq(['box3', 'box4', 'box8', 'box9', 'kodenr'].map((col) => P.fieldKey(new El('input', { name: 'r[groups][n1][' + col + ']' }))), ['varekoeb', 'varesalg', 'lagerfoert', 'batch', 'nr'])
  assert.equal(P.fieldKey(new El('select', { id: 'st-year' })), 'regnskabsaar')
  assert.equal(P.fieldKey(new El('input', { name: 'r[groups][3][box10]' })), null)
  assert.equal(P.fieldKey(null), null)
})

test('S3 lagerstyring: the cost update only with a method that uses it, and the form field keys', () => {
  const P = C.pages['systemdata/settingsSection.php'].variants['items.stock']
  const U = 'https://x/asr_hj/systemdata/settingsSection.php?s=items.stock'
  const page = (hidden, errors, fifo = false) => {
    const doc = new Doc()
    const form = new El('form', { class: 'st-form', id: 'st-form', action: 'settingsSection.php?s=items.stock' })
    doc.append(new El('body').append(form))
    if (errors) form.append(new El('ul', { class: 'st-errorlist' }))
    form.append(new El('button', { type: 'button', role: 'switch', class: 'st-switch', id: 'f-items-stock-fifo', 'aria-checked': fifo ? 'true' : 'false' }))
    form.append(new El('button', { type: 'button', role: 'switch', class: 'st-switch', id: 'f-items-stock-low_stock_warning', 'aria-checked': 'false' }),
      new El('select', { class: 'st-input st-select', id: 'f-items-stock-cost_method', name: 'f[items.stock.cost_method]' }),
      new El('input', { class: 'st-input', type: 'email', id: 'f-items-stock-status_mail', name: 'f[items.stock.status_mail]' }))
    const action = new El('div', hidden ? { class: 'st-action', 'data-key': 'items.stock.update_cost_prices', hidden: '' } : { class: 'st-action', 'data-key': 'items.stock.update_cost_prices' })
    action.append(new El('button', { type: 'button', class: 'st-btn', 'data-run': 'items.stock.update_cost_prices' }))
    form.append(action)
    return doc
  }
  const off = page(true, false)
  const oc = ctxFor(off, U)
  const os = P.detect(oc)
  eq([os.state, os.flags], ['formular', { fejl: false, kostmetode: false, fifo: false }])
  eq(P.order(oc, os), ['LS2', 'LS1', 'LS6', 'LS4', 'LS5', 'LS7'])
  eq(P.about(oc, os), ['help.slager.about.formular'])
  // FIFO already on (live check 24.15): LS6 last, and the about says what FIFO does on the item card
  const fc = ctxFor(page(true, false, true), U)
  const fs = P.detect(fc)
  assert.equal(fs.flags.fifo, true)
  eq(P.order(fc, fs), ['LS2', 'LS1', 'LS4', 'LS5', 'LS7', 'LS6'])
  eq(P.about(fc, fs), ['help.slager.about.formular', 'help.slager.about.fifo'])
  assert.equal(P.tasks.LS6.steps.length, 3)
  const on = page(false, false)
  const nc = ctxFor(on, U)
  const ns = P.detect(nc)
  assert.equal(ns.flags.kostmetode, true)
  eq(P.order(nc, ns).slice(0, 3), ['LS2', 'LS1', 'LS3'])
  assert.ok(on.querySelector(P.tasks.LS3.show.target) !== null)
  assert.ok(on.querySelector(P.tasks.LS1.show.target) !== null)
  assert.ok(on.querySelector(P.tasks.LS2.show.target) !== null)
  const bad = page(true, true)
  const bc = ctxFor(bad, U)
  const bs = P.detect(bc)
  eq([P.problem(bc, bs), P.order(bc, bs)[0]], ['LS8', 'LS8'])
  // field keys: by name, and by id for a field without a name (read only or locked, components.php:796)
  assert.equal(P.fieldKey(new El('select', { name: 'f[items.stock.cost_method]', id: 'f-items-stock-cost_method' })), 'kostmetode')
  assert.equal(P.fieldKey(new El('input', { id: 'f-items-stock-status_threshold' })), 'graense')
  assert.equal(P.fieldKey(new El('input', { name: 'f[items.stock.fifo]' })), null)
})

/** A list section (includes/settings/listView.php): rows with a status and a button, drawers, flashes. */
function s3ListPage (items, { readOnly = false, payments = false, key = false, drawerErrors = '' } = {}) {
  const doc = new Doc()
  const body = new El('body')
  doc.append(body)
  body.append(new El('h1', {}, { text: 'Integrationer' }))
  if (readOnly) body.append(new El('div', { class: 'st-flash st-flash-info', role: 'status' }))
  if (key) body.append(new El('div', { class: 'st-flash st-flash-ok st-flash-key', role: 'status' }).append(new El('code', {}, { text: 'abc' })))
  const list = new El('div', { class: 'st-list', id: 'st-list' })
  body.append(list)
  if (payments) list.append(new El('section', { class: 'st-sect', id: 'sub-payments' }))
  for (const [id, kind] of items) {
    list.append(new El('div', { class: 'st-irow st-irow-' + kind, 'data-item': id }).append(new El('button', { type: 'button', class: 'st-btn', 'data-open': id })))
  }
  for (const [id] of items) {
    const drawer = new El('aside', id === drawerErrors ? { class: 'st-drawer', 'data-item': id } : { class: 'st-drawer', 'data-item': id, hidden: '' })
    const form = new El('form', { class: 'st-dform' })
    if (id === drawerErrors) form.append(new El('ul', { class: 'st-errorlist' }))
    drawer.append(form)
    body.append(drawer)
  }
  return doc
}

test('S3 integrationer: the rows, the MobilePay webhook as the problem card, the new key and the targets', () => {
  const P = C.pages['systemdata/settingsSection.php'].variants['integrations.connections']
  const U = 'https://x/asr_hj/systemdata/settingsSection.php?s=integrations.connections'
  const rows = [['rest_api', 'off'], ['webshop', 'ok'], ['gls', 'off'], ['dfm', 'off'], ['easyubl', 'off'], ['app', 'off']]
  const plain = s3ListPage(rows)
  const pc = ctxFor(plain, U)
  const ps = P.detect(pc)
  eq([ps.state, ps.flags], ['liste', { nyNoegle: false, webhookFejl: false, fejl: false, kasse: false, kasseIBrug: false, aktiv: true }])
  eq(P.order(pc, ps), ['IN4', 'IN1', 'IN5', 'IN6', 'IN2', 'IN7'])
  eq(P.about(pc, ps), ['help.sintegr.about.liste'])
  assert.equal(P.problem(pc, ps), '')
  for (const [id, item] of [['IN1', 'webshop'], ['IN2', 'rest_api'], ['IN4', 'easyubl'], ['IN5', 'gls'], ['IN6', 'dfm'], ['IN7', 'app']]) {
    const el = plain.querySelector(P.tasks[id].show.target)
    assert.ok(el !== null && el.getAttribute('data-open') === item, id + ' rings the button of ' + item)
  }
  assert.equal(plain.querySelector(P.tasks.IN8.show.target), null, 'no MobilePay row without the POS module')
  assert.equal(P.tasks.IN11.show.target(pc, ps), null)
  // with the POS module: the payment providers first; MobilePay without its webhook is the problem
  const pos = s3ListPage([...rows, ['mobilepay', 'err'], ['flatpay', 'off'], ['vibrant', 'off']], { payments: true })
  const oc = ctxFor(pos, U)
  const os = P.detect(oc)
  eq([os.flags.kasse, os.flags.webhookFejl], [true, true])
  assert.equal(P.problem(oc, os), 'IN9')
  eq(P.order(oc, os).slice(0, 3), ['IN9', 'IN8', 'IN11'])
  // the till module present but no payment provider set up (any install without a pos_licence row): e-invoices and the webshop first
  const idle = s3ListPage([...rows, ['mobilepay', 'off'], ['flatpay', 'off'], ['vibrant', 'off']], { payments: true })
  const ic = ctxFor(idle, U)
  const is = P.detect(ic)
  eq([is.flags.kasse, is.flags.kasseIBrug], [true, false])
  eq(P.order(ic, is).slice(0, 4), ['IN4', 'IN1', 'IN8', 'IN11'])
  eq(P.questionOrder(ic, is), ['Q3', 'Q2'])
  eq(P.about(oc, os), ['help.sintegr.about.webhook'])
  assert.ok(P.tasks.IN11.show.target(oc, os).getAttribute('data-open') === 'flatpay')
  eq(P.questionOrder(oc, os), ['Q1', 'Q3', 'Q2'])
  // ?webhook=fail after the setup tab: the problem, not a refused save
  const fc = ctxFor(s3ListPage([...rows, ['mobilepay', 'ok']], { payments: true }), U + '&webhook=fail')
  const fs = P.detect(fc)
  eq([fs.flags.webhookFejl, fs.flags.fejl], [true, false])
  // a new API key shown once comes first
  const kc = ctxFor(s3ListPage(rows, { key: true }), U + '&newkey=1')
  const ks = P.detect(kc)
  eq(P.order(kc, ks).slice(0, 2), ['IN3', 'IN4'])
  // a refused save in a drawer: the problem card rings that drawer's error list
  const ed = s3ListPage(rows, { drawerErrors: 'webshop' })
  const ec = ctxFor(ed, U + '&item=webshop')
  const es = P.detect(ec)
  assert.equal(P.problem(ec, es), 'IN10')
  assert.ok(P.tasks.IN10.show.target(ec, es) === ed.querySelector('aside[data-item="webshop"] .st-errorlist'))
  // nothing set up yet, and read only
  const nc = ctxFor(s3ListPage(rows.map(([id]) => [id, 'off'])), U)
  eq(P.about(nc, P.detect(nc)), ['help.sintegr.about.ny'])
  const rc = ctxFor(s3ListPage(rows, { readOnly: true }), U)
  const rs = P.detect(rc)
  eq([rs.state, P.order(rc, rs)], ['kunLaes', []])
  // field keys of the drawers' fields
  assert.equal(P.fieldKey(new El('input', { name: 'f[integrations.webshop.url]', id: 'f-integrations-webshop-url' })), 'shop_url')
  assert.equal(P.fieldKey(new El('input', { type: 'password', id: 'f-integrations-easyubl-api_key', name: 'f[integrations.easyubl.api_key]' })), 'easyubl_noegle')
  assert.equal(P.fieldKey(new El('input', { name: 'f[integrations.gls.user]' })), null)
})

test('S3 kasse and afhentning: list sections with an add button in the head, and the form sections', () => {
  const SP = C.pages['systemdata/settingsSection.php']
  const head = (key) => new El('form', { method: 'post', action: 'settingsSection.php?s=x' }).append(
    new El('input', { type: 'hidden', name: 'action', value: 'run' }), new El('input', { type: 'hidden', name: 'key', value: key }),
    new El('button', { type: 'submit', class: 'st-btn st-btn-primary' }))
  // Kasser: no till yet, then two tills
  const T = SP.variants['pos.tills']
  const empty = new Doc()
  empty.append(new El('body').append(head('pos.tills.add'), new El('div', { class: 'st-list', id: 'st-list' }).append(new El('p', { class: 'st-h-empty st-list-empty' }))))
  const ec = ctxFor(empty, 'https://x/asr_hj/systemdata/settingsSection.php?s=pos.tills')
  const es = T.detect(ec)
  eq([es.state, T.order(ec, es)], ['tom', ['KA1']])
  assert.ok(T.tasks.KA1.show.target(ec, es) === empty.querySelector('button[type="submit"]'), 'KA1 rings Tilføj kasse')
  const tills = s3ListPage([['till_1', 'plain'], ['till_2', 'plain']])
  tills.querySelector('body').append(head('pos.tills.add'))
  const tc = ctxFor(tills, 'https://x/asr_hj/systemdata/settingsSection.php?s=pos.tills')
  const ts = T.detect(tc)
  eq([ts.state, T.order(tc, ts)], ['liste', ['KA2', 'KA1']])
  assert.equal(tills.querySelector(T.tasks.KA2.show.target).getAttribute('data-open'), 'till_1')
  // Betalingskort: the shared row alone is not a list of cards
  const K = SP.variants['pos.cards']
  const kc = ctxFor(s3ListPage([['general', 'plain']]), 'https://x/asr_hj/systemdata/settingsSection.php?s=pos.cards')
  eq(K.detect(kc).state, 'tom')
  // Afhentningsadresser: the add button of the head form, read only without it
  const A = SP.variants['integrations.pickup']
  const pick = s3ListPage([['pickup_1', 'plain']])
  pick.querySelector('body').append(head('integrations.pickup.add'))
  const ac = ctxFor(pick, 'https://x/asr_hj/systemdata/settingsSection.php?s=integrations.pickup')
  const as = A.detect(ac)
  eq([as.state, A.order(ac, as)], ['liste', ['AF1', 'AF2', 'AF3']])
  assert.ok(A.tasks.AF1.show.target(ac, as) !== null)
  assert.equal(A.tasks.AF1.show.target(kc, as), null, 'no add button of another section')
  // a form section of Kasse: the form and read only
  const S = SP.variants['pos.screen']
  const form = new Doc()
  form.append(new El('body').append(new El('nav', { class: 'st-tabs st-menu' }).append(new El('a', { class: 'st-menu-row st-menu-l2', href: 'posmenuer.php' })),
    new El('form', { id: 'st-form', action: 'settingsSection.php?s=pos.screen' }).append(new El('button', { type: 'button', role: 'switch', id: 'f-pos-screen-cash_button' }))))
  const fc = ctxFor(form, 'https://x/asr_hj/systemdata/settingsSection.php?s=pos.screen')
  const fs = S.detect(fc)
  eq([fs.state, S.order(fc, fs)], ['formular', ['SK1', 'SK2']])
  assert.ok(form.querySelector(S.tasks.SK2.show.target) !== null, 'SK2 rings POS-menuer in the left menu')
  // Emballage: the switch's state picks the order
  const E = SP.variants['items.packaging']
  const pk = new Doc()
  pk.append(new El('form', { id: 'st-form' }).append(new El('button', { type: 'button', role: 'switch', id: 'f-items-packaging-enabled', 'aria-checked': 'true' })))
  const pc = ctxFor(pk, 'https://x/asr_hj/systemdata/settingsSection.php?s=items.packaging')
  eq(E.order(pc, E.detect(pc)), ['EM2', 'EM1'])
})

test('S3 POS-menuer, the variant import and the app QR page: detect() and the targets', () => {
  const PM = C.pages['systemdata/posmenuer.php']
  const U = 'https://x/asr_hj/systemdata/posmenuer.php'
  const choose = new Doc()
  choose.append(new El('form', { name: 'posmenuer' }).append(new El('select', { name: 'menuvalg' }), new El('input', { type: 'submit', name: 'OK', value: 'OK' })),
    new El('form', { name: 'export_posmenu', action: 'exporter_posmenu.php' }).append(new El('input', { type: 'submit', value: 'Eksportér POS-menuer' })))
  const cc = ctxFor(choose, U)
  eq([PM.detect(cc).state, PM.order(cc, PM.detect(cc))], ['vaelg', ['PM1', 'PM2', 'PM5']])
  assert.ok(choose.querySelector(PM.tasks.PM5.show.target) !== null)
  const menu = new Doc()
  menu.append(new El('form', { name: 'posmenuer' }).append(new El('select', { name: 'menuvalg' }), new El('select', { name: 'menutype' })),
    new El('a', { href: 'posmenuer.php?menu_id=3&ret_row=1&ret_col=1' }))
  const mc = ctxFor(menu, U)
  eq(PM.detect(mc).state, 'menu')
  assert.ok(menu.querySelector(PM.tasks.PM3.show.target) !== null)
  const button = new Doc()
  button.append(new El('form', { name: 'posmenuer' }).append(new El('input', { type: 'submit', name: 'OK', value: 'OK' }), new El('input', { type: 'button', name: 'SletKnap' })))
  const bc = ctxFor(button, U + '?menu_id=3&ret_row=1&ret_col=1')
  eq([PM.detect(bc).state, PM.order(bc, PM.detect(bc))], ['knap', ['PM4']])
  assert.ok(PM.tasks.PM4.show.target(bc, PM.detect(bc)) === button.querySelector('input[name="OK"]'))
  assert.equal(PM.tasks.PM4.show.target(cc, PM.detect(cc)), null, 'PM4 rings nothing beside Menu (ID)')
  // Importér varianter: the two upload forms, or only the no-access note
  const VI = C.pages['systemdata/importer_varianter.php']
  const up = new Doc()
  up.append(new El('body').append(new El('form').append(new El('input', { type: 'file', name: 'variant_types_file' })), new El('form').append(new El('input', { type: 'file', name: 'variant_values_file' }))))
  const uc = ctxFor(up, 'https://x/asr_hj/systemdata/importer_varianter.php')
  eq([VI.detect(uc).state, VI.order(uc, VI.detect(uc))], ['upload', ['VI1', 'VI2']])
  assert.ok(up.querySelector(VI.tasks.VI2.show.target) !== null)
  const none = new Doc()
  none.append(new El('body').append(new El('p')))
  eq(VI.detect(ctxFor(none, 'https://x/asr_hj/systemdata/importer_varianter.php')).state, 'ingenAdgang')
  // Importér POS-menuer and the app QR code
  const PI = C.pages['systemdata/importer_posmenu.php']
  const pi = new Doc()
  pi.append(new El('form').append(new El('input', { type: 'file', name: 'uploadedfile' })))
  eq(PI.detect(ctxFor(pi, 'https://x/asr_hj/systemdata/importer_posmenu.php')).state, 'upload')
  const QR = C.pages['systemdata/barcodescan.php']
  const qr = new Doc()
  qr.append(new El('img', { id: 'qrcode-img', alt: 'QR Code' }))
  const qc = ctxFor(qr, 'https://x/asr_hj/systemdata/barcodescan.php')
  eq([QR.detect(qc).state, QR.order(qc, QR.detect(qc))], ['kode', ['QR1']])
})

test('S3 mærkater: the visual editor, raw HTML after a refused save, and the targets', () => {
  const P = C.pages['systemdata/labels.php']
  const U = 'https://x/asr_hj/systemdata/labels.php?l=Hylde'
  const visual = new Doc()
  visual.append(new El('body').append(new El('button', { type: 'button', class: 'st-btn lb-new', 'data-open': 'lb-new' }),
    new El('form', { class: 'st-card', id: 'lb-form' }).append(new El('input', { type: 'hidden', name: 'action', value: 'save_visual' }),
      new El('select', { class: 'st-input st-select', name: 'labelType' }),
      new El('div', { class: 'lb-foot' }).append(new El('a', { class: 'st-tl', href: 'labels.php?l=Hylde&mode=raw' }), new El('button', { type: 'button', 'data-open': 'lb-del' })))))
  const vc = ctxFor(visual, U)
  const vs = P.detect(vc)
  eq([vs.state, P.order(vc, vs), P.problem(vc, vs)], ['visuel', ['LB2', 'LB1', 'LB3', 'LB4'], ''])
  for (const id of ['LB1', 'LB2', 'LB4']) assert.ok(visual.querySelector(P.tasks[id].show.target) !== null, id)
  assert.ok(P.tasks.LB3.show.target(vc, vs) === visual.querySelector('a.st-tl'), 'LB3 rings the Rå HTML link in the visual editor')
  const raw = new Doc()
  raw.append(new El('body').append(new El('div', { class: 'st-flash st-flash-err', role: 'alert' }),
    new El('form', { class: 'st-card', id: 'lb-form' }).append(new El('textarea', { name: 'rawHTML' }))))
  const rc = ctxFor(raw, U + '&mode=raw&msg=refused')
  const rs = P.detect(rc)
  eq([rs.state, rs.flags.afvist, P.problem(rc, rs), P.order(rc, rs)], ['raa', true, 'LB5', ['LB5', 'LB3', 'LB1', 'LB4']])
  assert.ok(P.tasks.LB3.show.target(rc, rs) === raw.querySelector('textarea'), 'LB3 rings the HTML in raw mode')
  // a new label's name was taken or not valid: the new-label box first, not the reload
  const nc = ctxFor(visual, 'https://x/asr_hj/systemdata/labels.php?l=Standard&msg=exists&new=1&n=Hylde')
  const ns = P.detect(nc)
  eq([ns.flags.afvist, ns.flags.navnFejl, P.problem(nc, ns), P.order(nc, ns)], [false, true, 'LB1', ['LB1', 'LB2', 'LB3', 'LB4']])
})

test('S1 brugere og roller: the list, the card, a new login, the roles editor on master and the roles branch, the log and the refusal', () => {
  const P = C.pages['systemdata/usersRoles.php']
  assert.ok(P, 'systemdata/usersRoles.php has page help')
  eq([P.group, P.inPlace], ['sbrugere', true])
  const U = 'https://ssl12.saldi.dk/asr_hj/systemdata/usersRoles.php'
  const doc = (...kids) => { const d = new Doc(); d.append(new El('body').append(...kids)); return d }
  const menu = () => new El('nav').append(new El('a', { class: 'st-menu-row', href: 'usersRoles.php?tab=roles' }), new El('a', { class: 'st-menu-row', href: 'usersRoles.php?tab=log' }))
  const list = ({ write = true, review = false, empty = false } = {}) => {
    const thead = new El('thead').append(write ? new El('input', { type: 'checkbox', id: 'ur-check-all' }) : new El('th'))
    const row = (id, self) => new El('tr', { 'data-search': 'x', 'data-status': 'active', ...(self ? { 'data-self': '1' } : {}), ...(review ? { 'data-review': '1' } : {}) })
      .append(...(write ? [new El('input', { type: 'checkbox', name: 'ids[]', value: id })] : []), new El('a', { class: 'ur-name-a', href: 'usersRoles.php?tab=users&bruger=' + id }))
    return new El('form', { id: 'ur-bulk' }).append(new El('input', { type: 'search', id: 'ur-search' }), new El('select', { id: 'ur-status-filter' }),
      new El('table', { id: 'ur-users' }).append(thead, new El('tbody').append(row('1', true), row('2', false),
        new El('tr', { 'data-search': 'y', 'data-status': 'nologin', class: 'ur-nologin' }).append(new El('a', { class: 'ur-name-a', href: '#' })))),
      new El('div', { id: 'ur-empty', ...(empty ? {} : { hidden: '' }) }))
  }
  const ctx = (d, q = '') => ctxFor(d, U + q)
  const run = (d, q) => { const c = ctx(d, q); const s = P.detect(c); return { c, s, order: P.order(c, s), qs: P.questionOrder(c, s), about: P.about(c, s) } }

  // the list: invite first; Vis mig rings Tilføj person, another user's row and the roles menu
  let d = doc(menu(), new El('button', { type: 'button', 'data-padd': '' }), list())
  let r = run(d, '?tab=users')
  eq([r.s.state, r.s.flags.kunLaes, r.order.slice(0, 3), r.qs], ['liste', false, ['BR1', 'BR3', 'BR5'], ['Q1', 'Q2', 'Q4']])
  eq(P.name(r.c, r.s), 'help.sbrugere.name.brugere')
  assert.equal(P.tasks.BR1.show.target(r.c, r.s).getAttribute('data-padd'), '')
  assert.ok(P.tasks.BR3.show.target(r.c, r.s).getAttribute('href').endsWith('bruger=2'), 'BR3 rings another user, not your own row or an employee')
  assert.ok(P.tasks.BR9.show.target(r.c, r.s).getAttribute('href').includes('tab=roles'))
  assert.equal(P.tasks.RO1.show.target(r.c, r.s), null, 'a roles target rings nothing on the list')
  // migrated users waiting, a search without hits, read-only
  r = run(doc(new El('div', { id: 'ur-review-banner' }).append(new El('a', { href: '?status=review' })), list({ review: true, empty: true })))
  eq([r.s.flags.gennemgang, r.s.flags.ingenTraef, r.order.slice(0, 2), r.qs[0]], [true, true, ['BR6', 'BR5'], 'Q11'])
  r = run(doc(list({ write: false })))
  eq([r.s.flags.kunLaes, r.order, r.qs[0]], [true, ['BR5', 'BR10'], 'Q13'])
  // a refused save leads with FE
  r = run(doc(new El('div', { class: 'sk-note sk-bad', role: 'status' }), list()))
  eq([r.s.flags.fejl, P.problem(r.c, r.s), r.order[0]], [true, 'FE', 'FE'])

  // the user card, branch markup: Tofaktor folded under Sikkerhed rings the fold; with Stamdata shown BR3 rings the tab
  const card = ({ id = '7', write = true, own = false, closed = false, invited = false, fane = 'login' } = {}) => {
    const sec = new El('details', { class: 'ur-card-section' }).append(new El('summary'), new El('select', { id: 'ur-tfa', name: 'twofactor' }))
    const form = new El('form', { id: 'ur-user-form' }).append(new El('input', { type: 'hidden', name: 'id', value: id }),
      new El('select', { id: 'ur-role', name: 'role_id', ...(own || !write ? { disabled: '' } : {}) }), sec, new El('textarea', { id: 'ur-ip', name: 'ip_address' }))
    const foot = new El('div', { class: 'sk-drawer-f' }).append(closed ? new El('button', { form: 'ur-reopen-form' }) : new El('button', { id: 'ur-close-btn' }),
      invited ? new El('button', { form: 'ur-resend-form' }) : new El('button', { form: 'ur-reset-form' }))
    return new El('aside', { id: 'ur-drawer', 'data-fane': fane }).append(new El('button', { id: 'ur-tab-stamdata' }), new El('button', { id: 'ur-tab-login' }), form, ...(write ? [foot] : []))
  }
  r = run(doc(list(), card()))
  eq([r.s.state, r.order.slice(0, 3), r.qs], ['bruger', ['BR3', 'BK2', 'BK4'], ['Q2', 'Q4', 'Q9']])
  assert.equal(P.tasks.BR3.show.target(r.c, r.s).getAttribute('id'), 'ur-role')
  assert.equal(P.tasks.BK4.show.target(r.c, r.s).tagName, 'SUMMARY', 'a closed fold: Vis mig rings its summary')
  assert.equal(P.tasks.BK9.show.target(r.c, r.s).getAttribute('id'), 'ur-ip', 'master: the field is not folded')
  r = run(doc(list(), card({ fane: 'stamdata' })))
  assert.equal(P.tasks.BR3.show.target(r.c, r.s).getAttribute('id'), 'ur-tab-login')
  r = run(doc(list(), card({ own: true })))
  eq([r.s.flags.rolleLaast, r.order[0], r.qs[0]], [true, 'BK12', 'Q7'])
  r = run(doc(list(), card({ closed: true })))
  eq([r.s.flags.lukket, r.order, r.qs[0]], [true, ['BK6', 'BK11'], 'Q8'])
  r = run(doc(list(), card({ invited: true })))
  eq([r.s.flags.inviteret, r.order[0], r.qs[0]], [true, 'BK7', 'Q10'])
  r = run(doc(list({ write: false }), card({ write: false })))
  eq([r.s.flags.kunLaes, r.s.flags.rolleLaast, r.qs[0]], [true, false, 'Q13'])

  // a new login: invitation or password
  const fresh = (mode) => new El('aside', { id: 'ur-drawer' }).append(new El('form', { id: 'ur-user-form' }).append(new El('input', { type: 'hidden', name: 'id', value: '0' }),
    new El('input', { type: 'hidden', name: 'mode', value: mode }), new El('input', { type: 'email', id: 'ur-email', name: 'email' }), new El('input', { type: 'password', name: 'kode' })),
  new El('div', { class: 'sk-drawer-f' }))
  r = run(doc(list(), fresh('invite')))
  eq([r.s.state, r.s.flags.invitation, r.order, r.about], ['nyBruger', true, ['NB1', 'NB3', 'BR9'], ['help.sbrugere.about.nybruger_invite']])
  r = run(doc(list(), fresh('classic')))
  eq([r.s.flags.invitation, r.order[0]], [false, 'NB2'])

  // an employee without a login, and one who has left
  const give = (acts) => new El('aside', { id: 'ur-drawer', 'data-fane': 'login' }).append(new El('div', { class: 'ur-give' }).append(
    ...(acts ? [new El('div', { class: 'ur-give-acts' }).append(new El('a', { class: 'sk-btn sk-btn-primary', href: '?bruger=0&ansat=3' }))] : []), new El('p', { class: 'sk-muted' })))
  r = run(doc(list(), give(true)))
  eq([r.s.state, r.order, r.qs[0]], ['person', ['PE1', 'PE2'], 'Q1'])
  assert.equal(P.tasks.PE1.show.target(r.c, r.s).getAttribute('href'), '?bruger=0&ansat=3')
  r = run(doc(list(), give(false)))
  eq([r.s.flags.fratraadt, r.order], [true, ['PE2']])

  // the roles editor on master: Gem is the panel's submit, Kopiér a form button, .ur-lock a standard role
  const master = (write, builtIn) => new El('div', { class: 'sk-split ur-roles' }).append(
    new El('section').append(...(write ? [new El('button', { form: 'ur-copy-form' }), new El('a', { href: 'usersRoles.php?tab=roles&rolle=0' })] : [])),
    new El('form', { id: 'ur-role-form' }).append(new El('input', { type: 'hidden', name: 'id', value: '4' }), ...(builtIn ? [new El('span', { class: 'sk-sub ur-lock' })] : []),
      ...(write ? [builtIn ? new El('button', { form: 'ur-reset-role-form' }) : new El('button', { id: 'ur-del-role' }), new El('button', { class: 'sk-btn sk-btn-sm sk-btn-primary', type: 'submit' })] : []),
      new El('input', { class: 'sk-radio', type: 'radio', name: 'level[finans.kassekladde]', value: 'none' }), new El('button', { 'data-all-write': '' })))
  r = run(doc(master(true, true)), '?tab=roles')
  eq([r.s.state, r.s.flags.standard, r.s.flags.egen, r.s.flags.kunLaes, r.order, r.qs], ['roller', true, false, false, ['RO1', 'RO2', 'RO3', 'RO6', 'RO4', 'RO7', 'RO8', 'BR10'], ['Q3', 'Q2', 'Q6']])
  eq(P.name(r.c, r.s), 'help.sbrugere.name.roller')
  assert.equal(P.tasks.RO3.show.target(r.c, r.s).getAttribute('form'), 'ur-copy-form')
  assert.equal(P.tasks.RO4.show.target(r.c, r.s).getAttribute('form'), 'ur-reset-role-form')
  assert.equal(P.tasks.RO2.show.target(r.c, r.s).getAttribute('name'), 'level[finans.kassekladde]')
  assert.equal(P.tasks.RO7.show.target(r.c, r.s).getAttribute('data-all-write'), '')
  assert.equal(P.tasks.RO8.show.target(r.c, r.s), null, 'master has no enforcement note on the roles page: no Vis mig')
  r = run(doc(master(true, false)), '?tab=roles')
  eq([r.s.flags.egen, P.tasks.RO5.show.target(r.c, r.s).getAttribute('id')], [true, 'ur-del-role'])
  r = run(doc(master(false, true)), '?tab=roles')
  eq([r.s.flags.kunLaes, r.order, r.about[0]], [true, ['RO6', 'RO8'], 'help.sbrugere.about.kunlaes'])

  // the roles editor on the roles branch: #ur-role-save, Kopiér as a link, Afvis in the enforcement note, a new role
  const branch = (id) => new El('div', { class: 'ur-roles ur-roles-editor', id: 'ur-roles-editor' }).append(
    new El('aside', { class: 'ur-role-list' }).append(new El('a', { href: 'usersRoles.php?tab=roles&rolle=0' })),
    new El('form', { id: 'ur-role-form' }).append(new El('input', { type: 'hidden', name: 'id', value: id }), new El('button', { id: 'ur-role-save', class: 'sk-btn sk-btn-primary', type: 'submit' }),
      new El('a', { href: 'usersRoles.php?tab=roles&copy_role=' + id }), new El('button', { id: 'ur-role-delete' }), new El('div', { class: 'sk-note sk-info ur-role-enforcement' }),
      new El('input', { type: 'hidden', name: 'level[finans.kassekladde]', value: 'none' }), new El('input', { class: 'sk-radio', type: 'radio', name: 'level[finans.kassekladde]', value: 'none' }),
      new El('button', { 'data-group-level': 'none' })))
  r = run(doc(branch('5')), '?tab=roles&rolle=5')
  eq([r.s.flags.egen, r.s.flags.afvis, r.about], [true, true, ['help.sbrugere.about.roller', 'help.sbrugere.about.afvis']])
  assert.ok(P.tasks.RO3.show.target(r.c, r.s).getAttribute('href').includes('copy_role=5'))
  assert.equal(P.tasks.RO2.show.target(r.c, r.s).getAttribute('type'), 'radio', 'never the hidden fallback')
  r = run(doc(branch('0')), '?tab=roles&rolle=0')
  eq([r.s.state, r.order, r.qs], ['nyRolle', ['RO1', 'RO2', 'RO7', 'RO8'], ['Q3', 'Q2', 'Q6']])

  // the log, and the refusal card
  const log = new El('section').append(new El('form', { class: 'sk-bar ur-log-bar' }).append(new El('select', { name: 'type' }), new El('select', { name: 'bruger' })),
    new El('table', { class: 'sk-table ur-aud' }), new El('details', { class: 'sk-panel ur-enforce', id: 'ur-enforce' }).append(new El('summary'),
      new El('input', { type: 'radio', name: 'mode', value: 'log', checked: '' }), new El('input', { type: 'radio', name: 'mode', value: 'deny' })))
  r = run(doc(log), '?tab=log')
  eq([r.s.state, r.s.flags.afvis, r.order, r.qs, P.name(r.c, r.s)], ['log', false, ['LG1', 'LG2', 'LG3', 'LG4'], ['Q14', 'Q4', 'Q6'], 'help.sbrugere.name.log'])
  assert.equal(P.tasks.LG4.show.target(r.c, r.s).tagName, 'SUMMARY')
  assert.equal(P.tasks.LG3.show.target(r.c, r.s), null, 'no CSV link without Import og eksport')
  r = run(doc(new El('div', { role: 'alert' }).append(new El('a', { href: '../index/main.php', target: '_top' }))))
  eq([r.s.state, r.order, r.qs[0], P.problem(r.c, r.s)], ['ingenAdgang', ['NA1'], 'Q12', ''])

  // field keys: the level radios are one field, the second password is the password, the searches by id
  eq([P.fieldKey(new El('input', { name: 'level[settings.backup]' })), P.fieldKey(new El('input', { name: 'kode2' })), P.fieldKey(new El('input', { id: 'ur-search' })),
    P.fieldKey(new El('select', { name: 'role_id' })), P.fieldKey(new El('input', { name: 'person[navn]' }))], ['level', 'kode', 'soeg', 'role_id', null])
  for (const k of ['level', 'kode', 'soeg', 'role_id']) assert.ok(P.fields[k], k)
})
