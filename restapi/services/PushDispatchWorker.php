<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/PushDispatchWorker.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Retain referenced reminders, isolate cleanup failures and use generic event push text.
require_once __DIR__ . '/../core/MobileSchema.php';

/** Durable PostgreSQL leases; a killed worker leaves dispatch rows, never recreates inbox rows. */
final class PushDispatchWorker
{
    private MobileSchema $store;
    private $connection;
    private int $tenantId;
    private string $installation;
    private string $sendUrl;
    private string $receiptUrl;
    private const MAX_ATTEMPTS = 8;

    public function __construct($connection, int $tenantId, string $installation, ?string $sendUrl = null, ?string $receiptUrl = null)
    {
        $this->connection = $connection;
        $this->store = new MobileSchema($connection);
        $this->tenantId = $tenantId;
        $this->installation = $installation;
        $this->sendUrl = $sendUrl ?? (getenv('SALDI_EXPO_PUSH_URL') ?: 'https://exp.host/--/api/v2/push/send');
        $this->receiptUrl = $receiptUrl ?? (getenv('SALDI_EXPO_RECEIPTS_URL') ?: 'https://exp.host/--/api/v2/push/getReceipts');
    }

    /**
     * One tenant per invocation, one batch of at most 100 sends and 100 receipt polls.
     * Session advisory lock excludes overlapping workers and is released by process death.
     * @return array{claimed:int,ticketed:int,delivered:int,rejected:int,busy:bool}
     */
    public function run(): array
    {
        $stats = ['claimed'=>0, 'ticketed'=>0, 'delivered'=>0, 'rejected'=>0, 'busy'=>false];
        $lock = $this->store->rows("SELECT pg_try_advisory_lock(hashtext(current_database()),hashtext('mobile_push_worker')) AS locked");
        if ($lock[0]['locked'] !== 't') {
            $stats['busy'] = true;
            return $stats;
        }
        try {
            $this->cleanup();
            $receipts = $this->store->rows("SELECT d.*,p.token FROM push_dispatch d LEFT JOIN push_tokens p ON p.id=d.push_token_id
                WHERE d.state='ticketed' AND d.next_attempt_at<=now() ORDER BY d.id LIMIT 100");
            if ($receipts !== []) {
                $this->receipts($receipts, $stats);
            }
            $this->store->execute('BEGIN');
            try {
                $rows = $this->store->rows("SELECT d.*,p.token,n.id AS notification_id,n.type FROM push_dispatch d
                    JOIN notification_recipients r ON r.id=d.recipient_id JOIN notifications n ON n.id=r.notification_id
                    LEFT JOIN push_tokens p ON p.id=d.push_token_id
                    WHERE d.state IN ('stored','sent') AND d.next_attempt_at<=now() ORDER BY d.id LIMIT 100 FOR UPDATE OF d");
                foreach ($rows as &$row) {
                    $id = (int)$row['id'];
                    if ($row['token'] === null || (int)$row['attempts'] >= self::MAX_ATTEMPTS) {
                        $this->reject($id, $row['token'] === null ? 'TokenRemoved' : 'RetryLimit');
                        $stats['rejected']++;
                        $row['token'] = null;
                        continue;
                    }
                    $row['attempts'] = (int)$row['attempts'] + 1;
                    $row['sent_token_hash'] = hash('sha256', $row['token']);
                    $fingerprint = $this->store->literal($row['sent_token_hash']);
                    $this->store->execute("UPDATE push_dispatch SET state='sent',sent_token_hash=$fingerprint,attempts=attempts+1,next_attempt_at=now()+interval '5 minutes' WHERE id=$id");
                    $stats['claimed']++;
                }
                unset($row);
                $this->store->execute('COMMIT');
            } catch (Throwable $e) {
                $this->store->execute('ROLLBACK');
                throw $e;
            }
            $rows = array_values(array_filter($rows, static fn(array $row): bool => $row['token'] !== null && (int)$row['attempts'] <= self::MAX_ATTEMPTS));
            if ($rows !== []) {
                $payload = [];
                foreach ($rows as $row) {
                    $payload[] = ['to'=>$row['token'], 'title'=>'Ny besked fra Saldi', 'body'=>$row['type'] === 'reminder' ? 'Åbn appen for at se påmindelsen' : 'Åbn appen for at se beskeden',
                        'data'=>['notification_id'=>(int)$row['notification_id'], 'tenant_id'=>$this->tenantId, 'installation'=>$this->installation, 'type'=>$row['type']]];
                }
                try {
                    $response = $this->post($this->sendUrl, $payload);
                    $tickets = ifset($response, 'data');
                    if (!is_array($tickets) || !array_is_list($tickets) || count($tickets) !== count($rows)) {
                        throw new RuntimeException('Invalid ticket response');
                    }
                    foreach ($rows as $index => $row) {
                        $ticket = $tickets[$index];
                        $id = (int)$row['id'];
                        if (ifset($ticket, 'status') === 'ok' && is_string(ifset($ticket, 'id')) && strlen($ticket['id']) <= 255) {
                            $ticketId = $this->store->literal($ticket['id']);
                            $this->store->execute("UPDATE push_dispatch SET state='ticketed',ticket_id=$ticketId,ticketed_at=now(),last_error=NULL,next_attempt_at=now()+interval '15 minutes' WHERE id=$id");
                            $stats['ticketed']++;
                        } else {
                            $this->failure($row, $this->errorCode($ticket), $stats);
                        }
                    }
                } catch (Throwable $e) {
                    foreach ($rows as $row) {
                        // Already saved tickets must never be resubmitted after a later result fails.
                        $id = (int)$row['id'];
                        $state = $this->store->rows("SELECT state FROM push_dispatch WHERE id=$id");
                        if (ifset($state, [0,'state']) === 'sent') {
                            $this->retry($row, 'TransportError');
                        }
                    }
                }
            }
            return $stats;
        } finally {
            $this->store->execute("SELECT pg_advisory_unlock(hashtext(current_database()),hashtext('mobile_push_worker'))");
        }
    }

    /** Tenant inbox retention is 12 months; FK children are deleted before content. */
    private function cleanup(): void
    {
        $this->store->execute('BEGIN');
        try {
            $old = "SELECT id FROM notifications WHERE type IS NOT NULL AND created_at<now()-interval '12 months'";
            $this->store->execute("DELETE FROM push_dispatch WHERE recipient_id IN (SELECT id FROM notification_recipients WHERE notification_id IN ($old))");
            $this->store->execute("DELETE FROM notification_recipients WHERE notification_id IN ($old)");
            $this->store->execute("DELETE FROM notifications WHERE id IN ($old)");
            $this->store->execute("DELETE FROM reminders WHERE created_at<now()-interval '12 months' AND NOT EXISTS (SELECT 1 FROM notifications WHERE reminder_id=reminders.id)
                AND NOT EXISTS (SELECT 1 FROM pool_files WHERE reminder_id=reminders.id)");
            $this->store->execute('COMMIT');
        } catch (Throwable $e) {
            $this->store->execute('ROLLBACK');
            error_log(json_encode(['tenant_id'=>$this->tenantId, 'error'=>'push_cleanup_failed', 'exception'=>get_class($e)], JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $stats
     */
    private function receipts(array $rows, array &$stats): void
    {
        try {
            $response = $this->post($this->receiptUrl, ['ids'=>array_column($rows, 'ticket_id')]);
            $receipts = ifset($response, 'data');
            if (!is_array($receipts)) {
                throw new RuntimeException('Invalid receipts');
            }
            foreach ($rows as $row) {
                $receipt = ifset($receipts, $row['ticket_id']);
                $id = (int)$row['id'];
                if (ifset($receipt, 'status') === 'ok') {
                    $this->store->execute("UPDATE push_dispatch SET state='delivered',last_error=NULL WHERE id=$id");
                    $stats['delivered']++;
                } elseif (ifset($receipt, 'status') === 'error') {
                    $this->failure($row, $this->errorCode($receipt), $stats);
                } elseif (strtotime(ifset($row, 'ticketed_at', $row['created_at'])) < time() - 86400) {
                    $this->reject($id, 'ReceiptMissing');
                    $stats['rejected']++;
                } else {
                    // Back off polls so missing receipts do not starve later tickets.
                    $this->store->execute("UPDATE push_dispatch SET last_error='ReceiptPending',next_attempt_at=now()+interval '5 minutes' WHERE id=$id");
                }
            }
        } catch (Throwable $e) {
            // A failed receipt fetch must never cause a send of an already accepted ticket.
            foreach ($rows as $row) {
                $id = (int)$row['id'];
                $this->store->execute("UPDATE push_dispatch SET last_error='ReceiptTransportError',next_attempt_at=now()+interval '5 minutes' WHERE id=$id AND state='ticketed'");
            }
        }
    }

    /**
     * Never store relay response messages which may echo tokens or customer data.
     * @return string
     */
    private function errorCode($result): string
    {
        $code = ifset($result, ['details','error']);
        return in_array($code, ['DeviceNotRegistered','MessageTooBig','MessageRateExceeded','MismatchSenderId','InvalidCredentials'], true) ? $code : 'RelayError';
    }

    /** Device pruning compares the sent token too, preserving a newly rotated registration. */
    private function failure(array $row, string $error, array &$stats): void
    {
        $id = (int)$row['id'];
        if ($error === 'DeviceNotRegistered') {
            $tokenId = (int)$row['push_token_id'];
            $token = $this->store->literal((string)$row['token']);
            if ($row['token'] !== null && hash('sha256', $row['token']) === ifset($row, 'sent_token_hash')) {
                $this->store->execute("DELETE FROM push_tokens WHERE id=$tokenId AND token=$token");
            }
            $this->reject($id, $error);
            $stats['rejected']++;
        } elseif ($error === 'MessageRateExceeded' || $error === 'RelayError') {
            $this->retry($row, $error);
        } else {
            $this->reject($id, $error);
            $stats['rejected']++;
        }
    }

    private function retry(array $row, string $error): void
    {
        $id = (int)$row['id'];
        if ((int)$row['attempts'] >= self::MAX_ATTEMPTS) {
            $this->reject($id, 'RetryLimit');
            return;
        }
        $seconds = min(3600, 60 * (2 ** min(6, (int)$row['attempts'])));
        $code = $this->store->literal($error);
        $this->store->execute("UPDATE push_dispatch SET state='stored',ticket_id=NULL,ticketed_at=NULL,last_error=$code,next_attempt_at=now()+interval '$seconds seconds' WHERE id=$id");
    }

    private function reject(int $id, string $error): void
    {
        $code = $this->store->literal($error);
        $this->store->execute("UPDATE push_dispatch SET state='rejected',last_error=$code WHERE id=" . (int)$id);
    }

    /** @return array<string,mixed> */
    private function post(string $url, array $body): array
    {
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $accessToken = getenv('SALDI_EXPO_ACCESS_TOKEN');
        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20,
            CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS]);
        try {
            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($raw === false || $status < 200 || $status >= 300) {
                throw new RuntimeException('Push relay transport failed');
            }
            $result = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($result)) {
                throw new RuntimeException('Push relay response malformed');
            }
            return $result;
        } finally {
            curl_close($ch);
        }
    }
}
