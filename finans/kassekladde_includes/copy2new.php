<?php
function copy2new($kladde_id,$bilagsnr,$ny_dato,$vend_fortegn) {
	global $regnaar;
	global $connection;
	global $brugernavn;
	global $sprog_id; #20210720
	global $menu;

	include_once("../includes/stdFunc/fiscalYear.php");
	list ($regnstart,$regnslut) = explode(":",fiscalYear($regnaar));

	$title ='Kopiér til ny';

	$fejl=0;
	if ($bilagsnr && !is_numeric($bilagsnr) && $bilagsnr != '=') { #20150105-2
		$fejl="Bilagsnr skal være numerisk eller \"-\"";
		print tekstboks($fejl);
	}
	if ($bilagsnr && !$fejl) {
#cho "$kladde_id,$bilagsnr,$ny_dato,$vend_fortegn<br>";
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
				if ($ny_kladde_id<=$row['id']) $ny_kladde_id=$row['id']+1;
			}
			$qtxt="insert into kladdeliste (id, kladdenote, kladdedate, bogfort, oprettet_af)";
			$qtxt.=" values ";
			$qtxt.="('$ny_kladde_id', '$kladdenote', '$kladdedate', '-', '$brugernavn')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
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
				if ($vend_fortegn) {
					$debet=$row['kredit']*1;
					$kredit=$row['debet']*1;
					$d_type=$row['k_type'];
					$k_type=$row['d_type'];
				} else {
					$debet=$row['debet']*1;
					$kredit=$row['kredit']*1;
					$d_type=$row['d_type'];
					$k_type=$row['k_type'];
				}
				$amount=$row['amount'];
				$afd=$row['afd']*1;
				$ansat=$row['ansat']*1;
				$projekt=$row['projekt'];
				$valuta=$row['valuta']*1;

				if ($ny_dato && $ny_dato!="=") $date=usdate($ny_dato);
				else $date=$row['transdate'];
				$qtxt="insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, ";
				$qtxt.="momsfri, afd, ansat, projekt, valuta, kladde_id)";
				$qtxt.=" values ";
				$qtxt.="('$bilag','$date','$beskrivelse','$d_type','$debet','$k_type','$kredit',";
				$qtxt.="'$faktura','$row[amount]','$row[momsfri]','$afd','$ansat','$projekt','$valuta','$ny_kladde_id')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			transaktion('commit');
		}
		print "<meta http-equiv='refresh' content='0;URL=kassekladde.php?kladde_id=$ny_kladde_id'>";
		exit;
	} else {
		$query = db_select("select MAX(bilag) as bilag from kassekladde where transdate>='$regnstart' and transdate<='$regnslut'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$bilagsnr=$row['bilag']+1;
		$dato=date("d-m-y");
		global $menu;

		if ($menu=='T') {
			include_once '../includes/top_header.php';
			include_once '../includes/top_menu.php';
			print "<div id='header'>";
			print "<div class='headerbtnLft headLink'>&nbsp;&nbsp;&nbsp;</div>";
			print "<div class='headerTxt'>Kopier til ny</div>";
			print "<div class='headerbtnRght headLink'>&nbsp;&nbsp;&nbsp;</div>";
			print "</div>";
			print "<div class='content-noside'>";
		} else {
			print  "<center><table border='0' cellspacing='1' width='60%' align='center'><tbody>";
		}
		print "<form name='Form1' action=kassekladde.php?sort=bilag,transdate method=post>";
		print "<tr><td style='text-align:center'><span class='textinputBefore' title='".findtekst(1552, $sprog_id)."'>".findtekst(1553, $sprog_id).": <input class='textinput' type='text' style='text-align:right;width:100px;' name=bilagsnr value=$bilagsnr><br><br><br></span></td></tr>";
		print "<tr><td style='text-align:center;'><span class='textinputBefore' style=center title='".findtekst(1554, $sprog_id)."'>".findtekst(1555, $sprog_id)."<input class='textinput' style='text-align:left;width:60px;' type='text' size='8' name='ny_dato' value='$dato'><br><br><br></span></td></tr>";
		print "<tr><td style='text-align:center;'><span class='textinputBefore' style=center title='".findtekst(1556, $sprog_id)."'>".findtekst(1557, $sprog_id)." <input class='checkmark' type='checkbox' name=vend_fortegn><br><br><br> </span></td></tr>";

		print "<input type=hidden name=kladde_id value=$kladde_id>";
		print "<tr><td align = 'center'>";
		print "<input style = 'height:50px;width:150px;' class='button green medium' type='submit' accesskey='k' 
		value='".findtekst(1598, $sprog_id)."' name='copy2new' onclick='javascript:docChange = false'>";
		print "&nbsp;";
		print "<input style = 'height:50px;width:150px;' class='button rosy medium' type='button' value='Fortryd' onClick=\"location.href='../finans/kladdeliste.php'\">
		</span></td></tr>\n";
		print "</form>";

		print "</tbody></table>";

		if ($menu=='T') {
			include_once '../includes/topmenu/footer.php';
		} else {
			include_once '../includes/oldDesign/footer.php';
		}

		exit;
	}
	exit;
}
?>
