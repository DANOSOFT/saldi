<?php
// 20260827 CL/SZ Removed the dead top "Sprog" form (settings.languages/
//                languageId were never populated, so it always rendered with
//                zero options) and int-cast cookieLanguageId before it hits
//                SQL. Sprogindstillinger below is the only working selector. MB-27.
function language () {
    global $bgcolor,$bgcolor5,$bruger_id,$brugernavn;
    global $db;
    global $s_id,$sprog_id;

    $user_id = null;
    $user_id = (abs($bruger_id)); //20210517
    
    // Handle cookie language setting (same as index.php)
    if(isset($_POST['cookieLanguageId'])){
        $cookieLanguageId = (int)$_POST['cookieLanguageId'];
        $unixtime = time();
        include("../includes/connect.php");
        $qtxt = "UPDATE online SET logtime='$unixtime', language_id='$cookieLanguageId' WHERE session_id='$s_id'";
        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        include("../includes/online.php");
        if ($cookieLanguageId) {
            setcookie('languageId', $cookieLanguageId, time() + (10 * 365 * 24 * 60 * 60), '/');
        }
        print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=sprog\">";
        exit;
    } elseif(isset($_COOKIE['languageId'])){
        $cookieLanguageId = $_COOKIE['languageId'];
    } else {
        $cookieLanguageId = 1;
    }

    // Cookie language selector (same as index.php)
    print "<form method='POST' action='diverse.php?sektion=sprog'>";
    print "<tr><td colspan='6'><hr></td></tr>";
    print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst('2714|Sprogindstillinger', $sprog_id)."</b></u></td></tr>";
    print "<tr><td>".findtekst('2715|Vælg dit foretrukne sprog', $sprog_id).":</td>";
    // Read from tekster.csv like index.php does
    $fp = fopen("../importfiler/tekster.csv","r");
    if ($linje=trim(fgets($fp))) {
        $a = explode("\t",$linje);
    }
    // remove first element in a 
    array_shift($a);
    print "<td><select id='cookieLanguageId' name='cookieLanguageId' onchange='this.form.submit();'>";

    fclose($fp);

    if (!is_numeric($cookieLanguageId)) $cookieLanguageId = 1;
    for ($x=1; $x<=count($a); $x++){
        if ($x == $cookieLanguageId){
            print "<option selected value='$x'>".findtekst('1|Dansk', $x)."</option>\n";
        }
        else {
            print "<option value='$x'>".findtekst('1|Dansk', $x)."</option>\n";
        }
    }
    print "</select></td></tr>";
    print "<tr><td colspan='2'><small>".findtekst('2716|Nuværende sprog', $sprog_id).": ".findtekst('1|Dansk', $cookieLanguageId)."</small></td></tr>";
    print "</form>";

    // 20260828 CL/SZ Restored the link to tekster.php that lived on the removed dead "Sprog"
    // form (SirRolin, MB-27 review) - its own visible text now states what it does, instead
    // of an underlined language name with only a hover title explaining it.
    print "<tr><td colspan='2'><a href='tekster.php?sprog_id=$cookieLanguageId'>".findtekst('2717|Klik her for at rette tekster', $sprog_id)."</a></td></tr>";

} # endfunc sprog