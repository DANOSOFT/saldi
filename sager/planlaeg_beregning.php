<?php
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();
	
	$bg="nix";
	$header='nix';

	$menu_sager=NULL;
	$menu_planlaeg='id="menuActive"';
	$menu_dagbog=NULL;
	$menu_kunder=NULL;
	$menu_loen=NULL;
	$menu_ansatte=NULL;
	$menu_certificering=NULL;
	$menu_medarbejdermappe=NULL;

	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	
	$ddato = date("d-m-Y");
	$dateplusone = date('Y-m-d', strtotime('+1 days'));
	$dateplustwo = date('Y-m-d', strtotime('+2 days'));
	$dateplusfive = date('Y-m-d', strtotime('+5 days'));
	//$date = date('d-m-Y', strtotime('+5 days'));
	
	$where1="where status = 'Beregning' and beregn_opret > '0' and beregn_tilbud > '0'";
	$where2="where status = 'Beregning' and beregn_opret > '0' and beregn_tilbud <= '0'";
	
	$x=0;
	$q=db_select("(SELECT * FROM sager $where1 ORDER BY beregn_tilbud ASC) UNION ALL (SELECT * FROM sager $where2 ORDER BY beregn_opret ASC)",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$sag_id[$x]=$r['id'];
		$sag_nr[$x]=$r['sagsnr']*1;
		$sag_beskrivelse[$x]=htmlspecialchars($r['beskrivelse']);
		$sag_firmanavn[$x]=htmlspecialchars($r['firmanavn']);
		$sag_ansvarlig[$x]=htmlspecialchars($r['ref']);
		$sag_omfang[$x]=htmlspecialchars($r['omfang']);
		$sag_oprettet[$x]=htmlspecialchars($r['ref']);
		//$udf_firmanavn[$x]=htmlspecialchars($r['udf_firmanavn']);
		$udf_addr1[$x]=htmlspecialchars($r['udf_addr1']);
		$udf_postnr[$x]=$r['udf_postnr'];
		$udf_bynavn[$x]=htmlspecialchars($r['udf_bynavn']);
		$kontakt[$x]=htmlspecialchars($r['kontakt']);
		$oprettet_af[$x]=htmlspecialchars($r['oprettet_af']);
		$dato[$x]=date("d-m-Y",$r['tidspkt']);
		$tid[$x]=date("H:i",$r['tidspkt']);
		$uge[$x]=date("W",$r['tidspkt']);
		$status[$x]=$r['status'];
		$konto_id[$x]=$r['konto_id'];
		$planfraop[$x]=$r['planfraop'];
		$plantilop[$x]=$r['plantilop'];
		$beregn_opret[$x]=dkdato($r['beregn_opret']);
		$beregn_tilbud[$x]=$r['beregn_tilbud'];
		//$beregn_status[$x]=$r['beregn_status'];
		$beregner[$x]=htmlspecialchars($r['beregner']);
		$beregn_beskrivelse[$x]=htmlspecialchars($r['beregn_beskrivelse']);
	}
	$antal_sager=$x;
	
	

	
		
		
	
	/*
	function randomPassword($length) {
    $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
    $pass = array(); //remember to declare $pass as an array
    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
    for ($i = 0; $i < $length; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass); //turn the array into a string
	}
*/
	//echo randomPassword(8);
	
	
	
	print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
	<html>
	<head>
		<meta http-equiv=\"X-UA-Compatible\" content=\"IE=10\">
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
		<meta name=\"viewport\" content=\"width=1024\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/main_tilbud.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/print.css\" media=\"print\">
		
		<!--[if lt IE 9]>
		<script src=\"http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js\"></script>
		<![endif]-->
		<title>Stillads</title>
	</head>
	<body>
		<div id=\"wrapper\">";
	
	include("../includes/top_sagsmenu.php");
	
	print "<div id=\"breadcrumbbar\">

				<ul id=\"breadcrumb\">
					<li><a href=\"../sager/sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a></li>
					<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>-->";
					print "<li><a href=\"planlaeg.php\" title=\"Tilbage til planlægning menu\">Planlægning</a></li>\n";
					print "<li>Planlægning beregning</li>\n";
					if ($beregn_opret) print "<li style=\"float:right;\"><a href=\"#\" title=\"Print planlægning beregning\" class=\"print-preview\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print planlægning beregning\" class=\"printIcon\" /></a></li>";
				print "</ul>
				
	</div><!-- end of breadcrumbbar -->\n";
	
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div class=\"maincontentLarge\">\n";
	print "<div class=\"skemacontent\">\n";
	if ($beregn_opret) {
		print "<div id=\"printableArea\">\n";
		print "<div class=\"skemadato\">$ddato</div>";
		print "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"skematable\">";
		print "<tr>
						<th>Oprettet</th>
						<th>Adresse</th>
						<th>Beskrivelse</th>
						<th>Afl.&nbsp;dato</th>
						<th>Kontakt</th>
						<th>Kunde</th>
						<th>Beregner</th>
						<th>Sags&nbsp;nr</th>
					</tr>";
		for ($x=1;$x<=$antal_sager;$x++) {
			
			$bgcolor=NULL;
			$btcolor=NULL;
			$beregntilbud=NULL;
			
			($beregner[$x]!=NULL)?$bgcolor='background-color: #7EE37E;':$bgcolor='background-color: #FF8E8E;';
		
			if ($beregn_tilbud[$x]!=NULL && ($beregn_tilbud[$x] >= $dateplustwo && $beregn_tilbud[$x] <= $dateplusfive)) {
				$btcolor='background-color: #FFC18E;';
				$beregntilbud=dkdato($beregn_tilbud[$x]);
			} elseif ($beregn_tilbud[$x]!=NULL && $beregn_tilbud[$x] <= $dateplusone) {
				$btcolor='background-color: #FF8E8E;';
				$beregntilbud=dkdato($beregn_tilbud[$x]);
			} else {
				$beregntilbud=dkdato($beregn_tilbud[$x]);
			}
			
			if ($beregn_tilbud[$x]==NULL) $beregntilbud='HM';
			
		print "<tr>
						<td>$beregn_opret[$x]</td>
						<td style=\"$bgcolor\">$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]</td>
						<td>$beregn_beskrivelse[$x]</td>
						<td style=\"$btcolor\">$beregntilbud</td>
						<td>$kontakt[$x]</td>
						<td>$sag_firmanavn[$x]</td>
						<td>$beregner[$x]</td>
						<td><a href='sager.php?funktion=ret_sag&amp;sag_id=$sag_id[$x]&amp;konto_id=$konto_id[$x]' class=\"printLink\">$sag_nr[$x]</a></td>
					</tr>";
		}
		print "</table>"; // green #7EE37E red #FF8E8E
		print "</div><!-- end of printableArea -->\n";
	} else {
		print "<div style=\"text-align: center;\"><h3>Planlægning til sager med beregning som status!</h3><br><p style=\"font-size:14px;color:#cd3300;\">Her vises dato for oprettelse af sager, og dato for aflevering af tilbud.</p><br><p>For at bruge planlægning beregning, skal sagerne der vises have status beregning. Ved start af sag kan der vælges dato for oprettelse af sagen og afleveringsdato af tilbud under planlægnings information.</p></div>\n";
	}
	print "</div><!-- end of skemacontent -->\n";
	print "</div><!-- end of maincontentLarge -->\n";
	print "</div><!-- end of maincontentLargeHolder -->\n";
	print "</div><!-- end of wrapper -->\n";
	?>
	<script type="text/javascript">
		
		/* javascript funktion til print */
		function printDiv(divName) { // Original code. MÅ IKKE SLETTES!!!!!
			var printContents = document.getElementById(divName).innerHTML;
			var originalContents = document.body.innerHTML;

			document.body.innerHTML = printContents;

			window.print();

			document.body.innerHTML = originalContents;
			return false;
		}
		
	</script>
	
	<?php
	print "
		</body>
	</html>";
			
			
	
	
?>