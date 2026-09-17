<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------lager/productCardIncludes/stockLog.php---------lap 4.1.0---2026-02-26	-----
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
// Copyright (c) 2020-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 2020-07-22 MMK Inintial setup

@session_start();
$s_id = session_id();

$css = "../../css/standard.css?v=20";

include("../../includes/std_func.php");
include("../../includes/connect.php");
include("../../includes/online.php");
include("../../includes/stdFunc/dkDecimal.php");

$id = if_isset($_GET['id']) * 1;

// Item info
$r = db_fetch_array(db_select(
    "SELECT varenr, beskrivelse FROM varer WHERE id='$id'",
    __FILE__ . " linje " . __LINE__
));
$varenr  = htmlspecialchars($r['varenr']);
$beskriv = htmlspecialchars($r['beskrivelse']);

// Warehouse name lookup
$lagerNavn = [];
$q = db_select("SELECT kodenr, beskrivelse FROM grupper WHERE art='LG' ORDER BY kodenr", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $lagerNavn[$r['kodenr']] = $r['beskrivelse'];
}

// Unified transaction query (stocklog + batch_kob + batch_salg + regulering)
$qtxt = "

-- 1) Manual stock adjustments entered via the product card.
--    logtime is stored as a Unix timestamp (text), reason and initials are captured.
SELECT
    to_timestamp(sl.logtime::bigint)    AS sort_ts,
    'Manuel'                            AS type,
    NULL::integer                       AS lager,
    sl.correction                       AS antal,
    NULL::integer                       AS ordre_id,
    NULL::text                          AS ordrenr,
    sl.reason                           AS reference,
    sl.username                         AS bruger,
    sl.initials                         AS initialer,
    NULL::boolean                       AS bogfort
FROM stocklog sl
WHERE sl.item_id = $id

UNION ALL

-- 2) Incoming stock from purchase orders (batch_kob).
--    Lines belonging to the same order+warehouse are summed into one row.
--    ordre_id = 0 is treated as no order (kept as individual rows via the CASE key).
--    Rows are suppressed when a matching stocklog entry exists within 10 seconds
--    and with the same quantity as these are auto-created entries from manual adjustments.
SELECT
    COALESCE(MAX(bk.modtime), MAX(bk.kobsdate)::timestamp)  AS sort_ts,
    'Indkøb'                                                 AS type,
    bk.lager,
    SUM(bk.antal)                                            AS antal,
    NULLIF(MAX(bk.ordre_id), 0)                              AS ordre_id,   -- treat 0 as no order
    MAX(o.ordrenr)::text                                     AS ordrenr,
    NULL::text                                               AS reference,
    NULL::text                                               AS bruger,
    o.ref::text                                              AS initialer,
    NULL::boolean                                            AS bogfort
FROM batch_kob bk
LEFT JOIN ordrer o ON o.id = bk.ordre_id
WHERE bk.vare_id = $id
AND NOT EXISTS (
    -- Suppress batch_kob rows that are the FIFO side-effect of a manual stocklog adjustment
    SELECT 1 FROM stocklog sl
    WHERE sl.item_id = $id
      AND sl.correction = bk.antal
      AND ABS(EXTRACT(EPOCH FROM (
          COALESCE(bk.modtime, bk.kobsdate::timestamp) - to_timestamp(sl.logtime::bigint)
      ))) < 10
)
GROUP BY
    -- Group real orders by ordre_id: rows without an order each get a unique key
    CASE WHEN bk.ordre_id IS NULL OR bk.ordre_id = 0 THEN 'id_' || bk.id::text
         ELSE bk.ordre_id::text END,
    bk.lager,
    o.ref
HAVING SUM(bk.antal) <> 0  -- drop groups that net to zero (i assume they have been removed from a delivered order)

UNION ALL

-- 3) Outgoing stock from sales orders (batch_salg).
--    antal is negated so outgoing stock shows as a negative number.
--    Same grouping logic as batch_kob.
SELECT
    COALESCE(MAX(bs.modtime), MAX(bs.salgsdate)::timestamp) AS sort_ts,
    'Salg'                                                   AS type,
    bs.lager,
    -SUM(bs.antal)                                           AS antal,      -- negate: outgoing is negative
    NULLIF(MAX(bs.ordre_id), 0)                              AS ordre_id,
    MAX(o.ordrenr)::text                                     AS ordrenr,
    NULL::text                                               AS reference,
    NULL::text                                               AS bruger,
    o.ref::text                                              AS initialer,
    NULL::boolean                                            AS bogfort
FROM batch_salg bs
LEFT JOIN ordrer o ON o.id = bs.ordre_id
WHERE bs.vare_id = $id
GROUP BY
    CASE WHEN bs.ordre_id IS NULL OR bs.ordre_id = 0 THEN 'id_' || bs.id::text
         ELSE bs.ordre_id::text END,
    bs.lager,
    o.ref
HAVING SUM(bs.antal) <> 0

UNION ALL

-- 4) Physical inventory counts (regulering).
--    tidspkt stores the timestamp as YYYYMMDDHHMMSS text: falls back to transdate.
--    antal = optalt - beholdning (counted minus expected = the adjustment made).
--    bogfort = false means the count has not yet been posted to the ledger.
SELECT
    COALESCE(
        to_timestamp(r.tidspkt, 'YYYYMMDDHH24MISS'),
        r.transdate::timestamp
    )                                                AS sort_ts,
    'Optælling'                                      AS type,
    r.lager,
    (r.optalt - r.beholdning)                        AS antal,   -- delta: positive = more than expected
    NULL::integer                                    AS ordre_id,
    NULL::text                                       AS ordrenr,
    NULL::text                                       AS reference,
    r.bogfort_af                                     AS bruger,
    NULL::text                                       AS initialer,
    r.bogfort                                        AS bogfort
FROM regulering r
WHERE r.vare_id = $id

ORDER BY sort_ts DESC NULLS LAST
";

$q    = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$rows = array();
while ($r = db_fetch_array($q)) {
    $rows[] = $r;
}

// Running balance: start from current stock and work backwards (rows are newest-first)
$balR    = db_fetch_array(db_select(
    "SELECT COALESCE(SUM(beholdning), 0) AS beh FROM lagerstatus WHERE vare_id = $id",
    __FILE__ . " linje " . __LINE__
));
$balance = $balR['beh'] * 1;
foreach ($rows as &$row) {
    $row['balance'] = $balance;
    // Unposted inventory counts haven't moved actual stock — exclude from balance
    $isUnposted = $row['type'] === 'Optælling' && $row['bogfort'] !== 't';
    if (!$isUnposted) {
        $balance -= $row['antal'] * 1;
    }
}
unset($row);

// Type badge styles
$typeBadge = [
    'Manuel'    => "background:#888; color:#fff; border-radius:3px; padding:1px 6px; font-size:0.85em;",
    'Indkøb'    => "background:#27ae60; color:#fff; border-radius:3px; padding:1px 6px; font-size:0.85em;",
    'Salg'      => "background:#c0392b; color:#fff; border-radius:3px; padding:1px 6px; font-size:0.85em;",
    'Optælling' => "background:#2980b9; color:#fff; border-radius:3px; padding:1px 6px; font-size:0.85em;",
];
?>

<div style="padding: 10px;">
    <table width="100%" border="0" cellspacing="2" cellpadding="2">
        <tr>
            <td>
                <a href="../varekort.php?id=<?php print $id; ?>">
                    <input type="button" style="width: 150px;" class="button blue medium" value="&#8592; Luk"></input>
                </a>
            </td>
            <td><b>Lagerlog &mdash; <?php print "$varenr $beskriv"; ?></b></td>
            <td align="right" style="color:#666; font-size:0.9em;">
                <?php print count($rows); ?> poster
            </td>
        </tr>
    </table>
    <hr>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <thead>
            <tr bgcolor="<?php print $bgcolor; ?>">
                <th align="left"  style="padding:4px 6px;">Tidspunkt</th>
                <th align="left"  style="padding:4px 6px;">Type</th>
                <th align="left"  style="padding:4px 6px;">Lager</th>
                <th align="left"  style="padding:4px 6px;">Reference</th>
                <th align="left"  style="padding:4px 6px;">Bruger</th>
                <th align="left"  style="padding:4px 6px;">Initialer</th>
                <th align="right" style="padding:4px 6px;">Antal</th>
                <th align="right" style="padding:4px 6px;">Beholdning</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $linjebg = $bgcolor;
        foreach ($rows as $r) {
            ($linjebg != $bgcolor) ? $linjebg = $bgcolor : $linjebg = $bgcolor5;

            $type    = $r['type'];
            $dato    = $r['sort_ts'] ? date("d-m-Y H:i", strtotime($r['sort_ts'])) : '';
            $antal   = $r['antal'] * 1;
            $corrStr = ($antal >= 0 ? '+' : '') . dkdecimal($antal);
            $corrCss = ($antal >= 0)
                ? "color:#090; font-weight:bold;"
                : "color:#900; font-weight:bold;";

            // Dim unposted regulering rows
            if ($type === 'Optælling' && $r['bogfort'] === 'f') {
                $corrCss = "color:#999; font-style:italic;";
            }

            // Warehouse label
            $lagerLabel = '';
            if ($r['lager']) {
                $lagerLabel = isset($lagerNavn[$r['lager']]) ? htmlspecialchars($lagerNavn[$r['lager']]) : $r['lager'];
            }

            // Reference cell
            $ref = '';
            if ($type === 'Manuel') {
                $ref = nl2br(htmlspecialchars($r['reference']));
            } elseif (($type === 'Indkøb' || $type === 'Salg') && $r['ordrenr']) {
                $art  = ($type === 'Indkøb') ? 'kreditor' : 'debitor';
                $href = "../../$art/ordre.php?id=" . (int)$r['ordre_id'] . "&ro=1&returside=../includes/luk.php";
                $ref  = "<a href='$href' target='_blank'>Ordre #" . htmlspecialchars($r['ordrenr']) . "</a>";
            } elseif ($type === 'Optælling') {
                $bogfortLabel = ($r['bogfort'] === 't' || $r['bogfort'] === true)
                    ? ''
                    : " <em style='color:#999;'>(ikke bogført)</em>";
                $ref = htmlspecialchars($r['bruger'] ? 'Bogført af: ' . $r['bruger'] : '') . $bogfortLabel;
            }

            // User / initials
            $bruger    = ($type !== 'Optælling') ? htmlspecialchars($r['bruger'])    : '';
            $initialer = htmlspecialchars($r['initialer']);

            $badge = isset($typeBadge[$type]) ? "<span style='{$typeBadge[$type]}'>$type</span>" : $type;

            print "<tr bgcolor='$linjebg' class='table-row'>";
            print "<td style='padding:4px 6px; white-space:nowrap;'>$dato</td>";
            print "<td style='padding:4px 6px;'>$badge</td>";
            print "<td style='padding:4px 6px;'>$lagerLabel</td>";
            print "<td style='padding:4px 6px;'>$ref</td>";
            print "<td style='padding:4px 6px;'>$bruger</td>";
            print "<td style='padding:4px 6px;'>$initialer</td>";
            print "<td align='right' style='padding:4px 6px; $corrCss'>$corrStr</td>";
            $balStr = dkdecimal($r['balance']);
            print "<td align='right' style='padding:4px 6px; font-weight:bold;'>$balStr</td>";
            print "</tr>";
        }
        if (empty($rows)) {
            print "<tr><td colspan='8' align='center' style='padding:20px;'>";
            print "<i>Ingen transaktioner fundet for denne vare.</i>";
            print "</td></tr>";
        }
        ?>
        </tbody>
    </table>
</div>

<style>
    .table-row:hover {
        outline: 2px #000 solid;
    }
</style>

</html>
