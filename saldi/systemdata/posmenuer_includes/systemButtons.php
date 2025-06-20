<?php
// ----------systemdata/posmenuer_includes/systemButtons.php---------------- lap 3.5.5 -- 2019-05-08 --
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
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------------
// LN 20190805 LN Allow only specific countries to see given system buttons
// LN 20190709 LN Add buttons, "Gem bestilling", "Hent bestilling"
// 20191128 PHR	Set $country to 'Denmark' if not set.

$country = db_fetch_array(db_select("select land from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))['land'];
if (!$country) {
	$country="Denmark";
	db_modify("update adresser set land='Denmark' where art = 'S'",__FILE__ . " linje " . __LINE__); 
}
if ($d==6 && $menutype!='U') {
    print "<SELECT CLASS=\"inputbox\" style=\"width:100px;\" name=\"butvnr\">\n";
    if ($c==1)  print "<OPTION value=\"1\">$buttonTextArr[table]</OPTION>\n";
    if ($c==2)  print "<OPTION value=\"2\">$buttonTextArr[user]</OPTION>\n";
    if ($c==3)  print "<OPTION value=\"3\">$buttonTextArr[splitTable]</OPTION>\n";
    if ($c==4)  print "<OPTION value=\"4\">Enter</OPTION>\n";
    if ($c==5)  print "<OPTION value=\"5\">$buttonTextArr[findReceipt]</OPTION>\n";
    if ($c==6)  print "<OPTION value=\"6\">$buttonTextArr[moveTable]</OPTION>\n";
    if ($c==7)  print "<OPTION value=\"7\">$buttonTextArr[boxCount]</OPTION>\n";
    if ($c==8)  print "<OPTION value=\"8\">Kassevalg</OPTION>\n";
    if ($c==9)  print "<OPTION value=\"9\">Køkkenprint</OPTION>\n";
    if ($c==10) print "<OPTION value=\"10\">$buttonTextArr[close]</OPTION>\n";
    if ($c==11) print "<OPTION value=\"11\">$buttonTextArr[draw]</OPTION>\n";
    if ($c==12 && $country == 'Denmark') print "<OPTION value=\"12\">$buttonTextArr[print]</OPTION>\n";
    if ($c==13) print "<OPTION value=\"13\">$buttonTextArr[start]</OPTION>\n";
    if ($c==14) print "<OPTION value=\"14\">Ekspedient</OPTION>\n";
    if ($c==15) print "<OPTION value=\"15\">$buttonTextArr[clear]</OPTION>\n";
    if ($c==16) print "<OPTION value=\"16\">Afslut</OPTION>\n";
    if ($c==17) print "<OPTION value=\"17\">$buttonTextArr[price]</OPTION>\n";
    if ($c==18) print "<OPTION value=\"18\">$buttonTextArr[discount]</OPTION>\n";
    if ($c==19) print "<OPTION value=\"19\">$buttonTextArr[back]</OPTION>\n";
    if ($c==20) print "<OPTION value=\"20\">$buttonTextArr[newCustomer]</OPTION>\n";
    if ($c==21) print "<OPTION value=\"21\">$buttonTextArr[correction]</OPTION>\n";
    if ($c==22) print "<OPTION value=\"22\">Kortterminal</OPTION>\n";
    if ($c==23) print "<OPTION value=\"23\">$buttonTextArr[sendToKitchen]</OPTION>\n";
    if ($c==24) print "<OPTION value=\"24\">Kør bord</OPTION>\n";
    if ($c==25) print "<OPTION value=\"25\">Debitoropslag</OPTION>\n";
    if ($c==26) print "<OPTION value=\"26\">Indbetaling</OPTION>\n";
    if ($c==27) print "<OPTION value=\"27\">Konto</OPTION>\n";
    if ($c==28) print "<OPTION value=\"28\">Enter+Menu</OPTION>\n";
    if ($c==29) print "<OPTION value=\"29\">Vareopslag</OPTION>\n";
    if ($c==30) print "<OPTION value=\"30\">Stamkunder</OPTION>\n";
    if ($c==31) print "<OPTION value=\"31\">Kontoudtog</OPTION>\n";
    if ($c==32 && $country == "Denmark") print "<OPTION value=\"32\">Udskriv sidste</OPTION>\n";
    if ($c==33) print "<OPTION value=\"33\">Sæt</OPTION>\n";
    if ($c==34) print "<OPTION value=\"34\">Følgeseddel</OPTION>\n";
    if ($c==35) print "<OPTION value=\"35\">Leverandøropslag</OPTION>\n";
    if ($c==36) print "<OPTION value=\"36\">Gavekortsalg</OPTION>\n";	# 20181029
    if ($c==37) print "<OPTION value=\"37\">Gavekortstatus</OPTION>\n";	# 20181029
    if ($c==38) print "<OPTION value=\"38\">Totalrabat</OPTION>\n";	# 20190104
    if ($c==39 && $country == "Norway") print "<OPTION value=\"39\">Retur</OPTION>\n"; # LN 20190205
    if ($c==40 && $country == "Norway") print "<OPTION value=\"40\">Proforma</OPTION>\n"; # LN 20190205
    if ($c==41 && $country == "Norway") print "<OPTION value=\"41\">X-Rapport</OPTION>\n"; # LN 20190205
    if ($c==42 && $country == "Norway") print "<OPTION value=\"42\">Z-Rapport</OPTION>\n"; # LN 20190305
    if ($c==43 && $country == "Norway") print "<OPTION value=\"43\">$buttonTextArr[copy]</OPTION>\n"; # LN 20190305
    if ($c==44) print "<OPTION value=\"44\">Hent bestilling</OPTION>\n"; # LN 20190709
    if ($c==45) print "<OPTION value=\"45\">Gem bestilling</OPTION>\n"; # LN 20190709
    if ($c!=1)  print "<OPTION value=\"1\">$buttonTextArr[table]</OPTION>\n";
    if ($c!=2)  print "<OPTION value=\"2\">$buttonTextArr[user]</OPTION>\n";
    if ($c!=3)  print "<OPTION value=\"3\">$buttonTextArr[splitTable]</OPTION>\n";
    if ($c!=4)  print "<OPTION value=\"4\">Enter</OPTION>\n";
    if ($c!=5)  print "<OPTION value=\"5\">$buttonTextArr[findReceipt]</OPTION>\n";
    if ($c!=6)  print "<OPTION value=\"6\">$buttonTextArr[moveTable]</OPTION>\n";
    if ($c!=7)  print "<OPTION value=\"7\">$buttonTextArr[boxCount]</OPTION>\n";
    if ($c!=8)  print "<OPTION value=\"8\">Kassevalg</OPTION>\n";
    if ($c!=9)  print "<OPTION value=\"9\">Køkkenprint</OPTION>\n";
    if ($c!=10) print "<OPTION value=\"10\">$buttonTextArr[close]</OPTION>\n";
    if ($c!=11) print "<OPTION value=\"11\">$buttonTextArr[draw]</OPTION>\n";
    if ($c!=12 && $country == "Denmark") print "<OPTION value=\"12\">$buttonTextArr[print]</OPTION>\n";
    if ($c!=13) print "<OPTION value=\"13\">$buttonTextArr[start]</OPTION>\n";
    if ($c!=14) print "<OPTION value=\"14\">Ekspedient</OPTION>\n";
    if ($c!=15) print "<OPTION value=\"15\">$buttonTextArr[clear]</OPTION>\n";
    if ($c!=16) print "<OPTION value=\"16\">Afslut</OPTION>\n";
    if ($c!=17) print "<OPTION value=\"17\">$buttonTextArr[price]</OPTION>\n";
    if ($c!=18) print "<OPTION value=\"18\">$buttonTextArr[discount]</OPTION>\n";
    if ($c!=19) print "<OPTION value=\"19\">$buttonTextArr[back]</OPTION>\n";
    if ($c!=20) print "<OPTION value=\"20\">$buttonTextArr[newCustomer]</OPTION>\n";
    if ($c!=21) print "<OPTION value=\"21\">$buttonTextArr[correction]</OPTION>\n";
    if ($c!=22) print "<OPTION value=\"22\">Kortterminal</OPTION>\n";
    if ($c!=23) print "<OPTION value=\"23\">$buttonTextArr[sendToKitchen]</OPTION>\n";
    if ($c!=24) print "<OPTION value=\"24\">Kør bord</OPTION>\n";
    if ($c!=25) print "<OPTION value=\"25\">Debitoropslag</OPTION>\n";
    if ($c!=26) print "<OPTION value=\"26\">Indbetaling</OPTION>\n";
    if ($c!=27) print "<OPTION value=\"27\">Konto</OPTION>\n";
    if ($c!=28) print "<OPTION value=\"28\">Enter+Menu</OPTION>\n";
    if ($c!=29) print "<OPTION value=\"29\">Vareopslag</OPTION>\n";
    if ($c!=30) print "<OPTION value=\"30\">Stamkunder</OPTION>\n";
    if ($c!=31) print "<OPTION value=\"31\">Kontoudtog</OPTION>\n";
    if ($c!=32 && $country == "Denmark") print "<OPTION value=\"32\">Udskriv sidste</OPTION>\n";
    if ($c!=33) print "<OPTION value=\"33\">Sæt</OPTION>\n";
    if ($c!=34) print "<OPTION value=\"34\">Følgeseddel</OPTION>\n";
    if ($c!=35) print "<OPTION value=\"35\">Kreditoropslag</OPTION>\n";
    if ($c!=36) print "<OPTION value=\"36\">Gavekortsalg</OPTION>\n";	# 20181029
    if ($c!=37) print "<OPTION value=\"37\">Gavekortstatus</OPTION>\n";	# 20181029
    if ($c!=38) print "<OPTION value=\"38\">Totalrabat</OPTION>\n";	# 20190104
    if ($c!=39 && $country == "Norway") print "<OPTION value=\"39\">Retur</OPTION>\n";  # LN 20190205
    if ($c!=40 && $country == "Norway") print "<OPTION value=\"40\">Proforma</OPTION>\n";   # LN 20190205
    if ($c!=41 && $country == "Norway") print "<OPTION value=\"41\">X-Rapport</OPTION>\n";   # LN 20190205
    if ($c!=42 && $country == "Norway") print "<OPTION value=\"42\">Z-Rapport</OPTION>\n";   # LN 20190305
    if ($c!=43 && $country == "Norway") print "<OPTION value=\"43\">$buttonTextArr[copy]</OPTION>\n";   # LN 20190305
    if ($c!=44) print "<OPTION value=\"44\">Hent bestilling</OPTION>\n"; # LN 20190709
    if ($c!=45) print "<OPTION value=\"45\">Gem bestilling</OPTION>\n"; # LN 20190709
    print	"</SELECT>\n";
    } elseif ($d==8 && $menutype!='U') {
    $valuta[0]='DKK';
    $valutakode[0]=0;
    $x=1;
    $q=db_select("select * from grupper where art = 'VK' order by box1",__FILE__ . " linje " . __LINE__);
    while($r = db_fetch_array($q)){
        $valuta[$x]=$r['box1'];
        $valutakode[$x]=$r['kodenr'];
        $x++;
    }
    print "<SELECT CLASS=\"inputbox\" style=\"width:100px;\" name=\"butvnr\">\n";
    for ($x=0;$x<count($valuta);$x++){
        if ($c==$valutakode[$x]) print "<OPTION value=\"$valutakode[$x]\">$valuta[$x]</OPTION>\n";
    }
    for ($x=0;$x<count($valuta);$x++){
        if ($c!=$valutakode[$x]) print "<OPTION value=\"$valutakode[$x]\">$valuta[$x]</OPTION>\n";
    }
    print "</SELECT>\n";
} else {
    print "<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:100px;text-align:center\" name=\"butvnr\" value=\"$c\"><br>\n";
}


?>
