<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileT3RightsMatrixTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Capture nullable review metadata, common-account proposals and ambiguous creditors for contract validation.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/T3Seed.php';

final class MobileT3RightsMatrixTest extends TestCase
{
    private static ?T3Seed $seed = null;

    public static function setUpBeforeClass(): void
    {
        if ($reason = RestApiEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        self::$seed = new T3Seed();
        self::$seed->personas();
    }

    public static function tearDownAfterClass(): void
    {
        self::$seed?->close();
        self::$seed = null;
    }

    /** @return iterable<string,array{string,string,string,string}> Every supported T3 endpoint/method. */
    public static function operations(): iterable
    {
        foreach ([
            ['attachment/review.php','GET','bilag','read'],
            ['attachment/approve.php','POST','kassekladde','write'],
            ['attachment/reject.php','POST','kassekladde','write'],
            ['attachment/metadata.php','PATCH','bilag','own'],
            ['attachment/audit.php','GET','bilag','own'],
            ['journal/drafts.php','GET','kassekladde','read'],
            ['classification/suggest.php','GET','bilag','own'],
            ['classification/feedback.php','POST','bilag','own'],
            ['classification/stats.php','GET','bilag','read'],
            ['bank-lines/index.php','GET','kassekladde','read'],
            ['bank-lines/remind.php','POST','bilag','write'],
            ['bank-lines/state.php','POST','kassekladde','write'],
            ['notifications/preferences.php','GET','bilag','own'],
            ['notifications/preferences.php','PUT','bilag','own'],
            ['bookkeeper/summary.php','GET','bilag','read'],
            ['bookkeeper/registry-summary.php','GET','registry','read'],
        ] as $operation) {
            yield $operation[0] . ' ' . $operation[1] => $operation;
        }
    }

    /** @return array{string,?array,list<string>,string} Fresh valid request owned by the tested principal. */
    private function scenario(string $file,string $method,string $persona): array
    {
        $s = self::$seed;
        $path = $file;
        $body = null;
        $headers = [];
        $schema = match ($file) {
            'attachment/review.php'=>'review-list','attachment/approve.php'=>'approve','attachment/reject.php'=>'reject',
            'attachment/metadata.php'=>'metadata','attachment/audit.php'=>'audit','journal/drafts.php'=>'journal-drafts',
            'classification/suggest.php'=>'suggestion','classification/feedback.php'=>'feedback','classification/stats.php'=>'classification-stats',
            'bank-lines/index.php'=>'bank-lines','bank-lines/remind.php'=>'bank-line-remind','bank-lines/state.php'=>'bank-line-state',
            'notifications/preferences.php'=>'preferences','bookkeeper/summary.php'=>'tenant-summary',
            'bookkeeper/registry-summary.php'=>'registry-summary',
        };
        if (str_starts_with($file,'attachment/') || str_starts_with($file,'classification/')) {
            $row = $s->receipt([],$persona);
            if (in_array($file,['attachment/metadata.php','attachment/audit.php'],true)) {
                $path .= '?id=' . $row['id'];
            } elseif ($file === 'classification/suggest.php') {
                $path .= '?attachment_id=' . $row['id'];
            }
            if ($file === 'attachment/approve.php') {
                $body = ['client_id'=>T3Seed::uuid(),'id'=>(int)$row['id'],'kontonr'=>987001];
            } elseif ($file === 'attachment/reject.php') {
                $body = ['client_id'=>T3Seed::uuid(),'id'=>(int)$row['id'],'reason_code'=>'other','message'=>'Please correct the receipt'];
            } elseif ($file === 'attachment/metadata.php') {
                $body = ['client_id'=>T3Seed::uuid(),'description'=>'Owner correction'];
            } elseif ($file === 'classification/feedback.php') {
                $body = ['client_id'=>T3Seed::uuid(),'attachment_id'=>(int)$row['id'],'kontonr'=>987001];
            }
            if (in_array($file,['attachment/approve.php','attachment/reject.php','attachment/metadata.php'],true)) {
                $headers[] = 'If-Match: "' . pool_review_version($row) . '"';
            }
        } elseif ($file === 'journal/drafts.php') {
            $s->sql("DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
            $row = $s->receipt();
            $created = $s->request('POST','attachment/approve.php',
                ['client_id'=>T3Seed::uuid(),'id'=>(int)$row['id'],'kontonr'=>987001,'contra'=>['type'=>'F','kontonr'=>987010]],
                'full',['If-Match: "' . pool_review_version($row) . '"']);
            $this->assertSame(200,$created['status'],$created['body']);
            $path .= '?kladde_id=' . $created['json']['data']['draft']['kladde_id'];
        } elseif (str_starts_with($file,'bank-lines/')) {
            [$row,$journal,$line,$bank] = $s->bankReceipt('-',$persona);
            if ($file === 'bank-lines/remind.php') {
                $body = ['client_id'=>T3Seed::uuid(),'bank_line_id'=>$bank,'target'=>$s->subjects['receipt']];
            } elseif ($file === 'bank-lines/state.php') {
                $body = ['client_id'=>T3Seed::uuid(),'bank_line_id'=>$bank,'action'=>'ignore','note'=>'No receipt needed'];
            }
        } elseif ($file === 'notifications/preferences.php' && $method === 'PUT') {
            // An empty update is valid for every principal, including receipt-only users with no optional types.
            $body = ['client_id'=>T3Seed::uuid(),'items'=>[]];
        }
        return [$path,$body,$headers,$schema];
    }

    #[DataProvider('operations')]
    public function testEveryT3MethodAndPrincipal(string $file,string $method,string $module,string $action): void
    {
        [$path,$body,$headers] = $this->scenario($file,$method,'receipt');
        $this->denied(T3Seed::http($method,$path,$body,null,$headers),401,null);
        foreach (['receipt','unrelated','read','write','registry'] as $persona) {
            [$path,$body,$headers,$schema] = $this->scenario($file,$method,$persona);
            $allowed = $module === 'registry' ? $persona === 'registry' :
                ($action === 'own' || in_array($persona,['write','registry'],true) || ($persona === 'read' && $action === 'read'));
            $response = self::$seed->request($method,$path,$body,$persona,$headers);
            if (!$allowed) {
                $this->denied($response,403,'permission_denied');
                continue;
            }
            $this->allowed($response,$schema);
            if ($method !== 'GET') {
                $again = self::$seed->request($method,$path,$body,$persona,$headers);
                $this->assertSame($response['status'],$again['status'],$again['body']);
                $this->assertSame($response['json'],$again['json']);
                $this->assertSame('true',$again['headers']['idempotent-replayed'] ?? null);
                // Change a supported field: feedback validates unknown fields before consulting replay.
                $changed = $body;
                if ($file === 'attachment/approve.php') {
                    $changed['description'] = 'Changed request body';
                } elseif ($file === 'classification/feedback.php') {
                    $changed['kontonr'] = 987010;
                } elseif ($file === 'notifications/preferences.php') {
                    $changed['items'] = [['type'=>'attachment_decided','enabled'=>false]];
                } else {
                    $changed['message'] = 'Changed request body';
                }
                $conflict = self::$seed->request($method,$path,$changed,$persona,$headers);
                $this->denied($conflict,409,'conflict');
                $this->assertSame('client_id_reused',$conflict['json']['data']['details']['reason']);
            }
        }
    }

    private function allowed(array $response,string $schema,?string $scenario=null): void
    {
        $this->assertGreaterThanOrEqual(200,$response['status'],$response['body']);
        $this->assertLessThan(300,$response['status'],$response['body']);
        $this->assertTrue($response['json']['success']);
        $this->assertArrayHasKey('x-request-id',$response['headers']);
        self::$seed->contracts[] = ['schema'=>$schema,'response'=>$response['json'],'scenario'=>$scenario];
    }

    private function denied(array $response,int $status,?string $error): void
    {
        $this->assertSame($status,$response['status'],$response['body']);
        $this->assertFalse($response['json']['success']);
        if ($error !== null) {
            $this->assertSame($error,$response['json']['data']['error']);
        }
        $this->assertArrayHasKey('x-request-id',$response['headers']);
    }

    public function testOwnRightsDoNotGrantOtherOwnersRowsEvenToReviewers(): void
    {
        $s = self::$seed;
        $row = $s->receipt([],'other');
        foreach (['receipt','unrelated','read','write','registry'] as $persona) {
            foreach (['attachment/metadata.php','classification/feedback.php'] as $file) {
                $method = $file === 'attachment/metadata.php' ? 'PATCH' : 'POST';
                $path = $file . ($method === 'PATCH' ? '?id=' . $row['id'] : '');
                $body = $method === 'PATCH' ? ['client_id'=>T3Seed::uuid(),'description'=>'Other owner'] :
                    ['client_id'=>T3Seed::uuid(),'attachment_id'=>(int)$row['id'],'kontonr'=>987001];
                $this->denied($s->request($method,$path,$body,$persona,['If-Match: "' . pool_review_version($row) . '"']),404,'not_found');
            }
            foreach (['attachment/audit.php?id=','classification/suggest.php?attachment_id='] as $prefix) {
                $response = $s->request('GET',$prefix . $row['id'],null,$persona);
                if (in_array($persona,['receipt','unrelated'],true)) {
                    $this->denied($response,404,'not_found');
                } else {
                    $this->allowed($response,str_starts_with($prefix,'attachment/') ? 'audit' : 'suggestion');
                }
            }
        }
        $this->assertSame($row,$s->row((int)$row['id']));
    }

    public function testRegistrySummaryRechecksCurrentMembershipOnEveryCall(): void
    {
        $s = self::$seed;
        $response = $s->request('GET','bookkeeper/registry-summary.php',null,'registryRoot');
        $this->allowed($response,'registry-summary');
        $this->assertSame([$s->tenantId],array_column($response['json']['data']['items'],'tenant_id'));
        RestApiEnv::rows($s->registry,"UPDATE brugere SET rettigheder=',,,' WHERE id=$1",[$s->users['registry']['id']]);
        try {
            $next = $s->request('GET','bookkeeper/registry-summary.php',null,'registryRoot');
            $this->allowed($next,'registry-summary');
            $this->assertSame([],$next['json']['data']['items']);
            $this->assertSame(0,$next['json']['data']['total']);
        } finally {
            RestApiEnv::rows($s->registry,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',[',,,' . $s->tenantId,$s->users['registry']['id']]);
        }
    }

    public function testQueueDetailAndVendorSuggestionContracts(): void
    {
        $s = self::$seed;
        $row = $s->receipt([],'receipt');
        $this->allowed($s->request('GET','attachment/review.php?id=' . $row['id'],null,'write'),'review-detail');
        $this->allowed($s->request('GET','classification/suggest.php?vendor_name=Unknown&date=2026-09-14',null,'receipt'),'suggestion');
    }

    /** @return array{array,array} Capture the same receipt in both queue and detail responses. */
    private function captureReview(array $row,string $scenario): array
    {
        $s = self::$seed;
        $queue = $s->request('GET','attachment/review.php?limit=1',null,'write');
        $this->allowed($queue,'review-list',$scenario);
        $this->assertSame((int)$row['id'],$queue['json']['data']['items'][0]['id']);
        $detail = $s->request('GET','attachment/review.php?id=' . $row['id'],null,'write');
        $this->allowed($detail,'review-detail',$scenario);
        return [$queue['json']['data']['items'][0],$detail['json']['data']];
    }

    public function testReviewContractsPreserveMissingAndUnparsedMetadata(): void
    {
        foreach (['missing-metadata'=>['amount'=>null,'file_date'=>null],
            'unparsed-metadata'=>['amount'=>'not parsed','file_date'=>'not a date']] as $scenario=>$metadata) {
            $row = self::$seed->receipt($metadata+['norm_amount'=>null],'receipt');
            [$item,$detail] = $this->captureReview($row,$scenario);
            foreach ([$item,$detail,$detail['metadata']] as $projection) {
                $this->assertNull($projection['amount']);
                $this->assertNull($projection['date']);
            }
        }
    }

    public function testReviewContractsPreserveUnselectedCommonAccountSuggestion(): void
    {
        $s = self::$seed;
        $journal = $s->journal('V');
        $s->sql("INSERT INTO kassekladde(kladde_id,bilag,transdate,d_type,debet,k_type,kredit,amount,debetvat,kreditvat,momsfri)
            VALUES ($1,42,CURRENT_DATE,'F',987001,'K',987003,125,'K987','','')",[$journal]);
        $row = $s->receipt(['account'=>null,'vendor_match'=>'none','vendor_konto_id'=>null,
            'vendor_name'=>'Unknown common-account supplier','subject'=>'Unknown common-account supplier'],'receipt');
        $suggestion = $s->request('GET','classification/suggest.php?attachment_id=' . $row['id'],null,'receipt');
        $this->allowed($suggestion,'suggestion');
        $this->assertSame('common_accounts',$suggestion['json']['data']['basis']);
        $this->assertNotEmpty($suggestion['json']['data']['alternatives']);
        [$item,$detail] = $this->captureReview($row,'common-accounts');
        foreach ([$item,$detail] as $projection) {
            $this->assertSame($suggestion['json']['data']['suggestion_id'],$projection['suggestion']['id']);
            $this->assertSame('common_accounts',$projection['suggestion']['basis']);
            foreach (['kontonr','account_name','vat_code'] as $field) {
                $this->assertNull($projection['suggestion'][$field]);
            }
        }
    }

    public function testReviewContractsPreserveAmbiguousCreditorChoice(): void
    {
        $s = self::$seed;
        $s->sql("INSERT INTO adresser(art,kontonr,firmanavn,cvrnr,lukket) VALUES
            ('K','987020','First candidate','87654321',''),('K','987021','Second candidate','87654321','')");
        $row = $s->receipt(['vendor_match'=>'ambiguous','vendor_konto_id'=>null,'vendor_cvr'=>'87654321'],'receipt');
        [,$detail] = $this->captureReview($row,'ambiguous-creditor');
        $this->assertSame('choose',$detail['kreditor']['mode']);
        $this->assertNull($detail['kreditor']['match']);
        $this->assertEqualsCanonicalizing(['987020','987021'],array_column($detail['kreditor']['candidates'],'kontonr'));
        $this->assertSame([null,null],array_column($detail['kreditor']['candidates'],'match'));
    }
}
