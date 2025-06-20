<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/usdecimal.php");

if (isset($_GET['start']))  $startlinje=$_GET['start'];
else $startlinje=0;
$slutlinje=$startlinje+999;
echo "Importerer linje $startlinje til linje $slutlinje<br>";
transaktion("begin");
$fp=fopen("../../tidsreg/dumps/2006-06-27/OrdLn.txt","r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$x++;
		$linje=fgets($fp);
		if (($x>=$startlinje) && ($x<$slutlinje) && (substr($linje,0,1)=='"')) { 
			list($OrdNo, $LnNo, $TrDt, $EmpNo, $EmpPrGr, $WageRtNo, $CustNo, $InvoCust, $CustPrGr, $SupNo, $RmtSup, $ProdNo, $ProdPrGr, $SpecFunc, $StrSt, $ExcPrint, $Descr, $ProcMt, $FrStc, $ToStc, $Un, $StUnRt, $EdFmt, $FrNo, $ToNo, $NoReg, $NoInvoAb, $NoRet, $NoComp, $NoScr, $CompPr, $DCompPr, $CstPr, $Cur, $ExRt, $StPr, $DStPr, $Price, $DPrice, $Dc1P, $Dc1Am, $Dc1DAm, $Dc2P, $Dc2Am, $Dc2DAm, $Am, $DAm, $ProdGr, $AcSet, $VatNo, $DstDc, $DDstDc, $DstAd, $DDstAd, $SpcTxCd, $SpcTxAm, $DSpcTxAm, $VatAm, $DVatAm, $InvoRef, $RefNo, $DelDt, $CfDelDt, $SelBuy, $Rsp, $R1, $R2, $R3, $R4, $R5, $R6, $ResNo, $WageSrt, $AdWage1, $AdWage2, $ProdTp, $TransGr, $IncCst, $ShpNo, $PurcNo, $SerNo, $Loc, $DurDt, $ExArr, $ExQty, $NoInq, $NoPurc, $NoOf, $NoCf, $NoPic, $NoFin, $FinInc, $NoPac, $NoCon, $NoInvo, $EditPref, $FFm, $FSz, $FSt, $LnFl, $Dupl, $Srt, $DelAltNo, $ToShpNo, $CstCur, $CstExRt, $CCstPr, $CIncCst, $InvoSF, $DInvoSF, $IncSF, $InvoIF, $DInvoIF, $IncIF, $CIncSF, $CIncIF, $NoteNm, $Dupl2, $DelActNo, $CstPrAdd, $DelMt, $SCd, $NoRsv, $GrSep, $TrTp, $ERetDt, $EUStatNo, $ProdTp2, $TransGr2, $Factor3, $ArDt, $Stat1, $Stat2, $InvoPlLn, $LstSetDt, $Shr, $TanspTm, $DelTm, $ProdPrG2, $ProdPrG3, $CustPrG2, $FreeNo, $RlzFree, $NWgtU, $TareU, $LgtU, $WdtU, $HgtU, $AreaU, $VolU, $NoUn, $NWgtL, $TareL, $LgtL, $AreaL, $VolL, $Measure, $DFrAm1, $DFrAm2, $DFrAm3, $DFrAm4, $PrUn, $FinBy, $TransGr3, $TransGr4, $ProdTp3, $ProdTp4, $TrInf1, $TrInf2, $EDISt, $OrdTp, $Tenancy, $PrNo, $OrdLnSt, $InvoMth, $DensU, $MntTm, $DemTm, $PriceF, $FCfDelDt, $RlDelDt, $DelGr, $AdmTm, $Alloc, $PrTp, $Cus, $FrCusPro, $FinDt, $YrWk, $FinTm, $Dc3P, $Dc3Am, $Dc3DAm, $Dc4P, $Dc4Am, $Dc4DAm, $Dc5P, $Dc5Am, $Dc5DAm, $Dc6P, $Dc6Am, $Dc6DAm, $NoProd, $TrInf3, $TrInf4, $YrPr, $TrfOrdNo, $D1Hr, $D2Hr, $D3Hr, $D4Hr, $D5Hr, $D6Hr, $D7Hr, $OvCNOSm, $PicN, $FinN, $RetN, $CompN, $ScrN, $RlzN, $RsvN, $Free1, $Free2, $Free3, $Free4, $PictFNm, $JNo, $EntNo, $CustPrG3) = split("\" \"", $linje);
			$OrdNo=substr($OrdNo,1);	
			if ($OrdNo) {
				if ($r1=db_fetch_array(db_select("select id from ordrer where ordrenr='$OrdNo'"))) $ordre_id=$r1['id'];
				else $ordre_id='0';
			}
			else $ordre_id='0';
			
			if (($Price)&&(!$ProdNo)) {
				$ProdNo=".";
				$NoCf=1;	
			}
			if (($ordre_id)&&($ProdNo)) {
				if ($r1=db_fetch_array(db_select("select id from VARER where varenr='$ProdNo'"))) $vare_id=$r1['id'];
				elseif ($ProdNo==".") {
					db_modify("insert into varer (varenr, beskrivelse, gruppe, lukket) values ('$ProdNo', '', '1', '0')"); 
					$r1=db_fetch_array(db_select("select id from VARER where varenr='$ProdNo'")); $vare_id=$r1['id'];
				}
				else $vare_id='0';
			}
			else $vare_id='0';
			
			if ($ordre_id) {
				$Descr=str_replace("'", "\\'", $Descr);
				$Price=$Price*1;
				$InvoAm=$InvoAm*1;
				$LnNo=$LnNo*1;			
				$NoCf=$NoCf*1;			
				if (($ProdNo)&&(!$NoCf)) $NoCf=1;

# echo "ordre_id: $ordre_id, posnr: $LnNo, beskrivelse: $Descr, pris: $Price<br>";
				db_modify("INSERT INTO ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, pris, antal) values ('$ordre_id', '$LnNo', '$ProdNo', '$vare_id', '$Descr', '$Price', '$NoCf')");
			}
		}
		if ($x>$slutlinje) break;
	}
} 
fclose($fp);
echo "$x ordrelinjer importeret<br>";
transaktion("commit");
if ($x > $slutlinje) {
	$slutlinje++;
	print "<meta http-equiv=\"refresh\" content=\"0;URL=import_visma_ordrelinjer.php?start=$slutlinje\">";
}
?>