// --- tests/includes/ui/saldiAssistNavigate.test.mjs --- ai/mcp-assistant --- 2026.10.02
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// Allowlist of the host navigate bridge (javascript/saldi-assist-navigate.js, SPEC 2.7).
// Run on the host: node --test tests/includes/ui/
//
// 20261002 CL/LH Created.
// 20261002 CDX/LH Cover F1 integration behavior and regression cases.
// 20261002 CDX/LH Cover companion screens and refuse action parameters.
// 20261012 CL/LH Ported to master with the bridge (Guide tier); bare admin/backup.php only.
// 20261027 CL/LH Exact allowlist: every listed screen exists, nothing else passes.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { existsSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const root = fileURLToPath(new URL('../../../', import.meta.url))
const source = readFileSync(root + 'javascript/saldi-assist-navigate.js', 'utf8')

function load (withShell = true) {
  const calls = []
  const window = { location: { hash: '' } }
  if (withShell) window.update_iframe = (uri) => calls.push(uri)
  vm.runInNewContext(source, { window })
  return { navigate: window.SaldiAssistNavigate, calls, window }
}

test('listed screens are accepted and opened through update_iframe', () => {
  const { navigate, calls } = load()
  const screens = [
    'finans/kladdeliste.php',
    'systemdata/kontoplan.php',
    'debitor/debitor.php?valg=debitor',
    'debitor/ordreliste.php?valg=ordrer',
    'systemdata/diverse.php?sektion=sprog',
    'systemdata/stamkort.php',
    'admin/backup.php'
  ]
  for (const screen of screens) {
    assert.equal(navigate(screen), true, screen)
  }
  assert.deepEqual(calls, screens.map((screen) => '/' + screen))
})

test('every listed screen is a page in this repository', () => {
  const { navigate } = load()
  assert.ok(navigate.screens.length > 50)
  for (const screen of navigate.screens) {
    assert.ok(existsSync(root + screen.split('?')[0]), screen)
    assert.doesNotMatch(screen, /logud|slet|delete|luk\.php|^admin\/(?!backup\.php$)/, screen)
  }
})

test('everything else is refused and never navigates', () => {
  const { navigate, calls } = load()
  for (const screen of [
    // Pages that write on a plain GET.
    'lager/lagerstatusmail.php',
    'api/v2/setup_api_key.php',
    'bank_integration/login.php',
    // Deliberately left out of the list.
    'index/logud.php',
    'admin/admin_settings.php',
    'debitor/pos_ordre.php',
    'produktion/ordreliste.php',
    // Real pages that are not navigation destinations.
    'debitor/debitorkort.php',
    'lager/varekort.php',
    'constructor.php',
    // Typed record links and companion screens of the full Assist (not on master).
    'finans/kassekladde.php?kladde_id=12',
    'debitor/ordre.php?id=7',
    'inbox/index.php?item=12',
    'finans/pulje_review.php',
    'debitor/rapport.php?rapportart=openpost',
    'systemdata/assistent.php',
    // Variants of listed screens.
    'admin/backup.php?backup=1',
    'debitor/ordreliste.php?valg=ordrer&slet=1',
    'debitor/ordreliste.php?valg=Ordrer',
    'debitor/ordreliste.php?valg=',
    'debitor/ordreliste.php',
    'systemdata/diverse.php?sektion=sprog#x',
    'finans/kladdeliste.php?x=1',
    'FINANS/kladdeliste.php',
    'finans/kladdeliste.php ',
    'finans/kladdeliste.php\n',
    '/finans/kladdeliste.php',
    './finans/kladdeliste.php',
    'finans//kladdeliste.php',
    'finans/../finans/kladdeliste.php',
    'finans%2fkladdeliste.php',
    // Not screens at all.
    'javascript:alert(1)',
    'https://evil.example/x.php',
    '//evil.example/x.php',
    '__proto__',
    'constructor',
    'hasOwnProperty',
    'toString',
    '',
    null,
    undefined,
    12,
    {},
    ['finans/kladdeliste.php'],
    'a'.repeat(300) + '.php'
  ]) {
    assert.equal(navigate(screen), false, String(screen))
  }
  assert.equal(calls.length, 0)
})

test('without the shell function the hash is used', () => {
  const { navigate, window } = load(false)
  assert.equal(navigate('finans/kladdeliste.php'), true)
  assert.equal(window.location.hash, '/finans/kladdeliste.php')
})

test('isAllowed and the list are exposed, and the list is a copy', () => {
  const { navigate } = load()
  assert.equal(navigate.isAllowed('systemdata/kontoplan.php'), true)
  assert.equal(navigate.isAllowed('systemdata/kontoplan.php?x=1'), false)
  navigate.screens.push('lager/lagerstatusmail.php')
  assert.equal(navigate.isAllowed('lager/lagerstatusmail.php'), false)
})
