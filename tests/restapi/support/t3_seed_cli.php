<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/t3_seed_cli.php --- ver 5.0.0 --- 2026.10.06
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

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/T3Seed.php';
require_once __DIR__ . '/../../../includes/openpost/udlignId.php';

/** JSON-line fixture; only setup, observations and tenant-scoped CLI jobs run here. */
final class T3ContractSeed
{
    private ?T3Seed $seed = null;
    private array $buckets = [];
    private int $customer;
    private int $invoice;
    private int $product;
    private ?int $shop = null;

    /** @return array{account:string,tenant_id:int,year:int,users:array<string,array{username:string,password:string}>,scanner:array{invoice:int,product:int}} */
    public function start(): array
    {
        putenv('SALDI_REST_TEST_DB=saldi_t3c1');
        putenv('SALDI_REST_KEEP_TENANT=');
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            throw new RuntimeException($reason);
        }
        $this->seed = new T3Seed();
        $users = ['full'=>['username'=>RestApiEnv::user(),'password'=>RestApiEnv::password()]];
        foreach (['receipt'=>str_repeat('0',16),'other'=>str_repeat('0',16),'read'=>'0020000000000000'] as $role=>$rights) {
            $users[$role] = ['username'=>'t3c1_' . $role . '_' . bin2hex(random_bytes(4)),
                'password'=>TestCredentials::password('t3c1_' . $role)];
            $row = $this->seed->sql('INSERT INTO brugere(brugernavn,kode,rettigheder,status,twofactor,ip_address)
                VALUES($1,$2,$3,true,false,\'\') RETURNING *',[$users[$role]['username'],md5($users[$role]['password']),$rights])[0];
            $this->seed->users[$role] = $row;
            $this->seed->subjects[$role] = Subject::build('account',(int)$row['id']);
        }
        $users['registry'] = ['username'=>'t3c1r_' . bin2hex(random_bytes(5)),
            'password'=>TestCredentials::password('t3c1_registry')];
        $this->seed->users['registry'] = RestApiEnv::rows($this->seed->registry,
            'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,true) RETURNING *',
            [$users['registry']['username'],md5($users['registry']['password']),',,,' . $this->seed->tenantId])[0];
        $this->seed->subjects['registry'] = Subject::build('registry',(int)$this->seed->users['registry']['id']);
        foreach ($users as $role=>$user) {
            $this->buckets[] = MobileAuth::bucketKey($role === 'registry' ? 'registry' : 'account',
                $role === 'registry' ? '' : RestApiEnv::accountOpen(),$user['username']);
        }
        $this->customer = (int)$this->seed->sql("INSERT INTO adresser(art,kontonr,firmanavn) VALUES('D','987099','T3 scanner customer') RETURNING id")[0]['id'];
        $order = (int)$this->seed->sql("INSERT INTO ordrer(art,konto_id,kontonr,firmanavn,ordrenr,fakturanr,fakturadate,sum,moms,valuta,status)
            VALUES('DO',$1,'987099','T3 scanner customer',987099,'987099',current_date,100,0,'DKK',4) RETURNING id",[$this->customer])[0]['id'];
        $this->invoice = (int)$this->seed->sql("INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,forfaldsdate,kladde_id,refnr,valuta,valutakurs)
            VALUES($1,'987099','987099',100,'Invoice','0',current_date,current_date,0,$2,'DKK',100) RETURNING id",[$this->customer,$order])[0]['id'];
        $this->seed->sql("INSERT INTO grupper(art,kodenr,box8) VALUES('VG',987099,'on')");
        $this->product = (int)$this->seed->sql("INSERT INTO varer(varenr,beskrivelse,enhed,beholdning,min_lager,gruppe)
            VALUES('T3C1-STOCK','T3 stock fixture','stk',10,3,987099) RETURNING id")[0]['id'];
        return ['account'=>RestApiEnv::accountOpen(),'tenant_id'=>$this->seed->tenantId,'year'=>$this->seed->year,
            'users'=>$users,'scanner'=>['invoice'=>$this->invoice,'product'=>$this->product]];
    }

    /** @return array<string,mixed> Allowlisted operations confined to this process's disposable tenant. */
    public function command(array $input): array
    {
        switch (ifset($input,'command')) {
            case 'ledger':
                $result = [];
                foreach (['transaktioner','openpost','kontoplan'] as $table) {
                    $rows = $this->seed->sql('SELECT * FROM ' . $table . ' ORDER BY id');
                    $result[$table] = ['count'=>count($rows),'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];
                }
                return $result;
            case 'attachment':
                $id = (int)ifset($input,'id',0);
                $row = $this->seed->row($id);
                $path = $this->seed->root . '/' . $row['storage_path'];
                return ['status'=>$row['status'],'review_state'=>$row['review_state'],
                    'file_exists'=>is_file($path),'sha256'=>is_file($path) ? hash_file('sha256',$path) : null,
                    'documents'=>$this->seed->sql('SELECT source_id,pool_file_id FROM documents WHERE pool_file_id=$1 ORDER BY id',[$id]),
                    'provenance'=>$this->seed->sql('SELECT mode,kassekladde_id,kladde_id FROM mobile_journal_lines WHERE pool_file_id=$1 ORDER BY id',[$id]),
                    'moves'=>$this->seed->sql('SELECT state FROM pool_file_moves WHERE pool_file_id=$1 ORDER BY id',[$id])];
            case 'bank-import':
                $this->seed->sql("INSERT INTO kontoplan(kontonr,beskrivelse,kontotype,lukket,moms,regnskabsaar,saldo)
                    SELECT 6471,'Contract bank','S','','',$1,0 WHERE NOT EXISTS (SELECT 1 FROM kontoplan WHERE kontonr=6471 AND regnskabsaar=$1)",[$this->seed->year]);
                $journal = $this->seed->journal();
                $result = $this->seed->web('run_bankimport_page.php',[json_encode(['fixture'=>'outgoing.tsv'],JSON_THROW_ON_ERROR),$journal,$this->seed->session]);
                if ($result['exit'] !== 0) {
                    fwrite(STDERR,$result['stderr']);
                    throw new RuntimeException('Owned bank-import runner failed');
                }
                // Keep the legacy warning floor visible without sending the rendered page over the protocol.
                if ($result['stderr'] !== '') {
                    fwrite(STDERR,$result['stderr']);
                }
                $bank = $this->seed->sql('SELECT b.id,l.kassekladde_id FROM bank_lines b JOIN bank_line_journal_links l ON l.bank_line_id=b.id
                    JOIN kassekladde k ON k.id=l.kassekladde_id WHERE k.kladde_id=$1',[$journal]);
                if (count($bank) !== 1) {
                    throw new RuntimeException('Bank import did not persist one durable line');
                }
                return ['id'=>(int)$bank[0]['id'],'line_id'=>(int)$bank[0]['kassekladde_id'],'journal_id'=>$journal];
            case 'bank-row':
                return $this->seed->sql('SELECT debet,kredit,amount,debetvat,kreditvat,momsfri FROM kassekladde WHERE id=$1',[(int)ifset($input,'id',0)])[0];
            case 'scan':
                return $this->cron('mobile_scanners.php');
            case 'fanout':
                return $this->cron('mobile_events.php');
            case 'scanner-change':
                if ($this->shop !== null) {
                    throw new LogicException('Scanner fixture already changed');
                }
                $this->seed->sql('BEGIN');
                try {
                    $group = udlign_next_id($this->seed->tenant);
                    $this->seed->sql("UPDATE openpost SET udlignet='1',udlign_id=$1,udlign_date=current_date WHERE id=$2",[$group,$this->invoice]);
                    $this->seed->sql("INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,kladde_id,udlign_id,valuta,valutakurs)
                        VALUES($1,'987099','987099',-100,'Payment','1',current_date,31,$2,'DKK',100)",[$this->customer,$group]);
                    $this->seed->sql('UPDATE varer SET beholdning=2 WHERE id=$1',[$this->product]);
                    $this->shop = (int)$this->seed->sql("INSERT INTO ordrer(art,konto_id,kontonr,firmanavn,ordrenr,sum,moms,shop_id)
                        VALUES('DO',$1,'987099','T3 scanner customer',987100,200,50,987099) RETURNING id",[$this->customer])[0]['id'];
                    $this->seed->sql('COMMIT');
                } catch (Throwable $error) {
                    $this->seed->sql('ROLLBACK');
                    throw $error;
                }
                return ['invoice'=>$this->invoice,'product'=>$this->product,'shop'=>$this->shop];
            case 'events':
                return ['items'=>$this->seed->sql('SELECT type,ref_id,notification_id FROM mobile_events ORDER BY id')];
            case 'registry-membership':
                $allowed = ifset($input,'allowed') === true;
                RestApiEnv::rows($this->seed->registry,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',
                    [$allowed ? ',,,' . $this->seed->tenantId : ',,,',$this->seed->users['registry']['id']]);
                return ['allowed'=>$allowed];
            default:
                throw new InvalidArgumentException('Unknown fixture command');
        }
    }

    /** @return array<string,int|bool> Actual CLI command output; jobs never contact push/payment providers. */
    private function cron(string $name): array
    {
        $process = proc_open([PHP_BINARY,__DIR__ . '/../../../cron/' . $name,'--tenant-id=' . $this->seed->tenantId],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start tenant CLI job');
        }
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($errors !== '') {
            fwrite(STDERR,$errors);
        }
        if ($exit !== 0) {
            throw new RuntimeException('Tenant CLI job failed');
        }
        return json_decode(trim($output),true,512,JSON_THROW_ON_ERROR);
    }

    /** Remove all owned login families, registry rows, tenant and documents on stdin EOF. @return void */
    public function close(): void
    {
        if ($this->seed === null) {
            RestApiEnv::teardownTenant();
            return;
        }
        foreach (['refresh_sessions','auth_versions','auth_challenges'] as $table) {
            RestApiEnv::rows($this->seed->registry,'DELETE FROM ' . $table . ' WHERE tenant_id=$1',[$this->seed->tenantId]);
        }
        foreach ($this->buckets as $bucket) {
            RestApiEnv::rows($this->seed->registry,"DELETE FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1",[$bucket]);
        }
        $this->seed->close();
        $this->seed = null;
    }
}

$fixture = new T3ContractSeed();
try {
    echo json_encode(['data'=>$fixture->start()],JSON_THROW_ON_ERROR) . PHP_EOL;
    while (($line = fgets(STDIN)) !== false) {
        $input = json_decode($line,true,512,JSON_THROW_ON_ERROR);
        if (ifset($input,'command') === 'close') {
            break;
        }
        try {
            echo json_encode(['data'=>$fixture->command($input)],JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (Throwable $error) {
            fwrite(STDERR,get_class($error) . ': fixture command failed at ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL);
            echo json_encode(['error'=>'Fixture command failed']) . PHP_EOL;
        }
    }
} finally {
    $fixture->close();
}
