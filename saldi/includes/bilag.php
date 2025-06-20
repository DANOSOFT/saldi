<?php
// ----------includes/bilag.php------------patch 3.6.2-----2016.01.16-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2016 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2014.01.12 Tilføjet ordre som kilde.
// 2014.01.22 Rettet if til elseif dat man ellers kommer tilbage til historik ved opslag fra kassekladde. Søg 2014.01.22
// 2014.11.05 Flyttet tjek op i "egen FTP"  #20141105
// 2014.11.06 Sletter nu også bilag når der ikke er en descfil
// 2015.01.05 Ganger bilag med 1 for at sikre at der er en værdi til indsættelse - 20150105
// 2015.04.23 Indsat break ved upload fejlet, så bilag ikke sættes i tabel.
// 2016.01.16 ændret mkdir mv osv fra systemkommandoer til PHP kommandoer


@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Kassebillag";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

if(($_GET)||($_POST)) {

	$funktion=if_isset($_GET['funktion']);
	if ($_GET['kilde_id']) {
		$kilde_id=if_isset($_GET['kilde_id']);
		$kilde=if_isset($_GET['kilde']);
		$bilag_id=if_isset($_GET['bilag_id']);
		$bilag=if_isset($_GET['bilag']);
		$fokus=if_isset($_GET['fokus']);
		$ny=if_isset($_GET['ny']);
		$vis=if_isset($_GET['vis']);
		$filnavn=if_isset($_GET['filnavn']);
	} 
	if ($_POST['kilde_id']) {
		$submit=$_POST['submit'];
		$kilde_id=$_POST['kilde_id'];
		$kilde=$_POST['kilde'];
		$bilag_id=$_POST['bilag_id'];
		$bilag=$_POST['bilag'];
		$fokus=$_POST['fokus'];
	}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td colspan= \"3\" height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	if ($kilde=="kassekladde") $tmp="../finans/kassekladde.php?kladde_id=$kilde_id&fokus=$fokus";
	elseif ($kilde=="ordrer") $tmp="../debitor/ordre.php?id=$kilde_id&fokus=$fokus"; #20140122
	else $tmp="../debitor/historikkort.php?id=$kilde_id";

	if (file_exists("../owncloud")) $nfs_mappe='owncloud';
	elseif (file_exists("../bilag")) $nfs_mappe='bilag';
	
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$tmp accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$title $bilag</td>";
	print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>";

	
	
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
			print "<BODY onLoad=\"javascript:alert('Der tillades kun bilag af typen PDF')\">";
			upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus);
		}
	#		$filnavn=htmlentities($filnavn,ENT_COMPAT,$charset);
		$tmp="../temp/".$db."/".$filnavn;
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'],"$tmp")) {
			upload_bilag($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$funktion,$nfs_mappe);
		}	else {
			echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
			upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$nfs_mappe);
		}
	} else upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$nfs_mappe);
}
print "</tbody></table>";
################################################################################################################
function upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$nfs_mappe){
global $charset;
	print "<tr><td width=100% align=center><table width=\"500px\" height=\"200px\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	print "<tr><td width=100% align=center>Vedh&aelig;ft bilag</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	if ($kilde=='kassekladde') {
		$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
		if($box6=$r['box6']) {
			print "<form name=\"pulje\" action=\"bilag.php?sort=$sort&kilde=$kilde\"; method=\"post\">";
			print "<input type=\"hidden\" name=\"kilde_id\" value=$kilde_id>";
			print "<input type=\"hidden\" name=\"kilde\" value=$kilde>";
			print "<input type=\"hidden\" name=\"bilag_id\" value=$bilag_id>";
			print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
			print "<input type=\"hidden\" name=\"fokus\" value=$fokus>";
			print "<tr><td width=100% align=center> V&aelig;lg bilag fra pulje: <input class=\"inputbox\" name=\"pulje\" type=\"submit\" value=\"Gennemse\"/><br /></td></tr>";
			print "</form>";
		}
	}
	print "<form enctype=\"multipart/form-data\" action=\"bilag.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000000\">";
	print "<input type=\"hidden\" name=\"kilde_id\" value=$kilde_id>";
	print "<input type=\"hidden\" name=\"kilde\" value=$kilde>";
	print "<input type=\"hidden\" name=\"bilag_id\" value=$bilag_id>";
	print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<input type=\"hidden\" name=\"fokus\" value=$fokus>";
	print "<tr><td width=100% align=center> V&aelig;lg bilag til upload: <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
	print "<tr><td></form></td></tr>";
}

function upload_bilag($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$funktion,$nfs_mappe){
	global $charset;
	global $db;
	global $bruger_id;
	global $exec_path;

	$puljefil=if_isset($_POST['puljefil']);

	if (!isset($exec_path)) $exec_path="/usr/bin";
	
	if ($puljefil || file_exists("../temp/$db/$filnavn")) {
		$x=0;
		if (!file_exists("../".$nfs_mappe."/".$db)) {
		  mkdir ("../".$nfs_mappe."/".$db,0777);
		  if (!file_exists("../".$nfs_mappe."/".$db)) {
				print tekstboks("Det er sket en fejl, bilag ikke gemt\nRing venligst på 46902208 så problemet kan blive løst");
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">";
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
				print "<BODY onLoad=\"javascript:alert('indl&aelig;sning af $filnavn fejlet')\">";
				break 1;
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
			$langt_filnavn=$til;
			$bilag*=1;
			if (!$dato) $dato=date("d-m-Y");
			if (!is_numeric($debet)) {
				if (strtolower(substr($debet,0,1))=='d') $d_type='D';
				elseif (strtolower(substr($debet,0,1))=='k') $d_type='K';
				else $d_type='F';
				$debet=substr($debet,1);
			}
			$debet*=1;
			if (!is_numeric($kredit)) {
				if (strtolower(substr($kredit,0,1))=='d') $k_type='D';
				elseif (strtolower(substr($kredit,0,1))=='k') $k_type='K';
				else $k_type='F';
				$kredit=substr($kredit,1);
			}
			$kredit*=1;
			$afd*=1;
			if ($kilde=="kassekladde") {
					if ($puljefil) db_modify("update kassekladde set bilag='$bilag',transdate='".usdate($dato)."',beskrivelse='".db_escape_string($beskrivelse)."',d_type='$d_type',debet='$debet',k_type='$k_type',kredit='$kredit',faktura='$fakturanr',amount='".usdecimal($sum)."',afd='$afd',projekt='$projekt',dokument='$filnavn' where id='$bilag_id'",__FILE__ . " linje " . __LINE__);
					else db_modify("update kassekladde set dokument='$filnavn' where id='$bilag_id'",__FILE__ . " linje " . __LINE__);
			} else {
				db_modify("update $kilde set dokument='".db_escape_string($filnavn)."' where id='$bilag_id'",__FILE__ . " linje " . __LINE__);
			}
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
	if ($funktion=='gennemse') {
		$bilag++;
		$tmp="../includes/bilag.php?bilag=$bilag&kilde=$kilde&kilde_id=$kilde_id&fokus=$fokus&funktion=gennemse";
	}
	elseif ($kilde=="kassekladde") $tmp="../finans/kassekladde.php?kladde_id=$kilde_id&fokus=$fokus";
	elseif ($kilde=="ordrer") $tmp="../debitor/ordre.php?id=$kilde_id&fokus=$fokus";
	else $tmp="../debitor/historikkort.php?id=$kilde_id";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">";
}

function vis_bilag($kilde_id,$kilde,$bilag_id,$fokus,$filnavn,$nfs_mappe){

	global $charset;
	global $db;
	global $bruger_id;
	global $exec_path;
	
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
		$fp=fopen("../temp/$db/ftpscript.$bruger_id","w");
		if ($fp) {
			fwrite ($fp, "cd $mappe\ncd $undermappe\nget $bilagfilnavn\nbye\n");
		}
		fclose($fp);
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id > ftplog\nmv \"$bilagfilnavn\" \"$filnavn\"\n";
		system ($kommando);
	}
	print "<tr><td width=100% height=100% align=\"center\" valign=\"middle\"><iframe frameborder=\"no\" width=\"100%\" height=\"100%\" scrolling=\"auto\" src=\"vis_bilag.php?filnavn=$filnavn&db=$db&bilag_id=$bilag_id&kilde_id=$kilde_id&kilde=$kilde\"></iframe></td></tr>";
}

function gennemse($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$puljefil,$nfs_mappe){
	global $db;
	
	(isset($_POST['slet_bilag']) && $_POST['slet_bilag']=='Slet')?	$slet=1:$slet=0;
	(isset($_POST['upload_bilag']) && $_POST['upload_bilag']=='inds&aelig;t')?	$indsaet=1:$inssaet=0;
	$descfil=if_isset($_POST['descfil']);
	
	if ($slet) {
#		echo "slettter ../temp/$db/pulje/$descfil*<br>";
		if ($descfil) system("rm ../".$nfs_mappe."/$db/pulje/$descfil*\n");
		elseif (isset($_POST['puljefil'])) {
			$puljefil=if_isset($_POST['puljefil']);
#			echo "slettter ../".$nfs_mappe."/$db/pulje/$puljefil<br>";
			if ($puljefil) system("rm ../".$nfs_mappe."/$db/pulje/$puljefil\n");
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/bilag.php?bilag=$bilag&kilde_id=$kilde_id&kilde=$kilde&fokus=$fokus&funktion=gennemse\">";
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
		if (!$dato) $dato=dkdato($r['transdate']);
		if (!$beskrivelse) $beskrivelse=$r['beskrivelse'];
		if (!$debet) {
			if ($r['d_type']!='F') $debet=$r['d_type'].$r['debet'];
			else $debet=$r['debet'];
		}
		if (!$kredit) {
			if ($r['k_type']!='F') $kredit=$r['d_type'].$r['kredit'];
			else $kredit=$r['kredit'];
		} if (!$fakturanr) $fakturanr=$r['fakturanr'];
		if (!$sum) $sum=dkdecimal($r['amount']);
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
	print "<input type=\"hidden\" name=\"puljefil\" value=$puljefil>";
	print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
	print "<input type=\"hidden\" name=\"kilde_id\" value=$kilde_id>";
	print "<input type=\"hidden\" name=\"kilde\" value=$kilde>";
	print "<input type=\"hidden\" name=\"bilag_id\" value=$bilag_id>";
	print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<input type=\"hidden\" name=\"fokus\" value=$fokus>";
	print "<tr><td width=15% height=\"70%\" align=center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	print "<tr><td width=100% align=center>";
	$fil_nr=0;
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				if (substr($file,0,1)!='.' && substr($file,-5)!='.desc') {
					($file==$puljefil)?$bgcolor='#aaaaaa':$bgcolor='#ffffff'; 
					$fil_nr++;
					print "<tr><td bgcolor=\"$bgcolor\"><a href=../includes/bilag.php?funktion=gennemse&kilde_id=$kilde_id&kilde=$kilde&bilag=$bilag&bilag_id=$bilag_id&dato=$dato&fokus=$fil_nr&puljefil=$file onfocus=\"document.forms[0].fokus.value=this.name;\" id=\"$fil_nr\">$file</a></td></tr>";
				}
			}
			closedir($dh);
		}
	}
	if ($puljefil) {
		$tmp="../../../".$nfs_mappe."/$db/pulje/$puljefil";
		if (!is_dir("../temp/$db/pulje")) mkdir("../temp/$db/pulje"); 
		system("cd ../temp/$db/pulje\nrm *\ncp $tmp .\n");
	} else {
	
		print "<BODY onLoad=\"javascript:alert('Ingen bilag i pulje')\">";
		if ($kilde=="kassekladde") $tmp="../finans/kassekladde.php?kladde_id=$kilde_id&fokus=$fokus";
		elseif ($kilde=="ordrer") $tmp="../debitor/ordre.php?id=$kilde_id&fokus=$fokus";
		else $tmp="../debitor/historikkort.php?id=$kilde_id";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">";
	}
	print "</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "</table></td>";
	print "<td rowspan=\"2\" width=85% height=\"100%\" align=center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	print	"<tr><td width=100% align=center>";
	$tmp=$url.$puljefil;
	if ($puljefil) {
		if ($google_docs) $src="http://docs.google.com/viewer?url=$tmp&embedded=true";
		else $src=$tmp;
		print "<iframe style=\"width:100%;height:100%\" src=\"$src\" frameborder=\"0\">";
		print "</iframe></td></tr>";
	}
	print "</tbody></table></td></tr>";
	print "<tr><td><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	$descfil=NULL;
	if ($puljefil) {
		if (substr($puljefil,-3,1)==".") $descfil=substr($puljefil,0,strlen($puljefil)-3); 
		elseif (substr($puljefil,-4,1)==".") $descfil=substr($puljefil,0,strlen($puljefil)-4); 
		else $descfil=NULL;
	}
	if ($descfil) {
		$tmp="../../".$dir."/".$descfil.".desc";
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
	print "<tr><td>Bilag</td><td><input type=\"text\" style=\"width:150px\" name=\"bilag\" value=\"$bilag\"</td></tr>";	
	print "<tr><td>Dato</td><td><input type=\"text\" style=\"width:150px\" name=\"dato\" value=\"$dato\"</td></tr>";	
	print "<tr><td>beskrivelse</td><td><input type=\"text\" style=\"width:150px\" name=\"beskrivelse\" value=\"$beskrivelse\"</td></tr>";	
	print "<tr><td>Debet</td><td><input type=\"text\" style=\"width:150px\" name=\"debet\" value=\"$debet\"</td></tr>";	
	print "<tr><td>Kredit</td><td><input type=\"text\" style=\"width:150px\" name=\"kredit\" value=\"$kredit\"</td></tr>";	
	print "<tr><td>Fakturanr</td><td><input type=\"text\" style=\"width:150px\" name=\"fakturanr\" value=\"$fakturanr\"</td></tr>";	
	print "<tr><td>Sum</td><td><input type=\"text\" style=\"width:150px\" name=\"sum\" value=\"$sum\"</td></tr>";	
	print "<tr><td>Sag</td><td><input type=\"text\" style=\"width:150px\" name=\"sag\" value=\"$sag\"</td></tr>";	
	print "<tr><td>Afd</td><td><input type=\"text\" style=\"width:150px\" name=\"afd\" value=\"$afd\"</td></tr>";	
	print "<tr><td>Projekt</td><td><input type=\"text\" style=\"width:150px\" name=\"projekt\" value=\"$projekt\"</td></tr>";	
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\" name=\"indsaet_bilag\" value=\"Inds&aelig;t\"</tr>";	
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\" name=\"slet_bilag\" value=\"Slet\"</tr>";	
	print "</tbody></table></td></tr>";
	print "<input type=\"hidden\" style=\"width:150px\" name=\"descfil\" value=\"$descfil\"</td></tr>";	
	print "</form>";
	print "<script language=\"javascript\">";
	print "document.gennemse.$fokus.focus();";
	print "</script>";
	exit;

} # endfunc gennemse
?>