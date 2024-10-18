<?php
	include("../includes/oldDesign/header.php");
	include("../includes/topline_settings.php");

	$border='border:1px';
	$TableBG = "bgcolor=$bgcolor";

	print "<tr><td height = '25' align = 'center' valign = 'top'>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><td width=10% style=$butUpStyle>"; # Tabel 1.1 ->
	print "<a href=$returside accesskey='L'>
		   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst(30, $sprog_id)."</button></a></td>";
	print "<td width=80% style=$topStyle align=center><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->

	if ($valg=='debitor') {
		print "<td width = '100px' align=center>
			   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(908, $sprog_id)."</button></td>"; #20210701
	} else {
		print "<td width = '100px' align=center>
			   <a href='debitor.php?valg=debitor&returside=$returside'>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(908, $sprog_id)."</button></a></td>";
	}
	if ($valg=='historik') {
		print "<td width = '100px' align=center>
			   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(907, $sprog_id)."</button></td>";
	} else {
		print "<td width = '100px' align=center>
			   <a href='debitor.php?valg=historik&returside=$returside'>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(907, $sprog_id)."</button></a></td>";
	}
	if ($valg=='kommission') {
		print "<td width = '100px' align=center>
			   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(909, $sprog_id)."</button></td>";
	} elseif ($showMySale) {
		print "<td width = '100px' align=center>
			   <a href='debitor.php?valg=kommission&returside=$returside'>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(909, $sprog_id)."</button></a></td>";
	}
#		print "<td width = 20% align=center><a href='debitor.php?valg=rental&returside=$returside'>".findtekst(1116,$sprog_id)."</a></td>";
	if ($valg=='rental') {
		print "<td width = '100px' align=center>
			   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(1116, $sprog_id)."</button></td>";
	} elseif ($showRental) {
		print "<td width = '100px' align=center>
			   <a href='../rental/index.php?vare'>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(1116, $sprog_id)."</button></a></td>";
	}
	$title=findtekst(1664, $sprog_id); #20210728
	if ($jobkort) print "<td width = '100px' align=center>
						 <a href='jobliste.php' title ='$title'>
						 <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
						 .findtekst(38,$sprog_id)."</button></a></td>";

	print "</tbody></table></td>\n";

	print "<td width=5% style=$butUpStyle><a accesskey=V href=debitorvisning.php?valg=$valg>
		   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		   .findtekst(813,$sprog_id)."</button></a></td>\n";

	print "<td width=5% style=$butUpStyle>";
	if ($valg=='kommission' || $valg=='historik') {
		print "<a href=mailTxt.php?valg=$valg&returside=debitor.php>
			   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(218,$sprog_id)."</button></a></td>\n";
	} else {
		print "<a href=debitorkort.php?returside=debitor.php>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(39,$sprog_id)."</button></a></td>\n";
	}
	print "<center>"; #20141107
?>
