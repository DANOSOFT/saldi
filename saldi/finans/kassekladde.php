<?php
ob_start(); //Starter output buffering
// ------------finans/kassekladde.php------lap 3.5.0---2015.01.20------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2015 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2012.08.09 søg 20120809 V. openpostopslag viser både kreditorer og debitorer hvis de har samme kontonr - rettet 
// 2012.08.06 søg 20120806
// 2012.11.10 søg 20121110
// 2013.02.21 søg 20130221
// 2013.04.04 addslashes erstattet af db_escape_string
// 2013.07.31 db_escape_string fjernet 2 steder da den bruges senere på samme streng og fordoblede "'" ved hver gem.
// 2013.11.01 Fravalg for tjek af forskellige datoer.Søg: $forskellige_datoer
// 2013.12.16	Flyttet javascript og ned til de øvrige scripts og fjernet fejltastet "f"
// 2014.06.24 Flyttet opslag efter im betalings_id skal vises over $_POST så den også er med når kladden åbnes. Søg 20140624
// 2014.06.24 Indsat $gl_transdate[$x] Søg 20140624
// 2014.06.01 Tilføjet intern_bilag - Søg intern_bilag.
// 2014.07.09 Et cfirede kontonumre blev set som genvejstast og gav fejl.Søg "!is_numeric"   
// 2014.07.18 Indsat db_escape_string i beskrivelse for 'tilbagefør' Søg 20140718 
// 2014.11.06	Clips vises ikke før der er et kladde id 20141106
// 2015.01.05 db_escape_string på kladdenote 20150105
// 2015.01.05 Fejltjek på bilagsnummer v. kopier til ny 20150105-2
// 2015.01.20 Saldo vises ved kontopslag i funktion finansopslag. 
// 2015.05.21 Indsat begrænsning på antal bilag der kan tilføjes #20150521
// 2015.12.10 Indført brug af piletaster ctrl-pil - søg formnavi
// 2015.12.10	Kontrol for dubletposteringer. Søg dublet & $dub

@session_start();
$s_id=session_id();
$title="kassekladde";
$modulnr=2;
$css="../css/standard.css";

$browser=strtolower($_SERVER['HTTP_USER_AGENT']);
if (strstr($browser,'opera')) $browser="opera";
elseif (strstr($browser,'firefox')) $browser="firefox";
else $browser="ukendt";

$afd=array(NULL);$amount=array(NULL);$ansat=array(NULL);$belob=array(NULL);$beskrivelse=array();;$betal_id=array(NULL);$bilag=array(NULL);
$dato=array(NULL);$d_type=array(NULL);$debet=array(NULL);$faktura=array(NULL);$forfaldsdate=array(NULL);$forfaldsdato=array(NULL);
$id=array(NULL);$k_type=array(NULL);$kontonr=array(NULL);$kredit=array(NULL);$lobenr=array(NULL);$momsfri=array(NULL);
$projekt=array(NULL);$valuta=array(NULL);

$antal_ex=NULL;
$belob_ligslut=NULL;$belob_ligstart=NULL;$beskrivelse_ligslut=NULL;$beskrivelse_ligstart=NULL;$bogfort=NULL;
$debet_ligslut=NULL;$debet_ligstart=NULL;$d_type_ligslut=NULL;$d_type_ligstart=NULL;
$find=NULL;
$k_type_ligslut=NULL;$k_type_ligstart=NULL;$kontrolkonto=NULL;$kontrolsaldo=NULL;$kredit_ligslut=NULL;$kredit_ligstart=NULL;$kladde_id=NULL;$kladdenote=NULL;$regnstart=NULL;
$simuler=NULL;$sletstart=NULL;$sletslut=NULL;$opslag_id=NULL;$lukket=NULL;$linjebg=NULL;$submit=NULL;
$vis_afd=NULL;	$vis_ansat=NULL;$vis_bet_liste=NULL;$vis_forfald=NULL;$vis_projekt=NULL;$vis_valuta=NULL;
$fejl=0;$x=0;$y=0;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");

print "<script LANGUAGE=\"javascript\" TYPE=\"text/javascript\" SRC=\"../javascript/confirmclose.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\" SRC=\"../javascript/overlib.js\"></script>";
print "<script>
	function fokuser(that, fgcolor, bgcolor){
		that.style.color = fgcolor;
		that.style.backgroundColor = bgcolor;
		document.forms[0].fokus.value=that.name; 
	}
		function defokuser(that, fgcolor, bgcolor){
		that.style.color = fgcolor;
		that.style.backgroundColor = bgcolor;
	}
</script>";

$udskriv=if_isset($_GET['udskriv']);
if ($tjek=if_isset($_GET['tjek'])) {
	$tidspkt=microtime() ;
	list ($a,$b)=explode(" ",$tidspkt);
	$query = db_select("select bogfort,tidspkt,hvem from kladdeliste where (bogfort = '-' or bogfort = 'S') and id = $tjek",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	if (isset($row['tidspkt'])) {
		list ($a,$c)=explode(" ",$row['tidspkt']);
		if (($b-$c<3600)&&($row['hvem']!=$brugernavn)){
			print "<body onLoad=\"javascript:alert('Kladden er i brug af $row[hvem]')\">";
			if ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			else print "<meta http-equiv=\"refresh\" content=\"0;URL=../finans/kladdeliste.php\">";
		} else {
			$a--;
			$tidspkt=$a." ".$b; #der fratraekkes 1. sec af hensyn til refreshtjek;
			db_modify("update kladdeliste set hvem = '$brugernavn',tidspkt='$tidspkt' where id = '$tjek'",__FILE__ . " linje " . __LINE__);
		}
	}
	if (db_fetch_array(db_select("select id from tmpkassekl where kladde_id='$tjek'",__FILE__ . " linje " . __LINE__))) $fejl=1;
	else $fejl=0;

	if ($r=db_fetch_array(db_select("select * from grupper where ART = 'KASKL' and kode='1' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__))) {
		$kksort=$r['box1'];
		$kontrolkonto=$r['box2'];
	} else {
		db_modify ("insert into grupper (beskrivelse,art,kode,kodenr) values ('Kassekladde','KASKL','1','$bruger_id')",__FILE__ . " linje " . __LINE__);
	}
}

$ompost=isset($_GET['ompost'])? $_GET['ompost']:Null;
if ($ompost) ompost($ompost);

$kladde_id=isset($_POST['kladde_id'])? $_POST['kladde_id']:0;
$antal_ny=isset($_POST['antal_ny'])? $_POST['antal_ny']:0;
$h=$antal_ny*10+100;

?>
<script type="text/javascript">
<!--
function simuler(){
window.open("../finans/bogfor.php?kladde_id=<?php echo $kladde_id?>&funktion=simuler","","width=800,height=600,scrollbars=1,resizable=1")
}
//-->
</script>
<script type="text/javascript">
<!--
function bogfor() {
	window.open("../finans/bogfor.php?kladde_id=<?php echo $kladde_id?>","","width=800,height=600,scrollbars=1,resizable=1")
}
//-->
</script>
<div align="center">
<?php
$r=db_fetch_array(db_select("select box4,box10 from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__)); #20140624
($r['box4'])?$forskellige_datoer=1:$forskellige_datoer=0;
($r['box10'])?$vis_bet_id=1:$vis_bet_id=0;

if($_GET) {
	$returside=if_isset($_GET['returside']);
	if (!$returside){$returside="../finans/kladdeliste.php";}
	$fokus=if_isset($_GET['fokus']);
	$sort=if_isset($_GET['sort']);
	$kksort=if_isset($_GET['kksort']); #sortering i kassekladde
	$funktion=if_isset($_GET['funktion']);
	$x=if_isset($_GET['x'])*1;
	$id[$x]=if_isset($_GET['id']);
	$lobenr[$x]=if_isset($_GET['lobenr']);
	$kladde_id=if_isset($_GET['kladde_id'])*1;
	$bilag[$x]=if_isset($_GET['bilag']);
	$dato[$x]=if_isset($_GET['dato']);
	$beskrivelse[$x]=urldecode(if_isset($_GET['beskrivelse']));
#	$beskrivelse[$x]=str_replace("!apostrof!", "'",$beskrivelse[$x]);
	$d_type[$x]=if_isset($_GET['d_type']);
	$debet[$x]=if_isset($_GET['debet']);
	$k_type[$x]=if_isset($_GET['k_type']);
	$kredit[$x]=if_isset($_GET['kredit']);
	$faktura[$x]=urldecode(if_isset($_GET['faktura']));
	$belob[$x]=if_isset($_GET['belob']);
	$momsfri[$x]=if_isset($_GET['momsfri']);
	$afd[$x]=if_isset($_GET['afd']);
	$projekt[$x]=if_isset($_GET['projekt']);
	$ansat[$x]=if_isset($_GET['ansat']);
	$valuta[$x]=if_isset($_GET['valuta']);

	$beskrivelse[$x]=trim($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x])*1;
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x])*1;
	$faktura[$x]=trim($faktura[$x]);
	$belob[$x]=trim($belob[$x]);

	if ($kksort) db_modify("update grupper set box1='$kksort' where ART='KASKL' and kode='1' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
	if (($sort)&&($funktion)) {
		$funktion($sort,$fokus,$x,$id[$x],$kladde_id,$bilag[$x],$dato[$x],$beskrivelse[$x],$d_type[$x],$debet[$x],$k_type[$x],$kredit[$x],$faktura[$x],$belob[$x],$momsfri[$x],$afd[$x],$projekt[$x],$ansat[$x],$valuta[$x],$forfaldsdato[$x],$betal_id[$x],$lobenr[$x]);
	}
	$y=0;
	$query = db_select("select kontonr from kontoplan where kontotype != 'H' and kontotype != 'Z' and regnskabsaar=$regnaar",__FILE__ . " linje " . __LINE__);
	while($row = db_fetch_array($query)) {
		$y++;
		$kontonr[$y]=trim($row['kontonr']);
	}
	if ($kladde_id) {
		$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id",__FILE__ . " linje " . __LINE__));
		if (($row['bogfort']=='-')&&(($id[$x])||($lobenr[$x]||($x)))) {
			if ($id[$x]) db_modify("update tmpkassekl set d_type='$d_type[$x]', debet='$debet[$x]', k_type='$k_type[$x]', kredit='$kredit[$x]', faktura='$faktura[$x]', amount='$belob[$x]', momsfri='$momsfri[$x]', afd='$afd[$x]', projekt='$projekt[$x]', ansat='$ansat[$x]', valuta='$valuta[$x]',forfaldsdate='$forfaldsdato[$x]',betal_id='$betal_id[$x]' where id='$id[$x]' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			elseif ($lobenr[$x]) {
				db_modify("update tmpkassekl set bilag='$bilag[$x]', d_type='$d_type[$x]', debet='$debet[$x]', k_type='$k_type[$x]', kredit='$kredit[$x]', faktura='$faktura[$x]', amount='$belob[$x]', momsfri='$momsfri[$x]', afd='$afd[$x]', projekt='$projekt[$x]', ansat='$ansat[$x]', valuta='$valuta[$x]',forfaldsdate='$forfaldsdato[$x]',betal_id='$betal_id[$x]' where lobenr='$lobenr[$x]' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			else {
				db_modify("update tmpkassekl set bilag='$bilag[$x]',d_type='$d_type[$x]', debet='$debet[$x]', k_type='$k_type[$x]', kredit='$kredit[$x]', faktura='$faktura[$x]', amount='$belob[$x]', momsfri='$momsfri[$x]', afd='$afd[$x]', projekt='$projekt[$x]', ansat='$ansat[$x]', valuta='$valuta[$x]',forfaldsdate='$forfaldsdato[$x]',betal_id='$betal_id[$x]' where lobenr='$x' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			kontroller($id[$x],$bilag[$x],$dato[$x],$beskrivelse[$x],$d_type[$x],$debet[$x],$k_type[$x],$kredit[$x],$faktura[$x],$belob[$x],$momsfri[$x],$kontonr,$kladde_id,$afd[$x],$projekt[$x],$ansat[$x],$valuta[$x],$forfaldsdato[$x],$betal_id[$x],$x);
		}
		if ($fejl) $submit="Gem";
	}
	if ($r=db_fetch_array(db_select("select * from grupper where ART = 'KASKL' and kode='1' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__))) {
		$kksort=$r['box1'];
		$kontrolkonto=$r['box2'];
	}
}
if ($_POST) {
	$submit =trim(if_isset($_POST['submit']));
	if (strstr($submit, "Kopi") && strstr($submit, "til ny")) $submit="Kopier til ny";
	$tidspkt =if_isset($_POST['tidspkt']);
	$kladde_id =if_isset($_POST['kladde_id']);
	$ny_dato =if_isset($_POST['ny_dato']);
	$kontrolkonto=trim(if_isset($_POST['kontrolkonto']));
	$bilagsnr=if_isset($_POST['bilagsnr']);
	$kladdenote = db_escape_string(trim(if_isset($_POST['kladdenote'])));
	$ny_kladdenote = db_escape_string(trim(if_isset($_POST['ny_kladdenote'])));
	$antal_ny=if_isset($_POST['antal_ny']);
	$antal_ex=if_isset($_POST['antal_ex']);
	$fokus=if_isset($_POST['fokus']);
#	$momsfri=if_isset($_POST['momsfri']);
	$id=if_isset($_POST['id']);
	$gl_transdate=if_isset($_POST['transdate']);

	if ($kladde_id) {
		if ($r=db_fetch_array(db_select("select id from kladdeliste where bogfort='S' and id='$kladde_id'",__FILE__ . " linje " . __LINE__))) {
			 $alerttekst="Annullerer simulering for denne kladde";
			 print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
		}
		db_modify("delete from tmpkassekl where kladde_id=$kladde_id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from simulering where kladde_id=$kladde_id",__FILE__ . " linje " . __LINE__);
		db_modify("update kladdeliste set bogfort = '-', bogforingsdate = NULL, bogfort_af = '' where id = '$kladde_id' and bogfort = 'S'",__FILE__ . " linje " . __LINE__);
	}
	db_modify("update grupper set box2='$kontrolkonto' where ART='KASKL' and kode='1' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
	($r=db_fetch_array(db_select("select * from grupper where ART = 'bilag' and (box6 ='on' or (box1 !='' and box2 !='' and box3 !=''))",__FILE__ . " linje " . __LINE__)))?$vis_bilag=1:$vis_bilag=0;
	($r['box6']=='on')?$intern_bilag=1:$intern_bilag=0;
	(db_fetch_array(db_select("select * from grupper where ART = 'AFD'",__FILE__ . " linje " . __LINE__)))?$vis_afd=1:$vis_afd=0;
	(db_fetch_array(db_select("select * from grupper where ART = 'PRJ'",__FILE__ . " linje " . __LINE__)))?$vis_projekt=1:$vis_projekt=0;
	(db_fetch_array(db_select("select * from grupper where ART = 'VK'",__FILE__ . " linje " . __LINE__)))?$vis_valuta=1:$vis_valuta=0;
	for ($x=1;$x<=$antal_ny;$x++) {
		$dato[$x]=NULL;$beskrivelse[$x]=NULL;$d_type[$x]=NULL;$debet[$x]=NULL;$k_type[$x]=NULL;$kredit[$x]=NULL;$faktura[$x]=NULL;
		$belob[$x]=NULL;$momsfri[$x]=NULL;$afd[$x]=NULL;$projekt[$x]=NULL;$ansat[$x]=NULL;$valuta[$x]=NULL;$forfaldsdato[$x]=NULL;$betal_id[$x]=NULL;

		$y="bila".$x;
		$bilag[$x]=trim(if_isset($_POST[$y]));
#echo "1 bilag $bilag[$x]<br>";		
#		if ($id[$x] && $bilag[$x]=='') {
#			$bilag[$x]="-";  
#echo "2 bilag $bilag[$x]<br>";		
#			} else
			if (!$bilag[$x]) $bilag[$x]='0'; # PHR 02.09.06
#echo "3 bilag $bilag[$x]<br>";		
		$y="dato".$x;
		$dato[$x]=trim(if_isset($_POST[$y]));
		$y="besk".$x;
		$beskrivelse[$x]=trim(if_isset($_POST[$y])); #20130731
		while (strpos($beskrivelse[$x],"  ")) $beskrivelse[$x]=str_replace("  "," ",$beskrivelse[$x]);
#		while (strpos($beskrivelse[$x],"''")) $beskrivelse[$x]=str_replace("''","'",$beskrivelse[$x]); #20130731
		$y="d_ty".$x;
		$d_type[$x]=substr(strtoupper(if_isset($_POST[$y])),0,1);
		$y="debe".$x;
		$debet[$x]=trim(if_isset($_POST[$y]));
		$y="k_ty".$x;
		$k_type[$x]=substr(strtoupper(if_isset($_POST[$y])),0,1);
		$y="kred".$x;
		$kredit[$x]=trim(if_isset($_POST[$y]));
		$y="fakt".$x;
		$faktura[$x]=trim(if_isset($_POST[$y])); #20130731
		$y="belo".$x;
		$belob[$x]=if_isset($_POST[$y]);
		$y="dkka".$x;
		$dkkamount[$x]=if_isset($_POST[$y]);
		$y="afd_".$x;
		$afd[$x]=trim(if_isset($_POST[$y]));
		$y="proj".$x;
		$projekt[$x]=trim(if_isset($_POST[$y]));
		$y="meda".$x;
		$ansat[$x]=trim(if_isset($_POST[$y]));
		$y="valu".$x;
		$valuta[$x]=strtoupper(if_isset($_POST[$y]));
		$y="forf".$x;
		$forfaldsdato[$x]=trim(if_isset($_POST[$y]));
		$y="b_id".$x;
		$betal_id[$x]=trim(if_isset($_POST[$y]));
		$y="moms".$x;
		$momsfri[$x]=if_isset($_POST[$y]);
		if ($bilag[$x]==$bilag[$x-1] && $dato[$x]==$dato[$x-1] && $beskrivelse[$x]==$beskrivelse[$x-1] && !$debet[$x] && !$kredit[$x]) $bilag[$x]='-';
		elseif (!$bilag[$x] && !$dato[$x] && !$beskrivelse[$x] && !$debet[$x] && !$kredit[$x]) $bilag[$x]='-';
		if ($bilag[$x]=="=") $bilag[$x] = $bilag[$x-1];
		if ($bilag[$x]=="+") $bilag[$x] = $bilag[$x-1]+1;
		if (substr($bilag[$x],0,1)=="+") $bilag[$x] = $bilag[$x-1]+1;
		if (!$dato[$x] || $dato[$x]=='=') $dato[$x] = $dato[$x-1];
		if ($beskrivelse[$x]=="=") $beskrivelse[$x] = $beskrivelse[$x-1];
		if ($d_type[$x]=="=") $d_type[$x] = $d_type[$x-1];
		if ($debet[$x]=="=") $debet[$x] = $debet[$x-1];
		if ($k_type[$x]=="=") $k_type[$x] = $k_type[$x-1];
		if ($kredit[$x]=="=") $kredit[$x] = $kredit[$x-1];
		if ($faktura[$x]=="=") $faktura[$x] = $faktura[$x-1];
		if ($belob[$x]=="=") $belob[$x] = $belob[$x-1];
		if ($afd[$x]=="=") $afd[$x] = $afd[$x-1];
		if ($ansat[$x]=="=") $ansat[$x] = $ansat[$x-1];
		if ($projekt[$x]=="=") $projekt[$x] = $projekt[$x-1];
		if ($forfaldsdato[$x]=="=") $forfaldsdato[$x] = $forfaldsdato[$x-1];
		if ($betal_id[$x]=="=") $betal_id[$x] = $betal_id[$x-1];
		if ((!$dato[$x])&&(($beskrivelse[$x])||($debet[$x])||($kredit[$x]))) $dato[$x]=date("d-m-Y");
		if ($bilag[$x] != $bilag[$x-1]) $kontrolsum=0;
		if ($debet[$x]) $kontrolsum=$kontrolsum+$dkkamount[$x];
		if ($kredit[$x]) $kontrolsum=$kontrolsum-$dkkamount[$x];
		$bilagssum[$x]=$kontrolsum;
		# fjerner autogenererede linjer hvis bilaget er i balance
		if (!$kontrolsum && !$debet[$x] && !$kredit[$x] && $bilag[$x]==$bilag[$x-1] && $dato[$x]==$dato[$x-1] && $beskrivelse[$x]==$beskrivelse[$x-1]) $bilag[$x]="-";
		if ((!$sletslut) && ($bilag[$x]=="->")) {
			$sletstart=$x;
			$bilag[$x]="-";
		}
		if ($bilag[$x]=="<-") {
			$bilag[$x]="-";
			if ((!$sletslut) && ($sletstart)) $sletslut=$x;
		}
		if (($sletstart)&&($sletslut)&&($sletstart<$sletslut)) {
			for ($y=$sletstart;$y<=$sletslut;$y++) {
				$bilag[$y]="-";
				db_modify("update tmpkassekl set bilag= '$bilag[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$sletstart='';$sletslut='';
		}
## Bilag		
		if ((!$bilag_ligslut) && ($bilag[$x]=="=>")) {
			$bilag_ligstart=$x;
			$bilag[$x]=$bilag[$x-1];
		}
		if ($bilag[$x]=="<=") {
			$bilag[$x]==$bilag[$x-1];
			if ((!$bilag_ligslut) && ($bilag_ligstart)) $bilag_ligslut=$x;
		}
		if (($bilag_ligstart)&&($bilag_ligslut)&&($bilag_ligstart<$bilag_ligslut)) {
			for ($y=$bilag_ligstart;$y<=$bilag_ligslut;$y++) {
				$bilag[$y]=$bilag[$y-1];
				db_modify("update tmpkassekl set bilag= '$bilag[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$bilag_ligstart='';$bilag_ligslut='';
		}
# Dato
		if ((!$dato_ligslut) && ($dato[$x]=="=>")) {
			$dato_ligstart=$x;
			$dato[$x]=$dato[$x-1];
		}
		if ($dato[$x]=="<=") {
			$dato[$x]==$dato[$x-1];
			if ((!$dato_ligslut) && ($dato_ligstart)) $dato_ligslut=$x;
		}
		if (($dato_ligstart)&&($dato_ligslut)&&($dato_ligstart<$dato_ligslut)) {
			for ($y=$dato_ligstart;$y<=$dato_ligslut;$y++) {
				$dato[$y]=$dato[$y-1];
				db_modify("update tmpkassekl set dato= '$dato[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$dato_ligstart='';$dato_ligslut='';
		}
# beskrivelse
		if ((!$beskrivelse_ligslut) && ($beskrivelse[$x]=="=>")) {
			$beskrivelse_ligstart=$x;
			$beskrivelse[$x]=$beskrivelse[$x-1];
		}
		if ($beskrivelse[$x]=="<=") {
			$beskrivelse[$x]==$beskrivelse[$x-1];
			if ((!$beskrivelse_ligslut) && ($beskrivelse_ligstart)) $beskrivelse_ligslut=$x;
		}
		if (($beskrivelse_ligstart)&&($beskrivelse_ligslut)&&($beskrivelse_ligstart<$beskrivelse_ligslut)) {
			for ($y=$beskrivelse_ligstart;$y<=$beskrivelse_ligslut;$y++) {
				$beskrivelse[$y]=$beskrivelse[$y-1];
				db_modify("update tmpkassekl set beskrivelse= '$beskrivelse[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$beskrivelse_ligstart='';$beskrivelse_ligslut='';
		}
# d_type
		if ((!$d_type_ligslut) && ($d_type[$x]=="=>")) {
			$d_type_ligstart=$x;
			$d_type[$x]=$d_type[$x-1];
		}
		if ($d_type[$x]=="<=") {
			$d_type[$x]==$d_type[$x-1];
			if ((!$d_type_ligslut) && ($d_type_ligstart)) $d_type_ligslut=$x;
		}
		if (($d_type_ligstart)&&($d_type_ligslut)&&($d_type_ligstart<$d_type_ligslut)) {
			for ($y=$d_type_ligstart;$y<=$d_type_ligslut;$y++) {
				$d_type[$y]=$d_type[$y-1];
				db_modify("update tmpkassekl set d_type= '$d_type[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$d_type_ligstart='';$d_type_ligslut='';
		}
		if ($d_type[$x] && $d_type[$x] != 'D' && $d_type[$x] != 'K') $d_type[$x]='F'; #20110605
# debet
		if ((!$debet_ligslut) && ($debet[$x]=="=>")) {
			$debet_ligstart=$x;
			$debet[$x]=$debet[$x-1];
		}
		if ($debet[$x]=="<=") {
			$debet[$x]==$debet[$x-1];
			if ((!$debet_ligslut) && ($debet_ligstart)) $debet_ligslut=$x;
		}
		if (($debet_ligstart)&&($debet_ligslut)&&($debet_ligstart<$debet_ligslut)) {
			for ($y=$debet_ligstart;$y<=$debet_ligslut;$y++) {
				$debet[$y]=$debet[$y-1];
				db_modify("update tmpkassekl set debet= '$debet[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$debet_ligstart='';$debet_ligslut='';
		}
# k_type
		if ((!$k_type_ligslut) && ($k_type[$x]=="=>")) {
			$k_type_ligstart=$x;
			$k_type[$x]=$k_type[$x-1];
		}
		if ($k_type[$x]=="<=") {
			$k_type[$x]==$k_type[$x-1];
			if ((!$k_type_ligslut) && ($k_type_ligstart)) $k_type_ligslut=$x;
		}
		if (($k_type_ligstart)&&($k_type_ligslut)&&($k_type_ligstart<$k_type_ligslut)) {
			for ($y=$k_type_ligstart;$y<=$k_type_ligslut;$y++) {
				$k_type[$y]=$k_type[$y-1];
				db_modify("update tmpkassekl set k_type= '$k_type[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$k_type_ligstart='';$k_type_ligslut='';
		}
		if ($k_type[$x] && $k_type[$x] != 'D' && $k_type[$x] != 'K') $k_type[$x]='F';#20110605
# kredit
		if ((!$kredit_ligslut) && ($kredit[$x]=="=>")) {
			$kredit_ligstart=$x;
			$kredit[$x]=$kredit[$x-1];
		}
		if ($kredit[$x]=="<=") {
			$kredit[$x]==$kredit[$x-1];
			if ((!$kredit_ligslut) && ($kredit_ligstart)) $kredit_ligslut=$x;
		}
		if (($kredit_ligstart)&&($kredit_ligslut)&&($kredit_ligstart<$kredit_ligslut)) {
			for ($y=$kredit_ligstart;$y<=$kredit_ligslut;$y++) {
				$kredit[$y]=$kredit[$y-1];
				db_modify("update tmpkassekl set kredit= '$kredit[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$kredit_ligstart='';$kredit_ligslut='';
		}
# kredit
		if ((!$kredit_ligslut) && ($kredit[$x]=="=>")) {
			$kredit_ligstart=$x;
			$kredit[$x]=$kredit[$x-1];
		}
		if ($kredit[$x]=="<=") {
			$kredit[$x]==$kredit[$x-1];
			if ((!$kredit_ligslut) && ($kredit_ligstart)) $kredit_ligslut=$x;
		}
		if (($kredit_ligstart)&&($kredit_ligslut)&&($kredit_ligstart<$kredit_ligslut)) {
			for ($y=$kredit_ligstart;$y<=$kredit_ligslut;$y++) {
				$kredit[$y]=$kredit[$y-1];
				db_modify("update tmpkassekl set kredit= '$kredit[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$kredit_ligstart='';$kredit_ligslut='';
		}
# faktura
		if ((!$faktura_ligslut) && ($faktura[$x]=="=>")) {
			$faktura_ligstart=$x;
			$faktura[$x]=$faktura[$x-1];
		}
		if ($faktura[$x]=="<=") {
			$faktura[$x]==$faktura[$x-1];
			if ((!$faktura_ligslut) && ($faktura_ligstart)) $faktura_ligslut=$x;
		}
		if (($faktura_ligstart)&&($faktura_ligslut)&&($faktura_ligstart<$faktura_ligslut)) {
			for ($y=$faktura_ligstart;$y<=$faktura_ligslut;$y++) {
				$faktura[$y]=$faktura[$y-1];
				db_modify("update tmpkassekl set faktura= '$faktura[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$faktura_ligstart='';$faktura_ligslut='';
		}
# belob
		if ((!$belob_ligslut) && ($belob[$x]=="=>")) {
			$belob_ligstart=$x;
			$belob[$x]=$belob[$x-1];
		}
		if ($belob[$x]=="<=") {
			$belob[$x]==$belob[$x-1];
			if ((!$belob_ligslut) && ($belob_ligstart)) $belob_ligslut=$x;
		}
		if (($belob_ligstart)&&($belob_ligslut)&&($belob_ligstart<$belob_ligslut)) {
			for ($y=$belob_ligstart;$y<=$belob_ligslut;$y++) {
				$belob[$y]=$belob[$y-1];
				db_modify("update tmpkassekl set belob= '$belob[$y]' where lobenr='$y' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
			}
			$belob_ligstart='';$belob_ligslut='';
		}
/*
		if (strtoupper($debet[$x]) == 'D') $d_type[$x]='D';
		if (strtoupper($debet[$x]) == 'K') $d_type[$x]='K';
		if (strtoupper($kredit[$x]) == 'D') $k_type[$x]='D';
		if (strtoupper($kredit[$x]) == 'K') $k_type[$x]='K';
*/
# Hvis der skrives d eller k i debet eller kredit felt, sl�s op kreditor eller debitor liste.
	if ($submit == "Gem" && ($fokus=="debe$x" && (strtoupper($debet[$x])=='D' || strtoupper($debet[$x])=='K'))) {
			if (!db_fetch_array(db_select("select * from kontoplan where genvej='".strtoupper($debet[$x])."' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
				$d_type[$x]=strtoupper($debet[$x]);
				$submit = "Opslag";
				$debet[$x]='';
			}
		} elseif ($submit == "Gem" && ($fokus=="kred$x" && (strtoupper($kredit[$x])=='D' || strtoupper($kredit[$x])=='K'))) {
			if (!db_fetch_array(db_select("select * from kontoplan where genvej='".strtoupper($kredit[$x])."' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
				$k_type[$x]=strtoupper($kredit[$x]);
				$submit = "Opslag";
				$kredit[$x]='';
			}
		}
		if (($debet[$x])&&(($d_type[$x]!="F")||(strlen($debet[$x])>1))) {
			 if ($debet[$x]!=$debet[$x]*1) {
				 $alerttekst="Ulovlig v&aelig;rdi i debetfelt (Bilag nr $bilag[$x]) \n kladde ikke gemt!";
				 print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
				#$debet[$x]=$debet[$x]*1;
			}
		}
		if (($kredit[$x])&&(($k_type[$x]!="F")||(strlen($kredit[$x])>1))) {
			 if ($kredit[$x]!=$kredit[$x]*1) {
				 print "<BODY onLoad=\"javascript:alert('Ulovlig v&aelig;rdi i kreditfelt (Bilag nr $bilag[$x])')\">";
				$fejl=1;
				#$kredit[$x]=$kredit[$x]*1;
			}
		}
		if (($kredit[$x])&&(($k_type[$x]!="F")||(strlen($kredit[$x])>1))) {
			 if ($kredit[$x]!=$kredit[$x]*1) {
				 print "<BODY onLoad=\"javascript:alert('Ulovlig v&aelig;rdi i kreditfelt (Bilag nr $bilag[$x])')\">";
				$fejl=1;
				#$kredit[$x]=$kredit[$x]*1;
			}
		}
		if (!$forskellige_datoer && $bilag[$x] && $bilag[$x]!='-' && $bilag[$x]==$bilag[$x-1] && $dato[$x]!=$dato[$x-1] && $bilagssum[$x-1]) {
				print "<BODY onLoad=\"javascript:alert('Forskellige datoer i bilag $bilag[$x]')\">";
		}
		if ((strpos($bilag[$x],'+'))&&($kladde_id)) {
			indsaet_linjer($kladde_id,$bilag[$x],$dato[$x],$beskrivelse[$x],$d_type[$x],$debet[$x]*1,$k_type[$x],$kredit[$x]*1,$faktura[$x],$belob[$x],$afd[$x]*1,$ansat[$x],$projekt[$x],$valuta[$x],$forfaldsdato[$x],$betal_id[$x]*1,$momsfri[$x]);
		}
		if (($bilag[$x])&&($bilag[$x]!="-")&&($bilag[$x]!="->")&&($bilag[$x]!="<-")&&(!strpos($bilag[$x],'+'))&&(!is_numeric($bilag[$x]))) {
			 print "<BODY onLoad=\"javascript:alert('Ulovlig v&aelig;rdi i bilagsfelt (Bilag nr $bilag[$x])')\">";
			$fejl=1;
		} elseif (($bilag[$x])&&($bilag[$x]!="-")&&($bilag[$x]!="->")&&($bilag[$x]!="<-")) $bilag[$x]=$bilag[$x]*1;
		if ($bilag[$x] == "-") {$dato[$x]='';$beskrivelse[$x]='';$d_type[$x]='';$debet[$x]='';$k_type[$x]='';$kredit[$x]='';$faktura[$x]='';$belob[$x]='';$momsfri[$x]='';$afd[$x]='';$projekt[$x]='';$ansat[$x]='';$valuta[$x]='';$forfaldsdato[$x]='';$betal_id[$x]='';}
		if (!isset($id[$x])) $id[$x]='0';
		if (!$id[$x]) $id[$x]='0';
		if (!$kladde_id) {
			$tidspkt=microtime();
			$row = db_fetch_array(db_select("select MAX(id) AS id from kladdeliste",__FILE__ . " linje " . __LINE__));
			$kladde_id=$row['id']+1;
			$kladdedate=date("Y-m-d");	# OBS I naeste linje indsaettes tidspkt fratrukket 1 sek. Ellers bliver 1. gemning afvist af	"Refresktjek"
			db_modify("insert into kladdeliste (id, kladdenote, kladdedate, bogfort, hvem, oprettet_af, tidspkt) values ('$kladde_id', '$ny_kladdenote', '$kladdedate', '-', '$brugernavn', '$brugernavn', '$tidspkt')",__FILE__ . " linje " . __LINE__);
			$tidspkt=microtime();
		}
		if ($kladde_id) {
			$bilag[$x]=db_escape_string($bilag[$x]);$dato[$x]=db_escape_string($dato[$x]);$beskrivelse[$x]=db_escape_string($beskrivelse[$x]);$d_type[$x]=db_escape_string($d_type[$x]);$debet[$x]=db_escape_string($debet[$x]);$k_type[$x]=db_escape_string($k_type[$x]);$kredit[$x]=db_escape_string($kredit[$x]);$faktura[$x]=db_escape_string($faktura[$x]);$belob[$x]=db_escape_string($belob[$x]);$momsfri[$x]=db_escape_string($momsfri[$x]);$afd[$x]=db_escape_string($afd[$x]);$projekt[$x]=db_escape_string($projekt[$x]);$ansat[$x]=db_escape_string($ansat[$x]);$valuta[$x]=db_escape_string($valuta[$x]);$forfaldsdato[$x]=db_escape_string($forfaldsdato[$x]);
			db_modify("insert into tmpkassekl (lobenr,id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,momsfri,afd,kladde_id,projekt,ansat,valuta,forfaldsdate,betal_id) values ('$x', '$id[$x]', '$bilag[$x]', '$dato[$x]', '$beskrivelse[$x]', '$d_type[$x]', '$debet[$x]', '$k_type[$x]', '$kredit[$x]', '$faktura[$x]', '$belob[$x]', '$momsfri[$x]', '$afd[$x]', '$kladde_id', '$projekt[$x]', '$ansat[$x]', '$valuta[$x]','$forfaldsdato[$x]','$betal_id[$x]')",__FILE__ . " linje " . __LINE__);
		}
		if ($fejl) $submit="Gem";
	}
	if ($fejl) $submit="Gem";
	if ($submit=="Kopier til ny") {
		kopier_til_ny($kladde_id,$bilagsnr,$ny_dato);
	}
	$fokus=$_POST['fokus'];
	if ($kladde_id) {
		$row = db_fetch_array(db_select("select bogfort,tidspkt from kladdeliste where id=$kladde_id",__FILE__ . " linje " . __LINE__));
		if (!$row['bogfort'] && $tidspkt==$row['tidspkt']) { #Refreshtjek"
			print "<BODY onLoad=\"javascript:alert('Brug af refresh konstateret - handling ignoreret')\">";
		} else {
			db_modify("update kladdeliste set kladdenote = '$ny_kladdenote', hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$kladde_id'",__FILE__ . " linje " . __LINE__);
			$kladdenote = $ny_kladdenote;
			if (!$kontonr) {
				$x=0;
				$query = db_select("select kontonr from kontoplan where kontotype != 'H' and kontotype != 'Z' and regnskabsaar=$regnaar",__FILE__ . " linje " . __LINE__);
				while($row = db_fetch_array($query)) {
					$x++;
					$kontonr[$x]=trim($row[kontonr]);
				}
				$acc_ant=$x;
			}
			if ($submit == "Opslag") $opslag_id=substr($fokus,4,strlen($fokus)-4);

			if ($kladde_id) {
				$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id",__FILE__ . " linje " . __LINE__));
				if ($row['bogfort']=='-') {
					for ($x=0;$x<=$antal_ny;$x++) {
						if (!isset($bilag[$x]))$bilag[$x]=NULL;			if (!isset($dato[$x]))$dato[$x]=NULL; 			if (!isset($beskrivelse[$x]))$beskrivelse[$x]=NULL;
						if (!isset($d_type[$x]))$d_type[$x]=NULL;		if (!isset($debet[$x]))$debet[$x]=NULL;			if (!isset($k_type[$x]))$k_type[$x]=NULL;
						if (!isset($kredit[$x]))$kredit[$x]=NULL;		if (!isset($faktura[$x]))$faktura[$x]=NULL;	if (!isset($belob[$x]))$belob[$x]=NULL;
						if (!isset($momsfri[$x]))$momsfri[$x]=NULL;	if (!isset($afd[$x]))$afd[$x]=NULL;
						if ((!$fejl)&&($x!=$opslag_id)&&(($beskrivelse[$x])||($debet[$x])||($kredit[$x]))) {
							kontroller($id[$x],$bilag[$x],$dato[$x],$beskrivelse[$x],$d_type[$x],$debet[$x],$k_type[$x],$kredit[$x],$faktura[$x],$belob[$x],$momsfri[$x],$kontonr,$kladde_id,$afd[$x],$projekt[$x],$ansat[$x],$valuta[$x],$forfaldsdato[$x],$betal_id[$x],$x);
						}
						elseif ((!$fejl)&&($x!=$opslag_id)&&($bilag[$x]=="-")) {
							kontroller($id[$x],$bilag[$x],$dato[$x],$beskrivelse[$x],$d_type[$x],$debet[$x],$k_type[$x],$kredit[$x],$faktura[$x],$belob[$x],$momsfri[$x],$kontonr,$kladde_id,$afd[$x],$projekt[$x],$ansat[$x],$valuta[$x],$forfaldsdato[$x],$betal_id[$x],$x );
						}
					}
					if ($fejl) $submit="Gem";
				}
			}
#******************************
			if ($submit == "Opslag") {
				if (strtoupper($debet[$opslag_id])=="K") {$d_type[$opslag_id]="K";}
				elseif (strtoupper($debet[$opslag_id])=="D") {$d_type[$opslag_id]="D";}
#				else {$d_type[$opslag_id]="F";}
				if (strtoupper($kredit[$opslag_id])=="K") {$k_type[$opslag_id]="K";}
				elseif (strtoupper($kredit[$opslag_id])=="D") {$k_type[$opslag_id]="D";}
#				else {$k_type[$opslag_id]="F";}
				$d_type[$opslag_id]=trim(strtoupper($d_type[$opslag_id]));
				$k_type[$opslag_id]=trim(strtoupper($k_type[$opslag_id]));
				if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))) {
					if($d_type[$opslag_id]=="K") kreditoropslag('firmanavn',$fokus,$opslag_id,$id[$opslag_id],$kladde_id,$bilag[$opslag_id],$dato[$opslag_id],$beskrivelse[$opslag_id],$d_type[$opslag_id],$debet[$opslag_id],$k_type[$opslag_id],$kredit[$opslag_id],$faktura[$opslag_id],$belob[$opslag_id],$momsfri[$opslag_id],$afd[$opslag_id],$projekt[$opslag_id],$ansat[$opslag_id],$valuta[$opslag_id],$forfaldsdato[$opslag_id],$betal_id[$opslag_id],$opslag_id);
					elseif($d_type[$opslag_id]=="D") debitoropslag('firmanavn',$fokus,$opslag_id,$id[$opslag_id],$kladde_id,$bilag[$opslag_id],$dato[$opslag_id],$beskrivelse[$opslag_id],$d_type[$opslag_id],$debet[$opslag_id],$k_type[$opslag_id],$kredit[$opslag_id],$faktura[$opslag_id],$belob[$opslag_id],$momsfri[$opslag_id],$afd[$opslag_id],$projekt[$opslag_id],$ansat[$opslag_id],$valuta[$opslag_id],$forfaldsdato[$opslag_id],$betal_id[$opslag_id],$opslag_id);
					else finansopslag($fokus,$opslag_id);
				}
				if ((strstr($fokus,"kred"))||(strstr($fokus,"k_ty"))) {
					if($k_type[$opslag_id]=="K") kreditoropslag('firmanavn',$fokus,$opslag_id,$id[$opslag_id],$kladde_id,$bilag[$opslag_id],$dato[$opslag_id],$beskrivelse[$opslag_id],$d_type[$opslag_id],$debet[$opslag_id],$k_type[$opslag_id],$kredit[$opslag_id],$faktura[$opslag_id],$belob[$opslag_id],$momsfri[$opslag_id],$afd[$opslag_id],$projekt[$opslag_id],$ansat[$opslag_id],$valuta[$opslag_id],$forfaldsdato[$opslag_id],$betal_id[$opslag_id],$opslag_id);
					if($k_type[$opslag_id]=="D") debitoropslag('firmanavn',$fokus,$opslag_id,$id[$opslag_id],$kladde_id,$bilag[$opslag_id],$dato[$opslag_id],$beskrivelse[$opslag_id],$d_type[$opslag_id],$debet[$opslag_id],$k_type[$opslag_id],$kredit[$opslag_id],$faktura[$opslag_id],$belob[$opslag_id],$momsfri[$opslag_id],$afd[$opslag_id],$projekt[$opslag_id],$ansat[$opslag_id],$valuta[$opslag_id],$forfaldsdato[$opslag_id],$betal_id[$opslag_id],$opslag_id);
					else {finansopslag($fokus,$opslag_id);}
				}
				if ((strstr($fokus,"fakt"))||(strstr($fokus,"belo"))) openpost ('firmanavn',$fokus,$opslag_id,$id[$opslag_id],$kladde_id,$bilag[$opslag_id],$dato[$opslag_id],$beskrivelse[$opslag_id],$d_type[$opslag_id],$debet[$opslag_id],$k_type[$opslag_id],$kredit[$opslag_id],$faktura[$opslag_id],$belob[$opslag_id],$momsfri[$opslag_id],$afd[$opslag_id],$projekt[$opslag_id],$ansat[$opslag_id],$valuta[$opslag_id],$forfaldsdato[$opslag_id],$betal_id[$opslag_id],$opslag_id);
				if (strstr($fokus,"afd")) {afd_opslag ($fokus,$opslag_id,$opslag_id);}
				if (strstr($fokus,"meda")) {ansat_opslag ($fokus,$opslag_id,$opslag_id);}
				if (strstr($fokus,"proj")) {projekt_opslag ($fokus,$opslag_id,$opslag_id);}
				if (strstr($fokus,"valu")) {valuta_opslag ($fokus,$opslag_id,$opslag_id);}
		 	}
			if (strstr($submit,"Simul")) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../finans/bogfor.php?kladde_id=$kladde_id&funktion=simuler\">";
/*
#				?>
#				<body onload="simuler()">
#				<?php
*/
			}
			if (strstr($submit,"Bogf"))	{
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../finans/bogfor.php?kladde_id=$kladde_id&funktion=bogfor\">";
			}
			if (strstr($submit,"Tilbagef")){
				tilbagefor($kladde_id);
			}
			if (strstr($submit,"Hent")) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../finans/hentordrer.php?kladde_id=$kladde_id\">";
			}
			if (strstr($submit,"Impor")) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../finans/importer.php?kladde_id=$kladde_id&bilagsnr=$bilagsnr\">";
			}
			if (strstr($submit,"Udlig")) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../finans/autoudlign.php?kladde_id=$kladde_id\">";
			}
			if (strstr($submit,"DocuB")) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../finans/docubizzimport.php?kladde_id=$kladde_id\">";
			}
		}
	}
} else {# endif ($_POST)
	($r=db_fetch_array(db_select("select * from grupper where ART = 'bilag' and (box6 ='on' or (box1 !='' and box2 !='' and box3 !=''))",__FILE__ . " linje " . __LINE__)))?$vis_bilag=1:$vis_bilag=0;
	($r['box6']=='on')?$intern_bilag=1:$intern_bilag=0;
	(db_fetch_array(db_select("select * from grupper where ART = 'AFD'",__FILE__ . " linje " . __LINE__)))?$vis_afd=1:$vis_afd=0;
	(db_fetch_array(db_select("select * from grupper where ART = 'PRJ'",__FILE__ . " linje " . __LINE__)))?$vis_projekt=1:$vis_projekt=0;
	(db_fetch_array(db_select("select * from grupper where ART = 'VK'",__FILE__ . " linje " . __LINE__)))?$vis_valuta=1:$vis_valuta=0;
}
if ($r=db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))) {
	$egen_kto_id = $r['id'];
	$z=0;
	$q=db_select("select id, initialer from ansatte where konto_id = '$egen_kto_id' and lukket != 'on' order by id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$z++;
		$vis_ansat=1;
		$ansat_id[$z]=$r['id'];
		$ansat_init[$z]=$r['initialer'];
	} 
}
if (!$fejl && $kladde_id) {
	opdater($kladde_id);
	db_modify ("delete from tmpkassekl where kladde_id=$kladde_id",__FILE__ . " linje " . __LINE__);
}
/*
if (strlen($kontrolkonto)==1) {
	$kontrolkonto=strtoupper($kontrolkonto);
	$query = db_select("select * from kontoplan where genvej='$kontrolkonto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) $kontrolkonto=$row['kontonr'];
	else {
		$kontrolkonto=' ';
		setcookie("saldi_ktrkto",$kontrolkonto,time()+0);
	}
}
if (strlen($kontrolkonto)>1) setcookie("saldi_ktrkto",$kontrolkonto,time()+60*60*24*30);
else setcookie("saldi_ktrkto",$kontrolkonto,time()-3600);
ob_end_flush();	//Sender det "bufferede" output afsted...
*/
		if ($kladde_id) {
	$query = db_select("select kladdenote, bogfort from kladdeliste where id = $kladde_id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$kladdenote = htmlentities(stripslashes($row['kladdenote']),ENT_QUOTES,$charset);
	$bogfort = $row['bogfort'];

}
$x=0;
if (!$simuler) {
	if ($returside != "regnskab"){$returside="../finans/kladdeliste.php";}
	($udskriv)?$height='':$height='height="100%"';
	print "<table width=\"100%\" $height border=\"0\" cellspacing=\"1\" cellpadding=\"0\"><tbody>"; # Tabel 1 -> Hovedramme
	if (!$udskriv) {
		print "<tr><td height=\"1%\" align=\"center\" valign=\"top\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>";# Tabel 1.1 -> Toplinje
		if ($menu=='T') {
			if ($popup) $leftbutton="<a title=\"Klik her for at lukke kassekladden\" href=\"../includes/luk.php\" accesskey=\"L\">LUK</a>";
			else $leftbutton="<a title=\"Klik her for at lukke kassekladden\" href=\"../finans/kladdeliste.php\" accesskey=\"L\">LUK</a>";
			$rightbutton="<a href=../finans/kassekladde.php?returside=../finans/kladdeliste.php&tjek=-1 accesskey=N>NY</a>";
			include("../includes/topmenu.php");
		} elseif ($menu=='S') {
			include("../includes/sidemenu.php");
		} else {
			if ($popup) print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\" $top_bund>";
			else print "<td $top_bund>";
			$tekst=findtekst(154,$sprog_id);
			if ($popup)	print "<a href=\"javascript:confirmClose('../includes/luk.php?tabel=kladdeliste&amp;id=$kladde_id','$tekst')\" accesskey=\"L\">Luk</a></td>";
			else print "<a href=\"javascript:confirmClose('../finans/kladdeliste.php','$tekst')\" accesskey=\"L\">Luk</a></td>";
			print "<td width=\"80%\" $top_bund>Kassekladde $kladde_id</td>";
			print "<td width=\"10%\" $top_bund align=\"right\"><a href=\"javascript:confirmClose('../finans/kassekladde.php','$tekst')\" accesskey=\"N\">Ny</a></td></tr>";
		}
		print "</tbody></table>";# Tabel 1.1 <- Toplinje
		print "</td></tr>\n";
	}
}
if (!$udskriv) {
	print "<form name=\"kassekladde\" action=\"../finans/kassekladde.php?kksort=$kksort\" method=\"post\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=\"$kladde_id\">";
	print "<input type=\"hidden\" name=\"kladdenote\" value=\"$kladdenote\">";

	print "<tr><td width=\"100%\" valign=\"top\" height=\"1%\ align=\"center\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align = \"center\" valign = \"top\">";
	print "<tbody>"; # Tabel 1.2 -> bemærkningstekst
	print "<tr>";
	print "<td></td>";
	if ($bogfort!="S") {
	print "<td width=\"890px\"><b> <span title= 'Her kan skrives en bem&aelig;rkning til kladden'>Bem&aelig;rkning:$nbsp</b>";
	print "<input class=\"inputbox\" type=\"text\" style=\"width:800px\" name=ny_kladdenote value=\"$kladdenote\" onchange=\"javascript:docChange = true;\"></td>";	
	}
	if ($bogfort=="-") {
		if (!isset($kontrolkonto) && isset($_COOKIE['saldi_ktrkto'])) $kontrolkonto = $_COOKIE['saldi_ktrkto'];
		if ($kontrolkonto == "-") $kontrolkonto = "";
		print "<td width=\"80px\"><span title= 'Angiv kontonummer til kontrol af kontobev&aelig;gelser'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:80px\" name=kontrolkonto value=\"$kontrolkonto\" onchange=\"javascript:docChange = true;\"></td>";
	} elseif ($bogfort!="S") {
		print "<td width=\"80px\" align=\"center\"><span title=\"Klik her for at opdatere\"><input type=submit style=\"width: 5em\" accesskey=\"o\" value=\"Opdater\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
	}
	print "<td width=\"10%\" align=\"right\"><a href=\"../finans/kassekladde.php?kladde_id=$kladde_id&udskriv=1\" target=\"blank\"><img src=\"../ikoner/print.png\" style=\"border: 0px solid;\"></a></td>";
	#print "</tr><tr><td><br color=\"$bgcolor5\" \"align=center\"></td></tr>\n";
	print "</tbody></table>";# Tabel 1.2 <- bemærkningstekst
	#}
	# ####################################################################################################
}
if ($udskriv) print "<tr><td style=\"width:100%;\">";
else print "<tr><td style=\"height:100%;width:100%;\"><div class=\"vindue\">"; 
if(($bogfort && $bogfort!='-')||$udskriv) print "<table cellpadding=\"0\" cellspacing=\"2\" border=\"0\" align = \"center\" valign = \"top\">";
elseif ($browser=="opera" || $browser=="firefox") print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align = \"center\" valign = \"top\">";
else print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align = \"center\" valign = \"top\" class=\"formnavi\">";
print "<tbody>"; # Tabel 1.3 -> kladdelinjer
print "<tr>";
if ($vis_bilag && !$fejl && !$udskriv) print "<td></td>";
print "<td align = center><b><span title= 'Skriv - (minus) for at slette en linje'><a href=../finans/kassekladde.php?kladde_id=$kladde_id&kksort=bilag,transdate&tjek=$kladde_id>Bilag</a></b></td>";
print "<td align = center><b> <span title= 'Angiv dato som ddmmyy (f.eks 241205)'><a href=../finans/kassekladde.php?kladde_id=$kladde_id&kksort=transdate,bilag&tjek=$kladde_id>Dato</a></b></td>";
print "<td align = center><b> Bilagstekst</b></td>";
print "<td align = center><b> <span title= 'Angiv D for debitor, K for kreditor eller F for finanspostering'>D/K</b></td>";
print "<td align = center><b> <span title= 'Skriv D eller K og klik p&aring; [Opslag] for opslag i hhv, debitor- eller kreditorkartotek'>Debet</b></td>";
print "<td align = center><b> <span title= 'Angiv D for debitor, K for kreditor eller F for finanspostering'>D/K</b></td>";
print "<td align = center><b> <span title= 'Skriv D eller K og klik p&aring; [Opslag] for opslag i hhv, debitor- eller kreditorkartotek'>Kredit</b></td>";
print "<td align = center><b> <span title= 'Angiv fakturanummer - klik p&aring; opslag for at sl&aring; op i &aring;bne poster. Skriv et minus her for at undertrykke automatisk udligning.'>Fakturanr.</b></td>";
print "<td align = center><b> <span title= 'Angiv belob - klik p&aring; opslag for at sl&aring; op i &aring;bne poster'><a href=../finans/kassekladde.php?kladde_id=$kladde_id&kksort=amount&tjek=$kladde_id>Bel&oslash;b</a></b></td>";
if ($vis_afd) print "<td align = left><b> <span title= 'Angiv hvilken afdeling posteringen h&oslash;rer under'>Afd.</b></td>";
if ($vis_ansat) print "<td align = left><b> <span title= 'Angiv hvilket ansatejder posteringen h&oslash;rer under'>ansat.</b></td>";
if ($vis_projekt) print "<td align = left><b> <span title= 'Angiv hvilket projekt posteringen h&oslash;rer under'>Proj.</b></td>";
if ($vis_valuta)print "<td align = left><b> <span title= 'Angiv valuta for posteringen'>Valuta</b></td>";
if (db_fetch_array(db_select("select id from kassekladde where kladde_id = '$kladde_id' and (k_type = 'K' or d_type = 'D')",__FILE__ . " linje " . __LINE__))) {
	print  "<td  align=\"center\"><b> <span title= 'Betalingsdato for debitor eller kreditorfaktura'>Forfald</b></td>";
	if ($vis_bet_id) print "<td  align=\"center\"><b> <span title= 'Betalingsid fra girokort - Kun nummeret skal skrives'>Betal.id</b></td>";
}
print "<td align=\"center\" width=\"30px\"><b> <span title= 'Afm&aelig;rk her, hvis der ikke skal tr&aelig;kkes moms'>&nbsp;u/m</b></td>";
print "</tr>\n";

#####################################  Output  #################################

$r=db_fetch_array(db_select("select * from grupper where ART = 'KASKL' and kode='1' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__));
$kksort=$r['box1'];
$kontrolkonto=$r['box2'];
if ($kladde_id) {
	if ($kksort!='transdate,bilag' && $kksort!='amount') $kksort='bilag,transdate';
	$id=array();$bilag=array();$dato=array();$beskrivelse=array();$d_type=array();$debet=array();$k_type=array();$kredit=array();
	$faktura=array();$belob=array();$afd=array();$ansat=array();$ansat_id=array();$projekt=array();$valuta=array();$forfaldsdato=array();$betal_id=array();$momsfri=array();
	if ($popup) print "<meta http-equiv=\"refresh\" content=\"3600;URL=../includes/luk.php?tabel=kladdeliste&id=$kladde_id\">";
	else print "<meta http-equiv=\"refresh\" content=\"3600;URL=../finans/kladdeliste.php?tabel=kladdeliste&id=$kladde_id\">";
	$query = db_select("select * from tmpkassekl where kladde_id = $kladde_id order by lobenr",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$query = db_select("select * from tmpkassekl where kladde_id = $kladde_id order by lobenr",__FILE__ . " linje " . __LINE__);
		$fejl=1;
	} else {
		$query = db_select("select * from kassekladde where kladde_id = $kladde_id order by $kksort, id",__FILE__ . " linje " . __LINE__);
	}
	$bilagssum=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$valuta[$x]='DKK';
		$id[$x]=$row['id'];
		$bilag[$x]=$row['bilag'];
		if ($fejl) {
			$transdate[$x]=usdate($row['transdate']);
			$dato[$x]=$row['transdate'];
			if ($row['forfaldsdate']) {
				$forfaldsdate[$x]=usdate($row['forfaldsdate']);
				$forfaldsdato[$x]=$row['forfaldsdate'];
			}
		}	else {
			$transdate[$x]=$row['transdate'];
			$dato[$x]=dkdato($row['transdate']);
			if ($row['forfaldsdate']) {
				$forfaldsdate[$x]=$row['forfaldsdate'];
				$forfaldsdato[$x]=dkdato($row['forfaldsdate']);
			}
		}
#		$beskrivelse[$x]=htmlentities($row['beskrivelse'],ENT_QUOTES,$charset);
		$beskrivelse[$x]=$row['beskrivelse'];
		while (strpos($beskrivelse[$x],"''")) $beskrivelse[$x]=str_replace("''","'",$beskrivelse[$x]); #20130731
		$beskrivelse[$x]=htmlentities($beskrivelse[$x],ENT_QUOTES,$charset);
		$dokument[$x]=$row['dokument']; # ligger allerede med htmlentities i tabellen;
		$d_type[$x]=trim($row['d_type']);
		$debet[$x]=$row['debet'];
		$k_type[$x]=$row['k_type'];
		if ($k_type[$x]=="K" || $d_type[$x]=="D") $vis_forfald=1;
		$kredit[$x]=$row['kredit'];
		$faktura[$x]=htmlentities($row['faktura'],ENT_QUOTES,$charset);
		$amount[$x]=$row['amount'];
		if ($fejl) {
			$belob[$x]=$amount[$x];
			$amount[$x]=usdecimal($amount[$x]);
		} else $belob[$x]=dkdecimal($amount[$x]);
		$momsfri[$x]=$row['momsfri'];
		$afd[$x]=$row['afd'];
		if ($fejl) $ansat[$x]=$row['ansat'];
		else $ansat_id[$x]=$row['ansat'];
		if (!$fejl && $ansat_id[$x]) {
			$r2 = db_fetch_array(db_select("select navn, initialer from ansatte where id='$ansat_id[$x]'",__FILE__ . " linje " . __LINE__));
			$ansat[$x]=$r2['initialer'];
			$ansat_navn[$x]=$r2['navn'];
		} elseif (!$fejl) $ansat[$x]='';
		($row['projekt'])?$projekt[$x]=$row['projekt']:$projekt[$x]='';
		if ($fejl) $valuta[$x]=$row['valuta'];
		else {
			$valutakode[$x]=$row['valuta']*1;
			if ($valutakode[$x]) {
				$r2 = db_fetch_array(db_select("select box1 from grupper where art='VK' and kodenr ='$valutakode[$x]'",__FILE__ . " linje " . __LINE__));
				$valuta[$x]=$r2['box1'];
			}
		}
		if (!$valuta[$x] || $valuta[$x]=='DKK') $dkkamount[$x]=$amount[$x];
		elseif ($valutakode[$x]) list($dkkamount[$x],$diffkonto[$x],$valutakurs[$x])=valutaopslag($amount[$x],$valutakode[$x],$transdate[$x]);
 		else $dkkamount[$x]=$amount[$x]*1;
		if (!$beskrivelse) {$beskrivelse='';}
		if (($d_type[$x]=='F')&&($debet[$x])&&(!$fejl)) {
			$query2 = db_select("select beskrivelse, moms from kontoplan where kontonr='$debet[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
			$row2 = db_fetch_array($query2);
			$debettext[$x]=$row2['beskrivelse'];
			if (trim($row2['moms'])) $debettext[$x]=$debettext[$x]."&nbsp;-&nbsp;".trim($row2['moms']);
		}
		if ((($d_type[$x]=='D')||($d_type[$x]=='K'))&&($debet[$x])&&(!$fejl)) {
			$query2 = db_select("select firmanavn,betalingsbet,betalingsdage from adresser where kontonr='$debet[$x]' and art = '$d_type[$x]'",__FILE__ . " linje " . __LINE__);
			$row2 = db_fetch_array($query2);
			$debettext[$x]=trim($row2['firmanavn']);
			$tmpffdato=forfaldsdag($transdate[$x],$row2['betalingsbet'],$row2['betalingsdage']);
		}
		if (($k_type[$x]=='F')&&($kredit[$x])&&(!$fejl)) {
			$query2 = db_select("select beskrivelse, moms from kontoplan where kontonr='$kredit[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
			$row2 = db_fetch_array($query2);
			$kredittext[$x]=trim($row2['beskrivelse']);
			if (trim($row2['moms'])) $kredittext[$x]=$kredittext[$x]."&nbsp;-&nbsp;".trim($row2['moms']);
		}
		if ((($k_type[$x]=='D')||($k_type[$x]=='K'))&&($kredit[$x])&&(!$fejl)) {
			$query2 = db_select("select firmanavn,betalingsbet,betalingsdage from adresser where kontonr='$kredit[$x]' and art = '$k_type[$x]'",__FILE__ . " linje " . __LINE__);
			$row2 = db_fetch_array($query2);
			$kredittext[$x]=trim($row2['firmanavn']);

			$tmpffdato=forfaldsdag($transdate[$x],$row2['betalingsbet'],$row2['betalingsdage']);
		}
		if ((($d_type[$x]=='D'&& $debet[$x])||($k_type[$x]=='K'&& $kredit[$x])) && !$fejl && $gl_transdate[$x] && (!$forfaldsdato[$x] || $gl_transdate[$x] != $transdate[$x])) { #20140624
			$forfaldsdato[$x]=$tmpffdato;
		}
		$betal_id[$x]=$row['betal_id'];
	}
	if (!$fejl) db_modify ("delete from tmpkassekl where kladde_id=$kladde_id",__FILE__ . " linje " . __LINE__);
}
for ($y=1;$y<=$x;$y++)
if (!$fejl) $antal_ex=$x;
if (($bogfort && $bogfort!='-') || $udskriv) {
	for ($y=1;$y<=$x;$y++) {
		if (!$beskrivelse[$y]) $beskrivelse[$y]="&nbsp;";
#		if (($d_type[$y]!="D")&&($d_type[$y]!="K")) $d_type[$y]="F"; #phr 20070801
		if ($debet[$y] < 1){
			$debet[$y]="&nbsp;";
			$d_type[$y]="&nbsp;"; #phr 20070801
		}
#		if (($k_type[$y]!="D")&&($k_type[$y]!="K")) $k_type[$y]="F"; #phr 20070801
		if ($kredit[$y] < 1){
			$kredit[$y]="&nbsp;";
			$k_type[$y]="&nbsp;"; #phr 20070801
		}
		if (!$faktura[$y]) $faktura[$y]="&nbsp;";
		($linjebg!=$bgcolor)?$linjebg=$bgcolor:$linjebg=$bgcolor5;
		print "<tr bgcolor=$linjebg>";
		if ($vis_bilag && !$fejl && !$udskriv) {
			if ($dokument[$y]) print "<td title=\"klik her for at &aring;bne bilaget: $dokument[$y]\"><a href=\"../includes/bilag.php?kilde=kassekladde&filnavn=$dokument[$y]&bilag_id=$id[$y]&bilag=$bilag[$y]&kilde_id=$kladde_id\"><img style=\"border: 0px solid\" alt=\"clip_m_papir\" src=\"../ikoner/paper.png\"></a></td>";
			else print "<td title=\"klik her for at vedh&aelig;fte et bilag\"><a href=\"../includes/bilag.php?kilde=kassekladde&bilag_id=$id[$y]&bilag=$bilag[$y]&ny=ja&kilde_id=$kladde_id\"><img  style=\"border: 0px solid\" alt=\"clip\" src=\"../ikoner/clip.png\"></a></td>";
		}
		print "<td> $bilag[$y]</td>";
		print "<td> $dato[$y]</td>";
		print "<td> $beskrivelse[$y]</td>";
		print "<td> $d_type[$y]</td>";
		print "<td align=right title='$debettext[$y]'>$debet[$y]</td>";
		print "<td> $k_type[$y]</td>";
		print "<td align=right title='$kredittext[$y]'>$kredit[$y]</td>";
		print "<td align=right>$faktura[$y]</td>";
		print "<td align=right>$belob[$y]</td>";
		if ($vis_afd) print "<td align=right> $afd[$y]</td>";
		if ($vis_ansat) print "<td align=right>$ansat[$y]</td>";
		if ($vis_projekt) print "<td align=right>$projekt[$y]</td>";
		if ($vis_valuta) print "<td align=right>$valuta[$y]</td>";
		if ($forfaldsdato[$y]) {
			print "<td>$forfaldsdato[$y]</td>";
			if ($vis_bet_id) print "<td>$betal_id[$y]</td>";
		} elseif ($vis_forfald) {
			print "<td><br></td>";
			if ($vis_bet_id) print "<td><br></td>";
		}
		if (strstr($momsfri[$y],"on")) {print "<td align=center> V</td>";}
		else {print "<td> <br></td>";}
		if (!$udskriv && $bogfort!='S') print "<td title=\"Tilbagef&oslash;r postering\"><a href='../finans/kassekladde.php?kladde_id=$kladde_id&ompost=$id[$y]'><img alt=\"undo\" src=\"../ikoner/undo.png\" style=\"border: 0px solid ; width: 18px; height: 17px;\"></a></td>";
		print "</tr>\n";
	}
	print "<tr><td><br></td></tr>";
} else { ################################ Kladden er ikke bogfort ########################################
	$debetsum=0;
	$kreditsum=0;
	$de_fok="onfocus=\"fokuser(this,'#000000','#EFEFEF');\" onblur=\"defokuser(this,'#000000','#FFFFFF');\"";
	if ($kontrolkonto) {
		$kontrolkonto=$kontrolkonto*1;
		if ($r=db_fetch_array(db_select("select saldo,moms from kontoplan where kontonr='$kontrolkonto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))){
			$kontrolsaldo=$r['saldo'];
			if ($r['moms']) {
				$r=db_fetch_array(db_select("select box2 from grupper where
						kode='".substr($r[moms],0,1)."' and
						kodenr='".substr($r[moms],1,1)."'",__FILE__ . " linje " . __LINE__));
				$kontrolmoms=$r['box2']*1;
			}
		} else {
			$kontrolsaldo=0;
			$kontrolmoms=0;
		}
	}
	if(!isset($bilag[0]))$bilag[0]=NULL; if(!isset($bilag[$x+1]))$bilag[$x+1]=NULL;
	if(!isset($dato[0]))$dato[0]=NULL; if(!isset($dato[$x+1]))$dato[$x+1]=NULL;
	for ($y=1;$y<=$x;$y++) {
		if (!isset($bilag[$y]))$bilag[$y]=NULL; if (!isset($dato[$y]))$dato[$y]=NULL;
		if (!isset($kredit[$y]))$kredit[$y]=NULL; if (!isset($debet[$y]))$debet[$y]=NULL;
		if (!isset($kredittext[$y]))$kredittext[$y]=NULL; if (!isset($debettext[$y]))$debettext[$y]=NULL;

		if ((!$fejl)&&((($bilag[$y])=="-")||(!$bilag[$y])&&(!$dato[$y]))) {
			$bilag[$y]='';$dato[$y]='';$beskrivelse[$y]='';$d_type[$y]='';$debet[$y]='';$k_type[$y]='';$kredit[$y]='';$faktura[$y]='';$belob[$y]='';$momsfri[$y]='';$afd[$y]='';$projekt[$y]='';$valuta[$y]='';$forfaldsdato[$y]='';$betal_id[$y]='';
		}
		if ($fejl&&!$dato[$y]&&!$beskrivelse[$y]&&!$debet[$y]&&!$kredit[$y]&&!$faktura[$y]&&!$belob[$y]) $bilag[$y]='';
		if (!$fejl&&$debet[$y] < 1) $debet[$y]="";
		if (!$fejl&&$kredit[$y] < 1) $kredit[$y]="";
		if ($fejl) $amount[$y]=usdecimal($amount[$y]); # phr 20070801
		if (!$debet[$y]) $debet[$y]="";
		if (!$kredit[$y]) $kredit[$y]="";
#		if($valuta[$y]&&$valuta[$y]!='DKK') {
#		if ($r=db_fetch_array(db_select("select kodenr from grupper where art='VK' and box1='$valuta[$y]'",__FILE__ . " linje " . __LINE__))) {
#			if ($r=db_fetch_array(db_select("select kurs from valuta where gruppe='$r[kodenr]' and valdate < '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
#				$dk_amount=$amount[$y]*$r['kurs']/100;
#				} else $dk_amount=0;
#			} else $dk_amount=$amount[$y];
#		} else $dk_amount=$amount[$y];
		if ($momsfri[$y] || !$kontrolmoms) $tmp=1;
		else $tmp=(100+$kontrolmoms)/100;
		if ($d_type[$y]=='F' && $debet[$y]==$kontrolkonto) $kontrolsaldo=$kontrolsaldo+$dkkamount[$y]/$tmp;
		if ($k_type[$y]=='F' && $kredit[$y]==$kontrolkonto) $kontrolsaldo=$kontrolsaldo-$dkkamount[$y]/$tmp;
		if ($id[$y] && $debet[$y] && is_numeric($debet[$y]) && $kredit[$y] && is_numeric($kredit[$y])) list($dub_bilag[$y],$dub_kladde_id[$y])=explode(",",find_dublet($id[$y],$transdate[$y],$d_type[$y],$debet[$y],$k_type[$y],$kredit[$y],$amount[$y]));
  	print "<tr>";
		if ($vis_bilag && !$fejl) {
			if ($dokument[$y]) print "<td title=\"klik her for at &aring;bne bilaget: $dokument[$y]\"><a href=\"../includes/bilag.php?kilde=kassekladde&filnavn=$dokument[$y]&bilag_id=$id[$y]&bilag=$bilag[$y]&kilde_id=$kladde_id&fokus=bila$y\"><img style=\"border: 0px solid\" src=\"../ikoner/paper.png\"></a></td>";
			else print "<td title=\"klik her for at vedh&aelig;fte et bilag\"><a href=\"../includes/bilag.php?kilde=kassekladde&bilag_id=$id[$y]&bilag=$bilag[$y]&ny=ja&kilde_id=$kladde_id&fokus=bila$y\"><img  style=\"border: 0px solid\" src=\"../ikoner/clip.png\"></a></td>";
		}
		if ($dub_bilag[$y] && $dub_kladde_id[$y]) {
			$title="title=\"En tilsvarende postering er også ført på kladde $dub_kladde_id[$y] med bilagsnummer $dub_bilag[$y]\"";
			$color="color:#FF0000;";
		} else {
			$title=NULL;
			$color=NULL;
		}
		print "<td><input class=\"inputbox\" $title type=\"text\" style=\"text-align:right;width:50px;$color\" name=bila$y $de_fok value =\"$bilag[$y]\"\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:75px;\" name=dato$y $de_fok value =\"$dato[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:300px;\" name=besk$y $de_fok value =\"$beskrivelse[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:25px;\" name=d_ty$y $de_fok value =\"$d_type[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		if (($k_type[$y]=='D' || $k_type[$y]=='K') && $kredit[$y] && !$debet[$y]) {
			$libtxt=sidste_5($kredit[$y],$k_type[$y],'D');
			print "<td>
			<span onclick=\"return overlib('".$libtxt."', WIDTH=800);\" onmouseout=\"return nd();\">
			<input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=debe$y $de_fok value =\"$debet[$y]\" onchange=\"javascript:docChange = true;\">
			</span></td>";
		} else print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=debe$y $de_fok value =\"$debet[$y]\" title=\"$debettext[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:25px;\" name=k_ty$y $de_fok value =\"$k_type[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		if (($d_type[$y]=='D' || $d_type[$y]=='K') && $debet[$y] && !$kredit[$y]) {
			$libtxt=sidste_5($debet[$y],$d_type[$y],'K');
			print "<td>
			<span onclick=\"return overlib('".$libtxt."', WIDTH=800);\" onmouseout=\"return nd();\">
			<input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=kred$y $de_fok value =\"$kredit[$y]\" onchange=\"javascript:docChange = true;\">
			</span></td>";
		} else print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=kred$y $de_fok value =\"$kredit[$y]\" title= \"$kredittext[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=fakt$y $de_fok value =\"$faktura[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		if ($valuta[$y]=='DKK') $title="";
		else $title="DKK: ".dkdecimal($dkkamount[$y]);
		print "<td title=\"$title\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=belo$y $de_fok value =\"$belob[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_afd) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=afd_$y $de_fok value =\"$afd[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_ansat) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=meda$y $de_fok value =\"$ansat[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_projekt) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=proj$y $de_fok value =\"$projekt[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_valuta) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:40px;\" name=valu$y $de_fok value =\"$valuta[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		if ($k_type[$y]=='K' || $d_type[$y]=='D') {
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:75px;\" name=forf$y $de_fok value =\"$forfaldsdato[$y]\" onchange=\"javascript:docChange = true;\"></td>";
			if ($vis_bet_id) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:75px;\" name=b_id$y $de_fok value =\"$betal_id[$y]\" onchange=\"javascript:docChange = true;\"></td>";
		} elseif ($vis_forfald) {
			print "<td><input class=\"inputbox\" readonly=readonly size=10><br></td>";
			if ($vis_bet_id) print "<td><input class=\"inputbox\" readonly=readonly size=10><br></td>";
		}
		if ($momsfri[$y] == 'on') {print "<td align=\"center\"><input class=\"inputbox\" type=checkbox name=moms$y checked onchange=\"javascript:docChange = true;\" ></td>";}
		else {print"<td align=\"center\"><input class=\"inputbox\" type=checkbox name=moms$y onchange=\"javascript:docChange = true;\"></td>";}
		if ($kontrolkonto) {print "<td align=right>".dkdecimal($kontrolsaldo) ."</td>";}
		print "<input type=hidden name=\"id[$y]\" value='$id[$y]'>";
		print "<input type=hidden name=\"dkka$y\" value='$dkkamount[$y]'>";
		print "<input type=hidden name=\"transdate[$y]\" value='$transdate[$y]'>";
		print "</tr>\n";
		if ($kksort=="bilag,transdate") {
			if ($bilag[$y] != $bilag[$y-1]) {
				$debetsum=0;
				$kreditsum=0;
				$amount[$x+1]=0;
			}
			if ((($debet[$y])||($kredit[$y]))&&($amount[$y] > 0)) {
				if (($debet[$y])||($debet[$y]>0)) $debetsum=$debetsum+$dkkamount[$y];
				if (($kredit[$y])||($kredit[$y]>0)) $kreditsum=$kreditsum+$dkkamount[$y];
				if ((!$bilag[$x+1])||($bilag[$x+1]<$bilag[$y])) $bilag[$x+1]=$bilag[$y];
				if (!$dato[$x+1]) $dato[$x+1]=$dato[$y];
				$amount[$x+1]=$debetsum-$kreditsum;
			}
		}
	}
	$aa=$x+1;
	if (!isset($amount[$x+1])) $amount[$x+1]=0;
	if (abs($amount[$x+1])>0.01) {
		$beskrivelse[$x+1]=$beskrivelse[$x];
		$bilag[$x+1]=$bilag[$x];
		$dato[$x+1]=$dato[$x];
		$valuta[$x+1]=$valuta[$x]; #20121110 Rettet fra $valuta[$x+1]='DKK'  
	}
#	else {$bilag[$x+1]=$bilag[$x]+1;}
# Udeladt 121207 - Har vis ingen funktion??
# Genindsat 060408 - Tildeler bilagsnummer hvis bilag i balance
	elseif ($bilag[$x+1]==$bilag[$x]) {
		if (isset($amount[$x+1]) && $amount[$x] > 0) {
			$amount[$x+1]='';
			if ($bilag[$x]!='0') $bilag[$x+1]=$bilag[$x]+1;
			$dato[$x+1]=$dato[$x];
		}
	}#end if($bilag[$x+1]==$bilag[$x])

	if ($x > 20) {$y=$x+5;}
	else {$y=24;}
	$x++;
	if ($amount[$x]<0){$amount[$x]=$amount[$x]*-1;}
	if ($amount[$x]) $belob=dkdecimal($amount[$x]);
	else $belob="";
	if (!isset($amount[$x-1]))$amount[$x-1]=0;
	if (($amount[$x-1])&&($amount[$x-1]<0.01)) {
		$bilag[$x]="";
		$dato[$x]="";
		$belob="";
	}
	if ($fokus && (strstr($fokus,"belo") || strstr($fokus,"afd")) && strstr($submit,"Gem")) {
		$tmp=substr($fokus,4)+1;
		if (!$debet[$tmp] && !$kredit[$tmp]) $fokus=nextfokus($fokus);
	}
	if (!isset($dato[$y]))$dato[$y]=NULL;			if (!isset($beskrivelse[$y]))$beskrivelse[$y]=NULL;	if (!isset($debet[$y]))$debet[$y]=NULL;
	if (!isset($kredit[$y]))$kredit[$y]=NULL;	if (!isset($faktura[$y]))$faktura[$y]=NULL; if (!isset($valuta[$y]))$valuta[$y]=NULL;
	if (((($bilag[$x]=="-")||(!$dato[$y]&&!$beskrivelse[$y]&&!$debet[$y]&&!$kredit[$y]&&!$faktura[$y]&&!$amount[$x]))&&($x==1))||(!$kladde_id)) {
		$bilag[$x]=1;
		if (!$regnstart) list ($regnstart,$regnslut) = explode(":",regnskabsaar($regnaar));
		$query = db_select("select MAX(bilag) as bilag from kassekladde where transdate>='$regnstart' and transdate<='$regnslut'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $bilag[$x]=$row['bilag']+1;
	}
	if (!isset($debet[$x-1]))$debet[$x-1]=NULL;	if (!isset($kredit[$x-1]))$kredit[$x-1]=NULL;

	if (($bilag[$x])&&(!$dato[$x])) $dato[$x]=dkdato(date("Y-m-d"));
	if ($x<3000 && (($debet[$x-1])||($kredit[$x-1])||$x==1)) {
		if (!isset($dato[$x]))$dato[$x]=NULL;			if (!isset($beskrivelse[$x]))$beskrivelse[$x]=NULL;	if (!isset($debet[$x]))$debet[$x]=NULL;
		if (!isset($kredit[$x]))$kredit[$x]=NULL;	if (!isset($faktura[$x]))$faktura[$x]=NULL;					if (!isset($d_type[$x]))$d_type[$x]=NULL;
		if (!isset($k_type[$x]))$k_type[$x]=NULL;	if (!isset($afd[$x]))$afd[$x]=NULL;									if (!isset($momsfri[$x]))$momsfri[$x]=NULL;
		if (!isset($projekt[$x]))$projekt[$x]=NULL;if (!isset($valuta[$x]))$valuta[$x]=NULL;					if (!isset($ansat[$x]))$ansat[$x]=NULL;
		print "<tr>";
		if ($vis_bilag && !$fejl) { #20140425
			if ($kladde_id && $intern_bilag) print "<td title=\"klik her for at vedh&aelig;fte et bilag\"><a href=\"../includes/bilag.php?kilde=kassekladde&bilag_id=$id[$x]&bilag=$bilag[$x]&ny=ja&kilde_id=$kladde_id&fokus=bila$x\"><img  style=\"border: 0px solid\" src=\"../ikoner/clip.png\"></a></td>";
			else print "<td></td>";
		}
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=bila$x $de_fok value =\"$bilag[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:75px;\" name=\"dato$x\" $de_fok value =\"$dato[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:300px;\" name=besk$x $de_fok value =\"$beskrivelse[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:25px;\" name=\"d_ty$x\" $de_fok value =\"$d_type[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=debe$x $de_fok value =\"$debet[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:25px;\" name=k_ty$x $de_fok value =\"$k_type[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=kred$x $de_fok value=\"$kredit[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=fakt$x $de_fok value=\"$faktura[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=belo$x $de_fok value=\"$belob\" onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_afd) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=afd_$x $de_fok value=\"$afd[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_ansat) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=meda$x $de_fok value =\"$ansat[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_projekt) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=proj$x $de_fok value =\"$projekt[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_valuta) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:40px;\" name=valu$x $de_fok value =\"$valuta[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		if ($k_type[$y]=='K' || $d_type[$y]=='D') {
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:75px;\" name=forf$x $de_fok value =\"$forfaldsdato[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:75px;\" name=b_id$x $de_fok value =\"$betal_id[$x]\" onchange=\"javascript:docChange = true;\"></td>";
		} elseif ($vis_forfald) {
			print "<td><input  class=\"inputbox\" readonly=readonly size=10><br></td>";
			if ($vis_bet_id) print "<td><input  class=\"inputbox\" readonly=readonly size=10><br></td>";
		}
		if ($momsfri[$x] == 'on') {print"<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=moms$x checked onchange=\"javascript:docChange = true;\"></td>";}
		else {print"<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=moms$x onchange=\"javascript:docChange = true;\"></td>";}
	}
	if ($x!=1 || $bilag[$x]) $bilagsnr=$bilag[$x];
	if ($x < 3000) {
		if ($x > 6) {$y=$x+5;}
		else {$y=10;}
		if ($fejl) $y=$x;
	} else $y=$x-1;
	$x++;
	if ($x==1) {$bilag[1]='';$dato[1]='';$beskrivelse[1]='';$d_type[1]='';$debet[1]='';$k_type[1]='';$kredit[1]='';$faktura[1]='';$belob[1]='';$momsfri[1]='';$afd[1]='';}
	for ($z=$x;$z<=$y;$z++) {
		print "<tr>";
		if ($vis_bilag && !$fejl) print "<td><br></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=bila$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:75px;\" name=dato$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:300px;\" name=besk$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:25px;\" name=d_ty$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=debe$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:25px;\" name=k_ty$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=kred$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=fakt$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:75px;\" name=belo$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_afd) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=afd_$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_ansat) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=meda$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_projekt) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=proj$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_valuta) print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:40px;\" name=valu$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
#		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:75px;\" name=forf$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
#		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:75px;\" name=b_id$z $de_fok onchange=\"javascript:docChange = true;\"></td>";
		if ($vis_forfald) {
			print "<td><input  class=\"inputbox\" readonly=readonly size=10><br></td>";
			if ($vis_bet_id) print "<td><input  class=\"inputbox\" readonly=readonly size=10><br></td>";
		}
		print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=moms$z onchange=\"javascript:docChange = true;\"></td>";
		print "</tr>\n";
	}
	if (count($bilag)<10) print "<tr><td align=\"center\" colspan=\"8\">Hint - du kan navigere i kassekladen med piletaster hvis du holder [Ctrl] nede (Fungerer ikke i Firefox)</td></tr>";
	print "<input type=\"hidden\" name=\"fokus\" id=\"fokus\">";
	print "<input type=hidden name=kladde_id value=$kladde_id>";
	$tidspkt=microtime();
	print "<input type=hidden name=tidspkt value=$tidspkt>";
	print "<input type=hidden name=bilagsnr value=$bilagsnr>";
	print "<input type=hidden name=antal_ex value='$antal_ex'>";
	print "<input type=hidden name=antal_ny value='$y'>";
} #end if $bogfort...else

	($udskriv)?$div='':$div='</div>';
	?>
	<script	src="../javascript/jquery.formnavigation.js/jquery-1.10.2.min.js"></script>
	<script	src="../javascript/jquery.formnavigation.js"></script>
	<script>
	$(document).ready(function () {
		$('.formnavi').formNavigation();
	});
	</script>
	<?php
	print "</tbody></table>$div</td></tr>"; # Tabel 1.3 <- Kladdelinjer
	print "<tr><td><br></td></tr>\n";
	print "<tr><td align=center>";
	print "<table width=\"800px\" border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody><tr>"; # Tabel 1.4 -> Knapper
	if (!$udskriv) {
	if ($bogfort=='V'){
#		print "<input type=hidden name=ny_kladdenote value=\"$kladdenote\">";
		print "<tr><td colspan=9 align=center><input type=submit accesskey=\"k\" value=\"Kopi&eacute;r til ny\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td></tr>\n";
		print "</form>";
#		print "</tbody></table></td></tr>\n";
#		print "</tbody></table>";
	} elseif ($bogfort=='!'){
#		print "<input type=hidden name=ny_kladdenote value=\"$kladdenote\">";
		print "<tr><td colspan=9 align=center><input type=submit accesskey=\"b\" value=\"Tilbagef&oslash;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td></tr>\n";
		print "</form>";
#		print "</tbody></table></td></tr>\n";
#		print "</tbody></table>";
	} elseif ($bogfort=='S'){
		print "<tr><td colspan=9 align=center><input type=submit accesskey=\"a\" value=\"Annuller simulering\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td></tr>\n";
		print "</form>";
	} else {
		print "<td align=center><span title=\"Klik her for at gemme\"><input type=submit style=\"width: 5em\" accesskey=\"g\" value=\"Gem\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
		print "<td align=center><span title=\"Opslag - din mark&oslash;rs placering angiver hvilken tabel, opslag foretages i\"><input type=submit style=\"width: 5em\" accesskey=\"o\" value=\"Opslag\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
		if ($kladde_id && !$fejl) {
			print "<td align=center><span title=\"Simulering af bogf&oslash;ring viser bev&aelig;gelser i kontoplanen\"><input type=submit style=\"width: 5em\" accesskey=\"s\" value=\"Simul&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
			print "<td align=center><span title=\"Bogf&oslash;r - der foretages f&oslash;rst en simulering, som du skal bekr&aelig;fte\"><input type=submit style=\"width: 5em\" accesskey=\"b\" value=\"Bogf&oslash;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
			if (!$fejl && db_fetch_array(db_select("select id from ordrer where status=3",__FILE__ . " linje " . __LINE__))) {
				print "<td align=center><span title=\"Henter afsluttede ordrer fra ordreliste\"><input type=submit style=\"width: 5em\" accesskey=\"h\" value=\"Hent ordrer\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
			}
			if(db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '2' and box6='on'",__FILE__ . " linje " . __LINE__))) {
				print "<td align=center><input type=submit style=\"width: 5em\" accesskey=\"d\" value=\"DocuBizz\" name=\"submit\" onclick=\"javascript:docChange = false;\" onclick=\"return confirm('Importer data fra DocuBizz?')\"></td>";
			}
			print "<td align=center><span title=\"Importerer bankposteringer eller andre data fra .csv-fil (kommasepareret fil)\"><input type=submit style=\"width: 5em\" accesskey=\"i\" value=\"Import\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
			print "<td align=center><span title=\"Finder &aring;bne poster, som modsvarer bel&oslash;b og fakturanummer\"><input type=submit style=\"width: 5em\" accesskey=\"u\" value=\"Udlign\" name=\"submit\" onclick=\"javascript:docChange = false;\"></span></td>";
		}
	}
	print "</form>";
	}
	print "</tbody></table></td></tr>\n"; # Tabel 1.4 <- Knapper 
#	if ($udskriv) print "<tr><td width=\"100%\" height=\"100%\">zz</td></tr>";
	print "</tbody></table>"; # Tabel 1 <- 
	if ($udskriv) {
		print "<body onLoad=\"javascript:window.print()\">";#;javascript:window.close();\">";
		
	}
######################################################################################################################################
function debitoropslag($sort,$fokus,$opslag_id,$id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$afd,$projekt,$ansat,$valuta,$forfaldssato,$betalings_id,$lobenr) {

	global $bgcolor2;
	global $top_bund;

	$beskrivelse=urlencode(stripslashes($beskrivelse));
#	$beskrivelse=(str_replace("&","!og!",$beskrivelse));
#	$beskrivelse=(str_replace("'","!apostrof!",$beskrivelse));
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
	$faktura=urlencode(trim(stripslashes($faktura)));
	$belob=trim($belob);

	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd' accesskey=L>Luk</a></td>";
	print"<td width=\"80%\" $top_bund>Debitorliste</td>";
	print "<td width=\"10%\" $top_bund align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../debitor/debitorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\"><a href='../finans/kassekladde.php?sort=$sort&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'><u>Ny</u></a></td>";
#	else print"<td width=\"10%\" $top_bund align=\"right\"><a href=../debitor/debitorkort.php?returside=../finans/kasseklade.php&id=$id accesskey=N>Ny</a></td>";

	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=kontonr&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Kundenr</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=firmanavn&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Navn</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=addr1&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Adresse</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=addr2&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Adresse2</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=postnr&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Postnr</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=bynavn&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>By</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=kontakt&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Kontaktperson</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=tlf&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Telefon</a></b></td>";
	print" </tr>\n";

	 $sort = $_GET['sort'];
	 if (!$sort) {$sort = "firmanavn";}


	$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'D' order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if (!$beskrivelse){
			$beskr=htmlentities(stripslashes($row['firmanavn']),ENT_QUOTES,$charset);
		}
		else {$beskr=$beskrivelse;}
		$kontonr=trim($row['kontonr']);
		print "<tr>";
		if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))){$tmp="<a href='../finans/kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$kontonr&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>";}
		else {$tmp="<a href='../finans/kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kontonr&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>";}
		print "<td> $tmp $row[kontonr]</a><br></td>";
		print "<td> $tmp ". stripslashes($row[firmanavn]) ."</a><br></td>";
		print "<td> $tmp $row[addr1]</a><br></td>";
		print "<td> $tmp $row[addr2]</a><br></td>";
		print "<td> $tmp $row[postnr]</a><br></td>";
		print "<td> $tmp $row[bynavn]</a><br></td>";
		print "<td> $tmp $row[kontakt]</a><br></td>";
		print "<td> $tmp $row[tlf]</a><br></td>";
		print "</tr>\n";
	}

	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function kreditoropslag($sort,$fokus,$opslag_id,$id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$afd,$projekt,$ansat,$valuta,$forfaldssato,$betailngs_id,$lobenr) {
	global $bgcolor2;
	global $top_bund;
	global $x;
	global $charset;

#	$beskrivelse=htmlentities($beskrivelse,ENT_QUOTES,$charset);
	$beskrivelse=urlencode($beskrivelse);
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
#	$faktura=htmlentities($faktura,ENT_QUOTES,$charset);
	$faktura=urlencode($faktura);
	$belob=trim($belob);

	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr' accesskey='L'>Luk</a></td>";
	print"<td width=\"80%\" $top_bund>kreditorliste</td>";
	print "<td width=\"10%\" $top_bund align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../kreditor/kreditorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\"><a href='../finans/kassekladde.php?sort=$sort&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'><u>Ny</u></a></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=kontonr&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Kundenr</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=firmanavn&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Navn</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=addr1&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Adresse</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=addr2&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Adresse2</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=postnr&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Postnr</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=bynavn&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>By</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=kontakt&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Kontaktperson</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=tlf&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Telefon</a></b></td>";
	print" </tr>\n";


	 $sort = $_GET['sort'];
	 if (!$sort) {$sort = 'firmanavn';}


	$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'K' order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		if (!$beskrivelse){
			$beskr=htmlentities(stripslashes($row['firmanavn']),ENT_QUOTES,$charset);
		}
		else {$beskr=$beskrivelse;}
		$kontonr=trim($row['kontonr']);
		print "<tr>";
		if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))){$tmp = "<a href='../finans/kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$kontonr&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>";}
		else {$tmp="<a href='../finans/kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kontonr&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>";}
		print "<td> $tmp $row[kontonr]</a><br></td>";
		print "<td> $tmp ". stripslashes($row['firmanavn']) ."</a><br></td>";
		print "<td> $tmp $row[addr1]</a><br></td>";
		print "<td> $tmp $row[addr2]</a><br></td>";
		print "<td> $tmp $row[postnr]</a><br></td>";
		print "<td> $tmp $row[bynavn]</a><br></td>";
		print "<td> $tmp $row[kontakt]</a><br></td>";
		print "<td> $tmp $row[tlf]</a><br></td>";
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function openpost($sort,$fokus,$opslag_id,$id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$afd,$projekt,$ansat,$valuta,$forfaldssato,$betailngs_id,$lobenr) {
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $top_bund;
	global $charset;

	$linjebg=NULL;

	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
#	if ($faktura) $faktura=htmlentities($faktura,ENT_QUOTES,$charset);
	if ($faktura) $faktura=urlencode($faktura);
	$belob=str_replace("-","",trim($belob));
#	if ($beskrivelse) $beskrivelse=htmlentities($beskrivelse,ENT_QUOTES,$charset);
	if ($beskrivelse) $beskrivelse=urlencode($beskrivelse);

	if (!isset($x))$x=NULL;
	if (!isset($lobenr))$lobenr=NULL;
	if ($bilag=="-") $bilag=0;

#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr' accesskey=L>Luk</a></td>";
	print"<td width=\"80%\" $top_bund>&Aring;benposter</td>";
	print"<td width=\"10%\" $top_bund align=\"right\"><br></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=konto_nr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Kundenr</a></b></td>";
#	print"<td><b> <a 'href=kassekladde.php?sort=konto_nr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Kundenr</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=firmanavn&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Navn</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=faktnr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Fakturanr</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=transdate&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Dato</a></b></td>";
	print"<td style=\"text-align:right\"><b> <a href='../finans/kassekladde.php?sort=amount&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>Bel&oslash;b</a></b></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
#	if ($sort=="transdate,bilag") $sort=NULL; # konflikter med sortering fra kassekladde.
	if (!$sort) {$sort = 'konto_nr';}

	$x=0;
	$query = db_select("select kontonr, id, firmanavn, art, gruppe from adresser order by firmanavn",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		if ($kredit==$row['kontonr'] && $k_type==$row['art']) $konto_id[0]=$row['id']; #20120809
		if ($debet==$row['kontonr'] && $d_type==$row['art']) $konto_id[0]=$row['id']; #20120809
		$konto_id[$x]=$row['id'];
		$firmanavn[$x]=stripslashes($row['firmanavn']);
		$art[$x]=$row['art'];
		$gruppe=$row['gruppe'];
		$gruppeart=$art[$x]."G";
		$r2 = db_fetch_array(db_select("SELECT box5 FROM grupper WHERE art ='$gruppeart' AND kodenr	= '$gruppe'",__FILE__ . " linje " . __LINE__));
		$modkonto[$x]=$r2['box5'];
	}
# -> 2009.05.04
	$amount=usdecimal($belob);
	$tmp1=$amount-0.005;$tmp2=$amount+0.005;$tmp3=($amount*-1)-0.005;$tmp4=($amount*-1)+0.005;
	$kriterie="where udlignet !='1'";
	if ($faktura) $kriterie=$kriterie." and faktnr ='".$faktura."'";
	
	if ((($d_type=='K')|| ($d_type=='D')) and ($debet)) {
		if ($konto_id[0]) $kriterie=$kriterie." and konto_id='".$konto_id[0]."'"; #20120809
#		else $kriterie=$kriterie." and konto_nr='".$debet."'"; #20120809
		if ($amount != 0) $kriterie=$kriterie." and amount >= '".$tmp3."' and amount < '".$tmp4."'";
	}	elseif ((($k_type=='K')|| ($k_type=='D')) and ($kredit)) {
		if ($konto_id[0]) $kriterie=$kriterie." and konto_id='".$konto_id[0]."'"; #20120809
#		else $kriterie=$kriterie." and konto_nr='".$kredit."'"; #20120809
		if ($amount != 0) $kriterie=$kriterie." and amount >= '".$tmp1."' and amount < '".$tmp2."'";
	}	elseif ($amount != 0) {
		$kriterie=$kriterie." and ((amount >= '".$tmp1."' and amount <= '".$tmp2."') or (amount >= '".$tmp3."' and amount <= '".$tmp4."'))";
	}
	if ($sort=="firmanavn") $sort="konto_nr";
#cho "select id, konto_id, konto_nr, faktnr, transdate, amount,valuta from openpost $kriterie order by $sort<br>";
		$query = db_select("select id, konto_id, konto_nr, faktnr, transdate, amount,valuta from openpost $kriterie order by $sort",__FILE__ . " linje " . __LINE__);
# <- 2009.05.04
		while ($row = db_fetch_array($query)){
			for ($y=1;$y<=$x;$y++) {
			if ($row['konto_id']==$konto_id[$y]) {
				$firmanavn[0]=$firmanavn[$y];
				$art[0]=$art[$y];
			}
		}
		if (!$beskrivelse) {
#			$beskr=htmlentities($firmanavn[0],ENT_QUOTES,$charset);
			$beskr="Udligning, nota $faktura";
		}
		else	{$beskr=$beskrivelse;}
		$konto_nr=trim($row['konto_nr']);
#		$dato=dkdato($row['transdate']);
		$valuta=$row['valuta'];

		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;}

		print "<tr bgcolor=\"$linjebg\">";


		if ($row['amount']<0) {
			$amount=$row['amount']-0.0001; #af hensyn til afrundeingfejl i php
			$belob=dkdecimal($amount*-1);
			if (!$kredit) {$kredit=$modkonto[$x];}
			$tmp="<a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$art[0]&debet=$konto_nr&k_type=$k_type&kredit=$kredit&faktura=$row[faktnr]&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>";
			$belob=dkdecimal($amount);
		}
		else 	{
			$amount=$row['amount']+0.0001; #af hensyn til afrundeingfejl i php
			$belob=dkdecimal($amount);
			if (!$debet) {$debet=$modkonto[$x];}
			$tmp="<a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$art[0]&kredit=$konto_nr&faktura=$row[faktnr]&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr'>";
		}
			print "<td> $tmp $row[konto_nr]</a><br></td>";
#		print "<td> $row[id]<br></td>";
		#print "<td> $row[konto_nr]<br></td>";
		print "<td> $tmp $firmanavn[0]</a><br></td>";
		print "<td> $tmp $row[faktnr]</a><br></td>";
		print "<td> $tmp $dato</a><br></td>";
		print "<td style=\"text-align:right\"> $tmp $belob</a><br></td>";
		print "</tr>\n";
	}

	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function finansopslag($fokus,$x) {

	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $momsfri;
	global $afd;
	global $ansat;
	global $projekt;
	global $valuta;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $top_bund;
	global $lobenr;
	global $charset;
	global $find;

	$linjebg=NULL;
	$spantekst=NULL;

	if (!isset($lobenr[$x]))$lobenr[$x]=NULL;
#	$beskrivelse[$x]=htmlentities($beskrivelse[$x],ENT_QUOTES,$charset);
	$beskrivelse[$x]=urlencode($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
#	$faktura[$x]=htmlentities($faktura[$x],ENT_QUOTES,$charset);
	$faktura[$x]=urlencode($faktura[$x]);
	$belob[$x]=trim($belob[$x]);
	if ($bilag[$x]=="-") $bilag[$x]="0"; #<- 2009.05.14

	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]dato&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L>Luk</a></td>\n";
	print"<td width=\"80%\" $top_bund>Finansopslag</td>\n";
	print"<td width=\"10%\" $top_bund align=\"right\"><br></td>\n";
	print"</tbody></table>";
	print"</td></tr>\n";
	?>
	<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=10%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Kontonr</b></td>
		<td width=35%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
		<td align="center"><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Moms</a></b></td>
		<td align="center"><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Genvej</a></b></td>
		<td align="right"><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Saldo</a></b></td>
		</tr>
		<tr><td colspan="5"><hr></td></tr>
		<?php
		$i=0;
		$query = db_select("select * from grupper",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (substr(trim($row['art']),1,1)=='M') {
				$i++;
				$moms[$x]=$row['kode'].$row['kodenr'];
			$momstekst[$x]=$row['beskrivelse'];
			}
			$momsantal=$i;
	}
	$y=0;
	if ($find) $tmp="select kontotype, kontonr, beskrivelse, moms, genvej, lukket, saldo from kontoplan where (kontotype ='H' or ((kontotype ='D' or kontotype ='S') $find)) and regnskabsaar='$regnaar' order by kontonr";
	else $tmp="select kontotype, kontonr, beskrivelse, moms, genvej, lukket, saldo from kontoplan where (kontotype ='D' or kontotype ='S'or kontotype ='H') and regnskabsaar='$regnaar' order by kontonr";
	$query = db_select("$tmp",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if ($row['lukket']!='on') {
			$y++;
			$momskode[$y]=$row['moms'];
			$kontotype[$y]=trim($row['kontotype']);
			$kontonr[$y]=trim($row['kontonr']);
			$kontobeskrivelse[$y]=trim(stripslashes($row['beskrivelse']));
			$genvej[$y]=trim($row['genvej']);
			$saldo[$y]=trim($row['saldo']);
		}
	}
	for($y=1;$y<=count($kontonr);$y++) {
		($find && ($kontotype[$y]=='H' && $kontotype[$y+1]== 'H'))?$vis=0:$vis=1; 
		if ($vis) {
			if ($momskode[$x]){
				for ($i=1;$i<=$momsantal;$i++){
					if (!isset($momstekst[$i])) $momstekst[$i]=NULL; if (!isset($moms[$i])) $moms[$i]=NULL;

					if ($moms[$i]==$momskode[$y]) $spantekst=$momstekst[$i];
				}
			}
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
			print "<tr bgcolor=$linjebg>";
#			$faktura[$x]=trim($faktura[$x]);
#			$beskrivelse[$x]=urlencode(trim($beskrivelse[$x]));

			if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty")))	{
				$href="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$kontonr[$y]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=$genvej[$y]>";
			}
			if ((strstr($fokus,"kred"))||(strstr($fokus,"k_ty"))) {
			$href="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kontonr[$y]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=$genvej[$y]>";
			}
			if ($kontotype[$y]=='H') print "<td colspan=\"5\"><b>$kontobeskrivelse[$y]</font></b></td>\n";
			else {
				print "<td><span title='Klik for at overf&oslash;re kontonr til kassekladde'>$href $kontonr[$y]</font></a><span></td>\n";
				print "<td><span title='Klik for at overf&oslash;re kontonr til kassekladde'>$href $kontobeskrivelse[$y]</font></a><span></td>\n";
				print "<td align=\"center\"><span title='$spantekst'>$momskode[$y]</font></span></td>\n";
				print "<td align=\"center\"><span title='Klik for at overf&oslash;re kontonr til kassekladde'>$href $genvej[$y]</font></a><span></td>\n";
				print "<td align=\"right\">".dkdecimal($saldo[$y])."</font></td>\n";
			}
			print "</tr>\n";
		}
	}
	exit;
}# endfunc finansopslag
######################################################################################################################################
function afd_opslag($fokus,$x)
{

	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $afd;
	global $ansat;
	global $projekt;
	global $valuta;
	global $momsfri;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $top_bund;
	global $charset;

#	$beskrivelse[$x]=htmlentities($beskrivelse[$x],ENT_QUOTES,$charset);
	$beskrivelse[$x]=urlencode($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=htmlentities($faktura[$x],ENT_QUOTES,$charset);
	$belob[$x]=trim($belob[$x]);

	 print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L>Luk</a></td>";
	print"<td width=\"80%\" $top_bund>Afd. opslag</td>";
	print"<td width=\"10%\" $top_bund align=\"right\"><br></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Afdeling nr.</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
	</tr>
	<?php
	$query = db_select("select kodenr, beskrivelse from grupper where art='AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$kodenr=trim($row['kodenr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
		print "<tr bgcolor=$linjebg>";
		print "<td><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$kodenr&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>  $kodenr</a><br></td>";
		print "<td>  $row[beskrivelse]<br></td>";
		print "</tr>\n";

	}
	exit;
}
######################################################################################################################################
function projekt_opslag($fokus,$x)
{

	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $afd;
	global $ansat;
	global $projekt;
	global $valuta;
	global $momsfri;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $top_bund;
	global $charset;

#	$beskrivelse[$x]=htmlentities($beskrivelse[$x],ENT_QUOTES,$charset);
	$beskrivelse[$x]=urlencode($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=htmlentities($faktura[$x],ENT_QUOTES,$charset);
	$belob[$x]=trim($belob[$x]);

	 print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L>Luk</a></td>";
	print"<td width=\"80%\" $top_bund>Projekt opslag</td>";
	print"<td width=\"10%\" $top_bund align=\"right\"><br></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Projekt nr.</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
	</tr>
	<?php
	$query = db_select("select kodenr, beskrivelse from grupper where art='PRJ' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$kodenr=trim($row['kodenr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
		print "<tr bgcolor=$linjebg>";
		print "<td><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$kodenr&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>  $kodenr</a><br></td>";
		print "<td>  $row[beskrivelse]<br></td>";
		print "</tr>\n";
	}
	exit;
}
######################################################################################################################################
function ansat_opslag($fokus,$x)
{

	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $afd;
	global $ansat;
	global $projekt;
	global $valuta;
	global $momsfri;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $top_bund;
	global $charset;

#	$beskrivelse[$x]=htmlentities($beskrivelse[$x],ENT_QUOTES,$charset);
	$beskrivelse[$x]=urlencode($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=htmlentities($faktura[$x],ENT_QUOTES,$charset);
	$belob[$x]=trim($belob[$x]);

	$r=db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$egen_kto_id = $r['id']*1;


	 print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L>Luk</a></td>";
	print"<td width=\"80%\" $top_bund>Projekt opslag</td>";
	print"<td width=\"10%\" $top_bund align=\"right\"><br></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr><td><br></td></tr><tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Initialer</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Navn</a></b></td>
	</tr><tr><td><br></td></tr>
	<?php
	$query = db_select("select id, navn, initialer from ansatte where konto_id='$egen_kto_id' and lukket!='on' order by posnr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
		print "<tr bgcolor=$linjebg>";
		print "<td><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&ansat=$row[initialer]&projekt=$projekt[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>  $row[initialer]</a><br></td>";
		print "<td><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&ansat=$row[initialer]&projekt=$projekt[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>  $row[navn]</a><br></td>";
		print "</tr>\n";
	}
	exit;
}
######################################################################################################################################
function valuta_opslag($fokus,$x)
{
	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $momsfri;
	global $afd;
	global $ansat;
	global $projekt;
	global $valuta;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $top_bund;
	global $charset;

#	$beskrivelse[$x]=htmlentities($beskrivelse[$x],ENT_QUOTES,$charset);
	$beskrivelse[$x]=urlencode($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=htmlentities($faktura[$x],ENT_QUOTES,$charset);
	$belob[$x]=trim($belob[$x]);

	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L>Luk</a></td>";
	print"<td width=\"80%\" $top_bund>Valuta opslag</td>";
	print"<td width=\"10%\" $top_bund align=\"right\"><br></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Valuta.</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
	</tr>
	<?php
	$query = db_select("select kodenr, box1, beskrivelse from grupper where art='VK' order by box1",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
		$tmp="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$row[box1]'>";
		print "<tr bgcolor=$linjebg>";
		print "<td>$tmp  $row[box1]</a><br></td>";
		print "<td>$tmp  $row[beskrivelse]</a><br></td>";
		print "</tr>\n";

	}
	exit;
}
######################################################################################################################################
function kontroller($id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$kontonr,$kladde_id,$afd,$projekt,$ansat,$valuta,$forfaldsdato,$betal_id,$lobenr) {
	global $fokus;
	global $debitornr;
	global $connection;
	global $regnaar;
	global $aarstart;
	global $aarslut;
	global $prebilag;
	global $fejl;
	global $db_type;
	global $submit;
	global $find;
	global $opslag_id;
	global $x;

	if ($db_type == "mysql") $ilike ="like"; # <- 2009.05.12
	else $ilike ="ilike"; # <- 2009.05.12

	$lukket=NULL;
	if ($kladde_id) {
		$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id",__FILE__ . " linje " . __LINE__));
		if ($row['bogfort']!='-') {
			print "<BODY onLoad=\"javascript:alert('Kladden er allerede bogf&oslash;rt - kladden lukkes')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			exit;
		}
	}

	if (!$aarstart) {
		$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$md1=trim($row['box1']);
			$year1=trim($row['box2']);
			$md2=trim($row['box3']);
			$year2=trim($row['box4']);
			if (strlen($md1)<2 || strlen($md2)<2) {
				if (strlen($md1)<2) $md1='0'.$md1;
				if (strlen($md2)<2) $md2='0'.$md2;
				db_modify("update grupper set box1='$md1',box3='$md2' where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
			}
			$aarstart=$year1.$md1;
			$aarslut=$year2.$md2;
	 	}
	}
#	(!$bilag) {$bilag=$prebilag;} PHR 02.10.06
#	if ($bilag=="-"){$bilag="";} PHR 02.10.06
	if (($bilag)&&($bilag!='0')&&($bilag!='-')) $bilag=$bilag*1;
	$debet=trim($debet);
	$kredit=trim($kredit);
	if (($bilag != "-")&&(($bilag)||($beskrivelse)||($kredit)||($debet)||($faktura)||($belob))) {
		if ((!$bilag)&&($bilag!='0')) {$bilag=$prebilag;}
		if (!$bilag) $bilag='0';
		if ((strstr($d_type,"d"))||(strstr($d_type,"D"))){$d_type="D";}
		elseif ((strstr($d_type,"k"))||(strstr($d_type,"K"))){$d_type="K";}
		else {$d_type="F";}

		if ((strstr($k_type,"d"))||(strstr($k_type,"D"))){$k_type="D";}
		elseif ((strstr($k_type,"k"))||(strstr($k_type,"K"))){$k_type="K";}
		else {$k_type="F";}
		if (!$debet) {$debet=0;}
		if (!$kredit) {$kredit=0;}
		if (!$lukket) {
			$lukket=array();
			$y=0;
			$query = db_select("select kontonr,lukket from kontoplan where kontotype != 'H' and kontotype != 'Z' and regnskabsaar=$regnaar",__FILE__ . " linje " . __LINE__);
			while($row = db_fetch_array($query)) {
				$y++;
				$kontonr[$y]=trim($row['kontonr']);
				if ($row['lukket']) {
					$lukket[$y]=$kontonr[$y];
				}
			}
		}

		if (($d_type=="D")||($k_type=="D")||($d_type=="K")||($k_type=="K")) {
			$z=0;
			$y=0;
			$query = db_select("select kontonr, art from adresser",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				if (strstr($row['art'],"D")) {
					$z++;
					$debitornr[$z]=trim($row['kontonr']);
				}
				if (strstr($row['art'],"K")){
					$y++;
					$kreditornr[$y]=trim($row['kontonr']);
				}
			}

		}
		if ($d_type=="F" && strlen($debet)==1 && !is_numeric($debet) && $debet!='0') {
			$debet=strtoupper($debet);
			$query = db_select("select kontonr from kontoplan where genvej='$debet' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) $debet=$row[kontonr];
			else {
				$alerttekst=$debet.' er ikke defineret som genvejstast (Bilag nr '.$bilag.')\nKladden en IKKE gemt';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if (($d_type=="F")&&(strlen($debet)>1)&&(!is_numeric($debet))) {
			$tmp=$debet."%";
			$i=0;
#cho "select kontonr from kontoplan where beskrivelse $ilike '$tmp' and regnskabsaar='$regnaar' and lukket != 'on' order by beskrivelse<br>";
			$query = db_select("select kontonr from kontoplan where beskrivelse $ilike '$tmp' and regnskabsaar='$regnaar' and lukket != 'on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				$i++;
				$tmparray[$i]=$row['kontonr'];
			}
			if ($i==1) $debet=$tmparray[$i];
			elseif ($i>1) {
				$submit="Opslag";
				$opslag_id=$x;
				$find="and beskrivelse ".$ilike." '".$tmp."'";
			} else {
				$tmp="%".$debet."%";
#cho "select kontonr from kontoplan where beskrivelse $ilike '$tmp' and regnskabsaar='$regnaar' and lukket != 'on' order by beskrivelse<br>";
				$query = db_select("select kontonr from kontoplan where beskrivelse $ilike '$tmp' and regnskabsaar='$regnaar' and lukket != 'on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) $debet=$row['kontonr'];
				else {
					$alerttekst='Der er ingen konti som indeholder teksten '.$debet.' (Bilag nr '.$bilag.')\nKladden en IKKE gemt';
					print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
					$fejl=1;
				}
			}
		}
		if (($k_type=="F")&&(strlen($kredit)>1)&&(!is_numeric($kredit))) {
			$tmp=$kredit."%";
			$query = db_select("select kontonr from kontoplan where beskrivelse $ilike '$tmp' and regnskabsaar='$regnaar' order by beskrivelse",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				$i++;
				$tmparray[$i]=$row['kontonr'];
			}
			if ($i==1) $kredit=$tmparray[$i];
			elseif ($i>1) {
				$submit="Opslag";
				$opslag_id=$x;
				$find="and beskrivelse ".$ilike." '".$tmp."'";
			} else {
				$tmp="%".$kredit."%";
				$query = db_select("select kontonr from kontoplan where beskrivelse $ilike '$tmp' and regnskabsaar='$regnaar' order by beskrivelse",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) $kredit=$row['kontonr'];
				else {
					$alerttekst='Der er ingen konti som indeholder teksten '.$kredit.' (Bilag nr '.$bilag.')\nKladden en IKKE gemt';
					print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
					$fejl=1;
				}
			}
		}
		if (!$fejl && $k_type=="F" && strlen($kredit)==1 && !is_numeric($kredit) && $kredit!='0') {
			$kredit=strtoupper($kredit);
			$query = db_select("select kontonr from kontoplan where genvej='$kredit' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) $kredit=$row['kontonr'];
			else {
				$alerttekst=$kredit.' er ikke defineret som genvejstast (Bilag nr '.$bilag.')\nKladden en IKKE gemt';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if ((!$fejl)&&($d_type=="F")&&($debet>0)) {
			$alerttekst='';

			if (!in_array($debet,$kontonr)) {
				$alerttekst='Debetkonto '.$debet.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			} elseif (in_array($debet,$lukket)) $alerttekst='Debetkonto '.$debet.' er l&aring;st og m&aring; ikke anvendes (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			if ($alerttekst) {
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if ((!$fejl)&&($k_type=="F")&&($kredit>0)) {
			$alerttekst='';
			if (!in_array($kredit,$kontonr)) {
				$alerttekst='Kreditkonto '.$kredit.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			} elseif (in_array($kredit,$lukket)) $alerttekst='Kreditkonto '.$kredit.' er l&aring;st og m&aring; ikke anvendes (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			if ($alerttekst) print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
			if ($alerttekst) {
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if ((!$fejl)&&($d_type=="D")&&($debet)&&(!in_array($debet,$debitornr))) {
			$alerttekst='';
			$svar=find_kontonr($debet,'D');
			if ($svar==$debet) $alerttekst='Debitor '.$debet.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			else $debet=$svar;
			if ($alerttekst) {
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if ((!$fejl)&&($k_type=="D")&&($kredit)&&(!in_array($kredit,$debitornr))) {
			$alerttekst='';
			$svar=find_kontonr($kredit,'D');
			if ($svar==$kredit) $alerttekst='Debitor '.$kredit.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			else $kredit=$svar;
			if ($alerttekst) {
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if ((!$fejl)&&($d_type=="K")&&($debet)&&(!in_array($debet,$kreditornr))) {
			$alerttekst='';
			$svar=find_kontonr($debet,'K');
			if ($svar==$debet) $alerttekst='Kreditor '.$debet.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			else $debet=$svar;
			if ($alerttekst) {
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if ((!$fejl)&&($k_type=="K")&&($kredit)&&(!in_array($kredit,$kreditornr))) {
			$alerttekst='';
			$svar=find_kontonr($kredit,'K');
			if ($svar==$kredit) $alerttekst='Kreditor '.$kredit.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			else $kredit=$svar;
			if ($alerttekst) {
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if (($d_type=="K")&&(strtoupper($debet)=="K")) $debet=0;
		if (($d_type=="D")&&(strtoupper($debet)=="D")) $debet=0;
		if (($k_type=="K")&&(strtoupper($kredit)=="K")) $kredit=0;
		if (($k_type=="D")&&(strtoupper($kredit)=="D")) $kredit=0;
		$transdate=usdate($dato);
		list ($year,$month,$day) = explode ('-',$transdate);
#		$year=substr($year,-2);
		$ym=$year.$month;
		if ((!$fejl)&&($ym<$aarstart)||($ym>$aarslut)) {
			$alerttekst='Dato udenfor regnskabs&aring;r (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
			$fejl=1;
#			$transdate=date("Y-m-d");
		}
		$afd=$afd*1;
		if (!$fejl&&$afd!='0') {
			if (!$row= db_fetch_array(db_select("select id from grupper where art='AFD' and kodenr='$afd'",__FILE__ . " linje " . __LINE__))){
				$alerttekst='Afdeling '.$afd.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			} # else echo "ID=$row[id]";
		}
#		$projekt=$projekt*1;
		if (!$fejl&&$projekt) {
			if (!$row= db_fetch_array(db_select("select id from grupper where art='PRJ' and kodenr='$projekt'",__FILE__ . " linje " . __LINE__))){
				$alerttekst='Projekt '.$projekt.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			} # else echo "ID=$row[id]";
		}
		if (!$valuta) $valuta='DKK';
		if (!$fejl&&$valuta!='DKK') {
			if (!$row= db_fetch_array(db_select("select kodenr from grupper where art='VK' and box1='$valuta'",__FILE__ . " linje " . __LINE__))){
				$alerttekst='valuta '.$valuta.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			} else $valutakode=$row['kodenr'];
			$valdate=usdate($dato);
			if (!$fejl && $row= db_fetch_array(db_select("select kurs from valuta where gruppe='$valutakode' and valdate <= '$valdate' order by valdate",__FILE__ . " linje " . __LINE__))) {
				db_modify("update kassekladde set valutakurs = '$row[kurs]' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
		}
		if ($lobenr) {
			db_modify("update tmpkassekl set bilag = '$bilag', transdate = '$dato', beskrivelse = '$beskrivelse', d_type = '$d_type', debet = '$debet', k_type = '$k_type', kredit = '$kredit', faktura = '$faktura', amount = '$belob', momsfri = '$momsfri', afd= '$afd', projekt= '$projekt', valuta= '$valuta',forfaldsdate='$forfaldsdato',betal_id='$betal_id' where lobenr = '$lobenr' and kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
		}
	}
	elseif (($id)&&($bilag=="-")) {
		db_modify("delete from kassekladde where id = $id",__FILE__ . " linje " . __LINE__);
	}
	$prebilag=$bilag;
}
######################################################################################################################################
function opdater($kladde_id)
{
	global $egen_kto_id;

	$forfaldsdate=NULL;
	$valutakode='0';
	$q = db_select("select * from tmpkassekl where kladde_id = $kladde_id order by lobenr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (($r['bilag']!="-")&&($r['transdate'] || $r['debet'] || $r['kredit'])) {
			if ($r['transdate']) $transdate=usdate($r['transdate']);
#			else $transdate=NULL; # <- 2009.05.12
#			else $transdate=date("Y-m-d"); # <- 2009.05.14
			if ($r['forfaldsdate']) $forfaldsdate=usdate($r['forfaldsdate']);
			else $forfaldsdate=NULL; # <- 2009.05.12
			$amount=usdecimal($r['amount']);
			$momsfri=trim($r['momsfri']);
			$debet=$r['debet']*1;
			$kredit=$r['kredit']*1;
			$d_type=trim($r['d_type']);
			$k_type=trim($r['k_type']);
			$afd=$r['afd']*1;
			$ansat=strtolower($r['ansat']);
			$faktura=db_escape_string($r['faktura']);
			if ($egen_kto_id && $ansat) {
			$r2=db_fetch_array(db_select("select id from ansatte where lower(initialer) = '$ansat' and konto_id = '$egen_kto_id'",__FILE__ . " linje " . __LINE__));
				$ansat_id=$r2['id']*1;
			} else $ansat_id=0;
			$projekt=$r['projekt'];
			$valuta=$r['valuta'];
			if ($valuta!='DKK') {
				$valuta=strtoupper($valuta);
				# 20120806 Indsat "art = 'VK' and " herunder.
				$r2=db_fetch_array(db_select("select kodenr from grupper where art = 'VK' and box1 = '$valuta'",__FILE__ . " linje " . __LINE__));
				$valutakode=$r2['kodenr']*1;
			} else $valutakode=0; #Valutakode 0 er altid DKK
			$betal_id=$r['betal_id'];
			$beskrivelse=db_escape_string($r['beskrivelse']);
			if ($amount < 0) {# Hvis beloebet er negativt, byttes om paa debet og kredit.
				$tmp=$kredit;$kredit=$debet;	$debet=$tmp;
				$tmp=$k_type;$k_type=$d_type;$d_type=$tmp;
				$amount=$amount*-1;
			}
			if ($r['id']) {
				if (!$transdate && isset($_GET['dato'])) $transdate = usdate($_GET['dato']);
				if (!$transdate) $transdate = date("Y-m-d");
				db_modify("update kassekladde set bilag = '$r[bilag]', transdate = '$transdate', beskrivelse = '$beskrivelse', d_type = '$d_type', debet = '$debet', k_type = '$k_type', kredit = '$kredit', faktura = '$faktura', amount = '$amount', momsfri = '$momsfri', afd= '$afd', projekt= '$projekt', ansat= '$ansat_id', valuta= '$valutakode' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
				if ($forfaldsdate) db_modify("update kassekladde set forfaldsdate='$forfaldsdate', betal_id='$betal_id' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			else db_modify("update kassekladde set forfaldsdate=NULL, betal_id='' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			} elseif (($r['transdate'] || $transdate)&&(($r['beskrivelse'])||($debet)||($kredit)||($r['faktura']))) {
				$beskrivelse=db_escape_string($r['beskrivelse']);
				if ($forfaldsdate) db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, momsfri, afd, projekt, ansat, valuta, kladde_id,forfaldsdate,betal_id) values ('$r[bilag]', '$transdate', '$beskrivelse', '$d_type', '$debet', '$k_type', '$kredit', '$r[faktura]', '$amount', '$momsfri', '$afd', '$projekt', '$ansat_id', '$valutakode', '$kladde_id','$forfaldsdate','$betal_id')",__FILE__ . " linje " . __LINE__);
				else db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, momsfri, afd, projekt, ansat, valuta, kladde_id) values ('$r[bilag]', '$transdate', '$beskrivelse', '$d_type', '$debet', '$k_type', '$kredit', '$r[faktura]', '$amount', '$momsfri', '$afd', '$projekt', '$ansat_id', '$valutakode', '$kladde_id')",__FILE__ . " linje " . __LINE__);
			}
		}
	}
}
######################################################################################################################################
function tilbagefor($kladde_id)
{
	global $regnaar;
	global $connection;

	$query = db_select("select kladdenote from kladdeliste where id = '$kladde_id' and bogfort='!'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query))
	{
		db_modify("delete from openpost where kladde_id = '$kladde_id'",__FILE__ . " linje " . __LINE__);
		db_modify("delete from transaktioner where kladde_id = '$kladde_id'",__FILE__ . " linje " . __LINE__);
		db_modify("update kladdeliste set bogfort = '-' where id = '$kladde_id'",__FILE__ . " linje " . __LINE__);
	}
}
######################################################################################################################################
function kopier_til_ny($kladde_id,$bilagsnr,$ny_dato)
{
	global $regnaar;
	global $connection;
	global $brugernavn;

	list ($regnstart,$regnslut) = explode(":",regnskabsaar($regnaar));

	$fejl=0;
	if ($bilagsnr && !is_numeric($bilagsnr) && $bilagsnr != '=') { #20150105-2
		$fejl="Bilagsnr skal være numerisk eller \"-\"";
		print tekstboks($fejl); 
	}
	
	if ($bilagsnr && !$fejl) {
		$gl_bilag=0;
		$bilag=0;
		$query = db_select("select kladdenote from kladdeliste where id = '$kladde_id' and bogfort='V'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)){
			transaktion('begin');
			$kladdenote=db_escape_string($row['kladdenote']); #20150105
			$tidspkt=microtime();
			$kladdedate=date("Y-m-d");
			$ny_kladde_id=1;
			$query = db_select("select id from kladdeliste where id>=$kladde_id",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				if ($ny_kladde_id<=$row['id']){$ny_kladde_id=$row['id']+1;}
			}
			db_modify("insert into kladdeliste (id, kladdenote, kladdedate, bogfort, oprettet_af) values ('$ny_kladde_id', '$kladdenote', '$kladdedate', '-', '$brugernavn')",__FILE__ . " linje " . __LINE__);
			$query = db_select("select * from kassekladde where kladde_id=$kladde_id order by bilag",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				$beskrivelse=db_escape_string($row['beskrivelse']);
				$faktura=db_escape_string($row['faktura']);
				if ($bilagsnr != "=" && $row['bilag'] && $row['bilag']!=$gl_bilag) {
					if (!$bilag) $bilag=$bilagsnr;
					else $bilag++;
					$gl_bilag = $row['bilag'];
					$next_bilag = $bilag;
				} elseif ($bilagsnr=='=') $bilag=$row['bilag']*1;
				$debet=$row['debet']*1;
				$kredit=$row['kredit']*1;
				$afd=$row['afd']*1;
				$ansat=$row['ansat']*1;
				$projekt=$row['projekt'];
				$valuta=$row['valuta']*1;
				if ($ny_dato && $ny_dato!="=") $date=usdate($ny_dato);
				else $date=$row['transdate'];
				db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, momsfri, afd, ansat, projekt, valuta, kladde_id) values ('$bilag', '$date', '$beskrivelse', '$row[d_type]', '$debet', '$row[k_type]', '$kredit', '$faktura', '$row[amount]', '$row[momsfri]', '$afd', '$ansat', '$projekt', '$valuta', '$ny_kladde_id')",__FILE__ . " linje " . __LINE__);
			}
			transaktion('commit');
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$ny_kladde_id\">";
		exit;
	} else {
		$query = db_select("select MAX(bilag) as bilag from kassekladde where transdate>='$regnstart' and transdate<='$regnslut'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$bilagsnr=$row['bilag']+1;
		$dato=date("d-m-y");
		if ($sort!='transdate,bilag') $sort='bilag,transdate';

		print "<form name=\"Form1\" action=kassekladde.php?sort=$sort; method=post>";
		print "<span style=center title=\"Bilagsnummer for 1. bilag. De &oslash;vrige beregnes automatisk. S&aelig;ttes et lighedstegn anvendes orginalt bilagsnummer\">Skriv 1. bilagsnr <input type=\"text\" style=\"text-align:left;width:40px;\" name=bilagsnr value=$bilagsnr><br><br><br></span>";
		print "<span style=center title=\"S&aelig;ttes et lighedstegn, anvendes orginal bilagsdato\">Skriv dato for alle bilag <input type=\"text\" size=8 name=ny_dato value=$dato><br><br><br></span>";
		print "<input type=hidden name=kladde_id value=$kladde_id>";
		print "<input type=submit accesskey=\"k\" value=\"Kopi&eacute;r til ny\" name=\"submit\" onclick=\"javascript:docChange = false;\">&nbsp;<input type=button value=fortryd onClick=\"location.href='../includes/luk.php'\"><br></span>\n";
		print "</form>";
		exit;
	}
exit;
}
######################################################################################################################################
function nextfokus($fokus)
{
	global $id;
	global $amount;
	if ($fokus) {
		$f_id=substr($fokus,4,(strlen($fokus)-4));
		if (strstr($fokus,"bila")) {$fokus="dato".$f_id;}
		elseif (strstr($fokus,"dato")) {$fokus="besk".$f_id;}
		elseif (strstr($fokus,"besk")) {$fokus="d_ty".$f_id;}
		elseif (strstr($fokus,"d_ty")) {$fokus="debe".$f_id;}
		elseif (strstr($fokus,"debe")) {$fokus="k_ty".$f_id;}
		elseif (strstr($fokus,"k_ty")) {$fokus="kred".$f_id;}
		elseif (strstr($fokus,"kred")) {$fokus="fakt".$f_id;}
		elseif (strstr($fokus,"fakt")) {$fokus="belo".$f_id;}
		elseif (strstr($fokus,"belo")||strstr($fokus,"afd")) {
			$f_id++;
			$fokus="bila".$f_id;
		}
	} #else $fokus="bila".$x;
# 	if ($amount[$x-1]>0) {$fokus="bila".$x;}

	return $fokus;
}
function regnskabsaar($regnaar)
{
	if ($row = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__))){
		$start=trim($row['box2'])."-".trim($row['box1'])."-01";
		$slut=usdate("31-".trim($row['box3'])."-".trim($row['box4']))	; #usdate bruges for at sikre korrekt dato.
	} else {
		$alerttekst='Regnskabs&aring;r ikke oprettet!';
		print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
		exit;
	}
	return $start.":".$slut;
}

######################################################################################################################################
function indsaet_linjer($kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$afd,$ansat,$projekt,$valuta,$forfaldsdato,$betal_id,$momsfri)
{
	global $fejl;

	$date=usdate($dato);
	$amount=usdecimal($belob);
	if ($forfaldsdato) $forfaldsdate=usdate($forfaldsdato);
	else $forfaldsdate=NULL;
	$bilag = str_replace('+',':',$bilag); #jeg ved ikke hvorfor, men den vil ikke splitte med "+"
	list ($bilag,$antal) = explode (':',$bilag);
	if ($ansat) {
		$r = db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['id']*1;
		$r = db_fetch_array(db_select("select id from ansatte where initialer = '$ansat' and konto_id = '$tmp'",__FILE__ . " linje " . __LINE__));
		$ansat_id=$r['id'];
	}
	$ansat_id=$ansat_id*1;
	if ($valuta && $valuta!='DKK') {
		$r = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art = 'VK'",__FILE__ . " linje " . __LINE__));
		if ($r['kodenr']) $valutakode=$r['kodenr']*1;
		else {
			$fejl=1;
			print "<BODY onLoad=\"javascript:alert('Valuta $valuta eksisterer ikke (Bilag $bilag)')\">";
		}
	} else $valutakode=0;
	if (!$fejl) {
		if ($antal=="=") {
			if (!$forfaldsdate) $forfaldsdate=$date;
			db_modify("insert into kassekladde (bilag,kladde_id,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,afd,ansat,projekt,valuta,forfaldsdate,betal_id,momsfri) values ('$bilag','$kladde_id','$date','$beskrivelse','$d_type','$debet','$k_type','$kredit','$faktura','$amount','$afd','$ansat_id','$projekt','$valutakode','$forfaldsdate','$betal_id','$momsfri')",__FILE__ . " linje " . __LINE__);
		} else ($antal=$antal*1);
		if ($antal>25) { #20150521
			print "<BODY onLoad=\"javascript:alert('Du forsøger at indsætte $antal bilagslinjer! Max er 25!')\">";
			$antal=0;
		}
		for ($x=1;$x<=$antal;$x++) {
			db_modify("insert into kassekladde (bilag, kladde_id, transdate) values ('$bilag', '$kladde_id', '$date')",__FILE__ . " linje " . __LINE__);
			db_modify("insert into tmpkassekl (bilag, kladde_id, transdate) values ('$bilag', '$kladde_id', '$dato')",__FILE__ . " linje " . __LINE__);
		}
	}
	if (!$fokus)$fokus="ny_kladdenote";
}
######################################################################################################################################
function ompost($ompost)
{
	global $sprog_id;

	$ompost_til=isset($_GET['ompost_til'])? $_GET['ompost_til']:Null;
	$kladde_id=isset($_GET['kladde_id'])? $_GET['kladde_id']:Null;
	$x=0;
	if (!$ompost_til) {
		$x=0;
		print "<table border=\"1\"><tbody>";
		print "<tr><td colspan=3>".findtekst(158,$sprog_id)."</td></tr>";
		print "<tr><td>Kladde_id</td><td>Beskrivelse</td><td>Oprettet&nbsp;af</td></tr>";
		print "<tr><td><a href=kassekladde.php?kladde_id=$kladde_id>".findtekst(159,$sprog_id)."</a></td><td>".findtekst(160,$sprog_id)."</td><td><br></td></tr>";
		$q = db_select("select * from kladdeliste where bogfort='-'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)){
			$x++;
			print "<tr><td><a href=kassekladde.php?kladde_id=$kladde_id&ompost=$ompost&ompost_til=$r[id]>$r[id]</a></td><td>$r[kladdenote]</td><td>$r[oprettet_af]</td></tr>";
		}
		if ($x==0) {
			print "<body onLoad=\"javascript:alert('Der skal f&oslash;rst oprettes en kassekladde som posteringen kan tilbagef&oslash;res til')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
		}
		print "<tbody></table>";
		exit;
	} else {
		$r = db_fetch_array(db_select("select * from kassekladde where id = '$ompost'",__FILE__ . " linje " . __LINE__));
		$afd=$r['afd']*1;$ansat=$r['ansat']*1;$projekt=$r['projekt'];$valutakode=$r['valutakode']*1;
		#20140718
		db_modify("insert into kassekladde (bilag,kladde_id,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,momsfri,afd,ansat,projekt,valuta) values ('$r[bilag]','$ompost_til','$r[transdate]','".db_escape_string($r['beskrivelse'])."','$r[k_type]','$r[kredit]','$r[d_type]','$r[debet]','$r[faktura]','$r[amount]','$r[momsfri]','$afd','$ansat','$projekt','$valutakode')",__FILE__ . " linje " . __LINE__);
		print "<body onLoad=\"javascript:alert('Posteringen er tilbagef&oslash;rt p&aring; kladde $ompost_til')\">";
	}
} # endfunc ompost
##########################################################################################################
function valutaopslag($amount, $valuta, $transdate)
{
	$r = db_fetch_array(db_select("select * from valuta where gruppe = '$valuta' and valdate <= '$transdate' order by valdate desc",__FILE__ . " linje " . __LINE__));
	if ($r['kurs']) {
		$kurs=$r['kurs'];
		$amount=round($amount*$kurs/100+0.0001,2); # decimal rettet fra 3 til 2 20090617 grundet fejl i saldi_58_20090617-2224
	} else {
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '$valuta'",__FILE__ . " linje " . __LINE__));
		$tmp=dkdato($transdate);
		$fejltext="---";
		print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs for $r[box1] den $tmp')\">";
	}
	$r = db_fetch_array(db_select("select box3 from grupper where art = 'VK' and kodenr = '$valuta'",__FILE__ . " linje " . __LINE__));
	$diffkonto=$r['box3'];

	return array($amount,$diffkonto,$kurs); # 3'die parameter tilfojet 2009.02.10
}
##########################################################################################################
function find_kontonr($kontonr,$art) {
	$x=0;
	$tmp=db_escape_string(strtolower($kontonr));
	$q=db_select("select kontonr from adresser where art = '$art' and lower(firmanavn) like '%$tmp%' and lukket != 'on'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$nr=$r['kontonr'];
	}
	if ($x==1) return($nr);
	else return($kontonr);
}

function sidste_5($kontonr,$art,$dk) {
	global $kladde_id;
	global $charset;


	if ($dk=="D") $txt = "select bilag,transdate,beskrivelse,debet as kontonr from kassekladde where k_type = '$art' and kredit = '$kontonr' and kladde_id != '$kladde_id' order by transdate desc";
	else $txt = "select bilag,transdate,beskrivelse,kredit as kontonr from kassekladde where d_type = '$art' and debet = '$kontonr' and kladde_id != '$kladde_id' order by transdate desc";
	$retur="<table border=1><tbody>";
	if ($art == 'K') $retur.="<tr><td colspan=4>Sidste 5 posteringer for kreditor: $kontonr</td></tr>";
	else $retur.="<tr><td colspan=4>Sidste 5 posteringer for debitor: $kontonr</td></tr>";
	$retur.="<tr><td>bilag</td><td>dato</td><td>tekst</td><td>kontonr</td></tr>";
	$x=0;
	if (is_numeric($kontonr)) {
		$q=db_select($txt,__FILE__ . " linje " . __LINE__);
		while ($x<5 && ($r = db_fetch_array($q))) {
			if ($r['kontonr']) {
				$x++;
				// 20130221 htmlentities på beskrivelse:
				$retur.="<tr><td align=right>".$r['bilag']."</td><td>".dkdato($r['transdate'])."</td><td>".htmlentities($r['beskrivelse'],ENT_QUOTES,"$charset")."</td><td>".$r['kontonr']."</td></tr>";
			}
		}
		$retur.="</tbody></table>";
	}
	if ($x) return($retur);
	else return(NULL);
} # endfunc sidste_5

function find_dublet($id,$transdate,$d_type,$debet,$k_type,$kredit,$amount) {
	$r=db_fetch_array(db_select("select bilag,kladde_id from kassekladde where transdate='$transdate' and d_type='$d_type' and debet='$debet' and k_type='$k_type' and kredit='$kredit' and amount = '$amount' and id!='$id' limit 1",__FILE__ . " linje " . __LINE__));
	return($r['bilag'].",".$r['kladde_id']);
}

$x--;
if (!$fokus && $x==1) $fokus="dato$x";
if (!$fokus && $x>1) {
	$x--;
	$fokus="besk$x";
}
print "</tbody></table>";
print "<script language=\"javascript\">";
print "document.kassekladde.$fokus.focus()";;
print "</script>";
?>
</body></html>
