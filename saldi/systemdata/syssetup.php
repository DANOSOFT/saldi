<?php
// ----------------- systemdata/syssetup.php ------- lap 3.5.6 -- 2015-04-24 --
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
// // Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20132127 Indsat kontrol for at kodenr er numerisk på momskoder.
// 20140621 Ændret "kontrol for at kodenr er numerisk på momskoder" til at acceptere "-".
// 20141002 Tilføjet felt for omvendt betaling på kunder og varer.
// 20141212 CA  Tekstbokse i CSS som erstatning for JavaScript Alert-bokse. Søg 20141212A
// 20141212 CA  Variablen spantilte ændret til spantitle. Søg 20141212B
// 20150130 CA  Test af Lager Tilgang og Lager Træk ved den anden angivet i stedet for ved Lagerført. Søg 20150130
// 20150424 CA  Omdøbt funktionen udskriv til skriv_formtabel og flyttet den til filen skriv_formtabel.inc.php
// 20160118 PHR $kode blev ikke sat 20160118 

@session_start();
$s_id=session_id();

$nopdat=NULL;	

$modulnr=1;
$title="Systemsetup";
$css="../css/standard.css";
$dd=date("Y-m-d");
$genberegn=NULL;


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("skriv_formtabel.inc.php");
include("../includes/genberegn.php");

if ($menu=='T') {
#	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
#	print "<span class=\"headerTxt\">Systemsetup</span>\n";     
#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green small right\">Ny debitor</a>--></div>";       
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontent\">\n";
	print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>";
} else {
	include("top.php");
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
}
$valg=if_isset($_GET['valg']);

if ($_POST){ 
	$id=if_isset($_POST['id']);
	$beskrivelse=if_isset($_POST['beskrivelse']);
	$kodenr=if_isset($_POST['kodenr']);
	$kode=if_isset($_POST['kode']);
	$art=if_isset($_POST['art']);
	$box1=if_isset($_POST['box1']);
	$box2=if_isset($_POST['box2']);
	$box3=if_isset($_POST['box3']);
	$box4=if_isset($_POST['box4']);
	$box5=if_isset($_POST['box5']);
	$box6=if_isset($_POST['box6']);
	$box7=if_isset($_POST['box7']);
	$box8=if_isset($_POST['box8']);
	$box9=if_isset($_POST['box9']);
	$box10=if_isset($_POST['box10']);
	$box11=if_isset($_POST['box11']);
	$box12=if_isset($_POST['box12']);
	$box13=if_isset($_POST['box13']);
	$box14=if_isset($_POST['box14']);
	$antal=if_isset($_POST['antal']);
#cho "Antal $antal<br>";	
	$valg=if_isset($_POST['valg']);

	$s_art=array();
	$artantal=0;
	transaktion('begin');
	$y=0;
	for($x=0; $x<=$antal; $x++) {
		$set=0;
		if (isset($art[$x])) $set=1;
		if (isset($beskrivelse[$x])) $set=1;
		if (isset($kodenr[$x])) $set=1;
		if (isset($id[$x])) $set=1;
		if ($set) {
			$id[$y]=$id[$x]*1;
			$kodenr[$y]=$kodenr[$x]*1;
			$kode[$y]=$kode[$x]; #20160118
			$beskrivelse[$y]=db_escape_string(trim($beskrivelse[$x]));
			$art[$y]=trim($art[$x]);
			$box1[$y]=trim($box1[$x]);
			$box2[$y]=trim($box2[$x]);
			$box3[$y]=trim($box3[$x]);
			$box4[$y]=trim($box4[$x]);
			$box5[$y]=trim($box5[$x]);
			$box6[$y]=trim($box6[$x]);
			$box7[$y]=trim($box7[$x]);
			$box8[$y]=trim($box8[$x]);
			$box9[$y]=trim($box9[$x]);
			$box10[$y]=trim($box10[$x]);
			$box11[$y]=trim($box11[$x]);
			$box12[$y]=trim($box12[$x]);
			$box13[$y]=trim($box13[$x]);
			$box14[$y]=trim($box14[$x]);
			$y++;
		}
	}	
	
#	array_splice($kodenr,$y);
	transaktion('begin');
	for($x=0; $x<$y; $x++) {
	########## Til brug for sortering ########
		 if (($art[$x])&&(!in_array($art[$x],$s_art))) {
			$artantal++;
			$s_art[$artantal]=$art[$x];
			$s_kode[$artantal]=$kode[$x];
		}
#cho "KN $id[$x] $art[$x] $kodenr[$x] $beskrivelse[$x]<br>";
		
		################################
/*
		$beskrivelse[$x]=db_escape_string(trim($beskrivelse[$x]));
		$kodenr[$x]=trim($kodenr[$x]);
		$box1[$x]=trim($box1[$x]);
		$box2[$x]=trim($box2[$x]);
		$box3[$x]=trim($box3[$x]);
		$box4[$x]=trim($box4[$x]);
		$box5[$x]=trim($box5[$x]);
		$box6[$x]=trim($box6[$x]);
		$box7[$x]=trim($box7[$x]);
		$box8[$x]=trim($box8[$x]);
		$box9[$x]=trim($box9[$x]);
		$box10[$x]=trim($box10[$x]);
		$box11[$x]=trim($box11[$x]);
		$box12[$x]=trim($box12[$x]);
		$box13[$x]=trim($box13[$x]);
		$box14[$x]=trim($box14[$x]);
*/
		if (($art[$x]=='VG')&&($box8[$x]!='on')&&($box9[$x]=='on')) {
			$alerttext="Der kan kun f&oslash;res batchkontrol p&aring; lagerf&oslash;rte varer";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
			$box9[$x]='';
		}
		if ($art[$x]=='DG' || $art[$x]=='KG'){
#cho "art $art[$x]<br>";
			if (!$box3[$x]) $box3[$x]='DKK';
#cho "SELECT box2 FROM grupper where id='$id[$x]'<br>";
#cho __line__." ID: $id[$x]<br>";
			$r=db_fetch_array(db_select("SELECT box2 FROM grupper where id='$id[$x]'",__FILE__ . " linje " . __LINE__));
			if($box2[$x] && $r['box2'] && $box2[$x]!=$r['box2']) {
				list($regnstart,$regnslut)=explode(chr(9),regnstartslut($regnaar));
				if ($regnstart>$dd || $regnslut<$dd) {
					print tekstboks('Forkert regnskabsår aktivt');
					break 1;
				}
				$genberegn=1;
				$gl_smlkto=$r['box2'] ;
#cho "GL samlekonto $gl_smlkto Ny $box2[$x]<br>";
#xit;
				$z=0;
				$gruppesum=0;
				$qtxt="select id,kontonr from adresser where art = '".substr($art[$x],0,1)."' and gruppe='$kodenr[$x]'";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)){
					$adr_konto_id[$z]=$r['id'];
					$adr_kontonr[$z]=$r['kontonr'];
					$z++;
				}
				for ($z=0;$z<count($adr_konto_id);$z++){
					#cho "$z select amount,valutakurs from openpost where udlignet='0' and konto_id='$adr_konto_id[$z]'<br>";
					$qtxt="select amount,valutakurs from openpost where udlignet='0' and konto_id='$adr_konto_id[$z]'<br>";
					$q=db_select("select amount,valutakurs from openpost where udlignet='0' and konto_id='$adr_konto_id[$z]'",__FILE__ . " linje " . __LINE__);
					while($r=db_fetch_array($q)){
						$gruppesum+=$r['amount']*100/$r['valutakurs'];		
					}
				}
				$gruppesum=afrund($gruppesum,3);
				if ($gruppesum>0) $debkred='kredit';
				elseif($gruppesum<0)  $debkred='debet';
				$gruppesum=abs($gruppesum);
				if ($gruppesum){
					$posttekst="samlekonto D$kodenr[$x] flyttet fra konto $gl_smlkto til $box2[$x] af $brugernavn";
					$qtxt="insert into transaktioner"; 
					$qtxt.="(kontonr,bilag,transdate,logdate,logtime,beskrivelse,$debkred,faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)";
					$qtxt.="values";
					$qtxt.="('$gl_smlkto','0','".date("Y-m-d")."','".date("Y-m-d")."','".date("H:i")."','$posttekst','$gruppesum','0','0','0','0','','DKK','100','0','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					($debkred=='debet')?$debkred='kredit':$debkred='debet';
					$qtxt="insert into transaktioner";
					$qtxt.="(kontonr,bilag,transdate,logdate,logtime,beskrivelse,$debkred,faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)";
					$qtxt.="values";
					$qtxt.="('$box2[$x]','0','".date("Y-m-d")."','".date("Y-m-d")."','".date("H:i")."','$posttekst','$gruppesum','0','0','0','0','','DKK','100','0','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="select valuta from kontoplan where regnskabsaar = '$regnaar' and kontonr='$box2[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($valutakode=$r['valuta']) {
					$qtxt="select box1 from grupper where art= 'VK' and kodenr='$valutakode'";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$valuta=$r['box1'];
					if (!$valuta) {
						$alerttext="Valuta $valuta eksisterer ikke";
						print tekstboks($alerttext);
						break 1;

					}
				}	else $valuta='DKK';
				$box3[$x]=$valuta;
				genberegn($regnaar);
			}
			if ($art[$x]=='DG'&& $box6[$x]) $box6[$x]=usdecimal($box6[$x]);
			if ($art[$x]=='DG'&& $box6[$x]) $box7[$x]=usdecimal($box7[$x]);
		}
		if ($art[$x]=='VG' && $box8[$x]=='on' && $box10[$x]=='on') {
			$alerttext="Operationer kan ikke lagerf&oslash;res";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
			$box8[$x]=''; $box9[$x]='';
		} 
		if ($art[$x]=='VPG') {
			list($box1[$x],$box2[$x],$box3[$x],$box4[$x])=explode(";",opdater_varer($kodenr[$x],$art[$x],$box1[$x],$box2[$x],$box3[$x],$box4[$x])); 
		} 
		if ($art[$x]=='VTG') {
			list($box1[$x],$box2[$x],$box3[$x],$box4[$x])=explode(";",opdater_varer($kodenr[$x],$art[$x],$box1[$x],$box2[$x],$box3[$x],$box4[$x])); 
		} 
		if ($art[$x]=='VRG') opdater_varer($kodenr[$x],$art[$x],$box1[$x],$box2[$x],$box3[$x],$box4[$x]); 
		if (($art[$x]=='SM')||($art[$x]=='KM')||($art[$x]=='YM')||($art[$x]=='EM')||($art[$x]=='VK')) $box2[$x]=usdecimal($box2[$x]); 
		if ($art[$x]=='VK' ) $box3[$x]=usdate($box3[$x]);
#		if ($art[$x]=='PRJ' ) $kodenr[$x]=$kodenr[$x]*1;
		if (!$fejl && ($kode[$x])||($id[$x])) {
			$fejl=tjek ($id [$x],$beskrivelse[$x],$kodenr[$x],$kode[$x],$art[$x],$box1[$x],$box2[$x],$box3[$x],$box4[$x],$box5[$x],$box6[$x],$box7[$x],$box8[$x],$box9[$x]);
			if (!$fejl && ($id[$x]==0)&&($kode[$x])&&($kodenr[$x])&&($art[$x])) {
				$query = db_select("SELECT id FROM grupper WHERE kodenr = '$kodenr[$x]' and kode = '$kode[$x]' and art = '$art[$x]'",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) {
					if ($art[$x]=='SM'){print "<big><b>Der findes allerede en salgsmomskonto med nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
					if ($art[$x]=='KM'){print "<big><b>Der findes allerede en k&oslash;bssmomskonto med nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
					if ($art[$x]=='YM'){print "<big><b>Der findes allerede en konto til moms af ydelsesk&oslash;b i udlandet med nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
					if ($art[$x]=='EM'){print "<big><b>Der findes allerede en konto til moms af varek&oslash; i udlandet med nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
					if ($art[$x]=='SD'){print "<big><b>Der findes allerede en debitor-samlekonto nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
					if ($art[$x]=='KD'){print "<big><b>Der findes allerede en kreditor-samlekonto nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
				}
				elseif ($art[$x]=='RA'){nytaar($beskrivelse[$x],$kodenr[$x],$kode[$x],$art[$x],$box1[$x],$box2[$x],$box3[$x],$box4[$x],$box5[$x],$box6[$x]);}
				elseif ($art[$x]!='PV') {
					db_modify("insert into grupper (beskrivelse,kodenr,kode,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14) values ('$beskrivelse[$x]','$kodenr[$x]','$kode[$x]','$art[$x]','$box1[$x]','$box2[$x]','$box3[$x]','$box4[$x]','$box5[$x]','$box6[$x]','$box7[$x]','$box8[$x]','$box9[$x]','$box10[$x]','$box11[$x]','$box12[$x]','$box13[$x]','$box14[$x]')",__FILE__ . " linje " . __LINE__);
					if ($art[$x]=='LG'){
						if (!db_fetch_array(db_select("SELECT * FROM lagerstatus",__FILE__ . " linje " . __LINE__))) {
							$q1=db_select("SELECT id,beholdning FROM varer WHERE beholdning !='0' order by id",__FILE__ . " linje " . __LINE__);
							while ($r1=db_fetch_array($q1)) {
								db_modify("insert into lagerstatus (beholdning,vare_id,lager) values ('$r1[beholdning]','$r1[id]','0')",__FILE__ . " linje " . __LINE__); 
							}
						}
					}
				}
			}	
			elseif ((($id[$x]>0)&&($kodenr[$x])&&($kodenr[$x]!='-'))&&($art[$x])){ # &&(($box1[$x])||($box3[$x])||($art[$x]=='VK')))
			  if ($art[$x]=='PV') {db_modify("update grupper set box1 = '$box1[$x]',box2 = '$box2[$x]',box3 = '$box3[$x]' WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);}
				else {
					db_modify("update grupper set beskrivelse = '$beskrivelse[$x]',kode = '$kode[$x]',box1 = '$box1[$x]',box2 = '$box2[$x]',box3 = '$box3[$x]',box4 = '$box4[$x]',box5 = '$box5[$x]',box6 = '$box6[$x]',box7 = '$box7[$x]',box8 = '$box8[$x]',box9 = '$box9[$x]',box10 = '$box10[$x]',box11 = '$box11[$x]',box12 = '$box12[$x]',box13 = '$box13[$x]',box14 = '$box14[$x]' WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
				}
				if ($art[$x]=='VK') { #ValutaKoder
				if ($r=db_fetch_array(db_select("select id,kurs from valuta where valdate = '$box3[$x]' and gruppe =	'$kodenr[$x]'",__FILE__ . " linje " . __LINE__))) {
						if ($r['kurs'] != $box2[$x]) db_modify("update valuta set kurs = '$box2[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
					} else db_modify("insert into valuta(gruppe,valdate,kurs) values ('$kodenr[$x]','$box3[$x]','$box2[$x]')",__FILE__ . " linje " . __LINE__); 
				} 			
			} elseif (($id[$x]>0)&&($kodenr[$x]=="-")&&($art[$x]!='PV')) {
			if ($art[$x]=='VPG') {
					if ($box1[$x]) db_modify("update varer set kostpris = $box1[$x] WHERE prisgruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
					if ($box2[$x]) db_modify("update varer set salgspris = $box2[$x] WHERE prisgruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
					if ($box3[$x]) db_modify("update varer set retail_price = $box3[$x] WHERE prisgruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
					if ($box4[$x]) db_modify("update varer set tier_price = $box4[$x] WHERE prisgruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
				}
				if ($art[$x]=='LG') { #LagerGrupper
					$r1=db_fetch_array(db_select("SELECT kodenr FROM grupper WHERE id=$id[$x]",__FILE__ . " linje " . __LINE__));
					$q2=db_select("SELECT beholdning,vare_id FROM lagerstatus WHERE lager =  '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
						if ($r3=db_fetch_array(db_select("SELECT * FROM lagerstatus WHERE lager = '0' and vare_id = '$r2[vare_id]'",__FILE__ . " linje " . __LINE__))) {
							db_modify("update lagerstatus set beholdning = $r3[beholdning]+$r2[beholdning] WHERE id = $r3[id]",__FILE__ . " linje " . __LINE__);
						} else {
						db_modify("insert into lagerstatus (beholdning,vare_id,lager) values ('$r2[beholdning]','$r2[vare_id]','0')",__FILE__ . " linje " . __LINE__); 
						}
					}
					db_modify("delete FROM lagerstatus WHERE lager = '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					db_modify("update batch_kob set lager = 0 WHERE lager =  '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					db_modify("delete FROM grupper WHERE id = '$id[$x]'");
					$q1=db_select("SELECT kodenr FROM grupper WHERE art='LG' and kodenr > '$r1[kodenr]' order by kodenr",__FILE__ . " linje " . __LINE__);
					while ($r1=db_fetch_array($q1)) {
						db_modify("update lagerstatus set lager = $r1[kodenr]-1 WHERE lager = '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
						db_modify("update batch_kob set lager = $r1[kodenr]-1 WHERE lager =  '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					}	
					if (!db_fetch_array(db_select("SELECT kodenr FROM grupper WHERE art='LG'"))) db_modify("delete FROM lagerstatus",__FILE__ . " linje " . __LINE__);	
				} elseif ($art[$x]=='SM'||$art[$x]=='KM'||$art[$x]=='YM'||$art[$x]=='EM') {
					$r1=db_fetch_array(db_select("SELECT kodenr FROM grupper WHERE id=$id[$x]",__FILE__ . " linje " . __LINE__));
					$tmp=substr($art[$x],0,1).$r1['kodenr'];
					if ($r1=db_fetch_array(db_select("SELECT id FROM kontoplan WHERE moms='$tmp'",__FILE__ . " linje " . __LINE__))) print "<BODY onLoad=\"javascript:alert('Der er referencer til $tmp i kontoplanen. $tmp ikke slettet!')\">";
					elseif ($r1=db_fetch_array(db_select("SELECT id FROM grupper WHERE (art='DG' or art = 'KG') and box1='$tmp'",__FILE__ . " linje " . __LINE__))) print "<BODY onLoad=\"javascript:alert('Der er reference til $tmp i debitor-/kreditorgrupper. $tmp ikke slettet!')\">";
					else db_modify("delete FROM grupper WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
				} elseif ($art[$x]=='VK') db_modify("delete FROM valuta WHERE gruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
				else {
					$r1=db_fetch_array(db_select("SELECT kodenr FROM grupper WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__));
					if ($art[$x]=='VG' && db_fetch_array(db_select("SELECT id FROM varer WHERE gruppe = '$r1[kodenr]'",__FILE__ . " linje " . __LINE__))) {
							print "<BODY onLoad=\"javascript:alert('Der er varer i varegruppe $r1[kodenr] - varegruppe ikke slettet!')\">";
					} else db_modify("delete FROM grupper WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	transaktion('commit');
	if ($genberegn) genberegn($regnaar);
}

#########################################################################################################################################
if ($nopdat!=1) {
	$x=0;
	if ($valg=="projekter") $tmp='kodenr desc';
	else {
		if ($db_type=='mysql') $tmp="CAST(kodenr AS SIGNED)";
		else $tmp="to_number(textcat('0',kodenr),text(99999999))";
	} 
	$query = db_select("SELECT * FROM grupper order by $tmp",__FILE__ . " linje " . __LINE__);
	$feltbredde=6;
	while ($row = db_fetch_array($query)){
		$x++;
		$id[$x]=$row['id'];
		$beskrivelse[$x]=htmlentities(stripslashes($row['beskrivelse']),ENT_COMPAT,$charset);
		$kodenr[$x]=$row['kodenr'];
		if (strlen($kodenr[$x]) > $feltbredde) $feltbredde=strlen($kodenr[$x]); 
		$kode[$x]=$row['kode'];
		$art[$x]=$row['art'];
		$box1[$x]=$row['box1'];
		$box2[$x]=$row['box2'];
		$box3[$x]=$row['box3'];
		$box4[$x]=$row['box4'];
		$box5[$x]=$row['box5'];
		$box6[$x]=$row['box6'];
		$box7[$x]=$row['box7'];
		$box8[$x]=$row['box8'];
		$box9[$x]=$row['box9'];
		$box10[$x]=$row['box10'];
		$box11[$x]=$row['box11'];
		$box12[$x]=$row['box12'];
		$box13[$x]=$row['box13'];
		$box14[$x]=$row['box14'];
	}
}
if (!$valg) $valg='moms';
$y=$x+1;
print "<tr><td valign = top><table border=0><tbody>";
print "<form name=syssetup action=syssetup.php method=post>";
if ($valg=='moms'){
	$spantekst1='En beskrivende tekst efter eget valg';
	$spantekst2='Det nummer i kontoplanen som salgsmomsen skal konteres p&aring;.';
	$spantekst3='Moms %.';
	print "<tr><td></td><td colspan=3><b><span title='Den moms du skal betale til SKAT'>Salgsmoms (udg&aring;ende moms)</span></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=\"center\"><span title='$spantekst1'>Beskrivelse</span></td><td align=\"center\"><span title='$spantekst2'>Konto<span></td><td align=\"center\"><span title='$spantekst3'>Sats</span></td></tr>\n";		
	$y=skriv_formtabel('SM',$x,$y,$art,$id,'S',$kodenr,$beskrivelse,$box1,'6' ,$box2,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
	print "<tr><td><br></td></tr>\n";
	$spantekst2='Det nummer i kontoplanen som k&oslash;bsmomsen skal konteres p&aring;.';
	print "<tr><td></td><td colspan=3><b><span title='Den moms du skal have retur fra SKAT'>K&oslash;bsmoms (indg&aring;ende moms)</span></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=\"center\"><span title='$spantekst1'>Beskrivelse</span></td><td align=\"center\"><span title='$spantekst2'>Konto<span></td><td align=\"center\"><span title='$spantekst3'>Sats</span></td></tr>\n";
	$y=skriv_formtabel('KM',$x,$y,$art,$id,"K",$kodenr,$beskrivelse,$box1,'6',$box2,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
	print "<tr><td><br></td></tr>\n";
	$spantekst2='Konto til postering af salgsmoms for ydelsesk&oslash;b i udlandet';
	$spantekst4='Konto til postering af k&oslash;bsmoms for ydelsesk&oslash;b i udlandet';
	$spantekst5="Ved ydelsesk&oslash;b i udlandet,skal der betales dansk moms p&aring; vegne af s&aelig;lgeren. \nSamtidig kan k&oslash;bsmomsen tr&aelig;kkes fra s&aring; resultatet bliver 0.";
	print "<tr><td></td><td colspan=3><b><span title='$spantekst5'>Moms af ydelsesk&oslash;b i udlandet</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=\"center\"><span title='$spantekst1'>Beskrivelse</span></td><td align=\"center\"><span title='$spantekst2'>Konto<span></td><td align=\"center\"><span title='$spantekst3'>Sats</span></td><td align=\"center\"> <span title='$spantekst4'>Modkonto</span></td></tr>\n";
	$y=skriv_formtabel('YM',$x,$y,$art,$id,"Y",$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
	print "<tr><td><br></td></tr>\n";
	$spantekst2='Konto til postering af salgsmoms for k&oslash;b i udlandet';
	$spantekst4='Konto til postering af k&oslash;bsmoms for k&oslash;b i udlandet';
	$spantekst5="Ved varek&oslash;b i udlandet,skal der betales dansk moms p&aring; vegne af s&aelig;lgeren. \nSamtidig kan k&oslash;bsmomsen tr&aelig;kkes fra s&aring; resultatet bliver 0";
	print "<tr><td></td><td colspan=3><b><span title='$spantekst5'>Moms af varek&oslash;b i udlandet</span></b></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=\"center\"><span title='$spantekst1'>Beskrivelse</span></td><td align=\"center\"><span title='$spantekst2'>Konto<span></td><td align=\"center\"><span title='$spantekst3'>Sats</span></td><td align=\"center\"> <span title='$spantekst4'>Modkonto</span></td></tr>\n";
	$y=skriv_formtabel('EM',$x,$y,$art,$id,"E",$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
	print "<tr><td><br></td></tr>\n";
	print "<tr><td></td><td colspan=3><b>Momsrapport (konti som skal indg&aring; i momsrapport)</b></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=\"center\"><span title='$spantekst1'>Beskrivelse</span></td><td align=\"center\"><span title='F&oslash;rste kontonummer som skal indg&aring; i rapporten'>Fra</span></td><td align=\"center\"><span title='Sidste kontonummer som skal indg&aring; i rapporten'>Til</span></td><td><span title='Kontonummer for samlet varek&oslash;b i EU'>Rubrik A1</span></td><td><span title='Kontonummer for samlet ydelsesk&oslash;b i EU'>Rubrik A2</span></td><td><span title='Kontonummer for samlet varesalg i EU'>Rubrik B1</span></td><td><span title='Kontonummer for samlet ydelsessalg i EU'>Rubrik B2</span></td><td><span title='Kontonummer for samlet vare- og ydelsessalg uden for EU'>Rubrik C</span></td></tr>\n";
	$y=skriv_formtabel('MR',$x,$y,$art,$id,"R",$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'6',$box4,'6',$box5,'6',$box6,'6',$box7,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
}
elseif($valg=='debitor'){
	print "<tr><td>";
	print infoboks('<span style=\'font-size:80%; font-weigth:bold; padding:0px 2px 0px 2px; font-family:monospace; background: #0000ff; color: #ffffff\'>i</span>', '<h2>Debitorhjælp</h2><p>Her er lidt tekst omkring brugen af debitorgrupper.</p>', 'info', 'infoboks1');
	print "</td><td colspan=2><b>Debitorgrupper</td><td></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=\"center\">Beskrivelse</td><td align=\"center\"><span title='Momsgruppe som debitorgruppen skal tilknyttes'>Momsgrp</span></td><td align=\"center\"><span title='Samlekonto for debitorgruppen'>Samlekt.</span></td><td align=\"center\">Valuta</td>";
	print "<td align=\"center\"><span title=\"Det sprog der skal anvendes ved fakturering\">Sprog</td>";
	print "<td align=\"center\"><span title=\"Modkonto ved udligning af &aring;bne poster\">Modkonto</td>";
#	$spantitle="RABAT!\nHer angives rabatsatsen i procent for kundegruppen."; # 20141212B spantilte -> spantitle (start)
#	print "<td align=\"center\"><span title=\"".$spantitle."\">Rabat</td>";
	$spantitle="Provisionsprocent!\nHer angives hvor stor en procentdel af d&aelig;kningsbidraget det medg&aring;r ved beregning af provision.";
	print "<td align=\"center\"><span title=\"".$spantitle."\">Provision</td>\n";
	$spantitle="Business to business!\nAfm&aelig;rk her,hvis der skal anvendes b2b priser ved salg til denne kundegruppe";
	print "<td align=\"center\"><span title=\"".$spantitle."\">B2B</td>\n";
	$spantitle="Omvendt betaligspligt!\nAfm&aelig;rk her,hvis denne kundegruppe er omfattet af omvendt betalingspligt";
 	print "<td align=\"center\"><span title=\"".$spantitle."\">OB</td></tr>\n"; # 20141212B spantilte -> spantitle (slut)
#cho "$id[$x] $beskrivelse[$x]<br>";
	$y=skriv_formtabel('DG',$x,$y,$art,$id,'D',$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'10',$box4,'10',$box5,'6','-','4',$box7,'4',$box8,'checkbox',$box9,'checkbox','-','2','-','2','-','2','-','2','-','2');
	print "<tr><td><br></td></tr>\n";
	print "<tr><td></td><td colspan=2><b>Kreditorgrupper</td><td></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=\"center\">Beskrivelse</td><td align=\"center\"><span title='Momsgruppe som debitorgruppen skal tilknyttes'>Momsgrp</span></td>";
	print "<td align=\"center\"><span title='Samlekonto for debitorgruppen'>Samlekt.</span></td><td align=\"center\">Valuta</td>";
	print "<td align=\"center\"><span title=\"Det sprog der skal anvendes ved kommunikation med kreditoren\">Sprog</span></td>";
	print "<td align=\"center\"><span title=\"Modkonto ved udligning af &aring;bne poster\">Modkonto</span></td>";
	print "<td align=\"center\"><span title=\"Momsgruppe for salgsmoms ved omvendt betalingspligt\">S.moms grp.</span></td>";
	print "<td align=\"center\" title=\"Omvendt betaligspligt!\nAfm&aelig;rk her,hvis denne leverandørgruppe er omfattet af omvendt betalingspligt\">O/B<!-- box9 --></td></tr>\n";
#	print "<td align=\"center\"><span title=\"Omvendt betaligspligt!\"Afm&aelig;rk her,hvis denne leverandørgruppe er omfattet af omvendt betalingspligt>O/B</span></td></tr>\n";
	$y=skriv_formtabel('KG',$x,$y,$art,$id,'K',$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'10',$box4,'10',$box5,'10',$box6,'6','-','6','-','6',$box9,'checkbox','-','6','-','6','-','6','-','6','-','2');
}
elseif($valg=='afdelinger'){
	print "<tr><td></td><td colspan=3 align=\"center\"><b>Afdelinger</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td>Beskrivelse</td></tr>\n";
	$y=skriv_formtabel('AFD',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,'-','2',"-",'2',"-",'2','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
}
elseif($valg=='projekter'){
	print "<tr><td></td><td colspan=3 align=\"center\"><b>Projekter</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td>Beskrivelse</td></tr>\n";
	$y=skriv_formtabel('PRJ',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,'-','2',"-",'2',"-",'2','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
}
elseif($valg=='lagre'){
	print "<tr><td></td><td colspan=3 align=\"center\"><b>Lagre</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td>Beskrivelse</td><td align=\"center\">Afd.</td></tr>\n";
	$y=skriv_formtabel('LG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'2',"-",'2',"-",'2','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
}
elseif($valg=='varer'){
	$t6="Hvis varegruppen er omfattet af omvendt betalingspligt afmærkes dette felt";
	$q = db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box4='on'",__FILE__ . " linje " . __LINE__);
	if (db_fetch_array($q)){
		print "<tr><td></td><td colspan=10 align=\"center\"><b>Varegrupper</td></tr><tr><td colspan=13><hr></td></tr>\n";
		print "<tr><td align=\"center\"></td><td></td><td></td><td align=\"center\">Lager-</td><td align=\"center\">Lager-</td><td align=\"center\">K&oslash;b</td><td align=\"center\">Salg</td><td align=\"center\">Lager-</td>
			<td title=\"$t6\" align=\"center\">Omvendt-</td><td align=\"center\">Moms-</td><td align=\"center\">Lager-</td><td align=\"center\">Opera-</td>\n";
		print "<td title='Kontonummer for enten k&oslash; af Varek&oslash;b i EU (Rubrik A1) eller Ydelsesk&oslash;b i EU (Rubrik A2) - se Indstillinger - Moms'>K&oslash;b</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>Salg</td>\n";
		print "<td title='Kontonummer for en af Varek&oslash;b uden for EU, Ydelsesk&oslash;b uden for EU eller Vare- og ydelsesk&oslash;b uden for EU.'>K&oslash;b uden</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to f&oslash;rste angives, s&aring; skal kontonummeret v&aelig;re blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>Salg uden</td></tr>\n";
		print "<tr><td></td><td>Nr.</td><td align=\"center\">Beskrivelse</td><td align=\"center\">tilgang</td><td align=\"center\">tr&aelig;k</td><td align=\"center\">k&oslash;b</td><td align=\"center\">salg</td><td align=\"center\">regulering</td>
			<td  title=\"$t6\" align=\"center\">betaling</td><td align=\"center\">fri</td><td align=\"center\">f&oslash;rt</td><td align=\"center\">tion</td>\n";
		print "<td title='Kontonummer for enten k&oslash; af Varek&oslash;b i EU (Rubrik A1) eller Ydelsesk&oslash;b i EU (Rubrik A2) - se Indstillinger - Moms'>i EU</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>til EU</td>\n";
		print "<td title='Kontonummer for en af Varek&oslash;b uden for EU, Ydelsesk&oslash;b uden for EU eller Vare- og ydelsesk&oslash;b uden for EU.'>for EU</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to f&oslash;rste angives, s&aring; skal kontonummeret v&aelig;re blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>for EU</td></tr>\n";
		$y=skriv_formtabel('VG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'4',$box4,'4',$box5,'4',$box6,'checkbox',$box7,'checkbox',$box8,'checkbox',$box10,'checkbox','-','2',$box11,'4',$box12,'4',$box13,'4',$box14,'4');
	} else {
		print "<tr><td colspan=13 align=\"center\"><b>Varegrupper</td></tr><tr><td colspan=13><hr></td></tr>\n";
		print "<tr><td  title=\"$t6\" align=\"center\"></td><td></td><td></td><td align=\"center\">Lager-</td><td align=\"center\">Lager-</td><td align=\"center\">Vare-</td><td align=\"center\">Vare-</td><td align=\"center\">Lager-</td>
			<td align=\"center\">Omvendt-</td><td align=\"center\">Moms-</td><td align=\"center\">Lager-</td><td align=\"center\">Batch-</td><td align=\"center\">Opera-</td>\n";
		print "<td title='Kontonummer for enten k&oslash; af Varek&oslash;b i EU (Rubrik A1) eller Ydelsesk&oslash;b i EU (Rubrik A2) - se Indstillinger - Moms'>K&oslash;b</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>Salg</td>\n";
		print "<td title='Kontonummer for en af Varek&oslash; uden for EU, Ydelsesk&oslash; uden for EU eller Vare- og ydelsesk&oslash; uden for EU.'>K&oslash;b uden</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to f&oslash;rste angives, s&aring; skal kontonummeret v&aelig;re blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>Salg uden</td></tr>\n";
		print "<tr><td></td><td>Nr.</td><td align=\"center\">Beskrivelse</td><td align=\"center\">tilgang</td><td align=\"center\">tr&aelig;k</td><td align=\"center\">k&oslash;b</td><td align=\"center\">salg</td><td align=\"center\">regulering</td>
			<td  title=\"$t6\" align=\"center\">betaling</td><td align=\"center\">fri</td><td align=\"center\">f&oslash;rt</td><td align=\"center\">kontrol</td><td align=\"center\">tion</td>\n";
		print "<td title='Kontonummer for enten k&oslash; af Varek&oslash;b i EU (Rubrik A1) eller Ydelsesk&oslash;b i EU (Rubrik A2) - se Indstillinger - Moms'>i EU</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>til EU</td>\n";
		print "<td title='Kontonummer for en af Varek&oslash; uden for EU, Ydelsesk&oslash; uden for EU eller Vare- og ydelsesk&oslash; uden for EU.'>for EU</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to f&oslash;rste angives, s&aring; skal kontonummeret v&aelig;re blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>for EU</td></tr>\n";
		$y=skriv_formtabel('VG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'4',$box4,'4',$box5,'4',$box6,'checkbox',$box7,'checkbox',$box8,'checkbox',$box9,'checkbox',$box10,'checkbox',$box11,'4',$box12,'4',$box13,'4',$box14,'4');
	}
	print "<tr><td colspan=13 align=\"center\"><hr><b>Prisgrupper</td></tr><tr><td colspan=13><hr></td></tr>\n";
	print "<tr><td colspan=13><table><tbody>";
	print "<tr><td align=\"center\"></td><td></td><td></td><td align=\"center\">Kost-</td><td align=\"center\">Salgs-</td><td align=\"center\">Vejl-</td><td align=\"center\">B2B-</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=\"center\">Beskrivelse</td><td align=\"center\">pris</td><td align=\"center\">pris</td><td align=\"center\">pris</td><td align=\"center\">pris</td></tr>\n";
	$y=skriv_formtabel('VPG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'4',$box4,'4','-','6','-','2','-','0','-','0','-','0','-','0','-','0','-','0','-','0','-','0');
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=13 align=\"center\"><hr><b>Tilbudsgrupper</td></tr><tr><td colspan=13><hr></td></tr>\n";
	print "<tr><td colspan=13><table><tbody>";
	print "<tr><td align=\"center\"></td><td></td><td></td><td align=\"center\">Kost-</td><td align=\"center\">Salgs-</td><td align=\"center\">Start-</td><td align=\"center\">Slut-</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=\"center\">Beskrivelse</td><td align=\"center\">pris</td><td align=\"center\">pris</td><td align=\"center\">dato</td><td align=\"center\">dato</td></tr>\n";
	$y=skriv_formtabel('VTG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'7',$box4,'7','-','6','-','2','-','0','-','0','-','0','-','0','-','0','-','0','-','0','-','0');
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=13><table><tbody>";
	print "<tr><td colspan=13 align=\"center\"><hr><b>Rabatgrupper</td></tr><tr><td colspan=13><hr></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=\"center\">Beskrivelse</td><td align=\"center\">Type</td><td align=\"center\">Stk. rabat</td><td align=\"center\">v. antal</td></tr>\n";
	$y=skriv_formtabel('VRG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'2',$box2,'20',$box3,'20','-','2','-','4','-','2','-','4','-','2','-','7','-','7','-','0','-','0','-','0','-','0');
	print "</tbody></table></td></tr>";
}
elseif($valg=='formularer'){
	print "<tr><td></td><td colspan=5 align=\"center\"><b>Formularer</td></tr>\n";
	print "<tr><td></td><td colspan=5 align=\"center\"><a href=\"logoupload.php?upload=Yes\">Hent logo</a></td></tr>\n";
	print "<tr><td></td><td></td><td align=\"center\">Beskrivelse</td><td align=\"center\">Printkommando</td><td align=\"center\">PDF-kommando</td><td align=\"center\"></td><td align=\"center\"></td></tr>\n";
	$y=skriv_formtabel('PV',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'20',$box2,'20','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
}
print "<tr><td><br></td></tr>\n";
print "</tbody></table></td>";
print "<input type = \"hidden\" name=antal value=$y><input type = \"hidden\" name=valg value=$valg>";
print "<tr><td colspan = 3 align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td></tr>\n";
print "</form>";
print "</div>";

###########################################################################################################################
function nytaar($beskrivelse,$kodenr,$kode,$art,$box1,$box3,$box3,$box4,$box5,$box6) {
	$query = db_select("SELECT id FROM grupper WHERE art = 'RA'",__FILE__ . " linje " . __LINE__);
	print "<form name=nytaar action=syssetup.php method=post>";
	print "<tr><td colspan=4 align = center><big><b>Opret Regnskabs&aring;r: $beskrivelse</td></tr>\n";
	if (!$row = db_fetch_array($query)) {
		print "<tr><td colspan=2 align=\"center\"> Intast primotal for 1. regnskabs&aring;r:</td><td align = center>debet</td><td align = center>kredit</td></tr>\n";
		$query = db_select("SELECT id, kontonr,beskrivelse FROM kontoplan WHERE kontotype='D' or kontotype='S' order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			print "<tr><input type=hidden name=kontonr[$y] value=$row[kontonr]><td>$row[kontonr]</td><td>$row[beskrivelse]</td><td width=10 align=right><input class=\"inputbox\" type=\"text\" size=10 name=debet[$y]></td><td align=right><input class=\"inputbox\" type=\"text\" size=10 name=kredit[$y]></td></tr>\n";
		}
	} else {
		print "<tr><td> Overf&oslash;r &aring;bningsbalance</td><td><input class=\"inputbox\" type=\"checkbox\" name=aabn_bal></td></tr>\n";
	}
	print "<tr><td colspan = 4 align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td></tr>\n";
	print "</form>";
	exit;
}

###########################################################################################################################
function tjek ($id,$beskrivelse,$kodenr,$kode,$art,$box1,$box2,$box3,$box4,$box5,$box6,$box7,$box8,$box9) {
	$fejl=NULL;
	
	if ($beskrivelse)	{
		if ($art=='VG')	{
			if ($box2){ # 20150130 Test Lager Tilgang og Træk (start)
				if (!$box1) print tekstboks('"Lager Tilgang" skal udfyldes, n&aring;r "Lager Tr&aelig;k" er angivet.');
				elseif(!$fejl) $fejl=kontotjek($box1);
			}

			if ($box1){ 
				if (!$box2) print tekstboks('"Lager Tr&aelig;k" skal udfyldes n&aring;r "Lager Tilgang" er angivet.');
				elseif(!$fejl) $fejl=kontotjek($box2);
			} #20150130 Test Lager Tilgang og Træk (slut)
			if (!$box3) print tekstboks('Varek&oslash;b skal udfyldes'); # 20141212A
			elseif(!$fejl) $fejl=kontotjek($box3);
			if (!$box4) print tekstboks('Varesalg skal udfyldes'); # 20141212A
			elseif(!$fejl) $fejl=kontotjek($box4);
			if (!$fejl && $box5) $fejl=kontotjek($box5);
			if (!$fejl && $box6) $fejl=kontotjek($box6);
		}
		if ($art=='KM' || $art=='SM' || $art=='EM' || $art=='YM') { # 20132127
			if (!is_numeric($kodenr) && $kodenr!='-') { #20140621
				print tekstboks('Nr skal være numerisk! ('.$kodenr.')'); # 20141212A
				return ('1');
			}
		}
		if (!$fejl && $art=='KG' && $box9 && !$box6) {
			$fejl="S. moms grp skal udfyldes når OB (Omvendt betalingspligt) er afmærket";
			print tekstboks($fejl); # 20141212A
		}
		if (!$fejl && ($art=='DS'||$art=='KS'||$art=='KM'||$art=='SM')) $fejl=kontotjek($box1);
#cho __line__."<br>";		
		if (!$fejl && ($art=='DG'||$art=='KG')) $fejl=momsktotjek($art,$box1);
#cho __line__."<br>";		
		if (!$fejl && $art=='KG') $fejl=momsktotjek('DG',$box6);
#cho __line__."<br>";		
		if (!$fejl && ($art=='DG'||$art=='KG')) $fejl=kontotjek($box2);
#cho __line__."<br>";		
		if (!$fejl && ($art=='DG'||$art=='KG')) $fejl=kontotjek($box5);
#cho __line__."<br>";		
		if (!$fejl && ($art=='DG'||$art=='KG')) $fejl=sprogtjek($box4);
#cho __line__."<br>";		
		if (!$fejl && ($art=='LG')) $fejl=afdelingstjek($box1);
#cho __line__."<br>";		
		return $fejl;	
	}
}

###########################################################################################################################
function kontotjek ($konto) { 
	global $regnaar;
	$fejl=NULL;
	$konto=$konto*1;	
	if ($konto) {
		$qtxt="SELECT id FROM kontoplan WHERE kontonr = '$konto' and (kontotype = 'D' or kontotype = 'S') and regnskabsaar='$regnaar'";
#cho "$qtxt<br>";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$konto_id=$r['id'];
#cho "KONTO_ID $konto_id<br>";		
if (!$konto_id=$r['id']){
			print tekstboks('Kontonr: '.$konto.' kan ikke anvendes!'); # 20141212A
#			print tekstboks('Kontonr: '.$konto.' kan ikke anvendes!', 'info', 'td', 'boks2', 'tv'); # 20141121
#			print tekstboks('Kontonr: '.$konto.' kan ikke anvendes!', 'fejl', 'td', 'boks3', 'th'); # 20141121
#			print tekstboks('Kontonr: '.$konto.' kan ikke anvendes!', 'popop', 0, 'boks4', 'bv'); # 20141121
#			print tekstboks('Kontonr: '.$konto.' kan ikke anvendes!', 'info', 'td', 'boks2'); # 20141121
#			print tekstboks('Kontonr: '.$konto.' kan ikke anvendes!', 'fejl', 'td', 'boks3'); # 20141121
#		 	print tekstboks('Kontonr: '.$konto.' kan ikke anvendes!', 'popop', 0, 'boks4'); # 20141121
#			print tekstboks('Kontonr: '.$konto.' kan ikke anvendes!', 'advarsel', 0, 'boks5', 'bh'); # 20141121
			$fejl=1;
#			print "<BODY onLoad=\"javascript:alert('Kontonr: $konto kan ikke anvendes!!')\">";
		} else #cho "ID $r[id]<br>";
#cho $fejl;
	return $fejl;
	}
}
###########################################################################################################################
function sprogtjek ($sprog) { 
	$fejl=NULL;
	if ($sprog) {
		$tmp=strtolower($sprog);
		$query = db_select("SELECT id FROM formularer WHERE lower(sprog) = '$tmp'",__FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($query)) { 
			print tekstboks('Der eksisterer ikke nogen formular med '.$sprog.' som sprog!'); # 20141212A
			$fejl=1;
		}
	return $fejl;
	}
}
###########################################################################################################################
function momsktotjek ($art,$konto) {
	$fejl=NULL;
	if ($konto) {
		if ($art=='DG') {$momsart="art='SM'";}
		if ($art=='KG') {$momsart="(art='KM' or art='YM' or art='EM')";}
		$kode=substr($konto,0,1);
		$kodenr=substr($konto,1,1);
		$qtxt="SELECT id FROM grupper WHERE $momsart and kodenr = '$kodenr' and kode = '$kode'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if (!$r['id']) { 
			if ($art=='DG') print tekstboks('Salgsmomsgruppe: '.$konto.' findes ikke!');
			if ($art=='KG') print tekstboks('K&oslash;bsmomskonto: '.$konto.' findes ikke!');
			$fejl=1;
		}
		return $fejl;
	}
}
###########################################################################################################################
function afdelingstjek ($konto) {
	$fejl=NULL;
	$qtxt="SELECT id FROM grupper WHERE art='AFD' and kodenr = '$konto'";
	$r=db_fetch_array(db_select("SELECT id FROM grupper WHERE art='AFD' and kodenr = '$konto'",__FILE__ . " linje " . __LINE__));
	if (!$r['id'])	{
		tekstboks('Afdeling: '.$konto.' findes ikke!');
		$fejl=1;
	}
	return $fejl;
}
###########################################################################################################################
function opdater_varer($kodenr,$art,$box1,$box2,$box3,$box4) {
	if ($art=='VPG' && $kodenr) {
		if ($box1)$box1=usdecimal($box1);
		if ($box2)$box2=usdecimal($box2);
		if ($box3)$box3=usdecimal($box3);
		if ($box4)$box4=usdecimal($box4);
		if ($box1) db_modify("update varer set kostpris='$box1' where prisgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		if ($box2) db_modify("update varer set salgspris='$box2' where prisgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		if ($box3) db_modify("update varer set retail_price='$box3' where prisgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		if ($box4) db_modify("update varer set tier_price='$box4' where prisgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		return($box1.";".$box2.";".$box3.";".$box4);
	} 
	if ($art=='VTG' && $kodenr) {
		if ($box1)$box1=usdecimal($box1);
		if ($box2)$box2=usdecimal($box2);
		if ($box3)$box3=usdate($box3);
		if ($box4)$box4=usdate($box4);
		if ($box1) db_modify("update varer set special_price='$box1' where tilbudgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		if ($box2) db_modify("update varer set campaign_cost='$box2' where tilbudgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		if ($box3) db_modify("update varer set special_from_date='$box3' where tilbudgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		if ($box4) db_modify("update varer set special_to_date='$box4' where tilbudgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		return($box1.";".$box2.";".$box3.";".$box4);
	} 
	if ($art=='VRG' && $kodenr) {
		if ($box2)$box2=usdecimal($box2);
		if ($box3)$box3=usdecimal($box3);
		if ($box1) db_modify("update varer set m_type='$box1' where rabatgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		if ($box2) db_modify("update varer set m_rabat='$box2' where rabatgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		if ($box3) db_modify("update varer set m_antal='$box3' where rabatgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
	}
}
function titletxt($art,$felt) {
	$titletxt=NULL;
	if ($art=='VG') {
		if ($felt=='box1') $titletxt="Skriv kontonummeret for lagertilgang. Dette felt skal kun udfyldes hvis varen er lagerf&oslash;rt og lagerv&aelig;rdien skal reguleres automatisk";
		elseif ($felt=='box2') $titletxt="Skriv kontonummeret for lagerafgang. Dette felt skal kun udfyldes hvis varen er lagerf&oslash;rt og lagerv&aelig;rdien skal reguleres automatisk";
		elseif ($felt=='box3') $titletxt="Skriv kontonummeret for varek&oslash;b. Dette felt SKAL udfyldes";
		elseif ($felt=='box4') $titletxt="Skriv kontonummeret for varesalg. Dette felt SKAL udfyldes";
		elseif ($felt=='box5') $titletxt="Skriv kontonummeret for lagerregulering. Dette felt skal udfyldes hvis varen er lagerf&oslash;rt";
	}
	if ($art=='DG' || $art=='KG') {
		if ($felt=='box3') $titletxt="Valuta styres af samlekontoen";
	}
	return($titletxt);
}


?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
