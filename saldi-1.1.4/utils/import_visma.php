<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/usdecimal.php");

if (isset($_GET['start']))  $startlinje=$_GET['start'];
else $startlinje=0;
$slutlinje=$startlinje+99;
echo "Importerer linje $startlinje til linje $slutlinje<br>";
transaktion("begin");
$fp=fopen("../../tidsreg/dumps/struct.txt","r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$x++;
		$linje=fgets($fp);
		if substr($linje,0,1)=='@') {
			if (($afsnit=='Ord') {
				if (strstr($linje,'@Ord')) $Ord='OK';
				elseif ($Ord=='OK') $afsnit='OrdLn';
			}
			if (($afsnit=='OrdLn') {
				if (strstr($linje,'@OrdLn')) $OrdLn='OK';
				elseif ($OrdLn=='OK') $afsnit='struct';
			}
			elseif (($afsnit=='struct') {
				if (strstr($linje,'@struct')) $struct='OK';
				elseif ($struct=='OK') $afsnit='';
		}
		if (($afsnit=='Ord') && ($x>=$startlinje) && ($x<$slutlinje) && (substr($linje,0,1)=='"')) { 
			list($OrdNo, $LnNo, $TrDt, $EmpNo, $EmpPrGr, $WageRtNo, $CustNo, $InvoCust, $CustPrGr, $SupNo, $RmtSup, $ProdNo, $ProdPrGr, $SpecFunc, $StrSt, $ExcPrint, $Descr, $ProcMt, $FrStc, $ToStc, $Un, $StUnRt, $EdFmt, $FrNo, $ToNo, $NoReg, $NoInvoAb, $NoRet, $NoComp, $NoScr, $CompPr, $DCompPr, $CstPr, $Cur, $ExRt, $StPr, $DStPr, $Price, $DPrice, $Dc1P, $Dc1Am, $Dc1DAm, $Dc2P, $Dc2Am, $Dc2DAm, $Am, $DAm, $ProdGr, $AcSet, $VatNo, $DstDc, $DDstDc, $DstAd, $DDstAd, $SpcTxCd, $SpcTxAm, $DSpcTxAm, $VatAm, $DVatAm, $InvoRef, $RefNo, $DelDt, $CfDelDt, $SelBuy, $Rsp, $R1, $R2, $R3, $R4, $R5, $R6, $ResNo, $WageSrt, $AdWage1, $AdWage2, $ProdTp, $TransGr, $IncCst, $ShpNo, $PurcNo, $SerNo, $Loc, $DurDt, $ExArr, $ExQty, $NoInq, $NoPurc, $NoOf, $NoCf, $NoPic, $NoFin, $FinInc, $NoPac, $NoCon, $NoInvo, $EditPref, $FFm, $FSz, $FSt, $LnFl, $Dupl, $Srt, $DelAltNo, $ToShpNo, $CstCur, $CstExRt, $CCstPr, $CIncCst, $InvoSF, $DInvoSF, $IncSF, $InvoIF, $DInvoIF, $IncIF, $CIncSF, $CIncIF, $NoteNm, $Dupl2, $DelActNo, $CstPrAdd, $DelMt, $SCd, $NoRsv, $GrSep, $TrTp, $ERetDt, $EUStatNo, $ProdTp2, $TransGr2, $Factor3, $ArDt, $Stat1, $Stat2, $InvoPlLn, $LstSetDt, $Shr, $TanspTm, $DelTm, $ProdPrG2, $ProdPrG3, $CustPrG2, $FreeNo, $RlzFree, $NWgtU, $TareU, $LgtU, $WdtU, $HgtU, $AreaU, $VolU, $NoUn, $NWgtL, $TareL, $LgtL, $AreaL, $VolL, $Measure, $DFrAm1, $DFrAm2, $DFrAm3, $DFrAm4, $PrUn, $FinBy, $TransGr3, $TransGr4, $ProdTp3, $ProdTp4, $TrInf1, $TrInf2, $EDISt, $OrdTp, $Tenancy, $PrNo, $OrdLnSt, $InvoMth, $DensU, $MntTm, $DemTm, $PriceF, $FCfDelDt, $RlDelDt, $DelGr, $AdmTm, $Alloc, $PrTp, $Cus, $FrCusPro, $FinDt, $YrWk, $FinTm, $Dc3P, $Dc3Am, $Dc3DAm, $Dc4P, $Dc4Am, $Dc4DAm, $Dc5P, $Dc5Am, $Dc5DAm, $Dc6P, $Dc6Am, $Dc6DAm, $NoProd, $TrInf3, $TrInf4, $YrPr, $TrfOrdNo, $D1Hr, $D2Hr, $D3Hr, $D4Hr, $D5Hr, $D6Hr, $D7Hr, $OvCNOSm, $PicN, $FinN, $RetN, $CompN, $ScrN, $RlzN, $RsvN, $Free1, $Free2, $Free3, $Free4, $PictFNm, $JNo, $EntNo, $CustPrG3) = split("\" \"", $linje);
			$OrdNo=substr($OrdNo,1);	
			if ($OrdNo) {
				if ($r1=db_fetch_array(db_select("select id from ordrer where ordrenr='$OrdNo'"))) $ordre_id=$r1['id'];
				else $ordre_id='0';
			}
			$Descr=str_replace("'", "\\'", $Descr);
			$aar=substr($OrdDt,0,4);
			$md=substr($OrdDt,4,2);
			$dato=substr($OrdDt,6,2);
			$OrdDt=$aar."-".$md."-".$dato;
			$CrLmt=$CrLmt*1;
			$InvoAm=$InvoAm*1;
		
			if (($Nm)&&($CustNo)) {
				db_modify("INSERT INTO ordrelinjer (ordre_id, posnr, beskrivelse, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, notes, cvrnr, art, ordredate, sum, status) values ('$ordre_id', '$LnNo', '$Descr', '$Nm', '$Ad1', '$Ad2', '$PNo', '$PArea', '$Ctry', '$Pers', '$notes', '$CM', 'DO', '$OrdDt', '$InvoAm', '2')");
			}
		}
		if (($afsnit=='OrdLn') && ($x>=$startlinje) && ($x<$slutlinje) && (substr($linje,0,1)=='"')) { 
			list($OrdNo, $LnNo, $TrDt, $EmpNo, $EmpPrGr, $WageRtNo, $CustNo, $InvoCust, $CustPrGr, $SupNo, $RmtSup, $ProdNo, $ProdPrGr, $SpecFunc, $StrSt, $ExcPrint, $Descr, $ProcMt, $FrStc, $ToStc, $Un, $StUnRt, $EdFmt, $FrNo, $ToNo, $NoReg, $NoInvoAb, $NoRet, $NoComp, $NoScr, $CompPr, $DCompPr, $CstPr, $Cur, $ExRt, $StPr, $DStPr, $Price, $DPrice, $Dc1P, $Dc1Am, $Dc1DAm, $Dc2P, $Dc2Am, $Dc2DAm, $Am, $DAm, $ProdGr, $AcSet, $VatNo, $DstDc, $DDstDc, $DstAd, $DDstAd, $SpcTxCd, $SpcTxAm, $DSpcTxAm, $VatAm, $DVatAm, $InvoRef, $RefNo, $DelDt, $CfDelDt, $SelBuy, $Rsp, $R1, $R2, $R3, $R4, $R5, $R6, $ResNo, $WageSrt, $AdWage1, $AdWage2, $ProdTp, $TransGr, $IncCst, $ShpNo, $PurcNo, $SerNo, $Loc, $DurDt, $ExArr, $ExQty, $NoInq, $NoPurc, $NoOf, $NoCf, $NoPic, $NoFin, $FinInc, $NoPac, $NoCon, $NoInvo, $EditPref, $FFm, $FSz, $FSt, $LnFl, $Dupl, $Srt, $DelAltNo, $ToShpNo, $CstCur, $CstExRt, $CCstPr, $CIncCst, $InvoSF, $DInvoSF, $IncSF, $InvoIF, $DInvoIF, $IncIF, $CIncSF, $CIncIF, $NoteNm, $Dupl2, $DelActNo, $CstPrAdd, $DelMt, $SCd, $NoRsv, $GrSep, $TrTp, $ERetDt, $EUStatNo, $ProdTp2, $TransGr2, $Factor3, $ArDt, $Stat1, $Stat2, $InvoPlLn, $LstSetDt, $Shr, $TanspTm, $DelTm, $ProdPrG2, $ProdPrG3, $CustPrG2, $FreeNo, $RlzFree, $NWgtU, $TareU, $LgtU, $WdtU, $HgtU, $AreaU, $VolU, $NoUn, $NWgtL, $TareL, $LgtL, $AreaL, $VolL, $Measure, $DFrAm1, $DFrAm2, $DFrAm3, $DFrAm4, $PrUn, $FinBy, $TransGr3, $TransGr4, $ProdTp3, $ProdTp4, $TrInf1, $TrInf2, $EDISt, $OrdTp, $Tenancy, $PrNo, $OrdLnSt, $InvoMth, $DensU, $MntTm, $DemTm, $PriceF, $FCfDelDt, $RlDelDt, $DelGr, $AdmTm, $Alloc, $PrTp, $Cus, $FrCusPro, $FinDt, $YrWk, $FinTm, $Dc3P, $Dc3Am, $Dc3DAm, $Dc4P, $Dc4Am, $Dc4DAm, $Dc5P, $Dc5Am, $Dc5DAm, $Dc6P, $Dc6Am, $Dc6DAm, $NoProd, $TrInf3, $TrInf4, $YrPr, $TrfOrdNo, $D1Hr, $D2Hr, $D3Hr, $D4Hr, $D5Hr, $D6Hr, $D7Hr, $OvCNOSm, $PicN, $FinN, $RetN, $CompN, $ScrN, $RlzN, $RsvN, $Free1, $Free2, $Free3, $Free4, $PictFNm, $JNo, $EntNo, $CustPrG3) = split("\" \"", $linje);
			$OrdNo=substr($OrdNo,1);	
			if ($OrdNo) {
				if ($r1=db_fetch_array(db_select("select id from ordrer where ordrenr='$OrdNo'"))) $ordre_id=$r1['id'];
				else $ordre_id='0';
			}
			$Descr=str_replace("'", "\\'", $Descr);

			$aar=substr($OrdDt,0,4);
			$md=substr($OrdDt,4,2);
			$dato=substr($OrdDt,6,2);
			$OrdDt=$aar."-".$md."-".$dato;
			$CrLmt=$CrLmt*1;
			$InvoAm=$InvoAm*1;
			
			if (($Nm)&&($CustNo)) {
				db_modify("INSERT INTO ordrelinjer (ordre_id, posnr, beskrivelse, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, notes, cvrnr, art, ordredate, sum, status) values ('$ordre_id', '$LnNo', '$Descr', '$Nm', '$Ad1', '$Ad2', '$PNo', '$PArea', '$Ctry', '$Pers', '$notes', '$CM', 'DO', '$OrdDt', '$InvoAm', '2')");
			}
		}
		if (($afsnit=='struct') && ($x>=$startlinje) && ($x<$slutlinje) && (substr($linje,0,1)=='"')) { 
			list($ProdNo, $LnNo, $SubProd, $Descr, $ID, $NoPerStr, $SpecSub, $FFm, $FSz, $FSt, $LnFl, $Srt, $PrM2, $RawMat, $ProdTp1, $ProdTp2, $ProdTp3, $ProdTp4, $TrInf1, $TrInf2, $StrWgt, $PrM1, $PrM3, $PrM4, $PrM5, $TrInf3, $TrInf4) = split("\" \"", $linje);
			$ProdNo=substr($ProdNo,1);
			$r1=db_fetch_array(db_select("select id from varer where varenr='$ProdNo'"));
			$indgaar_i=$r1['id'];
			$r1=db_fetch_array(db_select("select id from varer where varenr='$SubProd'"));
			$vare_id=$r1['id'];

			if (($vare_id) && ($indgaar_i)) db_modify("INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) values ('$vare_id', '$indgaar_i', '$NoPerStr', '$LnNo')");
		}
		if ($x>$slutlinje) break;
	}
} 
fclose($fp);
echo "$x styklister importeret uden fejl<br>";
transaktion("commit");
if ($x > $slutlinje) {
	$slutlinje++;
	print "<meta http-equiv=\"refresh\" content=\"0;URL=import_visma.php?afsnit='stykliste'&start=$slutlinje\">";
}
?>