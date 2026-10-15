// --- javascript/saldi-inbox.js --- ai/mcp-assistant --- 2026.10.05
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// Saldi-origin glue between the shell (index/main.php) and Saldi Assist (SPEC 7.2):
//   window.SaldiAssist.getMcpToken(options)       session MCP token, cached until expiry - 5 min
//   window.SaldiAssist.decideInboxItem(id, kind)  'approve' opens a Saldi-owned modal; only its
//                                                 Godkend button (or G) approves, its Afvis button
//                                                 (or A) rejects. 'reject' posts directly.
//   window.SaldiAssist.openInboxItem(id)          shows the item on the Opgaver page
//   window.SaldiAssist.refreshInboxBadge()        re-counts the sidebar badge
// Configuration (CSRF token, tenant hash, texts) comes from the JSON block
// #saldi-inbox-config that main.php prints for tenant users only.
//
// Residual risk: the chat widget's loader script runs in this page (it is how the widget
// talks to Saldi). The modal protects decisions against a misbehaving chat iframe: the
// iframe cannot see or click it, the rest of the page is inert while it is open, and only
// a trusted click on Godkend approves. It does not protect against a compromised loader
// script, which runs with the same rights as this file.
//
// 20261002 CL/LH Created.
// 20261002 CL/LH window.SaldiAssist.askAbout(payload) (SPEC-companion C8.2): validates a page focus
//                (a kassekladde line) and hands it to the chat widget as window event saldi:assist:ask.
// 20261005 CDX/LH Expose bounded tenant-bound inbox previews for chat card status refresh.
// 20261007 CL/LH Approval modal in the Opgaver v2 look (sai-v2): quiet head with close, the v2 card, footer
//                with note, key hints, Afvis and Godkend (both Saldi-owned, trusted clicks only); G/A/N keys
//                inside the dialog only; Esc and the focus trap work from the whole document.
// 20261007 CL/LH Review fixes: Esc inside the open note folds the note (keeping the text) before it closes
//                the dialog; after a decision, or on an item that is already decided, the title says
//                "Afgjort forslag" instead of "Godkend forslag".
(function (root, doc) {
  'use strict'

  // ------------------------------------------------------------------ askAbout (C8.2)
  // Defined before the inbox config check: it needs no CSRF token or tenant hash.

  var ASK_STRINGS = ['bilag', 'date', 'description', 'amount', 'invoice']

  function askString (value) {
    return value === undefined || value === null ? '' : (typeof value === 'string' && value.length <= 100 ? value : null)
  }

  function askSide (value) {
    if (value === undefined || value === null) return { type: '', account: '' }
    if (typeof value !== 'object') return null
    var type = askString(value.type)
    var account = askString(value.account)
    if (type === null || account === null || !/^[DFK]?$/.test(type) || !/^(?:[0-9]{1,10})?$/.test(account)) return null
    return { type: type, account: account }
  }

  /** A fresh, shell-owned copy with only the known fields, or null when the shape is wrong. */
  function askFocus (payload) {
    if (!payload || typeof payload !== 'object' || payload.kind !== 'journal_line') return null
    var kladdeId = typeof payload.kladdeId === 'string' && /^[0-9]{1,10}$/.test(payload.kladdeId) ? Number(payload.kladdeId) : payload.kladdeId
    if (typeof kladdeId !== 'number' || !isFinite(kladdeId) || kladdeId < 1 || kladdeId > 9999999999 || Math.floor(kladdeId) !== kladdeId) return null
    var source = payload.row
    if (!source || typeof source !== 'object') return null
    var row = {}
    for (var i = 0; i < ASK_STRINGS.length; i++) {
      var value = askString(source[ASK_STRINGS[i]])
      if (value === null) return null
      row[ASK_STRINGS[i]] = value
    }
    row.debit = askSide(source.debit)
    row.credit = askSide(source.credit)
    if (!row.debit || !row.credit) return null
    return { kind: 'journal_line', kladdeId: kladdeId, row: row }
  }

  function askAmount (amount) {
    var m = amount.match(/^(-?)([0-9]+)(?:\.([0-9]{1,3}))?$/)
    if (!m) return amount
    return m[1] + m[2].replace(/\B(?=([0-9]{3})+(?!\d))/g, '.') + ',' + ((m[3] || '') + '00').slice(0, 2)
  }

  function askAbout (payload) {
    var focus = askFocus(payload)
    if (!focus) return false
    var row = focus.row
    var question = 'Hjælp mig med linjen: bilag ' + (row.bilag || '-') + ', ' + (row.description || '-') + ', ' +
      (row.amount ? askAmount(row.amount) : '-') + ' kr.'
    var detail = { question: question, focus: focus }
    var event
    try {
      event = new root.CustomEvent('saldi:assist:ask', { detail: detail })
    } catch (e) {
      event = doc.createEvent('CustomEvent')
      event.initCustomEvent('saldi:assist:ask', false, false, detail)
    }
    root.dispatchEvent(event)
    var shell = root.SaldiAssist
    return !!(shell && shell.v2Ready === true)
  }

  var shellApi = root.SaldiAssist && typeof root.SaldiAssist === 'object' ? root.SaldiAssist : (root.SaldiAssist = {})
  shellApi.askAbout = askAbout

  var cfgElement = doc.getElementById('saldi-inbox-config')
  if (!cfgElement) return
  var cfg
  try { cfg = JSON.parse(cfgElement.textContent) } catch (e) { return }
  if (!cfg || typeof cfg.csrf !== 'string' || typeof cfg.tenantHash !== 'string') return

  var T = cfg.texts || {}
  var ACTION_URL = cfg.actionUrl || '../inbox/action.php'
  var TOKEN_URL = cfg.tokenUrl || '../includes/saldi_assist_mcp_token.php'
  var APPROVE_ARM_MS = 400 // Godkend ignores clicks this long after the modal appears (no click-through).

  function text (key, fallback) {
    return typeof T[key] === 'string' && T[key] !== '' ? T[key] : fallback
  }

  function failure (code, message) {
    return { ok: false, error: { code: code, message_da: message } }
  }

  function post (url, body, timeoutMs) {
    var controller = typeof AbortController === 'function' ? new AbortController() : null
    var timer = controller ? setTimeout(function () { controller.abort() }, timeoutMs) : null
    return root.fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Saldi-Csrf': cfg.csrf },
      body: JSON.stringify(body),
      signal: controller ? controller.signal : undefined
    }).then(function (response) {
      return response.json().then(function (json) {
        return { status: response.status, json: json }
      }, function () {
        return { status: response.status, json: null }
      })
    }).then(function (result) {
      if (timer) clearTimeout(timer)
      return result
    }, function (error) {
      if (timer) clearTimeout(timer)
      throw error
    })
  }

  function action (body) {
    var payload = { tenant_hash: cfg.tenantHash }
    for (var key in body) {
      if (Object.prototype.hasOwnProperty.call(body, key)) payload[key] = body[key]
    }
    return post(ACTION_URL, payload, 15000).then(function (result) {
      var json = result.json
      if (json && typeof json.ok === 'boolean' && (json.ok || (json.error && typeof json.error.code === 'string'))) return json
      return failure('UNAVAILABLE', text('unavailable', 'Opgaver er midlertidigt utilgængelige. Prøv igen om lidt.'))
    }, function () {
      return failure('UNAVAILABLE', text('unavailable', 'Opgaver er midlertidigt utilgængelige. Prøv igen om lidt.'))
    })
  }

  // ------------------------------------------------------------------ MCP token

  var token = null
  var tokenUntil = 0
  var disabledUntil = 0
  var tokenRequest = null

  function getMcpToken (options) {
    var refresh = !!(options && options.refresh)
    var now = Date.now()
    if (!refresh && token && now < tokenUntil) return Promise.resolve(token)
    if (!refresh && now < disabledUntil) return Promise.resolve({ enabled: false })
    if (tokenRequest) return tokenRequest
    token = null
    tokenUntil = 0
    tokenRequest = post(TOKEN_URL, {}, 4000).then(function (result) {
      var json = result.json
      if (result.status === 200 && json && json.enabled === true && typeof json.token === 'string') {
        if (json.tenant_hash !== cfg.tenantHash) return null // the session moved to another company
        var fresh = {
          enabled: true,
          token: json.token,
          expiresAt: json.expires_at,
          installId: json.install_id,
          tenantHash: json.tenant_hash,
          userHash: json.user_hash
        }
        var expires = Date.parse(json.expires_at)
        var until = isFinite(expires) ? expires - 5 * 60 * 1000 : 0
        if (until > Date.now()) {
          token = fresh
          tokenUntil = until
        }
        return fresh
      }
      if (result.status === 200 && json && json.enabled === false) {
        disabledUntil = Date.now() + 60 * 1000
        return { enabled: false }
      }
      return null
    }, function () {
      return null
    }).then(function (value) {
      tokenRequest = null
      return value
    })
    return tokenRequest
  }

  // ------------------------------------------------------------------ badge

  var badgeRequest = null
  var badgeAt = 0

  function setBadge (count) {
    var n = typeof count === 'number' && isFinite(count) && count > 0 ? Math.floor(count) : 0
    var label = text('openCount', '{n} åbne').replace('{n}', String(n))
    var badges = doc.querySelectorAll('[data-saldi-inbox-badge]')
    for (var i = 0; i < badges.length; i++) {
      badges[i].textContent = n > 99 ? '99+' : String(n)
      badges[i].hidden = n === 0
      badges[i].setAttribute('title', label)
    }
    var labels = doc.querySelectorAll('[data-saldi-inbox-badge-label]')
    for (var j = 0; j < labels.length; j++) {
      labels[j].textContent = n > 0 ? ' (' + label + ')' : ''
    }
  }

  function refreshInboxBadge () {
    if (badgeRequest) return badgeRequest
    badgeAt = Date.now()
    badgeRequest = action({ op: 'count' }).then(function (result) {
      badgeRequest = null
      if (result.ok && typeof result.count === 'number') setBadge(result.count)
      return result.ok ? result.count : null
    })
    return badgeRequest
  }

  doc.addEventListener('visibilitychange', function () {
    if (doc.visibilityState === 'visible' && Date.now() - badgeAt > 60 * 1000) refreshInboxBadge()
  })

  // ------------------------------------------------------------------ navigation

  function openInboxItem (itemId) {
    if (typeof itemId !== 'number' || !isFinite(itemId) || itemId <= 0 || Math.floor(itemId) !== itemId) return false
    if (typeof root.update_iframe !== 'function') return false
    if (typeof root.clear_sidebar === 'function') root.clear_sidebar()
    var entry = doc.getElementById('saldi-inbox-entry')
    if (entry) entry.classList.add('active')
    root.update_iframe('/inbox/index.php?item=' + itemId)
    return true
  }

  function openScreen (screen) {
    if (root.SaldiAssistNavigate && root.SaldiAssistNavigate.isAllowed && root.SaldiAssistNavigate.isAllowed(screen)) {
      root.SaldiAssistNavigate(screen)
      return true
    }
    return false
  }

  // ------------------------------------------------------------------ modal

  var modalOpen = false
  var KEY_ARM_MS = 800 // G and A ignore key presses this long after the decision buttons appear.

  function el (tag, className, content) {
    var node = doc.createElement(tag)
    if (className) node.className = className
    if (content !== undefined) node.textContent = content
    return node
  }

  function button (label, variant, marker) {
    var node = el('button', 'sac-btn sac-btn--' + (variant || 'primary'), label)
    node.type = 'button'
    if (marker) node.setAttribute(marker, '')
    return node
  }

  function isTextField (node) {
    if (!node || typeof node.tagName !== 'string') return false
    var tag = node.tagName.toLowerCase()
    return tag === 'textarea' || tag === 'input' || tag === 'select' || node.isContentEditable === true
  }

  // Quiet shortcut hints for the footer, in the Opgaver style: <kbd>G</kbd> godkend.
  function keyHints (pairs) {
    var box = el('p', 'sai-keys sac-modal__keys')
    box.setAttribute('aria-hidden', 'true')
    pairs.forEach(function (pair) {
      box.appendChild(el('kbd', '', pair[0]))
      box.appendChild(el('span', '', pair[1]))
    })
    return box
  }

  // Opens the dialog at once (loading state); returns handles to fill it in.
  function openModal (onDismiss) {
    modalOpen = true
    var previousFocus = doc.activeElement
    var backdrop = el('div', 'sac-modal-backdrop')
    // sai-v2: the same tokens and card styles as the Opgaver page.
    var dialog = el('div', 'sac-modal sai-v2')
    dialog.setAttribute('role', 'dialog')
    dialog.setAttribute('aria-modal', 'true')
    dialog.setAttribute('aria-labelledby', 'sac-modal-title')
    dialog.tabIndex = -1 // a click on text inside keeps the focus (and Esc, G, A) in the dialog
    if (cfg.accent) dialog.style.setProperty('--sac-accent', cfg.accent)
    if (cfg.accentText) dialog.style.setProperty('--sac-accent-text', cfg.accentText)
    var head = el('div', 'sac-modal__head')
    var title = el('h2', 'sac-modal__title', text('approveTitle', 'Godkend forslag'))
    title.id = 'sac-modal-title'
    title.tabIndex = -1
    var closeX = el('button', 'sac-modal__close')
    closeX.type = 'button'
    closeX.setAttribute('aria-label', text('cancel', 'Annuller'))
    closeX.title = text('cancel', 'Annuller') + ' (Esc)'
    closeX.appendChild(el('span', '', '×')).setAttribute('aria-hidden', 'true')
    head.appendChild(title)
    head.appendChild(closeX)
    var body = el('div', 'sac-modal__body')
    var status = el('div', 'sac-modal__status')
    var live = el('div', 'sai-visually-hidden')
    live.setAttribute('role', 'status')
    var content = el('div', 'sac-modal__loading', text('loading', 'Henter forslaget …'))
    body.appendChild(live)
    body.appendChild(status)
    body.appendChild(content)
    var foot = el('div', 'sac-modal__foot')
    foot.hidden = true
    dialog.appendChild(head)
    dialog.appendChild(body)
    dialog.appendChild(foot)
    backdrop.appendChild(dialog)

    // Everything else (the chat iframe included) is inert while the decision is on screen.
    var inerted = []
    for (var i = 0; i < doc.body.children.length; i++) {
      var child = doc.body.children[i]
      if (!child.inert) {
        child.inert = true
        inerted.push(child)
      }
    }
    doc.body.appendChild(backdrop)

    var state = { busy: false, closed: false }
    var escape = null // optional handler that may consume Esc first (the note); returns true when it did
    var keys = null
    var keysAt = 0
    function close () {
      if (state.closed) return
      state.closed = true
      modalOpen = false
      doc.removeEventListener('keydown', onKey, true)
      backdrop.remove()
      inerted.forEach(function (node) { node.inert = false })
      if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus()
    }
    function dismiss () {
      if (state.busy) return
      close()
      if (onDismiss) onDismiss()
    }
    function focusables () {
      return Array.prototype.filter.call(dialog.querySelectorAll('a[href], button, textarea, input, select, summary, [tabindex]:not([tabindex="-1"])'), function (node) {
        return !node.disabled && node.offsetParent !== null
      })
    }
    // On the document, so Esc and Tab also work when the focus fell back to <body>.
    function onKey (event) {
      if (state.closed) return
      if (event.key === 'Escape') {
        event.preventDefault()
        if (escape && escape(event)) return
        dismiss()
        return
      }
      if (event.key === 'Tab') {
        var items = focusables()
        var inside = dialog.contains(doc.activeElement)
        if (!items.length) {
          event.preventDefault()
          title.focus()
          return
        }
        var first = items[0]
        var last = items[items.length - 1]
        if (!inside) {
          event.preventDefault()
          ;(event.shiftKey ? last : first).focus()
        } else if (event.shiftKey && (doc.activeElement === first || doc.activeElement === title || doc.activeElement === dialog)) {
          event.preventDefault()
          last.focus()
        } else if (!event.shiftKey && doc.activeElement === last) {
          event.preventDefault()
          first.focus()
        }
        return
      }
      // Single-letter shortcuts: only real key presses inside the dialog, never while typing a note,
      // never repeated, never with a modifier, and only after the buttons have been on screen a moment.
      if (!keys || !event.isTrusted || event.repeat || event.ctrlKey || event.metaKey || event.altKey) return
      if (!dialog.contains(event.target) || isTextField(event.target) || Date.now() - keysAt < KEY_ARM_MS) return
      var handler = keys[String(event.key).toLowerCase()]
      if (typeof handler === 'function') {
        event.preventDefault()
        handler(event)
      }
    }
    doc.addEventListener('keydown', onKey, true)
    closeX.addEventListener('click', function () { dismiss() })
    backdrop.addEventListener('mousedown', function (event) {
      if (event.target === backdrop) dismiss()
    })
    // Evidence links leave the decision and open the screen behind it.
    dialog.addEventListener('click', function (event) {
      var link = event.target && event.target.closest ? event.target.closest('a[data-saldi-screen]') : null
      if (!link) return
      event.preventDefault()
      if (state.busy) return
      var screen = link.getAttribute('data-saldi-screen')
      dismiss()
      openScreen(screen)
    })
    title.focus()

    return {
      state: state,
      close: close,
      dismiss: dismiss,
      setStatus: function (message, tone) {
        status.textContent = ''
        if (!message) return
        var box = el('div', 'sac-alert' + (tone === 'quiet' ? ' sac-alert--quiet' : ''))
        if (tone !== 'quiet') box.setAttribute('role', 'alert')
        message.forEach(function (line, index) {
          box.appendChild(el('p', index > 0 ? 'sac-alert__strong' : '', line))
        })
        status.appendChild(box)
        body.scrollTop = 0 // the message sits above the card
      },
      announce: function (message) {
        live.textContent = message
      },
      setContent: function (html) {
        // Server-rendered by includes/automation/approvalCard.php: every proposer string is escaped there.
        var wrap = el('div', 'sac-modal__card')
        wrap.innerHTML = html
        body.replaceChild(wrap, content)
        content = wrap
        var cardTitle = wrap.querySelector('.sac-title')
        if (cardTitle && cardTitle.id) {
          dialog.setAttribute('aria-describedby', cardTitle.id)
        } else {
          dialog.removeAttribute('aria-describedby')
        }
      },
      // aside: optional node for the left of the footer (the note, key hints).
      setButtons: function (buttons, aside) {
        foot.textContent = ''
        foot.hidden = false
        if (aside) foot.appendChild(aside)
        var group = el('div', 'sac-modal__buttons')
        buttons.forEach(function (b) { group.appendChild(b) })
        foot.appendChild(group)
      },
      setKeys: function (map) {
        keys = map
        keysAt = Date.now()
      },
      setEscape: function (handler) { escape = handler || null },
      setTitle: function (value) { title.textContent = value },
      focus: function (node) { (node || title).focus() }
    }
  }

  function receiptLines (result) {
    var lines = [text('approved', 'Godkendt') + '.']
    if (result.receipt && typeof result.receipt.text === 'string') lines = [result.receipt.text]
    return lines
  }

  function approveWithModal (itemId) {
    return new Promise(function (resolve) {
      var settled = false
      function settle (value) {
        if (!settled) {
          settled = true
          resolve(value)
        }
      }
      var cancelled = failure('CANCELLED', text('cancelled', 'Godkendelsen blev annulleret.') + ' ' + text('nothing', 'Intet er ændret.'))
      var modal = openModal(function () { settle(cancelled) })
      var closeButton = button(text('close', 'Luk'), 'secondary')
      closeButton.addEventListener('click', function () { modal.dismiss() })

      function end (buttons) {
        modal.setKeys(null)
        modal.setEscape(null)
        modal.setButtons(buttons)
        modal.focus(buttons[0])
      }

      // A failed preview or decision: amber, never green, and it says that nothing changed.
      function showError (result, item) {
        modal.state.busy = false
        modal.setStatus([result.error.message_da, text('nothing', 'Intet er ændret.')])
        var buttons = [closeButton]
        if (item) {
          var inInbox = button(text('openInInbox', 'Vis opgaven'), 'secondary')
          inInbox.addEventListener('click', function () { modal.dismiss(); openInboxItem(item.id) })
          buttons.unshift(inInbox)
        }
        end(buttons)
        settle(result)
      }

      action({ op: 'preview', item_id: itemId }).then(function (preview) {
        if (modal.state.closed) return
        if (!preview.ok) { showError(preview, null); return }
        var item = preview.item
        if (!item || item.tenant_hash !== cfg.tenantHash) {
          showError(failure('CONFLICT', text('companyChanged', 'Du har skiftet regnskab i et andet vindue. Genindlæs siden.')), null)
          return
        }
        modal.setContent(typeof preview.html === 'string' ? preview.html : '')
        if (item.status !== 'open') {
          modal.setTitle(text('decidedTitle', 'Afgjort forslag'))
          // The card itself shows the decided state (and says "Intet er ændret" where that is true).
          var handled = failure('CONFLICT', text('alreadyHandled', 'Forslaget er allerede behandlet eller ændret, siden du åbnede det.'))
          modal.setStatus([handled.error.message_da], 'quiet')
          var view = button(text('openInInbox', 'Vis opgaven'), 'secondary')
          view.addEventListener('click', function () { modal.dismiss(); openInboxItem(item.id) })
          end([closeButton, view])
          settle(handled)
          return
        }
        var approveLabel = (item.card && item.card.approve_label) || text('approve', 'Godkend')
        var rejectLabel = (item.card && item.card.reject_label) || text('reject', 'Afvis')
        var approve = button(approveLabel, 'primary', 'data-sac-modal-approve')
        var reject = button(rejectLabel, 'secondary', 'data-sac-modal-reject')
        approve.setAttribute('aria-keyshortcuts', 'G')
        reject.setAttribute('aria-keyshortcuts', 'A')
        approve.setAttribute('aria-disabled', 'true')
        reject.setAttribute('aria-disabled', 'true')
        var armed = false
        setTimeout(function () {
          armed = true
          approve.removeAttribute('aria-disabled')
          reject.removeAttribute('aria-disabled')
        }, APPROVE_ARM_MS)

        // The optional note travels with either decision (inbox/action.php, max 255 characters).
        var note = el('details', 'sac-note')
        var summary = el('summary', '', text('note', 'Tilføj en note'))
        var noteLabel = el('label', '', text('noteLabel', 'Note til beslutningen (valgfri)'))
        noteLabel.setAttribute('for', 'sac-modal-note')
        var noteField = el('textarea')
        noteField.id = 'sac-modal-note'
        noteField.maxLength = 255
        noteField.rows = 2
        note.appendChild(summary)
        note.appendChild(noteLabel)
        note.appendChild(noteField)
        var aside = el('div', 'sac-modal__aside')
        aside.appendChild(note)
        aside.appendChild(keyHints([['G', text('keyApprove', 'godkend')], ['A', text('keyReject', 'afvis')], ['Esc', text('keyCancel', 'annuller')]]))

        // Only a real click on a button here (or a real G/A key press in this dialog) decides;
        // scripted clicks, synthetic key events and early clicks do nothing.
        function decide (op, event) {
          if (!event || !event.isTrusted || !armed || modal.state.busy || modal.state.closed) return
          modal.state.busy = true
          modal.setKeys(null)
          var pressed = op === 'approve' ? approve : reject
          approve.disabled = true
          reject.disabled = true
          noteField.disabled = true
          pressed.setAttribute('aria-busy', 'true')
          pressed.textContent = op === 'approve' ? text('approving', 'Godkender …') : text('rejecting', 'Afviser …')
          var request = { op: op, item_id: itemId, digest: item.digest }
          var noteText = String(noteField.value || '').trim()
          if (noteText !== '') request.note = noteText.slice(0, 255)
          action(request).then(function (result) {
            pressed.removeAttribute('aria-busy')
            if (!result.ok) {
              pressed.textContent = op === 'approve' ? approveLabel : rejectLabel
              showError(result, item)
              return
            }
            modal.state.busy = false
            modal.setStatus(null)
            modal.setTitle(text('decidedTitle', 'Afgjort forslag'))
            if (typeof result.html === 'string') modal.setContent(result.html)
            var buttons = [closeButton]
            if (op === 'approve') {
              modal.announce(receiptLines(result).join(' '))
              var receipt = result.receipt
              if (receipt && typeof receipt.screen === 'string' && receipt.screen_label) {
                var open = button(receipt.screen_label, 'primary')
                open.addEventListener('click', function () {
                  modal.close()
                  openScreen(receipt.screen)
                })
                buttons.push(open)
              }
            } else {
              modal.announce(text('rejected', 'Afvist') + '. ' + text('nothing', 'Intet er ændret.'))
            }
            end(buttons)
            settle(result)
          })
        }
        approve.addEventListener('click', function (event) { decide('approve', event) })
        reject.addEventListener('click', function (event) { decide('reject', event) })
        modal.setButtons([reject, approve], aside)
        // Esc while working in the open note folds the note away (the text stays); the next Esc closes.
        modal.setEscape(function (event) {
          if (!note.open || !note.contains(event.target)) return false
          note.open = false
          summary.focus()
          return true
        })
        modal.setKeys({
          g: function (event) { decide('approve', event) },
          a: function (event) { decide('reject', event) },
          n: function () {
            note.open = true
            noteField.focus()
          }
        })
      })
    })
  }

  function decideInboxItem (itemId, decision) {
    if (typeof itemId !== 'number' || !isFinite(itemId) || itemId <= 0 || Math.floor(itemId) !== itemId || (decision !== 'approve' && decision !== 'reject')) {
      return Promise.resolve(failure('VALIDATION_ERROR', text('invalid', 'Kontrollér de indtastede oplysninger.')))
    }
    if (modalOpen) {
      return Promise.resolve(failure('CONFLICT', text('busy', 'Der er allerede en godkendelse åben.')))
    }
    var run = decision === 'approve'
      ? approveWithModal(itemId)
      : action({ op: 'preview', item_id: itemId }).then(function (preview) {
        if (!preview.ok) return preview
        if (!preview.item || preview.item.tenant_hash !== cfg.tenantHash) {
          return failure('CONFLICT', text('companyChanged', 'Du har skiftet regnskab i et andet vindue. Genindlæs siden.'))
        }
        return action({ op: 'reject', item_id: itemId, digest: preview.item.digest })
      })
    return run.then(function (result) {
      if (result.ok) refreshInboxBadge()
      return result
    })
  }

  /** Read at most ten items through the session/CSRF/tenant-bound preview endpoint. */
  function getInboxItems (ids) {
    if (!Array.isArray(ids) || ids.length > 10 || ids.some(function (id) {
      return typeof id !== 'number' || !isFinite(id) || id <= 0 || Math.floor(id) !== id
    })) return Promise.resolve([])
    return Promise.all(ids.map(function (id) {
      return action({ op: 'preview', item_id: id }).then(function (preview) {
        var item = preview.item
        if (!preview.ok || !item || item.id !== id || item.tenant_hash !== cfg.tenantHash ||
            !item.card || item.card.item_id !== id || item.card.tenant_hash !== cfg.tenantHash) return null
        return { item_id: id, status: item.status, can_undo: item.can_undo === true, card: item.card }
      }, function () { return null })
    }))
  }

  var assist = root.SaldiAssist && typeof root.SaldiAssist === 'object' ? root.SaldiAssist : (root.SaldiAssist = {})
  assist.getMcpToken = getMcpToken
  assist.getInboxItems = getInboxItems
  assist.decideInboxItem = decideInboxItem
  assist.openInboxItem = openInboxItem
  assist.refreshInboxBadge = refreshInboxBadge
  root.SaldiInbox = { setBadge: setBadge, refresh: refreshInboxBadge }
})(window, document)
