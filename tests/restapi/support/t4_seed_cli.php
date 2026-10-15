<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/t4_seed_cli.php --- ver 5.0.0 --- 2026.10.07
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
require_once __DIR__ . '/T4Seed.php';

/** Process-owned setup and read-only observations; business writes use the real HTTP client. */
final class T4ContractSeed
{
    private ?T4Seed $seed = null;

    /**
     * Create an isolated T4 tenant; passwords exist only in this process and its fixture protocol.
     *
     * @return array{
     *   account:string,tenant_id:int,year:int,
     *   users:array<string,array{username:string,password:string}>,
     *   employees:array<string,int>,case_id:int,task_id:int,
     *   stock:array<string,array{item:int,order:int,line:int,code:string}>
     * }
     */
    public function start(): array
    {
        putenv('SALDI_REST_TEST_DB=saldi_t4c1');
        putenv('SALDI_REST_KEEP_TENANT=');
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            throw new RuntimeException($reason);
        }
        $this->seed = new T4Seed();
        $users = [];
        foreach (['full','time','receipt','other'] as $role) {
            $user = $this->seed->base->users[$role];
            $password = TestCredentials::password('t4c1_' . $role);
            $this->seed->sql('UPDATE brugere SET kode=$1 WHERE id=$2',[md5($password),(int)$user['id']]);
            $users[$role] = ['username'=>$user['brugernavn'],'password'=>$password];
        }
        $stock = [];
        foreach (['count','stale','receipt'] as $name) {
            $stock[$name] = $this->seed->stock();
        }
        return ['account'=>RestApiEnv::accountOpen(),'tenant_id'=>$this->seed->base->tenantId,
            'year'=>$this->seed->base->year,'users'=>$users,'employees'=>$this->seed->employees,
            'case_id'=>$this->seed->caseId,'task_id'=>$this->seed->taskId,'stock'=>$stock];
    }

    /**
     * Observe committed business facts using fixed queries confined to this disposable tenant.
     *
     * @param array{command?:string,id?:int} $input
     * @return array<string,array{count:int,sha256:string}>|array{
     *   item:array{beholdning:string},
     *   warehouses:list<array{lager:string,beholdning:string}>,
     *   purchases:list<array{antal:string,rest:string,lager:string}>,
     *   sales:list<array{antal:string,lager:string}>,logs:list<array{id:string}>,
     *   counts:list<array{before_qty:string,after_qty:string,delta:string,resolution:string}>
     * }|array{
     *   status:string,review_state:string,file_exists:bool,sha256:?string,
     *   documents:list<array{source_id:string,pool_file_id:string}>,moves:list<array{state:string}>
     * }|array{
     *   item:array{status:string,version:string,done_at:?string},
     *   history:list<array{event:string,before_json:?string,after_json:string}>
     * }|array{
     *   claim:array{kind:string,ansat_id:string,statement_id:string},
     *   statement:array{state:string,pdf_sha256:string,km_total:string,amount:string},
     *   drafts:list<array{debet:string,kredit:string,amount:string,momsfri:string,debetvat:string,kreditvat:string,ansat:string}>
     * }
     */
    public function command(array $input): array
    {
        $id = (int)ifset($input,'id',0);
        switch (ifset($input,'command')) {
            case 'ledger':
                $result = [];
                foreach (['transaktioner','openpost','kontoplan'] as $table) {
                    $rows = $this->seed->sql('SELECT * FROM ' . $table . ' ORDER BY id');
                    $result[$table] = ['count'=>count($rows),'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];
                }
                return $result;
            case 'stock':
                return ['item'=>$this->seed->sql('SELECT beholdning FROM varer WHERE id=$1',[$id])[0],
                    'warehouses'=>$this->seed->sql('SELECT lager,beholdning FROM lagerstatus WHERE vare_id=$1 ORDER BY lager',[$id]),
                    'purchases'=>$this->seed->sql('SELECT antal,rest,lager FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$id]),
                    'sales'=>$this->seed->sql('SELECT antal,lager FROM batch_salg WHERE vare_id=$1 ORDER BY id',[$id]),
                    'logs'=>$this->seed->sql('SELECT id FROM stocklog WHERE item_id=$1 ORDER BY id',[$id]),
                    'counts'=>$this->seed->sql('SELECT before_qty,after_qty,delta,resolution FROM mobile_stock_count_lines WHERE vare_id=$1 ORDER BY id',[$id])];
            case 'attachment':
                $row = $this->seed->base->row($id);
                $path = $this->seed->base->root . '/' . $row['storage_path'];
                return ['status'=>$row['status'],'review_state'=>$row['review_state'],'file_exists'=>is_file($path),
                    'sha256'=>is_file($path) ? hash_file('sha256',$path) : null,
                    'documents'=>$this->seed->sql('SELECT source_id,pool_file_id FROM documents WHERE pool_file_id=$1 ORDER BY id',[$id]),
                    'moves'=>$this->seed->sql('SELECT state FROM pool_file_moves WHERE pool_file_id=$1 ORDER BY id',[$id])];
            case 'booking':
                return ['item'=>$this->seed->sql('SELECT status,version,done_at FROM mobile_bookings WHERE id=$1',[$id])[0],
                    'history'=>$this->seed->sql('SELECT event,before_json,after_json FROM mobile_booking_history WHERE booking_id=$1 ORDER BY id',[$id])];
            case 'claim':
                return ['claim'=>$this->seed->sql('SELECT kind,ansat_id,statement_id FROM mobile_expense_claims WHERE pool_file_id=$1',[$id])[0],
                    'statement'=>$this->seed->sql('SELECT s.state,s.pdf_sha256,s.km_total,s.amount FROM mobile_mileage_statements s WHERE s.pool_file_id=$1',[$id])[0],
                    'drafts'=>$this->seed->sql('SELECT k.debet,k.kredit,k.amount,k.momsfri,k.debetvat,k.kreditvat,k.ansat FROM kassekladde k JOIN mobile_journal_lines m ON m.kassekladde_id=k.id WHERE m.pool_file_id=$1 ORDER BY k.id',[$id])];
            default:
                throw new InvalidArgumentException('Unknown fixture command');
        }
    }

    /** @return void Remove HTTP login families before T4Seed drops its tenant and files. */
    public function close(): void
    {
        if ($this->seed === null) {
            RestApiEnv::teardownTenant();
            return;
        }
        foreach (['refresh_sessions','auth_versions','auth_challenges'] as $table) {
            RestApiEnv::rows($this->seed->base->registry,'DELETE FROM ' . $table . ' WHERE tenant_id=$1',[$this->seed->base->tenantId]);
        }
        $this->seed->close();
        $this->seed = null;
    }
}

$fixture = new T4ContractSeed();
try {
    echo json_encode(['data'=>$fixture->start()],JSON_THROW_ON_ERROR) . PHP_EOL;
    while (($line = fgets(STDIN)) !== false) {
        try {
            $input = json_decode($line,true,512,JSON_THROW_ON_ERROR);
            if (!is_array($input)) {
                throw new InvalidArgumentException('Fixture command must be an object');
            }
            echo json_encode(['data'=>$fixture->command($input)],JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (Throwable $error) {
            fwrite(STDERR,get_class($error) . ': fixture command failed at ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL);
            echo json_encode(['error'=>'Fixture command failed']) . PHP_EOL;
        }
    }
} finally {
    $fixture->close();
}
