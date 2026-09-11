<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/shop_order_reconcile.php --- 2026.09.11
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260911 CL/Sawaneh Created: offline replay of the shop-order arithmetic in
//                     api/rest_api.php (insert_shop_orderline -> opret_ordrelinje
//                     -> fakturer_ordre) for one raw request set, plus a scanner
//                     for "Error in amount" rejections in rest_api.log. Reports
//                     the line-by-line reconciliation and which shop-side
//                     rounding rule reproduces the shop totals (SST-768/JOB-117).
//                     Reads nothing from the database and changes nothing.

require_once __DIR__ . '/../includes/std_func.php';

const RECONCILE_TOLERANCE = 0.01;
const REDACTED_PARAMS = ['key', 'password', 'pass', 'token'];

/**
 * Parse one raw request (full URL or bare query string) into its parameters.
 *
 * @param string $raw
 * @return array<string,string> parameter => value, secrets replaced by '***'
 */
function parse_request($raw)
{
    $raw = trim($raw);
    if ($raw === '' || $raw[0] === '#') {
        return [];
    }
    $query = (strpos($raw, '?') !== false) ? substr($raw, strpos($raw, '?') + 1) : $raw;
    parse_str($query, $params);
    foreach (REDACTED_PARAMS as $secret) {
        if (isset($params[$secret])) {
            $params[$secret] = '***';
        }
    }
    return $params;
}

/**
 * Currency rates come from grupper (art VK) and may carry a Danish decimal
 * comma; rest_api.php runs those through usdecimal(). Request values never are.
 *
 * @param string|float $value
 * @return float
 */
function rate_value($value)
{
    $value = (string)$value;
    return (float)(strpos($value, ',') !== false ? usdecimal($value) : $value);
}

/**
 * Mirror of the price path in api/rest_api.php insert_shop_orderline() and
 * includes/ordrefunc.php opret_ordrelinje(): shop price -> DKK -> order
 * currency, then stored through PHP's default float-to-string conversion.
 *
 * @param float $shopPrice
 * @param float $valutakurs
 * @return float the value that ends up in ordrelinjer.pris
 */
function stored_line_price($shopPrice, $valutakurs)
{
    $pris = (float)$shopPrice;
    if ($valutakurs && $valutakurs != 100) {
        $pris = $pris * $valutakurs / 100;
        $pris = $pris * 100 / $valutakurs;
    }
    return (float)(string)$pris;
}

/**
 * Mirror of the settlement check in api/rest_api.php fakturer_ordre().
 *
 * @param array<int,array{antal:float,pris:float,rabat:float,momssats:float}> $lines
 * @return array{varesum:float,varemoms:float,lines:array<int,array{linjepris:float,linjemoms:float}>}
 */
function saldi_totals($lines)
{
    $varesum = $varemoms = 0;
    $detail = [];
    foreach ($lines as $l) {
        $linjepris = $l['antal'] * ($l['pris'] - $l['pris'] * $l['rabat'] / 100);
        $linjemoms = $linjepris * $l['momssats'] / 100;
        $varesum += afrund($linjepris, 3);
        $varemoms += afrund($linjemoms, 3);
        $detail[] = ['linjepris' => $linjepris, 'linjemoms' => $linjemoms];
    }
    return ['varesum' => $varesum, 'varemoms' => $varemoms, 'lines' => $detail];
}

/**
 * Candidate shop-side rounding rules, evaluated on the shop's own line data.
 *
 * @param array<int,array{antal:float,pris:float,rabat:float,momssats:float}> $lines
 * @param float $momssats order-level VAT rate used by rule B
 * @return array<string,array{net:float,vat:float}> rule name => totals
 */
function shop_hypotheses($lines, $momssats)
{
    $rate0 = $momssats / 100;
    $exactNet = $exactVat = 0;
    $net2 = $vat2 = 0;
    $gross2 = $net2b = 0;
    foreach ($lines as $l) {
        $net = $l['antal'] * ($l['pris'] - $l['pris'] * $l['rabat'] / 100);
        $rate = $l['momssats'] / 100;
        $exactNet += $net;
        $exactVat += $net * $rate;
        $net2 += round($net, 2);
        $vat2 += round(round($net, 2) * $rate, 2);
        $lineGross = round($l['pris'] * (1 + $rate), 2) * $l['antal'];
        $lineGross -= $lineGross * $l['rabat'] / 100;
        $gross2 += round($lineGross, 2);
        $net2b += round($lineGross / (1 + $rate), 2);
    }
    $grossWhole = round($exactNet + $exactVat, 0);
    return [
        'A exact, no rounding'                       => ['net' => $exactNet, 'vat' => $exactVat],
        'B lines net rounded 2, VAT on total'        => ['net' => $net2, 'vat' => round($net2 * $rate0, 2)],
        'C lines net rounded 2, VAT per line 2'      => ['net' => $net2, 'vat' => $vat2],
        'D gross per line 2, net = gross/(1+rate)'   => ['net' => $net2b, 'vat' => $gross2 - $net2b],
        'E gross total rounded to whole unit'        => ['net' => round($exactNet, 2), 'vat' => $grossWhole - round($exactNet, 2)],
        'F VAT rounded up to whole unit'             => ['net' => round($exactNet, 2), 'vat' => ceil($exactVat)],
    ];
}

/**
 * Replay one order: the insert_shop_order request plus its insert_shop_orderline requests.
 *
 * @param array<int,array<string,string>> $requests parsed requests in call order
 * @param array{momssats?:float,valutakurs?:float} $overrides values Saldi takes from its own
 *        settings rather than the request (customer-group VAT rate, currency rate)
 * @return string report
 */
function reconcile($requests, $overrides = [])
{
    $order = null;
    $lines = [];
    foreach ($requests as $p) {
        $action = $p['action'] ?? '';
        if ($action === 'insert_shop_order') {
            $order = $p;
        } elseif ($action === 'insert_shop_orderline') {
            $lines[] = $p;
        }
    }
    if (!$order) {
        return "No insert_shop_order request found.\n";
    }
    $valuta = $order['valuta'] ?: 'DKK';
    $valutakurs = ($valuta === 'DKK') ? 100 : (float)($overrides['valutakurs'] ?? rate_value($order['valutakurs'] ?? 100));
    $momssats = (float)($overrides['momssats'] ?? ($order['momssats'] ?? 25));
    $nettosum = (float)($order['nettosum'] ?? 0);
    $momssum = (float)($order['momssum'] ?? 0);
    $betalt = isset($order['ekstra2']) ? (float)$order['ekstra2'] : null;

    $out = [];
    $out[] = sprintf("Shop order %s  currency %s  rate %s  order VAT rate %s%%", $order['shop_ordre_id'] ?? '?', $valuta, $valutakurs, $momssats);
    $out[] = sprintf("Shop totals: nettosum %.4f  momssum %.4f  gross %.4f%s", $nettosum, $momssum, $nettosum + $momssum, $betalt !== null ? sprintf("  ekstra2 (paid) %.4f", $betalt) : '');
    $out[] = str_repeat('-', 100);
    $out[] = sprintf("%-4s %-14s %10s %12s %12s %8s %7s %14s %12s", '#', 'varenr', 'antal', 'shop pris', 'saldi pris', 'rabat', 'moms%', 'linjepris', 'linjemoms');

    $saldiLines = [];
    $flags = [];
    foreach ($lines as $i => $l) {
        $antal = (float)($l['antal'] ?? 0);
        $shopPris = (float)($l['pris'] ?? 0);
        $rabat = (float)($l['rabat'] ?? 0);
        $momsfri = !empty($l['momsfri']);
        $stored = stored_line_price($shopPris, $valutakurs);
        $lineRate = $momsfri ? 0 : $momssats;
        $saldiLines[] = ['antal' => $antal, 'pris' => $stored, 'rabat' => $rabat, 'momssats' => $lineRate];
        if ($stored != $shopPris) {
            $flags[] = sprintf("line %d: price changed by the currency round trip (%s -> %s)", $i + 1, $shopPris, $stored);
        }
        if ($rabat == 0) {
            $flags[] = sprintf("line %d: rabat=0 sent, Saldi may apply its own customer/item discount table (rabat) instead", $i + 1);
        }
        if (round($shopPris, 3) != $shopPris) {
            $flags[] = sprintf("line %d: shop price has more than 3 decimals (%s)", $i + 1, $shopPris);
        }
    }
    $saldi = saldi_totals($saldiLines);
    foreach ($saldiLines as $i => $l) {
        $out[] = sprintf("%-4d %-14s %10s %12s %12s %8s %7s %14.4f %12.4f", $i + 1, $lines[$i]['varenr'] ?? '', $l['antal'], $lines[$i]['pris'] ?? '', $l['pris'], $l['rabat'], $l['momssats'], $saldi['lines'][$i]['linjepris'], $saldi['lines'][$i]['linjemoms']);
    }
    $out[] = str_repeat('-', 100);
    $diffNet = $nettosum - $saldi['varesum'];
    $diffVat = $momssum - $saldi['varemoms'];
    $out[] = sprintf("Saldi:  varesum %.4f  varemoms %.4f  gross %.4f", $saldi['varesum'], $saldi['varemoms'], $saldi['varesum'] + $saldi['varemoms']);
    $out[] = sprintf("Diff (shop - Saldi):  net %+.4f  VAT %+.4f  (tolerance %.2f)", $diffNet, $diffVat, RECONCILE_TOLERANCE);
    $rejected = abs($diffNet) > RECONCILE_TOLERANCE || abs($diffVat) > RECONCILE_TOLERANCE;
    $out[] = $rejected
        ? sprintf("RESULT: REJECTED by fakturer_ordre: Error in amount (%s+%s) vs. item amount (%s+%s)", $nettosum, $momssum, $saldi['varesum'], $saldi['varemoms'])
        : "RESULT: accepted by the amount check";
    if ($betalt !== null) {
        $payDiff = abs($nettosum + $momssum - $betalt);
        $out[] = sprintf("Payment check (pos_betaling): |nettosum+momssum - ekstra2| = %.4f -> %s", $payDiff, $payDiff >= RECONCILE_TOLERANCE ? "REJECTED (Error in amount vs. paid amount)" : "ok");
    }
    if ($flags) {
        $out[] = "";
        $out[] = "Notes:";
        foreach ($flags as $f) {
            $out[] = "  - " . $f;
        }
    }
    $out[] = "";
    $out[] = "Which shop-side rule reproduces the shop totals?";
    foreach (shop_hypotheses($saldiLines, $momssats) as $name => $h) {
        $match = abs($h['net'] - $nettosum) < 0.005 && abs($h['vat'] - $momssum) < 0.005;
        $out[] = sprintf("  %s %-44s net %10.4f  vat %10.4f", $match ? '=>' : '  ', $name, $h['net'], $h['vat']);
    }
    return implode("\n", $out) . "\n";
}

/**
 * Scan a rest_api.log for amount rejections and print each with the line
 * arithmetic fakturer_ordre logged just before it.
 *
 * @param string $path
 * @return string report
 */
function scan_log($path)
{
    $fh = fopen($path, 'r');
    if (!$fh) {
        return "Cannot open $path\n";
    }
    $out = [];
    $block = [];
    $count = 0;
    while (($line = fgets($fh)) !== false) {
        $line = rtrim($line, "\r\n");
        if (strpos($line, 'base currency:') !== false) {
            $block = [];
        }
        if (preg_match('/^\d+ Svar : [-\d.]+=/', $line) || strpos($line, 'select * from ordrelinjer where ordre_id=') !== false) {
            $block[] = $line;
        }
        if (strpos($line, 'Error in amount') !== false) {
            $count++;
            $out[] = str_repeat('=', 100);
            foreach ($block as $b) {
                $out[] = "  " . $b;
            }
            $out[] = $line;
            $block = [];
        }
    }
    fclose($fh);
    $out[] = str_repeat('=', 100);
    $out[] = "$count amount rejection(s) found in $path";
    return implode("\n", $out) . "\n";
}

function usage()
{
    return <<<TXT
Usage:
  php tools/shop_order_reconcile.php REQUESTS.txt [--momssats=25] [--valutakurs=100]
      REQUESTS.txt holds the raw requests for ONE shop order, one per line
      (full URL or query string): the insert_shop_order call followed by its
      insert_shop_orderline calls. Lines starting with # are ignored. Keys are
      redacted in the output. --momssats/--valutakurs override what Saldi takes
      from the customer group / currency table instead of the request.

  php tools/shop_order_reconcile.php --log temp/<db>/rest_api.log
      Lists every "Error in amount" rejection with the line arithmetic that
      fakturer_ordre logged for it.

TXT;
}

function main($argv)
{
    $file = null;
    $log = null;
    $overrides = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--log=(.+)$/', $arg, $m)) {
            $log = $m[1];
        } elseif ($arg === '--log') {
            $log = '';
        } elseif ($log === '') {
            $log = $arg;
        } elseif (preg_match('/^--(momssats|valutakurs)=(.+)$/', $arg, $m)) {
            $overrides[$m[1]] = rate_value($m[2]);
        } elseif ($arg === '-h' || $arg === '--help') {
            fwrite(STDOUT, usage());
            return 0;
        } else {
            $file = $arg;
        }
    }
    if ($log) {
        fwrite(STDOUT, scan_log($log));
        return 0;
    }
    if (!$file || !is_readable($file)) {
        fwrite(STDERR, usage());
        return 1;
    }
    $requests = [];
    foreach (file($file) as $raw) {
        $p = parse_request($raw);
        if ($p) {
            $requests[] = $p;
        }
    }
    fwrite(STDOUT, reconcile($requests, $overrides));
    return 0;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    exit(main($argv));
}
