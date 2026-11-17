<?php
// includes/reminderAutomation.php — ver 5.0.0 — 2026.11.17
// Copyright (c) 2026 Danosoft ApS. GNU GPL version 2 or later.
// 20261117 CDX/ASR Opt-in reminder planning, approval and durable mail delivery for redesign.

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

    /** @return mixed Atomic change, with savepoints only for callers that already own a transaction (tests). */
    private function atomic(callable $callback)
    {
        $nested = pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE;
        $this->rows($nested ? 'SAVEPOINT reminder_auto' : 'BEGIN');
        try {
            $result = $callback();
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

    /** @return array<int, array{account: int, previous: int, name: string, level: int, reason: string}> Read-only candidates, including held accounts. */
    public function preview(): array
    {
        $days = $this->days();
        $cutoff = reminder_date_add($this->today, -$days['first']);
        $out = array();
        $accounts = $this->rows("SELECT DISTINCT a.id,a.firmanavn FROM adresser a JOIN openpost op ON op.konto_id=a.id
            WHERE a.art='D' AND op.udlignet='0' AND op.amount>0 AND op.forfaldsdate<$1
            AND NOT EXISTS(SELECT 1 FROM ordrelinjer l JOIN ordrer r ON r.id=l.ordre_id
                WHERE l.enhed=cast(op.id AS text) AND r.art IN ('R1','R2','R3') AND coalesce(r.betalt,'')<>'on')
            AND NOT EXISTS(SELECT 1 FROM ordrer r WHERE r.id=op.refnr AND r.art IN ('R1','R2','R3')) ORDER BY a.id", array($cutoff));
        foreach ($accounts as $a) {
            $out[] = array('account' => (int)$a['id'], 'previous' => 0, 'name' => $a['firmanavn'], 'level' => 1,
                'reason' => $this->accountReason((int)$a['id'], $cutoff));
        }
        if (!$this->installed()) return $out;
        // Old manual reminders are not adopted: their sending date/approval is unknown.
        $previous = $this->rows("SELECT r.*,j.sent_date FROM reminder_automation_jobs j JOIN ordrer r ON r.id=j.order_id
            WHERE j.state='sent' AND r.art IN ('R1','R2') AND coalesce(r.betalt,'')<>'on'
            AND NOT EXISTS(SELECT 1 FROM ordrer n WHERE n.konto_id=r.konto_id AND n.ordrenr=r.ordrenr AND n.art>r.art AND n.art IN ('R2','R3'))
            AND NOT EXISTS(SELECT 1 FROM reminder_automation_jobs n WHERE n.previous_id=r.id) ORDER BY r.id");
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
        if (!$a || !$this->validEmail((string)$a['email'])) return 'Kunden mangler en gyldig mailadresse.';
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

    /** @return array<int, array<string, mixed>> Recent work and durable delivery outcomes for the operator. */
    public function jobs(): array
    {
        return $this->rows("SELECT j.*,o.fakturanr,o.firmanavn FROM reminder_automation_jobs j LEFT JOIN ordrer o ON o.id=j.order_id
            ORDER BY j.created_at DESC,j.order_id DESC LIMIT 200");
    }

    /** @return void Approve/retry explicitly; an uncertain sending attempt must be checked before retrying. */
    public function approve(int $id, string $actor, bool $checkedUncertain = false): void
    {
        $this->enabled();
        $this->atomic(function () use ($id, $actor, $checkedUncertain) {
            $this->enabled(true);
            $row = $this->rows('SELECT * FROM reminder_automation_jobs WHERE order_id=$1 FOR UPDATE', array($id))[0] ?? null;
            if (!$row || $row['state'] === 'sent') throw new RuntimeException('Rykkeren er allerede sendt eller findes ikke.');
            if (in_array($row['state'], array('sending', 'failed'), true) && (!$checkedUncertain || ($row['state'] === 'sending' && strtotime($row['attempted_at']) > time() - 900))) {
                throw new RuntimeException('Afsendelsen er uafklaret. Vent mindst 15 minutter, og kontrollér mailserverens log før nyt forsøg.');
            }
            $reason = $this->deliveryReason($id);
            if ($reason !== '') throw new RuntimeException($reason);
            $this->rows("UPDATE reminder_automation_jobs SET state='queued',error='',actor=$2 WHERE order_id=$1", array($id, $actor));
            $this->event($id, 'approve', $actor, $row['state']);
        });
    }

    /** @return void Hold a queued/draft reminder without deleting any accounting record. */
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
     * Durable claim before SMTP. A crash leaves 'sending'; it is never retried automatically.
     * @return array{sent: bool, result: mixed, message: string, channel: string}
     */
    public function send(int $id, callable $transport, string $actor): array
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
            $invalid = $this->rows("SELECT l.id FROM ordrelinjer l LEFT JOIN varer v ON v.id=l.vare_id
                LEFT JOIN grupper g ON g.art='VG' AND cast(g.kodenr AS text)=cast(v.gruppe AS text)
                WHERE l.ordre_id=$1 AND l.vare_id>0 AND (g.id IS NULL OR g.box8='on' OR coalesce(g.box4,'') !~ '^[0-9]+$')", array($id));
            $debtorGroup = $this->rows("SELECT g.box2 FROM adresser a JOIN grupper g ON g.art='DG' AND cast(g.kodenr AS text)=cast(a.gruppe AS text) WHERE a.id=$1", array($order['konto_id']));
            if ($invalid || !$debtorGroup || !ctype_digit(trim((string)$debtorGroup[0]['box2']))) throw new RuntimeException('Kontrollér bogføringskonti for kundegruppe og rykkergebyr.');
            $accounts = $this->rows("SELECT DISTINCT g.box4 AS account FROM ordrelinjer l JOIN varer v ON v.id=l.vare_id
                JOIN grupper g ON g.art='VG' AND cast(g.kodenr AS text)=cast(v.gruppe AS text) WHERE l.ordre_id=$1 AND l.vare_id>0", array($id));
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
                        $this->rows('LOCK TABLE ordrer IN SHARE ROW EXCLUSIVE MODE');
                        // Replan under the same lock as manual creation, so a new manual reminder wins safely.
                        $found = false;
                        foreach ($this->preview() as $fresh) {
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
