<?php

// 20250911 LOE Sets value of jobkort directly.

include("../includes/oldDesign/header.php");
include("../includes/topline_settings.php");


$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";

if (isset($_GET['returside'])) {
	$backUrl = $_GET['returside'];
} else {
	$backUrl = '../index/menu.php';
}

if (!isset($jobkort)) { #LOE
	if (isset($_GET['jobkort'])) {
		$jobkort = $_GET['jobkort'];
	} else {
		$jobkort = null;
	}
}
print "<tr><td height = '25' align = 'center' valign = 'top'>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><td width=10% style='$buttonStyle'>"; # Tabel 1.1 ->
print "<a href=$backUrl accesskey='L'>";
print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
print findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
print "<td style='$topStyle' align=center><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->

if ($valg == 'debitor') {
	print "<td id='debitore' width = '100px' align=center>";
	print "<button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print findtekst('908|Debitorer', $sprog_id)."</button></td>"; #20210701
} else {
	print "<td id='debitore' width = '100px' align=center>";
	print "<a href='debitor.php?valg=debitor&returside=$returside'>";
	print "<button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print findtekst('908|Debitorer', $sprog_id)."</button></a></td>";
}

if ($valg == 'historik') {
	print "<td width = '100px' align=center>";
	print "<button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print findtekst('907|Historik', $sprog_id)."</button></td>";
} else {
	print "<td width = '100px' align=center>";
	print "<a href='debitor.php?valg=historik&returside=$returside'>";
	print "<button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print findtekst('907|Historik', $sprog_id)."</button></a></td>";
}

if ($valg == 'kommission') {
	print "<td id='kommission' width = '100px' align=center>";
	print "<button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print findtekst('909|Kommission', $sprog_id)."</button></td>";
} elseif ($showMySale) {
	print "<td id='kommission' width = '100px' align=center>";
	print "<a href='debitor.php?valg=kommission&returside=$returside'>";
	print "<button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print findtekst('909|Kommission', $sprog_id)."</button></a></td>";
}
#	print "<td width = 20% align=center><a href='debitor.php?valg=rental&returside=$returside'>".findtekst('1116|Booking'$sprog_id)."</a></td>";
if ($menu != "S") {
	print "<td width = '100px' align=center>";
	print "<a href='../rental/index.php?vare'>";
	print "<button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print findtekst('1116|Booking', $sprog_id)."</button></a></td>";
}

$title = findtekst('1664|Klik her for at skifte til joblisten', $sprog_id); #20210728 
if ($jobkort && $valg!='jobkort') { 
	print "<td id='jobliste' width = '100px' align=center>";
	print "<a href='jobliste.php?valg=jobkort&jobkort=$jobkort' title ='$title'>";
	print "<button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\" title ='$title'>";
	print findtekst('38|Stillingsliste', $sprog_id)."</button></td>";
} else {
	if($jobkort && $valg=='jobkort'){
		print "<td width = '100px' align=center>";
		print "<a href='jobliste.php?valg=jobkort' title ='$title'>";
		print "<button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
		print findtekst('38|Stillingsliste', $sprog_id)."</button></a></td>";
	}
}
print "</tbody></table></td>\n";

if ($valg != 'jobkort') {
	print "<td id='tutorial-help' width=5% style='$buttonStyle'>";
	print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	print findtekst('2564|Hjælp', $sprog_id)."</button></td>";

	print "<td width=5% style='$buttonStyle'><a accesskey=V href=debitorvisning.php?valg=$valg>";
	print "<button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print findtekst('813|Visning', $sprog_id)."</button></a></td>\n";
}
print "<td width=5% style=$butUpStyle>";

if ($valg == 'kommission' || $valg == 'historik') {
	print "<a href=mailTxt.php?valg=$valg&returside=debitor.php>";
	print "<button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print findtekst('218|Mailtekst', $sprog_id)."</button></a></td>\n";
} else {
	if(isset($jobkort) && $valg=='jobkort') {
		if ($popup) {  
			print "<a id='opret-ny' onClick=\"javascript:job=window.open('jobkort.php?returside=jobliste.php&konto_id=$konto_id&ordre_id=$ordre_id','job','scrollbars=1,resizable=1');job.focus(); return false;\">";
			print "<button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
			print findtekst('39|Ny', $sprog_id)."</button></a>\n";
		} else {
			print "<a id='opret-ny' href='jobkort.php?returside=jobkort.php&konto_id=$konto_id&ordre_id=$ordre_id'>";
			print "<button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
			print findtekst('39|Ny', $sprog_id)."</button></a>\n";
		}
	} else {
		print "<a id='opret-ny' href=debitorkort.php?returside=debitor.php>";
		print "<button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
		print findtekst('39|Ny', $sprog_id)."</button></a></td>\n";
	}
}
print "<center>"; #20141107
