<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiMailerTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
//
// Regression coverage for the owned procedure, mail and digest services.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiReachTestCase.php';
require_once __DIR__ . '/../../../includes/automation/SaldiMailer.php';

final class SaldiMailerTest extends SaldiReachTestCase
{
    public function testTenantSmtpAndSenderOverridesReachTheTransportWithoutLogging(): void
    {
        $password = TestCredentials::password('mailer');
        $this->company(['host' => 'smtp.example.test:2525', 'user' => 'fixture-user',
            'password' => $password, 'encryption' => 'tls']);
        $this->setting('email_settings', 'sender_email', 'override@example.test');
        $this->setting('email_settings', 'sender_name', 'Økonomi');
        $calls = [];
        $mailer = new SaldiMailer(static function (array $config, array $message) use (&$calls): bool {
            $calls[] = [$config, $message];
            return true;
        });
        self::assertSame(['status' => 'sent'], $mailer->send(
            $this->env->db,
            'recipient@example.test',
            'Dagens overblik',
            "<script>\nÆble & pære"
        ));
        self::assertCount(1, $calls);
        [$config, $message] = $calls[0];
        self::assertSame('smtp.example.test:2525', $config['host']);
        self::assertSame('fixture-user', $config['user']);
        self::assertSame($password, $config['password']);
        self::assertSame('tls', $config['encryption']);
        self::assertSame('override@example.test', $config['from_email']);
        self::assertSame('Økonomi', $config['from_name']);
        self::assertStringNotContainsString('<script>', $message['html']);
        self::assertStringContainsString('&lt;script&gt;', $message['html']);
        self::assertStringContainsString('Æble &amp; pære', $message['html']);
    }

    public function testFallbackSenderKillSwitchAndSanitizedErrors(): void
    {
        $this->company(['host' => 'localhost']);
        $calls = 0;
        $mailer = new SaldiMailer(static function (array $config) use (&$calls): bool {
            $calls++;
            self::assertSame('localhost', $config['host']);
            self::assertSame('sender@example.test', $config['from_email']);
            self::assertSame('Fixture company', $config['from_name']);
            throw new RuntimeException(TestCredentials::password('mailer_error'));
        });
        putenv('SALDI_MAIL_DISABLED=1');
        self::assertSame(['status' => 'skipped'], $mailer->send($this->env->db, 'to@example.test', 'Subject', 'Body'));
        self::assertSame(0, $calls);
        putenv('SALDI_MAIL_DISABLED');
        self::assertSame(['status' => 'failed', 'error' => 'mail delivery failed'], $mailer->send(
            $this->env->db,
            'to@example.test',
            'Subject',
            'Body'
        ));
        self::assertSame(1, $calls);
        self::assertSame('failed', $mailer->send($this->env->db, 'bad-address', 'Subject', 'Body')['status']);
        $badHeader = $mailer->send($this->env->db, 'to@example.test', "Header\r\nInjected", 'Body');
        self::assertSame('failed', $badHeader['status']);
        self::assertSame(1, $calls);
    }

    public function testOptionalLocalMailpitIntegration(): void
    {
        if (getenv('SALDI_TEST_MAILPIT') !== '1') {
            self::assertNotSame('1', getenv('SALDI_TEST_MAILPIT'));
            return;
        }
        $host = getenv('SMTP_HOST');
        self::assertContains($host, ['mailpit', 'localhost', '127.0.0.1']);
        $port = (int)getenv('SMTP_PORT');
        self::assertGreaterThan(0, $port);
        $this->company(['host' => $host . ':' . $port]);
        self::assertSame(['status' => 'sent'], (new SaldiMailer())->send(
            $this->env->db,
            'recipient@example.test',
            'Saldi local integration',
            'Only synthetic fixture data.'
        ));
    }
}
