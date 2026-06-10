<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------------lager/lagerstatusmailer.php--- lap 4.0.7 --- 2024-08-06 ----
// LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$title="Lagerstatus";

$linjebg=NULL;
$kostvalue=0;$lagervalue=0;$salgsvalue=0;
$dateType = 'levdate';

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$to = get_settings_value("mail", "lagerstatus", "");
$timestep = (int) get_settings_value("time", "lagerstatus", "");
$limit = (int) get_settings_value("trigger", "lagerstatus", "");
$lasttrigger = (int) get_settings_value("triggerstamp", "lagerstatus", 0);

# If it is time to send a report
if ($lasttrigger + ($timestep*60*60) > time()) {
    # Do nothing
} else {
    update_settings_value("triggerstamp", "lagerstatus", time(), "The timestamp of the last sent repport");

    # Get movements
    $qtxt = "SELECT L.vare_id, V.varenr, SUM(L.beholdning) AS total_beholdning, SUM(L.beholdning) - S.beholdning AS bevægelse, V.beskrivelse, S.beholdning
    FROM lagerstatus L
    JOIN varer V ON L.vare_id = V.id
    JOIN stockmovement S ON V.id = S.vareid
    JOIN grupper G ON V.gruppe = G.kodenr AND G.art = 'VG' AND G.fiscal_year = $regnaar AND G.box8 = 'on'
    WHERE V.lukket = '0'
    GROUP BY L.vare_id, V.beskrivelse, V.varenr, S.beholdning
    HAVING SUM(L.beholdning) <= $limit AND SUM(L.beholdning) - S.beholdning != 0";

    # Format mailtext
    $mailtext = "
    <html>
        <head>
            <style>
                table {
                    border-collapse: collapse;
                }
                tr {
                    font-size: 90%;
                }
                th {
                    background-color: #ddddff;
                }
                th, td {
                    padding: .2em .3em;
                    border: 1px solid #999;
                    color: #121212;
                }
                .red {
                    background-color: #ffc3c3;
                }
                .even {
                    background-color: #ffffff;
                }
                .odd {
                    background-color: #f5f5f5;
                }
            </style>
        </head>
        <body>
            <table>
                <tr>
                    <th>Vare nr.</th>
                    <th>Aktuel beholdning (før)</th>
                    <th>Bevægelse</th>
                    <th>Beskrivelse</th>
                    <th>Varekort</th>
                </tr>";
    $x = 0;
    $q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        $movement = dkdecimal($r['bevægelse']);
        $beholdning = dkdecimal($r['beholdning']);
        $totalbeh = dkdecimal($r['total_beholdning']);

        if ($r['beholdning'] > $limit) {
            $class = $x % 2 == 0 ? 'red' : 'red';
        } else {
            $class = $x % 2 == 0 ? 'even' : 'odd';
        }
        $mailtext .= "<tr class='$class'>
            <td>$r[varenr]</td>
            <td>$totalbeh ($beholdning)</td>
            <td>$movement</td>
            <td>$r[beskrivelse]</td>
            <td><a href='https://ssl4.saldi.dk/pos/lager/varekort.php?id=$r[vare_id]'>[åben]</a></td>
        </tr>\n";

        $x++;
    }
    $mailtext .= "</table></body></html>";

    # Update stockmovement
    $qtxt = "DELETE FROM stockmovement";
    db_modify($qtxt,__FILE__ . " linje " . __LINE__);

    $qtxt = "INSERT INTO stockmovement (vareid, beholdning)
    SELECT L.vare_id, coalesce(SUM(L.beholdning), 0) AS total_beholdning
    FROM lagerstatus L
    JOIN varer V ON L.vare_id = V.id
    JOIN grupper G ON V.gruppe = G.kodenr AND G.art = 'VG' AND G.fiscal_year = $regnaar AND G.box8 = 'on'
    WHERE V.lukket = '0'
    GROUP BY L.vare_id";
    db_modify($qtxt,__FILE__ . " linje " . __LINE__);


    $subject = "Lager status rapport ($x elementer)";

    $headers = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

    // Additional headers
    $headers .= 'From: info@saldi.dk' . "\r\n";
    $headers .= 'Reply-To: info@saldi.dk' . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    if(mail($to, $subject, $mailtext, $headers)) {
        print 'Email sent successfully!';
    } else {
        print 'Failed to send email.';
    }
}
/*
# Get all that have changed since last report
$qtxt = "SELECT L.vare_id, V.varenr, SUM(L.beholdning) AS total_beholdning, SUM(L.beholdning) - S.beholdning AS bevægelse, V.beskrivelse
FROM lagerstatus L
JOIN varer V ON L.vare_id = V.id
JOIN stockmovement S ON V.id = S.vareid
JOIN grupper G ON V.gruppe = G.kodenr AND G.art = 'VG' AND G.fiscal_year = $regnaar AND G.box8 = 'on'
WHERE V.lukket = '0'
GROUP BY L.vare_id, V.beskrivelse, V.varenr, S.beholdning
HAVING SUM(L.beholdning) < $limit AND SUM(L.beholdning) - S.beholdning != 0";

# Get all tha have beh less than 6
$qtxt = "SELECT L.vare_id, V.varenr, SUM(L.beholdning) AS total_beholdning, V.beskrivelse
FROM lagerstatus L
JOIN varer V ON L.vare_id = V.id
JOIN grupper G ON V.gruppe = G.kodenr AND G.art = 'VG' AND G.fiscal_year = $regnaar AND G.box8 = 'on'
WHERE V.lukket = '0'
GROUP BY L.vare_id, V.beskrivelse, V.varenr
HAVING SUM(L.beholdning) < $limit
LIMIT 10";

*/

?>
