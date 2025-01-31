<?php 
print "<html>";
$newDb=$newHost=$lnk=NULL;
if ($db == 'bizsys_107') {
	$newDb = 'pos_60';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_108') {
	$newDb = 'pos_74';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_127') {
	$newDb = 'pos_112';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_129') {
	$newDb = 'pos_2';
	$newHost = 'ssl7.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_151') {
	$newDb = 'pos_113';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_175') {
	$newDb = 'pos_83';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_207') {
	$newDb = 'pos_59';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_209') {
	$newDb = 'pos_78';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_233') {
	$newDb = 'pos_53';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_248') {
	$newDb = 'no_18';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/no/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_286') {
	$newDb = 'pos_29';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_307') {
	$newDb = 'pos_49';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_317') {
	$newDb = 'pos_66';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_328') {
	$newDb = 'pos_72';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_351') {
	$newDb = 'pos_76';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_356') {
	$newDb = 'pos_27';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_378') {
	$newDb = 'pos_84';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_391') {
	$newDb = 'no_2';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/no/mysale/mysale.php?id=';
} elseif ($db == 'bizsys_398') {
	$newDb = 'pos_6';
	$newHost = 'ssl4.saldi.dk';
	$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
}
/*
elseif (substr($db,0,6)!='bizsys') {
	list($a,$b) = explode('_',$db); 
	if ($a == 'develop') {
		$newHost = 'ssl10.saldi.dk';
		$lnk = 'https://'. $newHost .'/pos/mysale/mysale.php?id=';
		$newDb = $db;
	}
}
*/
if ($newDb && $newHost && $lnk) {
	$txt = $kto .'@'. $newDb  .'@'. $newHost;
	for ($x=0;$x<strlen($txt);$x++) {
		$lnk.=dechex(ord(substr($txt,$x,1)));
	}
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$lnk\">\n";
	exit;
} 
print "</html>";
?>
