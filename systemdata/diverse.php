<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverse.php -----patch 4.1.0 ----2024-05-22------------
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
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// 2012.09.20 Tilføjet integration med ebconnect
// 2013.01.19 funktioner lagt i selvstændig fil (../includes/sys_div_func.php)
// 2013.05.23 varelaterede rettet til varerelaterede valg.
// 2013.12.10	Tilføjet valg om kort er betalingskort som aktiver betalingsterminal. Søg 20131210
// 2013.12.13	Tilføjet "intern" bilagsopbevaring (box6 under ftp)
// 2014.01.29	Tilføjet valg til automatisk genkendelse af betalingskort (kun ved integreret betalingsterminal) Søg 20140129
// 2014.04.29	Ændret teksten så siden er mere overskuelig. Claus Agerskov ca@saldi.dk
// 2014.05.08	Tilføjet valg til bordhåndtering under pos_valg Søg 20140508
// 2014.06.16 Tilføjet mellemkonto til pos kasser. Søg mellemkonto.
// 2014.07.01	FTP ændret til bilag og intern bilagsopbevaring flyttet til owncloud
// 2015.04.11	Tilføjet labelprint.Søg label
// 20150424 CA  Ændret link til funktionsfilen sys_div_func.php    Søg 20150424a
// 20150424 CA  Ændret link til funktionsfilen konv_lager.php      Søg 20150424b
// 20150424 CA  Benytter funktionen skriv_formtabel til formularer Søg 20150424c
// 20150612 CA  Databasehåndtering af prislister (ej afsluttet)    Søg 20150612
// 20150907 PHR Sætpriser tilføjet under ordre_valg, Søg 20150907 & $saetvareid
// 20151006 PHR Labelprint ændret fra php til html og kontrol for php indsat.
// 20160116 PHR Indsat kontrol for ftp adgang v ebconnect integration
// 20160412 PHR Opdelt vare_valg i vare_valg, labels & shop_valg
// 20160601	PHR SMTP kan nu anvendes med brugernavn, adgangskode og kryptering.
// 20161118	PHR	Tilføjet default bord som option for kasse i funktion pos_valg. Søg bordvalg
// 20161125 PHR Indført html som formulargenerator som alternativ til postscript i funktion div_. Søg pv_box3
// 20170123 PHR Tilføjet API_valg
// 20170314 PHR POS Valg - tilføjet mulighed for at sætte 'udtages fra kasse' til 0 som default.
// 20170404 PHR ordre_valg - Straksbogfør skelner nu mellem debitor og kreditorordrer. Dvs debitor;kreditor - Søg # 20170404
// 20170731 PHR Tilføjet 'Nulstil regnskab - 20170731
// 20171009 PHR Tilføjet pos_font_size under pos_valg.
// 20181029 CA  Tilføjet voucher og tilgodehavende tilknyttet id  søg 20181029
// 20181126 PHR	Variant_valg lagt i egen funktion.
// 20181126 PHR	Tilvalg - Marker vare som udgået når beholdning går i minus (vare_valg). Søg DisItemIfNeg
// 20181129 PHR	Tilføjet mulighed for at sætte tidszone i regnskabet.
// 20181216 PHR	Tilføjet 'card_enabled' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$card_enabled'
// 20190107 PHR	Tilføjet 'change_cardvalue' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$change_cardvalue'
// 20190129 PHR	(vare_valg) Changed 'Momskode for salgspriser på varekort' to 'Vis priser med moms på varekort'. Search '$vatOnItemCard'
// 20190225 MSC Rettet topmenu design og isset fejl
// 20190322 LN Added tables to be deleted when pressing the "Nulstil" button
// 20190411 LN Call funtion in chooseProvision.php to save default provision value
// 20190421 PHR Added confirmDescriptionChange, in 'vare_valg'
// 20190614 LN Added argument to the function saveProvisionForItemGroup -> $defaultProvision
// 20200827 PHR Added shop_varer & shop_addresser to truncate in 'Nulstil'
// 20190921 PHR Added confirmStockChange, in 'vare_valg'
// 20201128 PHR Added labelType in.  'Label'
// 20210110 PHR Section Vare_valg. Added commission. 
// 20210213 PHR Some cleanup
// 20210303 CA  Added reservation of consignment for Danske Fragtmænd - search dfm_
// 20210305 CA  Added the selection to use debtor number as phone number in orders - search debtor2orderphone
// 20210312 PHR Changed intern_ftp til internFTP
// 20210410 PHR Correction of minor error in alertcondition in 'vare_valg'.
// 20210513 LOE	These texts were translated but not entered here previously
// 20210801 CA  Added the selection to use order notes in ordre_valg - search orderNoteEnabled
// 20210802 LOE Translated the remaining alert texts
// 20211123 PHR added paperflow
// 20211123 PHR added paperflowId & paperflowBearer
// 20220514 PHR mailText is now removed when account is reset.  
// 20221231 PHR	sektion 'bilag' box3 (ftp passwd) is now urlencoded as it failed with special characters in password. 
// 20231228 PBLM Added mobilePay (diverse valg)
// 20240126 PBLM Added nemhandel (diverse valg)
// 05-01-2025 PBLM Added a second file to api_valg
// 20241220 LOE Initialized tenantID and $key to null if not set

@session_start();
$s_id=session_id();
ob_start();

$title="Diverse Indstillinger";
$modulnr=1;
$css="../css/standard.css";
$diffkto=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("sys_div_func.php"); # 20150424a
include("skriv_formtabel.inc.php"); # 20150424c

$defaultProvision=$sqlstreng=NULL;
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
#	print "<span class=\"headerTxt\">Systemsetup</span>\n";
#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green small right\">Ny debitor</a>--></div>";
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_div_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
} elseif ($menu == 'S') {
	/*print "<script>
	if(window.self == window.top) {
	//run this code if in an iframe
	// alert('in frame');
	parent.location.href = \"../index/main.php\";
	} 
	</script>";*/
#	print "<script>try {parent.location.href = '../index/main.php'} catch {window.location.href =	 '../index/main.php'}</script>";
#	die();
	include("top.php");
} else {
	print "<script>
	if(window.self !== window.top) {
	//run this code if in an iframe
	// alert('in frame');
	parent.location.href = \"../index/menu.php\";
	} 
	</script>";
	include("oldTop.php");
} 

if (!isset($exec_path)) $exec_path="/usr/bin";

$sektion=if_isset($_GET['sektion']);
#if ($sektion == 'personlige_valg') $sektion = 'userSettings';
$skiftnavn=if_isset($_GET['skiftnavn']);
if ($_POST) {
	if ($sektion=='provision') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3'];
		$box4=$_POST['box4'];
		if (($id==0) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='1'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
		db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4) values ('Provisionsrapport', '1', 'DIV', '$box1', '$box2', '$box3', '$box4')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) db_modify("update grupper set  box1 = '$box1', box2 = '$box2', box3 = '$box3' , box4 = '$box4' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
		
	#######################################################################################
	} elseif ($sektion=='personlige_valg') {
		$refresh_opener = NULL;
		$id             = $_POST['id'];
		$jsvars         = $_POST['jsvars'];
		$popup          = if_isset($_POST['popup']);
		if ($popup && $_POST['popup']=='') $refresh_opener="on";
		$menu           = $_POST['menu'];
		if ($menu=="sidemenu") $menu='S';
		elseif ($menu=="topmenu") $menu='T';
		else $menu      = '';
		$bgcolor        = "#".$_POST['bgcolor'];
		$nuance         = $_POST['nuance'];
		$qtxt = "select id from grupper WHERE art = 'USET' and kodenr='$bruger_id'";
		if  (($id==0) && ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))) {
			$id=$r['id'];
		} elseif ($id==0) {
			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5) values ";
			$qtxt.= "('Personlige valg','$bruger_id','USET','$jsvars','$popup','$menu','$bgcolor','$nuance')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} elseif ($id>0) {
			$qtxt = "update grupper set box1='$jsvars',box2='$popup',box3='$menu',box4='$bgcolor',box5='$nuance' WHERE id = '$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		if ($refresh_opener) {
			print "<BODY onLoad=\"javascript:opener.location.reload();\">";
		}
	#######################################################################################
	} elseif ($sektion=='div_valg') {
		$id      = (int)$_POST['id'];
		$box1    = $_POST['box1']; #gruppevalg
		$box2    = $_POST['box2']; #kuansvalg
		$box3    = $_POST['box3']; #extra_ansat
		$box4    = $_POST['box4']; #forskellige_datoer
		$box5    = $_POST['box5']; #debtor2orderphone
		$box6    = $_POST['box6']; #docubizz
		$box7    = $_POST['box7']; #jobkort
		$box8    = $_POST['box8']; #ebconnect
		$box9    = $_POST['box9']; #ledig
		$box10   = $_POST['box10']; #betalingsliste
		$box12   = $_POST['box12'];
		$pv_box1 = $_POST['pv_box1']; #Direkte print til lokal printer
		$pv_box3            = $_POST['pv_box3']; #formulargenerator html/ps
		$gls_id             = $_POST['gls_id'];
		$gls_user           = if_isset($_POST['gls_user']);
		$gls_pass           = if_isset($_POST['gls_pass']);
		$gls_ctId           = if_isset($_POST['gls_ctId']);
		$dfm_id             = $_POST['dfm_id'];
		$dfm_user           = if_isset($_POST['dfm_user']);
		$dfm_pass           = if_isset($_POST['dfm_pass']);
		$dfm_agree          = if_isset($_POST['dfm_agree']);
		$dfm_hub            = if_isset($_POST['dfm_hub']);
		$dfm_ship           = if_isset($_POST['dfm_ship']);
		$dfm_good           = if_isset($_POST['dfm_good']);
		$dfm_pay            = if_isset($_POST['dfm_pay']);
		$dfm_url            = if_isset($_POST['dfm_url']);
		$dfm_gooddes        = if_isset($_POST['dfm_gooddes']);
		$dfm_sercode        = if_isset($_POST['dfm_sercode']);
		$dfm_pickup_addr    = if_isset($_POST['dfm_pickup_addr']);
		$dfm_pickup_name1   = if_isset($_POST['dfm_pickup_name1']);
		$dfm_pickup_name2   = if_isset($_POST['dfm_pickup_name2']);
		$dfm_pickup_street1 = if_isset($_POST['dfm_pickup_street1']);
		$dfm_pickup_street2 = if_isset($_POST['dfm_pickup_street2']);
		$dfm_pickup_town    = if_isset($_POST['dfm_pickup_town']);
		$dfm_pickup_zipcode = if_isset($_POST['dfm_pickup_zipcode']);
		$mySale             = if_isset($_POST['mySale']);
		$mySaleLabel        = if_isset($_POST['mySaleLabel']);
		$paperflow          = if_isset($_POST['paperflow']);
		$paperflowId        = if_isset($_POST['paperflowId']);
		$paperflowBearer    = if_isset($_POST['paperflowBearer']);
		$qp_agreement_id    = if_isset($_POST['qp_agreement_id']);
		$qp_merchant        = if_isset($_POST['qp_merchant']);
		$qp_md5secret       = if_isset($_POST['qp_md5secret']);
		$qp_itemGrp         = if_isset($_POST['qp_itemGrp']);
		$vibrant_api        = if_isset($_POST['vibrant_id']);

		$mobilepay_client_id      = if_isset($_POST['mobilepay_client_id'],"");
		$mobilepay_client_secret  = if_isset($_POST['mobilepay_client_secret'],"");
		$mobilepay_subscription   = if_isset($_POST['mobilepay_subscription'],"");
		$mobilepay_msn            = if_isset($_POST['mobilepay_msn'],"");

		$copay_api          = if_isset($_POST['copay_id']);
		$nemhandel          = if_isset($_POST['nemhandel']);

		$labelsize          = if_isset($_POST['labelsize']);
    
		# Vibrant API save
		if ($vibrant_api) {
			update_settings_value("vibrant_auth", "globals", $vibrant_api, "The vibrant API key");
		}

	 	update_settings_value("labelsize", "mysale", $labelsize, "The maxlength of the labels in mysale");

		#mobilePay
		if ($mobilepay_client_id) {
			update_settings_value("client_id",       "mobilepay", $mobilepay_client_id,     "The client id provided for the mobile pay integration");
			update_settings_value("client_secret",   "mobilepay", $mobilepay_client_secret, "The client secret provided for the mobile pay integration");
			update_settings_value("subscriptionKey", "mobilepay", $mobilepay_subscription,  "The Ocp-Apim-Subscription-Key provided for the mobile pay integration");
			update_settings_value("MSN",             "mobilepay", $mobilepay_msn,           "The Merchant-Serial-Number provided for the mobilepay intergreation");
		}


    # Copayone API save
    if ($copay_api) {
      # Expect a posted ID
      $qtxt = "SELECT var_value FROM settings WHERE var_name='copayone_auth'";
      $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

      # If the row already exsists
      if ($r) {
        $qtxt = "UPDATE settings SET var_value='$copay_api' WHERE var_name='copayone_auth'";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
      # If the row needs to be created in the database
      } else {
        $qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description) VALUES ('copayone_auth', 'globals', '$copay_api', 'The copayone API key')";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
      }
    }

		if ($box8) {
			ftptest($_POST['oiourl'],$_POST['oiobruger'],$_POST['oiokode']);
			$box8=$_POST['oiourl'].chr(9).$_POST['oiobruger'].chr(9).$_POST['oiokode'];
		}
		if (($id==0) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='2'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		if ($id==0){
		db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12) values ('Div_valg','2','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10','$box11','$box12')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
		}
		if ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'PV' and kodenr='1'",__FILE__ . " linje " . __LINE__))) {
			$id=$r['id'];
			$qtxt="update grupper set  box1='$pv_box1', box3='$pv_box3' WHERE id = '$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			$qtxt="insert into grupper (beskrivelse,kodenr,art,box1,box2,box3) values ('Udskrift','1','PV','$pv_box1','','$pv_box3')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$var_name=array('gls_id','gls_user','gls_pass','gls_ctId');
		$var_value=array("$gls_id","$gls_user","$gls_pass","$gls_ctId");
		$var_description=array('GLS id','GLS brugernavn','GLS password','GLS kontakt ID');
		for ($x=0;$x<count($var_name);$x++){
			$var_description[$x].=', used at GLS integration';
			$qtxt="select id from settings where var_grp='GLS' and var_name='$var_name[$x]'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="update settings set var_value='$var_value[$x]' where id='$r[id]'";
			} elseif ($var_value[$x]) {
				$qtxt="insert into settings (var_grp,var_name,var_value,var_description,user_id) values ";
				$qtxt.="('GLS','$var_name[$x]','$var_value[$x]','$var_description[$x]','0')";
			} else $qtxt = NULL;
			if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$var_name=array('dfm_id','dfm_user','dfm_pass','dfm_agree','dfm_hub','dfm_ship','dfm_good','dfm_pay','dfm_url','dfm_gooddes','dfm_sercode',
			'dfm_pickup_addr','dfm_pickup_name1','dfm_pickup_name2','dfm_pickup_street1','dfm_pickup_street2','dfm_pickup_town','dfm_pickup_zipcode');
		$var_value=array("$dfm_id","$dfm_user","$dfm_pass","$dfm_agree","$dfm_hub","$dfm_ship","$dfm_good","$dfm_pay","$dfm_url","$dfm_gooddes","$dfm_sercode",
			"$dfm_pickup_addr","$dfm_pickup_name1","$dfm_pickup_name2","$dfm_pickup_street1","$dfm_pickup_street2","$dfm_pickup_town","$dfm_pickup_zipcode");
		$var_description=array('DFM id','DFM brugernavn','DFM password','DFM aftalenummer','DFM hub','DFM standardshippingmetode',
			'DFM standardgodstype','DFM standardbetalingsmetode',"DFM API URL","DFM standardgodsbeskrivelse","DFM standardleveringsmetode",
			'DFM legal address is not the pickup addree','DFM pickup name1','DFM pickup name2','DFM pickup street1','DFM pickup street2','DFN pickup town','DFM pickup zipcode');
		for ($x=0;$x<count($var_name);$x++){
			$var_description[$x].=', used at DFM integration';
			$qtxt="select id from settings where var_grp='GLS' and var_name='$var_name[$x]'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="update settings set var_value='". db_escape_string($var_value[$x]) ."' where id='$r[id]'";
			} elseif ($var_value[$x]) {
				$qtxt="insert into settings (var_grp,var_name,var_value,var_description,user_id) values ";
				$qtxt.="('GLS','$var_name[$x]','". db_escape_string($var_value[$x]) ."','$var_description[$x]','0')";
			} else $qtxt = NULL;
			if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$var_name=array('qp_agreement_id','qp_merchant','qp_md5secret','qp_itemGrp');
		$var_value=array($qp_agreement_id,$qp_merchant,$qp_md5secret,$qp_itemGrp);
		$var_description=array('Agreement id from','Merchant no from','md5secret from','Item Group in Saldi for items paid using');
		for ($x=0;$x<count($var_name);$x++){
			$var_description[$x].=', Quickpay';
			$qtxt="select id from settings where var_grp='quickpay' and var_name='$var_name[$x]'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="update settings set var_value='$var_value[$x]' where id='$r[id]'";
			} elseif ($var_value[$x]) {
				$qtxt="insert into settings (var_grp,var_name,var_value,var_description,user_id) values ";
				$qtxt.="('quickpay','$var_name[$x]','$var_value[$x]','$var_description[$x]','0')";
			} else $qtxt = NULL;
			if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$qtxt = "select id from settings where var_grp='debitor' and var_name='mySale'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { 
			$qtxt = "update settings set var_value='$mySale' where id='$r[id]'";
		} elseif ($mySale) {
			$qtxt = "insert into settings (var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.= "('debitor','mySale','$mySale','Use mySale to allow customers acces to own salesdata (provision)','0')";
		}	else $qtxt = NULL;
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);

		$qtxt = "select id from settings where var_grp='debitor' and var_name='mySaleLabel'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { 
			$qtxt = "update settings set var_value='$mySaleLabel' where id='$r[id]'";
		} elseif ($mySaleLabel) {
			$qtxt = "insert into settings (var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.= "('debitor','mySaleLabel','$mySaleLabel','Disable labels from Mysale, so that only the owner can create labels','0')";
		}	else $qtxt = NULL;
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);

		$qtxt = "select id from settings where var_grp='creditor' and var_name='paperflow'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { 
		$qtxt = "update settings set var_value='$paperflow' where id='$r[id]'";
		} elseif ($paperflow) {
			$qtxt = "insert into settings (var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.= "('creditor','paperflow','$paperflow','Use Paperflow to read text from scanned invoices','0')";
		}	else $qtxt = NULL;
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);

		$qtxt = "select id from settings where var_grp='creditor' and var_name='paperflowId'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { 
			$qtxt = "update settings set var_value='$paperflowId' where id='$r[id]'";
		} elseif ($paperflowId) {
			$qtxt = "insert into settings (var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.= "('creditor','paperflowId','$paperflowId','Id given by Paperflow','0')";
		}	else $qtxt = NULL;
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select id from settings where var_grp='creditor' and var_name='paperflowBearer'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { 
			$qtxt = "update settings set var_value='$paperflowBearer' where id='$r[id]'";
		} elseif ($paperflowBearer) {
			$qtxt = "insert into settings (var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.= "('creditor','paperflowBearer','$paperflowBearer','Bearer given by Paperflow','0')";
		}	else $qtxt = NULL;
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	#######################################################################################
	} elseif ($sektion=='ordre_valg') {
		$box1             = if_isset($_POST['box1']);#incl_moms
		$box2             = if_isset($_POST['box2']);#Rabatvarenr
		$box3             = if_isset($_POST['box3']);#folge_s_tekst
		$box4             = if_isset($_POST['box4']);#hurtigfakt
		$box5             = if_isset($_POST['straks_deb']).";".if_isset($_POST['straks_kred']);#straks_bogf
		$box6             = if_isset($_POST['box6']);#fifo
		$box7             = if_isset($_POST['box7']);#
		$box8             = if_isset($_POST['box8']);#vis_nul_lev
		$box9             = if_isset($_POST['box9']);#negativt_lager
		$box10            = if_isset($_POST['box10']);#
		$box11            = if_isset($_POST['box11']);#advar_lav_beh
		$box12            = if_isset($_POST['box12']);#$procentfakt
		$box13            = if_isset($_POST['procenttillag']).chr(9).if_isset($_POST['procentvare']);
		$box14            = if_isset($_POST['box14']);
		$rabatvarenr      = if_isset($_POST['rabatvarenr']);
		$kostmetode       = if_isset($_POST['kostmetode']);
		$saetvarenr       = if_isset($_POST['saetvarenr']); #20150907
		$orderNoteEnabled = if_isset($_POST['orderNoteEnabled']);
		$debitoripad      = if_isset($_POST['debitoripad']);
		$portovarenr      = if_isset($_POST['portovarenr']);
		update_settings_value("debitoripad", "ordre", $debitoripad, "Weather or not to include the debitor ipad system");
		update_settings_value("porto_varnr", "ordre", $portovarenr, "Varenr to autmatically include on new orders");


		if ($box2 && $r=db_fetch_array(db_select("select id from varer WHERE varenr = '$box2'",__FILE__ . " linje " . __LINE__))) {
			$box2=$r['id'];
		} elseif ($box2) {
				$txt = str_replace('XXXXX',$box2,findtekst(289,$sprog_id));
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		if ($box14 && !$box2) {
			$txt=findtekst(1875, $sprog_id); #20210820
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			$box14='';
		}
		#20150907 ->
		$saetvareid = 0;
		$qtxt = "select id from varer WHERE varenr = '$saetvarenr'";
		if ($saetvarenr && $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $saetvareid=$r['id'];
		if ($saetvarenr && !$saetvareid) {
			$txt= findtekst(1876, $sprog_id);
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
/*
		if ($kostmetode) {
			if ($r=db_fetch_array(db_select("select id from grupper WHERE art = 'VG' and box1 != box2",__FILE__ . " linje " . __LINE__))) {
				$txt = findtekst(733,$sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../systemdata/konv_lager.php\">\n"; # 20140424b
				exit;
			}
		}
*/
		# <- 20150907
		if  ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__))) {
			$id=$r['id'];
			$qtxt = "update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',";
			$qtxt.= "box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12',box13='$box13',";
			$qtxt.= "box14='$box14' WHERE id = '$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,";
			$qtxt.= "box12,box13,box14) values ('Div_valg (Ordrer)','3','DIV','$box1','$box2','$box3','$box4','$box5','$box6',";
			$qtxt.= "'$box7','$box8','$box9','$box10','$box11','$box12','$box13','$box14')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		if ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='5'",__FILE__ . " linje " . __LINE__))) {
			$id=$r['id'];
			db_modify("update grupper set box6='$kostmetode',box8='$saetvareid' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13) ";
			$qtxt.= "values ('Div_valg','5','DIV','','','','','','','$kostmetode','','','','','','')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}

		if ($r = db_fetch_array(db_select("select id from settings where var_name='orderNoteEnabled'",__FILE__ . " linje " . __LINE__))) { #20210729
			$id=$r['id'];
			$qtxt = "update settings set var_value='$orderNoteEnabled' WHERE id='$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into settings (var_name, var_value) values ('orderNoteEnabled','$orderNoteEnabled')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}


	#######################################################################################
	} elseif ($sektion=='productOptions') {

		$id                              = $_POST['id'];
		$box1                            = if_isset($_POST['box1']);#incl_moms
		$DisItemIfNeg_id                 = if_isset($_POST['DisItemIfNeg_id']);
		$DisItemIfNeg                    = if_isset($_POST['DisItemIfNeg']);
		$vatOnItemCard_id                = if_isset($_POST['vatOnItemCard_id']);
		$vatOnItemCard                   = if_isset($_POST['vatOnItemCard']);
		$useCommission                   = if_isset($_POST['useCommission']);
		$useCommissionId                 = if_isset($_POST['useCommissionId']);
		$commissionAccountNew            = if_isset($_POST['commissionAccountNew']);
		$commissionAccountNewId          = if_isset($_POST['commissionAccountNewId']);
		$commissionAccountUsed           = if_isset($_POST['commissionAccountUsed']);
		$commissionAccountUsedId         = if_isset($_POST['commissionAccountUsedId']);
		$customerCommissionAccountNew    = if_isset($_POST['customerCommissionAccountNew']);
		$customerCommissionAccountNewId  = if_isset($_POST['customerCommissionAccountNewId']);
		$customerCommissionAccountUsed   = if_isset($_POST['customerCommissionAccountUsed']);
		$customerCommissionAccountUsedId = if_isset($_POST['customerCommissionAccountUsedId']);
		$defaultCommission               = if_isset($_POST['defaultCommission']);
		$defaultCommissionId             = if_isset($_POST['defaultCommissionId']);
		$commissionInclVat               = if_isset($_POST['commissionInclVat']);
		$commissionInclVatId             = if_isset($_POST['commissionInclVatId']);
		$ownCommissionAccountNew         = if_isset($_POST['ownCommissionAccountNew']);
		$ownCommissionAccountNewId       = if_isset($_POST['ownCommissionAccountNewId']);
		$ownCommissionAccountUsed        = if_isset($_POST['ownCommissionAccountUsed']);
		$ownCommissionAccountUsedId      = if_isset($_POST['ownCommissionAccountUsedId']);
		$commissionFromDate              = if_isset($_POST['commissionFromDate']);
		$convertExisting                 = if_isset($_POST['convertExisting']);
		$confirmDescriptionChange_id     = if_isset($_POST['confirmDescriptionChange_id']);
		$confirmDescriptionChange        = if_isset($_POST['confirmDescriptionChange']);
		$confirmStockChange_id           = if_isset($_POST['confirmStockChange_id']);
		$confirmStockChange              = if_isset($_POST['confirmStockChange']);

		if ($vatOnItemCard_id) $qtxt="update settings set var_value='$vatOnItemCard' where id='$vatOnItemCard_id'";
		else {
		$qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
		$qtxt.="('items','vatOnItemCard','$vatOnItemCard','If set, salesprice will be shown including VAT on ItemCard','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($DisItemIfNeg_id) $qtxt="update settings set var_value='$DisItemIfNeg' where id='$DisItemIfNeg_id'";
		else {
			$qtxt="insert into settings (var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.="('items','DisItemIfNeg','$DisItemIfNeg',";
			$qtxt.="'If set, item will be set as discontinued when stock turns negative','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($confirmDescriptionChange_id) {
			$qtxt="update settings set var_value='$confirmDescriptionChange' ";
			$qtxt.="where id='$confirmDescriptionChange_id'";
		} else {
			$qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.="('items','confirmDescriptionChange','$confirmDescriptionChange',";
			$qtxt.="'If set, confirm and reason will be required when stock is changed on ItemCard','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($confirmStockChange_id) {
			$qtxt="update settings set var_value='$confirmStockChange' ";
			$qtxt.="where id='$confirmStockChange_id'";
		} else {
			$qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.="('items','confirmStockChange','$confirmStockChange',";
			$qtxt.="'If set, confirm will be required when description is changed on ItemCard','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($useCommissionId) {
			$qtxt="update settings set var_value='$useCommission' ";
			$qtxt.="where id='$useCommissionId'";
		} else {
			$qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.="('items','useCommission','$useCommission',";
			$qtxt.="'Commisionsale. If set, checkbox will be shown at cashCount and at itemCard','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	
		if ($commissionAccountNew) {
			$qtxt="select id from kontoplan where regnskabsaar = '$regnaar' and kontotype = 'D' and kontonr='$commissionAccountNew'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				if ($commissionAccountNewId) {
					$qtxt="update settings set var_value='$commissionAccountNew' ";
					$qtxt.="where id='$commissionAccountNewId'";
				} else {
					$qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
					$qtxt.="('items','commissionAccountNew','$commissionAccountNew',";
					$qtxt.="'Account for commisionsale, new items. If set, commmision sale income of new items, is accounted in this account','0')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else alert ("".findtekst(1709, $sprog_id)." $commissionAccountNew ".findtekst(1735, $sprog_id)." ($regnaar)");
		} elseif ($commissionAccountNewId) {
			$qtxt="update settings set var_value='' ";
			$qtxt.="where id='$commissionAccountNewId'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} 
		if ($commissionAccountUsed) {
			$qtxt="select id from kontoplan where regnskabsaar = '$regnaar' and kontotype = 'D' and kontonr='$commissionAccountUsed'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($commissionAccountUsedId) {
					$qtxt="update settings set var_value='$commissionAccountUsed' ";
					$qtxt.="where id='$commissionAccountUsedId'";
				} else {
					$qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
					$qtxt.="('items','commissionAccountUsed','$commissionAccountUsed','Account for commisionsale, used items. If set, ";
					$qtxt.="commmision sale income of used items, is accounted in this account','0')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					} else alert ("".findtekst(1709, $sprog_id)." $commissionAccountUsed ".findtekst(1735, $sprog_id)." ($regnaar)"); #20210802
		} elseif ($commissionAccountUsedId) {
			$qtxt="update settings set var_value='' ";
			$qtxt.="where id='$commissionAccountUsedId'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		if ($customerCommissionAccountNew) {
			$qtxt="select id from kontoplan where regnskabsaar = '$regnaar' and kontotype = 'S' and kontonr='$customerCommissionAccountNew'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				if ($customerCommissionAccountNewId) {
					$qtxt="update settings set var_value='$customerCommissionAccountNew' ";
					$qtxt.="where id='$customerCommissionAccountNewId'";
				} else {
					$qtxt = "insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
					$qtxt.= "('items','customerCommissionAccountNew','$customerCommissionAccountNew',";
					$qtxt.= "'Account for customers share of commisionsale, new items. ";
					$qtxt.= "If set, customers part of commmision sale of new items, is taken from this account','0')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else alert ("".findtekst(1736, $sprog_id)." $customerCommissionAccountNew ".findtekst(1735, $sprog_id)." ($regnaar)");
		} elseif ($customerCommissionAccountNewId) {
			$qtxt="update settings set var_value='' ";
			$qtxt.="where id='$customerCommissionAccountNewId'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} 
		if ($customerCommissionAccountUsed) {
			$qtxt="select id from kontoplan where regnskabsaar = '$regnaar' and kontotype = 'S' and kontonr='$customerCommissionAccountUsed'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($customerCommissionAccountUsedId) {
					$qtxt="update settings set var_value='$customerCommissionAccountUsed' ";
					$qtxt.="where id='$customerCommissionAccountUsedId'";
				} else {
					$qtxt = "insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
					$qtxt.= "('items','customerCommissionAccountUsed','$customerCommissionAccountUsed',";
					$qtxt.= "'Account for customers share of commisionsale, used items. ";
					$qtxt.= "If set, customers part of commmision sale of used items, is taken from this account','0')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else alert ("".findtekst(1736, $sprog_id)." $customerCommissionAccountUsed ".findtekst(1735, $sprog_id)." ($regnaar)");
		} elseif ($customerCommissionAccountUsedId) {
			$qtxt="update settings set var_value='' ";
			$qtxt.="where id='$customerCommissionAccountUsedId'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} 
		if ($defaultCommission) {
			if ($defaultCommissionId) {
				$qtxt="update settings set var_value='$defaultCommission' ";
				$qtxt.="where id='$defaultCommissionId'";
			} else {
				$qtxt = "insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
				$qtxt.= "('items','defaultCommission','$defaultCommission',";
				$qtxt.= "'Account for customers share of commisionsale, used items. ";
				$qtxt.= "If set, customers part of commmision sale of used items, is taken from this account','0')";
			}
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} 
		$qtxt = NULL;
		if ($commissionInclVatId) {
			$qtxt="update settings set var_value='$commissionInclVat' ";
			$qtxt.="where id='$commissionInclVatId'";
		} elseif ($commissionInclVat) {
			$qtxt = "insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.= "('items','commissionInclVat','$commissionInclVat',";
			$qtxt.= "'Include VAT in commission for used items. ";
			$qtxt.= "If set, VAT vat is put in top of the shop's commision and withdrawn from customers share','0')";
		}
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);

		if ($ownCommissionAccountNew) {
			$qtxt="select id from kontoplan where regnskabsaar = '$regnaar' and kontotype = 'S' and kontonr='$ownCommissionAccountNew'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				if ($ownCommissionAccountNewId) {
					$qtxt="update settings set var_value='$ownCommissionAccountNew' ";
					$qtxt.="where id='$ownCommissionAccountNewId'";
				} else {
					$qtxt = "insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
					$qtxt.= "('items','ownCommissionAccountNew','$ownCommissionAccountNew',";
					$qtxt.= "'Account for customers share of commisionsale, new items. ";
					$qtxt.= "If set, customers part of commmision sale of new items, is taken from this account','0')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else alert ("".findtekst(1736, $sprog_id)." $ownCommissionAccountNew ".findtekst(1735, $sprog_id)." ($regnaar)");
		} elseif ($ownCommissionAccountNewId) {
			$qtxt="update settings set var_value='' ";
			$qtxt.="where id='$ownCommissionAccountNewId'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} 
		if ($ownCommissionAccountUsed) {
			$qtxt="select id from kontoplan where regnskabsaar = '$regnaar' and kontotype = 'S' and kontonr='$ownCommissionAccountUsed'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($ownCommissionAccountUsedId) {
					$qtxt="update settings set var_value='$ownCommissionAccountUsed' ";
					$qtxt.="where id='$ownCommissionAccountUsedId'";
				} else {
					$qtxt = "insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
					$qtxt.= "('items','ownCommissionAccountUsed','$ownCommissionAccountUsed',";
					$qtxt.= "'Account for customers share of commisionsale, used items. ";
					$qtxt.= "If set, customers part of commmision sale of used items, is taken from this account','0')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else alert ("".findtekst(1736, $sprog_id)." $ownCommissionAccountUsed ".findtekst(1735, $sprog_id)." ($regnaar)");
		} elseif ($ownCommissionAccountUsedId) {
			$qtxt="update settings set var_value='' ";
			$qtxt.="where id='$ownCommissionAccountUsedId'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} 

		if ($commissionFromDate) $commissionFromDate=usdate($commissionFromDate);
		else $commissionFromDate='2021-01-01';
		$qtxt = "select id from settings where var_name = 'commissionFromDate'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt="update settings set var_value='$commissionFromDate'";
			$qtxt.="where id='$r[id]'";
		} else {
			$qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.="('items','commissionFromDate','$commissionFromDate','First date for settling customer share of commissionsale','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select id from grupper WHERE art = 'DIV' and kodenr='5'";
		if  (($id==0) && ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
			$qtxt="insert into grupper (beskrivelse,kodenr,art,box1) values ('Div_valg (Varer)','5','DIV','$box1')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
		}
		if ($convertExisting) {
			$x=0;
			$qtxt="select id, varenr, kostpris, retail_price, provision from varer where (varenr like 'kb%' or varenr like 'kn%') ";
			$qtxt.= "and ((retail_price > 0 and retail_price < 100) or (kostpris > 0 and kostpris < 1)) order by varenr";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if (!$r['provision']) {
					$id=$r['id'];
					if ($r['retail_price'] && $r['retail_price'] < 100) {
						$provision = afrund($r['retail_price'],0)*1;
						$kostpris = 1-$provision/100;
						$qtxt = "update varer set provision = '$provision', kostpris = '$kostpris' where id ='$id'";
					} elseif ($r['kostpris'] >= 0.5 && $r['kostpris'] < 1) {
						$provision = 100 - ($r['kostpris'] * 100);
						$qtxt = "update varer set provision = '$provision' where id ='$id'";
					} else {
						$provision = $r['kostpris'] * 100;
						$kostpris  = 1-$r['kostpris'];
						$qtxt = "update varer set provision = '$provision', kostpris = '$kostpris' where id ='$id'";
					}
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$x++;
				}
			}
			sleep (10);
			alert ("$x ".findtekst(1737, $sprog_id)."");
		}
	# varevalg slut
	#######################################################################################
	} elseif ($sektion=='variant_valg') {
		$id=if_isset($_POST['id']);
		$variant_beskrivelse=if_isset($_POST['variant_beskrivelse']);
		$variant_id=if_isset($_POST['variant_id']);
		$var_type_beskrivelse=if_isset($_POST['var_type_beskrivelse']);
		$variant_antal=if_isset($_POST['variant_antal']);
		$rename_varianter=if_isset($_POST['rename_varianter']);
		$rename_var_type=if_isset($_POST['rename_var_type']);
		if ($rename_var_type) {
			db_modify("update variant_typer set  beskrivelse='$var_type_beskrivelse' WHERE id = '$rename_var_type'",__FILE__ . " linje " . __LINE__);
		} elseif ($rename_varianter) {
			db_modify("update varianter set  beskrivelse='$variant_beskrivelse' WHERE id = '$rename_varianter'",__FILE__ . " linje " . __LINE__);
		}	elseif ($variant_beskrivelse) db_modify("insert into varianter (beskrivelse) values ('$variant_beskrivelse')",__FILE__ . " linje " . __LINE__);
		for ($x=1;$x<=$variant_antal;$x++) {
			if ($var_type_beskrivelse[$x] && $variant_id[$x]) db_modify("insert into variant_typer (beskrivelse,variant_id) values ('$var_type_beskrivelse[$x]','$variant_id[$x]')",__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='shop_valg') {
		$id   = if_isset($_POST['id']);
#		$box1 = if_isset($_POST['box1']);#incl_moms
		$box2 = if_isset($_POST['box2']);#Shop url
		$box3 = if_isset($_POST['box3']);#shop valg
		$box4 = if_isset($_POST['box4']);#merchant id
		$box5 = if_isset($_POST['box5']);#md5 secret
#		$box6 = if_isset($_POST['box6']);#Bruges ved productOptions
		$box7 = if_isset($_POST['box7']);#Tegnsæt for webshop
#		$box8 = if_isset($_POST['box8']);#Bruges ved ordre_valg
		$box9 = if_isset($_POST['box9']);#Agreement ID
		$box10= if_isset($_POST['box10']);#ledig

		if ($box3=='1') $box2='!';
		$qtxt=NULL;
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='5'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		if (!$id) {
			$qtxt="insert into grupper (beskrivelse,kodenr,art,box2,box3,box4,box5,box7,box9) values ('Div_valg (Varer)','5','DIV','$box2','$box3','$box4','$box5','$box7','$box9')";
		} elseif ($id > 0) {
			$qtxt="update grupper set box2='$box2',box3='$box3',box4='$box4',box5='$box5',box7='$box7',box9='$box9' WHERE id = '$id'";
		}
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	#######################################################################################
	} elseif ($sektion=='api_valg') {
		$id=if_isset($_POST['id']);
		$box1=db_escape_string(if_isset($_POST['api_key']));
		$box2=db_escape_string(if_isset($_POST['ip_list']));
		$box3=db_escape_string(if_isset($_POST['api_bruger']));
		$box4=db_escape_string(if_isset($_POST['api_fil']));
		$box5=db_escape_string(if_isset($_POST["api_fil2"]));

		$qtxt=NULL;
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'API' and kodenr='1'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		if (!$id) {
			$qtxt="insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5) values ('API valg','1','API','$box1','$box2','$box3','$box4', '$box5')";
		} elseif ($id > 0) {
			$qtxt="update grupper set box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5' WHERE id = '$id'";
		}
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	#######################################################################################
	} elseif ($sektion=='labels') {
		$valg=if_isset($_GET['valg']);
		$labelText=if_isset($_POST['labelText']);
		$labelName=if_isset($_POST['labelName']);
		$labelType=if_isset($_POST['labelType']);
		$labelTemplate=if_isset($_POST['labelTemplate']);
		$createNewLabel=if_isset($_POST['createNewLabel']);
		$newLabelName=if_isset($_POST['newLabelName']);
		$saveLabel=if_isset($_POST['saveLabel']);
		$deleteLabel=if_isset($_POST['deleteLabel']);
		
		$php_slut="?".">"; # Hack til løsning af syntakshighlightning, så det ikke bliver set af redigeringsværktøjet som en afslutning af PHP-kode
		$noGo=array('<?php','<?',$php_slut); # Hack benyttes her
		for ($x=0;$x<count($noGo);$x++) {
			if (strstr($labelText,$noGo[$x])) {
				$labelText=str_replace($noGo[$x],'',$labelText);
				$alert = findtekst(1738, $sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$alert')\">";
			}
		}
		if ($createNewLabel && !$newLabelName) {
			$alert1 = findtekst(1739, $sprog_id); #20210802
			if ($labelTemplate) $newLabelName=str_replace('.txt','',$labelTemplate);
			else alert($alert1);
		}
		if ($createNewLabel && $newLabelName) {
			if ($labelTemplate) {
				$fn="../importfiler/$labelTemplate";
				$fp=fopen($fn,"r");
				$labelText = fread($fp,filesize($fn));
				fclose($fp);
			}
			$qtxt = "insert into labels (labelname,account_id,labeltype,labeltext) values ";
			$qtxt.= "('". db_escape_string($newLabelName) ."','0','$labelType','". db_escape_string($labelText) ."')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$labelName=$newLabelName;
		} elseif ($saveLabel) {
			if ($labelName) {
				$qtxt="select id from labels WHERE labelname='$labelName' and account_id='0'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r['id']) $qtxt = "update labels set labeltype='$labelType',labeltext='". db_escape_string($labelText) ."' where id = '$r[id]'";
				else {
					$qtxt = "insert into labels (labelname,account_id,labeltype,labeltext) values "; 
					$qtxt.= "('$labelName','0','$labelType','". db_escape_string($labelText) ."')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			if ($labelName == 'Standard') {
				$r = db_fetch_array(db_select("select id from grupper WHERE art = 'LABEL'",__FILE__ . " linje " . __LINE__));
				$id=$r['id'];
				if ($id) {
					$qtxt = "update grupper set $valg='". db_escape_string($labelText) ."' WHERE id = '$id'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} else {
					$qtxt = "insert into grupper (beskrivelse,kodenr,art,$valg) values ";
					$qtxt.= "('Label layout','1','LABEL','". db_escape_string($labelText) ."')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
		if ($deleteLabel) {
			$qtxt = "delete from labels where labelname='$labelName' and account_id='0'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL='diverse.php?sektion=labels&valg=$valg'\">";
		}
	#######################################################################################
	} elseif ($sektion=='prislister') {
		$id=$_POST['id'];
		$beskrivelse=$_POST['beskrivelse'];
		$box1=$_POST['lev_id'];
		$box2=$_POST['prisfil'];
		$box3=$_POST['opdateret'];
		$box4=$_POST['aktiv'];
		$box5=$_POST['rabatter'];
		$box6=$_POST['rabat'];
		$box7=$_POST['grupper'];
		$box8=$_POST['gruppe'];
		$box9=$_POST['filtype'];
		$slet=$_POST['slet'];
		$antal=$_POST['antal'];

		for($x=1;$x<=$antal;$x++) {
#			if (!$box4[$x]) $box1[$x]=''; # 20160225

			$id[$x]*=1;
			$qtxt = NULL;
			$q_txt = "select id from grupper WHERE art='PL' and beskrivelse='$beskrivelse[$x]'";
			if ($id[$x]==0 && $box4[$x] && $r = db_fetch_array(db_select($q_txt,__FILE__ . " linje " . __LINE__))) {
				$id[$x]=$r['id'];
			} elseif ( $id[$x]==0 && $box4[$x] && $beskrivelse[$x] ) {
				$box4[$x]=0; # 20150612
				$qtxt = "insert into grupper (beskrivelse,kodenr,art,box2,box4,box6,box8,box9) values ";
				$qtxt.= "('$beskrivelse[$x]','0','PL','$box2[$x]','$box4[$x]','$box6[$x]','$box8[$x]','$box9[$x]')";
			} elseif ( $id[$x] && $slet[$x]=="Slet" ) {
				$slet[$x]=$slet[$x];
			} elseif ($id[$x] > 0) {
				$qtxt = "update grupper set beskrivelse='$beskrivelse[$x]',box1='$box1[$x]',box2='$box2[$x]',box4='$box4[$x]',";
				$qtxt.= "box6='$box6[$x]',box8='$box8[$x]',box9='$box9[$x]' WHERE id='$id[$x]'";
			}
			if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='rykker_valg') {
		$id=if_isset($_POST['id']);
		$box1=if_isset($_POST['box1']);
		$box2=if_isset($_POST['box2']);
		$box3=if_isset($_POST['box3']);
		$box4=if_isset($_POST['box4']);
		$box5=if_isset($_POST['box5']);
		$box6=if_isset($_POST['box6']);
		$box7=if_isset($_POST['box7']);
		# $box8 er reserveret til dato for sidst afsendte mail.
		$box9=if_isset($_POST['box9']); #Inkasso
		if ($box1) {
			$r = db_fetch_array(db_select("select id from brugere WHERE brugernavn = '$box1'",__FILE__ . " linje " . __LINE__));
			$box1=$r['id'];
		}
		if ($box9) {
			$qtxt="select id from adresser WHERE kontonr='$box9'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$box9=$r['id'];
		}
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='4'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('Div_valg (Rykker)','4','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','','$box9','')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box9='$box9' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='posOptions') {
		$id1=if_isset($_POST['id1'])*1;
		$box1=if_isset($_POST['kasseantal'])*1;
		$afd_nr=if_isset($_POST['afd_nr']);
		$kassekonti=if_isset($_POST['kassekonti']);
		$box4=if_isset($_POST['kortantal'])*1;
		$korttyper=if_isset($_POST['korttyper']);
		$kortkonti=if_isset($_POST['kortkonti']);
		$moms_nr=if_isset($_POST['moms_nr']);
		$rabatvarenr=if_isset($_POST['rabatvarenr']);
		$box9=if_isset($_POST['straksbogfor']);
		$box10=if_isset($_POST['udskriv_bon']);
		$box11=if_isset($_POST['vis_kontoopslag']);
		$box12=if_isset($_POST['vis_hurtigknap']);
		$box13=if_isset($_POST['timeout']);
		$box14=if_isset($_POST['vis_indbetaling']);

		$ValutaKode=if_isset($_POST['ValutaKode']);
		$ValutaKonti=if_isset($_POST['ValutaKonti']);
		$ValutaMlKonti=if_isset($_POST['ValutaMlKonti']);
		$ValutaDifKonti=if_isset($_POST['ValutaDifKonti']);
		
		if (!$ValutaKode) $ValutaKode = array();
		

		$id2                = (int)if_isset($_POST['id2']);
		$enabled            = if_isset($_POST['enabled']);
		$change_cardvalue   = if_isset($_POST['change_cardvalue']);
		$deactivateBonprint = if_isset($_POST['deactivateBonprint']);
		$betalingskort      = if_isset($_POST['betalingskort']); #20131210

		$planamount         = if_isset($_POST['planamount']);
		$plan               = if_isset($_POST['plan'],array()); 
		$bord               = if_isset($_POST['bord']); #20140508
		$bordantal          = if_isset($_POST['bordantal']); #20140508
		$bordvalg           = if_isset($_POST['bordvalg']);

		$diffkonti          = if_isset($_POST['diffkonti']);
		$div_kort_kto       = if_isset($_POST['div_kort_kto']); #20140129
		$jump2price         = if_isset($_POST['jump2price']);
		$kasseprimo         = if_isset($_POST['kasseprimo']);
		$kasseprimo         = (int)usdecimal($kasseprimo);
		$koekkenprinter     = if_isset($_POST['koekkenprinter']);
		$kortno             = if_isset($_POST['kortno'],array());
		$mellemkonti        = if_isset($_POST['mellemkonti']);
		$optalassist        = if_isset($_POST['optalassist']);
		$printer_ip         = if_isset($_POST['printer_ip']);
		$terminal_type      = if_isset($_POST['terminal_type']);
		$terminal_ip        = if_isset($_POST['terminal_ip']);
		$varenr             = if_isset($_POST['varenr']);
		$vis_saet           = if_isset($_POST['vis_saet']);
		$voucher            = if_isset($_POST['voucher']); #20181029
		$voucherText        = if_isset($_POST['voucherText']); #20181029
		$voucherItemNo      = if_isset($_POST['voucherItemNo']); #20210116

		$box14_2            = if_isset($_POST['udtag0']);

		update_settings_value("show_stock", "POS", if_isset($_POST['lagerbeh'], "0"), "Weather or not to show the stock level on sale in the POS system");

    # Table plan logic
    # Get the amount of tables currently in the system
    $plans = 1;
    $q = db_select("select id, name from table_pages ORDER BY id", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
      $plans++;
    }
    # If the system has more plans than the inp
    if ($planamount > $plans-1) {
      # For every difference in plans we add an empty one
      for ($i = 0; $i <= $planamount-$plans; $i++) {
        $id = $plans + $i;
        $qtxt = "INSERT INTO table_pages(id, name) VALUES ($id, '')";
			  db_modify($qtxt,__FILE__ . " linje " . __LINE__);
      }
    # If it has less plans
    } else if ($planamount < $plans-1) {
			db_modify("DELETE FROM table_pages WHERE id > $planamount",__FILE__ . " linje " . __LINE__);
    }
    # Save the created fields
    for ($i = 1; $i <= count($plan)+1; $i++) {
      if (isset ($plan[$i])) {
				$id = $i;
				$name = $plan[$i];
				$qtxt = "UPDATE table_pages SET name='$name' WHERE id=$id";
      db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
    }
		for ($x=0;$x<count($kortno);$x++) { // Removes noise in log
			if (!isset($betalingskort[$x])) $betalingskort[$x] = '';
			if (!isset($enabled[$x])) $enabled[$x] = '';
			if (!isset($voucher[$x])) $voucher[$x] = '';
			if (!isset($voucherText[$x])) $voucherText[$x] = '';
		}
		$kort = array();
		for ($x=0;$x<count($kortno);$x++) { // hjemmelavet sortering da array_multisort flytter '$betalingskort'
			if ($kortno[$x]<=9) $kortno[$x]='0'.$kortno[$x];
			$kort[$x] = "$kortno[$x]".chr(9)."$korttyper[$x]".chr(9)."$kortkonti[$x]".chr(9)."$betalingskort[$x]".chr(9);
			$kort[$x].= "$voucher[$x]".chr(9)."$voucherText[$x]".chr(9)."$enabled[$x]";
		}
//		array_multisort($kortno, $korttyper, $kortkonti, $betalingskort, $voucher, $voucherText, $enabled);
		sort($kort);
		for ($x=0;$x<count($kortno);$x++){
			list ($kortno[$x],$korttyper[$x],$kortkonti[$x],$betalingskort[$x],$voucher[$x],$voucherText[$x],$enabled[$x])=explode(chr(9),$kort[$x]);
		}

		$id3=if_isset($_POST['id3'])*1;
		$box1_3=if_isset($_POST['brugervalg']);
		$pfs=if_isset($_POST['pfs']);
		$box3_3=if_isset($_POST['kundedisplay']);
		$postEachSale=if_isset($_POST['postEachSale']);
		$box2=NULL;
		$box3=NULL;
		$box7=NULL;
		$box8=NULL;
		$box3_2=NULL;
		$box4_2=NULL;
		$box11_2=NULL;
		for ($x=0;$x<$box1;$x++) {
			if (!isset($bordvalg[$x]))     $bordvalg[$x]     = NULL;
			if (!isset($postEachSale[$x])) $postEachSale[$x] = NULL;
		
			$qtxt="select id from kontoplan WHERE kontonr = '$kassekonti[$x]'";
			if (($kassekonti[$x] && is_numeric($kassekonti[$x]) && db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))));
			else {
				if ($kassekonti[$x]) $txt=str_replace("<variable>",$kassekonti[$x],findtekst(277,$sprog_id));
				else $txt = findtekst(278,$sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			}
			$txt='';
			$qtxt="select id from kontoplan WHERE kontonr = '$mellemkonti[$x]'";
			if (($mellemkonti[$x] && is_numeric($mellemkonti[$x]) && db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))));
			else {
				if ($mellemkonti[$x]) $txt=str_replace("<variable>",$mellemkonti[$x],findtekst(717,$sprog_id));
				else $txt = findtekst(718,$sprog_id);
				if ($txt) print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			}
			$qtxt="select id from kontoplan WHERE kontonr = '$diffkonti[$x]'";
			if (($diffkonti[$x] && is_numeric($diffkonti[$x]) && db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))));
			else {
				if ($mellemkonti[$x]) $txt=str_replace("<variable>",$diffkonti[$x],findtekst(723,$sprog_id));
				else $txt = findtekst(718,$sprog_id);
				if ($txt) print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			}

      # Flatpay setup
      $kasse_id = $x + 1;
      $qtxt = "SELECT var_value FROM settings WHERE pos_id=$kasse_id and var_name='terminal_type'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
      $termtype = $terminal_type[$x];

      # Check if a payment type has been setup on this term
      if ($r) {
        # It does exist, edit the row
        # Check if it has a vibrant thingy setup
				# $qtxt = "SELECT id FROM vibrant_terms WHERE pos_id=$kasse_id";
        $qtxt = "SELECT var_value FROM settings WHERE pos_id=$kasse_id AND var_grp='vibrant_terms'";
        $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));

        # If the terminal has vibrant setup we want to set its terminal id to be -1 as we save the vibrant terminal ids for later user
        if ($r) {
          $qtxt = "UPDATE settings SET pos_id=-1 WHERE pos_id=$kasse_id AND var_grp='vibrant_terms'";
          db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        }

        # Modify to use the right payment method on the selected terminal
        $qtxt = "UPDATE settings SET var_value='$termtype' WHERE pos_id=$kasse_id and var_name='terminal_type'";
			  db_modify($qtxt,__FILE__ . " linje " . __LINE__);
      } else {
        # It has not been setup yet, create the row
        $qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description, pos_id) 
                 VALUES ('terminal_type', 'POS', '$termtype', 'What the main payment system should be.', $kasse_id)";
			  db_modify($qtxt,__FILE__ . " linje " . __LINE__);
      }

      # Vibrant setup
      $termtype = $terminal_type[$x];
      if (str_starts_with($termtype, "Vibrant: ")) {
        $term_name = str_replace("Vibrant: ", "", $termtype);
        $kasse_id = $x + 1;

        $qtxt = "UPDATE settings SET pos_id='$kasse_id' WHERE var_name='$term_name' AND var_grp='vibrant_terms'";
			  db_modify($qtxt,__FILE__ . " linje " . __LINE__);
      } 
      
      $kasse_id = $x + 1;
      $qtxt = "SELECT var_value FROM settings WHERE pos_id=$kasse_id and var_name='terminal_type'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));

      # Check if another payment type has been setup on this term
      if ($r) {
        # It does exist, edit the row
        $qtxt = "UPDATE settings SET var_value='$termtype' WHERE pos_id=$kasse_id and var_name='terminal_type'";
			  db_modify($qtxt,__FILE__ . " linje " . __LINE__);
      } else {
        # It has not been setup yet, create the row
        $qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description, pos_id) 
                 VALUES ('terminal_type', 'POS', '$termtype', 'What the main payment system should be.', $kasse_id)";
			  db_modify($qtxt,__FILE__ . " linje " . __LINE__);
      }
       

			if ($box2) {
				$box2.=chr(9).$kassekonti[$x];
				$box3.=chr(9).$afd_nr[$x];
				$box7.=chr(9).$moms_nr[$x];
				$box3_2.=chr(9).$printer_ip[$x];
				$box4_2.=chr(9).$terminal_ip[$x];
				$box8_2.=chr(9).$mellemkonti[$x];
				$box9_2.=chr(9).$diffkonti[$x];
				$box10_2.=chr(9).$koekkenprinter[$x];
				$box13_2.=chr(9).$bordvalg[$x];	 #20161116
				$box2_3.=chr(9).$pfs[$x];	 #20161116
				$poEaSa.=chr(9).$postEachSale[$x];
				for ($y=0;$y<count($ValutaKode);$y++) {
					$VKbox4[$y].=chr(9).$ValutaKonti[$x][$y];
					$VKbox5[$y].=chr(9).$ValutaMlKonti[$x][$y];
					$VKbox6[$y].=chr(9).$ValutaDifKonti[$x][$y];
				}
			} else {
				$box2=$kassekonti[$x];
				$box3=$afd_nr[$x];
				$box7=$moms_nr[$x];
				$box3_2=$printer_ip[$x];
				$box4_2=$terminal_ip[$x];
				$box8_2=$mellemkonti[$x];
				$box9_2=$diffkonti[$x];
				$box10_2=$koekkenprinter[$x];
				$box13_2=$bordvalg[$x];	 #20161116
				$box2_3=$pfs[$x];
				$poEaSa=$postEachSale[$x];
				for ($y=0;$y<count($ValutaKode);$y++) {
					$VKbox4[$y]=$ValutaKonti[$x][$y];
					$VKbox5[$y]=$ValutaMlKonti[$x][$y];
					$VKbox6[$y]=$ValutaDifKonti[$x][$y];
				}
			}

		}
		$box5=NULL;
		$box6=NULL;

		setcookie('saldi_pfs','',time()-60,'/');
		if ($box3_2) {
			if (isset($_COOKIE['saldi_printserver'])) setcookie("saldi_printserver",'',time()-60,'/');
			if (isset($_COOKIE['salditerm'])) setcookie("salditerm",'',time()-60,'/'); #setcookie("salditerm","0",time()-1);
		}

		for ($x=0;$x<count($ValutaKode);$x++) {
			$qtxt="update grupper set box4='$VKbox4[$x]',box5='$VKbox5[$x]',box6='$VKbox6[$x]' WHERE art='VK' and box1='$ValutaKode[$x]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}

		for ($x=0;$x<$box4;$x++) {
			if ($korttyper[$x]) {
				$kortkonti[$x]*=1;
				if (!db_fetch_array(db_select("select id from kontoplan WHERE kontonr = '$kortkonti[$x]'",__FILE__ . " linje " . __LINE__))) {
					if ($kortkonti[$x]) $txt=str_replace("<variable>",$kortkonti[$x],findtekst(277,$sprog_id));
					else $txt = findtekst(278,$sprog_id);
					print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
				}
				if (isset($voucherItemNo[$x])) {
					$qtxt="select id from varer where lower(varenr) = '". db_escape_string(strtolower($voucherItemNo[$x])) ."'";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					if ($r['id']) $voucherItemId[$x]=$r['id'];
					else {
						$voucherItemId[$x]='0';
						$alerttxt="".findtekst(320, $sprog_id)." $voucherItemNo[$x] ".findtekst(1740, $sprog_id)."";
						alert ($alerttxt);
					}
				} else $voucherItemId[$x]='0';
				if ($box5) {
					$box5.=chr(9).trim($korttyper[$x]);
					$box6.=chr(9).trim($kortkonti[$x]);
					$box5_2.=chr(9).trim($betalingskort[$x]);	 #20121210
					$box4_3.=chr(9).trim($voucher[$x]);	 #20181029
					$box5_3.=chr(9).trim($voucherText[$x]);	 #20181029
					$voucherItems.=chr(9).trim($voucherItemId[$x]); #20200116
					$card_enabled.=chr(9).trim($enabled[$x]);	 #20181215
				} else {
					$box5=trim($korttyper[$x]);
					$box6=trim($kortkonti[$x]);
					$box5_2=trim($betalingskort[$x]);	#20121210
					$box4_3=trim($voucher[$x]);	#20181029
					$box5_3=trim($voucherText[$x]);	#20181029
					$voucherItems=trim($voucherItemId[$x]); #20200116
					$card_enabled=trim($enabled[$x]);	 #20181215
				}
			}


      

		}
		$box7_2=NULL;
		for ($x=0;$x<$bordantal;$x++) { #20140508
			$tmp=$x+1;
			if (!isset($bord[$x]) || !$bord[$x]) $bord[$x]="Bord ".$tmp;
			($box7_2)?$box7_2.=chr(9).$bord[$x]:$box7_2=trim($bord[$x]);
		}
		if ($varenr && $r=db_fetch_array(db_select("select id from varer WHERE varenr = '$varenr'",__FILE__ . " linje " . __LINE__))) {
			$box11_2=$r['id'];
		} elseif ($varenr) {
				$txt = str_replace('XXXXX',$varenr,findtekst(289,$sprog_id));
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		if ($rabatvarenr && $r=db_fetch_array(db_select("select id from varer WHERE varenr = '$rabatvarenr'",__FILE__ . " linje " . __LINE__))) {
			$box8=$r['id'];
		} elseif ($rabatvarenr) {
				$txt = str_replace('XXXXX',$rabatvarenr,findtekst(289,$sprog_id));
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		$qtxt = "select id from grupper WHERE art = 'POS' and kodenr='1' and fiscal_year = '$regnaar'";
		if  (($id1==0) && ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))) $id1=$r['id'];
		elseif ($id1==0){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14,fiscal_year) values ('POS_valg','1','POS','$box1','$box2','$box3','$box4','$box5','$box6','$box7','','$box9','$box10','$box11','$box12','$box13','$box14','$regnaar')",__FILE__ . " linje " . __LINE__);
		} elseif ($id1 > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12',box13='$box13',box14='$box14' WHERE id = '$id1'",__FILE__ . " linje " . __LINE__);
		}
		if ($id2) {
			$qtxt = "update grupper set box1 = '$kasseprimo',box2='$optalassist',box3='$box3_2',box4='$box4_2',";
			$qtxt.= "box5='$box5_2',box6='$div_kort_kto',box7='$box7_2',box8='$box8_2',box9='$box9_2',box10='$box10_2',";
			$qtxt.= "box11='$box11_2',box12='$vis_saet',box13='$box13_2',box14='$box14_2' WHERE id = '$id2'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		if ($id3) {
			$qtxt="update grupper set box1='$box1_3',box2='$box2_3',box3='$box3_3',box4='$box4_3',box5='$box5_3',box6='',box7='',";	#20181029
			$qtxt.="box8='',box9='',box10='',box11='',box12='',box13='',box14='' WHERE id = '$id3'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$qtxt="select id from settings where var_name='card_enabled'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt="update settings set var_value='$card_enabled' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('card_enabled','Paycards','$card_enabled','Tab separated list showing enabled paymentcards ";
			$qtxt.= "(Disabled cards are used to specify account# in API orders and returns from payment terminal)','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select id from settings where var_name='change_cardvalue'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt="update settings set var_value='$change_cardvalue' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('change_cardvalue','Paycards','$change_cardvalue','Allow changes in cardsums when doing cash summary";
			$qtxt.= "(Disabled cards are used to specify account# in API orders and returns from payment terminal)','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		# voucherItems
		$qtxt="select id from settings where var_name='voucherItems'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt="update settings set var_value='$voucherItems' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('voucherItems','Paycards','$voucherItems','Tab separated list showing which item representing the voucher','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select id from settings where var_name='deactivateBonprint'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt="update settings set var_value='$deactivateBonprint' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('deactivateBonprint','globals','$deactivateBonprint','Deactivates the receipt printer','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select id from settings where var_name='postEachSale'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt="update settings set var_value='$poEaSa' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('postEachSale','POS','$poEaSa','Post to transactions immediately when finishing sale','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select id from settings where var_name='jump2price'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt="update settings set var_value='$jump2price' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('jump2price','globals','$jump2price','Cursor jumps direct to price and sets quantity to 1 if price is 0','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);

		#######################################################################################
	} elseif ($sektion=='docubizz') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3'];
		$box4=$_POST['box4'];
		$box5=$_POST['box5'];

		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DocBiz'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5) values ('DocuBizz','1','DocBiz','$box1','$box2','$box3','$box4','$box5')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	} elseif ($sektion=='upload_dbz') {
		include("docubizzexport.php");
		$r = db_fetch_array(db_select("select * from grupper WHERE art = 'DocBiz'",__FILE__ . " linje " . __LINE__));
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$r['box2'].":".$r['box3']."@".$r['box1']."/".$r['box5']." < ftpscript > NULL ";
		system ($kommando);
		$alert= findtekst(1741, $sprog_id);
		print "<BODY onLoad=\"JavaScript:alert('$alert')\">";
#######################################################################################
	} elseif ($sektion=='bilag') {
		$id   = if_isset($_POST['id']);
		$box1 = if_isset($_POST['box1']);
		$box2 = if_isset($_POST['box2']);
		$box3 = if_isset($_POST['box3']);
		if ($box3 && $box3 != '********') $box3 = urlencode($box3); 
		$box4 = if_isset($_POST['box4']);
		$box5 = if_isset($_POST['box5']);
		$storageType = if_isset($_POST['storageType']);
		($storageType == 'internFTP')?$box6='on':$box6=NULL;
		$box7 = if_isset($_POST['box7']);
#		if ($box6) {
#			include("../includes/connect.php");
#			$r=db_fetch_array(db_select("select * from diverse WHERE beskrivelse='FTP' and nr='1'"));
#			$box1=$r['box1'];
#			$box2=$r['box2'];
#			$box3=$r['box3'];
#			$box4=$r['box4'];
#			$box5=$r['box5'];
#			include("../includes/online.php");
#		}
		if ($box1 && substr($box1,-1)!="/") $box1.="/";
		if ($box6 && $box1 && !strpos($_SERVER['SERVER_NAME'],$box1)) $box1.=$_SERVER['SERVER_NAME']."/";
		if ($box6 && $box1 && !strpos($db,$box1)) $box1.=$db."/";
		if ($box3=='********') {
			$r=db_fetch_array(db_select("select box3 from grupper WHERE art = 'bilag'",__FILE__ . " linje " . __LINE__));
			$box3=$r['box3'];
		}
		if (!$box6 && $box1 && $box2 && $box4 && $box5) testftp($box1,$box2,$box3,$box4,$box5,$box6);
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'bilag'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7) values ('Bilag og dokumenter','1','bilag','$box1','$box2','$box3','$box4','$box5','$box6','$box7')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			if ($box6) {
				db_modify("update grupper set box6='$box6',box7='$box7' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
			} else {
				db_modify("update grupper set box1='$box1',box2='$box2',box4='$box4',box5='$box5',box6='$box6' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
				include("../includes/connect.php");
				db_modify("update regnskab set bilag='0' WHERE id = '$db_id'",__FILE__ . " linje " . __LINE__);
				include("../includes/online.php");
				if ($box3!='********') db_modify("update grupper set  box3='$box3' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
			}
		}
		if ($box6) {
			include("../includes/connect.php");
			db_modify("update regnskab set bilag='1' WHERE id = '$db_id'",__FILE__ . " linje " . __LINE__);
			include("../includes/online.php");
		}
#######################################################################################
	} elseif ($sektion=='email') {
		$id=$_POST['id'];
		$box1=db_escape_string($_POST['box1']);
		$box2=db_escape_string($_POST['box2']);
		$box3=db_escape_string($_POST['box3']);
		$box4=db_escape_string($_POST['box4']);
		$box5=db_escape_string($_POST['box5']);
		$box6=db_escape_string($_POST['box6']);
		$box7=db_escape_string($_POST['box7']);
		$box8=db_escape_string($_POST['box8']);
		$box9=db_escape_string($_POST['box9']);
		$box10=db_escape_string($_POST['box10']);

		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'MAIL' and kodenr = '1'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('e-mail tekster','1','MAIL','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
		}
#######################################################################################
	} elseif ($sektion=='orediff') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2']*1;
		if ($box1) $box1=usdecimal($box1);
		if ($box2 && !db_fetch_array(db_select("select id from kontoplan WHERE kontonr = '$box2' and kontotype = 'D' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))){
			$tekst=findtekst(175,$sprog_id);
			print "<BODY onLoad=\"JavaScript:alert('$tekst')\">";
			$diffkto=$box2;
			$box2='';
		}
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'OreDif' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box1,box2,fiscal_year) ";
			$qtxt.= "values ('Oredifferencer','1','OreDif','$box1','$box2','$regnaar')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
		}
######################################################################################
	} elseif ($sektion=='massefakt') {
		$id         = if_isset($_POST['id']);
		$brug_mfakt = if_isset($_POST['brug_mfakt']);
		if ($brug_mfakt) {
			$brug_dellev = if_isset($_POST['brug_dellev']);
			$levfrist    = if_isset($_POST['levfrist']);
		} else {
			$brug_dellev=NULL;
			$levfrist=0;
		}
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'MFAKT'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3) values ('Massefakturering','1','MFAKT','$brug_mfakt','$brug_dellev','$levfrist')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$brug_mfakt',box2='$brug_dellev',box3='$levfrist' WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
		}
		

######################################################################################


######################################################################################
	} elseif ($sektion=='kontoplan_io') {
		if (strstr($_POST['submit'])=="Eksport") {
			list($tmp)=explode(":",$_POST['regnskabsaar']);
			print "<BODY onLoad=\"javascript:exporter_kontoplan=window.open('exporter_kontoplan.php?aar=$tmp','lager','scrollbars=yes,resizable=yes,dependent=yes');exporter_kontoplan.focus();\">";
		}
		elseif (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:importer_kontoplan=window.open('importer_kontoplan.php','kontoplan','scrollbars=yes,resizable=yes,dependent=yes');importer_kontoplan.focus();\">";
		}
	} elseif ($sektion=='adresser_io') {
		if (strstr($_POST['submit'])=="Eksport") {
			print "<BODY onLoad=\"javascript:exporter_adresser=window.open('exporter_adresser.php?aar=$tmp','debitor','scrollbars=yes,resizable=yes,dependent=yes');exporter_adresser.focus();\">";
		}
		elseif (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:importer_debitor=window.open('importer_debitor.php','debitor','scrollbars=yes,resizable=yes,dependent=yes');importer_debitor.focus();\">";
		}
	} elseif ($sektion=='varer_io') {
		if (strstr($_POST['submit'])=="Eksport") {
			print "<BODY onLoad=\"javascript:exporter_varer=window.open('exporter_varer.php?aar=$tmp','debitor','scrollbars=yes,resizable=yes,dependent=yes');exporter_varer.focus();\">";
		}
		elseif (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:importer_varer=window.open('importer_varer.php','importer_varer','scrollbars=yes,resizable=yes,dependent=yes');importer_varer.focus();\">";
		}
	} elseif ($sektion=='solar_io') {
		if (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:solarvvs=window.open('solarvvs.php','solarvvs','scrollbars=yes,resizable=yes,dependent=yes');solarvvs.focus();\">";
		}
	} elseif ($sektion=='formular_io') {
		if (strstr($_POST['submit'])=="Eksport") {
			print "<BODY onLoad=\"javascript:exporter_formular=window.open('exporter_formular.php','exporter_formular','scrollbars=yes,resizable=yes,dependent=yes');exporter_formular.focus();\">";
		}
		elseif (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:importer_formular=window.open('importer_formular.php','importer_formular','scrollbars=yes,resizable=yes,dependent=yes');importer_formular.focus();\">";
		}
	}elseif ($sektion=='sqlquery_io') {
		$sqlstreng=if_isset($_POST['sqlstreng']);
	} elseif ($sektion=='kontoindstillinger') {
		if (isset($_POST['submit']) && strstr($_POST['submit'],'Skift')) {
			$nyt_navn=trim(db_escape_string($_POST['nyt_navn']));
			include("../includes/connect.php");
			if (db_fetch_array(db_select("select id from regnskab WHERE regnskab = '$nyt_navn'",__FILE__ . " linje " . __LINE__))) {
				$alert1 = findtekst(1742, $sprog_id);
				$alert2 = findtekst(1743, $sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$alert1 $nyt_navn! $alert2')\">";
			} else {
				$r=db_fetch_array(db_select("select id from kundedata WHERE regnskab_id = '$db_id'",__FILE__ . " linje " . __LINE__));
				if (!$r['id']){
					$tmp=db_escape_string($regnskab);
					db_modify("update kundedata set regnskab_id = '$db_id' WHERE regnskab='$tmp'",__FILE__ . " linje " . __LINE__);
				}
				db_modify("update regnskab set regnskab = '$nyt_navn' WHERE db='$db'",__FILE__ . " linje " . __LINE__);

			}
			include("../includes/online.php");
		}	elseif (isset($_POST['opdat_tidszone'])) {
			$timezone=$_POST['timezone'];
			if ($timezone) {
				$r=db_fetch_array(db_select("select id from settings where var_name='timezone'",__FILE__ . " linje " . __LINE__));
				if ($r['id']) $qtxt="update settings set var_value='$timezone', user_id='0' where id='$r[id]'";
				else {
					$qtxt="insert into settings (var_name,var_value,var_description,user_id)";
					$qtxt.=" values ";
					$qtxt.="('timezone','$timezone','Tidszone. Anvendes hvis regnskabet anvender anden tidszone end serveren','0')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				setcookie("timezone",$timezone,time()+60*60*24*30,'/');
			}
		} elseif (isset($_POST['nulstil']) && $_POST['nulstil']) { #20170731
			$qtxt = "TRUNCATE ansatmappe,ansatmappebilag,batch_kob,batch_salg,betalinger,betalingsliste,bilag,bilag_tjekskema,";
			$qtxt.= "budget,corrections,crm,deleted_order,drawer,gavekort,gavekortbrug,historik,jobkort,jobkort_felter,";
			$qtxt.= "kassekladde,kladdeliste,kontokort,kostpriser,loen,loen_enheder,lagerstatus,mappe,mappebilag,misc_meta_data,";
			$qtxt.= "modtageliste,modtagelser,navigator,noter,openpost,opgaver,ordrelinjer,ordrer,ordretekster,";
			$qtxt.= "pbs_kunder,pbs_linjer,pbs_liste,pbs_ordrer,pos_betalinger,price_correction,proforma,provision,queries,rabat,";
			$qtxt.= "regulering,reservation,returnings,sager,sagstekster,serienr,shop_adresser,shop_ordrer,shop_varer,";
			$qtxt.= "simulering,tabeller,tidsreg,tjekpunkter,tmpkassekl,transaktioner,report,valuta restart identity";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM grupper WHERE art='RA' and kodenr !='1'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM grupper WHERE fiscal_year > 1",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM grupper WHERE art='USET'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM grupper WHERE art='DLV'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM grupper WHERE art='KLV'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM grupper WHERE art='DRV'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM grupper WHERE art='KRV'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM grupper WHERE art='VV'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM grupper WHERE art='OLV'",__FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM kontoplan WHERE regnskabsaar!='1'",__FILE__ . " linje " . __LINE__);
			db_modify("UPDATE varer SET beholdning = '0'",__FILE__ . " linje " . __LINE__);
			$qtxt = "DELETE From settings where (var_grp = 'debitor' or var_grp = 'mySale') and (var_name = 'mailSubject' or var_name = 'mailText')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			if ($_POST['behold_debkred']=='') {
				$qtxt="select id from adresser WHERE art='S'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$eget_id=$r['id'];
				$qtxt="DELETE FROM adresser WHERE id!='$eget_id'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt="TRUNCATE ansatte,ansatmappe,ansatmappebilag,vare_lev,shop_adresser restart identity";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			if ($_POST['behold_varer']=='') {
				$qtxt="TRUNCATE shop_varer,styklister,varer,vare_lev,varetilbud,variant_typer,variant_varer,varianter restart identity";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			print tekstboks('regnskab nulstillet');
		} elseif (isset($_POST['slet'])) {
			if ($_POST['slet_regnskab']=='on') { #20185024
				include("../includes/connect.php");
				db_modify("update regnskab set lukket='on',logintekst='slettet af $brugernavn den ".date("Ymd H.i")."' where id = '$db_id'",__FILE__ . " linje " . __LINE__);
				db_modify("delete from online where db='$db'",__FILE__ . " linje " . __LINE__);
				include("../includes/online.php");
				print "Sletter";
			} else {
				$tekst1=findtekst(852,$sprog_id);
				$alert = findtekst(1744, $sprog_id);
				alert("$alert $tekst1: $regnskab");
			}
		}

	} elseif ($sektion=='smtp') {
		$smtp=trim(db_escape_string($_POST['smtp']));
		$smtpuser=trim(db_escape_string($_POST['smtpuser']));
		$smtppass=trim(db_escape_string($_POST['smtppass']));
		$smtpcrypt=trim(db_escape_string($_POST['smtpcrypt']));
		db_modify("update adresser set felt_1 = '$smtp',felt_2 = '$smtpuser',felt_3 = '$smtppass',felt_4 = '$smtpcrypt' WHERE art='S'",__FILE__ . " linje " . __LINE__);
		$sektion='kontoindstillinger';

	} elseif ($sektion=='tjekliste') {
		$id=if_isset($_POST['id']);
		$tjekantal=if_isset($_POST['tjekantal']);
		$fase=if_isset($_POST['fase']);
		$ny_fase=if_isset($_POST['ny_fase']);
		$ny_tjekgruppe=if_isset($_POST['ny_tjekgruppe']);
		$tjekpunkt=if_isset($_POST['tjekpunkt']);
		$nyt_tjekpunkt=if_isset($_POST['nyt_tjekpunkt']);
		$liste_id=if_isset($_POST['liste_id']);
		$gruppe_id=if_isset($_POST['gruppe_id']);
		$ret=if_isset($_POST['ret']);

		if ($ny_tjekliste=$_POST['ny_tjekliste']) {
			$r = db_fetch_array($q = db_select("select max(fase) as fase from tjekliste WHERE assign_to = 'sager'",__FILE__ . " linje " . __LINE__));
			$nf=$r['fase']+1;
			db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekliste','0','sager','$nf')",__FILE__ . " linje " . __LINE__);
		}
		for ($x=1;$x<=$tjekantal;$x++) {
			if (isset($ny_fase[$x]) && $ny_fase[$x]) $nf=$ny_fase[$x]+.1;
			if ($fase[$x]!=$nf) db_modify("update tjekliste set fase='$nf' WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
			if ($ret && $ret==$id[$x] && $tjekpunkt[$x]) db_modify("update tjekliste set tjekpunkt='$tjekpunkt[$x]' WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
			if (isset($ny_tjekgruppe[$x]) && $ny_tjekgruppe[$x]) {
				db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekgruppe[$x]','$liste_id[$x]','sager','$fase[$x]')",__FILE__ . " linje " . __LINE__);
			}
			if (isset($nyt_tjekpunkt[$x]) && $nyt_tjekpunkt[$x]) {
				db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$nyt_tjekpunkt[$x]','$gruppe_id[$x]','sager','$fase[$x]')",__FILE__ . " linje " . __LINE__);
			}
		}

		if ($ny_tjekgruppe=$_POST['ny_tjekgruppe']) {

			$qtxt = "select max(fase) from tjekliste WHERE assign_to = 'sager'";
			($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)) && isset($r['fase']))?$ny_fase=$r['fase']:$ny_fase=NULL;
			if ($ny_fase || $ny_fase=='0') $ny_fase++;
			else $ny_fase=0;
#			db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekgruppe','0','sager','$ny_fase')",__FILE__ . " linje " . __LINE__);
		}
	}
} else {
	$valg=if_isset($_GET['valg']);
	$sektion=if_isset($_GET['sektion']);
#	if ($sektion == 'personlige_valg') $sektion = 'userSettings';
}
$docubizz=NULL;
if(db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr = '2' and box6='on'",__FILE__ . " linje " . __LINE__))) $docubizz='on';

print "<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" height=\"100%\"><tbody>";

if ($menu != 'T') {
	print "<td width=\"170px\" valign=\"top\">";
	print "<table cellpadding=\"2\" cellspacing=\"2\" border=\"0\" width=\"100%\"><tbody>";
	if ($menu == 'S') {
		print "<tr><td align=left>&nbsp;<a href=syssetup.php><button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\"><b>&#9668; Tilbage</b></button></a></td></tr>\n"; // 200240428

		print "<tr><td align=left><a href=diverse.php?sektion=kontoindstillinger>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(783,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=provision>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(784,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=personlige_valg>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(785,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=ordre_valg>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(786,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=productOptions>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(787,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=variant_valg>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(788,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=shop_valg>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(789,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=api_valg>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
			   API</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=labels>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(791,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=prislister>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(792,$sprog_id)."</button></a><!--tekst 427--></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=rykker_valg>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(793,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=div_valg>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(794,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=tjekliste>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(796,$sprog_id)."</button></a></td></tr>\n";

		if ($docubizz) print "<tr><td align=left><a href=diverse.php?sektion=docubizz>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(796,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=bilag>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(797,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=orediff>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(170,$sprog_id)."</button></a><!--tekst 170--></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=massefakt>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(200,$sprog_id)."</button></a><!--tekst 200--></td></tr>\n";

		if (file_exists("../debitor/pos_ordre.php")) print "<tr><td align=left><a href=diverse.php?sektion=posOptions><button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(271,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=sprog>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(801,$sprog_id)."</button></a></td></tr>\n";

		print "<tr><td align=left><a href=diverse.php?sektion=div_io>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst(802,$sprog_id)."</button></a></td></tr>\n";

		print "</tbody></table></td><td valign=\"top\" align=\"left\"><table align=\"left\" valign=\"top\" border=\"0\" width=\"90%\"><tbody>\n";
		print "<script>document.getElementById('sidebar-base').style.display = 'none';</script>";

	} else { //Gammel menu
		print "<tr><td align=\"center\" valign=\"top\"><br></td></tr>";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=kontoindstillinger>".findtekst(783,$sprog_id)."</a></td></tr>\n"; // 20210513
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=provision>".findtekst(784,$sprog_id)."</a>&nbsp;</td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=personlige_valg>".findtekst(785,$sprog_id)."</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=ordre_valg>".findtekst(786,$sprog_id)."</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=productOptions>".findtekst(787,$sprog_id)."</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=variant_valg>".findtekst(788,$sprog_id)."</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=shop_valg>".findtekst(789,$sprog_id)."</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=api_valg>API</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=labels>".findtekst(791,$sprog_id)."</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=prislister>".findtekst(792,$sprog_id)."</a><!--tekst 427--></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=rykker_valg>".findtekst(793,$sprog_id)."</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=div_valg>".findtekst(794,$sprog_id)."</a></td></tr>\n";
		# print "<tr><td align=left $top_bund>&nbsp;<a href=..\paperpdf/activatepaperflow.php>".findtekst(795,$sprog_id)."</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=tjekliste>".findtekst(796,$sprog_id)."</a></td></tr>\n";
		if ($docubizz) print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=docubizz>".findtekst(796,$sprog_id)."</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=bilag>".findtekst(797,$sprog_id)."</a></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=orediff>".findtekst(170,$sprog_id)."</a><!--tekst 170--></td></tr>\n";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=massefakt>".findtekst(200,$sprog_id)."</a><!--tekst 200--></td></tr>\n";
		if (file_exists("../debitor/pos_ordre.php")) print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=posOptions>".findtekst(271,$sprog_id)."</a><!--tekst 271--></td></tr>\n";
		# print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=email>Mail indstillinger</a></td></tr>";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=sprog>".findtekst(801,$sprog_id)."</a></td></tr>\n";
		# print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=kontoplan_io>Indl&aelig;s  / udl&aelig;s kontoplan</a></td></tr>";
		print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=div_io>".findtekst(802,$sprog_id)."</a></td></tr>\n";
		print "</tbody></table></td><td valign=\"top\" align=\"left\"><table align=\"left\" valign=\"top\" border=\"0\" width=\"90%\"><tbody>\n";
	}
} 
if (!$sektion) print "<td><br></td>";
if ($sektion=="kontoindstillinger") kontoindstillinger($regnskab,$skiftnavn);
if ($sektion=="provision") provision();
if ($sektion=="personlige_valg") personlige_valg();
if ($sektion == 'userSettings') {
	include_once('syssetupIncludes/userSettings.php');
	userSettings();
}
if ($sektion=="ordre_valg") ordre_valg();
if ($sektion=="productOptions" || $sektion=="label") {
	include ("diverseIncludes/productOptions.php");
	productOptions($defaultProvision);
}
if ($sektion=="variant_valg") variant_valg();
if ($sektion=="shop_valg") shop_valg();
if ($sektion=="api_valg") api_valg();
if ($sektion=="labels") labels($valg);
if ($sektion=="prislister") prislister();
if ($sektion=="rykker_valg") rykker_valg();
if ($sektion=="div_valg") div_valg(); # Kalder sys_div_valg.php
if ($sektion=="docubizz") docubizz();
if ($sektion=="bilag") bilag();
//if ($sektion=="paperflow") activatePaperflow();
if ($sektion=="orediff") orediff($diffkto);
if ($sektion=="massefakt") massefakt();
if ($sektion=="posOptions") {
	include ("diverseIncludes/posOptions.php");
	posOptions();
} if ($sektion=="sprog") {
	include ("diverseIncludes/language.php");
	language();
}
if ($sektion=="tjekliste") tjekliste();
if (strpos($sektion,"_io")) {
	kontoplan_io();
	formular_io();
	adresser_io();
	varer_io();
	variantvarer_io();
	sqlquery_io($sqlstreng);
}

print "</tbody></table></td></tr>";
if ($menu=='T') print "</div>";
#print "</form>";
#print "</tbody></table></td></tr>";





?>
</tbody></table>
</body></html>
