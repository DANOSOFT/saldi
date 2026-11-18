<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// tests/characterization/debitor/EasyUblCompanyUpdateTest.test.php
//
// SST-865: before an invoice is sent, debitor/api.php getInvoicesOrder() updates the company at
// EasyUBL once (until settings easyUBL/updatedCompany is set). When EasyUBL refused the update with
// hasEndpointPeppol false ("CVR is already registered in Semantics elsewhere"), the send stopped with
// die() and no log was written, so every later send stopped the same way.
//
// getInvoicesOrder() and updateCompany() live in a page script that talks to EasyUBL, so the two
// blocks are lifted out of the source and run here: the database calls and updateCompany() are
// swapped for a fake, and die() becomes an exception the test can see. No database, no network.
//
// History:
// 20261110 CL/MJ SST-865 Created.
// 20261111 CL/MJ SST-865 The refusal log leaves out the contact and bank details (CodeRabbit).
// 20261118 CL/MMR SST-1003 The other error logs (failed call, NemHandel creation) leave out the contact and bank details too.
// 20261118 CL/MMR SST-1007 An answer that is not 2xx counts as a failed update.
final class EasyUblCompanyUpdateTest extends TestCase
{
    private const API = __DIR__ . '/../../../debitor/api.php';

    private string $dir = '';

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            foreach (glob($this->dir . '/temp/sst865/*') ?: array() as $file) {
                unlink($file);
            }
            @rmdir($this->dir . '/temp/sst865');
            @rmdir($this->dir . '/temp');
            @rmdir($this->dir . '/work');
            @rmdir($this->dir);
        }
    }

    /** The source between two code anchors, the first searched from $after on. */
    private function lift(string $after, string $from, string $to): string
    {
        $src = file_get_contents(self::API);
        self::assertNotFalse($src, 'could not read debitor/api.php');
        $start = strpos($src, $after);
        self::assertNotFalse($start, "anchor is gone from api.php: $after");
        $a = strpos($src, $from, $start);
        self::assertNotFalse($a, "anchor is gone from api.php: $from");
        $b = strpos($src, $to, $a);
        self::assertNotFalse($b, "end anchor is gone from api.php: $to");
        return substr($src, $a, $b - $a);
    }

    /**
     * Runs getInvoicesOrder()'s company update for a tenant with a companyID and without
     * updatedCompany, with updateCompany() answering $update.
     *
     * @return array{sent: bool, stopped: string|null, inserts: list<string>, updates: int}
     */
    private function sendWithUpdate(array $update): array
    {
        $block = $this->lift(
            'function getInvoicesOrder(',
            '$query = db_select("SELECT var_value FROM settings WHERE var_name = \'updatedCompany\'',
            '$companyID = getCompanyID();'
        );
        $code = strtr($block, array(
            'db_select(' => '$fake->select(',
            'db_num_rows(' => '$fake->numRows(',
            'db_modify(' => '$fake->modify(',
            'updateCompany()' => '$fake->updateCompany()',
            'die(' => 'throw new RuntimeException(',
        ));
        $fake = new class ($update) {
            public array $inserts = array();
            public int $updates = 0;

            public function __construct(private array $update)
            {
            }

            public function select(string $sql, string $where): string
            {
                return $sql;
            }

            /** No updatedCompany yet, a companyID already. */
            public function numRows(string $sql): int
            {
                return str_contains($sql, "'companyID'") ? 1 : 0;
            }

            public function modify(string $sql, string $where): bool
            {
                $this->inserts[] = $sql;
                return true;
            }

            public function updateCompany(): array
            {
                $this->updates++;
                return $this->update;
            }
        };
        $stopped = null;
        try {
            eval($code);
        } catch (RuntimeException $e) {
            $stopped = $e->getMessage();
        }
        return array('sent' => $stopped === null, 'stopped' => $stopped, 'inserts' => $fake->inserts, 'updates' => $fake->updates);
    }

    public function testARefusedCompanyUpdateNoLongerStopsTheInvoice(): void
    {
        $out = $this->sendWithUpdate(array(
            'success' => false,
            'message' => 'CVR is already registered in Semantics elsewhere, you have to cancel that first.',
            'response' => array('hasEndpointPeppol' => false),
        ));

        self::assertTrue($out['sent'], 'the send stopped with: ' . (string)$out['stopped']);
        self::assertSame(1, $out['updates']);
        self::assertSame(array(), $out['inserts'], 'updatedCompany stays unset, so the update is tried again next time');
    }

    public function testAFailedUpdateCallDoesNotStopTheInvoiceEither(): void
    {
        $out = $this->sendWithUpdate(array('success' => false, 'message' => 'Error updating company: Operation timed out'));

        self::assertTrue($out['sent'], 'the send stopped with: ' . (string)$out['stopped']);
        self::assertSame(array(), $out['inserts']);
    }

    public function testASuccessfulUpdateIsRecordedAsBefore(): void
    {
        $out = $this->sendWithUpdate(array('success' => true, 'companyId' => 'c-1', 'response' => array('hasEndpointPeppol' => true)));

        self::assertTrue($out['sent']);
        self::assertCount(1, $out['inserts']);
        self::assertStringContainsString("'updatedCompany', 'easyUBL', 'true'", $out['inserts'][0]);
    }

    /** The hasEndpointPeppol false branch of updateCompany() keeps EasyUBL's answer in temp/<db>/. */
    public function testARefusedUpdateLeavesEasyUblsAnswerInTemp(): void
    {
        $branch = $this->lift(
            'function updateCompany()',
            '$timestamp = date("Y-m-d-H-i-s");',
            '// Save successful response in temp folder for debugging'
        );
        $this->dir = sys_get_temp_dir() . '/sst865_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/temp/sst865', 0777, true);
        mkdir($this->dir . '/work');
        $cwd = getcwd();
        chdir($this->dir . '/work'); # the page writes to ../temp/$db
        try {
            $db = 'sst865';
            $ch = curl_init();
            $httpCode = 200;
            $data = array('name' => 'Testfirma ApS', 'cvr' => 'DK12345674', 'registerAsRecipient' => true,
                'defaultContact' => array('email' => 'bogholder@testfirma.example', 'sms' => '+4512345678'),
                'payment' => array('bankAccount' => '1234-0012345678'));
            $response = array('hasEndpointPeppol' => false, 'id' => 'c-1', 'defaultContact' => array('email' => 'bogholder@testfirma.example'));
            $result = (function () use ($branch, $db, $ch, $httpCode, $data, $response) {
                return eval($branch);
            })();
        } finally {
            chdir((string)$cwd);
        }

        self::assertFalse($result['success']);
        $logs = glob($this->dir . '/temp/sst865/Update-company-error-*.json') ?: array();
        self::assertCount(1, $logs, 'no Update-company-error file was written');
        $log = (string)file_get_contents($logs[0]);
        self::assertStringContainsString('"hasEndpointPeppol":false', $log, "EasyUBL's answer is in the file");
        self::assertStringContainsString('"registerAsRecipient":true', $log, 'and what was sent');
        self::assertStringNotContainsString('bogholder@testfirma.example', $log, 'no contact e-mail: temp/ is under the web root');
        self::assertStringNotContainsString('+4512345678', $log, 'no phone number');
        self::assertStringNotContainsString('1234-0012345678', $log, 'no bank account');
    }

    /** Sent data and an answer that both carry the contact and bank details. */
    private const DATA = array('name' => 'Testfirma ApS', 'cvr' => 'DK12345674', 'registerAsRecipient' => true,
        'defaultContact' => array('email' => 'bogholder@testfirma.example', 'sms' => '+4512345678'),
        'payment' => array('bankAccount' => '1234-0012345678'));

    private function assertNoPrivateDetails(string $log): void
    {
        self::assertStringNotContainsString('bogholder@testfirma.example', $log, 'no contact e-mail: temp/ is under the web root');
        self::assertStringNotContainsString('+4512345678', $log, 'no phone number');
        self::assertStringNotContainsString('1234-0012345678', $log, 'no bank account');
        self::assertStringContainsString('"registerAsRecipient":true', $log, 'what was sent is still there');
    }

    /** A temp dir with temp/sst865 and a work dir the page code runs in (it writes to ../temp/$db). */
    private function tempDir(): string
    {
        $this->dir = sys_get_temp_dir() . '/sst865_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/temp/sst865', 0777, true);
        mkdir($this->dir . '/work');
        return $this->dir;
    }

    /**
     * Runs updateCompany() from the EasyUBL answer on: its error branches, or null when it gets past them
     * (the success path). Returns the result and the Update-company-error files written.
     *
     * @return array{result: mixed, logs: list<string>}
     */
    private function runUpdateBranches(int $httpCode, mixed $response): array
    {
        $branch = $this->lift(
            'function updateCompany()',
            '$timestamp = date("Y-m-d-H-i-s");',
            '// Save successful response in temp folder for debugging'
        );
        $this->tempDir();
        $cwd = getcwd();
        chdir($this->dir . '/work');
        try {
            $db = 'sst865';
            $ch = curl_init();
            $data = self::DATA;
            $result = (function () use ($branch, $db, $ch, $httpCode, $data, $response) {
                return eval($branch);
            })();
        } finally {
            chdir((string)$cwd);
        }
        $logs = array_map(fn (string $f): string => (string)file_get_contents($f), glob($this->dir . '/temp/sst865/Update-company-error-*.json') ?: array());
        return array('result' => $result, 'logs' => $logs);
    }

    public function testAFailedUpdateCallLogsWithoutContactOrBankDetails(): void
    {
        $out = $this->runUpdateBranches(500, array('errorNumber' => 7, 'error' => 'boom', 'defaultContact' => array('email' => 'bogholder@testfirma.example')));

        self::assertFalse($out['result']['success']);
        self::assertCount(1, $out['logs']);
        self::assertStringContainsString('"errorNumber":7', $out['logs'][0], "EasyUBL's answer is in the file");
        $this->assertNoPrivateDetails($out['logs'][0]);
    }

    public function testAnHttpErrorWithoutErrorFieldsCountsAsAFailedUpdate(): void
    {
        foreach (array(401, 500, 503) as $code) {
            $out = $this->runUpdateBranches($code, array('title' => 'Unauthorized', 'payment' => array('bankAccount' => '1234-0012345678')));

            self::assertIsArray($out['result'], "HTTP $code is not treated as success");
            self::assertFalse($out['result']['success'], "HTTP $code");
            self::assertStringContainsString((string)$code, $out['result']['message']);
            self::assertCount(1, $out['logs'], "HTTP $code is logged like the other failures");
            self::assertStringContainsString('"status code":' . $code, $out['logs'][0]);
            self::assertStringContainsString('"title":"Unauthorized"', $out['logs'][0]);
            $this->assertNoPrivateDetails($out['logs'][0]);
            $this->tearDown();
        }
    }

    public function testA2xxAnswerWithoutErrorFieldsStillSucceeds(): void
    {
        $out = $this->runUpdateBranches(200, array('id' => 'c-1', 'hasEndpointPeppol' => true));

        self::assertNull($out['result'], 'no error branch is taken: updateCompany() goes on to the success path');
        self::assertSame(array(), $out['logs']);
    }

    /**
     * Runs the two error branches of the NemHandel creation (getCompanyID() without a companyID). Their
     * exit becomes a return, the alert is caught.
     *
     * @return list<string> the Create-in-nemhandel-error files written
     */
    private function runNemHandelBranches(int $httpCode, mixed $response): array
    {
        $branch = $this->lift(
            '"https://easyubl.net/api/Company/Update/$guid"',
            '$timestamp = date("Y-m-d-H-i-s");',
            '}else{'
        );
        $code = str_replace('exit;', 'return "exit";', $branch) . '}';
        $this->tempDir();
        $cwd = getcwd();
        chdir($this->dir . '/work');
        ob_start();
        try {
            $db = 'sst865';
            $ch = curl_init();
            $data = self::DATA;
            (function () use ($code, $db, $ch, $httpCode, $data, $response) {
                return eval($code);
            })();
        } finally {
            ob_end_clean();
            chdir((string)$cwd);
        }
        return array_map(fn (string $f): string => (string)file_get_contents($f), glob($this->dir . '/temp/sst865/Create-in-nemhandel-error-*.json') ?: array());
    }

    public function testAFailedNemHandelCreationLogsWithoutContactOrBankDetails(): void
    {
        $logs = $this->runNemHandelBranches(0, null);

        self::assertCount(1, $logs);
        $this->assertNoPrivateDetails($logs[0]);
    }

    public function testARefusedNemHandelCreationLogsEasyUblsAnswerWithoutContactOrBankDetails(): void
    {
        $logs = $this->runNemHandelBranches(400, array('companyID' => '00000000-0000-0000-0000-000000000000', 'title' => 'Bad Request',
            'defaultContact' => array('email' => 'bogholder@testfirma.example'), 'payment' => array('bankAccount' => '1234-0012345678')));

        self::assertCount(1, $logs);
        self::assertStringContainsString('"title":"Bad Request"', $logs[0], "EasyUBL's answer is in the file");
        $this->assertNoPrivateDetails($logs[0]);
    }
}
