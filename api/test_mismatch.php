<?php
@session_start();
$s_id=session_id();
include "../includes/connect.php";
include "../includes/online.php";

$dry_run = true; // Set to false to actually modify the database


// ─── HTML Output ────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<title>Fix Mismatched Orders</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f4f6f9; color: #333; padding: 30px; }
    h1 { font-size: 22px; margin-bottom: 6px; }
    .mode-badge { display: inline-block; padding: 4px 14px; border-radius: 4px; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
    .mode-dry   { background: #fff3cd; color: #856404; }
    .mode-live  { background: #f8d7da; color: #721c24; }
    .summary    { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 16px 20px; margin-bottom: 24px; font-size: 14px; }
    .summary strong { font-size: 28px; display: block; margin-bottom: 4px; }
    .order-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 16px; overflow: hidden; }
    .order-header { background: #3b82f6; color: #fff; padding: 10px 16px; font-size: 14px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
    .order-header .shop-tag { background: rgba(255,255,255,.2); padding: 2px 8px; border-radius: 3px; font-size: 12px; }
    .order-body  { padding: 16px; font-size: 13px; }
    .compare-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .compare-table th { text-align: left; padding: 6px 10px; background: #f8f9fa; border-bottom: 1px solid #eee; font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: .5px; }
    .compare-table td { padding: 6px 10px; border-bottom: 1px solid #f0f0f0; }
    .compare-table tr.mismatch td { background: #fff5f5; }
    .label-wrong   { color: #dc3545; font-weight: 600; }
    .label-correct { color: #198754; font-weight: 600; }
    .action-box { background: #f0f7ff; border-left: 3px solid #3b82f6; padding: 10px 14px; border-radius: 0 4px 4px 0; margin-top: 8px; font-size: 13px; }
    .action-box.success { background: #f0fdf4; border-left-color: #22c55e; }
    .action-box.error   { background: #fef2f2; border-left-color: #ef4444; }
    .action-box.create  { background: #fefce8; border-left-color: #eab308; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
    .badge-found   { background: #d1fae5; color: #065f46; }
    .badge-new     { background: #fef9c3; color: #854d0e; }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-error   { background: #fee2e2; color: #991b1b; }
    .footer { margin-top: 20px; padding: 16px 20px; background: #fff; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
</style>
</head>
<body>

<h1>🔧 Fix Mismatched Orders</h1>
<?php if ($dry_run): ?>
    <div class="mode-badge mode-dry">⚠ DRY RUN – No database changes will be made</div>
<?php else: ?>
    <div class="mode-badge mode-live">🔴 LIVE MODE – Database will be updated</div>
<?php endif; ?>

<?php
// Find orders with mismatched customer info
$qtxt = "
    SELECT 
        o.id AS order_id, 
        o.ordrenr,
        o.konto_id AS order_konto_id,
        o.kontonr AS order_kontonr,
        o.firmanavn AS order_navn, 
        o.email AS order_email, 
        o.phone AS order_phone,
        o.addr1, o.addr2, o.postnr, o.bynavn, o.land, o.cvrnr, o.ean, o.betalingsbet, o.betalingsdage, o.kontakt,
        o.shop_id, o.afd,
        a.id AS addr_id,
        a.kontonr AS addr_kontonr,
        a.firmanavn AS addr_navn,
        a.email AS addr_email,
        a.tlf AS addr_phone
    FROM ordrer o
    JOIN adresser a ON o.konto_id = a.id
    WHERE o.art = 'DO'
      AND o.shop_id IS NOT NULL 
      AND o.shop_id != 0
      AND o.fakturadate >= NOW() - INTERVAL '1 month'
      AND (
          (o.email IS NOT NULL AND o.email != '' AND a.email IS NOT NULL AND a.email != '' AND LOWER(TRIM(o.email)) != LOWER(TRIM(a.email)))
          OR 
          (o.phone IS NOT NULL AND o.phone != '' AND a.tlf IS NOT NULL AND a.tlf != '' AND REPLACE(o.phone, ' ', '') != REPLACE(a.tlf, ' ', ''))
      )
    ORDER BY o.id ASC
";

$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
if (!$q) {
    echo '<div class="action-box error">❌ Query failed</div>';
    die('</body></html>');
}

$fixed_count = 0;
$results = []; // collect all for summary

while($r = db_fetch_array($q)){
    $order_id = $r['order_id'];
    $ordrenr = $r['ordrenr'];
    $order_email = trim($r['order_email']);
    $order_phone = trim($r['order_phone']);
    $shop_id = $r['shop_id'];
    $afd = (int)$r['afd'];
    
    // ─── Find correct customer ─────────────────────────
    $correct_addr_id = null;
    $correct_kontonr = null;
    $correct_found = false;
    
    $clean_order_phone = db_escape_string(str_replace(' ', '', $order_phone));
    $clean_order_email = db_escape_string(strtolower($order_email));
    
    $search_q = "SELECT id, kontonr, firmanavn FROM adresser WHERE art = 'D' AND (";
    $search_conds = [];
    if (!empty($clean_order_phone)) {
        $search_conds[] = "REPLACE(tlf, ' ', '') = '$clean_order_phone'";
    }
    if (!empty($clean_order_email)) {
        $search_conds[] = "LOWER(TRIM(email)) = '$clean_order_email'";
    }
    
    $found_customer = null;
    if (count($search_conds) > 0) {
        $search_q .= implode(" OR ", $search_conds) . ") ORDER BY id DESC LIMIT 1";
        $sq = db_select($search_q, __FILE__ . " linje " . __LINE__);
        if ($sq && $sr = db_fetch_array($sq)) {
            $correct_addr_id = $sr['id'];
            $correct_kontonr = $sr['kontonr'];
            $correct_found = true;
            $found_customer = $sr['firmanavn'];
        }
    }
    
    $status_msgs = [];

    if (!$correct_found) {
        if (!$dry_run) {
            $num_tlf = preg_replace('/\D/', '', $order_phone);
            $new_kontonr = null;
            
            if (!empty($num_tlf)) {
                $qcheck = db_select("SELECT id FROM adresser WHERE art = 'D' AND kontonr = '$num_tlf'", __FILE__ . " linje " . __LINE__);
                if (!db_fetch_array($qcheck)) {
                    $new_kontonr = $num_tlf;
                }
            }
            
            if (!$new_kontonr) {
                $kq = db_select("SELECT kontonr FROM adresser WHERE art = 'D' ORDER BY kontonr", __FILE__ . " linje " . __LINE__);
                $ktonr = [];
                while ($kr = db_fetch_array($kq)) {
                    $ktonr[] = $kr['kontonr'];
                }
                $new_kontonr = 1000;
                while(in_array((string)$new_kontonr, $ktonr)) $new_kontonr++;
            }
            
            $gruppe = 1;
            $betalingsbet = db_escape_string($r['betalingsbet']);
            $betalingsdage = (int)$r['betalingsdage'];
            
            $iq = "INSERT INTO adresser (
                    kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, cvrnr, ean, email, tlf,
                    gruppe, art, betalingsbet, betalingsdage, kontakt
                ) VALUES (
                    '$new_kontonr', 
                    '".db_escape_string($r['order_navn'])."', 
                    '".db_escape_string($r['addr1'])."', 
                    '".db_escape_string($r['addr2'])."', 
                    '".db_escape_string($r['postnr'])."', 
                    '".db_escape_string($r['bynavn'])."', 
                    '".db_escape_string($r['land'])."', 
                    '".db_escape_string($r['cvrnr'])."', 
                    '".db_escape_string($r['ean'])."', 
                    '".db_escape_string($order_email)."', 
                    '".db_escape_string($order_phone)."', 
                    '$gruppe', 
                    'D', 
                    '$betalingsbet', 
                    '$betalingsdage', 
                    '".db_escape_string($r['kontakt'])."'
                ) RETURNING id";
            
            $insert_res = db_select($iq, __FILE__ . " linje " . __LINE__);
            if ($insert_res && $insert_row = db_fetch_array($insert_res)) {
                $correct_addr_id = $insert_row['id'];
                $correct_kontonr = $new_kontonr;
                $status_msgs[] = ['success', "Created new customer (ID: $correct_addr_id, Kontonr: $correct_kontonr)"];
            } else {
                $status_msgs[] = ['error', "Failed to create new customer"];
                // output card now, then continue
                $fixed_count++;
                outputCard($ordrenr, $order_id, $shop_id, $r, $order_email, $order_phone, null, false, $correct_kontonr, $correct_addr_id, $status_msgs, $dry_run);
                continue;
            }
        } else {
            $correct_kontonr = "[NEW]";
            $correct_addr_id = "[NEW]";
        }
    }
    
    if ($correct_addr_id || $dry_run) {
        $upd_order_query = "UPDATE ordrer SET konto_id = '$correct_addr_id', kontonr = '$correct_kontonr' WHERE id = '$order_id'";
        
        if (!$dry_run) {
            $upd_res = db_modify($upd_order_query, __FILE__ . " linje " . __LINE__);
            if ($upd_res) {
                $status_msgs[] = ['success', "Order updated → kontonr: $correct_kontonr"];
            } else {
                $status_msgs[] = ['error', "Failed to update order"];
            }
            
            $del_res = db_modify("DELETE FROM shop_adresser WHERE shop_id = '$shop_id' AND afd = '$afd'", __FILE__ . " linje " . __LINE__);
            if ($del_res) {
                $ins_res = db_modify("INSERT INTO shop_adresser (saldi_id, shop_id, afd) VALUES ('$correct_addr_id', '$shop_id', '$afd')", __FILE__ . " linje " . __LINE__);
                if ($ins_res) {
                    $status_msgs[] = ['success', "Shop mapping updated"];
                } else {
                    $status_msgs[] = ['error', "Failed to insert shop mapping"];
                }
            } else {
                $status_msgs[] = ['error', "Failed to delete old shop mapping"];
            }
        }
    }
    
    $fixed_count++;
    outputCard($ordrenr, $order_id, $shop_id, $r, $order_email, $order_phone, $found_customer, $correct_found, $correct_kontonr, $correct_addr_id, $status_msgs, $dry_run);
}

// ─── Summary Footer ────────────────────────────────────────
echo '<div class="footer">';
echo "<strong>Total mismatched orders found:</strong> $fixed_count";
if ($dry_run && $fixed_count > 0) {
    echo '<br><br>👆 Review the results above, then set <code>$dry_run = false</code> and run again to apply changes.';
} elseif (!$dry_run && $fixed_count > 0) {
    echo '<br><br>✅ All changes applied.';
} elseif ($fixed_count == 0) {
    echo '<br><br>🎉 No mismatched orders found — everything looks good!';
}
echo '</div>';
?>

</body>
</html>

<?php
// ─── Helper: Output a single order card ───────────────────
function outputCard($ordrenr, $order_id, $shop_id, $r, $order_email, $order_phone, $found_customer, $correct_found, $correct_kontonr, $correct_addr_id, $status_msgs, $dry_run) {
    $email_mismatch = (strtolower(trim($r['order_email'])) !== strtolower(trim($r['addr_email']))) && !empty($r['order_email']) && !empty($r['addr_email']);
    $phone_mismatch = (str_replace(' ', '', $r['order_phone']) !== str_replace(' ', '', $r['addr_phone'])) && !empty($r['order_phone']) && !empty($r['addr_phone']);
    ?>
    <div class="order-card">
        <div class="order-header">
            <span>Ordre #<?= htmlspecialchars($ordrenr) ?> &nbsp;·&nbsp; ID <?= htmlspecialchars($order_id) ?></span>
            <span class="shop-tag">Shop <?= htmlspecialchars($shop_id) ?></span>
        </div>
        <div class="order-body">
            <table class="compare-table">
                <tr>
                    <th style="width:120px"></th>
                    <th>Navn</th>
                    <th>Email</th>
                    <th>Telefon</th>
                    <th>Kontonr</th>
                </tr>
                <tr>
                    <td class="label-correct">Ordre (korrekt)</td>
                    <td><?= htmlspecialchars($r['order_navn']) ?></td>
                    <td><?= htmlspecialchars($order_email) ?></td>
                    <td><?= htmlspecialchars($order_phone) ?></td>
                    <td><?= htmlspecialchars($r['order_kontonr']) ?></td>
                </tr>
                <tr class="mismatch">
                    <td class="label-wrong">Konto (forkert)</td>
                    <td><?= htmlspecialchars($r['addr_navn']) ?></td>
                    <td><?= $email_mismatch ? '<strong style="color:#dc3545">' . htmlspecialchars($r['addr_email']) . '</strong>' : htmlspecialchars($r['addr_email']) ?></td>
                    <td><?= $phone_mismatch ? '<strong style="color:#dc3545">' . htmlspecialchars($r['addr_phone']) . '</strong>' : htmlspecialchars($r['addr_phone']) ?></td>
                    <td><?= htmlspecialchars($r['addr_kontonr']) ?></td>
                </tr>
            </table>

            <?php if ($correct_found): ?>
                <div class="action-box">
                    <span class="badge badge-found">✓ Fundet</span>
                    &nbsp; Korrekt kunde: <strong><?= htmlspecialchars($found_customer) ?></strong>
                    (ID: <?= htmlspecialchars($correct_addr_id) ?>, Kontonr: <?= htmlspecialchars($correct_kontonr) ?>)
                </div>
            <?php else: ?>
                <div class="action-box create">
                    <span class="badge badge-new">+ Ny kunde</span>
                    &nbsp; Kunde ikke fundet i adresser — <?= $dry_run ? 'vil blive oprettet' : 'opretter ny' ?>
                    <?php if ($correct_kontonr): ?>(Kontonr: <?= htmlspecialchars($correct_kontonr) ?>)<?php endif; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($status_msgs as $msg): ?>
                <div class="action-box <?= $msg[0] ?>" style="margin-top:6px">
                    <?= $msg[0] === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg[1]) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
?>
