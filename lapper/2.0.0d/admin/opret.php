<?php
@session_start();
$s_id=session_id();

// --------------------------------/admin/opret.php-----patch 2.0.0a------ 2008-04-26 --------
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

ini_set("display_errors", "0");
include("../includes/connect.php");
include("../includes/db_query.php");
if (!$_POST['regnskab']&&!$_POST['admin']&&!$_POST['admin']&&!$_POST['passwd']&&!$_POST['passwd2']) include("../includes/online.php");

if (!$font) $font="Helvetica, Arial, sans-serif";
if (!$top_bund) $top_bund="style=\"border: 1px solid rgb(0, 0, 0); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_bg.gif\";";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>Saldi - Opret regnskab</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
	<tr><td align="center" valign="top" height="25">
		<table width="100%" align="center" border="0" cellspacing="2" cellpadding="0"><tbody>
			<td width="10%" <?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif" color="#000066"><small><a href=../index/admin_menu.php accesskey=L>Luk</a></small></td>
			<td width="80%" <?php echo $top_bund ?> align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Opret regnskab</small></td>
			<td width="10%" <?php echo $top_bund ?> align = "right"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
		</tbody></table>
	</td></tr>
<td align = center valign = center>
<table cellpadding="1" cellspacing="1" border="0"><tbody>
<?php
if ($_POST){
	$regnskab=str_replace("apostrof","'",trim($_POST['regnskab']));
	$admin=str_replace("apostrof","'",trim($_POST['admin']));
	$passwd=str_replace("apostrof","'",trim($_POST['passwd']));
	$passwd2=str_replace("apostrof","'",trim($_POST['passwd2']));
	$std_kto_plan=$_POST['std_kto_plan'];

	if ($passwd!=$passwd2){
		print "<BODY onLoad=\"javascript:alert('Passwords ikke ens')\">";
		forside($regnskab, $admin);
		exit;
	}

	$x=0;
	$tmp=addslashes($regnskab);
	$query = db_select("select * from regnskab where regnskab='$tmp'");
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab findes allerede')\">";
		forside($regnskab, $admin);
		exit;
	} elseif ((!$regnskab)||(!$admin)||(!$passwd)) {
		print "<BODY onLoad=\"javascript:alert('Alle felter SKAL udfyldes')\">";
		forside($regnskab, $admin);
		exit();
	} else {
		transaktion(begin);
		db_modify("insert into regnskab (regnskab, dbhost, dbuser) values ('$tmp', '$sqhost', '$squser')");
		$row=db_fetch_array(db_select("select id from regnskab where regnskab='$tmp' and dbhost='$sqhost' and dbuser='$squser'"));
		$db= $sqdb."_".$row[id];
		db_modify("update regnskab set db='$db' where id=$row[id]"); 
		transaktion(commit);
		$admin=addslashes($admin);
		$passwd=addslashes($passwd);
		opret($sqhost, $squser ,$sqpass, $db, $admin, $passwd, $std_kto_plan);
#		print "<tr><td align=center colspan=3><b><big>$font Regnskab $regnskab er oprettet</b></big></td></tr>";
		exit();
	}
} else {
	if (!isset($regnskab)) $regnskab='';
	if (!isset($admin)) $admin='';
	forside($regnskab, $admin);
print "</tbody></table";
}

function forside($regnskab, $admin) 
{
	global $font;

	print "<form name=debitorkort action=opret.php method=post>";
	print "<tr><td>$font Navn p&aring; regnskab</td><td><br></td><td><input type=text size=25 name=regnskab value='$regnskab'></td></tr>";
	print "<tr><td>$font Administrators navn</td><td><br></td><td><input type=text size=25 name=admin value='$admin'></td></tr>";
	print "<tr><td>$font Administrators adgangskode</td><td><br></td><td><input type=password size=25 name=passwd></td></tr>";
	print "<tr><td>$font Gentag adgangskode</td><td><br></td><td><input type=password size=25 name=passwd2></td></tr>";
	print "<tr><td>$font Opret standardkontoplan</td><td><br></td><td><input type=checkbox name=std_kto_plan checked></td></tr>";
	print "<tr><td><br></td></tr><tr><td><br></td></tr><tr><td><br></td></tr>";
	print "<tr><td colspan=3 align = center><input type=submit accesskey=\"g\" value=\"Gem/opdater\" name=\"submit\"></td></tr>";
	print "</form>";
}

function opret ($sqhost, $squser ,$sqpass, $db, $admin, $passwd, $std_kto_plan)
{
	global $connection;
	global $version;
	global $db_id;
	global $regnskab;

	db_modify("CREATE DATABASE $db with encoding = 'LATIN9'");
	db_close($connection);
	$connection = db_connect ("host=$sqhost dbname=$db user=$squser password=$sqpass");
	transaktion("begin");
	db_modify("CREATE SEQUENCE id START 1 INCREMENT 1 MAXVALUE 9223372036854775807 MINVALUE 1 CACHE 1");

	######## Tabelinfo ########
	db_modify("CREATE TABLE tabelinfo (id serial NOT NULL, tabelnavn varchar, feltnavn varchar, beskrivelse varchar, art varchar, PRIMARY KEY (id))");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('tabelinfo','id serial NOT NULL','','PRIMARY KEY')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('tabelinfo','tabelnavn','Siger sig selv','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('tabelinfo' ,'feltnavn','Siger sig selv','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('tabelinfo' ,'beskrivelse','Siger sig selv','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('tabelinfo' ,'type','Siger sig selv','varchar')");
	######## Adresser ########
	db_modify("CREATE TABLE adresser (id serial NOT NULL, firmanavn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, land varchar, kontakt varchar, tlf varchar, fax varchar, email varchar, web varchar, bank_navn varchar, bank_reg varchar, bank_konto varchar, bank_fi varchar, erh varchar, swift varchar, notes varchar, rabat numeric, momskonto integer, kreditmax numeric, betalingsbet varchar, betalingsdage integer DEFAULT 0, kontonr varchar, cvrnr varchar, ean varchar, institution varchar, art varchar, gruppe integer, kontoansvarlig integer, oprettet date, kontaktet date, kontaktes date, PRIMARY KEY (id))");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','id serial NOT NULL','','PRIMARY KEY')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','firmanavn','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','addr1','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','addr2','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','postnr','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','bynavn','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','land','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','kontakt','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','tlf','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','fax','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','email','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','web','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','bank_navn','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','bank_reg','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','bank_konto','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','notes','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','rabat','','numeric')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','kreditmax','','numeric')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','betalingsbet','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','betalingsdage','','integer DEFAULT 0')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','kontonr','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','cvrnr','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','ean','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','institution','','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','art','Kan vaere D=debitor, K=kreditor eller S=Stamdata(Egen konto)','varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','gruppe','Henviser til debitorgruppe eller kreditorgruppe i tabellen grupper','integer')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','kontoansvarlig','Henviser til person i tabellen ansatte som er knyttet til stamdata',' integer')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','oprettet','','date')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','kontaktet','','date')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('adresser','kontaktes','','date')");
	db_modify("CREATE TABLE ansatte (id serial NOT NULL, konto_id integer, navn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, tlf varchar, fax varchar, mobil varchar, privattlf varchar, initialer varchar, email varchar, notes varchar, cprnr varchar, posnr integer, afd integer, provision numeric, nummer integer, loen numeric, hold integer, lukket varchar, PRIMARY KEY (id))");
	db_modify("CREATE TABLE  crm (id serial NOT NULL, konto_id int, kontakt_id int, ansat_id int, notat varchar, notedate date, spor varchar, PRIMARY KEY (id))");
	db_modify("CREATE TABLE brugere(id serial NOT NULL, brugernavn varchar, kode varchar, status varchar, regnskabsaar integer, rettigheder varchar, ansat_id varchar, PRIMARY KEY (id))");
	db_modify("CREATE TABLE grupper (id serial NOT NULL, beskrivelse varchar, kode varchar, kodenr varchar, art varchar, box1 varchar, box2 varchar, box3 varchar, box4 varchar, box5 varchar, box6 varchar, box7 varchar, box8 varchar, box9 varchar, box10 varchar, PRIMARY KEY (id))");
	######## Kassekladde ########
	db_modify("CREATE TABLE kassekladde (id serial NOT NULL, bilag integer, transdate date, beskrivelse varchar, d_type varchar, debet numeric, k_type varchar, kredit numeric, faktura varchar, amount numeric, kladde_id integer, momsfri varchar, medarb integer, ansat varchar, afd integer, projekt numeric, valuta integer, valutakurs numeric, ordre_id integer, PRIMARY KEY (id))");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'id serial NOT NULL', '', 'PRIMARY KEY')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'bilag', '', 'integer')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'transdate', '', 'date')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'beskrivelse', '', 'varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'d_type', 'Debet type: kan vaere F=finans, D=debitor, K=kreditor', 'varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'debet', '', 'numeric')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'k_type', 'Kredit type: kan vaere F=finans, D=debitor, K=kreditor', 'varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'kredit', '', 'numeric')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'faktura', 'Evt fakturanummer knyttet til posteringen', 'varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'amount', 'Beloeb i US formatering', 'numeric')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'kladde_id', 'Henviser til kladdeliste', 'integer')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'momsfri', '', 'varchar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde' ,'afd', 'Afd ID henviser til grupper', 'integer')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde', 'projekt', 'Bruges ikke', 'integer')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde', 'valuta', 'Kode for valuta, henviser til VK i grupper', 'integer')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('kassekladde', 'valutakurs', 'valutakurs for postering', 'numeric')");

	db_modify("CREATE TABLE tmpkassekl (id integer, lobenr integer, bilag varchar, transdate varchar, beskrivelse varchar, d_type varchar, debet varchar, k_type varchar, kredit varchar, faktura varchar, amount varchar, kladde_id integer, momsfri varchar, afd varchar, projekt varchar, ansat varchar, valuta varchar, valutakurs varchar)");
	db_modify("CREATE TABLE kladdeliste (id serial NOT NULL, kladdedate date, bogforingsdate date, kladdenote varchar, bogfort varchar, oprettet_af varchar, bogfort_af varchar, hvem varchar, tidspkt varchar, PRIMARY KEY (id))");
	db_modify("CREATE TABLE kontoplan (id serial NOT NULL, kontonr numeric, beskrivelse varchar, kontotype varchar, moms varchar, fra_kto numeric, til_kto numeric, lukket varchar, primo numeric, saldo numeric, regnskabsaar integer, genvej varchar, overfor_til numeric, PRIMARY KEY (id))");
	db_modify("CREATE TABLE kontokort (id serial NOT NULL, ref_id integer, faktnr integer, refnr integer, beskrivelse varchar, kredit numeric, debet numeric, transdate date, PRIMARY KEY (id))");
	db_modify("CREATE TABLE ordrer (id serial NOT NULL, konto_id integer, firmanavn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, land varchar, kontakt varchar, kundeordnr varchar,lev_navn varchar, lev_addr1 varchar,	lev_addr2 varchar, lev_postnr varchar, lev_bynavn varchar,lev_kontakt varchar, ean varchar, institution varchar, betalingsbet varchar, betalingsdage integer, kontonr varchar, cvrnr varchar, art varchar, valuta varchar, valutakurs numeric, sprog varchar, projekt varchar, ordredate date, levdate date, fakturadate date, notes varchar, ordrenr integer, sum numeric, momssats numeric, status integer, ref varchar, fakturanr varchar, modtagelse integer, kred_ord_id integer, lev_adr varchar, kostpris numeric, moms numeric, hvem varchar, tidspkt varchar, betalt varchar, nextfakt date, PRIMARY KEY (id))");
	db_modify("CREATE TABLE ordrelinjer (id serial NOT NULL, varenr varchar, beskrivelse varchar, enhed varchar, posnr integer, pris numeric, rabat numeric, lev_varenr varchar, ordre_id integer, serienr varchar, vare_id integer, antal numeric, leveres numeric, leveret numeric, bogf_konto integer, oprettet_af varchar, bogfort_af varchar, hvem varchar, tidspkt varchar, kred_linje_id integer, momsfri varchar, kostpris numeric, samlevare varchar, PRIMARY KEY (id))");
	db_modify("CREATE TABLE openpost (id serial NOT NULL, konto_id integer, konto_nr varchar, faktnr varchar, amount numeric, refnr integer, beskrivelse varchar, udlignet varchar, transdate date, kladde_id integer, bilag_id integer, udlign_id integer, udlign_date date, valuta varchar, valutakurs numeric, PRIMARY KEY (id))");
	db_modify("CREATE TABLE transaktioner (id serial NOT NULL, kontonr integer, bilag integer, transdate date, logtime time without time zone, beskrivelse varchar, debet numeric, kredit numeric, faktura varchar, kladde_id integer, projekt numeric, ansat numeric, logdate date, afd integer, ordre_id integer, valuta varchar, valutakurs numeric, PRIMARY KEY (id))");
	db_modify("CREATE TABLE varer (id serial NOT NULL, varenr varchar, beskrivelse varchar, enhed varchar, enhed2 varchar, forhold numeric, gruppe varchar, salgspris numeric, kostpris numeric, provisionsfri varchar, notes varchar, lukket varchar, serienr varchar, beholdning numeric, samlevare varchar, delvare varchar, min_lager numeric, max_lager numeric, komplementaer varchar, circulate integer, operation integer, PRIMARY KEY (id))");
	db_modify("CREATE TABLE lagerstatus (id serial NOT NULL, lager integer, vare_id integer, beholdning numeric, PRIMARY KEY (id))");
	db_modify("CREATE TABLE batch_kob (id serial NOT NULL, kobsdate date, fakturadate date, vare_id integer, linje_id integer, ordre_id integer, pris numeric, antal numeric, rest numeric, lager integer, PRIMARY KEY (id))");
	db_modify("CREATE TABLE batch_salg (id serial NOT NULL, salgsdate date, fakturadate date, batch_kob_id integer, vare_id integer, linje_id integer, ordre_id integer, pris numeric, antal numeric, lev_nr integer, PRIMARY KEY (id))");
	db_modify("CREATE TABLE serienr (id serial NOT NULL, vare_id integer, kobslinje_id integer, salgslinje_id integer, batch_kob_id integer, batch_salg_id integer, serienr varchar, PRIMARY KEY (id))");
	db_modify("CREATE TABLE styklister (id serial NOT NULL, vare_id integer, indgaar_i integer, antal numeric, posnr integer, PRIMARY KEY (id))");
	db_modify("CREATE TABLE enheder (id serial NOT NULL, betegnelse varchar, beskrivelse varchar, PRIMARY KEY (id))");
	db_modify("CREATE TABLE materialer (id serial NOT NULL, beskrivelse varchar, densitet numeric, materialenr varchar, tykkelse numeric, kgpris numeric, avance numeric, enhed varchar, opdat_date date, opdat_time time, PRIMARY KEY (id))");
	db_modify("CREATE TABLE vare_lev (id serial NOT NULL, posnr integer, lev_id integer, vare_id integer, lev_varenr varchar, kostpris numeric, PRIMARY KEY (id))");
	db_modify("CREATE TABLE reservation (linje_id integer, batch_kob_id integer, batch_salg_id integer, vare_id integer, antal numeric, lager integer)");
	db_modify("CREATE TABLE formularer (id serial NOT NULL, formular integer, art integer, beskrivelse varchar, placering varchar, xa numeric, ya numeric, xb numeric, yb numeric, str numeric, color integer, font varchar, fed varchar, kursiv varchar, side varchar, sprog varchar, PRIMARY KEY (id))");
	db_modify("CREATE TABLE provision (id serial NOT NULL, gruppe_id integer, ansat_id integer, provision numeric, PRIMARY KEY (id))");
	db_modify("CREATE TABLE historik (id serial NOT NULL, konto_id int, kontakt_id int, ansat_id int, notat varchar, notedate date, kontaktet date, kontaktes date, PRIMARY KEY (id))");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'konto_id')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'kontakt_id')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'ansat_id')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'notat_id')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'notedate')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'kontaktet')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('historik' ,'kontaktes')");
	db_modify("CREATE TABLE valuta (id serial NOT NULL, gruppe integer, valdate date, kurs numeric, PRIMARY KEY (id))");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'id', 'id serial NOT NULL', 'PRIMARY KEY')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'gruppe', 'Modsvarer kodenr i GRUPPER hvor arg =VK', 'integer')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'valdate', 'Dato for kursændring', 'date')");
		db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn, beskrivelse, art) values ('valuta', 'kurs', 'Aktuel valutakurs', 'numeric')");
	db_modify("CREATE TABLE betalingsliste (id serial NOT NULL, listedate date, udskriftsdate date, listenote varchar, bogfort varchar, oprettet_af varchar, bogfort_af varchar, hvem varchar, tidspkt varchar, PRIMARY KEY (id))");
	db_modify("CREATE TABLE betalinger (id serial NOT NULL, bet_type varchar, fra_kto varchar, egen_ref varchar, til_kto varchar, modt_navn varchar, belob varchar, betalingsdato varchar, valuta varchar,kort_ref varchar, kvittering varchar, ordre_id integer, bilag_id integer, liste_id integer, PRIMARY KEY (id))");
	db_modify("CREATE TABLE tidsreg (id serial NOT NULL, person integer, ordre integer, pnummer integer,	operation integer, materiale integer, tykkelse numeric, laengde numeric, bredde numeric, antal_plader numeric,  gaa_hjem integer, tid integer, forbrugt_tid integer, opsummeret_tid integer, beregnet integer, pause integer, antal numeric,  faerdig integer, circ_time integer, PRIMARY KEY (id))");
	db_modify("CREATE TABLE tabeller (id serial NOT NULL, person integer, ordre integer, pnummer integer,	operation integer, materiale integer, tykkelse numeric, laengde numeric, bredde numeric, antal_plader numeric,  gaa_hjem integer, tid integer, forbrugt_tid integer, opsummeret_tid integer, beregnet integer, pause integer, antal numeric,  faerdig integer, circ_time integer, PRIMARY KEY (id))");



	$passwd=md5($passwd);
	db_modify("INSERT INTO brugere (brugernavn, kode, rettigheder, regnskabsaar) values ('$admin' ,'$passwd', '11111111111111111111', 1)");
	db_modify("insert into grupper (beskrivelse, art, box1) values ('Version', 'VE', '$version')");
	db_modify("insert into grupper (beskrivelse, kodenr, art, box4, box5) values ('Div_valg','2','DIV','on','on')");
	db_modify("insert into enheder (betegnelse, beskrivelse) values ('stk', 'styk')");

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
				$kontotype[$x]=str_replace('"','',$kontotype[$x]);
				$moms[$x]=str_replace('"','',$moms[$x]);
				$fra_kto[$x]=$fra_kto[$x]*1;
				db_modify("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, fra_kto, moms, regnskabsaar) values ('$kontonr[$x]', '$beskrivelse[$x]', '$kontotype[$x]', '$fra_kto[$x]', '$moms[$x]', '1')");
			}
			fclose($fp);
		}
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2) values ('Salgsmoms 25%', 'S', '1', 'SM', '66100', '25')");
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2) values ('Købsmoms 25%', 'K', '1', 'KM', '66200', '25')");
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2, box3) values ('Erhvervelsesmoms 25%', 'E', '1', 'EM', '66150', '25', '66200')");
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2) values ('Momsrapport', 'R', '1', 'MR', '66100', '66200')");
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5) values ('Danske Debitorer', 'D', '1', 'DG', 'S1', '56100', 'DKK', 'Dansk', '58000')");
		db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5) values ('Danske Kreditorer', 'K', '1', 'KG', 'K1', '65100', 'DKK', 'Dansk', '58000')");
		db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5, box8) values ('Handelsvarer', '1', 'VG', '55100', '55100', '2100', '1100', '2600', 'on')");
		db_modify("insert into grupper (beskrivelse, kodenr, art, box3, box4) values ('Ydelser', '2', 'VG', '2900', '1000')");
		db_modify("insert into grupper (beskrivelse, kodenr, art, box3, box4, box7) values ('Fragt/porto', '3', 'VG', '2300', '1300', 'on')");

		include("../includes/formularimport.php");
		formularimport("../importfiler/formular.txt");
		db_modify("update formularer set sprog = 'Dansk'");
	}
	transaktion("commit");
	include("../includes/connect.php");
	print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab er oprettet og aktiveret')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/admin_menu.php\">";
}
?>
</tbody></table>
</body></html>
