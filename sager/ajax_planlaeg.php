<?php
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

	
	$bg="nix";
	$header='nix';

	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	
	$id=if_isset($_POST['id']);
	$planfra=if_isset($_POST['planfra']);
	$plantil=if_isset($_POST['plantil']);
	$op=if_isset($_POST['op']);
	

if ($id && ($op==1)) {
	db_modify("update sager set planfraop='$planfra', plantilop='$plantil' where id = '$id'",__FILE__ . " linje " . __LINE__);
}
if ($id && ($op==0)) {
	db_modify("update sager set planfraned='$planfra', plantilned='$plantil' where id = '$id'",__FILE__ . " linje " . __LINE__);
}
	// Skal ikke bruges, da det ikke er nødvendigt med callback. 
	/*
	$items = NULL;
	$ganttdata = NULL;
	$where="where status != 'Tilbud' and status != 'Afsluttet' and planfra > '0' and plantil > '0'";
	
	$x=0;
	$q=db_select("SELECT * FROM sager $where ORDER BY id DESC",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$sag_id[$x]=$r['id'];
		$sag_nr[$x]=$r['sagsnr']*1;
		$sag_beskrivelse[$x]=htmlspecialchars($r['beskrivelse']);
		$sag_firmanavn[$x]=htmlspecialchars($r['firmanavn']);
		$sag_ansvarlig[$x]=htmlspecialchars($r['ref']);
		$sag_omfang[$x]=htmlspecialchars($r['omfang']);
		$sag_oprettet[$x]=htmlspecialchars($r['ref']);
		$udf_firmanavn[$x]=htmlspecialchars($r['udf_firmanavn']);
		$udf_addr1[$x]=htmlspecialchars($r['udf_addr1']);
		$udf_postnr[$x]=$r['udf_postnr'];
		$udf_bynavn[$x]=htmlspecialchars($r['udf_bynavn']);
		$oprettet_af[$x]=htmlspecialchars($r['oprettet_af']);
		$dato[$x]=date("d-m-y",$r['tidspkt']);
		$tid[$x]=date("H:i",$r['tidspkt']);
		$status[$x]=$r['status'];
		$konto_id[$x]=$r['konto_id'];
		$planfraA[$x]=$r['planfra'];
		$plantilA[$x]=$r['plantil'];
	}
	$antal_sager=$x;
	
	

	for ($x=1;$x<=$antal_sager;$x++) {
	
		list($daystart, $monthstart,  $yearstart) = explode("-",$planfraA[$x]);
		$newmonthstart = sprintf('%02d',$monthstart-1);
		
		list($dayend, $monthend,  $yearend) = explode("-",$plantilA[$x]);
		$newmonthend = sprintf('%02d',$monthend-1);
	
		$items .= "{ series: [{ id: \"$sag_id[$x]\", name: \"<a href='sager.php?funktion=vis_sag&sag_id=$sag_id[$x]&konto_id=$konto_id[$x]' title='Sag:		$sag_nr[$x]\\nKunde:		$sag_firmanavn[$x]\\nUdf. adr.:	$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]\\nAnsvarlig:	$sag_ansvarlig[$x]\\nStatus:		$status[$x]'>$udf_addr1[$x]<\/a>\", title: \"Planlagt\", start: new Date($yearstart,$newmonthstart,$daystart), end: new Date($yearend,$newmonthend,$dayend)}]},";
	
	}
	$ganttdata = "[".rtrim($items, ",")."]";

	 echo "$ganttdata";*/



?>