<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/usdecimal.php");

transaktion("begin");
$fp=fopen("../../tidsreg/dumps/2006-06-27/Ac.txt","r");
if ($fp) {
	$overskrft=array();
	$x=0; $y=0;
	while (!feof($fp)) {
		$x++;
		$linje=fgets($fp);
		if (substr($linje,0,1)=='"') { 
			list($AcNo, $Nm, $AcCode, $AcGr, $Res, $TxCl, $TxCd, $InpTxCd, $Trn, $Susp, $Cur, $InpQty, $R1, $R2, $R3, $R4, $R5, $R6, $InpR1, $InpR2, $InpR3, $InpR4, $InpR5, $InpR6, $DstGr, $ExpAut, $Gr, $Gr2, $Gr3, $Gr4, $Gr5, $Gr6, $Inf, $Inf2, $Inf3, $Inf4, $Inf5, $Inf6, $RepGr, $RepCd, $ProdNo, $Gr7, $Gr8, $Gr9, $Gr10, $Gr11, $Gr12, $Inf7, $Inf8, $Per, $OldAcNo, $TxCtrl, $NwAcNo, $WebPg) = split("\" \"", $linje);
			$AcNo=substr($AcNo,1);
			$Nm=str_replace("'", "\\'", $Nm);
			$AcGr=str_replace("'", "\\'", $AcGr);
			if (!in_array($AcGr,$overskrift)) {
				$y++;
				$overskrift[$y]=$AcGr;
				$oskontonr[$y]=$AcNo;
			} 
			$osantal=$y;
			for ($z=1; $z<=$osantal; $z++) {
				if (($overskrift[$z]==$AcGr)&&($oskontonr[$z]>=$AcNo)) $oskontonr[$z]=$AcNo;
			}

			if ($TxCd==1) $TxCd='K1';
			if ($TxCd==4) $TxCd='S1';
			else $TxCd='';
			if ($Res==1) db_modify("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar) values ('$AcNo', '$Nm', 'D', '$TxCd', 1)");
			else db_modify("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, regnskabsaar) values ('$AcNo', '$Nm', 'S', '1')");
		}
	}
} 
fclose($fp);
$linjeantal=$x;
echo "$x konti importeret<br>";
echo "$y overskrifter importeret<br>";
for ($y=1; $y<=$osantal; $y++) {
	$oskontonr[$y]--;
	db_modify("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, regnskabsaar) values ('$oskontonr[$y]', '$overskrift[$y]', '0', '1')");
	echo "$oskontonr[$y] : $overskrift[$y]<br>";
}
transaktion("commit");
?>