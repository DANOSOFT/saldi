<?php
// --- includes/docsIncludes/emailDoc.php -----patch 4.0.8 ----2023-08-01------
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
//20230727 LOE file created from bilag.php //refer to uploadDoc.php
//20230911 LOE Delete code modified

@session_start();
$s_id=session_id();
$css="../../css/standard.css";

$title="emailDoc";


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

$servername = $_SERVER['SERVER_NAME'];
$filnavn=$bilag=$archivefile= NULL;
$docFolder="bilag/".$servername;
$relativeP1 = "../../".$docFolder;

	if (isset($_GET['sourceId'])) {
		$sourceId = $_GET['sourceId'];
		if(isset($_GET['source']))   	$source  	= $_GET['source'] ;
		if(isset($_GET['bilag_id'])) 	$bilag_id 	= $_GET['bilag_id'];
	    if(isset($_GET['bilag'])) 		$bilag    	= $_GET['bilag'];
		if(isset($_GET['fokus']))		$fokus   	= $_GET['fokus'];
		if(isset($_GET['ny'])) 			$ny   		= $_GET['ny'];
		if(isset($_GET['vis'])) 		$vis      	= $_GET['vis'];
		if(isset($_GET['filnavn'])) 	$filnavn 	= $_GET['filnavn'];
		if(isset($_GET['archivefile'])) $archivefile 	= $_GET['archivefile'];
		if(isset($_GET['docFolder'])) 	$docFolder 	= $_GET['docFolder'];
		if(isset($_GET['kladde_id']))   $kladde_id   = $_GET['kladde_id'];
		if(isset($_GET['emailId']))   $emailId   = $_GET['emailId'];
	} 
	if (isset($_POST['sourceId']) && $_POST['sourceId']) {
		if(isset($_POST['submit']))  	 $submit      = $_POST['submit'];
		if(isset($_POST['sourceId']))	 $sourceId    = $_POST['sourceId'];
		if(isset($_POST['source']))		 $source      = $_POST['source'];
		if(isset($_POST['bilag_id']))	 $bilag_id    = $_POST['bilag_id'];
		if(isset($_POST['bilag']))		 $bilag       = $_POST['bilag'];
		if(isset($_POST['fokus']))		 $fokus       = $_POST['fokus'];
		if(isset($_POST['archivefile'])) $archivefile = $_POST['archivefile'];
		if(isset($_POST['kladde_id']))   $kladde_id   = $_POST['kladde_id'];
	}



	global $db,$exec_path;
	global $sprog_id;
	
#cho "$sourceId,$source,$bilag_id,$bilag<br>";

	$afd =  $beskrivelse = $debet = $dato = $fakturanr = $kredit = $projekt = $readOnly = $sag = $sum = NULL;

	(isset($_POST['unlink']) && $_POST['unlink'])?$unlink=1:$unlink=0;
	(isset($_POST['rename']) && $_POST['rename'])?$rename=1:$rename=0;
	(isset($_POST['unlinkFile']) && $_POST['unlinkFile'])?$unlinkFile=$_POST['unlinkFile']:$unlinkFile=NULL;
	(isset($_POST['upload_bilag']) && $_POST['upload_bilag']=='inds&aelig;t')?$indsaet=1:$indsaet=0;
	$newFileName = if_isset($_POST['newFileName']);
	
	$descfil=if_isset($_POST['descfil']);
	if ($source=="kassekladde"){
		if(isset($kladde_id)) $tmp = "../../finans/kassekladde.php?kladde_id=$kladde_id&bilag_id=$bilag_id&fokus=$fokus";
		else $tmp="../../finans/kladdeliste.php";
	}
	
	############
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td colspan= \"3\" height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	###########
	#***********
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$tmp accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst(1408, $sprog_id)." $bilag</td>";
	print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
	#***********

	//***************** */
	if (isset($_POST['indsaet_bilag'])) {
		if(!isset($filnavn)) $filnavn=$archivefile;
		// var_dump($sourceId,$source,$bilag_id,$bilag,$fokus,$filnavn,$relativeP1); exit;
		if(isset($kladde_id)){
			upload_bilag($sourceId,$source,$bilag_id,$bilag,$fokus,$filnavn,$relativeP1, $kladde_id);
			exit;
		}
	}

	//**************** */


	if ($rename && $newFileName && $newFileName != $archivefile) {
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
		$tmpA = explode('.',$archivefile);
		if (count($tmpA) > 1) $ext = end($tmpA);
		else $ext = NULL;
		$newFileName= trim($newFileName,' ._'); 
		$tmpA = explode('.',$newFileName);
		if (count($tmpA) > 1) $newExt = end($tmpA);
		else $newExt = NULL;
		if (strtolower($ext) != strtolower($newExt)) $newFileName.= ".$ext"; 
		$newFileName= trim($newFileName,' ._'); 
		rename($relativeP1."/$db/pulje/$archivefile",$relativeP1."/$db/pulje/$newFileName"); 
		$archivefile = $newFileName;
	}
	
	  if ($unlink && $unlinkFile) {
		
		if(isset($_POST['archivefile'])) {
			$nFile = "../../".$docFolder."/$db/pulje/$archivefile";
			if ($archivefile) system("rm $nFile\n");
			
		}

		print "<meta http-equiv=\"refresh\" content=\"0;URL=../docsIncludes/emailDoc.php?bilag=$bilag&bilag_id=$bilag_id&kladde_id=$kladde_id&sourceId=$sourceId&source=$source&fokus=$fokus\">";
		exit;
	}
	if ($indsaet) {
		echo "indsætter $archivefile";
		exit;
	}
	
	// $r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
	// $google_docs=$r['box7'];

			
			//include ('../../includes/std_func.php');
			$selected = db_select("select * from grupper where art = 'bilag'",__FILE__ . " linje " . __LINE__);
			if($selected != false){
				$r=db_fetch_array($selected);
				isset($r['box7'])? $google_docs=$r['box7']:$google_docs=null;
			}

	if ($bilag_id) {
		$r=db_fetch_array(db_select("select * from kassekladde where id='$bilag_id'",__FILE__ . " linje " . __LINE__));
		if (!$bilag) $bilag=$r['bilag'];
		$dato=dkdato(if_isset($r['transdate']));
		$beskrivelse=if_isset($r['beskrivelse']);
		$debet=if_isset($r['d_type']).if_isset($r['debet']);
		$kredit=if_isset($r['k_type']).if_isset($r['kredit']);
		$fakturanr = if_isset($r['faktura']);
		$sum=dkdecimal(if_isset($r['amount']));
		if(isset($r['kladde_id'])){
			$qtxt = "select bogfort from kladdeliste where id='$r[kladde_id]'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			(if_isset($r['bogfort']) != '-')?$readOnly=1:$readOnly=0; 
		}
	}
	
	$dir=$relativeP1."/".$db."/pulje";
	$url="://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
	$url=str_replace("/includes/emailDoc.php","/temp/$db/pulje/",$url);
	if ($_SERVER['HTTPS']) $url="s".$url;
	$url="http".$url;
	if (!$archivefile) {
		if (is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
					if (substr($file,0,1)!='.' && substr($file,-5)!='.desc') {
						if (!$archivefile) $archivefile=$file;
					}
				}
				closedir($dh);
			}
		}else{
			if (!file_exists($relativeP1))                 			mkdir ($relativeP1,0777);
			if (!file_exists("$relativeP1/$db"))        	        	mkdir ("$relativeP1/$db",0777);
			if (!file_exists("$relativeP1/$db/pulje"))        	        	mkdir ("$relativeP1/$db/pulje",0777);
		}
	}
	
	print "<form name=\"gennemse\" action=\"emailDoc.php?archivefile=$archivefile\" method=\"post\">\n";
	print "<input type=\"hidden\" name=\"archivefile\" value=$archivefile>\n";
	print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>\n";
	print "<input type=\"hidden\" name=\"sourceId\" value=$sourceId>\n";
	print "<input type=\"hidden\" name=\"source\" value=$source>\n";
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
						$newFile = trim ($file);
						$newFile = str_replace (' ','_',$file);
						$from = $relativeP1."/".$db."/pulje/".$file;
						$to   = $relativeP1."/".$db."/pulje/".$newFile;
						system ("mv '$from' '$to'\n");
						$file = $newFile;
					}
					($file == $archivefile)?$bgcolor='#aaaaaa':$bgcolor='#ffffff'; 
					$fil_nr++;
					$emailId='emailD';
					#emailDoc.php?sourceId=32&kladde_id=2&source=kassekladde&emailD=emailD&fokus=bila1&bilag_id=32
					$hreftxt = "emailDoc.php?sourceId=$sourceId&source=$source";
					$hreftxt.= "&emailD=$emailId&fokus=$fokus&kladde_id=$kladde_id&bilag_id=$bilag_id&dato=$dato&fokus=$fil_nr&archivefile=$file ";
					//$hreftxt.= "onfocus=\"document.forms[0].fokus.value=this.name;\" id=\"$fil_nr\"";
					print "<tr><td bgcolor=\"$bgcolor\">";
					print "<a href=$hreftxt>$file</a></td></tr>\n";
				}
			}
#			closedir($dh);
		}
#	}
			if ($archivefile) {
				$tmp="$dir/$archivefile";
				if (!is_dir("../temp/$db/pulje")) mkdir("../temp/$db/pulje"); 
				system("cd ../temp/$db/pulje\nrm *\ncp $tmp .\n");
			} else {
				$ccalert= __line__." ".findtekst(1416, $sprog_id);
				print "<BODY onLoad=\"javascript:alert('$ccalert')\">\n";
				if ($source=="kassekladde") $tmp="../../finans/kassekladde.php?kladde_id=$kladde_id&bilag_id=$bilag_id&fokus=$fokus\">";
				elseif ($source=="ordrer") $tmp="../debitor/ordre.php?id=$sourceId&fokus=$fokus";
				else $tmp="../debitor/historikkort.php?id=$sourceId";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
				exit;
			}
		


		print "</td></tr>\n";
		print "<tr><td width=100% align=center><br></td></tr>\n";
		print "</table></td>\n";
		print "<td rowspan=\"2\" width=85% height=\"100%\" align=center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
		print	"<tr><td width=100% align=center>";
	$corrected = 0;
	$ext = pathinfo($archivefile, PATHINFO_EXTENSION);
	$fullName = "../$docFolder/$db/pulje/$archivefile";
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
			exec("$exec_path/convert $fullName $newName");
			if (file_exists($newName))  {
				if (filesize($newName) > 10) {
					unlink($fullName);
					$fullName = $newName;
				} else unlink($newName);
			} 
		} 
		$corrected = 1;
	}
	if ($corrected == '1') {

		if ($source=="kassekladde") $tmp="../../finans/kassekladde.php?kladde_id=$kladde_id&bilag_id=$bilag_id&fokus=$fokus";
				
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
		exit;
		#print "<meta http-equiv=\"refresh\" content=\"0;URL=../docsIncludes/emailDoc.php?bilag=$bilag&bilag_id=$bilag_id&sourceId=$sourceId&source=$source&fokus=$fokus\">";
	}
	if ($archivefile && !$emailId) {
		if ($google_docs) $src="http://docs.google.com/viewer?url=$fullName&embedded=true";
			
			//print "<a href = 'documents.php?source=$source&sourceId=$sourceId&fokus=$fokus&showDoc=".urlencode("$r[filepath]/$r[filename]")."'>";
			//print "<a href = 'documents.php?source=$source&sourceId=$sourceId&fokus=$fokus&showDoc=".urlencode("$fullName")."'>";
		else $src=$tmp;
		print "<iframe style=\"width:100%;height:100%\" src=\"$src\" frameborder=\"0\">";
		print "</iframe></td></tr>\n";
	}
	if($emailId){
		$src=$tmp;
		print "<iframe style=\"width:100%;height:100%\" src=\"$src\" frameborder=\"0\">";
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
		$r=db_fetch_array(db_select("select max(bilag) as bilag from kassekladde where kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__));
		$bilag=$r['bilag']+1;
	}
	if (!$dato) $dato=date("d-m-Y");
	print "<tr><td>Filnavn</td><td><input type=\"text\" style=\"width:150px\" name=\"newFileName\" value=\"$archivefile\"</td></tr>\n";	
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\" name=\"rename\" value=\"Ret filnavn\"</tr>\n";	
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
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\" 
	name=\"indsaet_bilag\" value=\"".findtekst(1415, $sprog_id)."\"</tr>\n";	
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\" 
	name=\"unlink\" value=\"".findtekst(1099, $sprog_id)."\"</tr>\n";	
	print "</tbody></table></td></tr>\n";
	print "<input type=\"hidden\" style=\"width:150px\" name=\"unlinkFile\" value=\"$fullName\"</td></tr>\n";	
	print "<input type=\"hidden\" style=\"width:150px\" name=\"descfil\" value=\"$descfil\"</td></tr>\n";	
	print "<input type=\"hidden\" style=\"width:150px\" name=\"kladde_id\" value=\"$kladde_id\"</td></tr>\n";
	print "</form>";
	// print "<script language=\"javascript\">";
	// print "document.gennemse.$fokus.focus();";
	// print "</script>";
	exit;

	print "</tbody></table>";




	function upload_bilag($sourceId,$source,$bilag_id,$bilag,$fokus,$filnavn,$relativeP1,$kladde_id){
		global $charset;
		global $db;
		global $bruger_id;
		global $userId;
		global $exec_path;
		global $servername;
		//global $kladde_id;
		global $globalId;

		if(!isset($globalId)) $globalId=1;
		#global $sprog_id 
	
		$readOnly=0;
		$archivefile=if_isset($_POST['archivefile']);
		$path = "../../bilag/$servername/$db/finance/$kladde_id/$bilag_id";
		$path1= "../../bilag/$servername/$db/finance/$kladde_id";
		$showDoc = $path.$filnavn;
		if (!isset($exec_path)) $exec_path="/usr/bin";
		
		if ($archivefile || file_exists("../temp/$db/$filnavn")) {
			$x=0;
			if (!file_exists($relativeP1."/".$db)) {
			  mkdir ($relativeP1."/".$db,0777);
			 
		    }
			if(!file_exists($path1)){
				mkdir ($path1 ,0777);
				if (!file_exists($path1)) {
					print tekstboks("Det er sket en fejl, bilag ikke gemt\nRing venligst på 46902208 så problemet kan blive løst");
					print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
					exit;
			   }
			}

			$bilagfilnavn="bilag_".$bilag_id;
			
			$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
			if($box6=$r['box6']) {
				$archivefile=if_isset($_POST['archivefile']);
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
				if ($source=="kassekladde" || $source=="ordrer") {
					$mappe='bilag';
					if (!file_exists($relativeP1."/".$db."/".$mappe)) {
						mkdir ($relativeP1."/".$db."/".$mappe,0777);
					}
					$tidspkt=date("U");
					if ($source=="kassekladde") {
						if (!$bilag_id) {
							$bilag*=1; # 20150105
							db_modify("insert into kassekladde (bilag,beskrivelse,kladde_id) values ('$bilag','$tidspkt','$kladde_id')",__FILE__ . " linje " . __LINE__);
							$r=db_fetch_array(db_select("select id from kassekladde where bilag='$bilag' and beskrivelse='$tidspkt' and kladde_id='$Kladde_id'",__FILE__ . " linje " . __LINE__));
							$bilag_id=$r['id'];
						}
						$undermappe="kladde_$kladde_id";
						##echo __LINE__;
						// if (!file_exists($relativeP1."/".$db."/".$mappe."/".$undermappe)) {
						// 	mkdir ($relativeP1."/".$db."/".$mappe."/".$undermappe,0777);
						// } 
						if (!file_exists($path)) {
							mkdir ($path,0777);
						}
					} else {
						$undermappe="ordrer";
						if (!file_exists($relativeP1."/".$db."/".$mappe."/".$undermappe)) {
							mkdir ($relativeP1."/".$db."/".$mappe."/".$undermappe,0777);
						} 
					}
					$bilagfilnavn="bilag_".$bilag_id;
				} else {
					$mappe='dokumenter';
					if (!file_exists($relativeP1."/".$db."/".$mappe)) {
						mkdir ($relativeP1."/".$db."/".$mappe,0777);
					}
					$undermappe="debitor_$kladde_id";
					if (!file_exists($relativeP1."/".$db."/".$mappe."/".$undermappe)) mkdir ($relativeP1."/".$db."/".$mappe."/".$undermappe,0777);
					$bilagfilnavn="doc_".$bilag_id;
				}
				if ($archivefile) $fra=$relativeP1."/".$db."/pulje/".$archivefile;
				else $fra="../temp/".$db."/".$filnavn;
				//$til=$relativeP1."/".$db."/".$mappe."/".$undermappe."/".$bilagfilnavn;
				//$til="../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe."/".$bilagfilnavn;
				$til=$path;
				if(!file_exists($path."/".$archivefile)){ //Moves the file from uploaded email files to finans file
					system ("mv '$fra' '$til'\n");
				}else{
					
					$alerttxt = __line__." $archivefile already exists";
					
					print tekstboks("$alerttxt");
					$previousPage = $_SERVER['HTTP_REFERER'];
					header("Location: $previousPage");
					exit;
				} 

				if (file_exists($path)) $tjek='ok';
				else {
					$alerttxt = __line__." indlæsning af $filnavn fejlet";
					print "<BODY onLoad=\"javascript:alert($alerttxt)\">\n";
					return;
				}

				###########]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]
				if(file_exists($path."/".$archivefile)){
					
						
							$qtxt = "insert into documents(global_id,filename,filepath,source,source_id,timestamp,user_id) values ";
							$qtxt.= "('$globalId','$archivefile','$path','$source','$sourceId','". date('U') ."','$bruger_id')";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						
			
				} else print tekstboks("move file failed");	
				###########]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]
				if ($dh = opendir($relativeP1."/".$db."/pulje/")) {
					$slettet=0;	
					while (($file = readdir($dh)) !== false) {
						if ($slettet==0 && substr($file,-5)=='.desc') {
							$descfil=str_replace("desc","",$file);
							if (substr($archivefile,0,strlen($descfil))==$descfil) {
								
								//system ("rm ../".$nfs_mappe."/".$db."/pulje/".$descfil."desc\n");
								system ("rm ".$relativeP1."/".$db."/pulje/".$descfil."desc\n");
								$slettet=1;
							}
						}
					}
					closedir($dh);
				}
				if ($archivefile) $filnavn=$archivefile;
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
				$qt1 = "update $source set ";
				if ($source=="kassekladde") {
					if ($bilag_id) {
						$qt2 = "select bogfort from kladdeliste,kassekladde where kassekladde.id='$bilag_id' ";
						$qt2.= "and kladdeliste.id = kassekladde.kladde_id";
						$r=db_fetch_array(db_select($qt2,__FILE__ . " linje " . __LINE__));
						($r['bogfort'] != '-')?$readOnly=1:$readOnly=0; 
					}
					if ($archivefile && $readOnly == 0) {
						if(!isset($d_type)) $d_type='F';
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
			if ($source=="kassekladde" || $source=="ordrer") {
					$mappe=$r['box4'];
					($source=="kassekladde")?$undermappe="kladde_$kladde_id":$undermappe="ordrer";
					$bilagfilnavn="bilag_".$bilag_id;
				} else {
					$mappe=$r['box5'];
					$undermappe="debitor_$kladde_id";
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
					db_modify("update $source set dokument='".db_escape_string($filnavn)."' where id='$bilag_id'",__FILE__ . " linje " . __LINE__);
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
			$tmp="../includes/bilag.php?bilag=$bilag&kilde=$source&kilde_id=$sourceId&fokus=$fokus&funktion=gennemse";
		}
		else
	*/		
		if ($source=="kassekladde") $tmp="../../finans/kassekladde.php?kladde_id=$kladde_id&bilag_id=$bilag_id&fokus=$fokus";
		elseif ($source=="ordrer") $tmp="../debitor/ordre.php?id=$sourceId&fokus=$fokus";
		else $tmp="../debitor/historikkort.php?id=$sourceId";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
	}//end of upload_bilag func
?>
