<?php

@session_start();
$s_id=session_id();
include("../includes/connect.php");
include("../includes/std_func.php");
$query = db_select("SELECT * FROM online WHERE session_id = '$s_id' ORDER BY logtime DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
$res = db_fetch_array($query);
$db = trim($res["db"]);
$connection = db_connect($sqhost, $squser, $sqpass, $db);


function randomString($db){
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $charactersLength; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return "$randomString.bs1";
}

if(isset($_GET["data"])){
    $data = file_get_contents('php://input');
    echo $data;
}

if(isset($_GET["upload"])){
	$data = file_get_contents('php://input');
    $fileName = randomString($db);
    file_put_contents("../temp/$db/".$fileName, $data);
    echo json_encode($fileName);
}

if(isset($_GET["vis"])){
	$data = json_decode(file_get_contents('php://input'), true);
	$fileName = "../temp/$db/".$data["fileName"];
	$kladde_id = $data["kladde_id"];
	$bilag = $data["bilag"];
	$bruger_id = $data["bruger_id"];
	$id = $data["id"];
	$r=db_fetch_array(db_select("select * from grupper where ART = 'KASKL' and kode='2' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__));
	$modkonto=if_isset($r['box14']);
	vis_data($kladde_id, $fileName, $bilag, $modkonto, $id);
}

if(isset($_GET["flyt"])){
	$data = json_decode(file_get_contents('php://input'), true);
	$fileName = "../temp/$db/".$data["fileName"];
	$kladde_id = $data["kladde_id"];
	$bilag = $data["bilag"];
	$modkonto = $data["modkonto"];
	flyt_data($kladde_id, $fileName, $bilag, $modkonto);
}

function flyt_data($kladde_id, $filnavn, $bilag, $modkonto){
	global $charset;

	transaktion('begin');
	$fp=fopen("$filnavn","r");
	if ($fp) {
		$y=0;
		$feltantal=0;
#	for ($y=1; $y<20; $y++) {
		while ($linje=fgets($fp)) {
			$linje=trim(utf8_encode($linje));
			if ($linje && substr($linje,0,5)=='BS042') {
				if (substr($linje,13,4)=='0297') {
					$y++;
					$skriv_linje[$y]=1;
					$debitor[$y]=substr($linje,29,15)*1;
					$beskrivelse[$y]="Indbetaling via FI kort. Kunde $debitor[$y]";
					$date[$y]=usdate(substr($linje,103,6));
					$amount[$y]=substr($linje,115,13)/100;
					if (!$amount[$y]) $skriv_linje[$y]=0;
					list($aar,$maaned,$dag)=explode("-",$date[$y]);
					if (checkdate($maaned,$dag,$aar)) $dato[$y]=dkdato($date[$y]);
					else $skriv_linje[$y]=0;
				} elseif ($linje && substr($linje,13,4)=='0236') {
					$y++;
					$skriv_linje[$y]=1;
					$debitor[$y]=substr($linje,25,15)*1;
					$beskrivelse[$y]="Indbetaling via BS. Kunde $debitor[$y]";
					$date[$y]=usdate(substr($linje,103,6));
					$aftalenr[$y]==substr($linje,40,9);
					$amount[$y]=substr($linje,115,13)/100;
					if (!$amount[$y]) $skriv_linje[$y]=0;
					list($aar,$maaned,$dag)=explode("-",$date[$y]);
					if (checkdate($maaned,$dag,$aar)) $dato[$y]=dkdato($date[$y]);
					else $skriv_linje[$y]=0;
				}
			}
		}
	}
	$linjeantal=$y;
	fclose ($fp);
	unlink($filnavn); # sletter filen.
	$sum=0;
	$date[0]=$date[1];
	if ($linjeantal>1) {
		for ($x=1;$x<=$linjeantal;$x++) {
			if ($skriv_linje[$x]==1) {
#			$bilag++;
				$qtxt = "select faktnr from openpost where amount = '$amount[$x]' and konto_nr = '$debitor[$x]' and udlignet='0' ";
				$qtxt.= "order by transdate limit 1"; #20200102
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$faktura=$r['faktnr'];
				db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,faktura) values ('$bilag','$date[$x]','$beskrivelse[$x]','F','0','D','$debitor[$x]','$amount[$x]','$kladde_id','$faktura')",__FILE__ . " linje " . __LINE__);
				if ($date[0]!=$date[$x]) {
					db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,faktura) values ('$bilag','$date[0]','PBS Samlet betaling','F','$modkonto','F','0','$sum','$kladde_id','')",__FILE__ . " linje " . __LINE__);
					$bilag++;
					$date[0]!=$date[$x];
					$sum=0;;
				}
				$sum+=$amount[$x];
			}
		}	
		db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,faktura) values ('$bilag','$date[0]','PBS Samlet betaling','F','$modkonto','F','0','$sum','$kladde_id','')",__FILE__ . " linje " . __LINE__);
	} elseif ($skriv_linje[$linjeantal]==1) { 
		$r=db_fetch_array(db_select("select faktnr from openpost where amount = '$amount[$linjeantal]' and konto_nr = '$debitor[$linjeantal]' order by transdate desc",__FILE__ . " linje " . __LINE__));
		$faktura=$r['faktnr'];
		db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,faktura) values ('$bilag','$date[$linjeantal]','$beskrivelse[$linjeantal]','F','$modkonto','D','$debitor[$linjeantal]','$amount[$linjeantal]','$kladde_id','$faktura')",__FILE__ . " linje " . __LINE__);
	}
	transaktion('commit');
	//print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
}

function vis_data($kladde_id, $filnavn, $bilag, $modkonto, $id){
	global $bgcolor;
	global $bgcolor5;
	global $charset;

	$fp=fopen("$filnavn","r");
	if ($fp) {
		$y=0;
		$feltantal=0;
#	for ($y=1; $y<20; $y++) {
		while ($linje=fgets($fp)) {
			$linje=trim(utf8_encode($linje));
			if ($linje && substr($linje,0,5)=='BS042') {
				if (substr($linje,13,4)=='0297') {
					$y++;
					$skriv_linje[$y]=1;
					$debitor[$y]=substr($linje,29,15)*1;
					$beskrivelse[$y]="Indbetaling via FI kort. Kunde $debitor[$y]";
					$date[$y]=usdate(substr($linje,103,6));
					$amount[$y]=substr($linje,115,13)/100;
					$belob[$y]=dkdecimal($amount[$y]);
					if (!$amount[$y]) $skriv_linje[$y]=0;
					list($aar,$maaned,$dag)=explode("-",$date[$y]);
					if (checkdate($maaned,$dag,$aar)) $dato[$y]=dkdato($date[$y]);
					else $skriv_linje[$y]=0;
				} elseif ($linje && substr($linje,13,4)=='0236') {
					$y++;
					$skriv_linje[$y]=1;
					$debitor[$y]=substr($linje,25,15)*1;
					$beskrivelse[$y]="Indbetaling via BS. Kunde $debitor[$y]";
					$date[$y]=usdate(substr($linje,103,6));
					$aftalenr[$y]==substr($linje,40,9);
					$amount[$y]=substr($linje,115,13)/100;
					$belob[$y]=dkdecimal($amount[$y]);
					if (!$amount[$y]) $skriv_linje[$y]=0;
					list($aar,$maaned,$dag)=explode("-",$date[$y]);
					if (checkdate($maaned,$dag,$aar)) $dato[$y]=dkdato($date[$y]);
					else $skriv_linje[$y]=0;
				}
			}
		}
	}  
	$linjeantal=$y;
	fclose ($fp);
	$data .= "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
	#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
	$data .= "<label>Tilføj til flytning</label>";
	$data .= "<input type='checkbox' name='$id'>";
	$data .= "<tr><td colspan=\"5\"><hr></td></tr>\n";
	$data .= "<tr><td><span title='Angiv 1. bilagsnummer'><input class=\"inputbox\" style=\"text-align:right;width:60px\" type=\"text\" name=bilag value=$bilag></span></td>";
	$data .= "<td><b>Kundenr</b></td><td><b>Tekst</b></td><td><b>Dato</b></td><td><b>Bel&oslash;b</b></td></tr>";
	$linjebg=$bgcolor;
	$date[0]=$date[1];
	for ($x=1;$x<=$linjeantal;$x++) {
		($linjebg==$bgcolor)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
		if ($skriv_linje[$x]==1) {
			if ($date[0]!=$date[$x])$bilag++;
			$txtcolor="0,0,0";
		} else {
			$txtcolor="255,0,0";
	}	
		$data .= "<tr bgcolor=\"$linjebg\" style=\"color:rgb($txtcolor);\"><td align=\"right\" width=\"10px\">$bilag</td><td>$debitor[$x]</td><td>$beskrivelse[$x]</td><td>$dato[$x]</td><td>$belob[$x]</td></span></tr>";
	}
	$data .= "</tbody></table>";
	$data .= "</td></tr>";
	echo $data;
}
?>
