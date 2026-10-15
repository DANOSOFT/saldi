// --- javascript/saldi-journal-assist.js --- ai/mcp-assistant --- 2026.10.07
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// Kassekladde suggestions (SPEC-companion C8.1). When the user leaves the description, an
// account or the amount of a journal line that has a description but an empty account, this
// asks finans/kassekladde_includes/assistSuggest.php for up to three postings (rules first,
// then the tenant's own history) and shows them in a slim row under the line, together with
// possible duplicates and a "Spørg Assist" button that hands the line to the shell chat.
//
// "Brug" only fills empty fields (D/K type, account, VAT select, VAT exemption, description),
// dispatches input/change so the page's own handlers run, and then keeps the suggested VAT
// code while the page's asynchronous VAT lookups finish. It never touches bilag, dato, beløb or
// faktura and never submits: the line is saved only when the user presses Gem.
//
// Account fields get only `change`, not `input`: accountAutocomplete.js answers `input` by
// opening the blue lookup panel, which is noise after a fill. VAT selects get no events:
// handleVatChange() would ask the one-sided VAT confirm() for an ordinary expense line.
//
// Configuration (kladde id, CSRF token, endpoint, texts) comes from the JSON block
// #saldi-journal-assist-config that finans/kassekladde.php prints when the tenant switch
// assistant/journal_suggestions is on. All text is set with textContent.
//
// 20261002 CL/LH Created.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261007 CL/LH V1 Stille row: source chips carry their kind, and the row is marked busy (Assist's
//                jade dot pulses) while a fresh suggestion for the same line is on its way.
// 20261007 CL/LH The suggestion row spans the widest row of the table, also under a new, unsaved line.
(function (root, doc) {
  'use strict'

  var TRIGGER = /^(besk|debe|kred|belo)(\d+)$/
  var ROW_FIELD = /^(besk|debe|kred|belo|d_ty|k_ty|dvat|kvat|moms)(\d+)$/
  var AMOUNT = /^-?[0-9]{1,12}(?:\.[0-9]{1,3})?$/
  var ACCOUNT = /^[0-9]{1,10}$/
  var JOURNAL_SCREEN = /^finans\/kassekladde\.php\?kladde_id=[0-9]{1,10}$/
  var DEBOUNCE_MS = 250
  var VAT_GUARD_MS = 3000

  // ------------------------------------------------------------------ pure helpers

  /** Danish amount text ("1.250,00", "1250", "-25,5") -> "1250.00" style decimal, or null. */
  function parseAmount (text) {
    var value = String(text == null ? '' : text).replace(/[\s ]/g, '')
    if (value === '') return null
    if (value.indexOf(',') >= 0) {
      value = value.replace(/\./g, '').replace(',', '.')
    } else {
      var dots = value.split('.').length - 1
      // Without a comma a dot is a thousands separator unless it is the only one and is
      // followed by one, two or more than three digits ("12.5" is twelve and a half).
      if (dots > 1 || (dots === 1 && /\.[0-9]{3}$/.test(value))) value = value.replace(/\./g, '')
    }
    if (!AMOUNT.test(value)) return null
    var parts = value.split('.')
    var decimals = (parts[1] || '') + '00'
    return parts[0] + '.' + (parts[1] && parts[1].length === 3 ? parts[1] : decimals.slice(0, 2))
  }

  /** "dd-mm-yyyy", "dd-mm-yy", "ddmmyy", "ddmmyyyy" (also with . or /) -> "YYYY-MM-DD", or null. */
  function parseDate (text) {
    var value = String(text == null ? '' : text).trim()
    var m = value.match(/^([0-9]{1,2})[-./]([0-9]{1,2})[-./]([0-9]{2}|[0-9]{4})$/) ||
      value.match(/^([0-9]{2})([0-9]{2})([0-9]{2}|[0-9]{4})$/)
    if (!m) return null
    var day = Number(m[1])
    var month = Number(m[2])
    var year = Number(m[3].length === 2 ? '20' + m[3] : m[3])
    var check = new Date(Date.UTC(year, month - 1, day))
    if (check.getUTCFullYear() !== year || check.getUTCMonth() !== month - 1 || check.getUTCDate() !== day) return null
    return year + '-' + (month < 10 ? '0' : '') + month + '-' + (day < 10 ? '0' : '') + day
  }

  /** "2026-09-03" -> "03-09-2026"; anything else unchanged. */
  function formatDate (iso) {
    var m = String(iso || '').match(/^([0-9]{4})-([0-9]{2})-([0-9]{2})/)
    return m ? m[3] + '-' + m[2] + '-' + m[1] : String(iso || '')
  }

  /** "1250.00" -> "1.250,00". */
  function formatAmount (decimal) {
    var m = String(decimal || '').match(/^(-?)([0-9]+)(?:\.([0-9]+))?$/)
    if (!m) return String(decimal || '')
    var whole = m[2].replace(/\B(?=([0-9]{3})+(?!\d))/g, '.')
    return m[1] + whole + ',' + ((m[3] || '') + '00').slice(0, 2)
  }

  function daysBetween (a, b) {
    var x = Date.parse(a + 'T00:00:00Z')
    var y = Date.parse(b + 'T00:00:00Z')
    return isFinite(x) && isFinite(y) ? Math.round(Math.abs(x - y) / 86400000) : null
  }

  function ledgerType (ledger) {
    return ledger === 'customer' ? 'D' : (ledger === 'supplier' ? 'K' : 'F')
  }

  function clip (text, max) {
    text = String(text || '')
    return text.length > max ? text.slice(0, max - 1) + '…' : text
  }

  function makeTexts (texts) {
    texts = texts || {}
    return function (id, fallback) {
      var value = texts[String(id)]
      // findtekst() answers "Tekst nr: 6366" for an id that is not in tekster.csv yet; use the fallback then.
      return typeof value === 'string' && value !== '' && value !== 'Tekst nr: ' + id ? value : fallback
    }
  }

  /** One side of a suggestion: "2800 Software · K1" or "K 1005 Adobe". */
  function sideText (side) {
    if (!side) return ''
    var type = ledgerType(side.ledger)
    return (type === 'F' ? '' : type + ' ') + side.account + (side.name ? ' ' + clip(side.name, 28) : '') + (side.vat_code ? ' · ' + side.vat_code : '')
  }

  function chipText (suggestion, T) {
    var parts = []
    if (suggestion.debit) parts.push(T(1000, 'Debet') + ' ' + sideText(suggestion.debit))
    if (suggestion.credit) parts.push(T(1001, 'Kredit') + ' ' + sideText(suggestion.credit))
    return parts.join(' / ')
  }

  function metaText (suggestion, T) {
    if (suggestion.source === 'rule') return T(5963, 'Fra regel')
    var basis = suggestion.basis || {}
    return T(5964, 'Brugt %d gange, senest %s')
      .replace('%d', String(Number(basis.count) || 0))
      .replace('%s', formatDate(basis.last_date))
  }

  function duplicateText (dup, lineDate, T) {
    var text = T(671, 'Bilag').toLowerCase() + ' ' + dup.bilag + ', ' + T(5716, 'Kladde').toLowerCase() + ' ' + dup.kladde_id +
      ', ' + formatDate(dup.date) + ', ' + formatAmount(dup.amount)
    var days = lineDate ? daysBetween(dup.date, lineDate) : null
    if (days) text += ' (' + days + ' ' + T(5969, 'dage') + ')'
    if (dup.posted) text += ', ' + T(637, 'Bogført').toLowerCase()
    return text
  }

  /** Snapshot of a journal line as the page shows it (strings, trimmed). */
  function lineFromValues (v) {
    return {
      bilag: String(v.bila || '').trim(),
      date: String(v.dato || '').trim(),
      description: String(v.besk || '').trim(),
      debitType: String(v.d_ty || '').trim().toUpperCase(),
      debit: String(v.debe || '').trim(),
      creditType: String(v.k_ty || '').trim().toUpperCase(),
      credit: String(v.kred || '').trim(),
      invoice: String(v.fakt || '').trim(),
      amount: String(v.belo || '').trim(),
      lineId: /^[0-9]{1,10}$/.test(String(v.id || '')) ? Number(v.id) : 0
    }
  }

  /** The endpoint's `line`, or null when the row does not qualify (C8.1 trigger rule). */
  function requestLine (row) {
    if (row.description.length < 3 || (row.debit !== '' && row.credit !== '')) return null
    var line = { description: row.description.slice(0, 100) }
    var amount = parseAmount(row.amount)
    if (amount !== null && Number(amount) !== 0) line.amount = amount
    var date = parseDate(row.date)
    if (date) line.date = date
    if (row.invoice !== '' && row.invoice.length <= 30) line.invoice = row.invoice
    if (row.lineId > 0) line.exclude_line_id = row.lineId
    var party = null
    if ((row.debitType === 'D' || row.debitType === 'K') && ACCOUNT.test(row.debit)) {
      party = { ledger: row.debitType === 'D' ? 'customer' : 'supplier', account: row.debit, side: 'debit' }
    } else if ((row.creditType === 'D' || row.creditType === 'K') && ACCOUNT.test(row.credit)) {
      party = { ledger: row.creditType === 'D' ? 'customer' : 'supplier', account: row.credit, side: 'credit' }
    }
    if (party) line.party = party
    return line
  }

  /** The C8.2 askAbout payload for a line. */
  function askPayload (kladdeId, row) {
    function side (type, account) {
      return { type: /^[DFK]$/.test(type) ? type : '', account: ACCOUNT.test(account) ? account : '' }
    }
    var amount = parseAmount(row.amount)
    return {
      kind: 'journal_line',
      kladdeId: kladdeId,
      row: {
        bilag: row.bilag.slice(0, 100),
        date: parseDate(row.date) || row.date.slice(0, 100),
        description: row.description.slice(0, 100),
        amount: amount !== null ? amount : row.amount.slice(0, 100),
        debit: side(row.debitType, row.debit),
        credit: side(row.creditType, row.credit),
        invoice: row.invoice.slice(0, 100)
      }
    }
  }

  /**
   * Fill plan for "Brug": which empty fields get which value. A side is skipped when its account
   * is already filled or its D/K type disagrees. VAT is planned only for an empty select on a
   * side whose account this click fills. Existing VAT values remain under the user's control.
   */
  function fillPlan (current, suggestion) {
    var fill = {}
    var vat = {}
    var exempt = current.moms === true
    var existingVat = (String(current.debe || '').trim() !== '' && String(current.dvat || '') !== '') ||
      (String(current.kred || '').trim() !== '' && String(current.kvat || '') !== '')
    if (suggestion.vat_free === true && !exempt && !existingVat) {
      fill.moms = true
      exempt = true
    }
    ;[['debit', 'd_ty', 'debe', 'dvat'], ['credit', 'k_ty', 'kred', 'kvat']].forEach(function (names) {
      var side = suggestion[names[0]]
      if (!side || !ACCOUNT.test(String(side.account || ''))) return
      var type = ledgerType(side.ledger)
      var currentType = String(current[names[1]] || '').trim().toUpperCase()
      var currentAccount = String(current[names[2]] || '').trim()
      if (currentAccount === '') {
        if (currentType !== '' && currentType !== type) return
        if (currentType === '') fill[names[1]] = type
        fill[names[2]] = side.account
      } else {
        return
      }
      var before = String(current[names[3]] || '')
      if (before === '') vat[names[3]] = exempt || type !== 'F' ? '' : String(side.vat_code || '')
    })
    if (String(current.besk || '').trim() === '' && suggestion.description) fill.besk = String(suggestion.description).slice(0, 100)
    return { fill: fill, vat: vat }
  }

  var api = {
    parseAmount: parseAmount,
    parseDate: parseDate,
    formatDate: formatDate,
    formatAmount: formatAmount,
    chipText: chipText,
    metaText: metaText,
    duplicateText: duplicateText,
    lineFromValues: lineFromValues,
    requestLine: requestLine,
    askPayload: askPayload,
    fillPlan: fillPlan,
    makeTexts: makeTexts
  }
  root.SaldiJournalAssist = api

  // ------------------------------------------------------------------ page wiring

  var cfgElement = doc.getElementById('saldi-journal-assist-config')
  if (!cfgElement) return
  var cfg
  try { cfg = JSON.parse(cfgElement.textContent) } catch (e) { return }
  if (!cfg || typeof cfg.csrf !== 'string' || typeof cfg.kladdeId !== 'number' || cfg.kladdeId < 1) return

  var T = makeTexts(cfg.texts)
  var ENDPOINT = typeof cfg.endpoint === 'string' && cfg.endpoint !== '' ? cfg.endpoint : 'kassekladde_includes/assistSuggest.php'
  var disabled = false
  var filling = false
  var hintShown = false
  var inflight = null
  var busyMark = null
  var states = {}

  function state (n) {
    return states[n] || (states[n] = { n: n, timer: null, key: null, fingerprint: null, tr: null, dismissed: false, used: false, dupWarned: false, guard: null })
  }

  function field (prefix, n) {
    var list = doc.getElementsByName(prefix + n)
    return list && list.length ? list[0] : null
  }

  function rowFields (n) {
    var f = {}
    ;['bila', 'dato', 'besk', 'd_ty', 'debe', 'dvat', 'k_ty', 'kred', 'kvat', 'fakt', 'belo', 'moms'].forEach(function (prefix) {
      f[prefix] = field(prefix, n)
    })
    var id = doc.getElementsByName('id[' + n + ']')
    f.id = id && id.length ? id[0] : null
    return f
  }

  function readRow (n) {
    var f = rowFields(n)
    if (!f.besk) return null
    var values = {}
    for (var key in f) {
      if (Object.prototype.hasOwnProperty.call(f, key) && f[key] && key !== 'moms') values[key] = f[key].value
    }
    return lineFromValues(values)
  }

  function post (body, signal) {
    return root.fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Saldi-Csrf': cfg.csrf },
      body: JSON.stringify(body),
      signal: signal
    })
  }

  function logEvent (event, subject, source) {
    if (disabled || !subject) return
    var body = { op: 'event', event: event, subject: subject }
    if (source) body.source = source
    post(body).then(function () {}, function () {})
  }

  function subjectFor (suggestion) {
    return suggestion.source === 'rule' && suggestion.rule_id ? 'rule:' + suggestion.rule_id : 'kladde:' + cfg.kladdeId
  }

  function askAvailable () {
    try {
      var shell = root.parent
      return !!(shell && shell !== root && shell.SaldiAssist && typeof shell.SaldiAssist.askAbout === 'function')
    } catch (e) {
      return false
    }
  }

  function openScreen (screen) {
    if (!JOURNAL_SCREEN.test(String(screen || ''))) return
    try {
      var shell = root.parent
      if (shell && shell !== root && typeof shell.update_iframe === 'function') {
        shell.update_iframe('/' + screen)
        return
      }
    } catch (e) {}
    root.location.href = '../' + screen
  }

  // ------------------------------------------------------------------ rendering

  function el (tag, className, text) {
    var node = doc.createElement(tag)
    if (className) node.className = className
    if (text !== undefined) node.textContent = text
    return node
  }

  function button (label, className, title) {
    var node = el('button', 'sja-btn' + (className ? ' ' + className : ''), label)
    node.type = 'button'
    if (title) node.title = title
    return node
  }

  function removeRow (st) {
    if (st.tr && st.tr.parentNode) st.tr.parentNode.removeChild(st.tr)
    st.tr = null
    st.fingerprint = null
  }

  function removeAll () {
    for (var n in states) {
      if (Object.prototype.hasOwnProperty.call(states, n)) {
        removeRow(states[n])
        states[n].key = null
      }
    }
  }

  function rowSpan (tr) {
    var span = 0
    var cells = tr.cells || []
    for (var i = 0; i < cells.length; i++) span += cells[i].colSpan || 1
    return span
  }

  /**
   * The widest row of the journal table: a new, unsaved line has fewer cells than a saved one
   * (no Position and delete cells), and the suggestion row must still reach the table's edge.
   */
  function columnCount (lineTr) {
    var table = lineTr.closest ? lineTr.closest('table') : null
    var rows = (table && table.rows) || (lineTr.parentNode && lineTr.parentNode.rows) || [lineTr]
    var span = rowSpan(lineTr)
    for (var i = 0; i < rows.length; i++) {
      if (/(^|\s)sja-row(\s|$)/.test(String(rows[i].className || ''))) continue
      span = Math.max(span, rowSpan(rows[i]))
    }
    return span || 1
  }

  function focusField (n, prefix) {
    var target = field(prefix, n)
    if (target && typeof target.focus === 'function') target.focus()
  }

  function dismiss (st) {
    st.dismissed = true
    var sources = st.sources || []
    removeRow(st)
    logEvent('suggestion_dismissed', 'kladde:' + cfg.kladdeId, sources.length === 1 ? sources[0] : null)
    focusField(st.n, 'besk')
  }

  function ask (st) {
    var row = readRow(st.n)
    if (!row) return
    try {
      root.parent.SaldiAssist.askAbout(askPayload(cfg.kladdeId, row))
    } catch (e) {
      return
    }
    logEvent('ask_from_row', row.lineId > 0 ? 'line:' + row.lineId : 'kladde:' + cfg.kladdeId)
  }

  /** Builds (or rebuilds) the suggestion row under line n. */
  function render (st, suggestions, duplicates, row, options) {
    options = options || {}
    var besk = field('besk', st.n)
    var lineTr = besk && besk.closest ? besk.closest('tr') : null
    if (!lineTr || !lineTr.parentNode) return
    var hadFocus = !!(st.tr && doc.activeElement && st.tr.contains && st.tr.contains(doc.activeElement))
    var showAsk = row.description !== '' && askAvailable()
    if (!suggestions.length && !duplicates.length && !showAsk && !options.hint) {
      removeRow(st)
      return
    }

    var tr = el('tr', 'sja-row')
    tr.setAttribute('data-sja-row', String(st.n))
    var td = el('td', 'sja-cell')
    td.colSpan = columnCount(lineTr)
    var bar = el('div', 'sja-bar')
    bar.setAttribute('role', 'group')
    bar.setAttribute('aria-label', T(5960, 'Forslag'))
    td.appendChild(bar)
    tr.appendChild(td)

    if (suggestions.length) {
      bar.appendChild(el('span', 'sja-label', T(5960, 'Forslag')))
      suggestions.slice(0, 3).forEach(function (suggestion) {
        var chip = el('span', 'sja-chip')
        var label = chipText(suggestion, T)
        chip.appendChild(el('span', 'sja-chip__text', label))
        chip.appendChild(el('span', 'sja-chip__meta sja-src--' + (suggestion.source === 'rule' ? 'rule' : 'history'), metaText(suggestion, T)))
        var use = button(T(5961, 'Brug'), 'sja-use', label)
        use.addEventListener('click', function () { apply(st, suggestion) })
        chip.appendChild(use)
        bar.appendChild(chip)
      })
    }
    if (options.hint) bar.appendChild(el('span', 'sja-hint', T(5967, 'Linjen er udfyldt, men ikke gemt. Tryk Gem, når den er rigtig.')))
    var tools = el('span', 'sja-tools')
    if (showAsk) {
      var askButton = button(T(5966, 'Spørg Assist'), 'sja-ask', T(6014, 'Spørg Assist om denne linje'))
      askButton.setAttribute('aria-label', T(6014, 'Spørg Assist om denne linje'))
      askButton.addEventListener('click', function () { ask(st) })
      tools.appendChild(askButton)
    }
    if (suggestions.length || duplicates.length || options.hint) {
      var hide = button(T(5962, 'Skjul'), 'sja-hide')
      hide.addEventListener('click', function () { dismiss(st) })
      tools.appendChild(hide)
    }
    bar.appendChild(tools)

    if (duplicates.length) {
      var dupLine = el('div', 'sja-dup')
      dupLine.appendChild(el('span', 'sja-dup__label', T(5965, 'Mulig dublet:') + ' '))
      var lineDate = parseDate(row.date)
      duplicates.slice(0, 3).forEach(function (dup, index) {
        if (index > 0) dupLine.appendChild(el('span', '', '; '))
        var link = el('a', 'sja-dup__link', duplicateText(dup, lineDate, T))
        var screen = String(dup.screen || '')
        if (JOURNAL_SCREEN.test(screen)) {
          link.setAttribute('href', '../' + screen)
          link.addEventListener('click', function (event) {
            event.preventDefault()
            openScreen(screen)
          })
        }
        dupLine.appendChild(link)
      })
      td.appendChild(dupLine)
    }

    // Esc on any control in the row hides it, like Skjul.
    tr.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' || event.key === 'Esc') {
        event.preventDefault()
        if (event.stopPropagation) event.stopPropagation()
        dismiss(st)
      }
    })

    if (st.tr && st.tr.parentNode) {
      st.tr.parentNode.replaceChild(tr, st.tr)
    } else {
      lineTr.parentNode.insertBefore(tr, lineTr.nextSibling)
    }
    st.tr = tr
    st.sources = []
    suggestions.forEach(function (s) { if (st.sources.indexOf(s.source) < 0) st.sources.push(s.source) })
    if (hadFocus) {
      var first = tr.querySelector('button')
      if (first) first.focus()
    }
  }

  function show (st, result, row) {
    var suggestions = Array.isArray(result.suggestions) ? result.suggestions.slice(0, 3) : []
    var duplicates = Array.isArray(result.duplicates) ? result.duplicates : []
    var fingerprint = JSON.stringify([suggestions, duplicates])
    if (st.tr && st.fingerprint === fingerprint) return
    render(st, suggestions, duplicates, row)
    if (!st.tr) return
    st.fingerprint = fingerprint
    st.lastResult = { suggestions: suggestions, duplicates: duplicates }
    var logged = {}
    suggestions.forEach(function (s) {
      if (logged[s.source]) return
      logged[s.source] = true
      logEvent('suggestion_shown', subjectFor(s), s.source === 'rule' || s.source === 'history' ? s.source : null)
    })
    if (duplicates.length && !st.dupWarned) {
      st.dupWarned = true
      logEvent('duplicate_warned', 'line:' + duplicates[0].line_id)
    }
  }

  // ------------------------------------------------------------------ requests

  function request (st) {
    st.timer = null
    if (disabled || st.dismissed || st.used) return
    var row = readRow(st.n)
    var line = row ? requestLine(row) : null
    if (!line) {
      removeRow(st)
      st.key = null
      return
    }
    var key = JSON.stringify(line)
    if (key === st.key) return
    st.key = key
    send(st, row, line, true)
  }

  function send (st, row, line, allowRetry) {
    if (inflight) inflight.abort()
    var controller = typeof AbortController === 'function' ? new AbortController() : null
    inflight = controller
    var key = st.key
    // One request at a time: a request for another line aborts this one, so its row stops pulsing.
    var busyRow = st.tr
    if (busyMark && busyMark !== busyRow) busyMark.removeAttribute('data-sja-busy')
    busyMark = busyRow
    if (busyRow) busyRow.setAttribute('data-sja-busy', '')
    function idle () { if (busyRow && !inflight) busyRow.removeAttribute('data-sja-busy') }
    post({ op: 'suggest', kladde_id: cfg.kladdeId, line: line }, controller ? controller.signal : undefined).then(function (response) {
      return response.json().then(function (json) { return { status: response.status, json: json } }, function () { return { status: response.status, json: null } })
    }).then(function (result) {
      if (inflight === controller) inflight = null
      idle()
      if (st.key !== key || st.dismissed || st.used) return
      var json = result.json
      if (json && json.ok === true && json.enabled === false) {
        disabled = true
        removeAll()
        return
      }
      if (json && json.ok === true) {
        show(st, json, row)
        return
      }
      if (result.status === 401 || result.status === 403) {
        disabled = true
        removeAll()
        return
      }
      // A party that is closed or unknown fails validation; ask again on the text alone.
      if (allowRetry && line.party && json && json.error && json.error.code === 'VALIDATION_ERROR') {
        var plain = {}
        for (var k in line) if (Object.prototype.hasOwnProperty.call(line, k) && k !== 'party') plain[k] = line[k]
        send(st, row, plain, false)
        return
      }
      st.key = null
    }, function () {
      if (inflight === controller) inflight = null
      idle()
      if (st.key === key) st.key = null
    })
  }

  function schedule (n) {
    if (disabled) return
    var st = state(n)
    if (st.dismissed || st.used) return
    if (st.timer) clearTimeout(st.timer)
    st.timer = setTimeout(function () { request(st) }, DEBOUNCE_MS)
  }

  // ------------------------------------------------------------------ Brug

  function setValue (node, value) {
    if (!node) return false
    if (node.type === 'checkbox') {
      node.checked = !!value
    } else {
      node.value = value
    }
    return true
  }

  function fire (node, type) {
    var event
    try {
      event = new root.Event(type, { bubbles: true })
    } catch (e) {
      event = doc.createEvent('Event')
      event.initEvent(type, true, false)
    }
    node.dispatchEvent(event)
  }

  function applyVat (f, vat) {
    var changed = false
    for (var name in vat) {
      if (!Object.prototype.hasOwnProperty.call(vat, name)) continue
      var select = f[name]
      if (!select) continue
      var target = vat[name]
      if (select.options) {
        var found = target === ''
        for (var i = 0; i < select.options.length; i++) if (select.options[i].value === target) found = true
        if (!found) continue
      }
      if (select.value !== target) {
        select.value = target
        changed = true
      }
      if (select.dataset) select.dataset.previousValue = target
    }
    return changed
  }

  function stopGuard (st) {
    if (st.guard) {
      clearInterval(st.guard.timer)
      st.guard = null
    }
  }

  /**
   * The page resets the VAT selects asynchronously after an account change (jQuery lookup_vat and
   * accountAutocomplete's exact lookup). Re-apply the planned codes until those settle, unless the
   * user touches the row's account, type or VAT fields first.
   */
  function guardVat (st, f, vat) {
    stopGuard(st)
    applyVat(f, vat)
    var started = Date.now()
    st.guard = {
      timer: setInterval(function () {
        applyVat(f, vat)
        if (Date.now() - started > VAT_GUARD_MS) stopGuard(st)
      }, 100)
    }
  }

  function apply (st, suggestion) {
    var f = rowFields(st.n)
    var current = {}
    ;['besk', 'd_ty', 'debe', 'dvat', 'k_ty', 'kred', 'kvat'].forEach(function (k) { current[k] = f[k] ? f[k].value : '' })
    current.moms = !!(f.moms && f.moms.checked)
    var plan = fillPlan(current, suggestion)
    filling = true
    try {
      ;['d_ty', 'k_ty', 'debe', 'kred', 'moms', 'besk'].forEach(function (name) {
        if (!Object.prototype.hasOwnProperty.call(plan.fill, name) || !setValue(f[name], plan.fill[name])) return
        if (name !== 'debe' && name !== 'kred' && name !== 'moms') fire(f[name], 'input')
        fire(f[name], 'change')
      })
    } finally {
      filling = false
    }
    guardVat(st, f, plan.vat)
    root.docChange = true // the page's leave-warning flag (inline onchange handlers set it too)
    st.used = true
    logEvent('suggestion_used', subjectFor(suggestion), suggestion.source === 'rule' || suggestion.source === 'history' ? suggestion.source : null)

    var duplicates = st.lastResult ? st.lastResult.duplicates : []
    var row = readRow(st.n)
    var firstHint = !hintShown
    hintShown = true
    if (row && (firstHint || duplicates.length)) {
      st.fingerprint = null
      render(st, [], duplicates, row, { hint: firstHint })
    } else {
      removeRow(st)
    }
    focusField(st.n, 'belo')
  }

  // ------------------------------------------------------------------ events

  function onLeave (event) {
    if (filling || disabled) return
    var target = event.target
    var m = target && typeof target.name === 'string' ? target.name.match(TRIGGER) : null
    if (m) schedule(m[2])
  }

  doc.addEventListener('focusout', onLeave, true)
  doc.addEventListener('change', onLeave, true)

  // The user's own edits: a new description re-arms a used row; touching the account, type or
  // VAT fields stops the VAT guard so their choice wins.
  function onUserEdit (event) {
    if (filling || !event.isTrusted) return
    var target = event.target
    var m = target && typeof target.name === 'string' ? target.name.match(ROW_FIELD) : null
    if (!m || !states[m[2]]) return
    var st = states[m[2]]
    if (m[1] !== 'besk' && m[1] !== 'belo') stopGuard(st)
    if (m[1] === 'besk' && event.type === 'input' && st.used) {
      st.used = false
      st.key = null
    }
  }

  doc.addEventListener('input', onUserEdit, true)
  doc.addEventListener('focusin', onUserEdit, true)
  doc.addEventListener('mousedown', onUserEdit, true)

  // Sortable renumbers every row of the tbody after a drag; take the suggestion rows out first.
  doc.addEventListener('mousedown', function (event) {
    var target = event.target
    if (target && target.closest && target.closest('.drag-handle')) removeAll()
  }, true)
})(window, document)
