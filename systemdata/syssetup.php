<?php
// -----------------systemdata/syssetup.php----lap 3.4.2---2014-06-21----
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
// // Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20132127 Indsat kontrol for at kodenr er numerisk på momskoder.
// 20140621 Ændret kontrol for at kodenr er numerisk på momskoder til at acceptere "-".

@session_start();
$s_id=session_id();

$nopdat=NULL;	

$modulnr=1;
$title="Systemsetup";
$css="../css/standard.css";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
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
}	else {
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
	$valg=if_isset($_POST['valg']);
	
	$s_art=array();
	$artantal=0;
	transaktion('begin');
	for($x=0; $x<=$antal; $x++) {
		if (!isset($art[$x])) $art[$x]=NULL;
		if (!isset($beskrivelse[$x])) $beskrivelse[$x]=NULL;
		if (!isset($kodenr[$x])) $kodenr[$x]=NULL;
		if (!isset($box1[$x])) $box1[$x]=NULL;
		if (!isset($box2[$x])) $box2[$x]=NULL;
		if (!isset($box3[$x])) $box3[$x]=NULL;
		if (!isset($box4[$x])) $box4[$x]=NULL;
		if (!isset($box5[$x])) $box5[$x]=NULL;
		if (!isset($box6[$x])) $box6[$x]=NULL;
		if (!isset($box7[$x])) $box7[$x]=NULL;
		if (!isset($box8[$x])) $box8[$x]=NULL;
		if (!isset($box9[$x])) $box9[$x]=NULL;
		if (!isset($box10[$x])) $box10[$x]=NULL;
		if (!isset($box11[$x])) $box11[$x]=NULL;
		if (!isset($box12[$x])) $box12[$x]=NULL;
		if (!isset($box13[$x])) $box13[$x]=NULL;
		if (!isset($box14[$x])) $box14[$x]=NULL;
		if (!isset($kode[$x])) $kode[$x]=NULL;
		if (!isset($id[$x])) $id[$x]=NULL;
		
		########## Til brug for sortering ########
		 if (($art[$x])&&(!in_array($art[$x],$s_art))) {
			$artantal++;
			$s_art[$artantal]=$art[$x];
			$s_kode[$artantal]=$kode[$x];
		}
		################################
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
		if (($art[$x]=='VG')&&($box8[$x]!='on')&&($box9[$x]=='on')) {
			$alerttext="Der kan kun f&oslash;res batchkontrol p&aring; lagerf&oslash;rte varer";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
			$box9[$x]='';
		}
		if ($art[$x]=='DG' || $art[$x]=='KG'){
			if (!$box3[$x]) $box3[$x]='DKK';
			if ($box3[$x]!='DKK') {
				if (!db_fetch_array(db_SELECT("SELECT id FROM grupper WHERE art= 'VK' and box1 = '$box3[$x]'",__FILE__ . " linje " . __LINE__))) {
					$alerttext="Valuta $box3[$x] eksisterer ikke";
					print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
					$box3[$x]='';
				}
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
		if (($kode[$x])||($id[$x])) {
			$fejl=tjek ($id [$x],$beskrivelse[$x],$kodenr[$x],$kode[$x],$art[$x],$box1[$x],$box2[$x],$box3[$x],$box4[$x],$box5[$x],$box6[$x],$box7[$x],$box8[$x],$box9[$x]);
			if ($fejl); #do nothing;	
			elseif (($id[$x]==0)&&($kode[$x])&&($kodenr[$x])&&($art[$x])) {
				$query = db_SELECT("SELECT id FROM grupper WHERE kodenr = '$kodenr[$x]' and kode = '$kode[$x]' and art = '$art[$x]'",__FILE__ . " linje " . __LINE__);
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
						if (!db_fetch_array(db_SELECT("SELECT * FROM lagerstatus",__FILE__ . " linje " . __LINE__))) {
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
					$r1=db_fetch_array(db_SELECT("SELECT kodenr FROM grupper WHERE id=$id[$x]",__FILE__ . " linje " . __LINE__));
					$q2=db_select("SELECT beholdning,vare_id FROM lagerstatus WHERE lager =  '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
						if ($r3=db_fetch_array(db_SELECT("SELECT * FROM lagerstatus WHERE lager = '0' and vare_id = '$r2[vare_id]'",__FILE__ . " linje " . __LINE__))) {
							db_modify("update lagerstatus set beholdning = $r3[beholdning]+$r2[beholdning] WHERE id = $r3[id]",__FILE__ . " linje " . __LINE__);
						} else {
						db_modify("insert into lagerstatus (beholdning,vare_id,lager) values ('$r2[beholdning]','$r2[vare_id]','0')",__FILE__ . " linje " . __LINE__); 
						}
					}
					db_modify("delete FROM lagerstatus WHERE lager = '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					db_modify("update batch_kob set lager = 0 WHERE lager =  '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					db_modify("delete FROM grupper WHERE id = '$id[$x]'");
					$q1=db_SELECT("SELECT kodenr FROM grupper WHERE art='LG' and kodenr > '$r1[kodenr]' order by kodenr",__FILE__ . " linje " . __LINE__);
					while ($r1=db_fetch_array($q1)) {
						db_modify("update lagerstatus set lager = $r1[kodenr]-1 WHERE lager = '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
						db_modify("update batch_kob set lager = $r1[kodenr]-1 WHERE lager =  '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					}	
					if (!db_fetch_array(db_SELECT("SELECT kodenr FROM grupper WHERE art='LG'"))) db_modify("delete FROM lagerstatus",__FILE__ . " linje " . __LINE__);	
				} elseif ($art[$x]=='SM'||$art[$x]=='KM'||$art[$x]=='YM'||$art[$x]=='EM') {
					$r1=db_fetch_array(db_SELECT("SELECT kodenr FROM grupper WHERE id=$id[$x]",__FILE__ . " linje " . __LINE__));
					$tmp=substr($art[$x],0,1).$r1['kodenr'];
					if ($r1=db_fetch_array(db_SELECT("SELECT id FROM kontoplan WHERE moms='$tmp'",__FILE__ . " linje " . __LINE__))) print "<BODY onLoad=\"javascript:alert('Der er referencer til $tmp i kontoplanen. $tmp ikke slettet!')\">";
					elseif ($r1=db_fetch_array(db_SELECT("SELECT id FROM grupper WHERE (art='DG' or art = 'KG') and box1='$tmp'",__FILE__ . " linje " . __LINE__))) print "<BODY onLoad=\"javascript:alert('Der er reference til $tmp i debitor-/kreditorgrupper. $tmp ikke slettet!')\">";
					else db_modify("delete FROM grupper WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
				} elseif ($art[$x]=='VK') db_modify("delete FROM valuta WHERE gruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
				else {
					$r1=db_fetch_array(db_SELECT("SELECT kodenr FROM grupper WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__));
					if ($art[$x]=='VG' && db_fetch_array(db_SELECT("SELECT id FROM varer WHERE gruppe = '$r1[kodenr]'",__FILE__ . " linje " . __LINE__))) {
							print "<BODY onLoad=\"javascript:alert('Der er varer i varegruppe $r1[kodenr] - varegruppe ikke slettet!')\">";
					} else db_modify("delete FROM grupper WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	transaktion('commit');
}

#########################################################################################################################################
if ($nopdat!=1){
	$x=0;
	if ($valg=="projekter") $tmp='kodenr desc';
	else {
		if ($db_type=='mysql') $tmp="CAST(kodenr AS SIGNED)";
		else $tmp="to_number(textcat('0',kodenr),text(99999999))";
	} 
	$query = db_SELECT("SELECT * FROM grupper order by $tmp",__FILE__ . " linje " . __LINE__);
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
if (!$valg) {$valg='moms';}
$y=$x+1;
print "<tr><td valign = top><table border=0><tbody>";
print "<form name=syssetup action=syssetup.php method=post>";
if ($valg=='moms'){
	$spantekst1='En beskrivende tekst efter eget valg';
	$spantekst2='Det nummer i kontoplanen som salgsmomsen skal konteres p&aring;.';
	$spantekst3='Moms %.';
	print "<tr><td></td><td colspan=3><b><span title='Den moms du skal betale til SKAT'>Salgsmoms (udg&aring;ende moms)</span></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=center><span title='$spantekst1'>Beskrivelse</span></td><td align=center><span title='$spantekst2'>Konto<span></td><td align=center><span title='$spantekst3'>Sats</span></td></tr>\n";		
	$y=udskriv('SM',$x,$y,$art,$id,'S',$kodenr,$beskrivelse,$box1,'6' ,$box2,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
	print "<tr><td><br></td></tr>\n";
	$spantekst2='Det nummer i kontoplanen som k&oslash;bsmomsen skal konteres p&aring;.';
	print "<tr><td></td><td colspan=3><b><span title='Den moms du skal have retur fra SKAT'>K&oslash;bsmoms (indg&aring;ende moms)</span></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=center><span title='$spantekst1'>Beskrivelse</span></td><td align=center><span title='$spantekst2'>Konto<span></td><td align=center><span title='$spantekst3'>Sats</span></td></tr>\n";
	$y=udskriv('KM',$x,$y,$art,$id,"K",$kodenr,$beskrivelse,$box1,'6',$box2,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
	print "<tr><td><br></td></tr>\n";
	$spantekst2='Konto til postering af salgsmoms for ydelsesk&oslash;b i udlandet';
	$spantekst4='Konto til postering af k&oslash;bsmoms for ydelsesk&oslash;b i udlandet';
	$spantekst5="Ved ydelsesk&oslash;b i udlandet,skal der betales dansk moms p&aring; vegne af s&aelig;lgeren. \nSamtidig kan k&oslash;bsmomsen tr&aelig;kkes fra s&aring; resultatet bliver 0.";
	print "<tr><td></td><td colspan=3><b><span title='$spantekst5'>Moms af ydelsesk&oslash;b i udlandet</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=center><span title='$spantekst1'>Beskrivelse</span></td><td align=center><span title='$spantekst2'>Konto<span></td><td align=center><span title='$spantekst3'>Sats</span></td><td align=center> <span title='$spantekst4'>Modkonto</span></td></tr>\n";
	$y=udskriv('YM',$x,$y,$art,$id,"Y",$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
	print "<tr><td><br></td></tr>\n";
	$spantekst2='Konto til postering af salgsmoms for k&oslash;b i udlandet';
	$spantekst4='Konto til postering af k&oslash;bsmoms for k&oslash;b i udlandet';
	$spantekst5="Ved varek&oslash;b i udlandet,skal der betales dansk moms p&aring; vegne af s&aelig;lgeren. \nSamtidig kan k&oslash;bsmomsen tr&aelig;kkes fra s&aring; resultatet bliver 0";
	print "<tr><td></td><td colspan=3><b><span title='$spantekst5'>Moms af varek&oslash;b i udlandet</span></b></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=center><span title='$spantekst1'>Beskrivelse</span></td><td align=center><span title='$spantekst2'>Konto<span></td><td align=center><span title='$spantekst3'>Sats</span></td><td align=center> <span title='$spantekst4'>Modkonto</span></td></tr>\n";
	$y=udskriv('EM',$x,$y,$art,$id,"E",$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
	print "<tr><td><br></td></tr>\n";
	print "<tr><td></td><td colspan=3><b>Momsrapport (konti som skal indg&aring; i momsrapport)</b></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=center><span title='$spantekst1'>Beskrivelse</span></td><td align=center><span title='F&oslash;rste kontonummer som skal indg&aring; i rapporten'>Fra</span></td><td align=center><span title='Sidste kontonummer som skal indg&aring; i rapporten'>Til</span></td><td><span title='Kontonummer for samlet varek&oslash;b i EU'>Rubrik A1</span></td><td><span title='Kontonummer for samlet ydelsesk&oslash;b i EU'>Rubrik A2</span></td><td><span title='Kontonummer for samlet varesalg i EU'>Rubrik B1</span></td><td><span title='Kontonummer for samlet ydelsessalg i EU'>Rubrik B2</span></td><td><span title='Kontonummer for samlet vare- og ydelsessalg uden for EU'>Rubrik C</span></td></tr>\n";
	$y=udskriv('MR',$x,$y,$art,$id,"R",$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'6',$box4,'6',$box5,'6',$box6,'6',$box7,'6','-','6','-','6','-','6','-','6','-','6','-','6');
}
elseif($valg=='debitor'){
	print "<tr><td></td><td colspan=2><b>Debitorgrupper</td><td></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=center>Beskrivelse</td><td align=center><span title='Momsgruppe som debitorgruppen skal tilknyttes'>Momsgrp</span></td><td align=center><span title='Samlekonto for debitorgruppen'>Samlekt.</span></td><td align=center>Valuta</td>";
	print "<td align=center><span title=\"Det sprog der skal anvendes ved fakturering\">Sprog</td>";
	print "<td align=center><span title=\"Modkonto ved udligning af &aring;bne poster\">Modkonto</td>";
#	$spantilte="RABAT!\nHer angives rabatsatsen i procent for kundegruppen.";
#	print "<td align=center><span title = \"$spantilte\">Rabat</td>";
	$spantilte="Provisionsprocent!\nHer angives hvor stor en procentdel af d&aelig;kningsbidraget det medg&aring;r ved beregning af provision.";
	print "<td alicn=center><span title = \"$spantilte\">Provision</td>\n";
	$spantilte="Business to business!\nAfm&aelig;rk her,hvis der skal anvendes b2b priser ved salg til denne kundegruppe";
	print "<td alicn=center><span title = \"$spantilte\">B2B</td></tr>\n";
	$y=udskriv('DG',$x,$y,$art,$id,'D',$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'10',$box4,'10',$box5,'6','-','4',$box7,'4',$box8,'checkbox','-','6','-','6','-','6','-','6','-','6');
	print "<tr><td><br></td></tr>\n";
	print "<tr><td></td><td colspan=2><b>Kreditorgrupper</td><td></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=center>Beskrivelse</td><td align=center><span title='Momsgruppe som debitorgruppen skal tilknyttes'>Momsgrp</span></td>";
	print "<td align=center><span title='Samlekonto for debitorgruppen'>Samlekt.</span></td><td align=center>Valuta</td>";
	print "<td align=center><span title=\"Det sprog der skal anvendes ved kommunikation med kreditoren\">Sprog</td>";
	print "<td align=center><span title=\"Modkonto ved udligning af &aring;bne poster\">Modkonto</td></tr>\n";
	$y=udskriv('KG',$x,$y,$art,$id,'K',$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'10',$box4,'10',$box5,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
}
elseif($valg=='afdelinger'){
	print "<tr><td></td><td colspan=3 align=center><b>Afdelinger</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td>Beskrivelse</td></tr>\n";
	$y=udskriv('AFD',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,'-','2',"-",'2',"-",'2','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
}
elseif($valg=='projekter'){
	print "<tr><td></td><td colspan=3 align=center><b>Projekter</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td>Beskrivelse</td></tr>\n";
	$y=udskriv('PRJ',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,'-','2',"-",'2',"-",'2','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
}
elseif($valg=='lagre'){
	print "<tr><td></td><td colspan=3 align=center><b>Lagre</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td>Beskrivelse</td><td align=center>Afd.</td></tr>\n";
	$y=udskriv('LG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'2',"-",'2',"-",'2','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
}
elseif($valg=='varer'){
	$q = db_SELECT("select id from grupper where art = 'DIV' and kodenr = '2' and box4='on'",__FILE__ . " linje " . __LINE__);
	if (db_fetch_array($q)){
		print "<tr><td></td><td colspan=10 align=center><b>Varegrupper</td></tr><tr><td colspan=13><hr></td></tr>\n";
		print "<tr><td align=center></td><td></td><td></td><td align=center>Lager-</td><td align=center>Lager-</td><td align=center>K&oslash;b</td><td align=center>Salg</td><td align=center>Lager-</td><td align=center>Moms-</td><td align=center>Lager-</td><td align=center>Opera-</td>\n";
		print "<td title='Kontonummer for enten k&oslash; af Varek&oslash;b i EU (Rubrik A1) eller Ydelsesk&oslash;b i EU (Rubrik A2) - se Indstillinger - Moms'>K&oslash;b</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>Salg</td>\n";
		print "<td title='Kontonummer for en af Varek&oslash;b uden for EU, Ydelsesk&oslash;b uden for EU eller Vare- og ydelsesk&oslash;b uden for EU.'>K&oslash;b uden</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to f&oslash;rste angives, s&aring; skal kontonummeret v&aelig;re blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>Salg uden</td></tr>\n";

		print "<tr><td></td><td>Nr.</td><td align=center>Beskrivelse</td><td align=center>tilgang</td><td align=center>tr&aelig;k</td><td align=center>k&oslash;b</td><td align=center>salg</td><td align=center>regulering</td><td align=center>fri</td><td align=center>f&oslash;rt</td><td align=center>tion</td>\n";
		print "<td title='Kontonummer for enten k&oslash; af Varek&oslash;b i EU (Rubrik A1) eller Ydelsesk&oslash;b i EU (Rubrik A2) - se Indstillinger - Moms'>i EU</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>til EU</td>\n";
		print "<td title='Kontonummer for en af Varek&oslash;b uden for EU, Ydelsesk&oslash;b uden for EU eller Vare- og ydelsesk&oslash;b uden for EU.'>for EU</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to f&oslash;rste angives, s&aring; skal kontonummeret v&aelig;re blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>for EU</td></tr>\n";
		$y=udskriv('VG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'4',$box4,'4',$box5,'4','-','2',$box7,'checkbox',$box8,'checkbox',$box10,'checkbox','-','2',$box11,'4',$box12,'4',$box13,'4',$box14,'4');
	} else {
		print "<tr><td colspan=13 align=center><b>Varegrupper</td></tr><tr><td colspan=13><hr></td></tr>\n";
		print "<tr><td align=center></td><td></td><td></td><td align=center>Lager-</td><td align=center>Lager-</td><td align=center>Vare-</td><td align=center>Vare-</td><td align=center>Lager-</td><td align=center>Moms-</td><td align=center>Lager-</td><td align=center>Batch-</td><td align=center>Opera-</td>\n";
		print "<td title='Kontonummer for enten k&oslash; af Varek&oslash;b i EU (Rubrik A1) eller Ydelsesk&oslash;b i EU (Rubrik A2) - se Indstillinger - Moms'>K&oslash;b</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>Salg</td>\n";
		print "<td title='Kontonummer for en af Varek&oslash; uden for EU, Ydelsesk&oslash; uden for EU eller Vare- og ydelsesk&oslash; uden for EU.'>K&oslash;b uden</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to f&oslash;rste angives, s&aring; skal kontonummeret v&aelig;re blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>Salg uden</td></tr>\n";
		print "<tr><td></td><td>Nr.</td><td align=center>Beskrivelse</td><td align=center>tilgang</td><td align=center>tr&aelig;k</td><td align=center>k&oslash;b</td><td align=center>salg</td><td align=center>regulering</td><td align=center>fri</td><td align=center>f&oslash;rt</td><td align=center>kontrol</td><td align=center>tion</td>\n";
		print "<td title='Kontonummer for enten k&oslash; af Varek&oslash;b i EU (Rubrik A1) eller Ydelsesk&oslash;b i EU (Rubrik A2) - se Indstillinger - Moms'>i EU</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>til EU</td>\n";
		print "<td title='Kontonummer for en af Varek&oslash; uden for EU, Ydelsesk&oslash; uden for EU eller Vare- og ydelsesk&oslash; uden for EU.'>for EU</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to f&oslash;rste angives, s&aring; skal kontonummeret v&aelig;re blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>for EU</td></tr>\n";
		$y=udskriv('VG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'4',$box4,'4',$box5,'4','-','2',$box7,'checkbox',$box8,'checkbox',$box9,'checkbox',$box10,'checkbox',$box11,'4',$box12,'4',$box13,'4',$box14,'4');
	}
	print "<tr><td colspan=13 align=center><hr><b>Prisgrupper</td></tr><tr><td colspan=13><hr></td></tr>\n";
	print "<tr><td colspan=13><table><tbody>";
	print "<tr><td align=center></td><td></td><td></td><td align=center>Kost-</td><td align=center>Salgs-</td><td align=center>Vejl-</td><td align=center>B2B-</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=center>Beskrivelse</td><td align=center>pris</td><td align=center>pris</td><td align=center>pris</td><td align=center>pris</td></tr>\n";
	$y=udskriv('VPG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'4',$box4,'4','-','6','-','2','-','0','-','0','-','0','-','0');
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=13 align=center><hr><b>Tilbudsgrupper</td></tr><tr><td colspan=13><hr></td></tr>\n";
	print "<tr><td colspan=13><table><tbody>";
	print "<tr><td align=center></td><td></td><td></td><td align=center>Kost-</td><td align=center>Salgs-</td><td align=center>Start-</td><td align=center>Slut-</td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=center>Beskrivelse</td><td align=center>pris</td><td align=center>pris</td><td align=center>dato</td><td align=center>dato</td></tr>\n";
	$y=udskriv('VTG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'7',$box4,'7','-','6','-','2','-','0','-','0','-','0','-','0');
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=13><table><tbody>";
	print "<tr><td colspan=13 align=center><hr><b>Rabatgrupper</td></tr><tr><td colspan=13><hr></td></tr>\n";
	print "<tr><td></td><td>Nr.</td><td align=center>Beskrivelse</td><td align=\"center\">Type</td><td align=\"center\">Stk. rabat</td><td align=\"center\">v. antal</td></tr>\n";
	$y=udskriv('VRG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'2',$box2,'20',$box3,'20','-','2','-','4','-','2','-','4','-','2','-','7','-','7');
	print "</tbody></table></td></tr>";
}
elseif($valg=='formularer'){
	print "<tr><td></td><td colspan=5 align=center><b>Formularer</td></tr>\n";
	print "<tr><td></td><td colspan=5 align=center><a href=\"logoupload.php?upload=Yes\">Hent logo</a></td></tr>\n";
	print "<tr><td></td><td></td><td align=center>Beskrivelse</td><td align=center>Printkommando</td><td align=center>PDF-kommando</td><td align=center></td><td align=center></td></tr>\n";
	$y=udskriv('PV',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'20',$box2,'20','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
}
print "<tr><td><br></td></tr>\n";
print "</tbody></table></td>";
print "<input type = \"hidden\" name=antal value=$y><input type = \"hidden\" name=valg value=$valg>";
print "<tr><td colspan = 3 align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td></tr>\n";
print "</form>";
print "</div>";

##########################################################################################################################
function udskriv($a,$x,$y,$art,$id,$k,$kodenr,$beskrivelse,$box1,$b1,$box2,$b2,$box3,$b3,$box4,$b4,$box5,$b5,$box6,$b6,$box7,$b7,$box8,$b8,$box9,$b9,$box10,$b10,$box11="-",$b11=2,$box12="-",$b12=2,$box13="-",$b13=2,$box14="-",$b14=2) {
global $valg;
global $charset;
global $feltbredde;

	for ($i=0; $i<=$x; $i++) {
		if (!isset($art[$i])) $art[$i]=NULL; 
		if ($art[$i]=='MR') $momsrapport=$i;
		if (($art[$i]=='SM' || $art[$i]=='KM' || $art[$i]=='YM' || $art[$i]=='EM' || $art[$i]=='VK') && $box2!='-') $box2[$i]=dkdecimal($box2[$i]);
		if ($art[$i]=='DG' && isset($box6[$i])) $box6[$i]=dkdecimal($box6[$i]);
		if ($art[$i]=='DG' && isset($box7[$i])) $box7[$i]=dkdecimal($box7[$i]);
		if ($art[$i]=='VK' && isset($box3[$i])) $box3[$i]=dkdato($box3[$i]);
		if ($art[$i]=='VPG') {
			if ($box1[$i]) $box1[$i]=dkdecimal($box1[$i]);
			if ($box2[$i]) $box2[$i]=dkdecimal($box2[$i]);
			if ($box3[$i]) $box3[$i]=dkdecimal($box3[$i]);
			if ($box4[$i]) $box4[$i]=dkdecimal($box4[$i]);
		}
		if ($art[$i]=='VTG') {
			if ($box1[$i]) $box1[$i]=dkdecimal($box1[$i]);
			if ($box2[$i]) $box2[$i]=dkdecimal($box2[$i]);
			if ($box3[$i]) $box3[$i]=dkdato($box3[$i]);
			if ($box4[$i]) $box4[$i]=dkdato($box4[$i]);
		}
		if ($valg=='projekter'||$art[$i]=='PRJ') $size=$feltbredde*10;
		else $size=20;
		$size.="px";
		if ($art[$i]==$a){
			print "<tr><td>";
			print "$k</td>";
			$titletxt="Dette felt kan ikke &aelig;ndres. Dog&nbsp;kan&nbsp;du&nbsp;slette&nbsp;hele&nbsp;linjen&nbsp;ved&nbsp;at&nbsp;&aelig;ndre&nbsp;indholdet&nbsp;i&nbsp;feltet&nbsp;til&nbsp;et&nbsp;-&nbsp;(minus)."; 
			print "<td><input class=\"inputbox\" title=\"$titletxt\" type=\"text\" style=\"text-align:right;width:$size\" name=\"kodenr[$i]\" value=\"$kodenr[$i]\"></td>";
			print "<td><input class=\"inputbox\" type=\"text\" size=\"40\" name=\"beskrivelse[$i]\" value=\"$beskrivelse[$i]\"></td>";
			if (($box1!="-") &&($b1!="checkbox")){
				if ($art[$i]=='VRG') {
					print "<td title=\"".titletxt($art[$i],'box1')."\"><SELECT NAME=box1[$i] style=\"width: 4em\">";
					if ($box1[$i] == 'amount') {
						print "<option value=\"amount\">kr</option>";
						print "<option value=\"percent\">%</option>";
					} else {
						print "<option value=\"percent\">%</option>";
						print "<option value=\"amount\">kr</option>";
					}
					print "</SELECT></td>";
				} else {
					print "<td title=\"".titletxt($art[$i],'box1')."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b1\" name=\"box1[$i]\" value=\"$box1[$i]\"></td>";
				}
			} elseif($b1=="checkbox") {
				if (strstr($box1[$i],'on')) {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box1[$i]\" checked></td>";}
				else {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box1[$i]\"></td>";}
			}
			print "<input type = \"hidden\" name=id[$i] value='$id[$i]'><input type = \"hidden\" name=\"art[$i]\" value=\"$art[$i]\"><input type = \"hidden\" name=\"kode[$i]\" value=\"$k\">";
			if (($box2!="-") &&($b2!="checkbox")){print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b2\" name=\"box2[$i]\" value=\"$box2[$i]\"></td>";}
			elseif($b2=="checkbox"){
				if (strstr($box2[$i],'on')){print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box2[$i]\" checked></td>";}
				else {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box2[$i]\"></td>";}
			}
			print "<input type = \"hidden\" name=\"id[$i]\" value=\"$id[$i]\"><input type = \"hidden\" name=\"art[$i]\" value=\"$art[$i]\"><input type = \"hidden\" name=\"kode[$i]\" value=\"$k\">";
			if (($box3!="-") &&($b3!="checkbox")){print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b3\" name=\"box3[$i]\" value=\"$box3[$i]\"></td>";}
			elseif($b3=="checkbox"){
				if (strstr($box3[$i],'on')){print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box3[$i]\" checked></td>";}
				else {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box3[$i]\"></td>";}
			}
			print "<input type = \"hidden\" name=id[$i] value='$id[$i]'><input type = \"hidden\" name=\"art[$i]\" value=\"$art[$i]\"><input type = \"hidden\" name=\"kode[$i]\" value=\"$k\">";
			if (($box4!="-") &&($b4!="checkbox")){print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b4\" name=\"box4[$i]\" value=\"$box4[$i]\"></td>";}
			elseif($b4=="checkbox"){
				if (strstr($box4[$i],'on')){print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box4[$i]\" \"checked\"></td>";}
				else {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box4[$i]\"></td>";}
			}
			print "<input type = \"hidden\" name=id[$i] value='$id[$i]'><input type = \"hidden\" name=\"art[$i]\" value=\"$art[$i]\"><input type = \"hidden\" name=\"kode[$i]\" value=\"$k\">";
			if ($box5!="-") {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b5\" name=\"box5[$i]\" value=\"$box5[$i]\"></td>";}
			if ($box6!="-") {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b6\" name=\"box6[$i]\" value=\"$box6[$i]\"></td>";}
			if (($box7!="-")&&($b7!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b7\" name=\"box7[$i]\" value=\"$box7[$i]\"></td>";}
			elseif($b7=="checkbox"){
				if (strstr($box7[$i],'on')){print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box7[$i]\" checked></td>";}
				else {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box7[$i]\"></td>";}
			}
			if (($box8!="-")&&($b8!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b8\" name=\"box8[$i]\" value=\"$box8[$i]\"></td>";}
			elseif($b8=="checkbox"){
				if (strstr($box8[$i],'on')){print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box8[$i]\" checked></td>";}
				else {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box8[$i]\"></td>";}
			}
			if (($box9!="-")&&($b9!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b9\" name=\"box9[$i]\" value=\"$box9[$i]\"></td>";}
			elseif($b9=="checkbox"){
				if (strstr($box9[$i],'on')){print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box9[$i]\" checked></td>";}
				else {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box9[$i]\"></td>";}
			}
			if (($box10!="-")&&($b10!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b10\" name=\"box10[$i]\" value=\"$box10[$i]\"></td>";}
			elseif($b10=="checkbox"){
				if (strstr($box10[$i],'on')){print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=box10[$i] checked></td>";}
				else {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box10[$i]\"></td>";}
			}
			if (($box11!="-")&&($b11!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b11 name=\"box11[$i]\" value=\"$box11[$i]\"></td>";}
			elseif($b11=="checkbox"){
				if (strstr($box11[$i],'on')){print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box11[$i]\" checked></td>";}
				else {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box11[$i]\"></td>";}
			}
			if (($box12!="-")&&($b12!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b12 name=\"box12[$i]\" value=\"$box12[$i]\"></td>";}
			elseif($b12=="checkbox"){
				if (strstr($box12[$i],'on')){print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box12[$i]\" checked></td>";}
				else {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box12[$i]\"></td>";}
			}
			if (($box13!="-")&&($b13!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b13 name=\"box13[$i]\" value=\"$box13[$i]\"></td>";}
			elseif($b13=="checkbox"){
				if (strstr($box13[$i],'on')){print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box13[$i]\" checked></td>";}
				else {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box13[$i]\"></td>";}
			}
			if (($box14!="-")&&($b14!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b14 name=\"box14[$i]\" value=\"$box14[$i]\"></td>";}
			elseif($b14=="checkbox"){
				if (strstr($box14[$i],'on')){print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box14[$i]\" checked></td>";}
				else {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box14[$i]\"></td>";}
			}
			print "</tr>\n";
			print "<input type = \"hidden\" name=id[$i] value='$id[$i]'><input type = \"hidden\" name=\"art[$i]\" value=\"$art[$i]\"><input type = \"hidden\" name=\"kode[$i]\" value=\"$k\">";
		}
	}
	if (($k!='R')||(!$momsrapport)) {
		$y++;
		print "<tr>";
		print "<td>$k</td>";			
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:$size\" name=\"kodenr[$y]\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" size=\"40\" name=\"beskrivelse[$y]\"></td>";
		if (($box1!="-")&&($b1!="checkbox")) {
				if ($art[$y]=='VRG') {
					print "<td title=\"".titletxt($art[$y],'box1')."\"><SELECT NAME=box1[$i] style=\"width: 4em\">";
					print "<option value=\"amount\">kr</option>";
					print "<option value=\"percent\">%</option>";
					print "</SELECT></td>";
				} else {
					print "<td title=\"".titletxt($art[$y],'box1')."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b1\" name=\"box1[$y]\"></td>";
				}
#			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b1 name=box1[$y]></td>";
		} elseif($b1=="checkbox") {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box1[$y]\"></td>";}
		print "<input type = \"hidden\" name=\"id[$y]\" value='0'><input type = \"hidden\" name=\"kode[$y]\" value='$k'><input type = \"hidden\" name=\"art[$y]\" value=\"$a\">";
		if (($box2!="-")&&($b2!="checkbox")) {print "<td title=\"".titletxt($art[$y],'box2')."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b2\" name=\"box2[$y]\"></td>";}
		elseif($b2=="checkbox") {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box2[$y]\"></td>";}
		print "<input type = \"hidden\" name=\"id[$y]\" value='0'><input type = \"hidden\" name=\"kode[$y]\" value='$k'><input type = \"hidden\" name=\"art[$y]\" value=\"$a\">";
		if (($box3!="-")&&($b3!="checkbox")) {print "<td title=\"".titletxt($art[$y],'box3')."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b3\" name=\"box3[$y]\"></td>";}
		elseif($b3=="checkbox") {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box3[$y]\"></td>";}
		print "<input type = \"hidden\" name=\"id[$y]\" value='0'><input type = \"hidden\" name=\"kode[$y]\" value='$k'><input type = \"hidden\" name=\"art[$y]\" value=\"$a\">";
		if (($box4!="-")&&($b4!="checkbox")) {print "<td title=\"".titletxt($art[$y],'box4')."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b4\" name=\"box4[$y]\"></td>";}
		elseif($b4=="checkbox") {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box4[$y]\"></td>";}
		print "<input type = hidden name=\"id[$y]\" value='0'><input type = hidden name=\"kode[$y]\" value='$k'><input type = hidden name=\"art[$y]\" value=$a>";
		if ($box5!="-") {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b5 name=\"box5[$y]\"></td>";}
		if ($box6!="-") {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b6 name=\"box6[$y]\"></td>";}
		if (($box7!="-")&&($b7!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b7 name=\"box7[$y]\"></td>";}
		elseif($b7=="checkbox") {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box7[$y]\"></td>";}
		if (($box8!="-")&&($b8!="checkbox")) {print "<td align=center><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b8 name=\"box8[$y]\"></td>";}
		elseif($b8=="checkbox") {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box8[$y]\"></td>";}
		if (($box9!="-")&&($b9!="checkbox")) {print "<td align=center><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b9 name=\"box9[$y]\"></td>";}
		elseif($b9=="checkbox") {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box9[$y]\"></td>";}
		if (($box10!="-")&&($b10!="checkbox")) {print "<td align=center><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b10 name=\"box10[$y]\"></td>";}
		elseif($b10=="checkbox") {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box10[$y]\"></td>";}
		if (($box11!="-")&&($b11!="checkbox")) {print "<td align=center><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b11 name=\"box11[$y]\"></td>";}
		elseif($b11=="checkbox") {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box11[$y]\"></td>";}
		if (($box12!="-")&&($b12!="checkbox")) {print "<td align=center><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b12 name=\"box12[$y]\"></td>";}
		elseif($b12=="checkbox") {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box12[$y]\"></td>";}
		if (($box13!="-")&&($b13!="checkbox")) {print "<td align=center><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b13 name=\"box13[$y]\"></td>";}
		elseif($b13=="checkbox") {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box13[$y]\"></td>";}
		if (($box14!="-")&&($b14!="checkbox")) {print "<td align=center><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b14 name=\"box14[$y]\"></td>";}
		elseif($b14=="checkbox") {print "<td align=center><input class=\"inputbox\" type=\"checkbox\" name=\"box14[$y]\"></td>";}

		print "<input type = \"hidden\" name=\"id[$y]\" value='0'><input type = \"hidden\" name=\"kode[$y]\" value='$k'><input type =\"hidden\" name=\"art[$y]\" value=\"$a\">";
		print "</tr>\n";
	}
	return $y;
}

###########################################################################################################################
function nytaar($beskrivelse,$kodenr,$kode,$art,$box1,$box3,$box3,$box4,$box5,$box6)
{
	$query = db_SELECT("SELECT id FROM grupper WHERE art = 'RA'",__FILE__ . " linje " . __LINE__);
	print "<form name=nytaar action=syssetup.php method=post>";
	print "<tr><td colspan=4 align = center><big><b>Opret Regnskabs&aring;r: $beskrivelse</td></tr>\n";
	if (!$row = db_fetch_array($query))
	{
		print "<tr><td colspan=2 align=center> Intast primotal for 1. regnskabs&aring;r:</td><td align = center>debet</td><td align = center>kredit</td></tr>\n";
		$query = db_SELECT("SELECT id, kontonr,beskrivelse FROM kontoplan WHERE kontotype='D' or kontotype='S' order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query))
		{
			print "<tr><input type=hidden name=kontonr[$y] value=$row[kontonr]><td>$row[kontonr]</td><td>$row[beskrivelse]</td><td width=10 align=right><input class=\"inputbox\" type=\"text\" size=10 name=debet[$y]></td><td align=right><input class=\"inputbox\" type=\"text\" size=10 name=kredit[$y]></td></tr>\n";
		}
	}
	else
	{
		print "<tr><td> Overf&oslash;r &aring;bningsbalance</td><td><input class=\"inputbox\" type=\"checkbox\" name=aabn_bal></td></tr>\n";
	}
	print "<tr><td colspan = 4 align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td></tr>\n";
	print "</form>";
	exit;
}

###########################################################################################################################
function tjek ($id,$beskrivelse,$kodenr,$kode,$art,$box1,$box2,$box3,$box4,$box5,$box6,$box7,$box8,$box9)
{
	$fejl=NULL;
	
	if ($beskrivelse)
	{
		if ($art=='VG')
		{
			if ($box8=='on')
			{
				if (!$box1) {print "<BODY onLoad=\"javascript:alert('Lager Tilgang\" skal udfyldes n&aring;r \"Lagerf&oslash;rt\" er afm&aelig;rket')\">";}
				else {$fejl=kontotjek($box1);}
				if (!$box2) {print "<BODY onLoad=\"javascript:alert('Lager Tr&aelig;k\" skal udfyldes n&aring;r \"Lagerf&oslash;rt\" er afm&aelig;rket')\">";}
				else {$fejl=kontotjek($box2);}
			}		
			if (!$box3) {print "<BODY onLoad=\"javascript:alert('Varek&oslash;b skal udfyldes')\">";}
			else {$fejl=kontotjek($box3);}
			if (!$box4) {print "<BODY onLoad=\"javascript:alert('Varesalg skal udfyldes')\">";}
			else {$fejl=kontotjek($box4);}
			if ($box5) {$fejl=kontotjek($box5);}
			if ($box6) {$fejl=kontotjek($box6);}
		}
		if ($art=='KM' || $art=='SM' || $art=='EM' || $art=='YM') { # 20132127
			if (!is_numeric($kodenr) && $kodenr!='-') { #20140621
				print "<BODY onLoad=\"javascript:alert('Nr skal være numerisk! ($kodenr)')\">";
				return ('1');
			}
		}
		if (($art=='DS')||($art=='KS')||($art=='KM')||($art=='SM')) {$fejl=kontotjek($box1);}
		if (($art=='DG')||($art=='KG')) {$fejl=momsktotjek($art,$box1);}
		if (($art=='DG')||($art=='KG')) {$fejl=kontotjek($box2);}
		if (($art=='DG')||($art=='KG')) {$fejl=kontotjek($box5);}
		if (($art=='DG')||($art=='KG')) {$fejl=sprogtjek($box4);}
		if ($art=='LG') {$fejl=afdelingstjek($box1);}
				
		return $fejl;	
	}
}

###########################################################################################################################
function kontotjek ($konto)
{ 
	$fejl=NULL;
	$konto=$konto*1;	
	if ($konto) {
		$query = db_SELECT("SELECT id FROM kontoplan WHERE kontonr = '$konto' and (kontotype = 'D' or kontotype = 'S')",__FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($query)) { 
			print "<BODY onLoad=\"javascript:alert('Kontonr: $konto kan ikke anvendes!!')\">";
			$fejl=1;
		}
	return $fejl;
	}
}
###########################################################################################################################
function sprogtjek ($sprog)
{ 
	$fejl=NULL;
	if ($sprog) {
		$tmp=strtolower($sprog);
		$query = db_SELECT("SELECT id FROM formularer WHERE lower(sprog) = '$tmp'",__FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($query)) { 
			print "<BODY onLoad=\"javascript:alert('Der eksisterer ikke nogen formular med $sprog som sprog!')\">";
			$fejl=1;
		}
	return $fejl;
	}
}
###########################################################################################################################
function momsktotjek ($art,$konto)
{
	$fejl=NULL;
	if ($konto) {
		if ($art=='DG') {$momsart="art='SM'";}
		if ($art=='KG') {$momsart="(art='KM' or art='YM' or art='EM')";}
		$kode=substr($konto,0,1);
		$kodenr=substr($konto,1,1);
		$query = db_SELECT("SELECT id FROM grupper WHERE $momsart and kodenr = '$kodenr' and kode = '$kode'",__FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($query))	{ 
			if ($art=='DG') print "<BODY onLoad=\"javascript:alert('Salgsmomsgruppe: $konto findes ikke!!')\">";
			if ($art=='KG') print "<BODY onLoad=\"javascript:alert('K&oslash;bsmomskonto: $konto findes ikke!!')\">";
			$fejl=1;
		}
		return $fejl;
	}
}
###########################################################################################################################
function afdelingstjek ($konto)
{
	$fejl=NULL;
	$query = db_SELECT("SELECT id FROM grupper WHERE art='AFD' and kodenr = '$konto'",__FILE__ . " linje " . __LINE__);
	if (!db_fetch_array($query))	{
		print "<BODY onLoad=\"javascript:alert('Afdeling: $konto findes ikke!!')\">";
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
	return($titletxt);
}


?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
