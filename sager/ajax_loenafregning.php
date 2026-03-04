<?php
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

	
	$bg="nix";
	$header='nix';

	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	
	$alle_ansatte_id=if_isset($_POST['alle_ansatte_id']);
	$periode=if_isset($_POST['periode']);
	
	
	print "<li><a href=\"loen.php?funktion=loenafregning&amp;periode=$periode&amp;alle_ansatte_id=$alle_ansatte_id\">".findtekst('2786|Lønafregning', $sprog_id)." (".findtekst('2980|Timer', $sprog_id).")"."</a></li>"; #Lønafregning (Timer)
	print "<li><a href=\"loen.php?funktion=loenafregning&amp;vis=belob&amp;periode=$periode&amp;alle_ansatte_id=$alle_ansatte_id\">".findtekst('2786|Lønafregning', $sprog_id)." (".findtekst('934|Beløb', $sprog_id).")"."</a></li>"; #Lønafregning (Beløb)
	if ($alle_ansatte_id && (substr($sag_rettigheder,3,1))) print "<li><a href=\"loen.php?funktion=loenafregning&amp;alle_ansatte_id=".rtrim($alle_ansatte_id, ",")."&amp;periode=$periode&amp;visalle=on\">".findtekst('2979|Lønopgørelse', $sprog_id)." (".findtekst('2498|Alle', $sprog_id)."&nbsp;".lcfirst(findtekst('1262|Ansatte', $sprog_id)).")"."</a></li>"; #Lønopgørelse (Alle ansatte)
	print "<li><a href=\"loen.php?funktion=loenliste\">".findtekst('2787|Lønliste', $sprog_id)."</a></li>";
	if (substr($sag_rettigheder,3,1)) {
		print "<li><hr></li>";
		print "<li><a href=\"ansatte.php\">".ucfirst(findtekst('904|til', $sprog_id))." ".lcfirst(findtekst('1262|Ansatte', $sprog_id))."</a></li>"; #Til ansatte
	}
	
	
?>