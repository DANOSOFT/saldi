<?php
    print "<tr><td colspan=3 height=20%><b>Colli</b></td></tr>";
    $tmp=dkdecimal($colli,2);
    print "<tr><td width=34%>".findtekst(2030,$sprog_id)."</td><td width=33%><input class=\"inputbox\" type=text style=text-align:right size=8 name=colli value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td><td width=33%><br></td></tr>";
    $tmp=dkdecimal($outer_colli,2);
    print "<tr><td height=20%>".findtekst(2031,$sprog_id)."</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=outer_colli value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td></tr>";
    $tmp=dkdecimal($open_colli_price,2);
    print "<tr><td height=20%>".findtekst(2032,$sprog_id)."</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=open_colli_price value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td></tr>";
    $tmp=dkdecimal($outer_colli_price,2);
    print "<tr><td height=20%>".findtekst(950,$sprog_id)."</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=outer_colli_price value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td></tr>";
    $tmp=dkdecimal($colli_webfragt,2);
    print "<tr><td height=20%>".findtekst(2145,$sprog_id)."</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=colli_webfragt value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td></tr>";
?>
