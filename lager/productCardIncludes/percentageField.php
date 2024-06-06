<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/lager/varekort_includes/percentageField.php---------lap 3.8.1---2019-06-12	-----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2019-2019 saldi.dk aps
// ----------------------------------------------------------------------
// 20190326 LN Make new percentage field if wanted by the customer
// 20190419 PHR Removed number_format from calculateCost as it makes error when cost >= 1000. and it seems unnessecary
// 20190621 PHR Added trim() to return in function showProvision()
// 20190614 LN Changed the setup, so we use the table settings instead of grupper

function getProvisionPercentage($id)
{
    if (showProvision()) {
        $product = db_fetch_array(db_select("select provision from varer where id='$id'", __FILE__ . "linje" . __LINE__));
        if (isset($product['provision'])) {
            $provision = $product['provision'];
        } else {
            $provision =  defaultProvision();
        }
        return $provision;
    }
}

function defaultProvision()
{
    $qtxt = "select var_value from settings where var_name='defaultProvision' and var_grp='items'";
    $q = db_select($qtxt, __FILE__ . "linje" . __LINE__);
    $r = db_fetch_array($q);
    $provision = $r['var_value'];
    return $provision;
}

function setPercentageField($type, $tmp, $enhed2, $salgspris, $forhold, $incl_moms, $id, $provision = null)
{
    if ($provision == null) {
        $provision = getProvisionPercentage($id);
    } else {
        db_modify("update varer set provision='$provision' where id='$id'", __FILE__ . "linje" . __LINE__);
    }
    if (showProvision()) {
        print "<tr>
                <td style=width:20%;>
                    Salgspris
                </td>
                <td>
                    <input $type style=text-align:right size=\"8\" name=\"salgspris\" value=\"$tmp\"
                    onchange=\"javascript:docChange = true;\">
                </td>";
        if ($enhed2) {
            $tmp=dkdecimal($salgspris/$forhold,2);
            print "<td><INPUT class=\"inputbox\" READONLY=readonly style=text-align:right size=8 value=\"$tmp\"></td>";
        } elseif($incl_moms) {
            print  "<td style=width:30%;>
                        (incl moms)
                    </td>";
        }
        print " <td>
                    Provision
                </td>
                <td>
                    <input type=text id=provisionField style=text-align:right size=\"8\" name=\"provisionPercentage\" value=\"$provision\">
                </td>";
    } else {
        print "<tr>
                    <td>
                        Salgspris
                    </td>
                    <td>
                        <input $type style=text-align:right size=\"8\" name=\"salgspris\" value=\"$tmp\"
                        onchange=\"javascript:docChange = true;\">
                    </td>";
        if ($enhed2) {
            $tmp=dkdecimal($salgspris/$forhold,2);
            print "<td><INPUT class=\"inputbox\" READONLY=readonly style=text-align:right size=8 value=\"$tmp\"></td>";
        } elseif($incl_moms) {
            print  "<td>
                        (incl moms)
                    </td>";
        }
    }
    print "</tr>";
}


function setFinalCostField($lev_ant, $kostpris, $row, $charset, $lev_varenr, $enhed2, $forhold, $vare_lev_id, $lev_id, $salgspris, $id)
{
    $provisionPercentage = getProvisionPercentage($id);
    for ($x=1; $x<=$lev_ant; $x++) {
        $query = db_select("select kontonr, firmanavn from adresser where id='$lev_id[$x]'",__FILE__ . " linje " . __LINE__);
        $row = db_fetch_array($query);
        $y = dkdecimal(calculateCost($kostpris[$x], $salgspris, $provisionPercentage));
        print "<td><span title='Pos = minus sletter leverand&oslash;ren';><input class=\"inputbox\" type=text size=1 name=lev_pos[$x] value=$x onchange=\"javascript:docChange = true;\"></span></td><td> $row[kontonr]:".htmlentities($row['firmanavn'],ENT_COMPAT,$charset)."</td><td><input class=\"inputbox\" type=text style=text-align:right size=9 name=lev_varenr[$x] value=\"$lev_varenr[$x]\" onchange=\"javascript:docChange = true;\"></td><td style=text-align:right><input class=\"inputbox\" type=text style=text-align:right size=9 name=kostpris[$x] value=\"$y\" onchange=\"javascript:docChange = true;\"></td>";
        if (($enhed2)&&($forhold>0)) {
            $y=dkdecimal($kostpris[$x]/$forhold,2);
            print "<td><input class=\"inputbox\" type=text style=text-align:right size=9 name=kostpris2[$x] value=\"$y\" onchange=\"javascript:docChange = true;\"></td>";
        }
        print "</td></tr>";
        print "<input class=\"inputbox\" type=hidden name=vare_lev_id[$x] value=$vare_lev_id[$x]>";
    }
}


function calculateCost($cost, $salesPrice, $provision)
{
    if (showProvision() && $provision == 0) {
        return 0;
    } else if (showProvision()) {
        $calculatedCost = $salesPrice / 100 * (100 - $provision);
        #$calculatedCost = number_format($calculatedCost, 2, '.', ' ');
        return $calculatedCost;
    } else {
        #return number_format($cost, 2, '.', ' ');
        return $cost;
    }
}

function showProvision()
{
    $showProvision = db_fetch_array(db_select("select var_value from settings where var_grp='items' and var_name='showProvision'", __FILE__ . "linje" . __LINE__))['var_value'];
    if ($showProvision == "on") {
      return true;
    } else {
      return false;
    }
}


?>
