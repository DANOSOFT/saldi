// --- tests/includes/ui/saldiInbox.test.mjs --- ai/mcp-assistant --- 2026.10.05
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// javascript/saldi-inbox.js without a browser: MCP token caching and de-duplication,
// the reject path (preview -> digest-bound reject with CSRF + tenant hash), input
// validation and the inbox badge. The approval modal runs against a small DOM stub:
// only trusted, armed clicks or key presses decide; Afvis and Godkend post the
// reviewed digest (and the note); Esc cancels; decided items show no decision buttons.
// The look is verified in the browser walkthrough.
// Run on the host: node --test tests/includes/ui/
//
// 20261002 CL/LH Created.
// 20261005 CDX/LH Cover bounded status previews, tenant checks and inaccessible items.
// 20261007 CL/LH Cover the v2 approval modal: trusted/armed decisions, Afvis, note, G/A/N keys, Esc.
// 20261007 CL/LH Esc in the open note folds the note first; the title says "Afgjort forslag" once decided.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const source = readFileSync(fileURLToPath(new URL('../../../javascript/saldi-inbox.js', import.meta.url)), 'utf8')
const TENANT = 'a'.repeat(32)

function load (respond) {
  const requests = []
  const badge = { textContent: '', hidden: true, attrs: {}, setAttribute (k, v) { this.attrs[k] = v } }
  const label = { textContent: '' }
  const document = {
    getElementById: (id) => id === 'saldi-inbox-config'
      ? { textContent: JSON.stringify({ csrf: 'csrf-1', tenantHash: TENANT, texts: { openCount: '{n} åbne' } }) }
      : null,
    querySelectorAll: (selector) => selector === '[data-saldi-inbox-badge]' ? [badge] : (selector === '[data-saldi-inbox-badge-label]' ? [label] : []),
    addEventListener: () => {}
  }
  const window = {
    SaldiAssist: { appVersion: '5.0.0' },
    fetch: (url, init) => {
      const body = JSON.parse(init.body)
      requests.push({ url, body, headers: init.headers, credentials: init.credentials })
      const [status, json] = respond(url, body, requests.length)
      return Promise.resolve({ status, json: () => Promise.resolve(json) })
    }
  }
  vm.runInNewContext(source, { window, document, AbortController, setTimeout, clearTimeout, Date, JSON, Promise, Math, isFinite, Object, Array, String })
  return { window, requests, badge, label }
}

const future = () => new Date(Date.now() + 8 * 3600 * 1000).toISOString()

test('the shell API is added to the existing SaldiAssist object', () => {
  const { window } = load(() => [200, {}])
  assert.equal(window.SaldiAssist.appVersion, '5.0.0')
  for (const name of ['getMcpToken', 'getInboxItems', 'decideInboxItem', 'openInboxItem', 'refreshInboxBadge']) {
    assert.equal(typeof window.SaldiAssist[name], 'function', name)
  }
})

test('status refresh previews each item with CSRF and tenant checks and keeps null failures', async () => {
  const card = { item_id: 5, tenant_hash: TENANT, status: 'undone' }
  const { window, requests } = load((url, body) => {
    if (body.item_id === 5) return [200, { ok: true, item: { id: 5, tenant_hash: TENANT, status: 'undone', can_undo: false, card } }]
    if (body.item_id === 6) return [200, { ok: true, item: { id: 6, tenant_hash: 'c'.repeat(32), status: 'applied', card } }]
    if (body.item_id === 7) return [404, { ok: false, error: { code: 'NOT_FOUND' } }]
    return [502, null]
  })
  assert.equal(JSON.stringify(await window.SaldiAssist.getInboxItems([5, 6, 7, 8])), JSON.stringify([
    { item_id: 5, status: 'undone', can_undo: false, card }, null, null, null
  ]))
  assert.equal(requests.length, 4)
  for (const [index, request] of requests.entries()) {
    assert.equal(request.url, '../inbox/action.php')
    assert.deepEqual(request.body, { tenant_hash: TENANT, op: 'preview', item_id: index + 5 })
    assert.equal(request.headers['X-Saldi-Csrf'], 'csrf-1')
    assert.equal(request.credentials, 'same-origin')
  }
})

test('status refresh validates ids and the ten-item bound before making any requests', async () => {
  const { window, requests } = load(() => [200, {}])
  for (const ids of [null, '5', [0], [-1], [1.5], ['5'], [Infinity], Array.from({ length: 11 }, (_, i) => i + 1)]) {
    assert.equal(JSON.stringify(await window.SaldiAssist.getInboxItems(ids)), '[]')
  }
  assert.equal(requests.length, 0)
  assert.equal((await window.SaldiAssist.getInboxItems(Array.from({ length: 10 }, (_, i) => i + 1))).length, 10)
  assert.equal(requests.length, 10)
})

test('status refresh refuses mismatched item ids and card companies', async () => {
  for (const overrides of [{ id: 99 }, { card: { item_id: 5, tenant_hash: 'b'.repeat(32) } }, { card: { item_id: 99, tenant_hash: TENANT } }]) {
    const { window } = load(() => [200, { ok: true, item: {
      id: 5, tenant_hash: TENANT, status: 'applied', card: { item_id: 5, tenant_hash: TENANT }, ...overrides
    } }])
    assert.equal(JSON.stringify(await window.SaldiAssist.getInboxItems([5])), '[null]')
  }
})

test('getMcpToken de-duplicates, caches until expiry - 5 min and can be forced', async () => {
  const { window, requests } = load(() => [200, { enabled: true, token: 'smcp1.local.1.2.x', expires_at: future(), install_id: 'local', tenant_hash: TENANT, user_hash: 'b'.repeat(32) }])
  const [a, b] = await Promise.all([window.SaldiAssist.getMcpToken(), window.SaldiAssist.getMcpToken()])
  assert.equal(requests.length, 1)
  assert.equal(requests[0].url, '../includes/saldi_assist_mcp_token.php')
  assert.equal(requests[0].credentials, 'same-origin')
  assert.equal(a.token, 'smcp1.local.1.2.x')
  assert.equal(a.tenantHash, TENANT)
  assert.equal(a.installId, 'local')
  assert.deepEqual(a, b)
  await window.SaldiAssist.getMcpToken()
  assert.equal(requests.length, 1)
  await window.SaldiAssist.getMcpToken({ refresh: true })
  assert.equal(requests.length, 2)
})

test('a token about to expire is handed out but not cached', async () => {
  const { window, requests } = load(() => [200, { enabled: true, token: 't', expires_at: new Date(Date.now() + 60 * 1000).toISOString(), install_id: 'local', tenant_hash: TENANT }])
  await window.SaldiAssist.getMcpToken()
  await window.SaldiAssist.getMcpToken()
  assert.equal(requests.length, 2)
})

test('a token for another company is refused and disabled answers are remembered', async () => {
  const other = load(() => [200, { enabled: true, token: 't', expires_at: future(), tenant_hash: 'c'.repeat(32) }])
  assert.equal(await other.window.SaldiAssist.getMcpToken(), null)
  const off = load(() => [200, { enabled: false }])
  assert.equal(JSON.stringify(await off.window.SaldiAssist.getMcpToken()), '{"enabled":false}')
  await off.window.SaldiAssist.getMcpToken()
  assert.equal(off.requests.length, 1)
  const broken = load(() => [500, null])
  assert.equal(await broken.window.SaldiAssist.getMcpToken(), null)
})

test('reject previews first, then posts the reviewed digest with CSRF and the shell tenant hash', async () => {
  const digest = 'd'.repeat(64)
  const { window, requests, badge } = load((url, body) => {
    if (body.op === 'preview') return [200, { ok: true, item: { id: 5, status: 'open', digest, tenant_hash: TENANT } }]
    if (body.op === 'reject') return [200, { ok: true, item: { id: 5, status: 'rejected' } }]
    if (body.op === 'count') return [200, { ok: true, count: 3 }]
    return [400, { ok: false, error: { code: 'VALIDATION_ERROR', message_da: 'x' } }]
  })
  const result = await window.SaldiAssist.decideInboxItem(5, 'reject')
  assert.equal(result.ok, true)
  assert.deepEqual(requests.map((r) => r.body.op), ['preview', 'reject', 'count'])
  assert.deepEqual(requests[1].body, { tenant_hash: TENANT, op: 'reject', item_id: 5, digest })
  assert.equal(requests[1].url, '../inbox/action.php')
  assert.equal(requests[1].headers['X-Saldi-Csrf'], 'csrf-1')
  await new Promise((resolve) => setTimeout(resolve, 0))
  assert.equal(badge.textContent, '3')
  assert.equal(badge.hidden, false)
})

test('reject stops when the preview belongs to another company or fails', async () => {
  const moved = load((url, body) => [200, { ok: true, item: { id: 5, status: 'open', digest: 'd'.repeat(64), tenant_hash: 'e'.repeat(32) } }])
  const result = await moved.window.SaldiAssist.decideInboxItem(5, 'reject')
  assert.equal(result.ok, false)
  assert.equal(result.error.code, 'CONFLICT')
  assert.equal(moved.requests.length, 1)
  const missing = load(() => [404, { ok: false, error: { code: 'NOT_FOUND', message_da: 'Opgaven findes ikke.' } }])
  assert.equal(JSON.stringify(await missing.window.SaldiAssist.decideInboxItem(9, 'reject')), JSON.stringify({ ok: false, error: { code: 'NOT_FOUND', message_da: 'Opgaven findes ikke.' } }))
  const down = load(() => [502, null])
  assert.equal((await down.window.SaldiAssist.decideInboxItem(9, 'reject')).error.code, 'UNAVAILABLE')
})

test('invalid ids and decisions are refused without a request', async () => {
  const { window, requests } = load(() => [200, {}])
  for (const [id, decision] of [[0, 'approve'], [-1, 'reject'], [1.5, 'reject'], ['3', 'reject'], [3, 'undo'], [3, 'APPROVE']]) {
    const result = await window.SaldiAssist.decideInboxItem(id, decision)
    assert.equal(result.error.code, 'VALIDATION_ERROR')
  }
  assert.equal(requests.length, 0)
  assert.equal(window.SaldiAssist.openInboxItem('3'), false)
})

test('the badge hides at zero and caps the display at 99+', () => {
  const { window, badge, label } = load(() => [200, {}])
  window.SaldiInbox.setBadge(0)
  assert.equal(badge.hidden, true)
  assert.equal(label.textContent, '')
  window.SaldiInbox.setBadge(150)
  assert.equal(badge.textContent, '99+')
  assert.equal(badge.attrs.title, '150 åbne')
  assert.equal(label.textContent, ' (150 åbne)')
})

// ------------------------------------------------------------------ approval modal (DOM stub)

function domNode (tag, document) {
  const node = {
    tagName: String(tag).toUpperCase(),
    className: '',
    id: '',
    type: '',
    hidden: false,
    disabled: false,
    value: '',
    open: false,
    inert: false,
    children: [],
    parentNode: null,
    attrs: {},
    listeners: {},
    style: { setProperty () {} },
    _text: '',
    _html: '',
    get textContent () { return this._text + this.children.map((c) => c.textContent).join('') },
    set textContent (value) { this._text = String(value); this.children.forEach((c) => { c.parentNode = null }); this.children = [] },
    get innerHTML () { return this._html },
    set innerHTML (value) {
      this._html = String(value)
      this.children = []
      const m = this._html.match(/class="sac-title" id="([^"]+)"/)
      if (m) { const t = domNode('h3', document); t.className = 'sac-title'; t.id = m[1]; this.appendChild(t) }
    },
    get offsetParent () { return this.hidden ? null : {} },
    appendChild (child) { if (child.parentNode) child.parentNode.removeChild(child); child.parentNode = this; this.children.push(child); return child },
    removeChild (child) { this.children = this.children.filter((c) => c !== child); child.parentNode = null; return child },
    replaceChild (next, old) { const i = this.children.indexOf(old); next.parentNode = this; old.parentNode = null; this.children[i] = next; return old },
    remove () { if (this.parentNode) this.parentNode.removeChild(this) },
    contains (other) { for (let n = other; n; n = n.parentNode) if (n === this) return true; return false },
    setAttribute (k, v) { this.attrs[k] = String(v) },
    getAttribute (k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null },
    removeAttribute (k) { delete this.attrs[k] },
    addEventListener (type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn) },
    focus () { document.activeElement = this },
    all () { return this.children.flatMap((c) => [c, ...c.all()]) },
    querySelector (selector) { return this.all().find((n) => matches(n, selector)) || null },
    querySelectorAll (selector) { return this.all().filter((n) => selector.split(',').some((part) => matches(n, part.trim()))) }
  }
  return node
}

function matches (node, selector) {
  if (selector.startsWith('.')) return node.className.split(' ').includes(selector.slice(1))
  if (selector.startsWith('[') && selector.endsWith(']')) return node.getAttribute(selector.slice(1, -1)) !== null
  if (selector === 'button' || selector === 'textarea' || selector === 'summary') return node.tagName === selector.toUpperCase()
  return false
}

function loadModal (respond) {
  const requests = []
  const docListeners = {}
  const clock = { now: Date.now() }
  const document = {
    activeElement: null,
    getElementById: (id) => id === 'saldi-inbox-config'
      ? { textContent: JSON.stringify({ csrf: 'csrf-1', tenantHash: TENANT, texts: {} }) }
      : null,
    querySelectorAll: () => [],
    createElement: (tag) => domNode(tag, document),
    addEventListener: (type, fn) => { (docListeners[type] = docListeners[type] || []).push(fn) },
    removeEventListener: (type, fn) => { docListeners[type] = (docListeners[type] || []).filter((f) => f !== fn) }
  }
  document.body = domNode('body', document)
  const chat = document.body.appendChild(domNode('iframe', document))
  const window = {
    SaldiAssist: {},
    fetch: (url, init) => {
      const body = JSON.parse(init.body)
      requests.push(body)
      const [status, json] = respond(body)
      return Promise.resolve({ status, json: () => Promise.resolve(json) })
    }
  }
  const FakeDate = { now: () => clock.now, parse: Date.parse }
  vm.runInNewContext(source, { window, document, AbortController, setTimeout, clearTimeout, Date: FakeDate, JSON, Promise, Math, isFinite, Object, Array, String })
  const dialog = () => document.body.querySelector('.sac-modal')
  const press = (key, extra = {}) => {
    const event = { key, isTrusted: true, repeat: false, target: document.activeElement || document.body, preventDefault () { this.prevented = true }, ...extra }
    ;(docListeners.keydown || []).forEach((fn) => fn(event))
    return event
  }
  const click = (node, isTrusted = true) => (node.listeners.click || []).forEach((fn) => fn({ isTrusted, target: node }))
  const byMarker = (marker) => dialog() && dialog().querySelector('[' + marker + ']')
  return { window, document, requests, clock, chat, dialog, press, click, byMarker, docListeners }
}

const DIGEST = 'd'.repeat(64)
const openItem = { id: 5, status: 'open', digest: DIGEST, tenant_hash: TENANT, card: { approve_label: 'Godkend', reject_label: 'Afvis' } }
const cardHtml = '<article class="sac"><h3 class="sac-title" id="sac-m-title-5">Tilbud</h3></article>'
const settle = (ms = 0) => new Promise((resolve) => setTimeout(resolve, ms))

function modalServer (overrides = {}) {
  return (body) => {
    if (body.op === 'preview') return [200, { ok: true, item: overrides.item || openItem, html: cardHtml }]
    if (body.op === 'approve') return [200, { ok: true, item: { ...openItem, status: 'applied' }, html: cardHtml, receipt: { text: 'Tilbuddet er oprettet. Intet er sendt eller faktureret.', screen: 'debitor/ordre.php?id=7', screen_label: 'Åbn ordren' } }]
    if (body.op === 'reject') return [200, { ok: true, item: { ...openItem, status: 'rejected' }, html: cardHtml }]
    if (body.op === 'count') return [200, { ok: true, count: 4 }]
    return [400, null]
  }
}

test('the modal is a v2 dialog that makes the rest of the page inert and shows Afvis and Godkend', async () => {
  const m = loadModal(modalServer())
  m.window.SaldiAssist.decideInboxItem(5, 'approve')
  await settle()
  const dialog = m.dialog()
  assert.ok(dialog.className.split(' ').includes('sai-v2'))
  assert.equal(dialog.attrs.role, 'dialog')
  assert.equal(dialog.attrs['aria-modal'], 'true')
  assert.equal(dialog.attrs['aria-describedby'], 'sac-m-title-5')
  assert.equal(m.chat.inert, true)
  assert.ok(m.byMarker('data-sac-modal-approve'))
  assert.ok(m.byMarker('data-sac-modal-reject'))
  assert.equal(m.byMarker('data-sac-modal-approve').attrs['aria-keyshortcuts'], 'G')
})

test('Godkend ignores scripted and early clicks and then posts the reviewed digest with the note', async () => {
  const m = loadModal(modalServer())
  const decided = m.window.SaldiAssist.decideInboxItem(5, 'approve')
  await settle()
  const approve = m.byMarker('data-sac-modal-approve')
  m.click(approve, true) // too early: the buttons are not armed yet
  m.click(approve, false)
  await settle(450)
  m.click(approve, false) // armed, but not a real click
  assert.deepEqual(m.requests.map((r) => r.op), ['preview'])
  m.dialog().querySelector('textarea').value = '  Ring til kunden først  '
  m.click(approve, true)
  const result = await decided
  assert.equal(result.ok, true)
  assert.equal(result.item.status, 'applied')
  assert.deepEqual(m.requests[1], { tenant_hash: TENANT, op: 'approve', item_id: 5, digest: DIGEST, note: 'Ring til kunden først' })
  const labels = m.dialog().querySelectorAll('button').map((b) => b.textContent)
  assert.ok(labels.includes('Luk') && labels.includes('Åbn ordren'))
  assert.equal(m.byMarker('data-sac-modal-approve'), null, 'no second approve after the decision')
})

test('Afvis in the modal rejects with the digest; the result says rejected, never applied', async () => {
  const m = loadModal(modalServer())
  const decided = m.window.SaldiAssist.decideInboxItem(5, 'approve')
  await settle(450)
  m.click(m.byMarker('data-sac-modal-reject'), true)
  const result = await decided
  assert.equal(result.item.status, 'rejected')
  assert.deepEqual(m.requests[1], { tenant_hash: TENANT, op: 'reject', item_id: 5, digest: DIGEST })
})

test('G and A decide only for real, armed, unmodified key presses inside the dialog and never while typing', async () => {
  const m = loadModal(modalServer())
  const decided = m.window.SaldiAssist.decideInboxItem(5, 'approve')
  await settle(450)
  const dialog = m.dialog()
  const textarea = dialog.querySelector('textarea')
  m.press('g', { target: dialog }) // within the key arming time
  m.clock.now += 1000
  m.press('g', { target: dialog, isTrusted: false })
  m.press('g', { target: dialog, ctrlKey: true })
  m.press('g', { target: dialog, repeat: true })
  m.press('g', { target: textarea })
  m.press('g', { target: m.chat })
  assert.deepEqual(m.requests.map((r) => r.op), ['preview'])
  m.press('n', { target: dialog })
  assert.equal(dialog.querySelector('.sac-note').open, true)
  assert.equal(m.document.activeElement, textarea)
  m.press('a', { target: dialog })
  const result = await decided
  assert.equal(result.item.status, 'rejected')
  assert.equal(m.requests[1].op, 'reject')
})

test('Esc cancels: nothing is decided, the page is usable again and the caller hears CANCELLED', async () => {
  const m = loadModal(modalServer())
  const decided = m.window.SaldiAssist.decideInboxItem(5, 'approve')
  await settle()
  const event = m.press('Escape')
  assert.equal(event.prevented, true)
  const result = await decided
  assert.equal(result.error.code, 'CANCELLED')
  assert.equal(m.dialog(), null)
  assert.equal(m.chat.inert, false)
  assert.deepEqual(m.requests.map((r) => r.op), ['preview'])
  assert.equal((m.docListeners.keydown || []).length, 0, 'the key handler is removed with the dialog')
})

test('an item that is no longer open shows its card without decision buttons and reports CONFLICT', async () => {
  const m = loadModal(modalServer({ item: { ...openItem, status: 'applied' } }))
  const result = await m.window.SaldiAssist.decideInboxItem(5, 'approve')
  assert.equal(result.error.code, 'CONFLICT')
  assert.equal(m.byMarker('data-sac-modal-approve'), null)
  assert.equal(m.byMarker('data-sac-modal-reject'), null)
  const alert = m.dialog().querySelector('.sac-alert')
  assert.ok(alert.className.includes('sac-alert--quiet'), 'not amber: the card shows its own state')
  assert.ok(!alert.textContent.includes('Intet er ændret'), 'an applied item did change something')
})

test('a failed decision is amber and says that nothing changed', async () => {
  const m = loadModal((body) => body.op === 'approve'
    ? [409, { ok: false, error: { code: 'CONFLICT', message_da: 'Kassekladden er åben hos en anden bruger lige nu. Prøv igen om lidt.' } }]
    : modalServer()(body))
  const decided = m.window.SaldiAssist.decideInboxItem(5, 'approve')
  await settle(450)
  m.click(m.byMarker('data-sac-modal-approve'), true)
  const result = await decided
  assert.equal(result.ok, false)
  const alert = m.dialog().querySelector('.sac-alert')
  assert.equal(alert.attrs.role, 'alert')
  assert.ok(!alert.className.includes('quiet'))
  assert.ok(alert.textContent.includes('Intet er ændret.'))
})

test('Esc inside the open note folds the note and keeps the text; the next Esc closes the dialog', async () => {
  const m = loadModal(modalServer())
  const decided = m.window.SaldiAssist.decideInboxItem(5, 'approve')
  await settle(450)
  const dialog = m.dialog()
  m.clock.now += 1000
  m.press('n', { target: dialog })
  const note = dialog.querySelector('.sac-note')
  const textarea = dialog.querySelector('textarea')
  assert.equal(note.open, true)
  textarea.value = 'Ring til kunden først'
  const first = m.press('Escape', { target: textarea })
  assert.equal(first.prevented, true)
  assert.ok(m.dialog(), 'the dialog stays open')
  assert.equal(note.open, false)
  assert.equal(textarea.value, 'Ring til kunden først', 'the typed note is kept')
  assert.equal(m.document.activeElement, dialog.querySelector('summary'))
  m.press('Escape', { target: m.document.activeElement })
  const result = await decided
  assert.equal(result.error.code, 'CANCELLED')
  assert.equal(m.dialog(), null)
  assert.deepEqual(m.requests.map((r) => r.op), ['preview'])
})

test('the title says Godkend forslag while open and Afgjort forslag after a decision or on a decided item', async () => {
  const title = (m) => m.dialog().querySelector('.sac-modal__title').textContent
  const m = loadModal(modalServer())
  const decided = m.window.SaldiAssist.decideInboxItem(5, 'approve')
  await settle(450)
  assert.equal(title(m), 'Godkend forslag')
  m.click(m.byMarker('data-sac-modal-reject'), true)
  await decided
  assert.equal(title(m), 'Afgjort forslag')
  const approved = loadModal(modalServer())
  const done = approved.window.SaldiAssist.decideInboxItem(5, 'approve')
  await settle(450)
  approved.click(approved.byMarker('data-sac-modal-approve'), true)
  await done
  assert.equal(title(approved), 'Afgjort forslag')
  const handled = loadModal(modalServer({ item: { ...openItem, status: 'rejected' } }))
  await handled.window.SaldiAssist.decideInboxItem(5, 'approve')
  assert.equal(title(handled), 'Afgjort forslag')
  const failed = loadModal((body) => body.op === 'approve' ? [409, { ok: false, error: { code: 'CONFLICT', message_da: 'x' } }] : modalServer()(body))
  const attempt = failed.window.SaldiAssist.decideInboxItem(5, 'approve')
  await settle(450)
  failed.click(failed.byMarker('data-sac-modal-approve'), true)
  await attempt
  assert.equal(title(failed), 'Godkend forslag', 'nothing was decided')
})
