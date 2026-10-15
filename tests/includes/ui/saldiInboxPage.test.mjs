// --- tests/includes/ui/saldiInboxPage.test.mjs --- ai/mcp-assistant --- 2026.10.06
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// javascript/saldi-inbox-page.js, the pure parts: the split/stack decision, arrow-key movement that stays
// inside the list, the key map that is silent while typing or holding a modifier, and the row filter.
// The page itself runs against a small DOM stub: G/A press a card's Godkend/Afvis only for trusted,
// non-repeated key presses, after the arm delay, on a row the person chose and whose card is on screen,
// never on the row picked after a decision redirect; Enter is left to buttons, links and summaries.
// Run on the host: node --test tests/includes/ui/
//
// 20261006 CL/LH Created.
// 20261007 CL/LH Cover the review fixes: G/A custody (trusted, not repeated, armed, deliberate, visible)
//                and native Enter on buttons, links and summaries.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const source = readFileSync(fileURLToPath(new URL('../../../javascript/saldi-inbox-page.js', import.meta.url)), 'utf8')

function load () {
  const window = {}
  vm.runInNewContext(source, { window })
  return window.SaldiInboxPage
}

test('splits at the threshold and stacks below it', () => {
  const page = load()
  assert.equal(page.mode(page.SPLIT_MIN), 'split')
  assert.equal(page.mode(page.SPLIT_MIN - 1), 'stack')
  assert.equal(page.mode(0), 'stack')
})

test('arrow movement stays inside the list and starts at the ends', () => {
  const page = load()
  assert.equal(page.nextIndex(-1, 1, 5), 0)
  assert.equal(page.nextIndex(-1, -1, 5), 4)
  assert.equal(page.nextIndex(0, -1, 5), 0)
  assert.equal(page.nextIndex(4, 1, 5), 4)
  assert.equal(page.nextIndex(2, 1, 5), 3)
  assert.equal(page.nextIndex(0, 1, 0), -1)
})

test('keys map to actions, but not while typing, with a modifier, untrusted or auto-repeated', () => {
  const page = load()
  const key = (k, extra = {}) => ({ key: k, isTrusted: true, ...extra })
  assert.equal(page.keyAction(key('ArrowDown'), false), 'down')
  assert.equal(page.keyAction(key('ArrowUp'), false), 'up')
  assert.equal(page.keyAction(key('Enter'), false), 'toggle')
  assert.equal(page.keyAction(key('g'), false), 'approve')
  assert.equal(page.keyAction(key('A'), false), 'reject')
  assert.equal(page.keyAction(key('n'), false), 'note')
  assert.equal(page.keyAction(key('x'), false), null)
  assert.equal(page.keyAction(key('g'), true), null)
  assert.equal(page.keyAction(key('g', { ctrlKey: true }), false), null)
  assert.equal(page.keyAction(key('ArrowDown', { metaKey: true }), false), null)
  // Synthetic events (a script, another frame) never act; a held key repeats only the arrows.
  assert.equal(page.keyAction({ key: 'g' }, false), null)
  assert.equal(page.keyAction({ key: 'g', isTrusted: false }, false), null)
  assert.equal(page.keyAction(key('g', { repeat: true }), false), null)
  assert.equal(page.keyAction(key('a', { repeat: true }), false), null)
  assert.equal(page.keyAction(key('Enter', { repeat: true }), false), null)
  assert.equal(page.keyAction(key('ArrowDown', { repeat: true }), false), 'down')
})

test('a decision key is armed only on a chosen, visible card after the arm delay', () => {
  const page = load()
  const base = { selected: true, hidden: false, deliberate: true, mode: 'split', open: false, since: 1000, now: 1000 + page.KEY_ARM_MS }
  assert.equal(page.decisionArmed(base), true)
  assert.equal(page.decisionArmed({ ...base, now: 1000 + page.KEY_ARM_MS - 1 }), false, 'too soon after the selection')
  assert.equal(page.decisionArmed({ ...base, deliberate: false }), false, 'picked for the person, not by them')
  assert.equal(page.decisionArmed({ ...base, selected: false }), false)
  assert.equal(page.decisionArmed({ ...base, hidden: true }), false, 'filtered away')
  assert.equal(page.decisionArmed({ ...base, mode: 'stack', open: false }), false, 'folded row: the card is not on screen')
  assert.equal(page.decisionArmed({ ...base, mode: 'stack', open: true }), true)
})

test('the filter is a plain substring match on the prepared row text', () => {
  const page = load()
  assert.equal(page.matches('rykker: astrid fejrø rykker', 'astrid'), true)
  assert.equal(page.matches('rykker: astrid fejrø rykker', ''), true)
  assert.equal(page.matches('rykker: astrid fejrø rykker', 'kladde'), false)
  assert.equal(page.matches(null, 'x'), false)
})

// ------------------------------------------------------------------ the page (DOM stub)

function parseSelector (selector) {
  const m = /^([a-z]+)?((?:\.[\w-]+|\[[^\]]+\])*)$/i.exec(selector.trim())
  if (!m) throw new Error('unsupported selector ' + selector)
  const parts = { tag: m[1] ? m[1].toUpperCase() : null, classes: [], attrs: [] }
  for (const t of m[2].match(/\.[\w-]+|\[[^\]]+\]/g) || []) {
    if (t[0] === '.') parts.classes.push(t.slice(1))
    else { const a = /^\[([\w-]+)(?:="([^"]*)")?\]$/.exec(t); parts.attrs.push([a[1], a[2]]) }
  }
  return parts
}

function matchesSelector (node, selector) {
  return selector.split(',').some((one) => {
    const p = parseSelector(one)
    if (p.tag && node.tagName !== p.tag) return false
    if (!p.classes.every((c) => node.classList.contains(c))) return false
    return p.attrs.every(([k, v]) => { const got = node.getAttribute(k); return got !== null && (v === undefined || got === v) })
  })
}

function makeDom () {
  const clicks = []
  const docListeners = {}
  const doc = { activeElement: null, defaultView: null }
  function node (tag, attrs = {}, children = []) {
    const n = {
      tagName: tag.toUpperCase(), attrs: {}, children: [], parentNode: null, listeners: {}, hidden: false, disabled: false, open: false,
      _classes: new Set(),
      classList: {
        add: (c) => n._classes.add(c), remove: (c) => n._classes.delete(c), contains: (c) => n._classes.has(c),
        toggle: (c, force) => { const on = force === undefined ? !n._classes.has(c) : force; if (on) n._classes.add(c); else n._classes.delete(c); return on }
      },
      setAttribute (k, v) { this.attrs[k] = String(v); if (k === 'class') String(v).split(' ').filter(Boolean).forEach((c) => n._classes.add(c)) },
      getAttribute (k) { return k === 'class' ? [...n._classes].join(' ') : (Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null) },
      appendChild (child) { if (child.parentNode) child.parentNode.children = child.parentNode.children.filter((c) => c !== child); child.parentNode = this; this.children.push(child); return child },
      all () { return this.children.flatMap((c) => [c, ...c.all()]) },
      querySelector (sel) { return this.all().find((x) => matchesSelector(x, sel)) || null },
      querySelectorAll (sel) { return this.all().filter((x) => matchesSelector(x, sel)) },
      closest (sel) { for (let x = this; x && x.tagName; x = x.parentNode) if (matchesSelector(x, sel)) return x; return null },
      contains (other) { for (let x = other; x; x = x.parentNode) if (x === this) return true; return false },
      addEventListener (type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn) },
      focus () { doc.activeElement = this },
      scrollIntoView () {},
      click () { clicks.push(this) },
      // Rendered only when attached, not hidden, and not inside a folded row's card (CSS: display none).
      get offsetParent () {
        let attached = false
        for (let x = this; x; x = x.parentNode) {
          if (x.hidden) return null
          if (x.classList.contains('sai-row__card') && x.parentNode && x.parentNode.classList.contains('sai-row') && !x.parentNode.classList.contains('is-open')) return null
          if (x === doc.root) attached = true
        }
        return attached ? {} : null
      }
    }
    for (const [k, v] of Object.entries(attrs)) n.setAttribute(k, v)
    children.forEach((c) => n.appendChild(c))
    return n
  }
  return { node, doc, clicks, docListeners }
}

function loadPage ({ width = 1200, hash = '', ids = [11, 12, 13] } = {}) {
  const { node, doc, clicks, docListeners } = makeDom()
  const clock = { now: 100000 }
  const rows = ids.map((id) => node('li', { class: 'sai-row', 'data-item-id': String(id), 'data-filter': 'opgave ' + id }, [
    node('div', { class: 'sai-row__head', role: 'button', tabindex: '0' }),
    node('div', { class: 'sai-row__card' }, [
      node('article', { class: 'sac' }, [
        node('a', { class: 'sac-link', href: '../debitor/ordre.php?id=' + id }),
        node('form', { class: 'sac-actions' }, [
          node('details', { class: 'sac-note' }, [node('summary'), node('textarea')]),
          node('button', { name: 'op', value: 'approve' }),
          node('button', { name: 'op', value: 'reject' })
        ])
      ])
    ])
  ]))
  const rowsRoot = node('div', { 'data-sai-rows': '' }, [node('section', { class: 'sai-group' }, rows)])
  const detail = node('aside', { 'data-sai-detail': '' }, [node('p', { class: 'sai-detail__empty' })])
  const list = node('main', { 'data-sai-list': '' }, [rowsRoot, detail])
  list.clientWidth = width
  const search = node('input', { id: 'sai-search' })
  doc.root = node('body', {}, [search, list])
  doc.body = doc.root
  doc.querySelector = (sel) => doc.root.querySelector(sel)
  doc.getElementById = (id) => doc.root.all().find((x) => x.getAttribute('id') === id) || null
  doc.addEventListener = (type, fn) => { (docListeners[type] = docListeners[type] || []).push(fn) }
  const win = {
    location: { hash },
    history: { state: null, replaceState (state) { this.state = state } },
    addEventListener () {},
    setTimeout,
    clearTimeout
  }
  const FakeDate = { now: () => clock.now }
  const window = {}
  vm.runInNewContext(source, { window, Date: FakeDate })
  const api = window.SaldiInboxPage.init(doc, win)
  const key = (k, extra = {}) => {
    const event = { key: k, isTrusted: true, repeat: false, target: doc.activeElement || doc.body, defaultPrevented: false, preventDefault () { this.defaultPrevented = true }, ...extra }
    for (let x = event.target; x && x !== doc.root; x = x.parentNode) (x.listeners.keydown || []).forEach((fn) => fn(event))
    ;(docListeners.keydown || []).forEach((fn) => fn(event))
    return event
  }
  const clickHead = (row, isTrusted = true) => (row.querySelector('.sai-row__head').listeners.click || []).forEach((fn) => fn({ isTrusted, target: row.querySelector('.sai-row__head') }))
  const decided = () => clicks.filter((b) => b.tagName === 'BUTTON').map((b) => b.closest('.sai-row') ? b.closest('.sai-row').getAttribute('data-item-id') + ':' + b.getAttribute('value') : 'detail:' + b.getAttribute('value'))
  const pressed = () => clicks.filter((b) => b.tagName === 'BUTTON').map((b) => b.getAttribute('value'))
  return { api, doc, rows, detail, clock, key, clickHead, clicks, pressed, decided, list }
}

test('page: G on the start row acts only after the arm delay, never for synthetic or repeated presses', () => {
  const p = loadPage()
  assert.ok(p.rows[0].classList.contains('is-selected'))
  p.key('g')
  assert.deepEqual(p.pressed(), [], 'right after the page load')
  p.clock.now += 1000
  p.key('g', { isTrusted: false })
  p.key('g', { repeat: true })
  p.key('G', { ctrlKey: true })
  assert.deepEqual(p.pressed(), [])
  p.key('g')
  assert.deepEqual(p.pressed(), ['approve'])
  assert.equal(p.clicks[0], p.detail.querySelector('button[name="op"][value="approve"]'), 'the button of the card in the detail pane')
})

test('page: after a decision redirect the next row is never put under G/A', () => {
  const p = loadPage({ hash: '#sac-item-99' }) // the decided item left the Åbne list
  assert.equal(p.rows.some((r) => r.classList.contains('is-selected')), false, 'nothing selected')
  assert.equal(p.detail.querySelector('.sai-detail__empty').hidden, false)
  p.clock.now += 5000
  p.key('g'); p.key('a')
  assert.deepEqual(p.pressed(), [])
  p.key('ArrowDown') // the person picks a row
  assert.ok(p.rows[0].classList.contains('is-selected'))
  p.key('g')
  assert.deepEqual(p.pressed(), [], 'the arm delay starts again with the choice')
  p.clock.now += 1000
  p.key('a')
  assert.deepEqual(p.pressed(), ['reject'])
})

test('page: a redirect back to a still-listed row needs a deliberate choice; a click confirms without folding', () => {
  const p = loadPage({ width: 600, hash: '#sac-item-12' })
  const row = p.rows[1]
  assert.ok(row.classList.contains('is-selected') && row.classList.contains('is-open'))
  p.clock.now += 5000
  p.key('g')
  assert.deepEqual(p.pressed(), [])
  p.clickHead(row, false) // a scripted click is not a choice
  p.clock.now += 1000
  p.key('g')
  assert.deepEqual(p.pressed(), [])
  p.clickHead(row)
  assert.ok(row.classList.contains('is-open'), 'the first click confirms; the card stays open')
  p.clock.now += 1000
  p.key('g')
  assert.deepEqual(p.decided(), ['12:approve'])
})

test('page: in the stacked layout G/A do nothing on a folded row', () => {
  const p = loadPage({ width: 600 })
  const row = p.rows[0]
  p.clock.now += 1000
  p.key('Enter') // folds the selected row (Enter on the page, not on a control)
  assert.equal(row.classList.contains('is-open'), false)
  p.clock.now += 1000
  p.key('g'); p.key('a'); p.key('n')
  assert.deepEqual(p.pressed(), [])
  assert.equal(row.querySelector('details.sac-note').open, false, 'N does not open a hidden note')
  p.key('Enter') // unfold again: armed after the delay
  p.clock.now += 1000
  p.key('g')
  assert.deepEqual(p.decided(), ['11:approve'])
})

test('page: Enter keeps its native meaning on buttons, links, summaries and fields', () => {
  const p = loadPage({ width: 600 })
  const row = p.rows[0]
  p.clock.now += 1000
  for (const target of [row.querySelector('button[name="op"][value="approve"]'), row.querySelector('a'), row.querySelector('summary'), row.querySelector('textarea'), p.doc.getElementById('sai-search')]) {
    const event = p.key('Enter', { target })
    assert.equal(event.defaultPrevented, false, target.tagName)
    assert.ok(row.classList.contains('is-open'), 'the row did not fold: ' + target.tagName)
  }
  const onPage = p.key('Enter', { target: p.doc.body })
  assert.equal(onPage.defaultPrevented, true)
  assert.equal(row.classList.contains('is-open'), false)
  // Enter on the row head is handled by the head, once.
  const head = row.querySelector('.sai-row__head')
  const onHead = p.key('Enter', { target: head })
  assert.equal(onHead.defaultPrevented, true)
  assert.ok(row.classList.contains('is-open'))
})

test('page: filtering away the selected row disarms the row picked in its place', () => {
  const p = loadPage()
  p.clock.now += 1000
  const search = p.doc.getElementById('sai-search')
  search.value = 'opgave 12'
  ;(search.listeners.input || []).forEach((fn) => fn({}))
  assert.ok(p.rows[1].classList.contains('is-selected'))
  p.clock.now += 5000
  p.key('g')
  assert.deepEqual(p.pressed(), [], 'the filter picked it, not the person')
  p.clickHead(p.rows[1])
  p.clock.now += 1000
  p.key('g')
  assert.deepEqual(p.pressed(), ['approve'])
})
