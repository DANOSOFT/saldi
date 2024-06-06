<?php
@session_start();
$s_id=session_id();

$bg="nix";
$header='nix';

$menu_sager=NULL;
$menu_planlaeg=NULL;
$menu_dagbog=NULL;
$menu_kunder=NULL;
$menu_loen=NULL;
$menu_ansatte=NULL;
$menu_certificering=NULL;
$menu_medarbejdermappe='id="menuActive"';

$modulnr=0;
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

//$sag_id=if_isset($_GET['sag_id']);
//$konto_id=if_isset($_GET['konto_id']);
$funktion=if_isset($_GET['funktion']);
if (!$funktion) $funktion="medarbejdermappeliste";  

	
		global $brugernavn;
		global $db;
		global $regnskab;
		global $ansat_navn;
		
		include_once '../includes/top_header_sager_small.php';
		include_once '../includes/sagsmenu.php';
		
		$funktion();
		
		print "</div><!-- end of wrapper2 -->\n";
		print "</body>\n";
		print "</html>\n";
		
		
function medarbejdermappeliste() {

		global $sag_rettigheder;
		
		$ans_id=if_isset($_GET['ans_id']);
		$unsetans=if_isset($_GET['unsetans']);
		
		if ($_GET['ans_id']) {
			$_SESSION['ans_id']=$ans_id;
		} else {
			$ans_id=$_SESSION['ans_id'];
		}
		
		if ($unsetans) {
			unset($_SESSION['ans_id']);
			$ans_id = NULL;
			$unsetans = NULL;
		}
		//echo "ansat_id: $ans_id<br>";
		//echo "unsetans: $unsetans";
		print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li>";
					if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					print "</li>
				<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>";
				
				print "<li>Medarbejdermappe menu</li>
			</ul>
		</div><!-- end of breadcrumbbar -->\n";

		if (substr($sag_rettigheder,2,1)) {
		
			if ($ans_id) {
				$r=db_fetch_array(db_select("SELECT id, navn, initialer FROM ansatte WHERE konto_id = 1 AND id='$ans_id'",__FILE__ . " linje " . __LINE__));
				$ans_id=$r['id'];
				$ans_navn=$r['navn'];
				$ans_ini=$r['initialer'];
			}
			
			print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
			print "<tbody>\n";
			print "<tr><td width=\"100%\" align=\"center\">\n";
			//print "<p>dropdown / ajax søgebox</p>";
			print "<div class=\"contentsoeg\">
			<form name=\"kundesoeg\" action=\"medarbejdermappe.php?funktion=medarbejdermappeliste\" method=\"get\">
				<table border=\"0\" cellspacing=\"0\" width=\"500\">
					<thead>
						<tr>
							<th width=\"100%\">Medarbejder navn</th>
							<th colspan=\"2\">&nbsp;</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><input class=\"textinput mm_medarbejdernavn\" type=\"text\" value=\"\" id=\"mm_medarbejdernavn\" name=\"mm_medarbejdernavn\" tabindex=\"1\"/></td>
							<td style=\"padding:0px;\"><input type=\"hidden\" class=\"ans_id\" value=\"\" name=\"ans_id\"></td>   
							<td align=\"center\"><input type=\"submit\" value=\"Vælg ansat\" name=\"vaelgansat\" class=\"button gray small\" tabindex=\"2\"></td>
						</tr>
					</tbody>
				</table>
			</form>
			</div><!-- end of contentsoeg -->";
			print "<table width=\"500\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
			print "<tbody>\n";
			//print "<tr><td>&nbsp;</td></tr>\n";
			print "<tr><td align=\"left\"><a href=\"medarbejdermappe.php?funktion=medarbejdermappeliste&amp;unsetans=unset\" class=\"button gray small\">Slet ansat</a></td></tr>\n";
			print "</tbody>\n";
			print "</table>\n";
			print "<table width=\"500\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"kontrolskema_liste\" >\n";
			print "<tbody>\n";
			print "<tr><td colspan=\"2\" width=\"100%\" align=\"center\"><h4>Medarbejdermappe menu</h4></td></tr>\n";
			if ($ans_id) {
				print "<tr><td colspan=\"2\" width=\"100%\" align=\"center\" style=\"color: green;\"><h4>($ans_ini) $ans_navn</h4></td></tr>\n";
			} else {
				print "<tr><td colspan=\"2\" width=\"100%\" align=center><br></td></tr>\n";
			}
			print "</tbody>\n";
			print "<tbody class=\"dataTableZebra dataTableTopBorder\">\n";
			print "<tr><td>Fælles-mappe</td><td class=\"alignRight\"><a href=\"medarbejdermappe.php?funktion=faellesmappe\" title=\"Gå til medarbejder-håndbog her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
			print "<tr><td>Medarbejder-mappe</td><td class=\"alignRight\"><a href=\"medarbejdermappe.php?funktion=medarbejdermappe\" title=\"Gå til medarbejder-mappe her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
			print "<tr><td>Arbejdssedler</td><td class=\"alignRight\"><a href=\"mm_kontrolskemaer.php?funktion=arbejdsseddelskemaliste\" title=\"Gå til arbejdssedler her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
			print "<tr><td>Kontrolsedler</td><td class=\"alignRight\"><a href=\"mm_kontrolskemaer.php?funktion=kontrolseddelskemaliste\" title=\"Gå til kontrolsedler her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
			print "</tbody>\n";
			print "</table>\n";
			print "</td></tr>\n";
			print "</tbody>\n";
			print "</table>\n";
		} else {
			print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
			print "<tbody>\n";
			print "<tr><td width=\"100%\" align=\"center\">\n";
			//print "<p>medarbejder</p>";
			print "<table width=\"500\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"kontrolskema_liste\" >\n";
			print "<tbody>\n";
			print "<tr><td colspan=\"2\" width=\"100%\" align=\"center\"><h4>Medarbejdermappe menu</h4></td></tr>\n";
			print "<tr><td colspan=\"2\" width=\"100%\" align=center><br>\n";
			print "</tbody>\n";
			print "<tbody class=\"dataTableZebra dataTableTopBorder\">\n";
			print "<tr><td>Fælles-mappe</td><td class=\"alignRight\"><a href=\"medarbejdermappe.php?funktion=faellesmappe\" title=\"Gå til medarbejder-håndbog her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
			print "<tr><td>Medarbejder-mappe</td><td class=\"alignRight\"><a href=\"medarbejdermappe.php?funktion=medarbejdermappe\" title=\"Gå til medarbejder-mappe her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
			print "<tr><td>Arbejdssedler</td><td class=\"alignRight\"><a href=\"mm_kontrolskemaer.php?funktion=arbejdsseddelskemaliste\" title=\"Gå til arbejdssedler her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
			print "<tr><td>Kontrolsedler</td><td class=\"alignRight\"><a href=\"mm_kontrolskemaer.php?funktion=kontrolseddelskemaliste\" title=\"Gå til kontrolsedler her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
			print "</tbody>\n";
			print "</table>\n";
			print "</td></tr>\n";
			print "</tbody>\n";
			print "</table>\n";
		}
}
		
function faellesmappe() {

		global $sag_rettigheder;
		global $db;
		
		$ans_id = $_SESSION['ans_id'];
		//echo "ansat_id: $ans_id<br>";
		if ($slet_mappe=if_isset($_GET['slet_mappe'])) { // Hvis mappen indeholder filer skal disse også slettes
			if ($r=db_fetch_array(db_select("select * from mappebilag where assign_id='$slet_mappe'",__FILE__ . " linje " . __LINE__))) {
			
				$x=0;
				$q=db_select("select * from mappebilag where assign_id='$slet_mappe'",__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$bilag_id[$x] = $r['id'];
					$bilag_filtype[$x] = $r['filtype'];
					$x++;
					}
				$antal_bilag=$x;
				
				for ($y=0;$y<$antal_bilag;$y++) {
					//echo "bilag_id: $bilag_id[$y]<br>";
					unlink("../mappe/$db/$slet_mappe/$bilag_id[$y].$bilag_filtype[$y]");
					db_modify("delete from mappebilag where id = $bilag_id[$y]",__FILE__ . " linje " . __LINE__);
				}
				//rmdir("../mappe/$db/$slet_mappe");
				db_modify("delete from mappe where id = '$slet_mappe'",__FILE__ . " linje " . __LINE__);
			} else {
				//echo "der er ingen bilag"; exit();
				db_modify("delete from mappe where id = '$slet_mappe'",__FILE__ . " linje " . __LINE__);
			}
		} 
		
		if (isset($_POST['opdater'])) {
			$ny_mappe=db_escape_string($_POST['ny_mappe']);
			
			if (empty($ny_mappe)) {
				$messages = "Mappe skal have en beskrivelse!";
			}	else {
			 //echo "insert: $ny_mappe"; exit();
				db_modify("insert into mappe (beskrivelse) values ('$ny_mappe')",__FILE__ . " linje " . __LINE__);
			}
			//echo "tom: $ny_mappe"; exit();
		}
		
		$retmappe=if_isset($_POST['onoffswitch']);
		
		if ($_POST['onoffswitch']){
			if ($_POST['onoffswitch']=='on') {
				$_SESSION['retmappe']=$retmappe;
			} elseif ($_POST['onoffswitch']=='off') {
				unset($_SESSION['retmappe']);
			}
		} else {
			$retmappe=$_SESSION['retmappe'];
		}
		
		($retmappe=='on')?$checked_retmappe='checked':$checked_retmappe=NULL;
		
		print "
		<div id=\"breadcrumbbar\">
			<form name=\"retmappe\" action=\"medarbejdermappe.php?funktion=faellesmappe\" method=\"post\">
				<ul id=\"breadcrumb\">
					<li>";
						if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						print "</li>
					<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li><a href=\"medarbejdermappe.php\" title=\"Medarbejdermappe menu\">Medarbejdermappe menu</a></li>";
					print "<li>Fælles-mappe</li>\n";
					if (substr($sag_rettigheder,2,1)) {
							print "<li style=\"float:right;\">
							<div class=\"onoffswitch\">
									<input type=\"hidden\" name=\"onoffswitch\" value=\"off\" />
									<input type=\"checkbox\" name=\"onoffswitch\" class=\"onoffswitch-checkbox\" id=\"myonoffswitch\" onclick=\"this.form.submit()\" $checked_retmappe>
									<label class=\"onoffswitch-label\" for=\"myonoffswitch\">
											<span class=\"onoffswitch-inner\"><!-- --></span>
											<span class=\"onoffswitch-switch\"><!-- --></span>
									</label>
							</div>
							</li>
							<li style=\"float: right;font-size: 12px;color: #444;\">Ret mappe</li>\n";
						}
					print "
				</ul>
			</form>
		</div><!-- end of breadcrumbbar -->\n";
		
		print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
		print "<tbody><tr><td>\n";
		
		if (substr($sag_rettigheder,2,1) && ($retmappe=='on')) {
		
			print "<form name=\"ordre\" action=\"medarbejdermappe.php?funktion=faellesmappe\" method=\"post\">\n";
			print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\" class=\"ordretekstSort\">\n";
			
			if (!$r=db_fetch_array(db_select("select * from mappe",__FILE__ . " linje " . __LINE__))) {
				print "<tbody><tr><td colspan=\"5\"><i>Der er ingen mapper!</i></td></tr>\n";
			} else {
				print "<tbody class=\"dataTableZebra ordretekstListe\">\n";
				$x=0;
				$q = db_select("select * from mappe order by sort",__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$x++;
					print "<tr id=\"order_$r[id]\">\n";
					print "<td class=\"dragHandle\">&nbsp;</td>\n";
					print "<td align=\"left\" style=\"width:100%;\">".nl2br($r['beskrivelse'])."</td>\n";
					print "<td class=\"alignRight\"><a href=\"medarbejdermappe.php?funktion=vis_mappe&amp;mappe_id=$r[id]\" class=\"button blue small\">Vælg</a></td>\n";
					print "<td title=\"Ret mappe-tekst\" align=\"right\"><a href=\"medarbejdermappe.php?funktion=ret_mappe&amp;mappe_id=$r[id]\"><span style=\"width:16px; display:block;\"><span class=\"checkmark\">&nbsp;</span></span></a></td>\n";
					print "<td title=\"Slet mappe og al indhold\" align=\"left\"><a href=\"medarbejdermappe.php?funktion=faellesmappe&amp;slet_mappe=$r[id]\" onclick=\"return confirm('Vil du slette denne mappe?\\n\\nADVARSEL!\\nAl indhold i mappen vil også\\nblive slettet')\"><span style=\"width:16px; display:block;\"><span class=\"xmark\">&nbsp;</span></span></a></td>\n";
					print "</tr>\n";
				}
			}
			print "</tbody>\n";
			print "<tbody class=\"dataTableTopBorderGray\">\n";//<input class=\"inputbox\" type=\"text\" title=\"Hvis teksten skal være fed sættes <b> foran teksten og </b> efter teksten (F.eks. <b>Lorem ipsum</b>). Det samme gøres ved Italic, bare med <i> og </i>.\" style=\"text-align:left;width:800px;\" name=\"ny_linjetekst\">
			print "<tr><td valign=\"top\" colspan=\"1\"><span style=\"display:block;margin-top:3px;\">Ny&nbsp;mappe</span></td><td colspan=\"2\" style=\"width:100%;\"><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"ny_mappe\" rows=\"1\" cols=\"10\" style=\"height:16px;width:100%;\" title=\"Hvis teksten skal være fed sættes &lt;b&gt; foran teksten og &lt;/b&gt; efter teksten (F.eks. &lt;b&gt;Lorem ipsum&lt;/b&gt;). Det samme gøres ved Italic, bare med &lt;i&gt; og &lt;/i&gt;.\"></textarea></td>\n";
			print "<td colspan=\"2\" valign=\"bottom\"><input type=\"submit\" class=\"button gray medium\" value=\"Opret\" name=\"opdater\" onclick=\"javascript:docChange = false;\"></td>\n";
			print "</tr>\n";
			if ($messages) print "<tr><td colspan=\"1\">&nbsp;</td><td colspan=\"4\" align=\"left\"><i style=\"color:red;\">$messages</i></td></tr>\n";
			print "</tbody></table></form>\n";
			
		} else {
		
			print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\">\n";
			print "<tbody class=\"dataTableZebra\">\n";
			$x=0;
			$q = db_select("select * from mappe order by sort",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$x++;
				print "<tr>\n";
				print "<td align=\"left\" style=\"width:100%;\">".nl2br($r['beskrivelse'])."</td>\n";
				print "<td class=\"alignRight\"><a href=\"medarbejdermappe.php?funktion=vis_mappe&amp;mappe_id=$r[id]\" class=\"button blue small\">Vælg</a></td>\n";
				print "</tr>\n";
			}
			if (!$r=db_fetch_array(db_select("select * from mappe",__FILE__ . " linje " . __LINE__))) print "<tr><td style=\"width:100%;\"><i>Der er ingen mapper!</i></td></tr>";
			print "</tbody></table>\n";
		}
		
		print "</td></tr>\n";
		print "</tbody>\n";
		print "</table>\n";
		?>
		<script type="text/javascript">
			// Function som sortere i table
		$("document").ready(function() {
			$(".ordretekstSort .ordretekstListe").tableDnD({
				onDragClass: "highlight",
				onDrop: function(table, row) {
				var orders = $.tableDnD.serialize();
				$.post('../sager/sortmappe.php?mode=mappe', { orders : orders });
				},
				dragHandle: ".dragHandle"
			});
		});
		$("document").ready(function() {
			$(".ordretekstListe tr").hover(function() {
							$(this.cells[0]).addClass('showDragHandle');
				}, function() {
							$(this.cells[0]).removeClass('showDragHandle');
				});
		});
		</script>
		<?php
}
		
function ret_mappe() {

	global $sag_rettigheder;
	
	$messages=NULL;
	
	$mappe_id=if_isset($_GET['mappe_id']);
	
	if (isset($_POST['opdater']) && $mappe_id) {
		$mappetekst=if_isset($_POST['mappetekst']);
		
		if (empty($mappetekst)) {
			$messages = "Mappe skal have en beskrivelse!";
		} else {
			db_modify("update mappe set beskrivelse='$mappetekst' where id = '$mappe_id'",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/medarbejdermappe.php?funktion=faellesmappe\">";
		}
	}
	
	$r=db_fetch_array(db_select("select * from mappe where id='$mappe_id'",__FILE__ . " linje " . __LINE__));
	$mappetekst=$r['beskrivelse'];

	print "<div id=\"breadcrumbbar\">

		<ul id=\"breadcrumb\">
					<li>";
						if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						print "</li>
					<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li><a href=\"medarbejdermappe.php\" title=\"Medarbejdermappe menu\">Medarbejdermappe menu</a></li>
					<li><a href=\"medarbejdermappe.php?funktion=faellesmappe\" title=\"Fælles-mappe liste\">Fælles-mappe</a></li>
					<li>Ret mappe-tekst</li>
		</ul>
	</div><!-- end of breadcrumbbar -->\n";
	
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody><tr><td valign=\"top\" align=\"center\">\n";
	print "<form name=\"ret_mappe\" action=\"medarbejdermappe.php?funktion=ret_mappe&amp;mappe_id=$mappe_id\" method=\"post\">\n";
	print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\">\n";
	print "<tbody>\n";
	print "<tr><td align=\"center\" colspan=\"3\" style=\"padding-bottom: 15px;\"><h4>Ret&nbsp;mappe-tekst</h4></td></tr>\n";
	print "<tr>\n";
	print "<td valign=\"top\"><span style=\"display:block;margin-top:3px;\">Beskrivelse</span></td><td style=\"width:100%;\"><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"mappetekst\" rows=\"2\" cols=\"10\" style=\"height:16px;width:100%;\">".htmlspecialchars($mappetekst)."</textarea></td>\n";
	print "<td valign=\"bottom\"><input type=\"submit\" class=\"button gray medium\" value=\"Gem/Opdater\" name=\"opdater\"></td>\n";
	print "</tr>\n";
	if ($messages) print "<tr><td colspan=\"1\">&nbsp;</td><td colspan=\"2\" align=\"left\"><i style=\"color:red;\">$messages</i></td></tr>\n";
	print "</tbody></table></form></td></tr></tbody></table>\n";
	
}
		
function ret_bilag() {

	global $sag_rettigheder;
	
	$messages=NULL;
	
	$mappe_id=if_isset($_GET['mappe_id']);
	$bilag_id=if_isset($_GET['bilag_id']);

	if (isset($_POST['opdater']) && $bilag_id) {
		$bilagtekst=if_isset($_POST['bilagtekst']);
		
		if (empty($bilagtekst)) {
			$messages = "Bilag skal have en beskrivelse!";
		} else {
			db_modify("update mappebilag set beskrivelse='$bilagtekst' where id = '$bilag_id'",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/medarbejdermappe.php?funktion=vis_mappe&amp;mappe_id=$mappe_id\">";
		}
	}
	
	$r=db_fetch_array(db_select("select * from mappebilag where id='$bilag_id'",__FILE__ . " linje " . __LINE__));
	$bilagtekst=$r['beskrivelse'];

	$r=db_fetch_array(db_select("select * from mappe where id='$mappe_id'",__FILE__ . " linje " . __LINE__));
	$mappebeskrivelse=htmlspecialchars($r['beskrivelse']);
		
	print "<div id=\"breadcrumbbar\">

		<ul id=\"breadcrumb\">
					<li>";
						if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						print "</li>
					<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li><a href=\"medarbejdermappe.php\" title=\"Medarbejdermappe menu\">Medarbejdermappe menu</a></li>
					<li><a href=\"medarbejdermappe.php?funktion=faellesmappe\" title=\"Fælles-mappe liste\">Fælles-mappe</a></li>
					<li title=\"$mappebeskrivelse\"><a href=\"medarbejdermappe.php?funktion=vis_mappe&amp;mappe_id=$mappe_id\" style=\"display:block;max-width:160px;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;\">$mappebeskrivelse</a></li>
					<li>Ret bilag-tekst</li>
		</ul>
	</div><!-- end of breadcrumbbar -->\n";
	
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody><tr><td valign=\"top\" align=\"center\">\n";
	print "<form name=\"ret_bilag\" action=\"medarbejdermappe.php?funktion=ret_bilag&amp;mappe_id=$mappe_id&amp;bilag_id=$bilag_id\" method=\"post\">\n";
	print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\">\n";
	print "<tbody>\n";
	print "<tr><td align=\"center\" colspan=\"3\" style=\"padding-bottom: 15px;\"><h4>Ret&nbsp;bilag-tekst</h4></td></tr>\n";
	print "<tr>\n";
	print "<td valign=\"top\"><span style=\"display:block;margin-top:3px;\">Beskrivelse</span></td><td style=\"width:100%;\"><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"bilagtekst\" rows=\"2\" cols=\"10\" style=\"height:16px;width:100%;\">".htmlspecialchars($bilagtekst)."</textarea></td>\n";
	print "<td valign=\"bottom\"><input type=\"submit\" class=\"button gray medium\" value=\"Gem/Opdater\" name=\"opdater\"></td>\n";
	print "</tr>\n";
	if ($messages) print "<tr><td colspan=\"1\">&nbsp;</td><td colspan=\"2\" align=\"left\"><i style=\"color:red;\">$messages</i></td></tr>\n";
	print "</tbody></table></form></td></tr></tbody></table>\n";
	
}

function vis_mappe() {

	global $sag_rettigheder;
	global $db;
		
	$mappe_id=if_isset($_GET['mappe_id']);
		
		/* //skal rettes til!! mappen 'bilag' skal laves om til 'mappe', '$id' skal være '$mappe_id'
		if ($slet_bilag=if_isset($_GET['slet_bilag'])) {
			$r=db_fetch_array(db_select("select filtype from bilag where id='$slet_bilag'",__FILE__ . " linje " . __LINE__));
			unlink("../bilag/$db/$id/$slet_bilag.$r[filtype]");
			db_modify("delete from bilag where id = $slet_bilag",__FILE__ . " linje " . __LINE__);
		}
		*/
		/*
		if (isset($_POST['opdater'])) {
			$ny_mappe=db_escape_string($_POST['ny_mappe']);
				
			if ($ny_mappe){
			 //echo "insert: $ny_mappe"; exit();
				db_modify("insert into ordretekster (tekst) values ('$ny_mappe')",__FILE__ . " linje " . __LINE__);
			}
			//echo "tom: $ny_mappe"; exit();
		}
		*/
		if ($slet_bilag=if_isset($_GET['slet_bilag'])) {
			$r=db_fetch_array(db_select("select filtype from mappebilag where id='$slet_bilag'",__FILE__ . " linje " . __LINE__));
			unlink("../mappe/$db/$mappe_id/$slet_bilag.$r[filtype]");
			db_modify("delete from mappebilag where id = $slet_bilag",__FILE__ . " linje " . __LINE__);
		}
	
		$retvismappe=if_isset($_POST['onoffswitch']);
		
		if ($_POST['onoffswitch']){
			if ($_POST['onoffswitch']=='on') {
				$_SESSION['retvismappe']=$retvismappe;
			} elseif ($_POST['onoffswitch']=='off') {
				unset($_SESSION['retvismappe']);
			}
		} else {
			$retvismappe=$_SESSION['retvismappe'];
		}
		
		($retvismappe=='on')?$checked_retvismappe='checked':$checked_retvismappe=NULL;
		
		$r=db_fetch_array(db_select("select * from mappe where id='$mappe_id'",__FILE__ . " linje " . __LINE__));
		$beskrivelse=htmlspecialchars($r['beskrivelse']);
			
		print "
		<div id=\"breadcrumbbar\">
			<form name=\"retvismappe\" action=\"medarbejdermappe.php?funktion=vis_mappe&amp;mappe_id=$mappe_id\" method=\"post\">
				<ul id=\"breadcrumb\">
					<li>";
						if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						print "</li>
					<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li><a href=\"medarbejdermappe.php\" title=\"Medarbejdermappe menu\">Medarbejdermappe menu</a>
					<li><a href=\"medarbejdermappe.php?funktion=faellesmappe\" title=\"Fælles-mappe\">Fælles-mappe</a></li>";
					print "<li title=\"$beskrivelse\"><div style=\"width:160px;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;\">$beskrivelse</div></li>\n";
					if (substr($sag_rettigheder,2,1)) {
							print "<li style=\"float:right;\">
							<div class=\"onoffswitch\">
									<input type=\"hidden\" name=\"onoffswitch\" value=\"off\" />
									<input type=\"checkbox\" name=\"onoffswitch\" class=\"onoffswitch-checkbox\" id=\"myonoffswitch\" onclick=\"this.form.submit()\" $checked_retvismappe>
									<label class=\"onoffswitch-label\" for=\"myonoffswitch\">
											<span class=\"onoffswitch-inner\"><!-- --></span>
											<span class=\"onoffswitch-switch\"><!-- --></span>
									</label>
							</div>
							</li>
							<li style=\"float: right;font-size: 12px;color: #444;\">Ret mappe-indhold</li>\n";
						}
					print "
				</ul>
			</form>
		</div><!-- end of breadcrumbbar -->\n";
		
		print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
		print "<tbody><tr><td>\n";
		
		if (substr($sag_rettigheder,2,1) && ($retvismappe=='on')) {
		
			print "<form name=\"ordre\" action=\"medarbejdermappe.php?funktion=faellesmappe\" method=\"post\">\n";
			print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" class=\"ordretekstSort\" style=\"width:100%;\">\n";
			print "<tbody>\n";
			print "<tr><td colspan=\"8\" style=\"padding-bottom: 15px;\"><h4>$beskrivelse</h4></td></tr>\n";
			//print "<tr><td colspan=\"8\">&nbsp;</td></tr>\n";
			print "</tbody>\n";
			if (!$r=db_fetch_array(db_select("select * from mappebilag where assign_id = $mappe_id",__FILE__ . " linje " . __LINE__))) {
				print "<tbody class=\"dataTableTopBorderGray\"><tr><td colspan=\"8\"><i>Der er ingen bilag!</i></td></tr>\n";
			} else {
				print "<tbody class=\"\">\n";
				print "<tr class=\"dataTableHead\">\n";
				print "<td>&nbsp;</td>\n";
				print "<td><b>Beskrivelse</b></td>\n";
				print "<td><b>Filnavn</b></td>\n";
				print "<td><b>Dato</b></td>\n";
				print "<td><b>uploadet&nbsp;af</b></td>\n";
				print "<td colspan=\"3\">&nbsp;</td>\n";
				print "</tr>\n";
				print "</tbody>\n";
				print "<tbody class=\"dataTableZebra ordretekstListe\">\n";
				$x=0;
				$q = db_select("select * from mappebilag where assign_id = $mappe_id order by sort",__FILE__ . " linje " . __LINE__); 
				while ($r = db_fetch_array($q)) {
					$x++;
					print "<tr id=\"order_$r[id]\">\n";
					print "<td class=\"dragHandle\">&nbsp;</td>\n";
					print "<td align=\"left\" style=\"#width:100%;\">".nl2br($r['beskrivelse'])."</td>\n";
					print "<td align=\"left\" style=\"#width:100%;\">".$r['navn']."</td>\n";
					print "<td align=\"left\" style=\"#width:100%;\">".date("d-m-Y",$r['datotid'])."</td>\n";
					print "<td align=\"left\" style=\"#width:100%;\">".$r['hvem']."</td>\n";
					print "<td class=\"alignRight\"><a href=\"../mappe/$db/$mappe_id/$r[id].$r[filtype]\" target=\"blank\" class=\"button blue small\">Vis</a></td>\n";
					print "<td title=\"Ret bilag-tekst\" align=\"right\"><a href=\"medarbejdermappe.php?funktion=ret_bilag&amp;mappe_id=$mappe_id&amp;bilag_id=$r[id]\"><span style=\"width:16px; display:block;\"><span class=\"checkmark\">&nbsp;</span></span></a></td>\n";
					print "<td title=\"Slet bilag\" align=\"left\"><a href=\"medarbejdermappe.php?funktion=vis_mappe&amp;mappe_id=$mappe_id&amp;slet_bilag=$r[id]\" onclick=\"return confirm('Vil du slette dette bilag!')\"><span style=\"width:16px; display:block;\"><span class=\"xmark\">&nbsp;</span></span></a></td>\n";
					print "</tr>\n";
				}
			}
			print "</tbody>\n";
			print "<tbody class=\"dataTableTopBorderGray\">\n";//<input class=\"inputbox\" type=\"text\" title=\"Hvis teksten skal være fed sættes <b> foran teksten og </b> efter teksten (F.eks. <b>Lorem ipsum</b>). Det samme gøres ved Italic, bare med <i> og </i>.\" style=\"text-align:left;width:800px;\" name=\"ny_linjetekst\">
			print "<tr><td colspan=\"5\" style=\"width:100%;\"><span style=\"display:block;margin-top:3px;width:100%;\">Hent bilag til mappe her!</span></td>\n";
			print "<td align=\"center\" colspan=\"3\"><a href=\"bilag_mappe.php?kilde=sager&amp;ny=ja&amp;kilde_id=$mappe_id&amp;mappe_id=$mappe_id\" class=\"button blue small\">Nyt&nbsp;bilag</a></td>\n";
			print "</tr>\n";
			print "</tbody></table></form>\n";
			
		} else {
		
			print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\">\n";
			print "<tbody>\n";
			print "<tr><td colspan=\"5\" style=\"padding-bottom: 15px;\"><h4>$beskrivelse</h4></td></tr>\n";
			print "</tbody>\n";
			if (!$r=db_fetch_array(db_select("select * from mappebilag where assign_id = $mappe_id",__FILE__ . " linje " . __LINE__))) {
				print "<tbody class=\"dataTableTopBorderGray\"><tr><td colspan=\"5\" style=\"width:100%;\"><i>Der er ingen bilag!</i></td></tr>\n";
			} else {
				print "<tbody class=\"dataTableZebra dataTableTopBorderGray\">\n";
				$x=0;
				$q = db_select("select * from mappebilag where assign_id = $mappe_id order by sort",__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$x++;
					print "<tr>\n";
					print "<td align=\"left\" style=\"width:100%;\">".nl2br($r['beskrivelse'])."</td>\n";
					print "<td class=\"alignRight\"><a href=\"../mappe/$db/$mappe_id/$r[id].$r[filtype]\" target=\"blank\" class=\"button blue small\">Vis</a></td>\n";
					print "</tr>\n";
				}
			}
			print "</tbody></table>\n";
		}
		
		print "</td></tr>\n";
		print "</tbody>\n";
		print "</table>\n";
		?>
		<script type="text/javascript">
			// Function som sortere i table
		$("document").ready(function() {
			$(".ordretekstSort .ordretekstListe").tableDnD({
				onDragClass: "highlight",
				onDrop: function(table, row) {
				var orders = $.tableDnD.serialize();
				$.post('../sager/sortmappe.php?mode=mappebilag', { orders : orders });
				},
				dragHandle: ".dragHandle"
			});
		});
		$("document").ready(function() {
			$(".ordretekstListe tr").hover(function() {
							$(this.cells[0]).addClass('showDragHandle');
				}, function() {
							$(this.cells[0]).removeClass('showDragHandle');
				});
		});
		</script>
		<?php
}


function medarbejdermappe() {
/* SKAL RETTES TIL */
		global $sag_rettigheder;
		global $db;
		global $ansat_id;
		
		$ans_id = $_SESSION['ans_id'];
		
		if(!$ans_id) $ans_id=$ansat_id;
		//echo "ansat_id: $ansat_id<br>";echo "ans_id: $ans_id";
		if ($slet_ansatmappe=if_isset($_GET['slet_ansatmappe'])) { // Hvis mappen indeholder filer skal disse også slettes
			if ($r=db_fetch_array(db_select("select * from ansatmappebilag where assign_id='$slet_ansatmappe'",__FILE__ . " linje " . __LINE__))) {
			
				$x=0;
				$q=db_select("select * from ansatmappebilag where assign_id='$slet_ansatmappe'",__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$bilag_id[$x] = $r['id'];
					$bilag_filtype[$x] = $r['filtype'];
					$x++;
					}
				$antal_bilag=$x;
				
				for ($y=0;$y<$antal_bilag;$y++) {
					//echo "bilag_id: $bilag_id[$y]<br>";
					unlink("../ansatmappe/$db/$ans_id/$slet_ansatmappe/$bilag_id[$y].$bilag_filtype[$y]");
					db_modify("delete from ansatmappebilag where id = $bilag_id[$y]",__FILE__ . " linje " . __LINE__);
				}
				//rmdir("../mappe/$db/$slet_mappe");
				db_modify("delete from ansatmappe where id = '$slet_ansatmappe' and ans_id = '$ans_id'",__FILE__ . " linje " . __LINE__);
			} else {
				//echo "der er ingen bilag"; exit();
				db_modify("delete from ansatmappe where id = '$slet_ansatmappe' and ans_id = '$ans_id'",__FILE__ . " linje " . __LINE__);
			}
		} 
		
		if (isset($_POST['opdater'])) {
			$ny_ansatmappe=db_escape_string($_POST['ny_ansatmappe']);
			//$ny_ans_id=db_escape_string($_POST['ny_ans_id']);
			
			if (empty($ny_ansatmappe)) {
				$messages = "Mappe skal have en beskrivelse!";
			}	else {
			 //echo "insert: $ny_mappe"; exit();
				db_modify("insert into ansatmappe (beskrivelse,ans_id) values ('$ny_ansatmappe','$ans_id')",__FILE__ . " linje " . __LINE__);
			}
			//echo "tom: $ny_mappe"; exit();
		}
		
		$retansatmappe=if_isset($_POST['onoffswitch']);
		
		if ($_POST['onoffswitch']){
			if ($_POST['onoffswitch']=='on') {
				$_SESSION['retansatmappe']=$retansatmappe;
			} elseif ($_POST['onoffswitch']=='off') {
				unset($_SESSION['retansatmappe']);
			}
		} else {
			$retansatmappe=$_SESSION['retansatmappe'];
		}
		
		($retansatmappe=='on')?$checked_retansatmappe='checked':$checked_retansatmappe=NULL;
		
		if ($ans_id) {
			$r=db_fetch_array(db_select("SELECT id, navn, initialer FROM ansatte WHERE konto_id = 1 AND id='$ans_id'",__FILE__ . " linje " . __LINE__));
			$ans_id=$r['id'];
			$ans_navn=$r['navn'];
			$ans_ini=$r['initialer'];
		}
		
		print "
		<div id=\"breadcrumbbar\">
			<form name=\"retansatmappe\" action=\"medarbejdermappe.php?funktion=medarbejdermappe\" method=\"post\">
				<ul id=\"breadcrumb\">
					<li>";
						if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						print "</li>
					<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li><a href=\"medarbejdermappe.php\" title=\"Medarbejdermappe menu\">Medarbejdermappe menu</a></li>";
					print "<li>Medarbejder-mappe</li>\n";
					if (substr($sag_rettigheder,2,1)) {
							print "<li style=\"float:right;\">
							<div class=\"onoffswitch\">
									<input type=\"hidden\" name=\"onoffswitch\" value=\"off\" />
									<input type=\"checkbox\" name=\"onoffswitch\" class=\"onoffswitch-checkbox\" id=\"myonoffswitch\" onclick=\"this.form.submit()\" $checked_retansatmappe>
									<label class=\"onoffswitch-label\" for=\"myonoffswitch\">
											<span class=\"onoffswitch-inner\"><!-- --></span>
											<span class=\"onoffswitch-switch\"><!-- --></span>
									</label>
							</div>
							</li>
							<li style=\"float: right;font-size: 12px;color: #444;\">Ret mappe</li>\n";
						}
					print "
				</ul>
			</form>
		</div><!-- end of breadcrumbbar -->\n";
		
		print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
		print "<tbody><tr><td>\n";
		
		if (substr($sag_rettigheder,2,1) && ($retansatmappe=='on')) {
		
			print "<form name=\"ordre\" action=\"medarbejdermappe.php?funktion=medarbejdermappe\" method=\"post\">\n";
			print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\" class=\"ordretekstSort\">\n";
			
			if (!$r=db_fetch_array(db_select("select * from ansatmappe where ans_id=$ans_id",__FILE__ . " linje " . __LINE__))) {
				print "<tbody><tr><td align=\"center\" colspan=\"5\" style=\"color: green;\"><h4>($ans_ini) $ans_navn</h4></td></tr></tbody>";
				print "<tbody class=\"dataTableTopBorder\"><tr><td colspan=\"5\"><i>Der er ingen mapper!</i></td></tr>\n";
			} else {
				print "<tbody><tr><td align=\"center\" colspan=\"5\" style=\"color: green;\"><h4>($ans_ini) $ans_navn</h4></td></tr></tbody>\n";
				print "<tbody class=\"dataTableZebra dataTableTopBorder ordretekstListe\">\n";
				$x=0;
				$q = db_select("select * from ansatmappe where ans_id=$ans_id order by sort",__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$x++;
					print "<tr id=\"order_$r[id]\">\n";
					print "<td class=\"dragHandle\">&nbsp;</td>\n";
					print "<td align=\"left\" style=\"width:100%;\">".nl2br($r['beskrivelse'])."</td>\n";
					print "<td class=\"alignRight\"><a href=\"medarbejdermappe.php?funktion=vis_ansatmappe&amp;ansatmappe_id=$r[id]\" class=\"button blue small\">Vælg</a></td>\n";
					print "<td title=\"Ret mappe-tekst\" align=\"right\"><a href=\"medarbejdermappe.php?funktion=ret_ansatmappe&amp;ansatmappe_id=$r[id]\"><span style=\"width:16px; display:block;\"><span class=\"checkmark\">&nbsp;</span></span></a></td>\n";
					print "<td title=\"Slet mappe og al indhold\" align=\"left\"><a href=\"medarbejdermappe.php?funktion=medarbejdermappe&amp;slet_ansatmappe=$r[id]\" onclick=\"return confirm('Vil du slette denne mappe?\\n\\nADVARSEL!\\nAl indhold i mappen vil også\\nblive slettet')\"><span style=\"width:16px; display:block;\"><span class=\"xmark\">&nbsp;</span></span></a></td>\n";
					print "</tr>\n";
				}
			}
			print "</tbody>\n";
			print "<tbody class=\"dataTableTopBorderGray\">\n";//<input class=\"inputbox\" type=\"text\" title=\"Hvis teksten skal være fed sættes <b> foran teksten og </b> efter teksten (F.eks. <b>Lorem ipsum</b>). Det samme gøres ved Italic, bare med <i> og </i>.\" style=\"text-align:left;width:800px;\" name=\"ny_linjetekst\">
			print "<tr><td valign=\"top\" colspan=\"1\"><span style=\"display:block;margin-top:3px;\">Ny&nbsp;mappe</span></td><td colspan=\"2\" style=\"width:100%;\"><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"ny_ansatmappe\" rows=\"1\" cols=\"10\" style=\"height:16px;width:100%;\" title=\"Hvis teksten skal være fed sættes &lt;b&gt; foran teksten og &lt;/b&gt; efter teksten (F.eks. &lt;b&gt;Lorem ipsum&lt;/b&gt;). Det samme gøres ved Italic, bare med &lt;i&gt; og &lt;/i&gt;.\"></textarea></td>\n";
			print "<td colspan=\"2\" valign=\"bottom\"><input type=\"submit\" class=\"button gray medium\" value=\"Opret\" name=\"opdater\" onclick=\"javascript:docChange = false;\"></td>\n";
			print "</tr>\n";
			if ($messages) print "<tr><td colspan=\"1\">&nbsp;</td><td colspan=\"4\" align=\"left\"><i style=\"color:red;\">$messages</i></td></tr>\n";
			print "</tbody></table></form>\n";
			
		} else {
		
			print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\">\n";
			if (!$r=db_fetch_array(db_select("select * from ansatmappe where ans_id=$ans_id",__FILE__ . " linje " . __LINE__))) {
				print "<tbody><tr><td align=\"center\" colspan=\"5\" style=\"color: green;\"><h4>($ans_ini) $ans_navn</h4></td></tr></tbody>";
				print "<tbody class=\"dataTableTopBorder\"><tr><td colspan=\"5\"><i>Der er ingen mapper!</i></td></tr>\n";
			} else {
				print "<tbody><tr><td align=\"center\" colspan=\"5\" style=\"color: green;\"><h4>($ans_ini) $ans_navn</h4></td></tr></tbody>";
				print "<tbody class=\"dataTableZebra dataTableTopBorder\">\n";
				$x=0;
				$q = db_select("select * from ansatmappe where ans_id=$ans_id order by sort",__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$x++;
					print "<tr>\n";
					print "<td align=\"left\" style=\"width:100%;\">".nl2br($r['beskrivelse'])."</td>\n";
					print "<td class=\"alignRight\"><a href=\"medarbejdermappe.php?funktion=vis_ansatmappe&amp;ansatmappe_id=$r[id]\" class=\"button blue small\">Vælg</a></td>\n";
					print "</tr>\n";
				}
			}
			print "</tbody></table>\n";
		}
		
		print "</td></tr>\n";
		print "</tbody>\n";
		print "</table>\n";
		?>
		<script type="text/javascript">
			// Function som sortere i table
		$("document").ready(function() {
			$(".ordretekstSort .ordretekstListe").tableDnD({
				onDragClass: "highlight",
				onDrop: function(table, row) {
				var orders = $.tableDnD.serialize();
				$.post('../sager/sortmappe.php?mode=ansatmappe', { orders : orders });
				},
				dragHandle: ".dragHandle"
			});
		});
		$("document").ready(function() {
			$(".ordretekstListe tr").hover(function() {
							$(this.cells[0]).addClass('showDragHandle');
				}, function() {
							$(this.cells[0]).removeClass('showDragHandle');
				});
		});
		</script>
		<?php
}

function ret_ansatmappe() {

	global $sag_rettigheder;
	global $ansat_id;
		
	$messages=NULL;
	
	$ans_id = $_SESSION['ans_id'];
	$ansatmappe_id=if_isset($_GET['ansatmappe_id']);
	if(!$ans_id) $ans_id=$ansat_id;
	
		//echo "ansat_id: $ansat_id<br>";echo "ans_id: $ans_id";
		
	if (isset($_POST['opdater']) && $ansatmappe_id) {
		$ansatmappetekst=if_isset($_POST['ansatmappetekst']);
		
		if (empty($ansatmappetekst)) {
			$messages = "Mappe skal have en beskrivelse!";
		} else {
			db_modify("update ansatmappe set beskrivelse='$ansatmappetekst' where id = '$ansatmappe_id'",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/medarbejdermappe.php?funktion=medarbejdermappe\">";
		}
	}
	
	$r=db_fetch_array(db_select("select * from ansatmappe where id='$ansatmappe_id'",__FILE__ . " linje " . __LINE__));
	$ansatmappetekst=$r['beskrivelse'];
	
	if ($ans_id) {
		$r=db_fetch_array(db_select("SELECT id, navn, initialer FROM ansatte WHERE konto_id = 1 AND id='$ans_id'",__FILE__ . " linje " . __LINE__));
		$ans_id=$r['id'];
		$ans_navn=$r['navn'];
		$ans_ini=$r['initialer'];
	}
		
	print "<div id=\"breadcrumbbar\">

		<ul id=\"breadcrumb\">
					<li>";
						if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						print "</li>
					<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li><a href=\"medarbejdermappe.php\" title=\"Medarbejdermappe menu\">Medarbejdermappe menu</a></li>
					<li><a href=\"medarbejdermappe.php?funktion=medarbejdermappe\" title=\"Medarbejder-mappe liste\">Medarbejder-mappe</a></li>
					<li>Ret mappe-tekst</li>
		</ul>
	</div><!-- end of breadcrumbbar -->\n";
	
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody><tr><td valign=\"top\" align=\"center\">\n";
	print "<form name=\"ret_ansatmappe\" action=\"medarbejdermappe.php?funktion=ret_ansatmappe&amp;ansatmappe_id=$ansatmappe_id\" method=\"post\">\n";
	print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\">\n";
	print "<tbody><tr><td align=\"center\" colspan=\"3\" style=\"color: green;\"><h4>($ans_ini) $ans_navn</h4></td></tr></tbody>\n";
	print "<tbody class=\"dataTableTopBorder\">\n";
	print "<tr><td align=\"center\" colspan=\"3\" style=\"#padding: 10px 0 0 0;\"><h4>Ret&nbsp;mappe-tekst</h4></td></tr>\n";
	print "<tr>\n";
	print "<td valign=\"top\"><span style=\"display:block;margin-top:3px;\">Beskrivelse:</span></td><td style=\"width:100%;\"><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"ansatmappetekst\" rows=\"2\" cols=\"10\" style=\"height:16px;width:100%;\">".htmlspecialchars($ansatmappetekst)."</textarea></td>\n";
	print "<td valign=\"bottom\"><input type=\"submit\" class=\"button gray medium\" value=\"Gem/Opdater\" name=\"opdater\"></td>\n";
	print "</tr>\n";
	if ($messages) print "<tr><td colspan=\"1\">&nbsp;</td><td colspan=\"2\" align=\"left\"><i style=\"color:red;\">$messages</i></td></tr>\n";
	print "</tbody></table></form></td></tr></tbody></table>\n";
	
}

function ret_ansatbilag() {
/* SKAL RETTES TIL */
	global $sag_rettigheder;
	global $ansat_id;
	
	$messages=NULL;
	
	$ans_id = $_SESSION['ans_id'];
	$ansatmappe_id=if_isset($_GET['ansatmappe_id']);
	$ansatbilag_id=if_isset($_GET['ansatbilag_id']);
	if(!$ans_id) $ans_id=$ansat_id;
	
		//echo "ansat_id: $ansat_id<br>";echo "ans_id: $ans_id";
		
	if (isset($_POST['opdater']) && $ansatbilag_id) {
		$ansatbilagtekst=if_isset($_POST['ansatbilagtekst']);
		
		if (empty($ansatbilagtekst)) {
			$messages = "Bilag skal have en beskrivelse!";
		} else {
			db_modify("update ansatmappebilag set beskrivelse='$ansatbilagtekst' where id = '$ansatbilag_id'",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/medarbejdermappe.php?funktion=vis_ansatmappe&amp;ansatmappe_id=$ansatmappe_id\">";
		}
	}
	
	$r=db_fetch_array(db_select("select * from ansatmappebilag where id='$ansatbilag_id'",__FILE__ . " linje " . __LINE__));
	$ansatbilagtekst=$r['beskrivelse'];
	$ansatbilagnavn=$r['navn'];

	$r=db_fetch_array(db_select("select * from ansatmappe where id='$ansatmappe_id'",__FILE__ . " linje " . __LINE__));
	$ansatmappebeskrivelse=htmlspecialchars($r['beskrivelse']);
	
	if ($ans_id) {
		$r=db_fetch_array(db_select("SELECT id, navn, initialer FROM ansatte WHERE konto_id = 1 AND id='$ans_id'",__FILE__ . " linje " . __LINE__));
		$ans_id=$r['id'];
		$ans_navn=$r['navn'];
		$ans_ini=$r['initialer'];
	}
	
	print "<div id=\"breadcrumbbar\">

		<ul id=\"breadcrumb\">
					<li>";
						if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						print "</li>
					<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li><a href=\"medarbejdermappe.php\" title=\"Medarbejdermappe menu\">Medarbejdermappe menu</a></li>
					<li><a href=\"medarbejdermappe.php?funktion=medarbejdermappe\" title=\"Medarbejder-mappe liste\">Medarbejder-mappe</a></li>
					<li title=\"$ansatmappebeskrivelse\"><a href=\"medarbejdermappe.php?funktion=vis_ansatmappe&amp;ansatmappe_id=$ansatmappe_id\" style=\"display:block;max-width:160px;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;\">$ansatmappebeskrivelse</a></li>
					<li>Ret bilag-tekst</li>
		</ul>
	</div><!-- end of breadcrumbbar -->\n";
	
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody><tr><td valign=\"top\" align=\"center\">\n";
	print "<form name=\"ret_ansatbilag\" action=\"medarbejdermappe.php?funktion=ret_ansatbilag&amp;ansatmappe_id=$ansatmappe_id&amp;ansatbilag_id=$ansatbilag_id\" method=\"post\">\n";
	print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\">\n";
	print "<tbody><tr><td align=\"center\" colspan=\"3\" style=\"color: green;\"><h4>($ans_ini) $ans_navn</h4></td></tr></tbody>\n";
	print "<tbody class=\"dataTableTopBorder\">\n";
	print "<tr><td align=\"center\" colspan=\"3\" style=\"padding-bottom: 15px;\"><h4>Ret&nbsp;bilag-tekst</h4></td></tr>\n";
	print "</tbody>\n";
	print "<tbody class=\"dataTableZebra dataTableTopBorderGray\">\n";
	print "<tr><td>Mappe&nbsp;navn:</td><td colspan=\"2\"><b>$ansatmappebeskrivelse</b></td></tr>\n";
	print "<tr><td>Filnavn:</td><td colspan=\"2\">$ansatbilagnavn</td></tr>\n";
	print "</tbody>\n";
	print "<tbody class=\"dataTableTopBorderGray\">\n";
	print "<tr>\n";
	print "<td valign=\"top\"><span style=\"display:block;margin-top:3px;\">Beskrivelse:</span></td><td style=\"width:100%;\"><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"ansatbilagtekst\" rows=\"2\" cols=\"10\" style=\"height:16px;width:100%;\">".htmlspecialchars($ansatbilagtekst)."</textarea></td>\n";
	print "<td valign=\"bottom\"><input type=\"submit\" class=\"button gray medium\" value=\"Gem/Opdater\" name=\"opdater\"></td>\n";
	print "</tr>\n";
	if ($messages) print "<tr><td colspan=\"1\">&nbsp;</td><td colspan=\"2\" align=\"left\"><i style=\"color:red;\">$messages</i></td></tr>\n";
	print "</tbody></table></form></td></tr></tbody></table>\n";
	
}

function vis_ansatmappe() {
/* SKAL RETTES TIL */
	global $sag_rettigheder;
	global $db;
	global $ansat_id;
		
	$ans_id = $_SESSION['ans_id'];
	$ansatmappe_id=if_isset($_GET['ansatmappe_id']);
	
	if(!$ans_id) $ans_id=$ansat_id;
	
	//echo "ansat_id: $ansat_id<br>";echo "ans_id: $ans_id";
		/* //skal rettes til!! mappen 'bilag' skal laves om til 'mappe', '$id' skal være '$mappe_id'
		if ($slet_bilag=if_isset($_GET['slet_bilag'])) {
			$r=db_fetch_array(db_select("select filtype from bilag where id='$slet_bilag'",__FILE__ . " linje " . __LINE__));
			unlink("../bilag/$db/$id/$slet_bilag.$r[filtype]");
			db_modify("delete from bilag where id = $slet_bilag",__FILE__ . " linje " . __LINE__);
		}
		*/
		/*
		if (isset($_POST['opdater'])) {
			$ny_mappe=db_escape_string($_POST['ny_mappe']);
				
			if ($ny_mappe){
			 //echo "insert: $ny_mappe"; exit();
				db_modify("insert into ordretekster (tekst) values ('$ny_mappe')",__FILE__ . " linje " . __LINE__);
			}
			//echo "tom: $ny_mappe"; exit();
		}
		*/
		if ($slet_ansatbilag=if_isset($_GET['slet_ansatbilag'])) {
			$r=db_fetch_array(db_select("select filtype from ansatmappebilag where id='$slet_ansatbilag'",__FILE__ . " linje " . __LINE__));
			unlink("../ansatmappe/$db/$ans_id/$ansatmappe_id/$slet_ansatbilag.$r[filtype]");
			db_modify("delete from ansatmappebilag where id = $slet_ansatbilag",__FILE__ . " linje " . __LINE__);
		}
	
		$retvisansatmappe=if_isset($_POST['onoffswitch']);
		
		if ($_POST['onoffswitch']){
			if ($_POST['onoffswitch']=='on') {
				$_SESSION['retvisansatmappe']=$retvisansatmappe;
			} elseif ($_POST['onoffswitch']=='off') {
				unset($_SESSION['retvisansatmappe']);
			}
		} else {
			$retvisansatmappe=$_SESSION['retvisansatmappe'];
		}
		
		($retvisansatmappe=='on')?$checked_retvisansatmappe='checked':$checked_retvisansatmappe=NULL;
		
		$r=db_fetch_array(db_select("select * from ansatmappe where id='$ansatmappe_id' and ans_id='$ans_id'",__FILE__ . " linje " . __LINE__));
		$beskrivelse=htmlspecialchars($r['beskrivelse']);
		
		if ($ans_id) {
			$r=db_fetch_array(db_select("SELECT id, navn, initialer FROM ansatte WHERE konto_id = 1 AND id='$ans_id'",__FILE__ . " linje " . __LINE__));
			$ans_id=$r['id'];
			$ans_navn=$r['navn'];
			$ans_ini=$r['initialer'];
		}
	
		print "
		<div id=\"breadcrumbbar\">
			<form name=\"retvisansatmappe\" action=\"medarbejdermappe.php?funktion=vis_ansatmappe&amp;ansatmappe_id=$ansatmappe_id\" method=\"post\">
				<ul id=\"breadcrumb\">
					<li>";
						if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
						print "</li>
					<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li><a href=\"medarbejdermappe.php\" title=\"Medarbejdermappe menu\">Medarbejdermappe menu</a>
					<li><a href=\"medarbejdermappe.php?funktion=medarbejdermappe\" title=\"Medarbejder-mappe\">Medarbejder-mappe</a></li>";
					print "<li title=\"$beskrivelse\"><div style=\"width:160px;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;\">$beskrivelse</div></li>\n";
					if (substr($sag_rettigheder,2,1)) {
							print "<li style=\"float:right;\">
							<div class=\"onoffswitch\">
									<input type=\"hidden\" name=\"onoffswitch\" value=\"off\" />
									<input type=\"checkbox\" name=\"onoffswitch\" class=\"onoffswitch-checkbox\" id=\"myonoffswitch\" onclick=\"this.form.submit()\" $checked_retvisansatmappe>
									<label class=\"onoffswitch-label\" for=\"myonoffswitch\">
											<span class=\"onoffswitch-inner\"><!-- --></span>
											<span class=\"onoffswitch-switch\"><!-- --></span>
									</label>
							</div>
							</li>
							<li style=\"float: right;font-size: 12px;color: #444;\">Ret mappe-indhold</li>\n";
						}
					print "
				</ul>
			</form>
		</div><!-- end of breadcrumbbar -->\n";
		
		print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
		print "<tbody><tr><td>\n";
		
		if (substr($sag_rettigheder,2,1) && ($retvisansatmappe=='on')) {
		
			print "<form name=\"ordre\" action=\"medarbejdermappe.php?funktion=medarbejdermappe\" method=\"post\">\n";
			print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" class=\"ordretekstSort\" style=\"width:100%;\">\n";
			print "<tbody><tr><td align=\"center\" colspan=\"8\" style=\"color: green;\"><h4>($ans_ini) $ans_navn</h4></td></tr></tbody>\n";
			print "<tbody class=\"dataTableTopBorder\">\n";
			print "<tr><td colspan=\"8\" style=\"#padding-bottom: 10px;\">Mappe navn: <b>$beskrivelse</b></td></tr>\n";
			//print "<tr><td colspan=\"8\">&nbsp;</td></tr>\n";
			print "</tbody>\n";
			if (!$r=db_fetch_array(db_select("select * from ansatmappebilag where assign_id = $ansatmappe_id",__FILE__ . " linje " . __LINE__))) {
				print "<tbody class=\"dataTableTopBorderGray\"><tr><td colspan=\"8\"><i>Der er ingen bilag!</i></td></tr>\n";
			} else {
				print "<tbody class=\"\">\n";
				print "<tr class=\"dataTableHead\">\n";
				print "<td>&nbsp;</td>\n";
				print "<td><b>Beskrivelse</b></td>\n";
				print "<td><b>Filnavn</b></td>\n";
				print "<td><b>Dato</b></td>\n";
				print "<td><b>uploadet&nbsp;af</b></td>\n";
				print "<td colspan=\"3\">&nbsp;</td>\n";
				print "</tr>\n";
				print "</tbody>\n";
				print "<tbody class=\"dataTableZebra ordretekstListe\">\n";
				$x=0;
				$q = db_select("select * from ansatmappebilag where assign_id = $ansatmappe_id order by sort",__FILE__ . " linje " . __LINE__); 
				while ($r = db_fetch_array($q)) {
					$x++;
					print "<tr id=\"order_$r[id]\">\n";
					print "<td class=\"dragHandle\">&nbsp;</td>\n";
					print "<td align=\"left\" style=\"#width:100%;\">".nl2br($r['beskrivelse'])."</td>\n";
					print "<td align=\"left\" style=\"#width:100%;\">".$r['navn']."</td>\n";
					print "<td align=\"left\" style=\"#width:100%;\">".date("d-m-Y",$r['datotid'])."</td>\n";
					print "<td align=\"left\" style=\"#width:100%;\">".$r['hvem']."</td>\n";
					print "<td class=\"alignRight\"><a href=\"../ansatmappe/$db/$ans_id/$ansatmappe_id/$r[id].$r[filtype]\" target=\"blank\" class=\"button blue small\">Vis</a></td>\n";
					print "<td title=\"Ret bilag-tekst\" align=\"right\"><a href=\"medarbejdermappe.php?funktion=ret_ansatbilag&amp;ansatmappe_id=$ansatmappe_id&amp;ansatbilag_id=$r[id]\"><span style=\"width:16px; display:block;\"><span class=\"checkmark\">&nbsp;</span></span></a></td>\n";
					print "<td title=\"Slet bilag\" align=\"left\"><a href=\"medarbejdermappe.php?funktion=vis_ansatmappe&amp;ansatmappe_id=$ansatmappe_id&amp;slet_ansatbilag=$r[id]\" onclick=\"return confirm('Vil du slette dette bilag!')\"><span style=\"width:16px; display:block;\"><span class=\"xmark\">&nbsp;</span></span></a></td>\n";
					print "</tr>\n";
				}
			}
			print "</tbody>\n";
			print "<tbody class=\"dataTableTopBorderGray\">\n";//<input class=\"inputbox\" type=\"text\" title=\"Hvis teksten skal være fed sættes <b> foran teksten og </b> efter teksten (F.eks. <b>Lorem ipsum</b>). Det samme gøres ved Italic, bare med <i> og </i>.\" style=\"text-align:left;width:800px;\" name=\"ny_linjetekst\">
			print "<tr><td colspan=\"5\" style=\"width:100%;\"><span style=\"display:block;margin-top:3px;width:100%;\">Hent bilag til mappe her!</span></td>\n";
			print "<td align=\"center\" colspan=\"3\"><a href=\"bilag_ansatmappe.php?kilde=sager&amp;ny=ja&amp;kilde_id=$ansatmappe_id&amp;ansatmappe_id=$ansatmappe_id\" class=\"button blue small\">Nyt&nbsp;bilag</a></td>\n";
			print "</tr>\n";
			print "</tbody></table></form>\n";
			
		} else {
		
			print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" style=\"width:100%;\">\n";
			print "<tbody><tr><td align=\"center\" colspan=\"5\" style=\"color: green;\"><h4>($ans_ini) $ans_navn</h4></td></tr></tbody>\n";
			print "<tbody class=\"dataTableTopBorder\">\n";
			print "<tr><td colspan=\"5\" style=\"#padding-bottom: 10px;\">Mappe navn: <b>$beskrivelse</b></td></tr>\n";
			print "</tbody>\n";
			if (!$r=db_fetch_array(db_select("select * from ansatmappebilag where assign_id = $ansatmappe_id",__FILE__ . " linje " . __LINE__))) {
				print "<tbody class=\"dataTableTopBorderGray\"><tr><td colspan=\"5\" style=\"width:100%;\"><i>Der er ingen bilag!</i></td></tr>\n";
			} else {
				print "<tbody class=\"dataTableZebra dataTableTopBorderGray\">\n";
				$x=0;
				$q = db_select("select * from ansatmappebilag where assign_id = $ansatmappe_id order by sort",__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$x++;
					print "<tr>\n";
					print "<td align=\"left\" style=\"width:100%;\">".nl2br($r['beskrivelse'])."</td>\n";
					print "<td class=\"alignRight\"><a href=\"../ansatmappe/$db/$ans_id/$ansatmappe_id/$r[id].$r[filtype]\" target=\"blank\" class=\"button blue small\">Vis</a></td>\n";
					print "</tr>\n";
				}
			}
			print "</tbody></table>\n";
		}
		
		print "</td></tr>\n";
		print "</tbody>\n";
		print "</table>\n";
		?>
		<script type="text/javascript">
			// Function som sortere i table
		$("document").ready(function() {
			$(".ordretekstSort .ordretekstListe").tableDnD({
				onDragClass: "highlight",
				onDrop: function(table, row) {
				var orders = $.tableDnD.serialize();
				$.post('../sager/sortmappe.php?mode=ansatmappebilag', { orders : orders });
				},
				dragHandle: ".dragHandle"
			});
		});
		$("document").ready(function() {
			$(".ordretekstListe tr").hover(function() {
							$(this.cells[0]).addClass('showDragHandle');
				}, function() {
							$(this.cells[0]).removeClass('showDragHandle');
				});
		});
		</script>
		<?php
}

?>
