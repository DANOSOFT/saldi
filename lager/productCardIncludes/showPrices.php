<?php
    print "<tr><td height=\"20%\"><b>".findtekst(2017,$sprog_id)."</b></td><td width=\"33%\" align=\"center\">$enhed</td><td width=\"33%\" align=\"center\">$enhed2</td></tr>";
    if ($p_grp_salgspris) $type="readonly=readonly";
    else $type="type=text";
    if (round($salgspris,3)==0.001) $tmp=dkdecimal($salgspris,3); #20161006
    else $tmp=dkdecimal($salgspris,2);
#       if (isset($provisionPercentage)) {
#        setPercentageField($type, $tmp, $enhed2, $salgspris, $forhold, $incl_moms, $id, $provisionPercentage);
#    } else {
#        setPercentageField($type, $tmp, $enhed2, $salgspris, $forhold, $incl_moms, $id, null);
#    }
    print "<tr><td>".findtekst(949,$sprog_id)."</td><td>";
    print "<input $type style=text-align:right size=\"8\" name=\"salgspris\" value=\"$tmp\">";
    print "</td>";
    if ($enhed2) {
        $tmp=dkdecimal($salgspris/$forhold,2);
        print "<td><INPUT class=\"inputbox\" READONLY=readonly style=text-align:right size=8 value=\"$tmp\"></td>";
    } elseif($incl_moms) {
        print  "<td>(".findtekst(2018,$sprog_id).")</td>";
    }
    if ($p_grp_tier_price) $type="readonly=readonly";
    else $type="type=text";

    // B2B price
    $tmp=dkdecimal($tier_price,2);
    print "<tr><td>B2B ".findtekst(949,$sprog_id)."</td><td><input $type style=text-align:right size=8 name=tier_price value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td>";
    if ($enhed2) {
        $tmp=dkdecimal($tier_price/$forhold,2);
        print "<td><INPUT class=\"inputbox\" READONLY=readonly style=text-align:right size=8 value=\"$tmp\"></td>";
    }
    print "</tr>";

    // retail price
    if ($p_grp_retail_price) $type="readonly=readonly";
    else $type="type=text";
    $tmp=dkdecimal($retail_price,2);
    print "<tr><td>Vejl.pris</td><td><input $type style=text-align:right size=8 name=retail_price value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td>";
    if ($enhed2) {
        $tmp=dkdecimal($retail_price/$forhold,2);
        print "<td><INPUT class=\"inputbox\" READONLY=readonly style=text-align:right size=8 value=\"$tmp\"></td>";
    }
    print "</tr>";
    if ($stockItem) {
        $r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
        $fifo=$r['box6'];
    }
    $title = NULL;
    #   if ($provision == 0 && $salgspris > 0) $useCommission = 0;  # 20210401
#   elseif (($provision > 0 && $salgspris > $kostpris[0] && $kostpris[0] > 1)) $provision = $useCommission = 0;
/*
  if ($useCommission && $provision && $salgspris > $kostpris[0] && $kostpris[0] > 1) {
        if (abs($kostpris[0] - ($salgspris - $salgspris*$provision/100)) < 0.1) $kostpris[0] = (100 - $provision) / 100;
        $title = "title = 'Når kostpris er større end 0 og mindre end 1 og varen er en kommisionsvare \n";
        $title.= "bliver kostprisen beregnet som den pris varen sælges til * den her anførte kostpris'";
    } else
*/
    if ($useCommission && $provision && $salgspris == 0 && $kostpris[0] == 0) {
        $kostpris[0] = 1 - $provision/100;
        $title = "title = 'Når salgspris er 0 og kostpris er større end 0 og mindre end 1 \n ";
        $title.= "bliver kostprisen beregnet som den pris varen sælges til * den her anførte kostpris'\n";
        $title.= "Er varen en kommissionsvare er kostprisen kommissionskundens andel af salget";
    }
    if ($useCommission) {
        if ($salgspris > 0) {
            $title = "title = 'Når en kommissionsvare har fast salgpris beregnes kostprisen automatisk ud fra kommissionssatsen \n";
            $title.= "med mindre der skrives en anden kostpris her. '\n";
        } else {
            $title = "title = 'Når salgspris er 0 og kostpris er større end 0 og mindre end 1 \n ";
            $title.= "bliver kostprisen beregnet som den pris varen sælges til * den her anførte kostpris\n";
            $title.= "Er varen en kommissionsvare er kostprisen kommissionskundens andel af salget.\n";
            $title.= "F.eks. hvis varen sælges til kr 200,00 bliver kostprisen (kommissionskundens del) ". dkdecimal(200*$kostpris[0],2) ."'";
        }
    }
    $tmp=dkdecimal($kostpris[0],2);
    if ($p_grp_kostpris || $samlevare) $type="readonly=readonly";
    elseif ($fifo && $beholdning != 0) $type="readonly=readonly";
    else $type="type=text";
    print "<tr><td $title> ".findtekst(950,$sprog_id)."</td><td colspan='2' $title>";
    print "<input $type style=text-align:right size=8 name=kostpris[0] id='costPrice' value=\"$tmp\">";
    if ($useCommission) {
#       if ($salgspris) {
#           $cost = $salgspris * $kostpris[0] / 1;
#           print ' = '. dkdecimal($cost);
#       }
        print "</td></tr>";
        print "<tr><td>".findtekst(2020,$sprog_id).":</td><td>";
        ($provision || $commissionItem)?$checked="checked='checked'":$checked=NULL;
        print "<input type='checkbox' name='commissionItem' $checked onchange=\"javascript:docChange = true;\">";
        if ($provision && !$salgspris && $kostpris[0]) print "&nbsp;". dkdecimal($provision,0) ."%";
        elseif ($provision) {
            print "&nbsp<input type = 'text' style = 'width:30px;text-align:right;' name = 'provision' value = '$provision'>%";
        }
    } elseif ($salgspris) {
        $CM=0;
        $CM = 100 - $kostpris[0] * 100 / $netprice;
        print " ". dkdecimal($CM,1) ."% dg";
    }
    print "</td></tr>";

    // BEGIN 20221004
    print "<tr>";
    print "<td>".findtekst(2077,$sprog_id)."</td>";
    print "<td colspan='2'>";
    print "<select name='on_price_list'>";
    print "<option value=1".(($on_price_list==1)?" selected='selected'":"").">".findtekst(83,$sprog_id)."</option>";
    print "<option value=0".((!$on_price_list)?" selected='selected'":"").">".findtekst(84,$sprog_id)."</option>";
    print "</select>";
    print "</td>";
    print "</tr>";

    if ($show_advanced_price_calc || $salgspris_multiplier>0 || $tier_price_multiplier>0 || $retail_price_multiplier>0){
        print "<tr><td height='25px' colspan='3' title='".findtekst(2105,$sprog_id)."'><input type='checkbox' name='show_advanced_price_calc' checked='checked'> <b>".findtekst(2105,$sprog_id)."</b></td></tr>";

        // Automatically set the sales price from cost price at product list import
        print "<tr title='".findtekst(2079,$sprog_id)."'>";
#       print "<td>".findtekst(2076,$sprog_id)."</td>";
        print "<td>Salgspris DG</td>";
        print "<td colspan='2'>";
        print "<input type='text' style='text-align:right' size='8' name='salgspris_multiplier' value='".dkdecimal($salgspris_multiplier)."' onchange='javascript:docChange = true;'>";
        print "<select name='salgspris_method'>";
        print "<option value='percentage'".(($salgspris_method=='percentage')?" selected='selected'":"").">%</option>";
        print "<option value='amount'".(($salgspris_method=='amount')?" selected='selected'":"").">".findtekst(2065,$sprog_id)."</option>";
        print "</select></td></tr>";
        print "<tr><td>Afrunding</td><td><select name='salgspris_rounding'>";
        print "<option value='no_rounding'".(($salgspris_rounding=='no_rounding')?" selected='selected'":"").">".findtekst(2066,$sprog_id)."</option>";
        print "<option value='std_rounding'".(($salgspris_rounding=='std_rounding')?" selected='selected'":"").">".findtekst(2069,$sprog_id)."</option>";
        print "<option value='rounding_up'".(($salgspris_rounding=='rounding_up')?" selected='selected'":"").">".findtekst(2067,$sprog_id)."</option>";
        print "<option value='round_down'".(($salgspris_rounding=='round_down')?" selected='selected'":"").">".findtekst(2068,$sprog_id)."</option>";
        print "</select>";
        print "</td>";
        print "</tr>";

        // Automatically set the B2B price from cost price at product list import
        print "<tr title='".findtekst(2079,$sprog_id)."'>";
#        print "<td>".findtekst(2075,$sprog_id)."</td>";
        print "<td>B2B DG</td>";
        print "<td colspan='2'>";
        print "<input type='text' style='text-align:right' size='8' name='tier_price_multiplier' value='".dkdecimal($tier_price_multiplier)."' onchange='javascript:docChange = true;'>";
        print "<select name='tier_price_method'>";
        print "<option value='percentage'".(($tier_price_method=='percentage')?" selected='selected'":"").">%</option>";
        print "<option value='amount'".(($tier_price_method=='amount')?" selected='selected'":"").">".findtekst(2065,$sprog_id)."</option>";
        print "</select>";
        print "<select name='tier_price_rounding'>";
        print "<option value='no_rounding'".(($tier_price_rounding=='no_rounding')?" selected='selected'":"").">".findtekst(2066,$sprog_id)."</option>";
        print "<option value='std_rounding'".(($tier_price_rounding=='std_rounding')?" selected='selected'":"").">".findtekst(2069,$sprog_id)."</option>";
        print "<option value='rounding_up'".(($tier_price_rounding=='rounding_up')?" selected='selected'":"").">".findtekst(2067,$sprog_id)."</option>";
        print "<option value='round_down'".(($tier_price_rounding=='round_down')?" selected='selected'":"").">".findtekst(2068,$sprog_id)."</option>";
        print "</select>";
        print "</td>";
        print "</tr>";
/*
        // Automatically set the retail price from cost price at product list import
        print "<tr title='".findtekst(2079,$sprog_id)."'>";
#       print "<td>".findtekst(2080,$sprog_id)."</td>";
        print "<td>Vejl Pris >DG</td>";
        print "<td colspan='2'>";
        print "<input type='text' style='text-align:right' size='8' name='retail_price_multiplier' value='".dkdecimal($retail_price_multiplier)."' onchange='javascript:docChange = true;'>";
        print "<select name='retail_price_method'>";
        print "<option value='percentage'".(($retail_price_method=='percentage')?" selected='selected'":"").">".findtekst(2064,$sprog_id)."</option>";
        print "<option value='amount'".(($retail_price_method=='amount')?" selected='selected'":"").">".findtekst(2065,$sprog_id)."</option>";
        print "</select>";
        print "<select name='retail_price_rounding'>";
        print "<option value='no_rounding'".(($retail_price_rounding=='no_rounding')?" selected='selected'":"").">".findtekst(2066,$sprog_id)."</option>";
        print "<option value='std_rounding'".(($retail_price_rounding=='std_rounding')?" selected='selected'":"").">".findtekst(2069,$sprog_id)."</option>";
        print "<option value='rounding_up'".(($retail_price_rounding=='rounding_up')?" selected='selected'":"").">".findtekst(2067,$sprog_id)."</option>";
        print "<option value='round_down'".(($retail_price_rounding=='round_down')?" selected='selected'":"").">".findtekst(2068,$sprog_id)."</option>";
        print "</select>";
        print "</td>";
        print "</tr>";
*/
    } else {
        print "<tr><td colspan='3'><input type='checkbox' name='show_advanced_price_calc'> ".findtekst(2105,$sprog_id)."</td></tr>\n";
    }
    // END 20221004
?>
