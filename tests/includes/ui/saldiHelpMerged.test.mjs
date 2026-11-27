// --- tests/includes/ui/saldiHelpMerged.test.mjs --- ver 5.1.0 --- 2026.11.24
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// Hjælp inside Saldi Assist, the host side (javascript/saldi-help.js, doc/hjaelp_saldi_ai.md §19): the shell with the
// help files on a small fake DOM, a Kladdeliste in the frame, and the Guide loader stubbed. With SaldiAssist.helpReady the
// Hjælp button, ? and F1 open the chat, the state goes out as saldi:help:state and the chat's actions work in the page;
// without it the panel opens as before and no saldi:help:* event is sent.
// Run on the host: node --test tests/includes/ui/*.test.mjs (not run in CI, §4.4).
//
// 20261108 CL/ASR Created.
// 20261108 CL/ASR Merge review: a tour ended on its card, the field kept through Vis mig, views, the page going away, a
//                 press outside the chat, focus back into a docked chat, the loader's own isOpen()/layout() and
//                 saldi:chat:open, the topbar button, the chat that does not connect, and the actions run, back and seen;
//                 the fields in the search of a v1.1 loader with Vis mig on field:<id>, and the loader's hide()/show().
//                 Nodes are compared with assert.ok(a === b): a failed assert.equal() on a fake node ran out of memory.
// 20261108 ASR Vis mig audit (spec §21): the callout's button says Ok (chrome text 8795).
// 20261108 ASR Vis mig check (spec §21): the panel's own Vis mig without the merge also says Ok.
// 20261108 CL/ASR Oversigt in the frame (o.frame): its state is read again when it hides or shows itself (saldi:dash-hidden).
// 20261108 CL/ASR Oversigt review: hidden gives FS10, FS7, FS9.
// 20261108 ASR Saldi AI symbol: in merged mode the loader owns the topbar symbol marked data-saldi-assist-symbol.
// 20261108 CL/ASR The Debitor and Kreditor module files load after saldi-help-finans.js, as in index/main.php.
// 20261108 ASR Saldi Assist in the shell (spec §23, o.shell): Hjælp resumes the conversation and closes an open chat, a
//              leftover ring ends first, every chat open arms the watchdog, and "+ Ny chat" is hidden through
//              data-saldi-help-dead while the panel has taken over, its hidden left to the loader.
// 20261109 CL/ASR The Lager and Indstillinger module files load after saldi-help-kreditor.js, as in index/main.php. A settings section
//                 with a page of its own (spec §24) shows that page and loads its text group; another section the generic page.
// 20261109 CL/ASR Page questions (spec §25): the state carries the Kladdeliste's top three questions and the labels 8825-8827.
// 20261112 CL/ASR The CRM in the frame (crmPage()): a page.inPlace is read again after a click or Esc, Enter, N (tab, drawer,
//                 dialog), and a page that is not inPlace is not (spec §22.10).
// 20261124 CL/ASR Fictive fixture names and numbers; no change to what the test checks.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const root = fileURLToPath(new URL('../../../', import.meta.url))
const read = (f) => readFileSync(root + f, 'utf8')
const SCRIPTS = ['javascript/saldi-help-content.js', 'javascript/saldi-help-finans.js', 'javascript/saldi-help-debitor.js', 'javascript/saldi-help-kreditor.js',
  'javascript/saldi-help-lager.js', 'javascript/saldi-help-settings.js', 'javascript/saldi-help-state.js', 'javascript/saldi-help.js'].map(read)
const KEYED = read('importfiler/tekster_keyed.tsv').split('\n').filter((l) => l !== '').map((l) => l.split('\t'))
const CHROME = new Map(read('importfiler/tekster.csv').split('\n').filter((l) => /^\d+\t/.test(l)).map((l) => { const f = l.split('\t'); return [f[0], f[1]] }))
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

// ------------------------------------------------------------------ a small fake DOM
// Never hand a fake node to assert.equal() and friends: a failed assertion prints both values 1000 levels deep with
// their getters (node ignores a custom inspect there), and these linked nodes then grow the message until the process
// runs out of memory instead of failing. Compare nodes with assert.ok(a === b, '…').
// Elements, text nodes, fragments and documents with the calls saldi-help.js makes. Selectors: tag, #id, .class,
// [attr], [attr="v"], [attr=v], [attr^="v"], [attr*="v"], [attr$="v"], :not(simple), descendant and child combinators,
// comma lists.

function parseCompound (s) {
  const c = { tag: null, id: null, classes: [], attrs: [], nots: [] }
  let rest = s.trim()
  let m
  while (rest) {
    if ((m = /^[a-zA-Z][\w-]*|^\*/.exec(rest))) c.tag = m[0] === '*' ? null : m[0].toUpperCase()
    else if ((m = /^#([\w-]+)/.exec(rest))) c.id = m[1]
    else if ((m = /^\.([\w-]+)/.exec(rest))) c.classes.push(m[1])
    else if ((m = /^\[\s*([\w-]+)\s*(?:([\^*$]?=)\s*(?:"([^"]*)"|([\w-]+)))?\s*\]/.exec(rest))) c.attrs.push({ name: m[1], op: m[2] || null, value: m[3] !== undefined ? m[3] : m[4] })
    else if ((m = /^:not\(([^()]*)\)/.exec(rest))) c.nots.push(parseCompound(m[1]))
    else throw new Error('fake DOM: unsupported selector "' + rest + '"')
    rest = rest.slice(m[0].length)
  }
  return c
}
function splitTop (s, sep) {
  const out = []
  let cur = ''
  let depth = 0
  let quote = null
  for (const ch of s) {
    if (quote) { cur += ch; if (ch === quote) quote = null; continue }
    if (ch === '"' || ch === "'") { quote = ch; cur += ch; continue }
    if (ch === '[' || ch === '(') depth++
    if (ch === ']' || ch === ')') depth--
    if (depth === 0 && sep.includes(ch)) { out.push(cur, ch); cur = ''; continue }
    cur += ch
  }
  out.push(cur)
  return out
}
function parseSelector (sel) {
  return splitTop(sel, ',').filter((x) => x !== ',').map((part) => {
    const seq = []
    let comb = null
    for (const t of splitTop(part.trim().replace(/\s*>\s*/g, '>'), ' >')) {
      if (t === ' ' || t === '>') { comb = t; continue }
      if (t !== '') { seq.push({ c: parseCompound(t), comb }); comb = null }
    }
    return seq
  })
}
function matchCompound (el, c) {
  if (!el || el.nodeType !== 1) return false
  if (c.tag && el.tagName.toUpperCase() !== c.tag) return false
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
  if (seq[i].comb === '>') return matchSeq(el.parentElement, seq, i - 1)
  for (let p = el.parentElement; p; p = p.parentElement) if (matchSeq(p, seq, i - 1)) return true
  return false
}

class FNode {
  constructor (doc) { this.ownerDocument = doc; this.childNodes = []; this.parentNode = null; this.listeners = {} }
  get firstChild () { return this.childNodes[0] || null }
  get parentElement () { return this.parentNode && this.parentNode.nodeType === 1 ? this.parentNode : null }
  get children () { return this.childNodes.filter((n) => n.nodeType === 1) }
  get isConnected () { let p = this; while (p.parentNode) p = p.parentNode; return p.nodeType === 9 }
  get textContent () { return this.childNodes.map((c) => c.textContent).join('') }
  set textContent (v) { this.childNodes.forEach((c) => { c.parentNode = null }); this.childNodes = []; if (String(v) !== '') this.appendChild(new FText(this.ownerDocument, String(v))) }
  appendChild (n) {
    if (n.nodeType === 11) { [...n.childNodes].forEach((c) => this.appendChild(c)); return n }
    if (n.parentNode) n.parentNode.removeChild(n)
    n.parentNode = this
    this.childNodes.push(n)
    return n
  }
  insertBefore (n, ref) {
    if (!ref) return this.appendChild(n)
    if (n.parentNode) n.parentNode.removeChild(n)
    n.parentNode = this
    this.childNodes.splice(this.childNodes.indexOf(ref), 0, n)
    return n
  }
  removeChild (n) { const i = this.childNodes.indexOf(n); if (i >= 0) this.childNodes.splice(i, 1); n.parentNode = null; return n }
  contains (n) { for (let p = n; p; p = p.parentNode) if (p === this) return true; return false }
  * descendants () { for (const k of this.childNodes) { if (k.nodeType === 1) { yield k; yield * k.descendants() } } }
  querySelectorAll (sel) { const seqs = parseSelector(sel); return [...this.descendants()].filter((el) => seqs.some((seq) => matchSeq(el, seq))) }
  querySelector (sel) { return this.querySelectorAll(sel)[0] || null }
  addEventListener (type, fn, opts) { (this.listeners[type] = this.listeners[type] || []).push({ fn, once: !!(opts && opts.once) }) }
  removeEventListener (type, fn) { this.listeners[type] = (this.listeners[type] || []).filter((l) => l.fn !== fn) }
  dispatchEvent (ev) {
    for (const l of (this.listeners[ev.type] || []).slice()) { if (l.once) this.removeEventListener(ev.type, l.fn); l.fn.call(this, ev) }
    return !ev.defaultPrevented
  }

}
class FText extends FNode {
  constructor (doc, text) { super(doc); this.nodeType = 3; this.data = text }
  get textContent () { return this.data }
  set textContent (v) { this.data = String(v) }
}
class FFragment extends FNode { constructor (doc) { super(doc); this.nodeType = 11 } }
class FElement extends FNode {
  constructor (doc, tag) {
    super(doc)
    this.nodeType = 1
    this.tagName = tag.toUpperCase()
    this.attrs = {}
    this.value = ''
    this.rect = null
    const el = this
    this.style = { setProperty (k, v) { this[k] = v }, removeProperty (k) { delete this[k] } }
    this.classList = {
      list: () => (el.getAttribute('class') || '').split(/\s+/).filter(Boolean),
      add (...c) { el.setAttribute('class', [...new Set([...this.list(), ...c])].join(' ')) },
      remove (...c) { el.setAttribute('class', this.list().filter((x) => !c.includes(x)).join(' ')) },
      contains (c) { return this.list().includes(c) },
      toggle (c, force) { const on = force === undefined ? !this.contains(c) : !!force; if (on) this.add(c); else this.remove(c); return on }
    }
  }

  getAttribute (n) { return Object.prototype.hasOwnProperty.call(this.attrs, n) ? this.attrs[n] : null }
  setAttribute (n, v) { this.attrs[n] = String(v) }
  removeAttribute (n) { delete this.attrs[n] }
  hasAttribute (n) { return this.getAttribute(n) !== null }
  get id () { return this.getAttribute('id') || '' }
  set id (v) { this.setAttribute('id', v) }
  get className () { return this.getAttribute('class') || '' }
  set className (v) { this.setAttribute('class', v) }
  get hidden () { return this.hasAttribute('hidden') }
  set hidden (v) { if (v) this.setAttribute('hidden', ''); else this.removeAttribute('hidden') }
  get type () { return this.getAttribute('type') || '' }
  set type (v) { this.setAttribute('type', v) }
  get name () { return this.getAttribute('name') || '' }
  getClientRects () { return this.rect && this.isConnected ? [this.rect] : [] }
  getBoundingClientRect () { return this.rect || { left: 0, top: 0, right: 0, bottom: 0, width: 0, height: 0 } }
  matches (sel) { return parseSelector(sel).some((seq) => matchSeq(this, seq)) }
  closest (sel) { for (let e = this; e && e.nodeType === 1; e = e.parentNode) if (e.matches(sel)) return e; return null }
  focus () { this.ownerDocument.activeElement = this }
  click () { this.dispatchEvent({ type: 'click', target: this, detail: 1, preventDefault () {}, stopPropagation () {} }) }
  cloneNode (deep) {
    const c = new FElement(this.ownerDocument, this.tagName)
    c.attrs = { ...this.attrs }
    if (deep) this.childNodes.forEach((k) => c.appendChild(k.nodeType === 1 ? k.cloneNode(true) : new FText(this.ownerDocument, k.data)))
    return c
  }
  scrollIntoView () {}
  scrollBy () {}
  setSelectionRange () {}
}
class FDocument extends FNode {
  constructor () {
    super(null)
    this.ownerDocument = this
    this.nodeType = 9
    this.readyState = 'complete'
    this.documentElement = this.appendChild(new FElement(this, 'html'))
    this.head = this.documentElement.appendChild(new FElement(this, 'head'))
    this.body = this.documentElement.appendChild(new FElement(this, 'body'))
    this.activeElement = this.body
    this.forms = {}
  }

  createElement (tag) { return new FElement(this, tag) }
  createElementNS (ns, tag) { return new FElement(this, tag) }
  createTextNode (text) { return new FText(this, text) }
  createDocumentFragment () { return new FFragment(this) }
  getElementById (id) { return this.querySelector('#' + id) }
  getElementsByName (name) { return [...this.descendants()].filter((e) => e.getAttribute('name') === name) }
}
function E (doc, tag, attrs = {}, kids = [], rect = null) {
  const el = doc.createElement(tag)
  for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v)
  kids.forEach((k) => el.appendChild(k))
  el.rect = rect
  return el
}
const BOX = { left: 10, top: 10, right: 110, bottom: 40, width: 100, height: 30 }

// ------------------------------------------------------------------ the shell

const CHROME_IDS = [...Array.from({ length: 96 }, (_, i) => String(8700 + i)), '8825', '8826', '8827', '2564', '6024', '6455', '92', '5148', '636', '641']

const FAR = { left: 600, top: 500, right: 700, bottom: 540, width: 100, height: 40 }
/** A Kladdeliste without journals in the frame, with the tour card of tutorial.php (hidden until a tour runs). */
function kladdeliste () {
  const doc = new FDocument()
  const note = E(doc, 'input', { type: 'text', name: 'search[kladdelst][kladdenote]', value: '' }, [], BOX)
  const wrapper = E(doc, 'div', { id: 'datatable-wrapper-kladdelst' }, [
    E(doc, 'table', { id: 'datatable-kladdelst' }, [E(doc, 'thead', {}, [note]), E(doc, 'tbody')])
  ])
  doc.body.appendChild(E(doc, 'a', { id: 'create-new', href: 'kassekladde.php?kladde_id=-1' }, [], BOX))
  doc.body.appendChild(wrapper)
  doc.body.appendChild(E(doc, 'div', { id: 'tutorial-tooltip' }, [
    E(doc, 'button', { id: 'tutorial-finish' }, [], FAR), E(doc, 'button', { id: 'tutorial-ask' }, [], FAR)
  ]))
  // somewhere else in the page: a plain spot and a field, away from the targets
  doc.body.appendChild(E(doc, 'p', { id: 'far-spot' }, [], FAR))
  doc.body.appendChild(E(doc, 'input', { type: 'text', id: 'far-field', name: 'far' }, [], FAR))
  return doc
}

/** Oversigt (index/dashboard.php) in the frame, new company: 0 kr, and the overview hidden or shown (dashboard.php:441, :481). */
function dashboard (hidden) {
  const doc = new FDocument()
  const value = E(doc, 'span', {}, [doc.createTextNode('0 kr')])
  const kpis = E(doc, 'section', { class: 'blk db-sec db-kpis', 'data-block': 'kpis' }, [E(doc, 'div', { class: 'db-cell', 'data-widget': 'revyear' }, [E(doc, 'div', { class: 'db-val' }, [value])])], BOX)
  const grid = E(doc, 'div', { id: 'grid', class: hidden ? 'grid all-hidden' : 'grid' }, [kpis])
  doc.body.appendChild(E(doc, 'div', { class: 'dash' }, [grid, E(doc, 'div', { id: 'dashHidden', class: 'db-sec dash-hidden' }, [E(doc, 'button', { type: 'button', id: 'showDash' }, [], BOX)])]))
  return doc
}

/** The CRM (debitor/crm.php) in the frame as javascript/crm.js draws it on ?tab=follow: the tabs, the head's controls, one
 *  open follow-up, the drawer (closed, empty) and the dialog box #scrim (closed). crm.js changes all of it in place. */
function crmPage () {
  const doc = new FDocument()
  const tabs = E(doc, 'div', { class: 'sk-tabs', role: 'tablist', id: 'tabs' },
    ['follow', 'pipe', 'cal', 'cust', 'act'].map((id) => E(doc, 'button', { type: 'button', class: 'sk-tab', role: 'tab', 'aria-selected': String(id === 'follow'), 'data-tab': id }, [], BOX)))
  const scope = E(doc, 'div', { class: 'sk-seg', id: 'scope' }, [E(doc, 'button', { 'data-scope': 'all' }, [], BOX), E(doc, 'button', { 'data-scope': 'mine' }, [], BOX)])
  const row = E(doc, 'tr', { class: 'row body-row', 'data-open': '4', 'data-h': '7' }, [E(doc, 'td', {}, [E(doc, 'button', { class: 'tick', 'data-tick': '7' }, [], BOX)])], BOX)
  doc.body.appendChild(E(doc, 'div', { class: 'sk sk-page crm', id: 'crm' }, [
    tabs, E(doc, 'input', { class: 'sk-input', id: 'q' }, [], BOX), E(doc, 'select', { class: 'sk-input', id: 'grpSel' }, [], BOX), scope,
    E(doc, 'button', { type: 'button', class: 'sk-btn sk-btn-primary', id: 'newBtn' }, [], BOX),
    E(doc, 'div', { class: 'crm-view', id: 'v-follow' }, [E(doc, 'table', {}, [E(doc, 'tbody', {}, [row])])]),
    E(doc, 'div', { class: 'crm-view', id: 'v-cust', hidden: '' }),
    E(doc, 'div', { class: 'sk-scrim', id: 'dscrim' }),
    E(doc, 'aside', { class: 'sk-drawer drawer crm-drawer', id: 'drawer', role: 'dialog' }),
    E(doc, 'div', { class: 'sk-modal', id: 'scrim' }, [E(doc, 'div', { class: 'sk-dialog crm-dlg', id: 'dlg', role: 'dialog' })])
  ]))
  return doc
}

/**
 * The shell with the help panel on. o.helpReady: the loader keeps the merge contract; o.chat: the chat box
 * ({ open, layout, rect }) or null; o.v2: the Guide v2 script tag is in the page; o.api: the loader's isOpen()/layout()
 * ({ layout }); o.helpConnected: what the loader says about its widget; o.fast: the 4 s timer runs in 30 ms;
 * o.registry: the page's page_help() registry (topbarAssistPage()), whose rows go to topbarAssistRun(); o.hide: the loader
 * has hide() and show() (counted in chat.hidden and chat.shown); o.frame: another page in the frame ({ doc, route, group },
 * with query: the address's ?… and texts: the help texts the fetch answers for group, else the TSV's); o.extra: a script
 * that runs after the module files, before the state builder and the shell.
 * o.shell: a loader in shell mode (spec §23): SaldiAssist.shellVersion 1 and the topbar's "+ Ny chat" bound by it, hidden
 * when o.shell.hidden (an older chat window); o.store: more localStorage entries.
 */
function shell (o = {}) {
  const doc = new FDocument()
  const txt = Object.fromEntries(CHROME_IDS.filter((id) => CHROME.has(id)).map((id) => [id, CHROME.get(id)]))
  const help = E(doc, 'aside', { id: 'saldi-help', hidden: '', 'data-lang': 'da', 'data-support-send': '0', 'data-records': '0', 'data-signal': '0', 'data-txt': JSON.stringify(txt) })
  const iframe = E(doc, 'iframe', { id: 'iframe_a', name: 'iframe_a' })
  const sym = Object.assign({ class: 'ai-avatar' }, o.symbolAttr ? { 'data-saldi-assist-symbol': '' } : {})
  const btn = E(doc, 'button', { id: 'topbar-help-btn' }, [E(doc, 'span', sym, [E(doc, 'svg', {}, [E(doc, 'linearGradient'), E(doc, 'path', { class: 'sa-stroke' })])])])
  const newBtn = o.shell ? E(doc, 'button', { id: 'topbar-assist-new', class: 'topbar-assist-new', 'data-saldi-assist-new': 'bound', ...(o.shell.hidden ? { hidden: '' } : {}) }) : null
  doc.body.appendChild(E(doc, 'header', { id: 'topbar', class: 'topbar', ...(o.shell ? { 'data-saldi-assist-topbar': '' } : {}) }, newBtn ? [btn, newBtn] : [btn]))
  doc.body.appendChild(E(doc, 'div', { class: 'home-section' }, [E(doc, 'div', { class: 'home-content' }, [iframe, help])]))
  let box = null
  if (o.chat) {
    box = E(doc, 'div', { id: 'saldi-chat-container', 'data-layout': o.chat.layout || 'docked' }, [E(doc, 'iframe', { title: 'Saldi Assist' })], o.chat.rect || null)
    box.style.visibility = o.chat.open ? 'visible' : 'hidden'
    doc.body.appendChild(box)
  }
  if (o.v2) doc.body.appendChild(E(doc, 'script', { 'data-assistant': 'v2' }))

  const fdoc = o.frame ? o.frame.doc : kladdeliste()
  const path = '/asr_hj/' + (o.frame ? o.frame.route : 'finans/kladdeliste.php')
  const tour = { started: 0, stopped: 0 }
  const fwin = {
    document: fdoc,
    location: { pathname: path, href: 'https://ssl12.saldi.dk' + path + (o.frame && o.frame.query ? o.frame.query : '') },
    innerWidth: 1200,
    innerHeight: 800,
    listeners: {},
    addEventListener () {},
    removeEventListener () {},
    getComputedStyle: () => ({ overflowX: 'visible', overflowY: 'visible' }),
    focus () {},
    SaldiTour: { id: 'kladdel', count: () => 3, active: () => false, start () { tour.started++; return true }, stop () { tour.stopped++ } }
  }
  fwin.addEventListener = FNode.prototype.addEventListener
  fwin.removeEventListener = FNode.prototype.removeEventListener
  fwin.dispatchEvent = FNode.prototype.dispatchEvent
  fdoc.defaultView = fwin
  iframe.contentWindow = fwin

  const events = []
  const chat = { opened: 0, closed: 0, hidden: 0, shown: 0, statesAtOpen: [] }
  const runs = []
  const storageOf = (map) => ({ getItem: (k) => (map.has(k) ? map.get(k) : null), setItem: (k, v) => map.set(k, String(v)), removeItem: (k) => map.delete(k) })
  const store = new Map([...(o.pinned ? [['saldi.help.pinned', '1']] : []), ...(o.store || [])])
  const session = new Map(o.pinned ? [['saldi.help.open', '1']] : [])
  if (o.loading) doc.readyState = 'loading'
  const group = o.frame ? o.frame.group : 'kl'
  const texts = o.frame && o.frame.texts ? o.frame.texts
    : Object.fromEntries(KEYED.filter((f) => f[0].startsWith('help.' + group + '.') || f[0].startsWith('help.common.')).map((f) => [f[0], f[1]]))
  const win = {
    document: doc,
    location: { pathname: '/asr_hj/index/main.php', origin: 'https://ssl12.saldi.dk' },
    listeners: {},
    localStorage: storageOf(store),
    sessionStorage: storageOf(session),
    matchMedia: () => ({ matches: false }),
    CustomEvent,
    URL,
    URLSearchParams,
    Intl,
    console: { warn () {}, log () {} },
    setTimeout: o.fast ? (fn, ms) => setTimeout(fn, ms === 4000 ? 30 : ms) : setTimeout,
    clearTimeout,
    requestAnimationFrame: (fn) => setTimeout(() => fn(Date.now()), 0),
    cancelAnimationFrame: clearTimeout,
    performance: { now: () => Date.now() },
    fetch: (url) => Promise.resolve({ json: () => Promise.resolve(new RegExp('group=' + group + '$').test(url) ? { ok: true, group, lang: 'da', texts } : { ok: false }) }),
    SaldiAssist: { v2Ready: true, helpReady: o.helpReady, ...('helpConnected' in o ? { helpConnected: o.helpConnected } : {}), ...(o.shell ? { shellVersion: 1 } : {}) },
    SALDI_CHAT: {
      open () { chat.opened++; chat.statesAtOpen.push(events.filter((e) => e.type === 'saldi:help:state').length); if (box) box.style.visibility = 'visible' },
      close () { chat.closed++; if (box) box.style.visibility = 'hidden' }
    }
  }
  if (o.api) {
    win.SALDI_CHAT.isOpen = () => !!box && box.style.visibility === 'visible'
    win.SALDI_CHAT.layout = () => o.api.layout
  }
  if (o.hide) {
    win.SALDI_CHAT.hide = () => { chat.hidden++; if (box) box.style.visibility = 'hidden' }
    win.SALDI_CHAT.show = () => { chat.shown++; if (box) box.style.visibility = 'visible' }
  }
  if (o.registry) {
    win.topbarAssistPage = () => ({ help: o.registry, label: '', path: '', doc: fdoc })
    win.topbarAssistRun = (kind) => runs.push(kind)
  }
  win.window = win
  win.addEventListener = FNode.prototype.addEventListener
  win.removeEventListener = FNode.prototype.removeEventListener
  win.dispatchEvent = function (ev) { if (/^saldi:help:/.test(ev.type)) events.push({ type: ev.type, detail: JSON.parse(JSON.stringify(ev.detail)) }); return FNode.prototype.dispatchEvent.call(this, ev) }
  const context = vm.createContext(win)
  for (const s of o.extra ? [...SCRIPTS.slice(0, -2), o.extra, ...SCRIPTS.slice(-2)] : SCRIPTS) vm.runInContext(s, context)
  return { win, doc, fdoc, fwin, help, btn, newBtn, box, events, chat, tour, store, runs, H: win.SaldiHelp }
}
const of = (events, type) => events.filter((e) => e.type === type)
const key = (doc, k, target) => doc.dispatchEvent({ type: 'keydown', key: k, target: target || doc.body, preventDefault () { this.defaultPrevented = true }, defaultPrevented: false })
const act = (s, detail) => s.win.dispatchEvent(new CustomEvent('saldi:help:action', { detail }))
const press = (s, target) => s.fdoc.dispatchEvent({ type: 'pointerdown', isTrusted: true, target })
const frameMsg = (s, data) => s.win.dispatchEvent({ type: 'message', origin: 'https://ssl12.saldi.dk', source: s.fwin, data })
const SEEN = 'saldi-help.seen.finans/kladdeliste.php'

// ------------------------------------------------------------------ tests

test('fallback: without helpReady the panel opens as before and nothing goes to the chat', async () => {
  const s = shell({ helpReady: undefined, chat: { open: false } })
  await sleep(200)
  assert.equal(s.H.state().merged, false)
  s.H.toggle({ source: 'topbar' })
  assert.equal(s.H.isOpen(), true)
  assert.equal(s.help.hidden, false)
  await sleep(200)
  s.win.dispatchEvent(new CustomEvent('saldi:help:action', { detail: { action: 'tour' } }))
  assert.equal(s.tour.started, 0, 'an action is ignored without the merge')
  assert.deepEqual(of(s.events, 'saldi:help:state'), [])
  assert.deepEqual(of(s.events, 'saldi:help:open'), [])
  assert.equal(s.H.helpState(), null)
  // Vis mig from the panel's own task row: the callout's button is chrome text 8795 Ok, as in the chat (spec §21)
  const sm = s.help.querySelector('.hsb-showme')
  assert.ok(sm, 'a task row with Vis mig')
  sm.click()
  assert.equal(s.H.state().ring, true)
  assert.equal(s.fdoc.querySelector('#saldi-help-callout .shc-ok').textContent, 'Ok', 'the panel callout says Ok, not 5148 Forstået')
  assert.deepEqual(of(s.events, 'saldi:help:open'), [], 'still nothing goes to the chat')
})

test('merged: the state goes out on load and again when the texts arrive, only when it changed', async () => {
  const s = shell({ helpReady: true, chat: { open: false } })
  assert.equal(s.H.state().merged, true)
  await sleep(250)
  const states = of(s.events, 'saldi:help:state').map((e) => e.detail)
  assert.ok(states.length >= 2, 'before and after the texts: ' + states.length)
  assert.equal(states[0].hasContent, false)
  const last = states[states.length - 1]
  assert.equal(last.version, 1)
  assert.equal(last.route, 'finans/kladdeliste.php')
  assert.equal(last.hasContent, true)
  assert.deepEqual(last.tasks.map((t) => t.id), ['K11', 'K10', 'K13'])
  assert.equal(last.tasks[0].canShow, true, '#create-new is on the page')
  assert.deepEqual(last.tour, { steps: 3 })
  assert.equal(last.labels.helpButton, 'Hjælp til siden')
  // spec §25: the start screen's questions (the empty Kladdeliste's own order) and their labels from tekster.csv
  assert.deepEqual(last.questions, ['Q4', 'Q1', 'Q7'])
  assert.deepEqual(last.questions, last.search.filter((it) => it.kind === 'q').slice(0, 3).map((it) => it.id))
  assert.deepEqual([last.labels.questions, last.labels.askMore, last.labels.related], ['Spørgsmål om %s', 'Spørg Saldi AI om mere', 'Flere spørgsmål om siden'])
  assert.ok(!last.shortcuts.sections.some((sec) => sec.items.some((it) => it.keys === 'Esc')), 'Esc closes the panel, not the chat')
  assert.deepEqual(JSON.parse(JSON.stringify(s.H.helpState())), last)
  for (let i = 1; i < states.length; i++) assert.notDeepEqual(states[i], states[i - 1], 'no state is sent twice in a row')
  // a change in the page: the same state, so nothing new
  const n = of(s.events, 'saldi:help:state').length
  s.fdoc.dispatchEvent({ type: 'change', target: s.fdoc.body })
  await sleep(200)
  assert.equal(of(s.events, 'saldi:help:state').length, n)
})

test('merged: the Hjælp button, the classic cell, ? and F1 open the chat instead of the panel', async () => {
  const s = shell({ helpReady: true, chat: { open: false } })
  await sleep(250)
  s.H.toggle({ source: 'topbar', trigger: s.btn })
  assert.equal(s.H.isOpen(), false)
  assert.equal(s.help.hidden, true)
  let opens = of(s.events, 'saldi:help:open')
  assert.deepEqual(opens.map((e) => e.detail), [{ focus: 'help' }])
  // the state went out before the open, so the chat has the page help when it opens
  assert.ok(s.events.findIndex((e) => e.type === 'saldi:help:state') < s.events.findIndex((e) => e.type === 'saldi:help:open'))
  key(s.doc, '?')
  key(s.doc, 'F1')
  opens = of(s.events, 'saldi:help:open')
  assert.deepEqual(opens.map((e) => e.detail.focus), ['help', 'help', 'help'])
  assert.equal(s.H.isOpen(), false)
  // ? in a field types a question mark
  const input = E(s.doc, 'input', { type: 'text' })
  s.doc.body.appendChild(input)
  key(s.doc, '?', input)
  assert.equal(of(s.events, 'saldi:help:open').length, 3)
})

test('merged: F1 in a field opens the chat on that field\'s card, and its Vis mig rings the field', async () => {
  const s = shell({ helpReady: true, chat: { open: false } })
  await sleep(250)
  const field = s.fdoc.querySelector('input[name="search[kladdelst][kladdenote]"]')
  key(s.fdoc, 'F1', field)
  assert.deepEqual(of(s.events, 'saldi:help:open').map((e) => e.detail), [{ focus: 'field' }])
  const st = s.H.helpState()
  assert.equal(st.field.canShow, true)
  assert.ok(st.field.label !== '' && !st.field.label.includes('['))
  s.box.style.visibility = 'visible'   // the loader opened the chat; docked by default in this shell
  s.box.setAttribute('data-layout', 'docked')
  s.win.dispatchEvent(new CustomEvent('saldi:help:action', { detail: { action: 'showme', target: 'field' } }))
  assert.equal(s.H.state().ring, true)
  assert.equal(s.chat.closed, 0)
})

test('merged: Vis mig beside a docked chat rings the target and leaves the chat open', async () => {
  const s = shell({ helpReady: true, chat: { open: true, layout: 'docked' } })
  await sleep(250)
  s.win.dispatchEvent(new CustomEvent('saldi:help:action', { detail: { action: 'showme', target: 'task:K11' } }))
  assert.equal(s.H.state().ring, true)
  assert.equal(s.fdoc.getElementById('saldi-help-ring').getAttribute('data-on'), 'true')
  assert.equal(s.chat.closed, 0)
  assert.equal(s.help.hidden, true, 'no strip: the chat stands beside the page')
  assert.equal(s.H.state().back, false)
})

test('merged: Vis mig with the chat over the page closes it, and the strip leads back to it', async () => {
  const s = shell({ helpReady: true, chat: { open: true, layout: 'floating' } })
  await sleep(250)
  s.win.dispatchEvent(new CustomEvent('saldi:help:action', { detail: { action: 'showme', target: 'task:K11' } }))
  assert.equal(s.chat.closed, 1)
  assert.equal(s.H.state().ring, true)
  assert.equal(s.H.state().back, true)
  assert.equal(s.help.hidden, false)
  assert.ok(s.help.classList.contains('is-min'))
  assert.match(s.help.querySelector('.hc-strip').textContent, /Tilbage til hjælpen/)
  const before = of(s.events, 'saldi:help:open').length
  s.help.querySelector('.hsb-strip button').click()
  assert.equal(s.H.state().ring, false)
  assert.equal(s.H.state().back, false)
  assert.equal(s.help.hidden, true)
  assert.deepEqual(of(s.events, 'saldi:help:open').slice(before).map((e) => e.detail), [{ focus: 'help' }])
  // a press in the page ends the ring without reopening the chat
  s.box.style.visibility = 'visible'
  s.win.dispatchEvent(new CustomEvent('saldi:help:action', { detail: { action: 'showme', target: 'task:K11' } }))
  assert.equal(s.H.state().back, true)
  const n = of(s.events, 'saldi:help:open').length
  s.fdoc.dispatchEvent({ type: 'pointerdown', isTrusted: true, target: s.fdoc.body })
  assert.equal(s.H.state().ring, false)
  assert.equal(s.H.state().back, false)
  assert.equal(of(s.events, 'saldi:help:open').length, n)
})

test('merged: Rundvisning from the chat starts the tour; the fold choice is kept per page; anything else is ignored', async () => {
  const s = shell({ helpReady: true, chat: { open: true, layout: 'floating' } })
  await sleep(250)
  s.win.dispatchEvent(new CustomEvent('saldi:help:action', { detail: { action: 'tour' } }))
  assert.equal(s.tour.started, 1)
  assert.equal(s.chat.closed, 1)
  // the strip shows once the frame says the tour started; the tour's end goes back to the chat
  const msg = (data) => s.win.dispatchEvent({ type: 'message', origin: 'https://ssl12.saldi.dk', source: s.fwin, data })
  msg({ type: 'saldi:tour', id: 'kladdel', state: 'start', step: 1, of: 3 })
  assert.equal(s.H.state().back, true)
  assert.equal(s.help.hidden, false)
  const n = of(s.events, 'saldi:help:open').length
  msg({ type: 'saldi:tour', id: 'kladdel', state: 'end' })
  assert.equal(s.H.state().back, false)
  assert.equal(of(s.events, 'saldi:help:open').length, n + 1)

  s.win.dispatchEvent(new CustomEvent('saldi:help:action', { detail: { action: 'learn', open: false } }))
  assert.equal(s.store.get('saldi-help.learn.finans/kladdeliste.php'), '0')
  await sleep(200)
  assert.equal(s.H.helpState().learnOpen, false)

  const ring = () => s.H.state().ring
  for (const detail of [{ action: 'showme', target: '<b>' }, { action: 'showme', target: 'task:' + 'K'.repeat(30) }, { action: 'showme', target: 'task:constructor' },
    { action: 'learn', open: 'yes' }, { action: 'navigate', url: 'x' }, null, 'tour']) {
    s.win.dispatchEvent(new CustomEvent('saldi:help:action', { detail }))
    assert.equal(ring(), false, JSON.stringify(detail))
  }
  assert.equal(s.tour.started, 1)
  assert.equal(s.store.get('saldi-help.learn.finans/kladdeliste.php'), '0')
})

test('a pinned panel opens again after a shell reload only without the merge', async () => {
  // no Guide v2 script: as before, at once
  assert.equal(shell({ pinned: true }).H.isOpen(), true)
  // with the Guide v2 script, after the deferred loader ran: the panel without the merge, the chat's state with it
  const parse = (s) => { s.doc.readyState = 'interactive'; s.doc.dispatchEvent({ type: 'DOMContentLoaded' }) }
  const old = shell({ pinned: true, v2: true, loading: true })
  assert.equal(old.H.isOpen(), false)
  parse(old)
  assert.equal(old.H.isOpen(), true)
  const nu = shell({ pinned: true, v2: true, loading: true, chat: { open: false } })
  nu.win.SaldiAssist.helpReady = true   // what the new loader sets when it runs
  parse(nu)
  assert.equal(nu.H.isOpen(), false)
  assert.ok(of(nu.events, 'saldi:help:state').length >= 1, 'the first state goes out once the loader ran')
  await sleep(200)
})

test('merged: a tour ended with the mouse on its card goes back to the chat; its Spørg button and a press in the page do not', async () => {
  const s = shell({ helpReady: true, chat: { open: true, layout: 'floating' } })
  await sleep(250)
  const run = () => { s.box.style.visibility = 'visible'; act(s, { action: 'tour' }); frameMsg(s, { type: 'saldi:tour', id: 'kladdel', state: 'start', step: 1, of: 3 }) }
  run()
  assert.equal(s.H.state().back, true)
  let n = of(s.events, 'saldi:help:open').length
  press(s, s.fdoc.getElementById('tutorial-finish'))   // Færdig with the mouse
  frameMsg(s, { type: 'saldi:tour', id: 'kladdel', state: 'end' })
  assert.equal(s.H.state().back, false)
  assert.equal(s.help.hidden, true, 'the strip went')
  assert.deepEqual(of(s.events, 'saldi:help:open').slice(n).map((e) => e.detail), [{ focus: 'help' }])
  // the card's Spørg Saldi AI om dette: its own saldi:help-ask follows, so the end does not reopen
  run()
  n = of(s.events, 'saldi:help:open').length
  press(s, s.fdoc.getElementById('tutorial-ask'))
  frameMsg(s, { type: 'saldi:tour', id: 'kladdel', state: 'end' })
  assert.equal(s.H.state().back, false)
  assert.equal(of(s.events, 'saldi:help:open').length, n)
  // a press in the page: no way back (where the chat stood is unknown here, so it stays closed)
  run()
  n = of(s.events, 'saldi:help:open').length
  const opened = s.chat.opened
  press(s, s.fdoc.getElementById('far-spot'))
  frameMsg(s, { type: 'saldi:tour', id: 'kladdel', state: 'end' })
  assert.equal(s.H.state().back, false)
  assert.equal(of(s.events, 'saldi:help:open').length, n)
  assert.equal(s.chat.opened, opened)
})

test('merged: Vis mig on the field over the page keeps the field, so the way back lands on its card', async () => {
  const s = shell({ helpReady: true, chat: { open: false, layout: 'floating' } })
  await sleep(250)
  const field = s.fdoc.querySelector('input[name="search[kladdelst][kladdenote]"]')
  key(s.fdoc, 'F1', field)
  s.box.style.visibility = 'visible'   // the loader opened the chat
  act(s, { action: 'showme', target: 'field' })
  assert.equal(s.chat.closed, 1)
  assert.equal(s.H.state().back, true)
  const ok = s.fdoc.querySelector('#saldi-help-callout .shc-ok')
  s.fdoc.dispatchEvent({ type: 'focusin', target: ok })   // showMe() focused Ok with the chat closed
  assert.equal(s.H.state().lastField, 'search:kladdenote')
  const n = of(s.events, 'saldi:help:open').length
  ok.click()
  assert.deepEqual(of(s.events, 'saldi:help:open').slice(n).map((e) => e.detail), [{ focus: 'field' }])
  assert.ok(s.H.helpState().field && s.H.helpState().field.label !== '')
  assert.match(s.H.helpState().field.ask, /^Forklar mere om: /)
  // a button of the page itself still forgets the field while the chat is closed
  s.box.style.visibility = 'hidden'
  s.fdoc.dispatchEvent({ type: 'focusin', target: s.fdoc.getElementById('tutorial-finish').parentNode.parentNode.appendChild(E(s.fdoc, 'button', { id: 'page-btn' })) })
  assert.equal(s.H.state().lastField, null)
})

test('merged: only an open by the user is a view of the page; the way back after Vis mig is not', async () => {
  const s = shell({ helpReady: true, chat: { open: false, layout: 'floating' } })
  await sleep(250)
  assert.equal(s.store.get(SEEN), undefined)
  s.H.toggle({ source: 'topbar' })
  assert.equal(s.store.get(SEEN), '1')
  for (let i = 0; i < 3; i++) {
    s.box.style.visibility = 'visible'   // the loader opened the chat
    act(s, { action: 'showme', target: 'task:K11' })
    assert.equal(s.H.state().back, true)
    s.help.querySelector('.hsb-strip button').click()
  }
  assert.equal(s.store.get(SEEN), '1')
  assert.equal(s.H.helpState().learnOpen, false, 'Lær siden at kende is folded on every open (14 Nov 2026)')
})

test('merged: a page going away and a frame Saldi cannot read send route \'\' with the keys that work everywhere', async () => {
  const s = shell({ helpReady: true, chat: { open: true, layout: 'docked' } })
  await sleep(250)
  assert.equal(s.H.helpState().route, 'finans/kladdeliste.php')
  s.fwin.dispatchEvent({ type: 'pagehide' })
  const st = JSON.parse(JSON.stringify(s.H.helpState()))
  assert.equal(st.route, '')
  assert.equal(st.hasContent, false)
  assert.deepEqual([st.problem, st.field, st.tour], [null, null, null])
  assert.deepEqual([st.tasks, st.search, st.more], [[], [], []])
  assert.deepEqual(st.shortcuts.sections.map((x) => x.title), ['Overalt i Saldi'])
  assert.equal(st.labels.helpButton, 'Hjælp til siden')
  assert.equal(st.support.tel, '+4546902208')
  // Vis mig of the page that went away does nothing
  act(s, { action: 'showme', target: 'task:K11' })
  assert.equal(s.H.state().ring, false)
})

test('merged: a press outside where the chat stood brings it back without focus; under it or into a field it does not', async () => {
  const UNDER = { left: 0, top: 0, right: 420, bottom: 800, width: 420, height: 800 }
  const s = shell({ helpReady: true, chat: { open: true, layout: 'floating', rect: UNDER } })
  await sleep(250)
  const ring = () => { s.box.style.visibility = 'visible'; act(s, { action: 'showme', target: 'task:K11' }); assert.equal(s.H.state().back, true) }
  ring()
  let n = of(s.events, 'saldi:help:open').length
  let opened = s.chat.opened
  press(s, s.fdoc.getElementById('far-spot'))
  assert.equal(s.H.state().back, false)
  assert.equal(s.chat.opened, opened + 1, 'SALDI_CHAT.open() without the help focus')
  assert.equal(of(s.events, 'saldi:help:open').length, n)
  // under the chat: it stays closed
  ring()
  opened = s.chat.opened
  press(s, s.fdoc.getElementById('create-new'))
  assert.equal(s.H.state().back, false)
  assert.equal(s.chat.opened, opened)
  // into a field outside it: the user types there, the chat stays closed
  ring()
  opened = s.chat.opened
  n = of(s.events, 'saldi:help:open').length
  press(s, s.fdoc.getElementById('far-field'))
  assert.equal(s.chat.opened, opened)
  assert.equal(of(s.events, 'saldi:help:open').length, n)
})

test('merged: after Vis mig beside a docked chat, Ok puts focus back into the chat; a press in the page does not', async () => {
  const s = shell({ helpReady: true, chat: { open: true, layout: 'docked' } })
  await sleep(250)
  act(s, { action: 'showme', target: 'task:K11' })
  assert.equal(s.H.state().ring, true)
  assert.equal(s.fdoc.querySelector('#saldi-help-callout .shc-ok').textContent, 'Ok', 'the callout button is text 8795, not 5148 Forstået')
  assert.equal(s.chat.closed, 0)
  assert.equal(s.H.state().back, false)
  s.fdoc.querySelector('#saldi-help-callout .shc-ok').click()
  assert.equal(s.H.state().ring, false)
  assert.ok(s.doc.activeElement === s.box.querySelector('iframe'), 'focus is back in the chat')
  // a press in the page ends the ring with focus where the user put it
  act(s, { action: 'showme', target: 'task:K11' })
  s.doc.activeElement = s.doc.body
  press(s, s.fdoc.getElementById('far-spot'))
  assert.equal(s.H.state().ring, false)
  assert.ok(s.doc.activeElement !== s.box.querySelector('iframe'), 'focus stays where the press put it')
})

test('merged: the loader\'s own isOpen() and layout() come first, and the chat opened from its launcher ends the ring', async () => {
  const s = shell({ helpReady: true, chat: { open: true, layout: 'floating' }, api: { layout: 'docked' } })
  await sleep(250)
  act(s, { action: 'showme', target: 'task:K11' })
  assert.equal(s.chat.closed, 0, 'layout() says docked, the box\'s attribute is not read')
  s.fdoc.querySelector('#saldi-help-callout .shc-ok').click()
  const t = shell({ helpReady: true, chat: { open: true, layout: 'floating' } })
  await sleep(250)
  act(t, { action: 'showme', target: 'task:K11' })
  assert.equal(t.H.state().back, true)
  const n = of(t.events, 'saldi:help:open').length
  t.box.style.visibility = 'visible'   // the user clicked the chat's launcher
  t.win.dispatchEvent(new CustomEvent('saldi:chat:open', { detail: null }))
  assert.equal(t.H.state().ring, false)
  assert.equal(t.H.state().back, false)
  assert.equal(t.help.hidden, true)
  assert.equal(of(t.events, 'saldi:help:open').length, n, 'no second open')
})

test('merged: the topbar button opens the chat and says so; without the merge it controls the panel', async () => {
  const s = shell({ helpReady: true, chat: { open: false } })
  assert.equal(s.btn.getAttribute('aria-controls'), null)
  assert.equal(s.btn.getAttribute('aria-expanded'), null)
  assert.equal(s.btn.getAttribute('aria-haspopup'), 'dialog')
  assert.equal(s.btn.getAttribute('title'), 'Hjælp (?)')
  const f = shell({ helpReady: undefined, pinned: true })
  assert.equal(f.H.isOpen(), true)
  assert.equal(f.btn.getAttribute('aria-controls'), 'saldi-help')
  assert.equal(f.btn.getAttribute('aria-expanded'), 'true')
  assert.equal(f.btn.getAttribute('aria-haspopup'), null)
  // a loader that says whether the chat is open: the button points at the chat and follows its open and close
  const v = shell({ helpReady: true, chat: { open: false }, api: { layout: 'docked' } })
  assert.equal(v.btn.getAttribute('aria-controls'), 'saldi-chat-container')
  assert.equal(v.btn.getAttribute('aria-expanded'), 'false')
  v.box.style.visibility = 'visible'
  v.win.dispatchEvent(new CustomEvent('saldi:chat:open', { detail: { layout: 'docked' } }))
  assert.equal(v.btn.getAttribute('aria-expanded'), 'true')
  v.box.style.visibility = 'hidden'
  v.win.dispatchEvent(new CustomEvent('saldi:chat:close', { detail: { hidden: false } }))
  assert.equal(v.btn.getAttribute('aria-expanded'), 'false')
  await sleep(50)
})

test('merged: a loader that says helpConnected false and does not connect hands the help back to the panel', async () => {
  const s = shell({ helpReady: true, helpConnected: false, fast: true, chat: { open: false, layout: 'floating' } })
  await sleep(250)
  s.H.toggle({ source: 'topbar' })
  s.box.style.visibility = 'visible'   // the loader opened an empty chat
  assert.equal(s.H.isOpen(), false)
  await sleep(80)
  assert.equal(s.chat.closed, 1)
  assert.equal(s.H.isOpen(), true, 'the panel opened instead')
  assert.equal(s.H.state().merged, false)
  assert.equal(s.H.state().dead, true)
  assert.equal(s.btn.getAttribute('aria-controls'), 'saldi-help')
  s.H.close()
  // it connects after all: the next Hjælp goes to the chat again
  s.win.SaldiAssist.helpConnected = true
  s.win.dispatchEvent(new CustomEvent('saldi:help:ready', { detail: null }))
  assert.equal(s.H.state().merged, true)
  const n = of(s.events, 'saldi:help:open').length
  s.H.toggle({ source: 'topbar' })
  assert.equal(of(s.events, 'saldi:help:open').length, n + 1)
  // connected in time: nothing happens
  const t = shell({ helpReady: true, helpConnected: false, fast: true, chat: { open: false } })
  await sleep(250)
  t.H.toggle({ source: 'topbar' })
  t.win.SaldiAssist.helpConnected = true
  t.win.dispatchEvent(new CustomEvent('saldi:help:ready', { detail: null }))
  await sleep(80)
  assert.equal(t.H.isOpen(), false)
  assert.equal(t.H.state().merged, true)
})

test('merged: the actions of spec §19.9: back to the field, the page\'s own rows, and the chat\'s own view count', async () => {
  const s = shell({ helpReady: true, chat: { open: false, layout: 'floating' }, registry: { guide: 'https://x/vejledning.pdf', shortcuts: '[data-keys]' } })
  await sleep(250)
  const st = JSON.parse(JSON.stringify(s.H.helpState()))
  assert.deepEqual(st.more.map((m) => m.kind), ['guide', 'shortcuts'], 'the SaldiTour is the tour row, not a registered one')
  assert.equal(st.labels.back, 'Tilbage til samtalen')
  assert.equal(st.disclosure.title, 'Hvad får Saldi AI at vide?')
  // run: only a row the page has; a chat over the page closes first
  s.box.style.visibility = 'visible'
  act(s, { action: 'run', kind: 'navigate' })
  act(s, { action: 'run', kind: 'tour' })
  assert.deepEqual(s.runs, [])
  act(s, { action: 'run', kind: 'guide' })
  assert.deepEqual(s.runs, ['guide'])
  assert.equal(s.chat.closed, 1)
  // back: Esc in the chat after F1 returns to the field, and a chat over the page closes
  const field = s.fdoc.querySelector('input[name="search[kladdelst][kladdenote]"]')
  key(s.fdoc, 'F1', field)
  s.fdoc.activeElement = s.fdoc.body
  s.box.style.visibility = 'visible'
  act(s, { action: 'back' })
  assert.equal(s.chat.closed, 2)
  assert.ok(s.fdoc.activeElement === field, 'the caret is back in the field')
  // seen: once the chat says itself when it shows the help, the host's open is no longer a view
  const before = Number(s.store.get(SEEN))
  s.box.style.visibility = 'hidden'
  s.H.toggle({ source: 'topbar' })   // still counted by the host
  act(s, { action: 'seen' })        // the same view: not counted twice
  assert.equal(Number(s.store.get(SEEN)), before + 1)
  s.box.style.visibility = 'hidden'
  s.H.toggle({ source: 'topbar' })
  assert.equal(Number(s.store.get(SEEN)), before + 1, 'the open itself is not counted any more')
  act(s, { action: 'seen' })
  assert.equal(Number(s.store.get(SEEN)), before + 2)
})

test('merged: moving down a column of the same field builds the state once', async () => {
  const s = shell({ helpReady: true, chat: { open: true, layout: 'docked' } })
  await sleep(250)
  const a = s.fdoc.querySelector('input[name="search[kladdelst][kladdenote]"]')
  const b = E(s.fdoc, 'input', { type: 'text', name: 'search[kladdelst][kladdenote]' }, [], BOX)
  s.fdoc.body.appendChild(b)
  let builds = 0
  const build = s.win.SaldiHelpState.build
  s.win.SaldiHelpState.build = (input) => { builds++; return build(input) }
  press(s, a)   // the user acted: a focus the page makes by itself does not count
  s.fdoc.dispatchEvent({ type: 'focusin', target: a })
  await sleep(200)
  const n = builds
  assert.ok(n >= 1)
  s.fdoc.dispatchEvent({ type: 'focusin', target: b })
  await sleep(200)
  assert.equal(builds, n)
})

test('merged: a v1.1 loader gets the page\'s fields in the search, and Vis mig on field:<id> rings that field', async () => {
  const s = shell({ helpReady: true, helpConnected: true, chat: { open: true, layout: 'docked' } })
  await sleep(250)
  const items = s.H.helpState().search.filter((it) => it.kind === 'field')
  assert.ok(items.length >= 5, 'the Kladdeliste\'s fields: ' + items.length)
  const note = items.find((it) => it.id === 'search_kladdenote')
  assert.ok(note && note.canShow === true && note.title !== '', 'the note field is on the page')
  assert.ok(items.filter((it) => it.id !== 'search_kladdenote').every((it) => it.canShow === false), 'the other fields are not in this frame')
  act(s, { action: 'showme', target: 'field:search_kladdenote' })
  assert.equal(s.H.state().ring, true)
  assert.ok(s.fdoc.getElementById('saldi-help-ring') !== null)
  s.fdoc.querySelector('#saldi-help-callout .shc-ok').click()
  // a field that is not on the page, an unknown id and a malformed one: nothing
  for (const target of ['field:search_bogfort', 'field:nope', 'field:a-b']) {
    act(s, { action: 'showme', target })
    assert.equal(s.H.state().ring, false, target)
  }
  // a v1 loader (no helpConnected): no field items, because its widget would refuse the whole state
  const v1 = shell({ helpReady: true, chat: { open: true, layout: 'docked' } })
  await sleep(250)
  assert.ok(!v1.H.helpState().search.some((it) => it.kind === 'field'))
})

test('merged: Vis mig and a tour step the chat aside with the loader\'s hide(), and a press outside brings it back with show()', async () => {
  const UNDER = { left: 0, top: 0, right: 420, bottom: 800, width: 420, height: 800 }
  const s = shell({ helpReady: true, hide: true, chat: { open: true, layout: 'floating', rect: UNDER } })
  await sleep(250)
  act(s, { action: 'tour' })
  assert.deepEqual([s.chat.hidden, s.chat.closed], [1, 0], 'hidden, not closed: an open bug card in the chat stays')
  frameMsg(s, { type: 'saldi:tour', id: 'kladdel', state: 'start', step: 1, of: 3 })
  frameMsg(s, { type: 'saldi:tour', id: 'kladdel', state: 'end' })
  assert.equal(of(s.events, 'saldi:help:open').length, 1, 'the way back opens the chat on the help')
  s.box.style.visibility = 'visible'   // the loader opened it
  act(s, { action: 'showme', target: 'task:K11' })
  assert.deepEqual([s.chat.hidden, s.chat.closed], [2, 0])
  assert.equal(s.H.state().back, true)
  press(s, s.fdoc.getElementById('far-spot'))
  assert.deepEqual([s.chat.shown, s.chat.opened], [1, 0], 'show(), without the help focus')
  assert.equal(s.H.state().back, false)
  // a page row (run) and Esc back to the field leave the chat for the page: those close it
  s.box.style.visibility = 'visible'
  act(s, { action: 'back' })
  assert.deepEqual([s.chat.hidden, s.chat.closed], [2, 1])
})

test('merged: Oversigt hidden or shown in place is read again on saldi:dash-hidden', async () => {
  const fdoc = dashboard(true)
  const s = shell({ helpReady: true, chat: { open: false }, frame: { doc: fdoc, route: 'index/dashboard.php', group: 'fs' } })
  const hs = () => JSON.parse(JSON.stringify(s.H.helpState()))   // plain data out of the vm realm
  await sleep(250)
  let last = hs()
  assert.equal(last.route, 'index/dashboard.php')
  assert.equal(last.hasContent, true)
  assert.deepEqual(last.tasks.map((t) => t.id), ['FS10', 'FS7', 'FS9'], 'hidden: Vis oversigten igen first')
  assert.equal(last.tasks[0].canShow, true, '#showDash is on the page')
  // dashboard.js setHidden(false) shows the grid without a load or a change event, then posts saldi:dash-hidden
  fdoc.getElementById('grid').setAttribute('class', 'grid')
  frameMsg(s, { type: 'saldi:dash-hidden', hidden: false })
  await sleep(250)
  last = hs()
  assert.deepEqual(last.tasks.map((t) => t.id), ['FS2', 'FS1', 'FS7'], 'a new company again')
  // the message from anything but the frame is ignored
  fdoc.getElementById('grid').setAttribute('class', 'grid all-hidden')
  s.win.dispatchEvent({ type: 'message', origin: 'https://ssl12.saldi.dk', source: {}, data: { type: 'saldi:dash-hidden', hidden: true } })
  await sleep(250)
  assert.deepEqual(hs().tasks.map((t) => t.id), ['FS2', 'FS1', 'FS7'])
  frameMsg(s, { type: 'saldi:dash-hidden', hidden: true })
  await sleep(250)
  assert.deepEqual(hs().tasks.map((t) => t.id), ['FS10', 'FS7', 'FS9'])
})

test('merged: the CRM, which redraws in place, is read again after a click or a key (page.inPlace)', async () => {
  const fdoc = crmPage()
  const s = shell({ helpReady: true, chat: { open: false }, frame: { doc: fdoc, route: 'debitor/crm.php', group: 'dcrm', query: '?tab=follow' } })
  const hs = () => JSON.parse(JSON.stringify(s.H.helpState()))   // plain data out of the vm realm
  const can = (st, id) => st.tasks.find((t) => t.id === id).canShow
  const click = (target) => s.fdoc.dispatchEvent({ type: 'click', isTrusted: true, target, detail: 1, preventDefault () {}, stopPropagation () {} })
  const keyup = (k) => s.fdoc.dispatchEvent({ type: 'keyup', key: k, target: s.fdoc.body })
  await sleep(250)
  let last = hs()
  assert.equal(last.route, 'debitor/crm.php')
  assert.equal(last.hasContent, true)
  assert.deepEqual(last.tasks.map((t) => t.id), ['CR2', 'CR4', 'CR1'], 'Opfølgning')
  assert.match(last.about[0], /^Opfølgning viser/)
  // crm.js: a tab click sets aria-selected and redraws (S.tab = b.dataset.tab; renderAll()), no load and no change event
  const tab = (id) => fdoc.querySelector('#tabs [data-tab="' + id + '"]')
  tab('follow').setAttribute('aria-selected', 'false')
  tab('cust').setAttribute('aria-selected', 'true')
  click(tab('cust'))
  await sleep(250)
  last = hs()
  assert.deepEqual(last.tasks.map((t) => t.id), ['CR3', 'CR1', 'CR2'], 'Kunder after the tab click')
  assert.match(last.about[0], /^Kunder viser/)
  assert.equal(can(last, 'CR3'), true, 'the search')
  assert.equal(can(last, 'CR1'), false, 'no composer outside the drawer')
  // openDrawer(): the drawer with the customer's name and the composer, on a click on the row
  const drawer = fdoc.getElementById('drawer')
  drawer.appendChild(E(fdoc, 'h2', { class: 'sk-drawer-t', id: 'dTitle' }, [fdoc.createTextNode('Fjordlys ApS')]))
  drawer.appendChild(E(fdoc, 'div', { class: 'crm-comp', id: 'ccomp' }, [E(fdoc, 'textarea', { id: 'cNote' }, [], BOX), E(fdoc, 'div', { id: 'cchips' }, [], BOX)]))
  drawer.setAttribute('class', drawer.getAttribute('class') + ' open')
  click(fdoc.querySelector('[data-open="4"]'))
  await sleep(250)
  last = hs()
  assert.equal(last.title, 'CRM: Fjordlys ApS')
  assert.deepEqual(last.tasks.map((t) => t.id), ['CR1', 'CR2', 'CR4'], 'the drawer')
  assert.equal(can(last, 'CR1'), true, '#cNote in the open drawer')
  // Esc: closeDrawer() (the content stays), read again on the key
  drawer.setAttribute('class', 'sk-drawer drawer crm-drawer')
  keyup('Escape')
  await sleep(250)
  last = hs()
  assert.equal(last.title, 'CRM')
  assert.deepEqual(last.tasks.map((t) => t.id), ['CR3', 'CR1', 'CR2'])
  // Ny note with the customer picked: the dialog over the page, with the composer n (#scrim.open)
  const scrim = fdoc.getElementById('scrim')
  fdoc.getElementById('dlg').appendChild(E(fdoc, 'div', { class: 'crm-comp', id: 'ncomp' }, [E(fdoc, 'textarea', { id: 'nNote' }, [], BOX), E(fdoc, 'div', { id: 'nchips' }, [], BOX)]))
  scrim.setAttribute('class', 'sk-modal open')
  click(fdoc.getElementById('newBtn'))
  await sleep(250)
  last = hs()
  assert.equal(can(last, 'CR3'), false, 'the search is under the dialog')
  assert.equal(can(last, 'CR1'), true, '#nNote in the dialog')
  assert.equal(can(last, 'CR2'), true, '#nchips in the dialog')
  // another key does nothing; Esc closes the dialog (closeDlg()) and the page is read again
  scrim.setAttribute('class', 'sk-modal')
  keyup('a')
  await sleep(250)
  assert.equal(can(hs(), 'CR3'), false, 'not read again on a key the CRM does not use')
  keyup('Escape')
  await sleep(250)
  last = hs()
  assert.equal(can(last, 'CR3'), true)
  assert.equal(can(last, 'CR1'), false)
})

test('merged: the CRM drawer opened after a fetch ("crmdrawer") and typing in its search are read again', async () => {
  const fdoc = crmPage()
  const s = shell({ helpReady: true, chat: { open: false }, frame: { doc: fdoc, route: 'debitor/crm.php', group: 'dcrm', query: '?tab=follow' } })
  const hs = () => JSON.parse(JSON.stringify(s.H.helpState()))
  await sleep(250)
  assert.equal(hs().title, 'CRM')
  // the server search's pick: openOutside() awaits the customer, then openDrawer() says "crmdrawer"; no click or key follows
  const drawer = fdoc.getElementById('drawer')
  drawer.appendChild(E(fdoc, 'h2', { class: 'sk-drawer-t', id: 'dTitle' }, [fdoc.createTextNode('Fjordlys Design A/S')]))
  drawer.setAttribute('class', drawer.getAttribute('class') + ' open')
  await sleep(250)
  assert.equal(hs().title, 'CRM', 'nothing told the help yet')
  fdoc.dispatchEvent({ type: 'crmdrawer', detail: { cid: 9 } })
  await sleep(250)
  assert.equal(hs().title, 'CRM: Fjordlys Design A/S')
  drawer.setAttribute('class', 'sk-drawer drawer crm-drawer')
  fdoc.dispatchEvent({ type: 'keyup', key: 'Escape', target: fdoc.body })
  await sleep(250)
  // typing in #q filters every tab without a change event: the flag soegning follows once the typing pauses
  const q = fdoc.getElementById('q')
  const n = of(s.events, 'saldi:help:state').length
  q.value = 'Fj'
  fdoc.dispatchEvent({ type: 'input', target: q })
  q.value = 'Fjord'
  fdoc.dispatchEvent({ type: 'input', target: q })
  await sleep(250)
  let last = hs()
  assert.equal(of(s.events, 'saldi:help:state').length, n + 1, 'one state for the pause, not one per key')
  assert.equal(last.about.length, 2)
  assert.match(last.about[1], /^Listerne viser kun det, der passer/)
  q.value = 'Fjordlys'
  fdoc.dispatchEvent({ type: 'input', target: q })
  await sleep(250)
  assert.equal(of(s.events, 'saldi:help:state').length, n + 1, 'more text, the same state: nothing new')
  q.value = ''
  fdoc.dispatchEvent({ type: 'input', target: q })
  await sleep(250)
  last = hs()
  assert.equal(last.about.length, 1, 'the field emptied')
})

test('panel: "mine" finds the CRM\'s view Mine, and stays a stop word on other pages', async () => {
  const typed = async (s, text) => {
    const input = s.help.querySelector('.hc-input')
    input.value = text
    input.dispatchEvent({ type: 'input', target: input })
    await sleep(50)
    return s.help.querySelector('.hc-results').textContent
  }
  const crm = shell({ helpReady: undefined, chat: { open: false }, frame: { doc: crmPage(), route: 'debitor/crm.php', group: 'dcrm', query: '?tab=follow' } })
  await sleep(200)
  crm.H.toggle({ source: 'topbar' })
  await sleep(200)
  assert.match(await typed(crm, 'mine'), /Se kun dine egne opfølgninger og kunder/)
  assert.match(await typed(crm, 'vis mine kunder'), /Se kun dine egne opfølgninger og kunder/)
  assert.ok(crm.H.helpState() === null, 'the panel, not the chat')
  const kl = shell({ helpReady: undefined, chat: { open: false } })
  await sleep(200)
  kl.H.toggle({ source: 'topbar' })
  await sleep(200)
  const before = await typed(kl, 'kladde')
  assert.ok(kl.help.querySelector('.hc-results .hc-taskrow'), 'the Kladdeliste search finds its tasks')
  assert.notEqual(before, '')
  await typed(kl, 'mine')
  assert.equal(kl.help.querySelector('.hc-results .hc-taskrow'), null, 'mine is a stop word on the Kladdeliste')
})

test('merged: the CRM\'s ownWords keep "mine" in the chat\'s vocabulary of CR6', async () => {
  const s = shell({ helpReady: true, chat: { open: false }, frame: { doc: crmPage(), route: 'debitor/crm.php', group: 'dcrm', query: '?tab=follow' } })
  await sleep(250)
  const cr6 = s.H.helpState().search.find((it) => it.kind === 'task' && it.id === 'CR6')
  assert.ok(cr6.words.includes('mine'), 'the why text "Mine viser kun det, du er ansvarlig for"')
})

test('merged: a page that does not redraw in place is not read again on a click', async () => {
  const s = shell({ helpReady: true, chat: { open: false } })
  await sleep(250)
  const n = of(s.events, 'saldi:help:state').length
  const create = s.fdoc.getElementById('create-new')
  create.rect = null   // hidden without a load or a change event: K11 would lose its Vis mig if the page were read again
  s.fdoc.dispatchEvent({ type: 'click', isTrusted: true, target: s.fdoc.getElementById('far-spot'), detail: 1, preventDefault () {}, stopPropagation () {} })
  await sleep(250)
  assert.equal(of(s.events, 'saldi:help:state').length, n, 'no new state: the Kladdeliste is not inPlace')
})

test('merged: the topbar symbol marked for the chat keeps the state the loader gave it; without the mark or the merge the help sets it', async () => {
  const sym = (s) => s.btn.querySelector('.ai-avatar')
  const s = shell({ helpReady: true, chat: { open: false }, symbolAttr: true })
  await sleep(250)
  sym(s).setAttribute('data-ai-state', 'signal')   // the loader: an answer came while the chat was closed
  sym(s).setAttribute('data-here', 'false')
  s.H.setEngineState('thinking')
  s.H.setEngineState(null)
  assert.equal(sym(s).getAttribute('data-ai-state'), 'signal')
  assert.equal(sym(s).getAttribute('data-here'), 'false')
  const t = shell({ helpReady: true, chat: { open: false } })
  await sleep(250)
  sym(t).setAttribute('data-ai-state', 'signal')
  t.H.setEngineState(null)
  assert.equal(sym(t).getAttribute('data-ai-state'), 'ready', 'no mark: the help keeps the symbol')
  const u = shell({ symbolAttr: true })
  await sleep(250)
  sym(u).setAttribute('data-ai-state', 'signal')
  u.H.setEngineState(null)
  assert.equal(sym(u).getAttribute('data-ai-state'), 'ready', 'no merge: the help keeps the symbol')
})

// ------------------------------------------------------------------ Saldi Assist in the shell (spec §23)

test('shell: Hjælp opens the chat where the user left off, without the page help over it, and puts an open chat away', async () => {
  // Lær siden at kende is folded on every open (14 Nov 2026); the open is the page's fourth view
  const s = shell({ helpReady: true, shell: true, api: { layout: 'docked' }, chat: { open: false, layout: 'docked' }, store: [[SEEN, '3']] })
  await sleep(250)
  const before = of(s.events, 'saldi:help:state').length
  assert.equal(s.H.helpState().learnOpen, false)
  s.H.toggle({ source: 'topbar', trigger: s.btn })
  assert.equal(s.chat.opened, 1, 'SALDI_CHAT.open() once')
  assert.deepEqual(of(s.events, 'saldi:help:open'), [], 'no page help sheet over a conversation')
  // the state did not change (Lær siden at kende is folded already), so the chat has it and nothing new goes out
  assert.equal(of(s.events, 'saldi:help:state').length, before, 'no unchanged state again')
  assert.deepEqual(s.chat.statesAtOpen, [before], 'the chat had the state when it opened')
  assert.equal(s.H.helpState().learnOpen, false)
  assert.equal(s.store.get(SEEN), '4', 'the open is a view of the page')
  assert.equal(s.H.isOpen(), false)
  assert.equal(s.help.hidden, true, 'the panel stays shut')
  // the button is a toggle: the open chat goes away (minimised by a loader with minimize(), else closed as here)
  s.H.toggle({ source: 'topbar', trigger: s.btn })
  assert.equal(s.chat.closed, 1)
  assert.equal(s.chat.opened, 1)
  assert.deepEqual(of(s.events, 'saldi:help:open'), [])
  // ?, F1, the classic cell and the API still open the page help (spec §19)
  key(s.doc, '?')
  key(s.doc, 'F1')
  s.H.toggle({ source: 'classic' })
  s.H.toggle()
  assert.deepEqual(of(s.events, 'saldi:help:open').map((e) => e.detail.focus), ['help', 'help', 'help', 'help'])
  assert.equal(s.chat.opened, 1, 'those go through saldi:help:open, not SALDI_CHAT.open()')
  // a loader without shellVersion: Hjælp opens the page help as in §19, also with the button in the topbar
  const t = shell({ helpReady: true, api: { layout: 'docked' }, chat: { open: false, layout: 'docked' } })
  await sleep(250)
  t.H.toggle({ source: 'topbar', trigger: t.btn })
  assert.deepEqual(of(t.events, 'saldi:help:open').map((e) => e.detail), [{ focus: 'help' }])
  assert.equal(t.chat.opened, 0)
  // without the merge (no helpReady) shellVersion changes nothing: the panel opens
  const u = shell({ shell: true, chat: { open: false } })
  await sleep(250)
  u.H.toggle({ source: 'topbar', trigger: u.btn })
  assert.equal(u.H.isOpen(), true)
  assert.equal(u.chat.opened, 0)
})

test('shell: a ring left with the chat closed ends on Hjælp and the chat opens; a way back still leads to the card', async () => {
  const s = shell({ helpReady: true, shell: true, api: { layout: 'docked' }, chat: { open: true, layout: 'docked' } })
  await sleep(250)
  act(s, { action: 'showme', target: 'task:K11' })
  assert.equal(s.H.state().ring, true)
  assert.equal(s.H.state().back, false, 'beside a docked chat there is no way back')
  s.box.style.visibility = 'hidden'   // the user closed the chat with its X; the ring stays
  s.H.toggle({ source: 'topbar', trigger: s.btn })
  assert.equal(s.H.state().ring, false, 'the ring ended')
  assert.equal(s.chat.opened, 1)
  assert.deepEqual(of(s.events, 'saldi:help:open'), [])
  // a ring with the floating chat stepped aside: Hjælp goes back to the help as before
  const t = shell({ helpReady: true, shell: true, api: { layout: 'floating' }, chat: { open: true, layout: 'floating' } })
  await sleep(250)
  act(t, { action: 'showme', target: 'task:K11' })
  assert.equal(t.H.state().back, true)
  t.H.toggle({ source: 'topbar', trigger: t.btn })
  assert.equal(t.H.state().ring, false)
  assert.equal(t.H.state().back, false)
  assert.deepEqual(of(t.events, 'saldi:help:open').map((e) => e.detail), [{ focus: 'help' }])
  assert.equal(t.chat.opened, 0)
})

test('shell: every chat open is watched; a chat that never connects hides "+ Ny chat" by data-saldi-help-dead, not hidden', async () => {
  // "+ Ny chat" opened the chat (the loader's own open: only saldi:chat:open reaches Saldi)
  const s = shell({ helpReady: true, helpConnected: false, fast: true, shell: true, api: { layout: 'docked' }, chat: { open: false, layout: 'docked' } })
  await sleep(250)
  s.box.style.visibility = 'visible'
  s.win.dispatchEvent(new CustomEvent('saldi:chat:open', { detail: { layout: 'docked' } }))
  await sleep(80)
  assert.equal(s.chat.closed, 1, 'the empty chat closed')
  assert.equal(s.H.isOpen(), true, 'the panel opened instead')
  assert.equal(s.H.state().dead, true)
  assert.equal(s.newBtn.getAttribute('data-saldi-help-dead'), '')
  assert.equal(s.newBtn.hidden, false, 'hidden belongs to the loader')
  // it connects after all: the button comes back
  s.H.close()
  s.win.SaldiAssist.helpConnected = true
  s.win.dispatchEvent(new CustomEvent('saldi:help:ready', { detail: null }))
  assert.equal(s.H.state().dead, false)
  assert.equal(s.newBtn.hasAttribute('data-saldi-help-dead'), false)
  assert.equal(s.newBtn.hidden, false)
  // a button the loader hid (an older chat window) stays hidden through both
  const t = shell({ helpReady: true, helpConnected: false, fast: true, shell: { hidden: true }, api: { layout: 'docked' }, chat: { open: false, layout: 'docked' } })
  await sleep(250)
  t.H.toggle({ source: 'topbar', trigger: t.btn })   // Hjælp's own open is watched as before
  await sleep(80)
  assert.equal(t.H.state().dead, true)
  assert.equal(t.newBtn.hasAttribute('data-saldi-help-dead'), true)
  assert.equal(t.newBtn.hidden, true)
  t.win.SaldiAssist.helpConnected = true
  t.win.dispatchEvent(new CustomEvent('saldi:help:ready', { detail: null }))
  assert.equal(t.newBtn.hasAttribute('data-saldi-help-dead'), false)
  assert.equal(t.newBtn.hidden, true)
  // without shellVersion a chat opened elsewhere is not watched (spec §19 as before)
  const u = shell({ helpReady: true, helpConnected: false, fast: true, api: { layout: 'docked' }, chat: { open: false, layout: 'docked' } })
  await sleep(250)
  u.box.style.visibility = 'visible'
  u.win.dispatchEvent(new CustomEvent('saldi:chat:open', { detail: { layout: 'docked' } }))
  await sleep(80)
  assert.equal(u.H.state().dead, false)
  assert.equal(u.chat.closed, 0)
})

/** A settings section in the frame (systemdata/settingsSection.php:640-780): Tilbage, the title, the form with its save bar and one Gendan. */
function settingsSection (title) {
  const doc = new FDocument()
  doc.body.appendChild(E(doc, 'a', { class: 'sk-btn sk-btn-sm sk-back', href: '../systemdata/settings.php', 'data-back': '', accesskey: 'l' }, [], BOX))
  doc.body.appendChild(E(doc, 'h1', {}, [doc.createTextNode(title), E(doc, 'span', { class: 'st-qwrap' }, [E(doc, 'button', { type: 'button', 'data-section-help': '' }, [doc.createTextNode('?')])])]))
  const bar = E(doc, 'footer', { class: 'st-savebar', id: 'st-savebar' }, [E(doc, 'button', { type: 'button', id: 'st-undo' }, [], BOX), E(doc, 'button', { type: 'submit', id: 'st-save' }, [], BOX)])
  doc.body.appendChild(E(doc, 'form', { class: 'st-form', id: 'st-form', method: 'post', action: 'settingsSection.php' }, [bar]))
  doc.body.appendChild(E(doc, 'aside', { class: 'st-hist' }, [E(doc, 'button', { type: 'button', class: 'st-tl', 'data-restore': '7', 'data-value': '25' }, [], BOX)]))
  return doc
}
// The page of one section, registered in the dispatcher's variants as addSection() does, under a key no section has
// (zz_test.*), so the authors' sections never meet it; a test group, its texts below.
const SECTION_PAGE = `(function () {
  var P = window.SaldiHelpContent.pages['systemdata/settingsSection.php'];
  P.variants['zz_test.section'] = {
    group: 'zzmoms', states: ['aaben'], flags: [],
    name: function () { return 'help.zzmoms.name'; },
    detect: function () { return { state: 'aaben', flags: {}, data: {} }; },
    about: function () { return ['help.zzmoms.about']; },
    tasks: { M1: { title: 'help.zzmoms.task.m1.title', why: 'help.zzmoms.task.m1.why', steps: ['help.zzmoms.task.m1.step1'], show: { target: '#st-save', label: 'help.zzmoms.task.m1.show', kind: 'button' } } },
    order: function () { return ['M1']; }, fields: {}, shortcuts: [], questions: {},
    questionOrder: function () { return []; }, signal: function () { return false; }
  };
})();`
const SECTION_TEXTS = {
  'help.zzmoms.name': 'Moms', 'help.zzmoms.about': 'Momskoderne styrer momsen på hver postering.', 'help.zzmoms.task.m1.title': 'Opret en momskode',
  'help.zzmoms.task.m1.why': 'Hver sats har sin egen kode.', 'help.zzmoms.task.m1.step1': 'Tryk **Gem ændringer**.', 'help.zzmoms.task.m1.show': 'Gem ændringer'
}

test('merged: a settings section with a page of its own shows that page and loads its group; another section the generic page (spec §24)', async () => {
  const route = 'systemdata/settingsSection.php'
  const s = shell({ helpReady: true, chat: { open: false }, extra: SECTION_PAGE, frame: { doc: settingsSection('Moms'), route, query: '?s=zz_test.section', group: 'zzmoms', texts: SECTION_TEXTS } })
  await sleep(250)
  const last = JSON.parse(JSON.stringify(s.H.helpState()))
  assert.equal(last.route, route, 'the chat gets the path, as for every page')
  assert.equal(last.hasContent, true, 'the texts of the section\'s group arrived')
  assert.equal(last.title, 'Moms')
  assert.deepEqual(last.about, ['Momskoderne styrer momsen på hver postering.'])
  assert.deepEqual(last.tasks.map((t) => t.id), ['M1'])
  assert.equal(last.tasks[0].canShow, true, '#st-save is on the page')
  const st = s.H.state()
  assert.equal(st.group, 'zzmoms')
  assert.equal(st.pageId, route + '?zz_test.section')
  assert.equal(st.pageState, 'aaben')

  // the same route on a section without a page of its own: the generic page with the texts of sset
  const g = shell({ helpReady: true, chat: { open: false }, extra: SECTION_PAGE, frame: { doc: settingsSection('Valuta'), route, query: '?s=zz_test.other', group: 'sset' } })
  await sleep(250)
  const gen = JSON.parse(JSON.stringify(g.H.helpState()))
  assert.equal(gen.hasContent, true)
  assert.equal(gen.title, 'Indstillinger: Valuta', 'the heading without the ? beside it')
  assert.deepEqual(gen.tasks.map((t) => t.id), ['SS1', 'SS2', 'SS3'])
  assert.equal(g.H.state().group, 'sset')
  assert.equal(g.H.state().pageId, route, 'the generic page is the route itself')
  assert.equal(g.H.state().pageState, 'formular')
})
