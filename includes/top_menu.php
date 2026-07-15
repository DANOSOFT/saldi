<?php
// 20210402 LEO - Translated these texts to English 
// 20210710 LEO - Used this to correct the Illegal string offset bug 
// 20220728 MSC - Edited leverandører faktura link from ?valg=fakture to ?valg=faktura
// 20220912 MSC - Implementing new design
// 20221011 MSC - Added link to feedback mail in systemdata
// 20260306 Sawaneh - Added Simple guides link.

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
print "          <a href='#' onclick='document.getElementById(\"guideOverlayTop\").classList.add(\"active\"); return false;'>Guides</a>";
print "          <a href='../index/logud.php'>".findtekst(93,$sprog_id)."</a>";
print "          </div>";
print "      </li>";
print "    </ul>";
print "  </div>";
print "</div>";

// Guide Overlay for top menu pages
print "<div class='guide-overlay-top' id='guideOverlayTop' onclick='if(event.target===this) this.classList.remove(\"active\");'>";
print "  <div class='guide-modal-top'>";
print "    <div class='guide-modal-top-header'>";
print "      <h2><i class='bx bx-book-open'></i> Guides</h2>";
print "      <button class='guide-modal-top-close' onclick='document.getElementById(\"guideOverlayTop\").classList.remove(\"active\");'>&times;</button>";
print "    </div>";
print "    <div class='guide-modal-top-body'>";
$guide_select_txt = ($sprog_id == 1) ? 'Vælg en guide for at åbne den i en ny fane.' : 'Select a guide to open it in a new tab.';
$finance_label = ($sprog_id == 1) ? 'Regnskab (Finance)' : 'Finance Guide';
$scaffolding_label = ($sprog_id == 1) ? 'Stillads (Scaffolding)' : 'Scaffolding Guide';
print "      <p>$guide_select_txt</p>";
print "      <ul class='guide-list-top'>";
print "        <li><a href='../guides/pdf/finance_guide_da.pdf' target='_blank' onclick='document.getElementById(\"guideOverlayTop\").classList.remove(\"active\");'><i class='bx bx-coin-stack'></i> $finance_label <i class='bx bx-link-external' style=\"margin-left:auto;color:#999;\"></i></a></li>";
print "        <li><a href='../guides/pdf/scaffolding_guide_da.pdf' target='_blank' onclick='document.getElementById(\"guideOverlayTop\").classList.remove(\"active\");'><i class='bx bx-layer'></i> $scaffolding_label <i class='bx bx-link-external' style=\"margin-left:auto;color:#999;\"></i></a></li>";
print "      </ul>";
print "    </div>";
print "  </div>";
print "</div>";
print "<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>";
print "<style>";
print ".guide-overlay-top{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:9999;justify-content:center;align-items:center;}";
print ".guide-overlay-top.active{display:flex;}";
print ".guide-modal-top{background:#fff;border-radius:12px;width:420px;max-width:90vw;box-shadow:0 12px 40px rgba(0,0,0,0.25);overflow:hidden;animation:guideSlide .25s ease;}";
print "@keyframes guideSlide{from{transform:translateY(-30px);opacity:0}to{transform:translateY(0);opacity:1}}";
print ".guide-modal-top-header{background:#114691;color:#fff;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;}";
print ".guide-modal-top-header h2{font-size:18px;font-weight:600;margin:0;display:flex;align-items:center;gap:10px;color:#fff;}";
print ".guide-modal-top-header h2 i{font-size:22px;}";
print ".guide-modal-top-close{background:none;border:none;color:#fff;font-size:24px;cursor:pointer;opacity:0.8;}";
print ".guide-modal-top-close:hover{opacity:1;}";
print ".guide-modal-top-body{padding:16px 24px 24px;}";
print ".guide-modal-top-body p{color:#666;font-size:13px;margin-bottom:16px;}";
print ".guide-list-top{list-style:none;padding:0;margin:0;}";
print ".guide-list-top li{margin-bottom:8px;}";
print ".guide-list-top li a{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:8px;background:#f5f7fa;color:#333;text-decoration:none;font-size:15px;font-weight:500;transition:background .2s,transform .15s;border:1px solid #e8ecf1;}";
print ".guide-list-top li a:hover{background:#e8eef6;transform:translateX(4px);border-color:#c5d3e8;color:#333;}";
print ".guide-list-top li a i{font-size:22px;color:#114691;min-width:28px;text-align:center;}";
print "</style>";
print "<script>document.addEventListener('keydown',function(e){if(e.key==='Escape'){var o=document.getElementById('guideOverlayTop');if(o&&o.classList.contains('active'))o.classList.remove('active');}});</script>";

print "<div class='flex-container'>";

print "<div class='content'>";