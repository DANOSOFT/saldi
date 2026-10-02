<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------debitor/rapport.php------ver 5.0.0 ----2026-10-01--------------
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------

// 20121105 - Fejl ved "masseudligning (Klik på 0,00 i åbenpostoversigt) når kun 1 dato sat. Søg 20121105 
// 20170303 - Inlføjet inkasso (ikke aktiv)
// 20180207 - PHR Udlign kan nu bestå af flere kontonumre. Søg udlign
// 20181218 - MSC Rettet isset fejl
// 20190410 - PHR $konto_fra=$konto_fra=$konto rettet til $konto_fra=$konto_til=$konto;
// 20190815 - PHR
// 20210805 - LOE Translated some texts 
// 20250923 - LOE Sets rapportart to $rapportart = 'kontokort'; only if not accountChart 
// 20260513 PHR Cleanup
// 20260702 CX/PHR Split comma-separated openpost autoudlign account list
// 20260706 MJ Release session before long read-only reports to avoid blocking navigation.
// 20260706 MJ Load debtor open items report content asynchronously so the page renders before the heavy table.
// 20260817 Sawaneh SST-717 Openpost GET requests keep their dato/konto filters, which the
//                  async iframe and udlign links pass but the page previously dropped.
// 20260807 CL/NTR Generalize the async report shell to kontokort/kontosaldo/accountChart (not just openpost), drop the shell's inline padding, and add Cache-Control: no-store + pageshow/persisted reload so the back button can't restore a frozen shell/iframe.
// 20260807 CL/NTR Skip the async shell for requests already inside its own iframe (Sec-Fetch-Dest: iframe), so report links that don't carry the *_content flag forward don't nest a second shell+iframe inside the first.
// 20260824 CL/NTR Prototype: openpost drops the iframe shell - the shell stream-fetches the report and document.write()s it over itself chunk by chunk (progressive rendering like the iframe had), so Back/bfcache can never restore a nested or frozen frame; Cache-Control: no-store now sent before any output on openpost requests.
// 20260826 Sawaneh SD-140: kontonr GET branch keeps dato_fra/dato_til and accepts a fra:til range;
//                  the aging filter/sort state rides along on the async shell's forwarded params.
// 20260915 CL/SZ SST-786: openpost_csv GET requests buffer (and discard) the includes below before
//                dispatching to openpost_export_csv() - online.php's page shell prints regardless of
//                the async-shell logic further down, and openpost_export_csv() needs to send its own
//                CSV headers with nothing else sent yet.
// 20260923 CL/NTR Guard count($konto_id) against the field being absent from $_POST when the openpost
//                report has no matching accounts - Mail kontoudtog/Opret rykker/Ryk alle used to crash.
// 20261001 CDX/LAH Validate settlement lists and show skipped reminders and blocked-popup links.
// 20261001 CDX/LAH Encode sales-statistics redirects and use ifset for the reminder count lookup.

@session_start();
$s_id = session_id();
ini_set('max_execution_time', 300);
$css = "../css/std.css";
$title = "Debitorrapport";
$modulnr = 12;

$tmp = NULL;
$initialSubmitValue = isset($_POST['submit']) ? strtolower(trim($_POST['submit'])) : (isset($_GET['submit']) ? strtolower(trim($_GET['submit'])) : NULL);

// Must run before the includes below - they print markup, and headers can't be sent
// after output. no-store keeps both the shell and the report out of the back-forward
// cache, so Back always re-runs the request instead of restoring a stale page.
$openpostRequest = (isset($_GET['rapportart']) && $_GET['rapportart'] == 'openpost')
	|| isset($_POST['openpost'])
	|| (isset($_POST['rapportart']) && (strtolower(trim($_POST['rapportart'])) == 'openpost' || strstr(strtolower(trim($_POST['rapportart'])), 'ben post')));
if ($openpostRequest)
	header('Cache-Control: no-store');

// SST-786: "Vis alle poster" (all-posts, all-open) toggles the udlignet filter but the report stays
// paginated, so the customer can never see a totals-reconciling overview spanning more than one
// page. openpost_export_csv() bypasses pagination (batching its own queries instead) and sends its
// own Content-Type/Content-Disposition headers, so nothing else may reach the browser first -
// includes/online.php prints a page shell (doctype/head/body/button-color style) unconditionally
// unless several separate flags line up just right, so buffering and discarding it here is more
// robust than chasing every one of those flags individually.
$openpostCsvRequest = $openpostRequest && isset($_GET['openpost_csv']);
if ($openpostCsvRequest) ob_start();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");
include("../includes/autoudlign.php");
include("../includes/rapportfunc.php");

if ($openpostCsvRequest) {
	ob_end_clean();
	$dato_fra = ifset($_GET, 'dato_fra');
	$dato_til = ifset($_GET, 'dato_til');
	$konto_fra = ifset($_GET, 'konto_fra');
	$konto_til = ifset($_GET, 'konto_til');
	if ($konto_fra === null && isset($_GET['kontonr'])) list($konto_fra, $konto_til) = openpost_kontonr_range($_GET['kontonr']);
	openpost_export_csv($dato_fra, $dato_til, $konto_fra, $konto_til, 'D', ifset($_GET, 'kun_debet'), ifset($_GET, 'kun_kredit'), isset($_GET['vis_alle_poster']), ifset($_GET, 'showPBS', 1));
	exit;
}
include("../includes/row-hover-style-with-links.js.php");

/**
 * Keeps only positive integer account IDs from the GET settlement list.
 *
 * @return string Comma-separated IDs, or an empty string when none are valid.
 */
function rapport_udlign_list($udlign)
{
	if (!is_string($udlign)) return '';
	$accounts = array();
	foreach (explode(',', $udlign) as $account) {
		$account = trim($account);
		if (!preg_match('/^[0-9]+$/D', $account)) continue;
		$account = filter_var(ltrim($account, '0'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		if ($account !== false) $accounts[] = $account;
	}
	return implode(',', array_unique($accounts));
}

if (!function_exists('autoudlign_liste')) {
	function autoudlign_liste($udlign) {
		$udlign = rapport_udlign_list($udlign);
		if ($udlign === '') return;
		foreach (explode(',', $udlign) as $account) {
			autoudlign((int)$account);
		}
	}
}
$udlignListe = rapport_udlign_list(ifset($_GET, 'udlign', ''));

/**
 * Stores report preferences without changing the raw values passed to the report view.
 *
 * @return void
 */
function rapport_save_filters($husk, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $bruger_id)
{
	$husk = db_escape_string((string)$husk);
	$dato_fra = db_escape_string((string)$dato_fra);
	$dato_til = db_escape_string((string)$dato_til);
	$konto_fra = db_escape_string((string)$konto_fra);
	$konto_til = db_escape_string((string)$konto_til);
	$rapportart = db_escape_string((string)$rapportart);
	$bruger_id = (int)$bruger_id;
	db_modify("update grupper set box1='$husk',box2='$dato_fra',box3='$dato_til',box4='$konto_fra',box5='$konto_til',box6='$rapportart' where art='DRV' and kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__);
}

/**
 * Opens an action result after page load and offers a visible link if the browser blocks it.
 *
 * @return void
 */
function rapport_result_popup($page, $params, $jsvars, $sprog_id, $notice = '')
{
	static $helperPrinted = false;
	if (!$helperPrinted) {
		print <<<'JS'
<script>
function saldiOpenOrLink(url, label, features) {
	var popup = window.open(url, '', features);
	window.saldiPopupBlocked = !popup;
	if (!popup) {
		var banner = document.createElement('div');
		banner.setAttribute('role', 'alert');
		banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:10000;padding:12px;background:#fff;color:#000;border-bottom:1px solid #ccc;';
		var link = document.createElement('a');
		link.href = url;
		link.target = '_blank';
		link.rel = 'noopener';
		link.style.cssText = 'color:#000;text-decoration:underline;';
		link.textContent = label;
		banner.appendChild(link);
		document.body.insertBefore(banner, document.body.firstChild);
	}
}
</script>
JS;
		$helperPrinted = true;
	}
	$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
	$url = json_encode($page . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), $jsonFlags);
	$label = json_encode(html_entity_decode(findtekst('5511|Din browser blokerede vinduet. Klik her for at &aring;bne resultatet.', $sprog_id), ENT_QUOTES, 'UTF-8'), $jsonFlags);
	$features = json_encode((string)$jsvars, $jsonFlags);
	print "<script>window.addEventListener('load', function () { saldiOpenOrLink($url, $label, $features);";
	if ($notice !== '') {
		$notice = json_encode(html_entity_decode($notice, ENT_QUOTES, 'UTF-8'), $jsonFlags);
		print "alert($notice);";
	}
	print "});</script>";
}

/**
 * Queues a translated action alert with safe JavaScript string encoding.
 *
 * @return void
 */
function rapport_action_alert($message)
{
	$message = json_encode(html_entity_decode($message, ENT_QUOTES, 'UTF-8'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	print "<script>window.addEventListener('load', function () { alert($message); });</script>";
}

/**
 * Handles account selection for statements and new reminders.
 *
 * @return bool True when a reminder window was requested.
 */
function rapport_account_action($submit, $konto_id, $kontoudtog, $rykkerbelob, $dato_fra, $dato_til, $jsvars, $sprog_id)
{
	$accounts = array();
	$skipped = 0;
	foreach ($konto_id as $index => $account) {
		if (!isset($kontoudtog[$index]) || $kontoudtog[$index] !== 'on') continue;
		if ($submit != 'mail kontoudtog' && (float)($rykkerbelob[$index] ?? 0) <= 0) {
			if ($submit == 'opret rykker') $skipped++;
			continue;
		}
		$account = (int)$account;
		if ($account > 0) $accounts[] = $account;
	}
	$kontoantal = count($accounts);
	if ($kontoantal) {
		$params = array('kontoliste' => implode(';', $accounts) . ';', 'kontoantal' => $kontoantal);
		if ($submit == 'mail kontoudtog') {
			$params['dato_fra'] = $dato_fra;
			$params['dato_til'] = $dato_til;
			rapport_result_popup('mail_kontoudtog.php', $params, $jsvars, $sprog_id);
			return false;
		}
		$notice = '';
		if ($skipped) {
			$notice = sprintf(findtekst('5510|%s valgte konti blev sprunget over, fordi de ikke har et positivt forfaldent bel&oslash;b.', $sprog_id), $skipped);
		}
		rapport_result_popup('ny_rykker.php', $params, $jsvars, $sprog_id, $notice);
		return true;
	}
	if ($submit == 'ryk alle') {
		rapport_result_popup('ny_rykker.php', array('kontoliste' => 'alle', 'kontoantal' => 'max'), $jsvars, $sprog_id);
		return true;
	}
	rapport_action_alert(findtekst(($submit == 'mail kontoudtog') ? 1791 : 1792, $sprog_id));
	return false;
}

/**
 * Handles selected reminder actions with integer order IDs and safe result URLs.
 *
 * @return bool True when the reminder overview should refresh.
 */
function rapport_reminder_action($submit, $rykker_id, $rykkerbox, $rykkerantal, $jsvars, $sprog_id)
{
	$reminders = array();
	foreach ($rykker_id as $index => $id) {
		if ($index < 1 || $index > (int)$rykkerantal || !isset($rykkerbox[$index]) || $rykkerbox[$index] !== 'on') continue;
		$id = (int)$id;
		if ($id > 0) $reminders[] = $id;
	}
	if ($submit == 'slet') {
		foreach ($reminders as $id) {
			db_modify("delete from ordrelinjer where ordre_id=$id", __FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrer where id=$id", __FILE__ . " linje " . __LINE__);
		}
	} elseif (strstr($submit, 'bogf')) {
		foreach ($reminders as $id) {
			bogfor_rykker($id);
		}
	} elseif ($reminders) {
		$params = array('rykker_id' => implode(';', $reminders), 'kontoantal' => count($reminders));
		if ($submit == 'inkasso') {
			$url = htmlspecialchars('inkasso.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), ENT_QUOTES);
			print "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0; url=$url\">";
			exit;
		}
		if ($submit == 'udskriv') $page = 'rykkerprint.php';
		elseif ($submit == 'afslut') $page = 'afslut_rykker.php';
		else $page = 'ny_rykker.php';
		rapport_result_popup($page, $params, $jsvars, $sprog_id);
		return ($submit != 'udskriv');
	}
	return false;
}

$skipPopupCheck = $openpostRequest;

if (!$skipPopupCheck) {
?>
<script>
function checkPopupBlocked() {
    var popup = window.open('', 'test', 'width=1,height=1');
    
    if (!popup || popup.closed || typeof popup.closed == 'undefined') {
        // Popup blocked
        return true;
    } else {
        // Popup allowed - close test popup
        popup.close();
        return false;
    }
}

const res = checkPopupBlocked();
if (res) {
	// Alert the user about the popup blocker (Dansk translation)
	alert("Din browser blokerer pop-up vinduer. For at kunne bruge rapportfunktionen, skal du tillade pop-up vinduer for denne side.");
} else {
	// Proceed with the report functionality
	console.log("Pop-up allowed, proceeding with report functionality.");
}
</script>
<?php
}

print '
<style>
.hover-highlight:hover {
  outline: 2px solid #b2b2b2;
  background-color: #f9f9f9;
  cursor: pointer;
}
</style>
<script>
document.addEventListener("DOMContentLoaded", function () {
  // Only apply to tables with a specific class, e.g. "data-table"
  document.querySelectorAll("table.data-table").forEach(table => {
    table.querySelectorAll("tr").forEach(row => {
      const tds = row.querySelectorAll("td");
      if (tds.length <= 1) return;
      if (row.offsetParent === null) return;

      let hasLabel = false;
      let skip = false;

      for (let td of tds) {
        if (td.querySelector("label")) {
          hasLabel = true;
        }
        const interactive = td.querySelector("button, select, input:not([type=\'hidden\']), b, i, img");
        if (interactive) {
          skip = true;
          break;
        }
      }

      if (!skip || hasLabel) {
        row.classList.add("hover-highlight");
      }
    });
  });
});
</script>
';

#print "<script LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\" SRC=\"../javascript/overlib.js\"></script>";
global $sprog_id; //2021

$backUrl = isset($_GET['returside'])
	? $_GET['returside']
	: '../index/menu.php';
if ($popup)
	$returside = "../includes/luk.php";
else
	$returside = $backUrl;

$openpost = false;
if(isset($_GET["rapportart"]) && $_GET["rapportart"] == "openpost") {
	$openpost = true;
}

if (!isset($_GET["rapportart"]))
	$rapportart = NULL;
if (!isset($_GET['submit']))
	$_GET['submit'] = NULL;
/* if (!isset($_GET['returside']))
	$_GET['returside'] = NULL; */

if (isset($_GET['ny_rykker'])) {
	$dato_fra = $_GET['dato_fra'];
	$dato_til = $_GET['dato_til'];
	$konto_fra = $_GET['konto_fra'];
	$konto_til = $_GET['konto_til'];
	#	$regnaar=$_GET['regnaar'];
	openpost($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, 'D');
	exit;
} elseif (isset($_GET['rapportart']) && $_GET['rapportart'] != 'openpost') {
	$dato_fra = if_isset($_GET['dato_fra']);
	$dato_til = if_isset($_GET['dato_til']);
	$konto_fra = if_isset($_GET['konto_fra']);
	$konto_til = if_isset($_GET['konto_til']);
	$rapportart = $_GET['rapportart']; 
	if ($udlignListe !== '') {
		autoudlign_liste($udlignListe);
		$udlignListe = '';
		unset($_GET['udlign']);
	}
	if ($rapportart == 'kontokort' && if_isset($_GET['layout']) == 'grid' && $konto_fra && $konto_fra == $konto_til) {
		include_once 'generalLedger.php';
		renderDebitorGeneralLedgerGrid($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart);
		exit;
	}
	if ($rapportart == 'accountChart')
	include("../includes/row-hover-style-with-link-no-input.js.php");
    else $rapportart = 'kontokort';

	if ($rapportart == 'accountChart')
		include_once("../includes/reportFunc/accountChart.php");
	$rapportart($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, 'D');
	
	exit;
}

$rapportart = NULL;
if (isset($_POST['openpost']) || $openpost)
	$rapportart = 'openpost';
if ($openpost) {
	# Keep the raw filters for report links/searches; rapport_save_filters() escapes only the SQL write.
	if (!isset($dato_fra) && isset($_GET['dato_fra'])) $dato_fra = $_GET['dato_fra'];
	if (!isset($dato_til) && isset($_GET['dato_til'])) $dato_til = $_GET['dato_til'];
	if (!isset($konto_fra) && isset($_GET['konto_fra'])) $konto_fra = $_GET['konto_fra'];
	if (!isset($konto_til) && isset($_GET['konto_til'])) $konto_til = $_GET['konto_til'];
}
if (isset($_POST['kontosaldo']))
	$rapportart = 'kontosaldo';
if (isset($_POST['kontokort']))
	$rapportart = 'kontokort';
if (isset($_POST['accountChart']))
	$rapportart = 'accountChart';
if ($rapportart == 'accountChart')
	include_once("../includes/reportFunc/accountChart.php");
if (isset($_POST['dato'])) {
	$dato = $_POST['dato'];
	if (strpos($dato, ':'))
		list($dato_fra, $dato_til) = explode(":", $dato);
	elseif ($dato) {
		$dato_til = $dato_fra = $dato;
		$dato_fra = '01-01-2000';
	} else
		$dato_til = $dato_fra = NULL;
	if ($dato_fra) {
		$fromdate = usdate($dato_fra);
		$dato_fra = str_replace("-20", "", dkdato($fromdate));
		$dato_fra = trim(str_replace("-", "", $dato_fra));
	}
	if ($dato_til) {
		$todate = usdate($dato_til);
		$dato_til = str_replace("-20", "", dkdato($todate));
		$dato_til = trim(str_replace("-", "", $dato_til));
	}
}
if (isset($_POST['konto'])) {
	$konto = $_POST['konto'];
	if (strpos($konto, ':'))
		list($konto_fra, $konto_til) = explode(":", $konto);
	else
		$konto_fra = $konto_til = $konto;
	if (!is_numeric($konto_fra))
		$konto_til = NULL;
}
$husk = if_isset($_POST, NULL, 'husk');
if (isset($_POST['salgsstat']) && $_POST['salgsstat']) {
	if ($husk) {
		rapport_save_filters($husk, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $bruger_id);
	}
	$refreshUrl = '../includes/salgsstat.php?' . http_build_query(array(
		'dato_fra' => (string)$dato_fra,
		'dato_til' => (string)$dato_til,
		'konto_fra' => (string)$konto_fra,
		'konto_til' => (string)$konto_til,
		'art' => 'D'
	), '', '&', PHP_QUERY_RFC3986);
	$refreshContent = htmlspecialchars('1;URL=' . $refreshUrl, ENT_QUOTES);
	print "<meta http-equiv=\"refresh\" content=\"$refreshContent\">";
	exit;
}
if (isset($_POST['saft'])) {
	header("Location: saftCashRegister.php");
	exit();
}

if (isset($_POST['submit']) || $rapportart) {
	#	$husk=$_POST['husk'];
	if (!$rapportart) {
		$submit = strtolower(trim($_POST['submit']));
		$rapportart = strtolower(trim($_POST['rapportart']));
		$dato_fra = $_POST['dato_fra'];
		$dato_til = $_POST['dato_til'];
	} else {
		rapport_save_filters($husk, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $bruger_id);
		$submit = 'ok';
	}
	#	$md=$_POST['md'];
	#	if (isset($_POST['konto_fra']) && strpos($_POST['konto_fra'],":")) {
	#		list ($konto_fra, $firmanavn) = explode(":", $_POST['konto_fra']);
	// The in-report account search (open posts) sends kontonr - a single account, a fra:til range
	// or a firm-name pattern - and carries rapportart=openpost, so it lands in this branch instead
	// of the kontonr branch below. Derive the konto_fra/konto_til pair the report works with.
	if (!isset($_POST['konto']) && !isset($_GET['konto_fra']) && isset($_GET['kontonr'])) {
		list($konto_fra, $konto_til) = openpost_kontonr_range($_GET['kontonr']);
	}
	$konto_fra = trim(if_isset($konto_fra));
	#	}
	#	if (isset($_POST['konto_til']) && strpos($_POST['konto_til'],":")) {
	#		list ($konto_til, $firmanavn) = explode(":", $_POST['konto_til']);
	$konto_til = trim(if_isset($konto_til));
	#	}
	#	if (isset($_POST['regnaar']) && strpos($_POST['regnaar'],"-")) {
	#		list ($regnaar, $firmanavn)= explode("-", $_POST['regnaar']);
	$firmanavn = trim(if_isset($firmanavn ?? NULL));
	#	}
	if (!isset($_POST['konto_id']))
		$_POST['konto_id'] = NULL;
	if (!isset($_POST['kontoudtog']))
		$_POST['kontoudtog'] = NULL;
	if (!isset($_POST['rykkerbelob']))
		$_POST['rykkerbelob'] = NULL;
	if (($submit == "mail kontoudtog") || ($submit == "opret rykker") || ($submit == "ryk alle")) {
		$konto_id = if_array($_POST, 'konto_id');
		$kontoudtog = if_array($_POST, 'kontoudtog');
		$rykkerbelob = if_array($_POST, 'rykkerbelob');
		$ny_rykker = rapport_account_action($submit, $konto_id, $kontoudtog, $rykkerbelob, $dato_fra, $dato_til, $jsvars, $sprog_id);
		$submit = 'ok';
	} elseif ($submit == "slet" || $submit == "udskriv" || strstr($submit, "bogf") || $submit == "ny rykker" || $submit == "afslut" || $submit == "inkasso") {
		$rykkerantal = (int)ifset($_POST, 'rykkerantal', 0);
		$rykker_id = if_array($_POST, 'rykker_id');
		$rykkerbox = if_array($_POST, 'rykkerbox');
		$ny_rykker = rapport_reminder_action($submit, $rykker_id, $rykkerbox, $rykkerantal, $jsvars, $sprog_id);
		$submit = 'ok';
	}
	# echo "KF $konto_fra<br>";
} elseif (isset($_GET['konto_fra'])) {
	$rapportart = $_GET['rapportart'] ?? NULL;
	$dato_fra = $_GET['dato_fra'] ?? NULL;
	$dato_til = $_GET['dato_til'] ?? NULL;
	$konto_fra = $_GET['konto_fra'];
	$konto_til = $_GET['konto_til'] ?? NULL;
	#	$regnaar=$_GET['regnaar'];
	$submit = $_GET['submit'] ?? NULL;
	$returside = $_GET['returside'] ?? NULL;
	if ($udlignListe !== '') {
		autoudlign_liste($udlignListe);
		$udlignListe = '';
	}
	unset($_GET['udlign']);
} elseif (isset($_GET['kontonr'])) {
	list($konto_fra, $konto_til) = openpost_kontonr_range($_GET['kontonr']);
	$dato_fra = $_GET['dato_fra'] ?? NULL;
	$dato_til = $_GET['dato_til'] ?? NULL;
	$returside = $_GET['returside'] ?? NULL;
	$submit = "ok";
	$rapportart = $_GET['rapportart'] ?? NULL;
	/*
				 $row = db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
					 $start_md[$x]=$row['box1']*1;
					 $start_aar[$x]=$row['box2']*1;
					 $slut_md[$x]=$row['box3']*1;
					 $slut_aar[$x]=$row['box4']*1;
					 $dato_fra="$row[box2] $row[box1]";
					 $dato_til="$row[box4] $row[box3]";
			 */
}
#if ($dato_fra) $dato_fra=find_maaned_nr($dato_fra); 
#if ($dato_til) $dato_til=find_maaned_nr($dato_til); 

if ($udlignListe !== '') {
	autoudlign_liste($udlignListe);
}
unset($_GET['udlign']);
if (strstr($rapportart, "ben post"))
	$rapportart = "openpost";
if (!isset($submit))
	$submit = NULL;
if ($submit != 'ok')
	$submit = 'forside';
elseif ($rapportart)
	$submit = $rapportart;

if (!isset($dato_fra))
	$dato_fra = NULL;
if (!isset($dato_til))
	$dato_til = NULL;
if (!isset($konto_fra))
	$konto_fra = NULL;
if (!isset($konto_til))
	$konto_til = NULL;

$asyncReports = array('openpost', 'kontokort', 'kontosaldo', 'accountChart');
if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE && in_array($submit, $asyncReports)) {
	session_write_close();
}

$asyncContentParam = $rapportart . '_content';
// Any navigation happening inside the shell's own iframe (a link/form on the report that
// forgot to carry the *_content flag forward) still reports Sec-Fetch-Dest: iframe, so this
// catches it and renders content directly instead of nesting a second shell+iframe inside the first.
$isInsideIframe = isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] == 'iframe';
if (in_array($submit, $asyncReports) && !$isInsideIframe && !isset($_GET[$asyncContentParam]) && !isset($_POST[$asyncContentParam])) {
	$writeActions = array('mail kontoudtog', 'opret rykker', 'ryk alle', 'slet', 'udskriv', 'ny rykker', 'afslut', 'inkasso');
	$isWriteAction = in_array($initialSubmitValue, $writeActions) || strstr((string)$initialSubmitValue, 'bogf');
	if (!$isWriteAction) {
		// Forward every incoming filter/paging param as-is; each report type has its own set
		// (openpost_page, showPBS, ...) so we don't hardcode a per-report field list here.
		$params = array_merge($_GET, $_POST);
		$params['rapportart'] = $rapportart;
		$params['submit'] = 'ok';
		$params[$asyncContentParam] = 1;
		if ($rapportart == 'openpost') {
			// Prototype (no iframe): stream-fetch the report and document.write() it over this
			// shell chunk by chunk, so the top of the report (header bar, first rows) renders
			// while the rest is still being generated - the same progressive behavior the
			// iframe gave. The report ends up owning the whole document exactly as if
			// navigated to directly, so there is no frame for Back/bfcache to restore or for
			// inner links (with or without openpost_content) to nest a second shell into.
			// Links inside the report navigate top-level; ones missing the *_content flag
			// simply re-enter this shell and show the loading state again. Cache-Control:
			// no-store was already sent at the top of this file, before any output.
			$contentUrl = json_encode('rapport.php?' . http_build_query($params));
			print "<div id='rapportAsyncStatus' style='padding:10px; text-align:center;'>Indl&aelig;ser...</div>";
			print "<script>";
			print "window.addEventListener('pageshow', function(e){ if (e.persisted) location.reload(); });";
			// The written document loses this shell's listeners, so the bfcache guard rides
			// along inside the report markup itself.
			print "var guard = \"<script>window.addEventListener('pageshow', function(e){ if (e.persisted) location.reload(); });<\\/script>\";";
			// document.open() wipes the loading indicator, so it waits for the first chunk.
			print "var opened = false;";
			print "function beginDoc(){ if (!opened) { opened = true; document.open(); } }";
			print "fetch($contentUrl, {credentials: 'same-origin'}).then(function(r){";
			print "if (!r.ok) throw new Error('HTTP ' + r.status);";
			print "if (!r.body || !r.body.getReader) { return r.text().then(function(html){ beginDoc(); document.write(html + guard); document.close(); }); }";
			print "var reader = r.body.getReader();";
			print "var decoder = new TextDecoder();";
			print "function pump(){ return reader.read().then(function(res){";
			print "if (res.done) { beginDoc(); document.write(decoder.decode() + guard); document.close(); return; }";
			print "beginDoc();";
			print "document.write(decoder.decode(res.value, {stream: true}));";
			print "return pump();";
			print "}); }";
			print "return pump();";
			print "}).catch(function(err){";
			print "var msg = 'Rapporten kunne ikke indlæses (' + err.message + '). Prøv at genindlæse siden.';";
			print "var status = document.getElementById('rapportAsyncStatus');";
			print "if (status) status.textContent = msg; else { document.write('<p>' + msg + '</p>'); document.close(); }";
			print "});";
			print "</script>";
		} else {
			$frameSrc = 'rapport.php?' . str_replace('&', '&amp;', http_build_query($params));
			// Cache-Control: no-store keeps this transitional shell out of the back-forward cache,
			// so navigating back triggers a fresh request instead of restoring a frozen shell/iframe.
			header('Cache-Control: no-store');
			print "<div id='rapportAsyncShell'>";
			print "<div id='rapportAsyncStatus' style='padding:10px; text-align:center;'>Indl&aelig;ser...</div>";
			print "<iframe id='rapportAsyncFrame' data-src='$frameSrc' style='width:100%; min-height:720px; border:0;' onload=\"document.getElementById('rapportAsyncStatus').style.display='none'; this.style.minHeight=Math.max(720, (this.contentWindow && this.contentWindow.document && this.contentWindow.document.body ? this.contentWindow.document.body.scrollHeight + 40 : 720)) + 'px';\"></iframe>";
			print "<script>";
			print "setTimeout(function(){var frame=document.getElementById('rapportAsyncFrame'); if(frame && !frame.getAttribute('src')) frame.setAttribute('src', frame.getAttribute('data-src'));}, 10);";
			print "window.addEventListener('pageshow', function(e){ if (e.persisted) location.reload(); });";
			print "</script>";
			print "</div>";
		}
		print "</html>";
		exit;
	}
}

$submit($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, 'D', $returside);

?>

</html>
