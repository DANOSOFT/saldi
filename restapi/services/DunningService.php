<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/DunningService.php --- ver 5.0.0 --- 2026.10.03
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
// 20261001 CDX/LH Match PDF currency totals, ignore settled series and replay sends before preparation.

require_once __DIR__ . '/DocumentService.php';
require_once __DIR__ . '/../core/Paging.php';
require_once __DIR__ . '/../../includes/dunning/dunningService.php';
require_once __DIR__ . '/../../includes/stdFunc/dunningCutoff.php';

/** Transactional adapter over the same engine used by ny_rykker.php. */
final class DunningService
{
    private EngineContext $context;
    private string $subject;
    private MobileSchema $store;
    private Idempotency $idempotency;

    public function __construct(EngineContext $context, string $subject)
    {
        $this->context = $context;
        $this->subject = $subject;
        $this->store = new MobileSchema($context->connection);
        $this->idempotency = new Idempotency($context->connection);
    }

    /** Preview executes the writes then rolls back both new rows and changes to a base header. @return array{konto_id:int,customer_name:string,level:int,previous_rykker_id:int|null,currency:string,items:array,fee:string,interest:string,total:string,recipient_email:string} */
    public function preview(int $kontoId): array
    {
        $this->begin();
        try {
            return LegacyEngine::run($this->context, function () use ($kontoId): array {
                $created = $this->createRows($kontoId);
                return $this->snapshot($created['rykker_id'], $created['previous_rykker_id']);
            });
        } finally {
            $this->rollback();
        }
    }

    /** Commit creation and the B5 delivery intent atomically before any SMTP effect. @return array{status:int,body:array,headers:array<string,string>} */
    public function create(array $request): array
    {
        $kontoId = self::id(ifset($request, 'konto_id'));
        $this->begin();
        try {
            $replay = $this->idempotency->replay($this->subject, 'dunning.create', $kontoId, $request);
            if ($replay !== null) {
                $this->store->execute('COMMIT');
                return $replay;
            }
            $expected = ifset($request, 'expected_level');
            if (!is_int($expected) || $expected < 1 || $expected > 3) {
                throw new MobileT2Error('validation_failed', 422, ['reason'=>'invalid_level']);
            }
            $channel = ifset($request, 'deliver');
            if (!is_string($channel) || !in_array($channel, ['email','none'], true)) {
                throw new MobileT2Error('validation_failed', 422, ['reason'=>'invalid_channel']);
            }
            $email = ifset($request, 'email');
            if (array_key_exists('email', $request) && !is_string($email)) {
                throw new MobileT2Error('validation_failed', 422, ['reason'=>'invalid_email']);
            }
            $data = LegacyEngine::run($this->context, function () use ($kontoId,$expected): array {
                $created = $this->createRows($kontoId, $expected);
                $snapshot = $this->snapshot($created['rykker_id'], $created['previous_rykker_id']);
                $this->prepareLanguage($created['rykker_id']);
                $row = $this->order($created['rykker_id']);
                return ['rykker_id'=>$created['rykker_id'], 'level'=>$snapshot['level'],
                    'rykker_fakturanr'=>$row['fakturanr'], 'total'=>$snapshot['total'], 'currency'=>$snapshot['currency']];
            });
            return (new DocumentService($this->context, $this->subject))->deliverCreated($data['rykker_id'], $channel,
                $email, $request, 'dunning.create', $kontoId, $data, 201);
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    /** Resends use B5's durable intent and replay state machine. @return array{status:int,body:array,headers:array<string,string>} */
    public function send(array $request): array
    {
        $id = self::id(ifset($request, 'rykker_id'));
        Idempotency::clientId($request);
        $email = ifset($request, 'email');
        if (array_key_exists('email', $request) && !is_string($email)) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'invalid_email']);
        }
        // Keep B5 sendEmail's normalized request so existing send records retain the same hash.
        $request['order_id'] = $id;
        $request['channel'] = 'email';
        if ($email !== null) {
            $request['email'] = $email;
        }
        $this->begin();
        try {
            $replay = $this->idempotency->replay($this->subject, 'document.deliver', $id, $request);
            if ($replay !== null) {
                $this->store->execute('COMMIT');
                return $replay;
            }
            $this->order($id);
            $this->prepareLanguage($id);
            return (new DocumentService($this->context, $this->subject))->deliverCreated($id, 'email', $email,
                $request, 'document.deliver', $id, [], 200);
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    /** @return array{items:array,total:int,limit:int,offset:int} */
    public function history(int $kontoId, array $query = []): array
    {
        $this->customer($kontoId);
        $paging = new Paging($query);
        $count = (int)$this->store->rows("SELECT count(*) AS total FROM ordrer WHERE konto_id=$kontoId AND art IN ('R1','R2','R3')")[0]['total'];
        $items = [];
        foreach ($this->store->rows("SELECT * FROM ordrer WHERE konto_id=$kontoId AND art IN ('R1','R2','R3') ORDER BY id DESC " . $paging->sql()) as $order) {
            $id = (int)$order['id'];
            $snapshot = $this->snapshot($id);
            $deliveries = $this->store->rows("SELECT id,channel,state,recipient,error_reason,
                to_char(created_at AT TIME ZONE 'UTC','YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS at
                FROM document_deliveries WHERE ordre_id=$id ORDER BY id DESC LIMIT 1");
            $delivery = $deliveries[0] ?? null;
            if ($delivery !== null) {
                $delivery['id'] = (int)$delivery['id'];
                if ($delivery['channel'] === 'none') {
                    $delivery['state'] = 'none';
                }
            }
            $items[] = ['rykker_id'=>$id, 'level'=>$snapshot['level'], 'rykker_fakturanr'=>$order['fakturanr'],
                'created_on'=>$order['ordredate'], 'status'=>(int)$order['status'], 'total'=>$snapshot['total'],
                'currency'=>$snapshot['currency'], 'delivery'=>$delivery];
        }
        return $paging->response($items,$count);
    }

    /** Runs inside LegacyEngine and after taking the shared web/mobile lock, before eligibility reads. @return array{rykker_id:int,previous_rykker_id:int|null} */
    private function createRows(int $kontoId, ?int $expected = null): array
    {
        dunning_lock();
        $this->customer($kontoId);
        $date = (new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
        $latest = $this->store->rows("SELECT o.* FROM ordrer o WHERE o.konto_id=$kontoId AND o.art IN ('R1','R2','R3')
            AND COALESCE(o.betalt,'')!='on' AND EXISTS (
                SELECT 1 FROM ordrelinjer l JOIN openpost p ON l.enhed=p.id::text
                WHERE l.ordre_id=o.id AND COALESCE(l.vare_id,0)=0 AND COALESCE(p.udlignet,'') IN ('','0')
            ) ORDER BY o.art DESC,o.id DESC LIMIT 1");
        $base = $latest[0] ?? null;
        $level = $base === null ? 1 : (int)substr($base['art'],1) + 1;
        if ($level > 3) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'top_level_reached']);
        }
        if ($expected !== null && $expected !== $level) {
            throw new MobileT2Error('conflict',409,['reason'=>'level_changed','level'=>$level]);
        }
        $configuration = dunning_configuration($level);
        if ($configuration['fee'] === null) {
            throw new MobileT2Error('not_configured',422,['what'=>'dunning_fee']);
        }
        if ($base !== null) {
            $grace = $level === 2 ? $configuration['days2'] : $configuration['days3'];
            $cutoff = dunning_cutoff_date($grace,new DateTimeImmutable($date,new DateTimeZone('Europe/Copenhagen')));
            // A just-created rykker cannot be immediately advanced by a concurrent second submission.
            if ($base['ordredate'] >= $cutoff) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'nothing_to_dun']);
            }
            $baseId = (int)$base['id'];
            $result = dunning_advance($baseId,$date);
        } else {
            $baseId = null;
            $result = dunning_create_for_account($kontoId,$date);
        }
        if ($result['skipped'] !== null) {
            $code = $result['skipped'] === 'level_changed' ? 'conflict' : 'validation_failed';
            $status = $code === 'conflict' ? 409 : 422;
            throw new MobileT2Error($code,$status,['reason'=>$result['skipped']]);
        }
        return ['rykker_id'=>$result['rykker_id'],'previous_rykker_id'=>$baseId];
    }

    /** Read the persisted comment references and price lines, as rykkerprint() does. @return array{konto_id:int,customer_name:string,level:int,previous_rykker_id:int|null,currency:string,items:array,fee:string,interest:string,total:string,recipient_email:string} */
    private function snapshot(int $id, ?int $previous = null): array
    {
        $order = $this->order($id);
        $currency = trim((string)$order['valuta']) ?: 'DKK';
        // rykkerprint looks up the latest rate at the reminder date, independently of the stored R1 rate.
        $rate = 100;
        if ($currency !== 'DKK') {
            $currencySql = $this->store->literal($currency);
            $dateSql = $this->store->literal($order['ordredate']);
            $rates = $this->store->rows("SELECT v.kurs FROM valuta v JOIN grupper g ON v.gruppe=g.kodenr::integer
                WHERE g.art='VK' AND g.box1=$currencySql AND v.valdate<=$dateSql ORDER BY v.valdate DESC LIMIT 1");
            $rate = $rates ? (float)$rates[0]['kurs'] : 100;
        }
        $level = (int)substr($order['art'],1);
        $items = [];
        $fee = $interest = $total = 0;
        $form = $level + 5;
        $feeRows = $this->store->rows("SELECT xb FROM formularer WHERE beskrivelse='GEBYR' AND formular=$form");
        $feeIds = array_map('intval',array_column($feeRows,'xb'));
        foreach ($this->store->rows("SELECT l.*,p.id AS post_id,p.faktnr,p.forfaldsdate,p.transdate,p.amount,p.valuta,p.valutakurs
            FROM ordrelinjer l LEFT JOIN openpost p ON p.id::text=l.enhed AND COALESCE(l.vare_id,0)=0
            WHERE l.ordre_id=$id ORDER BY l.posnr,l.id") as $line) {
            if ($line['post_id'] !== null) {
                $postCurrency = trim((string)$line['valuta']) ?: 'DKK';
                $postRate = (float)$line['valutakurs'] ?: 100;
                $dkkAmount = (float)$line['amount'] * $postRate / 100;
                $amount = $currency === 'DKK' ? $dkkAmount
                    : ($postCurrency === $currency ? (float)$line['amount'] : $dkkAmount * 100 / $rate);
                $amount = (float)afrund($amount,2);
                $items[] = ['openpost_id'=>(int)$line['post_id'],'faktnr'=>(string)$line['faktnr'],
                    'due_date'=>$line['forfaldsdate'] ?: $line['transdate'],'amount'=>self::money($amount)];
            } else {
                $amount = (float)afrund((float)$line['pris'],2);
                if ((int)$line['vare_id'] > 0 && in_array((int)$line['vare_id'],$feeIds,true)) {
                    $fee += $amount;
                } elseif ((int)$line['vare_id'] > 0) {
                    $interest += $amount;
                }
            }
            $total += $amount;
        }
        return ['konto_id'=>(int)$order['konto_id'],'customer_name'=>(string)$order['firmanavn'],'level'=>$level,
            'previous_rykker_id'=>$previous,'currency'=>$currency,'items'=>$items,'fee'=>self::money($fee),
            'interest'=>self::money($interest),'total'=>self::money($total),'recipient_email'=>(string)$order['email']];
    }

    /** @return array<string,mixed> */
    private function order(int $id): array
    {
        $rows = $this->store->rows("SELECT * FROM ordrer WHERE id=$id AND art IN ('R1','R2','R3')");
        if (!$rows) {
            throw new MobileT2Error('not_found',404);
        }
        return $rows[0];
    }

    private function customer(int $id): void
    {
        if (!$this->store->rows("SELECT id FROM adresser WHERE id=$id AND art='D'")) {
            throw new MobileT2Error('not_found',404);
        }
    }

    /** Select the same default form language the web rykker card supplies before sending. */
    private function prepareLanguage(int $id): void
    {
        $this->store->execute("UPDATE ordrer o SET sprog=COALESCE(NULLIF(trim(g.box4),''),'dansk')
            FROM adresser a LEFT JOIN grupper g ON g.art='DG' AND g.kodenr::text=a.gruppe::text
            WHERE o.id=$id AND a.id=o.konto_id AND COALESCE(trim(o.sprog),'')=''");
    }

    private function begin(): void
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Dunning service owns its transaction');
        }
        $this->store->execute('BEGIN');
    }

    private function rollback(): void
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            $this->store->execute('ROLLBACK');
        }
    }

    private static function money(float $amount): string
    {
        return number_format(round($amount,2),2,'.','');
    }

    public static function id($value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D',(string)$value)
            || strlen((string)$value) > 10 || (int)$value > 2147483647) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_id']);
        }
        return (int)$value;
    }
}
