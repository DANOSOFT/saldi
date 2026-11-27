<?php
// Copyright (c) 2026 Danosoft ApS
// 20261028 CDX/MJ SST-876 Execute the real post-commit tails with transaction doubles for all five outer callers.
// 20261120 CL/MMR SST-999 In the two booking APIs the commit and return are taken from fakturer_ordre() only.
//                 createBooking now commits its own booking transaction earlier in the file, and the first commit in the file is no longer the invoice's.
$root = dirname(__DIR__, 4);
$depth = 1;
$events = [];
function transaktion($command) {
    $GLOBALS['events'][] = $command;
    $GLOBALS['depth'] += $command === 'begin' ? 1 : -1;
}
function shopSyncDrainQueue() {
    if ($GLOBALS['depth'] !== 0) {
        throw new RuntimeException('Sync ran before outer commit');
    }
    $GLOBALS['events'][] = 'drain';
}
function shopSyncAlertFailedItems() { $GLOBALS['events'][] = 'alert'; }
function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}
foreach (['soapserver/invoice.php', 'remoteBooking/api.php', 'mysale/api.php', 'debitor/ret_genfakt.php', 'debitor/bogfor.php'] as $file) {
    $source = file_get_contents($root . '/' . $file);
    $bookingApi = $file === 'remoteBooking/api.php' || $file === 'mysale/api.php';
    // In the booking APIs the invoice's commit is the one in fakturer_ordre(); createBooking has its own earlier.
    $from = 0;
    $functionEnd = strlen($source);
    if ($bookingApi) {
        $from = strpos($source, 'function fakturer_ordre(');
        check($from !== false, "$file has fakturer_ordre()");
        $open = strpos($source, '{', $from);
        $balance = 1;
        $functionEnd = $open + 1;
        while ($balance && $functionEnd < strlen($source)) {
            $balance += ($source[$functionEnd] === '{' ? 1 : ($source[$functionEnd] === '}' ? -1 : 0));
            $functionEnd++;
        }
    }
    preg_match('/transaktion\s*\(\s*[\'"]commit[\'"]\s*\)\s*;/', $source, $match, PREG_OFFSET_CAPTURE, $from);
    check(isset($match[0]) && $match[0][1] < $functionEnd, "$file has the transaction-owner commit");
    $start = $match[0][1];
    if ($bookingApi) {
        $return = strpos($source, 'return($saldi_id);', $start);
        check($return !== false && $return < $functionEnd, "$file returns the order id after the commit, in fakturer_ordre()");
        $end = $return + strlen('return($saldi_id);');
        $code = substr($source, $start, $end - $start);
    } else {
        // Take the commit and the brace-balanced write-error guard immediately after it.
        $at = strpos($source, 'if (empty($GLOBALS[', $start);
        check($at !== false && $at - $start < 100, "$file guards post-commit sends against write errors");
        $open = strpos($source, '{', $at);
        $balance = 1;
        $end = $open + 1;
        while ($balance && $end < strlen($source)) {
            $balance += ($source[$end] === '{' ? 1 : ($source[$end] === '}' ? -1 : 0));
            $end++;
        }
        $code = substr($source, $start, $end - $start);
    }
    foreach ([false, true] as $failed) {
        $depth = 1;
        $events = [];
        $db_modify_fejl = $failed;
        $saldi_id = 42;
        $run = function () use ($code, $db_modify_fejl, $saldi_id) { return eval($code); };
        $run();
        check($depth === 0, "$file completes its outer transaction");
        check(in_array('drain', $events, true) === !$failed, "$file sends only after a successful commit (write error=" . (int)$failed . ')');
        if (!$failed) {
            check($events[0] === 'commit' && $events[1] === 'drain', "$file commits before sending deferred stock");
        }
    }
}
