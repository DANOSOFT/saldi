<?php

@session_start();
$s_id=session_id();
$title="Diverse Indstilinger";
$modulnr=1;
$css="../css/standard.css";

$diffkto=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
# include("top.php");



$linje=NULL;
$filnavn="../temp/$db/$bruger_id.csv";
$fp=fopen($filnavn,"w");
$query="select m_rabat from ordrelinjer";
$q=db_select("$query");
$fieldName = db_field_name($q,0); 
$fieldType = db_field_type($q,0);

echo "$fieldName $fieldType<br>";
?>