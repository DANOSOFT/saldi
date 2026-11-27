<?php
// php tests/test_driftsbesked.php  -> exit 0 when the operations notice behaves
require __DIR__ . '/../includes/driftsbesked.php';
$fail = 0;
function check($ok, $what) { global $fail; echo ($ok ? "ok   " : "FAIL ") . "$what\n"; if (!$ok) $fail++; }
$dir = sys_get_temp_dir() . '/driftsbesked_' . getmypid(); @mkdir($dir); $f = "$dir/driftsbesked.txt";

check(driftsbeskedText($f) === '', 'no file: no notice');
check(driftsbeskedHtml('') === '', 'no notice: no html');
file_put_contents($f, "  \n\n "); check(driftsbeskedText($f) === '', 'blank file: no notice');
file_put_contents($f, file_get_contents(__DIR__ . '/../doc/driftsbesked-eksempel.txt'));
$t = driftsbeskedText($f); $h = driftsbeskedHtml($t);
check(strpos($h, 'Driftsforstyrrelse: bilag kan ikke vises lige nu') !== false, 'first line is the heading');
check(strpos($h, 'Ingen bilag eller data er gået tabt') !== false, 'body shown, UTF-8 kept');
file_put_contents($f, "<script>alert(1)</script>\nx & <b>y</b>"); $h = driftsbeskedHtml(driftsbeskedText($f));
check(strpos($h, '<script>alert(1)') === false && strpos($h, '&lt;b&gt;') !== false, 'text is escaped');
check(driftsbeskedHtml("a") !== driftsbeskedHtml("b") && preg_match('/saldi_driftsbesked_[0-9a-f]{12}/', $h), 'changed text gets a new dismiss key');
file_put_contents($f, str_repeat('æ', 5000)); check(mb_strlen(driftsbeskedText($f)) === 2000, 'capped at 2000 chars');
unlink($f); rmdir($dir);
exit($fail ? 1 : 0);
