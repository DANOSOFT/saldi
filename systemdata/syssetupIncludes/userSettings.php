<?php
function userSettings() {
	global $bgcolor,$bgcolor5,$bruger_id,$db;
	global $menu,$fgcolor;
	global $popup,$sprog_id,$sidemenu,$topmenu;

	$gl_menu=NULL;
#	$$sidemenu=NULL;


	$qtxt = "select * from grupper where art = 'USET' and kodenr = '$bruger_id'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$jsvars=$r['box1'];
	($r['box2'])?$popup='checked':$popup=NULL;
	if ($r['box3'] == 'S') $sidemenu='checked';
	elseif ($r['box3'] == 'T') $topmenu='checked';
	else $gl_menu='checked';
	($r['box4'])?$bgcolor=$r['box4']:$bgcolor=NULL;
	($r['box5'])?$fgcolor=$r['box5']:$fgcolor=NULL;

	$nuancefarver[0]=findtekst(418,$sprog_id); $nuancekoder[0]="+00-22-22";
	$nuancefarver[1]=findtekst(419,$sprog_id); $nuancekoder[1]="-22+00-22";
	$nuancefarver[2]=findtekst(420,$sprog_id); $nuancekoder[2]="-22-22+00";
	$nuancefarver[3]=findtekst(421,$sprog_id); $nuancekoder[3]="+00+00-33";
	$nuancefarver[4]=findtekst(422,$sprog_id); $nuancekoder[4]="+00-33+00";
	$nuancefarver[5]=findtekst(423,$sprog_id); $nuancekoder[5]="-33+00+00";

	$qtxt = "select var_value from settings where var_name = 'bgcolor' and var_grp = 'colors' and user_id = '$bruger_id'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $bgcolor = $r['var_value'];
	elseif (!$bgcolor) $bgcolor = '#eeeef0';
	$qtxt = "select var_value from settings where var_name = 'fgcolor' and var_grp = 'colors' and user_id = '$bruger_id'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $fgcolor = $r['var_value'];
	elseif (!$fgcolor) $fgcolor = '#eeeef0';
	$qtxt = "select var_value from settings where var_name = 'buttonColor' and var_grp = 'colors' and user_id = '$bruger_id'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $buttonColor = $r['var_value'];
	else $buttonColor = '#114691';
	$qtxt = "select var_value from settings where var_name = 'buttonTxtColor' and var_grp = 'colors' and user_id = '$bruger_id'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $buttonTxtColor = $r['var_value'];
	else $buttonTxtColor = '#ffffff';

	print "<form name=userSettings action=diverse.php?sektion=userSettings&popup=$popup method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(785,$sprog_id)."</u></b></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
#	print "<input type=hidden name=id value='$id'>";

	print "<tr><td title='".findtekst(207,$sprog_id)."'>".findtekst(208,$sprog_id)."</td><td><input class='inputbox' type='checkbox' name='popup' $popup></td></tr>";
#	if (strpos($_SERVER['SERVER_NAME'],'dvikling') || strpos($_SERVER['SERVER_NAME'],'sl3')) {
	#	print "<tr><td title='".findtekst(316,$sprog_id)."'><!--Tekst 523-->".findtekst(315,$sprog_id)."<!--Tekst 315--></td><td><input class='inputbox' type='radio' name='menu' value='sidemenu' $sidemenu></td></tr>";
	if (substr($db,0,4) == 'laja') {
		print "<tr><td title='".findtekst(523,$sprog_id)."'><!--Tekst 523-->".findtekst(522,$sprog_id)."<!--Tekst 522--></td><td><input class='inputbox' type='radio' name='menu' value='topmenu' $topmenu></td></tr>";
		print "<tr><td title='".findtekst(523,$sprog_id)."'><!--Tekst 523-->Anvend sidemenu<!--Tekst 522--></td><td><input class='inputbox' type='radio' name='menu' value='sidemenu' $sidemenu></td></tr>";
#	}	else $gl_menu='checked';
	print "<tr><td title='".findtekst(525,$sprog_id)."'><!--Tekst 525-->".findtekst(524,$sprog_id)."<!--Tekst 524--></td><td><input class='inputbox' type='radio' name='menu'  value='gl_menu' $gl_menu></td></tr>";
} else print "<input type = 'hidden' name = 'menu' value='$menu'>";
	print "<tr><td title='".findtekst(209,$sprog_id)."'>".findtekst(210,$sprog_id)."</td><td colspan='4'><input class='inputbox' type='text' style='width:600px' name='jsvars' value='$jsvars'></td></tr>";
	if ($menu=='T') {
		print "<input type='hidden' name='bgcolor' value='".substr($bgcolor,1,6)."'>";
		print "<input type='hidden' name='nuance' value='$fgcolor'>\n";
	}

	print "<tr><td title='".findtekst(318,$sprog_id)."'>".findtekst(317,$sprog_id)."</td>";
print "<td colspan='4'>
<input class='inputbox' type='text' style='width:100px' name='bgcolor' id='bgcolor_text' value='".substr($bgcolor,1,6)."'>
<input type='color' id='bgcolor_color' value='$bgcolor' style='width:50px;height:30px;margin-left:10px;'>
</td></tr>";

print "<tr><td title='".findtekst(416,$sprog_id)."'>".findtekst(415,$sprog_id)."</td>";
print "<td colspan='4'>
<input class='inputbox' type='text' style='width:100px' name='fgcolor' id='fgcolor_text' value='".substr($fgcolor,1,6)."'>
<input type='color' id='fgcolor_color' value='$fgcolor' style='width:50px;height:30px;margin-left:10px;'>
</td></tr>";

print "<tr><td title='textcolor'>Farve på Knapper</td>";
print "<td colspan='4'>
<input class='inputbox' type='text' style='width:100px;background-color:$buttonColor;color:$buttonTxtColor;'
name='buttonColor' id='buttonColor_text' value='".substr($buttonColor,0,6)."'>
<input type='color' id='buttonColor_color' value='#".substr($buttonColor,0,6)."' style='width:50px;height:30px;margin-left:10px;'>
</td></tr>";

print "<tr><td title='textcolor'>Tekst farve på Knapper</td>";
print "<td colspan='4'>
<input class='inputbox' type='text' style='width:100px;background-color:$buttonColor;color:$buttonTxtColor;'
name='buttonTxtColor' id='buttonTxtColor_text' value='".substr($buttonTxtColor,0,6)."'>
<input type='color' id='buttonTxtColor_color' value='#$buttonTxtColor' style='width:50px;height:30px;margin-left:10px;'>
</td></tr>";
?>
<script>
// Prevent form submission on Enter key in color inputs
function preventEnterSubmit(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        event.stopPropagation();
        return false;
    }
}

// Add event listeners to all color inputs
document.addEventListener('DOMContentLoaded', function() {
    const colorInputs = document.querySelectorAll('input[type="color"]');
    colorInputs.forEach(function(input) {
        input.addEventListener('keydown', preventEnterSubmit);
    });
    
    const textInputs = document.querySelectorAll('#bgcolor_text, #fgcolor_text, #buttonColor_text, #buttonTxtColor_text');
    textInputs.forEach(function(input) {
        input.addEventListener('keydown', preventEnterSubmit);
    });
});

// Sync color picker with text input for bgcolor
document.getElementById('bgcolor_color').addEventListener('change', function() {
    document.getElementById('bgcolor_text').value = this.value.substring(1);
});

document.getElementById('bgcolor_text').addEventListener('input', function() {
    let value = this.value;
    if (value.length === 6 && /^[0-9A-Fa-f]{6}$/.test(value)) {
        document.getElementById('bgcolor_color').value = '#' + value;
    }
});

// Sync color picker with text input for fgcolor
document.getElementById('fgcolor_color').addEventListener('change', function() {
    document.getElementById('fgcolor_text').value = this.value.substring(1);
});

document.getElementById('fgcolor_text').addEventListener('input', function() {
    let value = this.value;
    if (value.length === 6 && /^[0-9A-Fa-f]{6}$/.test(value)) {
        document.getElementById('fgcolor_color').value = '#' + value;
    }
});

// Sync color picker with text input for buttonColor
document.getElementById('buttonColor_color').addEventListener('change', function() {
    document.getElementById('buttonColor_text').value = this.value.substring(1);
});

document.getElementById('buttonColor_text').addEventListener('input', function() {
    let value = this.value;
    if (value.length === 6 && /^[0-9A-Fa-f]{6}$/.test(value)) {
        document.getElementById('buttonColor_color').value = '#' + value;
    }
});

// Sync color picker with text input for buttonTxtColor
document.getElementById('buttonTxtColor_color').addEventListener('change', function() {
    document.getElementById('buttonTxtColor_text').value = this.value.substring(1);
});

document.getElementById('buttonTxtColor_text').addEventListener('input', function() {
    let value = this.value;
    if (value.length === 6 && /^[0-9A-Fa-f]{6}$/.test(value)) {
        document.getElementById('buttonTxtColor_color').value = '#' + value;
    }
});

const submit = document.querySelector('input[type="submit"]');
submit.addEventListener('click', function(event) {
	// Validate color inputs
	submit.disabled = true;
});

</script>
<?php
/*
		<select name='nuance' title='".findtekst(417,$sprog_id)."'>\n";
	if ( ! $fgcolor ) {
		$valgt = "selected='selected'";
	} else {
		$valgt="";
	}
	print "   <option $valgt value='' style='background:$bgcolor'>Intet</option>\n";
	for ($x=0; $x<count($nuancefarver);$x++) {
		if ( $fgcolor === $nuancekoder[$x] ) {
			$valgt = "selected='selected'";
		} else {
			$valgt="";
		}
		print "   <option $valgt value='$nuancekoder[$x]' style='background:".farvenuance($bgcolor, $nuancekoder[$x])."'>$nuancefarver[$x]</option>\n";
	}
	print "</select></td></tr>\n";
*/
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td><td><br></td><td><br></td><td align = center><input class='button green medium' type=submit accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td></tr>\n";
	print "</form>";
} # endfunc userSettings

?>
