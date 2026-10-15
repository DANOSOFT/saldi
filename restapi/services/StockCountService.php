<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/StockCountService.php --- ver 5.0.0 --- 2026.10.06
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
require_once __DIR__ . '/legacy/LegacyEngine.php';
require_once __DIR__ . '/../core/EmployeeContext.php';
require_once __DIR__ . '/../core/Subject.php';
require_once __DIR__ . '/../../includes/db_query.php';
require_once __DIR__ . '/../../includes/lager/stockMovement.php';
require_once __DIR__ . '/../../includes/lager/productCatalog.php';

/** Warehouse counts reuse the product-card movement engine; all guards precede every movement. */
final class StockCountService
{
    private MobileSchema $store;
    private Idempotency $idempotency;
    private const OPERATION = 'stock.count.submit';
    /** @var Closure|null Test barrier after validation, while the item locks are still held. */
    private ?Closure $afterGuards;

    public function __construct(private EngineContext $context, private string $subject, ?Closure $afterGuards = null)
    {
        $identity = Subject::parse($subject);
        if ($identity['user_id'] !== $context->userId || $identity['installation'] !== Subject::installation()) {
            throw new MobileT2Error('permission_denied', 403);
        }
        $this->store = new MobileSchema($context->connection);
        $this->idempotency = new Idempotency($context->connection);
        $this->afterGuards = $afterGuards;
    }

    /** @return mixed Run legacy helpers with the product card's URL depth as well as its working directory. */
    private function engine(callable $operation): mixed
    {
        $previous = ifset($_SERVER, 'REQUEST_URI');
        $_SERVER['REQUEST_URI'] = '/saldi/lager/varekort.php';
        try {
            return LegacyEngine::run($this->context, $operation);
        } finally {
            if ($previous === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previous;
            }
        }
    }

    /** @return array{items:list<array{lager:int,name:string}>} Product-card warehouse numbers and names. */
    public function warehouses(): array
    {
        $year = (int)$this->context->regnaar;
        $names = [];
        foreach ($this->store->rows("SELECT kodenr,beskrivelse FROM grupper WHERE art='LG'
            ORDER BY CASE WHEN fiscal_year=$year THEN 0 ELSE 1 END,COALESCE(fiscal_year,0) DESC,id DESC") as $row) {
            $number = (int)$row['kodenr'];
            if ($number > 0 && !isset($names[$number])) {
                $names[$number] = ['lager'=>$number,'name'=>(string)$row['beskrivelse']];
            }
        }
        ksort($names, SORT_NUMERIC);
        return ['items'=>array_values($names ?: [1=>['lager'=>1,'name'=>'']])];
    }

    private function warehouse($value): int
    {
        $lager = product_catalog_integer($value, 1);
        if (!in_array($lager, array_column($this->warehouses()['items'], 'lager'), true)) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'warehouse_invalid']);
        }
        return $lager;
    }

    /** @return array{lager:int,as_of:string,items:list<array>,total:int,limit:int,offset:int} */
    public function snapshot($lager, $limit = 500, $offset = 0): array
    {
        $lager = $this->warehouse($lager);
        $paging = new Paging(['limit'=>$limit,'offset'=>$offset], true);
        return $this->readSnapshot(function () use ($lager, $paging): array {
            $year = (int)$this->context->regnaar;
            $where = "WHERE (SELECT g.box8 FROM grupper g WHERE g.art='VG' AND g.kodenr=v.gruppe
                AND g.fiscal_year=$year ORDER BY g.id DESC LIMIT 1)='on'";
            $total = (int)$this->store->rows("SELECT count(*) AS n FROM varer v $where")[0]['n'];
            $rows = $this->store->rows("SELECT v.* FROM varer v $where ORDER BY v.id " . $paging->sql());
            $time = $this->store->rows("SELECT to_char(now() AT TIME ZONE 'UTC','YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS stamp")[0]['stamp'];
            $items = $this->engine(fn(): array => array_map(fn(array $row): array => $this->item($row, $lager), $rows));
            return ['lager'=>$lager,'as_of'=>$time] + $paging->response($items, $total);
        });
    }

    /** @return array{items:list<array>} Exact barcode, alias or item-number matches using T2's catalogue. */
    public function lookup($code, $lager): array
    {
        $lager = $this->warehouse($lager);
        $code = ProductCatalog::text($code, 200);
        return $this->readSnapshot(fn(): array => $this->engine(function () use ($code, $lager): array {
            $matches = (new ProductCatalog($this->context->regnaar))->barcode($code)['items'];
            $items = [];
            foreach ($matches as $match) {
                $id = (int)$match['id'];
                $row = $this->store->rows("SELECT * FROM varer WHERE id=$id")[0];
                $items[] = $this->item($row, $lager);
            }
            return ['items'=>$items];
        }));
    }

    /** Keep paged item metadata, stock and fingerprints in the same database snapshot. */
    private function readSnapshot(callable $read): array
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Stock reads require an idle connection');
        }
        $this->store->execute('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        try {
            $result = $read();
            $this->store->execute('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }

    /** @return array{vare_id:int,varenr:string,name:string,unit:string,barcodes:list<string>,quantity:string,token:string,kostpris:string,countable:bool,why:?string} */
    private function item(array $row, int $lager): array
    {
        $id = (int)$row['id'];
        $snapshot = stock_snapshot($id, $lager);
        $why = stock_item_countable($id);
        $barcodes = [];
        foreach (['stregkode','varenr_alias','varenr'] as $field) {
            $code = trim((string)$row[$field]);
            if ($code !== '' && !in_array($code, $barcodes, true)) {
                $barcodes[] = $code;
            }
        }
        return ['vare_id'=>$id,'varenr'=>(string)$row['varenr'],'name'=>(string)$row['beskrivelse'],
            'unit'=>(string)$row['enhed'],'barcodes'=>$barcodes] + $snapshot
            + ['kostpris'=>number_format((float)$row['kostpris'], 2, '.', ''),'countable'=>$why === null,'why'=>$why];
    }

    /** @return string Validated UUID normalized to the database's one identity. */
    private function uuid($value, string $field): string
    {
        try {
            return Idempotency::clientId(['client_id'=>$value]);
        } catch (MobileT2Error $error) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'count_invalid','field'=>$field]);
        }
    }

    /** @return ?string A supplied instant must include its timezone offset. */
    private function instant($value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'count_invalid','field'=>$field]);
        }
        try {
            $instant = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors && ($errors['warning_count'] || $errors['error_count'])) {
                throw new InvalidArgumentException('Invalid instant');
            }
            return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        } catch (Exception $error) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'count_invalid','field'=>$field]);
        }
    }

    /** @return string A strict three-place decimal quantity; JSON numbers are refused. */
    private function quantity($value, string $field): string
    {
        try {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Quantity must be a string');
            }
            return stock_quantity($value);
        } catch (InvalidArgumentException $error) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'count_invalid','field'=>$field]);
        }
    }

    /** @return array Validated immutable session, preserving the original request for the replay hash. */
    private function validate(array $req): array
    {
        $client = Idempotency::clientId($req);
        $session = $this->uuid(ifset($req, 'session_id'), 'session_id');
        $lager = product_catalog_integer(ifset($req, 'lager'), 1);
        $reason = ifset($req, 'reason');
        if (!is_string($reason) || !in_array($reason, ['optaelling','svind','brud','fejlregistrering'], true)) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'count_invalid','field'=>'reason']);
        }
        $note = ProductCatalog::text(ifset($req, 'note', ''), 4000);
        $started = $this->instant(ifset($req, 'started_at'), 'started_at');
        $lines = ifset($req, 'lines');
        if (!is_array($lines) || !array_is_list($lines) || count($lines) < 1 || count($lines) > 1000) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'count_invalid','field'=>'lines']);
        }
        $ids = $refs = $clean = [];
        foreach ($lines as $line) {
            $line = is_object($line) ? (array)$line : $line;
            if (!is_array($line)) {
                throw new MobileT2Error('validation_failed', 422, ['reason'=>'count_invalid','field'=>'lines']);
            }
            $id = product_catalog_integer(ifset($line, 'vare_id'), 1);
            $ref = $this->uuid(ifset($line, 'line_ref'), 'line_ref');
            $token = ifset($line, 'expected_token');
            $resolution = ifset($line, 'resolution', 'none');
            if (isset($ids[$id]) || isset($refs[$ref]) || !is_string($token) || !preg_match('/^[0-9a-f]{64}$/D', $token)
                || !in_array($resolution, ['none','set'], true)) {
                throw new MobileT2Error('validation_failed', 422, ['reason'=>'count_invalid','field'=>'lines']);
            }
            $ids[$id] = $refs[$ref] = true;
            $clean[] = ['vare_id'=>$id,'line_ref'=>$ref,'expected_token'=>$token,'resolution'=>$resolution,
                'counted_qty'=>$this->quantity(ifset($line, 'counted_qty'), 'counted_qty'),
                'expected_qty'=>$this->quantity(ifset($line, 'expected_qty'), 'expected_qty'),
                'counted_at'=>$this->instant(ifset($line, 'counted_at'), 'counted_at')];
        }
        usort($clean, static fn(array $a, array $b): int => $a['vare_id'] <=> $b['vare_id']);
        return ['client_id'=>$client,'session_id'=>$session,'lager'=>$lager,'reason'=>$reason,'note'=>$note,'started_at'=>$started,'lines'=>$clean];
    }

    /**
     * Commit all count lines and replay record together, then synchronize the changed items.
     * Employee links are optional for stock rights; initials are drawn from the authenticated actor.
     * @return array{status:int,body:array{success:bool,message:string,data:array},headers:array<string,string>}
     */
    public function submit(array $req, EmployeeContext $actor): array
    {
        $identity = Subject::parse($this->subject);
        if ($actor->userId !== $this->context->userId || $actor->principal !== $identity['principal']) {
            throw new MobileT2Error('permission_denied', 403);
        }
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Stock submit owns its transaction');
        }
        $this->store->execute('BEGIN');
        try {
            $replay = $this->idempotency->replay($this->subject, self::OPERATION, null, $req);
            if ($replay !== null) {
                $this->store->execute('COMMIT');
                return $replay;
            }
            $session = $this->validate($req);
            $sessionLiteral = $this->store->literal($session['session_id']);
            $this->store->execute("SELECT pg_advisory_xact_lock(hashtextextended('saldi:count:' || $sessionLiteral,0))");
            $posted = $this->store->rows("SELECT id FROM mobile_stock_counts WHERE session_id=$sessionLiteral");
            if ($posted) {
                throw new MobileT2Error('conflict', 409, ['reason'=>'count_already_posted','count_id'=>(int)$posted[0]['id']]);
            }
            $clientLiteral = $this->store->literal($session['client_id']);
            // Count UUIDs are globally unique even though the general replay scope is per subject.
            $this->store->execute("SELECT pg_advisory_xact_lock(hashtextextended('saldi:count-client:' || $clientLiteral,0))");
            if ($this->store->rows("SELECT id FROM mobile_stock_counts WHERE client_id=$clientLiteral")) {
                throw new MobileT2Error('conflict', 409, ['reason'=>'client_id_reused']);
            }
            $this->warehouse($session['lager']);
            [$response, $targets] = $this->engine(fn(): array => $this->apply($session, $actor));
            $id = $response['body']['data']['count_id'];
            $this->idempotency->store($this->subject, self::OPERATION, null, $req, 201, $response['body'], 'stock_count', $id);
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
        $results = $this->engine(static fn(): array => stock_shop_sync($targets));
        $states = array_column($results, 'state');
        $state = in_array('failed', $states, true) ? 'partial' : (in_array('done', $states, true) ? 'done' : 'not_configured');
        $response['body']['data']['shop_sync'] = $state;
        $this->store->execute('BEGIN');
        try {
            $this->idempotency->complete($this->subject, self::OPERATION, null, $req, 201, $response['body']);
            $literal = $this->store->literal($state);
            $this->store->execute("UPDATE mobile_stock_counts SET shop_sync=$literal WHERE id=$id");
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
        return $response;
    }

    /** @return array{0:array,1:list<array>} Checked movement results and deferred sync targets. */
    private function apply(array $session, EmployeeContext $actor): array
    {
        $lager = $session['lager'];
        $year = (int)$this->context->regnaar;
        $errors = ['not_countable'=>[],'batch_tracked_increase'=>[],'web_count_open'=>[],'count_stale'=>[]];
        $lines = [];
        // Acquire all item locks before reading any stock, consistently with the other stock engines.
        foreach ($session['lines'] as $line) {
            stock_lock_item($line['vare_id']);
        }
        foreach ($session['lines'] as $line) {
            $id = $line['vare_id'];
            $rows = $this->store->rows("SELECT * FROM varer WHERE id=$id FOR UPDATE");
            $row = ifset($rows, 0);
            $why = stock_item_countable($id);
            $snapshot = stock_snapshot($id, $lager, true);
            $detail = ['line_ref'=>$line['line_ref'],'vare_id'=>$id,'varenr'=>(string)ifset($row, 'varenr', '')];
            if ($why !== null) {
                $errors['not_countable'][] = $detail + ['why'=>$why];
            }
            $scope = stock_warehouse_scope($lager);
            if ($this->store->rows("SELECT id FROM regulering WHERE vare_id=$id AND $scope AND bogfort='0' LIMIT 1")) {
                $errors['web_count_open'][] = $detail;
            }
            if (!hash_equals($snapshot['token'], $line['expected_token']) && $line['resolution'] !== 'set') {
                $errors['count_stale'][] = $detail + ['expected_qty'=>$line['expected_qty'],'current_qty'=>$snapshot['quantity'],'counted_qty'=>$line['counted_qty']];
            }
            $group = (int)ifset($row, 'gruppe', 0);
            $groups = $this->store->rows("SELECT box9 FROM grupper WHERE art='VG' AND kodenr=$group AND fiscal_year=$year ORDER BY id DESC LIMIT 1");
            if ((float)$line['counted_qty'] > (float)$snapshot['quantity']
                && (item_has_due_date($id) || ifset($groups, [0,'box9']) === 'on')) {
                $errors['batch_tracked_increase'][] = $detail;
            }
            $lines[] = $line + ['row'=>$row,'before'=>$snapshot['quantity']];
        }
        foreach ($errors as $reason=>$details) {
            if ($details !== []) {
                $status = in_array($reason, ['not_countable','batch_tracked_increase'], true) ? 422 : 409;
                throw new MobileT2Error($status === 422 ? 'validation_failed' : 'conflict', $status, ['reason'=>$reason,'lines'=>$details]);
            }
        }
        if ($this->afterGuards !== null) {
            ($this->afterGuards)();
        }
        $adjusted = count(array_filter($lines, static fn(array $line): bool => $line['counted_qty'] !== $line['before']));
        $values = array_map([$this->store, 'literal'], [$session['client_id'],$session['session_id'],$this->subject,$session['reason'],$session['note']]);
        $employee = $actor->linked ? (string)(int)$actor->ansatId : 'NULL';
        $started = $session['started_at'] === null ? 'NULL' : $this->store->literal($session['started_at']);
        $terms = [];
        foreach ($lines as $line) {
            $counted = $this->store->literal($line['counted_qty']);
            $before = $this->store->literal($line['before']);
            $cost = $this->store->literal((string)($line['row']['kostpris'] ?? '0'));
            $terms[] = "(($counted::numeric-$before::numeric)*$cost::numeric)";
        }
        $estimate = $this->store->rows('SELECT round(' . implode('+', $terms) . ',2)::text AS value')[0]['value'];
        $estimateLiteral = $this->store->literal($estimate);
        $count = $this->store->rows("INSERT INTO mobile_stock_counts(client_id,session_id,subject,ansat_id,lager,reason_code,note,started_at,submitted_at,line_count,adjusted_count,value_estimate)
            VALUES($values[0],$values[1],$values[2],$employee,$lager,$values[3],$values[4],$started,clock_timestamp()," . count($lines) . ",$adjusted,$estimateLiteral) RETURNING id")[0];
        $countId = (int)$count['id'];
        $targets = $resultLines = [];
        foreach ($lines as $line) {
            $id = $line['vare_id'];
            $cost = stock_quantity((string)($line['row']['kostpris'] ?? '0'));
            $delta = stock_quantity(number_format((float)$line['counted_qty'] - (float)$line['before'], 3, '.', ''));
            $log = 'NULL';
            if ($delta !== '0') {
                $labels = ['optaelling'=>'Optælling','svind'=>'Svind','brud'=>'Brud og kassation','fejlregistrering'=>'Fejlregistrering'];
                $reason = "Optælling (app): {$labels[$session['reason']]} · lager $lager · optælling $countId";
                $log = stock_log_correction($id, $this->context->username, (string)ifset($actor->employee, 'initialer', ''), $reason, $delta);
                $movement = stock_set_quantity($id, 0, $lager, $line['counted_qty'], $cost,
                    (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d'), true);
                $targets[] = $movement['sync'];
            }
            $outcome = $delta === '0' ? 'unchanged' : 'adjusted';
            $fields = array_map([$this->store, 'literal'], [$line['line_ref'],$line['counted_qty'],$line['expected_qty'],$line['expected_token'],
                $line['resolution'],$line['before'],$delta,$cost,$outcome]);
            $at = $line['counted_at'] === null ? 'NULL' : $this->store->literal($line['counted_at']);
            $this->store->execute("INSERT INTO mobile_stock_count_lines(count_id,line_ref,vare_id,lager,counted_qty,expected_qty,expected_token,counted_at,resolution,before_qty,after_qty,delta,kostpris,stocklog_id,outcome)
                VALUES($countId,$fields[0],$id,$lager,$fields[1],$fields[2],$fields[3],$at,$fields[4],$fields[5],$fields[1],$fields[6],$fields[7],$log,$fields[8])");
            $resultLines[] = ['line_ref'=>$line['line_ref'],'vare_id'=>$id,'varenr'=>(string)$line['row']['varenr'],
                'before'=>$line['before'],'counted'=>$line['counted_qty'],'after'=>$line['counted_qty'],'delta'=>$delta,'outcome'=>$outcome];
        }
        $value = $this->store->rows("SELECT round(SUM(delta*kostpris),2)::text AS value FROM mobile_stock_count_lines WHERE count_id=$countId")[0]['value'];
        if ($value !== $estimate) {
            throw new LogicException('Count value was not initialized');
        }
        return [['status'=>201,'headers'=>[],'body'=>['success'=>true,'message'=>'','data'=>[
            'count_id'=>$countId,'lager'=>$lager,'reason'=>$session['reason'],'lines'=>$resultLines,
            'adjusted_count'=>$adjusted,'unchanged_count'=>count($lines)-$adjusted,'value_estimate'=>$value,'shop_sync'=>'partial']]], $targets];
    }

    /** @return array A history page, or one immutable count with its lines when id is supplied. */
    public function history(array $query = []): array
    {
        $paging = new Paging($query);
        $where = '';
        if (array_key_exists('lager', $query)) {
            $where = 'WHERE c.lager=' . $this->warehouse($query['lager']);
        }
        if (array_key_exists('id', $query)) {
            $where = 'WHERE c.id=' . product_catalog_integer($query['id'], 1);
        }
        $pageSql = array_key_exists('id', $query) ? '' : $paging->sql();
        $rows = $this->store->rows("SELECT c.*,to_char(c.submitted_at AT TIME ZONE 'UTC','YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS stamp
            FROM mobile_stock_counts c $where ORDER BY c.submitted_at DESC,c.id DESC $pageSql");
        $names = array_column($this->warehouses()['items'], 'name', 'lager');
        $items = [];
        foreach ($rows as $row) {
            $identity = Subject::parse($row['subject']);
            $user = $identity['principal'] === 'account' ? $this->store->rows('SELECT b.brugernavn,a.navn FROM brugere b LEFT JOIN ansatte a ON a.id=b.ansat_id WHERE b.id=' . (int)$identity['user_id']) : [];
            $items[] = ['count_id'=>(int)$row['id'],'lager'=>(int)$row['lager'],'lager_name'=>(string)ifset($names, (int)$row['lager'], ''),
                'reason'=>$row['reason_code'],'submitted_at'=>$row['stamp'],'by_name'=>(string)(ifset($user, [0,'navn']) ?: ifset($user, [0,'brugernavn'], '')),
                'line_count'=>(int)$row['line_count'],'adjusted_count'=>(int)$row['adjusted_count'],'value_estimate'=>$row['value_estimate']];
        }
        if (array_key_exists('id', $query)) {
            if (!$items) {
                throw new MobileT2Error('not_found', 404);
            }
            $id = $items[0]['count_id'];
            $lines = $this->store->rows("SELECT l.*,v.varenr FROM mobile_stock_count_lines l LEFT JOIN varer v ON v.id=l.vare_id WHERE l.count_id=$id ORDER BY l.vare_id");
            $items[0]['lines'] = array_map(static fn(array $line): array => ['line_ref'=>$line['line_ref'],'vare_id'=>(int)$line['vare_id'],
                'varenr'=>(string)$line['varenr'],'before'=>stock_quantity($line['before_qty']),'counted'=>stock_quantity($line['counted_qty']),
                'after'=>stock_quantity($line['after_qty']),'delta'=>stock_quantity($line['delta']),'outcome'=>$line['outcome']], $lines);
            return $items[0] + ['note'=>$rows[0]['note'],'shop_sync'=>$rows[0]['shop_sync'] === 'pending' ? 'partial' : $rows[0]['shop_sync'],'unchanged_count'=>$items[0]['line_count']-$items[0]['adjusted_count']];
        }
        $total = (int)$this->store->rows("SELECT count(*) AS n FROM mobile_stock_counts c $where")[0]['n'];
        return $paging->response($items, $total);
    }
}
