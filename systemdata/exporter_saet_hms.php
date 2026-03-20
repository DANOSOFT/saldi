<?php
// --- systemdata/exporter_saet_hms.php ---
// Eksporterer varer fra "SÆT i HMS - SÆT.csv" til ny CSV med udvidede felter.

@session_start();
$s_id = session_id();
$title = "Eksporter SÆT HMS";
$header = 'nix';
$bg = 'nix';

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/stykliste.php");

// Læs varenumre fra input-CSV (kolonne 0, spring header over)
$csv_file = '../SÆT i HMS - SÆT.csv';
$varenumre = [];
if (($fh = fopen($csv_file, 'r')) !== false) {
    fgetcsv($fh); // spring header over
    while (($row = fgetcsv($fh)) !== false) {
        $vnr = trim($row[0]);
        if ($vnr !== '') $varenumre[] = $vnr;
    }
    fclose($fh);
} else {
    die("Kunne ikke åbne: $csv_file");
}

// Send CSV til browser
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="saet_hms_export.csv"');

$out = fopen('php://output', 'w');

// UTF-8 BOM så Excel åbner korrekt
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Varenummer', 'Beskrivelse', 'Salgspris', 'Kostpris', 'Vejl. pris',
    'Enhed', 'Nettovægt', 'Bruttovægt', 'L x B x H', 'Stykliste', 'Varegruppe'
], ';');

foreach ($varenumre as $vnr) {
    $vnr_esc = db_escape_string($vnr);
    $q = db_select("SELECT * FROM varer WHERE varenr = '$vnr_esc'", __FILE__ . " linje " . __LINE__);
    if ($r = db_fetch_array($q)) {
        $id = $r['id'];

        $kostpris = (float)$r['kostpris'];
        $retail   = (float)$r['retail_price'];

        // Tjek om varen har stykliste-poster
        $qs = db_select("SELECT COUNT(*) AS c FROM styklister WHERE indgaar_i = '$id'", __FILE__ . " linje " . __LINE__);
        $rs = db_fetch_array($qs);
        if ((int)$rs['c'] > 0) {
            // Auto-beregn kostpris fra stykliste hvis 0
            if ($kostpris == 0) {
                $kostpris = (float)stykliste($id, 0, '');
            }
            // Auto-beregn vejl.pris fra stykliste hvis 0
            if ($retail == 0) {
                $qr = db_select(
                    "SELECT v.retail_price, s.antal FROM styklister s JOIN varer v ON v.id = s.vare_id WHERE s.indgaar_i = '$id'",
                    __FILE__ . " linje " . __LINE__
                );
                $ret_sum = 0.0;
                while ($sr = db_fetch_array($qr)) {
                    $ret_sum += (float)$sr['retail_price'] * (float)$sr['antal'];
                }
                $retail = $ret_sum;
            }

            // Byg stykliste-streng: varenr(antal); ...
            $qp = db_select(
                "SELECT v.varenr, s.antal FROM styklister s JOIN varer v ON v.id = s.vare_id WHERE s.indgaar_i = '$id' ORDER BY s.posnr",
                __FILE__ . " linje " . __LINE__
            );
            $parts = [];
            while ($sp = db_fetch_array($qp)) {
                $parts[] = $sp['varenr'] . '(' . $sp['antal'] . ')';
            }
            $styk_str = implode('; ', $parts);
        } else {
            $styk_str = '';
        }

        // L x B x H (kun hvis mindst ét felt er udfyldt)
        $l = (float)$r['length'];
        $b = (float)$r['width'];
        $h = (float)$r['height'];
        $lbh = ($l || $b || $h) ? "$l x $b x $h" : '';

        fputcsv($out, [
            $r['varenr'],
            $r['beskrivelse'],
            dkdecimal($r['salgspris'], 2),
            dkdecimal($kostpris, 2),
            dkdecimal($retail, 2),
            $r['enhed'],
            $r['netweight'],
            $r['grossweight'],
            $lbh,
            $styk_str,
            $r['gruppe']
        ], ';');
    } else {
        // Varenummer ikke fundet i databasen
        fputcsv($out, [$vnr, 'IKKE FUNDET', '', '', '', '', '', '', '', '', ''], ';');
    }
}

fclose($out);
exit;
