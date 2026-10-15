<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/StockFixture.php --- ver 5.0.0 --- 2026.10.06
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

/** Disposable stock rows and a local-only webshop recorder, shared by the stock tests. */
final class StockFixture
{
    public static $tenant;
    public static string $log = '';
    public static string $url = '';
    public static string $sessionId = '';
    private static $server;

    public static function start(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            throw new RuntimeException($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        self::$sessionId = bin2hex(random_bytes(16));
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $year = (int)CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS ra FROM grupper WHERE art='RA'")['ra'];
        CharacterizationEnv::rows($master, 'INSERT INTO online
            (session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES($1,$2,$3,$4,$5,$6,$7,false,0)',
            [self::$sessionId,'chartest',CharacterizationEnv::testDb(),CharacterizationEnv::pgUser(),str_repeat('9',50),$year,(string)time()]);
        pg_close($master);
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$log = tempnam(sys_get_temp_dir(), 'stock-stub-');
        self::$url = 'http://' . $address . '/shop_api_stub.php';
        $env = getenv();
        $env['SALDI_STOCK_STUB_LOG'] = self::$log;
        self::$server = proc_open([PHP_BINARY, '-S', $address, '-t', __DIR__ . '/../../fixtures/shop'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', self::$log . '.server', 'a'], 2 => ['file', self::$log . '.server', 'a']], $pipes, null, $env);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $probe = curl_init('http://' . $address . '/ready');
            curl_setopt_array($probe, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>100]);
            $ready = curl_exec($probe) !== false;
            curl_close($probe);
            if ($ready) {
                break;
            }
            usleep(20000);
        }
        self::sql("DELETE FROM grupper WHERE art IN ('API','LG')");
        $ra = (int)self::one("SELECT max(kodenr) AS ra FROM grupper WHERE art='RA'")['ra'];
        self::sql("INSERT INTO grupper(art,kodenr,beskrivelse,box4,box8,fiscal_year) VALUES
            ('API','1','Local shop',$1,'',$2),('LG','1','One','','',$2),('LG','2','Two','','',$2),
            ('VG','98760','Stock fixture','','on',$2)", [self::$url, $ra]);
        self::sql("UPDATE grupper SET box5='',box6='' WHERE art='API'");
        self::sql("DELETE FROM settings WHERE var_name IN ('confirmStockChange','confirmDescriptionChange')");
        $dir = CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb();
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
    }

    public static function stop(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        if (self::$tenant) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            CharacterizationEnv::rows($master, 'DELETE FROM online WHERE session_id=$1 AND db=$2',
                [self::$sessionId,CharacterizationEnv::testDb()]);
            pg_close($master);
            pg_close(self::$tenant);
            CharacterizationEnv::teardownTenant();
        }
        foreach ([self::$log, self::$log . '.server'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** @return list<array<string,string|null>> */
    public static function sql(string $sql, array $params = []): array
    {
        return CharacterizationEnv::rows(self::$tenant, $sql, $params);
    }

    /** @return array<string,string|null> */
    public static function one(string $sql, array $params = []): array
    {
        return self::sql($sql, $params)[0];
    }

    public static function item(int $id, int $quantity = 0, bool $assembled = false): void
    {
        self::sql("INSERT INTO varer(id,varenr,beskrivelse,gruppe,kostpris,salgspris,beholdning,samlevare,serienr,lukket,
            varenr_alias,stregkode,m_type,m_rabat,delvare,retail_price,colli_webfragt,enhed)
            VALUES($1,$2,'Stock fixture',98760,4,8,$3,$4,'','','','','percent',0,'',8,0,'stk')",
            [$id, 'ST-' . $id, $quantity, $assembled ? 'on' : '']);
        self::sql('INSERT INTO shop_varer(saldi_id,shop_id) VALUES($1,$1)', [$id]);
    }

    public static function stock(int $id, int $lager, string $quantity, int $variant = 0): void
    {
        self::sql('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($1,$2,$3,$4)', [$id,$variant,$lager,$quantity]);
    }

    public static function batch(int $id, int $lager, string $quantity, string $due, int $variant = 0, string $date = '2026-01-01'): int
    {
        return (int)self::one("INSERT INTO batch_kob(vare_id,variant_id,lager,antal,rest,pris,kobsdate,fakturadate,linje_id,ordre_id,due_date)
            VALUES($1,$2,$3,$4,$4,4,$5,$5,0,0,$6) RETURNING id", [$id,$variant,$lager,$quantity,$date,$due])['id'];
    }

    /** @return list<array<string,string>> */
    public static function calls(): array
    {
        $lines = file(self::$log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
    }
}
