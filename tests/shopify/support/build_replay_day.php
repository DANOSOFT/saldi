<?php
// tests/shopify/support/build_replay_day.php
//
// Writes the NF-1 replay day (SD-766): tests/shopify/fixtures/day.json and graphql.json from
// ReplayDay::build(), the same bytes for the same seed. No database, no network, no secrets.
// After it, refresh expected.json on a test tenant with run_replay.php record:
//
//   php tests/shopify/support/build_replay_day.php [seed]
//   php tests/shopify/support/run_replay.php saldi_shopify_test_<x> record   (SALDI_SHOPIFY_* env as for the suite)
//
// History:
// 20261103 CL/LH SD-766: created.

if (PHP_SAPI !== 'cli') {
	exit;
}
require_once __DIR__ . '/../../../includes/shopify/events.php';
require_once __DIR__ . '/ReplayDay.php';

$built = ReplayDay::build((int)($argv[1] ?? ReplayDay::SEED));
foreach (['day', 'graphql'] as $name) {
	file_put_contents(ReplayDay::FIXTURES . "/$name.json", ReplayDay::encode($built[$name]));
	echo "wrote tests/shopify/fixtures/$name.json\n";
}
echo count($built['day']['deliveries']) . " deliveries\n";
