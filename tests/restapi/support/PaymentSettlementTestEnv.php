<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/PaymentSettlementTestEnv.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261005 CDX/LH Use provider-shaped webhook payloads and documented authentication headers.

require_once __DIR__ . '/PaymentLinkTestEnv.php';
require_once __DIR__ . '/../../../restapi/services/payments/PaymentSettlementService.php';

/** Observe the committed capture reservation, or inject an explicit provider refusal. */
final class PaymentCaptureProbe implements PaymentLinkProvider
{
    public function __construct(private PaymentLinkProvider $delegate, private Closure $beforeCapture)
    {
    }
    public function configured(): bool
    {
        return $this->delegate->configured();
    }
    public function create(string $reference, int $amountMinor, string $currency, string $returnUrl, string $description): array
    {
        return $this->delegate->create($reference, $amountMinor, $currency, $returnUrl, $description);
    }
    public function get(string $reference): array
    {
        return $this->delegate->get($reference);
    }
    public function cancel(string $reference): bool
    {
        return $this->delegate->cancel($reference);
    }
    public function capture(string $reference, int $amountMinor, string $currency): array
    {
        ($this->beforeCapture)($reference);
        return $this->delegate->capture($reference, $amountMinor, $currency);
    }
}

/** Local-only signed webhook fixtures and state observations. */
final class PaymentSettlementTestEnv
{
    /** @return array{link:array,attempt:array} */
    public static function attempt(): array
    {
        $body = PaymentLinkTestEnv::body();
        $link = PaymentLinkTestEnv::service()->create($body)['body']['data'];
        $link['openpost_id'] = $body['openpost_id'];
        $started = PaymentLinkTestEnv::service()->startAttempt($link['id'], $link['url']);
        $attempt = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT * FROM payment_attempts WHERE id=$1', [$started['attempt_id']])[0];
        return ['link'=>$link,'attempt'=>$attempt];
    }

    public static function context($connection = null): EngineContext
    {
        return new EngineContext($connection ?? PaymentLinkTestEnv::$tenant, RestApiEnv::testDb(),
            RestApiEnv::regnskabId(RestApiEnv::accountOpen()), RestApiEnv::USER, (int)RestApiEnv::loginData()['user']['id'], '2026-10-04');
    }

    public static function settlement($connection = null): PaymentSettlementService
    {
        $context = self::context($connection);
        return new PaymentSettlementService($context, PaymentLinkTestEnv::subject(), new VippsEpaymentProvider($context->connection), PaymentLinkTestEnv::$registry);
    }

    public static function remote(array $attempt, string $state, ?int $captured = null, ?int $amount = null, string $currency = 'DKK'): void
    {
        PaymentLinkTestEnv::control(['reference'=>$attempt['provider_reference'], 'payment'=>[
            'reference'=>$attempt['provider_reference'], 'state'=>$state, 'amount'=>['value'=>$amount ?? (int)$attempt['amount_minor'],'currency'=>$currency],
            'aggregate'=>['capturedAmount'=>['value'=>$captured ?? 0,'currency'=>$currency]],
            'pspReference'=>'psp-' . $attempt['id'],
        ]]);
    }

    /** @return array<string,mixed> */
    public static function event(array $attempt, string $name = 'captured'): array
    {
        return ['reference'=>$attempt['provider_reference'],'pspReference'=>'psp-' . $attempt['id'],
            'msn'=>'1234567','name'=>strtoupper($name),'success'=>true,
            'amount'=>['value'=>(int)$attempt['amount_minor'],'currency'=>$attempt['currency']],
            'timestamp'=>gmdate('c'),'idempotencyKey'=>$attempt['provider_reference'] . '-' . $name];
    }

    /** @return array{url:string,body:string,headers:list<string>} */
    public static function signed(array $event, array $overrides = [], bool $pos = false): array
    {
        $tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        $path = $pos ? '/debitor/payments/mobilepay/webhook_recive.php?db=' . RestApiEnv::testDb()
            : '/debitor/payments/link/webhook.php?t=' . $tenantId;
        $url = RestApiEnv::baseUrl() . $path;
        $parts = parse_url($url);
        $target = $parts['path'] . '?' . $parts['query'];
        $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $body = json_encode($event, JSON_THROW_ON_ERROR);
        $date = $overrides['date'] ?? gmdate('D, d M Y H:i:s') . ' GMT';
        $hash = $overrides['hash'] ?? base64_encode(hash('sha256', $body, true));
        $key = $pos ? 'webhook_secret' : 'invoice_webhook_secret';
        $secret = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "SELECT var_value FROM settings WHERE var_grp='mobilepay' AND var_name=$1 AND user_id=0 ORDER BY id DESC LIMIT 1", [$key])[0]['var_value'];
        $signature = $overrides['signature'] ?? base64_encode(hash_hmac('sha256', "POST\n$target\n$date;$host;$hash", $secret, true));
        $authHeader = !empty($overrides['fallback']) ? 'X-Vipps-Authorization' : 'Authorization';
        return ['url'=>$url, 'body'=>$body, 'headers'=>['Content-Type: application/json', 'x-ms-date: ' . $date,
            'x-ms-content-sha256: ' . $hash, "$authHeader: HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=$signature"]];
    }

    /** @return CurlHandle */
    public static function handle(array $signed)
    {
        $ch = curl_init($signed['url']);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$signed['body'],
            CURLOPT_HTTPHEADER=>$signed['headers'],CURLOPT_TIMEOUT=>40]);
        return $ch;
    }

    /** @return array{status:int,body:string} */
    public static function webhook(array $event, array $overrides = [], bool $pos = false): array
    {
        $ch = self::handle(self::signed($event, $overrides, $pos));
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status'=>$status,'body'=>(string)$body];
    }

    public static function row(int $id): array
    {
        return RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT * FROM payment_attempts WHERE id=$1', [$id])[0];
    }
}
