<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------/admin/opret.php-----patch 3.9.6 ------ 2020-11-14 --------
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
// Copyright (c) 2003-2020 Saldi.dk ApS
// ----------------------------------------------------------------------
// 
// 2013.05.14 Slutmd blev sat til 1 ved oprettelse af regnskabsår
// 2013.06.03 Apoststroffer omkring $startmd & $slutmd
// 2013.06.28 Div tilføjelser til ver 3.3.0
// 2013.10.07 Tilføjet kasse_nr numeric(15,0) til "create transaktioner";
// 2013.11.15 Tilføjet variant_id integer til "create regulering";
// 2014.01.06 Tilføjet tmp_kode text til "create brugere";
// 2014.01.07 Tilføjet dokument text til "create ordrer";
// 2014.05.08 Tilføjet password text & overtid numeric (1,0) til "create ansatte";
// 2014.06.18 Tilføjet indhold numeric (15,3) til "create varer";
// 2014.10.02 Tilføjet omvbet varchar(2) "create ordrelinjer";
// 2014.10.02 Tilføjet omvbet varchar(2) "create ordrer";
// 2014.12.05 PK - Tilføjet table "mappe og mappebilag"
// 2015.01.02 PHR Tilføjet div. see opdat_3.4.php ver 3.4.6-3.4.8
// 2015.01.22 PHR Tilføjet saet integer i ordrelinjer. se opdat_3.4.php ver 3.4.9
// 2015.02.03 PHR Tilføjet tabel kostpriser, se opdat_3.4.php ver 3.5.0
// 2015.02.03 PHR Tilføjet lager til regulering, se opdat_3.5.php ver 3.5.0
// 2015.02.03 PHR Tilføjet afd & lager til ordrerlinjer, se opdat_3.5.php ver 3.5.0
// 2015.02.03 PHR Tilføjet afd til ordrer, se opdat_3.5.php ver 3.5.0
// 2015.02.03 PHR Tilføjet div index til openpost & ordrer, se opdat_3.5.php ver 3.5.4
// 2015.08.10 PHR Tilføjet tilfravalg til ordrelinjer, se opdat_3.5.php ver 3.5.6
// 2016.01.16 PHR Tilføjet valuta & valutakurs til kontoplan, se opdat_3.6.php ver 3.6.1
// 2016.01.16 PHR Tilføjet fotonavn til varer, se opdat_3.6.php ver 3.6.2
// 2016.01.26 PK Tilføjet kontakt_tlf til ordrer, se opdat_3.6.php ver 3.6.3
// 2016.01.27 PK Tilføjet saldo til adresser, se opdat_3.6.php ver 3.6.3
// 2016.01.28 PHR Tilføjet uxtid til openpost, se opdat_3.6.php ver 3.6.3
// 2016.02.15 PHR Tilføjet tabel varetilbud, se opdat_3.6.php ver 3.6.4
// 2016.05.14 PHR Tilføjet valuta & valutakors til tabel pos_betalinger, se opdat_3.6.php ver 3.6.5
// 2017.02.16 PHR	Indextabeller oprettes kun for postgresql baser da der opstår fejl på mysql
// 2017.03.02 PK Tilføjet tabel bilag_tjekskema, se opdat_3.6.php ver 3.6.6
// 2017.05.02 PHR Tilføjet lager til tabel ordrer se opdat_3.6.php ver 3.6.8
// 2017.09.07 PHR Advokaternes Inkassoservice indsættes som default inkassovirksomhed. ver. 3.6.9  
// 2017.11.17 PHR variant_id indsat i lagerstatus,batch_køb & batch_salg samt shop_varer som shop_variant & saldi_variant. 3.7.0
// 2018.11.26 PHR Tabel settings oprettes og værdier trækkes fra importfiler/settings.txt
// Fra og med 2019 er udviklingssprog engelsk.
// 2019.01.24 PHR Added vat_price in 'ordrelinjer' & 'varer' and vat_account in 'ordrelinjer'. 
// 2019.03.13 LN Added varius tables related to pos orders (3.7.7).
// 2019.04.07 RG (Rune Grysbæk) Mysqli implementation (3.7.8)
// 2019.07.04 PHR Added 'provision' to table 'varer' (3.7.9).
// 2019.09.26 PHR Added field copied til table ordrer and corrected varius price fields to numeric (3.8.3) 
// 2019.10.01	PHR Added fiels houtType in table loen. (3.8.4)
// 2019.10.25	PHR index at ordrer.fakturadate & ordrer.felt_5 (3.8.5)
// 2019.11.08	PHR Added field grossweight & netweight index at varer (3.8.6)
// 2019.11.25	PHR changed id from serial/autoincrement til integer in table drawer, corrections, price_corrections (3.8.7)
// 2019.12.17	PHR Added field modtime at adresser,batch_kob,batch_salg & varer and modtime update trigger (3.8.8)
// 2020.02.28	PHR Added ',0"' to function call formularimport. 
// 2020.05.06	PHR Added tabel mylabel. 
// 2020.06.22	PHR Added tabel labeltemplate. 
// 2020.09.29	PHR Added tabel stocklog. 
// 2020.11.14	PHR Added field netweightunit,grossweightunit,address_id to table varer (3.9.6)
// 2020.11.14	PHR Added field netweight,grossweight,phone to table ordrer (3.9.6)


@session_start();
$s_id=session_id();

ini_set("display_errors","0");
$css="../css/standard.css";
		
include("../includes/connect.php");
include("../includes/std_func.php");

$modulnr=101;
# echo "rev $revisorregnskab<br>";

if (!$_POST['regnskab']||!$_POST['brugernavn']||!$_POST['passwd']||!$_POST['passwd2']) {
	include("../includes/online.php");
	if ($db != $sqdb) {
		print "<BODY onLoad=\"javascript:alert('Hmm du har vist ikke noget at g&oslash;re her! Dit IP nummer, brugernavn og regnskab er registreret!')\">";
		print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
		exit;
	}
}

# if (!$top_bund) $top_bund="style=\"border: 1px solid rgb(0, 0, 0); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_bg.gif\";";
$fra_formular=NULL;

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
	<tr><td align=\"center\" valign=\"top\" height=\"25\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>
			<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=../index/admin_menu.php accesskey=L>Luk</a></td>
			<td width=\"80%\" $top_bund align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Opret regnskab</td>
			<td width=\"10%\" $top_bund align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>
		</tbody></table>
	</td></tr>
<td align = center valign = center>
<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

if ($_POST){
	if (isset($_POST['fra_formular'])) {
		$fra_formular = $_POST['fra_formular'];
		$kontakt=db_escape_string(trim($_POST['kontakt']));
		$firmanavn=db_escape_string(trim($_POST['firmanavn']));
		$addr1=db_escape_string(trim($_POST['addr1']));
		$addr2=db_escape_string(trim($_POST['addr2']));
		$postnr=db_escape_string(trim($_POST['postnr']));
		$bynavn=db_escape_string(trim($_POST['bynavn']));
		$tlf=db_escape_string(trim($_POST['tlf']));
		$email=db_escape_string(trim($_POST['email']));
		$cvrnr=db_escape_string(trim($_POST['cvrnr']));
	}	
	$regnskab=db_escape_string(trim($_POST['regnskab']));
	$brugernavn=db_escape_string(trim($_POST['brugernavn']));
	$passwd=db_escape_string(trim($_POST['passwd']));
	$passwd2=db_escape_string(trim($_POST['passwd2']));
	$posteringer=db_escape_string(trim($_POST['posteringer']));
	$brugerantal=db_escape_string(trim($_POST['brugerantal']));
	$std_kto_plan=$_POST['std_kto_plan'];

	if ((($revisorregnskab && $passwd) || !$revisorregnskab)  && $passwd!=$passwd2 ) {
		print "<BODY onLoad=\"javascript:alert('Adgangskoder er ikke ens')\">";
		forside($regnskab,$brugernavn);
		exit;
	}
/*
	if ($db_encode=="UTF8") {
		$firmanavn=utf8_encode($firmanavn);
		$addr1=utf8_encode($addr1);
		$addr2=utf8_encode($addr2);
		$bynavn=utf8_encode($bynavn);
		$kontakt=utf8_encode($kontakt);
		$regnskab=utf8_encode($regnskab);
		$brugernavn=utf8_encode($brugernavn);
		$passwd=utf8_encode($passwd);
	}
*/
	$x=0;
#	$tmp=db_escape_string($regnskab);
	$query = db_select("select * from regnskab where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab findes allerede')\">";
		forside($regnskab,$brugernavn);
		exit;
	} elseif ((!$revisorregnskab && (!$brugernavn||!$passwd)) || (!$regnskab))  {
		print "<BODY onLoad=\"javascript:alert('Alle felter SKAL udfyldes')\">";
		forside($regnskab,$brugernavn);
		exit();
	} else {
		transaktion(begin);
		$brugerantal=$brugerantal*1;
		$posteringer=$posteringer*1;
		db_modify("insert into regnskab (regnskab,dbhost,dbuser,brugerantal,posteringer,email,bilag) values ('$regnskab','$sqhost','$squser','$brugerantal','$posteringer','$email','0')",__FILE__ . " linje " . __LINE__);
		$row=db_fetch_array(db_select("select id from regnskab where regnskab='$regnskab' and dbhost='$sqhost' and dbuser='$squser'",__FILE__ . " linje " . __LINE__));
		$db_id=$row['id'];
		$db=$sqdb."_".$row[id];
		db_modify("update regnskab set db='$db' where id=$row[id]",__FILE__ . " linje " . __LINE__); 

		$q = db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
		if ($bruger_id=$r['id']) {
			$rettigheder=$r['rettigheder'];
			list($admin,$oprette,$slette,$tmp)=explode(",",$rettigheder,4);
			$adgang_til=explode(",",$tmp);
			if ($adgang_til!="*") {
				if (substr($rettigheder,-1)==",") $rettigheder=$rettigheder.$db_id;
				else $rettigheder=$rettigheder.",".$db_id;
				db_modify("update brugere set rettigheder='$rettigheder' where id='$bruger_id'",__FILE__ . " linje " . __LINE__);
			}
		}
		transaktion(commit);
#		$brugernavn=db_escape_string($brugernavn);
#		$passwd=db_escape_string($passwd);
		opret($sqhost,$squser ,$sqpass,$db,$brugernavn,$passwd,$std_kto_plan);
#		print "<tr><td align=center colspan=3><b><big> Regnskab $regnskab er oprettet</b></big></td></tr>";
		exit();
	}
} else {
	if (!isset($regnskab)) $regnskab='';
	if (!isset($brugernavn)) $brugernavn='';
	forside($regnskab,$brugernavn);
print "</tbody></table";
}

function forside($regnskab,$brugernavn) {
	global $charset;

	print "<form name=debitorkort action=opret.php method=post>";
	print "<tr><td> Navn p&aring; regnskab</td><td><br></td><td><input type=text size=25 name=regnskab value='$regnskab'></td></tr>";
	print "<tr><td> Administrators navn</td><td><br></td><td><input type=text size=25 name=brugernavn value='$brugernavn'></td></tr>";
	print "<tr><td> Administrators adgangskode</td><td><br></td><td><input type=password size=25 name=passwd></td></tr>";
	print "<tr><td> Gentag adgangskode</td><td><br></td><td><input type=password size=25 name=passwd2></td></tr>";
	print "<tr><td> Opret standardkontoplan</td><td><br></td><td><input type=checkbox name=std_kto_plan checked></td></tr>";
	print "<tr><td><br></td></tr><tr><td><br></td></tr><tr><td><br></td></tr>";
	print "<tr><td colspan=3 align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td></tr>";
	print "</form>";
}

function opret ($sqhost,$squser,$sqpass,$db,$brugernavn,$passwd,$std_kto_plan) {
	global $bruger_id,$connection;
	global $db_id,$db_encode,$db_type;
	global $fra_formular,$regnskab;
	global $s_id,$sqdb,$version;
	if ($fra_formular) {
		global $cvrnr,$kontakt,$firmanavn;
		global $addr1,$addr2;
		global $postnr,$bynavn;
		global $tlf,$email;
	}
	
	db_create($db);
/*
	if ($db_type=="mysql" or $db_type=="mysqli") { #RG_mysqli
		db_modify("CREATE DATABASE $db",__FILE__ . " linje " . __LINE__);
		if ($db_type=="mysql") mysql_select_db("$db");
		else mysqli_select_db($connection,$db);
		if ($db_encode=="UTF8") db_modify("SET character_set_client = 'UTF8'",__FILE__ . " linje " . __LINE__);
		else db_modify("SET character_set_client = 'LATIN1'",__FILE__ . " linje " . __LINE__);
	} else {
		if ($db_encode=="UTF8") db_modify("CREATE DATABASE $db encoding = 'UTF8' template template0",__FILE__ . " linje " . __LINE__);
		else db_modify("CREATE DATABASE $db encoding = 'LATIN9' template template0",__FILE__ . " linje " . __LINE__);
#		db_modify("delete from online where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
#		db_modify("insert into online (session_id, brugernavn, db, dbuser, regnskabsaar, logtime) values ('$s_id', '".db_escape_string($brugernavn)."', '$db', '$squser', '1', '".date("U")."')",__FILE__ . " linje " . __LINE__);
		db_close($connection);
		$connection = db_connect ("$sqhost","$squser","$sqpass","$db",__FILE__ . " linje " . __LINE__);
	}
#	include ("../includes/online.php");
*/	
	transaktion("begin");
#	db_modify("CREATE SEQUENCE id START 1 INCREMENT 1 MAXVALUE 9223372036854775807 MINVALUE 1 CACHE 1",__FILE__ . " linje " . __LINE__);
	######## Adresser ##########
	$qtxt = "CREATE TABLE adresser ";
	$qtxt.= "(id serial NOT NULL,firmanavn varchar(60),addr1 varchar(60),addr2 varchar(60),postnr varchar(10),bynavn varchar(60),";
	$qtxt.= "land varchar(60),kontakt varchar(60),tlf varchar(15),fax varchar(15),email varchar(60),web varchar(60),";
	$qtxt.= "bank_navn varchar(60),bank_reg varchar(15),bank_konto varchar(15),bank_fi varchar(15),erh varchar(15),swift varchar(15),";
	$qtxt.= "notes text,rabat numeric(15,3),momskonto integer,kreditmax numeric(15,3),betalingsbet varchar(15),";
	$qtxt.= "betalingsdage integer DEFAULT 0,kontonr numeric(15,0),cvrnr varchar(15),ean varchar(15),institution varchar(15),";
	$qtxt.= "art varchar(2),gruppe integer,rabatgruppe integer,kontoansvarlig integer,oprettet date,kontaktet date,";
	$qtxt.= "kontaktes date,pbs varchar(2),pbs_nr text,pbs_date date,mailfakt varchar(2),udskriv_til varchar(10),felt_1 text,felt_2 text,";
	$qtxt.= "felt_3 text,felt_4 text,felt_5 text,vis_lev_addr varchar(2),kontotype varchar(15),fornavn varchar(60),";
	$qtxt.= "efternavn varchar(60),lev_firmanavn varchar(60),lev_fornavn varchar(60),lev_efternavn varchar(60),lev_addr1 varchar(60),"; $qtxt.= "lev_addr2 varchar(60),lev_postnr varchar(15),lev_bynavn varchar(60),lev_land varchar(60),lev_kontakt varchar(60),";
	$qtxt.= "lev_tlf varchar(15),lev_email varchar(60),status varchar(15),lukket varchar(2),kategori varchar(15),saldo numeric(15,3),";
	$qtxt.= "invoiced date,mysale varchar(2),PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE ansatte (id serial NOT NULL,konto_id integer,navn text,addr1 text,addr2 text,postnr text,bynavn text,tlf text,fax text,mobil text,privattlf text,initialer text,email text,notes text,cprnr text,posnr integer,afd integer,provision numeric(15,3),nummer integer,loen numeric(15,3),hold integer,lukket varchar(2),bank text,startdate date,slutdate date,gruppe numeric(15,3),extraloen numeric(15,3),trainee text,password text,overtid numeric(1,0),sag_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE  crm (id serial NOT NULL,konto_id int,kontakt_id int,ansat_id int,notat text,notedate date,spor text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE brugere(id serial NOT NULL,brugernavn text,kode text,tmp_kode text,status boolean,regnskabsaar integer,rettigheder text,ansat_id integer,sprog_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE grupper (id serial NOT NULL,beskrivelse text,kode text,kodenr text,art text,box1 text,box2 text,box3 text,box4 text,box5 text,box6 text,box7 text,box8 text,box9 text,box10 text,box11 text,box12 text,box13 text,box14 text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	######## Kassekladde ########
	db_modify("CREATE TABLE kassekladde (id serial NOT NULL,bilag integer,transdate date,beskrivelse text,d_type varchar(1),debet numeric(15,0),k_type varchar(1),kredit numeric(15,0),faktura text,amount numeric(15,3),kladde_id integer,momsfri varchar(2),medarb integer,ansat text,afd integer,projekt text,valuta integer,valutakurs numeric(15,3),ordre_id integer,forfaldsdate date,betal_id text,dokument text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE tmpkassekl (id integer,lobenr integer,bilag text,transdate text,beskrivelse text,d_type text,debet text,k_type text,kredit text,faktura text,amount text,kladde_id integer,momsfri text,afd text,projekt text,ansat text,valuta text,valutakurs text,forfaldsdate text,betal_id text,dokument text)",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE kladdeliste (id serial NOT NULL,kladdedate date,bogforingsdate date,kladdenote text,bogfort varchar(2),oprettet_af text,bogfort_af text,hvem text,tidspkt text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE kontoplan (id serial NOT NULL,kontonr numeric(15,0),beskrivelse text,kontotype varchar(1),moms text,fra_kto numeric(15,0),til_kto numeric(15,0),lukket varchar(2),primo numeric(15,3),saldo numeric(15,3),regnskabsaar integer,genvej varchar(2),overfor_til numeric(15,0),anvendelse text,modkonto numeric(15,0),valuta integer,valutakurs numeric(15,4),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE kontokort (id serial NOT NULL,ref_id integer,faktnr integer,refnr integer,beskrivelse text,kredit numeric(15,0),debet numeric(15,0),transdate date,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE ordrer (id serial NOT NULL,konto_id integer,firmanavn text,addr1 text,addr2 text,postnr text,bynavn text,";
	$qtxt.= "land text,kontakt text,email text,mail_fakt varchar(2),udskriv_til varchar(10),kundeordnr text,lev_navn text,";
	$qtxt.= "lev_addr1 text,lev_addr2 textlev_postnr text,lev_bynavn text,lev_kontakt text,ean text,institution text,";
	$qtxt.= "betalingsbet text,betalingsdage integer,kontonr text,cvrnr text,art varchar(2),valuta text,valutakurs numeric(15,3),";
	$qtxt.= "sprog text,projekt text,ordredate date,levdate date,fakturadate date,notes text,ordrenr integer,sum numeric(15,3),";
	$qtxt.= "momssats numeric(15,3),status integer,ref text,fakturanr text,modtagelse integer,kred_ord_id integer,lev_adr text,";
	$qtxt.= "kostpris numeric(15,3),moms numeric(15,3),hvem text,tidspkt text,betalt varchar(12),nextfakt date,pbs varchar(2),";
	$qtxt.= "mail varchar(2),mail_cc text,mail_bcc text,mail_subj text,mail_text text,felt_1 text,felt_2 text,felt_3 text,";
	$qtxt.= "felt_4 text,felt_5 text,vis_lev_addr varchar(2),restordre numeric(2,0), betalings_id text,sag_id integer,"; 
	$qtxt.= "tilbudnr numeric(15,0),datotid text,nr numeric(15,0),returside text,sagsnr numeric(15,0),dokument text, ";
	$qtxt.= "procenttillag numeric(15,3),mail_bilag varchar(2),omvbet varchar(2),afd integer,lager integer,kontakt_tlf text,";
	$qtxt.= "copied boolean,phone varchar(15),PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE ordrelinjer ";
	$qtxt.= "(id serial NOT NULL,varenr text,beskrivelse text,enhed text,posnr integer,pris numeric(15,3),vat_price numeric(15,3),";
	$qtxt.= "rabat numeric(15,3),lev_varenr text,ordre_id integer,serienr text,vare_id integer,antal numeric(15,3),leveres numeric(15,3),";
	$qtxt.= "leveret numeric(15,3),bogf_konto integer,oprettet_af text,bogfort_af text,hvem text,tidspkt text,kred_linje_id integer,";
	$qtxt.= "momsfri varchar(2),momssats numeric(15,3),vat_account numeric(15,0),kostpris numeric(15,3),samlevare varchar(2),projekt text,";
	$qtxt.= "m_rabat numeric(15,3),rabatgruppe integer,folgevare integer,kdo varchar(2),rabatart varchar(10),variant_id text,procent numeric(15,3),";
	$qtxt.= "omvbet varchar(2),saet integer,fast_db numeric(15,3),afd integer,lager integer,tilfravalg text,PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE ordretekster (id serial NOT NULL,tekst text,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE openpost (id serial NOT NULL,konto_id integer,konto_nr text,faktnr text,amount numeric(15,3),refnr integer,beskrivelse text,udlignet varchar(2),transdate date,uxtid text,kladde_id integer,bilag_id integer,udlign_id integer,udlign_date date,valuta text,projekt text,valutakurs numeric(15,3),forfaldsdate date,betal_id text,betalings_id text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE transaktioner (id serial NOT NULL,kontonr numeric(15,0),bilag numeric(15,0),transdate date,logtime time,beskrivelse text,debet numeric(15,3),kredit numeric(15,3),faktura text,kladde_id integer,projekt text,ansat numeric(15,0),logdate date,afd integer,ordre_id integer,valuta text,valutakurs numeric(15,3),moms numeric(15,3),adresser_id int4,kasse_nr numeric(15,0),land varchar(3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE simulering (id serial NOT NULL,kontonr numeric(15,0),bilag numeric(15,0),transdate date,beskrivelse text,debet numeric(15,3),kredit numeric(15,3),faktura text,kladde_id int4,projekt text,ansat numeric(15,0),logdate date,logtime time,afd int4,ordre_id int4,valuta text,valutakurs numeric(15,3),moms numeric(15,3),adresser_id int4,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE varer ";
	$qtxt.= "(id serial NOT NULL,varenr text,stregkode text,beskrivelse text,enhed text,enhed2 text,indhold numeric(15,3)";
	$qtxt.= ",forhold numeric(15,3),gruppe text,salgspris numeric(15,3),vat_price numeric(15,3),kostpris numeric(15,3),";
	$qtxt.= "provisionsfri varchar(2),notes text,lukket varchar(2),serienr text,beholdning numeric(15,3),samlevare varchar(2),";
	$qtxt.= "delvare varchar(2),min_lager numeric(15,3),max_lager numeric(15,3), trademark text,location text,";
	$qtxt.= "retail_price numeric(15,3),special_price numeric(15,3),campaign_cost numeric(15,3),tier_price numeric(15,3),";
	$qtxt.= "open_colli_price numeric(15,3),colli numeric(15,3),outer_colli numeric(15,3),outer_colli_price numeric(15,3),";
	$qtxt.= "special_from_date date,special_to_date date,special_from_time time,special_to_time time,komplementaer text,";
	$qtxt.= "circulate integer,operation integer,prisgruppe integer,tilbudgruppe integer,rabatgruppe integer,dvrg integer,";
	$qtxt.= "m_type varchar(10),m_rabat text,m_antal text,folgevare text,kategori text,varianter text,publiceret varchar(2),";
	$qtxt.= "montage numeric(15,3),demontage numeric(15,3),fotonavn text,tilbudsdage text,provision integer,netweight numeric(15,3),";
	$qtxt.= "netweightunit varchar(2),grossweight numeric(15,3),grossweightunit varchar(2),gavekort varchar(2),specialtype varchar(10),";
	$qtxt.= "address_id int,length numeric(15,3),width numeric(15,3),height numeric(15,3),PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE lagerstatus (id serial NOT NULL,lager integer,vare_id integer,variant_id integer,beholdning numeric(15,3),lok1 text,lok2 text,lok3 text,lok4 text,lok5 text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE batch_kob (id serial NOT NULL,kobsdate date,fakturadate date,vare_id integer,variant_id integer,linje_id integer,ordre_id integer,pris numeric(15,3),antal numeric(15,3),rest numeric(15,3),lager integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE batch_salg (id serial NOT NULL,salgsdate date,fakturadate date,batch_kob_id integer,vare_id integer,variant_id integer,linje_id integer,ordre_id integer,pris numeric(15,3),antal numeric(15,3),lev_nr integer,lager integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE serienr (id serial NOT NULL,vare_id integer,kobslinje_id integer,salgslinje_id integer,batch_kob_id integer,batch_salg_id integer,serienr text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE styklister (id serial NOT NULL,vare_id integer,indgaar_i integer,antal numeric(15,3),posnr integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE enheder (id serial NOT NULL,betegnelse text,beskrivelse text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE materialer (id serial NOT NULL,beskrivelse text,densitet numeric(15,3),materialenr text,tykkelse numeric(15,3),kgpris numeric(15,3),avance numeric(15,3),enhed text,opdat_date date,opdat_time time,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE vare_lev (id serial NOT NULL,posnr integer,lev_id integer,vare_id integer,lev_varenr text,kostpris numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE reservation (linje_id integer,batch_kob_id integer,batch_salg_id integer,vare_id integer,antal numeric(15,3),lager integer)",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE formularer (id serial NOT NULL,formular integer,art integer,beskrivelse text,justering text,xa numeric(15,3),ya numeric(15,3),xb numeric(15,3),yb numeric(15,3),str numeric(15,3),color integer,font text,fed varchar(2),kursiv varchar(2),side varchar(2),sprog text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE provision (id serial NOT NULL,gruppe_id integer,ansat_id integer,provision numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE historik (id serial NOT NULL,konto_id int,kontakt_id int,ansat_id int,notat text,notedate date,kontaktet date,kontaktes date,dokument text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE valuta (id serial NOT NULL,gruppe integer,valdate date,kurs numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE betalingsliste (id serial NOT NULL,listedate date,udskriftsdate date,listenote text,bogfort varchar(2),oprettet_af text,bogfort_af text,hvem text,tidspkt text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE betalinger (id serial NOT NULL,bet_type text,fra_kto text,egen_ref text,til_kto text,modt_navn text,belob text,betalingsdato text,valuta text,kort_ref text,kvittering text,ordre_id integer,bilag_id integer,liste_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE tidsreg (id serial NOT NULL,person integer,ordre integer,pnummer integer,operation integer,materiale integer,tykkelse numeric(15,3),laengde numeric(15,3),bredde numeric(15,3),antal_plader numeric(15,3), gaa_hjem integer,tid integer,forbrugt_tid integer,opsummeret_tid integer,beregnet integer,pause integer,antal numeric(15,3), faerdig integer,circ_time integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE tabeller (id serial NOT NULL,person integer,ordre integer,pnummer integer,	operation integer,materiale integer,tykkelse numeric(15,3),laengde numeric(15,3),bredde numeric(15,3),antal_plader numeric(15,3), gaa_hjem integer,tid integer,forbrugt_tid integer,opsummeret_tid integer,beregnet integer,pause integer,antal numeric(15,3), faerdig integer,circ_time integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE tekster (id serial NOT NULL,sprog_id integer,tekst_id integer,tekst text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE varetekster (id serial NOT NULL,sprog_id integer,vare_id integer,tekst text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE jobkort (id serial NOT NULL,konto_id integer,ordre_id integer,kontonr text,firmanavn text,addr1 text,addr2 text,postnr text,bynavn text,kontakt text,tlf text,initdate date,oprettet_af text,startdate date,slutdate date,hvem text,tidspkt text,felt_1 text,felt_2 text,felt_3 text,felt_4 text,felt_5 text,felt_6 text,felt_7 text,felt_8 text,felt_9 text,felt_10 text,felt_11 text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE jobkort_felter (id serial NOT NULL,job_id integer,art text,feltnr integer,subnr integer,feltnavn text,indhold text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE modtageliste (id serial NOT NULL,initdate date,modtagdate date,modtagnote text,modtaget text,init_af text,modtaget_af text,hvem text,tidspkt text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE modtagelser (id serial NOT NULL,varenr text,beskrivelse text,leveres numeric(15,3),liste_id integer,lager numeric(15,3),ordre_id integer,vare_id integer,antal numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE pbs_kunder(id serial NOT NULL,konto_id integer,kontonr varchar(20),pbs_nr text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE pbs_liste(id serial NOT NULL,liste_date date,afsendt varchar(8),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE pbs_ordrer(id serial NOT NULL,liste_id integer,ordre_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE pos_betalinger (id serial NOT NULL,ordre_id integer,betalingstype varchar(40),amount numeric(15,3),valuta varchar(3),valutakurs numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE pbs_linjer(id serial NOT NULL,liste_id integer,linje text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE budget (id serial NOT NULL,regnaar integer,md integer, kontonr numeric(15,0),amount numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE rabat(id serial NOT NULL,rabat numeric(6,2),debitorart varchar(2),debitor int,vareart varchar(2),vare int,rabatart varchar(6),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE pos_buttons (id serial NOT NULL,menu_id integer,col numeric(2,0),row numeric(2,0),colspan numeric(1,0),rowspan numeric(1,0),beskrivelse text,vare_id numeric(10,0),funktion numeric(1,0),color varchar(6),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE regulering (id serial NOT NULL,vare_id integer, variant_id integer,lager integer,beholdning numeric(15,3),optalt numeric(15,3),tidspkt text,bogfort bool,transdate date,logtime time,bogfort_af text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE navigator (bruger_id integer,session_id text,side text,returside text,konto_id integer,ordre_id integer,vare_id integer)",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE kostpriser (id serial NOT NULL,vare_id integer,transdate date,kostpris numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	
	db_modify("CREATE TABLE sager (id serial NOT NULL,konto_id integer,firmanavn text,addr1 text,addr2 text,postnr text,bynavn text,land text,kontakt text,email text,beskrivelse text,omfang text,ref text,udf_firmanavn text,udf_addr1 text,udf_addr2 text,udf_postnr text,udf_bynavn text,udf_kontakt text,status text,tidspkt text,hvem text,oprettet_af text,kunde_ref text,planfraop text,plantilop text,planfraned text,plantilned text,beregn_opret text,beregn_tilbud text,beregner text,beregn_beskrivelse text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE bilag (id serial NOT NULL,navn text,beskrivelse text,datotid text,hvem text,assign_to text,assign_id int,fase numeric(15,3),kategori text,filtype text,bilag_fase text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE noter (id serial NOT NULL,notat text,beskrivelse text,datotid text,hvem text,besked_til text,assign_to text,assign_id integer,status integer,fase numeric(15,3),notat_fase text,kategori text,nr numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE tjekliste (id serial NOT NULL,tjekpunkt text,fase numeric(15,3),assign_to text,assign_id integer,sagsnr text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE tjekpunkter (id serial NOT NULL,tjekliste_id integer,assign_id integer,status integer,status_tekst text,tjekskema_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE sagstekster (id serial NOT NULL,tekstnr numeric(15,0),beskrivelse text,tekst text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE loen (id serial NOT NULL,nummer numeric(15,0),kategori integer,loendate date,sag_id integer, sag_nr numeric(15,0),tekst text,ansatte text,fordeling text,timer text,hourType text,t50pct text,t100pct text,hvem text,oprettet text,afsluttet text,godkendt text,sum numeric(15,3),oprettet_af text,afsluttet_af text,godkendt_af text,master_id integer,loen text,afvist text,afvist_af text,udbetalt text,art text,skur text,datoer text,afregnet text,afregnet_af text,korsel text,opg_id integer,opg_nr integer,afvist_pga text,sag_ref text,feriefra text,ferietil text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE loen_enheder (id serial NOT NULL,loen_id integer,vare_id integer,op numeric(15,3),ned numeric(15,3),tekst text,pris_op numeric(15,3),pris_ned numeric(15,3),op_25 numeric(15,3),ned_25 numeric(15,3),op_30m numeric(15,3),ned_30m numeric(15,3),op_40 numeric(15,3),ned_40 numeric(15,3),op_60 numeric(15,3),ned_60 numeric(15,3),op_tag numeric(15,3),ned_tag numeric(15,3),varenr text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE opgaver (id serial NOT NULL,assign_id integer,assign_to text,nr numeric(15,0),beskrivelse text,omfang text,ref text,status text,tidspkt text,hvem text,oprettet_af text,kunde_ref text,opg_planfra text,opg_plantil text,opg_tilknyttil text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE tjekskema (id serial NOT NULL,tjekliste_id integer,datotid text,opg_art text,sjak text,sag_id integer,hvem text,man_trans text,stillads_til text,opg_navn text,opg_beskrivelse text,sjakid text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE mappe (id serial NOT NULL,beskrivelse text,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE mappebilag (id serial NOT NULL,navn text,beskrivelse text,datotid text,hvem text,assign_to text,assign_id int4,filtype text,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE ansatmappe (id serial NOT NULL,beskrivelse text,ans_id int4,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE ansatmappebilag (id serial NOT NULL,navn text,beskrivelse text,datotid text,hvem text,assign_to text,assign_id int4,filtype text,sort numeric(15,0),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE bilag_tjekskema (id serial NOT NULL,tjekskema_id integer,bilag_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	$qtxt="CREATE TABLE settings ";
	$qtxt.="(id serial NOT NULL, var_name text, var_grp text, var_value text, var_description text, user_id integer, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE shop_adresser (id serial NOT NULL,saldi_id integer,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE shop_varer (id serial NOT NULL,saldi_id integer,saldi_variant integer,shop_id integer,shop_variant integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE shop_ordrer (id serial NOT NULL,saldi_id integer,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE varianter (id serial NOT NULL,beskrivelse text,shop_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE variant_typer (id serial NOT NULL,variant_id integer,shop_id integer,beskrivelse text,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE variant_varer (id serial NOT NULL,vare_id integer,variant_type text,variant_beholdning numeric(15,3),variant_stregkode text,lager integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE varetilbud (id serial NOT NULL,vare_id integer,startdag numeric(15,0),slutdag numeric(15,0),starttid time,sluttid time,ugedag integer,salgspris numeric(15,2),kostpris numeric(15,2),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	$qtxt="CREATE TABLE misc_meta_data ";
	$qtxt.="(id serial NOT NULL, meta_name text, meta_grp text, meta_no integer,";
	$qtxt.=" meta_value text, meta_description text, user_id integer, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE drawer (id integer, openings integer)", __FILE__ . "linje" . __LINE__);
	$qtxt="CREATE TABLE proforma (id serial NOT NULL, price numeric(15,3), count integer, PRIMARY KEY (id))";	
	db_modify($qtxt, __FILE__ . "linje" . __LINE__);
	$qtxt="CREATE TABLE deleted_order (id serial NOT NULL, price numeric(15,3), kasse integer, ordre_id integer, PRIMARY KEY (id))";
	db_modify($qtxt, __FILE__ . "linje" . __LINE__);
	db_modify("CREATE TABLE corrections (id integer, price numeric(15,3), kasse integer)", __FILE__ . "linje" . __LINE__);
	db_modify("CREATE TABLE report (id serial NOT NULL, date date, type text, description text, count integer, total numeric(15,3), report_number integer, PRIMARY KEY (id))", __FILE__ . "linje" . __LINE__);
	db_modify("CREATE TABLE price_correction (id integer, price numeric(15,3), kasse integer)", __FILE__ . "linje" . __LINE__);
	$qtxt = "CREATE TABLE mylabel (id serial NOT NULL, account_id integer, page numeric(5,0), row numeric(5,0), col numeric(5,0), ";
	$qtxt.= "price numeric(15,3), description varchar (40), condition varchar(10), barcode varchar (20), hidden boolean, sold boolean, ";
	$qtxt.= "PRIMARY KEY (id))";
	db_modify($qtxt, __FILE__ . "linje" . __LINE__);
	$qtxt = "CREATE TABLE labeltemplate (id serial NOT NULL, account_id integer, description varchar (40), ";
	$qtxt.= "labeltext text, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="CREATE TABLE gavekort (id serial NOT NULL, gavekortnr numeric(15,0), PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="CREATE TABLE gavekortbrug (id serial NOT NULL, gavekortid integer, saldo numeric(15,2), ordre_id integer, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
	$qtxt="CREATE TABLE queries (id serial NOT NULL, query text, query_descrpition text, user_id integer, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE labels (id serial NOT NULL, account_id integer, labeltype varchar (10), labelname varchar (40), ";
	$qtxt.= "labeltext text, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE stocklog (id serial NOT NULL, item_id integer, username varchar (10), initials varchar (10), ";
	$qtxt.= "correction numeric(15,3), reason text,logtime varchar (10), PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);

	
	if ($db_type=="mysql" || $db_type=="mysqli") {
		db_modify("ALTER TABLE adresser ADD modtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", __FILE__ . "linje" . __LINE__);
		db_modify("ALTER TABLE batch_kob ADD modtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", __FILE__ . "linje" . __LINE__);
		db_modify("ALTER TABLE batch_salg ADD modtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", __FILE__ . "linje" . __LINE__);
		db_modify("ALTER TABLE varer ADD modtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", __FILE__ . "linje" . __LINE__);
	} else {
		$qtxt="SELECT lanname from pg_language WHERE lanname = 'plpgsql'";
		$r=db_fetch_array(db_select($qtxt, __FILE__ . "linje" . __LINE__));
		if (!$r['lanname']) {
		db_modify("CREATE language plpgsql", __FILE__ . "linje" . __LINE__);
		}
		db_modify("ALTER TABLE adresser ADD modtime TIMESTAMP DEFAULT now()", __FILE__ . "linje" . __LINE__);
		db_modify("ALTER TABLE batch_kob ADD modtime TIMESTAMP DEFAULT now()", __FILE__ . "linje" . __LINE__);
		db_modify("ALTER TABLE batch_salg ADD modtime TIMESTAMP DEFAULT now()", __FILE__ . "linje" . __LINE__);
		db_modify("ALTER TABLE varer ADD modtime TIMESTAMP DEFAULT now()", __FILE__ . "linje" . __LINE__);
		$qtxt = "CREATE OR REPLACE FUNCTION update_modtime_column() \n";
		$qtxt.= "RETURNS TRIGGER AS $$ ";
		$qtxt.= "BEGIN ";
		$qtxt.= "NEW.modtime = now(); "; 
		$qtxt.= "RETURN NEW; ";
		$qtxt.= "END; ";
		$qtxt.= "$$ language 'plpgsql';";
		pg_query($qtxt);
		$qtxt = "CREATE TRIGGER update_adresser_modtime BEFORE UPDATE ";
		$qtxt.= "ON adresser FOR EACH ROW EXECUTE PROCEDURE ";
		$qtxt.= "update_modtime_column(); ";
		pg_query($qtxt);
		$qtxt = "CREATE TRIGGER update_batch_kob_modtime BEFORE UPDATE ";
		$qtxt.= "ON batch_kob FOR EACH ROW EXECUTE PROCEDURE ";
		$qtxt.= "update_modtime_column(); ";
		pg_query($qtxt);
		$qtxt = "CREATE TRIGGER update_batch_salg_modtime BEFORE UPDATE ";
		$qtxt.= "ON batch_salg FOR EACH ROW EXECUTE PROCEDURE ";
		$qtxt.= "update_modtime_column(); ";
		pg_query($qtxt);
		$qtxt = "CREATE TRIGGER update_varer_modtime BEFORE UPDATE ";
		$qtxt.= "ON varer FOR EACH ROW EXECUTE PROCEDURE ";
		$qtxt.= "update_modtime_column(); ";
		pg_query($qtxt);
		$qtxt="UPDATE grupper set box1='$nextver' where art = 'VE'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}

	if ($db_type == "postgresql") {
		db_modify("CREATE INDEX batch_kob_antal_idx ON batch_kob (antal)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX batch_kob_fakturadate_idx ON batch_kob (fakturadate)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX batch_kob_id_idx ON batch_kob (id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX batch_kob_kobsdate_idx ON batch_kob (kobsdate)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX batch_kob_linje_id_idx ON batch_kob (linje_id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX batch_kob_vare_id_idx ON batch_kob (vare_id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX batch_salg_antal_idx ON batch_salg (antal)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX batch_salg_fakturadate_idx ON batch_salg (fakturadate)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX batch_salg_salgsdate_idx ON batch_salg (salgsdate)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX batch_salg_vare_id_idx ON batch_salg (vare_id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX openpost_id_idx ON openpost (id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX openpost_konto_id_idx ON openpost (konto_id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX openpost_udlign_id_idx ON openpost (udlign_id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX ordrelinjer_id_idx ON ordrelinjer (id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX ordrelinjer_ordre_id_idx ON ordrelinjer (ordre_id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX ordrelinjer_vare_id_idx ON ordrelinjer (vare_id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX ordrer_art_idx ON ordrer (art)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX ordrer_betalt_idx ON ordrer (betalt)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX ordrer_id_idx ON ordrer (id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX ordrer_fakturadate_idx ON ordrer (fakturadate)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX ordrer_felt_5_idx ON ordrer (felt_5)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX ordrer_ordrenr_idx ON ordrer (ordrenr)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX pos_betalinger_ordre_id_idx ON pos_betalinger (ordre_id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX pos_betalinger_betalingstype_idx ON pos_betalinger (betalingstype)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX transaktioner_id_idx ON transaktioner (id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX transaktioner_transdate_idx ON transaktioner (transdate)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX transaktioner_kontonr_idx ON transaktioner (kontonr)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX varer_beskrivelse_idx ON varer (beskrivelse)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX varer_id_idx ON varer (id)",__FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX varer_varenr_idx ON varer (varenr)",__FILE__ . " linje " . __LINE__);
	}
	db_modify("INSERT INTO brugere (brugernavn,rettigheder,regnskabsaar) values ('$brugernavn','11111111111111111111',1)",__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array(db_select("select id from brugere where brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__));
	$pw=saldikrypt($r['id'],$passwd);
	db_modify("UPDATE brugere set kode ='$pw' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	db_modify("insert into grupper (beskrivelse,art,box1) values ('Version','VE','$version')",__FILE__ . " linje " . __LINE__);
	db_modify("insert into grupper (beskrivelse,kodenr,art,box4,box5) values ('Div_valg','2','DIV','','')",__FILE__ . " linje " . __LINE__);
	db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('Div_valg','3','DIV','','','','on','on','on','','','','')",__FILE__ . " linje " . __LINE__);
	db_modify("insert into grupper (beskrivelse,kode,kodenr,art) values ('Dansk','DA','1','SPROG')",__FILE__ . " linje " . __LINE__);
	db_modify("insert into enheder (betegnelse,beskrivelse) values ('stk','styk')",__FILE__ . " linje " . __LINE__);
	db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box1,box2) values ('Administratorer','','0','brgrp','','11111111')",__FILE__ . " linje " . __LINE__);

	$fp=fopen("../importfiler/settings.txt","r");
	while ($line=fgets($fp)) {
		list($var_name,$var_value,$description)=explode(",",$line);
		$var_name=db_escape_string($var_name);
		$var_value=db_escape_string($var_value);
		$description=db_escape_string($description);
		$qtxt="insert into settings(var_name,var_grp,var_value,var_description,user_id) values ('$name','globals','$value','$description','0')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	fclose($fp);

	if ($std_kto_plan) {
#	 include("../includes/kontoplan.php"); 
		if (file_exists("../importfiler/egen_kontoplan.txt")) $fp=fopen("../importfiler/egen_kontoplan.txt","r");
		else $fp=fopen("../importfiler/kontoplan.txt","r");
		if ($fp) {
			$x=0;
			while (!feof($fp)) {
				$x++;	
				list($kontonr[$x],$beskrivelse[$x],$kontotype[$x],$moms[$x],$fra_kto[$x],$valuta[$x],$valutakurs[$x]) = explode(chr(9),fgets($fp));
				if (!$kontonr[$x]) {$x--;}
			}
			$kontoantal=$x;
			for ($x=1; $x<=$kontoantal; $x++){
				$beskrivelse[$x]=db_escape_string(trim(str_replace('"','',$beskrivelse[$x])));
				if ($db_encode=="UTF8") $beskrivelse[$x]=utf8_encode($beskrivelse[$x]);
				$kontotype[$x]=trim(str_replace('"','',$kontotype[$x]));
				$moms[$x]=trim(str_replace('"','',$moms[$x]));
				$fra_kto[$x]=$fra_kto[$x]*1;
				if (!$valuta[$x]) $valuta[$x]='0';
				if (!$valutakurs[$x]) $valutakurs[$x]='100';
				db_modify("INSERT INTO kontoplan (kontonr,beskrivelse,kontotype,fra_kto,moms,regnskabsaar,lukket,valuta,valutakurs) values ('$kontonr[$x]','$beskrivelse[$x]','$kontotype[$x]','$fra_kto[$x]','$moms[$x]','1','','$valuta[$x]','$valutakurs[$x]')",__FILE__ . " linje " . __LINE__);
			}
			fclose($fp);
		}
		if (file_exists("../importfiler/egne_grupper.txt")) $fp=fopen("../importfiler/egne_grupper.txt","r");
		else $fp=fopen("../importfiler/grupper.txt","r");
		if ($fp) {
			while (!feof($fp)) {
				$x++;
				$linje=fgets($fp);
				if ($linje && substr($linje,0,1)!="#") {
					$linje=trim($linje);
					if ($db_encode!="UTF8") $linje=utf8_decode($linje);
					# if (strstr($linje,"'")>=28 && strstr($linje,",")>=13)zz
					db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14) values ($linje)",__FILE__ . " linje " . __LINE__);
					# else print "<BODY onLoad=\"javascript:alert('Fejl i gruppefil, regnskab ikke oprettet korrekt')\">";
				}
			}
			fclose($fp);
			if ($sqdb=='rotary') {
				$startmd='07';
				$slutmd='06';
				(date('m')>=7)?$startaar=date("Y"):$startaar=date("Y")-1;
				$slutaar=$startaar+1;
				$ra_besk=$startaar."/".$slutaar;
	} else {
				$startmd='01';
				$slutmd='12';
				$startaar=date("Y");
				$slutaar=date("Y");
				$ra_besk=$startaar;
			}
			db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14) values ('$ra_besk','','1','RA','$startmd','$startaar','$slutmd','$slutaar','on','0','','','','','','','','')",__FILE__ . " linje " . __LINE__);
		}
		if (file_exists("../importfiler/egne_varer.txt")) {
			$fp=fopen("../importfiler/egne_varer.txt","r");
			if ($fp) {
				while (!feof($fp)) {
					$x++;
					$linje=fgets($fp);
					if ($linje && substr($linje,0,1)!="#") {
						$linje=trim($linje);
						if ($db_encode!="UTF8") $linje=utf8_decode($linje);
						db_modify("insert into varer (varenr,beskrivelse,gruppe,salgspris,kostpris,lukket) values ($linje)",__FILE__ . " linje " . __LINE__);
					}
				}
				fclose($fp);
			}
		}
		include("../includes/formularimport.php");
		formularimport("../importfiler/formular.txt","0"); #20200227
		db_modify("update formularer set sprog = 'Dansk'",__FILE__ . " linje " . __LINE__);
		if ($fra_formular) {
			db_modify("insert into adresser (firmanavn,addr1,addr2,postnr,bynavn,kontakt,tlf,email,cvrnr,art)values('$firmanavn','$addr1','$addr2','$postnr','$bynavn','','$tlf','$email','$cvrnr','S')",__FILE__ . " linje " . __LINE__);
		}
		$qtxt="insert into adresser (kontonr,firmanavn,addr1,addr2,postnr,bynavn,kontakt,tlf,email,cvrnr,art)values('88535553','Advokaternes Inkasso Service','Esplanaden 26','','1263','København K','Torben Stohn','88535553','info@inkassoadvokat.dk','74159710','K')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select id from adresser where kontonr='88535553'",__FILE__ . " linje " . __LINE__));
		db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14) values ('Div_valg (Rykker)','4','DIV','','','','','','','','','".$r['id']."','','','','','')",__FILE__ . " linje " . __LINE__);
	}
	transaktion("commit");
	print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab er oprettet og aktiveret')\">";
	if ($fra_formular) {
		$regnskab=urlencode($regnskab);
		$brugernavn=urlencode($brugernavn);
		print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/index.php?regnskab=$regnskab&navn=$brugernavn&tlf=$tlf\">"; 
	} else {
		include("../includes/connect.php");
		print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/admin_menu.php\">";
	}
}
?>
</tbody></table>
</body></html>
