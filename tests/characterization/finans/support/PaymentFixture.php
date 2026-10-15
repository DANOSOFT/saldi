<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/finans/support/PaymentFixture.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Disposable debtor-payment fixtures.


final class PaymentFixture
{
    public const SESSION_ID = 'mct2b7paymentsession000000000001';

    public static function session(): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $year = self::query($tenant, "SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")[0]['year'];
        self::query($master, 'DELETE FROM online WHERE session_id=$1', [self::SESSION_ID]);
        self::query($master, 'INSERT INTO online (session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id) VALUES ($1,$2,$3,$4,$5,$6,$7,false,0)', [self::SESSION_ID,'chartest',CharacterizationEnv::testDb(),CharacterizationEnv::pgUser(),str_repeat('9',50),$year,(string)time()]);
        pg_close($tenant);
        pg_close($master);
    }

    public static function dropSession(): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        self::query($master, 'DELETE FROM online WHERE session_id=$1 AND db=$2', [self::SESSION_ID,CharacterizationEnv::testDb()]);
        pg_close($master);
    }

    /** @return array{year:int,bank:int,debtor:int,number:int,collective:int} */
    public static function seed($connection): array
    {
        $row = self::query($connection, "SELECT kodenr FROM grupper WHERE art='RA' ORDER BY kodenr DESC LIMIT 1")[0];
        $year = (int)$row['kodenr'];
        $group = self::query($connection, "SELECT kodenr,box2 FROM grupper WHERE art='DG' AND fiscal_year=$year ORDER BY kodenr LIMIT 1")[0];
        $bank = (int)self::query($connection, "SELECT kontonr FROM kontoplan WHERE regnskabsaar=$year AND kontotype IN ('D','S') AND COALESCE(moms,'')='' ORDER BY kontonr DESC LIMIT 1")[0]['kontonr'];
        $debtor = (int)self::query($connection, "INSERT INTO adresser (kontonr,firmanavn,art,gruppe) VALUES ('987654','Payment fixture','D',$1) RETURNING id", [$group['kodenr']])[0]['id'];
        return ['year' => $year, 'bank' => $bank, 'debtor' => $debtor, 'number' => 987654, 'collective' => (int)$group['box2']];
    }

    public static function invoice($connection, array $fixture, string $number, string $amount = '1000.00', string $currency = 'DKK', string $date = '2026-01-15'): int
    {
        $order = self::query($connection, "INSERT INTO ordrer (konto_id,kontonr,art,status,fakturanr,fakturadate,valuta,sum,moms) VALUES ($1,$2,'DO',4,$3,$4,$5,$6,0) RETURNING id", [$fixture['debtor'], $fixture['number'], $number, $date, $currency, $amount])[0]['id'];
        return (int)self::query($connection, "INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,refnr,udlignet,transdate,forfaldsdate,valuta,valutakurs,projekt,kladde_id,udlign_id) VALUES ($1,$2,$3,$4,$5,'0',$6,$6,$7,100,0,0,0) RETURNING id", [$fixture['debtor'], $fixture['number'], $number, $amount, $order, $date, $currency])[0]['id'];
    }

    public static function journal($connection, array $fixture, string $number, string $amount, string $date = '2026-03-15'): int
    {
        $journal = (int)self::query($connection, "INSERT INTO kladdeliste (kladdedate,bogfort,kladdenote,oprettet_af) VALUES ($1,'','chartest','chartest') RETURNING id", [$date])[0]['id'];
        self::query($connection, "INSERT INTO kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,faktura,projekt,afd,ansat,valuta,ordre_id,momsfri) VALUES (1,$1,'Payment fixture','F',$2,'D',$3,$4,$5,$6,0,0,0,0,0,'on')", [$date,$fixture['bank'],$fixture['number'],$amount,$journal,$number]);
        return $journal;
    }

    /** @return list<array<string,string|null>> */
    public static function query($connection, string $sql, array $params = []): array
    {
        $result = pg_query_params($connection, $sql, $params);
        if (!$result) {
            throw new RuntimeException(pg_last_error($connection));
        }
        return pg_fetch_all($result) ?: [];
    }
}
