<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/einvoiceProviderSetting.test.php --- ver 5.1.1 --- 2026-11-12 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261112 CL/MMR SD-815 Created. The guards of Indstillinger > Diverse > E-faktura (systemdata/diverseIncludes/einvoiceProvider.php) without a database: the value whitelist, the server flag, the rights, the CSRF token and the registration check.

declare(strict_types=1);

namespace SaldiTests\Einvoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../systemdata/diverseIncludes/einvoiceProvider.php';
require_once __DIR__ . '/../../../includes/einvoice/semanticSend.php';

final class einvoiceProviderSetting extends TestCase
{
    private string $token = '';

    protected function setUp(): void
    {
        $this->token = bin2hex(random_bytes(32));
    }

    /**
     * A request that passes every guard, with $changes applied.
     *
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function request(array $changes = array()): array
    {
        return array_merge(array(
            'visible' => true,
            'rettigheder' => '1111111111111111',
            'sessionToken' => $this->token,
            'postedToken' => $this->token,
            'posted' => 'semantic',
            'current' => 'easyubl',
            'state' => array('semanticAllowed' => true, 'reason' => ''),
        ), $changes);
    }

    /**
     * @return array<string, array{0: mixed, 1: string|null}>
     */
    public static function postedValues(): array
    {
        return array(
            'easyubl' => array('easyubl', 'easyubl'),
            'semantic' => array('semantic', 'semantic'),
            'case and spaces' => array(' Semantic ', 'semantic'),
            'empty' => array('', null),
            'another provider' => array('nemhandel', null),
            'sql' => array("semantic' OR '1'='1", null),
            'html' => array('<script>semantic</script>', null),
            'array' => array(array('semantic'), null),
            'number' => array(1, null),
            'null' => array(null, null),
        );
    }

    #[DataProvider('postedValues')]
    public function testOnlyWhitelistedValuesAreAccepted(mixed $posted, ?string $expected): void
    {
        $this->assertSame($expected, einvoice_provider_normalize($posted));
        $decision = einvoice_provider_save_decision($this->request(array('posted' => $posted, 'current' => 'none')));
        if ($expected === null) {
            $this->assertFalse($decision['save']);
            $this->assertSame('invalid', $decision['reason']);
            $this->assertNull($decision['value']);
        } else {
            $this->assertTrue($decision['save']);
            $this->assertSame($expected, $decision['value']);
        }
    }

    public function testEveryAcceptedValueIsOneTheSendSwitchUnderstands(): void
    {
        $this->assertSame('easyubl', einvoice_provider_options()[0], 'the default comes first');
        foreach (einvoice_provider_options() as $option) {
            $this->assertSame($option === 'semantic', semantic_provider_is_semantic($option));
        }
    }

    public function testChoiceIsHiddenUnlessTheServerEnablesIt(): void
    {
        $this->assertTrue(einvoice_provider_choice_visible('1', true));
        $this->assertTrue(einvoice_provider_choice_visible('on', true));
        $this->assertTrue(einvoice_provider_choice_visible(' TRUE ', true));
        $this->assertFalse(einvoice_provider_choice_visible('1', false), 'no Semantic connection on the server');
        $this->assertFalse(einvoice_provider_choice_visible('0', true), 'flag off: customers do not see it before launch');
        $this->assertFalse(einvoice_provider_choice_visible('', true));
        $this->assertFalse(einvoice_provider_choice_visible('enabled', true));
    }

    public function testHiddenChoiceIsNeverSaved(): void
    {
        $decision = einvoice_provider_save_decision($this->request(array('visible' => false)));
        $this->assertSame(array('save' => false, 'value' => null, 'reason' => 'hidden'), $decision);
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function rights(): array
    {
        return array(
            'all rights' => array('1111111111111111', true),
            'only Indstillinger' => array('0100000000000000', true),
            'no Indstillinger' => array('1011111111111111', false),
            'empty (online.php lets it through)' => array('', false),
            'short' => array('1', false),
            'null' => array(null, false),
            'not a string' => array(array('1', '1'), false),
        );
    }

    #[DataProvider('rights')]
    public function testOnlyUsersWithTheIndstillingerRightMaySave(mixed $rettigheder, bool $allowed): void
    {
        $this->assertSame($allowed, einvoice_provider_can_edit($rettigheder));
        $decision = einvoice_provider_save_decision($this->request(array('rettigheder' => $rettigheder)));
        $this->assertSame($allowed, $decision['save']);
        if (!$allowed) {
            $this->assertSame('no_rights', $decision['reason']);
        }
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function tokens(): array
    {
        return array(
            'missing in the form' => array('session', null),
            'empty in the form' => array('session', ''),
            'another token' => array('session', 'other'),
            'no session token' => array(null, 'session'),
            'both empty' => array('', ''),
            'array' => array('session', array('session')),
        );
    }

    #[DataProvider('tokens')]
    public function testTheFormMustCarryTheSessionsToken(mixed $session, mixed $posted): void
    {
        $map = function (mixed $value): mixed {
            if ($value === 'session') {
                return $this->token;
            }
            return ($value === 'other') ? bin2hex(random_bytes(32)) : $value;
        };
        $decision = einvoice_provider_save_decision($this->request(array('sessionToken' => $map($session), 'postedToken' => $map($posted))));
        $this->assertFalse($decision['save']);
        $this->assertSame('csrf', $decision['reason']);
    }

    public function testGuardsComeBeforeTheValueIsRead(): void
    {
        $decision = einvoice_provider_save_decision($this->request(array('rettigheder' => '', 'postedToken' => '', 'posted' => 'bogus')));
        $this->assertSame('no_rights', $decision['reason'], 'rights first');
        $decision = einvoice_provider_save_decision($this->request(array('postedToken' => '', 'posted' => 'bogus')));
        $this->assertSame('csrf', $decision['reason'], 'then the token, then the value');
    }

    public function testTheSameValueIsNotWrittenAgain(): void
    {
        $decision = einvoice_provider_save_decision($this->request(array('posted' => 'semantic', 'current' => 'semantic')));
        $this->assertFalse($decision['save']);
        $this->assertSame('unchanged', $decision['reason']);
    }

    public function testSemanticIsRefusedWhileRegisteredAtEasyUbl(): void
    {
        $db = 'test_42';
        $state = einvoice_provider_state(array_fill_keys(semantic_settings_names(), ''), '6f1c1f9e-1111-4222-8333-444455556666', $db);
        $this->assertTrue($state['easyUbl']);
        $this->assertFalse($state['semantic']);
        $this->assertFalse($state['semanticAllowed']);
        $this->assertSame('registered_elsewhere', $state['reason']);

        $decision = einvoice_provider_save_decision($this->request(array('state' => $state)));
        $this->assertFalse($decision['save']);
        $this->assertSame('refused_registered_elsewhere', $decision['reason']);

        $back = einvoice_provider_save_decision($this->request(array('state' => $state, 'posted' => 'easyubl', 'current' => 'semantic')));
        $this->assertTrue($back['save'], 'going back to EasyUBL is always possible');
    }

    public function testSemanticStateOfAFreshACopiedAndARegisteredRegnskab(): void
    {
        $empty = array_fill_keys(semantic_settings_names(), '');
        $fresh = einvoice_provider_state($empty, '', 'test_42');
        $this->assertSame(array('easyUbl' => false, 'semantic' => false, 'semanticAllowed' => true, 'reason' => ''), $fresh);

        $zeroCompany = einvoice_provider_state($empty, '00000000-0000-0000-0000-000000000000', 'test_42');
        $this->assertFalse($zeroCompany['easyUbl'], 'the zero id EasyUBL answers on a failed registration is no registration');

        $registered = array_merge($empty, array('companyId' => '6f1c1f9e-1111-4222-8333-444455556666', 'dbName' => 'test_42'));
        $own = einvoice_provider_state($registered, '', 'test_42');
        $this->assertTrue($own['semantic']);
        $this->assertTrue($own['semanticAllowed']);

        $copy = einvoice_provider_state($registered, '', 'test_42_copy');
        $this->assertFalse($copy['semanticAllowed']);
        $this->assertSame('copy', $copy['reason']);
        $decision = einvoice_provider_save_decision($this->request(array('state' => $copy)));
        $this->assertSame('refused_copy', $decision['reason']);

        $unknown = einvoice_provider_state(array_merge($empty, array('companyId' => '6f1c1f9e-1111-4222-8333-444455556666')), '', 'test_42');
        $this->assertSame('db_unknown', $unknown['reason']);
    }

    public function testMissingStateRefusesSemantic(): void
    {
        $decision = einvoice_provider_save_decision($this->request(array('state' => array())));
        $this->assertFalse($decision['save']);
        $this->assertSame('refused_unknown', $decision['reason']);
    }
}
