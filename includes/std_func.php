<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/std_func.php --- patch 4.1.1 --- 2025-11-06 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20130210 Break ændret til break 1 Tastefejl rettet.
// 20140501 Funktion findtekst - teksten ignoreres nu hvis tekst="-"
// 20140505 Funktion findtekst insdat db_escape_string. (PHR - Danosoft) Søg 20140505
// 20141010 CA  Funktionen farvenuance antager hvid baggrund, hvis ingen angivet. Søg 20141010
// 20141031 CA  Funktionen advarselsboks skal erstatte JavaScript Alertbokse - er ikke færdig. Søg 20141031
// 20141031 PHR tilføjet funktion "find_varemomssats"
// 20141121 CA  Funktionen tekstsboks erstatter advarselsboks en udgave med og uden tabel. Søg 20141121
// 20141121 CA  Tilføjet funktion bokshjoerne til flytning af tekstbokse. Søg 20141121
// 20141223 PHR Tilføjet funktion find_lagervaerdi til brug med aut_lager. Søg 20141223
// 20150218 PK  Tilføjet to funktioner til at lave uppercase på tekst-streng. Søg mb_ucfirst eller mb_ucwords
// 20150313 CA  Byttet om på farverne for infoboks (nu blå) og popop (nu grøn). Søg 20150313
// 20160116 PHR Oprettet funktion regnstartslut
// 20161011 PHR Oprettet funktion lagerreguler
// 20161022 PHR Rettet funktion lagerreguler $diff skal ikke fratrækkes $rest 20161022
// 20161124 PHR Rettet funktion lagerreguler så lagerstatus fjernes for lagre > 0 hvis der ikke er flere lagre  Søg 20161022
// 20161217 PHR Tilføjet funktion find_beholdning (Flyttet fra lager/varer.php)
// 20161222 PHR Rettet funktion lagerreguler så dubletter på lager 0 slettes hvis der ikke er flere lagre.
// 20161222 PHR Rettet fejl. Lager o blev ikke fundet så dublet blev oprettet på lager 0
// 20170404 PHR Funktion 'find_lagervaerdi' udelader nu ikke bogførte ordrer da disse kan give skæve tal.
// 20180119 PHR Tilføjet funktion hent_shop_ordrer som opdaterer ordrer fra shop.
// 20180123 PHR En del rettelser i funktion lagerreguler i forhold til varianter og flere lagre.
// 20180518 PHR Tilføjet funktion alert.
// 20181126 PHR Variabeldefiniton i div. funktioner. 
// 20181220 MSC  Rettet fejl
// 20190104 PHR	 " nye funktioner create_debtor & get_next_number
// 20190221 MSC  Flytter $boksid="" til enden søg edit 2019.02.21 for kode
// 20190423 PHR 'bynavn' (city) was not inserted in 'adresser' if not set in function create_debtor. 20190423
// 20190605 PHR Added if_isset to avoid warning. 20190605
// 20190704 RG  (Rune Grysbæk) Mysqli implementation 
// 20200326 PHR Optimized function findtekst.
// 20200327 PHR Moved function barcode form lager/varekort
// 20200407 PHR Some minor changes to function barcode to work with kreditor/ordre.php
// 20200907 PHR Function sync_shop_vare. Query parted in 2 to make it find varenr if no shop_id
// 20200917 PHR	Added missing email in create_debtor
// 20210225 LOE Added active language function
// 20200917 PHR Added missing email in create_debtor
// 20201119 PHR Some optimation in function lagerreguler an extended logging in function sync_shop_vare 
// 20210107 PHR Function cvrnr_omr. Removed GB from EU
// 20210107 PHR Function lagerreguler. Added file & line to wget call. 
// 20210301 CA  Added function trim_utf8. Trims for traditional whitespaces and &nbsp;
// 20210302 LOE Function csv_to_array() and engdan() added 
// 20210303 LOE Added this block to handle a situation when tekster table is empty
// 20210304 LOE Replaced the if(!$teskt) fuction with an updated version 
// 20210409 LOE Modified engdan() to accomondate other languages
// 20210419 LOE Did some modifications in findtekst function 20210419
// 20210421 LOE modified added activelanguage id activelanguage() function 20210421
// 20210427 LOE Modified active language function
// 20210428 LOE findtekst function is modified to accomodate admin during installation Danish as the default 20210428
// 20210429 LOE activeLanguage function also updated 
// 20210517 Loe Updated findtekst function using abs function to get negative ids for admins positive and updated some where it applies
// 20210518 LOE Added this to check handle more than one account for the admin ..if the initial language still exists it updates the table with active one
// 20210704 LOE Little modification of findtekst() function
// 20210818 LOE Did some modication to findtekst function checking before insert if the value already exists in tekster table
// 20210828 LOE Updated findtekst function 
// 20210830 LOE Created get_ip function...This is used in login.php file and in brugere.php
// 20210831 LOE Created restrict_user_ip and restore_user_ip functions
// 20210901 LOE Created authenticate_user_ip function
// 20210908 LOE Created input_ip function
// 20210909 LOE Modified some function relating to Ip
// 20210915 LOE Modified activeLanguage function for older vesion compatibility
// 20210916 LOE Modified findtekst function to have a default language for develop db
// 20210917 LOE Set sprog_id for admins and updated some codes
// 20210922 LOE Added session language for admin users.
// 20211001 LOE Added lingua as language set for admins*
// 20211006 LOE Updating some findtekst function codes
// 20211007 LOE For admin checking before insert if the value already exists in tekster table
// 20211008 PHR Function findtekst rolled back.
// 20211013 PHR Function sync_shop_vare & lagerreguler - added totalStock 
// 20211015 LOE Modified some codes to adjust to IP moved to settings table
// 20211029 PHR Function barcode - If not numeric, barcode is not EAN13
// 20220110 PHR Function 'sync_shop_vare' Check if item is a stock item
// 20200125 PHR Added func getAvailable, added it to func sync_shop_vare and addef func sync_shop_vare to func lagerreguler
// 20220514 PHR Function findtekst. Added str_replace('\n\n',"\n\n" to newTxt
// 20220603 PHR Function 'sync_shop_vare.' Added search for items that is part of items that is part of items
// 20220628 PHR Function usdate. Corrected type $slutaar was $slutadaar ???
// 20220716 PHR Function tekster. Texts is not fetched from 'inportfiler/egne_tekster.csv' if file exists
// 20220716 PHR Function usdecimal. $tal (number) is now trimmed as it returned 0 if space in either end.
// 20230224 CA  Function findtekst. If tekstid has other characters than digits it returns tekstid. 
//              So when developing just write findtekst('Text showed', $sprog_id)
// 20230321 PHR Added "-colorspace RGB" in function barcode to make it work in Ubuntu 20; #20230321
// 20230623 PHR Addad function 'str_starts_with' (included in php8)
// 20230707 PHR Moved some functions to stdFunc/
// 20230730 LOE Minor modifications, absolute path for stdFunc* 
// 20230801 LOE locateDir() created, this assists to search for a file with relation to accounting directory
// 20240416 LOE Converted some strings to int before maths operation and also Initialized $bynavn = null 
// 20240726 PHR function findtekst now accepts textstring as first argument
// 20240815 PHR function findtekst moved to stdFunc/findTxt.php
// 20240925 PBLM we now send price to the api aswell
// 20250113 PHR function find_lagervaerdi - fiscal_year
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250321 LOE Updated with various changes and new additions from multiple updates including comments
// 20250323 LOE Checks if input is set, ensures it doesn't exceed 80 characters, and sanitizes it to prevent XSS attacks.
// 20250405 LOE if_isset() updated and to check explicitly if array keys exist if they are arrays.
// 20250630 PHR Minor change in transtjek
// 20250811 PHR Another minor change in transtjek
// 20251106 LOE Added z-index to tekstboks function
include('stdFunc/dkDecimal.php');
include('stdFunc/nrCast.php');
include('stdFunc/strStartsWith.php');
include('stdFunc/usDecimal.php');
if (!function_exists('locateDir')) {
	function locateDir($baseRelativeDir) {
		/**
		 * Locates a directory by moving up the directory tree.
		 * 
		 * This function starts with a given relative directory and checks if it exists. 
		 * If it doesn't, the function keeps moving one directory level up by prepending `../` 
		 * to the directory path. It stops after 5 iterations or if the directory is found.
		 *
		 * @param string $baseRelativeDir - The relative directory to locate.
		 * @return string - The path to the directory if found, or an empty string if not.
		 */
		$i = 0;
		while (!file_exists($baseRelativeDir)) {
			$baseRelativeDir = '../'.$baseRelativeDir;
			$i++;
			if ($i>5) return '';
		}
		return $baseRelativeDir;
/*
		// format of $baseRelativeDir == "importfiler";
		//  "foo", "bar" etc.
		$currentDir = __DIR__;
		// Get the file path of the directory above the current directory
		$parentDir = dirname(__DIR__); //account path
		$baseRelativeDir = $parentDir . "/" . $baseRelativeDir;
		return $baseRelativeDir;
*/
	}
}


if (!function_exists('dkdato')) {
	function dkdato($dato)
	{
		/**
		 * Converts a date from the format YYYY-MM-DD to the Danish date format DD-MM-YYYY.
		 *
		 * @param string $dato - The date in YYYY-MM-DD format.
		 * @return string - The formatted date in DD-MM-YYYY format.
		 */
		if ($dato) {
			list($year, $month, $day) = explode('-', $dato, 3);
			$month = (int)$month;
			$day = (int)$day;
			if ($month < 10) {
				$month = '0' . $month;
			}
			if ($day < 10) {
				$day = '0' . $day;
			}
			$dato = $day . "-" . $month . "-" . $year;
			return $dato;
		}
	}
}

if (!function_exists('get_relative')) {
    function get_relative() {
		/**
		 * Gets the relative path to navigate up the directory structure based on the current URL.
		 * It calculates the number of directory levels in the current URL's path and returns a relative path 
		 * consisting of `../` that allows you to move up in the directory structure.
		 * Usefull for includes inside include files, as it returns a relative path to the project root.
		 *
		 * @return string - The relative path to go up to the desired directory level.
		 */
        $url = $_SERVER['REQUEST_URI'];
        $questionMarkPos = strpos($url, '?');
        if ($questionMarkPos !== false) {
            $path = substr($url, 0, $questionMarkPos);
        } else {
            $path = $url;
        }
        $slashCount = substr_count($path, '/');
        $relativePath = str_repeat('../', max(0, $slashCount - 2));

        return $relativePath;
    }
}

// if (!function_exists('if_isset')) {
// 	function if_isset(&$var, $return = NULL)
// 	{
		/**
		 * Checks if a variable is set and not empty.
		 * If set and not empty, returns the value of the variable.
		 * Otherwise, returns the default value provided.
		 *
		 * @param mixed $var - The variable to check.
		 * @param mixed $return - The value to return if the variable is not set or is empty (default: NULL).
		 *
		 * @return mixed - The value of the variable if set and not empty, otherwise the default value.
		 * ######## Known Behaviour #######, 
		 * This doesn't return True if $var === 0 as 0 is considered Falsy in PHP. But 0 can be set and retrieved as value in $return param.
		 * Same thing for False value.
		 * if_isset(false) //NULL
		 * if_isset(0)     //NULL
		 * #################
		 */
// 		if ($var)
// 			return ($var);
// 		else
// 			return ($return);
// 	}
// }

if (!function_exists('if_isset')) {
    function if_isset($arrayOrVar, $default = null, $key = null) {
        /**
         * Custom function to safely check if a variable or an array key exists.
         *
         * - if_isset($var, $default)           // safely checks if $var is set, returns $default if not
         * - if_isset($array, $default, $key)   // safely gets $array[$key] or returns $default
         *
         * Behavior for special values:
         * ----------------------------------------
         * - `false`: Treated as "not set" and returns NULL . Explicitly set for single values
         * - `null`: If the variable or array key is explicitly `null`
         * - `0`: Considered a valid value, returned as-is (0 is treated as set).
         * - `""` (empty string): Considered a valid value, returned as-is (empty string is set).
         * - Arrays: If the key exists, it returns the value. If not, it returns the default value.
         * #############USECASE####################
		 * $sektion = if_isset($_GET,null,'sektion');
		 * ########################################
		 * 
         * @param mixed $arrayOrVar The array or variable to check.
         * @param mixed $default    The default value to return if the variable or key is not set.
         * @param mixed $key        The key (if array is passed).
         * @return mixed           The actual value or the default.
         */

        // Case 1: One argument — treat as a single variable fallback
        if ($key === null) {
            // If key is not provided, we're dealing with just a single variable
          
			 // Check if the variable is explicitly false and return NULL
			 if ($arrayOrVar === false) {
				return NULL;
			}
	
			return isset($arrayOrVar) ? $arrayOrVar : $default;
        }

        // Case 2: Two arguments — array + key
        if (is_array($arrayOrVar) && array_key_exists($key, $arrayOrVar)) {
            // If it's an array and the key exists, return the value or NULL if it's false
            $value = $arrayOrVar[$key];
            return $value === false ? null : $value;
        }

        // Default case: Return the default value
        return $default;
    }
}

if (!function_exists('usdate')) {
	function usdate($date)
	{
		/**
		 * Standardizes and validates a date string and returns it in "Y-m-d" format.
		 * The function handles various date formats, adjusts for incorrect or missing dates, 
		 * and checks if the date is valid within the given month and year.
		 *
		 * @param string $date - The date string to be validated and standardized.
		 *                        If not provided or empty, the current date is used.
		 *
		 * @return string - A date in the format "Y-m-d" (e.g., "2024-11-27").
		 */
		global $regnaar;
		$day = NULL;
		$month = NULL;
		$year = NULL;

		$date = trim($date);

		if (!isset($date) || !$date)
			$date = date("dmY");

		$date = str_replace(".", "-", $date);
		$date = str_replace(" ", "-", $date);
		$date = str_replace("/", "-", $date);

		if (strpos($date, "-"))
			list($day, $month, $year) = explode('-', $date);
		if ($year)
			$year = (int)$year;
		if ($month)
			$month = (int)$month;
		if ($day)
			$day = (int)$day;
		if ($year && $year < 10)
			$year = '0' . $year;
		elseif (!$year)
			$year = "";
		if ($month && $month < 10)
			$month = '0' . $month;
		elseif (!$month)
			$month = "";
		if ($day && $day < 10)
			$day = '0' . $day;
		if ($day)
			$date = $day . $month . $year;

		if (strlen($date) <= 2) {
			$date = (int)$date;
			if ($date < 10)
				$date = '0' . $date;
			$date = $date . date("m");
		}
		if (strlen($date) <= 4) {
			$g1 = substr($date, 0, 2);
			$g2 = substr($date, 2, 2);
			$qtxt = "select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$startmaaned = trim($r['box1']);
				$startaar = trim($r['box2']);
				$slutmaaned = trim($r['box3']);
				$slutaar = trim($r['box4']);
				if ($startaar == $slutaar)
					$g3 = $startaar;
				elseif ($g2 >= $startmaaned)
					$g3 = $startaar;
				else
					$g3 = $slutaar;
			} else {
				$alerttekst = 'Regnskabs&aring;r ikke oprettet!';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				exit;
			}
			$date = $g1 . "-" . $g2 . "-" . $g3;
		} elseif (strlen($date) <= 6) {
			$g1 = substr($date, 0, 2);
			$g2 = substr($date, 2, 2);
			$g3 = substr($date, 4, 2);
			$date = $g1 . "-" . $g2 . "-" . $g3;
		} else {
			$g1 = substr($date, 0, 2);
			$g2 = substr($date, 2, 2);
			$g3 = substr($date, 4, 4);
			$date = $g1 . "-" . $g2 . "-" . $g3;
		}

		list($day, $month, $year) = explode('-', $date);

		$year = intval($year) * 1;
		$month = intval($month) * 1;
		$day = intval($day) * 1;

		if ($year < 10) {
			$year = '0' . $year;
		}
		if ($month < 10) {
			$month = '0' . $month;
		}
		if ($day < 10) {
			$day = '0' . $day;
		}

		if ($day > 28) {
			while (!checkdate($month, $day, $year)) {
				$day = $day - 1;
				if ($day < 28)
					break 1;
			}
		}

		if ($year < 80) {
			$year = "20" . $year;
		} elseif ($year < 100) {
			$year = "19" . $year;
		}

		if (checkdate($month, $day, $year)) {
			$date = $year . "-" . $month . "-" . $day;
		} else {
			$date = date("Y-m-d");
		}

		return $date;
	}
}
if (!function_exists('findtekst')) include_once(__DIR__.'/stdFunc/findTxt.php');

if (!function_exists('javascript')) {
	function javascript()
	{

	}
}
if (!function_exists('afrund')) {
	function afrund($tal, $decimaler)
	{
		/**
		 * Corrects rounding errors in PHP by adjusting the rounding threshold.
		 *
		 * This function addresses rounding errors in PHP that can occur when working with floating-point numbers.
		 * It ensures that numbers are rounded to a specified number of decimal places by adding a small correction factor
		 * before rounding the number. This helps to prevent inaccuracies caused by the way PHP handles floating-point arithmetic.
		 *
		 * @param float $tal - The number to be rounded.
		 * @param int $decimaler - The number of decimal places to round to.
		 *
		 * @return float - The number rounded to the specified number of decimal places.
		 */
		# Korrigerer afrundingsfejl i php 
		$decimaler = $decimaler * 1;
		$tmp = 0.001;
		for ($x = 1; $x < $decimaler; $x++) {
			$tmp = $tmp / 10;
		}
		if ($tal > 0)
			$tal = round($tal + $tmp, $decimaler);
		elseif ($tal < 0)
			$tal = round($tal - $tmp, $decimaler);
		return $tal;
	}
}
if (!function_exists('fjern_nul')) {
	function fjern_nul($tal)
	{
		/**
		 * Removes trailing zeros from a decimal number.
		 *
		 * This function takes a number represented as a string and removes any unnecessary trailing zeros
		 * after the decimal point. If the number has a decimal part, it ensures the decimal part is stripped of trailing zeros.
		 * If the decimal part is entirely zeros, it removes the decimal point as well.
		 *
		 * @param string $tal - The number (as a string) from which trailing zeros are to be removed.
		 *
		 * @return string - The number without trailing zeros in the decimal part.
		 */
		if (strpos($tal, ",")) {
			list($a, $b) = explode(",", $tal);
			$b = $b * 1;
			if ($b)
				$tal = $a . "," . $b;
			else
				$tal = $a;
		}
		return $tal;
	}
}
if (!function_exists('bynavn')) {
	function bynavn($postnr)
	{
		/**
		 * Retrieves the city name based on a postal code from a CSV file.
		 *
		 * This function reads a CSV file containing postal codes and city names, searches for the given postal code (`$postnr`),
		 * and returns the corresponding city name. The function expects the CSV file to have tab-separated values, where the first
		 * column is the postal code and the second column is the city name. The city name is returned after removing any quotes.
		 *
		 * @param string $postnr - The postal code to search for in the CSV file.
		 *
		 * @return string|null - The city name corresponding to the postal code, or null if no match is found.
		 */
		global $db_encode;
		$bynavn = null;

		$fp = fopen("../importfiler/postnr.csv", "r");
		if ($fp) {
			while ($linje = trim(fgets($fp))) {
				if ($db_encode == "UTF8")
					$linje = mb_convert_encoding($linje, 'UTF-8', 'ISO-8859-1');
				list($a, $b) = explode(chr(9), $linje);
				if ($a == $postnr) {
					$bynavn = str_replace('"', '', $b);
					break 1;
				}
			}
		}
		fclose($fp);
		return ("$bynavn");
	}
}

if (!function_exists('felt_fra_tekst')) {
	function felt_fra_tekst($feltmatch, $tekstlinjer)
	{
		/**
		 * Removes matched text from lines in a given array of text.
		 *
		 * This function searches for lines containing a specified text (`$feltmatch`) within an array of text lines
		 * (`$tekstlinjer`). It then removes the matched text from the lines that contain it and returns the result.
		 * The function uses regular expressions to find the matching lines and `str_replace` to remove the matched portion.
		 *
		 * @param string $feltmatch - The text to search for and remove from the lines.
		 * @param array $tekstlinjer - An array of text lines where the search will take place.
		 *
		 * @return string|null - The modified line with the matched text removed, or null if no match is found.
		 */
		$matchende_linjer = preg_grep("/$feltmatch/", $tekstlinjer);
		foreach ($matchende_linjer as $linje) {
			$retur = str_replace($feltmatch, "", $linje);
		}
		return $retur;
	}
}

if (!function_exists('sidste_dag_i_maaned')) {
	function sidste_dag_i_maaned($aar, $maaned)
	{
		/**
		 * Returns the last day of the given month and year.
		 *
		 * This function calculates the last day of the specified month and year. It uses the `mktime` function to generate
		 * a timestamp for the 0th day of the next month and then formats it to return the day (which will be the last day of
		 * the given month).
		 *
		 * @param int $aar - The year (e.g., 2024).
		 * @param int $maaned - The month (1 for January, 2 for February, etc.).
		 *
		 * @return string - The last day of the month in "d" format (e.g., "31").
		 */
		$maaned++;
		$retur = date("d", mktime(12, 0, 0, $maaned, 0, $aar));
		return $retur;
	}
}

if (!function_exists('farvenuance')) {
	function farvenuance($farve, $nuance)
	{ # Notation for nuance: -33+33-33 eller -3+3-3
		/**
		 * Adjusts the color by applying a specified nuance to its red, green, and blue components.
		 *
		 * This function takes a color and a nuance value to modify the color's components (red, green, blue).
		 * The color and nuance values are expected to be in hexadecimal format. The nuance is applied to each 
		 * component of the color individually, allowing the creation of lighter or darker shades.
		 * The function returns a new color in hexadecimal format after the nuance is applied.
		 *
		 * The nuance format is a string of 6 characters (e.g., "-33+33-33"), where each pair of characters represents
		 * the adjustment to the red, green, and blue components respectively. A negative sign indicates a reduction, and
		 * a positive sign indicates an increase in that component.
		 *
		 * @param string $farve - The base color in hexadecimal format (e.g., "#ff5733").
		 * @param string $nuance - The nuance value to apply to the color. The format is a string like "-33+33-33", where 
		 *                         each pair of characters represents adjustments for red, green, and blue components.
		 *
		 * @return string - The modified color in hexadecimal format (e.g., "#ff5733").
		 *
		 * @note If the `farve` or `nuance` is not provided in the expected format, the function will return the base background color (`$bgcolor`).
		 *       The background color will default to white (`#ffffff`) if it is not specified.
		 */
		global $bgcolor;

		if ($bgcolor == "#")
			$bgcolor = "#ffffff"; # 20141010 Hvis ingen bgcolor er angivet, så benyttes hvid som baggrund.
		if ($farve == "#")
			$farve = "#ffffff"; # 20141010 Hvis ingen farve er angivet, så benyttes hvid som baggrund.

		$retur = $bgcolor;

		$farve = preg_replace("/[^0-9A-Fa-f]/", '', $farve);

		if (strlen($farve) == 3) {
			$roed_farve = hexdec(str_repeat(substr($farve, 0, 1), 2));
			$groen_farve = hexdec(str_repeat(substr($farve, 1, 1), 2));
			$blaa_farve = hexdec(str_repeat(substr($farve, 2, 1), 2));
		} else {
			$roed_farve = hexdec(substr($farve, 0, 2));
			$groen_farve = hexdec(substr($farve, 2, 2));
			$blaa_farve = hexdec(substr($farve, 4, 2));
		}

		if (strlen($nuance) == 6) {
			$roed_fortegn = substr($nuance, 0, 1) . "1";
			$roed_nuance = $roed_fortegn * hexdec(str_repeat(substr($nuance, 1, 1), 2));
			$groen_fortegn = substr($nuance, 2, 1) . "1";
			$groen_nuance = $groen_fortegn * hexdec(str_repeat(substr($nuance, 3, 1), 2));
			$blaa_fortegn = substr($nuance, 4, 1) . "1";
			$blaa_nuance = $blaa_fortegn * hexdec(str_repeat(substr($nuance, 5, 1), 2));
		} else {
			$roed_fortegn = substr($nuance, 0, 1) . "1";
			$roed_nuance = intval($roed_fortegn) * hexdec(substr($nuance, 1, 2));
			$groen_fortegn = substr($nuance, 3, 1) . "1";
			$groen_nuance = intval($groen_fortegn) * hexdec(substr($nuance, 4, 2));
			$blaa_fortegn = substr($nuance, 6, 1) . "1";
			$blaa_nuance = intval($blaa_fortegn) * hexdec(substr($nuance, 7, 2));
		}

		$roed_farve = $roed_farve + $roed_nuance;
		if ($roed_farve < 0)
			$roed_farve = 0;
		if ($roed_farve > 255)
			$roed_farve = 255;
		$groen_farve = $groen_farve + $groen_nuance;
		if ($groen_farve < 0)
			$groen_farve = 0;
		if ($groen_farve > 255)
			$groen_farve = 255;
		$blaa_farve = $blaa_farve + $blaa_nuance;
		if ($blaa_farve < 0)
			$blaa_farve = 0;
		if ($blaa_farve > 255)
			$blaa_farve = 255;

		$roed_farve = str_pad(dechex($roed_farve), 2, STR_PAD_LEFT);
		$groen_farve = str_pad(dechex($groen_farve), 2, STR_PAD_LEFT);
		$blaa_farve = str_pad(dechex($blaa_farve), 2, STR_PAD_LEFT);

		$retur = "#" . $roed_farve . $groen_farve . $blaa_farve;

		return $retur;
	}
}

if (!function_exists('linjefarve')) {
	#function linjefarve ($linjefarve, $ulige_bg, $lige_bg, $nuance = 0, $stdnuance = 0) {
	function linjefarve($linjefarve, $ulige_bg, $lige_bg, $stdnuance = 0, $nuance = 0)
	{
		/**
		 * Determines the line color based on the background color and specified nuance values.
		 *
		 * This function checks whether the provided `$linjefarve` matches the odd or even background color. 
		 * If it does, it adjusts the background color by applying a nuance to the even background (`$lige_bg`). 
		 * If it doesn't match, it adjusts the background color by applying a nuance to the odd background (`$ulige_bg`). 
		 * The function uses a helper function `farvenuance` to apply the nuance, if specified. 
		 * The default nuance and standard nuance values can be adjusted for custom color adjustments.
		 *
		 * @param string $linjefarve - The current line color that is being evaluated.
		 * @param string $ulige_bg - The background color for odd lines.
		 * @param string $lige_bg - The background color for even lines.
		 * @param int $stdnuance - A standard nuance value for adjusting the background color. Default is 0 (no nuance).
		 * @param int $nuance - The nuance value to be applied to the selected background color. Default is 0 (no nuance).
		 *
		 * @return string - The adjusted background color, with or without nuance applied.
		 *
		 * @note The function compares `$linjefarve` with `$ulige_bg` or a nuanced version of it, and applies `$nuance` if specified. 
		 *       If no nuance is applied, it simply returns the appropriate background color (`$lige_bg` or `$ulige_bg`).
		 */

		if ($linjefarve === $ulige_bg || $linjefarve === farvenuance($ulige_bg, $stdnuance)) {
			if ($nuance) {
				$retur = farvenuance($lige_bg, $nuance);
			} else {
				$retur = $lige_bg;
			}
		} else {
			if ($nuance) {
				$retur = farvenuance($ulige_bg, $nuance);
			} else {
				$retur = $ulige_bg;
			}
		}

		return $retur;
	}
}

if (!function_exists('copy_row')) {
	function copy_row($table, $id)
	{
		/**
		 * Copies a row from a specified table and inserts it as a new row with updated values.
		 *
		 * This function selects a row from the specified table based on the given `id`, processes the row's fields, 
		 * and inserts a new row with the same field values, while making adjustments to certain fields like `posnr`. 
		 * If necessary, it also updates existing rows to shift the `posnr` values to accommodate the new row.
		 * The function is designed to handle specific conditions on fields (e.g., non-zero `pris`, non-zero `m_rabat`, etc.).
		 *
		 * @param string $table - The name of the table from which to copy the row.
		 * @param int $id - The `id` of the row to copy.
		 *
		 * @return int|string - The `id` of the newly inserted row, or '0' if the table or ID is invalid.
		 * 
		 * @note The function performs checks to ensure only rows with specific conditions (`pris != 0`, `m_rabat != 0`, `rabat = 0`) are copied.
		 *       It also modifies the `posnr` field by incrementing it to avoid conflicts with existing rows.
		 */
		if (!$table || !$id)
			return ('0');
		$r = 0;
		$x = 0;
		$fieldstring = NULL;
		$qtxt = "select * from $table where pris != '0' and m_rabat != '0' and rabat = '0' and id='$id'";
		$q = db_select("$qtxt", __FILE__ . " linje " . __LINE__);
		while ($r < db_num_fields($q)) {
			if (db_field_name($q, $r) != 'id') {
				$x++;
				$fieldName[$x] = db_field_name($q, $r);
				$fieldType[$x] = db_field_type($q, $r);
				($fieldstring) ? $fieldstring .= "," . $fieldName[$x] : $fieldstring = $fieldName[$x];
			}
			$r++;
		}
		$feltantal = $x;
		$ordre_id = NULL;
		$posnr = NULL;
		$x = 0;
		$q = db_select("$qtxt", __FILE__ . " linje " . __LINE__);
		if ($r = db_fetch_array($q)) {
			$fieldvalues = NULL;
			$selectstring = NULL;
			for ($y = 1; $y <= $feltantal; $y++) {
				$linjerabat = afrund($r['pris'] / $r['m_rabat'], 2);
				$feltnavn = $fieldName[$y];
				$felt[$y] = $r[$feltnavn];
				if ($fieldType[$y] == 'varchar' || $fieldType[$y] == 'text')
					$felt[$y] = addslashes($felt[$y]);
				if (substr($fieldType[$y], 0, 3) == 'int' || $fieldType[$y] == 'numeric')
					$felt[$y] *= 1;
				if ($fieldName[$y] == 'posnr') {
					$felt[$y]++;
					$posnr = $felt[$y];
				}
				if ($fieldName[$y] == 'ordre_id')
					$ordre_id = $felt[$y];
				($fieldvalues) ? $fieldvalues .= ",'" . $felt[$y] . "'" : $fieldvalues = "'" . $felt[$y] . "'";
				($selectstring) ? $selectstring .= " and " . $fieldName[$y] . "='" . $felt[$y] . "'" : $selectstring = $fieldName[$y] . "='" . $felt[$y] . "'";
			}
		}
		if ($posnr && $ordre_id)
			db_modify("update $table set posnr=posnr+1 where ordre_id = '$ordre_id' and posnr >= '$posnr'", __FILE__ . " linje " . __LINE__);
		db_modify("insert into ordrelinjer ($fieldstring) values ($fieldvalues)", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select id from $table where $selectstring", __FILE__ . " linje " . __LINE__));
		$ny_id = $r['id'];
		return ($ny_id);
	} # endfunc copy_row
}
if (!function_exists('reducer')) {
	function reducer($tal)
	{
		/**
		 * Removes trailing zeros and decimal points from a number string.
		 *
		 * This function takes a string representation of a number, and removes any trailing zeros 
		 * or decimal points (either period `.` or comma `,`). The function works by checking if the 
		 * number ends with these characters and then removing them one by one until the conditions are met.
		 *
		 * @param string $tal - The number as a string to be reduced by removing trailing zeros and decimal points.
		 *
		 * @return string - The number with trailing zeros and decimal points removed.
		 */
		while ((strpos($tal, ".") || strpos($tal, ",")) && ($tal && (substr($tal, -1, 1) == '0' or substr($tal, -1, 1) == ',' or substr($tal, -1, 1) == '.'))) {
			$tal = substr($tal, 0, strlen($tal) - 1);
		}
		return ($tal);
	}
}

if (!function_exists('transtjek')) {
	function transtjek() {
		/**
		 * Checks for any imbalance in the accounting transactions.
		 *
		 * This function calculates the difference between the total debet and kredit values 
		 * in the `transaktioner` table. If the absolute difference is greater than or equal to 1, 
		 * it sends an email notification regarding the imbalance. The function returns the absolute 
		 * difference between debet and kredit.
		 *
		 * @return float - The absolute difference between the debet and kredit values in the database.
		 *                 If the difference is less than 1, the return value will be 0.
		 * 
		 * @note The function sends an email notification to 'fejl@saldi.dk' if the imbalance is detected.
		 *       The email contains details of the database and the imbalance amount.
		 */

		global $db,$regnaar;
		$qtxt = "select box1,box2 from grupper where art = 'RA' and kodenr = '$regnaar'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$countFrom = $r['box2'] ."-". $r['box1'] ."-01";

		$qtxt = "select sum(round(debet,2)) as debet,sum(round(kredit,2)) as kredit from transaktioner where transdate >= '$countFrom'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$diff = abs(afrund($r['debet'] - $r['kredit'], 2));

		if ($diff > 0.1) {
			$message = $db . " | Ubalance i regnskab: kr: $diff";
			$headers = 'From: fejl@saldi.dk' . "\r\n" . 'Reply-To: fejl@saldi.dk' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'Ubalance i regnskab:' . $db, $message, $headers);
		}
		return $diff;
	}
}
if (!function_exists('cvrnr_omr')) {
	function cvrnr_omr($landekode)
	{
		/**
		 * Determines the country region based on a provided country code.
		 *
		 * This function returns a region identifier based on the provided country code. 
		 * The country code is typically a two-letter ISO 3166-1 alpha-2 code, and the 
		 * function returns "DK" for Denmark, "EU" for most European countries, or "UD" 
		 * for unknown or unsupported countries.
		 *
		 * @param string $landekode - The two-letter country code (e.g., 'dk' for Denmark, 'at' for Austria).
		 * 
		 * @return string - The region corresponding to the provided country code:
		 *                  - "DK" for Denmark.
		 *                  - "EU" for countries in the European Union.
		 *                  - "UD" for countries outside the EU or unsupported codes.
		 * 
		 * @note The country code lookup is case-sensitive and only supports the countries
		 *       specified in the function. Any other code will return "UD".
		 */
		$retur = "";
		if (!$landekode) {
			$retur = "";
		} else {
			switch ($landekode) {
				case "dk":
					$retur = "DK";
					break 1;
				case "at":
					$retur = "EU";
					break 1;
				case "be":
					$retur = "EU";
					break 1;
				case "cy":
					$retur = "EU";
					break 1;
				case "cz":
					$retur = "EU";
					break 1;
				case "de":
					$retur = "EU";
					break 1;
				case "ee":
					$retur = "EU";
					break 1;
				case "gr":
					$retur = "EU";
					break 1;
				case "es":
					$retur = "EU";
					break 1;
				case "fi":
					$retur = "EU";
					break 1;
				case "fr":
					$retur = "EU";
					break 1;
				#case "gb": $retur = "EU"; break 1;
				case "hu":
					$retur = "EU";
					break 1;
				case "ie":
					$retur = "EU";
					break 1;
				case "it":
					$retur = "EU";
					break 1;
				case "lt":
					$retur = "EU";
					break 1;
				case "lu":
					$retur = "EU";
					break 1;
				case "lv":
					$retur = "EU";
					break 1;
				case "mt":
					$retur = "EU";
					break 1;
				case "nl":
					$retur = "EU";
					break 1;
				case "pl":
					$retur = "EU";
					break 1;
				case "pt":
					$retur = "EU";
					break 1;
				case "ro":
					$retur = "EU";
					break 1;
				case "se":
					$retur = "EU";
					break 1;
				case "si":
					$retur = "EU";
					break 1;
				case "sk":
					$retur = "EU";
					break 1;
				case "gl":
					$retur = "UD";
					break 1;
				default:
					$retur = "UD";
					break 1;
			}
		}
		return $retur;
	}
}
if (!function_exists('cvrnr_land')) {
	function cvrnr_land($cvrnr)
	{
		$retur = "";

		$cvrnr = strtoupper($cvrnr);

		if (!$cvrnr) {
			$retur = "";
		} elseif (is_numeric(substr($cvrnr, 0, 1))) {
			$retur = "dk";
		} else {
			$start_tegn = strtolower(substr($cvrnr, 0, 3));
			switch ($start_tegn) {
				case "ger":
					$start_tegn = "gl";
					break 1;
				default:
					break 1;
			}
			$start_tegn = substr($start_tegn, 0, 2);
			switch ($start_tegn) {
				case "el":
					$retur = "gr";
					break 1;
				default:
					$retur = $start_tegn;
			}
		}
		return $retur;
	}
}
if (!function_exists('str2low')) {
	function str2low($string)
	{
		global $db_encode;

		$string = strtolower($string);

		if ($db_encode == 'UTF8') {
			$string = str_replace(chr(195) . chr(134), chr(195) . chr(166), $string);
			$string = str_replace(chr(195) . chr(152), chr(195) . chr(184), $string);
			$string = str_replace(chr(195) . chr(133), chr(195) . chr(165), $string);
		} else {
			$string = str_replace(chr(198), chr(230), $string);
			$string = str_replace(chr(216), chr(248), $string);
			$string = str_replace(chr(197), chr(229), $string);
		}
		return ("$string");
	}
}
if (!function_exists('str2up')) {
	function str2up($string)
	{
		global $db_encode;
		$string = strtoupper($string);
		if ($db_encode == 'UTF8') {
			$string = str_replace(chr(195) . chr(166), chr(195) . chr(134), $string);
			$string = str_replace(chr(195) . chr(184), chr(195) . chr(152), $string);
			$string = str_replace(chr(195) . chr(165), chr(195) . chr(133), $string);
		} else {
			$string = str_replace(chr(230), chr(198), $string);
			$string = str_replace(chr(248), chr(216), $string);
			$string = str_replace(chr(229), chr(197), $string);
		}
		$string = str_replace('æ', 'Æ', $string);
		$string = str_replace('ø', 'Ø', $string);
		$string = str_replace('å', 'Å', $string);
		return ("$string");
	}
}

# Tekstvinduer i CSS i stedet for JavaScript Alert - 20141031 - 20141121 - 20141212
# boksflytbar=span giver kun div, boksflytbar=td giver en tabel i en div boksflybar=0 giver ingen mulighed for at flytte. 
if (!function_exists('tekstboks')) {
	function tekstboks($bokstekst, $bokstype = 'advarsel', $boksid = 'boks1', $boksflytbar = 'span', $boksplacering = 'mm')
	{
		$boksindhold = "\n<!-- Tekstboks " . $boksid . " - start -->\n";

		if ($boksflytbar === 'td') {
			# Nedenstående linjer er forsøg på at påvirker det originale udseende så lidt som muligt 
# ved brug af den flytbare boks med <table> inden i en <div>. Læser man dokumentationen, 
# så skulle et element med display:none ikke have nogen indflydelse på udseendet, men det 
# har det i både Opera 25.0 og Chrome 38.0.2125.111 m. 
# 
# Claus Agerskov 20141121.
#		$boksindhold.="<div style='display:none'><table style='display:none'><tr><td>Test</td></tr></table></div>\n";
#		$boksindhold.="<table style='display:none'><tr><td>Test</td></tr></table>\n";
			$boksindhold .= "<table style='display:none'></table>\n"; # Giver mindst indvirkning på udseendet.
#		$boksindhold.="<tr style='display:none'><td>Test</td></tr>\n";
#		$boksindhold.="<table><tr><td>Test</td></tr></table>\n";
#		$boksindhold.="<div style='display:none'>Test2</div>\n";
		}

		if ($bokstype === 'fejl') {
			$bokskant = '#ff3333';
			$boksbaggrund = '#ffeeee';
		}
		if ($bokstype === 'advarsel') {
			$bokskant = '#ff9900';
			$boksbaggrund = '#ffeecc';
		}
		if ($bokstype === 'info') {
			$bokskant = '#0000ff'; # 20150313
			$boksbaggrund = '#eeeeff';
		}
		if ($bokstype === 'popop') {
			$bokskant = '#00ff00'; # 20150313
			$boksbaggrund = '#eeffff';
		}
		if (substr($boksplacering, 0, 1) == 'm')
			$boksvertikal = '30%';
		if (substr($boksplacering, 0, 1) == 't')
			$boksvertikal = '1%';
		if (substr($boksplacering, 0, 1) == 'b')
			$boksvertikal = '68%';
		if (substr($boksplacering, 1, 1) == 'm')
			$bokshorisontal = '30%';
		if (substr($boksplacering, 1, 1) == 'v')
			$bokshorisontal = '1%';
		if (substr($boksplacering, 1, 1) == 'h')
			$bokshorisontal = '68%';


		$boksindhold .= "\n<div id='" . $boksid . "' style='position:fixed; margin:10px; z-index:100; border:solid 4px " . $bokskant . "; padding:1px; background:" . $boksbaggrund . ";";
		if ($bokstype === 'info')
			$boksindhold .= " display:none;";
		$boksindhold .= " top:" . $boksvertikal . "; left:" . $bokshorisontal . "; width:320px;'>\n";
		if ($boksflytbar === 'td') {
			$boksindhold .= "<table><tr>\n";
			$boksindhold .= bokshjoerne($boksid, 'tv', 'td');
			$boksindhold .= "<td width='99%' rowspan='3'>\n";
		}
		$boksindhold .= "<p style='font-size: 12pt; background: " . $boksbaggrund . "; color: #000000'>\n";
		$boksindhold .= $bokstekst . "</p>\n";
		$boksindhold .= "<p style='font-size: 12pt; text-align:center'>\n";
		$boksindhold .= "<button type='button' style='width:100px; height:30px'";
		$boksindhold .= " onClick=\"document.getElementById('" . $boksid . "').style.display = 'none';\">Luk</button>\n";
		if ($boksflytbar === 'span') {
			$boksindhold .= "<br />";
			$boksindhold .= bokshjoerne($boksid, 'tv', 'span');
			$boksindhold .= "&nbsp;";
			$boksindhold .= bokshjoerne($boksid, 'th', 'span');
			$boksindhold .= "&nbsp;";
			$boksindhold .= bokshjoerne($boksid, 'bv', 'span');
			$boksindhold .= "&nbsp;";
			$boksindhold .= bokshjoerne($boksid, 'bh', 'span');
		}
		$boksindhold .= "</p>\n";
		if ($boksflytbar === 'td') {
			$boksindhold .= "</td>";
			$boksindhold .= bokshjoerne($boksid, 'th', 'td');
			$boksindhold .= "</tr>\n";
			$boksindhold .= "<tr><td>&nbsp;</td>";
			$boksindhold .= "<td>&nbsp;</td></tr>\n";
			$boksindhold .= "<tr>";
			$boksindhold .= bokshjoerne($boksid, 'bv', 'td');
			#                $boksindhold.="<td onClick=\"document.getElementById('".$boksid."').style.top = '68%'; document.getElementById('".$boksid."').style.left = '68%'; \">&#9698;</td>\n";
			$boksindhold .= bokshjoerne($boksid, 'bh', 'td');
			$boksindhold .= "</tr></table>\n";
		}
		$boksindhold .= "</div>\n";
		$boksindhold .= "\n<!-- Tekstboks " . $boksid . " - slut -->\n";
		return ("$boksindhold");
	}
}

# Hjørne til tekstbokse som ved klik flytter boksen i hjørnets retning. t=top, b=bund, v=venstre og h=hoejre. De kombineres til tv, th, bv og bh.
# Visning er td=<td>-celle, 0=intet, span=i teksten. 20141121
if (!function_exists('bokshjoerne')) {
	function bokshjoerne($boksid, $hjoerne, $visning = 'td', $kant_oppe = '1%', $kant_nede = '68%', $kant_venstre = '1%', $kant_hoejre = '68%', $kant_midt = '40%')
	{
		/**
		 * Generates a corner box with a clickable icon that moves the box to a corner of the screen.
		 *
		 * This function creates a box with a clickable icon in one of the four corners (top-left, top-right, bottom-left, bottom-right).
		 * When the user clicks the icon, the box moves to the corresponding corner. The function supports different HTML elements
		 * (`td`, `span`, etc.) for displaying the box, and allows customization of the position for the box's corners and the icon.
		 *
		 * @param string $boksid - The ID of the box element that will be moved to the specified corner when clicked.
		 * @param string $hjoerne - The corner to which the box should move. Accepts values:
		 *                          'tv' (top-left), 'th' (top-right), 'bv' (bottom-left), 'bh' (bottom-right).
		 * @param string $visning - The HTML element to be used for displaying the icon. Default is 'td'.
		 * @param string $kant_oppe - The vertical position (top) of the box. Default is '1%'.
		 * @param string $kant_nede - The vertical position (bottom) of the box. Default is '68%'.
		 * @param string $kant_venstre - The horizontal position (left) of the box. Default is '1%'.
		 * @param string $kant_hoejre - The horizontal position (right) of the box. Default is '68%'.
		 * @param string $kant_midt - A middle position for the box. Default is '40%' (not used in this implementation).
		 * 
		 * @return string - The HTML for the clickable corner icon element that triggers the box movement.
		 * 
		 * @note The `onClick` event changes the position of the box with the specified ID to the selected corner when clicked.
		 */

		if (!$visning)
			return "";

		if ($hjoerne == 'tv') {
			$vertikal_kant = $kant_oppe;
			$horisontal_kant = $kant_venstre;
			$tv_tegn = '&#9700;';
			$popopbesked = 'Op til venstre';
		} elseif ($hjoerne == 'th') {
			$vertikal_kant = $kant_oppe;
			$horisontal_kant = $kant_hoejre;
			$tv_tegn = '&#9701;';
			$popopbesked = 'Op til højre';
		} elseif ($hjoerne == 'bv') {
			$vertikal_kant = $kant_nede;
			$horisontal_kant = $kant_venstre;
			$tv_tegn = '&#9699;';
			$popopbesked = 'Ned til venstre';
		} elseif ($hjoerne == 'bh') {
			$vertikal_kant = $kant_nede;
			$horisontal_kant = $kant_hoejre;
			$tv_tegn = '&#9698;';
			$popopbesked = 'Ned til højre';
		}

		$bokshjoerne = "<" . $visning . " title='" . $popopbesked . "'";
		$bokshjoerne .= " onClick=\"document.getElementById('" . $boksid . "').style.top = '" . $vertikal_kant . "';";
		$bokshjoerne .= " document.getElementById('" . $boksid . "').style.left = '" . $horisontal_kant . "'; \">";
		$bokshjoerne .= $tv_tegn . "</" . $visning . ">\n";
		return $bokshjoerne;
	}
}

if (!function_exists('find_varemomssats')) {
	function find_varemomssats($linje_id)
	{
		/**
		 * Retrieves and sets the VAT rate for an order line item.
		 *
		 * This function calculates the VAT rate for a specific order line based on various conditions,
		 * including whether the item is exempt from VAT, the VAT rate associated with the order, and the 
		 * VAT rate associated with the item's group. The function updates the VAT rate for the order line 
		 * in the database and returns the determined VAT rate.
		 *
		 * @param int $linje_id - The ID of the order line whose VAT rate is to be determined.
		 * 
		 * @return string - The calculated VAT rate (as a string) for the given order line.
		 * 
		 * @note This function directly updates the `ordrelinjer` table with the determined VAT rate.
		 */

		global $regnaar;

		$r = db_fetch_array(db_select("select ordre_id,vare_id,momsfri,omvbet from ordrelinjer where id='$linje_id'", __FILE__ . " linje " . __LINE__));
		$ordre_id = $r['ordre_id'] * 1;
		$vare_id = $r['vare_id'] * 1;
		$momsfri = $r['momsfri'];
		$omvbet = $r['omvbet'];

		if (!$vare_id)
			return ("0");

		if ($momsfri) {
			db_modify("update ordrelinjer set momssats='0' where id = '$linje_id'", __FILE__ . " linje " . __LINE__);
			return ('0');
			exit;
		}
		$r = db_fetch_array(db_select("select momssats,status from ordrer where id='$ordre_id'", __FILE__ . " linje " . __LINE__));
		$momssats = $r['momssats'];
		$status = $r['status'];

		$r = db_fetch_array(db_select("select gruppe from varer where id = '$vare_id'", __FILE__ . " linje " . __LINE__));
		$gruppe = $r['gruppe'];
		$r = db_fetch_array(db_select("select box4,box6,box7,box8 from grupper where art = 'VG' and kodenr = '$gruppe'", __FILE__ . " linje " . __LINE__));
		$bogfkto = if_isset($r2['box4']); #20190605 + 1 line
		$momsfri = if_isset($r2['box7']);
		if ($momsfri) {
			db_modify("update ordrelinjer set momssats='0' where id = '$linje_id'", __FILE__ . " linje " . __LINE__);
			return ('0');
			exit;
		}
		if ($bogfkto) {
			$r = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$bogfkto' and regnskabsaar = '$regnaar'", __FILE__ . " linje " . __LINE__));
			if ($tmp = trim($r2['moms'])) { # f.eks S3
				$tmp = substr($tmp, 1); #f.eks 3
				$r2 = db_fetch_array(db_select("select box2 from grupper where art = 'SM' and kodenr = '$tmp'", __FILE__ . " linje " . __LINE__));
				if ($r2['box2'])
					$varemomssats = $r2['box2'] * 1;
			} else
				$varemomssats = $momssats;
		} else
			$varemomssats = $momssats;
		db_modify("update ordrelinjer set momssats='$varemomssats' where id = '$linje_id'", __FILE__ . " linje " . __LINE__);
		return ("$varemomssats");
	}
}

#edit 2019.02.21
if (!function_exists('infoboks')) {
	function infoboks($infosymbol, $infotekst, $infotype, $hjoerne, $visning = 'span', $kant_oppe = '1%', $kant_nede = '68%', $kant_venstre = '1%', $kant_hoejre = '68%', $kant_midt = '40%', $boksid = "")
	{
		/**
		 * Generates an information box with a symbol and a tooltip that displays additional text.
		 *
		 * This function creates a clickable element (like a `span` or `a` tag) with an information symbol, which,
		 * when clicked, shows an information box with the provided text. The position and style of the information
		 * box are customizable through various parameters.
		 *
		 * @param string $infosymbol - The symbol or icon to display (e.g., an info icon or a text character).
		 * @param string $infotekst - The text to display inside the information box when clicked.
		 * @param string $infotype - The type of information (e.g., a message type, such as 'info' or 'error').
		 * @param string $hjoerne - Specifies corner rounding or positioning for the information box (currently unused in the function).
		 * @param string $visning - Specifies the HTML tag to wrap the information symbol (default is 'span').
		 * @param string $kant_oppe - The top margin (default is '1%').
		 * @param string $kant_nede - The bottom margin (default is '68%').
		 * @param string $kant_venstre - The left margin (default is '1%').
		 * @param string $kant_hoejre - The right margin (default is '68%').
		 * @param string $kant_midt - The horizontal position (default is '40%').
		 * @param string $boksid - The unique ID for the information box, used to show/hide the box (default is an empty string).
		 * 
		 * @return string - The HTML markup for the clickable element with the information symbol and the tooltip functionality.
		 */

		$infoboks = "";
		$infoboks .= tekstboks($infotekst, $infotype, $boksid);
		if (!$visning)
			return "";

		$infoboks .= "<" . $visning . " title='Hjælpetekst til siden'";
		$infoboks .= " onClick=\"document.getElementById('" . $boksid . "').style.display = 'block'; \">";
		$infoboks .= $infosymbol . "</" . $visning . ">\n";
		return $infoboks;
	}
}
if (!function_exists('find_lagervaerdi')) {
	function find_lagervaerdi($kontonr, $slut, $tidspkt)
	{
		/**
		 * Calculates the total inventory value for a given account number and date.
		 *
		 * This function computes the total value of inventory by considering purchases 
		 * and sales for the specified account number, based on a specific cutoff date (`$slut`). 
		 * It fetches the relevant data from multiple tables and returns the inventory value.
		 *
		 * @param string $kontonr - The account number for which the inventory value should be calculated.
		 * @param string $slut - The cutoff date for the inventory value calculation in 'YYYY-MM-DD' format.
		 * @param string $tidspkt - Specifies the time point ('start' or other) for the calculation.
		 * 
		 * @return float|string - The total inventory value if calculations are successful, or 'stop' if no cutoff date is provided.
		 */

		global $regnaar;
		$x = 0;
		$lagervaerdi = 0;
		$lager = array();
		$gruppe = array();
		$kob = 0;
		$salg = 0;

		if (!$slut) {
			return ('stop');
		}
		$qtxt = "select kodenr,box1,box2,box3,box11,box13 from grupper where art = 'VG' and box8 = 'on' and ";
		$qtxt.= "(box1 = '$kontonr' or box2 = '$kontonr' or box3 = '$kontonr') and fiscal_year = '$regnaar'";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['box1'] == $kontonr)
				$kob = 1;
			if ($r['box2'] == $kontonr)
				$salg = 1;
			if ($r['box3'] == $kontonr) {
				$salg = 1;
				$kob = 1;
			}
			if ($r['box11'] == $kontonr) {
				$salg = 1;
				$kob = 1;
			}
			if ($r['box13'] == $kontonr) {
				$salg = 1;
				$kob = 1;
			}
			$gruppe[$x] = $r['kodenr'];
			$x++;
		}
		$vare_id = array();
		/////////////////////////////////////
/*
	$x=0;
	$qtxt="select kostpriser.vare_id,kostpriser.kostpris,varer.gruppe from kostpriser,varer";
	$qtxt.=" where ";
	$qtxt.="kostpriser.transdate<='$slut' and varer.id=kostpriser.vare_id";
	$qtxt.=" order by ";
	$qtxt.="kostpriser.transdate desc";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['vare_id'],$vare_id) && in_array($r['gruppe'],$gruppe)) {
			$vare_id[$x]=$r['vare_id'];
			$kostpris[$x]=$r['kostpris'];
			$antal[$x]=0;
			$x++;
		}
	}
	$qtxt="select id,kostpris,gruppe from varer";
	$qtxt.=" order by ";
	$qtxt.="id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['id'],$vare_id) && in_array($r['gruppe'],$gruppe)) {
			$vare_id[$x]=$r['id'];
			$kostpris[$x]=$r['kostpris'];
			$antal[$x]=0;
			$x++;
		}
	}
/////////////////////////
*/
		$y = 0;
		for ($x = 0; $x < count($gruppe); $x++) {
			$q = db_select("select id,kostpris from varer where gruppe = '$gruppe[$x]' order by id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$vare_id[$y] = $r['id'];
				$kostpris[$y] = $r['kostpris'];
				$antal[$y] = 0;
				$y++;
			}
		}
		for ($x = 0; $x < count($vare_id); $x++) {
			if ($kob) { # 20170404 Tilføjet and fakturadate > '1970-01-01' da ikke bogførte købsordrer kan give skæve tal.
				if ($tidspkt == 'start')
					$qtxt = "select sum(antal) as antal from batch_kob where vare_id = $vare_id[$x] and kobsdate < '$slut'and kobsdate < '$slut'"; # or kobsdate < '$slut'
				else
					$qtxt = "select sum(antal) as antal from batch_kob where vare_id = $vare_id[$x] and kobsdate > '1970-01-01' and kobsdate <= '$slut'"; # or kobsdate <= '$slut'	
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$antal[$x] += $r['antal'];
			}
			if ($salg) {
				if ($tidspkt == 'start')
					$qtxt = "select sum(antal) as antal from batch_salg where vare_id = $vare_id[$x] and salgsdate < '$slut'";
				else
					$qtxt = "select sum(antal) as antal from batch_salg where vare_id = $vare_id[$x] and salgsdate <= '$slut'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$antal[$x] -= $r['antal'];
			}
			$vaerdi[$x] = $antal[$x] * $kostpris[$x];
			$lagervaerdi += $vaerdi[$x];
		}
		return ($lagervaerdi);
	}
}

// Funktion som laver uppercase på første bogstav i streng. Virker som php funktion 'ucfirst', men med æøå
if (!function_exists('mb_ucfirst')) {
	function mb_ucfirst($str, $encoding = 'UTF-8')
	{
		/**
		 * Converts the first character of a string to uppercase.
		 *
		 * This function works similarly to the PHP function `ucfirst()`, but it properly handles
		 * multibyte characters (such as æ, ø, å) when working with encodings like UTF-8.
		 *
		 * @param string $str - The input string where the first character should be converted to uppercase.
		 * @param string $encoding - The character encoding to be used (default is UTF-8).
		 * 
		 * @return string - The string with the first character converted to uppercase, and the rest unchanged.
		 */

		$firstChar = mb_substr($str, 0, 1, $encoding);
		$then = mb_substr($str, 1, mb_strlen($str, $encoding) - 1, $encoding);
		return mb_strtoupper($firstChar, $encoding) . $then;
	}
}

if (!function_exists('mb_ucwords')) {
	function mb_ucwords($str)
	{
		/**
		 * Capitalizes the first letter of each word in a string.
		 *
		 * This function performs a title case conversion on a string, meaning it capitalizes 
		 * the first letter of each word while keeping the rest of the letters in lowercase. 
		 * It uses multibyte string handling to support UTF-8 encoded characters and danish æøå.
		 *
		 * @param string $str - The input string to be converted.
		 * 
		 * @return string - The string with each word capitalized.
		 */

		return mb_convert_case($str, MB_CASE_TITLE, "UTF-8");
	}
}
if (!function_exists('ftptest')) {
	function ftptest($server, $bruger, $kode)
	{
		/**
		 * Tests FTP connection by uploading and downloading a test file.
		 *
		 * This function performs a basic test of an FTP connection by:
		 * 1. Creating a temporary test file on the server.
		 * 2. Uploading the test file to the FTP server using the `ncftp` command-line tool.
		 * 3. Attempting to download the test file back from the FTP server.
		 * If the file is successfully downloaded, it confirms that the FTP connection works.
		 * If there is an issue with the connection, it will alert with an error message.
		 *
		 * @param string $server - The FTP server address.
		 * @param string $bruger - The FTP username.
		 * @param string $kode - The FTP password.
		 * 
		 * @return void - This function prints an alert message indicating whether the FTP connection was successful or not.
		 */

		global $db;
		global $exec_path;
		$fp = fopen("../temp/$db/test.txt", "w");
		fwrite($fp, "Hej der\n");
		fclose($fp);
		$fp = fopen("../temp/$db/ftpscript1", "w");
		fwrite($fp, "set confirm-close no\nput test.txt\nbye\n");
		fclose($fp);
		$kommando = "cd ../temp/$db\n$exec_path/ncftp ftp://" . $bruger . ":" . $kode . "@" . $server . " < ftpscript1 > ftp1.log ";
		system($kommando);
		unlink("../temp/$db/test.txt");
		$fp = fopen("../temp/$db/ftpscript2", "w");
		fwrite($fp, "set confirm-close no\nget test.txt\nbye\n");
		fclose($fp);
		$kommando = "cd ../temp/$db\n$exec_path/ncftp ftp://" . $bruger . ":" . $kode . "@" . $server . " < ftpscript2 > ftp2.log ";
		system($kommando);
		(file_exists("../temp/$db/test.txt")) ? $txt = "FTP tjek OK" : $txt = "Fejl i FTP oplysninger";
		print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		unlink("../temp/$db/test.txt");
		unlink("../temp/$db/ftpscript1");
		unlink("../temp/$db/ftpscript2");
	}
}
if (!function_exists('valutaopslag')) {
	function valutaopslag($amount, $valuta, $transdate)
	{
		/**
		 * Retrieves and applies the exchange rate for a given currency on a specific transaction date.
		 *
		 * This function checks the `valuta` table for the exchange rate (`kurs`) of a given currency (`$valuta`) 
		 * on or before the specified transaction date (`$transdate`). If a valid exchange rate is found, it calculates 
		 * the amount in the base currency by multiplying the amount by the exchange rate, and returns the adjusted amount 
		 * along with a relevant account code. If no exchange rate is found, an error message is displayed.
		 *
		 * @param float $amount - The amount in the foreign currency to be converted.
		 * @param string $valuta - The currency code for which the exchange rate is being looked up.
		 * @param string $transdate - The transaction date for which the exchange rate should be applied.
		 * 
		 * @return array - An array containing:
		 *                 - The converted amount after applying the exchange rate.
		 *                 - A currency-related account code (`diffkonto`).
		 *                 - The exchange rate used for conversion (`kurs`).
		 */

		global $connection;
		global $fejltext;

		$r = db_fetch_array(db_select("select * from valuta where gruppe = '$valuta' and valdate <= '$transdate' order by valdate desc", __FILE__ . " linje " . __LINE__));
		if ($r['kurs']) {
			$kurs = $r['kurs'];
			$amount = afrund($amount * $kurs / 100, 2); # decimal rettet fra 3 til 2 20090617 grundet fejl i saldi_58_20090617-2224
		} else {
			$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '$valuta'", __FILE__ . " linje " . __LINE__));
			$tmp = dkdato($transdate);
			$fejltext = "---";
			print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs for $r[box1] den $tmp')\">";
		}
		$r = db_fetch_array(db_select("select box3 from grupper where art = 'VK' and kodenr = '$valuta'", __FILE__ . " linje " . __LINE__));
		$diffkonto = $r['box3'];

		return array($amount, $diffkonto, $kurs); # 3'die parameter tilfojet 2009.02.10
	}
}

if (!function_exists('regnstartslut')) {
	function regnstartslut($regnaar) {
		/**
		 * Retrieves the start and end dates for a given financial year.
		 *
		 * This function queries the `grupper` table for the financial year (`regnaar`) specified,
		 * then constructs and returns the start and end dates for that year. The start date is the first day
		 * of the starting month and year, while the end date is the last day of the ending month and year.
		 * The result is returned as a string with the start and end dates separated by a tab character.
		 *
		 * @param string $regnaar - The financial year identifier (typically a code or number).
		 * 
		 * @return string - A string containing the start and end dates for the financial year,
		 *                  formatted as "YYYY-MM-DD	YYYY-MM-DD".
		 */
		$r = db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr = '$regnaar'", __FILE__ . " linje " . __LINE__));
		$startmd = $r['box1'];
		$startaar = $r['box2'];
		$slutmd = $r['box3'];
		$slutaar = $r['box4'];
		$regnstart = $startaar . '-' . $startmd . '-01';
		$regnslut = $slutaar . '-' . $slutmd . '-31';
		return ($regnstart . chr(9) . $regnslut);
	}
}

if (!function_exists('lagerreguler')) {
	function lagerreguler($vare_id, $ny_beholdning, $kostpris, $lager, $transdate, $variant_id) {
		/**
		 * Adjusts the stock levels of an item in the inventory system.
		 *
		 * This function updates the stock levels of a given product (identified by its `vare_id`) in the 
		 * inventory system, considering factors like variant, stock location, and transaction date. It handles
		 * both stock increases and decreases, and updates related tables like `lagerstatus`, `batch_kob`, and `batch_salg`.
		 * It also ensures synchronization with an external system by calling `sync_shop_vare` and updates the overall stock level.
		 *
		 * @param int $vare_id - The unique identifier of the product (item) whose stock level is to be adjusted.
		 * @param float $ny_beholdning - The new stock level to be set for the item.
		 * @param float $kostpris - The cost price of the item, used when adjusting stock quantities in `batch_kob`.
		 * @param int $lager - The stock location identifier.
		 * @param string $transdate - The transaction date of the stock adjustment.
		 * @param int $variant_id - The variant identifier of the item (if applicable).
		 * 
		 * @return void - This function does not return any value but modifies the stock levels in the database.
		 */

		global $db;

		$qtxt = "select box4 from grupper where art='API'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$api_fil = trim($r['box4']);

		if ($lager < 1)
			$lager = 1;

		$ny_beholdning = (float) $ny_beholdning;
		$vare_id = (int) $vare_id;
		$variant_id = (int) $variant_id;

		$x = 0;
		$qtxt = "update lagerstatus set variant_id='0' where vare_id='$vare_id' and variant_id is NULL";
		#	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update lagerstatus set lager='1' where  vare_id='$vare_id' and lager = '0' or lager is NULL";
		#	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		# If the item already exsits in lagerstatus, update it, else change it
		$qtxt = "select id,beholdning from lagerstatus where vare_id='$vare_id' and lager='$lager' and variant_id='$variant_id' order by id limit 1";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			# $qtxt = "delete from lagerstatus where vare_id='$vare_id' and lager='$lager' and variant_id='$variant_id' and id !='$r[id]'";
			#		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$existingStock = $r['beholdning'];
			$diff = $ny_beholdning - $existingStock;
			if ($diff) {
#				echo "Updating";
#						echo $ny_beholdning;
				$qtxt = "update lagerstatus set beholdning='$ny_beholdning' where id='$r[id]'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$qtxt = "update variant_varer set variant_beholdning='$ny_beholdning' where id='$variant_id'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		} else {
			$qtxt = "insert into lagerstatus(vare_id,variant_id,beholdning,lager) values ('$vare_id','$variant_id','$ny_beholdning','$lager')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$diff = "$ny_beholdning";
		}
		# Når en lager ændring laves, der ikke har en lerveringsorder bhgskal den indsættes før lagerstyringen giver mening
		if ($diff > 0) {
			$qtxt = "insert into batch_kob(vare_id,variant_id,linje_id,kobsdate,fakturadate,ordre_id,antal,pris,rest,lager)";
			$qtxt .= "values";
			$qtxt .= "('$vare_id','$variant_id','0','$transdate','$transdate','0','$diff','$kostpris','$diff','$lager')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			$diff *= -1;
			$qtxt = "select id,rest,pris from batch_kob where vare_id='$vare_id' and lager='$lager' and variant_id='$variant_id' and rest>'0' order by kobsdate,id";
			$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($diff && $r = db_fetch_array($q)) {
				$pris = (float)$r['pris'];
				if ($diff - $r['rest'] >= 0) {
					$qtxt = "update batch_kob set rest='0' where id='$r[id]'";
					db_modify("update batch_kob set rest='0' where id='$r[id]'", __FILE__ . " linje " . __LINE__);
					$qtxt = "insert into batch_salg(batch_kob_id,vare_id,variant_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,lager)";
					$qtxt .= "values";
					$qtxt .= "('$r[id]','$vare_id','$variant_id','0','$transdate','$transdate','0','$r[rest]','$pris','1','$lager')";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					$diff -= $r['rest'];
				} else {
					if (!$diff) $diff = 0;
					$qtxt = "update batch_kob set rest=rest+$diff where id='$r[id]'";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					$qtxt = "insert into batch_salg(batch_kob_id,vare_id,variant_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,lager)";
					$qtxt .= "values";
					$qtxt .= "('$r[id]','$vare_id','$variant_id','0','$transdate','$transdate','0','$diff','$pris','1','$lager')";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					$diff = 0;
				}
			}
			if ($diff) {
				$qtxt = "insert into batch_salg(batch_kob_id,vare_id,variant_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,lager)";
				$qtxt .= "values";
				$qtxt .= "('0','$vare_id','$variant_id','0','$transdate','$transdate','0','$diff','$kostpris','1',$lager)";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
    $qtxt="select id from styklister where vare_id='$vare_id' limit 1";
#		if (db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
#			$diff = $ny_beholdning - $existingStock;
#			include_once('productCardIncludes/updateParentStock.php');
#			updateParentStock($id, $lager, $diff);
#	  }
		sync_shop_vare($vare_id, $variant_id, $lager);
		$qtxt = "select sum(beholdning) as beholdning from lagerstatus where vare_id='$vare_id'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$beholdning = $r['beholdning'] * 1;
		$qtxt = "update varer set beholdning='$beholdning' where id='$vare_id'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		#
	}
} #	endfunc lagerreguler

if (!function_exists('saldikrypt')) {
	function saldikrypt($id, $pw)
	{
		/**
		 * Encrypts a password using a custom cryptographic method.
		 *
		 * This function takes an ID and a password, performs a series of transformations on the password,
		 * and returns an encrypted password (MD5 hash).
		 *
		 * @param int $id - A numeric ID used in the transformation process.
		 * @param string $pw - The password to be encrypted.
		 * 
		 * @return string - The resulting MD5 hash after all transformations.
		 */

		$tmp = '';
		for ($i = 0; $i < strlen($pw); $i++)
			$tmp .= ord(substr($pw, $i, 1)) * 3;
		$pw = md5($tmp);
		for ($i = 0; $i < $id * 100; $i++) {
			$y = round(substr($i, -2) / 4, 0);
			if (is_numeric(substr($pw, $y, 1)))
				$pw = md5(strrev($pw));
			else
				$pw = md5($pw);
		}
		#	$file = "../temp/pw.txt";
#	$txt =  "$id $pw \n";
#	file_put_contents($file, $txt, FILE_APPEND);
		return ($pw);
	}
}
if (!function_exists('find_beholdning')) {
	function find_beholdning($vare_id, $udskriv)
	{
		/**
		 * Fetches the current stock levels of an item based on various sales and purchase orders.
		 * This function calculates and returns the stock levels by evaluating open and closed orders
		 * and the quantity of items related to those orders.
		 * 
		 * The stock levels are categorized into:
		 * - In sales offer
		 * - Sales offer #
		 * - In sales order
		 * - Sales order #
		 * - In buy proposal
		 * - Buy proposal #
		 * - In buy order
		 * - Buy order #
		 * 
		 * @param int $vare_id - The item ID for which the stock levels are being calculated.
		 * @param bool $udskriv - Flag to indicate whether the results should be printed (not used in the function).
		 * 
		 * @return array $beholdning - An associative array with stock levels for various categories. 
		 * Each index represents a category (e.g., 'in sales offer', 'sales order', etc.) and contains 
		 * the corresponding stock or order information.
		 */

		/*
			$x=0;
			$ordre_id=array();
			$q = db_select("select id from ordrer where status < 1 and art = 'DO'",__FILE__ . " linje " . __LINE__);
			while ($r =db_fetch_array($q)){
				$ordre_id[$x]=$r['id'];
				$x++;
			}
		*/
		$x = 0;
		$y = '';
		$beholdning[1] = 0;  // in salesoffer 
		$beholdning[2] = 0;  // sales offer#
		$beholdning[3] = 0;  // in sales order
		$beholdning[4] = 0;  // sales ordre#
		$beholdning[5] = ''; // in buy proposal  
		$beholdning[6] = ''; // buy proposal#
		$beholdning[7] = ''; // in buy order
		$beholdning[8] = ''; // buy order#

		$qtxt = "select ordrelinjer.id as linje_id, ordrelinjer.ordre_id as ordre_id, ordrelinjer.antal as antal,";
		$qtxt .= "ordrelinjer.leveret as leveret,ordrer.ordrenr as ordrenr,ordrer.status as status,ordrer.art as art ";
		$qtxt .= "from ordrelinjer,ordrer where ordrelinjer.vare_id = $vare_id and ordrer.id=ordrelinjer.ordre_id";
		$query2 = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($row2 = db_fetch_array($query2)) {
			if ($row2['status'] < 1 && $row2['art'] == 'DO') {
				$beholdning[1] += $row2['antal'];
				($beholdning[5]) ? $beholdning[5] .= "," . $row2['ordrenr'] : $beholdning[5] .= $row2['ordrenr'];
			} elseif ($row2['status'] < 3 && $row2['art'] == 'DO') {
				$beholdning[2] += $row2['antal'];
				($beholdning[6]) ? $beholdning[6] .= "," . $row2['ordrenr'] : $beholdning[6] .= $row2['ordrenr'];
				$beholdning[2] -= $row2['leveret'];
				#			$query3 = db_select("select antal from batch_salg where linje_id = '$row2[linje_id]'",__FILE__ . " linje " . __LINE__);
#			while ($row3=db_fetch_array($query3)) {$beholdning[2]-=$row3['antal'];}
			} elseif ($row2['status'] < 1 && $row2['art'] == 'KO') {
				$beholdning[3] += $row2['antal'];
				($beholdning[7]) ? $beholdning[7] .= "," . $row2['ordrenr'] : $beholdning[7] .= $row2['ordrenr'];
			} elseif ($row2['status'] < 3 && $row2['art'] == 'KO') {
				$beholdning[4] += $row2['antal'];
				($beholdning[8]) ? $beholdning[8] .= "," . $row2['ordrenr'] : $beholdning[8] .= $row2['ordrenr'];
				$beholdning[4] -= $row2['leveret'];
				#			$query3 = db_select("select antal from batch_kob where linje_id = '$row2[linje_id]'",__FILE__ . " linje " . __LINE__);
#			while ($row3=db_fetch_array($query3)) {$beholdning[4]-=$row3['antal'];}
			}
		}
		return $beholdning;
	}
} #endfunc find_beholdning()

if (!function_exists('hent_shop_ordrer')) {
	function hent_shop_ordrer($shop_ordre_id, $from_date)
	{
	  global $db;
	  $qtxt = "select box4, box5, box6 from grupper where art='API'";
	  $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	  $api_fil = isset($r['box4']) ? trim($r['box4']) : 0;
	  if ($api_fil) {
		file_put_contents("../temp/$db/ny_shop.json", json_encode($r));
		if (file_exists("../temp/$db/shoptidspkt.txt")) {
		  $tidspkt = trim(file_get_contents("../temp/$db/shoptidspkt.txt"));
		} else
		  $tidspkt = 0;
		if ($tidspkt < date("U") - 300 || $shop_ordre_id) {
		  file_put_contents("../temp/$db/shoptidspkt.txt", date("U"));
		  #      $header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
		  $api_txt = "$api_fil?put_new_orders=1";
		  if ($shop_ordre_id)
			$api_txt .= "&ordre_id=$shop_ordre_id";
		  if ($from_date)
			$api_txt .= "&from_date=$from_date";
		  exec("nohup /usr/bin/wget  -O - -q --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
		  
		  if($r["box5"]){
			$api_txt = "$r[box5]?put_new_orders=1";
		  if ($shop_ordre_id)
			$api_txt .= "&ordre_id=$shop_ordre_id";
		  if ($from_date)
			$api_txt .= "&from_date=$from_date";
		  exec("nohup /usr/bin/wget  -O - -q --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
		  }
		  if($r["box6"]){
			$api_txt = "$r[box6]?put_new_orders=1";
		  if ($shop_ordre_id)
			$api_txt .= "&ordre_id=$shop_ordre_id";
		  if ($from_date)
			$api_txt .= "&from_date=$from_date";
		  exec("nohup /usr/bin/wget  -O - -q --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
		  }
		}
	  }
	}
  } #endfunc hent_shop_ordrer()
  

if (!function_exists('alert')) {
	function alert($msg)
	{
		/**
		 * Displays a JavaScript alert message on the client's browser.
		 * This function generates a JavaScript `alert()` that will display a message 
		 * to the user in a popup dialog box.
		 * 
		 * @param string $msg - The message to be displayed in the alert dialog.
		 * 
		 * @return void - This function does not return a value; it simply outputs JavaScript.
		 */
		echo "<script type='text/javascript'>alert('$msg');</script>";
	}
}

if(!function_exists("sync_shop_price")){
	function sync_shop_price($vare_id){
	  global $bruger_id,$db;
	  $costPrice = 0;
	  $log = fopen("../temp/$db/rest_api.log", "a");
	  $qtxt = "select box4, box5, box6 from grupper where art='API'";
	  fwrite($log, __FILE__ . " " . __LINE__ . " $qtxt\n");
	  $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	  $api_fil = trim($r['box4']); #20211013 $api_fil was omitted loe
	  $api_fil2 = trim($r["box5"]);
	  $api_fil3 = trim($r["box6"]);
	  if (!$api_fil) {
		fwrite($log, __FILE__ . " " . __LINE__ . " no api\n");
		fclose($log);
		return ('no api');
	  }
	  $qtxt = "SELECT varenr, kostpris, salgspris, m_type, m_rabat, retail_price, colli_webfragt, stregkode FROM varer WHERE varer.id='$vare_id'";
	  if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$salesPrice = $r['salgspris'];
		$discountType = $r['m_type'];
		$discount = $r['m_rabat'];
		$itemNo = $r['varenr'];
		$costPrice = $r['kostpris'];
		$retailPrice = $r["retail_price"];
		$webFragt = $r["colli_webfragt"];
		$stregkode = $r["stregkode"];
		$txt = "$api_fil?update_price=$shop_id&salesPrice=$salesPrice&discountType=$discountType&discount=$discount&itemNo=" . urlencode("$itemNo") . "&rand=$rand&costPrice=$costPrice&retailPrice=$retailPrice&webFragt=$webFragt&barcode=$stregkode";
		fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
		shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
		if($api_fil2){
		  $txt = "$api_fil2?update_price=$shop_id&salesPrice=$salesPrice&discountType=$discountType&discount=$discount&itemNo=" . urlencode("$itemNo") . "&rand=$rand&costPrice=$costPrice&retailPrice=$retailPrice&webFragt=$webFragt&barcode=$stregkode";
		  fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
		  shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
		}
		if($api_fil3){
		  $txt = "$api_fil3?update_price=$shop_id&salesPrice=$salesPrice&discountType=$discountType&discount=$discount&itemNo=" . urlencode("$itemNo") . "&rand=$rand&costPrice=$costPrice&retailPrice=$retailPrice&webFragt=$webFragt&barcode=$stregkode";
		  fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
		  shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
		}
	  }
	}
  }
  

if (!function_exists('sync_shop_vare')) {
	function sync_shop_vare($vare_id, $variant_id, $lager) {
		global $bruger_id,$db,$regnaar;
		$costPrice = 0;
		$log = fopen("../temp/$db/rest_api.log", "a");
		$qtxt = "select box4, box5, box6 from grupper where art='API'";
		fwrite($log, __FILE__ . " " . __LINE__ . " $qtxt\n");
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$api_fil = trim($r['box4']); #20211013 $api_fil was omitted loe
		$api_fil2 = trim($r["box5"]);
		$api_fil3 = trim($r["box6"]);
		
		if (!$api_fil) {
			fwrite($log, __FILE__ . " " . __LINE__ . " no api\n");
			fclose($log);
			return ('no api');
		}
		
		$qtxt = "select delvare,gruppe from varer where id='$vare_id'"; #20220110
		fwrite($log, __FILE__ . " " . __LINE__ . " $qtxt\n");
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$itemGroup = (int) $r['gruppe'];
		$partOfItem = $r['delvare'];
		#if ($partOfItem) echo __line__." Id $vare_id is part of another item<br>";  	
		$qtxt = "select box8 from grupper where kodenr='$itemGroup' and art = 'VG' AND fiscal_year = $regnaar";
		fwrite($log, __FILE__ . " " . __LINE__ . " $qtxt\n");
		echo $qtxt;
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if (!$r['box8']) {
			fwrite($log, __FILE__ . " " . __LINE__ . " no stock\n");
			fclose($log);
			return ('no stock');
		}
		
		$header = "User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
		if ($variant_id) {
			$qtxt = "select shop_variant from shop_varer where saldi_variant='$variant_id'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$shop_id = $r['shop_variant'];
			$qtxt = "select beholdning from lagerstatus where variant_id='$variant_id'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$variant_beholdning = $r['beholdning']; #-$antal;
			if (!$shop_id) {
				$qtxt = "select variant_stregkode from variant_varer where id='$variant_id'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$shop_id = str_replace("EAN", "", $r['variant_stregkode']);
			}
			if ($vare_id) {
				$qtxt = "select kostpris from varer where id='$vare_id'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$costPrice = $r['kostpris'];
			}
			$txt = "/usr/bin/wget --spider --no-check-certificate --header='$header' '$api_fil?update_stock=$shop_id";
			$txt .= "&stock=$variant_beholdning&stockno=$lager&stockvalue=$r[lagerbeh]&file=" . __FILE__ . "&line=" . __LINE__ . "'";
			fwrite($log, __FILE__ . " " . __LINE__ . " $txt\n");
			exec("nohup $txt > /dev/null 2>&1 &\n");
			if($api_fil2){
				$txt = "/usr/bin/wget --spider --no-check-certificate --header='$header' '$api_fil2?update_stock=$shop_id";
				$txt .= "&stock=$variant_beholdning&stockno=$lager&stockvalue=$r[lagerbeh]&file=" . __FILE__ . "&line=" . __LINE__ . "'";
				fwrite($log, __FILE__ . " " . __LINE__ . " $txt\n");
				exec("nohup $txt > /dev/null 2>&1 &\n");
			}
			if($api_fil3){
				$txt = "/usr/bin/wget --spider --no-check-certificate --header='$header' '$api_fil3?update_stock=$shop_id";
				$txt .= "&stock=$variant_beholdning&stockno=$lager&stockvalue=$r[lagerbeh]&file=" . __FILE__ . "&line=" . __LINE__ . "'";
				fwrite($log, __FILE__ . " " . __LINE__ . " $txt\n");
				exec("nohup $txt > /dev/null 2>&1 &\n");
			}
		} else {
			$qtxt = "select varer.varenr, varer.varenr_alias, varer.kostpris, varer.salgspris, varer.m_type, varer.m_rabat, lagerstatus.beholdning as stock from lagerstatus,varer ";
			$qtxt .= "where lagerstatus.vare_id='$vare_id' and lagerstatus.lager='$lager' and varer.id='$vare_id'";
			echo $qtxt;
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$stock = $r['stock'];
				$itemNo = $r['varenr'];
				$itemNoAlias = $r['varenr_alias'];
				$costPrice = $r['kostpris'];
			} #$stock=$itemNo=NULL; #20210225
			$qtxt = "select sum(beholdning) as total_stock from lagerstatus where vare_id='$vare_id'";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$totalStock = $r['total_stock'];
			}
			$qtxt = "select shop_id from shop_varer where saldi_id='$vare_id'";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
				$shop_id = $r['shop_id'];
			elseif (is_integer($itemNo))
				$shop_id = $r['itemNo'];
			else
				$shop_id = 0;

			$qtxt = "SELECT varenr, varenr_alias, kostpris, salgspris, m_type, m_rabat, retail_price, colli_webfragt, stregkode FROM varer WHERE varer.id='$vare_id'";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$salesPrice = $r['salgspris'];
				$discountType = $r['m_type'];
				$discount = $r['m_rabat'];
				$itemNo = $r['varenr'];
				$itemNoAlias = $r['varenr_alias'];
				$costPrice = $r['kostpris'];
				$retailPrice = $r["retail_price"];
				$webFragt = $r["colli_webfragt"];
				$stregkode = $r["stregkode"];
				$txt = "$api_fil?update_price=$shop_id&salesPrice=$salesPrice&discountType=$discountType&discount=$discount&itemNo=" . urlencode("$itemNo") . "&itemNoAlias=" . urlencode("$itemNoAlias") . "&rand=$rand&costPrice=$costPrice&retailPrice=$retailPrice&webFragt=$webFragt&barcode=$stregkode";
				fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
#if ($bruger_id == '-1') echo "$txt<br>";
				shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
				if($api_fil2){
					$txt = "$api_fil2?update_price=$shop_id&salesPrice=$salesPrice&discountType=$discountType&discount=$discount&itemNo=" . urlencode("$itemNo") . "&itemNoAlias=" . urlencode("$itemNoAlias") . "&rand=$rand&costPrice=$costPrice&retailPrice=$retailPrice&webFragt=$webFragt&barcode=$stregkode";
					fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
					shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
				}
				if($api_fil3){
					$txt = "$api_fil3?update_price=$shop_id&salesPrice=$salesPrice&discountType=$discountType&discount=$discount&itemNo=" . urlencode("$itemNo") . "&itemNoAlias=" . urlencode("$itemNoAlias") . "&rand=$rand&costPrice=$costPrice&retailPrice=$retailPrice&webFragt=$webFragt&barcode=$stregkode";
					fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
					shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
				}
			}
			$stock = (int)$stock;

			if ($itemNo) {
				#			if (($shop_id || $itemNo) && is_numeric($stock)) {
				$rand = rand();
				$txt = "$api_fil?sku=" . urlencode("$itemNo") . "&skuAlias=" . urlencode("$itemNoAlias") . "&costPrice=$costPrice&rand=$rand";
				fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
				shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
				if($api_fil2){
					$txt = "$api_fil2?sku=" . urlencode("$itemNo") . "&skuAlias=" . urlencode("$itemNoAlias") . "&costPrice=$costPrice&rand=$rand";
					fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
					shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
				}
				$txt = "$api_fil?update_stock=$shop_id&stock=$stock&totalStock=$totalStock";
				$txt .= "&stockno=$lager&costPrice=$costPrice&salesPrice=$salesPrice&discountType=$discountType&discount=$discount&itemNo=" . urlencode("$itemNo") . "&itemNoAlias=" . urlencode("$itemNoAlias") . "&rand=$rand&retailPrice=$retailPrice&webFragt=$webFragt&barcode=$stregkode";
				fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
				shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
				if($api_fil2){
					$txt = "$api_fil2?update_stock=$shop_id&stock=$stock&totalStock=$totalStock";
					$txt .= "&stockno=$lager&costPrice=$costPrice&salesPrice=$salesPrice&discountType=$discountType&discount=$discount&itemNo=" . urlencode("$itemNo") . "&itemNoAlias=" . urlencode("$itemNoAlias") . "&rand=$rand&retailPrice=$retailPrice&webFragt=$webFragt&barcode=$stregkode";
#if ($bruger_id == '-1') echo "$txt<br>";
				fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
				shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
				}
				if($api_fil3){
					$txt = "$api_fil3?update_stock=$shop_id&stock=$stock&totalStock=$totalStock";
					$txt .= "&stockno=$lager&costPrice=$costPrice&salesPrice=$salesPrice&discountType=$discountType&discount=$discount&itemNo=" . urlencode("$itemNo") . "&itemNoAlias=" . urlencode("$itemNoAlias") . "&rand=$rand&retailPrice=$retailPrice&webFragt=$webFragt&barcode=$stregkode";
#if ($bruger_id == '-1') echo "$txt<br>";
				fwrite($log, __FILE__ . " " . __LINE__ . " nohup curl '$txt' &\n");
				shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
				}
				if ($partOfItem) {
					$x = 0;
					$partOf = array();
					$qtxt = "select * from styklister where vare_id = '$vare_id'";
					$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$partOf[$x] = $r['indgaar_i'];
						$x++;
					}
					$y = $x;
					for ($x = 0; $x < count($partOf); $x++) { #20220603
						$qtxt = "select * from styklister where vare_id = '$partOf[$x]'";
						$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
						while ($r = db_fetch_array($q)) {
							$PartOf[$y] = $r['indgaar_i'];
							$y++;
						}
					}
					for ($x = 0; $x < count($partOf); $x++) {
						$shop_id = 0;
						$qtxt = "select varenr,varenr_alias,kostpris from varer where id = '$partOf[$x]'";
#if ($bruger_id == '-1') echo __line__." $qtxt<br>";
						if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
							$costPrice = $r['kostpris'];
							$productNo = $r['varenr'];
							$productNoAlias = $r['varenr_alias'];
						}
#if ($bruger_id == '-1') echo __line__." productNo $productNo ($r[varenr])<br>";
						$qtxt = "select shop_id from shop_varer where saldi_id = $partOf[$x]";
						if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $shop_id = $r['shop_id'];
						list($totalStock, $stock) = explode('|', getAvailable($partOf[$x], $lager));
						$txt = "$api_fil?update_stock=$shop_id&stock=$stock&totalStock=$totalStock&";
						$txt .= "stockno=$lager&costPrice=$costPrice&itemNo=" . urlencode("$productNo") . "&itemNoAlias=" . urlencode("$productNoAlias") . "&sku=" . urlencode("$productNo") . "&skuAlias=" . urlencode("$productNoAlias");
						$txt .= "&file=" . __FILE__ . "&line=" . __LINE__;
#if ($bruger_id == '-1') echo __line__." $txt<br>";
						fwrite($log, __FILE__ . " " . __LINE__ . " $txt\n");
						exec("/usr/bin/nohup curl '$txt' > /dev/null 2>&1 &\n");
						if($api_fil2){
#if ($bruger_id == '-1') echo __line__." productNo $productNo ($r[varenr])<br>";
							$txt = "$api_fil2?update_stock=$shop_id&stock=$stock&totalStock=$totalStock&";
							$txt .= "stockno=$lager&costPrice=$costPrice&itemNo=" . urlencode("$productNo") . "&itemNoAlias=" . urlencode("$productNoAlias") . "&sku=" . urlencode("$productNo") . "&skuAlias=" . urlencode("$productNoAlias");
							$txt .= "&file=" . __FILE__ . "&line=" . __LINE__;
#if ($bruger_id == '-1') echo __line__." $txt<br>";
							fwrite($log, __FILE__ . " " . __LINE__ . " $txt\n");
							exec("/usr/bin/nohup curl '$txt' > /dev/null 2>&1 &\n");
						}
						if($api_fil3){
#if ($bruger_id == '-1') echo __line__." productNo $productNo ($r[varenr])<br>";
							$txt = "$api_fil3?update_stock=$shop_id&stock=$stock&totalStock=$totalStock&";
							$txt .= "stockno=$lager&costPrice=$costPrice&itemNo=" . urlencode("$productNo") . "&itemNoAlias=" . urlencode("$productNoAlias") . "&sku=" . urlencode("$productNo") . "&skuAlias=" . urlencode("$productNoAlias");
							$txt .= "&file=" . __FILE__ . "&line=" . __LINE__;
#if ($bruger_id == '-1') echo __line__." $txt<br>";
							fwrite($log, __FILE__ . " " . __LINE__ . " $txt\n");
							exec("/usr/bin/nohup curl '$txt' > /dev/null 2>&1 &\n");
						}
						$txt = "$api_fil?costPrice=$costPrice&sku=". urlencode("$productNo") . "&skuAlias=" . urlencode("$productNoAlias"); 
#if ($bruger_id == '-1') echo __line__." $txt<br>";
						shell_exec("/usr/bin/nohup curl '$txt' > /dev/null 2>&1 &\n");
						if($api_fil2){
							$txt = "$api_fil2?costPrice=$costPrice&sku=". urlencode("$productNo") . "&skuAlias=" . urlencode("$productNoAlias"); 
#if ($bruger_id == '-1') echo __line__." $txt<br>";
							shell_exec("/usr/bin/nohup curl '$txt' > /dev/null 2>&1 &\n");
						}
						if($api_fil3){
							$txt = "$api_fil3?costPrice=$costPrice&sku=". urlencode("$productNo") . "&skuAlias=" . urlencode("$productNoAlias"); 
#if ($bruger_id == '-1') echo __line__." $txt<br>";
							shell_exec("/usr/bin/nohup curl '$txt' > /dev/null 2>&1 &\n");
						}
					}
				}
			}
		}
		return ('OK');
	}
} #endfunc sync_shop_vare()

if (!function_exists('getAvailable')) {
	function getAvailable($itemId, $stockNo)
	{
		/**
		 * Calculates the available quantity of an item and its total availability across all warehouses.
		 * It checks the stock for each part of the item and returns the available quantity in the given stock and the total available quantity across all stocks.
		 * 
		 * @param int $itemId - The ID of the item whose availability is being checked.
		 * @param string $stockNo - The warehouse number where the availability is being checked.
		 * 
		 * @return string - A pipe-separated string containing the total available quantity and the available quantity in the specified stock.
		 */

		$x = 0;
		$available = $totalAvailable = 100000;
		$qtxt = "select * from styklister where indgaar_i = '$itemId'";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$IemPart[$x] = $r['vare_id'];
			$IemQty[$x] = $r['antal'];
			$qtxt = "select gruppe from varer where id = $IemPart[$x]";
			$r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$gruppe = (int) $r2['gruppe'];
			$qtxt = "select beholdning from lagerstatus where vare_id = $IemPart[$x] and lager = '$stockNo'";
			$r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$stock = $r2['beholdning'];
			$qtxt = "select sum(beholdning) as totalstock from lagerstatus where vare_id = $IemPart[$x]";
			$r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$totalStock = $r2['totalstock'];
			if ($stock == 0 || $total_stock == 0) {
				$qtxt = "select box8 from grupper where kodenr = '$gruppe' and art = 'VG'";
				$r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$box8 = $r2['box8'];
			}
			if (!$stock && $box8)
				$available = 0;
			elseif ($stock && $stock / $IemQty[$x] < $available)
				$available = $stock / $IemQty[$x];
			if (!$totalStock && $box8)
				$totalAvailable = 0;
			elseif ($totalStock && $totalStock / $IemQty[$x] < $totalAvailable)
				$totalAvailable = $totalStock / $IemQty[$x];
			$x++;
		}
		if ($available < 0)
			$available = 0;
		$available = floor($available);
		if ($totalAvailable < 0)
			$otalAvailable = 0;
		$totalAvailable = floor($totalAvailable);
		return ($totalAvailable . "|" . $available);
	}
}

//                   --------------------------------- alert ----------------------------------
if (!function_exists('alert')) {
	function alert($msg)
	{
		echo "<script type='text/javascript'>alert('$msg');</script>";
	}
}

//                   ----------------------------- create_debtor ------------------------------
if (!function_exists('create_debtor')) {
	/**
	 * Creates a new debtor record in the 'adresser' table.
	 * This function either generates a new account number or checks if a provided one is available.
	 * It inserts the debtor's details, including company name, address, contact information, and payment terms.
	 * 
	 * @param string $kontonr - The account number for the debtor. If not provided, a new number is generated.
	 * @param string $firmanavn - The name of the debtor's company.
	 * @param string $addr1 - The first line of the debtor's address.
	 * @param string $addr2 - The second line of the debtor's address.
	 * @param string $postnr - The postal code of the debtor's location.
	 * @param string $bynavn - The city or town name of the debtor. If not provided and postal code exists, it will be fetched automatically.
	 * @param string $email - The debtor's email address.
	 * @param string $tlf - The debtor's phone number.
	 * @param string $cvrnr - The debtor's CVR (business registration) number.
	 * @param string $grp - The debtor's group or category.
	 * @param string $ean - The debtor's EAN (European Article Number).
	 * @param string $betalingsbet - The debtor's payment terms (e.g., "Net 30", "COD").
	 * @param int $betalingsdage - The number of payment days allowed for the debtor.
	 * @param string $kontakt - The name or contact information of the debtor's contact person.
	 * 
	 * @return int|null - Returns the ID of the created debtor record if successful, or NULL if there was an error.
	 */
	include_once('stdFunc/createDebitor.php');
}

//                   ----------------------------- get_next_number ------------------------------
if (!function_exists('get_next_number')) {
	function get_next_number($table, $art)
	{
		/**
		 * Generates the next available account number (kontonr) for a given 'art' (type).
		 * It checks the existing account numbers in the specified table and ensures the new number is unique.
		 * 
		 * @param string $table - The table name to search for existing account numbers.
		 * @param string $art - The type/category associated with the account numbers.
		 * 
		 * @return int - The next available account number.
		 */

		$x = 0;
		$ktonr = array();
		$qtxt = "select kontonr from $table where art='$art'";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$ktonr[$x] = $r['kontonr'];
			$x++;
		}
		$kontonr = 1000;
		while (in_array($kontonr, $ktonr)) {
			$kontonr++;

		}
		return ($kontonr);
	}
}

//                   ----------------------------- get_next_order_number ------------------------------
if (!function_exists('get_next_order_number')) {
	function get_next_order_number($art = 'DO')
	{
		/**
		 * Generates the next available order number (ordrenr) for a given 'art' (type).
		 * Uses database transactions and locking to prevent race conditions and duplicate numbers.
		 * 
		 * @param string $art - The order type ('DO', 'DK', 'KO', 'KK', 'PO', etc.)
		 * 
		 * @return int - The next available order number.
		 * @throws Exception - If unable to generate unique order number after maximum attempts.
		 */
		
		$max_attempts = 10;
		$attempt = 0;
		$ordrenr = null;
		
		// Start transaction to ensure atomicity
		transaktion('begin');
		
		try {
			while ($attempt < $max_attempts) {
				$attempt++;
				
				// Lock the ordrer table to prevent concurrent access
				db_modify("LOCK TABLE ordrer IN EXCLUSIVE MODE", __FILE__ . " linje " . __LINE__);
				
				// Get the maximum order number for the given art type
				$qtxt = "SELECT COALESCE(MAX(ordrenr), 0) as max_ordrenr FROM ordrer WHERE art = '$art'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$ordrenr = ($r['max_ordrenr'] ? $r['max_ordrenr'] : 0) + 1;
				
				// Double-check that this order number doesn't exist (extra safety)
				$qtxt = "SELECT id FROM ordrer WHERE ordrenr = '$ordrenr' AND art = '$art'";
				$check_r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				
				if (!$check_r['id']) {
					// Order number is unique, commit transaction and return
					transaktion('commit');
					return $ordrenr;
				} else {
					// Order number already exists, increment and try again
					$ordrenr++;
					usleep(rand(10000, 50000)); // Small random delay to reduce contention
				}
			}
			
			// If we get here, we couldn't generate a unique number
			transaktion('rollback');
			throw new Exception("Could not generate unique order number after $max_attempts attempts");
			
		} catch (Exception $e) {
			transaktion('rollback');
			throw $e;
		}
	}
}

//                   ----------------------------- get_next_invoice_number ------------------------------
if (!function_exists('get_next_invoice_number')) {
	function get_next_invoice_number($art = 'DO', $id = null)
	{
		/**
		 * Generates the next available invoice number (fakturanr) for a given 'art' (type).
		 * Uses database transactions and locking to prevent race conditions and duplicate numbers.
		 * Handles non-numeric fakturanr field by using string comparison and conversion.
		 * 
		 * @param string $art - The order type ('DO', 'DK', 'PO', etc.)
		 * @param int $id - The order ID to exclude from checks (optional)
		 * 
		 * @return int - The next available invoice number.
		 * @throws Exception - If unable to generate unique invoice number after maximum attempts.
		 */
		
		$max_attempts = 10;
		$attempt = 0;
		$fakturanr = null;
		
		// Start transaction to ensure atomicity
		transaktion('begin');
		
		try {
			while ($attempt < $max_attempts) {
				$attempt++;
				
				// Lock the ordrer table to prevent concurrent access
				db_modify("LOCK TABLE ordrer IN EXCLUSIVE MODE", __FILE__ . " linje " . __LINE__);
				
				// Get the maximum invoice number for the given art type
				// Since fakturanr is not numeric, we need to handle it carefully
				// Fetch all records and find max in PHP to avoid database-specific casting issues
				$qtxt = "SELECT fakturanr FROM ordrer WHERE (art = '$art' OR art = 'DK') AND fakturanr != '' AND fakturanr IS NOT NULL";
				if ($id) {
					$qtxt .= " AND id != '$id'";
				}
				$qtxt .= " ORDER BY fakturadate DESC, id DESC LIMIT 100";
				
				$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
				$fakturanr = 1; // Start with 1
				
				while ($r = db_fetch_array($q)) {
					$existing_fakturanr = (int)$r['fakturanr'];
					if ($fakturanr <= $existing_fakturanr) {
						$fakturanr = $existing_fakturanr + 1;
					}
				}
				
				// Double-check that this invoice number doesn't exist (extra safety)
				$qtxt = "SELECT id FROM ordrer WHERE (art = '$art' OR art = 'DK') AND fakturanr = '$fakturanr'";
				if ($id) {
					$qtxt .= " AND id != '$id'";
				}
				$check_r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				
				if (!$check_r['id']) {
					// Check minimum invoice number from settings
					$r = db_fetch_array(db_select("SELECT box1 FROM grupper WHERE art = 'RB' AND kodenr='1'", __FILE__ . " linje " . __LINE__));
					if ($r && $fakturanr < (int)$r['box1']) {
						$fakturanr = (int)$r['box1'];
					}
					if ($fakturanr < 1) {
						$fakturanr = 1;
					}
					
					// If order ID is provided, set the fakturanr on the order NOW while table is locked
					// This prevents race conditions between getting and setting the number
					if ($id) {
						db_modify("UPDATE ordrer SET fakturanr='$fakturanr' WHERE id='$id'", __FILE__ . " linje " . __LINE__);
					}
					
					// Invoice number is unique, commit transaction and return
					transaktion('commit');
					return $fakturanr;
				} else {
					// Invoice number already exists, increment and try again
					$fakturanr++;
					usleep(rand(10000, 50000)); // Small random delay to reduce contention
				}
			}
			
			// If we get here, we couldn't generate a unique number
			transaktion('rollback');
			throw new Exception("Could not generate unique invoice number after $max_attempts attempts");
			
		} catch (Exception $e) {
			transaktion('rollback');
			throw $e;
		}
	}
}

if (!function_exists('barcode')) {
	function barcode($stregkode)
	{
		/**
		 * Generates a barcode image (PNG) for the given barcode string.
		 * It checks if the input is a valid EAN-13 code and creates a barcode image using external tools.
		 * 
		 * The function will first check if the required tools (`barcode` or `tbarcode`) are available and if
		 * the barcode string is valid. It will then generate the barcode in EPS format and convert it to PNG.
		 * The barcode image will be saved in the `../temp/$db/` directory.
		 *
		 * @param string $stregkode - The barcode string to generate the image for.
		 * 
		 * @return string|null - The path to the generated PNG file, or null if an error occurs.
		 */

		global $bruger_id, $db, $exec_path;
		$ean13 = NULL;
		#(strpos($stregkode,';'))?$stregkoder=explode(";",$stregkode):$stregkoder[0]=$stregkode;
		$stregkoder = explode(";", $stregkode);
		$png = NULL;
		if (file_exists($exec_path . "/barcode") || (file_exists($exec_path . "/tbarcode")) && file_exists($exec_path . "/convert")) { #20140603
			$dan_kode = 1;
			if (strpos($stregkoder[0], 'æ'))
				$dan_kode = 0;
			if (strpos($stregkoder[0], 'Æ'))
				$dan_kode = 0;
			if (strpos($stregkoder[0], 'ø'))
				$dan_kode = 0;
			if (strpos($stregkoder[0], 'Ø'))
				$dan_kode = 0;
			if (strpos($stregkoder[0], 'å'))
				$dan_kode = 0;
			if (strpos($stregkoder[0], 'Å'))
				$dan_kode = 0;
			if (strpos($stregkoder[0], ' '))
				$dan_kode = 0;
			if ($dan_kode) {
				$eps = "../temp/$db/$stregkoder[0].eps";
				$png = "../temp/$db/$stregkoder[0].png";
				if (is_numeric($stregkoder[0]) && strlen($stregkoder[0]) == 13) { #20211029 is_numeric($stregkoder[0]
					$a = substr($stregkoder[0], 11, 1) + substr($stregkoder[0], 9, 1) + substr($stregkoder[0], 7, 1) + substr($stregkoder[0], 5, 1) + substr($stregkoder[0], 3, 1) + substr($stregkoder[0], 1, 1);
					$a *= 3;
					$a += substr($stregkoder[0], 10, 1) + substr($stregkoder[0], 8, 1) + substr($stregkoder[0], 6, 1) + substr($stregkoder[0], 4, 1) + substr($stregkoder[0], 2, 1) + substr($stregkoder[0], 0, 1);
					$b = 0;
					while (!is_int(($a + $b) / 10))
						$b++;
					($b == substr($stregkoder[0], 12, 1)) ? $ean13 = 1 : $ean13 = 0;
				}
				if (file_exists("../temp/$db/" . abs($bruger_id) . "_*.eps"))
					unlink("../temp/$db/" . abs($bruger_id) . "_*.eps");
				if (file_exists($exec_path . "/barcode")) {
					$barcodgen = $exec_path . "/barcode";
					($ean13) ? $ean = 'ean13' : $ean = '128';
					$ms = date("is");
					$barcodtxt = $barcodgen . " -n -E -e $ean -g 200x40 -b $stregkoder[0] -o $eps\n" . $exec_path;
					$barcodtxt .= "/convert $eps $png\n" . $exec_path . "/rm -colorspace RGB $eps\n"; #20230321
				} else {
					$barcodgen = $exec_path . "/tbarcode";
					($ean13) ? $ean = '13' : $ean = '20';
					$barcodtxt = $barcodgen . " --format=ps --barcode=$ean --text=hide --width=80 --height=15 --data=$stregkoder[0] > $eps\n" . $exec_path . "/convert $eps $png\n" . $exec_path . "/rm $eps\n";
				}
				system($barcodtxt);
			} else
				$png = NULL;
		} else {
			echo $exec_path . "/barcode not found?<br>";
		}
		return ($png);
	}
}

// Acts like the ordinary trim but also take the character Non-Breaking Space U+00A0
// Inspiration to enhance the function:
//   https://en.wikipedia.org/wiki/Whitespace_character#Unicode
if (!function_exists('trim_utf8')) {
	function trim_utf8($textstring, $htmlentity = '&nbsp;', $showiteration = FALSE)
	{
		/**
		 * Recursively trims a specified HTML entity (default `&nbsp;`) from both ends of a UTF-8 encoded string.
		 *
		 * - This function is useful for cleaning up strings that may have leading or trailing non-visible characters 
		 *   (like non-breaking spaces) which are commonly represented as HTML entities.
		 * - The function operates recursively to ensure all instances of the specified entity are removed.
		 *
		 * @param string $textstring - The input string to trim.
		 * @param string $htmlentity - The HTML entity to trim from the string (default is `&nbsp;`).
		 * @param bool   $showiteration - If set to `TRUE`, the function will output the string at each iteration 
		 *                                to help with debugging (default is `FALSE`).
		 * 
		 * @return string - The trimmed string.
		 */

		if ($showiteration)
			echo "&gt;<span style='background:#000;color:#9ff'>" . $textstring . "</span>&lt;<br />\n";
		$trimstring = $textstring;

		$nbsphtml = html_entity_decode($htmlentity, ENT_HTML401, 'UTF-8');

		if (substr($trimstring, 0, strlen($nbsphtml)) === $nbsphtml)
			$trimstring = substr($trimstring, strlen($nbsphtml));
		$chrlen = strlen($nbsphtml);
		$restlen = strlen($trimstring) - $chrlen;
		$chrlen = (-1) * $chrlen;
		if (substr($trimstring, $chrlen) === $nbsphtml)
			$trimstring = substr($trimstring, 0, $restlen);

		$trimstring = trim($trimstring);

		if ($trimstring === $textstring) {
			return $textstring;
		} else {
			return trim_utf8($trimstring, $htmlentity, $showiteration);
		}
	}
}

if (!function_exists('activeLanguage')) { //20210225
	#function activeLanguage($LangID=(true || false)){
	#function activeLanguage($LangID=true || false){
	function activeLanguage($LangID = true)
	{ #20210915
		/**
		 * Retrieves and sets the active language for the user.
		 *
		 * - The function checks the user's language setting in the `brugere` table.
		 * - If no language is set, it defaults to Danish.
		 * - It stores the active language in the `brugere` table and the `settings` table if necessary.
		 *
		 * @param bool $LangID - If `true`, returns the name of the active language; if `false`, returns the language ID.
		 * @return string|int - Returns the active language name or ID based on the value of `$LangID`.
		 */

		global $bruger_id;
		global $brugernavn;
		$active_language_id = $activeLanguage = $user_id = null;
		$user_id = abs($bruger_id); #20210517

		if ($bruger_id < 0) {
			$qtxt = "select sprog_id from brugere WHERE id = '$user_id'"; #20210505
		} else {
			$qtxt = "select sprog_id from brugere WHERE id = '$bruger_id'";
		}
		###### 20210427
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			if ($r['sprog_id'] !== null) {
				$active_language_id = $r['sprog_id'];
				$q = db_fetch_array(db_select("select id, var_name from settings where id = '$active_language_id'", __FILE__ . " linje " . __LINE__)); #20210506
				#$active_language_id= $q['id'];
				$activeLanguage = $q['var_name'];

			} elseif ($q = db_fetch_array(db_select("select id, var_name from settings where var_value = 'Danish'", __FILE__ . " linje " . __LINE__))) {
				$active_language_id = $q['id'];
				$activeLanguage = $q['var_name'];
			} else {
				db_modify("insert into settings (var_name,  var_grp , var_value , var_description, user_id)values('Danish','localization','Danish','Danish language', 0)", __FILE__ . " linje " . __LINE__);
				$q = db_fetch_array(db_select("select id, var_name from settings where var_value = 'Danish'", __FILE__ . " linje " . __LINE__));
				$active_language_id = $q['id'];
				$activeLanguage = $q['var_name'];

				if ($bruger_id < 0) {
					db_modify("update brugere set sprog_id = '$active_language_id' where id ='$user_id'", __FILE__ . " linje " . __LINE__); #20210505
				} else {
					db_modify("update brugere set sprog_id = '$active_language_id' where id ='$bruger_id'", __FILE__ . " linje " . __LINE__);
				}
			}

		} elseif ($q = db_fetch_array(db_select("select id, var_name from settings where var_value = 'Danish'", __FILE__ . " linje " . __LINE__))) { //20210501
			$active_language_id = $q['id'];
			$activeLanguage = $q['var_name'];
		} else {
			db_modify("insert into settings (var_name,  var_grp , var_value , var_description, user_id)values('Danish','localization','Danish','Danish language', 0)", __FILE__ . " linje " . __LINE__);
			$q = db_fetch_array(db_select("select id, var_name from settings where var_value = 'Danish'", __FILE__ . " linje " . __LINE__));
			$active_language_id = $q['id'];
			$activeLanguage = $q['var_name'];

			if ($bruger_id < 0) {
				db_modify("update brugere set sprog_id = '$active_language_id' where id ='$user_id'", __FILE__ . " linje " . __LINE__); #20210505
			} else {
				db_modify("update brugere set sprog_id = '$active_language_id' where id ='$bruger_id'", __FILE__ . " linje " . __LINE__);
			}
		}


		if ($LangID == true) {
			return $activeLanguage;
		} else {
			return $active_language_id;
		}
		#################
	}
}

if (!function_exists('csv_to_array')) { // 20210302
	function csv_to_array($filename = '', $delimiter = "\t")
	{
		/**
		 * Converts a CSV file to an associative array.
		 *
		 * - The first row of the CSV file is used as the header to associate with each column in the data rows.
		 * - Returns an array of associative arrays, where each array represents a row of data.
		 * - Handles cases where the number of columns in data rows matches the header.
		 *
		 * @param string $filename - The path to the CSV file to be converted.
		 * @param string $delimiter - The delimiter used in the CSV file (default is tab, `\t`).
		 *
		 * @return array|false - Returns an associative array on success or FALSE if the file can't be read.
		 */

		if (!file_exists($filename) || !is_readable($filename))
			return FALSE;

		$header = NULL;
		$data = array();
		if (($handle = fopen($filename, 'r')) !== FALSE) {
			while (($row = fgetcsv($handle, 2500, $delimiter)) !== FALSE) {
				if (!$header)
					$header = $row;
				else
					#$data[] = array_combine($header, $row);
					// $h = array_pad($row, count($header),'');
					// $data[] = array_combine($header, $h);
					if (count($row) == count($header)) { #20211009 loe corrected array_combine bug
						$data[] = array_combine($header, $row);
					}
			}
			fclose($handle);
		}
		return $data;
	}
}



if (!function_exists('engdan')) { // 20210409

	function engdan(array $g, $newLanguage)
	{
		/**
		 * Translates data based on a given language column in a multidimensional array.
		 *
		 * - Checks if the language exists in the first row of the data array.
		 * - Iterates through the data array and retrieves values from the language column.
		 * - Returns a new array with the first key being the `tekst_id` and the language column.
		 *
		 * @param array $g - The multidimensional array containing the data to be translated.
		 * @param string $newLanguage - The language column to be used for translation.
		 *
		 * @return array - Returns an associative array with the first key being `tekst_id` and the selected language.
		 */

		if (array_key_exists("$newLanguage", $g[0])) {
			$columnsNames = array_keys($g[0]);
			$tekst_id = $columnsNames[0];

			for ($i = 0; $i <= count($columnsNames) - 1; $i++) {
				if ($columnsNames[$i] == "$newLanguage")
					$languageColumn = $columnsNames[$i];
			}

			foreach ($g as $data) {
				// {
				// #$temp[$data[1]] = $data["$languageColumn"];
				// #if($data["$languageColumn"]) $lang3 = $data["$languageColumn"];
				// $temp[$data[1]] = $data["$languageColumn"] ?? "-"; #
				// #$temp[$data[1]] = $lang3; #
				// }
				if ($data[1]) {
					$temp[$data[1]] = $data["$languageColumn"];
					#$temp[$data[1]] = $data["$languageColumn"] ?? "-"; #
					#$temp[$data[1]] = $lang3; #
				}
			}
			$arr1 = array($tekst_id => $languageColumn);
			$newarr = $arr1 + $temp;
			return $newarr;
		}
	}
}

if (!function_exists('get_ip')) { #20210830
	function get_ip()
	{
		/**
		 * Retrieves the user's IP address from various headers.
		 *
		 * - First checks the `HTTP_CLIENT_IP` header for the IP address.
		 * - If not found, checks the `HTTP_X_FORWARDED_FOR` header (for proxy servers).
		 * - If neither is found, it falls back to the `REMOTE_ADDR` server variable.
		 *
		 * @return string - The user's IP address.
		 */

		$ip_address = if_isset($_SERVER,NULL,'REMOTE_ADDR');
		$proxy_ip = if_isset($_SERVER,NULL,'HTTP_X_FORWARDED_FOR');
		$client_ip = if_isset($_SERVER,NULL,'HTTP_CLIENT_IP');

		if ($client_ip) {
			$ip = $client_ip;
		} elseif ($proxy_ip) {
			$ip = $proxy_ip;
		} else {
			$ip = $ip_address;
		}
		return $ip;

	}
}

if (!function_exists('restore_user_ip')) { #20210831 + 20210909
	function restore_user_ip($user_ip, $ret_id)
	{
		/**
		 * Restores a user's IP address by removing it from the restricted list.
		 *
		 * - Checks if the provided IP address is restricted for the user.
		 * - If the IP is found in the `restricted_users` table, it removes the restriction.
		 *
		 * @param $user_ip - The IP address to restore (remove restriction).
		 * @param $ret_id - The user ID associated with the IP address.
		 *
		 * @return void
		 */

		$query = db_select("select ip_values from restricted_users where ip_values = '$user_ip' and user_id = '$ret_id' ", __FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			db_modify("delete from restricted_users where user_id = '$ret_id' and ip_values = '$user_ip'", __FILE__ . " linje " . __LINE__);
		}
	}
}

if (!function_exists('authenticate_user_ip')) { #20210901 
	function authenticate_user_ip($user_ip)
	{
		/**
		 * Checks if a user's IP address is restricted.
		 *
		 * - Compares the provided user IP address with the stored restricted IP addresses in the `restricted_users` table.
		 * - Verifies if the IP address matches the current user's ID.
		 *
		 * @param $user_ip - The IP address to check.
		 *
		 * @return bool - Returns `true` if the IP address is restricted for the user, `false` otherwise.
		 */

		global $bruger_id;

		$query = db_select("select ip_values from restricted_users where ip_values = '$user_ip' and user_id = '$bruger_id' ", __FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			return true;
		} else {
			return false;
		}
	}
}

if (!function_exists('input_ip')) { #20210908
	function input_ip($ip, $ret_id)
	{
		/**
		 * Logs a user's IP address in the database if it does not already exist.
		 *
		 * - Checks whether the provided IP address already exists in the `users_ip` table.
		 * - If the IP address is not found, it inserts a new record with the current timestamp.
		 *
		 * @param $ip - The IP address to log.
		 * @param $ret_id - The ID of the user associated with the IP address.
		 *
		 * @return void
		 */
		#global $ret_id;	
		$d = new DateTime('NOW');
		$created_ip_date = $d->format('c'); // ISO8601 formated datetime
		$query = db_select("select * from users_ip where ip_values = '$ip' ", __FILE__ . " linje " . __LINE__);
		if (!$row = db_fetch_array($query)) {
			$qtxt = "insert into users_ip (user_id,ip_values,ip_logged_date) values ('$ret_id', '$ip','$created_ip_date')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
}

if(!function_exists('get_settings_value')){
	function get_settings_value($var_name, $var_grp, $default, $user=NULL, $kasse=NULL) {
		/**
		 * Retrieves a settings value from the database or returns a default value if the setting does not exist.
		 *
		 * - Searches for a specific setting based on its name, group, and optional user or POS ID.
		 * - If the setting is found, its value is returned.
		 * - If the setting is not found, a default value is returned.
		 *
		 * @param $var_name - The name of the variable to retrieve.
		 * @param $var_grp - The group/category of the variable.
		 * @param $default - The default value to return if the setting is not found.
		 * @param $user - (Optional) The user ID associated with the variable.
		 * @param $kasse - (Optional) The POS ID associated with the variable.
		 *
		 * @return mixed - The value of the setting if found, otherwise the default value.
		 */

		$qtxt = "SELECT var_value FROM settings WHERE var_name='$var_name' AND var_grp = '$var_grp'";

		if ($user !== NULL) $qtxt = $qtxt." AND user_id=$user";
		if ($kasse !== NULL) $qtxt = $qtxt." AND pos_id=$kasse";

		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r) {
			return $r[0];
		} else {
			return $default;
		}
	}
}

if(!function_exists('check_and_sanitize_input')){
	function check_and_sanitize_input($input_name, $message, $nonce) {

		/**
		 * Checks the input length and sanitizes it.
		 *
		 * - If the input exceeds 80 characters, it sanitizes the message and shows an alert.
		 * - If the input is valid, it returns the sanitized input.
		 * - If the input is not found, it returns null.
		 *
		 * @param $input_name - The name of the input field to check.
		 * @param $message - The message to display in case of invalid input.
		 * @param $nonce - The nonce value to use in the scripts for security.
		 *
		 * @return string|null - The sanitized input if valid, or null if not found.
		 */

		if (isset($_POST[$input_name])) { 
			if (strlen($_POST[$input_name]) > 80) { 
				
				$sanitized_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
				
				echo "<script nonce='{$nonce}'>alert('$sanitized_message');</script>";
			
				echo "<script nonce='{$nonce}'>window.location.href = 'index.php';</script>";
				exit; 
			}
			
			return htmlspecialchars($_POST[$input_name], ENT_QUOTES, 'UTF-8');
		}
		
		return null;
	}
}

if(!function_exists('update_settings_value')){
        function update_settings_value($var_name, $var_grp, $var_value, $var_description, $user=NULL, $posid=NULL) {
		/**
		 * Updates or inserts a settings value in the database.
		 *
		 * - If the setting already exists, its value is updated.
		 * - If the setting does not exist, a new row is created.
		 *
		 * @param $var_name - The name of the variable being updated/inserted.
		 * @param $var_grp - The group/category of the variable.
		 * @param $var_value - The value to be stored for the variable.
		 * @param $var_description - A description of the variable.
		 * @param $user - (Optional) The user ID associated with the variable.
		 * @param $posid - (Optional) The POS ID associated with the variable.
		 *
		 * @return void
		 */
                # Expect a posted ID
                $qtxt = "SELECT var_value FROM settings WHERE var_name='$var_name' AND var_grp = '$var_grp'";
                if ($user !== NULL)  $qtxt .= " AND user_id=$user";
				if ($posid !== NULL) $qtxt .= " AND pos_id=$posid";
                $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				
                # If the row already exsists
                if ($r) {
                        $qtxt = "UPDATE settings SET var_value='$var_value' WHERE var_name='$var_name' AND var_grp = '$var_grp'";
                        if ($user !== NULL)  $qtxt .= " AND user_id=$user";
                        if ($posid !== NULL) $qtxt .= " AND pos_id=$posid";
                        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
                # If the row needs to be created in the database
                } else {
                        $qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description";
                        if ($user !== NULL)  $qtxt .= ", user_id";
                        if ($posid !== NULL) $qtxt .= ", pos_id";

                        $qtxt .= ") VALUES ('$var_name', '$var_grp', '$var_value', '$var_description'";
                        if ($user !== NULL)  $qtxt .= ", $user";
                        if ($posid !== NULL) $qtxt .= ", $posid";
                        $qtxt = $qtxt.")";

                        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
                }
        }
}

if (!function_exists('clean_phone_number')) { 
	function clean_phone_number($phoneNumber, $countryCode = "45") {
		/**
		 * Cleans and formats a phone number for use in the messaging system.
		 *
		 * - Removes spaces, plus signs, and any non-numeric characters.
		 * - Ensures the phone number includes the correct country code.
		 *
		 * @param $phoneNumber - The raw phone number to be cleaned.
		 * @param $countryCode - The country code to prepend if missing (default is "45" for Denmark).
		 *
		 * @return string - The cleaned phone number, ready for use.
		 */

		$cleanedNumber = preg_replace('/[^\d]/', '', $phoneNumber);
		if (strpos($cleanedNumber, $countryCode) !== 0) {
			$cleanedNumber = $countryCode . $cleanedNumber;
		}
		
		return $cleanedNumber;
	}
}

if (!function_exists('send_sms')) { 
	function send_sms($from, $to, $message)
	{
		/**
		* Sends a message to a phone number, automatically updates the db message counter
		*
		* @param $from - The text that will appear as the sender for the message
		* @param $to - Where the message gets sent to, automatically gets clearned
		* @param $message - The message to send, the longer the messaee the more the cost increases
		*
		* @return bool - If the system was able to send the message or not
		*/

		global $s_id;

		$to = clean_phone_number($to);

		// Validate 'message'
		if (!is_string($message) || strlen($message) > 1530) {
			throw new InvalidArgumentException("Invalid 'message' parameter. Must be a string up to 1530 characters.");
		}

		// Validate 'from'
		if (!is_string($from) || (!ctype_alnum($from) && !ctype_digit($from)) || strlen($from) > 15 || (ctype_alpha($from) && strlen($from) > 11)) {
			throw new InvalidArgumentException("Invalid 'from' parameter. Must be numeric (max 15 chars) or alphanumeric (max 11 chars).");
		}

		# Access global db
		include (get_relative().'includes/connect.php');
		$apikey = get_settings_value("apikey", "cpsms", NULL);
		include (get_relative().'includes/online.php');

		# Alert the user if it is unable to send if misconfigured
		if ($apikey == NULL) {
			echo "<script>alert('SMS network not setup on this server, please contact your system administrator with the following information:\\n\\nGlobal settings: \\\"var_name\\\": \\\"apikey\\\", \\\"var_grp\\\": \\\"cpsms\\\"');</script>";
			return false;
		}

		# Submit request to cpsms
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.cpsms.dk/v2/send");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Authorization: Basic $apikey",
			"Content-Type: application/json"
		]);
		$data = [
			"to" => $to,
			"message" => $message,
			"from" => $from
		];
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);

		// Check for errors
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		} else {
			$responseData = json_decode($response, true);

			// Extract the cost
			if (isset($responseData['success'][0]['cost'])) {
				$cost = $responseData['success'][0]['cost'];
				$regnskab = $db;

				# Update the value for the client of how manu sms they send
				include (get_relative().'includes/connect.php');
				$r=db_fetch_array(db_select("select coalesce(sms, 0) as sms from regnskab where db='$regnskab'",__FILE__ . " linje " . __LINE__));
				if ($r["sms"] == 0) {
					echo "<script>alert('Dette er den første SMS der afsendes fra Saldi i dit regnskab. Bemærk venligst at såfremt du overstiger en grænse på 10 afstente sms'ser, vil du modregnes 0,89 kr. pr. sms på din næste faktura.');</script>";
				}
				$sms = $r["sms"] + $cost;

				db_modify("update regnskab set sms = $sms where db='$regnskab'",__FILE__ . " linje " . __LINE__);
				include (get_relative().'includes/online.php');
				return true;
			} else {
				return false;
			}
		}

		curl_close($ch);
	}
}

if (!function_exists('send_email')) { 
	function send_email($to, $subject, $message)
	{
		/**
		* Sends an email to a recipient
		*
		* @param $to - The email address of the recipient
		* @param $subject - The subject of the email
		* @param $message - The message to send
		*
		* @return bool - If the system was able to send the email or not
		*/

		$headers = "From: Saldi <info@saldi.dk>\r\n";
		$headers .= "Reply-To: info@saldi.dk\r\n";
		$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

		mail($to, $subject, $message, $headers);
		if (mail($to, $subject, $message, $headers)) {
			return true;
		} else {
			echo "<script>alert('Der opstod en fejl ved afsendelse af email. Prøv igen senere.');</script>";
			return false;
		}
	}
}
?>
