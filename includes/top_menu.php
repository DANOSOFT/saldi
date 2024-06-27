<?php
// 20210402 LEO - Translated these texts to English 
// 20210710 LEO - Used this to correct the Illegal string offset bug 
// 20220728 MSC - Edited leverandører faktura link from ?valg=fakture to ?valg=faktura
// 20220912 MSC - Implementing new design
// 20221011 MSC - Added link to feedback mail in systemdata

$site = "";
$subsite = "";
$site_names = "";
$subsite_names = "";
$key = "";
$subkey = "";

$border='border:0px';
$bgcolor='#eee';
$textcolor='';
$textcolor2='';
$textcolor3='';
$bgcolor2='';
$bgcolor3='';
$bgcolor4='';
$bgcolor5='#ddd';
$bgcolor01='';
$bgnuance1 ='';
$bgnuance = '';
$font ='';
$color = 'black';
$linjebg = '';

if(isset($_GET['title'])){
	$site = $_GET['title'];
}
if(isset($_GET['page'])){
	$subsite = $_GET['page'];
}

	if($site == ""){
		$key = "frontpage";
	} else {
		$key = $site;
	}
	if($subsite == ""){
		$subkey = "frontpage";
	} else {
		$subkey = $subsite;
	}
	$site_names = explode(" ", $site_names); #20210710
	$site_names[$key] = 'navbarActive';
	$subsite_names = explode(" ", $subsite_names);
	$subsite_names[$subkey] = 'class="subnavbarActive"';
	
function subsite_names ($a) {
	global $subsite_names;
	if (isset ($subsite_names[$a])) return ($subsite_names[$a]);
	else return (NULL);
}

$qtxt = "select var_value from settings where var_grp='debitor' and var_name='mySale'";
($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$showMySale=trim($r['var_value']):$showMySale=NULL;
$qtxt = "select var_value from settings where var_grp='rental'";
($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$showRental=trim($r['var_value']):$showRental=NULL;

#print "<div class='logo-bg'>";
#print "</div>";

print "<div class='logobar'><div class='logo-link'><a href=../index/menu.php class='logo-link logo'>";
		print "<div class='logo'>";
			print "     <div class='logo-container' title='Nuværende regnskab: $regnskab - Klik for at komme tilbage til forsiden'>";
#				print "     <a href='../index/menu.php' class='logolink'>";
				print "      <img style='pointer-events:none;' class='logoimg' src='../img/topmenu/logo.PNG'> <div class='logo-name'>Saldi</div>";
#				print "    </a>";
			print "     </div>";
		print "</div>";
		print "</div>";
print "</div></a>";

print "<div class='navbar'>";
print "  <div class='menuBar'>";
print "    <ul class='dropdownMenu'>";
print "     <li class='dropDown'>";
print "        <a href='#contact' class='dropDownbtn'>".findtekst(600,$sprog_id)."</a>";
print "          <div class='dropdownContent-Fin'>";
print "          <a href='../finans/kladdeliste.php'>".findtekst(105,$sprog_id)."</a>";
print "          <a href='../finans/regnskab.php'>".findtekst(849,$sprog_id)."</a>";
print "          <a href='../finans/budget.php'>".findtekst(1067,$sprog_id)."</a>";
print "          <a href='../finans/rapport.php'>".findtekst(603,$sprog_id)."</a>";
print "          </div>";
print "      </li>";
print "      <li class='dropDown'>";
print "        <a href='#contact' class='dropDownbtn'>".findtekst(991,$sprog_id)."</a>";
print "          <div class='dropdownContent-Kun'>";
print "          <a href='../debitor/ordreliste.php?valg=ordrer'>".findtekst(985,$sprog_id)."</a>";
print "          <a href='../debitor/ordreliste.php?valg=faktura'>".findtekst(986,$sprog_id)."</a>";
print "          <a href='../debitor/debitor.php?valg=debitor'>".findtekst(606,$sprog_id)."</a>";
print "          <a href='../debitor/debitor.php?valg=historik'>".findtekst(131,$sprog_id)."</a>";
print "          <a href='../debitor/rapport.php'>".findtekst(124,$sprog_id)."</a>";
if ($showMySale) print "          <a href='../debitor/debitor.php?valg=kommission'>".findtekst(909,$sprog_id)."</a>";
if ($showRental) print "          <a href='../debitor/debitor.php?valg=rental'>".findtekst(1116,$sprog_id)."</a>";
$qtxt="select id from grupper where art = 'POS' and kodenr = '1' and box1 >= '1'"; #20180807
if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	if ($popup)	print "";
	else	print "";
} elseif (file_exists('../sager/sager.php')){ // Hvis 'sager.php' eksistere, skal der linkes tilbage til sagstyring
	print "<a href='../sager/sager.php'>".findtekst(987,$sprog_id)."</a>";
} else {
	print "";
}
print "          </div>";
print "      </li>";
print "      <li class='dropDown'>";
print "        <a href='#contact' class='dropDownbtn'>".findtekst(988,$sprog_id)."</a>";
print "          <div class='dropdownContent-Lev'>";
print "          <a href='../kreditor/ordreliste.php?valg=ordrer'>".findtekst(985,$sprog_id)."</a>";
print "          <a href='../kreditor/ordreliste.php?valg=faktura'>".findtekst(989,$sprog_id)."</a>";
print "          <a href='../kreditor/kreditor.php'>".findtekst(606,$sprog_id)."</a>";
print "          <a href='../kreditor/rapport.php'>".findtekst(603,$sprog_id)."</a>";
print "          </div>";
print "      </li>";
print "      <li class='dropDown'>";
print "        <a href='#contact' class='dropDownbtn'>".findtekst(608,$sprog_id)."</a>";
print "          <div class='dropdownContent-Lag'>";
print "          <a href='../lager/varer.php'>".findtekst(110,$sprog_id)."</a>";
print "          <a href='../lager/modtageliste.php'>".findtekst(610,$sprog_id)."</a>";
print "          <a href='../lager/rapport.php'>".findtekst(603,$sprog_id)."</a>";
print "          </div>";
print "      </li>";
print "      <li class='dropDown'>";
print "        <a href='#contact' class='dropDownbtn'>".findtekst(611,$sprog_id)."</a>";
print "          <div class='dropdownContent-Sys'>";
print "          <a href='../systemdata/kontoplan.php'>".findtekst(113,$sprog_id)."</a>";
print "          <a href='../systemdata/syssetup.php'>".findtekst(613,$sprog_id)."</a>";
print "          <a href='../systemdata/feedbackmail.php'>Feedback Mail</a>";
print "          <a href='../admin/backup.php'>".findtekst(521,$sprog_id)."</a>";
print "          </div>";
print "      </li>";
print "      <li class='dropDown'>";
print "        <a href='#contact' class='dropDownbtn'>".findtekst(990,$sprog_id)."</a>";
print "          <div class='dropdownContent-Bru'>";
print "          <a href='#' onClick=MyWindow=window.open('http://www.saldi.dk/dok/komigang.html','MyWindow','width=600,height=600'); return false;>".findtekst(92,$sprog_id)."</a>";
print "          <a href='../index/logud.php'>".findtekst(93,$sprog_id)."</a>";
print "          </div>";
print "      </li>";
print "    </ul>";
print "  </div>";
print "</div>";

print "<div class='flex-container'>";

print "<div class='content'>";