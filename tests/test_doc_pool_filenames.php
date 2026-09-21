<?php
// tests/test_doc_pool_filenames.php --- 2026-09-21
// Copyright (c) 2026 Danosoft ApS
// 20260921 CDX/PHR Test lossless document selection with comma-containing filenames.
error_reporting(E_ALL);
set_error_handler(function($severity,$message){throw new ErrorException($message,0,$severity);});
$source=file_get_contents($argv[1] ?? __DIR__.'/../includes/docsIncludes/docPool.php');
$start=strpos($source,'$poolFiles = array();');
$end=strpos($source,'$poolFiles = array_filter($poolFiles);',$start);
if($start===false || $end===false)throw new RuntimeException('Selection block missing');
$code=substr($source,$start,$end-$start).'$poolFiles = array_filter($poolFiles);';
$receipt='Scan 2. jul. 2026, 13.03(1).pdf';
$other='Scan 2. jul. 2026, 13.01(2).pdf';
foreach([
 [['poolFile'=>[$receipt],'poolFiles'=>$receipt],[],[$receipt]],
 [['poolFile'=>[$receipt,$other],'poolFiles'=>$receipt.','.$other],[],[$receipt,$other]],
 [['poolFile'=>['a.pdf']],[],['a.pdf']],
 [['poolFiles'=>'a.pdf, b.pdf'],[],['a.pdf','b.pdf']],
 [['poolFile'=>$receipt],[],[$receipt]],
 [[],['poolFile'=>[$receipt]],[$receipt]],
 [[],['poolFile'=>$receipt],[$receipt]],
 [[],['poolFiles'=>'a.pdf,b.pdf'],['a.pdf','b.pdf']],
 [[],[],[]],
] as [$post,$get,$expected]) {
 $_POST=$post;$_GET=$get;$poolFile='currently-viewed.pdf';
 eval($code);
 if(array_values($poolFiles)!==$expected)throw new RuntimeException('Wrong selected filenames: '.json_encode($poolFiles));
}
echo "OK: 9 filename selection cases.\n";
