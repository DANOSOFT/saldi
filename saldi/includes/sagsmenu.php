<?php
	print "<div id=\"menubarholder\">
		<div id=\"menubar\">
			<ul class=\"navbar\">
				<li>";
					if (substr($sag_rettigheder,2,1)) print "<a $menu_sager href=\"sager.php\">Sager</a>";
					else print "<a $menu_sager href=\"\">------</a>";
				print "</li><li>";
					if (substr($sag_rettigheder,5,1)) print "<a $menu_dagbog href=\"notat.php\">Dagbog</a>";
					else print "<a $menu_dagbog href=\"\">------</a>";
				print "</li><li>";
					if (substr($sag_rettigheder,4,1)) print "<a $menu_dagbog href=\"kunder.php\">Kunder</a>";
					else print "<a $menu_kunder href=\"\">------</a>";
				print "</li><li>";
					print "<a $menu_loen href=\"loen.php\">Løn</a>";
				print "</li><li>";
					if (substr($sag_rettigheder,3,1)) print "<a $menu_dagbog href=\"ansatte.php\">Ansatte</a>";
					else print "<a $menu_ansatte href=\"\">------</a>";
				print "</li><li>";
					if (substr($sag_rettigheder,3,1)) print "<a $menu_dagbog href=\"../index/menu.php\">Regnskab</a>";
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

	<div id=\"header\">
		<img src=\"\" alt=\"\" height=\"45\"/>
		<div class=\"headerRght\"><p>Du er logget ind som <b>$ansat_navn</b></p>
			<a href=\"../index/logud.php\" class=\"button rosy small headerbtn\">Log ud</a>
		</div>
	</div><!-- end of header -->";
?>
ny søgebar-->
			<!--<div class=\"navbarsearch\">
				<form action=\"\" method=\"post\">
					<input class=\"clearable\" type=\"text\" name=\"countries\" id=\"countries\"/>
				</form> 
			</div>-->  
		</div><!-- end of menubar -->
	</div><!-- end of menubarholder -->

	<div id=\"header\">
		<!--<img src=\"\" alt=\"\" height=\"45\"/>-->";
		if ($menu_loen || $menu_ansatte) {
		print "
		<div class=\"headerLft\"><p>&nbsp;</p>
			<a href=\"../sager/medarbejdermappe.php\" class=\"button orange small headerMM\">Medabejder mappe</a><!-- data-notifications=\"10\" -->
		</div>";
		}
		print "
		<div class=\"headerRght\"><p>Du er logget ind som <b>$ansat_navn</b></p>
			<a href=\"../index/logud.php\" class=\"button rosy small headerbtn\">Log ud</a>
		</div>
	</div><!-- end of header -->";
?>
