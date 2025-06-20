<?
// --------------------------------------systemdata/formularkort -------patch0.936-------------------------
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

  @session_start();
  $s_id=session_id();
  include("../includes/connect.php");
  include("../includes/online.php");
#  include("../includes/db_query.php");
  
  $id=$db_id;
  
  if ($_GET['upload'])
  {
    upload($id, $font);
    exit;
  }
  if ($_GET['id']) {$id = $_GET['id'];}
  if($_GET['returside'])
  {
    $returside= $_GET['returside'];
#    $ordre_id = $_GET['ordre_id'];
#    $fokus = $_GET['fokus'];
  }
  else {$returside="syssetup.php";}
  $navn=$_GET['navn'];

if ($HTTP_POST_VARS)
{
  $formular=$HTTP_POST_VARS['formular'];
  $beskrivelse=$HTTP_POST_VARS['beskrivelse'];
  $ny_beskrivelse=$HTTP_POST_VARS['ny_beskrivelse'];
  $art=$HTTP_POST_VARS['art'];
  $id=$HTTP_POST_VARS['id'];
  $xa=$HTTP_POST_VARS['xa'];
  $ya=$HTTP_POST_VARS['ya'];
  $xb=$HTTP_POST_VARS['xb'];
  $yb=$HTTP_POST_VARS['yb'];
  $str=$HTTP_POST_VARS['str'];
  $color=$HTTP_POST_VARS['color'];
  $form_font=$HTTP_POST_VARS['form_font'];
  $fed=$HTTP_POST_VARS['fed'];
  $placering=$HTTP_POST_VARS['placering'];
  $kursiv=$HTTP_POST_VARS['kursiv'];
  $side=$HTTP_POST_VARS['side'];
  $linjeantal=$HTTP_POST_VARS['linjeantal'];
  
  list($art_nr, $art_tekst)=split(":", $art);
  list($form_nr, $form_tekst)=split(":", $formular);
  if ($HTTP_POST_VARS['linjer']){
    transaktion('begin');  
    for ($x=0; $x<=$linjeantal; $x++) {
      if ((trim($xa[$x])=='-')&&($id[$x])&&($beskrivelse[$x]!='LOGO')) {db_modify("delete from formularer where id =$id[$x]");}
      else {
        if ($ny_beskrivelse[$x]) {$beskrivelse[$x]=trim($beskrivelse[$x]." $".$ny_beskrivelse[$x].";");}
        $xa[$x]=$xa[$x]*1; $ya[$x]=$ya[$x]*1; $xb[$x]=$xb[$x]*1; $yb[$x]=$yb[$x]*1; $str[$x]=$str[$x]*1; $color[$x]=$color[$x]*1;
        if ($x==0){
          if  ($xa[$x]>0) {db_modify("insert into formularer (beskrivelse, formular, art, xa, ya, xb, yb, str, color, font, fed, kursiv, side, placering) values ('$beskrivelse[$x]', $form_nr, $art_nr, $xa[$x], $ya[$x], $xb[$x], $yb[$x], $str[$x], $color[$x], '$form_font[$x]', '$fed[$x]', '$kursiv[$x]', '$side[$x]', '$placering[$x]')");}
        }
        else {db_modify("update formularer set beskrivelse='$beskrivelse[$x]', xa=$xa[$x], ya=$ya[$x], xb=$xb[$x], yb=$yb[$x], str=$str[$x], color=$color[$x], font='$form_font[$x]', fed='$fed[$x]', kursiv='$kursiv[$x]', side='$side[$x]', placering='$placering[$x]'  where id = $id[$x]");}
      } 
    }
  }
  transaktion('commit');   
}
#}


print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td width=100% height=2.5% align=\"center\" valign=\"top\" collspan=2>";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=$returside accesskey=\"t\">Tilbage</a></small></td>";
print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Formularkort</small></td>";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=logoupload.php?upload=yes accesskey=\"u\">Hent logo</a><br></small></td>";
print "</tbody></table></td></tr>";
print "<tr><td align=center width=100%><table align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";

print "<tr><td colspan=10 align=center><table><tbody>";
print "<form name=formularvalg action=$_SERVER[PHP_SELF] method=\"post\">";
print "<tr><td> $font<small> Formular</td>";
print "<td><SELECT NAME=formular>";
if ($formular) {print "<option>$formular</option>";}
print "<option>1:Tilbud</option>";
print "<option>2:Ordrebekr&aelig;ftelse</option>";
print "<option>3:F&oslash;lgeseddel</option>";
print "<option>4:Faktura</option>";
print "<option>5:Kreditnota</option>";
print "</SELECT></td>";
print "<td> $font<small> Art</td>";
print "<td><SELECT NAME=art>";
if ($formular) {print "<option>$art</option>";}
print "<option>1:Linjer</option>";
print "<option>2:Tekster</option>";
print "<option>3:Ordrelinjer</option>";
print "</SELECT></td>";
print "<td><input type=submit accesskey=\"v\" value=\"V&aelig;lg\" name=\"formularvalg\"></td></tr>";
print "</tbody></table></td></tr>";
#if ($form_nr) {

  print "<form name=linjer action=$_SERVER[PHP_SELF] method=\"post\">";

if ($art_nr==1) {
    print "<tr><td><br></td></tr>";
    print "<tr><td colspan=10 align=center>$font LOGO</td></tr>";
    print "<tr><td><br></td></tr>";

    print "<tr><td></td><td></td><td align=center>$font<small>X</td><td align=center>$font<small> Y</td></tr>";
    $x=1;
    $query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse ='LOGO'");
    $row=db_fetch_array($query);
    print "<tr>";
    print "<input type=hidden name=id[$x] value=$row[id]><input type=hidden name=beskrivelse[$x] value='LOGO'>";
    print "<td colspan=2></td><td align=center><input type=text align=right size=5 name=xa[$x] value=$row[xa]>";
    print "<td align=center><input type=text align=right size=5 name=ya[$x] value=$row[ya]>";

    print "<tr><td><br></td></tr>";
    print "<tr><td colspan=6 align=center>$font Linjer</td></tr>";
    print "<tr><td><br></td></tr>";

    print "<tr><td colspan=2 align=center>$font<small> Start</td>";
    print "<td colspan=2 align=center>$font<small> Slut</td></tr>";
    print "<tr><td align=center>$font<small>X</td><td align=center>$font<small> Y</td><td align=center>$font<small> X</td><td align=center>$font<small> Y</td>";
    print "<td align=center>$font<small> Bredde</td><td align=center>$font<small> Farve</td></tr>";

    $x=0;
    print "<tr>";
    print "<td align=center><input type=text align=right size=5 name=xa[$x]>";
    print "<td align=center><input type=text align=right size=5 name=ya[$x]>";
    print "<td align=center><input type=text align=right size=5 name=xb[$x]>";
    print "<td align=center><input type=text align=right size=5 name=yb[$x]>";
    print "<td align=center><input type=text align=right size=5 name=str[$x]>";
    print "<td align=center><input type=text align=right size=5 name=color[$x]>";
    print "</tr>";
 
# echo "select * from formularer where formular = $form_nr and art = $art_nr<br>"; 
    $x=1;
    $query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse !='LOGO'");
    while ($row=db_fetch_array($query))
    {
      $x++; 
      print "<tr>";
      print "<input type=hidden name=id[$x] value=$row[id]>";
      print "<td align=center><input type=text align=right size=5 name=xa[$x] value=$row[xa]>";
      print "<td align=center><input type=text align=right size=5 name=ya[$x] value=$row[ya]>"; 
      print "<td align=center><input type=text align=right size=5 name=xb[$x] value=$row[xb]>";
      print "<td align=center><input type=text align=right size=5 name=yb[$x] value=$row[yb]>";
      print "<td align=center><input type=text align=right size=5 name=str[$x] value=$row[str]>";
      print "<td align=center><input type=text align=right size=5 name=color[$x] value=$row[color]>";
      print "</tr>";
    }   
  } 
  elseif ($art_nr==2) {
     print "<tr><td></td><td align=center>$font<small>Tekst</td>";
     print "<td align=center>$font<small>X</td><td align=center>$font<small> Y</td>";
     print "<td align=center>$font<small>H&oslash;jde</td><td align=center>$font<small> Farve</td>";
     print "<td align=center>$font<small>Plac.</td><td align=center>$font<small>Font</td>";
     print "<td align=center>$font<small>Side</td>";
     print "<td align=center>$font<small>Fed</td><td align=center>$font<small>&nbsp;Kursiv</td>";
#    print "<td align=center>$font<small>Understr.</td></tr>";

    $x=0;
    print "<tr>";
    print "<td><SELECT NAME=ny_beskrivelse[$x]>";
    print "<option></option>";
    print "<option>eget_firmanavn</option>";
    print "<option>egen_addr1</option>";
    print "<option>egen_addr2</option>";
    print "<option>eget_postnr</option>";
    print "<option>eget_bynavn</option>";
    print "<option>eget_land</option>";
    print "<option>eget_cvrnr</option>";
    print "<option>egen_tlf</option>";
    print "<option>egen_fax</option>";
    print "<option>egen_bank_navn</option>";
    print "<option>egen_bank_reg</option>";
    print "<option>egen_bank_konto</option>";
    print "<option>egen_email</option>";
    print "<option>egen_web</option>";
    print "<option>ansat_initialer</option>";
    print "<option>ansat_navn</option>";
    print "<option>ansat_addr1</option>";
    print "<option>ansat_addr2</option>";
    print "<option>ansat_postnr</option>";
    print "<option>ansat_by</option>";
    print "<option>ansat_email</option>";
    print "<option>ansat_mobil</option>";
    print "<option>ansat_tlf</option>";
    print "<option>ansat_fax</option>";
    print "<option>ansat_privattlf</option>";
    print "<option>ordre_firmanavn</option>";
    print "<option>ordre_addr1</option>";
    print "<option>ordre_addr2</option>";
    print "<option>ordre_postnr</option>";
    print "<option>ordre_bynavn</option>";
    print "<option>ordre_land</option>";
    print "<option>ordre_kontakt</option>";
    print "<option>ordre_cvrnr</option>";
    print "<option>ordre_ordredate</option>";
    print "<option>ordre_levdate</option>";
    print "<option>ordre_notes</option>";
    print "<option>ordre_ordrenr</option>";
    print "<option>ordre_momssats</option>";
    print "<option>ordre_kundeordrenr</option>";
    print "<option>ordre_lev_navn</option>";
    print "<option>ordre_lev_addr1</option>";
    print "<option>ordre_lev_addr2</option>";
    print "<option>ordre_lev_postnr</option>";
    print "<option>ordre_lev_bynavn</option>";
    print "<option>ordre_lev_kontakt</option>";
    print "<option>ordre_ean</option>";
    print "<option>ordre_institution</option>";
    print "<option>ordre_lev_kontakt</option>";
    if (substr($formular,0,1)==3) {
      print "<option>levering_lev_nr</option>";
      print "<option>levering_salgsdate</option>";
    } 
    if (substr($formular,0,1)==4) {
      print "<option>ordre_fakturanr</option>";
      print "<option>ordre_fakturadate</option>";
    }  
    print "</SELECT></td>";
    print "<td align=center><input type=text size=25 name=beskrivelse[$x]></td>";
    print "<td align=center><input type=text align=right size=5 name=xa[$x]></td>";
    print "<td align=center><input type=text align=right size=5 name=ya[$x]></td>";
    print "<td align=center><input type=text align=right size=5 name=str[$x]></td>";
    print "<td align=center><input type=text align=right size=5 name=color[$x]></td>";
    print "<td><SELECT NAME=placering[$x]>";
    print "<option>V</option>";
    print "<option>C</option>";
    print "<option>H</option>";
    print "</SELECT></td>";
    print "<td><SELECT NAME=form_font[$x]>";
      print "<option>Helvetica</option>";
#   print "<option>Courier</option>";
#   print "<option>Bookman</option>";
    print "<option>Times</option>";
    print "</SELECT></td>";
    print "<td><SELECT NAME=side[$x]>";
    print "<option>A</option>";
    print "<option>1</option>";
    print "<option>S</option>";
    print "<option>!S</option>";
    print "</SELECT></td>";
    print "<td align=center><input type=checkbox name=fed[$x]></td>";
    print "<td align=center><input type=checkbox name=kursiv[$x]></td>";
#  print "<td align=center><input type=checkbox name=understr[$x]>";
    print "</tr>";
 
# echo "select * from formularer where formular = $form_nr and art = $art_nr<br>";
    $query=db_select("select * from formularer where formular = $form_nr and art = $art_nr order by ya desc, xa");
    while ($row=db_fetch_array($query))
    {
      $x++;
      print "<tr>";
      print "<input type=hidden name=id[$x] value=$row[id]>";
      print "<td><SELECT NAME=ny_beskrivelse[$x]>";
      print "<option></option>";
      print "<option>eget_firmanavn</option>";
      print "<option>egen_addr1</option>";
      print "<option>egen_addr2</option>";
      print "<option>eget_postnr</option>";
      print "<option>eget_bynavn</option>";
      print "<option>eget_land</option>";
      print "<option>eget_cvrnr</option>";
      print "<option>egen_tlf</option>";
      print "<option>egen_fax</option>";
      print "<option>egen_bank_navn</option>";
      print "<option>egen_bank_reg</option>";
      print "<option>egen_bank_konto</option>";
      print "<option>egen_email</option>";
      print "<option>egen_web</option>";
      print "<option>ansat_initialer</option>";
      print "<option>ansat_navn</option>";
      print "<option>ansat_addr1</option>";
      print "<option>ansat_addr2</option>";
      print "<option>ansat_postnr</option>";
      print "<option>ansat_by</option>";
      print "<option>ansat_email</option>";
      print "<option>ansat_mobil</option>";
      print "<option>ansat_tlf</option>";
      print "<option>ansat_fax</option>";
      print "<option>ansat_privattlf</option>";
      print "<option>ordre_firmanavn</option>";
      print "<option>ordre_addr1</option>";
      print "<option>ordre_addr2</option>";
      print "<option>ordre_postnr</option>";
      print "<option>ordre_bynavn</option>";
      print "<option>ordre_land</option>";
      print "<option>ordre_kontakt</option>";
      print "<option>ordre_cvrnr</option>";
      print "<option>ordre_ordredate</option>";
      print "<option>ordre_levdate</option>";
      print "<option>ordre_notes</option>";
      print "<option>ordre_ordrenr</option>";
      print "<option>ordre_momssats</option>";
      print "<option>ordre_kundeordrenr</option>";
      print "<option>ordre_lev_navn</option>";
      print "<option>ordre_lev_addr1</option>";
      print "<option>ordre_lev_addr2</option>";
      print "<option>ordre_lev_postnr</option>";
      print "<option>ordre_lev_bynavn</option>";
      print "<option>ordre_lev_kontakt</option>";
      print "<option>ordre_ean</option>";
      print "<option>ordre_institution</option>";
      print "<option>ordre_lev_kontakt</option>";
      print "<option>formular_side</option>";
      print "<option>formular_nextside</option>";
      print "<option>formular_transportsum</option>";
      print "<option>formular_moms</option>";
      print "<option>formular_sum</option>";
      print "<option>formular_ialt</option>";
      if (substr($formular,0,1)==3) {
        print "<option>formular_lev_nr</option>";
        print "<option>levering_salgsdate</option>";
     } 
      if (substr($formular,0,1)==4) {
        print "<option>ordre_fakturanr</option>";
        print "<option>ordre_fakturadate</option>";
    }  
    print "</SELECT></td>";
      print "<td align=center><input type=text size=25 name=beskrivelse[$x] value='$row[beskrivelse]'></td>";
      print "<td align=center><input type=text align=right size=5 name=xa[$x] value=$row[xa]></td>";
     print "<td align=center><input type=text align=right size=5 name=ya[$x] value=$row[ya]></td>";
      print "<td align=center><input type=text align=right size=5 name=str[$x] value=$row[str]></td>";
      print "<td align=center><input type=text align=right size=5 name=color[$x] value=$row[color]></td>";
      print "<td><SELECT NAME=placering[$x]>";
      print "<option>$row[placering]</option>";
      print "<option>V</option>";
      print "<option>C</option>";
      print "<option>H</option>";
      print "</SELECT></td>";
      print "<td><SELECT NAME=form_font[$x]>";
      print "<option>$row[font]</option>";
      print "<option>Helvetica</option>";
#      print "<option>Courier</option>";
#      print "<option>Bookman</option>";
      print "<option>Times</option>";
    print "<option>Ocrbb12</option>";
       print "</SELECT></td>";
      print "<td><SELECT NAME=side[$x]>";
      print "<option>$row[side]</option>";
      print "<option>A</option>";
      print "<option>1</option>";
      print "<option>S</option>";
      print "<option>!S</option>";
      print "</SELECT></td>";
      if ($row[fed]=='on') {$row[fed]='checked';}
      print "<td align=center><input type=checkbox name=fed[$x] $row[fed]></td>";
      if ($row[kursiv]=='on') {$row[kursiv]='checked';}
      print "<td align=center><input type=checkbox name=kursiv[$x] $row[kursiv]></td>";
      print "</tr>";
    }   
  }

  elseif ($art_nr==3) {
    $x=1;
    print "<tr><td></td><td></td><td align=cente>$font<small>Linjeantal</td>";
    print "<td align=center>$font<small>Y</td>";
    print "<td align=center>$font<small>Linafs.</td></tr>";
#    print "<td align=center>$font<small>Understr.</td></tr>";
    $query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse = 'generelt' order by xa");
    if (!$row=db_fetch_array($query)) {
      $query=db_modify ("insert into formularer (formular, art, beskrivelse, xa, ya, xb) values ($form_nr, $art_nr, 'generelt', 34, 185, 4)");
      $query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse = 'generelt' order by xa");
      $row=db_fetch_array($query);
    }
    print "<tr><td></td><td></td>";
    print "<input type=hidden name=id[$x] value=$row[id]>";
    print "<input type=hidden name=beskrivelse[$x] value=$row[beskrivelse]>";
    print "<td align=center><input type=text align=right size=5 name=xa[$x] value=$row[xa]></td>";
    print "<td align=center><input type=text align=right size=5 name=ya[$x] value=$row[ya]></td>";
    print "<td align=center><input type=text align=right size=3 name=xb[$x] value=$row[xb]></td></tr>";
    print "<tr><td>$font<small>Beskrivelse</td>";
    print "<td align=center>$font<small>X</td>";
    print "<td align=center>$font<small>H&oslash;jde</td><td align=center>$font<small> Farve</td>";
    print "<td align=center>$font<small>Plac.</td><td align=center>$font<small>Font</td><td align=center>$font<small> Fed</td>";
    print "<td align=center>$font<small> Kursiv</td><td align=center>$font<small> Tekstl&aelig;ngde</td></tr>";
#    print "<td align=center>$font<small>Understr.</td></tr>";

    $x=0;
    print "<tr>";
    print "<td><SELECT NAME=beskrivelse[$x]>";
    print "<option>posnr</option>";
    print "<option>varenr</option>";
    print "<option>antal</option>";
    print "<option>beskrivelse</option>";
    print "<option>pris</option>";
    print "<option>rabat</option>";
    print "<option>linjesum</option>";
    if (substr($formular,0,1)==3) {
      print "<option>lev_tidl_lev</option>";
      print "<option>lev_antal</option>";
      print "<option>lev_rest</option>";
    } 
    print "</SELECT></td>";
#    print "<td align=center><input type=text align=right size=5 name=xa[$x]></td>";
    print "<td align=center><input type=text align=right size=5 name=xa[$x]></td>";
    print "<td align=center><input type=text align=right size=5 name=str[$x]></td>";
    print "<td align=center><input type=text align=right size=5 name=color[$x]></td>";
    print "<td><SELECT NAME=placering[$x]>";
    print "<option>V</option>";
    print "<option>C</option>";
    print "<option>H</option>";
    print "</SELECT></td>";
    print "<td><SELECT NAME=form_font[$x]>";
    print "<option>Helvetica</option>";
#   print "<option>Courier</option>";
#   print "<option>Bookman</option>";
    print "<option>Times</option>";
    print "</SELECT></td>";
    print "<td align=center><input type=checkbox name=fed[$x]></td>";
    print "<td align=center><input type=checkbox name=kursiv[$x]></td>";
#  print "<td align=center><input type=checkbox name=understr[$x]>";
    print "</tr>";
    
# echo "select * from formularer where formular = $form_nr and art = $art_nr<br>";
    $x=1;
    $query=db_select("select * from formularer where formular = $form_nr and art = $art_nr and beskrivelse != 'generelt' order by xa");
    while ($row=db_fetch_array($query))
    {
      $x++;
      print "<tr>";
      print "<input type=hidden name=id[$x] value=$row[id]>";
      print "<td><SELECT NAME=beskrivelse[$x]>";
      print "<option>$row[beskrivelse]</option>";
      print "<option>posnr</option>";
      print "<option>varenr</option>";
      print "<option>antal</option>";
      print "<option>beskrivelse</option>";
      print "<option>pris</option>";
      print "<option>rabat</option>";
      print "<option>linjesum</option>";
      if (substr($formular,0,1)==3) {
        print "<option>lev_tidl_lev</option>";
        print "<option>lev_antal</option>";
        print "<option>lev_rest</option>";
     } 
      print "</SELECT></td>";
      print "<td align=center><input type=text align=right size=5 name=xa[$x] value=$row[xa]></td>";
      print "<td align=center><input type=text align=right size=5 name=str[$x] value=$row[str]></td>";
      print "<td align=center><input type=text align=right size=5 name=color[$x] value=$row[color]></td>";
      print "<td><SELECT NAME=placering[$x]>";
      print "<option>$row[placering]</option>";
      print "<option>V</option>";
      print "<option>C</option>";
      print "<option>H</option>";
      print "</SELECT></td>";
      print "<td><SELECT NAME=form_font[$x]>";
      print "<option>$row[font]</option>";
      print "<option>Helvetica</option>";
#   print "<option>Courier</option>";
#   print "<option>Bookman</option>";
      print "<option>Times</option>";
      print "</SELECT></td>";
      if ($row[fed]=='on') {$row[fed]='checked';}
      print "<td align=center><input type=checkbox name=fed[$x] $row[fed]></td>";
      if ($row[kursiv]=='on') {$row[kursiv]='checked';}
      print "<td align=center><input type=checkbox name=kursiv[$x] $row[kursiv]></td>";
      if ($row[beskrivelse]=='beskrivelse'){print "<td align=center><input type=text align=right size=5 name=xb[$x] value=$row[xb]></td>";}
      print "</tr>";
    }   
  }

  print "<input type=hidden name=linjeantal value=$x>";
  print "<tr><td colspan=10 align=center><hr></td></tr>";
  print "<td colspan=10 align=center><input type=submit accesskey=\"v\" value=\"Opdater\" name=\"linjer\"></td></tr>";
  print "</tbody></table></td></tr>";

?>

<tr><td  width="100%" height="2.5%" align = "center" valign = "bottom">
    <table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
      <td width="100%" bgcolor="<? echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
    </tbody></table>
</td></tr>
</tbody></table>
</body></html>
