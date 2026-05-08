<?php

if (!function_exists('findtextinst')) {
    function findtextinst($textId, $languageID) {
        
        $sessionVar = 'text_'. $textId .'_'. $languageID;

        global $bruger_id;

        $id = 0;

        if (strpos($textId, '|')) {
            list($a, $b) = explode('|', $textId);
            if (preg_match('/^[0-9]+$/', $a)) $textId = $a;
        }

        // Handle CSV files
        if (!preg_match('/^[0-9]+$/', $textId)) {
            $txtlines = array();
            if (file_exists("../importfiler/egnetekster.csv")) $fileName = "../importfiler/egnetekster.csv";
            else $fileName = "../importfiler/tekster.csv";
            $txtlines = explode("\n", file_get_contents($fileName));

            for ($i = 0; $i < count($txtlines); $i++) {
                $texts = explode("\t", $txtlines[$i]);
                if (in_array($textId, $texts)) {
                    for ($i2 = 1; $i2 < count($texts); $i2++) {
                        if ($textId == $texts[$i2]) $textId = $texts[0];
                        break 2;
                    }
                }
            }
        }

        // If textId still doesn't match a number, return it
        if (!preg_match('/^[0-9]+$/', $textId)) {
            return $textId;
        }

        $newTxt = $tekst = $tmp = NULL;
        $textId = trim($textId);
        
        // Default languageID handling
        if (!$languageID || $languageID > 3) {
            $languageID = 1;
        }

        // Read the CSV file for the text
        if (file_exists("../importfiler/egnetekster.csv")) {
            $fp = fopen("../importfiler/egnetekster.csv", "r");
            if ($fp) {
                $tmp = array();
                while (!feof($fp)) {
                    if ($linje = trim(fgets($fp))) {
                        if (strpos($linje, chr(9))) 
                            $tmp = explode(chr(9), $linje);
                        if ($textId == $tmp[0]) 
                            $newTxt = $tmp[$languageID]; # Text after 1st tab
                    }
                }
                fclose($fp);
            }
        }

        // If not found in egnetekster.csv, look in tekster.csv
        if (!$newTxt && file_exists("../importfiler/tekster.csv")) {
            $fp = fopen("../importfiler/tekster.csv", "r");
            while (!feof($fp) && !$newTxt) {
                if ($linje = trim(fgets($fp))) {
                    $a = explode("\t", $linje);
                    if ($a[0] == $textId) {
                        $newTxt = $a[$languageID];
                    }
                }
            }
            fclose($fp);
        }

        // Fallback to empty string if no text is found
        if (!$newTxt) {
            $newTxt = "Tekst nr: $textId";
        } elseif ($newTxt == "-") {
            $newTxt = '';
        }

        $_SESSION[$sessionVar] = $newTxt;
        return ($newTxt);
    }
}
?>
