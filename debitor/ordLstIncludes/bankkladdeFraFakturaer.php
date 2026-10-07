<?php
// --- debitor/ordLstIncludes/bankkladdeFraFakturaer.php --- ver 5.0.0 --- 2026-10-06 ---
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
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261006 LOE Ticked invoices are copied to a bank draft as paid: one line per invoice on the
//                 invoice's open amount, described by the invoice's own text, or by its name
//                 when the invoice has no text.

# A chart description that names a bank account the money can arrive on. Interest, loan, debt, fee
# and exchange-loss accounts carry "bank" in their text without being an account a customer payment
# lands on, so they are not offered.
function bankkladdeBanknavn($beskrivelse) {
	$navn = strtolower(trim((string)$beskrivelse));
	if (strpos($navn,'bank') === false) return false;
	$udelukket = array('rente','lån','laan','gæld','kurstab','gebyr','omkostning');
	foreach ($udelukket as $ord) {
		if (strpos($navn,$ord) !== false) return false;
	}
	return true;
}

# The bank accounts the chart offers for the fiscal year, as kontonr => beskrivelse. Which one the
# money arrives on is the tenant's own decision, so the list is offered with the lowest numbered
# account as the default rather than chosen silently.
function bankkladdeKonti($regnaar) {
	$konti = array();
	$qtxt = "select kontonr, beskrivelse from kontoplan where regnskabsaar='$regnaar' order by kontonr";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)) {
		if (!bankkladdeBanknavn($row['beskrivelse'])) continue;
		$konti[bankkladdeKontonr($row['kontonr'])] = trim($row['beskrivelse']);
	}
	return $konti;
}

# The default account for a caller that has no selection to pass on: the lowest numbered candidate.
function bankkladdeKonto($regnaar,$bruger_id) {
	$konti = bankkladdeKonti($regnaar);
	foreach ($konti as $kontonr => $beskrivelse) return $kontonr;
	return '';
}

# kontonr is numeric in the chart and may arrive as "9800.0" from some drivers.
function bankkladdeKontonr($konto) {
	$konto = trim((string)$konto);
	if (substr($konto,-2) === '.0') $konto = substr($konto,0,-2);
	return $konto;
}

# The text on the draft line: the invoice's own text when it has one, otherwise the invoice's name.
function bankkladdeBeskrivelse($notes,$firmanavn,$fakturanr) {
	$notes = trim((string)$notes);
	if ($notes !== '') return $notes;
	$navn = trim((string)$firmanavn);
	$fakturanr = trim((string)$fakturanr);
	if ($navn !== '' && $fakturanr !== '') return $navn . " - Faktura " . $fakturanr;
	if ($fakturanr !== '') return "Faktura " . $fakturanr;
	return $navn;
}

# The lines a batch would write, and the reason for every ticked invoice that cannot be copied.
# Nothing is written here, so the same plan serves both the dry run and the real run.
function bankkladdePlan($ordre_ids,$konto) {
	global $sprog_id;
	$sprog = isset($sprog_id) ? $sprog_id : 1;
	$plan = array('linjer'=>array(),'udeladt'=>array(),'total'=>0);
	$set = array();
	foreach ($ordre_ids as $ordre_id) {
		$ordre_id = (int)$ordre_id;
		if (!$ordre_id) continue;
		$qtxt = "select art,fakturanr,konto_id,kontonr,firmanavn,notes,valuta,afd,projekt ";
		$qtxt.= "from ordrer where id='$ordre_id'";
		$row = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if (!$row) {
			$plan['udeladt'][] = array('id'=>$ordre_id,'fakturanr'=>'','aarsag'=>findtekst('5417|Ordren findes ikke',$sprog));
			continue;
		}
		$fakturanr = trim($row['fakturanr']);
		$kunde     = trim($row['firmanavn']);
		if ($fakturanr === '' || $fakturanr === '0') {
			$plan['udeladt'][] = array('id'=>$ordre_id,'fakturanr'=>'','aarsag'=>findtekst('5418|Ikke faktureret',$sprog),'kunde'=>$kunde);
			continue;
		}
		# a reminder or credit note number is not an invoice number, and the open item is looked up
		# by the invoice number, so anything that is not numeric is left to the user
		if (!is_numeric($fakturanr)) {
			$plan['udeladt'][] = array('id'=>$ordre_id,'fakturanr'=>$fakturanr,'aarsag'=>findtekst('5419|Fakturanummeret er ikke numerisk',$sprog),'kunde'=>$kunde);
			continue;
		}
		if (substr(trim($row['art']),0,1) !== 'D') {
			$plan['udeladt'][] = array('id'=>$ordre_id,'fakturanr'=>$fakturanr,'aarsag'=>findtekst('5420|Ikke en debitorfaktura',$sprog),'kunde'=>$kunde);
			continue;
		}
		if (!is_numeric(trim($row['kontonr']))) {
			$plan['udeladt'][] = array('id'=>$ordre_id,'fakturanr'=>$fakturanr,'aarsag'=>findtekst('5421|Kunden har ikke et kontonummer',$sprog),'kunde'=>$kunde);
			continue;
		}
		$valuta = trim($row['valuta']);
		if ($valuta !== '' && strtoupper($valuta) !== 'DKK') {
			$plan['udeladt'][] = array('id'=>$ordre_id,'fakturanr'=>$fakturanr,'aarsag'=>findtekst('5422|Udenlandsk valuta',$sprog),'kunde'=>$kunde);
			continue;
		}
		if (isset($set[$fakturanr])) {
			$plan['udeladt'][] = array('id'=>$ordre_id,'fakturanr'=>$fakturanr,'aarsag'=>findtekst('5423|Fakturaen er valgt mere end en gang',$sprog),'kunde'=>$kunde);
			continue;
		}
		# the open item is the evidence that the invoice is posted and still unpaid, and its amount
		# is what the settlement must use - the invoice total can differ after a partial payment
		$qtxt = "select id,amount,valuta from openpost where konto_id='".(int)$row['konto_id']."' ";
		$qtxt.= "and coalesce(udlignet,'')<>'1' and faktnr='$fakturanr' order by id";
		$open = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if (!$open) {
			$plan['udeladt'][] = array('id'=>$ordre_id,'fakturanr'=>$fakturanr,'aarsag'=>findtekst('5424|Ingen åben post',$sprog),'kunde'=>$kunde);
			continue;
		}
		$belob = (float)$open['amount'];
		if ($belob <= 0) {
			$plan['udeladt'][] = array('id'=>$ordre_id,'fakturanr'=>$fakturanr,'aarsag'=>findtekst('5425|Beløbet er ikke positivt',$sprog),'kunde'=>$kunde);
			continue;
		}
		# the same invoice must not be copied twice, not even before the first draft is posted
		$qtxt = "select k.id from kassekladde k, kladdeliste l where k.kladde_id=l.id ";
		$qtxt.= "and k.ordre_id='$ordre_id' and (l.bogfort='-' or l.bogfort='!')";
		if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$plan['udeladt'][] = array('id'=>$ordre_id,'fakturanr'=>$fakturanr,'aarsag'=>findtekst('5426|Ligger allerede i en åben kladde',$sprog),'kunde'=>$kunde);
			continue;
		}
		$set[$fakturanr] = 1;
		$plan['linjer'][] = array(
			'ordre_id'   => $ordre_id,
			'fakturanr'  => $fakturanr,
			'kunde'      => $kunde,
			'kredit'     => trim($row['kontonr']),
			'amount'     => $belob,
			'beskrivelse'=> bankkladdeBeskrivelse($row['notes'],$row['firmanavn'],$fakturanr),
			'afd'        => (int)$row['afd'],
			'projekt'    => trim($row['projekt'])
		);
		$plan['total'] = $plan['total'] + $belob;
	}
	return $plan;
}

# Writes the plan as one draft on the bank account: the bank is debited and the customer's account
# is credited per invoice, so posting settles the invoice's open item. Returns the draft's id.
function bankkladdeOpret($plan,$konto,$dato,$kladdenote,$simuler=false) {
	global $brugernavn;
	global $regnaar;
	$resultat = array('kladde_id'=>0,'antal'=>0,'total'=>0,'fejl'=>'');
	if (!$plan['linjer']) {
		$resultat['fejl'] = 'ingen fakturaer';
		return $resultat;
	}
	if (!is_numeric($konto) || $konto === '') {
		$resultat['fejl'] = 'ingen bankkonto';
		return $resultat;
	}
	# the account has to be in the chart of the fiscal year the draft is written in
	$qtxt = "select kontonr from kontoplan where kontonr='$konto' and regnskabsaar='$regnaar'";
	if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$resultat['fejl'] = 'ukendt bankkonto';
		return $resultat;
	}
	if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$dato)) {
		$resultat['fejl'] = 'ugyldig dato';
		return $resultat;
	}
	$resultat['antal'] = count($plan['linjer']);
	$resultat['total'] = $plan['total'];
	if ($simuler) return $resultat;

	$note = db_escape_string($kladdenote);
	$tidspkt = microtime();
	transaktion('begin');
	$qtxt = "select max(id) as id from kladdeliste";
	$row = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$kladde_id = ((int)$row['id']) + 1;
	$qtxt = "insert into kladdeliste (id, kladdenote, kladdedate, bogfort, hvem, oprettet_af, tidspkt) ";
	$qtxt.= "values ('$kladde_id','$note','$dato','-','" . db_escape_string($brugernavn) . "','" . db_escape_string($brugernavn) . "','$tidspkt')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$bilag = 0;
	foreach ($plan['linjer'] as $linje) {
		$bilag++;
		$beskrivelse = db_escape_string($linje['beskrivelse']);
		$projekt = db_escape_string($linje['projekt']);
		$amount = $linje['amount'];
		$qtxt = "insert into kassekladde ";
		$qtxt.= "(bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,afd,projekt,ordre_id) ";
		$qtxt.= "values ";
		$qtxt.= "('$bilag','$dato','$beskrivelse','F','$konto','D','" . $linje['kredit'] . "','" . $linje['fakturanr'] . "',";
		$qtxt.= "'$amount','$kladde_id','" . $linje['afd'] . "','$projekt','" . $linje['ordre_id'] . "')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	transaktion('commit');
	$resultat['kladde_id'] = $kladde_id;
	return $resultat;
}

# The whole batch: resolve the account, plan, and write the draft.
function bankkladdeFraFakturaer($ordre_ids,$dato,$konto,$kladdenote,$simuler=false) {
	$plan = bankkladdePlan($ordre_ids,$konto);
	$resultat = bankkladdeOpret($plan,$konto,$dato,$kladdenote,$simuler);
	$resultat['plan'] = $plan;
	return $resultat;
}
