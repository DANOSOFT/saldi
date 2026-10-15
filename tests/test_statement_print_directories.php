<?php
// Copyright (c) 2026 Danosoft ApS
// 20261008 CDX/PHR Verify statements and reminders preserve earlier print files.
error_reporting(E_ALL);
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$source=file_get_contents(__DIR__.'/../includes/formfunk.php');
if (!preg_match_all('/\$printDirectory = abs\(.*?throw new RuntimeException\([^;]+;\s*\}/s',$source,$matches) || count($matches[0])!==2) throw new RuntimeException('Expected both print paths');
$root=sys_get_temp_dir().'/statement-print-test-'.bin2hex(random_bytes(6));
mkdir($root);mkdir($root.'/includes');mkdir($root.'/temp');mkdir($root.'/temp/fixture');mkdir($root.'/temp/fixture/27_012006');
file_put_contents($root.'/temp/fixture/27_012006/kontoudtog.pdf','old PDF fixture');
$cwd=getcwd();chdir($root.'/includes');$db='fixture';$bruger_id=27;$created=array();
try {
 foreach (array(1,1,0,1) as $block) {
  eval($matches[0][$block]);
  if (!is_dir($mappe) || basename($mappe)!==$printDirectory || isset($created[$mappe])) throw new RuntimeException('Print directory collision');
  file_put_contents($mappe.'/kontoudtog.pdf','current PDF fixture');$created[$mappe]=true;
  if (!is_file('../temp/fixture/27_012006/kontoudtog.pdf')) throw new RuntimeException('Old print deleted');
  foreach ($created as $path=>$unused) if (!is_file($path.'/kontoudtog.pdf')) throw new RuntimeException('Parallel print deleted');
 }
 echo "PASS: repeated statements and reminders preserve old and parallel print files; all four directories are unique\n";
} finally {
 foreach ($created as $path=>$unused) {unlink($path.'/kontoudtog.pdf');rmdir($path);}
 chdir($cwd);unlink($root.'/temp/fixture/27_012006/kontoudtog.pdf');rmdir($root.'/temp/fixture/27_012006');rmdir($root.'/temp/fixture');rmdir($root.'/temp');rmdir($root.'/includes');rmdir($root);
}
