<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/MobileEventScanners.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Retain unfinished webshop orders and consumed overdue keys, and scan one stable overdue snapshot.

require_once __DIR__ . '/MobileEventOutbox.php';
require_once __DIR__ . '/ReminderService.php';
require_once __DIR__ . '/../../includes/openPostsService.php';
require_once __DIR__ . '/../../includes/finans/bankLines.php';
require_once __DIR__ . '/../../includes/lager/stockWarning.php';

/** Ledger scanners with tenant-local state; never changes ledger or stored bank workflow state. */
final class MobileEventScanners
{
    private const SCANNERS = ['invoice_paid','invoice_overdue','webshop_order','low_stock','bank_reminders'];
    private $clock;
    private ReminderService $reminders;

    /**
     * @param callable():DateTimeImmutable|null $clock Optional deterministic clock for scanner-window tests.
     * @param OpenPostsReader|null $overdueReader Optional tenant-bound reader for deterministic concurrent-ledger tests.
     */
    public function __construct(private $connection, $registryConnection, private int $tenantId, ?callable $clock = null,
        private ?OpenPostsReader $overdueReader = null)
    {
        $this->reminders = new ReminderService($connection, $registryConnection, $tenantId);
        $this->clock = $clock;
    }

    /**
     * Owns one transaction for all scanner states, pairings, events and reminder resolutions.
     * An overlapping run returns busy; an error rolls back everything and remains retryable.
     * @return array{busy:bool,seeded:int,invoice_paid:int,invoice_overdue:int,invoice_overdue_digest:int,webshop_order:int,low_stock:int,resolved:int}
     */
    public function run(): array
    {
        if (!$this->connection instanceof \PgSql\Connection || pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Scanners require an idle PostgreSQL tenant connection');
        }
        $stats = ['busy'=>false,'seeded'=>0,'invoice_paid'=>0,'invoice_overdue'=>0,
            'invoice_overdue_digest'=>0,'webshop_order'=>0,'low_stock'=>0,'resolved'=>0];
        $this->query('BEGIN');
        try {
            $locked = $this->query("SELECT pg_try_advisory_xact_lock(hashtext(current_database()),hashtext('mobile_scanners_worker')) AS locked")[0]['locked'];
            if ($locked !== 't') {
                $this->query('ROLLBACK');
                $stats['busy'] = true;
                return $stats;
            }
            $databaseNow = $this->query('SELECT now() AS time')[0]['time'];
            $now = ($this->clock ? ($this->clock)() : new DateTimeImmutable($databaseNow))->setTimezone(new DateTimeZone('Europe/Copenhagen'));
            foreach (self::SCANNERS as $scanner) {
                $state = $this->query('SELECT cursor FROM mobile_scanner_state WHERE scanner=$1 FOR UPDATE', [$scanner]);
                $seed = $state === [];
                $cursor = $seed ? [] : json_decode($state[0]['cursor'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($cursor)) {
                    throw new UnexpectedValueException('Invalid scanner state');
                }
                switch ($scanner) {
                    case 'invoice_paid':
                        $cursor = $this->paid($cursor, $seed, $now, $stats);
                        break;
                    case 'invoice_overdue':
                        $cursor = $this->overdue($cursor, $seed, $now, $stats);
                        break;
                    case 'webshop_order':
                        $cursor = $this->webshop($cursor, $seed, $now, $stats);
                        break;
                    case 'low_stock':
                        $this->stock($seed, $stats);
                        break;
                    case 'bank_reminders':
                        if (!$seed) {
                            $stats['resolved'] = $this->resolveBankReminders();
                        }
                        break;
                }
                $this->query('INSERT INTO mobile_scanner_state(scanner,seeded_at,last_run_at,cursor) VALUES($1,$2,$2,$3)
                    ON CONFLICT(scanner) DO UPDATE SET last_run_at=EXCLUDED.last_run_at,cursor=EXCLUDED.cursor',
                    [$scanner,$now->format(DATE_ATOM),json_encode($cursor, JSON_THROW_ON_ERROR)]);
                $stats['seeded'] += $seed ? 1 : 0;
            }
            $this->query('COMMIT');
            return $stats;
        } catch (Throwable $error) {
            $this->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return list<array<string,mixed>> Parameterized tenant queries with checked server results. */
    private function query(string $sql, array $params = []): array
    {
        if (!pg_send_query_params($this->connection, $sql, $params)) {
            throw new RuntimeException('Scanner query could not be sent');
        }
        $result = pg_get_result($this->connection);
        while (pg_get_result($this->connection) !== false) {
        }
        if (!$result || !in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true)) {
            throw new RuntimeException('Scanner database command failed');
        }
        return pg_num_fields($result) ? pg_fetch_all($result) : [];
    }

    /**
     * Keep one anchor at least an hour old, plus newer observations. Never jump to this run's max.
     * A lower sequence/id allocated by a transaction that commits later remains inside the overlap.
     * @return array{0:int,1:array{floor:int,samples:list<array{at:int,max:int}>}}
     */
    private function window(array $cursor, int $maximum, bool $seed, DateTimeImmutable $now): array
    {
        $floor = $seed ? $maximum : (int)$cursor['floor'];
        $recent = [];
        foreach (if_array($cursor, 'samples') as $sample) {
            if ((int)$sample['at'] <= $now->getTimestamp() - 3600) {
                $floor = max($floor, (int)$sample['max']);
            } else {
                $recent[] = $sample;
            }
        }
        $recent[] = ['at'=>$now->getTimestamp(),'max'=>$maximum];
        return [$floor, ['floor'=>$floor,'samples'=>$recent]];
    }

    /** @return array<string,mixed> Separate group and POS watermarks, plus POS payment-to-invoice claims. */
    private function paid(array $cursor, bool $seed, DateTimeImmutable $now, array &$stats): array
    {
        // Read the existing settlement watermark without using the legacy MAX-id allocator shape.
        $max = $this->query("SELECT COALESCE((SELECT udlign_id FROM openpost
                WHERE udlign_id IS NOT NULL ORDER BY udlign_id DESC LIMIT 1),0) AS grouped,
            COALESCE(max(id) FILTER (WHERE kladde_id=0 AND amount<0 AND beskrivelse LIKE 'Indbetaling konto: %'),0) AS pos FROM openpost")[0];
        [$groupFloor, $groupWindow] = $this->window(if_array($cursor, 'grouped'), (int)$max['grouped'], $seed, $now);
        [$posFloor, $posWindow] = $this->window(if_array($cursor, 'pos'), (int)$max['pos'], $seed, $now);
        $pairs = array_filter(if_array($cursor, 'pos_pairs'), static fn($payment): bool => (int)$payment > $posFloor, ARRAY_FILTER_USE_KEY);
        // Historical ungrouped invoices have no outbox key after a quiet seed. Remember them
        // separately so their lower ids cannot take a later POS payment's invoice match.
        $seededInvoices = $seed ? array_map('intval', array_column($this->query("SELECT i.id FROM openpost i
            JOIN ordrer o ON o.id=i.refnr AND o.art='DO' AND o.konto_id=i.konto_id AND trim(o.fakturanr)=trim(i.faktnr)
            WHERE i.kladde_id=0 AND i.amount>0 AND i.udlignet='1' AND COALESCE(i.udlign_id,0)=0 ORDER BY i.id"), 'id'))
            : if_array($cursor, 'seeded_pos_invoices');
        if (!$seed) {
            $settings = $this->query("SELECT var_value FROM settings WHERE var_name='baseCurrency' ORDER BY id LIMIT 1");
            $currency = trim((string)ifset($settings, [0, 'var_value'], '')) ?: 'DKK';
            $invoices = $this->query("SELECT i.*,o.firmanavn AS customer_name FROM openpost i
                JOIN ordrer o ON o.id=i.refnr AND o.art='DO' AND o.konto_id=i.konto_id AND trim(o.fakturanr)=trim(i.faktnr)
                WHERE i.kladde_id=0 AND i.amount>0 AND i.udlignet='1' AND i.udlign_id>$1 AND i.udlign_id<=$2
                AND EXISTS (SELECT 1 FROM openpost p WHERE p.udlign_id=i.udlign_id AND p.konto_id=i.konto_id
                    AND p.amount<0 AND p.udlignet='1' AND (p.kladde_id>1 OR EXISTS
                        (SELECT 1 FROM payment_registrations pr WHERE pr.openpost_id=i.id AND pr.konto_id=i.konto_id AND pr.aligned=true AND pr.amount>0)))
                ORDER BY i.id", [$groupFloor,(int)$max['grouped']]);
            foreach ($invoices as $invoice) {
                $stats['invoice_paid'] += $this->emitInvoice($invoice);
            }
            $payments = $this->query("SELECT p.* FROM openpost p JOIN ordrer o ON o.id=p.refnr
                WHERE p.id>$1 AND p.id<=$2 AND p.kladde_id=0 AND p.amount<0 AND p.udlignet='1'
                    AND p.beskrivelse LIKE 'Indbetaling konto: %' ORDER BY p.id", [$posFloor,(int)$max['pos']]);
            // Payments still visible in the overlap cannot be consumed twice, even if another equal invoice settles.
            foreach ($payments as $payment) {
                $paymentId = (int)$payment['id'];
                if (array_key_exists($paymentId, $pairs)) {
                    continue;
                }
                $claimed = '{' . implode(',', array_map('intval', array_merge($seededInvoices, array_values($pairs)))) . '}';
                // Ungrouped, unnotified invoices are the native POS shape. A recent grouped/link
                // event is only an overlap fallback; it must not consume a different POS invoice.
                $candidates = $this->query("SELECT i.*,o.firmanavn AS customer_name,e.id AS event_id FROM openpost i
                    JOIN ordrer o ON o.id=i.refnr AND o.art='DO' AND o.konto_id=i.konto_id AND trim(o.fakturanr)=trim(i.faktnr)
                    LEFT JOIN mobile_events e ON e.dedupe_key='invoice_paid:'||i.id
                    WHERE i.kladde_id=0 AND i.amount=-$1::numeric AND i.konto_id=$2 AND i.udlignet='1' AND i.refnr<>$3
                        AND COALESCE(NULLIF(i.valuta,''),$6)=COALESCE(NULLIF($4,''),$6) AND NOT i.id=ANY($5::integer[])
                    ORDER BY CASE WHEN e.id IS NULL AND COALESCE(i.udlign_id,0)=0 THEN 0
                        WHEN e.id IS NOT NULL AND COALESCE(i.udlign_id,0)>$7 THEN 1
                        WHEN e.id IS NULL THEN 2 ELSE 3 END,i.id LIMIT 1", [$payment['amount'],(int)$payment['konto_id'],
                        (int)$payment['refnr'],$payment['valuta'],$claimed,$currency,$groupFloor]);
                if ($candidates !== []) {
                    $invoice = $candidates[0];
                    $pairs[$paymentId] = (int)$invoice['id'];
                    $stats['invoice_paid'] += $this->emitInvoice($invoice, $paymentId);
                }
            }
        }
        return ['grouped'=>$groupWindow,'pos'=>$posWindow,'pos_pairs'=>$pairs,'seeded_pos_invoices'=>$seededInvoices];
    }

    /** @return int One new event, or zero when this invoice is already in the shared outbox. */
    private function emitInvoice(array $invoice, ?int $posPaymentId = null): int
    {
        $payload = ['faktnr'=>trim($invoice['faktnr']),'amount'=>OpenPostsReader::money((float)$invoice['amount']),
            'customer_name'=>(string)$invoice['customer_name']];
        if ($posPaymentId !== null) {
            $payload['pos_payment_id'] = $posPaymentId;
        }
        return (int)MobileEventOutbox::emit($this->connection,'invoice_paid','invoice_paid:' . (int)$invoice['id'],
            'openpost',(int)$invoice['id'],$payload,null);
    }

    /** @return array{day:string|null,seen:list<string>} Consumed invoice keys survive settlement, reversal and digest delivery. */
    private function overdue(array $cursor, bool $seed, DateTimeImmutable $now, array &$stats): array
    {
        $day = $now->format('Y-m-d');
        if (!$seed && ($now->format('H:i') < '07:00' || ifset($cursor, 'day') === $day)) {
            return $cursor;
        }
        $eligible = [];
        $reader = $this->overdueReader ?? openposts_reader($this->connection);
        // items() rebuilds its ledger groups on every call. Read them once, then page the
        // resulting snapshot so a concurrent settlement cannot shift an unread offset.
        $snapshot = $reader->items(['status'=>'overdue','sort'=>'id'], PHP_INT_MAX, 0);
        foreach (array_chunk($snapshot['items'], 100) as $page) {
            foreach ($page as $item) {
                if ($item['type'] === 'invoice' && (float)$item['rest']>0 && $item['days_overdue']>=1 && $item['days_overdue']<=3) {
                    $key = 'invoice_overdue:' . (int)$item['konto_id'] . ':' . $item['faktnr'];
                    $eligible[$key] = $item;
                }
            }
        }
        $seen = array_fill_keys(if_array($cursor, 'seen'), true);
        $fresh = array_diff_key($eligible, $seen);
        if (!$seed) {
            foreach ($fresh as $key => $item) {
                if ($this->query('SELECT 1 FROM mobile_events WHERE dedupe_key=$1', [$key]) !== []) {
                    unset($fresh[$key]);
                }
            }
            if (count($fresh)>10) {
                $stats['invoice_overdue_digest'] = (int)MobileEventOutbox::emit($this->connection,'invoice_overdue_digest',
                    'invoice_overdue_digest:' . $day,null,null,['count'=>count($fresh)],null);
            } else {
                foreach ($fresh as $key => $item) {
                    $stats['invoice_overdue'] += (int)MobileEventOutbox::emit($this->connection,'invoice_overdue',$key,
                        'openpost',(int)$item['id'],['faktnr'=>$item['faktnr'],'amount'=>$item['rest'],
                            'customer_name'=>$item['customer_name']],null);
                }
            }
        }
        return ['day'=>$now->format('H:i') >= '07:00' ? $day : null,'seen'=>array_keys($seen + $eligible)];
    }

    /** @return array{floor:int,samples:list<array{at:int,max:int}>,pending:list<int>} Unfinished totals remain candidates below the order-id watermark. */
    private function webshop(array $cursor, bool $seed, DateTimeImmutable $now, array &$stats): array
    {
        $max = (int)$this->query('SELECT COALESCE(max(id),0) AS id FROM ordrer')[0]['id'];
        [$floor, $window] = $this->window($cursor, $max, $seed, $now);
        $pending = [];
        if (!$seed) {
            $pendingIds = '{' . implode(',', array_map('intval', if_array($cursor, 'pending'))) . '}';
            foreach ($this->query("SELECT id,ordrenr,firmanavn,sum,moms,sum+moms AS amount FROM ordrer o
                WHERE ((id>$1 AND id<=$2) OR id=ANY($3::integer[])) AND art='DO'
                    AND (shop_id>0 OR EXISTS (SELECT 1 FROM shop_ordrer s WHERE s.saldi_id=o.id))
                ORDER BY id", [$floor,$max,$pendingIds]) as $order) {
                // hent_ordrer commits its mapping before writing totals. NULL means
                // unfinished; explicit zero totals are complete and may be delivered.
                if ($order['sum'] === null || $order['moms'] === null) {
                    $pending[] = (int)$order['id'];
                    continue;
                }
                $stats['webshop_order'] += (int)MobileEventOutbox::emit($this->connection,'webshop_order',
                    'webshop_order:' . (int)$order['id'],'ordre',(int)$order['id'],['ordrenr'=>(string)$order['ordrenr'],
                        'amount'=>OpenPostsReader::money((float)$order['amount']),'customer_name'=>(string)$order['firmanavn']],null);
            }
        }
        $window['pending'] = $pending;
        return $window;
    }

    /** @return void Seed existing/new items quietly; record recovery and each later crossing atomically. */
    private function stock(bool $seed, array &$stats): void
    {
        $setting = $this->query("SELECT var_value FROM settings WHERE var_name='min_beholdning' AND var_grp='productOptions' LIMIT 1");
        $value = trim((string)ifset($setting, [0, 'var_value'], ''));
        $standard = is_numeric($value) ? (float)$value : 0.0;
        $items = $this->query("SELECT v.id,v.varenr,v.beskrivelse,v.enhed,v.min_lager,
            COALESCE(l.qty,v.beholdning,0) AS stock,s.below,s.crossings,s.vare_id AS known,
            EXISTS (SELECT 1 FROM grupper g WHERE g.art='VG' AND g.box8='on' AND g.kodenr=v.gruppe) AS tracked
            FROM varer v LEFT JOIN (SELECT vare_id,sum(beholdning) AS qty FROM lagerstatus GROUP BY vare_id) l ON l.vare_id=v.id
            LEFT JOIN mobile_low_stock_state s ON s.vare_id=v.id
            WHERE s.vare_id IS NOT NULL OR EXISTS (SELECT 1 FROM grupper g WHERE g.art='VG' AND g.box8='on' AND g.kodenr=v.gruppe)
            ORDER BY v.id");
        foreach ($items as $item) {
            $below = stock_warning_evaluate($item,(float)$item['stock'],$standard,$item['tracked'] === 't');
            $crossing = !$seed && $item['known'] !== null && $item['below'] === 'f' && $below;
            $crossings = (int)$item['crossings'] + (int)$crossing;
            $this->query('INSERT INTO mobile_low_stock_state(vare_id,below,crossings) VALUES($1,$2,$3)
                ON CONFLICT(vare_id) DO UPDATE SET below=EXCLUDED.below,crossings=EXCLUDED.crossings,
                changed_at=CASE WHEN mobile_low_stock_state.below<>EXCLUDED.below THEN now() ELSE mobile_low_stock_state.changed_at END',
                [(int)$item['id'],$below ? 'true' : 'false',$crossings]);
            if ($crossing) {
                $stats['low_stock'] += (int)MobileEventOutbox::emit($this->connection,'low_stock',
                    'low_stock:' . (int)$item['id'] . ':' . $crossings,'vare',(int)$item['id'],['varenr'=>(string)$item['varenr'],
                        'name'=>(string)$item['beskrivelse'],'stock'=>(string)$item['stock'],'unit'=>(string)$item['enhed'],
                        'min'=>(string)stock_warning_minimum($item,$standard)],null);
            }
        }
    }

    /** @return int Recipients closed after a web match; ignored, received and ordinary reminders stay open. */
    private function resolveBankReminders(): int
    {
        $state = bank_line_state_sql();
        return count($this->query("UPDATE notification_recipients nr SET resolved_at=now() FROM notifications n,reminders r,bank_lines b
            WHERE nr.notification_id=n.id AND n.reminder_id=r.id AND r.bank_line_id=b.id AND n.type='reminder'
                AND nr.resolved_at IS NULL AND ($state)='matched' RETURNING nr.notification_id"));
    }
}
