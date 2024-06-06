<?php
    $kat_niveauer = $x = 0;
    $kat_id = array();
    $q=db_select("select id,box1,box2 from grupper where art='V_CAT' order by box1",__FILE__ . " linje " . __LINE__);
    while ($r=db_fetch_array($q)) {
        $x++;
        $kat_id[$x]=$r['id'];
        $kat_beskrivelse[$x]=$r['box1'];
        ($r['box2'])?$kat_masters[$x]=explode(chr(9),$r['box2']):$kat_masters[$x]=array();
        $kat_niveau[$x]=count($kat_masters[$x]);
        if ($kat_niveau[$x]>$kat_niveauer) $kat_niveauer=$kat_niveau[$x];
        if (count($kat_masters[$x])) {
            $tmp=count($kat_masters[$x])-1;
            $kat_master[$x]=$kat_masters[$x][$tmp];
        } else $kat_master[$x] = 0;
    }

    for ($x=1;$x<=count($kat_id);$x++) {
        if ($kat_master[$x] && !in_array($kat_master[$x],$kat_id)) {
            db_modify("delete from grupper where id = '$kat_id[$x]'",__FILE__ . " linje " . __LINE__);
        }
    }

    print "<tr><td colspan=\"4\" valign=\"top\"><b>".findtekst(388,$sprog_id)."<!--tekst 388--></b></td></tr>\n";
    $x=0;

$a=1;$b=0;$e=0;$f=0;
$used_id=array();
$brugt=array();
$pre=array();
while ($a <= count($kat_id)) {
        $niveau=0;
#cho "A $a ID $kat_id[$a] Master $kat_master[$a]<br>";
    if (!$kat_master[$a] && !in_array($kat_id[$a],$used_id)) {
        $checked=NULL;
        for ($y=0;$y<count($kategori);$y++) {
            if ($kat_id[$a]==$kategori[$y]) $checked="checked";
        }
        print "<tr><td title=\"ID=$kat_id[$a]\">$kat_beskrivelse[$a]</td>\n";
        print "<td title=\"".findtekst(395,$sprog_id)."\" align=\"center\"><!--tekst 395--><input type=\"checkbox\" name=\"kat_valg[$a]\" $checked></td>\n";
        print "<td title=\"".findtekst(396,$sprog_id)."\"><!--tekst 396--><a href=\"varekort.php?id=$id&rename_category=$kat_id[$a]\" onclick=\"return confirm('Vil du omd&oslash;be denne kategori?')\"><img src=../ikoner/rename.png border=0></a></td>\n";
        if (in_array($kat_id[$a],$kat_master)) print "<td></td>";
        else print "<td title=\"".findtekst(397,$sprog_id)."\"><!--tekst 396--><a href=\"varekort.php?id=$id&delete_category=$kat_id[$a]\" onclick=\"return confirm('Vil du slette denne katagori?')\"><img src=../ikoner/delete.png border=0></a></td>\n";
        print "</tr>\n";
        print "<input type=\"hidden\" name=\"kat_id[$a]\" value=\"$kat_id[$a]\">\n";

#       print "$kat_beskrivelse[$a]<br>";
        $used_id[$b]=$kat_id[$a];
        $b++;
    }
    $c=$a;
    $q=0;
    for ($d=1;$d<=count($kat_id);$d++) {
    $q++;
# Master_id skal være = master  & id må ikke være brugt før og master skal være sat.
#cho "$q $kat_master[$d]()==$kat_id[$c]($kat_beskrivelse[$c]) $kat_beskrivelse[$d]<br>";
    if ($kat_master[$d]==$kat_id[$c] && !in_array($kat_id[$d],$used_id) && in_array($kat_master[$d],$used_id)) {
#cho "her $kat_beskrivelse[$d]<br>";
        $checked=NULL;
        for ($y=0;$y<=$kategori_antal;$y++) {
            if ($kat_id[$d]==$kategori[$y]) $checked="checked";
        }
            print "<tr><td title=\"ID=$kat_id[$d]\">";
            for ($e=0;$e<$kat_niveau[$d];$e++) print "-&nbsp;";
#           print "$a | $c | $d | $kat_id[$d] | $kat_beskrivelse[$d] | $kat_master[$d]</td>\n";
            print "$kat_beskrivelse[$d]</td>\n";
            print "<td title=\"".findtekst(395,$sprog_id)."\" align=\"center\"><!--tekst 395--><input type=\"checkbox\" name=\"kat_valg[$d]\" $checked></td>\n";
            print "<td title=\"".findtekst(396,$sprog_id)."\"><!--tekst 396--><a href=\"varekort.php?id=$id&rename_category=$kat_id[$d]\" onclick=\"return confirm('Vil du omd&oslash;be denne kategori?')\"><img src=../ikoner/rename.png border=0></a></td>\n";
            if (in_array($kat_id[$d],$kat_master)) print "<td></td>";
            else print "<td title=\"".findtekst(397,$sprog_id)."\"><!--tekst 396--><a href=\"varekort.php?id=$id&delete_category=$kat_id[$d]\" onclick=\"return confirm('Vil du slette denne kategori?')\"><img src=../ikoner/delete.png border=0></a></td>\n";
            print "</tr>\n";
            print "<input type=\"hidden\" name=\"kat_id[$d]\" value=\"$kat_id[$d]\">\n";

            $used_id[$b]=$kat_id[$d];
            $nivau++;
            $pre[$niveau]=$c;
            $b++;
            $c=$d;
            $d=1;
#cho "$a | $c | $d | $kat_id[$c] | $kat_beskrivelse[$c] | $kat_master[$c]</br>\n";
        }
#cho "$d==count($kat_id) && $c!=$a<br>";
        if ($d==count($kat_id) && $c!=$a) {
#cho "skifter A $a B $c D $d<br>";
            $c=$a;
            if ($niveau && $pre[$niveau]) $c=$pre[$niveau];
            $d=1;
            $niveau--;
#cho "-> A $a B $c D $d<br>";
        }
#       if ($q>10000) {
#           break 1;
#       }
    }

    $a++;
}

    if ($rename_category){
        for ($x=0;$x<count($kat_id);$x++) {
            if ($rename_category==$kat_id[$x]) $ny_kategori=$kat_beskrivelse[$x];
        }
        $tekst=findtekst(388,$sprog_id);
#       $tekst=str_replace('$ny_kategori',$ny_kategori,$tekst);
        print "<tr><td colspan=\"4\">Ret \"$ny_kategori\" til:</td></tr>\n";
        print "<input type=\"hidden\" name=\"rename_category\" value=\"$rename_category\">\n";
    #   print "<tr><td colspan=\"4\" title=\"".findtekst(390,$sprog_id)."\"><input type=\"text\" size=\"25\" name=\"ny_kategori\" value=\"$ny_kategori\"></td></tr>\n";
    } else $ny_kategori='';
    print "<tr><td colspan=\"4\" title=\"".findtekst(390,$sprog_id)."\"><!--tekst 390--><input class=\"inputbox\" type=\"text\" size=\"25\" name=\"ny_kategori\" placeholder=\"".findtekst(343,$sprog_id)."\" value=\"$ny_kategori\"></td></tr>\n";
?>
