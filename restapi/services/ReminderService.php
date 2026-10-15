<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/ReminderService.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Validate and lock upload recipients before writing files.
// 20261001 CDX/LH Include the creating user display name in reminder history.
// 20261001 CDX/LH Join bank reminder transactions and atomically link answering uploads and deletions.
// 20261001 CDX/LH Keep legacy reminder fields isolated and restrict attached uploads to existing associations.
require_once __DIR__ . '/PushService.php';
require_once __DIR__ . '/../core/MobileAuth.php';

require_once __DIR__ . '/../core/MobileDisplayName.php';

/** An expected reminder failure; endpoint and web controllers choose their own presentation. */
final class MobileReminderError extends RuntimeException
{
    public string $error;
    public int $status;

    public function __construct(string $error, int $status, string $message)
    {
        parent::__construct($message);
        $this->error = $error;
        $this->status = $status;
    }
}

/** Session-derived recipients and per-recipient reminder resolution in one tenant. */
final class ReminderService
{
    private MobileSchema $tenant;
    private MobileSchema $registry;
    private $connection;
    private int $tenantId;
    private MobileDisplayName $displayNames;

    public function __construct($tenantConnection, $registryConnection, int $tenantId)
    {
        $this->connection = $tenantConnection;
        $this->tenant = new MobileSchema($tenantConnection);
        $this->registry = new MobileSchema($registryConnection);
        $this->displayNames = new MobileDisplayName($tenantConnection, $registryConnection);
        $this->tenantId = $tenantId;
        $id = (int)$tenantId;
        $rows = $this->registry->rows("SELECT db FROM regnskab WHERE id=$id");
        $database = $this->tenant->rows('SELECT current_database() AS db');
        if (count($rows) !== 1 || $rows[0]['db'] !== $database[0]['db']) {
            throw new MobileReminderError('permission_denied', 403, 'Reminder tenant does not match the selected database');
        }
    }

    /**
     * A live refresh row (including a rotated predecessor) represents a live session family.
     * Resolve names in the principal's database; equal numeric ids never merge.
     * @return list<array{subject:string,principal:string,user_id:int,username:string}>
     */
    public function appUsers(): array
    {
        $id = (int)$this->tenantId;
        $subjects = $this->registry->rows("SELECT DISTINCT subject,principal FROM refresh_sessions
            WHERE tenant_id=$id AND revoked_at IS NULL AND expires_at>now() ORDER BY subject");
        $users = [];
        foreach ($subjects as $row) {
            $identity = Subject::parse($row['subject']);
            if ($identity['installation'] !== Subject::installation() || $identity['principal'] !== $row['principal']) {
                continue;
            }
            $userId = (int)$identity['user_id'];
            $store = $identity['principal'] === 'registry' ? $this->registry : $this->tenant;
            $names = $store->rows("SELECT brugernavn FROM brugere WHERE id=$userId");
            if ($names === []) {
                continue;
            }
            $users[] = ['subject'=>$row['subject'], 'principal'=>$identity['principal'], 'user_id'=>$userId, 'username'=>$names[0]['brugernavn']];
        }
        return $users;
    }

    /**
     * Tier 1 fields shared by REST and the web screen; bank workflow fields are server-selected.
     * @return array{id:int,notification_id:int,recipient_count:int,dispatch_count:int}
     */
    public function create(string $createdBy, $input): array
    {
        if (!$this->connection instanceof \PgSql\Connection || pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Reminder create requires an idle PostgreSQL connection');
        }
        $this->tenant->execute('BEGIN');
        try {
            $result = $this->createInTransaction($createdBy, array_intersect_key((array)$input,
                array_flip(['target', 'message', 'amount', 'date'])));
            $this->tenant->execute('COMMIT');
            return $result;
        } catch (Throwable $e) {
            $this->tenant->execute('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Share reminder/inbox/dispatch writes with the caller's business transaction; never commit it.
     * @param array{target:string,message:string,amount?:string|int|float|null,date?:string|null,bank_line_id?:int|null,title?:string} $input Defaults retain the Tier 1 reminder behavior.
     * @return array{id:int,notification_id:int,recipient_count:int,dispatch_count:int}
     */
    public function createInTransaction(string $createdBy, array $input): array
    {
        if (!$this->connection instanceof \PgSql\Connection || pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_INTRANS) {
            throw new LogicException('Reminder insertion requires a caller-owned PostgreSQL transaction');
        }
        $target = ifset($input, 'target');
        $message = ifset($input, 'message');
        $amount = ifset($input, 'amount');
        $date = ifset($input, 'date');
        if (!is_string($target) || !is_string($message) || trim($message) === '' || mb_strlen($message) > 4000 || str_contains($message, "\0")) {
            throw new MobileReminderError('validation_failed', 422, 'target and message (1–4000 characters) are required');
        }
        if ($amount !== null && ((!is_string($amount) && !is_int($amount) && !is_float($amount))
            || !preg_match('/^[0-9]{1,12}(\.[0-9]{1,3})?$/D', (string)$amount))) {
            throw new MobileReminderError('validation_failed', 422, 'amount must be a nonnegative decimal with at most three fractional digits');
        }
        if ($date !== null) {
            if (!is_string($date) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date) || substr($date, 0, 4) === '0000') {
                throw new MobileReminderError('validation_failed', 422, 'date must be YYYY-MM-DD');
            }
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new MobileReminderError('validation_failed', 422, 'date must be YYYY-MM-DD');
            }
        }
        $subjects = array_column($this->appUsers(), 'subject');
        if ($target !== 'all') {
            if (!in_array($target, $subjects, true)) {
                throw new MobileReminderError('validation_failed', 422, 'target must be an active app user in this regnskab');
            }
            $subjects = [$target];
        }
        if ($subjects === []) {
            throw new MobileReminderError('validation_failed', 422, 'No active app users in this regnskab');
        }
        $bankId = ifset($input, 'bank_line_id');
        $title = ifset($input, 'title', 'Bilag til bogholder');
        if (($bankId !== null && (!is_int($bankId) || $bankId < 1 || $bankId > 2147483647))
            || !is_string($title) || trim($title) === '' || mb_strlen($title) > 200 || str_contains($title, "\0")) {
            throw new MobileReminderError('validation_failed', 422, 'Invalid bank line or title');
        }
        $bank = $bankId === null ? 'NULL' : (string)(int)$bankId;
        $owner = $this->tenant->literal($createdBy);
        $destination = $this->tenant->literal($target);
        $text = $this->tenant->literal(trim($message));
        $value = $amount === null ? 'NULL' : $this->tenant->literal((string)$amount);
        $day = $date === null ? 'NULL' : $this->tenant->literal($date);
        $rows = $this->tenant->rows("INSERT INTO reminders(target_subject,message,amount,date,created_by,bank_line_id)
            VALUES($destination,$text,$value,$day,$owner,$bank) RETURNING id");
        $id = (int)$rows[0]['id'];
        $notification = (new PushService($this->connection))->create($subjects, $createdBy, 'reminder', $title, trim($message), $id, true);
        return ['id'=>$id] + $notification;
    }

    /**
     * Bookkeeper history includes each recipient's answer, with notification id for correlation.
     * @return array{items:list<array{id:int,target:string,message:string,amount:string|null,date:string|null,created_by:string,sender_name:string,created_at:string,notification_id:int,recipients:list<array{subject:string,read_at:string|null,resolved_at:string|null}>}>,next_before:int|null}
     */
    public function history(int $limit = 50, ?int $before = null): array
    {
        $limit = max(1, min(100, $limit));
        $cursor = $before === null ? '' : 'WHERE m.id<' . (int)$before;
        $fetch = $limit + 1;
        $rows = $this->tenant->rows("SELECT m.id,m.target_subject AS target,m.message,m.amount,m.date,m.created_by,m.created_at,n.id AS notification_id
            FROM reminders m JOIN notifications n ON n.reminder_id=m.id $cursor ORDER BY m.id DESC LIMIT $fetch");
        $more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as &$row) {
            $row['sender_name'] = $this->displayNames->sender((string)$row['created_by']);
            $row['id'] = (int)$row['id'];
            $row['notification_id'] = (int)$row['notification_id'];
            $row['recipients'] = $this->tenant->rows('SELECT subject,read_at,resolved_at FROM notification_recipients WHERE notification_id=' . $row['notification_id'] . ' ORDER BY subject');
        }
        unset($row);
        return ['items'=>$rows, 'next_before'=>$more ? (int)end($rows)['id'] : null];
    }
}

/**
 * Call only on a successfully committed/committing upload, using its server-selected tenant link.
 * Can participate in the upload transaction. Idempotent replay keeps the first resolution time.
 * @return array{reminder_id:int,resolved:bool,resolved_at:string,pool_file?:array<string,mixed>}
 * @throws MobileReminderError For a foreign/missing reminder or wrong targeted uploader.
 */
function mobileResolveReminder($tenantConnection, int $tenantId, string $uploaderSubject, int $reminderId, ?int $poolFileId = null): array
{
    $service = new ReminderService($tenantConnection, MobileAuth::connectRegistry(), $tenantId);
    $store = new MobileSchema($tenantConnection);
    $owner = $store->literal($uploaderSubject);
    $id = (int)$reminderId;
    if ($id < 1) {
        throw new MobileReminderError('validation_failed', 422, 'reminder_id must be positive');
    }
    $pool = null;
    if ($poolFileId !== null) {
        if (!$tenantConnection instanceof \PgSql\Connection || pg_transaction_status($tenantConnection) !== PGSQL_TRANSACTION_INTRANS) {
            throw new LogicException('Answering upload requires a caller-owned transaction');
        }
        $poolId = (int)$poolFileId;
        $pools = $store->rows("SELECT * FROM pool_files WHERE id=$poolId AND uploaded_by=$owner FOR UPDATE");
        if (count($pools) !== 1 || !in_array($pools[0]['status'], ['ubehandlet','attached'], true)) {
            throw new MobileReminderError('conflict', 409, 'Answering attachment is not available');
        }
        $pool = $pools[0];
        if ($pool['reminder_id'] !== null && (int)$pool['reminder_id'] !== $id) {
            throw new MobileReminderError('conflict', 409, 'Attachment already answers another reminder');
        }
        if ($pool['status'] === 'attached' && (int)$pool['reminder_id'] !== $id) {
            throw new MobileReminderError('conflict', 409, 'Attached receipt cannot answer a new reminder');
        }
        mobileValidateUploadReminder($tenantConnection, $tenantId, $uploaderSubject, $id);
        $reminder = $store->rows("SELECT bank_line_id FROM reminders WHERE id=$id")[0];
        if ($reminder['bank_line_id'] !== null) {
            $bankId = (int)$reminder['bank_line_id'];
            $bank = $store->rows("SELECT * FROM bank_lines WHERE id=$bankId FOR UPDATE")[0];
            require_once __DIR__ . '/../../includes/finans/bankLines.php';
            $effective = $store->rows('SELECT ' . bank_line_state_sql() . " AS state FROM bank_lines b WHERE b.id=$bankId")[0]['state'];
            $linked = $store->rows("SELECT id FROM pool_files WHERE (id=" . (int)$bank['pool_file_id'] . " OR bank_line_id=$bankId)
                AND id<>$poolId AND status IN ('ubehandlet','attached')");
            $same = (int)$pool['bank_line_id'] === $bankId && (int)$bank['pool_file_id'] === $poolId;
            if ($pool['status'] !== 'ubehandlet' && !$same) {
                throw new MobileReminderError('conflict', 409, 'Attached receipt cannot answer a new bank line');
            }
            if (($effective === 'matched' || $effective === 'ignored' || $linked !== []) && !$same) {
                throw new MobileReminderError('conflict', 409, 'Bank line is closed or already has an answering attachment');
            }
            if ($pool['bank_line_id'] !== null && (int)$pool['bank_line_id'] !== $bankId) {
                throw new MobileReminderError('conflict', 409, 'Attachment already answers another bank line');
            }
            $store->execute("UPDATE pool_files SET reminder_id=$id,bank_line_id=$bankId WHERE id=$poolId");
            if (!$same) {
                $store->execute("UPDATE bank_lines SET pool_file_id=$poolId,state='received',state_changed_at=now(),state_changed_by=$owner WHERE id=$bankId");
            }
        } else {
            $store->execute("UPDATE pool_files SET reminder_id=$id WHERE id=$poolId");
        }
        $pool = $store->rows("SELECT * FROM pool_files WHERE id=$poolId")[0];
    }
    $rows = $store->rows("UPDATE notification_recipients r SET resolved_at=COALESCE(r.resolved_at,now())
        FROM notifications n,reminders m WHERE r.notification_id=n.id AND n.reminder_id=m.id
        AND m.id=$id AND r.subject=$owner AND (m.target_subject='all' OR m.target_subject=$owner)
        RETURNING r.resolved_at");
    if (count($rows) !== 1) {
        throw new MobileReminderError('permission_denied', 403, 'Reminder is not assigned to this uploader in this regnskab');
    }
    $result = ['reminder_id'=>$id, 'resolved'=>true, 'resolved_at'=>$rows[0]['resolved_at']];
    return $pool === null ? $result : $result + ['pool_file'=>$pool];
}

/**
 * Validate and lock the assigned recipient before an upload writes files; caller owns the transaction.
 * @return void
 * @throws MobileReminderError For a missing or foreign recipient/tenant.
 */
function mobileValidateUploadReminder($tenantConnection, int $tenantId, string $uploaderSubject, int $reminderId): void
{
    new ReminderService($tenantConnection, MobileAuth::connectRegistry(), $tenantId);
    $store = new MobileSchema($tenantConnection);
    $owner = $store->literal($uploaderSubject);
    $id = (int)$reminderId;
    $rows = $store->rows("SELECT r.id FROM notification_recipients r
        JOIN notifications n ON r.notification_id=n.id JOIN reminders m ON n.reminder_id=m.id
        WHERE m.id=$id AND r.subject=$owner AND (m.target_subject='all' OR m.target_subject=$owner)
        FOR UPDATE OF r,n,m");
    if (count($rows) !== 1) {
        throw new MobileReminderError('permission_denied', 403, 'Reminder is not assigned to this uploader in this regnskab');
    }
}

/**
 * Release the deleted answering identity inside the owner's existing deletion transaction.
 * Keep provenance on the tombstone; another live answer, when present, remains received.
 * @return void
 */
function mobileReleaseBankLine($tenantConnection, array $poolRow): void
{
    $bankId = (int)ifset($poolRow, 'bank_line_id', 0);
    if ($bankId < 1) {
        return;
    }
    if (!$tenantConnection instanceof \PgSql\Connection || pg_transaction_status($tenantConnection) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Bank line release requires a caller-owned transaction');
    }
    $store = new MobileSchema($tenantConnection);
    $bank = $store->rows("SELECT * FROM bank_lines WHERE id=$bankId FOR UPDATE");
    if ($bank === []) {
        return;
    }
    $poolId = (int)$poolRow['id'];
    $others = $store->rows("SELECT id FROM pool_files WHERE bank_line_id=$bankId AND id<>$poolId
        AND status IN ('ubehandlet','attached') ORDER BY id DESC LIMIT 1");
    $link = $others === [] ? 'NULL' : (string)(int)$others[0]['id'];
    $state = $bank[0]['state'] === 'ignored' ? 'ignored' : ($others === [] ? 'requested' : 'received');
    $actor = $store->literal((string)$poolRow['uploaded_by']);
    $store->execute("UPDATE bank_lines SET pool_file_id=$link,state='$state',state_changed_at=now(),state_changed_by=$actor WHERE id=$bankId");
}
