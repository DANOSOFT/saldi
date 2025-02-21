<?php
	@session_start();
	$s_id=session_id();

	// -------sager/autocomplete.php--------lap 3.0.0-------2013-01-06-------
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2003-2013 DANOSOFT ApS
// ----------------------------------------------------------------------

	$bg="nix";
	$header='nix';
	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	
	$q=$_GET['q'];
	switch ($_GET['mode']) {
		case 'sagsnr': // til søgning af sagsnummer i loen.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT id, sagsnr, udf_addr1, udf_postnr, udf_bynavn, status FROM sager WHERE status != 'Ordrebekræftelse' AND status != 'Tilbud' AND status != 'Beregning' AND status != 'Afsluttet' AND sagsnr::text LIKE '%$my_data%' ORDER BY id",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['sagsnr']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."\n";
				}
			} 
			break;
		case 'sagsaddr': // til søgning af sagsaddresse i loen.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT id, sagsnr, udf_addr1, udf_postnr, udf_bynavn, status FROM sager WHERE status != 'Ordrebekræftelse' AND status != 'Tilbud' AND status != 'Beregning' AND status != 'Afsluttet' AND (udf_addr1 ILIKE '%$my_data%' OR udf_postnr ILIKE '%$my_data%' OR udf_bynavn ILIKE '%$my_data%') ORDER BY udf_addr1",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['sagsnr']."\n";
				}
			}
			break;
/*
		case 'opgnr': // til søgning af opgavenummer i loen.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT id, nr, beskrivelse FROM opgaver WHERE nr::text LIKE '%$my_data%' ORDER BY id",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['nr']."|".$row['beskrivelse']."\n";
				}
			} 
			break;
		case 'opgbesk': // til søgning af opgavenummer i loen.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT id, nr, beskrivelse FROM opgaver WHERE beskrivelse LIKE '%$my_data%' ORDER BY beskrivelse",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['beskrivelse']."|".$row['nr']."\n";
				}
			} 
			break;
*/			
		case 'medarbejdernr': // til søgning af medarbejdernummer i loen.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT id, konto_id, navn, nummer, lukket FROM ansatte WHERE konto_id = 1 AND lukket < '0' AND nummer::text LIKE '%$my_data%' ORDER BY nummer",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['nummer']."|".$row['navn']."\n";
				}
			}
			break;
		case 'medarbejdernavn': // til søgning af medarbejdernavn i loen.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT id, konto_id, navn, nummer, lukket FROM ansatte WHERE konto_id = 1 AND lukket < '0' AND navn ILIKE '%$my_data%' ORDER BY navn",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['navn']."|".$row['nummer']."\n";
				}
			}
			break;
		case 'sager': // til søgning af sager i notat.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM sager WHERE sagsnr::text LIKE '%$my_data%' OR udf_addr1 ILIKE '%$my_data%' OR udf_postnr ILIKE '%$my_data%' OR udf_bynavn ILIKE '%$my_data%' ORDER BY udf_addr1",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['sagsnr']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['id']."\n";
				}
			} 
			break;
		case 'medarbejder': // til søgning af medarbejdernr og medarbejdernavn i notat.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT id, konto_id, navn, nummer FROM ansatte WHERE konto_id = 1 AND medarbejdernr::text LIKE '%$my_data%' OR konto_id = 1 AND navn ILIKE '%$my_data%' ORDER BY navn",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['nummer']."|".$row['navn']."\n";
				}
			}
			break;
		case 'kontonr': // til søgning af kontonummer i kunder.php og i sager.php under 'opret sag'
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM adresser WHERE art='D' AND kontonr::text LIKE '%$my_data%' ORDER BY kontonr",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['kontonr']."|".$row['firmanavn']."|".$row['addr1'].", ".$row['postnr']." ".$row['bynavn']."|".$row['id']."\n";
				}
			} 
			break;
		case 'firmanavn': // til søgning af firmanavn i kunder.php og i sager.php under 'opret sag'
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM adresser WHERE art='D' AND firmanavn ILIKE '%$my_data%' ORDER BY firmanavn",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['firmanavn']."|".$row['kontonr']."|".$row['addr1'].", ".$row['postnr']." ".$row['bynavn']."|".$row['id']."\n";
				}
			} 
			break;
		case 'firmaadresse': // til søgning af firmaadresse i kunder.php og i sager.php under 'opret sag'
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM adresser WHERE art='D' AND addr1 ILIKE '%$my_data%' OR postnr ILIKE '%$my_data%' OR bynavn ILIKE '%$my_data%' ORDER BY addr1",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['addr1'].", ".$row['postnr']." ".$row['bynavn']."|".$row['firmanavn']."|".$row['kontonr']."|".$row['id']."\n";
				}
			} 
			break;
		case 'sagsagsnr': // til søgning af sagsnummer i sager.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM sager WHERE sagsnr::text LIKE '%$my_data%' ORDER BY id",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['sagsnr']."|".$row['firmanavn']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['id']."|".$row['status']."\n";
				}
			} 
			break;
		case 'sagfirmanavn': // til søgning af firmanavn i sager.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM sager WHERE firmanavn ILIKE '%$my_data%' ORDER BY firmanavn,sagsnr DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['firmanavn']."|".$row['sagsnr']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['id']."|".$row['status']."\n";
				}
			} 
			break;
		case 'sagadresse': // til søgning af sagsaddresse i sager.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM sager WHERE udf_addr1 ILIKE '%$my_data%' OR udf_postnr ILIKE '%$my_data%' OR udf_bynavn ILIKE '%$my_data%' ORDER BY udf_addr1",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['firmanavn']."|".$row['sagsnr']."|".$row['id']."|".$row['status']."\n";
				}
			} 
			break;
		case 'n_dato': // til søgning af dato i notat.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT noter.id as n_id,noter.datotid,noter.hvem as n_hvem,noter.beskrivelse,noter.assign_id,noter.assign_to,sager.id,sager.sagsnr FROM noter 
														LEFT JOIN sager ON noter.assign_id = sager.id
														WHERE assign_to='sager' AND datotid::text LIKE '%$my_data%' ORDER BY datotid DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['n_hvem']."|".$row['beskrivelse']."|".$row['n_id']."\n";
				}
			} 
			break;
		case 'n_sagsnr': // til søgning af sagsnummer i notat.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT noter.id as n_id,noter.datotid,noter.hvem as n_hvem,noter.beskrivelse,noter.assign_id,noter.assign_to,sager.id,sager.sagsnr FROM noter 
														INNER JOIN sager ON noter.assign_id = sager.id
														WHERE assign_to='sager' AND sager.sagsnr::text LIKE '%$my_data%' ORDER BY sagsnr DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['sagsnr']."|".date("d-m-Y",$row['datotid'])."|".$row['n_hvem']."|".$row['beskrivelse']."|".$row['n_id']."\n";
				}
			} 
			break;
		case 'n_af': // til søgning af forfatter i notat.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT noter.id as n_id,noter.datotid,noter.hvem as n_hvem,noter.beskrivelse,noter.assign_id,noter.assign_to,sager.id,sager.sagsnr FROM noter 
														LEFT JOIN sager ON noter.assign_id = sager.id
														WHERE assign_to='sager' AND noter.hvem ILIKE '%$my_data%' ORDER BY noter.hvem DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['n_hvem']."|".date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['beskrivelse']."|".$row['n_id']."\n";
				}
			} 
			break;
		case 'n_beskrivelse': // til søgning af beskrivelse i notat.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT noter.id as n_id,noter.datotid,noter.hvem as n_hvem,noter.beskrivelse,noter.assign_id,noter.assign_to,sager.id,sager.sagsnr FROM noter 
														LEFT JOIN sager ON noter.assign_id = sager.id
														WHERE assign_to='sager' AND noter.beskrivelse ILIKE '%$my_data%' ORDER BY noter.beskrivelse DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['beskrivelse']."|".date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['n_hvem']."|".$row['n_id']."\n";
				}
			} 
			break;
		case 'n_sagsagsnr': // til søgning af sagsnummer i notat.php i 'find sag'
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM sager WHERE sagsnr::text LIKE '%$my_data%' ORDER BY id",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['sagsnr']."|".$row['firmanavn']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['id']."|".$row['konto_id']."\n";
				}
			} 
			break;
		case 'n_sagfirmanavn': // til søgning af firmanavn i notat.php i 'find sag'
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM sager WHERE firmanavn ILIKE '%$my_data%' ORDER BY firmanavn",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['firmanavn']."|".$row['sagsnr']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['id']."|".$row['konto_id']."\n";
				}
			} 
			break;
		case 'n_sagadresse': // til søgning af sagsaddresse i notat.php i 'find sag'
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM sager WHERE udf_addr1 ILIKE '%$my_data%' OR udf_postnr ILIKE '%$my_data%' OR udf_bynavn ILIKE '%$my_data%' ORDER BY udf_addr1",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['firmanavn']."|".$row['sagsnr']."|".$row['id']."|".$row['konto_id']."\n";
				}
			} 
			break;
		case 'medarbejdernr2': // til søgning af medarbejdernr i ansatte.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM ansatte WHERE konto_id = 1 AND nummer::text LIKE '%$my_data%' ORDER BY nummer",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['nummer']."|".$row['navn']."|".$row['addr1'].", ".$row['postnr']." ".$row['bynavn']."|".$row['id']."\n";
				}
			} 
			break;
		case 'medarbejdernavn2': // til søgning af medarbejdernavn i ansatte.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM ansatte WHERE konto_id = 1 AND navn ILIKE '%$my_data%' ORDER BY navn",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['navn']."|".$row['nummer']."|".$row['addr1'].", ".$row['postnr']." ".$row['bynavn']."|".$row['id']."\n";
				}
			} 
			break;
		case 'medarbejderadresse': // til søgning af medarbejderadresse i ansatte.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM ansatte WHERE konto_id = 1 AND addr1 ILIKE '%$my_data%' OR postnr ILIKE '%$my_data%' OR bynavn ILIKE '%$my_data%' ORDER BY addr1",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['addr1'].", ".$row['postnr']." ".$row['bynavn']."|".$row['navn']."|".$row['nummer']."|".$row['id']."\n";
				}
			} 
			break;
		case 'k_dato': // til søgning af dato i kontrolskemaer.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND datotid::text LIKE '%$my_data%' ORDER BY datotid DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['skema_hvem']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'k_sagsnr': // til søgning af sagsnummer i kontrolskemaer.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND sager.sagsnr::text LIKE '%$my_data%' ORDER BY sagsnr DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo $row['sagsnr']."|".date("d-m-Y",$row['datotid'])."|".$row['skema_hvem']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'k_af': // til søgning af forfatter i kontrolskemaer.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND tjekskema.hvem ILIKE '%$my_data%' ORDER BY tjekskema.hvem DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo $row['skema_hvem']."|".date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'k_adresse': // til søgning af sagsaddresse i kontrolskemaer.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND sager.udf_addr1 ILIKE '%$my_data%' OR sager.udf_postnr ILIKE '%$my_data%' OR sager.udf_bynavn ILIKE '%$my_data%' ORDER BY sager.udf_addr1",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo $row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['skema_hvem']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'sjak': // til søgning af sjak i kontrol_sager.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT id, navn, initialer, lukket FROM ansatte WHERE konto_id = 1 AND lukket < '0' AND navn ILIKE '%$my_data%' ORDER BY initialer",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['initialer']."|".$row['navn']."|".$row['id']."\n";
				}
			}
			break;
		case 'ordre_kopi_sagsnr': // til søgning af sagsnummer i sager.php i 'kopi_ordre'
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM sager WHERE sagsnr::text LIKE '%$my_data%' ORDER BY id",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['sagsnr']."|".$row['firmanavn']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['id']."|".$row['konto_id']."\n";
				}
			} 
			break;
		case 'ordre_kopi_firmanavn': // til søgning af firmanavn i sager.php i 'kopi_ordre'
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM sager WHERE firmanavn ILIKE '%$my_data%' ORDER BY firmanavn",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['firmanavn']."|".$row['sagsnr']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['id']."|".$row['konto_id']."\n";
				}
			} 
			break;
		case 'ordre_kopi_adresse': // til søgning af sagsaddresse i sager.php i 'kopi_ordre'
			$my_data=db_escape_string($q);
			$result = db_select("SELECT * FROM sager WHERE udf_addr1 ILIKE '%$my_data%' OR udf_postnr ILIKE '%$my_data%' OR udf_bynavn ILIKE '%$my_data%' ORDER BY udf_addr1",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['firmanavn']."|".$row['sagsnr']."|".$row['id']."|".$row['konto_id']."\n";
				}
			} 
			break;
		case 'ma_dato': // til søgning af dato i mm_kontrolskemaer.php i 'vis_arbejdsseddel'
			$my_data=db_escape_string($q);
			$ans_id=if_isset($_SESSION['ans_id']);
			
			if ($ans_id) {
				$tmp1=$ans_id.chr(59);
				$tmp2=chr(59).$ans_id;
				$tmp3=chr(59).$ans_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ans_id')";
			} else {
				$tmp1=$ansat_id.chr(59);
				$tmp2=chr(59).$ansat_id;
				$tmp3=chr(59).$ansat_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ansat_id')";
			}
			
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND tjekliste.fase = '1' AND $where AND datotid::text LIKE '%$my_data%' ORDER BY datotid DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['skema_hvem']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'ma_sagsnr': // til søgning af sagsnummer i mm_kontrolskemaer.php i 'vis_arbejdsseddel'
			$my_data=db_escape_string($q);
			$ans_id=if_isset($_SESSION['ans_id']);
			
			if ($ans_id) {
				$tmp1=$ans_id.chr(59);
				$tmp2=chr(59).$ans_id;
				$tmp3=chr(59).$ans_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ans_id')";
			} else {
				$tmp1=$ansat_id.chr(59);
				$tmp2=chr(59).$ansat_id;
				$tmp3=chr(59).$ansat_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ansat_id')";
			}
			
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND tjekliste.fase = '1' AND $where AND sager.sagsnr::text LIKE '%$my_data%' ORDER BY sagsnr DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo $row['sagsnr']."|".date("d-m-Y",$row['datotid'])."|".$row['skema_hvem']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'ma_af': // til søgning af forfatter i mm_kontrolskemaer.php i 'vis_arbejdsseddel'
			$my_data=db_escape_string($q);
			$ans_id=if_isset($_SESSION['ans_id']);
			
			if ($ans_id) {
				$tmp1=$ans_id.chr(59);
				$tmp2=chr(59).$ans_id;
				$tmp3=chr(59).$ans_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ans_id')";
			} else {
				$tmp1=$ansat_id.chr(59);
				$tmp2=chr(59).$ansat_id;
				$tmp3=chr(59).$ansat_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ansat_id')";
			}
			
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND tjekliste.fase = '1' AND $where AND tjekskema.hvem ILIKE '%$my_data%' ORDER BY tjekskema.hvem DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo $row['skema_hvem']."|".date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'ma_adresse': // til søgning af sagsaddresse i mm_kontrolskemaer.php i 'vis_arbejdsseddel'
			$my_data=db_escape_string($q);
			$ans_id=if_isset($_SESSION['ans_id']);
			
			if ($ans_id) {
				$tmp1=$ans_id.chr(59);
				$tmp2=chr(59).$ans_id;
				$tmp3=chr(59).$ans_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ans_id')";
			} else {
				$tmp1=$ansat_id.chr(59);
				$tmp2=chr(59).$ansat_id;
				$tmp3=chr(59).$ansat_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ansat_id')";
			}
			
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND tjekliste.fase = '1' AND $where AND (sager.udf_addr1 ILIKE '%$my_data%' OR sager.udf_postnr ILIKE '%$my_data%' OR sager.udf_bynavn ILIKE '%$my_data%') ORDER BY sager.udf_addr1",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo $row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['skema_hvem']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'mk_dato': // til søgning af dato i mm_kontrolskemaer.php i 'vis_kontrolskema'
			$my_data=db_escape_string($q);
			$ans_id=if_isset($_SESSION['ans_id']);
			
			if ($ans_id) {
				$tmp1=$ans_id.chr(59);
				$tmp2=chr(59).$ans_id;
				$tmp3=chr(59).$ans_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ans_id')";
			} else {
				$tmp1=$ansat_id.chr(59);
				$tmp2=chr(59).$ansat_id;
				$tmp3=chr(59).$ansat_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ansat_id')";
			}
			
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND tjekliste.fase != '1' AND $where AND datotid::text LIKE '%$my_data%' ORDER BY datotid DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['skema_hvem']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'mk_sagsnr': // til søgning af sagsnummer i mm_kontrolskemaer.php i 'vis_kontrolskema'
			$my_data=db_escape_string($q);
			$ans_id=if_isset($_SESSION['ans_id']);
			
			if ($ans_id) {
				$tmp1=$ans_id.chr(59);
				$tmp2=chr(59).$ans_id;
				$tmp3=chr(59).$ans_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ans_id')";
			} else {
				$tmp1=$ansat_id.chr(59);
				$tmp2=chr(59).$ansat_id;
				$tmp3=chr(59).$ansat_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ansat_id')";
			}
			
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND tjekliste.fase != '1' AND $where AND sager.sagsnr::text LIKE '%$my_data%' ORDER BY sagsnr DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo $row['sagsnr']."|".date("d-m-Y",$row['datotid'])."|".$row['skema_hvem']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'mk_af': // til søgning af forfatter i mm_kontrolskemaer.php i 'vis_kontrolskema'
			$my_data=db_escape_string($q);
			$ans_id=if_isset($_SESSION['ans_id']);
			
			if ($ans_id) {
				$tmp1=$ans_id.chr(59);
				$tmp2=chr(59).$ans_id;
				$tmp3=chr(59).$ans_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ans_id')";
			} else {
				$tmp1=$ansat_id.chr(59);
				$tmp2=chr(59).$ansat_id;
				$tmp3=chr(59).$ansat_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ansat_id')";
			}
			
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND tjekliste.fase != '1' AND $where AND tjekskema.hvem ILIKE '%$my_data%' ORDER BY tjekskema.hvem DESC",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo $row['skema_hvem']."|".date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'mk_adresse': // til søgning af sagsaddresse i mm_kontrolskemaer.php i 'vis_kontrolskema'
			$my_data=db_escape_string($q);
			$ans_id=if_isset($_SESSION['ans_id']);
			
			if ($ans_id) {
				$tmp1=$ans_id.chr(59);
				$tmp2=chr(59).$ans_id;
				$tmp3=chr(59).$ans_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ans_id')";
			} else {
				$tmp1=$ansat_id.chr(59);
				$tmp2=chr(59).$ansat_id;
				$tmp3=chr(59).$ansat_id.chr(59);
				$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ansat_id')";
			}
			
			$result = db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' AND tjekliste.assign_id = '0' AND tjekliste.fase != '1' AND $where AND (sager.udf_addr1 ILIKE '%$my_data%' OR sager.udf_postnr ILIKE '%$my_data%' OR sager.udf_bynavn ILIKE '%$my_data%') ORDER BY sager.udf_addr1",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					if (!empty($row['datotid'])) {
						echo $row['udf_addr1'].", ".$row['udf_postnr']." ".$row['udf_bynavn']."|".date("d-m-Y",$row['datotid'])."|".$row['sagsnr']."|".$row['skema_hvem']."|".$row['tjekpunkt']."|".$row['opg_navn']."|".$row['skema_id']."|".$row['fase']."|".$row['sag_id']."|".$row['tjek_id']."\n";
					}
				}
			} 
			break;
		case 'mm_medarbejdernavn': // til søgning af medarbejdernavn i medarbejdermappe.php
			$my_data=db_escape_string($q);
			$result = db_select("SELECT id, navn, initialer FROM ansatte WHERE konto_id = 1 AND navn ILIKE '%$my_data%' ORDER BY initialer",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['initialer']."|".$row['navn']."|".$row['id']."\n";
				}
			}
			break;
		case 'ss_sagfirmanavn': // til søgning af kunde i sager.php i avanceret søg i sagsliste
			$my_data=db_escape_string($q);
			$AND = NULL;
			
			$ss_sagsagsnr=if_isset($_SESSION['ss_sagsagsnr']);
			$ss_sagadresse=if_isset($_SESSION['ss_sagadresse']);
			$ss_sagpostnr=if_isset($_SESSION['ss_sagpostnr']);
			$ss_sagby=if_isset($_SESSION['ss_sagby']);
			$ss_ansvarlig=if_isset($_SESSION['ss_ansvarlig']);
			$ss_status=if_isset($_SESSION['ss_status']);
			
			if ($ss_sagsagsnr) $AND.= " AND sagsnr = '$ss_sagsagsnr'";
			if ($ss_sagadresse) {
				if (preg_match('/[A-Za-z]\s/', $ss_sagadresse) && preg_match('/[0-9]/', $ss_sagadresse)) {
					$result = preg_split('/(?<=[A-Za-z])\s+(?=\d)/', "$ss_sagadresse");
					$letter = $result[0];
					$number = $result[1];
					$AND.= " and udf_addr1 ILIKE '%$letter%' and udf_addr1 ILIKE '%$number%'";
				} else {
					$AND.= " and udf_addr1 ILIKE '%$ss_sagadresse%'";
				}
			}
			if ($ss_sagpostnr) $AND.= " AND udf_postnr = '$ss_sagpostnr'"; 
			if ($ss_sagby) $AND.= " AND udf_bynavn ILIKE '%$ss_sagby%'";
			if ($ss_ansvarlig) $AND.= " AND ref = '$ss_ansvarlig'";
			if ($ss_status) $AND.= " AND status = '$ss_status'";
			
			$result = db_select("SELECT DISTINCT firmanavn FROM sager WHERE firmanavn ILIKE '%$my_data%' $AND ORDER BY firmanavn",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['firmanavn']."\n";
				}
			}
			break;
		case 'ss_sagpostnr': // til søgning af postnr i sager.php i avanceret søg i sagsliste
			$my_data=db_escape_string($q);
			$AND = NULL;
			
			$ss_sagsagsnr=if_isset($_SESSION['ss_sagsagsnr']);
			$ss_sagfirmanavn=if_isset($_SESSION['ss_sagfirmanavn']);
			$ss_sagadresse=if_isset($_SESSION['ss_sagadresse']);
			$ss_sagby=if_isset($_SESSION['ss_sagby']);
			$ss_ansvarlig=if_isset($_SESSION['ss_ansvarlig']);
			$ss_status=if_isset($_SESSION['ss_status']);
			
			if ($ss_sagsagsnr) $AND.= " AND sagsnr = '$ss_sagsagsnr'";
			if ($ss_sagfirmanavn) $AND.= " AND firmanavn = '$ss_sagfirmanavn'";
			if ($ss_sagadresse) {
				if (preg_match('/[A-Za-z]\s/', $ss_sagadresse) && preg_match('/[0-9]/', $ss_sagadresse)) {
					$result = preg_split('/(?<=[A-Za-z])\s+(?=\d)/', "$ss_sagadresse");
					$letter = $result[0];
					$number = $result[1];
					$AND.= " and udf_addr1 ILIKE '%$letter%' and udf_addr1 ILIKE '%$number%'";
				} else {
					$AND.= " and udf_addr1 ILIKE '%$ss_sagadresse%'";
				}
			}
			if ($ss_sagby) $AND.= " AND udf_bynavn ILIKE '%$ss_sagby%'";
			if ($ss_ansvarlig) $AND.= " AND ref = '$ss_ansvarlig'";
			if ($ss_status) $AND.= " AND status = '$ss_status'";
			
			$result = db_select("SELECT DISTINCT(TRIM(udf_postnr, ' ')) udf_postnr FROM sager WHERE udf_postnr ILIKE '$my_data%' $AND ORDER BY udf_postnr",__FILE__ . " linje " . __LINE__);
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo $row['udf_postnr']."\n";
				}
			}
			break;
		case 'ss_sagby': // til søgning af bynavn i sager.php i avanceret søg i sagsliste
			$my_data=db_escape_string($q);
			$AND = NULL;
			
			/*
			function mb_ucfirst($string, $encoding='UTF-8')
			{
					$firstChar = mb_substr($string, 0, 1, $encoding);
					$then = mb_substr($string, 1, mb_strlen($string, $encoding)-1, $encoding);
					return mb_strtoupper($firstChar, $encoding) . $then;
			}
			function mb_ucwords($str) {
				return mb_convert_case($str, MB_CASE_TITLE, "UTF-8");
			}
			*/
			$ss_sagsagsnr=if_isset($_SESSION['ss_sagsagsnr']);
			$ss_sagfirmanavn=if_isset($_SESSION['ss_sagfirmanavn']);
			$ss_sagadresse=if_isset($_SESSION['ss_sagadresse']);
			$ss_sagpostnr=if_isset($_SESSION['ss_sagpostnr']);
			$ss_ansvarlig=if_isset($_SESSION['ss_ansvarlig']);
			$ss_status=if_isset($_SESSION['ss_status']);
			
			if ($ss_sagsagsnr) $AND.= " AND sagsnr = '$ss_sagsagsnr'";
			if ($ss_sagfirmanavn) $AND.= " AND firmanavn = '$ss_sagfirmanavn'";
			if ($ss_sagadresse) {
				if (preg_match('/[A-Za-z]\s/', $ss_sagadresse) && preg_match('/[0-9]/', $ss_sagadresse)) {
					$result = preg_split('/(?<=[A-Za-z])\s+(?=\d)/', "$ss_sagadresse");
					$letter = $result[0];
					$number = $result[1];
					$AND.= " and udf_addr1 ILIKE '%$letter%' and udf_addr1 ILIKE '%$number%'";
				} else {
					$AND.= " and udf_addr1 ILIKE '%$ss_sagadresse%'";
				}
			}
			if ($ss_sagpostnr) $AND.= " AND udf_postnr = '$ss_sagpostnr'"; 
			if ($ss_ansvarlig) $AND.= " AND ref = '$ss_ansvarlig'";
			if ($ss_status) $AND.= " AND status = '$ss_status'";
			
			//$result = db_select("SELECT DISTINCT(LOWER(TRIM(REPLACE(udf_bynavn, '.', '')))) udf_bynavn FROM sager WHERE udf_bynavn ILIKE '%$my_data%' $AND ORDER BY udf_bynavn",__FILE__ . " linje " . __LINE__);
			$result = db_select("SELECT DISTINCT(LOWER(TRIM(TRIM(TRAILING '.' FROM udf_bynavn)))) udf_bynavn FROM sager WHERE udf_bynavn ILIKE '%$my_data%' $AND ORDER BY udf_bynavn",__FILE__ . " linje " . __LINE__); //DISTINCT(LOWER(TRIM(udf_bynavn, ' '))) 
			
			if($result)
			{
				while($row=db_fetch_array($result))
				{
					echo mb_ucwords($row['udf_bynavn'])."\n";
				}
			}
			break;
	}
?>