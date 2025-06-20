<?
  @session_start();
  $s_id=session_id();

// --------------------------------/admin/opret.php-----patch 0.956----------------------------------------
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
// Copyright (c) 2004-2006 ITz ApS
// ----------------------------------------------------------------------


if ((!$sqhost)||(!$dbuser)||(!$db))
{
  include("../includes/connect.php");
  include("../includes/db_query.php");
}
if (!$font) {$font="Helvetica, Arial, sans-serif";}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SQL-Finans - Opret regnskab</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
  <tr><td align="center" valign="top">
    <table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
      <td width="25%" bgcolor="<? echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><a href=../index/index.php accesskey=T>Tilbage</a></small></td>
      <td width="50%" bgcolor="<? echo $bgcolor2 ?>" align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Opret regnskab</small></td>
      <td width="25%" bgcolor="<? echo $bgcolor2 ?>" align = "right"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
    </tbody></table>
  </td></tr>
<td align = center valign = center>
<table cellpadding="1" cellspacing="1" border="0"><tbody>
<?
if ($HTTP_POST_VARS)
{
  $regnskab=trim($HTTP_POST_VARS['regnskab']);
  $admin=trim($HTTP_POST_VARS['admin']);
  $passwd=trim($HTTP_POST_VARS['passwd']);
  $passwd2=trim($HTTP_POST_VARS['passwd2']);
  $std_kto_plan=$HTTP_POST_VARS['std_kto_plan'];


  if ($passwd!=$passwd2){
    print "<BODY onLoad=\"javascript:alert('Passwords ikke ens')\">";
    forside($regnskab, $admin);
    exit;
  }


  $x=0;
  $query = db_select("select * from regnskab where regnskab='$regnskab'");
  if ($row = db_fetch_array($query)) {
    print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab findes allerede')\">";
    forside($regnskab, $admin);
    exit;
  }
  elseif ((!$regnskab)||(!$admin)||(!$passwd)) {
    print "<BODY onLoad=\"javascript:alert('Alle felter SKAL udfyldes')\">";
    forside($regnskab, $admin);
    exit();
  }
  else {
    transaktion(begin);
    print "<tr><td align=center colspan=3><b><big>$font Regnskab $regnskab oprettes - vent!</b></big></td></tr>";
    db_modify("insert into regnskab (regnskab, dbhost, dbuser) values ('$regnskab', '$sqhost', '$squser')");
    $row=db_fetch_array(db_select("select id from regnskab where regnskab='$regnskab' and dbhost='$sqhost' and dbuser='$squser'"));
    $db= $sqdb."_".$row[id];
    db_modify("update regnskab set db='$db' where id=$row[id]"); 
    transaktion(commit);
    opret($sqhost, $squser ,$sqpass, $db, $admin, $passwd, $std_kto_plan);
    print "<tr><td align=center colspan=3><b><big>$font Regnskab $regnskab er oprettet</b></big></td></tr>";
    exit();
  }
}

else
{
  forside($regnskab, $admin);
}

function forside($regnskab, $admin)
{
  global $font;

  print "<form name=debitorkort action=opret.php method=post>";
  print "<tr><td>$font Navn p&aring; regnskab</td><td><br></td><td><input type=text size=25 name=regnskab value=$regnskab></td></tr>";
  print "<tr><td>$font Administrators navn</td><td><br></td><td><input type=text size=25 name=admin value=$admin></td></tr>";
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


  db_modify("CREATE DATABASE $db with encoding = 'LATIN9'");
  db_close($connection);
  $connection = db_connect ("host=$sqhost dbname=$db user=$squser");
  transaktion("begin");
  db_modify("CREATE SEQUENCE id START 1 INCREMENT 1 MAXVALUE 9223372036854775807 MINVALUE 1 CACHE 1;");

  db_modify("CREATE TABLE adresser (id serial NOT NULL, firmanavn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, land varchar, kontakt varchar, tlf varchar, fax varchar, email varchar, web varchar, bank_navn varchar, bank_reg varchar, bank_konto varchar, notes varchar, rabat numeric, momskonto integer, kreditmax numeric, betalingsbet varchar, betalingsdage smallint DEFAULT 0, kontonr varchar, cvrnr varchar, ean varchar, institution varchar, art varchar, gruppe smallint, PRIMARY KEY (id))");
  db_modify("CREATE TABLE ansatte (id serial NOT NULL, konto_id integer, navn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, tlf varchar, fax varchar, mobil varchar, privattlf varchar, initialer varchar, email varchar, notes varchar, cprnr varchar, posnr integer, afd integer, PRIMARY KEY (id))");
  db_modify("CREATE TABLE brugere(id serial NOT NULL, brugernavn varchar, kode varchar, status varchar, regnskabsaar smallint, rettigheder varchar, ansat_id varchar, PRIMARY KEY (id))");
  db_modify("CREATE TABLE grupper (id serial NOT NULL, beskrivelse varchar, kode varchar,  kodenr varchar, art varchar, box1 varchar, box2 varchar, box3 varchar, box4 varchar, box5 varchar, box6 varchar, box7 varchar, box8 varchar, PRIMARY KEY (id))");
  db_modify("CREATE TABLE kassekladde (id serial NOT NULL, bilag integer, transdate date, beskrivelse varchar, d_type varchar, debet integer, k_type varchar, kredit integer, faktura varchar, amount numeric, kladde_id integer, momsfri varchar, afd integer, PRIMARY KEY (id))");
  db_modify("CREATE TABLE kladdeliste (id serial NOT NULL, kladdedate date, bogforingsdate date, kladdenote varchar, bogfort varchar, oprettet_af varchar, bogfort_af varchar, hvem varchar, tidspkt varchar, PRIMARY KEY (id))");
  db_modify("CREATE TABLE kontoplan (id serial NOT NULL, kontonr numeric, beskrivelse varchar, kontotype varchar, moms varchar, fra_kto varchar, til_kto varchar, lukket varchar, md01 numeric, md02 numeric, md03 numeric, md04 numeric, md05 numeric, md06 numeric, md07 numeric, md08 numeric, md09 numeric, md10 numeric, md11 numeric, md12 numeric, primo numeric, regnskabsaar smallint, PRIMARY KEY (id))");
  db_modify("CREATE TABLE kontokort (id serial NOT NULL, ref_id integer, faktnr integer, refnr integer, beskrivelse varchar, kredit numeric, debet numeric, transdate date, PRIMARY KEY (id))");
  db_modify("CREATE TABLE ordrer (id serial NOT NULL, konto_id integer, firmanavn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, land varchar, kontakt varchar, kundeordnr varchar,lev_navn varchar, lev_addr1 varchar,  lev_addr2 varchar, lev_postnr varchar, lev_bynavn varchar,lev_kontakt varchar, ean varchar, institution varchar, betalingsbet varchar, betalingsdage smallint, kontonr varchar, cvrnr varchar, art varchar, ordredate date, levdate date, fakturadate date, notes varchar, ordrenr integer, sum numeric, momssats numeric, status smallint, ref varchar, fakturanr varchar, modtagelse integer, kred_ord_id integer, lev_adr varchar, kostpris numeric, moms numeric, hvem varchar, tidspkt varchar, PRIMARY KEY (id))");
  db_modify("CREATE TABLE ordrelinjer (id serial NOT NULL, varenr varchar, beskrivelse varchar, enhed varchar, posnr smallint, pris numeric, rabat numeric, lev_varenr varchar, ordre_id integer, serienr varchar, vare_id integer, antal numeric, leveres numeric, bogf_konto integer, oprettet_af varchar, bogfort_af varchar, hvem varchar, tidspkt varchar, kred_linje_id integer, momsfri varchar, PRIMARY KEY (id))");
  db_modify("CREATE TABLE openpost (id serial NOT NULL, konto_id integer, konto_nr varchar, faktnr varchar, amount numeric, refnr integer, beskrivelse varchar, udlignet varchar, transdate date, kladde_id integer, PRIMARY KEY (id))");
  db_modify("CREATE TABLE transaktioner (id serial NOT NULL, kontonr integer, bilag integer, transdate date, logtime time without time zone, beskrivelse varchar, debet numeric, kredit numeric, faktura varchar, kladde_id integer, projekt_id integer, logdate date, afd integer, PRIMARY KEY (id))");
  db_modify("CREATE TABLE varer (id serial NOT NULL, varenr varchar, beskrivelse varchar, enhed varchar, enhed2 varchar, forhold numeric, gruppe varchar, salgspris numeric, kostpris numeric, provisionsfri varchar, notes varchar, lukket varchar, serienr varchar, beholdning numeric, samlevare varchar, delvare varchar, min_lager numeric, max_lager numeric, PRIMARY KEY (id))");
  db_modify("CREATE TABLE lagerstatus (id serial NOT NULL, lager integer, vare_id integer, beholdning numeric, PRIMARY KEY (id))");
  db_modify("CREATE TABLE batch_kob (id serial NOT NULL, kobsdate date, fakturadate date, vare_id integer, linje_id integer, ordre_id integer, pris numeric, antal numeric, rest numeric, lager integer, PRIMARY KEY (id))");
  db_modify("CREATE TABLE batch_salg (id serial NOT NULL, salgsdate date, fakturadate date, batch_kob_id integer, vare_id integer, linje_id integer, ordre_id integer, pris numeric, antal numeric, lev_nr integer, PRIMARY KEY (id))");
  db_modify("CREATE TABLE serienr (id serial NOT NULL, vare_id integer, kobslinje_id integer, salgslinje_id integer, batch_kob_id integer, batch_salg_id integer, serienr varchar, PRIMARY KEY (id))");
  db_modify("CREATE TABLE styklister (id serial NOT NULL, vare_id integer, indgaar_i integer, antal numeric, posnr integer, PRIMARY KEY (id))");
  db_modify("CREATE TABLE enheder (id serial NOT NULL, betegnelse varchar, beskrivelse varchar, PRIMARY KEY (id))");
  db_modify("CREATE TABLE materialer (id serial NOT NULL, beskrivelse varchar, densitet numeric, PRIMARY KEY (id))");
  db_modify("CREATE TABLE vare_lev (id serial NOT NULL, posnr integer, lev_id integer, vare_id integer, lev_varenr varchar, kostpris numeric, PRIMARY KEY (id))");
  db_modify("CREATE TABLE reservation (linje_id integer, batch_kob_id integer, batch_salg_id integer, vare_id integer, antal numeric, lager integer)");
  db_modify("CREATE TABLE formularer (id serial NOT NULL, formular integer, art integer, beskrivelse varchar, placering varchar, xa numeric, ya numeric, xb numeric, yb numeric, str numeric, color integer, font varchar, fed varchar, kursiv varchar, side varchar, PRIMARY KEY (id))");


  $passwd=md5($passwd);
  db_modify("INSERT INTO brugere (brugernavn, kode, rettigheder, regnskabsaar) values ('$admin' ,'$passwd', '11111111111111111111', 1)");
  db_modify("insert into grupper (beskrivelse, art, box1) values ('Version', 'VE', '$version')");

#  db_modify("insert into enheder (betegnelse, beskrivelse) values ('m', 'meter')");
#  db_modify("insert into enheder (betegnelse, beskrivelse) values ('m2', 'kvadratmeter')");
#  db_modify("insert into enheder (betegnelse, beskrivelse) values ('m3', 'kubikmeter')");
#  db_modify("insert into enheder (betegnelse, beskrivelse) values ('l', 'liter')");
#  db_modify("insert into enheder (betegnelse, beskrivelse) values ('kg', 'kilogram')");
  db_modify("insert into enheder (betegnelse, beskrivelse) values ('stk', 'styk')");

  if ($std_kto_plan)   {
#   include("../includes/kontoplan.php"); 
    $fp=fopen("../importfiler/kontoplan.txt","r");
    if ($fp) {
      $x=0;
      while (!feof($fp)) {
        $x++;  
        list($kontonr[$x], $beskrivelse[$x], $kontotype[$x], $fra_kto[$x], $til_kto[$x], $moms[$x]) = split(chr(9), fgets($fp));
        if (!$kontonr[$x]) {$x--;}
      }
      $kontoantal=$x;
      for ($x=1; $x<=$kontoantal; $x++){
        $beskrivelse[$x]=str_replace('"','',$beskrivelse[$x]);
        $kontotype[$x]=str_replace('"','',$kontotype[$x]);
        $moms[$x]=str_replace('"','',$moms[$x]);
        if (strstr(strtoupper($kontotype[$x]),"OVERSKRIFT")){$kontotype[$x]="H";}
        elseif (strtoupper($kontotype[$x])=="TEKST") {$kontotype[$x]="H";}
        elseif (strtoupper($kontotype[$x])=="DRIFT") {$kontotype[$x]="D";}
        elseif (strtoupper($kontotype[$x])=="TOTAL") {$kontotype[$x]="Z";}
        elseif (strtoupper($kontotype[$x])=="SUM") {$kontotype[$x]="Z";}
        elseif (strtoupper($kontotype[$x])=="STATUS") {$kontotype[$x]="S";}
        if (($kontotype[$x]=="Z")&&(!$til_kto[$x])) {$til_kto[$x]=$kontonr[$x];}
        if (strtoupper(substr($moms[$x],0,1))=="S") {$moms[$x]="S1";}
        elseif (strtoupper(substr($moms[$x],0,1))=="K") {$moms[$x]="K1";}
        db_modify("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, fra_kto, til_kto, moms, regnskabsaar) values ('$kontonr[$x]', '$beskrivelse[$x]', '$kontotype[$x]', '$fra_kto[$x]', '$til_kto[$x]', '$moms[$x]', '1')");
      }
      fclose($fp);
    }
    db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2) values ('Salgsmoms 25%', 'S', '1', 'SM', '33100', '25')");
    db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2) values ('K&oslash;bsmoms 25%', 'K', '1', 'KM', '33200', '25')");
    db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2) values ('Danske Debitorer', 'D', '1', 'DG', '1', '25100')");
    db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, box2) values ('Danske Kreditorer', 'K', '1', 'KG', '1', '34100')");
    db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5, box8) values ('Handelsvarer', '1', 'VG', '24200', '24500', '12010', '11010', '12400', 'on')");
    db_modify("insert into grupper (beskrivelse, kodenr, art, box3, box4) values ('Ydelser', '2', 'VG', '12020', '11020')");
    db_modify("insert into grupper (beskrivelse, kodenr, art, box3, box4, box7) values ('Fragt / Porto', '3', 'VG', '12310', '11030', 'on')");

    $fp=fopen("../importfiler/formular.txt","r");
    if ($fp) {
      while (!feof($fp)) {
        list($formular, $art, $beskrivelse, $xa, $ya, $xb, $yb, $placering, $str, $color, $font, $fed, $kursiv, $side) = split(chr(9), fgets($fp));
        if ($xa>0) {
          $placering=trim($placering); $form=trim($font); $fed=trim($fed); $kursiv=trim($kursiv); $side=trim($side); 
          $xa= $xa*1; $ya= $ya*1; $xb= $xb*1; $yb=$yb*1; $str=$str*1; $color=$color*1;
          db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values  ('$formular', '$art', '$beskrivelse', '$xa', '$ya', '$xb', '$yb', '$placering', '$str', '$color', '$font', '$fed', '$kursiv', '$side')"); 
        }
      }
      fclose($fp);
    }
  }

  transaktion("commit");
  print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
}
?>
</tbody></table>
</body></html>
