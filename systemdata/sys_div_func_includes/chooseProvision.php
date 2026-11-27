<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ systemdata/sys_div_func_includes/chooseProvision.php ------- lap 3.7.9 -- 2019-04-09 --
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2019 saldi.dk ApS
// ----------------------------------------------------------------------------
// 2019-04-09 LN Make field to set default provision value
// 2019-06-14 LN Change the setup, so we use the table settings instead of grupper

function chooseProvisionForProductGroup($showProvision)
{
  $defaultTxt = "select var_value from settings where var_name='defaultProvision' and var_grp='items'";
 	$defaultProvisionSetting = db_fetch_array(db_select($defaultTxt, __LINE__ . "linje" . __LINE__));
  if ($showProvision) $showProvision = "checked";
  print "<tr style='height:17px;'>  </tr>";
 	if (!empty($defaultProvisionSetting['var_value'])) {
    $provisionPercentage = $defaultProvisionSetting['var_value'];
    print "<tr> <td> Provision sats </td><td><INPUT class='inputbox' type='text' style='width:35px;text-align:right;' name='defaultProvision' value='$provisionPercentage'>%</td></tr>";
	} else {
    print "<tr> <td> Provision sats </td><td><INPUT class='inputbox' type='text' style='width:35px;text-align:right;' name='defaultProvision' value='0'>%</td></tr>";
	}


  $showTxt = "select var_value from settings where var_name='showProvision' and var_grp='items'";
 	$showProvisionSetting = db_fetch_array(db_select($showTxt, __LINE__ . "linje" . __LINE__));
  $showProvision = ($showProvisionSetting['var_value'] == 'on') ? "checked" : "";
  print "<tr>
          <td> Provision </td>
          <td>
             <input name='showProvision' style='width:15px;' type='checkbox' $showProvision>
          </td>
        </tr>";

        echo '<script type="text/javascript">
        $(document).ready(function(e) {
          $(".calendercolumn .dragbox #dragID").append("<div class=\'detailssaved\'><a href=\'#\' ><img src=\'./images/check_mark.JPG\' height=\'15\' width=\'15\'></a></div>");
        });
        </script>';

}

function saveProvisionForItemGroup($defaultProvision, $showProvision)
{
    updateProvision('defaultProvision', $defaultProvision);
    updateProvision('showProvision', $showProvision);
}

function updateProvision($field, $value)
{
    $dbTemp = __LINE__ . "linje" . __LINE__;
    $txt = "select * from settings where var_name='$field' and var_grp='items'";
    $query = db_select($txt, $dbTemp);
    if (db_fetch_array($query)) {
        $dbTxt = "update settings set var_value='$value' where var_name='$field' and var_grp='items'";
    } else {
        $dbTxt = "insert into settings (var_name, var_grp, var_value) values ('$field', 'items', '$value')";
    }
    db_modify($dbTxt, $dbTemp);
}

?>
