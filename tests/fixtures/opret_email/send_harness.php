<?php
// Runs includes/opretEmailFunc.php's opret_email_build()/opret_email_send() for
// OpretEmailTest without a database: the two db-backed lookups are replaced by
// stubs fed from the scenario file, and mail() is captured through sendmail_path.
//
// Usage: php -d sendmail_path="cat >> /tmp/x" send_harness.php <scenario.json>
// Scenario: {"action": "send"|"build", "settings": {"emne","html","afsender"},
//            "package": {"navn","pris"}|null, "to", "kode", "vars": {...}}
// Prints the function's return value as JSON.

$scenario = json_decode((string) file_get_contents($argv[1]), true);

function opret_email_settings($sprog = 1)
{
	global $scenario;
	return $scenario['settings'] + array('emne' => '', 'html' => '', 'afsender' => '');
}

function opret_email_package($kode)
{
	global $scenario;
	return $scenario['package'];
}

require dirname(__DIR__, 3) . '/includes/std_func.php';
require dirname(__DIR__, 3) . '/includes/opretEmailFunc.php';

if ($scenario['action'] === 'build') {
	$out = opret_email_build($scenario['kode'], $scenario['vars']);
} else {
	$out = opret_email_send($scenario['to'], $scenario['kode'], $scenario['vars']);
}
echo json_encode($out);
