<?php
// -------------------------------produktion/ordrer---------lap 1.9.3b------2008-04-16------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

	@session_start();
	$s_id=session_id();
	print "<meta http-equiv=\"refresh\" content=\"3600;URL=../includes/luk.php\">";
	$modulnr=14;
	
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/dkdato.php");
	include("../includes/usdate.php");
	include("../includes/dkdecimal.php");
	include("../includes/usdecimal.php");
	
	$tidspkt=date("U");
		
	if ($tjek=$_GET['tjek'])
	{
		$query = db_select("select tidspkt, hvem from ordrer where status < 3 and id = $tjek and hvem != '$brugernavn'");
		if ($row = db_fetch_array($query))
		{
			if ($tidspkt-($row['tidspkt'])<3600) 
			{
				print "<BODY onLoad=\"javascript:alert('Ordren er i brug af $row[hvem]')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			}
			else {db_modify("update ordrer set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'");}
		}
	}

	$id=$_GET['id'];
	$vis=$_GET['vis'];
	$sort=$_GET['sort'];
	$fokus=$_GET['fokus'];
	$submit=$_GET['funktion'];

	if ($id=='ny') {$id = indset_konto('');}

	if (($kontakt=$_GET['kontakt'])&&($id)) {db_modify("update ordrer set kontakt='$kontakt' where id=$id");}

	if ($_GET['varenr'])
	{
		$varenr=$_GET['varenr'];
		$linjenr=substr($fokus,4);
		if ($id)
		{
			$query = db_select("select kontonr, status from ordrer where id = $id");
			$row = db_fetch_array($query);
			if ($row[status]>2)
			{
				print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
				exit;
			}
			$query = db_select("select posnr from ordrelinjer where ordre_id = '$id' order by posnr desc");
			if ($row = db_fetch_array($query)) {$posnr=$row[posnr]+1;}
			else {$posnr=1;}
		}
		else {$posnr=1;}

		$query = db_select("select * from varer where varenr = '$varenr'");
		if ($row = db_fetch_array($query))
		{
			$vare_id=$row[id];
			$serienr=trim($row['serienr']);
			if (!$beskrivelse){$beskrivelse=$row[beskrivelse];}
			if (!$enhed){$enhed=$row[enhed];}
			if (!$pris){$pris=$row[kostpris];}
			if (!$rabat){$rabat=$row[rabat];}
		}
		if (((!$pris)||(!$lev_varenr))&&($vare_id)&&($konto_id))
		{
			$query = db_select("select * from vare_lev where vare_id = '$vare_id' and lev_id = '$konto_id'");
			if ($row = db_fetch_array($query))
			{
				$pris=$row['kostpris'];
				$lev_varenr=$row['lev_varenr'];
			}
		}
		if (!$id) {$id = indset_konto($id, $konto_id);}
		$pris=$pris*1;
		if(!$antal){$antal=1;}
		if (!$rabat){$rabat=0;}
		if ($linjenr==0)
		if ($serienr) {$antal=round($antal);}
		{
		db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, pris, lev_varenr, serienr) values ('$id', '$posnr', '$varenr', '$vare_id', '$beskrivelse', '$enhed', '$pris', '$lev_varenr', '$serienr')");}
	}


	if ($_POST)
	{
		$fokus=$_POST['fokus'];
		$submit = $_POST['submit'];
		$id = $_POST['id'];
		$ordrenr = $_POST['ordrenr'];
		$kred_ord_id = $_POST['kred_ord_id'];
		$art = $_POST['art'];
		$konto_id = trim($_POST['konto_id']);
		$kontonr = trim($_POST['kontonr']);
		$firmanavn = trim($_POST['firmanavn']);
		$addr1 = trim($_POST['addr1']);
		$addr2 = trim($_POST['addr2']);
		$postnr = trim($_POST['postnr']);
		$bynavn = trim($_POST['bynavn']);
		$land = trim($_POST['land']);
		$kontakt = trim($_POST['kontakt']);
		$lev_navn = trim($_POST['lev_navn']);
		$lev_addr1 = trim($_POST['lev_addr1']);
		$lev_addr2 = trim($_POST['lev_addr2']);
		$lev_postnr = $_POST['lev_postnr'];
		$lev_bynavn = trim($_POST['lev_bynavn']);
		$lev_kontakt = trim($_POST['lev_kontakt']);
		$ordredate = usdate($_POST['ordredato']);
		$levdate = usdate(trim($_POST['levdato']));
		$cvrnr = trim($_POST['cvrnr']);
		$betalingsbet = $_POST['betalingsbet'];
		$betalingsdage = $_POST['betalingsdage']*1;
		$lev_adr = trim($_POST['lev_adr']);
		$sum=$_POST['sum'];
		$linjeantal = $_POST['linjeantal'];
		$linje_id = $_POST['linje_id'];
		$vare_id = $_POST['vare_id'];
		$posnr = $_POST['posnr'];
		$status = $_POST['status'];
		$godkend = $_POST['godkend'];
		$kreditnota = $_POST['kreditnota'];
		$ref = trim($_POST['ref']);
		$fakturanr = trim($_POST['fakturanr']);
		$momssats = trim($_POST['momssats']);
		$lev_varenr = $_POST['lev_varenr'];
		$serienr=$_POST['serienr'];
		 if (strstr($submit,'Slet'))
		{
			db_modify("delete from ordrelinjer where ordre_id=$id");
			db_modify("delete from ordrer where id=$id");
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php\">";
		}

		for ($x=0; $x<=$linjeantal;$x++)
		{
			$y="posn".$x;
			$posnr_ny[$x]=trim($_POST[$y]);
			$y="vare".$x;
			$varenr[$x]=trim($_POST[$y]);
			$y="anta".$x;
			$antal[$x]=usdecimal($_POST[$y]);
			$y="leve".$x;
			$leveres[$x]=trim($_POST[$y]);
			$y="besk".$x;
			$beskrivelse[$x]=trim($_POST[$y]);
			$y="pris".$x;
			if (($x!=0)||($_POST[$y])||($_POST[$y]=='0')) {$pris[$x]=usdecimal($_POST[$y]);}
			$y="raba".$x;
			$rabat[$x]=usdecimal($_POST[$y]);
			if (($x>0)&&(!$rabat[$x])){$rabat=0;}
			$y="ialt".$x;
			$ialt[$x]=$_POST[$y];
			if (($godkend == "on")&&($status==0)) {$leveres[$x]=$antal[$x];}
		}

		$bogfor=1;
		if (!$sum){$sum=0;}
		if (!$status){$status=0;}


		#Kontrol mod brug af browserens "tilbage" knap og mulighed for 2 x bogfring af samme ordre
		if ($id)
		{
			$query = db_select("select status from ordrer where id = $id");
			if ($row = db_fetch_array($query))
			{
				if ($row[status]!=$status)
				{
					print "Hmmm -a $row[status] - b $status har du brugt browserens tilbageknap?";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
					exit;
				}
			}
		}
		if (strstr($submit, "Kopi"))
		{
				$id='';
				$status=0;
		}

		if ($godkend == "on")
		{
			if ($status==0) {$status=1;}
			elseif ($status==1) {$status=2;}
		}

		if (strlen($ordredate)<6){$ordredate=date("Y-m-d");}
		if (($kontonr)&&(!$firmanavn))
		{
			$query = db_select("select * from adresser where art = 'S'");
			if ($row = db_fetch_array($query))
			{
				$konto_id=$row[id];
				$firmanavn=$row['firmanavn'];
				$addr1=$row['addr1'];
				$addr2=$row['addr2'];
				$postnr=$row['postnr'];
				$bynavn=$row['bynavn'];
				$land=$row['land'];
			 $kontakt=$row['kontakt'];
				$betalingsdage=$row['betalingsdage'];
				$betalingsbet=$row['betalingsbet'];
				$cvrnr=$row['cvrnr'];
				$notes=$row['notes'];
				$gruppe=$row['gruppe'];
			}
		}
		if ((!$id)&&($firmanavn))
		{
			$query = db_select("select ordrenr from ordrer where art='PO' order by ordrenr desc");
			if ($row = db_fetch_array($query)) {$ordrenr=$row[ordrenr]+1;}
			else {$ordrenr=1;}

			db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, lev_navn,	lev_addr1,	lev_addr2,	lev_postnr,	lev_bynavn, lev_kontakt, cvrnr, art, ordredate, status, ref, sum) values 
			($ordrenr, $konto_id, '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$lev_navn',	'$lev_addr1',	'$lev_addr2',	'$lev_postnr',	'$lev_bynavn', '$lev_kontakt', '$cvrnr', 'PO', '$ordredate', $status, '$ref', '$sum')");
			$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' and art = 'PO' order by id desc");
			if ($row = db_fetch_array($query)) {$id=$row[id];}
		}
		elseif($firmanavn)
		{
			$sum=0;
			for($x=1; $x<=$linjeantal; $x++)
			{
				if (!$varenr[$x]) {$antal[$x]=0; $pris[$x]=0; $rabat[$x]=0;}
				elseif (($antal[$x]<0)&&($art!='KK'))
				{
					$query = db_select("select gruppe, beholdning from varer where varenr = '$varenr[$x]'");
					$row = db_fetch_array($query);
					if (!$row[beholdning]){$row[beholdning]=0;}
					if ($row[beholdning]-$antal[$x]<0)
					{
						print "Du kan ikke returnere $antal[$x] n&aring;r lagerbeholdningen er $row[beholdning]! (Varenr: $varenr[$x])<br>";
						$bogfor=0;
					}
				}
				if (!$vare_id[$x]){$vare_id[$x]=find_vare_id($varenr[$x]);}
				if (($posnr_ny[$x]<1)&&($status<1)) {db_modify("delete from ordrelinjer where id='$linje_id[$x]'");}
				elseif (!strstr($submit,"Kopi"))
				{
					if (!$antal[$x]){$antal[$x]=1;}
					if ($status>0)
					{
						$tidl_lev[$x]=0;
						if ($vare_id[$x])
						{
							$status=2;
							if ($antal[$x]>0) {$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]");}
							else {
							$query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]");}
							while($row = db_fetch_array($query)){$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
							if (($posnr_ny[$x]<1)&&($tidl_lev[$x]==0)) {db_modify("delete from ordrelinjer where id='$linje_id[$x]'");}
							elseif ($antal[$x]<0)
							{
								if ($antal[$x]>$tidl_lev[$x])
								{
									print "(Varenr: $varenr[$x]) \"antal\" &aelig;ndret fra $antal[$x] til $tidl_lev[$x]!";
									$antal[$x]=$tidl_lev[$x]; $submit="Gem"; $status=1;
								}
								if ($leveres[$x]<$antal[$x]+$tidl_lev[$x])
								{
									$temp=$antal[$x]+$tidl_lev[$x];
									print "(b Varenr: $varenr[$x]) \"modtag\" &aelig;ndret fra $leveres[$x] til $temp!";
									$leveres[$x]=$temp; $submit="Gem"; $status=1;
								}
								elseif ($leveres[$x] > 0)
								{
									$temp=0;
									print "(c Varenr: $varenr[$x]) \"modtag\" &aelig;ndret fra $leveres[$x] til $temp!";
									$leveres[$x]=$temp; $submit="Gem"; $status=1;
								}
							}
							else
							{
								if ($antal[$x]<$tidl_lev[$x])
								{
									print "(d Varenr: $varenr[$x]) \"modtag\" &aelig;ndret fra $leveres[$x] til $tidl_lev[$x]!";
									$antal[$x]=$tidl_lev[$x]; $submit="Gem"; $status=1;
								}
								if ($leveres[$x]>$antal[$x]-$tidl_lev[$x])
								{
									$temp=$antal[$x]-$tidl_lev[$x];
									print "(e Varenr: $varenr[$x]) \"modtag\" &aelig;ndret fra $leveres[$x] til $temp!";
									$leveres[$x]=$temp; $submit="Gem"; $status=1;
								}
								elseif ($leveres[$x]<0)
								{
									$temp=0;
									print "(f Varenr: $varenr[$x]) \"antal\" &aelig;ndret fra $antal[$x] til $tidl_lev[$x]!";
									$leveres[$x]=$temp; $submit="Gem"; $status=1;
								}
							}
						 if (($antal[$x]>=0)&&($antal[$x]!=$tidl_lev[$x])){$status=1;}
							elseif (($antal[$x]<0)&&($antal[$x]+$tidl_lev[$x]!=0)){$status=1;}
						}
					}
					if (!$leveres[$x]){$leveres[$x]=0;}
					$sum=$sum+($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
					if (($posnr_ny[$x]>0)&&(($status<2)||(($antal[$x]>0)&&($status==2)&&($antal[$x]>=$tidl_lev[$x]))||(($antal[$x]<0)&&($status==2)&&($antal[$x]<=$tidl_lev[$x]))))
					{
						if ($serienr[$x]){$antal[$x]=round($antal[$x]);}
						db_modify("update ordrelinjer set posnr='$posnr_ny[$x]', beskrivelse='$beskrivelse[$x]', antal='$antal[$x]', leveres='$leveres[$x]', pris='$pris[$x]', rabat='$rabat[$x]' where id='$linje_id[$x]'");
					}
				}
			}
			if ($posnr_ny[0]>0)
			{
				if ($varenr[0])
				{
					$query = db_select("select * from varer where varenr = '$varenr[0]'");
					if ($row = db_fetch_array($query))
					{
						$vare_id[0]=$row[id];
						$serienr[0]=round($row['serienr']);
						if (!$beskrivelse[0]){$beskrivelse[0]=$row[beskrivelse];}
						if (!$enhed[0]){$enhed[0]=$row[enhed];}
						if (!$rabat[0]){$rabat[0]=$row[rabat];}
						if (!$antal_ny[0]){$antal_ny[0]=1;}
						if (!$rabat[0]){$rabat[0]=0;}
						if ((!$pris[0])||(!$lev_varenr[0]))
						{
							if (!$konto_id)
							{
								$query = db_select("select * from adresser where art = 'S'");
								if ($row = db_fetch_array($query)) {$konto_id=$row[id];}
							}
							$query = db_select("select * from vare_lev where vare_id = $vare_id[0] and lev_id = $konto_id");
							if ($row = db_fetch_array($query))
							{
								$pris[0]=$row['kostpris'];
								$lev_varenr[0]=$row['lev_varenr'];
							}
						}
						$pris[0]=$pris[0]*1;
						db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, serienr, lev_varenr) values ('$id', '$posnr_ny[0]', '$varenr[0]', '$vare_id[0]', '$beskrivelse[0]', '$enhed[0]', '$antal_ny[0]', '$pris[0]', '$rabat[0]', '$serienr[0]', '$lev_varenr[0]')");
					}
				if ($status==2){$status=1;}
				}
				elseif ($beskrivelse[0])
				{
					db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$id', '$posnr_ny[0]', '$beskrivelse[0]')");
					if ($status==2){$status=1;}
				}
			}
			$query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'");
			if ($row = db_fetch_array($query))
			{
				if (strlen($levdate)<6){$opdat="update ordrer set firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', kontakt='$kontakt', lev_navn='$lev_navn',	lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', notes='$notes', art='$art', ordredate='$ordredate', status=$status, ref='$ref', fakturanr='$fakturanr', lev_adr='$lev_adr', hvem = '$brugernavn', tidspkt='$tidspkt' where id=$id";}
				else {$opdat="update ordrer set firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', kontakt='$kontakt', lev_navn='$lev_navn',	lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', notes='$notes', art='$art', ordredate='$ordredate', levdate='$levdate', status=$status, ref='$ref', fakturanr='$fakturanr', lev_adr='$lev_adr', hvem = '$brugernavn', tidspkt='$tidspkt' where id=$id";}
				db_modify($opdat);
			}
			else 
			{			
				$query = db_select("select hvem from ordrer where id=$id");
				if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Ordren er overtaget af $row[hvem]')\">";}
				else {print "<BODY onLoad=\"javascript:alert('Du er blevet smidt af')\">";}
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			}	
 
		}
		if (($godkend=='on')&&($status==2))
		{
			$opret_ny=0;
			for($x=1; $x<=$linjeantal; $x++)
			{
				if ($antal[$x]!=$tidl_lev[$x]) {$opret_ny=1;}
			}
			if ($opret_ny==1)
			{
				db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, lev_navn,	lev_addr1,	lev_addr2,	lev_postnr,	lev_bynavn, lev_kontakt, betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, ref, sum, lev_adr) values ($ordrenr, $konto_id, '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$lev_navn',	'$lev_addr1',	'$lev_addr2',	'$lev_postnr',	'$lev_bynavn'', '$lev_kontakt', '$betalingsdage', '$betalingsbet', '$cvrnr', '$notes', '$art', '$ordredate', '$momssats', 1, '$ref', '$sum', '$lev_adr')");
				$query = db_select("select id from ordrer where ordrenr='$ordrenr' order by id desc");
				$row = db_fetch_array($query);
				$ny_id=$row[id];
				$ny_sum=0;
				for($x=1; $x<=$linjeantal; $x++)
				{
					if ($antal[$x]!=$tidl_lev[$x])
					{
						$diff[$x]=$antal[$x]-$tidl_lev[$x];
						$antal[$x]=$tidl_lev[$x];
						db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, serienr, lev_varenr) values ('$ny_id', '$posnr_ny[$x]', '$varenr[$x]', '$vare_id[$x]', '$beskrivelse[$x]', '$enhed[$x]', '$diff[$x]', '$pris[$x]', '$rabat[$x]', '$serienr[$x]', '$lev_varenr[$x]')");
						db_modify("update ordrelinjer set antal=$antal[$x] where id = $linje_id[$x]");
						$ny_sum=$ny_sum+$diff[$x]*($pris[$x]-$pris[$x]*$rabat[$x]/100);
					}
				}
				db_modify("update ordrer set sum=$ny_sum where id = $ny_id");
			}
		}
		if (strstr($submit,'Kopi'))
		{
			for($x=1; $x<=$linjeantal; $x++)
			{
				if (!$vare_id[$x])
				{
					$query = db_select("select id from varer where varenr = '$varenr[$x]'");
					if ($row = db_fetch_array($query)) {$vare_id[$x]=$row[id];}
				}
				db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, serienr, lev_varenr) values ('$id', '$posnr_ny[$x]', '$varenr[$x]', '$vare_id[$x]', '$beskrivelse[$x]', '$enhed[$x]', '$antal[$x]', '$pris[$x]', '$rabat[$x]', '$serienr[$x]', '$lev_varenr[$x]')");
			}
		}
		$vis=1;
	}

	if (strstr($submit,'Opslag'))
	{
		if (strstr($fokus,'vare')) {vareopslag($sort, $fokus, $id, $vis);}
		if (strstr($fokus,'kontakt')){ansatopslag($sort, $fokus, $id, $vis);}
	}
		if ((strstr($submit,'Bogf'))&&($bogfor!=0)&&($status==2))
	{
			$query = db_select("select * from ordrelinjer where ordre_id = '$id'");
			if (!$row = db_fetch_array($query)) {Print "Du kan ikke lukke uden ordrelinjer";}
			 db_modify("update ordrer set status=3 where id = $id");
	}
	if ((strstr($submit,'Modt'))&&($bogfor!=0))
	{
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'");
		if (!$row = db_fetch_array($query)) {Print "Du kan ikke modtage uden ordrelinjer";}
		else {print "<meta http-equiv=\"refresh\" content=\"0;URL=modtag.php?id=$id\">";}
	}
	ordreside($id);


######################################################################################################################################

function ordreside($id)
{

	global $art;
	global $font;
	global $bogfor;
	global $submit;

	print "<form name=ordre action=ordre.php method=post>";
	if ($id)
	{
		$query = db_select("select * from ordrer where id = '$id'");
		$row = db_fetch_array($query);
		$ordrenr=$row[ordrenr];
		if($row['ordredate']) {$ordredato=dkdato($row['ordredate']);}
		else {$ordredato=date("d-m-y");}
		if ($row['levdate']) {$levdato=dkdato($row['levdate']);}
		$momssats=$row[momssats];
		$status=$row[status];
		if (!$status){$status=0;}
		$art=$row['art'];

		$query = db_select("select id, ordrenr from ordrer where kred_ord_id = '$id'");
		while ($row2 = db_fetch_array($query))
		{
			$x++;
			if ($x>1) {$krediteret=$krediteret.", ";}
			$krediteret=$krediteret."<a href=ordre.php?id=$row2[id]>$row2[ordrenr]</a>";
		}
	}

	if ((strstr($submit,'Kred'))||($art=='KK')) {sidehoved($id, "ordreliste.php", "", "", "Produktionskreditnota $ordrenr (kreditering af ordrenr.: <a href=ordre.php?id=$row[kred_ord_id]>$row[kred_ord_id]</a>)");}
	elseif ($krediteret) {sidehoved($id, "ordreliste.php", "", "", "Produktionsordre $ordrenr (krediteret p&aring; KN-nr.: $krediteret)");}
	else {sidehoved($id, "ordreliste.php", "", "", "Produktionsordre $ordrenr");}

	if (!$status){$status=0;}
	print "<input type=hidden name=ordrenr value=$ordrenr>";
	print "<input type=hidden name=status value=$status>";
	print "<input type=hidden name=id value=$row[id]>";
	print "<input type=hidden name=art value=$art>";
	print "<input type=hidden name=momssats value=$momssats>";
	print "<input type=hidden name=konto_id value=$row[konto_id]>";
	print "<input type=hidden name=kred_ord_id value=$row[kred_ord_id]>";
	if ($status>=3)
	{
#		print "<input type=hidden name=id value=$row[id]>";
		print "<input type=hidden name=konto_id value=$row[konto_id]>";
		print "<input type=hidden name=kontonr value='$row[kontonr]'>";
		print "<input type=hidden name=firmanavn value='$row[firmanavn]'>";
		print "<input type=hidden name=addr1 value='$row[addr1]'>";
		print "<input type=hidden name=addr2 value='$row[addr2]'>";
		print "<input type=hidden name=postnr value='$row[postnr]'>";
		print "<input type=hidden name=bynavn value='$row[bynavn]'>";
		print "<input type=hidden name=land value='$row[land]'>";
		print "<input type=hidden name=kontakt value='$row[kontakt]'>";
		print "<input type=hidden name=lev_navn value='$row[lev_navn]'>";
		print "<input type=hidden name=lev_addr1 value='$row[lev_addr1]'>";
		print "<input type=hidden name=lev_addr2 value='$row[lev_addr2]'>";
		print "<input type=hidden name=lev_postnr value='$row[lev_postnr]'>";
		print "<input type=hidden name=lev_bynavn value='$row[lev_bynavn]'>";
		print "<input type=hidden name=lev_kontakt value='$row[lev_kontakt]'>";
		print "<input type=hidden name=levdato value='$row[levdato]'>";
		print "<input type=hidden name=cvrnr value='$row[cvrnr]'>";
		print "<input type=hidden name=betalingsbet value='$row[betalingsbet]'>";
		print "<input type=hidden name=betalingsdage value='$row[betalingsdage]'>";
		print "<input type=hidden name=momssats value='$momssats'>";
		print "<input type=hidden name=ref value='$row[ref]'>";
		print "<input type=hidden name=fakturanr value='$row[fakturanr]'>";
		print "<input type=hidden name=lev_adr value='$row[lev_adr]'>";

		print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\" valign = \"top\"><tbody>";
		$ordre_id=$row[id];
		print "<tr><td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
		print "<tr><td width=100>$font<small><b>Kontonr</td><td width=100>$font<small>$row[kontonr]</td></tr>";
		print "<tr><td>$font<small><b>Firmanavn</td><td>$font<small>$row[firmanavn]</td></tr>";
		print "<tr><td>$font<small><b>Adresse</td><td>$font<small>$row[addr1]</td></tr>";
		print "<tr><td>$font<small></td><td>$font<small>$row[addr2]</td></tr>";
		print "<tr><td>$font<small><b>Postnr, by</td><td>$font<small>$row[postnr] $row[bynavn]</td></tr>";
		print "<tr><td>$font<small><b>Land</td><td>$font<small>$row[land]</td></tr>";
		print "<tr><td>$font<small><b>Att.:</td><td>$font<small>$row[kontakt]</td></tr>";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
		print "<tr><td width=100>$font<small><b>Ordredato</td><td width=100>$font<small>$ordredato</td></tr>";
		print "<tr><td>$font<small><b>Lev. dato</td><td>$font<small>$levdato</td></tr>";
		print "<tr><td>$font<small><b>CVR.nr</td><td>$font<small>$row[cvrnr]</td></tr>";
		print "<tr><td>$font<small><b>Betaling</td><td>$font<small>$row[betalingsbet]&nbsp;+&nbsp;$row[betalingsdage]</td>";
		print "<tr><td>$font<small><b>Vor ref.</td><td>$font<small>$row[ref]</td></tr>";
		print "<tr><td>$font<small><b>Fakturanr</td><td>$font<small>$row[fakturanr]</td></tr>";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border = 0 width=240>";
		print "<tr><td>$font<small><b>Leveringsadresse.</td></tr>";
		$lev_adr=str_replace(chr(10),"<br>",$row['lev_adr']);
		print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small>$row[lev_navn]</td></tr>";
		print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small>$row[lev_addr1]</td></tr>";
		print "<tr><td>$font<small></td><td colspan=2>$font<small>$row[lev_addr2]</td></tr>";
		print "<tr><td>$font<small>Postnr, By</td><td>$font<small>$row[lev_postnr] $row[lev_bynavn]</td></tr>";
		print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small>$row[lev_kontakt]</td></tr>";
#		print "<tr><td>$font<small>$lev_adr</td></tr>";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=1 cellspacing=0 border=1 width=100%><tbody>";
		print "<tr><td colspan=7></td></tr><tr>";
		print "<td align=center>$font<small><b>pos</td><td align=center>$font<small><b>varenr</td><td align=center>$font<small><b>antal/enhed</td><td align=center>$font<small><b>beskrivelse</td><td align=center>$font<small><b>pris</td><td align=center>$font<small><b>%</td><td align=center>$font<small><b>ialt</td><td align=center>$font<small><b>solgt</td>";
		print "</tr>";
		$x=0;
		if (!$ordre_id){$ordre_id=0;}
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr");
		while ($row = db_fetch_array($query))
		{
			if ($row[posnr]>0)
			{
				$x++;
				$linje_id[$x]=$row['id'];
				$vare_id[$x]=$row['vare_id'];
				$posnr[$x]=$row['posnr'];
				$varenr[$x]=trim($row['varenr']);
				$lev_varenr[$x]=trim($row['lev_varenr']);
				$beskrivelse[$x]=trim($row['beskrivelse']);
				$enhed[$x]=trim($row['enhed']);
				$pris[$x]=$row['pris'];
				$rabat[$x]=$row['rabat'];
				$antal[$x]=$row['antal'];
				$serienr[$x]=trim($row['serienr']); 
				if ($vare_id[$x])
				{
					$tmp = db_fetch_array(db_select("select gruppe from varer where id = $vare_id[$x]"));
					$tmp = db_fetch_array(db_select("select box8 from grupper where kodenr='$tmp[gruppe]' and art='VG'"));
					$box8[$x]=trim($tmp[box8]);
				}
			}
		}
		$linjeantal=$x;
		print "<input type=hidden name=linjeantal value=$x>";
		$totalrest=0;
		$sum=0;
		for ($x=1; $x<=$linjeantal; $x++)
		{
			if (!$vare_id[$x])
			{
				$query = db_select("select id from varer where varenr = '$varenr[$x]'");
				if ($row = db_fetch_array($query)) {$vare_id[$x]=$row[id];}
			}
			if (($varenr[$x])&&($vare_id[$x]))
			{
				$rest[$x]=0;
				$query = db_select("select id, rest from batch_kob where linje_id = '$linje_id[$x]' and ordre_id = '$ordre_id' and vare_id = '$vare_id[$x]'");
				while ($row = db_fetch_array($query)) {$rest[$x]=$rest[$x]+$row[rest];}
				$solgt[$x]=$antal[$x]-$rest[$x];
				$totalrest=$totalrest+$rest[$x];

				$ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				$sum=$sum+$ialt;
				$ialt=dkdecimal($ialt);
				$dkpris=dkdecimal($pris[$x]);
				$dkrabat=dkdecimal($rabat[$x]);
			}
			else {$antal[$x]=''; $dkpris=''; $dkrabat=''; $ialt='';}
			print "<tr>";
			print "<input type=hidden name=posn$x value=$posnr[$x]><td align=right>$font<small>$posnr[$x]</td>";
			print "<input type=hidden name=vare$x value='$varenr[$x]'><td align=right>$font<small>$varenr[$x]</td>";
			print "<input type=hidden name=anta$x value=$antal[$x]><td align=right>$font<small>".dkdecimal($antal[$x])."&nbsp;$enhed[$x]</td>";
			print "<input type=hidden name=besk$x value='$beskrivelse[$x]'><td>$font<small>$beskrivelse[$x]</td>";
			print "<input type=hidden name=pris$x value=$dkpris><td align=right>$font<small>$dkpris</td>";
			print "<input type=hidden name=raba$x value=$dkrabat><td align=right>$font<small>$dkrabat</td>";
			print "<input type=hidden name=serienr[$x] value=$serienr[$x]>";
			print "<input type=hidden name=vare_id[$x] value=$vare_id[$x]>";
			print "<input type=hidden name=lev_varenr[$x] value='$lev_varenr[$x]'>";
			print "<td align=right>$font<small>$ialt</td>";
			if ($box8[$x]=='on') 
			{
				if ($serienr[$x]) {print "<td onClick=\"serienummer($linje_id[$x])\" align=right>$font<small><u>$solgt[$x]</u></td>";}
				else {print "<td align=right>$font<small>$solgt[$x]</td>";}
			}
			else {print "<td align=right>$font<small><br></td>";}

			print "</tr>";
		}
		$moms=$sum/100*$momssats;
		$ialt=dkdecimal($sum+$moms);
		$sum=dkdecimal($sum);
		$moms=dkdecimal($moms);
		print "<tr><td colspan=7></td></tr>";
		print "<tr><td colspan=7><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
		print "<tr>";
		print "<td align=center>$font<small>Ordresum</td><td align=center>$font<small>$sum</td>";
		print "<td align=center>$font<small>Moms</td><td align=center>$font<small>$moms</td>";
		print "<td align=center>$font<small>I alt</td><td align=right>$font<small>$ialt</td>";
		print "</tbody></table></td></tr>";
		print "<tr><td align=center colspan=8>";
		print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
		print "<td align=center><input type=submit value=\"&nbsp;Kopier&nbsp;\" name=\"submit\"></td>";

	}
	else
	{
		print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\" valign = \"top\" width = 100><tbody>";
		$ordre_id=$row[id];
		print "<tr><td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100>";
		print "<tr><td witdh=200>$font<small>Kontonr</td><td colspan=2>$font<small><input readonly=readonly size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value='$row[kontonr]'></td></tr>";
		print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small><input readonly=readonly size=25 name=firmanavn value='$row[firmanavn]'></td></tr>";
		print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small><input readonly=readonly size=25 name=addr1 value='$row[addr1]'></td></tr>";
		print "<tr><td>$font<small></td><td colspan=2>$font<small><input readonly=readonly size=25 name=addr2 value='$row[addr2]'></td></tr>";
		print "<tr><td>$font<small>Postnr, by</td><td>$font<small><input readonly=readonly size=4 name=postnr value='$row[postnr]'></td><td><input readonly=readonly size=19 name=bynavn value=$row[bynavn]></td></tr>";
		print "<tr><td>$font<small>Land</td><td colspan=2>$font<small><input readonly=readonly size=25 name=land value='$row[land]'></td></tr>";
		print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small><input type=text size=25 name=kontakt onfocus=\"document.forms[0].fokus.value=this.name;\" value='$row[kontakt]'></td></tr>";
		print "</tbody></table></td>";
		print "<td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100>";
		print "<tr><td>$font<small>Ordredato</td><td>$font<small><input type=text size=10 name=ordredato value='$ordredato'></td></tr>";
		print "<tr><td>$font<small>Lev. dato</td><td>$font<small><input type=text size=10 name=levdato value='$levdato'></td></tr>";
		print "<tr><td>$font<small>CVR.nr</td><td colspan=2>$font<small><input type=text size=23 name=cvrnr value='$row[cvrnr]'></td></tr>";
		print "<tr><td witdh=200>$font<small>Vor ref.</td><td colspan=2>$font<small><input type=text size=23 name=ref value='$row[ref]'></td></tr>";
		if ($status==0){print "<tr><td>$font<small>Godkend</td><td><input type=checkbox name=godkend></td></tr>";}
		elseif ($status==1)
		{
			$query = db_select("select * from batch_kob where ordre_id=$id");
			if(db_fetch_array($query)){print "<tr><td>$font<small>Dan lev. fakt.</td><td><input type=checkbox name=godkend></td></tr>";}
			else
			{
				$query = db_select("select * from batch_salg where ordre_id=$id");
				if(db_fetch_array($query)){print "<tr><td>$font<small>Dan lev. fakt.</td><td><input type=checkbox name=godkend></td></tr>";}
			}
		}
#		elseif ($status==1){print "<tr><td>$font<small>Modtag</td><td><input type=checkbox name=modtag></td></tr>";}
#		else{print "<tr><td witdh=200>$font<small>Fakturanr</td><td colspan=2>$font<small><input type=text size=23 name=fakturanr value='$row[fakturanr]'></td></tr>";}
		print "</tbody></table></td>";
		print "<td align=center width=33%><table cellpadding=0 cellspacing=0 width=250>";
		print "<tr><tdcolspan=2 >$font<small>Leveringsadresse.</td></tr>";
		print "<tr><td colspan=2 align=center><hr></td></tr>";
		print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small><input type=text size=25 name=lev_navn value='$row[lev_navn]'></td></tr>";
		print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small><input type=text size=25 name=lev_addr1 value='$row[lev_addr1]'></td></tr>";
		print "<tr><td>$font<small></td><td colspan=2>$font<small><input type=text size=25 name=lev_addr2 value='$row[lev_addr2]'></td></tr>";
		print "<tr><td>$font<small>Postnr, By</td><td>$font<small><input type=text size=4 name=lev_postnr value='$row[lev_postnr]'><input type=text size=19 name=lev_bynavn value='$row[lev_bynavn]'></td></tr>";
		print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small><input type=text size=25 name=lev_kontakt value='$row[lev_kontakt]'></td></tr>";
	#		print "<tr><td><textarea style=\"font-family: helvetica,arial,sans-serif;\" name=lev_adr rows=5 cols=35>$row[lev_adr]</textarea></td></tr>";
		print "</td></tr></tbody></table></td>";
		print "</td></tr><tr><td align=center colspan=3><table cellpadding=1 cellspacing=0 width=100><tbody>";
		print "<tr>";
		if ($status==1)
		{
			print "<td align=center>$font<small>pos</td><td align=center>$font<small>varenr</td><td align=center>$font<small>Lev. vnr</td><td align=center>$font<small>antal</td><td align=center>$font<small>beskrivelse</td><td align=center>$font<small>pris</td><td align=center>$font<small>%</td><td align=center>$font<small>ialt</td>";
			if ($art=='KK') {print "<td colspan=2 align=center>$font<small>returner</td>";}
			else {print "<td align=center>$font<small>modt.</td>";}
		}
		else {print "<td align=center>$font<small>pos</td><td align=center>$font<small>varenr</td><td align=center>$font<small>Lev. vnr</td><td align=center>$font<small>antal/enhed</td><td align=center>$font<small>beskrivelse</td><td align=center>$font<small>pris</td><td align=center>$font<small>%</td><td align=center>$font<small>ialt</td>";}
		print "</tr>";

		$x=0;
		if (!$ordre_id){$ordre_id=0;}
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr");
		while ($row = db_fetch_array($query))
		{
			if ($row[posnr]>0)
			{
				$x++;
				$linje_id[$x]=$row['id'];
				$posnr[$x]=$row['posnr'];
				$varenr[$x]=trim($row['varenr']);
				$lev_varenr[$x]=trim($row['lev_varenr']);
				$beskrivelse[$x]=trim($row['beskrivelse']);
				$pris[$x]=$row['pris'];
				$rabat[$x]=$row['rabat'];
				$antal[$x]=$row['antal'];
				$leveres[$x]=$row['leveres'];
				$enhed[$x]=$row['enhed'];
				$vare_id[$x]=$row['vare_id'];
				$serienr[$x]=trim($row['serienr']);
		 }
		}
		$linjeantal=$x;
		print "<input type=hidden name=linjeantal value=$linjeantal>";
		$sum=0;
#		if ($status==1){$status=2;}
		for ($x=1; $x<=$linjeantal; $x++)
		{
			if ($varenr[$x])
			{
				$ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				$sum=$sum+$ialt;
				$ialt=dkdecimal($ialt);
				$dkpris=dkdecimal($pris[$x]);
				$dkrabat=dkdecimal($rabat[$x]);
			}
			else {$antal[$x]=''; $dkpris=''; $dkrabat=''; $ialt='';}

			print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
			print "<input type=hidden name=serienr[$x] value='$serienr[$x]'>";
			print "<tr>";
			print "<td>$font<small><input type=text align=right size=3 name=posn$x value='$x'></td>";
			print "<td>$font<small><input type=text size=7 name=vare$x onfocus=\"document.forms[0].fokus.value=this.name;\" value='$varenr[$x]'></td>";
			print "<td>$font<small><input type=text size=7 name=lev_varenr$x value='$lev_varenr[$x]'></td>";
			$antal[$x]=dkdecimal($antal[$x]);
			if (substr($antal[$x],-1,1)=="0"){$antal[$x]=(substr($antal[$x],0,strlen($antal[$x])-1));}
			if (substr($antal[$x],-1,1)=="0"){$antal[$x]=(substr($antal[$x],0,strlen($antal[$x])-1));}
			if (substr($antal[$x],-1,1)==","){$antal[$x]=(substr($antal[$x],0,strlen($antal[$x])-1));}
			print "<td>$font<small><input type=text align=right size=2 name=anta$x value='$antal[$x]'&nbsp;$enhed[$x]</td>";
			print "<td>$font<small><input type=text size=60 name=besk$x value='$beskrivelse[$x]'></td>";
			print "<td>$font<small><input type=text size=10 name=pris$x value='$dkpris'></td>";
			print "<td>$font<small><input type=text align=right size=4 name=raba$x value='$dkrabat'></td>";
			print "<td align=right>$font<small>$ialt</td>";
			if ($status>=1)
			{
				if ($vare_id[$x]) #{$vare_id[$x]=0;}
#				$query = db_select("select gruppe from varer where id = $vare_id[$x]");
#				$row = db_fetch_array($query);
#				$query = db_select("select box8 from grupper where kodenr = $row[gruppe] and art = 'VG'");
#				$row = db_fetch_array($query);
#				if (trim($row[box8])=='on')
				{
					$tidl_lev[$x]=0;
					print "<td>$font<small><input type=text align=right size=2 name=leve$x value='$leveres[$x]'></td>";
					if (($antal[$x]>0)&&($art!='KK'))
					{
						$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]");
						while($row = db_fetch_array($query)){$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
						if ($tidl_lev[$x]<$antal[$x]){$status=1;}
					}
					if (($antal[$x]>0)&&($art=='KK'))
					{
						$query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]");
						while($row = db_fetch_array($query)){$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
						if ($tidl_lev[$x]<$antal[$x]){$status=1;}
					}
					if ($antal[$x]<0)
					{
						$query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]");
						while($row = db_fetch_array($query)){$tidl_lev[$x]=$tidl_lev[$x]-$row[antal];}
						if ($tidl_lev[$x]>$antal[$x]){$status=1;}
					}
					print "<td>$font<small>($tidl_lev[$x])</td>";
				}
			}
			if (($status>0)&&($serienr[$x])){print "<td onClick=\"serienummer($linje_id[$x])\"><input type=button value=\"Serienr.\" name=\"vis_snr$x\"></td>";}

			print "</tr>";
		}
		print "<tr>";
		print "<td>$font<small><input type=text align=right size=3 name=posn0 value=$x></td>";
		print "<td>$font<small><input type=text size=7 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td>$font<small><input type=text size=7 name=lev_v0></td>";
		print "<td>$font<small><input type=text align=right size=2 name=anta0></td>";
		print "<td>$font<small><input type=text size=60 name=besk0></td>";
		print "<td>$font<small><input type=text align=right size=10 name=pris0></td>";
		print "<td>$font<small><input type=text align=right size=4 name=raba0></td>";
		print "<td>$font<small></td>";
#		if ($status==1) {print "<td>$font<small><input type=text align=right size=2 name=modt0></td>";}
		print "</tr>";
		print "<input type=hidden size=3 name=sum value=$sum>";
		$moms=$sum/100*$momssats;
		$ialt=$sum+$moms;
#		$sum=dkdecimal($sum);
#		$moms=dkdecimal($moms);
		print "<tr><td colspan=7><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
		print "<tr>";
		print "<td align=center>$font<small>Ordresum</td><td align=center>$font<small>".dkdecimal($sum)."</td>";
		print "<td align=center>$font<small>Moms</td><td align=center>$font<small>".dkdecimal($moms)."</td>";
		print "<td align=center>$font<small>I alt</td><td align=right>$font<small>".dkdecimal($ialt)."</td>";
		if ($id) {db_modify("update ordrer set sum=$sum where id=$id");}

		print "</tbody></table></td></tr>";
		print "<input type=\"hidden\" name=\"fokus\">";
		print "<tr><td align=center colspan=8>";
		print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody><tr>";
		print "<td align=center><input type=submit accesskey=\"g\" value=\"&nbsp;&nbsp;Gem&nbsp;&nbsp;\" name=\"submit\"></td>";
		print "<td align=center><input type=submit accesskey=\"o\" value=\"Opslag\" name=\"submit\"></td>";
		if (($status==1)&&($bogfor==1))
		{
#			if (($art=='KK')&&()) {}
			print "<td align=center><input type=submit accesskey=\"m\" value=\"Modtag\" name=\"submit\"></td>";
		}
		elseif (($status > 1)&&($bogfor==1)){print "<td align=center><input type=submit accesskey=\"b\" value=\"Bogf&oslash;r\" name=\"submit\"></td>";}
		if (!$posnr[1]) {print "<td align=center><input type=submit value=\"&nbsp;&nbsp;Slet&nbsp;&nbsp;\" name=\"submit\"></td>";}
	}
	print "</tbody></table></td></tr>";
	print "</form>";
	print "</tbody></table></td></tr></tbody></table></td></tr>";
	print "<tr><td></td></tr>";
}
######################################################################################################################################
function ansatopslag($sort, $fokus, $id)
{
	global $font;

	sidehoved($id, "ordre.php", "../kreditor/kreditorkort.php", $fokus, "Produktionsordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font<a href=ordre.php?sort=navn&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=tlf&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Lokal</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=mobil&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Mobil</b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=email&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>E-mail</b></small></td>";
	print" </tr>";


	$sort = $_GET['sort'];
	if (!$sort) {$sort = navn;}

	$query = db_select("select konto_id from ordrer where id = $id");
	$row = db_fetch_array($query);

	$query = db_select("select * from ansatte where konto_id = $row[konto_id] order by $sort");
	while ($row = db_fetch_array($query))
	{
		print "<td><small>$font<a href='ordre.php?fokus=$fokus&id=$id&kontakt=$row[navn]'>$row[navn]</a></small></td>";
		print "<td><small>$font $row[tlf]</small></td>";
		print "<td><small>$font $row[mobil]</small></td>";
		print "<td><small>$font $row[email]</small></td>";
		print "</tr>";
	}

print "</tbody></table></td></tr></tbody></table>";
exit;
}
######################################################################################################################################
function vareopslag($sort, $fokus, $id, $vis)
{
	global $kontonr;
	global $font;


	if ((!$kontonr)&&($id))
	{
		$query = db_select("select kontonr from ordrer where id = $id");
		if ($row = db_fetch_array($query)) {$kontonr=trim(	$row[kontonr]);}
	}
	if ($kontonr)
	{
		$query = db_select("select id from adresser where kontonr = '$kontonr'");
		if ($row = db_fetch_array($query)) {$leverandor=$row[id];}
	}

	sidehoved($id, "ordre.php", "../lager/varekort.php", $fokus, "Produktionsordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font<a href=ordre.php?sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=$vis>Varenr</a></b></small></td>";
	print"<td><small><b>$font<a href=ordre.php?sort=beskrivelse&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=$vis>Beskrivelse</a></b></small></td>";
	print"<td align=right><small><b>$font<a href=ordre.php?sort=salgspris&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=$vis>Salgspris</a></b></small></td>";
	print"<td align=right><small><b>$font Kostpris</b></small></td>";
	print"<td align=right><small><b>$font Beholdning</b></small></td>";
	print"<td width=2%></td>";
	print"<td align><small><b>$font Leverand&oslash;r</b></small></td>";
	if ($kontonr)
	{
		if ($vis) {print"<td align=right><small>$font<a href=ordre.php?sort=$sort&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id>Vis alle lev.</a></small></td>";}
		else {print"<td align=right><small>$font<a href=ordre.php?sort=$sort&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis=1>Vis aktuel lev.</a></small></td>";}
	}
		print" </tr>";

	$sort = $_GET['sort'];
	if (!$sort) {$sort = varenr;}


	$vare_id=array();
	if (($vis)&&($leverandor))
	{
		$temp=" and lev_id = ".$leverandor;
	}

	$x=0;
	$query = db_select("select * from vare_lev");
	while ($row = db_fetch_array($query))
	{
		$x++;
		$vare_id[$x]=$row[vare_id];
	}

	if (!$kontonr){$x++;}
	elseif ($x>1) {print "<td colspan=9><hr></td>";}
	$query = db_select("select * from varer where lukket != '1' order by $sort");
	while ($row = db_fetch_array($query))
	{
		$varenr=trim($row[varenr]);
		$vist=0;
		$query2 = db_select("select * from vare_lev where vare_id = $row[id] $temp");
		while ($row2 = db_fetch_array($query2))
		{
			print "<tr>";
			print "<td><small>$font<a href=\"ordre.php?varenr=$varenr&fokus=$fokus&konto_id=$row2[lev_id]&id=$id\">$varenr</a></small></td>";
			print "<td><small>$font $row[beskrivelse]<br></small></td>";
			$salgspris=dkdecimal($row[salgspris]);
			print "<td align=right><small>$font $salgspris<br></small></td>";
			$kostpris=dkdecimal($row2[kostpris]);
			print "<td align=right><small>$font $kostpris<br></small></td>";
			print "<td align=right><small>$font $row[beholdning]<br></small></td>";
			print "<td></td>";
			$levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]");
			if ($levrow = db_fetch_array($levquery)){print "<td><small>$font $levrow[kontonr] - $levrow[firmanavn]</small></td>";}
			else {print "<td></td>";}
			print "<td align=right><a href=\"../lager/varekort.php?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus&id=$row[id]\">$font<small>Ret</a></td>";
			print "</tr>";
			$vist=1;
		}
		if ((!in_array($row[id], $vare_id))&&($vist==0)&&($row['samlevare']!='on'))
		{
			print "<tr>";
			print "<td><small>$font<a href=\"ordre.php?varenr=$varenr&fokus=$fokus&id=$id\">$row[varenr]</a></small></td>";
			print "<td><small>$font $row[beskrivelse]<br></small></td>";
			$salgspris=dkdecimal($row[salgspris]);
			print "<td align=right><small>$font $salgspris<br></small></td>";
			$kostpris=dkdecimal($row[kostpris]);
			print "<td align=right><small>$font $kostpris<br></small></td>";
			print "<td></td><td></td><td></td>";
			print "<td align=right><a href=\"../lager/varekort.php?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus&id=$row[id]\">$font<small>Ret</a></td>";
			print "</tr>";
		}
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function sidehoved($id, $returside, $kort, $fokus, $tekst)
{
global $bgcolor2;

	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>SALDI - ordre</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";
	print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
	print "<div align=\"center\">";

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	if ($returside != "ordre.php") {print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=ordreliste.php accesskey=L>Luk</a></small></td>";}
	else {print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=ordre.php?id=$id accesskey=L>Luk</a></small></td>";}
	print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>$tekst</small></td>";
	if ($returside != "ordre.php") {print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=ordre.php?returside=ordreliste.php accesskey=N>Ny</a></small></td>";}
	else {print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus accesskey=N>Ny</a></small></td>";}
	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td valign=\"top\" align=center>";
}
######################################################################################################################################
function indset_konto($id)
{
	global $art;
	global $brugernavn;
	$tidspkt=date("U");

	$query = db_select("select * from adresser where art = 'S'");
	if ($row = db_fetch_array($query))
	{
		$konto_id=trim($row[id]);
		$kontonr=trim($row['kontonr']);
		$firmanavn=trim($row['firmanavn']);
		$addr1=trim($row['addr1']);
		$addr2=trim($row['addr2']);
		$postnr=trim($row['postnr']);
		$bynavn=trim($row['bynavn']);
		$land=trim($row['land']);
		$cvrnr=trim($row['cvrnr']);
	}
	else
	{
		print "<BODY onLoad=\"javascript:alert('Stamdata ikke oprettet')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		exit;
	}
	
	if (!$id)
	{
		$ordredate=date("Y-m-d");
		$query = db_select("select ordrenr from ordrer where art='PO' order by ordrenr desc");
		if ($row = db_fetch_array($query)) {$ordrenr=$row[ordrenr]+1;}
		else {$ordrenr=1;}

		db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, cvrnr, art, ordredate, status, hvem, tidspkt) values ($ordrenr, '$konto_id', '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$cvrnr', 'PO', '$ordredate', '0', '$brugernavn', '$tidspkt')");
		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc");
		if ($row = db_fetch_array($query)) {$id=$row[id];}
	}
	elseif($firmanavn)	
	{
		$query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'");
		if ($row = db_fetch_array($query))
		{
			 db_modify("update ordrer set konto_id=$konto_id, kontonr='$kontonr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', notes='$notes', hvem = '$brugernavn', tidspkt='$tidspkt'	where id=$id");
		 }
		else 
		{			
			$query = db_select("select hvem from ordrer where id=$id");
			if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Ordren er overtaget af $row[hvem]')\">";}
			else {print "<BODY onLoad=\"javascript:alert('Du er blevet smidt af')\">";}
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		}	
	}
	return $id;
}
######################################################################################################################################
function find_vare_id ($varenr)
{
	$query = db_select("select id from varer where varenr = '$varenr'");
	if ($row = db_fetch_array($query)) {return $row[id];}
}


?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
