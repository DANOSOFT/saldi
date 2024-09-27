<?php
	include("../includes/oldDesign/header.php");
	$border='border:1px';
	$TableBG = "bgcolor=$bgcolor";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><td width=10% $top_bund>"; # Tabel 1.1 ->
	#if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>";
	#else print "<a href=\"../index/menu.php\" accesskey=\"L\">Luk</a></td>";
	print "<a href=$returside accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
	print "<td width=80% $top_bund align=center><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->
	if ($valg=="tilbud" && !$hurtigfakt) {print "<td width = 20% align=center $knap_ind>".findtekst(812, $sprog_id)."</td>";}
	elseif (!$hurtigfakt) {print "<td width = 20% align=center><a href='ordreliste.php?valg=tilbud&konto_id=$konto_id&returside=$returside'>".findtekst(812, $sprog_id)."</a></td>";} //20210318
	if ($valg=="ordre") {print "<td width = 20% align=center $knap_ind>".findtekst(107, $sprog_id)."</td>";}
	else {print "<td width = 20% align=center><a href='ordreliste.php?valg=ordre&konto_id=$konto_id&returside=$returside'>".findtekst(107, $sprog_id)."</a></td>";}
	if ($valg=="faktura") print "<td width = 20% align=center $knap_ind>".findtekst(643, $sprog_id)."</td>";
	else print "<td width = 20% align=center><a href='ordreliste.php?valg=faktura&konto_id=$konto_id&returside=$returside'>".findtekst(643, $sprog_id)."</a></td>";
	if ($valg=='pbs') print "<td width = 20% align=center $knap_ind>".findtekst(385, $sprog_id)."</td>";
	elseif ($pbs) print "<td width = 20% align=center><a href='ordreliste.php?valg=pbs&konto_id=$konto_id&returside=$returside'>".findtekst(385, $sprog_id)."</a></td>";
	print "</tbody></table></td>\n"; # <- Tabel 1.1.1
	if ($valg=='pbs') {
		if ($popup) print "<td width=10% $top_bund onClick=\"javascript:ordre=window.open('pbs_import.php?returside=x','ordre','scrollbars=1,resizable=1');ordre.focus();\"><a accesskey=N href=ordreliste.php?sort=$sort>Import PBS</a></td>\n";
		else  print "<td width=10% $top_bund><a href=pbs_import.php?returside=ordreliste.php>Import PBS</a></td>\n";
		include("pbsliste.php");
		exit;
	}
	if ($valg=='pbs') {
	#	if ($popup) print "<td width=10% $top_bund onClick=\"javascript:ordre=window.open('pbs_import.php?returside=ordreliste.php','ordre','scrollbars=1,resizable=1');ordre.focus();\"><a accesskey=N href=ordreliste.php?sort=$sort>Import PBS</a></td>\n";
	#	else  print "<td width=10% $top_bund><a href=pbs_import.php?returside=ordreliste.php>Import PBS</a></td>\n";
	} else {
		print "<td width=5% $top_bund><a accesskey=V href=ordrevisning.php?valg=$valg>".findtekst(813, $sprog_id)."</a></td>\n";
		if ($popup) {
			print "<td width=5% $top_bund onClick=\"javascript:ordre=window.open('ordre.php?returside=ordreliste.php&konto_id=$konto_id','ordre','scrollbars=1,resizable=1');ordre.focus();\"><a accesskey=N href='".$_SERVER['PHP_SELF']."'>".findtekst(39, $sprog_id)."</a></td>\n";
		} else {
			print "<td width=5%  $top_bund><a href=ordre.php?konto_id=$konto_id&returside=ordreliste.php?konto_id=$konto_id>".findtekst(39, $sprog_id)."</a></td>\n";
		}
		print "</tbody></table></td></tr>\n"; # <- Tabel 1.1.1
	}
	if ($valg=="ordre") { #20121017
		$dir = '../ublfiler/ind/';
		if (file_exists("$dir")) {
			$vis_xml=0;
			$filer = scandir($dir);
			for ($x=0;$x<count($filer);$x++) {
				if (substr($filer[$x],-3)=='xml') $vis_xml=1;
			}
			if ($vis_xml) print "<tr><td align=\"center\"><a href=\"ubl2ordre.php\" target=\"blank\">".findtekst(876, $sprog_id)."</a></td></tr>";
		}
	}
	print "<center>"; #20141107

?>
