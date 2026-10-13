<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/support/InvoicePaymentDatabase.php --- 2026.10.12
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
// 20261012 CL/LH Shared isolated PDO fixture for Slice A tests; credentials come from the environment.

require_once __DIR__ . '/../../../includes/stripeIncludes/InvoicePaymentStore.php';
require_once __DIR__ . '/../../../includes/stripeIncludes/InvoicePaymentSchema.php';

trait InvoicePaymentDatabase
{
    private \Saldi\Stripe\InvoicePaymentStore $db;
    private ?string $schema = null;

    protected function openDatabase(): void
    {
        $dsn = getenv('SALDI_STRIPE_TEST_DSN');
        $pdo = new PDO($dsn ?: 'sqlite::memory:', getenv('SALDI_STRIPE_TEST_USER') ?: null, getenv('SALDI_STRIPE_TEST_PASSWORD') ?: null);
        $this->db = new \Saldi\Stripe\InvoicePaymentStore($pdo, static fn() => 1791360000);
        if ($dsn) {
            if ($this->db->driver() !== 'pgsql') throw new RuntimeException('Integration tests require PostgreSQL');
            $this->schema = 'stripe_test_' . bin2hex(random_bytes(8));
            $pdo->exec('CREATE SCHEMA ' . $this->schema);
            $pdo->exec('SET search_path TO ' . $this->schema);
        }
    }

    protected function tearDown(): void
    {
        if ($this->schema !== null) $this->db->pdo->exec('DROP SCHEMA ' . $this->schema . ' CASCADE');
    }
}
