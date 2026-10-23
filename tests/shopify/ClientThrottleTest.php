<?php
// tests/shopify/ClientThrottleTest.php
//
// shopify_throttle_delay(): pacing from the reported throttleStatus, never
// from hardcoded bucket sizes, and no division by zero (SD-747).
//
// History:
// 20261021 CL/SZ created (SD-747).

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/shopify/client.php';

final class ClientThrottleTest extends TestCase
{
	private const BUCKET = ['maximumAvailable' => 2000.0, 'currentlyAvailable' => 100.0, 'restoreRate' => 100.0, 'at' => 50.0];

	public function test_no_wait_when_the_bucket_holds_the_cost(): void
	{
		$this->assertSame(0.0, shopify_throttle_delay(self::BUCKET, 100.0, 50.0));
	}

	public function test_waits_for_the_missing_points_at_the_restore_rate(): void
	{
		// 300 needed, 100 available, 100 points/s back.
		$this->assertEqualsWithDelta(2.0, shopify_throttle_delay(self::BUCKET, 300.0, 50.0), 1e-9);
	}

	public function test_time_since_the_observation_counts_as_restored(): void
	{
		// 1.5 s after the observation the bucket holds 250.
		$this->assertEqualsWithDelta(0.5, shopify_throttle_delay(self::BUCKET, 300.0, 51.5), 1e-9);
		$this->assertSame(0.0, shopify_throttle_delay(self::BUCKET, 300.0, 60.0));
	}

	public function test_uses_the_shops_reported_bucket_not_a_fixed_tier(): void
	{
		// A Plus-tier bucket (20000 / 1000) restores ten times faster than Standard.
		$plus = ['maximumAvailable' => 20000.0, 'currentlyAvailable' => 0.0, 'restoreRate' => 1000.0, 'at' => 0.0];
		$this->assertEqualsWithDelta(0.5, shopify_throttle_delay($plus, 500.0, 0.0), 1e-9);
	}

	public function test_empty_bucket_means_nothing_known_so_no_wait(): void
	{
		$this->assertSame(0.0, shopify_throttle_delay([], 500.0, 10.0));
		$this->assertSame(0.0, shopify_throttle_delay(['maximumAvailable' => 0, 'currentlyAvailable' => 0], 500.0, 10.0));
	}

	public function test_zero_or_missing_restore_rate_does_not_divide_by_zero(): void
	{
		$zero = ['maximumAvailable' => 2000.0, 'currentlyAvailable' => 10.0, 'restoreRate' => 0.0, 'at' => 0.0];
		$missing = ['maximumAvailable' => 2000.0, 'currentlyAvailable' => 10.0];

		$this->assertSame(SHOPIFY_THROTTLE_FALLBACK_DELAY, shopify_throttle_delay($zero, 500.0, 5.0));
		$this->assertSame(SHOPIFY_THROTTLE_FALLBACK_DELAY, shopify_throttle_delay($missing, 500.0, 5.0));
		$this->assertSame(0.0, shopify_throttle_delay($zero, 5.0, 5.0));
	}

	public function test_cost_above_the_bucket_size_waits_only_for_a_full_bucket(): void
	{
		$bucket = ['maximumAvailable' => 1000.0, 'currentlyAvailable' => 900.0, 'restoreRate' => 50.0, 'at' => 0.0];
		$this->assertEqualsWithDelta(2.0, shopify_throttle_delay($bucket, 5000.0, 0.0), 1e-9);
	}

	public function test_clock_before_the_observation_is_not_negative_restore(): void
	{
		$this->assertEqualsWithDelta(2.0, shopify_throttle_delay(self::BUCKET, 300.0, 40.0), 1e-9);
	}
}
