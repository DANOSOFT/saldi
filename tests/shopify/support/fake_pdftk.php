#!/usr/bin/env php
<?php
// tests/shopify/support/fake_pdftk.php
//
// Stand-in for pdftk in the render_document tests, for machines without it: "<in>... cat output <out>"
// and "<in> background <bg> output <out>" both copy the first input to <out>. Each call is appended
// to $FAKE_PDFTK_LOG when set, with $FAKE_PDFTK_CALLER (the process that started it); $FAKE_PDFTK_SLEEP_MS
// delays each call, to widen race windows.
//
// History:
// 20261029 CL/SZ SD-751: created.

$args = array_slice($argv, 1);
$at = array_search('output', $args, true);
if ($at === false || !isset($args[$at + 1], $args[0]) || !is_file($args[0])) {
    fwrite(STDERR, "fake_pdftk: unsupported call: " . implode(' ', $args) . "\n");
    exit(1);
}
if (($sleep = (int)getenv('FAKE_PDFTK_SLEEP_MS')) > 0) {
    usleep($sleep * 1000);
}
if (($log = getenv('FAKE_PDFTK_LOG')) !== false && $log !== '') {
    file_put_contents($log, (getenv('FAKE_PDFTK_CALLER') ?: '?') . ' ' . implode(' ', array_map('basename', $args)) . "\n", FILE_APPEND | LOCK_EX);
}
exit(copy($args[0], $args[$at + 1]) ? 0 : 1);
