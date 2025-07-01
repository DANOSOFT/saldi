<?php
// --- finans/kladdeliste.php -------- patch 4.0.7 --- 2023.03.04 --- 
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2023 Saldi.dk ApS
// -----------------------------------------------------------------------------------
// 20150722 PHR Vis alle/egne gemmes nu som cookie. 
// 20181220 MSC - Rettet ny kladde knap til Ny
// 20190130 MSC - Rettet topmenu design til
// 20210211 PHR - Some cleanup
// 20211112 MSC - Implementing new design
// 20220627 MSC - Implementing new design
// 20220930 MSC - Changed new button text to a plus icon, if the design is topmenu
// 20230708 LOE - A minor modification
// 12/02/2025 PBLM - Added a new button to open the digital approver
// 16/05/2025 make sure the back button redirect too the previous page rather than going back to the dashboard
@session_start();
$s_id=session_id();
	
$css="../css/standard.css";		
$modulnr=2;	
$title="kladdeliste";	
$backUrl = isset($_GET['returside'])
? $_GET['returside']
: 'javascript:window.history.go(-2);';
include("../includes/connect.php");
include("../includes/std_func.php");
$query = db_select("SELECT * FROM settings WHERE var_name = 'apiKey' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
$apiKey = db_fetch_array($query)["var_value"];
include("../includes/online.php");
include("../includes/topline_settings.php");
include("../includes/row-hover-style.js.php");

if (!isset ($_COOKIE['saldi_kladdeliste'])) $_COOKIE['saldi_kladdeliste'] = NULL;

$sort=isset($_GET['sort'])? $_GET['sort']:Null;
$rf=isset($_GET['rf'])? $_GET['rf']:Null;
$vis=isset($_GET['vis'])? $_GET['vis']:Null;
print "<meta http-equiv=\"refresh\" content=\"150;URL=kladdeliste.php?sort=$sort&rf=$rf&vis=$vis\">";

if (isset($_GET['sort'])) {
	$cookievalue="$sort;$rf;$vis";
	setcookie("saldi_kladdeliste", $cookievalue, strtotime('+30 days'));
} else list ($sort,$rf,$vis) = array_pad(explode(";", $_COOKIE['saldi_kladdeliste']), 3, null);
if (!$sort) {
	$sort = "id";
	$rf = "desc";
}
if (strpos(findtekst('639|Kladdeliste', $sprog_id),'undtrykke')) {
	$qtxt = "update tekster set tekst = '' where tekst_id >= '600'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\"><a href=kassekladde.php?returside=kladdeliste.php&tjek=-1 accesskey=N title='Opret ny kassekladde'><i class='fa fa-plus-square fa-lg'></i></a></div>";     
	print "</div>";
	print "<div class='content-noside'>";
	print  "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
} elseif ($menu=='S') {
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>
		   <tr><td height = '25' align='center' valign='top'>
		   <table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";

	print "<td width='10%'  title='".findtekst('1599|Klik her for at lukke kladdelisten', $sprog_id)."'>"; #20210721
	print "<a href='$backUrl' accesskey='L'><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
	print "<td width=70% style=$topStyle align=center>".findtekst('639|Kladdeliste', $sprog_id)."</td>";
	print "<td id='tutorial-help' width=5% style=$buttonStyle>
	<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
		".findtekst('2564|Hjælp', $sprog_id)."
	</button></td>";
	$query = db_select("SELECT var_value FROM settings WHERE var_name = 'companyID'", __FILE__ . " linje " . __LINE__);
	if(db_num_rows($query) > 0){
		print "<td width='5%'><form method='post' name='digital'>";
		print "<button type='submit' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\" name='digital' value='digital'>";
		print "Digital";
		print "</button>";
		print "</form></td>";
	}
	if(isset($_POST['digital'])) {
		$query = db_select("SELECT var_value FROM settings WHERE var_name = 'companyID'", __FILE__ . " linje " . __LINE__);
		$companyID = db_fetch_array($query)["var_value"];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://easyubl.net/api/Tools/TemporaryKey/$companyID/3");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', "Authorization: ".$apiKey));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$res = curl_exec($ch);
		curl_close($ch);
		?>
		<script>
			window.open('https://approver.easyubl.eu/?tempKey=<?php echo $res; ?>', '_blank');
			// Optionally close the current window or redirect it
			// window.location.href = 'your-return-url.php'; // redirect current window
			// window.close(); // close current window
		</script>
		<?php
	}
	print "<td width='5%' title='".findtekst('1600|Klik her for at oprette en ny kassekladde', $sprog_id)."'>";
	print "<a href=kassekladde.php?returside=kladdeliste.php&tjek=-1 accesskey=N><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\" id='ny'>".findtekst('39|Ny', $sprog_id)."</button></a></td>";
	print "</tbody></table></td></tr><tr><td valign='top'><table cellpadding='1' cellspacing='1' border='0' width='100%' valign = 'top'>";
} else {
#	if ($menu=='S') {
#		print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
#		print "<tr><td style = 'width:150px;'>";
#		include ('../includes/sidemenu.php');
#		print "</td><td><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
#	}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
	<tr><td height = \"25\" align=\"center\" valign=\"top\">
	<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\"  title=\"".findtekst('1599|Klik her for at lukke kladdelisten', $sprog_id)."\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">"; #20210721
	if ($popup) print "<a href=../includes/luk.php accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
	else print "<a href=../index/menu.php accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst('639|Kladdeliste', $sprog_id)."</td>";
	if ($popup) print "<td width=\"10%\" title=\"".findtekst('1600|Klik her for at oprette en ny kassekladde', $sprog_id)."\" $top_bund onClick=\"javascript:kladde=window.open('kassekladde.php?returside=kladdeliste.php&tjek=-1','kladde','$jsvars');kladde.focus();\"><a href=kladdeliste.php?sort=$sort&rf=$rf&vis=$vis accesskey=N id='ny'>".findtekst('39|Ny', $sprog_id)."</a></td>";
	else print "<td width=\"10%\" title=\"".findtekst('1600|Klik her for at oprette en ny kassekladde', $sprog_id)."\" $top_bund><a href=kassekladde.php?returside=kladdeliste.php&tjek=-1 accesskey=N>".findtekst('39|Ny', $sprog_id)."</a></td>";
	print "</tbody></table></td></tr><tr><td valign=\"top\"><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
}
if ($vis=='alle') {
	print "<tr>";
	print "<td colspan=1 align=left></td>";
	print "<td colspan=4 align=center><a href=kladdeliste.php?sort=$sort&rf=$rf>".findtekst('641|Vis egne', $sprog_id)."</a></td>";
	print "<td colspan=1 align=right class='imgNoTextDeco'></td>";
	print "</tr>";
}
else {
	print "<tr><td colspan=6 align=center title='".findtekst('1601|Klik her for at se alle kladder', $sprog_id)."'><a href=kladdeliste.php?sort=$sort&rf=$rf&vis=alle id='visalle'>".findtekst('636|Vis alle', $sprog_id)."</a></td></tr>";}
	if ((!isset($linjebg))||($linjebg!=$bgcolor)) {$linjebg=$bgcolor; $color='#000000';
}
else {$linjebg=$bgcolor5; $color='#000000';}
print "<tr bgcolor=\"$linjebg\">";
if (($sort == 'id')&&(!$rf)) {print "<td width = 5%><b><a href=kladdeliste.php?sort=id&rf=desc&vis=$vis>Id</a></b></td>\n";}
else {print "<td width = 5% title='".findtekst('1602|Klik her for at sortere på ID', $sprog_id)."'><b><a href=kladdeliste.php?sort=id&vis=$vis>ID</a></b></td>\n";}
if (($sort == 'kladdedate')&&(!$rf)) {print "<td width = 10%><b><a href=kladdeliste.php?sort=kladdedate&rf=desc&vis=$vis>".findtekst('635|Dato', $sprog_id)."</a></b></td>\n";} //20210318
else {print "<td width = 10% title='".findtekst('1603|Klik her for at sortere på dato', $sprog_id)."'><b><a href=kladdeliste.php?sort=kladdedate&vis=$vis>".findtekst('635|Dato', $sprog_id)."</a></b></td>\n";}
if (($sort == 'oprettet_af')&&(!$rf)) {print "<td><b><a href=kladdeliste.php?sort=oprettet_af&rf=desc&vis=$vis>".findtekst('634|Ejer', $sprog_id)."</a></b></td>\n";}
else {print "<td title='".findtekst('1604|Klik her for at sortere på ejer (den der har oprettet kassekladden)', $sprog_id)."'><b><a href=kladdeliste.php?sort=oprettet_af&vis=$vis>".findtekst('634|Ejer', $sprog_id)."</a></b></td>\n";}
if (($sort == 'kladdenote')&&(!$rf)) {print "<td width = 70%><b><a href=kladdeliste.php?sort=kladdenote&rf=desc&vis=$vis>".findtekst('391|Bemærkning', $sprog_id)."</a></b></td>\n";}
else {print "<td width = 70% title='".findtekst('1605|Klik her for at sortere på bemærkning', $sprog_id)."'><b><a href=kladdeliste.php?sort=kladdenote&vis=$vis>".findtekst('391|Bemærkning', $sprog_id)."</a></b></td>\n";}
if (($sort == 'bogforingsdate')&&(!$rf)) {print "<td align=center><b><a href=kladdeliste.php?sort=bogforingsdate&rf=desc&vis=$vis>".findtekst('637|Bogført', $sprog_id)."</a></b></td>\n";}
else {print "<td align=center><b><a href=kladdeliste.php?sort=bogforingsdate&vis=$vis>".findtekst('637|Bogført', $sprog_id)."</a></b></td>\n";}
if (($sort == 'bogfort_af')&&(!$rf)) {print "<td><b><a href=kladdeliste.php?sort=bogfort_af&rf=desc&vis=$vis>Af</a></b></td>\n";}
else {print "<td title='".findtekst('1606|Klik her for at sortere på bogført af', $sprog_id)."' align='center'><b><a href=kladdeliste.php?sort=bogfort_af&vis=$vis>".findtekst('638|Af', $sprog_id)."</a></b></td>\n";}
print "</tr>\n";
$tjek=0;
#$sqhost = "localhost";
	
	if ($vis == 'alle') $vis = ''; 
	else $vis="and oprettet_af = '".$brugernavn."'";
	$tidspkt=date("U");
	$qtxt = "select * from kladdeliste where bogfort = '-' $vis order by $sort $rf";
	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$tjek++;
		$kladde="kladde".$row['id'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if (strpos(' ',$row['tidspkt'])) list ($a,$b)=explode(" ",$row['tidspkt']);
		elseif ($row['tidspkt']) $b=$row['tidspkt'];
		else $b = 0;
			if ($tidspkt - trim(intval($b)) > 3600 || $row['hvem'] == $brugernavn) {
			if ($popup) print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?tjek=$row[id]&kladde_id=$row[id]&returside=kladdeliste.php','$kladde','".$jsvars."');$kladde.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
			else print "<td><a href=kassekladde.php?tjek=$row[id]&kladde_id=$row[id]&returside=kladdeliste.php'>$row[id]</a></td>";
		}
		else {print "<td><span title= '".findtekst('1607|Kladde er låst af', $sprog_id)." $row[hvem]'>$row[id]</span></td>";}
		$kladdedato=dkdato($row['kladdedate']);
		print "<td>$kladdedato<br></td>";
		print "<td>".htmlentities(stripslashes($row['oprettet_af']),ENT_QUOTES,$charset)."<br></td>";
		print "<td>".htmlentities(stripslashes($row['kladdenote']),ENT_QUOTES,$charset)."<br></td>";
		print "<td align = center>$row[bogfort]<br></td>";
		print "<td></td></tr>";
	}
#	print "<tr><td colspan=6><hr></td></tr>";
	$query = db_select("select * from kladdeliste where bogfort = '!' $vis order by $sort $rf",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$kladde="kladde".$row[id];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn)) {
		if ($popup) print "<td  onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?kladde_id=$row[id]&returside=kladdeliste.php','$kladde','".$jsvars."');$kladde.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
		else print "<td><a href=kassekladde.php?tjek=$row[id]&kladde_id=$row[id]&returside=kladdeliste.php'>$row[id]</a></td>";
		}
		else {print "<td><span title= '".findtekst('1607|Kladde er låst af', $sprog_id)." $row[hvem]'>$row[id]</span></td>";}#		print "<tr>";
#		print "<td> $row[id]<br></td>";
		$kladdedato=dkdato($row['kladdedate']);
		print "<td>$kladdedato<br></td>";
		print "<td>".htmlentities(stripslashes($row['oprettet_af']),ENT_QUOTES,$charset)."<br></td>";
		print "<td>".htmlentities(stripslashes($row['kladdenote']),ENT_QUOTES,$charset)."<br></td>";
		print "<td align = center>$row[bogfort]<br></td>";
		print "</tr>";
	}
	$query = db_select("select * from kladdeliste where bogfort = 'S' $vis order by $sort $rf",__FILE__ . " linje " . __LINE__);
	$hr=$tjek;
	while ($row = db_fetch_array($query)){
		if ($hr==$tjek) {
			print "<tr><td colspan=\"2\" align=\"center\"><b>".findtekst('1089|Simulerede kladder', $sprog_id)."</b></td><td colspan=\"4\"><hr></td></tr>";
		}
		$tjek++;
		$kladde="kladde".$row['id'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($popup) print "<td  onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?kladde_id=$row[id]&tjek=$row[id]&returside=kladdeliste.php','$kladde','".$jsvars."');$kladde.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
		else print "<td><a href=kassekladde.php?kladde_id=$row[id]&tjek=$row[id]&returside=kladdeliste.php>$row[id]</a><br></td>";
		$kladdedato=dkdato($row['kladdedate']);
		print "<td>$kladdedato<br></td>";
		print "<td>".htmlentities(stripslashes($row['oprettet_af']),ENT_QUOTES,$charset)."<br></td>";
		print "<td>".htmlentities(stripslashes($row['kladdenote']),ENT_QUOTES,$charset)."<br></td>";
## Da der ikke blev sat bogfringsdato foer ver. 0.23 skal det saettes hak ved kladder bogfrt fr denne version...
		if ($row['bogforingsdate']){
			$bogforingsdato=dkdato($row['bogforingsdate']);
			print "<td align = center>$bogforingsdato<br></td>";
		}
		else {print "<td align = center>$row[bogfort]<br></td>";}
		print "<td>$row[bogfort_af]<br></td>";

		print "</tr>";
	}
	$query = db_select("select * from kladdeliste where bogfort = 'V' $vis order by $sort $rf",__FILE__ . " linje " . __LINE__);
	$hr=$tjek;
	while ($row = db_fetch_array($query)){
		if ($hr==$tjek) {
			print "<tr><td colspan=\"2\" align=\"center\"><b>".findtekst('1093|Bogførte kladder', $sprog_id)."</b></td><td colspan=\"4\"><hr></td></tr>";
		}
		$tjek++;
		$kladde="kladde".$row['id'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($popup) print "<td  onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?kladde_id=$row[id]&returside=kladdeliste.php','$kladde','".$jsvars."');$kladde.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
		else print "<td><a href=kassekladde.php?kladde_id=$row[id]&returside=kladdeliste.php>$row[id]</a><br></td>";
		$kladdedato=dkdato($row['kladdedate']);
		print "<td>$kladdedato<br></td>";
		print "<td>".htmlentities(stripslashes($row['oprettet_af']),ENT_QUOTES,$charset)."<br></td>";
		print "<td>".htmlentities(stripslashes($row['kladdenote']),ENT_QUOTES,$charset)."<br></td>";
## Da der ikke blev sat bogfringsdato foer ver. 0.23 skal det saettes hak ved kladder bogfrt fr denne version...
		if ($row['bogforingsdate']){
			$bogforingsdato=dkdato($row['bogforingsdate']);
			print "<td align = center>$bogforingsdato<br></td>";
		}
		else {print "<td align = center>$row[bogfort]<br></td>";}
		print "<td>$row[bogfort_af]<br></td>";

		print "</tr>";
	}
	if ($menu=='T') {
		$newbutton= "<i class='fa fa-plus-square fa-lg'></i>";
	} else {
		$newbutton= "<u>".findtekst('39|Ny', $sprog_id)."</u>";
	}
	if (!$tjek) {
		print "<tr><td colspan=5 height=25> </td></tr>"; 
		print "<tr><td colspan=3 align=right>TIP 1: </td><td>".findtekst('640|Du opretter en ny kassekladde ved at klikke på', $sprog_id)." $newbutton ".findtekst('642|øverst til højre', $sprog_id).".</td></tr>"; 
		if (db_fetch_array(db_select("select * from kladdeliste",__FILE__ . " linje " . __LINE__))) {
			print "<tr><td colspan=3 align=right>TIP 2: </td><td>".findtekst('597|Du kan se dine kollegers kladder ved at klikke på', $sprog_id)." <u>".findtekst('636|Vis alle', $sprog_id)."</u>.</td></tr>"; 
		}
	}
if ($menu=='T') {
	print "</tbody></table>";	
	include_once '../includes/topmenu/footer.php';
} else {
	print "</tbody>
	</table>
		</td></tr>
	</tbody></table>";
	include_once '../includes/oldDesign/footer.php';
}

$steps = array();
$steps[] = array(
	"selector" => "#ny",
	"content" => "Opret ny kassekladde ved at klikke her",
);
$steps[] = array(
	"selector" => "[name=digital]",
	"content" => "Digital godkendelse af fakturaer gennem nemhandel.",
);
$steps[] = array(
	"selector" => "#visalle",
	"content" => "Du kan se dine kollegers kladder ved at klikke her",
);

include(__DIR__ . "/../includes/tutorial.php");
create_tutorial("kladlist", $steps);
?>
