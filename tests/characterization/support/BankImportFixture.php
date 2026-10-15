<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/BankImportFixture.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Created.

require_once __DIR__ . '/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/mobileUpdates.php';

/** Disposable bank-import fixture shared by the two per-file test processes. */
abstract class BankImportFixture extends PHPUnit\Framework\TestCase
{
    protected static $tenant;
    protected static string $session;

    public static function setUpBeforeClass(): void
    {
        if (($reason = CharacterizationEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        mobileTenantUpdates(self::$tenant);
        self::$session = 'bank' . bin2hex(random_bytes(14));
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        CharacterizationEnv::rows($master, 'INSERT INTO online (session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES ($1,$2,$3,$4,$5,$6,$7,false,0)',
            [self::$session, 'chartest', CharacterizationEnv::testDb(), CharacterizationEnv::pgUser(), str_repeat('9',50),
                CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")['year'], (string)time()]);
        pg_close($master);
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO kontoplan(kontonr,beskrivelse,regnskabsaar,kontotype) VALUES (6471,'Bank',987,'D')");
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE art='VK'");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper(art,kodenr,box1) VALUES ('VK',1,'EUR')");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO ordrer(art,kontonr,fakturanr,fakturadate,sum,moms,betalings_id,betalt)
            VALUES ('DO',1001,'42','2026-09-01',800,200,'ha_00042','0')");
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            pg_close(self::$tenant);
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            CharacterizationEnv::rows($master, 'DELETE FROM online WHERE session_id=$1', [self::$session]);
            pg_close($master);
            CharacterizationEnv::teardownTenant();
        }
    }

    /** @return int Fixture journal id. */
    protected function journal(): int
    {
        return (int)CharacterizationEnv::one(self::$tenant, "INSERT INTO kladdeliste(kladdedate,bogfort,kladdenote,oprettet_af)
            VALUES ('2026-09-14','-','bank-char','chartest') RETURNING id")['id'];
    }

    /** @return array{exit:int,stdout:string,stderr:string} Child page output with legacy warnings visible. */
    protected function runImport(string $runner, int $journal, array $options = []): array
    {
        $result = CharacterizationEnv::runChild(__DIR__ . '/' . $runner, [json_encode($options), $journal, self::$session]);
        $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        return $result;
    }

    /** @return list<array<string,mixed>> Persisted rows, excluding only generated row and journal ids. */
    protected function journalRows(int $journal, string $table = 'kassekladde'): array
    {
        $rows = CharacterizationEnv::rows(self::$tenant, 'SELECT * FROM ' . $table . ' WHERE kladde_id=$1 ORDER BY id', [$journal]);
        foreach ($rows as &$row) {
            unset($row['id'], $row['kladde_id']);
        }
        return $rows;
    }
    /** @return void Compare every persisted column to the pre-change fixture. */
    protected function assertSnapshot(string $name, array $rows): void
    {
        $path = __DIR__ . '/../../fixtures/bank/legacy-' . $name . '.tsv';
        $expected = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $expected[] = json_decode(explode("\t", $line, 2)[1], true, 512, JSON_THROW_ON_ERROR);
        }
        $this->assertSame($expected, $rows, $name);
    }

    /** @return void Inject a checked identity write failure after the legacy journal writes. */
    protected function rejectIdentityWrite(bool $enabled): void
    {
        if ($enabled) {
            CharacterizationEnv::rows(self::$tenant, "CREATE FUNCTION bank_test_reject() RETURNS trigger LANGUAGE plpgsql AS \$\$
                BEGIN IF NEW.text='Rejected bank line' THEN RAISE EXCEPTION 'fixture rejected'; END IF; RETURN NEW; END \$\$");
            CharacterizationEnv::rows(self::$tenant, 'CREATE TRIGGER bank_test_reject BEFORE INSERT ON bank_lines FOR EACH ROW EXECUTE FUNCTION bank_test_reject()');
        } else {
            CharacterizationEnv::rows(self::$tenant, 'DROP TRIGGER bank_test_reject ON bank_lines');
            CharacterizationEnv::rows(self::$tenant, 'DROP FUNCTION bank_test_reject()');
        }
    }

}
