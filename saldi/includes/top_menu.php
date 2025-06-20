<?php
$site = "";
$subsite = "";
$site_names = "";
$subsite_names = "";
$key = "";
$subkey = "";

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
	$site_names[$key] = 'navbarActive';
	$subsite_names[$subkey] = 'class="subnavbarActive"';
	
function subsite_names ($a) {
	global $subsite_names;
	if (isset ($subsite_names[$a])) return ($subsite_names[$a]);
	else return (NULL);
}	
	
print "<div id=\"headerTop\">\n";
print "	<div id=\"menubarholder\">\n";
print "		<div id=\"menubar\">\n";
print "			<ul class=\"navbar\">\n";
print "				<li>\n";
print "					<a href=\"#\" class=\"navbarlink\">Finans</a>\n";
print "					<ul class=\"subnavbar\">\n";
print "						<li><a href=\"../finans/kladdeliste.php\">Kassekladde</a></li>\n";
print "						<li><a href=\"../finans/regnskab.php\">Regnskab</a></li>\n";
print "						<li><a href=\"../finans/budget.php\">Budget</a></li>\n";
print "						<li><a href=\"../finans/rapport.php\">Rapporter</a></li>\n";
print "					</ul>\n";
print "				</li>\n";
print "				<li>\n";
print "					<a href=\"#\" class=\"navbarlink\">Kunder</a>\n";
print "					<ul class=\"subnavbar\">\n";
print "						<li><a href=\"../debitor/ordreliste.php?valg=ordrer\">Åbne ordrer</a></li>\n";
print "						<li><a href=\"../debitor/ordreliste.php?valg=faktura\">Fakturerede ordrer</a></li>\n";
print "						<li><a href=\"../debitor/debitor.php?valg=debitor\">Konti</a></li>\n";
print "						<li><a href=\"../debitor/debitor.php?valg=historik\">Historik</a></li>\n";
print "						<li><a href=\"../debitor/rapport.php\">Rapporter</a></li>\n";
print "						<li><a href=\"../sager/sager.php\">Sagsstyring</a></li>\n";
print "					</ul>\n";
print "				</li>\n";
print "				<li>\n";
print "					<a href=\"#\" class=\"navbarlink\">Leverandører</a>\n";
print "					<ul class=\"subnavbar\">\n";
print "						<li><a href=\"../kreditor/ordreliste.php?valg=ordrer\">Åbne ordrer</a></li>\n";
print "						<li><a href=\"../kreditor/ordreliste.php?valg=fakture\">Fakture</a></li>\n";
print "						<li><a href=\"../kreditor/kreditor.php\">Konti</a></li>\n";
print "						<li><a href=\"../kreditor/rapport.php\">Rapporter</a></li>\n";
#print "						<li ".subsite_names('kreditor/ordreliste')." ". subsite_names('kreditor/ordre')."><a href=\"index.php?page=kreditor/ordreliste&amp;title=kreditor\">Ordre</a></li>\n";
#print "						<li ".subsite_names('kreditor/fakture')."><a href=\"index.php?page=kreditor/fakture&amp;title=kreditor\">Fakture</a></li>\n";
#print "						<li ".subsite_names('kreditor/kreditor')." ". subsite_names('kreditor/kreditorkort')." ". subsite_names('kreditor/ansatte')."><a href=\"index.php?page=kreditor/kreditor&amp;title=kreditor\">Konti</a></li>\n";
#print "						<li ".subsite_names('kreditor/rapport')." ". subsite_names('kreditor/kontokort')."><a href=\"index.php?page=kreditor/rapport&amp;title=kreditor\">Rapporter</a></li>\n";
print "					</ul>\n";
print "				</li>\n";
print "				<li>\n";
print "					<a href=\"#\" class=\"navbarlink\">Lager</a>\n";
print "					<ul class=\"subnavbar\">\n";
print "						<li><a href=\"../lager/varer.php\">Varer</a></li>\n";
print "						<li><a href=\"../lager/modtageliste.php\">Varemodtagelse</a></li>\n";
print "						<li><a href=\"../lager/rapport.php\">Rapporter</a></li>\n";
print "					</ul>\n";
print "				</li>\n";
print "				<li>\n";
print "					<a href=\"#\" class=\"navbarlink\">Systemdata</a>\n";
print "					<ul class=\"subnavbar\">\n";
print "						<li><a href=\"../systemdata/kontoplan.php\">Kontoplan</a></li>\n";
print "						<li><a href=\"../systemdata/syssetup.php\">Indstillinger</a></li>\n";
print "						<li><a href=\"../admin/backup.php\">Sikkerhedskopi</a></li>\n";
print "					</ul>\n";
print "				</li>\n";
print "				<li>\n";
print "					<a href=\"#\" class=\"navbarlink\">Bruger</a>\n";
print "					<ul class=\"subnavbarLast\">\n";
#print "						<li><a href=\"index.php?page=frontpage\">Forside</a></li>\n";
print "						<li><a href=\"#\" onClick=\"MyWindow=window.open('http://www.saldi.dk/dok/komigang.html','MyWindow','width=600,height=600'); return false;\">Vejledning</a></li>\n";
#print "						<li><a href=\"#\">Indstillinger</a></li>\n";
print "						<li><a href=\"../index/logud.php\">Log ud</a></li>\n";
print "					</ul>\n";
print "				</li>\n";
print "			</ul>\n";
print "		</div><!-- end of menubar -->\n";
print "	</div><!-- end of menubarholder -->\n";
print "</div><!-- end of headerTop -->\n";