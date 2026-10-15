<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/payments/PaymentLinkService.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261001 CDX/LH Created. Invoice payment links.
// 20261001 CDX/LH Recover stale unconfirmed creation with its original reference and payload.
// 20261005 CDX/LH Report captured totals grouped by currency instead of the requested link amount.

require_once __DIR__ . '/VippsEpaymentProvider.php';
require_once __DIR__ . '/../PaymentRegistrationService.php';
require_once __DIR__ . '/../../../includes/mobileRegistryUpdates.php';

/** Invoice link reservation, public POST attempts and cancellation. Never captures or books money. */
final class PaymentLinkService
{
    private MobileSchema $store;
    private EngineContext $context;
    private string $subject;
    private PaymentLinkProvider $provider;
    private array $settings = [];
    private ?Closure $synchronize;

    public function __construct(EngineContext $context, string $subject, ?PaymentLinkProvider $provider = null, ?callable $synchronize = null)
    {
        $this->context = $context;
        $this->subject = $subject;
        $this->store = new MobileSchema($context->connection);
        $this->provider = $provider ?? new VippsEpaymentProvider($context->connection);
        $this->synchronize = $synchronize === null ? null : Closure::fromCallable($synchronize);
        // B8b supplies this adapter; injected callbacks keep the boundary testable.
        $settlementFile = __DIR__ . '/PaymentSettlementService.php';
        if ($this->synchronize === null && is_file($settlementFile)) {
            require_once $settlementFile;
            if (is_callable(['PaymentSettlementService','synchronizeReference'])) {
                $this->synchronize = fn(string $reference) => PaymentSettlementService::synchronizeReference(
                    $context,$subject,$this->provider,$reference);
            }
        }
        foreach ($this->store->rows("SELECT var_grp,var_name,var_value FROM settings WHERE var_grp IN ('mobile_betaling','mobilepay') AND user_id=0 ORDER BY id") as $row) {
            $this->settings[$row['var_grp']][$row['var_name']] = (string)$row['var_value'];
        }
    }

    /** @return array{payable:bool,reason:string|null,providers:list<array{id:string,label:string,configured:bool}>} */
    public function options(int $postId): array
    {
        $item = (new OpenPostService($this->context->connection))->detail($postId);
        $configured = $this->configured();
        $reason = null;
        if ($this->context->baseCurrency !== 'DKK' || !in_array(trim((string)$item['original_currency']), ['DKK','-',''], true)) {
            $reason = 'foreign_currency';
        } elseif ($item['id'] !== $postId || $item['type'] !== 'invoice' || (float)$item['rest'] <= 0 || str_contains($item['faktnr'], ';')) {
            $reason = 'not_invoice';
        } elseif (PaymentRegistrationService::accounts($this->context->connection, $this->context->regnaar)['mobilepay'] === null) {
            $reason = 'no_account';
        } elseif (!$configured) {
            $reason = 'provider_not_configured';
        }
        return ['payable'=>$reason === null,'reason'=>$reason,
            'providers'=>[['id'=>'vipps_epayment','label'=>'MobilePay','configured'=>$configured]]];
    }

    private function configured(): bool
    {
        $base = $this->publicBase();
        $secret = trim((string)ifset($this->settings, ['mobilepay','invoice_webhook_secret'], ''));
        $webhook = trim((string)ifset($this->settings, ['mobilepay','invoice_webhook_url'], ''));
        return $this->provider->configured() && $base !== null && strlen($secret) >= 16
            && $webhook === $base . '/debitor/payments/link/webhook.php?t=' . $this->context->dbId
            && PaymentRegistrationService::accounts($this->context->connection, $this->context->regnaar)['mobilepay'] !== null;
    }

    private function publicBase(): ?string
    {
        $base = rtrim(trim((string)ifset($this->settings, ['mobile_betaling','public_base_url'], '')), '/');
        $parts = parse_url($base);
        return $parts && ifset($parts,'scheme') === 'https' && isset($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
            && !preg_match('/[\r\n]/', $base) ? $base : null;
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function create(array $body): array
    {
        $postId = OpenPostService::id(ifset($body,'openpost_id'));
        Idempotency::clientId($body);
        $this->store->execute('BEGIN');
        try {
            $replay = new Idempotency($this->context->connection);
            $cached = $replay->replay($this->subject, 'payment.link.create', $postId, $body);
            if ($cached !== null) {
                $this->store->execute('COMMIT');
                return $cached;
            }
            if (ifset($body,'provider') !== 'vipps_epayment') {
                throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_provider']);
            }
            $link = $this->reserve($postId, Idempotency::clientId($body));
            $envelope = ['success'=>true,'message'=>'','data'=>$link];
            $replay->store($this->subject,'payment.link.create',$postId,$body,201,$envelope,'payment_link',$link['id']);
            $this->store->execute('COMMIT');
            return ['status'=>201,'body'=>$envelope,'headers'=>[]];
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    /** Reserve inside a caller transaction; the caller MUST store the URL in its replay envelope before commit.
     * @return array{id:int,url:string,amount:string,currency:string,expires_at:string,state:string,provider:string}
     */
    public function reserve(int $postId, string $clientId): array
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_INTRANS) {
            throw new LogicException('Payment link reservation requires a transaction');
        }
        $item = (new OpenPostService($this->context->connection))->detail($postId);
        if ($item['id'] !== $postId) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'not_payable']);
        }
        $this->lockGroup($postId, true);
        $options = $this->options($postId);
        if (!$options['payable']) {
            $error = in_array($options['reason'], ['provider_not_configured','no_account'], true) ? 'not_configured' : 'validation_failed';
            throw new MobileT2Error($error,422,['reason'=>$options['reason'] === 'foreign_currency' ? 'currency_not_supported' : 'not_payable']);
        }
        $active = $this->store->rows("SELECT *,expires_at>now() AS valid FROM payment_links WHERE openpost_id=$postId AND state='active' ORDER BY id");
        $item = (new OpenPostService($this->context->connection))->detail($postId);
        $rest = $this->rest($postId);
        if ($rest <= 0) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'not_payable']);
        }
        foreach ($active as $row) {
            if ($row['valid'] === 't' && self::minor($row['amount']) === $rest) {
                $url = $this->url((int)$row['id']);
                if ($url !== null) {
                    return $this->shape($row, $url);
                }
                throw new MobileT2Error('conflict',409,['reason'=>'in_progress']);
            }
            $this->assertNoPaymentInProgress($postId);
            $state = $row['valid'] === 't' ? 'superseded' : 'expired';
            $this->store->execute("UPDATE payment_links SET state='$state' WHERE id=".(int)$row['id']);
        }
        // URLs live in the replay envelope (30-day retention), hence a <=30-day link lifetime.
        $days = min(30,max(1,(int)ifset($this->settings,['mobile_betaling','link_lifetime_days'],14)));
        $token = bin2hex(random_bytes(16));
        $hash = $this->store->literal(hash('sha256',$token));
        $amount = number_format($rest/100,2,'.','');
        $row = $this->store->rows("INSERT INTO payment_links (token_sha256,provider,openpost_id,konto_id,ordre_id,faktnr,amount,currency,expires_at,created_by,client_id)
            VALUES ($hash,'vipps_epayment',$postId,".(int)$item['konto_id'].",".(int)$item['ordre_id'].",".$this->store->literal($item['faktnr']).",$amount,'DKK',now()+interval '$days days',".$this->store->literal($this->subject).",".$this->store->literal($clientId).") RETURNING *")[0];
        return $this->shape($row, $this->publicBase().'/debitor/payments/link/betal.php?l='.$this->context->dbId.'-'.$token);
    }

    /** Status polling only asks for existing attempts. B8b's synchronizer owns capture and booking.
     * @return array{id:int,state:string,amount:string,currency:string,openpost_id:int,paid_at:string|null,url?:string,paid_amount?:string,paid_currency?:string,received_amounts?:list<array{amount:string,currency:string}>,rest_after?:string}
     */
    public function status(int $linkId, bool $poll = true): array
    {
        $row = $this->link($linkId);
        if ($row['state'] === 'active') {
            $this->store->execute("UPDATE payment_links SET state='expired' WHERE id=$linkId AND state='active' AND expires_at<=now()");
            $row = $this->link($linkId);
        }
        if ($poll && $row['state'] === 'active') {
            $due = $this->store->rows("UPDATE payment_attempts SET last_checked_at=now() WHERE id=(SELECT id FROM payment_attempts
                WHERE link_id=$linkId AND state IN ('created','authorized','capturing','captured') ORDER BY id DESC LIMIT 1)
                AND (last_checked_at IS NULL OR last_checked_at<now()-interval '10 seconds') RETURNING provider_reference");
            if ($due) {
                if ($this->synchronize !== null) {
                    ($this->synchronize)($due[0]['provider_reference']);
                } else {
                    $this->pollReference($due[0]['provider_reference']);
                }
                $row = $this->link($linkId);
            }
        }
        $data = ['id'=>(int)$row['id'],'state'=>$row['state'],'amount'=>number_format((float)$row['amount'],2,'.',''),
            'currency'=>$row['currency'],'openpost_id'=>(int)$row['openpost_id'],'paid_at'=>self::instant($row['paid_at'])];
        $url = $this->url($linkId);
        if ($url !== null) {
            $data['url'] = $url;
        }
        if ($row['paid_at'] !== null) {
            $received = $this->store->rows("SELECT captured_currency,SUM(captured_minor) AS minor FROM payment_attempts
                WHERE link_id=" . (int)$linkId . " AND captured_minor IS NOT NULL AND captured_currency IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM payment_attempts u WHERE u.link_id=" . (int)$linkId . " AND u.state='captured'
                    AND (u.captured_minor IS NULL OR u.captured_currency IS NULL))
                GROUP BY captured_currency ORDER BY captured_currency");
            $data['received_amounts'] = array_map(static function (array $money): array {
                $minor = (int)$money['minor'];
                return ['amount'=>intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT),
                    'currency'=>$money['captured_currency']];
            }, $received);
            // Until recovery resolves every captured reference, no link total is authoritative.
            // The scalar is meaningful only when all received funds share one currency.
            if (count($data['received_amounts']) === 1) {
                $data['paid_amount'] = $data['received_amounts'][0]['amount'];
                $data['paid_currency'] = $data['received_amounts'][0]['currency'];
            }
            $data['rest_after'] = (new OpenPostService($this->context->connection))->detail((int)$row['openpost_id'])['rest'];
        }
        return $data;
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function cancel(int $linkId, array $body): array
    {
        Idempotency::clientId($body);
        $this->store->execute('BEGIN');
        try {
            $replay = new Idempotency($this->context->connection);
            $cached = $replay->replay($this->subject,'payment.link.cancel',$linkId,$body);
            if ($cached !== null) {
                $this->store->execute('COMMIT');
                return $cached;
            }
            $link = $this->link($linkId);
            $this->lockGroup((int)$link['openpost_id'], true);
            $this->assertNoPaymentInProgress((int)$link['openpost_id']);
            $this->store->execute("UPDATE payment_links SET state='cancelled' WHERE id=$linkId AND state='active'");
            $envelope = ['success'=>true,'message'=>'','data'=>$this->status($linkId,false)];
            $replay->store($this->subject,'payment.link.cancel',$linkId,$body,200,$envelope);
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
        $this->cancelCreated($linkId);
        return ['status'=>200,'body'=>$envelope,'headers'=>[]];
    }

    /** Resolve an opaque public token in the server-selected tenant. @return array<string,mixed> */
    public function byToken(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$token)) {
            throw new MobileT2Error('not_found',404);
        }
        $rows = $this->store->rows('SELECT * FROM payment_links WHERE token_sha256='.$this->store->literal(hash('sha256',$token)));
        if (!$rows) {
            throw new MobileT2Error('not_found',404);
        }
        return $rows[0];
    }

    /** Public POST only. A session advisory lock survives the reservation commit, preventing duplicate provider work.
     * @return array{redirect_url:string|null,state:string,attempt_id:int|null}
     */
    public function startAttempt(int $linkId, string $publicUrl): array
    {
        if (!$this->configured()) {
            throw new MobileT2Error('not_configured',422,['reason'=>'provider_not_configured']);
        }
        $link = $this->link($linkId);
        $postId = (int)$link['openpost_id'];
        $anchor = $this->anchor($postId);
        $lock = $this->store->literal('saldi:paylink:attempt:'.$anchor['konto_id'].':'.$anchor['faktnr']);
        $this->store->execute("SELECT pg_advisory_lock(hashtextextended($lock,0))");
        try {
            $this->store->execute('BEGIN');
            $this->lockGroup($postId);
            $link = $this->link($linkId);
            if ($link['state'] !== 'active') {
                $this->store->execute('COMMIT');
                return ['redirect_url'=>null,'state'=>$link['state'],'attempt_id'=>null];
            }
            if ($link['valid'] !== 't' || $this->rest($postId) < self::minor($link['amount'])) {
                $state = $link['valid'] !== 't' ? 'expired' : 'superseded';
                $this->store->execute("UPDATE payment_links SET state='$state' WHERE id=$linkId");
                $this->store->execute('COMMIT');
                $this->cancelCreated($linkId);
                return ['redirect_url'=>null,'state'=>$state,'attempt_id'=>null];
            }
            $attempts = $this->store->rows("SELECT *,created_at>now()-interval '9 minutes' AS fresh FROM payment_attempts
                WHERE link_id=$linkId AND (state IN ('created','authorized','capturing') OR (state='captured' AND booking='none')) ORDER BY id");
            $attempt = ifset($attempts,0);
            $retryAbsent = false;
            if ($attempt !== null && $attempt['state'] !== 'created') {
                $this->store->execute('COMMIT');
                return ['redirect_url'=>null,'state'=>'processing','attempt_id'=>(int)$attempt['id']];
            }
            if ($attempt !== null && $attempt['fresh'] !== 't') {
                try {
                    $remote = $this->provider->get($attempt['provider_reference']);
                } catch (MobileT2Error $error) {
                    if ($error->error !== 'upstream_unavailable'
                        || ifset($error->details,'reason') !== 'provider_payment_not_found'
                        || $attempt['redirect_url'] !== null) {
                        throw $error;
                    }
                    // Only confirmed absence allows retrying an unconfirmed create. Keep its identity.
                    $remote = null;
                    $retryAbsent = true;
                }
                if ($remote !== null) {
                    if ($remote['state'] === 'created') {
                        if (!$this->provider->cancel($attempt['provider_reference'])) {
                            throw new MobileT2Error('conflict',409,['reason'=>'payment_in_progress']);
                        }
                        $remote = $this->provider->get($attempt['provider_reference']);
                    }
                    $this->observe($attempt, $remote);
                    if (!in_array($remote['state'], ['cancelled','aborted','expired','terminated'], true)) {
                        $this->store->execute('COMMIT');
                        return ['redirect_url'=>null,'state'=>'processing','attempt_id'=>(int)$attempt['id']];
                    }
                    $attempt = null;
                }
            }
            if ($attempt !== null && $attempt['redirect_url'] !== null) {
                $this->store->execute('COMMIT');
                return ['redirect_url'=>$attempt['redirect_url'],'state'=>'created','attempt_id'=>(int)$attempt['id']];
            }
            if ($attempt === null) {
                $n = (int)$this->store->rows("SELECT count(*) AS n FROM payment_attempts WHERE link_id=$linkId")[0]['n']+1;
                $ref = $this->store->literal('saldi-'.$this->context->dbId.'-'.$linkId.'-'.$n);
                $attempt = $this->store->rows("INSERT INTO payment_attempts (link_id,provider_reference,amount_minor,currency)
                    VALUES ($linkId,$ref,".self::minor($link['amount']).",'DKK') RETURNING *")[0];
            }
            $this->store->execute('COMMIT');
            // A crash or ambiguous transport response retains this same reference for a safe retry.
            // The committed link URL freezes returnUrl even if tenant configuration changes during an outage.
            $returnUrl = $this->url($linkId) ?? $publicUrl;
            $created = $this->provider->create($attempt['provider_reference'],(int)$attempt['amount_minor'],$attempt['currency'],
                $returnUrl.'&return=1','Faktura '.$link['faktnr']);
            $this->store->execute('BEGIN');
            $this->lockGroup($postId);
            $current = $this->link($linkId);
            $id = (int)$attempt['id'];
            $creationTime = $retryAbsent ? ',created_at=now()' : '';
            $this->store->execute("UPDATE payment_attempts SET redirect_url=".$this->store->literal($created['redirect_url']).",updated_at=now()$creationTime WHERE id=$id");
            $usable = $current['state'] === 'active' && $current['valid'] === 't' && $this->rest($postId) >= self::minor($link['amount']);
            if (!$usable && $current['state'] === 'active') {
                $this->store->execute("UPDATE payment_links SET state='superseded' WHERE id=$linkId");
            }
            $this->store->execute('COMMIT');
            if (!$usable) {
                $this->cancelCreated($linkId);
            }
            return ['redirect_url'=>$usable ? $created['redirect_url'] : null,'state'=>$usable ? 'created' : 'superseded','attempt_id'=>$id];
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        } finally {
            $this->store->execute("SELECT pg_advisory_unlock(hashtextextended($lock,0))");
        }
    }

    /** Read-only provider fallback until B8b supplies capture/settlement. Never creates a payment. */
    private function pollReference(string $reference): void
    {
        try {
            $remote = $this->provider->get($reference);
        } catch (MobileT2Error $error) {
            $unconfirmed = $this->store->rows('SELECT id FROM payment_attempts WHERE provider_reference='.
                $this->store->literal($reference)." AND state='created' AND redirect_url IS NULL");
            if ($error->error === 'upstream_unavailable'
                && ifset($error->details,'reason') === 'provider_payment_not_found' && $unconfirmed) {
                // Status is read-only at the provider; only a customer POST may retry this intent.
                return;
            }
            throw $error;
        }
        $this->store->execute('BEGIN');
        try {
            $rows = $this->store->rows('SELECT a.*,l.openpost_id FROM payment_attempts a JOIN payment_links l ON l.id=a.link_id
                WHERE a.provider_reference='.$this->store->literal($reference));
            if ($rows) {
                $attempt = $rows[0];
                $this->lockGroup((int)$attempt['openpost_id']);
                $attempt = $this->store->rows('SELECT * FROM payment_attempts WHERE id='.(int)$attempt['id'])[0];
                if ($attempt['state'] === 'created') {
                    $this->observe($attempt,$remote);
                } elseif ($attempt['state'] === 'authorized' && in_array($remote['state'],['captured','cancelled','expired','aborted','terminated'],true)) {
                    $id = (int)$attempt['id'];
                    $this->store->execute('UPDATE payment_attempts SET state='.$this->store->literal($remote['state']).",updated_at=now()
                        WHERE id=$id AND state='authorized'");
                }
            }
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    /** Store an authoritative observation without capturing or changing terminal/capturing states. */
    private function observe(array $attempt, array $remote): void
    {
        $id = (int)$attempt['id'];
        if ((int)$remote['amount_minor'] !== (int)$attempt['amount_minor'] || $remote['currency'] !== $attempt['currency']) {
            throw new MobileT2Error('conflict',409,['reason'=>'payment_in_progress']);
        }
        $this->store->execute('UPDATE payment_attempts SET state='.$this->store->literal($remote['state']).",last_checked_at=now(),updated_at=now() WHERE id=$id AND state='created'");
    }

    /** Provider cancellation after a committed local cancel/supersession, with confirmation before marking terminal. */
    public function cancelCreated(int $linkId): void
    {
        foreach ($this->store->rows("SELECT a.* FROM payment_attempts a JOIN payment_links l ON l.id=a.link_id
            WHERE l.id=$linkId AND l.state<>'active' AND a.state='created'") as $attempt) {
            try {
                if ($this->provider->cancel($attempt['provider_reference'])) {
                    $this->store->execute('UPDATE payment_attempts SET state=\'cancelled\',updated_at=now() WHERE id='.(int)$attempt['id']." AND state='created'");
                }
            } catch (MobileT2Error $error) {
                // Preserve the pending attempt for B8b's cron/capture recheck; never claim cancellation.
            }
        }
    }

    /** @return array<string,mixed> */
    private function anchor(int $postId): array
    {
        $rows = $this->store->rows("SELECT * FROM openpost WHERE id=$postId");
        if (!$rows) {
            throw new MobileT2Error('not_found',404);
        }
        return $rows[0];
    }

    /** Lock every link of the invoice group, then every openpost row. Serialize first-link creation even with no link row. */
    public function lockGroup(int $postId, bool $creating = false): void
    {
        $anchor = $this->anchor($postId);
        $customer = (int)$anchor['konto_id'];
        $invoice = $this->store->literal((string)$anchor['faktnr']);
        if ($creating) {
            $key = $this->store->literal('saldi:paylink:reserve:'.$customer.':'.$anchor['faktnr']);
            $this->store->execute("SELECT pg_advisory_xact_lock(hashtextextended($key,0))");
        }
        $this->store->rows("SELECT l.id FROM payment_links l JOIN openpost p ON p.id=l.openpost_id
            WHERE p.konto_id=$customer AND p.faktnr=$invoice ORDER BY l.id FOR UPDATE OF l");
        $this->store->rows("SELECT id FROM openpost WHERE konto_id=$customer AND faktnr=$invoice ORDER BY id FOR UPDATE");
    }

    /** Current payable rest includes future payments as well as today's report rows. */
    public function rest(int $postId): int
    {
        $anchor = $this->anchor($postId);
        $rows = $this->store->rows('SELECT amount,valuta FROM openpost WHERE konto_id='.(int)$anchor['konto_id'].
            ' AND faktnr='.$this->store->literal((string)$anchor['faktnr'])." AND COALESCE(udlignet,'0')<>'1'");
        $rest = 0;
        foreach ($rows as $row) {
            if (!in_array(trim((string)$row['valuta']),['DKK','-',''],true)) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'currency_not_supported']);
            }
            $rest += self::minor(number_format((float)$row['amount'],2,'.',''));
        }
        return $rest;
    }

    private function assertNoPaymentInProgress(int $postId): void
    {
        $anchor = $this->anchor($postId);
        if ($this->store->rows('SELECT a.id FROM payment_attempts a JOIN payment_links l ON l.id=a.link_id JOIN openpost p ON p.id=l.openpost_id
            WHERE p.konto_id='.(int)$anchor['konto_id'].' AND p.faktnr='.$this->store->literal((string)$anchor['faktnr'])."
            AND (a.state IN ('authorized','capturing') OR (a.state='captured' AND a.booking='none')) LIMIT 1")) {
            throw new MobileT2Error('conflict',409,['reason'=>'payment_in_progress']);
        }
    }

    /** @return array<string,mixed> */
    public function link(int $id): array
    {
        $rows = $this->store->rows("SELECT *,expires_at>now() AS valid FROM payment_links WHERE id=$id");
        if (!$rows) {
            throw new MobileT2Error('not_found',404);
        }
        return $rows[0];
    }

    /** Recover the opaque URL from its atomically committed creation/delivery replay envelope. */
    public function url(int $id): ?string
    {
        foreach ($this->store->rows("SELECT response_json FROM mobile_idempotency WHERE (resource_type='payment_link' AND resource_id=$id) OR
            (resource_type='document_delivery' AND response_json::jsonb #>> '{data,payment_link,id}'='$id') ORDER BY id DESC") as $record) {
            $data = ifset(json_decode($record['response_json'],true),'data',[]);
            $link = ifset($data,'payment_link',$data);
            if (ifset($link,'id') === $id && is_string(ifset($link,'url'))) {
                return $link['url'];
            }
        }
        return null;
    }

    /** @return array{id:int,url:string,amount:string,currency:string,expires_at:string,state:string,provider:string} */
    private function shape(array $row, string $url): array
    {
        return ['id'=>(int)$row['id'],'url'=>$url,'amount'=>number_format((float)$row['amount'],2,'.',''),
            'currency'=>$row['currency'],'expires_at'=>self::instant($row['expires_at']),'state'=>$row['state'],'provider'=>$row['provider']];
    }

    public static function instant(?string $value): ?string
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
    }

    public static function minor(string $value): int
    {
        return (int)round((float)$value*100);
    }

    /** Installation-wide token bucket. SQL now() handles refill independently of timezone. */
    public static function rateLimit($registry, string $scope, string $key, int $capacity = 30, float $perSecond = 0.5): void
    {
        $store = mobileT2RegistryStore($registry);
        $scopeSql = $store->literal($scope);
        $keySql = $store->literal(hash('sha256',$key));
        $store->execute('BEGIN');
        try {
            $store->execute("INSERT INTO mobile_rate_buckets(scope,bucket_key,tokens,updated_at) VALUES($scopeSql,$keySql,$capacity,now()) ON CONFLICT DO NOTHING");
            $row = $store->rows("SELECT LEAST($capacity,tokens+GREATEST(0,EXTRACT(EPOCH FROM now()-updated_at))*$perSecond) AS available
                FROM mobile_rate_buckets WHERE scope=$scopeSql AND bucket_key=$keySql FOR UPDATE")[0];
            $available = (float)$row['available'];
            $remaining = $available < 1 ? $available : $available-1;
            $store->execute("UPDATE mobile_rate_buckets SET tokens=$remaining,updated_at=now() WHERE scope=$scopeSql AND bucket_key=$keySql");
            $thisError = $available < 1;
            $store->execute('COMMIT');
            if ($thisError) {
                throw new MobileT2Error('rate_limited',429,['retry_after'=>max(1,(int)ceil((1-$available)/$perSecond))]);
            }
        } catch (Throwable $error) {
            if (pg_transaction_status($registry) !== PGSQL_TRANSACTION_IDLE) {
                $store->execute('ROLLBACK');
            }
            throw $error;
        }
    }

    private function rollback(): void
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            $this->store->execute('ROLLBACK');
        }
    }
}
