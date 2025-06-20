<?php
// ----------kreditor/kreditorkort.php-(modul nr. 8)--------lap 1.1.2----12.10.2007----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------
	@session_start();
	$s_id=session_id();
	
	$modulnr=8;
	$title="Kreditorkort";
	
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/dkdecimal.php");
	include("../includes/usdecimal.php");
	include("../includes/db_query.php");
		
	$id = $_GET['id'];
	if($_GET['returside']) {
		$returside= $_GET['returside'];
		$ordre_id = $_GET['ordre_id'];
		$fokus = $_GET['fokus'];
	} else $returside="kreditor.php";

	if ($_POST) {
		$submit=trim($_POST['submit']);
		$id=$_POST['id'];
		$kontonr=$_POST['kontonr'];
		$ny_kontonr=addslashes(trim($_POST['ny_kontonr']));
		$firmanavn=addslashes(trim($_POST['firmanavn']));
		$addr1=addslashes(trim($_POST['addr1']));
		$addr2=addslashes(trim($_POST['addr2']));
		$postnr=trim($_POST['postnr']);
		$bynavn=addslashes(trim($_POST['bynavn']));
		$land=addslashes(trim($_POST['land']));
		$kontakt=addslashes(trim($_POST['kontakt']));
		$tlf=trim($_POST['tlf']);
		$fax=trim($_POST['fax']);
		$email=trim($_POST['email']);
		$web=trim($_POST['web']);
		$betalingsbet=$_POST[betalingsbet];
		$cvrnr=trim($_POST['cvrnr']);
		$betalingsdage=$_POST['betalingsdage'];
		$kreditmax=usdecimal($_POST['kreditmax']);
		list ($gruppe) = split (':', $_POST['gruppe']);
		$notes=addslashes(trim($_POST['notes']));
		$ordre_id=$_POST['ordre_id'];
		$returside=$_POST['returside'];
		$fokus=$_POST['fokus'];
		$posnr=$_POST['posnr'];
		$ans_id=$_POST['ans_id'];
		$ans_ant=$_POST['ans_ant'];

		######### Tjekker om kontonr er integer
	
	$temp=str_replace(" ","",$ny_kontonr);
	$tmp2='';
	for ($x=0; $x<strlen($temp); $x++) {
		$y=substr($temp,$x,1);
		if ((ord($y)<48)||(ord($y)>57)) {$y=0;}
		$tmp2=$tmp2.$y;
	}
	$tmp2=$tmp2*1;
	if ($tmp2!=$ny_kontonr) {print "<BODY onLoad=\"javascript:alert('Kontonummer m&aring; kun best&aring; af heltal uden mellemrum')\">";}
	$ny_kontonr=$tmp2;
	
 
	## Tildeler aut kontonr hvis det ikke er angivet
	if (($firmanavn)&&(($ny_kontonr < 1)||(!$ny_kontonr))) {
		if (!$id) {$id="0";}
		$x=0;
		$query = db_select("select kontonr from adresser where art = 'K'	and id != $id order by kontonr");
			while ($row = db_fetch_array($query)) {
			$x++;
			$ktoliste[$x]=$row[kontonr];
		}
		$ny_kontonr=1000;
		while(in_array($ny_kontonr, $ktoliste)) {$ny_kontonr++;}
		print "<BODY onLoad=\"javascript:alert('Kontonummer $ny_kontonr tildelt automatisk')\">";
 }
	
############################

		if ($submit=="Slet") db_modify("delete from adresser where id = $id");
		else {
			if(!$kreditmax){$kreditmax=0;}
			if(!$betalingsdage){$betalingsdage=0;}
			if ($id==0) {
				$query = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'K'");
				$row = db_fetch_array($query);
				if ($row[id]) {
				 print "<BODY onLoad=\"javascript:alert('Der findes allerede en kreditor med Leverand&oslash;rnr: $ny_kontonr')\">";
					$id=0;
				} elseif($ny_kontonr) {
					db_modify("insert into adresser (kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf, fax, email, web, betalingsdage, kreditmax, betalingsbet, cvrnr, notes, art, gruppe) values ('$ny_kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$tlf', '$fax', '$email', '$web', '$betalingsdage', '$kreditmax', '$betalingsbet', '$cvrnr', '$notes', 'K', $gruppe)");
					$query = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'K'");
					$row = db_fetch_array($query);
					$id = $row[id];
				}
			} elseif ($id > 0) {
				if ($ny_kontonr!=$kontonr) {
					$query = db_select("select kontonr from adresser where art = 'K' order by kontonr");
					while ($row = db_fetch_array($query)) {
						$x++;
						$ktoliste[$x]=$row[kontonr];
					}
					if (in_array($ny_kontonr, $ktoliste)) {
						 print "<BODY onLoad=\"javascript:alert('Kontonummer findes allerede, ikke &aelig;ndret')\">";
					} else $kontonr=$ny_kontonr;
				}
				db_modify("update adresser set kontonr = '$kontonr',	firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', land = '$land', kontakt = '$kontakt', tlf = '$tlf', fax = '$fax', email = '$email', web = '$web', betalingsdage= '$betalingsdage', kreditmax = '$kreditmax', betalingsbet = '$betalingsbet', cvrnr = '$cvrnr', notes = '$notes', gruppe = '$gruppe' where id = '$id'");
				for ($x=1; $x<=$ans_ant; $x++) {
					 $y=trim($posnr[$x]);
					 if (($y)&&($y!="-")&&($ans_id[$x])){db_modify("update ansatte set posnr = '$y' where id = '$ans_id[$x]'");}
					 elseif (($y=="-")&&($ans_id[$x])){db_modify("delete from ansatte	where id = '$ans_id[$x]'");}
				 	else {print "<BODY onLoad=\"javascript:alert('Hint!	Du skal s&aelig;tte et - (minus) som pos nr for at slette en kontaktperson')\">";}
				}
			}
		}
	}

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id accesskey=l>Luk</a></small></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>SALDI - Kreditorkort</small></td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=kreditorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id accesskey=N>Ny</a><br></small></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = center>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

	if ($id > 0)
	{
		$query = db_select("select * from adresser where id = '$id'");
		$row = db_fetch_array($query);
		$kontonr=trim($row['kontonr']);
		$firmanavn=htmlentities(stripslashes(trim($row['firmanavn'])));
		$addr1=htmlentities(stripslashes(trim($row['addr1'])));
		$addr2=htmlentities(stripslashes(trim($row['addr2'])));
		$postnr=trim($row['postnr']);
		$bynavn=stripslashes(trim($row['bynavn']));
		$land=htmlentities(stripslashes(trim($row['land'])));
#		$kontakt=htmlentities(stripslashes(trim($row['kontakt'])));
		$tlf=trim($row['tlf']);
		$fax=trim($row['fax']);
		$email=trim($row['email']);
		$web=trim($row['web']);
		$kreditmax=$row['kreditmax'];
		$betalingsdage=$row['betalingsdage'];
		$betalingsbet=trim($row['betalingsbet']);
		$cvrnr=trim($row['cvrnr']);
		$notes=htmlentities(stripslashes(trim($row['notes'])));
		$gruppe=trim($row['gruppe']);
	}
	else
	{
		$id=0;
		$betalingsdage=8;
		$betalingsbet="Netto";
	}

#		$addr1=htmlentities(stripslashes(trim($row['addr1'])));

	$kreditmax=dkdecimal($kreditmax);
	print "<form name=kreditorkort action=kreditorkort.php method=post>";
	print "<input type=hidden name=id value='$id'>";
	print "<input type=hidden name=kontonr value='$kontonr'>";
	print "<input type=hidden name=ordre_id value='$ordre_id'>";
	print "<input type=hidden name=returside value='$returside'>";
	print "<input type=hidden name=fokus value='$fokus'>";

	print "<tr><td>$font Leverand&oslash;rnr</td><td><br></td><td><input type=text size=25 name=ny_kontonr value=\"$kontonr\"></td>";
	print "<td width = 20%><br></td>";
	print "<td>$font Navn</td><td><br></td><td><input type=text size=25 name=firmanavn value=\"$firmanavn\"></td></tr>";
	print "<tr><td>$font Adresse</td><td><br></td><td><input type=text size=25 name=addr1 value=\"$addr1\"></td>";
	print "<td><br></td>";
	print "<td>$font Adresse2</td><td><br></td><td><input type=text size=25 name=addr2 value=\"$addr2\"></td></tr>";
	print "<tr><td>$font Postnr</td><td><br></td><td><input type=text size=6 name=postnr value=\"$postnr\"></td>";
	print "<td><br></td>";
	print "<td>$font By</td><td><br></td><td><input type=text size=25 name=bynavn value=\"$bynavn\"></td></tr>";
#	print "<tr><td>$font Kontaktperson</td><td><br></td><td><input type=text size=25 name=kontakt value=\"$kontakt\"></td>";
#	print "<td><br></td>";
	 print "<tr><td>$font Land</td><td><br></td><td><input type=text size=25 name=land value=\"$land\"></td>";
	print "<td><br></td>";
	print "<td>$font CVR-nr.</td>";
	print "<td><td><input type=text size=10 name=cvrnr value=\"$cvrnr\"></td></tr>";
	print "<tr><td>$font Telefon</td><td><br></td><td><input type=text size=10 name=tlf value=\"$tlf\"></td>";
	print "<td><br></td>";
	print "<td>$font Telefax</td><td><br></td><td><input type=text size=10 name=fax value=\"$fax\"></td></tr>";
	print "<tr><td>$font e-mail</td><td><br></td><td><input type=text size=25 name=email value=\"$email\"></td>";
	print "<td><br></td>";
	print "<td>$font Hjemmeside</td><td><br></td><td><input type=text size=25 name=web value=\"$web\"></td></tr>";	
	print "<tr><td>$font Betalingsbetingelse</td><td><br></td>";
	print "<td><SELECT NAME=betalingsbet>";
	print "<option>$betalingsbet</option>";
	if ($betalingsbet!='Forud') 	{print "<option>Forud</option>"; }
	if ($betalingsbet!='Kontant') 	{print "<option>Kontant</option>"; }
	if ($betalingsbet!='Efterkrav') 	{print "<option>Efterkrav</option>"; }
	if ($betalingsbet!='Netto'){print "<option>Netto</option>"; }
	if ($betalingsbet!='Lb. md.'){print "<option>Lb. md.</option>";}
	if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')||($betalingsbet=='Forud')) {$betalingsdage='';}
	elseif (!$betalingsdage) {$betalingsdage='Nul';}
	if ($betalingsdage){
		if ($betalingsdage=='Nul') {$betalingsdage=0;}
		print "</SELECT>&nbsp;+<input type=text size=2 style=text-align:right	name=betalingsdage value=\"$betalingsdage\"></td>";
	}
	print "<td><br></td>";
	print "<td>$font Kreditmax</td><td><br></td><td><input type=text size=10 name=kreditmax value=\"$kreditmax\"></td></tr>";
	print "<tr><td>$font Kreditorgruppe</td><td>";
	if (!$gruppe) {$gruppe=1;}
	$query = db_select("select beskrivelse from grupper where art='KG' and kodenr='$gruppe'");
	$row = db_fetch_array($query);
	print "<td><SELECT NAME=gruppe value=\"$gruppe\">";
	print "<option>$gruppe:$row[beskrivelse]</option>";
	$query = db_select("select * from grupper where art='KG' and kodenr!='$gruppe' order by kodenr");
	while ($row = db_fetch_array($query))
	{
		print "<option>$row[kodenr]:$row[beskrivelse]</option>";
	}
	print "</SELECT></td>";
	print "<tr><td valign=top>$font Bem&aelig;rkning</td><td colspan=7><textarea name=\"notes\" rows=\"3\" cols=\"85\">$notes</textarea></td></tr>";
	if ($id)
	{
		print "<tr><td></td><td></td><td>$font Pos. Kontakt</td><td>$font Lokalnr. / Mobil</td><td>$font E-mail</td><td>$font <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Ny</a></td></tr>";
		$x=0;
		$query = db_select("select * from ansatte where konto_id = '$id' order by posnr");
		while ($row = db_fetch_array($query))
		{
			$x++;
			if ($x > 0) {print "<tr><td><br></td><td><br></td>";}
			print "<td><input type=text size=1 name=posnr[$x] value=\"$x\">$font &nbsp;<a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&id=$row[id]>".htmlentities(stripslashes($row[navn]))."</a></td>";
			print "<td>$font $row[tlf] / $row[mobil]</td><td>$font $row[email]</td></tr>";
			print "<input type=hidden name=ans_id[$x] value=$row[id]>";
			if ($x==1) {print "<input type=hidden name=kontakt value='$row[navn]'>";}
		}
	}
	print "<input type=hidden name=ans_ant value=$x>";

	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
 $query = db_select("select id from openpost where konto_id = '$id'");
if (db_fetch_array($query)) {$slet="NO";}
$query = db_select("select id from ordrer where konto_id = '$id'");
if (db_fetch_array($query)) {$slet="NO";}
$query = db_select("select id from ansatte where konto_id = '$id'");
if (db_fetch_array($query)) {$slet="NO";}
		 
if ($slet=="NO") {print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td>";}		 
else {print "<td><br></td><td><br><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td><td><br></td><td><input type=submit accesskey=\"s\" value=\"Slet\" name=\"submit\"></td>";}
 
?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
		<table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
			<td width="100%" bgcolor="<?php echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
		</tbody></table>
</td></tr>
</tbody></table>
</body></html>
	
