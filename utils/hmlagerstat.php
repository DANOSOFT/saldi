<?php
@session_start();
$s_id=session_id();

ini_set('display_errors',1);

$title="lagerstatus med lokationer";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

$bogfor=if_isset($_GET['bogfor']);

$x=0;
$lagergrp=array();
$qtxt="select * from grupper where art='VG' and box8='on' order by kodenr";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$lagergrp[$x]=$r['kodenr'];
	$x++;
}

$x=0;
$qtxt="select * from varer order by id";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if (in_array($r['gruppe'],$lagergrp)) {
		$id[$x]=$r['id'];
		$varenr[$x]=$r['varenr'];
		$lukket[$x]=$r['lukket']*1;
		$beskrivelse[$x]=$r['beskrivelse'];
		$kostpris[$x]=$r['kostpris'];
		$salgspris[$x]=$r['salgspris']*1.25;
		$x++;
	}
}
echo "<center>Antal varer: $x<br>";

$x=0;
$query = db_select("select beskrivelse, kodenr from grupper where art='LG' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	$x++;
	$lagernavn[$x]=$row['beskrivelse'];
}
$lagerantal=$x;

$x=0;
$qtxt="select * from lagerstatus order by vare_id,lager";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$vare_id[$x]=$r['vare_id'];
	$lager[$x]=$r['lager'];
	$beholdning[$x]=$r['beholdning'];
	$lokation[$x]=$r['lok1'];
	$x++;
}

$csv=fopen("../temp/$db/lagerfil.csv","w");
fwrite ($csv, "\"Varenr\";\"Enhed\";\"Beskrivelse\";\"Udgået\";");
#echo "\"Varenr\";\"Enhed\";\"Beskrivelse\";";
for ($l=1;$l<=$lagerantal;$l++) {
	fwrite ($csv, "\"Beh($lagernavn[$l])\";\"Lok($lagernavn[$l])\";");
#	echo "\"Beh($lagernavn[$l])\";\"Lok($lagernavn[$l])\";";
}
fwrite ($csv, "\"Ialt\";\"Kostpris\";\"Salgspris(Incl moms)\"\n\r");
#echo "\"Ialt\";\"Kostpris\";\"Salgspris\"<br>";
for ($x=0;$x<count($id);$x++) {
	fwrite ($csv, "\"$varenr[$x]\";\"$enhed[$x]\";\"".$beskrivelse[$x]."\";\"$lukket[$x]\";");
#	echo "\"$varenr[$x]\";\"$enhed[$x]\";\"$beskrivelse[$x]\";";
	$ialt[$x]=0;
	for ($l=1;$l<=$lagerantal;$l++) {
		$fundet=0;
		$l_navn[$x]=$lagernavn[$l];
		for ($y=0;$y<count($vare_id);$y++) {
			if ($vare_id[$y]==$id[$x] && $l==$lager[$y]) {
				fwrite ($csv, "\"".dkdecimal($beholdning[$y])."\";\"".$lokation[$y]."\";");
#				echo "\"".dkdecimal($beholdning[$y])."\";\"$lokation[$y]\";";
				$ialt[$x]+=$beholdning[$y];
				$fundet=1;
			}
		}
		if(!$fundet) {
			fwrite ($csv, "\"0\";\"\";");
			#cho "\"0\";\"\";";
		}
	}
	fwrite ($csv, "\"".dkdecimal($ialt[$x])."\";\"".dkdecimal($kostpris[$x])."\";\"".dkdecimal($salgspris[$x])."\"\n\r");
#	echo "\"ialt ".dkdecimal($ialt[$x])."\";\"".dkdecimal($kostpris[$x])."\";\"".dkdecimal($salgspris[$x])."\"<br>";
#echo "$x ";
#if (substr($x,-1)=='0') echo "<br>";
}
fclose($csv);
echo "<br><a href='../temp/$db/lagerfil.csv'>lagerfil</a><br>";
?>
