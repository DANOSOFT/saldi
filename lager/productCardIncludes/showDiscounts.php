<?php
    print "<tr><td colspan=\"2\" height=\"20%\"><b>".findtekst(812,$sprog_id)."</b></td><td colspan=\"2\"><a href=\"happyhour.php?vare_id=$id\">".findtekst(2023,$sprog_id)."</a></td></tr>";
    print "<tr>";
    $tmp=dkdecimal($special_price,2);
    if ($incl_moms) {
        $tekst="(".findtekst(2024,$sprog_id).")";
        $title="".findtekst(2018,$sprog_id)."";
    } else {
        $tekst="";
        $title="";
    }
    $inputTxt = "class='inputbox' type='text' style='text-align:right' size='8' name='special_price' ";
    $inputTxt.= "value='$tmp' onchange='javascript:docChange = true;'";
    if ($incl_moms && $specialType == 'price') {
        $tekst="(".findtekst(2024,$sprog_id).")";
        $title="".findtekst(2018,$sprog_id)."";
    } else {
        $tekst="";
        $title="";
    }
    print "<td>";
    if (!$specialType || $m_antal_array[0]) $specialType='price';
    if ($special_price || $m_antal_array[0]) {
        ($specialType=='price')?print "".findtekst(949,$sprog_id)."":print "".findtekst(2025,$sprog_id)."";
        print "<input type='hidden' name='specialType' value='$specialType'>";
    } else {
        $options[0]="<option value='price'>".findtekst(949,$sprog_id)."</option>";
        $options[1]="<option value='percent'>".findtekst(2025,$sprog_id)."</option>";
        print "<select name='specialType'>";
        ($specialType=='price')?print $options[0]:print $options[1];
        ($specialType=='price')?print $options[1]:print $options[0];
        print "</select>";
    }
    print "</td>";
    print "<td title=\"$title\"><input $inputTxt>$tekst</td>";
    $tmp=dkdecimal($campaign_cost,2);
    print "<td height=20%>".findtekst(950,$sprog_id)."</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=campaign_cost value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td></tr>";
    if ($special_price!=0) $tmp=dkdato($special_from_date);
    else $tmp='';
    print "<tr><td height=20%>".findtekst(2026,$sprog_id)."</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=special_from_date value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td>";
    print "<td height=20%>".findtekst(2028,$sprog_id)."</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=special_from_time value=\"$special_from_time\" onchange=\"javascript:docChange = true;\"></td></tr>";
    if ($special_price!=0) $tmp=dkdato($special_to_date);
    else $tmp='';
    print "<tr><td height=20%>".findtekst(2027,$sprog_id)."</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=special_to_date value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td>";
    print "<td height=20%>".findtekst(2029,$sprog_id)."</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=special_to_time value=\"$special_to_time\" onchange=\"javascript:docChange = true;\"></td></tr>";
?>
