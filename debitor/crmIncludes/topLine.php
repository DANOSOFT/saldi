<?php
	include("../includes/oldDesign/header.php");
	include("../includes/topline_settings.php");

	$border = 'border:1px';
	$TableBG = "bgcolor=$bgcolor";

	print "<tr><td height = '25' align = 'center' valign = 'top'>";
	
	if ($valg == "visning") {
		$returside = "crmkalender.php?returside=../index/dashboard.php&vismenu=all";
	}

	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>"; # Tabel 1.1 ->
	print "<td width=10% style=$buttonStyle>
		   <a href=$returside accesskey='L'>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
		   .findtekst(30, $sprog_id)."</button></a></td>";

	print "<td width=80% style=$topStyle align=center><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->

	if ($valg=="kalender") {
		print "<td width = '100px' align=center>
			   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">Kalender</button></td>";
	} else {
		print "<td width = '100px' align=center>
			   <a href='crmkalender.php?returside=$returside&vismenu=$_GET[vismenu]'>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">Kalender</button></a></td>";
	} //20210318

	print "</tbody></table></td>\n"; # <- Tabel 1.1.1

	print "<td width=10% style=$buttonStyle>
		   <a accesskey=V href=crmvisning.php?returside=$returside>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		   .findtekst(813, $sprog_id)."</button></a></td>\n";
	print "</tbody></table></td></tr>\n"; # <- Tabel 1.1.1

?>
