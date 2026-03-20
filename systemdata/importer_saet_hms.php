<?php
// --- systemdata/importer_saet_hms.php ---
// Importerer varer fra "SÆT i HMS - SÆT.csv" til varer-tabellen.

@session_start();
$s_id = session_id();
$title = "Importer SÆT HMS";
$css   = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$csv_file = '../SÆT i HMS - SÆT.csv';

// Konverter dansk decimalformat til float: "2.799,20" -> 2799.20
function parse_danish_number($s) {
    $s = str_replace('.', '', trim($s));
    $s = str_replace(',', '.', $s);
    return (float)$s;
}

// Læs CSV
$rows = [];
if (($fh = fopen($csv_file, 'r')) !== false) {
    // Fjern evt. UTF-8 BOM
    $bom = fread($fh, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($fh);
    fgetcsv($fh, 0, ';'); // spring header over
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (count($row) >= 2 && trim($row[0]) !== '') {
            $rows[] = $row;
        }
    }
    fclose($fh);
} else {
    die("<p style='color:red'>Kunne ikke åbne: " . htmlspecialchars($csv_file) . "</p>");
}

$mode        = isset($_POST['mode']) ? $_POST['mode'] : 'all';
$do_import   = isset($_POST['import']);
$imp_antal   = 0;
$upd_antal   = 0;
$skip_antal  = 0;
$styk_skip   = [];

if ($do_import) {
    foreach ($rows as $row) {
        $varenr      = db_escape_string(trim($row[0]));
        $beskrivelse = db_escape_string(trim($row[1] ?? ''));
        $salgspris   = parse_danish_number($row[2] ?? 0);
        $kostpris    = parse_danish_number($row[3] ?? 0);
        $retail      = parse_danish_number($row[4] ?? 0);
        $enhed       = db_escape_string(trim($row[5] ?? ''));
        $netweight   = parse_danish_number($row[6] ?? 0);
        $grossweight = parse_danish_number($row[7] ?? 0);
        $gruppe      = (int)trim($row[10] ?? 0);

        // Parse L x B x H
        $lbh_str = trim($row[8] ?? '');
        $length = $width = $height = 0;
        if ($lbh_str !== '') {
            $lbh_parts = array_map('trim', explode('x', $lbh_str));
            $length = (float)($lbh_parts[0] ?? 0);
            $width  = (float)($lbh_parts[1] ?? 0);
            $height = (float)($lbh_parts[2] ?? 0);
        }

        // Tjek om varen allerede eksisterer
        $q = db_select("SELECT id FROM varer WHERE varenr = '$varenr'", __FILE__ . " linje " . __LINE__);
        $r = db_fetch_array($q);
        $vare_id = $r ? $r['id'] : false;

        if ($vare_id && ($mode === 'only_new')) {
            $skip_antal++;
            continue;
        }
        if (!$vare_id && ($mode === 'only_existing')) {
            $skip_antal++;
            continue;
        }

        if ($vare_id) {
            // Opdater eksisterende
            $qtxt = "UPDATE varer SET
                beskrivelse='$beskrivelse', salgspris='$salgspris', kostpris='$kostpris',
                retail_price='$retail', enhed='$enhed', netweight='$netweight',
                grossweight='$grossweight', length='$length', width='$width',
                height='$height', gruppe='$gruppe'
                WHERE id='$vare_id'";
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            $upd_antal++;
        } else {
            // Indsæt ny
            $qtxt = "INSERT INTO varer(varenr, beskrivelse, salgspris, kostpris, retail_price,
                enhed, netweight, grossweight, length, width, height, gruppe, lukket)
                VALUES ('$varenr','$beskrivelse','$salgspris','$kostpris','$retail',
                '$enhed','$netweight','$grossweight','$length','$width','$height','$gruppe','')";
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            // Hent ny id
            $r2 = db_fetch_array(db_select("SELECT id FROM varer WHERE varenr='$varenr'", __FILE__ . " linje " . __LINE__));
            $vare_id = $r2 ? $r2['id'] : false;
            $imp_antal++;
        }

        // Stykliste: parse "varenr(antal); varenr(antal)"
        $styk_str = trim($row[9] ?? '');
        if ($vare_id && $styk_str !== '') {
            // Slet eksisterende stykliste-poster for denne vare
            db_modify("DELETE FROM styklister WHERE indgaar_i = '$vare_id'", __FILE__ . " linje " . __LINE__);
            $styk_entries = array_filter(array_map('trim', explode(';', $styk_str)));
            $posnr = 1;
            foreach ($styk_entries as $entry) {
                // Match "varenr(antal)"
                if (preg_match('/^(.+)\(([0-9.,]+)\)$/', $entry, $m)) {
                    $sub_vnr  = db_escape_string(trim($m[1]));
                    $sub_ant  = parse_danish_number($m[2]);
                    $sq = db_fetch_array(db_select("SELECT id FROM varer WHERE varenr='$sub_vnr'", __FILE__ . " linje " . __LINE__));
                    if ($sq) {
                        $sub_id = $sq['id'];
                        db_modify("INSERT INTO styklister(vare_id, indgaar_i, antal, posnr) VALUES ('$sub_id','$vare_id','$sub_ant','$posnr')", __FILE__ . " linje " . __LINE__);
                        $posnr++;
                    } else {
                        $styk_skip[] = htmlspecialchars($row[0]) . ": delvare '$sub_vnr' ikke fundet";
                    }
                }
            }
        }
    }
}
?>
<html>
<head>
<title><?= htmlspecialchars($title) ?></title>
<link rel="stylesheet" type="text/css" href="../css/standard.css">
<style>
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; margin: 10px; }
  table { border-collapse: collapse; width: 100%; }
  th { background: #114691; color: #fff; padding: 4px 8px; text-align: left; }
  td { padding: 3px 8px; border-bottom: 1px solid #ddd; }
  tr:nth-child(even) td { background: #f4f4f8; }
  .ny     { color: #007700; font-weight: bold; }
  .findes { color: #cc6600; }
  .result { background: #e8f4e8; border: 1px solid #aac; padding: 10px; margin-bottom: 12px; }
  .warn   { color: #cc0000; font-size: 9pt; }
  .btn    { background:#114691; color:#fff; border:none; padding:6px 18px; cursor:pointer; font-size:10pt; }
</style>
</head>
<body>

<h2>Importer SÆT HMS varer</h2>
<p>Kilde: <code><?= htmlspecialchars($csv_file) ?></code> &mdash; <?= count($rows) ?> rækker fundet</p>

<?php if ($do_import): ?>
<div class="result">
  <b>Import gennemført:</b>
  <?= $imp_antal ?> indsat &nbsp;|&nbsp;
  <?= $upd_antal ?> opdateret &nbsp;|&nbsp;
  <?= $skip_antal ?> sprunget over
</div>
<?php if ($styk_skip): ?>
<p class="warn"><b>Stykliste-advarsler (delvarer ikke fundet i DB):</b><br>
<?= implode('<br>', $styk_skip) ?></p>
<?php endif; ?>
<?php endif; ?>

<form method="post">
<table>
<thead>
  <tr>
    <th>Varenummer</th>
    <th>Beskrivelse</th>
    <th>Salgspris</th>
    <th>Kostpris</th>
    <th>Vejl. pris</th>
    <th>Enhed</th>
    <th>Stykliste</th>
    <th>Status</th>
  </tr>
</thead>
<tbody>
<?php foreach ($rows as $row):
    $vnr_esc = db_escape_string(trim($row[0]));
    $exists  = db_fetch_array(db_select("SELECT id FROM varer WHERE varenr='$vnr_esc'", __FILE__ . " linje " . __LINE__));
?>
<tr>
  <td><?= htmlspecialchars($row[0]) ?></td>
  <td><?= htmlspecialchars($row[1] ?? '') ?></td>
  <td><?= htmlspecialchars($row[2] ?? '') ?></td>
  <td><?= htmlspecialchars($row[3] ?? '') ?></td>
  <td><?= htmlspecialchars($row[4] ?? '') ?></td>
  <td><?= htmlspecialchars($row[5] ?? '') ?></td>
  <td style="font-size:8pt"><?= htmlspecialchars($row[9] ?? '') ?></td>
  <td><?php if ($exists): ?><span class="findes">Findes allerede</span><?php else: ?><span class="ny">Ny</span><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<br>
<b>Importtilstand:</b><br>
<label><input type="radio" name="mode" value="all" <?= $mode==='all'?'checked':'' ?>> Indsæt nye + opdater eksisterende</label><br>
<label><input type="radio" name="mode" value="only_new" <?= $mode==='only_new'?'checked':'' ?>> Kun indsæt nye (spring eksisterende over)</label><br>
<label><input type="radio" name="mode" value="only_existing" <?= $mode==='only_existing'?'checked':'' ?>> Kun opdater eksisterende (spring nye over)</label><br>
<br>
<input type="submit" name="import" class="btn" value="Udfør import">
</form>

</body>
</html>
