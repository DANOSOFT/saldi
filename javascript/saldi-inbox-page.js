// --- javascript/saldi-inbox-page.js --- ai/mcp-assistant --- 2026.10.06
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The Opgaver page (inbox/index.php) as a list you can work through. Every item is a compact row with its
// full ApprovalCard underneath. Wide enough, the page splits: the rows on the left, the selected card in a
// detail pane on the right. Narrower, the selected row unfolds in place. Arrow keys move, Enter unfolds,
// G and A press the card's own Godkend/Afvis buttons (the card's form posts as before: one decision per
// POST, digest and tenant hash checked server-side), N opens the note. The search box filters the rows on
// the page by title and kind. Without this script every card is simply shown open (see approval_card.css).
// G and A act only for a real (trusted), non-repeated key press, only on a card that is on screen (the
// detail pane, or the unfolded row), only after the person chose that row themselves (click, arrows,
// Enter), and only KEY_ARM_MS after that choice. The row picked automatically on a plain page load is
// treated as chosen KEY_ARM_MS after the load; after a decision redirect (#sac-item-N) nothing is armed
// until the person picks a row. Enter keeps its native meaning on buttons, links, summaries and fields.
//
// 20261006 CL/LH Created.
// 20261007 CL/LH Review fixes: trusted, non-repeated, armed G/A on a visible card only; no armed selection
//                after a decision redirect; Enter is left to buttons, links, summaries and form fields.
(function (root) {
  'use strict'

  var SPLIT_MIN = 1040
  var KEY_ARM_MS = 800 // G and A ignore key presses this long after a selection (and after page load).
  // Elements where Enter (and the browser's own key handling) belongs to the element, not to the list.
  var INTERACTIVE = 'a[href], button, summary, input, select, textarea, label, [contenteditable="true"], [role="button"], [role="link"], [role="tab"], [role="checkbox"], [role="switch"]'

  /** Layout for a list container of the given width. */
  function mode (width) {
    return width >= SPLIT_MIN ? 'split' : 'stack'
  }

  /** Index after moving delta rows from current (-1 = nothing selected) in a list of count rows. */
  function nextIndex (current, delta, count) {
    if (count <= 0) return -1
    if (current < 0) return delta > 0 ? 0 : count - 1
    return Math.min(count - 1, Math.max(0, current + delta))
  }

  /**
   * What a key press means on the page; null when typing in a field, using a modifier, for synthetic
   * (untrusted) events, and for auto-repeat of anything but the arrow keys.
   */
  function keyAction (event, inField) {
    if (!event || event.isTrusted !== true || inField || event.metaKey || event.ctrlKey || event.altKey) return null
    var action = null
    switch (event.key) {
      case 'ArrowUp': action = 'up'; break
      case 'ArrowDown': action = 'down'; break
      case 'Enter': action = 'toggle'; break
      case 'g': case 'G': action = 'approve'; break
      case 'a': case 'A': action = 'reject'; break
      case 'n': case 'N': action = 'note'; break
    }
    if (event.repeat && action !== 'up' && action !== 'down') return null
    return action
  }

  /**
   * May G/A press a decision button now? Only on a selected row the person chose (deliberate), at least
   * KEY_ARM_MS after that choice, whose card is on screen: split mode shows it in the detail pane, stack
   * mode only while the row is unfolded; never on a row the filter hid.
   */
  function decisionArmed (state) {
    if (!state || !state.selected || state.hidden || !state.deliberate) return false
    if (state.mode !== 'split' && !state.open) return false
    return typeof state.now === 'number' && typeof state.since === 'number' && state.now - state.since >= KEY_ARM_MS
  }

  /** Does Enter on this element belong to the element (button, link, summary, field, row head)? */
  function ownsEnter (el) {
    return !!(el && el.closest && el.closest(INTERACTIVE + ', .sai-row__head'))
  }

  /** Row filter: the row's lower-cased title and kind against the trimmed, lower-cased query. */
  function matches (filterText, query) {
    return !query || (filterText || '').indexOf(query) !== -1
  }

  function isField (el) {
    if (!el || !el.tagName) return false
    var tag = el.tagName.toUpperCase()
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable === true
  }

  function init (doc, win) {
    var kindForm = doc.querySelector('[data-sai-kind-form]')
    if (kindForm) {
      var kindSelect = kindForm.querySelector('select')
      if (kindSelect) kindSelect.addEventListener('change', function () { kindForm.submit() })
    }
    var list = doc.querySelector('[data-sai-list]')
    var rowsRoot = doc.querySelector('[data-sai-rows]')
    var detail = doc.querySelector('[data-sai-detail]')
    if (!list || !rowsRoot || !detail) return null

    var rows = Array.prototype.slice.call(rowsRoot.querySelectorAll('.sai-row'))
    var selected = null
    var current = ''
    var deliberate = false // the person chose the selected row (or it is the plain-load start row)
    var since = 0 // when G/A start counting their arm delay for the selected row
    var now = function () { return Date.now() }
    var empty = detail.querySelector('.sai-detail__empty')

    function cardOf (row) { return row.querySelector('.sai-row__card') || row._saiCard || null }
    function headOf (row) { return row.querySelector('.sai-row__head') }
    function visibleRows () { return rows.filter(function (r) { return !r.hidden }) }

    function returnCard (row) {
      var card = row._saiCard
      if (card && card.parentNode !== row) row.appendChild(card)
    }

    function showInDetail (row) {
      var card = cardOf(row)
      if (!card) return
      row._saiCard = card
      if (empty) empty.hidden = true
      detail.appendChild(card)
      detail.scrollTop = 0
    }

    // opts.deliberate: a trusted click or key press chose this row; anything else (load, filter, resize)
    // leaves an unchanged selection as it was and disarms a changed one.
    function select (row, opts) {
      opts = opts || {}
      if (opts.deliberate || selected !== row) {
        deliberate = !!opts.deliberate
        since = now()
      }
      if (selected && selected !== row) {
        selected.classList.remove('is-selected')
        selected.classList.remove('is-open')
        headOf(selected).setAttribute('aria-expanded', 'false')
        if (current === 'split') returnCard(selected)
      }
      selected = row
      if (!row) {
        if (empty) empty.hidden = false
        return
      }
      row.classList.add('is-selected')
      var open = current === 'split' ? true : (opts.toggle ? !row.classList.contains('is-open') : opts.open !== false)
      if (current === 'split') {
        showInDetail(row)
      } else {
        row.classList.toggle('is-open', open)
      }
      headOf(row).setAttribute('aria-expanded', open ? 'true' : 'false')
      if (opts.scroll) row.scrollIntoView({ block: 'nearest' })
      if (opts.focus) headOf(row).focus()
      // Remember the selection for a reload without touching the URL: a fragment change scrolls the page.
      var id = row.getAttribute('data-item-id')
      if (id && win.history && win.history.replaceState) {
        try { win.history.replaceState({ saiItem: id }, '') } catch (e) { /* sandboxed */ }
      }
    }

    function applyMode () {
      var next = mode(list.clientWidth)
      if (next === current) return
      current = next
      list.setAttribute('data-mode', next)
      if (next === 'split') {
        rows.forEach(function (r) { r.classList.remove('is-open') })
        if (selected) { showInDetail(selected); headOf(selected).setAttribute('aria-expanded', 'true') }
        else if (empty) empty.hidden = false
      } else {
        rows.forEach(returnCard)
        if (selected) select(selected, { open: true })
      }
    }

    function move (delta) {
      var visible = visibleRows()
      var index = nextIndex(visible.indexOf(selected), delta, visible.length)
      if (index >= 0) select(visible[index], { open: true, scroll: true, focus: true, deliberate: true })
    }

    function cardShown () {
      return !!selected && !selected.hidden && (current === 'split' || selected.classList.contains('is-open'))
    }

    // In stack mode a click or Enter on the selected row folds it; on a row that was only picked for the
    // person (after a redirect) it first just confirms the choice, so the card stays open.
    function toggles (row) {
      return current === 'stack' && (row !== selected || deliberate)
    }

    function press (name) {
      if (!decisionArmed({ selected: selected, hidden: !!(selected && selected.hidden), deliberate: deliberate, mode: current, open: !!(selected && selected.classList.contains('is-open')), since: since, now: now() })) return
      var card = cardOf(selected)
      var button = card && card.querySelector('button[name="op"][value="' + name + '"]')
      // offsetParent is null for anything not rendered (a collapsed row, a hidden pane).
      if (button && !button.disabled && button.offsetParent !== null) button.click()
    }

    function openNote () {
      var card = cardShown() && cardOf(selected)
      var note = card && card.querySelector('details.sac-note')
      if (!note) return
      note.open = true
      var area = note.querySelector('textarea')
      if (area) area.focus()
    }

    rows.forEach(function (row) {
      var head = headOf(row)
      head.addEventListener('click', function (event) {
        if (event.target && event.target.closest && event.target.closest('a')) return
        select(row, { toggle: toggles(row), deliberate: event.isTrusted === true })
      })
      head.addEventListener('keydown', function (event) {
        // Only the head itself: Enter on a link inside it keeps its native meaning.
        if (event.target !== head || event.repeat) return
        if (event.key === ' ' || event.key === 'Enter') { event.preventDefault(); select(row, { toggle: toggles(row), deliberate: event.isTrusted === true }) }
      })
    })

    doc.addEventListener('keydown', function (event) {
      if (event.defaultPrevented) return
      var action = keyAction(event, isField(event.target))
      if (!action) return
      if (action === 'toggle') {
        // Enter on a button, link, summary or row head is theirs; only Enter on the page itself toggles.
        if (!selected || ownsEnter(event.target)) return
        event.preventDefault(); select(selected, { toggle: toggles(selected), deliberate: true }); return
      }
      event.preventDefault()
      if (action === 'up') move(-1)
      else if (action === 'down') move(1)
      else if (action === 'approve') press('approve')
      else if (action === 'reject') press('reject')
      else if (action === 'note') openNote()
    })

    var search = doc.getElementById('sai-search')
    var none = doc.querySelector('[data-sai-none]')
    if (search) {
      search.addEventListener('input', function () {
        var query = search.value.trim().toLowerCase()
        rows.forEach(function (r) { r.hidden = !matches(r.getAttribute('data-filter'), query) })
        Array.prototype.forEach.call(rowsRoot.querySelectorAll('.sai-group'), function (group) {
          group.hidden = Array.prototype.every.call(group.querySelectorAll('.sai-row'), function (r) { return r.hidden })
        })
        var visible = visibleRows()
        if (none) none.hidden = visible.length > 0
        if (selected && selected.hidden) select(visible[0] || null, { open: true })
      })
    }

    var timer = null
    win.addEventListener('resize', function () {
      if (timer) win.clearTimeout(timer)
      timer = win.setTimeout(applyMode, 120)
    })

    applyMode()
    // The row to start on. After a decision redirect (#sac-item-N) that row if it is still listed, else
    // none: the next row is never put under G/A without the person choosing it. Otherwise the remembered
    // row, else the first, armed KEY_ARM_MS after the load.
    var hash = win.location && /^#sac-item-([0-9]+)$/.exec(win.location.hash || '')
    var remembered = win.history && win.history.state && win.history.state.saiItem
    var wanted = hash ? hash[1] : (remembered ? String(remembered) : '')
    var first = wanted ? rows.filter(function (r) { return r.getAttribute('data-item-id') === wanted })[0] || null : null
    if (!first && !hash) first = visibleRows()[0] || null
    if (first) {
      select(first, { open: true, scroll: !!hash })
      deliberate = !hash
    }
    return { select: select, applyMode: applyMode, rows: rows, press: press }
  }

  root.SaldiInboxPage = { SPLIT_MIN: SPLIT_MIN, KEY_ARM_MS: KEY_ARM_MS, mode: mode, nextIndex: nextIndex, keyAction: keyAction, decisionArmed: decisionArmed, ownsEnter: ownsEnter, matches: matches, init: init }
  if (typeof document !== 'undefined' && document.querySelector && document.querySelector('[data-sai-list]')) {
    init(document, root)
  }
})(typeof window !== 'undefined' ? window : this)
