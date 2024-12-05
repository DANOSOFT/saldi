<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/frontpage/itemTxt.php ---------- lap 3.7.7----2019.03.14-------
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
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190215 Make text on pos_ordre frontpage that depends on the country

function setRoundUpText($status, $difkto, $rest, $betvaluta, $afrundet)
{
    if (getCountry() == "Switzerland") {
        $txtField = "Rounded";
    } else {
        $txtField = "Afrundet";
    }
		if ($status < '3' && $difkto && $afrundet != $rest && $betvaluta=='DKK') print "<tr><td>$txtField</td><td align=\"right\"></td><td></td><td align=\"right\"></td><td align=\"right\">".dkdecimal($afrundet,2)."</td></tr>\n";
}

function setItemHeaderTxt($lagerantal, $fokus)
{
	$txtArray = getItemHeaderTxt();
	$txt = $txtArray['itemNumber'];
	print "<tr><td style=\"width:20%;height:25px;\" valign=\"bottom\">$txt</td>\n";
	$txt = $txtArray['number'];
	print "<td style=\"width:7%\" valign=\"bottom\">$txt</td>\n";
	if ($lagerantal>1) {
        $txt = $txtArray['stock'];
        print "<td style=\"width:7%\"valign=\"bottom\">$txt</td>\n";
    }
    $txt = $txtArray['itemName'];
	print "<td valign=\"bottom\">$txt</td>\n";
    $txt = $txtArray['price'];
	print "<td style=\"width:13%\" align=\"right\" valign=\"bottom\">$txt</td>\n";
 	if ($fokus=="rabat_ny") {
        $txt = $txtArray['discount'];
        print "<td colspan=\"2\" align=\"right\" valign=\"bottom\">$txt</td></tr>\n";
    }
    $txt = $txtArray['sum'];
 	print "<td style=\"width:13%\" align=\"right\" valign=\"bottom\">$txt</td><td style=\"width:80px\"><br></td></tr>\n";
	print "<tr><td colspan=\"7\"><hr></td></tr>\n";
}

function getItemHeaderTxt()
{
    if (getCountry() == "Switzerland") {
        return ['itemNumber' => 'Item number', 'number' => 'Quantity',  'stock' => 'Lager',
            'itemName' => 'Description', 'price' => 'Price', 'discount' => 'Rabat',
            'sum' => 'Total'];
    } else {
        return ['itemNumber' => 'Varenummer', 'number' => 'Antal',  'stock' => 'Lager',
            'itemName' => 'Varenavn', 'price' => 'Pris', 'discount' => 'Rabat', 'sum' => 'Sum'];
    }
}




?>
