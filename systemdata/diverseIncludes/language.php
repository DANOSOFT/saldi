<?php
function language () {
    include("../includes/languages.php"); #20210112
    global $bgcolor,$bgcolor5,$bruger_id,$brugernavn;
    global $db;
    global $s_id,$sprog_id; 

    $languageId=$sprog_id;
    
    $csvfile = "../importfiler/tekster.csv";
    $g1 =csv_to_array($csvfile);
    $x=0;
    $user_id = null;
    $user_id = (abs($bruger_id)); //20210517
    
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
        print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=sprog\">";
        exit;
    } elseif(isset($_COOKIE['languageId'])){
        $cookieLanguageId = $_COOKIE['languageId'];
    } else {
        $cookieLanguageId = 1;
    }

    print "<form name=diverse action=diverse.php?sektion=sprog method=post>";
    print "<tr><td colspan='6'><hr></td></tr>";
    print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(801,$sprog_id)."</b></u></td></tr>"; // 20210303
    print "<tr><td colspan='6'><br></td></tr>";
    
    if (isset($_POST['newLanguageId']) && $_POST['newLanguageId'])  {
        $newLanguageId = $_POST['newLanguageId'];
        $qtxt = "select id from settings where var_name = 'languageId' and user_id='0'";
        if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
            $qtxt = "update settings set var_value = '$newLanguageId' where id = '$r[id]'";
        } else {
            $qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
            $qtxt.= "('languageId','globals','$newLanguageId','Active default language','0')";
        }
        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        $languageId = $newLanguageId;
        include("../includes/connect.php");
        $qtxt = "update online set language_id = '$languageId' where session_id='$s_id'";
        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=sprog\">";
        exit;
    }
    include("../includes/connect.php");
    $qtxt = "select id, var_value from settings where var_name = 'languages' order by id limit 1";
    $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
    $languages=explode(chr(9),$r['var_value']);
    include("../includes/online.php");
    
    $tekst1=findtekst('1|Dansk', $sprog_id);
    $tekst2=findtekst('2|Vælg aktivt sprog', $sprog_id);
    for ($x=1; $x<count($languages);$x++) {
        if ($languageId == $x) $languageName  = $languages[$x];
    }
    if (!$languageId) {
        $languageId = 1;
        $languageName = 'Dansk';
    }
    #print "<tr><td title='Klik her for at rette tekster'><a href=tekster.php?sprog_id=1>$tekst1</a></td>";
    print "<tr><td title='".findtekst('2717|Klik her for at rette tekster', $sprog_id)."'><a href=tekster.php?sprog_id=$languageId>$languageName</a></td>"; #20210818
    print "<td><SELECT class='inputbox' NAME='newLanguageId' title='$tekst2'>";
    #		if ($box3[$x]) print"<option>$box3[$x]</option>";
    for ($x=1; $x<count($languages);$x++) {
        if ($languageId == $x) print "<option value='$x'>$languages[$x]</option>";
    }
    for ($x=1; $x<count($languages);$x++) {
        if ($languageId != $x) print "<option value='$x'>$languages[$x]</option>";
    }
    print "</SELECT></td></tr>";
#	}
    print "<tr><td><br></td></tr>";

    $tekst1=findtekst('3|Gem', $sprog_id);
    print "<tr><td align = right colspan='4'><input class='button green medium' type=submit value='$tekst1' name='submit'></td></tr>";
    print "</form>";
    
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
    
} # endfunc sprog