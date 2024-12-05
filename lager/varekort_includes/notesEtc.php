<?php 
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----/lager/varekort_includes/notesEtx.php----lap 3.9.7---2020-11-22-----
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
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 2020.11.22 PHR - Created this file from notes section of ../varekort.php
// 17/05-2023 PBLM - Fixed made rental button work

print "<!-- varekort_includes/notsEtc.php start -->"; 

print "<tr><td valign='top' colspan='3'><table border='0' width='100%'><tbody>"; # Notetabel ->
print "<tr><td valign='top'>".findtekst(391,$sprog_id)."</td>";
print "<td colspan='6'><textarea name='notes' rows='4' cols='100'>$notes</textarea></td>";
print "<td><button type='button' id='rentItem'>".findtekst(2050,$sprog_id)."</button></td>";
print "</tr><tr>";
if ($serienr == 'on') print "<td>Serienr.&nbsp;<input class='inputbox' type=checkbox name=serienr checked></td>";
elseif  ($box8 == 'on') print "<td>Serienr&nbsp;<input class='inputbox' type=checkbox name=serienr></td>";
else print "<td></td>";
$r=db_fetch_array(db_select("select count(id) as lev_antal from vare_lev where vare_id='$id'",__FILE__ . " linje " . __LINE__));
$lev_antal=$r['lev_antal']*1;
($samlevare)?$checked='checked':$checked=''; 
($beholdning || $lev_antal || $varianter)?$readonly='disabled':$readonly='';
$title = "Afmærk her hvis varen er en samlevare ";
$title.= "Feltet er låst, hvis beholdningen er forskellig fra 0, der er varianter på varen eller varen indgår i en uafsluttet ordre";
print "<td><span title='$title'>Samlevare</span>";
print "&nbsp;<input $readonly title='$title' class='inputbox' type=checkbox name='samlevare' $checked></td>";
if ($readonly) print "<input type=hidden name='samlevare' value='$samlevare'>";
if ($lukket==0) print "<td>Udg&aring;et &nbsp;<input class='inputbox' type=checkbox name=lukket></td>";
else print "<td>Udg&aring;et &nbsp;<input class='inputbox' type=checkbox name=lukket checked></td>";
print "</tbody></table></td></tr>";# <- Note tabel 


print "<!-- varekort_includes/notsEtc.php end -->"; 

?>
<script>
    document.getElementById('rentItem').addEventListener('click', () => {
        fetch("../rental/rental.php?subItemId=<?php print $id; ?>")
        .then(res => res.json())
        .then(res => {
            alert(res.message)
        })
    });
</script>