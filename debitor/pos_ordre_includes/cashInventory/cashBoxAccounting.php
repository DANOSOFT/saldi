<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/cashInventory/cashBoxAccounting.php ---------- lap 3.7.9----2019.05.09-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190310 LN Set the function posbogfor here
// LN 20190310 LN Include the file cashBoxAccounting/basicData.php
// LN 20190310 LN Include the file cashBoxAccounting/valuta.php

function posbogfor ($kasse,$regnstart) {
	global $afd;
	global $brugernavn;
	global $db;
	global $regnaar;
	global $vis_saet;
	
    include("pos_ordre_includes/cashInventory/cashBoxAccounting/basicData.php"); #20190510

	$ko_id=NULL; #Salg på konto
	transaktion('begin');
	for ($z=0;$z<count($valuta);$z++) { #201606132 Flyttet fra nederst (af de 3 for løkker) til øverst"
		for ($x=0;$x<count($fakturadate);$x++) {
			for ($y=0;$y<count($betaling);$y++) {
				$id=NULL;
				$k=0;
				$qtxt="select ordrer.id,ordrer.konto_id from ordrer,pos_betalinger where ordrer.felt_5='$kasse' and ordrer.fakturadate='$fakturadate[$x]' ";
				$qtxt.="and pos_betalinger.betalingstype='$betaling[$y]' ";
				$qtxt.="and pos_betalinger.valuta='$valuta[$z]' and ";
				$qtxt.="ordrer.status='3' and ordrer.id=pos_betalinger.ordre_id"; #20150306 + 20150310
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					if (strtolower($betaling[$y])=='konto') {
						($ko_id)?$ko_id.=",".$r['id']:$ko_id=$r['id']; # salg på konto
					} else {
						($id)?$id.=",".$r['id']:$id=$r['id'];
						($kto_id)?$kto_id.=",".$r['konto_id']:$kto_id=$r['konto_id'];
					}
				}
				$qtxt="select box9 from grupper where art='POS' and kodenr='1'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if($id) {
					$svar=bogfor_nu("$id","Dagsafslutning");
					if ($svar=='OK') {
					} else {
						echo "$svar<br>\n";
						print "Der er konstateret en uoverensstemmelse i posteringssummen, ID $ordre_id ordre $ordrenr, d=$d_kontrol, k=$k_kontrol kontakt saldi.dk p&aring; telefon 4690 2208";
						print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverenstemmelse i posteringssummen. \\nKontakt saldi.dk på telefon 4690 2208 eller 2066 9820')\">\n";
						exit;
						print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
						exit;
					} 
				}
			} # 20160612 Flyttet fra under nedenstående blok
		} # 20160612 Flyttet fra under nedenstående blok
#		}
		if ($ko_id) {
			$k_oid=explode(',',$ko_id);
			for ($k=0;$k<count($k_oid);$k++) {
				if ($k_oid[$k]) {
					$svar=bogfor_nu($k_oid[$k],'');
					if ($svar!='OK') {
						echo $svar."<br>";
						exit;
					}
				}
			}
		}

        include("pos_ordre_includes/cashInventory/cashBoxAccounting/valuta.php"); #20190510
		
		if ($change_cardvalue && $kassekonto) {
		$diffsum=0;
		$ny_kortsum=if_isset($_POST['ny_kortsum']);
		$kortsum=if_isset($_POST['kortsum']);
		$kortnavn=if_isset($_POST['kortnavn']);
		$kontkonto=if_isset($_POST['kontkonto']);
		for ($y=0;$y<count($kortnavn);$y++) {
			$ny_kortsum[$y]=usdecimal($ny_kortsum[$y],2);
			if ($diff=$ny_kortsum[$y]-$kortsum[$y]) {
				$debet=0;
				$kredit=0;
				($diff>0)?$debet=$diff:$kredit-=$diff;
				$diffsum+=$diff;
				$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,";
				$qtxt.="ansat,ordre_id,kasse_nr)";
				$qtxt.="values ";
				$qtxt.="('0','$dd','Efterpost - bet.kort kasse $kasse','$kontkonto[$y]','0','$debet','$kredit',0,'$afd','$dd','$logtime','',";
				$qtxt.="'$ansat_id','0','$kasse')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		if (abs(afrund($diffsum,2))>=0.01) {
			$debet=0;
			$kredit=0;
			($diffsum>0)?$kredit=$diffsum:$debet-=$diffsum;
			$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,";
			$qtxt.="ansat,ordre_id,kasse_nr)";
			$qtxt.="values ";
			$qtxt.="('0','$dd','Efterpost - bet.kort kasse $kasse','$kassekonto','0','$debet','$kredit',0,'$afd','$dd','$logtime','',";
			$qtxt.="'$ansat_id','0','$kasse')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	$logtime=date("U")+60;
	$logtime=date("H:i:s",$logtime);
	$qtxt = "insert into transaktioner";
	$qtxt.= " (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr)";
	$qtxt.= " values";
	$qtxt.= " ('0','$dd','Kasseoptaelling,kasse $kasse','0','0','0','0','0','$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__); # 20161116
}
#transaktion('rollback');
#exit;
transaktion('commit');
# <-- 20140709
}

?>

