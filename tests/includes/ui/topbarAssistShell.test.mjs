// --- tests/includes/ui/topbarAssistShell.test.mjs --- ver 5.1.0 --- 2026.11.13
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// Saldi Assist in the shell, the markup Saldi renders for the loader (doc/hjaelp_saldi_ai.md §23): the topbar and page
// markers, the topbar's "+ Ny chat" (rendered hidden, the loader binds it), its texts and css, the cache versions, and
// the shell's promises to the loader (the topbar's z-index, nothing that would trap the chat in .home-section).
// The markers come only with the help panel on (the pilot switch, §9): without it the chat keeps today's layout.
// Source checks only: the behaviour is in saldiHelpMerged.test.mjs and in the loader's own tests.
// Run on the host: node --test tests/includes/ui/topbarAssistShell.test.mjs (not run in CI).
//
// 20261108 ASR Created.
// 20261109 CL/ASR saldi-help.js ?v=10 (variants, spec §24).
// 20261109 CL/ASR saldi-help.js ?v=11 (page questions, spec §25).
// 20261112 CL/ASR saldi-help.js ?v=12 (a page that redraws in place is read again, spec §22.10).
// 20261113 CL/ASR saldi-help.js ?v=13 (crmdrawer, typing in an inPlace page, ownWords) and saldi-help.css ?v=6: the head's title wraps.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('../../../', import.meta.url))
const read = (f) => readFileSync(root + f, 'utf8')
const TOPBAR = read('index/mainIncludes/topbar.php')
const MAIN = read('index/main.php')
const CSS = read('css/topbar.css')
const HELP = read('javascript/saldi-help.js')
/** How often a marker stands in the markup (the history lines in the header name it too). */
const inMarkup = (src, marker) => src.split('\n').filter((l) => !/^\s*\/\//.test(l)).join('\n').split(marker).length - 1

/** The topbar's two Assist branches: the Assist menu (no help panel) and the Hjælp button (help panel on). */
function branches () {
  const open = "<?php if (empty($ctx['helpPanel'])) { ?>"
  const a = TOPBAR.indexOf(open)
  assert.ok(a > 0, 'the Assist branch')
  const b = TOPBAR.indexOf('<?php } else { ?>', a)
  const c = TOPBAR.indexOf('<?php } ?>', b)
  assert.ok(b > a && c > b, 'the Hjælp branch')
  return { assist: TOPBAR.slice(a + open.length, b), help: TOPBAR.slice(b + '<?php } else { ?>'.length, c) }
}
/** One css rule's declarations by its exact selector, or null. */
function rule (selector) {
  const at = CSS.indexOf('\n' + selector + ' {')
  if (at < 0) return null
  const start = CSS.indexOf('{', at) + 1
  return CSS.slice(start, CSS.indexOf('}', start)).trim()
}

test('the topbar is marked for the loader, once, only with the help panel on (the pilot, §9)', () => {
  assert.ok(TOPBAR.includes(`<header class="topbar" id="topbar"<?= empty($ctx['helpPanel']) ? '' : ' data-saldi-assist-topbar' ?>>`))
  assert.equal(inMarkup(TOPBAR, 'data-saldi-assist-topbar'), 1)
})

test('no "+ Ny chat" in the topbar: closing the chat ends its conversation (14 Nov 2026)', () => {
  assert.equal(inMarkup(TOPBAR, 'data-saldi-assist-new'), 0)
  assert.ok(!HELP.includes('topbar-assist-new'))
  assert.match(TOPBAR, /id="topbar-help-btn" data-txt-resume="<\?= \$t\('9502\|Fortsæt samtalen'\) \?>"/)
})

test('the page area is marked with the help panel on, and is the next element after the topbar in .home-section', () => {
  const div = `<div class="home-content"<?= empty($topbar['helpPanel']) ? '' : ' data-saldi-assist-page' ?>>`
  assert.ok(MAIN.includes(div))
  assert.equal(inMarkup(MAIN, 'data-saldi-assist-page'), 1)
  const at = MAIN.indexOf('<section class="home-section">')
  assert.equal(MAIN.slice(at, MAIN.indexOf(div) + div.length).replace(/\s+/g, ' '), '<section class="home-section"> <?php topbar_render($topbar, (int) $sprog_id); ?> ' + div)
})

test('texts 8796 and 8797 in Danish, English and Norwegian', () => {
  const rows = new Map(read('importfiler/tekster.csv').split('\n').filter((l) => /^\d+\t/.test(l)).map((l) => [l.split('\t')[0], l.split('\t')]))
  assert.deepEqual(rows.get('8796'), ['8796', 'Ny chat', 'New chat', 'Ny chat'])
  assert.deepEqual(rows.get('8797'), ['8797', 'Start en ny samtale med Saldi AI', 'Start a new conversation with Saldi AI', 'Start en ny samtale med Saldi AI'])
})

test('cache versions: topbar.css ?v=24 or later (the brand shell bumped it), saldi-help.js ?v=14, saldi-help.css ?v=6', () => {
  const tb = [...MAIN.matchAll(/<link href='\.\.\/css\/topbar\.css\?v=(\d+)' rel='stylesheet'>/g)]
  assert.equal(tb.length, 1, 'one topbar.css link')
  assert.ok(Number(tb[0][1]) >= 24, 'topbar.css ?v=' + tb[0][1])
  assert.ok(MAIN.includes('<script src="../javascript/saldi-help.js?v=14"></script>'))
  assert.ok(!/saldi-help\.js\?v=(?!14")/.test(MAIN), 'no other version left')
  assert.ok(MAIN.includes("<link href='../css/saldi-help.css?v=6' rel='stylesheet'>"))
})

test('the help panel head wraps a long page name ("Indstillinger: Brugere og ansatte") instead of cutting it off', () => {
  const m = /\.hs-b \.hsb-title \{([^}]*)\}/.exec(read('css/saldi-help.css'))
  assert.ok(m, '.hsb-title')
  assert.ok(!/white-space:\s*nowrap|text-overflow/.test(m[1]), m[1])
  assert.match(m[1], /line-clamp: 3;/, 'at most three lines')
})

test('the button\'s css mirrors .topbar-help with the topbar\'s own variables, and hides while the panel has taken over', () => {
  const btn = rule('.topbar-assist-new')
  assert.ok(btn, '.topbar-assist-new')
  for (const d of ['display: inline-flex', 'height: 34px', 'min-width: 34px', 'border-radius: 10px', 'background: transparent', 'color: var(--topbar-txt)', 'cursor: pointer']) assert.ok(btn.includes(d), d)
  assert.equal(rule('.topbar-assist-new i'), 'font-size: 18px;')
  assert.equal(rule('.topbar-assist-new:hover'), 'background: var(--topbar-hover);')
  assert.equal(rule('.topbar-assist-new:focus-visible'), 'outline: 2px solid var(--topbar-accent); outline-offset: 2px;')
  assert.match(rule('.topbar-assist-new-lbl'), /^display: none; font-family: var\(--font-display\); font-weight: 500; font-size: \.8125rem;/)
  assert.equal(rule('.topbar-assist-new .topbar-assist-new-plus'), 'display: none;')
  assert.ok(CSS.includes('@media (min-width: 1240px) { .topbar-assist-new { padding: 0 11px 0 8px; } .topbar-assist-new-lbl { display: inline; } .topbar-assist-new .topbar-assist-new-ic { display: none; } .topbar-assist-new .topbar-assist-new-plus { display: inline-block; } }'))
  assert.equal(rule('.topbar-assist-new[data-saldi-help-dead]'), 'display: none !important;')
  // [hidden] (the loader's) beats display: inline-flex
  assert.match(CSS, /\.topbar \[hidden\],\s*\n\.topbar-pop \[hidden\] \{\s*display: none !important;/)
  // no new colours: every rule of the button uses the topbar's variables
  // the button's rules end where the next commented block starts (the brand shell's navy bar follows them)
  const from = CSS.indexOf('\n.topbar-assist-new {'), to = CSS.indexOf('\n/*', from + 1)
  const block = CSS.slice(from, to < 0 ? undefined : to)
  assert.ok(!/#[0-9a-f]{3,8}\b|rgba?\(|oklch\(/i.test(block), 'no literal colours')
})

test('what the shell promises the loader: topbar z-index between the chat (40) and its menus (60), no trap in .home-section', () => {
  const bar = rule('.topbar')
  assert.ok(bar && /position: relative;/.test(bar), '.topbar is positioned')
  const z = Number(/z-index: (\d+);/.exec(bar)[1])
  assert.ok(z > 40 && z < 60, 'z-index ' + z)
  // .home-section is the chat's mount parent and .home-content the page area: no transform, filter or contain on the
  // first (they would move the chat's fixed box), no z-index on the second (it would lift the page over the chat)
  for (const f of ['css/sidebar_style.css', 'css/saldi-theme.css', 'css/saldi-help.css', 'css/topbar.css']) {
    const src = read(f)
    for (const m of src.matchAll(/([^{}]*\.home-(?:section|content)[^{}]*)\{([^}]*)\}/g)) {
      if (/\.home-section(?![\w-])(?!\s*\.home-content)\s*$/.test(m[1].trim().split(',').pop())) assert.ok(!/\b(transform|filter|contain|perspective|will-change)\s*:/.test(m[2]), f + ': ' + m[1].trim())
      if (/\.home-content\s*$/.test(m[1].trim())) assert.ok(!/z-index\s*:/.test(m[2]), f + ': ' + m[1].trim())
    }
  }
})
