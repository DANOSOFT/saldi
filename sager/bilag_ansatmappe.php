<?php
// ----------finans/importer.php------------patch 3.2.5-----2011.12.28-----------
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

/*              SKAL RETTES TIL             */
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

//$sag_id=if_isset($_GET['sag_id']);
//$sag_id=if_isset($_POST['sag_id']);
//$konto_id=if_isset($_GET['konto_id']);


		$bg="nix";
		$header='nix';

		$menu_sager=NULL;
		$menu_dagbog=NULL;
		$menu_kunder=NULL;
		$menu_loen=NULL;
		$menu_ansatte=NULL;
		$menu_certificering=NULL;
		$menu_medarbejdermappe='id="menuActive"';
		
		$modulnr=0;
		
		global $brugernavn;
		global $db;
		global $ansat_navn;
		global $ansat_id;
		
		$ans_id = $_SESSION['ans_id'];
		$ansatmappe_id=if_isset($_GET['ansatmappe_id']);
		
		if(!$ans_id) $ans_id=$ansat_id;
	
		
		/*
		$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__)); // mister sag_id når der oploades??
		$sagsnr=$r['sagsnr'];
		$sag_beskrivelse=htmlspecialchars($r['beskrivelse']);
		$udf_addr1=htmlspecialchars($r['udf_addr1']);
		$udf_postnr=$r['udf_postnr'];
		$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
		*/
		
		$r=db_fetch_array(db_select("select * from ansatmappe where id='$ansatmappe_id'",__FILE__ . " linje " . __LINE__));
		$ansatmappebeskrivelse=htmlspecialchars($r['beskrivelse']);
		
		include_once '../includes/top_header_sager_small.php';
		include_once '../includes/sagsmenu.php';
		
		//echo "ansat_id: $ansat_id<br>";echo "ans_id: $ans_id";
		
		print "<div id=\"breadcrumbbar\">

			<ul id=\"breadcrumb\">
				<li>";
					if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					print "</li>
				<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
				<li><a href=\"medarbejdermappe.php\" title=\"Medarbejdermappe menu\">Medarbejdermappe menu</a>
				<li><a href=\"medarbejdermappe.php?funktion=medarbejdermappe\" title=\"Medarbejder-mappe\">Medarbejder-mappe</a></li>";
				print "<li title=\"$ansatmappebeskrivelse\"><a href=\"medarbejdermappe.php?funktion=vis_ansatmappe&amp;ansatmappe_id=$ansatmappe_id\" style=\"display:block;max-width:160px;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;\">$ansatmappebeskrivelse</a></li>\n";
				print "<li>Vedhæft bilag</li>
			</ul>
		</div><!-- end of breadcrumbbar -->\n";
		
		//print "<div class=\"maincontentLargeHolder\">\n";
		//print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
		//print "<tbody>\n";

$filnavn = NULL;

if(($_GET['kilde_id'])||($_POST['kilde_id'])) {
	if ($_GET['kilde_id']) {
		$kilde_id=if_isset($_GET['kilde_id']);
		$kilde=if_isset($_GET['kilde']);
		$bilag_id=if_isset($_GET['bilag_id']);
		$bilag=if_isset($_GET['bilag']);
		$fokus=if_isset($_GET['fokus']);
		$ny=if_isset($_GET['ny']);
		$vis=if_isset($_GET['vis']);
		$filnavn=if_isset($_GET['filnavn']);
		$fase=if_isset($_GET['fase']);
	} else {
		$submit=$_POST['submit'];
		$kilde_id=$_POST['kilde_id'];
		$kilde=$_POST['kilde'];
		$bilag_id=$_POST['bilag_id'];
		$bilag=$_POST['bilag'];
		$fokus=$_POST['fokus'];
		$kategori=trim(if_isset($_POST['ny_kategori']));
		if (!$kategori) $kategori=if_isset($_POST['kategori']);
		$beskrivelse=if_isset($_POST['beskrivelse']);
		$fase=if_isset($_POST['fase']);
		$bilag_fase=if_isset($_POST['bilag_fase']);
	} 
	
	
	if ($bilag_id) {
		ret_bilag($bilag_id);
	} elseif ($filnavn) { 
		vis_bilag($kilde_id,$kilde,$bilag_id,$fokus,$filnavn);
	} elseif (if_isset($_FILES['uploadedfile']['name'])) { 
	
		//$filnavn=basename($_FILES['uploadedfile']['name']);
		//$filnavn=htmlentities($filnavn,ENT_COMPAT,$charset);
			//$test=$_FILES['uploadedfile']['name'];
			//echo "file:$test[0]".count($_FILES['uploadedfile']['name']);
			//echo "beskrivelse: $beskrivelse";
			//exit;
			if ((empty($_FILES['uploadedfile']['name'][0])) && $beskrivelse){
				$messages = "Vælg en eller flere filer der skal hentes";
				upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$fase,$messages,$beskrivelse);
			} elseif ((empty($beskrivelse)) && $_FILES['uploadedfile']['name'][0]){
				$messages = "Beskrivelse er ikke udfyldt";
				upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$fase,$messages,$beskrivelse);
			} elseif ((empty($_FILES['uploadedfile']['name'][0])) && (empty($beskrivelse))) {
				$messages = "Der mangler både beskrivelse og filer!";
				upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$fase,$messages,$beskrivelse);
			} else {
			$count=count($_FILES['uploadedfile']['name']);
			
			for($x =0; $x< $count;$x++){
				$filnavn=basename($_FILES['uploadedfile']['name'][$x]);
				$filnavn=htmlentities($filnavn,ENT_COMPAT,$charset);
				$i=strlen($filnavn);
			
				$t="";
				$filtype="";
				while ($i>0 && $t!='.'){
				$filtype=$t.$filtype;
					$t=substr($filnavn,$i,1);
					$i--;
				}
				// Hvis der er flere end 1 fil for filerne et nr foran navnet
				if ($count > 1) {
				 $nyfilnavn = ($x+1)."_".$filnavn;
				} else {
				 $nyfilnavn = $filnavn;
				}
				
				$datotid=date('U');
				db_modify("insert into ansatmappebilag(navn,filtype,beskrivelse,datotid,assign_to,assign_id,hvem) values('".db_escape_string($nyfilnavn)."','".db_escape_string($filtype)."','".db_escape_string($beskrivelse)."','$datotid','sager','$kilde_id','$ansat_navn')",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select max(id) as id from ansatmappebilag where assign_to = 'sager' and datotid='$datotid'",__FILE__ . " linje " . __LINE__));
				$ny_id=$r['id']; // lægger 1 til ved hver gang den kører
				if (!file_exists("../ansatmappe/".$db."/".$ans_id."/".$kilde_id)) { // husk at få ans_id med (mellem db og kilde_id)
					if (!file_exists("../ansatmappe")) mkdir("../ansatmappe",0777); 
					if (!file_exists("../ansatmappe/".$db)) mkdir("../ansatmappe/".$db,0777);
					if (!file_exists("../ansatmappe/".$db."/".$ans_id)) mkdir("../ansatmappe/".$db."/".$ans_id,0777); 
					if (!file_exists("../ansatmappe/".$db."/".$ans_id."/".$kilde_id)) mkdir("../ansatmappe/".$db."/".$ans_id."/".$kilde_id); 
				}
				$tmp="../ansatmappe/".$db."/".$ans_id."/".$kilde_id."/".$ny_id.".".$filtype;
				
				//echo "tmp: $tmp<br>";
				/*
				// her er til enkelt fil opload
				$i=strlen($filnavn);
				$t="";
				$filtype="";
				while ($i>0 && $t!='.'){
				$filtype=$t.$filtype;
					$t=substr($filnavn,$i,1);
					$i--;
				} 
				
				$datotid=date('U');
				db_modify("insert into bilag(navn,filtype,beskrivelse,datotid,assign_to,assign_id,kategori,fase,hvem,bilag_fase) values('".db_escape_string($filnavn)."','".db_escape_string($filtype)."','".db_escape_string($beskrivelse)."','$datotid','sager','$kilde_id','$kategori','$fase','$brugernavn','".db_escape_string($bilag_fase)."')",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from bilag where assign_to = 'sager' and datotid='$datotid'",__FILE__ . " linje " . __LINE__));
				$ny_id=$r['id'];
				if (!file_exists("../bilag/".$db."/".$kilde_id)) {
					if (!file_exists("../bilag")) mkdir("../bilag",0777); 
					if (!file_exists("../bilag/".$db)) mkdir("../bilag/".$db,0777);
					if (!file_exists("../bilag/".$db."/".$kilde_id)) mkdir("../bilag/".$db."/".$kilde_id); 
				}
				$tmp="../bilag/".$db."/".$kilde_id."/".$ny_id.".".$filtype;
				*//*
				if(!is_uploaded_file($_FILES['uploadedfile']['tmp_name'][$x]))
        {
					$messages[] = $filnavn.' No file uploaded."<br>"';
        }*/
				if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'][$x],"$tmp")) {
				
					print "<meta http-equiv=\"refresh\" content=\"1;URL=medarbejdermappe.php?funktion=vis_ansatmappe&amp;ansatmappe_id=$kilde_id\">";
					//$messages[] = $filnavn.' Uploaded."<br>"';
				}	else {
					$messages = "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
					upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$fase,$messages);
				}
			}// her slutter løkke til opload 
		}
	} else upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$fase);
}
// Her updateres fase, kategori og bilag til bilag
if (isset($_POST['ret_bilag']) && $bilag_id) {
		$bilag_beskrivelse=db_escape_string($_POST['beskrivelse']);
		/*$bilag_fase=db_escape_string($_POST['bilag_fase']);
		$bilag_kategori=trim(if_isset($_POST['ny_kategori']));
		if (!$bilag_kategori) $bilag_kategori=if_isset($_POST['kategori']);
		*/
		db_modify("update ansatmappebilag set beskrivelse='$bilag_beskrivelse' where id = '$bilag_id'",__FILE__ . " linje " . __LINE__);
				print "<meta http-equiv=\"refresh\" content=\"0;URL=medarbejdermappe.php?funktion=vis_ansatmappe&amp;ansatmappe_id=$kilde_id\">";
	}
################################################################################################################
function upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$fase,$messages,$beskrivelse){

	global $charset;
	global $ansat_id;
		
	$ans_id = $_SESSION['ans_id'];
	$ansatmappe_id=if_isset($_GET['ansatmappe_id']);
	
	if(!$ans_id) $ans_id=$ansat_id;
	
	$r=db_fetch_array(db_select("select * from ansatmappe where id='$ansatmappe_id'",__FILE__ . " linje " . __LINE__));
	$ansatmappebeskrivelse=htmlspecialchars($r['beskrivelse']);
	
	if ($ans_id) {
		$r=db_fetch_array(db_select("SELECT id, navn, initialer FROM ansatte WHERE konto_id = 1 AND id='$ans_id'",__FILE__ . " linje " . __LINE__));
		$ans_id=$r['id'];
		$ans_navn=$r['navn'];
		$ans_ini=$r['initialer'];
	}
/*
if ($kilde=='sager') {
	$x=0;
	$q=db_select("select distinct(kategori) from bilag where assign_to = 'sager' order by kategori",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['kategori']) {
			$x++;
			$sags_kat[$x]=$r['kategori'];
		}
	}
}

// Query til fase
$bilag_status = NULL;
if ($kilde=='sager') {
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tjek_id[$x]=$r['id'];
		$tjek_sub_id[$x]=$r['sub_id'];
		$tjek_punkt[$x]=$r['tjekpunkt']; 
		$tjek_fase[$x]=$r['fase']*1;
		$x++;
	}
}
*/
print "<div class=\"maincontentLargeHolder\">\n";
print "<form enctype=\"multipart/form-data\" action=\"bilag_ansatmappe.php?ansatmappe_id=$kilde_id\" method=\"POST\">\n";
print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
print "<tbody><tr><td>\n";
print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\">\n";
print "<tbody><tr><td align=\"center\" style=\"color: green;\"><h3>($ans_ini) $ans_navn</h3></td></tr></tbody>\n";
print "<tbody>\n";
print "<tr><td width=\"100%\" align=\"center\">\n";
print "<table width=\"500\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid lightgray; #padding: 0pt 0pt 1px;\">\n";
print "<tbody>\n";
print "<tr><td colspan=\"2\" width=\"100%\" align=\"center\"><h3>Vedh&aelig;ft bilag til mappe</h3></td></tr>\n";
print "<tr><td colspan=\"2\" width=\"100%\" align=center><br>\n";

print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000000\">\n";
print "<input type=\"hidden\" name=\"kilde_id\" value=\"$kilde_id\">\n";
print "<input type=\"hidden\" name=\"kilde\" value=\"$kilde\">\n";
print "<input type=\"hidden\" name=\"bilag_id\" value=\"$bilag_id\">\n";
print "<input type=\"hidden\" name=\"bilag\" value=\"$bilag\">\n";
print "<input type=\"hidden\" name=\"fokus\" value=\"$fokus\">\n";
print "<input type=\"hidden\" name=\"fase\" value=\"$fase\">\n";

print "</td></tr>\n";
print "<tr><td>Mappe&nbsp;navn:</td><td><b>$ansatmappebeskrivelse</b></td></tr>\n";
print "<tr><td>Beskrivelse:</td><td><input style=\"width:380px\" type=\"text\" name=\"beskrivelse\" value=\"$beskrivelse\"></td></tr>\n";
/*
print "<tr><td>Fase</td><td><select style=\"width:400px\" name=\"bilag_fase\">\n";
for($y=0;$y<=count($tjek_id);$y++) {
		if ($bilag_status==$tjek_punkt[$y]) print "<option value=\"$tjek_punkt[$y]\">$tjek_punkt[$y]</option>";
	}
	for($y=0;$y<=count($tjek_id);$y++) {
		if ($bilag_status!=$tjek_punkt[$y]) print "<option value=\"$tjek_punkt[$y]\">$tjek_punkt[$y]</option>";
	}
print "</select></td></tr>\n";
if ($sags_kat[1]) {
	print "<tr><td>Kategori</td><td><select style=\"width:400px\" name=\"kategori\">\n";
	print "<option value=\"\">&nbsp;</option>\n";
	$x=1;
	while ($sags_kat[$x]) {
		print "<option value=\"$sags_kat[$x]\">$sags_kat[$x]</option>\n";
		$x++;
	}
	print "</select></td></tr>\n";
}
print "<tr><td>Evt ny kategori</td><td><input style=\"width:400px\" type=\"text\" name=\"ny_kategori\"></td></tr>\n";
*/
// Tilføj [] i name og multiple for at vælge flere filer til opload
print "<tr><td>V&aelig;lg bilag:</td><td><input class=\"inputbox\" name=\"uploadedfile[]\" type=\"file\" multiple/><br /></td></tr>\n";
print "<tr><td colspan=\"2\" align=\"center\"><i style=\"color:red;\">&nbsp;$messages</i></td></tr>\n";
//print "<tr><td colspan=\"2\" align=\"center\"><i style=\"color:red;\">";
//$count= count($messages); for ($i =0; $i < $count; $i++){echo $messages[$i]."<br>";}
//print "</i></td></tr>\n";
print "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" class=\"button gray medium\" value=\"Hent\" /></td></tr>\n";
print "</tbody>\n";
print "</table>\n";
print "</td></tr>\n";
print "</tbody>\n";
print "</table>\n";
print "</td></tr>\n";
print "</tbody>\n";
print "</table>\n";
print "</form>\n";
print "</div><!-- end of maincontentLargeHolder -->\n";
}
/*
function upload_til_ftp($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn){
	global $charset;
	global $db;
	global $bruger_id;
	global $exec_path;

	if (!isset($exec_path)) $exec_path="/usr/bin";
	
	if (file_exists("../temp/$db/$filnavn")) {
		
		$ftpfilnavn="bilag_".$bilag_id;
		
		$r=db_fetch_array(db_select("select * from grupper where art='FTP'",__FILE__ . " linje " . __LINE__));
		$box1=$r['box1'];
		$box2=$r['box2'];
		$box3=$r['box3'];
		if ($kilde=="kassekladde") {
			$mappe=$r['box4'];
			$undermappe="kladde_$kilde_id";
			$ftpfilnavn="bilag_".$bilag_id;
		} else {
			$mappe=$r['box5'];
			$undermappe="debitor_$kilde_id";
			$ftpfilnavn="doc_".$bilag_id;
		}
		$fp=fopen("../temp/$db/ftpscript.$bruger_id","w");
		if ($fp) {
			fwrite ($fp, "mkdir $mappe\ncd $mappe\nmkdir $undermappe\ncd $undermappe\nput $ftpfilnavn\nbye\n");
		}
		fclose($fp);
		$fp=fopen("../temp/$db/ftplog","w");
		fwrite ($fp, "cd ../temp/$db\n\rmv \"$filnavn\" \"$ftpfilnavn\"\n\r$exec_path/ncftp ftp://$box2:$box3@$box1\n\rrm $ftpfilnavn\n\r");
		fclose($fp);
		$kommando="cd ../temp/$db\nmv \"$filnavn\" \"$ftpfilnavn\"\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id >> ftplog\nrm $ftpfilnavn\n";#rm ftpscript.$bruger_id";
		system ($kommando);
		$fp=fopen("../temp/$db/ftpscript.$bruger_id","w");
		if ($fp) {
			fwrite ($fp, "cd $mappe\ncd $undermappe\nget $ftpfilnavn\nbye\n");
		}
		fclose($fp);
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id >> ftplog\n";
		system ($kommando);
		$langt_filnavn="../temp/$db/".$ftpfilnavn;
		if (file_exists($langt_filnavn)) {
			$filnavn=db_escape_string($filnavn);
			db_modify("update $kilde set dokument='$filnavn' where id='$bilag_id'",__FILE__ . " linje " . __LINE__);
			print "<BODY onLoad=\"javascript:alert('$filnavn er indl&aelig;st')\">";
		} else {
			print "<BODY onLoad=\"javascript:alert('indl&aelig;sning af $filnavn fejlet')\">";
		}
	} print "<BODY onLoad=\"javascript:alert('indl&aelig;sning af $filnavn fejlet')\">";
	if ($kilde=="kassekladde") $tmp="../finans/kassekladde.php?kladde_id=$kilde_id&fokus=$fokus";
	else $tmp="../debitor/historikkort.php?id=$kilde_id";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">";
}
*/
function vis_bilag($kilde_id,$kilde,$bilag_id,$fokus,$filnavn){

	global $charset;
	global $db;
	global $bruger_id;
	global $exec_path;
	
	if (!isset($exec_path)) $exec_path="/usr/bin";

	$ftpfilnavn="bilag_".$bilag_id;
	$r=db_fetch_array(db_select("select * from grupper where art='FTP'",__FILE__ . " linje " . __LINE__));
	$box1=$r['box1'];
	$box2=$r['box2'];
	$box3=$r['box3'];
	if ($kilde=="kassekladde") {
		$mappe=$r['box4'];
		$undermappe="kladde_$kilde_id";
		$ftpfilnavn="bilag_".$bilag_id;
	} else {
		$mappe=$r['box5'];
		$undermappe="debitor_$kilde_id";
		$ftpfilnavn="doc_".$bilag_id;
	}
	
	$fp=fopen("../temp/$db/ftpscript.$bruger_id","w");
	if ($fp) {
		fwrite ($fp, "cd $mappe\ncd $undermappe\nget $ftpfilnavn\nbye\n");
	}
	fclose($fp);
	$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id > ftplog\nmv \"$ftpfilnavn\" \"$filnavn\"\n";
	system ($kommando);
	print "<tr><td width=100% height=100% align=\"center\" valign=\"middle\"><iframe frameborder=\"no\" width=\"100%\" height=\"100%\" scrolling=\"auto\" src=\"vis_bilag.php?filnavn=$filnavn&db=$db&bilag_id=$bilag_id&kilde_id=$kilde_id&kilde=$kilde\"></iframe></td></tr>";
}

function ret_bilag() {
	global $charset;
	
	$bilag_id=if_isset($_GET['bilag_id']);
	$kilde=if_isset($_GET['kilde']);
	$kilde_id=if_isset($_GET['kilde_id']);
	/*
// Query til kategori
	if ($kilde=='sager') {
	$x=0;
	$q=db_select("select distinct(kategori) from bilag where assign_to = 'sager' order by kategori",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['kategori']) {
			$x++;
			$sags_kat[$x]=$r['kategori'];
		}
	}
}

// Query til fase
if ($kilde=='sager') {
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tjek_id[$x]=$r['id'];
		$tjek_sub_id[$x]=$r['sub_id'];
		$tjek_punkt[$x]=$r['tjekpunkt']; 
		$tjek_fase[$x]=$r['fase']*1;
		$x++;
	}
}
*/
$r=db_fetch_array(db_select("select * from mappebilag where id='$bilag_id'",__FILE__ . " linje " . __LINE__));
	$bilag_beskrivelse=$r['beskrivelse'];
	//$bilag_fase=$r['bilag_fase'];
	//$bilag_kategori=$r['kategori'];
	
print "<div class=\"maincontentLargeHolder\">\n";
print "<form method=\"post\" action=\"bilag_mappe.php?mappe_id=$kilde_id&amp;bilag_id=$bilag_id\">\n";
print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
print "<tbody>\n";
print "<tr><td width=\"100%\" align=\"center\">\n";
print "<table width=\"500\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid lightgray; padding: 0pt 0pt 1px;\">\n";
print "<tbody>\n";
print "<tr><td colspan=\"2\" width=\"100%\" align=\"center\">Ret fase &amp; katgori i bilag</td></tr>\n";
print "<tr><td colspan=\"2\" width=\"100%\" align=center><br>\n";

print "<input type=\"hidden\" name=\"kilde_id\" value=\"$kilde_id\">\n";
print "<input type=\"hidden\" name=\"kilde\" value=\"$kilde\">\n";
print "<input type=\"hidden\" name=\"bilag_id\" value=\"$bilag_id\">\n";

print "</td></tr>\n";
if ($kilde=='sager') {
	print "<tr><td>Beskrivelse</td><td><input style=\"width:400px\" type=\"text\" name=\"beskrivelse\" value=\"$bilag_beskrivelse\"></td></tr>\n";
	//print "<tr><td>Beskrivelse</td><td><b>$bilag_beskrivelse</b></td></tr>\n";
	/*
	print "<tr><td>Fase</td><td><select style=\"width:400px\" name=\"bilag_fase\">\n";
	for($y=0;$y<=count($tjek_id);$y++) {
			if ($bilag_fase==$tjek_punkt[$y]) print "<option value=\"$tjek_punkt[$y]\">$tjek_punkt[$y]&nbsp;</option>";
		}
		for($y=0;$y<=count($tjek_id);$y++) {
			if ($bilag_fase!=$tjek_punkt[$y]) print "<option value=\"$tjek_punkt[$y]\">$tjek_punkt[$y]&nbsp;</option>";
		}
	print "</select></td></tr>\n";
	if ($sags_kat[1]) {
		print "<tr><td>Kategori</td><td><select style=\"width:400px\" name=\"kategori\">\n";
		if ($bilag_kategori==NULL) print "<option value=\"\">&nbsp;</option>\n";
		$x=1;
		while ($sags_kat[$x]) {
			if ($bilag_kategori==$sags_kat[$x]) print "<option value=\"$sags_kat[$x]\">$sags_kat[$x]</option>\n";
			$x++;
		}
		$x=1;
		while ($sags_kat[$x]) {
			if ($bilag_kategori!=$sags_kat[$x]) print "<option value=\"$sags_kat[$x]\">$sags_kat[$x]</option>\n";
			$x++;
		}
		if ($bilag_kategori!=NULL) print "<option value=\"\">&nbsp;</option>\n";
		print "</select></td></tr>\n";
	}
	#print "<tr><td>Evt ny kategori</td><td><input style=\"width:400px\" type=\"text\" name=\"ny_kategori\"></td></tr>\n";
	*/
}

#print "<tr><td>V&aelig;lg bilag:</td><td><input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>\n";
print "<tr><td colspan=\"2\"><br></td></tr>\n";
print "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"ret_bilag\" class=\"button gray medium\" value=\"Gem\" /></td></tr>\n";
print "</tbody>\n";
print "</table>\n";
print "</td></tr>\n";
print "</tbody>\n";
print "</table>\n";
print "</form>\n";
print "</div><!-- end of maincontentLargeHolder -->\n";
}
//print "</tbody></table>";
print "</div><!-- end of wrapper2 -->\n";

?>