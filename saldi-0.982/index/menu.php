<?
// ----------------------------------------------------------------------Patch 0958----------
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
// Copyright (c) 2004-2006 ITz ApS
// ----------------------------------------------------------------------


 @session_start();  # Skal angives oeverst i filen??!!
 $s_id=session_id();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head>
<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type"><title>SALDI - Hovedmenu</title></head>
<?
include("../includes/connect.php");
include("../includes/online.php");

$query = db_select("select * from grupper where art = 'RA'");
if (!$row = db_fetch_array($query)) {
  print "<BODY onLoad=\"JavaScript:window.open('../systemdata/regnskabsaar.php' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
}


print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"20%\" bgcolor=\"$bgcolor2\">$font <small>Ver $version</small></td>";
    print "<td width=\"60%\" bgcolor=\"$bgcolor2\" align = \"center\">$font <a href=komigang.html><small>Vejledning</small></td>\n";
    print "<td width=\"20%\" bgcolor=\"$bgcolor2\" align = \"right\">$font <a href=logud.php accesskey=L><small>Logud</small></td>\n";
    print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"center\">\n";

  print "<table align=\"center\" border=\"1\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td colspan=\"6\" align=\"center\">$font <big><big><big><b>SALDI 1.0 rc8</b></big></big></big></td></tr>";
  print "<tr><td colspan=\"6\" align=\"center\">$font <b>$regnskab</b></td></tr><tr>";
  print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>SYSTEMDATA</b></td>";
  print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>FINANS</b></td>";
  print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>DEBITOR</b></td>";
  print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>KREDITOR</b></td>";
#  print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>PRODUKTION</b></td>";  Udeladt indtil test gennemført.
  print "<td width=\"150\" bgcolor=\"$bgcolor2\">$font <b>LAGER</b></td>";
  print "</tr><tr>";
  if (substr($rettigheder,0,1)=='1') {print "<td onClick=\"javascript:kontoplan=window.open('../systemdata/kontoplan.php','kontoplan','scrollbars=1,resizable=1');kontoplan.focus();\">$font<span style=\"text-decoration: underline;\">Kontoplan</span></td>";}
  else  {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Kontoplan</td>";}
  if (substr($rettigheder,2,1)=='1') {print "<td onClick=\"javascript:kladdeliste=window.open('../finans/kladdeliste.php','kladdeliste','scrollbars=1,resizable=1');kladdeliste.focus();\">$font<span style=\"text-decoration: underline;\">Kassekladde</span></td>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Kassekladde</td>";}
  if (substr($rettigheder,5,1)=='1') {print "<td onClick=\"javascript:d_ordrer=window.open('../debitor/ordreliste.php','d_ordrer','scrollbars=1,resizable=1');d_ordrer.focus();\" >$font <span style=\"text-decoration: underline;\">Ordre</span></td>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Ordre</td>";}
  if (substr($rettigheder,7,1)=='1') {print "<td onClick=\"javascript:k_ordrer=window.open('../kreditor/ordreliste.php','k_ordrer','scrollbars=1,resizable=1');k_ordrer.focus();\" >$font <span style=\"text-decoration: underline;\">Ordre</span></td>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Ordre</td>";}
/*  
  if (substr($rettigheder,14,1)=='1') {print "<td onClick=\"javascript:p_ordrer=window.open('../produktion/ordreliste.php','p_ordrer','scrollbars=1,resizable=1');p_ordrer.focus();\" >$font <span style=\"text-decoration: underline;\">Ordre</span></td>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Ordre</td>";}
*/  
  if (substr($rettigheder,9,1)=='1') {print "<td onClick=\"javascript:lager=window.open('../lager/varer.php','lager','scrollbars=1,resizable=1');lager.focus();\" >$font <span style=\"text-decoration: underline;\">Varer</span></td>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Varer</td>";}
  print "</tr><tr>";
  if (substr($rettigheder,1,1)=='1') {print "<td onClick=\"javascript:syssetup=window.open('../systemdata/syssetup.php','syssetup','scrollbars=1,resizable=1');syssetup.focus();\" >$font <span style=\"text-decoration: underline;\">Indstillinger</span></td>";}
  else {print "<td>$font <a onfocus=\"this.style.color='$bgcolor2'\" onblur=\"this.style.color='#000066'\" href=\"../systemdata/brugerdata.php\">Indstillinger</td>";}
  if (substr($rettigheder,3,1)=='1') {print "<td onClick=\"javascript:regnskab=window.open('../finans/regnskab.php','regnskab','scrollbars=1,resizable=1');regnskab.focus();\" >$font <span style=\"text-decoration: underline;\">Regnskab</span></td>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Regnskab</td>";}
  if (substr($rettigheder,6,1)=='1') {print "<td onClick=\"javascript:d_konti=window.open('../debitor/debitor.php','d_konti','scrollbars=1,resizable=1');d_konti.focus();\" >$font <span style=\"text-decoration: underline;\">Konti</span></td>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Konti</td>";}
  if (substr($rettigheder,8,1)=='1') {print "<td onClick=\"javascript:k_konti=window.open('../kreditor/kreditor.php','k_konti','scrollbars=1,resizable=1');k_konti.focus();\" >$font <span style=\"text-decoration: underline;\">Konti</span></td>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Konti</td>";}
#  print "<td>$font <br></td>";
  if (substr($rettigheder,10,1)=='1') {print "<td onClick=\"javascript:lagerenh=window.open('../lager/enheder.php','lagerenh','scrollbars=1,resizable=1');lagerenh.focus();\" >$font <span style=\"text-decoration: underline;\">Enh./mat.</span></td>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Enh./mat.</td>";}
  print "</tr><tr>";
  if (substr($rettigheder,11,1)=='1') {print "<td onClick=\"javascript:backup=window.open('../index/backup.php','backup','scrollbars=1,resizable=1');backup.focus();\" >$font <span style=\"text-decoration: underline;\">Backup</span></td>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\">Backup</td>";}
  if (substr($rettigheder,4,1)=='1') {print "<td onClick=\"javascript:f_rapport=window.open('../finans/rapport.php','f_rapport','scrollbars=1,resizable=1');f_rapport.focus();\" >$font <span style=\"text-decoration: underline;\">Rapporter</td></span>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";}
  if (substr($rettigheder,12,1)=='1') {print "<td onClick=\"javascript:d_rapport=window.open('../debitor/rapport.php','d_rapport','scrollbars=1,resizable=1');d_rapport.focus();\" >$font <span style=\"text-decoration: underline;\">Rapporter</td></span>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";}
  if (substr($rettigheder,13,1)=='1') {print "<td onClick=\"javascript:k_rapport=window.open('../kreditor/rapport.php','k_rapport','scrollbars=1,resizable=1');k_rapport.focus();\" >$font <span style=\"text-decoration: underline;\">Rapporter</td></span>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";}
#  print "<td>$font <br></td>";
  if (substr($rettigheder,15,1)=='1') {print "<td onClick=\"javascript:k_rapport=window.open('../lager/rapport.php','k_rapport','scrollbars=1,resizable=1');k_rapport.focus();\" >$font <span style=\"text-decoration: underline;\">Rapporter</td></span>";}
  else {print "<td><font face=\"Helvetica, Arial, sans-serif\" color=\"#888888\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Rapporter</td>";}
#  print "<td>$font <br></td>";
  print "</tr>";

?>
    </tbody></table>
  </td></tr>
  <tr><td align="center" valign="bottom">
    <table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
      <td width="25%" bgcolor="<? print $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Copyright (C) 2004-2006 ITz ApS</small></td>
      <td width="50%" bgcolor="<? print $bgcolor2 ?>" align = "center"><font face="Helvetica, Arial, sans-serif" color="#000066"></td>
      <td width="25%" bgcolor="<? print $bgcolor2 ?>" align = "right"><font face="Helvetica, Arial, sans-serif" color="#000066"></td>
    </tbody></table>
  </td></tr>
</tbody></table>
</body></html>
