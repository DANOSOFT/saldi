<?php
// ----------systemdata/stamdata.php---------------- lap 3.5.5 -- 2015-03-31 --
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
// LN 20190304 Make functions to check if the user has admin permissions and can choose the countryConfig


function checkUserAndSetCountryConfig($countryConfig, $permissions)
{
    $numberOnes = substr_count($permissions, 1);
    if ($numberOnes == 21) { # LN 20190304 Ask if the user has permission to change the countryConfig
        setCountryDropdown($countryConfig);
    } else {
        setHardcodedCountry($countryConfig);
    }
}


function setCountryDropdown($countryConfig)
{
    $denmark = 'Denmark';
    $norway = 'Norway';
    $switzerland = 'Switzerland';
    if ($countryConfig == 'Switzerland') {
        $switzerland .= ' selected';
    } elseif ($countryConfig == 'Denmark') {
        $denmark .= ' selected';
    } elseif ($countryConfig == 'Norway') {
        $norway .= ' selected';
    }
    print "<td height=23>Landeconfig</td><td>
            <select position=\"absolute\" style=\"width:150px\" name=\"landeconfig\" value=\"$countryConfig\"> 
                <option value=$denmark> Denmark </option>
                <option value=$switzerland> Switzerland</option>
                <option value=$norway> Norway </option>
            </select>";
}

function setHardcodedCountry($countryConfig)
{
    print "<td height=23>Landeconfig</td>
            <td>
                <font size=\"2\">
                    $countryConfig
                </font>";
}









?>
