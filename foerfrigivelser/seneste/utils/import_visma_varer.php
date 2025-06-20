<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/usdecimal.php");

transaktion("begin");
$fp=fopen("../../tidsreg/dumps/2006-06-27/Prod.txt","r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$x++;
		$linje=fgets($fp);
		if (substr($linje,0,1)=='"') { 
			list($ProdNo, $Descr, $ProdGr, $ProdTp, $StSaleUn, $ProdPrGr, $SpecFunc, $ExcPrint, $EditPref, $EdFmt, $ExpStr, $PrM2b, $WageSrt, $DesMrg, $NrmStc, $ProcMt, $AgrAct, $NWgtU, $TareU, $VolU, $CusStNo, $RepProd, $R1, $R2, $R3, $R4, $R5, $R6, $Gr, $Gr2, $Gr3, $Gr4, $Gr5, $Gr6, $Inf, $Inf2, $Inf3, $Inf4, $Inf5, $Inf6, $ExArr, $ExQty, $NoteNm, $CstPrAdd, $GrSep, $EUStatNo, $ProdTp2, $FrNo, $ToNo, $Factor3, $InvoPlLn, $Rsp, $Buyer, $ProdPrG2, $ProdPrG3, $PerNot, $PerBack, $StartDt, $Alfa, $LgtU, $WdtU, $HgtU, $AreaU, $Measure, $StUnRt, $Gr7, $Gr8, $Gr9, $Gr10, $Gr11, $Gr12, $Inf7, $Inf8, $ProdTp3, $ProdTp4, $TrInf1, $TrInf2, $Rsp2, $PrUn, $PrNo, $DensU, $TrTp, $TrAm, $PrM1b, $PrM1c, $PrM2c, $PrM3b, $PrM3c, $PrM4b, $PrM4c, $PrM5b, $PrM5c, $PictNo, $TrInf3, $TrInf4, $OldPNo, $SPrAdd, $EANItmNo, $ProdPro, $PrCatNo, $PrCatNo2, $NwProdNo, $WebPg2, $PgElNo) = split("\" \"", $linje);
			$ProdNo=substr($ProdNo,1);
			$Descr=str_replace("'", "\\'", $Descr);
			db_modify("INSERT INTO varer (varenr, beskrivelse, gruppe, lukket) values ('$ProdNo', '$Descr', '1', '0')");
		}
	}
} 
fclose($fp);
echo "$x varer importeret<br>";
transaktion("commit");
?>