<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/BankLineService.php --- ver 5.0.0 --- 2026.10.10
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
// 20261010 CDX/LH Expose the read-only Assist bank-line snapshot.

require_once __DIR__ . '/ReminderService.php';
require_once __DIR__ . '/../core/Paging.php';
require_once __DIR__ . '/../models/attachment/DurableAttachmentModel.php';
require_once __DIR__ . '/../../includes/finans/bankLines.php';

/** Tenant-local bank workflow over B3a's durable identities and derived document state. */
final class BankLineService
{
    private $connection;
    private MobileSchema $store;
    private ReminderService $reminders;
    private MobileDisplayName $names;
    private DurableAttachmentModel $attachments;
    private string $subject;

    public function __construct($connection, $registryConnection, int $tenantId, string $subject)
    {
        $this->connection = $connection;
        $this->store = new MobileSchema($connection);
        $this->reminders = new ReminderService($connection,$registryConnection,$tenantId);
        $this->names = new MobileDisplayName($connection,$registryConnection);
        $this->subject = $subject;
        $db = $this->store->rows('SELECT current_database() AS db')[0]['db'];
        $this->attachments = new DurableAttachmentModel($connection,$db,$subject,true);
    }

    /** @return int Canonical positive PostgreSQL integer from a query or typed body field. */
    public static function id($value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D',(string)$value)
            || strlen((string)$value) > 10 || (int)$value > 2147483647) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_id']);
        }
        return (int)$value;
    }

	/** @return array<string,mixed>|null Durable identity and newest surviving journal link. */
	public static function assistRecordSnapshot(PDO $pdo, int $id): ?array
	{
		$id = self::id($id);
		$state = bank_line_state_sql();
		$query = $pdo->prepare("SELECT b.*,($state) AS effective_state,j.* FROM bank_lines b
			LEFT JOIN LATERAL (SELECT k.id AS kassekladde_id,k.kladde_id,k.bilag,kl.bogfort
				FROM bank_line_journal_links l JOIN kassekladde k ON k.id=l.kassekladde_id
				LEFT JOIN kladdeliste kl ON kl.id=k.kladde_id WHERE l.bank_line_id=b.id
				ORDER BY l.created_at DESC,k.id DESC LIMIT 1) j ON true WHERE b.id = ?");
		$query->execute([$id]);
		$row = $query->fetch(PDO::FETCH_ASSOC);
		return $row === false ? null : $row;
	}

    /** Restore the connection consumed by B3a's SQL-only helpers, including on a failed query. */
    private function withConnection(callable $read)
    {
        $exists = array_key_exists('connection',$GLOBALS);
        $previous = ifset($GLOBALS,'connection');
        $GLOBALS['connection'] = $this->connection;
        try {
            return $read();
        } finally {
            if ($exists) {
                $GLOBALS['connection'] = $previous;
            } else {
                unset($GLOBALS['connection']);
            }
        }
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int,summary:array{missing_count:int,missing_total:string}} */
    public function listing(array $query): array
    {
        $paging = new Paging($query);
        $state = ifset($query,'state','missing');
        if (!is_string($state) || !in_array($state,['missing','requested','received','matched','ignored','all'],true)) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_filter']);
        }
        $filter = ['state'=>$state];
        if (array_key_exists('account',$query)) {
            $filter['account'] = self::id($query['account']);
        }
        $this->idle();
        $this->store->execute('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        try {
            $result = $this->withConnection(fn() => bank_lines_missing($filter,$paging->limit,$paging->offset));
            $items = array_map(fn(array $row): array => $this->project($row),$result['items']);
            $where = isset($filter['account']) ? ' AND b.account_kontonr=' . (int)$filter['account'] : '';
            $effective = bank_line_state_sql();
            $summary = $this->store->rows("SELECT count(*) AS count,round(COALESCE(sum(-b.amount),0),2) AS total FROM bank_lines b
                WHERE b.amount<0 AND ($effective) IN ('open','requested')
                AND EXISTS (SELECT 1 FROM bank_line_journal_links l JOIN kassekladde k ON k.id=l.kassekladde_id WHERE l.bank_line_id=b.id)
                AND NOT EXISTS (SELECT 1 FROM pool_files p WHERE (p.id=b.pool_file_id OR p.bank_line_id=b.id)
                    AND p.status IN ('ubehandlet','attached')) $where")[0];
            $this->store->execute('COMMIT');
            return $paging->response($items,$result['total']) + ['summary'=>[
                'missing_count'=>(int)$summary['count'],'missing_total'=>(string)$summary['total']]];
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> Raw identity with newest surviving journal and effective state. */
    private function find(int $id,bool $lock=false): array
    {
        if ($lock && $this->store->rows("SELECT id FROM bank_lines WHERE id=$id FOR UPDATE") === []) {
            throw new MobileT2Error('not_found',404);
        }
        $effective = bank_line_state_sql();
        $rows = $this->store->rows("SELECT b.*,($effective) AS effective_state,j.* FROM bank_lines b
            LEFT JOIN LATERAL (SELECT k.id AS kassekladde_id,k.kladde_id,k.bilag,kl.bogfort
                FROM bank_line_journal_links l JOIN kassekladde k ON k.id=l.kassekladde_id
                LEFT JOIN kladdeliste kl ON kl.id=k.kladde_id WHERE l.bank_line_id=b.id
                ORDER BY l.created_at DESC,k.id DESC LIMIT 1) j ON true WHERE b.id=$id");
        if ($rows === []) {
            throw new MobileT2Error('not_found',404);
        }
        return $rows[0];
    }

    /** @return string|null ISO 8601 UTC instant. */
    private static function time(?string $value): ?string
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
    }

    /** @return string Current name without exposing an unavailable canonical identity. */
    private function name(string $subject): string
    {
        $name = $this->names->sender($subject);
        return $name === $subject ? 'Saldi' : $name;
    }

    /**
     * @return array{
     *   id:int,date:string,text:string,amount:string,currency:string,account:array{kontonr:int,name:string},state:string,
     *   journal:array{kladde_id:int,kassekladde_id:int,bilag:int,posted:bool}|null,
     *   reminder:array{id:int,target_name:string|null,all:bool,sent_at:string,resolved_at:string|null}|null,
     *   attachment:array{id:int,uploaded_by_name:string,display_status:string}|null
     * }
     */
    private function project(array $row): array
    {
        $id = (int)$row['id'];
        $account = (int)$row['account_kontonr'];
        $names = $this->store->rows("SELECT beskrivelse FROM kontoplan WHERE kontonr=$account ORDER BY regnskabsaar DESC,id DESC LIMIT 1");
        $reminders = $this->store->rows("SELECT r.*,CASE WHEN count(nr.id)>0 AND bool_and(nr.resolved_at IS NOT NULL)
            THEN max(nr.resolved_at) ELSE NULL END AS resolved_at
            FROM reminders r LEFT JOIN notifications n ON n.reminder_id=r.id
            LEFT JOIN notification_recipients nr ON nr.notification_id=n.id
            WHERE r.bank_line_id=$id GROUP BY r.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1");
        $reminder = null;
        if ($reminders !== []) {
            $r = $reminders[0];
            $all = $r['target_subject'] === 'all';
            $reminder = ['id'=>(int)$r['id'],'target_name'=>$all ? null : $this->name($r['target_subject']),
                'all'=>$all,'sent_at'=>self::time($r['created_at']),'resolved_at'=>self::time($r['resolved_at'])];
        }
        $poolId = (int)$row['pool_file_id'];
        $pools = $this->store->rows("SELECT * FROM pool_files WHERE (id=$poolId OR bank_line_id=$id)
            AND status IN ('ubehandlet','attached') ORDER BY (id=$poolId) DESC,id DESC LIMIT 1");
        $attachment = null;
        if ($pools !== []) {
            $pool = $pools[0];
            $attachment = ['id'=>(int)$pool['id'],'uploaded_by_name'=>$this->name((string)$pool['uploaded_by']),
                'display_status'=>$this->attachments->toArray($pool)['display_status']];
        }
        return ['id'=>$id,'date'=>$row['transdate'],'text'=>$row['text'],'amount'=>bank_line_money($row['amount']),
            'currency'=>trim($row['currency']),'account'=>['kontonr'=>$account,'name'=>(string)ifset($names,[0,'beskrivelse'],'')],
            'state'=>$row['effective_state'],'journal'=>$row['kassekladde_id'] === null ? null : [
                'kladde_id'=>(int)$row['kladde_id'],'kassekladde_id'=>(int)$row['kassekladde_id'],
                'bilag'=>(int)$row['bilag'],'posted'=>$row['bogfort'] === 'V'],
            'reminder'=>$reminder,'attachment'=>$attachment];
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function remind(array $input): array
    {
        return $this->write('bank_line_remind',$input,function (array $row,int $id) use ($input): array {
            $this->requireOutgoing($row);
            if (in_array($row['effective_state'],['matched','ignored'],true)) {
                throw new MobileT2Error('conflict',409,['reason'=>'bank_line_closed']);
            }
            $custom = ifset($input,'message');
            if ($custom !== null && (!is_string($custom) || trim($custom) === '' || mb_strlen($custom)>4000 || str_contains($custom,"\0"))) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_message']);
            }
            $date = new DateTimeImmutable($row['transdate']);
            $amount = bank_line_money(abs((float)$row['amount']));
            $message = $custom ?? ('Påmindelse fra ' . $this->name($this->subject) . ': ' . $row['text'] . ' '
                . $date->format('d/n') . ', ' . number_format((float)$amount,2,',','.') . '.');
            $created = $this->reminders->createInTransaction($this->subject,[
                'target'=>ifset($input,'target'),'message'=>$message,'amount'=>$amount,'date'=>$row['transdate'],
                'bank_line_id'=>$id,'title'=>'Mangler bilag']);
            // A received bilag stays linked when the bookkeeper sends another reminder.
            $state = $row['effective_state'] === 'received' ? 'received' : 'requested';
            $actor = $this->store->literal($this->subject);
            $this->store->execute("UPDATE bank_lines SET state='$state',state_changed_at=now(),state_changed_by=$actor WHERE id=$id");
            return ['status'=>201,'body'=>['success'=>true,'message'=>'','data'=>[
                'reminder_id'=>$created['id'],'notification_id'=>$created['notification_id'],
                'recipient_count'=>$created['recipient_count'],'bank_line'=>$this->project($this->find($id))]]];
        });
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function state(array $input): array
    {
        $action = ifset($input,'action');
        $note = ifset($input,'note');
        if (!in_array($action,['ignore','reopen'],true) || ($note !== null && (!is_string($note) || mb_strlen($note)>4000 || str_contains($note,"\0")))) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_state']);
        }
        return $this->write('bank_line_state',$input,function (array $row,int $id) use ($action,$note): array {
            $this->requireOutgoing($row);
            if ($row['effective_state'] === 'matched') {
                throw new MobileT2Error('conflict',409,['reason'=>'bank_line_closed']);
            }
            $next = 'ignored';
            if ($action === 'reopen') {
                $hasPool = $this->store->rows("SELECT id FROM pool_files WHERE (id=" . (int)$row['pool_file_id'] . " OR bank_line_id=$id)
                    AND status IN ('ubehandlet','attached') LIMIT 1") !== [];
                $hasReminder = $this->store->rows("SELECT r.id FROM reminders r JOIN notifications n ON n.reminder_id=r.id
                    JOIN notification_recipients nr ON nr.notification_id=n.id WHERE r.bank_line_id=$id AND nr.resolved_at IS NULL LIMIT 1") !== [];
                $next = $hasPool ? 'received' : ($hasReminder ? 'requested' : 'open');
            }
            $noteSql = $action === 'ignore' && $note !== null ? $this->store->literal($note) : 'NULL';
            $actor = $this->store->literal($this->subject);
            $this->store->execute("UPDATE bank_lines SET state='$next',ignore_note=$noteSql,state_changed_at=now(),state_changed_by=$actor WHERE id=$id");
            return ['status'=>200,'body'=>['success'=>true,'message'=>'','data'=>$this->project($this->find($id))]];
        });
    }

    /** Only durable outgoing identities can require a bilag. */
    private function requireOutgoing(array $row): void
    {
        if ((float)$row['amount'] >= 0) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'bank_line_not_outgoing']);
        }
    }

    /** Assert transaction ownership before issuing BEGIN. */
    private function idle(): void
    {
        if (!$this->connection instanceof \PgSql\Connection || pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Bank line service requires an idle PostgreSQL connection');
        }
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Atomic workflow plus complete replay envelope. */
    private function write(string $operation,array $input,callable $change): array
    {
        $value = ifset($input,'bank_line_id');
        if (!is_int($value)) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_id']);
        }
        $id = self::id($value);
        Idempotency::clientId($input);
        $this->idle();
        $this->store->execute('BEGIN');
        try {
            $response = (new Idempotency($this->connection))->execute($this->subject,$operation,$id,$input,
                fn(): array => $change($this->find($id,true),$id));
            $this->store->execute('COMMIT');
            return $response;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }
}
