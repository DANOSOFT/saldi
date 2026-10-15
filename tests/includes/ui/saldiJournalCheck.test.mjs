// --- tests/includes/ui/saldiJournalCheck.test.mjs --- ai/mcp-assistant --- 2026.10.04
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// javascript/saldi-journal-check.js (SPEC-companion-2 E3, "Tjek kladden") against a small DOM stub:
// the button next to Gem, the op 'check' request, the panel above the table (errors before
// warnings, grouped findings, clean state, failure), row marks and scrolling, the unsaved-changes
// note, Skjul/Esc and the re-run after a reload while the panel is open.
// Run on the host: node --test tests/includes/ui/saldiJournalCheck.test.mjs
//
// 20261004 CL/LH Created.
// 20261004 CDX/LH Verify Vis links, mixed row identifiers, early edits and closing during a request.
// 20261007 CL/LH V1 Stille panel: counted section titles, check time, Tjek igen, width follows the table.
// 20261007 CL/LH Review fix: the trigger is an input[type=button] like Gem and Simulér.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const source = readFileSync(fileURLToPath(new URL('../../../javascript/saldi-journal-check.js', import.meta.url)), 'utf8')
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

// ------------------------------------------------------------------ DOM stub

class StubEvent {
  constructor (type, init) {
    this.type = type
    this.bubbles = !!(init && init.bubbles)
    this.defaultPrevented = false
    this.stopped = false
    if (init && init.key) this.key = init.key
  }

  preventDefault () { this.defaultPrevented = true }
  stopPropagation () { this.stopped = true }
}

/** Supports "tag", ".class", "[attr]", "[attr="v"]" and combinations like input[name="save"]. */
function matcher (selector) {
  const m = selector.match(/^([a-z]*)((?:\.[\w-]+)*)((?:\[[\w-]+(?:="[^"]*")?\])*)$/i)
  if (!m) throw new Error('unsupported selector ' + selector)
  const tag = m[1].toUpperCase()
  const classes = m[2].split('.').filter(Boolean)
  const attrs = [...m[3].matchAll(/\[([\w-]+)(?:="([^"]*)")?\]/g)].map((a) => [a[1], a[2]])
  return (node) => {
    if (!node.tagName || (tag && node.tagName !== tag)) return false
    const own = String(node.className || '').split(/\s+/)
    if (!classes.every((c) => own.includes(c))) return false
    return attrs.every(([key, value]) => {
      const actual = key === 'name' || key === 'type' ? node[key] : node.getAttribute(key)
      return value === undefined ? actual !== null : actual === value
    })
  }
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
    this.style = {}
    this.value = ''
    this.name = ''
    this.type = ''
    this.title = ''
    this.disabled = false
    this.offsetWidth = 900
    this.scrolled = 0
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

  get disabled () { return this._disabled }
  set disabled (value) {
    this._disabled = value
    if (value && this.ownerDocument && this.ownerDocument.activeElement === this) this.ownerDocument.activeElement = null
  }

  setAttribute (key, value) { this.attributes[key] = String(value) }
  getAttribute (key) { return Object.prototype.hasOwnProperty.call(this.attributes, key) ? this.attributes[key] : null }
  removeAttribute (key) { delete this.attributes[key] }
  addEventListener (type, fn, capture) { this.listeners.push({ type, fn, capture: !!capture }) }
  contains (node) {
    for (let current = node; current; current = current.parentNode) if (current === this) return true
    return false
  }

  matches (selector) { return matcher(selector)(this) }
  closest (selector) {
    const match = matcher(selector)
    for (let current = this; current && current.tagName; current = current.parentNode) if (match(current)) return current
    return null
  }

  all (predicate, out = []) {
    for (const child of this.children) {
      if (predicate(child)) out.push(child)
      child.all(predicate, out)
    }
    return out
  }

  querySelector (selector) { return this.all(matcher(selector))[0] || null }
  querySelectorAll (selector) { return this.all(matcher(selector)) }
  focus () { this.ownerDocument.activeElement = this }
  scrollIntoView () { this.scrolled += 1; this.ownerDocument.scrolls.push(this) }
  click () { if (!this.disabled) this.dispatchEvent(new StubEvent('click', { bubbles: true })) }
  get text () { return [this.textContent, ...this.children.map((child) => child.text)].join('') }

  dispatchEvent (event) {
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
  doc.tagName = ''
  doc.ownerDocument = doc
  doc.activeElement = null
  doc.readyState = 'complete'
  doc.scrolls = []
  doc.createElement = (tag) => new StubNode(doc, tag)
  doc.getElementById = (id) => doc.all((node) => node.attributes.id === id)[0] || null
  doc.body = doc.appendChild(doc.createElement('body'))
  return doc
}

/**
 * The kassekladde layout the script relies on: the journal table (tbody#kassekladde-tbody) with
 * saved lines (delete button data-id + hidden id[n]) and an empty new line, then #buttonTable
 * with Bilags match, Gem and Opslag cells.
 */
function makePage (doc, lineIds) {
  const center = doc.body.appendChild(doc.createElement('center'))
  const table = center.appendChild(doc.createElement('table'))
  table.className = 'formnavi dataTableForm'
  const tbody = table.appendChild(doc.createElement('tbody'))
  tbody.setAttribute('id', 'kassekladde-tbody')
  const rows = {}
  lineIds.concat([0]).forEach((id, index) => {
    const tr = tbody.appendChild(doc.createElement('tr'))
    const besk = tr.appendChild(doc.createElement('td')).appendChild(doc.createElement('input'))
    besk.name = 'besk' + (index + 1)
    besk.type = 'text'
    if (id) {
      const del = tr.appendChild(doc.createElement('td')).appendChild(doc.createElement('button'))
      del.className = 'delete-line-btn'
      del.setAttribute('data-id', String(id))
    }
    const hidden = tr.appendChild(doc.createElement('input'))
    hidden.type = 'hidden'
    hidden.name = 'id[' + (index + 1) + ']'
    hidden.value = String(id)
    rows[id] = tr
  })
  const buttons = doc.body.appendChild(doc.createElement('table'))
  buttons.setAttribute('id', 'buttonTable')
  const buttonRow = buttons.appendChild(doc.createElement('tbody')).appendChild(doc.createElement('tr'))
  const cells = {}
  for (const name of ['bilagsmatch', 'save', 'lookup']) {
    const td = buttonRow.appendChild(doc.createElement('td'))
    const input = td.appendChild(doc.createElement('span')).appendChild(doc.createElement('input'))
    input.type = name === 'bilagsmatch' ? 'button' : 'submit'
    input.name = name
    cells[name] = td
  }
  return { center, table, tbody, rows, buttonRow, cells }
}

const TEXTS = {
  671: 'Bilag', 5962: 'Skjul', 6140: 'Tjek kladden', 6141: 'Ingen fund. Kladden ser klar ud.', 6142: 'Fejl, der stopper bogføringen', 6143: 'Bør tjekkes',
  1133: 'Vis', 6291: 'Tjekket gælder kun de gemte linjer.',
  6366: 'Tjek igen', 6367: 'Tjekket kl. %s',
  6292: 'Markér linjerne i kladden', 6293: 'Tjekker kladden …', 6294: 'Kladden kunne ikke tjekkes lige nu.', 6295: 'Tjek de gemte linjer for fejl, før du bogfører. Intet ændres.'
}

const finding = (code, severity, lineIds, bilag, message) => ({ code, severity, line_ids: lineIds, bilag, message_da: message, detail: {} })
const RESULT = {
  ok: true,
  journal_id: 19,
  status: 'open',
  ready: false,
  errors: [
    finding('unbalanced', 'error', [902, 903], 6, 'Bilaget stemmer ikke: forskel 250,00'),
    finding('date_outside_year', 'error', [904], 7, 'Dato uden for regnskabsåret'),
    finding('date_outside_year', 'error', [905], 9, 'Dato uden for regnskabsåret')
  ],
  warnings: [
    finding('one_sided', 'warning', [902], 6, 'Linjen mangler konto på den ene side'),
    finding('missing_document', 'warning', [901], 5, 'Bilag mangler'),
    finding('possible_duplicate', 'warning', [777], 3, 'Mulig dublet af bilag 2 i kladde 16')
  ]
}
const CLEAN = { ok: true, journal_id: 19, status: 'open', ready: true, errors: [], warnings: [] }

function load ({ respond, config, lineIds = [901, 902, 903, 904, 905], docChange = false, stored = null, noSave = false } = {}) {
  const doc = makeDocument()
  const page = makePage(doc, lineIds)
  if (noSave) page.cells.save.parentNode.removeChild(page.cells.save)
  const cfg = config === undefined ? { kladdeId: 19, csrf: 'csrf-1', endpoint: 'kassekladde_includes/assistSuggest.php', lang: 1, texts: TEXTS } : config
  if (cfg) {
    const node = doc.body.appendChild(doc.createElement('script'))
    node.setAttribute('id', 'saldi-journal-assist-config')
    node.textContent = JSON.stringify(cfg)
  }
  const requests = []
  const store = new Map(stored ? Object.entries(stored) : [])
  const windowListeners = []
  const window = {
    docChange,
    listeners: windowListeners,
    addEventListener: (type, fn) => windowListeners.push({ type, fn }),
    sessionStorage: {
      getItem: (key) => (store.has(key) ? store.get(key) : null),
      setItem: (key, value) => store.set(key, String(value)),
      removeItem: (key) => store.delete(key)
    },
    fetch: (url, init) => {
      requests.push({ url, body: JSON.parse(init.body), headers: init.headers, credentials: init.credentials, method: init.method })
      const [status, json] = (respond || (() => [200, RESULT]))(JSON.parse(init.body))
      return Promise.resolve({ status, json: () => (json === undefined ? Promise.reject(new Error('no json')) : Promise.resolve(json)) })
    }
  }
  vm.runInNewContext(source, { window, document: doc, JSON, Promise, Math, Object, Array, String, Number })
  const trigger = () => doc.querySelector('.sjc-trigger')
  const panel = () => doc.querySelector('.sjc-panel')
  const run = async () => { trigger().click(); await sleep(0); await sleep(0) }
  const marked = () => doc.querySelectorAll('[data-saldi-assist-highlight]')
  return { window, doc, page, requests, store, trigger, panel, run, marked }
}

// ------------------------------------------------------------------ pure helpers

test('groupFindings merges equal code+message, keeps order, lines and vouchers', () => {
  const { window } = load({ config: null })
  const c = window.SaldiJournalCheck
  const groups = JSON.parse(JSON.stringify(c.groupFindings(RESULT.errors.concat([{ code: 'x', message_da: '' }, null, finding('no_account', 'error', [0, -2, 1.5, 'x', 906, 906], null, 'Linjen har et beløb, men ingen konto.')]))))
  assert.deepEqual(groups, [
    { code: 'unbalanced', message: 'Bilaget stemmer ikke: forskel 250,00', lineIds: [902, 903], bilag: [6] },
    { code: 'date_outside_year', message: 'Dato uden for regnskabsåret', lineIds: [904, 905], bilag: [7, 9] },
    { code: 'no_account', message: 'Linjen har et beløb, men ingen konto.', lineIds: [906], bilag: [] }
  ])
  const T = c.makeTexts(TEXTS)
  assert.equal(c.bilagLabel([7], T), 'Bilag 7')
  assert.equal(c.bilagLabel([1, 2, 3, 4, 5, 6, 7, 8], T), 'Bilag 1, 2, 3, 4, 5, 6 +2')
  assert.equal(c.bilagLabel([], T), '')
  assert.equal(c.showLabel({ bilag: [], lineIds: [1] }, T), 'Vis')
  assert.equal(c.showLabel({ bilag: [7], lineIds: [1, 2] }, T), 'Vis')
  assert.equal(c.readResult({ ok: false, error: {} }), null)
  assert.equal(c.readResult({ ok: true, enabled: false }), null)
  assert.equal(c.readResult(CLEAN).ready, true)
})

// ------------------------------------------------------------------ page behaviour

test('the button sits right after Gem, never submits and does not run by itself', async () => {
  const t = load()
  const cells = t.page.buttonRow.children
  assert.equal(cells.indexOf(t.page.cells.save) + 1, cells.indexOf(t.trigger().parentNode))
  // Review (kladde, M): an <input type="button"> with the legacy classes and Gem's 120 px width,
  // so the page's input[type=button] style makes it look like its neighbours, not a white <button>.
  assert.equal(t.trigger().tagName, 'INPUT')
  assert.equal(t.trigger().type, 'button')
  assert.equal(t.trigger().value, 'Tjek kladden')
  assert.equal(t.trigger().className, 'button gray medium sjc-trigger')
  assert.equal(t.trigger().style.width, '120px')
  assert.equal(t.trigger().title, 'Tjek de gemte linjer for fejl, før du bogfører. Intet ændres.')
  await sleep(0)
  assert.equal(t.requests.length, 0)
  assert.equal(t.panel(), null)
})

test('no config, an invalid config or no Gem button: no button', () => {
  assert.equal(load({ config: null }).trigger(), null)
  assert.equal(load({ config: { kladdeId: 0, csrf: 'x' } }).trigger(), null)
  assert.equal(load({ noSave: true }).trigger(), null)
})

test('Tjek kladden posts op check with CSRF and renders errors before warnings above the table', async () => {
  const t = load()
  await t.run()
  assert.equal(t.requests.length, 1)
  const request = t.requests[0]
  assert.equal(request.url, 'kassekladde_includes/assistSuggest.php')
  assert.equal(request.method, 'POST')
  assert.equal(request.credentials, 'same-origin')
  assert.equal(request.headers['X-Saldi-Csrf'], 'csrf-1')
  assert.equal(request.headers['Content-Type'], 'application/json')
  assert.deepEqual(request.body, { op: 'check', kladde_id: 19 })

  const panel = t.panel()
  assert.ok(panel)
  assert.equal(panel.nextSibling, t.page.table, 'directly above the journal table')
  assert.equal(panel.getAttribute('aria-label'), 'Tjek kladden')
  assert.equal(panel.style.maxWidth, '900px')
  const sections = panel.querySelectorAll('.sjc-section')
  assert.deepEqual(sections.map((s) => s.className), ['sjc-section sjc-section--error', 'sjc-section sjc-section--warning'])
  assert.equal(sections[0].querySelector('.sjc-section__name').textContent, 'Fejl, der stopper bogføringen')
  assert.equal(sections[0].querySelector('.sjc-section__count').textContent, '2')
  assert.equal(sections[1].querySelector('.sjc-section__title').text, 'Bør tjekkes3')
  assert.match(panel.querySelector('.sjc-when').textContent, /^Tjekket kl\. [0-9]{2}:[0-9]{2}$/)
  assert.equal(panel.querySelector('.sjc-rerun').textContent, 'Tjek igen')
  const items = (s) => s.querySelectorAll('.sjc-item').map((li) => li.text)
  assert.deepEqual(items(sections[0]), ['Bilaget stemmer ikke: forskel 250,00Bilag 6Vis', 'Dato uden for regnskabsåretBilag 7, 9Vis'])
  // the duplicate's own line is not on this page: no button, only the voucher as text
  assert.deepEqual(items(sections[1]), ['Linjen mangler konto på den ene sideBilag 6Vis', 'Bilag manglerBilag 5Vis', 'Mulig dublet af bilag 2 i kladde 16Bilag 3'])
  assert.equal(sections[1].querySelectorAll('.sjc-show').length, 2)
  assert.equal(sections[1].querySelectorAll('.sjc-meta').at(-1).textContent, 'Bilag 3')
  assert.ok(panel.querySelectorAll('button').every((b) => b.type === 'button'), 'panel buttons never submit')
  assert.equal(t.trigger().disabled, false)
  assert.equal(t.store.get('saldi-journal-check:19'), '1')
})

test('a finding marks its rows, scrolls to the first and steps on; another finding moves the marks', async () => {
  const t = load()
  await t.run()
  const [unbalanced, dates] = t.panel().querySelectorAll('.sjc-show')
  assert.equal(unbalanced.title, 'Markér linjerne i kladden')
  unbalanced.click()
  assert.deepEqual(t.marked(), [t.page.rows[902], t.page.rows[903]])
  assert.equal(unbalanced.tagName, 'A')
  assert.equal(unbalanced.textContent, 'Vis')
  assert.equal(unbalanced.getAttribute('href'), '#saldi-journal-check-line-902')
  assert.equal(unbalanced.getAttribute('data-active'), '')
  assert.deepEqual(t.doc.scrolls, [t.page.rows[902]])
  unbalanced.click()
  unbalanced.click()
  assert.deepEqual(t.doc.scrolls, [t.page.rows[902], t.page.rows[903], t.page.rows[902]])
  dates.click()
  assert.deepEqual(t.marked(), [t.page.rows[904], t.page.rows[905]])
  assert.equal(unbalanced.getAttribute('data-active'), null)
  // the shell drops marks on an edit; the next press marks again
  t.marked().forEach((row) => row.removeAttribute('data-saldi-assist-highlight'))
  dates.click()
  assert.deepEqual(t.marked(), [t.page.rows[904], t.page.rows[905]])
  assert.equal(t.doc.scrolls.at(-1), t.page.rows[904])
})

test('rows are also found through the hidden id inputs when there are no delete buttons', async () => {
  const t = load()
  t.doc.querySelectorAll('.delete-line-btn').forEach((b) => b.parentNode.removeChild(b))
  await t.run()
  t.panel().querySelector('.sjc-show').click()
  assert.deepEqual(t.marked(), [t.page.rows[902], t.page.rows[903]])
})

test('a finding maps all rows when only some have delete buttons', async () => {
  const t = load()
  const del = t.page.rows[902].querySelector('.delete-line-btn')
  del.parentNode.removeChild(del)
  await t.run()
  t.panel().querySelector('.sjc-show').click()
  assert.deepEqual(t.marked(), [t.page.rows[902], t.page.rows[903]])
})

test('an edit before opening the panel survives rechecks and close/reopen', async () => {
  const t = load({ respond: () => [200, CLEAN] })
  t.page.rows[901].querySelector('input').dispatchEvent(new StubEvent('input', { bubbles: true }))
  assert.equal(t.window.docChange, false, 'the change handler may not have run yet')
  await t.run()
  assert.ok(t.panel().querySelector('.sjc-note'))
  await t.run()
  assert.ok(t.panel().querySelector('.sjc-note'))
  t.panel().querySelector('.sjc-hide').click()
  await t.run()
  assert.ok(t.panel().querySelector('.sjc-note'))
})

test('closing a pending check discards its response, including after a new check', async () => {
  const t = load()
  const releases = []
  t.window.fetch = () => new Promise((resolve) => releases.push((json) => resolve({ status: 200, json: () => Promise.resolve(json) })))
  t.trigger().click()
  t.panel().querySelector('.sjc-hide').click()
  assert.equal(t.panel(), null)
  assert.equal(t.trigger().disabled, false)
  t.trigger().click()
  releases[0](RESULT)
  await sleep(0)
  assert.ok(t.panel().querySelector('.sjc-busy'))
  assert.equal(t.trigger().disabled, true)
  releases[1](CLEAN)
  await sleep(0)
  assert.ok(t.panel().querySelector('.sjc-clean'))
  assert.equal(t.trigger().disabled, false)
})

test('failed HTTP status and network rejection never show a clean result', async () => {
  const failed = load({ respond: () => [500, CLEAN] })
  await failed.run()
  assert.ok(failed.panel().querySelector('.sjc-failed'))
  assert.equal(failed.panel().querySelector('.sjc-clean'), null)
  const offline = load()
  offline.window.fetch = () => Promise.reject(new Error('offline'))
  await offline.run()
  assert.ok(offline.panel().querySelector('.sjc-failed'))
  assert.equal(offline.trigger().disabled, false)
})

test('keyboard activation moves focus into the panel and Escape returns to the trigger', async () => {
  const t = load()
  t.trigger().focus()
  await t.run()
  assert.equal(t.doc.activeElement, t.panel())
  const esc = new StubEvent('keydown', { bubbles: true, key: 'Escape' })
  t.panel().dispatchEvent(esc)
  assert.equal(t.panel(), null)
  assert.equal(t.doc.activeElement, t.trigger())
})

test('a clean kladde gets one calm line', async () => {
  const t = load({ respond: () => [200, CLEAN] })
  await t.run()
  assert.equal(t.panel().querySelectorAll('.sjc-section').length, 0)
  assert.equal(t.panel().querySelector('.sjc-clean').textContent, 'Ingen fund. Kladden ser klar ud.')
  assert.equal(t.panel().querySelector('.sjc-note'), null)
})

test('unsaved changes: a note when checking a changed page, and once after a later edit', async () => {
  const dirty = load({ docChange: true, respond: () => [200, CLEAN] })
  await dirty.run()
  assert.equal(dirty.panel().querySelectorAll('.sjc-note').length, 1)
  assert.equal(dirty.panel().querySelector('.sjc-note').textContent, 'Tjekket gælder kun de gemte linjer.')

  const t = load()
  await t.run()
  assert.equal(t.panel().querySelector('.sjc-note'), null)
  const besk = t.page.rows[901].querySelector('input')
  besk.dispatchEvent(new StubEvent('input', { bubbles: true }))
  besk.dispatchEvent(new StubEvent('change', { bubbles: true }))
  const notes = t.panel().querySelectorAll('.sjc-note')
  assert.equal(notes.length, 1)
  assert.equal(t.panel().children[1], notes[0], 'note right under the heading')
  // a re-check keeps the note while the page is still unsaved
  await t.run()
  assert.equal(t.panel().querySelectorAll('.sjc-note').length, 1)
})

test('a failed check says so, with the server message, and the button works again', async () => {
  const t = load({ respond: () => [403, { ok: false, error: { code: 'PERMISSION_DENIED', message_da: 'Du har ikke adgang.', detail: {} } }] })
  await t.run()
  assert.equal(t.panel().querySelector('.sjc-failed').textContent, 'Kladden kunne ikke tjekkes lige nu. Du har ikke adgang.')
  assert.equal(t.trigger().disabled, false)
  const broken = load({ respond: () => [500, undefined] })
  await broken.run()
  assert.equal(broken.panel().querySelector('.sjc-failed').textContent, 'Kladden kunne ikke tjekkes lige nu.')
})

test('one request at a time; the panel shows that it is checking', async () => {
  let release
  const t = load()
  t.window.fetch = (url, init) => {
    t.requests.push(JSON.parse(init.body))
    return new Promise((resolve) => { release = () => resolve({ status: 200, json: () => Promise.resolve(CLEAN) }) })
  }
  t.trigger().click()
  t.trigger().disabled = false // even a forced second click while running is ignored
  t.trigger().click()
  assert.equal(t.requests.length, 1)
  assert.equal(t.panel().querySelector('.sjc-busy').textContent, 'Tjekker kladden …')
  release()
  await sleep(0)
  await sleep(0)
  assert.ok(t.panel().querySelector('.sjc-clean'))
})

test('Skjul and Esc close the panel, clear marks and forget the open state', async () => {
  const t = load()
  await t.run()
  t.panel().querySelector('.sjc-show').click()
  t.panel().querySelector('.sjc-hide').click()
  assert.equal(t.panel(), null)
  assert.equal(t.marked().length, 0)
  assert.equal(t.store.has('saldi-journal-check:19'), false)
  assert.equal(t.doc.activeElement, t.trigger())

  await t.run()
  const esc = new StubEvent('keydown', { bubbles: true, key: 'Escape' })
  t.panel().querySelector('.sjc-show').dispatchEvent(esc)
  assert.equal(esc.defaultPrevented, true)
  assert.equal(t.panel(), null)
})

test('an open panel is checked again after the page reloads (Gem), only for the same kladde', async () => {
  const again = load({ stored: { 'saldi-journal-check:19': '1' } })
  await sleep(0)
  await sleep(0)
  assert.equal(again.requests.length, 1)
  assert.ok(again.panel())
  const other = load({ stored: { 'saldi-journal-check:20': '1' } })
  await sleep(0)
  assert.equal(other.requests.length, 0)
})

test('timeLabel gives hours and minutes with leading zeros', () => {
  const { SaldiJournalCheck: api } = load().window
  assert.equal(api.timeLabel(new Date(2026, 9, 7, 9, 5)), '09:05')
  assert.equal(api.timeLabel(new Date(2026, 9, 7, 13, 41)), '13:41')
  assert.equal(api.timeLabel(new Date('x')), '')
  assert.equal(api.timeLabel(null), '')
})

test('Tjek igen runs the check again from the panel; the busy panel has no time and no Tjek igen', async () => {
  const t = load()
  await t.run()
  assert.equal(t.requests.length, 1)
  t.panel().querySelector('.sjc-rerun').click()
  assert.ok(t.panel().querySelector('.sjc-busy'))
  assert.equal(t.panel().querySelector('.sjc-rerun'), null)
  assert.equal(t.panel().querySelector('.sjc-when'), null)
  await sleep(0)
  await sleep(0)
  assert.equal(t.requests.length, 2)
  assert.ok(t.panel().querySelector('.sjc-section'))
})

test('a failed check offers Tjek igen but no check time', async () => {
  const t = load({ respond: () => [500, undefined] })
  await t.run()
  assert.ok(t.panel().querySelector('.sjc-failed'))
  assert.ok(t.panel().querySelector('.sjc-rerun'))
  assert.equal(t.panel().querySelector('.sjc-when'), null)
})

test('the panel follows the table width when the frame is resized (Saldi Assist docks)', async () => {
  const t = load()
  await t.run()
  assert.equal(t.panel().style.maxWidth, '900px')
  t.page.table.offsetWidth = 640
  t.window.listeners.filter((l) => l.type === 'resize').forEach((l) => l.fn())
  assert.equal(t.panel().style.maxWidth, '640px')
})

test('makeTexts falls back for ids findtekst does not know yet', () => {
  const { SaldiJournalCheck: api } = load().window
  const T = api.makeTexts({ 6366: 'Tekst nr: 6366', 6367: 'Tjekket kl. %s', 6140: '' })
  assert.equal(T(6366, 'Tjek igen'), 'Tjek igen')
  assert.equal(T(6367, 'x'), 'Tjekket kl. %s')
  assert.equal(T(6140, 'Tjek kladden'), 'Tjek kladden')
})
