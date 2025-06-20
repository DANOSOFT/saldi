<?php
function openpost($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart, $art) 
{
	global $bgcolor;
	global $bgcolor5;
	global $font;
	global $md;
	global $kontoudtog;
	
	$currentdate=date("Y-m-d");

	list ($x, $maaned_fra) = split(" ", $maaned_fra);
	list ($x, $maaned_til) = split(" ", $maaned_til);
	
	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);

	print "$font <a accesskey=t href=\"rapport.php?rapportart=openpost&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til\"><small><small>Luk</small></small></a><br><br>";

	print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
	print "<tr><td>$font<small><small>Kontonr</small></small></td><td>$font<small><small>Firmanavn</small></small></td><td align=right>$font<small><small>>90</small></small></td><td align=right>$font<small><small>60-90</small></small></td><td align=right>$font<small><small>30-60</small></small></td><td align=right>$font<small><small>8-30</small></small></td><td align=right>$font<small><small>0-8</small></small></td><td align=right>$font<small><small>I alt</small></small></td><tr>";


	## Finder start og slut paa regnskabsaar


	for ($x=1; $x<=12; $x++) {
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
	$row = db_fetch_array($query);
#	$regnaar=$row[kodenr];
	$startmaaned=$row[box1]*1;
	$startaar=$row[box2]*1;
	$slutmaaned=$row[box3]*1;
	$slutaar=$row[box4]*1;
	$slutdato=31;

	##

	if ($maaned_fra) $startmaaned=$maaned_fra;
	if ($maaned_til) $slutmaaned=$maaned_til;

	while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
		$slutdato=$slutdato-1;
		if ($slutdato<28) break;
	}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}

	$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
 #$regnslut = "2005-05-04"; 
 
#	echo "$maaned_fra -$maaned_til<br>";

	print "<form name=aabenpost action=rapport.php method=post>";
	print "<tr><td colspan=10><hr></td></tr>";
		
	$x=0;
	$query = db_select("select * from adresser where kontonr>='$konto_fra' and kontonr<='$konto_til' and art = '$art' order by firmanavn");
	while ($row = db_fetch_array($query)) {
		$x++;
		$id[$x]=$row[id];
					print "<input type=hidden name=konto_id[$x] value=$id[$x]>";
#		echo "$id[$x]<br>";
		$kontonr[$x]=trim($row['kontonr']);
		$firmanavn[$x]=stripslashes($row['firmanavn']);
		$addr1[$x]=stripslashes($row['addr1']);
		$addr2[$x]=stripslashes($row['addr2']);
		$postnr[$x]=trim($row['postnr']);
		$bynavn[$x]=stripslashes($row['bynavn']);
		$email[$x]=trim($row['email']);	
	}
	$kontoantal=$x;

	$sum=0;
	for ($x=1; $x<=$kontoantal; $x++) {
		$udlignet=1;
		$rykkerbelob=0;
		$forfalden=0;
		$forfalden_plus8=0;
		$forfalden_plus30=0;
		$forfalden_plus60=0;
		$forfalden_plus90=0;
		$y=0;
		$query = db_select("select * from openpost where konto_id=$id[$x] and transdate <= '$regnslut'");
		while ($row = db_fetch_array($query)) {
			if ($row[udlignet]!=1){
				$udlignet="0";
				$query2 = db_select("select betalingsbet, betalingsdage from ordrer where fakturanr='$row[faktnr]'");
				$row2 = db_fetch_array($query2);
				$forfaldsdag=usdate(forfaldsdag($row[transdate], $row2[betalingsbet], $row2[betalingsdage]));
				$forfaldsdag_plus8=usdate(forfaldsdag($row[transdate], $row2[betalingsbet], $row2[betalingsdage]+8));
				$forfaldsdag_plus30=usdate(forfaldsdag($row[transdate], $row2[betalingsbet], $row2[betalingsdage]+30));
				$forfaldsdag_plus60=usdate(forfaldsdag($row[transdate], $row2[betalingsbet], $row2[betalingsdage]+60));
				$forfaldsdag_plus90=usdate(forfaldsdag($row[transdate], $row2[betalingsbet], $row2[betalingsdage]+90));
				if ($forfaldsdag<$currentdate){$rykkerbelob=$rykkerbelob+$row[amount];}
				if (($forfaldsdag<$currentdate)&&($forfaldsdag_plus8>$currentdate)){$forfalden=$forfalden+$row[amount];}
				if (($forfaldsdag_plus8<=$currentdate)&&($forfaldsdag_plus30>$currentdate)){$forfalden_plus8=$forfalden_plus8+$row[amount];}
				if (($forfaldsdag_plus30<=$currentdate)&&($forfaldsdag_plus60>$currentdate)){$forfalden_plus30=$forfalden_plus30+$row[amount];}
				if (($forfaldsdag_plus60<=$currentdate)&&($forfaldsdag_plus90>$currentdate)){$forfalden_plus60=$forfalden_plus60+$row[amount];}
				if ($forfaldsdag_plus90<=$currentdate){$forfalden_plus90=$forfalden_plus90+$row[amount];}
			}

			$y=$y+$row[amount];
		}
		if (($y>0.01)||($udlignet=="0"))	{	
					if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
					elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
			
			$forfaldsum=$forfaldsum+$forfalden;
			$forfaldsum_plus8=$forfaldsum_plus8+$forfalden_plus8;
			$forfaldsum_plus30=$forfaldsum_plus30+$forfalden_plus30;
			$forfaldsum_plus60=$forfaldsum_plus60+$forfalden_plus60;
			$forfaldsum_plus90=$forfaldsum_plus90+$forfalden_plus90;
			$sum=$sum+$y;
			print "<tr bgcolor=\"$linjebg\">";
			print "<td onClick=\"window.open('rapport.php?rapportart=kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$kontonr[$x]&konto_til=$kontonr[$x]&submit=ok','kreditorrapport','width=640,height=480,scrollbars=1,resizable=1')\">$font<small><small><span title='Klik for detaljer' style=\"text-decoration: underline;\"><a>$kontonr[$x]</a></small></small></td>";
			print "<td>$font<small><small>$firmanavn[$x]</small></small></td>";
			$tmp=dkdecimal($forfalden_plus90);
			print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small><small>$tmp</small></small></td>";
			$tmp=dkdecimal($forfalden_plus60);
			print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small><small>$tmp</small></small></td>";
			$tmp=dkdecimal($forfalden_plus30);
			print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small><small>$tmp</small></small></td>";
			$tmp=dkdecimal($forfalden_plus8);
			print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small><small>$tmp</small></small></td>";
			$tmp=dkdecimal($forfalden);
			print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small><small>$tmp</small></small></td>";
			$tmp=dkdecimal($y);
			if (abs($y)<0.01) {
				print "<td align=right>$font<a accesskey=t href=\"rapport.php?submit=ok&rapportart=openpost&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&udlign=$id[$x]\"><small><small>$tmp</small></small></a></td>";
			}
			else {print "<td align=right>$font<small><small>$tmp</small></small></td>";}
				if ($kontoudtog[$x]==on) {print "<td align=center><input type=checkbox name=kontoudtog[$x] checked>";}
				else print "<td align=center><input type=checkbox name=kontoudtog[$x]>";
			print "</tr>";
		}
		print "<input type=hidden name=rykkerbelob[$x] value=$rykkerbelob>";
	}
	print "<tr><td colspan=10><hr></td></tr>";
	print "<tr><td><br></td><td><small><small>I alt</small></small></td>";
	$tmp=dkdecimal($forfaldsum_plus90);
	print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small><small>$tmp</small></small></td>";
	$tmp=dkdecimal($forfaldsum_plus60);
	print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small><small>$tmp</small></small></td>";
	$tmp=dkdecimal($forfaldsum_plus30);
	print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small><small>$tmp</small></small></td>";
	$tmp=dkdecimal($forfaldsum_plus8);
	print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small><small>$tmp</small></small></td>";
	$tmp=dkdecimal($forfaldsum);
	print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small><small>$tmp</small></small></td>";
	$tmp=dkdecimal($sum);
	print "<td align=right>$font<small><small>$tmp</small></small></td>";
				
	print "<input type=hidden name=rapportart value=\"openpost\">";
	print "<input type=hidden name=regnaar value=$regnaar>";
	print "<input type=hidden name=maaned_fra value=$maaned_fra>";
	print "<input type=hidden name=maaned_til value=$maaned_til>";
	print "<input type=hidden name=konto_fra value=$konto_fra>";
	print "<input type=hidden name=konto_til value=$konto_til>";
	print "<input type=hidden name=kontoantal value=$kontoantal>";


	if ($art=='D') print "<tr><td colspan=10 align=center><small><small><input type=submit value=\"Mail kontoudtog\" name=\"submit\">&nbsp;<input type=submit value=\"Udskriv rykker\" name=\"submit\"></td></tr>";
	print "</form>\n";
	print "<tr><td colspan=10><hr></td></tr>";
 }
?>