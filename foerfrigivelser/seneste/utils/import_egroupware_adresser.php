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
$fp=fopen("../../tidsreg/dumps/2006-06-27/Actor.txt","r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$x++;
		$linje=fgets($fp);
		if (($x>=$startlinje) && ($x<$slutlinje) && (substr($linje,0,1)=='"')) { 
			
			list($ActNo, $Nm, $Ad1, $Ad2, $Ad3, $Ad4, $PNo, $PArea, $Ctry, $Lang, $Shrt, $MailAd, $Phone, $PrivPh, $MobPh, $Pers, $Fax, $CustNo, $NwCustNo, $SupNo, $NwSupNo, $EmpNo, $NwEmpNo, $Title, $LiaAct, $Usr, $NwUsr, $Seller, $Buyer, $Rsp, $Att, $R1, $R2, $R3, $R4, $R5, $R6, $CrLmt, $CrSusp, $CrEval, $CustAcNo, $SupAcNo, $AgAcNo, $CustMaMt, $SupMaMt, $CAcSet, $SAcSet, $CVatNo, $SVatNo, $ExVat, $ExSpc, $DelToAct, $DelFrAct, $CStc, $SStc, $DelPri, $CDelMt, $SDelMt, $CDelTrm, $SDelTrm, $Cur, $CustPrGr, $EmpPrGr, $CustTotD, $SupTotD, $InvoCust, $FactNo, $CPmtTrm, $SPmtTrm, $CPmtMt, $SPmtMt, $BGiro, $PGiro, $DebFrDt, $DebToDt, $MaxRems, $LstRemDt, $LstRemNo, $IntRt, $RmtPri, $RmtSup, $SwftCd, $SwftAd1, $SwftAd2, $SwftAd3, $SwftAd4, $OurCNo, $InfCat, $Trade, $Distr, $BsNo, $NoOfEmp, $Turn, $Gr, $Gr2, $Gr3, $Gr4, $Gr5, $Gr6, $Inf, $Inf2, $Inf3, $Inf4, $Inf5, $Inf6, $FrDt, $ToDt, $SrcNo, $NoteNm, $SAgAcNo, $CreDt, $CreUsr, $Branch, $DirDeb, $MaxDebAm, $CustPrG2, $PrSup, $SPrAdd, $CustPref, $SupPref, $Gr7, $Gr8, $Gr9, $Gr10, $Gr11, $Gr12, $Inf7, $Inf8, $YrRef, $Fax2, $EUTaxNo, $AdmTm, $DelTm, $TanspTm, $DocSMt, $DelIntv, $LstDelDt, $MainAct, $MaxDueP, $MaxDueDy, $AcDocSMt, $SOLink, $CryptK, $ActPro, $NwCNo, $NwSNo, $PictFNm, $TspDy, $LstSuit, $ExtID, $ChExt, $IntAd1, $IntAd2, $OldCNo, $OldSNo, $EANLocCd, $OlAcNo, $ClReAcNo, $ClBaAcNo, $CustPrG3) = split("\" \"", $linje);
			
/*			
			$r1=db_fetch_array(db_select("select id from varer where varenr='$indg_i'"));
			$indgaar_i=$r1['id'];
			$r1=db_fetch_array(db_select("select id from varer where varenr='$varenr'"));
			$vare_id=$r1['id'];
*/
			$notes='';
			if ($inf) $notes.=$inf."\n";
			if ($inf2) $notes.=$inf2."\n";
			if ($inf3) $notes.=$inf3."\n";
			if ($inf4) $notes.=$inf4."\n";
			if ($inf5) $notes.=$inf5."\n";
			if ($inf6) $notes.=$inf6."\n";
			if ($inf7) $notes.=$inf7."\n";
			if ($inf8) $notes.=$inf8."\n";

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

			$Nm=str_replace("'", "\\'", $Nm);
			$Ad1=str_replace("'", "\\'", $Ad1);
			$Ad2=str_replace("'", "\\'", $Ad2);

			if (($Nm)&&($CustNo)) {
#				echo "INSERT INTO adresser (firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf, fax, email, notes, kreditmax, cvrnr, art, gruppe) values ('$Nm', '$Ad1', '$Ad2', '$PNo', '$PArea', '$Ctry', '$Pers', '$Phone', '$Fax', '$MailAd', '$notes', '$CrLmt', '$CM', 'D', '1' )<br>";
				db_modify("INSERT INTO adresser (kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf, fax, email, notes, kreditmax, cvrnr, art, gruppe) values ('$CustNo', '$Nm', '$Ad1', '$Ad2', '$PNo', '$PArea', '$Ctry', '$Pers', '$Phone', '$Fax', '$MailAd', '$notes', '$CrLmt', '$CM', 'D', '1' )");
			}
			if (($Nm)&&($SupNo)) {
#				echo "INSERT INTO adresser (firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf, fax, email, notes, kreditmax, cvrnr, art, gruppe) values ('$Nm', '$Ad1', '$Ad2', '$PNo', '$PArea', '$Ctry', '$Pers', '$Phone', '$Fax', '$MailAd', '$notes', '$CrLmt', '$CM', 'K', '1' )<br>";
				db_modify("INSERT INTO adresser (kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf, fax, email, notes, kreditmax, cvrnr, art, gruppe) values ('$SupNo', '$Nm', '$Ad1', '$Ad2', '$PNo', '$PArea', '$Ctry', '$Pers', '$Phone', '$Fax', '$MailAd', '$notes', '$CrLmt', '$CM', 'K', '1' )");
			}
		}
		if ($x>$slutlinje) break;
	}
} 
fclose($fp);
echo "$x adresser importeret uden fejl<br>";
transaktion("commit");
if ($x > $slutlinje) {
	$slutlinje++;
	print "<meta http-equiv=\"refresh\" content=\"0;URL=import_visma_adresser.php?start=$slutlinje\">";
}
?>