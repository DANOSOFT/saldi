// --- javascript/saldi-assist-banner.js --- ai/mcp-assistant --- 2026.10.02
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The shell's quiet "Vigtigt" banner (SPEC-companion C5.1). On shell load it asks inbox/action.php
// {op: 'banner'} for the newest open interrupt item this user has not been shown yet. If there is one,
// it shows one slim non-modal strip above the content frame (the frame shrinks by the strip's height,
// so nothing is covered): "Vigtigt: <title>", "Åbn" (opens the item on the Opgaver page) and a close
// button. Once the banner is on screen it records {op: 'banner_seen'}, so the same item never shows
// again. At most one banner per shell load; no
// sound, no focus stealing, no modal. Uses the CSRF token and tenant hash from #saldi-inbox-config
// (index/main.php), the same as javascript/saldi-inbox.js. All text is set with textContent.
//
// 20261002 CL/LH Created.
// 20261007 CL/LH Opgaver v2 look: "Vigtigt" is a pill (the colon is for screen readers only); styles in
//                css/approval_card.css.

(function (root, doc) {
  'use strict'

  var cfgElement = doc.getElementById('saldi-inbox-config')
  if (!cfgElement) return
  var cfg
  try { cfg = JSON.parse(cfgElement.textContent) } catch (e) { return }
  if (!cfg || typeof cfg.csrf !== 'string' || typeof cfg.tenantHash !== 'string') return

  var ACTION_URL = cfg.actionUrl || '../inbox/action.php'
  var started = false
  var current = null
  var currentHost = null

  function post (body) {
    var controller = typeof AbortController === 'function' ? new AbortController() : null
    var timer = controller ? setTimeout(function () { controller.abort() }, 8000) : null
    var payload = { tenant_hash: cfg.tenantHash }
    for (var key in body) {
      if (Object.prototype.hasOwnProperty.call(body, key)) payload[key] = body[key]
    }
    return root.fetch(ACTION_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Saldi-Csrf': cfg.csrf },
      body: JSON.stringify(payload),
      signal: controller ? controller.signal : undefined
    }).then(function (response) {
      if (timer) clearTimeout(timer)
      return response.json().then(null, function () { return null })
    }, function () {
      if (timer) clearTimeout(timer)
      return null
    })
  }

  function valid (banner) {
    return !!banner && typeof banner === 'object' &&
      typeof banner.item_id === 'number' && isFinite(banner.item_id) && banner.item_id > 0 && Math.floor(banner.item_id) === banner.item_id &&
      typeof banner.title === 'string' && banner.title !== ''
  }

  function label (texts, key, fallback) {
    return texts && typeof texts[key] === 'string' && texts[key] !== '' ? texts[key] : fallback
  }

  function el (tag, className, text) {
    var node = doc.createElement(tag)
    node.className = className
    if (text !== undefined) node.textContent = text
    return node
  }

  function remove () {
    if (current && current.parentNode) current.parentNode.removeChild(current)
    if (currentHost && currentHost.classList) currentHost.classList.remove('saldi-assist-banner-on')
    current = null
    currentHost = null
  }

  function openItem (itemId) {
    var assist = root.SaldiAssist
    if (assist && typeof assist.openInboxItem === 'function' && assist.openInboxItem(itemId)) return true
    if (typeof root.update_iframe === 'function') {
      root.update_iframe('/inbox/index.php?item=' + itemId)
      return true
    }
    return false
  }

  function show (banner) {
    var texts = banner.texts || {}
    var box = el('div', 'saldi-assist-banner')
    box.setAttribute('role', 'status')
    box.setAttribute('data-item-id', String(banner.item_id))
    var message = el('p', 'saldi-assist-banner__text')
    message.appendChild(el('strong', 'saldi-assist-banner__label', label(texts, 'important', 'Vigtigt')))
    message.appendChild(el('span', 'sai-visually-hidden', ': '))
    message.appendChild(el('span', 'saldi-assist-banner__title', banner.title))
    var open = el('button', 'saldi-assist-banner__open', label(texts, 'open', 'Åbn'))
    open.type = 'button'
    var close = el('button', 'saldi-assist-banner__close', '×')
    close.type = 'button'
    close.setAttribute('aria-label', label(texts, 'close', 'Luk'))
    close.setAttribute('title', label(texts, 'close', 'Luk'))
    open.addEventListener('click', function () {
      remove()
      openItem(banner.item_id)
    })
    close.addEventListener('click', remove)
    box.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') remove()
    })
    box.appendChild(message)
    box.appendChild(open)
    box.appendChild(close)
    var host = doc.querySelector('.home-section')
    if (!host) return false
    var content = host.querySelector ? host.querySelector('.home-content') : null
    if (content) {
      host.insertBefore(box, content)
    } else {
      host.appendChild(box)
    }
    if (host.classList) host.classList.add('saldi-assist-banner-on')
    current = box
    currentHost = host
    return true
  }

  function start () {
    if (started) return null
    started = true
    return post({ op: 'banner' }).then(function (result) {
      if (!result || result.ok !== true || !valid(result.banner)) return null
      if (!show(result.banner)) return null
      // Recorded only once the banner is actually on screen.
      return post({ op: 'banner_seen', item_id: result.banner.item_id }).then(function () {
        return result.banner.item_id
      })
    })
  }

  root.SaldiAssistBanner = { start: start, close: remove }
  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', start)
  } else {
    start()
  }
})(window, document)
