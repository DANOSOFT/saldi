<?php
//                         ___   _   _   __  _
//                        / __| / \ | | |  \| |
//                        \__ \/ _ \| |_| | | |
//                        |___/_/ \_|___|__/|_|
//
// -------------------- systemdata/posmenuer.php ------ patch 3.6.4--2016-03-07--------
// LICENS..
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
// Copyright (c) 2004-2016 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2013.10.17 Ku max ha' 10 menuer.
// 2014.11.11 Tilføjet knapdesign på menu 0: Tastatur og tilføjet radius på knapper.
// 2015.01.07 Menu blev slettet når side blev åbnet #20150107
// 2015.09.25 Kolonner og rækker kan nu flyttes. Søg flyt_col & flyt_row
// 2015.10.23	Tilføjet knap Enter+Menu. Efter knaptekst skrives +x, hvor x er den menu der skal kaldes.
// 2015.11.29	Tilføjet knap Konant på beløb & Betalingskort på beløb.
// 2016.01.28 Tilføjet systemknap Stamkunder. Se funktion stamkunder i ordrefunc.php
// 2016.01.31 Tilføjet systemknap Kontoudtog & Udskriv sidste. 
// 2016.02.18 Kontrol for strenglængde for butcolor. Søg 20160218  
// 2016.03.07 Man kunne ikke lave tastaturknapper med '-'Søg 20160307

@session_start();
$s_id=session_id();
$title="POS knap menu";
$modulnr=1;
$css="../css/pos.css";

$diffkto=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

#$systemknap=array('Afslut','Bordvalg','Brugervalg','Del bord','Enter','Find bon','Flyt bord','Kasseoptælling','Kassevalg','Køkkenprint','Luk','Skuffe','Udskriv','Forfra','Ekspedient','Ryd','Afslut','Pris','Rabat','Tilbage','Ny kunde','Korrektion','Kortterminal','Send til køkken','Kør bord');

$menuvalg=if_isset($_POST['menuvalg']);
$menu_id=if_isset($_POST['menu_id']);
$beskrivelse=if_isset($_POST['beskrivelse']);
$plads=if_isset($_POST['plads']);
$cols=if_isset($_POST['cols'])*1;
$rows=if_isset($_POST['rows'])*1;
$height=if_isset($_POST['height'])*1;
$radius=if_isset($_POST['radius'])*1;
$fontsize=if_isset($_POST['fontsize'])*1;
$width=if_isset($_POST['width'])*1;
$menutype=if_isset($_POST['menutype']);
$begin=if_isset($_POST['begin']);
$end=if_isset($_POST['end']);
$projekt=if_isset($_POST['projekt']);
$buttxt=trim(if_isset($_POST['buttxt']));
$butcolor=if_isset($_POST['butcolor']);
$butfunc=if_isset($_POST['butfunc']);
$butvnr=if_isset($_POST['butvnr']);
$ret_col=if_isset($_POST['ret_col']);
$ret_row=if_isset($_POST['ret_row']);
$byt=if_isset($_POST['byt']);
$flyt_col=if_isset($_POST['flyt_col']); 
$flyt_row=if_isset($_POST['flyt_row']); 

if (isset($_GET['menu_id'])) {
	$menu_id=if_isset($_GET['menu_id']);
	$ret_col=if_isset($_GET['ret_col']);
	$ret_row=if_isset($_GET['ret_row']);
	$farvekode=if_isset($_GET['farvekode']);
	if ($farvekode && $menu_id && $ret_col && $ret_row) {
		db_modify("update pos_buttons set color='$farvekode' where menu_id='$menu_id' and col='$ret_col' and row='$ret_row'",__FILE__ . " linje " . __LINE__);
	}
	if ($flyt_col) {
		for ($x=1;$x<=count($flyt_col);$x++) {
			if (ord($flyt_col[$x])-96 != $x && ord($flyt_col[$x])-96 <= count($flyt_col)) {
				$ny=ord($flyt_col[$x])-96;
				db_modify("update pos_buttons set col='0' where menu_id='$menu_id' and col='$x'",__FILE__ . " linje " . __LINE__);
				db_modify("update pos_buttons set col='$x' where menu_id='$menu_id' and col='$ny'",__FILE__ . " linje " . __LINE__);
				db_modify("update pos_buttons set col='$ny' where menu_id='$menu_id' and col='0'",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	if ($flyt_row) {
		for ($x=1;$x<=count($flyt_row);$x++) {
			if ($flyt_row[$x] != $x && $flyt_row[$x] <= count($flyt_row)) {
				db_modify("update pos_buttons set row='0' where menu_id='$menu_id' and row='$x'",__FILE__ . " linje " . __LINE__);
				db_modify("update pos_buttons set row='$x' where menu_id='$menu_id' and row='$flyt_row[$x]'",__FILE__ . " linje " . __LINE__);
				db_modify("update pos_buttons set row='$flyt_row[$x]' where menu_id='$menu_id' and row='0'",__FILE__ . " linje " . __LINE__);
			}
		}
	}

} 

$beskrivelse=db_escape_string($beskrivelse);

if ($menutype) {
	$begin=tid($begin);
	$end=tid($end);
	if ($end=='00:00') $end='24:00';
}

if ($menuvalg=='ny') {
	$x=0;
	$m_id=NULL;
	$kodenr=array();
	$q=db_select("select kodenr from grupper where art='POSBUT' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$kodenr[$x]=$r['kodenr'];
		$x++;
	}
	for ($x=0;$x<count($kodenr);$x++) {
		if (!$m_id && $x && !in_array($x,$kodenr)) $m_id=$x;
	}
	if (!$m_id) $m_id=$x;
	if (($m_id || $m_id=='0') && $beskrivelse) {
		$qtxt="insert into grupper(beskrivelse,art,kode,kodenr,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11) values ('POS menu knapper','POSBUT','$plads','$m_id','$beskrivelse','$cols','$rows','$height','$width','$menutype','$begin','$end','$projekt','$fontsize','$radius')"; 
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
} else {
	list($m_id,$tmp)=explode(":",$menuvalg);
	$menu_id*=1;
	if ($menu_id && $beskrivelse=='-' && $butfunc != '5') { #20160307
		$qtxt="delete from pos_buttons where menu_id=$menu_id";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
		$qtxt="delete from grupper where art='POSBUT' and kodenr='$menu_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	} elseif ($menu_id==$m_id && $cols && $rows) {
		$qtxt="update grupper set kode='$plads',box1='$beskrivelse',box2='$cols',box3='$rows',box4='$height',box5='$width',box6='$menutype',box7='$begin',box8='$end',box9='$projekt',box10='$fontsize',box11='$radius' where art='POSBUT' and kodenr='$m_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	} elseif ($menu_id && $ret_col && $ret_row && $butfunc) { #20150107
		$buttxt=db_escape_string($buttxt);
		$buttxt=str_replace("<br>","\n",$buttxt);
		if ($butfunc==1) {
			$r=db_fetch_array(db_select("select id,beskrivelse from varer where varenr='$butvnr' and lukket !='on'"));
			if (!$buttxt) $buttxt=db_escape_string($r['beskrivelse']);
			$butvnr=$r['id']*1;
		} elseif ($butfunc==3) {
			$r=db_fetch_array(db_select("select id from adresser where kontonr='$butvnr' and lukket !='on'",__FILE__ . " linje " . __LINE__));
			$butvnr=$r['id']*1;
		} elseif ($butfunc==6) {
			$butvnr=$butvnr*1;
		} else $butvnr=$butvnr*1;
		$qtxt="select id from pos_buttons where menu_id='$menu_id' and row='$ret_row' and col='$ret_col'";
		if (strlen($butcolor)>6) $butcolor=substr($butcolor,-6); #20160218
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$but_id=$r['id'];
				#cho "$buttxt && $butfunc<br>";
				if ($buttxt=='-' && $butfunc != '5') $qtxt="delete from pos_buttons where id='$but_id'"; # 20160307
				else $qtxt="update pos_buttons set beskrivelse='$buttxt',color='$butcolor',funktion='$butfunc',vare_id='$butvnr' where id='$r[id]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} elseif ($buttxt || $butcolor || $butfunc || $butvnr) {
			$qtxt="insert into pos_buttons (menu_id,row,col,beskrivelse,color,funktion,vare_id,colspan,rowspan) values ('$menu_id','$ret_row','$ret_col','$buttxt','$butcolor','$butfunc','$butvnr','1','1')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		if ($byt) {
			$ny_col=ord(substr($byt,0,1))-96;
			$ny_row=substr($byt,1);
			if (is_numeric($ny_col) && is_numeric($ny_row)) {
				if (!$bud_id) {
					$qtxt="select id from pos_buttons where menu_id='$menu_id' and row='$ret_row' and col='$ret_col'";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$but_id=$r['id'];
				}
				$qtxt="select id from pos_buttons where menu_id='$menu_id' and row='$ny_row' and col='$ny_col'";
				if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					db_modify("update pos_buttons set col='$ret_col',row='$ret_row' where id='$r[id]'",__FILE__ . " linje " . __LINE__); 
				}
				db_modify("update pos_buttons set col='$ny_col',row='$ny_row' where id='$but_id'",__FILE__ . " linje " . __LINE__); 
				$ret_col=$ny_col;
				$ret_row=$ny_row;
			}
		}
	}
}
$x=0;
list($tmp,$beskrivelse)=explode(":",$menuvalg);
if (is_numeric($tmp) && $menu_id != $tmp) $menu_id=$tmp;
$menu_id*=1;
$qtxt="select * from grupper where art='POSBUT' and kodenr='$menu_id'";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
$menu_id=$r['kodenr'];
if (!$menu_id) {
	$plads='H';
	$beskrivelse="Tastatur";;
	$menutype='';
	$cols='3';
	$rows='3';
} else {
	$plads=$r['kode'];
	$beskrivelse=str_replace("\n","<br>",$r['box1']);
	$menutype=$r['box6'];
	$cols=$r['box2'];
	$rows=$r['box3'];
}
$height=$r['box4'];
$width=$r['box5'];
$begin=$r['box7'];
$end=$r['box8'];
$projekt=$r['box9'];
$fontsize=$r['box10'];
$radius=$r['box11'];
if ($menutype=='on' || $menutype=='H') {
	$menutype='H';
	if (!$begin) $begin="00:00";
	if (!$end) $end="24:00";
}
if (!$cols) $cols=10;
if (!$rows) $rows=1;
if (!$height) $height=40;
if (!$width) $width=80;
if (!$radius) $radius=1;
if (!$fontsize) $fontsize=20;

print "<table border=\"1\"><tbody><tr><td>\n";
print "<form name=\"posmenuer\" action=\"posmenuer.php\" method=\"post\">\n";
// Vindue 1 - >
print "<table border=\"0\"><tbody>\n";
print "<tr><td><a href=diverse.php?sektion=pos_valg>Luk</a></td></tr>\n";
if (($menu_id) && $ret_col && $ret_row) {
	print "<tr><td colspan=\"2\" align=\"center\">Menu $menu_id</td></tr>\n";
	print "<tr><td colspan=\"2\" align=\"center\"><a href=\"posmenuer.php?menu_id=$menu_id\">Skift menu</a></td></tr>\n";
	print "<tr>\n";
	print "<td style=\"width:140px;height:".$height."px;text-align:left\">\n
	<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Tekst\"><br>\n
	<a href=\"../includes/farvekort.php?menu_id=$menu_id&ret_col=$ret_col&ret_row=$ret_row\">\n
	<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Farvekode\"></a><br>\n
	<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Vare-/menunr\"><br>\n
	<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Funktion\">\n
	<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Byt med:\">\n
	<input type=\"hidden\" name=\"menu_id\" value=\"$menu_id\">\n
	<input type=\"hidden\" name=\"ret_col\" value=\"$ret_col\">\n
	<input type=\"hidden\" name=\"ret_row\" value=\"$ret_row\">\n
	</td>\n";

	$qtxt="select * from pos_buttons where menu_id='$menu_id' and row='$ret_row' and col='$ret_col'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$a=str_replace("\n","<br>",$r['beskrivelse']);
	$b=$r['color'];
	$c=$r['vare_id']*1;
	$d=$r['funktion']*1;
	
	if ($d==1 && $c) {
		$r=db_fetch_array(db_select("select varenr from varer where id='$c' and lukket !='on'"));
		$c=$r['varenr'];
	}
	if ($d==3 && $c) {
		$r=db_fetch_array(db_select("select kontonr from adresser where id='$c' and lukket !='on'"));
		$c=$r['kontonr'];
	}
	if (!$c) $c='';
	print "<td style=\"width:100px;height:100px;text-align:center\">";
	if ($d==7) {
		$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
		$kortantal=$r['box4']*1;
		$korttyper=explode(chr(9),$r['box5']);
		print "<SELECT CLASS=\"inputbox\" style=\"width:100px;\" name=\"buttxt\">\n";
		if($a=='Kontant') print "<OPTION>Kontant</OPTION>\n";
		elseif($a=='Kontant på beløb') print "<OPTION>Kontant på beløb</OPTION>\n";
		elseif($a=='Konto') print "<OPTION>Konto</OPTION>\n";
		for($x=0;$x<$kortantal;$x++) {
			if ($a==$korttyper[$x]) print "<OPTION>$korttyper[$x]</OPTION>\n";
			elseif ($a==$korttyper[$x].' på beløb') print "<OPTION>$korttyper[$x] på beløb</OPTION>\n";
		}
		if($a!='Kontant') print "<OPTION>Kontant</OPTION>\n";
		if($a!='Kontant på beløb') print "<OPTION>Kontant på beløb</OPTION>\n";
		if($a!='Konto') print "<OPTION>Konto</OPTION>\n";
		for($x=0;$x<$kortantal;$x++) {
			if ($buttxt!=$korttyper[$x]) print "<OPTION>$korttyper[$x]</OPTION>\n";
			if ($buttxt!=$korttyper[$x].' på beløb') print "<OPTION>$korttyper[$x] på beløb</OPTION>\n";
		}
		print "</SELECT>\n";
	} else print "<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:100px;text-align:center\" name=\"buttxt\" value=\"$a\"><br>\n";
	print "<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:100px;text-align:center\" name=\"butcolor\" value=\"$b\"><br>\n";
	if ($d==6) {
		print "<SELECT CLASS=\"inputbox\" style=\"width:100px;\" name=\"butvnr\">\n";
		if ($c==1) print "<OPTION value=\"1\">Bordvalg</OPTION>\n";
		if ($c==2) print "<OPTION value=\"2\">Brugervalg</OPTION>\n";
		if ($c==3) print "<OPTION value=\"3\">Del bord</OPTION>\n";
		if ($c==4) print "<OPTION value=\"4\">Enter</OPTION>\n";
		if ($c==5) print "<OPTION value=\"5\">Find bon</OPTION>\n";
		if ($c==6) print "<OPTION value=\"6\">Flyt bord</OPTION>\n";
		if ($c==7) print "<OPTION value=\"7\">Kasseoptælling</OPTION>\n";
		if ($c==8) print "<OPTION value=\"8\">Kassevalg</OPTION>\n";
		if ($c==9) print "<OPTION value=\"9\">Køkkenprint</OPTION>\n";
		if ($c==10) print "<OPTION value=\"10\">Luk</OPTION>\n";
		if ($c==11) print "<OPTION value=\"11\">Skuffe</OPTION>\n";
		if ($c==12) print "<OPTION value=\"12\">Udskriv</OPTION>\n";
		if ($c==13) print "<OPTION value=\"13\">Forfra</OPTION>\n";
		if ($c==14) print "<OPTION value=\"14\">Ekspedient</OPTION>\n";
		if ($c==15) print "<OPTION value=\"15\">Ryd</OPTION>\n";
		if ($c==16) print "<OPTION value=\"16\">Afslut</OPTION>\n";
		if ($c==17) print "<OPTION value=\"17\">Pris</OPTION>\n";
		if ($c==18) print "<OPTION value=\"18\">Rabat</OPTION>\n";
		if ($c==19) print "<OPTION value=\"19\">Tilbage</OPTION>\n";
		if ($c==20) print "<OPTION value=\"20\">Ny kunde</OPTION>\n";
		if ($c==21) print "<OPTION value=\"21\">Korrektion</OPTION>\n";
		if ($c==22) print "<OPTION value=\"22\">Kortterminal</OPTION>\n";
		if ($c==23) print "<OPTION value=\"23\">Send til køkken</OPTION>\n";
		if ($c==24) print "<OPTION value=\"24\">Kør bord</OPTION>\n";
		if ($c==25) print "<OPTION value=\"25\">Kontoopslag</OPTION>\n";
		if ($c==26) print "<OPTION value=\"26\">Indbetaling</OPTION>\n";
		if ($c==27) print "<OPTION value=\"27\">Konto</OPTION>\n";
		if ($c==28) print "<OPTION value=\"28\">Enter+Menu</OPTION>\n";
		if ($c==29) print "<OPTION value=\"29\">Vareopslag</OPTION>\n";
		if ($c==30) print "<OPTION value=\"30\">Stamkunder</OPTION>\n";
		if ($c==31) print "<OPTION value=\"31\">Kontoudtog</OPTION>\n";
		if ($c==32) print "<OPTION value=\"32\">Udskriv sidste</OPTION>\n";
		if ($c!=16) print "<OPTION value=\"16\">Afslut</OPTION>\n";
		if ($c!=1) print "<OPTION value=\"1\">Bordvalg</OPTION>\n";
		if ($c!=2) print "<OPTION value=\"2\">Brugervalg</OPTION>\n";
		if ($c!=3) print "<OPTION value=\"3\">Del bord</OPTION>\n";
		if ($c!=14) print "<OPTION value=\"14\">Ekspedient</OPTION>\n";
		if ($c!=4) print "<OPTION value=\"4\">Enter</OPTION>\n";
		if ($c!=28) print "<OPTION value=\"28\">Enter+Menu</OPTION>\n";
		if ($c!=5) print "<OPTION value=\"5\">Find bon</OPTION>\n";
		if ($c!=6) print "<OPTION value=\"6\">Flyt bord</OPTION>\n";
		if ($c!=13) print "<OPTION value=\"13\">Forfra</OPTION>\n";
		if ($c!=26) print "<OPTION value=\"26\">Indbetaling</OPTION>\n";
		if ($c!=7) print "<OPTION value=\"7\">Kasseoptælling</OPTION>\n";
		if ($c!=8) print "<OPTION value=\"8\">Kassevalg</OPTION>\n";
		if ($c!=27) print "<OPTION value=\"27\">Konto</OPTION>\n";
		if ($c!=25) print "<OPTION value=\"25\">Kontoopslag</OPTION>\n";
		if ($c!=31) print "<OPTION value=\"31\">Kontoudtog</OPTION>\n";
		if ($c!=21) print "<OPTION value=\"21\">Korrektion</OPTION>\n";
		if ($c!=22) print "<OPTION value=\"22\">Kortterminal</OPTION>\n";
		if ($c!=9) print "<OPTION value=\"9\">Køkkenprint</OPTION>\n";
		if ($c!=24) print "<OPTION value=\"24\">Kør bord</OPTION>\n";
		if ($c!=10) print "<OPTION value=\"10\">Luk</OPTION>\n";
		if ($c!=20) print "<OPTION value=\"20\">Ny kunde</OPTION>\n";
		if ($c!=17) print "<OPTION value=\"17\">Pris</OPTION>\n";
		if ($c!=18) print "<OPTION value=\"18\">Rabat</OPTION>\n";
		if ($c!=15) print "<OPTION value=\"15\">Ryd</OPTION>\n";
		if ($c!=23) print "<OPTION value=\"23\">Send til køkken</OPTION>\n";
		if ($c!=11) print "<OPTION value=\"11\">Skuffe</OPTION>\n";
		if ($c!=30) print "<OPTION value=\"30\">Stamkunder</OPTION>\n";
		if ($c!=19) print "<OPTION value=\"19\">Tilbage</OPTION>\n";
		if ($c!=12) print "<OPTION value=\"12\">Udskriv</OPTION>\n";
		if ($c!=32) print "<OPTION value=\"32\">Udskriv sidste</OPTION>\n";
		if ($c!=29) print "<OPTION value=\"29\">Vareopslag</OPTION>\n";
		print	"</SELECT>\n";
	} else print "<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:100px;text-align:center\" name=\"butvnr\" value=\"$c\"><br>\n";
	print "<SELECT CLASS=\"inputbox\" style=\"width:100px;\" name=\"butfunc\">\n";
	if ($d==1) print "<OPTION value=\"1\">Varenr</OPTION>\n";
	if ($d==2) print "<OPTION value=\"2\">Menu</OPTION>\n";
	if ($d==3) print "<OPTION value=\"3\">Kundenr</OPTION>\n";
	if ($d==4) print "<OPTION value=\"4\">Specialfunktion</OPTION>\n";
	if ($d==5) print "<OPTION value=\"5\">Tastatur</OPTION>\n";
	if ($d==6) print "<OPTION value=\"6\">Systemknap</OPTION>\n";
	if ($d==7) print "<OPTION value=\"7\">Betalingsknap</OPTION>\n";
	if ($d!=1) print "<OPTION value=\"1\">Varenr</OPTION>\n";
	if ($d!=2) print "<OPTION value=\"2\">Menu</OPTION>\n";
	if ($d!=3) print "<OPTION value=\"3\">Kundenr</OPTION>\n";
	if ($d!=4) print "<OPTION value=\"4\">Specialfunktion</OPTION>\n";
	if ($d!=5) print "<OPTION value=\"5\">Tastatur</OPTION>\n";
	if ($d!=6) print "<OPTION value=\"6\">Systemknap</OPTION>\n";
	if ($d!=7) print "<OPTION value=\"7\">Betalingsknap</OPTION>\n";
	print	"</SELECT><br>\n";
	print "<input style=\"width:100px;\" type=\"text\" name=\"byt\"></td></tr>\n";
} else {
	print "<tr><td>Menu ID</td><td>$menu_id</td></tr>\n";
	print "<tr><td></td><td><select CLASS=\"inputbox\" name=\"menuvalg\">\n";
	if (($menu_id || $menu_id=='0') && $beskrivelse) $menuvalg=$menu_id.":".$beskrivelse;
	else $menuvalg=NULL;
	$menu_id=$menu_id*1;
	($menu_id)?$disabled='':$disabled='disabled=disabled';

	print "<option value=\"$menuvalg\">$menuvalg</option>";
	$q = db_select("select * from grupper where art = 'POSBUT' order by box1",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tmp=$r['kodenr'].":".$r['box1'];
		if ($tmp!=$menuvalg) print "<option value=\"$tmp\">$tmp</option>\n";
	}
	print "<option value=\"ny\">Opret ny</option>\n";
	print "</select></td>\n";
	print "<td><input type=submit value=\"ok\" name=\"ok\"></td></tr>\n";
	print "<input type=\"hidden\" name=\"menu_id\" value=\"$menu_id\">\n";
	print "<tr><td>Beskrivelse</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"beskrivelse\" value=\"$beskrivelse\"></td></tr>\n";
	if ($beskrivelse) {
		print "<tr><td>Menytype</td><td><SELECT CLASS=\"inputbox\" $disabled name=\"menutype\">\n";
		if ($menutype=='H') print "<option value='H'>Hovedmenu</option>\n";
		elseif ($menutype=='B') print "<option value='B'>Bogført</option>\n";
		elseif ($menutype=='A') print "<option value='A'>Afslutning</option>\n";
		else print "<option value=''>Undermenu</option>\n";
		if ($menutype!='H') print "<option value='H'>Hovedmenu</option>\n";
		if ($menutype!='A') print "<option value='A'>Afslutning</option>\n";
		if ($menutype!='B') print "<option value='B'>Bogført</option>\n";
		if ($menutype) print "<option value=''>Undermenu</option>\n";
		print "</select></td></tr>\n";
		if ($menutype=='H') {	
			print "<tr><td>Aktiv fra</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"begin\" value=\"$begin\"></td></tr>";
			print "<tr><td>Aktiv til</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"end\" value=\"$end\"></td></tr>";
			print "<tr><td>Projekt</td><td><SELECT CLASS=\"inputbox\" $disabled name=\"projekt\">";
			$r=db_fetch_array(db_select("select * from grupper where art='PRJ' and kodenr='$projekt'",__FILE__ . " linje " . __LINE__));
			print "<option value=\"$projekt\">$r[beskrivelse]</option>";
			$q=db_select("select * from grupper where art='PRJ'",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				if ($projekt!=$r['kodenr']) print "<option value=\"$r[kodenr]\">$r[beskrivelse]</option>";
			}
			if ($projekt) print "<option value=\"\"></option>";
			print "</select></td></tr>";
		}
		if ($menu_id || $menu_id=='0') {
			print "<tr><td>Antal menur&aelig;kker</td><td><INPUT CLASS=\"inputbox\" $disabled TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"rows\" value=\"$rows\"></td></tr>";
			print "<tr><td>Antal menukolonner</td><td><INPUT CLASS=\"inputbox\" $disabled TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"cols\" value=\"$cols\"></td></tr>";
		} else {
			print "<INPUT TYPE=\"hidden\" name=\"rows\" value=\"$rows\"></td></tr>";
			print "<INPUT TYPE=\"hidden\" name=\"cols\" value=\"$cols\"></td></tr>";
		}
		print "<tr><td>Knap h&oslash;jde</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"height\" value=\"$height\"></td></tr>";
		print "<tr><td>Knap bredde</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"width\" value=\"$width\"></td></tr>";
		print "<tr><td>Radius</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"radius\" value=\"$radius\"></td></tr>";
		print "<tr><td>tekst st&oslash;rrelse</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"fontsize\" value=\"$fontsize\"></td></tr>";
		print "<tr><td>Plads</td><td><SELECT CLASS=\"inputbox\" $disabled name=\"plads\">";
		if ($plads=='H') {
			print "<option value=\"H\">Højre side</option>";
			print "<option value=\"B\">Bund</option>";
		} else {
			print "<option value=\"B\">Bund</option>";
			print "<option value=\"H\">Højre side</option>";
		}
		print "</select></td></tr>";
	}
}
if (!$menu_id) { 
	print "<input type=\"hidden\" name=\"projekt\" value=\"\">";
	print "<input type=\"hidden\" name=\"rows\" value=\"$rows\">";
	print "<input type=\"hidden\" name=\"cols\" value=\"$cols\">";
	print "<input type=\"hidden\" name=\"plads\" value=\"$plads\">";
}
print "<tr><td><input type=submit value=\"ok\" name=\"ok\"></td></tr>";
print "</tbody></table></td></form>";
print "<td><table border=0><tbody>";
// <- Vindue 1
#if ($menu_id ) {
	if ($plads=='H') output($menu_id,$rows,$cols,$radius,$width,$height,$fontsize,$bgcolor2);
#}
print "</tbody></table>";
print "</td></tr><tr><td colspan=\"2\">";
// vindue 2 -> 
print "<table border=\"0\" cellspacing=\"5\" cellpadding=\"5\"><tbody>";
#if ($menu_id) {
	if ($plads=='B') output($menu_id,$rows,$cols,$radius,$width,$height,$fontsize,$bgcolor2);
#}
print "</tbody></table>";
print "</td><tr><td colspan=\"2\ width=\"100%\"><tr>";
print "<tbody>";
print "</tbody>";
print "</td></tr></tbody></table>";

function tid($tid) {
	list($a,$b)=explode(":",$tid);
	$a=$a*1;
	if ($a>24) $a=24;
	while (strlen($a)<2) $a="0".$a;
	if ($b>59) $b=59;
	while (strlen($b)<2) $b="0".$b;
	$tid=$a.":".$b;
	return($tid);
}
function input($menu_id,$rows,$cols) {
	for ($x=1;$x<=$rows;$x++) {
		print "<tr>";
		print "<td style=\"width:140px;height:".$height."px;text-align:left\">
		<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Tekst\"><br>
		<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Farvekode\"><br>
		<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Vare-/menunr\"><br>
		<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Funktion\">
		<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Byt plads med\">
		</td>";
		for ($y=1;$y<=$cols;$y++) {
			$r=db_fetch_array(db_select("select * from pos_buttons where menu_id='$menu_id' and row='$x' and col='$y'"));
			$a=str_replace("\n","<br>",$r['beskrivelse']);
			$b=$r['color'];
			$c=$r['vare_id']*1;
			$d=$r['funktion']*1;
			if ($d==1 && $c) {
				$r=db_fetch_array(db_select("select varenr from varer where id='$c' and lukket !='on'"));
				$c=$r['varenr'];
			}
			if ($d==3 && $c) {
				$r=db_fetch_array(db_select("select kontonr from adresser where id='$c' and lukket !='on'"));
				$c=$r['kontonr'];
			}
			if (!$c) $c='';
			print "<td style=\"width:100px;height:100px;text-align:center\">
			<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:100px;text-align:center\" name=\"buttxt\" value=\"$a\"><br>
			<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:100px;text-align:center\" name=\"butcolor\" value=\"$b\"><br>\n";
			if ($d==6) {
				print "<SELECT CLASS=\"inputbox\" style=\"width:100px;\" name=\"butvnr\">";
				if ($c==1) print "<OPTION value=\"1\">Bordvalg</OPTION>";
				if ($c==2) print "<OPTION value=\"2\">Brugervalg</OPTION>";
				if ($c==3) print "<OPTION value=\"3\">Del bord</OPTION>";
				if ($c==4) print "<OPTION value=\"4\">Enter</OPTION>";
				if ($c==5) print "<OPTION value=\"5\">Find bon</OPTION>";
				if ($c==6) print "<OPTION value=\"6\">Flyt bord</OPTION>";
				if ($c==7) print "<OPTION value=\"7\">Kasseoptælling</OPTION>";
				if ($c==8) print "<OPTION value=\"8\">Kassevalg</OPTION>";
				if ($c==9) print "<OPTION value=\"9\">Køkkenprint</OPTION>";
				if ($c==10) print "<OPTION value=\"10\">Luk</OPTION>";
				if ($c==11) print "<OPTION value=\"11\">Skuffe</OPTION>";
				if ($c==12) print "<OPTION value=\"12\">Udskriv</OPTION>";
				if ($c!=1) print "<OPTION value=\"1\">Bordvalg</OPTION>";
				if ($c!=2) print "<OPTION value=\"2\">Brugervalg</OPTION>";
				if ($c!=3) print "<OPTION value=\"3\">Del bord</OPTION>";
				if ($c!=4) print "<OPTION value=\"4\">Enter</OPTION>";
				if ($c!=5) print "<OPTION value=\"5\">Find bon</OPTION>";
				if ($c!=6) print "<OPTION value=\"6\">Flyt bord</OPTION>";
				if ($c!=7) print "<OPTION value=\"7\">Kasseoptælling</OPTION>";
				if ($c!=8) print "<OPTION value=\"8\">Kassevalg</OPTION>";
				if ($c!=9) print "<OPTION value=\"9\">Køkkenprint</OPTION>";
				if ($c!=10) print "<OPTION value=\"10\">Luk</OPTION>";
				if ($c!=11) print "<OPTION value=\"11\">Skuffe</OPTION>";
				if ($c!=12) print "<OPTION value=\"12\">Udskriv</OPTION>";
				print	"</SELECT>";
			} else print "<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:100px;text-align:center\" name=\"butvnr\" value=\"$c\"><br>\n";
			print "<SELECT CLASS=\"inputbox\" style=\"width:100px;\" name=\"butfunc\">";
			if ($d==1) print "<OPTION value=\"1\">Varenr</OPTION>";
			if ($d==2) print "<OPTION value=\"2\">Menu</OPTION>";
			if ($d==3) print "<OPTION value=\"3\">Kundenr</OPTION>";
			if ($d==4) print "<OPTION value=\"4\">Specialfunktion</OPTION>";
			if ($d==5) print "<OPTION value=\"5\">Tastatur</OPTION>";
			if ($d==6) print "<OPTION value=\"6\">Systemknap</OPTION>";
			
			if ($d!=1) print "<OPTION value=\"1\">Varenr</OPTION>";
			if ($d!=2) print "<OPTION value=\"2\">Menu</OPTION>";
			if ($d!=3) print "<OPTION value=\"3\">Kundenr</OPTION>";
			if ($d!=4) print "<OPTION value=\"4\">Specialfunktion</OPTION>";
			if ($d!=5) print "<OPTION value=\"5\">Tastatur</OPTION>";
			if ($d!=6) print "<OPTION value=\"6\">Systemknap</OPTION>";
			print	"</SELECT></td>";
		}
		print "</tr>";
	}
}
function output ($menu_id,$rows,$cols,$radius,$width,$height,$fontsize,$bgcolor2) {
	print "<tr><td></td>\n";	
	print "<form name=\"flyt_cols\" action=\"posmenuer.php?menu_id=$menu_id\" method=\"post\">\n";
	for ($y=1;$y<=$cols;$y++) {
		print "";
		print "<td align=\"center\">
			<input type=\"hidden\" name=\"col[$y]\" value=\"".chr(96+$y)."\">\n
			<input style=\"width:20px;text-align:center;\" type=\"text\" name=\"flyt_col[$y]\" value=\"".chr(96+$y)."\" onchange= \"this.form.submit()\">
			</td>\n";
	}
	print "</form>\n";
	print "</tr>\n";
	$a=0;
	print "<form name=\"flyt_rows\" action=\"posmenuer.php?menu_id=$menu_id\" method=\"post\">\n";
	for ($x=1;$x<=$rows;$x++) {
		print "<tr><td>
			<input style=\"width:20px;text-align:center;\" type=\"text\" name=\"flyt_row[$x]\" value=\"$x\" onchange= \"this.form.submit()\">
			</td>";
		for ($y=1;$y<=$cols;$y++) {
#cho "select * from pos_buttons where menu_id='$menu_id' and row='$x' and col='$y'<br>";
			$r=db_fetch_array(db_select("select * from pos_buttons where menu_id='$menu_id' and row='$x' and col='$y'"));
#			$a=str_replace("\n","<br>",$r['beskrivelse']);
			if ($menu_id) $a=$r['beskrivelse'];
			else $a++;
			$b=$r['color'];
			$c=$r['vare_id']*1;
			$d=$r['funktion']*1;
			if ($d==1 && $c) {
				$r=db_fetch_array(db_select("select varenr from varer where id='$c' and lukket !='on'"));
				$c=$r['varenr'];
			}
			if (!$c) $c='';
#			$fontsize=$height*0.7;	
			$style="
				display: table-cell;
				moz-border-radius:".$radius."px;
				-webkit-border-radius:".$radius."px;
				width:".$width."px;
				height:".$height."px;
				text-align:center;
				vertical-align:middle;
				font-size:".$fontsize."px; 
				border: 1px solid #$bgcolor2;
				white-space: normal;
				background-color:#$b;
			";
/*
			if (!$menu_id) {
				$a=$y;
				if ($x>1) $a+=3;
				if ($x>2) $a+=3;
			}
*/			
#			style=\"width:".$width."px;height:".$height."px;text-align:center;font-size:".$fontsize."px; background-color:#$b;\"
			$a=str_replace(" på beløb","\npå beløb",$a);
			$a=str_replace('<br>','&#x00A;',$a);
			print "<td><a href=\"posmenuer.php?menu_id=$menu_id&ret_row=$x&ret_col=$y\" style=\"text-decoration: none\"><input type=\"button\" style=\"$style\" value= \"$a\"></a></td>";
#&buttxt=$a&butcolor=$b&butvnr=$c&butfunc=$d
#			print "<td style=\"width:".$width."px;height:".$height."px;text-align:center;font-size:".$fontsize."px;\" bgcolor=\"#$b\">$a</td>";
		}
		print "</tr>";
	}
}
print "<script language=\"javascript\">document.posmenuer.buttxt.focus();</script>";
?>
