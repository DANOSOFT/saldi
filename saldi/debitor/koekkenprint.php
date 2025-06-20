<?php
// -------------debitor/pos_print/koekkenprint.php----------lap 3.5.6-----2015-06-16----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// -----------------------------------------------------------------------
// 20140903 - Rettet diverse pga antal blev stillet tilbage efter 3. klik
// 20140904 - Indsat 3x linjeafstand på udskrift.
// 20141030 - Mange ændringer til understøttelse af flere køkkener.
// 20141202 -	Sat printfil='' i alle kald til
// 20150616 - Understøtter nu til og fravalg Søg 'tilfravalg'
// 20150627	- Der kan nu sendes besked til køkken. Søg besked

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Køkkenprint";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

print "<div align=\"center\">";

$id=if_isset($_GET['id']);
$bordnr=if_isset($_GET['bordnr']);
$bordnavn=if_isset($_GET['bordnavn']);
$next=if_isset($_GET['next']);

$url="://".$_SERVER['SERVER_NAME'];
if (!strpos($url,$_SERVER['PHP_SELF'])) $url.=$_SERVER['PHP_SELF'];
$url=str_replace("/debitor/koekkenprint.php","",$url);
if ($_SERVER['HTTPS']) $url="s".$url;
$url="http".$url;

if ($next) {
	$bon='';
	$pfnavn="../temp/".$db."/".abs($bruger_id).".$next";
#cho "$pfnavn<br>";
	if (file_exists($pfnavn)) {
#cho "den findes<br>";
		$x=0;
		$fp=fopen("$pfnavn","r");
		while($linje=fgets($fp)) {
			if ($x==0) $kpr=trim($linje);
			else $bon.=$linje;
			$x++;
		}
		fclose($fp);
		$bon=urlencode($bon);
		unlink($pfnavn);
		$next++;
	} else {
		$next=NULL;
	}
	if ($next) {
		$pfnavn="../temp/".$db."/".abs($bruger_id).".$next";
		if (!file_exists($pfnavn)) $next=NULL; 
	}
	if ($kpr) {
		if ($next) $url="http://$kpr/saldiprint.php?printfil=&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&returside=$url/debitor/koekkenprint.php&next=$next&gem=0";
		else $url="http://$kpr/saldiprint.php?printfil=&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&returside=$url/debitor/pos_ordre.php&next=$next";
	} else $url="$url/borlplaner/bordplan.php?id=$id";
 	print "<meta http-equiv=\"refresh\" content=\"0;URL=$url\">\n";
}	

$x=0;
$q=db_select("select * from grupper where art='V_CAT' order by box1",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array ($q)) {
	if (substr($r['box1'],0,1)=='K' && strlen($r['box1'])=='2') {
		$cat_id[$x]=$r['id'];
		$cat[$x]=$r['box1'];
#cho "$cat_id[$x] $cat[$x]<br>"; 
		$x++;
	}
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td colspan=\"3\" height = \"100\" align=\"center\" valign=\"top\" width=\"100%\">";
print "<a href=pos_ordre.php?id=$id accesskey=L><input style=\"width:200;height:50\" type=\"button\" value=\"LUK\"></a></td>";

#print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
#print "<td width=\"10%\"$top_bund><a href=pos_ordre.php?id=$id accesskey=L>Luk</a></td>";
#print "<td width=\"80%\"$top_bund>$title</td>";
#print "<td width=\"10%\"$top_bund><br></td>";
#print "</tbody></table>";
print "</td></tr>";


if ($_POST['linje_id']) {
	$linje_id=$_POST['linje_id'];
	$antal=$_POST['antal'];
	$leveres=$_POST['leveres'];
	$leveret=$_POST['leveret'];
	$beskrivelse=$_POST['beskrivelse'];
	$tilfravalg=$_POST['tilfravalg'];
	$notes=$_POST['notes'];
	$koekkenprinter=$_POST['koekkenprinter'];
	$koekkennr=$_POST['koekkennr'];
	$kategori=$_POST['kategori'];
	$besked=$_POST['besked'];

	if ($_POST['send_bestilling']) {
		$bestil=$_POST['bestil'];
		include("../includes/ConvertCharset.class.php");
		if ($db_encode=="UTF8") $FromCharset = "UTF-8";
		else $FromCharset = "iso-8859-15";
		$ToCharset = "cp865";
		$convert = new ConvertCharset();
		$skriv=0;

		for ($x=0;$x<count($linje_id);$x++) {
			$tmp="bestil_".$x;
			$tmp=$_POST[$tmp];
			if ($tmp || $tmp=='0') $bestil[$x]=$tmp;
			if ($bestil[$x]) {
				$skriv=1;
				$bestil[$x]=$convert ->Convert("$bestil[$x]", $FromCharset, $ToCharset);
				$beskrivelse[$x]=$convert ->Convert("$beskrivelse[$x]", $FromCharset, $ToCharset);
				if (strlen($bestil[$x])>5) $bestil[$x]=substr($bestil[$x],-5);
				while(strlen($bestil[$x])<5) $bestil[$x]=' '.$bestil[$x];
				if (strlen($beskrivelse[$x])>35) $b=substr($beskrivelse[$x],0,34);
			}
		}
		if ($skriv) {
			for ($x=0;$x<count($linje_id);$x++) {
#cho "B $bestil[$x]<br>";
				if ($bestil[$x]) {
					$tmp=$leveret[$x]+$bestil[$x];
					if ($tmp>$antal) $tmp=$antal;
					if ($tmp<0) $tmp=0;
					db_modify("update ordrelinjer set leveret='$tmp' where id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
#					for ($y=0;$y<count($kategori[$x]);$y++) {
#						cho "$beskrivelse[$x] -> kat ". $kategori[$x][$y] ."<br>";
#					}
				}
			}
			$koekkenprinter=strtolower($koekkenprinter);
#cho "$koekkenprinter<br>";
			if (strpos($koekkenprinter,",")) {
				$kp=array();
				$kp=explode(",",$koekkenprinter);
#cho "$kp[0] $kp[1]<br>";
			} elseif (strpos($koekkenprinter,":")) {
				$kp=array();
				$kp=explode(":",$koekkenprinter);
#cho "$kp[0] $kp[1]<br>";
			} elseif (strpos($koekkenprinter,";")) {
				$kp=array();
				$kp=explode(";",$koekkenprinter);
#cho "$kp[0] $kp[1]<br>";
			} else {
				if ($koekkenprinter=='box') {
					$filnavn="http://saldi.dk/kasse/koekken_".$_SERVER['REMOTE_ADDR'].".ip";
					if ($fp=fopen($filnavn,'r')) {
						$kp[0]=trim(fgets($fp));
						fclose ($fp);
					} else {
						$filnavn="http://saldi.dk/kasse/K1_".$_SERVER['REMOTE_ADDR'].".ip";
						if ($fp=fopen($filnavn,'r')) {
							$kp[0]=trim(fgets($fp));
							fclose ($fp);
						}
					}
				} else $kp[0]=$koekkenprinter;
			} 
			$udskrives=array();
			for($y=0;$y<count($kp);$y++) {
				$udskrives[$y]=0;
				for ($x=0;$x<count($linje_id);$x++) {
// Hvis der er bestilling på varen OG (der er flere køkkener og varen har køkkenet som kategori ELLER der ikke er defineret køkken kategorier);
					if ($bestil[$x] && (in_array ($cat_id[$y],$kategori[$x]) || count($cat_id)<1)) $udskrives[$y]=1;
				}
				$kp[$y]=strtolower($kp[$y]);
				if (trim($kp[$y])=='box') {
					$z=$y+1;
					$filnavn="http://saldi.dk/kasse/K".$z."_".$_SERVER['REMOTE_ADDR'].".ip";
					if ($fp=fopen($filnavn,'r')) {
						$kp[$y]=trim(fgets($fp));
						fclose ($fp);
					}
				}
				$pfnavn="../temp/".$db."/".abs($bruger_id).".$y";
				$fp=fopen("$pfnavn","w");
				if ($y) fwrite($fp,"$kp[$y]\n");
				if ($udskrives[$y]) {
					$txt=$convert ->Convert("******   BESTILLING   ******", $FromCharset, $ToCharset);
					while (strlen($txt)<40) $txt=" ".$txt." ";
					fwrite($fp,"$txt\n");
					if (count($kp)) {
						$txt="Køkken ";
						$txt.= $y+1;
						$txt=$convert ->Convert($txt, $FromCharset, $ToCharset);
						while (strlen($txt)<40) $txt=" ".$txt." ";
						fwrite($fp,"$txt\n");
					}
					fwrite($fp,"\nD. ".date("d.m.Y")." kl. ".(date("H:i"))."\n\n");  
					$txt=$convert ->Convert("Bord:       $bordnavn", $FromCharset, $ToCharset);
					fwrite($fp,"$txt\n\n");
					$txt=$convert ->Convert("Bestilt af: $brugernavn", $FromCharset, $ToCharset);
					fwrite($fp,"$txt\n\n");
					if ($besked) {
						fwrite($fp,"----------------------------------------\n");
						$txt=$convert ->Convert("BESKED YIL KØKKEN!", $FromCharset, $ToCharset);
						while (strlen($txt)<40) $txt=" ".$txt." ";
						fwrite($fp,"$txt\n\n");
						$ord=explode(' ',$besked);
						$linje=array();
						$l=0;
						$linje[$l]=$ord[0];
						for ($o=1;$o<count($ord);$o++) {
							if (strlen($linje[$l]." ".$ord[$o]) <= 40 && $o<count($ord)) $linje[$l].=" ".$ord[$o];
							else {
								$l++;
								$linje[$l]=$ord[$o]; 
							}
						}
						for ($l=0;$l<count($linje);$l++) {
							$txt=$convert ->Convert($linje[$l], $FromCharset, $ToCharset);
#							while (strlen($txt)<40) $txt=" ".$txt." ";
							fwrite($fp,"$txt\n");
						}
						fwrite($fp,"----------------------------------------\n\n");
					}
					fwrite($fp,"Antal  Beskrivelse\n");
					fwrite($fp,"----------------------------------------\n");
					for ($x=0;$x<count($linje_id);$x++) {
						if ($bestil[$x] && (in_array ($cat_id[$y],$kategori[$x]) || count($cat_id)<1)) {
							fwrite($fp,"$bestil[$x]  $beskrivelse[$x]\n");
							if ($tilfravalg[$x]){
								$tfv=explode(chr(9),$tilfravalg[$x]);
								for ($t=0;$t<count($tfv);$t++){
									$r=db_fetch_array(db_select("select beskrivelse from varer where id = '$tfv[$t]'",__FILE__ . " linje " . __LINE__));
									$txt=$convert ->Convert("$r[beskrivelse]", $FromCharset, $ToCharset);
									fwrite($fp,"     $txt\n");
								}
							}
							fwrite($fp,"$notes[$x]\n\n\n\n");
							fwrite($fp,"----------------------------------------\n");
						}
					}
					fwrite($fp,"\n\n\n");
			#cho "$bestil[$x]=$tmp<br>";
#		else $bestil[$x]=$antal[$x]; 
				}
				fclose($fp);
				$bon='';
			}
		} else print "<BODY onLoad=\"javascript:alert('Der er ikke noget på bestillingslisten til køkken for $bordnavn')\">\n";
		(count($kp)>1)?$next=1:$next=NULL;
		$pfnavn="../temp/".$db."/".abs($bruger_id).".0";
		$fp=fopen("$pfnavn","r");
		while($linje=fgets($fp))$bon.=$linje;
		fclose($fp);
		$bon=urlencode($bon);
		if ($next) $url="http://$kp[0]/saldiprint.php?printfil=&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&returside=$url/debitor/koekkenprint.php&next=$next&gem=1";
		else $url="http://$kp[0]/saldiprint.php?printfil=&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&returside=$url/debitor/pos_ordre.php&next=$next&gem=0";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$url\">\n";
		exit;
		print $y+1 ."<br>";
	}
} else {
	if (!$bordnr && $bordnr!='0') {
		$r=db_fetch_array(db_select("select nr,hvem from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		$bordnr=$r['nr'];
		$hvem=$r['hvem'];
	}
	$r = db_fetch_array(db_select("select box7,box10 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__)); 
	$koekkenprinter=$r['box10'];
	if (($bordnr || $bordnr=='0') && !$bordnavn) {
		($r['box7'])?$bord=explode(chr(9),$r['box7']):$bord=NULL;
		$bordnavn=$bord[$bordnr];
	}
	
	$x=0;
	$q=db_select("select ordrelinjer.id,ordrelinjer.antal,ordrelinjer.leveres,ordrelinjer.leveret,ordrelinjer.beskrivelse,ordrelinjer.tilfravalg,varer.notes,varer.kategori from ordrelinjer,varer where ordrelinjer.ordre_id='$id' and ordrelinjer.vare_id=varer.id  order by ordrelinjer.posnr desc",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array ($q)) {
		$linje_id[$x]=$r['id'];
		$antal[$x]=$r['antal']*1;
		$leveres[$x]=$r['leveres']*1;
		$leveret[$x]=$r['leveret']*1;
		$beskrivelse[$x]=$r['beskrivelse'];
		$tilfravalg[$x]=$r['tilfravalg'];
		$notes[$x]=$r['notes'];
		$kategori[$x]=explode(chr(9),$r['kategori']);
		for ($y=0;$y<count($cat_id);$y++) {
			if (in_array($cat_id[$y],$kategori[$x])) {
				$koekkennr[$x]=substr($cat[$y],1);
			}
		}
		$x++;
	}
}

print "<tr><td width=\"50%\" align=\"right\" valign=\"top\"><table cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tbody>";
print "<form name=koekkenprint align=\"center\" action=\"koekkenprint.php?id=$id&bordnr=$bordnr&bordnavn=$bordnavn\" method=post autocomplete=\"off\">\n";
print "<tr><td colspan=\"4\" align=\"center\">Besked til køkken</td></tr>";
print "<tr><td colspan=\"4\" align=\"center\"><textarea name=\"besked\" rows=\"5\" cols=\"50\">$besked</textarea></td></tr>";
print "<tr><td>Beskrivelse</td><td>&nbsp;&nbsp;&nbsp;&nbsp;I ordre</td><td><!--Serveret--></td><td>&nbsp;&nbsp;&nbsp;&nbsp;Bestil nu</td></tr>";
$fokus=if_isset($_POST['fokus']);
$pre_fokus=if_isset($_POST['pre_fokus']);
for ($x=0;$x<count($linje_id);$x++) {
	$koekken[$x]=NULL;
	print "<input type=\"hidden\" name=\"linje_id[$x]\" value=\"$linje_id[$x]\">";

	if (!count($cat) || $koekkennr[$x]) {
		if (isset($_POST['bestil'])) {
			$bestil=$_POST['bestil'];
		}	else $bestil[$x]=$antal[$x]-$leveret[$x];
		if (!$fokus) $fokus='bestil_0';
		$tmp="bestil_".$x;
		if ($tmp==$fokus) {	
			$tmp=$_POST[$tmp];
			if ($tmp || $tmp=='0') $bestil[$x]=$tmp;
		}	elseif ($tmp==$pre_fokus) {	
			$tmp=$_POST[$tmp];
			if ($tmp || $tmp=='0') $bestil[$x]=$tmp;
		}
		if (!$leveres[$x] && !$leveret[$x]){
			$leveres[$x]=$antal[$x];
		}
		if ($bestil[$x]<0)$bestil[$x]=0; 
		print "<tr>";
		print "<td>
		<input type=\"hidden\" name=\"koekkennr[$x]\" value=\"$koekkennr[$x]\">
		<input type=\"hidden\" name=\"antal[$x]\" value=\"$antal[$x]\">
		<input type=\"hidden\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\">
		<input type=\"hidden\" name=\"bestil[$x]\" value=\"$bestil[$x]\">
		<input type=\"hidden\" name=\"leveret[$x]\" value=\"$leveret[$x]\">
		<input type=\"hidden\" name=\"tilfravalg[$x]\" value=\"$tilfravalg[$x]\">
		<input type=\"hidden\" name=\"notes[$x]\" value=\"$notes[$x]\">
		<input type=\"hidden\" name=\"koekkenprinter\" value=\"$koekkenprinter\">";
		for ($y=0;$y<count($kategori[$x]);$y++) print "<input type=\"hidden\" name=\"kategori[$x][$y]\" value=\"".$kategori[$x][$y]."\">";
		print "$beskrivelse[$x]
	</td>";
	print "<td align=\"right\">".str_replace(".",",",$antal[$x])."</td><td align=\"right\"><!--".str_replace(".",",",$antal[$x])."--></td>";
	print "<td align=\"right\"><input style=\"width:50px;height:30px;text-align:right\" name=\"bestil_$x\" type=\"text\" value=\"\" placeholder=\"".str_replace(".",",",$bestil[$x])."\" onfocus=\"document.forms[0].fokus.value=this.name;\" onclick=\"javascript:document.koekkenprint.submit()\"></td>"; 
	print "<tr>";
	}
}
print "<tr><td colspan=\"5\"><input type=\"hidden\" name=\"fokus\"><input type=\"hidden\" name=\"pre_fokus\" value=\"$fokus\">";
print "<input style=\"width:100%;height:40px;font-size:120%\" type=\"submit\" name=\"send_bestilling\" value=\"Send til køkken\"></td></tr>";
print "</form>";
print "</tbody></table></td><td style=\"width:100px\"></td>";
tastatur();
	
#}

function tastatur() {
	print "\n<!-- Function tastatur (start)-->\n";
	global $fokus;
	
	print "<TD height=\"100%\" valign=\"top\"  align=\"left\"><TABLE BORDER=\"0\" CELLPADDING=\"4\" CELLSPACING=\"4\"><TBODY>\n";
	print "<TR>\n";
		$stil="STYLE=\"width:80px;height:40px;font-size:120%;\"";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"one\"   VALUE=\"1\" OnClick=\"koekkenprint.$fokus.value += '1';koekkenprint.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"two\"   VALUE=\"2\" OnCLick=\"koekkenprint.$fokus.value += '2';koekkenprint.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"three\" VALUE=\"3\" OnClick=\"koekkenprint.$fokus.value += '3';koekkenprint.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"four\"  VALUE=\"4\" OnClick=\"koekkenprint.$fokus.value += '4';koekkenprint.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"five\"  VALUE=\"5\" OnCLick=\"koekkenprint.$fokus.value += '5';koekkenprint.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"six\"   VALUE=\"6\" OnClick=\"koekkenprint.$fokus.value += '6';koekkenprint.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"seven\" VALUE=\"7\" OnClick=\"koekkenprint.$fokus.value += '7';koekkenprint.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"eight\" VALUE=\"8\" OnCLick=\"koekkenprint.$fokus.value += '8';koekkenprint.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"nine\"  VALUE=\"9\" OnClick=\"koekkenprint.$fokus.value += '9';koekkenprint.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"zero\"  VALUE=\",\" OnClick=\"koekkenprint.$fokus.value += ',';koekkenprint.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"zero\"  VALUE=\"0\" OnClick=\"koekkenprint.$fokus.value += '0';koekkenprint.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"clear\" VALUE=\"Ryd\" OnClick=\"koekkenprint.$fokus.value = '';koekkenprint.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
	print "</TR>\n";
	print "</TBODY></TABLE></TD></TR>\n";
	print "\n<!-- Function tastatur (slut)-->\n";
}
if (!$fokus) $fokus='besked';
?>
<script language="javascript">
document.koekkenprint.<?php echo $fokus?>.focus();
</script>
<?php
#cho $fokus;

print "</tbody></table>";
#####################################################################################################

