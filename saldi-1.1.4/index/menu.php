<?php
// ----------------------------------------index/menu.php-------------------Patch 1.1.9 --------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();	# Skal angives oeverst i filen??!!
$s_id=session_id();

$produktion=0; # Menukolonnen PRODUKTION udeladt indtil test er gennemført
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="refresh" content="3600;URL=menu.php" />
<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type" />
<title>SALDI - Hovedmenu</title>
</head>

<?php
if (isset($_GET['online'])) $online=$_GET['online'];
else $online=0;

$modulnr=0;
include("../includes/connect.php");
include("../includes/online.php");

$provision=0;
if (trim($ansat_id)) {
	$ansat_id=$ansat_id*1;
	$r = db_fetch_array(db_select("select * from ansatte where id = '$ansat_id'"));
	$provision = $r['provision'];
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td  $top_bund width=\"10%\">$font <small>Ver $version</small></td>";
print "<td  $top_bund width=\"35%\"><small>&nbsp;</small></td>";
print "<td  $top_bund width=\"10%\" align = \"center\">$font <a href=\"komigang.html\" target=\"blank\"><small>Vejledning</small></td>\n";
print "<td  $top_bund width=\"35%\"><small>&nbsp;</small></td>";
print "<td  $top_bund width=\"10%\" align = \"right\">$font <a href=logud.php accesskey=L><small>Log ud</small></td>\n";
print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"center\">\n";

print "<table align=\"center\" border=\"3\" cellspacing=\"2\" cellpadding=\"2\"><tbody>";
print "<tr><td background=\"../img/blaa2hvid_bg.gif\" colspan=\"5\" align=\"center\">$font<span style=\"color:$textcolor\"><big><big><big><b><i>= SALDI 2.0 BETA =</i></b></big></big></big></span></td></tr>\n";
print "<tr><td height=\"45\" colspan=\"5\" align=\"center\">$font <big><big><b>$regnskab</b></big></big></td></tr>\n";

print "<tr>";
print "<td height=\"35\" width=\"150\"  background=\"../img/blaa2hvid_bg.gif\" align=\"center\">$font <b>FINANS</b></td>";
print "<td width=\"150\"  background=\"../img/blaa2hvid_bg.gif\" align=\"center\">$font <b>DEBITOR</b></td>";
print "<td width=\"150\"  background=\"../img/blaa2hvid_bg.gif\" align=\"center\">$font <b>KREDITOR</b></td>";
if ($produktion) { # Udeladt indtil test gennemført.
    print "<td width=\"150\"  background=\"../img/blaa2hvid_bg.gif\" align=\"center\">$font <b>PRODUKTION</b></td>";
}
print "<td width=\"150\"  background=\"../img/blaa2hvid_bg.gif\" align=\"center\">$font <b>LAGER</b></td>";
print "<td width=\"150\"  background=\"../img/blaa2hvid_bg.gif\" align=\"center\">$font <b>SYSTEMDATA</b></td>";
print "</tr>\n";

print "<tr>";

if (substr($rettigheder,2,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:kladdeliste=window.open('../finans/kladdeliste.php','kladdeliste','scrollbars=yes,resizable=yes,dependent=yes');kladdeliste.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\">$font Kassekladde</span></td>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Kassekladde</td>";
}

if (substr($rettigheder,5,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:d_ordrer=window.open('../debitor/ordreliste.php','d_ordrer','scrollbars=yes,resizable=yes,dependent=yes');d_ordrer.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font Ordre</span></td>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Ordre</td>";
}

if (substr($rettigheder,7,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:k_ordrer=window.open('../kreditor/ordreliste.php','k_ordrer','scrollbars=yes,resizable=yes,dependent=yes');k_ordrer.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Ordre</span></td>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Ordre</td>";
}

if ($produktion) {
    if (substr($rettigheder,14,1)=='1') {
        print "<td $stor_knap_bg onClick=\"javascript:p_ordrer=window.open('../produktion/ordreliste.php','p_ordrer','scrollbars=yes,resizable=yes,dependent=yes');p_ordrer.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Ordre</span></td>";
    } else {
        print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Ordre</td>";
    }
}

if (substr($rettigheder,9,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:lager=window.open('../lager/varer.php','lager','scrollbars=yes,resizable=yes,dependent=yes');lager.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Varer</span></td>";
} else {
    $row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'"));
    if ($row[ansat_id]) {
        $row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]"));
        if ($row[navn]) {
            $ref=$row['navn'];
	    if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'"))) {
                if ($row= db_fetch_array(db_select("select beskrivelse, kodenr from grupper where box1='$row[afd]' and art='LG'"))) {
                    $lager=$row['kodenr']*1;
                    print "<td  $stor_knap_bg onClick=\"javascript:lager=window.open('../lager/beholdningsliste.php','lager','scrollbars=yes,resizable=yes,dependent=yes');lager.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >$font  beholdningsliste</span></td>";
                }
            }
        }
    }
    if (!$lager) {
        print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Varer</td>";
    }
}

if (substr($rettigheder,0,1)=='1') {
    print "<td height=\"35\" $stor_knap_bg onClick=\"javascript:kontoplan=window.open('../systemdata/kontoplan.php','kontoplan','scrollbars=yes,resizable=yes,dependent=yes');kontoplan.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\">$font Kontoplan</span></td>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Kontoplan</td>";
}

print "</tr>\n";

print "<tr>";
	
if (substr($rettigheder,3,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:regnskab=window.open('../finans/regnskab.php','regnskab','scrollbars=yes,resizable=yes,dependent=yes');regnskab.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Regnskab</span></td>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Regnskab</td>";
}

if (substr($rettigheder,6,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:d_konti=window.open('../debitor/debitor.php','d_konti','scrollbars=yes,resizable=yes,dependent=yes');d_konti.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Konti</span></td>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Konti</td>";
}

if (substr($rettigheder,8,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:k_konti=window.open('../kreditor/kreditor.php','k_konti','scrollbars=yes,resizable=yes,dependent=yes');k_konti.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Konti</span></td>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Konti</td>";
}

if ($produktion) {
    if (substr($rettigheder,15,1)=='1') {
        print "<td  $stor_knap_bg onClick=\"javascript:k_rapport=window.open('../tidsreg/index.php','k_rapport','scrollbars=yes,resizable=yes,dependent=yes');k_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Tidsreg</td></span>";
    } else {
        print "<td $stor_knap_bg>$font<span style=\"color:#999;\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Tidsreg</td>";
    }
}

if (substr($rettigheder,10,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:lagerenh=window.open('../lager/enheder.php','lagerenh','scrollbars=yes,resizable=yes,dependent=yes');lagerenh.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Enh./mat.</span></td>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Enh./mat.</td>";
}

if (substr($rettigheder,1,1)=='1') {
    print "<td  height=\"35\" $stor_knap_bg onClick=\"javascript:syssetup=window.open('../systemdata/syssetup.php','syssetup','scrollbars=yes,resizable=yes,dependent=yes');syssetup.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Indstillinger</span></td>";
} else {
    print "<td  $stor_knap_bg onClick=\"javascript:brugerdata=window.open('../systemdata/brugerdata.php','brugerdata','scrollbars=yes,resizable=yes,dependent=yes');brugerdata.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Indstillinger</span></td>";
}

print "</tr>\n";

print "<tr>";

if (substr($rettigheder,4,1)=='1') {
    print "<td $stor_knap_bg onClick=\"javascript:f_rapport=window.open('../finans/rapport.php','f_rapport','scrollbars=yes,resizable=yes,dependent=yes');f_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Rapporter</td></span>";
} elseif ($provision) {
    print "<td $stor_knap_bg onClick=\"javascript:provisionsrapport=window.open('../finans/provisionsrapport.php','provisionsrapport','scrollbars=yes,resizable=yes,dependent=yes');provisionsrapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Provision</td></span>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";
}

if (substr($rettigheder,12,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:d_rapport=window.open('../debitor/rapport.php','d_rapport','scrollbars=yes,resizable=yes,dependent=yes');d_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Rapporter</td></span>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";
}

if (substr($rettigheder,13,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:k_rapport=window.open('../kreditor/rapport.php','k_rapport','scrollbars=yes,resizable=yes,dependent=yes');k_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Rapporter</td></span>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";
}

if ($produktion) {
    print "<td>&nbsp;</td>";
}
	
if (substr($rettigheder,15,1)=='1') {
    print "<td  $stor_knap_bg onClick=\"javascript:k_rapport=window.open('../lager/rapport.php','k_rapport','scrollbars=yes,resizable=yes,dependent=yes');k_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Rapporter</td></span>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";
}

if (substr($rettigheder,11,1)=='1') {
    print "<td  height=\"35\" $stor_knap_bg onClick=\"javascript:backup=window.open('../admin/backup.php','backup','scrollbars=yes,resizable=yes,dependent=yes');backup.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font  Sikkerhedskopi</span></td>";
} else {
    print "<td $stor_knap_bg>$font<span style=\"color:#999;\">Backup</td>";
}

#	print "<td>$font <br></td>";
print "</tr>";

print	"</tbody></table>";
print	"</td></tr>";
print	"<tr><td align=\"center\" valign=\"bottom\">";
print	"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print	"<td style=\"border: 1px solid #b4b4ff; padding: 0pt 0pt 1px;\" align=\"left\" background=\"../img/grey1.gif\" width=\"100%\" bgcolor=\"<?php print $bgcolor2 ?>\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000\"><small><small>&nbsp;Copyright&nbsp;&copy;&nbsp;2003-2008&nbsp;DANOSOFT&nbsp;ApS</small></small></td>";
print	"</tbody></table>";
print	"</td></tr>";
print	"</tbody></table>";
print	"</body></html>";
$query = db_select("select * from grupper where art = 'RA'");
if (!$row = db_fetch_array($query)) {
	print "<body onLoad=\"JavaScript:regnaar=window.open('../systemdata/regnskabsaar.php','regnaar',',scrollbars=yes,resizable=yes,dependent=yes,alwaysraised=yes');regnaar.focus();regnaar.focus();\">";
}
?>
