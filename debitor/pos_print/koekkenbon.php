<?php
@session_start();
$s_id=session_id();
$css="../../css/standard.css";

$title="Køkkenprint";

include("../../includes/connect.php");
include("../../includes/online.php");
include("../../includes/std_func.php");
include("../../includes/ordrefunc.php");
include("../../includes/ConvertCharset.class.php");
if ($db_encode=="UTF8") $FromCharset = "UTF-8";
else $FromCharset = "iso-8859-15";
$ToCharset = "cp865";
$convert = new ConvertCharset();

$id=$_GET['id'];

$filnavn="http://saldi.dk/kasse/K2_".$_SERVER['REMOTE_ADDR'].".ip";
if ($fp=fopen($filnavn,'r')) {
	$kpr=trim(fgets($fp));
	fclose ($fp);
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
	if (substr($r['tilfravalg'],0,2)!="L:") {
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

$pfnavn="../../temp/".$db."/K".abs($bruger_id).".$y";
$fp=fopen("$pfnavn","w");
$best_nr=substr($id,-2);
$txt=$convert ->Convert("******   BESTILLING $best_nr  ******", $FromCharset, $ToCharset);
while (strlen($txt)<40) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");
fwrite($fp,"\nD. ".date("d.m.Y")." kl. ".(date("H:i"))."\n\n");  
$txt=$convert ->Convert("Bestilt af: $brugernavn", $FromCharset, $ToCharset);
fwrite($fp,"$txt\n\n");
fwrite($fp,"Antal  Beskrivelse\n");
fwrite($fp,"----------------------------------------\n");
for ($x=0;$x<count($linje_id);$x++) {
	if ($koekkennr[$x]) {
		fwrite($fp,"$antal[$x]  $beskrivelse[$x]\n");
		if ($tilfravalg[$x]){
			$tfv=explode(chr(9),$tilfravalg[$x]);
			for ($t=0;$t<count($tfv);$t++){
				$r=db_fetch_array(db_select("select beskrivelse from varer where id = '$tfv[$t]'",__FILE__ . " linje " . __LINE__));
				$txt=$convert ->Convert("$r[beskrivelse]", $FromCharset, $ToCharset);
				fwrite($fp,"     $txt\n");
			}
		}
		fwrite($fp,"$notes[$x]\n\n");
		fwrite($fp,"----------------------------------------\n");
	}
}
fwrite($fp,"\n\n\n");
fclose($fp);
$bon='';
$fp=fopen("$pfnavn","r");
while($linje=fgets($fp))$bon.=$linje;
fclose($fp);
$bon=urlencode($bon);
if (count($koekkennr)) {
	$url="http://$kpr/saldiprint.php?printfil=&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&gem=0";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$url\">\n";
} else print  "<body onload=\"javascript:window.close();\">";
#cho $url; 
exit;
?>

