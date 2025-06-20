<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/showPosLines/showPosLinesFunc.php ---------- lap 3.7.7----2019.05.08-------
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
// LN 20190508 Move function vis_pos_linjer here

function vis_pos_linjer($id,$momssats,$status,$pris_ny) {
	print "\n<!-- Function vis_pos_linjer (start)-->\n";
	global $afd,$afd_lager,$afd_navn,$afslut;
	global $bgcolor,$bgcolor5,$bordnr,$brugernavn,$betvaluta,$betvalkurs,$betvalsum;
	global $del_bord,$difkto,$db_id;
	global $fokus;
	global $ifs;
	global $koekken,$kundedisplay;
	global $lagerantal,$lagernavn,$lagernr;
	global $regnaar;
	global $status,$svnr;
	global $varenr_ny,$vis_saet;
	global $varelinjer;

	$afd*=1;
	$nettosum=0;
	$rvnr=NULL;
	$samlet_rabat=0;
	$samlet_rabatpct=0;
	$saetpris=0;

	if (isset($_POST['betvaluta'])) $betvaluta=$_POST['betvaluta'];
	else $betvaluta='DKK';

	$samlet_pris=if_isset($_POST['samlet_pris']);
	if ($vis_saet && !$samlet_pris && isset($_GET['samlet_pris'])) $samlet_pris=dkdecimal($_GET['samlet_pris']); #20170622-1
	if ($varenr_ny) $samlet_pris='-'; # 20170318
	if ($samlet_pris!='-') {
		$samlet_pris=usdecimal($samlet_pris,2);
		$samlet_pris*=1;
	}

	include("ordrelinjerData.php"); #20190510


	include("ordrelinjerDataII.php"); #20190510 // Her sætter den ordre linierne

	$varelinjer=$x;
	$x=0;
	if(if_isset($_POST['del_bord'])) {
		$del_bord=$_POST['del_bord'];
		$delflyt=$_POST['delflyt'];
		$delliste=$_POST['delliste'];
		if ($del_bord=='Del bord') 	$del_bord=max($linje_id);
		else {
			for ($x=1;$x<=$varelinjer;$x++) {
				if ($del_bord==$linje_id[$x]) {
					if ($delflyt > $antal[$x]) $delflyt=$antal[$x];
					$delliste[$x]=$delflyt;
					$del_bord=$linje_id[$x+1];
					break(1);
				}
			}
		}
		if ($x == $varelinjer) {
			$delflyt=NULL;
			for ($x=1;$x<=$varelinjer;$x++) {
				if ($delliste[$x]) {

					($delflyt)?$delflyt.=",".$linje_id[$x].":".$delliste[$x]:$delflyt=$linje_id[$x].":".$delliste[$x];
				}
			}
			if ($delflyt) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../bordplaner/bordplan.php?id=$id&flyt=$bordnr&delflyt=$delflyt\">\n";
				exit;
			}
		}
	} elseif(isset($_GET['del_bord']))$del_bord=$_get['del_bord'];
	$stil=find_stil('knap',1,NULL);

	if ($fokus=='modtaget' || $fokus=='modtaget2') $class=NULL;
	else $class="class=\"vindue\"";
	print "</tbody></table></td></tr>\n";
	print "<tr><td id=\"varelin\" style=\"width:100%; height:80%\" valign=\"top\">";
	print "<div $class><table width=\"100%\" border=\"0\"><tbody>\n";

	include("productLines.php"); #20190510

	if ($vis_saet && $samlet_rabatpct && $samlet_pris)  {
		$diff=afrund($samlet_pris-$sum,3); #20170721
		if($diff || $samlet_rabatpct) {
			opret_ordrelinje($id,$rvid,$rvnr,1,'',$diff,20,100,'PO','','','0','','','','','0',$lager_ny,__LINE__);
			$r=db_fetch_array(db_select("select id from ordrelinjer where ordre_id = '$id' and varenr = 'R'",__FILE__ . " linje " . __LINE__));
			$vist_rabat=$samlet_pris-$bruttosaetsum."|".$samlet_pris;
			db_modify("update ordrelinjer set lev_varenr='$vist_rabat' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
		}
	}
	if($r=db_fetch_array(db_select("select * from ordrelinjer where ordre_id = '$id' and varenr = 'R'",__FILE__ . " linje " . __LINE__)));
	$sum+=$r['pris'];
	list($lev_vnr)=explode("|",$r['lev_varenr']);
	print "<tr><td></td><td></td><td></td><td></td><td align=right><td></tr>";
	print "<tr><td></td><td></td><td>$r[beskrivelse]</td><td></td><td align=right>".dkdecimal($lev_vnr,2)."<td></tr>";
	if (!$id) {
		global $kasse;
		global $bord;
		if ($afd_navn) {
			print "<tr><td height=\"40%\" colspan=\"4\" align=\"center\" valign=\"middle\">";
			print "<b><div style=\"font-size:25mm;color:$bgcolor5;\">$kasse</div></b><br>";
			print "<b><div style=\"font-size:15mm;color:$bgcolor5;\">$afd_navn</div></b></td></tr>\n";
		} else {
			print "<tr><td height=\"40%\" colspan=\"4\" align=\"center\" valign=\"middle\"><b>";
			print "<div style=\"font-size:25mm;color:$bgcolor5;\">Kasse $kasse</div></b></td></tr>\n";
		}
	}
	print "</tbody></table></div></td></tr>\n";
	print "<tr><td width=\"100%\"><table width=\"100%\"><tbody>\n";
	if ($status > 2) {
		$r=db_fetch_array(db_select("select sum,moms from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$sum=$r['sum']+$r['moms'];
	}
	$afrundet=pos_afrund($sum,$difkto,'');
	if ($rvnr && abs($sum*0.8-$nettosum)<0.02) $nettosum=$sum*0.8;
	$d_b=$nettosum-$kostsum;
	($nettosum)?$dg=100-$kostsum*100/$nettosum:$dg=0;
	print "<tr><td colspan=\"6\"><hr></td></tr>\n";
	$sum=afrund($sum,2);
	$dd=date("Y-m-d");


	include("sum.php"); #20190510

	$x=0;
	$a=array();
	$b=array();
	$rest=$sum;
	$q=db_select("select * from pos_betalinger where ordre_id = '$id' order by id",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$a[$x]=$r['amount'];
		$b[$x]=$r['betalingstype'];
		$c[$x]=$r['valuta'];
		$d[$x]=$r['valutakurs'];
		$rest-=$a[$x];
		$x++;
	}
	$rest=afrund($rest,2);
	if ($rest!=$sum) {
		if ($status < '3') print "<tr><td colspan=\"4\"><b>Heraf betalt</b></td></tr>\n";
		for ($x=0;$x<count($b);$x++) {
			if (is_numeric($b[$x])) {
				$b[$x]='Ny saldo';
				$a[$x]*=-1;
			}
			print "<tr><td colspan=\"4\">$b[$x]</td><td align=\"right\">";
			if ($c[$x]!='DKK') print "($c[$x] ".dkdecimal($a[$x]*100/$d[$x],2).") ";
			print dkdecimal($a[$x],2)."</td></tr>\n";
		}
		if ($status < '3') {
			print "<tr><td colspan=\"4\"><b>Rest</b></td><td align=\"right\"><b>".dkdecimal($rest,2)."</b></td></tr>\n";
			$q=db_select("select kodenr,box1 from grupper where art = 'VK' order by box1",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				$qtxt="select kurs from valuta where valdate <='$dd' and gruppe='$r[kodenr]' order by valdate desc limit 1";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				print "<tr><td>Rest $r[box1]</td><td colspan=\"4\" align=\"right\">".dkdecimal($rest*100/$r2['kurs'],2)."</td></tr>";
			}
		}
		if ($difkto) $afrundet=pos_afrund($rest,$difkto,$betvalkurs);
	}
    setRoundUpText($status, $difkto, $rest, $betvaluta, $afrundet);
	if ($afslut && $status<3 && $kundedisplay) kundedisplay('I alt',$sum,1);
	if (($nettosum || $nettosum == '0') && $status<3 && $id) { #20150505
		$moms=$sum-$nettosum;
		if ($nettosum < 999999999999.99) {
			$qtxt="update ordrer set sum= '$nettosum',moms='$moms',hvem = '$brugernavn',afd='$afd',felt_5='$kasse' where id = '$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__); #20171123
		} else alert('Tjek beløb');
	}
	$kostsum=afrund($kostsum,2);
	return($sum.chr(32).$rest.chr(32).$afrundet.chr(32).$kostsum);
	print "\n<!-- Function vis_pos_linjer (slut)-->\n";
} #endfunc vis_pos_linjer


?>
