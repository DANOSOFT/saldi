<?php
// ----------------------------------------------------------------------050424----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2005 DANOSOFT ApS
// ----------------------------------------------------------------------


 @session_start();  # Skal angives oeverst i filen??!!
 $s_id=session_id();
include("../includes/connect.php");
# include("../includes/online.php");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head>
<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type"><title>SALDI - Hovedmenu</title></head>
<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
<tr><td align="center" valign="top">
<table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
<td width="20%" bgcolor=<?php echo "$bgcolor2> $font<small>Ver $version" ?></small></td>
    <td width="60%" bgcolor=<?php echo "$bgcolor2 align = \"center\">$font "?><a href=komigang.html><small>Vejledning</small></td>
    <td width="20%" bgcolor=<?php echo "$bgcolor2 align = \"right\">$font "?><a href=logud.php accesskey=L><small>Logud</small></td>
    </tbody></table></td></tr><tr><td align="center" valign="center">
    <table width="20%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
        <tr>
          <td colspan="5" align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><big><big><b>Saldi</b></big></big></td>
        </tr><tr>
          <td bgcolor=<?php echo "$bgcolor2" ?> align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><b>Administrations menu</b></td>
        </tr><tr>
          <td align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><a onfocus="this.style.color='$bgcolor2'" onblur="this.style.color='#000066'" href="../admin/opret.php">Opret regnskab</td>
        </tr><tr>
          <td align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><a onfocus="this.style.color='$bgcolor2'" onblur="this.style.color='#000066'" href="../admin/stdkontoplan.php"><br></td>
        </tr><tr>
          <td align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><a onfocus="this.style.color='$bgcolor2'" onblur="this.style.color='#000066'"><br></td>
        </tr><tr>
          <td align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><a onfocus="this.style.color='$bgcolor2'" onblur="this.style.color='#000066'"><br></td>
        </tr>
    </tbody></table>
  </td></tr>
  <tr><td align="center" valign="bottom">
    <table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
      <td width="25%" bgcolor="<?php print $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Copyright (c) 2004-2005 DANOSOFT ApS</small></td>
      <td width="50%" bgcolor="<?php print $bgcolor2 ?>" align = "center"><font face="Helvetica, Arial, sans-serif" color="#000066"></td>
      <td width="25%" bgcolor="<?php print $bgcolor2 ?>" align = "right"><font face="Helvetica, Arial, sans-serif" color="#000066"></td>
    </tbody></table>
  </td></tr>
</tbody></table>
</body></html>
