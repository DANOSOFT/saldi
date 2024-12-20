<?php
#### -------------------- utils/bilagstjek.php --- lap 20.0.0b----- 2008.04.28 -------------
@session_start();
$s_id=session_id();
ini_set("display_errors", "0");

include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/std_func.php");
 
	$x=0;
#cho "select * from regnskab where lukket != 'on' order by id<br>";
	$q=db_select("select * from regnskab where id > 1 and (lukket != 'on' or lukket is  NULL) order by id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$db_id[$x]=$r['id'];
		$regnskab[$x]=$r['regnskab'];
		$db[$x]=$r['db'];
#echo "$db_id[$x] | $regnskab[$x] | $db[$x]<br>";
		$x++;
	}

	$y=date("Y");
	$m=date("m");
	$d=date("d");
	$y--;
	$startdate=$y."-".$m."-".$d;
	for ($x=0;$x<count($db_id);$x++){
		if ($db) {
#cho "$sqhost | $squser | $sqpass | $db[$x]<br>";
			if (db_connect ("$sqhost", "$squser", "$sqpass", "$db[$x]")) {
				if (db_fetch_array(db_select("select * from pg_tables where tablename='transaktioner'"))) {
					$qtxt="select count(id) as posteringer from transaktioner where logdate > '$startdate'";
#cho "$qtxt<br>";
					$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
					$posteringer[$x]=$r['posteringer']*1;
				#cho "$posteringer[$x]<br>";
					$qtxt="select logdate from transaktioner order by logdate desc";
#cho "$qtxt<br>";
					$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
					$sidste[$x]=$r['logdate'];
#cho "Sidste $sidste[$x]<br>";		
#cho "$sqhost | $squser | $sqpass | $sqdb<br>";
				} else echo "Transaktioner eksisterer ikke i $regnskab[$x]<br>";
			} else echo "$regnskab[$x] eksisterer ikke<br>";
			db_connect ("$sqhost", "$squser", "$sqpass", "$sqdb");
			$sidst=strtotime($sidste[$x]);
			if (!$sidst)$sidst=0;
			$qtxt="update regnskab set sidst='$sidst' posteringer = '$posteringer[$x]' where id = '$db_id[$x]'";
#cho "$qtxt<br>";
			#		db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
		}
	}
		
	$postsum=0;	
	print "<table colspan=5 border=3><tbody>";
	print  "<tr><td>Regnskab</td><td align=center>Posteringer</td><td align=center>Sidst</td></tr>";	
	for($x=0;$x<count($db_id);$x++) {
		print "<tr><td>".$regnskab[$x]."</td><td align=right>$posteringer[$x]</td><td align=right>$sidste[$x]</td></tr>";
		$postsum+=$posteringer[$x];
	}
	print "<tr><td colspan=\"1\">Kontrolsum</td><td align=right>$postsum</td></tr>";

?> 
