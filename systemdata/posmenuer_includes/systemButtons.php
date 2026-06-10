<?php
// --- systemdata/posmenuer_includes/systemButtons.php --- ver 4.0.5 -- 2022-02-09 --
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
// Copyright (c) 2019-2022 Saldi.dk ApS
// ----------------------------------------------------------------------------
// 20190805 LN Allow only specific countries to see given system buttons
// 20190709 LN Add buttons, "Gem bestilling", "Hent bestilling"
// 20191128 PHR	Set $country to 'Denmark' if not set.
// 20220209	PHR enabled udskriv_sidste for Norway.


$country = db_fetch_array(db_select("select land from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))['land'];
if (!$country) {
	$country="Denmark";
	db_modify("update adresser set land='Denmark' where art = 'S'",__FILE__ . " linje " . __LINE__); 
}
if ($d==6 && $menutype!='U') {
    print "<SELECT CLASS='inputbox' style='width:100px;' name='butvnr'
				onchange=\"document.getElementsByName('posmenuer')[0].submit();\"
    >\n";

# Tables
    print "<OPTION value='1'".($c==1?" selected":"").">$buttonTextArr[table]</OPTION>\n";
    print "<OPTION value='3'".($c==3?" selected":"").">$buttonTextArr[splitTable]</OPTION>\n";
    print "<OPTION value='6'".($c==6?" selected":"").">$buttonTextArr[moveTable]</OPTION>\n";
    print "<OPTION value='24'".($c==24?" selected":"").">Kør bord</OPTION>\n";

# User
    print "<OPTION value='2'".($c==2?" selected":"").">$buttonTextArr[user]</OPTION>\n";
    print "<OPTION value='14'".($c==14?" selected":"").">Ekspedient</OPTION>\n";
    
# Fysiske
    print "<OPTION value='11'".($c==11?" selected":"").">$buttonTextArr[draw]</OPTION>\n";
    if ($country == "Denmark") print "<OPTION value='12'".($c==12?" selected":"").">$buttonTextArr[print]</OPTION>\n";
    print "<OPTION value='32'".($c==32?" selected":"").">Udskriv sidste</OPTION>\n";
    print "<OPTION value='9'".($c==9?" selected":"").">Køkkenprint</OPTION>\n";
    print "<OPTION value='23'".($c==23?" selected":"").">$buttonTextArr[sendToKitchen]</OPTION>\n";
    
# Varer / rabatter
    print "<OPTION value='33'".($c==33?" selected":"").">Sæt</OPTION>\n";
    print "<OPTION value='17'".($c==17?" selected":"").">$buttonTextArr[price]</OPTION>\n";
    print "<OPTION value='18'".($c==18?" selected":"").">$buttonTextArr[discount]</OPTION>\n";
    print "<OPTION value='47'".($c==47?" selected":"").">Sæt</OPTION>"; # PHR 20240823
    print "<OPTION value='38'".($c==38?" selected":"").">Totalrabat</OPTION>\n";	# 20190104
    
# Generelle knapper
    print "<OPTION value='4'".($c==4?" selected":"").">Enter</OPTION>\n";
    print "<OPTION value='28'".($c==28?" selected":"").">Enter+Menu</OPTION>\n";
    print "<OPTION value='16'".($c==16?" selected":"").">Afslut</OPTION>\n";
    print "<OPTION value='5'".($c==5?" selected":"").">$buttonTextArr[findReceipt]</OPTION>\n";
    print "<OPTION value='8'".($c==8?" selected":"").">Kassevalg</OPTION>\n";
    print "<OPTION value='44'".($c==44?" selected":"").">Hent bestilling</OPTION>\n"; # LN 20190709
    print "<OPTION value='45'".($c==45?" selected":"").">Gem bestilling</OPTION>\n"; # LN 20190709
    
# Bogført
    print "<OPTION value='20'".($c==20?" selected":"").">$buttonTextArr[newCustomer]</OPTION>\n";
    print "<OPTION value='21'".($c==21?" selected":"").">$buttonTextArr[correction]</OPTION>\n";

    print "<OPTION value='7'".($c==7?" selected":"").">$buttonTextArr[boxCount]</OPTION>\n";
    print "<OPTION value='10'".($c==10?" selected":"").">$buttonTextArr[close]</OPTION>\n";
    print "<OPTION value='13'".($c==13?" selected":"").">$buttonTextArr[start]</OPTION>\n";
    print "<OPTION value='15'".($c==15?" selected":"").">$buttonTextArr[clear]</OPTION>\n";
    print "<OPTION value='19'".($c==19?" selected":"").">$buttonTextArr[back]</OPTION>\n";
    print "<OPTION value='22'".($c==22?" selected":"").">Kortterminal</OPTION>\n";
    print "<OPTION value='25'".($c==25?" selected":"").">Debitoropslag</OPTION>\n";
    print "<OPTION value='26'".($c==26?" selected":"").">Indbetaling</OPTION>\n";
    print "<OPTION value='27'".($c==27?" selected":"").">Konto</OPTION>\n";
    print "<OPTION value='29'".($c==29?" selected":"").">Vareopslag</OPTION>\n";
    print "<OPTION value='30'".($c==30?" selected":"").">Stamkunder</OPTION>\n";
    print "<OPTION value='31'".($c==31?" selected":"").">Kontoudtog</OPTION>\n";
    print "<OPTION value='34'".($c==34?" selected":"").">Følgeseddel</OPTION>\n";
    print "<OPTION value='35'".($c==35?" selected":"").">Kreditoropslag</OPTION>\n";
    print "<OPTION value='36'".($c==36?" selected":"").">Gavekortsalg</OPTION>\n";	# 20181029
    print "<OPTION value='37'".($c==37?" selected":"").">Gavekortstatus</OPTION>\n";	# 20181029
    if ($country == "Norway") print "<OPTION value='39'".($c==39?" selected":"").">Retur</OPTION>\n";  # LN 20190205
    if ($country == "Norway") print "<OPTION value='40'".($c==40?" selected":"").">Udskriv</OPTION>\n";   # LN 20190205
    if ($country == "Norway") print "<OPTION value='41'".($c==41?" selected":"").">X-Rapport</OPTION>\n";   # LN 20190205
    if ($country == "Norway") print "<OPTION value='42'".($c==42?" selected":"").">Z-Rapport</OPTION>\n";   # LN 20190305
    if ($country == "Norway") print "<OPTION value='43'".($c==43?" selected":"").">$buttonTextArr[copy]</OPTION>\n";   # LN 20190305
    print "<OPTION value='46'".($c==46?" selected":"").">Åben scannermodul</OPTION>\n"; # MMK 20242208
    print "<OPTION value='47'".($c==47?" selected":"").">Lane3000 afstemning</OPTION>\n"; # MMK 20242208
    print "<OPTION value='48'".($c==48?" selected":"").">KDS status</OPTION>\n"; # MMK 20242208
    # Latest id: 48 next id: 49


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
    print "<div
            style='position: relative;'
           >";
    print "<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:100px;text-align:center\" name=\"butvnr\" value=\"$c\"><br>\n";
    print "
      <div class='tooltip' id='varekort-btn'>
        <svg 
          onclick='open_product_page();'
          fill='none' 
          height='16' 
          stroke='currentColor' 
          stroke-linecap='round'
          stroke-linejoin='round' 
          stroke-width='2' 
          viewBox='0 0 24 24' 
          width='16' 
          xmlns='http://www.w3.org/2000/svg'
        >
          <polyline points='15 14 20 9 15 4'/>
          <path d='M4 20v-7a4 4 0 0 1 4-4h12'/>
        </svg>
        <span class='tooltiptext'>Åben varekort</span>
      </div>

";
      
}


?>
