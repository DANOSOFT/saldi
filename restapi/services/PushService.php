<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/PushService.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Exclude ambiguous legacy registrations from dispatch and quarantine stale dispatches.
// 20261001 CDX/LH Include the creating user display name in inbox responses.
// 20261001 CDX/LH Accept notification references in the atomic inbox write.
require_once __DIR__ . '/../core/MobileSchema.php';
require_once __DIR__ . '/../core/Subject.php';

require_once __DIR__ . '/../core/MobileDisplayName.php';

/** Tenant inbox and dispatch writes. The caller supplies the selected tenant connection. */
final class PushService
{
    private MobileSchema $store;
    private MobileDisplayName $displayNames;

    public function __construct($connection)
    {
        $this->store = new MobileSchema($connection);
        $this->displayNames = new MobileDisplayName($connection);
    }

    /**
     * Write content, one inbox row per subject, and one dispatch per registered device.
     * May run in a reminder transaction; retries of delivery operate only on dispatch ids.
     * @param list<string> $subjects
     * @return array{notification_id:int, recipient_count:int, dispatch_count:int}
     */
    public function create(array $subjects, string $createdBy, string $type, string $title, string $message, ?int $reminderId = null, bool $inTransaction = false, ?string $refType = null, ?int $refId = null): array
    {
        if (!$inTransaction) {
            $this->store->execute('BEGIN');
        }
        try {
            $creator = $this->store->literal($createdBy);
            $kind = $this->store->literal($type);
            $heading = $this->store->literal($title);
            $text = $this->store->literal($message);
            $reminder = $reminderId === null ? 'NULL' : (string)(int)$reminderId;
            $referenceType = $refType === null ? 'NULL' : $this->store->literal($refType);
            $referenceId = $refId === null ? 'NULL' : (string)(int)$refId;
            $rows = $this->store->rows("INSERT INTO notifications(type,title,message,reminder_id,created_by,ref_type,ref_id)
                VALUES ($kind,$heading,$text,$reminder,$creator,$referenceType,$referenceId) RETURNING id");
            $id = (int)$rows[0]['id'];
            $count = 0;
            foreach (array_unique($subjects) as $subject) {
                $identity = Subject::parse($subject);
                $principal = $this->store->literal($identity['principal']);
                $user = (int)$identity['user_id'];
                $owner = $this->store->literal($subject);
                $recipients = $this->store->rows("INSERT INTO notification_recipients(notification_id,subject)
                    VALUES ($id,$owner) RETURNING id");
                $recipient = (int)$recipients[0]['id'];
                $dispatch = $this->store->rows("INSERT INTO push_dispatch(recipient_id,device_id,push_token_id)
                    SELECT $recipient,device_id,id FROM push_tokens WHERE subject=$owner AND device_id IS NOT NULL
                        AND device_id<>'' AND principal=$principal AND user_id=$user AND db_name=current_database()
                    RETURNING id");
                $count += count($dispatch);
            }
            if (!$inTransaction) {
                $this->store->execute('COMMIT');
            }
            return ['notification_id'=>$id, 'recipient_count'=>count(array_unique($subjects)), 'dispatch_count'=>$count];
        } catch (Throwable $e) {
            if (!$inTransaction) {
                $this->store->execute('ROLLBACK');
            }
            throw $e;
        }
    }

    /**
     * Quarantine unsent dispatches whose registration has no matching canonical recipient/device identity.
     * @return int Number of dispatches refused before the scheduled worker can send them.
     */
    public function quarantineAmbiguousDispatches(string $installation): int
    {
        $prefix = $this->store->literal($installation . '/');
        $rows = $this->store->rows("UPDATE push_dispatch d SET state='rejected',last_error='AmbiguousIdentity'
            WHERE d.state IN ('stored','sent') AND NOT EXISTS (
                SELECT 1 FROM push_tokens p JOIN notification_recipients r ON r.id=d.recipient_id
                WHERE p.id=d.push_token_id AND p.subject=r.subject AND p.device_id=d.device_id
                    AND p.device_id<>'' AND p.db_name=current_database() AND p.principal IN ('account','registry')
                    AND p.subject=$prefix || p.principal || '/' || p.user_id::text)
            RETURNING d.id");
        return count($rows);
    }

    /**
     * Cursor is the notification id; descending ids keep pages stable when new messages arrive.
     * @return array{items:list<array{notification_id:int,type:string,title:string,message:string,reminder_id:int|null,amount:string|null,date:string|null,created_by:string,sender_name:string,created_at:string,read_at:string|null,resolved_at:string|null}>,next_before:int|null}
     */
    public function inbox(string $subject, int $limit = 50, ?int $before = null): array
    {
        $owner = $this->store->literal($subject);
        $limit = max(1, min(100, $limit));
        $cursor = $before === null ? '' : ' AND n.id<' . (int)$before;
        $fetch = $limit + 1;
        $rows = $this->store->rows("SELECT n.id AS notification_id,n.type,n.title,n.message,n.reminder_id,
                n.created_by,n.created_at,r.read_at,r.resolved_at,m.amount,m.date
            FROM notification_recipients r JOIN notifications n ON n.id=r.notification_id
            LEFT JOIN reminders m ON m.id=n.reminder_id
            WHERE r.subject=$owner $cursor ORDER BY n.id DESC LIMIT $fetch");
        $more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as &$row) {
            $row['sender_name'] = $this->displayNames->sender((string)$row['created_by']);
            $row['notification_id'] = (int)$row['notification_id'];
            $row['reminder_id'] = $row['reminder_id'] === null ? null : (int)$row['reminder_id'];
        }
        unset($row);
        return ['items'=>$rows, 'next_before'=>$more ? (int)end($rows)['notification_id'] : null];
    }

    /** Idempotently mark only the caller's recipient row; no foreign row existence is disclosed. */
    public function read(string $subject, int $notificationId): bool
    {
        $owner = $this->store->literal($subject);
        $id = (int)$notificationId;
        return count($this->store->rows("UPDATE notification_recipients SET read_at=COALESCE(read_at,now())
            WHERE subject=$owner AND notification_id=$id RETURNING id")) === 1;
    }
}
