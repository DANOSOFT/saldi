<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/AssistConsentTest.test.php --- ver 5.0.0 --- 2026.10.08
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261008 CL/LH Created for the mobile Assist contract.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../includes/saldi_assist_consent.php';
final class AssistConsentTest extends SaldiRuntimeDbTestCase
{
	private function server(string $method = 'GET'): array
	{
		$_SESSION ??= [];
		return ['REQUEST_METHOD' => $method, 'HTTP_HOST' => 'saldi.test', 'HTTP_ORIGIN' => 'https://saldi.test', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_SALDI_CSRF' => saldi_inbox_csrf_token()];
	}
	public function testWebAndSettingsShareConsentAndFailClosedOnCsrfOriginAndRevisor(): void
	{
		$ctx = $this->env->ctx(); $session = $this->env->session($ctx); $server = $this->server();
		self::assertSame(['status' => 200, 'body' => ['consent' => null]], saldi_assist_consent_handle($server, '', $session));
		foreach (['yes', 'no'] as $value) {
			$r = saldi_assist_consent_handle($this->server('POST'), json_encode(['consent' => $value]), $session);
			self::assertSame(['status' => 200, 'body' => ['consent' => $value]], $r);
			self::assertSame($value, SaldiSettings::getUser($this->env->db, $ctx, 'assistant', 'improvement_consent'));
		}
		SaldiSettings::setUser($this->env->db, $ctx, 'assistant', 'improvement_consent', 'yes');
		self::assertSame('yes', saldi_assist_consent_handle($server, '', $session)['body']['consent']);
		foreach (['HTTP_ORIGIN' => 'https://foreign.test', 'HTTP_X_SALDI_CSRF' => ''] as $name => $value) {
			$bad = $server; $bad[$name] = $value; self::assertSame(403, saldi_assist_consent_handle($bad, '', $session)['status']);
		}
		self::assertSame(401, saldi_assist_consent_handle($server, '', '')['status']);
		self::assertSame(422, saldi_assist_consent_handle($this->server('POST'), '{"consent":"on"}', $session)['status']);
		self::assertSame(403, saldi_assist_consent_handle($server, '', $this->env->session($ctx, true))['status']);
		self::assertSame(405, saldi_assist_consent_handle($this->server('DELETE'), '', $session)['status']);
	}
}
