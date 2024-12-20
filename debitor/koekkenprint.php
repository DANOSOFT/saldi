<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/koekkenprint.php - lap 4.1.0 --- 2025-05-21 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2008-2024 saldi.dk aps
// -----------------------------------------------------------------------
// 20140903 - Rettet diverse pga antal blev stillet tilbage efter 3. klik
// 20140904 - Indsat 3x linjeafstand på udskrift.
// 20141030 - Mange ændringer til understøttelse af flere køkkener.
// 20141202 -	Sat printfil='' i alle kald til
// 20150616 - Understøtter nu til og fravalg Søg 'tilfravalg'
// 20150627	- Der kan nu sendes besked til køkken. Søg besked
// 20170619 - PHR udskriftsdel lagt i funktion så der kan laves kundetilpasninger. 20170619
// 20210915 - PHR Replaced convert with iconv and defined some undefined variables. 
// 20220302 - PHR Added $next as k2 was not printed if $1 was empty
// 20220421 -	PHR	Added varenr and grouping after 1 letter in varenr
// 20240521	- PHR	fiscal_year

@session_start();
$s_id=session_id();
$besked=$bon=$bonantal=$kp[0]=$leveres=NULL;
$css="../css/standard.css";
$title="Køkkenprint";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");
(file_exists("pos_print/koekkenprint_".$db_id.".php"))?include("pos_print/koekkenprint_".$db_id.".php"):include("pos_print/koekkenprint.php"); #20170619

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
	} else $url="$url/bordplaner/bordplan.php?id=$id";
 	print "<meta http-equiv=\"refresh\" content=\"0;URL=$url\">\n";
}	

$x=0;
$q=db_select("select * from grupper where art='V_CAT' order by box1",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array ($q)) {
	if (substr($r['box1'],0,1)=='K' && strlen($r['box1'])=='2') {
		$cat_id[$x]=$r['id'];
		$cat[$x]=$r['box1'];
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


if (isset($_POST['linje_id']) && $linje_id=$_POST['linje_id']) {
	$antal=$_POST['antal'];
	$varenr=$_POST['varenr'];
	$leveres=if_isset($_POST['leveres']);
	$leveret=$_POST['leveret'];
	$beskrivelse=$_POST['beskrivelse'];
	$tilfravalg=$_POST['tilfravalg'];
	$notes=$_POST['notes'];
	$koekkenprinter=$_POST['koekkenprinter'];
	$koekkennr=$_POST['koekkennr'];
	$kategori=$_POST['kategori'];
	$besked=$_POST['besked'];
	
	if (isset($_POST['send_bestilling']) && $_POST['send_bestilling']) {
		$bestil=$_POST['bestil'];
		if ($db_encode=="UTF8") $FromCharset = "UTF-8";
		else $FromCharset = "iso-8859-15";
		$ToCharset = "cp865";
		$skriv=0;

		for ($x=0;$x<count($linje_id);$x++) {
			$tmp="bestil_".$x;
			$tmp=$_POST[$tmp];
			if ($tmp || $tmp=='0') $bestil[$x]=$tmp;
			if ($bestil[$x]) {
				$skriv=1;
				$bestil[$x]=iconv($FromCharset, $ToCharset,trim($bestil[$x]));
				$beskrivelse[$x]=iconv($FromCharset, $ToCharset,trim($beskrivelse[$x]));
				if (strlen($bestil[$x])>5) $bestil[$x]=substr($bestil[$x],-5);
				while(strlen($bestil[$x])<5) $bestil[$x]=' '.$bestil[$x];
				if (strlen($beskrivelse[$x])>35) $b=substr($beskrivelse[$x],0,34);
			}
		}
		if ($skriv) {
			for ($x=0;$x<count($linje_id);$x++) {
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
			if (strpos($koekkenprinter,",")) {
				$kp=array();
				$kp=explode(",",$koekkenprinter);
			} elseif (strpos($koekkenprinter,":")) {
				$kp=array();
				$kp=explode(":",$koekkenprinter);
			} elseif (strpos($koekkenprinter,";")) {
				$kp=array();
				$kp=explode(";",$koekkenprinter);
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
			if ($db == 'develop_27' || $db == 'pos_98') koekkenprint($linje_id,$bestil,$beskrivelse,$cat_id,$kategori,$varenr);
			else {
				echo "Sender til køkken<br>";
				koekkenprint($linje_id,$bestil,$beskrivelse,$cat_id,$kategori);
			}
		} else print "<BODY onLoad=\"javascript:alert('Der er ikke noget på bestillingslisten til køkken for $bordnavn')\">\n";
		(count($kp)>1)?$next=1:$next=NULL;
		$pfnavn="../temp/".$db."/".abs($bruger_id).".0";
		$fp=fopen("$pfnavn","r");
		while($linje=fgets($fp))$bon.=$linje;
		fclose($fp);
		$bon=urlencode($bon);
		if ($next || $bon && $kp[0]) { // 20220302
			if ($next) $url="http://$kp[0]/saldiprint.php?printfil=&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&returside=$url/debitor/koekkenprint.php&next=$next&gem=1";
			else $url="http://$kp[0]/saldiprint.php?printfil=&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&returside=$url/debitor/pos_ordre.php&next=$next&gem=0";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$url\">\n";
			exit;
			print $y+1 ."<br>";
		}
	}
} else {
	if (!$bordnr && $bordnr!='0') {
		$r=db_fetch_array(db_select("select nr,hvem from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		$bordnr=$r['nr'];
		$hvem=$r['hvem'];
	}
	$kasse=stripslashes($_COOKIE['saldi_pos']);
	$r = db_fetch_array(db_select("select box7,box10 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
	($r['box10'])?$koekkenprintere=explode(chr(9),$r['box10']):$koekkenprintere=NULL;
	$koekkenprinter=$koekkenprintere[$kasse-1];
	if (($bordnr || $bordnr=='0') && !$bordnavn) {
		($r['box7'])?$bord=explode(chr(9),$r['box7']):$bord=NULL;
		$bordnavn=$bord[$bordnr];
	}
	$x=0;
	if ($db == 'develop_27' || $db == 'pos_98') {
		for ($i=0;$i<4;$i++) {
			$qtxt = "select ordrelinjer.id,ordrelinjer.antal,ordrelinjer.leveres,ordrelinjer.leveret,ordrelinjer.beskrivelse,";
			$qtxt.= "ordrelinjer.tilfravalg,varer.notes,varer.kategori,ordrelinjer.varenr from ordrelinjer,varer ";
			$qtxt.= "where ordrelinjer.ordre_id='$id' and ordrelinjer.vare_id=varer.id ";
			if ($i==0) $qtxt.= "and upper(ordrelinjer.varenr) like 'AF%' ";
			if ($i==1) $qtxt.= "and upper(ordrelinjer.varenr) like 'AH%' ";
			if ($i==2) $qtxt.= "and upper(ordrelinjer.varenr) like 'AD%' ";
			if ($i==3) {
				$qtxt.= "and upper(ordrelinjer.varenr) not like 'AF%' ";
				$qtxt.= "and upper(ordrelinjer.varenr) not like 'AH%' ";
				$qtxt.= "and upper(ordrelinjer.varenr) not like 'AD%' ";
			}
			$qtxt.= "order by ordrelinjer.posnr desc";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array ($q)) {
				$linje_id[$x]=$r['id'];
				($r['antal'])?$antal[$x]=$r['antal']*1:$antal[$x]=0;
				$varenr[$x]=$r['varenr'];
#cho "$i,$varenr[$x]<br>";
				($r['leveres'])?$leveres[$x]=$r['leveres']*1:$leveres[$x]=0;
				($r['leveret'])?$leveret[$x]=$r['leveret']*1:$leveret[$x]=0;
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
	} elseif($id) {
		$qtxt = "select ordrelinjer.id,ordrelinjer.antal,ordrelinjer.leveres,ordrelinjer.leveret,ordrelinjer.beskrivelse,";
		$qtxt.= "ordrelinjer.tilfravalg,varer.notes,varer.kategori,ordrelinjer.varenr from ordrelinjer,varer ";
		$qtxt.= "where ordrelinjer.ordre_id='$id' and ordrelinjer.vare_id=varer.id  order by ordrelinjer.posnr desc";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array ($q)) {
			$linje_id[$x]=$r['id'];
			$antal[$x]=$r['antal']*1;
			$varenr[$x]=$r['varenr'];
			$leveres[$x]=$r['leveres']*1;
			$leveret[$x]=$r['leveret']*1;
#cho "L $leveret[$x]<br>";			
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
	print "<input type=\"hidden\" name=\"tilfravalg[$x]\" value=\"$tilfravalg[$x]\">";

	if (!count($cat) || $koekkennr[$x]) {
		if (!isset($leveres[$x])) $leveres[$x]=0; 
		if (isset($_POST['bestil'])) {
			$bestil=$_POST['bestil'];
		}	else $bestil[$x]=$antal[$x]-$leveret[$x];
		#if (!$fokus) $fokus='bestil_0';
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
		<input type=\"hidden\" name=\"varenr[$x]\" value=\"$varenr[$x]\">
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
kptastatur();
	
#}

function kptastatur() {
	print "\n<!-- Function kptastatur (start)-->\n";
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
	print "\n<!-- Function kptastatur (slut)-->\n";
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

