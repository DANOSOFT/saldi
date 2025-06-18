<?php
@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

	
	$bg="nix";
	$header='nix';

	$menu_sager='id="menuActive"';
	$menu_dagbog=NULL;
	$menu_kunder=NULL;
	$menu_loen=NULL;
	$menu_ansatte=NULL;

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
	$template[] = array('beskrivelse' => 'Ingen template', 'tekst' => '');
}
/*
  echo '<pre>';
  print_r($template);
  echo '</pre>';
*/

$id=if_isset($_GET['sag_id']);
$konto_id=if_isset($_GET['konto_id']);

$query = db_select("SELECT * FROM sager WHERE id = '$id'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$sagsnr=$row['sagsnr'];
$firmanavn=$row['firmanavn'];
$beskrivelse=$row['beskrivelse'];
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link rel="stylesheet" type="text/css" href="../css/main.css">
        <script type="text/javascript" src="../tiny_mce/tiny_mce.js"></script>

        <script type="text/javascript">
            var templates = <?php echo json_encode($template); ?>;
            // Creates a new plugin class and a custom listbox
            tinymce.create('tinymce.plugins.TemplatePlugin', {
                createControl: function(n, cm) {
                    switch (n) {
                        case 'mytemplate':
                            var mlb = cm.createListBox('mytemplate', {
                                title : 'Templates',
                                onselect : function(v){
                                    var ed=this.control_manager.editor; 
                                    ed.focus();
                                    ed.selection.setContent(v);
                                    return false;
                                }
                            });

                            // Add some values to the list box
                            for(i=0;i<templates.length;i++)
                            {
                                var val = templates[i];
                                mlb.add(val.beskrivelse, val.tekst);
                            }
                            
                            // Return the new listbox instance
                            return mlb;
     
                        }

                        return null;
                    }
                });

                // Register plugin with a short name
                tinymce.PluginManager.add('mytemplate', tinymce.plugins.TemplatePlugin);

                tinyMCE.init({
                    // General options
                    mode: "exact",
                    language : "da",
                    elements : "tilbud",
                    theme : "advanced",
                    plugins : "-mytemplate,autolink,lists,spellchecker,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template,wordcount",
                    plugin_insertdate_dateFormat : "%d-%m-%Y",
                    
                    // Theme options
                    theme_advanced_buttons1 : "mytemplate,save,newdocument,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,styleselect,formatselect,fontselect,fontsizeselect",
                    theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
                    theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl,|,fullscreen",
                    theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,spellchecker,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking,template,blockquote,pagebreak,|,insertfile,insertimage",
                    theme_advanced_toolbar_location : "top",
                    theme_advanced_toolbar_align : "left",
                    theme_advanced_statusbar_location : "bottom",
                    theme_advanced_resizing : false,
        
                    width: "778",
                    height: "600",

                    // Skin options
                    skin : "o2k7",
                    skin_variant : "silver",

                    // Example content CSS (should be your site CSS)
                    content_css : "css/example.css",

                    // Drop lists for link/image/../img/template dialogs
                    template_external_list_url : "js/template_list.js",
                    external_link_list_url : "js/link_list.js",
                    external_image_list_url : "js/image_list.js",
                    media_external_list_url : "js/media_list.js",

                    // Replace values for the template plugin
                    template_replace_values : {
                        username : "Some User",
                        staffid : "991234"
                    }
                });
        </script>
<?php
print "<title>Stillads</title>
        
    </head>
    <body>
        <div id=\"wrapper\">";
        include ("../includes/sagsmenu.php");

            print "<div id=\"breadcrumbbar\">

                <ul id=\"breadcrumb\">
                    <li><a href=\"sager.php\" title=\"Sager\"><img src=\"../img/home.png\" alt=\"Sager\" class=\"home\" /></a></li>
                    <li><a href=\"sager.php\" title=\"Sager\">Sager</a></li>
                    <!--<li><a href=\"#\" title=\"Sample page 2\">Sample page 2</a></li>
                    <li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
                    <li>Current page</li>-->
                    <li>Nyt tilbud</li>
                </ul>

            </div><!-- end of breadcrumbbar -->

            <div id=\"leftmenuholder\">
                <div class=\"leftmenu\">
                    <div class=\"leftmenuhead\">Tilbud:</div>
                    <ul>
                        <li><a href=\"sager.php?function=vis_sag&amp;sag_id=$id\">Retur til sag</a></li>
                        <li><a href=\"template_list.php?sag_id=$id\">Opret/Ret templates</a></li>
                    </ul>
                </div><!-- end of leftmenu -->


            </div><!-- end of leftmenuholder -->

            <div class=\"maincontent\">
								<div class=\"content\">
									<p>Tilbud til <b>$firmanavn</b>, sag: <b>$sagsnr</b> $beskrivelse</p>
								</div>
                <div class=\"content\">
                    <form method=\"post\" action=\"\">
                        <table border=\"0\" cellspacing=\"0\">
                            <tr>
                                <td colspan=\"2\"><textarea id=\"tilbud\" name=\"tilbud\" rows=\"20\" cols=\"70\"></textarea></td>
                            </tr>
                            <tr>
                                <td style=\"padding-top: 10px;\"><input class=\"button gray medium\" type=\"submit\" value=\"Gem\" >
                                <td style=\"padding-top: 10px;\" align=\"right\"><input class=\"button gray medium\" type=\"reset\" value=\"reset\" ></td>
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