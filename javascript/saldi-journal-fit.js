// --- javascript/saldi-journal-fit.js --- ai/mcp-assistant --- 2026.10.07
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// Editable kassekladde with Saldi Assist docked in the shell. The docked panel narrows the frame, and
// the journal (Bilagstekst inputs of 300 px) and the note field (750 px) then no longer fit: focusing a
// field scrolls the journal sideways and cuts the Bilag column.
//
// While the panel is docked and only while something really overflows, this takes back just the
// overflow from the Bilagstekst inputs (down to 140 px; the table against its scroll container) and
// from the note field (down to 160 px; the page against the frame). It is measured on the real page,
// so extra columns (projekt, afdeling, valuta) are covered too. Without a docked panel, outside the
// shell or when everything fits, nothing is changed; closing the panel puts the page's own widths
// back. Never touches values, never submits.
//
// 20261007 CL/LH Created (replaces a fixed 100vw rule that applied to every user and window width).
(function (root, doc) {
  'use strict'

  var MARK = 'data-sjf-width'
  var BESK = /^besk[0-9]+$/

  function widthOf (node) {
    var width = parseFloat(node.style.width)
    return isFinite(width) && width > 0 ? width : node.offsetWidth
  }

  function setWidth (node, px) {
    if (node.getAttribute(MARK) === null) node.setAttribute(MARK, node.style.width || '')
    node.style.width = px + 'px'
  }

  /** Puts back the page's own widths on every field this script changed. */
  function restore (nodes) {
    nodes.forEach(function (node) {
      var original = node.getAttribute(MARK)
      if (original === null) return
      node.style.width = original
      node.removeAttribute(MARK)
    })
  }

  /**
   * Shrinks each group ({ nodes, min, overflow }) in order by what its own overflow() reports, until
   * that is 0, the minimum is reached or a step does not help. A step that does not reduce the
   * overflow is undone, so fields that are not what overflows keep their width. Returns the largest
   * overflow left.
   */
  function fitGroups (groups) {
    var left = 0
    groups.forEach(function (group) {
      var nodes = group.nodes
      var over = group.overflow()
      for (var step = 0; step < 3 && over > 0; step++) {
        var before = nodes.map(widthOf)
        var beforeStyle = nodes.map(function (node) { return node.style.width })
        var beforeMark = nodes.map(function (node) { return node.getAttribute(MARK) })
        var changed = false
        for (var i = 0; i < nodes.length; i++) {
          var next = Math.max(group.min, Math.floor(before[i] - over))
          if (next < before[i]) {
            setWidth(nodes[i], next)
            changed = true
          }
        }
        if (!changed) break
        var after = group.overflow()
        if (after >= over) {
          for (var j = 0; j < nodes.length; j++) {
            nodes[j].style.width = beforeStyle[j]
            if (beforeMark[j] === null) nodes[j].removeAttribute(MARK)
          }
          break
        }
        over = after
      }
      left = Math.max(left, over)
    })
    return left
  }

  /** True when the shell around this frame shows Saldi Assist docked at the side. */
  function assistDocked (win) {
    try {
      var shell = win.parent
      if (!shell || shell === win || !shell.document) return false
      var container = shell.document.getElementById('saldi-chat-container')
      return !!container && container.getAttribute('data-layout') === 'docked'
    } catch (e) {
      return false
    }
  }

  function journalFields (d) {
    var out = []
    var tbody = d.getElementById('kassekladde-tbody')
    var inputs = tbody ? tbody.querySelectorAll('input') : []
    for (var i = 0; i < inputs.length; i++) if (BESK.test(String(inputs[i].name || ''))) out.push(inputs[i])
    return out
  }

  function noteFields (d) {
    var list = d.getElementsByName('ny_kladdenote')
    return list && list.length ? [list[0]] : []
  }

  /**
   * How much wider the journal table is than the box it sits in. The table scrolls inside its own
   * container (.kassekladde-scroll-container), so the document's width alone does not show it.
   */
  function tableOverflow (d) {
    var tbody = d.getElementById('kassekladde-tbody')
    var table = tbody && tbody.closest ? tbody.closest('table') : null
    if (!table) return 0
    for (var box = table.parentNode; box && box.nodeType === 1; box = box.parentNode) {
      if (box.clientWidth > 0) return Math.max(0, table.offsetWidth - box.clientWidth)
    }
    return 0
  }

  /** How much wider the page is than the frame (the note row). */
  function pageOverflow (d) {
    var de = d.documentElement
    var body = d.body
    return Math.max(0, de.scrollWidth - de.clientWidth, body ? body.scrollWidth - body.clientWidth : 0)
  }

  /** One pass on the page: restore, then fit while Saldi Assist is docked. Returns the overflow left. */
  function fit (win, d) {
    var besk = journalFields(d)
    var note = noteFields(d)
    restore(besk.concat(note))
    if (!assistDocked(win)) return 0
    return fitGroups([
      { nodes: besk, min: 140, overflow: function () { return tableOverflow(d) } },
      { nodes: note, min: 160, overflow: function () { return pageOverflow(d) } }
    ])
  }

  root.SaldiJournalFit = { fitGroups: fitGroups, restore: restore, assistDocked: assistDocked, fit: fit }

  if (!doc || !doc.documentElement || typeof root.addEventListener !== 'function') return

  var queued = false
  function schedule () {
    if (queued) return
    queued = true
    var run = function () {
      queued = false
      fit(root, doc)
    }
    if (typeof root.requestAnimationFrame === 'function') root.requestAnimationFrame(run)
    else setTimeout(run, 16)
  }

  // Docking or closing the panel resizes this frame, so resize covers both.
  root.addEventListener('resize', schedule)
  root.addEventListener('load', schedule)
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', schedule)
  else schedule()
})(window, document)
