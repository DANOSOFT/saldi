<?php
// tests/shopify/support/build_replay_day.php
//
// Writes the NF-1 replay day (SD-766): tests/shopify/fixtures/day.json and graphql.json from
// ReplayDay::build(), the same bytes for the same seed. No database, no network, no secrets.
// After it, refresh expected.json on a test tenant with run_replay.php record, and review the
// three files together (tests/shopify/support/FAKE-STORE.md has the steps):
//
//   php tests/shopify/support/build_replay_day.php [seed] [dir]   (dir defaults to tests/shopify/fixtures)
//   php tests/shopify/support/run_replay.php saldi_shopify_test_<x> record   (SALDI_SHOPIFY_* env as for the suite)
//
// History:
// 20261103 CL/LH SD-766: created.
// 20261103 CL/LH SD-766: review fixes: points at the refresh and review steps in FAKE-STORE.md.

if (PHP_SAPI !== 'cli') {
	exit;
}
require_once __DIR__ . '/../../../includes/shopify/events.php';
require_once __DIR__ . '/ReplayDay.php';

$built = ReplayDay::build((int)($argv[1] ?? ReplayDay::SEED));
$dir = $argv[2] ?? ReplayDay::FIXTURES;
foreach (['day', 'graphql'] as $name) {
	file_put_contents("$dir/$name.json", ReplayDay::encode($built[$name]));
	echo "wrote $dir/$name.json\n";
}
echo count($built['day']['deliveries']) . " deliveries\n";
