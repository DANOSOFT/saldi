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
	



function deleteTemplate($tempid) {
    $result = db_modify("DELETE FROM sagstekster WHERE id = $tempid",__FILE__ . " linje " . __LINE__);
    return $result;
}

function selectTemplates() {
    $sql = db_select("SELECT * FROM sagstekster ORDER BY id DESC",__FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($sql)) {
        $template[] = $row;
    }
    if(isset($template)){
		return $template;
	}
}

if(isset($_GET['delete']) && is_numeric($_GET['delete']))
{
   $result = deleteTemplate($_GET['delete']);
   if($result)
   {
      header('location:template_list.php'); exit();
   }
   else 
   {
      $error = '<p>Template '.$result.'</p>';
   }
}

$id=if_isset($_GET['sag_id']);
/*
echo '<pre>';
print_r($template);
echo '</pre>';*/
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link rel="stylesheet" type="text/css" href="../css/main.css">
        

        <title>Stillads</title>
    </head>
    <body>
        <div id="wrapper"> 
      <?php  include ("../includes/sagsmenu.php"); ?>
            

            <div id="breadcrumbbar">

                <ul id="breadcrumb">
                    <li><a href="sager.php" title="Sager"><img src="../img/home.png" alt="Sager" class="home" /></a></li>
                    <li><a href="sager.php" title="Sager">Sager</a></li>
                    <li><a href="tilbud.php?sag_id=<?php echo $id ?>" title="Tilbud">Tilbud</a></li>
                    <!--<li><a href="#" title="Sample page 2">Sample page 2</a></li>
                    <li><a href="#" title="Sample page 3">Sample page 3</a></li>
                    <li>Current page</li>-->
                    <li>Opret/Ret templates</li>
                </ul>

            </div><!-- end of breadcrumbbar -->

            <div id="leftmenuholder">
                <div class="leftmenu">
                    <div class="leftmenuhead">Tilbud:</div>
                    <ul>
                        <li><a href="tilbud.php?sag_id=<?php echo $id ?>">Retur til tilbud</a></li>
                        
                    </ul>
                </div><!-- end of leftmenu -->


            </div><!-- end of leftmenuholder -->

            <div class="maincontent">

                <div class="content"> 
                    
                        <p style="margin:5px 0px 10px 0px;"><a href="template_form.php?sag_id=<?php echo $id ?>" class="button blue small">Opret template</a></p>
                        <hr>
                        <?php
                        $data = selectTemplates();
                        echo '
                        <table width="778" class="templateListTable" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                        <th>Template navn</th>			
                                        <th colspan="2">&nbsp;</th>
                                </tr>
                        ';
												if(is_array($data)){
													foreach($data as $key => $temp)
													{
														$on_click = 'onclick="document.location=\'template_form.php?edit='.$temp['id'].'&amp;sag_id='.$id.'\'"';
														echo '
														<tr>
																	<td '.$on_click.'>'.$temp['beskrivelse'].'</td>
																	<td width="50">
																					<a href="template_list.php?delete='.$temp['id'].'" class="button red small"
																									title="Slet denne template" onclick="return confirm(\'Er du sikker på du vil slette denne?\');">Slet</a>
																	</td>
																	<td '.$on_click.' width="50">
																					<a href="template_form.php?edit='.$temp['id'].'&amp;sag_id='.$id.'" class="button green small" title="Ret denne template">Ret</a>
																	</td>
														</tr>
														';
													}
												}else{
												echo '
												<tr>
													<td colspan="3"><i>Der er ingen templates</i></td>
												</tr>';
												}
                        echo '</table>';
                        if(isset($error)) echo $error;
                        ?>
                    
                </div><!-- end of content -->

            </div><!-- end of maincontent -->

        </div><!-- end of wrapper -->  
        <div id="footer"><p>Pluder | Pluder</p></div>
    </body>
</html>
