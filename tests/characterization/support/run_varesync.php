<?php
// tests/characterization/support/run_varesync.php
//
// Child-process runner for the shop item sync (api/varesync.php), the code behind
// "Hent nye varer" (valg 1) and "Opdater fra shop" (valg 2) on the API settings page.
//
// varesync() downloads shop_products.csv and shop_variants.csv with wget from the folder of
// the URL in grupper art='API' box4, and writes them to ../temp/<db>/. The test points box4 at
// a local stub server (varesync_stub_router.php), so the real function runs end to end.
//
// argv: 1 = valg (1 or 2)
//       2 = tenant db
//
// Prints what varesync() printed, then a JSON object on the last line.
//
// varesync() stops with exit when the variant file can't be fetched, so the JSON line is printed
// from a shutdown function.
//
// History:
// 20261125 CL/SZ MB-145 Created.
// 20261125 CL/SZ MB-192 Print the JSON line from a shutdown function, so a run that stops with exit still reports.

if ($argc < 3) {
	fwrite(STDERR, "usage: php run_varesync.php <valg> <tenant_db>\n");
	exit(2);
}
$valg = $argv[1];
$tenantDb = $argv[2];

require __DIR__ . '/bootstrap_ordrefunc.php';

$tempDir = dirname(__DIR__, 3) . "/temp/$db";
if (!is_dir($tempDir)) {
	mkdir($tempDir, 0777, true);
}

require_once dirname(__DIR__, 3) . '/api/varesync.php';

register_shutdown_function(static function (): void {
	$printed = (string)ob_get_clean();
	echo $printed, "\n";
	echo json_encode(['printed' => $printed]), "\n";
});

ob_start();
varesync($valg);
