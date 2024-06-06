<?php
@session_start();	# Skal angives oeverst i filen??!!
$s_id=session_id();
// ------ sager/leftmenu.php-------lap 3.3.0 ------2013-02-11------12:11---------
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
// Copyright (c) 2003-2013 Danosoft ApS
// ----------------------------------------------------------------------
// små rettelser i menu sager
// 20140104 Rettet i menu sager. Under funktion ret_opgave er der tilføjet link til akkordsedler
// 20140104 Query hvor 'opgave_id' og 'sag_id' bliver lavet til sagsnr og opgavenr i $s_sag_nr. Søg 20140104-1
// 20140104 Her finder vi ud af om $s_sag_nr indeholder opgave. Hvis opgave, hentes 'sag_id' og 'opgave_id'. Link til både opgave og sag. Søg 20140104-2
// 20140522 Ændret side-menu i certificering til dynamisk, så forskellige kontrolkemaer kan vises. Søg #22052014
// 20141003 PK - Har tilføjet lønopgørelse til loen. 
// 20141205 PK - Har tilføjet 'MENU MEDARBEJDERMAPPE'
// 20150513 PK - Har sat session på menupunkter (godkendte,afviste,betalte) i loenliste. 
// 20150528 PK - Har sat session på menupunkter (tilbud,aktive,afsluttede) i sagsliste.
// 20150702 PK - Har lavet en span med class 'loenafregningVis' i loenafregning. Her bliver resultatet fra ajax_loenafregning.php sat ind.
// 20151007 PK - Har lavet nye menupunkter til visning af ansatte og fratrådte i MENU ANSATTE. Der er også sat session på menupunkter (alle,fratrådte).
// 20160217 PK - Har lavet nyt menupunkt 'Til sag' i MENU KUNDER.
// 20160816 PK - Har tilføjet 'notatliste' til MENU DAGBOG + 'Til notat' i function find_person
// 20160930 PK - Har tilføjet nyt menupunkt 'Ændre kunde' i MENU SAGER (funktion ret_sag)
// 20170804 PK - Har tilføjet 'opgave_id, loendate, loendateFra og loendateTil' i link til akkordsedler i MENU SAGER (funktion akkordliste)
// 20170804 PK - Har tilføjet nyt menupunkt 'Til akkordlister' i MENU LOEN, som sender 'sag_id, opg_id, akkorfraSoeg og akkordtilSoeg' til akkordlister under sager

if (!isset($loendate))      $loendate      = NULL;
if (!isset($loendateFra))   $loendateFra   = NULL;
if (!isset($loendateTil))   $loendateTil   = NULL;
if (!isset($opgave_id))     $opgave_id     = NULL;
if (!isset($soeg))          $soeg          = NULL;
if (!isset($s_loendateFra)) $s_loendateFra = NULL;
if (!isset($s_loendateTil)) $s_loendateTil = NULL;

print "<div class=\"leftmenu\">";
################################## MENU SAGER ###############################
	if ($menu_sager) {
		print "<div class=\"leftmenuhead link\">Sagsliste</div>";
		print "<ul>";
		if ($funktion=='opret_sag') { 
			print "<li><a href=\"kunder.php?funktion=ret_kunde\">Opret kunde</a></li>";
			print "<li><a href=\"sager.php\">Sagsliste</a></li>";
		} elseif ($funktion=='ret_sag') {
			print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">Retur til sag</a></li>";
			print "<li><a href=\"planlaeg_sag.php?sag_id=$sag_id\">Planlægning sag</a></li>";
			print "<li><a href=\"sager.php\">Sagsliste</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"sager.php?funktion=ny_kunde&amp;sag_id=$sag_id&amp;konto_id=$konto_id\">Ændre kunde</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"loen.php?funktion=loenliste&amp;sag_id=$sag_id\">Til akkordsedler</a></li>";
		} elseif ($funktion=='ret_opgave') {
			$opgave_id=if_isset($_GET['opgave_id']);
			print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">Retur til sag</a></li>";
			print "<li><a href=\"planlaeg_sag.php?sag_id=$sag_id\">Planlægning sag</a></li>";
			print "<li><a href=\"sager.php\">Sagsliste</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"loen.php?funktion=loenliste&amp;sag_id=$sag_id&amp;opgave_id=$opgave_id\">Til akkordsedler</a></li>";
		} elseif ($funktion=='vis_sag') {
			print "<li><a href=\"sager.php?funktion=ret_opgave&amp;sag_id=$sag_id\">Opret opgave</a></li>";
			print "<li><a href=\"planlaeg_sag.php?sag_id=$sag_id\">Planlægning sag</a></li>";
			print "<li><a href=\"sager.php\">Sagsliste</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"loen.php?funktion=loenliste&amp;sag_id=$sag_id\">Til akkordsedler</a></li>";
		} elseif ($funktion=='kontrolskema') {
			print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">Retur til sag</a></li>";
			if (isset($_GET['opgave_id'])) {
				$opgave_id = $_GET['opgave_id']; 
				print "<li><a href=\"sager.php?funktion=ret_opgave&amp;sag_id=$sag_id&amp;opgave_id=$opgave_id\">Retur til opgave</a></li>";
			}
			print "<li><a href=\"sager.php\">Sagsliste</a></li>";
		} elseif ($funktion=='kopi_ordre') {
			if ($vis!='beregning') print "<li><a href=\"sager.php?funktion=kopi_ordre&amp;sort=$sort&amp;nysort=$nysort&amp;vis=beregning&amp;returside=ordre&amp;sag_id=$sag_id&amp;ordre_id=$ordre_id\">Vis beregning</a></li>";
			if ($vis!='tilbud') print "<li><a href=\"sager.php?funktion=kopi_ordre&amp;sort=$sort&amp;nysort=$nysort&amp;vis=tilbud&amp;returside=ordre&amp;sag_id=$sag_id&amp;ordre_id=$ordre_id\">Vis tilbud</a></li>";
			if ($vis!='aktiv') print "<li><a href=\"sager.php?funktion=kopi_ordre&amp;sort=$sort&amp;nysort=$nysort&amp;vis=aktiv&amp;returside=ordre&amp;sag_id=$sag_id&amp;ordre_id=$ordre_id\">Vis aktive</a></li>";
			if ($vis!='afsluttede') print "<li><a href=\"sager.php?funktion=kopi_ordre&amp;sort=$sort&amp;nysort=$nysort&amp;vis=afsluttede&amp;returside=ordre&amp;sag_id=$sag_id&amp;ordre_id=$ordre_id\">Vis afsluttede</a></li>";
			if ($vis!=NULL && $vis!='alle') print "<li><a href=\"sager.php?funktion=kopi_ordre&amp;sort=$sort&amp;nysort=$nysort&amp;vis=alle&amp;returside=ordre&amp;sag_id=$sag_id&amp;ordre_id=$ordre_id\">Vis alle</a></li>";
		} elseif ($funktion=='sag_kontakt') {
			if(isset($_POST['konto_id'])) $konto_id = $_POST['konto_id'];
			print "<li><a href=\"sager.php?funktion=ret_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\">Retur til saginfo</a></li>";
			print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">Retur til sag</a></li>";
			print "<li><a href=\"sager.php\">Sagsliste</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"loen.php?funktion=loenliste&amp;sag_id=$sag_id\">Til akkordsedler</a></li>";
		} elseif ($funktion=='akkordliste') {
			if(isset($_POST['akkordfraSoeg'])) $akkordfraSoeg = $_POST['akkordfraSoeg'];
			if(isset($_POST['akkordtilSoeg'])) $akkordtilSoeg = $_POST['akkordtilSoeg'];
			if(isset($_POST['opg_id'])) $opg_id = $_POST['opg_id'];
			if (!$akkordfraSoeg) $akkordfraSoeg=if_isset($_GET['akkordfraSoeg']);
			if (!$akkordtilSoeg) $akkordtilSoeg=if_isset($_GET['akkordtilSoeg']);
			if (!$opg_id) $opg_id=if_isset($_GET['opg_id']);
			print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">Retur til sag</a></li>";
			print "<li><a href=\"sager.php\">Sagsliste</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"loen.php?funktion=loenliste&amp;sag_id=$sag_id&amp;opgave_id=$opg_id&amp;loendate=$akkordfraSoeg&amp;loendateFra=$akkordfraSoeg&amp;loendateTil=$akkordtilSoeg\">Til akkordsedler</a></li>";
		} elseif ($funktion=='ny_kunde') {
			print "<li><a href=\"sager.php?funktion=ret_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\">Retur til saginfo</a></li>";
			print "<li><a href=\"sager.php\">Sagsliste</a></li>";
			
		} else {
			$unsetsort=if_isset($_GET['unsetsort']);
			if ($vis=='alleL' || $unsetsort) {
				unset($_SESSION['beregningL'],$_SESSION['tilbudL'],$_SESSION['aktivL'],$_SESSION['afsluttedeL']);
			}
			if ($vis=='beregningL') {
				unset($_SESSION['tilbudL'],$_SESSION['aktivL'],$_SESSION['afsluttedeL']);
			}
			if ($vis=='tilbudL') {
				unset($_SESSION['beregningL'],$_SESSION['aktivL'],$_SESSION['afsluttedeL']);
			}
			if ($vis=='aktivL') {
				unset($_SESSION['beregningL'],$_SESSION['tilbudL'],$_SESSION['afsluttedeL']);
			}
			if ($vis=='afsluttedeL') {
				unset($_SESSION['beregningL'],$_SESSION['tilbudL'],$_SESSION['aktivL']);
			}
			elseif (isset($_SESSION['beregningL'])   && $_SESSION['beregningL'])  $vis=$_SESSION['beregningL'];
			elseif (isset($_SESSION['tilbudL'])     && $_SESSION['tilbudL'])     $vis=$_SESSION['tilbudL'];
			elseif (isset($_SESSION['aktivL'])      && $_SESSION['aktivL'])      $vis=$_SESSION['aktivL'];
			elseif (isset($_SESSION['afsluttedeL']) && $_SESSION['afsluttedeL']) $vis=$_SESSION['afsluttedeL'];
			
			print "<li><a href=\"sager.php?funktion=opret_sag&amp;sag_id=\">Opret sag</a></li>";
			//if ($vis!='ordrebekraeftelse') print "<li><a href=\"sager.php?funktion=sagsliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=ordrebekraeftelse\">Vis ordrebekræft.</a></li>";
			if ($vis!='beregningL') print "<li><a href=\"sager.php?funktion=sagsliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=beregningL\">Vis beregning</a></li>";
			if ($vis!='tilbudL') print "<li><a href=\"sager.php?funktion=sagsliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=tilbudL\">Vis tilbud</a></li>";
			if ($vis!='aktivL') print "<li><a href=\"sager.php?funktion=sagsliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=aktivL\">Vis aktive</a></li>";
			if ($vis!='afsluttedeL') print "<li><a href=\"sager.php?funktion=sagsliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=afsluttedeL\">Vis afsluttede</a></li>";
			if ($vis!=NULL && $vis!='alleL') print "<li><a href=\"sager.php?funktion=sagsliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=alleL\">Vis alle</a></li>";
		}
		print "</ul>";
################################## MENU DAGBOG ###############################
	} elseif ($menu_dagbog) {
		print "<div class=\"leftmenuhead link\">Dagbog</div>";
		print "<ul>";
		if ($mine_notater) print "<li><a href=\"notat.php\">Alle notater</a></li>";
		else print "<li><a href=\"notat.php?mine_notater=on\">Mine notater</a></li>";
		print "<li><a href=\"notat.php?funktion=ret_note\">Nyt notat</a></li>";
		if ($funktion=='find_person') print "<li><a href=\"notat.php?id=$id\">Til notat</a></li>";
		if ($funktion=='ret_note'||$id) print "<li><a href=\"notat.php\">Notatliste</a></li>";
		print "</ul>";
################################## MENU KUNDER ###############################
	} elseif ($menu_kunder) {
		print "<div class=\"leftmenuhead link\">Kunder</div>";
		print "<ul>";
		if ($funktion=='kontrolskema') print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sagid\">Retur til sag</a></li>";
		elseif ($funktion=='kundeliste') print "<li><a href=\"kunder.php?funktion=ret_kunde\">Opret kunde</a></li>";
		elseif (strstr($funktion,'ret_kunde')) {
			print "<li><a href=\"kunder.php?funktion=kundeliste\">Kundeliste</a></li>";
			print "<li><a href=\"sager.php?funktion=ret_sag&amp;konto_id=$konto_id&amp;sag_id=0\">Opret sag</a></li>";
		}
		if ($funktion=='ret_kunde_ansat') {
			print "<li><a href=\"kunder.php?funktion=ret_kunde&amp;konto_id=$konto_id&amp;sag_id=$sagid\">Retur til kundekort</a></li>";
		}
		if ($sagid) {
			print "<li><hr></li>";
			print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sagid\">Til sag</a></li>";
		}
		print "</ul>";
##################################  MENU LOEN  ###############################
	} elseif ($menu_loen) {
	 print "<div class=\"leftmenuhead link\">Løn</div>";
		print "<ul>"; 
		if ($funktion=='loenliste') {
			
			if (($sag_id=if_isset($_GET['sag_id'])) && (!$opgave_id=if_isset($_GET['opgave_id'])) && (!$loendate=if_isset($_GET['loendate']))) {
				$r=db_fetch_array(db_select("select sagsnr from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
				$s_sag_nr=$r['sagsnr']*1;
				$_SESSION['s_sag_nr']=$s_sag_nr;
				//echo "SAGID";
			}
			if (($sag_id=if_isset($_GET['sag_id'])) && ($opgave_id=if_isset($_GET['opgave_id']))) { # 20140104-1
				$r=db_fetch_array(db_select("select sagsnr from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
				$sagnr=$r['sagsnr']*1;
				$r2=db_fetch_array(db_select("select nr from opgaver where id='$opgave_id'",__FILE__ . " linje " . __LINE__));
				$opg_nr=$r2['nr']*1;
				$s_sag_nr="$sagnr-$opg_nr";
				$_SESSION['s_sag_nr']=$s_sag_nr;
			}
			if (($sag_id=if_isset($_GET['sag_id'])) && (!$opgave_id=if_isset($_GET['opgave_id'])) && ($loendate=if_isset($_GET['loendate'])) && ($loendateFra=if_isset($_GET['loendateFra'])) && (!$loendateTil=if_isset($_GET['loendateTil']))) {
				$r=db_fetch_array(db_select("select sagsnr from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
				$s_sag_nr=$r['sagsnr']*1;
				$_SESSION['s_sag_nr']=$s_sag_nr;
				$s_loendate=$loendate;
				$s_loendateFra=$loendateFra;
				$_SESSION['s_loendate']=$s_loendate;
				$_SESSION['s_loendateFra']=$s_loendateFra;
				unset($_SESSION['s_loendateTil'],$s_loendateTil);
				//echo "Fra";
			}
			if (($sag_id=if_isset($_GET['sag_id'])) && (!$opgave_id=if_isset($_GET['opgave_id'])) && ($loendate=if_isset($_GET['loendate'])) && ($loendateFra=if_isset($_GET['loendateFra'])) && ($loendateTil=if_isset($_GET['loendateTil']))) {
				$r=db_fetch_array(db_select("select sagsnr from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
				$s_sag_nr=$r['sagsnr']*1;
				$_SESSION['s_sag_nr']=$s_sag_nr;
				$s_loendate=$loendateTil;
				$s_loendateFra=$loendateFra;
				$s_loendateTil=$loendateTil;
				$_SESSION['s_loendate']=$s_loendate;
				$_SESSION['s_loendateFra']=$s_loendateFra;
				$_SESSION['s_loendateTil']=$s_loendateTil;
				//echo "Fra Til";
			}
			
			$s_sag_nr=if_isset($_POST['s_sag_nr']);
			(!$s_sag_nr)?$s_sag_nr=if_isset($_SESSION['s_sag_nr']):$_SESSION['s_sag_nr']=$s_sag_nr; //  && $s_sag_nr!=NULL
			
			if (!$loendate) $loendate=if_isset($_POST['s_loendate']);
			if (!$loendateFra) $loendateFra=if_isset($_POST['s_loendateFra']);
			if (!$loendateTil) $loendateTil=if_isset($_POST['s_loendateTil']);
			//echo "ld1 $loendate<br>ldF1 $loendateFra<br>ldT1 $loendateTil<br><br>";
			//if (!$loendateTil) unset($_SESSION['s_loendateTil'],$loendateTil);
			(!$loendate)?$loendate=if_isset($_SESSION['s_loendate']):$_SESSION['s_loendate']=$loendate;
			(!$loendateFra)?$loendateFra=if_isset($_SESSION['s_loendateFra']):$_SESSION['s_loendateFra']=$loendateFra;
			(!$loendateTil)?$loendateTil=if_isset($_SESSION['s_loendateTil']):$_SESSION['s_loendateTil']=$loendateTil;
			if ($_POST['s_loendate'] && $_POST['s_loendateFra'] && !$_POST['s_loendateTil']) $loendateTil=NULL;
			//echo "s_sag_nr: $s_sag_nr<br>";
			/*
			if (!$_GET['loendate']) {
				echo "NO_GET_LD<br>";
				echo "P_ld $_POST[s_loendate]<br>";
				echo "P_ldF $_POST[s_loendateFra]<br>";
				echo "P_ldT $_POST[s_loendateTil]<br>";
				echo "ld $loendate<br><br>";
				if (!$_POST['s_loendate']) {
					//$loendate=NULL;$loendateFra=NULL;$loendateTil=NULL;
					echo "NO_LD2<br>";
					echo "SS_ld $_SESSION[s_loendate]<br>";
					echo "SS_ldF $_SESSION[s_loendateFra]<br>";
					echo "SS_ldT $_SESSION[s_loendateTil]<br><br>";
				}
			}
			*/
			
			//echo "ld2 $loendate<br>ldF2 $loendateFra<br>ldT2 $loendateTil<br>";
			//echo "s_loendate $s_loendate s_loendateFra $s_loendateFra s_loendateTil $s_loendateTil<br>";
			//echo "SessionLøndato: $_SESSION[s_loendate] SessionFra: $_SESSION[s_loendateFra] SessionTil: $_SESSION[s_loendateTil]<br>";
			
			$unsetsort=if_isset($_GET['unsetsort']);
			if ($unsetsort) {
				unset($_SESSION['s_sag_nr'],$s_sag_nr);
			}
			/*
			echo "opgnr=$opg_nr<br>";
			echo "sagid=$sag_id<br>";
			echo "sagnr_post=$s_sag_nr<br>";
			echo "sagnr_sess=".$_SESSION['s_sag_nr']."<br>";
			*/
			if ($s_sag_nr) { #20140104-2
				if ((strpos($s_sag_nr,'-') !== false) && is_numeric(substr($s_sag_nr, strpos($s_sag_nr, "-") + 1))) {
					list($sagnr, $opgnr) = explode("-", "$s_sag_nr", 2);
					if ($sagnr!=NULL) {
						$r=db_fetch_array(db_select("select id from sager where sagsnr='$sagnr'",__FILE__ . " linje " . __LINE__));
						$sag_id=$r['id']*1;
						$r2=db_fetch_array(db_select("select id from opgaver where assign_id='$sag_id' and assign_to='sager' and nr='$opgnr'",__FILE__ . " linje " . __LINE__));
						$opgave_id=$r2['id']*1;
					}
				} elseif (is_numeric($s_sag_nr)) {
					$r=db_fetch_array(db_select("select id from sager where sagsnr='$s_sag_nr'",__FILE__ . " linje " . __LINE__));
					$sag_id=$r['id']*1;
				} 
			}
			/*
			if ($s_loendate) {
				if ($s_loendateFra && $s_loendateTil) {
					echo "FRA $s_loendateFra og TIL $s_loendateTil<br>";
				} else {
					echo "FRA $s_loendateFra<br>";
				}
			}
			*/
			if ($vis=='alle' || $unsetsort) {
				unset($_SESSION['overforte'],$_SESSION['godkendte'],$_SESSION['afviste'],$_SESSION['betalte']);
			}
			if ($vis=='overforte') {
				unset($_SESSION['godkendte'],$_SESSION['afviste'],$_SESSION['betalte']);
			}
			if ($vis=='godkendte') {
				unset($_SESSION['overforte'],$_SESSION['afviste'],$_SESSION['betalte']);
			}
			if ($vis=='afviste') {
				unset($_SESSION['overforte'],$_SESSION['godkendte'],$_SESSION['betalte']);
			}
			if ($vis=='betalte') {
				unset($_SESSION['overforte'],$_SESSION['godkendte'],$_SESSION['afviste']);
			}
			elseif (isset($_SESSION['overforte']) && $_SESSION['overforte']) $vis=$_SESSION['overforte'];
			elseif (isset($_SESSION['godkendte']) && $_SESSION['godkendte']) $vis=$_SESSION['godkendte'];
			elseif (isset($_SESSION['afviste'])   && $_SESSION['afviste'])   $vis=$_SESSION['afviste'];
			elseif (isset($_SESSION['betalte'])   && $_SESSION['betalte'])   $vis=$_SESSION['betalte'];
			
			
			//echo "$_SESSION[betalte]<br>";echo "vis: $vis<br>";echo "unset: $unsetsort<br>";
			//echo "sag_id: $sag_id<br>";
			//echo "P_sagnr $_POST[s_sag_nr]";
// Udbetalt 
// Afviste 
//	heunder perioder
			print "<li><a href=\"loen.php?funktion=ret_loen&amp;id=0\">Ny lønseddel</a></li>";
			if ($vis!='overforte') print "<li><a href=\"loen.php?funktion=loenliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=overforte\">Overførte sedler</a></li>";
			if ($vis!='afviste') print "<li><a href=\"loen.php?funktion=loenliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=afviste\">Afviste sedler</a></li>";
			if ($vis!='betalte') print "<li><a href=\"loen.php?funktion=loenliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=betalte\">Udbetalte</a></li>";
			if ($vis!='godkendte') print "<li><a href=\"loen.php?funktion=loenliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=godkendte\">Til udbetaling</a></li>";
			if ($vis!=NULL && $vis!='alle') print "<li><a href=\"loen.php?funktion=loenliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=alle\">Vis alle</a></li>";
			if (substr($sag_rettigheder,6,1)) {
				print "<li><a href=\"loen.php?funktion=loenafregning&amp;refresh=on\">Lønafregning</a></li>";
#				print "<li><a href=\"loen.php?funktion=d_loenafregning\">Løn (datoindelt)</a></li>";
				print "<li><a href=\"loen.php?funktion=satser\">Ret satser mm.</a></li>";
			} else {
				print "<li><a href=\"loen.php?funktion=loenafregning&amp;refresh=on\">Lønafregning</a></li>";
			}
			if (substr($sag_rettigheder,3,1)) {
				print "<li><hr></li>";
				print "<li><a href=\"ansatte.php\">Til ansatte</a></li>";
				if ($sag_id) {
					print "<li id=\"sagidLink\"><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">Til sag</a></li>";
				}
				if ($opgave_id) {
					print "<li id=\"opgaveLink\"><a href=\"sager.php?funktion=ret_opgave&amp;sag_id=$sag_id&amp;opgave_id=$opgave_id\">Til opgave</a></li>";
				}
				if ($loendate && $sag_id) {
					print "<li id=\"akkordlisteLink\"><a href=\"sager.php?funktion=akkordliste&amp;sag_id=$sag_id&amp;opg_id=$opgave_id&amp;akkordfraSoeg=$loendateFra&amp;akkordtilSoeg=$loendateTil\">Til akkordlister</a></li>";
				}
			}
		} elseif ($funktion=='loenafregning') {
			
			$periode=if_isset($_POST['periode']);
			if (!$periode) $periode=if_isset($_GET['periode']);
			$ansatte_id=if_isset($_GET['ansat_id']);
			$alle_ansatte_id=if_isset($_POST['alle_ansatte_id']);
			if (!$alle_ansatte_id) $alle_ansatte_id=if_isset($_GET['alle_ansatte_id']);
			$visalle=if_isset($_GET['visalle']);
			$refresh=if_isset($_GET['refresh']);
			//echo "periode: $periode<br>";
			//echo "visalle: $visalle<br>";
			//echo "refresh: $refresh<br>";
			//echo "alleid: $alle_ansatte_id<br>";
			//echo "ansatid: $ansatte_id<br>";
			if (!$ansatte_id && (!$visalle=='on')) {
				print "<span class=\"loenafregningVis\"><span>"; // Her bliver resultat fra ajax_loenafregning.php skrevet
				print "<li><a href=\"loen.php?funktion=loenafregning&amp;periode=$periode&amp;alle_ansatte_id=$alle_ansatte_id\">Lønafregning (Timer)</a></li>";
				print "<li><a href=\"loen.php?funktion=loenafregning&amp;vis=belob&amp;periode=$periode&amp;alle_ansatte_id=$alle_ansatte_id\">Lønafregning (Beløb)</a></li>";
				if ($alle_ansatte_id && (!$refresh=='on') && (substr($sag_rettigheder,3,1))) print "<li><a href=\"loen.php?funktion=loenafregning&amp;vis=$vis&amp;alle_ansatte_id=".rtrim($alle_ansatte_id, ",")."&amp;periode=$periode&amp;visalle=on\">Lønopgørelse (Alle&nbsp;ansatte)</a></li>";
			} elseif ($visalle=='on') {
				print "<li><a href=\"loen.php?funktion=loenafregning&amp;alle_ansatte_id=$alle_ansatte_id&amp;visalle=$visalle&amp;periode=$periode\">Lønafregning alle (Timer)</a></li>";
				print "<li><a href=\"loen.php?funktion=loenafregning&amp;vis=belob&amp;alle_ansatte_id=$alle_ansatte_id&amp;visalle=$visalle&amp;periode=$periode\">Lønafregning alle (Beløb)</a></li>";
				print "<li><a href=\"loen.php?funktion=loenafregning&amp;vis=$vis&amp;alle_ansatte_id=$alle_ansatte_id&amp;periode=$periode\">Lønafregning</a></li>";
			} else {
				print "<li><a href=\"loen.php?funktion=loenafregning&amp;ansat_id=$ansatte_id&amp;periode=$periode\">Lønafregning ansat (Timer)</a></li>";
				print "<li><a href=\"loen.php?funktion=loenafregning&amp;vis=belob&amp;ansat_id=$ansatte_id&amp;periode=$periode\">Lønafregning ansat (Beløb)</a></li>";
				if (substr($sag_rettigheder,3,1)) print "<li><a href=\"loen.php?funktion=loenafregning&amp;vis=$vis&amp;periode=$periode&amp;refresh=on\">Lønafregning</a></li>";
			}
				print "<li><a href=\"loen.php?funktion=loenliste\">L&oslash;nliste</a></li>";
				if (substr($sag_rettigheder,3,1)) {
					print "<li><hr></li>";
					print "<li><a href=\"ansatte.php\">Til ansatte</a></li>";
				}
		} else {
			print "<li><a href=\"loen.php?funktion=loenliste\">L&oslash;nliste</a></li>";
				if (substr($sag_rettigheder,3,1)) {
					print "<li><hr></li>";
					print "<li><a href=\"ansatte.php\">Til ansatte</a></li>";
				}
			}
		print "</ul>";
	 ################################## MENU ANSATTE ###############################
	} elseif ($menu_ansatte) {
		print "<div class=\"leftmenuhead link\">Ansatte</div>";
		print "<ul>";
		if ($funktion=='ansatliste') {
		
			$unsetsort=if_isset($_GET['unsetsort']);
			if ($vis=='ansatteA' || $unsetsort) {
				unset($_SESSION['fratraadteA'],$_SESSION['alleA']);
			}
			if ($vis=='fratraadteA') {
				unset($_SESSION['alleA']);
			}
			if ($vis=='alleA') {
				unset($_SESSION['fratraadteA']);
			}
			
			elseif ($_SESSION['alleA']) $vis=$_SESSION['alleA'];
			elseif ($_SESSION['fratraadteA']) $vis=$_SESSION['fratraadteA'];
			
			print "<li><a href=\"ansatte.php?funktion=ret_ansat\">Opret medarbejder</a></li>";
			if ($vis!='fratraadteA') print "<li><a href=\"ansatte.php?funktion=ansatliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=fratraadteA\">Vis fratrådte</a></li>";
			if ($vis!='alleA') print "<li><a href=\"ansatte.php?funktion=ansatliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=alleA\">Vis alle</a></li>";
			if ($vis!=NULL && $vis!='ansatteA') print "<li><a href=\"ansatte.php?funktion=ansatliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=ansatteA\">Vis ansatte</a></li>";
			print "<li><a href=\"ansatte.php?funktion=brugergrupper\">Brugergrupper</a></li>";
			print "<li><a href=\"ansatte.php?funktion=stamkort\">Tilret stamkort</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"loen.php\">Til Løn</a></li>";
		} elseif ($funktion=='ret_ansat') {
			print "<li><a href=\"ansatte.php?funktion=ret_ansat\">Opret medarbejder</a></li>";
			print "<li><a href=\"ansatte.php?funktion=ansatliste\">Medarbejderliste</a></li>";
			print "<li><a href=\"ansatte.php?funktion=brugergrupper\">Brugergrupper</a></li>";
			print "<li><a href=\"ansatte.php?funktion=stamkort\">Tilret stamkort</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"loen.php\">Til Løn</a></li>";
		} elseif ($funktion=='stamkort') {
			print "<li><a href=\"ansatte.php?funktion=ret_ansat\">Opret medarbejder</a></li>";
			print "<li><a href=\"ansatte.php?funktion=ansatliste\">Medarbejderliste</a></li>";
			print "<li><a href=\"ansatte.php?funktion=brugergrupper\">Brugergrupper</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"loen.php\">Til Løn</a></li>";
		} elseif ($funktion=='brugergrupper') {
			print "<li><a href=\"ansatte.php?funktion=ret_ansat\">Opret medarbejder</a></li>";
			print "<li><a href=\"ansatte.php?funktion=ansatliste\">Medarbejderliste</a></li>";
			print "<li><a href=\"ansatte.php?funktion=stamkort\">Tilret stamkort</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"loen.php\">Til Løn</a></li>";
		}	
		print "</ul>";
	################################## MENU CERTIFICERING ############################
	} elseif ($menu_certificering) {
	
		$x=0; #22052014
		$q=db_select("SELECT tjekpunkt,fase FROM tjekliste WHERE assign_to = 'sager' AND assign_id = '0' ORDER BY fase ASC",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$fase[$x]=$r['fase'];
			$skemanavn[$x]=$r['tjekpunkt'];
		}
		$antal_fase=$x;
		
		print "<div class=\"leftmenuhead link\">Certificering</div>";
		print "<ul>";
		if ($funktion=='kontrolskemaliste') {
			for ($x=1;$x<=$antal_fase;$x++) {
				if ($vis!=$fase[$x]) print "<li><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=$fase[$x]\">$skemanavn[$x]</a></li>";
			}
			/*
			if ($vis!='1') print "<li><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=1\">Arbejdssedler</a></li>";
			if ($vis!='2') print "<li><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=2\">Arbejdsmiljøkontrol</a></li>";
			if ($vis!='3') print "<li><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=3\">Løbende kontrol</a></li>";
			*/
			if ($vis!=NULL && $vis!='alle') print "<li><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=alle\">Vis alle</a></li>";
		} elseif ($funktion=='vis_arbejdsseddel') {
			print "<li><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste\">Skemaliste</a></li>";
			print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">Til sag</a></li>";
		} elseif ($funktion=='vis_kontrolskema') {
			print "<li><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste\">Skemaliste</a></li>";
			print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">Til sag</a></li>";
		}
		print "</ul>";
	################################## MENU MEDARBEJDERMAPPE ############################
	}	elseif ($menu_medarbejdermappe) {
	
		$x=0; 
		$q=db_select("SELECT tjekpunkt,fase FROM tjekliste WHERE fase != '1' AND assign_to = 'sager' AND assign_id = '0' ORDER BY fase ASC",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$fase[$x]=$r['fase'];
			$skemanavn[$x]=$r['tjekpunkt'];
		}
		$antal_fase=$x;
		
		/*
		$tmp1=$ansat_id.chr(59);
		$tmp2=chr(59).$ansat_id;
		$tmp3=chr(59).$ansat_id.chr(59);
		$where = "(tjekskema.sjakid LIKE '$tmp1%' or tjekskema.sjakid LIKE '%$tmp2' or tjekskema.sjakid LIKE '%$tmp3%' or tjekskema.sjakid = '$ansat_id')";
	
		$x=0;
		$q=db_select("SELECT tjekliste.id, tjekliste.tjekpunkt, tjekliste.fase, tjekskema.tjekliste_id, tjekskema.sjakid FROM tjekliste 
									INNER JOIN tjekskema ON tjekliste.id = tjekskema.tjekliste_id
									WHERE fase != '1' AND assign_to = 'sager' AND assign_id = '0' AND $where ORDER BY fase ASC",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$fase[$x]=$r['fase'];
			$skemanavn[$x]=$r['tjekpunkt'];
		}
		$antal_fase=$x;
		*/
		print "<div class=\"leftmenuhead link\">Medarbejdermappe</div>";
		print "<ul>";
		if ($funktion=='kontrolseddelskemaliste') {
			for ($x=1;$x<=$antal_fase;$x++) {
				if ($vis!=$fase[$x]) print "<li><a href=\"mm_kontrolskemaer.php?funktion=kontrolseddelskemaliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=$fase[$x]\">$skemanavn[$x]</a></li>";
			}
			/*
			if ($vis!='1') print "<li><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=1\">Arbejdssedler</a></li>";
			if ($vis!='2') print "<li><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=2\">Arbejdsmiljøkontrol</a></li>";
			if ($vis!='3') print "<li><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=3\">Løbende kontrol</a></li>";
			*/
			if ($vis!=NULL && $vis!='alle') print "<li><a href=\"mm_kontrolskemaer.php?funktion=kontrolseddelskemaliste&amp;sort=$sort&amp;nysort=$nysort&amp;vis=alle\">Vis alle</a></li>";
			print "<li><hr></li>";
			print "<li><a href=\"medarbejdermappe.php\">Medarbejdermappe</a></li>";
			print "<li><a href=\"loen.php\">Til Løn</a></li>";
		} elseif ($funktion=='arbejdsseddelskemaliste') {
			print "<li><a href=\"medarbejdermappe.php\">Medarbejdermappe</a></li>";
			//print "<li><hr></li>";
			print "<li><a href=\"loen.php\">Til Løn</a></li>";
		} elseif ($funktion=='vis_arbejdsseddel') {
			print "<li><a href=\"mm_kontrolskemaer.php?funktion=arbejdsseddelskemaliste\">Skemaliste</a></li>";
			if (substr($sag_rettigheder,3,1)) print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">Til sag</a></li>";
			//print "<li><hr></li>";
			print "<li><a href=\"loen.php\">Til Løn</a></li>";
		} elseif ($funktion=='vis_kontrolskema') {
			print "<li><a href=\"mm_kontrolskemaer.php?funktion=kontrolseddelskemaliste\">Skemaliste</a></li>";
			if (substr($sag_rettigheder,3,1)) print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">Til sag</a></li>";
			//print "<li><hr></li>";
			print "<li><a href=\"loen.php\">Til Løn</a></li>";
		}
		print "</ul>";
	}
	print "</div><!-- end of leftmenu -->";
/*
<div class=\"leftmenu\">
	<ul>
		<li><a href=\"#\">underlink 1</a></li>
		<li><a href=\"#\">underlink 2</a></li>
		<li><a href=\"#\">underlink 3</a></li>
		<li><a href=\"#\">underlink 4</a></li>
		<li><a href=\"#\">underlink 5</a></li>
	</ul>
	<hr> 
	<ul>
		<li><a href=\"#\">underlink 1</a></li>
		<li><a href=\"#\">underlink 2</a></li>
		<li><a href=\"#\">underlink 3</a></li>
		<li><a href=\"#\">underlink 4</a></li>
		<li><a href=\"#\">underlink 5</a></li>
	</ul>
	</div><!-- end of leftmenu -->"
*/
?>
