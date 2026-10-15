// --- tests/includes/ui/saldiJournalFit.test.mjs --- ai/mcp-assistant --- 2026.10.07
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// javascript/saldi-journal-fit.js against a small layout model: the page is as wide as the wider of
// the journal table (fixed columns + the Bilagstekst inputs) and the note row (label + note field).
// The fit applies only while Saldi Assist is docked in the shell and the page overflows, takes back
// just the overflow (measured, so extra columns count), keeps fields that are not what overflows,
// and puts the page's own widths back when the panel closes.
// Run on the host: node --test tests/includes/ui/saldiJournalFit.test.mjs
//
// 20261007 CL/LH Created (review: the fixed 100vw width rule applied to every user and window width).
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const source = readFileSync(fileURLToPath(new URL('../../../javascript/saldi-journal-fit.js', import.meta.url)), 'utf8')

class Field {
  constructor (name, width) {
    this.name = name
    this.style = { width: width + 'px' }
    this.attributes = {}
  }

  get offsetWidth () { return parseFloat(this.style.width) + 6 }
  setAttribute (key, value) { this.attributes[key] = String(value) }
  getAttribute (key) { return Object.prototype.hasOwnProperty.call(this.attributes, key) ? this.attributes[key] : null }
  removeAttribute (key) { delete this.attributes[key] }
}

/**
 * tableFixed: the journal's width without the Bilagstekst column; the table scrolls inside its own
 * container (18 px narrower than the frame). noteFixed: the note row without the note field, which
 * makes the page itself wider. client: the frame width. layout: 'docked' | 'floating' | null.
 */
function makePage ({ client, tableFixed = 830, noteFixed = 520, rows = 3, layout = 'docked', inShell = true }) {
  const besk = Array.from({ length: rows }, (_, i) => new Field('besk' + (i + 1), 300))
  const others = [new Field('bila1', 90), new Field('belo1', 110)]
  const note = new Field('ny_kladdenote', 750)
  const beskColumn = () => Math.max(...besk.map((f) => f.offsetWidth), 90)
  const documentElement = {
    nodeType: 1,
    clientWidth: client,
    get scrollWidth () { return Math.max(this.clientWidth, noteFixed + note.offsetWidth) }
  }
  const body = { nodeType: 1, parentNode: documentElement, get clientWidth () { return documentElement.clientWidth }, get scrollWidth () { return documentElement.scrollWidth } }
  const container = { nodeType: 1, parentNode: body, get clientWidth () { return documentElement.clientWidth - 18 } }
  const center = { nodeType: 1, parentNode: container, clientWidth: 0 }
  const table = { nodeType: 1, parentNode: center, get offsetWidth () { return tableFixed + beskColumn() } }
  Object.defineProperty(container, 'scrollWidth', { get () { return Math.max(this.clientWidth, table.offsetWidth) } })
  const tbody = { closest: (sel) => (sel === 'table' ? table : null), querySelectorAll: (sel) => (sel === 'input' ? [...others, ...besk] : []) }
  const listeners = {}
  const doc = {
    nodeType: 9,
    readyState: 'complete',
    documentElement,
    body,
    getElementById: (id) => (id === 'kassekladde-tbody' ? tbody : null),
    getElementsByName: (name) => (name === 'ny_kladdenote' ? [note] : []),
    addEventListener: () => {}
  }
  documentElement.parentNode = doc
  const chat = { attrs: { 'data-layout': layout }, getAttribute (k) { return this.attrs[k] ?? null } }
  const shell = { document: { getElementById: (id) => (id === 'saldi-chat-container' && layout ? chat : null) } }
  const win = {
    addEventListener: (type, fn) => { (listeners[type] = listeners[type] || []).push(fn) },
    requestAnimationFrame: (fn) => fn()
  }
  win.parent = inShell ? shell : win
  vm.runInNewContext(source, { window: win, document: doc, setTimeout, Math, isFinite, parseFloat, String })
  const tableOver = () => Math.max(0, table.offsetWidth - container.clientWidth)
  const pageOver = () => documentElement.scrollWidth - documentElement.clientWidth
  const resize = (width, nextLayout) => {
    documentElement.clientWidth = width
    if (nextLayout !== undefined) chat.attrs['data-layout'] = nextLayout
    for (const fn of listeners.resize || []) fn()
  }
  return { win, doc, besk, others, note, tableOver, pageOver, resize, api: win.SaldiJournalFit }
}

test('docked: the Bilagstekst inputs give back exactly what the table overflows its scroll container', () => {
  // Table 830 + 306 = 1136 in a 1082 px container (frame 1100); the note row (300 + 756 = 1056) fits.
  const t = makePage({ client: 1100, noteFixed: 300 })
  assert.equal(t.tableOver(), 0)
  assert.equal(t.pageOver(), 0)
  assert.deepEqual(t.besk.map((f) => f.style.width), ['246px', '246px', '246px'])
  assert.equal(t.note.style.width, '750px', 'the note field was not what overflowed')
  assert.equal(t.note.getAttribute('data-sjf-width'), null)
  assert.deepEqual(t.others.map((f) => f.style.width), ['90px', '110px'])
})

test('only the note row is too wide: only the note field shrinks', () => {
  // note row 520 + 756 = 1276 > 1200; table 600 + 306 = 906 fits its 1182 px container.
  const t = makePage({ client: 1200, tableFixed: 600 })
  assert.equal(t.pageOver(), 0)
  assert.deepEqual(t.besk.map((f) => f.style.width), ['300px', '300px', '300px'])
  assert.equal(t.besk[0].getAttribute('data-sjf-width'), null)
  assert.equal(t.note.style.width, '674px')
})

test('both too wide: each gives back only its own share; extra columns are measured, not assumed', () => {
  // An extra 160 px of columns (projekt, afdeling): table 990 + 306 = 1296 in 1162; note row 1276 in 1180.
  const t = makePage({ client: 1180, tableFixed: 990 })
  assert.equal(t.tableOver(), 0)
  assert.equal(t.pageOver(), 0)
  assert.deepEqual(t.besk.map((f) => f.style.width), ['166px', '166px', '166px'])
  assert.equal(t.note.style.width, '654px')
})

test('the minimum widths hold; what is left scrolls rather than squeezing fields away', () => {
  const t = makePage({ client: 700 })
  assert.deepEqual(t.besk.map((f) => f.style.width), ['140px', '140px', '140px'])
  assert.equal(t.note.style.width, '174px')
  assert.equal(t.pageOver(), 0)
  assert.equal(t.tableOver(), 830 + 146 - 682)
})

test('without a docked panel nothing changes: floating chat, no chat, or a page outside the shell', () => {
  for (const options of [{ layout: 'floating' }, { layout: null }, { inShell: false }]) {
    const t = makePage({ client: 1000, ...options })
    assert.ok(t.tableOver() > 0 && t.pageOver() > 0, 'the page still overflows like before')
    assert.deepEqual(t.besk.map((f) => f.style.width), ['300px', '300px', '300px'])
    assert.equal(t.note.style.width, '750px')
    assert.equal(t.note.getAttribute('data-sjf-width'), null)
  }
})

test('docking and closing the panel resize the frame: fit, refit wider, then the page widths come back', () => {
  const t = makePage({ client: 1600, layout: 'floating' })
  assert.equal(t.note.style.width, '750px')
  t.resize(1100, 'docked')
  assert.equal(t.tableOver(), 0)
  assert.equal(t.pageOver(), 0)
  assert.equal(t.note.style.width, '574px')
  assert.deepEqual(t.besk.map((f) => f.style.width), ['246px', '246px', '246px'])
  t.resize(1180)
  assert.equal(t.note.style.width, '654px', 'a wider frame gives width back')
  assert.deepEqual(t.besk.map((f) => f.style.width), ['300px', '300px', '300px'])
  t.resize(1600, 'floating')
  assert.equal(t.note.style.width, '750px')
  assert.deepEqual(t.besk.map((f) => f.style.width), ['300px', '300px', '300px'])
  assert.equal(t.note.getAttribute('data-sjf-width'), null)
  assert.equal(t.besk[0].getAttribute('data-sjf-width'), null)
})

test('fitGroups undoes a step that does not help and leaves the field as it was', () => {
  const t = makePage({ client: 1600, layout: 'floating' })
  const field = new Field('besk9', 300)
  const left = t.api.fitGroups([{ nodes: [field], min: 140, overflow: () => 50 }])
  assert.equal(left, 50)
  assert.equal(field.style.width, '300px')
  assert.equal(field.getAttribute('data-sjf-width'), null)
})
