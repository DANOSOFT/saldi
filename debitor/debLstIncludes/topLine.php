<?php
include("../includes/oldDesign/header.php");
include("../includes/topline_settings.php");

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";
if(isset($_GET['returside'])){
	$backUrl = $_GET['returside'];
}else{
	$backUrl = '../index/dashboard.php';
}

print "<tr><td height = '25' align = 'center' valign = 'top'>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><td width=10% style=$buttonStyle>"; # Tabel 1.1 ->
print "<a href=$backUrl accesskey='L'>

		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">" . findtekst(30, $sprog_id) . "</button></a></td>";
print "<td style=$topStyle align=center><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->

if ($valg == 'debitor') {
	print "<td id='debitore' width = '100px' align=center>
			   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(908, $sprog_id) . "</button></td>"; #20210701
} else {
	print "<td id='debitore' width = '100px' align=center>
			   <a href='debitor.php?valg=debitor&returside=$returside'>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(908, $sprog_id) . "</button></a></td>";
}
if ($valg == 'historik') {
	print "<td width = '100px' align=center>
			   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(907, $sprog_id) . "</button></td>";
} else {
	print "<td width = '100px' align=center>
			   <a href='debitor.php?valg=historik&returside=$returside'>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(907, $sprog_id) . "</button></a></td>";
}
if ($valg == 'kommission') {
	print "<td id='kommission' width = '100px' align=center>
			   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(909, $sprog_id) . "</button></td>";
} elseif ($showMySale) {
	print "<td id='kommission' width = '100px' align=center>
			   <a href='debitor.php?valg=kommission&returside=$returside'>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(909, $sprog_id) . "</button></a></td>";
}
#		print "<td width = 20% align=center><a href='debitor.php?valg=rental&returside=$returside'>".findtekst(1116,$sprog_id)."</a></td>";
if ($menu != "S") {
	print "<td width = '100px' align=center>
			   <a href='../rental/index.php?vare'>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(1116, $sprog_id) . "</button></a></td>";
}
$title = findtekst(1664, $sprog_id); #20210728
if ($jobkort) print "<td width = '100px' align=center>
						 <a href='jobliste.php' title ='$title'>
						 <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
	. findtekst(38, $sprog_id) . "</button></a></td>";

print "</tbody></table></td>\n";

print "<td id='tutorial-help' width=5% style=$buttonStyle>
		<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
			Hjælp  
		</button></td>";
print "<td width=5% style=$buttonStyle><a accesskey=V href=debitorvisning.php?valg=$valg>
		   <button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
	. findtekst(813, $sprog_id) . "</button></a></td>\n";

print "<td width=5% style=$butUpStyle>";
if ($valg == 'kommission' || $valg == 'historik') {
	print "<a href=mailTxt.php?valg=$valg&returside=debitor.php>
			   <button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(218, $sprog_id) . "</button></a></td>\n";
} else {
	print "<a id='opret-ny' href=debitorkort.php?returside=debitor.php>
			   <button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(39, $sprog_id) . "</button></a></td>\n";
}
print "<center>"; #20141107
