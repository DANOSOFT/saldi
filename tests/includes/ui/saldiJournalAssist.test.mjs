// --- tests/includes/ui/saldiJournalAssist.test.mjs --- ai/mcp-assistant --- 2026.10.03
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// javascript/saldi-journal-assist.js (SPEC-companion C8.1) against a small DOM stub: trigger and
// request shape, the suggestion row, "Brug" (only empty fields, page events, VAT kept after the
// page's async reset, never bilag/dato/beløb/faktura), Skjul/Esc, duplicates, telemetry, the
// tenant switch, and window.SaldiAssist.askAbout from javascript/saldi-inbox.js (C8.2).
// Run on the host: node --test tests/includes/ui/
//
// 20261002 CL/LH Created.
// 20261003 CDX/LH Add regression coverage for the confirmed Assist review findings.
// 20261007 CL/LH Source chips carry their kind; the row is marked busy while a refresh is on its way.
// 20261007 CL/LH Review fixes: the row spans the widest table row; Brug/Spørg Assist/finding styles.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const source = readFileSync(fileURLToPath(new URL('../../../javascript/saldi-journal-assist.js', import.meta.url)), 'utf8')
const css = readFileSync(fileURLToPath(new URL('../../../css/saldi_journal_assist.css', import.meta.url)), 'utf8')
const inboxSource = readFileSync(fileURLToPath(new URL('../../../javascript/saldi-inbox.js', import.meta.url)), 'utf8')
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

// ------------------------------------------------------------------ DOM stub

class StubEvent {
  constructor (type, init) {
    this.type = type
    this.bubbles = !!(init && init.bubbles)
    this.isTrusted = false
    this.defaultPrevented = false
    this.stopped = false
    if (init && init.key) this.key = init.key
  }

  preventDefault () { this.defaultPrevented = true }
  stopPropagation () { this.stopped = true }
}

class StubNode {
  constructor (doc, tag) {
    this.ownerDocument = doc
    this.tagName = tag.toUpperCase()
    this.children = []
    this.parentNode = null
    this.attributes = {}
    this.listeners = []
    this.className = ''
    this.textContent = ''
    this.dataset = {}
    this.style = {}
    this.value = ''
    this.name = ''
    this.type = ''
    this.checked = false
    this.colSpan = 1
    this.title = ''
    this.fired = []
  }

  appendChild (child) {
    if (child.parentNode) child.parentNode.removeChild(child)
    child.parentNode = this
    this.children.push(child)
    return child
  }

  insertBefore (child, ref) {
    if (child.parentNode) child.parentNode.removeChild(child)
    const index = ref ? this.children.indexOf(ref) : -1
    child.parentNode = this
    if (index < 0) this.children.push(child)
    else this.children.splice(index, 0, child)
    return child
  }

  removeChild (child) {
    this.children = this.children.filter((node) => node !== child)
    child.parentNode = null
    return child
  }

  replaceChild (next, old) {
    const index = this.children.indexOf(old)
    if (next.parentNode) next.parentNode.removeChild(next)
    this.children[index] = next
    next.parentNode = this
    old.parentNode = null
    return old
  }

  get nextSibling () {
    if (!this.parentNode) return null
    const list = this.parentNode.children
    return list[list.indexOf(this) + 1] || null
  }

  get cells () { return this.children.filter((node) => node.tagName === 'TD') }
  get rows () { return this.children.filter((node) => node.tagName === 'TR') }
  get options () { return this.tagName === 'SELECT' ? this._options : undefined }
  setAttribute (key, value) { this.attributes[key] = String(value) }
  getAttribute (key) { return Object.prototype.hasOwnProperty.call(this.attributes, key) ? this.attributes[key] : null }
  removeAttribute (key) { delete this.attributes[key] }
  addEventListener (type, fn, capture) { this.listeners.push({ type, fn, capture: !!capture }) }
  contains (node) {
    for (let current = node; current; current = current.parentNode) if (current === this) return true
    return false
  }

  matches (selector) {
    if (selector.startsWith('.')) return this.className.split(/\s+/).includes(selector.slice(1))
    return this.tagName === selector.toUpperCase()
  }

  closest (selector) {
    for (let current = this; current && current.matches; current = current.parentNode) if (current.matches(selector)) return current
    return null
  }

  all (predicate, out = []) {
    for (const child of this.children) {
      if (predicate(child)) out.push(child)
      child.all(predicate, out)
    }
    return out
  }

  querySelector (selector) { return this.all((node) => node.matches(selector))[0] || null }
  querySelectorAll (selector) { return this.all((node) => node.matches(selector)) }
  focus () { this.ownerDocument.activeElement = this }
  click () { this.dispatchEvent(new StubEvent('click', { bubbles: true })) }
  get text () { return [this.textContent, ...this.children.map((child) => child.text)].join('') }

  dispatchEvent (event) {
    this.fired.push(event.type)
    event.target = this
    const path = []
    for (let current = this; current; current = current.parentNode) path.push(current)
    for (const node of path.slice().reverse()) {
      for (const l of node.listeners) if (l.capture && l.type === event.type && !event.stopped) l.fn.call(node, event)
    }
    for (const node of event.bubbles ? path : [this]) {
      for (const l of node.listeners) if (!l.capture && l.type === event.type && !event.stopped) l.fn.call(node, event)
    }
    return !event.defaultPrevented
  }
}

function makeDocument () {
  const doc = new StubNode(null, '#document')
  doc.ownerDocument = doc
  doc.matches = () => false
  doc.activeElement = null
  doc.createElement = (tag) => new StubNode(doc, tag)
  doc.getElementsByName = (name) => doc.all((node) => node.name === name)
  doc.getElementById = (id) => doc.all((node) => node.attributes.id === id)[0] || null
  doc.createEvent = () => new StubEvent('')
  doc.body = doc.appendChild(doc.createElement('body'))
  return doc
}

/** One journal line like kassekladde.php renders it (row 1), plus its hidden id. */
function makePage (doc, values = {}) {
  const tbody = doc.body.appendChild(doc.createElement('tbody'))
  tbody.setAttribute('id', 'kassekladde-tbody')
  const tr = tbody.appendChild(doc.createElement('tr'))
  const fields = {}
  for (const name of ['bila', 'dato', 'besk', 'd_ty', 'debe', 'dvat', 'k_ty', 'kred', 'kvat', 'fakt', 'belo', 'moms']) {
    const td = tr.appendChild(doc.createElement('td'))
    const input = td.appendChild(doc.createElement(name.endsWith('vat') ? 'select' : 'input'))
    input.name = name + '1'
    input.type = name === 'moms' ? 'checkbox' : 'text'
    if (input.tagName === 'SELECT') input._options = ['', 'K1', 'S1', 'U1'].map((value) => ({ value }))
    input.value = values[name] ?? ''
    fields[name] = input
  }
  tr.appendChild(doc.createElement('td')).className = 'drag-handle'
  const id = tr.appendChild(doc.createElement('input'))
  id.type = 'hidden'
  id.name = 'id[1]'
  id.value = String(values.id ?? '0')
  const next = tbody.appendChild(doc.createElement('tr'))
  return { tbody, tr, next, fields }
}

const TEXTS = { 5960: 'Forslag', 5961: 'Brug', 5962: 'Skjul', 5963: 'Fra regel', 5964: 'Brugt %d gange, senest %s', 5965: 'Mulig dublet:', 5966: 'Spørg Assist', 5967: 'Linjen er udfyldt, men ikke gemt. Tryk Gem, når den er rigtig.', 5969: 'dage', 6014: 'Spørg Assist om denne linje', 671: 'Bilag', 1000: 'Debet', 1001: 'Kredit', 5716: 'Kladde', 637: 'Bogført' }

const HISTORY = {
  source: 'history', rule_id: null, confidence: 'high',
  debit: { ledger: 'account', account: '5900', name: 'Reklame', vat_code: 'K1' },
  credit: { ledger: 'account', account: '58000', name: 'Bank', vat_code: '' },
  vat_free: false, description: 'Krak', basis: { count: 7, last_date: '2025-06-23', line_ids: [783, 759] }
}
const RULE = {
  source: 'rule', rule_id: 3, confidence: 'high',
  debit: { ledger: 'account', account: '2800', name: 'Software', vat_code: 'K1' },
  credit: { ledger: 'supplier', account: '1005', name: 'Adobe', vat_code: '' },
  vat_free: false, description: 'Adobe', basis: { count: 2, last_date: null, line_ids: [] }
}
const DUP = { line_id: 783, kladde_id: 16, bilag: 114, date: '2025-06-23', amount: '573.75', description: 'Krak', posted: true, screen: 'finans/kassekladde.php?kladde_id=16' }

function load ({ respond, values, config, shell } = {}) {
  const doc = makeDocument()
  const page = makePage(doc, values)
  const cfg = config === undefined ? { kladdeId: 19, csrf: 'csrf-1', endpoint: 'kassekladde_includes/assistSuggest.php', lang: 1, texts: TEXTS } : config
  if (cfg) {
    const node = doc.body.appendChild(doc.createElement('script'))
    node.setAttribute('id', 'saldi-journal-assist-config')
    node.textContent = JSON.stringify(cfg)
  }
  const requests = []
  const window = {
    Event: StubEvent,
    location: { href: '' },
    fetch: (url, init) => {
      const body = JSON.parse(init.body)
      requests.push({ url, body, headers: init.headers, credentials: init.credentials, method: init.method })
      const [status, json] = (respond || (() => [200, { ok: true }]))(body)
      return Promise.resolve({ status, json: () => Promise.resolve(json) })
    }
  }
  window.parent = shell || window
  vm.runInNewContext(source, { window, document: doc, AbortController, setTimeout, clearTimeout, setInterval, clearInterval, Date, JSON, Promise, Math, isFinite, Object, Array, String, Number })
  const events = () => requests.filter((r) => r.body.op === 'event').map((r) => [r.body.event, r.body.subject, r.body.source ?? null])
  const suggests = () => requests.filter((r) => r.body.op === 'suggest')
  const row = () => page.tbody.querySelector('.sja-row')
  const leave = async (name = 'besk') => {
    page.fields[name].dispatchEvent(new StubEvent('change', { bubbles: true }))
    await sleep(300)
  }
  return { window, doc, page, requests, events, suggests, row, leave }
}

const suggestResponse = (suggestions, duplicates = []) => (body) => body.op === 'suggest' ? [200, { ok: true, suggestions, duplicates }] : [200, { ok: true }]

// ------------------------------------------------------------------ pure helpers

test('amounts, dates and display formats', () => {
  const { window } = load({ config: null })
  const a = window.SaldiJournalAssist
  assert.equal(a.parseAmount('1.250,00'), '1250.00')
  assert.equal(a.parseAmount('1250'), '1250.00')
  assert.equal(a.parseAmount('-25,5'), '-25.50')
  assert.equal(a.parseAmount('12.5'), '12.50')
  assert.equal(a.parseAmount('1.250'), '1250.00')
  assert.equal(a.parseAmount('1250,125'), '1250.125')
  assert.equal(a.parseAmount(''), null)
  assert.equal(a.parseAmount('abc'), null)
  assert.equal(a.parseDate('03-09-2026'), '2026-09-03')
  assert.equal(a.parseDate('030926'), '2026-09-03')
  assert.equal(a.parseDate('3.9.26'), '2026-09-03')
  assert.equal(a.parseDate('31-02-2026'), null)
  assert.equal(a.parseDate('i går'), null)
  assert.equal(a.formatDate('2026-09-03'), '03-09-2026')
  assert.equal(a.formatAmount('1250.00'), '1.250,00')
  const T = a.makeTexts(TEXTS)
  assert.equal(a.chipText(RULE, T), 'Debet 2800 Software · K1 / Kredit K 1005 Adobe')
  assert.equal(a.metaText(RULE, T), 'Fra regel')
  assert.equal(a.metaText(HISTORY, T), 'Brugt 7 gange, senest 23-06-2025')
  assert.equal(a.duplicateText(DUP, '2025-07-03', T), 'bilag 114, kladde 16, 23-06-2025, 573,75 (10 dage), bogført')
})

test('requestLine: trigger rule, party side, saved line id and bounded fields', () => {
  const { window } = load({ config: null })
  const a = window.SaldiJournalAssist
  const line = (values) => a.requestLine(a.lineFromValues(values))
  assert.equal(line({ besk: 'Kr' }), null)
  assert.equal(line({ besk: 'Krak', debe: '5900', kred: '58000' }), null)
  assert.deepEqual(JSON.parse(JSON.stringify(line({ besk: ' Krak ', belo: '573,75', dato: '23-06-2025', fakt: 'F-1', id: '783' }))),
    { description: 'Krak', amount: '573.75', date: '2025-06-23', invoice: 'F-1', exclude_line_id: 783 })
  assert.deepEqual(JSON.parse(JSON.stringify(line({ besk: 'Adobe', k_ty: 'k', kred: '1005' }).party)), { ledger: 'supplier', account: '1005', side: 'credit' })
  assert.deepEqual(JSON.parse(JSON.stringify(line({ besk: 'Salg', d_ty: 'D', debe: '7' }).party)), { ledger: 'customer', account: '7', side: 'debit' })
  const bounded = line({ besk: 'x'.repeat(150), belo: '0,00', fakt: 'y'.repeat(31), id: '0', d_ty: 'K', debe: 'ab' })
  assert.equal(bounded.description.length, 100)
  assert.equal('amount' in bounded || 'invoice' in bounded || 'exclude_line_id' in bounded || 'party' in bounded, false)
})

test('fillPlan fills only empty fields and never a conflicting side', () => {
  const { window } = load({ config: null })
  const plan = (current, s) => JSON.parse(JSON.stringify(window.SaldiJournalAssist.fillPlan(current, s)))
  assert.deepEqual(plan({ besk: 'Krak' }, HISTORY), { fill: { d_ty: 'F', debe: '5900', k_ty: 'F', kred: '58000' }, vat: { dvat: 'K1', kvat: '' } })
  // a user-typed credit account that differs keeps its side untouched
  assert.deepEqual(plan({ besk: 'Krak', kred: '58100' }, HISTORY), { fill: { d_ty: 'F', debe: '5900' }, vat: { dvat: 'K1' } })
  // the same account typed by the user is untouched, including its VAT
  assert.deepEqual(plan({ besk: 'Krak', debe: '5900' }, HISTORY).vat, { kvat: '' })
  // an explicit VAT choice is kept; a type that disagrees skips the side
  assert.deepEqual(plan({ besk: 'Krak', dvat: 'S1', k_ty: 'K' }, HISTORY), { fill: { d_ty: 'F', debe: '5900' }, vat: {} })
  // supplier side gets K + account and no VAT; vat_free ticks the exemption and blanks VAT
  assert.deepEqual(plan({ besk: 'Adobe' }, RULE), { fill: { d_ty: 'F', debe: '2800', k_ty: 'K', kred: '1005' }, vat: { dvat: 'K1', kvat: '' } })
  assert.deepEqual(plan({ besk: 'Gebyr' }, { ...HISTORY, vat_free: true }).fill.moms, true)
  assert.deepEqual(plan({ besk: 'Gebyr' }, { ...HISTORY, vat_free: true }).vat, { dvat: '', kvat: '' })
  assert.equal(plan({ besk: '' }, HISTORY).fill.besk, 'Krak')
})

// ------------------------------------------------------------------ page behaviour

test('leaving the description requests suggestions once (debounced) with CSRF and renders a quiet row', async () => {
  const t = load({ respond: suggestResponse([RULE, HISTORY, HISTORY, HISTORY], [DUP]), values: { besk: 'Krak', belo: '573,75', dato: '03-07-2025', bila: '7', id: '901' } })
  t.page.fields.besk.dispatchEvent(new StubEvent('focusout', { bubbles: true }))
  t.page.fields.besk.dispatchEvent(new StubEvent('change', { bubbles: true }))
  await sleep(300)
  assert.equal(t.suggests().length, 1)
  const request = t.suggests()[0]
  assert.equal(request.url, 'kassekladde_includes/assistSuggest.php')
  assert.equal(request.method, 'POST')
  assert.equal(request.credentials, 'same-origin')
  assert.equal(request.headers['X-Saldi-Csrf'], 'csrf-1')
  assert.deepEqual(request.body, { op: 'suggest', kladde_id: 19, line: { description: 'Krak', amount: '573.75', date: '2025-07-03', exclude_line_id: 901 } })
  const row = t.row()
  assert.ok(row, 'suggestion row rendered')
  assert.equal(t.page.tr.nextSibling, row, 'directly under the line')
  assert.equal(row.querySelector('td').colSpan, 13)
  const buttons = row.querySelectorAll('button')
  assert.deepEqual(buttons.map((b) => b.textContent), ['Brug', 'Brug', 'Brug', 'Skjul'], 'max 3 chips, no ask button outside the shell')
  assert.ok(buttons.every((b) => b.type === 'button'), 'buttons never submit')
  assert.match(row.text, /Fra regel/)
  assert.match(row.text, /Mulig dublet: bilag 114, kladde 16, 23-06-2025, 573,75 \(10 dage\), bogført/)
  await sleep(0)
  assert.deepEqual(t.events(), [['suggestion_shown', 'rule:3', 'rule'], ['suggestion_shown', 'kladde:19', 'history'], ['duplicate_warned', 'line:783', null]])
  // leaving again with the same line neither re-requests nor re-logs
  await t.leave('belo')
  assert.equal(t.suggests().length, 1)
  assert.equal(t.events().length, 3)
})

test('no request without a 3-character description or when both accounts are filled', async () => {
  const t = load({ values: { besk: 'Kr' } })
  await t.leave()
  t.page.fields.besk.value = 'Krak'
  t.page.fields.debe.value = '5900'
  t.page.fields.kred.value = '58000'
  await t.leave('kred')
  assert.equal(t.suggests().length, 0)
  assert.equal(t.row(), null)
})

test('Brug fills only empty fields, fires the page events, keeps the VAT code and never submits', async () => {
  const t = load({ respond: suggestResponse([HISTORY]), values: { besk: 'Krak', bila: '7', dato: '03-07-2025', fakt: '', belo: '573,75' } })
  // The page resets the VAT select asynchronously when the account changes (lookup_vat).
  t.page.fields.debe.addEventListener('change', () => setTimeout(() => { t.page.fields.dvat.value = '' }, 150))
  let submitted = false
  t.doc.addEventListener('submit', () => { submitted = true }, true)
  await t.leave()
  t.row().querySelector('.sja-use').click()
  const f = t.page.fields
  assert.deepEqual([f.d_ty.value, f.debe.value, f.k_ty.value, f.kred.value, f.dvat.value, f.kvat.value], ['F', '5900', 'F', '58000', 'K1', ''])
  assert.deepEqual([f.bila.value, f.dato.value, f.besk.value, f.belo.value, f.fakt.value], ['7', '03-07-2025', 'Krak', '573,75', ''])
  assert.deepEqual(f.d_ty.fired, ['input', 'change'])
  assert.deepEqual(f.debe.fired, ['change'], 'no input event: it would open the lookup panel')
  assert.deepEqual(f.dvat.fired, [], 'no change event: it would trigger the one-sided VAT confirm')
  assert.deepEqual(f.belo.fired.concat(f.bila.fired, f.dato.fired, f.fakt.fired), [])
  assert.equal(t.window.docChange, true)
  await sleep(400)
  assert.equal(f.dvat.value, 'K1', 'VAT re-applied after the page reset it')
  assert.equal(submitted, false)
  assert.match(t.row().text, /Linjen er udfyldt, men ikke gemt/)
  assert.equal(t.doc.activeElement, f.belo)
  assert.deepEqual(t.events().at(-1), ['suggestion_used', 'kladde:19', 'history'])
  // a used row does not ask again until the user rewrites the description
  f.kred.value = ''
  await t.leave('kred')
  assert.equal(t.suggests().length, 1)
})

test('a user edit of the VAT select during the guard wins', async () => {
  const t = load({ respond: suggestResponse([HISTORY]), values: { besk: 'Krak' } })
  await t.leave()
  t.row().querySelector('.sja-use').click()
  const vat = t.page.fields.dvat
  const focus = new StubEvent('focusin', { bubbles: true })
  focus.isTrusted = true
  vat.dispatchEvent(focus)
  vat.value = 'S1'
  await sleep(250)
  assert.equal(vat.value, 'S1')
})

test('Skjul and Esc hide the row, log the dismissal and silence the line', async () => {
  const t = load({ respond: suggestResponse([HISTORY]), values: { besk: 'Krak' } })
  await t.leave()
  t.row().querySelector('.sja-hide').click()
  assert.equal(t.row(), null)
  assert.deepEqual(t.events().at(-1), ['suggestion_dismissed', 'kladde:19', 'history'])
  t.page.fields.belo.value = '10'
  await t.leave('belo')
  assert.equal(t.suggests().length, 1)

  const esc = load({ respond: suggestResponse([RULE]), values: { besk: 'Adobe' } })
  await esc.leave()
  const use = esc.row().querySelector('.sja-use')
  use.dispatchEvent(new StubEvent('keydown', { bubbles: true, key: 'Escape' }))
  assert.equal(esc.row(), null)
  assert.deepEqual(esc.events().at(-1), ['suggestion_dismissed', 'kladde:19', 'rule'])
})

test('duplicate links open the kladde through the shell and only for the journal screen', async () => {
  const opened = []
  const shell = { update_iframe: (uri) => opened.push(uri) }
  const bad = { ...DUP, line_id: 5, screen: 'index/logud.php' }
  const t = load({ respond: suggestResponse([], [DUP, bad]), values: { besk: 'Krak', k_ty: 'K', kred: '1005', belo: '573,75' }, shell })
  await t.leave()
  assert.deepEqual(t.suggests()[0].body.line.party, { ledger: 'supplier', account: '1005', side: 'credit' })
  const links = t.row().querySelectorAll('a')
  assert.equal(links.length, 2)
  assert.equal(links[0].getAttribute('href'), '../finans/kassekladde.php?kladde_id=16')
  assert.equal(links[1].getAttribute('href'), null)
  links[0].click()
  links[1].click()
  assert.deepEqual(opened, ['/finans/kassekladde.php?kladde_id=16'])
})

test('Spørg Assist hands the line to the shell and logs ask_from_row', async () => {
  const asked = []
  const shell = { SaldiAssist: { askAbout: (payload) => { asked.push(payload); return true } } }
  const t = load({ respond: suggestResponse([]), values: { besk: 'Krak', bila: '7', dato: '03-07-2025', belo: '573,75', d_ty: 'F', debe: '5900', fakt: 'A-1', id: '901' }, shell })
  await t.leave()
  const buttons = t.row().querySelectorAll('button')
  assert.deepEqual(buttons.map((b) => b.textContent), ['Spørg Assist'], 'shown without suggestions')
  assert.equal(buttons[0].getAttribute('aria-label'), 'Spørg Assist om denne linje')
  buttons[0].click()
  assert.deepEqual(JSON.parse(JSON.stringify(asked)), [{
    kind: 'journal_line',
    kladdeId: 19,
    row: { bilag: '7', date: '2025-07-03', description: 'Krak', amount: '573.75', debit: { type: 'F', account: '5900' }, credit: { type: '', account: '' }, invoice: 'A-1' }
  }])
  await sleep(0)
  assert.deepEqual(t.events().at(-1), ['ask_from_row', 'line:901', null])
})

test('tenant switch off, CSRF failure or missing config: nothing is shown', async () => {
  const off = load({ respond: () => [200, { ok: true, enabled: false }], values: { besk: 'Krak' } })
  await off.leave()
  off.page.fields.belo.value = '5'
  await off.leave('belo')
  assert.equal(off.suggests().length, 1)
  assert.equal(off.row(), null)
  const denied = load({ respond: () => [403, { ok: false, error: { code: 'PERMISSION_DENIED', message_da: 'x' } }], values: { besk: 'Krak' } })
  await denied.leave()
  denied.page.fields.belo.value = '5'
  await denied.leave('belo')
  assert.equal(denied.requests.length, 1)
  const none = load({ config: null, values: { besk: 'Krak' } })
  await none.leave()
  assert.equal(none.requests.length, 0)
})

test('an unknown party is retried on the text alone', async () => {
  const t = load({
    respond: (body) => body.line && body.line.party ? [422, { ok: false, error: { code: 'VALIDATION_ERROR', message_da: 'x' } }] : [200, { ok: true, suggestions: [HISTORY], duplicates: [] }],
    values: { besk: 'Krak', k_ty: 'K', kred: '999' }
  })
  await t.leave()
  await sleep(0)
  assert.equal(t.suggests().length, 2)
  assert.equal('party' in t.suggests()[1].body.line, false)
  assert.ok(t.row())
})

test('grabbing a drag handle removes the suggestion rows before Sortable renumbers', async () => {
  const t = load({ respond: suggestResponse([HISTORY]), values: { besk: 'Krak' } })
  await t.leave()
  assert.ok(t.row())
  t.page.tr.querySelector('.drag-handle').dispatchEvent(new StubEvent('mousedown', { bubbles: true }))
  assert.equal(t.row(), null)
})

// ------------------------------------------------------------------ askAbout (saldi-inbox.js, C8.2)

function loadShell (extra = {}) {
  const dispatched = []
  class CustomEvent {
    constructor (type, init) {
      this.type = type
      this.detail = init.detail
    }
  }
  const window = {
    SaldiAssist: { appVersion: '5.0.0', ...extra },
    CustomEvent,
    dispatchEvent: (event) => { dispatched.push(event); return true }
  }
  const document = { getElementById: () => null, querySelectorAll: () => [], addEventListener: () => {} }
  vm.runInNewContext(inboxSource, { window, document, setTimeout, clearTimeout, Date, JSON, Promise, Math, isFinite, Object, Array, String, Number })
  return { window, dispatched }
}

const PAYLOAD = {
  kind: 'journal_line',
  kladdeId: 19,
  row: { bilag: '7', date: '2025-07-03', description: 'Krak', amount: '1573.75', debit: { type: 'F', account: '5900' }, credit: { type: '', account: '' }, invoice: '', extra: 'dropped' }
}

test('askAbout dispatches saldi:assist:ask with the fixed prefill and a validated copy', () => {
  const { window, dispatched } = loadShell()
  assert.equal(typeof window.SaldiAssist.askAbout, 'function', 'defined even without the inbox config')
  assert.equal(window.SaldiAssist.askAbout(PAYLOAD), false, 'no v2 listener yet')
  assert.equal(dispatched.length, 1)
  assert.equal(dispatched[0].type, 'saldi:assist:ask')
  assert.equal(dispatched[0].detail.question, 'Hjælp mig med linjen: bilag 7, Krak, 1.573,75 kr.')
  assert.deepEqual(JSON.parse(JSON.stringify(dispatched[0].detail.focus)), {
    kind: 'journal_line',
    kladdeId: 19,
    row: { bilag: '7', date: '2025-07-03', description: 'Krak', amount: '1573.75', invoice: '', debit: { type: 'F', account: '5900' }, credit: { type: '', account: '' } }
  })
  const ready = loadShell({ v2Ready: true })
  assert.equal(ready.window.SaldiAssist.askAbout(PAYLOAD), true)
})

test('askAbout refuses a wrong shape without dispatching', () => {
  const { window, dispatched } = loadShell({ v2Ready: true })
  const bad = [
    null,
    { ...PAYLOAD, kind: 'order' },
    { ...PAYLOAD, kladdeId: 0 },
    { ...PAYLOAD, kladdeId: 1.5 },
    { ...PAYLOAD, kladdeId: 'x' },
    { ...PAYLOAD, row: null },
    { ...PAYLOAD, row: { ...PAYLOAD.row, description: 'x'.repeat(101) } },
    { ...PAYLOAD, row: { ...PAYLOAD.row, amount: 12 } },
    { ...PAYLOAD, row: { ...PAYLOAD.row, debit: { type: 'X', account: '5900' } } },
    { ...PAYLOAD, row: { ...PAYLOAD.row, credit: { type: 'K', account: '10a5' } } },
    { ...PAYLOAD, row: { ...PAYLOAD.row, credit: 'K 1005' } }
  ]
  for (const payload of bad) assert.equal(window.SaldiAssist.askAbout(payload), false, JSON.stringify(payload))
  assert.equal(dispatched.length, 0)
  assert.equal(window.SaldiAssist.askAbout({ ...PAYLOAD, kladdeId: '19' }), true, 'a numeric string id is accepted')
  assert.equal(dispatched[0].detail.focus.kladdeId, 19)
})


test('fillPlan never manages VAT on a user-filled side, even for the same account', () => {
  const { window } = load()
  const plan = JSON.parse(JSON.stringify(window.SaldiJournalAssist.fillPlan(
    { besk: 'Krak', debe: '5900', dvat: 'S1', kred: '58000', kvat: '' }, HISTORY
  )))
  assert.deepEqual(plan, { fill: {}, vat: {} })
})

test('vat_free does not exempt a row with VAT on a user-filled side', () => {
  const { window } = load()
  for (const current of [{ debe: '5900', dvat: 'S1' }, { kred: '58100', kvat: 'K1' }]) {
    const plan = window.SaldiJournalAssist.fillPlan({ besk: 'Krak', ...current }, { ...HISTORY, vat_free: true })
    assert.equal(plan.fill.moms, undefined)
    assert.equal(plan.vat[current.debe ? 'dvat' : 'kvat'], undefined)
  }
})

test('source chips carry their kind (rule or history)', async () => {
  const t = load({ respond: suggestResponse([RULE, HISTORY]), values: { besk: 'Krak' } })
  await t.leave()
  const metas = t.row().querySelectorAll('.sja-chip__meta')
  assert.deepEqual(metas.map((m) => m.className), ['sja-chip__meta sja-src--rule', 'sja-chip__meta sja-src--history'])
  assert.deepEqual(metas.map((m) => m.textContent), ['Fra regel', 'Brugt 7 gange, senest 23-06-2025'])
})

test('an open row is marked busy while a fresh suggestion for the line is on its way', async () => {
  let release = null
  const t = load({ respond: suggestResponse([HISTORY]), values: { besk: 'Krak' } })
  await t.leave()
  const first = t.row()
  assert.ok(first)
  assert.equal(first.getAttribute('data-sja-busy'), null)
  const fetch = t.window.fetch
  t.window.fetch = (url, init) => {
    if (JSON.parse(init.body).op !== 'suggest') return fetch(url, init)
    return new Promise((resolve) => { release = () => resolve({ status: 200, json: () => Promise.resolve({ ok: true, suggestions: [HISTORY], duplicates: [] }) }) })
  }
  t.page.fields.besk.value = 'Krak annonce'
  await t.leave()
  assert.equal(first.getAttribute('data-sja-busy'), '')
  release()
  await sleep(0)
  await sleep(0)
  assert.equal(first.getAttribute('data-sja-busy'), null, 'cleared when the answer is the same')
  assert.equal(t.row(), first)
})

test('makeTexts falls back for ids findtekst does not know yet', () => {
  const { SaldiJournalAssist: api } = load().window
  const T = api.makeTexts({ 5961: 'Tekst nr: 5961', 5962: 'Skjul' })
  assert.equal(T(5961, 'Brug'), 'Brug')
  assert.equal(T(5962, 'x'), 'Skjul')
})

test('under a new, unsaved line the suggestion row still spans to the table edge', async () => {
  // Review (kladde, M): a new line has no Position and delete cells, so its own cell count stopped
  // the tinted row before the u/m, Position and action columns.
  let answer = [HISTORY]
  const t = load({ respond: (body) => suggestResponse(answer)(body), values: { besk: 'Krak' } })
  const saved = t.doc.createElement('tr')
  for (let i = 0; i < 15; i++) saved.appendChild(t.doc.createElement('td'))
  saved.cells[14].colSpan = 2
  t.page.tbody.insertBefore(saved, t.page.tr)
  assert.equal(t.page.tr.cells.length, 13)
  await t.leave()
  assert.equal(t.row().querySelector('.sja-cell').colSpan, 16)
  // A new answer re-renders the row; the old suggestion row itself is not counted.
  saved.removeChild(saved.cells[14])
  answer = [RULE, HISTORY]
  t.page.fields.besk.value = 'Krak annonce'
  await t.leave()
  assert.equal(t.row().querySelector('.sja-cell').colSpan, 14)
})

/** The declarations of the first rule whose selector list is exactly `selector`. */
function rule (selector) {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const m = css.match(new RegExp('(?:^|\\n)' + escaped + ' \\{([^}]*)\\}'))
  return m ? m[1] : null
}

test('stylesheet: Brug is a small rounded rectangle, the jade dot is busy-only, findings sit on a neutral tint', () => {
  // Review (kladde, M): Brug was a 999 px pill; V1 actions are rounded rectangles (9 px at 34 px).
  const use = rule('.sja-use')
  assert.ok(use, '.sja-use rule')
  const radius = Number(use.match(/border-radius:\s*([0-9.]+)px/)[1])
  const height = Number(use.match(/(?:^|\s)height:\s*([0-9.]+)px/)[1])
  assert.ok(radius < height / 2, 'not a pill: radius ' + radius + ' at height ' + height)
  assert.ok(radius / height <= 0.34, 'V1 button proportion')
  // Review (kladde, L): jade marks live state, so Spørg Assist shows its dot only while busy.
  assert.equal(rule('.sja-ask::before'), null)
  assert.match(rule('tr.sja-row[data-sja-busy] .sja-ask::before'), /background: var\(--sj-jade\)/)
  // Review (kladde, L): the error/warning lists use the neutral note tint; the title dot carries severity.
  assert.equal(rule('.sjc-section--error .sjc-list'), null)
  assert.equal(rule('.sjc-section--warning .sjc-list'), null)
  assert.match(rule('.sjc-list'), /background: var\(--sj-soft\)/)
  assert.match(rule('.sjc-section--error .sjc-section__title::before'), /--sj-err-dot/)
})
