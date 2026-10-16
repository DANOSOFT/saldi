<?php
// ---- index/globalSearch.php --- lap 5.0.0 --- 2026.10.05 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261005 Sawaneh Topbar addendum 2026-10-05 §6: record lookups for the global search in the top bar - customers,
//                  suppliers, orders/invoices, cash journals, items and accounts - and the recently visited pages.
//                  Read-only JSON; every group only for users who may open that area; at most 4 per group with
//                  LIMIT in SQL. Pages and settings are matched in the shell (sidebar) and by settingsSearch.php.

ob_start();
@session_start();
$s_id = session_id();
$title = "globalSearch";
$webservice = true;
$modulnr = 0;
$permission_key = 'any';
$permission_post_read = true;

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$charset = (isset($db_encode) && $db_encode !== 'UTF8') ? 'ISO-8859-1' : 'UTF-8';
$utf = function ($s) use ($charset): string {
	return $charset === 'UTF-8' ? (string) $s : mb_convert_encoding((string) $s, 'UTF-8', $charset);
};
$txt = function (string $t) use ($utf, $sprog_id): string {
	return html_entity_decode($utf(findtekst($t, $sprog_id)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
};
$can = function (string $key): bool {
	return !function_exists('perm_can') || perm_can($key, 'read');
};

// Recently visited (§6.1): the navigation stack, newest first, without the page now open.
if (!empty($_GET['recent'])) {
	$out = array();
	if (function_exists('_nav_read')) {
		$stack = array_reverse(_nav_read());
		array_shift($stack);
		$seen = array();
		foreach ($stack as $url) {
			$path = function_exists('page_chrome_path') ? page_chrome_path((string) $url) : '';
			if ($path === '' || isset($seen[$path]) || $path === 'index/dashboard.php' || $path === 'index/menu.php') {
				continue;
			}
			$seen[$path] = true;
			$query = strpos((string) $url, '?') !== false ? substr((string) $url, strpos((string) $url, '?')) : '';
			$label = isset($_SESSION['page_chrome'][$path]['label']) ? (string) $_SESSION['page_chrome'][$path]['label'] : '';
			$out[] = array('href' => '/' . $path . $query, 'path' => '/' . $path, 'label' => $label);
			if (count($out) >= 5) {
				break;
			}
		}
	}
	echo json_encode(array('recent' => $out), JSON_UNESCAPED_UNICODE);
	exit;
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$groups = array();
if (mb_strlen($q) >= 2 && mb_strlen($q) <= 60) {
	$qDb = $charset === 'UTF-8' ? $q : mb_convert_encoding($q, $charset, 'UTF-8');
	$like = db_escape_string(str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), mb_strtolower($qDb)));
	$isNum = ctype_digit($q);
	$num = $isNum ? (int) $q : 0;
	$rows = function (string $sql) {
		$out = array();
		$res = db_select($sql, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($res)) {
			$out[] = $r;
		}
		return $out;
	};

	foreach (array('D' => array('debitor.konti', '991|Kunder', '/debitor/debitorkort.php?id='), 'K' => array('kreditor.konti', '988|Leverandører', '/kreditor/kreditorkort.php?id=')) as $art => $g) {
		if (!$can($g[0])) {
			continue;
		}
		$sql = "select id, kontonr, firmanavn from adresser where art = '$art' and (lukket is null or lukket != 'on') ";
		$sql .= "and (cast(kontonr as text) like '$like%' or lower(firmanavn) like '%$like%' or cast(cvrnr as text) like '$like%') order by firmanavn limit 4";
		$items = array();
		foreach ($rows($sql) as $r) {
			$items[] = array('label' => $utf($r['firmanavn']), 'sub' => $utf($r['kontonr']), 'href' => $g[2] . (int) $r['id']);
		}
		if ($items) {
			$groups[] = array('id' => $art === 'D' ? 'customers' : 'suppliers', 'label' => $txt($g[1]), 'items' => $items);
		}
	}

	$docs = array();
	if ($isNum && ($can('debitor.ordre') || $can('kreditor.ordre'))) {
		$arts = array();
		if ($can('debitor.ordre')) {
			$arts[] = "art like 'D%'";
		}
		if ($can('kreditor.ordre')) {
			$arts[] = "art like 'K%'";
		}
		$sql = "select id, art, ordrenr, fakturanr, firmanavn from ordrer where (" . implode(' or ', $arts) . ") ";
		$sql .= "and (ordrenr = $num or fakturanr = '" . db_escape_string($q) . "') order by id desc limit 4";
		foreach ($rows($sql) as $r) {
			$debtor = (substr((string) $r['art'], 0, 1) === 'D');
			$invoice = trim((string) $r['fakturanr']) === $q;
			$label = $invoice ? sprintf($txt('6449|Faktura %s'), $q) : sprintf($txt($debtor ? '6448|Ordre %s' : '6452|Indkøbsordre %s'), (int) $r['ordrenr']);
			$docs[] = array('label' => $label, 'sub' => $utf($r['firmanavn']), 'href' => ($debtor ? '/debitor/ordre.php?id=' : '/kreditor/ordre.php?id=') . (int) $r['id']);
		}
	}
	if ($isNum && $can('finans.kassekladde')) {
		foreach ($rows("select id, kladdenote from kladdeliste where id = $num limit 1") as $r) {
			$docs[] = array('label' => sprintf($txt('6450|Kassekladde %s'), (int) $r['id']), 'sub' => $utf($r['kladdenote']), 'href' => '/finans/kassekladde.php?kladde_id=' . (int) $r['id']);
		}
	}
	if ($docs) {
		$groups[] = array('id' => 'documents', 'label' => $txt('6447|Bilag og fakturaer'), 'items' => array_slice($docs, 0, 4));
	}

	if ($can('lager.varer')) {
		$sql = "select id, varenr, beskrivelse from varer where (lukket is null or lukket != 'on') ";
		$sql .= "and (lower(varenr) like '$like%' or lower(beskrivelse) like '%$like%') order by varenr limit 4";
		$items = array();
		foreach ($rows($sql) as $r) {
			$items[] = array('label' => $utf($r['beskrivelse']), 'sub' => $utf($r['varenr']), 'href' => '/lager/varekort.php?id=' . (int) $r['id']);
		}
		if ($items) {
			$groups[] = array('id' => 'items', 'label' => $txt('609|Varer'), 'items' => $items);
		}
	}

	if ($can('system.kontoplan')) {
		$sql = "select id, kontonr, beskrivelse from kontoplan where regnskabsaar = " . (int) $regnaar . " ";
		$sql .= "and (cast(kontonr as text) like '$like%' or lower(beskrivelse) like '%$like%') order by kontonr limit 4";
		$items = array();
		foreach ($rows($sql) as $r) {
			$items[] = array('label' => $utf($r['beskrivelse']), 'sub' => $utf($r['kontonr']), 'href' => '/systemdata/kontokort.php?id=' . (int) $r['id']);
		}
		if ($items) {
			$groups[] = array('id' => 'accounts', 'label' => $txt('606|Konti'), 'items' => $items);
		}
	}
}

echo json_encode(array('q' => $q, 'groups' => $groups), JSON_UNESCAPED_UNICODE);
exit;
