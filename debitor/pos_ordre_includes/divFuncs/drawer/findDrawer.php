<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/divFuncs/findDrawer.php ---------- lap 3.7.7----2019.05.08-------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190510 Move function find_kasse here

function find_kasse($kasse) {
	global $id;
	global $afd;

	$id*=1;

  if ($kasse!="?" && isset($_COOKIE['saldi_pos'])) {
		$kasse=stripslashes($_COOKIE['saldi_pos']);
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
		if ($kasse>$r['box1'])$kasse='?';
		else return($kasse);
  }
  if (!$kasse || $kasse=="?") {
		print "<form name=pos_ordre action=\"pos_ordre.php?kasse=opdat&del_bord=$del_bord&id=$id\" method=\"post\" autocomplete=\"off\">\n";
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
		$kasseantal=$r['box1']*1;
		$afd=explode(chr(9),$r['box3']);

		if ($id) {
			$r=db_fetch_array(db_select("select felt_5,afd from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
			$nuv_kasse=$r['felt_5'];
			$nuv_afd=$r['afd'];
		} elseif (isset($_COOKIE['saldi_pos'])) {
			$nuv_kasse=stripslashes($_COOKIE['saldi_pos']);
		}
		if (!$nuv_kasse) $nuv_kasse=1;

		if ($kasseantal) {
			$x=0;
			$q = db_select("select * from grupper where art = 'AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$afd_nr[$x]=$r['kodenr'];
				$afd_navn[$x]=$r['beskrivelse'];
				$x++;
			}
		}
		$stil=find_stil('select',2,1);
		print "<center><table><tbody>";
		print "<tr><td>V&aelig;lg kasse</td><td><SELECT $stil NAME=\"kasse\">\n";
		for($x=0;$x<count($afd);$x++) {
			$kasse=$x+1;
			if (!count($afd_nr) && $kasse==$nuv_kasse) print "<option value=\"$kasse\">$kasse $afd_navn[$y]</option>\n";
			for($y=0;$y<count($afd_nr);$y++) {
				if ($kasse==$nuv_kasse && $afd[$x]==$afd_nr[$y]) print "<option value=\"$kasse\">$kasse $afd_navn[$y]</option>\n";
			}
		}
		for($x=0;$x<count($afd);$x++) {
			$kasse=$x+1;
			if (!count($afd_nr) && $kasse!=$nuv_kasse) print "<option value=\"$kasse\">$kasse $afd_navn[$y]</option>\n";
			for($y=0;$y<count($afd_nr);$y++) {
				if ($kasse!=$nuv_kasse && $afd[$x]==$afd_nr[$y]) print "<option value=\"$kasse\">$kasse $afd_navn[$y]</option>\n";
			}
		}
		print "</SELECT></td></tr>\n";

		$stil=find_stil('knap',1);
		print "<tr><td colspan='2'><hr></td></tr>\n";
		print "<tr><td colspan='2' align='center'><INPUT TYPE=\"submit\" style=\"width:100%\" NAME=\"submit\" VALUE=\"OK\"></td></tr>\n";
		print "</tbody></table>";
		print "</form>\n";
	}
	exit;
}

?>
