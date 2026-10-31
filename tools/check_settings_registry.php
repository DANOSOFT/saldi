<?php
// tools/check_settings_registry.php
// 20260929 Sawaneh Settings redesign §8.14: consistency check of registry v2, run from the command
// line (php tools/check_settings_registry.php). Exit code 1 when an entry is incomplete:
// label/help text ids missing in any of the three languages, unknown permission key, no old-menu
// ('legacy') path, unknown section or sub-section, or a section without 'context' pages.
// 20261004 Sawaneh Runs without a database again: stand-in db functions answer one sample row for the per-till,
//                  per-price-list and per-language expansions.

if (php_sapi_name() !== 'cli') {
	exit;
}
$root = dirname(__DIR__);
$texts = array();
$fp = fopen($root . '/importfiler/tekster.csv', 'r');
while (($line = fgets($fp)) !== false) {
	$c = explode("\t", rtrim($line, "\r\n"));
	if (isset($c[3])) {
		$texts[$c[0]] = array($c[1], $c[2], $c[3]);
	}
}
fclose($fp);
function findtekst($id, $sprog = 1) { return (string) $id; }
// No database here: the registry expands some fields per till, price list and form language from the company's rows,
// so every query answers one sample row and every expanded field is still checked once.
if (!function_exists('db_select')) {
	function db_select($q, $f = '') { static $n = 0; return 'r' . (++$n); }
	function db_fetch_array($r) {
		static $done = array();
		if (isset($done[$r])) {
			return false;
		}
		$done[$r] = true;
		return array('id' => 1, 'box1' => '1', 'box9' => '', 'beskrivelse' => 'sample', 'kodenr' => '1', 0 => '1');
	}
	function db_escape_string($s) { return addslashes((string) $s); }
}
function perm_can($k, $l = 'read') { return true; }
require $root . '/includes/permissionRegistry.php';
require $root . '/systemdata/settingsRegistry.php';

$errors = array();
$textOk = function ($id, $what) use ($texts, &$errors) {
	$id = (string) (int) $id;
	if (!isset($texts[$id])) {
		$errors[] = "$what: text $id is not in tekster.csv";
	} elseif (in_array('', array_map('trim', $texts[$id]), true)) {
		$errors[] = "$what: text $id lacks a language";
	}
};
$permissions = permission_registry();
$sections = getSettingsSections();
$groups = getSettingsGroups();
foreach ($sections as $id => $section) {
	$textOk($section['label'], "section $id");
	if (!isset($groups[$section['group']])) {
		$errors[] = "section $id: unknown group";
	}
	if (empty($section['legacy'])) {
		$errors[] = "section $id: no legacy path";
	}
	if (empty($section['context'])) {
		$errors[] = "section $id: no context pages";
	}
	foreach ($section['subsections'] as $sub => $label) {
		$textOk($label, "section $id / $sub");
	}
}
$storage = array();
foreach (getSettingDefinitions() as $key => $def) {
	$textOk($def['label'], $key . ' label');
	if (!isset($def['help'])) {
		$errors[] = "$key: no help text";
	} else {
		$textOk($def['help'], $key . ' help');
	}
	if ($def['permission'] !== 'any' && !isset($permissions[$def['permission']])) {
		$errors[] = "$key: unknown permission " . $def['permission'];
	}
	if (empty($def['legacy'])) {
		$errors[] = "$key: no legacy path";
	} else {
		foreach ($def['legacy'] as $t) {
			$textOk($t, $key . ' legacy');
		}
	}
	$sectionId = $def['group'] . '.' . $def['section'];
	if ($def['group'] !== 'personal') {
		if (!isset($sections[$sectionId])) {
			$errors[] = "$key: unknown section $sectionId";
		} elseif (!isset($sections[$sectionId]['subsections'][$def['sub']])) {
			$errors[] = "$key: unknown sub-section " . $def['sub'];
		}
	}
	if ($def['type'] === 'select') {
		foreach ($def['options'] as $label) {
			$textOk($label, $key . ' option');
		}
	}
	if ($def['type'] === 'action') {
		$textOk($def['confirm_title'], $key . ' confirm title');
		$textOk($def['confirm'], $key . ' confirm');
		continue;
	}
	if (empty($def['storage'])) {
		$errors[] = "$key: no storage";
		continue;
	}
	$s = $def['storage'];
	$where = implode('/', array((string) $s[0], (string) $s[1], (string) $s[2], $s[0] === 'grupper' ? (string) $s[3] : '', isset($s['index']) ? (string) $s['index'] : ''));
	if (isset($storage[$where])) {
		$errors[] = "$key: same storage as " . $storage[$where];
	}
	$storage[$where] = $key;
}
foreach (getSettingsMovedMap() as $row) {
	foreach ($row['old'] as $t) {
		$textOk($t, 'moved map');
	}
}
if ($errors) {
	echo implode("\n", $errors), "\n", count($errors), " problem(s)\n";
	exit(1);
}
echo count(getSettingDefinitions()), " settings in ", count($sections), " sections: registry is consistent\n";
