<?php

// ------------------------------------------------------------includes/autoudlign.php-------lap 1.0.8---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------

function autoudlign($udlign)
{ 
	$y=0;
	$v=0;

	$udlign=$udlign*1;
	if ($udlign>0) { #sker kun når kontoens saldo er 0
		$q1=db_select("SELECT MAX(udlign_id) as udlign_id from openpost");
		if ($r1=db_fetch_array($q1)) $y=$r1['udlign_id'];
		$tilfoj="konto_id=$udlign and udlignet!='1' and";
	}
	else $tilfoj="udlignet='1' and";

	for ($i=1;$i<=5;$i++) {

		$kontrol=array();
		$x=0;
		if ($i<=2) $q1=db_select("SELECT id, faktnr, amount, konto_id from openpost where $tilfoj faktnr != '' and udlign_id = '0' order by transdate");
		elseif ($i==3) $q1=db_select("SELECT id, amount, konto_id from openpost where $tilfoj faktnr != '' and udlign_id = '0' order by transdate");
		elseif ($i==4) $q1=db_select("SELECT id, amount, konto_id from openpost where $tilfoj faktnr = '' and udlign_id = '0' order by transdate");
		elseif ($i==5) $q1=db_select("SELECT id, amount, konto_id from openpost where $tilfoj udlign_id = '0' order by transdate");

		while ($r1=db_fetch_array($q1)) {
			if(!in_array($r1[id], $kontrol)) {
				$x++;
				$id[$x]=$r1['id'];
				$faktnr[$x]=$r1['faktnr'];
				$amount[$x]=$r1['amount']*-1;
				$konto_id[$x]=$r1['konto_id'];
			}
		}
		$faktantal=$x;

		for ($x=1; $x<=$faktantal; $x++) {
			if ($i==1) $q1=db_select("SELECT id, transdate, faktnr from openpost where $tilfoj id != '$id[$x]' and amount='$amount[$x]' and konto_id='$konto_id[$x]' and udlignet='1' and udlign_id = '0' order by transdate");
			elseif ($i==2) $q1=db_select("SELECT id, transdate from openpost where $tilfoj id != '$id[$x]' and amount='$amount[$x]' and konto_id='$konto_id[$x]' and  udlign_id = '0' and udlignet='1' order by transdate");
			elseif ($i<=4) $q1=db_select("SELECT id, transdate from openpost where $tilfoj id != '$id[$x]' and amount='$amount[$x]' and konto_id='$konto_id[$x]' and  udlign_id = '0' and udlignet='1' order by transdate");
			elseif ($i==5) $q1=db_select("SELECT id, transdate from openpost where $tilfoj id != '$id[$x]' and  udlign_id = '0'");
			$z=0;
			if (($r1=db_fetch_array($q1))&&(!in_array($r1[id], $kontrol))) {
				$z++;
				$id2=$r1['id'];	
				$transdate=$r1['transdate'];	
			}
			if (($z==1)&&(!in_array($id[$x], $kontrol))&&(!in_array($id2, $kontrol))){
				$y++;
				$v++;
				$kontrol[$v]=$id[$x];
				$v++;
				$kontrol[$v]=$id2;
				db_modify("update openpost set udlignet='1', udlign_id='$y', udlign_date='$transdate' where id='$id[$x]'");
				db_modify("update openpost set udlignet='1', udlign_id='$y', udlign_date='$transdate' where id='$id2'");
			}
		}	
	}
	$y++;
	if ($udlign>0) { #Hvis der er nogen tilbage som ikke er blevet udlignet
		$q1=db_select("SELECT id, transdate from openpost where konto_id='$udlign' and udlignet = '0'");
		while ($r1=db_fetch_array($q1)) {
			db_modify("update openpost set udlignet='1', udlign_id='$y', udlign_date='$r1[transdate]' where id='$r1[id]'");
		}
	}
}
?>