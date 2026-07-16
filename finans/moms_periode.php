<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/moms_periode.php --- patch 5.0.0 --- 2026-07-16 ---
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
// but WITHOUT ANY WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft.ApS
// ----------------------------------------------------------------------
// 20260716 MJ R5 – Aaben/luk individuelle maaneder. Lukket maaned blokkerer alle
//                  posteringsveje via PostgreSQL-trigger paa transaktioner-tabellen.
//                  Audit trail gemmes i moms_periode_luk-tabellen.

@session_start();
$s_id = session_id();
$title   = 'Momsperioder – Aaben/Luk';
$modulnr = 2;
$css     = '../css/standard.css';

include('../includes/var_def.php');
include('../includes/connect.php');
include('../includes/online.php');
include('../includes/topline_settings.php');
include('../includes/std_func.php');

$md = [];
$md[1]='januar'; $md[2]='februar'; $md[3]='marts';    $md[4]='april';
$md[5]='maj';    $md[6]='juni';    $md[7]='juli';      $md[8]='august';
$md[9]='september'; $md[10]='oktober'; $md[11]='november'; $md[12]='december';

// --- ensure schema exists ---
db_modify("CREATE TABLE IF NOT EXISTS moms_periode_luk (
    id               SERIAL PRIMARY KEY,
    kalender_aar     INTEGER NOT NULL,
    kalender_maaned  INTEGER NOT NULL CHECK (kalender_maaned BETWEEN 1 AND 12),
    status           VARCHAR(6) NOT NULL DEFAULT 'open' CHECK (status IN ('open','closed')),
    lukket_af        VARCHAR(100),
    lukket_dato      TIMESTAMP,
    aabnet_af        VARCHAR(100),
    aabnet_dato      TIMESTAMP,
    UNIQUE (kalender_aar, kalender_maaned)
)", __FILE__." linje ".__LINE__);

// Trigger function — blocks inserts/updates on transaktioner for closed months
db_modify("CREATE OR REPLACE FUNCTION check_moms_periode_luk()
RETURNS TRIGGER AS \$\$
BEGIN
    IF EXISTS (
        SELECT 1 FROM moms_periode_luk
        WHERE kalender_aar    = EXTRACT(YEAR  FROM NEW.transdate)
          AND kalender_maaned = EXTRACT(MONTH FROM NEW.transdate)
          AND status = 'closed'
    ) THEN
        RAISE EXCEPTION 'Perioden % er lukket for bogfoering – kontakt bogholder for at genaabne.',
            TO_CHAR(NEW.transdate, 'MM-YYYY');
    END IF;
    RETURN NEW;
END;
\$\$ LANGUAGE plpgsql", __FILE__." linje ".__LINE__);

db_modify("DROP TRIGGER IF EXISTS tr_check_moms_periode_luk ON transaktioner", __FILE__." linje ".__LINE__);
db_modify("CREATE TRIGGER tr_check_moms_periode_luk
    BEFORE INSERT OR UPDATE ON transaktioner
    FOR EACH ROW EXECUTE FUNCTION check_moms_periode_luk()", __FILE__." linje ".__LINE__);

// --- permission: require finans access (modulnr 2, bit >= 1) ---
$kan_aendre = ($rettigheder && substr($rettigheder, 2, 1) >= '1');

// --- handle toggle action ---
$msg = '';
if ($_POST && $kan_aendre) {
    $action = if_isset($_POST, NULL, 'action');
    $aar    = (int)if_isset($_POST, NULL, 'aar');
    $maaned = (int)if_isset($_POST, NULL, 'maaned');
    $now    = date('Y-m-d H:i:s');

    if ($aar >= 2000 && $aar <= 2100 && $maaned >= 1 && $maaned <= 12) {
        if ($action === 'luk') {
            db_modify("INSERT INTO moms_periode_luk (kalender_aar, kalender_maaned, status, lukket_af, lukket_dato)
                VALUES ($aar, $maaned, 'closed', '$brugernavn', '$now')
                ON CONFLICT (kalender_aar, kalender_maaned)
                DO UPDATE SET status='closed', lukket_af='$brugernavn', lukket_dato='$now'",
                __FILE__." linje ".__LINE__);
            $msg = "Perioden $maaned-$aar er nu lukket.";
        } elseif ($action === 'aaben') {
            db_modify("UPDATE moms_periode_luk
                SET status='open', aabnet_af='$brugernavn', aabnet_dato='$now'
                WHERE kalender_aar=$aar AND kalender_maaned=$maaned",
                __FILE__." linje ".__LINE__);
            $msg = "Perioden $maaned-$aar er nu aabnet.";
        }
    }
    // redirect to avoid form resubmit on refresh
    $redir_aar = (int)if_isset($_POST, date('Y'), 'vis_aar');
    header("Location: moms_periode.php?vis_aar=$redir_aar&msg=" . urlencode($msg));
    exit;
}

// --- determine which year to show ---
$vis_aar = (int)if_isset($_GET, date('Y'), 'vis_aar');
if ($vis_aar < 2000 || $vis_aar > 2100) $vis_aar = (int)date('Y');
if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

// Load current status for the year
$status_map = [];
$q = db_select("SELECT * FROM moms_periode_luk WHERE kalender_aar = $vis_aar ORDER BY kalender_maaned", __FILE__." linje ".__LINE__);
while ($r = db_fetch_array($q)) $status_map[$r['kalender_maaned']] = $r;

// --- page output ---
if ($menu == 'T') {
    include_once '../includes/top_header.php';
    include_once '../includes/top_menu.php';
    print "<div id='header'>";
    print "<div class='headerbtnLft headLink'><a href='kladdeliste.php'><i class='fa fa-close fa-lg'></i> Luk</a></div>";
    print "<div class='headerTxt'>Momsperioder – &Aring;ben/Luk</div>";
    print "<div class='headerbtnRght headLink'>&nbsp;</div>";
    print "</div>";
    print "<div class='content-noside' style='padding:16px;'>";
} else {
    print "<body>";
    print "<table width='100%' cellpadding='0' cellspacing='1px' border='0'><tr><td height='8'>";
    print "<table width='100%' align='center' border='0' cellspacing='3' cellpadding='0'><tbody>";
    print "<td width='10%'><a href='kladdeliste.php'>Luk</a></td>";
    print "<td width='80%' align='center'><b>Momsperioder – &Aring;ben/Luk</b></td>";
    print "<td width='10%'>&nbsp;</td>";
    print "</tbody></table></td></tr></table>";
    print "<div style='padding:16px;'>";
}

if ($msg) print "<div style='padding:8px 12px; margin-bottom:12px; background:#d4edda; color:#155724; border-radius:4px;'>$msg</div>";

if (!$kan_aendre) {
    print "<div style='padding:8px 12px; color:#c00;'>Du har ikke rettighed til at aendre periodestatuser.</div>";
}

// Year navigation
print "<div style='margin-bottom:16px;'>";
print "<a href='moms_periode.php?vis_aar=" . ($vis_aar - 1) . "'>&laquo; " . ($vis_aar - 1) . "</a> &nbsp;";
print "<b>$vis_aar</b> &nbsp;";
print "<a href='moms_periode.php?vis_aar=" . ($vis_aar + 1) . "'>" . ($vis_aar + 1) . " &raquo;</a>";
print "</div>";

print "<table border='0' cellspacing='1' cellpadding='6' style='border-collapse:collapse; min-width:600px;'>";
print "<thead><tr style='background:#eeeef0;'>";
print "<th align='left' style='padding:6px 12px;'>Maaned</th>";
print "<th align='center' style='padding:6px 12px;'>Status</th>";
print "<th align='left' style='padding:6px 12px;'>Lukket af / dato</th>";
print "<th align='left' style='padding:6px 12px;'>Aabnet af / dato</th>";
if ($kan_aendre) print "<th style='padding:6px 12px;'>&nbsp;</th>";
print "</tr></thead><tbody>";

for ($m = 1; $m <= 12; $m++) {
    $row    = $status_map[$m] ?? null;
    $closed = ($row && $row['status'] === 'closed');
    $bg     = $closed ? '#ffe0e0' : '#f0fff0';

    $status_txt  = $closed
        ? "<span style='color:#c00; font-weight:bold;'>&#x1F512; Lukket</span>"
        : "<span style='color:#090;'>&#x1F513; Aaben</span>";

    $lukket_info = ($row && $row['lukket_af'])
        ? htmlspecialchars($row['lukket_af']) . '<br><small>' . htmlspecialchars($row['lukket_dato']) . '</small>'
        : '–';
    $aabnet_info = ($row && $row['aabnet_af'])
        ? htmlspecialchars($row['aabnet_af']) . '<br><small>' . htmlspecialchars($row['aabnet_dato']) . '</small>'
        : '–';

    print "<tr style='background:$bg; border-bottom:1px solid #ddd;'>";
    print "<td style='padding:6px 12px;'><b>" . ucfirst($md[$m]) . " $vis_aar</b></td>";
    print "<td align='center' style='padding:6px 12px;'>$status_txt</td>";
    print "<td style='padding:6px 12px;'>$lukket_info</td>";
    print "<td style='padding:6px 12px;'>$aabnet_info</td>";

    if ($kan_aendre) {
        print "<td style='padding:6px 12px;'>";
        if ($closed) {
            print "<form method='POST' style='display:inline;'>";
            print "<input type='hidden' name='action' value='aaben'>";
            print "<input type='hidden' name='aar' value='$vis_aar'>";
            print "<input type='hidden' name='maaned' value='$m'>";
            print "<input type='hidden' name='vis_aar' value='$vis_aar'>";
            print "<button type='submit' onclick=\"return confirm('Aaben perioden " . ucfirst($md[$m]) . " $vis_aar?');\" "
                . "style='background:#28a745; color:#fff; border:none; padding:4px 10px; cursor:pointer; border-radius:3px;'>"
                . "&Aring;ben</button></form>";
        } else {
            print "<form method='POST' style='display:inline;'>";
            print "<input type='hidden' name='action' value='luk'>";
            print "<input type='hidden' name='aar' value='$vis_aar'>";
            print "<input type='hidden' name='maaned' value='$m'>";
            print "<input type='hidden' name='vis_aar' value='$vis_aar'>";
            print "<button type='submit' onclick=\"return confirm('Luk perioden " . ucfirst($md[$m]) . " $vis_aar for bogfoering?');\" "
                . "style='background:#dc3545; color:#fff; border:none; padding:4px 10px; cursor:pointer; border-radius:3px;'>"
                . "Luk</button></form>";
        }
        print "</td>";
    }
    print "</tr>\n";
}

print "</tbody></table>";
print "<p style='margin-top:16px; color:#666; font-size:0.9em;'>"
    . "Lukkede maaneder blokerer <em>alle</em> posteringsveje via databasetrigger. "
    . "En genaabnelse tillader bogfoering igen og logges i ovenstaaende tabel."
    . "</p>";

print "</div>";

if ($menu == 'T') {
    include_once '../includes/topmenu/footer.php';
} else {
    include_once '../includes/oldDesign/footer.php';
}
?>
