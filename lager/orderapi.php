<?php

@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$vare_id = isset($_POST["vare_id"]) ? $_POST["vare_id"] : null;
$antal = isset($_POST["antal"]) ? $_POST["antal"] : "1,00";

$antal = usdecimal($antal);

if ($vare_id === null) {
	exit;
}

genbestil($vare_id, $antal);

function genbestil($vare_id, $antal) {
	global $brugernavn,$db,$regnaar,$sprog_id;
	
	# Hent ansant til ordre ref
	$r = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
	if ($r) {
		$r = db_fetch_array(db_select("select navn from ansatte where id = $r[ansat_id]",__FILE__ . " linje " . __LINE__));
		($r['navn'])?$ref=$r['navn']:$ref=NULL;
	} else {
        $ref = NULL;
    }

	# Find leverandøre til vare id'et
	$qtxt="select * from vare_lev where vare_id = '$vare_id' order by posnr";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$lev_id=$r['lev_id'];
		$lev_varenr=$r['lev_varenr'];
		$pris=(int)$r['kostpris'];
		$ordredate=date("Y-m-d");

		# Se om der er et åben't forslag med ordredato i dag
		$qtxt="select id, sum from ordrer where konto_id = $lev_id and art = 'KO' and status < 1 and ordredate = '$ordredate'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$sum=(int)$r['sum'];
			$ordre_id=$r['id'];
		} else {
			# Get latest ordrenr
			$qtxt="select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $ordrenr=$r['ordrenr']+1;
			else $ordrenr=1;
			
			# Fetch info on the kreditor
			$qtxt="select * from adresser where id = $lev_id";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			# Check if the kreditor is part of a kreditor group to see what momssats should be used
			if ($r['gruppe']) {
				$qtxt = "select box1 from grupper ";
				$qtxt.= "where kode = 'K' and art = 'KG' and kodenr = '$r[gruppe]' and fiscal_year = '$regnaar'";
				$r1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				# Fetch the momskode
				$kode=substr($r1['box1'],0,1); 
                $kodenr=substr($r1['box1'],1);
			}	else {
				$qtxt="select varenr from varer where id = '$vare_id'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				print "<BODY onLoad=\"javascript:alert('Leverand&oslash;rgruppe ikke korrekt opsat for varenr $r[varenr]')\">";
                return;
			}

			# Fetch the momssats from the momskode
            if ($kode) {
                $qtxt = "select box2 from grupper where art = 'KM' and kode = '$kode' and kodenr = '$kodenr' and fiscal_year = '$regnaar'";
                $r1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                $momssats=(int)$r1['box2'];
            } else {
                $momssats = 0;
            }

			# Create the order
			$qtxt="insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,"; #218180822
			$qtxt.="betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, ref)";
			$qtxt.=" values ";
			$qtxt.="('$ordrenr','$r[id]','$r[kontonr]','".db_escape_string($r['firmanavn'])."','".db_escape_string($r['addr1'])."',";
			$qtxt.= "'".db_escape_string($r['addr2'])."','".db_escape_string($r['postnr'])."','".db_escape_string($r['bynavn'])."',";
			$qtxt.= "'".db_escape_string($r['land]'])."','$r[betalingsdage]','$r[betalingsbet]','$r[cvrnr]','".db_escape_string($r['notes'])."',";
			$qtxt.= "'KO','$ordredate','$momssats','0','".db_escape_string($ref)."')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);

			# Fetch the dynamically generated ordrenr
			$qtxt="select id from ordrer where ordrenr='$ordrenr' and art = 'KO'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$ordre_id=$r['id'];
		}
		# Get the vare information
		$qtxt="select varer.varenr as varenr,varer.beskrivelse as beskrivelse,varer.enhed as enhed,";
		$qtxt.="vare_lev.lev_varenr as lev_varenr,grupper.box7 as momsfri ";
		$qtxt.="from varer,vare_lev,grupper where ";
		$qtxt.="varer.id='$vare_id' and vare_lev.vare_id='$vare_id' and grupper.art='VG' and grupper.kodenr=varer.gruppe"; #20190313
	
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$varenr=db_escape_string($r['varenr']);
		$lev_varenr=db_escape_string($r['lev_varenr']);
		$enhed=db_escape_string($r['enhed']);
		$beskrivelse=db_escape_string($r['beskrivelse']);
		$momsfri=$r['momsfri'];
		
		# Add the vare to the order
		$qtxt="insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, pris, lev_varenr, antal, momsfri)";
		$qtxt.=" values ";
		$qtxt.="('$ordre_id', '1000', '$varenr', '$vare_id', '$beskrivelse', '$enhed', '$pris', '$lev_varenr', '$antal', '$momsfri')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$sum=$sum+$pris*$antal;	
		db_modify("update ordrer set sum = '$sum' where id = $ordre_id",__FILE__ . " linje " . __LINE__);	
	} else { 
		# Ingen leverandør
		$r = db_fetch_array(db_select("select varenr from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
		print "".findtekst(951,$sprog_id)." findes ikke (Varenr: $r[varenr])<br>";
	}
}