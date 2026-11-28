<?php
// tests/characterization/includes/support/mysqli_escape_stand_in.php
//
// mysqli_real_escape_string() for DbEscapeStringMysqlTest where PHP has no mysqli, as in CI. It records the connection
// it was given in $GLOBALS['mysqliEscapeCalls'] and escapes as MySQL and MariaDB do with their default sql_mode.
//
// History:
// 20261105 CL/LH Created.
// 20261124 CL/ASR The stand-in is declared inside an if, so the file also parses where PHP has mysqli.

if (!function_exists('mysqli_real_escape_string')) {
	// Declared inside an if, so the file also compiles where PHP has mysqli (php -l in CI); the test loads it only where PHP has none.
	/**
	 * Escapes $string for a quoted MySQL string literal, like mysqli_real_escape_string().
	 *
	 * @param mixed  $mysql  the connection db_escape_string() passed
	 * @param string $string the text to escape
	 * @return string $string with \, NUL, newline, carriage return, quotes and Ctrl-Z backslash-escaped
	 */
	function mysqli_real_escape_string($mysql, string $string): string
	{
		$GLOBALS['mysqliEscapeCalls'][] = $mysql;
		return strtr($string, ["\\" => "\\\\", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "'" => "\\'", '"' => '\\"', "\x1a" => "\\Z"]);
	}
}
