<?php
// 20261022 CDX/LH Enforce Stripe's unchanged-body contract when tests reuse an idempotency key.

require_once __DIR__ . '/bootstrap.php';

final class StripeTransportTest extends PHPUnit\Framework\TestCase
{
    public function testFakeRejectsTheSameIdempotencyKeyWithADifferentBody(): void
    {
        StripeTestTransport::reset();
        try {
            $params = ['amount' => 34000, 'payment_method_types' => ['card']];
            $opts = ['idempotency_key' => 'transport-contract'];
            StripeTestTransport::queueBody(['id' => 'pi_contract'], 'POST', '/v1/payment_intents');
            $this->assertTrue(stripeHttpRequest('POST', '/v1/payment_intents', $params, $opts)['ok']);
            StripeTestTransport::queueBody(['id' => 'pi_contract'], 'POST', '/v1/payment_intents');
            $this->assertTrue(stripeHttpRequest('POST', '/v1/payment_intents', $params, $opts)['ok']);
            $params['setup_future_usage'] = 'off_session';
            $result = stripeHttpRequest('POST', '/v1/payment_intents', $params, $opts);
            $this->assertFalse($result['ok']);
            $this->assertSame(400, $result['status']);
            $this->assertSame('idempotency_error', $result['body']['error']['type']);
            $this->assertCount(3, StripeTestTransport::$requests);
            $this->assertSame([], StripeTestTransport::$responses);
        } finally {
            StripeTestTransport::reset();
        }
    }
}
