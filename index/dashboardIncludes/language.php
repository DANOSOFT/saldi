<?php
// Handle cookie language setting (same as index.php)

if(isset($_POST['cookieLanguageId'])){
    $cookieLanguageId = $_POST['cookieLanguageId'];
    $unixtime = time();
    include("../includes/connect.php");
    $qtxt = "UPDATE online SET logtime='$unixtime', language_id='$cookieLanguageId' WHERE session_id='$s_id'";
    db_modify($qtxt,__FILE__ . " linje " . __LINE__);
    include("../includes/online.php");
    if ($cookieLanguageId) {
        setcookie('languageId', $cookieLanguageId, time() + (10 * 365 * 24 * 60 * 60), '/');
    }
    print "<meta http-equiv=\"refresh\" content=\"0;URL=menu.php\">";
    exit;
} else{
	include("../includes/connect.php");
	$qtxt = "SELECT language_id FROM online WHERE session_id='$s_id'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$cookieLanguageId = $r['language_id'];
	include("../includes/online.php");
}

// Cookie language selector (same as index.php)
print "<form method='POST'>";
print "<tr bgcolor='$bgcolor5'><td colspan='6'>Sprog: </td></tr>";
// Read from tekster.csv like index.php does
$fp = fopen("../importfiler/tekster.csv","r");
if ($linje=trim(fgets($fp))) {
    $a = explode("\t",$linje);
}
// remove first element in a 
array_shift($a);
print "<td><select id='cookieLanguageId' name='cookieLanguageId' onchange='this.form.submit();'>";
fclose($fp);
echo "<script>console.log('cookieLanguageId: $cookieLanguageId');</script>";
for ($x=1; $x<=count($a); $x++){
    if ($x == $cookieLanguageId){
        print "<option selected value='$x'>".findtekst('1|Dansk', $x)."</option>\n";
    }
    else {
        print "<option value='$x'>".findtekst('1|Dansk', $x)."</option>\n";
    }
}
print "</select></td></tr>";
print "</form>";
?>