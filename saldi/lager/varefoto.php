<?php
// ------------- lager/varefoto.php ---------- ver 3.6.1----2016.01.06-------
// LICENS
//		if ($bordnr) $bordnr=$_COOKIE['saldi_bordnr'];

// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();

$modulnr=9;
$title="Varefoto";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$submit=if_isset($_POST['submit']);
$id=if_isset($_GET['id']);
$fotonavn=if_isset($_GET['fotonavn']);
$sletfoto=if_isset($_GET['sletfoto']);

if ($fotonavn) $title=$fotonavn;

print "<div align=\"center\">\n";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
print "<td width=\"10%\" $top_bund><a href=\"varekort.php?id=$id\" accesskey=\"L\">Luk</a></td>\n";
print "<td width=\"80%\" $top_bund>$title</td>\n";
print "<td width=\"10%\" $top_bund><br></td>\n";
print "</tbody></table>\n";
print "</td></tr>\n";

if($submit) {
	if ($fotonavn=db_escape_string(basename($_FILES['uploadedfile']['name']))) {
		echo "$fotonavn<br>";
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			echo "overfører $filnavn<br>";
			upload_foto($id,$filnavn,$fotonavn);
			# overfoer_data($filnavn,$splitter,$feltnavn,$feltantal,$tegnset);
		}
	}
} elseif ($sletfoto==1) {
	if (file_exists("../owncloud/".$db."/varefotos/".$id)) unlink ("../owncloud/".$db."/varefotos/".$id);
	db_modify("update varer set fotonavn='' where id='$id'",__FILE__ . " linje " . __LINE__);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=varefoto.php?id=$id\">";
} elseif (file_exists("../owncloud/".$db."/varefotos/".$id)) {
	$fotourl="../owncloud/".$db."/varefotos/".$id;
	print "<tr><td align=\"center\"><img style=\"border:0px solid;width:100%;\" alt=\"$fotonavn\" src=\"$fotourl\"></td></tr>";
	print "<tr><td align=\"center\"><a href=\"varefoto.php?id=$id&sletfoto=1\">Slet foto</a></td></tr>";
} else upload($id);

print "</tbody></table>";
print "</body></html>";
#####################################################################################################
function upload($id){
	print "<form enctype='multipart/form-data' action='varefoto.php?id=$id' method='POST'>\n";
	print "<tr><td width='100%' align='center'><table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	print "<input type='hidden' name='MAX_FILE_SIZE' value='2900000'>\n";
	print "<tr><td width='100%' align='center'> V&aelig;lg datafil: <input name='uploadedfile' type='file' /><br /></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td align='center'><input type='submit' name='submit' value='Hent' /></td></tr>\n";
	#print "</tbody></table>\n";
	#print "</td></tr>\n";
	print "<tr><td>&nbsp;</td></tr>\n";
	print "</form>\n";
} # end function upload

function upload_foto($id,$filnavn,$fotonavn){
	global $charset;
	global $db;
	global $bruger_id;
	global $exec_path;

	if (!isset($exec_path)) $exec_path="/usr/bin";
	
	if (!file_exists("../owncloud/".$db)) {
	  mkdir ("../owncloud/".$db,0777);
	  if (!file_exists("../owncloud/".$db)) {
			print tekstboks("Det er sket en fejl, bilag ikke gemt\nRing venligst på 46902208 så problemet kan blive løst");
			print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id\">";
			exit;
		}
  }
	$mappe='varefotos';
	if (!file_exists("../owncloud/".$db."/".$mappe)) {
		mkdir ("../owncloud/".$db."/".$mappe,0777);
	}
	$fra=$filnavn;
	$til="../owncloud/".$db ."/".$mappe ."/".$id;
	rename ($filnavn,$til);
	echo "flytter '$fra' '$til'<br>";
	db_modify("update varer set fotonavn='".db_escape_string($fotonavn)."' where id='$id'",__FILE__ . " linje " . __LINE__);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=varefoto.php?id=$id\">";
}
/*
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
				print "<BODY onLoad=\"javascript:alert('$filnavn er indl&aelig;st')\">";
			} else {
				print "<BODY onLoad=\"javascript:alert('A indl&aelig;sning af $filnavn fejlet')\">";
			}
		}
	} #print "<BODY onLoad=\"javascript:alert('B indl&aelig;sning af $filnavn fejlet')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">";
}
*/

?>