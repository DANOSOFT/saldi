<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/payments/PaymentSettlementService.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261005 CDX/LH Normalize provider events and retain authoritative capture dates and money.
// 20261001 CDX/LH Route paid events through the shared deduplicated outbox and fan-out.

require_once __DIR__ . '/PaymentLinkService.php';
require_once __DIR__ . '/../PushService.php';
require_once __DIR__ . '/../MobileEventOutbox.php';
require_once __DIR__ . '/../MobileEventFanout.php';

/** Durable webhook processing, idempotent capture and shared journal posting. */
final class PaymentSettlementService
{
    private MobileSchema $store;
    private PaymentLinkService $links;

    public function __construct(private EngineContext $context, private string $subject, private PaymentLinkProvider $provider, private $registryConnection = null)
    {
        $this->store = new MobileSchema($context->connection);
        $this->links = new PaymentLinkService($context, $subject, $provider, static fn(string $ref) => null);
    }

    /** B8a status/return-page adapter. @return array{state:string,booking:string,busy:bool} */
    public static function synchronizeReference(EngineContext $context, string $subject, PaymentLinkProvider $provider, string $reference): array
    {
        return (new self($context, $subject, $provider))->synchronize($reference);
    }

    /** Verify the raw body and exact signed request target before parsing or storing anything. */
    public static function verify(string $body, array $server, string $secret): bool
    {
        $date = ifset($server, 'HTTP_X_MS_DATE', '');
        $parsed = is_string($date) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC7231, $date, new DateTimeZone('UTC')) : false;
        if (!$parsed && is_string($date)) {
            $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $date);
        }
        if (strlen($secret) < 16 || !$parsed || abs(time() - $parsed->getTimestamp()) > 300) {
            return false;
        }
        $hash = ifset($server, 'HTTP_X_MS_CONTENT_SHA256', '');
        $auth = ifset($server, 'HTTP_AUTHORIZATION', ifset($server, 'HTTP_X_VIPPS_AUTHORIZATION', ''));
        $method = ifset($server, 'REQUEST_METHOD', '');
        $path = ifset($server, 'REQUEST_URI', '');
        $host = ifset($server, 'HTTP_HOST', '');
        if (!is_string($hash) || !is_string($auth) || !is_string($host) || !is_string($path) || $method !== 'POST') {
            return false;
        }
        $signed = "$method\n$path\n$date;$host;$hash";
        $signature = base64_encode(hash_hmac('sha256', $signed, $secret, true));
        return hash_equals(base64_encode(hash('sha256', $body, true)), $hash)
            && hash_equals('HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=' . $signature, $auth);
    }

    /** Store once before acknowledging; duplicates never redo synchronous work.
     * @return array{id:int,duplicate:bool}
     */
    public static function receive($connection, string $body): array
    {
        $event = json_decode($body, true);
        $reference = ifset($event, 'reference');
        $name = ifset($event, 'name');
        $psp = ifset($event, 'pspReference');
        if (!is_string($reference) || !preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $reference)
            || !is_string($name) || !preg_match('/^[a-zA-Z0-9_.-]{1,60}$/D', $name)
            || !is_string($psp) || $psp === '' || strlen($psp) > 64) {
            throw new MobileT2Error('validation_failed', 400);
        }
        $name = self::eventName($name);
        $store = new MobileSchema($connection);
        $key = $store->literal(hash('sha256', json_encode(['vipps_epayment', $reference, $name, $psp], JSON_THROW_ON_ERROR)));
        $rows = $store->rows('INSERT INTO payment_provider_events(provider,event_key,provider_reference,name,payload) VALUES('
            . "'vipps_epayment',$key," . $store->literal($reference) . ',' . $store->literal($name) . ',' . $store->literal($body)
            . ') ON CONFLICT(event_key) DO NOTHING RETURNING id');
        if ($rows) {
            return ['id'=>(int)$rows[0]['id'], 'duplicate'=>false];
        }
        return ['id'=>(int)$store->rows("SELECT id FROM payment_provider_events WHERE event_key=$key")[0]['id'], 'duplicate'=>true];
    }

    /** Retryable events are authoritative observations, never instructions to trust an event amount. */
    public function processEvent(int $eventId): void
    {
        $rows = $this->store->rows('SELECT * FROM payment_provider_events WHERE id=' . (int)$eventId);
        if (!$rows || in_array($rows[0]['state'], ['processed','ignored'], true)) {
            return;
        }
        $event = $rows[0];
        $ref = (string)$event['provider_reference'];
        $supported = ['authorized','captured','aborted','expired','cancelled','terminated','created'];
        $own = str_starts_with($ref, 'saldi-' . $this->context->dbId . '-');
        $name = self::eventName($event['name']);
        if (!$own || !in_array($name, $supported, true) || !$this->attempt($ref)) {
            $this->finishEvent($eventId, 'ignored');
            return;
        }
        try {
            $result = $this->synchronize($ref, $name === 'captured');
            if (!$result['busy']) {
                $this->finishEvent($eventId, 'processed');
            }
        } catch (Throwable $error) {
            $this->finishEvent($eventId, 'failed', $error instanceof MobileT2Error ? $error->error : 'internal');
            throw $error;
        }
    }

    /** Capture and booking serialize across commits with a per-reference session lock.
     * An ambiguous capture is retried with the same provider idempotency key.
     * @return array{state:string,booking:string,busy:bool}
     */
    public function synchronize(string $reference, bool $observeCapture = false): array
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Settlement owns its transactions');
        }
        $attempt = $this->attempt($reference);
        if (!$attempt || !str_starts_with($reference, 'saldi-' . $this->context->dbId . '-')) {
            return ['state'=>'ignored','booking'=>'none','busy'=>false];
        }
        $key = $this->store->literal('saldi:paylink:settle:' . $reference);
        $locked = $this->store->rows("SELECT pg_try_advisory_lock(hashtextextended($key,0)) AS locked")[0]['locked'] === 't';
        if (!$locked) {
            return ['state'=>$attempt['state'],'booking'=>$attempt['booking'],'busy'=>true];
        }
        try {
            $attempt = $this->attempt($reference);
            if (!$observeCapture && in_array($attempt['booking'], ['booked','review'], true) && $attempt['captured_minor'] !== null
                && $attempt['captured_currency'] !== null) {
                return $this->result($attempt);
            }
            $this->store->execute('UPDATE payment_attempts SET last_checked_at=now() WHERE id=' . (int)$attempt['id']);
            try {
                $remote = $this->provider->get($reference);
            } catch (MobileT2Error $error) {
                // A public creation that never reached the provider is retried only by public POST.
                if ($attempt['state'] === 'created' && $attempt['redirect_url'] === null
                    && ifset($error->details, 'reason') === 'provider_payment_not_found') {
                    return $this->result($attempt);
                }
                throw $error;
            }
            $this->store->execute('BEGIN');
            $this->links->lockGroup((int)$attempt['openpost_id']);
            $attempt = $this->attempt($reference);
            $link = $this->links->link((int)$attempt['link_id']);
            $id = (int)$attempt['id'];
            if ($remote['state'] === 'captured' || $remote['captured_minor'] > 0) {
                $this->recordCapture($id, $remote);
                $this->store->execute('COMMIT');
                $this->book($reference, $remote);
            } elseif ($remote['state'] === 'authorized') {
                $others = $this->store->rows('SELECT a.id FROM payment_attempts a JOIN payment_links l ON l.id=a.link_id JOIN openpost p ON p.id=l.openpost_id
                    WHERE p.konto_id=' . (int)$link['konto_id'] . ' AND p.faktnr=' . $this->store->literal($link['faktnr']) . " AND a.id<>$id
                    AND (a.state='capturing' OR (a.state='captured' AND a.booking='none')) LIMIT 1");
                $eligible = $link['state'] === 'active' && $link['valid'] === 't'
                    && in_array($attempt['state'], ['created','authorized','capturing'], true)
                    && $this->matches($attempt, $link, $remote)
                    && $this->links->rest((int)$link['openpost_id']) >= (int)$attempt['amount_minor'] && !$others;
                // Keep authorized until cancellation is confirmed, blocking manual registration during the call.
                $state = $eligible ? 'capturing' : 'authorized';
                $this->store->execute("UPDATE payment_attempts SET state='$state',last_checked_at=now(),updated_at=now() WHERE id=$id");
                if (!$eligible && $link['state'] === 'active') {
                    $this->store->execute("UPDATE payment_links SET state='superseded' WHERE id=" . (int)$link['id']);
                }
                $this->store->execute('COMMIT');
                if ($eligible) {
                    $captured = $this->provider->capture($reference, (int)$attempt['amount_minor'], $attempt['currency']);
                    if ($captured['captured_minor'] <= 0) {
                        throw new MobileT2Error('upstream_unavailable', 503);
                    }
                    $this->book($reference, $captured);
                } else {
                    $this->provider->cancel($reference);
                    $confirmed = $this->provider->get($reference);
                    if ($confirmed['captured_minor'] > 0) {
                        $this->book($reference, $confirmed);
                    } elseif (in_array($confirmed['state'], ['cancelled','aborted','expired','terminated'], true)) {
                        $this->terminal($reference, $confirmed['state']);
                    } else {
                        throw new MobileT2Error('upstream_unavailable', 503);
                    }
                }
            } else {
                $terminal = in_array($remote['state'], ['cancelled','aborted','expired','terminated'], true);
                // A stale CREATED observation must never downgrade a capturing/captured attempt.
                if ($terminal && $attempt['state'] !== 'captured') {
                    $this->store->execute('UPDATE payment_attempts SET state=' . $this->store->literal($remote['state']) . ",updated_at=now(),last_checked_at=now() WHERE id=$id");
                } else {
                    $this->store->execute("UPDATE payment_attempts SET last_checked_at=now() WHERE id=$id");
                }
                $cancel = $remote['state'] === 'created' && ($link['state'] !== 'active' || $link['valid'] !== 't');
                $this->store->execute('COMMIT');
                if ($cancel && $this->provider->cancel($reference)) {
                    $this->terminal($reference, 'cancelled');
                }
            }
            return $this->result($this->attempt($reference));
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        } finally {
            $this->store->execute("SELECT pg_advisory_unlock(hashtextextended($key,0))");
        }
    }

    /** Shared booking and notification are committed atomically with attempt and link state. */
    private function book(string $reference, array $remote): void
    {
        $attempt = $this->attempt($reference);
        $this->store->execute('BEGIN');
        $registration = null;
        try {
            $this->links->lockGroup((int)$attempt['openpost_id']);
            $attempt = $this->attempt($reference);
            $this->recordCapture((int)$attempt['id'], $remote);
            $link = $this->links->link((int)$attempt['link_id']);
            $linkId = (int)$link['id'];
            $this->store->execute("UPDATE payment_links SET paid_at=(SELECT min(captured_at) FROM payment_attempts
                WHERE link_id=$linkId AND captured_minor IS NOT NULL) WHERE id=$linkId");
            if (in_array($attempt['booking'], ['booked','review'], true)) {
                $this->store->execute('COMMIT');
                return;
            }
            $id = (int)$attempt['id'];
            $attempt = $this->attempt($reference);
            $booking = 'review';
            $linkState = 'review';
            $notification = 'payment_review';
            $registrationId = null;
            if ($this->matches($attempt, $link, $remote) && $remote['captured_minor'] === (int)$attempt['amount_minor']
                && $this->links->rest((int)$link['openpost_id']) >= (int)$attempt['amount_minor']) {
                $this->store->execute('SAVEPOINT payment_booking');
                try {
                    $date = (new DateTimeImmutable($attempt['captured_at']))->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format('Y-m-d');
                    $creator = Subject::parse($link['created_by']);
                    $context = new EngineContext($this->context->connection, $this->context->db, $this->context->dbId,
                        $link['created_by'], $creator['user_id'], $date, $this->context->languageId);
                    $registration = new PaymentRegistrationService($context, $link['created_by'], $this->registryConnection);
                    $result = $registration->registerFromProvider(['openpost_id'=>(int)$link['openpost_id'],
                        'amount'=>self::money((int)$attempt['amount_minor']), 'date'=>$date], $reference);
                    $registrationId = $result['body']['data']['registration_id'];
                    $booking = 'booked';
                    $linkState = 'paid';
                    $notification = 'invoice_paid';
                } catch (MobileT2Error $error) {
                    $this->store->execute('ROLLBACK TO SAVEPOINT payment_booking');
                    $registration = null;
                    $booking = 'unsettled';
                    $linkState = 'paid_unsettled';
                    $notification = 'payment_unsettled';
                }
                $this->store->execute('RELEASE SAVEPOINT payment_booking');
            }
            $this->store->execute("UPDATE payment_attempts SET booking='$booking',registration_id=" . ($registrationId ?? 'NULL') . ",updated_at=now() WHERE id=$id");
            $settled = $booking === 'booked' ? ',settled_at=COALESCE(settled_at,now()),registration_id=' . (int)$registrationId : '';
            $this->store->execute("UPDATE payment_links SET state='$linkState'$settled WHERE id=$linkId");
            // Retrying an unsettled booking does not emit the same warning again.
            if ($attempt['booking'] !== $booking) {
                $this->notify($link, $notification, (int)$remote['captured_minor']);
            }
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
        if ($registration !== null) {
            $registration->afterCommit();
        }
    }

    /** Store provider facts before booking; missing capture time remains retryable, never today's date. */
    private function recordCapture(int $id, array $remote): void
    {
        $timestamp = ifset($remote, 'captured_at');
        if (!is_string($timestamp) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $timestamp)
            || !is_int($remote['captured_minor']) || $remote['captured_minor'] <= 0
            || !preg_match('/^[A-Z]{3}$/D', $remote['currency'])) {
            throw new MobileT2Error('upstream_unavailable', 503);
        }
        $capturedAt = new DateTimeImmutable($timestamp);
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new MobileT2Error('upstream_unavailable', 503);
        }
        $this->store->execute("UPDATE payment_attempts SET state='captured',captured_at="
            . $this->store->literal($capturedAt->format('Y-m-d\TH:i:s.uP')) . ',captured_minor=' . (int)$remote['captured_minor']
            . ',captured_currency=' . $this->store->literal($remote['currency']) . ',psp_reference='
            . ($remote['psp_reference'] === null ? 'NULL' : $this->store->literal($remote['psp_reference']))
            . ",last_checked_at=now(),updated_at=now() WHERE id=" . (int)$id);
    }

    /** @return bool True only when provider and local identities and authorized totals agree. */
    private function matches(array $attempt, array $link, array $remote): bool
    {
        return $remote['reference'] === $attempt['provider_reference']
            && $remote['amount_minor'] === (int)$attempt['amount_minor']
            && $remote['currency'] === $attempt['currency'] && $remote['currency'] === $link['currency']
            && (int)$attempt['amount_minor'] === PaymentLinkService::minor($link['amount']);
    }

    private function notify(array $link, string $type, int $amount): void
    {
        $subjects = [$link['created_by']];
        $customer = $this->store->rows('SELECT firmanavn FROM adresser WHERE id=' . (int)$link['konto_id']);
        $sum = number_format($amount / 100, 2, ',', '.');
        $invoice = $link['faktnr'];
        if ($type === 'invoice_paid') {
            $origin = $this->store->rows('SELECT created_by FROM mobile_order_origins WHERE ordre_id=' . (int)$link['ordre_id']);
            if ($origin) {
                $subjects[] = $origin[0]['created_by'];
            }
            $key = 'invoice_paid:' . (int)$link['openpost_id'];
            MobileEventOutbox::emit($this->context->connection, $type, $key, 'openpost', (int)$link['openpost_id'],
                ['faktnr'=>$invoice,'amount'=>self::money($amount),'customer_name'=>(string)ifset($customer, [0,'firmanavn'], ''),
                    'via'=>'mobilepay','created_by'=>$link['created_by']], null);
            (new MobileEventFanout($this->context->connection, $this->registryConnection ?? MobileAuth::connectRegistry(), $this->context->dbId))
                ->settlementInTransaction($key, $subjects);
            return;
        } elseif ($type === 'payment_review') {
            $title = 'Betaling skal kontrolleres';
            $message = "Faktura $invoice: $sum er modtaget via MobilePay, men fakturaen er allerede betalt eller beløbet passer ikke. Bed din bogholder om at kontrollere betalingen i Saldi.";
        } else {
            $title = 'Betaling ikke bogført';
            $message = "Faktura $invoice: $sum er modtaget via MobilePay, men kunne ikke bogføres. Bed din bogholder om at bogføre den i Saldi.";
        }
        $notification = (new PushService($this->context->connection))->create($subjects, $link['created_by'], $type, $title, $message, null, true);
        $this->store->execute("UPDATE notifications SET ref_type='openpost',ref_id=" . (int)$link['openpost_id'] . ' WHERE id=' . (int)$notification['notification_id']);
    }

    /** Bounded tenant recovery; no provider payment is ever created here.
     * @return array{events:int,attempts:int,expired:int,failed:int}
     */
    public function run(int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $stats = ['events'=>0,'attempts'=>0,'expired'=>0,'failed'=>0];
        $events = $this->store->rows("SELECT id FROM payment_provider_events WHERE state IN ('received','failed') ORDER BY processed_at NULLS FIRST,id LIMIT $limit");
        foreach ($events as $event) {
            try {
                $this->processEvent((int)$event['id']);
                $stats['events']++;
            } catch (Throwable $error) {
                $stats['failed']++;
            }
        }
        $refs = $this->store->rows("SELECT provider_reference FROM payment_attempts WHERE state IN ('created','authorized','capturing')
            OR (state='captured' AND (booking IN ('none','unsettled') OR captured_minor IS NULL OR captured_currency IS NULL))
            ORDER BY last_checked_at NULLS FIRST,id LIMIT $limit");
        foreach ($refs as $ref) {
            try {
                $this->synchronize($ref['provider_reference']);
                $stats['attempts']++;
            } catch (Throwable $error) {
                $stats['failed']++;
            }
        }
        $expired = $this->store->rows("UPDATE payment_links SET state='expired' WHERE state='active' AND expires_at<=now() RETURNING id");
        $stats['expired'] = count($expired);
        return $stats;
    }

    /** Registration is installation-local and serialized. No marker is saved without a usable secret. */
    public static function ensureWebhook($connection, int $tenantId): bool
    {
        $store = new MobileSchema($connection);
        $store->execute("SELECT pg_advisory_lock(hashtext('saldi:invoice:webhook'))");
        try {
            $settings = [];
            foreach ($store->rows("SELECT var_grp,var_name,var_value FROM settings WHERE var_grp IN ('mobilepay','mobile_betaling') AND user_id=0 ORDER BY id") as $row) {
                $settings[$row['var_grp']][$row['var_name']] = $row['var_value'];
            }
            $base = rtrim(trim((string)ifset($settings, ['mobile_betaling','public_base_url'], '')), '/');
            $parts = parse_url($base);
            if (!$parts || ifset($parts, 'scheme') !== 'https' || !isset($parts['host']) || isset($parts['user'])
                || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || preg_match('/[\r\n]/', $base)) {
                return false;
            }
            $provider = new VippsEpaymentProvider($connection);
            if (!$provider->configured()) {
                return false;
            }
            $expected = $base . '/debitor/payments/link/webhook.php?t=' . (int)$tenantId;
            $mp = ifset($settings, 'mobilepay', []);
            $secret = (string)ifset($mp, 'invoice_webhook_secret', '');
            if (ifset($mp, 'invoice_webhook_url') === $expected && strlen($secret) >= 16) {
                return true;
            }
            $config = ['expectedUrl'=>$expected, 'db'=>$store->rows('SELECT current_database() AS db')[0]['db'],
                'apiBase'=>ifset($mp, 'epayment_api_base', 'https://api.vipps.no'), 'receiverBasename'=>'webhook.php',
                'events'=>array_values(array_merge(['epayments.payment.captured.v1'], array_filter(mobilepay_webhook_events(), static fn(string $event): bool => str_starts_with($event, 'epayments.')))),
                'clientId'=>$mp['client_id'], 'clientSecret'=>$mp['client_secret'], 'subscriptionKey'=>$mp['subscriptionKey'], 'msn'=>$mp['MSN']];
            // Losing the secret requires replacing this exact receiver registration to obtain a new one.
            $config['replaceExpected'] = strlen($secret) < 16;
            self::setting($store, 'invoice_webhook_url', '');
            $result = mobilepay_webhook_sync($config);
            $store->execute('BEGIN');
            if (is_string($result['secret']) && strlen($result['secret']) >= 16) {
                self::setting($store, 'invoice_webhook_secret', $result['secret']);
                $secret = $result['secret'];
            }
            if ($result['reconciled'] && strlen($secret) >= 16) {
                self::setting($store, 'invoice_webhook_url', $expected);
                $store->execute('COMMIT');
                return true;
            }
            $store->execute('COMMIT');
            return false;
        } catch (Throwable $error) {
            if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
                $store->execute('ROLLBACK');
            }
            throw $error;
        } finally {
            $store->execute("SELECT pg_advisory_unlock(hashtext('saldi:invoice:webhook'))");
        }
    }

    private static function setting(MobileSchema $store, string $name, string $value): void
    {
        $key = $store->literal($name);
        $updated = $store->rows("UPDATE settings SET var_value=" . $store->literal($value)
            . " WHERE var_grp='mobilepay' AND user_id=0 AND var_name=$key RETURNING id");
        if (!$updated) {
            $store->execute("INSERT INTO settings(var_grp,var_name,var_value,user_id) VALUES('mobilepay',$key," . $store->literal($value) . ',0)');
        }
    }

    /** @return array<string,mixed>|null */
    private function attempt(string $reference): ?array
    {
        $rows = $this->store->rows('SELECT a.*,l.openpost_id FROM payment_attempts a JOIN payment_links l ON l.id=a.link_id WHERE a.provider_reference=' . $this->store->literal($reference));
        return ifset($rows, 0);
    }

    private function terminal(string $reference, string $state): void
    {
        $this->store->execute('BEGIN');
        try {
            $attempt = $this->attempt($reference);
            $this->links->lockGroup((int)$attempt['openpost_id']);
            $this->store->execute('UPDATE payment_attempts SET state=' . $this->store->literal($state) . ',updated_at=now() WHERE provider_reference='
                . $this->store->literal($reference) . " AND state<>'captured'");
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    private function finishEvent(int $id, string $state, ?string $error = null): void
    {
        $this->store->execute('UPDATE payment_provider_events SET state=' . $this->store->literal($state)
            . ',error=' . ($error === null ? 'NULL' : $this->store->literal($error)) . ',processed_at=now() WHERE id=' . (int)$id . " AND state IN ('received','failed')");
    }

    /** Canonical payload name, also accepting older subscription-shaped callbacks. */
    private static function eventName(string $name): string
    {
        return preg_replace('/^epayments\.payment\.(.*)\.v1$/', '$1', strtolower($name));
    }

    /** @return array{state:string,booking:string,busy:bool} */
    private function result(array $attempt): array
    {
        return ['state'=>$attempt['state'],'booking'=>$attempt['booking'],'busy'=>false];
    }

    private static function money(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function rollback(): void
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            $this->store->execute('ROLLBACK');
        }
    }
}
