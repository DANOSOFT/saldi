<?php
// tests/characterization/includes/LukFallbackTest.test.php
//
// includes/luk.php closes a popup. When the browser does not let it (a window the page did not open), it moved on
// to the returside after a second with a meta refresh. A message carried into luk.php in Saldi's message box
// (javascript/saldi-dialog.js) does not block like alert(), so the page moved on before it was read. The fallback
// now waits while a message is open (SaldiDialog.busy()); the meta refresh is only for a browser without script.
// The script itself is exercised in a browser by e2e/saldi-dialog-carry.spec.mjs.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261126 ASR Created for review M1.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class LukFallbackTest extends CharacterizationTestCase
{
    public function test_the_fallback_waits_while_a_saldi_message_is_open(): void
    {
        $html = self::runChild('run_luk_page.php', [CharacterizationEnv::sessionId(),
            json_encode(['popup' => '1', 'returside' => 'rapport.php?rapportart=openpost'])])['stdout'];

        $this->assertStringContainsString('window.close()', $html, 'the popup is closed first, as before');
        $this->assertStringContainsString('SaldiDialog.busy()', $html, 'the fallback waits while a message is open');
        $this->assertStringContainsString('setTimeout(go, 1000); })("rapport.php?rapportart=openpost");', $html, 'and then moves on to the returside');
        $this->assertStringNotContainsString('http-equiv="refresh"', preg_replace('#<noscript>.*?</noscript>#s', '', $html),
            'no meta refresh moves on while a message is open');
        $this->assertStringContainsString('<noscript><meta http-equiv="refresh" content="1;URL=rapport.php?rapportart=openpost"></noscript>', $html,
            'a browser without script moves on as before');
    }
}
