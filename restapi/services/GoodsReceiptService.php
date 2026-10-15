<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/GoodsReceiptService.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Defer receipt fiscal-year resolution until replay misses and bootstrap reads from a configured year.
// 20261001 CDX/LH Attach delivery notes after stock commit through recoverable pool move intents.
// 20261001 CDX/LH Project recovered delivery notes after a retry persists their pending state.
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.


require_once __DIR__ . '/legacy/LegacyEngine.php';
require_once __DIR__ . '/../core/ETag.php';
require_once __DIR__ . '/../core/Paging.php';
require_once __DIR__ . '/../core/EmployeeContext.php';
require_once __DIR__ . '/../core/Subject.php';
require_once __DIR__ . '/../../includes/kreditor/creditorReceive.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolAttachSource.php';

/** Server-selected receipt identity without a date or fiscal-year dependency. */
final class GoodsReceiptIdentity
{
    public function __construct(
        public readonly \PgSql\Connection $connection,
        public readonly string $db,
        public readonly int $dbId,
        public readonly string $username,
        public readonly int $userId,
        public readonly int $languageId = 0
    ) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $db) || $dbId <= 0 || $userId <= 0 || $username === '') {
            throw new InvalidArgumentException('Invalid receipt identity');
        }
    }
}

/** Purchase receipts use the web engine under the same order and item locks as Saldi. */
final class GoodsReceiptService
{
    private MobileSchema $store;
    private int $readYear;
    private const OPERATION = 'goods_receipt.receive';

    public function __construct(private EngineContext|GoodsReceiptIdentity $context, private string $subject)
    {
        $this->store = new MobileSchema($context->connection);
        $identity = Subject::parse($subject);
        if ($identity['installation'] !== Subject::installation() || $identity['user_id'] !== $context->userId) {
            throw new MobileT2Error('permission_denied', 403);
        }
    }

    /** @return int Canonical positive PostgreSQL id; JSON body ids are integers. */
    public static function id($value, bool $body = false): int
    {
        if (($body && !is_int($value)) || (!is_string($value) && !is_int($value))
            || !preg_match('/^[1-9][0-9]{0,9}$/D', (string)$value) || (int)$value > 2147483647) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'invalid_id']);
        }
        return (int)$value;
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int} Outstanding KO orders only. */
    public function openOrders(array $query = []): array
    {
        $page = new Paging($query);
        return $this->read(function () use ($page): array {
            $where = "o.art='KO' AND o.status IN (1,2) AND EXISTS (SELECT 1 FROM ordrelinjer l
                WHERE l.ordre_id=o.id AND l.posnr>0 AND l.vare_id>0 AND COALESCE(trim(l.varenr),'')<>''
                AND l.antal > COALESCE((SELECT SUM(b.antal) FROM batch_kob b WHERE b.linje_id=l.id),0))";
            $total = (int)$this->store->rows("SELECT count(*) AS n FROM ordrer o WHERE $where")[0]['n'];
            $items = [];
            foreach ($this->store->rows("SELECT o.* FROM ordrer o WHERE $where ORDER BY o.ordredate DESC,o.id DESC " . $page->sql()) as $header) {
                $lines = $this->rawLines((int)$header['id']);
                $item = $this->header($header);
                unset($item['status']);
                $item['lines_open'] = count(array_filter($lines, static fn(array $line): bool =>
                    (int)$line['posnr'] > 0 && (int)$line['vare_id'] > 0 && trim((string)$line['varenr']) !== ''
                    && self::units($line['antal'] ?? '0') > self::units($line['received'])));
                $item['lines_total'] = count(array_filter($lines, static fn(array $line): bool => (int)$line['posnr'] > 0));
                $items[] = $item;
            }
            return $page->response($items, $total);
        });
    }

    /**
     * @return array{
     *   order_id:int,ordrenr:int,kreditor:array{kontonr:string,name:string},order_date:?string,levdate:?string,
     *   status:int,locked_by:?string,etag:string,lines:list<array>,receipts:list<array>
     * } Exact receipt validator, tracking, variants and audit history.
     */
    public function order(int $id): array
    {
        return $this->read(function () use ($id): array {
            $header = $this->rawOrder($id);
            $lines = $this->rawLines($id);
            return $this->header($header) + ['etag'=>$this->etag($header, $lines),
                'lines'=>array_values(array_map(fn(array $line): array => $this->line($line, $header),
                    array_filter($lines, static fn(array $line): bool => (int)$line['posnr'] > 0))),
                'receipts'=>$this->receiptItems($id)];
        });
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int} Immutable receipt lines and attachment state. */
    public function receipts(int $orderId, array $query = []): array
    {
        $page = new Paging($query);
        return $this->read(function () use ($orderId, $page): array {
            $this->rawOrder($orderId);
            $total = (int)$this->store->rows('SELECT count(*) AS n FROM mobile_goods_receipts WHERE ordre_id=' . (int)$orderId)[0]['n'];
            return $page->response($this->receiptItems($orderId, $page), $total);
        });
    }

    /**
     * Receive once, checking replay before current state and validator. Shop synchronization is after COMMIT.
     * @return array{status:int,body:array,headers:array<string,string>}
     */
    public function receive(array $body, ?string $ifMatch): array
    {
        $this->idle();
        $orderId = self::id(ifset($body, 'order_id'), true);
        $clientId = Idempotency::clientId($body);
        $idem = new Idempotency($this->context->connection);
        $this->store->execute('BEGIN');
        try {
            $replay = $idem->replay($this->subject, self::OPERATION, $orderId, $body);
            if ($replay !== null) {
                $this->store->execute('COMMIT');
                $attachment = ifset($replay['body']['data'], 'attachment');
                if ($attachment !== null && in_array($attachment['state'], ['pending','failed'], true)) {
                    $data = $replay['body']['data'];
                    $data['attachment'] = $this->attachAfterCommit((int)$data['receipt_id'], $orderId,
                        (int)$attachment['attachment_id'], $clientId);
                    $data['warnings'] = array_values(array_diff($data['warnings'], ['attachment_failed']));
                    if ($data['attachment']['state'] === 'failed') {
                        $data['warnings'][] = 'attachment_failed';
                    }
                    $replay['body']['data'] = $data;
                    $this->store->execute('BEGIN');
                    $idem->complete($this->subject, self::OPERATION, $orderId, $body, 201, $replay['body']);
                    $this->store->execute('COMMIT');
                }
                return $replay;
            }
            // The audit header's UUID is tenant-unique, whereas replay scope includes the subject.
            $lock = $this->store->literal('saldi:goods-receipt:' . $clientId);
            $this->store->execute("SELECT pg_advisory_xact_lock(hashtextextended($lock,0))");
            if ($this->store->rows('SELECT id FROM mobile_goods_receipts WHERE client_id=' . $this->store->literal($clientId))) {
                throw new MobileT2Error('conflict', 409, ['reason'=>'client_id_reused']);
            }
            $header = $this->rawOrder($orderId, true);
            if ((int)$header['status'] > 2) {
                self::invalid('order_closed');
            }
            $lockedBy = $this->lease($header);
            if ($lockedBy !== null) {
                throw new MobileT2Error('conflict', 409, ['reason'=>'order_locked','locked_by'=>$lockedBy]);
            }
            $rawLines = $this->rawLines($orderId);
            ETag::requireMatch($ifMatch, $this->etag($header, $rawLines));
            $date = ifset($body, 'receipt_date', self::today());
            self::date($date, 'date_missing');
            if ($header['ordredate'] !== null && $date < $header['ordredate']) {
                self::invalid('delivery_date_before_order');
            }
            $context = new EngineContext($this->context->connection, $this->context->db, $this->context->dbId,
                $this->context->username, $this->context->userId, $date, $this->context->languageId);
            $attachmentId = array_key_exists('attachment_id', $body) ? self::id($body['attachment_id'], true) : null;
            if ($attachmentId !== null) {
                $this->attachment($attachmentId);
            }
            $requested = $this->validateLines($body, $rawLines, $header, $context->regnaar);
            $actor = new EmployeeContext($context->connection, Subject::parse($this->subject)['principal'], $context->userId);
            $result = LegacyEngine::run($context, function () use ($requested, $orderId, $date): array {
                $this->store->execute('UPDATE ordrer SET levdate=' . $this->store->literal($date) . ' WHERE id=' . (int)$orderId);
                foreach ($requested as $lineId=>$line) {
                    $this->store->execute('UPDATE ordrelinjer SET leveres=' . $this->store->literal($line['quantity'])
                        . ',batch_batch_no=' . $this->nullable($line['batch_no']) . ',batch_due_date=' . $this->nullable($line['due_date'])
                        . ' WHERE id=' . (int)$lineId . ' AND ordre_id=' . (int)$orderId);
                }
                $received = creditor_receive($orderId, ['defer_shop_sync'=>true]);
                if ($received['reason'] !== null) {
                    $reason = $received['reason'] === 'serial_numbers_missing' ? 'serial_numbers_required' : $received['reason'];
                    self::invalid($reason);
                }
                if (count($received['lines']) !== count($requested)) {
                    throw new RuntimeException('Receipt engine did not consume the requested lines');
                }
                return $received;
            });
            $subject = $this->store->literal($this->subject);
            $client = $this->store->literal($clientId);
            $employee = $actor->linked ? (string)(int)$actor->ansatId : 'NULL';
            $state = $attachmentId === null ? 'none' : 'pending';
            $attachment = $attachmentId === null ? 'NULL' : (string)(int)$attachmentId;
            $receiptId = (int)$this->store->rows("INSERT INTO mobile_goods_receipts
                (client_id,ordre_id,subject,ansat_id,receipt_date,attachment_pool_file_id,attachment_state)
                VALUES($client,$orderId,$subject,$employee," . $this->store->literal($date) . ",$attachment,'$state') RETURNING id")[0]['id'];
            $responseLines = [];
            $after = array_column($this->rawLines($orderId), null, 'id');
            foreach ($result['lines'] as $line) {
                $lineId = (int)$line['linje_id'];
                $request = $requested[$lineId];
                $this->store->execute("INSERT INTO mobile_goods_receipt_lines
                    (receipt_id,ordrelinje_id,vare_id,variant_id,lager,quantity,batch_no,due_date) VALUES("
                    . $receiptId . ',' . $lineId . ',' . (int)$line['vare_id'] . ',' . (int)$line['variant_id'] . ',' . (int)$line['lager']
                    . ',' . $this->store->literal($request['quantity']) . ',' . $this->nullable($request['batch_no']) . ',' . $this->nullable($request['due_date']) . ')');
                $responseLines[] = ['line_id'=>$lineId,'received_now'=>self::decimal($line['received']),
                    'received_total'=>self::decimal($after[$lineId]['received']),
                    'remaining'=>self::fromUnits(max(0, self::units($after[$lineId]['antal']) - self::units($after[$lineId]['received'])))];
            }
            $complete = true;
            foreach ($after as $line) {
                if ((int)$line['posnr'] > 0 && (int)$line['vare_id'] > 0 && trim((string)$line['varenr']) !== ''
                    && self::units($line['antal'] ?? '0') > self::units($line['received'])) {
                    $complete = false;
                }
            }
            $data = ['receipt_id'=>$receiptId,'order_id'=>$orderId,'lines'=>$responseLines,'order_complete'=>$complete,
                'attachment'=>$attachmentId === null ? null : ['state'=>'pending','attachment_id'=>$attachmentId,'reason'=>null],
                'shop_sync'=>'partial','warnings'=>['shop_sync_failed']];
            $envelope = ['success'=>true,'message'=>'','data'=>$data];
            $idem->store($this->subject, self::OPERATION, $orderId, $body, 201, $envelope, 'goods_receipt', $receiptId);
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
        $sync = $this->shopSync($context, $result['sync']);
        $data['warnings'] = [];
        $states = array_column($sync, 'state');
        $data['shop_sync'] = in_array('failed', $states, true) ? 'partial'
            : (in_array('done', $states, true) ? 'done' : 'not_configured');
        if ($data['shop_sync'] === 'partial') {
            $data['warnings'][] = 'shop_sync_failed';
        }
        if ($attachmentId !== null) {
            $data['attachment'] = $this->attachAfterCommit($receiptId, $orderId, $attachmentId, $clientId);
            if ($data['attachment']['state'] === 'failed') {
                $data['warnings'][] = 'attachment_failed';
            }
        }
        $envelope['data'] = $data;
        $this->store->execute('BEGIN');
        try {
            $idem->complete($this->subject, self::OPERATION, $orderId, $body, 201, $envelope);
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
        return ['status'=>201,'body'=>$envelope,'headers'=>[]];
    }

    /**
     * The shop helper derives db_select() log paths from REQUEST_URI as well as cwd.
     * Supply its first-level web-page path only within the legacy call, then restore HTTP context.
     * @param list<array{vare_id:int,variant_id:int,lager:int}> $targets
     * @return list<array{vare_id:int,variant_id:int,lager:int,state:string}>
     */
    private function shopSync(EngineContext $context, array $targets): array
    {
        return LegacyEngine::run($context, static function () use ($targets): array {
            $exists = array_key_exists('REQUEST_URI', $_SERVER);
            $uri = $_SERVER['REQUEST_URI'] ?? null;
            $_SERVER['REQUEST_URI'] = '/saldi/kreditor/modtag.php';
            try {
                return stock_shop_sync($targets);
            } finally {
                if ($exists) {
                    $_SERVER['REQUEST_URI'] = $uri;
                } else {
                    unset($_SERVER['REQUEST_URI']);
                }
            }
        });
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Attach or recover a delivery note independently of stock. */
    public function attach(array $body): array
    {
        $this->idle();
        $orderId = self::id(ifset($body, 'order_id'), true);
        $attachmentId = self::id(ifset($body, 'attachment_id'), true);
        $clientId = Idempotency::clientId($body);
        foreach (array_keys($body) as $key) {
            if (!in_array($key, ['client_id','order_id','attachment_id'], true)) {
                self::invalid('missing_fields');
            }
        }
        return pool_attach_to_source(['connection'=>$this->context->connection, 'db'=>$this->context->db,
            'subject'=>$this->subject, 'user_id'=>$this->context->userId, 'request_id'=>RequestId::current()],
            $attachmentId, 'creditorOrder', $orderId, $clientId);
    }

    /**
     * A separate deterministic operation UUID avoids reusing the stock operation's replay scope.
     * Attachment failures are reported without rolling back the committed stock receipt.
     * @return array{state:string,attachment_id:int,reason:?string}
     */
    private function attachAfterCommit(int $receiptId, int $orderId, int $attachmentId, string $clientId): array
    {
        $hex = hash('sha256', 'saldi:receipt-attachment:' . strtolower($clientId));
        $attachmentClient = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
            . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
        try {
            $response = $this->attach(['client_id'=>$attachmentClient, 'order_id'=>$orderId, 'attachment_id'=>$attachmentId]);
            $attachment = $response['body']['data']['attachment'];
        } catch (Throwable $error) {
            $attachment = ['state'=>'failed', 'attachment_id'=>$attachmentId,
                'reason'=>$error instanceof MobileT2Error ? (string)ifset($error->details, 'reason', $error->error) : 'internal'];
            error_log(json_encode(['event'=>'receipt_attachment_failed', 'receipt_id'=>$receiptId, 'exception'=>get_class($error)]));
        }
        // A committed identity is already linked; T3 recovery can finish its final rename alone.
        $storedState = $attachment['state'] === 'pending' ? 'attached' : $attachment['state'];
        $this->store->execute('UPDATE mobile_goods_receipts SET attachment_state=' . $this->store->literal($storedState)
            . ',attachment_error=' . ($storedState === 'attached' ? 'NULL' : $this->nullable($attachment['reason']))
            . ' WHERE id=' . $receiptId . ' AND subject=' . $this->store->literal($this->subject));
        return $attachment;
    }

    /** Validate an own published, pending delivery note; attachment happens after stock commit. */
    private function attachment(int $id): void
    {
        $rows = $this->store->rows('SELECT * FROM pool_files WHERE id=' . (int)$id . ' AND uploaded_by='
            . $this->store->literal($this->subject) . ' FOR UPDATE');
        if ($rows === []) {
            throw new MobileT2Error('not_found', 404);
        }
        $row = $rows[0];
        if ($row['status'] !== 'ubehandlet' || $row['review_state'] !== 'pending'
            || $this->store->rows('SELECT id FROM pool_file_moves WHERE pool_file_id=' . (int)$id . " AND state IN ('reserved','committed')")) {
            throw new MobileT2Error('conflict', 409, ['reason'=>'attachment_processed']);
        }
    }

    /** @return array<int,array{quantity:string,batch_no:?string,due_date:?string}> */
    private function validateLines(array $body, array $lines, array $header, int $year): array
    {
        $input = ifset($body, 'lines');
        if (!is_array($input) || !array_is_list($input) || $input === [] || count($input) > 1000) {
            self::invalid('missing_fields');
        }
        $available = array_column($lines, null, 'id');
        $requested = [];
        foreach ($input as $raw) {
            $line = is_object($raw) ? get_object_vars($raw) : $raw;
            if (!is_array($line)) {
                self::invalid('missing_fields');
            }
            $id = self::id(ifset($line, 'line_id'), true);
            if (isset($requested[$id]) || !isset($available[$id])) {
                self::invalid('invalid_line');
            }
            $current = $available[$id];
            $view = $this->line($current, $header, $year);
            if (!$view['receivable']) {
                self::invalid($view['why'] === 'serial' ? 'serial_numbers_required' : $view['why']);
            }
            $quantity = ifset($line, 'quantity');
            if (!is_string($quantity) || !preg_match('/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,3})?$/D', $quantity)
                || self::units($quantity) <= 0) {
                self::invalid('quantity_invalid');
            }
            if (self::units($quantity) > self::units($current['antal']) - self::units($current['received'])) {
                self::invalid('over_receipt');
            }
            $batch = ifset($line, 'batch_no');
            $due = ifset($line, 'due_date');
            if (($batch !== null && (!is_string($batch) || strlen($batch) > 100 || str_contains($batch, "\0")))
                || ($view['tracking']['batch_no'] && ($batch === null || trim($batch) === ''))
                || ($view['tracking']['due_date'] && $due === null)) {
                self::invalid('batch_info_missing');
            }
            if ($due !== null) {
                self::date($due, 'batch_info_missing');
            }
            $requested[$id] = ['quantity'=>self::decimal($quantity),'batch_no'=>$batch,'due_date'=>$due];
        }
        foreach ($lines as $line) {
            if (!isset($requested[(int)$line['id']]) && self::units($line['leveres'] ?? '0') !== 0) {
                throw new MobileT2Error('conflict', 409, ['reason'=>'receipt_prepared_in_web']);
            }
        }
        return $requested;
    }

    /** @return array Raw order row, optionally locked against every cooperating web save. */
    private function rawOrder(int $id, bool $lock = false): array
    {
        $rows = $this->store->rows('SELECT * FROM ordrer WHERE id=' . (int)$id . " AND art='KO'" . ($lock ? ' FOR UPDATE' : ''));
        if ($rows === []) {
            throw new MobileT2Error('not_found', 404);
        }
        return $rows[0];
    }

    /** @return list<array> Include every line in validators and prepared-in-web checks. */
    private function rawLines(int $orderId): array
    {
        return $this->store->rows('SELECT l.*,COALESCE((SELECT SUM(b.antal) FROM batch_kob b WHERE b.linje_id=l.id),0)::text AS received
            FROM ordrelinjer l WHERE l.ordre_id=' . (int)$orderId . ' ORDER BY l.posnr,l.id');
    }

    /** @return array{order_id:int,ordrenr:int,kreditor:array{kontonr:string,name:string},order_date:?string,levdate:?string,status:int,locked_by:?string} */
    private function header(array $row): array
    {
        return ['order_id'=>(int)$row['id'],'ordrenr'=>(int)$row['ordrenr'],
            'kreditor'=>['kontonr'=>(string)($row['kontonr'] ?? ''),'name'=>(string)($row['firmanavn'] ?? '')],
            'order_date'=>$row['ordredate'],'levdate'=>$row['levdate'],'status'=>(int)$row['status'],'locked_by'=>$this->lease($row)];
    }

    /** @return string|null Apply the web's one-hour lease rule using the database clock. */
    private function lease(array $row): ?string
    {
        $who = trim((string)($row['hvem'] ?? ''));
        $time = (string)($row['tidspkt'] ?? '0');
        $now = (int)$this->store->rows('SELECT extract(epoch FROM now())::bigint AS n')[0]['n'];
        return (int)$row['status'] < 3 && $who !== '' && $who !== $this->context->username
            && is_numeric($time) && $now - (float)$time < 3600 ? $who : null;
    }

    /**
     * @return array{
     *   line_id:int,posnr:int,vare_id:?int,varenr:string,name:string,unit:string,
     *   variant:array{id:int,label:string,barcode:string}|null,lager:int,lager_name:string,
     *   ordered:string,received:string,remaining:string,barcodes:list<string>,
     *   tracking:array{serial:bool,batch_no:bool,due_date:bool},receivable:bool,why:?string
     * } Group tracking matches creditor_receive() exactly.
     */
    private function line(array $row, array $header, ?int $year = null): array
    {
        $item = $this->store->rows('SELECT * FROM varer WHERE id=' . (int)$row['vare_id'])[0] ?? [];
        $year = $year ?? $this->readYear;
        $group = $this->store->rows("SELECT * FROM grupper WHERE art='VG' AND kodenr=" . (int)($item['gruppe'] ?? 0)
            . ' AND fiscal_year=' . (int)$year . ' ORDER BY id LIMIT 1')[0] ?? [];
        $due = in_array($item['has_due_date'] ?? null, ['t',true,'1',1], true);
        $serial = trim((string)($row['serienr'] ?? '')) !== '' || trim((string)($item['serienr'] ?? '')) !== '';
        $batch = $due || trim((string)($group['box9'] ?? '')) === 'on';
        $why = null;
        if ($serial) {
            $why = 'serial';
        } elseif ((int)$header['status'] > 2) {
            $why = 'order_closed';
        } elseif ((int)$row['posnr'] <= 0 || (int)$row['vare_id'] <= 0 || trim((string)$row['varenr']) === '' || $item === []) {
            $why = 'invalid_line';
        } elseif ($group === [] || (trim((string)($group['box8'] ?? '')) !== 'on' && trim((string)($group['box3'] ?? '')) === '')) {
            $why = 'product_group_missing';
        }
        $variant = null;
        if ((int)$row['variant_id'] > 0) {
            $variantRow = $this->store->rows('SELECT * FROM variant_varer WHERE id=' . (int)$row['variant_id'] . ' AND vare_id=' . (int)$row['vare_id'])[0] ?? null;
            if ($variantRow === null) {
                $why = 'variant_missing';
            } else {
                $variant = ['id'=>(int)$row['variant_id'],'label'=>(string)($variantRow['variant_text'] ?? ''),
                    'barcode'=>(string)($variantRow['variant_stregkode'] ?? '')];
            }
        }
        $lager = (int)$row['lager'] ?: 1;
        $warehouse = $this->store->rows("SELECT beskrivelse FROM grupper WHERE art='LG' AND kodenr=" . (int)$lager
            . ' ORDER BY CASE WHEN fiscal_year=' . (int)$year . ' THEN 0 ELSE 1 END,id DESC LIMIT 1')[0] ?? [];
        $barcodes = [];
        foreach ([$row['varenr'], $item['varenr'] ?? '', $item['varenr_alias'] ?? '', $item['stregkode'] ?? '', $variant['barcode'] ?? ''] as $code) {
            if (trim((string)$code) !== '') {
                $barcodes[] = trim((string)$code);
            }
        }
        return ['line_id'=>(int)$row['id'],'posnr'=>(int)$row['posnr'],'vare_id'=>(int)$row['vare_id'] > 0 ? (int)$row['vare_id'] : null,
            'varenr'=>(string)$row['varenr'],'name'=>(string)($row['beskrivelse'] ?: ($item['beskrivelse'] ?? '')),
            'unit'=>(string)($row['enhed'] ?: ($item['enhed'] ?? '')),'variant'=>$variant,
            'lager'=>$lager,'lager_name'=>(string)($warehouse['beskrivelse'] ?? $lager),
            'ordered'=>self::decimal($row['antal'] ?? '0'),'received'=>self::decimal($row['received']),
            'remaining'=>self::fromUnits(max(0, self::units($row['antal'] ?? '0') - self::units($row['received']))),
            'barcodes'=>array_values(array_unique($barcodes)),
            'tracking'=>['serial'=>$serial,'batch_no'=>$batch,'due_date'=>$due],'receivable'=>$why === null,'why'=>$why];
    }

    /** @return string Strong validator includes receipt/date/batch inputs as well as every line's remaining. */
    private function etag(array $header, array $lines): string
    {
        $fields = array_intersect_key($header, array_flip(['id','art','status','ordredate','levdate','hvem','tidspkt']));
        return '"' . hash('sha256', Idempotency::canonicalJson([$fields, $lines])) . '"';
    }

    /** @return list<array{receipt_id:int,date:string,by_name:string,lines:int,line_items:list<array>,attachment:?array}> */
    private function receiptItems(int $orderId, ?Paging $page = null): array
    {
        $rows = $this->store->rows('SELECT r.*,u.brugernavn FROM mobile_goods_receipts r LEFT JOIN brugere u
            ON r.subject=' . $this->store->literal(Subject::installation() . '/account/') . " || u.id::text
            WHERE r.ordre_id=" . (int)$orderId . ' ORDER BY r.created_at DESC,r.id DESC ' . ($page ? $page->sql() : ''));
        $items = [];
        foreach ($rows as $row) {
            $lines = $this->store->rows('SELECT * FROM mobile_goods_receipt_lines WHERE receipt_id=' . (int)$row['id'] . ' ORDER BY ordrelinje_id');
            $items[] = ['receipt_id'=>(int)$row['id'],'date'=>$row['receipt_date'],'by_name'=>(string)($row['brugernavn'] ?? ''),
                'lines'=>count($lines),'line_items'=>array_map(static fn(array $line): array => ['line_id'=>(int)$line['ordrelinje_id'],
                    'vare_id'=>(int)$line['vare_id'],'variant_id'=>(int)$line['variant_id'],'lager'=>(int)$line['lager'],
                    'quantity'=>self::decimal($line['quantity']),'batch_no'=>$line['batch_no'],'due_date'=>$line['due_date']], $lines),
                'attachment'=>$this->receiptAttachment($row)];
        }
        return $items;
    }

    /** @return array{state:string,attachment_id:int,reason:?string}|null Project the final file move's current state. */
    private function receiptAttachment(array $row): ?array
    {
        if ($row['attachment_pool_file_id'] === null) {
            return null;
        }
        $id = (int)$row['attachment_pool_file_id'];
        $state = $row['attachment_state'];
        $reason = $row['attachment_error'];
        if (in_array($state, ['attached','pending'], true)) {
            $moves = $this->store->rows("SELECT m.state FROM pool_file_moves m JOIN pool_files p ON p.id=m.pool_file_id
                WHERE m.pool_file_id=$id AND m.dest_path=p.storage_path ORDER BY m.id DESC LIMIT 1");
            if ($moves && $moves[0]['state'] === 'done') {
                $state = 'attached';
                $reason = null;
            } elseif ($moves && $moves[0]['state'] === 'committed') {
                $state = 'pending';
                $reason = 'file_move_pending';
            } elseif ($moves && $moves[0]['state'] === 'lost') {
                $state = 'failed';
                $reason = 'attachment_processed';
            }
        }
        return ['state'=>$state, 'attachment_id'=>$id, 'reason'=>$reason];
    }

    /** @return mixed Read related header/line/batch rows from one database snapshot. */
    private function read(callable $operation): mixed
    {
        $this->idle();
        $this->store->execute('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        try {
            $context = $this->context;
            if ($context instanceof GoodsReceiptIdentity) {
                $context = EngineContext::forConfig($context->connection, $context->db, $context->dbId,
                    $context->username, $context->userId, $context->languageId);
            }
            $this->readYear = $context->regnaar;
            $result = LegacyEngine::run($context, $operation);
            $this->store->execute('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }

    private function idle(): void
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Goods receipts require an idle PostgreSQL connection');
        }
    }

    private function nullable(?string $value): string
    {
        return $value === null ? 'NULL' : $this->store->literal($value);
    }

    /** @return string Quantity without loss of decimal precision. */
    private static function decimal(string $value): string
    {
        return str_contains($value, '.') ? (rtrim(rtrim($value, '0'), '.') ?: '0') : $value;
    }

    private static function units(string $value): int
    {
        $sign = str_starts_with($value, '-') ? -1 : 1;
        $parts = explode('.', ltrim($value, '-'), 2);
        return $sign * ((int)$parts[0] * 1000 + (int)str_pad($parts[1] ?? '', 3, '0'));
    }

    private static function fromUnits(int $value): string
    {
        return self::decimal(intdiv($value, 1000) . '.' . str_pad((string)($value % 1000), 3, '0', STR_PAD_LEFT));
    }

    private static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
    }

    private static function date($value, string $reason): void
    {
        $parsed = is_string($value) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            self::invalid($reason);
        }
    }

    private static function invalid(?string $reason): never
    {
        throw new MobileT2Error('validation_failed', 422, ['reason'=>$reason]);
    }
}
