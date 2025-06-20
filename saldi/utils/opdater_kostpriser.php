<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$q=db_select("select varer.id as vare_id,ordrelinjer.pris*ordrer.valutakurs/100 as ny_kostpris from varer,ordrelinjer,ordrer where varer.gruppe ='2' and ordrelinjer.vare_id=varer.id and ordrelinjer.ordre_id=ordrer.id and lower(ordrer.art)='ko' and ordrer.fakturadate='2015.01.05' order by varer.varenr",__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)) {
	db_modify("update varer set kostpris='$r[ny_kostpris]' where id = '$r[vare_id]'",__FILE__ . " linje " . __LINE__);
}
?>