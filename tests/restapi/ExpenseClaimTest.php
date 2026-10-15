<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/ExpenseClaimTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Cover tenant-configured claims capability in auth me.


require_once __DIR__.'/support/T4ClaimsTestCase.php';
final class ExpenseClaimTest extends T4ClaimsTestCase
{
    private const ROUTE='employee/expenses.php';
    public function test_own_expenses_conversion_is_atomic_and_replayed(): void
    {
        $row=$this->fixture();
        $body=$this->body(['attachment_id'=>(int)$row['id']]);
        $created=$this->request('POST',self::ROUTE,$body);
        $item=$this->assertStatus(201,$created);
        $this->assertSame((int)$row['id'],$item['attachment_id']);
        $this->assertSame('10.00',$item['amount']);
        $this->assertSame('Field employee',$item['employee_name']);
        $this->assertSame($created['json'],$this->request('POST',self::ROUTE,$body)['json']);
        $this->assertSame('1',self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE pool_file_id=$1',[$row['id']])[0]['n']);
        $this->assertStatus(409,$this->request('POST',self::ROUTE,$this->body(['attachment_id'=>(int)$row['id']])));
        $another=$this->fixture();
        $this->assertStatus(409,$this->request('POST',self::ROUTE,array_replace($body,['attachment_id'=>(int)$another['id']])));
        $own=$this->assertStatus(200,$this->request('GET',self::ROUTE.'?period_from=2026-10-01&period_to=2026-10-01&limit=1'));
        $this->assertGreaterThan(0,$own['total']);
        $this->assertCount(1,$own['items']);
        $other=$this->assertStatus(200,$this->request('GET',self::ROUTE,null,self::$otherHeaders));
        $this->assertSame(0,$other['total']);
        $this->assertStatus(404,$this->request('POST',self::ROUTE,$this->body(['attachment_id'=>(int)$another['id']]),self::$otherHeaders));
    }
    public function test_legacy_receipt_dates_are_normalized_filtered_and_keep_the_claims_original_employee(): void
    {
        $row=$this->fixture();
        self::sql("UPDATE pool_files SET file_date='17-10-2026',amount='1.234,56' WHERE id=$1",[$row['id']]);
        $item=$this->assertStatus(201,$this->request('POST',self::ROUTE,$this->body(['attachment_id'=>(int)$row['id']])));
        $this->assertSame('2026-10-17',$item['date']);
        $this->assertSame('1234.56',$item['amount']);
        $list=$this->assertStatus(200,$this->request('GET',self::ROUTE.'?period_from=2026-10-17&period_to=2026-10-17'));
        $this->assertSame([(int)$row['id']],array_column($list['items'],'attachment_id'));
        $this->assertSame('17-10-2026',self::sql('SELECT file_date FROM pool_files WHERE id=$1',[$row['id']])[0]['file_date']);
        self::sql('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$userId]);
        try {
            $list=$this->assertStatus(200,$this->request('GET',self::ROUTE.'?period_from=2026-10-17&period_to=2026-10-17'));
            $this->assertSame('Field employee',$list['items'][0]['employee_name']);
        } finally { self::sql('UPDATE brugere SET ansat_id=$1 WHERE id=$2',[self::$employeeId,self::$userId]); }
        $unknown=$this->fixture();
        self::sql("UPDATE pool_files SET file_date='unknown' WHERE id=$1",[$unknown['id']]);
        $item=$this->assertStatus(201,$this->request('POST',self::ROUTE,$this->body(['attachment_id'=>(int)$unknown['id']])));
        $this->assertNull($item['date']);
        $this->assertStatus(422,$this->request('GET',self::ROUTE.'?period_from=2026-10-18&period_to=2026-10-17'));
    }

    public function test_processed_and_moving_bilag_cannot_be_claimed(): void
    {
        foreach ([['staged','pending'],['attached','approved'],['ubehandlet','approved'],['ubehandlet','rejected'],['deleted','pending']] as [$status,$review]) {
            $row=$this->fixture($status,$review);
            $this->assertStatus(409,$this->request('POST',self::ROUTE,$this->body(['attachment_id'=>(int)$row['id']])));
            $this->assertSame('0',self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE pool_file_id=$1',[$row['id']])[0]['n']);
        }
        foreach (['reserved','committed'] as $state) {
            $row=$this->fixture();
            self::sql("INSERT INTO pool_file_moves(client_id,pool_file_id,source_path,staging_path,sha256,state) VALUES ($1,$2,'source','staging',$3,$4)",
                [RequestId::resolve(null),$row['id'],hash('sha256','moving'),$state]);
            $response=$this->request('POST',self::ROUTE,$this->body(['attachment_id'=>(int)$row['id']]));
            $this->assertStatus(409,$response);
            $this->assertSame('attachment_moving',$response['json']['data']['details']['reason']);
        }
    }
    public function test_capability_fails_closed_and_upload_requires_employee_link(): void
    {
        self::setting('claims_payable_account','');
        try {
            $this->assertFalse($this->service()->capability());
            $me = $this->assertStatus(200, $this->request('GET', 'auth/me.php'));
            $this->assertFalse($me['capabilities']['claims']);
            $response=$this->request('GET',self::ROUTE);
            $this->assertStatus(422,$response);
            $this->assertSame('claims_payable_account',$response['json']['data']['details']['what']);
        } finally { self::setting('claims_payable_account','987610'); }
        $me = $this->assertStatus(200, $this->request('GET', 'auth/me.php'));
        $this->assertSame('v2', $me['capabilities']['claims']);
        self::sql('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$userId]);
        try {
            $this->assertStatus(403,$this->request('POST','attachment/',$this->payload()+['claim'=>['kind'=>'udlaeg']]));
        } finally { self::sql('UPDATE brugere SET ansat_id=$1 WHERE id=$2',[self::$employeeId,self::$userId]); }
        foreach ([['kind'=>'koersel'],['kind'=>'udlaeg','ansat_id'=>self::$employeeId],'udlaeg'] as $claim) {
            $this->assertStatus(422,$this->request('POST','attachment/',$this->payload()+['claim'=>$claim]));
        }
    }
    public function test_plain_upload_remains_compatible_and_cannot_gain_claim_on_upload_replay(): void
    {
        $body=$this->payload();
        $upload=$this->assertStatus(201,$this->request('POST','attachment/',$body));
        $this->assertStatus(409,$this->request('POST','attachment/',$body+['claim'=>['kind'=>'udlaeg']]));
        $this->assertStatus(201,$this->request('POST',self::ROUTE,$this->body(['attachment_id'=>$upload['id']])));
    }
}
