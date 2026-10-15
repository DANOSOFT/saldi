<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/MobileEventFanout.php --- ver 5.0.0 --- 2026.10.08
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
// 20261001 CDX/LH Allow sellers to manage settlement notifications independently of broadcast rights.

// 20261001 CDX/LH Add booking recipients, preferences and Danish inbox texts.
// 20261008 CL/LH Deliver Assist interrupts once with current item visibility and the assistant_interrupt switch.
require_once __DIR__ . '/../core/EmployeeContext.php';
require_once __DIR__ . '/ReminderService.php';
require_once __DIR__ . '/../core/Rights.php';
require_once __DIR__ . '/../core/Idempotency.php';
require_once __DIR__ . '/../../includes/runtime/bootstrap.php';
require_once __DIR__ . '/../../includes/automation/SaldiInboxService.php';

/** Tenant event delivery and the recipient's own switches, using current rights. */
final class MobileEventFanout
{
    private MobileSchema $store;
    private MobileSchema $registry;
    private ReminderService $reminders;
    private const TYPES = [
        'attachment_decided'=>['bilag','own'],
        'attachment_resubmitted'=>['kassekladde','write'],
        'invoice_paid'=>['debitorrapport','read'],
        'invoice_overdue'=>['debitorrapport','read'],
        'invoice_overdue_digest'=>['debitorrapport','read'],
        'webshop_order'=>['debitorordre','read'],
        'low_stock'=>['varer','read'],
        'booking_assigned'=>['employee','own'],
        'inbox_interrupt'=>['assistant','read'],
    ];

    public function __construct(private $connection, $registryConnection, private int $tenantId)
    {
        $this->store = new MobileSchema($connection);
        $this->registry = new MobileSchema($registryConnection);
        $this->reminders = new ReminderService($connection, $registryConnection, $tenantId);
    }

    /**
     * At most 200 events, each claimed and committed with its entire inbox in one transaction.
     * A failed event remains retryable; other events still run. Overlaps skip locked rows.
     * @return array{fanned_out:int,notifications:int,recipients:int,failed:int,pruned:int}
     */
    public function run(int $limit = 200): array
    {
        $this->requireTransaction(false);
        $stats = ['fanned_out'=>0,'notifications'=>0,'recipients'=>0,'failed'=>0,'pruned'=>0];
        $this->store->execute('BEGIN');
        try {
            $deleted = $this->store->rows("DELETE FROM mobile_events WHERE id IN (
                SELECT id FROM mobile_events WHERE created_at<now()-interval '12 months'
                ORDER BY created_at,id LIMIT 100 FOR UPDATE SKIP LOCKED) RETURNING id");
            $stats['pruned'] = count($deleted);
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            error_log(json_encode(['tenant_id'=>$this->tenantId,'error'=>'event_cleanup_failed','exception'=>get_class($error)]));
        }
        $cursor = 0;
        for ($i = 0; $i < max(1, min(200, $limit)); $i++) {
            $this->store->execute('BEGIN');
            $event = null;
            try {
                $rows = $this->store->rows("SELECT * FROM mobile_events WHERE fanned_out_at IS NULL AND id>$cursor
                    AND created_at>=now()-interval '12 months'
                    ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED");
                if ($rows === []) {
                    $this->store->execute('COMMIT');
                    break;
                }
                $event = $rows[0];
                $cursor = (int)$event['id'];
                $notification = $this->deliver($event);
                $this->store->execute('COMMIT');
                $stats['fanned_out']++;
                $stats['notifications'] += $notification['notification_id'] === null ? 0 : 1;
                $stats['recipients'] += $notification['recipient_count'];
            } catch (Throwable $error) {
                $this->store->execute('ROLLBACK');
                if ($event === null) {
                    throw $error;
                }
                $stats['failed']++;
                $this->store->execute("UPDATE mobile_events SET attempts=attempts+1,last_error='fanout_failed'
                    WHERE id=$cursor AND fanned_out_at IS NULL");
                error_log(json_encode(['tenant_id'=>$this->tenantId,'event_id'=>$cursor,'error'=>'fanout_failed','exception'=>get_class($error)]));
            }
        }
        return $stats;
    }

    /** Deliver only the newly committed Assist event; its row lock makes cron overlap idempotent. */
    public function interruptAfterCommit(int $itemId): void
    {
        $this->requireTransaction(false);
        $this->store->execute('BEGIN');
        try {
            $key = $this->store->literal('assist:interrupt:' . $itemId);
            $events = $this->store->rows("SELECT * FROM mobile_events WHERE type='inbox_interrupt'
                AND dedupe_key=$key AND fanned_out_at IS NULL FOR UPDATE");
            if ($events !== []) {
                $this->deliver($events[0]);
            }
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }

    /**
     * T2 keeps its immediate settlement inbox and explicit creator snapshot. Lock/mark the
     * outbox row inside the booking transaction, so a cron cannot duplicate the notification.
     * Only the settlement service supplies this server-derived list; never accept client subjects.
     * @param list<string> $creatorSubjects Existing T2 link and app-order creators.
     * @return void
     */
    public function settlementInTransaction(string $dedupeKey, array $creatorSubjects): void
    {
        $this->requireTransaction(true);
        $key = $this->store->literal($dedupeKey);
        $rows = $this->store->rows("SELECT * FROM mobile_events WHERE dedupe_key=$key FOR UPDATE");
        if ($rows !== [] && $rows[0]['fanned_out_at'] === null) {
            $this->deliver($rows[0], $creatorSubjects);
        }
    }

    /** @return array{notification_id:?int,recipient_count:int} */
    private function deliver(array $event, array $creatorSubjects = []): array
    {
        $type = (string)$event['type'];
        if (!isset(self::TYPES[$type])) {
            throw new UnexpectedValueException('Unknown event type');
        }
        $payload = json_decode($event['payload'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || array_is_list($payload) && $payload !== []) {
            throw new UnexpectedValueException('Event payload must be an object');
        }
        [$title, $message] = self::render($type, $payload);
        if (str_starts_with($type, 'attachment_') && $event['target_subject'] === null) {
            throw new UnexpectedValueException('Attachment event needs a target');
        }
        if ($type === 'booking_assigned' && (!is_int(ifset($payload, 'employee_ansat_id'))
            || $payload['employee_ansat_id'] < 1 || !is_array(ifset($payload, 'recipient_subjects')))) {
            throw new UnexpectedValueException('Booking event needs assigned recipients');
        }
        $subjects = [];
        foreach ($this->reminders->appUsers() as $user) {
            if ($type === 'booking_assigned') {
                $identity = Subject::parse($user['subject']);
                $employee = new EmployeeContext($this->connection, $identity['principal'], $identity['user_id']);
                if (!$employee->linked || $employee->ansatId !== $payload['employee_ansat_id']
                    || $user['subject'] === ifset($payload, 'actor')
                    || !in_array($user['subject'], $payload['recipient_subjects'], true)) {
                    continue;
                }
            }
            if ($event['target_subject'] !== null && $user['subject'] !== $event['target_subject']) {
                continue;
            }
            if ($type === 'inbox_interrupt' && !$this->interruptAllowed($user, $event)) {
                continue;
            }
            if ($this->allowed($user['subject'], $type) && $this->enabled($user['subject'], $type)) {
                $subjects[] = $user['subject'];
            }
        }
        foreach ($creatorSubjects as $subject) {
            $identity = Subject::parse($subject);
            if ($identity['installation'] === Subject::installation() && $this->enabled($subject, $type)) {
                $subjects[] = $subject;
            }
        }
        $subjects = array_values(array_unique($subjects));
        $id = null;
        if ($subjects !== []) {
            $creator = $type === 'inbox_interrupt' ? 'assistant' : (string)ifset($payload, 'created_by', ifset($payload, 'actor', 'Saldi'));
            $notification = (new PushService($this->connection))->create($subjects, $creator, $type, $title, $message,
                null, true, $event['ref_type'], $event['ref_id'] === null ? null : (int)$event['ref_id']);
            $id = $notification['notification_id'];
        }
        $eventId = (int)$event['id'];
        $notificationId = $id ?? 'NULL';
        $this->store->execute("UPDATE mobile_events SET fanned_out_at=now(),notification_id=$notificationId,
            attempts=attempts+1,last_error=NULL WHERE id=$eventId");
        return ['notification_id'=>$id,'recipient_count'=>count($subjects)];
    }

    /** @return bool Interrupts use current item visibility and kind read rights; revisors receive no push. */
    private function interruptAllowed(array $user, array $event): bool
    {
        if ($user['principal'] !== 'account' || $event['ref_type'] !== 'inbox_item' || $event['ref_id'] === null) {
            return false;
        }
        $runtime = SaldiDb::connect(saldi_runtime_config(), $this->store->rows('SELECT current_database() AS db')[0]['db']);
        try {
            $ctx = saldi_runtime_context_for_user($runtime, ['id' => $this->tenantId], (int)$user['user_id'], 'user:' . $user['user_id']);
            $item = (new SaldiInboxService($runtime, SaldiKindRegistry::default()))->get($ctx, (int)$event['ref_id']);
            return $item['status'] === 'open' && $item['attention'] === 'interrupt'
                && Rights::allows($ctx->rights, SaldiKindRegistry::module($item['kind']), 'read');
        } catch (SaldiRuntimeError $error) {
            if (in_array($error->errorCode, ['NOT_FOUND', 'PERMISSION_DENIED', 'UNAUTHENTICATED'], true)) {
                return false;
            }
            throw $error;
        } finally {
            $runtime->close();
        }
    }

    /** @return bool Broadcast eligibility uses only the type's current module right. */
    private function allowed(string $subject, string $type): bool
    {
        if (!isset(self::TYPES[$type])) {
            return false;
        }
        [$module, $action] = self::TYPES[$type];
        return $this->allowsModule($subject, $module, $action);
    }

    /** @return bool Sellers may receive settlement creator notifications without report rights. */
    private function preferenceEligible(string $subject, string $type): bool
    {
        return $this->allowed($subject, $type)
            || ($type === 'invoice_paid' && $this->allowsModule($subject, 'debitorordre', 'read'));
    }

    /** @return bool Current user existence/status and registry membership precede the module check. */
    private function allowsModule(string $subject, string $module, string $action): bool
    {
        $identity = Subject::parse($subject);
        if ($identity['installation'] !== Subject::installation()) {
            return false;
        }
        $revisor = $identity['principal'] === 'registry';
        $store = $revisor ? $this->registry : $this->store;
        $id = (int)$identity['user_id'];
        $rows = $store->rows("SELECT to_jsonb(b) AS user_json FROM brugere b WHERE id=$id");
        if ($rows === []) {
            return false;
        }
        $user = json_decode($rows[0]['user_json'], true, 512, JSON_THROW_ON_ERROR);
        if (array_key_exists('status', $user) && $user['status'] !== null && !MobileAuth::enabled($user['status'])) {
            return false;
        }
        if ($revisor) {
            $membership = Rights::registry((string)ifset($user, 'rettigheder', ''));
            if (!$membership['admin'] && !in_array($this->tenantId, $membership['tenant_ids'], true)) {
                return false;
            }
        }
        if ($module === 'employee') {
            return (new EmployeeContext($this->connection, $identity['principal'], $id))->linked;
        }
        return Rights::allows((string)ifset($user, 'rettigheder', ''), $module, $action, null, $revisor);
    }

    /** @return bool Missing preferences default to on. */
    private function enabled(string $subject, string $type): bool
    {
        $rows = $this->store->rows('SELECT enabled FROM notification_preferences WHERE subject='
            . $this->store->literal($subject) . ' AND type=' . $this->store->literal($type === 'inbox_interrupt' ? 'assistant_interrupt' : $type));
        return $rows === [] || $rows[0]['enabled'] === 't';
    }

    /** @return array{items:list<array{type:string,enabled:bool,label?:string}>} */
    public function preferences(string $subject): array
    {
        $items = [];
        foreach (array_keys(self::TYPES) as $type) {
            if ($this->preferenceEligible($subject, $type)) {
                $item = ['type'=>$type === 'inbox_interrupt' ? 'assistant_interrupt' : $type,'enabled'=>$this->enabled($subject, $type)];
                if ($type === 'inbox_interrupt') {
                    $item['label'] = $this->assistantPreferenceLabel();
                }
                $items[] = $item;
            }
        }
        return ['items'=>$items];
    }

    /** @return string Label uses the tenant text catalogue with the frozen Danish fallback. */
    private function assistantPreferenceLabel(): string
    {
        $runtime = SaldiDb::connect(saldi_runtime_config(), $this->store->rows('SELECT current_database() AS db')[0]['db']);
        try {
            return SaldiText::get($runtime, 6433, 1, 'Forslag og fund fra Saldi Assist');
        } finally {
            $runtime->close();
        }
    }

    /**
     * Own switches and replay record commit together. Unsupported/mandatory types cannot be stored.
     * @return array{status:int,body:array,headers:array<string,string>}
     */
    public function updatePreferences(string $subject, array $body): array
    {
        $this->requireTransaction(false);
        $this->store->execute('BEGIN');
        try {
            $result = (new Idempotency($this->connection))->execute($subject, 'notifications.preferences', null, $body,
                function () use ($subject, $body): array {
                    $items = ifset($body, 'items');
                    if (!is_array($items) || !array_is_list($items) || count($items) > count(self::TYPES)) {
                        throw new MobileT2Error('validation_failed', 422, ['reason'=>'invalid_preferences']);
                    }
                    $seen = [];
                    foreach ($items as $item) {
                        $type = ifset($item, 'type');
                        $enabled = ifset($item, 'enabled');
                        $eventType = $type === 'assistant_interrupt' ? 'inbox_interrupt' : $type;
                        if (!is_string($type) || $type === 'inbox_interrupt' || !isset(self::TYPES[$eventType]) || !is_bool($enabled) || isset($seen[$type])) {
                            throw new MobileT2Error('validation_failed', 422, ['reason'=>'invalid_preferences']);
                        }
                        if (!$this->preferenceEligible($subject, $eventType)) {
                            throw new MobileT2Error('permission_denied', 403);
                        }
                        $seen[$type] = true;
                        $this->store->execute('INSERT INTO notification_preferences(subject,type,enabled) VALUES('
                            . $this->store->literal($subject) . ',' . $this->store->literal($type) . ',' . ($enabled ? 'true' : 'false')
                            . ') ON CONFLICT(subject,type) DO UPDATE SET enabled=EXCLUDED.enabled,updated_at=now()');
                    }
                    return ['status'=>200,'body'=>['success'=>true,'message'=>'','data'=>$this->preferences($subject)]];
                });
            $this->store->execute('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }

    /**
     * Producer payload contract: name/description/amount/kontonr/account_name/reason for attachments;
     * faktnr/amount/customer_name/via for invoices; count for digest; ordrenr/amount/customer_name
     * for webshop; varenr/name/stock/unit/min for stock. All money is a decimal string.
     * Bookings: event/status/service/customer_name/date/start; employee_ansat_id,
     * recipient_subjects and actor are server-derived delivery guards.
     * Optional title/message provide the producer's already-rendered Danish inbox copy.
     * @return array{0:string,1:string}
     */
    public static function render(string $type, array $payload): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new UnexpectedValueException('Unknown event type');
        }
        $text = static function (string $key, string $default = '') use ($payload): string {
            $value = ifset($payload, $key, $default);
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                throw new UnexpectedValueException('Invalid event field');
            }
            return (string)$value;
        };
        $amount = $text('amount', '0.00');
        $sum = number_format((float)$amount, 2, ',', '.');
        $name = $text('name', 'Saldi');
        $description = $text('description');
        $invoice = $text('faktnr');
        $customer = $text('customer_name');
        switch ($type) {
            case 'inbox_interrupt':
                return [$text('title', 'Saldi Assist har et nyt fund'), $text('message', 'Åbn Opgaver for at se det.')];
            case 'attachment_decided':
                if ($text('decision', 'approved') === 'rejected') {
                    $title = 'Bogholder spørger';
                    $message = "$name afviste \"$description\": " . $text('reason');
                } else {
                    $title = 'Bilag godkendt';
                    $message = "$name godkendte \"$description\" på $sum — konto " . $text('kontonr') . ' ' . $text('account_name') . '.';
                }
                break;
            case 'attachment_resubmitted':
                $title = 'Bilag sendt igen';
                $message = "$name har rettet \"$description\" og sendt det igen.";
                break;
            case 'invoice_paid':
                $title = 'Betaling modtaget';
                $via = $text('via') === 'mobilepay' ? ' via MobilePay' : '';
                $message = "Faktura $invoice på $sum er betalt af $customer$via.";
                break;
            case 'invoice_overdue':
                $title = 'Faktura forfalden';
                $message = "Faktura $invoice til $customer på $sum er forfalden.";
                break;
            case 'invoice_overdue_digest':
                $title = 'Forfaldne fakturaer';
                $message = $text('count') . ' fakturaer er forfaldne i dag. Se dem under Penge.';
                break;
            case 'webshop_order':
                $title = 'Ny webshopordre';
                $message = 'Ordre ' . $text('ordrenr') . " fra $customer på $sum.";
                break;
            case 'booking_assigned':
                $service = $text('service');
                $when = $text('date') . ' kl. ' . $text('start');
                if ($text('event') === 'created') {
                    $title = 'Ny booking';
                    $message = "$service hos $customer $when.";
                } elseif ($text('status') === 'aflyst') {
                    $title = 'Booking aflyst';
                    $message = "$service hos $customer $when er aflyst.";
                } elseif ($text('event') === 'moved') {
                    $title = 'Booking flyttet';
                    $message = "$service hos $customer er flyttet til $when.";
                } else {
                    $title = 'Booking ændret';
                    $message = "$service hos $customer $when er ændret.";
                }
                break;
            case 'low_stock':
                $title = 'Lager under minimum';
                $message = $text('varenr') . " $name: " . $text('stock') . ' ' . $text('unit') . ' på lager, minimum ' . $text('min') . '.';
                break;
        }
        return [$text('title', $title), $text('message', $message)];
    }

    private function requireTransaction(bool $open): void
    {
        $expected = $open ? PGSQL_TRANSACTION_INTRANS : PGSQL_TRANSACTION_IDLE;
        if (!$this->connection instanceof \PgSql\Connection || pg_transaction_status($this->connection) !== $expected) {
            throw new LogicException('Invalid event transaction ownership');
        }
    }
}
