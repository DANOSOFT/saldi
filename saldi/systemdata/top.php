<?php
// -----------systemdata/top.php-------lap 3.7.2----2018.11.02---------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2018 saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20181102 PHR Oprydning, udefinerede variabler.

$small=NULL;
if (!isset($css)) $css=NULL;
if (!isset($rightoptxt)) $rightoptxt=NULL;

if ($css) $font="";
else $small="<small>";

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1
print "<td width=\"120px\" $top_bund>$font $small<a href=\"$returside\" accesskey=\"L\">Luk</a></td>
        <td $top_bund>$font $small Indstillinger<br></td>
        <td width=\"120px\" $top_bund>$font $small $rightoptxt<br></td></tr>
      </tbody></table></td></tr>"; # <- tabel 1.1.1
print "</tr></tbody></table></td></tr>
  <tr><td width=\"125px\" align=\"center\" valign=\"top\">";
print "<table align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"2\"width=\"120px\"><tbody>"; #tabel 1.1.2
print "<tr><td width=\"120px\"><br></td></tr>
      <tr><td $top_bund>$font $small<a href=\"syssetup.php?valg=moms\" accesskey=\"M\">Moms</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"syssetup.php?valg=debitor\" accesskey=\"D\">Deb/kred-grp</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"syssetup.php?valg=afdelinger\" accesskey=\"A\">Afdelinger</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"projekter.php\" accesskey=\"P\">Projekter</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"syssetup.php?valg=lagre\" accesskey=\"G\">Lagre</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"syssetup.php?valg=varer\" accesskey=\"V\">Varegrp</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"rabatgrupper.php\" accesskey=\"V\">Rabatgrp</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"valuta.php\" accesskey=\"U\">Valuta</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"brugere.php\" accesskey=\"B\">Brugere</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"regnskabsaar.php\" accesskey=\"R\">Regnskabs&aring;r</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"stamkort.php\" accesskey=\"S\">Stamdata</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"formularkort.php?valg=formularer\" accesskey=\"F\">Formularer</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"enheder.php\" accesskey=\"E\">Enh/mat</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"diverse.php?valg=diverse\" accesskey=\"I\">Diverse</a></td></tr>";
print "</tbody></table>";# <-tabel 1.1.2
print "</td><td align=\"center\" valign=\"top\" height=\"99%\"><br>";
?>
