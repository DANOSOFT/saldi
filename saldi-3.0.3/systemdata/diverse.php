<?php
// -------------------- systemdata/diverse.php ------ patch 2.1.8--2010-04-16--------
// LICENS..
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$title="Diverse Indstilinger";
$modulnr=1;
$css="../css/standard.css";

$diffkto=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("top.php");

$sektion=if_isset($_GET['sektion']);
$skiftnavn=if_isset($_GET['skiftnavn']);

if ($_POST) {
	if ($sektion=='provision') {
		$id=$_POST['id'];
		$beskrivelse=$_POST['beskrivelse'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3'];
		$box4=$_POST['box4'];
		if (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='1'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
		db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4) values ('Provisionsrapport', '1', 'DIV', '$box1', '$box2', '$box3', '$box4')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) db_modify("update grupper set  box1 = '$box1', box2 = '$box2', box3 = '$box3' , box4 = '$box4' where id = '$id'",__FILE__ . " linje " . __LINE__);
	#######################################################################################
	} elseif ($sektion=='personlige_valg') {
		$refresh_opener=NULL;
		$id=$_POST['id'];
		$jsvars=$_POST['jsvars'];
		if ($popup && $_POST['popup']=='') $refresh_opener="on";
		$popup=$_POST['popup'];
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'USET' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
			db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2) values ('Personlige valg', '$bruger_id', 'USET', '$jsvars', '$popup')",__FILE__ . " linje " . __LINE__);
		} elseif ($id>0) db_modify("update grupper set  box1='$jsvars',box2='$popup' where id = '$id'",__FILE__ . " linje " . __LINE__);
		if ($refresh_opener) {
			print "<BODY onLoad=\"javascript:opener.location.reload();\">";
		}
	#######################################################################################
	} elseif ($sektion=='div_valg') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3']; #ledig
		$box4=$_POST['box4']; #ledig
		$box5=$_POST['box5']; #ledig
		$box6=$_POST['box6'];
		$box7=$_POST['box7'];
		$box8=$_POST['box8']; #ledig
		$box9=$_POST['box9']; #ledig
		$box10=$_POST['box10'];
		
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='2'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('Div_valg','2','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='ordre_valg') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3']; 
		$box4=$_POST['box4']; 
		$box5=$_POST['box5']; 
		$box6=$_POST['box6'];
		$box7=$_POST['box7'];
		$box8=$_POST['box8']; 
		$box9=$_POST['box9']; 
		$box10=$_POST['box10'];
		
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('Div_valg','3','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='rykker_valg') {
		$id=if_isset($_POST['id']);
		$box1=if_isset($_POST['box1']);
		$box2=if_isset($_POST['box2']);
		$box3=if_isset($_POST['box3']); 
		$box4=if_isset($_POST['box4']); 
		$box5=if_isset($_POST['box5']); 
		$box6=if_isset($_POST['box6']);
		$box7=if_isset($_POST['box7']);
		# $box8 er reserveret til dato for sidst afsendte mail. 
		
		
		if ($box1) {
			$r = db_fetch_array(db_select("select id from brugere where brugernavn = '$box1'",__FILE__ . " linje " . __LINE__));
			$box1=$r['id'];
		}
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='4'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('Div_valg','4','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','','','')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='pos_valg') {
		$id=if_isset($_POST['id'])*1;
		$box1=if_isset($_POST['kasseantal'])*1;
		$afd_nr=if_isset($_POST['afd_nr']);
		$kassekonti=if_isset($_POST['kassekonti']);
		$box4=if_isset($_POST['kortantal'])*1;
		$korttyper=if_isset($_POST['korttyper']);
		$kortkonti=if_isset($_POST['kortkonti']);
		$moms_nr=if_isset($_POST['moms_nr']);
		$rabatvarenr=if_isset($_POST['rabatvarenr']);
		
		$box2=NULL;
		$box3=NULL;
		$box7=NULL;
		$box8=NULL;
		for ($x=0;$x<$box1;$x++) {
			if ($kassekonti[$x] && db_fetch_array(db_select("select id from kontoplan where kontonr = '$kassekonti[$x]'",__FILE__ . " linje " . __LINE__))) {
				if ($box2) {
					$box2.=chr(9).$kassekonti[$x];
					$box3.=chr(9).$afd_nr[$x];
					$box7.=chr(9).$moms_nr[$x];
					} else {
					$box2=$kassekonti[$x];
					$box3=$afd_nr[$x];
					$box7=$moms_nr[$x];
				}
			}	else {
				if ($kassekonti[$x]) $txt=str_replace("<variable>",$kassekonti[$x],findtekst(277,$sprog_id));
				else $txt = findtekst(278,$sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			}
		}
		$box5=NULL;
		$box6=NULL;
		for ($x=0;$x<$box4;$x++) {
			if ($korttyper[$x]) {
				if (!db_fetch_array(db_select("select id from kontoplan where kontonr = '$kortkonti[$x]'",__FILE__ . " linje " . __LINE__))) {
					if ($kortkonti[$x]) $txt=str_replace("<variable>",$kortkonti[$x],findtekst(277,$sprog_id));
					else $txt = findtekst(278,$sprog_id);
					print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
				} 
				if ($box5) {
					$box5.=chr(9).$korttyper[$x];
					$box6.=chr(9).$kortkonti[$x];
				} else {
					$box5=$korttyper[$x];
					$box6=$kortkonti[$x];
				}
			}
		}
		
		if ($rabatvarenr && $r=db_fetch_array(db_select("select id from varer where varenr = '$rabatvarenr'",__FILE__ . " linje " . __LINE__))) {
			$box8=$r['id'];
		} elseif ($rabatvarenr) {
				$txt = str_replace('XXXXX',$rabatvarenr,findtekst(289,$sprog_id));
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('POS_valg','1','POS','$box1','$box2','$box3','$box4','$box5','$box6','$box7','','','')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='docubizz') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3'];
		$box4=$_POST['box4'];
		$box5=$_POST['box5'];
		
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DocBiz'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5) values ('DocuBizz','1','DocBiz','$box1','$box2','$box3','$box4','$box5')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	} elseif ($sektion=='upload_dbz') {
		include("docubizzexport.php");
		$r = db_fetch_array(db_select("select * from grupper where art = 'DocBiz'",__FILE__ . " linje " . __LINE__));
		$kommando="cd ../temp/$db\n/usr/bin/ncftp ftp://".$r['box2'].":".$r['box3']."@".$r['box1']."/".$r['box5']." < ftpscript > NULL ";
		system ($kommando);
		print "<BODY onLoad=\"JavaScript:alert('Data sendt til DocuBizz')\">";
#######################################################################################
	} elseif ($sektion=='ftp') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3'];
		$box4=$_POST['box4'];
		$box5=$_POST['box5'];
		
		if ($box1 && $box2 && $box3 && $box4 && $box5) testftp($box1,$box2,$box3,$box4,$box5);
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper where art = 'FTP'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5) values ('FTP til bilag og dokumenter','1','FTP','$box1','$box2','$box3','$box4','$box5')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
#######################################################################################
	} elseif ($sektion=='email') {
		$id=$_POST['id'];
		$box1=addslashes($_POST['box1']);
		$box2=addslashes($_POST['box2']);
		$box3=addslashes($_POST['box3']);
		$box4=addslashes($_POST['box4']);
		$box5=addslashes($_POST['box5']);
		$box6=addslashes($_POST['box6']);
		$box7=addslashes($_POST['box7']);
		$box8=addslashes($_POST['box8']);
		$box9=addslashes($_POST['box9']);
		$box10=addslashes($_POST['box10']);
		
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper where art = 'MAIL' and kodenr = '1'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('e-mail tekster','1','MAIL','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
#######################################################################################
	} elseif ($sektion=='orediff') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2']*1;
		if ($box1) $box1=usdecimal($box1);
		if ($box2 && !db_fetch_array(db_select("select id from kontoplan where kontonr = '$box2' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))){
			$tekst=findtekst(175,$sprog_id);	
			print "<BODY onLoad=\"JavaScript:alert('$tekst')\">";
			$diffkto=$box2;
			$box2='';
		}
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2) values ('Oredifferencer','1','OreDif','$box1','$box2')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
######################################################################################
	} elseif ($sektion=='massefakt') {
		$id=$_POST['id'];
		$brug_mfakt=$_POST['brug_mfakt'];
		if ($brug_mfakt) {
			$brug_dellev=$_POST['brug_dellev'];
			$levfrist=$_POST['levfrist'];
		} else {
			$brug_dellev=NULL;
			$levfrist=0;
		}
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper where art = 'MFAKT'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3) values ('Massefakturering','1','MFAKT','$brug_mfakt','$brug_dellev','$levfrist')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$brug_mfakt',box2='$brug_dellev',box3='$levfrist' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
######################################################################################
	} elseif ($sektion=='kontoplan_io') {
		if (strstr($_POST['submit'])=="Eksport") {
			list($tmp)=split(":",$_POST['regnskabsaar']);
			print "<BODY onLoad=\"javascript:exporter_kontoplan=window.open('exporter_kontoplan.php?aar=$tmp','lager','scrollbars=yes,resizable=yes,dependent=yes');exporter_kontoplan.focus();\">";				
		}
		elseif (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:importer_kontoplan=window.open('importer_kontoplan.php','kontoplan','scrollbars=yes,resizable=yes,dependent=yes');importer_kontoplan.focus();\">";				
		}
	} elseif ($sektion=='debitor_io') {
		if (strstr($_POST['submit'])=="Eksport") {
			print "<BODY onLoad=\"javascript:exporter_debitor=window.open('exporter_debitor.php?aar=$tmp','debitor','scrollbars=yes,resizable=yes,dependent=yes');exporter_debitor.focus();\">";				
		}
		elseif (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:importer_debitor=window.open('importer_debitor.php','debitor','scrollbars=yes,resizable=yes,dependent=yes');importer_debitor.focus();\">";
		}
	} elseif ($sektion=='regnskabsnavn') {
		if (strstr($_POST[submit],'Skift')) {
			$nyt_navn=trim(addslashes($_POST['nyt_navn']));
			include("../includes/connect.php");
			if (db_fetch_array(db_select("select id from regnskab where regnskab = '$nyt_navn'",__FILE__ . " linje " . __LINE__))) {
				print "<BODY onLoad=\"JavaScript:alert('Der findes allerede et regnskab med navnet $nyt_navn! Navn ikke &aelig;ndret')\">";
			} else {
				$r=db_fetch_array(db_select("select id from kundedata where regnskab_id = '$db_id'"));
				if (!$r['id']){
					$tmp=addslashes($regnskab);	
					db_modify("update kundedata set regnskab_id = '$db_id' where regnskab='$tmp'",__FILE__ . " linje " . __LINE__);
				}
				db_modify("update regnskab set regnskab = '$nyt_navn' where db='$db'",__FILE__ . " linje " . __LINE__);
			
			}
			include("../includes/online.php");
		}
	}
}
if(db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box6='on'",__FILE__ . " linje " . __LINE__))) $docubizz='on';

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" height=\"100%\"><tbody>";

print "<td width=\"170px\" valign=\"top\">";
print "<table cellpadding=\"2\" cellspacing=\"2\" border=\"0\" width=\"100%\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\"><br></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=regnskabsnavn>Skift navn p&aring; regnskab</a></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=provision>Provisionsberegning</a>&nbsp;</td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=personlige_valg>Personlige valg</a></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=ordre_valg>Ordrerelaterede valg</a></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=rykker_valg>Rykkerrelaterede valg</a></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=div_valg>Diverse valg</a></td></tr>";
if ($docubizz) print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=docubizz>DocuBizz</a></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=ftp>FTP info</a></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=orediff>".findtekst(170,$sprog_id)."</a></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=massefakt>".findtekst(200,$sprog_id)."</a></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=pos_valg>".findtekst(271,$sprog_id)."</a></td></tr>";
# print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=email>Mail indstillinger</a></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=sprog>Sprog</a></td></tr>";
# print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=kontoplan_io>Indl&aelig;s  / udl&aelig;s kontoplan</a></td></tr>";
print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=div_io>Import &amp; eksport</a></td></tr>";
print "</tbody></table></td><td valign=\"top\" align=\"left\"><table align=\"left\" valign=\"top\" width=90%><tbody>";


if ($sektion=="regnskabsnavn") regnskabsnavn($regnskab,$skiftnavn);
if ($sektion=="provision") provision();
if ($sektion=="personlige_valg") personlige_valg();
if ($sektion=="ordre_valg") ordre_valg();
if ($sektion=="rykker_valg") rykker_valg();
if ($sektion=="div_valg") div_valg();
if ($sektion=="docubizz") docubizz();
if ($sektion=="ftp") ftp();
if ($sektion=="orediff") orediff($diffkto);
if ($sektion=="massefakt") massefakt();
if ($sektion=="pos_valg") pos_valg();
if ($sektion=="sprog") sprog();
if (strpos($sektion,"_io")) {
	kontoplan_io();
	debitor_io();
}

print "</tbody></table></td></tr>";

#print "</form>";
#print "</tbody></table></td></tr>";

function regnskabsnavn($regnskab,$skiftnavn) 
{
	global $bgcolor;
	global $bgcolor5;

	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Skift navn p&aring; regnskab</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	if (!$skiftnavn) {
		print "<tr><td colspan=6>Dit regnskab hedder: \"$regnskab\". Klik <a href=diverse.php?sektion=regnskabsnavn&skiftnavn=ja>her</a> for at &aelig;ndre navnet.</td></tr>";
	} else  {
		print "<form name=diverse action=diverse.php?sektion=regnskabsnavn method=post>"; 
		print "<tr><td colspan=6>Skriv nyt navn p&aring; regnskab <input type = text size=\"40\" name=\"nyt_navn\" value=\"$regnskab\"> og klik <input type=submit value=\"Skift&nbsp;navn\" name=\"submit\"></td></tr>";
		print "</form>";
	}
	print "<tr><td colspan=6><br></td></tr>";
}

function provision() 
{
	global $bgcolor;
	global $bgcolor5;
	
	$bet=NULL; $ref=NULL; $kua=NULL; $smart=NULL;
	$kort=NULL; $batch=NULL;
	
	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse'];
	$kodenr=$r['kodenr'];
	$box1=$r['box1'];
	$box2=$r['box2'];
	$box3=$r['box3'];
	$box4=$r['box4'];

	if ($box1=='ref') $ref="checked";
	elseif ($box1=='kua') $kua="checked";
	else $smart="checked";

	if ($box2=='kort') $kort="checked";
	else $batch="checked";

	if ($box4=='bet') $bet="checked";
	else $fak="checked";

	print "<form name=diverse action=diverse.php?sektion=provision method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Grundlag for provisionsberegning</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td>Beregn provision p&aring; ordrer som er faktureret eller faktureret og betalt</td><td></td><td align=center>Faktureret</td><td align=center>Betalt</td></tr>";
	print "<tr><td></td><td></td><td align=center><input type=radio name=box4 value=fak title='Provision beregnes p&aring; fakturerede ordrer' $fak></td><td align=center><input type=radio name=box4 value=bet title= 'Provision beregnes p&aring; betalte ordrer' $bet></td></tr>";
	print "<tr><td>Kilde for personinfo</td><td align=center>Ref.</td><td align=center>Kundeans.</td><td align=center>Begge</td></tr>";
	print "<tr><td></td><td align=center><input type=radio name=box1 value=ref title='Provision tilfalder den der er angivet som referenceperson p&aring; de enkelte ordrer' $ref></td><td align=center><input type=radio name=box1 value=kua title= 'Provision tilfalder den kundeansvarlige' $kua></td><td align=center><input type=radio name=box1 value=smart title='Provision tilfalder den kundeansvarlige s&aring;fremt der er tildelt en s&aring;dan, ellers til den som er referenceperson p&aring; de enkelte ordrer' $smart></td></tr>";
	print "<tr><td>Kilde for kostpris</td><td></td><td align=center>Indk&oslash;bspris</td><td align=center>Varekort</td></tr>";
	print "<tr><td></td><td></td><td align=center><input type=radio name=box2 value=batch title='Anvend varens reelle indk&oslash;bspris som kostpris.' $batch></td><td align=center><input type=radio name=box2 value=kort title='Anvend kostpris fra varekort.' $kort></td></tr>";
	print "<tr><td>Sk&aelig;ringsdato for provisionsberegning</td><td></td><td></td><td align=center><SELECT NAME=box3 title='Dato hvorfra og med (i foreg&aring;ende m&aring;ned) til (dato i indev&aelig;rende m&aring;ned)provisionsberegning foretages'>";
	if ($box3) print"<option>$box3</option>";
	for ($x=1; $x<=28; $x++) { 
		print "<option>$x</option>";
	}
	print "</SELECT></td></tr>";;
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc provision

function kontoplan_io() 
{
	global $bgcolor;
	global $bgcolor5;
	
	$x=0;
	$q = db_select("select * from grupper where art = 'RA' order by  kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
	}
	$antal_regnskabsaar=$x;
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Indl&aelig;s/udl&aelig;s kontoplan</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	if ($popup) {
		print "<form name=diverse action=diverse.php?sektion=kontoplan_io method=post>";
		print "<tr><td colspan=2>Eksport&eacute;r kontoplan</td>\n";
		print "<td align=center><SELECT NAME=regnskabsaar title='V&aelig;lg det regnskabs&aring;r hvor kontoplanen skal eksporteres fra'>";
#		if ($box3[$x]) print"\t<option>$box3[$x]</option>";
		for ($x=1; $x<=$antal_regnskabsaar; $x++) { 
			print "\t<option>$kodenr[$x] : $beskrivelse[$x]</option>";
		}
		print "</select></td>";;
		print "<td align = center><input type=submit style=\"width: 8em\" accesskey=\"e\" value=\"Eksport&eacute;r\" name=\"submit\"></td><tr>";
		print "<tr><td colspan=3>Import&eacute;r kontoplan (erstatter kontoplanen for nyeste regnskabs&aring;r) </td>";
		print "<td align = center><input type=submit style=\"width: 8em\" accesskey=\"i\" value=\"Import&eacute;r\" name=\"submit\"></td><tr>";
		print "</form>";
	} else {
		print "<tr><td colspan=3>Eksport&eacute;r kontoplan</td><td align=center title='V&aelig;lg det regnskabs&aring;r hvor kontoplanen skal eksporteres fra'>";
#		if ($box3[$x]) {
#			print "<form form name=exporter$kodenr[$x] action=\"exporter_kontoplan.php?aar=$box3[$x]\" method=\"post\">\n";
#			print"<input type=\"submit\" style=\"width: 8em\" value=\"$box3[$x]\"><br>\n";
#			print "</form>\n";
#		}
		for ($x=1; $x<=$antal_regnskabsaar; $x++) {
			print ""; 
			print "<form name=exporter$kodenr[$x] action=exporter_kontoplan.php?aar=$kodenr[$x] method=post><input type=\"submit\" style=\"width: 8em\" value=\"$beskrivelse[$x]\"></form>\n";
		}	print "";
		print "</td></tr>\n\n";
		print "<tr><td colspan=3>Import&eacute;r kontoplan (erstatter kontoplanen for nyeste regnskabs&aring;r) </td>";
		print "<td align = center><form action=\"importer_kontoplan.php\"><input type=\"submit\" style=\"width: 8em\" value=\"Import&eacute;r\" accesskey=\"i\"></form></td><tr>";
#		print "<td align = center><a href=\"importer_kontoplan.php\" style=\"text-decoration:none\" accesskey=\"i\">Import&eacute;r</a></td><tr>";
	}
#	print "</tbody></table></td></tr>";

} # endfunc kontoplan_io

function kreditor_io() 
{
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	print "<form name=diverse action=diverse.php?sektion=kreditor_io method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Indl&aelig;s/udl&aelig;s kreditorer</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<tr><td colspan=3>Eksport&eacute;r kreditorer</td>";
	if ($popup) print "<td align = center><input type=submit accesskey=\"e\" value=\"Eksport&eacute;r\" name=\"submit\"></td><tr>\n\n";
	else print "<td align = center><a href=\"exporter_kreditor.php\" style=\"text-decoration:none\"><input type=\"button\" style=\"width: 8em\"  value=\"Eksport&eacute;r\"></a></td></tr>\n\n";
	print "<tr><td colspan=3>Import&eacute;r kreditorer </td>\n";
	if ($popup) print "<td align = center><input type=submit accesskey=\"i\" value=\"Import&eacute;r\" name=\"submit\"></td><tr>\n\n";
	else print "<td align = center><a href=\"importer_kreditor.php\" style=\"text-decoration:none\"><input type=\"button\"  style=\"width: 8em\" value=\"Import&eacute;r\"></a></td></tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";

} # endfunc kreditor_io
function debitor_io() 
{
	global $bgcolor;
	global $bgcolor5;
	
	$x=0;
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Indl&aelig;s/udl&aelig;s debitorer</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<tr><td colspan=3>Eksport&eacute;r debitorer</td>";
	if ($popup) {
		print "<form name=diverse action=diverse.php?sektion=debitor_io method=post>";
		print "<td align = center><input type=submit accesskey=\"e\" style=\"width: 8em\" value=\"Eksport&eacute;r\" name=\"submit\"></td><tr>";
		print "<tr><td colspan=3>Import&eacute;r debitorer</td>";
		print "<td align = center><input type=submit accesskey=\"i\" style=\"width: 8em\" value=\"Import&eacute;r\" name=\"submit\"></td><tr>";
		print "</form>";
	} else {
		print "<td align = center><form name=impdeb action=\"exporter_debitor.php\"><input type=\"submit\" style=\"width: 8em\" value=\"Eksport&eacute;r\"></form></td></tr>\n\n";
		print "<tr><td colspan=3>Import&eacute;r debitorer</td>";
		print "<td align = center><form name=expdeb action=\"importer_debitor.php\"><input type=\"submit\" style=\"width: 8em\" value=\"Import&eacute;r\"></form></td></tr>\n\n";
	}
#	print "</tbody></table></td></tr>";

} # endfunc debitor_io

function sprog () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	$q = db_select("select * from grupper where art = 'SPROG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
		$sprogkode[$x]=$r['box1'];
	}
	$antal_sprog=$x;
	print "<form name=diverse action=diverse.php?sektion=sprog method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Sprog</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	$tekst1=findtekst(1,$sprog_id);
	$tekst2=findtekst(2,$sprog_id);	
	print "<tr><td title=\"Klik her for at rette tekster\"><a href=tekster.php?sprog_id=1>$tekst1</a></td><td><SELECT NAME=sprog title='$tekst2'>";
	if ($box3[$x]) print"<option>$box3[$x]</option>";
	for ($x=1; $x<=$antal_sprog; $x++) { 
		print "<option>$beskrivelse[$x]</option>";
	}
	print "</SELECT></td></tr>";
	print "<tr><td><br></td></tr>";
	$tekst1=findtekst(3,$sprog_id);
	print "<tr><td align = right colspan=4><input type=submit value=\"$tekst1\" name=\"submit\"></td></tr>";
#	print "<td align = center><input type=submit value=\"$tekst2\" name=\"submit\"></td>";
#	print "<td align = center><input type=submit value=\"$tekst3\" name=\"submit\"></td><tr>";
/*	
	print "</tbody></table></td></tr>";
*/
	print "</form>";
} # endfunc sprog

function jobkort () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	$q = db_select("select * from grupper where art = 'JOBKORT' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
		$sprogkode[$x]=$r['box1'];
	}
	$antal_sprog=$x;
	print "<form name=diverse action=diverse.php?sektion=sprog method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Sprog</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	$tekst1=findtekst(1,$sprog_id);
	$tekst2=findtekst(2,$sprog_id);	
	print "<tr><td>	$tekst1</td><td><SELECT NAME=sprog title='$tekst2'>";
	if ($box3[$x]) print"<option>$box3[$x]</option>";
	for ($x=1; $x<=$antal_sprog; $x++) { 
		print "<option>$beskrivelse[$x]</option>";
	}
	print "</SELECT></td></tr>";
	print "<tr><td><br></td></tr>";
	$tekst1=findtekst(3,$sprog_id);
	print "<tr><td align = right colspan=4><input type=submit value=\"$tekst1\" name=\"submit\"></td></tr>";
#	print "<td align = center><input type=submit value=\"$tekst2\" name=\"submit\"></td>";
#	print "<td align = center><input type=submit value=\"$tekst3\" name=\"submit\"></td><tr>";
/*	
	print "</tbody></table></td></tr>";
*/
	print "</form>";

} # endfunc sprog


function personlige_valg() 
{
	global $sprog_id;
	global $popup;
	global $bruger_id;
	global $bgcolor;
	global $bgcolor5;
	
	$r = db_fetch_array(db_select("select * from grupper where art = 'USET' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$jsvars=$r['box1'];
	if ($r['box2']) $popup='checked';

	print "<form name=personlige_valg action=diverse.php?sektion=personlige_valg&popup=$popup method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Personlige valg</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
#	print "<input type=hidden name=id value='$id'>";
	
	print "<tr><td title=\"".findtekst(207,$sprog_id)."\">".findtekst(208,$sprog_id)."</td><td><input type=checkbox name=popup $popup></td></tr>";
	print "<tr><td title=\"".findtekst(209,$sprog_id)."\">".findtekst(210,$sprog_id)."</td><td colspan=4><input type=text size=60 name=jsvars value=\"$jsvars\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc personlige_valg
function div_valg() 
{
	global $sprog_id;
	global $docubizz;
	global $bgcolor;
	global $bgcolor5;
	
	$gruppevalg=NULL;$kuansvalg=NULL;
	$ref=NULL; $kua=NULL; $smart=NULL;
	$jobkort=NULL; $kort=NULL; $batch=NULL; 
			
	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse']; $kodenr=$r['kodenr'];	$box1=$r['box1'];	 $box2=$r['box2']; $box3=$r['box3']; $box4=$r['box4']; $box5=$r['box5']; $box6=$r['box6'];$box7=$r['box7'];$box8=$r['box8'];$box9=$r['box9'];$box10=$r['box10'];
	if ($box1=='on') $gruppevalg="checked"; if ($box2=='on') $kuansvalg="checked"; if ($box3=='on') $ledig="checked"; 
	if ($box4=='on') $ledig="checked";	if ($box5=='on') $ledig="checked";	if ($box6=='on') $docubizz="checked";
	if ($box7=='on') $jobkort="checked";if ($box8=='on') $ledig="checked";if ($box9=='on') $ledig="checked";
	if ($box10=='on') $betalingsliste="checked";

	print "<form name=diverse action=diverse.php?sektion=div_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Diverse valg</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(186,$sprog_id)."\">".findtekst(162,$sprog_id)."</td><td><input type=checkbox name=box1 $gruppevalg></td></tr>";
#	print "<td title=\"".findtekst(211,$sprog_id)."\">".findtekst(212,$sprog_id)."</td><td><input type=checkbox name=box7 $jobkort></td></tr>";
	print "<tr><td title=\"".findtekst(187,$sprog_id)."\">".findtekst(163,$sprog_id)."</td><td><input type=checkbox name=box2 $kuansvalg></td></tr>";
	print "<tr><td title=\"".findtekst(185,$sprog_id)."\">".findtekst(184,$sprog_id)."</td><td title=\"".findtekst(185,$sprog_id)."\"><input type=checkbox name=box10 $betalingsliste></td></tr>";
	print "<tr><td title=\"".findtekst(193,$sprog_id)."\">".findtekst(167,$sprog_id)."</td><td><input type=checkbox name=box6 $docubizz></td></tr>";
	print "<tr><td title=\"".findtekst(194,$sprog_id)."\">".findtekst(168,$sprog_id)."</td><td><input type=checkbox name=box7 $jobkort></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc div_valg

function ordre_valg() 
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	
	$hurtigfakt=NULL; $incl_moms=NULL; $folge_s_tekst=NULL; $negativt_lager=NULL; $straks_bogf=NULL; $vis_nul_lev=NULL;
			
	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse']; $kodenr=$r['kodenr'];	$box1=$r['box1'];	 $box2=$r['box2']; $box3=$r['box3']; $box4=$r['box4']; $box5=$r['box5']; $box6=$r['box6'];$box7=$r['box7'];$box8=$r['box8'];$box9=$r['box9'];$box10=$r['box10'];
	if ($box1=='on') $incl_moms="checked"; if ($box2=='on') $ledig="checked"; if ($box3=='on') $folge_s_tekst="checked"; 
	if ($box4=='on') $hurtigfakt="checked";	if ($box5=='on') $straks_bogf="checked";	if ($box6=='on') $ledig="checked";
	if ($box7=='on') $ledig="checked";if ($box8=='on') $vis_nul_lev="checked";if ($box9=='on') $negativt_lager="checked";
	if ($box10=='on') $ledig="checked";
	
	print "<form name=diverse action=diverse.php?sektion=ordre_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Ordrerelaterede valg</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(197,$sprog_id)."\">".findtekst(196,$sprog_id)."</td><td><input type=checkbox name=box1 $incl_moms></td></tr>";
	print "<tr><td title=\"".findtekst(188,$sprog_id)."\">".findtekst(164,$sprog_id)."</td><td><input type=checkbox name=box3 $folge_s_tekst></td></tr>";
	print "<tr><td title=\"".findtekst(189,$sprog_id)."\">".findtekst(169,$sprog_id)."</td><td><input type=checkbox name=box8 $vis_nul_lev></td></tr>";
	$r=db_fetch_array(db_select("select id from grupper where art = 'VG' and box9='on'",__FILE__ . " linje " . __LINE__));
	if ($r['id']) $hurtigfakt="onclick=\"return false\"";
	print "<tr><td title=\"".findtekst(190,$sprog_id)."\">".findtekst(165,$sprog_id)."</td><td title=\"".findtekst(254,$sprog_id)."\"><input type=\"checkbox\" name=\"box4\" $hurtigfakt></td></tr>";
	print "<tr><td title=\"".findtekst(191,$sprog_id)."\">".findtekst(166,$sprog_id)."</td><td><input type=checkbox name=box5 $straks_bogf></td></tr>";
	print "<tr><td title=\"".findtekst(192,$sprog_id)."\">".findtekst(183,$sprog_id)."</td><td><input type=checkbox name=box9 $negativt_lager></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc ordre_valg

function rykker_valg() 
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	
	$box1=NULL;$box2=NULL;$box3=NULL;$box4=NULL;$box5=NULL;$box6=NULL;$box7=NULL;$box8=NULL;$box9=NULL;
	
	$r = db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '4'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$box1=$r['box1'];	 $box2=$r['box2']; 
	if ($r['box3']) $box3=$r['box3']*1;
	$box4=$r['box4']; 
	if ($r['box5']) $box5=$r['box5']*1;
	if ($r['box6']) $box6=$r['box6']*1;
	if ($r['box7']) $box7=$r['box7']*1;
#	$box8=$r['box8']; Box 8 bruger til resistrering af sidst sendte reminder.
	$box9=$r['box9'];$box10=$r['box10'];

	$x=0;
	$q = db_select("select id,brugernavn from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$br_id[$x]=$r['id'];
		$br_navn[$x]=$r['brugernavn'];
		if ($box1==$br_id[$x]) $box1=$br_navn[$x];	
	}
	$br_antal=$x;
/*	
	if ($box3 || $box4) {
		if ($r=db_fetch_array(db_select("select beskrivelse from varer where varenr = '$box4'",__FILE__ . " linje " . __LINE__))) {
			$varetekst=htmlentities($r['beskrivelse']);
		} else print "<BODY onLoad=\"JavaScript:alert('Varenummer ikke gyldigt')\">";
	}
*/	
	print "<form name=diverse action=diverse.php?sektion=rykker_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Rykkerrelaterede valg</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	#Box1 Brugernavn for "rykkeransvarlig - Naar bruger logger ind adviseres hvis der skal rykkes - Hvis navn ikke angives adviseres alle..
		
	print "<tr><td title=\"".findtekst(224,$sprog_id)."\">".findtekst(225,$sprog_id)."</td>";
	print "<td title=\"".findtekst(224,$sprog_id)."\"><SELECT NAME=box1>";
	if ($box1) print "<option>$box1</option>";
	print "<option></option>";
	for ($x=1;$x<=$br_antal;$x++){
		if ($br_navn[$x]!=$box1) print "<option>$br_navn[$x]</option>";
	}
	print "</select></td></tr>";
	#Box2 Mailadresse for rykkeransvarlig hvis angivet sendes email naar der skal rykkes. (Naar nogen logger ind - uanset hvem)
	print "<tr><td title=\"".findtekst(226,$sprog_id)."\">".findtekst(227,$sprog_id)."</td><td><input type=text size=15 name=box2 value=\"$box2\"></td></tr>";
	#Box4 Varenummer for rente
#	print "<tr><td title=\"".findtekst(230,$sprog_id)."\">".findtekst(231,$sprog_id)."</td><td><input type=text size=15 name=box4 value=\"$box4\"></td></tr>";
	#Box3 Rentesats % pr paabegyndt md.
#	print "<tr><td title=\"".findtekst(228,$sprog_id)."\">".findtekst(229,$sprog_id)."</td><td><input type=text style=\"text-align:right\" size=1 name=box3 value=\"$box3\"> %</td></tr>";
	#Box5 Dage betalingsfrist skal vaere overskredet foer der rykkes.
	print "<tr><td title=\"".findtekst(232,$sprog_id)."\">".findtekst(233,$sprog_id)."</td><td><input type=text style=\"text-align:right\" size=1 name=box5 value=\"$box5\"> dage</td></tr>";
	#Box6 Dage fra rykker 1 til rykker 2
	print "<tr><td title=\"".findtekst(234,$sprog_id)."\">".findtekst(235,$sprog_id)."</td><td><input type=text style=\"text-align:right\" size=1 name=box6 value=\"$box6\"> dage</td></tr>";
	#Box7 Dage fra rykker 2 til rykker 3
	print "<tr><td title=\"".findtekst(236,$sprog_id)."\">".findtekst(237,$sprog_id)."</td><td><input type=text style=\"text-align:right\" size=1 name=box7 value=\"$box7\"> dage</td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc rykker_valg

function email() # Bruges ikke kan slettes 
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	
			
	$q = db_select("select * from grupper where art = 'MAIL' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse']; $tilbud_subj=$r['box1'];$tilbud_text=$r['box2'];$ordrebek_subj=$r['box3'];$ordrebek_text=$r['box4'];$faktura_subj=$r['box5'];$faktura_text=$r['box6'];$kn_subj=$r['box7'];$kn_text=$r['box8'];$rykker_subj=$r['box9'];$rykker_text=$r['box10'];

	print "<form name=diverse action=diverse.php?sektion=email method=post>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Mail indstillinger</u></b></td></tr>";
#	print "<tr><td colspan=6><table border=1><tbody>";
	print "<tr><td title=\"".findtekst(211,$sprog_id)."\">".findtekst(212,$sprog_id)."</td><td>".findtekst(219,$sprog_id)."</td><td	><input type=\"text\" size=\"40\" name=\"box1\" value=\"$tilbud_subj\"></td></tr>";
	print "<tr><td colspan=4><textarea name=\"box2\" rows=\"5\" cols=\"100\" onchange=\"javascript:docChange = true;\">$tilbud_text</textarea></td></tr>\n";
	print "<tr><td title=\"".findtekst(213,$sprog_id)."\">".findtekst(214,$sprog_id)."</td><td>".findtekst(219,$sprog_id)."</td><td	><input type=\"text\" size=\"40\" name=\"box3\" value=\"$ordrebek_subj\"></td></tr>";
	print "<tr><td colspan=4><textarea name=\"box4\" rows=\"5\" cols=\"100\" onchange=\"javascript:docChange = true;\">$ordrebek_text</textarea></td></tr>\n";
	print "<tr><td title=\"".findtekst(215,$sprog_id)."\">".findtekst(216,$sprog_id)."</td><td>".findtekst(219,$sprog_id)."</td><td	><input type=\"text\" size=\"40\" name=\"box5\" value=\"$faktura_subj\"></td></tr>";
	print "<tr><td colspan=3><textarea name=\"box6\" rows=\"5\" cols=\"100\" onchange=\"javascript:docChange = true;\">$faktura_text</textarea></td></tr>\n";
	print "<tr><td title=\"".findtekst(217,$sprog_id)."\">".findtekst(218,$sprog_id)."</td><td>".findtekst(219,$sprog_id)."</td><td	><input type=\"text\" size=\"40\" name=\"box7\" value=\"$kn_subj\"></td></tr>";
	print "<tr><td colspan=3><textarea name=\"box8\" rows=\"5\" cols=\"100\" onchange=\"javascript:docChange = true;\">$kn_text</textarea></td></tr>\n";
	print "<tr><td title=\"".findtekst(217,$sprog_id)."\">".findtekst(218,$sprog_id)."</td><td>".findtekst(219,$sprog_id)."</td><td	><input type=\"text\" size=\"40\" name=\"box9\" value=\"$rykker_subj\"></td></tr>";
	print "<tr><td colspan=3><textarea name=\"box10\" rows=\"5\" cols=\"100\" onchange=\"javascript:docChange = true;\">$rykker_text</textarea></td></tr>\n";
#	print "</tbody></table><td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc email
function docubizz() 

{
	global $bgcolor;
	global $bgcolor5;
	
	?>
	<script Language="JavaScript">
	<!--
	function Form1_Validator(docubizz) {
		if (docubizz.box3.value != docubizz.pw2.value) {
		alert("Begge adgangskoder skal v&aelig;re ens.");
		docubizz.box3.focus();
		return (false);
		}
	}
	//--></script>

	<?php
	$q = db_select("select * from grupper where art = 'DocBiz'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$ftpsted=$r['box1'];
	$ftplogin=$r['box2'];
	$ftpkode=$r['box3'];
	$ftp_dnld_mappe=$r['box4'];
	$ftp_upld_mappe=$r['box5'];
	
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>DocuBizz</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	
	print "<form name=docubizz action=diverse.php?sektion=docubizz method=post onsubmit=\"return Form1_Validator(this)\">";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td>Navn eller IP p&aring; ftpserver</td><td colspan=2><input type=text name=box1 size=20 value=\"$ftpsted\"></td></tr>";
	print "<tr><td>Mappe til download p&aring; ftpserver</td><td colspan=2><input type=text name=box4 size=20 value=\"$ftp_dnld_mappe\"></td></tr>";
	print "<tr><td>Mappe til upload p&aring; ftpserver</td><td colspan=2><input type=text name=box5 size=20 value=\"$ftp_upld_mappe\"></td></tr>";
	print "<tr><td>Brugernavn p&aring; ftpserver</td><td colspan=2><input type=text name=box2 size=20 value=\"$ftplogin\"></td></tr>";
	print "<tr><td>Adgangskode til ftpserver</td><td colspan=2><input type=password name=box3 size=20 value=\"$ftpkode\"></td></tr>";
	print "<tr><td>Gentag adgangskode</td><td colspan=2><input type=password name=pw2 size=20 value=\"$ftpkode\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td><td><br></td><td><br></td><td align = center><input style=\"width: 8em\" type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td><tr>";
	print "</form>";
	print "<form name=upload_dbz action=diverse.php?sektion=upload_dbz method=post>";
	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=3>Opdater Docubizz server</td><td align = center><input style=\"width: 8em\" type=submit accesskey=\"g\" value=\"Send data\" name=\"submit\"></td><tr>";
	print "</form>";
	
} # endfunc docubizz

function ftp() {
	global $bgcolor;
	global $bgcolor5;
	
	?>
	<script Language="JavaScript">
	<!--
	function Form1_Validator(ftp) {
		if (ftp.box3.value != ftp.pw2.value) {
		alert("Begge adgangskoder skal v&aelig;re ens.");
		ftp.box3.focus();
		return (false);
		}
	}
	//--></script>

	<?php
	$q = db_select("select * from grupper where art = 'FTP'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$ftpsted=$r['box1'];
	$ftplogin=$r['box2'];
	$ftpkode=$r['box3'];
	$ftp_bilag_mappe=$r['box4'];
	$ftp_dokument_mappe=$r['box5'];
	
	if (!$ftp_bilag_mappe) $ftp_bilag_mappe='bilag'; 
	if (!$ftp_dokument_mappe) $ftp_dokument_mappe='dokumenter'; 
	
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>FTP</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<tr><td colspan=6>Denne sektion indeholder de informationer, som er n&oslash;dvendige for at kunne h&aring;ndtere scannede bilag</td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<form name=ftp action=diverse.php?sektion=ftp method=post onsubmit=\"return Form1_Validator(this)\">";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td>Navn eller IP p&aring; ftpserver</td><td colspan=2><input type=text name=box1 size=20 value=\"$ftpsted\"></td></tr>";
	print "<tr><td>Brugernavn p&aring; ftpserver</td><td colspan=2><input type=text name=box2 size=20 value=\"$ftplogin\"></td></tr>";
	print "<tr><td>Adgangskode til ftpserver</td><td colspan=2><input type=password name=box3 size=20 value=\"$ftpkode\"></td></tr>";
	print "<tr><td>Gentag adgangskode</td><td colspan=2><input type=password name=pw2 size=20 value=\"$ftpkode\"></td></tr>";
	print "<tr><td>Mappe til bilag p&aring; ftpserver</td><td colspan=2><input type=text name=box4 size=20 value=\"$ftp_bilag_mappe\"></td></tr>";
	print "<tr><td>Mappe til dokumenter p&aring; ftpserver</td><td colspan=2><input type=text name=box5 size=20 value=\"$ftp_dokument_mappe\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td><td><br></td><td><br></td><td align = center><input style=\"width: 8em\" type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td><tr>";
	print "</form>";
} # endfunc ftp

function orediff($diffkto) 
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	
	$q = db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$maxdiff=dkdecimal($r['box1']);
	if (!$diffkto) $diffkto=$r['box2'];
	
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>".findtekst(170,$sprog_id)."</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	
	print "<form name=orediff action=diverse.php?sektion=orediff method=post onsubmit=\"return Form1_Validator(this)\">";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(171,$sprog_id)."\">".findtekst(172,$sprog_id)."</td><td colspan=2><input type=text style=\"text-align:right\" name=box1 size=3 value=\"$maxdiff\"></td></tr>";
	print "<tr><td title=\"".findtekst(173,$sprog_id)."\">".findtekst(174,$sprog_id)."</td><td colspan=2><input type=text style=\"text-align:right\" name=box2 size=3 value=\"$diffkto\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td><td><br></td><td><br></td><td align = center><input style=\"width: 8em\" type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td><tr>";
	print "</form>";
	
} # endfunc orediff.
function massefakt () {
	global $sprog_id;
	global $docubizz;
	global $bgcolor;
	global $bgcolor5;
	
	$folge_s_tekst=NULL;$gruppevalg=NULL;$kuansvalg=NULL;
	$ref=NULL; $kua=NULL; $smart=NULL;
	$kort=NULL; $batch=NULL; 
			
	$q = db_select("select * from grupper where art = 'MFAKT' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	if ($r['box1'] == 'on') $brug_mfakt='checked';
	else $brug_mfakt='';
	if ($r['box2'] == 'on') $brug_dellev='checked';
	else $brug_dellev='';
	$levfrist=$r['box3'];
		
	print "<form name=diverse action=diverse.php?sektion=massefakt method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>".findtekst(200,$sprog_id)."</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(202,$sprog_id)."\">".findtekst(201,$sprog_id)."</td><td><input type=checkbox name=brug_mfakt $brug_mfakt></td></tr>";
	print "<tr><td title=\"".findtekst(204,$sprog_id)."\">".findtekst(203,$sprog_id)."</td><td><input type=checkbox name=brug_dellev $brug_dellev></td></tr>";
	print "<tr><td title=\"".findtekst(206,$sprog_id)."\">".findtekst(205,$sprog_id)."</td><td><input type=text style=\"text-align:right\" name=levfrist size=1 value=\"$levfrist\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc massefakt
#####################################################
function pos_valg () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	
	$kassekonti=array();
	$afd=array();
	
	$q = db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$kasseantal=$r['box1']*1;
	$kassekonti=explode(chr(9),$r['box2']);
	$afd=explode(chr(9),$r['box3']);
	$kortantal=$r['box4']*1;
	$korttyper=explode(chr(9),$r['box5']);
	$kortkonti=explode(chr(9),$r['box6']);
	$moms=explode(chr(9),$r['box7']);
	$rabatvareid=$r['box8']*1;
	
	if ($rabatvareid) {
		$r = db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
		$rabatvarenr=$r['varenr'];
	}
	
	$x=0;
	if ($kasseantal) {
		$q = db_select("select * from grupper where art = 'AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$afd_nr[$x]=$r['kodenr'];
			$afd_navn[$x]=$r['beskrivelse'];
		}
		$afd_antal=$x;
		$x=0;
		$q = db_select("select * from grupper where art = 'SM' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$moms_nr[$x]=$r['kodenr'];
			$moms_navn[$x]=$r['beskrivelse'];
		}
		$moms_antal=$x;
	} 
	
	print "<form name=diverse action=diverse.php?sektion=pos_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>".findtekst(265,$sprog_id)."</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(266,$sprog_id)."\">".findtekst(267,$sprog_id)."</td><td><input type=\"text\" style=\"text-align:right\" size=\"1\" name=\"kasseantal\" value=\"$kasseantal\"></td></tr>";
#	print "<tr><td title=\"".findtekst(285,$sprog_id)."\">".findtekst(285,$sprog_id)."</td>";
	if ($kasseantal) {
		print "<tr><td title=\"".findtekst(288,$sprog_id)."\">".findtekst(287,$sprog_id)."</td><td><input type=\"text\" size=\"7\" name=\"rabatvarenr\" value=\"$rabatvarenr\"></td></tr>";
		print "<tr><td colspan=3><hr></td></tr>";
		print "<tr><td>".findtekst(272,$sprog_id)."</td>";
		if ($afd_antal) print "<td title=\"".findtekst(273,$sprog_id)."\">".findtekst(274,$sprog_id)."</td>";
		if ($moms_antal) print "<td title=\"".findtekst(285,$sprog_id)."\">".findtekst(286,$sprog_id)."</td>";
		print "<td title=\"".findtekst(275,$sprog_id)."\">".findtekst(276,$sprog_id)."</td></tr>";
		for($x=0;$x<$kasseantal;$x++) {
			print "<tr bgcolor=$bgcolor5>";
			$tmp=$x+1;
			print "<td>$tmp</td>";
			if ($afd_antal) {
				print "<td title=\"".findtekst(273,$sprog_id)."\"><SELECT NAME=afd_nr[$x] title=\"".findtekst(273,$sprog_id)."\">";
				for($y=1;$y<=$afd_antal;$y++) {
					if ($afd[$x]==$afd_nr[$y]) print "<option value=\"$afd_nr[$y]\">$afd_navn[$y]</option>";
				}
				print "<option value=\"0\"></option>";
				for($y=1;$y<=$afd_antal;$y++) {
					if ($afd[$x]!=$afd_nr[$y]) print "<option value=\"$afd_nr[$y]\">$afd_navn[$y]</option>";
				}
-				print "</SELECT></td>";;
			}
			if ($moms_antal) {
				print "<td title=\"".findtekst(273,$sprog_id)."\"><SELECT NAME=moms_nr[$x] title=\"".findtekst(273,$sprog_id)."\">";
				for($y=1;$y<=$moms_antal;$y++) {
					if ($moms[$x]==$moms_nr[$y]) print "<option value=\"$moms_nr[$y]\">$moms_navn[$y]</option>";
				}
				print "<option value=\"0\"></option>";
				for($y=1;$y<=$moms_antal;$y++) {
					if ($moms[$x]!=$moms_nr[$y]) print "<option value=\"$moms_nr[$y]\">$moms_navn[$y]</option>";
				}
-				print "</SELECT></td>";;
			}
			print "<td><input type=\"text\" style=\"text-align:right\" size=\"3\" name=\"kassekonti[$x]\" value=\"$kassekonti[$x]\"></td></tr>";
		}
	}
	print "<tr><td colspan=3><hr></td></tr>";
	print "<tr><td title=\"".findtekst(279,$sprog_id)."\">".findtekst(280,$sprog_id)."</td><td><input type=\"text\" style=\"text-align:right\" size=\"1\" name=\"kortantal\" value=\"$kortantal\"></td></tr>";
	if ($kortantal) {
		print "<tr><td></td><td title=\"".findtekst(281,$sprog_id)."\">".findtekst(283,$sprog_id)."</td>";
		print "<td title=\"".findtekst(282,$sprog_id)."\">".findtekst(284,$sprog_id)."</td></tr>";
		print "<tr><td colspan=3><hr></td></tr>";
		for($x=0;$x<$kortantal;$x++) {
			print "<tr bgcolor=$bgcolor5>";
			$tmp=$x+1;
			print "<td>$tmp</td>";
			print "<td><input type=\"text\" style=\"text-align:left\" size=\"15\" name=\"korttyper[$x]\" value=\"$korttyper[$x]\"></td>";
			print "<td><input type=\"text\" style=\"text-align:right\" size=\"3\" name=\"kortkonti[$x]\" value=\"$kortkonti[$x]\"></td></tr>";
		}
	}
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc pos
#####################################################
function testftp($box1,$box2,$box3,$box4,$box5) {
 	global $db;
	
	$fp=fopen("../temp/$db/testfil.txt","w");
	if ($fp) {
		fwrite ($fp, "testfil fra saldi\n");
	}
	fclose($fp);
	$fp=fopen("../temp/$db/ftpscript","w");
	if ($fp) {
		fwrite ($fp, "mkdir $box4\nmkdir $box5\ncd $box4\nput testfil.txt\nbye\n");
	}
	fclose($fp);
	$kommando="cd ../temp/$db\n/usr/bin/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript > ftplog\nrm testfil.txt\n";
	system ($kommando);
	$fp=fopen("../temp/$db/ftpscript","w");
	if ($fp) {
		fwrite ($fp, "get testfil.txt\ndel testfil.txt\nbye\n");
	}
	fclose($fp);
	$kommando="cd ../temp/$db\n/usr/bin/ncftp ftp://".$box2.":".$box3."@".$box1."/".$box4." < ftpscript > ftplog\nrm ftpscript\nrm ftplog";
	system ($kommando);
	if (file_exists("../temp/$db/testfil.txt")) print "<BODY onLoad=\"JavaScript:alert('FTP tilg&aelig;ngelig')\">";
	else print "<BODY onLoad=\"JavaScript:alert('FTP ikke tilg&aelig;ngelig')\">";
}

?>
</tbody></table>
</body></html>
