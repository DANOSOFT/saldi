// --- javascript/saldi-journal-check.js --- ai/mcp-assistant --- 2026.10.07
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// "Tjek kladden" (SPEC-companion-2 E3, U4). Adds a button next to Gem on an editable kassekladde.
// Pressing it posts op 'check' to finans/kassekladde_includes/assistSuggest.php and shows the
// findings in a quiet panel above the journal table: errors that stop posting first, then things
// that should be checked, or one line saying the kladde looks ready. Findings with the same code
// and message are shown once with all their lines. Each finding has a Vis link that marks its rows
// (the data-saldi-assist-highlight attribute the shell's highlightRows uses) and scrolls to them;
// pressing it again moves on to the next marked row.
//
// The check is read-only and reads the saved lines. When the page has unsaved changes (the page's
// own docChange flag, or a later edit) the panel says so. While the panel is open it is re-run
// automatically after the page reloads (Gem), so a fix and a save show the new result at once.
// Never submits, never touches the form fields.
//
// Configuration (kladde id, CSRF token, endpoint, texts) is the JSON block
// #saldi-journal-assist-config that finans/kassekladde.php prints for javascript/saldi-journal-assist.js.
// All text is set with textContent.
//
// 20261004 CL/LH Created.
// 20261004 CDX/LH Use Vis links, retain unsaved edits and discard responses after closing the panel.
// 20261007 CL/LH V1 Stille panel: caption head with the check time and Tjek igen, counted section
//                titles; the panel follows the table's width when the frame narrows (docked chat).
// 20261007 CL/LH The toolbar trigger is an input[type=button] (120 px) so it matches Gem and Simulér.
(function (root, doc) {
  'use strict'

  var HIGHLIGHT = 'data-saldi-assist-highlight'
  var STORE_PREFIX = 'saldi-journal-check:'
  var MAX_BILAG = 6

  // ------------------------------------------------------------------ pure helpers

  function makeTexts (texts) {
    texts = texts || {}
    return function (id, fallback) {
      var value = texts[String(id)]
      // findtekst() answers "Tekst nr: 6366" for an id that is not in tekster.csv yet; use the fallback then.
      return typeof value === 'string' && value !== '' && value !== 'Tekst nr: ' + id ? value : fallback
    }
  }

  function validIds (list) {
    var out = []
    ;(Array.isArray(list) ? list : []).forEach(function (id) {
      if (typeof id === 'number' && id > 0 && Math.floor(id) === id && out.indexOf(id) < 0) out.push(id)
    })
    return out
  }

  /**
   * Findings with the same code and message become one entry with all their lines and vouchers,
   * in first-seen order. Entries without a message are dropped.
   */
  function groupFindings (findings) {
    var groups = []
    var index = {}
    ;(Array.isArray(findings) ? findings : []).forEach(function (finding) {
      if (!finding || typeof finding.message_da !== 'string' || finding.message_da === '') return
      var key = String(finding.code || '') + '\u0000' + finding.message_da
      var group = index[key]
      if (!group) {
        group = { code: String(finding.code || ''), message: finding.message_da, lineIds: [], bilag: [] }
        index[key] = group
        groups.push(group)
      }
      validIds(finding.line_ids).forEach(function (id) { if (group.lineIds.indexOf(id) < 0) group.lineIds.push(id) })
      var bilag = finding.bilag
      if (typeof bilag === 'number' && Math.floor(bilag) === bilag && group.bilag.indexOf(bilag) < 0) group.bilag.push(bilag)
    })
    return groups
  }

  /** "Bilag 7", "Bilag 3, 5, 9", "Bilag 1, 2, 3, 4, 5, 6 +2"; '' without vouchers. */
  function bilagLabel (bilag, T) {
    if (!bilag || !bilag.length) return ''
    var shown = bilag.slice(0, MAX_BILAG).join(', ')
    return T(671, 'Bilag') + ' ' + shown + (bilag.length > MAX_BILAG ? ' +' + (bilag.length - MAX_BILAG) : '')
  }

  /** "13:04" for the head's "Tjekket kl. %s". */
  function timeLabel (date) {
    if (!date || typeof date.getTime !== 'function' || !isFinite(date.getTime())) return ''
    var h = date.getHours()
    var m = date.getMinutes()
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m
  }

  /** Label of a finding's row link. */
  function showLabel (group, T) {
    return T(1133, 'Vis')
  }

  /** Normalises the endpoint answer; null when it is not a check result. */
  function readResult (json) {
    if (!json || json.ok !== true || !Array.isArray(json.errors) || !Array.isArray(json.warnings)) return null
    return { errors: groupFindings(json.errors), warnings: groupFindings(json.warnings), ready: json.ready === true }
  }

  var api = {
    makeTexts: makeTexts,
    groupFindings: groupFindings,
    bilagLabel: bilagLabel,
    showLabel: showLabel,
    readResult: readResult,
    timeLabel: timeLabel
  }
  root.SaldiJournalCheck = api

  // ------------------------------------------------------------------ page wiring

  var cfgElement = doc.getElementById('saldi-journal-assist-config')
  if (!cfgElement) return
  var cfg
  try { cfg = JSON.parse(cfgElement.textContent) } catch (e) { return }
  if (!cfg || typeof cfg.csrf !== 'string' || typeof cfg.kladdeId !== 'number' || cfg.kladdeId < 1) return

  var T = makeTexts(cfg.texts)
  var ENDPOINT = typeof cfg.endpoint === 'string' && cfg.endpoint !== '' ? cfg.endpoint : 'kassekladde_includes/assistSuggest.php'
  var STORE_KEY = STORE_PREFIX + cfg.kladdeId
  var panel = null
  var stale = false
  var running = false
  var trigger = null
  var activeItem = null
  var edited = false
  var requestId = 0

  function el (tag, className, text) {
    var node = doc.createElement(tag)
    if (className) node.className = className
    if (text !== undefined) node.textContent = text
    return node
  }

  function button (label, className) {
    var node = el('button', className, label)
    node.type = 'button'
    return node
  }

  function remember (open) {
    try {
      if (open) root.sessionStorage.setItem(STORE_KEY, '1')
      else root.sessionStorage.removeItem(STORE_KEY)
    } catch (e) {}
  }

  function remembered () {
    try { return root.sessionStorage.getItem(STORE_KEY) === '1' } catch (e) { return false }
  }

  function journalTable () {
    var tbody = doc.getElementById('kassekladde-tbody')
    return tbody && tbody.closest ? tbody.closest('table') : null
  }

  /** The journal rows of the given saved line ids, in page order. */
  function rowsFor (lineIds) {
    var rows = []
    var tbody = doc.getElementById('kassekladde-tbody')
    if (!tbody) return rows
    function add (id, node) {
      if (lineIds.indexOf(id) < 0) return
      var tr = node && node.closest ? node.closest('tr') : null
      if (tr && tbody.contains(tr) && rows.indexOf(tr) < 0) rows.push(tr)
    }
    var buttons = doc.querySelectorAll('.delete-line-btn[data-id]')
    for (var i = 0; i < buttons.length; i++) add(Number(buttons[i].getAttribute('data-id')), buttons[i])
    var hidden = doc.querySelectorAll('input[type="hidden"]')
    for (var j = 0; j < hidden.length; j++) {
      if (/^id\[[0-9]+\]$/.test(String(hidden[j].name || ''))) add(Number(hidden[j].value), hidden[j])
    }
    var pageRows = tbody.querySelectorAll('tr')
    return rows.sort(function (a, b) { return Array.prototype.indexOf.call(pageRows, a) - Array.prototype.indexOf.call(pageRows, b) })
  }

  function clearHighlight () {
    var marked = doc.querySelectorAll('[' + HIGHLIGHT + ']')
    for (var i = 0; i < marked.length; i++) marked[i].removeAttribute(HIGHLIGHT)
    if (activeItem) {
      activeItem.button.removeAttribute('data-active')
      activeItem = null
    }
  }

  /** First press marks the rows and scrolls to the first; further presses step through them. */
  function showRows (item) {
    // An edit lets the shell drop the marks; the next press then marks the rows again.
    if (activeItem !== item || !item.rows.length || item.rows[0].getAttribute(HIGHLIGHT) === null) {
      clearHighlight()
      item.rows = rowsFor(item.group.lineIds)
      if (!item.rows.length) return
      item.rows.forEach(function (row) { row.setAttribute(HIGHLIGHT, '') })
      item.next = 0
      activeItem = item
      item.button.setAttribute('data-active', '')
    }
    var row = item.rows[item.next % item.rows.length]
    item.next += 1
    if (row && typeof row.scrollIntoView === 'function') row.scrollIntoView({ block: 'center', behavior: 'smooth' })
  }

  function closePanel () {
    requestId += 1
    setRunning(false)
    clearHighlight()
    if (panel && panel.parentNode) panel.parentNode.removeChild(panel)
    panel = null
    remember(false)
    if (trigger && typeof trigger.focus === 'function') trigger.focus()
  }

  function setStale () {
    if (!panel || stale) return
    stale = true
    var note = panel.querySelector('.sjc-note')
    if (!note) panel.insertBefore(el('p', 'sjc-note', T(6291, 'Tjekket gælder kun de gemte linjer.')), panel.children[1] || null)
  }

  /** The panel is never wider than the journal table (which shrinks when Saldi Assist docks). */
  function fitPanel () {
    var table = journalTable()
    if (panel && table && table.offsetWidth) panel.style.maxWidth = table.offsetWidth + 'px'
  }

  /**
   * Replaces (or creates) the panel above the journal table with the given body nodes.
   * options.checkedAt adds "Tjekket kl. 13:04" to the head, options.rerun the Tjek igen button.
   */
  function renderPanel (nodes, options) {
    options = options || {}
    var table = journalTable()
    if (!table || !table.parentNode) return
    clearHighlight()
    var next = el('section', 'sjc-panel')
    next.setAttribute('role', 'region')
    next.setAttribute('aria-label', T(6140, 'Tjek kladden'))
    next.setAttribute('aria-live', 'polite')
    next.setAttribute('tabindex', '-1')
    var head = el('div', 'sjc-head')
    head.appendChild(el('span', 'sjc-title', T(6140, 'Tjek kladden')))
    if (options.checkedAt) {
      var when = timeLabel(options.checkedAt)
      if (when) head.appendChild(el('span', 'sjc-when', T(6367, 'Tjekket kl. %s').replace('%s', when)))
    }
    if (options.rerun) {
      var again = button(T(6366, 'Tjek igen'), 'sjc-btn sjc-rerun')
      again.addEventListener('click', runCheck)
      head.appendChild(again)
    }
    var hide = button(T(5962, 'Skjul'), 'sjc-btn sjc-hide')
    hide.addEventListener('click', closePanel)
    head.appendChild(hide)
    next.appendChild(head)
    if (stale) next.appendChild(el('p', 'sjc-note', T(6291, 'Tjekket gælder kun de gemte linjer.')))
    nodes.forEach(function (node) { next.appendChild(node) })
    next.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' || event.key === 'Esc') {
        event.preventDefault()
        closePanel()
      }
    })
    if (table.offsetWidth) next.style.maxWidth = table.offsetWidth + 'px'
    var hadFocus = doc.activeElement === trigger || (panel && panel.contains(doc.activeElement))
    if (panel && panel.parentNode) panel.parentNode.replaceChild(next, panel)
    else table.parentNode.insertBefore(next, table)
    panel = next
    if (hadFocus) panel.focus({ preventScroll: true })
  }

  function section (groups, severity, title) {
    var box = el('div', 'sjc-section sjc-section--' + severity)
    var heading = el('div', 'sjc-section__title')
    heading.appendChild(el('span', 'sjc-section__name', title))
    heading.appendChild(el('span', 'sjc-section__count', String(groups.length)))
    box.appendChild(heading)
    var list = el('ul', 'sjc-list')
    groups.forEach(function (group) {
      var li = el('li', 'sjc-item')
      li.appendChild(el('span', 'sjc-msg', group.message))
      if (group.bilag.length) li.appendChild(el('span', 'sjc-meta', bilagLabel(group.bilag, T)))
      if (group.lineIds.length && rowsFor(group.lineIds).length) {
        var item = { group: group, rows: [], next: 0, button: null }
        var row = rowsFor(group.lineIds)[0]
        if (!row.getAttribute('id')) row.setAttribute('id', 'saldi-journal-check-line-' + group.lineIds[0])
        item.button = el('a', 'sjc-show', showLabel(group, T))
        item.button.setAttribute('href', '#' + row.getAttribute('id'))
        item.button.title = T(6292, 'Markér linjerne i kladden')
        item.button.addEventListener('click', function (event) { event.preventDefault(); showRows(item) })
        li.appendChild(item.button)
      }
      list.appendChild(li)
    })
    box.appendChild(list)
    return box
  }

  function renderResult (result) {
    var nodes = []
    if (result.errors.length) nodes.push(section(result.errors, 'error', T(6142, 'Fejl, der stopper bogføringen')))
    if (result.warnings.length) nodes.push(section(result.warnings, 'warning', T(6143, 'Bør tjekkes')))
    if (!nodes.length) nodes.push(el('p', 'sjc-clean', T(6141, 'Ingen fund. Kladden ser klar ud.')))
    renderPanel(nodes, { checkedAt: new Date(), rerun: true })
  }

  function renderMessage (className, text, options) {
    renderPanel([el('p', className, text)], options)
  }

  function setRunning (on) {
    running = on
    if (trigger) {
      trigger.disabled = on
      trigger.setAttribute('aria-busy', on ? 'true' : 'false')
    }
  }

  function runCheck () {
    if (running) return
    var currentRequest = ++requestId
    var hadFocus = doc.activeElement === trigger || (panel && panel.contains(doc.activeElement))
    setRunning(true)
    stale = edited || root.docChange === true
    remember(true)
    renderMessage('sjc-busy', T(6293, 'Tjekker kladden …'))
    if (hadFocus && panel) panel.focus({ preventScroll: true })
    root.fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Saldi-Csrf': cfg.csrf },
      body: JSON.stringify({ op: 'check', kladde_id: cfg.kladdeId })
    }).then(function (response) {
      return response.json().then(function (json) {
        if (response.status < 200 || response.status >= 300) return { error: json && json.error }
        return json
      }, function () { return null })
    }).then(function (json) {
      if (currentRequest !== requestId) return
      setRunning(false)
      var result = readResult(json)
      if (result) {
        renderResult(result)
        return
      }
      var message = json && json.error && typeof json.error.message_da === 'string' && json.error.message_da !== '' ? json.error.message_da : ''
      renderMessage('sjc-failed', T(6294, 'Kladden kunne ikke tjekkes lige nu.') + (message ? ' ' + message : ''), { rerun: true })
    }, function () {
      if (currentRequest !== requestId) return
      setRunning(false)
      renderMessage('sjc-failed', T(6294, 'Kladden kunne ikke tjekkes lige nu.'), { rerun: true })
    })
  }

  /** The button goes into its own cell right after Gem's cell in the page's button row. */
  function addButton () {
    var save = doc.querySelector('input[name="save"]')
    var cell = save && save.closest ? save.closest('td') : null
    if (!cell || !cell.parentNode) return false
    var td = el('td', 'sjc-cell')
    td.setAttribute('align', 'center')
    // An <input type="button"> like its neighbours (Gem, Simulér): the page's legacy button style
    // only reaches input[type=submit|button], so a <button> would be the one odd white button in the row.
    trigger = el('input', 'button gray medium sjc-trigger')
    trigger.type = 'button'
    trigger.value = T(6140, 'Tjek kladden')
    trigger.style.width = '120px'
    trigger.title = T(6295, 'Tjek de gemte linjer for fejl, før du bogfører. Intet ændres.')
    trigger.addEventListener('click', runCheck)
    td.appendChild(trigger)
    cell.parentNode.insertBefore(td, cell.nextSibling)
    return true
  }

  function start () {
    if (!addButton()) return
    if (remembered()) runCheck()
  }

  // A later edit makes an open result stale; the shell clears the row marks itself.
  function onEdit (event) {
    var target = event.target
    if (!target || (panel && panel.contains(target))) return
    var table = journalTable()
    if (table && table.contains(target) && /^(INPUT|SELECT|TEXTAREA)$/.test(String(target.tagName || ''))) {
      edited = true
      setStale()
    }
  }
  doc.addEventListener('input', onEdit, true)
  doc.addEventListener('change', onEdit, true)
  if (typeof root.addEventListener === 'function') root.addEventListener('resize', fitPanel)

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', start)
  else start()
})(window, document)
