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
	
	
$key = NULL;
$field['tekst'] = NULL;
$field['beskrivelse'] = NULL;
$errors['tekst'] = NULL;
$errors['beskrivelse'] = NULL;
$toptxt = NULL;

// Viser liste med templates i dropdown menu tinyMCE
function visTemplateList() {
$sql = db_select("SELECT * FROM sagstekster ORDER BY id DESC",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($sql)) {	
		$template[] = $row;
	}
	if(isset($template)){
		return $template;
	}
}

$templ = visTemplateList();
if(is_array($templ)){
foreach ($templ as $key => $temp) {
    $template[] = array('id' => $temp['id'], 'beskrivelse' => $temp['beskrivelse'], 'tekst' => $temp['tekst']);
    }
}else{
	$template[] = array('beskrivelse' => findtekst('3236|Ingen skabelon', $sprog_id), 'tekst' => '');
}

// functioner der viser opret/ret templates
function getSingleTemplate($tempid) {
    $sql = db_select("SELECT * FROM sagstekster WHERE id = $tempid",__FILE__ . " linje " . __LINE__);
    $row = db_fetch_array($sql);
    $template = $row;
    return $template;
}

function updateTemplate($name, $text, $tempid) {
    $result = db_modify("UPDATE sagstekster SET beskrivelse = '".db_escape_string($name)."', tekst ='".db_escape_string($text)."' WHERE id = $tempid",__FILE__ . " linje " . __LINE__);
    return $result;
}

function createTemplate($name, $text) {
    $result = db_modify("INSERT INTO sagstekster(beskrivelse, tekst)
    VALUES('" . db_escape_string($_POST['beskrivelse']) . "',
           '" . db_escape_string($_POST['tekst']) . "')",__FILE__ . " linje " . __LINE__);  
    return $result;
}

$id=if_isset($_GET['sag_id']);


if(isset($_POST['cancel'])) { header("location:template_list.php?sag_id=".$_POST['id']); exit(); }
// håndter data sendt fra formularen
if(isset($_POST['template']))
{
   $errors = array();
   $is_valid = true;

   $field['beskrivelse'] = $_POST['beskrivelse'];
   $field['tekst'] = $_POST['tekst'];
   

   if(!$field['beskrivelse'])
   {
      $is_valid = false;
      $errors['beskrivelse'] = '<p>'.findtekst('3237|Udfyld skabelonnavn', $sprog_id).'</p>';
   }

   if(!$field['tekst'])
   {
      $is_valid = false;
      $errors['tekst'] = '<p>'.findtekst('3238|Udfyld skabelon', $sprog_id).'</p>';
   }
   
   if($is_valid)
   {

      if(isset($_GET['edit']))
      {
         $query = updateTemplate($field['beskrivelse'],$field['tekst'],$_GET['edit']);
         if($query)
         {
            header("location: template_list.php?sag_id=".$_POST['id']); exit();
         }
      }
      else
      {    
         $query = createTemplate($field['beskrivelse'],$field['tekst']);
         if($query)
         {
            header("location: template_list.php?sag_id=".$_POST['id']); exit();
         }
      }
   }
}
if(isset($_GET['edit'])){
    $toptxt = findtekst('3239|Ret skabelon', $sprog_id);
}else{
    $toptxt = findtekst('3240|Opret ny skabelon', $sprog_id);
}
// hvis det er en redigering, hent data og sæt formens action
$form_action = 'template_form.php';
if(isset($_GET['edit']) && is_numeric($_GET['edit']))
{
   $form_action .= '?edit='.$_GET['edit'];
   $field = getSingleTemplate($_GET['edit']);
}


/*
  echo '<pre>';
  print_r($template);
  echo '</pre>';
*/
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link rel="stylesheet" type="text/css" href="../css/main.css">
        <link rel="stylesheet" type="text/css" href="../css/form.css">
        <script type="text/javascript" src="../tiny_mce/tiny_mce.js"></script>

        <script type="text/javascript">
            var templates = <?php echo json_encode($template); ?>;
            // Creates a new plugin class and a custom listbox
            tinymce.create('tinymce.plugins.TemplatePlugin', {
                createControl: function(n, cm) {
                    switch (n) {
                        case 'mytemplate':
                            var mlb = cm.createListBox('mytemplate', {
                                title : '<?php echo findtekst('803|Skabelon', $sprog_id); ?>',
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
                    language : "<?php echo ($sprog_id == 2) ? 'en' : 'da'; ?>",
                    elements : "temptext",
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

        <title><?php echo findtekst('2783|Stillads', $sprog_id); ?></title>
    </head>
    <body>
        <div id="wrapper"> 
        <?php  include ("../includes/sagsmenu.php"); ?>
            

            <div id="breadcrumbbar">

                <ul id="breadcrumb">
                    <li><a href="sager.php" title="<?php echo findtekst('2774|Sager', $sprog_id); ?>"><img src="../img/home.png" alt="<?php echo findtekst('2774|Sager', $sprog_id); ?>" class="home" ></a></li>
                    <li><a href="sager.php" title="<?php echo findtekst('2774|Sager', $sprog_id); ?>"><?php echo findtekst('2774|Sager', $sprog_id); ?></a></li>
                    <li><a href="tilbud.php?sag_id=<?php echo $id; ?>" title="<?php echo findtekst('812|Tilbud', $sprog_id); ?>"><?php echo findtekst('812|Tilbud', $sprog_id); ?></a></li>
                    <li><a href="template_list.php?sag_id=<?php echo $id; ?>" title="<?php echo findtekst('3241|Opret/ret skabeloner', $sprog_id); ?>"><?php echo findtekst('3241|Opret/ret skabeloner', $sprog_id); ?></a></li>
                    <li><?php echo $toptxt; ?></li>
                </ul>

            </div><!-- end of breadcrumbbar -->

            <div id="leftmenuholder">
                <div class="leftmenu">
                    <div class="leftmenuhead"><?php echo findtekst('812|Tilbud', $sprog_id); ?>:</div>
                    <ul>
                        <li><a href="tilbud.php?sag_id=<?php echo $id; ?>"><?php echo findtekst('2814|Tilbage til tilbud', $sprog_id); ?></a></li>
                        <li><a href="template_list.php?sag_id=<?php echo $id; ?>"><?php echo findtekst('3243|Tilbage til opret/ret skabeloner', $sprog_id); ?></a></li>
                    </ul>
                </div><!-- end of leftmenu -->


            </div><!-- end of leftmenuholder -->

            <div class="maincontent">

                <div class="content">
                    <form action="<?php echo $form_action; ?>" method="post">
                        <div style="float:left; margin-right:20px; ">
                            <h3><?php echo $toptxt; ?></h3>
                            <div class="contentA">
                                <div class="row">
                                    <div class="left"><?php echo findtekst('3244|Skabelonnavn', $sprog_id); ?>:</div>
                                    <div class="rightLarge"><input name="beskrivelse" type="text" class="textLong" value="<?php echo $field['beskrivelse']; ?>"><?php if(isset($errors['beskrivelse'])){echo $errors['beskrivelse'];} ?></div>
                                    <div class="clear"></div>
                                </div>                  
                            </div>
                        </div>
                        <div style="float:left;">
                        <table border="0" cellspacing="0">
                            <tr>
                                <td colspan="2"><textarea id="temptext" name="tekst" rows="20" cols="70"><?php echo $field['tekst']; ?></textarea></td>
                            </tr>
                            <tr>
                                <td><?php if(isset($errors['tekst'])){echo $errors['tekst'];} ?></td>
                                <td><input type="hidden" name="id" value="<?php echo $id; ?>"></td>
                            </tr>
                            <tr>
                                <td style="padding-top: 10px;"><input class="button gray medium" type="submit" name="template" value="<?php echo findtekst('3245|Gem/ret skabelon', $sprog_id); ?>" >
                                <td style="padding-top: 10px;" align="right"><input class="button rosy medium" type="submit" name="cancel" value="<?php echo findtekst('5|Annullér', $sprog_id); ?>" ></td>
                            </tr>
                        </table>
                        </div>
                    </form>
                </div><!-- end of content -->
               
            </div><!-- end of maincontent -->

        </div><!-- end of wrapper -->  
        <div id="footer"><p>Pluder | Pluder</p></div>
    </body>
</html>
