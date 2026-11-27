<?php
// includes/reminderAutomation.php — ver 5.1.1 — 2026.11.27
// Copyright (c) 2026 Danosoft ApS. GNU GPL version 2 or later.
// 20261117 CDX/ASR Opt-in reminder planning, approval and durable mail delivery for redesign.
// 20261126 ASR Review I12: run() takes the creation lock (reminder_create_lock()) instead of LOCK TABLE ordrer, and re-checks only the candidate's own account under it.
//              preview() takes an optional account; without one it lists every candidate, as before.
// 20261126 ASR Review GPT-R-1: bookPrevious() checks the fee item's and the debtor's group of the reminder's fiscal year (grupper.fiscal_year), the rows bogfor_rykker() posts with.
// 20261126 ASR Review GPT-R-5: a sending attempt stays uncertain until it is approved after the mail server's log was checked (uncertainSql(), from the audit events), also when the job was paused in between.
//              approve() asks for that check for a paused job too, and jobs() says which jobs need it.
// 20261126 ASR Review GPT-R-6: confirmSent() records a delivery the operator confirmed in the mail server's log (the process stopped after the mail server took the reminder, before 'sent' was saved), on the day it went out and without sending it again.
//              A link reminder is marked sent as after its mail, and the day goes in reminder_dispatch; the job can then be escalated, and an R3's fee is booked.
// 20261126 ASR Review GPT-R-7: dateForDispatch() dates a tracked reminder that has not gone out on the day it is sent (rykkerprint() calls it before rendering), so a draft approved late does not go out with a payment deadline that has passed.
// 20261126 ASR Review GPT-R-9: the preview checks the customer's address with its reminder contacts (getReminderEmails()), the recipients creation gives the reminder.
// 20261126 ASR Review GPT-F-2: atomic() runs its change through db_checked(), so a statement the database rejects in the legacy booking or creation code throws and the change is rolled back.
//              In a page request (the run button) db_modify() would otherwise end the request there, before the other candidates.
// 20261126 ASR Review GPT-G-4: bookPrevious() checks the groups with reminder_posting_groups(), as both bogfor_rykker() copies do.
//              A group without a row in the reminder's fiscal year stops the run with the same message, which names the groups and the year.
// 20261126 ASR Review AR2-1: jobs() says when a job's reminder order is gone (deleted), so the page shows it without a link to the reminder.
// 20261127 ASR Review GPT-H-1: send() holds the customer's dispatch lock (reminder_dispatch_locked()) from its legal check until the job is saved as sent.
//              Another reminder for the same invoice sent at the same time is checked after this one counts.

require_once __DIR__ . '/reminderCreate.php';

/** PostgreSQL scheduler. Legacy creation/rendering uses the caller's already authenticated tenant connection. */
final class ReminderAutomation
{
    private $connection;
    private string $binding;
    private string $today;

    /** @param mixed $connection Tenant PostgreSQL connection used by the legacy DB helpers too. */
    public function __construct($connection, string $ledger, string $root, string $today = '')
    {
        $this->connection = $connection;
        $actual = $this->rows('SELECT current_database() AS name')[0]['name'];
        if ($actual !== $ledger || $connection !== ($GLOBALS['connection'] ?? null)) {
            throw new RuntimeException('Forkert regnskabsforbindelse.');
        }
        $this->binding = hash('sha256', gethostname() . '|' . realpath($root) . '|' . $ledger);
        $this->today = $today ?: reminder_today();
    }

    /** @return array<int, array<string, mixed>> Parameterized query results; failures never count as an empty plan. */
    public function rows(string $sql, array $params = array()): array
    {
        $result = pg_query_params($this->connection, $sql, $params);
        if ($result === false) throw new RuntimeException('Rykkerkørslen kunne ikke gemmes. Se serverloggen.');
        return pg_num_fields($result) ? (pg_fetch_all($result) ?: array()) : array();
    }

    /** @return bool Tables are installed, without creating anything. */
    public function installed(): bool
    {
        return $this->rows("SELECT to_regclass('reminder_automation_jobs') IS NOT NULL AND
            to_regclass('reminder_automation_config') IS NOT NULL AND to_regclass('reminder_automation_events') IS NOT NULL AS ok")[0]['ok'] === 't';
    }

    /** @return array{mode: string, armed: bool, last_run: ?string, last_error: string} Missing/copied configuration is disabled. */
    public function config(): array
    {
        $row = $this->installed() ? ($this->rows('SELECT * FROM reminder_automation_config WHERE id=1')[0] ?? array()) : array();
        return array('mode' => $row['mode'] ?? 'off', 'armed' => isset($row['binding']) && hash_equals($this->binding, $row['binding']),
            'last_run' => $row['last_run'] ?? null, 'last_error' => $row['last_error'] ?? '');
    }

    /** @return void Explicitly bind opt-in to this installation and ledger. Called only after permission/CSRF checks. */
    public function configure(string $mode, string $actor): void
    {
        if (!in_array($mode, array('off', 'approval', 'automatic'), true)) throw new InvalidArgumentException('Ugyldig indstilling.');
        $this->atomic(function () use ($mode, $actor) {
            $this->rows("INSERT INTO reminder_automation_config(id,mode,binding,actor) VALUES(1,$1,$2,$3)
                ON CONFLICT(id) DO UPDATE SET mode=excluded.mode,binding=excluded.binding,actor=excluded.actor,updated_at=current_timestamp",
                array($mode, $mode === 'off' ? '' : $this->binding, $actor));
            // Turning automatic sending off or requiring approval also holds already queued drafts.
            if ($mode !== 'automatic') $this->rows("UPDATE reminder_automation_jobs SET state='approval' WHERE state='queued'");
            $this->event(null, 'configure', $actor, $mode);
        });
    }

    /** @return void Refuse background work unless explicitly enabled here. */
    private function enabled(bool $lock = false): void
    {
        if ($lock) $this->rows('SELECT id FROM reminder_automation_config WHERE id=1 FOR UPDATE');
        $config = $this->config();
        if (!$config['armed'] || $config['mode'] === 'off') throw new RuntimeException('Automatiske rykkere er ikke aktiveret i dette regnskab på denne installation.');
    }

    /**
     * @return mixed Atomic change, with savepoints only for callers that already own a transaction (tests).
     *               A statement the database rejects in legacy code (db_modify(), db_select()) throws (db_checked()) and rolls it back.
     */
    private function atomic(callable $callback)
    {
        $nested = pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE;
        $this->rows($nested ? 'SAVEPOINT reminder_auto' : 'BEGIN');
        try {
            $result = function_exists('db_checked') ? db_checked($callback) : $callback();
            if (pg_transaction_status($this->connection) === PGSQL_TRANSACTION_INERROR) throw new RuntimeException('Rykkerkørslen er rullet tilbage.');
            $this->rows($nested ? 'RELEASE SAVEPOINT reminder_auto' : 'COMMIT');
            return $result;
        } catch (Throwable $e) {
            $this->rows($nested ? 'ROLLBACK TO SAVEPOINT reminder_auto' : 'ROLLBACK');
            throw $e;
        }
    }

    /** @return void Append an audit event; never delete reminders or previous events. */
    private function event(?int $id, string $action, string $actor, string $detail = ''): void
    {
        $this->rows('INSERT INTO reminder_automation_events(order_id,action,actor,detail) VALUES($1,$2,$3,$4)', array($id, $action, $actor, $detail));
    }

    /** @return array{first: int, second: int, third: int} Calendar days from the existing reminder settings. */
    private function days(): array
    {
        $r = $this->rows("SELECT box5,box6,box7 FROM grupper WHERE art='DIV' AND kodenr='4' ORDER BY id LIMIT 1")[0] ?? array();
        return array('first' => max(0, (int)($r['box5'] ?? 0)), 'second' => max(0, (int)($r['box6'] ?? 0)), 'third' => max(0, (int)($r['box7'] ?? 0)));
    }

    /**
     * @param int $account Only this debtor (adresser.id); 0 = every debtor.
     * @return array<int, array{account: int, previous: int, name: string, level: int, reason: string}> Read-only candidates, including held accounts.
     */
    public function preview(int $account = 0): array
    {
        $days = $this->days();
        $cutoff = reminder_date_add($this->today, -$days['first']);
        $out = array();
        $accounts = $this->rows("SELECT DISTINCT a.id,a.firmanavn FROM adresser a JOIN openpost op ON op.konto_id=a.id
            WHERE a.art='D' AND op.udlignet='0' AND op.amount>0 AND op.forfaldsdate<$1 AND ($2::int=0 OR a.id=$2::int)
            AND NOT EXISTS(SELECT 1 FROM ordrelinjer l JOIN ordrer r ON r.id=l.ordre_id
                WHERE l.enhed=cast(op.id AS text) AND r.art IN ('R1','R2','R3') AND coalesce(r.betalt,'')<>'on')
            AND NOT EXISTS(SELECT 1 FROM ordrer r WHERE r.id=op.refnr AND r.art IN ('R1','R2','R3')) ORDER BY a.id", array($cutoff, $account));
        foreach ($accounts as $a) {
            $out[] = array('account' => (int)$a['id'], 'previous' => 0, 'name' => $a['firmanavn'], 'level' => 1,
                'reason' => $this->accountReason((int)$a['id'], $cutoff));
        }
        if (!$this->installed()) return $out;
        // Old manual reminders are not adopted: their sending date/approval is unknown.
        $previous = $this->rows("SELECT r.*,j.sent_date FROM reminder_automation_jobs j JOIN ordrer r ON r.id=j.order_id
            WHERE j.state='sent' AND r.art IN ('R1','R2') AND coalesce(r.betalt,'')<>'on' AND ($1::int=0 OR r.konto_id=$1::int)
            AND NOT EXISTS(SELECT 1 FROM ordrer n WHERE n.konto_id=r.konto_id AND n.ordrenr=r.ordrenr AND n.art>r.art AND n.art IN ('R2','R3'))
            AND NOT EXISTS(SELECT 1 FROM reminder_automation_jobs n WHERE n.previous_id=r.id) ORDER BY r.id", array($account));
        foreach ($previous as $r) {
            $delay = $r['art'] === 'R1' ? $days['second'] : $days['third'];
            if (!$r['sent_date'] || reminder_date_add($r['sent_date'], max(10, $delay)) >= $this->today) continue;
            $reason = $this->deliveryReason((int)$r['id']);
            if ($reason === '') $reason = reminder_escalation_skip((int)$r['id'], true, $this->today);
            $out[] = array('account' => (int)$r['konto_id'], 'previous' => (int)$r['id'], 'name' => $r['firmanavn'],
                'level' => (int)substr($r['art'], 1) + 1, 'reason' => $reason);
        }
        return $out;
    }

    /** @return string Reason a debtor needs manual handling; initial test release supports ordinary DKK e-mail invoices. */
    private function accountReason(int $account, string $cutoff): string
    {
        $a = $this->rows("SELECT a.email,g.box3 AS currency FROM adresser a LEFT JOIN grupper g ON g.art='DG' AND cast(g.kodenr AS text)=cast(a.gruppe AS text) WHERE a.id=$1", array($account))[0] ?? array();
        if (!$a || !$this->validEmail(getReminderEmails($account, (string)$a['email']))) return 'Kunden mangler en gyldig mailadresse.';
        if (!isset($a['currency']) || !in_array(strtoupper(trim($a['currency'])), array('', 'DKK'), true)) return 'Kontrollér kundegruppe og valuta manuelt (automatik understøtter DKK).';
        if ($this->rows("SELECT 1 FROM openpost WHERE konto_id=$1 AND udlignet='0' AND amount<0 LIMIT 1", array($account))) {
            return 'Der er en åben betaling eller kreditnota. Udlign posterne først.';
        }
        $protection = stripe_dunning_openpost_sql('op');
        $open = $this->rows("SELECT op.*,($protection) AS allowed,o.status,o.udskriv_til,o.art FROM openpost op
            LEFT JOIN ordrer o ON o.konto_id=op.konto_id AND o.fakturanr=op.faktnr AND o.art='DO'
            WHERE op.konto_id=$1 AND op.udlignet='0' AND op.amount>0 AND op.forfaldsdate<$2
            AND NOT EXISTS(SELECT 1 FROM ordrelinjer l JOIN ordrer r ON r.id=l.ordre_id WHERE l.enhed=cast(op.id AS text)
                AND r.art IN ('R1','R2','R3') AND coalesce(r.betalt,'')<>'on')", array($account, $cutoff));
        if (!$open) return 'Ingen forfaldne fakturaer.';
        foreach ($open as $op) {
            if ($op['allowed'] !== 't') return 'Betaling er registreret eller under behandling.';
            if ((int)$op['status'] < 3 || !in_array($op['udskriv_til'], array('email', 'Link', 'Stripe'), true)) {
                return 'Fakturaen kræver manuel kontrol af bogføring og mailtype.';
            }
            if (!in_array(strtoupper(trim((string)$op['valuta'])), array('', 'DKK'), true)) return 'Valutaen kræver manuel behandling.';
        }
        return '';
    }

    /** @return bool All recipients must be valid; preserve the existing multi-recipient convention. */
    private function validEmail(string $email): bool
    {
        $parts = preg_split('/[;,]/', $email);
        foreach ($parts as $part) {
            if (!filter_var(trim($part), FILTER_VALIDATE_EMAIL)) return false;
        }
        return count($parts) > 0;
    }

    /** @return string Recheck live debt, mail settings and payment holds immediately before sending/escalating. */
    public function deliveryReason(int $id): string
    {
        $order = $this->rows('SELECT * FROM ordrer WHERE id=$1', array($id))[0] ?? null;
        if (!$order || !in_array($order['art'], array('R1', 'R2', 'R3'), true)) return 'Rykkeren findes ikke længere.';
        if ($order['betalt'] === 'on' || trim((string)$order['felt_5']) !== '') return 'Rykkeren er afsluttet eller sendt til inkasso.';
        if (!$this->validEmail((string)$order['email']) || $order['mail_fakt'] !== 'on') return 'Kontrollér mailadresse og Send pr. mail på rykkeren.';
        if (!in_array(strtoupper(trim((string)$order['valuta'])), array('', 'DKK'), true)) return 'Valutaen kræver manuel behandling.';
        if ($this->rows("SELECT 1 FROM openpost WHERE konto_id=$1 AND udlignet='0' AND amount<0 LIMIT 1", array($order['konto_id']))) {
            return 'Der er en åben betaling eller kreditnota. Udlign posterne først.';
        }
        $protection = stripe_dunning_openpost_sql('op');
        $posts = $this->rows("SELECT DISTINCT op.*,($protection) AS allowed FROM ordrelinjer l JOIN openpost op ON l.enhed=cast(op.id AS text)
            WHERE l.ordre_id=$1 AND op.konto_id=$2 AND op.amount>0", array($id, $order['konto_id']));
        $unpaid = $paid = false;
        foreach ($posts as $op) {
            if (reminder_openpost_paid($op)) { $paid = true; continue; }
            if ($op['allowed'] !== 't') return 'En faktura er under betaling. Kontrollér betalingen først.';
            $unpaid = true;
        }
        if (!$unpaid) return 'Alle fakturaer er betalt eller rykkeren mangler fakturaer.';
        if ($paid) return 'En faktura på rykkeren er betalt. Kontrollér kladdens indhold og renter manuelt.';
        return reminder_legal_check($id, $this->today);
    }

    /** @return array<string, mixed>|null Tracked order, or null for an ordinary manual reminder. */
    public function job(int $id): ?array
    {
        return $this->installed() ? ($this->rows('SELECT * FROM reminder_automation_jobs WHERE order_id=$1', array($id))[0] ?? null) : null;
    }

    /**
     * SQL predicate: the job's newest sending attempt is unresolved. send() logs 'sending' when it claims a job, and approve()
     * logs 'approve' only after the mail server's log was checked for a job with such an attempt, so an attempt without a
     * later approval may have reached the customer, whatever the job's state is now (a failed job can be paused).
     *
     * @param string $alias Trusted SQL alias for reminder_automation_jobs.
     * @return string
     */
    private function uncertainSql(string $alias): string
    {
        return "($alias.state<>'sent' AND EXISTS(SELECT 1 FROM reminder_automation_events s WHERE s.order_id=$alias.order_id AND s.action='sending'
            AND NOT EXISTS(SELECT 1 FROM reminder_automation_events a WHERE a.order_id=s.order_id AND a.action='approve' AND a.id>s.id)))";
    }

    /**
     * @return array<int, array<string, mixed>> Recent work and durable delivery outcomes for the operator.
     *   uncertain: 't' when an attempt to send it is unresolved (uncertainSql()); attempted_day: Y-m-d of the last attempt (Copenhagen);
     *   deleted: 't' when the reminder order is gone (deleted after the job was made), 'f' otherwise.
     */
    public function jobs(): array
    {
        return $this->rows("SELECT j.*,o.fakturanr,o.firmanavn," . $this->uncertainSql('j') . " AS uncertain,
            cast(j.attempted_at AT TIME ZONE 'Europe/Copenhagen' AS date) AS attempted_day,(o.id IS NULL) AS deleted
            FROM reminder_automation_jobs j LEFT JOIN ordrer o ON o.id=j.order_id
            ORDER BY j.created_at DESC,j.order_id DESC LIMIT 200");
    }

    /** @return void Approve/retry explicitly; an uncertain sending attempt must be checked before retrying, also after a pause. */
    public function approve(int $id, string $actor, bool $checkedUncertain = false): void
    {
        $this->enabled();
        $this->atomic(function () use ($id, $actor, $checkedUncertain) {
            $this->enabled(true);
            $row = $this->rows('SELECT * FROM reminder_automation_jobs WHERE order_id=$1 FOR UPDATE', array($id))[0] ?? null;
            if (!$row || $row['state'] === 'sent') throw new RuntimeException('Rykkeren er allerede sendt eller findes ikke.');
            // 20261126 ASR Review GPT-R-5: the attempt, not only the state 'failed', asks for the check (a paused failed job is 'held')
            $uncertain = in_array($row['state'], array('sending', 'failed'), true)
                || $this->rows('SELECT ' . $this->uncertainSql('j') . ' AS u FROM reminder_automation_jobs j WHERE j.order_id=$1', array($id))[0]['u'] === 't';
            if ($uncertain && (!$checkedUncertain || ($row['state'] === 'sending' && strtotime($row['attempted_at']) > time() - 900))) {
                throw new RuntimeException('Afsendelsen er uafklaret. Vent mindst 15 minutter, og kontrollér mailserverens log før nyt forsøg.');
            }
            $reason = $this->deliveryReason($id);
            if ($reason !== '') throw new RuntimeException($reason);
            $this->rows("UPDATE reminder_automation_jobs SET state='queued',error='',actor=$2 WHERE order_id=$1", array($id, $actor));
            $this->event($id, 'approve', $actor, $row['state']);
        });
    }

    /** @return void Hold a queued/draft or failed reminder without deleting any accounting record; an uncertain attempt stays uncertain (approve()). */
    public function hold(int $id, string $actor): void
    {
        $this->atomic(function () use ($id, $actor) {
            $changed = $this->rows("UPDATE reminder_automation_jobs SET state='held',actor=$2 WHERE order_id=$1
                AND state IN ('approval','queued','failed','held') RETURNING order_id", array($id, $actor));
            if (!$changed) throw new RuntimeException('Rykkeren er sendt eller under afsendelse.');
            $this->event($id, 'hold', $actor);
        });
    }

    /**
     * Date a tracked reminder that has not gone out yet on the day it is sent. Its date (ordredate, levdate, fakturadate) is
     * the day it was drafted, and its payment deadline follows that date (stripe_reminder_deadline(): the date plus its
     * payment days, used by the link mail, the PDF, the waiver and the closing warning), as does the letter's date. A draft
     * approved later than it was made would otherwise go out with a deadline that has passed. rykkerprint() calls it before
     * it renders a tracked reminder for sending, so the PDF and the mail agree, and the date stays when it is resent or
     * printed again. Only for a job send() can still claim (approval, queued), in a ledger where the flow is on here, and a
     * reminder that is not booked (status < 3); its interest lines are kept (calculated to the day it was drafted).
     *
     * @param int $id ordrer.id of the reminder.
     * @param string $actor Audit event 'dated' with the old and the new date.
     * @return void
     */
    public function dateForDispatch(int $id, string $actor): void
    {
        $config = $this->config();
        if (!$config['armed'] || $config['mode'] === 'off') return;
        $this->atomic(function () use ($id, $actor) {
            $job = $this->rows('SELECT state FROM reminder_automation_jobs WHERE order_id=$1 FOR UPDATE', array($id))[0] ?? null;
            if (!$job || !in_array($job['state'], array('approval', 'queued'), true)) return;
            $order = $this->rows("SELECT fakturadate FROM ordrer WHERE id=$1 AND art IN ('R1','R2','R3') AND status<3 FOR UPDATE", array($id))[0] ?? null;
            if (!$order || (string)$order['fakturadate'] >= $this->today) return;
            $this->rows('UPDATE ordrer SET ordredate=cast($2 AS date),levdate=cast($2 AS date),fakturadate=cast($2 AS date) WHERE id=$1', array($id, $this->today));
            $this->event($id, 'dated', $actor, $order['fakturadate'] . ' ' . $this->today);
        });
    }

    /**
     * Record a delivery the operator confirmed in the mail server's log, without sending anything: an uncertain attempt
     * (uncertainSql(); e.g. the process stopped after the mail server took the reminder, before 'sent' was saved) becomes
     * 'sent' on the day it went out. A link reminder is marked sent as after its mail (stripe_reminders: status, and
     * mailed_at when it has none), and the day goes in reminder_dispatch, so the next level, the 10 days of renteloven
     * § 9 b and the fee follow it. The other way back is approve() with the check that it was not sent.
     *
     * @param int $id ordrer.id of the reminder.
     * @param string $actor Who confirmed it (audit event 'confirm_sent').
     * @param string $date Y-m-d the reminder went out, from the day of the attempt to today.
     * @param bool $checked The operator declared that the mail server's log shows the reminder was sent.
     * @return string The channel recorded: link or classic.
     */
    public function confirmSent(int $id, string $actor, string $date, bool $checked): string
    {
        if (!$checked || $date === '') {
            throw new RuntimeException(reminder_txt(8221, 'Bekræft, at mailserverens log viser, at rykkeren blev sendt, og angiv dagen.'));
        }
        return $this->atomic(function () use ($id, $actor, $date) {
            $row = $this->rows("SELECT state,attempted_at,cast(attempted_at AT TIME ZONE 'Europe/Copenhagen' AS date) AS attempted_day
                FROM reminder_automation_jobs WHERE order_id=$1 FOR UPDATE", array($id))[0] ?? null;
            $uncertain = $row && $this->rows('SELECT ' . $this->uncertainSql('j') . ' AS u FROM reminder_automation_jobs j WHERE j.order_id=$1', array($id))[0]['u'] === 't';
            if (!$uncertain) throw new RuntimeException(reminder_txt(8223, 'Kun en rykker, hvis afsendelse er uafklaret, kan registreres som sendt.'));
            if ($row['state'] === 'sending' && strtotime($row['attempted_at']) > time() - 900) {
                throw new RuntimeException(reminder_txt(8225, 'Afsendelsen kan stadig være i gang. Vent mindst 15 minutter efter forsøget, og kontrollér mailserverens log.'));
            }
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $first = (string)$row['attempted_day'];
            if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < $first || $date > $this->today) {
                throw new RuntimeException(reminder_txt(8222, 'Dagen, rykkeren blev sendt, skal ligge fra {forsoeg} til {idag}.',
                    array('forsoeg' => reminder_fmt_date($first), 'idag' => reminder_fmt_date($this->today))));
            }
            require_once __DIR__ . '/formFuncIncludes/reminderMailDelivery.php';
            // The tracked transport sends a link reminder only as the link mail (no fallback), any other as the classic PDF mail.
            $link = formReminderLinkRow($id);
            $channel = $link !== null ? 'link' : 'classic';
            if ($link !== null) {
                $order = $this->rows('SELECT email FROM ordrer WHERE id=$1', array($id))[0] ?? array();
                stripe_reminder_mark_sent($id, 'link', (string)($order['email'] ?? ''));
                if (trim((string)$link['mailed_at']) === '') {
                    $this->rows('UPDATE stripe_reminders SET mailed_at=cast($2 AS timestamp) WHERE rykker_id=$1', array($id, $date . ' 00:00:00'));
                }
            }
            reminder_dispatch_record($id, $channel, $date);
            $this->rows("UPDATE reminder_automation_jobs SET state='sent',sent_date=cast($2 AS date),sent_at=NULL,channel=$3,error='',actor=$4
                WHERE order_id=$1", array($id, $date, $channel, $actor));
            $this->event($id, 'confirm_sent', $actor, $date . ' ' . $channel);
            return $channel;
        });
    }

    /**
     * Durable claim before SMTP. A crash leaves 'sending'; it is never retried automatically.
     * The customer's dispatch lock (reminder_dispatch_locked()) is held from the legal check (deliveryReason()) until the job
     * is saved as sent, as the fees per invoice count an automatic reminder only once it is 'sent'.
     * @return array{sent: bool, result: mixed, message: string, channel: string}
     */
    public function send(int $id, callable $transport, string $actor): array
    {
        return reminder_dispatch_locked($id, function () use ($id, $transport, $actor): array {
            return $this->sendLocked($id, $transport, $actor);
        });
    }

    /** @return array{sent: bool, result: mixed, message: string, channel: string} send() under the customer's dispatch lock. */
    private function sendLocked(int $id, callable $transport, string $actor): array
    {
        $this->enabled();
        $reason = $this->deliveryReason($id);
        if ($reason !== '') {
            $this->rows("UPDATE reminder_automation_jobs SET state='held',error=$2 WHERE order_id=$1 AND state IN ('queued','approval')", array($id, $reason));
            return array('sent' => false, 'result' => false, 'message' => $reason, 'channel' => '');
        }
        $claimed = $this->atomic(function () use ($id, $actor) {
            $this->enabled(true);
            $claimed = $this->rows("UPDATE reminder_automation_jobs SET state='sending',attempted_at=current_timestamp,actor=$2,error=''
                WHERE order_id=$1 AND state IN ('queued','approval') RETURNING order_id", array($id, $actor));
            if ($claimed) $this->event($id, 'sending', $actor);
            return (bool)$claimed;
        });
        if (!$claimed) return array('sent' => false, 'result' => false, 'message' => 'Rykkeren er sendt, sat på pause eller afventer kontrol.', 'channel' => '');
        // No automatic fallback on SMTP failure: delivery may have succeeded before the connection failed.
        try {
            $result = $transport();
        } catch (Throwable $e) {
            error_log('Reminder delivery ' . $id . ': ' . $e->getMessage());
            $result = array('sent' => false, 'result' => false, 'message' => 'Mailafsendelsen er uafklaret. Kontrollér mailserverens log før nyt forsøg.', 'channel' => '');
        }
        $this->atomic(function () use ($id, $result, $actor) {
            $state = !empty($result['sent']) ? 'sent' : 'failed';
            $this->rows("UPDATE reminder_automation_jobs SET state=cast($2 AS text),error=$3,channel=$4,
                sent_at=CASE WHEN cast($2 AS text)='sent' THEN current_timestamp ELSE NULL END,
                sent_date=CASE WHEN cast($2 AS text)='sent' THEN cast($5 AS date) ELSE NULL END WHERE order_id=$1 AND state='sending'",
                array($id, $state, $result['message'] ?? '', $result['channel'] ?? '', $this->today));
            $this->event($id, $state, $actor, $result['message'] ?? '');
        });
        return $result;
    }

    /** @return void Use the existing posting implementation before escalation, atomically with the next draft. */
    private function bookPrevious(int $id): void
    {
        $order = $this->rows('SELECT * FROM ordrer WHERE id=$1 FOR UPDATE', array($id))[0];
        if ((int)$order['status'] >= 4) return;
        require_once __DIR__ . '/openpost.php';
        $previousYear = $GLOBALS['regnaar'] ?? null;
        if (!stripe_reminder_carried($id)) {
            // Reject missing/closed fiscal years and invalid posting accounts before entering the legacy writer.
            $year = $this->rows("SELECT kodenr,box5 FROM grupper WHERE art='RA' AND
                (box2 || lpad(box1,2,'0')) <= $1 AND (box4 || lpad(box3,2,'0')) >= $1 ORDER BY id", array(substr(str_replace('-', '', $order['fakturadate']), 0, 6)));
            if (count($year) !== 1 || trim((string)$year[0]['box5']) !== 'on') throw new RuntimeException('Regnskabsåret skal kontrolleres før bogføring af rykkergebyr.');
            // 20261126 ASR Review GPT-G-4: a group without a row in the reminder's fiscal year is refused with the message Bogfør and "Ryk alle" give
            $groups = reminder_posting_groups($id, (int)$year[0]['kodenr']);
            if ($groups['refusal'] !== '') throw new RuntimeException($groups['refusal']);
            // 20261126 ASR Review GPT-R-1: the groups of the reminder's fiscal year (one grupper row per year), which bogfor_rykker() posts with
            $invalid = $this->rows("SELECT l.id FROM ordrelinjer l LEFT JOIN varer v ON v.id=l.vare_id
                LEFT JOIN grupper g ON g.art='VG' AND cast(g.kodenr AS text)=cast(v.gruppe AS text) AND cast(g.fiscal_year AS text)=cast($2 AS text)
                WHERE l.ordre_id=$1 AND l.vare_id>0 AND (g.id IS NULL OR g.box8='on' OR coalesce(g.box4,'') !~ '^[0-9]+$')", array($id, $year[0]['kodenr']));
            $debtorGroup = $this->rows("SELECT g.box2 FROM adresser a JOIN grupper g ON g.art='DG' AND cast(g.kodenr AS text)=cast(a.gruppe AS text)
                AND cast(g.fiscal_year AS text)=cast($2 AS text) WHERE a.id=$1", array($order['konto_id'], $year[0]['kodenr']));
            if ($invalid || !$debtorGroup || !ctype_digit(trim((string)$debtorGroup[0]['box2']))) throw new RuntimeException('Kontrollér bogføringskonti for kundegruppe og rykkergebyr.');
            $accounts = $this->rows("SELECT DISTINCT g.box4 AS account FROM ordrelinjer l JOIN varer v ON v.id=l.vare_id
                JOIN grupper g ON g.art='VG' AND cast(g.kodenr AS text)=cast(v.gruppe AS text) AND cast(g.fiscal_year AS text)=cast($2 AS text)
                WHERE l.ordre_id=$1 AND l.vare_id>0", array($id, $year[0]['kodenr']));
            $accounts[] = array('account' => $debtorGroup[0]['box2']);
            foreach ($accounts as $account) {
                if (!$this->rows("SELECT id FROM kontoplan WHERE kontonr=$1 AND regnskabsaar=$2 AND kontotype IN ('D','S') AND coalesce(lukket,'')<>'on'",
                    array(trim($account['account']), $year[0]['kodenr']))) {
                    throw new RuntimeException('En bogføringskonto mangler eller er lukket i rykkerens regnskabsår.');
                }
            }
            $GLOBALS['regnaar'] = $year[0]['kodenr'];
        }
        try {
            bogfor_rykker($id, false);
        } finally {
            $GLOBALS['regnaar'] = $previousYear;
        }
        if ((int)$this->rows('SELECT status FROM ordrer WHERE id=$1', array($id))[0]['status'] !== 4) throw new RuntimeException('Det forrige rykkergebyr kunne ikke bogføres.');
    }

    /**
     * Serialize a whole run. Creation and queue insertion commit together; mail happens only afterwards.
     * @param callable $sender Receives an order id; must call formSendReminderEntry()/send() through the shared renderer.
     * @return array{created: int, sent: int, held: int, errors: array<int, string>}
     */
    public function run(callable $sender, string $actor): array
    {
        $this->enabled();
        $lock = $this->rows("SELECT pg_try_advisory_lock(hashtext(current_database()),hashtext('reminder_automation')) AS ok")[0]['ok'];
        if ($lock !== 't') throw new RuntimeException('En rykkerkørsel er allerede i gang.');
        $counts = array('created' => 0, 'sent' => 0, 'held' => 0, 'errors' => array());
        try {
            foreach ($this->preview() as $candidate) {
                if ($candidate['reason'] !== '') { $counts['held']++; continue; }
                try {
                    $made = $this->atomic(function () use ($candidate, $actor) {
                        $this->enabled(true);
                        reminder_create_lock();
                        // Replan the candidate's own account under the same lock as manual creation, so a new manual reminder wins safely.
                        $found = false;
                        foreach ($this->preview($candidate['account']) as $fresh) {
                            if ($fresh['account'] === $candidate['account'] && $fresh['previous'] === $candidate['previous'] && $fresh['reason'] === '') $found = true;
                        }
                        if (!$found) return 0;
                        if ($candidate['previous']) $this->bookPrevious($candidate['previous']);
                        $cutoff = reminder_date_add($this->today, -$this->days()['first']);
                        $created = reminder_create_batch($candidate['previous'] ? array() : array($candidate['account']),
                            $candidate['previous'] ? array($candidate['previous']) : array(), $this->today, $cutoff, $actor);
                        if (!$created['ids']) throw new RuntimeException(implode(' ', $created['notes']) ?: 'Ingen rykker blev oprettet.');
                        foreach ($created['ids'] as $id) {
                            $mode = $this->config()['mode'];
                            $this->rows("UPDATE ordrer SET mail_fakt='on' WHERE id=$1", array($id));
                            $this->rows('INSERT INTO reminder_automation_jobs(order_id,previous_id,mode,state,actor) VALUES($1,$2,$3,$4,$5)',
                                array($id, $candidate['previous'] ?: null, $mode, $mode === 'automatic' ? 'queued' : 'approval', $actor));
                            $this->event($id, 'created', $actor, implode(' ', $created['notes']));
                        }
                        return count($created['ids']);
                    });
                    $counts['created'] += $made;
                } catch (Throwable $e) {
                    $counts['errors'][] = $candidate['name'] . ': ' . $e->getMessage();
                }
            }
            foreach ($this->rows("SELECT order_id FROM reminder_automation_jobs WHERE state='queued' ORDER BY order_id") as $job) {
                $id = (int)$job['order_id'];
                try {
                    $this->enabled();
                    $sender($id);
                    if ($this->job($id)['state'] === 'sent') $counts['sent']++;
                    else $counts['errors'][] = 'Rykker ' . $id . ': ' . ($this->job($id)['error'] ?: 'Afsendelsen kræver kontrol.');
                } catch (Throwable $e) {
                    $this->rows("UPDATE reminder_automation_jobs SET state='failed',error=$2 WHERE order_id=$1 AND state='queued'", array($id, $e->getMessage()));
                    $counts['errors'][] = 'Rykker ' . $id . ': ' . $e->getMessage();
                }
            }
            // The last level has no successor to trigger booking; finish its confirmed fee here.
            foreach ($this->rows("SELECT r.id FROM reminder_automation_jobs j JOIN ordrer r ON r.id=j.order_id
                WHERE j.state='sent' AND r.art='R3' AND r.status<4") as $last) {
                try {
                    $this->atomic(function () use ($last, $actor) {
                        $this->enabled(true);
                        $this->bookPrevious((int)$last['id']);
                        $this->event((int)$last['id'], 'booked', $actor);
                        $this->rows("UPDATE reminder_automation_jobs SET error='' WHERE order_id=$1", array($last['id']));
                    });
                } catch (Throwable $e) {
                    $this->rows('UPDATE reminder_automation_jobs SET error=$2 WHERE order_id=$1', array($last['id'], $e->getMessage()));
                    $counts['errors'][] = 'Rykker ' . $last['id'] . ': ' . $e->getMessage();
                }
            }
            $this->rows('UPDATE reminder_automation_config SET last_run=current_timestamp,last_error=$1 WHERE id=1', array(implode("\n", $counts['errors'])));
        } finally {
            $this->rows("SELECT pg_advisory_unlock(hashtext(current_database()),hashtext('reminder_automation'))");
        }
        return $counts;
    }
}

/**
 * Bridge for legacy delivery helpers. Connection/db are injected by online.php (web) or reminder_due.php (CLI).
 * @return ReminderAutomation|null Unsupported DB engines retain the ordinary manual flow.
 */
function reminder_automation_service(): ?ReminderAutomation
{
    if (in_array($GLOBALS['db_type'] ?? '', array('mysql', 'mysqli'), true)) return null;
    return new ReminderAutomation($GLOBALS['connection'], (string)$GLOBALS['db'], dirname(__DIR__));
}

/** @return void Render and deliver one tracked reminder using the same form and mail code as manual sending. */
function reminder_automation_render_send(int $id): void
{
    require_once __DIR__ . '/formfunk.php';
    require_once __DIR__ . '/var2str.php';
    $level = ob_get_level();
    ob_start();
    try {
        $results = rykkerprint(array(), array($id), '0', '', '', $GLOBALS['regnaar'] ?? '', '', true);
        if (empty($results[$id])) throw new RuntimeException('Rykkerens PDF kunne ikke klargøres. Kontrollér formularen.');
    } finally {
        while (ob_get_level() > $level) ob_end_clean();
    }
}
