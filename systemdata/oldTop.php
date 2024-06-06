<?php
// --- systemdata/top.php --- ver 4.1.0 --- 2024-05-01 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20181102 PHR Oprydning, udefinerede variabler.
// 20210223 Loe Replaced the text values on the table data with dynamic data with findtekst().
// 20220103 PHR Checks for error in text id 778 - can be removed in 2023

$small=NULL;
if (!isset($css)) $css=NULL;
if (!isset($rightoptxt)) $rightoptxt=NULL;

if ($css) $font="";
else $small="<small>";

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

if (findtekst(778,$sprog_id) == 'Regnskabså') {
	$qtxt = "update tekster set tekst = '' where tekst_id = '778'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}


print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1
print "<td width=\"170px\" $top_bund>$font $small<a href=\"$returside\" accesskey=\"L\">".findtekst(30,$sprog_id)."</a></td>
        <td $top_bund>$font $small ".findtekst(613,$sprog_id)."<br></td>
        <td width=\"170px\" $top_bund>$font $small $rightoptxt<br></td></tr>
      </tbody></table></td></tr>"; # <- tabel 1.1.1
print "</tr></tbody></table></td></tr>
  <tr><td id='sidebar-base'  width=\"125px\" align=\"left\" valign=\"top\">";
print "<table align=\"left\" border=\"0\" cellspacing=\"2\" cellpadding=\"2\"width=\"170px\"><tbody>"; #tabel 1.1.2
print "<tr><td width=\"170px\"><br></td></tr>
      <tr><td $top_bund>$font $small<a href=\"syssetup.php?valg=moms\" accesskey=\"M\">".findtekst(770,$sprog_id)."</a></td></tr> 
      <tr><td $top_bund>$font $small<a href=\"syssetup.php?valg=debitor\" accesskey=\"D\">".findtekst(771,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"syssetup.php?valg=afdelinger\" accesskey=\"A\">".findtekst(772,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"projekter.php\" accesskey=\"P\">".findtekst(773,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"syssetup.php?valg=lagre\" accesskey=\"G\">".findtekst(608,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"syssetup.php?valg=varer\" accesskey=\"V\">".findtekst(774,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"rabatgrupper.php\" accesskey=\"V\">".findtekst(775,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"valuta.php\" accesskey=\"U\">".findtekst(776,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"brugere.php\" accesskey=\"B\">".findtekst(777,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"regnskabsaar.php\" accesskey=\"R\">".findtekst(778,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"stamkort.php\" accesskey=\"S\">".findtekst(779,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"formularkort.php?valg=formularer\" accesskey=\"F\">".findtekst(780,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"enheder.php\" accesskey=\"E\">".findtekst(781,$sprog_id)."</a></td></tr>
      <tr><td $top_bund>$font $small<a href=\"diverse.php?valg=diverse\" accesskey=\"I\">".findtekst(782,$sprog_id)."</a></td></tr>"; #20210223
print "</tbody></table>";# <-tabel 1.1.2
print "</td><td align=\"center\" valign=\"top\" height=\"99%\"><br>";

?>
