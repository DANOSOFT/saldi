<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/PaymentRegistrationService.php --- ver 5.0.0 --- 2026-10-07 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Shared web/app payment registration service.
// 20261001 CDX/LH Validate all locked postings and lock the open bookkeeping year.
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.

require_once __DIR__ . '/legacy/LegacyEngine.php';
require_once __DIR__ . '/OpenPostService.php';
require_once __DIR__ . '/../../includes/alignOpenpostIncludes/alignGroup.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';

/** One journal posting path for app registrations and captured provider references. */
final class PaymentRegistrationService
{
    private EngineContext $context;
    private string $subject;
    private MobileSchema $store;
    private $registry;
    private ?Closure $cancelCreatedAttempt;

    public function __construct(EngineContext $context, string $subject, $registryConnection = null, ?callable $cancelCreatedAttempt = null)
    {
        if ($subject === '' || strlen($subject) > 255) {
            throw new InvalidArgumentException('Invalid payment subject');
        }
        $this->context = $context;
        $this->subject = $subject;
        $this->store = new MobileSchema($context->connection);
        $this->registry = $registryConnection;
        $this->cancelCreatedAttempt = $cancelCreatedAttempt === null ? null : Closure::fromCallable($cancelCreatedAttempt);
    }

    /** @return array{bank:array{kontonr:int,name:string}|null,kasse:array{kontonr:int,name:string}|null,mobilepay:array{kontonr:int,name:string}|null} */
    public static function accounts($connection, int $year): array
    {
        $store = new MobileSchema($connection);
        $result = ['bank' => null, 'kasse' => null, 'mobilepay' => null];
        foreach ($result as $method => $unused) {
            $name = $store->literal('account_' . $method);
            $rows = $store->rows("SELECT k.kontonr,k.beskrivelse FROM settings s JOIN kontoplan k
                ON k.kontonr::text=s.var_value AND k.regnskabsaar=" . (int)$year . "
                WHERE s.var_grp='mobile_betaling' AND s.var_name=$name AND s.user_id=0
                AND k.kontotype='S' AND COALESCE(trim(k.lukket),'')='' ORDER BY s.id DESC,k.id LIMIT 1");
            if ($rows) {
                $result[$method] = ['kontonr' => (int)$rows[0]['kontonr'], 'name' => (string)$rows[0]['beskrivelse']];
            }
        }
        return $result;
    }

    /** @return array{status:int,body:array{success:bool,message:string,data:array},headers:array<string,string>} */
    public function register(array $body): array
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('App registration owns its transaction');
        }
        Idempotency::clientId($body);
        return $this->perform($body, null);
    }

    /**
     * Book a captured reference once. Under an existing provider transaction, the caller commits
     * the returned registration together with its attempt/link state and then calls afterCommit().
     * @return array{status:int,body:array,headers:array<string,string>}
     */
    public function registerFromProvider(array $body, string $reference): array
    {
        if (!preg_match('/^saldi-[A-Za-z0-9-]+$/D', $reference) || strlen($reference) > 50) {
            throw new InvalidArgumentException('Invalid provider reference');
        }
        $body['method'] = 'mobilepay';
        $body['client_id'] = 'vipps:' . $reference;
        return $this->perform($body, $reference);
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    private function perform(array $body, ?string $reference): array
    {
        $id = OpenPostService::id(ifset($body, 'openpost_id'));
        $owned = pg_transaction_status($this->context->connection) === PGSQL_TRANSACTION_IDLE;
        if (!$owned && pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_INTRANS) {
            throw new LogicException('Registration requires a usable transaction');
        }
        if ($owned) {
            mobileTenantUpdates($this->context->connection);
            $this->store->execute('BEGIN');
        }
        try {
            $replay = new Idempotency($this->context->connection);
            if ($reference === null) {
                $cached = $replay->replay($this->subject, 'payment.register', $id, $body);
                if ($cached) {
                    $this->store->execute('COMMIT');
                    return $cached;
                }
            } else {
                $key = $this->store->literal($body['client_id']);
                $this->store->execute("SELECT pg_advisory_xact_lock(hashtextextended($key,0))");
                $cached = $this->store->rows("SELECT * FROM payment_registrations WHERE client_id=$key");
                if ($cached) {
                    $row = $cached[0];
                    if ((int)$row['openpost_id'] !== $id || (string)$row['amount'] !== self::amount(ifset($body, 'amount'))
                        || $row['transdate'] !== ifset($body, 'date')) {
                        throw new MobileT2Error('conflict',409,['reason'=>'client_id_reused']);
                    }
                    $rest = (new OpenPostService($this->context->connection))->detail($id)['rest'];
                    $result = $this->response($row, $rest, ['Idempotent-Replayed'=>'true']);
                    if ($owned) {
                        $this->store->execute('COMMIT');
                    }
                    return $result;
                }
            }
            $amount = self::amount(ifset($body,'amount'));
            $date = ifset($body,'date');
            $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d',$date) : false;
            // A shared row lock permits parallel postings but prevents closing/changing this year until commit.
            $years = $this->store->rows("SELECT box1,box2,box3,box4,box5 FROM grupper
                WHERE art='RA' AND kodenr=".(int)$this->context->regnaar." FOR SHARE");
            $month = (int)$parsed->format('Y') * 12 + (int)$parsed->format('n');
            if (!$years || trim((string)$years[0]['box5']) !== 'on'
                || $month < (int)$years[0]['box2'] * 12 + (int)$years[0]['box1']
                || $month > (int)$years[0]['box4'] * 12 + (int)$years[0]['box3']) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'outside_fiscal_year']);
            }
            $method = ifset($body,'method');
            if (!is_string($method) || !in_array($method,['bank','kasse','mobilepay'],true)) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_method']);
            }
            // Lock all links of the group first, including links anchored on another payment row.
            $anchor = $this->store->rows("SELECT * FROM openpost WHERE id=$id");
            if (!$anchor) {
                throw new MobileT2Error('not_found',404);
            }
            $customer = (int)$anchor[0]['konto_id'];
            $invoice = $this->store->literal((string)$anchor[0]['faktnr']);
            $links = $this->store->rows("SELECT l.id FROM payment_links l JOIN openpost p ON p.id=l.openpost_id
                WHERE p.konto_id=$customer AND p.faktnr=$invoice ORDER BY l.id FOR UPDATE OF l");
            $linkIds = implode(',',array_map('intval',array_column($links,'id')));
            if ($reference === null && $links && $this->store->rows("SELECT id FROM payment_attempts WHERE link_id IN ($linkIds)
                AND (state IN ('authorized','capturing') OR (state='captured' AND booking='none')) LIMIT 1")) {
                throw new MobileT2Error('conflict',409,['reason'=>'payment_in_progress']);
            }
            // Lock the persistent invoice anchor as well as all open rows; an empty settled group still serializes.
            $this->store->rows("SELECT id FROM openpost WHERE konto_id=$customer AND faktnr=$invoice ORDER BY id FOR UPDATE");
            $item = (new OpenPostService($this->context->connection))->detail($id);
            if ($item['type'] !== 'invoice'
                || trim((string)$item['faktnr']) === '' || str_contains((string)$item['faktnr'],';')) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'not_payable']);
            }
            if ($this->context->baseCurrency !== 'DKK' || !in_array(trim((string)$item['original_currency']),['DKK','-',''],true)) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'currency_not_supported']);
            }
            $collective = $this->store->rows("SELECT k.id FROM adresser a JOIN grupper g ON g.art='DG' AND g.kodenr=a.gruppe
                AND g.fiscal_year=".(int)$this->context->regnaar." JOIN kontoplan k ON k.kontonr::text=g.box2
                AND k.regnskabsaar=".(int)$this->context->regnaar." WHERE a.id=$customer AND a.art='D'");
            if (!$collective || !ctype_digit((string)$item['kontonr'])) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'not_payable']);
            }
            $group = $this->store->rows("SELECT * FROM openpost WHERE konto_id=$customer AND faktnr=$invoice AND COALESCE(udlignet,'0')<>'1' ORDER BY id");
            foreach ($group as $post) {
                if (!in_array(trim((string)$post['valuta']),['DKK','-',''],true)) {
                    throw new MobileT2Error('validation_failed',422,['reason'=>'currency_not_supported']);
                }
            }
            // Reporting detail is as of today; booking eligibility must include future-dated payments too.
            $payableRest = array_sum(array_map(static fn(array $row): int => self::minor(number_format((float)$row['amount'],2,'.','')), $group));
            if ($payableRest <= 0) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'not_payable']);
            }
            if (self::minor($amount) > $payableRest) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'amount_exceeds_rest']);
            }
            $account = self::accounts($this->context->connection, $this->context->regnaar)[$method];
            if (!$account) {
                throw new MobileT2Error('not_configured',422,['reason'=>'no_account']);
            }
            $registration = $this->engine(function () use ($date,$group,$amount,$account,$item,$id,$customer,$invoice,$body,$reference,$method): array {
                LegacyEngine::load('kladde');
                self::loadRecalculation();
                require_once __DIR__ . '/../../finans/kassekladde_includes/bilagNumber.php';
                if ($this->store->rows("SELECT to_regclass('moms_periode_luk') AS name")[0]['name'] !== null && check_periode_luk($date)) {
                    throw new MobileT2Error('validation_failed',422,['reason'=>'period_locked']);
                }
                // Serialize mobile voucher allocation, without extending a full-year recalculation lock into posting.
                $this->store->execute("SELECT pg_advisory_xact_lock(hashtext('saldi:mobile:payment:bilag'))");
                $journal = (int)$this->store->rows("INSERT INTO kladdeliste (kladdedate,bogfort,kladdenote,oprettet_af)
                    VALUES (".$this->store->literal($date).",'','Indbetaling via Saldi Mobile',".$this->store->literal($this->context->username).") RETURNING id")[0]['id'];
                $bilag = bilagNextNumberForJournal($journal);
                $project = count(array_unique(array_column($group,'projekt'))) === 1 ? (int)$group[0]['projekt'] : 0;
                $this->store->execute("INSERT INTO kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,faktura,projekt,afd,ansat,valuta,ordre_id,momsfri)
                    VALUES ($bilag,".$this->store->literal($date).",'Indbetaling via Saldi Mobile','F',".(int)$account['kontonr'].",'D',".(int)$item['kontonr'].",$amount,$journal,$invoice,$project,0,0,0,0,'on')");
                $error = kladde_bogfor($journal,'Indbetaling via Saldi Mobile','');
                if ($error !== null) {
                    throw new MobileT2Error('validation_failed',422,['reason'=>'posting_failed']);
                }
                $remaining = $this->store->rows("SELECT id,amount FROM openpost WHERE konto_id=$customer AND faktnr=$invoice AND COALESCE(udlignet,'0')<>'1' ORDER BY id FOR UPDATE");
                $sum = array_sum(array_map(static fn(array $row): int => self::minor(number_format((float)$row['amount'],2,'.','')), $remaining));
                if ($remaining && $sum === 0) {
                    openpost_align_group(array_map('intval',array_column($remaining,'id')),$date);
                }
                $aligned = !$this->store->rows("SELECT id FROM openpost WHERE konto_id=$customer AND faktnr=$invoice AND COALESCE(udlignet,'0')<>'1'");
                return $this->store->rows("INSERT INTO payment_registrations (client_id,source,openpost_id,konto_id,faktnr,amount,currency,method,account_kontonr,transdate,kladde_id,bilag,aligned,created_by)
                    VALUES (".$this->store->literal(($reference === null ? Idempotency::clientId($body) : $body['client_id'])).",".$this->store->literal($reference === null ? 'app':'payment_link').",$id,$customer,$invoice,$amount,'DKK',".$this->store->literal($method).",".(int)$account['kontonr'].",".$this->store->literal($date).",$journal,$bilag,".($aligned?'true':'false').",".$this->store->literal($this->subject).") RETURNING *")[0];
            });
            if ($reference === null && $links) {
                $this->store->execute("UPDATE payment_links SET state='superseded' WHERE id IN ($linkIds) AND state='active'");
            }
            $rest = (new OpenPostService($this->context->connection))->detail($id)['rest'];
            $result = $this->response($registration,$rest);
            if ($reference === null) {
                $replay->store($this->subject,'payment.register',$id,$body,201,$result['body'],'payment_registration',(int)$registration['id']);
            }
            if ($owned) {
                $this->store->execute('COMMIT');
            }
        } catch (Throwable $error) {
            if ($owned) {
                $this->store->execute('ROLLBACK');
            }
            throw $error;
        }
        if ($owned) {
            $this->afterCommit();
        }
        return $result;
    }

    /** Recompute R/Z after commit, like the web. A failure cannot undo the posted voucher. */
    public function afterCommit(): void
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Payment follow-up must run after commit');
        }
        if ($this->cancelCreatedAttempt !== null) {
            try {
                $this->cancelSupersededCreatedAttempts($this->cancelCreatedAttempt);
            } catch (Throwable $error) {
                error_log(json_encode(['request_id'=>class_exists('RequestId') ? RequestId::current():null,
                    'exception'=>get_class($error),'location'=>$error->getFile().':'.$error->getLine()]));
            }
        }
        try {
            $count = $this->engine(function (): int {
                self::loadRecalculation();
                genberegn($this->context->regnaar,false);
                equalizeMatchingRecords();
                $year = (int)date('Y')-1;
                $month = (int)date('m');
                $day = (int)date('d');
                while (!checkdate($month,$day,$year)) {
                    $day--;
                }
                $from = sprintf('%04d-%02d-%02d',$year,$month,$day);
                return (int)$this->store->rows('SELECT count(id) AS count FROM transaktioner WHERE transdate>='.$this->store->literal($from))[0]['count'];
            });
            $registry = $this->registry;
            if (!$registry) {
                $previous = ifset($GLOBALS,'connection');
                try {
                    $registry = db_connect($GLOBALS['sqhost'],$GLOBALS['squser'],$GLOBALS['sqpass'],$GLOBALS['sqdb'],__FILE__);
                } finally {
                    $GLOBALS['connection'] = $previous;
                }
            }
            (new MobileSchema($registry))->execute('UPDATE regnskab SET posteret='.$count.' WHERE id='.(int)$this->context->dbId);
        } catch (Throwable $error) {
            error_log(json_encode(['request_id'=>class_exists('RequestId') ? RequestId::current():null,
                'exception'=>get_class($error),'location'=>$error->getFile().':'.$error->getLine()]));
        }
    }

    /**
     * B8a supplies a provider canceller returning true only after a terminal cancellation is confirmed.
     * Supersession is durable, so its cron can also retry this follow-up after a provider outage.
     * Provider calls happen after commit; late authorization is blocked by the superseded link.
     * @param callable(string):bool $cancel Provider cancellation with terminal-state confirmation.
     */
    public function cancelSupersededCreatedAttempts(callable $cancel): void
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Provider cancellation must run after commit');
        }
        $attempts = $this->store->rows("SELECT a.id,a.provider_reference FROM payment_attempts a
            JOIN payment_links l ON l.id=a.link_id WHERE l.state='superseded' AND a.state='created' ORDER BY a.id");
        foreach ($attempts as $attempt) {
            try {
                if ($cancel((string)$attempt['provider_reference']) === true) {
                    $this->store->execute("UPDATE payment_attempts SET state='cancelled',updated_at=now()
                        WHERE id=".(int)$attempt['id']." AND state='created'");
                }
            } catch (Throwable $error) {
                error_log(json_encode(['request_id'=>class_exists('RequestId') ? RequestId::current():null,
                    'exception'=>get_class($error),'location'=>$error->getFile().':'.$error->getLine()]));
            }
        }
    }

    /**
     * The legacy DB logger derives filesystem paths from REQUEST_URI as well as cwd.
     * Keep its first-level web path consistent with LegacyEngine's cwd and restore the real API path.
     * @return mixed The engine callback's result.
     */
    private function engine(callable $operation)
    {
        $exists = array_key_exists('REQUEST_URI', $_SERVER);
        $saved = ifset($_SERVER, 'REQUEST_URI');
        $_SERVER['REQUEST_URI'] = '/saldi/finans/bogfor.php';
        try {
            return LegacyEngine::run($this->context, $operation);
        } finally {
            if ($exists) {
                $_SERVER['REQUEST_URI'] = $saved;
            } else {
                unset($_SERVER['REQUEST_URI']);
            }
        }
    }

    /** Include the shared definitions without executing the legacy GET entry action. */
    private static function loadRecalculation(): void
    {
        $exists = array_key_exists('regnskabsaar',$_GET);
        $saved = ifset($_GET,'regnskabsaar');
        unset($_GET['regnskabsaar']);
        try {
            require_once __DIR__ . '/../../includes/genberegn.php';
        } finally {
            if ($exists) {
                $_GET['regnskabsaar'] = $saved;
            }
        }
    }

    /** @return string Positive money with two decimals, never an exponent or a rounded input. */
    private static function amount($value): string
    {
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,10})\.[0-9]{2}$/D',$value) || self::minor($value) <= 0) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_amount']);
        }
        return $value;
    }

    private static function minor(string $amount): int
    {
        return (int)str_replace('.','',$amount);
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    private function response(array $row, string $rest, array $headers=[]): array
    {
        return ['status'=>201,'headers'=>$headers,'body'=>['success'=>true,'message'=>'','data'=>[
            'registration_id'=>(int)$row['id'],'kladde_id'=>(int)$row['kladde_id'],'bilag'=>(int)$row['bilag'],
            'aligned'=>$row['aligned'] === 't' || $row['aligned'] === true,'rest'=>$rest,'currency'=>trim((string)$row['currency'])]]];
    }
}
