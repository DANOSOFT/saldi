<?php 
	global $sprog_id; // includes/ordrefunc.php includes this file inside a function
	print "<div id=\"menubarholder\">
		<div id=\"menubar\">
			<ul class=\"navbar\">
				<li>";
					if (substr($sag_rettigheder,2,1)) print "<a $menu_sager href=\"../sager/sager.php\">".findtekst('2774|Sager', $sprog_id)."</a>";
					else print "<a $menu_sager href=\"\">------</a>";
				print "</li><li>";
					if (substr($sag_rettigheder,2,1)) print "<a $menu_planlaeg href=\"../sager/planlaeg.php\">".findtekst('2775|Planlægning', $sprog_id)."</a>";
					else print "<a $menu_planlaeg href=\"#\">------</a>";
				print "</li><li>";
					if (substr($sag_rettigheder,5,1)) print "<a $menu_dagbog href=\"../sager/notat.php\">".findtekst('2776|Dagbog', $sprog_id)."</a>";
					else print "<a $menu_dagbog href=\"\">------</a>";
				print "</li><li>";
					if (substr($sag_rettigheder,4,1)) print "<a $menu_kunder href=\"../sager/kunder.php\">".findtekst('991|Kunder', $sprog_id)."</a>";
					else print "<a $menu_kunder href=\"\">------</a>";
				print "</li><li>";
					if($menu_loen) print "<a $menu_loen href=\"../sager/loen.php\">".findtekst('2784|Løn', $sprog_id)."</a>";
					else print "<a $menu_ansatte href=\"../sager/loen.php\">".findtekst('2784|Løn', $sprog_id)."</a>";
				print "</li><li>";
					#if (substr($sag_rettigheder,3,1)) print "<a $menu_ansatte href=\"ansatte.php\">Ansatte</a>";
					#else print "<a $menu_ansatte href=\"\">------</a>";
					if(substr($sag_rettigheder,2,1)) print "<a $menu_certificering href=\"../sager/certificering.php\">".findtekst('2777|Certificering', $sprog_id)."</a>";
					else print "<a $menu_certificering href=\"\">------</a>";
				print "</li><li>";
					if (substr($sag_rettigheder,3,1)) print "<a href=\"../index/menu.php\">".findtekst('2778|Regnskab', $sprog_id)."</a>";
					else print "<a href=\"\">------</a>";
				print "</li>
				<!--<li class=\"search\">
					<form action=\"\" method=\"post\">
						<input type=\"text\" name=\"countries\" id=\"countries\"/>
					</form>
				</li>-->
			</ul>
			<!-- ny søgebar-->
			<!--<div class=\"navbarsearch\">
				<form action=\"\" method=\"post\">
					<input class=\"clearable\" type=\"text\" name=\"countries\" id=\"countries\"/>
				</form> 
			</div>-->  
		</div><!-- end of menubar -->
	</div><!-- end of menubarholder -->
	
	<div id=\"headerHolder\">
	<div id=\"header\">
		<!--<img src=\"\" alt=\"\" height=\"45\"/>-->";
		if ($menu_planlaeg && $planlaeg_sager) {
			if (if_isset($_SESSION['nysager'])) $vis=$_SESSION['nysager'];
			if (if_isset($_SESSION['ASC'])) $sort=$_SESSION['ASC'];
			
			if ($sort=='ASC') {
				print "
				<div class=\"headerLft\"><p>&nbsp;</p>
					<a href=\"../sager/planlaeg_sager.php?sort=DESC\" class=\"button gray small headerMM\">&#9660;</a>
				</div>";
			} else {
				print "
				<div class=\"headerLft\"><p>&nbsp;</p>
					<a href=\"../sager/planlaeg_sager.php?sort=ASC\" class=\"button gray small headerMM\">&#9650;</a>
				</div>";
			}
			if ($vis=='nysager') {
				print "
				<div class=\"headerLft\"><p>&nbsp;</p>
					<a href=\"../sager/planlaeg_sager.php?vis=aktsager\" class=\"button green small headerMM\">".findtekst('8418|Aktuelle sager', $sprog_id)."</a>
				</div>";
			} else {
				print "
				<div class=\"headerLft\"><p>&nbsp;</p>
					<a href=\"../sager/planlaeg_sager.php?vis=nysager\" class=\"button green small headerMM\">".findtekst('8419|Alle sager', $sprog_id)."</a>
				</div>";
			}
		}
		if ($menu_planlaeg && $planlaeg_opgaver) {
			if (if_isset($_SESSION['nyopgaver'])) $vis=$_SESSION['nyopgaver'];
			if (if_isset($_SESSION['ASCopg'])) $sort=$_SESSION['ASCopg'];
			if ($sort=='ASCopg') {
				print "
				<div class=\"headerLft\"><p>&nbsp;</p>
					<a href=\"../sager/planlaeg_opgaver.php?sort=DESC\" class=\"button gray small headerMM\">&#9660;</a>
				</div>";
			} else {
				print "
				<div class=\"headerLft\"><p>&nbsp;</p>
					<a href=\"../sager/planlaeg_opgaver.php?sort=ASCopg\" class=\"button gray small headerMM\">&#9650;</a>
				</div>";
			}
			if ($vis=='nyopgaver') {
				print "
				<div class=\"headerLft\"><p>&nbsp;</p>
					<a href=\"../sager/planlaeg_opgaver.php?vis=aktopgaver\" class=\"button green small headerMM\">".findtekst('8411|Aktuelle opgaver', $sprog_id)."</a>
				</div>";
			} else {
				print "
				<div class=\"headerLft\"><p>&nbsp;</p>
					<a href=\"../sager/planlaeg_opgaver.php?vis=nyopgaver\" class=\"button green small headerMM\">".findtekst('2949|Alle opgaver', $sprog_id)."</a>
				</div>";
			}
		}
		print "
		<div class=\"headerRght\"><p>".findtekst('2780|Du er logget ind som', $sprog_id)." <b>$ansat_navn</b></p>
			<a href=\"../index/logud.php\" class=\"button rosy small headerbtn\">".findtekst('93|Log ud', $sprog_id)."</a>
		</div>
	</div><!-- end of header -->
	</div><!-- end of headerHolder -->";
?>
