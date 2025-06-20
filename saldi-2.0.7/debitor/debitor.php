<?php

// ------------debitor/debitor.php-----lap 2.0.5----2009.02.24--------------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

	
@session_start();
$s_id=session_id();

$vis_alt=0; $vis_liste="0";

$title="Debitorer";
$modulnr=6;	
$css="../css/standard.css";
$antal=0;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";
if (isset($_GET['sort'])) $sort = $_GET['sort'];
if (isset($_GET['ny_sort'])) $ny_sort = $_GET['ny_sort'];
if ($sort && $sort==$ny_sort) $sort=$sort." desc";
elseif ($ny_sort) {$sort=$ny_sort; $nysort='';}
if (!$sort) $sort = 'firmanavn';
#print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>SALDI - Debitorliste</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

$r=db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
$jobkort=$r['box7'];
$width=34;
if ($jobkort) $width.=-4;
if ($pbs) $width.=2;
print "<div align=\"center\">";
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"8%\" $top_bund><a href=$returside accesskey=L>Luk</a></td>";
print "<td width=\"$width%\" $top_bund><br></td>";
print "<td width=\"8%\" $knap_ind>Debitorer</a></td>";
print "<td width=\"8%\" $top_bund ><a href=historik.php title =\"Klik her for at skifte til historikoversigten\">Historik</a></td>";
if ($jobkort) print "<td width=\"8%\" $top_bund ><a href=jobliste.php title =\"Klik her for at skifte til joblisten\">".findtekst(38,$sprog_id)."</a></td>";
print "<td width=\"$width%\" $top_bund><br></td>";
if ($popup) print "<td width=\"4%\" $top_bund onClick=\"javascript:debgrpvis=window.open('grpvisning.php?side=debitor','debgrpvis','scrollbars=1,resizable=1');debgrpvis.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"> <u>Visning</u></td>";
else print "<td width=\"4%\" $top_bund><a href=grpvisning.php?side=debitor>Visning</td>";
print "<td width=\"4%\" $top_bund><a href=debitorkort.php accesskey=N>Ny</a></td>";
print " </tr></tbody></table></td></tr>";
print "<tr><td valign=\"top\">";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\"><tbody>";

# brugernavn i box1 er en pre 2.0.7 ting - fremover skal der identificeres paa kodenr / bruger_id.
if ($r = db_fetch_array(db_select("select * from grupper where art = 'DGV' and (box1 = '$brugernavn' or kodenr = '$bruger_id')",__FILE__ . " linje " . __LINE__))) {
	$vis_liste=$r['box3'];
} else {
	db_modify("insert into grupper(beskrivelse, art, kode, kodenr)values('debitorvisning', 'DGV', '$brugernavn','$bruger_id')",__FILE__ . " linje " . __LINE__);
#	$vis_liste='1';
}
if (!strstr($vis_liste,"1")) $vis_alt=1; #strpos ser ikke hvis 1. ciffer = 1.
$q0 = db_select("select * from grupper where art = 'DG' order by beskrivelse",__FILE__ . " linje " . __LINE__);
$tmp=str_replace(" ","_",$sort);
$x=-1;
while ($r0 = db_fetch_array($q0)) {
	$x++;
	if (substr($vis_liste,$x,1)=='1' || ($vis_alt && !$x)) {
		if (!$vis_alt) {
			print "<tr><td colspan=3><b>$r0[beskrivelse]</b></td></tr>";	
			print "<tr><td colspan=10><hr></td></tr>";
		}
		print "<tr><td title=\"".findtekst(135,$sprog_id)."\"><b><a href=debitor.php?sort=$sort&ny_sort=kontonr>".findtekst(136,$sprog_id)."</b></td>";
		print "<td title=\"".findtekst(137,$sprog_id)."\"><b><a href=debitor.php?sort=$sort&ny_sort=firmanavn>".findtekst(138,$sprog_id)."</a></b></td>";	
		print "<td title=\"".findtekst(139,$sprog_id)."\"><b><a href=debitor.php?sort=$sort&ny_sort=addr1>".findtekst(140,$sprog_id)."</a></b></td>";
		print "<td title=\"".findtekst(141,$sprog_id)."\"><b><a href=debitor.php?sort=$sort&ny_sort=addr2>".findtekst(142,$sprog_id)."</a></b></td>";
		print "<td title=\"".findtekst(143,$sprog_id)."\"><b><a href=debitor.php?sort=$sort&ny_sort=postnr>".findtekst(144,$sprog_id)."</a></b></td>";
		print "<td title=\"".findtekst(145,$sprog_id)."\"><b><a href=debitor.php?sort=$sort&ny_sort=bynavn>".findtekst(146,$sprog_id)."</a></b></td>";
		print "<td title=\"".findtekst(147,$sprog_id)."\"><b><a href=debitor.php?sort=$sort&ny_sort=kontakt>".findtekst(148,$sprog_id)."</a></b></td>";
		print "<td title=\"".findtekst(149,$sprog_id)."\"><b><a href=debitor.php?sort=$sort&ny_sort=tlf>".findtekst(150,$sprog_id)."</a></b></td>";
		print "<td title=\"".findtekst(151,$sprog_id)."\"><b><a href=debitor.php?sort=$sort&ny_sort=kontoansvarlig>".findtekst(152,$sprog_id)."</a></b></td>";
		print "</tr>";
		if (!$vis_alt) $gruppe="and adresser.gruppe=$r0[kodenr]";	
		if ($sort) {
			if ($sort=='kontonr' || $sort=='kontonr desc') {
				if ($db_type=='mysql') $sort=str_replace("kontonr","CAST(kontonr AS SIGNED)",$sort); 
				else $sort=str_replace("kontonr","to_number(textcat('0',kontonr),text(99999999))",$sort);
			}
			if ($sort=='initialer' || $sort=='initialer desc') $q1 = db_select("select adresser.* from adresser, ansatte where adresser.art = 'D' $gruppe and ansatte.konto_id=adresser.id order by ansatte.$sort",__FILE__ . " linje " . __LINE__);
			else $q1 = db_select("select * from adresser where art = 'D' $gruppe order by $sort",__FILE__ . " linje " . __LINE__);
		} else $q1 = db_select("select * from adresser where art = 'D' $gruppe order by kontaktes, firmanavn",__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			$antal++;
			if ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
			else {$linjebg=$bgcolor; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\">";
			if ($popup) { 
				$href="onClick=\"javascript:debitorkort=window.open('debitorkort.php?id=$r1[id]&returside=../includes/luk.php','debitorkort','".$jsvars."');debitorkort.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\";";
				print "<td $href><u>$r1[kontonr]</u><br></td>";
			} else print "<td  title=\"".findtekst(153,$sprog_id)."\"><a href=debitorkort.php?id=$r1[id]>$r1[kontonr]</a><br></td>";
			print "<td>".htmlentities($r1['firmanavn'],ENT_COMPAT,$charset)."<br></td>";
			print "<td>".htmlentities($r1['addr1'],ENT_COMPAT,$charset)."<br></td>";
			print "<td>".htmlentities($r1['addr2'],ENT_COMPAT,$charset)."<br></td>";
			print "<td>".htmlentities($r1['postnr'],ENT_COMPAT,$charset)."<br></td>";
			print "<td>".htmlentities($r1['bynavn'],ENT_COMPAT,$charset)."<br></td>";
			print "<td>".htmlentities($r1['kontakt'],ENT_COMPAT,$charset)."<br></td>";
			print "<td>".htmlentities($r1['tlf'],ENT_COMPAT,$charset)."<br></td>";
			$tmp=$r1['kontoansvarlig']*1;
			$r=db_fetch_array(db_select("select initialer from ansatte where id = '$tmp'",__FILE__ . " linje " . __LINE__));
			print "<td>".htmlentities($r['initialer'],ENT_COMPAT,$charset)."<br></td>";
			print "</tr>";
		}
		print "<tr><td><br></td></tr><tr><td><br></td></tr>";
	}
}
if (!$antal) {
	print "<tr><td>TIP 1:   Du opretter en ny kunde ved at klikke p&aring; Ny &oslash;verst til h&oslash;jre.</td></tr>";
	print "<tr><td>TIP 2:   Du v&aelig;lger hvilke kundegrupper, som skal v&aelig;re synlige, ved at klikke p&aring; ";
	print "Visning &oslash;verst til h&oslash;jre.</td></tr>";
}
print "</tbody></table></td></tr>";

?>
</tbody></table>

</body></html>
