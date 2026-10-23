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
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const source = readFileSync(fileURLToPath(new URL('../../../javascript/saldi-assist-navigate.js', import.meta.url)), 'utf8')

function load (withShell = true) {
  const calls = []
  const window = { location: { hash: '' } }
  if (withShell) window.update_iframe = (uri) => calls.push(uri)
  vm.runInNewContext(source, { window })
  return { navigate: window.SaldiAssistNavigate, calls, window }
}

test('typed SaldiScreens links and bare manifest paths are accepted', () => {
  const { navigate, calls } = load()
  for (const screen of [
    'finans/kassekladde.php?kladde_id=12',
    'finans/kladdeliste.php',
    'debitor/ordre.php?id=7',
    'kreditor/ordre.php?id=7',
    'debitor/debitorkort.php?id=1234567890',
    'kreditor/kreditorkort.php?id=3',
    'lager/varekort.php?id=44',
    'inbox/index.php?item=12',
    'finans/pulje_review.php',
    'debitor/rykker.php',
    'debitor/rapport.php?rapportart=openpost',
    'debitor/rykker.php?rykker_id=12',
    'systemdata/assistent.php',
    'systemdata/assistent_rapport.php',
    'finans/rapport.php',
    'finans/moms_periode.php',
    'debitor/ordreliste.php',
    'debitor/ordreliste.php?valg=ordrer',
    'systemdata/diverse.php?sektion=posoptions',
    'systemdata/diverse.php?sektion=a-b',
    'debitor/ordreliste.php?valg=' + 'a'.repeat(40),
    'admin/backup.php'
  ]) {
    assert.equal(navigate(screen), true, screen)
  }
  assert.deepEqual(calls.slice(0, 2), ['/finans/kassekladde.php?kladde_id=12', '/finans/kladdeliste.php'])
})

test('everything else is refused and never navigates', () => {
  const { navigate, calls } = load()
  for (const screen of [
    'javascript:alert(1)',
    'https://evil.example/x.php',
    '//evil.example/x.php',
    '/finans/kladdeliste.php',
    'index/logud.php',
    'debitor/sletordre.php',
    'lager/delete.php',
    'finans/luk.php',
    'admin/index.php',
    'admin/backup.php?backup=1',
    'admin/backup.php?valg=x',
    'admin/admin_settings.php',
    'admin/nested/read.php',
    'index/logud.php?valg=read',
    'admin/index.php?sektion=read',
    '../index/logud.php',
    'finans/../index/logud.php',
    'finans//kladdeliste.php',
    './finans/kladdeliste.php',
    'finans/kassekladde.php?id=12',
    'finans/kassekladde.php?kladde_id=12&slet=1',
    'finans/kassekladde.php?kladde_id=abc',
    'finans/kassekladde.php?kladde_id=12345678901',
    'finans/kassekladde.php?kladde_id=',
    'finans/kladdeliste.php?x=1',
    'debitor/ordreliste.php?valg=ordrer&slet=1',
    'debitor/ordreliste.php?valg=Ordrer',
    'debitor/ordreliste.php?valg=',
    'systemdata/diverse.php?sektion=' + 'a'.repeat(41),
    'systemdata/diverse.php?sektion=a.b',
    'systemdata/diverse.php?sektion=a%2Db',
    'finans/kassekladde.php?slet=1',
    'inbox/action.php?item=1',
    'debitor/rykker.php?id=12',
    'debitor/rapport.php?rapportart=openpost&slet=1',
    'debitor/rapport.php?rapportart=delete',
    'debitor/rykker.php?rykker_id=12&inkasso=1',
    'finans/pulje_review.php?source_id=3',
    'systemdata/assistent.php?set=1',
    'FINANS/kladdeliste.php',
    'finans/kladdeliste.php#x',
    '',
    null,
    12,
    'a'.repeat(300) + '.php'
  ]) {
    assert.equal(navigate(screen), false, String(screen))
  }
  assert.equal(calls.length, 0)
})

test('without the shell function the hash is used', () => {
  const { navigate, window } = load(false)
  assert.equal(navigate('inbox/index.php?item=3'), true)
  assert.equal(window.location.hash, '/inbox/index.php?item=3')
})

test('isAllowed is exposed for the inbox bridge', () => {
  const { navigate } = load()
  assert.equal(navigate.isAllowed('finans/kassekladde.php?kladde_id=1'), true)
  assert.equal(navigate.isAllowed('finans/kassekladde.php?kladde_id=1&a=b'), false)
})
