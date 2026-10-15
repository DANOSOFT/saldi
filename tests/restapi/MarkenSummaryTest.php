<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MarkenSummaryTest.php --- ver 5.0.0 --- 2026.10.06
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

require_once __DIR__ . '/support/T4ClaimsTestCase.php';

/** Public summary respects live rights, Copenhagen week bounds and packed employee privacy. */
final class MarkenSummaryTest extends T4ClaimsTestCase
{
    protected function setUp(): void
    {
        self::sql('UPDATE brugere SET rettigheder=$1,ansat_id=$2 WHERE id=$3',[str_repeat('0',16),self::$employeeId,self::$userId]);
        self::sql("UPDATE ansatte SET lukket='',gruppe=NULL WHERE id=$1",[self::$employeeId]);
        self::setting('claims_payable_account','987610');
    }
    private function summary(?array $headers=null): array
    {
        return $this->assertStatus(200,$this->request('GET','marken/summary.php',null,$headers));
    }
    private function rights(array $positions): void
    {
        $rights=str_repeat('0',16);
        foreach ($positions as $position=>$digit) { $rights[$position]=$digit; }
        self::sql('UPDATE brugere SET rettigheder=$1 WHERE id=$2',[$rights,self::$userId]);
    }
    public function testSectionRightsUseCurrentDigitsAndEmployeeCaseGroup(): void
    {
        $base=$this->summary();
        $this->assertSame(['stock_count'=>false,'goods_receipt'=>false,'time'=>false,'bookings'=>true,'claims'=>true,'catalogue'=>false],$base['sections']);
        $this->assertNull($base['hours_week']); $this->assertNull($base['open_receipt_orders']);
        $this->rights([9=>'2']);
        $read=$this->summary(); $this->assertFalse($read['sections']['stock_count']); $this->assertTrue($read['sections']['catalogue']);
        $this->rights([9=>'1',7=>'2']);
        $group=(int)self::sql("INSERT INTO grupper(art,kodenr,box2) VALUES ('ANSAT',987701,'1000000') RETURNING id")[0]['id'];
        self::sql('UPDATE ansatte SET gruppe=$1 WHERE id=$2',[$group,self::$employeeId]);
        $allowed=$this->summary();
        $this->assertSame(array_fill_keys(['stock_count','goods_receipt','time','bookings','claims','catalogue'],true),$allowed['sections']);
        $this->assertSame('0.00',$allowed['hours_week']);
        $this->assertSame(['count'=>0,'first'=>null],$allowed['open_receipt_orders']);
        $this->rights([5=>'2']);
        $sales=$this->summary(); $this->assertTrue($sales['sections']['catalogue']); $this->assertFalse($sales['sections']['stock_count']);
        self::sql("UPDATE grupper SET box2='0000000' WHERE id=$1",[$group]);
        $this->assertFalse($this->summary()['sections']['time']);
        // Live link/closed-state checks are independent of the unchanged access token.
        self::sql("UPDATE ansatte SET lukket='on' WHERE id=$1",[self::$employeeId]);
        $closed=$this->summary(); $this->assertFalse($closed['sections']['bookings']); $this->assertNull($closed['bookings_today']);
        self::sql('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$userId]);
        $unlinked=$this->summary(); $this->assertFalse($unlinked['sections']['time']); $this->assertFalse($unlinked['sections']['bookings']);
        $this->assertTrue($unlinked['sections']['claims'],'Unlinked logins can still record their own trips and expenses');
        self::setting('claims_payable_account','');
        $missing=$this->summary(); $this->assertFalse($missing['sections']['claims']); $this->assertNull($missing['km_week']); $this->assertNull($missing['expenses_week']);
    }
    public function testFiguresUseOwnSubjectOwnPackedHoursAndCopenhagenWeek(): void
    {
        $today=new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen'));
        $from=$today->modify('-'.((int)$today->format('N')-1).' days'); $to=$from->modify('+6 days');
        $date=$today->format('Y-m-d');
        $group=(int)self::sql("INSERT INTO grupper(art,kodenr,box2) VALUES ('ANSAT',987702,'1000000') RETURNING id")[0]['id'];
        self::sql('UPDATE ansatte SET gruppe=$1 WHERE id=$2',[$group,self::$employeeId]);
        $otherEmployee=(int)self::sql("INSERT INTO ansatte(navn,initialer,lukket) VALUES ('Private colleague','PC','') RETURNING id")[0]['id'];
        foreach ([[$date,'planlagt',self::$employeeId],[$date,'udfoert',$otherEmployee],[$date,'aflyst',self::$employeeId],[$from->modify('-1 day')->format('Y-m-d'),'planlagt',self::$employeeId]] as [$day,$status,$employee]) {
            self::sql("INSERT INTO mobile_bookings(client_id,booking_date,start_time,duration_minutes,ansat_id,customer_name,service,status,created_by)
                VALUES ($1,$2,'08:00',60,$3,'Customer','Field visit',$4,$5)",[RequestId::resolve(null),$day,$employee,$status,self::$owner]);
        }
        self::sql("INSERT INTO loen(art,loendate,ansatte,timer) VALUES ('timer',$1,$2,$3),('timer',$4,$2,'1000')",
            [$date,$otherEmployee."\t".self::$employeeId,"999\t1.60",$from->modify('-1 day')->format('Y-m-d')]);
        self::sql("INSERT INTO loen(art,loendate,ansatte,timer) VALUES ('timer',$1,$2,'888')",[$date,(string)$otherEmployee]);
        $this->service()->createTrip($this->tripBody(['date'=>$from->format('Y-m-d'),'km'=>'10.0']));
        $this->service()->createTrip($this->tripBody(['date'=>$to->format('Y-m-d'),'km'=>'32.5']));
        $this->service()->createTrip($this->tripBody(['date'=>$from->modify('-1 day')->format('Y-m-d'),'km'=>'999.0']));
        $this->service(self::$otherId)->createTrip($this->tripBody(['date'=>$date,'km'=>'888.0']));
        $deleted=$this->service()->createTrip($this->tripBody(['date'=>$date,'km'=>'777.0']))['body']['data']['id'];
        self::sql('UPDATE mobile_mileage_trips SET deleted_at=now() WHERE id=$1',[$deleted]);
        foreach ([$today->format('d-m-Y'),$from->modify('-1 day')->format('Y-m-d')] as $day) {
            $row=$this->fixture(); self::sql('UPDATE pool_files SET file_date=$1 WHERE id=$2',[$day,$row['id']]);
            $this->assertStatus(201,$this->request('POST','employee/expenses.php',$this->body(['attachment_id'=>(int)$row['id']])));
        }
        $row=$this->fixture();
        self::sql('UPDATE pool_files SET file_date=$1,uploaded_by=$2 WHERE id=$3',[$date,Subject::build('account',self::$otherId),$row['id']]);
        $this->assertStatus(201,$this->request('POST','employee/expenses.php',$this->body(['attachment_id'=>(int)$row['id']]),self::$otherHeaders));
        $summary=$this->summary();
        $this->assertSame(['planned'=>1,'done'=>1],$summary['bookings_today']);
        $this->assertSame('1.60',$summary['hours_week']); $this->assertSame('42.5',$summary['km_week']); $this->assertSame(1,$summary['expenses_week']);
        $this->assertStringNotContainsString('Private colleague',json_encode($summary,JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString(self::$owner,json_encode($summary,JSON_THROW_ON_ERROR));
        // A second login linked to the same employee sees their shared hours but their own claims only.
        $alias=$this->summary(self::$otherHeaders); $this->assertSame('1.60',$alias['hours_week']); $this->assertSame('888.0',$alias['km_week']); $this->assertSame(1,$alias['expenses_week']);
        self::sql("UPDATE grupper SET box2='0000000' WHERE id=$1",[$group]);
        $this->assertNull($this->summary()['hours_week']);
    }
    public function testReceiptCountMatchesOutstandingOrdersAndFirstIsNewest(): void
    {
        $this->rights([7=>'2']);
        $ids=[];
        foreach ([[987701,1,'2026-01-01'],[987702,2,'2026-02-01'],[987703,3,'2026-03-01'],[987704,1,'2026-04-01']] as [$number,$status,$date]) {
            $id=(int)self::sql("INSERT INTO ordrer(ordrenr,art,status,ordredate,kontonr,firmanavn) VALUES ($1,'KO',$2,$3,'777','Receipt supplier') RETURNING id",[$number,$status,$date])[0]['id']; $ids[]=$id;
            $line=(int)self::sql("INSERT INTO ordrelinjer(ordre_id,posnr,vare_id,varenr,antal) VALUES ($1,1,987701,'Summary item',10) RETURNING id",[$id])[0]['id'];
            if ($number===987704) { self::sql('INSERT INTO batch_kob(linje_id,antal) VALUES ($1,10)',[$line]); }
        }
        $summary=$this->summary();
        $this->assertSame(['count'=>2,'first'=>['ordrenr'=>987702,'kreditor'=>['kontonr'=>'777','name'=>'Receipt supplier']]],$summary['open_receipt_orders']);
        $this->rights([]); $this->assertNull($this->summary()['open_receipt_orders']);
        foreach ($ids as $id) { self::sql("UPDATE ordrer SET status=3 WHERE id=$1",[$id]); }
    }
    public function testRegistryPrincipalHasNoFieldPersonaAndMethodRequiresAuthentication(): void
    {
        $this->assertStatus(401,$this->request('GET','marken/summary.php',null,[]));
        $this->assertStatus(405,$this->request('POST','marken/summary.php',[]));
        $master=RestApiEnv::connect(RestApiEnv::masterDb());
        $name='summary_registry_'.bin2hex(random_bytes(5));
        $tenant=RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        $user=RestApiEnv::rows($master,'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,true) RETURNING id',
            [$name,md5(TestCredentials::password('marken_registry')),',,,'.$tenant])[0];
        $subject=Subject::build('registry',(int)$user['id']);
        try {
            $login=RestApiEnv::http('POST','/restapi/endpoints/v1/auth/registry-login.php',['username'=>$name,'password'=>TestCredentials::password('marken_registry'),'device_id'=>'marken-summary-test']);
            $data=$this->assertStatus(200,$login);
            $exchange=RestApiEnv::http('POST','/restapi/endpoints/v1/auth/tenant-token.php',['tenant_id'=>$tenant],['Authorization: Bearer '.$data['access_token']]);
            $token=$this->assertStatus(200,$exchange)['access_token'];
            $summary=$this->summary(['Authorization: Bearer '.$token]);
            $this->assertSame(array_fill_keys(['stock_count','goods_receipt','time','bookings','claims','catalogue'],false),$summary['sections']);
            unset($summary['sections']); $this->assertSame(array_fill_keys(array_keys($summary),null),$summary);
        } finally {
            foreach (['refresh_sessions','auth_versions','auth_challenges'] as $table) { RestApiEnv::rows($master,'DELETE FROM '.$table.' WHERE subject=$1',[$subject]); }
            RestApiEnv::rows($master,'DELETE FROM brugere WHERE id=$1',[$user['id']]);
            pg_close($master);
        }
    }
}
