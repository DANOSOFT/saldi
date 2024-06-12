<?php
// -----------debitor/inkasso.php---------lap 3.6.7-------2017-03-03--------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2017 DANOSOFT ApS
// ----------------------------------------------------------------------
// Ikke færdig eller frigivet - måske en anden gang 

@session_start();
$s_id=session_id();

$modulnr=5;
$title="Rykker";
$css="../css/standard.css";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

echo __line__."<br>";

$rykker_id=if_isset($_GET['rykker_id']);
$rykkerfil=if_isset($_GET['rykkerfil']);

echo __line__."<br>";
if (!$rykkerfil) {
	$qtxt="select * from ordrer where id='$rykker_id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); #20140903
	$art=$r['art'];
	$rykkernr=substr($art,1);
	$formular=$rykkernr+5;
#	if ($formular<6) $formular=6;
	print "<meta http-equiv=\"refresh\" content=\"0;URL=rykkerprint.php?rykker_id=$rykker_id&inkasso=$rykker_id&kontoantal=1\">";
}

echo "Rykkerfil $rykkerfil<br>";


?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
