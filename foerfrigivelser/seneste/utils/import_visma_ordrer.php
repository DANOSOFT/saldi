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
$fp=fopen("../../tidsreg/dumps/2006-06-27/Ord.txt","r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$x++;
		$linje=fgets($fp);
		if (($x>=$startlinje) && ($x<$slutlinje) && (substr($linje,0,1)=='"')) { 
			
			list($OrdNo, $TrTp, $OrdTp, $OrdDt, $CustNo, $Nm, $Ad1, $Ad2, $Ad3, $Ad4, $PNo, $PArea, $Ctry, $Lang, $SupNo, $SelBuy, $Rsp, $TransGr, $Gr, $Gr2, $Gr3, $Gr4, $Gr5, $Gr6, $Inf, $Inf2, $Inf3, $Inf4, $Inf5, $Inf6, $R1, $R2, $R3, $R4, $R5, $R6, $OrdBasNo, $OrdPrGr, $CustPrGr, $TotDcP, $TotDcAm, $TotDcDAm, $ObVatNo, $ExVat, $ExSpc, $NOrdSum, $VatAm, $DNOrdSum, $DVatAm, $OrdSum, $OrdSumT, $DOrdSum, $DOrdSumT, $IncCst, $CtrAm, $AcSet, $DelActNo, $DelNm, $DelAd1, $DelAd2, $DelAd3, $DelAd4, $DelPNo, $DelPArea, $DelCtry, $DelLang, $FrStc, $ToStc, $DelTrm, $DelMt, $ConsNo, $FrAm, $PstAm, $DelDt, $CfDelDt, $Label, $OurRef, $YrRef, $ReqNo, $InvoCust, $RmtSup, $Cur, $ExRt, $PmtTrm, $DueDt, $PmtMt, $InvoPl, $FactNo, $InvoRef, $ExOrdPrc, $OrdPrSt, $InvoNo, $InvoAm, $LstSetDt, $LstInvDt, $ExPr, $EdSt, $NoteNm, $Dupl, $DelPri, $EmpNo, $EmpPrGr, $WageRtNo, $Trunc, $DTrunc, $FrAm2, $FrAm3, $FrAm4, $Package, $SpcTxAm, $DSpcTxAm, $InvoSF, $IncSF, $InvoIF, $DInvoIF, $IncIF, $CstCur, $CstExRt, $CIncCst, $CIncSF, $CIncIF, $SaleCstP, $MainOrd, $ERetDt, $TransGr2, $VoSr, $InqCnt, $PurcCnt, $OfCnt, $CfCnt, $PicCnt, $PacCnt, $ConCnt, $InvoCnt, $DtyFrAm, $DDtyFrAm, $ArDt, $DifProd, $DCtrAm, $CustPrG2, $CreDt, $CreUsr, $PrSup, $SPrAdd, $OrdPref, $CreTm, $NWgt, $Tare, $Lgt, $Area, $Vol, $CSOrdNo, $FinBy, $NoUn, $Gr7, $Gr8, $Gr9, $Gr10, $Gr11, $Gr12, $TransGr3, $TransGr4, $ConNo, $PacNo, $PicNo, $CfNo, $OfNo, $PurcNo, $InqNo, $EDISt, $OrdBal, $DOrdBal, $LiaActNo, $FCfDelDt, $RlDelDt, $OrdPrSt2, $TestRes, $DocSMt, $IgnTest, $FrCusPro, $FinDt, $ProdNo, $ProdCnt, $GWgtTot, $GWgtCoSF, $OvCNOSm, $Free1, $Free2, $Free3, $Free4, $DepDt, $CustPrG3) = split("\" \"", $linje);

			$OrdNo=substr($OrdNo,1);	
			
			if ($Ctry=='0') $Ctry='';
			elseif ($Ctry=='1') $Ctry='USA';
			elseif ($Ctry=='31') $Ctry='NL';
			elseif ($Ctry=='34') $Ctry='E';
			elseif ($Ctry=='43') $Ctry='A';
			elseif ($Ctry=='44') $Ctry='UK';
			elseif ($Ctry=='45') $Ctry='DK';
			elseif ($Ctry=='46') $Ctry='S';
			elseif ($Ctry=='47') $Ctry='N';
			elseif ($Ctry=='49') $Ctry='D';
			elseif ($Ctry=='358')$Ctry='FI';

			if ($CustNo) {
				if ($r1=db_fetch_array(db_select("select id from adresser where kontonr='$CustNo'"))) $konto_id=$r1['id'];
				else $konto_id='0';
			}
			if ($CustNo==250) $art="PO";
			else $art="DO";

			$Nm=str_replace("'", "\\'", $Nm);
			$Ad1=str_replace("'", "\\'", $Ad1);
			$Ad2=str_replace("'", "\\'", $Ad2);

			$aar=substr($OrdDt,0,4);
			$md=substr($OrdDt,4,2);
			$dato=substr($OrdDt,6,2);
			$OrdDt=$aar."-".$md."-".$dato;
		

			$CrLmt=$CrLmt*1;
			$InvoAm=$InvoAm*1;
			if ($NoUn!=0) $status=1;
			else $status=3;
			
			if (($Nm)&&($CustNo)&&(!$InvoNo)) {
				db_modify("INSERT INTO ordrer (konto_id, ordrenr, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, notes, cvrnr, art, ordredate, sum, status) values ('$konto_id', '$OrdNo', '$CustNo', '$Nm', '$Ad1', '$Ad2', '$PNo', '$PArea', '$Ctry', '$Pers', '$notes', '$CM', '$art', '$OrdDt', '$InvoAm', '$NoUn')");
			}
		}
		if ($x>$slutlinje) break;
	}
} 
fclose($fp);
echo "$x ordrer importeret<br>";
transaktion("commit");
if ($x > $slutlinje) {
	$slutlinje++;
	print "<meta http-equiv=\"refresh\" content=\"0;URL=import_visma_ordrer.php?start=$slutlinje\">";
}
?>