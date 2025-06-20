<?php
// ----------------------------------------index/menu.php-------------------Patch 1.0.8 --------
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
@session_start();	# Skal angives oeverst i filen??!!
 $s_id=session_id();

$produktion=0;
print "<meta http-equiv=\"refresh\" content=\"60;URL=menu.php\">";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head>
<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type"><title>SALDI - Hovedmenu</title></head>
<?php
if (isset($_GET['online'])) $online=$_GET['online'];
else $online=0;

$modulnr=0;
include("../includes/connect.php");
include("../includes/online.php");

$provision=0;
if ($ansat_id) {
	$r = db_fetch_array(db_select("select * from ansatte where id = '$ansat_id[$a]'"));
	$provision = $r['provision'];
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"20%\" bgcolor=\"$bgcolor2\">$font <small>Ver $version</small></td>";
print "<td width=\"60%\" bgcolor=\"$bgcolor2\" align = \"center\">$font <a href=komigang.html><small>Vejledning</small></td>\n";
print "<td width=\"20%\" bgcolor=\"$bgcolor2\" align = \"right\">$font <a href=logud.php accesskey=L><small>Logud</small></td>\n";
print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"center\">\n";

print "<table align=\"center\" border=\"1\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td colspan=\"6\" align=\"center\">$font <big><big><big><b>SALDI 1.0</b></big></big></big></td></tr>";
print "<tr><td colspan=\"6\" align=\"center\">$font <b>$regnskab</b></td></tr><tr>";
print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>SYSTEMDATA</b></td>";
print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>FINANS</b></td>";
print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>DEBITOR</b></td>";
print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>KREDITOR</b></td>";
if ($produktion)	print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>PRODUKTION</b></td>";	#Udeladt indtil test gennemført.
print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>LAGER</b></td>";
print "</tr><tr>";
if (substr($rettigheder,0,1)=='1') {print "<td onClick=\"javascript:kontoplan=window.open('../systemdata/kontoplan.php','kontoplan','scrollbars=yes,resizable=yes,dependent=yes');kontoplan.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\">$font<span style=\"text-decoration: underline;\">Kontoplan</span></td>";}
else	{print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Kontoplan</td>";}
if (substr($rettigheder,2,1)=='1') {print "<td onClick=\"javascript:kladdeliste=window.open('../finans/kladdeliste.php','kladdeliste','scrollbars=yes,resizable=yes,dependent=yes');kladdeliste.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\">$font<span style=\"text-decoration: underline;\">Kassekladde</span></td>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Kassekladde</td>";}
if (substr($rettigheder,5,1)=='1') {print "<td onClick=\"javascript:d_ordrer=window.open('../debitor/ordreliste.php','d_ordrer','scrollbars=yes,resizable=yes,dependent=yes');d_ordrer.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Ordre</span></td>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Ordre</td>";}
if (substr($rettigheder,7,1)=='1') {print "<td onClick=\"javascript:k_ordrer=window.open('../kreditor/ordreliste.php','k_ordrer','scrollbars=yes,resizable=yes,dependent=yes');k_ordrer.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Ordre</span></td>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Ordre</td>";}
if ($produktion) {
	if (substr($rettigheder,14,1)=='1') {print "<td onClick=\"javascript:p_ordrer=window.open('../produktion/ordreliste.php','p_ordrer','scrollbars=yes,resizable=yes,dependent=yes');p_ordrer.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Ordre</span></td>";}
	else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Ordre</td>";}
}
if (substr($rettigheder,9,1)=='1') {print "<td onClick=\"javascript:lager=window.open('../lager/varer.php','lager','scrollbars=yes,resizable=yes,dependent=yes');lager.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Varer</span></td>";}
else {
	$row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'"));
	if ($row[ansat_id]) {
		$row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]"));
		if ($row[navn]) {
			$ref=$row['navn'];
			if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'"))) {
				if ($row= db_fetch_array(db_select("select beskrivelse, kodenr from grupper where box1='$row[afd]' and art='LG'")))	{
					$lager=$row['kodenr']*1;
					print "<td onClick=\"javascript:lager=window.open('../lager/beholdningsliste.php','lager','scrollbars=yes,resizable=yes,dependent=yes');lager.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">beholdningsliste</span></td>";}
			}
		}
	}
	if (!$lager) {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Varer</td>";}
}
 
print "</tr><tr>";
	
if (substr($rettigheder,1,1)=='1') {print "<td onClick=\"javascript:syssetup=window.open('../systemdata/syssetup.php','syssetup','scrollbars=yes,resizable=yes,dependent=yes');syssetup.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Indstillinger</span></td>";}
else {print "<td>$font <a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../systemdata/brugerdata.php\">Indstillinger</td>";}
if (substr($rettigheder,3,1)=='1') {print "<td onClick=\"javascript:regnskab=window.open('../finans/regnskab.php','regnskab','scrollbars=yes,resizable=yes,dependent=yes');regnskab.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Regnskab</span></td>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Regnskab</td>";}
if (substr($rettigheder,6,1)=='1') {print "<td onClick=\"javascript:d_konti=window.open('../debitor/debitor.php','d_konti','scrollbars=yes,resizable=yes,dependent=yes');d_konti.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Konti</span></td>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Konti</td>";}
if (substr($rettigheder,8,1)=='1') {print "<td onClick=\"javascript:k_konti=window.open('../kreditor/kreditor.php','k_konti','scrollbars=yes,resizable=yes,dependent=yes');k_konti.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Konti</span></td>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Konti</td>";}
if ($produktion) {
	if (substr($rettigheder,16,1)=='1') {print "<td onClick=\"javascript:k_rapport=window.open('../tidsreg/index.php','k_rapport','scrollbars=yes,resizable=yes,dependent=yes');k_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Tidsreg</td></span>";}
	else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Tidsreg</td>";}
}
if (substr($rettigheder,10,1)=='1') {print "<td onClick=\"javascript:lagerenh=window.open('../lager/enheder.php','lagerenh','scrollbars=yes,resizable=yes,dependent=yes');lagerenh.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Enh./mat.</span></td>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Enh./mat.</td>";}
print "</tr><tr>";
if (substr($rettigheder,11,1)=='1') {print "<td onClick=\"javascript:backup=window.open('../admin/backup.php','backup','scrollbars=yes,resizable=yes,dependent=yes');backup.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Sikkerhedskopi</span></td>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Backup</td>";}
if (substr($rettigheder,4,1)=='1') {print "<td onClick=\"javascript:f_rapport=window.open('../finans/rapport.php','f_rapport','scrollbars=yes,resizable=yes,dependent=yes');f_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Rapporter</td></span>";}
elseif ($provision) {print "<td onClick=\"javascript:provisionsrapport=window.open('../finans/provisionsrapport.php','provisionsrapport','scrollbars=yes,resizable=yes,dependent=yes');provisionsrapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Provision</td></span>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";}
if (substr($rettigheder,12,1)=='1') {print "<td onClick=\"javascript:d_rapport=window.open('../debitor/rapport.php','d_rapport','scrollbars=yes,resizable=yes,dependent=yes');d_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Rapporter</td></span>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";}
if (substr($rettigheder,13,1)=='1') {print "<td onClick=\"javascript:k_rapport=window.open('../kreditor/rapport.php','k_rapport','scrollbars=yes,resizable=yes,dependent=yes');k_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Rapporter</td></span>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";}
if ($produktion) {
	print "<td><br></td>";
}	
if (substr($rettigheder,15,1)=='1') {print "<td onClick=\"javascript:k_rapport=window.open('../lager/rapport.php','k_rapport','scrollbars=yes,resizable=yes,dependent=yes');k_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >$font <span style=\"text-decoration: underline;\">Rapporter</td></span>";}
else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";}
#	print "<td>$font <br></td>";
print "</tr>";
?>
		</tbody></table>
	</td></tr>
	<tr><td align="center" valign="bottom">
		<table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
			<td width="25%" bgcolor="<?php print $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000000"><small><small>Copyright&nbsp;&copy;&nbsp;2004-2006&nbsp;DANOSOFT&nbsp;ApS</small></small></td>
			<td width="50%" bgcolor="<?php print $bgcolor2 ?>" align = "center"><font face="Helvetica, Arial, sans-serif" color="#000000"></td>
			<td width="25%" bgcolor="<?php print $bgcolor2 ?>" align = "right"><font face="Helvetica, Arial, sans-serif" color="#000000"></td>
		</tbody></table>
	</td></tr>
</tbody></table>
</body></html>
<?php
$query = db_select("select * from grupper where art = 'RA'");
if (!$row = db_fetch_array($query)) {
	print "<BODY onLoad=\"JavaScript:regnaar=window.open('../systemdata/regnskabsaar.php','regnaar',',scrollbars=yes,resizable=yes,dependent=yes,alwaysraised=yes');regnaar.focus();regnaar.focus();\">";
}
?>