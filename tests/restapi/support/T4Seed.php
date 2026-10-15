<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T4Seed.php --- ver 5.0.0 --- 2026.10.07
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

require_once __DIR__ . '/T3Seed.php';
require_once __DIR__ . '/../../../restapi/core/MobileAuth.php';

/** Disposable field-work tenant shared by the rights matrix and the app contract runner. */
final class T4Seed
{
    public T3Seed $base;
    public array $employees = [];
    public int $caseId;
    public int $taskId;
    public int $stockGroup = 989410;
    public array $contracts = [];
    public array $contractErrors = [];

    public function __construct()
    {
        $this->base = new T3Seed();
        $this->base->personas();
        require_once __DIR__ . '/../../../restapi/core/JwtSecretProvisioning.php';
        JWT::setSecret(_jwtLoadSecret(JWT::secretPath()));
        $this->sql("DELETE FROM grupper WHERE art IN ('API','LG')");
        $this->sql("INSERT INTO grupper(art,kodenr,beskrivelse,box3,box8,box9,fiscal_year) VALUES
            ('VG',$1,'T4 stock','987001','on','',$2),('LG',1,'One','','','',$2),('LG',2,'Two','','','',$2)",
            [$this->stockGroup,$this->base->year]);
        $company = $this->sql("SELECT id FROM adresser WHERE art='S' ORDER BY id LIMIT 1");
        $companyId = $company ? (int)$company[0]['id'] : (int)$this->sql("INSERT INTO adresser(art,firmanavn) VALUES('S','T4 company') RETURNING id")[0]['id'];
        $full = $this->sql('SELECT * FROM brugere WHERE brugernavn=$1',[RestApiEnv::user()])[0];
        $this->base->users['full'] = $full;
        foreach (['receipt','other','full','stockRead','receiptRead','time','noCase','leader','admin'] as $role) {
            $digits = str_repeat('0',16);
            if ($role === 'full') { $digits = str_repeat('1',16); }
            if ($role === 'stockRead') { $digits[9] = '2'; }
            if ($role === 'receiptRead') { $digits[7] = '2'; }
            if ($role === 'admin') { $digits[1] = '1'; }
            $caseDigits = $role === 'full' ? '11111111' : ($role === 'time' ? '10000000' : ($role === 'leader' ? '00000010' : '00000000'));
            $group = (int)$this->sql("INSERT INTO grupper(art,box2) VALUES('ANSAT',$1) RETURNING id",[$caseDigits])[0]['id'];
            $this->employees[$role] = (int)$this->sql("INSERT INTO ansatte(navn,initialer,nummer,konto_id,gruppe,lukket,loen,trainee,startdate)
                VALUES($1,'FE',$2,$3,$4,'',180,'','2026-01-01') RETURNING id",['T4 '.$role,(string)(100+count($this->employees)),$companyId,$group])[0]['id'];
            if (!isset($this->base->users[$role])) {
                $this->base->users[$role] = $this->sql('INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,true) RETURNING *',
                    ['t4x1_'.bin2hex(random_bytes(6)),md5(TestCredentials::password('t4x1_'.strtolower($role))),$digits])[0];
            }
            $user = $this->base->users[$role];
            $this->sql('UPDATE brugere SET ansat_id=$1,rettigheder=$2 WHERE id=$3',[$this->employees[$role],$digits,$user['id']]);
            $this->base->subjects[$role] = Subject::build('account',(int)$user['id']);
            $this->base->tokens[$role] = (new MobileAuth())->create($user,'account',$this->base->tenantId,'t4x1-'.$role)['access_token'];
        }
        $this->caseId = (int)$this->sql("INSERT INTO sager(sagsnr,beskrivelse,status,firmanavn,udf_addr1) VALUES($1,'T4 case','Montage','T4 customer','Testvej 1') RETURNING id",['T4-'.bin2hex(random_bytes(4))])[0]['id'];
        $this->taskId = (int)$this->sql("INSERT INTO opgaver(nr,assign_id,assign_to,beskrivelse,status) VALUES(1,$1,'sager','T4 task','Montage') RETURNING id",[$this->caseId])[0]['id'];
        foreach (['claims_payable_account'=>'987010','claims_mileage_account'=>'987001','km_rate_2026'=>'3.79','km_rate_low_2026'=>'2.23','km_threshold_2026'=>'20000'] as $key=>$value) {
            $this->setting($key,$value);
        }
        $directory = dirname(__DIR__,3).'/temp/'.RestApiEnv::testDb();
        if (!is_dir($directory)) { mkdir($directory,0777,true); }
        chmod($directory,0777);
        file_put_contents($directory.'/rest_api.log','');
        chmod($directory.'/rest_api.log',0666);
    }

    /** @return list<array<string,string|null>> Parameterized SQL on this disposable tenant. */
    public function sql(string $sql,array $params = []): array { return $this->base->sql($sql,$params); }

    public static function uuid(): string { return T3Seed::uuid(); }

    public function setting(string $name,string $value): void
    {
        $this->sql("DELETE FROM settings WHERE var_grp='mobile_marken' AND user_id=0 AND var_name=$1",[$name]);
        $this->sql("INSERT INTO settings(var_grp,var_name,var_value,user_id) VALUES('mobile_marken',$1,$2,0)",[$name,$value]);
    }

    /** @return array{status:int,json:?array,body:string,headers:array<string,string>} Real HTTP response. */
    public function request(string $method,string $path,?array $body = null,string $role = 'full',array $headers = []): array
    {
        return T3Seed::http($method,$path,$body,$role === 'anonymous' ? null : $this->base->tokens[$role],$headers);
    }

    /** @return array{item:int,order:int,line:int,code:string} Fresh stock item and open purchase order. */
    public function stock(): array
    {
        $code = 'T4-'.bin2hex(random_bytes(6));
        $item = (int)$this->sql("INSERT INTO varer(varenr,beskrivelse,enhed,gruppe,kostpris,salgspris,beholdning,serienr,samlevare,lukket,has_due_date)
            VALUES($1,'T4 stock','stk',$2,4,8,10,'','','',false) RETURNING id",[$code,$this->stockGroup])[0]['id'];
        $this->sql('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($1,0,2,10)',[$item]);
        $this->sql("INSERT INTO batch_kob(vare_id,variant_id,linje_id,ordre_id,kobsdate,antal,rest,lager,pris) VALUES($1,0,0,0,'2026-01-01',10,10,2,4)",[$item]);
        $order = (int)$this->sql("INSERT INTO ordrer(art,status,ordrenr,ordredate,levdate,kontonr,firmanavn,hvem,tidspkt,ref)
            VALUES('KO',1,989410,'2026-10-01','2026-10-06','987003','T4 supplier','',0,$1) RETURNING id",[RestApiEnv::user()])[0]['id'];
        $line = (int)$this->sql("INSERT INTO ordrelinjer(ordre_id,posnr,varenr,vare_id,antal,leveres,leveret,serienr,variant_id,lager,kred_linje_id,beskrivelse,enhed,pris)
            VALUES($1,1,$2,$3,10,0,0,'',0,2,0,'T4 stock','stk',4) RETURNING id",[$order,$code,$item])[0]['id'];
        return ['item'=>$item,'order'=>$order,'line'=>$line,'code'=>$code];
    }

    /** @return array{client_id:string,session_id:string,lager:int,reason:string,lines:list<array>} Count from the real snapshot. */
    public function countBody(array $stock): array
    {
        $snapshot = $this->request('GET','stock/counts/lookup.php?lager=2&code='.rawurlencode($stock['code']))['json']['data']['items'][0];
        return ['client_id'=>self::uuid(),'session_id'=>self::uuid(),'lager'=>2,'reason'=>'optaelling','lines'=>[
            ['line_ref'=>self::uuid(),'vare_id'=>$stock['item'],'counted_qty'=>'7','expected_qty'=>$snapshot['quantity'],
                'expected_token'=>$snapshot['token'],'counted_at'=>'2026-10-06T10:00:00+02:00']]];
    }

    /** @return array<string,int|string> Valid single-employee time registration. */
    public function timeBody(): array
    {
        return ['client_id'=>self::uuid(),'case_id'=>$this->caseId,'task_id'=>$this->taskId,'date'=>'2026-10-06','hours'=>'7.50','text'=>'T4 work'];
    }

    /** @return array<string,mixed> Valid booking on the caller. */
    public function bookingBody(): array
    {
        return ['client_id'=>self::uuid(),'date'=>'2026-10-06','start'=>'08:00','duration_minutes'=>60,
            'customer'=>['name'=>'T4 customer'],'service'=>'T4 visit','case_id'=>$this->caseId];
    }

    /** @return array<string,string> Valid unclaimed trip. */
    public function tripBody(): array
    {
        return ['client_id'=>self::uuid(),'date'=>'2026-10-06','from'=>'Office','to'=>'Customer','km'=>'42.0','purpose'=>'T4 visit'];
    }

    public function close(): void
    {
        $output = getenv('SALDI_T4_CONTRACT_OUTPUT');
        if ($output && $this->contracts) {
            file_put_contents($output,json_encode(['cases'=>$this->contracts,'errors'=>$this->contractErrors],JSON_THROW_ON_ERROR));
        }
        $this->base->close();
    }
}
