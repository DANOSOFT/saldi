<?php
    if (!$beskrivelse[0]) $fokus="beskrivelse0";
    print "<tr><td width='17%'>".findtekst(914,$sprog_id)."</td><td>";
    print "<input type='hidden' name='oldDescription' value=\"$beskrivelse[0]\">";
    print "<input class='inputbox' type='text' style='text-align:left;width:400px;' name='beskrivelse0' ";
    if (strpos($beskrivelse[0],"'")) print "value=\"". $beskrivelse[0] ."\" ";
    else print "value='". $beskrivelse[0] ."' ";
    print "onchange=\"javascript:docChange = true;\"></td>";
#   print "<a href=changeDescription.php?id=$id>$beskrivelse[0]</a></td>";
    print "<td rowspan='6' valign='top'>";
    $box6 = NULL;
    $qtxt = "select box6 from grupper where art = 'bilag'";
    if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $box6 = $r['box6'];
    if ($box6) {
        if ($fotonavn) {
            $fotourl=$docfolder.$db."/varefotos/".$id;
            ($noEdit)?$href=NULL:$href="varefoto.php?id=$id&fotonavn=".urlencode($fotonavn);
            print "<a href=\"$href\"><img style=\"border:0px solid;height:100px\" alt=\"$fotonavn\" src=\"$fotourl\"></a>";
        } else {
            ($noEdit)?$href=NULL:$href="varefoto.php?id=$id";
            print "<a href=\"$href\">".findtekst(2022,$sprog_id)."</a>";
        }
    }
    print "</td></tr>";
    for ($x=1;$x<=$vare_sprogantal;$x++) {
        print "<input type=\"hidden\" name=\"vare_tekst_id[$x]\" value=\"$vare_tekst_id[$x]\">";
        print "<tr><td>$vare_sprog[$x]</td><td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:400px;\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\" onchange=\"javascript:docChange = true;\"></td></tr>";
    }
    print "<tr><td>".findtekst(2019,$sprog_id)."</td><td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:400px;\" name=\"trademark\" value=\"$trademark\" onchange=\"javascript:docChange = true;\"></td></tr>";
?>
