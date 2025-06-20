<?php
// -----------systemdata/top.php-------lap 2.0.6----2009.02.27---------------
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

$small=NULL;
if (!isset($css)) $css=NULL;

if ($css) {
	$font=""; 
} else {
	$small="<small>";
} 	

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

print"
<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
  <tr><td align=\"center\" valign=\"top\">
    <table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>
      <table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>
        <td width=\"10%\" $top_bund>$font $small<a href=\"$returside\" accesskey=\"L\">Luk</a></td>
        <td width=\"80%\" $top_bund>$font $small Indstillinger<br></td>
        <td width=\"10%\" $top_bund>$font $small<br></td>
      </tr></tbody></table></td>
    </tr></tbody></table>
  </td></tr>
  <tr><td align=\"center\" valign=\"top\">
    <table align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"2\"><tbody>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"syssetup.php?valg=moms\" accesskey=\"M\">Moms</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"syssetup.php?valg=debitor\" accesskey=\"D\">Deb/kred-grp</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"syssetup.php?valg=afdelinger\" accesskey=\"A\">Afdelinger</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"syssetup.php?valg=projekter\" accesskey=\"P\">Projekter</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"syssetup.php?valg=lagre\" accesskey=\"G\">Lagre</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"syssetup.php?valg=varer\" accesskey=\"V\">Varegrp</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"valuta.php\" accesskey=\"U\">Valuta</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"brugere.php\" accesskey=\"B\">Brugere</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"regnskabsaar.php\" accesskey=\"R\">Regnskabs&aring;r</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"stamkort.php\" accesskey=\"S\">Stamdata</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"formularkort.php?valg=formularer\" accesskey=\"F\">Formularer</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"enheder.php\" accesskey=\"E\">Enh/mat</a></td>
      <td width=\"7.5%\" $top_bund>$font $small<a href=\"diverse.php?valg=diverse\" accesskey=\"I\">Diverse</a></td>
    </tbody></table>
  </td></tr>
<tr><td align=\"center\" valign=\"top\" height=\"95%\">";
?>
