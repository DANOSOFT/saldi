<?php 
$bg="nix";
include("../includes/connect.php");
include("../includes/std_func.php");

$affiliate=if_isset($_GET['affiliate']);
$website=if_isset($_GET['website']);
$dato=if_isset($_GET['dato']);
if (!$dato) $dato="15.09.2011";
$date=usdate($dato);

$oprettede=0;
$q = db_select("select affiliate,referer from kundedata where oprettet >= '$date'",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($affiliate==$r['affiliate']) {
		$oprettede++;
	} elseif (!$r['affiliate'] && (strstr($r['referer'],$website))) {
		$oprettede++;
	}
}
print "<link rel=\"stylesheet\" href=\"http://saldi.dk/css/standard.css\" type=\"text/css\" />";
echo "$oprettede";
?> 
