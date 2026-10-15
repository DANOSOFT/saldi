<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T3BankLineTestCase.php --- ver 5.0.0 --- 2026.10.05
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
require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../restapi/services/ReminderService.php';
require_once __DIR__ . '/../../../restapi/models/attachment/DurableAttachmentModel.php';
require_once __DIR__ . '/../../../includes/mobileUpdates.php';

/** Disposable bank tenant; sessions and credentials come from the shared test environment. */
abstract class T3BankLineTestCase extends TestCase
{
    protected static $conn;
    protected static $master;
    protected static int $tenantId;
    protected static int $userId;
    protected static string $sender;
    protected static string $capture;
    protected static array $headers;
    protected static array $captureHeaders;
    protected static string $root;
    protected int $account;

    public static function setUpBeforeClass(): void
    {
        if ($reason = RestApiEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$conn = RestApiEnv::connect(RestApiEnv::testDb());
        self::$master = RestApiEnv::connect(RestApiEnv::masterDb());
        mobileTenantUpdates(self::$conn);
        self::$tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        self::$headers = RestApiEnv::authHeaders();
        self::$userId = (int)RestApiEnv::loginData()['user']['id'];
        self::$sender = Subject::build('account', self::$userId);
        $id = RestApiEnv::rows(self::$conn,
            "INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES ('bank_capture',$1,$2,true) RETURNING id",
            [md5(TestCredentials::password('bankcapture')), str_repeat('0',16)])[0]['id'];
        $login = RestApiEnv::login('bank_capture',TestCredentials::password('bankcapture'),RestApiEnv::accountOpen());
        if ($login['status'] !== 200) {
            throw new RuntimeException('Bank capture login failed');
        }
        self::$captureHeaders = ['Authorization: Bearer ' . $login['json']['data']['access_token']];
        self::$capture = Subject::build('account',(int)$id);
        self::$root = dirname(poolPuljePath(RestApiEnv::testDb()));
        if (!is_dir(self::$root . '/pulje')) {
            mkdir(self::$root . '/pulje',0777,true);
        }
        chmod(self::$root,0777);
        chmod(self::$root . '/pulje',0777);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            pg_close(self::$conn);
            pg_close(self::$master);
            foreach ([self::$root,dirname(__DIR__,3) . '/temp/' . RestApiEnv::testDb()] as $path) {
                if (is_dir($path)) {
                    self::remove($path);
                }
            }
        }
        RestApiEnv::teardownTenant();
    }

    private static function remove(string $path): void
    {
        foreach (new FilesystemIterator($path) as $file) {
            if ($file->isDir() && !$file->isLink()) {
                self::remove($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($path);
    }

    protected function setUp(): void
    {
        $this->account = random_int(700000,999999);
        RestApiEnv::rows(self::$conn,"UPDATE brugere SET rettigheder=$1 WHERE id=$2",['0010000000000000',self::$userId]);
    }

    /** @return array<string,mixed> Unique outgoing row and a live journal link. */
    protected function bank(string $amount='-612.50',?int $account=null): array
    {
        $account = $account ?? $this->account;
        RestApiEnv::rows(self::$conn,"INSERT INTO kontoplan(kontonr,beskrivelse,regnskabsaar,kontotype) SELECT $1,'Bank, drift',kodenr,'D' FROM grupper WHERE art='RA'",[$account]);
        $journal = (int)RestApiEnv::rows(self::$conn,
            "INSERT INTO kladdeliste(id,bogfort,kladdenote) SELECT COALESCE(MAX(id),0)+1,'-','Bank fixture' FROM kladdeliste RETURNING id")[0]['id'];
        $line = (int)RestApiEnv::rows(self::$conn,
            "INSERT INTO kassekladde(kladde_id,bilag,transdate,beskrivelse,amount,kredit) VALUES ($1,1042,'2026-09-14','CIRCLE K',$2,$3) RETURNING id",
            [$journal,abs((float)$amount),$account])[0]['id'];
        $import = (int)RestApiEnv::rows(self::$conn,
            "INSERT INTO bank_line_imports(source,account_kontonr,kladde_id,imported_by) VALUES ('csv',$1,$2,$3) RETURNING id",
            [$account,$journal,self::$sender])[0]['id'];
        $row = RestApiEnv::rows(self::$conn,
            "INSERT INTO bank_lines(account_kontonr,line_key,source,transdate,amount,currency,text,first_import_id)
            VALUES ($1,$2,'csv','2026-09-14',$3,'DKK','CIRCLE K 4055 AARHUS',$4) RETURNING *",
            [$account,hash('sha256',random_bytes(16)),$amount,$import])[0];
        RestApiEnv::rows(self::$conn,'INSERT INTO bank_line_journal_links(bank_line_id,kassekladde_id,import_id) VALUES ($1,$2,$3)',[$row['id'],$line,$import]);
        return $row + ['kassekladde_id'=>$line,'kladde_id'=>$journal];
    }

    /** @return array{status:int,json:array|null,body:string,headers:array<string,string>} */
    protected function http(string $method,string $path,?array $body=null,?array $headers=null): array
    {
        $received = [];
        $curl = curl_init(RestApiEnv::baseUrl() . '/restapi/endpoints/v1/' . $path);
        $options = [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>array_merge(['Accept: application/json','Content-Type: application/json'],$headers ?? self::$headers),
            CURLOPT_HEADERFUNCTION=>static function ($curl,string $line) use (&$received): int {
                if (str_contains($line,':')) {
                    [$name,$value] = explode(':',$line,2);
                    $received[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            }];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body,JSON_THROW_ON_ERROR);
        }
        curl_setopt_array($curl,$options);
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return ['status'=>$status,'json'=>json_decode((string)$raw,true),'body'=>(string)$raw,'headers'=>$received];
    }

    /** @return array{client_id:string} */
    protected function body(array $fields=[]): array
    {
        return ['client_id'=>RequestId::resolve(null)] + $fields;
    }

    /** @return string Minimal valid one-page PDF used by the upload path. */
    protected static function pdf(): string
    {
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Contents 4 0 R >>',
            "<< /Length 0 >>\nstream\n\nendstream"];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i=>$object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i+1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 5\n0000000000 65535 f \n";
        foreach (array_slice($offsets,1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n",$offset);
        }
        return $pdf . "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
