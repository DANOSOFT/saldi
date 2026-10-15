<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MileageRateTest.php --- ver 5.0.0 --- 2026.10.06
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


require_once __DIR__.'/support/T4ClaimsTestCase.php';
require_once __DIR__.'/../../systemdata/diverseIncludes/mobilMarkenValg.php';
final class MileageRateTest extends T4ClaimsTestCase
{
    public function test_rates_are_year_specific_missing_configuration_is_read_only(): void
    {
        $before=self::sql("SELECT * FROM settings WHERE var_grp='mobile_marken' ORDER BY id");
        $missing=$this->assertStatus(200,$this->request('GET','employee/mileage/rates.php?year=2028'));
        $this->assertSame(['year'=>2028,'rate'=>null,'rate_low'=>null,'threshold_km'=>20000,'configured'=>false],$missing);
        $this->assertSame($before,self::sql("SELECT * FROM settings WHERE var_grp='mobile_marken' ORDER BY id"));
        $rate=$this->assertStatus(200,$this->request('GET','employee/mileage/rates.php?year=2026'));
        $this->assertSame(['year'=>2026,'rate'=>'3.79','rate_low'=>'2.23','threshold_km'=>20000,'configured'=>true],$rate);
        $this->assertSame(20000,mileage_rate(2027,self::$conn)['threshold_km']);
        self::setting('km_rate_low_2028','4.00');
        self::setting('km_rate_2028','3.00');
        $this->assertFalse(mileage_rate(2028,self::$conn)['configured']);
        foreach (['0','10000','1.2','x'] as $year) { $this->assertStatus(422,$this->request('GET','employee/mileage/rates.php?year='.$year)); }
    }
    public function test_threshold_splits_one_trip_rounds_to_ore_and_shares_one_employee_between_logins(): void
    {
        $scope=$this->service()->allowanceScope();
        $this->assertSame($scope,$this->service(self::$otherId)->allowanceScope());
        $held=$this->statement($scope,2026,'19995.0');
        $trips=[['id'=>20,'trip_date'=>'2026-10-02','km'=>'0.1'],['id'=>10,'trip_date'=>'2026-10-01','km'=>'10.0']];
        self::sql('BEGIN');
        try {
            $a=mileage_allocate($scope,2026,$trips,self::$conn);
            $this->assertSame('5.0',$a['km_high']);
            $this->assertSame('5.1',$a['km_low']);
            $this->assertSame('30.32',$a['amount']);
            $this->assertSame([10,20],array_column($a['lines'],'trip_id'));
            $this->assertSame('5.0',$a['lines'][0]['km_high']);
            $this->assertSame('5.0',$a['lines'][0]['km_low']);
            $this->assertSame('30.10',$a['lines'][0]['amount']);
            $this->assertSame('0.22',$a['lines'][1]['amount']);
            $year=mileage_allocate($scope,2027,[['id'=>30,'trip_date'=>'2027-01-01','km'=>'10.0']],self::$conn);
            $this->assertSame('10.0',$year['km_high']);
            $this->assertSame('40.00',$year['amount']);
            // Withdrawal restores the allowance; existing append-only allocations stay unchanged.
            self::sql("UPDATE mobile_mileage_statements SET state='withdrawn',withdrawn_at=now() WHERE id=$1",[$held]);
            $again=mileage_allocate($scope,2026,$trips,self::$conn);
            $this->assertSame('10.1',$again['km_high']);
            $this->assertSame('38.28',$again['amount']);
        } finally { self::sql('ROLLBACK'); self::sql("UPDATE mobile_mileage_statements SET state='withdrawn' WHERE id=$1",[$held]); }
    }
    public function test_employee_allowance_preview_uses_other_logins_consumption_without_exposing_their_rows(): void
    {
        $scope=$this->service()->allowanceScope();
        $held=$this->statement($scope,2026,'20000.0');
        try {
            $trip=$this->service(self::$otherId)->createTrip($this->tripBody(['km'=>'10.0']))['body']['data'];
            $this->assertSame('22.30',$trip['amount_preview']);
            $this->assertSame('2.23',$trip['rate_preview']);
            $this->assertSame(0,$this->service()->trips([])['total']);
        } finally { self::sql("UPDATE mobile_mileage_statements SET state='withdrawn' WHERE id=$1",[$held]); }
    }
    public function test_short_trip_rate_previews_keep_the_tier_rate_before_rounding_the_amount(): void
    {
        $high=$this->service()->createTrip($this->tripBody(['date'=>'2026-10-20','km'=>'0.1']))['body']['data'];
        $this->assertSame('3.79',$high['rate_preview']);
        $this->assertSame('0.38',$high['amount_preview']);
        $held=$this->statement($this->service()->allowanceScope(),2026,'20000.0');
        try {
            $low=$this->service()->createTrip($this->tripBody(['date'=>'2026-10-21','km'=>'0.1']))['body']['data'];
            $this->assertSame('2.23',$low['rate_preview']);
            $this->assertSame('0.22',$low['amount_preview']);
        } finally { self::sql("UPDATE mobile_mileage_statements SET state='withdrawn' WHERE id=$1",[$held]); }
    }

    public function test_annual_lock_serializes_same_employee_and_keeps_years_independent(): void
    {
        $scope=$this->service()->allowanceScope();
        $other=RestApiEnv::connect(RestApiEnv::testDb());
        self::sql('BEGIN');
        try {
            mileage_lock($scope,2026,self::$conn);
            RestApiEnv::rows($other,'BEGIN');
            $same=RestApiEnv::rows($other,"SELECT pg_try_advisory_xact_lock(hashtext($1),2026) AS ok",['saldi:mileage:'.$scope])[0]['ok'];
            $next=RestApiEnv::rows($other,"SELECT pg_try_advisory_xact_lock(hashtext($1),2027) AS ok",['saldi:mileage:'.$scope])[0]['ok'];
            $this->assertSame('f',$same);
            $this->assertSame('t',$next);
        } finally { RestApiEnv::rows($other,'ROLLBACK'); pg_close($other); self::sql('ROLLBACK'); }
        $this->expectException(LogicException::class);
        mileage_lock($scope,2026,self::$conn);
    }
    public function test_settings_rights_csrf_tenant_binding_and_whole_form_validation(): void
    {
        $session=$_SESSION ?? null;
        $_SESSION=['csrf_token'=>bin2hex(random_bytes(24)),'mobile_marken_settings_db'=>RestApiEnv::testDb()];
        $input=['csrf_token'=>$_SESSION['csrf_token'],'year'=>'2029','claims_payable_account'=>'987610','claims_mileage_account'=>'987611',
            'km_rate'=>'4.00','km_rate_low'=>'2.00','km_threshold'=>'20000','booking_day_start'=>'07:00','booking_day_end'=>'17:00','booking_slot_minutes'=>'60'];
        $before=self::sql("SELECT * FROM settings WHERE var_grp='mobile_marken' ORDER BY id");
        try {
            $this->assertSame(5498,mobilMarkenSave($input));
            self::sql('UPDATE brugere SET rettigheder=$1 WHERE id=$2',['0100000000000000',self::$userId]);
            foreach ([['csrf_token'=>'incorrect'],['claims_payable_account'=>'987611'],['km_rate_low'=>'4.01'],['km_threshold'=>'20000.01'],['booking_day_start'=>'17:00'],['booking_slot_minutes'=>'0']] as $change) {
                $this->assertSame(5498,mobilMarkenSave(array_replace($input,$change)));
                $this->assertSame($before,self::sql("SELECT * FROM settings WHERE var_grp='mobile_marken' ORDER BY id"));
            }
            $_SESSION['mobile_marken_settings_db']='another_tenant';
            $this->assertSame(5498,mobilMarkenSave($input));
            $_SESSION['mobile_marken_settings_db']=RestApiEnv::testDb();
            self::sql('UPDATE brugere SET rettigheder=$1 WHERE id=$2',['0200000000000000',self::$userId]);
            $this->assertTrue(mobilBetalingAllowed('read'));
            $this->assertFalse(mobilBetalingAllowed('write'));
            $this->assertSame(5498,mobilMarkenSave($input));
            $this->assertSame($before,self::sql("SELECT * FROM settings WHERE var_grp='mobile_marken' ORDER BY id"));
            self::sql('UPDATE brugere SET rettigheder=$1 WHERE id=$2',['0100000000000000',self::$userId]);
            $this->assertSame(5499,mobilMarkenSave($input));
            $this->assertSame('4.00',mileage_rate(2029,self::$conn)['rate']);
            $this->assertSame('3.79',mileage_rate(2026,self::$conn)['rate']);
            $this->assertTrue($this->service()->capability());
            $this->assertSame(5499,mobilMarkenSave(array_replace($input,['claims_payable_account'=>''])));
            $this->assertFalse($this->service()->capability());
        } finally {
            self::setting('claims_payable_account','987610');
            self::sql('UPDATE brugere SET rettigheder=$1 WHERE id=$2',[str_repeat('0',16),self::$userId]);
            if ($session===null) { unset($_SESSION); } else { $_SESSION=$session; }
        }
    }
    public function test_translation_ids_are_unique_and_complete(): void
    {
        $counts=[];
        foreach (file(__DIR__.'/../../importfiler/tekster.csv') as $line) {
            $id=(int)explode("\t",$line)[0];
            if ($id>=5490 && $id<=5499) { $counts[$id]=($counts[$id] ?? 0)+1; }
        }
        $this->assertCount(10,$counts);
        foreach ($counts as $count) { $this->assertSame(1,$count); }
    }
}
