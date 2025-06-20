<?php
// ----------kreditor/kreditorkort.php-(modul nr. 8)--------lap 1.9.4----16.04.2008----
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();

$modulnr=8;
$title="Kreditorkort\n";

include("../includes/var_def.php");	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
include("../includes/db_query.php");
		
$id=isset($_GET['id'])? $_GET['id']:Null;
$returside=isset($_GET['returside'])? $_GET['returside']:Null;
if($returside) {
	$ordre_id=isset($_GET['ordre_id'])? $_GET['ordre_id']:Null;
	$fokus=isset($_GET['fokus'])? $_GET['fokus']:Null;
	$id=isset($_GET['id'])? $_GET['id']:Null;
} else $returside="kreditor.php\n";

if ($_POST) {
	$submit=trim($_POST['submit']);
	$id=$_POST['id'];
	if ($submit!="Slet") {
		$kontonr=$_POST['kontonr'];
		$ny_kontonr=addslashes(trim($_POST['ny_kontonr']));
		$firmanavn=addslashes(trim($_POST['firmanavn']));
		$addr1=addslashes(trim($_POST['addr1']));
		$addr2=addslashes(trim($_POST['addr2']));
		$postnr=trim($_POST['postnr']);
		$bynavn=addslashes(trim($_POST['bynavn']));
		$land=addslashes(trim($_POST['land']));
		$kontakt=addslashes(trim(isset($_POST['kontakt'])? $_POST['kontakt']:NULL));
		$tlf=trim($_POST['tlf']);
		$fax=trim($_POST['fax']);
		$email=trim($_POST['email']);
		$web=trim($_POST['web']);
		$betalingsbet=$_POST['betalingsbet'];
		$cvrnr=trim($_POST['cvrnr']);
		$betalingsdage=$_POST['betalingsdage'];
		$bank_navn=trim($_POST['bank_navn']);
		$bank_reg=trim($_POST['bank_reg']);
		$bank_konto=trim($_POST['bank_konto']);
		$bank_fi=trim($_POST['bank_fi']);
		list($erh) = split(" = ",$_POST['erh']);
		$swift=trim($_POST['swift']);
		$kreditmax=usdecimal($_POST['kreditmax']);
		list ($gruppe) = split (':', $_POST['gruppe']);
		$notes=addslashes(trim($_POST['notes']));
		$ordre_id=$_POST['ordre_id'];
		$returside=$_POST['returside'];
		$fokus=$_POST['fokus'];
		$posnr=isset($_POST['posnr'])? $_POST['posnr']:NULL;
		$ans_id=isset($_POST['ans_id'])? $_POST['ans_id']:NULL;
		$ans_ant=isset($_POST['ans_ant'])? $_POST['ans_ant']:NULL;	

		######### Tjekker om kontonr er integer
		$temp=str_replace(" ","",$ny_kontonr);
		$tmp2='';
		for ($x=0; $x<strlen($temp); $x++) {
			$y=substr($temp,$x,1);
			if ((ord($y)<48)||(ord($y)>57)) $y=0;
			$tmp2=$tmp2.$y;
		}
		$tmp2=$tmp2*1;
		if ($tmp2!=$ny_kontonr) {print "<BODY onLoad=\"javascript:alert('Kontonummer m&aring; kun best&aring; af heltal uden mellemrum')\">\n";}
		$ny_kontonr=$tmp2;
	
 
		## Tildeler aut kontonr hvis det ikke er angivet
		if (($firmanavn)&&(($ny_kontonr < 1)||(!$ny_kontonr))) {
			if (!$id) $id="0";
			$x=0;
			$query = db_select("select kontonr from adresser where art = 'K'	and id != $id order by kontonr");
			while ($row = db_fetch_array($query)) {
				$x++;
				$ktoliste[$x]=$row[kontonr];
			}
			$ny_kontonr=1000;
			while(in_array($ny_kontonr, $ktoliste)) $ny_kontonr++;
			print "<BODY onLoad=\"javascript:alert('Kontonummer $ny_kontonr tildelt automatisk')\">\n";
		} 
	
############################
		if(!$kreditmax) $kreditmax=0;
		if(!$betalingsdage) $betalingsdage=0;
		if ($id==0) {
			$query = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'K'");
			$row = db_fetch_array($query);
			if ($row[id]) {
			 print "<BODY onLoad=\"javascript:alert('Der findes allerede en kreditor med Leverand&oslash;rnr: $ny_kontonr')\">\n";
				$id=0;
			} elseif($ny_kontonr) {
				db_modify("insert into adresser (kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,tlf,fax,email,web,betalingsdage,kreditmax,betalingsbet,cvrnr,notes,art,gruppe,bank_navn,bank_reg,bank_konto,bank_fi,erh,swift) values ('$ny_kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$tlf','$fax','$email','$web','$betalingsdage','$kreditmax','$betalingsbet','$cvrnr','$notes','K',$gruppe,'$bank_navn','$bank_reg','$bank_konto','$bank_fi','$erh','$swift')");
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
					 print "<BODY onLoad=\"javascript:alert('Kontonummer findes allerede, ikke &aelig;ndret')\">\n";
				} else $kontonr=$ny_kontonr;
			}
			db_modify("update adresser set kontonr = '$kontonr',	firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', land = '$land', kontakt = '$kontakt', tlf = '$tlf', fax = '$fax', email = '$email', web = '$web', betalingsdage= '$betalingsdage', kreditmax = '$kreditmax', betalingsbet = '$betalingsbet', cvrnr = '$cvrnr', notes = '$notes', gruppe = '$gruppe' ,bank_navn='$bank_navn',bank_reg='$bank_reg',bank_konto='$bank_konto',bank_fi='$bank_fi',erh='$erh',swift='$swift' where id = '$id'");
			for ($x=1; $x<=$ans_ant; $x++) {
				 $y=trim($posnr[$x]);
				 if (($y)&&($y!="-")&&($ans_id[$x])){db_modify("update ansatte set posnr = '$y' where id = '$ans_id[$x]'");}
				 elseif (($y=="-")&&($ans_id[$x])){db_modify("delete from ansatte	where id = '$ans_id[$x]'");}
			 	else {print "<BODY onLoad=\"javascript:alert('Hint!	Du skal s&aelig;tte et - (minus) som pos nr for at slette en kontaktperson')\">\n";}
			}
		}
	}
	else {
		db_modify("delete from adresser where id = $id");
		$id=0;
	}
}
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";#tabel 1 start
print "<tr><td colspan=\"3\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";#tabel 1.1 start
print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\"$top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id accesskey=L>Luk</a></small></td>";
#print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id accesskey=l>Luk</a></small></td>\n";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>SALDI - Kreditorkort</small></td>\n";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=kreditorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id accesskey=N>Ny</a><br></small></td>\n";
print "</tbody></table>\n";#tabel 1.1 slut
print "</td></tr>\n";
print "<td></td><td align = center valign = center>\n";
print "<table width=\"830\" cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>\n";#tabel 1.2 start

if ($id > 0){
	$query = db_select("select * from adresser where id = '$id'");
	$row = db_fetch_array($query);
	$kontonr=trim($row['kontonr']);
	$firmanavn=htmlentities(stripslashes(trim($row['firmanavn'])));
	$addr1=htmlentities(stripslashes(trim($row['addr1'])));
	$addr2=htmlentities(stripslashes(trim($row['addr2'])));
	$postnr=trim($row['postnr']);
	$bynavn=stripslashes(trim($row['bynavn']));
	$land=htmlentities(stripslashes(trim($row['land'])));
	$kontakt=htmlentities(stripslashes(trim($row['kontakt'])));
	$tlf=trim($row['tlf']);
	$fax=trim($row['fax']);
	$email=trim($row['email']);
	$web=trim($row['web']);
	$kreditmax=$row['kreditmax'];
	$betalingsdage=$row['betalingsdage'];
	$betalingsbet=trim($row['betalingsbet']);
	$bank_navn=trim($row['bank_navn']);
	$bank_reg=trim($row['bank_reg']);
	$bank_konto=trim($row['bank_konto']);
	$bank_fi=trim($row['bank_fi']);
	$erh=trim($row['erh']);
	$swift=trim($row['swift']);
	$cvrnr=trim($row['cvrnr']);
	$notes=htmlentities(stripslashes(trim($row['notes'])));
	$gruppe=trim($row['gruppe']);
}else	{
	$id=0;
	$betalingsdage=8;
	$betalingsbet="Netto\n";
}

#		$addr1=htmlentities(stripslashes(trim($row['addr1'])));

$kreditmax=dkdecimal($kreditmax);
print "<form name=kreditorkort action=kreditorkort.php method=post>\n";
print "<input type=hidden name=id value='$id'>\n";
print "<input type=hidden name=kontonr value='$kontonr'>\n";
print "<input type=hidden name=ordre_id value='$ordre_id'>\n";
print "<input type=hidden name=returside value='$returside'>\n";
print "<input type=hidden name=fokus value='$fokus'>\n";
print "<tr><td colspan=2><table border=\"0\"><tbody>\n"; # tabel 1.2.1
print "<tr><td width=130>$font Leverand&oslash;rnr</td><td><br></td><td><input type=text size=25 name=ny_kontonr value=\"$kontonr\"></td>\n";
print "<td width = 20%><br></td>\n";
print "<td>$font Navn</td><td><br></td><td><input type=text size=25 name=firmanavn value=\"$firmanavn\"></td></tr>\n";
print "<tr><td>$font Adresse</td><td><br></td><td><input type=text size=25 name=addr1 value=\"$addr1\"></td>\n";
print "<td><br></td>\n";
print "<td>$font Adresse2</td><td><br></td><td><input type=text size=25 name=addr2 value=\"$addr2\"></td></tr>\n";
print "<tr><td>$font Postnr</td><td><br></td><td><input type=text size=6 name=postnr value=\"$postnr\"></td>\n";
print "<td><br></td>\n";
print "<td>$font By</td><td><br></td><td><input type=text size=25 name=bynavn value=\"$bynavn\"></td></tr>\n";
print "<tr><td>$font Land</td><td><br></td><td><input type=text size=25 name=land value=\"$land\"></td>\n";
print "<td><br></td>\n";
print "<td>$font CVR-nr.</td>\n";
print "<td><td><input type=text size=10 name=cvrnr value=\"$cvrnr\"></td></tr>\n";
print "<tr><td>$font Telefon</td><td><br></td><td><input type=text size=10 name=tlf value=\"$tlf\"></td>\n";
print "<td><br></td>\n";
print "<td>$font Telefax</td><td><br></td><td><input type=text size=10 name=fax value=\"$fax\"></td></tr>\n";
print "<tr><td>$font e-mail</td><td><br></td><td><input type=text size=25 name=email value=\"$email\"></td>\n";
print "<td><br></td>\n";
print "<td>$font Hjemmeside</td><td><br></td><td><input type=text size=25 name=web value=\"$web\"></td></tr>\n";	
print "<tr><td>$font Betalingsbetingelse</td><td><br></td>\n";
print "<td><SELECT NAME=betalingsbet>\n";
print "<option>$betalingsbet</option>\n";
if ($betalingsbet!='Forud') print "<option>Forud</option>\n";
if ($betalingsbet!='Kontant') print "<option>Kontant</option>\n";
if ($betalingsbet!='Efterkrav') print "<option>Efterkrav</option>\n";
if ($betalingsbet!='Netto') print "<option>Netto</option>\n";
if ($betalingsbet!='Lb. md.') print "<option>Lb. md.</option>\n";
if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')||($betalingsbet=='Forud')) $betalingsdage='';
elseif (!$betalingsdage) $betalingsdage='Nul';
if ($betalingsdage){
	if ($betalingsdage=='Nul') $betalingsdage=0;
	print "</SELECT>&nbsp;+<input type=text size=2 style=text-align:right	name=betalingsdage value=\"$betalingsdage\"></td>\n";
}
print "<td><br></td>\n";
print "<td>$font Kreditmax</td><td><br></td><td><input type=text size=10 name=kreditmax value=\"$kreditmax\"></td></tr>\n";
print "<tr><td>$font Kreditorgruppe</td><td>\n";
if (!$gruppe) {$gruppe=1;}
$query = db_select("select beskrivelse from grupper where art='KG' and kodenr='$gruppe'");
$row = db_fetch_array($query);
print "<td><SELECT NAME=gruppe value=\"$gruppe\">\n";
print "<option>$gruppe:$row[beskrivelse]</option>\n";
$query = db_select("select * from grupper where art='KG' and kodenr!='$gruppe' order by kodenr");
while ($row = db_fetch_array($query)){
	print "<option>$row[kodenr]:$row[beskrivelse]</option>\n";
}
print "</SELECT></td>\n";
print "</tbody></table></td>\n"; # tabel 1.2.1 slut
print "<td width=140><table border=0><tbody>\n"; # tabel 1.2.2 start
print "<tr><td><br></td></tr>\n";
print "<tr><td>$font Bank</td><td><input type=\"text\" name=\"bank_navn\" size=\"25\" value=\"$bank_navn\"></td></tr>\n";
print "<tr><td>$font Reg.</td><td><input type=t\"ext\" name=\"bank_reg\" size=\"6\" value=\"$bank_reg\"></td></tr>\n";
print "<tr><td>$font<span title=\"For udenlandske kreditorer skrives IBAN nummer her\">Konto</span></td><td><input type=\"text\" name=\"bank_konto\" size=\"25\" value=\"$bank_konto\"></td></tr>\n";
print "<tr><td>$font FI&nbsp;kreditor&nbsp;nr.</td><td><input type=\"text\" name=\"bank_fi\" size=\"25\" value=\"$bank_fi\"></td></tr>\n";
print "<tr><td>$font ERH Kode</td>";
print "<td><SELECT NAME=erh>\n";
if ($erh=='ERH351') print "<option>ERH351 = FI kort 71</option>\n";
if ($erh=='ERH352') print "<option>ERH352 = FI kort 04 & 15</option>\n";
if ($erh=='ERH354') print "<option>ERH354 = FI kort 01 & 41</option>\n";
if ($erh=='ERH355') print "<option>ERH355 = Bankoverf. med normal advisering</option>\n";
if ($erh=='ERH356') print "<option>ERH356 = Bankoverf. med straks advisering</option>\n";
if ($erh=='ERH357') print "<option>ERH357 = FI kort 73</option>\n";
if ($erh=='ERH358') print "<option>ERH358 = FI kort 75</option>\n";
if ($erh=='ERH400') print "<option>ERH400 = Udenlandsk overf&oslash;rsel</option>\n";
if ($erh!='ERH351') print "<option>ERH351 = FI kort 71</option>\n";
if ($erh!='ERH352') print "<option>ERH352 = FI kort 04 & 15</option>\n";
if ($erh!='ERH354') print "<option>ERH354 = FI kort 01 & 41</option>\n";
if ($erh!='ERH355') print "<option>ERH355 = Bankoverf. med normal advisering</option>\n";
if ($erh!='ERH356') print "<option>ERH356 = Bankoverf. med straks advisering</option>\n";
if ($erh!='ERH357') print "<option>ERH357 = FI kort 73</option>\n";
if ($erh!='ERH358') print "<option>ERH358 = FI kort 75</option>\n";
if ($erh!='ERH400') print "<option>ERH400 = Udenlandsk overf&oslash;rsel</option>\n";
print "</SELECT></td></tr>\n";
print "<tr><td>$font<span title=\"Anvendes kun ved udenlandske kreditorer som ikke har et IBAN nr.\">SWIFT nr</span></td><td><input type=\"text\" name=\"swift\" size=\"25\" value=\"$swift\"></td></tr>\n";
print "<tr><td><br></td></tr>\n";
print "</tbody></table></td></tr>\n";#tabel 1.2.2 slut
print "<tr><td colspan=3><table><tbody></td></tr>\n";#tabel 1.2.3 start
print "<tr><td valign=top width=130>$font Bem&aelig;rkning</td><td colspan=2><textarea name=\"notes\" rows=\"5\" cols=\"125\">$notes</textarea></td></tr>\n";
if ($id) {
	print "<tr><td></td><td colspan=2><table width=700 border=0><tbody>\n"; #tabel 3.3.1 start
	print "<tr><td>$font Pos. Kontakt</td><td>$font Lokalnr. / Mobil</td><td>$font E-mail</td><td>$font <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Ny</a></td></tr>\n";
	$x=0;
	$query = db_select("select * from ansatte where konto_id = '$id' order by posnr");
	while ($row = db_fetch_array($query)){
		$x++;
		print "<td><input type=text size=1 name=posnr[$x] value=\"$x\">$font &nbsp;<a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&id=$row[id]>".htmlentities(stripslashes($row['navn']))."</a></td>\n";
		print "<td>$font $row[tlf] / $row[mobil]</td><td>$font $row[email]</td></tr>\n";
		print "<input type=hidden name=ans_id[$x] value=$row[id]>\n";
		if ($x==1) print "<input type=hidden name=kontakt value='$row[navn]'>\n";
	}
}
print "<input type=hidden name=ans_ant value=$x>\n";
print "</tbody></table>\n"; #tabel 1.3.1 slut
print "<tr><td colspan=3><br></td></tr>\n";
print "<tr><td colspan=3><br></td></tr>\n";
$query = db_select("select id from openpost where konto_id = '$id'");
if (db_fetch_array($query)) $slet="NO";
$query = db_select("select id from ordrer where konto_id = '$id'");
if (db_fetch_array($query)) $slet="NO";
$query = db_select("select id from ansatte where konto_id = '$id'");
if (db_fetch_array($query)) $slet="NO";
		 
if ($slet=="NO") {print "<tr><td colspan=3 align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td>\n";}		
else {print "<tr><td colspan=3 align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\">&nbsp;&nbsp;&nbsp;<input type=submit accesskey=\"s\" value=\"Slet\" name=\"submit\"></td>\n";}
print	"</tbody></table>";#tabel 1.2.3 slut
print	"</td></tr>";
print	"</tbody></table>";#tabel 1.2 slut
print	"<tr><td colspan=\"3\" align=\"center\" valign=\"bottom\">";
print	"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";#tabel 1.3. start
print	"<td style=\"border: 1px solid #b4b4ff; padding: 0pt 0pt 1px;\" align=\"left\" background=\"../img/grey1.gif\" width=\"100%\"><small><br></small></td>";
print	"</tbody></table>";#tabel 1.3 slut
print	"</td></tr>";
print	"</tbody></table>";#tabel 1 slut
print	"</body></html>";
 
	
