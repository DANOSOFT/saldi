<?php
// --- debitor/generalLedger.php --- patch 5.0.0 --- 2026-03-19 ---

function debitorGeneralLedgerEscape($value)
{
	global $charset;

	if (!$charset) {
		$charset = 'UTF-8';
	}

	return htmlspecialchars((string)$value, ENT_QUOTES, $charset);
}

function debitorGeneralLedgerUrl($params)
{
	return 'rapport.php?' . http_build_query($params, '', '&');
}

function renderDebitorGeneralLedgerGrid($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart)
{
	global $bgcolor, $bgcolor5, $bruger_id, $jsvars, $menu, $popup, $regnaar, $sprog_id;

	include_once "../includes/topline_settings.php";
	include_once "../includes/grid.php";

	if (!$konto_fra || $konto_fra != $konto_til) {
		kontokort($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, 'D');
		return;
	}

	$konto_fra = trim($konto_fra);
	$konto_til = trim($konto_til);
	$unAlign = if_isset($_GET['unAlign'], NULL);
	$unAlignAccount = if_isset($_GET['unAlignAccount'], 0);
	$unAlignId = if_isset($_GET['oppId'], 0);
	$kilde = if_isset($_GET['kilde']);
	$kilde_kto_fra = if_isset($_GET['kilde_kto_fra']);
	$kilde_kto_til = if_isset($_GET['kilde_kto_til']);

	if ($unAlign || $unAlignId) {
		$qtxt = "update openpost set udlignet='0',udlign_id='0' where konto_id = '" . (int)$unAlignAccount . "'";
		if ($unAlign) {
			$qtxt .= " and udlign_id='" . (int)$unAlign . "'";
		} elseif ($unAlignId) {
			$qtxt .= " and id = '" . (int)$unAlignId . "'";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}

	if (!$regnaar) {
		$qtxt = "select regnskabsaar from brugere where id = '$bruger_id'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$regnaar = $r['regnskabsaar'];
		}
	}

	$regnstart = '';
	$regnslut = '';
	$r = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'", __FILE__ . " linje " . __LINE__));
	if ($r) {
		$regnstart = "01-" . trim($r['box1']) . "-" . trim($r['box2']);
		$tmpDay = 31;
		while (!checkdate(trim($r['box3']), $tmpDay, trim($r['box4']))) {
			$tmpDay--;
			if ($tmpDay < 28) {
				break;
			}
		}
		$regnslut = $tmpDay . "-" . trim($r['box3']) . "-" . trim($r['box4']);
	}

	$backUrl = if_isset($_GET['returside']);
	if (!$backUrl) {
		if ($popup) {
			$backUrl = "../includes/luk.php";
		} elseif ($kilde == 'openpost') {
			$backUrl = "rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kilde_kto_fra&konto_til=$kilde_kto_til";
		} else {
			$backUrl = "debitorkort.php?kontonr=$konto_fra";
		}
	}

	$fromdate = NULL;
	$todate = NULL;
	if ($dato_fra && $dato_til) {
		$fromdate = usdate($dato_fra);
		$todate = usdate($dato_til);
	} elseif ($dato_fra && !$dato_til) {
		$todate = usdate($dato_fra);
	}

	$qtxt = "select * from adresser where art = 'D' and kontonr = '$konto_fra' order by id limit 1";
	$account = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$account) {
		kontokort($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, 'D');
		return;
	}

	$accountId = (int)$account['id'];
	$groupArt = trim($account['art']) . "G";
	$groupData = db_fetch_array(db_select("select box3 from grupper where art='$groupArt' and kodenr='" . (int)$account['gruppe'] . "'", __FILE__ . " linje " . __LINE__));
	$valuta = trim(if_isset($groupData, 'DKK', 'box3'));
	$valutakode = 0;
	if ($valuta && $valuta != 'DKK') {
		$r = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'", __FILE__ . " linje " . __LINE__));
		$valutakode = (int)if_isset($r, 0, 'kodenr');
	} else {
		$valuta = 'DKK';
	}

	$betalingsbet = trim($account['betalingsbet']);
	$betalingsdage = (int)$account['betalingsdage'];
	$max_valdif_id = 0;
	$r = db_fetch_array(db_select("select max(id) as max_valdif_id from openpost where konto_id='$accountId' and abs(amount) = '0.001'", __FILE__ . " linje " . __LINE__));
	if ($r && $r['max_valdif_id']) {
		$max_valdif_id = (int)$r['max_valdif_id'];
	}

	$entries = array();
	$firstdate = date("Y-m-d");
	$lastdate = '1970-01-01';
	$qtxt = "select * from openpost where konto_id='$accountId'";
	if ($todate) {
		$qtxt .= " and transdate<='$todate'";
	}
	$qtxt .= " order by transdate,id,faktnr,refnr";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)) {
		$entry = array();
		$entry['id'] = (int)$row['id'];
		$entry['amount'] = (float)$row['amount'];
		$entry['dkkamount'] = (float)$row['amount'];
		$entry['description'] = $row['beskrivelse'];
		$entry['valutakurs'] = (float)$row['valutakurs'];
		$entry['oppvaluta'] = $row['valuta'];
		$entry['faktnr'] = $row['faktnr'];
		$entry['forfaldsdag'] = $row['forfaldsdate'];
		$entry['kladde_id'] = (int)$row['kladde_id'];
		$entry['projekt'] = if_isset($row, '', 'projekt');
		$entry['refnr'] = $entry['kladde_id'] ? $row['refnr'] : '';
		$entry['transdate'] = $row['transdate'];
		$entry['udlignet'] = $row['udlignet'];
		$entry['udlign_id'] = (int)$row['udlign_id'];

		if (!$entry['oppvaluta']) {
			$entry['oppvaluta'] = 'DKK';
			$entry['valutakurs'] = 100;
		}
		if (!strlen((string)$entry['valutakurs'])) {
			$entry['valutakurs'] = 100;
		}

		if ($firstdate > $entry['transdate']) {
			$firstdate = $entry['transdate'];
		}
		if ($lastdate < $entry['transdate']) {
			$lastdate = $entry['transdate'];
		}

		if ($entry['oppvaluta'] != 'DKK' && $entry['valutakurs'] == 100) {
			$r3 = db_fetch_array(db_select("select kodenr from grupper where box1 = '" . $entry['oppvaluta'] . "' and art='VK'", __FILE__ . " linje " . __LINE__));
			if ($r3) {
				$r4 = db_fetch_array(db_select("select kurs from valuta where gruppe ='" . (int)$r3['kodenr'] . "' and valdate <= '" . $entry['transdate'] . "' order by valdate desc", __FILE__ . " linje " . __LINE__));
				if ($r4) {
					$entry['valutakurs'] = (float)$r4['kurs'];
				}
			}
			$entry['dkkamount'] = $entry['amount'] * $entry['valutakurs'] / 100;
			$entry['description'] = $row['beskrivelse'] . " - (Omregnet fra DKK til $valuta " . dkdecimal($entry['dkkamount'], 2) . ", kurs " . dkdecimal($entry['valutakurs'], 2) . ")";
		} elseif ($valuta != "DKK" && $entry['valutakurs'] == 100) {
			$r3 = db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '" . $entry['transdate'] . "' order by valdate desc", __FILE__ . " linje " . __LINE__));
			if ($r3) {
				$entry['dkkamount'] = $entry['amount'];
				$entry['amount'] = $entry['amount'] * 100 / $r3['kurs'];
				$entry['description'] = $row['beskrivelse'] . " - (Omregnet til $valuta fra DKK " . dkdecimal($entry['dkkamount'], 2) . ", kurs " . dkdecimal($r3['kurs'], 2) . ")";
			} elseif ($r4 = db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' order by valdate", __FILE__ . " linje " . __LINE__))) {
				$entry['dkkamount'] = $entry['amount'];
				$entry['amount'] = $entry['amount'] * 100 / $r4['kurs'];
				$entry['description'] = $row['beskrivelse'] . " - (Omregnet til $valuta fra DKK " . dkdecimal($entry['dkkamount'], 2) . ", kurs " . dkdecimal($r4['kurs'], 2) . ")";
			}
		} elseif ($entry['oppvaluta'] != 'DKK' && $valuta == "DKK" && $entry['valutakurs'] != 100) {
			$entry['description'] = $row['beskrivelse'] . " - (Omregnet til DKK fra " . $entry['oppvaluta'] . " " . dkdecimal($entry['amount'], 2) . ", kurs " . dkdecimal($entry['valutakurs'], 2) . ")";
			$entry['amount'] = $entry['amount'] * $entry['valutakurs'] / 100;
			$entry['dkkamount'] = $entry['amount'];
		} elseif ($valuta != "DKK" && $valuta == $entry['oppvaluta'] && $entry['valutakurs'] != 100) {
			if (!$entry['valutakurs'] && $entry['oppvaluta'] && $entry['oppvaluta'] != '-') {
				$r3 = db_fetch_array(db_select("select kodenr from grupper where box1 = '" . $entry['oppvaluta'] . "' and art='VK'", __FILE__ . " linje " . __LINE__));
				if ($r3) {
					$r4 = db_fetch_array(db_select("select kurs from valuta where gruppe ='" . (int)$r3['kodenr'] . "' and valdate <= '" . $entry['transdate'] . "' order by valdate desc", __FILE__ . " linje " . __LINE__));
					if ($r4) {
						$entry['valutakurs'] = (float)$r4['kurs'];
					}
				}
			}
			$entry['dkkamount'] = $entry['amount'] * $entry['valutakurs'] / 100;
			if ($entry['oppvaluta'] != '-' && abs($entry['amount']) >= 0.005) {
				if (!strpos($entry['description'], 'Udligning af valutadiff')) {
					$entry['description'] = $row['beskrivelse'] . " - (DKK " . dkdecimal($entry['dkkamount'], 2) . ")";
				}
			} elseif (abs($entry['amount']) < 0.005) {
				$entry['description'] = $row['beskrivelse'];
			} else {
				$entry['description'] = $row['beskrivelse'] . " - (DKK " . dkdecimal($entry['amount'], 2) . ")";
			}
		} elseif ($entry['oppvaluta'] != $valuta && $entry['oppvaluta'] != '-') {
			if (!$entry['valutakurs']) {
				$r3 = db_fetch_array(db_select("select kodenr from grupper where box1 = '" . $entry['oppvaluta'] . "' and art='VK'", __FILE__ . " linje " . __LINE__));
				if ($r3) {
					$r4 = db_fetch_array(db_select("select kurs from valuta where gruppe ='" . (int)$r3['kodenr'] . "' and valdate <= '" . $entry['transdate'] . "' order by valdate desc", __FILE__ . " linje " . __LINE__));
					if ($r4) {
						$entry['valutakurs'] = (float)$r4['kurs'];
					}
				}
			}
			$r3 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'", __FILE__ . " linje " . __LINE__));
			$r4 = $r3 ? db_fetch_array(db_select("select kurs from valuta where gruppe ='" . (int)$r3['kodenr'] . "' and valdate <= '" . $entry['transdate'] . "' order by valdate desc", __FILE__ . " linje " . __LINE__)) : NULL;
			$dagskurs = $r4 ? (float)$r4['kurs'] : 100;
			$entry['description'] .= " " . $entry['oppvaluta'] . " " . dkdecimal($entry['amount'], 2) . " Kurs " . $entry['valutakurs'];
			$entry['amount'] = $entry['amount'] * $entry['valutakurs'] / $dagskurs;
			$entry['dkkamount'] = $entry['amount'] * $entry['valutakurs'] / 100;
		} else {
			$entry['description'] = $row['beskrivelse'];
			$entry['dkkamount'] = $entry['amount'];
		}

		if ($entry['oppvaluta'] == "-") {
			$entry['dkkamount'] = $entry['amount'];
			$entry['amount'] = 0;
			$entry['forfaldsdag'] = '';
		}

		$entries[] = $entry;
	}

	if (!$entries) {
		$lastdate = $firstdate;
	}

	$title = findtekst('133|Kontokort', $sprog_id);
	$pageTitle = findtekst('1141|Debitorrapport', $sprog_id) . " - " . lcfirst(findtekst('133|Kontokort', $sprog_id));
	$printLabel = findtekst('880|Udskriv', $sprog_id);
	$primoLabel = findtekst('1165|Primosaldo', $sprog_id);
	$projectLabel = db_fetch_array(db_select("select id from grupper where art = 'PRJ'", __FILE__ . " linje " . __LINE__)) ? 'Projekt' : '';
	$showProjectColumn = false;
	if ($projectLabel) {
		foreach ($entries as $entry) {
			if (trim((string)$entry['projekt']) !== '') {
				$showProjectColumn = true;
				break;
			}
		}
	}

	$baseParams = array(
		'rapportart' => 'kontokort',
		'layout' => 'grid',
		'submit' => 'ok',
		'dato_fra' => $dato_fra,
		'dato_til' => $dato_til,
		'konto_fra' => $konto_fra,
		'konto_til' => $konto_til,
		'returside' => $backUrl
	);

	$printFrom = $fromdate ? $fromdate : $firstdate;
	$printTo = $todate ? $todate : $lastdate;
	$emailUrl = "mail_kontoudtog.php?dato_fra=" . dkdato($printFrom) . "&dato_til=" . dkdato($printTo) . "&kontoantal=1&kontoliste=$accountId";

	if ($menu == 'T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">";
		print "<div class=\"headerbtnLft headLink\"><a href='" . debitorGeneralLedgerEscape($backUrl) . "' accesskey='L' title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst('2172|Luk', $sprog_id) . "</a></div>";
		print "<div class=\"headerTxt\">" . debitorGeneralLedgerEscape($pageTitle) . "</div>";
		print "<div class=\"headerbtnRght headLink\">";
		print "<a href='javascript:void(0);' onclick=\"showLangModalKontoprint()\" title='" . debitorGeneralLedgerEscape($printLabel) . "'><i class='fa fa-print fa-lg'></i> " . debitorGeneralLedgerEscape($printLabel) . "</a>";
		print "&nbsp;&nbsp;";
		print "<a href='javascript:void(0);' onclick=\"window.open('" . debitorGeneralLedgerEscape($emailUrl) . "','kontomail','" . debitorGeneralLedgerEscape($jsvars) . "')\" title='Email'><i class='fa fa-envelope fa-lg'></i> " . findtekst('52|E-mail', $sprog_id) . "</a>";
		print "</div>";
		print "</div>";
		print "<div class='content-noside debitor-ledger-page-shell'>";
	} else {
		include("../includes/oldDesign/header.php");
		print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n";
		include __DIR__ . "/generalLedgerTopLine.php";
		print "<tr><td align='center' valign='top' width='100%'><div class='debitor-ledger-page-shell'>";
	}

	$openingBalance = 0;
	$openingBalanceDkk = 0;
	$periodEntries = array();
	foreach ($entries as $entry) {
		if ($fromdate && $entry['transdate'] < $fromdate) {
			$openingBalance += $entry['amount'];
			$openingBalanceDkk += $entry['dkkamount'];
		} else {
			$periodEntries[] = $entry;
		}
	}

	$kontosum = $openingBalance;
	$dkksum = $openingBalanceDkk;
	$visiblerows = 0;
	$primoText = '';
	if ($valuta != 'DKK') {
		$primoText = " - Beløb kan være omregnet fra DKK";
	}

	print "<div class='debitor-ledger-page'>";
	print "<div class='debitor-ledger-sticky-header'>";
	print "<div class='debitor-ledger-page-title'>" . debitorGeneralLedgerEscape($pageTitle) . "</div>";
	print "<div class='debitor-ledger-info-wrap'>";
	print "<div class='debitor-ledger-info-left'>";
	print "<div class='debitor-ledger-company-name'>" . debitorGeneralLedgerEscape(stripslashes($account['firmanavn'])) . "</div>";
	$adresse1 = trim(isset($account['adresse1']) ? $account['adresse1'] : '');
	$adresse2 = trim(isset($account['adresse2']) ? $account['adresse2'] : '');
	$postnr = trim(isset($account['postnr']) ? $account['postnr'] : '');
	$bynavn = trim(isset($account['bynavn']) ? $account['bynavn'] : '');
	if ($adresse1) {
		print "<div class='debitor-ledger-address'>" . debitorGeneralLedgerEscape($adresse1) . "</div>";
	}
	if ($adresse2) {
		print "<div class='debitor-ledger-address'>" . debitorGeneralLedgerEscape($adresse2) . "</div>";
	}
	$cityLine = trim($postnr . ' ' . $bynavn);
	if ($cityLine) {
		print "<div class='debitor-ledger-address'>" . debitorGeneralLedgerEscape($cityLine) . "</div>";
	}
	print "</div>";
	print "<div class='debitor-ledger-info-right'>";
	print "<table class='debitor-ledger-meta-table'><tbody>";
	print "<tr><td>" . debitorGeneralLedgerEscape(findtekst('284|Kontonr.', $sprog_id)) . "</td><td>" . debitorGeneralLedgerEscape($account['kontonr']) . "</td></tr>";
	print "<tr><td>Dato</td><td>" . debitorGeneralLedgerEscape(date('d-m-Y')) . "</td></tr>";
	print "<tr><td>Valuta</td><td>" . debitorGeneralLedgerEscape($valuta) . "</td></tr>";
	print "</tbody></table>";
	print "</div>";
	print "</div>";
	print "<div class='debitor-ledger-opening-line'>";
	print "<div class='debitor-ledger-opening-label'>" . debitorGeneralLedgerEscape($primoLabel . $primoText) . "</div>";
	print "<div class='debitor-ledger-opening-amount' title='DKK " . debitorGeneralLedgerEscape(dkdecimal($openingBalanceDkk, 2)) . "'>" . debitorGeneralLedgerEscape(dkdecimal($openingBalance, 2)) . "</div>";
	print "</div>";
	print "</div>";

	print "<div class='debitor-ledger-table-wrap'>";
	print "<table class='datatable debitor-ledger-table' width='100%'>";
	print "<thead><tr>";
	print "<th>" . debitorGeneralLedgerEscape(findtekst(635, $sprog_id)) . "</th>";
	print "<th>" . debitorGeneralLedgerEscape(findtekst(671, $sprog_id)) . "</th>";
	print "<th>" . debitorGeneralLedgerEscape(findtekst(643, $sprog_id)) . "</th>";
	print "<th>" . debitorGeneralLedgerEscape(findtekst(1163, $sprog_id)) . "</th>";
	if ($showProjectColumn) {
		print "<th>" . debitorGeneralLedgerEscape($projectLabel) . "</th>";
	}
	print "<th>" . debitorGeneralLedgerEscape(findtekst(1164, $sprog_id)) . "</th>";
	print "<th class='text-right'>" . debitorGeneralLedgerEscape(findtekst(1000, $sprog_id)) . "</th>";
	print "<th class='text-right'>" . debitorGeneralLedgerEscape(findtekst(1001, $sprog_id)) . "</th>";
	print "<th class='text-right'>" . debitorGeneralLedgerEscape(findtekst(1073, $sprog_id)) . "</th>";
	print "</tr></thead>";
	print "<tbody>";

	foreach ($periodEntries as $index => $entry) {

		$visiblerows++;
		$rowClass = ($visiblerows % 2) ? 'debitor-ledger-row-odd' : 'debitor-ledger-row-even';
		$displayAmount = $entry['amount'] < 0 ? 0 - $entry['amount'] : $entry['amount'];
		$displayAmount = dkdecimal($displayAmount, 2);
		$forfaldsdag = $entry['forfaldsdag'];
		if (!$forfaldsdag) {
			$forfaldsdag = usdate(forfaldsdag($entry['transdate'], $betalingsbet, $betalingsdage));
		}
		$ffdag = debitorGeneralLedgerEscape(dkdato($forfaldsdag));
		if ($entry['udlignet'] != '1') {
			if ($forfaldsdag < date('Y-m-d')) {
				$ffdag = "<span style='color: rgb(255, 0, 0);'>" . debitorGeneralLedgerEscape(dkdato($forfaldsdag)) . "</span>";
			}
		}
		$refCell = debitorGeneralLedgerEscape($entry['refnr']);
		if ($entry['kladde_id']) {
			$ledgerReturnUrl = '../debitor/' . debitorGeneralLedgerUrl($baseParams);
			$refCell = "<a style='cursor:pointer;' title='Kladde ID: " . debitorGeneralLedgerEscape($entry['kladde_id']) . "' href='../finans/kassekladde.php?kladde_id=" . (int)$entry['kladde_id'] . "&returside=" . urlencode($ledgerReturnUrl) . "'>" . debitorGeneralLedgerEscape($entry['refnr']) . "</a>";
		}

		$debitCell = "0";
		$creditCell = "0";
		if ($entry['amount'] > 0) {
			if ($entry['udlignet'] != '1') {
				$debitCell = "<a class='debitor-ledger-open-link' title='Klik her for at udligne åbne poster' href='" . debitorGeneralLedgerEscape("../includes/udlign_openpost.php?post_id=" . (int)$entry['id'] . "&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&layout=grid&returside=" . urlencode($backUrl) . "&retur=../debitor/rapport.php") . "'>$displayAmount</a>";
			} else {
				$unAlignUrl = debitorGeneralLedgerUrl(array_merge($baseParams, array(
					'unAlign' => (int)$entry['udlign_id'],
					'oppId' => (int)$entry['id'],
					'unAlignAccount' => $accountId
				)));
				$debitCell = "<a class='debitor-ledger-balanced-link' href='" . debitorGeneralLedgerEscape($unAlignUrl) . "' title='Udlign id=" . (int)$entry['udlign_id'] . ". Klik for at ophæve udligningen' onclick=\"return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id " . (int)$entry['udlign_id'] . "?')\">$displayAmount</a>";
			}
		} else {
			if ($entry['udlignet'] != '1') {
				$creditCell = "<a class='debitor-ledger-open-link' title='Klik her for at udligne åbne poster' href='" . debitorGeneralLedgerEscape("../includes/udlign_openpost.php?post_id=" . (int)$entry['id'] . "&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&layout=grid&returside=" . urlencode($backUrl) . "&retur=../debitor/rapport.php") . "'>$displayAmount</a>";
			} else {
				$unAlignUrl = debitorGeneralLedgerUrl(array_merge($baseParams, array(
					'unAlign' => (int)$entry['udlign_id'],
					'oppId' => (int)$entry['id'],
					'unAlignAccount' => $accountId
				)));
				$creditCell = "<a class='debitor-ledger-balanced-link' I have merged and tested, and backgrounds in test_12 are displayed, so no problems here.href='" . debitorGeneralLedgerEscape($unAlignUrl) . "' title='Udlign id=" . (int)$entry['udlign_id'] . ". Klik for at ophæve udligningen' onclick=\"return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id " . (int)$entry['udlign_id'] . "?')\">$displayAmount</a>";
			}
		}

		$kontosum += afrund($entry['amount'], 2);
		$dkksum += $entry['dkkamount'];
		$dkksum = afrund($dkksum, 2);
		$balanceDisplay = dkdecimal($kontosum, 2);
		$balanceTitle = '';
		$diff = 0;
		if ($valuta != 'DKK' && $kontosum != $dkksum) {
			$balanceTitle = "DKK: " . dkdecimal($dkksum, 2);
			if (!$entry['oppvaluta'] || $entry['oppvaluta'] != '-') {
				$r = db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '" . $entry['transdate'] . "' order by valdate desc", __FILE__ . " linje " . __LINE__));
				if ($r) {
					$dagskurs = (float)$r['kurs'];
					$chkamount = $kontosum * $dagskurs / 100;
					$diff = afrund($chkamount - $dkksum, 2);
				}
			}
		}

		$regulering = afrund($diff, 2);
		$showDiffLink = 0;
		if ($regulering && $valuta != 'DKK' && $regnstart && $regnslut) {
			$showDiffLink = 1;
			foreach ($entries as $checkEntry) {
				if ($checkEntry['transdate'] == $entry['transdate'] && $checkEntry['oppvaluta'] == '-') {
					$showDiffLink = 0;
					break;
				}
			}
			if ($entry['id'] >= $max_valdif_id && $showDiffLink && abs($regulering) > 0.01 && $entry['transdate'] >= usdate($regnstart) && $entry['transdate'] <= usdate($regnslut)) {
				$diffUrl = "../includes/ret_valutadiff.php?bfdate=" . $entry['transdate'] . "&valuta=$valuta&diff=$regulering&post_id=" . (int)$entry['id'] . "&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&layout=grid&returside=" . urlencode($backUrl) . "&retur=../debitor/rapport.php";
				$balanceDisplay = "<a class='debitor-ledger-open-link' href='" . debitorGeneralLedgerEscape($diffUrl) . "'>" . debitorGeneralLedgerEscape($balanceDisplay) . "</a>";
			} else {
				$balanceDisplay = debitorGeneralLedgerEscape($balanceDisplay);
			}
		} else {
			$balanceDisplay = debitorGeneralLedgerEscape($balanceDisplay);
		}

		print "<tr class='$rowClass'>";
		print "<td>" . debitorGeneralLedgerEscape(dkdato($entry['transdate'])) . "</td>";
		print "<td>$refCell</td>";
		print "<td>" . debitorGeneralLedgerEscape($entry['faktnr']) . "</td>";
		print "<td>" . debitorGeneralLedgerEscape(stripslashes($entry['description'])) . "</td>";
		if ($showProjectColumn) {
			print "<td>" . debitorGeneralLedgerEscape($entry['projekt']) . "</td>";
		}
		print "<td>$ffdag</td>";
		print "<td class='text-right'>$debitCell</td>";
		print "<td class='text-right'>$creditCell</td>";
		print "<td class='text-right' title='" . debitorGeneralLedgerEscape($balanceTitle) . "'>$balanceDisplay</td>";
		print "</tr>";
	}

	if (!$visiblerows) {
		print "<tr class='debitor-ledger-empty-row'>";
		print "<td colspan='" . ($showProjectColumn ? '9' : '8') . "'>Ingen posteringer fundet for den valgte periode.</td>";
		print "</tr>";
	}

	print "</tbody></table></div>";
	print "<div class='debitor-ledger-footer-bar'>";
	print "<div class='debitor-ledger-footer-label'>Balance (DKK)</div>";
	print "<div class='debitor-ledger-footer-amount'>" . debitorGeneralLedgerEscape(dkdecimal($dkksum, 2)) . "</div>";
	print "</div>";
	print "</div>";

	print '
<div id="langModalKontoprint" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3);">
  <div style="background:#fff; padding:30px; border-radius:8px; width:300px; margin:10% auto; box-shadow:0 2px 10px #0003;">
    <h3>Select Language</h3>
    <select id="langSelectKontoprint" style="width:100%; padding:8px;">
     <option value="danish">Dansk</option>
     <option value="english">English</option>
    </select>
    <div style="margin-top:20px; text-align:right;">
      <button type="button" onclick="closeLangModalKontoprint()">Cancel</button>
      <button type="button" onclick="proceedKontoprint()">Print</button>
    </div>
  </div>
</div>
<script>
function showLangModalKontoprint() {
  document.getElementById("langModalKontoprint").style.display = "block";
}
function closeLangModalKontoprint() {
  document.getElementById("langModalKontoprint").style.display = "none";
}
function proceedKontoprint() {
  var lang = document.getElementById("langSelectKontoprint").value;
  var url = "kontoprint.php?dato_fra=' . $dato_fra . '&dato_til=' . $dato_til . '&konto_fra=' . $konto_fra . '&konto_til=' . $konto_til . '&kontoart=D&lang=" + lang;
  window.open(url, "kontoprint", "left=0,top=0,width=1000,height=700,scrollbars=yes,resizable=yes,menubar=no,location=no");
  closeLangModalKontoprint();
}
	(function() {
	  function updateLedgerStickyOffset() {
	    var topLine = document.querySelector(".topLine");
	    var stickyHeader = document.querySelector(".debitor-ledger-sticky-header");
	    var topLineHeight = topLine ? Math.ceil(topLine.getBoundingClientRect().height) : 0;
	    var stickyHeaderHeight = stickyHeader ? stickyHeader.offsetHeight : 0;
	    document.documentElement.style.setProperty("--debitor-ledger-shell-top-offset", topLineHeight + "px");
	    document.documentElement.style.setProperty("--debitor-ledger-sticky-offset", stickyHeaderHeight + "px");
	  }
  document.addEventListener("DOMContentLoaded", updateLedgerStickyOffset);
  window.addEventListener("resize", updateLedgerStickyOffset);
  updateLedgerStickyOffset();
})();
</script>
';

	print "<style>
	html, body {
		height: 100%;
		overflow: hidden;
		padding: 0;
	}

		.debitor-ledger-page-shell {
			display: block;
			box-sizing: border-box;
			width: 100%;
			max-width: 100%;
			height: calc(100vh - var(--debitor-ledger-shell-top-offset, 34px));
			height: calc(100dvh - var(--debitor-ledger-shell-top-offset, 34px));
			max-height: calc(100vh - var(--debitor-ledger-shell-top-offset, 34px));
			max-height: calc(100dvh - var(--debitor-ledger-shell-top-offset, 34px));
			min-height: 0;
			overflow: hidden;
		}

	.debitor-ledger-page,
	.debitor-ledger-sticky-header,
	.debitor-ledger-info-wrap,
	.debitor-ledger-table-wrap,
	.debitor-ledger-table {
		box-sizing: border-box;
		max-width: 100%;
	}

	.debitor-ledger-page {
		width: 100%;
		height: 100%;
		min-height: 0;
		display: flex;
		flex-direction: column;
		padding: 6px 0 0;
		overflow: hidden;
	}

	.debitor-ledger-page-title {
		font-size: 1.15rem;
		font-weight: 700;
		color: #1f3c74;
		margin-bottom: 1px;
		padding: 0 0 1px;
		border-bottom: 1px solid #8d8d8d;
		text-align: center;
		line-height: 1.2;
	}

	.debitor-ledger-sticky-header {
		position: sticky;
		top: 0;
		z-index: 55;
		flex: 0 0 auto;
		padding-bottom: 0;
		background: #f5f6fb;
	}

	.debitor-ledger-info-wrap {
		display: grid;
		grid-template-columns: minmax(0, 1fr) 260px;
		align-items: start;
		gap: 24px;
		padding: 0 0 1px;
		min-height: 0;
		border-bottom: 1px solid #8d8d8d;
		background: #f5f6fb;
	}

	.debitor-ledger-info-left {
		min-width: 0;
		display: block;
		text-align: left;
		padding: 0;
		align-self: start;
	}

	.debitor-ledger-company-name {
		font-size: 1rem;
		font-weight: 600;
		color: #000;
		line-height: 1.4;
		text-align: left;
		margin: 0 0 2px;
		margin-left: 4px;
	}

	.debitor-ledger-address {
		font-size: 0.95rem;
		color: #000;
		line-height: 1.3;
		text-align: left;
		margin: 0;
		margin-left: 4px;
	}

	.debitor-ledger-info-right {
		grid-column: 2;
		justify-self: end;
		align-self: start;
		min-width: 0;
		padding: 0 4px 0 0;
	}

	.debitor-ledger-meta-table {
		width: 100%;
		border-collapse: collapse;
	}

	.debitor-ledger-meta-table td {
		padding: 0;
		font-size: 0.95rem;
		line-height: 1.05;
		vertical-align: top;
	}

	.debitor-ledger-meta-table td:first-child {
		text-align: right;
		padding-right: 18px;
		color: #000;
	}

	.debitor-ledger-meta-table td:last-child {
		text-align: right;
		font-weight: 400;
		color: #000;
	}

		.debitor-ledger-table-wrap {
			flex: 1 1 auto;
			min-height: 0;
			margin-top: 0;
			background: #fff;
			overflow: auto;
			overscroll-behavior: contain;
		}

	.debitor-ledger-table thead th {
		background: #fff;
		text-align: left;
		border-bottom: 1px solid #7f7f7f;
		font-weight: 600;
		color: #000;
		padding: 2px 4px;
		font-size: 0.95rem;
		white-space: nowrap;
		position: sticky;
		top: 0;
		z-index: 2;
	}

	.debitor-ledger-table thead th:nth-child(4) {
		white-space: normal;
	}

	.debitor-ledger-opening-line {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		align-items: end;
		gap: 16px;
		padding: 3px 4px 3px;
		background: #f5f6fb;
		border-bottom: 1px solid #7f7f7f;
	}

	.debitor-ledger-opening-label {
		text-align: center;
		font-size: 0.95rem;
	}

	.debitor-ledger-opening-amount {
		text-align: right;
		font-size: 0.95rem;
		min-width: 72px;
	}

	.debitor-ledger-table {
		border-collapse: separate;
		border-spacing: 0;
	}

	.debitor-ledger-table td,
	.debitor-ledger-table th {
		vertical-align: top;
	}

		.debitor-ledger-footer-bar {
			flex: 0 0 auto;
			margin-top: auto;
			position: sticky;
			bottom: 0;
			z-index: 45;
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		align-items: center;
		gap: 16px;
		padding: 10px 4px 16px 4px;
		border-top: solid 1px #7f7f7f;
	}

	.debitor-ledger-footer-label {
		font-weight: 600;
	}

	.debitor-ledger-footer-amount {
		font-weight: 600;
		text-align: right;
		min-width: 72px;
	}

	.debitor-ledger-empty-row td {
		padding: 14px 12px;
		text-align: center;
		color: #3f5d95;
		background: #fff;
	}

	.debitor-ledger-row-odd td {
		background: #e7e8fb;
	}

	.debitor-ledger-row-even td {
		background: #fff;
	}

	.debitor-ledger-table td,
	.debitor-ledger-table th {
		padding: 2px 4px;
		#font-size: 0.95rem;
		white-space: nowrap;
	}

	.debitor-ledger-table td:nth-child(4),
	.debitor-ledger-table th:nth-child(4) {
		white-space: normal;
	}

	.debitor-ledger-table .text-right {
		text-align: right;
	}

	.debitor-ledger-table a {
		color: #154ab3;
		text-decoration: underline;
	}

	.debitor-ledger-balanced-link {
		text-decoration: none !important;
	}

	.debitor-ledger-open-link {
		text-decoration: underline !important;
	}

		@media (max-width: 820px) {
			.debitor-ledger-page-shell {
				height: calc(100vh - var(--debitor-ledger-shell-top-offset, 34px));
				height: calc(100dvh - var(--debitor-ledger-shell-top-offset, 34px));
				max-height: calc(100vh - var(--debitor-ledger-shell-top-offset, 34px));
				max-height: calc(100dvh - var(--debitor-ledger-shell-top-offset, 34px));
			}

		.debitor-ledger-page {
			height: 100%;
			padding: 8px 8px 0;
		}

		.debitor-ledger-info-wrap {
			grid-template-columns: 1fr;
			gap: 8px;
			padding-top: 0;
			min-height: 0;
		}

		.debitor-ledger-opening-line {
			grid-template-columns: 1fr;
			gap: 2px;
			padding-left: 0;
			padding-right: 0;
		}

		.debitor-ledger-header-table th {
			white-space: normal;
		}

		.debitor-ledger-info-left,
		.debitor-ledger-info-right {
			padding-top: 0;
			width: 100%;
			min-width: 0;
			text-align: left;
		}

		.debitor-ledger-meta-table td:first-child,
		.debitor-ledger-meta-table td:last-child {
			text-align: left;
			padding-right: 0;
		}

		.debitor-ledger-table td,
		.debitor-ledger-table th {
			white-space: normal;
		}

		.debitor-ledger-opening-label,
		.debitor-ledger-opening-amount,
		.debitor-ledger-footer-label,
		.debitor-ledger-footer-amount {
			text-align: left;
		}
	}
	</style>";

	if ($menu == 'T') {
		print "<style>
	/* Hide old top-menu navigation — sidebar lives in the outer shell */
	.logobar, .navbar, #guideOverlayTop {
		display: none !important;
		height: 0 !important;
	}
	body {
		display: flex;
		flex-direction: column;
	}
	#header {
		flex-shrink: 0;
	}
	.debitor-ledger-page-shell {
		flex: 1 1 auto !important;
		min-height: 0 !important;
		height: auto !important;
		max-height: none !important;
		padding: 0 8px !important;
		display: flex !important;
		flex-direction: column !important;
		overflow: hidden !important;
	}
	.debitor-ledger-page {
		flex: 1 1 auto !important;
		min-height: 0 !important;
		height: auto !important;
	}
	</style>";
	}

	if ($menu == 'T') {
		print "</div>";
		include_once '../includes/topmenu/footer.php';
	} else {
		print "</div></td></tr></tbody></table>";
		include_once '../includes/oldDesign/footer.php';
	}
}
