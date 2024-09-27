<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/varekort_includes/updateParentStock.php --- lap 4.1.0 --- 2024-06-18 ---
// LICENS
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------
function updateParentStock($id, $diff) {
    $x=0;
    $y=0;
#cho "select * from styklister where vare_id =$id<br>";
    $q1 = db_select("select * from styklister where vare_id = $id",__FILE__ . " linje " . __LINE__);
    while ($r1 = db_fetch_array($q1)) {
        $x++;
        $indgaar_i[$x]=$r1['indgaar_i'];
        $stockDiff=$r1['antal']*$diff;
#cho "update varer set kostpris=kostpris+$belob where id=$indgaar_i[$x]<br>";
        $qtxt = "update varer set beholdning=beholdning+$stockDiff where id= '$indgaar_i[$x]'";
        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        $qtxt = "update lagerstatus set beholdning=beholdning+$stockDiff where vare_id= '$indgaar_i[$x]'";
        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
    }
    $y=$x;
    for ($y=1; $y<=$x; $y++) {
#cho "select * from styklister where vare_id=$indgaar_i[$y]<br>";
        $q1 = db_select("select * from styklister where vare_id= $indgaar_i[$y]",__FILE__ . " linje " . __LINE__);
        while ($r1 = db_fetch_array($q1)) {
            if ($row['indgaar_i']!=$id) {
                $x++;
                $vare_id[$x]=$r1['id'];
                $indgaar_i[$x]=$r1['indgaar_i'];
                $antal[$x]=$r1['antal'];
#cho "update varer set kostpris=kostpris+$diff*$antal[$x] where id=$vare_id[$x]<br>";
                $qtxt = "update varer set beholdning=beholdning+$stockDiff*$antal[$x] where id=$vare_id[$x]";
                db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                $qtxt = "update lagerstatus set beholdning=beholdning+$stockDiff*$antal[$x] where vare_id=$vare_id[$x]";
                db_modify($qtxt,__FILE__ . " linje " . __LINE__);
            } else {
                $qtxt = "select varenr from varer where id=$vare_id[$y]";
                $r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                db_modify("delete from styklister where id=$r1[id]",__FILE__ . " linje " . __LINE__);
                print "<BODY onLoad=\"javascript:alert('Cirkul&aelig;r reference registreret varenr.: $r2[varenr] fjernet fra styklisten')\">";
            }
        }
    }
}
?>
