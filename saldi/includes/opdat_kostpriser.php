<?php
@session_start();
$s_id=session_id();

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

ini_set("display_errors", "1");
	print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
	<html>
		<head>
			<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
</head>";

$vare_id=if_isset($_GET['vare_id'])*1;
if ($r=db_fetch_array(db_select("select id,vare_id,pris,kobsdate from batch_kob where vare_id > '$vare_id' and linje_id != '0' and antal > '0' order by vare_id,kobsdate desc limit 1",__FILE__ . " linje " . __LINE__))) {
	$id=$r['id'];
	$vare_id=$r['vare_id'];
	$pris=$r['pris'];
	$kobsdate=$r['kobsdate'];
	$qtxt=NULL;
	if ($r=db_fetch_array(db_select("select id,kostpris,transdate from kostpriser where vare_id='$vare_id' order by transdate desc limit 1",__FILE__ . " linje " . __LINE__))) {	
		if ($r['transdate'] < $kobsdate && $r['kostpris'] != $pris) $qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$vare_id','$pris','$kobsdate')";
		elseif ($r['transdate'] == '2015-01-01' && $r['kostpris'] != $pris) $qtxt="update kostpriser set kostpris='$pris', transdate = '$kobsdate' where id = '$id'";
		elseif ($r['transdate'] == $kobsdate && $r['kostpris'] != $pris) $qtxt="update kostpriser set kostpris='$pris' where id = '$id'";
	} else $qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$vare_id','$pris','$kobsdate')";
	if ($qtxt) db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
	db_modify("update varer set kostpris='$pris' where id='$vare_id'",__FILE__ . " linje " . __LINE__);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/opdat_kostpriser.php?vare_id=$vare_id\">";
	exit;
} else print "<body onload=\"javascript:window.close();\">";


print "</html>";
?>