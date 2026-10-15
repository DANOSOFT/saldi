// --- tests/includes/ui/saldiAssistBanner.test.mjs --- ai/mcp-assistant --- 2026.10.02
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// javascript/saldi-assist-banner.js with a small DOM stub: one quiet banner for the newest unseen
// interrupt item (C5.1), 'banner_seen' only after it is on screen, text set with textContent, "Åbn"
// opens the item through SaldiAssist.openInboxItem, close and Escape remove it, and nothing shows
// (and nothing is recorded) when the server has no banner, fails or returns a malformed one.
// Run on the host: node --test tests/includes/ui/
//
// 20261002 CL/LH Created.
// 20261007 CL/LH The "Vigtigt" label is a pill without the visible colon.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const source = readFileSync(fileURLToPath(new URL('../../../javascript/saldi-assist-banner.js', import.meta.url)), 'utf8')
const TENANT = 'a'.repeat(32)

function node (tag) {
  return {
    tagName: tag.toUpperCase(),
    className: '',
    textContent: '',
    type: '',
    children: [],
    attrs: {},
    listeners: {},
    parentNode: null,
    appendChild (child) { child.parentNode = this; this.children.push(child); return child },
    insertBefore (child, ref) { child.parentNode = this; const i = this.children.indexOf(ref); this.children.splice(i < 0 ? this.children.length : i, 0, child); return child },
    classes: new Set(),
    get classList () { const c = this.classes; return { add: (n) => c.add(n), remove: (n) => c.delete(n), contains: (n) => c.has(n) } },
    querySelector (selector) { return selector.startsWith('.') ? this.find(selector.slice(1)) : null },
    removeChild (child) { this.children = this.children.filter((c) => c !== child); child.parentNode = null; return child },
    setAttribute (k, v) { this.attrs[k] = String(v) },
    getAttribute (k) { return this.attrs[k] },
    addEventListener (type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn) },
    fire (type, event = {}) { (this.listeners[type] || []).forEach((fn) => fn(event)) },
    find (className) {
      if (this.className === className && this.tagName !== 'SECTION') return this
      for (const c of this.children) { const hit = c.find && c.find(className); if (hit) return hit }
      return null
    },
    get text () { return this.textContent + this.children.map((c) => c.text || c.textContent || '').join('') }
  }
}

function load (respond, { config = { csrf: 'csrf-1', tenantHash: TENANT }, openInboxItem } = {}) {
  const requests = []
  const host = node('section')
  host.className = 'home-section'
  const content = host.appendChild(node('div'))
  content.className = 'home-content'
  const opened = []
  const document = {
    readyState: 'complete',
    getElementById: (id) => id === 'saldi-inbox-config' && config ? { textContent: JSON.stringify(config) } : null,
    querySelector: (selector) => selector === '.home-section' ? host : null,
    createElement: (tag) => node(tag),
    createTextNode: (text) => ({ textContent: text, text }),
    addEventListener: () => {},
    body: null
  }
  const window = {
    SaldiAssist: { openInboxItem: openInboxItem || ((id) => { opened.push(id); return true }) },
    fetch: (url, init) => {
      const body = JSON.parse(init.body)
      requests.push({ url, body, headers: init.headers, credentials: init.credentials, method: init.method })
      const reply = respond(body, requests.length)
      if (reply instanceof Error) return Promise.reject(reply)
      return Promise.resolve({ status: 200, json: () => Promise.resolve(reply) })
    }
  }
  vm.runInNewContext(source, { window, document, AbortController, setTimeout, clearTimeout, JSON, Promise, Math, isFinite, Object, String })
  return { window, requests, host, opened }
}

const banners = (host) => host.children.filter((c) => c.className === 'saldi-assist-banner')
const flush = () => new Promise((resolve) => setTimeout(resolve, 0))
const banner = { item_id: 42, title: '<img src=x onerror=alert(1)> Bankoplysninger ændret', texts: { important: 'Vigtigt', open: 'Åbn', close: 'Luk' } }

test('shows one banner for the unseen interrupt and records it after display', async () => {
  const { requests, host } = load((body) => body.op === 'banner' ? { ok: true, banner } : { ok: true, recorded: true })
  await flush(); await flush()
  assert.equal(requests.length, 2)
  assert.deepEqual(requests[0].body, { tenant_hash: TENANT, op: 'banner' })
  assert.equal(requests[0].url, '../inbox/action.php')
  assert.equal(requests[0].method, 'POST')
  assert.equal(requests[0].credentials, 'same-origin')
  assert.equal(requests[0].headers['X-Saldi-Csrf'], 'csrf-1')
  assert.deepEqual(requests[1].body, { tenant_hash: TENANT, op: 'banner_seen', item_id: 42 })
  assert.equal(banners(host).length, 1)
  assert.equal(host.children[0].className, 'saldi-assist-banner', 'inserted above the content frame')
  assert.ok(host.classList.contains('saldi-assist-banner-on'))
  const box = host.children[0]
  assert.equal(box.className, 'saldi-assist-banner')
  assert.equal(box.attrs.role, 'status')
  assert.equal(box.find('saldi-assist-banner__label').textContent, 'Vigtigt')
  assert.equal(box.find('sai-visually-hidden').textContent, ': ', 'the colon is read, not shown')
  // Untrusted title goes in as text, never markup.
  assert.equal(box.find('saldi-assist-banner__title').textContent, banner.title)
  assert.equal(box.find('saldi-assist-banner__open').textContent, 'Åbn')
  assert.equal(box.find('saldi-assist-banner__close').attrs['aria-label'], 'Luk')
})

test('Åbn opens the item in the inbox and removes the banner; close and Escape remove it', async () => {
  const first = load((body) => body.op === 'banner' ? { ok: true, banner } : { ok: true, recorded: true })
  await flush(); await flush()
  first.host.children[0].find('saldi-assist-banner__open').fire('click')
  assert.deepEqual(first.opened, [42])
  assert.equal(banners(first.host).length, 0)
  assert.ok(!first.host.classList.contains('saldi-assist-banner-on'))

  const second = load((body) => body.op === 'banner' ? { ok: true, banner } : { ok: true, recorded: true })
  await flush(); await flush()
  second.host.children[0].find('saldi-assist-banner__close').fire('click')
  assert.equal(banners(second.host).length, 0)
  assert.deepEqual(second.opened, [])

  const third = load((body) => body.op === 'banner' ? { ok: true, banner } : { ok: true, recorded: true })
  await flush(); await flush()
  third.host.children[0].fire('keydown', { key: 'Escape' })
  assert.equal(banners(third.host).length, 0)
})

test('at most one banner per shell load', async () => {
  const { window, requests, host } = load((body) => body.op === 'banner' ? { ok: true, banner } : { ok: true, recorded: true })
  await flush(); await flush()
  assert.equal(window.SaldiAssistBanner.start(), null)
  await flush()
  assert.equal(requests.length, 2)
  assert.equal(banners(host).length, 1)
})

test('no banner, an error, a failed request or a malformed banner show and record nothing', async () => {
  for (const reply of [
    { ok: true, banner: null },
    { ok: false, error: { code: 'CONFLICT' } },
    new Error('offline'),
    { ok: true, banner: { item_id: '42', title: 'x' } },
    { ok: true, banner: { item_id: 0, title: 'x' } },
    { ok: true, banner: { item_id: 4.5, title: 'x' } },
    { ok: true, banner: { item_id: 42, title: '' } },
    null
  ]) {
    const { requests, host } = load(() => reply)
    await flush(); await flush()
    assert.equal(requests.length, 1, JSON.stringify(reply))
    assert.equal(banners(host).length, 0, JSON.stringify(reply))
    assert.ok(!host.classList.contains('saldi-assist-banner-on'))
  }
})

test('without the inbox config (revisor/admin shell) nothing is requested', async () => {
  const { requests, window } = load(() => ({ ok: true, banner }), { config: null })
  await flush()
  assert.equal(requests.length, 0)
  assert.equal(window.SaldiAssistBanner, undefined)
})

test('falls back to the shell navigation when openInboxItem is unavailable', async () => {
  const calls = []
  const { host, window } = load((body) => body.op === 'banner' ? { ok: true, banner } : { ok: true, recorded: true }, { openInboxItem: () => false })
  window.update_iframe = (path) => calls.push(path)
  await flush(); await flush()
  host.children[0].find('saldi-assist-banner__open').fire('click')
  assert.deepEqual(calls, ['/inbox/index.php?item=42'])
})
