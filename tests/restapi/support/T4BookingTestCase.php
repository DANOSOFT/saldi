<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T4BookingTestCase.php --- ver 5.0.0 --- 2026.10.06
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

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/T3EventTestEnv.php';
require_once __DIR__ . '/../../../restapi/services/BookingService.php';

/** Isolated tenant, linked employee personas and real HTTP tokens for booking tests. */
abstract class T4BookingTestCase extends TestCase
{
    protected static array $users = [];
    protected static array $employees = [];
    protected static array $groups = [];
    protected static int $company;
    protected static array $tokens = [];
    protected string $date;
    private static int $day = 1;

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        T3EventTestEnv::setup();
        self::$users = self::$employees = self::$groups = self::$tokens = [];
        $conn = T3EventTestEnv::$tenant;
        $own = RestApiEnv::rows($conn, "SELECT id FROM adresser WHERE art='S' ORDER BY id LIMIT 1");
        self::$company = (int)($own === [] ? RestApiEnv::rows($conn,"INSERT INTO adresser(art,firmanavn) VALUES('S','Booking company') RETURNING id")[0]['id'] : $own[0]['id']);
        foreach (['self','other','leader','admin','alias'] as $role) {
            if ($role === 'alias') {
                self::$employees[$role] = self::$employees['self'];
                self::$groups[$role] = self::$groups['self'];
            } else {
                self::$groups[$role] = (int)RestApiEnv::rows($conn,"INSERT INTO grupper(art,box2) VALUES('ANSAT',$1) RETURNING id",
                    [$role === 'leader' ? '10000010' : '10000000'])[0]['id'];
                self::$employees[$role] = (int)RestApiEnv::rows($conn,"INSERT INTO ansatte(navn,initialer,nummer,konto_id,gruppe,lukket)
                    VALUES($1,$2,'42',$3,$4,'') RETURNING id",[$role,'FE',self::$company,self::$groups[$role]])[0]['id'];
            }
            if ($role === 'self') {
                self::$users[$role] = T3EventTestEnv::$account;
                RestApiEnv::rows($conn,'UPDATE brugere SET ansat_id=$1 WHERE id=$2',[self::$employees[$role],self::$users[$role]['id']]);
            } else {
                self::$users[$role] = RestApiEnv::rows($conn,'INSERT INTO brugere(brugernavn,kode,rettigheder,status,ansat_id)
                    VALUES($1,$2,$3,true,$4) RETURNING *', ['book_' . bin2hex(random_bytes(5)),
                    md5(TestCredentials::password('booking')),str_repeat('0',16),self::$employees[$role]])[0];
            }
            self::$tokens[$role] = (new MobileAuth())->create(self::$users[$role], 'account', T3EventTestEnv::$tenantId, 'booking-test')['access_token'];
        }
    }

    public static function tearDownAfterClass(): void
    {
        T3EventTestEnv::teardown();
    }

    protected function setUp(): void
    {
        T3EventTestEnv::reset();
        foreach (self::$users as $role=>$user) {
            $digits = str_repeat('0',16);
            if ($role === 'admin') {
                $digits[1] = '1';
            }
            $this->rows('UPDATE brugere SET rettigheder=$1,ansat_id=$2,status=true WHERE id=$3',[$digits,self::$employees[$role],$user['id']]);
            $this->rows("UPDATE ansatte SET lukket='',konto_id=$1 WHERE id=$2",[self::$company,self::$employees[$role]]);
            T3EventTestEnv::session(Subject::build('account',(int)$user['id']));
            self::$tokens[$role] = (new MobileAuth())->create($user, 'account', T3EventTestEnv::$tenantId, 'booking-test')['access_token'];
        }
        // Each scenario has its own day; immutable history remains until the tenant is dropped.
        $this->date = (new DateTimeImmutable('2027-01-01'))->modify('+' . self::$day++ . ' days')->format('Y-m-d');
    }

    protected function rows(string $sql, array $params = []): array
    {
        return RestApiEnv::rows(T3EventTestEnv::$tenant,$sql,$params);
    }

    protected function request(string $method, string $path, $body = null, string $role = 'self', ?string $etag = null): array
    {
        $headers = $role === 'anonymous' ? [] : ['Authorization: Bearer ' . self::$tokens[$role]];
        if ($etag !== null) {
            $headers[] = 'If-Match: ' . $etag;
        }
        $curl = curl_init(RestApiEnv::baseUrl() . '/restapi/endpoints/v1/bookings/' . $path);
        $responseHeaders = '';
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>array_merge($headers, ['Accept: application/json','Content-Type: application/json']),
            CURLOPT_HEADERFUNCTION=>static function ($handle, string $line) use (&$responseHeaders): int {
                $responseHeaders .= $line;
                return strlen($line);
            }]);
        if ($body !== null) {
            curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($body, JSON_THROW_ON_ERROR));
        }
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return ['status'=>$status,'body'=>(string)$raw,'json'=>json_decode((string)$raw,true),'headers'=>$responseHeaders];
    }

    protected function body(array $override = []): array
    {
        return array_replace(['client_id'=>MobileAuth::uuid(),'date'=>$this->date,'start'=>'08:00','duration_minutes'=>60,
            'customer'=>['name'=>"Hansen's ApS",'address'=>'Testvej 1','phone'=>'12345678'],'service'=>'Montage','note'=>'Visit'],$override);
    }

    protected function create(array $override = [], string $role = 'self'): array
    {
        $response = $this->request('POST','index.php',$this->body($override),$role);
        self::assertSame(201,$response['status'],$response['body']);
        return $response['json']['data'];
    }

    protected function service(string $role = 'self', $conn = null): BookingService
    {
        $id = (int)self::$users[$role]['id'];
        return new BookingService($conn ?? T3EventTestEnv::$tenant,'account',$id,Subject::build('account',$id));
    }

    protected function history(int $id): array
    {
        return $this->rows('SELECT * FROM mobile_booking_history WHERE booking_id=$1 ORDER BY id',[$id]);
    }
}
