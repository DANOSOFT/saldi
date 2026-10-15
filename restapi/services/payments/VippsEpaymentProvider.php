<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/payments/VippsEpaymentProvider.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261001 CDX/LH Normalize cancellation aggregates and distinguish authoritative payment absence.
// 20261005 CDX/LH Resolve successful capture timestamps from the authoritative event log.

require_once __DIR__ . '/PaymentLinkProvider.php';
require_once __DIR__ . '/../../core/Idempotency.php';
require_once __DIR__ . '/../../../includes/stdFunc/mobilepayWebhookSync.php';

/** Vipps ePayment WALLET / WEB_REDIRECT using the existing bounded HTTP transport. */
final class VippsEpaymentProvider implements PaymentLinkProvider
{
    /** Trusted redirect origins from the provider's servers documentation, shared with the pay-page CSP. */
    public const REDIRECT_ORIGINS = [
        'https://api.vipps.no','https://apitest.vipps.no',
        'https://pay.vipps.no','https://pay-mt.vipps.no',
        'https://pay.mobilepay.dk','https://pay-mt.mobilepay.dk',
        'https://pay.mobilepay.fi','https://pay-mt.mobilepay.fi',
    ];
    private array $settings;
    private string $base;
    private ?string $accessToken = null;

    public function __construct($connection)
    {
        $store = new MobileSchema($connection);
        $this->settings = [];
        foreach ($store->rows("SELECT var_name,var_value FROM settings WHERE var_grp='mobilepay' AND user_id=0 ORDER BY id") as $row) {
            $this->settings[$row['var_name']] = trim((string)$row['var_value']);
        }
        $this->base = rtrim((string)ifset($this->settings, 'epayment_api_base', 'https://api.vipps.no'), '/');
        $url = parse_url($this->base);
        // Plain HTTP is confined to the in-container stub / local development.
        if (!$url || !in_array(ifset($url, 'scheme'), ['https','http'], true)
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
            || (ifset($url, 'scheme') === 'http' && !in_array(ifset($url, 'host'), ['127.0.0.1','localhost'], true))) {
            throw new MobileT2Error('not_configured', 422, ['reason'=>'provider_not_configured']);
        }
    }

    public function configured(): bool
    {
        foreach (['client_id','client_secret','subscriptionKey','MSN'] as $key) {
            $value = (string)ifset($this->settings, $key, '');
            if ($value === '' || preg_match('/[\r\n]/', $value)) {
                return false;
            }
        }
        return true;
    }

    /** @return array{reference:string,state:string,redirect_url:string} */
    public function create(string $reference, int $amountMinor, string $currency, string $returnUrl, string $description): array
    {
        $row = $this->request('POST', '/epayment/v1/payments', [
            'amount'=>['value'=>$amountMinor,'currency'=>$currency], 'paymentMethod'=>['type'=>'WALLET'],
            'reference'=>$reference, 'userFlow'=>'WEB_REDIRECT', 'returnUrl'=>$returnUrl,
            'paymentDescription'=>mb_substr($description, 0, 100),
        ], $reference . '-create');
        $redirect = ifset($row, 'redirectUrl');
        if (!is_string($redirect) || !self::safeRedirect($redirect)) {
            throw new MobileT2Error('upstream_unavailable', 503);
        }
        return ['reference'=>$reference,'state'=>'created','redirect_url'=>$redirect];
    }

    /** @return array{reference:string,state:string,amount_minor:int,currency:string,captured_minor:int,captured_at:string|null,psp_reference:string|null} */
    public function get(string $reference): array
    {
        $row = $this->request('GET', '/epayment/v1/payments/' . rawurlencode($reference), null, null, true);
        $state = strtolower((string)ifset($row, 'state', ''));
        $amount = ifset($row, ['amount','value']);
        $currency = ifset($row, ['amount','currency']);
        $captured = ifset($row, ['aggregate','capturedAmount','value'], 0);
        $cancelledAmount = ifset($row, ['aggregate','cancelledAmount'], ['value'=>0,'currency'=>$currency]);
        $cancelled = ifset($cancelledAmount,'value');
        if (ifset($row,'reference') !== $reference
            || ifset($row,['aggregate','capturedAmount','currency'],$currency) !== $currency
            || ifset($cancelledAmount,'currency') !== $currency
            || !in_array($state, ['created','authorized','captured','aborted','expired','cancelled','terminated'], true)
            || !is_int($amount) || $amount <= 0 || !is_int($captured) || $captured < 0
            || !is_int($cancelled) || $cancelled < 0 || $captured > $amount || $cancelled > $amount-$captured
            || !is_string($currency) || !preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new MobileT2Error('upstream_unavailable', 503);
        }
        // AUTHORIZED also persists after cancellation. Captured funds must still be booked/reviewed.
        $normalized = $captured > 0 ? 'captured' : ($state === 'authorized' && $cancelled === $amount ? 'cancelled' : $state);
        return ['reference'=>$reference,'state'=>$normalized,
            'amount_minor'=>$amount,'currency'=>$currency,'captured_minor'=>$captured,
            'captured_at'=>$captured > 0 ? $this->captureTime($reference, $captured, $currency) : null,
            'psp_reference'=>is_string(ifset($row,'pspReference')) ? $row['pspReference'] : null];
    }

    /** Use the last successful capture's time only when the event totals reconcile with the payment. */
    private function captureTime(string $reference, int $capturedMinor, string $currency): string
    {
        $events = $this->request('GET', '/epayment/v1/payments/' . rawurlencode($reference) . '/events');
        if (!array_is_list($events)) {
            throw new MobileT2Error('upstream_unavailable', 503);
        }
        $total = 0;
        $last = null;
        $seen = [];
        foreach ($events as $event) {
            if (!is_array($event) || !is_string(ifset($event, 'name'))) {
                throw new MobileT2Error('upstream_unavailable', 503);
            }
            if (strtoupper($event['name']) !== 'CAPTURED' || ifset($event, 'success') !== true) {
                continue;
            }
            $amount = ifset($event, ['amount','value']);
            $timestamp = ifset($event, 'timestamp');
            $psp = ifset($event, 'pspReference');
            if (ifset($event, 'reference') !== $reference || ifset($event, ['amount','currency']) !== $currency
                || !is_int($amount) || $amount <= 0 || $amount > $capturedMinor - $total
                || !is_string($psp) || $psp === '' || isset($seen[$psp])
                || !is_string($timestamp) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $timestamp)) {
                throw new MobileT2Error('upstream_unavailable', 503);
            }
            try {
                $time = new DateTimeImmutable($timestamp);
            } catch (Exception $error) {
                throw new MobileT2Error('upstream_unavailable', 503);
            }
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                throw new MobileT2Error('upstream_unavailable', 503);
            }
            $total += $amount;
            $seen[$psp] = true;
            if ($last === null || $time > $last) {
                $last = $time;
            }
        }
        if ($total !== $capturedMinor || $last === null) {
            throw new MobileT2Error('upstream_unavailable', 503);
        }
        return $last->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }

    public function cancel(string $reference): bool
    {
        $this->request('POST', '/epayment/v1/payments/' . rawurlencode($reference) . '/cancel', [], $reference . '-cancel');
        return in_array($this->get($reference)['state'], ['cancelled','aborted','expired','terminated'], true);
    }

    /** @return array{reference:string,state:string,amount_minor:int,currency:string,captured_minor:int,captured_at:string|null,psp_reference:string|null} */
    public function capture(string $reference, int $amountMinor, string $currency): array
    {
        $this->request('POST', '/epayment/v1/payments/' . rawurlencode($reference) . '/capture',
            ['modificationAmount'=>['value'=>$amountMinor,'currency'=>$currency]], $reference . '-capture');
        return $this->get($reference);
    }

    /** Only browser HTTP(S) locations without credentials or header injection. */
    public static function safeRedirect(string $url): bool
    {
        $parts = parse_url($url);
        return $parts !== false && isset($parts['host']) && in_array(ifset($parts,'scheme'), ['https','http'], true)
            && !isset($parts['user']) && !isset($parts['pass']) && !preg_match('/[\r\n]/', $url)
            && ($parts['scheme'] === 'https' || in_array($parts['host'], ['localhost','127.0.0.1'], true));
    }

    /** @return array<string,mixed> */
    private function request(string $method, string $path, ?array $body = null, ?string $key = null, bool $allowNotFound = false): array
    {
        if ($key !== null && !preg_match('/^saldi-[A-Za-z0-9-]+-(create|capture|cancel)$/D',$key)) {
            throw new InvalidArgumentException('Invalid ePayment idempotency key');
        }
        if (!$this->configured()) {
            throw new MobileT2Error('not_configured', 422, ['reason'=>'provider_not_configured']);
        }
        if ($this->accessToken === null) {
            $reply = mobilepay_webhook_call('POST', $this->base . '/accesstoken/get', [
                'Content-Type: application/json', 'Client_id: '.$this->settings['client_id'],
                'Client_secret: '.$this->settings['client_secret'], 'Ocp-Apim-Subscription-Key: '.$this->settings['subscriptionKey'],
                'Merchant-Serial-Number: '.$this->settings['MSN'],
            ], '');
            $token = json_decode($reply['body'], true);
            if (!$reply['ok'] || !is_string(ifset($token,'access_token')) || $token['access_token'] === ''
                || preg_match('/[\r\n]/', $token['access_token'])) {
                throw new MobileT2Error('upstream_unavailable', 503);
            }
            $this->accessToken = $token['access_token'];
        }
        $headers = ['Content-Type: application/json', 'Authorization: Bearer '.$this->accessToken,
            'Ocp-Apim-Subscription-Key: '.$this->settings['subscriptionKey'], 'Merchant-Serial-Number: '.$this->settings['MSN'],
            'Vipps-System-Name: Saldi', 'Vipps-System-Version: 5.0.0',
            'Vipps-System-Plugin-Name: saldi-mobile', 'Vipps-System-Plugin-Version: 1.0.0'];
        if ($key !== null) {
            $headers[] = 'Idempotency-Key: '.$key;
        }
        $reply = mobilepay_webhook_call($method, $this->base.$path, $headers,
            $body === null ? null : json_encode((object)$body, JSON_THROW_ON_ERROR));
        if ($allowNotFound && $reply['httpCode'] === 404 && $reply['error'] === '') {
            throw new MobileT2Error('upstream_unavailable',503,['reason'=>'provider_payment_not_found']);
        }
        $decoded = $reply['body'] === '' ? [] : json_decode($reply['body'], true);
        if (!$reply['ok'] || !is_array($decoded)) {
            throw new MobileT2Error('upstream_unavailable', 503);
        }
        return $decoded;
    }
}
