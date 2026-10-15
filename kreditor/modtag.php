<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/modtag.php --- ver 5.0.0 --- 2026.10.06 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 2013.08.30 Fejl v. "interne shops" (Rotary) de der blev forsøgt kald til ikke eksisterende url.Søn 20130830
// 2013.10.01 Opdat_beholdning blev ikke åbnet v. webshop.
// 20141005 Div i forbindelse med omvendt betalingspligt, samt nogle generelle ændringer således at varereturnering nu bogføres
//	som negativt køb og ikke som salg.
// 20141005 Ændret + til - pga fejl ved netagiv modtagelse på samme lev ordre.
// 20150118 sætter kobsdate hvis ikke sat. Søg kobsdate.
// 20161022 PHR - tilretning iht flere afd pr lager. 20161022
// 20170123 PHR - Diverse i forhold til varianter
// 20181003 PHR - Lille udefinerbar rettelse
// 20221106 PHR - Various changes to fit php8 / MySQLi
// 20231025 PHR Added call to sync_shop_vare and log to stocklog.
// 20240626 PHR Added 'fiscal_year' in queries
// 20250207 PHR Corrected error in 'bogf_konto' as is used wrong account !
// 20260908 CDX/LH Lock creditor orders before receipt validation and batch changes (SST-765).
// 20260914 CL/SZ Block receipt of a batch/expiry-tracked line whose batch_due_date or
//                batch_batch_no is empty, mirroring the existing serial-number check; also
//                escaped batch_due_date and fixed a "0" batch-no being treated as empty in
//                every batch_kob insert/update built from these two fields (MB-36).
// 20260914 CL/SZ Added docstrings to reservation()/returnering() (CodeRabbit, PR #608).
// 20260917 SZ Split the batch-info hard-stop above: batch_due_date is required only when
//             item_has_due_date() is true, batch_no is required when that OR the item's
//             group has box9='on' - box9 governs lot/batch tracking independently of expiry
//             tracking, and item_has_due_date() itself used to query box9 before this PR,
//             so box9-only items already relied on this validation (MB-36).
// 20260917 SZ Guard the box9 lookup above against a missing grupper row (CodeRabbit,
//             PR #608) - degrades to "not tracked" instead of a PHP warning.

// 20261001 CDX/LH Delegate receipt writes to creditor_receive; caller retains transaction and alerts.

@session_start();
$s_id = session_id();

require __DIR__ . '/../includes/connect.php';
require __DIR__ . '/../includes/online.php';
require_once __DIR__ . '/../includes/std_func.php';
require_once __DIR__ . '/../includes/kreditor/creditorReceive.php';

$id = (int)ifset($_GET, 'id', 0);
if ($id <= 0) {
    print '<meta http-equiv="refresh" content="0;URL=ordreliste.php">';
    exit;
}
?>
<script language="JavaScript">
<!--
function fejltekst(tekst) {
    alert(tekst);
    window.location.replace("ordre.php?id=<?php echo $id ?>");
}
-->
</script>
<?php

// Serialize receipt eligibility with posting and order saves.
transaktion('begin');
try {
    creditor_receive_query('SELECT id FROM ordrer WHERE id=$1 FOR UPDATE', [$id]);
    $receipt = creditor_receive($id);
    if ($receipt['reason'] !== null) {
        transaktion('rollback');
        if ($receipt['reason'] === 'order_missing') {
            print '<meta http-equiv="refresh" content="0;URL=ordreliste.php">';
        } else {
            // Decode the page's fixed entity-bearing texts before encoding for JavaScript.
            // Product-group text contains database values and is encoded directly.
            $text = $receipt['reason'] === 'product_group_missing' ? $receipt['message']
                : html_entity_decode($receipt['message'], ENT_QUOTES, 'UTF-8');
            $message = substr(json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 1, -1);
            $action = ($receipt['reason'] === 'product_group_missing' ? 'javascript:alert' : 'fejltekst') . "('" . $message . "')";
            print '<BODY onLoad="' . htmlentities($action, ENT_COMPAT, 'UTF-8') . '">';
            if (in_array($receipt['reason'], ['product_group_missing','serial_numbers_not_selected'], true)) {
                print '<meta http-equiv="refresh" content="0;URL=ordre.php?id=' . $id . '">';
            }
        }
        exit;
    }
    transaktion('commit');
} catch (Throwable $error) {
    transaktion('rollback');
    throw $error;
}
print '<meta http-equiv="refresh" content="0;URL=ordre.php?id=' . $id . '">';
?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
