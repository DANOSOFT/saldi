<?php
// --- includes/bilag.php --- patch 4.0.7 --- 2023.03.04 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2010-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20140112 Tilføjet ordre som kilde.
// 20140122 Rettet if til elseif dat man ellers kommer tilbage til historik ved opslag fra kassekladde. Søg 2014.01.22
// 20141105 Flyttet tjek op i "egen FTP"  #20141105
// 20141106 Sletter nu også bilag når der ikke er en descfil
// 20150105 Ganger bilag med 1 for at sikre at der er en værdi til indsættelse - 20150105
// 20150423 Indsat break ved upload fejlet, så bilag ikke sættes i tabel.
// 20160116 ændret mkdir mv osv fra systemkommandoer til PHP kommandoer
// 20210208 PHR Corrected error handling
// 20210714 LOE Translated some texts 
// 20220715 PHR Some changes in vis_bilag
// 20230118 PHR k_type and invioce number lost when inserting from poll
// 20230123 PHR Corrected error if text in debet or credit
// 20230304 PHR	Attachments can now be renamed

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Kassebillag";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">";
$filnavn = NULL;

if(($_GET)||($_POST)) {

	$funktion=if_isset($_GET['funktion']);
	if (isset($_GET['kilde_id'])) {
		$kilde_id = $_GET['kilde_id'];
		$kilde    = if_isset($_GET['kilde']);
		$bilag_id = if_isset($_GET['bilag_id']);
		$bilag    = if_isset($_GET['bilag']);
		$fokus    = if_isset($_GET['fokus']);
		$ny       = if_isset($_GET['ny']);
		$vis      = if_isset($_GET['vis']);
		$filnavn  = if_isset($_GET['filnavn']);
	} 
	if (isset($_POST['kilde_id']) && $_POST['kilde_id']) {
		$submit      = if_isset($_POST['submit']);
		$kilde_id    = $_POST['kilde_id'];
		$kilde       = $_POST['kilde'];
		$bilag_id    = $_POST['bilag_id'];
		$bilag       = $_POST['bilag'];
		$fokus       = $_POST['fokus'];
	}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td colspan= \"3\" height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	if ($kilde=="kassekladde") $tmp="../finans/kassekladde.php?kladde_id=$kilde_id&fokus=$fokus";
	elseif ($kilde=="ordrer") $tmp="../debitor/ordre.php?id=$kilde_id&fokus=$fokus"; #20140122
	else $tmp="../debitor/historikkort.php?id=$kilde_id";
	if (file_exists("../documents")) $nfs_mappe='documents';
	elseif (file_exists("../owncloud")) $nfs_mappe='owncloud';
	elseif (file_exists("../bilag")) $nfs_mappe='bilag';
	else {
		$alerttxt = __line__." ".findtekst(1409, $sprog_id);
		alert ($alerttxt);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">";
		exit;
	}
	
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$tmp accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst(1408, $sprog_id)." $bilag</td>";
	print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>\n";

	$xalert=__line__." ".findtekst(1410, $sprog_id);
	
	if (isset($_POST['indsaet_bilag'])) {
		upload_bilag($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$funktion,$nfs_mappe);
		exit;
	}
	if (isset($_POST['pulje'])||$funktion=='gennemse') {
		$puljefil=if_isset($_GET['puljefil']);
		gennemse($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$puljefil,$nfs_mappe);
	}
	
	if ($filnavn) {
		vis_bilag($kilde_id,$kilde,$bilag_id,$fokus,$filnavn,$nfs_mappe);
	} elseif ($filnavn=basename($_FILES['uploadedfile']['name'])) {
		$filtype=strtolower(substr($filnavn,-4));
		if ($kilde=='ordrer' && $filtype!='.pdf'){

			print "<BODY onLoad=\"javascript:alert('$xalert')\">";
			upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus);
		}
	#		$filnavn=htmlentities($filnavn,ENT_COMPAT,$charset);
		$tmp="../temp/".$db."/".$filnavn;
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'],"$tmp")) {
			upload_bilag($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$funktion,$nfs_mappe);
		}	else {
			echo findtekst(1370, $sprog_id);
			upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$nfs_mappe);
		}
	} else upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$nfs_mappe);
}
print "</tbody></table>";
################################################################################################################
function upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$nfs_mappe){
global $charset;
global $sprog_id;
	print "<tr><td width=100% align=center><table width=\"500px\" height=\"200px\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	print "<tr><td width=100% align=center>".findtekst(1411, $sprog_id)."</td></tr>\n";
	print "<tr><td width=100% align=center><br></td></tr>\n";
	if ($kilde=='kassekladde') {
		$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
		if($box6=$r['box6']) {
			print "<form name=\"pulje\" action=\"bilag.php?sort=$sort&kilde=$kilde\"; method=\"post\">";
			print "<input type=\"hidden\" name=\"kilde_id\" value=$kilde_id>";
			print "<input type=\"hidden\" name=\"kilde\" value=$kilde>";
			print "<input type=\"hidden\" name=\"bilag_id\" value=$bilag_id>";
			print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
			print "<input type=\"hidden\" name=\"fokus\" value=$fokus>";
			print "<tr><td width=100% align=center> ".findtekst(1412, $sprog_id).": <input class=\"inputbox\" name=\"pulje\" type=\"submit\" value=\"".findtekst(1413, $sprog_id)."\"/><br /></td></tr>\n";
			print "</form>";
		}
	}
	print "<form enctype=\"multipart/form-data\" action=\"bilag.php\" method=\"POST\">\n";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000000\">\n";
	print "<input type=\"hidden\" name=\"kilde_id\" value=$kilde_id>\n";
	print "<input type=\"hidden\" name=\"kilde\" value=$kilde>\n";
	print "<input type=\"hidden\" name=\"bilag_id\" value=$bilag_id>\n";
	print "<input type=\"hidden\" name=\"bilag\" value=$bilag>\n";
	print "<input type=\"hidden\" name=\"fokus\" value=$fokus>\n";
	print "<tr><td width=100% align=center> ".findtekst(1414, $sprog_id).": <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td align=center><input type=\"submit\" value=\"".findtekst(1078, $sprog_id)."\" /></td></tr>\n";
	print "<tr><td></form></td></tr>\n";
}

function upload_bilag($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$funktion,$nfs_mappe){
	global $charset;
	global $db;
	global $bruger_id;
	global $exec_path;
	#global $sprog_id 

	$readOnly=0;
	$puljefil=if_isset($_POST['puljefil']);

	if (!isset($exec_path)) $exec_path="/usr/bin";
	
	if ($puljefil || file_exists("../temp/$db/$filnavn")) {
		$x=0;
		if (!file_exists("../".$nfs_mappe."/".$db)) {
		  mkdir ("../".$nfs_mappe."/".$db,0777);
		  if (!file_exists("../".$nfs_mappe."/".$db)) {
				print tekstboks("Det er sket en fejl, bilag ikke gemt\nRing venligst på 46902208 så problemet kan blive løst");
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
				exit;
			}
	  }
		$bilagfilnavn="bilag_".$bilag_id;
		
		$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
		if($box6=$r['box6']) {
			$puljefil=if_isset($_POST['puljefil']);
			$bilag=if_isset($_POST['bilag']);	
			$dato=if_isset($_POST['dato']);
			$beskrivelse=if_isset($_POST['beskrivelse']);
			$debet=if_isset($_POST['debet']);
			$kredit=if_isset($_POST['kredit']);
			$fakturanr=if_isset($_POST['fakturanr']);
			$sum=if_isset($_POST['sum']);
			$sag=if_isset($_POST['sag']);
			$afd=if_isset($_POST['afd']);
			$projekt=if_isset($_POST['projekt']);
			if ($kilde=="kassekladde" || $kilde=="ordrer") {
				$mappe='bilag';
				if (!file_exists("../".$nfs_mappe."/".$db."/".$mappe)) {
					mkdir ("../".$nfs_mappe."/".$db."/".$mappe,0777);
				}
				$tidspkt=date("U");
				if ($kilde=="kassekladde") {
					if (!$bilag_id) {
						$bilag*=1; # 20150105
						db_modify("insert into kassekladde (bilag,beskrivelse,kladde_id) values ('$bilag','$tidspkt','$kilde_id')",__FILE__ . " linje " . __LINE__);
						$r=db_fetch_array(db_select("select id from kassekladde where bilag='$bilag' and beskrivelse='$tidspkt' and kladde_id='$kilde_id'",__FILE__ . " linje " . __LINE__));
						$bilag_id=$r['id'];
					}
					$undermappe="kladde_$kilde_id";
					if (!file_exists("../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe)) {
						mkdir ("../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe,0777);
					} 
				} else {
					$undermappe="ordrer";
					if (!file_exists("../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe)) {
						mkdir ("../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe,0777);
					} 
				}
				$bilagfilnavn="bilag_".$bilag_id;
			} else {
				$mappe='dokumenter';
				if (!file_exists("../".$nfs_mappe."/".$db."/".$mappe)) {
					mkdir ("../".$nfs_mappe."/".$db."/".$mappe,0777);
				}
				$undermappe="debitor_$kilde_id";
				if (!file_exists("../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe)) mkdir ("../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe,0777);
				$bilagfilnavn="doc_".$bilag_id;
			}
			if ($puljefil) $fra="../".$nfs_mappe."/".$db."/pulje/".$puljefil;
			else $fra="../temp/".$db."/".$filnavn;
			$til="../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe."/".$bilagfilnavn;
			system ("mv '$fra' '$til'\n");
			if (file_exists("../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe."/".$bilagfilnavn)) $tjek='ok';
			else {
				$alerttxt = __line__." indlæsning af $filnavn fejlet";
				print "<BODY onLoad=\"javascript:alert($alerttxt)\">\n";
				return;
			}
			if ($dh = opendir("../".$nfs_mappe."/".$db."/pulje/")) {
				$slettet=0;	
				while (($file = readdir($dh)) !== false) {
					if ($slettet==0 && substr($file,-5)=='.desc') {
						$descfil=str_replace("desc","",$file);
						if (substr($puljefil,0,strlen($descfil))==$descfil) {
							system ("rm ../".$nfs_mappe."/".$db."/pulje/".$descfil."desc\n");
							$slettet=1;
						}
					}
				}
				closedir($dh);
			}
			if ($puljefil) $filnavn=$puljefil;
#			else $filnavn=$bilagfilnavn;
#			if (isset($til)) $langt_filnavn=$til;
			if (!$bilag) $bilag = 0;
			if (!$dato) $dato=date("d-m-Y");
			if (!is_numeric($debet)) {
				if (strtolower(substr($debet,0,1))=='d') $d_type='D';
				elseif (strtolower(substr($debet,0,1))=='k') $d_type='K';
				else $d_type='F';
				$debet=substr($debet,1);
			}
			if (!is_numeric($debet)) {
				$tmp = 'K'.$bilag_id;
				$_SESSION[$tmp]=$debet;
				$debet = 0;
			}
			if (!$debet) $debet=0;
			if (!is_numeric($kredit)) {
				if (strtolower(substr($kredit,0,1))=='d') $k_type='D';
				elseif (strtolower(substr($kredit,0,1))=='k') $k_type='K';
				else $k_type='F';
				$kredit=substr($kredit,1);
			}
			if (!is_numeric($kredit)) {
				$tmp = 'K'.$bilag_id;
				$_SESSION[$tmp]=$kredit;
				$kredit = 0;
			}
			if (!$kredit) $kredit=0;
			if (!$afd) $afd=0;
			$qt1 = "update $kilde set ";
			if ($kilde=="kassekladde") {
				if ($bilag_id) {
					$qt2 = "select bogfort from kladdeliste,kassekladde where kassekladde.id='$bilag_id' ";
					$qt2.= "and kladdeliste.id = kassekladde.kladde_id";
					$r=db_fetch_array(db_select($qt2,__FILE__ . " linje " . __LINE__));
					($r['bogfort'] != '-')?$readOnly=1:$readOnly=0; 
				}
				if ($puljefil && $readOnly == 0) {
					$qt1.= "bilag='$bilag' ";
					$qt1.= ",transdate='".usdate($dato)."',beskrivelse='".db_escape_string($beskrivelse)."',";
					$qt1.= "d_type='$d_type',debet='$debet',k_type='$k_type',kredit='$kredit',faktura='$fakturanr',";
					$qt1.= "amount='".usdecimal($sum)."',afd='$afd',projekt='$projekt',";
				}
			}
			$qt1.= "dokument='".db_escape_string($filnavn)."' where id = '$bilag_id'";
#cho __line__." $qt1<br>";
#xit;			
			db_modify($qt1,__FILE__ . " linje " . __LINE__);
		} else { #Egen FTP'
			$box1=$r['box1'];
			$box2=$r['box2'];
			$box3=$r['box3'];
		if ($kilde=="kassekladde" || $kilde=="ordrer") {
				$mappe=$r['box4'];
				($kilde=="kassekladde")?$undermappe="kladde_$kilde_id":$undermappe="ordrer";
				$bilagfilnavn="bilag_".$bilag_id;
			} else {
				$mappe=$r['box5'];
				$undermappe="debitor_$kilde_id";
				$bilagfilnavn="doc_".$bilag_id;
			}
			$fp=fopen("../temp/$db/ftpscript1.$bruger_id","w");
			if ($fp) {
				fwrite ($fp, "mkdir $mappe\ncd $mappe\nmkdir $undermappe\ncd $undermappe\nput $bilagfilnavn\nbye\n");
			}
			fclose($fp);
#cho "$bilagfilnavn<br>\n";			
			$fp=fopen("../temp/$db/ftplog","w");
			fwrite ($fp, "cd ../temp/$db\n\rmv \"$filnavn\" \"$bilagfilnavn\"\n\r$exec_path/ncftp ftp://$box2:$box3@$box1\n\rrm $bilagfilnavn\n\r");
			fclose($fp);
			$kommando="cd ../temp/$db\nmv \"$filnavn\" \"$bilagfilnavn\"\n$exec_path/ncftp ftp://".$box2.":'".$box3."'@".$box1." < ftpscript1.$bruger_id >> ftplog\nrm $bilagfilnavn\n";#rm ftpscript.$bruger_id";
			system ($kommando);
			$fp=fopen("../temp/$db/ftpscript2.$bruger_id","w");
			if ($fp) {
				fwrite ($fp, "cd $mappe\ncd $undermappe\nget $bilagfilnavn\nbye\n");
			}
			fclose($fp);
			$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":'".$box3."'@".$box1." < ftpscript2.$bruger_id >> ftplog\n";
			system ($kommando);
			$langt_filnavn="../temp/$db/".$bilagfilnavn;
			if (file_exists($langt_filnavn)) {
				db_modify("update $kilde set dokument='".db_escape_string($filnavn)."' where id='$bilag_id'",__FILE__ . " linje " . __LINE__);
			}
			if (file_exists($langt_filnavn)) { #20141105
				print "<BODY onLoad=\"javascript:alert('$filnavn er indl&aelig;st')\">\n";
			} else {
				alert("Indlæsning af $filnavn fejlet");
			}
		}
	} #print "<BODY onLoad=\"javascript:alert('B indl&aelig;sning af $filnavn fejlet')\">\n";
/*
	if ($funktion=='gennemse') {
		$bilag++;
		$tmp="../includes/bilag.php?bilag=$bilag&kilde=$kilde&kilde_id=$kilde_id&fokus=$fokus&funktion=gennemse";
	}
	else
*/		
	if ($kilde=="kassekladde") $tmp="../finans/kassekladde.php?kladde_id=$kilde_id&fokus=$fokus";
	elseif ($kilde=="ordrer") $tmp="../debitor/ordre.php?id=$kilde_id&fokus=$fokus";
	else $tmp="../debitor/historikkort.php?id=$kilde_id";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
}

function vis_bilag($kilde_id,$kilde,$bilag_id,$fokus,$filnavn,$nfs_mappe){

	global $charset;
	global $db;
	global $bruger_id;
	global $exec_path;

	$readOnly=0;
	
	if (!isset($exec_path)) $exec_path="/usr/bin";
	$r=db_fetch_array(db_select("select * from kassekladde where id='$bilag_id'",__FILE__ . " linje " . __LINE__));

	
	$bilagfilnavn="bilag_".$bilag_id;
	$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
	if($box6=$r['box6']) {
		if ($kilde=="kassekladde" || $kilde=="ordrer") {
			$mappe='bilag';
			($kilde=="kassekladde")?$undermappe="kladde_$kilde_id":$undermappe="ordrer";
			$bilagfilnavn="bilag_".$bilag_id;
		} else {
			$mappe='dokumenter';
			$undermappe="debitor_$kilde_id";
			$bilagfilnavn="doc_".$bilag_id;
		}
		$google_docs=$r['box7'];
		$fra="../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe."/".$bilagfilnavn;
		$til="../temp/".$db."/".$filnavn;
			system ("cp '$fra' '$til'\n");
	} else {
		$box1=$r['box1'];
		$box2=$r['box2'];
		$box3=$r['box3'];
		if ($kilde=="kassekladde" || $kilde=="ordrer") {
			$mappe=$r['box4'];
			($kilde=="kassekladde")?$undermappe="kladde_$kilde_id":$undermappe="ordrer";
			$bilagfilnavn="bilag_".$bilag_id;
		} else {
			$mappe=$r['box5'];
			$undermappe="debitor_$kilde_id";
			$bilagfilnavn="doc_".$bilag_id;
		}
		$kommando="cd $mappe\ncd $undermappe\nget $bilagfilnavn\nbye\n";
		file_put_contents("../temp/$db/ftpscript.txt", $kommando);
	
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.txt > ftplog.txt\ncp \"$bilagfilnavn\" \"$filnavn\"\n";
		file_put_contents("../temp/$db/ftpCommand.txt", $kommando);
		system ($kommando);
	}
	print "<tr><td width=100% height=100% align=\"center\" valign=\"middle\"><iframe frameborder=\"no\" width=\"100%\" height=\"100%\" scrolling=\"auto\" src=\"vis_bilag.php?filnavn=$filnavn&db=$db&bilag_id=$bilag_id&kilde_id=$kilde_id&kilde=$kilde\"></iframe></td></tr>\n";
}

function gennemse($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$puljefil,$nfs_mappe){
	global $bruger_id,$db,$exec_path;
	global $sprog_id;
	
#cho "$kilde_id,$kilde,$bilag_id,$bilag<br>";

	$afd =  $beskrivelse = $debet = $dato = $fakturanr = $kredit = $projekt = $readOnly = $sag = $sum = NULL;

	(isset($_POST['unlink']) && $_POST['unlink'])?$unlink=1:$unlink=0;
	(isset($_POST['rename']) && $_POST['rename'])?$rename=1:$rename=0;
	(isset($_POST['unlinkFile']) && $_POST['unlinkFile'])?$unlinkFile=$_POST['unlinkFile']:$unlinkFile=NULL;
	(isset($_POST['upload_bilag']) && $_POST['upload_bilag']=='inds&aelig;t')?$indsaet=1:$indsaet=0;
	$newFileName = if_isset($_POST['newFileName']);

	$descfil=if_isset($_POST['descfil']);

	if ($rename && $newFileName && $newFileName != $puljefil) {
	  $legalChars = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','x','y','z');
		array_push($legalChars,'0','1','2','3','4','5','6','7','8','9','_','-','.');
		$nfn = trim($newFileName);
		$nfn = str_replace('æ','ae',$nfn);
		$nfn = str_replace('Æ','AE',$nfn);
		$nfn = str_replace('ø','oe',$nfn);
		$nfn = str_replace('Ø','OE',$nfn);
		$nfn = str_replace('å','aa',$nfn);
		$nfn = str_replace('Å','AA',$nfn);
		$newFileName = '';
		for ($x=0;$x<strlen($nfn);$x++) {
			
			$c1=substr($nfn,$x,1);
			$c2=strtolower($c1);
			if (!in_array($c2,$legalChars)) $c1 = '_';
			$newFileName.= $c1; 
		}
		$tmpA = explode('.',$puljefil);
		if (count($tmpA) > 1) $ext = end($tmpA);
		else $ext = NULL;
		$newFileName= trim($newFileName,' ._'); 
		$tmpA = explode('.',$newFileName);
		if (count($tmpA) > 1) $newExt = end($tmpA);
		else $newExt = NULL;
	if ($bruger_id == '-1') echo "$puljefil $newExt<br>";
		if (strtolower($ext) != strtolower($newExt)) $newFileName.= ".$ext"; 
		$newFileName= trim($newFileName,' ._'); 
		rename("../".$nfs_mappe."/$db/pulje/$puljefil","../".$nfs_mappe."/$db/pulje/$newFileName"); 
		$puljefil = $newFileName;
	}
	if ($unlink && $unlinkFile) {
		if ($descfil) system("rm ../".$nfs_mappe."/$db/pulje/$descfil\n");
		if ($unlinkFile) system("rm $unlinkFile\n");
		elseif (isset($_POST['puljefil'])) {
			$puljefil=if_isset($_POST['puljefil']);
#cho "slettter ../".$nfs_mappe."/$db/pulje/$puljefil<br>";
			if ($puljefil) system("rm ../".$nfs_mappe."/$db/pulje/$puljefil\n");
		}
#exit;
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/bilag.php?bilag=$bilag&bilag_id=$bilag_id&kilde_id=$kilde_id&kilde=$kilde&fokus=$fokus&funktion=gennemse\">";
		exit;
	}
	if ($indsaet) {
		echo "indsætter $puljefil";
		exit;
	}
	$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
	$google_docs=$r['box7'];

	if ($bilag_id) {
		$r=db_fetch_array(db_select("select * from kassekladde where id='$bilag_id'",__FILE__ . " linje " . __LINE__));
		if (!$bilag) $bilag=$r['bilag'];
		$dato=dkdato($r['transdate']);
		$beskrivelse=$r['beskrivelse'];
		$debet=$r['d_type'].$r['debet'];
		$kredit=$r['k_type'].$r['kredit'];
		$fakturanr = if_isset($r['faktura']);
		$sum=dkdecimal($r['amount']);
		$qtxt = "select bogfort from kladdeliste where id='$r[kladde_id]'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		($r['bogfort'] != '-')?$readOnly=1:$readOnly=0; 
	}
	$dir="../".$nfs_mappe."/".$db."/pulje";
	$url="://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
	$url=str_replace("/includes/bilag.php","/temp/$db/pulje/",$url);
	if ($_SERVER['HTTPS']) $url="s".$url;
	$url="http".$url;
	if (!$puljefil) {
		if (is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
					if (substr($file,0,1)!='.' && substr($file,-5)!='.desc') {
						if (!$puljefil) $puljefil=$file;
					}
				}
				closedir($dh);
			}
		}
	}
	print "<form name=\"gennemse\" action=\"bilag.php?funktion=gennemse&puljefil=$puljefil\" method=\"post\">\n";
	print "<input type=\"hidden\" name=\"puljefil\" value=$puljefil>\n";
	print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>\n";
	print "<input type=\"hidden\" name=\"kilde_id\" value=$kilde_id>\n";
	print "<input type=\"hidden\" name=\"kilde\" value=$kilde>\n";
	print "<input type=\"hidden\" name=\"bilag_id\" value=$bilag_id>\n";
	print "<input type=\"hidden\" name=\"bilag\" value=$bilag>\n";
	print "<input type=\"hidden\" name=\"fokus\" value=$fokus>\n";
	print "<tr><td width=15% height=\"70%\" align=center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>\n";
	print "<tr><td width=100% align=center>\n";
	$fil_nr=0;
#cho "$bilag_id<br>";
		if (is_dir($dir)) {
			$files = scandir($dir);
			sort($files);
			foreach ($files as $file) {
				if ($file != '.' && $file != '..' && substr($file,-5)!='.desc') {
# 				if (substr($file,0,1)!='.' && substr($file,-5)!='.desc') {
					if (strpos($file,' ')) {
						$newFile = str_replace (' ','_',$file);
						$from = "../".$nfs_mappe."/".$db."/pulje/".$file;
						$to   = "../".$nfs_mappe."/".$db."/pulje/".$newFile;
						system ("mv '$from' '$to'\n");
						$file = $newFile;
					}
					($file == $puljefil)?$bgcolor='#aaaaaa':$bgcolor='#ffffff'; 
					$fil_nr++;
					$hreftxt = "../includes/bilag.php?funktion=gennemse&kilde_id=$kilde_id&kilde=$kilde";
					$hreftxt.= "&bilag=$bilag&bilag_id=$bilag_id&dato=$dato&fokus=$fil_nr&puljefil=$file ";
					$hreftxt.= "onfocus=\"document.forms[0].fokus.value=this.name;\" id=\"$fil_nr\"";
					print "<tr><td bgcolor=\"$bgcolor\">";
					print "<a href=$hreftxt>$file</a></td></tr>\n";
				}
			}
#			closedir($dh);
		}
#	}
	if ($puljefil) {
		$tmp="../../../".$nfs_mappe."/$db/pulje/$puljefil";
		if (!is_dir("../temp/$db/pulje")) mkdir("../temp/$db/pulje"); 
		system("cd ../temp/$db/pulje\nrm *\ncp $tmp .\n");
	} else {
	$ccalert= __line__." ".findtekst(1416, $sprog_id);
		print "<BODY onLoad=\"javascript:alert('$ccalert')\">\n";
		if ($kilde=="kassekladde") $tmp="../finans/kassekladde.php?kladde_id=$kilde_id&fokus=$fokus";
		elseif ($kilde=="ordrer") $tmp="../debitor/ordre.php?id=$kilde_id&fokus=$fokus";
		else $tmp="../debitor/historikkort.php?id=$kilde_id";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
		exit;
	}
	print "</td></tr>\n";
	print "<tr><td width=100% align=center><br></td></tr>\n";
	print "</table></td>\n";
	print "<td rowspan=\"2\" width=85% height=\"100%\" align=center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	print	"<tr><td width=100% align=center>";
	$corrected = 0;
	$ext = pathinfo($puljefil, PATHINFO_EXTENSION);
	$fullName = "../$nfs_mappe/$db/pulje/$puljefil";
	$descfil = $newName = str_replace($ext,'.desc',$fullName);
	if (strpos($fullName,'æ')) {
		$newName = str_replace('æ','ae',$fullName);
		exec("mv \"$fullName\" \"$newName\"\n"); 
		$fullName = $newName ; 
		$corrected = 1;
	}
	if (strpos($fullName,'ø')) {
		$newName = str_replace('ø','oe',$fullName);
		exec("mv \"$fullName\" \"$newName\"\n"); 
		$fullName = $newName ; 
		$corrected = 1;
	}
	if (strpos($fullName,'å')) {
		$newName = str_replace('å','aa',$fullName);
		exec("mv \"$fullName\" \"$newName\"\n"); 
		$fullName = $newName ; 
		$corrected = 1;
	}
	if (strpos($fullName,'Æ')) {
		$newName = str_replace('Æ','AE',$fullName);
		exec("mv \"$fullName\" \"$newName\"\n"); 
		$fullName = $newName ; 
		$corrected = 1;
	}
	if (strpos($fullName,'Ø')) {
		$newName = str_replace('Ø','OE',$fullName);
		exec("mv \"$fullName\" \"$newName\"\n"); 
		$fullName = $newName ; 
		$corrected = 1;
	}
	if (strpos($fullName,'Å')) {
		$newName = str_replace('Å','AA',$fullName);
		exec("mv \"$fullName\" \"$newName\"\n"); 
		$fullName = $newName ; 
		$corrected = 1;
	}
	if (strpos($fullName,'(')) {
		$newName = str_replace('(','_',$fullName);
		exec("mv \"$fullName\" \"$newName\"\n"); 
		$fullName = $newName ; 
		$corrected = 1;
	}
	if (strpos($fullName,')')) {
		$newName = str_replace(')','_',$fullName);
		exec("mv \"$fullName\" \"$newName\"\n"); 
		$fullName = $newName ; 
		$corrected = 1;
	}
	if (strpos($fullName,'?')) {
		$newName = str_replace('?','_',$fullName);
		exec("mv \"$fullName\" \"$newName\"\n"); 
		$fullName = $newName ; 
		$corrected = 1;
	}
	if (!$ext) {
		$newName = $fullName.'.pdf';
		exec("mv \"$fullName\" \"$newName\"\n"); 
		$fullName = $newName ;
		$puljefil = $puljefil.'.pdf';
		$corrected = 1;
	}
	if (strtolower($ext) != 'pdf') {
		$choices = array('bmp','jpg','jpeg','png','tif','tiff');
#		$tmp=str_replace($fullName);
		if (in_array(strtolower($ext),$choices)) {
			$fs = filesize($fullName);
			if ($fs > 500000) {
				$reduce = round (50000000 / $fs, 0);
				exec("$exec_path/mogrify -resize $reduce% $fullName");
			}
			$newName =  str_replace($ext,'pdf',$fullName);
			$puljefil = str_replace($ext,'pdf',$puljefil);
			exec("$exec_path/convert $fullName $newName");
			if (file_exists($newName))  {
				if (filesize($newName) > 10) {
					unlink($fullName);
					$fullName = $newName;
				} else unlink($newName);
			} 
			$corrected = 1;
		} 
	}
#	if ($bruger_id == '-1') {
#		echo $corrected;
#		exit;
#	}
	if ($corrected == '1') {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/bilag.php?bilag=$bilag&bilag_id=$bilag_id&kilde_id=$kilde_id&kilde=$kilde&fokus=$fokus&funktion=gennemse&puljefil=\">";
	}
	if ($puljefil) {
		if ($google_docs) $src="http://docs.google.com/viewer?url=$fullName&embedded=true";
		else $src=$tmp;
		print "<iframe style=\"width:100%;height:100%\" src=\"$fullName\" frameborder=\"0\">";
		print "</iframe></td></tr>\n";
	}
	print "</tbody></table></td></tr>\n";
	print "<tr><td><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	$tmp="../../".$dir."/".$descfil.".desc";
	if (file_exists($tmp)) {
		system("cd ../temp/$db/pulje\ncp $tmp .\n");
		$fp=fopen("../temp/$db/pulje/$descfil.desc","r");
		while($linje=trim(fgets($fp))) {
			if (strtolower(substr($linje,0,6))=='bilag:') $bilag=trim(substr($linje,6));
			if (strtolower(substr($linje,0,5))=='dato:') $dato=trim(substr($linje,5));
			if (strtolower(substr($linje,0,12))=='beskrivelse:') $beskrivelse=trim(substr($linje,12));
			if (strtolower(substr($linje,0,6))=='debet:') $debet=trim(substr($linje,6));
			if (strtolower(substr($linje,0,7))=='kredit:') $kredit=trim(substr($linje,7));
			if (strtolower(substr($linje,0,10))=='fakturanr:') $fakturanr=trim(substr($linje,10));
			if (strtolower(substr($linje,0,4))=='sum:') $sum=trim(substr($linje,4));
			if (strtolower(substr($linje,0,4))=='sag:') $sag=trim(substr($linje,4));
			if (strtolower(substr($linje,0,4))=='afd:') $afd=trim(substr($linje,4));
			if (strtolower(substr($linje,0,8))==='projekt:') $projekt=trim(substr($linje,8));
		}
	}
	if (!$bilag && $bilag!='0') {
		$r=db_fetch_array(db_select("select max(bilag) as bilag from kassekladde where kladde_id='$kilde_id'",__FILE__ . " linje " . __LINE__));
		$bilag=$r['bilag']+1;
	}
	if (!$dato) $dato=date("d-m-Y");
	print "<tr><td>Filnavn</td><td><input type=\"text\" style=\"width:150px\" 
	name=\"newFileName\" value=\"$puljefil\"</td></tr>\n";	
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\" 
	name=\"rename\" value=\"Ret filnavn\"</tr>\n";	
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\" 
	name=\"indsaet_bilag\" value=\"".findtekst(1415, $sprog_id)."\"</tr>\n";	
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\" 
	name=\"unlink\" value=\"".findtekst(1099, $sprog_id)."\"</tr>\n";	
	print "<tr><td>Bilag&nbsp;</td>";
	if ($readOnly) print "<td>$bilag</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"bilag\" value=\"$bilag\"</td></tr>\n";	
	print "<tr><td>Dato&nbsp;</td>";
	if ($readOnly) print "<td> $dato</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"dato\" value=\"$dato\"</td></tr>\n";	
	print "<tr><td>Beskrivelse&nbsp;</td>";
	if ($readOnly) print "<td> $beskrivelse</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"beskrivelse\" value=\"$beskrivelse\"</td></tr>\n";	
	print "<tr><td>Debet&nbsp;</td>";
	if ($readOnly) print "<td> $debet</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"debet\" value=\"$debet\"</td></tr>\n";	
	print "<tr><td>Kredit&nbsp;</td>";
	if ($readOnly) print "<td> $kredit</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"kredit\" value=\"$kredit\"</td></tr>\n";	
	print "<tr><td>Fakturanr&nbsp;</td>";
	if ($readOnly) print "<td> $fakturanr</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"fakturanr\" value=\"$fakturanr\"</td></tr>\n";	
	 print "<tr><td>Sum&nbsp;</td>";
	if ($readOnly) print "<td> $sum</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"sum\" value=\"$sum\"</td></tr>\n";	
	print "<tr><td>Sag&nbsp;</td>";
	if ($readOnly) print "<td> $sag</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"sag\" value=\"$sag\"</td></tr>\n";	
	print "<tr><td>Afd&nbsp;</td>";
	if ($readOnly) print "<td> $afd</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"afd\" value=\"$afd\"</td></tr>\n";	
	print "<tr><td>Projekt&nbsp;</td>";
	if ($readOnly) print "<td> $projekt</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"projekt\" value=\"$projekt\"</td></tr>\n";	
	print "</tbody></table></td></tr>\n";
	print "<input type=\"hidden\" style=\"width:150px\" name=\"unlinkFile\" value=\"$fullName\"</td></tr>\n";	
	print "<input type=\"hidden\" style=\"width:150px\" name=\"descfil\" value=\"$descfil\"</td></tr>\n";	
	print "</form>";
	print "<script language=\"javascript\">";
	print "document.gennemse.$fokus.focus();";
	print "</script>";
	exit;

} # endfunc gennemse
?>
