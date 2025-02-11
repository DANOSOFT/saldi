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
	
	
	print "<li><a href=\"loen.php?funktion=loenafregning&amp;periode=$periode&amp;alle_ansatte_id=$alle_ansatte_id\">Lønafregning (Timer)</a></li>";
	print "<li><a href=\"loen.php?funktion=loenafregning&amp;vis=belob&amp;periode=$periode&amp;alle_ansatte_id=$alle_ansatte_id\">Lønafregning (Beløb)</a></li>";
	if ($alle_ansatte_id && (substr($sag_rettigheder,3,1))) print "<li><a href=\"loen.php?funktion=loenafregning&amp;alle_ansatte_id=".rtrim($alle_ansatte_id, ",")."&amp;periode=$periode&amp;visalle=on\">Lønopgørelse (Alle&nbsp;ansatte)</a></li>";
	print "<li><a href=\"loen.php?funktion=loenliste\">L&oslash;nliste</a></li>";
	if (substr($sag_rettigheder,3,1)) {
		print "<li><hr></li>";
		print "<li><a href=\"ansatte.php\">Til ansatte</a></li>";
	}
	
	
?>