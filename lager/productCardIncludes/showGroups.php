<?php
    print "<tr><td><b>".findtekst(2037,$sprog_id)."</b></td></tr>";
    #varegruppe->
    print "<tr><td width=33%>".findtekst(774,$sprog_id)."</td>";
    if (!$gruppe) $gruppe=1;
    $qtxt="select beskrivelse,box10 from grupper where art='VG' and kodenr = '$gruppe' and fiscal_year = '$regnaar'";
    $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
    if ($r['box10'] && !$operation) {
        $r2 = db_fetch_array(db_select("select MAX(operation) as operation from varer where lukket !='on'",__FILE__ . " linje " . __LINE__));
        $operation=$r2['operation']+1;
    }
    print "<td width=67%>
    <input type=\"hidden\" NAME=\"gruppe\" value=\"$gruppe\">
    <SELECT class=\"inputbox\" NAME=\"ny_gruppe\" style=\"width: 18em\">";
    print "<option value=\"$gruppe\">$gruppe $r[beskrivelse]</option>";
    if (!$beholdning || !$batchItem) { # batchItem added 20090210 to make groupchange possible if stockItem is set.
        if ($samlevare=='on') $query = db_select("select * from grupper where art='VG' and kodenr!='$gruppe' and box8!='on' and fiscal_year = '$regnaar' order by ".nr_cast('kodenr')."",__FILE__ . " linje " . __LINE__);
        elseif ($beholdning) $query = db_select("select * from grupper where art='VG' and kodenr!='$gruppe' and box8='on' and fiscal_year = '$regnaar'  order by ".nr_cast('kodenr')."",__FILE__ . " linje " . __LINE__);# tilfoejet 20090210
        else $query = db_select("select * from grupper where art='VG' and kodenr!='$gruppe' and fiscal_year = '$regnaar' order by ".nr_cast('kodenr')."",__FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($query)) {
            print "<option value=\"$row[kodenr]\">$row[kodenr] $row[beskrivelse]</option>";
        }
    }
    print "</SELECT></td></tr>";
#<- Varegruppe
    if (isset($dvrg_nr[1]) && $dvrg_nr[1]) {
        print "<tr><td>Debitorrabatgrp.</td>";
        print "<td><SELECT class=\"inputbox\" NAME=\"dvrg\" style=\"width: 18em\">";
        if (!$dvrg_nr[0]) print "<option value=\"0\"></option>";
        for ($x=1;$x<=count($dvrg_nr);$x++) {
            if ($dvrg_nr[0] && $dvrg_nr[0]==$dvrg_nr[$x]) print "<option value=\"$dvrg_nr[$x]\">$dvrg_nr[$x] $dvrg_navn[$x]</option>";
        }
        for ($x=1;$x<=count($dvrg_nr);$x++) {
            if ($dvrg_nr[0]!=$dvrg_nr[$x]) print "<option value=\"$dvrg_nr[$x]\">$dvrg_nr[$x] $dvrg_navn[$x]</option>";
        }
    }
    print "</SELECT></td></tr>";
# Prisgruppe->
    print "<tr><td>".findtekst(2038,$sprog_id)."</td>";
    if (!$prisgruppe) $prisgruppe=0;
    print "<td><SELECT class=\"inputbox\" NAME=prisgruppe value='$prisgruppe' style=\"width: 18em\">";
    $qtxt = "select * from grupper where art='VPG' and kodenr='$prisgruppe' order by kodenr";
    if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
        print "<option value=\"$prisgruppe\">$r[beskrivelse]</option>";
    }
    $q = db_select("select * from grupper where art='VPG' and kodenr!='$prisgruppe' order by kodenr",__FILE__ . " linje " . __LINE__);
#if ($prisgruppe) print "<option value=\"0\"></option>";
    while ($r = db_fetch_array($q)) {
        print "<option value=\"$r[kodenr]\">$r[kodenr] $r[beskrivelse]</option>";
    }
    if ($prisgruppe) print "<option value=\"0\"></option>";
    print "</SELECT></td></tr>";
#<- Prisgruppe

# tilbudgruppe->
    print "<tr><td>".findtekst(2039,$sprog_id)."</td>";
    if (!$tilbudgruppe) $tilbudgruppe=0;
    print "<td><SELECT class=\"inputbox\" NAME=tilbudgruppe value='$tilbudgruppe' style=\"width: 18em\">";
    print "<option value=\"$tilbudgruppe\">";
    $qtxt = "select * from grupper where art='VTG' and kodenr='$tilbudgruppe' order by kodenr";
    if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) print $r['beskrivelse'];
    print "</option>";
    $qtxt = "select * from grupper where art='VTG' and kodenr!='$tilbudgruppe' order by kodenr";
    $q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        print "<option value=\"$r[kodenr]\">$r[kodenr] $r[beskrivelse]</option>";
    }
    if ($tilbudgruppe) print "<option value=\"0\"></option>";
    print "</SELECT></td></tr>";
    #<- tilbudgruppe
    # Rabatgruppe->
    print "<tr><td>".findtekst(2040,$sprog_id)."</td>";
    if (!$rabatgruppe) $rabatruppe=0;
    print "<td><SELECT class=\"inputbox\" NAME=rabatgruppe value='$rabatgruppe' style=\"width: 18em\">";
    print "<option value=\"$rabatgruppe\">";
    $qtxt = "select * from grupper where art='VRG' and kodenr='$rabatgruppe' order by kodenr";
    if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) print $r['beskrivelse'];
    print "</option>";
    if ($rabatgruppe) print "<option value=\"0\"></option>";
    $q = db_select("select * from grupper where art='VRG' and kodenr!='$rabatgruppe' order by kodenr",__FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        print "<option value=\"$r[kodenr]\">$r[beskrivelse]</option>";
    }
    print "</SELECT></td></tr>";
    #<- Rabatgruppe
?>
