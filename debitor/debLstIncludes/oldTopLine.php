<?php

	include("../includes/oldDesign/header.php");
	print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>\n";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><td width=10% $top_bund>\n";
	print "<a href=$returside accesskey=L>".findtekst(30,$sprog_id)."</a></td>";
	print "<td width=80% $top_bund align=center><table border=0 cellspacing=2 cellpadding=0><tbody>\n";

	if ($valg=='debitor') print "<td width = 20% align=center $knap_ind>".findtekst(908,$sprog_id)."</td>"; #20210701
	else print "<td width = 20% align=center><a href='debitor.php?valg=debitor&returside=$returside'>".findtekst(908,$sprog_id)."</a></td>";
	if ($valg=='historik') print "<td width = 20% align=center $knap_ind>".findtekst(907,$sprog_id)."</td>";
	else print "<td width = 20% align=center><a href='debitor.php?valg=historik&returside=$returside'>".findtekst(907,$sprog_id)."</a></td>";
	if ($valg=='kommission') print "<td width = 20% align=center $knap_ind>".findtekst(909,$sprog_id)."</td>";
	elseif ($showMySale) {
		print "<td width = 20% align=center><a href='debitor.php?valg=kommission&returside=$returside'>".findtekst(909,$sprog_id)."</a></td>";
	}
#		print "<td width = 20% align=center><a href='debitor.php?valg=rental&returside=$returside'>".findtekst(1116,$sprog_id)."</a></td>";
	if ($valg=='rental') print "<td width = 20% align=center $knap_ind>".findtekst(1116,$sprog_id)."</td>";
	elseif ($showRental) {
	print "<td width = 20% align=center><a href='../rental/index.php?vare'>".findtekst(1116,$sprog_id)."</a></td>";
}
	$title=findtekst(1664, $sprog_id); #20210728
	if ($jobkort)	print "<td width = 20% align=center><a href='jobliste.php' title ='$title'>".findtekst(38,$sprog_id)."</a></td>";
	print "</tbody></table></td>\n";
	print "<td width=5% $top_bund><a accesskey=V href=debitorvisning.php?valg=$valg>".findtekst(813,$sprog_id)."</a></td>\n";
	print "<td width=5%  $top_bund>";
	if ($valg=='kommission' ||$valg=='historik') print "<a href=mailTxt.php?valg=$valg&returside=debitor.php>".findtekst(218,$sprog_id)."</a></td>\n";
	else print "<a href=debitorkort.php?returside=debitor.php>".findtekst(39,$sprog_id)."</a></td>\n";
	print "</td><td></td></tr>\n";
	print "</tbody></table>";
	print " </td></tr>\n<tr><td align=\"center\" valign=\"top\" width=\"100%\">";

?>
