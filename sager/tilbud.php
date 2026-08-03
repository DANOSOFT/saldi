<?php
@session_start();	# Skal angives oeverst i filen??!!
	$s_id = session_id();

	
	$bg     = "nix";
	$header = 'nix';

	$menu_sager   = 'id="menuActive"';
	$menu_dagbog  = NULL;
	$menu_kunder  = NULL;
	$menu_loen    = NULL;
	$menu_ansatte = NULL;

	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	


function visTemplates() {
	$sql = db_select("SELECT * FROM sagstekster ORDER BY id DESC",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($sql)) {	
		$template[] = $row;
	}
	if(isset($template)){
		return $template;
	}
}

$templ = visTemplates();
if(is_array($templ)){
	foreach ($templ as $key => $temp) {
			$template[] = array('beskrivelse' => $temp['beskrivelse'], 'tekst' => $temp['tekst']);
	}
}else{
	$template[] = array('beskrivelse' => findtekst('3236|Ingen skabelon', $sprog_id), 'tekst' => '');
}
/*
  echo '<pre>';
  print_r($template);
  echo '</pre>';
*/

$id=if_isset($_GET['sag_id']);
$konto_id=if_isset($_GET['konto_id']);

$query       =  db_select("SELECT * FROM sager WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
$row         =  db_fetch_array($query);
$sagsnr      = $row['sagsnr'];
$firmanavn   = $row['firmanavn'];
$beskrivelse = $row['beskrivelse'];
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link rel="stylesheet" type="text/css" href="../css/main.css">
        <script type="text/javascript" src="../tiny_mce/tinymce.min.js"></script>

        <script type="text/javascript">
            var templates = <?php echo json_encode($template); ?>;
            // Toolbar dropdown that inserts a stored sagstekster template at the cursor
            tinymce.PluginManager.add('mytemplate', function (editor) {
                editor.ui.registry.addMenuButton('mytemplate', {
                    text: '<?php echo findtekst('803|Skabelon', $sprog_id); ?>',
                    fetch: function (callback) {
                        callback(templates.map(function (tpl) {
                            return {
                                type: 'menuitem',
                                text: tpl.beskrivelse,
                                onAction: function () {
                                    editor.focus();
                                    editor.selection.setContent(tpl.tekst);
                                }
                            };
                        }));
                    }
                });
                return {};
            });

            tinymce.init({
                license_key: 'gpl',
                selector: '#tilbud',
                language: '<?php echo ($sprog_id == 2) ? 'en' : 'da'; ?>',
                plugins: 'mytemplate autolink lists table save image link emoticons insertdatetime preview media searchreplace directionality fullscreen visualchars nonbreaking pagebreak wordcount charmap code visualblocks anchor help',
                toolbar: [
                    'mytemplate save | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | blocks fontfamily fontsize',
                    'searchreplace | bullist numlist | outdent indent blockquote | undo redo | link unlink anchor image code help | insertdatetime preview | forecolor backcolor',
                    'table | hr removeformat visualblocks | subscript superscript | charmap emoticons media | ltr rtl | fullscreen',
                    'visualchars nonbreaking pagebreak'
                ],
                browser_spellcheck: true,
                promotion: false,
                width: '778',
                height: '600'
            });
        </script>
<?php
print "<title>".findtekst('2783|Stillads', $sprog_id)."</title>
        
    </head>
    <body>
        <div id=\"wrapper\">";
        include ("../includes/sagsmenu.php");

            print "<div id=\"breadcrumbbar\">

                <ul id=\"breadcrumb\">
                    <li><a href=\"sager.php\" title=\"".findtekst('2774|Sager', $sprog_id)."\"><img src=\"../img/home.png\" alt=\"".findtekst('2774|Sager', $sprog_id)."\" class=\"home\" /></a></li>
                    <li><a href=\"sager.php\" title=\"".findtekst('2774|Sager', $sprog_id)."\">".findtekst('2774|Sager', $sprog_id)."</a></li>
                    <!--<li><a href=\"#\" title=\"Sample page 2\">Sample page 2</a></li>
                    <li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
                    <li>Current page</li>-->
                    <li>".findtekst('3251|Nyt tilbud', $sprog_id)."</li>
                </ul>

            </div><!-- end of breadcrumbbar -->

            <div id=\"leftmenuholder\">
                <div class=\"leftmenu\">
                    <div class=\"leftmenuhead\">".findtekst('812|Tilbud', $sprog_id).":</div>
                    <ul>
                        <li><a href=\"sager.php?function=vis_sag&amp;sag_id=$id\">".findtekst('2813|Tilbage til sag', $sprog_id)."</a></li>
                        <li><a href=\"template_list.php?sag_id=$id\">".findtekst('3241|Opret/ret skabeloner', $sprog_id)."</a></li>
                    </ul>
                </div><!-- end of leftmenu -->


            </div><!-- end of leftmenuholder -->

            <div class=\"maincontent\">
								<div class=\"content\">
									<p>".findtekst('3253|Tilbud til', $sprog_id)." <b>$firmanavn</b>, ".lcfirst(findtekst('2792|Sag', $sprog_id)).": <b>$sagsnr</b> $beskrivelse</p>
								</div>
                <div class=\"content\">
                    <form method=\"post\" action=\"\">
                        <table border=\"0\" cellspacing=\"0\">
                            <tr>
                                <td colspan=\"2\"><textarea id=\"tilbud\" name=\"tilbud\" rows=\"20\" cols=\"70\"></textarea></td>
                            </tr>
                            <tr>
                                <td style=\"padding-top: 10px;\"><input class=\"button gray medium\" type=\"submit\" value=\"".findtekst('3|Gem', $sprog_id)."\" >
                                <td style=\"padding-top: 10px;\" align=\"right\"><input class=\"button gray medium\" type=\"reset\" value=\"".findtekst('1239|Nulstil', $sprog_id)."\" ></td>
                            </tr>
                        </table>
                    </form>
                </div><!-- end of content -->

            </div><!-- end of maincontent -->

        </div><!-- end of wrapper -->  
        <div id=\"footer\"><p>Pluder | Pluder</p></div>
    </body>
</html>";
?>