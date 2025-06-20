<?php
@session_start();
$s_id=session_id();

// ------------/admin/opret.php-----patch 2.0.7------ 2009-05-20 --------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

ini_set("display_errors", "0");
$css="../css/standard.css";
		
include("../includes/connect.php");
include("../includes/std_func.php");

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
		$kontakt=addslashes(trim($_POST['kontakt']));
		$firmanavn=addslashes(trim($_POST['firmanavn']));
		$addr1=addslashes(trim($_POST['addr1']));
		$addr2=addslashes(trim($_POST['addr2']));
		$postnr=addslashes(trim($_POST['postnr']));
		$bynavn=addslashes(trim($_POST['bynavn']));
		$tlf=addslashes(trim($_POST['tlf']));
		$email=addslashes(trim($_POST['email']));
		$cvrnr=addslashes(trim($_POST['cvrnr']));
	}	
	$regnskab=addslashes(trim($_POST['regnskab']));
	$brugernavn=addslashes(trim($_POST['brugernavn']));
	$passwd=addslashes(trim($_POST['passwd']));
	$passwd2=addslashes(trim($_POST['passwd2']));
	$posteringer=addslashes(trim($_POST['posteringer']));
	$brugerantal=addslashes(trim($_POST['brugerantal']));
	$std_kto_plan=$_POST['std_kto_plan'];

	if ($passwd!=$passwd2){
		print "<BODY onLoad=\"javascript:alert('Adgangskoder er ikke ens')\">";
		forside($regnskab, $brugernavn);
		exit;
	}

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

	$x=0;
#	$tmp=addslashes($regnskab);
$query = db_select("select * from regnskab where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab findes allerede')\">";
		forside($regnskab, $brugernavn);
		exit;
	} elseif ((!$regnskab)||(!$brugernavn)||(!$passwd)) {
		print "<BODY onLoad=\"javascript:alert('Alle felter SKAL udfyldes')\">";
		forside($regnskab, $brugernavn);
		exit();
	} else {
		transaktion(begin);
		$brugerantal=$brugerantal*1;
		$posteringer=$posteringer*1;
		db_modify("insert into regnskab (regnskab,dbhost,dbuser,brugerantal,posteringer) values ('$regnskab','$sqhost','$squser','$brugerantal','$posteringer')",__FILE__ . " linje " . __LINE__);
		$row=db_fetch_array(db_select("select id from regnskab where regnskab='$regnskab' and dbhost='$sqhost' and dbuser='$squser'",__FILE__ . " linje " . __LINE__));
		$db= $sqdb."_".$row[id];
		db_modify("update regnskab set db='$db' where id=$row[id]",__FILE__ . " linje " . __LINE__); 
		transaktion(commit);
#		$brugernavn=addslashes($brugernavn);
#		$passwd=addslashes($passwd);
		opret($sqhost, $squser ,$sqpass, $db, $brugernavn, $passwd, $std_kto_plan);
#		print "<tr><td align=center colspan=3><b><big> Regnskab $regnskab er oprettet</b></big></td></tr>";
		exit();
	}
} else {
	if (!isset($regnskab)) $regnskab='';
	if (!isset($brugernavn)) $brugernavn='';
	forside($regnskab, $brugernavn);
print "</tbody></table";
}

function forside($regnskab, $brugernavn) 
{
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

function opret ($sqhost, $squser ,$sqpass, $db, $brugernavn, $passwd, $std_kto_plan)
{
	global $connection;
	global $version;
	global $db_id;
	global $regnskab;
	global $fra_formular;
	global $db_encode;
	global $db_type;
	if ($fra_formular) {
		global $kontakt;
		global $firmanavn;
		global $addr1;
		global $addr2;
		global $postnr;
		global $bynavn;
		global $tlf;
		global $email;
		global $cvrnr;
	}
	
	if ($db_type=="mysql") {
		db_modify("CREATE DATABASE $db",__FILE__ . " linje " . __LINE__);
		mysql_select_db("$db");
		if ($db_encode=="UTF8") db_modify("SET character_set_client = 'UTF8'",__FILE__ . " linje " . __LINE__);
		else db_modify("SET character_set_client = 'LATIN1'",__FILE__ . " linje " . __LINE__);
	} else {
		if ($db_encode=="UTF8") db_modify("CREATE DATABASE $db with encoding = 'UTF8'",__FILE__ . " linje " . __LINE__);
		else db_modify("CREATE DATABASE $db with encoding = 'LATIN9'",__FILE__ . " linje " . __LINE__);
		db_close($connection);
		$connection = db_connect ("$sqhost", "$squser", "$sqpass", "$db", __FILE__ . " linje " . __LINE__);
	}
	
	transaktion("begin");
#	db_modify("CREATE SEQUENCE id START 1 INCREMENT 1 MAXVALUE 9223372036854775807 MINVALUE 1 CACHE 1",__FILE__ . " linje " . __LINE__);

	######## Adresser ##########
	db_modify("CREATE TABLE adresser (id serial NOT NULL, firmanavn varchar(250), addr1 varchar(250), addr2 varchar(250), postnr varchar(250), bynavn varchar(250), land varchar(250), kontakt varchar(250), tlf varchar(250), fax varchar(250), email varchar(250), web varchar(250), bank_navn varchar(250), bank_reg varchar(250), bank_konto varchar(250), bank_fi varchar(250), erh varchar(250), swift varchar(250), notes varchar(5000), rabat numeric, momskonto integer, kreditmax numeric, betalingsbet varchar(250), betalingsdage integer DEFAULT 0, kontonr varchar(250), cvrnr varchar(250), ean varchar(250), institution varchar(250), art varchar(2), gruppe integer, kontoansvarlig integer, oprettet date, kontaktet date, kontaktes date, pbs varchar(2), pbs_nr varchar(250), pbs_date date, mailfakt varchar(2), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE ansatte (id serial NOT NULL, konto_id integer, navn varchar(250), addr1 varchar(250), addr2 varchar(250), postnr varchar(250), bynavn varchar(250), tlf varchar(250), fax varchar(250), mobil varchar(250), privattlf varchar(250), initialer varchar(250), email varchar(250), notes varchar(5000), cprnr varchar(250), posnr integer, afd integer, provision numeric, nummer integer, loen numeric, hold integer, lukket varchar(2), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE  crm (id serial NOT NULL, konto_id int, kontakt_id int, ansat_id int, notat varchar(5000), notedate date, spor varchar(250), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE brugere(id serial NOT NULL, brugernavn varchar(250), kode varchar(250), status boolean, regnskabsaar integer, rettigheder varchar(250), ansat_id integer, sprog_id integer, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE grupper (id serial NOT NULL, beskrivelse varchar(250), kode varchar(250), kodenr varchar(250), art varchar(250), box1 varchar(5000), box2 varchar(5000), box3 varchar(5000), box4 varchar(5000), box5 varchar(5000), box6 varchar(5000), box7 varchar(5000), box8 varchar(5000), box9 varchar(5000), box10 varchar(5000), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	######## Kassekladde ########
	db_modify("CREATE TABLE kassekladde (id serial NOT NULL, bilag integer, transdate date, beskrivelse varchar(250), d_type varchar(1), debet numeric, k_type varchar(1), kredit numeric, faktura varchar(250), amount numeric, kladde_id integer, momsfri varchar(2), medarb integer, ansat varchar(250), afd integer, projekt numeric, valuta integer, valutakurs numeric, ordre_id integer, forfaldsdate date, betal_id varchar(250), dokument varchar(250), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);

	db_modify("CREATE TABLE tmpkassekl (id integer, lobenr integer, bilag varchar(250), transdate varchar(250), beskrivelse varchar(250), d_type varchar(1), debet varchar(250), k_type varchar(1), kredit varchar(250), faktura varchar(250), amount varchar(250), kladde_id integer, momsfri varchar(2), afd varchar(250), projekt varchar(250), ansat varchar(250), valuta varchar(250), valutakurs varchar(250), forfaldsdate varchar(250), betal_id varchar(250), dokument varchar(250))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE kladdeliste (id serial NOT NULL, kladdedate date, bogforingsdate date, kladdenote varchar(250), bogfort varchar(2), oprettet_af varchar(250), bogfort_af varchar(250), hvem varchar(250), tidspkt varchar(250), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE kontoplan (id serial NOT NULL, kontonr numeric, beskrivelse varchar(250), kontotype varchar(1), moms varchar(250), fra_kto numeric, til_kto numeric, lukket varchar(2), primo numeric, saldo numeric, regnskabsaar integer, genvej varchar(2), overfor_til numeric, anvendelse varchar(250), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE kontokort (id serial NOT NULL, ref_id integer, faktnr integer, refnr integer, beskrivelse varchar(250), kredit numeric, debet numeric, transdate date, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE ordrer (id serial NOT NULL, konto_id integer, firmanavn varchar(250), addr1 varchar(250), addr2 varchar(250), postnr varchar(250), bynavn varchar(250), land varchar(250), kontakt varchar(250), email varchar(250), mail_fakt varchar(2), kundeordnr varchar(250),lev_navn varchar(250), lev_addr1 varchar(250),	lev_addr2 varchar(250), lev_postnr varchar(250), lev_bynavn varchar(250),lev_kontakt varchar(250), ean varchar(250), institution varchar(250), betalingsbet varchar(250), betalingsdage integer, kontonr varchar(250), cvrnr varchar(250), art varchar(2), valuta varchar(250), valutakurs numeric, sprog varchar(250), projekt varchar(250), ordredate date, levdate date, fakturadate date, notes varchar(5000), ordrenr integer, sum numeric, momssats numeric, status integer, ref varchar(250), fakturanr varchar(250), modtagelse integer, kred_ord_id integer, lev_adr varchar(250), kostpris numeric, moms numeric, hvem varchar(250), tidspkt varchar(250), betalt varchar(2), nextfakt date, pbs varchar(2), mail varchar(2), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE ordrelinjer (id serial NOT NULL, varenr varchar(250), beskrivelse varchar(250), enhed varchar(250), posnr integer, pris numeric, rabat numeric, lev_varenr varchar(250), ordre_id integer, serienr varchar(250), vare_id integer, antal numeric, leveres numeric, leveret numeric, bogf_konto integer, oprettet_af varchar(250), bogfort_af varchar(250), hvem varchar(250), tidspkt varchar(250), kred_linje_id integer, momsfri varchar(2), kostpris numeric, samlevare varchar(2), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE openpost (id serial NOT NULL, konto_id integer, konto_nr varchar(250), faktnr varchar(250), amount numeric, refnr integer, beskrivelse varchar(250), udlignet varchar(2), transdate date, kladde_id integer, bilag_id integer, udlign_id integer, udlign_date date, valuta varchar(250), valutakurs numeric,forfaldsdate date,betal_id varchar(250), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE transaktioner (id serial NOT NULL, kontonr integer, bilag integer, transdate date, logtime time, beskrivelse varchar(250), debet numeric, kredit numeric, faktura varchar(250), kladde_id integer, projekt numeric, ansat numeric, logdate date, afd integer, ordre_id integer, valuta varchar(250), valutakurs numeric, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE varer (id serial NOT NULL, varenr varchar(250), beskrivelse varchar(250), enhed varchar(250), enhed2 varchar(250), forhold numeric, gruppe varchar(250), salgspris numeric, kostpris numeric, provisionsfri varchar(2), notes varchar(5000), lukket varchar(2), serienr varchar(250), beholdning numeric, samlevare varchar(2), delvare varchar(2), min_lager numeric, max_lager numeric, komplementaer varchar(250), circulate integer, operation integer, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE lagerstatus (id serial NOT NULL, lager integer, vare_id integer, beholdning numeric, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE batch_kob (id serial NOT NULL, kobsdate date, fakturadate date, vare_id integer, linje_id integer, ordre_id integer, pris numeric, antal numeric, rest numeric, lager integer, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE batch_salg (id serial NOT NULL, salgsdate date, fakturadate date, batch_kob_id integer, vare_id integer, linje_id integer, ordre_id integer, pris numeric, antal numeric, lev_nr integer, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE serienr (id serial NOT NULL, vare_id integer, kobslinje_id integer, salgslinje_id integer, batch_kob_id integer, batch_salg_id integer, serienr varchar(250), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE styklister (id serial NOT NULL, vare_id integer, indgaar_i integer, antal numeric, posnr integer, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE enheder (id serial NOT NULL, betegnelse varchar(250), beskrivelse varchar(250), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE materialer (id serial NOT NULL, beskrivelse varchar(250), densitet numeric, materialenr varchar(250), tykkelse numeric, kgpris numeric, avance numeric, enhed varchar(250), opdat_date date, opdat_time time, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE vare_lev (id serial NOT NULL, posnr integer, lev_id integer, vare_id integer, lev_varenr varchar(250), kostpris numeric, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE reservation (linje_id integer, batch_kob_id integer, batch_salg_id integer, vare_id integer, antal numeric, lager integer)",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE formularer (id serial NOT NULL, formular integer, art integer, beskrivelse varchar(250), placering varchar(250), xa numeric, ya numeric, xb numeric, yb numeric, str numeric, color integer, font varchar(250), fed varchar(2), kursiv varchar(2), side varchar(2), sprog varchar(250), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE provision (id serial NOT NULL, gruppe_id integer, ansat_id integer, provision numeric, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE historik (id serial NOT NULL, konto_id int, kontakt_id int, ansat_id int, notat varchar(5000), notedate date, kontaktet date, kontaktes date, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE valuta (id serial NOT NULL, gruppe integer, valdate date, kurs numeric, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE betalingsliste (id serial NOT NULL, listedate date, udskriftsdate date, listenote varchar(250), bogfort varchar(2), oprettet_af varchar(250), bogfort_af varchar(250), hvem varchar(250), tidspkt varchar(250), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE betalinger (id serial NOT NULL, bet_type varchar(250), fra_kto varchar(250), egen_ref varchar(250), til_kto varchar(250), modt_navn varchar(250), belob varchar(250), betalingsdato varchar(250), valuta varchar(250),kort_ref varchar(250), kvittering varchar(250), ordre_id integer, bilag_id integer, liste_id integer, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE tidsreg (id serial NOT NULL, person integer, ordre integer, pnummer integer, operation integer, materiale integer, tykkelse numeric, laengde numeric, bredde numeric, antal_plader numeric,  gaa_hjem integer, tid integer, forbrugt_tid integer, opsummeret_tid integer, beregnet integer, pause integer, antal numeric,  faerdig integer, circ_time integer, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE tabeller (id serial NOT NULL, person integer, ordre integer, pnummer integer,	operation integer, materiale integer, tykkelse numeric, laengde numeric, bredde numeric, antal_plader numeric,  gaa_hjem integer, tid integer, forbrugt_tid integer, opsummeret_tid integer, beregnet integer, pause integer, antal numeric,  faerdig integer, circ_time integer, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE tekster (id serial NOT NULL, sprog_id integer, tekst_id integer, tekst varchar(5000), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE jobkort (id serial NOT NULL, konto_id integer, kontonr varchar(250), firmanavn varchar(250), addr1 varchar(250), addr2 varchar(250), postnr varchar(250), bynavn varchar(250), kontakt varchar(250), tlf varchar(250), initdate date, oprettet_af varchar(250), startdate date, slutdate date, hvem varchar(250), tidspkt varchar(250), felt_1 varchar(5000), felt_2 varchar(5000), felt_3 varchar(5000), felt_4 varchar(5000), felt_5 varchar(5000), felt_6 varchar(5000), felt_7 varchar(5000), felt_8 varchar(5000), felt_9 varchar(5000), felt_10 varchar(5000), felt_11 varchar(5000), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE jobkort_felter (id serial NOT NULL, job_id integer, art varchar(250), feltnr integer, subnr integer, feltnavn varchar(250), indhold varchar(5000), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art) values ('Dansk', 'DA', '1', 'SPROG')",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE modtageliste (id serial NOT NULL, initdate date, modtagdate date, modtagnote varchar(5000), modtaget varchar(250), init_af varchar(250), modtaget_af varchar(250), hvem varchar(250), tidspkt varchar(250), PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE modtagelser (id serial NOT NULL, varenr varchar(250), beskrivelse varchar(250), leveres numeric, liste_id integer, lager numeric, ordre_id integer, vare_id integer, antal numeric, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE pbs_kunder(id serial NOT NULL,konto_id integer,kontonr varchar(20), pbs_nr varchar(250),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE pbs_liste(id serial NOT NULL,liste_date date,afsendt varchar(8),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE pbs_ordrer(id serial NOT NULL,liste_id integer,ordre_id integer,PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("CREATE TABLE pbs_linjer(id serial NOT NULL,liste_id integer,linje varchar(140),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);

	$pw=md5($passwd);
	db_modify("INSERT INTO brugere (brugernavn, kode, rettigheder, regnskabsaar) values ('$brugernavn' ,'$pw', '11111111111111111111', 1)",__FILE__ . " linje " . __LINE__);
	db_modify("insert into grupper (beskrivelse, art, box1) values ('Version', 'VE', '$version')",__FILE__ . " linje " . __LINE__);
	db_modify("insert into grupper (beskrivelse, kodenr, art, box4, box5) values ('Div_valg','2','DIV','','')",__FILE__ . " linje " . __LINE__);
	db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('Div_valg','3','DIV','','','','on','on','','','','','')",__FILE__ . " linje " . __LINE__);
	db_modify("insert into enheder (betegnelse, beskrivelse) values ('stk', 'styk')",__FILE__ . " linje " . __LINE__);

	if ($std_kto_plan) {
#	 include("../includes/kontoplan.php"); 
		$fp=fopen("../importfiler/kontoplan.txt","r");
		if ($fp) {
			$x=0;
			while (!feof($fp)) {
				$x++;	
				list($kontonr[$x], $beskrivelse[$x], $kontotype[$x], $moms[$x], $fra_kto[$x]) = split(chr(9), fgets($fp));
				if (!$kontonr[$x]) {$x--;}
			}
			$kontoantal=$x;
			for ($x=1; $x<=$kontoantal; $x++){
				$beskrivelse[$x]=str_replace('"','',$beskrivelse[$x]);
				if ($db_encode=="UTF8") $beskrivelse[$x]=utf8_encode($beskrivelse[$x]);
				$kontotype[$x]=str_replace('"','',$kontotype[$x]);
				$moms[$x]=str_replace('"','',$moms[$x]);
				$fra_kto[$x]=$fra_kto[$x]*1;
				db_modify("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, fra_kto, moms, regnskabsaar, lukket) values ('$kontonr[$x]', '$beskrivelse[$x]', '$kontotype[$x]', '$fra_kto[$x]', '$moms[$x]', '1', '')",__FILE__ . " linje " . __LINE__);
			}
			fclose($fp);
		}
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2) values ('Salgsmoms 25%', 'S', '1', 'SM', '66100', '25')",__FILE__ . " linje " . __LINE__);
		$tmp='Købsmoms';
		if ($db_encode=="UTF8") $tmp=utf8_encode($tmp);
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2) values ('$tmp', 'K', '1', 'KM', '66200', '25')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2, box3) values ('Erhvervelsesmoms 25%', 'E', '1', 'EM', '66150', '25', '66200')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2) values ('Momsrapport', 'R', '1', 'MR', '66100', '66200')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5) values ('Danske Debitorer', 'D', '1', 'DG', 'S1', '56100', 'DKK', 'Dansk', '58000')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5) values ('Danske Kreditorer', 'K', '1', 'KG', 'K1', '65100', 'DKK', 'Dansk', '58000')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into grupper (beskrivelse, kodenr, art, box3, box4) values ('Ydelser', '1', 'VG', '2900', '1000')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5, box8) values ('Handelsvarer', '2', 'VG', '55100', '55100', '2100', '1100', '2600', 'on')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into grupper (beskrivelse, kodenr, art, box3, box4, box7) values ('Fragt/porto', '3', 'VG', '2300', '1300', 'on')",__FILE__ . " linje " . __LINE__);

		include("../includes/formularimport.php");
		formularimport("../importfiler/formular.txt");
		db_modify("update formularer set sprog = 'Dansk'",__FILE__ . " linje " . __LINE__);
		if ($fra_formular) {
			db_modify("insert into adresser (firmanavn,addr1,addr2,postnr,bynavn,kontakt,tlf,email,cvrnr, art)values('$firmanavn','$addr1','$addr2','$postnr','$bynavn','$kontakt','$tlf','$email','$cvrnr','S')",__FILE__ . " linje " . __LINE__);
		}
	}
	transaktion("commit");
	print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab er oprettet og aktiveret')\">";
	if ($fra_formular) {
		$regnskab=htmlentities($regnskab);
		$brugernavn=htmlentities($brugernavn);
		print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/index.php?regnskab=$regnskab&navn=$brugernavn&tlf=$tlf\">"; 
	} else {
		include("../includes/connect.php");
		print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/admin_menu.php\">";
	}
}
?>
</tbody></table>
</body></html>
