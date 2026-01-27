<?php

// --- debitor/ansatte.php --- lap 4.1.1 --- 2025.09.18----------
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
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20221229 PHR Some cleanup
// 20250913 LEO Added display of existing employees and top menu and "Delete all" button 

@session_start();
$s_id=session_id();

$modulnr=6;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

 if ($_GET){
 	 $id = $_GET['id'];
 	 $returside= $_GET['returside'];
 	 $ordre_id = $_GET['ordre_id'];
 	 $fokus = $_GET['fokus'];
 	$konto_id=$_GET['konto_id'];
	$_private = if_isset($_GET,NULL,'privat');
	$_business = if_isset($_GET, NULL, 'erhverv');
 }

if ($_POST){
 	$id=$_POST['id'];
 	$submit=addslashes(trim($_POST['submit']));
	$delete= if_isset($_POST['delete'],NULL);
	$deleteAll= if_isset($_POST['deleteAll'],NULL);
 	$konto_id=$_POST['konto_id'];
 	$navn=addslashes(trim($_POST['navn']));
 	$addr1=addslashes(trim($_POST['addr1']));
 	$addr2=addslashes(trim($_POST['addr2']));
 	$postnr=addslashes(trim($_POST['postnr']));
 	$bynavn=addslashes(trim($_POST['bynavn']));
 	$tlf=addslashes(trim($_POST['tlf']));
 	$fax=addslashes(trim($_POST['fax']));
 	$mobil=addslashes(trim($_POST['mobil']));
 	$email=addslashes(trim($_POST['email']));
 	$cprnr=addslashes(trim($_POST['cprnr']));
 	$notes=addslashes(trim($_POST['notes']));
 	$ordre_id = $_GET['ordre_id'];
 	$returside=$_POST['returside'];
 	$fokus=$_POST['fokus'];
	$_private = if_isset($_POST,NULL,'privat');
	$_business = if_isset($_POST,NULL,'erhverv');
	$posnr = if_isset($_POST,NULL,'posnr');

 	if ($delete) {
 	 	if ($id) db_modify("delete from ansatte where id = '$id'",__FILE__ . " linje " . __LINE__); 
        //after deleting reassign values to posnr of each row  
       
		########

		$sql = "SELECT id FROM ansatte WHERE konto_id = '$konto_id' ORDER BY 
			CASE WHEN posnr IS NULL THEN 1 ELSE 0 END, 
			posnr ASC";

		// Execute the query (db_select should wrap pg_query)
		$result = db_select($sql, __FILE__ . " linje " . __LINE__);

		// Initialize counter
		$counter = 1;

		// Use while loop to fetch each row
		while ($row = db_fetch_array($result)) {
			$id = $row['id'];
			db_modify("UPDATE ansatte SET posnr = '$counter' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
			$counter++;
		}
		
		
					$query = db_select("
					SELECT navn 
					FROM ansatte 
					WHERE konto_id = '$konto_id' 
					AND posnr = 1
				", __FILE__ . " linje " . __LINE__);

				$row = db_fetch_array($query);
				$navnA = $row['navn'];
                 if(!$navnA) $navnA = NULL;
				//update adresser where id = konto_id and set kontakt to kontakt
			db_modify("update adresser set kontakt = '$navnA' where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		
		#########






		if($_private == 'privat'){
 			print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus&privat=privat\">";
		}elseif($_business == 'erhverv'){
			 print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus&erhverv=erhverv\">";
		}else{
			print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus\">";
		}
 	} elseif ($deleteAll) {
		
		if ($konto_id) {
			db_modify("delete from ansatte where konto_id = '$konto_id'", __FILE__ . " linje " . __LINE__);

	        //Also set the value of kontakt in adresser table to null where id = konto_id
			db_modify("update adresser set kontakt = NULL where id = '$konto_id'", __FILE__ . " linje " . __LINE__);
		
			 if($_private == 'privat'){
			     print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus&privat=privat\">";
			 }else{
			    print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus&erhverv=erhverv\">";
			 }
		}
	   

	}else {

			##########

			if(!$posnr){
				//select the last posnr value from ansatte table where konto_id = $konto_id order by id desc limit 1
				$query = db_select("
					SELECT id, posnr 
					FROM ansatte 
					WHERE konto_id = '$konto_id' 
					AND navn IS NOT NULL 
					ORDER BY id ASC
				", __FILE__ . " linje " . __LINE__);

				$i = 2;
				while ($row = db_fetch_array($query)) {
					$ansatt_id = $row['id'];

					// Update posnr with incrementing value
					db_modify("
						UPDATE ansatte 
						SET posnr = '$i' 
						WHERE id = '$ansatt_id'
					", __FILE__ . " linje " . __LINE__);

					$i++;
				}

              $posnr ='1';
			}
			#########


			if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
			if (($id==0)&&($navn)){
				$query = db_modify("insert into ansatte (navn, konto_id, addr1, addr2, postnr, bynavn, tlf, fax, mobil, email, cprnr, notes, lukket, posnr) values ('$navn', '$konto_id', '$addr1', '$addr2', '$postnr', '$bynavn', '$tlf', '$fax', '$mobil', '$email', '$cprnr', '$notes', '','$posnr')",__FILE__ . " linje " . __LINE__);
				$query = db_select("select id from ansatte where konto_id = '$konto_id' and navn='$navn' order by id desc",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$id = $row['id'];
			}
			elseif ($id > 0){
				db_modify("update ansatte set navn = '$navn', konto_id = '$konto_id', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', email = '$email', tlf = '$tlf', fax = '$fax', mobil = '$mobil', cprnr = '$cprnr', notes = '$notes', lukket = '', posnr = '$posnr' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}

			//select navn from ansatte where konto_id and posnr = 1;
			#####
			$query = db_select("
					SELECT navn 
					FROM ansatte 
					WHERE konto_id = '$konto_id' 
					AND posnr = 1
				", __FILE__ . " linje " . __LINE__);

				$row = db_fetch_array($query);
				$navnA = $row['navn'];

				//update adresser where id = konto_id and set kontakt to kontakt
			db_modify("update adresser set kontakt = '$navnA' where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
			

			####


			
			if (!empty($_SERVER['HTTP_REFERER'])) {
				header("Location: " . $_SERVER['HTTP_REFERER']);
				exit;
			}


	}
}

$query = db_select("select firmanavn from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);



########################### 

if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	## add onClick=\"JavaScript:opener.location.reload();\" but still get style from headlink MALENE
	print "<div class=\"headerbtnLft headLink\"><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst('30|Tilbage', $sprog_id) . "</a>";
	
	print "</div>";
	print "<div class=\"headerTxt\">$title</div>";
	print "<div class=\"headerbtnRght headLink\"><a href='historikkort.php?id=$id&returside=debitorkort.php' title='" . findtekst('131|Historik', $sprog_id) . "'><i class='fa fa-history fa-lg'></i></a>&nbsp;&nbsp;<a href='rapport.php?rapportart=kontokort&konto_fra=$kontonr&konto_til=$kontonr&returside=../debitor/debitorkort.php?id=$id' title='" . findtekst('133|Kontokort', $sprog_id) . "'><i class='fa fa-vcard fa-lg'></i></a>";
	if (substr($rettigheder, 5, 1) == '1') {
		print "&nbsp;&nbsp;<a href='ordreliste.php?konto_id=$id&valg=faktura&returside=../debitor/debitorkort.php?id=$id' title='" . findtekst('134|Fakturaliste', $sprog_id) . "'><i class='fa fa-dollar fa-lg'></i></a>";
	} else {
		print "";
	}

	print "</div></div>";
	print "<div class='content-noside'>";
	print  "<table border='0' cellspacing='1' class='dataTableForm' width='100%'>";
} elseif ($menu == 'S') {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n"; # TABEL 1 ->
	print "<tr><td align=\"center\" valign=\"top\">\n";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; # TABEL 1.1 ->

	print "<td width='10%'>
    <a href=\"debitorkort.php?returside=$returside&id=$konto_id&fokus=$fokus\" accesskey=L>
    <button style=\"$buttonStyle; width:100%\" onmouseover=\"this.style.cursor = 'pointer'\">". findtekst('30|Tilbage', $sprog_id) . "</button></a></td>\n";


	print "<td width='80%' style='$topStyle' align='center'>" . "$font$row[firmanavn] - Ansatte" . "</td>\n";


	print "<td width='10%'>
    <a href=\"ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$konto_id\" accesskey=N>
    <button style=\"$buttonStyle; width:100%\" onmouseover=\"this.style.cursor = 'pointer'\">"
. findtekst('39|Ny', $sprog_id) . "</button></a></td>\n";


	print "</tbody></table>"; # <- TABEL 1.1
	print "</td></tr>\n";
	print "<tr><td align = center valign = center>\n";
	print "<table cellpadding=\"0\" cellspacing=\"10\" border=\"0\"><tbody>\n"; # TABEL 1.2 -> 

} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n"; # TABEL 1 ->
	print "<tr><td align=\"center\" valign=\"top\">\n";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; # TABEL 1.1 ->
	if ($popup) print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\" $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>" . findtekst('30|Tilbage', $sprog_id) . "<!--tekst 30--></a></td>\n";
	else print "<td $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L><!--tekst 154-->" . findtekst('30|Tilbage', $sprog_id) . "<!--tekst 30--></a></td>\n";
	print "<td width=\"80%\"$top_bund>" . findtekst('356|Debitorkort', $sprog_id) . "<!--tekst 356--></td>\n";
	print "<td width=\"10%\"$top_bund><a href=\"javascript:confirmClose('debitorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=0','$tekst')\" accesskey=N><!--tekst 154-->" . findtekst('39|Ny', $sprog_id) . "<!--tekst 39--></a></td>\n";
	print "</tbody></table>"; # <- TABEL 1.1
	print "</td></tr>\n";
	print "<tr><td align = center valign = center>\n";
	print "<table cellpadding=\"0\" cellspacing=\"10\" border=\"0\"><tbody>\n"; # TABEL 1.2 ->
}

######################


if ($id > 0){
 	$query = db_select("select * from ansatte where id = '$id'",__FILE__ . " linje " . __LINE__);
 	$row = db_fetch_array($query);
 	$konto_id=$row['konto_id'];
 	$navn=htmlentities($row['navn'],ENT_COMPAT,$charset);
 	$addr1=htmlentities($row['addr1'],ENT_COMPAT,$charset);
 	$addr2=htmlentities($row['addr2'],ENT_COMPAT,$charset);
 	$postnr=htmlentities($row['postnr'],ENT_COMPAT,$charset);
 	$bynavn=htmlentities($row['bynavn'],ENT_COMPAT,$charset);
 	$email=htmlentities($row['email'],ENT_COMPAT,$charset);
 	$tlf=htmlentities($row['tlf'],ENT_COMPAT,$charset);
 	$fax=htmlentities($row['fax'],ENT_COMPAT,$charset);
 	$mobil=htmlentities($row['mobil'],ENT_COMPAT,$charset);
 	$cprnr=htmlentities($row['cprnr'],ENT_COMPAT,$charset);
 	$notes=htmlentities($row['notes'],ENT_COMPAT,$charset);
}
else{$id=0;}
print "<form name=ansatte action=ansatte.php method=post>";
print "<input type=hidden name=id value=\"$id\">";
print "<input type=hidden name=konto_id value=\"$konto_id\">";
print "<input type=hidden name=ordre_id value=\"$ordre_id\">";
print "<input type=hidden name=returside value=\"$returside\">";
print "<input type=hidden name=fokus value=\"$fokus\">";


if($_private == 'privat'){
	print "<input type=\"hidden\" name=\"privat\" value=\"$_private\">";

}elseif($_business=='erhverv'){
		print "<input type=\"hidden\" name=\"erhverv\" value=\"$_business\">"; 

}
print "<td>".findtekst('138|Navn', $sprog_id)."</td><td><br></td><td><input class=\"inputbox\" type=text size=25 name=navn value=\"$navn\"></td></tr>";
print "<tr><td>".findtekst('140|Adresse', $sprog_id)."</td><td><br></td><td><input class=\"inputbox\" type=text size=25 name=addr1 value=\"$addr1\"></td>";
print "<td><br></td>";
print "<td>".findtekst('142|Adresse 2', $sprog_id)."</td><td><br></td><td><input class=\"inputbox\" type=text size=25 name=addr2 value=\"$addr2\"></td></tr>";
print "<tr><td>".findtekst('36|Postnr.', $sprog_id)."</td><td><br></td><td><input class=\"inputbox\" type=text size=6 name=postnr value=\"$postnr\"></td>";
print "<td><br></td>";
print "<td>".findtekst('46|By', $sprog_id)."</td><td><br></td><td><input class=\"inputbox\" type=text size=25 name=bynavn value=\"$bynavn\"></td></tr>";
print "<tr><td>".findtekst('52|E-mail', $sprog_id)."</td><td><br></td><td><input class=\"inputbox\" type=text size=25 name=email value=\"$email\"></td>";
print "<td><br></td>";
#print "<td>CVR. nr.</td><td><br></td><td><input type=text size=10 name=cprnr value=\"$cprnr\"></td></tr>";
print "<td>".findtekst('401|Mobil', $sprog_id)."</td><td><br></td><td><input class=\"inputbox\" type=text size=10 name=mobil value=\"$mobil\"></td></tr>";
print "<tr><td>".findtekst('654|Lokalnr.', $sprog_id)."</td><td><br></td><td><input class=\"inputbox\" type=text size=10 name=tlf value=\"$tlf\"></td>";
print "<td><br></td>";
print "<td>".findtekst('655|Lokal fax', $sprog_id)."</td><td><br></td><td><input type=text class=\"inputbox\" size=10 name=fax value=\"$fax\"></td></tr>";
print "<td><br></td>";
print "<tr><td valign=top>".findtekst('659|Bemærkning', $sprog_id)."</td><td colspan=7><textarea class=\"inputbox\" name=\"notes\" rows=\"3\" cols=\"85\">$notes</textarea></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<td><br></td><td><br></td><td><br></td>";
print "<td align = center><input type=submit accesskey=\"g\" value='".findtekst('3|Gem', $sprog_id)."' name=\"submit\"></td>";
print "<td><br></td>";
$return_confirm = findtekst('2696|Er du sikker på, at du vil slette denne ansatte?', $sprog_id);
print "<td align = center><input  type='submit' value='".findtekst('1099|Slet', $sprog_id)."' name='delete' onclick='return confirm(\"$return_confirm\");'></td>";

if (isset($konto_id) && !empty($konto_id)) {
    $query = db_select("SELECT id, navn FROM ansatte WHERE konto_id = '$konto_id' ORDER BY posnr", __FILE__ . " linje " . __LINE__);

    echo '<tr><td colspan="7">';
    echo '<div class="employee-section">';
    echo '<h3>Existing Employees:</h3>'; 
    echo '<ul class="employee-list">';
    
if($_private){
	$value='privat';
}elseif($_business){
	$value='erhverv';
}
$value= if_isset($value,'');
    while ($row = db_fetch_array($query)) {
        $navn_list = htmlentities($row['navn'], ENT_COMPAT, $charset);
        $id = (int)$row['id']; 

        // Build URL with konto_id and id
        $url = htmlspecialchars($_SERVER['PHP_SELF']) . '?konto_id=' . urlencode($konto_id) . '&id=' . urlencode($id).'&'.$value.'='.$value	;

        echo "<li><a href=\"$url\" title=\"View employee ID $id\">$navn_list</a></li>";
    }

    echo "</ul>";
	$txt = findtekst('2698|Slet alt', $sprog_id);
	$return_confirm = findtekst('2697|Er du sikker på, at du vil slette alle ansatte tilhørende denne konto?', $sprog_id);
    echo "<div class='delete-button-wrapper'>";
    echo "<input type='submit' accesskey='A' name='deleteAll' value='$txt' onclick='return confirm(\"$return_confirm\");'></td>";
    echo "</div>";

    echo "</div>"; // .employee-section
    echo "</td></form></tr>";
}





############### Footer section ###############
print "</tbody></table></td></tr>"; 
print "<tr><td align = 'center' valign = 'bottom'>\n";

if ($menu == 'T') {
    // Do nothing
} elseif ($menu == 'S') {
    print "<table width='100%' align='center' border='0' cellspacing='1' cellpadding='0'><tbody><tr>";


    print "<td width='25%' align='center' style='$topStyle'>&nbsp;</td>";

  
    $tekst = findtekst('130|Vis historik.', $sprog_id);
    $buttonText = findtekst('131|Historik', $sprog_id);
    if ($popup) {
        print "<td width='10%' title='$tekst'>
                <button onclick=\"window.open('historikkort.php?id=$id&returside=../includes/luk.php', 'historik', '$jsvars'); return false;\"
                        style='$buttonStyle; width:100%; cursor:pointer;'>
                    $buttonText
                </button>
              </td>";
    } elseif ($returside != "historikkort.php") {
        print "<td width='10%' title='$tekst'>
                <a href='historikkort.php?id=$konto_id&returside=../debitor/ansatte.php?konto_id=$konto_id'
                   style='$buttonStyle; display:block; text-align:center; text-decoration:none; color:white; width:100%;'>
                    $buttonText
                </a>
              </td>";
    } else {
        print "<td width='10%' title='$tekst'>
                <a href='historikkort.php?id=$id'
                   style='$buttonStyle; display:block; text-align:center; text-decoration:none; color:white; width:100%;'>
                    $buttonText
                </a>
              </td>";
    }

    
    $tekst = findtekst('132|Vis Kontokort.', $sprog_id);
    $buttonText = findtekst('133|Kontokort', $sprog_id);
    print "<td width='10%' title='$tekst'>
            <a href='rapport.php?rapportart=kontokort&konto_fra=&konto_til=&returside=../debitor/ansatte.php?konto_id=$konto_id'
               style='$buttonStyle; display:block; text-align:center; text-decoration:none; color:white; width:100%;'>
                $buttonText
            </a>
          </td>";

   
    $tekst = findtekst('129|Vis fakturaliste.', $sprog_id);
    $buttonText = findtekst('134|Fakturaliste', $sprog_id);
    if (substr($rettigheder, 5, 1) == '1') {
        print "<td width='10%' title='$tekst'>
                <a href='ordreliste.php?konto_id=$konto_id&valg=faktura&returside=../debitor/ansatte.php?konto_id=$konto_id'
                   style='$buttonStyle; display:block; text-align:center; text-decoration:none; color:white; width:100%;'>
                    $buttonText
                </a>
              </td>";
    } else {
        print "<td width='10%' align='center' style='$topStyle'>
                <span style='color:#999;'>$buttonText</span>
              </td>";
    }

    $r = db_fetch_array(db_select("SELECT box7 FROM grupper WHERE art = 'DIV' AND kodenr = '2'", __FILE__ . " linje " . __LINE__));
    $jobkort = $r['box7'];
    $tekst = findtekst('312|Klik her for at åbne listen med arbejdskort.', $sprog_id);
    $buttonText = findtekst('38|Stillingsliste', $sprog_id);
    if ($jobkort) {
        print "<td width='10%' title='$tekst'>
                <a href='jobliste.php?konto_id=$konto_id&returside=../debitor/ansatte.php?konto_id=$konto_id'
                   style='$buttonStyle; display:block; text-align:center; text-decoration:none; color:white; width:100%;'>
                    $buttonText
                </a>
              </td>";
    } else {
        print "<td width='10%' align='center' style='$topStyle'>
                <span style='color:#999;'>$buttonText</span>
              </td>";
    }

    // Spacer cell
    print "<td width='25%' style='$topStyle'>&nbsp;</td>";

    print "</tr></tbody></table>";
}


############



?>

