<?php

include("../includes/oldDesign/header.php");
print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>\n";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>\n";
print "<tr><td width=10% $top_bund><a href=$returside accesskey=L>" . findtekst(30, $sprog_id) . "</a></td>";
print "<td width = 80% align=center $top_bund>" . findtekst(607, $sprog_id) . "</td>";
print "<td width=5% $top_bund><a accesskey=V href=kreditorvisning.php?valg=$valg>" . findtekst(813, $sprog_id) . "</a></td>\n";
print "<td width=5%  $top_bund><a href=kreditorkort.php?returside=kreditor.php>" . findtekst(39, $sprog_id) . "</a></td></tr>\n";
print "</tbody></table>";
print " </td></tr>\n<tr><td align=\"center\" valign=\"top\" width=\"100%\">";

?>
