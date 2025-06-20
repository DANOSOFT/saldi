<?php
// -------systemdata/ansatte_load.php--------lap 3.0.0-------2013-01-22---06:51----
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2003-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
if ($id > 0) {
	$r=db_fetch_array(db_select("select * from ansatte where id = '$id'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id'];
	$nummer=$r['nummer'];
	$navn=htmlspecialchars($r['navn']);
	$initialer=htmlspecialchars($r['initialer']);
	$addr1=htmlspecialchars($r['addr1']);
	$addr2=htmlspecialchars($r['addr2']);
	$postnr=$r['postnr'];
	$bynavn=htmlspecialchars($r['bynavn']);
	$email=htmlspecialchars($r['email']);
	$tlf=$r['tlf'];
	$fax=$r['fax'];
	$mobil=$r['mobil'];
	$privattlf=$r['privattlf'];
	$cprnr=$r['cprnr'];
	$notes=htmlspecialchars($r['notes']);
	$afd=$r['afd']*1;
	$lukket=$r['lukket'];
	$gruppe=$r['gruppe'];
	$bank=$r['bank'];
	$loen=dkdecimal($r['loen']);
	$extraloen=dkdecimal($r['extraloen']);
	$startdate=$r['startdate'];
	$slutdate=$r['slutdate'];
	($r['trainee'])?$trainee="checked=\"cheched\"":$trainee=NULL;
	if ($startdate=='1900-01-01') $startdate=NULL;
	if ($slutdate=='9999-12-31') $slutdate=NULL;

	if (!$nummer) {
		$r = db_fetch_array(db_select("select max(nummer) as nummer from ansatte where id != '$id'",__FILE__ . " linje " . __LINE__));
		$nummer=$r['nummer']+1;
	}	
	$r=db_fetch_array(db_select("select * from brugere where ansat_id='$id'",__FILE__ . " linje " . __LINE__));
	$brugere_id=$r['id'];
	$brugere_navn=$r['brugernavn'];
	
} else $id=0;
if (!$konto_id) {
	$r = db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['id'];
}	
$x=0;
$q = db_select("SELECT * FROM grupper WHERE art = 'AFD' ORDER BY kodenr",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$afd_nr[$x]=$r['kodenr'];
	$afd_navn[$x]=$r['beskrivelse'];
	$x++;
}

if ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='2' and box3='on'",__FILE__ . " linje " . __LINE__))) {
	$q=db_select("select * from grupper where art='ANSAT' and kodenr='0'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['kode']=='1') {
			$box[15]=$r['box1'];
			$box[16]=$r['box2'];
			$box[17]=$r['box3'];
			$box[18]=$r['box4'];
			$box[19]=$r['box5'];
			$box[20]=$r['box6'];
			$box[21]=$r['box7'];
			$box[22]=$r['box8'];
			$box[23]=$r['box9'];
			$box[24]=$r['box10'];
			$box[25]=$r['box11'];
			$box[26]=$r['box12'];
			$box[27]=$r['box13'];
			$box[28]=$r['box14'];
		} else {
			$box[1]=$r['box1'];
			$box[2]=$r['box2'];
			$box[3]=$r['box3'];
			$box[4]=$r['box4'];
			$box[5]=$r['box5'];
			$box[6]=$r['box6'];
			$box[7]=$r['box7'];
			$box[8]=$r['box8'];
			$box[9]=$r['box9'];
			$box[10]=$r['box10'];
			$box[11]=$r['box11'];
			$box[12]=$r['box12'];
			$box[13]=$r['box13'];
			$box[14]=$r['box14'];
		}
	}

	for ($x=1;$x<=28;$x++) {
		$tekstnr=616+$x;
		$feltnavn[$x]=findtekst($tekstnr,$sprog_id);
		if ($feltnavn[$x]=="-") $feltnavn[$x]=NULL;
		if ($feltnavn[$x]) {
			$tmp=NULL;
			$feltvalg[$x]=array();
			list($felttype[$x],$tmp)=explode("|",$box[$x],2);
			($tmp)?$feltvalg[$x]=explode("|",$tmp):$feltvalg[$x]=NULL;
		}
	}
	if ($id && $q=db_select("select * from grupper where art='ANSAT' and kodenr='$id'",__FILE__ . " linje " . __LINE__)){
		while ($r = db_fetch_array($q)) {
			if ($r['kode']=='1') {
				$extra_id_1=$r['id'];
				for($x=1;$x<=14;$x++) {
						$y=$x+14;
					$tmp="box".$x;
					$box[$y]=if_isset($r[$tmp]);
				}
			} else {
				$extra_id_0=$r['id'];
				for($x=1;$x<=14;$x++) {
					$tmp="box".$x;
					$box[$x]=if_isset($r[$tmp]);
				}
			}
		}
	}
}
?>
